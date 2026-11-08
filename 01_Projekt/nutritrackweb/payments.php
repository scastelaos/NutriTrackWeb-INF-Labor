<?php
// payments.php
// Seite der Ärztin (Admin) für die ZAHLUNGEN der Kunden.
// Jede Zeile der Tabelle payments ist EIN ABRECHNUNGSZEITRAUM (Beginn, Ende, Fälligkeit).
// Betrag und Währung kommen aus dem Profil des Kunden (clients.monthly_fee und clients.currency).
// Regeln der V1:
//  - nur die Ärztin legt Rechnungen an (nichts automatisch, nichts wiederkehrend, keine Erinnerungen)
//  - status: 0 = Open, 1 = Paid, 2 = Cancelled (storniert kommt nicht zurück; nichts wird gelöscht)
//  - "Überfällig" ist kein Status: es ist eine Open-Rechnung, deren Fälligkeit schon vorbei ist
//  - die Ärztin entscheidet selbst, wann eine Rechnung bezahlt ist; der Beleg (PDF, Typ Payment Proof)
//    ist optional und liegt in den Dokumenten des Kunden
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
        'only_client' => 'Showing only payments from %s', 'clear_filter' => 'Show all clients',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clients', 'nav_appointments' => 'Appointments',
        'nav_messages' => 'Messages', 'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos',
        'nav_payments' => 'Payments', 'nav_settings' => 'Profile Settings', 'logout' => 'Logout',
        'kpi_messages' => 'New Messages', 'kpi_appointments' => 'Upcoming Appointments',
        'kpi_payments' => 'Open Payments', 'kpi_pending' => 'Pending Approvals',
        'page_title' => 'Payments', 'subtitle' => 'Create payment periods for your clients and mark them as paid.',
        'btn_new' => 'New payment', 'sec_new' => 'New payment',
        'client' => 'Client', 'client_ph' => 'Choose a client', 'p_start' => 'Period start', 'p_end' => 'Period end', 'due' => 'Due date',
        'form_hint' => 'The amount and the currency come from the client profile.',
        'btn_create' => 'Create payment', 'btn_discard' => 'Cancel',
        'sec_list' => 'All payments', 'f_client' => 'Client', 'f_status' => 'Status',
        'all_clients' => 'All clients', 'all_status' => 'All statuses',
        'st_open' => 'Open', 'st_paid' => 'Paid', 'st_cancelled' => 'Cancelled', 'st_overdue' => 'Overdue',
        'btn_filter' => 'Filter', 'btn_clear' => 'Clear', 'total' => '%d payments', 'empty' => 'No payments found.',
        'col_client' => 'Client', 'col_period' => 'Period', 'col_due' => 'Due', 'col_amount' => 'Amount', 'col_status' => 'Status', 'col_paid' => 'Paid on',
        'paid_on' => 'Payment date', 'btn_mark' => 'Mark as paid', 'btn_cancel' => 'Cancel', 'btn_reopen' => 'Reopen', 'proofs' => 'Proofs',
        'confirm_cancel' => 'Cancel this payment? It cannot be reactivated.', 'confirm_reopen' => 'Set this payment back to Open?',
        'no_fee' => 'no amount set', 'page_of' => 'Page %1$d of %2$d', 'prev' => 'Previous', 'next' => 'Next',
        'done_created' => 'Payment created.', 'done_paid' => 'Payment marked as paid.', 'done_cancelled' => 'Payment cancelled.', 'done_reopened' => 'Payment set back to Open.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_client' => 'Please choose an approved client.',
        'err_dates' => 'Please enter valid dates. The period end cannot be before the start (maximum one year).',
        'err_due' => 'Please enter a valid due date.',
        'err_paid_date' => 'Please enter a valid payment date (not in the future).',
        'err_dup' => 'This client already has a payment that overlaps with this period.',
        'err_done' => 'This item was already handled or is no longer available.',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'role' => 'Nutricionista',
        'only_client' => 'Mostrando só os pagamentos de %s', 'clear_filter' => 'Mostrar todas as clientes',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clientes', 'nav_appointments' => 'Consultas',
        'nav_messages' => 'Mensagens', 'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições',
        'nav_payments' => 'Pagamentos', 'nav_settings' => 'Configurações do Perfil', 'logout' => 'Sair',
        'kpi_messages' => 'Novas Mensagens', 'kpi_appointments' => 'Próximas Consultas',
        'kpi_payments' => 'Pagamentos em Aberto', 'kpi_pending' => 'Aprovações Pendentes',
        'page_title' => 'Pagamentos', 'subtitle' => 'Crie os períodos de cobrança das suas clientes e marque como pagos.',
        'btn_new' => 'Novo pagamento', 'sec_new' => 'Novo pagamento',
        'client' => 'Cliente', 'client_ph' => 'Escolha uma cliente', 'p_start' => 'Início do período', 'p_end' => 'Fim do período', 'due' => 'Vencimento',
        'form_hint' => 'O valor e a moeda vêm do perfil da cliente.',
        'btn_create' => 'Criar pagamento', 'btn_discard' => 'Cancelar',
        'sec_list' => 'Todos os pagamentos', 'f_client' => 'Cliente', 'f_status' => 'Situação',
        'all_clients' => 'Todas as clientes', 'all_status' => 'Todas as situações',
        'st_open' => 'Em aberto', 'st_paid' => 'Pago', 'st_cancelled' => 'Cancelado', 'st_overdue' => 'Atrasado',
        'btn_filter' => 'Filtrar', 'btn_clear' => 'Limpar', 'total' => '%d pagamentos', 'empty' => 'Nenhum pagamento encontrado.',
        'col_client' => 'Cliente', 'col_period' => 'Período', 'col_due' => 'Vence', 'col_amount' => 'Valor', 'col_status' => 'Situação', 'col_paid' => 'Pago em',
        'paid_on' => 'Data do pagamento', 'btn_mark' => 'Marcar como pago', 'btn_cancel' => 'Cancelar', 'btn_reopen' => 'Reabrir', 'proofs' => 'Comprovantes',
        'confirm_cancel' => 'Cancelar este pagamento? Ele não pode ser reativado.', 'confirm_reopen' => 'Voltar este pagamento para Em aberto?',
        'no_fee' => 'sem valor definido', 'page_of' => 'Página %1$d de %2$d', 'prev' => 'Anterior', 'next' => 'Próxima',
        'done_created' => 'Pagamento criado.', 'done_paid' => 'Pagamento marcado como pago.', 'done_cancelled' => 'Pagamento cancelado.', 'done_reopened' => 'Pagamento voltou para Em aberto.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_client' => 'Escolha uma cliente aprovada.',
        'err_dates' => 'Informe datas válidas. O fim do período não pode ser antes do início (máximo de um ano).',
        'err_due' => 'Informe um vencimento válido.',
        'err_paid_date' => 'Informe uma data de pagamento válida (não pode ser no futuro).',
        'err_dup' => 'Esta cliente já tem um pagamento que se sobrepõe a este período.',
        'err_done' => 'Este item já foi tratado ou não está mais disponível.',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'role' => 'Ernährungsberaterin',
        'only_client' => 'Es werden nur Zahlungen von %s angezeigt', 'clear_filter' => 'Alle Kunden anzeigen',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Kunden', 'nav_appointments' => 'Termine',
        'nav_messages' => 'Nachrichten', 'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos',
        'nav_payments' => 'Zahlungen', 'nav_settings' => 'Profileinstellungen', 'logout' => 'Abmelden',
        'kpi_messages' => 'Neue Nachrichten', 'kpi_appointments' => 'Bevorstehende Termine',
        'kpi_payments' => 'Offene Zahlungen', 'kpi_pending' => 'Ausstehende Freigaben',
        'page_title' => 'Zahlungen', 'subtitle' => 'Legen Sie Zahlungszeiträume für Ihre Kunden an und markieren Sie sie als bezahlt.',
        'btn_new' => 'Neue Zahlung', 'sec_new' => 'Neue Zahlung',
        'client' => 'Kundin', 'client_ph' => 'Kundin auswählen', 'p_start' => 'Zeitraum von', 'p_end' => 'Zeitraum bis', 'due' => 'Fälligkeitsdatum',
        'form_hint' => 'Betrag und Währung stammen aus dem Kundenprofil.',
        'btn_create' => 'Zahlung anlegen', 'btn_discard' => 'Abbrechen',
        'sec_list' => 'Alle Zahlungen', 'f_client' => 'Kundin', 'f_status' => 'Status',
        'all_clients' => 'Alle Kunden', 'all_status' => 'Alle Status',
        'st_open' => 'Offen', 'st_paid' => 'Bezahlt', 'st_cancelled' => 'Storniert', 'st_overdue' => 'Überfällig',
        'btn_filter' => 'Filtern', 'btn_clear' => 'Zurücksetzen', 'total' => '%d Zahlungen', 'empty' => 'Keine Zahlungen gefunden.',
        'col_client' => 'Kundin', 'col_period' => 'Zeitraum', 'col_due' => 'Fällig', 'col_amount' => 'Betrag', 'col_status' => 'Status', 'col_paid' => 'Bezahlt am',
        'paid_on' => 'Zahlungsdatum', 'btn_mark' => 'Als bezahlt markieren', 'btn_cancel' => 'Stornieren', 'btn_reopen' => 'Wieder öffnen', 'proofs' => 'Belege',
        'confirm_cancel' => 'Diese Zahlung stornieren? Sie kann nicht wieder aktiviert werden.', 'confirm_reopen' => 'Diese Zahlung wieder auf Offen setzen?',
        'no_fee' => 'kein Betrag hinterlegt', 'page_of' => 'Seite %1$d von %2$d', 'prev' => 'Zurück', 'next' => 'Weiter',
        'done_created' => 'Zahlung angelegt.', 'done_paid' => 'Zahlung als bezahlt markiert.', 'done_cancelled' => 'Zahlung storniert.', 'done_reopened' => 'Zahlung wieder auf Offen gesetzt.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_client' => 'Bitte wählen Sie eine freigegebene Kundin.',
        'err_dates' => 'Bitte gültige Daten eingeben. Das Ende darf nicht vor dem Beginn liegen (höchstens ein Jahr).',
        'err_due' => 'Bitte ein gültiges Fälligkeitsdatum eingeben.',
        'err_paid_date' => 'Bitte ein gültiges Zahlungsdatum eingeben (nicht in der Zukunft).',
        'err_dup' => 'Für diese Kundin gibt es bereits eine Zahlung, die sich mit diesem Zeitraum überschneidet.',
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

// gibt es einen ANDEREN Termin am selben Tag zur selben Uhrzeit? (dient nur für die Warnung)

// BLOCK 5: Sicherheits-Token des Formulars (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// BLOCK 6: Was passiert, wenn ein Formular abgeschickt wird
$error   = "";
$warning = "";

// Betrag mit Währungssymbol ("R$ 350.00"); ohne Betrag wird "" zurückgegeben
function money($fee, $currency) {
    if ($fee === null || $fee === '') {
        return "";
    }
    $symbols = ['BRL' => 'R$', 'EUR' => '€', 'USD' => '$'];
    $symbol  = $symbols[$currency] ?? $currency;
    return $symbol . ' ' . number_format((float)$fee, 2, '.', '');
}

$today = date('Y-m-d');
$f_new = ['client_id' => ((int)($_GET['client'] ?? 0) > 0 ? (string)(int)$_GET['client'] : ''), 'p_start' => $today, 'p_end' => date('Y-m-d', strtotime('+1 month -1 day')), 'due' => $today];
$open_form = isset($_GET['new']);

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form  = $_POST['form'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        $error = $t['err_invalid'];

    } elseif ($form === 'create') {
        // ---------- EINEN ABRECHNUNGSZEITRAUM ANLEGEN ----------
        $open_form = true;
        $f_new = [
            'client_id' => (string)($_POST['client_id'] ?? ''),
            'p_start'   => (string)($_POST['p_start'] ?? ''),
            'p_end'     => (string)($_POST['p_end'] ?? ''),
            'due'       => (string)($_POST['due'] ?? ''),
        ];
        // filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
        $cid = filter_var($f_new['client_id'], FILTER_VALIDATE_INT);

        // der Kunde muss existieren und FREIGEGEBEN sein
        $ok_client = false;
        if ($cid !== false && $cid > 0) {
            $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE client_id = ? AND approval_status = 1");
            $stmt->execute([$cid]);
            $ok_client = (bool)$stmt->fetchColumn();
        }

        $d1 = valid_date($f_new['p_start']);
        $d2 = valid_date($f_new['p_end']);
        $d3 = valid_date($f_new['due']);

        if (!$ok_client) {
            $error = $t['err_client'];
        } elseif ($d1 === "" || $d2 === "" || $d2 < $d1 || strtotime($d2) - strtotime($d1) > 366 * 86400) {
            $error = $t['err_dates'];
        } elseif ($d3 === "") {
            $error = $t['err_due'];
        } else {
            try {
                // der neue Zeitraum darf sich NICHT mit einem anderen desselben Kunden ÜBERSCHNEIDEN
                // (zwei Zeiträume überschneiden sich, wenn: Beginn des alten <= Ende des neuen UND Ende des alten >= Beginn des neuen).
                // Stornierte Rechnungen zählen nicht.
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM payments
                     WHERE clients_client_id = ? AND period_start <= ? AND period_end >= ? AND (status IS NULL OR status <> 2)"
                );
                $stmt->execute([$cid, $d2, $d1]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $error = $t['err_dup'];
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO payments (period_start, period_end, status, due_date, payment_date, clients_client_id)
                         VALUES (?, ?, 0, ?, NULL, ?)"
                    );
                    $stmt->execute([$d1, $d2, $d3, $cid]);
                    header("Location: payments.php?done=created");
                    exit;
                }
            } catch (PDOException $ex) {
                $error = $t['err_other'];
            }
        }

    } elseif (in_array($form, ['mark_paid', 'cancel', 'reopen'], true)) {
        // ---------- DEN STATUS EINER RECHNUNG ÄNDERN ----------
        $pid = filter_var($_POST['payment_id'] ?? '', FILTER_VALIDATE_INT);

        if ($pid === false || $pid < 1) {
            $error = $t['err_invalid'];
        } else {
            try {
                if ($form === 'mark_paid') {
                    $paid = valid_date($_POST['paid_date'] ?? '');
                    if ($paid === "" || $paid > $today) {
                        $error = $t['err_paid_date'];
                    } else {
                        $stmt = $pdo->prepare(
                            "UPDATE payments SET status = 1, payment_date = ?
                             WHERE payment_id = ? AND (status IS NULL OR status = 0)"
                        );
                        $stmt->execute([$paid, $pid]);
                        $key = 'paid';
                    }
                } elseif ($form === 'cancel') {
                    $stmt = $pdo->prepare("UPDATE payments SET status = 2 WHERE payment_id = ? AND (status IS NULL OR status = 0)");
                    $stmt->execute([$pid]);
                    $key = 'cancelled';
                } else {
                    $stmt = $pdo->prepare("UPDATE payments SET status = 0, payment_date = NULL WHERE payment_id = ? AND status = 1");
                    $stmt->execute([$pid]);
                    $key = 'reopened';
                }

                if ($error === "") {
                    if ($stmt->rowCount() === 0) {
                        $error = $t['err_done'];
                    } else {
                        header("Location: payments.php?done=" . $key);
                        exit;
                    }
                }
            } catch (PDOException $ex) {
                $error = $t['err_other'];
            }
        }
    }
}

// Erfolgsmeldung (kommt von der Weiterleitung)
$success = "";
$done_messages = ['created' => 'done_created', 'paid' => 'done_paid', 'cancelled' => 'done_cancelled', 'reopened' => 'done_reopened'];
if (isset($_GET['done']) && isset($done_messages[$_GET['done']])) {
    $success = $t[$done_messages[$_GET['done']]];
}

// BLOCK 7: Daten der Seite

// 7a: Filter (Kunde und Status) und Seite
$f_status = (string)($_GET['status'] ?? '');
if (!in_array($f_status, ['', 'open', 'overdue', 'paid', 'cancelled'], true)) { $f_status = ''; }
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// 7b: alle Kunden (Filter) und die freigegebenen (neue Rechnung)
$all_clients = $pdo->query(
    "SELECT client_id, first_name, last_name, profile_photo_path, approval_status, monthly_fee, currency
     FROM clients ORDER BY first_name, last_name"
)->fetchAll(PDO::FETCH_ASSOC);

// Filter nach Kunde: nur aktiv, wenn die Ärztin von der Abkürzung im Profil kommt (?client=ID)
$f_client = (int)($_GET['client'] ?? 0);
$only_row = null;
foreach ($all_clients as $c) {
    if ((int)$c['client_id'] === $f_client) { $only_row = $c; }
}
if (!$only_row) { $f_client = 0; }

// 7c: die Liste der Zahlungen
$where  = [];
$params = [];
if ($f_client > 0)             { $where[] = "p.clients_client_id = ?"; $params[] = $f_client; }
if ($f_status === 'open')      { $where[] = "(p.status IS NULL OR p.status = 0)"; }
if ($f_status === 'overdue')   { $where[] = "(p.status IS NULL OR p.status = 0) AND p.due_date < ?"; $params[] = $today; }
if ($f_status === 'paid')      { $where[] = "p.status = 1"; }
if ($f_status === 'cancelled') { $where[] = "p.status = 2"; }
$where_sql = count($where) ? " WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p" . $where_sql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));
if ($page > $pages) { $page = $pages; }
$offset = ($page - 1) * $per_page;

// zuerst die offenen (die ältesten oben), dann bezahlte und stornierte, die neuesten zuerst
$stmt = $pdo->prepare(
    "SELECT p.payment_id, p.period_start, p.period_end, p.status, p.due_date, p.payment_date,
            c.client_id, c.first_name, c.last_name, c.profile_photo_path, c.monthly_fee, c.currency
     FROM payments p
     JOIN clients c ON c.client_id = p.clients_client_id"
    . $where_sql
    . " ORDER BY CASE WHEN p.status IS NULL OR p.status = 0 THEN 0 ELSE 1 END,
                 CASE WHEN p.status IS NULL OR p.status = 0 THEN p.due_date END ASC,
                 p.payment_id DESC
        LIMIT " . (int)$per_page . " OFFSET " . (int)$offset
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$qs = ($f_status !== '' ? '&status=' . $f_status : '') . ($f_client > 0 ? '&client=' . $f_client : '');

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

// Einträge des Seitenmenüs: [Datei, Symbol, Text]
$current = "payments.php";
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
        .plink { color: var(--primary); font-weight: bold; }
        .plink:hover { text-decoration: underline; }
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
        <div class="kpi warm"><?= icon('coin') ?><div><b><?= $count_payments ?></b><span><?= e($t['kpi_payments']) ?></span></div></div>
        <div class="kpi"><?= icon('user-check') ?><div><b><?= $count_pending ?></b><span><?= e($t['kpi_pending']) ?></span></div></div>
    </header>

    <!-- INHALT -->
    <main class="main">

        <div class="title-row">
            <div>
                <h1><?= e($t['page_title']) ?></h1>
                <p><?= e($t['subtitle']) ?></p>
            </div>
            <a class="btn fill" href="payments.php?new=1<?= $f_client > 0 ? '&amp;client=' . (int)$f_client : '' ?>#form"><?= icon('plus', 'w') ?><?= e($t['btn_new']) ?></a>
        </div>

        <?php if ($only_row): ?>
            <div class="only">
                <?= avatar($only_row, 'av sm') ?>
                <span><?= e(sprintf($t['only_client'], full_name($only_row))) ?></span>
                <a class="btn sm" href="payments.php"><?= e($t['clear_filter']) ?></a>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($open_form): ?>
        <!-- NEUE RECHNUNG (erscheint nur mit dem Knopf "+ Neue Zahlung") -->
        <div class="card" id="form">
            <div class="ct"><?= icon('wallet') ?><?= e($t['sec_new']) ?></div>
            <form method="post" action="payments.php#form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="form" value="create">
                <div class="form3">
                    <div>
                        <label class="fl" for="client_id"><?= e($t['client']) ?></label>
                        <div class="field">
                            <select id="client_id" name="client_id" required>
                                <option value=""><?= e($t['client_ph']) ?></option>
                                <?php foreach ($all_clients as $c): ?>
                                    <?php if ((int)$c['approval_status'] !== 1) { continue; } ?>
                                    <?php $fee = money($c['monthly_fee'], $c['currency']); ?>
                                    <option value="<?= (int)$c['client_id'] ?>" <?= (string)$c['client_id'] === $f_new['client_id'] ? 'selected' : '' ?>><?= e(full_name($c) . ' · ' . ($fee !== '' ? $fee : $t['no_fee'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="fl" for="p_start"><?= e($t['p_start']) ?></label>
                        <div class="field"><input type="date" id="p_start" name="p_start" value="<?= e($f_new['p_start']) ?>" required></div>
                    </div>
                    <div>
                        <label class="fl" for="p_end"><?= e($t['p_end']) ?></label>
                        <div class="field"><input type="date" id="p_end" name="p_end" value="<?= e($f_new['p_end']) ?>" required></div>
                    </div>
                    <div>
                        <label class="fl" for="due"><?= e($t['due']) ?></label>
                        <div class="field"><input type="date" id="due" name="due" value="<?= e($f_new['due']) ?>" required></div>
                    </div>
                </div>
                <div style="font-size:12px;color:#777;margin-bottom:10px"><?= e($t['form_hint']) ?></div>
                <div class="form-btns">
                    <button type="submit" class="btn fill"><?= icon('check', 'w') ?><?= e($t['btn_create']) ?></button>
                    <a class="btn" href="payments.php"><?= e($t['btn_discard']) ?></a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- LISTE -->
        <div class="card">
            <div class="ct"><?= icon('wallet') ?><?= e($t['sec_list']) ?>
                <span style="font-weight:normal;color:#777;font-size:12px">· <?= e(sprintf($t['total'], $total)) ?></span></div>

            <form method="get" action="payments.php" class="filters">
                <?php if ($f_client > 0): ?><input type="hidden" name="client" value="<?= (int)$f_client ?>"><?php endif; ?>
                <div>
                    <label class="fl" for="f_status"><?= e($t['f_status']) ?></label>
                    <div class="field">
                        <select id="f_status" name="status">
                            <option value=""><?= e($t['all_status']) ?></option>
                            <?php foreach (['open' => 'st_open', 'overdue' => 'st_overdue', 'paid' => 'st_paid', 'cancelled' => 'st_cancelled'] as $val => $key): ?>
                                <option value="<?= $val ?>" <?= $f_status === $val ? 'selected' : '' ?>><?= e($t[$key]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn"><?= e($t['btn_filter']) ?></button>
                <?php if ($f_status !== ''): ?>
                    <a class="btn" href="payments.php<?= $f_client > 0 ? '?client=' . (int)$f_client : '' ?>"><?= e($t['btn_clear']) ?></a>
                <?php endif; ?>
            </form>

            <?php if (count($rows) === 0): ?>
                <div class="empty"><?= e($t['empty']) ?></div>
            <?php else: ?>
                <div class="scroll">
                <table class="pay">
                    <thead>
                        <tr>
                            <th><?= e($t['col_client']) ?></th>
                            <th><?= e($t['col_period']) ?></th>
                            <th><?= e($t['col_due']) ?></th>
                            <th><?= e($t['col_amount']) ?></th>
                            <th><?= e($t['col_status']) ?></th>
                            <th><?= e($t['col_paid']) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $st      = $r['status'] === null ? 0 : (int)$r['status'];
                        $overdue = ($st === 0 && $r['due_date'] !== null && $r['due_date'] < $today);
                        if ($st === 1)      { $badge = [$t['st_paid'], 'b-ok']; }
                        elseif ($st === 2)  { $badge = [$t['st_cancelled'], 'b-gray']; }
                        elseif ($overdue)   { $badge = [$t['st_overdue'], 'b-red']; }
                        else                { $badge = [$t['st_open'], 'b-info']; }
                        $fee = money($r['monthly_fee'], $r['currency']);
                        ?>
                        <tr>
                            <td><div class="who"><?= avatar($r, 'av sm') ?><a class="plink" href="clients.php?id=<?= (int)$r['client_id'] ?>"><?= e(full_name($r)) ?></a></div></td>
                            <td class="nowrap"><?= e($r['period_start'] ? long_date($r['period_start']) : '-') ?> – <?= e($r['period_end'] ? long_date($r['period_end']) : '-') ?></td>
                            <td class="nowrap"><?= e($r['due_date'] ? long_date($r['due_date']) : '-') ?></td>
                            <td class="nowrap"><?= $fee !== '' ? e($fee) : '<span class="muted">' . e($t['no_fee']) . '</span>' ?></td>
                            <td><span class="bd <?= e($badge[1]) ?>"><?= e($badge[0]) ?></span></td>
                            <td class="nowrap"><?= e($r['payment_date'] ? long_date($r['payment_date']) : '-') ?></td>
                            <td>
                                <div class="acts">
                                <?php if ($st === 0): ?>
                                    <form method="post" action="payments.php" class="paidform">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="form" value="mark_paid">
                                        <input type="hidden" name="payment_id" value="<?= (int)$r['payment_id'] ?>">
                                        <input type="date" name="paid_date" value="<?= e($today) ?>" max="<?= e($today) ?>" required title="<?= e($t['paid_on']) ?>">
                                        <button type="submit" class="sq ok" title="<?= e($t['btn_mark']) ?>"><?= icon('check') ?></button>
                                    </form>
                                    <form method="post" action="payments.php" class="inl" onsubmit="return confirm(<?= e(json_encode($t['confirm_cancel'])) ?>);">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="form" value="cancel">
                                        <input type="hidden" name="payment_id" value="<?= (int)$r['payment_id'] ?>">
                                        <button type="submit" class="sq no" title="<?= e($t['btn_cancel']) ?>"><?= icon('x') ?></button>
                                    </form>
                                <?php elseif ($st === 1): ?>
                                    <a class="sq ok" href="documents.php?client=<?= (int)$r['client_id'] ?>&amp;type=3" title="<?= e($t['proofs']) ?>"><?= icon('documents') ?></a>
                                    <form method="post" action="payments.php" class="inl" onsubmit="return confirm(<?= e(json_encode($t['confirm_reopen'])) ?>);">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="form" value="reopen">
                                        <input type="hidden" name="payment_id" value="<?= (int)$r['payment_id'] ?>">
                                        <button type="submit" class="btn sm"><?= e($t['btn_reopen']) ?></button>
                                    </form>
                                <?php endif; ?>
                                </div>
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
                            <?php if ($page > 1): ?><a class="btn sm" href="payments.php?page=<?= $page - 1 ?><?= e($qs) ?>"><?= e($t['prev']) ?></a><?php endif; ?>
                            <?php if ($page < $pages): ?><a class="btn sm" href="payments.php?page=<?= $page + 1 ?><?= e($qs) ?>"><?= e($t['next']) ?></a><?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
    // wenn man den Beginn des Zeitraums wählt, wird das Ende vorgeschlagen (1 Monat später, minus 1 Tag) und die Fälligkeit (der Beginn selbst)
    var ps = document.getElementById('p_start');
    if (ps) {
        ps.addEventListener('change', function () {
            if (!ps.value) { return; }
            var d = new Date(ps.value + 'T00:00:00');
            var end = new Date(d.getFullYear(), d.getMonth() + 1, d.getDate() - 1);
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            document.getElementById('p_end').value = end.getFullYear() + '-' + pad(end.getMonth() + 1) + '-' + pad(end.getDate());
            document.getElementById('due').value = ps.value;
        });
    }
</script>

</body>
</html>
