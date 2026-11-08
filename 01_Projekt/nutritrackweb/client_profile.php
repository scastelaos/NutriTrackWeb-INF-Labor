<?php
// client_profile.php
// "My Profile" des FREIGEGEBENEN Kunden: persönliche Daten ansehen und bearbeiten, das Foto wechseln,
// das Passwort ändern und einen neuen Messwert für Gewicht/Größe hinzufügen.
// Es sind 3 unabhängige Formulare auf derselben Seite (Profil, Passwort und Messwert).

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
$stmt = $pdo->prepare("SELECT role, email, password_hash FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

if (!$me || $me['role'] != 0) {
    header("Location: login.php?lang=" . $lang);
    exit;
}

// 2e: die Daten des Kunden holen (der approval_status kommt IMMER aus der Datenbank)
$stmt = $pdo->prepare(
    "SELECT client_id, first_name, last_name, profile_photo_path, phone, country, city,
            date_of_birth, preferred_language, goal, approval_status,
            registration_date, monthly_fee, currency
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
        'page_title' => 'My Profile', 'your_nutri' => 'Your nutritionist: %s',
        'nav_home' => 'Home', 'nav_appointments' => 'Appointments', 'nav_messages' => 'Messages',
        'nav_documents' => 'Documents', 'nav_photos' => 'Meal Photos', 'nav_payments' => 'Payments',
        'nav_profile' => 'My Profile', 'logout' => 'Logout',
        'kpi_appt' => 'Next Appointment', 'kpi_reply' => 'New Reply', 'kpi_payment' => 'Payment', 'kpi_weight' => 'Current Weight',
        'due' => 'Due', 'today' => 'Today', 'tomorrow' => 'Tomorrow', 'yesterday' => 'Yesterday',
        'subtitle' => 'Keep your details up to date.', 'save' => 'Save changes',
        'sec_personal' => 'Personal data', 'photo' => 'Profile photo', 'change_photo' => 'Change photo',
        'photo_hint' => 'JPG or PNG, up to 2 MB.', 'remove' => 'Remove', 'removing' => 'Will be removed when you save', 'photo_chosen' => 'Selected: %s',
        'first_name' => 'First name', 'last_name' => 'Last name', 'phone' => 'Phone', 'dob' => 'Date of birth',
        'country' => 'Country', 'country_ph' => 'Select your country', 'city' => 'City',
        'pref_lang' => 'Preferred language', 'goal' => 'Goal',
        'sec_account' => 'Account', 'email' => 'Email', 'email_note' => 'Your email cannot be changed here.',
        'password' => 'Password', 'pw_title' => 'Change password', 'cur_pw' => 'Current password',
        'new_pw' => 'New password', 'confirm_pw' => 'Confirm new password',
        'pw_hint' => 'At least 8 characters.', 'btn_pw' => 'Change password', 'toggle_label' => 'Show or hide password',
        'sec_plan' => 'Your plan', 'nutritionist' => 'Nutritionist', 'fee' => 'Monthly fee', 'currency' => 'Currency',
        'since' => 'Client since', 'plan_note' => 'Set by %s. Contact your nutritionist to change it.',
        'sec_hist' => 'Weight and height history', 'add_title' => 'Add new measurement',
        'weight' => 'Weight (kg)', 'height' => 'Height (cm)', 'btn_add' => 'Add measurement',
        'start_now' => 'Start %1$s · Now %2$s', 'height_line' => 'Height %s', 'last_update' => 'Last update: %s',
        'hist_empty' => 'No measurements yet.', 'hist_date' => 'Date',
        'saved_profile' => 'Your profile was saved.', 'saved_password' => 'Your password was changed.',
        'saved_measure' => 'Measurement added.', 'saved_delete' => 'Measurement deleted.',
        'delete_label' => 'Delete measurement', 'confirm_delete' => 'Delete this measurement?',
        'err_invalid' => 'Invalid request. Please reload the page and try again.',
        'err_required' => 'Please fill in your first and last name.',
        'err_dob' => 'Please enter a valid date of birth.', 'err_country' => 'Please choose a country from the list.',
        'err_goal' => 'Your goal is too long (maximum 255 characters).',
        'err_photo_type' => 'The photo must be a JPG or PNG image.',
        'err_photo_size' => 'The photo is too large (maximum 2 MB).',
        'err_photo_save' => 'The photo could not be saved. Please try again.',
        'err_pw_empty' => 'Please fill in all password fields.', 'err_pw_wrong' => 'The current password is incorrect.',
        'err_pw_short' => 'The new password must have at least 8 characters.',
        'err_pw_match' => 'The new passwords do not match.',
        'err_meas_empty' => 'Please enter your weight or your height.',
        'err_weight' => 'Please enter a valid weight (in kg).', 'err_height' => 'Please enter a valid height (in cm).',
        'err_other' => 'Something went wrong. Please try again.',
    ],
    'pt' => [
        'page_title' => 'Meu Perfil', 'your_nutri' => 'Sua nutricionista: %s',
        'nav_home' => 'Início', 'nav_appointments' => 'Consultas', 'nav_messages' => 'Mensagens',
        'nav_documents' => 'Documentos', 'nav_photos' => 'Fotos das Refeições', 'nav_payments' => 'Pagamentos',
        'nav_profile' => 'Meu Perfil', 'logout' => 'Sair',
        'kpi_appt' => 'Próxima Consulta', 'kpi_reply' => 'Nova Resposta', 'kpi_payment' => 'Pagamento', 'kpi_weight' => 'Peso Atual',
        'due' => 'Vence', 'today' => 'Hoje', 'tomorrow' => 'Amanhã', 'yesterday' => 'Ontem',
        'subtitle' => 'Mantenha seus dados atualizados.', 'save' => 'Salvar alterações',
        'sec_personal' => 'Dados pessoais', 'photo' => 'Foto de perfil', 'change_photo' => 'Trocar foto',
        'photo_hint' => 'JPG ou PNG, até 2 MB.', 'remove' => 'Remover', 'removing' => 'Será removida ao salvar', 'photo_chosen' => 'Selecionada: %s',
        'first_name' => 'Nome', 'last_name' => 'Sobrenome', 'phone' => 'Telefone', 'dob' => 'Data de nascimento',
        'country' => 'País', 'country_ph' => 'Selecione seu país', 'city' => 'Cidade',
        'pref_lang' => 'Idioma preferido', 'goal' => 'Objetivo',
        'sec_account' => 'Conta', 'email' => 'E-mail', 'email_note' => 'Seu e-mail não pode ser alterado aqui.',
        'password' => 'Senha', 'pw_title' => 'Alterar senha', 'cur_pw' => 'Senha atual',
        'new_pw' => 'Nova senha', 'confirm_pw' => 'Confirmar nova senha',
        'pw_hint' => 'Pelo menos 8 caracteres.', 'btn_pw' => 'Alterar senha', 'toggle_label' => 'Mostrar ou esconder a senha',
        'sec_plan' => 'Seu plano', 'nutritionist' => 'Nutricionista', 'fee' => 'Mensalidade', 'currency' => 'Moeda',
        'since' => 'Cliente desde', 'plan_note' => 'Definido por %s. Fale com sua nutricionista para alterar.',
        'sec_hist' => 'Histórico de peso e altura', 'add_title' => 'Adicionar nova medida',
        'weight' => 'Peso (kg)', 'height' => 'Altura (cm)', 'btn_add' => 'Adicionar medida',
        'start_now' => 'Início %1$s · Agora %2$s', 'height_line' => 'Altura %s', 'last_update' => 'Última atualização: %s',
        'hist_empty' => 'Ainda não há medidas.', 'hist_date' => 'Data',
        'saved_profile' => 'Seu perfil foi salvo.', 'saved_password' => 'Sua senha foi alterada.',
        'saved_measure' => 'Medida adicionada.', 'saved_delete' => 'Medida excluída.',
        'delete_label' => 'Excluir medida', 'confirm_delete' => 'Excluir esta medida?',
        'err_invalid' => 'Solicitação inválida. Recarregue a página e tente novamente.',
        'err_required' => 'Preencha seu nome e sobrenome.',
        'err_dob' => 'Informe uma data de nascimento válida.', 'err_country' => 'Escolha um país da lista.',
        'err_goal' => 'O objetivo está muito longo (máximo de 255 caracteres).',
        'err_photo_type' => 'A foto deve ser uma imagem JPG ou PNG.',
        'err_photo_size' => 'A foto é grande demais (máximo de 2 MB).',
        'err_photo_save' => 'Não foi possível salvar a foto. Tente novamente.',
        'err_pw_empty' => 'Preencha todos os campos de senha.', 'err_pw_wrong' => 'A senha atual está incorreta.',
        'err_pw_short' => 'A nova senha deve ter pelo menos 8 caracteres.',
        'err_pw_match' => 'As novas senhas não são iguais.',
        'err_meas_empty' => 'Informe seu peso ou sua altura.',
        'err_weight' => 'Informe um peso válido (em kg).', 'err_height' => 'Informe uma altura válida (em cm).',
        'err_other' => 'Algo deu errado. Tente novamente.',
    ],
    'de' => [
        'page_title' => 'Mein Profil', 'your_nutri' => 'Ihre Ernährungsberaterin: %s',
        'nav_home' => 'Start', 'nav_appointments' => 'Termine', 'nav_messages' => 'Nachrichten',
        'nav_documents' => 'Dokumente', 'nav_photos' => 'Mahlzeitenfotos', 'nav_payments' => 'Zahlungen',
        'nav_profile' => 'Mein Profil', 'logout' => 'Abmelden',
        'kpi_appt' => 'Nächster Termin', 'kpi_reply' => 'Neue Antwort', 'kpi_payment' => 'Zahlung', 'kpi_weight' => 'Aktuelles Gewicht',
        'due' => 'Fällig', 'today' => 'Heute', 'tomorrow' => 'Morgen', 'yesterday' => 'Gestern',
        'subtitle' => 'Halten Sie Ihre Daten aktuell.', 'save' => 'Änderungen speichern',
        'sec_personal' => 'Persönliche Daten', 'photo' => 'Profilfoto', 'change_photo' => 'Foto ändern',
        'photo_hint' => 'JPG oder PNG, bis 2 MB.', 'remove' => 'Entfernen', 'removing' => 'Wird beim Speichern entfernt', 'photo_chosen' => 'Ausgewählt: %s',
        'first_name' => 'Vorname', 'last_name' => 'Nachname', 'phone' => 'Telefon', 'dob' => 'Geburtsdatum',
        'country' => 'Land', 'country_ph' => 'Land auswählen', 'city' => 'Stadt',
        'pref_lang' => 'Bevorzugte Sprache', 'goal' => 'Ziel',
        'sec_account' => 'Konto', 'email' => 'E-Mail', 'email_note' => 'Ihre E-Mail-Adresse kann hier nicht geändert werden.',
        'password' => 'Passwort', 'pw_title' => 'Passwort ändern', 'cur_pw' => 'Aktuelles Passwort',
        'new_pw' => 'Neues Passwort', 'confirm_pw' => 'Neues Passwort bestätigen',
        'pw_hint' => 'Mindestens 8 Zeichen.', 'btn_pw' => 'Passwort ändern', 'toggle_label' => 'Passwort anzeigen oder verbergen',
        'sec_plan' => 'Ihr Plan', 'nutritionist' => 'Ernährungsberaterin', 'fee' => 'Monatsbetrag', 'currency' => 'Währung',
        'since' => 'Kunde seit', 'plan_note' => 'Festgelegt von %s. Wenden Sie sich zur Änderung an Ihre Ernährungsberaterin.',
        'sec_hist' => 'Gewichts- und Größenverlauf', 'add_title' => 'Neue Messung hinzufügen',
        'weight' => 'Gewicht (kg)', 'height' => 'Größe (cm)', 'btn_add' => 'Messung hinzufügen',
        'start_now' => 'Start %1$s · Jetzt %2$s', 'height_line' => 'Größe %s', 'last_update' => 'Letzte Aktualisierung: %s',
        'hist_empty' => 'Noch keine Messungen.', 'hist_date' => 'Datum',
        'saved_profile' => 'Ihr Profil wurde gespeichert.', 'saved_password' => 'Ihr Passwort wurde geändert.',
        'saved_measure' => 'Messung hinzugefügt.', 'saved_delete' => 'Messung gelöscht.',
        'delete_label' => 'Messung löschen', 'confirm_delete' => 'Diese Messung löschen?',
        'err_invalid' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_required' => 'Bitte geben Sie Vor- und Nachnamen ein.',
        'err_dob' => 'Bitte geben Sie ein gültiges Geburtsdatum ein.', 'err_country' => 'Bitte wählen Sie ein Land aus der Liste.',
        'err_goal' => 'Ihr Ziel ist zu lang (maximal 255 Zeichen).',
        'err_photo_type' => 'Das Foto muss ein JPG- oder PNG-Bild sein.',
        'err_photo_size' => 'Das Foto ist zu groß (maximal 2 MB).',
        'err_photo_save' => 'Das Foto konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.',
        'err_pw_empty' => 'Bitte füllen Sie alle Passwortfelder aus.', 'err_pw_wrong' => 'Das aktuelle Passwort ist falsch.',
        'err_pw_short' => 'Das neue Passwort muss mindestens 8 Zeichen lang sein.',
        'err_pw_match' => 'Die neuen Passwörter stimmen nicht überein.',
        'err_meas_empty' => 'Bitte geben Sie Gewicht oder Größe ein.',
        'err_weight' => 'Bitte geben Sie ein gültiges Gewicht (in kg) ein.', 'err_height' => 'Bitte geben Sie eine gültige Größe (in cm) ein.',
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

// Liste der Länder: der Schlüssel (auf Englisch) ist das, was in die Datenbank geht (derselbe wie in register.php)
$countries = [
    'Austria'        => ['de' => 'Österreich',          'pt' => 'Áustria'],
    'Germany'        => ['de' => 'Deutschland',         'pt' => 'Alemanha'],
    'Switzerland'    => ['de' => 'Schweiz',             'pt' => 'Suíça'],
    'Portugal'       => ['de' => 'Portugal',            'pt' => 'Portugal'],
    'Brazil'         => ['de' => 'Brasilien',           'pt' => 'Brasil'],
    'Spain'          => ['de' => 'Spanien',             'pt' => 'Espanha'],
    'France'         => ['de' => 'Frankreich',          'pt' => 'França'],
    'Italy'          => ['de' => 'Italien',             'pt' => 'Itália'],
    'United Kingdom' => ['de' => 'Vereinigtes Königreich', 'pt' => 'Reino Unido'],
    'Ireland'        => ['de' => 'Irland',              'pt' => 'Irlanda'],
    'Netherlands'    => ['de' => 'Niederlande',         'pt' => 'Países Baixos'],
    'Belgium'        => ['de' => 'Belgien',             'pt' => 'Bélgica'],
    'Luxembourg'     => ['de' => 'Luxemburg',           'pt' => 'Luxemburgo'],
    'United States'  => ['de' => 'Vereinigte Staaten',  'pt' => 'Estados Unidos'],
    'Canada'         => ['de' => 'Kanada',              'pt' => 'Canadá'],
    'Other'          => ['de' => 'Anderes Land',        'pt' => 'Outro país'],
];

// Nummer der Sprache (clients.preferred_language) <-> Code der Sprache der Seite
$lang_codes = [0 => 'en', 1 => 'de', 2 => 'pt'];

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

// Uhrzeit ohne Sekunden ("09:00:00" wird zu "09:00")
function fmt_time($time) {
    return $time ? substr($time, 0, 5) : "";
}

// Betrag mit Währungssymbol ("R$ 350.00")
function money($fee, $currency) {
    if ($fee === null || $fee === '') {
        return "-";
    }
    $symbols = ['BRL' => 'R$', 'EUR' => '€', 'USD' => '$'];
    $symbol  = $symbols[$currency] ?? $currency;
    return $symbol . ' ' . number_format($fee, 2, '.', '');
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

// BLOCK 6: Sicherheits-Token (CSRF), das die 3 Formulare benutzen
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ordner der Profilfotos (muss existieren; seine .htaccess verhindert, dass PHP dort läuft)
$photo_dir = __DIR__ . '/uploads/profile/';
$photo_url = 'uploads/profile/';

// Werte des Profilformulars (am Anfang das, was in der Datenbank steht)
$v = [
    'first_name' => $client['first_name'] ?? '', 'last_name' => $client['last_name'] ?? '',
    'phone' => $client['phone'] ?? '', 'dob' => $client['date_of_birth'] ?? '',
    'country' => $client['country'] ?? '', 'city' => $client['city'] ?? '',
    'preferred_language' => (string)(int)$client['preferred_language'], 'goal' => $client['goal'] ?? '',
];
$m_weight = "";
$m_height = "";

// ein Fehler pro Formular: er erscheint in der richtigen Karte
$errors = ['profile' => '', 'password' => '', 'measure' => ''];

// BLOCK 7: Was passiert, wenn ein Formular abgeschickt wird
// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form  = $_POST['form'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        // ohne bestimmten Formularfehler: wird im Profilformular gezeigt
        $errors['profile'] = $t['err_invalid'];

    } elseif ($form === 'profile') {
        // ---------- 7A: DAS PROFIL SPEICHERN ----------
        foreach ($v as $key => $old) {
            $v[$key] = trim($_POST[$key] ?? '');
        }

        $dob = null;
        $country = null;
        $pref = (int)$v['preferred_language'];

        if ($v['first_name'] === '' || $v['last_name'] === '') {
            $errors['profile'] = $t['err_required'];
        } elseif (mb_strlen($v['goal']) > 255) {
            $errors['profile'] = $t['err_goal'];
        } elseif (!in_array($v['preferred_language'], ['0', '1', '2'], true)) {
            $errors['profile'] = $t['err_invalid'];
        }

        // Geburtsdatum (optional; wenn angegeben, muss es ein echtes Datum in der Vergangenheit sein)
        if ($errors['profile'] === '' && $v['dob'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $v['dob']);
            if (!$d || $d->format('Y-m-d') !== $v['dob']
                || $d > new DateTime('today') || (int)$d->format('Y') < 1900) {
                $errors['profile'] = $t['err_dob'];
            } else {
                $dob = $v['dob'];
            }
        }

        // Land: eins aus der Liste (oder das, was schon gespeichert war, damit nichts aus Versehen gelöscht wird)
        if ($errors['profile'] === '' && $v['country'] !== '') {
            if (array_key_exists($v['country'], $countries) || $v['country'] === ($client['country'] ?? '')) {
                $country = $v['country'];
            } else {
                $errors['profile'] = $t['err_country'];
            }
        }

        // neues Foto (optional)
        $new_photo = null;
        $photo_file = $_FILES['photo'] ?? null;
        $has_photo = $photo_file && $photo_file['error'] !== UPLOAD_ERR_NO_FILE;

        if ($errors['profile'] === '' && $has_photo) {
            if ($photo_file['error'] === UPLOAD_ERR_INI_SIZE || $photo_file['error'] === UPLOAD_ERR_FORM_SIZE
                || $photo_file['size'] > 2 * 1024 * 1024) {
                $errors['profile'] = $t['err_photo_size'];
            } elseif ($photo_file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($photo_file['tmp_name'])) {
                $errors['profile'] = $t['err_photo_save'];
            } else {
                // den ECHTEN Typ der Datei prüfen (nicht nur die Endung): 2 = JPG, 3 = PNG
                // getimagesize() liest den ECHTEN Inhalt des Bildes (Typ und Größe). So wissen wir, dass es wirklich ein Bild ist und nicht nur so heißt.
                $info = @getimagesize($photo_file['tmp_name']);
                $ext  = ($info && $info[2] === IMAGETYPE_JPEG) ? 'jpg' : (($info && $info[2] === IMAGETYPE_PNG) ? 'png' : '');

                if ($ext === '') {
                    $errors['profile'] = $t['err_photo_type'];
                } elseif (!is_dir($photo_dir) || !is_writable($photo_dir)) {
                    $errors['profile'] = $t['err_photo_save'];
                } else {
                    // neuer, zufälliger Name: der Name, den der Kunde gewählt hat, wird nie benutzt
                    $file_name = bin2hex(random_bytes(16)) . '.' . $ext;
                    // move_uploaded_file() verschiebt die hochgeladene Datei vom Temp-Ordner des Servers in den Zielordner (geht nur bei echten Uploads).
                    if (move_uploaded_file($photo_file['tmp_name'], $photo_dir . $file_name)) {
                        $new_photo = $photo_url . $file_name;
                    } else {
                        $errors['profile'] = $t['err_photo_save'];
                    }
                }
            }
        }

        // Foto entfernen (optional): Die Checkbox "remove_photo" wurde angehakt und es wurde kein neues Foto gewählt.
        // Dann wird der Pfad in der Datenbank leer (NULL) und die Seite zeigt wieder die Initialen.
        $old_photo    = $client['profile_photo_path'] ?? '';
        $remove_photo = ($new_photo === null && !empty($_POST['remove_photo']) && $old_photo !== '');
        // Welcher Wert kommt in die Datenbank? Neues Foto, oder NULL (entfernt), oder das alte Foto (unverändert).
        $photo_value  = $new_photo ?? ($remove_photo ? null : ($old_photo !== '' ? $old_photo : null));

        // alles in Ordnung: speichern
        if ($errors['profile'] === '') {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE clients
                     SET first_name = ?, last_name = ?, phone = ?, date_of_birth = ?,
                         country = ?, city = ?, preferred_language = ?, goal = ?,
                         profile_photo_path = ?
                     WHERE client_id = ?"
                );
                $stmt->execute([
                    mb_substr($v['first_name'], 0, 100), mb_substr($v['last_name'], 0, 100),
                    $v['phone'] !== '' ? mb_substr($v['phone'], 0, 100) : null,
                    $dob, $country,
                    $v['city'] !== '' ? mb_substr($v['city'], 0, 100) : null,
                    $pref,
                    $v['goal'] !== '' ? $v['goal'] : null,
                    $photo_value, $client_id
                ]);

                // das alte Foto löschen (nur, wenn es im Fotoordner lag)
                // (bei neuem Foto UND beim Entfernen wird die alte Datei gelöscht)
                if (($new_photo !== null || $remove_photo) && $old_photo !== '' && strpos($old_photo, $photo_url) === 0) {
                    $old_file = $photo_dir . basename($old_photo);
                    if (is_file($old_file)) {
                        // unlink() löscht eine Datei von der Festplatte.
                        @unlink($old_file);
                    }
                }

                // die gewählte Sprache gilt ab jetzt
                $_SESSION['lang'] = $lang_codes[$pref];

                header("Location: client_profile.php?done=profile");
                exit;
            } catch (PDOException $ex) {
                // wenn die Datenbank einen Fehler hatte, bleibt das neue Foto nicht lose im Ordner liegen
                if ($new_photo !== null && is_file($photo_dir . basename($new_photo))) {
                    @unlink($photo_dir . basename($new_photo));
                }
                $errors['profile'] = $t['err_other'];
            }
        } elseif ($new_photo !== null && is_file($photo_dir . basename($new_photo))) {
            @unlink($photo_dir . basename($new_photo));
        }

    } elseif ($form === 'password') {
        // ---------- 7B: DAS PASSWORT ÄNDERN ----------
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $errors['password'] = $t['err_pw_empty'];
        // password_verify() vergleicht das eingegebene Passwort mit dem Hash aus der Datenbank.
        // Das echte Passwort wird nie gespeichert und nie zurückgerechnet.
        } elseif (!password_verify($current, $me['password_hash'])) {
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

                // neue Session nach dem Ändern des Passworts (sicherer)
                // session_regenerate_id(true) gibt der Person eine NEUE Session-Nummer. Eine alte (gestohlene) Nummer funktioniert dann nicht mehr
                // (Schutz gegen Session Fixation).
                session_regenerate_id(true);
                header("Location: client_profile.php?done=password");
                exit;
            } catch (PDOException $ex) {
                $errors['password'] = $t['err_other'];
            }
        }

    } elseif ($form === 'measure') {
        // ---------- 7C: EINEN MESSWERT HINZUFÜGEN ----------
        $m_weight = trim($_POST['weight'] ?? '');
        $m_height = trim($_POST['height'] ?? '');
        $weight = null;
        $height = null;

        if ($m_weight === '' && $m_height === '') {
            $errors['measure'] = $t['err_meas_empty'];
        }
        if ($errors['measure'] === '' && $m_weight !== '') {
            $w = str_replace(',', '.', $m_weight);
            // Regel: größer als null und passt in das Feld der Datenbank (decimal(5,2) = bis 999.99)
            if (!is_numeric($w) || round((float)$w, 2) < 0.01 || (float)$w > 999.99) {
                $errors['measure'] = $t['err_weight'];
            } else {
                $weight = round((float)$w, 2);
            }
        }
        if ($errors['measure'] === '' && $m_height !== '') {
            $h = str_replace(',', '.', $m_height);
            if (!is_numeric($h) || round((float)$h, 2) < 0.01 || (float)$h > 999.99) {
                $errors['measure'] = $t['err_height'];
            } else {
                $height = round((float)$h, 2);
            }
        }

        if ($errors['measure'] === '') {
            try {
                // wenn nur ein Wert angegeben wurde, wiederholt der andere den letzten bekannten Wert
                // (so sind aktuelles Gewicht und aktuelle Größe im Verlauf nie leer)
                $stmt = $pdo->prepare(
                    "SELECT weight, height FROM client_measurements
                     WHERE clients_client_id = ?
                     ORDER BY measurement_date DESC, measurement_id DESC LIMIT 1"
                );
                $stmt->execute([$client_id]);
                $prev = $stmt->fetch();
                if ($weight === null && $prev) { $weight = $prev['weight']; }
                if ($height === null && $prev) { $height = $prev['height']; }

                $stmt = $pdo->prepare(
                    "INSERT INTO client_measurements (weight, height, measurement_date, clients_client_id)
                     VALUES (?, ?, CURDATE(), ?)"
                );
                $stmt->execute([$weight, $height, $client_id]);

                header("Location: client_profile.php?done=measure");
                exit;
            } catch (PDOException $ex) {
                $errors['measure'] = $t['err_other'];
            }
        }

    } elseif ($form === 'delete_measure') {
        // ---------- 7D: EINEN MESSWERT LÖSCHEN ----------
        // filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
        $measure_id = filter_var($_POST['measurement_id'] ?? '', FILTER_VALIDATE_INT);

        if ($measure_id === false || $measure_id < 1) {
            $errors['measure'] = $t['err_invalid'];
        } else {
            try {
                // das WHERE hat BEIDES: die Nummer des Messwerts und seinen Besitzer.
                // So löscht der Kunde nie den Messwert einer anderen Person, auch wenn er die Nummer im Formular ändert.
                $stmt = $pdo->prepare(
                    "DELETE FROM client_measurements WHERE measurement_id = ? AND clients_client_id = ?"
                );
                $stmt->execute([$measure_id, $client_id]);

                if ($stmt->rowCount() === 0) {
                    // nichts wurde gelöscht: der Messwert existiert nicht oder gehört nicht zu diesem Kunden
                    $errors['measure'] = $t['err_invalid'];
                } else {
                    header("Location: client_profile.php?done=delete");
                    exit;
                }
            } catch (PDOException $ex) {
                $errors['measure'] = $t['err_other'];
            }
        }

    } else {
        $errors['profile'] = $t['err_invalid'];
    }
}

// Erfolgsmeldung (kommt von der Weiterleitung nach dem Speichern)
$success = "";
$done_messages = ['profile' => 'saved_profile', 'password' => 'saved_password', 'measure' => 'saved_measure', 'delete' => 'saved_delete'];
if (isset($_GET['done']) && isset($done_messages[$_GET['done']])) {
    $success = $t[$done_messages[$_GET['done']]];
}

// BLOCK 8: den Kunden neu laden (nach dem Speichern zeigt die Seitenleiste den neuen Namen/das neue Foto)
$stmt = $pdo->prepare(
    "SELECT first_name, last_name, profile_photo_path, registration_date, monthly_fee, currency
     FROM clients WHERE client_id = ?"
);
$stmt->execute([$client_id]);
$fresh = $stmt->fetch();

$first      = $fresh['first_name'] ?? "";
$full_name  = trim($first . ' ' . ($fresh['last_name'] ?? ''));
$initials   = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($fresh['last_name'] ?? '', 0, 1));
$photo      = $fresh['profile_photo_path'] ?? "";

// BLOCK 9: Daten dieses Kunden für den Kopf und den Verlauf

// nächster Termin
$stmt = $pdo->prepare(
    "SELECT appointment_date, appointment_time FROM appointments
     WHERE clients_client_id = ? AND status IN ('scheduled', 'rescheduled') AND appointment_date >= CURDATE()
     ORDER BY appointment_date, appointment_time LIMIT 1"
);
$stmt->execute([$client_id]);
$next_appt = $stmt->fetch();

// neue Antworten der Ärztin (messages.status 1 = Answered)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE clients_client_id = ? AND status = 1");
$stmt->execute([$client_id]);
$count_replies = (int)$stmt->fetchColumn();

// nächste Fälligkeit (payments.status 0 = Open)
$stmt = $pdo->prepare(
    "SELECT due_date FROM payments WHERE clients_client_id = ? AND status = 0 ORDER BY due_date ASC LIMIT 1"
);
$stmt->execute([$client_id]);
$next_due = $stmt->fetchColumn();

// Verlauf der Messwerte: die 6 neuesten (vom neuesten zum ältesten)
$stmt = $pdo->prepare(
    "SELECT measurement_id, weight, height, measurement_date FROM client_measurements
     WHERE clients_client_id = ?
     ORDER BY measurement_date DESC, measurement_id DESC LIMIT 6"
);
$stmt->execute([$client_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// allererstes Gewicht (der "Start")
$stmt = $pdo->prepare(
    "SELECT weight FROM client_measurements
     WHERE clients_client_id = ? AND weight IS NOT NULL
     ORDER BY measurement_date ASC, measurement_id ASC LIMIT 1"
);
$stmt->execute([$client_id]);
$first_weight = $stmt->fetchColumn();

// BLOCK 10: fertige Werte für die Anzeige
$kpi_appt    = $next_appt ? short_date($next_appt['appointment_date']) . ', ' . fmt_time($next_appt['appointment_time']) : "-";
$kpi_payment = $next_due ? $t['due'] . ' ' . short_date($next_due) : "-";

// Gewichte und letzte Größe (Messwerte ohne Wert werden ignoriert)
$series = [];                       // vom ältesten zum neuesten, nur mit Gewicht
$last_height = null;
$last_date = null;
foreach (array_reverse($history) as $m) {
    if ($m['weight'] !== null) { $series[] = (float)$m['weight']; }
}
foreach ($history as $m) {          // vom neuesten zum ältesten: nimmt den ersten mit Wert
    if ($last_height === null && $m['height'] !== null) { $last_height = $m['height']; }
}
if (count($history) > 0) { $last_date = $history[0]['measurement_date']; }

$current_weight = count($series) > 0 ? end($series) : null;
$kpi_weight = $current_weight !== null ? one_decimal($current_weight) . ' kg' : "-";

// Gewichtsdiagramm: Punkte der Linie (nur mit 2 oder mehr Gewichten)
$n = count($series);
$points = "";
$dots   = [];
if ($n >= 2) {
    $w_min = min($series);
    $w_max = max($series);
    $range = $w_max - $w_min;
    foreach ($series as $i => $w) {
        $x = 12 + $i * (416 / ($n - 1));
        $y = ($range == 0) ? 32 : 10 + (($w_max - $w) / $range) * 44;
        $points .= number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '') . ' ';
        $dots[] = [number_format($x, 1, '.', ''), number_format($y, 1, '.', '')];
    }
}

// Optionen der Länderliste (behält ein altes Land, das nicht in der Liste steht)
$country_options = [];
foreach ($countries as $key => $labels) {
    $country_options[$key] = $labels[$lang] ?? $key;
}
if ($v['country'] !== '' && !isset($country_options[$v['country']])) {
    $country_options[$v['country']] = $v['country'];
}

// Seitenmenü: [Datei, Symbol, Text]
$current = "client_profile.php";
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
        /* "Entfernen"-Knopf: roter Rand; ist "on", wenn er angeklickt wurde */
        .rm { font-size: 12px; padding: 5px 11px; border-radius: 8px; cursor: pointer; border: 1px solid #D9A3A3; color: #791F1F; background: #fff; display: inline-flex; align-items: center; gap: 6px; margin: 0; }
        /* beide Knöpfe (Foto ändern / Entfernen) stehen nebeneinander, gleich hoch, auf einer Linie */
        .photo-btns { display: flex; align-items: center; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
        .photo-btns .btn, .photo-btns .rm { height: 30px; box-sizing: border-box; margin: 0; line-height: 1; }
        .rm input { display: none; }
        .rm .ico { width: 15px; height: 15px; }
        .rm.on { background: #F9D6D6; }

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

        @media (max-width: 900px) {
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
        <div class="kpi hl"><?= icon('scale') ?><div><b><?= e($kpi_weight) ?></b><span><?= e($t['kpi_weight']) ?></span></div></div>
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
                <!-- der Knopf steht hier oben, sendet aber das Profilformular (form="profile_form") -->
                <button type="submit" form="profile_form" class="btn fill"><?= icon('check', 'w') ?><?= e($t['save']) ?></button>
            </div>

            <!-- FORMULAR 1: persönliche Daten + Foto (enctype, damit die Datei gesendet werden kann) -->
            <form id="profile_form" method="post" action="client_profile.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="form" value="profile">

                <div class="card">
                    <div class="ct"><?= icon('user') ?><?= e($t['sec_personal']) ?></div>

                    <?php if ($errors['profile'] !== ""): ?>
                        <div class="error"><?= e($errors['profile']) ?></div>
                    <?php endif; ?>

                    <div class="photo-row">
                        <div class="avatar" id="avatar-preview">
                            <?php if ($photo !== ""): ?>
                                <img src="<?= e($photo) ?>" alt="<?= e($full_name) ?>">
                            <?php else: ?>
                                <?= e($initials) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="t1"><?= e($t['photo']) ?></div>
                            <div class="photo-btns">
                            <label class="btn sm pick" for="photo"><?= icon('upload-arrow') ?><?= e($t['change_photo']) ?></label>
                            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png">
                            <?php if ($photo !== ""): ?>
                                <!-- "Entfernen"-Knopf mit Papierkorb-Symbol (wie in den Einstellungen der Beraterin).
                                     Das Kästchen darin ist versteckt: ein Klick auf den Knopf hakt es an. Beim Speichern wird das Foto
                                     entfernt und die Initialen kommen zurück. -->
                                <label class="rm" id="rm-photo"><input type="checkbox" name="remove_photo" value="1"><?= icon('trash') ?><?= e($t['remove']) ?></label>
                            <?php endif; ?>
                            </div>
                            <div class="hint" id="photo-name"><?= e($t['photo_hint']) ?></div>
                        </div>
                    </div>

                    <div class="r2">
                        <div>
                            <label class="fl" for="first_name"><?= e($t['first_name']) ?></label>
                            <div class="field"><input type="text" id="first_name" name="first_name" maxlength="100" value="<?= e($v['first_name']) ?>" required></div>
                        </div>
                        <div>
                            <label class="fl" for="last_name"><?= e($t['last_name']) ?></label>
                            <div class="field"><input type="text" id="last_name" name="last_name" maxlength="100" value="<?= e($v['last_name']) ?>" required></div>
                        </div>
                    </div>
                    <div class="r2">
                        <div>
                            <label class="fl" for="phone"><?= e($t['phone']) ?></label>
                            <div class="field has-icon">
                                <img class="icon" src="icons/phone.svg" alt="">
                                <input type="tel" id="phone" name="phone" maxlength="100" placeholder="+00 000 000 000" value="<?= e($v['phone']) ?>">
                            </div>
                        </div>
                        <div>
                            <label class="fl" for="dob"><?= e($t['dob']) ?></label>
                            <div class="field has-icon">
                                <img class="icon" src="icons/calendar.svg" alt="">
                                <input type="date" id="dob" name="dob" max="<?= date('Y-m-d') ?>" value="<?= e($v['dob']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="r2">
                        <div>
                            <label class="fl" for="country"><?= e($t['country']) ?></label>
                            <div class="field">
                                <select id="country" name="country">
                                    <option value=""><?= e($t['country_ph']) ?></option>
                                    <?php foreach ($country_options as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $v['country'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="fl" for="city"><?= e($t['city']) ?></label>
                            <div class="field"><input type="text" id="city" name="city" maxlength="100" value="<?= e($v['city']) ?>"></div>
                        </div>
                    </div>
                    <div class="r2" style="margin-bottom:0">
                        <div>
                            <label class="fl" for="preferred_language"><?= e($t['pref_lang']) ?></label>
                            <div class="field">
                                <select id="preferred_language" name="preferred_language">
                                    <option value="0" <?= $v['preferred_language'] === '0' ? 'selected' : '' ?>>English</option>
                                    <option value="1" <?= $v['preferred_language'] === '1' ? 'selected' : '' ?>>Deutsch</option>
                                    <option value="2" <?= $v['preferred_language'] === '2' ? 'selected' : '' ?>>Português</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="fl" for="goal"><?= e($t['goal']) ?></label>
                            <div class="field"><input type="text" id="goal" name="goal" maxlength="255" value="<?= e($v['goal']) ?>"></div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="g2">
                <!-- FORMULAR 2: Konto (E-Mail nur lesbar + Passwort ändern) -->
                <div class="card">
                    <div class="ct"><?= icon('lock') ?><?= e($t['sec_account']) ?></div>

                    <label class="fl" for="email"><?= e($t['email']) ?></label>
                    <div class="field has-icon">
                        <img class="icon" src="icons/mail.svg" alt="">
                        <input type="email" id="email" value="<?= e($me['email']) ?>" readonly>
                    </div>
                    <div class="hint" style="margin-bottom:12px"><?= e($t['email_note']) ?></div>

                    <div class="ct" style="margin-top:6px"><?= e($t['pw_title']) ?></div>

                    <?php if ($errors['password'] !== ""): ?>
                        <div class="error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>

                    <form method="post" action="client_profile.php">
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

                <!-- Tarif/Plan: nur lesbar (von der Ärztin festgelegt) -->
                <div class="card" style="align-self:start">
                    <div class="ct"><?= icon('coin') ?><?= e($t['sec_plan']) ?></div>
                    <div class="kv"><span><?= e($t['nutritionist']) ?></span><span><?= e($short) ?></span></div>
                    <div class="kv"><span><?= e($t['fee']) ?></span><span><?= e(money($fresh['monthly_fee'], $fresh['currency'])) ?></span></div>
                    <div class="kv"><span><?= e($t['currency']) ?></span><span><?= e($fresh['currency'] ?: '-') ?></span></div>
                    <div class="kv"><span><?= e($t['since']) ?></span><span><?= $fresh['registration_date'] ? e(long_date($fresh['registration_date'])) : '-' ?></span></div>
                    <div class="note"><?= e(sprintf($t['plan_note'], $short)) ?></div>
                </div>
            </div>

            <!-- FORMULAR 3: Verlauf von Gewicht und Größe + neuer Messwert -->
            <div class="card" style="margin-top:14px">
                <div class="ct"><?= icon('chart') ?><?= e($t['sec_hist']) ?></div>

                <?php if (count($history) === 0): ?>
                    <div class="empty"><?= e($t['hist_empty']) ?></div>

                <?php else: ?>
                    <?php if ($n >= 2): ?>
                        <svg viewBox="0 0 440 64" width="100%" height="64" role="img" aria-label="<?= e($t['sec_hist']) ?>">
                            <line x1="0" y1="60" x2="440" y2="60" stroke="#E5E8E5" stroke-width="1"/>
                            <line x1="0" y1="32" x2="440" y2="32" stroke="#E5E8E5" stroke-width="1"/>
                            <polyline points="<?= e(trim($points)) ?>" fill="none" stroke="<?= e($primary) ?>" stroke-width="2"/>
                            <?php foreach ($dots as $i => $d): ?>
                                <circle cx="<?= $d[0] ?>" cy="<?= $d[1] ?>" r="<?= $i === count($dots) - 1 ? 4.5 : 3.5 ?>" fill="<?= $i === count($dots) - 1 ? e($accent) : e($primary) ?>"/>
                            <?php endforeach; ?>
                        </svg>
                    <?php elseif ($n === 1): ?>
                        <div class="big"><?= e(one_decimal($series[0])) ?> kg</div>
                    <?php endif; ?>

                    <div class="chart-sum">
                        <span>
                            <?php if ($first_weight !== false && $current_weight !== null): ?>
                                <?= e(sprintf($t['start_now'], one_decimal($first_weight) . ' kg', one_decimal($current_weight) . ' kg')) ?>
                            <?php endif; ?>
                        </span>
                        <span>
                            <?php if ($last_height !== null): ?>
                                <?= e(sprintf($t['height_line'], rtrim(rtrim(number_format((float)$last_height, 2, '.', ''), '0'), '.') . ' cm')) ?> ·
                            <?php endif; ?>
                            <?= e(sprintf($t['last_update'], $last_date ? short_date($last_date) : '-')) ?>
                        </span>
                    </div>

                    <table class="hist">
                        <tr><th><?= e($t['hist_date']) ?></th><th><?= e($t['weight']) ?></th><th><?= e($t['height']) ?></th><th class="del-col"></th></tr>
                        <?php foreach ($history as $m): ?>
                            <tr>
                                <td><?= e(short_date($m['measurement_date'])) ?></td>
                                <td><?= $m['weight'] !== null ? e(one_decimal($m['weight'])) : '-' ?></td>
                                <td><?= $m['height'] !== null ? e(rtrim(rtrim(number_format((float)$m['height'], 2, '.', ''), '0'), '.')) : '-' ?></td>
                                <td class="del-col">
                                    <!-- Papierkorb-Knopf: sendet die Nummer des Messwerts; vorher kommt die Frage, ob man sicher ist -->
                                    <form method="post" action="client_profile.php" onsubmit="return confirm(<?= e(json_encode($t['confirm_delete'])) ?>);">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="form" value="delete_measure">
                                        <input type="hidden" name="measurement_id" value="<?= (int)$m['measurement_id'] ?>">
                                        <button type="submit" class="del-btn" title="<?= e($t['delete_label']) ?>" aria-label="<?= e($t['delete_label']) ?>"><img src="icons/trash.svg" alt=""></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>

                <div class="add-box">
                    <div class="ct" style="margin-bottom:8px"><?= e($t['add_title']) ?></div>

                    <?php if ($errors['measure'] !== ""): ?>
                        <div class="error"><?= e($errors['measure']) ?></div>
                    <?php endif; ?>

                    <form method="post" action="client_profile.php">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form" value="measure">
                        <div class="r2" style="margin-bottom:10px">
                            <div>
                                <label class="fl" for="weight"><?= e($t['weight']) ?></label>
                                <div class="field has-icon">
                                    <img class="icon" src="icons/scale.svg" alt="">
                                    <input type="text" inputmode="decimal" id="weight" name="weight" placeholder="64.2" value="<?= e($m_weight) ?>">
                                </div>
                            </div>
                            <div>
                                <label class="fl" for="height"><?= e($t['height']) ?></label>
                                <div class="field has-icon">
                                    <img class="icon" src="icons/ruler.svg" alt="">
                                    <input type="text" inputmode="decimal" id="height" name="height" placeholder="168" value="<?= e($m_height) ?>">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn"><?= icon('chart') ?><?= e($t['btn_add']) ?></button>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    // merkt sich den Kreis, wie er beim Laden der Seite aussieht (Foto oder Initialen)
    var avatarOriginal = document.getElementById("avatar-preview").innerHTML;
    // Zeigt den Namen des gewählten Fotos
    document.getElementById("photo").addEventListener("change", function () {
        var label = document.getElementById("photo-name");
        if (this.files.length > 0) {
            label.textContent = <?= json_encode($t['photo_chosen']) ?>.replace('%s', this.files[0].name);
            // Vorschau: das gewählte Foto erscheint sofort im Kreis (gespeichert wird erst mit "Speichern")
            var av = document.getElementById("avatar-preview");
            av.innerHTML = '';
            var im = document.createElement('img');
            im.src = URL.createObjectURL(this.files[0]);
            av.appendChild(im);
            // wurde ein neues Foto gewählt, ist "Entfernen" nicht mehr aktiv
            var rmBox = document.querySelector('input[name="remove_photo"]');
            if (rmBox) { rmBox.checked = false; rmBox.parentNode.classList.remove('on'); }
        } else {
            label.textContent = <?= json_encode($t['photo_hint']) ?>;
            document.getElementById("avatar-preview").innerHTML = avatarOriginal;
        }
    });

    // "Entfernen"-Knopf: färbt sich rosa, zeigt den Hinweis und leert die Dateiauswahl
    var rmCheckbox = document.querySelector('input[name="remove_photo"]');
    if (rmCheckbox) {
        rmCheckbox.addEventListener("change", function () {
            rmCheckbox.parentNode.classList.toggle('on', rmCheckbox.checked);
            var label = document.getElementById("photo-name");
            var av = document.getElementById("avatar-preview");
            if (rmCheckbox.checked) {
                document.getElementById("photo").value = '';
                label.textContent = <?= json_encode($t['removing']) ?>;
                // Vorschau: sofort die Initialen zeigen (gespeichert wird erst mit "Speichern")
                av.textContent = <?= json_encode($initials) ?>;
            } else {
                label.textContent = <?= json_encode($t['photo_hint']) ?>;
                av.innerHTML = avatarOriginal;
            }
        });
    }

    // Auge: zeigt oder versteckt jedes Passwortfeld
    document.querySelectorAll(".toggle").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var field = document.getElementById(btn.getAttribute("data-target"));
            var icon  = btn.querySelector("img");
            if (field.type === "password") {
                field.type = "text";
                icon.src = "icons/eye-off.svg";
            } else {
                field.type = "password";
                icon.src = "icons/eye.svg";
            }
        });
    });
</script>
</body>
</html>
