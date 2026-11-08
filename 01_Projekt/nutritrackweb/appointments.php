<?php
// appointments.php
// Seite der Ärztin (Admin) für die TERMINE:
//  1) Anfragen zur Terminverschiebung beantworten (genehmigen oder ablehnen)
//  2) die nächsten Termine sehen, als erledigt markieren oder absagen
//  3) einen neuen Termin anlegen und einen bestehenden bearbeiten (Datum, Uhrzeit, Link und Notizen)
// Regeln der V1: Termine werden nie gelöscht (Absagen ändert nur den Status); ein Zeitkonflikt
// gibt nur eine WARNUNG (blockiert nicht); ein erledigter Termin kann wieder auf "geplant" zurück.
// Die Sprache kommt vom Login ($_SESSION['lang']).

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
// prepare() + execute() = Prepared Statement (vorbereitete Abfrage). Die ? sind Platzhalter; die Werte werden getrennt gesendet.
// So kann niemand SQL-Code einschleusen (Schutz gegen SQL-Injection).
$stmt = $pdo->prepare("SELECT role, status FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

if (!$me || $me['role'] != 1 || $me['status'] != 1) {
    header("Location: login.php?lang=" . $lang);
    exit;
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
        'role' => 'Nutritionist',
        'only_client' => 'Showing only appointments from %s', 'clear_filter' => 'Show all clients',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clients', 'nav_appointments' => 'Appointments',
        'nav_messages' => 'Messages', 'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos',
        'nav_payments' => 'Payments', 'nav_settings' => 'Profile Settings', 'logout' => 'Logout',
        'kpi_messages' => 'New Messages', 'kpi_appointments' => 'Upcoming Appointments',
        'kpi_payments' => 'Open Payments', 'kpi_pending' => 'Pending Approvals',
        'page_title' => 'Appointments', 'subtitle' => 'Answer reschedule requests and manage all appointments.',
        'sec_requests' => 'Reschedule requests', 'requests_empty' => 'No pending reschedule requests.',
        'current' => 'Current', 'requested' => 'Requested', 'reason' => 'Reason',
        'approve' => 'Approve', 'reject' => 'Reject',
        'approve_confirm' => 'Approve this request? The appointment will move to the new date and time.',
        'reject_confirm' => 'Reject this request? The appointment stays as it is.',
        'conflict' => 'Another appointment is already scheduled at this time.',
        'sec_upcoming' => 'Upcoming appointments', 'upcoming_empty' => 'No upcoming appointments.',
        'sec_past' => 'Past and closed appointments', 'past_empty' => 'Nothing here yet.',
        'col_date' => 'Date', 'col_time' => 'Time', 'col_client' => 'Client', 'col_status' => 'Status', 'col_actions' => 'Actions',
        'st_scheduled' => 'Scheduled', 'st_rescheduled' => 'Rescheduled', 'st_completed' => 'Completed', 'st_cancelled' => 'Cancelled',
        'btn_edit' => 'Edit', 'btn_complete' => 'Mark completed', 'btn_cancel' => 'Cancel', 'btn_reopen' => 'Back to scheduled',
        'cancel_confirm' => 'Cancel this appointment? It stays in the history.',
        'video' => 'Video call', 'no_link' => 'No link yet',
        'sec_new' => 'New appointment', 'sec_edit' => 'Edit appointment', 'btn_new' => 'New appointment',
        'client' => 'Client', 'client_ph' => 'Select a client', 'date' => 'Date', 'time' => 'Time',
        'link' => 'Video call link (optional)', 'notes' => 'Notes (optional)',
        'btn_create' => 'Create appointment', 'btn_save' => 'Save changes', 'btn_discard' => 'Discard',
        'done_created' => 'Appointment created.', 'done_updated' => 'Appointment updated.',
        'done_approved' => 'Request approved. The appointment was moved to the new date and time.',
        'done_rejected' => 'Request rejected. The appointment stays as it was.',
        'done_status' => 'Appointment status updated.',
        'warn_conflict' => 'Warning: there is already another appointment at this time.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_done' => 'This item was already handled or is no longer available.',
        'err_client' => 'Please select an approved client.',
        'err_date' => 'Please enter a valid date (today or later for new appointments).',
        'err_time' => 'Please enter a valid time.',
        'err_url' => 'The video link must start with http:// or https:// (maximum 500 characters).',
        'err_long' => 'The notes are too long (maximum 2000 characters).',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'role' => 'Nutricionista',
        'only_client' => 'Mostrando só as consultas de %s', 'clear_filter' => 'Mostrar todas as clientes',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clientes', 'nav_appointments' => 'Consultas',
        'nav_messages' => 'Mensagens', 'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições',
        'nav_payments' => 'Pagamentos', 'nav_settings' => 'Configurações do Perfil', 'logout' => 'Sair',
        'kpi_messages' => 'Novas Mensagens', 'kpi_appointments' => 'Próximas Consultas',
        'kpi_payments' => 'Pagamentos em Aberto', 'kpi_pending' => 'Aprovações Pendentes',
        'page_title' => 'Consultas', 'subtitle' => 'Responda pedidos de reagendamento e gerencie todas as consultas.',
        'sec_requests' => 'Pedidos de reagendamento', 'requests_empty' => 'Nenhum pedido de reagendamento pendente.',
        'current' => 'Atual', 'requested' => 'Solicitado', 'reason' => 'Motivo',
        'approve' => 'Aprovar', 'reject' => 'Recusar',
        'approve_confirm' => 'Aprovar este pedido? A consulta será movida para a nova data e horário.',
        'reject_confirm' => 'Recusar este pedido? A consulta continua como está.',
        'conflict' => 'Já existe outra consulta marcada neste horário.',
        'sec_upcoming' => 'Próximas consultas', 'upcoming_empty' => 'Nenhuma consulta futura.',
        'sec_past' => 'Consultas anteriores e encerradas', 'past_empty' => 'Nada por aqui ainda.',
        'col_date' => 'Data', 'col_time' => 'Horário', 'col_client' => 'Cliente', 'col_status' => 'Status', 'col_actions' => 'Ações',
        'st_scheduled' => 'Agendada', 'st_rescheduled' => 'Reagendada', 'st_completed' => 'Realizada', 'st_cancelled' => 'Cancelada',
        'btn_edit' => 'Editar', 'btn_complete' => 'Marcar como realizada', 'btn_cancel' => 'Cancelar', 'btn_reopen' => 'Voltar para agendada',
        'cancel_confirm' => 'Cancelar esta consulta? Ela continua no histórico.',
        'video' => 'Videochamada', 'no_link' => 'Sem link ainda',
        'sec_new' => 'Nova consulta', 'sec_edit' => 'Editar consulta', 'btn_new' => 'Nova consulta',
        'client' => 'Cliente', 'client_ph' => 'Selecione uma cliente', 'date' => 'Data', 'time' => 'Horário',
        'link' => 'Link da videochamada (opcional)', 'notes' => 'Notas (opcional)',
        'btn_create' => 'Criar consulta', 'btn_save' => 'Salvar alterações', 'btn_discard' => 'Descartar',
        'done_created' => 'Consulta criada.', 'done_updated' => 'Consulta atualizada.',
        'done_approved' => 'Pedido aprovado. A consulta foi movida para a nova data e horário.',
        'done_rejected' => 'Pedido recusado. A consulta continua como estava.',
        'done_status' => 'Status da consulta atualizado.',
        'warn_conflict' => 'Atenção: já existe outra consulta neste horário.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_done' => 'Este item já foi tratado ou não está mais disponível.',
        'err_client' => 'Selecione uma cliente aprovada.',
        'err_date' => 'Informe uma data válida (hoje ou depois, para novas consultas).',
        'err_time' => 'Informe um horário válido.',
        'err_url' => 'O link da videochamada deve começar com http:// ou https:// (máximo 500 caracteres).',
        'err_long' => 'As notas são muito longas (máximo 2000 caracteres).',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'role' => 'Ernährungsberaterin',
        'only_client' => 'Es werden nur Termine von %s angezeigt', 'clear_filter' => 'Alle Kunden anzeigen',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Kunden', 'nav_appointments' => 'Termine',
        'nav_messages' => 'Nachrichten', 'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos',
        'nav_payments' => 'Zahlungen', 'nav_settings' => 'Profileinstellungen', 'logout' => 'Abmelden',
        'kpi_messages' => 'Neue Nachrichten', 'kpi_appointments' => 'Bevorstehende Termine',
        'kpi_payments' => 'Offene Zahlungen', 'kpi_pending' => 'Ausstehende Freigaben',
        'page_title' => 'Termine', 'subtitle' => 'Umterminungsanfragen beantworten und alle Termine verwalten.',
        'sec_requests' => 'Umterminungsanfragen', 'requests_empty' => 'Keine offenen Umterminungsanfragen.',
        'current' => 'Aktuell', 'requested' => 'Gewünscht', 'reason' => 'Begründung',
        'approve' => 'Genehmigen', 'reject' => 'Ablehnen',
        'approve_confirm' => 'Diese Anfrage genehmigen? Der Termin wird auf das neue Datum und die neue Uhrzeit verschoben.',
        'reject_confirm' => 'Diese Anfrage ablehnen? Der Termin bleibt unverändert.',
        'conflict' => 'Zu dieser Zeit ist bereits ein anderer Termin eingetragen.',
        'sec_upcoming' => 'Bevorstehende Termine', 'upcoming_empty' => 'Keine bevorstehenden Termine.',
        'sec_past' => 'Vergangene und abgeschlossene Termine', 'past_empty' => 'Noch nichts vorhanden.',
        'col_date' => 'Datum', 'col_time' => 'Uhrzeit', 'col_client' => 'Kunde', 'col_status' => 'Status', 'col_actions' => 'Aktionen',
        'st_scheduled' => 'Geplant', 'st_rescheduled' => 'Verschoben', 'st_completed' => 'Abgeschlossen', 'st_cancelled' => 'Abgesagt',
        'btn_edit' => 'Bearbeiten', 'btn_complete' => 'Als abgeschlossen markieren', 'btn_cancel' => 'Absagen', 'btn_reopen' => 'Zurück auf geplant',
        'cancel_confirm' => 'Diesen Termin absagen? Er bleibt im Verlauf erhalten.',
        'video' => 'Videoanruf', 'no_link' => 'Noch kein Link',
        'sec_new' => 'Neuer Termin', 'sec_edit' => 'Termin bearbeiten', 'btn_new' => 'Neuer Termin',
        'client' => 'Kunde', 'client_ph' => 'Kunden auswählen', 'date' => 'Datum', 'time' => 'Uhrzeit',
        'link' => 'Link zum Videoanruf (optional)', 'notes' => 'Notizen (optional)',
        'btn_create' => 'Termin anlegen', 'btn_save' => 'Änderungen speichern', 'btn_discard' => 'Verwerfen',
        'done_created' => 'Termin angelegt.', 'done_updated' => 'Termin aktualisiert.',
        'done_approved' => 'Anfrage genehmigt. Der Termin wurde auf das neue Datum und die neue Uhrzeit verschoben.',
        'done_rejected' => 'Anfrage abgelehnt. Der Termin bleibt unverändert.',
        'done_status' => 'Terminstatus aktualisiert.',
        'warn_conflict' => 'Achtung: Zu dieser Zeit gibt es bereits einen anderen Termin.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_done' => 'Dieser Eintrag wurde bereits bearbeitet oder ist nicht mehr verfügbar.',
        'err_client' => 'Bitte wählen Sie einen freigegebenen Kunden.',
        'err_date' => 'Bitte geben Sie ein gültiges Datum ein (bei neuen Terminen heute oder später).',
        'err_time' => 'Bitte geben Sie eine gültige Uhrzeit ein.',
        'err_url' => 'Der Videolink muss mit http:// oder https:// beginnen (maximal 500 Zeichen).',
        'err_long' => 'Die Notizen sind zu lang (maximal 2000 Zeichen).',
        'err_other' => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.',
    ],
];
$t = $texts[$lang];

// Namen der Monate in jeder Sprache
$months = [
    'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    'pt' => ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
    'de' => ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
];

// BLOCK 4: kleine Funktionen, die beim Aufbau der Seite helfen

// schützt jeden Text, bevor er auf dem Bildschirm erscheint (gegen XSS)
function e($text) {
    // htmlspecialchars() macht aus Zeichen wie < und > harmlosen Text. So kann niemand Schadcode (JavaScript) in die Seite einschleusen (XSS-Schutz).
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// zeigt ein Symbol (icon) aus dem Ordner icons (z. B. icon('mail')); "w" macht das Symbol weiß
function icon($name, $extra = '') {
    return '<img class="ico ' . $extra . '" src="icons/' . $name . '.svg" alt="">';
}

function full_name($row) {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

function client_initials($row) {
    return mb_strtoupper(mb_substr($row['first_name'] ?? '', 0, 1) . mb_substr($row['last_name'] ?? '', 0, 1));
}

// kleiner Kreis mit den Initialen; die Farbe ändert sich je nach Nummer des Kunden
function avatar($row, $class = 'av') {
    $colors = [
        ['#F2C9A0', '#5B3A12'], ['#C0DD97', '#27500A'], ['#F4C0D1', '#72243E'], ['#B5D4F4', '#0C447C'],
        ['#FAC775', '#633806'], ['#CECBF6', '#3C3489'], ['#9FE1CB', '#085041'], ['#F5C4B3', '#712B13'],
    ];
    // wenn die Kundin ein Profilfoto hat, wird das Foto gezeigt; sonst die farbigen Initialen
    $photo = (string)($row['profile_photo_path'] ?? '');
    if ($photo !== '' && preg_match('#^uploads/profile/[A-Za-z0-9._-]+$#', $photo)) {
        return '<div class="' . $class . '" style="background:#E5E8E5 url(\'' . e($photo) . '\') center / cover no-repeat"></div>';
    }
    $c = $colors[(int)$row['client_id'] % count($colors)];
    return '<div class="' . $class . '" style="background:' . $c[0] . ';color:' . $c[1] . '">' . e(client_initials($row)) . '</div>';
}

// Datum mit Jahr: "Jun 5, 2026" (EN), "5. Jun 2026" (DE), "5 jun 2026" (PT)
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

// Uhrzeit ohne Sekunden ("09:00:00" wird zu "09:00")
function fmt_time($time) {
    return $time ? substr($time, 0, 5) : "";
}

// nur Links, die mit http:// oder https:// anfangen
function safe_url($url) {
    if ($url && preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return "";
}

// gültiges Datum im Format JJJJ-MM-TT?
function valid_date($text) {
    $d = DateTime::createFromFormat('Y-m-d', $text);
    return $d && $d->format('Y-m-d') === $text;
}

// gibt es einen ANDEREN Termin am selben Tag zur selben Uhrzeit? (dient nur für die Warnung)
function has_conflict($pdo, $date, $time, $ignore_id) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments
         WHERE appointment_date = ? AND appointment_time = ?
           AND status IN ('scheduled', 'rescheduled') AND appointment_id <> ?"
    );
    $stmt->execute([$date, substr($time, 0, 5) . ':00', (int)$ignore_id]);
    return (int)$stmt->fetchColumn() > 0;
}

// BLOCK 5: Sicherheits-Token des Formulars (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// BLOCK 6: Was passiert, wenn ein Formular abgeschickt wird
$error      = "";      // Fehler, der oben auf der Seite gezeigt wird
$form_error = "";      // Fehler, der im Formular zum Anlegen/Bearbeiten gezeigt wird
$from_dash = false;   // wird true, wenn die Anfrage zum Genehmigen/Ablehnen vom Dashboard kommt
// Filter nach Kunde: nur aktiv, wenn die Ärztin von der Abkürzung im Profil kommt (?client=ID)
$only_client = (int)($_GET['client'] ?? 0);
$f = ['appointment_id' => '0', 'client_id' => ($only_client > 0 ? (string)$only_client : ''), 'appt_date' => '', 'appt_time' => '', 'meeting_url' => '', 'notes' => ''];
$edit_id  = (int)($_GET['edit'] ?? 0);   // Nummer des Termins, der bearbeitet wird (0 = neu)
$open_form = isset($_GET['new']) || $edit_id > 0;

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form  = $_POST['form'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // die Anfrage kann vom Dashboard kommen (admin_dashboard.php): dann gehen wir danach dorthin zurück
    $from_dash = (($_POST['return'] ?? '') === 'dashboard');
    $back      = $from_dash ? 'admin_dashboard.php' : 'appointments.php';

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        $error = $t['err_invalid'];

    } elseif ($form === 'request_decision') {
        // ---------- 6A: EINE ANFRAGE ZUR TERMINVERSCHIEBUNG GENEHMIGEN ODER ABLEHNEN ----------
        // filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
        $request_id = filter_var($_POST['request_id'] ?? '', FILTER_VALIDATE_INT);
        $decision   = $_POST['decision'] ?? '';

        if ($request_id === false || $request_id < 1 || !in_array($decision, ['approve', 'reject'], true)) {
            $error = $t['err_invalid'];
        } else {
            try {
                $stmt = $pdo->prepare(
                    "SELECT r.requested_date, r.requested_time, r.appointments_appointment_id AS appt_id
                     FROM reschedule_requests r WHERE r.request_id = ? AND r.status = 0"
                );
                $stmt->execute([$request_id]);
                $req = $stmt->fetch();

                if (!$req) {
                    $error = $t['err_done'];

                } elseif ($decision === 'reject') {
                    // ablehnen: nur die Anfrage ändert sich (2 = Rejected); der Termin bleibt gleich
                    $stmt = $pdo->prepare("UPDATE reschedule_requests SET status = 2 WHERE request_id = ? AND status = 0");
                    $stmt->execute([$request_id]);
                    header("Location: " . $back . "?done=rejected");
                    exit;

                } else {
                    // genehmigen: die Anfrage wird Approved (1) und der Termin bekommt das neue Datum/die neue Uhrzeit (rescheduled)
                    // beginTransaction(): ab hier gehört alles zusammen (Transaktion). Entweder wird ALLES gespeichert (commit)
                    // oder bei einem Fehler NICHTS (rollBack macht alles seit hier rückgängig).
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("UPDATE reschedule_requests SET status = 1 WHERE request_id = ? AND status = 0");
                    $stmt->execute([$request_id]);
                    $ok = $stmt->rowCount() > 0;

                    if ($ok) {
                        $stmt = $pdo->prepare(
                            "UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'rescheduled'
                             WHERE appointment_id = ? AND status IN ('scheduled', 'rescheduled')"
                        );
                        $stmt->execute([$req['requested_date'], $req['requested_time'], $req['appt_id']]);
                        $ok = $stmt->rowCount() > 0;
                    }

                    if (!$ok) {
                        $pdo->rollBack();
                        $error = $t['err_done'];
                    } else {
                        $pdo->commit();
                        $warn = has_conflict($pdo, $req['requested_date'], $req['requested_time'], $req['appt_id']);
                        header("Location: " . $back . "?done=approved" . ($warn ? "&warn=1" : ""));
                        exit;
                    }
                }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = $t['err_other'];
            }
        }

    } elseif ($form === 'set_status') {
        // ---------- 6B: ERLEDIGT / ABGESAGT / ZURÜCK AUF GEPLANT ----------
        $appt_id    = filter_var($_POST['appointment_id'] ?? '', FILTER_VALIDATE_INT);
        $new_status = $_POST['new_status'] ?? '';

        // von wo nach wo man wechseln darf (wir löschen nie etwas)
        $allowed = [
            'completed' => ['scheduled', 'rescheduled'],
            'cancelled' => ['scheduled', 'rescheduled'],
            'scheduled' => ['completed'],
        ];

        if ($appt_id === false || $appt_id < 1 || !isset($allowed[$new_status])) {
            $error = $t['err_invalid'];
        } else {
            try {
                $marks = implode(',', array_fill(0, count($allowed[$new_status]), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE appointments SET status = ? WHERE appointment_id = ? AND status IN ($marks)"
                );
                $stmt->execute(array_merge([$new_status, $appt_id], $allowed[$new_status]));

                if ($stmt->rowCount() === 0) {
                    $error = $t['err_done'];
                } else {
                    $warn = false;
                    if ($new_status === 'scheduled') {
                        // zurück auf geplant: warnt, wenn es mit einem anderen Termin zusammenfällt
                        $stmt = $pdo->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE appointment_id = ?");
                        $stmt->execute([$appt_id]);
                        $row = $stmt->fetch();
                        $warn = $row && has_conflict($pdo, $row['appointment_date'], $row['appointment_time'], $appt_id);
                    }
                    header("Location: appointments.php?done=status" . ($warn ? "&warn=1" : ""));
                    exit;
                }
            } catch (PDOException $ex) {
                $error = $t['err_other'];
            }
        }

    } elseif ($form === 'save_appointment') {
        // ---------- 6C: EINEN TERMIN ANLEGEN ODER BEARBEITEN ----------
        foreach ($f as $key => $default) {
            $f[$key] = trim($_POST[$key] ?? $default);
        }
        $edit_id   = (int)$f['appointment_id'];     // 0 = neuer Termin
        $open_form = true;
        $is_new    = ($edit_id === 0);

        $target = null;
        if (!$is_new) {
            $stmt = $pdo->prepare("SELECT appointment_id, clients_client_id FROM appointments WHERE appointment_id = ?");
            $stmt->execute([$edit_id]);
            $target = $stmt->fetch();
        }

        $client_ok = false;
        if ($is_new) {
            $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE client_id = ? AND approval_status = 1");
            $stmt->execute([(int)$f['client_id']]);
            $client_ok = (bool)$stmt->fetch();
        }

        if (!$is_new && !$target) {
            $error = $t['err_done'];
        } elseif ($is_new && !$client_ok) {
            $form_error = $t['err_client'];
        } elseif (!valid_date($f['appt_date']) || ($is_new && $f['appt_date'] < date('Y-m-d'))) {
            $form_error = $t['err_date'];
        } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $f['appt_time'])) {
            $form_error = $t['err_time'];
        } elseif ($f['meeting_url'] !== '' && (safe_url($f['meeting_url']) === '' || mb_strlen($f['meeting_url']) > 500)) {
            $form_error = $t['err_url'];
        } elseif (mb_strlen($f['notes']) > 2000) {
            $form_error = $t['err_long'];
        } else {
            try {
                $url   = $f['meeting_url'] !== '' ? $f['meeting_url'] : null;
                $notes = $f['notes'] !== '' ? $f['notes'] : null;

                if ($is_new) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO appointments (appointment_date, appointment_time, meeting_url, notes, status, clients_client_id)
                         VALUES (?, ?, ?, ?, 'scheduled', ?)"
                    );
                    $stmt->execute([$f['appt_date'], $f['appt_time'] . ':00', $url, $notes, (int)$f['client_id']]);
                    $saved_id = (int)$pdo->lastInsertId();   // damit die Konfliktwarnung den eigenen Termin nicht mitzählt
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE appointments SET appointment_date = ?, appointment_time = ?, meeting_url = ?, notes = ?
                         WHERE appointment_id = ?"
                    );
                    $stmt->execute([$f['appt_date'], $f['appt_time'] . ':00', $url, $notes, $edit_id]);
                    $saved_id = $edit_id;
                }
                $warn = has_conflict($pdo, $f['appt_date'], $f['appt_time'], $saved_id);
                header("Location: appointments.php?done=" . ($is_new ? "created" : "updated") . ($warn ? "&warn=1" : ""));
                exit;
            } catch (PDOException $ex) {
                $form_error = $t['err_other'];
            }
        }

    } else {
        $error = $t['err_invalid'];
    }
}

// wenn die Anfrage vom Dashboard kam und ein Fehler passiert ist (z. B. schon beantwortet), geht es mit Hinweis zum Dashboard zurück
if ($error !== "" && $from_dash) {
    header("Location: admin_dashboard.php?done=error");
    exit;
}

// Erfolgsmeldung (kommt von der Weiterleitung) und Warnung bei Zeitkonflikt
$success = "";
$done_messages = [
    'created' => 'done_created', 'updated' => 'done_updated', 'approved' => 'done_approved',
    'rejected' => 'done_rejected', 'status' => 'done_status',
];
if (isset($_GET['done']) && isset($done_messages[$_GET['done']])) {
    $success = $t[$done_messages[$_GET['done']]];
}
$warning = isset($_GET['warn']) && $success !== "" ? $t['warn_conflict'] : "";

// BLOCK 7: Daten der Seite

// 7-0: der Kunde aus dem Filter (wenn er nicht existiert, wird der Filter ignoriert) und das Stück SQL, das filtert
$only_row = null;
if ($only_client > 0) {
    $stmt = $pdo->prepare("SELECT client_id, first_name, last_name, profile_photo_path FROM clients WHERE client_id = ?");
    $stmt->execute([$only_client]);
    $only_row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$cf = $only_row ? " AND a.clients_client_id = " . (int)$only_row['client_id'] : "";

// 7a: offene Anfragen zur Terminverschiebung (status 0), vom ältesten zum neuesten
$requests = $pdo->query(
    "SELECT r.request_id, r.requested_date, r.requested_time, r.request_reason,
            a.appointment_id, a.appointment_date, a.appointment_time,
            c.client_id, c.first_name, c.last_name, c.profile_photo_path
     FROM reschedule_requests r
     JOIN appointments a ON a.appointment_id = r.appointments_appointment_id
     JOIN clients c ON c.client_id = a.clients_client_id
     WHERE r.status = 0" . $cf . "
     ORDER BY r.request_id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($requests as $i => $r) {
    $requests[$i]['conflict'] = has_conflict($pdo, $r['requested_date'], $r['requested_time'], $r['appointment_id']);
}

// 7b: nächste Termine (scheduled oder rescheduled, ab heute)
$upcoming = $pdo->query(
    "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.meeting_url, a.notes, a.status,
            c.client_id, c.first_name, c.last_name, c.profile_photo_path
     FROM appointments a
     JOIN clients c ON c.client_id = a.clients_client_id
     WHERE a.status IN ('scheduled', 'rescheduled') AND a.appointment_date >= CURDATE()" . $cf . "
     ORDER BY a.appointment_date, a.appointment_time LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

// 7c: frühere und beendete Termine (schon vorbei, oder erledigt/abgesagt)
$past = $pdo->query(
    "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.meeting_url, a.notes, a.status,
            c.client_id, c.first_name, c.last_name, c.profile_photo_path
     FROM appointments a
     JOIN clients c ON c.client_id = a.clients_client_id
     WHERE (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled'))" . $cf . "
     ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 30"
)->fetchAll(PDO::FETCH_ASSOC);

// 7d: beim Bearbeiten wird der Termin geladen (nur beim ersten Mal; nach einem Fehler gilt, was eingegeben wurde)
$edit_row = null;
if ($edit_id > 0) {
    $stmt = $pdo->prepare(
        "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.meeting_url, a.notes,
                c.client_id, c.first_name, c.last_name, c.profile_photo_path
         FROM appointments a JOIN clients c ON c.client_id = a.clients_client_id
         WHERE a.appointment_id = ?"
    );
    $stmt->execute([$edit_id]);
    $edit_row = $stmt->fetch();
    if (!$edit_row) { $edit_id = 0; $open_form = isset($_GET['new']); }
}
if ($edit_row && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $f['appt_date']   = $edit_row['appointment_date'];
    $f['appt_time']   = fmt_time($edit_row['appointment_time']);
    $f['meeting_url'] = $edit_row['meeting_url'] ?? '';
    $f['notes']       = $edit_row['notes'] ?? '';
}

// 7e: freigegebene Kunden (zur Auswahl beim neuen Termin)
$clients = $pdo->query(
    "SELECT client_id, first_name, last_name FROM clients WHERE approval_status = 1 ORDER BY first_name, last_name"
)->fetchAll(PDO::FETCH_ASSOC);

// 7f: die 4 Zahlen im Kopf
$count_messages = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE status = 0")->fetchColumn();
$count_appointments = (int)$pdo->query(
    "SELECT COUNT(*) FROM appointments
     WHERE status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()"
)->fetchColumn();
$count_payments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 0")->fetchColumn();
$count_pending  = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE approval_status = 0")->fetchColumn();

// BLOCK 8: Daten der Ärztin (Name, Logo, Foto und die 4 Farben)
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

$initials = "";
foreach (explode(" ", trim($name)) as $word) {
    if ($word === "" || strpos($word, ".") !== false) { continue; }
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}
$initials = mb_substr($initials, 0, 2);

// Etiketten (Labels) für den Status: [Text, Farbklasse]
$status_badges = [
    'scheduled'   => [$t['st_scheduled'],   'b-ok'],
    'rescheduled' => [$t['st_rescheduled'], 'b-info'],
    'completed'   => [$t['st_completed'],   'b-gray'],
    'cancelled'   => [$t['st_cancelled'],   'b-bad'],
];

// Einträge des Seitenmenüs: [Datei, Symbol, Text]
$current = "appointments.php";
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

// kleine Hilfe, um die Zeilen der Tabellen zu bauen (Aktionsknöpfe jedes Termins)
// $a = ein Termin; $with_edit = zeigt den Knopf "Bearbeiten"
function action_buttons($a, $with_edit = true) {
    global $t;
    $out = '';
    if ($with_edit) {
        $out .= '<a class="btn sm" href="appointments.php?edit=' . (int)$a['appointment_id'] . '#form">' . e($t['btn_edit']) . '</a> ';
    }
    $buttons = [];
    if (in_array($a['status'], ['scheduled', 'rescheduled'], true)) {
        $buttons[] = ['completed', $t['btn_complete'], ''];
        $buttons[] = ['cancelled', $t['btn_cancel'], $t['cancel_confirm']];
    } elseif ($a['status'] === 'completed') {
        $buttons[] = ['scheduled', $t['btn_reopen'], ''];
    }
    foreach ($buttons as $b) {
        $onsubmit = $b[2] !== '' ? ' onsubmit="return confirm(' . e(json_encode($b[2])) . ');"' : '';
        $out .= '<form method="post" action="appointments.php" class="inl"' . $onsubmit . '>'
              . '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">'
              . '<input type="hidden" name="form" value="set_status">'
              . '<input type="hidden" name="appointment_id" value="' . (int)$a['appointment_id'] . '">'
              . '<input type="hidden" name="new_status" value="' . e($b[0]) . '">'
              . '<button type="submit" class="btn sm' . ($b[0] === 'cancelled' ? ' danger' : '') . '">' . e($b[1]) . '</button></form> ';
    }
    return $out;
}
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

        /* ICONS (Symbole): dieselbe Datei passt für hellen und dunklen Hintergrund (Filter) */
        .ico { width: 18px; height: 18px; vertical-align: middle; filter: brightness(0); opacity: .6; }
        .side .ico, .top .ico { filter: brightness(0) invert(1); opacity: .95; }
        .nav a.on .ico { filter: brightness(0); opacity: .75; }
        .top .ico { width: 22px; height: 22px; }

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
        .kpi { background: rgba(255,255,255,.14); color: #fff; border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; gap: 12px; }
        .kpi b { display: block; font-size: 24px; line-height: 1; }
        .kpi span { display: block; font-size: 12px; margin-top: 3px; }
        .kpi.warm { background: var(--accent); }

        /* INHALT */
        .main { padding: 20px; }
        .hd {
            background: linear-gradient(rgba(255,255,255,.65), rgba(255,255,255,.65)), var(--secondary);
            border: 1px solid var(--secondary); border-radius: 10px; padding: 12px 16px; margin-bottom: 14px;
        }
        .crumb { font-size: 12px; color: #666; margin-bottom: 8px; }
        .crumb a { color: var(--primary); }
        .hrow { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .av-lg { width: 48px; height: 48px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: bold; }
        .hname { font-size: 18px; font-weight: bold; margin-right: 8px; }
        .hsub { font-size: 12px; color: #666; }
        .hnext { font-size: 12px; color: #666; text-align: right; margin-left: auto; line-height: 1.5; }
        .hnext a { color: var(--accent); font-weight: bold; }

        .bd { font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 99px; }
        .b-pen  { background: var(--accent); color: #fff; }
        .b-ok   { background: #DDEBD2; color: #27500A; }
        .b-bad  { background: #F9D6D6; color: #791F1F; }
        .b-info { background: #FBEFD9; color: #6B4A12; }

        .panel { background: #fff; border: 1px solid #D8DDD8; border-radius: 10px; padding: 16px; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); gap: 14px; align-items: start; }
        .col { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
        .card { border: 1px solid #D8DDD8; border-radius: 10px; padding: 12px; background: #fff; min-width: 0; }
        .card.warm { background: #FBEFD9; border-color: #E8CFA0; }
        .ct { display: flex; align-items: center; gap: 7px; font-size: 14px; font-weight: bold; color: var(--primary); margin-bottom: 8px; }

        .kv { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px solid #E5E8E5; font-size: 13px; }
        .kv:last-child { border-bottom: none; }
        .kv span:first-child { color: #666; flex: none; }
        .kv span:last-child { text-align: right; font-weight: bold; min-width: 0; word-break: break-word; }

        .blood { display: flex; align-items: center; gap: 10px; }
        .blood .tx { flex: 1; min-width: 0; font-size: 13px; }
        .blood .sb { font-size: 12px; color: #666; }
        .btn-off { font-size: 12px; padding: 5px 12px; border-radius: 8px; border: 1px solid #C9C9C9; color: #999; background: #fff; display: inline-flex; align-items: center; gap: 5px; cursor: not-allowed; }

        /* Auswahl der Entscheidung (3 Knöpfe) */
        .seg { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; margin-bottom: 10px; }
        .seg label { margin: 0; cursor: pointer; }
        .seg input { position: absolute; opacity: 0; pointer-events: none; }
        .sg { border: 1px solid #D8DDD8; border-radius: 8px; padding: 8px 4px; text-align: center; font-size: 12px; color: #555; background: #F5F7F5; }
        .sg .ico { display: block; margin: 0 auto 3px; width: 18px; height: 18px; }
        .seg input:checked + .sg { background: var(--primary); color: #fff; border-color: var(--primary); font-weight: bold; }
        .seg input:checked + .sg .ico { filter: brightness(0) invert(1); opacity: 1; }
        .seg input.rej:checked + .sg { background: #791F1F; border-color: #791F1F; }
        .seg input:focus-visible + .sg { outline: 2px solid var(--accent); }

        .dsec[hidden] { display: none; }
        .help { font-size: 12px; color: #666; margin-bottom: 10px; line-height: 1.4; }

        label { display: block; font-size: 12px; color: #555; margin-bottom: 3px; }
        .row2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-bottom: 8px; }
        .field { position: relative; }
        .field input, .field select, .field textarea {
            width: 100%; padding: 8px 10px; font-size: 13px; font-family: inherit;
            border: 1px solid #D8DDD8; border-radius: 8px; background: #fff; color: #2B2A24;
        }
        .field.has-icon input { padding-left: 36px; }
        .field textarea { resize: vertical; min-height: 64px; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: 2px solid var(--accent); border-color: var(--accent); }
        .field .icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; opacity: .6; pointer-events: none; filter: brightness(0); }

        .go {
            width: 100%; padding: 10px; margin-top: 10px; font-size: 13px; font-weight: bold;
            border-radius: 8px; border: 1px solid var(--primary); cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            background: var(--primary); color: #fff;
        }
        .go .ico { filter: brightness(0) invert(1); opacity: 1; }
        .go.warm { background: var(--accent); border-color: var(--accent); }
        .go.danger { background: #fff; color: #791F1F; border-color: #791F1F; }
        .go.danger .ico { filter: brightness(0) saturate(100%) invert(15%) sepia(60%) saturate(3000%) hue-rotate(345deg); }
        .go:hover { opacity: .92; }

        .warn { display: flex; gap: 7px; align-items: flex-start; background: #F9D6D6; color: #791F1F; border-radius: 8px; padding: 8px 10px; margin-top: 10px; font-size: 12px; line-height: 1.4; }
        .warn .ico { flex: none; }
        .error { background: #F9D6D6; color: #791F1F; font-size: 13px; padding: 9px 11px; border-radius: 8px; margin-bottom: 12px; }
        .success { background: #DDEBD2; color: #27500A; font-size: 13px; padding: 9px 11px; border-radius: 8px; margin-bottom: 14px; }
        .lastnote { background: #FBEFD9; border: 1px solid #E8CFA0; border-radius: 8px; padding: 8px 10px; font-size: 12px; margin-bottom: 10px; white-space: pre-line; }
        .lastnote b { display: block; margin-bottom: 3px; color: #6B4A12; }
        .final { font-size: 13px; line-height: 1.5; }
        .final .back { display: inline-block; margin-top: 10px; color: var(--accent); font-weight: bold; }


        .title-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .title-row h1 { margin: 0; font-size: 20px; color: var(--primary); }
        .title-row p { margin: 2px 0 0; font-size: 13px; color: #666; }
        .only { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 13px; background: #fff; border: 1px solid #D8DDD8; border-radius: 10px; padding: 8px 12px; margin-bottom: 12px; }
        .only .btn { margin-left: auto; }
        .main .card { margin-bottom: 14px; padding: 14px; }
        .empty { font-size: 13px; color: #888; padding: 4px 0; }
        .muted { font-size: 12px; color: #999; }
        .av { width: 34px; height: 34px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; }
        .av.sm { width: 26px; height: 26px; font-size: 10px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--primary); color: var(--primary); background: #fff; cursor: pointer; font-family: inherit; }
        .btn.fill { background: var(--primary); color: #fff; }
        .btn.fill .ico { filter: brightness(0) invert(1); opacity: 1; }
        .btn.sm { font-size: 12px; padding: 5px 10px; }
        .btn.danger { color: #791F1F; border-color: #791F1F; }
        .btn:hover { opacity: .92; }
        form.inl { display: inline-block; margin: 0; }
        .req { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid #E5E8E5; flex-wrap: wrap; }
        .req:last-child { border-bottom: none; }
        .req-tx { flex: 1; min-width: 220px; }
        .req-tx .nm { font-size: 14px; font-weight: bold; }
        .req-tx .sb { font-size: 12.5px; color: #555; margin-top: 2px; }
        .req-tx .reason { white-space: pre-line; }
        .req-btns { display: flex; gap: 6px; flex-wrap: wrap; }
        .conflict, .warning { background: #FBEFD9; color: #6B4A12; border: 1px solid #E8CFA0; border-radius: 8px; font-size: 12px; padding: 6px 9px; margin-top: 6px; }
        .warning { font-size: 13px; padding: 9px 11px; margin: 0 0 14px; }
        .scroll { overflow-x: auto; }
        .tb { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tb th { text-align: left; color: #777; font-weight: normal; padding: 5px 8px 5px 0; border-bottom: 1px solid #E5E8E5; white-space: nowrap; }
        .tb td { padding: 7px 8px 7px 0; border-bottom: 1px solid #F0F2F0; vertical-align: middle; }
        .plink { color: var(--primary); font-weight: bold; }
        .plink:hover { text-decoration: underline; }
        .tb .cl { display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .tb .acts { white-space: nowrap; }
        .tb .acts .btn { margin: 2px 0; }
        .lk .ico { width: 20px; height: 20px; }
        .b-gray { background: #E4E1D8; color: #444441; }
        .row2.inner { margin-bottom: 0; }
        .form-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .field input[readonly] { background: #F5F7F5; color: #555; }
        .field textarea { resize: vertical; min-height: 64px; }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
            .side { grid-row: auto; }
            .top { grid-template-columns: 1fr 1fr; }
            .row2 { grid-template-columns: 1fr; }
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
        <div class="kpi warm"><?= icon('calendar') ?><div><b><?= $count_appointments ?></b><span><?= e($t['kpi_appointments']) ?></span></div></div>
        <div class="kpi"><?= icon('coin') ?><div><b><?= $count_payments ?></b><span><?= e($t['kpi_payments']) ?></span></div></div>
        <div class="kpi"><?= icon('user-check') ?><div><b><?= $count_pending ?></b><span><?= e($t['kpi_pending']) ?></span></div></div>
    </header>

    <!-- INHALT -->
    <main class="main">

        <?php if ($success !== ""): ?>
            <div class="success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($warning !== ""): ?>
            <div class="warning"><?= e($warning) ?></div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="title-row">
            <div>
                <h1><?= e($t['page_title']) ?></h1>
                <p><?= e($t['subtitle']) ?></p>
            </div>
            <a class="btn fill" href="appointments.php?new=1<?= $only_row ? '&amp;client=' . (int)$only_row['client_id'] : '' ?>#form"><?= icon('plus', 'w') ?><?= e($t['btn_new']) ?></a>
        </div>

        <?php if ($only_row): ?>
            <div class="only">
                <?= avatar($only_row, 'av sm') ?>
                <span><?= e(sprintf($t['only_client'], full_name($only_row))) ?></span>
                <a class="btn sm" href="appointments.php"><?= e($t['clear_filter']) ?></a>
            </div>
        <?php endif; ?>

        <!-- ANFRAGEN ZUR TERMINVERSCHIEBUNG -->
        <div class="card">
            <div class="ct"><?= icon('calendar-time') ?><?= e($t['sec_requests']) ?></div>

            <?php if (count($requests) === 0): ?>
                <div class="empty"><?= e($t['requests_empty']) ?></div>
            <?php endif; ?>

            <?php foreach ($requests as $r): ?>
                <div class="req">
                    <?= avatar($r) ?>
                    <div class="req-tx">
                        <div class="nm"><a class="plink" href="clients.php?id=<?= (int)$r['client_id'] ?>"><?= e(full_name($r)) ?></a></div>
                        <div class="sb">
                            <?= e($t['current']) ?>: <?= e(long_date($r['appointment_date'])) ?>, <?= e(fmt_time($r['appointment_time'])) ?>
                            &rarr; <b><?= e($t['requested']) ?>: <?= e(long_date($r['requested_date'])) ?>, <?= e(fmt_time($r['requested_time'])) ?></b>
                        </div>
                        <?php if (trim($r['request_reason'] ?? '') !== ''): ?>
                            <div class="sb reason"><?= e($t['reason']) ?>: <?= e($r['request_reason']) ?></div>
                        <?php endif; ?>
                        <?php if ($r['conflict']): ?>
                            <div class="conflict"><?= e($t['conflict']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="req-btns">
                        <form method="post" action="appointments.php" class="inl" onsubmit="return confirm(<?= e(json_encode($t['approve_confirm'])) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="form" value="request_decision">
                            <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="btn sm fill"><?= icon('check', 'w') ?><?= e($t['approve']) ?></button>
                        </form>
                        <form method="post" action="appointments.php" class="inl" onsubmit="return confirm(<?= e(json_encode($t['reject_confirm'])) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="form" value="request_decision">
                            <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                            <input type="hidden" name="decision" value="reject">
                            <button type="submit" class="btn sm danger"><?= icon('x') ?><?= e($t['reject']) ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- NÄCHSTE TERMINE -->
        <div class="card">
            <div class="ct"><?= icon('calendar') ?><?= e($t['sec_upcoming']) ?></div>
            <?php if (count($upcoming) === 0): ?>
                <div class="empty"><?= e($t['upcoming_empty']) ?></div>
            <?php else: ?>
                <div class="scroll">
                <table class="tb">
                    <tr>
                        <th><?= e($t['col_date']) ?></th><th><?= e($t['col_time']) ?></th><th><?= e($t['col_client']) ?></th>
                        <th><?= e($t['col_status']) ?></th><th><?= e($t['video']) ?></th><th><?= e($t['col_actions']) ?></th>
                    </tr>
                    <?php foreach ($upcoming as $a): ?>
                        <?php $b = $status_badges[$a['status']] ?? [$a['status'], 'b-gray']; $u = safe_url($a['meeting_url']); ?>
                        <tr>
                            <td><?= e(long_date($a['appointment_date'])) ?></td>
                            <td><?= e(fmt_time($a['appointment_time'])) ?></td>
                            <td><div class="cl"><?= avatar($a, 'av sm') ?><a class="plink" href="clients.php?id=<?= (int)$a['client_id'] ?>"><?= e(full_name($a)) ?></a></div></td>
                            <td><span class="bd <?= e($b[1]) ?>"><?= e($b[0]) ?></span></td>
                            <td><?php if ($u !== ''): ?><a class="lk" href="<?= e($u) ?>" target="_blank" rel="noopener"><?= icon('video') ?></a><?php else: ?><span class="muted"><?= e($t['no_link']) ?></span><?php endif; ?></td>
                            <td class="acts"><?= action_buttons($a) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ANLEGEN / BEARBEITEN (erscheint nur, wenn man oben auf "+ Neuer Termin" oder auf Bearbeiten klickt) -->
        <?php if ($open_form): ?>
        <div class="card" id="form">
            <div class="ct"><?= icon('calendar') ?><?= e($edit_id > 0 ? $t['sec_edit'] : $t['sec_new']) ?></div>

            <div>
                <?php if ($form_error !== ""): ?>
                    <div class="error"><?= e($form_error) ?></div>
                <?php endif; ?>
                <form method="post" action="appointments.php#form">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="form" value="save_appointment">
                    <input type="hidden" name="appointment_id" value="<?= (int)$edit_id ?>">

                    <div class="row2">
                        <div>
                            <label for="client_id"><?= e($t['client']) ?></label>
                            <?php if ($edit_id > 0 && $edit_row): ?>
                                <div class="field"><input type="text" value="<?= e(full_name($edit_row)) ?>" readonly></div>
                            <?php else: ?>
                                <div class="field">
                                    <select id="client_id" name="client_id" required>
                                        <option value=""><?= e($t['client_ph']) ?></option>
                                        <?php foreach ($clients as $c): ?>
                                            <option value="<?= (int)$c['client_id'] ?>" <?= (string)$c['client_id'] === $f['client_id'] ? 'selected' : '' ?>><?= e(full_name($c)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="row2 inner">
                            <div>
                                <label for="appt_date"><?= e($t['date']) ?></label>
                                <div class="field"><input type="date" id="appt_date" name="appt_date" <?= $edit_id > 0 ? '' : 'min="' . date('Y-m-d') . '"' ?> value="<?= e($f['appt_date']) ?>" required></div>
                            </div>
                            <div>
                                <label for="appt_time"><?= e($t['time']) ?></label>
                                <div class="field"><input type="time" id="appt_time" name="appt_time" value="<?= e($f['appt_time']) ?>" required></div>
                            </div>
                        </div>
                    </div>
                    <div style="margin-bottom:8px">
                        <label for="meeting_url"><?= e($t['link']) ?></label>
                        <div class="field"><input type="url" id="meeting_url" name="meeting_url" maxlength="500" placeholder="https://meet.google.com/..." value="<?= e($f['meeting_url']) ?>"></div>
                    </div>
                    <div style="margin-bottom:8px">
                        <label for="notes"><?= e($t['notes']) ?></label>
                        <div class="field"><textarea id="notes" name="notes" rows="3" maxlength="2000"><?= e($f['notes']) ?></textarea></div>
                    </div>
                    <div class="form-btns">
                        <button type="submit" class="btn fill"><?= icon('check', 'w') ?><?= e($edit_id > 0 ? $t['btn_save'] : $t['btn_create']) ?></button>
                        <a class="btn" href="appointments.php"><?= e($t['btn_discard']) ?></a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- FRÜHERE UND BEENDETE TERMINE -->
        <div class="card">
            <div class="ct"><?= icon('calendar') ?><?= e($t['sec_past']) ?></div>
            <?php if (count($past) === 0): ?>
                <div class="empty"><?= e($t['past_empty']) ?></div>
            <?php else: ?>
                <div class="scroll">
                <table class="tb">
                    <tr>
                        <th><?= e($t['col_date']) ?></th><th><?= e($t['col_time']) ?></th><th><?= e($t['col_client']) ?></th>
                        <th><?= e($t['col_status']) ?></th><th><?= e($t['col_actions']) ?></th>
                    </tr>
                    <?php foreach ($past as $a): ?>
                        <?php $b = $status_badges[$a['status']] ?? [$a['status'], 'b-gray']; ?>
                        <tr>
                            <td><?= e(long_date($a['appointment_date'])) ?></td>
                            <td><?= e(fmt_time($a['appointment_time'])) ?></td>
                            <td><div class="cl"><?= avatar($a, 'av sm') ?><a class="plink" href="clients.php?id=<?= (int)$a['client_id'] ?>"><?= e(full_name($a)) ?></a></div></td>
                            <td><span class="bd <?= e($b[1]) ?>"><?= e($b[0]) ?></span></td>
                            <td class="acts"><?= action_buttons($a) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

</body>
</html>
