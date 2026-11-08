<?php
// download.php?id=NUMMER_DES_DOKUMENTS
// Der einzige Eingang (Tür), um ein PDF aus dem Ordner uploads/documents/ zu öffnen. Der Ordner selbst ist durch .htaccess gesperrt.
// Wer darf das PDF öffnen:
//  - die Ernährungsberaterin (aktive Admin): jedes Dokument
//  - der Kunde: NUR seine eigenen Dokumente
// Alle anderen bekommen "404" (nicht gefunden). So erfährt niemand, ob ein Dokument überhaupt existiert.
// Normalerweise öffnet sich das PDF im Browser; mit &download=1 wird es als Datei heruntergeladen.

// BLOCK 1: Session starten und mit der Datenbank verbinden
// session_start() startet die Session (Sitzung; sessão). Der Server erkennt den Besucher an einer Nummer im Cookie wieder.
// Alles, was in $_SESSION steht (z. B. user_id), bleibt zwischen den Seiten gespeichert.
session_start();
require 'config.php';

$timeout = 30 * 60;   // 30 Minuten ohne Aktivität (in Sekunden)

// Einfache Antwort "nicht gefunden" (wird für alles benutzt, was nicht ausgeliefert werden darf)
function not_found() {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found";
    exit;
}

// BLOCK 2: der "Türsteher" (porteiro): man muss eingeloggt sein und innerhalb der Zeit bleiben
if (!isset($_SESSION['user_id'])) {
    $lang = in_array($_SESSION['lang'] ?? '', ['en', 'de', 'pt']) ? $_SESSION['lang'] : 'en';
    // header("Location: ...") schickt den Browser auf eine andere Seite (Redirect). Das exit danach stoppt das Skript, damit nichts mehr ausgeführt wird.
    header("Location: login.php?lang=" . $lang);
    exit;
}
if (time() - ($_SESSION['last_activity'] ?? 0) > $timeout) {
    $lang = in_array($_SESSION['lang'] ?? '', ['en', 'de', 'pt']) ? $_SESSION['lang'] : 'en';
    // Zeit abgelaufen oder nicht erlaubt: session_unset() leert alle Daten in $_SESSION, session_destroy() zerstört
    // die Session auf dem Server. Danach ist die Person nicht mehr eingeloggt (logout).
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1&lang=" . $lang);
    exit;
}
$_SESSION['last_activity'] = time();

// Wer ist diese Person? (die role kommt IMMER aus der Datenbank, nie vom Browser)
// prepare() + execute() = Prepared Statement (vorbereitete Abfrage). Die ? sind Platzhalter; die Werte werden getrennt gesendet.
// So kann niemand SQL-Code einschleusen (Schutz gegen SQL-Injection).
$stmt = $pdo->prepare("SELECT role, status FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();
if (!$me) {
    not_found();
}

$is_admin = ($me['role'] == 1 && $me['status'] == 1);

$my_client_id = 0;
if (!$is_admin) {
    if ($me['role'] != 0) {
        not_found();
    }
    // Kunde: muss Pending (0), Approved (1) oder MoreInfos (3) sein. Rejected (2) darf nichts öffnen.
    $stmt = $pdo->prepare("SELECT client_id, approval_status FROM clients WHERE users_user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $c = $stmt->fetch();
    if (!$c || !in_array((int)$c['approval_status'], [0, 1, 3], true)) {
        not_found();
    }
    $my_client_id = (int)$c['client_id'];
}

// BLOCK 3: Welches Dokument? (?id=NUMMER)
// filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
$doc_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if ($doc_id === false || $doc_id < 1) {
    not_found();
}

$stmt = $pdo->prepare("SELECT file_name, file_path, clients_client_id FROM documents WHERE document_id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

// Das Dokument existiert nicht, oder der Kunde versucht, das Dokument einer anderen Person zu öffnen
if (!$doc || (!$is_admin && (int)$doc['clients_client_id'] !== $my_client_id)) {
    not_found();
}

// BLOCK 4: Die Datei auf der Festplatte finden. Es wird NUR der Dateiname (basename) mit dem festen Ordner benutzt,
// so kann ein komischer Pfad aus der Datenbank nie aus dem Dokumentenordner herausführen.
$dir  = realpath(__DIR__ . '/uploads/documents');
$file = $dir ? realpath($dir . DIRECTORY_SEPARATOR . basename((string)$doc['file_path'])) : false;

if ($file === false || strpos($file, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    not_found();
}

// BLOCK 5: Das PDF ausliefern
$shown_name = preg_replace('/[^\w.\- ]+/u', '_', (string)$doc['file_name']);   // sicherer Name für den Header (Sonderzeichen werden zu _)
if ($shown_name === '' || $shown_name === null) { $shown_name = 'document.pdf'; }
if (strtolower(substr($shown_name, -4)) !== '.pdf') { $shown_name .= '.pdf'; }

$disposition = isset($_GET['download']) ? 'attachment' : 'inline';

session_write_close();    // Session freigeben, bevor die Datei gesendet wird (so blockiert sie keine anderen Seiten)

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: ' . $disposition . '; filename="' . $shown_name . '"; filename*=UTF-8\'\'' . rawurlencode($shown_name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file);
exit;
