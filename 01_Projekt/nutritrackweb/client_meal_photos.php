<?php
// client_meal_photos.php
// "Meal Photos" (Mahlzeitenfotos) des FREIGEGEBENEN Kunden: Fotos von Mahlzeiten senden, eigene Fotos als Raster sehen,
// per Klick vergrößern und das eigene Foto löschen.
// Regeln der V1:
//  - nur JPG und PNG (geprüft am INHALT des Bildes), bis 8 MB, keine Begrenzung der Anzahl
//  - Mahlzeitentyp (1 Breakfast, 2 Lunch, 3 Dinner, 4 Other) und optionaler Kommentar (bis 500 Zeichen)
//  - Datum und Uhrzeit des Uploads (uploaded_at) gelten als Datum der Mahlzeit
//  - der Kunde löscht NUR seine eigenen Fotos; es gibt kein Bearbeiten; die Rückmeldung der Ernährungsberaterin kommt über die Nachrichten
//  - die Dateien liegen in uploads/meal_photos/ (gesperrter Ordner); zum Ansehen immer über view_image.php

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
        'page_title' => 'Meal Photos', 'subtitle' => 'Send photos of your meals to your nutritionist and see all your photos.',
        'your_nutri' => 'Your nutritionist: %s',
        'nav_home' => 'Home', 'nav_appointments' => 'Appointments', 'nav_messages' => 'Messages',
        'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos', 'nav_payments' => 'Payments',
        'nav_profile' => 'My Profile', 'logout' => 'Logout',
        'kpi_appt' => 'Next Appointment', 'kpi_reply' => 'New Reply', 'kpi_payment' => 'Payment', 'kpi_weight' => 'Current Weight',
        'due' => 'Due',
        'sec_upload' => 'Add a meal photo', 'meal_type' => 'Meal', 'photo' => 'Photo', 'choose_photo' => 'Choose photo', 'no_file' => 'No photo chosen',
        'comment' => 'Comment (optional)', 'comment_ph' => 'For example: with less rice than usual',
        'upload_hint' => 'JPG or PNG, maximum 8 MB per photo.', 'btn_upload' => 'Upload photo',
        'meal1' => 'Breakfast', 'meal2' => 'Lunch', 'meal3' => 'Dinner', 'meal4' => 'Other',
        'sec_list' => 'Your photos', 'filter_all' => 'All', 'empty' => 'You have not sent any meal photo yet.',
        'total' => '%d photos', 'page_of' => 'Page %1$d of %2$d', 'prev' => 'Previous', 'next' => 'Next',
        'btn_delete' => 'Delete', 'confirm_delete' => 'Delete this photo? This cannot be undone.', 'close' => 'Close',
        'feedback_note' => 'To ask about a photo, send a message to %s in Messages.',
        'done_uploaded' => 'Your photo was uploaded.', 'done_deleted' => 'The photo was deleted.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_type' => 'Please choose a valid meal type.',
        'err_nofile' => 'Please choose a photo.',
        'err_toobig' => 'The photo is too large. The maximum is 8 MB.',
        'err_notimg' => 'Only JPG and PNG photos are allowed.',
        'err_comment' => 'The comment is too long (maximum 500 characters).',
        'err_gone' => 'This photo no longer exists.',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'page_title' => 'Fotos das Refeições', 'subtitle' => 'Envie fotos das suas refeições para sua nutricionista e veja todas as suas fotos.',
        'your_nutri' => 'Sua nutricionista: %s',
        'nav_home' => 'Início', 'nav_appointments' => 'Consultas', 'nav_messages' => 'Mensagens',
        'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições', 'nav_payments' => 'Pagamentos',
        'nav_profile' => 'Meu Perfil', 'logout' => 'Sair',
        'kpi_appt' => 'Próxima Consulta', 'kpi_reply' => 'Nova Resposta', 'kpi_payment' => 'Pagamento', 'kpi_weight' => 'Peso Atual',
        'due' => 'Vence',
        'sec_upload' => 'Adicionar foto de refeição', 'meal_type' => 'Refeição', 'photo' => 'Foto', 'choose_photo' => 'Escolher foto', 'no_file' => 'Nenhuma foto escolhida',
        'comment' => 'Comentário (opcional)', 'comment_ph' => 'Por exemplo: com menos arroz que o normal',
        'upload_hint' => 'JPG ou PNG, no máximo 8 MB por foto.', 'btn_upload' => 'Enviar foto',
        'meal1' => 'Café da manhã', 'meal2' => 'Almoço', 'meal3' => 'Jantar', 'meal4' => 'Outro',
        'sec_list' => 'Suas fotos', 'filter_all' => 'Todas', 'empty' => 'Você ainda não enviou nenhuma foto de refeição.',
        'total' => '%d fotos', 'page_of' => 'Página %1$d de %2$d', 'prev' => 'Anterior', 'next' => 'Próxima',
        'btn_delete' => 'Excluir', 'confirm_delete' => 'Excluir esta foto? Isso não pode ser desfeito.', 'close' => 'Fechar',
        'feedback_note' => 'Para perguntar sobre uma foto, envie uma mensagem para %s em Mensagens.',
        'done_uploaded' => 'Sua foto foi enviada.', 'done_deleted' => 'A foto foi excluída.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_type' => 'Escolha um tipo de refeição válido.',
        'err_nofile' => 'Escolha uma foto.',
        'err_toobig' => 'A foto é grande demais. O máximo é 8 MB.',
        'err_notimg' => 'Somente fotos JPG e PNG são permitidas.',
        'err_comment' => 'O comentário é longo demais (máximo 500 caracteres).',
        'err_gone' => 'Esta foto não existe mais.',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'page_title' => 'Mahlzeitenfotos', 'subtitle' => 'Senden Sie Fotos Ihrer Mahlzeiten an Ihre Ernährungsberaterin und sehen Sie alle Ihre Fotos.',
        'your_nutri' => 'Ihre Ernährungsberaterin: %s',
        'nav_home' => 'Start', 'nav_appointments' => 'Termine', 'nav_messages' => 'Nachrichten',
        'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos', 'nav_payments' => 'Zahlungen',
        'nav_profile' => 'Mein Profil', 'logout' => 'Abmelden',
        'kpi_appt' => 'Nächster Termin', 'kpi_reply' => 'Neue Antwort', 'kpi_payment' => 'Zahlung', 'kpi_weight' => 'Aktuelles Gewicht',
        'due' => 'Fällig',
        'sec_upload' => 'Mahlzeitenfoto hinzufügen', 'meal_type' => 'Mahlzeit', 'photo' => 'Foto', 'choose_photo' => 'Foto auswählen', 'no_file' => 'Kein Foto ausgewählt',
        'comment' => 'Kommentar (optional)', 'comment_ph' => 'Zum Beispiel: mit weniger Reis als sonst',
        'upload_hint' => 'JPG oder PNG, maximal 8 MB pro Foto.', 'btn_upload' => 'Foto hochladen',
        'meal1' => 'Frühstück', 'meal2' => 'Mittagessen', 'meal3' => 'Abendessen', 'meal4' => 'Sonstiges',
        'sec_list' => 'Ihre Fotos', 'filter_all' => 'Alle', 'empty' => 'Sie haben noch kein Mahlzeitenfoto gesendet.',
        'total' => '%d Fotos', 'page_of' => 'Seite %1$d von %2$d', 'prev' => 'Zurück', 'next' => 'Weiter',
        'btn_delete' => 'Löschen', 'confirm_delete' => 'Dieses Foto löschen? Das kann nicht rückgängig gemacht werden.', 'close' => 'Schließen',
        'feedback_note' => 'Für Fragen zu einem Foto senden Sie %s eine Nachricht unter Nachrichten.',
        'done_uploaded' => 'Ihr Foto wurde hochgeladen.', 'done_deleted' => 'Das Foto wurde gelöscht.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_type' => 'Bitte wählen Sie eine gültige Mahlzeit.',
        'err_nofile' => 'Bitte wählen Sie ein Foto.',
        'err_toobig' => 'Das Foto ist zu groß. Das Maximum sind 8 MB.',
        'err_notimg' => 'Nur JPG- und PNG-Fotos sind erlaubt.',
        'err_comment' => 'Der Kommentar ist zu lang (maximal 500 Zeichen).',
        'err_gone' => 'Dieses Foto existiert nicht mehr.',
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


// BLOCK 7: Regeln für die Fotos und was passiert, wenn ein Foto gesendet oder gelöscht wird
$max_bytes  = 8 * 1024 * 1024;                  // 8 MB (Höchstgröße in Bytes)
$max_pixels = 60 * 1000 * 1000;                 // 60 Megapixel (schützt den Server vor riesigen Bildern)
$upload_dir = __DIR__ . '/uploads/meal_photos'; // Ordner (durch .htaccess gesperrt)

$form_error = "";
$v_type     = "1";
$v_comment  = "";

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $form  = $_POST['form'] ?? '';

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // das Foto war größer als post_max_size von PHP: dann verwirft PHP alles
        $form_error = $t['err_toobig'];

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    } elseif (!hash_equals($_SESSION['csrf_token'], (string)$token) || !in_array($form, ['upload', 'delete'], true)) {
        $form_error = $t['err_invalid'];

    } elseif ($form === 'delete') {
        // ---- das EIGENE Foto LÖSCHEN ----
        // filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
        $photo_id = filter_var($_POST['photo_id'] ?? '', FILTER_VALIDATE_INT);

        $row = false;
        if ($photo_id !== false && $photo_id > 0) {
            // das Foto wird nur gefunden, wenn es zu DIESEM Kunden gehört
            $stmt = $pdo->prepare("SELECT photo_path FROM meal_photos WHERE photo_id = ? AND clients_client_id = ?");
            $stmt->execute([$photo_id, $client_id]);
            $row = $stmt->fetch();
        }

        if (!$row) {
            $form_error = $t['err_gone'];
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM meal_photos WHERE photo_id = ? AND clients_client_id = ?");
                $stmt->execute([$photo_id, $client_id]);

                // die Datei von der Festplatte löschen (nur innerhalb des Fotoordners)
                $path = realpath($upload_dir . '/' . basename((string)$row['photo_path']));
                $base = realpath($upload_dir);
                if ($path !== false && $base !== false && strpos($path, $base . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
                    // unlink() löscht eine Datei von der Festplatte.
                    @unlink($path);
                }

                header("Location: client_meal_photos.php?done=deleted");
                exit;
            } catch (PDOException $ex) {
                $form_error = $t['err_other'];
            }
        }

    } else {
        // ---- ein Foto SENDEN ----
        $v_type    = (string)($_POST['meal_type'] ?? '1');
        $v_comment = trim($_POST['comment'] ?? '');

        $f   = $_FILES['photo'] ?? null;
        $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;

        if (!in_array($v_type, ['1', '2', '3', '4'], true)) {
            $form_error = $t['err_type'];
        } elseif (mb_strlen($v_comment) > 500) {
            $form_error = $t['err_comment'];
        } elseif (!$f || $err === UPLOAD_ERR_NO_FILE) {
            $form_error = $t['err_nofile'];
        } elseif ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > $max_bytes) {
            $form_error = $t['err_toobig'];
        } elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $form_error = $t['err_other'];
        } else {
            // prüfen, ob es WIRKLICH ein JPG- oder PNG-Bild ist, anhand des Inhalts (nicht nur des Namens)
            // getimagesize() liest den ECHTEN Inhalt des Bildes (Typ und Größe). So wissen wir, dass es wirklich ein Bild ist und nicht nur so heißt.
            $info  = @getimagesize($f['tmp_name']);
            $kinds = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
            $ext   = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));

            if (!$info || !isset($kinds[$info[2]]) || !in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $form_error = $t['err_notimg'];
            } elseif ($info[0] * $info[1] > $max_pixels) {
                $form_error = $t['err_toobig'];
            } else {
                // ZUFÄLLIGER Name auf der Festplatte, mit der Endung des echten Bildtyps
                $stored = bin2hex(random_bytes(16)) . '.' . $kinds[$info[2]];
                $target = $upload_dir . '/' . $stored;

                // move_uploaded_file() verschiebt die hochgeladene Datei vom Temp-Ordner des Servers in den Zielordner (geht nur bei echten Uploads).
                if (!is_dir($upload_dir) || !move_uploaded_file($f['tmp_name'], $target)) {
                    $form_error = $t['err_other'];
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            "INSERT INTO meal_photos (meal_type, photo_path, comment, uploaded_at, clients_client_id)
                             VALUES (?, ?, ?, NOW(), ?)"
                        );
                        $stmt->execute([(int)$v_type, 'uploads/meal_photos/' . $stored,
                                        $v_comment !== '' ? $v_comment : null, $client_id]);

                        header("Location: client_meal_photos.php?done=uploaded");
                        exit;
                    } catch (PDOException $ex) {
                        @unlink($target);      // keine "losen" Dateien übrig lassen, wenn die Datenbank einen Fehler hatte (unlink = Datei löschen)
                        $form_error = $t['err_other'];
                    }
                }
            }
        }
    }
}

$done = $_GET['done'] ?? '';
$success = $done === 'uploaded' ? $t['done_uploaded'] : ($done === 'deleted' ? $t['done_deleted'] : "");

// BLOCK 8: Daten des Kunden für die Seitenleiste
$first      = $client['first_name'] ?? "";
$full_name  = trim($first . ' ' . ($client['last_name'] ?? ''));
$initials   = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($client['last_name'] ?? '', 0, 1));
$photo      = $client['profile_photo_path'] ?? "";

// BLOCK 9: Daten dieser Seite

// Filter nach Mahlzeitentyp (0 = alle) und Seite
$f_type = (int)($_GET['type'] ?? 0);
if (!in_array($f_type, [0, 1, 2, 3, 4], true)) { $f_type = 0; }
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

$where  = "clients_client_id = ?";
$params = [$client_id];
if ($f_type > 0) { $where .= " AND meal_type = ?"; $params[] = $f_type; }

$stmt = $pdo->prepare("SELECT COUNT(*) FROM meal_photos WHERE " . $where);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));
if ($page > $pages) { $page = $pages; }
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare(
    "SELECT photo_id, meal_type, comment, uploaded_at FROM meal_photos WHERE " . $where
    . " ORDER BY uploaded_at DESC, photo_id DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset
);
$stmt->execute($params);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$qs = $f_type > 0 ? '&type=' . $f_type : '';

// Farben und Namen der Mahlzeitentypen
$meal_types = [
    1 => [$t['meal1'], '#FAC775', '#633806'],
    2 => [$t['meal2'], '#C0DD97', '#27500A'],
    3 => [$t['meal3'], '#F5C4B3', '#712B13'],
    4 => [$t['meal4'], '#E4E1D8', '#444441'],
];

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

// Seitenmenü: [Datei, Symbol, Text]
$current = "client_meal_photos.php";
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


        /* Fotos der Mahlzeiten */
        .up-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; margin-bottom: 10px; align-items: end; }
        .file-pick { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .file-pick input[type=file] { display: none; }
        #file-name { font-size: 12.5px; color: #666; word-break: break-all; }
        .chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
        .chip { font-size: 12.5px; padding: 5px 12px; border-radius: 99px; border: 1px solid #D8DDD8; background: #fff; color: #555; }
        .chip.on { background: var(--primary); border-color: var(--primary); color: #fff; font-weight: bold; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
        .shot { border: 1px solid #E5E8E5; border-radius: 10px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
        .shot .imgbtn { display: block; width: 100%; padding: 0; border: 0; background: #EEF1EE; cursor: zoom-in; aspect-ratio: 1 / 1; }
        .shot .imgbtn img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .shot .info { padding: 8px 10px 10px; font-size: 12px; color: #666; display: flex; flex-direction: column; gap: 5px; flex: 1; }
        .tag { display: inline-block; align-self: flex-start; font-size: 11px; font-weight: bold; padding: 2px 9px; border-radius: 99px; }
        .shot .cm { color: #333; font-size: 12.5px; white-space: pre-line; word-break: break-word; }
        .shot form { margin: 0; margin-top: auto; }
        .btn.danger { color: #791F1F; border-color: #791F1F; }
        .pager { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 14px; font-size: 12.5px; color: #666; }
        .total { font-weight: normal; color: #777; font-size: 12px; }

        /* Vergrößerung (Klick auf das Foto) */
        .lb { position: fixed; inset: 0; background: rgba(0,0,0,.85); display: none; align-items: center; justify-content: center; z-index: 50; padding: 20px; }
        .lb.open { display: flex; }
        .lb img { max-width: 100%; max-height: 90vh; border-radius: 8px; }
        .lb button { position: absolute; top: 14px; right: 18px; font-size: 14px; padding: 7px 14px; border-radius: 8px; border: 0; background: #fff; cursor: pointer; font-family: inherit; }
        @media (max-width: 900px) {
            .up-row { grid-template-columns: 1fr; }
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

            <div class="title-row">
                <div>
                    <h1><?= e($t['page_title']) ?></h1>
                    <p><?= e($t['subtitle']) ?></p>
                </div>
            </div>

            <!-- FOTO SENDEN -->
            <div class="card">
                <div class="ct"><?= icon('camera') ?><?= e($t['sec_upload']) ?></div>

                <?php if ($form_error !== ""): ?>
                    <div class="error"><?= e($form_error) ?></div>
                <?php endif; ?>

                <form method="post" action="client_meal_photos.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="form" value="upload">
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$max_bytes ?>">

                    <div class="up-row">
                        <div>
                            <label class="fl" for="meal_type"><?= e($t['meal_type']) ?></label>
                            <div class="field">
                                <select id="meal_type" name="meal_type" required>
                                    <?php foreach ($meal_types as $num => $mt): ?>
                                        <option value="<?= $num ?>" <?= (string)$num === $v_type ? 'selected' : '' ?>><?= e($mt[0]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="fl"><?= e($t['photo']) ?></label>
                            <div class="file-pick">
                                <label class="btn sm" for="photo"><?= icon('camera') ?><?= e($t['choose_photo']) ?></label>
                                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required>
                                <span id="file-name"><?= e($t['no_file']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:10px">
                        <label class="fl" for="comment"><?= e($t['comment']) ?></label>
                        <div class="field"><textarea id="comment" name="comment" rows="2" maxlength="500" placeholder="<?= e($t['comment_ph']) ?>"><?= e($v_comment) ?></textarea></div>
                    </div>

                    <div class="send-row" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <button type="submit" class="btn fill"><?= icon('camera', 'w') ?><?= e($t['btn_upload']) ?></button>
                        <span class="hint"><?= e($t['upload_hint']) ?></span>
                    </div>
                </form>
            </div>

            <!-- MEINE FOTOS -->
            <div class="card" style="margin-top:14px">
                <div class="ct"><?= icon('camera') ?><?= e($t['sec_list']) ?> <span class="total">· <?= e(sprintf($t['total'], $total)) ?></span></div>

                <div class="chips">
                    <a class="chip <?= $f_type === 0 ? 'on' : '' ?>" href="client_meal_photos.php"><?= e($t['filter_all']) ?></a>
                    <?php foreach ($meal_types as $num => $mt): ?>
                        <a class="chip <?= $f_type === $num ? 'on' : '' ?>" href="client_meal_photos.php?type=<?= $num ?>"><?= e($mt[0]) ?></a>
                    <?php endforeach; ?>
                </div>

                <?php if (count($photos) === 0): ?>
                    <div class="empty"><?= e($t['empty']) ?></div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($photos as $p): ?>
                            <?php $mt = $meal_types[(int)$p['meal_type']] ?? $meal_types[4]; ?>
                            <div class="shot">
                                <button type="button" class="imgbtn" data-full="view_image.php?id=<?= (int)$p['photo_id'] ?>">
                                    <img src="view_image.php?id=<?= (int)$p['photo_id'] ?>" alt="<?= e($mt[0]) ?>" loading="lazy">
                                </button>
                                <div class="info">
                                    <span class="tag" style="background:<?= e($mt[1]) ?>;color:<?= e($mt[2]) ?>"><?= e($mt[0]) ?></span>
                                    <span><?= e(long_date($p['uploaded_at'])) ?>, <?= e(fmt_time(substr($p['uploaded_at'], 11))) ?></span>
                                    <?php if (trim($p['comment'] ?? '') !== ''): ?>
                                        <div class="cm"><?= e($p['comment']) ?></div>
                                    <?php endif; ?>
                                    <form method="post" action="client_meal_photos.php<?= $f_type > 0 ? '?type=' . $f_type : '' ?>"
                                          onsubmit="return confirm(<?= e(json_encode($t['confirm_delete'])) ?>);">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="form" value="delete">
                                        <input type="hidden" name="photo_id" value="<?= (int)$p['photo_id'] ?>">
                                        <button type="submit" class="btn sm danger"><?= icon('trash') ?><?= e($t['btn_delete']) ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($pages > 1): ?>
                        <div class="pager">
                            <span><?= e(sprintf($t['page_of'], $page, $pages)) ?></span>
                            <span>
                                <?php if ($page > 1): ?><a class="btn sm" href="client_meal_photos.php?page=<?= $page - 1 ?><?= e($qs) ?>"><?= e($t['prev']) ?></a><?php endif; ?>
                                <?php if ($page < $pages): ?><a class="btn sm" href="client_meal_photos.php?page=<?= $page + 1 ?><?= e($qs) ?>"><?= e($t['next']) ?></a><?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="note"><?= e(sprintf($t['feedback_note'], $short)) ?></div>
            </div>

        </div>
    </main>
</div>

<!-- vergrößertes Foto -->
<div class="lb" id="lb">
    <button type="button" id="lb-close"><?= e($t['close']) ?></button>
    <img src="" alt="" id="lb-img">
</div>

<script>
    // zeigt den Namen des gewählten Fotos neben dem Knopf
    var photoInput = document.getElementById('photo');
    var fileName   = document.getElementById('file-name');
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            fileName.textContent = photoInput.files.length ? photoInput.files[0].name : <?= json_encode($t['no_file']) ?>;
        });
    }

    // Foto per Klick vergrößern; schließen mit dem Knopf, durch Klick daneben oder mit Esc
    var lb = document.getElementById('lb');
    var lbImg = document.getElementById('lb-img');
    function closeLb() { lb.classList.remove('open'); lbImg.src = ''; }
    document.querySelectorAll('.imgbtn').forEach(function (b) {
        b.addEventListener('click', function () { lbImg.src = b.getAttribute('data-full'); lb.classList.add('open'); });
    });
    document.getElementById('lb-close').addEventListener('click', closeLb);
    lb.addEventListener('click', function (ev) { if (ev.target === lb) { closeLb(); } });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { closeLb(); } });
</script>

</body>
</html>
