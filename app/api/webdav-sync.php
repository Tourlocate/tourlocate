<?php
// Gemeinsamer Kern des WebDAV-/Nextcloud-Imports: holt neue Fotos einer
// Verbindung und hängt sie als Stationen an die Präsentation an. Wird von
// ZWEI Aufrufern genutzt:
//   - cron/poll-webdav.php  (alle 5 Minuten, 20 Bilder pro Lauf)
//   - api/webdav.php action=sync ("Jetzt abrufen" im Editor, 10 pro Aufruf,
//     der Browser ruft wiederholt auf, bis nichts mehr übrig ist)
//
// Schutz gegen Überlastung: pro Lauf höchstens $maxPerRun Bilder, und pro
// Präsentation insgesamt höchstens max_images_per_presentation (config,
// Standard 500) — gilt gleichermaßen für Upload und Cloud-Import.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/webdav-client.php';
require_once __DIR__ . '/image-process.php'; // tl_normalize_jpeg, tl_store_jpeg

const TL_SYNC_TARGET_BYTES  = 200 * 1024; // Zielgröße pro Bild (~200 KB)
const TL_SYNC_HARD_MAX_SIDE = 1600;

// Im Web-Kontext kommt now_iso() aus bootstrap.php; der CLI-Poller lädt
// bootstrap bewusst nicht (keine Session/Cookies) — dann hier definieren.
if (!function_exists('now_iso')) {
    function now_iso(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}

function tl_images_limit(): int
{
    // Admin-konfigurierbar (app_settings), sonst config-Default — siehe
    // tl_max_images_per_presentation() in bootstrap.php.
    return tl_max_images_per_presentation();
}

function tl_image_count(int $pid): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM images WHERE presentation_id = ?');
    $st->execute([$pid]);
    return (int) $st->fetchColumn();
}

// EXIF-GPS (Rationalzahlen-Tripel + Ref) → Dezimalgrad
function tl_sync_exif_coord($triple, string $ref): ?float
{
    if (!is_array($triple) || count($triple) < 3) {
        return null;
    }
    $num = [];
    foreach ($triple as $part) {
        if (is_string($part) && strpos($part, '/') !== false) {
            [$a, $b] = explode('/', $part, 2);
            $num[] = (float) $b !== 0.0 ? (float) $a / (float) $b : 0.0;
        } else {
            $num[] = (float) $part;
        }
    }
    $deg = $num[0] + $num[1] / 60 + $num[2] / 3600;
    if ($ref === 'S' || $ref === 'W') {
        $deg = -$deg;
    }
    return $deg;
}

// EXIF aus JPEG-Bytes lesen → ['lat'=>?, 'lng'=>?, 'date'=>?ISO]
function tl_sync_read_exif(string $jpeg): array
{
    $out = ['lat' => null, 'lng' => null, 'date' => null];
    if (!function_exists('exif_read_data')) {
        return $out;
    }
    $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($jpeg));
    if (!is_array($exif)) {
        return $out;
    }
    if (isset($exif['GPSLatitude'], $exif['GPSLongitude'])) {
        $out['lat'] = tl_sync_exif_coord($exif['GPSLatitude'], (string) ($exif['GPSLatitudeRef'] ?? 'N'));
        $out['lng'] = tl_sync_exif_coord($exif['GPSLongitude'], (string) ($exif['GPSLongitudeRef'] ?? 'E'));
    }
    $dt = $exif['DateTimeOriginal'] ?? ($exif['DateTime'] ?? null);
    if (is_string($dt) && preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $dt, $m)) {
        $out['date'] = sprintf('%s-%s-%sT%s:%s:%sZ', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
    }
    return $out;
}

// Diagnose für eine übersprungene Datei: Was kam vom Server wirklich an?
// Beantwortet die Frage „löscht Nextcloud beim WebDAV-Abruf die EXIF-Daten?"
// mit harten Fakten (Größe, Content-Type, JPEG ja/nein, EXIF-Block da?,
// wie viele EXIF-Felder, GPS-Felder vorhanden?).
function tl_sync_diag(string $body, string $contentType): string
{
    $size = strlen($body);
    $isJpeg = $size >= 2 && $body[0] === "\xFF" && $body[1] === "\xD8";
    $kb = round($size / 1024);
    if (!$isJpeg) {
        $head = preg_replace('/[^\x20-\x7E]/', '.', substr($body, 0, 24));
        return "{$kb} KB, KEIN JPEG (Content-Type: {$contentType}; Beginn: „{$head}“)";
    }
    $exif = function_exists('exif_read_data')
        ? @exif_read_data('data://image/jpeg;base64,' . base64_encode($body)) : null;
    if (!is_array($exif)) {
        return "{$kb} KB, JPEG ok, aber KEIN lesbarer EXIF-Block (Content-Type: {$contentType})";
    }
    $n = count($exif);
    $hasGps = isset($exif['GPSLatitude']) || isset($exif['GPSLongitude']);
    if (!$hasGps) {
        return "{$kb} KB, JPEG ok, EXIF-Block vorhanden ({$n} Felder), GPS-Felder: FEHLEN (Content-Type: {$contentType})";
    }
    // GPS-Tags da — liest UNSER Parser sie? Wenn nein: Rohstruktur zeigen,
    // damit der Parser-Bug punktgenau behoben werden kann.
    $lat = isset($exif['GPSLatitude']) ? tl_sync_exif_coord($exif['GPSLatitude'], (string) ($exif['GPSLatitudeRef'] ?? 'N')) : null;
    $lng = isset($exif['GPSLongitude']) ? tl_sync_exif_coord($exif['GPSLongitude'], (string) ($exif['GPSLongitudeRef'] ?? 'E')) : null;
    if ($lat !== null && $lng !== null) {
        return "{$kb} KB, JPEG ok, EXIF-Block ({$n} Felder), GPS LESBAR: {$lat}, {$lng} (Content-Type: {$contentType})";
    }
    $show = static function ($v): string {
        if (is_array($v)) {
            $items = array_map(static fn($x) => is_scalar($x) ? (string) $x : '(' . gettype($x) . ')',
                array_slice($v, 0, 4));
            return 'array[' . count($v) . ']{' . implode('|', $items) . '}';
        }
        return is_scalar($v) ? gettype($v) . ':' . $v : '(' . gettype($v) . ')';
    };
    $dump = 'Lat=' . (isset($exif['GPSLatitude']) ? $show($exif['GPSLatitude']) : 'fehlt')
        . ' Ref=' . (isset($exif['GPSLatitudeRef']) ? $show($exif['GPSLatitudeRef']) : 'fehlt')
        . '; Lng=' . (isset($exif['GPSLongitude']) ? $show($exif['GPSLongitude']) : 'fehlt')
        . ' Ref=' . (isset($exif['GPSLongitudeRef']) ? $show($exif['GPSLongitudeRef']) : 'fehlt');
    return "{$kb} KB, JPEG ok, EXIF-Block ({$n} Felder), GPS-Tags vorhanden aber UNLESBAR — {$dump} (CT: {$contentType})";
}

// Einen Abruf-Lauf für EINE Verbindung ausführen. Schreibt last_poll_at /
// last_error / seen_files_json selbst (identisches Verhalten für Cron und
// Editor-Button). $log bekommt Detailzeilen (der Cron reicht logline durch).
//
// $ignoreSeen = true: die Merkliste (seen_files_json) beim Auswählen der
// Kandidaten übergehen und ALLE noch nicht importierten Dateien erneut
// prüfen ("Erneut einlesen"). Doppel-Importe verhindert dabei der Abgleich
// mit bereits importierten Quell-Hrefs (images.source_href).
//
// $heal = true: verwaiste Stationen wiederherstellen (bereits importiertes
// Bild, aber Station fehlt in data_json). NUR bei manuellem Abruf — der
// Hintergrund-Cron heilt NICHT, sonst holt er vom Nutzer gelöschte Stationen
// heimlich zurück.
//
// Rückgabe: ['ok'=>bool, 'error'=>?string, 'imported'=>int, 'skipped'=>int,
//            'remaining'=>int, 'limitReached'=>bool, 'listed'=>int, …]
function tl_webdav_sync(array $c, int $maxPerRun, ?callable $log = null, bool $ignoreSeen = false, bool $heal = false): array
{
    $pdo = db();
    $cid = (int) $c['id'];
    $pid = (int) $c['presentation_id'];
    $uid = (int) $c['user_id'];
    $note = $log ?? static function (string $m): void {};
    $fail = function (string $msg) use ($pdo, $cid): array {
        $pdo->prepare('UPDATE webdav_connections SET last_poll_at = ?, last_error = ? WHERE id = ?')
            ->execute([now_iso(), $msg, $cid]);
        return ['ok' => false, 'error' => $msg, 'imported' => 0, 'skipped' => 0,
                'remaining' => 0, 'limitReached' => false];
    };

    $pass = tl_decrypt($c['secret_cipher'], $c['secret_nonce']);
    if ($pass === null) {
        return $fail('Passwort nicht entschlüsselbar (Serverschlüssel geändert?).');
    }

    $res = tl_dav_request('list', $c['url'], $c['username'], $pass);
    if (!$res['ok']) {
        return $fail($res['error']);
    }
    if ($res['status'] === 401 || $res['status'] === 403) {
        return $fail('Anmeldung abgelehnt (HTTP ' . $res['status'] . ').');
    }
    if ($res['status'] >= 300) {
        return $fail('WebDAV-Server: HTTP ' . $res['status']);
    }

    $hrefs = tl_dav_list_images($res['body'], $c['url']);
    $seen = json_decode($c['seen_files_json'] ?: '[]', true) ?: [];

    // Bereits in DIESE Präsentation importierte Quell-Hrefs (Dedup).
    $impSt = $pdo->prepare('SELECT source_href FROM images WHERE presentation_id = ? AND source_href IS NOT NULL');
    $impSt->execute([$pid]);
    $importedHrefs = $impSt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Präsentations-Zustand früh laden (für Import UND Selbstheilung).
    $st = $pdo->prepare('SELECT data_json FROM presentations WHERE id = ?');
    $st->execute([$pid]);
    $dataJson = $st->fetchColumn();
    if ($dataJson === false) {
        return $fail('Ziel-Präsentation existiert nicht mehr.');
    }
    $data = json_decode((string) $dataJson, true) ?: [];
    $pois = (isset($data['pois']) && is_array($data['pois'])) ? $data['pois'] : [];

    $newPois = [];   // für den Editor: in diesem Lauf ergänzte Stationen (neu ODER geheilt)
    $recovered = 0;

    // Selbstheilung (nur manuell, $heal): Bilder, die laut DB schon in DIESE
    // Präsentation importiert wurden (source_href + Geodaten vorhanden), deren
    // Station aber nicht mehr in data_json steht (z. B. nach Löschen+Speichern
    // oder einem früheren Überschreiben), als Station WIEDER ergänzen — ohne
    // Neu-Download. So lässt sich eine gelöschte Station per „Jetzt abrufen"
    // wieder einfügen; der Cron macht das bewusst NICHT.
    if ($heal) {
        $haveImg = [];
        foreach ($pois as $p) {
            if (!empty($p['img'])) {
                $haveImg[$p['img']] = true;
            }
        }
        $recSt = $pdo->prepare('SELECT id, lat, lng, taken_at, source_href FROM images
            WHERE presentation_id = ? AND source_href IS NOT NULL AND lat IS NOT NULL AND lng IS NOT NULL');
        $recSt->execute([$pid]);
        foreach ($recSt->fetchAll() as $row) {
            $url = 'api/image.php?id=' . (int) $row['id'];
            if (isset($haveImg[$url])) {
                continue; // Station existiert bereits
            }
            $poi = [
                'lat'  => (float) $row['lat'], 'lng' => (float) $row['lng'],
                'name' => preg_replace('/\.[^.]+$/', '', rawurldecode(basename((string) $row['source_href']))),
                'link' => '', 'linkText' => '',
                'img'  => $url, 'createdAt' => (string) ($row['taken_at'] ?? ''),
            ];
            $pois[] = $poi;
            $newPois[] = $poi;
            $recovered++;
        }
        if ($recovered > 0) {
            $note("$recovered verwaiste Station(en) aus bereits importierten Bildern wiederhergestellt.");
        }
    }

    // Kandidaten: im Normalfall die noch nicht gesehenen Dateien; beim
    // „Erneut einlesen" (ignoreSeen) ALLE außer den bereits importierten.
    $exclude = $ignoreSeen ? $importedHrefs : array_merge($seen, $importedHrefs);
    $newAll = array_values(array_diff($hrefs, $exclude));
    $note(count($hrefs) . ' Bilder gelistet, ' . count($newAll)
        . ($ignoreSeen ? ' zu prüfen.' : ' neu.'));
    if (!$newAll) {
        if ($recovered > 0) {
            usort($pois, fn($a, $b) => strcmp((string) ($a['createdAt'] ?? ''), (string) ($b['createdAt'] ?? '')));
            $data['pois'] = $pois;
            $pdo->prepare('UPDATE presentations SET data_json = ?, updated_at = ? WHERE id = ?')
                ->execute([json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), now_iso(), $pid]);
        }
        $pdo->prepare('UPDATE webdav_connections SET last_poll_at = ?, last_error = NULL WHERE id = ?')
            ->execute([now_iso(), $cid]);
        return ['ok' => true, 'error' => null, 'imported' => 0, 'skipped' => 0,
                'skippedNoGps' => 0, 'skippedBadImage' => 0, 'recovered' => $recovered,
                'remaining' => 0, 'limitReached' => false,
                'listed' => count($hrefs), 'lastGetError' => null,
                'skipDiag' => null, 'newPois' => $newPois];
    }
    $new = array_slice($newAll, 0, $maxPerRun);

    // Obergrenze pro Präsentation (Überlastungsschutz, siehe config)
    $limit = tl_images_limit();
    $count = tl_image_count($pid);
    if ($count >= $limit) {
        return $fail('Bild-Limit erreicht (max. ' . $limit . ' Bilder pro Präsentation) — Import gestoppt.');
    }

    // (Präsentations-Zustand $data/$pois ist oben bereits geladen.)
    $dir = tl_data_dir() . '/uploads/' . $uid . '/' . $pid;
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        return $fail('Upload-Verzeichnis nicht anlegbar.');
    }

    $imported = 0;
    $skipped = 0;
    $skippedNoGps = 0;
    $skippedBadImage = 0;
    $limitReached = false;
    $hardError = null;
    $lastGetError = null;
    $skipDiag = null; // Diagnose der ersten ohne-GPS übersprungenen Datei
    // $newPois ist oben deklariert (enthält evtl. schon geheilte Stationen)
    foreach ($new as $href) {
        if ($count >= $limit) {
            $limitReached = true;
            break;
        }
        $fileUrl = tl_dav_href_to_url($href, $c['url']);
        $get = tl_dav_request('get', $fileUrl, $c['username'], $pass);
        if (!$get['ok'] || $get['status'] >= 300) {
            $lastGetError = $get['error'] ?? ('HTTP ' . $get['status']);
            $note("$href: Abruf fehlgeschlagen — " . $lastGetError);
            // NICHT als gesehen markieren → nächster Lauf versucht es erneut
            continue;
        }
        $seen[] = $href; // ab hier gilt die Datei als behandelt

        $exif = tl_sync_read_exif($get['body']);
        if ($exif['lat'] === null || $exif['lng'] === null) {
            $skipped++;
            $skippedNoGps++;
            if ($skipDiag === null) {
                $skipDiag = tl_sync_diag($get['body'], (string) ($get['contentType'] ?? ''));
            }
            $note("$href: kein GPS — übersprungen. [$skipDiag]");
            continue;
        }
        $small = tl_normalize_jpeg($get['body'], TL_SYNC_TARGET_BYTES, TL_SYNC_HARD_MAX_SIDE);
        if ($small === null) {
            $skipped++;
            $skippedBadImage++;
            $note("$href: nicht dekodierbar — übersprungen.");
            continue;
        }

        $name = tl_store_jpeg($dir, $small);
        if ($name === null) {
            // Abbrechen, aber bereits Importiertes unten noch festschreiben
            $hardError = 'Bild nicht speicherbar (Schreibrechte?).';
            break;
        }
        $takenAt = $exif['date'] ?? now_iso();
        $ins = $pdo->prepare(
            'INSERT INTO images (presentation_id, user_id, filename, lat, lng, taken_at, created_at, source_href)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$pid, $uid, $name, $exif['lat'], $exif['lng'], $takenAt, now_iso(), $href]);
        $imgId = (int) $pdo->lastInsertId();

        $base = preg_replace('/\.[^.]+$/', '', rawurldecode(basename($href)));
        $poi = [
            'lat' => $exif['lat'], 'lng' => $exif['lng'],
            'name' => $base, 'link' => '', 'linkText' => '',
            'img' => 'api/image.php?id=' . $imgId,
            'createdAt' => $takenAt,
        ];
        $pois[] = $poi;
        $newPois[] = $poi;
        $imported++;
        $count++;
        $note("$href: importiert (Bild $imgId).");
    }

    if ($imported > 0 || $recovered > 0) {
        // Nummerierung folgt dem Aufnahmedatum (wie sortPois 'date','asc')
        usort($pois, fn($a, $b) => strcmp((string) ($a['createdAt'] ?? ''), (string) ($b['createdAt'] ?? '')));
        $data['pois'] = $pois;
        $st = $pdo->prepare('UPDATE presentations SET data_json = ?, updated_at = ? WHERE id = ?');
        $st->execute([json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), now_iso(), $pid]);
    }

    // Fortschritt IMMER festschreiben — auch bei Abbruch mitten im Lauf,
    // sonst würden bereits importierte Bilder beim nächsten Lauf doppelt
    // angelegt (seen_files_json wäre veraltet).
    $err = $hardError
        ?? ($limitReached ? 'Bild-Limit erreicht (max. ' . $limit . ' Bilder pro Präsentation) — Import gestoppt.' : null);
    $st = $pdo->prepare('UPDATE webdav_connections SET last_poll_at = ?, last_error = ?, seen_files_json = ? WHERE id = ?');
    $st->execute([now_iso(), $err, json_encode(array_values($seen)), $cid]);

    return [
        'ok'              => $hardError === null,
        'error'           => $err,
        'imported'        => $imported,
        'recovered'       => $recovered,
        'skipped'         => $skipped,
        'skippedNoGps'    => $skippedNoGps,
        'skippedBadImage' => $skippedBadImage,
        // Noch offen = gelistet minus (bereits gesehen + importiert). GET-
        // Fehler sind NICHT in seen → zählen als offen (nächster Lauf probiert).
        'remaining'       => count(array_values(array_diff($hrefs, array_merge($seen, $importedHrefs)))),
        'limitReached'    => $limitReached,
        'listed'          => count($hrefs),
        'lastGetError'    => $lastGetError,
        'skipDiag'        => $skipDiag,
        'newPois'         => $newPois,
    ];
}
