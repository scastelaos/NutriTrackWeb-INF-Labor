<?php
// documents.php
// Seite der Ärztin (Admin) für die DOKUMENTE (PDF) der Kunden:
//  alle Dokumente sehen (mit Filter nach Kunde und Typ), öffnen/herunterladen
//  und ein Dokument AN einen freigegebenen Kunden senden (Nutrition Plan oder Other).
// Regeln der V1: nur PDF, bis 10 MB, keine Begrenzung der Anzahl, kein Löschen.
// Die Dateien liegen in uploads/documents/ (gesperrter Ordner); zum Öffnen immer über download.php
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
        'only_client' => 'Showing only documents from %s', 'clear_filter' => 'Show all clients',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clients', 'nav_appointments' => 'Appointments',
        'nav_messages' => 'Messages', 'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos',
        'nav_payments' => 'Payments', 'nav_settings' => 'Profile Settings', 'logout' => 'Logout',
        'kpi_messages' => 'New Messages', 'kpi_appointments' => 'Upcoming Appointments',
        'kpi_payments' => 'Open Payments', 'kpi_pending' => 'Pending Approvals',
        'page_title' => 'Documents', 'subtitle' => 'All client PDFs in one place, and the nutrition plans you send.',
        'sec_upload' => 'Send a document to a client', 'client' => 'Client', 'choose_client' => 'Choose a client',
        'doc_type' => 'Document type', 'file' => 'PDF file', 'choose_file' => 'Choose PDF', 'no_file' => 'No file chosen',
        'upload_hint' => 'PDF only, maximum 10 MB per file. The client can view and download it.',
        'btn_upload' => 'Upload document',
        'type_1' => 'Blood Test', 'type_2' => 'Nutrition Plan', 'type_3' => 'Payment Proof', 'type_4' => 'Other',
        'f_search' => 'Search', 'search_ph' => 'Client name or file name', 'f_sort' => 'Sort by', 'sort_az' => 'Name A-Z', 'sort_za' => 'Name Z-A', 'sort_new' => 'Newest first', 'sort_old' => 'Oldest first',
        'sec_list' => 'All documents', 'filter_client' => 'Client', 'filter_type' => 'Type',
        'all_clients' => 'All clients', 'all_types' => 'All types', 'btn_filter' => 'Filter', 'btn_clear' => 'Clear',
        'empty' => 'No documents found.', 'col_client' => 'Client', 'col_name' => 'File', 'col_type' => 'Type', 'col_date' => 'Date',
        'btn_view' => 'View', 'btn_download' => 'Download', 'page_of' => 'Page %1$d of %2$d', 'prev' => 'Previous', 'next' => 'Next',
        'total' => '%d documents',
        'done_uploaded' => 'Document uploaded.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_client' => 'Please choose an approved client.',
        'err_type' => 'Please choose a valid document type.',
        'err_nofile' => 'Please choose a PDF file.',
        'err_toobig' => 'The file is too large. The maximum is 10 MB.',
        'err_notpdf' => 'Only PDF files are allowed.',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'role' => 'Nutricionista',
        'only_client' => 'Mostrando só os documentos de %s', 'clear_filter' => 'Mostrar todas as clientes',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clientes', 'nav_appointments' => 'Consultas',
        'nav_messages' => 'Mensagens', 'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições',
        'nav_payments' => 'Pagamentos', 'nav_settings' => 'Configurações do Perfil', 'logout' => 'Sair',
        'kpi_messages' => 'Novas Mensagens', 'kpi_appointments' => 'Próximas Consultas',
        'kpi_payments' => 'Pagamentos em Aberto', 'kpi_pending' => 'Aprovações Pendentes',
        'page_title' => 'Documentos', 'subtitle' => 'Todos os PDFs das clientes em um só lugar, e os planos alimentares que você envia.',
        'sec_upload' => 'Enviar um documento para uma cliente', 'client' => 'Cliente', 'choose_client' => 'Escolha uma cliente',
        'doc_type' => 'Tipo de documento', 'file' => 'Arquivo PDF', 'choose_file' => 'Escolher PDF', 'no_file' => 'Nenhum arquivo escolhido',
        'upload_hint' => 'Somente PDF, no máximo 10 MB por arquivo. A cliente pode ver e baixar.',
        'btn_upload' => 'Enviar documento',
        'type_1' => 'Exame de sangue', 'type_2' => 'Plano alimentar', 'type_3' => 'Comprovante de pagamento', 'type_4' => 'Outro',
        'f_search' => 'Buscar', 'search_ph' => 'Nome da cliente ou do arquivo', 'f_sort' => 'Ordenar por', 'sort_az' => 'Nome A-Z', 'sort_za' => 'Nome Z-A', 'sort_new' => 'Mais recentes', 'sort_old' => 'Mais antigos',
        'sec_list' => 'Todos os documentos', 'filter_client' => 'Cliente', 'filter_type' => 'Tipo',
        'all_clients' => 'Todas as clientes', 'all_types' => 'Todos os tipos', 'btn_filter' => 'Filtrar', 'btn_clear' => 'Limpar',
        'empty' => 'Nenhum documento encontrado.', 'col_client' => 'Cliente', 'col_name' => 'Arquivo', 'col_type' => 'Tipo', 'col_date' => 'Data',
        'btn_view' => 'Ver', 'btn_download' => 'Baixar', 'page_of' => 'Página %1$d de %2$d', 'prev' => 'Anterior', 'next' => 'Próxima',
        'total' => '%d documentos',
        'done_uploaded' => 'Documento enviado.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_client' => 'Escolha uma cliente aprovada.',
        'err_type' => 'Escolha um tipo de documento válido.',
        'err_nofile' => 'Escolha um arquivo PDF.',
        'err_toobig' => 'O arquivo é grande demais. O máximo é 10 MB.',
        'err_notpdf' => 'Somente arquivos PDF são permitidos.',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'role' => 'Ernährungsberaterin',
        'only_client' => 'Es werden nur Dokumente von %s angezeigt', 'clear_filter' => 'Alle Kunden anzeigen',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Kunden', 'nav_appointments' => 'Termine',
        'nav_messages' => 'Nachrichten', 'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos',
        'nav_payments' => 'Zahlungen', 'nav_settings' => 'Profileinstellungen', 'logout' => 'Abmelden',
        'kpi_messages' => 'Neue Nachrichten', 'kpi_appointments' => 'Bevorstehende Termine',
        'kpi_payments' => 'Offene Zahlungen', 'kpi_pending' => 'Ausstehende Freigaben',
        'page_title' => 'Dokumente', 'subtitle' => 'Alle PDFs der Kunden an einem Ort und die Ernährungspläne, die Sie senden.',
        'sec_upload' => 'Dokument an eine Kundin senden', 'client' => 'Kundin', 'choose_client' => 'Kundin auswählen',
        'doc_type' => 'Dokumenttyp', 'file' => 'PDF-Datei', 'choose_file' => 'PDF auswählen', 'no_file' => 'Keine Datei ausgewählt',
        'upload_hint' => 'Nur PDF, maximal 10 MB pro Datei. Die Kundin kann es ansehen und herunterladen.',
        'btn_upload' => 'Dokument hochladen',
        'type_1' => 'Blutbild', 'type_2' => 'Ernährungsplan', 'type_3' => 'Zahlungsnachweis', 'type_4' => 'Sonstiges',
        'f_search' => 'Suche', 'search_ph' => 'Name der Kundin oder der Datei', 'f_sort' => 'Sortieren', 'sort_az' => 'Name A-Z', 'sort_za' => 'Name Z-A', 'sort_new' => 'Neueste zuerst', 'sort_old' => 'Älteste zuerst',
        'sec_list' => 'Alle Dokumente', 'filter_client' => 'Kundin', 'filter_type' => 'Typ',
        'all_clients' => 'Alle Kunden', 'all_types' => 'Alle Typen', 'btn_filter' => 'Filtern', 'btn_clear' => 'Zurücksetzen',
        'empty' => 'Keine Dokumente gefunden.', 'col_client' => 'Kundin', 'col_name' => 'Datei', 'col_type' => 'Typ', 'col_date' => 'Datum',
        'btn_view' => 'Ansehen', 'btn_download' => 'Herunterladen', 'page_of' => 'Seite %1$d von %2$d', 'prev' => 'Zurück', 'next' => 'Weiter',
        'total' => '%d Dokumente',
        'done_uploaded' => 'Dokument hochgeladen.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_client' => 'Bitte wählen Sie eine freigegebene Kundin.',
        'err_type' => 'Bitte wählen Sie einen gültigen Dokumenttyp.',
        'err_nofile' => 'Bitte wählen Sie eine PDF-Datei.',
        'err_toobig' => 'Die Datei ist zu groß. Das Maximum sind 10 MB.',
        'err_notpdf' => 'Nur PDF-Dateien sind erlaubt.',
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

// BLOCK 6: Filter (Adresszeile) und was passiert, wenn ein PDF gesendet wird
$max_bytes   = 10 * 1024 * 1024;                 // 10 MB (Höchstgröße in Bytes)
$upload_dir  = __DIR__ . '/uploads/documents';   // Ordner (durch .htaccess gesperrt)
$admin_types = [2, 4];                           // Typen, die die ÄRZTIN sendet: Nutrition Plan und Other

// Filter der Liste: Typ, Suche und Reihenfolge. ?client=ID (kommt von der Abkürzung im Profil des Kunden) filtert die Liste
// auf diesen Kunden und wählt ihn auch im Sendeformular schon aus
$f_client = (int)($_GET['client'] ?? 0);
$f_type   = (int)($_GET['type'] ?? 0);
if (!in_array($f_type, [0, 1, 2, 3, 4], true)) { $f_type = 0; }
// Textsuche (Vorname, Nachname oder Dateiname) und Reihenfolge der Liste
$f_q    = trim((string)($_GET['q'] ?? ''));
$f_q    = mb_substr($f_q, 0, 60);
$f_sort = (string)($_GET['sort'] ?? 'az');
if (!in_array($f_sort, ['az', 'za', 'new', 'old'], true)) { $f_sort = 'az'; }
$page = max(1, (int)($_GET['page'] ?? 1));

$error  = "";
$v_client = $f_client;      // im Sendeformular schon ausgewählter Kunde
$v_type   = "2";

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token    = $_POST['csrf_token'] ?? '';
    $v_client = (int)($_POST['client_id'] ?? 0);
    $v_type   = (string)($_POST['doc_type'] ?? '2');

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = $t['err_toobig'];      // war größer als post_max_size: dann verwirft PHP alles
    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    } elseif (!hash_equals($_SESSION['csrf_token'], (string)$token) || ($_POST['form'] ?? '') !== 'upload') {
        $error = $t['err_invalid'];
    } elseif (!in_array((int)$v_type, $admin_types, true)) {
        $error = $t['err_type'];
    } else {
        // der Kunde muss existieren und FREIGEGEBEN sein
        $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE client_id = ? AND approval_status = 1");
        $stmt->execute([$v_client]);
        $target_client = $stmt->fetchColumn();

        $f   = $_FILES['pdf'] ?? null;
        $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;

        if (!$target_client) {
            $error = $t['err_client'];
        } elseif ($err === UPLOAD_ERR_NO_FILE || !$f) {
            $error = $t['err_nofile'];
        } elseif ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $error = $t['err_toobig'];
        } elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $error = $t['err_other'];
        } elseif ($f['size'] > $max_bytes) {
            $error = $t['err_toobig'];
        } else {
            // prüfen, ob es WIRKLICH ein PDF ist: Endung .pdf und die ersten 5 Bytes "%PDF-"
            $original = basename(str_replace('\\', '/', (string)$f['name']));
            $original = preg_replace('/[\x00-\x1F\x7F]+/', '', $original);
            $fh    = fopen($f['tmp_name'], 'rb');
            $magic = $fh ? fread($fh, 5) : '';
            if ($fh) { fclose($fh); }

            if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'pdf' || $magic !== '%PDF-') {
                $error = $t['err_notpdf'];
            } else {
                if (mb_strlen($original) > 200) {
                    $original = mb_substr($original, 0, 196) . '.pdf';
                }
                $stored = bin2hex(random_bytes(16)) . '.pdf';      // zufälliger Name auf der Festplatte (random_bytes = zufällige Zeichen, bin2hex macht daraus Text)
                $target = $upload_dir . '/' . $stored;

                // move_uploaded_file() verschiebt die hochgeladene Datei vom Temp-Ordner des Servers in den Zielordner (geht nur bei echten Uploads).
                if (!is_dir($upload_dir) || !move_uploaded_file($f['tmp_name'], $target)) {
                    $error = $t['err_other'];
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            "INSERT INTO documents (document_type, file_name, file_path, uploaded_at, clients_client_id)
                             VALUES (?, ?, ?, NOW(), ?)"
                        );
                        $stmt->execute([(int)$v_type, $original, 'uploads/documents/' . $stored, (int)$target_client]);

                        header("Location: documents.php?done=uploaded");
                        exit;
                    } catch (PDOException $ex) {
                        // unlink() löscht eine Datei von der Festplatte.
                        @unlink($target);        // keine "losen" Dateien übrig lassen, wenn die Datenbank einen Fehler hatte (unlink = Datei löschen)
                        $error = $t['err_other'];
                    }
                }
            }
        }
    }
}

$success = (($_GET['done'] ?? '') === 'uploaded') ? $t['done_uploaded'] : "";

// BLOCK 7: Daten der Seite

// 7a: Listen für die Auswahlfelder der Kunden
$all_clients = $pdo->query(
    "SELECT client_id, first_name, last_name, profile_photo_path, approval_status FROM clients ORDER BY first_name, last_name"
)->fetchAll(PDO::FETCH_ASSOC);

// Kunde des Filters (nur, wenn er existiert)
$only_row = null;
foreach ($all_clients as $c) {
    if ((int)$c['client_id'] === $f_client) { $only_row = $c; }
}

// 7b: die Liste der Dokumente (mit den Filtern), 20 pro Seite
$per_page = 20;
$where  = [];
$params = [];
if ($only_row)    { $where[] = "d.clients_client_id = ?";  $params[] = (int)$only_row['client_id']; }
if ($f_type > 0)   { $where[] = "d.document_type = ?";     $params[] = $f_type; }
if ($f_q !== '') {
    // sucht im Vornamen, im Nachnamen, im vollen Namen und im Dateinamen
    $like = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $f_q) . '%';
    $where[] = "(c.first_name LIKE ? ESCAPE '|' OR c.last_name LIKE ? ESCAPE '|'
                 OR CONCAT(c.first_name, ' ', c.last_name) LIKE ? ESCAPE '|' OR d.file_name LIKE ? ESCAPE '|')";
    array_push($params, $like, $like, $like, $like);
}
$where_sql = count($where) ? " WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM documents d JOIN clients c ON c.client_id = d.clients_client_id" . $where_sql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));
if ($page > $pages) { $page = $pages; }
$offset = ($page - 1) * $per_page;

$orders = [
    'az'  => "c.first_name ASC, c.last_name ASC, d.clients_client_id ASC, d.uploaded_at DESC, d.document_id DESC",
    'za'  => "c.first_name DESC, c.last_name DESC, d.clients_client_id DESC, d.uploaded_at DESC, d.document_id DESC",
    'new' => "d.uploaded_at DESC, d.document_id DESC",
    'old' => "d.uploaded_at ASC, d.document_id ASC",
];
$order_sql = $orders[$f_sort];

$stmt = $pdo->prepare(
    "SELECT d.document_id, d.document_type, d.file_name, d.uploaded_at,
            c.client_id, c.first_name, c.last_name, c.profile_photo_path
     FROM documents d
     JOIN clients c ON c.client_id = d.clients_client_id"
    . $where_sql
    . " ORDER BY " . $order_sql . " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset
);
$stmt->execute($params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Basisadresse für die Seitenlinks (behält die Filter)
$qs = ($only_row ? '&client=' . (int)$only_row['client_id'] : '') . ($f_type > 0 ? '&type=' . $f_type : '') . ($f_q !== '' ? '&q=' . urlencode($f_q) : '') . '&sort=' . $f_sort;

// 7c: die 4 Zahlen im Kopf
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

// Einträge des Seitenmenüs: [Datei, Symbol, Text]
$current = "documents.php";
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
        .only .btn { margin-left: auto; }
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


        /* Dokumente */
        .up-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px 12px; margin-bottom: 10px; align-items: end; }
        .file-pick { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .file-pick input[type=file] { display: none; }
        #file-name { font-size: 12.5px; color: #666; word-break: break-all; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .filters .field { min-width: 180px; }
        .scroll { overflow-x: auto; }
        .docs { width: 100%; border-collapse: collapse; font-size: 13px; }
        .docs th { text-align: left; color: #777; font-weight: normal; padding: 5px 8px 5px 0; border-bottom: 1px solid #E5E8E5; white-space: nowrap; }
        .docs td { padding: 7px 8px 7px 0; border-bottom: 1px solid #F0F2F0; vertical-align: middle; }
        .plink { color: var(--primary); font-weight: bold; }
        .plink:hover { text-decoration: underline; }
        .docs .who { display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .docs .fname { word-break: break-word; }
        .docs .acts { white-space: nowrap; }
        .docs .acts .btn { margin: 2px 0 2px 4px; }
        .pager { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 12px; font-size: 12.5px; color: #666; }
        label.fl { display: block; font-size: 12px; color: #555; margin-bottom: 4px; }

        @media (max-width: 900px) {
            .up-grid { grid-template-columns: 1fr; }
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
                <a class="btn sm" href="documents.php"><?= e($t['clear_filter']) ?></a>
            </div>
        <?php endif; ?>

        <!-- DOKUMENT AN EINEN KUNDEN SENDEN -->
        <div class="card">
            <div class="ct" style="font-weight:bold;color:var(--primary);margin-bottom:10px"><?= icon('documents') ?> <?= e($t['sec_upload']) ?></div>
            <form method="post" action="documents.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="form" value="upload">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$max_bytes ?>">

                <div class="up-grid">
                    <div>
                        <label class="fl" for="client_id"><?= e($t['client']) ?></label>
                        <div class="field">
                            <select id="client_id" name="client_id" required>
                                <option value=""><?= e($t['choose_client']) ?></option>
                                <?php foreach ($all_clients as $c): ?>
                                    <?php if ((int)$c['approval_status'] !== 1) { continue; } ?>
                                    <option value="<?= (int)$c['client_id'] ?>" <?= (int)$c['client_id'] === $v_client ? 'selected' : '' ?>><?= e(full_name($c)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="fl" for="doc_type"><?= e($t['doc_type']) ?></label>
                        <div class="field">
                            <select id="doc_type" name="doc_type" required>
                                <?php foreach ($admin_types as $ty): ?>
                                    <option value="<?= $ty ?>" <?= (string)$ty === $v_type ? 'selected' : '' ?>><?= e($t['type_' . $ty]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="fl"><?= e($t['file']) ?></label>
                        <div class="file-pick">
                            <label class="btn sm" for="pdf"><?= icon('documents') ?><?= e($t['choose_file']) ?></label>
                            <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf" required>
                            <span id="file-name"><?= e($t['no_file']) ?></span>
                        </div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <button type="submit" class="btn fill"><?= icon('documents', 'w') ?><?= e($t['btn_upload']) ?></button>
                    <span style="font-size:11.5px;color:#777"><?= e($t['upload_hint']) ?></span>
                </div>
            </form>
        </div>

        <!-- LISTE MIT FILTERN -->
        <div class="card">
            <div class="ct" style="font-weight:bold;color:var(--primary);margin-bottom:10px"><?= icon('documents') ?> <?= e($t['sec_list']) ?>
                <span style="font-weight:normal;color:#777;font-size:12px">· <?= e(sprintf($t['total'], $total)) ?></span></div>

            <form method="get" action="documents.php" class="filters">
                <?php if ($only_row): ?><input type="hidden" name="client" value="<?= (int)$only_row['client_id'] ?>"><?php endif; ?>
                <div>
                    <label class="fl" for="f_q"><?= e($t['f_search']) ?></label>
                    <div class="field"><input type="text" id="f_q" name="q" value="<?= e($f_q) ?>" maxlength="60" placeholder="<?= e($t['search_ph']) ?>"></div>
                </div>
                <div>
                    <label class="fl" for="f_type"><?= e($t['filter_type']) ?></label>
                    <div class="field">
                        <select id="f_type" name="type">
                            <option value="0"><?= e($t['all_types']) ?></option>
                            <?php foreach ([1, 2, 3, 4] as $ty): ?>
                                <option value="<?= $ty ?>" <?= $ty === $f_type ? 'selected' : '' ?>><?= e($t['type_' . $ty]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fl" for="f_sort"><?= e($t['f_sort']) ?></label>
                    <div class="field">
                        <select id="f_sort" name="sort">
                            <?php foreach (['az', 'za', 'new', 'old'] as $so): ?>
                                <option value="<?= $so ?>" <?= $so === $f_sort ? 'selected' : '' ?>><?= e($t['sort_' . $so]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn"><?= e($t['btn_filter']) ?></button>
                <?php if ($f_type > 0 || $f_q !== '' || $f_sort !== 'az'): ?>
                    <a class="btn" href="documents.php"><?= e($t['btn_clear']) ?></a>
                <?php endif; ?>
            </form>

            <?php if (count($docs) === 0): ?>
                <div class="empty"><?= e($t['empty']) ?></div>
            <?php else: ?>
                <div class="scroll">
                <table class="docs">
                    <thead>
                        <tr>
                            <th><?= e($t['col_client']) ?></th>
                            <th><?= e($t['col_name']) ?></th>
                            <th><?= e($t['col_type']) ?></th>
                            <th><?= e($t['col_date']) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($docs as $d): ?>
                        <?php
                        $ty = (int)$d['document_type'];
                        $type_label = in_array($ty, [1, 2, 3, 4], true) ? $t['type_' . $ty] : '-';
                        $type_class = [1 => 'b-info', 2 => 'b-ok', 3 => 'b-gray', 4 => 'b-gray'][$ty] ?? 'b-gray';
                        ?>
                        <tr>
                            <td><div class="who"><?= avatar($d, 'av sm') ?><a class="plink" href="clients.php?id=<?= (int)$d['client_id'] ?>"><?= e(full_name($d)) ?></a></div></td>
                            <td class="fname"><?= e($d['file_name']) ?></td>
                            <td><span class="bd <?= e($type_class) ?>"><?= e($type_label) ?></span></td>
                            <td><?= e(long_date($d['uploaded_at'])) ?></td>
                            <td class="acts">
                                <a class="btn sm" href="download.php?id=<?= (int)$d['document_id'] ?>" target="_blank" rel="noopener"><?= e($t['btn_view']) ?></a>
                                <a class="btn sm" href="download.php?id=<?= (int)$d['document_id'] ?>&amp;download=1"><?= e($t['btn_download']) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php if ($pages > 1): ?>
                    <div class="pager">
                        <span><?= e(sprintf($t['page_of'], $page, $pages)) ?></span>
                        <span>
                            <?php if ($page > 1): ?><a class="btn sm" href="documents.php?page=<?= $page - 1 ?><?= e($qs) ?>"><?= e($t['prev']) ?></a><?php endif; ?>
                            <?php if ($page < $pages): ?><a class="btn sm" href="documents.php?page=<?= $page + 1 ?><?= e($qs) ?>"><?= e($t['next']) ?></a><?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
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
</script>

</body>
</html>
