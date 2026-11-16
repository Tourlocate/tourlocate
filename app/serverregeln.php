<?php
// Serverregeln (Nutzungsregeln dieser Instanz). Der Inhalt liegt – sobald im
// Admin-Bereich gepflegt – in app_settings (DB, persistentes Volume) und
// überlebt damit jeden Deploy. Ist nichts hinterlegt, gilt das Standard-
// Regelwerk unten (anders als beim Impressum KEIN TODO-Platzhalter: die
// Registrierung verlangt die Zustimmung zu dieser Seite, sie muss also
// immer einen verbindlichen Inhalt haben).
declare(strict_types=1);
$regeln = '';
try {
    require_once __DIR__ . '/api/bootstrap.php';
    $regeln = trim((string) tl_setting('serverregeln_html', ''));
} catch (Throwable $e) {
    $regeln = ''; // DB/Config nicht verfügbar → Standard-Regelwerk zeigen
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Serverregeln — Tourlocate</title>
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
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Serverregeln</h1>
<?php if ($regeln !== ''): ?>
    <?= $regeln /* vom Admin gepflegt (vertrauenswürdig) */ ?>
<?php else: ?>
    <p>Mit der Registrierung auf dieser Tourlocate-Instanz erklärst du dich mit den
    folgenden Regeln einverstanden. Sie dienen dem Schutz aller Nutzerinnen und
    Nutzer sowie des Betreibers.</p>

    <h2>1. Zweck des Dienstes</h2>
    <p>Dieser Dienst dient dem Erstellen und Teilen von Touren und Präsentationen
    mit Stationen, Fotos und Routen. Eine Nutzung zu anderen Zwecken
    (z.&nbsp;B. als allgemeiner Datei-Speicher) ist nicht vorgesehen.</p>

    <h2>2. Eigene Inhalte und Rechte Dritter</h2>
    <ul>
      <li>Lade nur Inhalte hoch, an denen du die erforderlichen Rechte besitzt
      (eigene Fotos und Texte oder ausdrücklich erlaubtes Material).</li>
      <li>Fotos, auf denen Personen erkennbar sind, dürfen nur mit deren
      Einwilligung veröffentlicht werden.</li>
      <li>Veröffentliche keine privaten Daten Dritter (Adressen, Wohnorte,
      Aufenthaltsorte) ohne deren Zustimmung.</li>
    </ul>

    <h2>3. Verbotene Inhalte</h2>
    <p>Nicht erlaubt sind insbesondere Inhalte, die rechtswidrig, beleidigend,
    diskriminierend, gewaltverherrlichend, jugendgefährdend oder irreführend
    sind, sowie Werbung und Spam.</p>

    <h2>4. Kein Missbrauch</h2>
    <p>Untersagt sind das Anlegen von Konten in großer Zahl, automatisierte
    Massenzugriffe, Versuche, den Dienst zu stören oder Sicherheitsmechanismen
    zu umgehen, sowie die Weitergabe von Zugangsdaten.</p>

    <h2>5. Live-Standort</h2>
    <p>Teile deinen Live-Standort nur bewusst und nur mit Personen, denen du
    vertraust. Teile niemals den Standort anderer Personen ohne deren Wissen
    und Einwilligung.</p>

    <h2>6. Durchsetzung</h2>
    <p>Bei Verstößen kann der Betreiber Inhalte entfernen sowie Konten sperren
    oder löschen — bei schweren Verstößen auch ohne Vorwarnung. Rechtsverletzende
    Inhalte können über den Link „Inhalt melden" auf der jeweiligen Seite oder
    über die im <a href="impressum.php">Impressum</a> genannte Adresse gemeldet
    werden.</p>

    <h2>7. Kein Anspruch auf Verfügbarkeit</h2>
    <p>Der Dienst wird ohne Gewähr für ständige Verfügbarkeit oder dauerhafte
    Speicherung betrieben. Bitte sichere wichtige Touren zusätzlich über die
    Export-Funktionen (GeoJSON, GPX, HTML/ZIP).</p>
<?php endif; ?>

    <p style="margin-top:22px"><a href="impressum.php">Impressum</a> ·
    <a href="datenschutz.php">Datenschutzerklärung</a> ·
    <a href="index.php">Zur Anmeldung</a></p>
  </div>
</div>
</body>
</html>
