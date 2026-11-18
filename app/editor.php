<?php
// Editor einer Präsentation. Bettet die Leaflet-Karte der statischen App
// ein (../js) und aktiviert über editor.js den Server-Modus.
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';

$uid = current_user_id();
if ($uid === null) {
    header('Location: index.php');
    exit;
}
// „Neu" (?new=1): leerer Editor ohne vorhandene Präsentation — angelegt wird
// erst beim Speichern (bzw. bei der Übernahme aus der freien Karte). Sonst:
// bestehende Präsentation, die dem Nutzer gehören muss.
$isNew = isset($_GET['new']);
$pid = (int) ($_GET['id'] ?? 0);
if (!$isNew) {
    $st = db()->prepare('SELECT id FROM presentations WHERE id = ? AND user_id = ?');
    $st->execute([$pid, $uid]);
    if (!$st->fetch()) {
        header('Location: dashboard.php');
        exit;
    }
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= tl_lang() ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Tourlocate — Editor</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-control-geocoder/dist/Control.Geocoder.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" type="image/png" href="../img/logo.png">
  <script>/* Kontosprache an die JS-Karte/-Editor durchreichen (VOR den Modulen,
       damit map-i18n beim Laden gleich die richtige Sprache nimmt). In der
       User-Variante bestimmt das Konto die Sprache; der Editor hat daher
       keinen eigenen Sprachknopf. */
    try { localStorage.setItem('tl-lang', <?= json_encode(tl_lang()) ?>); } catch(e){}
    /* für Start-Ansicht „Zuletzt": diese Seite merken — aber nicht den
       flüchtigen „Neu"-Modus (?new=1), sonst startete die App später in
       einem leeren Editor */
    try { if (!/[?&]new=/.test(location.search))
      localStorage.setItem('tlLast', location.pathname + location.search); } catch(e){}</script>

  <style>
    html, body { height: 100%; margin: 0; }
    body { display: flex; flex-direction: column; }
    #tl-editbar{
      flex:0 0 auto; display:flex; align-items:center; gap:12px;
      padding:8px 14px; background:#fff; border-bottom:1px solid #e2e8f0;
    }
    #tl-editbar .back{ text-decoration:none; color:#2b6cb0; font-weight:600; white-space:nowrap; }
    #tl-editbar .brand-img{ width:28px; height:28px; border-radius:50%; }
    /* Titel wird nicht hier bearbeitet (Umbenennen gibt es im Dashboard) —
       das Eingabefeld bleibt nur unsichtbar im DOM, weil editor.js den Wert
       beim Laden/Speichern weiter nutzt. */
    #titleInput{ display:none; }
    /* Status füllt die Mitte und schiebt die Buttons nach rechts */
    #saveStatus{ flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis;
                 white-space:nowrap; text-align:right; font-size:13px; color:#4a5568; }
    #saveBtn{ background:#2b6cb0; color:#fff; border-color:#2b6cb0; font-weight:600; }
    #tl-main{ flex:1 1 auto; display:flex; flex-direction:row; min-height:0; }
    #map{ flex:1 1 auto; min-width:0; height:100%; }
    /* WebDAV-Bereich kommt erst in einer späteren Scheibe */
    #secWebdav { display:none; }
    /* Menü-Knopf unter die Editor-Kopfleiste, damit er den
       "‹ Übersicht"-Link oben links nicht verdeckt */
    #sidebarToggle { top:56px; }
    /* Schmale Geräte (App): alles bleibt in EINER Zeile — Logo weglassen,
       Buttons kompakter, Status schrumpft mit Ellipse. */
    @media (max-width: 560px){
      #tl-editbar{ gap:8px; padding:8px 10px; }
      #tl-editbar .brand-img{ display:none; }
      #shareBtn, #saveBtn{ padding:7px 10px; white-space:nowrap; }
    }
  </style>
</head>
<body>
  <div id="tl-editbar">
    <a class="back" href="dashboard.php" title="<?= L('Zurück zum Dashboard', 'Back to the dashboard') ?>"><?= L('‹ Übersicht', '‹ Overview') ?></a>
    <img class="brand-img" src="../img/logo.png" alt="" onerror="this.style.display='none'">
    <input id="titleInput" type="text" placeholder="<?= L('Titel der Präsentation', 'Presentation title') ?>">
    <span id="saveStatus"></span>
    <button id="shareBtn"><?= L('Teilen', 'Share') ?></button>
    <button id="saveBtn"><?= L('Speichern', 'Save') ?></button>
  </div>

  <!-- Teilen-Dialog -->
  <div id="shareModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:16px">
    <div style="background:#fff;border-radius:12px;padding:22px;max-width:440px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,.3)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <strong style="font-size:16px"><?= L('Präsentation teilen', 'Share presentation') ?></strong>
        <button id="shareClose" style="border:0;background:none;font-size:22px;line-height:1;cursor:pointer;color:#666">×</button>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:14px;margin-bottom:12px">
        <input type="checkbox" id="shareToggle"> <?= L('Öffentlichen Link aktivieren', 'Enable public link') ?>
      </label>
      <div id="shareBody" style="display:none">
        <div style="display:flex;gap:6px;margin-bottom:12px">
          <input id="shareUrl" type="text" readonly style="flex:1;padding:8px;border:1px solid #cbd2d9;border-radius:7px;font:inherit;background:#f7f9fb">
          <button id="shareCopy" style="padding:8px 12px;border:1px solid #cbd2d9;border-radius:7px;background:#fff;cursor:pointer"><?= L('Kopieren', 'Copy') ?></button>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;margin-bottom:8px">
          <input type="checkbox" id="sharePwToggle"> <?= L('Mit Passwort schützen', 'Protect with password') ?>
        </label>
        <div id="sharePwRow" style="display:none;gap:6px">
          <input id="sharePw" type="text" placeholder="<?= L('Passwort', 'Password') ?>" style="flex:1;padding:8px;border:1px solid #cbd2d9;border-radius:7px;font:inherit">
          <button id="sharePwSave" style="padding:8px 12px;border:1px solid #2b6cb0;border-radius:7px;background:#2b6cb0;color:#fff;font-weight:600;cursor:pointer"><?= L('Setzen', 'Set') ?></button>
        </div>
        <p style="font-size:12px;color:#667;margin:10px 0 0"><?= L('Jeder mit diesem Link (und ggf. Passwort) kann die Präsentation ansehen — nur lesend.', 'Anyone with this link (and the password, if set) can view the presentation — read-only.') ?></p>
      </div>
      <div id="shareMsg" style="font-size:13px;color:#c0392b;min-height:1em;margin-top:8px"></div>
    </div>
  </div>

  <!-- Details-Popup: alle Fotos im Cloud-Ordner (können viele sein → scrollbar) -->
  <div id="wcFilesModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:16px">
    <div style="background:#fff;border-radius:12px;padding:20px 22px;max-width:480px;width:100%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 10px 40px rgba(0,0,0,.3)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <strong id="wcFilesTitle"><?= L('Fotos im Ordner', 'Photos in folder') ?></strong>
        <button id="wcFilesClose" style="border:0;background:none;font-size:22px;line-height:1;cursor:pointer;color:#666">×</button>
      </div>
      <ol id="wcFilesList" style="margin:0;padding-left:24px;overflow:auto;font-size:13px;line-height:1.7;word-break:break-all"></ol>
    </div>
  </div>

  <div id="tl-main">
    <button id="sidebarToggle" title="Menü">☰</button>

    <aside id="sidebar">
      <div class="sb-head">
        <span class="sb-brand">
          <img src="../img/logo.png" alt="" onerror="this.style.display='none'">
          <strong>Tourlocate</strong>
        </span>
        <button id="sidebarClose" title="Seitenleiste einklappen">«</button>
      </div>

      <p class="sb-hint" data-i18n="hint">Doppelklick / Rechtsklick (mobil: Tippen / lange drücken) = neue Station</p>

      <details open class="sb-sec">
        <summary data-i18n="secStations">Stationen</summary>
        <div class="sb-row">
          <button id="undoActionBtn" disabled data-i18n="undoAction" title="Ctrl+Z">Rückgängig</button>
        </div>
        <div class="sb-row">
          <button id="undoBtn" disabled data-i18n="undo">Letzte löschen</button>
          <button id="clearBtn" disabled data-i18n="clear">Alles löschen</button>
        </div>
        <div class="sb-row">
          <select id="sortSel" disabled>
            <option value="" data-i18n="sortPlaceholder">Sortieren…</option>
            <option value="name-asc" data-i18n="sortNameAsc">Name ↑</option>
            <option value="name-desc" data-i18n="sortNameDesc">Name ↓</option>
            <option value="date-asc" data-i18n="sortDateAsc">Datum ↑</option>
            <option value="date-desc" data-i18n="sortDateDesc">Datum ↓</option>
          </select>
        </div>
      </details>

      <details open class="sb-sec">
        <summary data-i18n="secImport">Import</summary>
        <div class="sb-row">
          <button id="importPhotosBtn" data-i18n="importPhotos">Fotos importieren…</button>
        </div>
        <div class="sb-row">
          <button id="takePhotoBtn" data-i18n="takePhoto">Foto vom Standort aufnehmen</button>
        </div>
        <div class="sb-row">
          <button id="importGeoBtn" data-i18n="importGeo">GeoJSON importieren…</button>
        </div>
        <div class="sb-row">
          <button id="importGpxBtn" data-i18n="importGpx">GPX importieren…</button>
        </div>
      </details>

      <details id="secExport" class="sb-sec">
        <summary data-i18n="secExport">Export</summary>
        <div class="sb-row">
          <button id="exportGeoBtn" disabled>GeoJSON</button>
          <button id="exportGpxBtn" disabled>GPX</button>
        </div>
        <div class="sb-row">
          <button id="exportHtmlBtn" disabled data-i18n="exportHtml">Ein-Datei-HTML</button>
        </div>
        <div class="sb-row">
          <button id="exportZipBtn" disabled data-i18n="exportZip">HTML+Bilder (ZIP)</button>
        </div>
        <div class="sb-row">
          <button id="exportImsBtn" disabled data-i18n="exportIms" title="IMS Content Package (für Moodle u. a. Lernplattformen)">IMS-Paket (Moodle)</button>
        </div>
        <label class="sb-check"><input type="checkbox" id="imgDownloadChk"><span data-i18n="imgDownloadLabel">Download-Button für Einzelbilder</span></label>
      </details>

      <details class="sb-sec">
        <summary data-i18n="secSettings">Einstellungen</summary>
        <label class="sb-field"><span data-i18n="connection">Verbindung:</span>
          <select id="lineModeSel">
            <option value="route" data-i18n="connRoute">Route</option>
            <option value="straight" data-i18n="connStraight">Luftlinie</option>
            <option value="track" data-i18n="connTrack" disabled>Aufgezeichnete Strecke</option>
            <option value="none" data-i18n="connNone">keine</option>
          </select>
        </label>
        <label class="sb-field"><span data-i18n="profile">Profil:</span>
          <select id="profileSel">
            <option value="car" data-i18n="profCar">Auto</option>
            <option value="bike" data-i18n="profBike">Rad</option>
            <option value="foot" data-i18n="profFoot">zu Fuß</option>
          </select>
        </label>
        <label class="sb-field"><span data-i18n="imgQuality">Bildqualität:</span>
          <select id="imgQualitySel">
            <option value="small" data-i18n="imgSmall">klein</option>
            <option value="medium" data-i18n="imgMedium">mittel</option>
            <option value="large" data-i18n="imgLarge">groß</option>
            <option value="original" data-i18n="imgOriginal">original</option>
          </select>
        </label>
        <!-- Kein eigener Sprachknopf in der User-Variante: die Sprache kommt
             aus dem Konto (Umschalter im Dashboard). Feld ausgeblendet. -->
        <label class="sb-field" style="display:none"><span data-i18n="language">Sprache:</span>
          <button id="langBtn" title="Deutsch / English">EN</button>
        </label>
      </details>

      <details class="sb-sec" id="secWebdavConn">
        <summary><?= L('Cloud-Ordner (automatisch)', 'Cloud folder (automatic)') ?></summary>
        <p class="sb-hint" style="margin:6px 0"><?= L(
          'Neue Fotos aus einem WebDAV-/Nextcloud-Ordner werden automatisch als Stationen ergänzt (alle paar Minuten; auch die beim Verbinden schon vorhandenen). <strong>„Jetzt abrufen"</strong> startet sofort. Unterordner werden nicht durchsucht. Als Passwort bitte ein <strong>App-Passwort</strong> verwenden, nicht dein Konto-Passwort: bei Nextcloud unter <em>Einstellungen → Sicherheit → „Neues App-Passwort erstellen"</em>. Du kannst für jede Verbindung ein eigenes anlegen und es einzeln widerrufen; bei aktivierter Zwei-Faktor-Anmeldung ist es ohnehin Pflicht. Es wird verschlüsselt auf dem Server gespeichert.',
          'New photos from a WebDAV/Nextcloud folder are added automatically as stations (every few minutes; including those already present when connecting). <strong>“Fetch now”</strong> starts immediately. Subfolders are not scanned. As the password please use an <strong>app password</strong>, not your account password: in Nextcloud under <em>Settings → Security → “Create new app password”</em>. You can create a separate one per connection and revoke it individually; with two-factor sign-in enabled it is required anyway. It is stored encrypted on the server.'
        ) ?></p>
        <p class="sb-hint" style="margin:6px 0"><?= L(
          'Jedes Cloud-Foto wird <strong>nur einmal</strong> importiert: Löschst du eine so entstandene Station wieder, bleibt sie beim nächsten Abruf gelöscht (sonst würden einmal entfernte Fotos immer wieder auftauchen). Zum erneuten Holen dann „Fotos importieren" nutzen — oder, zum Einrichten/Testen, <strong>„Gelöschte erneut einlesen"</strong>.',
          'Each cloud photo is imported <strong>only once</strong>: if you delete the resulting station, it stays deleted on the next fetch (otherwise removed photos would keep reappearing). To fetch one again, use “Import photos” — or, for setup/testing, <strong>“Re-import deleted”</strong>.'
        ) ?></p>
        <label class="sb-field-col"><span><?= L('Ordner-URL:', 'Folder URL:') ?></span>
          <input id="wcUrl" type="url" placeholder="https://cloud.example.org/remote.php/dav/files/USER/Ordner/">
        </label>
        <label class="sb-field-col"><span><?= L('Benutzer:', 'User:') ?></span>
          <input id="wcUser" type="text" autocomplete="off">
        </label>
        <label class="sb-field-col"><span title="<?= L('In Nextcloud erstellen: Einstellungen → Sicherheit → „Neues App-Passwort". Pro Verbindung ein eigenes möglich, einzeln widerrufbar — nicht das Konto-Passwort.', 'Create in Nextcloud: Settings → Security → “New app password”. A separate one per connection is possible, individually revocable — not your account password.') ?>"><?= L('App-Passwort:', 'App password:') ?></span>
          <input id="wcPass" type="password" autocomplete="off" placeholder="<?= L('leer = unverändert', 'empty = unchanged') ?>">
        </label>
        <div class="sb-row">
          <button id="wcSaveBtn"><?= L('Speichern', 'Save') ?></button>
          <button id="wcTestBtn"><?= L('Testen', 'Test') ?></button>
          <button id="wcDeleteBtn" class="tl-danger"><?= L('Entfernen', 'Remove') ?></button>
        </div>
        <div class="sb-row">
          <button id="wcSyncBtn"><?= L('Jetzt abrufen', 'Fetch now') ?></button>
          <button id="wcRescanBtn" title="<?= L('Einrichtungs-/Fehlersuche-Hilfe: holt auch Fotos zurück, deren Station gelöscht wurde.', 'Setup/troubleshooting aid: also fetches back photos whose station was deleted.') ?>"><?= L('Gelöschte erneut einlesen', 'Re-import deleted') ?></button>
        </div>
        <p class="sb-hint" id="wcStatus" style="margin:6px 0"></p>
      </details>

      <details id="secWebdav" class="sb-sec">
        <summary data-i18n="secWebdav">WebDAV-Bilder (z. B. Nextcloud)</summary>
        <label class="sb-field-col"><span data-i18n="webdavUrlLabel">Ordner-URL:</span>
          <input id="webdavUrlInp" type="url">
        </label>
        <label class="sb-field-col"><span data-i18n="webdavUserLabel">Benutzer:</span>
          <input id="webdavUserInp" type="text" autocomplete="off">
        </label>
        <label class="sb-field-col"><span data-i18n="webdavPassLabel">App-Passwort:</span>
          <input id="webdavPassInp" type="password" autocomplete="off">
        </label>
        <div class="sb-row">
          <button id="webdavImportBtn" data-i18n="webdavImport">Bilder importieren</button>
        </div>
      </details>

      <div class="sb-foot">
        <button id="fullscreenBtn" data-i18n="fullscreen">Vollbild</button>
        <!-- führt im Editor zur Anleitung (hilfe.php) — editor.js überschreibt
             die Overlay-Bindung aus map-ui.js -->
        <button id="helpBtn" data-i18n="help">Hilfe</button>
      </div>
    </aside>

    <div id="map"></div>
  </div>

  <div id="routeinfo"></div>
  <div id="dropOverlay"><span data-i18n="dropHint">Fotos hier ablegen — Stationen entstehen automatisch aus den GPS-Daten</span></div>

  <input type="file" id="imgInput"  accept="image/*" hidden>
  <input type="file" id="filePhotos" accept="image/*" multiple hidden>
  <input type="file" id="fileGeo" accept=".geojson,.json,application/geo+json,application/json" hidden>
  <input type="file" id="fileGpx"  accept=".gpx,application/gpx+xml,application/xml" hidden>

  <script>window.TL_EDITOR = { pid: <?= $pid ?>, csrf: <?= json_encode($csrf) ?>, isNew: <?= $isNew ? 'true' : 'false' ?> };</script>

  <!-- Live-Standort-Hinweis: zeigt überall an, dass gerade geteilt wird,
       und hält die Ortung beim Seitenwechsel am Laufen -->
  <script>window.TL_LIVE_CSRF = window.TL_EDITOR.csrf;</script>
  <script src="live-share.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/leaflet-control-geocoder/dist/Control.Geocoder.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/@tmcw/togeojson@5.8.1/dist/togeojson.umd.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js" defer></script>

  <script type="module" src="editor.js"></script>
</body>
</html>
