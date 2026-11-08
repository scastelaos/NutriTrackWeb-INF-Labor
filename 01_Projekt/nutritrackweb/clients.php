<?php
// clients.php
// Seite der Ärztin (Admin), um die KUNDEN ZU VERWALTEN.
//  - ohne ?id=  : LISTE aller Kunden (Suche nach Name + Statusfilter, 20 pro Seite)
//  - mit ?id=N  : PROFIL des Kunden (nur freigegebene; die anderen öffnen die Prüfung der Registrierung)
// Regeln der V1:
//  - die Ärztin ändert NICHT die persönlichen Daten des Kunden (das macht nur der Kunde selbst)
//  - die Ärztin ändert nur die MONATSGEBÜHR (clients.monthly_fee, darf leer bleiben) und die WÄHRUNG (EUR, USD oder BRL)
//  - Kunden deaktivieren/reaktivieren ist in der V1 NICHT vorgesehen (siehe Pflichtenheft, Abschnitt 5.3)
//  - die Messwerte (Gewicht/Größe) sind nur zum Lesen: sie werden vom Kunden eingetragen
//  - die Abkürzungen öffnen Termine, Nachrichten, Dokumente, Fotos und Zahlungen schon für diesen Kunden gefiltert
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
        'page_title' => 'Clients', 'subtitle' => 'All your clients in one place.',
        'search' => 'Search by name', 'f_status' => 'Status', 'btn_filter' => 'Filter', 'btn_clear' => 'Clear',
        'total' => '%d clients', 'empty' => 'No clients found.',
        'fs_all' => 'All', 'fs_approved' => 'Approved', 'fs_pending' => 'Pending', 'fs_more' => 'More information',
        'fs_rejected' => 'Rejected',
        'col_client' => 'Client', 'col_status' => 'Status', 'col_next' => 'Next appointment', 'col_open' => 'Open payments', 'col_weight' => 'Last measurement',
        'overdue' => '%d overdue', 'review' => 'Review registration', 'open_profile' => 'Open profile',
        'page_of' => 'Page %1$d of %2$d', 'prev' => 'Previous', 'next' => 'Next',
        'back' => 'Clients', 'since' => 'Client since %s', 'years' => '%d years',
        'tile_next' => 'Next appointment', 'tile_msgs' => 'Open messages', 'tile_pay' => 'Open payments', 'tile_weight' => 'Last weight',
        'none' => 'None', 'no_data' => 'No data yet',
        'sec_personal' => 'Personal data', 'email' => 'Email', 'phone' => 'Phone', 'location' => 'Location', 'birth' => 'Date of birth',
        'language' => 'Language', 'goal' => 'Goal', 'registered' => 'Registered on',
        'readonly_hint' => 'Personal data can only be changed by the client herself.',
        'sec_fee' => 'Monthly fee', 'fee' => 'Amount', 'currency' => 'Currency', 'fee_hint' => 'Leave the amount empty if no fee is set yet.',
        'btn_save' => 'Save',
        'sec_weight' => 'Weight evolution', 'weight_hist' => 'Measurements', 'col_date' => 'Date', 'col_w' => 'Weight (kg)', 'col_h' => 'Height (cm)',
        'need_two' => 'At least two measurements are needed for the chart.', 'change' => 'Change: %s kg', 'measure_hint' => 'Measurements are registered by the client.',
        'sec_short' => 'Shortcuts', 'sh_appointments' => 'Appointments', 'sh_messages' => 'Messages', 'sh_documents' => 'Documents', 'sh_photos' => 'Meal Photos', 'sh_payments' => 'Payments',
        'st_approved' => 'Approved', 'st_pending' => 'Pending', 'st_more' => 'More information', 'st_rejected' => 'Rejected',
        'done_fee' => 'Monthly fee saved.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_fee' => 'Please enter a valid amount (for example 120 or 120.50).',
        'err_currency' => 'Please choose a currency.',
        'err_done' => 'This item was already handled or is no longer available.',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'role' => 'Nutricionista',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clientes', 'nav_appointments' => 'Consultas',
        'nav_messages' => 'Mensagens', 'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições',
        'nav_payments' => 'Pagamentos', 'nav_settings' => 'Configurações do Perfil', 'logout' => 'Sair',
        'kpi_messages' => 'Novas Mensagens', 'kpi_appointments' => 'Próximas Consultas',
        'kpi_payments' => 'Pagamentos em Aberto', 'kpi_pending' => 'Aprovações Pendentes',
        'page_title' => 'Clientes', 'subtitle' => 'Todas as suas clientes num só lugar.',
        'search' => 'Buscar pelo nome', 'f_status' => 'Situação', 'btn_filter' => 'Filtrar', 'btn_clear' => 'Limpar',
        'total' => '%d clientes', 'empty' => 'Nenhuma cliente encontrada.',
        'fs_all' => 'Todas', 'fs_approved' => 'Aprovadas', 'fs_pending' => 'Pendentes', 'fs_more' => 'Mais informações',
        'fs_rejected' => 'Recusadas',
        'col_client' => 'Cliente', 'col_status' => 'Situação', 'col_next' => 'Próxima consulta', 'col_open' => 'Pagamentos em aberto', 'col_weight' => 'Última medida',
        'overdue' => '%d atrasado(s)', 'review' => 'Revisar cadastro', 'open_profile' => 'Abrir perfil',
        'page_of' => 'Página %1$d de %2$d', 'prev' => 'Anterior', 'next' => 'Próxima',
        'back' => 'Clientes', 'since' => 'Cliente desde %s', 'years' => '%d anos',
        'tile_next' => 'Próxima consulta', 'tile_msgs' => 'Mensagens abertas', 'tile_pay' => 'Pagamentos em aberto', 'tile_weight' => 'Último peso',
        'none' => 'Nenhuma', 'no_data' => 'Ainda sem dados',
        'sec_personal' => 'Dados pessoais', 'email' => 'E-mail', 'phone' => 'Telefone', 'location' => 'Local', 'birth' => 'Data de nascimento',
        'language' => 'Idioma', 'goal' => 'Objetivo', 'registered' => 'Cadastro em',
        'readonly_hint' => 'Os dados pessoais só podem ser alterados pela própria cliente.',
        'sec_fee' => 'Mensalidade', 'fee' => 'Valor', 'currency' => 'Moeda', 'fee_hint' => 'Deixe o valor vazio se ainda não houver mensalidade definida.',
        'btn_save' => 'Salvar',
        'sec_weight' => 'Evolução do peso', 'weight_hist' => 'Medidas', 'col_date' => 'Data', 'col_w' => 'Peso (kg)', 'col_h' => 'Altura (cm)',
        'need_two' => 'São necessárias pelo menos duas medidas para o gráfico.', 'change' => 'Variação: %s kg', 'measure_hint' => 'As medidas são registradas pela própria cliente.',
        'sec_short' => 'Atalhos', 'sh_appointments' => 'Consultas', 'sh_messages' => 'Mensagens', 'sh_documents' => 'Documentos', 'sh_photos' => 'Fotos das Refeições', 'sh_payments' => 'Pagamentos',
        'st_approved' => 'Aprovada', 'st_pending' => 'Pendente', 'st_more' => 'Mais informações', 'st_rejected' => 'Recusada',
        'done_fee' => 'Mensalidade salva.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_fee' => 'Informe um valor válido (por exemplo 120 ou 120,50).',
        'err_currency' => 'Escolha uma moeda.',
        'err_done' => 'Este item já foi tratado ou não está mais disponível.',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'role' => 'Ernährungsberaterin',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Kunden', 'nav_appointments' => 'Termine',
        'nav_messages' => 'Nachrichten', 'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos',
        'nav_payments' => 'Zahlungen', 'nav_settings' => 'Profileinstellungen', 'logout' => 'Abmelden',
        'kpi_messages' => 'Neue Nachrichten', 'kpi_appointments' => 'Bevorstehende Termine',
        'kpi_payments' => 'Offene Zahlungen', 'kpi_pending' => 'Ausstehende Freigaben',
        'page_title' => 'Kunden', 'subtitle' => 'Alle Ihre Kundinnen an einem Ort.',
        'search' => 'Nach Namen suchen', 'f_status' => 'Status', 'btn_filter' => 'Filtern', 'btn_clear' => 'Zurücksetzen',
        'total' => '%d Kundinnen', 'empty' => 'Keine Kundinnen gefunden.',
        'fs_all' => 'Alle', 'fs_approved' => 'Freigegeben', 'fs_pending' => 'Ausstehend', 'fs_more' => 'Weitere Angaben',
        'fs_rejected' => 'Abgelehnt',
        'col_client' => 'Kundin', 'col_status' => 'Status', 'col_next' => 'Nächster Termin', 'col_open' => 'Offene Zahlungen', 'col_weight' => 'Letzte Messung',
        'overdue' => '%d überfällig', 'review' => 'Anmeldung prüfen', 'open_profile' => 'Profil öffnen',
        'page_of' => 'Seite %1$d von %2$d', 'prev' => 'Zurück', 'next' => 'Weiter',
        'back' => 'Kunden', 'since' => 'Kundin seit %s', 'years' => '%d Jahre',
        'tile_next' => 'Nächster Termin', 'tile_msgs' => 'Offene Nachrichten', 'tile_pay' => 'Offene Zahlungen', 'tile_weight' => 'Letztes Gewicht',
        'none' => 'Keiner', 'no_data' => 'Noch keine Daten',
        'sec_personal' => 'Persönliche Daten', 'email' => 'E-Mail', 'phone' => 'Telefon', 'location' => 'Wohnort', 'birth' => 'Geburtsdatum',
        'language' => 'Sprache', 'goal' => 'Ziel', 'registered' => 'Registriert am',
        'readonly_hint' => 'Persönliche Daten kann nur die Kundin selbst ändern.',
        'sec_fee' => 'Monatsbeitrag', 'fee' => 'Betrag', 'currency' => 'Währung', 'fee_hint' => 'Lassen Sie den Betrag leer, wenn noch kein Beitrag festgelegt ist.',
        'btn_save' => 'Speichern',
        'sec_weight' => 'Gewichtsverlauf', 'weight_hist' => 'Messungen', 'col_date' => 'Datum', 'col_w' => 'Gewicht (kg)', 'col_h' => 'Größe (cm)',
        'need_two' => 'Für das Diagramm sind mindestens zwei Messungen nötig.', 'change' => 'Veränderung: %s kg', 'measure_hint' => 'Die Messungen trägt die Kundin selbst ein.',
        'sec_short' => 'Verknüpfungen', 'sh_appointments' => 'Termine', 'sh_messages' => 'Nachrichten', 'sh_documents' => 'Dokumente', 'sh_photos' => 'Mahlzeitenfotos', 'sh_payments' => 'Zahlungen',
        'st_approved' => 'Freigegeben', 'st_pending' => 'Ausstehend', 'st_more' => 'Weitere Angaben', 'st_rejected' => 'Abgelehnt',
        'done_fee' => 'Monatsbeitrag gespeichert.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_fee' => 'Bitte einen gültigen Betrag eingeben (z. B. 120 oder 120,50).',
        'err_currency' => 'Bitte eine Währung wählen.',
        'err_done' => 'Dieser Eintrag wurde bereits bearbeitet oder ist nicht mehr verfügbar.',
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
// gibt das Datum selbst (JJJJ-MM-TT) zurück, wenn es wirklich existiert; sonst "" (leer)
function valid_date($text) {
    $text = (string)$text;
    $d = DateTime::createFromFormat('Y-m-d', $text);
    if ($d && $d->format('Y-m-d') === $text) {
        return $text;
    }
    return "";
}

// BLOCK 5: Sicherheits-Token des Formulars (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Betrag mit Währungssymbol ("R$ 350.00"); ohne Betrag wird "" zurückgegeben
function money($fee, $currency) {
    if ($fee === null || $fee === '') {
        return "";
    }
    $symbols = ['BRL' => 'R$', 'EUR' => '€', 'USD' => '$'];
    $symbol  = $symbols[$currency] ?? $currency;
    return $symbol . ' ' . number_format((float)$fee, 2, '.', '');
}


// Namen der Sprachen (clients.preferred_language: 0 EN, 1 DE, 2 PT)
$lang_names = [0 => 'English', 1 => 'Deutsch', 2 => 'Português'];

// Alter in Jahren aus dem Geburtsdatum ("" wenn es keins gibt)
function age_years($birth) {
    if (!$birth) { return ""; }
    try {
        $b = new DateTime($birth);
        return (string)$b->diff(new DateTime('today'))->y;
    } catch (Exception $ex) {
        return "";
    }
}

// Gewicht/Größe mit einer Nachkommastelle ("72.5"); leer ergibt "-"
function num1($n) {
    if ($n === null || $n === '') { return "-"; }
    return rtrim(rtrim(number_format((float)$n, 1, '.', ''), '0'), '.');
}

$today = date('Y-m-d');

// BLOCK 6: Was passiert, wenn ein Formular abgeschickt wird (nur im Profil des Kunden)
$error = "";
$post_id = (int)($_POST['id'] ?? 0);
$f_fee = null;   // eingegebene Werte (damit sie bei einem Fehler nicht verloren gehen)
$f_cur = null;

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form  = $_POST['form'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        $error = $t['err_invalid'];

    } elseif ($form === 'save_fee' && $post_id > 0) {

        // nur FREIGEGEBENE Kunden dürfen hier geändert werden
        $stmt = $pdo->prepare(
            "SELECT c.client_id, c.users_user_id FROM clients c
             JOIN users u ON u.user_id = c.users_user_id
             WHERE c.client_id = ? AND c.approval_status = 1 AND u.role = 0"
        );
        $stmt->execute([$post_id]);
        $target = $stmt->fetch();

        if (!$target) {
            $error = $t['err_done'];

        } elseif ($form === 'save_fee') {
            // ---------- MONATSGEBÜHR UND WÄHRUNG ----------
            $f_fee = trim((string)($_POST['monthly_fee'] ?? ''));
            $f_cur = (string)($_POST['currency'] ?? '');
            if (!in_array($f_cur, ['EUR', 'USD', 'BRL'], true)) {
                $error = $t['err_currency'];
            } elseif ($f_fee !== '' && !preg_match('/^\d{1,8}([.,]\d{1,2})?$/', $f_fee)) {
                $error = $t['err_fee'];
            } else {
                // leer = keine Monatsgebühr festgelegt (NULL); Komma wird zu Punkt
                $fee_value = ($f_fee === '') ? null : number_format((float)str_replace(',', '.', $f_fee), 2, '.', '');
                try {
                    $stmt = $pdo->prepare("UPDATE clients SET monthly_fee = ?, currency = ? WHERE client_id = ?");
                    $stmt->execute([$fee_value, $f_cur, $post_id]);
                    header("Location: clients.php?id=" . $post_id . "&done=fee");
                    exit;
                } catch (PDOException $ex) {
                    $error = $t['err_other'];
                }
            }

        }
    } else {
        $error = $t['err_invalid'];
    }
}

// Erfolgsmeldung (kommt von der Weiterleitung)
$success = "";
$done_messages = ['fee' => 'done_fee'];
if (isset($_GET['done']) && isset($done_messages[$_GET['done']])) {
    $success = $t[$done_messages[$_GET['done']]];
}

// BLOCK 7: Daten der Seite
$view_id = (int)($_GET['id'] ?? $post_id);
$c = null;   // der geöffnete Kunde (Profil); null = zeigt die Liste

if ($view_id > 0) {
    $stmt = $pdo->prepare(
        "SELECT c.client_id, c.first_name, c.last_name, c.profile_photo_path, c.phone, c.country, c.city,
                c.date_of_birth, c.preferred_language, c.goal, c.approval_status, c.registration_date,
                c.monthly_fee, c.currency, u.email, u.status AS user_status
         FROM clients c JOIN users u ON u.user_id = c.users_user_id
         WHERE c.client_id = ?"
    );
    $stmt->execute([$view_id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        header("Location: clients.php");
        exit;
    }
    // nur die freigegebenen haben ein volles Profil; die anderen gehen zur Prüfung der Registrierung
    if ((int)$c['approval_status'] !== 1) {
        header("Location: registration_review.php?id=" . (int)$c['client_id']);
        exit;
    }
}

if ($c) {
    // ---------- PROFIL: Mini-Dashboard und Messwerte ----------
    $cid = (int)$c['client_id'];

    // alle Messwerte, vom ältesten zum neuesten
    $stmt = $pdo->prepare(
        "SELECT weight, height, measurement_date FROM client_measurements
         WHERE clients_client_id = ? ORDER BY measurement_date ASC, measurement_id ASC"
    );
    $stmt->execute([$cid]);
    $meas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // letztes bekanntes Gewicht und letzte bekannte Größe
    $last_weight = null; $last_weight_date = null; $last_height = null;
    foreach ($meas as $m) {
        if ($m['weight'] !== null && $m['weight'] !== '') { $last_weight = $m['weight']; $last_weight_date = $m['measurement_date']; }
        if ($m['height'] !== null && $m['height'] !== '') { $last_height = $m['height']; }
    }

    // Punkte des Diagramms (nur Messwerte mit Gewicht; höchstens die letzten 30)
    $points = [];
    foreach ($meas as $m) {
        if ($m['weight'] !== null && $m['weight'] !== '') { $points[] = ['w' => (float)$m['weight'], 'd' => $m['measurement_date']]; }
    }
    $points = array_slice($points, -30);

    $fee_show = ($f_fee !== null) ? $f_fee : (($c['monthly_fee'] !== null && $c['monthly_fee'] !== '') ? number_format((float)$c['monthly_fee'], 2, '.', '') : '');
    $cur_show = ($f_cur !== null) ? $f_cur : ($c['currency'] ?: 'EUR');

} else {
    // ---------- LISTE ----------
    $f_status = (string)($_GET['status'] ?? '');
    if (!in_array($f_status, ['', 'approved', 'pending', 'more', 'rejected'], true)) { $f_status = ''; }
    $q = trim((string)($_GET['q'] ?? ''));
    $q = mb_substr($q, 0, 100);
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 20;

    $where  = [];
    $params = [];
    if ($f_status === 'approved') { $where[] = "c.approval_status = 1"; }
    if ($f_status === 'pending')  { $where[] = "c.approval_status = 0"; }
    if ($f_status === 'more')     { $where[] = "c.approval_status = 3"; }
    if ($f_status === 'rejected') { $where[] = "c.approval_status = 2"; }
    if ($q !== '') {
        $like = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $q) . '%';
        $where[] = "(c.first_name LIKE ? ESCAPE '|' OR c.last_name LIKE ? ESCAPE '|' OR CONCAT(c.first_name, ' ', c.last_name) LIKE ? ESCAPE '|')";
        array_push($params, $like, $like, $like);
    }
    $where_sql = count($where) ? " WHERE " . implode(" AND ", $where) : "";

    $from = " FROM clients c JOIN users u ON u.user_id = c.users_user_id";

    $stmt = $pdo->prepare("SELECT COUNT(*)" . $from . $where_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $per_page));
    if ($page > $pages) { $page = $pages; }
    $offset = ($page - 1) * $per_page;

    $stmt = $pdo->prepare(
        "SELECT c.client_id, c.first_name, c.last_name, c.profile_photo_path, c.approval_status, u.status AS user_status"
        . $from . $where_sql
        . " ORDER BY c.first_name ASC, c.last_name ASC, c.client_id ASC
            LIMIT " . (int)$per_page . " OFFSET " . (int)$offset
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // für jeden freigegebenen Kunden der Seite: nächster Termin, offene Zahlungen und letzter Messwert
    $q_next = $pdo->prepare(
        "SELECT MIN(appointment_date) FROM appointments
         WHERE clients_client_id = ? AND status IN ('scheduled', 'rescheduled') AND appointment_date >= ?"
    );
    $q_open = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE clients_client_id = ? AND (status IS NULL OR status = 0)");
    $q_late = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE clients_client_id = ? AND (status IS NULL OR status = 0) AND due_date < ?");
    $q_meas = $pdo->prepare(
        "SELECT weight, measurement_date FROM client_measurements
         WHERE clients_client_id = ? AND weight IS NOT NULL
         ORDER BY measurement_date DESC, measurement_id DESC LIMIT 1"
    );
    foreach ($rows as $i => $r) {
        $rows[$i]['next_date'] = null; $rows[$i]['open_pay'] = 0; $rows[$i]['late_pay'] = 0; $rows[$i]['last_meas'] = null;
        if ((int)$r['approval_status'] === 1) {
            $q_next->execute([$r['client_id'], $today]);
            $rows[$i]['next_date'] = $q_next->fetchColumn() ?: null;
            $q_open->execute([$r['client_id']]);
            $rows[$i]['open_pay'] = (int)$q_open->fetchColumn();
            $q_late->execute([$r['client_id'], $today]);
            $rows[$i]['late_pay'] = (int)$q_late->fetchColumn();
            $q_meas->execute([$r['client_id']]);
            $rows[$i]['last_meas'] = $q_meas->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    $qs = ($f_status !== '' ? '&status=' . $f_status : '') . ($q !== '' ? '&q=' . urlencode($q) : '');
}

// die 4 Zahlen im Kopf
$count_messages = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE status = 0")->fetchColumn();
$count_appointments = (int)$pdo->query(
    "SELECT COUNT(*) FROM appointments
     WHERE status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()"
)->fetchColumn();
$count_payments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 0")->fetchColumn();
$count_pending  = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE approval_status = 0")->fetchColumn();

// Status (Text + Farbe) eines Kunden
function status_badge($approval, $user_status) {
    global $t;
    if ((int)$approval === 1) { return [$t['st_approved'], 'b-ok']; }
    if ((int)$approval === 0) { return [$t['st_pending'], 'b-pen']; }
    if ((int)$approval === 3) { return [$t['st_more'], 'b-info']; }
    return [$t['st_rejected'], 'b-bad'];
}

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
        .tb .cl { display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .tb .acts { white-space: nowrap; }
        .tb .acts .btn { margin: 2px 0; }
        .lk .ico { width: 20px; height: 20px; }
        .b-gray { background: #E4E1D8; color: #444441; }
        .row2.inner { margin-bottom: 0; }
        .form-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .field input[readonly] { background: #F5F7F5; color: #555; }
        .field textarea { resize: vertical; min-height: 64px; }

        /* Zahlungen */
        .sq { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex: none; }
        .sq.ok { background: #DDEBD2; }
        .sq.no { background: #F9D6D6; }
        .sq .ico { opacity: .75; width: 16px; height: 16px; }
        button.sq { border: 0; padding: 0; cursor: pointer; font: inherit; }
        form.inl { margin: 0; display: inline-flex; }
        .paidform { margin: 0; }
        .b-ok { background: #DDEBD2; color: #27500A; }
        .b-red { background: #F9D6D6; color: #791F1F; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .filters .field { min-width: 170px; }
        label.fl { display: block; font-size: 12px; color: #555; margin-bottom: 4px; }
        .scroll { overflow-x: auto; }
        .pay { width: 100%; border-collapse: collapse; font-size: 13px; }
        .pay th { text-align: left; color: #777; font-weight: normal; padding: 5px 8px 5px 0; border-bottom: 1px solid #E5E8E5; white-space: nowrap; }
        .pay td { padding: 8px 8px 8px 0; border-bottom: 1px solid #F0F2F0; vertical-align: middle; }
        .pay .who { display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .pay .nowrap { white-space: nowrap; }
        .pay .muted { color: #888; font-size: 11.5px; }
        .pay .acts { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .paidform { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .paidform input[type=date] { font: inherit; font-size: 12px; padding: 4px 6px; border: 1px solid #D8DDD8; border-radius: 8px; }
        .pager { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 14px; font-size: 12.5px; color: #666; }
        .form3 { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 10px 12px; margin-bottom: 10px; align-items: end; }

        @media (max-width: 900px) {
            .form3 { grid-template-columns: 1fr; }
            .layout { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
            .side { grid-row: auto; }
            .top { grid-template-columns: 1fr 1fr; }
            .row2 { grid-template-columns: 1fr; }
        }

        /* Kunden */
        .only-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .cli { width: 100%; border-collapse: collapse; font-size: 13px; }
        .cli th { text-align: left; color: #777; font-weight: normal; padding: 5px 8px 5px 0; border-bottom: 1px solid #E5E8E5; white-space: nowrap; }
        .cli td { padding: 8px 8px 8px 0; border-bottom: 1px solid #F0F2F0; vertical-align: middle; }
        .cli .who { display: flex; align-items: center; gap: 8px; white-space: nowrap; font-weight: bold; }
        .cli .who:hover span { color: var(--primary); text-decoration: underline; }
        .cli .nowrap { white-space: nowrap; }
        .cli .muted { color: #888; font-size: 11.5px; }
        .cli tr.off .who { opacity: .65; }
        .late { color: #791F1F; font-size: 11.5px; font-weight: bold; }

        .tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
        .tile { background: #fff; border: 1px solid #D8DDD8; border-radius: 10px; padding: 12px; display: block; }
        .tile:hover { border-color: var(--primary); }
        .tile .tl { font-size: 12px; color: #666; display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
        .tile .tv { font-size: 18px; font-weight: bold; color: var(--primary); }
        .tile .ts { font-size: 11.5px; color: #888; margin-top: 2px; }
        .tile.alert .tv { color: #791F1F; }

        .grid2 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 14px; align-items: start; margin-bottom: 14px; }
        .grid2 .card { margin-bottom: 0; }
        .chart { width: 100%; height: auto; display: block; margin: 4px 0 8px; }
        .hist { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-top: 6px; }
        .hist th { text-align: left; color: #777; font-weight: normal; padding: 4px 8px 4px 0; border-bottom: 1px solid #E5E8E5; }
        .hist td { padding: 5px 8px 5px 0; border-bottom: 1px solid #F0F2F0; }
        .short { display: flex; gap: 8px; flex-wrap: wrap; }
        .fee-row { display: grid; grid-template-columns: 1.4fr 1fr; gap: 10px; margin-bottom: 8px; }
        .hint { font-size: 12px; color: #777; margin: 4px 0 10px; line-height: 1.4; }
        .badge-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 3px; }
        .card.off { background: #F4F2EC; }

        @media (max-width: 900px) {
            .tiles { grid-template-columns: 1fr 1fr; }
            .grid2 { grid-template-columns: 1fr; }
            .fee-row { grid-template-columns: 1fr; }
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

<?php if ($c): ?>
        <!-- ================= PROFIL DES KUNDEN ================= -->
        <div class="hd">
            <div class="crumb"><a href="clients.php"><?= e($t['back']) ?></a> › <?= e(full_name($c)) ?></div>
            <div class="hrow">
                <?= avatar($c, 'av-lg') ?>
                <div>
                    <div class="hname"><?= e(full_name($c)) ?></div>
                    <div class="badge-row">
                        <?php $b = status_badge($c['approval_status'], $c['user_status']); ?>
                        <span class="bd <?= e($b[1]) ?>"><?= e($b[0]) ?></span>
                        <?php if ($c['registration_date']): ?>
                            <span class="hsub"><?= e(sprintf($t['since'], long_date($c['registration_date']))) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success !== ""): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error !== ""): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <!-- ABKÜRZUNGEN (öffnen die Seite schon für diesen Kunden gefiltert) -->
        <div class="card">
            <div class="ct"><?= icon('arrow-right') ?><?= e($t['sec_short']) ?></div>
            <div class="short">
                <a class="btn" href="appointments.php?client=<?= (int)$c['client_id'] ?>"><?= icon('calendar') ?><?= e($t['sh_appointments']) ?></a>
                <a class="btn" href="messages.php?filter=all&amp;client=<?= (int)$c['client_id'] ?>"><?= icon('message') ?><?= e($t['sh_messages']) ?></a>
                <a class="btn" href="documents.php?client=<?= (int)$c['client_id'] ?>"><?= icon('documents') ?><?= e($t['sh_documents']) ?></a>
                <a class="btn" href="meal_photos.php?client=<?= (int)$c['client_id'] ?>"><?= icon('camera') ?><?= e($t['sh_photos']) ?></a>
                <a class="btn" href="payments.php?client=<?= (int)$c['client_id'] ?>"><?= icon('wallet') ?><?= e($t['sh_payments']) ?></a>
            </div>
        </div>


        <div class="grid2">
            <!-- PERSÖNLICHE DATEN (nur lesen) -->
            <div class="card">
                <div class="ct"><?= icon('user') ?><?= e($t['sec_personal']) ?></div>
                <div class="kv"><span><?= e($t['email']) ?></span><span><?= e($c['email']) ?></span></div>
                <div class="kv"><span><?= e($t['phone']) ?></span><span><?= e($c['phone'] ?: '-') ?></span></div>
                <div class="kv"><span><?= e($t['location']) ?></span><span><?= e(trim(($c['city'] ?? '') . ($c['city'] && $c['country'] ? ', ' : '') . ($c['country'] ?? '')) ?: '-') ?></span></div>
                <?php $age = age_years($c['date_of_birth']); ?>
                <div class="kv"><span><?= e($t['birth']) ?></span><span><?= $c['date_of_birth'] ? e(long_date($c['date_of_birth'])) . ($age !== '' ? ' (' . e(sprintf($t['years'], (int)$age)) . ')' : '') : '-' ?></span></div>
                <div class="kv"><span><?= e($t['language']) ?></span><span><?= e($lang_names[(int)$c['preferred_language']] ?? '-') ?></span></div>
                <div class="kv"><span><?= e($t['goal']) ?></span><span><?= e($c['goal'] ?: '-') ?></span></div>
                <div class="hint" style="margin-bottom:0"><?= e($t['readonly_hint']) ?></div>
            </div>

            <!-- MONATSGEBÜHR -->
            <div class="card">
                <div class="ct"><?= icon('coin') ?><?= e($t['sec_fee']) ?></div>
                <form method="post" action="clients.php?id=<?= (int)$c['client_id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="form" value="save_fee">
                    <input type="hidden" name="id" value="<?= (int)$c['client_id'] ?>">
                    <div class="fee-row">
                        <div>
                            <label class="fl" for="monthly_fee"><?= e($t['fee']) ?></label>
                            <div class="field"><input type="text" inputmode="decimal" id="monthly_fee" name="monthly_fee" maxlength="11" value="<?= e($fee_show) ?>"></div>
                        </div>
                        <div>
                            <label class="fl" for="currency"><?= e($t['currency']) ?></label>
                            <div class="field">
                                <select id="currency" name="currency">
                                    <?php foreach (['EUR' => 'EUR (€)', 'USD' => 'USD ($)', 'BRL' => 'BRL (R$)'] as $code => $label): ?>
                                        <option value="<?= $code ?>" <?= $cur_show === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="hint"><?= e($t['fee_hint']) ?></div>
                    <button type="submit" class="btn fill"><?= icon('check', 'w') ?><?= e($t['btn_save']) ?></button>
                </form>
            </div>
        </div>

        <div class="grid2">
            <!-- ENTWICKLUNG DES GEWICHTS (nur lesen) -->
            <div class="card">
                <div class="ct"><?= icon('chart') ?><?= e($t['sec_weight']) ?></div>
                <?php if (count($points) >= 2): ?>
                    <?php
                    // einfaches Liniendiagramm in SVG (hier selbst gebaut, ohne Bibliothek)
                    $W = 400; $H = 150; $pl = 36; $pr = 12; $pt = 14; $pb = 24;
                    $ws = array_column($points, 'w');
                    $wmin = min($ws); $wmax = max($ws);
                    if ($wmax - $wmin < 1) { $wmin -= 0.5; $wmax += 0.5; }
                    $n = count($points);
                    $xy = [];
                    foreach ($points as $i => $p) {
                        $x = $pl + ($W - $pl - $pr) * ($n > 1 ? $i / ($n - 1) : 0);
                        $y = $pt + ($H - $pt - $pb) * (1 - ($p['w'] - $wmin) / ($wmax - $wmin));
                        $xy[] = [round($x, 1), round($y, 1)];
                    }
                    $line = implode(' ', array_map(function ($p) { return $p[0] . ',' . $p[1]; }, $xy));
                    $diff = $points[$n - 1]['w'] - $points[0]['w'];
                    ?>
                    <svg class="chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= e($t['sec_weight']) ?>">
                        <line x1="<?= $pl ?>" y1="<?= $pt ?>" x2="<?= $pl ?>" y2="<?= $H - $pb ?>" stroke="#D8DDD8"/>
                        <line x1="<?= $pl ?>" y1="<?= $H - $pb ?>" x2="<?= $W - $pr ?>" y2="<?= $H - $pb ?>" stroke="#D8DDD8"/>
                        <text x="<?= $pl - 4 ?>" y="<?= $pt + 4 ?>" font-size="10" fill="#777" text-anchor="end"><?= e(num1($wmax)) ?></text>
                        <text x="<?= $pl - 4 ?>" y="<?= $H - $pb ?>" font-size="10" fill="#777" text-anchor="end"><?= e(num1($wmin)) ?></text>
                        <text x="<?= $pl ?>" y="<?= $H - 8 ?>" font-size="10" fill="#777"><?= e(long_date($points[0]['d'])) ?></text>
                        <text x="<?= $W - $pr ?>" y="<?= $H - 8 ?>" font-size="10" fill="#777" text-anchor="end"><?= e(long_date($points[$n - 1]['d'])) ?></text>
                        <polyline points="<?= e($line) ?>" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
                        <?php foreach ($xy as $p): ?>
                            <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3.5" fill="var(--primary)"/>
                        <?php endforeach; ?>
                    </svg>
                    <div class="hsub"><b><?= e(sprintf($t['change'], ($diff > 0 ? '+' : '') . num1($diff))) ?></b></div>
                <?php else: ?>
                    <div class="empty"><?= e($t['need_two']) ?></div>
                <?php endif; ?>
                <div class="hint" style="margin-bottom:0"><?= e($t['measure_hint']) ?></div>
            </div>

            <!-- VERLAUF DER MESSWERTE (die letzten 8) -->
            <div class="card">
                <div class="ct"><?= icon('scale') ?><?= e($t['weight_hist']) ?></div>
                <?php if (count($meas) === 0): ?>
                    <div class="empty"><?= e($t['no_data']) ?></div>
                <?php else: ?>
                    <table class="hist">
                        <thead><tr><th><?= e($t['col_date']) ?></th><th><?= e($t['col_w']) ?></th><th><?= e($t['col_h']) ?></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice(array_reverse($meas), 0, 8) as $m): ?>
                            <tr>
                                <td><?= e($m['measurement_date'] ? long_date($m['measurement_date']) : '-') ?></td>
                                <td><?= e(num1($m['weight'])) ?></td>
                                <td><?= e(num1($m['height'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

<?php else: ?>
        <!-- ================= LISTE DER KUNDEN ================= -->
        <div class="title-row">
            <div>
                <h1><?= e($t['page_title']) ?></h1>
                <p><?= e($t['subtitle']) ?></p>
            </div>
        </div>

        <?php if ($success !== ""): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error !== ""): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <div class="card">
            <div class="ct"><?= icon('users') ?><?= e($t['page_title']) ?>
                <span style="font-weight:normal;color:#777;font-size:12px">· <?= e(sprintf($t['total'], $total)) ?></span></div>

            <form method="get" action="clients.php" class="filters">
                <div>
                    <label class="fl" for="q"><?= e($t['search']) ?></label>
                    <div class="field"><input type="text" id="q" name="q" maxlength="100" value="<?= e($q) ?>"></div>
                </div>
                <div>
                    <label class="fl" for="f_status"><?= e($t['f_status']) ?></label>
                    <div class="field">
                        <select id="f_status" name="status">
                            <?php foreach (['' => 'fs_all', 'approved' => 'fs_approved', 'pending' => 'fs_pending', 'more' => 'fs_more', 'rejected' => 'fs_rejected'] as $val => $key): ?>
                                <option value="<?= $val ?>" <?= $f_status === $val ? 'selected' : '' ?>><?= e($t[$key]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn"><?= e($t['btn_filter']) ?></button>
                <?php if ($f_status !== '' || $q !== ''): ?>
                    <a class="btn" href="clients.php"><?= e($t['btn_clear']) ?></a>
                <?php endif; ?>
            </form>

            <?php if (count($rows) === 0): ?>
                <div class="empty"><?= e($t['empty']) ?></div>
            <?php else: ?>
                <div class="scroll">
                <table class="cli">
                    <thead>
                        <tr>
                            <th><?= e($t['col_client']) ?></th>
                            <th><?= e($t['col_status']) ?></th>
                            <th><?= e($t['col_next']) ?></th>
                            <th><?= e($t['col_open']) ?></th>
                            <th><?= e($t['col_weight']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $approved = ((int)$r['approval_status'] === 1);
                        $b = status_badge($r['approval_status'], $r['user_status']);
                        // freigegebene öffnen das Profil; die anderen öffnen die Prüfung der Registrierung
                        $href = $approved ? 'clients.php?id=' . (int)$r['client_id'] : 'registration_review.php?id=' . (int)$r['client_id'];
                        ?>
                        <tr>
                            <td><a class="who" href="<?= e($href) ?>" title="<?= e($approved ? $t['open_profile'] : $t['review']) ?>"><?= avatar($r, 'av sm') ?><span><?= e(full_name($r)) ?></span></a></td>
                            <td><span class="bd <?= e($b[1]) ?>"><?= e($b[0]) ?></span></td>
                            <td class="nowrap"><?= ($approved && $r['next_date']) ? e(long_date($r['next_date'])) : '<span class="muted">–</span>' ?></td>
                            <td class="nowrap">
                                <?php if ($approved): ?>
                                    <?= (int)$r['open_pay'] ?>
                                    <?php if ($r['late_pay'] > 0): ?> <span class="late">· <?= e(sprintf($t['overdue'], $r['late_pay'])) ?></span><?php endif; ?>
                                <?php else: ?><span class="muted">–</span><?php endif; ?>
                            </td>
                            <td class="nowrap">
                                <?php if ($approved && $r['last_meas']): ?>
                                    <?= e(num1($r['last_meas']['weight'])) ?> kg <span class="muted">· <?= e(long_date($r['last_meas']['measurement_date'])) ?></span>
                                <?php else: ?><span class="muted">–</span><?php endif; ?>
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
                            <?php if ($page > 1): ?><a class="btn sm" href="clients.php?page=<?= $page - 1 ?><?= e($qs) ?>"><?= e($t['prev']) ?></a><?php endif; ?>
                            <?php if ($page < $pages): ?><a class="btn sm" href="clients.php?page=<?= $page + 1 ?><?= e($qs) ?>"><?= e($t['next']) ?></a><?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<?php endif; ?>

    </main>
</div>

</body>
</html>
