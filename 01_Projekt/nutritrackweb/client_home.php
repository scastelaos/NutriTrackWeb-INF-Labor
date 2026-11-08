<?php
// client_home.php
// Startseite des FREIGEGEBENEN Kunden (die "Overview").
// Sie zeigt nur Informationen: die Knöpfe führen zu den anderen Seiten.

// BLOCK 1: Session starten und mit der Datenbank verbinden
// session_start() startet die Session (Sitzung; sessão). Der Server erkennt den Besucher an einer Nummer im Cookie wieder.
// Alles, was in $_SESSION steht (z. B. user_id), bleibt zwischen den Seiten gespeichert.
session_start();
require 'config.php';

// Höchstzeit ohne Aktivität: 30 Minuten (in Sekunden)
$timeout = 30 * 60;

// Sprache, die beim Login gewählt wurde (erlaubt sind nur en, de oder pt)
$lang = $_SESSION['lang'] ?? 'en';
if (!in_array($lang, ['en', 'de', 'pt'])) {
    $lang = 'en';
}

// BLOCK 2: der "Türsteher" (porteiro) der Seite: Wer darf hinein?

// 2a: man muss eingeloggt sein
if (!isset($_SESSION['user_id'])) {
    // header("Location: ...") schickt den Browser auf eine andere Seite (Redirect). Das exit danach stoppt das Skript, damit nichts mehr ausgeführt wird.
    header("Location: login.php?lang=" . $lang);
    exit;
}

// 2b: mehr als 30 Minuten ohne Aktivität? Dann wird die Session beendet
if (time() - ($_SESSION['last_activity'] ?? 0) > $timeout) {
    // Zeit abgelaufen oder nicht erlaubt: session_unset() leert alle Daten in $_SESSION, session_destroy() zerstört
    // die Session auf dem Server. Danach ist die Person nicht mehr eingeloggt (logout).
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1&lang=" . $lang);
    exit;
}

// 2c: noch innerhalb der Zeit: die Uhrzeit der letzten Aktivität wird erneuert
$_SESSION['last_activity'] = time();

// 2d: in der Datenbank prüfen, ob diese Person ein Kunde ist
// prepare() + execute() = Prepared Statement (vorbereitete Abfrage). Die ? sind Platzhalter; die Werte werden getrennt gesendet.
// So kann niemand SQL-Code einschleusen (Schutz gegen SQL-Injection).
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

if (!$me || $me['role'] != 0 ) {
    // Admin und andere: der Login weiß, wohin er jeden schickt
    header("Location: login.php?lang=" . $lang);
    exit;
}

// 2e: die Daten des Kunden holen (der approval_status kommt IMMER aus der Datenbank)
$stmt = $pdo->prepare(
    "SELECT client_id, first_name, last_name, profile_photo_path, approval_status
     FROM clients WHERE users_user_id = ?"
);
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch();

if (!$client) {
    header("Location: login.php?lang=" . $lang);
    exit;
}

// 2f: nur FREIGEGEBENE Kunden (1) bleiben hier; Pending, Rejected und MoreInfos gehen zur anderen Seite
if ($client['approval_status'] != 1) {
    if (in_array($client['approval_status'], [0, 2, 3])) {
        header("Location: client_status.php");
    } else {
        header("Location: login.php?lang=" . $lang);
    }
    exit;
}

$client_id = (int)$client['client_id'];

// Sprache über das Menü wechseln (?lang=en|de|pt): wird in der Session gespeichert und man kommt zur GLEICHEN Seite zurück,
// mit den anderen Teilen der Adresse (ohne ?lang)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'de', 'pt'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    $keep = $_GET;
    unset($keep['lang']);
    header("Location: " . basename($_SERVER['PHP_SELF']) . (count($keep) ? '?' . http_build_query($keep) : ''));
    exit;
}

// BLOCK 3: Texte der Seite, ein Satz Texte für jede Sprache
$texts = [
    'en' => [
        'page_title' => 'Home', 'your_nutri' => 'Your nutritionist: %s',
        'nav_home' => 'Home', 'nav_appointments' => 'Appointments', 'nav_messages' => 'Messages',
        'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos', 'nav_payments' => 'Payments',
        'nav_profile' => 'My Profile', 'logout' => 'Logout',
        'tab_overview' => 'Overview',
        'kpi_appt' => 'Next Appointment', 'kpi_reply' => 'New Reply', 'kpi_payment' => 'Payment', 'kpi_weight' => 'Current Weight',
        'due' => 'Due',
        'appt_title' => 'Your next appointment', 'appt_line' => '%1$s, %2$s with %3$s',
        'join_video' => 'Join video call', 'reschedule' => 'Request reschedule',
        'appt_empty' => 'You have no upcoming appointment.',
        'weight_title' => 'Weight and height', 'history' => 'History',
        'start_now' => 'Start %1$s · Now %2$s', 'height_line' => 'Height %s', 'last_update' => 'Last update: %s',
        'weight_empty' => 'No measurements yet.',
        'new_btn' => 'New', 'st_new' => 'New', 'st_answered' => 'Answered', 'st_closed' => 'Closed',
        'sent' => 'Sent %s', 'replied' => 'Replied %s', 'messages_empty' => 'No messages yet.',
        'upload_pdf' => 'Upload PDF', 'documents_empty' => 'No documents yet.',
        'doc1' => 'Blood Test', 'doc2' => 'Nutrition Plan', 'doc3' => 'Payment Proof', 'doc4' => 'Other',
        'add_photo' => 'Add photo', 'photos_empty' => 'No meal photos yet.',
        'meal1' => 'Breakfast', 'meal2' => 'Lunch', 'meal3' => 'Dinner', 'meal4' => 'Other',
        'today' => 'Today', 'tomorrow' => 'Tomorrow', 'yesterday' => 'Yesterday',
    ],
    'pt' => [
        'page_title' => 'Início', 'your_nutri' => 'Sua nutricionista: %s',
        'nav_home' => 'Início', 'nav_appointments' => 'Consultas', 'nav_messages' => 'Mensagens',
        'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições', 'nav_payments' => 'Pagamentos',
        'nav_profile' => 'Meu Perfil', 'logout' => 'Sair',
        'tab_overview' => 'Visão geral',
        'kpi_appt' => 'Próxima Consulta', 'kpi_reply' => 'Nova Resposta', 'kpi_payment' => 'Pagamento', 'kpi_weight' => 'Peso Atual',
        'due' => 'Vence',
        'appt_title' => 'Sua próxima consulta', 'appt_line' => '%1$s, %2$s com %3$s',
        'join_video' => 'Entrar na videochamada', 'reschedule' => 'Pedir reagendamento',
        'appt_empty' => 'Você não tem nenhuma consulta marcada.',
        'weight_title' => 'Peso e altura', 'history' => 'Histórico',
        'start_now' => 'Início %1$s · Agora %2$s', 'height_line' => 'Altura %s', 'last_update' => 'Última atualização: %s',
        'weight_empty' => 'Nenhuma medida registrada ainda.',
        'new_btn' => 'Nova', 'st_new' => 'Nova', 'st_answered' => 'Respondida', 'st_closed' => 'Fechada',
        'sent' => 'Enviada %s', 'replied' => 'Respondida %s', 'messages_empty' => 'Nenhuma mensagem ainda.',
        'upload_pdf' => 'Enviar PDF', 'documents_empty' => 'Nenhum documento ainda.',
        'doc1' => 'Exame de sangue', 'doc2' => 'Plano alimentar', 'doc3' => 'Comprovante de pagamento', 'doc4' => 'Outro',
        'add_photo' => 'Adicionar foto', 'photos_empty' => 'Ainda não há fotos de refeições.',
        'meal1' => 'Café da manhã', 'meal2' => 'Almoço', 'meal3' => 'Jantar', 'meal4' => 'Outro',
        'today' => 'Hoje', 'tomorrow' => 'Amanhã', 'yesterday' => 'Ontem',
    ],
    'de' => [
        'page_title' => 'Startseite', 'your_nutri' => 'Ihre Ernährungsberaterin: %s',
        'nav_home' => 'Startseite', 'nav_appointments' => 'Termine', 'nav_messages' => 'Nachrichten',
        'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos', 'nav_payments' => 'Zahlungen',
        'nav_profile' => 'Mein Profil', 'logout' => 'Abmelden',
        'tab_overview' => 'Übersicht',
        'kpi_appt' => 'Nächster Termin', 'kpi_reply' => 'Neue Antwort', 'kpi_payment' => 'Zahlung', 'kpi_weight' => 'Aktuelles Gewicht',
        'due' => 'Fällig',
        'appt_title' => 'Ihr nächster Termin', 'appt_line' => '%1$s, %2$s Uhr mit %3$s',
        'join_video' => 'Videoanruf beitreten', 'reschedule' => 'Umterminung anfragen',
        'appt_empty' => 'Sie haben keinen bevorstehenden Termin.',
        'weight_title' => 'Gewicht und Größe', 'history' => 'Verlauf',
        'start_now' => 'Start %1$s · Jetzt %2$s', 'height_line' => 'Größe %s', 'last_update' => 'Letzte Aktualisierung: %s',
        'weight_empty' => 'Noch keine Messwerte.',
        'new_btn' => 'Neu', 'st_new' => 'Neu', 'st_answered' => 'Beantwortet', 'st_closed' => 'Geschlossen',
        'sent' => 'Gesendet %s', 'replied' => 'Beantwortet %s', 'messages_empty' => 'Noch keine Nachrichten.',
        'upload_pdf' => 'PDF hochladen', 'documents_empty' => 'Noch keine Dokumente.',
        'doc1' => 'Bluttest', 'doc2' => 'Ernährungsplan', 'doc3' => 'Zahlungsbeleg', 'doc4' => 'Sonstiges',
        'add_photo' => 'Foto hinzufügen', 'photos_empty' => 'Noch keine Mahlzeitenfotos.',
        'meal1' => 'Frühstück', 'meal2' => 'Mittagessen', 'meal3' => 'Abendessen', 'meal4' => 'Sonstiges',
        'today' => 'Heute', 'tomorrow' => 'Morgen', 'yesterday' => 'Gestern',
    ],
];

// $t ist der Satz Texte der gewählten Sprache
$t = $texts[$lang];

// Namen von Monaten und Wochentagen in jeder Sprache (date('w'): 0 = Sonntag)
$months = [
    'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    'pt' => ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
    'de' => ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
];
$days_full = [
    'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    'pt' => ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'],
    'de' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
];

// BLOCK 4: kleine Funktionen, die beim Aufbau der Seite helfen
// (das Wort "global" erlaubt der Funktion, die oben erstellten Variablen zu benutzen)

// schützt jeden Text, bevor er auf dem Bildschirm erscheint (gegen XSS)
function e($text) {
    // htmlspecialchars() macht aus Zeichen wie < und > harmlosen Text. So kann niemand Schadcode (JavaScript) in die Seite einschleusen (XSS-Schutz).
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// zeigt ein Symbol (icon) aus dem Ordner icons (z. B. icon('phone')); "w" macht das Symbol weiß
function icon($name, $extra = '') {
    return '<img class="ico ' . $extra . '" src="icons/' . $name . '.svg" alt="">';
}

// kurzes Datum in der richtigen Sprache: "Oct 26" (EN), "26. Okt" (DE), "26 out" (PT)
function short_date($date) {
    global $lang, $months;
    $ts = strtotime($date);
    $d  = (int)date('j', $ts);
    $m  = $months[$lang][(int)date('n', $ts) - 1];

    if ($lang === 'en') {
        return $m . ' ' . $d;
    }
    if ($lang === 'de') {
        return $d . '. ' . $m;
    }
    return $d . ' ' . $m;
}

// "Heute", "Gestern" oder "12. Okt" (in der jeweiligen Sprache)
function friendly_day($datetime) {
    global $t;
    if (!$datetime) {
        return "";
    }
    $day = date('Y-m-d', strtotime($datetime));
    if ($day == date('Y-m-d')) {
        return $t['today'];
    }
    if ($day == date('Y-m-d', strtotime('-1 day'))) {
        return $t['yesterday'];
    }
    return short_date($datetime);
}

// Uhrzeit ohne Sekunden ("09:00:00" wird zu "09:00")
function fmt_time($time) {
    return $time ? substr($time, 0, 5) : "";
}

// nur Links, die mit http:// oder https:// anfangen (verhindert gefährliche Links)
function safe_url($url) {
    if ($url && preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return "";
}

// Mahlzeitentypen: [Name in der richtigen Sprache, Hintergrundfarbe, Textfarbe]
$meal_types = [
    1 => [$t['meal1'], '#FAC775', '#633806'],
    2 => [$t['meal2'], '#C0DD97', '#27500A'],
    3 => [$t['meal3'], '#F5C4B3', '#712B13'],
    4 => [$t['meal4'], '#E4E1D8', '#444441'],
];

// BLOCK 5: Daten der Ärztin (Name, Logo und die 4 Farben)
$profile = $pdo->query(
    "SELECT professional_name, logo_path,
            primary_color, secundary_color, background_color, accent_color
     FROM nutritionist_profile LIMIT 1"
)->fetch();

$name       = $profile['professional_name'] ?? "Nutritionist";
$logo       = $profile['logo_path'] ?? "";
$primary    = $profile['primary_color']      ?? "#2E7D32";
$secondary  = $profile['secundary_color']    ?? "#A5D6A7";
$background = $profile['background_color']   ?? "#F5F7F5";
$accent     = $profile['accent_color']       ?? "#FF9800";

// kurzer Name der Ärztin für die Sätze: "Dra. Sofia"
$short = "";
foreach (explode(" ", trim($name)) as $word) {
    if ($word === "") {
        continue;
    }
    $short .= ($short === "" ? "" : " ") . $word;
    if (strpos($word, ".") === false) {
        break;                            // hört beim ersten Wort ohne Punkt auf
    }
}

// Daten des Kunden für die Seitenleiste
$first      = $client['first_name'] ?? "";
$full_name  = trim($first . ' ' . ($client['last_name'] ?? ''));
$initials   = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($client['last_name'] ?? '', 0, 1));
$photo      = $client['profile_photo_path'] ?? "";

// BLOCK 6: ECHTE Daten dieses Kunden (wenn es nichts gibt, bleiben die Listen leer)

// 6a: nächster Termin (scheduled oder rescheduled, ab heute)
$stmt = $pdo->prepare(
    "SELECT appointment_date, appointment_time, meeting_url, notes
     FROM appointments
     WHERE clients_client_id = ? AND status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()
     ORDER BY appointment_date, appointment_time
     LIMIT 1"
);
$stmt->execute([$client_id]);
$next_appt = $stmt->fetch();

// 6b: wie viele Antworten der Ärztin (messages.status 1 = Answered)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE clients_client_id = ? AND status = 1");
$stmt->execute([$client_id]);
$count_replies = (int)$stmt->fetchColumn();

// 6c: nächste Fälligkeit (payments.status 0 = Open)
$stmt = $pdo->prepare(
    "SELECT due_date FROM payments
     WHERE clients_client_id = ? AND status = 0
     ORDER BY due_date ASC LIMIT 1"
);
$stmt->execute([$client_id]);
$next_due = $stmt->fetchColumn();

// 6d: Messwerte: die letzten 6 (für das Diagramm) und der allererste (der "Start")
// Es zählen nur GÜLTIGE Messwerte: mit ausgefülltem Gewicht (bei der Registrierung darf das Gewicht leer bleiben)
$stmt = $pdo->prepare(
    "SELECT weight, height, measurement_date FROM client_measurements
     WHERE clients_client_id = ? AND weight IS NOT NULL
     ORDER BY measurement_date DESC, measurement_id DESC LIMIT 6"
);
$stmt->execute([$client_id]);
$measures = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));   // vom ältesten zum neuesten (array_reverse dreht die Reihenfolge um)

$stmt = $pdo->prepare(
    "SELECT weight FROM client_measurements
     WHERE clients_client_id = ? AND weight IS NOT NULL
     ORDER BY measurement_date ASC, measurement_id ASC LIMIT 1"
);
$stmt->execute([$client_id]);
$first_weight = $stmt->fetchColumn();

// zuletzt angegebene Größe (kann in einem anderen Messwert stehen als das Gewicht)
$stmt = $pdo->prepare(
    "SELECT height FROM client_measurements
     WHERE clients_client_id = ? AND height IS NOT NULL
     ORDER BY measurement_date DESC, measurement_id DESC LIMIT 1"
);
$stmt->execute([$client_id]);
$last_height = $stmt->fetchColumn();

// 6e: letzte Nachrichten, Dokumente und Fotos
$stmt = $pdo->prepare(
    "SELECT subject, status, created_at, answered_at FROM messages
     WHERE clients_client_id = ? ORDER BY created_at DESC LIMIT 3"
);
$stmt->execute([$client_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT file_name, document_type, uploaded_at FROM documents
     WHERE clients_client_id = ? ORDER BY uploaded_at DESC LIMIT 3"
);
$stmt->execute([$client_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT photo_id, meal_type FROM meal_photos
     WHERE clients_client_id = ? ORDER BY uploaded_at DESC LIMIT 4"
);
$stmt->execute([$client_id]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// BLOCK 7: fertige Werte für die Anzeige

// die 4 Zahlen im Kopf ("-", wenn es keine Daten gibt)
$kpi_appt    = $next_appt ? short_date($next_appt['appointment_date']) . ', ' . fmt_time($next_appt['appointment_time']) : "-";
$kpi_payment = $next_due ? $t['due'] . ' ' . short_date($next_due) : "-";

$weights = [];
foreach ($measures as $m) {
    $weights[] = (float)$m['weight'];
}
$n = count($weights);

$last        = $n > 0 ? $measures[$n - 1] : null;
$kpi_weight  = $last ? number_format($last['weight'], 1, '.', '') . ' kg' : "-";

// Gewichtsdiagramm: berechnet die Punkte der Linie (nur mit 2 oder mehr Messwerten)
$points = "";
$dots   = [];
if ($n >= 2) {
    $w_min = min($weights);
    $w_max = max($weights);
    $range = $w_max - $w_min;

    foreach ($weights as $i => $w) {
        $x = 8 + $i * (204 / ($n - 1));
        // höheres Gewicht steht weiter oben; wenn alle gleich sind, steht es in der Mitte
        $y = ($range == 0) ? 35 : 10 + (($w_max - $w) / $range) * 50;
        $points .= number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '') . ' ';
        $dots[] = [number_format($x, 1, '.', ''), number_format($y, 1, '.', '')];
    }
}

// Seitenmenü: [Datei, Symbol, Text]
$current = "client_home.php";
$nav = [
    ["client_home.php",         "home",      $t['nav_home']],
    ["client_appointments.php", "calendar",  $t['nav_appointments']],
    ["client_messages.php",     "message",   $t['nav_messages']],
    ["client_documents.php",    "documents", $t['nav_documents']],
    ["client_meal_photos.php",  "camera",    $t['nav_photos']],
    ["client_payments.php",     "wallet",    $t['nav_payments']],
    ["client_profile.php",      "user",      $t['nav_profile']],
];

// Reiter: zeigen auf dieselben Seiten wie das Menü; "Overview" ist die aktuelle
$tabs = [
    ["client_home.php",         $t['tab_overview']],
    ["client_appointments.php", $t['nav_appointments']],
    ["client_messages.php",     $t['nav_messages']],
    ["client_documents.php",    $t['nav_documents']],
    ["client_meal_photos.php",  $t['nav_photos']],
    ["client_payments.php",     $t['nav_payments']],
    ["client_profile.php",      $t['nav_profile']],
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($t['page_title']) ?> - <?= e($name) ?></title>

    <style>
        /* Die 4 Farben kommen aus der Datenbank und werden zu CSS-Variablen */
        :root {
            --primary:    <?= e($primary) ?>;
            --secondary:  <?= e($secondary) ?>;
            --background: <?= e($background) ?>;
            --accent:     <?= e($accent) ?>;
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #2B2A24; background: var(--background); }
        a { color: inherit; text-decoration: none; }

        /* ICONS (Symbole): die Originalfarbe der Datei ist egal.
           brightness(0) macht das Symbol schwarz; mit opacity wird es dunkelgrau (heller Hintergrund).
           In der Seitenleiste und im Kopf (dunkler Hintergrund) macht invert(1) es weiß.
           Die Klasse "w" erzwingt ebenfalls weiß (für farbige Knöpfe). */
        .ico { width: 18px; height: 18px; vertical-align: middle; filter: brightness(0); opacity: .6; }
        .side .ico, .top .ico, .ico.w { filter: brightness(0) invert(1); opacity: .95; }
        .nav a.on .ico { filter: brightness(0); opacity: .75; }

        /* Aufbau der Seite: Seitenleiste + Kopf + Inhalt */
        .layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            grid-template-rows: 110px 1fr;
            min-height: 100vh;
        }

        /* SEITENLEISTE */
        .side {
            grid-row: 1 / 3;
            background: var(--primary);
            color: #fff;
            display: flex; flex-direction: column;
            padding: 20px 14px;
        }
        .logo { display: block; max-width: 100%; max-height: 48px; margin: 0 auto 16px; object-fit: contain; }
        .profile { text-align: center; padding-bottom: 16px; margin-bottom: 14px; border-bottom: 1px solid rgba(255,255,255,.25); }
        .photo {
            width: 76px; height: 76px; border-radius: 50%; margin: 0 auto 8px;
            background: rgba(255,255,255,.9); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: bold; overflow: hidden;
        }
        .photo img { width: 100%; height: 100%; object-fit: cover; }
        .profile .pname { font-size: 14px; font-weight: bold; }
        .profile .prole { font-size: 12px; opacity: .8; margin-top: 2px; }

        .nav a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; margin-bottom: 3px;
            border-radius: 8px; font-size: 14px; opacity: .92;
        }
        .nav a:hover { background: rgba(255,255,255,.15); }
        .nav a.on { background: var(--background); color: var(--primary); font-weight: bold; opacity: 1; }

        .spacer { flex: 1; min-height: 20px; }
        /* Sprachumschalter im Menü */
        .langs { display: flex; gap: 6px; padding: 12px 12px 4px; }
        .langs a { font-size: 12px; padding: 3px 9px; border-radius: 6px; border: 1px solid rgba(255,255,255,.55); color: #fff; opacity: .85; }
        .langs a.on { background: #fff; color: var(--primary); font-weight: bold; opacity: 1; border-color: #fff; }
        .logout {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 12px 10px; border-top: 1px solid rgba(255,255,255,.25);
            font-size: 14px;
        }
        .powered {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 11px; opacity: .85; padding-top: 6px;
        }

        /* KOPF MIT DEN 4 ZAHLEN */
        .top {
            background: var(--primary);
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
            padding: 18px 20px; align-items: stretch;
        }
        .top .ico { width: 22px; height: 22px; flex: none; }
        .kpi {
            background: rgba(255,255,255,.14); color: #fff;
            border-radius: 10px; padding: 10px 14px;
            display: flex; align-items: center; gap: 12px; min-width: 0;
        }
        .kpi.hl { background: var(--accent); }
        .kpi b { display: block; font-size: 18px; line-height: 1.15; }
        .kpi span { display: block; font-size: 12px; margin-top: 3px; }

        /* INHALT: Reiter + Panel */
        .main { padding: 20px; }
        .tabs { display: flex; gap: 4px; flex-wrap: wrap; position: relative; z-index: 1; }
        .tab {
            padding: 9px 16px; font-size: 14px; color: #555;
            background: rgba(0,0,0,.06); border: 1px solid #D8DDD8; border-bottom: none;
            border-radius: 8px 8px 0 0;
        }
        .tab:hover { background: rgba(0,0,0,.1); }
        .tab.on { background: #fff; color: var(--primary); font-weight: bold; border-color: var(--primary); margin-bottom: -1px; }
        .panel { background: #fff; border: 1px solid #D8DDD8; border-radius: 0 10px 10px 10px; padding: 18px; }

        .card { border: 1px solid #D8DDD8; border-radius: 10px; padding: 12px; background: #fff; min-width: 0; }
        .card.appt {
            background: linear-gradient(rgba(255,255,255,.8), rgba(255,255,255,.8)), var(--accent);
            border-color: var(--accent);
        }
        .ct { display: flex; align-items: center; gap: 7px; font-size: 14px; font-weight: bold; color: var(--primary); margin-bottom: 8px; }
        .ct .right { margin-left: auto; }
        .g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }

        /* nächster Termin */
        .appt-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .datebox { background: var(--accent); color: #fff; border-radius: 8px; padding: 6px 14px; text-align: center; flex: none; }
        .datebox small { display: block; font-size: 11px; }
        .datebox b { display: block; font-size: 24px; line-height: 1.1; }
        .appt-text { flex: 1; min-width: 160px; }
        .appt-text .at { font-size: 15px; font-weight: bold; }
        .appt-text .as { font-size: 13px; color: #555; margin-top: 2px; }
        .btns { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; padding: 7px 13px; border-radius: 8px;
            border: 1px solid var(--primary); color: var(--primary); background: #fff;
        }
        .btn.fill { background: var(--primary); color: #fff; }
        .btn.sm { font-size: 12px; padding: 4px 10px; }

        /* Listen */
        .li { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #E5E8E5; font-size: 13px; }
        .li:last-child { border-bottom: none; padding-bottom: 0; }
        .li.hl { background: linear-gradient(rgba(255,255,255,.75), rgba(255,255,255,.75)), var(--secondary); border-radius: 8px; padding: 8px; margin: 2px 0; }
        .tx { flex: 1; min-width: 0; }
        .nm { font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .nm.strong { font-weight: bold; }
        .sb { font-size: 12px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
        .empty { font-size: 13px; color: #888; padding: 6px 0; }
        .kv { display: flex; justify-content: space-between; gap: 8px; padding: 5px 0; font-size: 12px; color: #666; }

        .bd { font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 99px; flex: none; }
        .b-new { background: var(--accent); color: #fff; }
        .b-ans { background: #DDEBD2; color: #27500A; }
        .b-cls { background: #E4E1D8; color: #444441; }

        .big { font-size: 26px; font-weight: bold; color: var(--primary); }

        /* Mahlzeitenfotos: Vorschaubilder der echten Fotos (die Farbe des Typs erscheint, wenn das Foto nicht lädt) */
        .photos { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .ph {
            position: relative; display: flex; align-items: center; justify-content: center;
            height: 110px; border-radius: 8px; overflow: hidden;
        }
        .ph .ico { opacity: .55; width: 22px; height: 22px; }
        .ph img.pic { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .ph .cap {
            position: absolute; left: 0; right: 0; bottom: 0; padding: 14px 8px 5px;
            background: linear-gradient(to top, rgba(0,0,0,.7), rgba(0,0,0,0));
            color: #fff; font-size: 11px; font-weight: bold; line-height: 1.25;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ph:hover { opacity: .92; }

        /* kleine Bildschirme (Handy) */
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
            .side { grid-row: auto; }
            .top { grid-template-columns: 1fr 1fr; }
            .g2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">

    <!-- SEITENLEISTE -->
    <aside class="side">

        <!-- Logo der Ärztin (erscheint nur, wenn sie eins hochgeladen hat) -->
        <?php if ($logo !== ""): ?>
            <img class="logo" src="<?= e($logo) ?>" alt="<?= e($name) ?>">
        <?php endif; ?>

        <div class="profile">
            <div class="photo">
                <?php if ($photo !== ""): ?>
                    <img src="<?= e($photo) ?>" alt="<?= e($full_name) ?>">
                <?php else: ?>
                    <?= e($initials) ?>
                <?php endif; ?>
            </div>
            <div class="pname"><?= e($full_name) ?></div>
            <div class="prole"><?= e(sprintf($t['your_nutri'], $short)) ?></div>
        </div>

        <nav class="nav">
            <?php foreach ($nav as $item): ?>
                <a href="<?= e($item[0]) ?>" class="<?= $item[0] === $current ? 'on' : '' ?>">
                    <?= icon($item[1]) ?><?= e($item[2]) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="spacer"></div>

        <div class="langs">
            <?php foreach (['en' => 'EN', 'de' => 'DE', 'pt' => 'PT'] as $code => $label): ?>
                <a href="<?= e(basename($_SERVER['PHP_SELF']) . '?' . http_build_query(array_merge($_GET, ['lang' => $code]))) ?>" class="<?= $lang === $code ? 'on' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <a href="logout.php" class="logout"><?= icon('logout') ?><?= e($t['logout']) ?></a>

        <div class="powered">
            <svg width="14" height="14" viewBox="0 0 48 48" aria-hidden="true">
                <path d="M8 40 C8 19 23 7 42 7 C42 27 31 42 8 40 Z" fill="#ffffff"/>
                <path d="M8 40 L31 17" stroke="#2F5D3A" stroke-width="3" stroke-linecap="round" fill="none"/>
                <circle cx="39" cy="38" r="5" fill="#C9803A"/>
            </svg>
            Powered by <b>NutriTrackWeb</b>
        </div>
    </aside>

    <!-- KOPF MIT DEN 4 ZAHLEN -->
    <header class="top">
        <div class="kpi"><?= icon('calendar') ?><div><b><?= e($kpi_appt) ?></b><span><?= e($t['kpi_appt']) ?></span></div></div>
        <div class="kpi"><?= icon('mail') ?><div><b><?= $count_replies ?></b><span><?= e($t['kpi_reply']) ?></span></div></div>
        <div class="kpi"><?= icon('coin') ?><div><b><?= e($kpi_payment) ?></b><span><?= e($t['kpi_payment']) ?></span></div></div>
        <div class="kpi"><?= icon('scale') ?><div><b><?= e($kpi_weight) ?></b><span><?= e($t['kpi_weight']) ?></span></div></div>
    </header>

    <!-- INHALT -->
    <main class="main">

        <!-- Reiter (sie verlinken auf dieselben Seiten wie das Menü) -->
        <div class="tabs">
            <?php foreach ($tabs as $tab): ?>
                <a href="<?= e($tab[0]) ?>" class="tab <?= $tab[0] === $current ? 'on' : '' ?>"><?= e($tab[1]) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="panel">

            <!-- Nächster Termin -->
            <?php if ($next_appt): ?>
            <div class="card appt">
                    <?php
                        $ts   = strtotime($next_appt['appointment_date']);
                        $url  = safe_url($next_appt['meeting_url']);
                        $line = sprintf(
                            $t['appt_line'],
                            $days_full[$lang][(int)date('w', $ts)],
                            fmt_time($next_appt['appointment_time']),
                            $short
                        );
                    ?>
                    <div class="appt-row">
                        <div class="datebox">
                            <small><?= e(mb_strtoupper($months[$lang][(int)date('n', $ts) - 1])) ?></small>
                            <b><?= (int)date('j', $ts) ?></b>
                        </div>
                        <div class="appt-text">
                            <div class="at"><?= e($t['appt_title']) ?></div>
                            <div class="as"><?= e($line) ?></div>
                            <?php if (trim($next_appt['notes'] ?? '') !== ""): ?>
                                <div class="as"><?= e($next_appt['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="btns">
                            <?php if ($url !== ""): ?>
                                <a class="btn fill" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= icon('video', 'w') ?><?= e($t['join_video']) ?></a>
                            <?php endif; ?>
                            <!-- führt zur Terminseite -->
                            <a class="btn" href="client_appointments.php"><?= icon('calendar-time') ?><?= e($t['reschedule']) ?></a>
                        </div>
                    </div>
            </div>
            <?php endif; ?>

            <div class="g2">
                <!-- Gewicht und Größe -->
                <div class="card">
                    <div class="ct"><?= icon('chart') ?><?= e($t['weight_title']) ?> <a class="right btn sm" href="client_profile.php"><?= e($t['history']) ?></a></div>

                    <?php if ($n === 0): ?>
                        <div class="empty"><?= e($t['weight_empty']) ?></div>

                    <?php elseif ($n === 1): ?>
                        <div class="big"><?= e(number_format($last['weight'], 1, '.', '')) ?> kg</div>

                    <?php else: ?>
                        <svg viewBox="0 0 220 70" width="100%" height="70" role="img" aria-label="<?= e($t['weight_title']) ?>">
                            <line x1="0" y1="66" x2="220" y2="66" style="stroke:#E5E8E5" stroke-width="1"/>
                            <line x1="0" y1="35" x2="220" y2="35" style="stroke:#E5E8E5" stroke-width="1"/>
                            <polyline points="<?= e(trim($points)) ?>" fill="none" style="stroke:var(--primary)" stroke-width="2"/>
                            <?php foreach ($dots as $i => $d): ?>
                                <?php $is_last = ($i === count($dots) - 1); ?>
                                <circle cx="<?= $d[0] ?>" cy="<?= $d[1] ?>" r="<?= $is_last ? 4.5 : 3.5 ?>" style="fill:<?= $is_last ? 'var(--accent)' : 'var(--primary)' ?>"/>
                            <?php endforeach; ?>
                        </svg>
                    <?php endif; ?>

                    <?php if ($n > 0): ?>
                        <div class="kv">
                            <span><?= e(sprintf($t['start_now'], number_format($first_weight, 1, '.', '') . ' kg', number_format($last['weight'], 1, '.', '') . ' kg')) ?></span>
                        </div>
                        <div class="kv">
                            <span><?= $last_height !== false && $last_height !== null ? e(sprintf($t['height_line'], number_format($last_height, 0, '.', '') . ' cm')) : '' ?></span>
                            <span><?= e(sprintf($t['last_update'], short_date($last['measurement_date']))) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Nachrichten -->
                <div class="card">
                    <div class="ct"><?= icon('message') ?><?= e($t['nav_messages']) ?>
                        <a class="right btn fill sm" href="client_messages.php"><?= icon('plus', 'w') ?><?= e($t['new_btn']) ?></a>
                    </div>
                    <?php if (count($messages) === 0): ?>
                        <div class="empty"><?= e($t['messages_empty']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($messages as $m): ?>
                        <?php
                            // 0 = New, 1 = Answered, 2 = Closed
                            if ($m['status'] == 1) {
                                $badge = ['b-ans', $t['st_answered']];
                                $sub = sprintf($t['replied'], friendly_day($m['answered_at'] ?: $m['created_at']));
                            } elseif ($m['status'] == 2) {
                                $badge = ['b-cls', $t['st_closed']];
                                $sub = friendly_day($m['created_at']);
                            } else {
                                $badge = ['b-new', $t['st_new']];
                                $sub = sprintf($t['sent'], friendly_day($m['created_at']));
                            }
                        ?>
                        <div class="li">
                            <div class="tx">
                                <div class="nm strong"><?= e($m['subject']) ?></div>
                                <div class="sb"><?= e($sub) ?></div>
                            </div>
                            <span class="bd <?= $badge[0] ?>"><?= e($badge[1]) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="g2">
                <!-- Dokumente (nur Name, Typ und Datum) -->
                <div class="card">
                    <div class="ct"><?= icon('documents') ?><?= e($t['nav_documents']) ?>
                        <a class="right btn sm" href="client_documents.php"><?= icon('upload-arrow') ?><?= e($t['upload_pdf']) ?></a>
                    </div>
                    <?php if (count($documents) === 0): ?>
                        <div class="empty"><?= e($t['documents_empty']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($documents as $d): ?>
                        <?php $type_label = $t['doc' . (int)$d['document_type']] ?? $t['doc4']; ?>
                        <!-- der Ernährungsplan (Typ 2) wird hervorgehoben -->
                        <div class="li <?= (int)$d['document_type'] === 2 ? 'hl' : '' ?>">
                            <?= icon('documents') ?>
                            <div class="tx">
                                <div class="nm strong"><?= e($d['file_name']) ?></div>
                                <div class="sb"><?= e($type_label) ?> · <?= e(friendly_day($d['uploaded_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Mahlzeitenfotos -->
                <div class="card">
                    <div class="ct"><?= icon('camera') ?><?= e($t['nav_photos']) ?>
                        <a class="right btn sm" href="client_meal_photos.php"><?= icon('upload-arrow') ?><?= e($t['add_photo']) ?></a>
                    </div>
                    <?php if (count($photos) === 0): ?>
                        <div class="empty"><?= e($t['photos_empty']) ?></div>
                    <?php else: ?>
                        <div class="photos">
                            <?php foreach ($photos as $p): ?>
                                <?php $type = $meal_types[$p['meal_type']] ?? $meal_types[4]; ?>
                                <a class="ph" href="client_meal_photos.php" style="background:<?= e($type[1]) ?>;color:<?= e($type[2]) ?>">
                                    <?= icon('image') ?>
                                    <img class="pic" src="view_image.php?id=<?= (int)$p['photo_id'] ?>" alt="" loading="lazy" onerror="this.remove()">
                                    <span class="cap"><?= e($type[0]) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>
