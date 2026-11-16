<?php
// Cron-Poller: holt neue Fotos aus den hinterlegten WebDAV-Ordnern und
// hängt sie als Stationen an die jeweilige Präsentation an.
//
// Einrichtung (System-Cron, z. B. alle 5 Minuten):
//   */5 * * * * php /pfad/zu/tourlocate/app/cron/poll-webdav.php
//
// Die eigentliche Import-Logik (PROPFIND → neue JPEGs → GET → EXIF →
// verkleinern → Bild + Station anlegen) liegt in api/webdav-sync.php und
// wird auch vom „Jetzt abrufen"-Button im Editor genutzt. Hier nur der
// CLI-Rahmen: alle aktiven Verbindungen durchgehen und loggen.
//
// Hinweis: HEIC wird übersprungen (GD kann kein HEIC dekodieren);
// HEIC-Fotos importiert weiterhin der Browser-Import im Editor.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur per Kommandozeile/Cron ausführbar.\n");
}

require_once __DIR__ . '/../api/webdav-sync.php';

const TL_MAX_PER_RUN = 20; // Neue Bilder pro Lauf und Verbindung

function logline(string $msg): void
{
    echo '[' . now_iso() . '] ' . $msg . "\n";
}

$conns = db()->query('SELECT * FROM webdav_connections WHERE active = 1')->fetchAll();
logline(count($conns) . ' aktive Verbindung(en).');

foreach ($conns as $c) {
    $cid = (int) $c['id'];
    $r = tl_webdav_sync($c, TL_MAX_PER_RUN, fn(string $m) => logline("  $m"));
    if (!$r['ok']) {
        logline("Verbindung $cid: FEHLER — " . $r['error']);
        continue;
    }
    logline("Verbindung $cid: {$r['imported']} importiert, {$r['skipped']} übersprungen"
        . ($r['remaining'] > 0 ? ", {$r['remaining']} folgen in späteren Läufen" : '')
        . ($r['limitReached'] ? ' — Bild-Limit erreicht' : '') . '.');
}

logline('Fertig.');
