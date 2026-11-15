<?php
// Live-Standort teilen.
//   POST api/live.php?action=start   {label?, ttl?}       → Sitzung starten
//   POST api/live.php?action=ping     {token, lat, lng, acc} → Position melden
//   POST api/live.php?action=stop     {token}              → Teilen beenden
//   GET  api/live.php?action=status                        → eigene aktive Sitzung
//   GET  api/live.php?action=read&t=…                      → öffentl. Position lesen
//
// start/ping/stop/status sind Besitzer-Aktionen (Login + CSRF nötig).
// read ist öffentlich (nur Token) — die Betrachterseite verfolgen.php
// fragt sie im Takt ab. Es wird nur die ZULETZT gemeldete Position
// gespeichert, keine Spur-Historie.

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ratelimit.php';

$action = $_GET['action'] ?? '';

// Erlaubte Ablaufzeiten in Minuten (15 min / 1 h / 8 h)
const TL_LIVE_TTLS = [15, 60, 480];

// Liefert den Zustand einer Zeile als öffentlich unbedenkliches Array
// (keine user_id, keine internen Felder).
function live_public(array $row): array
{
    $expired = $row['expires_at'] <= now_iso();
    $active  = ((int) $row['active'] === 1) && !$expired;
    $paused  = $active && (int) ($row['paused'] ?? 0) === 1;
    $out = ['ok' => true, 'active' => $active, 'expired' => $expired, 'paused' => $paused];
    if ($active && $row['last_at'] !== null) {
        $out['lat'] = (float) $row['last_lat'];
        $out['lng'] = (float) $row['last_lng'];
        $out['acc'] = $row['last_acc'] !== null ? (float) $row['last_acc'] : null;
        $out['at']  = $row['last_at'];
    }
    if ($row['label'] !== null && $row['label'] !== '') {
        $out['label'] = $row['label'];
    }
    return $out;
}

// ---- Öffentlich: Position lesen -------------------------------------
if ($action === 'read') {
    $token = (string) ($_GET['t'] ?? '');

    // Token-Raten drosseln: NICHT jeden Lesezugriff (legitimes Polling),
    // sondern nur fehlschlagende (unbekannte) Token pro IP.
    $ipKey = 'live-read:ip:' . tl_client_ip();
    tl_rate_guard($ipKey);

    $st = db()->prepare('SELECT label, last_lat, last_lng, last_acc, last_at, expires_at, active, paused FROM live_shares WHERE token = ?');
    $st->execute([$token]);
    $row = $st->fetch();
    if (!$row) {
        tl_rate_fail($ipKey, 30); // viele unbekannte Token von einer IP = Raten
        json_error('Link nicht gefunden.', 404);
    }
    json_out(live_public($row));
}

// Positions-Upload vom NATIVEN Dienst der App (läuft außerhalb der WebView,
// hat also keine Session/Cookie). Authentifiziert per Token + Secret.
if ($action === 'push') {
    $input  = read_input();
    $token  = (string) ($input['token'] ?? '');
    $secret = (string) ($input['secret'] ?? '');
    $lat = $input['lat'] ?? null;
    $lng = $input['lng'] ?? null;
    $acc = $input['acc'] ?? null;

    // Fehlversuche pro IP drosseln (Token+Secret sind unerratbar; Vorsorge).
    $ipKey = 'live-push:ip:' . tl_client_ip();
    tl_rate_guard($ipKey);

    if (!is_numeric($lat) || !is_numeric($lng)
        || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180
        || !preg_match('/^[a-f0-9]{64}$/', $secret)) {
        tl_rate_fail($ipKey, 60);
        json_error('Ungültige Anfrage.', 400);
    }
    $acc = is_numeric($acc) ? max(0.0, (float) $acc) : null;

    $st = db()->prepare('SELECT upload_secret, active, expires_at FROM live_shares WHERE token = ?');
    $st->execute([$token]);
    $row = $st->fetch();

    $secretOk = $row && $row['upload_secret'] !== null
        && hash_equals((string) $row['upload_secret'], hash('sha256', $secret));
    if (!$secretOk) {
        tl_rate_fail($ipKey, 60);
        json_error('Nicht autorisiert.', 403);
    }
    // Secret stimmt → Sitzung noch aktiv? (abgelaufen/beendet ist KEIN Fehler,
    // der Dienst soll sich dann sauber beenden.)
    if ((int) $row['active'] !== 1 || $row['expires_at'] <= now_iso()) {
        json_out(['ok' => true, 'active' => false]);
    }
    db()->prepare(
        'UPDATE live_shares SET last_lat = ?, last_lng = ?, last_acc = ?, last_at = ? WHERE token = ?'
    )->execute([(float) $lat, (float) $lng, $acc, now_iso(), $token]);
    json_out(['ok' => true, 'active' => true]);
}

// ---- Ab hier: Besitzer-Aktionen -------------------------------------
$uid = require_login();

if ($action === 'status') {
    $st = db()->prepare(
        'SELECT token, label, expires_at, paused FROM live_shares
          WHERE user_id = ? AND active = 1 AND expires_at > ?
          ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$uid, now_iso()]);
    $row = $st->fetch();
    json_out([
        'ok'    => true,
        'share' => $row ? [
            'token'     => $row['token'],
            'label'     => $row['label'],
            'expiresAt' => $row['expires_at'],
            'paused'    => (int) $row['paused'] === 1,
        ] : null,
    ]);
}

require_post();
$input = read_input();
require_csrf($input);

switch ($action) {
    case 'start': {
        $label = tl_str_limit(trim((string) ($input['label'] ?? '')), 80);
        $ttl   = (int) ($input['ttl'] ?? 60);
        if (!in_array($ttl, TL_LIVE_TTLS, true)) {
            $ttl = 60;
        }
        $expires = gmdate('Y-m-d\TH:i:s\Z', time() + $ttl * 60);

        $pdo = db();
        // Frühere Sitzungen desselben Nutzers beenden — eine neue Sitzung
        // macht alte Links sofort ungültig (Datenschutz).
        $pdo->prepare('UPDATE live_shares SET active = 0 WHERE user_id = ? AND active = 1')
            ->execute([$uid]);

        // Gelegentliches Aufräumen längst abgelaufener Zeilen (~5 %),
        // damit die Tabelle nicht unbegrenzt wächst.
        if (random_int(1, 20) === 1) {
            $pdo->prepare('DELETE FROM live_shares WHERE expires_at < ?')
                ->execute([gmdate('Y-m-d\TH:i:s\Z', time() - 86400)]);
        }

        $token = bin2hex(random_bytes(16));
        // Schreib-Secret für den nativen Upload-Dienst (App): unerratbare
        // Berechtigung, Position zu dieser Sitzung zu melden — OHNE Login/Cookie.
        // Nur der Hash wird gespeichert; der Klartext geht einmalig an den
        // Besitzer zurück und wird dort vom nativen Dienst gehalten.
        $uploadSecret = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO live_shares (user_id, token, upload_secret, label, expires_at, active, created_at)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        )->execute([$uid, $token, hash('sha256', $uploadSecret), $label !== '' ? $label : null, $expires, now_iso()]);

        json_out(['ok' => true, 'token' => $token, 'uploadSecret' => $uploadSecret,
                  'label' => $label, 'expiresAt' => $expires]);
    }

    case 'ping': {
        $token = (string) ($input['token'] ?? '');
        $lat = $input['lat'] ?? null;
        $lng = $input['lng'] ?? null;
        $acc = $input['acc'] ?? null;

        if (!is_numeric($lat) || !is_numeric($lng)
            || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            json_error('Ungültige Koordinaten.', 400);
        }
        $acc = is_numeric($acc) ? max(0.0, (float) $acc) : null;

        // Nur eigene, aktive, nicht abgelaufene Sitzung aktualisieren.
        $st = db()->prepare(
            'UPDATE live_shares
                SET last_lat = ?, last_lng = ?, last_acc = ?, last_at = ?
              WHERE token = ? AND user_id = ? AND active = 1 AND expires_at > ?'
        );
        $st->execute([(float) $lat, (float) $lng, $acc, now_iso(), $token, $uid, now_iso()]);

        if ($st->rowCount() === 0) {
            // Nichts aktualisiert → Sitzung abgelaufen/beendet/fremd.
            json_out(['ok' => true, 'active' => false]);
        }
        json_out(['ok' => true, 'active' => true]);
    }

    case 'extend': {
        $ttl = (int) ($input['ttl'] ?? 60);
        if (!in_array($ttl, TL_LIVE_TTLS, true)) {
            $ttl = 60;
        }
        // Aktive, nicht abgelaufene Sitzung des Nutzers holen und die
        // gewählte Dauer auf die RESTLAUFZEIT aufschlagen (gleicher Token,
        // damit der verschickte Link gültig bleibt).
        $st = db()->prepare(
            'SELECT token, expires_at FROM live_shares
              WHERE user_id = ? AND active = 1 AND expires_at > ?
              ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$uid, now_iso()]);
        $row = $st->fetch();
        if (!$row) {
            json_error('Keine aktive Sitzung zum Verlängern.', 404);
        }
        $base    = max(strtotime($row['expires_at']), time());
        $expires = gmdate('Y-m-d\TH:i:s\Z', $base + $ttl * 60);
        db()->prepare('UPDATE live_shares SET expires_at = ? WHERE token = ? AND user_id = ?')
            ->execute([$expires, $row['token'], $uid]);
        json_out(['ok' => true, 'token' => $row['token'], 'expiresAt' => $expires]);
    }

    case 'pause': {
        $token = (string) ($input['token'] ?? '');
        db()->prepare('UPDATE live_shares SET paused = 1 WHERE token = ? AND user_id = ? AND active = 1')
            ->execute([$token, $uid]);
        json_out(['ok' => true, 'paused' => true]);
    }

    case 'resume': {
        $token = (string) ($input['token'] ?? '');
        db()->prepare('UPDATE live_shares SET paused = 0 WHERE token = ? AND user_id = ? AND active = 1')
            ->execute([$token, $uid]);
        json_out(['ok' => true, 'paused' => false]);
    }

    case 'stop': {
        $token = (string) ($input['token'] ?? '');
        db()->prepare('UPDATE live_shares SET active = 0 WHERE token = ? AND user_id = ?')
            ->execute([$token, $uid]);
        json_out(['ok' => true]);
    }

    default:
        json_error('Unbekannte Aktion.', 400);
}
