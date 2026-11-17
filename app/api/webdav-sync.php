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
    return (int) (tl_config()['max_images_per_presentation'] ?? 500);
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

// Einen Abruf-Lauf für EINE Verbindung ausführen. Schreibt last_poll_at /
// last_error / seen_files_json selbst (identisches Verhalten für Cron und
// Editor-Button). $log bekommt Detailzeilen (der Cron reicht logline durch).
//
// $ignoreSeen = true: die Merkliste (seen_files_json) beim Auswählen der
// Kandidaten übergehen und ALLE noch nicht importierten Dateien erneut
// prüfen ("Erneut einlesen"). Doppel-Importe verhindert dabei der Abgleich
// mit bereits importierten Quell-Hrefs (images.source_href).
//
// Rückgabe: ['ok'=>bool, 'error'=>?string, 'imported'=>int, 'skipped'=>int,
//            'remaining'=>int, 'limitReached'=>bool, 'listed'=>int, …]
function tl_webdav_sync(array $c, int $maxPerRun, ?callable $log = null, bool $ignoreSeen = false): array
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

    // Kandidaten: im Normalfall die noch nicht gesehenen Dateien; beim
    // „Erneut einlesen" (ignoreSeen) ALLE außer den bereits importierten.
    $exclude = $ignoreSeen ? $importedHrefs : array_merge($seen, $importedHrefs);
    $newAll = array_values(array_diff($hrefs, $exclude));
    $note(count($hrefs) . ' Bilder gelistet, ' . count($newAll)
        . ($ignoreSeen ? ' zu prüfen.' : ' neu.'));
    if (!$newAll) {
        $pdo->prepare('UPDATE webdav_connections SET last_poll_at = ?, last_error = NULL WHERE id = ?')
            ->execute([now_iso(), $cid]);
        return ['ok' => true, 'error' => null, 'imported' => 0, 'skipped' => 0,
                'skippedNoGps' => 0, 'skippedBadImage' => 0,
                'remaining' => 0, 'limitReached' => false,
                'listed' => count($hrefs), 'lastGetError' => null];
    }
    $new = array_slice($newAll, 0, $maxPerRun);

    // Obergrenze pro Präsentation (Überlastungsschutz, siehe config)
    $limit = tl_images_limit();
    $count = tl_image_count($pid);
    if ($count >= $limit) {
        return $fail('Bild-Limit erreicht (max. ' . $limit . ' Bilder pro Präsentation) — Import gestoppt.');
    }

    // Präsentations-Zustand laden
    $st = $pdo->prepare('SELECT data_json FROM presentations WHERE id = ?');
    $st->execute([$pid]);
    $dataJson = $st->fetchColumn();
    if ($dataJson === false) {
        return $fail('Ziel-Präsentation existiert nicht mehr.');
    }
    $data = json_decode((string) $dataJson, true) ?: [];
    $pois = (isset($data['pois']) && is_array($data['pois'])) ? $data['pois'] : [];

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
            $note("$href: kein GPS — übersprungen.");
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
        $pois[] = [
            'lat' => $exif['lat'], 'lng' => $exif['lng'],
            'name' => $base, 'link' => '', 'linkText' => '',
            'img' => 'api/image.php?id=' . $imgId,
            'createdAt' => $takenAt,
        ];
        $imported++;
        $count++;
        $note("$href: importiert (Bild $imgId).");
    }

    if ($imported > 0) {
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
        'skipped'         => $skipped,
        'skippedNoGps'    => $skippedNoGps,
        'skippedBadImage' => $skippedBadImage,
        // Noch offen = gelistet minus (bereits gesehen + importiert). GET-
        // Fehler sind NICHT in seen → zählen als offen (nächster Lauf probiert).
        'remaining'       => count(array_values(array_diff($hrefs, array_merge($seen, $importedHrefs)))),
        'limitReached'    => $limitReached,
        'listed'          => count($hrefs),
        'lastGetError'    => $lastGetError,
    ];
}
