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
const T = window.TL_EDITOR.t;            // dynamische Meldungen (de/en, aus editor.php)
const LOCALE = window.TL_EDITOR.locale;  // 'de-DE' | 'en-GB' für Datums-/Zeitformate
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
  if (!data.ok) throw new Error(data.error || T.uploadFail);
  return data.url; // 'api/image.php?id=X' (relativ zu /app/)
}

// ---- Zustand laden ---------------------------------------------------
async function loadPresentation(map) {
  const res = await fetch('api/presentations.php?action=get&id=' + pid, {
    headers: { 'X-CSRF-Token': csrf }
  });
  const data = await res.json().catch(() => ({}));
  if (!data.ok) { setStatus(T.loadFail, true); return; }
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
    setStatus(T.imgUploadFail + (e?.message || e), true);
    return;
  }
  setStatus(T.saving);
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
  else setStatus(data.error || T.saveFail, true);
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
      if (!data.ok) throw new Error(data.error || T.createFail);
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
    setStatus(T.img + ' ' + n + '/' + pending.length + ' ' + T.transferring);
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
    setStatus(T.creatingPres);
    await ensurePid($('titleInput').value);
    await save(map);
    if (dirty) return; // save() ist gescheitert (Status zeigt den Fehler)
    sessionStorage.removeItem('tlHandoff');
    location.replace('editor.php?id=' + pid);
  } catch (e) {
    setStatus(T.handoffFail + (e?.message || e), true);
  }
}

// ---- Status/Dirty-Anzeige + Auto-Speichern --------------------------
// Kein „Speichern"-Knopf mehr: jede Änderung wird automatisch gesichert
// (entprellt). `loaded` verhindert ein Speichern WÄHREND des initialen
// Aufbaus (das Anlegen der Stationen aus dem Server/der Übergabe löst
// markDirty aus). `changeSeq` trennt „neue Änderungen kamen während des
// Speicherns" von „Speichern ist gescheitert" — nur Ersteres plant erneut,
// sonst würde ein dauerhafter Serverfehler alle 800 ms neu hämmern.
let dirty = false;
let loaded = false;
let saving = false;
let saveTimer = null;
let changeSeq = 0;
let mapRef = null;

function setStatus(text, isError = false) {
  const el = $('saveStatus');
  el.textContent = text;
  el.style.color = isError ? '#c0392b' : '#4a5568';
}
function markDirty() { dirty = true; changeSeq++; setStatus(T.notSaved); scheduleSave(); }
function markSaved() { dirty = false; loaded = true; setStatus(T.saved); }

function scheduleSave() {
  if (!loaded) return;                 // während des initialen Aufbaus nicht speichern
  clearTimeout(saveTimer);
  saveTimer = setTimeout(runAutosave, 800);
}

async function runAutosave() {
  saveTimer = null;
  if (saving || !dirty || !loaded || !mapRef) return;
  saving = true;
  const seq = changeSeq;               // Stand, den dieser Lauf abdeckt
  try {
    try { await ensurePid($('titleInput').value); }
    catch (e) { setStatus(T.createFailPfx + (e?.message || e), true); return; }
    await save(mapRef);                // setzt bei Erfolg markSaved(), sonst Fehlerstatus
    // „Neu" ohne id in der URL → nach dem Anlegen sauber nachtragen
    if (isNew && pid && location.search.indexOf('id=') === -1) {
      history.replaceState(null, '', 'editor.php?id=' + pid);
    }
  } finally {
    saving = false;
    // Nur erneut planen, wenn WÄHREND des Speicherns neue Änderungen kamen.
    if (changeSeq > seq) scheduleSave();
  }
}

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
    if (s.ok) render(s); else shareMsg(s.error || T.error);
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
    if (!pw) { shareMsg(T.enterPassword); return; }
    const s = await shareApi('setpassword', { password: pw });
    if (s.ok) { render(s); shareMsg(''); } else shareMsg(s.error || T.error);
  };

  $('shareCopy').onclick = async () => {
    try { await navigator.clipboard.writeText($('shareUrl').value); shareMsg(T.linkCopied); }
    catch { $('shareUrl').select(); document.execCommand('copy'); shareMsg(T.linkCopied); }
  };
}

// ---- WebDAV-Dauerverbindung (Cloud-Ordner, Cron-Import) -------------
function setupWebdavConn(map) {
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
    $('wcAutoPoll').checked = !!s.autoPoll; // automatischer Cron-Abruf (Default aus)
    if (s.configured) {
      $('wcUrl').value = s.url || '';
      $('wcUser').value = s.username || '';
      let txt = T.connActive;
      if (s.lastPollAt) txt += T.lastFetch + new Date(s.lastPollAt).toLocaleString(LOCALE) + '.';
      else txt += T.waitingCron;
      if (s.lastError) { status(txt + T.lastErr + s.lastError, true); return; }
      status(txt);
    } else {
      status(T.noConn);
    }
  };

  api('status').then(render);

  $('wcSaveBtn').onclick = async () => {
    status(T.saving);
    const s = await api('save', {
      url: $('wcUrl').value.trim(),
      username: $('wcUser').value.trim(),
      password: $('wcPass').value,
      autoPoll: $('wcAutoPoll').checked
    });
    if (s.ok) { $('wcPass').value = ''; render(s); }
    else status(s.error || T.error, true);
  };

  // Details-Popup: alle Fotos im Ordner (können viele sein → eigenes Popup).
  const filesModal = $('wcFilesModal');
  const closeFilesModal = () => { filesModal.style.display = 'none'; };
  $('wcFilesClose').onclick = closeFilesModal;
  filesModal.onclick = e => { if (e.target === filesModal) closeFilesModal(); };
  const showFilesModal = (files) => {
    $('wcFilesTitle').textContent = T.filesInFolder + ' (' + files.length + ')';
    const list = $('wcFilesList');
    list.textContent = '';
    files.forEach(name => {
      const li = document.createElement('li');
      li.textContent = name;
      list.appendChild(li);
    });
    filesModal.style.display = 'flex';
  };

  $('wcTestBtn').onclick = async () => {
    status(T.testing);
    // Die AKTUELLEN Formularwerte mitschicken — so funktioniert „Testen"
    // auch vor dem ersten Speichern (leeres Passwort = gespeichertes nutzen).
    const s = await api('test', {
      url: $('wcUrl').value.trim(),
      username: $('wcUser').value.trim(),
      password: $('wcPass').value
    });
    if (!s.ok) { status(s.error || T.testFail, true); return; }
    // Schlanke Rückmeldung + optionaler „Details"-Link (öffnet das Popup).
    const el = $('wcStatus');
    el.style.color = '#555';
    el.textContent = T.connOk + s.imageCount + T.jpegsFound;
    const files = s.files || [];
    if (files.length) {
      const a = document.createElement('a');
      a.href = '#'; a.textContent = T.details;
      a.style.cursor = 'pointer';
      a.onclick = (e) => { e.preventDefault(); showFilesModal(files); };
      el.appendChild(a);
    }
  };

  $('wcDeleteBtn').onclick = async () => {
    if (!confirm(T.confirmRemoveConn)) return;
    const s = await api('delete', {});
    if (s.ok) { $('wcUrl').value = ''; $('wcUser').value = ''; $('wcPass').value = ''; render(s); }
    else status(s.error || T.error, true);
  };

  // Die serverseitig importierten Stationen SOFORT in die laufende Karte
  // einfügen — so sieht man sie ohne Speichern/Neuladen. Sie stecken bereits
  // in data_json des Servers; war vorher nichts ungespeichert, bleibt der
  // Stand „gespeichert" (Inhalt = Server). Hatte man eigene Änderungen offen,
  // bleiben sie offen und werden beim nächsten Speichern mit übernommen.
  const applyNewPois = (newPois) => {
    if (!Array.isArray(newPois) || !newPois.length) return;
    const wasDirty = dirty;
    newPois.forEach(sp => createPoi(map, {
      lat: sp.lat, lng: sp.lng,
      name: sp.name || '', link: sp.link || '', linkText: sp.linkText || '',
      img: sp.img || '', createdAt: sp.createdAt || ''
    }));
    renumberAndRoute(map);
    fitToMarkers(map);
    if (!wasDirty) markSaved();
  };

  // Ergebnis eines Abruf-/Rescan-Laufs anzeigen (mit aufgeschlüsselten
  // Gründen — hilft bei „nichts kommt an"). skipDiag erklärt bei „ohne GPS",
  // was vom Server wirklich ankam (Größe/Content-Type/EXIF-Block).
  const reportSync = (imported, recovered, noGps, badImg, lastGetError, skipDiag) => {
    const parts = [imported + T.imported];
    if (recovered) parts.push(recovered + T.restored);
    if (noGps)  parts.push(noGps + T.skippedNoGps);
    if (badImg) parts.push(badImg + T.notJpeg);
    let msg = parts.join(', ') + '.';
    if (noGps && skipDiag) msg += T.fromServer + skipDiag;
    if (lastGetError) msg += T.lastFetchErr + lastGetError;
    status(msg, imported === 0 && recovered === 0);
  };

  // Rescan-Lauf: ignoriert die Merkliste UND heilt verwaiste Importe — holt
  // also auch gelöschte Fotos zurück. Wird vom Knopf „Gelöschte erneut
  // einlesen" aufgerufen (nicht vom normalen „Jetzt abrufen").
  const runRescan = async () => {
    status(T.reReading);
    const s = await api('rescan', {});
    if (!s.ok) { status(s.error || T.reReadFail, true); return; }
    applyNewPois(s.newPois);
    reportSync(s.imported, s.recovered || 0, s.skippedNoGps || 0, s.skippedBadImage || 0, s.lastGetError, s.skipDiag);
  };

  // „Jetzt abrufen": Import sofort anstoßen. Der Server verarbeitet pro
  // Aufruf höchstens 10 Bilder (kurze Requests) — hier so lange nachfassen,
  // bis nichts mehr übrig ist. Die neuen Stationen werden direkt in die Karte
  // eingefügt (kein Speichern/Neuladen nötig).
  $('wcSyncBtn').onclick = async () => {
    const btn = $('wcSyncBtn');
    btn.disabled = true;
    let imported = 0, recovered = 0, noGps = 0, badImg = 0, lastGetError = null, listed = 0, processed = 0, skipDiag = null;
    let stopMsg = null; // Sondermeldung (Limit/GET-Fehler) statt der Normal-Bilanz
    const newPois = [];
    try {
      // Zuerst den AKTUELLEN Editor-Stand speichern — der serverseitige Abruf
      // arbeitet auf data_json. Ohne das würden z. B. ungespeicherte
      // Löschungen ignoriert (die Station gilt serverseitig noch als da →
      // „0 importiert", kein Wieder-Einfügen möglich).
      if (dirty) {
        clearTimeout(saveTimer);   // ein entprelltes Auto-Speichern nicht parallel laufen lassen
        status(T.savingState);
        await save(map);
        if (dirty) { status(T.fetchAborted, true); return; }
      }
      for (let round = 0; round < 60; round++) {
        status(T.fetching + imported + T.importedShort);
        const s = await api('sync', {});
        if (!s.ok) { status(s.error || T.fetchFail, true); return; }
        listed = s.listed;
        imported += s.imported;
        recovered += (s.recovered || 0);
        noGps += (s.skippedNoGps || 0);
        badImg += (s.skippedBadImage || 0);
        processed += s.imported + s.skipped;
        if (Array.isArray(s.newPois)) newPois.push(...s.newPois);
        if (s.lastGetError) lastGetError = s.lastGetError;
        if (s.skipDiag && !skipDiag) skipDiag = s.skipDiag;
        if (s.limitReached) { stopMsg = s.error || T.limitReached; break; }
        if (s.remaining <= 0) break;
        if (s.imported + s.skipped === 0) {
          // Kein Fortschritt mehr → Rest ließ sich nicht abrufen (GET-Fehler)
          stopMsg = T.incompletePre + s.remaining + T.filesCouldntLoad
            + (lastGetError ? ' (' + lastGetError + ')' : '') + '. '
            + imported + T.importedSuffix;
          break;
        }
      }
      // „Jetzt abrufen" holt wie der Cron NUR neue Fotos — gelöschte werden
      // NICHT wieder eingefügt (dafür gibt es den Knopf „Gelöschte erneut
      // einlesen"). Nichts Neues bearbeitet → schlichte Rückmeldung.
      applyNewPois(newPois); // genau einmal die neuen Stationen in die Karte
      if (stopMsg) status(stopMsg, true);
      else if (imported === 0 && noGps === 0 && badImg === 0) status(T.noNew);
      else reportSync(imported, recovered, noGps, badImg, lastGetError, skipDiag);
    } finally {
      btn.disabled = false;
    }
  };

  // „Gelöschte erneut einlesen" (Einrichtungs-/Debug-Hilfe): holt auch Fotos
  // zurück, deren Station gelöscht wurde (rescan mit Heilung). Mit Rückfrage,
  // und vorher den aktuellen Stand speichern (sonst arbeitet der Server auf
  // einem veralteten data_json).
  $('wcRescanBtn').onclick = async () => {
    if (!confirm(T.confirmRescan)) return;
    const btn = $('wcRescanBtn');
    btn.disabled = true;
    try {
      if (dirty) {
        clearTimeout(saveTimer);   // ein entprelltes Auto-Speichern nicht parallel laufen lassen
        status(T.savingState);
        await save(map);
        if (dirty) { status(T.saveAborted, true); return; }
      }
      await runRescan();
    } finally {
      btn.disabled = false;
    }
  };
}

// ---- Start -----------------------------------------------------------
window.addEventListener('DOMContentLoaded', () => {
  const map = initMap();
  mapRef = map;                 // für das Auto-Speichern
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

  // Verbindungsart / Profil / Einzelbild-Download sind keine POI-Änderungen,
  // lösen also onPoisChanged nicht aus — darum hier explizit als Änderung
  // markieren, damit sie ebenfalls automatisch gespeichert werden. (Die
  // programmatische Übernahme beim Laden setzt .value ohne 'change'-Event und
  // speichert daher NICHT versehentlich.)
  ['lineModeSel', 'profileSel', 'imgDownloadChk'].forEach(id => {
    const el = $(id);
    if (el) el.addEventListener('change', markDirty);
  });

  // „Hilfe" führt im Editor zur ausführlichen Anleitung (hilfe.php) statt
  // zum Gesten-Overlay der statischen App — bei ungespeicherten Änderungen
  // warnt die beforeunload-Sperre wie bei jedem Seitenwechsel.
  $('helpBtn').onclick = () => { location.href = 'hilfe.php'; };
  setupShare();
  setupWebdavConn(map);

  // Vor dem Verlassen warnen, wenn ungespeichert
  window.addEventListener('beforeunload', e => {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });
});
