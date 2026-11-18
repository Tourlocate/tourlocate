<?php
// Einstiegsseite der User-Version: angemeldet → weiter zum Dashboard,
// sonst Login-/Registrierungsformular.
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';

// Sprachumschaltung (?lang=de|en) für nicht angemeldete Besucher: Cookie
// setzen + sauber neu laden.
if (tl_handle_lang_switch()) {
    exit;
}

if (current_user_id() !== null) {
    header('Location: dashboard.php');
    exit;
}
$csrf = csrf_token();

// Registrierung: Modus + ggf. mitgeschickter Einladungs-Token
$regMode = tl_registration_mode();
$inviteToken = trim((string) ($_GET['invite'] ?? ''));
$inviteValid = false;
if ($regMode === 'invite' && $inviteToken !== '') {
    $st = db()->prepare('SELECT 1 FROM invites WHERE token = ? AND used_by IS NULL');
    $st->execute([$inviteToken]);
    $inviteValid = (bool) $st->fetchColumn();
}
// Selbstregistrierung möglich? (offen; oder Einladung mit gültigem Token)
$canRegister = $regMode === 'open' || ($regMode === 'invite' && $inviteValid);
?>
<!DOCTYPE html>
<html lang="<?= tl_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tourlocate — <?= L('Anmelden', 'Sign in') ?></title>
<link rel="icon" type="image/png" href="../img/logo.png">
<style>
  body{margin:0;font-family:system-ui,sans-serif;background:#f4f6f8;color:#1a1a1a;
       display:flex;min-height:100vh;align-items:center;justify-content:center;padding:16px}
  .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.1);
        padding:28px 26px;width:100%;max-width:360px}
  .lang{text-align:right;font-size:12px;margin-bottom:4px}
  .lang a{color:#889;text-decoration:none}
  .brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
  .brand img{width:36px;height:36px;border-radius:50%}
  .brand strong{font-size:20px}
  h1{font-size:16px;margin:0 0 14px;font-weight:600}
  label{display:block;font-size:13px;margin:10px 0 4px;color:#444}
  input{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid #cbd2d9;
        border-radius:7px;font:inherit}
  button{width:100%;margin-top:16px;padding:10px;border:0;border-radius:7px;
         background:#2b6cb0;color:#fff;font:inherit;font-weight:600;cursor:pointer}
  button:disabled{opacity:.6;cursor:not-allowed}
  .switch{margin-top:14px;font-size:13px;text-align:center;color:#555}
  .switch a{color:#2b6cb0;cursor:pointer;text-decoration:underline}
  .msg{margin-top:12px;font-size:13px;min-height:1em}
  .msg.err{color:#c0392b}
  .msg.ok{color:#1e7d34}
</style>
</head>
<body>
<div class="card">
  <div class="lang">
<?php if (tl_lang() === 'en'): ?>
    <a href="?lang=de">DE</a> · <strong>EN</strong>
<?php else: ?>
    <strong>DE</strong> · <a href="?lang=en">EN</a>
<?php endif; ?>
  </div>
  <div class="brand">
    <img src="../img/logo.png" alt="" onerror="this.style.display='none'">
    <strong>Tourlocate</strong>
  </div>
  <h1 id="formTitle"><?= L('Anmelden', 'Sign in') ?></h1>
  <form id="authForm" autocomplete="on">
    <label for="email"><?= L('E-Mail', 'Email') ?></label>
    <input id="email" name="email" type="email" required autocomplete="email">
    <label for="password"><?= L('Passwort', 'Password') ?></label>
    <input id="password" name="password" type="password" required
           autocomplete="current-password" minlength="8">
    <div id="totpRow" style="display:none">
      <label for="totpCode"><?= L('Code aus der Authenticator-App (oder Recovery-Code)', 'Code from your authenticator app (or a recovery code)') ?></label>
      <input id="totpCode" type="text" inputmode="numeric" autocomplete="one-time-code"
             placeholder="123456">
    </div>
    <label id="rememberRow" style="display:flex;align-items:center;gap:8px;font-weight:normal;margin:6px 0">
      <input type="checkbox" id="rememberChk" style="width:auto;margin:0;flex:none"> <?= L('Angemeldet bleiben', 'Stay signed in') ?>
    </label>
    <!-- Nur im Registrieren-Modus sichtbar; Pflicht (required + Server-Prüfung) -->
    <label id="rulesRow" style="display:none;align-items:center;gap:8px;font-weight:normal;margin:6px 0">
      <input type="checkbox" id="rulesChk" style="width:auto;margin:0;flex:none">
      <span><?= L('Ich akzeptiere die', 'I accept the') ?> <a href="serverregeln.php" target="_blank" rel="noopener"><?= L('Serverregeln', 'server rules') ?></a></span>
    </label>
    <button type="submit" id="submitBtn"><?= L('Anmelden', 'Sign in') ?></button>
  </form>
  <div class="switch" id="switchLine">
    <?php if ($canRegister): ?><?= L('Noch kein Konto?', 'No account yet?') ?> <a id="switchLink"><?= L('Registrieren', 'Sign up') ?></a>
    <?php elseif ($regMode === 'invite'): ?><span style="color:#889"><?= L('Registrierung nur mit Einladung.', 'Registration by invitation only.') ?></span>
    <?php endif; ?>
  </div>
  <div class="switch" id="forgotLine"><a id="forgotLink"><?= L('Passwort vergessen?', 'Forgot password?') ?></a></div>
  <div id="forgotBox" style="display:none;margin-top:12px;border-top:1px solid #eef2f6;padding-top:12px">
    <label for="forgotEmail"><?= L('E-Mail für den Link zum Zurücksetzen', 'Email for the reset link') ?></label>
    <input id="forgotEmail" type="email" autocomplete="email">
    <button type="button" id="forgotSendBtn"><?= L('Link senden', 'Send link') ?></button>
    <div class="msg" id="forgotMsg"></div>
  </div>
  <div class="msg" id="msg"></div>
  <div style="margin-top:16px;text-align:center;font-size:12px">
    <a href="impressum.php" style="color:#889"><?= L('Impressum', 'Legal notice') ?></a> ·
    <a href="datenschutz.php" style="color:#889"><?= L('Datenschutz', 'Privacy') ?></a> ·
    <a href="serverregeln.php" style="color:#889"><?= L('Serverregeln', 'Server rules') ?></a>
    <span id="serverSwitchWrap" style="display:none"> ·
      <a id="serverSwitchLink" style="color:#889;cursor:pointer"><?= L('Server wechseln', 'Switch server') ?></a></span>
  </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
const LANG = <?= json_encode(tl_lang()) ?>; // bei der Registrierung ans Konto
// Übersetzte JS-Texte (serverseitig passend zur Seitensprache gewählt)
const T = {
  login:        <?= json_encode(L('Anmelden', 'Sign in')) ?>,
  createAcct:   <?= json_encode(L('Konto erstellen', 'Create account')) ?>,
  register:     <?= json_encode(L('Registrieren', 'Sign up')) ?>,
  noAcct:       <?= json_encode(L('Noch kein Konto?', 'No account yet?')) ?>,
  haveAcct:     <?= json_encode(L('Schon ein Konto?', 'Already have an account?')) ?>,
  minChars:     <?= json_encode(L('mind. 12 Zeichen', 'at least 12 characters')) ?>,
  moment:       <?= json_encode(L('Moment…', 'One moment…')) ?>,
  sent:         <?= json_encode(L('Gesendet.', 'Sent.')) ?>,
  error:        <?= json_encode(L('Fehler.', 'Error.')) ?>,
  connErr:      <?= json_encode(L('Verbindungsfehler: ', 'Connection error: ')) ?>,
  success:      <?= json_encode(L('Erfolgreich — weiter…', 'Success — continuing…')) ?>,
  confirmCode:  <?= json_encode(L('Code bestätigen', 'Confirm code')) ?>,
  switchServer: <?= json_encode(L('Verbindung zu diesem Server trennen und einen anderen wählen?', 'Disconnect from this server and choose a different one?')) ?>,
};
let mode = 'login'; // 'login' | 'register'

const title = document.getElementById('formTitle');
const submitBtn = document.getElementById('submitBtn');
const switchLine = document.getElementById('switchLine');
const switchLink = document.getElementById('switchLink');
const pwInput = document.getElementById('password');
const msg = document.getElementById('msg');
const rememberChk = document.getElementById('rememberChk');
// In der nativen App standardmäßig angehakt (Komfort). Im Browser bewusst AUS
// (Opt-in): Das Dauer-Login-Cookie wird nur auf aktiven Wunsch gesetzt — so ist
// kein Einwilligungs-Banner nötig.
if (window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) {
  rememberChk.checked = true;
}

const rulesRow = document.getElementById('rulesRow');
const rulesChk = document.getElementById('rulesChk');

function setMode(m) {
  mode = m;
  // Serverregeln-Häkchen nur bei der Registrierung (dort Pflicht)
  rulesRow.style.display = (m === 'register') ? 'flex' : 'none';
  rulesChk.required = (m === 'register');
  if (m === 'login') {
    title.textContent = T.login;
    submitBtn.textContent = T.login;
    switchLine.innerHTML = T.noAcct + ' <a id="switchLink">' + T.register + '</a>';
    pwInput.autocomplete = 'current-password';
    // Login: Bestandskonten (8–11 Zeichen) nicht aussperren
    pwInput.minLength = 8;
    pwInput.placeholder = '';
  } else {
    title.textContent = T.createAcct;
    submitBtn.textContent = T.register;
    switchLine.innerHTML = T.haveAcct + ' <a id="switchLink">' + T.login + '</a>';
    pwInput.autocomplete = 'new-password';
    // Registrierung: neue Passwörter mind. 12 Zeichen
    pwInput.minLength = 12;
    pwInput.placeholder = T.minChars;
  }
  const sl = document.getElementById('switchLink');
  if (sl) sl.onclick = () => setMode(m === 'login' ? 'register' : 'login');
  msg.textContent = '';
  // "Passwort vergessen?" nur im Login-Modus anbieten
  const fl = document.getElementById('forgotLine');
  if (fl) fl.style.display = (m === 'login') ? '' : 'none';
  document.getElementById('forgotBox').style.display = 'none';
}
const CAN_REGISTER = <?= $canRegister ? 'true' : 'false' ?>;
const INVITE_TOKEN = <?= json_encode($inviteToken) ?>;
if (switchLink) switchLink.onclick = () => setMode('register');
// Mit gültigem Einladungslink direkt im Registrierungsmodus starten
if (CAN_REGISTER && INVITE_TOKEN) setMode('register');

let awaiting2fa = false;

// Nur in der nativen App mit Server-Picker: zurück zur Server-Auswahl.
// (Löscht die gespeicherte Instanz-Wahl auf dem Gerät und startet die
// App-Oberfläche neu — Konten/Daten auf dem Server bleiben unberührt.)
(function () {
  const Cap = window.Capacitor;
  const SC = Cap && Cap.isNativePlatform && Cap.isNativePlatform()
    && Cap.Plugins && Cap.Plugins.ServerConfig;
  if (!SC) return;
  document.getElementById('serverSwitchWrap').style.display = '';
  document.getElementById('serverSwitchLink').onclick = () => {
    if (confirm(T.switchServer)) {
      SC.clear();
    }
  };
})();

// ---- Passwort vergessen ----
document.getElementById('forgotLink').onclick = () => {
  const box = document.getElementById('forgotBox');
  box.style.display = box.style.display === 'none' ? 'block' : 'none';
  if (box.style.display === 'block') {
    const fe = document.getElementById('forgotEmail');
    fe.value = document.getElementById('email').value;
    fe.focus();
  }
};
document.getElementById('forgotSendBtn').onclick = async () => {
  const fm = document.getElementById('forgotMsg');
  const btn = document.getElementById('forgotSendBtn');
  fm.className = 'msg';
  fm.textContent = T.moment;
  btn.disabled = true;
  try {
    const res = await fetch('api/auth.php?action=forgotpassword', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ email: document.getElementById('forgotEmail').value, csrf: CSRF })
    });
    const data = await res.json().catch(() => ({}));
    fm.className = data.ok ? 'msg ok' : 'msg err';
    fm.textContent = data.ok ? (data.message || T.sent) : (data.error || T.error);
  } catch (err) {
    fm.className = 'msg err';
    fm.textContent = T.connErr + err.message;
  } finally {
    btn.disabled = false;
  }
};

document.getElementById('authForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  submitBtn.disabled = true;
  msg.className = 'msg';
  msg.textContent = T.moment;
  try {
    // Zweiter Schritt: nur den 2FA-Code schicken
    const action = awaiting2fa ? 'totp' : mode;
    const body = awaiting2fa
      ? { code: document.getElementById('totpCode').value.trim(), csrf: CSRF }
      : { email: document.getElementById('email').value, password: pwInput.value, csrf: CSRF,
          remember: rememberChk.checked,
          rules: (mode === 'register' ? rulesChk.checked : undefined),
          lang: (mode === 'register' ? LANG : undefined),
          invite: (mode === 'register' ? INVITE_TOKEN : undefined) };
    const res = await fetch('api/auth.php?action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(body)
    });
    const data = await res.json();
    if (data.ok && data.need2fa) {
      // Passwort ok, Konto hat 2FA → Code-Eingabe zeigen
      awaiting2fa = true;
      document.getElementById('totpRow').style.display = 'block';
      document.getElementById('rememberRow').style.display = 'none';
      document.getElementById('email').disabled = true;
      pwInput.disabled = true;
      submitBtn.textContent = T.confirmCode;
      msg.textContent = '';
      document.getElementById('totpCode').focus();
    } else if (data.ok) {
      msg.className = 'msg ok';
      msg.textContent = T.success;
      location.href = 'dashboard.php';
    } else {
      msg.className = 'msg err';
      msg.textContent = data.error || T.error;
    }
  } catch (err) {
    msg.className = 'msg err';
    msg.textContent = T.connErr + err.message;
  } finally {
    submitBtn.disabled = false;
  }
});
</script>
</body>
</html>
