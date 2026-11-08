<?php
// client_appointments.php
// "Appointments" (Termine) des FREIGEGEBENEN Kunden: nächste Termine sehen (mit dem Link zum Videoanruf),
// eine Terminverschiebung anfragen und den Verlauf von Terminen und Anfragen ansehen.
// Regeln der V1: nur 1 OFFENE Anfrage pro Termin; der Kunde sagt keine Termine ab
// (er fragt nur eine Verschiebung an); die Entscheidung trifft die Ernährungsberaterin (AP07, andere Seite).

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

// 2f: nur FREIGEGEBENE Kunden (1); alle anderen gehen zur Statusseite
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
        'page_title' => 'Appointments', 'subtitle' => 'Your upcoming appointments and reschedule requests.',
        'your_nutri' => 'Your nutritionist: %s',
        'nav_home' => 'Home', 'nav_appointments' => 'Appointments', 'nav_messages' => 'Messages',
        'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos', 'nav_payments' => 'Payments',
        'nav_profile' => 'My Profile', 'logout' => 'Logout',
        'kpi_appt' => 'Next Appointment', 'kpi_reply' => 'New Reply', 'kpi_payment' => 'Payment', 'kpi_weight' => 'Current Weight',
        'due' => 'Due',
        'sec_upcoming' => 'Upcoming appointments', 'upcoming_empty' => 'You have no upcoming appointments.',
        'appt_line' => '%1$s, %2$s with %3$s',
        'join_video' => 'Join video call', 'no_link' => 'The video link will be added by your nutritionist.',
        'btn_reschedule' => 'Request reschedule', 'req_title' => 'Request a new date',
        'new_date' => 'New date', 'new_time' => 'New time', 'reason' => 'Reason',
        'reason_ph' => 'Tell your nutritionist why you need to change the appointment.',
        'btn_send' => 'Send request',
        'pending_box' => 'Request sent: %1$s at %2$s. Waiting for the answer of %3$s.',
        'sec_requests' => 'Your reschedule requests', 'requests_empty' => 'You have not sent any request yet.',
        'col_appt' => 'Appointment', 'col_new' => 'Requested', 'col_status' => 'Status', 'col_date' => 'Date', 'col_time' => 'Time',
        'req_0' => 'Pending', 'req_1' => 'Approved', 'req_2' => 'Rejected',
        'sec_past' => 'Past appointments', 'past_empty' => 'No past appointments yet.',
        'st_scheduled' => 'Scheduled', 'st_rescheduled' => 'Rescheduled', 'st_completed' => 'Completed', 'st_cancelled' => 'Cancelled',
        'saved_request' => 'Your request was sent. Your nutritionist will answer soon.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_appt' => 'This appointment is not available for rescheduling.',
        'err_pending' => 'There is already a pending request for this appointment.',
        'err_date' => 'Please choose a date after today (within the next year).',
        'err_time' => 'Please enter a valid time.',
        'err_same' => 'The new date and time are the same as the current appointment.',
        'err_reason' => 'Please tell us the reason (maximum 1000 characters).',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'page_title' => 'Consultas', 'subtitle' => 'Suas próximas consultas e pedidos de reagendamento.',
        'your_nutri' => 'Sua nutricionista: %s',
        'nav_home' => 'Início', 'nav_appointments' => 'Consultas', 'nav_messages' => 'Mensagens',
        'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições', 'nav_payments' => 'Pagamentos',
        'nav_profile' => 'Meu Perfil', 'logout' => 'Sair',
        'kpi_appt' => 'Próxima Consulta', 'kpi_reply' => 'Nova Resposta', 'kpi_payment' => 'Pagamento', 'kpi_weight' => 'Peso Atual',
        'due' => 'Vence',
        'sec_upcoming' => 'Próximas consultas', 'upcoming_empty' => 'Você não tem consultas marcadas.',
        'appt_line' => '%1$s, %2$s com %3$s',
        'join_video' => 'Entrar na videochamada', 'no_link' => 'O link da videochamada será adicionado pela sua nutricionista.',
        'btn_reschedule' => 'Pedir reagendamento', 'req_title' => 'Pedir uma nova data',
        'new_date' => 'Nova data', 'new_time' => 'Novo horário', 'reason' => 'Motivo',
        'reason_ph' => 'Conte à sua nutricionista por que você precisa mudar a consulta.',
        'btn_send' => 'Enviar pedido',
        'pending_box' => 'Pedido enviado: %1$s às %2$s. Aguardando a resposta de %3$s.',
        'sec_requests' => 'Seus pedidos de reagendamento', 'requests_empty' => 'Você ainda não enviou nenhum pedido.',
        'col_appt' => 'Consulta', 'col_new' => 'Solicitado', 'col_status' => 'Status', 'col_date' => 'Data', 'col_time' => 'Horário',
        'req_0' => 'Pendente', 'req_1' => 'Aprovado', 'req_2' => 'Recusado',
        'sec_past' => 'Consultas anteriores', 'past_empty' => 'Ainda não há consultas anteriores.',
        'st_scheduled' => 'Agendada', 'st_rescheduled' => 'Reagendada', 'st_completed' => 'Realizada', 'st_cancelled' => 'Cancelada',
        'saved_request' => 'Seu pedido foi enviado. Sua nutricionista responderá em breve.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_appt' => 'Esta consulta não está disponível para reagendamento.',
        'err_pending' => 'Já existe um pedido pendente para esta consulta.',
        'err_date' => 'Escolha uma data depois de hoje (dentro do próximo ano).',
        'err_time' => 'Informe um horário válido.',
        'err_same' => 'A nova data e horário são iguais aos da consulta atual.',
        'err_reason' => 'Conte o motivo (máximo 1000 caracteres).',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'page_title' => 'Termine', 'subtitle' => 'Ihre bevorstehenden Termine und Umterminungsanfragen.',
        'your_nutri' => 'Ihre Ernährungsberaterin: %s',
        'nav_home' => 'Start', 'nav_appointments' => 'Termine', 'nav_messages' => 'Nachrichten',
        'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos', 'nav_payments' => 'Zahlungen',
        'nav_profile' => 'Mein Profil', 'logout' => 'Abmelden',
        'kpi_appt' => 'Nächster Termin', 'kpi_reply' => 'Neue Antwort', 'kpi_payment' => 'Zahlung', 'kpi_weight' => 'Aktuelles Gewicht',
        'due' => 'Fällig',
        'sec_upcoming' => 'Bevorstehende Termine', 'upcoming_empty' => 'Sie haben keine bevorstehenden Termine.',
        'appt_line' => '%1$s, %2$s Uhr mit %3$s',
        'join_video' => 'Videoanruf beitreten', 'no_link' => 'Den Videolink trägt Ihre Ernährungsberaterin ein.',
        'btn_reschedule' => 'Umterminung anfragen', 'req_title' => 'Neuen Termin anfragen',
        'new_date' => 'Neues Datum', 'new_time' => 'Neue Uhrzeit', 'reason' => 'Begründung',
        'reason_ph' => 'Teilen Sie Ihrer Ernährungsberaterin mit, warum Sie den Termin ändern möchten.',
        'btn_send' => 'Anfrage senden',
        'pending_box' => 'Anfrage gesendet: %1$s um %2$s Uhr. Warten auf die Antwort von %3$s.',
        'sec_requests' => 'Ihre Umterminungsanfragen', 'requests_empty' => 'Sie haben noch keine Anfrage gesendet.',
        'col_appt' => 'Termin', 'col_new' => 'Gewünscht', 'col_status' => 'Status', 'col_date' => 'Datum', 'col_time' => 'Uhrzeit',
        'req_0' => 'Ausstehend', 'req_1' => 'Genehmigt', 'req_2' => 'Abgelehnt',
        'sec_past' => 'Vergangene Termine', 'past_empty' => 'Noch keine vergangenen Termine.',
        'st_scheduled' => 'Geplant', 'st_rescheduled' => 'Verschoben', 'st_completed' => 'Abgeschlossen', 'st_cancelled' => 'Abgesagt',
        'saved_request' => 'Ihre Anfrage wurde gesendet. Ihre Ernährungsberaterin antwortet bald.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_appt' => 'Dieser Termin kann nicht umgebucht werden.',
        'err_pending' => 'Für diesen Termin gibt es bereits eine ausstehende Anfrage.',
        'err_date' => 'Bitte wählen Sie ein Datum nach heute (innerhalb des nächsten Jahres).',
        'err_time' => 'Bitte geben Sie eine gültige Uhrzeit ein.',
        'err_same' => 'Datum und Uhrzeit sind identisch mit dem aktuellen Termin.',
        'err_reason' => 'Bitte geben Sie eine Begründung an (maximal 1000 Zeichen).',
        'err_other' => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.',
    ],
];
$t = $texts[$lang];

// Namen von Monaten und Wochentagen in jeder Sprache
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
    if ($lang === 'en') { return $m . ' ' . $d; }
    if ($lang === 'de') { return $d . '. ' . $m; }
    return $d . ' ' . $m;
}

// Datum mit Jahr: "Oct 26, 2026" (EN), "26. Okt 2026" (DE), "26 out 2026" (PT)
function long_date($date) {
    global $lang, $months;
    $ts = strtotime($date);
    $d  = (int)date('j', $ts);
    $m  = $months[$lang][(int)date('n', $ts) - 1];
    $y  = date('Y', $ts);
    if ($lang === 'en') { return $m . ' ' . $d . ', ' . $y; }
    if ($lang === 'de') { return $d . '. ' . $m . ' ' . $y; }
    return $d . ' ' . $m . ' ' . $y;
}

// Wochentag + Datum: "Monday, Nov 9, 2026"
function weekday_date($date) {
    global $lang, $days_full;
    return $days_full[$lang][(int)date('w', strtotime($date))] . ', ' . long_date($date);
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

// Betrag mit Währungssymbol ("R$ 350.00")
function money($fee, $currency) {
    if ($fee === null || $fee === '') { return "-"; }
    $symbols = ['BRL' => 'R$', 'EUR' => '€', 'USD' => '$'];
    return ($symbols[$currency] ?? $currency) . ' ' . number_format($fee, 2, '.', '');
}

// Zahl mit 1 Nachkommastelle ("64.2")
function one_decimal($n) {
    return number_format((float)$n, 1, '.', '');
}

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
    if ($word === "") { continue; }
    $short .= ($short === "" ? "" : " ") . $word;
    if (strpos($word, ".") === false) { break; }
}

// BLOCK 6: Sicherheits-Token (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Werte des Anfrage-Formulars (kommen nur ausgefüllt zurück, wenn es einen Fehler gab)
$form_error    = "";     // Text des Fehlers
$form_error_id = 0;      // zu welchem Termin der Fehler gehört (der Fehler erscheint in der richtigen Karte)
$v_date   = "";
$v_time   = "";
$v_reason = "";

// BLOCK 7: Was passiert, wenn das Formular abgeschickt wird
// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token   = $_POST['csrf_token'] ?? '';
    // filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
    $appt_id = filter_var($_POST['appointment_id'] ?? '', FILTER_VALIDATE_INT);

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    if (!hash_equals($_SESSION['csrf_token'], (string)$token) || ($_POST['form'] ?? '') !== 'reschedule'
        || $appt_id === false || $appt_id < 1) {
        $form_error = $t['err_invalid'];
    } else {
        $form_error_id = $appt_id;
        $v_date   = trim($_POST['new_date'] ?? '');
        $v_time   = trim($_POST['new_time'] ?? '');
        $v_reason = trim($_POST['reason'] ?? '');

        try {
            // der Termin muss DIESEM Kunden gehören, geplant sein (scheduled/rescheduled) und ab heute liegen
            $stmt = $pdo->prepare(
                "SELECT appointment_date, appointment_time FROM appointments
                 WHERE appointment_id = ? AND clients_client_id = ?
                   AND status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()"
            );
            $stmt->execute([$appt_id, $client_id]);
            $appt = $stmt->fetch();

            // Regel der V1: nur 1 offene Anfrage (status 0) pro Termin
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM reschedule_requests WHERE appointments_appointment_id = ? AND status = 0"
            );
            $stmt->execute([$appt_id]);
            $has_pending = (int)$stmt->fetchColumn() > 0;

            // gültiges Datum (JJJJ-MM-TT, echt), nach heute und innerhalb von 1 Jahr
            $date_ok = false;
            $d_obj = DateTime::createFromFormat('Y-m-d', $v_date);
            if ($d_obj && $d_obj->format('Y-m-d') === $v_date
                && $v_date > date('Y-m-d') && $v_date <= date('Y-m-d', strtotime('+1 year'))) {
                $date_ok = true;
            }
            $time_ok = (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v_time);

            if (!$appt) {
                $form_error = $t['err_appt'];
            } elseif ($has_pending) {
                $form_error = $t['err_pending'];
            } elseif (!$date_ok) {
                $form_error = $t['err_date'];
            } elseif (!$time_ok) {
                $form_error = $t['err_time'];
            } elseif ($v_date === $appt['appointment_date'] && $v_time === fmt_time($appt['appointment_time'])) {
                $form_error = $t['err_same'];
            } elseif ($v_reason === '' || mb_strlen($v_reason) > 1000) {
                $form_error = $t['err_reason'];
            } else {
                // alles in Ordnung: die Anfrage wird als Pending (0) gespeichert
                $stmt = $pdo->prepare(
                    "INSERT INTO reschedule_requests
                        (requested_date, requested_time, request_reason, status, appointments_appointment_id)
                     VALUES (?, ?, ?, 0, ?)"
                );
                $stmt->execute([$v_date, $v_time . ':00', $v_reason, $appt_id]);

                header("Location: client_appointments.php?done=request");
                exit;
            }
        } catch (PDOException $ex) {
            $form_error = $t['err_other'];
        }
    }
}

// Erfolgsmeldung (kommt von der Weiterleitung nach dem Senden)
$success = (($_GET['done'] ?? '') === 'request') ? $t['saved_request'] : "";

// BLOCK 8: Daten des Kunden für die Seitenleiste
$first      = $client['first_name'] ?? "";
$full_name  = trim($first . ' ' . ($client['last_name'] ?? ''));
$initials   = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($client['last_name'] ?? '', 0, 1));
$photo      = $client['profile_photo_path'] ?? "";

// BLOCK 9: Daten dieser Seite

// nächste Termine (scheduled oder rescheduled, ab heute)
$stmt = $pdo->prepare(
    "SELECT appointment_id, appointment_date, appointment_time, meeting_url, notes, status
     FROM appointments
     WHERE clients_client_id = ? AND status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()
     ORDER BY appointment_date, appointment_time LIMIT 10"
);
$stmt->execute([$client_id]);
$upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

// offene Anfragen (status 0), pro Termin
$stmt = $pdo->prepare(
    "SELECT r.appointments_appointment_id AS appt_id, r.requested_date, r.requested_time
     FROM reschedule_requests r
     JOIN appointments a ON a.appointment_id = r.appointments_appointment_id
     WHERE a.clients_client_id = ? AND r.status = 0
     ORDER BY r.request_id DESC"
);
$stmt->execute([$client_id]);
$pending = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!isset($pending[$row['appt_id']])) { $pending[$row['appt_id']] = $row; }
}

// Verlauf der Anfragen (die 10 neuesten)
$stmt = $pdo->prepare(
    "SELECT r.requested_date, r.requested_time, r.status AS req_status, a.appointment_date, a.appointment_time
     FROM reschedule_requests r
     JOIN appointments a ON a.appointment_id = r.appointments_appointment_id
     WHERE a.clients_client_id = ?
     ORDER BY r.request_id DESC LIMIT 10"
);
$stmt->execute([$client_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// frühere Termine: schon vorbei, oder erledigt/abgesagt
$stmt = $pdo->prepare(
    "SELECT appointment_date, appointment_time, status FROM appointments
     WHERE clients_client_id = ?
       AND (appointment_date < CURDATE() OR status IN ('completed', 'cancelled'))
     ORDER BY appointment_date DESC, appointment_time DESC LIMIT 10"
);
$stmt->execute([$client_id]);
$past = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Zahlen im Kopf
$next_appt = $upcoming[0] ?? null;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE clients_client_id = ? AND status = 1");
$stmt->execute([$client_id]);
$count_replies = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT due_date FROM payments WHERE clients_client_id = ? AND status = 0 ORDER BY due_date ASC LIMIT 1"
);
$stmt->execute([$client_id]);
$next_due = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT weight FROM client_measurements
     WHERE clients_client_id = ? AND weight IS NOT NULL
     ORDER BY measurement_date DESC, measurement_id DESC LIMIT 1"
);
$stmt->execute([$client_id]);
$cur_weight = $stmt->fetchColumn();

$kpi_appt    = $next_appt ? short_date($next_appt['appointment_date']) . ', ' . fmt_time($next_appt['appointment_time']) : "-";
$kpi_payment = $next_due ? $t['due'] . ' ' . short_date($next_due) : "-";
$kpi_weight  = ($cur_weight !== false && $cur_weight !== null) ? one_decimal($cur_weight) . ' kg' : "-";

// Etiketten (Labels) für den Status (Termin) und (Anfrage): [Text, Farbklasse]
$appt_badges = [
    'scheduled'   => [$t['st_scheduled'],   'b-ok'],
    'rescheduled' => [$t['st_rescheduled'], 'b-res'],
    'completed'   => [$t['st_completed'],   'b-done'],
    'cancelled'   => [$t['st_cancelled'],   'b-bad'],
];
$req_badges = [0 => [$t['req_0'], 'b-res'], 1 => [$t['req_1'], 'b-ok'], 2 => [$t['req_2'], 'b-bad']];

// der Fehler erscheint in der Karte des Termins (wenn sein Formular auf dem Bildschirm ist);
// sonst (Termin einer anderen Person, offene Anfrage ...) erscheint er oben auf der Seite
$error_in_card = false;
foreach ($upcoming as $a) {
    if ($form_error_id === (int)$a['appointment_id'] && !isset($pending[$a['appointment_id']])) {
        $error_in_card = true;
    }
}

// Seitenmenü: [Datei, Symbol, Text]
$current = "client_appointments.php";
$nav = [
    ["client_home.php",         "home",      $t['nav_home']],
    ["client_appointments.php", "calendar",  $t['nav_appointments']],
    ["client_messages.php",     "message",   $t['nav_messages']],
    ["client_documents.php",    "documents", $t['nav_documents']],
    ["client_meal_photos.php",  "camera",    $t['nav_photos']],
    ["client_payments.php",     "wallet",    $t['nav_payments']],
    ["client_profile.php",      "user",      $t['nav_profile']],
];

// Reiter (abas) oben auf der Seite (dieselben wie auf der Startseite); der Reiter der aktuellen Seite ist hervorgehoben
$overview_label = ['en' => 'Overview', 'pt' => 'Visão geral', 'de' => 'Übersicht'][$lang] ?? 'Overview';
$tabs = [
    ["client_home.php",         $overview_label],
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

        /* ICONS (Symbole): die Originalfarbe der Datei ist egal (Filter); "w" erzwingt weiß */
        .ico { width: 18px; height: 18px; vertical-align: middle; filter: brightness(0); opacity: .6; }
        .side .ico, .top .ico, .ico.w { filter: brightness(0) invert(1); opacity: .95; }
        .nav a.on .ico { filter: brightness(0); opacity: .75; }

        /* Aufbau der Seite: Seitenleiste + Kopf + Inhalt */
        .layout { display: grid; grid-template-columns: 240px 1fr; grid-template-rows: 110px 1fr; min-height: 100vh; }

        /* SEITENLEISTE */
        .side { grid-row: 1 / 3; background: var(--primary); color: #fff; display: flex; flex-direction: column; padding: 20px 14px; }
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
        .nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; margin-bottom: 3px; border-radius: 8px; font-size: 14px; opacity: .92; }
        .nav a:hover { background: rgba(255,255,255,.15); }
        .nav a.on { background: var(--background); color: var(--primary); font-weight: bold; opacity: 1; }
        .spacer { flex: 1; min-height: 20px; }
        /* Sprachumschalter im Menü */
        .langs { display: flex; gap: 6px; padding: 12px 12px 4px; }
        .langs a { font-size: 12px; padding: 3px 9px; border-radius: 6px; border: 1px solid rgba(255,255,255,.55); color: #fff; opacity: .85; }
        .langs a.on { background: #fff; color: var(--primary); font-weight: bold; opacity: 1; border-color: #fff; }
        .logout { display: flex; align-items: center; gap: 10px; padding: 14px 12px 10px; border-top: 1px solid rgba(255,255,255,.25); font-size: 14px; }
        .powered { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 11px; opacity: .85; padding-top: 6px; }

        /* KOPF MIT DEN 4 ZAHLEN */
        .top { background: var(--primary); display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding: 18px 20px; align-items: stretch; }
        .top .ico { width: 22px; height: 22px; flex: none; }
        .kpi { background: rgba(255,255,255,.14); color: #fff; border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; gap: 12px; min-width: 0; }
        .kpi.hl { background: var(--accent); }
        .kpi b { display: block; font-size: 18px; line-height: 1.15; }
        .kpi span { display: block; font-size: 12px; margin-top: 3px; }

        /* INHALT */
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
        .title-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .title-row h1 { margin: 0; font-size: 20px; color: var(--primary); }
        .title-row p { margin: 2px 0 0; font-size: 13px; color: #666; }

        .card { border: 1px solid #D8DDD8; border-radius: 10px; padding: 14px; background: #fff; min-width: 0; }
        .ct { display: flex; align-items: center; gap: 7px; font-size: 14px; font-weight: bold; color: var(--primary); margin-bottom: 10px; }
        .g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px; font-size: 13px; padding: 8px 14px;
            border-radius: 8px; border: 1px solid var(--primary); color: var(--primary); background: #fff; cursor: pointer;
        }
        .btn.fill { background: var(--primary); color: #fff; }
        .btn.sm { font-size: 12px; padding: 5px 11px; }
        .btn:hover { opacity: .92; }

        /* Formulare */
        label.fl { display: block; font-size: 12px; color: #555; margin-bottom: 4px; }
        .r2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; margin-bottom: 10px; }
        .field { position: relative; }
        .field input, .field select, .field textarea {
            width: 100%; padding: 9px 11px; font-size: 13px; font-family: inherit;
            border: 1px solid #D8DDD8; border-radius: 8px; background: #fff; color: #2B2A24;
        }
        .field.has-icon input { padding-left: 36px; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: 2px solid var(--accent); border-color: var(--accent); }
        .field input[readonly] { background: #F5F7F5; color: #555; }
        .field .icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; opacity: .6; pointer-events: none; filter: brightness(0); }
        .field.pw input { padding-right: 40px; }
        .toggle { position: absolute; right: 7px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 4px; cursor: pointer; display: flex; align-items: center; }
        .toggle img { width: 17px; height: 17px; opacity: .6; filter: brightness(0); }
        .hint { font-size: 11.5px; color: #777; margin-top: 4px; }

        /* Profilfoto */
        .photo-row { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }
        .avatar {
            width: 64px; height: 64px; border-radius: 50%; flex: none; overflow: hidden;
            background: var(--secondary); color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-size: 19px; font-weight: bold;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .photo-row .t1 { font-size: 13px; font-weight: bold; }
        .photo-row input[type=file] { display: none; }
        .photo-row .pick { margin-top: 4px; }
        #photo-name { word-break: break-all; }

        .kv { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px solid #E5E8E5; font-size: 13px; }
        .kv:last-of-type { border-bottom: none; }
        .kv span:first-child { color: #666; }
        .kv span:last-child { font-weight: bold; text-align: right; }
        .note { font-size: 11.5px; color: #777; margin-top: 8px; line-height: 1.4; }

        .error { background: #F9D6D6; color: #791F1F; font-size: 13px; padding: 9px 11px; border-radius: 8px; margin-bottom: 12px; }
        .success { background: #DDEBD2; color: #27500A; font-size: 13px; padding: 9px 11px; border-radius: 8px; margin-bottom: 14px; }

        /* Verlauf */
        .chart-sum { display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; font-size: 12px; color: #666; margin-top: 4px; }
        .big { font-size: 26px; font-weight: bold; color: var(--primary); }
        .empty { font-size: 13px; color: #888; padding: 6px 0; }
        .hist { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12.5px; }
        .hist th { text-align: left; color: #777; font-weight: normal; padding: 4px 0; border-bottom: 1px solid #E5E8E5; }
        .hist td { padding: 5px 0; border-bottom: 1px solid #F0F2F0; }
        .hist .del-col { width: 32px; text-align: right; }
        .del-btn { background: none; border: 0; padding: 3px; cursor: pointer; border-radius: 6px; line-height: 0; }
        .del-btn:hover { background: #F9D6D6; }
        .del-btn img { width: 16px; height: 16px; filter: brightness(0); opacity: .55; }
        .add-box { border-top: 1px solid #E5E8E5; margin-top: 14px; padding-top: 12px; }


        /* Termine */
        .appt-item { border: 1px solid #E5E8E5; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
        .appt-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .datebox { background: var(--accent); color: #fff; border-radius: 8px; padding: 6px 14px; text-align: center; flex: none; }
        .datebox small { display: block; font-size: 11px; }
        .datebox b { display: block; font-size: 24px; line-height: 1.1; }
        .appt-text { flex: 1; min-width: 160px; }
        .appt-text .at { font-size: 14px; font-weight: bold; }
        .appt-text .as { font-size: 13px; color: #555; margin-top: 3px; }
        .badge { display: inline-block; font-size: 11px; font-weight: normal; padding: 2px 8px; border-radius: 10px; margin-left: 6px; vertical-align: middle; }
        .b-ok { background: #DDEBD2; color: #27500A; }
        .b-res { background: #FAEEDA; color: #633806; }
        .b-bad { background: #F9D6D6; color: #791F1F; }
        .b-done { background: #E4E1D8; color: #444441; }
        .pending-box { margin-top: 10px; background: #FAEEDA; color: #633806; font-size: 13px; padding: 9px 11px; border-radius: 8px; }
        details.req { margin-top: 10px; }
        details.req summary { list-style: none; cursor: pointer; border: 1px solid var(--primary); color: var(--primary); background: #fff; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; }
        details.req summary::-webkit-details-marker { display: none; }
        .req-form { margin-top: 12px; border-top: 1px solid #E5E8E5; padding-top: 12px; }
        .field textarea { resize: vertical; }
        .hist td, .hist th { padding-right: 8px; }

        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
            .side { grid-row: auto; }
            .top { grid-template-columns: 1fr 1fr; }
            .g2, .r2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">

    <!-- SEITENLEISTE -->
    <aside class="side">
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
        <div class="kpi hl"><?= icon('calendar') ?><div><b><?= e($kpi_appt) ?></b><span><?= e($t['kpi_appt']) ?></span></div></div>
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

            <?php if ($success !== ""): ?>
                <div class="success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($form_error !== "" && !$error_in_card): ?>
                <div class="error"><?= e($form_error) ?></div>
            <?php endif; ?>

            <div class="title-row">
                <div>
                    <h1><?= e($t['page_title']) ?></h1>
                    <p><?= e($t['subtitle']) ?></p>
                </div>
            </div>

            <!-- NÄCHSTE TERMINE -->
            <div class="card">
                <div class="ct"><?= icon('calendar') ?><?= e($t['sec_upcoming']) ?></div>

                <?php if (count($upcoming) === 0): ?>
                    <div class="empty"><?= e($t['upcoming_empty']) ?></div>
                <?php endif; ?>

                <?php foreach ($upcoming as $a): ?>
                    <?php
                        $ts      = strtotime($a['appointment_date']);
                        $url     = safe_url($a['meeting_url']);
                        $pend    = $pending[$a['appointment_id']] ?? null;
                        $badge   = $appt_badges[$a['status']] ?? [$a['status'], 'b-done'];
                        $has_err = ($form_error !== "" && $form_error_id === (int)$a['appointment_id']);
                    ?>
                    <div class="appt-item">
                        <div class="appt-row">
                            <div class="datebox">
                                <small><?= e(mb_strtoupper($months[$lang][(int)date('n', $ts) - 1])) ?></small>
                                <b><?= (int)date('j', $ts) ?></b>
                            </div>
                            <div class="appt-text">
                                <div class="at">
                                    <?= e(sprintf($t['appt_line'], weekday_date($a['appointment_date']), fmt_time($a['appointment_time']), $short)) ?>
                                    <span class="badge <?= e($badge[1]) ?>"><?= e($badge[0]) ?></span>
                                </div>
                                <?php if (trim($a['notes'] ?? '') !== ""): ?>
                                    <div class="as"><?= e($a['notes']) ?></div>
                                <?php endif; ?>
                                <?php if ($url === ""): ?>
                                    <div class="as"><?= e($t['no_link']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($url !== ""): ?>
                                <a class="btn fill" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= icon('video', 'w') ?><?= e($t['join_video']) ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($pend): ?>
                            <!-- es gibt schon eine offene Anfrage: man kann keine zweite senden -->
                            <div class="pending-box">
                                <?= e(sprintf($t['pending_box'], long_date($pend['requested_date']), fmt_time($pend['requested_time']), $short)) ?>
                            </div>
                        <?php else: ?>
                            <details class="req" <?= ($has_err || (($_GET['req'] ?? '') == $a['appointment_id'])) ? 'open' : '' ?>>
                                <summary class="btn sm"><?= icon('calendar-time') ?><?= e($t['btn_reschedule']) ?></summary>
                                <form method="post" action="client_appointments.php" class="req-form">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="form" value="reschedule">
                                    <input type="hidden" name="appointment_id" value="<?= (int)$a['appointment_id'] ?>">

                                    <div class="ct" style="margin-bottom:8px"><?= e($t['req_title']) ?></div>

                                    <?php if ($has_err): ?>
                                        <div class="error"><?= e($form_error) ?></div>
                                    <?php endif; ?>

                                    <div class="r2">
                                        <div>
                                            <label class="fl" for="d<?= (int)$a['appointment_id'] ?>"><?= e($t['new_date']) ?></label>
                                            <div class="field"><input type="date" id="d<?= (int)$a['appointment_id'] ?>" name="new_date"
                                                min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
                                                value="<?= $has_err ? e($v_date) : '' ?>" required></div>
                                        </div>
                                        <div>
                                            <label class="fl" for="t<?= (int)$a['appointment_id'] ?>"><?= e($t['new_time']) ?></label>
                                            <div class="field"><input type="time" id="t<?= (int)$a['appointment_id'] ?>" name="new_time"
                                                value="<?= $has_err ? e($v_time) : '' ?>" required></div>
                                        </div>
                                    </div>
                                    <div style="margin-bottom:10px">
                                        <label class="fl" for="r<?= (int)$a['appointment_id'] ?>"><?= e($t['reason']) ?></label>
                                        <div class="field"><textarea id="r<?= (int)$a['appointment_id'] ?>" name="reason" rows="3" maxlength="1000"
                                            placeholder="<?= e($t['reason_ph']) ?>" required><?= $has_err ? e($v_reason) : '' ?></textarea></div>
                                    </div>
                                    <button type="submit" class="btn fill"><?= icon('check', 'w') ?><?= e($t['btn_send']) ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="g2">
                <!-- MEINE ANFRAGEN -->
                <div class="card">
                    <div class="ct"><?= icon('calendar-time') ?><?= e($t['sec_requests']) ?></div>
                    <?php if (count($requests) === 0): ?>
                        <div class="empty"><?= e($t['requests_empty']) ?></div>
                    <?php else: ?>
                        <table class="hist">
                            <tr><th><?= e($t['col_appt']) ?></th><th><?= e($t['col_new']) ?></th><th><?= e($t['col_status']) ?></th></tr>
                            <?php foreach ($requests as $r): ?>
                                <?php $rb = $req_badges[(int)$r['req_status']] ?? ['-', 'b-done']; ?>
                                <tr>
                                    <td><?= e(short_date($r['appointment_date'])) ?>, <?= e(fmt_time($r['appointment_time'])) ?></td>
                                    <td><?= e(short_date($r['requested_date'])) ?>, <?= e(fmt_time($r['requested_time'])) ?></td>
                                    <td><span class="badge <?= e($rb[1]) ?>"><?= e($rb[0]) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- FRÜHERE TERMINE -->
                <div class="card">
                    <div class="ct"><?= icon('calendar') ?><?= e($t['sec_past']) ?></div>
                    <?php if (count($past) === 0): ?>
                        <div class="empty"><?= e($t['past_empty']) ?></div>
                    <?php else: ?>
                        <table class="hist">
                            <tr><th><?= e($t['col_date']) ?></th><th><?= e($t['col_time']) ?></th><th><?= e($t['col_status']) ?></th></tr>
                            <?php foreach ($past as $p): ?>
                                <?php $pb = $appt_badges[$p['status']] ?? [$p['status'], 'b-done']; ?>
                                <tr>
                                    <td><?= e(long_date($p['appointment_date'])) ?></td>
                                    <td><?= e(fmt_time($p['appointment_time'])) ?></td>
                                    <td><span class="badge <?= e($pb[1]) ?>"><?= e($pb[0]) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>
