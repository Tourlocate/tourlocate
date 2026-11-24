<?php
// App-weite Sprache (de/en) für die PHP-Seiten der User-Version. Ergänzt die
// JS-i18n (js/map-i18n.js, läuft über localStorage 'tl-lang'): Dashboard und
// Editor spiegeln die hier bestimmte Sprache beim Laden in localStorage, damit
// Karte/Editor dieselbe Sprache sprechen.
//
// Statt eines großen Schlüssel-Wörterbuchs werden die Übersetzungen inline an
// der Textstelle geführt: L('deutsch', 'english'). Bei dieser Seitenzahl ist
// das übersichtlicher und weniger fehleranfällig (Text + Übersetzung bleiben
// beisammen).
//
// Sprachbestimmung (erste Quelle gewinnt):
//   1. angemeldetes Konto  (users.lang)
//   2. Cookie              (tl_lang)
//   3. Browser             (Accept-Language, en → en, sonst de)
//   4. Standard            de

declare(strict_types=1);

function tl_lang_valid(string $l): bool
{
    return in_array($l, ['de', 'en', 'fr', 'es'], true);
}

// Anzeigenamen der Sprachen für den Umschalter.
function tl_lang_names(): array
{
    return ['de' => 'DE', 'en' => 'EN', 'fr' => 'FR', 'es' => 'ES'];
}

// Wörterbuch einer Zusatzsprache (fr/es) laden: deutscher Quelltext → Übersetzung.
// Datei app/api/lang/<lang>.php gibt ein Array zurück; fehlt sie, gilt ein
// leeres Array (→ L() fällt auf Deutsch zurück). Ergebnis wird gecacht.
function tl_translations(string $lang): array
{
    static $cache = [];
    if (array_key_exists($lang, $cache)) {
        return $cache[$lang];
    }
    $file = __DIR__ . '/lang/' . $lang . '.php';
    $data = is_file($file) ? require $file : [];
    return $cache[$lang] = is_array($data) ? $data : [];
}

function tl_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    // 1) Angemeldetes Konto
    $uid = current_user_id();
    if ($uid !== null) {
        try {
            $st = db()->prepare('SELECT lang FROM users WHERE id = ?');
            $st->execute([$uid]);
            $l = (string) ($st->fetchColumn() ?: '');
            if (tl_lang_valid($l)) {
                return $lang = $l;
            }
        } catch (Throwable $e) {
            // Spalte evtl. noch nicht migriert → weiter mit Cookie/Browser
        }
    }
    // 2) Cookie
    $c = (string) ($_COOKIE['tl_lang'] ?? '');
    if (tl_lang_valid($c)) {
        return $lang = $c;
    }
    // 3) Browser (grob: beginnt die Liste mit en/fr/es, dann diese Sprache)
    $al = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')));
    foreach (['en', 'fr', 'es'] as $cand) {
        if (strncmp($al, $cand, 2) === 0) {
            return $lang = $cand;
        }
    }
    // 4) Standard
    return $lang = 'de';
}

// Übersetzungshelfer: gibt je nach aktueller Sprache den passenden Text zurück.
// de/en stehen inline an der Textstelle; fr/es kommen aus dem Wörterbuch
// (app/api/lang/<lang>.php), nachgeschlagen über den DEUTSCHEN Quelltext als
// Schlüssel. Fehlt ein Eintrag, wird Deutsch gezeigt (sicherer Rückfall) —
// so lassen sich fr/es schrittweise ergänzen, ohne dass etwas bricht.
function L(string $de, string $en): string
{
    $lang = tl_lang();
    if ($lang === 'en') {
        return $en;
    }
    if ($lang === 'de') {
        return $de;
    }
    $tr = tl_translations($lang);
    return $tr[$de] ?? $de;
}

// Spracheinstellung im Cookie ablegen (1 Jahr). Nicht httponly, damit die
// JS-Seite notfalls nachziehen kann; die Sprache ist kein Geheimnis.
function tl_set_lang_cookie(string $lang): void
{
    if (!tl_lang_valid($lang)) {
        return;
    }
    setcookie('tl_lang', $lang, [
        'expires'  => time() + 31536000,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Lax',
        'secure'   => (bool) tl_config()['cookie_secure'],
    ]);
    $_COOKIE['tl_lang'] = $lang;
}

// Sprachumschaltung per ?lang=xx behandeln (für Seiten ohne eigenes Konto,
// z. B. die Login-Seite): Cookie setzen und per Redirect die Seite ohne den
// Parameter neu laden (PRG, damit die Caches von tl_lang() frisch greifen).
// Gibt true zurück, wenn umgeleitet wurde (Aufrufer sollte dann exit()).
function tl_handle_lang_switch(): bool
{
    $l = (string) ($_GET['lang'] ?? '');
    if (!tl_lang_valid($l)) {
        return false;
    }
    tl_set_lang_cookie($l);
    // Pfad + übrige Query-Parameter ohne lang (z. B. ?t=… bei view/verfolgen
    // bleibt erhalten, sonst wäre nach dem Umschalten der Share-Token weg).
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $path = strtok($uri, '?');
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);
    unset($q['lang']);
    $qs = http_build_query($q);
    header('Location: ' . $path . ($qs !== '' ? '?' . $qs : ''));
    return true;
}

// URL der aktuellen Seite mit gesetztem ?lang=<to> (übrige Parameter bleiben).
// Für den DE·EN-Umschalter auf Seiten ohne eigenes Konto.
function tl_lang_switch_url(string $to): string
{
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $path = strtok($uri, '?');
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);
    $q['lang'] = $to;
    return $path . '?' . http_build_query($q);
}

// Sprachumschalter als HTML (aktuelle Sprache fett, übrige als Link).
// Zwei Varianten, je nach Seitentyp:
//  - …_links():   öffentliche Seiten ohne Konto → Link mit ?lang=… (Cookie).
//  - …_setlang(): angemeldete Seiten → data-setlang (JS → users.lang). Braucht
//                 das data-setlang-JS auf der Seite (Dashboard/Admin haben es).
function tl_lang_switcher_links(): string
{
    $cur = tl_lang();
    $parts = [];
    foreach (tl_lang_names() as $code => $label) {
        $parts[] = $code === $cur
            ? '<strong>' . $label . '</strong>'
            : '<a href="' . htmlspecialchars(tl_lang_switch_url($code), ENT_QUOTES) . '">' . $label . '</a>';
    }
    return implode(' · ', $parts);
}

function tl_lang_switcher_setlang(): string
{
    $cur = tl_lang();
    $parts = [];
    foreach (tl_lang_names() as $code => $label) {
        $parts[] = $code === $cur
            ? '<strong>' . $label . '</strong>'
            : '<a href="#" data-setlang="' . $code . '">' . $label . '</a>';
    }
    return implode(' · ', $parts);
}
