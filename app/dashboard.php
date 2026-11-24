<?php
// Dashboard: Liste der eigenen Präsentationen mit Anlegen/Öffnen/
// Umbenennen/Löschen. Dynamik über api/presentations.php.
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';

$uid = current_user_id();
if ($uid === null) {
    header('Location: index.php');
    exit;
}
$st = db()->prepare('SELECT email, is_admin FROM users WHERE id = ?');
$st->execute([$uid]);
$me = $st->fetch();
$email = (string) ($me['email'] ?? '');
$isAdmin = (int) ($me['is_admin'] ?? 0) === 1;
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= tl_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tourlocate — <?= L('Meine Präsentationen', 'My presentations') ?></title>
<link rel="icon" type="image/png" href="../img/logo.png">
<script>
// Start-Ansicht: nur beim ERSTEN Laden pro App-Start (sessionStorage-Flag)
// gemäß Nutzer-Einstellung früh umleiten — läuft im <head>, damit das
// Dashboard bei „Karte"/„Zuletzt" gar nicht erst aufblitzt.
(function(){
  try {
    if (sessionStorage.getItem('tlStartDone')) return;
    sessionStorage.setItem('tlStartDone', '1');
    var sv = localStorage.getItem('tlStartView') || 'dashboard';
    // href (nicht replace): so bleibt das Dashboard in der Historie und die
    // Hardware-Zurück-Taste führt dorthin zurück — sonst säße man auf der Karte
    // ohne Weg zum Konto fest.
    if (sv === 'map') { location.href = '../index.html?app=1'; return; }
    if (sv === 'last') {
      var last = localStorage.getItem('tlLast');
      if (last && last.indexOf('dashboard.php') === -1) location.href = last;
    }
  } catch (e) {}
})();
</script>
<style>
  body{margin:0;font-family:system-ui,sans-serif;background:#f4f6f8;color:#1a1a1a}
  .bar{display:flex;align-items:center;justify-content:space-between;
       padding:14px 20px;background:#fff;border-bottom:1px solid #e2e8f0}
  .brand{display:flex;align-items:center;gap:10px}
  .brand img{width:32px;height:32px;border-radius:50%}
  .brand strong{font-size:18px}
  .who{font-size:13px;color:#555;margin-right:12px}
  .lang{font-size:12px;margin-right:10px}
  .lang a{color:#889;text-decoration:none}
  .wrap{max-width:820px;margin:24px auto;padding:0 16px}
  .head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
  h1{font-size:20px;margin:0}
  button{padding:8px 14px;border:1px solid #cbd2d9;border-radius:7px;background:#fff;
         cursor:pointer;font:inherit}
  button.primary, a.primary{background:#2b6cb0;color:#fff;border:1px solid #2b6cb0;font-weight:600}
  a.primary{display:inline-block;text-decoration:none;padding:8px 14px;border-radius:7px}
  button.danger{color:#c0392b;border-color:#e3b0ab}
  .list{display:flex;flex-direction:column;gap:10px}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;
        display:flex;align-items:center;justify-content:space-between;gap:12px}
  .card .meta{min-width:0}
  .card .title{font-weight:600;font-size:15px;white-space:nowrap;overflow:hidden;
               text-overflow:ellipsis}
  .card .sub{font-size:12px;color:#667;margin-top:3px}
  .badge{display:inline-block;font-size:11px;background:#e6f0fb;color:#2b6cb0;
         border-radius:20px;padding:1px 8px;margin-left:6px}
  .actions{display:flex;gap:6px;flex:0 0 auto}
  .empty{color:#667;background:#fff;border:1px dashed #cbd2d9;border-radius:10px;
         padding:28px;text-align:center}
  .msg{font-size:13px;color:#c0392b;min-height:1em;margin-top:10px}

  /* Schmale Screens: Karte vertikal stapeln, damit der Titel die volle
     Breite bekommt und die Buttons nicht hineinragen. */
  @media (max-width: 560px){
    .card{flex-direction:column;align-items:stretch;gap:10px}
    .card .title{white-space:normal}      /* langer Name darf umbrechen */
    .actions{flex-wrap:wrap}
    .actions button{flex:1 1 auto}        /* Buttons füllen die Breite */
    .bar{flex-wrap:wrap;gap:8px}
  }
</style>
</head>
<body>
<div class="bar">
  <div class="brand">
    <img src="../img/logo.png" alt="" onerror="this.style.display='none'">
    <strong>Tourlocate</strong>
  </div>
  <div>
    <span class="who"><?= htmlspecialchars($email, ENT_QUOTES) ?></span>
    <span class="lang"><?= tl_lang_switcher_setlang() ?></span>
    <a href="hilfe.php" style="margin-right:10px;color:#2b6cb0"><?= L('Hilfe', 'Help') ?></a>
    <?php if ($isAdmin): ?><a href="admin.php" style="margin-right:10px;color:#2b6cb0"><?= L('Administration', 'Administration') ?></a><?php endif; ?>
    <button id="logoutBtn"><?= L('Abmelden', 'Sign out') ?></button>
  </div>
</div>

<div class="wrap">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px">
    <a href="../index.html?app=1" id="mapBtn" class="primary"><?= L('Karte', 'Map') ?></a>
    <label style="font-size:13px;color:#555"><?= L('Beim Start zeigen:', 'Show at startup:') ?>
      <select id="startViewSel" style="margin-left:4px;padding:4px 6px">
        <option value="dashboard"><?= L('Dashboard', 'Dashboard') ?></option>
        <option value="map"><?= L('Karte', 'Map') ?></option>
        <option value="last"><?= L('Zuletzt geöffnet', 'Last opened') ?></option>
      </select>
    </label>
  </div>
  <!-- Nur in der nativen App sichtbar (per JS eingeblendet): EXIF-Hinweis beim
       Speichern von Fotos ins Geräte-Album -->
  <div id="nativeSettings" style="display:none;margin:-10px 0 22px">
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#555">
      <input type="checkbox" id="dcimExifChk" style="width:auto;margin:0;flex:none">
      <span><?= L('Beim Speichern von Fotos ins Geräte-Album den EXIF-Hinweis anzeigen', 'Show the EXIF notice when saving photos to the device album') ?></span>
    </label>
  </div>
  <div class="head">
    <h1><?= L('Meine Präsentationen', 'My presentations') ?></h1>
    <button class="primary" id="newBtn"><?= L('+ Neue Präsentation', '+ New presentation') ?></button>
  </div>
  <div class="list" id="list"></div>
  <div class="msg" id="msg"></div>

  <h1 style="margin-top:34px;font-size:18px"><?= L('Live-Standort teilen', 'Share live location') ?></h1>
  <div class="card" style="display:block" id="liveCard">
    <!-- Inaktiv: Sitzung starten -->
    <div id="liveStart">
      <div class="sub" style="margin-bottom:10px"><?= L(
        'Teile deinen aktuellen Standort über einen Link — der Empfänger braucht keinen Account. Funktioniert, solange diese Seite geöffnet und im Vordergrund ist (im Hintergrund oder bei gesperrtem Display pausiert die Ortung).',
        'Share your current location via a link — the recipient needs no account. Works while this page is open and in the foreground (location pauses in the background or when the screen is locked).'
      ) ?></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input id="liveLabel" type="text" maxlength="80" placeholder="<?= L('Bezeichnung (optional)', 'Label (optional)') ?>"
               style="flex:1 1 180px;padding:9px;border:1px solid #cbd2d9;border-radius:7px;font:inherit">
        <select id="liveTtl" style="padding:9px;border:1px solid #cbd2d9;border-radius:7px;font:inherit">
          <option value="15"><?= L('15 Minuten', '15 minutes') ?></option>
          <option value="60" selected><?= L('1 Stunde', '1 hour') ?></option>
          <option value="480"><?= L('8 Stunden', '8 hours') ?></option>
        </select>
        <button class="primary" id="liveStartBtn"><?= L('Standort teilen', 'Share location') ?></button>
      </div>
    </div>
    <!-- Aktiv: läuft -->
    <div id="liveActive" style="display:none">
      <div class="title"><?= L('Du teilst gerade deinen Standort', 'You are currently sharing your location') ?> <span class="badge">live</span></div>
      <div class="sub" id="liveExpiry" style="margin:4px 0 10px"></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
        <input id="liveUrl" type="text" readonly
               style="flex:1 1 220px;padding:9px;border:1px solid #cbd2d9;border-radius:7px;font:inherit;background:#f7f9fb">
        <button id="liveCopyBtn"><?= L('Kopieren', 'Copy') ?></button>
        <button id="liveShareBtn" style="display:none"><?= L('Teilen', 'Share') ?></button>
        <button id="livePreviewBtn"><?= L('Ansicht öffnen', 'Open view') ?></button>
        <button class="danger" id="liveStopBtn"><?= L('Teilen beenden', 'Stop sharing') ?></button>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:8px">
        <span class="sub"><?= L('Verlängern um', 'Extend by') ?></span>
        <select id="liveExtendTtl" style="padding:7px;border:1px solid #cbd2d9;border-radius:7px;font:inherit">
          <option value="15"><?= L('15 Minuten', '15 minutes') ?></option>
          <option value="60" selected><?= L('1 Stunde', '1 hour') ?></option>
          <option value="480"><?= L('8 Stunden', '8 hours') ?></option>
        </select>
        <button id="liveExtendBtn"><?= L('Verlängern', 'Extend') ?></button>
      </div>
      <div class="sub" id="liveStatus" style="margin-top:10px"></div>
    </div>
    <div class="msg" id="liveMsg"></div>
  </div>

  <h1 style="margin-top:34px;font-size:18px"><?= L('Sicherheit', 'Security') ?></h1>

  <div class="card" style="display:block;margin-bottom:10px">
    <div class="title"><?= L('Passwort ändern', 'Change password') ?></div>
    <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px;max-width:360px">
      <input id="pwCurrent" type="password" autocomplete="current-password" placeholder="<?= L('Aktuelles Passwort', 'Current password') ?>"
             style="padding:9px;border:1px solid #cbd2d9;border-radius:7px;font:inherit">
      <input id="pwNew" type="password" autocomplete="new-password" placeholder="<?= L('Neues Passwort (mind. 12 Zeichen)', 'New password (at least 12 characters)') ?>"
             style="padding:9px;border:1px solid #cbd2d9;border-radius:7px;font:inherit">
      <input id="pwNew2" type="password" autocomplete="new-password" placeholder="<?= L('Neues Passwort wiederholen', 'Repeat new password') ?>"
             style="padding:9px;border:1px solid #cbd2d9;border-radius:7px;font:inherit">
      <div><button class="primary" id="pwSaveBtn"><?= L('Passwort ändern', 'Change password') ?></button></div>
      <div id="pwMsg" style="font-size:13px;min-height:1em"></div>
    </div>
  </div>

  <div class="card" style="display:block" id="twofaCard">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
      <div class="meta">
        <div class="title"><?= L('Zwei-Faktor-Authentifizierung (2FA)', 'Two-factor authentication (2FA)') ?></div>
        <div class="sub" id="twofaState"><?= L('Lade…', 'Loading…') ?></div>
      </div>
      <div class="actions">
        <button id="twofaEnableBtn" style="display:none"><?= L('Einrichten', 'Set up') ?></button>
        <button id="twofaDisableBtn" class="danger" style="display:none"><?= L('Abschalten', 'Disable') ?></button>
      </div>
    </div>
    <div id="twofaSetup" style="display:none;margin-top:14px;border-top:1px solid #eef2f6;padding-top:14px">
      <p style="font-size:13px;margin:0 0 10px"><?= L(
        'Scanne den QR-Code mit einer Authenticator-App (z. B. Aegis, FreeOTP, Google Authenticator) und bestätige dann unten mit dem <strong>6-stelligen Code</strong>, den die App anzeigt.',
        'Scan the QR code with an authenticator app (e.g. Aegis, FreeOTP, Google Authenticator), then confirm below with the <strong>6-digit code</strong> it shows.'
      ) ?></p>
      <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
        <div style="width:180px;height:180px;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden">
          <img id="twofaQr" alt="QR-Code" style="width:100%;height:100%;image-rendering:pixelated;display:none">
          <span id="twofaQrFallback" style="display:none;font-size:12px;color:#92400e;text-align:center;padding:8px"><?= L('QR-Code konnte nicht geladen werden. Bitte das Secret rechts von Hand in der App eingeben.', 'The QR code could not be loaded. Please enter the secret on the right into the app by hand.') ?></span>
        </div>
        <div style="min-width:220px">
          <div style="font-size:12px;color:#667"><?= L('Kannst du nicht scannen? Dann gib diesen Schlüssel <strong>in der App</strong> von Hand ein (nicht in das Feld unten):', 'Can\'t scan? Then enter this key <strong>into the app</strong> by hand (not into the field below):') ?></div>
          <code id="twofaSecret" style="font-size:13px;user-select:all;word-break:break-all"></code>
          <div style="font-size:11px;color:#8a94a0;margin-top:4px"><?= L('Enthält keine Null und keine Eins — nur Buchstaben und die Ziffern 2–7.', 'Contains no zero or one — only letters and the digits 2–7.') ?></div>
          <div style="font-size:12px;color:#667;margin-top:14px"><?= L('6-stelligen Code <strong>aus der App</strong> hier eingeben:', 'Enter the 6-digit code <strong>from the app</strong> here:') ?></div>
          <div style="margin-top:6px;display:flex;gap:6px">
            <input id="twofaCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456"
                   style="width:110px;padding:8px;border:1px solid #cbd2d9;border-radius:7px;font:inherit">
            <button class="primary" id="twofaConfirmBtn"><?= L('Aktivieren', 'Activate') ?></button>
          </div>
          <div id="twofaMsg" style="font-size:13px;color:#c0392b;min-height:1em;margin-top:6px"></div>
        </div>
      </div>
    </div>
    <div id="twofaRecovery" style="display:none;margin-top:14px;border-top:1px solid #eef2f6;padding-top:14px">
      <strong style="color:#1e7d34"><?= L('2FA ist aktiv.', '2FA is active.') ?></strong>
      <p style="font-size:13px;margin:6px 0"><?= L(
        'Bewahre diese Recovery-Codes sicher auf (Passwort-Manager, Ausdruck). Jeder Code funktioniert genau einmal, falls die Authenticator-App verloren geht. <strong>Sie werden nur jetzt angezeigt.</strong>',
        'Keep these recovery codes safe (password manager, printout). Each code works exactly once if the authenticator app is lost. <strong>They are shown only now.</strong>'
      ) ?></p>
      <pre id="twofaCodes" style="background:#f7f9fb;border:1px solid #e2e8f0;border-radius:8px;padding:12px;font-size:14px;user-select:all"></pre>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js" defer></script>
<script>window.TL_LIVE_CSRF = <?= json_encode($csrf) ?>;</script>
<script src="live-share.js"></script>
<script>
const CSRF = <?= json_encode($csrf) ?>;
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const LOCALE = <?= json_encode(tl_lang() === 'en' ? 'en-GB' : 'de-DE') ?>;
const LANG = <?= json_encode(tl_lang()) ?>;
// Übersetzte JS-Texte (serverseitig passend zur Kontosprache gewählt)
const T = {
  loadErr:       <?= json_encode(L('Fehler beim Laden.', 'Error while loading.')) ?>,
  error:         <?= json_encode(L('Fehler.', 'Error.')) ?>,
  emptyList:     <?= json_encode(L('Noch keine Präsentation. Leg mit „+ Neue Präsentation“ los.', 'No presentations yet. Start with “+ New presentation”.')) ?>,
  shared:        <?= json_encode(L('geteilt', 'shared')) ?>,
  open:          <?= json_encode(L('Öffnen', 'Open')) ?>,
  rename:        <?= json_encode(L('Umbenennen', 'Rename')) ?>,
  del:           <?= json_encode(L('Löschen', 'Delete')) ?>,
  imgOne:        <?= json_encode(L('Bild', 'image')) ?>,
  imgMany:       <?= json_encode(L('Bilder', 'images')) ?>,
  changedAt:     <?= json_encode(L('geändert', 'changed')) ?>,
  renamePrompt:  <?= json_encode(L('Neuer Name:', 'New name:')) ?>,
  confirmDelete: <?= json_encode(L('Diese Präsentation und ihre Bilder wirklich löschen?', 'Really delete this presentation and its images?')) ?>,
  newPrompt:     <?= json_encode(L('Name der neuen Präsentation:', 'Name of the new presentation:')) ?>,
  newDefault:    <?= json_encode(L('Neue Präsentation', 'New presentation')) ?>,
  logoutShare:   <?= json_encode(L('Du teilst gerade deinen Standort — mit dem Abmelden wird das Teilen beendet. Fortfahren?', 'You are currently sharing your location — signing out will stop sharing. Continue?')) ?>,
  twofaUnavail:  <?= json_encode(L('Status nicht abrufbar.', 'Status unavailable.')) ?>,
  twofaActive:   <?= json_encode(L('Aktiv — beim Anmelden wird zusätzlich ein Code abgefragt.', 'Active — an extra code is required at sign-in.')) ?>,
  twofaLeft1:    <?= json_encode(L('Recovery-Code übrig.', 'recovery code left.')) ?>,
  twofaLeftN:    <?= json_encode(L('Recovery-Codes übrig.', 'recovery codes left.')) ?>,
  twofaAdmin:    <?= json_encode(L('<strong style="color:#b45309">Dringend empfohlen für Admin-Konten.</strong> Dein Konto kann Nutzer verwalten und fremde Inhalte einsehen — sichere es mit 2FA zusätzlich zum Passwort ab.', '<strong style="color:#b45309">Strongly recommended for admin accounts.</strong> Your account can manage users and view others’ content — secure it with 2FA in addition to the password.')) ?>,
  twofaInactive: <?= json_encode(L('Nicht aktiv. Empfohlen — schützt dein Konto zusätzlich zum Passwort.', 'Not active. Recommended — protects your account in addition to the password.')) ?>,
  twofaWrong:    <?= json_encode(L('Der Code stimmt nicht — bitte den aktuellen 6-stelligen Code aus der App eingeben.', 'The code is incorrect — please enter the current 6-digit code from the app.')) ?>,
  twofaDisPw:    <?= json_encode(L('Zum Abschalten der 2FA bitte dein Passwort eingeben:', 'To disable 2FA, please enter your password:')) ?>,
  pwMinLen:      <?= json_encode(L('Das neue Passwort muss mindestens 12 Zeichen lang sein.', 'The new password must be at least 12 characters long.')) ?>,
  pwMismatch:    <?= json_encode(L('Die beiden neuen Passwörter stimmen nicht überein.', 'The two new passwords do not match.')) ?>,
  pwChanged:     <?= json_encode(L('Passwort geändert ✓', 'Password changed ✓')) ?>,
  liveSending:   <?= json_encode(L('Dein Standort wird gesendet, solange diese Seite oder der Editor geöffnet ist.', 'Your location is sent while this page or the editor is open.')) ?>,
  liveEnded:     <?= json_encode(L('Teilen beendet.', 'Sharing stopped.')) ?>,
  extended:      <?= json_encode(L('Verlängert ✓', 'Extended ✓')) ?>,
  liveMaxReached:<?= json_encode(L('Maximale Dauer erreicht — nicht weiter verlängerbar.', 'Maximum duration reached — cannot extend further.')) ?>,
  copied:        <?= json_encode(L('Kopiert ✓', 'Copied ✓')) ?>,
  shareTitle:    <?= json_encode(L('Mein Live-Standort', 'My live location')) ?>,
  hours:         <?= json_encode(L('Std.', 'h')) ?>,
  mins:          <?= json_encode(L('Min.', 'min')) ?>,
};

// Sprach-Umschalter (persistiert users.lang, dann Seite neu laden)
document.querySelectorAll('[data-setlang]').forEach(a => {
  a.onclick = async (e) => {
    e.preventDefault();
    await fetch('api/auth.php?action=setlang', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ lang: a.dataset.setlang, csrf: CSRF })
    });
    location.reload();
  };
});

async function api(action, body) {
  const opts = { headers: { 'X-CSRF-Token': CSRF } };
  if (body) {
    opts.method = 'POST';
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify({ ...body, csrf: CSRF });
  }
  const res = await fetch('api/presentations.php?action=' + action, opts);
  return res.json();
}

const msg = document.getElementById('msg');
const listEl = document.getElementById('list');

function fmtDate(iso) {
  const d = new Date(iso);
  return isNaN(d) ? '' : d.toLocaleString(LOCALE, { dateStyle: 'medium', timeStyle: 'short' });
}
function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

async function load() {
  msg.textContent = '';
  const data = await api('list');
  if (!data.ok) { msg.textContent = data.error || T.loadErr; return; }
  const items = data.presentations;
  if (!items.length) {
    listEl.innerHTML = '<div class="empty">' + esc(T.emptyList) + '</div>';
    return;
  }
  listEl.innerHTML = items.map(p => `
    <div class="card" data-id="${p.id}">
      <div class="meta">
        <div class="title">${esc(p.title)}${p.shared ? '<span class="badge">' + esc(T.shared) + '</span>' : ''}</div>
        <div class="sub">${p.image_count} ${p.image_count === 1 ? esc(T.imgOne) : esc(T.imgMany)} · ${esc(T.changedAt)} ${esc(fmtDate(p.updated_at))}</div>
      </div>
      <div class="actions">
        <button data-act="open">${esc(T.open)}</button>
        <button data-act="rename">${esc(T.rename)}</button>
        <button class="danger" data-act="delete">${esc(T.del)}</button>
      </div>
    </div>`).join('');
}

listEl.addEventListener('click', async (e) => {
  const btn = e.target.closest('button'); if (!btn) return;
  const card = e.target.closest('.card'); const id = card.dataset.id;
  const act = btn.dataset.act;
  if (act === 'open') {
    location.href = 'editor.php?id=' + id;
  } else if (act === 'rename') {
    const cur = card.querySelector('.title').textContent.replace(T.shared,'').trim();
    const title = prompt(T.renamePrompt, cur);
    if (title === null) return;
    const r = await api('save', { id: Number(id), title });
    if (r.ok) load(); else msg.textContent = r.error || T.error;
  } else if (act === 'delete') {
    if (!confirm(T.confirmDelete)) return;
    const r = await api('delete', { id: Number(id) });
    if (r.ok) load(); else msg.textContent = r.error || T.error;
  }
});

document.getElementById('newBtn').onclick = async () => {
  const title = prompt(T.newPrompt, T.newDefault);
  if (title === null) return;
  const r = await api('create', { title });
  if (r.ok) location.href = 'editor.php?id=' + r.id;
  else msg.textContent = r.error || T.error;
};

document.getElementById('logoutBtn').onclick = async () => {
  // Warnen, wenn gerade geteilt wird — Abmelden beendet das Teilen.
  if (window.TLLiveShare && TLLiveShare.getState().active && !confirm(T.logoutShare)) {
    return;
  }
  await fetch('api/auth.php?action=logout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ csrf: CSRF })
  });
  location.href = 'index.php';
};

load();

// ==================== 2FA (TOTP) ====================
async function totpApi(action, body) {
  const opts = { headers: { 'X-CSRF-Token': CSRF } };
  if (body) {
    opts.method = 'POST';
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify({ ...body, csrf: CSRF });
  }
  const res = await fetch('api/totp.php?action=' + action, opts);
  return res.json().catch(() => ({}));
}

const twofaState = document.getElementById('twofaState');
const twofaCard = document.getElementById('twofaCard');
const enableBtn = document.getElementById('twofaEnableBtn');
const disableBtn = document.getElementById('twofaDisableBtn');

async function loadTwofa() {
  const s = await totpApi('status');
  if (!s.ok) { twofaState.textContent = T.twofaUnavail; return; }
  if (s.enabled) {
    twofaState.textContent = T.twofaActive + ' ' + s.recoveryLeft + ' '
      + (s.recoveryLeft === 1 ? T.twofaLeft1 : T.twofaLeftN);
    twofaCard.style.borderColor = '';
    twofaCard.style.background = '';
    enableBtn.style.display = 'none';
    disableBtn.style.display = '';
  } else {
    if (IS_ADMIN) {
      // Admin-Konten können fremde Inhalte einsehen und Nutzer verwalten —
      // hier nachdrücklich zur 2FA raten und die Karte hervorheben.
      twofaState.innerHTML = T.twofaAdmin;
      twofaCard.style.borderColor = '#f0b429';
      twofaCard.style.background = '#fffbeb';
    } else {
      twofaState.textContent = T.twofaInactive;
      twofaCard.style.borderColor = '';
      twofaCard.style.background = '';
    }
    enableBtn.style.display = '';
    disableBtn.style.display = 'none';
  }
}

enableBtn.onclick = async () => {
  const s = await totpApi('setup', {});
  if (!s.ok) { msg.textContent = s.error || T.error; return; }
  document.getElementById('twofaSetup').style.display = 'block';
  document.getElementById('twofaSecret').textContent = s.secret.replace(/(.{4})/g, '$1 ').trim();
  // QR aus der otpauth-URI zeichnen (qrcode-generator). Scheitert die
  // Bibliothek, bleibt die manuelle Secret-Eingabe als Fallback.
  const qrImg = document.getElementById('twofaQr');
  const qrFallback = document.getElementById('twofaQrFallback');
  let drawn = false;
  if (window.qrcode) {
    try {
      const qr = qrcode(0, 'M');      // Typ 0 = automatisch, Fehlerkorrektur M
      qr.addData(s.uri);
      qr.make();
      qrImg.src = qr.createDataURL(6, 8); // Zellgröße, Rand
      qrImg.style.display = '';
      qrFallback.style.display = 'none';
      drawn = true;
    } catch (e) { /* unten Fallback */ }
  }
  if (!drawn) {
    qrImg.style.display = 'none';
    qrFallback.style.display = '';
  }
  document.getElementById('twofaCode').focus();
};

document.getElementById('twofaConfirmBtn').onclick = async () => {
  const tm = document.getElementById('twofaMsg');
  tm.textContent = '';
  const s = await totpApi('confirm', { code: document.getElementById('twofaCode').value.trim() });
  if (!s.ok) { tm.textContent = s.error || T.twofaWrong; return; }
  document.getElementById('twofaSetup').style.display = 'none';
  document.getElementById('twofaRecovery').style.display = 'block';
  document.getElementById('twofaCodes').textContent = s.recoveryCodes.join('\n');
  loadTwofa();
};

disableBtn.onclick = async () => {
  const pw = prompt(T.twofaDisPw);
  if (pw === null) return;
  const s = await totpApi('disable', { password: pw });
  if (!s.ok) { msg.textContent = s.error || T.error; return; }
  document.getElementById('twofaRecovery').style.display = 'none';
  loadTwofa();
};

loadTwofa();

// ==================== Passwort ändern ====================
document.getElementById('pwSaveBtn').onclick = async () => {
  const m = document.getElementById('pwMsg');
  m.style.color = '#c0392b'; m.textContent = '';
  const cur = document.getElementById('pwCurrent').value;
  const nw  = document.getElementById('pwNew').value;
  const nw2 = document.getElementById('pwNew2').value;
  if (nw.length < 12) { m.textContent = T.pwMinLen; return; }
  if (nw !== nw2)    { m.textContent = T.pwMismatch; return; }
  const res = await fetch('api/auth.php?action=changepassword', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ current: cur, new: nw, csrf: CSRF })
  });
  const d = await res.json().catch(() => ({}));
  if (d.ok) {
    m.style.color = '#1e7d34';
    m.textContent = T.pwChanged;
    document.getElementById('pwCurrent').value = '';
    document.getElementById('pwNew').value = '';
    document.getElementById('pwNew2').value = '';
  } else {
    m.textContent = d.error || T.error;
  }
};

// ==================== Live-Standort teilen ====================
// watchPosition/Ping/Hinweis-Banner/Fortsetzen liegen im gemeinsamen
// Motor live-share.js (window.TLLiveShare). Hier nur die Dashboard-UI.
const liveStartEl  = document.getElementById('liveStart');
const liveActiveEl = document.getElementById('liveActive');
const liveMsg      = document.getElementById('liveMsg');
const liveStatus   = document.getElementById('liveStatus');
const liveUrlInp   = document.getElementById('liveUrl');

function liveTickExpiry() {
  const st = TLLiveShare.getState();
  if (!st.active || !st.expiresAt) return;
  const ms = new Date(st.expiresAt).getTime() - Date.now();
  if (ms <= 0) return; // Auto-Stopp erledigt der Motor
  const min = Math.ceil(ms / 60000);
  const end = new Date(st.expiresAt).toLocaleTimeString(LOCALE, { hour: '2-digit', minute: '2-digit' });
  const rest = (min >= 60 ? Math.round(min/60) + ' ' + T.hours : min + ' ' + T.mins);
  document.getElementById('liveExpiry').textContent = (LANG === 'en')
    ? ('Expires at ' + end + ' (' + rest + ' left).')
    : ('Läuft ab um ' + end + ' Uhr (noch ' + rest + ').');
}

// Karte an den Motor-Zustand anpassen (aktiv/inaktiv)
TLLiveShare.onChange((st) => {
  if (st.active) {
    liveStartEl.style.display = 'none';
    liveActiveEl.style.display = 'block';
    liveUrlInp.value = TLLiveShare.urlFor(st.token);
    if (navigator.share) document.getElementById('liveShareBtn').style.display = '';
    liveStatus.textContent = T.liveSending;
    liveTickExpiry();
  } else {
    liveActiveEl.style.display = 'none';
    liveStartEl.style.display = 'block';
  }
});
setInterval(liveTickExpiry, 1000);

document.getElementById('liveStartBtn').onclick = async () => {
  liveMsg.textContent = '';
  const label = document.getElementById('liveLabel').value.trim();
  const ttl = Number(document.getElementById('liveTtl').value);
  const d = await TLLiveShare.start(label, ttl);
  if (!d.ok) { liveMsg.style.color = '#c0392b'; liveMsg.textContent = d.error || T.error; }
};

document.getElementById('liveStopBtn').onclick = async () => {
  await TLLiveShare.stop();
  liveMsg.style.color = '#667';
  liveMsg.textContent = T.liveEnded;
};

document.getElementById('liveExtendBtn').onclick = async () => {
  const ttl = Number(document.getElementById('liveExtendTtl').value);
  const d = await TLLiveShare.extend(ttl);
  if (!d.ok) { liveMsg.style.color = '#c0392b'; liveMsg.textContent = d.error || T.error; return; }
  liveTickExpiry();
  if (d.capped) { liveMsg.style.color = '#8a5a00'; liveMsg.textContent = T.liveMaxReached; }
  const b = document.getElementById('liveExtendBtn');
  const t = b.textContent; b.textContent = T.extended;
  setTimeout(() => { b.textContent = t; }, 1500);
};

document.getElementById('liveCopyBtn').onclick = async () => {
  try { await navigator.clipboard.writeText(liveUrlInp.value); }
  catch (e) { liveUrlInp.select(); document.execCommand('copy'); }
  const b = document.getElementById('liveCopyBtn');
  const t = b.textContent; b.textContent = T.copied;
  setTimeout(() => { b.textContent = t; }, 1500);
};

document.getElementById('liveShareBtn').onclick = async () => {
  try { await navigator.share({ title: T.shareTitle, url: liveUrlInp.value }); }
  catch (e) { /* Nutzer hat abgebrochen — ignorieren */ }
};

// Dieselbe Ansicht wie der Empfänger (mit Zurück-Link).
// - In der nativen App: im SYSTEM-Browser öffnen (LiveShare.openExternal) —
//   die App bleibt auf der Teilen-Seite, der native Upload-Dienst sendet
//   weiter, und der Eigencheck zeigt die echten Live-Updates.
// - Im Browser: wie bisher in derselben Seite navigieren. Hinweis: Dort
//   pausiert währenddessen das Senden der eigenen Position — beim Zurück-
//   kehren zum Dashboard läuft es automatisch wieder an.
document.getElementById('livePreviewBtn').onclick = () => {
  const st = TLLiveShare.getState();
  if (!st.active) return;
  const url = TLLiveShare.urlFor(st.token) + '&preview=1';
  const Cap = window.Capacitor;
  const LS = Cap && Cap.isNativePlatform && Cap.isNativePlatform()
    && Cap.Plugins && Cap.Plugins.LiveShare;
  if (LS && LS.openExternal) {
    LS.openExternal({ url }).catch(() => { location.href = url; });
  } else {
    location.href = url;
  }
};

// Start-Ansicht-Auswahl: speichern/laden (localStorage, pro Gerät).
(function(){
  var sel = document.getElementById('startViewSel');
  if (!sel) return;
  try { sel.value = localStorage.getItem('tlStartView') || 'dashboard'; } catch(e){}
  sel.onchange = function(){ try { localStorage.setItem('tlStartView', sel.value); } catch(e){} };
})();

// Native-App-Einstellung: EXIF-Hinweis beim Album-Speichern (dcimExifInfo in
// map-settings, Schlüssel tl-dcimExifInfo). Nur in der Capacitor-App zeigen.
(function(){
  var Cap = window.Capacitor;
  if (!(Cap && Cap.isNativePlatform && Cap.isNativePlatform())) return;
  document.getElementById('nativeSettings').style.display = '';
  var chk = document.getElementById('dcimExifChk');
  try { chk.checked = (localStorage.getItem('tl-dcimExifInfo') || 'on') !== 'off'; }
  catch(e){ chk.checked = true; }
  chk.onchange = function(){
    try { localStorage.setItem('tl-dcimExifInfo', chk.checked ? 'on' : 'off'); } catch(e){}
  };
})();
// Diese Seite als „zuletzt geöffnet" merken (für die Start-Ansicht „Zuletzt").
try { localStorage.setItem('tlLast', location.pathname + location.search); } catch(e){}
</script>
</body>
</html>
