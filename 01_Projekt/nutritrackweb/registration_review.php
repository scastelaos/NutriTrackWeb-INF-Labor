<?php
// registration_review.php?id=NUMMER
// Seite, auf der die Ärztin (Admin) die Registrierung eines Kunden prüft und entscheidet:
//  Approve (genehmigen + Monatsgebühr + erster Termin), More info (weitere Informationen) oder Reject (ablehnen).
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

// BLOCK 3: Welcher Kunde? (?id=NUMMER)
$client_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT c.client_id, c.first_name, c.last_name, c.profile_photo_path, c.phone, c.country, c.city,
            c.date_of_birth, c.preferred_language, c.goal, c.approval_status,
            c.registration_date, c.review_note, u.email
     FROM clients c
     JOIN users u ON u.user_id = c.users_user_id
     WHERE c.client_id = ?"
);
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    // ungültige oder nicht existierende id: zurück zum Dashboard
    header("Location: admin_dashboard.php");
    exit;
}

// BLOCK 4: Texte der Seite, ein Satz für jede Sprache
$texts = [
    'en' => [
        'role' => 'Nutritionist',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clients', 'nav_appointments' => 'Appointments',
        'nav_messages' => 'Messages', 'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos',
        'nav_payments' => 'Payments', 'nav_settings' => 'Profile Settings', 'logout' => 'Logout',
        'kpi_messages' => 'New Messages', 'kpi_appointments' => 'Upcoming Appointments',
        'kpi_payments' => 'Open Payments', 'kpi_pending' => 'Pending Approvals',
        'today' => 'Today', 'yesterday' => 'Yesterday',
        'msgs_link' => 'Messages from this client', 'crumb' => 'Registration review', 'registered' => 'Registered', 'pending_count' => '%d pending', 'next' => 'Next',
        'st0' => 'Pending', 'st1' => 'Approved', 'st2' => 'Rejected', 'st3' => 'More info requested',
        'personal' => 'Personal data', 'phone' => 'Phone', 'dob' => 'Date of birth',
        'country_city' => 'Country, city', 'language' => 'Language',
        'followup' => 'Initial follow-up data', 'weight' => 'Weight', 'height' => 'Height', 'goal' => 'Goal', 'email' => 'Email',
        'blood' => 'Blood test', 'blood_none' => 'No file available yet.', 'blood_open' => 'Open', 'blood_soon' => 'Available soon',
        'decision' => 'Your decision', 'd_approve' => 'Approve', 'd_more' => 'More info', 'd_reject' => 'Reject',
        'approve_help' => "Set up the client's plan. The account becomes active as soon as you approve.",
        'fee' => 'Monthly fee', 'currency' => 'Currency', 'first_appt' => 'First appointment', 'time' => 'Time',
        'video' => 'Video call link (optional)', 'initial_notes' => 'Initial notes',
        'initial_notes_ph' => 'Notes about the start of the follow-up', 'btn_approve' => 'Approve and activate account',
        'more_help' => 'The client will see your request in the system and can reply with documents or answers.',
        'more_label' => 'Message to the client', 'more_ph' => 'What do you need from the client?', 'btn_more' => 'Send request',
        'reject_help' => 'The registration will be declined and the client will not get access to the platform.',
        'reject_label' => 'Reason (optional)', 'reject_ph' => 'Write a reason for the client',
        'reject_warn' => 'Please confirm before rejecting this registration.',
        'reject_confirm' => 'Do you really want to reject this registration?', 'btn_reject' => 'Reject registration',
        'last_request' => 'Your last message to the client',
        'final_approved' => 'This registration was approved.',
        'final_rejected' => 'This registration was rejected. In this version the decision is final.',
        'done_approved' => 'Registration approved. The account is active and the first appointment was created.',
        'done_more' => 'Request sent to the client.', 'done_rejected' => 'Registration rejected.',
        'back' => 'Back to dashboard',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_fee' => 'Please enter a valid monthly fee.', 'err_currency' => 'Please choose a currency.',
        'err_date' => 'Please choose a first appointment date that is today or later.',
        'err_time' => 'Please enter a valid time.', 'err_url' => 'The video call link must start with http:// or https://.',
        'err_note' => 'Please write a message to the client.', 'err_long' => 'The text is too long.',
        'err_other' => 'Something went wrong. Please try again.', 'err_done' => 'This registration was already decided.',
    ],
    'pt' => [
        'role' => 'Nutricionista',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clientes', 'nav_appointments' => 'Consultas',
        'nav_messages' => 'Mensagens', 'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições',
        'nav_payments' => 'Pagamentos', 'nav_settings' => 'Configurações do Perfil', 'logout' => 'Sair',
        'kpi_messages' => 'Novas Mensagens', 'kpi_appointments' => 'Próximas Consultas',
        'kpi_payments' => 'Pagamentos em Aberto', 'kpi_pending' => 'Aprovações Pendentes',
        'today' => 'Hoje', 'yesterday' => 'Ontem',
        'msgs_link' => 'Mensagens desta cliente', 'crumb' => 'Revisão de cadastro', 'registered' => 'Cadastrado', 'pending_count' => '%d pendentes', 'next' => 'Próximo',
        'st0' => 'Pendente', 'st1' => 'Aprovado', 'st2' => 'Rejeitado', 'st3' => 'Mais informações pedidas',
        'personal' => 'Dados pessoais', 'phone' => 'Telefone', 'dob' => 'Data de nascimento',
        'country_city' => 'País, cidade', 'language' => 'Idioma',
        'followup' => 'Dados iniciais de acompanhamento', 'weight' => 'Peso', 'height' => 'Altura', 'goal' => 'Objetivo', 'email' => 'E-mail',
        'blood' => 'Exame de sangue', 'blood_none' => 'Nenhum arquivo disponível ainda.', 'blood_open' => 'Abrir', 'blood_soon' => 'Disponível em breve',
        'decision' => 'Sua decisão', 'd_approve' => 'Aprovar', 'd_more' => 'Mais info', 'd_reject' => 'Rejeitar',
        'approve_help' => 'Defina o plano do cliente. A conta fica ativa assim que você aprovar.',
        'fee' => 'Mensalidade', 'currency' => 'Moeda', 'first_appt' => 'Primeira consulta', 'time' => 'Horário',
        'video' => 'Link da videochamada (opcional)', 'initial_notes' => 'Observações iniciais',
        'initial_notes_ph' => 'Observações sobre o início do acompanhamento', 'btn_approve' => 'Aprovar e ativar a conta',
        'more_help' => 'O cliente verá o seu pedido no sistema e poderá responder com documentos ou respostas.',
        'more_label' => 'Mensagem para o cliente', 'more_ph' => 'O que você precisa do cliente?', 'btn_more' => 'Enviar pedido',
        'reject_help' => 'O cadastro será recusado e o cliente não terá acesso à plataforma.',
        'reject_label' => 'Motivo (opcional)', 'reject_ph' => 'Escreva um motivo para o cliente',
        'reject_warn' => 'Confirme antes de rejeitar este cadastro.',
        'reject_confirm' => 'Você realmente quer rejeitar este cadastro?', 'btn_reject' => 'Rejeitar cadastro',
        'last_request' => 'Sua última mensagem ao cliente',
        'final_approved' => 'Este cadastro foi aprovado.',
        'final_rejected' => 'Este cadastro foi rejeitado. Nesta versão a decisão é final.',
        'done_approved' => 'Cadastro aprovado. A conta está ativa e a primeira consulta foi criada.',
        'done_more' => 'Pedido enviado ao cliente.', 'done_rejected' => 'Cadastro rejeitado.',
        'back' => 'Voltar ao dashboard',
        'err_invalid' => 'Solicitação inválida. Recarregue a página e tente novamente.',
        'err_fee' => 'Informe uma mensalidade válida.', 'err_currency' => 'Escolha uma moeda.',
        'err_date' => 'Escolha uma data de primeira consulta de hoje em diante.',
        'err_time' => 'Informe um horário válido.', 'err_url' => 'O link da videochamada deve começar com http:// ou https://.',
        'err_note' => 'Escreva uma mensagem para o cliente.', 'err_long' => 'O texto está muito longo.',
        'err_other' => 'Algo deu errado. Tente novamente.', 'err_done' => 'Este cadastro já foi decidido.',
    ],
    'de' => [
        'role' => 'Ernährungsberaterin',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Kunden', 'nav_appointments' => 'Termine',
        'nav_messages' => 'Nachrichten', 'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos',
        'nav_payments' => 'Zahlungen', 'nav_settings' => 'Profileinstellungen', 'logout' => 'Abmelden',
        'kpi_messages' => 'Neue Nachrichten', 'kpi_appointments' => 'Bevorstehende Termine',
        'kpi_payments' => 'Offene Zahlungen', 'kpi_pending' => 'Ausstehende Freigaben',
        'today' => 'Heute', 'yesterday' => 'Gestern',
        'msgs_link' => 'Nachrichten dieses Kunden', 'crumb' => 'Registrierung prüfen', 'registered' => 'Registriert', 'pending_count' => '%d ausstehend', 'next' => 'Nächster',
        'st0' => 'Ausstehend', 'st1' => 'Genehmigt', 'st2' => 'Abgelehnt', 'st3' => 'Weitere Infos angefordert',
        'personal' => 'Persönliche Daten', 'phone' => 'Telefon', 'dob' => 'Geburtsdatum',
        'country_city' => 'Land, Stadt', 'language' => 'Sprache',
        'followup' => 'Erste Verlaufsdaten', 'weight' => 'Gewicht', 'height' => 'Größe', 'goal' => 'Ziel', 'email' => 'E-Mail',
        'blood' => 'Bluttest', 'blood_none' => 'Noch keine Datei vorhanden.', 'blood_open' => 'Öffnen', 'blood_soon' => 'Bald verfügbar',
        'decision' => 'Ihre Entscheidung', 'd_approve' => 'Genehmigen', 'd_more' => 'Mehr Infos', 'd_reject' => 'Ablehnen',
        'approve_help' => 'Legen Sie den Plan des Kunden fest. Das Konto wird aktiv, sobald Sie genehmigen.',
        'fee' => 'Monatsbetrag', 'currency' => 'Währung', 'first_appt' => 'Erster Termin', 'time' => 'Uhrzeit',
        'video' => 'Videoanruf-Link (optional)', 'initial_notes' => 'Anfangsbemerkungen',
        'initial_notes_ph' => 'Bemerkungen zum Beginn der Betreuung', 'btn_approve' => 'Genehmigen und Konto aktivieren',
        'more_help' => 'Der Kunde sieht Ihre Anfrage im System und kann mit Dokumenten oder Antworten reagieren.',
        'more_label' => 'Nachricht an den Kunden', 'more_ph' => 'Was benötigen Sie vom Kunden?', 'btn_more' => 'Anfrage senden',
        'reject_help' => 'Die Registrierung wird abgelehnt und der Kunde erhält keinen Zugang zur Plattform.',
        'reject_label' => 'Grund (optional)', 'reject_ph' => 'Schreiben Sie einen Grund für den Kunden',
        'reject_warn' => 'Bitte bestätigen Sie, bevor Sie diese Registrierung ablehnen.',
        'reject_confirm' => 'Möchten Sie diese Registrierung wirklich ablehnen?', 'btn_reject' => 'Registrierung ablehnen',
        'last_request' => 'Ihre letzte Nachricht an den Kunden',
        'final_approved' => 'Diese Registrierung wurde genehmigt.',
        'final_rejected' => 'Diese Registrierung wurde abgelehnt. In dieser Version ist die Entscheidung endgültig.',
        'done_approved' => 'Registrierung genehmigt. Das Konto ist aktiv und der erste Termin wurde angelegt.',
        'done_more' => 'Anfrage an den Kunden gesendet.', 'done_rejected' => 'Registrierung abgelehnt.',
        'back' => 'Zurück zum Dashboard',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_fee' => 'Bitte geben Sie einen gültigen Monatsbetrag ein.', 'err_currency' => 'Bitte wählen Sie eine Währung.',
        'err_date' => 'Bitte wählen Sie ein Datum für den ersten Termin ab heute.',
        'err_time' => 'Bitte geben Sie eine gültige Uhrzeit ein.', 'err_url' => 'Der Videoanruf-Link muss mit http:// oder https:// beginnen.',
        'err_note' => 'Bitte schreiben Sie eine Nachricht an den Kunden.', 'err_long' => 'Der Text ist zu lang.',
        'err_other' => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.', 'err_done' => 'Diese Registrierung wurde bereits entschieden.',
    ],
];
$t = $texts[$lang];

// Namen der Monate in jeder Sprache
$months = [
    'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    'pt' => ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
    'de' => ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
];

// BLOCK 5: kleine Funktionen, die beim Aufbau der Seite helfen

// schützt jeden Text, bevor er auf dem Bildschirm erscheint (gegen XSS)
function e($text) {
    // htmlspecialchars() macht aus Zeichen wie < und > harmlosen Text. So kann niemand Schadcode (JavaScript) in die Seite einschleusen (XSS-Schutz).
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// zeigt ein Symbol (icon) aus dem Ordner icons (z. B. icon('mail'))
function icon($name) {
    return '<img class="ico" src="icons/' . $name . '.svg" alt="">';
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

// Datum mit Jahr: "Jun 5, 1994" (EN), "5. Jun 1994" (DE), "5 jun 1994" (PT)
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

// "Heute", "Gestern" oder "12. Okt" (in der jeweiligen Sprache)
function friendly_day($datetime) {
    global $t;
    if (!$datetime) { return ""; }
    $day = date('Y-m-d', strtotime($datetime));
    if ($day == date('Y-m-d')) { return $t['today']; }
    if ($day == date('Y-m-d', strtotime('-1 day'))) { return $t['yesterday']; }
    return short_date($datetime);
}

// nur Links, die mit http:// oder https:// anfangen
function safe_url($url) {
    if ($url && preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return "";
}

// Zahl ohne überflüssige Nullen ("71.50" wird zu "71.5")
function clean_number($n) {
    if ($n === null || $n === '') { return ""; }
    return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
}

// BLOCK 6: Sicherheits-Token des Formulars (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// BLOCK 7: Was passiert, wenn die Ärztin eine Entscheidung sendet
$status = (int)$client['approval_status'];       // kommt immer aus der Datenbank
$can_decide = in_array($status, [0, 3], true);   // 0 Pending und 3 MoreInfos dürfen entschieden werden
$currencies = ['EUR', 'USD', 'BRL'];

// Werte des Formulars (bei einem Fehler kommen sie ausgefüllt zurück)
$f = [
    'decision' => 'approve', 'fee' => '', 'currency' => 'EUR', 'appt_date' => '', 'appt_time' => '',
    'meeting_url' => '', 'appt_notes' => '', 'note_more' => '', 'note_reject' => '',
];
$error = "";

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($f as $key => $default) {
        $f[$key] = trim($_POST[$key] ?? $default);
    }
    $token = $_POST['csrf_token'] ?? '';

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        $error = $t['err_invalid'];

    } elseif (!$can_decide) {
        $error = $t['err_done'];

    } elseif ($f['decision'] === 'approve') {
        // ---- GENEHMIGEN: Monatsgebühr, Währung und erster Termin ----
        $fee = str_replace(',', '.', $f['fee']);
        $d   = DateTime::createFromFormat('Y-m-d', $f['appt_date']);

        if ($f['fee'] === '' || !is_numeric($fee) || $fee < 0 || $fee > 99999999.99) {
            $error = $t['err_fee'];
        } elseif (!in_array($f['currency'], $currencies, true)) {
            $error = $t['err_currency'];
        } elseif (!$d || $d->format('Y-m-d') !== $f['appt_date'] || $d < new DateTime('today')) {
            $error = $t['err_date'];
        } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $f['appt_time'])) {
            $error = $t['err_time'];
        } elseif ($f['meeting_url'] !== '' && (safe_url($f['meeting_url']) === '' || mb_strlen($f['meeting_url']) > 500)) {
            $error = $t['err_url'];
        } elseif (mb_strlen($f['appt_notes']) > 2000) {
            $error = $t['err_long'];
        } else {
            try {
                // beginTransaction(): ab hier gehört alles zusammen (Transaktion). Entweder wird ALLES gespeichert (commit)
                // oder bei einem Fehler NICHTS (rollBack macht alles seit hier rückgängig).
                $pdo->beginTransaction();

                // 1) den Kunden aktivieren und Monatsgebühr und Währung speichern
                $stmt = $pdo->prepare(
                    "UPDATE clients SET approval_status = 1, monthly_fee = ?, currency = ?
                     WHERE client_id = ? AND approval_status IN (0, 3)"
                );
                $stmt->execute([round((float)$fee, 2), $f['currency'], $client_id]);

                if ($stmt->rowCount() === 0) {
                    // jemand hat diese Registrierung im selben Moment entschieden
                    $pdo->rollBack();
                    $error = $t['err_done'];
                } else {
                    // 2) den ersten Termin anlegen
                    $stmt = $pdo->prepare(
                        "INSERT INTO appointments
                            (appointment_date, appointment_time, meeting_url, notes, status, clients_client_id)
                         VALUES (?, ?, ?, ?, 'scheduled', ?)"
                    );
                    $stmt->execute([
                        $f['appt_date'], $f['appt_time'] . ':00',
                        $f['meeting_url'] !== '' ? $f['meeting_url'] : null,
                        $f['appt_notes'] !== '' ? $f['appt_notes'] : null,
                        $client_id
                    ]);

                    $pdo->commit();
                    header("Location: registration_review.php?id=" . $client_id . "&done=approved");
                    exit;
                }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = $t['err_other'];
            }
        }

    } elseif ($f['decision'] === 'more_info') {
        // ---- WEITERE INFORMATIONEN VERLANGEN: die Nachricht ist Pflicht ----
        if ($f['note_more'] === '') {
            $error = $t['err_note'];
        } elseif (mb_strlen($f['note_more']) > 2000) {
            $error = $t['err_long'];
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE clients SET approval_status = 3, review_note = ?
                     WHERE client_id = ? AND approval_status IN (0, 3)"
                );
                $stmt->execute([$f['note_more'], $client_id]);
                header("Location: registration_review.php?id=" . $client_id . "&done=more");
                exit;
            } catch (PDOException $ex) {
                $error = $t['err_other'];
            }
        }

    } elseif ($f['decision'] === 'reject') {
        // ---- ABLEHNEN: der Grund ist optional ----
        if (mb_strlen($f['note_reject']) > 2000) {
            $error = $t['err_long'];
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE clients SET approval_status = 2, review_note = ?
                     WHERE client_id = ? AND approval_status IN (0, 3)"
                );
                $stmt->execute([$f['note_reject'] !== '' ? $f['note_reject'] : null, $client_id]);
                header("Location: registration_review.php?id=" . $client_id . "&done=rejected");
                exit;
            } catch (PDOException $ex) {
                $error = $t['err_other'];
            }
        }

    } else {
        $error = $t['err_invalid'];
    }

    // wenn man hier ankommt, gab es einen Fehler: die gewählte Option bleibt
    if (!in_array($f['decision'], ['approve', 'more_info', 'reject'], true)) {
        $f['decision'] = 'approve';
    }
}

// Erfolgsmeldung nach einer Entscheidung (kommt von der Weiterleitung)
$success = "";
$done_messages = ['approved' => 'done_approved', 'more' => 'done_more', 'rejected' => 'done_rejected'];
if (isset($_GET['done']) && isset($done_messages[$_GET['done']])) {
    $success = $t[$done_messages[$_GET['done']]];
}

// BLOCK 8: zusätzliche Daten des Kunden (letzter Messwert, Bluttest, andere offene)

// letzter Messwert (Gewicht und Größe)
$stmt = $pdo->prepare(
    "SELECT weight, height FROM client_measurements
     WHERE clients_client_id = ?
     ORDER BY measurement_date DESC, measurement_id DESC LIMIT 1"
);
$stmt->execute([$client_id]);
$measure = $stmt->fetch();

// Bluttest (document_type 1)
$stmt = $pdo->prepare(
    "SELECT document_id, file_name, uploaded_at FROM documents
     WHERE clients_client_id = ? AND document_type = 1
     ORDER BY uploaded_at DESC LIMIT 1"
);
$stmt->execute([$client_id]);
$blood = $stmt->fetch();

// andere offene Registrierungen (für das "Next")
$count_pending = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE approval_status = 0")->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT client_id, first_name, last_name FROM clients
     WHERE approval_status = 0 AND client_id <> ?
     ORDER BY registration_date DESC LIMIT 1"
);
$stmt->execute([$client_id]);
$next_client = $stmt->fetch();

// BLOCK 9: die 4 Zahlen im Kopf
$count_messages = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE status = 0")->fetchColumn();
$count_appointments = (int)$pdo->query(
    "SELECT COUNT(*) FROM appointments
     WHERE status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()"
)->fetchColumn();
$count_payments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 0")->fetchColumn();

// BLOCK 10: Daten der Ärztin (Name, Logo, Foto und die 4 Farben)
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

// Namen der Sprachen des Kunden (0 = EN, 1 = DE, 2 = PT)
$lang_names = [0 => 'English', 1 => 'Deutsch', 2 => 'Português'];

// Etikett (Label) des aktuellen Status: Text und Farbklasse
$status_class = [0 => 'b-pen', 1 => 'b-ok', 2 => 'b-bad', 3 => 'b-info'];

// Einträge des Seitenmenüs: [Datei, Symbol, Text]
$current = "clients.php";
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

// Zeile "Stadt, Land" im Kopf
$place = trim(($client['city'] ?? '') . ', ' . ($client['country'] ?? ''), ', ');
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($t['crumb']) ?> - <?= e(full_name($client)) ?></title>
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
        .btn-open { font-size: 12px; padding: 5px 12px; border-radius: 8px; border: 1px solid var(--primary); color: var(--primary); background: #fff; display: inline-flex; align-items: center; gap: 5px; }
        .btn-open:hover { opacity: .92; }
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

        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
            .side { grid-row: auto; }
            .top { grid-template-columns: 1fr 1fr; }
            .panel { grid-template-columns: 1fr; }
            .hnext { margin-left: 0; text-align: left; }
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
        <div class="kpi"><?= icon('calendar') ?><div><b><?= $count_appointments ?></b><span><?= e($t['kpi_appointments']) ?></span></div></div>
        <div class="kpi"><?= icon('coin') ?><div><b><?= $count_payments ?></b><span><?= e($t['kpi_payments']) ?></span></div></div>
        <div class="kpi warm"><?= icon('user-check') ?><div><b><?= $count_pending ?></b><span><?= e($t['kpi_pending']) ?></span></div></div>
    </header>

    <!-- INHALT -->
    <main class="main">

        <?php if ($success !== ""): ?>
            <div class="success"><?= e($success) ?></div>
        <?php endif; ?>

        <!-- Kopf des Kunden -->
        <div class="hd">
            <div class="crumb">
                <a href="clients.php">&larr; <?= e($t['nav_clients']) ?></a> / <b><?= e($t['crumb']) ?></b>
            </div>
            <div class="hrow">
                <?= avatar($client, 'av-lg') ?>
                <div>
                    <div>
                        <span class="hname"><?= e(full_name($client)) ?></span>
                        <span class="bd <?= e($status_class[$status] ?? 'b-info') ?>"><?= e($t['st' . $status] ?? '') ?></span>
                    </div>
                    <div class="hsub"><?= e($t['registered']) ?> <?= e(friendly_day($client['registration_date'])) ?><?= $place !== '' ? ' · ' . e($place) : '' ?></div>
                </div>
                <?php if ($count_pending > 0): ?>
                    <div class="hnext">
                        <?= e(sprintf($t['pending_count'], $count_pending)) ?>
                        <?php if ($next_client): ?>
                            <br><?= e($t['next']) ?>: <a href="registration_review.php?id=<?= (int)$next_client['client_id'] ?>"><?= e(full_name($next_client)) ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">

            <!-- LINKS: was der Kunde ausgefüllt hat -->
            <div class="col">
                <div class="card">
                    <div class="ct"><?= icon('user') ?><?= e($t['personal']) ?></div>
                    <div class="kv"><span><?= e($t['phone']) ?></span><span><?= e($client['phone'] ?: '-') ?></span></div>
                    <div class="kv"><span><?= e($t['dob']) ?></span><span><?= $client['date_of_birth'] ? e(long_date($client['date_of_birth'])) : '-' ?></span></div>
                    <div class="kv"><span><?= e($t['country_city']) ?></span><span><?= e($place !== '' ? $place : '-') ?></span></div>
                    <div class="kv"><span><?= e($t['language']) ?></span><span><?= e($lang_names[(int)$client['preferred_language']] ?? '-') ?></span></div>
                </div>

                <div class="card">
                    <div class="ct"><?= icon('scale') ?><?= e($t['followup']) ?></div>
                    <div class="kv"><span><?= e($t['weight']) ?></span><span><?= $measure && $measure['weight'] !== null ? e(clean_number($measure['weight'])) . ' kg' : '-' ?></span></div>
                    <div class="kv"><span><?= e($t['height']) ?></span><span><?= $measure && $measure['height'] !== null ? e(clean_number($measure['height'])) . ' cm' : '-' ?></span></div>
                    <div class="kv"><span><?= e($t['goal']) ?></span><span><?= e($client['goal'] ?: '-') ?></span></div>
                    <div class="kv"><span><?= e($t['email']) ?></span><span><?= e($client['email']) ?></span></div>
                </div>

                <!-- Bluttest: öffnet sich über download.php (nur die Ärztin und der Besitzer der Datei können es) -->
                <div class="card warm">
                    <div class="ct"><?= icon('pdf') ?><?= e($t['blood']) ?></div>
                    <div class="blood">
                        <div class="tx">
                            <?php if ($blood): ?>
                                <?= e($blood['file_name']) ?>
                                <div class="sb"><?= e(friendly_day($blood['uploaded_at'])) ?></div>
                            <?php else: ?>
                                <?= e($t['blood_none']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($blood): ?>
                            <a class="btn-open" href="download.php?id=<?= (int)$blood['document_id'] ?>" target="_blank" rel="noopener"><?= icon('eye') ?><?= e($t['blood_open']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RECHTS: die Entscheidung -->
            <div class="card">
                <div class="ct"><?= icon('user-check') ?><?= e($t['decision']) ?></div>

                <?php if ($error !== ""): ?>
                    <div class="error"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!$can_decide): ?>
                    <!-- Genehmigt oder abgelehnt: nur lesen -->
                    <div class="final">
                        <?= e($status === 1 ? $t['final_approved'] : $t['final_rejected']) ?>
                        <?php if (trim($client['review_note'] ?? '') !== ""): ?>
                            <div class="lastnote" style="margin-top:10px"><b><?= e($t['last_request']) ?></b><?= e($client['review_note']) ?></div>
                        <?php endif; ?>
                        <br><a class="back" href="admin_dashboard.php"><?= e($t['back']) ?></a>
                    </div>

                <?php else: ?>
                    <form method="post" action="registration_review.php?id=<?= (int)$client_id ?>" onsubmit="return confirmReject();">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                        <!-- die 3 Knöpfe der Entscheidung -->
                        <div class="seg">
                            <label>
                                <input type="radio" name="decision" value="approve" <?= $f['decision'] === 'approve' ? 'checked' : '' ?> onchange="updateDecision()">
                                <div class="sg"><?= icon('check') ?><?= e($t['d_approve']) ?></div>
                            </label>
                            <label>
                                <input type="radio" name="decision" value="more_info" <?= $f['decision'] === 'more_info' ? 'checked' : '' ?> onchange="updateDecision()">
                                <div class="sg"><?= icon('message-question') ?><?= e($t['d_more']) ?></div>
                            </label>
                            <label>
                                <input type="radio" class="rej" name="decision" value="reject" <?= $f['decision'] === 'reject' ? 'checked' : '' ?> onchange="updateDecision()">
                                <div class="sg"><?= icon('x') ?><?= e($t['d_reject']) ?></div>
                            </label>
                        </div>

                        <!-- 1) GENEHMIGEN -->
                        <div class="dsec" data-for="approve" <?= $f['decision'] === 'approve' ? '' : 'hidden' ?>>
                            <div class="help"><?= e($t['approve_help']) ?></div>
                            <div class="row2">
                                <div>
                                    <label for="fee"><?= e($t['fee']) ?></label>
                                    <div class="field"><input type="text" inputmode="decimal" id="fee" name="fee" placeholder="90.00" value="<?= e($f['fee']) ?>" required></div>
                                </div>
                                <div>
                                    <label for="currency"><?= e($t['currency']) ?></label>
                                    <div class="field">
                                        <select id="currency" name="currency">
                                            <?php foreach ($currencies as $cur): ?>
                                                <option value="<?= e($cur) ?>" <?= $f['currency'] === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row2">
                                <div>
                                    <label for="appt_date"><?= e($t['first_appt']) ?></label>
                                    <div class="field has-icon">
                                        <img class="icon" src="icons/calendar.svg" alt="">
                                        <input type="date" id="appt_date" name="appt_date" min="<?= date('Y-m-d') ?>" value="<?= e($f['appt_date']) ?>" required>
                                    </div>
                                </div>
                                <div>
                                    <label for="appt_time"><?= e($t['time']) ?></label>
                                    <div class="field"><input type="time" id="appt_time" name="appt_time" value="<?= e($f['appt_time']) ?>" required></div>
                                </div>
                            </div>
                            <div style="margin-bottom:8px">
                                <label for="meeting_url"><?= e($t['video']) ?></label>
                                <div class="field has-icon">
                                    <img class="icon" src="icons/video.svg" alt="">
                                    <input type="text" id="meeting_url" name="meeting_url" maxlength="500" placeholder="https://meet.google.com/..." value="<?= e($f['meeting_url']) ?>">
                                </div>
                            </div>
                            <div>
                                <label for="appt_notes"><?= e($t['initial_notes']) ?></label>
                                <div class="field"><textarea id="appt_notes" name="appt_notes" maxlength="2000" placeholder="<?= e($t['initial_notes_ph']) ?>"><?= e($f['appt_notes']) ?></textarea></div>
                            </div>
                            <button type="submit" class="go"><?= icon('check-circle') ?><?= e($t['btn_approve']) ?></button>
                        </div>

                        <!-- 2) WEITERE INFORMATIONEN VERLANGEN -->
                        <div class="dsec" data-for="more_info" <?= $f['decision'] === 'more_info' ? '' : 'hidden' ?>>
                            <div class="help"><?= e($t['more_help']) ?></div>
                            <?php if ($status === 3 && trim($client['review_note'] ?? '') !== ""): ?>
                                <div class="lastnote"><b><?= e($t['last_request']) ?></b><?= e($client['review_note']) ?></div>
                            <?php endif; ?>
                            <label for="note_more"><?= e($t['more_label']) ?></label>
                            <div class="field"><textarea id="note_more" name="note_more" maxlength="2000" style="min-height:90px" placeholder="<?= e($t['more_ph']) ?>" required><?= e($f['note_more']) ?></textarea></div>
                            <button type="submit" class="go warm"><?= icon('arrow-right') ?><?= e($t['btn_more']) ?></button>
                        </div>

                        <!-- 3) ABLEHNEN -->
                        <div class="dsec" data-for="reject" <?= $f['decision'] === 'reject' ? '' : 'hidden' ?>>
                            <div class="help"><?= e($t['reject_help']) ?></div>
                            <label for="note_reject"><?= e($t['reject_label']) ?></label>
                            <div class="field"><textarea id="note_reject" name="note_reject" maxlength="2000" style="min-height:90px" placeholder="<?= e($t['reject_ph']) ?>"><?= e($f['note_reject']) ?></textarea></div>
                            <div class="warn"><?= icon('info-circle') ?><?= e($t['reject_warn']) ?></div>
                            <button type="submit" class="go danger"><?= icon('user-x') ?><?= e($t['btn_reject']) ?></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // Zeigt nur den Teil der gewählten Entscheidung (Approve, More info oder Reject).
    // Die Felder der anderen Teile sind ausgeschaltet: sie werden weder gesendet noch geprüft.
    function updateDecision() {
        var checked = document.querySelector('input[name=decision]:checked');
        if (!checked) { return; }
        var chosen = checked.value;

        document.querySelectorAll('.dsec').forEach(function (section) {
            var active = section.getAttribute('data-for') === chosen;
            section.hidden = !active;
            section.querySelectorAll('input, select, textarea, button').forEach(function (el) {
                el.disabled = !active;
            });
        });
    }

    // Sicherheitsfrage vor dem Ablehnen
    function confirmReject() {
        var checked = document.querySelector('input[name=decision]:checked');
        if (checked && checked.value === 'reject') {
            return confirm(<?= json_encode($t['reject_confirm']) ?>);
        }
        return true;
    }

    updateDecision();
</script>
</body>
</html>
