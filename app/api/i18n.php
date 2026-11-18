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
    return $l === 'de' || $l === 'en';
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
    // 3) Browser (grob: beginnt die Liste mit "en", dann Englisch)
    $al = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')));
    if (strncmp($al, 'en', 2) === 0) {
        return $lang = 'en';
    }
    // 4) Standard
    return $lang = 'de';
}

// Übersetzungshelfer: gibt je nach aktueller Sprache den passenden Text zurück.
function L(string $de, string $en): string
{
    return tl_lang() === 'en' ? $en : $de;
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
    $url = strtok($_SERVER['REQUEST_URI'] ?? '', '?'); // Pfad ohne Query
    header('Location: ' . $url);
    return true;
}
