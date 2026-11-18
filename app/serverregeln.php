<?php
// Serverregeln (Nutzungsregeln dieser Instanz). Der Inhalt liegt – sobald im
// Admin-Bereich gepflegt – in app_settings (DB, persistentes Volume) und
// überlebt damit jeden Deploy. Ist nichts hinterlegt, gilt das Standard-
// Regelwerk unten (anders als beim Impressum KEIN TODO-Platzhalter: die
// Registrierung verlangt die Zustimmung zu dieser Seite, sie muss also
// immer einen verbindlichen Inhalt haben).
//
// Zweisprachig (de/en) über L(). Hinweis: Ein vom Admin gepflegter Text wird
// unverändert (in seiner Sprache) ausgegeben; das Standard-Regelwerk gibt es
// in beiden Sprachen. Rechtlich maßgeblich ist die deutsche Fassung — die
// englische Fassung ist nur eine informelle Übersetzung (Hinweis unten).
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';

// DE·EN-Umschaltung (?lang=…)
if (tl_handle_lang_switch()) {
    exit;
}

$regeln = '';
try {
    $regeln = trim((string) tl_setting('serverregeln_html', ''));
} catch (Throwable $e) {
    $regeln = ''; // DB/Config nicht verfügbar → Standard-Regelwerk zeigen
}
?>
<!DOCTYPE html>
<html lang="<?= tl_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= L('Serverregeln', 'Server rules') ?> — Tourlocate</title>
<link rel="icon" type="image/png" href="../img/logo.png">
<style>
  body{margin:0;font-family:system-ui,sans-serif;background:#f4f6f8;color:#1a1a1a}
  .wrap{max-width:720px;margin:32px auto;padding:0 16px 40px}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:24px 26px}
  h1{font-size:20px;margin:0 0 16px}
  h2{font-size:15px;margin:20px 0 6px}
  p, li{font-size:14px;line-height:1.55;margin:6px 0}
  ul{margin:6px 0;padding-left:22px}
  a{color:#2b6cb0}
  .topbar{display:flex;align-items:center;justify-content:space-between;margin:0 0 6px}
  .note{font-size:12px;color:#777;font-style:italic;margin:0 0 14px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="topbar">
      <h1 style="margin:0"><?= L('Serverregeln', 'Server rules') ?></h1>
      <a href="<?= htmlspecialchars(tl_lang_switch_url(tl_lang() === 'de' ? 'en' : 'de'), ENT_QUOTES) ?>" style="font-size:13px;color:#888;text-decoration:none"><?= tl_lang() === 'de' ? 'EN' : 'DE' ?></a>
    </div>
<?php if (tl_lang() === 'en'): ?>
    <p class="note">This English text is an informal translation. The legally binding version of these rules is the German one.</p>
<?php endif; ?>
<?php if ($regeln !== ''): ?>
    <?= $regeln /* vom Admin gepflegt (vertrauenswürdig) */ ?>
<?php else: ?>
    <p><?= L(
      'Mit der Registrierung auf dieser Tourlocate-Instanz erklärst du dich mit den folgenden Regeln einverstanden. Sie dienen dem Schutz aller Nutzerinnen und Nutzer sowie des Betreibers.',
      'By registering on this Tourlocate instance you agree to the following rules. They serve to protect all users as well as the operator.') ?></p>

    <h2>1. <?= L('Zweck des Dienstes', 'Purpose of the service') ?></h2>
    <p><?= L(
      'Dieser Dienst dient dem Erstellen und Teilen von Touren und Präsentationen mit Stationen, Fotos und Routen. Eine Nutzung zu anderen Zwecken (z.&nbsp;B. als allgemeiner Datei-Speicher) ist nicht vorgesehen.',
      'This service is for creating and sharing tours and presentations with stops, photos and routes. Use for other purposes (e.g. as general file storage) is not intended.') ?></p>

    <h2>2. <?= L('Eigene Inhalte und Rechte Dritter', 'Your content and third-party rights') ?></h2>
    <ul>
      <li><?= L(
        'Lade nur Inhalte hoch, an denen du die erforderlichen Rechte besitzt (eigene Fotos und Texte oder ausdrücklich erlaubtes Material).',
        'Only upload content for which you hold the necessary rights (your own photos and texts or expressly permitted material).') ?></li>
      <li><?= L(
        'Fotos, auf denen Personen erkennbar sind, dürfen nur mit deren Einwilligung veröffentlicht werden.',
        'Photos in which people are identifiable may only be published with their consent.') ?></li>
      <li><?= L(
        'Veröffentliche keine privaten Daten Dritter (Adressen, Wohnorte, Aufenthaltsorte) ohne deren Zustimmung.',
        'Do not publish private data of third parties (addresses, places of residence, whereabouts) without their consent.') ?></li>
    </ul>

    <h2>3. <?= L('Verbotene Inhalte', 'Prohibited content') ?></h2>
    <p><?= L(
      'Nicht erlaubt sind insbesondere Inhalte, die rechtswidrig, beleidigend, diskriminierend, gewaltverherrlichend, jugendgefährdend oder irreführend sind, sowie Werbung und Spam.',
      'In particular, content that is unlawful, offensive, discriminatory, glorifies violence, is harmful to minors or misleading, as well as advertising and spam, is not permitted.') ?></p>

    <h2>4. <?= L('Kein Missbrauch', 'No abuse') ?></h2>
    <p><?= L(
      'Untersagt sind das Anlegen von Konten in großer Zahl, automatisierte Massenzugriffe, Versuche, den Dienst zu stören oder Sicherheitsmechanismen zu umgehen, sowie die Weitergabe von Zugangsdaten.',
      'Creating accounts in large numbers, automated mass access, attempts to disrupt the service or circumvent security mechanisms, as well as sharing access credentials, are prohibited.') ?></p>

    <h2>5. <?= L('Live-Standort', 'Live location') ?></h2>
    <p><?= L(
      'Teile deinen Live-Standort nur bewusst und nur mit Personen, denen du vertraust. Teile niemals den Standort anderer Personen ohne deren Wissen und Einwilligung.',
      'Share your live location only deliberately and only with people you trust. Never share other people’s location without their knowledge and consent.') ?></p>

    <h2>6. <?= L('Durchsetzung', 'Enforcement') ?></h2>
    <p><?= L(
      'Bei Verstößen kann der Betreiber Inhalte entfernen sowie Konten sperren oder löschen — bei schweren Verstößen auch ohne Vorwarnung. Rechtsverletzende Inhalte können über den Link „Inhalt melden" auf der jeweiligen Seite oder über die im <a href="impressum.php">Impressum</a> genannte Adresse gemeldet werden.',
      'In the event of violations, the operator may remove content and block or delete accounts — in the case of serious violations also without prior warning. Infringing content can be reported via the “Report content” link on the respective page or via the address given in the <a href="impressum.php">imprint</a>.') ?></p>

    <h2>7. <?= L('Kein Anspruch auf Verfügbarkeit', 'No claim to availability') ?></h2>
    <p><?= L(
      'Der Dienst wird ohne Gewähr für ständige Verfügbarkeit oder dauerhafte Speicherung betrieben. Bitte sichere wichtige Touren zusätzlich über die Export-Funktionen (GeoJSON, GPX, HTML/ZIP).',
      'The service is operated without any guarantee of permanent availability or lasting storage. Please additionally back up important tours using the export functions (GeoJSON, GPX, HTML/ZIP).') ?></p>
<?php endif; ?>

    <p style="margin-top:22px"><a href="impressum.php"><?= L('Impressum', 'Imprint') ?></a> ·
    <a href="datenschutz.php"><?= L('Datenschutzerklärung', 'Privacy policy') ?></a> ·
    <a href="index.php"><?= L('Zur Anmeldung', 'To sign-in') ?></a></p>
  </div>
</div>
</body>
</html>
