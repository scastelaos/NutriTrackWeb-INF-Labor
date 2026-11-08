<?php
// client_payments.php
// "Payments" (Zahlungen) des FREIGEGEBENEN Kunden: die Rechnungen sehen (Zeitraum, Fälligkeit, Betrag, Status),
// sehen, wie man zahlt (IBAN und PIX-QR-Code der Ernährungsberaterin) und einen Zahlungsbeleg als PDF senden (optional).
// Regeln der V1:
//  - die Rechnung anlegen und als BEZAHLT markieren kann immer nur die Ernährungsberaterin; der Kunde sieht nur
//  - Betrag und Währung kommen aus dem Profil des Kunden (clients.monthly_fee / clients.currency)
//  - "Überfällig" ist kein Status in der Datenbank: es ist eine OFFENE Rechnung, deren Fälligkeit schon vorbei ist
//  - stornierte (cancelled) Rechnungen sieht der Kunde nicht
//  - der Beleg ist optional: PDF bis 10 MB, gespeichert als Dokument Typ 3 (Payment Proof) in uploads/documents/

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
        'page_title' => 'Payments', 'subtitle' => 'See your payments, how to pay and send your proof of payment.',
        'your_nutri' => 'Your nutritionist: %s',
        'nav_home' => 'Home', 'nav_appointments' => 'Appointments', 'nav_messages' => 'Messages',
        'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos', 'nav_payments' => 'Payments',
        'nav_profile' => 'My Profile', 'logout' => 'Logout',
        'kpi_appt' => 'Next Appointment', 'kpi_reply' => 'New Reply', 'kpi_payment' => 'Payment', 'kpi_weight' => 'Current Weight',
        'due' => 'Due',
        'sec_list' => 'Your payments', 'empty' => 'You have no payments yet.',
        'col_period' => 'Period', 'col_due' => 'Due', 'col_amount' => 'Amount', 'col_status' => 'Status', 'col_paid' => 'Paid on',
        'st_open' => 'Open', 'st_overdue' => 'Overdue', 'st_paid' => 'Paid',
        'btn_pay' => 'Pay', 'no_fee' => '-',
        'sec_pay' => 'How to pay', 'pay_intro' => 'Make the transfer using one of the options below. Then you can send your proof of payment (optional).',
        'pay_amount' => 'Amount to pay', 'pay_iban' => 'IBAN', 'pay_pix' => 'PIX (scan the QR code)',
        'btn_copy' => 'Copy', 'copied' => 'Copied!', 'btn_close' => 'Close',
        'pay_none' => 'The payment details are not available yet. Please contact your nutritionist.',
        'sec_proof' => 'Send proof of payment', 'proof_intro' => 'Optional. Your nutritionist confirms the payment and marks it as paid.',
        'file' => 'PDF file', 'choose_file' => 'Choose PDF', 'no_file' => 'No file chosen',
        'upload_hint' => 'PDF only, maximum 10 MB per file.', 'btn_upload' => 'Send proof',
        'proofs_link' => 'See all my documents',
        'saved_upload' => 'Your proof of payment was sent.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_nofile' => 'Please choose a PDF file.',
        'err_toobig' => 'The file is too large. The maximum is 10 MB.',
        'err_notpdf' => 'Only PDF files are allowed.',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'page_title' => 'Pagamentos', 'subtitle' => 'Veja seus pagamentos, como pagar e envie seu comprovante.',
        'your_nutri' => 'Sua nutricionista: %s',
        'nav_home' => 'Início', 'nav_appointments' => 'Consultas', 'nav_messages' => 'Mensagens',
        'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições', 'nav_payments' => 'Pagamentos',
        'nav_profile' => 'Meu Perfil', 'logout' => 'Sair',
        'kpi_appt' => 'Próxima Consulta', 'kpi_reply' => 'Nova Resposta', 'kpi_payment' => 'Pagamento', 'kpi_weight' => 'Peso Atual',
        'due' => 'Vence',
        'sec_list' => 'Seus pagamentos', 'empty' => 'Você ainda não tem pagamentos.',
        'col_period' => 'Período', 'col_due' => 'Vence', 'col_amount' => 'Valor', 'col_status' => 'Situação', 'col_paid' => 'Pago em',
        'st_open' => 'Em aberto', 'st_overdue' => 'Atrasado', 'st_paid' => 'Pago',
        'btn_pay' => 'Pagar', 'no_fee' => '-',
        'sec_pay' => 'Como pagar', 'pay_intro' => 'Faça a transferência por uma das opções abaixo. Depois você pode enviar o comprovante (opcional).',
        'pay_amount' => 'Valor a pagar', 'pay_iban' => 'IBAN', 'pay_pix' => 'PIX (escaneie o QR Code)',
        'btn_copy' => 'Copiar', 'copied' => 'Copiado!', 'btn_close' => 'Fechar',
        'pay_none' => 'Os dados de pagamento ainda não estão disponíveis. Fale com sua nutricionista.',
        'sec_proof' => 'Enviar comprovante', 'proof_intro' => 'Opcional. Sua nutricionista confirma o pagamento e marca como pago.',
        'file' => 'Arquivo PDF', 'choose_file' => 'Escolher PDF', 'no_file' => 'Nenhum arquivo escolhido',
        'upload_hint' => 'Somente PDF, no máximo 10 MB por arquivo.', 'btn_upload' => 'Enviar comprovante',
        'proofs_link' => 'Ver todos os meus documentos',
        'saved_upload' => 'Seu comprovante foi enviado.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_nofile' => 'Escolha um arquivo PDF.',
        'err_toobig' => 'O arquivo é grande demais. O máximo é 10 MB.',
        'err_notpdf' => 'Só são aceitos arquivos PDF.',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'page_title' => 'Zahlungen', 'subtitle' => 'Sehen Sie Ihre Zahlungen, wie Sie bezahlen und senden Sie Ihren Zahlungsbeleg.',
        'your_nutri' => 'Ihre Ernährungsberaterin: %s',
        'nav_home' => 'Start', 'nav_appointments' => 'Termine', 'nav_messages' => 'Nachrichten',
        'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos', 'nav_payments' => 'Zahlungen',
        'nav_profile' => 'Mein Profil', 'logout' => 'Abmelden',
        'kpi_appt' => 'Nächster Termin', 'kpi_reply' => 'Neue Antwort', 'kpi_payment' => 'Zahlung', 'kpi_weight' => 'Aktuelles Gewicht',
        'due' => 'Fällig',
        'sec_list' => 'Ihre Zahlungen', 'empty' => 'Sie haben noch keine Zahlungen.',
        'col_period' => 'Zeitraum', 'col_due' => 'Fällig', 'col_amount' => 'Betrag', 'col_status' => 'Status', 'col_paid' => 'Bezahlt am',
        'st_open' => 'Offen', 'st_overdue' => 'Überfällig', 'st_paid' => 'Bezahlt',
        'btn_pay' => 'Bezahlen', 'no_fee' => '-',
        'sec_pay' => 'So bezahlen Sie', 'pay_intro' => 'Überweisen Sie über eine der Optionen unten. Danach können Sie Ihren Zahlungsbeleg senden (optional).',
        'pay_amount' => 'Zu zahlender Betrag', 'pay_iban' => 'IBAN', 'pay_pix' => 'PIX (QR-Code scannen)',
        'btn_copy' => 'Kopieren', 'copied' => 'Kopiert!', 'btn_close' => 'Schließen',
        'pay_none' => 'Die Zahlungsdaten sind noch nicht verfügbar. Bitte wenden Sie sich an Ihre Ernährungsberaterin.',
        'sec_proof' => 'Zahlungsbeleg senden', 'proof_intro' => 'Optional. Ihre Ernährungsberaterin bestätigt die Zahlung und markiert sie als bezahlt.',
        'file' => 'PDF-Datei', 'choose_file' => 'PDF wählen', 'no_file' => 'Keine Datei gewählt',
        'upload_hint' => 'Nur PDF, höchstens 10 MB pro Datei.', 'btn_upload' => 'Beleg senden',
        'proofs_link' => 'Alle meine Dokumente ansehen',
        'saved_upload' => 'Ihr Zahlungsbeleg wurde gesendet.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_nofile' => 'Bitte wählen Sie eine PDF-Datei.',
        'err_toobig' => 'Die Datei ist zu groß. Das Maximum sind 10 MB.',
        'err_notpdf' => 'Es sind nur PDF-Dateien erlaubt.',
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


// BLOCK 5: Daten der Ärztin (Name, Logo, Farben und Zahlungsdaten)
$profile = $pdo->query(
    "SELECT professional_name, logo_path, iban, pix_image_path,
            primary_color, secundary_color, background_color, accent_color
     FROM nutritionist_profile LIMIT 1"
)->fetch();

$name       = $profile['professional_name'] ?? "Nutritionist";
$logo       = $profile['logo_path'] ?? "";
$primary    = $profile['primary_color']      ?? "#2E7D32";
$secondary  = $profile['secundary_color']    ?? "#A5D6A7";
$background = $profile['background_color']   ?? "#F5F7F5";
$accent     = $profile['accent_color']       ?? "#FF9800";

// IBAN (nur Buchstaben und Zahlen, in Vierergruppen gezeigt) und PIX-Bild (nur aus dem Ordner uploads/profile/)
$iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($profile['iban'] ?? '')));
$iban_pretty = trim(chunk_split($iban, 4, ' '));
$pix  = (string)($profile['pix_image_path'] ?? '');
if (!preg_match('#^uploads/profile/[A-Za-z0-9._-]+$#', $pix)) { $pix = ""; }

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

// BLOCK 7: Beleg senden (PDF, Typ 3 = Payment Proof)
$max_bytes  = 10 * 1024 * 1024;                 // 10 MB (Höchstgröße in Bytes)
$upload_dir = __DIR__ . '/uploads/documents';   // Ordner (durch .htaccess gesperrt)
$form_error = "";
$open_pay   = isset($_GET['pay']);

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $open_pay = true;
    $token    = $_POST['csrf_token'] ?? '';

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // die Datei war größer als post_max_size von PHP: dann verwirft PHP alles
        $form_error = $t['err_toobig'];
    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    } elseif (!hash_equals($_SESSION['csrf_token'], (string)$token) || ($_POST['form'] ?? '') !== 'proof') {
        $form_error = $t['err_invalid'];
    } else {
        $f   = $_FILES['pdf'] ?? null;
        $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($err === UPLOAD_ERR_NO_FILE || !$f) {
            $form_error = $t['err_nofile'];
        } elseif ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $form_error = $t['err_toobig'];
        } elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $form_error = $t['err_other'];
        } elseif ($f['size'] > $max_bytes) {
            $form_error = $t['err_toobig'];
        } else {
            // prüfen, ob es WIRKLICH ein PDF ist: Endung .pdf und die ersten 5 Bytes "%PDF-"
            $original = basename(str_replace('\\', '/', (string)$f['name']));
            $original = preg_replace('/[\x00-\x1F\x7F]+/', '', $original);
            $fh    = fopen($f['tmp_name'], 'rb');
            $magic = $fh ? fread($fh, 5) : '';
            if ($fh) { fclose($fh); }

            if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'pdf' || $magic !== '%PDF-') {
                $form_error = $t['err_notpdf'];
            } else {
                // Originalname (nur zur Anzeige) und ZUFÄLLIGER Name auf der Festplatte
                if (mb_strlen($original) > 200) {
                    $original = mb_substr($original, 0, 196) . '.pdf';
                }
                $stored = bin2hex(random_bytes(16)) . '.pdf';
                $target = $upload_dir . '/' . $stored;

                // move_uploaded_file() verschiebt die hochgeladene Datei vom Temp-Ordner des Servers in den Zielordner (geht nur bei echten Uploads).
                if (!is_dir($upload_dir) || !move_uploaded_file($f['tmp_name'], $target)) {
                    $form_error = $t['err_other'];
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            "INSERT INTO documents (document_type, file_name, file_path, uploaded_at, clients_client_id)
                             VALUES (3, ?, ?, NOW(), ?)"
                        );
                        $stmt->execute([$original, 'uploads/documents/' . $stored, $client_id]);

                        header("Location: client_payments.php?done=uploaded");
                        exit;
                    } catch (PDOException $ex) {
                        // unlink() löscht eine Datei von der Festplatte.
                        @unlink($target);      // keine "losen" Dateien übrig lassen, wenn die Datenbank einen Fehler hatte (unlink = Datei löschen)
                        $form_error = $t['err_other'];
                    }
                }
            }
        }
    }
}

$success = (($_GET['done'] ?? '') === 'uploaded') ? $t['saved_upload'] : "";

// BLOCK 8: Daten des Kunden für die Seitenleiste
$first      = $client['first_name'] ?? "";
$full_name  = trim($first . ' ' . ($client['last_name'] ?? ''));
$initials   = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($client['last_name'] ?? '', 0, 1));
$photo      = $client['profile_photo_path'] ?? "";

// BLOCK 9: Daten dieser Seite
$today = date('Y-m-d');

// Betrag und Währung dieses Kunden
$stmt = $pdo->prepare("SELECT monthly_fee, currency FROM clients WHERE client_id = ?");
$stmt->execute([$client_id]);
$fee_row = $stmt->fetch();
$fee_text = money($fee_row['monthly_fee'] ?? null, $fee_row['currency'] ?? '');

// Rechnungen (ohne die stornierten): zuerst die offenen (nächste Fälligkeit zuerst), dann die bezahlten (neueste zuerst)
$stmt = $pdo->prepare(
    "SELECT payment_id, period_start, period_end, status, due_date, payment_date
     FROM payments
     WHERE clients_client_id = ? AND (status IS NULL OR status <> 2)
     ORDER BY CASE WHEN status IS NULL OR status = 0 THEN 0 ELSE 1 END,
              CASE WHEN status IS NULL OR status = 0 THEN due_date END ASC,
              payment_date DESC, payment_id DESC"
);
$stmt->execute([$client_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$has_open = false;
foreach ($payments as $p) {
    if ($p['status'] === null || (int)$p['status'] === 0) { $has_open = true; break; }
}

// Zahlen im Kopf
$stmt = $pdo->prepare(
    "SELECT appointment_date, appointment_time FROM appointments
     WHERE clients_client_id = ? AND status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()
     ORDER BY appointment_date, appointment_time LIMIT 1"
);
$stmt->execute([$client_id]);
$next_appt = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE clients_client_id = ? AND status = 1");
$stmt->execute([$client_id]);
$count_replies = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT due_date FROM payments WHERE clients_client_id = ? AND (status IS NULL OR status = 0) ORDER BY due_date ASC LIMIT 1"
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

// Seitenmenü: [Datei, Symbol, Text]
$current = "client_payments.php";
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


        /* Nachrichten */
        .badge { display: inline-block; font-size: 11px; font-weight: normal; padding: 2px 8px; border-radius: 10px; margin-left: 6px; vertical-align: middle; }
        .b-ok { background: #DDEBD2; color: #27500A; }
        .b-res { background: #FAEEDA; color: #633806; }
        .b-done { background: #E4E1D8; color: #444441; }
        .pending-box { background: #FAEEDA; color: #633806; font-size: 13px; padding: 9px 11px; border-radius: 8px; }
        .field textarea { resize: vertical; }
        .send-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .msg { border: 1px solid #E5E8E5; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
        .msg-head { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .msg-subject { font-size: 14px; font-weight: bold; }
        .msg-date { font-size: 11.5px; color: #888; margin-top: 2px; font-weight: normal; }
        .msg-text { font-size: 13px; color: #333; margin-top: 8px; white-space: pre-line; word-break: break-word; }
        .reply { background: #F1F6F1; border-left: 3px solid var(--primary); border-radius: 6px; padding: 9px 11px; margin-top: 10px; }
        .reply-head { font-size: 12px; font-weight: bold; color: var(--primary); }
        .reply .msg-text { margin-top: 5px; }
        .waiting { font-size: 12.5px; color: #777; margin-top: 10px; }


        /* Dokumente */
        .badge { display: inline-block; font-size: 11px; font-weight: normal; padding: 2px 8px; border-radius: 10px; vertical-align: middle; }
        .b-ok { background: #DDEBD2; color: #27500A; }
        .b-res { background: #FAEEDA; color: #633806; }
        .b-done { background: #E4E1D8; color: #444441; }
        .up-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; margin-bottom: 10px; align-items: end; }
        .file-pick { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .file-pick input[type=file] { display: none; }
        #file-name { font-size: 12.5px; color: #666; word-break: break-all; }
        .docs { width: 100%; border-collapse: collapse; font-size: 13px; }
        .docs th { text-align: left; color: #777; font-weight: normal; padding: 6px 8px 6px 0; border-bottom: 1px solid #E5E8E5; }
        .docs td { padding: 9px 8px 9px 0; border-bottom: 1px solid #F0F2F0; vertical-align: middle; }
        .docs .fname { word-break: break-word; font-weight: bold; }
        .docs .acts { white-space: nowrap; text-align: right; }
        .docs .acts .btn { margin-left: 6px; }


        /* Zahlungen */
        .pay { width: 100%; border-collapse: collapse; font-size: 13px; }
        .pay th { text-align: left; color: #777; font-weight: normal; padding: 6px 8px 6px 0; border-bottom: 1px solid #E5E8E5; white-space: nowrap; }
        .pay td { padding: 9px 8px 9px 0; border-bottom: 1px solid #F0F2F0; vertical-align: middle; }
        .pay .nowrap { white-space: nowrap; }
        .pay .acts { text-align: right; white-space: nowrap; }
        .b-red { background: #F9D6D6; color: #791F1F; }
        .scroll { overflow-x: auto; }
        /* Feld zum Auswählen des PDFs (wie das Feld für den Bluttest bei der Registrierung) */
        .upload { display: flex; align-items: center; gap: 12px; border: 1px dashed var(--accent); border-radius: 8px; background: #FBEFD9; padding: 14px; margin-bottom: 12px; }
        .upload > img { width: 28px; height: 28px; flex: none; }
        .upload .txt { flex: 1; min-width: 0; }
        .upload .t1 { font-size: 13px; font-weight: bold; }
        .upload .t2 { font-size: 11.5px; color: #6B6553; }
        .upload .fname { font-size: 11.5px; color: var(--primary); margin-top: 2px; word-break: break-all; }
        .upload input[type=file] { display: none; }
        .upload .pick { font-size: 12px; padding: 7px 12px; border-radius: 8px; cursor: pointer; border: 1px solid var(--primary); color: var(--primary); background: #fff; margin: 0; }

        .paybox { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: start; }
        .iban-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .iban { font-family: Consolas, Menlo, monospace; font-size: 16px; font-weight: bold; letter-spacing: .5px; word-break: break-all; }
        .qr { max-width: 220px; width: 100%; border: 1px solid #D8DDD8; border-radius: 10px; padding: 8px; background: #fff; display: block; }
        .pay-label { font-size: 12px; color: #666; margin-bottom: 4px; }
        .pay-amount { font-size: 22px; font-weight: bold; color: var(--primary); margin-bottom: 14px; }

        @media (max-width: 900px) {
            .paybox { grid-template-columns: 1fr; }
            .up-row { grid-template-columns: 1fr; }
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
        <div class="kpi"><?= icon('calendar') ?><div><b><?= e($kpi_appt) ?></b><span><?= e($t['kpi_appt']) ?></span></div></div>
        <div class="kpi"><?= icon('mail') ?><div><b><?= $count_replies ?></b><span><?= e($t['kpi_reply']) ?></span></div></div>
        <div class="kpi hl"><?= icon('coin') ?><div><b><?= e($kpi_payment) ?></b><span><?= e($t['kpi_payment']) ?></span></div></div>
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

            <div class="title-row">
                <div>
                    <h1><?= e($t['page_title']) ?></h1>
                    <p><?= e($t['subtitle']) ?></p>
                </div>
            </div>

            <!-- LISTE DER ZAHLUNGEN -->
            <div class="card">
                <div class="ct"><?= icon('wallet') ?><?= e($t['sec_list']) ?></div>

                <?php if (count($payments) === 0): ?>
                    <div class="empty"><?= e($t['empty']) ?></div>
                <?php else: ?>
                    <div class="scroll">
                    <table class="pay">
                        <thead>
                            <tr>
                                <th><?= e($t['col_period']) ?></th>
                                <th><?= e($t['col_due']) ?></th>
                                <th><?= e($t['col_amount']) ?></th>
                                <th><?= e($t['col_status']) ?></th>
                                <th><?= e($t['col_paid']) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <?php
                            $st      = $p['status'] === null ? 0 : (int)$p['status'];
                            $overdue = ($st === 0 && $p['due_date'] !== null && $p['due_date'] < $today);
                            if ($st === 1)     { $badge = [$t['st_paid'], 'b-ok']; }
                            elseif ($overdue)  { $badge = [$t['st_overdue'], 'b-red']; }
                            else               { $badge = [$t['st_open'], 'b-res']; }
                            ?>
                            <tr>
                                <td class="nowrap"><?= e($p['period_start'] ? long_date($p['period_start']) : '-') ?> – <?= e($p['period_end'] ? long_date($p['period_end']) : '-') ?></td>
                                <td class="nowrap"><?= e($p['due_date'] ? long_date($p['due_date']) : '-') ?></td>
                                <td class="nowrap"><?= e($fee_text) ?></td>
                                <td><span class="badge <?= e($badge[1]) ?>"><?= e($badge[0]) ?></span></td>
                                <td class="nowrap"><?= e($p['payment_date'] ? long_date($p['payment_date']) : '-') ?></td>
                                <td class="acts">
                                    <?php if ($st === 0): ?>
                                        <a class="btn sm fill" href="client_payments.php?pay=1#pay"><?= icon('wallet', 'w') ?><?= e($t['btn_pay']) ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($open_pay): ?>
            <!-- WIE MAN ZAHLT (öffnet sich mit dem Knopf "Zahlen") -->
            <div class="card" id="pay" style="margin-top:14px">
                <div class="ct"><?= icon('wallet') ?><?= e($t['sec_pay']) ?></div>

                <?php if ($iban === "" && $pix === ""): ?>
                    <div class="pending-box"><?= e($t['pay_none']) ?></div>
                <?php else: ?>
                    <p style="font-size:13px;color:#555;margin:0 0 12px"><?= e($t['pay_intro']) ?></p>
                    <?php if ($fee_text !== "-"): ?>
                        <div class="pay-label"><?= e($t['pay_amount']) ?></div>
                        <div class="pay-amount"><?= e($fee_text) ?></div>
                    <?php endif; ?>
                    <div class="paybox">
                        <?php if ($iban !== ""): ?>
                        <div>
                            <div class="pay-label"><?= e($t['pay_iban']) ?></div>
                            <div class="iban-row">
                                <span class="iban" id="iban-text"><?= e($iban_pretty) ?></span>
                                <button type="button" class="btn sm" id="copy-btn" data-iban="<?= e($iban) ?>"><?= e($t['btn_copy']) ?></button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($pix !== ""): ?>
                        <div>
                            <div class="pay-label"><?= e($t['pay_pix']) ?></div>
                            <img class="qr" src="<?= e($pix) ?>" alt="PIX">
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- BELEG (optional) -->
                <div style="border-top:1px solid #E5E8E5;margin-top:16px;padding-top:14px">
                    <div class="ct" style="margin-bottom:4px"><?= icon('documents') ?><?= e($t['sec_proof']) ?></div>
                    <p style="font-size:12.5px;color:#666;margin:0 0 10px"><?= e($t['proof_intro']) ?></p>

                    <?php if ($form_error !== ""): ?>
                        <div class="error"><?= e($form_error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="client_payments.php#pay" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form" value="proof">
                        <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$max_bytes ?>">
                        <div class="upload">
                            <img src="icons/upload.svg" alt="">
                            <div class="txt">
                                <div class="t1"><?= e($t['file']) ?></div>
                                <div class="t2"><?= e($t['upload_hint']) ?></div>
                                <div class="fname" id="file-name"><?= e($t['no_file']) ?></div>
                            </div>
                            <label class="pick" for="pdf"><?= e($t['choose_file']) ?></label>
                            <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf" required>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                            <button type="submit" class="btn fill"><?= icon('upload-arrow', 'w') ?><?= e($t['btn_upload']) ?></button>
                            <a class="btn" href="client_payments.php"><?= e($t['btn_close']) ?></a>
                                                    </div>
                    </form>
                    <div class="note"><a href="client_documents.php" style="text-decoration:underline"><?= e($t['proofs_link']) ?></a></div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
    // zeigt den Namen des gewählten PDFs neben dem Knopf
    var pdfInput = document.getElementById('pdf');
    var pdfName  = document.getElementById('file-name');
    if (pdfInput) {
        pdfInput.addEventListener('change', function () {
            pdfName.textContent = pdfInput.files.length ? pdfInput.files[0].name : <?= json_encode($t['no_file']) ?>;
        });
    }

    // kopiert die IBAN (ohne Leerzeichen) in die Zwischenablage (clipboard)
    var copyBtn = document.getElementById('copy-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var txt = copyBtn.getAttribute('data-iban');
            var done = function () {
                var old = copyBtn.textContent;
                copyBtn.textContent = <?= json_encode($t['copied']) ?>;
                setTimeout(function () { copyBtn.textContent = old; }, 1800);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(txt).then(done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = txt; document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); done(); } catch (err) {}
                document.body.removeChild(ta);
            }
        });
    }
</script>

</body>
</html>
