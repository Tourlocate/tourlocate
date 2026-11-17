<?php
// Hilfe/Anleitung der Multi-User-App. Statische Seite (kein Login nötig,
// enthält keine Nutzerdaten) — verlinkt aus Dashboard und Editor.
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hilfe — Tourlocate</title>
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
</style>
</head>
<body>
<div class="wrap">

  <!-- Zurück zur Seite, von der man kam (Editor, Dashboard, …);
       ohne Verlauf (direkter Aufruf) als Fallback zum Dashboard. -->
  <p style="margin:0 0 10px"><a href="dashboard.php" id="backLink"
     style="font-weight:600;text-decoration:none">‹ Zurück</a></p>
  <script>
    document.getElementById('backLink').onclick = function (e) {
      if (history.length > 1) { e.preventDefault(); history.back(); }
    };
  </script>

  <div class="card">
    <h1>Hilfe &amp; Anleitung</h1>
    <p>Tourlocate ist ein Werkzeug, um Touren als Folge von Stationen auf einer
    Karte zu planen, mit Fotos zu versehen und als Präsentation zu teilen oder
    zu exportieren.</p>
    <p class="toc">
      <a href="#konto">1. Konto &amp; Dashboard</a><br>
      <a href="#karte">2. Karte &amp; Stationen</a><br>
      <a href="#fotos">3. Fotos</a><br>
      <a href="#verbindung">4. Verbindung &amp; Routing</a><br>
      <a href="#importexport">5. Import &amp; Export</a><br>
      <a href="#teilen">6. Präsentationen teilen</a><br>
      <a href="#live">7. Live-Standort teilen</a><br>
      <a href="#sicherheit">8. Sicherheit (Passwort &amp; 2FA)</a><br>
      <a href="#app">9. Android-App</a><br>
      <a href="#tipps">10. Tipps &amp; Besonderheiten</a>
    </p>
  </div>

  <div class="card" id="konto">
    <h2>1. Konto &amp; Dashboard</h2>
    <p>Nach der Anmeldung landest du im <strong>Dashboard</strong>: der Übersicht deiner
    Präsentationen. Dort kannst du Präsentationen anlegen, öffnen, umbenennen und löschen.</p>
    <ul>
      <li><strong>+ Neue Präsentation</strong> legt eine leere Präsentation an und öffnet den Editor.</li>
      <li><strong>Karte</strong> öffnet die freie Karte zum Ausprobieren — mit dem Feld
      „Präsentation aus dieser Ansicht erstellen" kannst du die dort gesetzten Stationen
      direkt in eine neue Präsentation übernehmen.</li>
      <li><strong>Beim Start zeigen</strong> legt fest, was nach dem Öffnen der App zuerst
      erscheint: Dashboard, Karte oder die zuletzt geöffnete Ansicht.</li>
    </ul>
  </div>

  <div class="card" id="karte">
    <h2>2. Karte &amp; Stationen</h2>
    <h3>Station anlegen</h3>
    <ul>
      <li><strong>Computer:</strong> Doppelklick oder Rechtsklick auf die Karte.</li>
      <li><strong>Handy/Tablet:</strong> Tippen oder lange drücken.</li>
    </ul>
    <p>Im Dialog kannst du Name, Link (mit eigener Beschriftung) und ein Bild vergeben.
    Stationen werden automatisch durchnummeriert.</p>
    <h3>Bearbeiten &amp; Ordnen</h3>
    <ul>
      <li>Station antippen → im Popup <strong>Bearbeiten</strong> wählen (oder Marker auf der Karte verschieben).</li>
      <li><strong>Rückgängig</strong> (<span class="kbd">Strg</span>+<span class="kbd">Z</span>) macht die letzte Aktion rückgängig.</li>
      <li><strong>Sortieren…</strong> ordnet alle Stationen nach Name oder Aufnahmedatum.</li>
      <li><strong>Letzte löschen</strong> / <strong>Alles löschen</strong> entfernen Stationen.</li>
    </ul>
    <p class="tip">Im Editor einer Präsentation gilt: Änderungen erst mit <strong>Speichern</strong>
    sichern — „Nicht gespeichert" in der Kopfleiste erinnert daran.</p>
  </div>

  <div class="card" id="fotos">
    <h2>3. Fotos</h2>
    <ul>
      <li><strong>Fotos importieren…</strong> (oder Fotos einfach auf die Karte ziehen):
      Aus den GPS-Daten der Bilder entstehen automatisch Stationen in Aufnahme-Reihenfolge.
      Fotos ohne GPS-Daten werden übersprungen. JPEG und HEIC (iPhone) werden unterstützt.</li>
      <li><strong>Foto vom Standort aufnehmen:</strong> nimmt ein Foto mit der Kamera auf und
      legt die Station an deiner aktuellen Position an.</li>
      <li><strong>Cloud-Ordner (automatisch):</strong> verbindet einen WebDAV-/Nextcloud-Ordner als
      Bildquelle. Bitte ein <em>App-Passwort</em> verwenden, kein Konto-Passwort (bei 2-Faktor-Schutz
      ohnehin nötig). Mit <strong>„Testen"</strong> prüfst du Verbindung und Inhalt, mit
      <strong>„Jetzt abrufen"</strong> startest du den Import sofort.</li>
    </ul>
    <h3>So arbeitet der Cloud-Ordner</h3>
    <ul>
      <li><strong>Automatisch:</strong> Neue Fotos werden <strong>alle paar Minuten</strong> von selbst
      als Stationen ergänzt (Position aus den GPS-Daten, Reihenfolge nach Aufnahmedatum) — pro Lauf
      bis zu <strong>20 neue Bilder</strong>, ein großer Ordner wird also über mehrere Läufe nachgezogen.
      Du musst die Präsentation dafür nicht offen haben; beim nächsten Öffnen sind die neuen Stationen da.</li>
      <li><strong>Jeder Cloud-Ordner-Import nur einmal:</strong> Ein Foto wird genau einmal als Station
      übernommen. Löschst du diese Station wieder, bleibt sie gelöscht — weder der automatische Abruf noch
      <strong>„Jetzt abrufen"</strong> holen sie zurück (sonst kämen einmal entfernte Fotos ständig wieder).
      Willst du ein gelöschtes Cloud-Foto erneut, nutze <strong>„Fotos importieren"</strong> — oder, zum
      Einrichten/Testen, den Knopf <strong>„Gelöschte erneut einlesen"</strong> im Cloud-Ordner-Bereich.</li>
      <li><strong>Nur GPS-Fotos, nur JPEG:</strong> Bilder ohne GPS-Daten werden übersprungen; HEIC kann nur
      der direkte Weg „Fotos importieren". <strong>Unterordner</strong> werden nicht durchsucht.</li>
      <li><strong>Grenzen:</strong> bis zu <strong>30 MB pro Datei</strong> und <strong>500 Bilder pro
      Präsentation</strong>. Jedes Bild wird auf etwa 200 KB verkleinert.</li>
    </ul>
    <p class="tip">Die <strong>Bildqualität</strong> (Einstellungen) bestimmt, wie stark Bilder beim
    direkten Einfügen verkleinert werden; Cloud-Importe werden immer auf ~200 KB gebracht.</p>
  </div>

  <div class="card" id="verbindung">
    <h2>4. Verbindung &amp; Routing</h2>
    <p>Unter <strong>Einstellungen → Verbindung</strong> wählst du, wie die Stationen verbunden werden:</p>
    <ul>
      <li><strong>Route:</strong> echte Streckenführung (Straßen/Wege) mit Kilometerangabe —
      das Profil (Auto, Rad, zu Fuß) bestimmt die Wegewahl.</li>
      <li><strong>Luftlinie:</strong> gerade Linien zwischen den Stationen.</li>
      <li><strong>Aufgezeichnete Strecke:</strong> nach einem GPX-Import folgt die Verbindung
      der importierten Aufzeichnung; Stationen rasten auf die Strecke ein.</li>
      <li><strong>keine:</strong> nur die Stationen, ohne Verbindung.</li>
    </ul>
  </div>

  <div class="card" id="importexport">
    <h2>5. Import &amp; Export</h2>
    <h3>Import</h3>
    <ul>
      <li><strong>GeoJSON:</strong> stellt eine früher exportierte Tour vollständig wieder her
      (Namen, Links, Bilder).</li>
      <li><strong>GPX:</strong> übernimmt Wegpunkte als Stationen und eine enthaltene
      Aufzeichnung als Strecke (z. B. aus Komoot oder einer Sport-Uhr).</li>
    </ul>
    <h3>Export</h3>
    <ul>
      <li><strong>GeoJSON:</strong> komplette Sicherung der Tour (für den Re-Import).</li>
      <li><strong>GPX:</strong> für Navis und Portale wie Komoot.</li>
      <li><strong>Ein-Datei-HTML:</strong> eine einzige HTML-Datei mit eingebetteter Karte und
      Bildern — funktioniert offline und ohne Tourlocate, z. B. zum Verschicken.</li>
      <li><strong>HTML+Bilder (ZIP):</strong> wie zuvor, aber mit den Fotos als separaten
      JPEG-Dateien (inklusive GPS-Daten) im Ordner <code>bilder/</code>.</li>
      <li><strong>IMS-Paket (Moodle):</strong> zum direkten Import in Lernplattformen.</li>
    </ul>
    <p class="tip">Exporte sind auch die Datensicherung: Der Server garantiert keine dauerhafte
    Aufbewahrung — wichtige Touren regelmäßig als GeoJSON oder ZIP sichern.</p>
  </div>

  <div class="card" id="teilen">
    <h2>6. Präsentationen teilen</h2>
    <p>Im Editor über den Button <strong>Teilen</strong>:</p>
    <ul>
      <li><strong>Öffentlichen Link aktivieren</strong> erzeugt einen Lese-Link — wer ihn hat,
      sieht die Präsentation (ohne Konto, ohne Bearbeiten).</li>
      <li>Optional <strong>mit Passwort schützen</strong>.</li>
      <li>Der Schalter <strong>„Download-Button für Einzelbilder"</strong> (Export-Bereich der
      Seitenleiste) bestimmt, ob Betrachter einzelne Fotos herunterladen können.</li>
      <li>Link deaktivieren macht die Präsentation wieder privat.</li>
    </ul>
  </div>

  <div class="card" id="live">
    <h2>7. Live-Standort teilen</h2>
    <p>Im Dashboard unter <strong>Live-Standort teilen</strong>: erzeugt einen zeitlich begrenzten
    Link (15 Minuten bis 8 Stunden, verlängerbar), unter dem Empfänger deine aktuelle Position
    auf einer Karte verfolgen können — ohne eigenes Konto.</p>
    <ul>
      <li><strong>In der Android-App</strong> läuft das Senden auch bei gesperrtem Bildschirm
      weiter (sichtbar an der Benachrichtigung „Dein Standort wird gerade geteilt").</li>
      <li><strong>Im Browser</strong> pausiert das Senden, wenn das Fenster in den Hintergrund
      wechselt, und läuft erst weiter, wenn das Tourlocate-Fenster wieder aktiv ist.</li>
      <li><strong>Teilen beenden</strong> stoppt sofort; abgelaufene Links zeigen nichts mehr.</li>
    </ul>
    <p class="tip">Teile Live-Links nur mit Personen, denen du vertraust — jeder mit dem Link
    sieht deine Position, solange die Freigabe läuft.</p>
  </div>

  <div class="card" id="sicherheit">
    <h2>8. Sicherheit (Passwort &amp; 2FA)</h2>
    <ul>
      <li><strong>Passwort ändern</strong> im Dashboard (mindestens 12 Zeichen).</li>
      <li><strong>Zwei-Faktor-Authentifizierung (2FA):</strong> mit einer Authenticator-App
      (z. B. Aegis, FreeOTP) einrichten — beim Anmelden wird dann zusätzlich ein 6-stelliger
      Code abgefragt. Die Recovery-Codes sicher aufbewahren!</li>
      <li><strong>Angemeldet bleiben</strong> (Haken beim Login) hält die Anmeldung 30 Tage —
      nur auf eigenen Geräten verwenden.</li>
      <li><strong>Passwort vergessen?</strong> auf der Anmeldeseite schickt einen
      Zurücksetz-Link per E-Mail.</li>
    </ul>
  </div>

  <div class="card" id="app">
    <h2>9. Android-App</h2>
    <ul>
      <li>Beim ersten Start wählst du den <strong>Server</strong> (die Adresse deiner
      Tourlocate-Instanz). Über „Server wechseln" auf der Anmeldeseite kannst du sie
      später ändern.</li>
      <li>Mit <strong>Foto vom Standort aufnehmen</strong> geschossene Bilder landen zusätzlich
      im Geräte-Album <code>DCIM/Tourlocate</code>.</li>
      <li>Der <strong>Live-Standort</strong> läuft in der App auch bei gesperrtem Bildschirm
      weiter (Benachrichtigung beachten).</li>
      <li>Unter <strong>Beim Start zeigen</strong> (Dashboard) stellst du ein, ob die App mit
      dem Dashboard, der Karte oder der zuletzt geöffneten Ansicht startet.</li>
    </ul>
  </div>

  <div class="card" id="tipps">
    <h2>10. Tipps &amp; Besonderheiten</h2>
    <h3>iPhone &amp; iPad</h3>
    <p>Exportierte Dateien landen in der Dateien-App; GeoJSON/GPX lassen sich von dort
    über „Teilen" wieder importieren. Die exportierte <strong>HTML-Datei</strong> zeigt
    die Dateien-App-Vorschau nur eingeschränkt — besser eine App mit eingebautem Browser
    verwenden (z.&nbsp;B. „Documents" von Readdle) oder die Datei in eine Cloud laden und
    den Link in Safari öffnen.</p>
    <h3>DuckDuckGo-Browser (Android)</h3>
    <p>Beim Export erscheint unten ein Hinweis „Tippen zum Herunterladen" — das ist
    normal, der Browser blockiert automatische Downloads.</p>
    <h3>Eigener Standort</h3>
    <p>Der Knopf mit der Standort-Nadel auf der Karte zeigt die eigene Position als
    blauen Punkt mit Genauigkeitskreis (Browser-Ortung, nur über HTTPS).</p>
    <h3>Kartenlayer &amp; Bild-Metadaten</h3>
    <p>Alle Kartenebenen sind frei lizenziert (OSM, CyclOSM, OpenTopoMap: CC-BY-SA;
    Satellit: Sentinel-2 cloudless von EOX, <strong>CC BY-NC-SA</strong> — nur für
    nicht-kommerzielle Nutzung). Beim ZIP-Export erhalten die Bilddateien
    <strong>EXIF-Daten</strong> mit Stationsname, Datum und GPS-Position.</p>
  </div>

  <div class="card">
    <p style="margin:0"><a href="dashboard.php">Zurück zum Dashboard</a> ·
    <a href="serverregeln.php">Serverregeln</a> ·
    <a href="impressum.php">Impressum</a> ·
    <a href="datenschutz.php">Datenschutz</a></p>
  </div>

</div>
</body>
</html>
