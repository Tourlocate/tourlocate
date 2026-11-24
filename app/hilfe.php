<?php
// Hilfe/Anleitung der Multi-User-App. Statische Seite (kein Login nötig,
// enthält keine Nutzerdaten) — verlinkt aus Dashboard und Editor.
// Zweisprachig (de/en) über L() aus api/i18n.php.
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';

// DE·EN-Umschaltung (?lang=…)
if (tl_handle_lang_switch()) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= tl_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= L('Hilfe', 'Help') ?> — Tourlocate</title>
<link rel="icon" type="image/png" href="../img/logo.png">
<style>
  body{margin:0;font-family:system-ui,sans-serif;background:#f4f6f8;color:#1a1a1a}
  .wrap{max-width:760px;margin:32px auto;padding:0 16px 40px}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:24px 26px;margin-bottom:14px}
  h1{font-size:20px;margin:0 0 6px}
  h2{font-size:16px;margin:0 0 10px}
  h3{font-size:14px;margin:14px 0 4px}
  p, li{font-size:14px;line-height:1.55;margin:6px 0}
  ul{margin:6px 0;padding-left:22px}
  a{color:#2b6cb0}
  .toc{font-size:14px;line-height:1.9}
  .kbd{display:inline-block;border:1px solid #cbd2d9;border-bottom-width:2px;border-radius:5px;
       padding:0 6px;font-size:12px;background:#f7f9fb;white-space:nowrap}
  .tip{background:#f0f7ff;border:1px solid #cfe3f8;border-radius:8px;padding:8px 12px;font-size:13px;margin:10px 0}
  .topbar{display:flex;align-items:center;justify-content:space-between;margin:0 0 10px}
</style>
</head>
<body>
<div class="wrap">

  <!-- Zurück zur Seite, von der man kam (Editor, Dashboard, …);
       ohne Verlauf (direkter Aufruf) als Fallback zum Dashboard. -->
  <div class="topbar">
    <a href="dashboard.php" id="backLink" style="font-weight:600;text-decoration:none"><?= L('‹ Zurück', '‹ Back') ?></a>
    <span style="font-size:13px;color:#888"><?= tl_lang_switcher_links() ?></span>
  </div>
  <script>
    document.getElementById('backLink').onclick = function (e) {
      if (history.length > 1) { e.preventDefault(); history.back(); }
    };
  </script>

  <div class="card">
    <h1><?= L('Hilfe &amp; Anleitung', 'Help &amp; Guide') ?></h1>
    <p><?= L(
      'Tourlocate ist ein Werkzeug, um Touren als Folge von Stationen auf einer Karte zu planen, mit Fotos zu versehen und als Präsentation zu teilen oder zu exportieren.',
      'Tourlocate is a tool for planning tours as a sequence of stops on a map, adding photos, and sharing or exporting them as a presentation.') ?></p>
    <p class="toc">
      <a href="#konto">1. <?= L('Konto &amp; Dashboard', 'Account &amp; Dashboard') ?></a><br>
      <a href="#karte">2. <?= L('Karte &amp; Stationen', 'Map &amp; Stops') ?></a><br>
      <a href="#fotos">3. <?= L('Fotos', 'Photos') ?></a><br>
      <a href="#verbindung">4. <?= L('Verbindung &amp; Routing', 'Connection &amp; Routing') ?></a><br>
      <a href="#importexport">5. <?= L('Import &amp; Export', 'Import &amp; Export') ?></a><br>
      <a href="#teilen">6. <?= L('Präsentationen teilen', 'Sharing presentations') ?></a><br>
      <a href="#live">7. <?= L('Live-Standort teilen', 'Sharing live location') ?></a><br>
      <a href="#sicherheit">8. <?= L('Sicherheit (Passwort &amp; 2FA)', 'Security (password &amp; 2FA)') ?></a><br>
      <a href="#app">9. <?= L('Android-App', 'Android app') ?></a><br>
      <a href="#tipps">10. <?= L('Tipps &amp; Besonderheiten', 'Tips &amp; special notes') ?></a>
    </p>
  </div>

  <div class="card" id="konto">
    <h2>1. <?= L('Konto &amp; Dashboard', 'Account &amp; Dashboard') ?></h2>
    <p><?= L(
      'Nach der Anmeldung landest du im <strong>Dashboard</strong>: der Übersicht deiner Präsentationen. Dort kannst du Präsentationen anlegen, öffnen, umbenennen und löschen.',
      'After signing in you land in the <strong>Dashboard</strong>: the overview of your presentations. There you can create, open, rename and delete presentations.') ?></p>
    <ul>
      <li><?= L(
        '<strong>+ Neue Präsentation</strong> legt eine leere Präsentation an und öffnet den Editor.',
        '<strong>+ New presentation</strong> creates an empty presentation and opens the editor.') ?></li>
      <li><?= L(
        '<strong>Karte</strong> öffnet die freie Karte zum Ausprobieren — mit dem Feld „Präsentation aus dieser Ansicht erstellen" kannst du die dort gesetzten Stationen direkt in eine neue Präsentation übernehmen.',
        '<strong>Map</strong> opens the free map to experiment — with the field “Create presentation from this view” you can turn the stops you placed there directly into a new presentation.') ?></li>
      <li><?= L(
        '<strong>Beim Start zeigen</strong> legt fest, was nach dem Öffnen der App zuerst erscheint: Dashboard, Karte oder die zuletzt geöffnete Ansicht.',
        '<strong>Show on start</strong> defines what appears first after opening the app: dashboard, map or the most recently opened view.') ?></li>
    </ul>
  </div>

  <div class="card" id="karte">
    <h2>2. <?= L('Karte &amp; Stationen', 'Map &amp; Stops') ?></h2>
    <h3><?= L('Station anlegen', 'Create a stop') ?></h3>
    <ul>
      <li><?= L('<strong>Computer:</strong> Doppelklick oder Rechtsklick auf die Karte.',
                '<strong>Computer:</strong> double-click or right-click on the map.') ?></li>
      <li><?= L('<strong>Handy/Tablet:</strong> Tippen oder lange drücken.',
                '<strong>Phone/tablet:</strong> tap or long-press.') ?></li>
    </ul>
    <p><?= L(
      'Im Dialog kannst du Name, Link (mit eigener Beschriftung) und ein Bild vergeben. Stationen werden automatisch durchnummeriert.',
      'In the dialog you can set a name, a link (with your own label) and an image. Stops are numbered automatically.') ?></p>
    <h3><?= L('Bearbeiten &amp; Ordnen', 'Edit &amp; order') ?></h3>
    <ul>
      <li><?= L(
        'Station antippen → im Popup <strong>Bearbeiten</strong> wählen (oder Marker auf der Karte verschieben).',
        'Tap a stop → choose <strong>Edit</strong> in the popup (or drag the marker on the map).') ?></li>
      <li><?= L(
        '<strong>Rückgängig</strong> (<span class="kbd">Strg</span>+<span class="kbd">Z</span>) macht die letzte Aktion rückgängig.',
        '<strong>Undo</strong> (<span class="kbd">Ctrl</span>+<span class="kbd">Z</span>) reverts the last action.') ?></li>
      <li><?= L(
        '<strong>Sortieren…</strong> ordnet alle Stationen nach Name oder Aufnahmedatum.',
        '<strong>Sort…</strong> orders all stops by name or capture date.') ?></li>
      <li><?= L(
        '<strong>Letzte löschen</strong> / <strong>Alles löschen</strong> entfernen Stationen.',
        '<strong>Delete last</strong> / <strong>Delete all</strong> remove stops.') ?></li>
    </ul>
    <p class="tip"><?= L(
      'Im Editor einer Präsentation gilt: Änderungen erst mit <strong>Speichern</strong> sichern — „Nicht gespeichert" in der Kopfleiste erinnert daran.',
      'In the editor of a presentation: changes are only saved with <strong>Save</strong> — “Not saved” in the header is a reminder.') ?></p>
  </div>

  <div class="card" id="fotos">
    <h2>3. <?= L('Fotos', 'Photos') ?></h2>
    <ul>
      <li><?= L(
        '<strong>Fotos importieren…</strong> (oder Fotos einfach auf die Karte ziehen): Aus den GPS-Daten der Bilder entstehen automatisch Stationen in Aufnahme-Reihenfolge. Fotos ohne GPS-Daten werden übersprungen. JPEG und HEIC (iPhone) werden unterstützt.',
        '<strong>Import photos…</strong> (or simply drag photos onto the map): the GPS data in the images automatically creates stops in capture order. Photos without GPS data are skipped. JPEG and HEIC (iPhone) are supported.') ?></li>
      <li><?= L(
        '<strong>Foto vom Standort aufnehmen:</strong> nimmt ein Foto mit der Kamera auf und legt die Station an deiner aktuellen Position an.',
        '<strong>Take photo at location:</strong> captures a photo with the camera and creates a stop at your current position.') ?></li>
      <li><?= L(
        '<strong>Cloud-Ordner (automatisch):</strong> verbindet einen WebDAV-/Nextcloud-Ordner als Bildquelle. Als Passwort ein <em>App-Passwort</em> verwenden, nicht das Konto-Passwort — bei Nextcloud unter <em>Einstellungen → Sicherheit → „Neues App-Passwort erstellen"</em>. Du kannst pro Verbindung ein eigenes anlegen und einzeln widerrufen (bei 2-Faktor-Schutz ohnehin nötig). Mit <strong>„Testen"</strong> prüfst du Verbindung und Inhalt, mit <strong>„Jetzt abrufen"</strong> startest du den Import sofort.',
        '<strong>Cloud folder (automatic):</strong> connects a WebDAV/Nextcloud folder as an image source. Use an <em>app password</em>, not your account password — in Nextcloud under <em>Settings → Security → “Create new app password”</em>. You can create a separate one per connection and revoke each individually (required anyway with two-factor protection). Use <strong>“Test”</strong> to check the connection and its content, and <strong>“Fetch now”</strong> to start the import immediately.') ?></li>
    </ul>
    <h3><?= L('So arbeitet der Cloud-Ordner', 'How the cloud folder works') ?></h3>
    <ul>
      <li><?= L(
        '<strong>Automatisch:</strong> Neue Fotos werden <strong>alle paar Minuten</strong> von selbst als Stationen ergänzt (Position aus den GPS-Daten, Reihenfolge nach Aufnahmedatum) — pro Lauf bis zu <strong>20 neue Bilder</strong>, ein großer Ordner wird also über mehrere Läufe nachgezogen. Du musst die Präsentation dafür nicht offen haben; beim nächsten Öffnen sind die neuen Stationen da.',
        '<strong>Automatic:</strong> new photos are added as stops by themselves <strong>every few minutes</strong> (position from the GPS data, order by capture date) — up to <strong>20 new images</strong> per run, so a large folder is pulled in over several runs. You don’t need to keep the presentation open; the new stops are there the next time you open it.') ?></li>
      <li><?= L(
        '<strong>Jeder Cloud-Ordner-Import nur einmal:</strong> Ein Foto wird genau einmal als Station übernommen. Löschst du diese Station wieder, bleibt sie gelöscht — weder der automatische Abruf noch <strong>„Jetzt abrufen"</strong> holen sie zurück (sonst kämen einmal entfernte Fotos ständig wieder). Willst du ein gelöschtes Cloud-Foto erneut, nutze <strong>„Fotos importieren"</strong> — oder, zum Einrichten/Testen, den Knopf <strong>„Gelöschte erneut einlesen"</strong> im Cloud-Ordner-Bereich.',
        '<strong>Each cloud-folder import only once:</strong> a photo is taken in as a stop exactly once. If you delete that stop again, it stays deleted — neither the automatic fetch nor <strong>“Fetch now”</strong> brings it back (otherwise removed photos would keep reappearing). To re-add a deleted cloud photo, use <strong>“Import photos”</strong> — or, for setup/testing, the <strong>“Re-read deleted”</strong> button in the cloud-folder section.') ?></li>
      <li><?= L(
        '<strong>Nur GPS-Fotos, nur JPEG:</strong> Bilder ohne GPS-Daten werden übersprungen; HEIC kann nur der direkte Weg „Fotos importieren". <strong>Unterordner</strong> werden nicht durchsucht.',
        '<strong>GPS photos only, JPEG only:</strong> images without GPS data are skipped; HEIC works only via the direct “Import photos” path. <strong>Subfolders</strong> are not searched.') ?></li>
      <li><?= L(
        '<strong>Grenzen:</strong> bis zu <strong>30 MB pro Datei</strong> und <strong>500 Bilder pro Präsentation</strong>. Jedes Bild wird auf etwa 200 KB verkleinert.',
        '<strong>Limits:</strong> up to <strong>30 MB per file</strong> and <strong>500 images per presentation</strong>. Each image is reduced to about 200 KB.') ?></li>
    </ul>
    <p class="tip"><?= L(
      'Die <strong>Bildqualität</strong> (Einstellungen) bestimmt, wie stark Bilder beim direkten Einfügen verkleinert werden; Cloud-Importe werden immer auf ~200 KB gebracht.',
      'The <strong>image quality</strong> (settings) determines how much images are downsized when inserted directly; cloud imports are always brought down to ~200 KB.') ?></p>
  </div>

  <div class="card" id="verbindung">
    <h2>4. <?= L('Verbindung &amp; Routing', 'Connection &amp; Routing') ?></h2>
    <p><?= L(
      'Unter <strong>Einstellungen → Verbindung</strong> wählst du, wie die Stationen verbunden werden:',
      'Under <strong>Settings → Connection</strong> you choose how the stops are connected:') ?></p>
    <ul>
      <li><?= L(
        '<strong>Route:</strong> echte Streckenführung (Straßen/Wege) mit Kilometerangabe — das Profil (Auto, Rad, zu Fuß) bestimmt die Wegewahl.',
        '<strong>Route:</strong> real routing (roads/paths) with a distance in kilometres — the profile (car, bike, on foot) determines the path.') ?></li>
      <li><?= L(
        '<strong>Luftlinie:</strong> gerade Linien zwischen den Stationen.',
        '<strong>Straight line:</strong> straight lines between the stops.') ?></li>
      <li><?= L(
        '<strong>Aufgezeichnete Strecke:</strong> nach einem GPX-Import folgt die Verbindung der importierten Aufzeichnung; Stationen rasten auf die Strecke ein.',
        '<strong>Recorded track:</strong> after a GPX import the connection follows the imported recording; stops snap onto the track.') ?></li>
      <li><?= L(
        '<strong>keine:</strong> nur die Stationen, ohne Verbindung.',
        '<strong>none:</strong> only the stops, without a connection.') ?></li>
    </ul>
  </div>

  <div class="card" id="importexport">
    <h2>5. <?= L('Import &amp; Export', 'Import &amp; Export') ?></h2>
    <h3><?= L('Import', 'Import') ?></h3>
    <ul>
      <li><?= L(
        '<strong>GeoJSON:</strong> stellt eine früher exportierte Tour vollständig wieder her (Namen, Links, Bilder).',
        '<strong>GeoJSON:</strong> fully restores a previously exported tour (names, links, images).') ?></li>
      <li><?= L(
        '<strong>GPX:</strong> übernimmt Wegpunkte als Stationen und eine enthaltene Aufzeichnung als Strecke (z. B. aus Komoot oder einer Sport-Uhr).',
        '<strong>GPX:</strong> takes waypoints as stops and any included recording as a track (e.g. from Komoot or a sports watch).') ?></li>
    </ul>
    <h3><?= L('Export', 'Export') ?></h3>
    <ul>
      <li><?= L(
        '<strong>GeoJSON:</strong> komplette Sicherung der Tour (für den Re-Import).',
        '<strong>GeoJSON:</strong> a complete backup of the tour (for re-import).') ?></li>
      <li><?= L(
        '<strong>GPX:</strong> für Navis und Portale wie Komoot.',
        '<strong>GPX:</strong> for sat-navs and portals such as Komoot.') ?></li>
      <li><?= L(
        '<strong>Ein-Datei-HTML:</strong> eine einzige HTML-Datei mit eingebetteter Karte und Bildern — funktioniert offline und ohne Tourlocate, z. B. zum Verschicken.',
        '<strong>Single-file HTML:</strong> a single HTML file with an embedded map and images — works offline and without Tourlocate, e.g. for sending.') ?></li>
      <li><?= L(
        '<strong>HTML+Bilder (ZIP):</strong> wie zuvor, aber mit den Fotos als separaten JPEG-Dateien (inklusive GPS-Daten) im Ordner <code>bilder/</code>.',
        '<strong>HTML+images (ZIP):</strong> as above, but with the photos as separate JPEG files (including GPS data) in the <code>bilder/</code> folder.') ?></li>
      <li><?= L(
        '<strong>IMS-Paket (Moodle):</strong> zum direkten Import in Lernplattformen.',
        '<strong>IMS package (Moodle):</strong> for direct import into learning platforms.') ?></li>
    </ul>
    <p class="tip"><?= L(
      'Exporte sind auch die Datensicherung: Der Server garantiert keine dauerhafte Aufbewahrung — wichtige Touren regelmäßig als GeoJSON oder ZIP sichern.',
      'Exports are also your backup: the server does not guarantee permanent storage — back up important tours regularly as GeoJSON or ZIP.') ?></p>
  </div>

  <div class="card" id="teilen">
    <h2>6. <?= L('Präsentationen teilen', 'Sharing presentations') ?></h2>
    <p><?= L('Im Editor über den Button <strong>Teilen</strong>:', 'In the editor via the <strong>Share</strong> button:') ?></p>
    <ul>
      <li><?= L(
        '<strong>Öffentlichen Link aktivieren</strong> erzeugt einen Lese-Link — wer ihn hat, sieht die Präsentation (ohne Konto, ohne Bearbeiten).',
        '<strong>Activate public link</strong> creates a read-only link — anyone who has it can view the presentation (no account, no editing).') ?></li>
      <li><?= L('Optional <strong>mit Passwort schützen</strong>.', 'Optionally <strong>protect with a password</strong>.') ?></li>
      <li><?= L(
        'Der Schalter <strong>„Download-Button für Einzelbilder"</strong> (Export-Bereich der Seitenleiste) bestimmt, ob Betrachter einzelne Fotos herunterladen können.',
        'The <strong>“Download button for individual images”</strong> toggle (export section of the sidebar) determines whether viewers can download individual photos.') ?></li>
      <li><?= L(
        'Link deaktivieren macht die Präsentation wieder privat.',
        'Deactivating the link makes the presentation private again.') ?></li>
    </ul>
  </div>

  <div class="card" id="live">
    <h2>7. <?= L('Live-Standort teilen', 'Sharing live location') ?></h2>
    <p><?= L(
      'Im Dashboard unter <strong>Live-Standort teilen</strong>: erzeugt einen zeitlich begrenzten Link (15 Minuten bis 8 Stunden, verlängerbar), unter dem Empfänger deine aktuelle Position auf einer Karte verfolgen können — ohne eigenes Konto.',
      'In the dashboard under <strong>Share live location</strong>: creates a time-limited link (15 minutes to 8 hours, extendable) under which recipients can follow your current position on a map — without an account of their own.') ?></p>
    <ul>
      <li><?= L(
        '<strong>In der Android-App</strong> läuft das Senden auch bei gesperrtem Bildschirm weiter (sichtbar an der Benachrichtigung „Dein Standort wird gerade geteilt").',
        '<strong>In the Android app</strong> sending continues even with the screen locked (visible via the notification “Your location is currently being shared”).') ?></li>
      <li><?= L(
        '<strong>Im Browser</strong> pausiert das Senden, wenn das Fenster in den Hintergrund wechselt, und läuft erst weiter, wenn das Tourlocate-Fenster wieder aktiv ist.',
        '<strong>In the browser</strong> sending pauses when the window moves to the background and only resumes when the Tourlocate window is active again.') ?></li>
      <li><?= L(
        '<strong>Teilen beenden</strong> stoppt sofort; abgelaufene Links zeigen nichts mehr.',
        '<strong>End sharing</strong> stops immediately; expired links no longer show anything.') ?></li>
    </ul>
    <p class="tip"><?= L(
      'Teile Live-Links nur mit Personen, denen du vertraust — jeder mit dem Link sieht deine Position, solange die Freigabe läuft.',
      'Only share live links with people you trust — anyone with the link sees your position as long as sharing is active.') ?></p>
  </div>

  <div class="card" id="sicherheit">
    <h2>8. <?= L('Sicherheit (Passwort &amp; 2FA)', 'Security (password &amp; 2FA)') ?></h2>
    <ul>
      <li><?= L(
        '<strong>Passwort ändern</strong> im Dashboard (mindestens 12 Zeichen).',
        '<strong>Change password</strong> in the dashboard (at least 12 characters).') ?></li>
      <li><?= L(
        '<strong>Zwei-Faktor-Authentifizierung (2FA):</strong> mit einer Authenticator-App (z. B. Aegis, FreeOTP) einrichten — beim Anmelden wird dann zusätzlich ein 6-stelliger Code abgefragt. Die Recovery-Codes sicher aufbewahren!',
        '<strong>Two-factor authentication (2FA):</strong> set up with an authenticator app (e.g. Aegis, FreeOTP) — signing in then also asks for a 6-digit code. Keep the recovery codes in a safe place!') ?></li>
      <li><?= L(
        '<strong>Angemeldet bleiben</strong> (Haken beim Login) hält die Anmeldung 30 Tage — nur auf eigenen Geräten verwenden.',
        '<strong>Stay signed in</strong> (checkbox at login) keeps you signed in for 30 days — use only on your own devices.') ?></li>
      <li><?= L(
        '<strong>Passwort vergessen?</strong> auf der Anmeldeseite schickt einen Zurücksetz-Link per E-Mail.',
        '<strong>Forgot password?</strong> on the sign-in page sends a reset link by email.') ?></li>
    </ul>
  </div>

  <div class="card" id="app">
    <h2>9. <?= L('Android-App', 'Android app') ?></h2>
    <ul>
      <li><?= L(
        'Beim ersten Start wählst du den <strong>Server</strong> (die Adresse deiner Tourlocate-Instanz). Über „Server wechseln" auf der Anmeldeseite kannst du sie später ändern.',
        'On first start you choose the <strong>server</strong> (the address of your Tourlocate instance). You can change it later via “Switch server” on the sign-in page.') ?></li>
      <li><?= L(
        'Mit <strong>Foto vom Standort aufnehmen</strong> geschossene Bilder landen zusätzlich im Geräte-Album <code>DCIM/Tourlocate</code>.',
        'Images taken with <strong>Take photo at location</strong> are additionally saved to the device album <code>DCIM/Tourlocate</code>.') ?></li>
      <li><?= L(
        'Der <strong>Live-Standort</strong> läuft in der App auch bei gesperrtem Bildschirm weiter (Benachrichtigung beachten).',
        'The <strong>live location</strong> continues in the app even with the screen locked (watch for the notification).') ?></li>
      <li><?= L(
        'Unter <strong>Beim Start zeigen</strong> (Dashboard) stellst du ein, ob die App mit dem Dashboard, der Karte oder der zuletzt geöffneten Ansicht startet.',
        'Under <strong>Show on start</strong> (dashboard) you set whether the app starts with the dashboard, the map or the most recently opened view.') ?></li>
    </ul>
  </div>

  <div class="card" id="tipps">
    <h2>10. <?= L('Tipps &amp; Besonderheiten', 'Tips &amp; special notes') ?></h2>
    <h3><?= L('iPhone &amp; iPad', 'iPhone &amp; iPad') ?></h3>
    <p><?= L(
      'Exportierte Dateien landen in der Dateien-App; GeoJSON/GPX lassen sich von dort über „Teilen" wieder importieren. Die exportierte <strong>HTML-Datei</strong> zeigt die Dateien-App-Vorschau nur eingeschränkt — besser eine App mit eingebautem Browser verwenden (z.&nbsp;B. „Documents" von Readdle) oder die Datei in eine Cloud laden und den Link in Safari öffnen.',
      'Exported files land in the Files app; GeoJSON/GPX can be imported again from there via “Share”. The exported <strong>HTML file</strong> is only shown in a limited way in the Files preview — better use an app with a built-in browser (e.g. “Documents” by Readdle) or upload the file to a cloud and open the link in Safari.') ?></p>
    <h3><?= L('DuckDuckGo-Browser (Android)', 'DuckDuckGo browser (Android)') ?></h3>
    <p><?= L(
      'Beim Export erscheint unten ein Hinweis „Tippen zum Herunterladen" — das ist normal, der Browser blockiert automatische Downloads.',
      'During export a note “Tap to download” appears at the bottom — this is normal, the browser blocks automatic downloads.') ?></p>
    <h3><?= L('Eigener Standort', 'Your own location') ?></h3>
    <p><?= L(
      'Der Knopf mit der Standort-Nadel auf der Karte zeigt die eigene Position als blauen Punkt mit Genauigkeitskreis (Browser-Ortung, nur über HTTPS).',
      'The button with the location pin on the map shows your own position as a blue dot with an accuracy circle (browser geolocation, HTTPS only).') ?></p>
    <h3><?= L('Kartenlayer &amp; Bild-Metadaten', 'Map layers &amp; image metadata') ?></h3>
    <p><?= L(
      'Alle Kartenebenen sind frei lizenziert (OSM, CyclOSM, OpenTopoMap: CC-BY-SA; Satellit: Sentinel-2 cloudless von EOX, <strong>CC BY-NC-SA</strong> — nur für nicht-kommerzielle Nutzung). Beim ZIP-Export erhalten die Bilddateien <strong>EXIF-Daten</strong> mit Stationsname, Datum und GPS-Position.',
      'All map layers are freely licensed (OSM, CyclOSM, OpenTopoMap: CC-BY-SA; satellite: Sentinel-2 cloudless by EOX, <strong>CC BY-NC-SA</strong> — non-commercial use only). On ZIP export the image files receive <strong>EXIF data</strong> with the stop name, date and GPS position.') ?></p>
  </div>

  <div class="card">
    <p style="margin:0"><a href="dashboard.php"><?= L('Zurück zum Dashboard', 'Back to dashboard') ?></a> ·
    <a href="serverregeln.php"><?= L('Serverregeln', 'Server rules') ?></a> ·
    <a href="impressum.php"><?= L('Impressum', 'Imprint') ?></a> ·
    <a href="datenschutz.php"><?= L('Datenschutz', 'Privacy') ?></a></p>
  </div>

</div>
</body>
</html>
