<?php
// register.php
// Öffentliche Registrierungsseite für Kunden + die Logik, die die Registrierung speichert.
// Nach der Registrierung ist die Person schon eingeloggt und geht zu client_status.php (Pending).

// BLOCK 1: Session starten und mit der Datenbank verbinden
// session_start() startet die Session (Sitzung; sessão). Der Server erkennt den Besucher an einer Nummer im Cookie wieder.
// Alles, was in $_SESSION steht (z. B. user_id), bleibt zwischen den Seiten gespeichert.
session_start();
require 'config.php';

// BLOCK 2: kleine Funktion, die Texte schützt, bevor sie auf dem Bildschirm erscheinen (gegen XSS)
function e($text) {
    // htmlspecialchars() macht aus Zeichen wie < und > harmlosen Text. So kann niemand Schadcode (JavaScript) in die Seite einschleusen (XSS-Schutz).
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// BLOCK 3: Sprache der Seite
// kommt aus dem Link (?lang=pt), wird in der Session gespeichert, Standard ist EN
$languages = ['en', 'de', 'pt'];
$lang = $_SESSION['lang'] ?? 'en';

if (isset($_GET['lang']) && in_array($_GET['lang'], $languages)) {
    $lang = $_GET['lang'];
}
$_SESSION['lang'] = $lang;

// Nummer der Sprache, die in clients.preferred_language gespeichert wird
$lang_numbers = ['en' => 0, 'de' => 1, 'pt' => 2];

// BLOCK 4: wer schon eingeloggt ist, muss sich nicht noch einmal registrieren
// (login.php weiß, wohin es jede Person schickt)
if (isset($_SESSION['user_id'])) {
    // header("Location: ...") schickt den Browser auf eine andere Seite (Redirect). Das exit danach stoppt das Skript, damit nichts mehr ausgeführt wird.
    header("Location: login.php?lang=" . $lang);
    exit;
}

// BLOCK 5: alle Texte der Seite, ein Satz für jede Sprache
$texts = [
    'en' => [
        'title'         => 'Become a client',
        'role'          => 'Nutritionist',
        'login'         => 'Log in',
        'subtitle'      => '%s will review your registration before you get full access.',
        'subtitle_lead' => 'Fill in your details.',
        'sec1'          => 'Personal data',
        'sec2'          => 'Initial follow-up data',
        'sec3'          => 'Your account',
        'sec4'          => 'Latest blood test',
        'first_name'    => 'First name',
        'last_name'     => 'Last name',
        'phone'         => 'Phone',
        'dob'           => 'Date of birth',
        'country'       => 'Country',
        'country_ph'    => 'Select your country',
        'city'          => 'City',
        'pref_lang'     => 'Preferred language',
        'weight'        => 'Weight (kg)',
        'height'        => 'Height (cm)',
        'goal'          => 'Your goal',
        'goal_ph'       => 'Describe what you want to achieve',
        'email'         => 'Email',
        'email_ph'      => 'name@email.com',
        'password'      => 'Password',
        'password_ph'   => 'At least 8 characters',
        'toggle_label'  => 'Show or hide password',
        'blood_title'   => 'Upload your blood test (PDF)',
        'blood_hint'    => 'Required. PDF, maximum 10 MB. It must be from the last 12 months.',
        'choose_file'   => 'Choose file',
        'no_file'       => 'No file chosen',
        'privacy'       => 'I have read the privacy policy and agree to the processing of my personal data for the purpose of nutrition counseling.',
        'submit'        => 'Submit registration',
        'has_account'   => 'Already a client?',
        'rights'        => 'All rights reserved.',
        'err_invalid'   => 'Invalid request. Please reload the page and try again.',
        'err_required'  => 'Please fill in your first name, last name, email and password.',
        'err_email'     => 'Please enter a valid email address.',
        'err_email_used'=> 'This email is already registered. Please log in instead.',
        'err_password'  => 'The password must have at least 8 characters.',
        'err_dob'       => 'Please enter a valid date of birth.',
        'err_weight'    => 'Please enter a valid weight (in kg).',
        'err_height'    => 'Please enter a valid height (in cm).',
        'err_goal'      => 'Your goal is too long (maximum 255 characters).',
        'err_other'     => 'Something went wrong. Please try again later.',
        'err_noprofile' => 'Registration is not available right now.',
        'err_blood'     => 'Please upload your blood test as a PDF file.',
        'err_blood_big' => 'The blood test file is too large. The maximum is 10 MB.',
        'err_blood_pdf' => 'The blood test must be a PDF file.',
        'err_privacy'    => 'Please confirm that you have read the privacy policy.',
    ],
    'pt' => [
        'title'         => 'Torne-se cliente',
        'role'          => 'Nutricionista',
        'login'         => 'Entrar',
        'subtitle'      => '%s vai analisar o seu cadastro antes de liberar o acesso completo.',
        'subtitle_lead' => 'Preencha seus dados.',
        'sec1'          => 'Dados pessoais',
        'sec2'          => 'Dados iniciais de acompanhamento',
        'sec3'          => 'Sua conta',
        'sec4'          => 'Último exame de sangue',
        'first_name'    => 'Nome',
        'last_name'     => 'Sobrenome',
        'phone'         => 'Telefone',
        'dob'           => 'Data de nascimento',
        'country'       => 'País',
        'country_ph'    => 'Selecione seu país',
        'city'          => 'Cidade',
        'pref_lang'     => 'Idioma preferido',
        'weight'        => 'Peso (kg)',
        'height'        => 'Altura (cm)',
        'goal'          => 'Seu objetivo',
        'goal_ph'       => 'Descreva o que você quer alcançar',
        'email'         => 'E-mail',
        'email_ph'      => 'nome@email.com',
        'password'      => 'Senha',
        'password_ph'   => 'Pelo menos 8 caracteres',
        'toggle_label'  => 'Mostrar ou esconder a senha',
        'blood_title'   => 'Envie seu exame de sangue (PDF)',
        'blood_hint'    => 'Obrigatório. PDF, máximo 10 MB. Deve ser dos últimos 12 meses.',
        'choose_file'   => 'Escolher arquivo',
        'no_file'       => 'Nenhum arquivo escolhido',
        'privacy'       => 'Li a política de privacidade e concordo com o tratamento dos meus dados pessoais para fins de acompanhamento nutricional.',
        'submit'        => 'Enviar cadastro',
        'has_account'   => 'Já é cliente?',
        'rights'        => 'Todos os direitos reservados.',
        'err_invalid'   => 'Solicitação inválida. Recarregue a página e tente novamente.',
        'err_required'  => 'Preencha nome, sobrenome, e-mail e senha.',
        'err_email'     => 'Informe um e-mail válido.',
        'err_email_used'=> 'Este e-mail já está cadastrado. Entre na sua conta.',
        'err_password'  => 'A senha deve ter pelo menos 8 caracteres.',
        'err_dob'       => 'Informe uma data de nascimento válida.',
        'err_weight'    => 'Informe um peso válido (em kg).',
        'err_height'    => 'Informe uma altura válida (em cm).',
        'err_goal'      => 'O objetivo está muito longo (máximo de 255 caracteres).',
        'err_other'     => 'Algo deu errado. Tente novamente mais tarde.',
        'err_noprofile' => 'O cadastro não está disponível no momento.',
        'err_blood'     => 'Envie seu exame de sangue em PDF.',
        'err_blood_big' => 'O arquivo do exame é grande demais. O máximo é 10 MB.',
        'err_blood_pdf' => 'O exame de sangue precisa ser um arquivo PDF.',
        'err_privacy'    => 'Confirme que você leu a política de privacidade.',
    ],
    'de' => [
        'title'         => 'Kunde werden',
        'role'          => 'Ernährungsberaterin',
        'login'         => 'Anmelden',
        'subtitle'      => '%s prüft Ihre Registrierung, bevor Sie vollen Zugang erhalten.',
        'subtitle_lead' => 'Bitte füllen Sie Ihre Daten aus.',
        'sec1'          => 'Persönliche Daten',
        'sec2'          => 'Erste Verlaufsdaten',
        'sec3'          => 'Ihr Konto',
        'sec4'          => 'Letzter Bluttest',
        'first_name'    => 'Vorname',
        'last_name'     => 'Nachname',
        'phone'         => 'Telefon',
        'dob'           => 'Geburtsdatum',
        'country'       => 'Land',
        'country_ph'    => 'Land auswählen',
        'city'          => 'Stadt',
        'pref_lang'     => 'Bevorzugte Sprache',
        'weight'        => 'Gewicht (kg)',
        'height'        => 'Größe (cm)',
        'goal'          => 'Ihr Ziel',
        'goal_ph'       => 'Beschreiben Sie, was Sie erreichen möchten',
        'email'         => 'E-Mail',
        'email_ph'      => 'name@email.com',
        'password'      => 'Passwort',
        'password_ph'   => 'Mindestens 8 Zeichen',
        'toggle_label'  => 'Passwort anzeigen oder verbergen',
        'blood_title'   => 'Bluttest hochladen (PDF)',
        'blood_hint'    => 'Pflichtfeld. PDF, maximal 10 MB. Er darf nicht älter als 12 Monate sein.',
        'choose_file'   => 'Datei auswählen',
        'no_file'       => 'Keine Datei ausgewählt',
        'privacy'       => 'Ich habe die Datenschutzerklärung gelesen und bin mit der Verarbeitung meiner personenbezogenen Daten zum Zweck der Ernährungsberatung einverstanden.',
        'submit'        => 'Registrierung absenden',
        'has_account'   => 'Bereits Kunde?',
        'rights'        => 'Alle Rechte vorbehalten.',
        'err_invalid'   => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_required'  => 'Bitte geben Sie Vorname, Nachname, E-Mail und Passwort ein.',
        'err_email'     => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        'err_email_used'=> 'Diese E-Mail-Adresse ist bereits registriert. Bitte melden Sie sich an.',
        'err_password'  => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
        'err_dob'       => 'Bitte geben Sie ein gültiges Geburtsdatum ein.',
        'err_weight'    => 'Bitte geben Sie ein gültiges Gewicht (in kg) ein.',
        'err_height'    => 'Bitte geben Sie eine gültige Größe (in cm) ein.',
        'err_goal'      => 'Ihr Ziel ist zu lang (maximal 255 Zeichen).',
        'err_other'     => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es später erneut.',
        'err_noprofile' => 'Die Registrierung ist derzeit nicht verfügbar.',
        'err_blood'     => 'Bitte laden Sie Ihren Bluttest als PDF hoch.',
        'err_blood_big' => 'Die Bluttest-Datei ist zu groß. Das Maximum sind 10 MB.',
        'err_blood_pdf' => 'Der Bluttest muss eine PDF-Datei sein.',
        'err_privacy'    => 'Bitte bestätigen Sie, dass Sie die Datenschutzerklärung gelesen haben.',
    ],
];
$t = $texts[$lang];

// Liste der Länder: der Schlüssel (auf Englisch) geht in die Datenbank;
// die Namen auf DE und PT erscheinen nur auf dem Bildschirm
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

// BLOCK 6: Sicherheits-Token des Formulars (CSRF)
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// BLOCK 7: die Daten der Ärztin holen (Name, Foto, Farben und ihre id)
$stmt = $pdo->query(
    "SELECT nutritionist_id, professional_name, profile_photo_path,
            primary_color, secundary_color, background_color, accent_color
     FROM nutritionist_profile LIMIT 1"
);
$profile = $stmt->fetch();

$nutritionist_id = $profile['nutritionist_id'] ?? null;
$name       = $profile['professional_name'] ?? "NutriTrackWeb";
$photo      = $profile['profile_photo_path'] ?? "";
$primary    = $profile['primary_color']      ?? "#2E7D32";
$secondary  = $profile['secundary_color']    ?? "#A5D6A7";
$background = $profile['background_color']   ?? "#F5F7F5";
$accent     = $profile['accent_color']       ?? "#FF9800";

// Initialen für den Kreis, wenn es kein Foto gibt ("Dra. Sofia Martins" wird zu "SM")
$initials = "";
foreach (explode(" ", trim($name)) as $word) {
    if ($word === "" || strpos($word, ".") !== false) {
        continue;
    }
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}
$initials = mb_substr($initials, 0, 2);

// BLOCK 8: Werte des Formulars (am Anfang leer; bei einem Fehler kommen sie ausgefüllt zurück)
$v = [
    'first_name' => '', 'last_name' => '', 'phone' => '', 'dob' => '',
    'country' => '', 'city' => '', 'preferred_language' => (string)$lang_numbers[$lang],
    'weight' => '', 'height' => '', 'goal' => '', 'email' => '',
];
$error = "";

// BLOCK 9: Was passiert, wenn das Formular abgeschickt wird
// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // jedes Feld des Formulars holen (das Passwort kommt nie ausgefüllt zurück)
    foreach ($v as $key => $default) {
        $v[$key] = trim($_POST[$key] ?? $default);
    }
    $password = $_POST['password'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    // 9a: das Passwortfeld und die E-Mail müssen existieren; sie werden eins nach dem anderen geprüft
    $weight = null;
    $height = null;
    $dob    = null;

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // die Datei war größer als post_max_size von PHP: dann verwirft PHP das ganze Formular
        $error = $t['err_blood_big'];

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    } elseif (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        $error = $t['err_invalid'];

    } elseif ($nutritionist_id === null) {
        $error = $t['err_noprofile'];

    } elseif ($v['first_name'] === '' || $v['last_name'] === '' || $v['email'] === '' || $password === '') {
        $error = $t['err_required'];

    // Server-Prüfung der Datenschutzerklärung: der Browser prüft das Häkchen schon (required),
    // aber ein Browser kann umgangen werden. Deshalb wird es hier im PHP nochmal geprüft (isset = ist gesetzt?).
    } elseif (!isset($_POST['privacy'])) {
        $error = $t['err_privacy'];

    // filter_var() prüft ein Format (z. B. ob es eine gültige E-Mail, URL oder ganze Zahl ist).
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 255) {
        $error = $t['err_email'];

    } elseif (mb_strlen($password) < 8) {
        $error = $t['err_password'];

    } elseif (mb_strlen($v['goal']) > 255) {
        $error = $t['err_goal'];
    }

    // 9b: Geburtsdatum (optional, aber wenn angegeben, muss es ein echtes Datum in der Vergangenheit sein)
    if ($error === "" && $v['dob'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $v['dob']);
        if (!$d || $d->format('Y-m-d') !== $v['dob']
            || $d > new DateTime('today') || (int)$d->format('Y') < 1900) {
            $error = $t['err_dob'];
        } else {
            $dob = $v['dob'];
        }
    }

    // 9c: Gewicht und Größe (optional; Komma oder Punkt sind erlaubt)
    if ($error === "" && $v['weight'] !== '') {
        $w = str_replace(',', '.', $v['weight']);
        if (!is_numeric($w) || $w < 20 || $w > 500) {
            $error = $t['err_weight'];
        } else {
            $weight = round((float)$w, 2);
        }
    }
    if ($error === "" && $v['height'] !== '') {
        $h = str_replace(',', '.', $v['height']);
        if (!is_numeric($h) || $h < 50 || $h > 260) {
            $error = $t['err_height'];
        } else {
            $height = round((float)$h, 2);
        }
    }

    // 9d: das Land muss eins aus der Liste sein; die Sprache muss 0, 1 oder 2 sein
    $country = array_key_exists($v['country'], $countries) ? $v['country'] : null;
    $pref_lang = in_array($v['preferred_language'], ['0', '1', '2'], true)
               ? (int)$v['preferred_language'] : $lang_numbers[$lang];

    // 9e: gibt es die E-Mail schon?
    if ($error === "") {
        // prepare() + execute() = Prepared Statement (vorbereitete Abfrage). Die ? sind Platzhalter; die Werte werden getrennt gesendet.
        // So kann niemand SQL-Code einschleusen (Schutz gegen SQL-Injection).
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$v['email']]);
        if ($stmt->fetch()) {
            $error = $t['err_email_used'];
        }
    }

    // 9e2: der Bluttest ist PFLICHT (auch hier auf dem Server geprüft):
    //     nur ein echtes PDF (Endung .pdf und erste Bytes "%PDF-"), bis 10 MB
    $max_bytes    = 10 * 1024 * 1024;
    $blood_tmp    = "";
    $blood_name   = "";
    if ($error === "") {
        $f   = $_FILES['blood_test'] ?? null;
        $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;

        if (!$f || $err === UPLOAD_ERR_NO_FILE) {
            $error = $t['err_blood'];
        } elseif ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || $f['size'] > $max_bytes) {
            $error = $t['err_blood_big'];
        } elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $error = $t['err_other'];
        } else {
            $blood_name = basename(str_replace('\\', '/', (string)$f['name']));
            $blood_name = preg_replace('/[\x00-\x1F\x7F]+/', '', $blood_name);
            $fh    = fopen($f['tmp_name'], 'rb');
            $magic = $fh ? fread($fh, 5) : '';
            if ($fh) { fclose($fh); }

            if (strtolower(pathinfo($blood_name, PATHINFO_EXTENSION)) !== 'pdf' || $magic !== '%PDF-') {
                $error = $t['err_blood_pdf'];
            } else {
                if (mb_strlen($blood_name) > 200) {
                    $blood_name = mb_substr($blood_name, 0, 196) . '.pdf';
                }
                $blood_tmp = $f['tmp_name'];
            }
        }
    }

    // 9f: alles in Ordnung: Konto, Registrierung, Messwerte und Bluttest werden zusammen gespeichert (eine Transaktion: alles oder nichts)
    if ($error === "") {
        $blood_target = "";      // Pfad der Datei auf der Festplatte (um sie zu löschen, falls etwas schiefgeht)
        try {
            // beginTransaction(): ab hier gehört alles zusammen (Transaktion). Entweder wird ALLES gespeichert (commit)
            // oder bei einem Fehler NICHTS (rollBack macht alles seit hier rückgängig).
            $pdo->beginTransaction();

            // das Konto: role 0 = Kunde, status 1 = Active
            $stmt = $pdo->prepare(
                "INSERT INTO users (email, password_hash, role, status, created_at)
                 VALUES (?, ?, 0, 1, NOW())"
            );
            // password_hash() macht aus dem Passwort einen Hash (eine unlesbare Zeichenkette). Nur dieser Hash wird gespeichert (Spalte password_hash).
            $stmt->execute([$v['email'], password_hash($password, PASSWORD_DEFAULT)]);
            $user_id = $pdo->lastInsertId();

            // die Registrierung des Kunden: approval_status 0 = Pending
            $stmt = $pdo->prepare(
                "INSERT INTO clients
                    (first_name, last_name, phone, country, city, date_of_birth,
                     preferred_language, goal, approval_status, registration_date,
                     users_user_id, nutritionist_profile_nutritionist_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?, ?)"
            );
            $stmt->execute([
                $v['first_name'], $v['last_name'],
                $v['phone'] !== '' ? mb_substr($v['phone'], 0, 100) : null,
                $country,
                $v['city'] !== '' ? mb_substr($v['city'], 0, 100) : null,
                $dob, $pref_lang,
                $v['goal'] !== '' ? $v['goal'] : null,
                $user_id, $nutritionist_id
            ]);
            $client_id = $pdo->lastInsertId();

            // der erste Messwert (nur, wenn mindestens Gewicht oder Größe angegeben wurde)
            if ($weight !== null || $height !== null) {
                $stmt = $pdo->prepare(
                    "INSERT INTO client_measurements (weight, height, measurement_date, clients_client_id)
                     VALUES (?, ?, CURDATE(), ?)"
                );
                $stmt->execute([$weight, $height, $client_id]);
            }

            // der Bluttest: kommt in den geschützten Ordner mit ZUFÄLLIGEM Namen
            // und wird in der Tabelle documents eingetragen (document_type 1 = Blood Test)
            $upload_dir = __DIR__ . '/uploads/documents';
            $stored     = bin2hex(random_bytes(16)) . '.pdf';
            $blood_target = $upload_dir . '/' . $stored;

            // move_uploaded_file() verschiebt die hochgeladene Datei vom Temp-Ordner des Servers in den Zielordner (geht nur bei echten Uploads).
            if (!is_dir($upload_dir) || !move_uploaded_file($blood_tmp, $blood_target)) {
                $blood_target = "";
                throw new RuntimeException('blood test could not be saved');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO documents (document_type, file_name, file_path, uploaded_at, clients_client_id)
                 VALUES (1, ?, ?, NOW(), ?)"
            );
            $stmt->execute([$blood_name, 'uploads/documents/' . $stored, $client_id]);

            $pdo->commit();

            // Registrierung fertig: die Person ist schon eingeloggt und geht zur Statusseite (Pending)
            // session_regenerate_id(true) gibt der Person eine NEUE Session-Nummer. Eine alte (gestohlene) Nummer funktioniert dann nicht mehr
            // (Schutz gegen Session Fixation).
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user_id;
            $_SESSION['role']          = 0;
            $_SESSION['last_activity'] = time();
            $_SESSION['lang']          = $lang;
            unset($_SESSION['csrf_token']);

            header("Location: client_status.php");
            exit;

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // das PDF nicht "lose" auf der Festplatte lassen, wenn die Registrierung nicht gespeichert wurde
            if ($blood_target !== "" && is_file($blood_target)) {
                // unlink() löscht eine Datei von der Festplatte.
                @unlink($blood_target);
            }
            // 23000 = doppelter Wert (jemand hat im selben Moment dieselbe E-Mail registriert)
            $error = ($ex->getCode() == '23000') ? $t['err_email_used'] : $t['err_other'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($t['title']) ?> - <?= e($name) ?></title>

    <style>
        /* Die 4 Farben kommen aus der Datenbank und werden zu CSS-Variablen */
        :root {
            --primary:    <?= e($primary) ?>;
            --secondary:  <?= e($secondary) ?>;
            --background: <?= e($background) ?>;
            --accent:     <?= e($accent) ?>;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #2B2A24;
            background: var(--background);
        }

        .page { min-height: 100vh; display: flex; flex-direction: column; }

        /* Balken oben: Foto/Initialen, Name, Sprachen und Knopf Log in */
        .bar {
            background: var(--primary); color: #fff;
            display: flex; align-items: center; gap: 12px; padding: 12px 20px;
        }
        .mono {
            width: 36px; height: 36px; border-radius: 50%; flex: none;
            background: #fff; color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: bold; overflow: hidden;
        }
        .mono img { width: 100%; height: 100%; object-fit: cover; }
        .bar .who { flex: 1; min-width: 0; }
        .bar .who .n { font-size: 14px; font-weight: bold; line-height: 1.2; }
        .bar .who .r { font-size: 11px; opacity: .8; }

        .lang a {
            display: inline-block; font-size: 11.5px; padding: 3px 9px; margin-left: 4px;
            border-radius: 99px; border: 1px solid rgba(255,255,255,.55); color: #fff;
            text-decoration: none;
        }
        .lang a.on { background: #fff; color: var(--primary); border-color: #fff; }
        .btn-top {
            font-size: 12.5px; padding: 6px 14px; border-radius: 8px;
            border: 1px solid #fff; color: #fff; text-decoration: none;
        }

        .wrap { flex: 1; width: 100%; max-width: 760px; margin: 0 auto; padding: 22px 18px 16px; }

        .head { text-align: center; margin-bottom: 16px; }
        .head h1 { margin: 0 0 4px; font-size: 22px; color: var(--primary); }
        .head p { margin: 0; font-size: 13px; color: #6B6553; }

        .card {
            background: #fff; border: 1px solid #D8DDD8; border-radius: 12px;
            padding: 20px 22px;
        }

        .sec {
            display: flex; align-items: center; gap: 8px;
            font-size: 14px; font-weight: bold; color: var(--primary); margin: 4px 0 12px;
        }
        .sec .num {
            width: 22px; height: 22px; border-radius: 50%; flex: none;
            background: var(--primary); color: #fff; font-size: 11px;
            display: flex; align-items: center; justify-content: center;
        }

        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 14px; margin-bottom: 20px; }
        .full { grid-column: 1 / 3; }

        label { display: block; font-size: 12px; color: #555; margin-bottom: 4px; }

        /* Feld (mit oder ohne Symbol darin) */
        .field { position: relative; }
        .field input, .field select, .field textarea {
            width: 100%; padding: 10px 12px; font-size: 14px; font-family: inherit;
            border: 1px solid #D8DDD8; border-radius: 8px; background: #fff; color: #2B2A24;
        }
        .field.has-icon input { padding-left: 40px; }
        .field textarea { resize: vertical; min-height: 64px; }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: 2px solid var(--accent); border-color: var(--accent);
        }
        .field .icon {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; opacity: .65; pointer-events: none;
            /* macht alle Symbole gleichfarbig, egal welche Farbe die .svg-Datei hat */
            filter: brightness(0);
        }
        .field.password input { padding-right: 42px; }

        /* Auge-Knopf (Passwort zeigen/verstecken) */
        .toggle {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: none; border: none; padding: 4px; cursor: pointer;
            display: flex; align-items: center;
        }
        .toggle img { width: 18px; height: 18px; opacity: .65; filter: brightness(0); }

        /* Feld für den Bluttest */
        .upload {
            display: flex; align-items: center; gap: 12px;
            border: 1px dashed var(--accent); border-radius: 8px;
            background: #FBEFD9; padding: 14px; margin-bottom: 18px;
        }
        .upload > img { width: 28px; height: 28px; flex: none; }
        .upload .txt { flex: 1; min-width: 0; }
        .upload .t1 { font-size: 13px; font-weight: bold; }
        .upload .t2 { font-size: 11.5px; color: #6B6553; }
        .upload .fname { font-size: 11.5px; color: var(--primary); margin-top: 2px; word-break: break-all; }
        .upload input[type=file] { display: none; }
        .upload .pick {
            font-size: 12px; padding: 7px 12px; border-radius: 8px; cursor: pointer;
            border: 1px solid var(--primary); color: var(--primary); background: #fff; margin: 0;
        }

        /* Häkchen für die Datenschutzerklärung */
        .privacy { display: flex; gap: 8px; align-items: flex-start; font-size: 12px; color: #555; margin: 0 0 16px; }
        .privacy input { margin-top: 2px; flex: none; }

        .btn-submit {
            width: 100%; padding: 12px; font-size: 14px; font-weight: bold;
            color: #fff; background: var(--primary);
            border: none; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { opacity: .9; }
        /* macht den Pfeil weiß, auch wenn die .svg-Datei eine neutrale Farbe hat */
        .btn-submit img { width: 16px; height: 16px; filter: brightness(0) invert(1); }

        .error {
            background: #F9D6D6; color: #791F1F; font-size: 13px;
            padding: 9px 11px; border-radius: 8px; margin-bottom: 16px;
        }

        .has-account { text-align: center; font-size: 13px; color: #666; margin-top: 14px; }
        .has-account a { color: var(--accent); font-weight: bold; text-decoration: none; }

        .foot {
            background: var(--secondary); border-top: 1px solid rgba(0,0,0,.08);
            padding: 10px 20px; display: flex; align-items: center; justify-content: space-between;
            gap: 10px; flex-wrap: wrap; font-size: 11.5px; color: #3d3d3d;
        }
        .foot .powered { display: flex; align-items: center; gap: 6px; }
        .foot b { color: var(--primary); }

        /* Handy: alles in einer Spalte */
        @media (max-width: 560px) {
            .grid { grid-template-columns: 1fr; }
            .full { grid-column: 1; }
            .bar { flex-wrap: wrap; }
            .upload { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="page">

    <!-- Balken oben -->
    <div class="bar">
        <div class="mono">
            <?php if ($photo !== ""): ?>
                <img src="<?= e($photo) ?>" alt="<?= e($name) ?>">
            <?php else: ?>
                <?= e($initials) ?>
            <?php endif; ?>
        </div>
        <div class="who">
            <div class="n"><?= e($name) ?></div>
            <div class="r"><?= e($t['role']) ?></div>
        </div>
        <div class="lang">
            <a href="register.php?lang=en" class="<?= $lang === 'en' ? 'on' : '' ?>">EN</a>
            <a href="register.php?lang=de" class="<?= $lang === 'de' ? 'on' : '' ?>">DE</a>
            <a href="register.php?lang=pt" class="<?= $lang === 'pt' ? 'on' : '' ?>">PT</a>
        </div>
        <a class="btn-top" href="login.php?lang=<?= e($lang) ?>"><?= e($t['login']) ?></a>
    </div>

    <div class="wrap">
        <div class="head">
            <h1><?= e($t['title']) ?></h1>
            <p><?= e($t['subtitle_lead']) ?> <?= e(sprintf($t['subtitle'], $name)) ?></p>
        </div>

        <div class="card">

            <!-- Fehlermeldung (erscheint nur, wenn $error nicht leer ist) -->
            <?php if ($error !== ""): ?>
                <div class="error"><?= e($error) ?></div>
            <?php endif; ?>

            <!-- enctype multipart/form-data: nötig, um die Datei des Bluttests zu senden -->
            <form method="post" action="register.php" enctype="multipart/form-data">
                <!-- Sicherheits-Token (versteckt) -->
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <!-- 1. Persönliche Daten -->
                <div class="sec"><span class="num">1</span><?= e($t['sec1']) ?></div>
                <div class="grid">
                    <div>
                        <label for="first_name"><?= e($t['first_name']) ?></label>
                        <div class="field"><input type="text" id="first_name" name="first_name" maxlength="100" value="<?= e($v['first_name']) ?>" required></div>
                    </div>
                    <div>
                        <label for="last_name"><?= e($t['last_name']) ?></label>
                        <div class="field"><input type="text" id="last_name" name="last_name" maxlength="100" value="<?= e($v['last_name']) ?>" required></div>
                    </div>
                    <div>
                        <label for="phone"><?= e($t['phone']) ?></label>
                        <div class="field has-icon">
                            <img class="icon" src="icons/phone.svg" alt="">
                            <input type="tel" id="phone" name="phone" maxlength="100" placeholder="+00 000 000 000" value="<?= e($v['phone']) ?>">
                        </div>
                    </div>
                    <div>
                        <label for="dob"><?= e($t['dob']) ?></label>
                        <div class="field has-icon">
                            <img class="icon" src="icons/calendar.svg" alt="">
                            <input type="date" id="dob" name="dob" value="<?= e($v['dob']) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div>
                        <label for="country"><?= e($t['country']) ?></label>
                        <div class="field">
                            <select id="country" name="country">
                                <option value=""><?= e($t['country_ph']) ?></option>
                                <?php foreach ($countries as $key => $labels): ?>
                                    <option value="<?= e($key) ?>" <?= $v['country'] === $key ? 'selected' : '' ?>>
                                        <?= e($labels[$lang] ?? $key) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="city"><?= e($t['city']) ?></label>
                        <div class="field"><input type="text" id="city" name="city" maxlength="100" value="<?= e($v['city']) ?>"></div>
                    </div>
                    <div class="full">
                        <label for="preferred_language"><?= e($t['pref_lang']) ?></label>
                        <div class="field">
                            <select id="preferred_language" name="preferred_language">
                                <option value="0" <?= $v['preferred_language'] === '0' ? 'selected' : '' ?>>English</option>
                                <option value="1" <?= $v['preferred_language'] === '1' ? 'selected' : '' ?>>Deutsch</option>
                                <option value="2" <?= $v['preferred_language'] === '2' ? 'selected' : '' ?>>Português</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2. Anfangsdaten für die Betreuung -->
                <div class="sec"><span class="num">2</span><?= e($t['sec2']) ?></div>
                <div class="grid">
                    <div>
                        <label for="weight"><?= e($t['weight']) ?></label>
                        <div class="field has-icon">
                            <img class="icon" src="icons/scale.svg" alt="">
                            <input type="text" inputmode="decimal" id="weight" name="weight" placeholder="64.2" value="<?= e($v['weight']) ?>">
                        </div>
                    </div>
                    <div>
                        <label for="height"><?= e($t['height']) ?></label>
                        <div class="field has-icon">
                            <img class="icon" src="icons/ruler.svg" alt="">
                            <input type="text" inputmode="decimal" id="height" name="height" placeholder="168" value="<?= e($v['height']) ?>">
                        </div>
                    </div>
                    <div class="full">
                        <label for="goal"><?= e($t['goal']) ?></label>
                        <div class="field">
                            <textarea id="goal" name="goal" maxlength="255" placeholder="<?= e($t['goal_ph']) ?>"><?= e($v['goal']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 3. Dein Konto -->
                <div class="sec"><span class="num">3</span><?= e($t['sec3']) ?></div>
                <div class="grid">
                    <div>
                        <label for="email"><?= e($t['email']) ?></label>
                        <div class="field has-icon">
                            <img class="icon" src="icons/mail.svg" alt="">
                            <input type="email" id="email" name="email" maxlength="255" placeholder="<?= e($t['email_ph']) ?>" value="<?= e($v['email']) ?>" required>
                        </div>
                    </div>
                    <div>
                        <label for="password"><?= e($t['password']) ?></label>
                        <div class="field has-icon password">
                            <img class="icon" src="icons/lock.svg" alt="">
                            <input type="password" id="password" name="password" minlength="8" placeholder="<?= e($t['password_ph']) ?>" required>
                            <button type="button" class="toggle" onclick="togglePassword()" aria-label="<?= e($t['toggle_label']) ?>">
                                <img id="eye-icon" src="icons/eye.svg" alt="">
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4. Bluttest (Pflicht; wird gespeichert, siehe BLOCK 9) -->
                <div class="sec"><span class="num">4</span><?= e($t['sec4']) ?></div>
                <div class="upload">
                    <img src="icons/upload.svg" alt="">
                    <div class="txt">
                        <div class="t1"><?= e($t['blood_title']) ?></div>
                        <div class="t2"><?= e($t['blood_hint']) ?></div>
                        <div class="fname" id="file-name"><?= e($t['no_file']) ?></div>
                    </div>
                    <label class="pick" for="blood_test"><?= e($t['choose_file']) ?></label>
                    <input type="file" id="blood_test" name="blood_test" accept=".pdf,application/pdf" required>
                </div>

                <!-- Datenschutzerklärung: der Browser lässt nur mit gesetztem Häkchen senden
                     (required). Zusätzlich prüft auch das PHP, ob das Häkchen gesetzt ist (isset($_POST['privacy'])). -->
                <label class="privacy">
                    <input type="checkbox" name="privacy" required>
                    <span><?= e($t['privacy']) ?></span>
                </label>

                <button type="submit" class="btn-submit">
                    <?= e($t['submit']) ?>
                    <img src="icons/arrow-right.svg" alt="">
                </button>
            </form>

            <div class="has-account"><?= e($t['has_account']) ?> <a href="login.php?lang=<?= e($lang) ?>"><?= e($t['login']) ?></a></div>
        </div>
    </div>

    <div class="foot">
        <span><?= e($name) ?>. <?= e($t['rights']) ?></span>
        <span class="powered">
            <svg width="15" height="15" viewBox="0 0 48 48" aria-hidden="true">
                <path d="M8 40 C8 19 23 7 42 7 C42 27 31 42 8 40 Z" fill="#2F5D3A"/>
                <path d="M8 40 L31 17" stroke="#ffffff" stroke-width="3" stroke-linecap="round" fill="none"/>
                <circle cx="39" cy="38" r="5" fill="#C9803A"/>
            </svg>
            Powered by <b>NutriTrackWeb</b>
        </span>
    </div>
</div>

<script>
    // Zeigt oder versteckt das Passwort, wenn man auf das Auge klickt
    function togglePassword() {
        var field = document.getElementById("password");
        var icon  = document.getElementById("eye-icon");

        if (field.type === "password") {
            field.type = "text";
            icon.src = "icons/eye-off.svg";
        } else {
            field.type = "password";
            icon.src = "icons/eye.svg";
        }
    }

    // Zeigt den Namen der gewählten Datei im Feld des Bluttests
    document.getElementById("blood_test").addEventListener("change", function () {
        var label = document.getElementById("file-name");
        if (this.files.length > 0) {
            label.textContent = this.files[0].name;
        } else {
            label.textContent = <?= json_encode($t['no_file']) ?>;
        }
    });
</script>
</body>
</html>
