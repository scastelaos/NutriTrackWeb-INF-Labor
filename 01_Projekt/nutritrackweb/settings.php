<?php
// settings.php
// Profileinstellungen der Ärztin (Admin): berufliche Daten, Marke (Logo, Foto und Farben),
// Zahlungsdaten (IBAN und PIX-QR-Code) und Passwort ändern.
// Regeln der V1:
//  - es gibt nur EIN Profil (eine Installation = eine Ernährungsberaterin): die Seite bearbeitet immer diese Zeile von nutritionist_profile
//  - die E-Mail wird nur gezeigt (sie kann hier nicht geändert werden)
//  - Farben: freie Farbauswahl (color picker) mit Vorschau, ohne Warnungen und ohne Sperre
//  - IBAN: wird nicht geprüft; es gilt nur die Länge des Feldes in der Datenbank (50 Zeichen)
//  - Bilder (Foto, Logo, PIX-QR): nur JPG und PNG, bis 8 MB, zufälliger Name in uploads/profile/;
//    SVG wird nicht akzeptiert; beim Ersetzen oder Entfernen wird die alte Datei gelöscht
//  - Links: wenn "https://" fehlt, ergänzt die Seite es selbst
//  - Passwort: aktuelles Passwort + neues Passwort zweimal, mindestens 8 Zeichen
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
        'page_title' => 'Profile Settings', 'subtitle' => 'Your professional information, branding and payment details.',
        'save' => 'Save changes',
        'sec_profile' => 'Profile information', 'sec_brand' => 'Branding', 'sec_pay' => 'Payment information', 'sec_account' => 'Account',
        'f_name' => 'Professional name', 'f_phone' => 'Phone', 'f_bio' => 'Bio', 'f_studies' => 'Education',
        'f_website' => 'Website', 'f_instagram' => 'Instagram', 'f_facebook' => 'Facebook', 'f_linkedin' => 'LinkedIn',
        'link_hint' => 'You can type it without "https://".',
        'img_photo' => 'Profile photo', 'img_logo' => 'Logo', 'img_pix' => 'PIX QR code',
        'img_hint' => 'JPG or PNG, up to 8 MB.', 'choose' => 'Change', 'choose_new' => 'Choose image', 'no_file' => 'No file chosen',
        'remove' => 'Remove', 'removing' => 'Will be removed when you save',
        'colors' => 'Colors', 'c_primary' => 'Primary', 'c_secondary' => 'Secondary', 'c_background' => 'Background', 'c_accent' => 'Accent',
        'c_hint' => 'Choose any color. The preview updates right away.', 'preview' => 'Preview',
        'pv_menu' => 'Menu', 'pv_button' => 'Button', 'pv_badge' => 'Badge', 'pv_text' => 'Sample text on the background color',
        'f_iban' => 'IBAN', 'iban_hint' => 'Shown to your clients when they tap "Pay". Saved exactly as you type it.',
        'pix_hint' => 'Clients scan this image to pay with PIX.',
        'f_email' => 'E-mail', 'email_note' => 'The e-mail cannot be changed here.',
        'pw_title' => 'Change password', 'cur_pw' => 'Current password', 'new_pw' => 'New password', 'confirm_pw' => 'Confirm new password',
        'pw_hint' => 'At least 8 characters.', 'btn_pw' => 'Change password', 'toggle_label' => 'Show or hide password',
        'saved_profile' => 'Your settings were saved.', 'saved_password' => 'Your password was changed.',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_name' => 'Please enter your professional name (up to 150 characters).',
        'err_long' => 'One of the texts is too long: phone and IBAN allow 50 characters, the bio and education 5000.',
        'err_link' => 'One of the links is not valid. Example: instagram.com/yourname',
        'err_color' => 'Please choose valid colors.',
        'err_img_size' => 'An image is too large. The maximum is 8 MB.',
        'err_img_type' => 'Only JPG and PNG images are allowed.',
        'err_img_save' => 'The image could not be saved. Please try again.',
        'err_noprofile' => 'The profile could not be found.',
        'err_pw_empty' => 'Please fill in all password fields.', 'err_pw_wrong' => 'The current password is incorrect.',
        'err_pw_short' => 'The new password must have at least 8 characters.', 'err_pw_match' => 'The new passwords do not match.',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'role' => 'Nutricionista',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Clientes', 'nav_appointments' => 'Consultas',
        'nav_messages' => 'Mensagens', 'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições',
        'nav_payments' => 'Pagamentos', 'nav_settings' => 'Configurações do Perfil', 'logout' => 'Sair',
        'kpi_messages' => 'Novas Mensagens', 'kpi_appointments' => 'Próximas Consultas',
        'kpi_payments' => 'Pagamentos em Aberto', 'kpi_pending' => 'Aprovações Pendentes',
        'page_title' => 'Configurações do Perfil', 'subtitle' => 'Suas informações profissionais, sua marca e seus dados de pagamento.',
        'save' => 'Salvar alterações',
        'sec_profile' => 'Informações do perfil', 'sec_brand' => 'Marca', 'sec_pay' => 'Informações de pagamento', 'sec_account' => 'Conta',
        'f_name' => 'Nome profissional', 'f_phone' => 'Telefone', 'f_bio' => 'Biografia', 'f_studies' => 'Formação',
        'f_website' => 'Site', 'f_instagram' => 'Instagram', 'f_facebook' => 'Facebook', 'f_linkedin' => 'LinkedIn',
        'link_hint' => 'Pode digitar sem "https://".',
        'img_photo' => 'Foto de perfil', 'img_logo' => 'Logo', 'img_pix' => 'QR Code do PIX',
        'img_hint' => 'JPG ou PNG, até 8 MB.', 'choose' => 'Trocar', 'choose_new' => 'Escolher imagem', 'no_file' => 'Nenhum arquivo escolhido',
        'remove' => 'Remover', 'removing' => 'Será removida ao salvar',
        'colors' => 'Cores', 'c_primary' => 'Principal', 'c_secondary' => 'Secundária', 'c_background' => 'Fundo', 'c_accent' => 'Destaque',
        'c_hint' => 'Escolha qualquer cor. A prévia muda na hora.', 'preview' => 'Prévia',
        'pv_menu' => 'Menu', 'pv_button' => 'Botão', 'pv_badge' => 'Etiqueta', 'pv_text' => 'Texto de exemplo sobre a cor de fundo',
        'f_iban' => 'IBAN', 'iban_hint' => 'Aparece para suas clientes quando tocam em "Pagar". É salvo exatamente como você digitar.',
        'pix_hint' => 'As clientes escaneiam esta imagem para pagar com PIX.',
        'f_email' => 'E-mail', 'email_note' => 'O e-mail não pode ser alterado aqui.',
        'pw_title' => 'Alterar senha', 'cur_pw' => 'Senha atual', 'new_pw' => 'Nova senha', 'confirm_pw' => 'Confirmar nova senha',
        'pw_hint' => 'Pelo menos 8 caracteres.', 'btn_pw' => 'Alterar senha', 'toggle_label' => 'Mostrar ou esconder a senha',
        'saved_profile' => 'Suas configurações foram salvas.', 'saved_password' => 'Sua senha foi alterada.',
        'err_invalid' => 'Pedido inválido. Recarregue a página e tente novamente.',
        'err_name' => 'Informe seu nome profissional (até 150 caracteres).',
        'err_long' => 'Um dos textos é longo demais: telefone e IBAN aceitam 50 caracteres, a biografia e a formação 5000.',
        'err_link' => 'Um dos links não é válido. Exemplo: instagram.com/seunome',
        'err_color' => 'Escolha cores válidas.',
        'err_img_size' => 'Uma imagem é grande demais. O máximo é 8 MB.',
        'err_img_type' => 'Só são aceitas imagens JPG e PNG.',
        'err_img_save' => 'Não foi possível salvar a imagem. Tente novamente.',
        'err_noprofile' => 'O perfil não foi encontrado.',
        'err_pw_empty' => 'Preencha todos os campos de senha.', 'err_pw_wrong' => 'A senha atual está incorreta.',
        'err_pw_short' => 'A nova senha deve ter pelo menos 8 caracteres.', 'err_pw_match' => 'As novas senhas não são iguais.',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'role' => 'Ernährungsberaterin',
        'nav_dashboard' => 'Dashboard', 'nav_clients' => 'Kunden', 'nav_appointments' => 'Termine',
        'nav_messages' => 'Nachrichten', 'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos',
        'nav_payments' => 'Zahlungen', 'nav_settings' => 'Profileinstellungen', 'logout' => 'Abmelden',
        'kpi_messages' => 'Neue Nachrichten', 'kpi_appointments' => 'Bevorstehende Termine',
        'kpi_payments' => 'Offene Zahlungen', 'kpi_pending' => 'Ausstehende Freigaben',
        'page_title' => 'Profil-Einstellungen', 'subtitle' => 'Ihre beruflichen Angaben, Ihr Erscheinungsbild und Ihre Zahlungsdaten.',
        'save' => 'Änderungen speichern',
        'sec_profile' => 'Profilinformationen', 'sec_brand' => 'Erscheinungsbild', 'sec_pay' => 'Zahlungsinformationen', 'sec_account' => 'Konto',
        'f_name' => 'Berufsbezeichnung / Name', 'f_phone' => 'Telefon', 'f_bio' => 'Biografie', 'f_studies' => 'Ausbildung',
        'f_website' => 'Website', 'f_instagram' => 'Instagram', 'f_facebook' => 'Facebook', 'f_linkedin' => 'LinkedIn',
        'link_hint' => 'Sie können es ohne "https://" eingeben.',
        'img_photo' => 'Profilfoto', 'img_logo' => 'Logo', 'img_pix' => 'PIX-QR-Code',
        'img_hint' => 'JPG oder PNG, bis 8 MB.', 'choose' => 'Ändern', 'choose_new' => 'Bild wählen', 'no_file' => 'Keine Datei gewählt',
        'remove' => 'Entfernen', 'removing' => 'Wird beim Speichern entfernt',
        'colors' => 'Farben', 'c_primary' => 'Primär', 'c_secondary' => 'Sekundär', 'c_background' => 'Hintergrund', 'c_accent' => 'Akzent',
        'c_hint' => 'Wählen Sie eine beliebige Farbe. Die Vorschau ändert sich sofort.', 'preview' => 'Vorschau',
        'pv_menu' => 'Menü', 'pv_button' => 'Button', 'pv_badge' => 'Etikett', 'pv_text' => 'Beispieltext auf der Hintergrundfarbe',
        'f_iban' => 'IBAN', 'iban_hint' => 'Wird Ihren Kunden bei "Bezahlen" angezeigt. Wird genau so gespeichert, wie Sie es eingeben.',
        'pix_hint' => 'Kunden scannen dieses Bild, um mit PIX zu bezahlen.',
        'f_email' => 'E-Mail', 'email_note' => 'Die E-Mail-Adresse kann hier nicht geändert werden.',
        'pw_title' => 'Passwort ändern', 'cur_pw' => 'Aktuelles Passwort', 'new_pw' => 'Neues Passwort', 'confirm_pw' => 'Neues Passwort bestätigen',
        'pw_hint' => 'Mindestens 8 Zeichen.', 'btn_pw' => 'Passwort ändern', 'toggle_label' => 'Passwort anzeigen oder verbergen',
        'saved_profile' => 'Ihre Einstellungen wurden gespeichert.', 'saved_password' => 'Ihr Passwort wurde geändert.',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_name' => 'Bitte geben Sie Ihren Namen ein (höchstens 150 Zeichen).',
        'err_long' => 'Einer der Texte ist zu lang: Telefon und IBAN erlauben 50 Zeichen, Biografie und Ausbildung 5000.',
        'err_link' => 'Einer der Links ist ungültig. Beispiel: instagram.com/ihrname',
        'err_color' => 'Bitte wählen Sie gültige Farben.',
        'err_img_size' => 'Ein Bild ist zu groß. Das Maximum sind 8 MB.',
        'err_img_type' => 'Es sind nur JPG- und PNG-Bilder erlaubt.',
        'err_img_save' => 'Das Bild konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.',
        'err_noprofile' => 'Das Profil wurde nicht gefunden.',
        'err_pw_empty' => 'Bitte füllen Sie alle Passwortfelder aus.', 'err_pw_wrong' => 'Das aktuelle Passwort ist falsch.',
        'err_pw_short' => 'Das neue Passwort muss mindestens 8 Zeichen haben.', 'err_pw_match' => 'Die neuen Passwörter stimmen nicht überein.',
        'err_other' => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.',
    ],
];
$t = $texts[$lang];

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


// BLOCK 5: Sicherheits-Token des Formulars (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// BLOCK 6: Was passiert, wenn ein Formular abgeschickt wird
$photo_dir = __DIR__ . '/uploads/profile/';
$photo_url = 'uploads/profile/';
$max_img   = 8 * 1024 * 1024;     // 8 MB pro Bild
$errors    = ['profile' => '', 'password' => ''];

// das Profil (eine einzige Zeile) und der eingeloggte Benutzer
$prof = $pdo->query("SELECT * FROM nutritionist_profile LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT email, password_hash FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

// gültige Farbe = #rrggbb
function valid_color($c) {
    return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}

// Link: ergänzt "https://", wenn es fehlt; gibt "" (leer), den fertigen Link oder false (ungültig) zurück
function clean_link($text) {
    $text = trim((string)$text);
    if ($text === "") { return ""; }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $text) && !preg_match('#^https?://#i', $text)) { return false; }
    if (!preg_match('#^https?://#i', $text)) { $text = 'https://' . $text; }
    // filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
    if (mb_strlen($text) > 255 || !filter_var($text, FILTER_VALIDATE_URL)) { return false; }
    $host = (string)parse_url($text, PHP_URL_HOST);
    if (strpos($host, '.') === false) { return false; }
    return $text;
}

// löscht eine alte Datei, aber nur, wenn sie wirklich in uploads/profile/ liegt
function delete_old_image($path, $dir, $url) {
    if (is_string($path) && preg_match('#^uploads/profile/[A-Za-z0-9._-]+$#', $path)) {
        $file = $dir . basename($path);
        // unlink() löscht eine Datei von der Festplatte.
        if (is_file($file)) { @unlink($file); }
    }
}

// speichert ein gesendetes Bild (echtes JPG/PNG). Gibt [Pfad|null, Fehler|""] zurück
function save_image($key, $dir, $url, $max, $t) {
    $f = $_FILES[$key] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) { return [null, ""]; }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > $max) {
        return [null, $t['err_img_size']];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) { return [null, $t['err_img_save']]; }

    // prüft den ECHTEN Typ am Inhalt (2 = JPG, 3 = PNG) und die Größe in Pixeln
    // getimagesize() liest den ECHTEN Inhalt des Bildes (Typ und Größe). So wissen wir, dass es wirklich ein Bild ist und nicht nur so heißt.
    $info = @getimagesize($f['tmp_name']);
    $ext  = ($info && $info[2] === IMAGETYPE_JPEG) ? 'jpg' : (($info && $info[2] === IMAGETYPE_PNG) ? 'png' : '');
    if ($ext === '') { return [null, $t['err_img_type']]; }
    if ($info[0] * $info[1] > 60000000) { return [null, $t['err_img_size']]; }
    if (!is_dir($dir) || !is_writable($dir)) { return [null, $t['err_img_save']]; }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    // move_uploaded_file() verschiebt die hochgeladene Datei vom Temp-Ordner des Servers in den Zielordner (geht nur bei echten Uploads).
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) { return [null, $t['err_img_save']]; }
    return [$url . $name, ""];
}

// Werte des Formulars (am Anfang das, was in der Datenbank steht)
$v = [
    'professional_name' => $prof['professional_name'] ?? '',
    'phone'     => $prof['phone'] ?? '',
    'bio'       => $prof['bio'] ?? '',
    'studies'   => $prof['studies'] ?? '',
    'website'   => $prof['website'] ?? '',
    'instagram' => $prof['instagram_url'] ?? '',
    'facebook'  => $prof['facebook_url'] ?? '',
    'linkedin'  => $prof['linkedin_url'] ?? '',
    'iban'      => $prof['iban'] ?? '',
    'primary_color'    => valid_color($prof['primary_color'] ?? '')    ? $prof['primary_color']    : '#2E7D32',
    'secundary_color'  => valid_color($prof['secundary_color'] ?? '')  ? $prof['secundary_color']  : '#A5D6A7',
    'background_color' => valid_color($prof['background_color'] ?? '') ? $prof['background_color'] : '#F5F7F5',
    'accent_color'     => valid_color($prof['accent_color'] ?? '')     ? $prof['accent_color']     : '#FF9800',
];

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form  = $_POST['form'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // war größer als post_max_size von PHP: dann verwirft PHP alles
        $errors['profile'] = $t['err_img_size'];

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    } elseif (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        $errors[$form === 'password' ? 'password' : 'profile'] = $t['err_invalid'];

    } elseif ($form === 'profile') {
        // ---------- PROFIL, MARKE UND ZAHLUNG SPEICHERN ----------
        if (!$prof) {
            $errors['profile'] = $t['err_noprofile'];
        } else {
            foreach (['professional_name', 'phone', 'bio', 'studies', 'website', 'instagram', 'facebook', 'linkedin', 'iban',
                      'primary_color', 'secundary_color', 'background_color', 'accent_color'] as $k) {
                $raw = (string)($_POST[$k] ?? '');
                $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $raw);   // entfernt Steuerzeichen (unsichtbare Sonderzeichen)
                $v[$k] = ($k === 'bio' || $k === 'studies') ? trim($raw) : trim(preg_replace('/\s+/', ' ', $raw));
            }
            // die Ärztin kann mehrere Absätze in Bio und Ausbildung schreiben: die Zeilenumbrüche bleiben
            foreach (['bio', 'studies'] as $k) {
                $v[$k] = trim(preg_replace('/\r\n?/', "\n", (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)($_POST[$k] ?? ''))));
            }

            $links = [];
            foreach (['website', 'instagram', 'facebook', 'linkedin'] as $k) {
                $links[$k] = clean_link($v[$k]);
            }

            if ($v['professional_name'] === '' || mb_strlen($v['professional_name']) > 150) {
                $errors['profile'] = $t['err_name'];
            } elseif (mb_strlen($v['phone']) > 50 || mb_strlen($v['iban']) > 50 || mb_strlen($v['bio']) > 5000 || mb_strlen($v['studies']) > 5000) {
                $errors['profile'] = $t['err_long'];
            } elseif (in_array(false, $links, true)) {
                $errors['profile'] = $t['err_link'];
            } elseif (!valid_color($v['primary_color']) || !valid_color($v['secundary_color'])
                      || !valid_color($v['background_color']) || !valid_color($v['accent_color'])) {
                $errors['profile'] = $t['err_color'];
            } else {
                foreach ($links as $k => $val) { $v[$k] = $val; }   // zeigt den schon ergänzten Link

                // neue Bilder (Foto, Logo, PIX-QR)
                $new_files = [];       // neue Dateien (um sie zu löschen, falls etwas schiefgeht)
                $final = [];
                foreach (['photo' => 'profile_photo_path', 'logo' => 'logo_path', 'pix' => 'pix_image_path'] as $key => $col) {
                    list($path, $err) = save_image($key, $photo_dir, $photo_url, $max_img, $t);
                    if ($err !== "") { $errors['profile'] = $err; break; }
                    if ($path !== null) {
                        $new_files[] = $path;
                        $final[$col] = $path;                                    // das Bild wurde ersetzt
                    } elseif (!empty($_POST['remove_' . $key])) {
                        $final[$col] = null;                                     // das Bild wurde entfernt
                    } else {
                        $final[$col] = $prof[$col];                              // das Bild bleibt dasselbe
                    }
                }

                if ($errors['profile'] === '') {
                    try {
                        $stmt = $pdo->prepare(
                            "UPDATE nutritionist_profile SET
                                professional_name = ?, phone = ?, bio = ?, studies = ?,
                                website = ?, instagram_url = ?, facebook_url = ?, linkedin_url = ?,
                                iban = ?, profile_photo_path = ?, logo_path = ?, pix_image_path = ?,
                                primary_color = ?, secundary_color = ?, background_color = ?, accent_color = ?
                             WHERE nutritionist_id = ?"
                        );
                        $nul = function ($x) { return $x === '' ? null : $x; };
                        $stmt->execute([
                            $v['professional_name'], $nul($v['phone']), $nul($v['bio']), $nul($v['studies']),
                            $nul($v['website']), $nul($v['instagram']), $nul($v['facebook']), $nul($v['linkedin']),
                            $nul($v['iban']), $final['profile_photo_path'], $final['logo_path'], $final['pix_image_path'],
                            strtoupper($v['primary_color']), strtoupper($v['secundary_color']),
                            strtoupper($v['background_color']), strtoupper($v['accent_color']),
                            $prof['nutritionist_id']
                        ]);

                        // es hat geklappt: jetzt werden die alten Bilder gelöscht, die ersetzt oder entfernt wurden
                        foreach (['profile_photo_path', 'logo_path', 'pix_image_path'] as $col) {
                            if (($prof[$col] ?? null) !== null && $prof[$col] !== $final[$col]) {
                                delete_old_image($prof[$col], $photo_dir, $photo_url);
                            }
                        }
                        header("Location: settings.php?done=profile");
                        exit;
                    } catch (PDOException $ex) {
                        foreach ($new_files as $p) { delete_old_image($p, $photo_dir, $photo_url); }
                        $errors['profile'] = $t['err_other'];
                    }
                } else {
                    foreach ($new_files as $p) { delete_old_image($p, $photo_dir, $photo_url); }
                }
            }
        }

    } elseif ($form === 'password') {
        // ---------- DAS PASSWORT ÄNDERN ----------
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $errors['password'] = $t['err_pw_empty'];
        // password_verify() vergleicht das eingegebene Passwort mit dem Hash aus der Datenbank.
        // Das echte Passwort wird nie gespeichert und nie zurückgerechnet.
        } elseif (!$account || !password_verify($current, $account['password_hash'])) {
            $errors['password'] = $t['err_pw_wrong'];
        } elseif (mb_strlen($new) < 8) {
            $errors['password'] = $t['err_pw_short'];
        } elseif ($new !== $confirm) {
            $errors['password'] = $t['err_pw_match'];
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                // password_hash() macht aus dem Passwort einen Hash (eine unlesbare Zeichenkette). Nur dieser Hash wird gespeichert (Spalte password_hash).
                $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
                // session_regenerate_id(true) gibt der Person eine NEUE Session-Nummer. Eine alte (gestohlene) Nummer funktioniert dann nicht mehr
                // (Schutz gegen Session Fixation).
                session_regenerate_id(true);        // neue Session nach dem Ändern des Passworts (session_regenerate_id erstellt eine neue Session-Nummer)
                header("Location: settings.php?done=password");
                exit;
            } catch (PDOException $ex) {
                $errors['password'] = $t['err_other'];
            }
        }
    }
}

// Erfolgsmeldung (kommt von der Weiterleitung)
$success = "";
if (($_GET['done'] ?? '') === 'profile')  { $success = $t['saved_profile']; }
if (($_GET['done'] ?? '') === 'password') { $success = $t['saved_password']; }

// BLOCK 7: Daten der Seite
// 7a: was bei den Bildern angezeigt wird (Pfade nur aus dem Ordner uploads/profile/)
$cur_img = [];
foreach (['photo' => 'profile_photo_path', 'logo' => 'logo_path', 'pix' => 'pix_image_path'] as $key => $col) {
    $p = (string)($prof[$col] ?? '');
    $cur_img[$key] = preg_match('#^uploads/profile/[A-Za-z0-9._-]+$#', $p) ? $p : '';
}

// 7f: die 4 Zahlen im Kopf
$count_messages = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE status = 0")->fetchColumn();
$count_appointments = (int)$pdo->query(
    "SELECT COUNT(*) FROM appointments
     WHERE status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()"
)->fetchColumn();
$count_payments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 0")->fetchColumn();
$count_pending  = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE approval_status = 0")->fetchColumn();

// BLOCK 8: Daten der Ärztin für die Seitenleiste und die Farben der Seite (schon mit dem, was gespeichert ist)
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
$current = "settings.php";
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

        /* Einstellungen */
        .sgrid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 14px; align-items: start; }
        .sgrid .card { margin-bottom: 14px; }
        .fgroup { margin-bottom: 10px; }
        .field textarea { min-height: 90px; }
        .hint { font-size: 11.5px; color: #777; margin-top: 3px; }
        .imgrow { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid #E5E8E5; flex-wrap: wrap; }
        .imgrow:first-of-type { border-top: none; padding-top: 0; }
        .thumb { width: 56px; height: 56px; border-radius: 10px; border: 1px solid #D8DDD8; background: #F5F7F5; flex: none; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #999; }
        .thumb img { width: 100%; height: 100%; object-fit: contain; }
        .thumb.round { border-radius: 50%; }
        .thumb.round img { object-fit: cover; }
        .imgtxt { flex: 1; min-width: 140px; }
        .imgtxt .t1 { font-size: 13px; font-weight: bold; }
        .fname { font-size: 11.5px; color: var(--primary); word-break: break-all; }
        .imgrow input[type=file] { display: none; }
        .pick { font-size: 12px; padding: 6px 12px; border-radius: 8px; cursor: pointer; border: 1px solid var(--primary); color: var(--primary); background: #fff; margin: 0; }
        .rm { font-size: 12px; padding: 6px 12px; border-radius: 8px; cursor: pointer; border: 1px solid #D9A3A3; color: #791F1F; background: #fff; display: inline-flex; align-items: center; gap: 6px; margin: 0; }
        .rm input { display: none; }   /* das Kästchen ist versteckt; der ganze Knopf ist klickbar */
        .rm .ico { width: 15px; height: 15px; }
        .rm.on { background: #F9D6D6; }
        .colors { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin: 8px 0 10px; }
        .cbox label { display: block; font-size: 12px; color: #555; margin-bottom: 4px; }
        .cbox input[type=color] { width: 100%; height: 38px; padding: 2px; border: 1px solid #D8DDD8; border-radius: 8px; background: #fff; cursor: pointer; }
        .cbox .hex { font-size: 11px; color: #777; font-family: Consolas, Menlo, monospace; margin-top: 2px; }
                .pv { display: flex; border: 1px solid #D8DDD8; border-radius: 10px; overflow: hidden; min-height: 120px; }
        .pv-side { width: 90px; padding: 10px 8px; color: #fff; font-size: 11px; background: var(--p); }
        .pv-side b { display: block; margin-bottom: 8px; font-size: 12px; }
        .pv-side span { display: block; padding: 4px 6px; border-radius: 6px; background: rgba(255,255,255,.18); margin-bottom: 4px; }
        .pv-main { flex: 1; padding: 10px; background: var(--b); color: #2B2A24; font-size: 12px; }
        .pv-top { display: flex; gap: 6px; margin-bottom: 8px; }
        .pv-kpi { flex: 1; border-radius: 6px; padding: 6px 8px; color: #fff; background: var(--p); font-size: 11px; }
        .pv-kpi.hl { background: var(--a); }
        .pv-btn { display: inline-block; padding: 5px 12px; border-radius: 8px; background: var(--p); color: #fff; font-size: 12px; margin-right: 6px; }
        .pv-badge { display: inline-block; padding: 3px 10px; border-radius: 99px; background: var(--s); color: #2B2A24; font-size: 11px; font-weight: bold; }
        .pv-text { margin-top: 8px; }
        .field.pw input { padding-right: 40px; }
        .toggle { position: absolute; right: 7px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 4px; cursor: pointer; display: flex; align-items: center; }
        .toggle img { width: 17px; height: 17px; opacity: .6; filter: brightness(0); }
        .pwcard { max-width: 520px; }
        @media (max-width: 900px) { .sgrid { grid-template-columns: 1fr; } .colors { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
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

        <div class="title-row">
            <div>
                <h1><?= e($t['page_title']) ?></h1>
                <p><?= e($t['subtitle']) ?></p>
            </div>
            <!-- der Knopf steht hier oben, sendet aber das Hauptformular (form="settings_form") -->
            <button type="submit" form="settings_form" class="btn fill"><?= icon('check', 'w') ?><?= e($t['save']) ?></button>
        </div>

        <?php if ($errors['profile'] !== ""): ?>
            <div class="error"><?= e($errors['profile']) ?></div>
        <?php endif; ?>

        <form id="settings_form" method="post" action="settings.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="form" value="profile">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$max_img ?>">

            <div class="sgrid">
                <!-- LINKE SPALTE -->
                <div>
                    <div class="card">
                        <div class="ct"><?= icon('user') ?><?= e($t['sec_profile']) ?></div>

                        <div class="fgroup">
                            <label class="fl" for="professional_name"><?= e($t['f_name']) ?></label>
                            <div class="field"><input type="text" id="professional_name" name="professional_name" maxlength="150" required value="<?= e($v['professional_name']) ?>"></div>
                        </div>
                        <div class="fgroup">
                            <label class="fl" for="phone"><?= e($t['f_phone']) ?></label>
                            <div class="field"><input type="text" id="phone" name="phone" maxlength="50" value="<?= e($v['phone']) ?>"></div>
                        </div>
                        <div class="fgroup">
                            <label class="fl" for="bio"><?= e($t['f_bio']) ?></label>
                            <div class="field"><textarea id="bio" name="bio" maxlength="5000"><?= e($v['bio']) ?></textarea></div>
                        </div>
                        <div class="fgroup">
                            <label class="fl" for="studies"><?= e($t['f_studies']) ?></label>
                            <div class="field"><textarea id="studies" name="studies" maxlength="5000"><?= e($v['studies']) ?></textarea></div>
                        </div>
                        <?php foreach ([['website', 'f_website', 'website'], ['instagram', 'f_instagram', 'instagram'], ['facebook', 'f_facebook', 'facebook'], ['linkedin', 'f_linkedin', 'linkedin']] as $lk): ?>
                            <div class="fgroup">
                                <label class="fl" for="<?= $lk[0] ?>"><?= e($t[$lk[1]]) ?></label>
                                <div class="field"><input type="text" id="<?= $lk[0] ?>" name="<?= $lk[0] ?>" maxlength="255" autocapitalize="none" value="<?= e($v[$lk[2]]) ?>"></div>
                            </div>
                        <?php endforeach; ?>
                        <div class="hint"><?= e($t['link_hint']) ?></div>
                    </div>

                    <div class="card">
                        <div class="ct"><?= icon('wallet') ?><?= e($t['sec_pay']) ?></div>
                        <div class="fgroup">
                            <label class="fl" for="iban"><?= e($t['f_iban']) ?></label>
                            <div class="field"><input type="text" id="iban" name="iban" maxlength="50" value="<?= e($v['iban']) ?>"></div>
                            <div class="hint"><?= e($t['iban_hint']) ?></div>
                        </div>
                <div class="imgrow">
                    <div class="thumb" id="thumb-pix">
                        <?php if ($cur_img['pix'] !== ''): ?><img src="<?= e($cur_img['pix']) ?>" alt=""><?php else: ?>-<?php endif; ?>
                    </div>
                    <div class="imgtxt">
                        <div class="t1"><?= e($t['img_pix']) ?></div>
                        <div class="hint"><?= e($t['pix_hint']) ?></div>
                        <div class="fname" id="fname-pix"><?= e($t['no_file']) ?></div>
                    </div>
                    <label class="pick" for="file-pix"><?= e($cur_img['pix'] !== '' ? $t['choose'] : $t['choose_new']) ?></label>
                    <input type="file" id="file-pix" name="pix" accept="image/jpeg,image/png" data-key="pix">
                    <?php if ($cur_img['pix'] !== ''): ?>
                        <label class="rm" id="rm-pix"><input type="checkbox" name="remove_pix" value="1" data-key="pix"><?= icon('trash') ?><?= e($t['remove']) ?></label>
                    <?php endif; ?>
                </div>
                    </div>
                </div>

                <!-- RECHTE SPALTE -->
                <div>
                    <div class="card">
                        <div class="ct"><?= icon('settings') ?><?= e($t['sec_brand']) ?></div>
                <div class="imgrow">
                    <div class="thumb round" id="thumb-photo">
                        <?php if ($cur_img['photo'] !== ''): ?><img src="<?= e($cur_img['photo']) ?>" alt=""><?php else: ?>-<?php endif; ?>
                    </div>
                    <div class="imgtxt">
                        <div class="t1"><?= e($t['img_photo']) ?></div>
                        <div class="hint"><?= e($t['img_hint']) ?></div>
                        <div class="fname" id="fname-photo"><?= e($t['no_file']) ?></div>
                    </div>
                    <label class="pick" for="file-photo"><?= e($cur_img['photo'] !== '' ? $t['choose'] : $t['choose_new']) ?></label>
                    <input type="file" id="file-photo" name="photo" accept="image/jpeg,image/png" data-key="photo">
                    <?php if ($cur_img['photo'] !== ''): ?>
                        <label class="rm" id="rm-photo"><input type="checkbox" name="remove_photo" value="1" data-key="photo"><?= icon('trash') ?><?= e($t['remove']) ?></label>
                    <?php endif; ?>
                </div>
                <div class="imgrow">
                    <div class="thumb" id="thumb-logo">
                        <?php if ($cur_img['logo'] !== ''): ?><img src="<?= e($cur_img['logo']) ?>" alt=""><?php else: ?>-<?php endif; ?>
                    </div>
                    <div class="imgtxt">
                        <div class="t1"><?= e($t['img_logo']) ?></div>
                        <div class="hint"><?= e($t['img_hint']) ?></div>
                        <div class="fname" id="fname-logo"><?= e($t['no_file']) ?></div>
                    </div>
                    <label class="pick" for="file-logo"><?= e($cur_img['logo'] !== '' ? $t['choose'] : $t['choose_new']) ?></label>
                    <input type="file" id="file-logo" name="logo" accept="image/jpeg,image/png" data-key="logo">
                    <?php if ($cur_img['logo'] !== ''): ?>
                        <label class="rm" id="rm-logo"><input type="checkbox" name="remove_logo" value="1" data-key="logo"><?= icon('trash') ?><?= e($t['remove']) ?></label>
                    <?php endif; ?>
                </div>

                        <div class="imgrow" style="display:block">
                            <div class="t1" style="font-size:13px;font-weight:bold"><?= e($t['colors']) ?></div>
                            <div class="hint"><?= e($t['c_hint']) ?></div>
                            <div class="colors">
                                <?php foreach ([['primary_color', 'c_primary'], ['secundary_color', 'c_secondary'], ['background_color', 'c_background'], ['accent_color', 'c_accent']] as $c): ?>
                                    <div class="cbox">
                                        <label for="<?= $c[0] ?>"><?= e($t[$c[1]]) ?></label>
                                        <input type="color" id="<?= $c[0] ?>" name="<?= $c[0] ?>" value="<?= e(strtolower($v[$c[0]])) ?>">
                                        <div class="hex" id="hex-<?= $c[0] ?>"><?= e(strtoupper($v[$c[0]])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="fl" style="margin-top:6px"><?= e($t['preview']) ?></div>
                            <div class="pv" id="pv">
                                <div class="pv-side"><b><?= e($t['pv_menu']) ?></b><span>Dashboard</span><span>Clients</span></div>
                                <div class="pv-main">
                                    <div class="pv-top"><div class="pv-kpi">12</div><div class="pv-kpi hl">3</div></div>
                                    <span class="pv-btn"><?= e($t['pv_button']) ?></span><span class="pv-badge"><?= e($t['pv_badge']) ?></span>
                                    <div class="pv-text"><?= e($t['pv_text']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- KONTO: E-Mail (nur lesen) und Passwort ändern (eigenes Formular) -->
        <div class="card pwcard">
            <div class="ct"><?= icon('lock') ?><?= e($t['sec_account']) ?></div>

            <div class="fgroup">
                <label class="fl" for="email"><?= e($t['f_email']) ?></label>
                <div class="field"><input type="email" id="email" value="<?= e($account['email'] ?? '') ?>" readonly></div>
                <div class="hint"><?= e($t['email_note']) ?></div>
            </div>

            <div class="ct" style="margin-top:14px"><?= e($t['pw_title']) ?></div>
            <?php if ($errors['password'] !== ""): ?>
                <div class="error"><?= e($errors['password']) ?></div>
            <?php endif; ?>

            <form method="post" action="settings.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="form" value="password">
                <?php foreach ([['current_password', 'cur_pw'], ['new_password', 'new_pw'], ['confirm_password', 'confirm_pw']] as $pw): ?>
                    <label class="fl" for="<?= $pw[0] ?>"><?= e($t[$pw[1]]) ?></label>
                    <div class="field has-icon pw" style="margin-bottom:10px">
                        <img class="icon" src="icons/lock.svg" alt="">
                        <input type="password" id="<?= $pw[0] ?>" name="<?= $pw[0] ?>" autocomplete="<?= $pw[0] === 'current_password' ? 'current-password' : 'new-password' ?>" required>
                        <button type="button" class="toggle" data-target="<?= $pw[0] ?>" aria-label="<?= e($t['toggle_label']) ?>">
                            <img src="icons/eye.svg" alt="">
                        </button>
                    </div>
                <?php endforeach; ?>
                <div class="hint" style="margin:-4px 0 10px"><?= e($t['pw_hint']) ?></div>
                <button type="submit" class="btn"><?= icon('lock') ?><?= e($t['btn_pw']) ?></button>
            </form>
        </div>
    </main>
</div>

<script>
    // --- Vorschau der Farben (ändert sich sofort) ---
    var ids = {primary: 'primary_color', secondary: 'secundary_color', background: 'background_color', accent: 'accent_color'};
    function updatePreview() {
        var pv = document.getElementById('pv');
        var val = function (k) { return document.getElementById(ids[k]).value; };
        pv.style.setProperty('--p', val('primary'));
        pv.style.setProperty('--s', val('secondary'));
        pv.style.setProperty('--b', val('background'));
        pv.style.setProperty('--a', val('accent'));
        Object.keys(ids).forEach(function (k) {
            document.getElementById('hex-' + ids[k]).textContent = val(k).toUpperCase();
        });
    }
    Object.keys(ids).forEach(function (k) {
        document.getElementById(ids[k]).addEventListener('input', updatePreview);
    });
    updatePreview();

    // --- gewähltes Bild: zeigt die Vorschau und den Namen; "Entfernen" markiert/entmarkiert ---
    document.querySelectorAll('input[type=file][data-key]').forEach(function (input) {
        input.addEventListener('change', function () {
            var key = input.getAttribute('data-key');
            var f = input.files[0];
            document.getElementById('fname-' + key).textContent = f ? f.name : <?= json_encode($t['no_file']) ?>;
            if (f) {
                var thumb = document.getElementById('thumb-' + key);
                thumb.innerHTML = '';
                var img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                thumb.appendChild(img);
                var rm = document.querySelector('input[name="remove_' + key + '"]');
                if (rm) { rm.checked = false; rm.parentNode.classList.remove('on'); }
            }
        });
    });
    document.querySelectorAll('.rm input').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var key = cb.getAttribute('data-key');
            cb.parentNode.classList.toggle('on', cb.checked);
            if (cb.checked) {
                document.getElementById('file-' + key).value = '';
                document.getElementById('fname-' + key).textContent = <?= json_encode($t['removing']) ?>;
            } else {
                document.getElementById('fname-' + key).textContent = <?= json_encode($t['no_file']) ?>;
            }
        });
    });

    // --- Auge des Passworts ---
    document.querySelectorAll('.toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var field = document.getElementById(btn.getAttribute('data-target'));
            var icon  = btn.querySelector('img');
            if (field.type === 'password') { field.type = 'text'; icon.src = 'icons/eye-off.svg'; }
            else { field.type = 'password'; icon.src = 'icons/eye.svg'; }
        });
    });
</script>

</body>
</html>
