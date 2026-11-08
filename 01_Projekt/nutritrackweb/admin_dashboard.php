<?php
// admin_dashboard.php
// Startseite der Ärztin (Admin). Nur wer Admin ist und eingeloggt ist, kommt hier hinein.
// Die Sprache kommt vom Login ($_SESSION['lang']); im Menü kann man sie wechseln (EN/DE/PT).

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

// 2d: in der Datenbank prüfen, ob diese Person wirklich Admin und aktiv ist
// (wir vertrauen nicht nur dem, was in der Session steht)
// prepare() + execute() = Prepared Statement (vorbereitete Abfrage). Die ? sind Platzhalter; die Werte werden getrennt gesendet.
// So kann niemand SQL-Code einschleusen (Schutz gegen SQL-Injection).
$stmt = $pdo->prepare("SELECT role, status FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

if (!$me || $me['role'] != 1 || $me['status'] != 1) {
    // der Login weiß, wohin jede Art von Person geschickt wird
    header("Location: login.php?lang=" . $lang);
    exit;
}

// Sicherheits-Token für die Knöpfe Genehmigen/Ablehnen (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sprache über das Menü wechseln (?lang=en|de|pt): wird in der Session gespeichert und man kommt zur GLEICHEN Seite zurück,
// mit den anderen Filtern aus der Adresse (aber ohne ?lang)
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
        'greet_morning' => 'Good Morning', 'greet_afternoon' => 'Good Afternoon', 'greet_evening' => 'Good Evening',
        'role' => 'Nutritionist',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clients', 'nav_appointments' => 'Appointments',
        'nav_messages' => 'Messages', 'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos',
        'nav_payments' => 'Payments', 'nav_settings' => 'Profile Settings', 'logout' => 'Logout',
        'kpi_messages' => 'New Messages', 'kpi_appointments' => 'Upcoming Appointments',
        'kpi_payments' => 'Open Payments', 'kpi_pending' => 'Pending Approvals',
        'card_requests' => 'Reschedule Requests', 'card_photos' => 'Recent Meal Photos',
        'view_all' => 'View all', 'review' => 'Review',
        'approve' => 'Approve', 'decline' => 'Decline',
        'approve_confirm' => 'Approve this request? The appointment will move to the new date and time.',
        'reject_confirm' => 'Reject this request? The appointment stays as it is.',
        'done_approved' => 'Request approved. The appointment was moved to the new date and time.',
        'done_rejected' => 'Request rejected. The appointment stays as it was.',
        'warn_conflict' => 'Warning: there is already another appointment at this time.',
        'err_done' => 'This item was already handled or is no longer available.', 'join_video' => 'Join video call',
        'due' => 'Due', 'today' => 'Today', 'tomorrow' => 'Tomorrow', 'yesterday' => 'Yesterday',
        'st_scheduled' => 'Scheduled', 'st_rescheduled' => 'Rescheduled', 'badge_new' => 'New',
        'empty_appointments' => 'No upcoming appointments.', 'empty_pending' => 'No pending approvals.',
        'empty_requests' => 'No reschedule requests.', 'empty_messages' => 'No new messages.',
        'empty_payments' => 'No open payments.', 'empty_photos' => 'No meal photos yet.',
        'meal1' => 'Breakfast', 'meal2' => 'Lunch', 'meal3' => 'Dinner', 'meal4' => 'Other',
    ],
    'pt' => [
        'greet_morning' => 'Bom dia', 'greet_afternoon' => 'Boa tarde', 'greet_evening' => 'Boa noite',
        'role' => 'Nutricionista',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clientes', 'nav_appointments' => 'Consultas',
        'nav_messages' => 'Mensagens', 'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições',
        'nav_payments' => 'Pagamentos', 'nav_settings' => 'Configurações do Perfil', 'logout' => 'Sair',
        'kpi_messages' => 'Novas Mensagens', 'kpi_appointments' => 'Próximas Consultas',
        'kpi_payments' => 'Pagamentos em Aberto', 'kpi_pending' => 'Aprovações Pendentes',
        'card_requests' => 'Pedidos de Reagendamento', 'card_photos' => 'Fotos de Refeições Recentes',
        'view_all' => 'Ver todos', 'review' => 'Revisar',
        'approve' => 'Aprovar', 'decline' => 'Recusar',
        'approve_confirm' => 'Aprovar este pedido? A consulta será movida para a nova data e horário.',
        'reject_confirm' => 'Recusar este pedido? A consulta continua como está.',
        'done_approved' => 'Pedido aprovado. A consulta foi movida para a nova data e horário.',
        'done_rejected' => 'Pedido recusado. A consulta continua como estava.',
        'warn_conflict' => 'Atenção: já existe outra consulta neste horário.',
        'err_done' => 'Este item já foi tratado ou não está mais disponível.', 'join_video' => 'Entrar na videochamada',
        'due' => 'Vence', 'today' => 'Hoje', 'tomorrow' => 'Amanhã', 'yesterday' => 'Ontem',
        'st_scheduled' => 'Agendada', 'st_rescheduled' => 'Reagendada', 'badge_new' => 'Nova',
        'empty_appointments' => 'Nenhuma consulta futura.', 'empty_pending' => 'Nenhuma aprovação pendente.',
        'empty_requests' => 'Nenhum pedido de reagendamento.', 'empty_messages' => 'Nenhuma mensagem nova.',
        'empty_payments' => 'Nenhum pagamento em aberto.', 'empty_photos' => 'Ainda não há fotos de refeições.',
        'meal1' => 'Café da manhã', 'meal2' => 'Almoço', 'meal3' => 'Jantar', 'meal4' => 'Outro',
    ],
    'de' => [
        'greet_morning' => 'Guten Morgen', 'greet_afternoon' => 'Guten Tag', 'greet_evening' => 'Guten Abend',
        'role' => 'Ernährungsberaterin',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Kunden', 'nav_appointments' => 'Termine',
        'nav_messages' => 'Nachrichten', 'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos',
        'nav_payments' => 'Zahlungen', 'nav_settings' => 'Profileinstellungen', 'logout' => 'Abmelden',
        'kpi_messages' => 'Neue Nachrichten', 'kpi_appointments' => 'Bevorstehende Termine',
        'kpi_payments' => 'Offene Zahlungen', 'kpi_pending' => 'Ausstehende Freigaben',
        'card_requests' => 'Umterminungsanfragen', 'card_photos' => 'Aktuelle Mahlzeitenfotos',
        'view_all' => 'Alle anzeigen', 'review' => 'Prüfen',
        'approve' => 'Genehmigen', 'decline' => 'Ablehnen',
        'approve_confirm' => 'Diese Anfrage genehmigen? Der Termin wird auf das neue Datum und die neue Uhrzeit verschoben.',
        'reject_confirm' => 'Diese Anfrage ablehnen? Der Termin bleibt unverändert.',
        'done_approved' => 'Anfrage genehmigt. Der Termin wurde auf das neue Datum und die neue Uhrzeit verschoben.',
        'done_rejected' => 'Anfrage abgelehnt. Der Termin bleibt unverändert.',
        'warn_conflict' => 'Achtung: Zu dieser Zeit gibt es bereits einen anderen Termin.',
        'err_done' => 'Dieser Eintrag wurde bereits bearbeitet oder ist nicht mehr verfügbar.', 'join_video' => 'Videoanruf beitreten',
        'due' => 'Fällig', 'today' => 'Heute', 'tomorrow' => 'Morgen', 'yesterday' => 'Gestern',
        'st_scheduled' => 'Geplant', 'st_rescheduled' => 'Verschoben', 'badge_new' => 'Neu',
        'empty_appointments' => 'Keine bevorstehenden Termine.', 'empty_pending' => 'Keine ausstehenden Freigaben.',
        'empty_requests' => 'Keine Umterminungsanfragen.', 'empty_messages' => 'Keine neuen Nachrichten.',
        'empty_payments' => 'Keine offenen Zahlungen.', 'empty_photos' => 'Noch keine Mahlzeitenfotos.',
        'meal1' => 'Frühstück', 'meal2' => 'Mittagessen', 'meal3' => 'Abendessen', 'meal4' => 'Sonstiges',
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
$days_short = [
    'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    'pt' => ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'],
    'de' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
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

// zeigt ein Symbol (icon) aus dem Ordner icons (z. B. icon('mail'))
function icon($name) {
    return '<img class="ico" src="icons/' . $name . '.svg" alt="">';
}

// vollständiger Name des Kunden
function full_name($row) {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

// Initialen des Kunden ("Ana Beatriz Lima" wird zu "AL")
function client_initials($row) {
    return mb_strtoupper(mb_substr($row['first_name'] ?? '', 0, 1) . mb_substr($row['last_name'] ?? '', 0, 1));
}

// kleiner Kreis mit den Initialen; die Farbe ändert sich je nach Nummer des Kunden
function avatar($row) {
    $colors = [
        ['#F2C9A0', '#5B3A12'], ['#C0DD97', '#27500A'], ['#F4C0D1', '#72243E'], ['#B5D4F4', '#0C447C'],
        ['#FAC775', '#633806'], ['#CECBF6', '#3C3489'], ['#9FE1CB', '#085041'], ['#F5C4B3', '#712B13'],
    ];
    // wenn die Kundin ein Profilfoto hat, wird das Foto gezeigt; sonst die farbigen Initialen
    $photo = (string)($row['profile_photo_path'] ?? '');
    if ($photo !== '' && preg_match('#^uploads/profile/[A-Za-z0-9._-]+$#', $photo)) {
        return '<div class="av" style="background:#E5E8E5 url(\'' . e($photo) . '\') center / cover no-repeat"></div>';
    }
    $c = $colors[(int)$row['client_id'] % count($colors)];
    return '<div class="av" style="background:' . $c[0] . ';color:' . $c[1] . '">' . e(client_initials($row)) . '</div>';
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

// "Heute", "Morgen" oder "Mo, 26. Okt" (für zukünftige Termine)
function day_label($date) {
    global $t, $lang, $days_short;
    if (!$date) {
        return "";
    }
    if ($date == date('Y-m-d')) {
        return $t['today'];
    }
    if ($date == date('Y-m-d', strtotime('+1 day'))) {
        return $t['tomorrow'];
    }
    return $days_short[$lang][(int)date('w', strtotime($date))] . ', ' . short_date($date);
}

// Uhrzeit ohne Sekunden ("09:00:00" wird zu "09:00")
function fmt_time($time) {
    return $time ? substr($time, 0, 5) : "";
}

// "26. Okt, 09:00"
function fmt_date_time($date, $time) {
    return short_date($date) . ', ' . fmt_time($time);
}

// Betrag mit Währungssymbol ("R$ 350.00")
function money($fee, $currency) {
    if ($fee === null) {
        return "";
    }
    $symbols = ['BRL' => 'R$', 'EUR' => '€', 'USD' => '$'];
    $symbol  = $symbols[$currency] ?? $currency;
    return $symbol . ' ' . number_format($fee, 2, '.', '');
}

// nur Links, die mit http:// oder https:// anfangen (verhindert gefährliche Links)
function safe_url($url) {
    if ($url && preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return "";
}

// farbiges Etikett (Label) für den Status des Termins
function status_badge($status) {
    global $t;
    $labels = [
        'scheduled'   => [$t['st_scheduled'],   'b-sch'],
        'rescheduled' => [$t['st_rescheduled'], 'b-res'],
    ];
    $b = $labels[$status] ?? [ucfirst((string)$status), 'b-res'];
    return '<span class="bd ' . $b[1] . '">' . e($b[0]) . '</span>';
}

// Mahlzeitentypen: Name (in der richtigen Sprache), Hintergrundfarbe und Textfarbe
$meal_types = [
    1 => [$t['meal1'], '#FAC775', '#633806'],
    2 => [$t['meal2'], '#C0DD97', '#27500A'],
    3 => [$t['meal3'], '#F5C4B3', '#712B13'],
    4 => [$t['meal4'], '#E4E1D8', '#444441'],
];

// BLOCK 5: die 4 Zahlen im Kopf (0, wenn es noch keine Daten gibt)

// neue Nachrichten (status 0 = New)
$count_messages = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE status = 0")->fetchColumn();

// zukünftige Termine: scheduled oder rescheduled, ab heute
$count_appointments = (int)$pdo->query(
    "SELECT COUNT(*) FROM appointments
     WHERE status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()"
)->fetchColumn();

// offene Zahlungen (status 0 = Open)
$count_payments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 0")->fetchColumn();

// offene Registrierungen (approval_status 0 = Pending; 3 = MoreInfos zählt NICHT)
$count_pending = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE approval_status = 0")->fetchColumn();

// BLOCK 6: die Listen der Karten (nur Lesen, keine Daten vom Benutzer)

// nächste Termine
$appointments = $pdo->query(
    "SELECT a.appointment_date, a.appointment_time, a.meeting_url, a.status,
            c.client_id, c.first_name, c.last_name, c.profile_photo_path
     FROM appointments a
     JOIN clients c ON c.client_id = a.clients_client_id
     WHERE a.status IN ('scheduled', 'rescheduled') AND a.appointment_date >= CURDATE()
     ORDER BY a.appointment_date, a.appointment_time
     LIMIT 4"
)->fetchAll(PDO::FETCH_ASSOC);

// offene Registrierungen
$pending = $pdo->query(
    "SELECT client_id, first_name, last_name, profile_photo_path, city, country, registration_date
     FROM clients
     WHERE approval_status = 0
     ORDER BY registration_date DESC
     LIMIT 2"
)->fetchAll(PDO::FETCH_ASSOC);

// Hinweis nach dem Genehmigen/Ablehnen einer Anfrage (kommt von der Weiterleitung aus appointments.php)
$flash = "";
$flash_type = "success";
$flash_key = ['approved' => 'done_approved', 'rejected' => 'done_rejected'][$_GET['done'] ?? ''] ?? '';
if ($flash_key !== '') {
    $flash = $t[$flash_key];
    if (isset($_GET['warn'])) { $flash .= ' ' . $t['warn_conflict']; }
} elseif (($_GET['done'] ?? '') === 'error') {
    $flash = $t['err_done'];
    $flash_type = "error";
}

// Anfragen zur Terminverschiebung, die auf Antwort warten (status 0 = Pending)
$requests = $pdo->query(
    "SELECT r.request_id, r.requested_date, r.requested_time, a.appointment_date, a.appointment_time,
            c.client_id, c.first_name, c.last_name, c.profile_photo_path
     FROM reschedule_requests r
     JOIN appointments a ON a.appointment_id = r.appointments_appointment_id
     JOIN clients c ON c.client_id = a.clients_client_id
     WHERE r.status = 0
     ORDER BY r.request_id DESC
     LIMIT 2"
)->fetchAll(PDO::FETCH_ASSOC);

// neue Nachrichten
$messages = $pdo->query(
    "SELECT m.subject, c.client_id, c.first_name, c.last_name, c.profile_photo_path
     FROM messages m
     JOIN clients c ON c.client_id = m.clients_client_id
     WHERE m.status = 0
     ORDER BY m.created_at DESC
     LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);

// offene Zahlungen (der Betrag kommt aus der Monatsgebühr des Kunden)
$payments = $pdo->query(
    "SELECT p.due_date, c.client_id, c.first_name, c.last_name, c.profile_photo_path, c.monthly_fee, c.currency
     FROM payments p
     JOIN clients c ON c.client_id = p.clients_client_id
     WHERE p.status = 0
     ORDER BY p.due_date ASC
     LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);

// letzte Mahlzeitenfotos
$photos = $pdo->query(
    "SELECT mp.photo_id, mp.meal_type, c.first_name, c.last_name, c.client_id, c.profile_photo_path
     FROM meal_photos mp
     JOIN clients c ON c.client_id = mp.clients_client_id
     ORDER BY mp.uploaded_at DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

// BLOCK 7: Daten der Ärztin (Name, Logo, Foto und die 4 Farben)
$profile = $pdo->query(
    "SELECT professional_name, logo_path, profile_photo_path,
            primary_color, secundary_color, background_color, accent_color
     FROM nutritionist_profile LIMIT 1"
)->fetch();

$name       = $profile['professional_name'] ?? "Nutritionist";
$logo       = $profile['logo_path'] ?? "";
$photo      = $profile['profile_photo_path'] ?? "";
$primary    = $profile['primary_color']      ?? "#2E7D32";
$secondary  = $profile['secundary_color']    ?? "#A5D6A7";
$background = $profile['background_color']   ?? "#F5F7F5";
$accent     = $profile['accent_color']       ?? "#FF9800";

// Initialen der Ärztin ("Dra. Sofia Martins" wird zu "SM")
$initials = "";
foreach (explode(" ", trim($name)) as $word) {
    if ($word === "" || strpos($word, ".") !== false) {
        continue;
    }
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}
$initials = mb_substr($initials, 0, 2);

// Begrüßung: "Guten Morgen, Dra. Sofia" (ändert sich je nach Tageszeit)
$hour = (int)date('G');
if ($hour < 12) {
    $greet = $t['greet_morning'];
} elseif ($hour < 18) {
    $greet = $t['greet_afternoon'];
} else {
    $greet = $t['greet_evening'];
}

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

// heutiges Datum oben: "Freitag, 23. Okt"
$today_text = $days_full[$lang][(int)date('w')] . ', ' . short_date(date('Y-m-d'));

// Einträge des Seitenmenüs: [Datei, Symbol, Text]
$current = "admin_dashboard.php";
$nav = [
    ["admin_dashboard.php", "dashboard", $t['nav_dashboard']],
    ["clients.php",         "users",     $t['nav_clients']],
    ["appointments.php",    "calendar",  $t['nav_appointments']],
    ["messages.php",        "message",   $t['nav_messages']],
    ["documents.php",       "documents", $t['nav_documents']],
    ["meal_photos.php",     "camera",    $t['nav_photos']],
    ["payments.php",        "wallet",    $t['nav_payments']],
    ["settings.php",        "settings",  $t['nav_settings']],
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($t['nav_dashboard']) ?> - <?= e($name) ?></title>

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

        /* ICONS (Symbole)
        Dieselbe .svg-Datei passt für hellen und dunklen Hintergrund. Die Originalfarbe
        der Datei ist egal: der Filter "brightness(0)" macht das Symbol schwarz
        und danach passen wir es an:
        - HELLER Hintergrund (Standard): schwarz mit Transparenz = dunkelgrau
        - DUNKLER Hintergrund (Seitenleiste und Kopf): "invert(1)" = weiß */
        .ico { width: 18px; height: 18px; vertical-align: middle; filter: brightness(0); opacity: .6; }
        .side .ico, .top .ico { filter: brightness(0) invert(1); opacity: .95; }
        /* aktiver Menüpunkt: heller Hintergrund, deshalb dunkles Symbol */
        .nav a.on .ico { filter: brightness(0); opacity: .75; }

        /* Aufbau der Seite: Seitenleiste + Kopf + Inhalt */
        .layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            grid-template-rows: 110px 1fr;
            min-height: 100vh;
        }

        /* SEITENLEISTE (barra lateral) */
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
        .top .ico { width: 22px; height: 22px; }
        .kpi {
            background: rgba(255,255,255,.14); color: #fff;
            border-radius: 10px; padding: 10px 14px;
            display: flex; align-items: center; gap: 12px;
        }
        .kpi b { display: block; font-size: 24px; line-height: 1; }
        .kpi span { display: block; font-size: 12px; margin-top: 3px; }

        /* INHALT */
        .main { padding: 20px; }
        .panel { background: #fff; border: 1px solid #D8DDD8; border-radius: 10px; padding: 18px; }
        .hello { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .hello h1 { margin: 0; font-size: 20px; color: var(--primary); }
        .hello span { font-size: 13px; color: #666; }

        .card { border: 1px solid #D8DDD8; border-radius: 10px; padding: 12px; background: #fff; min-width: 0; }
        /* hervorgehobene Karte: die Sekundärfarbe ganz hell */
        .card.hl {
            background: linear-gradient(rgba(255,255,255,.65), rgba(255,255,255,.65)), var(--secondary);
            border-color: var(--secondary);
        }
        .ct { display: flex; align-items: center; gap: 7px; font-size: 14px; font-weight: bold; color: var(--primary); margin-bottom: 8px; }
        .ct .lnk { margin-left: auto; font-size: 12px; font-weight: normal; color: var(--accent); }
        .g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }

        .li { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #E5E8E5; font-size: 13px; }
        .li:last-child { border-bottom: none; padding-bottom: 0; }
        .tx { flex: 1; min-width: 0; }
        .nm { font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .nm.strong { font-weight: bold; }
        .sb { font-size: 12px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
        .tm { width: 84px; flex: none; font-size: 12px; font-weight: bold; color: var(--primary); line-height: 1.35; }
        .tm span { font-weight: normal; color: #666; }
        .av {
            width: 30px; height: 30px; border-radius: 50%; flex: none;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: bold;
        }
        .empty { font-size: 13px; color: #888; padding: 6px 0; }

        /* Etiketten (Labels) für den Status */
        .bd { font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 99px; flex: none; }
        .b-sch { background: #DDEBD2; color: #27500A; }
        .b-res { background: #E6E0F7; color: #3C3489; }
        .b-new { background: var(--accent); color: #fff; }

        /* kleine Knöpfe */
        .btn-sm { font-size: 12px; padding: 4px 11px; border: 1px solid var(--primary); border-radius: 8px; color: var(--primary); background: #fff; flex: none; }
        .sq { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex: none; }
        .sq.ok { background: #DDEBD2; }
        .sq.no { background: #F9D6D6; }
        .sq .ico { opacity: .75; width: 16px; height: 16px; }
        button.sq { border: 0; padding: 0; cursor: pointer; font: inherit; }
        form.inl { margin: 0; display: inline-flex; }
        .flash { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 14px; }
        .flash.success { background: #DDEBD2; color: #27500A; }
        .flash.error { background: #F9D6D6; color: #791F1F; }
        .amount { font-size: 13px; font-weight: bold; flex: none; }

        /* Mahlzeitenfotos: Vorschaubilder der echten Fotos (die Farbe des Typs erscheint, wenn das Foto nicht lädt) */
        .photos { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .ph {
            position: relative; display: block; aspect-ratio: 1 / 1; border-radius: 8px; overflow: hidden;
            display: flex; align-items: center; justify-content: center;
        }
        .ph .ico { opacity: .55; width: 22px; height: 22px; }
        .ph img.pic { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .ph .cap {
            position: absolute; left: 0; right: 0; bottom: 0; padding: 14px 6px 5px;
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
            .photos { grid-template-columns: repeat(3, 1fr); }
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
                    <img src="<?= e($photo) ?>" alt="<?= e($name) ?>">
                <?php else: ?>
                    <?= e($initials) ?>
                <?php endif; ?>
            </div>
            <div class="pname"><?= e($name) ?></div>
            <div class="prole"><?= e($t['role']) ?></div>
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
        <div class="kpi"><?= icon('mail') ?><div><b><?= $count_messages ?></b><span><?= e($t['kpi_messages']) ?></span></div></div>
        <div class="kpi"><?= icon('calendar') ?><div><b><?= $count_appointments ?></b><span><?= e($t['kpi_appointments']) ?></span></div></div>
        <div class="kpi"><?= icon('coin') ?><div><b><?= $count_payments ?></b><span><?= e($t['kpi_payments']) ?></span></div></div>
        <div class="kpi"><?= icon('user-check') ?><div><b><?= $count_pending ?></b><span><?= e($t['kpi_pending']) ?></span></div></div>
    </header>

    <!-- INHALT -->
    <main class="main">
        <div class="panel">

            <?php if ($flash !== ""): ?>
                <div class="flash <?= e($flash_type) ?>"><?= e($flash) ?></div>
            <?php endif; ?>

            <div class="hello">
                <h1><?= e($greet) ?>, <?= e($short) ?></h1>
                <span><?= e($today_text) ?></span>
            </div>

            <!-- Nächste Termine -->
            <div class="card hl">
                <div class="ct"><?= icon('calendar') ?><?= e($t['kpi_appointments']) ?> <a class="lnk" href="appointments.php"><?= e($t['view_all']) ?></a></div>
                <?php if (count($appointments) === 0): ?>
                    <div class="empty"><?= e($t['empty_appointments']) ?></div>
                <?php endif; ?>
                <?php foreach ($appointments as $a): ?>
                    <div class="li">
                        <div class="tm"><?= e(day_label($a['appointment_date'])) ?><br><span><?= e(fmt_time($a['appointment_time'])) ?></span></div>
                        <?= avatar($a) ?>
                        <div class="tx"><div class="nm strong"><?= e(full_name($a)) ?></div></div>
                        <?php $url = safe_url($a['meeting_url']); ?>
                        <?php if ($url !== ""): ?>
                            <a href="<?= e($url) ?>" target="_blank" rel="noopener" title="<?= e($t['join_video']) ?>"><?= icon('video') ?></a>
                        <?php endif; ?>
                        <?= status_badge($a['status']) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="g2">
                <!-- Offene Genehmigungen -->
                <div class="card">
                    <div class="ct"><?= icon('user-check') ?><?= e($t['kpi_pending']) ?> <a class="lnk" href="clients.php?status=pending"><?= e($t['view_all']) ?></a></div>
                    <?php if (count($pending) === 0): ?>
                        <div class="empty"><?= e($t['empty_pending']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($pending as $c): ?>
                        <div class="li">
                            <?= avatar($c) ?>
                            <div class="tx">
                                <div class="nm strong"><?= e(full_name($c)) ?></div>
                                <div class="sb"><?= e(trim($c['city'] . ', ' . $c['country'], ', ')) ?> · <?= e(friendly_day($c['registration_date'])) ?></div>
                            </div>
                            <a class="btn-sm" href="registration_review.php?id=<?= (int)$c['client_id'] ?>"><?= e($t['review']) ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Anfragen zur Terminverschiebung -->
                <div class="card">
                    <div class="ct"><?= icon('calendar-time') ?><?= e($t['card_requests']) ?> <a class="lnk" href="appointments.php"><?= e($t['view_all']) ?></a></div>
                    <?php if (count($requests) === 0): ?>
                        <div class="empty"><?= e($t['empty_requests']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($requests as $r): ?>
                        <div class="li">
                            <?= avatar($r) ?>
                            <div class="tx">
                                <div class="nm strong"><?= e(full_name($r)) ?></div>
                                <div class="sb"><?= e(fmt_date_time($r['appointment_date'], $r['appointment_time'])) ?> → <?= e(fmt_date_time($r['requested_date'], $r['requested_time'])) ?></div>
                            </div>
                            <!-- Genehmigen / Ablehnen direkt hier (appointments.php macht die Arbeit und kommt zum Dashboard zurück) -->
                            <form method="post" action="appointments.php" class="inl" onsubmit="return confirm(<?= e(json_encode($t['approve_confirm'])) ?>);">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="form" value="request_decision">
                                <input type="hidden" name="return" value="dashboard">
                                <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="sq ok" title="<?= e($t['approve']) ?>"><?= icon('check') ?></button>
                            </form>
                            <form method="post" action="appointments.php" class="inl" onsubmit="return confirm(<?= e(json_encode($t['reject_confirm'])) ?>);">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="form" value="request_decision">
                                <input type="hidden" name="return" value="dashboard">
                                <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                                <input type="hidden" name="decision" value="reject">
                                <button type="submit" class="sq no" title="<?= e($t['decline']) ?>"><?= icon('x') ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="g2">
                <!-- Neue Nachrichten -->
                <div class="card">
                    <div class="ct"><?= icon('message') ?><?= e($t['kpi_messages']) ?> <a class="lnk" href="messages.php"><?= e($t['view_all']) ?></a></div>
                    <?php if (count($messages) === 0): ?>
                        <div class="empty"><?= e($t['empty_messages']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($messages as $m): ?>
                        <div class="li">
                            <?= avatar($m) ?>
                            <div class="tx">
                                <div class="nm strong"><?= e(full_name($m)) ?></div>
                                <div class="sb"><?= e($m['subject']) ?></div>
                            </div>
                            <span class="bd b-new"><?= e($t['badge_new']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Offene Zahlungen -->
                <div class="card">
                    <div class="ct"><?= icon('coin') ?><?= e($t['kpi_payments']) ?> <a class="lnk" href="payments.php?status=open"><?= e($t['view_all']) ?></a></div>
                    <?php if (count($payments) === 0): ?>
                        <div class="empty"><?= e($t['empty_payments']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($payments as $p): ?>
                        <div class="li">
                            <?= avatar($p) ?>
                            <div class="tx">
                                <div class="nm strong"><?= e(full_name($p)) ?></div>
                                <div class="sb"><?= e($t['due']) ?> <?= e($p['due_date'] ? short_date($p['due_date']) : '-') ?></div>
                            </div>
                            <span class="amount"><?= e(money($p['monthly_fee'], $p['currency'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Aktuelle Mahlzeitenfotos -->
            <div class="card" style="margin-top:14px;">
                <div class="ct"><?= icon('camera') ?><?= e($t['card_photos']) ?> <a class="lnk" href="meal_photos.php"><?= e($t['view_all']) ?></a></div>
                <?php if (count($photos) === 0): ?>
                    <div class="empty"><?= e($t['empty_photos']) ?></div>
                <?php else: ?>
                    <div class="photos">
                        <?php foreach ($photos as $ph):
                            $type = $meal_types[$ph['meal_type']] ?? $meal_types[4];
                        ?>
                            <a class="ph" href="meal_photos.php?client=<?= (int)$ph['client_id'] ?>"
                               style="background:<?= e($type[1]) ?>;color:<?= e($type[2]) ?>"
                               title="<?= e(trim($ph['first_name'] . ' ' . $ph['last_name'])) ?> · <?= e($type[0]) ?>">
                                <?= icon('camera') ?>
                                <img class="pic" src="view_image.php?id=<?= (int)$ph['photo_id'] ?>" alt="" loading="lazy" onerror="this.remove()">
                                <span class="cap"><?= e($ph['first_name']) ?> · <?= e($type[0]) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>
</body>
</html>
