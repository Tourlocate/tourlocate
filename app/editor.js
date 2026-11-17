// Editor der User-Version: baut dieselbe Leaflet-Karte wie die statische
// App (Wiederverwendung der js/-Module) und schaltet den "Server-Modus"
// dazu — Bilder werden verkleinert + hochgeladen, der Zustand wird in der
// Präsentation gespeichert.

import { initMap, fitToMarkers } from '../js/map-core.js';
import { setupPOIs, createPoi, renumberAndRoute } from '../js/map-pois.js';
import { setupUI } from '../js/map-ui.js';
import { setupIO } from '../js/map-io.js';
import { getSetting, setSetting } from '../js/map-settings.js';
import { fileToTargetJpeg, dataURLToBytes } from '../js/map-utils.js';

// pid ist wandelbar: im „Neu"-Modus (?new=1) wird die Präsentation erst beim
// Speichern/Übernehmen angelegt und die id dann hier eingetragen.
let pid = window.TL_EDITOR.pid;
const { csrf, isNew } = window.TL_EDITOR;
const $ = id => document.getElementById(id);

// ---- Upload-Hook: Datei → ~200-KB-JPEG → Server → URL -----------------
// Kein EXIF-Stempeln mehr client-seitig: der Server (upload.php →
// tl_normalize_jpeg) kodiert das Bild ohnehin neu und entfernt dabei alle
// Metadaten. Geoposition und Datum gehen als Formularfelder mit und landen
// in der Datenbank (nicht im Bild).
async function imageStore(file, meta = {}) {
  const bytes = await fileToTargetJpeg(file, 200 * 1024);

  const fd = new FormData();
  fd.append('presentation_id', pid);
  fd.append('csrf', csrf);
  if (meta.lat != null) fd.append('lat', meta.lat);
  if (meta.lng != null) fd.append('lng', meta.lng);
  if (meta.date) fd.append('taken_at', meta.date);
  fd.append('image', new Blob([bytes], { type: 'image/jpeg' }), 'foto.jpg');

  const res = await fetch('api/upload.php', {
    method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd
  });
  const data = await res.json().catch(() => ({}));
  if (!data.ok) throw new Error(data.error || 'Upload fehlgeschlagen');
  return data.url; // 'api/image.php?id=X' (relativ zu /app/)
}

// ---- Zustand laden ---------------------------------------------------
async function loadPresentation(map) {
  const res = await fetch('api/presentations.php?action=get&id=' + pid, {
    headers: { 'X-CSRF-Token': csrf }
  });
  const data = await res.json().catch(() => ({}));
  if (!data.ok) { setStatus('Konnte Präsentation nicht laden.', true); return; }
  const P = data.presentation;

  $('titleInput').value = P.title || '';
  document.title = 'Tourlocate — ' + (P.title || 'Editor');

  // Einstellungen übernehmen (Verbindungsdarstellung/Profil)
  setSetting('lineMode', P.line_mode);
  setSetting('profile', P.profile);
  if ($('lineModeSel')) $('lineModeSel').value = P.line_mode;
  if ($('profileSel')) { $('profileSel').value = P.profile; $('profileSel').disabled = P.line_mode !== 'route'; }

  // Download-Button-Freigabe dieser Präsentation übernehmen
  const allowDl = !!(P.data && P.data.allowDownload);
  setSetting('imgDownload', allowDl ? 'on' : 'off');
  if ($('imgDownloadChk')) $('imgDownloadChk').checked = allowDl;

  // Aufgezeichnete Strecke (Track-Modus) VOR den POIs setzen, damit das
  // Einrasten/Routing beim Anlegen schon greift.
  map.state.track = (P.data && Array.isArray(P.data.track)) ? P.data.track : null;
  if (map.onTrackChanged) map.onTrackChanged();

  const pois = (P.data && Array.isArray(P.data.pois)) ? P.data.pois : [];
  pois.forEach(sp => createPoi(map, {
    lat: sp.lat, lng: sp.lng,
    name: sp.name || '', link: sp.link || '', linkText: sp.linkText || '',
    img: sp.img || '', createdAt: sp.createdAt || ''
  }));
  renumberAndRoute(map);
  if (pois.length) fitToMarkers(map);
  markSaved();
}

// ---- Zustand speichern ----------------------------------------------
async function save(map) {
  // Noch nicht hochgeladene Base64-Bilder (aus der Karten-Übernahme) IMMER
  // zuerst auf den Server bringen — der Server lehnt data:-Bilder in
  // data_json ab (Leitplanke gegen DB-Bloat).
  try {
    await uploadBase64Images(map);
  } catch (e) {
    setStatus('Bild-Upload fehlgeschlagen: ' + (e?.message || e), true);
    return;
  }
  setStatus('Speichern…');
  const pois = map.state.pois.map(p => ({
    lat: p.lat, lng: p.lng,
    name: p.name || '', link: p.link || '', linkText: p.linkText || '',
    img: p.img || '', createdAt: p.createdAt || ''
  }));
  const body = {
    id: pid,
    title: $('titleInput').value,
    line_mode: getSetting('lineMode'),
    profile: getSetting('profile'),
    // Routen-Geometrie mitspeichern, damit die öffentliche Ansicht bei
    // "Route" die echte Strecke zeigt (ohne dort neu routen zu müssen).
    // track = importierte Aufzeichnung (für den Track-Modus, editierbar).
    data: { pois, route: map.state.routeCoords || [], track: map.state.track || null, allowDownload: getSetting('imgDownload') === 'on' },
    csrf
  };
  const res = await fetch('api/presentations.php?action=save', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify(body)
  });
  const data = await res.json().catch(() => ({}));
  if (data.ok) { markSaved(); document.title = 'Tourlocate — ' + data.title; }
  else setStatus(data.error || 'Speichern fehlgeschlagen.', true);
}

// ---- „Neu": Übernahme aus der freien Karte --------------------------
// Legt (falls noch nicht geschehen) die Präsentation an und trägt die id in
// das modulweite `pid` ein — danach greifen Upload/Speichern wie gewohnt.
// Das Promise wird gecacht, damit parallele Aufrufe (z. B. Doppelklick auf
// „Speichern") nicht ZWEI Präsentationen anlegen.
let pidPromise = null;
function ensurePid(title) {
  if (pid) return Promise.resolve(pid);
  if (!pidPromise) {
    pidPromise = (async () => {
      const res = await fetch('api/presentations.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ title: title || '', csrf })
      });
      const data = await res.json().catch(() => ({}));
      if (!data.ok) throw new Error(data.error || 'Anlegen fehlgeschlagen.');
      pid = data.id;
      return pid;
    })().catch(e => { pidPromise = null; throw e; }); // Fehler → neuer Versuch möglich
  }
  return pidPromise;
}

// Stationen mit Base64-Bild (aus der Karten-Übergabe) auf den Server laden
// und das Bild durch die Server-URL ersetzen. Braucht ein bestehendes pid.
async function uploadBase64Images(map) {
  const pending = map.state.pois.filter(p => p.img && p.img.startsWith('data:'));
  let n = 0;
  for (const p of pending) {
    n++;
    setStatus('Bild ' + n + '/' + pending.length + ' wird übertragen…');
    const bytes = dataURLToBytes(p.img);
    const file = new File([bytes], 'foto.jpg', { type: 'image/jpeg' });
    p.img = await imageStore(file, { lat: p.lat, lng: p.lng, date: p.createdAt });
  }
}

async function initNewPresentation(map) {
  const raw = sessionStorage.getItem('tlHandoff');
  if (!raw) { markSaved(); return; }   // leerer neuer Editor (ohne Übergabe)

  let hand;
  try { hand = JSON.parse(raw); }
  catch { sessionStorage.removeItem('tlHandoff'); markSaved(); return; }

  // Titel + Verbindungs-/Profil-Einstellungen übernehmen
  $('titleInput').value = hand.title || '';
  if (hand.line_mode) {
    setSetting('lineMode', hand.line_mode);
    if ($('lineModeSel')) $('lineModeSel').value = hand.line_mode;
  }
  if (hand.profile) {
    setSetting('profile', hand.profile);
    if ($('profileSel')) {
      $('profileSel').value = hand.profile;
      $('profileSel').disabled = hand.line_mode !== 'route';
    }
  }

  // Stationen anlegen (Bilder zunächst als Base64)
  const pois = Array.isArray(hand.pois) ? hand.pois : [];
  pois.forEach(sp => createPoi(map, {
    lat: sp.lat, lng: sp.lng,
    name: sp.name || '', link: sp.link || '', linkText: sp.linkText || '',
    img: sp.img || '', createdAt: sp.createdAt || ''
  }));
  renumberAndRoute(map);
  if (pois.length) fitToMarkers(map);

  // Anlegen → speichern (lädt Base64-Bilder mit hoch) → sauberer Reload mit
  // Server-URLs. Die Übergabe-Daten werden erst NACH dem Erfolg entfernt —
  // scheitert etwas, überlebt der Zustand einen Reload (lieber ein
  // mögliches Duplikat als verlorene Stationen).
  try {
    setStatus('Präsentation wird angelegt…');
    await ensurePid($('titleInput').value);
    await save(map);
    if (dirty) return; // save() ist gescheitert (Status zeigt den Fehler)
    sessionStorage.removeItem('tlHandoff');
    location.replace('editor.php?id=' + pid);
  } catch (e) {
    setStatus('Übernahme fehlgeschlagen: ' + (e?.message || e), true);
  }
}

// ---- Status/Dirty-Anzeige -------------------------------------------
let dirty = false;
function setStatus(text, isError = false) {
  const el = $('saveStatus');
  el.textContent = text;
  el.style.color = isError ? '#c0392b' : '#4a5568';
}
function markDirty() { dirty = true; setStatus('Nicht gespeichert'); }
function markSaved() { dirty = false; setStatus('Gespeichert ✓'); }

// ---- Teilen (öffentlicher Link + Passwort) --------------------------
function setupShare() {
  const modal = $('shareModal');
  const shareApi = async (action, extra) => {
    const res = await fetch('api/share.php?action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ id: pid, csrf, ...extra })
    });
    return res.json().catch(() => ({}));
  };
  const shareMsg = t => { $('shareMsg').textContent = t || ''; };

  const render = (s) => {
    $('shareToggle').checked = !!s.shared;
    $('shareBody').style.display = s.shared ? 'block' : 'none';
    if (s.shared && s.token) {
      $('shareUrl').value = new URL('view.php?t=' + s.token, location.href).href;
    }
    $('sharePwToggle').checked = !!s.hasPassword;
    $('sharePwRow').style.display = s.hasPassword ? 'flex' : 'none';
    $('sharePw').value = '';
  };

  $('shareBtn').onclick = async () => {
    shareMsg('');
    const res = await fetch('api/share.php?action=status&id=' + pid, { headers: { 'X-CSRF-Token': csrf } });
    render(await res.json().catch(() => ({})));
    modal.style.display = 'flex';
  };
  $('shareClose').onclick = () => { modal.style.display = 'none'; };
  modal.onclick = e => { if (e.target === modal) modal.style.display = 'none'; };

  $('shareToggle').onchange = async (e) => {
    shareMsg('');
    const s = await shareApi(e.target.checked ? 'enable' : 'disable');
    if (s.ok) render(s); else shareMsg(s.error || 'Fehler.');
  };

  $('sharePwToggle').onchange = (e) => {
    $('sharePwRow').style.display = e.target.checked ? 'flex' : 'none';
    if (!e.target.checked) {
      // Passwortschutz entfernen = leeres Passwort setzen
      shareApi('setpassword', { password: '' }).then(s => { if (s.ok) render(s); });
    }
  };

  $('sharePwSave').onclick = async () => {
    shareMsg('');
    const pw = $('sharePw').value;
    if (!pw) { shareMsg('Bitte ein Passwort eingeben.'); return; }
    const s = await shareApi('setpassword', { password: pw });
    if (s.ok) { render(s); shareMsg(''); } else shareMsg(s.error || 'Fehler.');
  };

  $('shareCopy').onclick = async () => {
    try { await navigator.clipboard.writeText($('shareUrl').value); shareMsg('Link kopiert ✓'); }
    catch { $('shareUrl').select(); document.execCommand('copy'); shareMsg('Link kopiert ✓'); }
  };
}

// ---- WebDAV-Dauerverbindung (Cloud-Ordner, Cron-Import) -------------
function setupWebdavConn() {
  const api = async (action, extra) => {
    const opts = { headers: { 'X-CSRF-Token': csrf } };
    if (extra !== undefined) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify({ presentation_id: pid, csrf, ...extra });
    }
    const url = 'api/webdav.php?action=' + action +
      (extra === undefined ? '&presentation_id=' + pid : '');
    const res = await fetch(url, opts);
    return res.json().catch(() => ({}));
  };
  const status = (text, isError = false) => {
    const el = $('wcStatus');
    el.textContent = text || '';
    el.style.color = isError ? '#c0392b' : '#555';
  };

  const render = (s) => {
    if (s.configured) {
      $('wcUrl').value = s.url || '';
      $('wcUser').value = s.username || '';
      let txt = 'Verbindung aktiv.';
      if (s.lastPollAt) txt += ' Letzter Abruf: ' + new Date(s.lastPollAt).toLocaleString('de-DE') + '.';
      else txt += ' Wartet auf den ersten Cron-Lauf.';
      if (s.lastError) { status(txt + ' Letzter Fehler: ' + s.lastError, true); return; }
      status(txt);
    } else {
      status('Keine Verbindung hinterlegt.');
    }
  };

  api('status').then(render);

  $('wcSaveBtn').onclick = async () => {
    status('Speichern…');
    const s = await api('save', {
      url: $('wcUrl').value.trim(),
      username: $('wcUser').value.trim(),
      password: $('wcPass').value
    });
    if (s.ok) { $('wcPass').value = ''; render(s); }
    else status(s.error || 'Fehler.', true);
  };

  $('wcTestBtn').onclick = async () => {
    status('Teste Verbindung…');
    // Die AKTUELLEN Formularwerte mitschicken — so funktioniert „Testen"
    // auch vor dem ersten Speichern (leeres Passwort = gespeichertes nutzen).
    const s = await api('test', {
      url: $('wcUrl').value.trim(),
      username: $('wcUser').value.trim(),
      password: $('wcPass').value
    });
    if (s.ok) status('Verbindung ok — ' + s.imageCount + ' JPEG(s) im Ordner gefunden.');
    else status(s.error || 'Test fehlgeschlagen.', true);
  };

  $('wcDeleteBtn').onclick = async () => {
    if (!confirm('Cloud-Verbindung entfernen? (Bereits importierte Bilder bleiben erhalten.)')) return;
    const s = await api('delete', {});
    if (s.ok) { $('wcUrl').value = ''; $('wcUser').value = ''; $('wcPass').value = ''; render(s); }
    else status(s.error || 'Fehler.', true);
  };

  // Ergebnis eines Abruf-/Rescan-Laufs anzeigen (mit aufgeschlüsselten
  // Gründen — hilft bei „nichts kommt an") und bei Erfolg neu laden.
  const reportSync = (imported, noGps, badImg, lastGetError) => {
    const parts = [imported + ' Bild(er) importiert'];
    if (noGps)  parts.push(noGps + ' ohne GPS-Daten übersprungen');
    if (badImg) parts.push(badImg + ' nicht als JPEG lesbar (z. B. HEIC)');
    let msg = parts.join(', ') + '.';
    if (lastGetError) msg += ' Letzter Abruffehler: ' + lastGetError;
    status(msg, imported === 0);
    if (imported > 0) {
      if (dirty) {
        status(imported + ' Bild(er) importiert — bitte erst speichern und dann die '
          + 'Seite neu laden, um die neuen Stationen zu sehen.');
      } else {
        setTimeout(() => location.reload(), 1500);
      }
    }
  };

  // „Erneut einlesen": alle noch nicht importierten Dateien in EINEM Lauf
  // erneut prüfen (ignoriert die Merkliste) — zum Nachholen/Debuggen.
  const runRescan = async () => {
    status('Lese erneut ein…');
    const s = await api('rescan', {});
    if (!s.ok) { status(s.error || 'Erneutes Einlesen fehlgeschlagen.', true); return; }
    reportSync(s.imported, s.skippedNoGps || 0, s.skippedBadImage || 0, s.lastGetError);
  };

  // „Jetzt abrufen": Import sofort anstoßen. Der Server verarbeitet pro
  // Aufruf höchstens 10 Bilder (kurze Requests) — hier so lange nachfassen,
  // bis nichts mehr übrig ist. Danach Seite neu laden, damit die neuen
  // Stationen auf der Karte erscheinen (nur wenn nichts ungespeichert ist).
  $('wcSyncBtn').onclick = async () => {
    const btn = $('wcSyncBtn');
    btn.disabled = true;
    let imported = 0, noGps = 0, badImg = 0, lastGetError = null, listed = 0, processed = 0;
    try {
      for (let round = 0; round < 60; round++) {
        status('Rufe ab… (' + imported + ' importiert)');
        const s = await api('sync', {});
        if (!s.ok) { status(s.error || 'Abruf fehlgeschlagen.', true); return; }
        listed = s.listed;
        imported += s.imported;
        noGps += (s.skippedNoGps || 0);
        badImg += (s.skippedBadImage || 0);
        processed += s.imported + s.skipped;
        if (s.lastGetError) lastGetError = s.lastGetError;
        if (s.limitReached) { status(s.error || 'Bild-Limit erreicht.', true); break; }
        if (s.remaining <= 0) break;
        if (s.imported + s.skipped === 0) {
          // Kein Fortschritt mehr → Rest ließ sich nicht abrufen (GET-Fehler)
          status('Abruf unvollständig — ' + s.remaining + ' Datei(en) ließen sich nicht '
            + 'laden' + (lastGetError ? ' (' + lastGetError + ')' : '') + '. '
            + imported + ' importiert.', true);
          return;
        }
      }
      // Nichts Neues bearbeitet, aber Bilder im Ordner → alle schon geprüft
      // (z. B. früher ohne GPS übersprungen). Erneutes Einlesen anbieten.
      if (processed === 0 && listed > 0) {
        if (confirm(listed + ' Bild(er) im Ordner wurden bereits geprüft und werden '
          + 'normalerweise nicht erneut geladen (z. B. zuvor ohne GPS-Daten übersprungen).\n\n'
          + 'Alle erneut einlesen und prüfen? Bereits importierte Bilder bleiben erhalten '
          + '(keine Dubletten).')) {
          await runRescan();
        } else {
          status('Keine neuen Dateien.');
        }
        return;
      }
      reportSync(imported, noGps, badImg, lastGetError);
    } finally {
      btn.disabled = false;
    }
  };
}

// ---- Start -----------------------------------------------------------
window.addEventListener('DOMContentLoaded', () => {
  const map = initMap();
  setupPOIs(map);
  setupUI(map);
  setupIO(map);

  // Server-Modus aktivieren (die js-Module fragen diese Hooks ab)
  map.imageStore = imageStore;
  // Für den Export: Server-Bild-URL → Base64 auflösen
  map.resolveImage = async (url) => {
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const blob = await res.blob();
    return await new Promise((resolve, reject) => {
      const fr = new FileReader();
      fr.onload = () => resolve(fr.result);
      fr.onerror = () => reject(fr.error || new Error('filereader-error'));
      fr.readAsDataURL(blob);
    });
  };

  // Änderungen an POIs als "nicht gespeichert" markieren
  const prevHook = map.onPoisChanged;
  map.onPoisChanged = () => { prevHook?.(); markDirty(); };

  if (isNew) initNewPresentation(map);
  else loadPresentation(map);

  // Im „Neu"-Modus ohne Übergabe erst beim Speichern die Präsentation
  // anlegen. Button während des Laufs sperren (gegen Doppelklick).
  $('saveBtn').onclick = async () => {
    const btn = $('saveBtn');
    btn.disabled = true;
    try {
      try { await ensurePid($('titleInput').value); }
      catch (e) { setStatus('Anlegen fehlgeschlagen: ' + (e?.message || e), true); return; }
      await save(map);
      // URL sauber machen, damit ein Reload die angelegte Präsentation lädt.
      if (isNew && pid && location.search.indexOf('id=') === -1) {
        history.replaceState(null, '', 'editor.php?id=' + pid);
      }
    } finally {
      btn.disabled = false;
    }
  };

  // „Hilfe" führt im Editor zur ausführlichen Anleitung (hilfe.php) statt
  // zum Gesten-Overlay der statischen App — bei ungespeicherten Änderungen
  // warnt die beforeunload-Sperre wie bei jedem Seitenwechsel.
  $('helpBtn').onclick = () => { location.href = 'hilfe.php'; };
  setupShare();
  setupWebdavConn();

  // Vor dem Verlassen warnen, wenn ungespeichert
  window.addEventListener('beforeunload', e => {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });
});
