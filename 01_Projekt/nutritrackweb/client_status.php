<?php
// client_status.php
// Seite des Kunden, der noch NICHT freigegeben ist. Sie hat drei Versionen:
//  approval_status 0 = Pending   (in Prüfung)
//  approval_status 3 = MoreInfos (die Ärztin hat weitere Informationen verlangt)
//  approval_status 2 = Rejected  (Registrierung abgelehnt)
// Sie zeigt nur Informationen, AUSSER im Status MoreInfos (3): dort kann der Kunde eine Antwort schreiben.
// Die Antwort wird in der Tabelle messages gespeichert und die Registrierung geht zurück auf Pending (0), damit die Ärztin sie neu prüft.

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
$stmt = $pdo->prepare("SELECT role, email FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

if (!$me || $me['role'] != 0 ) {
    // Admin und andere: der Login weiß, wohin er jeden schickt
    header("Location: login.php?lang=" . $lang);
    exit;
}

// 2e: die Daten des Kunden holen (der approval_status kommt IMMER aus der Datenbank)
$stmt = $pdo->prepare(
    "SELECT client_id, first_name, last_name, country, city, preferred_language,
            goal, approval_status, review_note
     FROM clients WHERE users_user_id = ?"
);
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch();

if (!$client) {
    header("Location: login.php?lang=" . $lang);
    exit;
}

// 2f: ein freigegebener Kunde bleibt nicht hier: er geht in den vollen Bereich
if ($client['approval_status'] == 1) {
    header("Location: client_home.php");
    exit;
}

// 2g: nur die Status 0, 2 und 3 dürfen diese Seite sehen
if (!in_array($client['approval_status'], [0, 2, 3])) {
    header("Location: login.php?lang=" . $lang);
    exit;
}

$status = (int)$client['approval_status'];

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
// (die %s werden später durch den Namen des Kunden oder der Ärztin ersetzt)
$texts = [
    'en' => [
        'page_title' => 'Registration status', 'role' => 'Nutritionist', 'logout' => 'Logout',
        'pending_title' => 'Thank you, %s!',
        'pending_text'  => 'Your registration is under review. %s will check your details and your blood test. You will get full access after approval.',
        'more_title'    => '%s needs a bit more information',
        'more_text'     => 'Please provide the information requested below so the review can be completed.',
        'more_contact'  => 'Write your reply below, or contact %s using the details below.',
        'rej_title'     => 'Your registration was not approved',
        'rej_text'      => '%s reviewed your registration and could not accept it. You do not have access to the platform.',
        'step_sent' => 'Registration sent', 'step_review' => 'Under review', 'step_more' => 'More info needed',
        'step_reviewed' => 'Reviewed', 'step_rejected' => 'Not approved', 'step_access' => 'Access approved',
        'card_registration' => 'Your registration',
        'lbl_name' => 'Name', 'lbl_location' => 'Country, city', 'lbl_language' => 'Preferred language',
        'lbl_measures' => 'Weight, height', 'lbl_goal' => 'Goal', 'lbl_email' => 'Email',
        'blood_sent' => 'Sent', 'blood_none' => 'No blood test received yet.',
        'next_title' => 'What happens next',
        'next_text'  => 'If approved, your account opens with your plan and your first appointment. If %s needs more information, you will see the request here.',
        'msg_title' => 'Message from %s',
        'contact_more' => 'To reply, contact %s', 'contact_rej' => 'Questions? Contact %s',
        'reply_title' => 'Your reply', 'reply_label' => 'Message to %s',
        'reply_ph' => 'Write your answer or the information requested.', 'reply_btn' => 'Send reply',
        'reply_hint' => 'After sending, your registration goes back to review.',
        'reply_subject' => 'Reply to the request for more information',
        'reply_done' => 'Your reply was sent. %s will review your registration again.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_reply' => 'Please write your reply (maximum 5000 characters).',
        'attach_label' => 'Attach a PDF (optional)', 'attach_hint' => 'PDF only, maximum 10 MB. Your nutritionist can open it.',
        'err_pdf_big' => 'The PDF is too large. The maximum is 10 MB.', 'err_pdf_type' => 'The attachment must be a PDF file.',
        'err_state' => 'Your registration is no longer waiting for more information.',
        'err_other' => 'Something went wrong. Please try again.',
        'today' => 'Today', 'yesterday' => 'Yesterday',
        'lang0' => 'English', 'lang1' => 'German', 'lang2' => 'Portuguese',
    ],
    'pt' => [
        'page_title' => 'Status do cadastro', 'role' => 'Nutricionista', 'logout' => 'Sair',
        'pending_title' => 'Obrigado(a), %s!',
        'pending_text'  => 'Seu cadastro está em análise. %s vai conferir seus dados e seu exame de sangue. Você terá acesso completo depois da aprovação.',
        'more_title'    => '%s precisa de mais algumas informações',
        'more_text'     => 'Envie as informações pedidas abaixo para que a análise possa ser concluída.',
        'more_contact'  => 'Escreva sua resposta abaixo ou entre em contato com %s pelos dados abaixo.',
        'rej_title'     => 'Seu cadastro não foi aprovado',
        'rej_text'      => '%s analisou seu cadastro e não pôde aceitá-lo. Você não tem acesso à plataforma.',
        'step_sent' => 'Cadastro enviado', 'step_review' => 'Em análise', 'step_more' => 'Mais informações',
        'step_reviewed' => 'Analisado', 'step_rejected' => 'Não aprovado', 'step_access' => 'Acesso aprovado',
        'card_registration' => 'Seu cadastro',
        'lbl_name' => 'Nome', 'lbl_location' => 'País, cidade', 'lbl_language' => 'Idioma preferido',
        'lbl_measures' => 'Peso, altura', 'lbl_goal' => 'Objetivo', 'lbl_email' => 'E-mail',
        'blood_sent' => 'Enviado', 'blood_none' => 'Nenhum exame de sangue recebido ainda.',
        'next_title' => 'O que acontece agora',
        'next_text'  => 'Se for aprovado, sua conta será aberta com o seu plano e a sua primeira consulta. Se %s precisar de mais informações, você verá o pedido aqui.',
        'msg_title' => 'Mensagem de %s',
        'contact_more' => 'Para responder, fale com %s', 'contact_rej' => 'Dúvidas? Fale com %s',
        'reply_title' => 'Sua resposta', 'reply_label' => 'Mensagem para %s',
        'reply_ph' => 'Escreva sua resposta ou as informações pedidas.', 'reply_btn' => 'Enviar resposta',
        'reply_hint' => 'Depois de enviar, seu cadastro volta para análise.',
        'reply_subject' => 'Resposta ao pedido de mais informações',
        'reply_done' => 'Sua resposta foi enviada. %s vai analisar seu cadastro novamente.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_reply' => 'Escreva sua resposta (máximo 5000 caracteres).',
        'attach_label' => 'Anexar um PDF (opcional)', 'attach_hint' => 'Somente PDF, no máximo 10 MB. Sua nutricionista poderá abrir.',
        'err_pdf_big' => 'O PDF é grande demais. O máximo é 10 MB.', 'err_pdf_type' => 'O anexo precisa ser um arquivo PDF.',
        'err_state' => 'Seu cadastro não está mais aguardando informações.',
        'err_other' => 'Algo deu errado. Tente novamente.',
        'today' => 'Hoje', 'yesterday' => 'Ontem',
        'lang0' => 'Inglês', 'lang1' => 'Alemão', 'lang2' => 'Português',
    ],
    'de' => [
        'page_title' => 'Registrierungsstatus', 'role' => 'Ernährungsberaterin', 'logout' => 'Abmelden',
        'pending_title' => 'Vielen Dank, %s!',
        'pending_text'  => 'Ihre Registrierung wird geprüft. %s wird Ihre Angaben und Ihren Bluttest prüfen. Nach der Freigabe erhalten Sie vollen Zugang.',
        'more_title'    => '%s benötigt noch einige Informationen',
        'more_text'     => 'Bitte stellen Sie die unten angeforderten Informationen bereit, damit die Prüfung abgeschlossen werden kann.',
        'more_contact'  => 'Schreiben Sie Ihre Antwort unten oder wenden Sie sich über die folgenden Kontaktdaten an %s.',
        'rej_title'     => 'Ihre Registrierung wurde nicht freigegeben',
        'rej_text'      => '%s hat Ihre Registrierung geprüft und konnte sie nicht annehmen. Sie haben keinen Zugang zur Plattform.',
        'step_sent' => 'Registrierung gesendet', 'step_review' => 'In Prüfung', 'step_more' => 'Weitere Angaben nötig',
        'step_reviewed' => 'Geprüft', 'step_rejected' => 'Nicht freigegeben', 'step_access' => 'Zugang freigegeben',
        'card_registration' => 'Ihre Registrierung',
        'lbl_name' => 'Name', 'lbl_location' => 'Land, Stadt', 'lbl_language' => 'Bevorzugte Sprache',
        'lbl_measures' => 'Gewicht, Größe', 'lbl_goal' => 'Ziel', 'lbl_email' => 'E-Mail',
        'blood_sent' => 'Gesendet', 'blood_none' => 'Noch kein Bluttest eingegangen.',
        'next_title' => 'Wie es weitergeht',
        'next_text'  => 'Bei einer Freigabe wird Ihr Konto mit Ihrem Plan und Ihrem ersten Termin geöffnet. Wenn %s weitere Informationen benötigt, sehen Sie die Anfrage hier.',
        'msg_title' => 'Nachricht von %s',
        'contact_more' => 'Zum Antworten: Kontakt zu %s', 'contact_rej' => 'Fragen? Kontakt zu %s',
        'reply_title' => 'Ihre Antwort', 'reply_label' => 'Nachricht an %s',
        'reply_ph' => 'Schreiben Sie Ihre Antwort oder die angeforderten Informationen.', 'reply_btn' => 'Antwort senden',
        'reply_hint' => 'Nach dem Senden geht Ihre Registrierung zurück in die Prüfung.',
        'reply_subject' => 'Antwort auf die Anfrage nach weiteren Informationen',
        'reply_done' => 'Ihre Antwort wurde gesendet. %s prüft Ihre Registrierung erneut.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_reply' => 'Bitte schreiben Sie Ihre Antwort (maximal 5000 Zeichen).',
        'attach_label' => 'PDF anhängen (optional)', 'attach_hint' => 'Nur PDF, maximal 10 MB. Ihre Ernährungsberaterin kann es öffnen.',
        'err_pdf_big' => 'Das PDF ist zu groß. Das Maximum sind 10 MB.', 'err_pdf_type' => 'Der Anhang muss eine PDF-Datei sein.',
        'err_state' => 'Ihre Registrierung wartet nicht mehr auf weitere Informationen.',
        'err_other' => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.',
        'today' => 'Heute', 'yesterday' => 'Gestern',
        'lang0' => 'Englisch', 'lang1' => 'Deutsch', 'lang2' => 'Portugiesisch',
    ],
];

// $t ist der Satz Texte der gewählten Sprache
$t = $texts[$lang];

// Sicherheits-Token (CSRF) des Antwortformulars
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// BLOCK 3b: Antwort auf die Anfrage nach weiteren Informationen (nur im Status MoreInfos = 3)
$reply_error = "";
$v_reply     = "";

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v_reply = trim($_POST['reply'] ?? '');
    $token   = $_POST['csrf_token'] ?? '';

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $reply_error = $t['err_pdf_big'];           // war größer als post_max_size: dann verwirft PHP alles
    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    } elseif (!hash_equals($_SESSION['csrf_token'], (string)$token) || ($_POST['form'] ?? '') !== 'more_reply') {
        $reply_error = $t['err_invalid'];
    } elseif ($status !== 3) {
        $reply_error = $t['err_state'];             // der Status hat sich geändert, während sie schrieb
    } elseif ($v_reply === '' || mb_strlen($v_reply) > 5000) {
        $reply_error = $t['err_reply'];
    } else {
        // optionales PDF: wenn der Kunde eine Datei gewählt hat, muss es ein echtes PDF bis 10 MB sein
        $max_bytes = 10 * 1024 * 1024;
        $pdf_tmp   = "";
        $pdf_name  = "";
        $f   = $_FILES['attachment'] ?? null;
        $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($f && $err !== UPLOAD_ERR_NO_FILE) {
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > $max_bytes) {
                $reply_error = $t['err_pdf_big'];
            } elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                $reply_error = $t['err_other'];
            } else {
                $pdf_name = basename(str_replace('\\', '/', (string)$f['name']));
                $pdf_name = preg_replace('/[\x00-\x1F\x7F]+/', '', $pdf_name);
                $fh    = fopen($f['tmp_name'], 'rb');
                $magic = $fh ? fread($fh, 5) : '';
                if ($fh) { fclose($fh); }

                if (strtolower(pathinfo($pdf_name, PATHINFO_EXTENSION)) !== 'pdf' || $magic !== '%PDF-') {
                    $reply_error = $t['err_pdf_type'];
                } else {
                    if (mb_strlen($pdf_name) > 200) { $pdf_name = mb_substr($pdf_name, 0, 196) . '.pdf'; }
                    $pdf_tmp = $f['tmp_name'];
                }
            }
        }
    }

    if ($reply_error === "" && $_POST && ($_POST['form'] ?? '') === 'more_reply' && $status === 3) {
        $pdf_target = "";      // Pfad auf der Festplatte (um die Datei zu löschen, falls etwas schiefgeht)
        try {
            // beginTransaction(): ab hier gehört alles zusammen (Transaktion). Entweder wird ALLES gespeichert (commit)
            // oder bei einem Fehler NICHTS (rollBack macht alles seit hier rückgängig).
            $pdo->beginTransaction();

            // 1) die Registrierung geht zurück auf Pending (0), aber nur, wenn sie noch MoreInfos (3) ist
            $stmt = $pdo->prepare("UPDATE clients SET approval_status = 0 WHERE client_id = ? AND approval_status = 3");
            $stmt->execute([$client['client_id']]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                $reply_error = $t['err_state'];
            } else {
                // 2) die Antwort wird als normale Nachricht gespeichert (status 0 = Neu)
                $stmt = $pdo->prepare(
                    "INSERT INTO messages (subject, message_text, nutritionist_reply, status, created_at, answered_at, clients_client_id)
                     VALUES (?, ?, NULL, 0, NOW(), NULL, ?)"
                );
                $stmt->execute([$t['reply_subject'], $v_reply, $client['client_id']]);

                // 3) das optionale PDF: geschützter Ordner (zufälliger Name) + Eintrag in documents (4 = Other)
                if ($pdf_tmp !== "") {
                    $upload_dir = __DIR__ . '/uploads/documents';
                    $stored     = bin2hex(random_bytes(16)) . '.pdf';
                    $pdf_target = $upload_dir . '/' . $stored;

                    // move_uploaded_file() verschiebt die hochgeladene Datei vom Temp-Ordner des Servers in den Zielordner (geht nur bei echten Uploads).
                    if (!is_dir($upload_dir) || !move_uploaded_file($pdf_tmp, $pdf_target)) {
                        $pdf_target = "";
                        throw new RuntimeException('attachment could not be saved');
                    }

                    $stmt = $pdo->prepare(
                        "INSERT INTO documents (document_type, file_name, file_path, uploaded_at, clients_client_id)
                         VALUES (4, ?, ?, NOW(), ?)"
                    );
                    $stmt->execute([$pdf_name, 'uploads/documents/' . $stored, $client['client_id']]);
                }

                $pdo->commit();
                header("Location: client_status.php?done=reply");
                exit;
            }
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // unlink() löscht eine Datei von der Festplatte.
            if ($pdf_target !== "" && is_file($pdf_target)) { @unlink($pdf_target); }   // kein loses PDF übrig lassen (unlink = Datei löschen)
            $reply_error = $t['err_other'];
        }
    }
}

// Hinweis nach dem Senden der Antwort (die Seite kommt schon im Status Pending zurück)
$reply_done = (($_GET['done'] ?? '') === 'reply' && $status === 0);

// Namen der Monate (um Daten in der richtigen Sprache zu zeigen)
$months = [
    'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    'pt' => ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
    'de' => ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
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

// nur Links, die mit http:// oder https:// anfangen (verhindert gefährliche Links)
function safe_url($url) {
    if ($url && preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return "";
}

// BLOCK 5: Daten der Ärztin (Name, Logo, Foto, Kontakt und die 4 Farben)
$profile = $pdo->query(
    "SELECT professional_name, logo_path, profile_photo_path, phone, website,
            primary_color, secundary_color, background_color, accent_color
     FROM nutritionist_profile LIMIT 1"
)->fetch();

$name       = $profile['professional_name'] ?? "Nutritionist";
$logo       = $profile['logo_path'] ?? "";
$photo      = $profile['profile_photo_path'] ?? "";
$phone      = trim($profile['phone'] ?? "");
$website    = safe_url(trim($profile['website'] ?? ""));
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

// kurzer Name für die Sätze: "Dra. Sofia"
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

// Daten des Kunden für die Anzeige
$first = $client['first_name'] ?? "";
$client_initials = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($client['last_name'] ?? '', 0, 1));

// Text der Nachricht der Ärztin (erscheint nur, wenn es Inhalt gibt)
$note = trim($client['review_note'] ?? "");

// BLOCK 6: Was in jedem Status gezeigt wird (Symbol, Titel, Text und die 3 Schritte)
// jeder Schritt: [Symbol, Zustand, Text]
// Zustände: done = erledigt, current = aktuell, info = Bitte um Info, bad = abgelehnt, todo = noch nicht
if ($status === 0) {                      // Pending
    $hero_icon  = "hourglass";
    $hero_class = "c-pending";
    $hero_title = sprintf($t['pending_title'], $first);
    $hero_text  = sprintf($t['pending_text'], $short);
    $steps = [
        ["check", "done",    $t['step_sent']],
        ["eye",     "current", $t['step_review']],
        ["lock-open", "todo",  $t['step_access']],
    ];
} elseif ($status === 3) {                // MoreInfos
    $hero_icon  = "message-question";
    $hero_class = "c-more";
    $hero_title = sprintf($t['more_title'], $short);
    $hero_text  = $t['more_text'];
    $steps = [
        ["check", "done",             $t['step_sent']],
        ["message-question", "info",    $t['step_more']],
        ["lock-open", "todo",           $t['step_access']],
    ];
} else {                                  // Rejected (2)
    $hero_icon  = "user-x";
    $hero_class = "c-rej";
    $hero_title = $t['rej_title'];
    $hero_text  = sprintf($t['rej_text'], $short);
    $steps = [
        ["check", "done", $t['step_sent']],
        ["check", "done", $t['step_reviewed']],
        ["x", "bad",  $t['step_rejected']],
    ];
}

// nur im Status Pending: Zusammenfassung der Registrierung, Gewicht/Größe und Bluttest
$measure_text = "-";
$blood        = false;

if ($status === 0) {
    // zuletzt gespeichertes Gewicht und Größe (die Größe wird in Zentimetern gespeichert)
    $stmt = $pdo->prepare(
        "SELECT weight, height FROM client_measurements
         WHERE clients_client_id = ?
         ORDER BY measurement_date DESC, measurement_id DESC LIMIT 1"
    );
    $stmt->execute([$client['client_id']]);
    $m = $stmt->fetch();

    // zeigt nur, was angegeben wurde (Gewicht und Größe können leer sein)
    if ($m) {
        $parts = [];
        if ($m['weight'] !== null) {
            $parts[] = number_format($m['weight'], 1, '.', '') . ' kg';
        }
        if ($m['height'] !== null) {
            $parts[] = number_format($m['height'], 0, '.', '') . ' cm';
        }
        if (count($parts) > 0) {
            $measure_text = implode(', ', $parts);
        }
    }

    // Bluttest: documents.document_type 1 = Blood Test
    $stmt = $pdo->prepare(
        "SELECT file_name, uploaded_at FROM documents
         WHERE clients_client_id = ? AND document_type = 1
         ORDER BY uploaded_at DESC LIMIT 1"
    );
    $stmt->execute([$client['client_id']]);
    $blood = $stmt->fetch();
}

// bevorzugte Sprache des Kunden: 0 = EN, 1 = DE, 2 = PT
$pref_key = 'lang' . (int)($client['preferred_language'] ?? -1);
$pref_text = $t[$pref_key] ?? "-";

// Ort: "Ireland, Dublin"
$location = trim(($client['country'] ?? '') . ', ' . ($client['city'] ?? ''), ', ');

// Kontakt der Ärztin: zeigt nur, was vorhanden ist
$has_contact = ($phone !== "" || $website !== "");
$contact_title = ($status === 3) ? sprintf($t['contact_more'], $short) : sprintf($t['contact_rej'], $short);
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
           brightness(0) macht das Symbol schwarz; mit opacity wird es dunkelgrau.
           Die Klasse "w" (invert) macht das Symbol weiß, für dunkle/farbige Hintergründe. */
        .ico { width: 18px; height: 18px; vertical-align: middle; filter: brightness(0); opacity: .6; }
        .ico.w { filter: brightness(0) invert(1); opacity: 1; }

        .page { min-height: 100vh; display: flex; flex-direction: column; }

        /* OBERER BALKEN */
        .bar {
            background: var(--primary); color: #fff;
            display: flex; align-items: center; gap: 12px; padding: 12px 22px;
        }
        .brand { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
        .brand img.logo { max-height: 38px; max-width: 160px; object-fit: contain; }
        .mono {
            width: 38px; height: 38px; border-radius: 50%; flex: none;
            background: rgba(255,255,255,.92); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: bold; overflow: hidden;
        }
        .mono img { width: 100%; height: 100%; object-fit: cover; }
        .brand .bn { font-size: 15px; font-weight: bold; line-height: 1.2; }
        .brand .br { font-size: 11px; opacity: .8; }
        /* Sprachumschalter im Balken */
        .langs { display: flex; gap: 6px; }
        .langs a { font-size: 12px; padding: 3px 9px; border-radius: 6px; border: 1px solid rgba(255,255,255,.55); color: #fff; opacity: .85; }
        .langs a.on { background: #fff; color: var(--primary); font-weight: bold; opacity: 1; border-color: #fff; }
        .usr { display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .usr .a {
            width: 28px; height: 28px; border-radius: 50%;
            background: rgba(255,255,255,.92); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: bold;
        }
        .out {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,.7);
        }
        .out:hover { background: rgba(255,255,255,.15); }

        /* INHALT */
        .wrap { flex: 1; width: 100%; max-width: 580px; margin: 0 auto; padding: 24px 18px 16px; }
        .card { background: #fff; border: 1px solid #D8DDD8; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
        .card.tint {
            background: linear-gradient(rgba(255,255,255,.7), rgba(255,255,255,.7)), var(--secondary);
            border-color: var(--secondary);
        }
        .ct { display: flex; align-items: center; gap: 7px; font-size: 14px; font-weight: bold; color: var(--primary); margin-bottom: 8px; }

        /* Hauptkarte */
        .hero { text-align: center; padding: 22px 18px; }
        .circle {
            width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .circle .ico { width: 28px; height: 28px; }
        .c-pending { background: linear-gradient(rgba(255,255,255,.7), rgba(255,255,255,.7)), var(--accent); }
        .c-more    { background: #D6E6F7; }
        .c-rej     { background: #F9D6D6; }
        .hero h1 { margin: 0 0 6px; font-size: 20px; color: var(--primary); }
        .hero p { margin: 0 auto 6px; max-width: 400px; font-size: 14px; line-height: 1.5; color: #555; }

        /* Schritte (etapas) */
        .steps { display: flex; margin-top: 18px; }
        .s { flex: 1; text-align: center; position: relative; }
        .s .c {
            width: 32px; height: 32px; border-radius: 50%; margin: 0 auto 6px;
            display: flex; align-items: center; justify-content: center;
            position: relative; z-index: 1;
        }
        .s .t { font-size: 12px; line-height: 1.3; color: #666; }
        .s .ln { position: absolute; top: 15px; left: 50%; width: 100%; height: 2px; background: #D8DDD8; z-index: 0; }
        .s .ln.done { background: var(--primary); }
        .st-done    { background: var(--primary); }
        .st-current { background: var(--accent); }
        .st-info    { background: #0C447C; }
        .st-bad     { background: #791F1F; }
        .st-todo    { background: #E5E8E5; }
        .t-done, .t-current { color: var(--primary); font-weight: bold; }
        .t-info { color: #0C447C; font-weight: bold; }
        .t-bad  { color: #791F1F; font-weight: bold; }

        /* Zeilen mit Informationen */
        .kv { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; border-bottom: 1px solid #E5E8E5; font-size: 14px; }
        .kv:last-of-type { border-bottom: none; }
        .kv span:first-child { color: #666; flex: none; }
        .kv span:last-child { font-weight: bold; text-align: right; min-width: 0; overflow-wrap: anywhere; }

        .file {
            display: flex; align-items: center; gap: 10px; margin-top: 10px;
            background: linear-gradient(rgba(255,255,255,.7), rgba(255,255,255,.7)), var(--secondary);
            border-radius: 8px; padding: 9px 12px; font-size: 14px;
        }
        .file .ico { width: 22px; height: 22px; }
        .file .fx { flex: 1; min-width: 0; }
        .file .fn { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file .fs { font-size: 12px; color: #666; }

        .note { font-size: 14px; line-height: 1.55; }
        .info-text { font-size: 14px; line-height: 1.55; color: #555; }

        .contact a, .contact div { display: flex; align-items: center; gap: 10px; padding: 7px 0; font-size: 14px; }
        .contact a:hover span { text-decoration: underline; }

        /* Antwort auf die Anfrage nach weiteren Informationen */
        .success { background: #DDEBD2; color: #27500A; font-size: 14px; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .error { background: #F9D6D6; color: #791F1F; font-size: 14px; padding: 9px 11px; border-radius: 8px; margin-bottom: 12px; }
        label.fl { display: block; font-size: 13px; color: #555; margin-bottom: 5px; }
        textarea { width: 100%; padding: 10px 12px; font-size: 14px; font-family: inherit; border: 1px solid #D8DDD8; border-radius: 8px; background: #fff; color: #2B2A24; resize: vertical; }
        textarea:focus { outline: 2px solid var(--accent); border-color: var(--accent); }
        .send-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 10px; }
        .btn-send { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; padding: 9px 16px; border-radius: 8px; border: 1px solid var(--primary); background: var(--primary); color: #fff; cursor: pointer; font-family: inherit; }
        .btn-send:hover { opacity: .92; }
        .hint { font-size: 12px; color: #777; }
        .file-in { font-size: 13px; font-family: inherit; max-width: 100%; }

        .powered {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 12px; color: #555; padding: 12px 0 18px;
        }
        .powered b { color: var(--primary); }
    </style>
</head>
<body>
<div class="page">

    <!-- OBERER BALKEN: Identität der Ärztin, Kunde und Logout -->
    <div class="bar">
        <div class="brand">
            <?php if ($logo !== ""): ?>
                <img class="logo" src="<?= e($logo) ?>" alt="<?= e($name) ?>">
            <?php else: ?>
                <div class="mono">
                    <?php if ($photo !== ""): ?>
                        <img src="<?= e($photo) ?>" alt="<?= e($name) ?>">
                    <?php else: ?>
                        <?= e($initials) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="bn"><?= e($name) ?></div>
                    <div class="br"><?= e($t['role']) ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="langs">
            <?php foreach (['en' => 'EN', 'de' => 'DE', 'pt' => 'PT'] as $code => $label): ?>
                <a href="<?= e(basename($_SERVER['PHP_SELF']) . '?' . http_build_query(array_merge($_GET, ['lang' => $code]))) ?>" class="<?= $lang === $code ? 'on' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <div class="usr"><div class="a"><?= e($client_initials) ?></div><?= e($first) ?></div>
        <a class="out" href="logout.php"><?= icon('logout', 'w') ?><?= e($t['logout']) ?></a>
    </div>

    <div class="wrap">

        <?php if ($reply_done): ?>
            <div class="success"><?= e(sprintf($t['reply_done'], $short)) ?></div>
        <?php endif; ?>

        <!-- HAUPTKARTE: ändert sich je nach Status -->
        <div class="card hero">
            <div class="circle <?= e($hero_class) ?>"><?= icon($hero_icon) ?></div>
            <h1><?= e($hero_title) ?></h1>
            <p><?= e($hero_text) ?></p>
            <?php if ($status === 3): ?>
                <p><?= e(sprintf($t['more_contact'], $short)) ?></p>
            <?php endif; ?>

            <div class="steps">
                <?php foreach ($steps as $i => $s): ?>
                    <div class="s">
                        <?php if ($i < count($steps) - 1): ?>
                            <div class="ln <?= $s[1] === 'done' ? 'done' : '' ?>"></div>
                        <?php endif; ?>
                        <div class="c st-<?= e($s[1]) ?>"><?= icon($s[0], $s[1] === 'todo' ? '' : 'w') ?></div>
                        <div class="t t-<?= e($s[1]) ?>"><?= e($s[2]) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($status === 0): ?>

            <!-- IN PRÜFUNG: Zusammenfassung der Registrierung -->
            <div class="card">
                <div class="ct"><?= icon('file-text') ?><?= e($t['card_registration']) ?></div>
                <div class="kv"><span><?= e($t['lbl_name']) ?></span><span><?= e(trim($first . ' ' . ($client['last_name'] ?? ''))) ?></span></div>
                <div class="kv"><span><?= e($t['lbl_location']) ?></span><span><?= e($location) ?></span></div>
                <div class="kv"><span><?= e($t['lbl_language']) ?></span><span><?= e($pref_text) ?></span></div>
                <div class="kv"><span><?= e($t['lbl_measures']) ?></span><span><?= e($measure_text) ?></span></div>
                <div class="kv"><span><?= e($t['lbl_goal']) ?></span><span><?= e($client['goal'] ?? '-') ?></span></div>
                <div class="kv"><span><?= e($t['lbl_email']) ?></span><span><?= e($me['email']) ?></span></div>

                <div class="file">
                    <?= icon('pdf') ?>
                    <?php if ($blood): ?>
                        <div class="fx">
                            <div class="fn"><?= e($blood['file_name']) ?></div>
                            <div class="fs"><?= e($t['blood_sent']) ?> · <?= e(friendly_day($blood['uploaded_at'])) ?></div>
                        </div>
                        <?= icon('check-circle') ?>
                    <?php else: ?>
                        <div class="fx"><div class="fs"><?= e($t['blood_none']) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="ct"><?= icon('info-circle') ?><?= e($t['next_title']) ?></div>
                <div class="info-text"><?= e(sprintf($t['next_text'], $short)) ?></div>
            </div>

        <?php else: ?>

            <!-- WEITERE INFORMATIONEN oder ABGELEHNT: Nachricht der Ärztin (nur, wenn es Text gibt) -->
            <?php if ($note !== ""): ?>
                <div class="card tint">
                    <div class="ct"><?= icon('message') ?><?= e(sprintf($t['msg_title'], $short)) ?></div>
                    <div class="note"><?= nl2br(e($note)) ?></div>
                </div>
            <?php endif; ?>

            <!-- Kontakt der Ärztin (zeigt nur, was vorhanden ist) -->
            <?php if ($has_contact): ?>
                <div class="card contact">
                    <div class="ct"><?= icon('contact-book') ?><?= e($contact_title) ?></div>
                    <?php if ($phone !== ""): ?>
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= icon('phone') ?><span><?= e($phone) ?></span></a>
                    <?php endif; ?>
                    <?php if ($website !== ""): ?>
                        <a href="<?= e($website) ?>" target="_blank" rel="noopener"><?= icon('globe') ?><span><?= e(rtrim(preg_replace('#^https?://#i', '', $website), '/')) ?></span></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>


            <?php if ($status === 3): ?>
                <!-- ANTWORT auf die Anfrage nach weiteren Informationen: wird zu einer Nachricht an die Ärztin -->
                <div class="card">
                    <div class="ct"><?= icon('reply') ?><?= e($t['reply_title']) ?></div>

                    <?php if ($reply_error !== ""): ?>
                        <div class="error"><?= e($reply_error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="client_status.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form" value="more_reply">
                        <label class="fl" for="reply"><?= e(sprintf($t['reply_label'], $short)) ?></label>
                        <textarea id="reply" name="reply" rows="5" maxlength="5000" placeholder="<?= e($t['reply_ph']) ?>" required><?= e($v_reply) ?></textarea>

                        <label class="fl" for="attachment" style="margin-top:12px"><?= e($t['attach_label']) ?></label>
                        <input type="file" id="attachment" name="attachment" accept="application/pdf,.pdf" class="file-in">
                        <div class="hint" style="margin:4px 0 12px"><?= e($t['attach_hint']) ?></div>

                        <div class="send-row">
                            <button type="submit" class="btn-send"><?= icon('reply', 'w') ?><?= e($t['reply_btn']) ?></button>
                            <span class="hint"><?= e($t['reply_hint']) ?></span>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <div class="powered">
        <svg width="15" height="15" viewBox="0 0 48 48" aria-hidden="true">
            <path d="M8 40 C8 19 23 7 42 7 C42 27 31 42 8 40 Z" fill="#2F5D3A"/>
            <path d="M8 40 L31 17" stroke="#ffffff" stroke-width="3" stroke-linecap="round" fill="none"/>
            <circle cx="39" cy="38" r="5" fill="#C9803A"/>
        </svg>
        Powered by <b>NutriTrackWeb</b>
    </div>
</div>
</body>
</html>
