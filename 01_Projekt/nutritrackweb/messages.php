<?php
// messages.php
// Seite der Ärztin (Admin) für die NACHRICHTEN der Kunden:
//  Nachrichten nach Reiter sehen (Neu, Beantwortet, Geschlossen, Alle), antworten und schließen,
//  und den Verlauf von EINEM Kunden sehen (?client=NUMMER).
// Regeln der V1: 1 Frage + 1 Antwort pro Nachricht; die Antwort kann nicht bearbeitet werden;
// nur die Ärztin schließt (nur beantwortete Nachrichten); eine geschlossene Nachricht ist nur noch lesbar.
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
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clients', 'nav_appointments' => 'Appointments',
        'nav_messages' => 'Messages', 'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos',
        'nav_payments' => 'Payments', 'nav_settings' => 'Profile Settings', 'logout' => 'Logout',
        'kpi_messages' => 'New Messages', 'kpi_appointments' => 'Upcoming Appointments',
        'kpi_payments' => 'Open Payments', 'kpi_pending' => 'Pending Approvals',
        'page_title' => 'Messages', 'subtitle' => 'Answer your clients\' questions and keep the history organized.',
        'tab_all' => 'All', 'tab_0' => 'New', 'tab_1' => 'Answered', 'tab_2' => 'Closed',
        'st_0' => 'New', 'st_1' => 'Answered', 'st_2' => 'Closed',
        'only_client' => 'Showing only messages from %s', 'clear_filter' => 'Show all clients',
        'empty' => 'No messages here.',
        'sent_on' => 'Sent %s', 'answered_on' => 'Answered %s', 'your_reply' => 'Your answer',
        'reply_label' => 'Your answer', 'reply_ph' => 'Write your answer to the client.',
        'btn_reply' => 'Send answer', 'btn_close' => 'Close conversation',
        'reply_hint' => 'The answer cannot be edited after sending.',
        'close_confirm' => 'Close this conversation? It becomes read-only for both of you.',
        'closed_note' => 'Closed conversation (read-only).',
        'done_replied' => 'Answer sent.', 'done_closed' => 'Conversation closed.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_done' => 'This message was already handled or is no longer available.',
        'err_reply' => 'Please write an answer (maximum 5000 characters).',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'role' => 'Nutricionista',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clientes', 'nav_appointments' => 'Consultas',
        'nav_messages' => 'Mensagens', 'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições',
        'nav_payments' => 'Pagamentos', 'nav_settings' => 'Configurações do Perfil', 'logout' => 'Sair',
        'kpi_messages' => 'Novas Mensagens', 'kpi_appointments' => 'Próximas Consultas',
        'kpi_payments' => 'Pagamentos em Aberto', 'kpi_pending' => 'Aprovações Pendentes',
        'page_title' => 'Mensagens', 'subtitle' => 'Responda às perguntas das clientes e mantenha o histórico organizado.',
        'tab_all' => 'Todas', 'tab_0' => 'Novas', 'tab_1' => 'Respondidas', 'tab_2' => 'Encerradas',
        'st_0' => 'Nova', 'st_1' => 'Respondida', 'st_2' => 'Encerrada',
        'only_client' => 'Mostrando só as mensagens de %s', 'clear_filter' => 'Mostrar todas as clientes',
        'empty' => 'Nenhuma mensagem aqui.',
        'sent_on' => 'Enviada em %s', 'answered_on' => 'Respondida em %s', 'your_reply' => 'Sua resposta',
        'reply_label' => 'Sua resposta', 'reply_ph' => 'Escreva a resposta para a cliente.',
        'btn_reply' => 'Enviar resposta', 'btn_close' => 'Encerrar conversa',
        'reply_hint' => 'A resposta não pode ser editada depois de enviada.',
        'close_confirm' => 'Encerrar esta conversa? Ela vira somente leitura para vocês duas.',
        'closed_note' => 'Conversa encerrada (somente leitura).',
        'done_replied' => 'Resposta enviada.', 'done_closed' => 'Conversa encerrada.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_done' => 'Esta mensagem já foi tratada ou não está mais disponível.',
        'err_reply' => 'Escreva uma resposta (máximo 5000 caracteres).',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'role' => 'Ernährungsberaterin',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Kunden', 'nav_appointments' => 'Termine',
        'nav_messages' => 'Nachrichten', 'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos',
        'nav_payments' => 'Zahlungen', 'nav_settings' => 'Profileinstellungen', 'logout' => 'Abmelden',
        'kpi_messages' => 'Neue Nachrichten', 'kpi_appointments' => 'Bevorstehende Termine',
        'kpi_payments' => 'Offene Zahlungen', 'kpi_pending' => 'Ausstehende Freigaben',
        'page_title' => 'Nachrichten', 'subtitle' => 'Beantworten Sie die Fragen Ihrer Kunden und behalten Sie den Verlauf im Blick.',
        'tab_all' => 'Alle', 'tab_0' => 'Neu', 'tab_1' => 'Beantwortet', 'tab_2' => 'Geschlossen',
        'st_0' => 'Neu', 'st_1' => 'Beantwortet', 'st_2' => 'Geschlossen',
        'only_client' => 'Es werden nur Nachrichten von %s angezeigt', 'clear_filter' => 'Alle Kunden anzeigen',
        'empty' => 'Hier gibt es keine Nachrichten.',
        'sent_on' => 'Gesendet am %s', 'answered_on' => 'Beantwortet am %s', 'your_reply' => 'Ihre Antwort',
        'reply_label' => 'Ihre Antwort', 'reply_ph' => 'Schreiben Sie die Antwort an den Kunden.',
        'btn_reply' => 'Antwort senden', 'btn_close' => 'Gespräch schließen',
        'reply_hint' => 'Die Antwort kann nach dem Senden nicht mehr bearbeitet werden.',
        'close_confirm' => 'Dieses Gespräch schließen? Es ist danach für beide nur noch lesbar.',
        'closed_note' => 'Geschlossenes Gespräch (nur lesbar).',
        'done_replied' => 'Antwort gesendet.', 'done_closed' => 'Gespräch geschlossen.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_done' => 'Diese Nachricht wurde bereits bearbeitet oder ist nicht mehr verfügbar.',
        'err_reply' => 'Bitte schreiben Sie eine Antwort (maximal 5000 Zeichen).',
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

// BLOCK 5: Sicherheits-Token des Formulars (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// BLOCK 6: Filter der Seite (kommen aus der Adresszeile) und was in den Formularen passiert

// gewählter Reiter: all, 0 (Neu), 1 (Beantwortet) oder 2 (Geschlossen). Standard: Neu
$filter = $_GET['filter'] ?? ($_POST['filter'] ?? '0');
if (!in_array((string)$filter, ['all', '0', '1', '2'], true)) { $filter = '0'; }
$filter = (string)$filter;

// Verlauf von EINEM Kunden (?client=NUMMER); 0 = alle
$only_client = (int)($_GET['client'] ?? ($_POST['client'] ?? 0));

// Adresse, zu der man nach einer Aktion zurückkommt (behält den Filter)
$back = 'messages.php?filter=' . $filter . ($only_client > 0 ? '&client=' . $only_client : '');

$error        = "";     // Fehler oben auf der Seite
$reply_error  = "";     // Fehler in der Karte einer Nachricht
$reply_error_id = 0;
$v_reply      = "";

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form  = $_POST['form'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    // filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
    $message_id = filter_var($_POST['message_id'] ?? '', FILTER_VALIDATE_INT);

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    if (!hash_equals($_SESSION['csrf_token'], (string)$token) || $message_id === false || $message_id < 1) {
        $error = $t['err_invalid'];

    } elseif ($form === 'reply') {
        // ---------- ANTWORTEN (nur bei neuer Nachricht; die Antwort kann danach nicht bearbeitet werden) ----------
        $v_reply = trim($_POST['reply'] ?? '');
        $reply_error_id = $message_id;

        if ($v_reply === '' || mb_strlen($v_reply) > 5000) {
            $reply_error = $t['err_reply'];
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE messages SET nutritionist_reply = ?, status = 1, answered_at = NOW()
                     WHERE message_id = ? AND status = 0"
                );
                $stmt->execute([$v_reply, $message_id]);

                if ($stmt->rowCount() === 0) {
                    $error = $t['err_done'];          // jemand hat schon geantwortet oder die Nachricht existiert nicht
                    $reply_error_id = 0;
                } else {
                    header("Location: " . $back . "&done=replied");
                    exit;
                }
            } catch (PDOException $ex) {
                $reply_error = $t['err_other'];
            }
        }

    } elseif ($form === 'close') {
        // ---------- SCHLIESSEN (nur die Ernährungsberaterin; nur beantwortete Nachricht; niemand öffnet sie wieder) ----------
        try {
            $stmt = $pdo->prepare("UPDATE messages SET status = 2 WHERE message_id = ? AND status = 1");
            $stmt->execute([$message_id]);

            if ($stmt->rowCount() === 0) {
                $error = $t['err_done'];
            } else {
                header("Location: " . $back . "&done=closed");
                exit;
            }
        } catch (PDOException $ex) {
            $error = $t['err_other'];
        }

    } else {
        $error = $t['err_invalid'];
    }
}

// Erfolgsmeldung (kommt von der Weiterleitung)
$success = "";
$done_messages = ['replied' => 'done_replied', 'closed' => 'done_closed'];
if (isset($_GET['done']) && isset($done_messages[$_GET['done']])) {
    $success = $t[$done_messages[$_GET['done']]];
}

// BLOCK 7: Daten der Seite

// 7a: wenn man den Verlauf eines Kunden sieht, wird sein Name geholt
$only_row = null;
if ($only_client > 0) {
    $stmt = $pdo->prepare("SELECT client_id, first_name, last_name, profile_photo_path FROM clients WHERE client_id = ?");
    $stmt->execute([$only_client]);
    $only_row = $stmt->fetch();
    if (!$only_row) { $only_client = 0; }
}

// 7b: wie viele Nachrichten in jedem Reiter (beachtet den Filter nach Kunde)
$counts = [0 => 0, 1 => 0, 2 => 0];
$sql = "SELECT status, COUNT(*) AS n FROM messages" . ($only_client > 0 ? " WHERE clients_client_id = ?" : "") . " GROUP BY status";
$stmt = $pdo->prepare($sql);
$stmt->execute($only_client > 0 ? [$only_client] : []);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($counts[(int)$row['status']])) { $counts[(int)$row['status']] = (int)$row['n']; }
}
$counts['all'] = $counts[0] + $counts[1] + $counts[2];

// 7c: die Nachrichten des gewählten Reiters (die neuen: die älteste zuerst, damit man der Reihe nach antwortet)
$where  = [];
$params = [];
if ($filter !== 'all') { $where[] = "m.status = ?"; $params[] = (int)$filter; }
if ($only_client > 0)  { $where[] = "m.clients_client_id = ?"; $params[] = $only_client; }
$order = ($filter === '0') ? "m.created_at ASC, m.message_id ASC" : "m.created_at DESC, m.message_id DESC";

$stmt = $pdo->prepare(
    "SELECT m.message_id, m.subject, m.message_text, m.nutritionist_reply, m.status, m.created_at, m.answered_at,
            c.client_id, c.first_name, c.last_name, c.profile_photo_path
     FROM messages m
     JOIN clients c ON c.client_id = m.clients_client_id"
    . (count($where) ? " WHERE " . implode(" AND ", $where) : "")
    . " ORDER BY " . $order . " LIMIT 50"
);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7d: die 4 Zahlen im Kopf
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

// Etiketten (Labels) für den Status der Nachricht: [Text, Farbklasse]
$msg_badges = [0 => [$t['st_0'], 'b-info'], 1 => [$t['st_1'], 'b-ok'], 2 => [$t['st_2'], 'b-gray']];

// Einträge des Seitenmenüs: [Datei, Symbol, Text]
$current = "messages.php";
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
        .b-gray { background: #E4E1D8; color: #444441; }
        .row2.inner { margin-bottom: 0; }
        .form-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .field input[readonly] { background: #F5F7F5; color: #555; }
        .field textarea { resize: vertical; min-height: 64px; }

        /* Nachrichten */
        .tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
        .tab { font-size: 13px; padding: 7px 14px; border-radius: 99px; border: 1px solid #D8DDD8; background: #fff; color: #555; }
        .tab .n { display: inline-block; min-width: 20px; text-align: center; font-size: 11px; background: #EEF1EE; border-radius: 99px; padding: 1px 6px; margin-left: 4px; }
        .tab.on { background: var(--primary); border-color: var(--primary); color: #fff; font-weight: bold; }
        .tab.on .n { background: rgba(255,255,255,.25); color: #fff; }
        .only { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 13px; background: #fff; border: 1px solid #D8DDD8; border-radius: 10px; padding: 8px 12px; margin-bottom: 12px; }
        .msg { border: 1px solid #E5E8E5; border-radius: 10px; padding: 12px; margin-bottom: 12px; }
        .msg-head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .msg-who { flex: 1; min-width: 0; }
        .who { font-size: 14px; font-weight: bold; color: var(--primary); }
        .who:hover { text-decoration: underline; }
        .msg-date { font-size: 11.5px; color: #888; font-weight: normal; }
        .msg-subject { font-size: 14px; font-weight: bold; }
        .msg-text { font-size: 13px; color: #333; margin-top: 6px; white-space: pre-line; word-break: break-word; }
        .reply { background: #F1F6F1; border-left: 3px solid var(--primary); border-radius: 6px; padding: 9px 11px; margin: 10px 0; }
        .reply-head { font-size: 12px; font-weight: bold; color: var(--primary); }
        .reply .msg-text { margin-top: 5px; }
        .reply-form { margin-top: 10px; border-top: 1px solid #E5E8E5; padding-top: 10px; }
        .reply-form .form-btns { align-items: center; margin-top: 8px; }

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
        <div class="kpi warm"><?= icon('mail') ?><div><b><?= $count_messages ?></b><span><?= e($t['kpi_messages']) ?></span></div></div>
        <div class="kpi"><?= icon('calendar') ?><div><b><?= $count_appointments ?></b><span><?= e($t['kpi_appointments']) ?></span></div></div>
        <div class="kpi"><?= icon('coin') ?><div><b><?= $count_payments ?></b><span><?= e($t['kpi_payments']) ?></span></div></div>
        <div class="kpi"><?= icon('user-check') ?><div><b><?= $count_pending ?></b><span><?= e($t['kpi_pending']) ?></span></div></div>
    </header>

    <!-- INHALT -->
    <main class="main">

        <?php if ($success !== ""): ?>
            <div class="success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="title-row">
            <div>
                <h1><?= e($t['page_title']) ?></h1>
                <p><?= e($t['subtitle']) ?></p>
            </div>
        </div>

        <?php if ($only_row): ?>
            <div class="only">
                <?= avatar($only_row, 'av sm') ?>
                <span><?= e(sprintf($t['only_client'], full_name($only_row))) ?></span>
                <a class="btn sm" href="messages.php?filter=<?= e($filter) ?>"><?= e($t['clear_filter']) ?></a>
            </div>
        <?php endif; ?>

        <!-- REITER (abas) -->
        <div class="tabs">
            <?php foreach (['0', '1', '2', 'all'] as $tab): ?>
                <a href="messages.php?filter=<?= $tab ?><?= $only_client > 0 ? '&client=' . $only_client : '' ?>" class="tab <?= $filter === $tab ? 'on' : '' ?>">
                    <?= e($t['tab_' . $tab]) ?> <span class="n"><?= (int)$counts[$tab === 'all' ? 'all' : (int)$tab] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <?php if (count($messages) === 0): ?>
                <div class="empty"><?= e($t['empty']) ?></div>
            <?php endif; ?>

            <?php foreach ($messages as $m): ?>
                <?php
                    $st = (int)$m['status'];
                    $b  = $msg_badges[$st] ?? ['-', 'b-gray'];
                    $has_err = ($reply_error !== "" && $reply_error_id === (int)$m['message_id']);
                ?>
                <div class="msg">
                    <div class="msg-head">
                        <?= avatar($m) ?>
                        <div class="msg-who">
                            <a class="who" href="clients.php?id=<?= (int)$m['client_id'] ?>"><?= e(full_name($m)) ?></a>
                            <div class="msg-date"><?= e(sprintf($t['sent_on'], long_date($m['created_at']) . ', ' . fmt_time(substr($m['created_at'], 11)))) ?></div>
                        </div>
                        <span class="bd <?= e($b[1]) ?>"><?= e($b[0]) ?></span>
                    </div>
                    <div class="msg-subject"><?= e($m['subject']) ?></div>
                    <div class="msg-text"><?= e($m['message_text']) ?></div>

                    <?php if (trim($m['nutritionist_reply'] ?? '') !== ''): ?>
                        <div class="reply">
                            <div class="reply-head"><?= e($t['your_reply']) ?>
                                <?php if ($m['answered_at']): ?>
                                    <span class="msg-date">· <?= e(sprintf($t['answered_on'], long_date($m['answered_at']) . ', ' . fmt_time(substr($m['answered_at'], 11)))) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="msg-text"><?= e($m['nutritionist_reply']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($st === 0): ?>
                        <form method="post" action="messages.php" class="reply-form">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="form" value="reply">
                            <input type="hidden" name="message_id" value="<?= (int)$m['message_id'] ?>">
                            <input type="hidden" name="filter" value="<?= e($filter) ?>">
                            <input type="hidden" name="client" value="<?= (int)$only_client ?>">
                            <?php if ($has_err): ?><div class="error"><?= e($reply_error) ?></div><?php endif; ?>
                            <label for="r<?= (int)$m['message_id'] ?>"><?= e($t['reply_label']) ?></label>
                            <div class="field"><textarea id="r<?= (int)$m['message_id'] ?>" name="reply" rows="4" maxlength="5000" placeholder="<?= e($t['reply_ph']) ?>" required><?= $has_err ? e($v_reply) : '' ?></textarea></div>
                            <div class="form-btns">
                                <button type="submit" class="btn fill"><?= icon('check', 'w') ?><?= e($t['btn_reply']) ?></button>
                                <span class="muted"><?= e($t['reply_hint']) ?></span>
                            </div>
                        </form>
                    <?php elseif ($st === 1): ?>
                        <form method="post" action="messages.php" class="inl" onsubmit="return confirm(<?= e(json_encode($t['close_confirm'])) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="form" value="close">
                            <input type="hidden" name="message_id" value="<?= (int)$m['message_id'] ?>">
                            <input type="hidden" name="filter" value="<?= e($filter) ?>">
                            <input type="hidden" name="client" value="<?= (int)$only_client ?>">
                            <button type="submit" class="btn sm"><?= e($t['btn_close']) ?></button>
                        </form>
                    <?php else: ?>
                        <div class="muted"><?= e($t['closed_note']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

</body>
</html>
