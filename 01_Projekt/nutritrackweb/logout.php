<?php
// logout.php
// Beendet die Session (Sitzung; sessão) und geht zurück zur öffentlichen Seite der Ernährungsberaterin (index.php). Die Datei hat keine eigene Anzeige.

// BLOCK 1: Die aktuelle Session öffnen (nur so kann man sie auch löschen)
// session_start() startet die Session (Sitzung; sessão). Der Server erkennt den Besucher an einer Nummer im Cookie wieder.
// Alles, was in $_SESSION steht (z. B. user_id), bleibt zwischen den Seiten gespeichert.
session_start();

// BLOCK 2: Die Sprache merken, bevor alles gelöscht wird
// (erlaubt sind nur en, de oder pt; alles andere wird zu en)
$lang = $_SESSION['lang'] ?? 'en';
if (!in_array($lang, ['en', 'de', 'pt'])) {
    $lang = 'en';
}

// BLOCK 3: Alle Daten der Session leeren (user_id, role, last_activity ...)
$_SESSION = [];

// BLOCK 4: Das Cookie löschen, in dem der Browser die Session-Nummer speichert
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}

// BLOCK 5: Die Session auf dem Server zerstören (destroy): danach ist niemand mehr eingeloggt
// session_destroy() zerstört die Session auf dem Server: danach ist die Person nicht mehr eingeloggt.
session_destroy();

// BLOCK 6: Zurück zur öffentlichen Seite, in derselben Sprache
// header("Location: ...") schickt den Browser auf eine andere Seite (Redirect). Das exit danach stoppt das Skript, damit nichts mehr ausgeführt wird.
header("Location: index.php?lang=" . $lang);
exit;
