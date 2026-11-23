<?php
// Admin-Panel: Nutzerverwaltung, Inhalts-Einblick (nur-lesend, auditiert),
// Audit-Log. Zugang nur für Konten mit is_admin (siehe make-admin.php).
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';

$uid = current_user_id();
if ($uid === null || !is_admin_user($uid)) {
    header('Location: index.php');
    exit;
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= tl_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tourlocate — <?= L('Administration', 'Administration') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<link rel="icon" type="image/png" href="../img/logo.png">
<style>
  body{margin:0;font-family:system-ui,sans-serif;background:#f4f6f8;color:#1a1a1a}
  .bar{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;
       background:#fff;border-bottom:1px solid #e2e8f0}
  .brand{display:flex;align-items:center;gap:10px}
  .brand img{width:32px;height:32px;border-radius:50%}
  .bar-right{display:flex;align-items:center;gap:14px}
  .lang{font-size:12px}
  .lang a{color:#889;text-decoration:none}
  .wrap{max-width:960px;margin:24px auto;padding:0 16px}
  h1{font-size:20px;margin:0 0 4px}
  .note{font-size:12px;color:#8a5a00;background:#fff7e6;border:1px solid #f0d9a8;
        border-radius:8px;padding:8px 12px;margin:10px 0 18px}
  h2{font-size:16px;margin:26px 0 10px}
  table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;
        border-radius:10px;overflow:hidden;font-size:13px}
  th,td{padding:8px 10px;text-align:left;border-bottom:1px solid #eef2f6;vertical-align:top}
  th{background:#f8fafc;font-weight:600}
  tr:last-child td{border-bottom:0}
  button{padding:5px 10px;border:1px solid #cbd2d9;border-radius:6px;background:#fff;
         cursor:pointer;font:inherit;font-size:12px}
  button.danger{color:#c0392b;border-color:#e3b0ab}
  .badge{display:inline-block;font-size:11px;border-radius:20px;padding:1px 8px;margin-left:4px}
  .badge.admin{background:#e6f0fb;color:#2b6cb0}
  .badge.blocked{background:#fdecea;color:#c0392b}
  .badge.shared{background:#e8f6ec;color:#1e7d34}
  .msg{font-size:13px;color:#c0392b;min-height:1em;margin-top:10px}
  #presPanel{display:none;background:#fff;border:1px solid #e2e8f0;border-radius:10px;
             padding:14px 16px;margin-top:10px}
  #viewMap{height:340px;border-radius:8px;margin-top:10px;display:none}
  .poi-num{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;
           background:#2b6cb0;color:#fff;font-weight:700;font-size:13px;border:2px solid #fff;
           box-shadow:0 0 4px rgba(0,0,0,.3)}
  a.plain{color:#2b6cb0}
</style>
</head>
<body>
<div class="bar">
  <div class="brand">
    <img src="../img/logo.png" alt="" onerror="this.style.display='none'">
    <strong>Tourlocate — <?= L('Administration', 'Administration') ?></strong>
  </div>
  <div class="bar-right">
    <span class="lang"><?php if (tl_lang() === 'en'): ?><a href="#" data-setlang="de">DE</a> · <strong>EN</strong><?php else: ?><strong>DE</strong> · <a href="#" data-setlang="en">EN</a><?php endif; ?></span>
    <a class="plain" href="dashboard.php"><?= L('Zum Dashboard', 'To the dashboard') ?></a>
  </div>
</div>

<div class="wrap">
  <h1><?= L('Registrierung &amp; Zugang', 'Registration &amp; access') ?></h1>
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:10px">
    <label style="font-size:14px"><?= L('Wer kann sich anmelden?', 'Who can sign up?') ?>
      <select id="regMode" style="margin-left:8px;padding:6px 8px;border:1px solid #cbd2d9;border-radius:6px;font:inherit">
        <option value="open"><?= L('Offen — jeder kann sich registrieren', 'Open — anyone can register') ?></option>
        <option value="invite"><?= L('Nur mit Einladung', 'Invitation only') ?></option>
        <option value="closed"><?= L('Geschlossen — nur Admin legt Konten an', 'Closed — only the admin creates accounts') ?></option>
      </select>
    </label>
    <span id="regModeSaved" style="font-size:12px;color:#1e7d34;margin-left:10px"></span>

    <div id="inviteBlock" style="margin-top:14px;border-top:1px solid #eef2f6;padding-top:12px">
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <input id="inviteNote" type="text" placeholder="<?= L('Notiz (optional, z. B. Name)', 'Note (optional, e.g. name)') ?>" style="flex:1;min-width:160px;padding:7px 8px;border:1px solid #cbd2d9;border-radius:6px;font:inherit">
        <button id="createInviteBtn"><?= L('Einladungslink erzeugen', 'Create invitation link') ?></button>
      </div>
      <table id="invitesTable" style="margin-top:10px">
        <thead><tr><th><?= L('Erstellt', 'Created') ?></th><th><?= L('Notiz', 'Note') ?></th><th><?= L('Status', 'Status') ?></th><th><?= L('Link', 'Link') ?></th><th></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>

    <div style="margin-top:14px;border-top:1px solid #eef2f6;padding-top:12px">
      <strong style="font-size:14px"><?= L('Konto manuell anlegen', 'Create account manually') ?></strong>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:8px">
        <input id="newUserEmail" type="email" placeholder="<?= L('E-Mail', 'Email') ?>" style="flex:1;min-width:160px;padding:7px 8px;border:1px solid #cbd2d9;border-radius:6px;font:inherit">
        <input id="newUserPass" type="text" placeholder="<?= L('Passwort (min. 12)', 'Password (min. 12)') ?>" style="flex:1;min-width:140px;padding:7px 8px;border:1px solid #cbd2d9;border-radius:6px;font:inherit">
        <button id="createUserBtn"><?= L('Anlegen', 'Create') ?></button>
      </div>
      <div id="createUserMsg" style="font-size:13px;min-height:1em;margin-top:6px"></div>
    </div>
  </div>

  <h1 style="margin-top:26px"><?= L('Grenzen', 'Limits') ?></h1>
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:10px">
    <label style="font-size:14px"><?= L('Maximale Fotos pro Präsentation', 'Maximum photos per presentation') ?>
      <input id="maxImages" type="number" min="1" max="100000" style="margin-left:8px;width:100px;padding:6px 8px;border:1px solid #cbd2d9;border-radius:6px;font:inherit">
    </label>
    <button id="maxImagesSave" style="margin-left:8px"><?= L('Speichern', 'Save') ?></button>
    <span id="maxImagesSaved" style="font-size:12px;color:#1e7d34;margin-left:10px"></span>
    <p class="note" style="margin-top:8px"><?= L('Begrenzt die Hardware- und Speicherauslastung (≈ 200 KB je Bild — z. B. 500 Fotos ≈ 100 MB). Gilt für Foto-Upload und Cloud-Import. Bestehende Präsentationen über dem Limit nehmen keine weiteren Bilder mehr auf.', 'Limits hardware and storage load (≈ 200 KB per image — e.g. 500 photos ≈ 100 MB). Applies to photo upload and cloud import. Existing presentations over the limit accept no further images.') ?></p>
  </div>

  <h1 style="margin-top:26px"><?= L('Nutzerverwaltung', 'User management') ?></h1>
  <div class="note"><?= L('Jeder Einblick in fremde Präsentationen und jede Verwaltungsaktion wird im Audit-Log protokolliert. Einsicht nur anlassbezogen vornehmen (z. B. bei einer Meldung wegen rechtswidriger Inhalte).', 'Every look into other users\' presentations and every administrative action is recorded in the audit log. Only inspect content when there is a specific reason (e.g. a report about unlawful content).') ?></div>

  <table id="usersTable">
    <thead><tr><th><?= L('ID', 'ID') ?></th><th><?= L('E-Mail', 'Email') ?></th><th><?= L('Registriert', 'Registered') ?></th><th><?= L('Präsentationen', 'Presentations') ?></th>
    <th><?= L('Bilder', 'Images') ?></th><th><?= L('Status', 'Status') ?></th><th><?= L('Aktionen', 'Actions') ?></th></tr></thead>
    <tbody></tbody>
  </table>

  <div id="presPanel">
    <strong id="presTitle"></strong>
    <table id="presTable" style="margin-top:8px">
      <thead><tr><th><?= L('ID', 'ID') ?></th><th><?= L('Titel', 'Title') ?></th><th><?= L('Bilder', 'Images') ?></th><th><?= L('Geteilt', 'Shared') ?></th><th><?= L('Geändert', 'Modified') ?></th><th><?= L('Aktionen', 'Actions') ?></th></tr></thead>
      <tbody></tbody>
    </table>
    <div id="viewMap"></div>
  </div>

  <h2><?= L('Meldungen (Inhalt melden)', 'Reports (report content)') ?></h2>
  <table id="reportsTable">
    <thead><tr><th><?= L('Zeit', 'Time') ?></th><th><?= L('Präsentation', 'Presentation') ?></th><th><?= L('Kategorie', 'Category') ?></th><th><?= L('Begründung', 'Reason') ?></th><th><?= L('Melder', 'Reporter') ?></th><th><?= L('Status', 'Status') ?></th><th><?= L('Aktionen', 'Actions') ?></th></tr></thead>
    <tbody></tbody>
  </table>

  <h2><?= L('Audit-Log (letzte 200 Einträge)', 'Audit log (last 200 entries)') ?></h2>
  <table id="auditTable">
    <thead><tr><th><?= L('Zeit', 'Time') ?></th><th><?= L('Admin', 'Admin') ?></th><th><?= L('Aktion', 'Action') ?></th><th><?= L('Nutzer', 'User') ?></th><th><?= L('Präs.', 'Pres.') ?></th><th><?= L('Detail', 'Detail') ?></th></tr></thead>
    <tbody></tbody>
  </table>

  <h2 style="margin-top:28px"><?= L('E-Mail-Versand (SMTP)', 'Email delivery (SMTP)') ?></h2>
  <p style="font-size:13px;color:#667;margin:0 0 10px"><?= L('Für „Passwort vergessen". Wird in der Datenbank gespeichert (Passwort verschlüsselt) und bleibt bei einem Neu-Deploy erhalten. Empfehlung: ein SMTP-Relay / Transaktions-Mail-Dienst (gute Zustellbarkeit, nicht direkt von der Server-IP). Diese Werte haben Vorrang vor Umgebungsvariablen / config.php.', 'For "forgot password". Stored in the database (password encrypted) and preserved across redeploys. Recommended: an SMTP relay / transactional mail service (good deliverability, not directly from the server IP). These values take precedence over environment variables / config.php.') ?></p>
  <style>
    #mailForm{display:grid;grid-template-columns:150px 1fr;gap:8px 10px;max-width:540px;align-items:center;font-size:13px}
    #mailForm input, #mailForm select{padding:6px 8px;border:1px solid #cbd2d9;border-radius:6px;font:inherit;width:100%;box-sizing:border-box}
    @media (max-width:520px){ #mailForm{grid-template-columns:1fr} #mailForm label{margin-top:6px} }
  </style>
  <div id="mailForm">
    <label for="mHost"><?= L('Host', 'Host') ?></label><input id="mHost" type="text" autocomplete="off" placeholder="smtp.example.org">
    <label for="mPort"><?= L('Port', 'Port') ?></label><input id="mPort" type="number" min="1" max="65535" placeholder="587">
    <label for="mSecure"><?= L('Verschlüsselung', 'Encryption') ?></label>
    <select id="mSecure">
      <option value="tls"><?= L('STARTTLS (Port 587)', 'STARTTLS (port 587)') ?></option>
      <option value="ssl"><?= L('SSL/TLS (Port 465)', 'SSL/TLS (port 465)') ?></option>
      <option value="none"><?= L('keine', 'none') ?></option>
    </select>
    <label for="mUser"><?= L('Benutzer', 'User') ?></label><input id="mUser" type="text" autocomplete="off">
    <label for="mPass"><?= L('Passwort', 'Password') ?></label><input id="mPass" type="password" autocomplete="new-password">
    <label for="mFrom"><?= L('Absender (From)', 'Sender (from)') ?></label><input id="mFrom" type="email" placeholder="noreply@example.org">
    <label for="mFromName"><?= L('Absender-Name', 'Sender name') ?></label><input id="mFromName" type="text" placeholder="Tourlocate">
    <label for="mBaseUrl"><?= L('Basis-URL', 'Base URL') ?></label><input id="mBaseUrl" type="url" placeholder="https://tourlocate.example.org">
  </div>
  <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <button id="mailSaveBtn"><?= L('Speichern', 'Save') ?></button>
    <button id="mailTestBtn"><?= L('Testmail an mich senden', 'Send test email to me') ?></button>
    <label style="font-size:12px;color:#667"><input type="checkbox" id="mClearPass"> <?= L('Passwort löschen', 'Delete password') ?></label>
    <span id="mailMsg" style="font-size:13px"></span>
  </div>

  <h2 style="margin-top:28px"><?= L('Rechtstexte (Impressum, Datenschutz &amp; Serverregeln)', 'Legal texts (imprint, privacy &amp; server rules)') ?></h2>
  <p style="font-size:13px;color:#667;margin:0 0 10px"><?= L('Diese Texte werden in der Datenbank gespeichert und bleiben bei einem Neu-Deploy erhalten. HTML ist erlaubt (z. B. &lt;h2&gt;, &lt;p&gt;, &lt;a&gt;, &lt;ul&gt;/&lt;li&gt;). Leer lassen zeigt wieder die Platzhalter-Vorlage (bei den Serverregeln: das eingebaute Standard-Regelwerk). Die Überschrift und die Fußzeilen-Links setzt die Seite selbst.', 'These texts are stored in the database and preserved across redeploys. HTML is allowed (e.g. &lt;h2&gt;, &lt;p&gt;, &lt;a&gt;, &lt;ul&gt;/&lt;li&gt;). Leaving a field empty shows the placeholder template again (for the server rules: the built-in default ruleset). The page itself sets the heading and footer links.') ?></p>
  <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:12px">
    <label style="font-size:13px;font-weight:600"><?= L('Impressum', 'Imprint') ?> (<a href="impressum.php" target="_blank" rel="noopener"><?= L('ansehen', 'view') ?></a>)</label>
    <textarea id="legalImpressum" rows="8" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #cbd2d9;border-radius:7px;font:13px/1.45 ui-monospace,monospace"></textarea>
  </div>
  <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:10px">
    <label style="font-size:13px;font-weight:600"><?= L('Datenschutzerklärung', 'Privacy policy') ?> (<a href="datenschutz.php" target="_blank" rel="noopener"><?= L('ansehen', 'view') ?></a>)</label>
    <textarea id="legalDatenschutz" rows="10" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #cbd2d9;border-radius:7px;font:13px/1.45 ui-monospace,monospace"></textarea>
  </div>
  <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:10px">
    <label style="font-size:13px;font-weight:600"><?= L('Serverregeln', 'Server rules') ?> (<a href="serverregeln.php" target="_blank" rel="noopener"><?= L('ansehen', 'view') ?></a>) — <?= L('werden bei der Registrierung akzeptiert', 'are accepted during registration') ?></label>
    <textarea id="legalServerregeln" rows="10" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #cbd2d9;border-radius:7px;font:13px/1.45 ui-monospace,monospace"></textarea>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <button id="legalSaveBtn"><?= L('Rechtstexte speichern', 'Save legal texts') ?></button>
    <span id="legalSaved" style="font-size:12px;color:#1e7d34"></span>
  </div>

  <div class="msg" id="msg"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const CSRF = <?= json_encode($csrf) ?>;
const LOCALE = <?= json_encode(tl_lang() === 'en' ? 'en-GB' : 'de-DE') ?>;
// Dynamische (per JS gebaute) Texte — zweisprachig aus PHP. {platzhalter}
// werden unten ersetzt.
const T = {
  err:            <?= json_encode(L('Fehler.', 'Error.')) ?>,
  badgeAdmin:     <?= json_encode(L('Admin', 'Admin')) ?>,
  blocked:        <?= json_encode(L('gesperrt', 'blocked')) ?>,
  active:         <?= json_encode(L('aktiv', 'active')) ?>,
  btnContents:    <?= json_encode(L('Inhalte', 'Content')) ?>,
  btnPassword:    <?= json_encode(L('Passwort', 'Password')) ?>,
  btnBlock:       <?= json_encode(L('Sperren', 'Block')) ?>,
  btnUnblock:     <?= json_encode(L('Entsperren', 'Unblock')) ?>,
  btnDelete:      <?= json_encode(L('Löschen', 'Delete')) ?>,
  presOf:         <?= json_encode(L('Präsentationen von {email}', 'Presentations of {email}')) ?>,
  yes:            <?= json_encode(L('ja', 'yes')) ?>,
  no:             <?= json_encode(L('nein', 'no')) ?>,
  btnView:        <?= json_encode(L('Ansehen (auditiert)', 'View (audited)')) ?>,
  presEmpty:      <?= json_encode(L('Keine Präsentationen.', 'No presentations.')) ?>,
  pwPrompt:       <?= json_encode(L('Neues Passwort für {email} (mind. 12 Zeichen):', 'New password for {email} (min. 12 characters):')) ?>,
  pwTooShort:     <?= json_encode(L('Das Passwort muss mindestens 12 Zeichen lang sein.', 'The password must be at least 12 characters long.')) ?>,
  pwSet:          <?= json_encode(L('Passwort für {email} gesetzt ✓', 'Password for {email} set ✓')) ?>,
  blockConfirm:   <?= json_encode(L('{action}: {email}?', '{action}: {email}?')) ?>,
  delUser1:       <?= json_encode(L('Konto {email} MIT ALLEN INHALTEN endgültig löschen?', 'Permanently delete account {email} WITH ALL CONTENT?')) ?>,
  delUser2:       <?= json_encode(L('Wirklich sicher? Das kann nicht rückgängig gemacht werden.', 'Are you really sure? This cannot be undone.')) ?>,
  station:        <?= json_encode(L('Station', 'Station')) ?>,
  osmAttr:        <?= json_encode(L('© OpenStreetMap-Mitwirkende', '© OpenStreetMap contributors')) ?>,
  btnShowMap:     <?= json_encode(L('Karte zeigen', 'Show map')) ?>,
  delPresConfirm: <?= json_encode(L('Präsentation #{id} mit allen Bildern löschen?', 'Delete presentation #{id} with all images?')) ?>,
  auditEmpty:     <?= json_encode(L('Noch keine Einträge.', 'No entries yet.')) ?>,
  catUrheberrecht:   <?= json_encode(L('Urheberrecht', 'Copyright')) ?>,
  catPersoenlichkeit:<?= json_encode(L('Persönlichkeitsrecht', 'Personal rights')) ?>,
  catGewaltHass:     <?= json_encode(L('Gewalt / Hass', 'Violence / hate')) ?>,
  catSpam:           <?= json_encode(L('Spam', 'Spam')) ?>,
  catSonstiges:      <?= json_encode(L('Sonstiges', 'Other')) ?>,
  reportUser:     <?= json_encode(L('Nutzer', 'User')) ?>,
  reportOpen:     <?= json_encode(L('offen', 'open')) ?>,
  reportDone:     <?= json_encode(L('erledigt', 'resolved')) ?>,
  btnResolve:     <?= json_encode(L('Erledigt', 'Resolve')) ?>,
  reportsEmpty:   <?= json_encode(L('Keine Meldungen.', 'No reports.')) ?>,
  reportedPres:   <?= json_encode(L('Gemeldete Präsentation #{id} — {title}', 'Reported presentation #{id} — {title}')) ?>,
  saved:          <?= json_encode(L('Gespeichert ✓', 'Saved ✓')) ?>,
  usedBy:         <?= json_encode(L('verwendet ({email})', 'used ({email})')) ?>,
  invitesEmpty:   <?= json_encode(L('Noch keine Einladungen.', 'No invitations yet.')) ?>,
  userCreated:    <?= json_encode(L('Konto angelegt: {email}', 'Account created: {email}')) ?>,
  mailPassPh:     <?= json_encode(L('•••••• (gespeichert — leer = unverändert)', '•••••• (saved — empty = unchanged)')) ?>,
  saving:         <?= json_encode(L('Speichere…', 'Saving…')) ?>,
  sendingTest:    <?= json_encode(L('Sende Testmail…', 'Sending test email…')) ?>,
  testSent:       <?= json_encode(L('Testmail an {to} gesendet ✓ (auch Spam-Ordner prüfen)', 'Test email sent to {to} ✓ (check the spam folder too)')) ?>,
  sendFail:       <?= json_encode(L('Versand fehlgeschlagen.', 'Sending failed.')) ?>,
};
const fill = (tpl, vars) => Object.entries(vars || {}).reduce((s, [k, v]) => s.replaceAll('{' + k + '}', v), tpl);

const msg = document.getElementById('msg');
const esc = s => String(s==null?'':s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const fmt = iso => { const d=new Date(iso); return isNaN(d)?'':d.toLocaleString(LOCALE,{dateStyle:'short',timeStyle:'short'}); };

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

async function api(action, body, params='') {
  const opts = { headers: { 'X-CSRF-Token': CSRF } };
  if (body) {
    opts.method='POST';
    opts.headers['Content-Type']='application/json';
    opts.body=JSON.stringify({ ...body, csrf: CSRF });
  }
  const res = await fetch('api/admin.php?action='+action+params, opts);
  return res.json().catch(()=>({}));
}

async function loadUsers() {
  const d = await api('users');
  if (!d.ok) { msg.textContent = d.error || T.err; return; }
  document.querySelector('#usersTable tbody').innerHTML = d.users.map(u => `
    <tr data-id="${u.id}" data-email="${esc(u.email)}">
      <td>${u.id}</td>
      <td>${esc(u.email)}${u.is_admin?`<span class="badge admin">${T.badgeAdmin}</span>`:''}${u.blocked?`<span class="badge blocked">${T.blocked}</span>`:''}</td>
      <td>${fmt(u.created_at)}</td>
      <td>${u.pres_count}</td>
      <td>${u.image_count}</td>
      <td>${u.blocked?T.blocked:T.active}</td>
      <td>
        <button data-act="pres">${T.btnContents}</button>
        <button data-act="setpw">${T.btnPassword}</button>
        ${u.is_admin?'':`<button data-act="block">${u.blocked?T.btnUnblock:T.btnBlock}</button>
        <button class="danger" data-act="del">${T.btnDelete}</button>`}
      </td>
    </tr>`).join('');
}

document.querySelector('#usersTable tbody').addEventListener('click', async e => {
  const btn = e.target.closest('button'); if (!btn) return;
  const tr = e.target.closest('tr'); const id = Number(tr.dataset.id); const email = tr.dataset.email;
  msg.textContent = ''; msg.style.color = '#c0392b';
  if (btn.dataset.act === 'pres') {
    const d = await api('presentations', null, '&user_id='+id);
    if (!d.ok) { msg.textContent = d.error||T.err; return; }
    document.getElementById('presPanel').style.display='block';
    document.getElementById('presTitle').textContent = fill(T.presOf, { email });
    document.getElementById('viewMap').style.display='none';
    document.querySelector('#presTable tbody').innerHTML = d.presentations.map(p => `
      <tr data-id="${p.id}">
        <td>${p.id}</td><td>${esc(p.title)}</td><td>${p.image_count}</td>
        <td>${p.shared?`<span class="badge shared">${T.yes}</span>`:T.no}</td>
        <td>${fmt(p.updated_at)}</td>
        <td><button data-act="view">${T.btnView}</button>
            <button class="danger" data-act="delpres">${T.btnDelete}</button></td>
      </tr>`).join('') || `<tr><td colspan="6">${T.presEmpty}</td></tr>`;
    document.getElementById('presPanel').scrollIntoView({behavior:'smooth'});
  } else if (btn.dataset.act === 'setpw') {
    const pw = prompt(fill(T.pwPrompt, { email }));
    if (pw === null) return;
    if (pw.length < 12) { msg.textContent = T.pwTooShort; return; }
    const d = await api('setpassword', { user_id: id, password: pw });
    if (d.ok) { msg.style.color = '#1e7d34'; msg.textContent = fill(T.pwSet, { email }); loadAudit(); }
    else { msg.style.color = '#c0392b'; msg.textContent = d.error || T.err; }
  } else if (btn.dataset.act === 'block') {
    const blocked = btn.textContent.trim() === T.btnBlock ? 1 : 0;
    if (!confirm(fill(T.blockConfirm, { action: btn.textContent.trim(), email }))) return;
    const d = await api('block', { user_id: id, blocked });
    if (d.ok) { loadUsers(); loadAudit(); } else msg.textContent = d.error||T.err;
  } else if (btn.dataset.act === 'del') {
    if (!confirm(fill(T.delUser1, { email }))) return;
    if (!confirm(T.delUser2)) return;
    const d = await api('deleteuser', { user_id: id });
    if (d.ok) { document.getElementById('presPanel').style.display='none'; loadUsers(); loadAudit(); }
    else msg.textContent = d.error||T.err;
  }
});

let viewMap = null, viewLayer = null;
document.querySelector('#presTable tbody').addEventListener('click', async e => {
  const btn = e.target.closest('button'); if (!btn) return;
  const id = Number(e.target.closest('tr').dataset.id);
  msg.textContent = '';
  if (btn.dataset.act === 'view') {
    const d = await api('presentation', null, '&id='+id);
    if (!d.ok) { msg.textContent = d.error||T.err; return; }
    const el = document.getElementById('viewMap');
    el.style.display='block';
    if (!viewMap) { viewMap = L.map('viewMap'); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:T.osmAttr}).addTo(viewMap); }
    if (viewLayer) viewLayer.remove();
    viewLayer = L.featureGroup().addTo(viewMap);
    (d.presentation.data.pois||[]).forEach((p,i) => {
      const icon = L.divIcon({className:'poi-num', html:String(i+1), iconSize:[26,26], iconAnchor:[13,13]});
      L.marker([p.lat,p.lng],{icon}).addTo(viewLayer)
        .bindPopup('<strong>'+esc((i+1)+'. '+(p.name||T.station))+'</strong>'
          + (p.img ? '<br><img src="'+esc(p.img)+'" style="max-width:150px;max-height:110px;margin-top:6px;border-radius:6px">' : ''));
    });
    setTimeout(() => {
      viewMap.invalidateSize();
      const b = viewLayer.getBounds();
      if (b.isValid()) viewMap.fitBounds(b.pad(0.2)); else viewMap.setView([51,10],5);
    }, 50);
    loadAudit(); // der Einblick ist soeben protokolliert worden
  } else if (btn.dataset.act === 'delpres') {
    if (!confirm(fill(T.delPresConfirm, { id }))) return;
    const d = await api('deletepresentation', { id });
    if (d.ok) { loadUsers(); loadAudit(); document.getElementById('viewMap').style.display='none'; }
    else msg.textContent = d.error||T.err;
  }
});

async function loadAudit() {
  const d = await api('audit');
  if (!d.ok) return;
  document.querySelector('#auditTable tbody').innerHTML = d.audit.map(a => `
    <tr><td>${fmt(a.created_at)}</td><td>${esc(a.admin_email)}</td><td>${esc(a.action)}</td>
    <td>${a.target_user_id??''}</td><td>${a.target_pres_id??''}</td><td>${esc(a.detail)}</td></tr>`).join('')
    || `<tr><td colspan="6">${T.auditEmpty}</td></tr>`;
}

async function loadReports() {
  const d = await api('reports');
  if (!d.ok) return;
  const catLabels = { urheberrecht:T.catUrheberrecht, persoenlichkeit:T.catPersoenlichkeit, gewalt_hass:T.catGewaltHass, spam:T.catSpam, sonstiges:T.catSonstiges };
  document.querySelector('#reportsTable tbody').innerHTML = d.reports.map(r => `
    <tr>
      <td>${fmt(r.created_at)}</td>
      <td>#${r.presentation_id} ${esc(r.title)} (${T.reportUser} ${r.user_id})</td>
      <td>${r.category ? esc(catLabels[r.category] || r.category) : '—'}</td>
      <td style="max-width:260px">${esc(r.reason)}</td>
      <td>${r.reporter_email ? `<a href="mailto:${esc(r.reporter_email)}">${esc(r.reporter_email)}</a>` : '—'}</td>
      <td>${r.status === 'open' ? `<span class="badge blocked">${T.reportOpen}</span>` : T.reportDone}</td>
      <td>
        <button data-view="${r.presentation_id}">${T.btnView}</button>
        ${r.status === 'open' ? `<button data-close="${r.id}">${T.btnResolve}</button>` : ''}
      </td>
    </tr>`).join('') || `<tr><td colspan="7">${T.reportsEmpty}</td></tr>`;
}

document.querySelector('#reportsTable tbody').addEventListener('click', async e => {
  const btn = e.target.closest('button'); if (!btn) return;
  msg.textContent = '';
  if (btn.dataset.view) {
    // gleicher auditierter Einblick wie im Präsentations-Panel
    const d = await api('presentation', null, '&id=' + btn.dataset.view);
    if (!d.ok) { msg.textContent = d.error || T.err; return; }
    const panel = document.getElementById('presPanel');
    panel.style.display = 'block';
    document.getElementById('presTitle').textContent =
      fill(T.reportedPres, { id: d.presentation.id, title: d.presentation.title });
    document.querySelector('#presTable tbody').innerHTML =
      `<tr data-id="${d.presentation.id}"><td>${d.presentation.id}</td><td>${esc(d.presentation.title)}</td>
       <td>-</td><td>${d.presentation.shared?T.yes:T.no}</td><td>-</td>
       <td><button data-act="view">${T.btnShowMap}</button>
           <button class="danger" data-act="delpres">${T.btnDelete}</button></td></tr>`;
    panel.scrollIntoView({behavior:'smooth'});
    loadAudit();
  } else if (btn.dataset.close) {
    const d = await api('closereport', { id: Number(btn.dataset.close) });
    if (d.ok) { loadReports(); loadAudit(); } else msg.textContent = d.error || T.err;
  }
});

// ==================== Registrierung & Zugang ====================
const regMode = document.getElementById('regMode');
const inviteBlock = document.getElementById('inviteBlock');

async function loadRegSettings() {
  const s = await api('settings');
  if (!s.ok) return;
  regMode.value = s.registrationMode;
  inviteBlock.style.display = s.registrationMode === 'invite' ? 'block' : 'none';
  document.getElementById('maxImages').value = s.maxImages;
}

document.getElementById('maxImagesSave').onclick = async () => {
  const saved = document.getElementById('maxImagesSaved');
  saved.style.color = '#1e7d34'; saved.textContent = '';
  const s = await api('setmaximages', { maxImages: Number(document.getElementById('maxImages').value) });
  if (s.ok) {
    document.getElementById('maxImages').value = s.maxImages;
    saved.textContent = T.saved;
    setTimeout(() => saved.textContent = '', 1800);
  } else { saved.style.color = '#c0392b'; saved.textContent = s.error || T.err; }
};

regMode.onchange = async () => {
  const s = await api('setmode', { mode: regMode.value });
  if (s.ok) {
    inviteBlock.style.display = s.registrationMode === 'invite' ? 'block' : 'none';
    const saved = document.getElementById('regModeSaved');
    saved.textContent = T.saved;
    setTimeout(() => saved.textContent = '', 1800);
    if (s.registrationMode === 'invite') loadInvites();
  } else msg.textContent = s.error || T.err;
};

async function loadInvites() {
  const d = await api('invites');
  if (!d.ok) return;
  document.querySelector('#invitesTable tbody').innerHTML = d.invites.map(i => {
    const url = new URL('index.php?invite=' + i.token, location.href).href;
    const used = i.used_at
      ? fill(T.usedBy, { email: esc(i.used_by_email || '') })
      : `<span class="badge blocked">${T.reportOpen}</span>`;
    return `<tr data-id="${i.id}">
      <td>${fmt(i.created_at)}</td>
      <td>${esc(i.note || '')}</td>
      <td>${used}</td>
      <td>${i.used_at ? '' : `<input readonly value="${esc(url)}" style="width:230px;font-size:11px;padding:3px 5px;border:1px solid #cbd2d9;border-radius:5px" onclick="this.select()">`}</td>
      <td>${i.used_at ? '' : `<button data-del="1">${T.btnDelete}</button>`}</td>
    </tr>`;
  }).join('') || `<tr><td colspan="5">${T.invitesEmpty}</td></tr>`;
}

document.getElementById('createInviteBtn').onclick = async () => {
  const s = await api('createinvite', { note: document.getElementById('inviteNote').value });
  if (s.ok) { document.getElementById('inviteNote').value = ''; loadInvites(); }
  else msg.textContent = s.error || T.err;
};

document.querySelector('#invitesTable tbody').addEventListener('click', async e => {
  const btn = e.target.closest('button[data-del]'); if (!btn) return;
  const id = Number(e.target.closest('tr').dataset.id);
  const s = await api('deleteinvite', { id });
  if (s.ok) loadInvites(); else msg.textContent = s.error || T.err;
});

document.getElementById('createUserBtn').onclick = async () => {
  const m = document.getElementById('createUserMsg');
  m.style.color = '#c0392b'; m.textContent = '';
  const s = await api('createuser', {
    email: document.getElementById('newUserEmail').value,
    password: document.getElementById('newUserPass').value
  });
  if (s.ok) {
    m.style.color = '#1e7d34';
    m.textContent = fill(T.userCreated, { email: s.email });
    document.getElementById('newUserEmail').value = '';
    document.getElementById('newUserPass').value = '';
    loadUsers();
  } else m.textContent = s.error || T.err;
};

// ---- Rechtstexte (Impressum/Datenschutz) ----
async function loadLegal() {
  const s = await api('legal');
  if (!s.ok) return;
  document.getElementById('legalImpressum').value = s.impressum || '';
  document.getElementById('legalDatenschutz').value = s.datenschutz || '';
  document.getElementById('legalServerregeln').value = s.serverregeln || '';
}
document.getElementById('legalSaveBtn').onclick = async () => {
  const saved = document.getElementById('legalSaved');
  saved.style.color = '#1e7d34'; saved.textContent = '';
  const s = await api('setlegal', {
    impressum: document.getElementById('legalImpressum').value,
    datenschutz: document.getElementById('legalDatenschutz').value,
    serverregeln: document.getElementById('legalServerregeln').value
  });
  if (s.ok) { saved.textContent = T.saved; setTimeout(() => { saved.textContent=''; }, 2500); }
  else { saved.style.color = '#c0392b'; saved.textContent = s.error || T.err; }
};

// ---- E-Mail-Versand (SMTP) ----
async function loadMailSettings() {
  const s = await api('mailsettings');
  if (!s.ok) return;
  document.getElementById('mHost').value = s.host || '';
  document.getElementById('mPort').value = s.port || 587;
  document.getElementById('mUser').value = s.user || '';
  document.getElementById('mFrom').value = s.from || '';
  document.getElementById('mFromName').value = s.fromName || '';
  document.getElementById('mBaseUrl').value = s.baseUrl || '';
  document.getElementById('mSecure').value = s.secure || 'tls';
  document.getElementById('mPass').placeholder = s.hasPass ? T.mailPassPh : '';
}
document.getElementById('mailSaveBtn').onclick = async () => {
  const mm = document.getElementById('mailMsg');
  mm.style.color = '#1e7d34'; mm.textContent = T.saving;
  const s = await api('setmailsettings', {
    host: document.getElementById('mHost').value,
    port: Number(document.getElementById('mPort').value) || 587,
    user: document.getElementById('mUser').value,
    pass: document.getElementById('mPass').value,
    clearpass: document.getElementById('mClearPass').checked ? 1 : 0,
    from: document.getElementById('mFrom').value,
    fromName: document.getElementById('mFromName').value,
    secure: document.getElementById('mSecure').value,
    baseUrl: document.getElementById('mBaseUrl').value
  });
  if (s.ok) {
    mm.style.color = '#1e7d34'; mm.textContent = T.saved;
    document.getElementById('mPass').value = '';
    document.getElementById('mClearPass').checked = false;
    loadMailSettings();
  } else { mm.style.color = '#c0392b'; mm.textContent = s.error || T.err; }
};
document.getElementById('mailTestBtn').onclick = async () => {
  const mm = document.getElementById('mailMsg');
  mm.style.color = '#667'; mm.textContent = T.sendingTest;
  const s = await api('testmail', {});
  if (s.ok) { mm.style.color = '#1e7d34'; mm.textContent = fill(T.testSent, { to: s.to }); }
  else { mm.style.color = '#c0392b'; mm.textContent = s.error || T.sendFail; }
};

loadRegSettings(); loadInvites();
loadUsers(); loadAudit(); loadReports(); loadLegal(); loadMailSettings();
</script>
</body>
</html>
