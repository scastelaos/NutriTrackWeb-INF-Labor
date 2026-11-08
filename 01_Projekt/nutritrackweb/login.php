<?php
// login.php
// Login-Seite (nur die Karte) + die Logik, die E-Mail und Passwort prüft.
// Der Sprachumschalter (EN / DE / PT) funktioniert hier schon.

// BLOCK 1: Session starten und mit der Datenbank verbinden
// session_start() startet die Session (Sitzung; sessão). Der Server erkennt den Besucher an einer Nummer im Cookie wieder.
// Alles, was in $_SESSION steht (z. B. user_id), bleibt zwischen den Seiten gespeichert.
session_start();
require 'config.php';

// Höchstzeit ohne Aktivität: 30 Minuten (in Sekunden)
$timeout = 30 * 60;

// BLOCK 2: zwei kleine Funktionen

// e() schützt jeden Text, bevor er auf dem Bildschirm erscheint (gegen XSS: eingeschleuster Code wird zu normalem Text)
function e($text) {
    // htmlspecialchars() macht aus Zeichen wie < und > harmlosen Text. So kann niemand Schadcode (JavaScript) in die Seite einschleusen (XSS-Schutz).
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// findet heraus, auf welche Seite die Person gehen soll (oder null, wenn sie nicht hinein darf)
function get_destination($pdo, $user_id, $role) {

    // role 1 = Admin: darf nur hinein, wenn users.status 1 (Active) ist
    if ($role == 1) {
        // prepare() + execute() = Prepared Statement (vorbereitete Abfrage). Die ? sind Platzhalter; die Werte werden getrennt gesendet.
        // So kann niemand SQL-Code einschleusen (Schutz gegen SQL-Injection).
        $stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && $user['status'] == 1) {
            return "admin_dashboard.php";
        }
        return null;
    }

    // role 0 = Kunde: die Seite hängt vom approval_status ab
    if ($role == 0) {
        $stmt = $pdo->prepare("SELECT approval_status FROM clients WHERE users_user_id = ?");
        $stmt->execute([$user_id]);
        $client = $stmt->fetch();

        if (!$client) {
            return null;                  // Benutzer ohne Eintrag in clients
        }
        if ($client['approval_status'] == 1) {
            return "client_home.php";     // 1 = Approved (freigegeben)
        }
        if (in_array($client['approval_status'], [0, 2, 3])) {
            return "client_status.php";   // 0 Pending, 2 Rejected, 3 MoreInfos
        }
        return null;
    }

    return null;                          // unbekannte role
}

// BLOCK 3: Sprache und Texte der Seite
// die Sprache kommt aus dem Link (?lang=pt), wird in der Session gespeichert, Standard ist EN
$languages = ['en', 'de', 'pt'];
$lang = $_SESSION['lang'] ?? 'en';

if (isset($_GET['lang']) && in_array($_GET['lang'], $languages)) {
    $lang = $_GET['lang'];
}

// alle Texte der Seite, ein Satz für jede Sprache
$texts = [
    'en' => [
        'title'        => 'Sign In',
        'role'         => 'Nutritionist',
        'welcome'      => 'Welcome Back',
        'email'        => 'Email',
        'email_ph'     => 'name@email.com',
        'password'     => 'Password',
        'password_ph'  => 'Your password',
        'toggle_label' => 'Show or hide password',
        'signin'       => 'Sign In',
        'no_account'   => 'Not registered yet?',
        'register'     => 'Create Account',
        'err_invalid'  => 'Invalid request. Please reload the page and try again.',
        'err_empty'    => 'Please enter your email and password.',
        'err_wrong'    => 'Email or password incorrect.',
        'expired'      => 'Your session expired. Please log in again.',
    ],
    'pt' => [
        'title'        => 'Entrar',
        'role'         => 'Nutricionista',
        'welcome'      => 'Bem-vindo(a) de volta',
        'email'        => 'E-mail',
        'email_ph'     => 'nome@email.com',
        'password'     => 'Senha',
        'password_ph'  => 'Sua senha',
        'toggle_label' => 'Mostrar ou esconder a senha',
        'signin'       => 'Entrar',
        'no_account'   => 'Ainda não possui cadastro?',
        'register'     => 'Criar conta',
        'err_invalid'  => 'Solicitação inválida. Recarregue a página e tente novamente.',
        'err_empty'    => 'Informe seu e-mail e sua senha.',
        'err_wrong'    => 'E-mail ou senha incorretos.',
        'expired'      => 'Sua sessão expirou. Entre novamente.',
    ],
    'de' => [
        'title'        => 'Anmelden',
        'role'         => 'Ernährungsberaterin',
        'welcome'      => 'Willkommen zurück',
        'email'        => 'E-Mail',
        'email_ph'     => 'name@email.com',
        'password'     => 'Passwort',
        'password_ph'  => 'Ihr Passwort',
        'toggle_label' => 'Passwort anzeigen oder verbergen',
        'signin'       => 'Anmelden',
        'no_account'   => 'Noch nicht registriert?',
        'register'     => 'Konto erstellen',
        'err_invalid'  => 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        'err_empty'    => 'Bitte geben Sie Ihre E-Mail-Adresse und Ihr Passwort ein.',
        'err_wrong'    => 'E-Mail oder Passwort ist falsch.',
        'expired'      => 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.',
    ],
];

// $t ist der Satz Texte der gewählten Sprache
$t = $texts[$lang];

// BLOCK 4: Meldungen für den Bildschirm und Prüfung, wer schon eingeloggt ist
$error = "";
$info  = "";

// die geschützten Seiten schicken hierher mit ?expired=1, wenn die Zeit abgelaufen ist
if (isset($_GET['expired'])) {
    $info = $t['expired'];
}

if (isset($_SESSION['user_id'])) {
    $inactive = time() - ($_SESSION['last_activity'] ?? 0);

    if ($inactive > $timeout) {
        // mehr als 30 Minuten vergangen: die alte Session wird beendet
        // Zeit abgelaufen oder nicht erlaubt: session_unset() leert alle Daten in $_SESSION, session_destroy() zerstört
        // die Session auf dem Server. Danach ist die Person nicht mehr eingeloggt (logout).
        session_unset();
        session_destroy();
        session_start();
        $info = $t['expired'];
    } else {
        // gültige Session: man muss den Login nicht noch einmal sehen
        $destination = get_destination($pdo, $_SESSION['user_id'], $_SESSION['role']);

        if ($destination !== null) {
            // header("Location: ...") schickt den Browser auf eine andere Seite (Redirect). Das exit danach stoppt das Skript, damit nichts mehr ausgeführt wird.
            header("Location: " . $destination);
            exit;
        }
        // wenn die Person nicht hinein darf, wird die Session geleert und der Login gezeigt
        session_unset();
        session_destroy();
        session_start();
    }
}

// die Sprache in der Session speichern (nochmal, weil die Session oben neu erstellt worden sein kann)
$_SESSION['lang'] = $lang;

// BLOCK 5: Sicherheits-Token des Formulars (CSRF)
// eine geheime Zahl, die versteckt im Formular mitgeschickt wird und mit ihm zurückkommt
if (empty($_SESSION['csrf_token'])) {
    // CSRF-Token: random_bytes() erzeugt eine zufällige, geheime Zahl. Sie geht versteckt im Formular mit.
    // So erkennt der Server, dass das Formular wirklich von dieser Seite kommt und nicht von einer fremden Webseite (CSRF-Angriff).
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// BLOCK 6: Was passiert, wenn das Formular abgeschickt wird
$email = "";

// REQUEST_METHOD zeigt, wie die Seite aufgerufen wurde: POST = ein Formular wurde abgeschickt, GET = die Seite wird nur angezeigt.
// Alles aus $_POST und $_GET kommt vom Benutzer und wird deshalb immer geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    // hash_equals() vergleicht zwei Texte auf sichere Weise: das Token aus dem Formular und das Token aus der Session.
    // Wenn sie nicht gleich sind, wird NICHTS gespeichert.
    if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        // die geheime Zahl stimmt nicht: das Formular kam nicht von dieser Seite
        $error = $t['err_invalid'];

    } elseif ($email === '' || $password === '') {
        $error = $t['err_empty'];

    } else {
        // den Benutzer über die E-Mail suchen
        $stmt = $pdo->prepare("SELECT user_id, password_hash, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // das Passwort mit dem Hash prüfen, der in der Datenbank gespeichert ist (Hash = Passwort unlesbar gemacht)
        // password_verify() vergleicht das eingegebene Passwort mit dem Hash aus der Datenbank.
        // Das echte Passwort wird nie gespeichert und nie zurückgerechnet.
        if ($user && password_verify($password, $user['password_hash'])) {

            $destination = get_destination($pdo, $user['user_id'], $user['role']);

            if ($destination !== null) {
                // Login korrekt: eine neue Session erstellen und nur das Nötigste speichern
                // session_regenerate_id(true) gibt der Person eine NEUE Session-Nummer. Eine alte (gestohlene) Nummer funktioniert dann nicht mehr
                // (Schutz gegen Session Fixation).
                session_regenerate_id(true);
                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['last_activity'] = time();

                // das alte Token gilt nicht mehr: die nächsten Seiten erstellen ein neues
                unset($_SESSION['csrf_token']);

                header("Location: " . $destination);
                exit;
            }
        }

        // dieselbe Meldung für falsche E-Mail, falsches Passwort oder Konto ohne Zugang (so erfährt man nicht, welche E-Mails existieren)
        $error = $t['err_wrong'];
    }
}

// BLOCK 7: Daten der Ärztin holen, um die Karte aufzubauen (Name, Foto und Farben)
$stmt = $pdo->query(
    "SELECT professional_name, profile_photo_path,
            primary_color, secundary_color, background_color, accent_color
     FROM nutritionist_profile LIMIT 1"
);
$profile = $stmt->fetch();

// Standardwerte, falls es noch kein Profil gibt
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
        continue;                         // "Dra.", "Dr." usw. werden ignoriert
    }
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}
$initials = mb_substr($initials, 0, 2);

// Teil des Links vom Sprachumschalter: behält die Meldung "Session abgelaufen"
$extra = isset($_GET['expired']) ? '&expired=1' : '';
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

        .page {
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* dekorative Formen im Hintergrund */
        .shape { position: absolute; border-radius: 50%; }
        .shape1 { width: 280px; height: 280px; background: var(--secondary); opacity: .45; left: -100px; bottom: -120px; }
        .shape2 { width: 130px; height: 130px; background: var(--secondary); opacity: .30; right: -30px; top: 70px; }
        .shape3 { width: 50px;  height: 50px;  background: var(--accent);    opacity: .25; right: 110px; bottom: 90px; }

        /* Sprachumschalter */
        .top { position: relative; display: flex; justify-content: flex-end; padding: 16px 20px; }
        .lang a {
            display: inline-block; font-size: 12px; padding: 3px 10px; margin-left: 4px;
            border-radius: 99px; border: 1px solid #D8DDD8; background: #fff; color: #555;
            text-decoration: none;
        }
        .lang a:hover { border-color: var(--primary); }
        .lang a.on { background: var(--primary); color: #fff; border-color: var(--primary); }

        .middle {
            position: relative; flex: 1;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 18px 10px;
        }

        .card {
            position: relative; width: 100%; max-width: 340px;
            background: #fff; border: 1px solid #D8DDD8; border-radius: 16px;
            padding: 50px 24px 24px;
        }

        .photo {
            position: absolute; left: 50%; top: -38px; margin-left: -38px;
            width: 76px; height: 76px; border-radius: 50%;
            border: 4px solid var(--background);
            background: var(--primary); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: bold; overflow: hidden;
        }
        .photo img { width: 100%; height: 100%; object-fit: cover; }

        .name { text-align: center; font-size: 18px; font-weight: bold; color: var(--primary); }
        .role { text-align: center; font-size: 12px; font-weight: bold; color: var(--accent); margin: 2px 0 14px; }
        .welcome { text-align: center; font-size: 14px; color: #555; margin-bottom: 18px; }

        label { display: block; font-size: 12px; color: #555; margin-bottom: 4px; }

        /* Feld mit Symbol darin */
        .field { position: relative; margin-bottom: 14px; }
        .field input {
            width: 100%; padding: 10px 12px 10px 40px;
            font-size: 14px; border: 1px solid #D8DDD8; border-radius: 8px; background: #fff;
        }
        .field input:focus { outline: 2px solid var(--accent); border-color: var(--accent); }
        .field .icon {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; opacity: .65; pointer-events: none;
        }
        .field.password input { padding-right: 42px; }

        /* Auge-Knopf (Passwort zeigen/verstecken) */
        .toggle {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: none; border: none; padding: 4px; cursor: pointer;
            display: flex; align-items: center;
        }
        .toggle img { width: 18px; height: 18px; opacity: .65; }

        /* Login-Knopf */
        .btn-login {
            width: 100%; padding: 11px; font-size: 14px; font-weight: bold;
            color: #fff; background: var(--primary);
            border: none; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-login:hover { opacity: .9; }
        /* macht den Pfeil weiß, auch wenn die .svg-Datei eine neutrale Farbe hat */
        .btn-login img { width: 16px; height: 16px; filter: brightness(0) invert(1); }

        .error {
            background: #F9D6D6; color: #791F1F; font-size: 13px;
            padding: 9px 11px; border-radius: 8px; margin-bottom: 14px;
        }
        .info {
            background: #FBEFD9; color: #6B4A12; font-size: 13px;
            padding: 9px 11px; border-radius: 8px; margin-bottom: 14px;
        }

        .divider { border-top: 1px solid #E5E8E5; margin: 18px 0 14px; }
        .register { text-align: center; font-size: 13px; color: #666; }
        .register a { color: var(--accent); font-weight: bold; text-decoration: none; }

        .powered {
            position: relative; display: flex; align-items: center; justify-content: center;
            gap: 6px; font-size: 12px; color: #555; padding: 10px 0 18px;
        }
        .powered b { color: var(--primary); }
    </style>
</head>
<body>
<div class="page">
    <div class="shape shape1"></div>
    <div class="shape shape2"></div>
    <div class="shape shape3"></div>

    <!-- Sprachumschalter: jeder Knopf ist ein Link, der die Sprache wechselt -->
    <div class="top">
        <div class="lang">
            <a href="login.php?lang=en<?= $extra ?>" class="<?= $lang === 'en' ? 'on' : '' ?>">EN</a>
            <a href="login.php?lang=de<?= $extra ?>" class="<?= $lang === 'de' ? 'on' : '' ?>">DE</a>
            <a href="login.php?lang=pt<?= $extra ?>" class="<?= $lang === 'pt' ? 'on' : '' ?>">PT</a>
        </div>
    </div>

    <div class="middle">
        <div class="card">

            <!-- Foto der Ärztin (oder die Initialen, wenn es kein Foto gibt) -->
            <div class="photo">
                <?php if ($photo !== ""): ?>
                    <img src="<?= e($photo) ?>" alt="<?= e($name) ?>">
                <?php else: ?>
                    <?= e($initials) ?>
                <?php endif; ?>
            </div>

            <div class="name"><?= e($name) ?></div>
            <div class="role"><?= e($t['role']) ?></div>
            <div class="welcome"><?= e($t['welcome']) ?></div>

            <!-- Hinweis "Session abgelaufen" (erscheint nur, wenn $info nicht leer ist) -->
            <?php if ($info !== ""): ?>
                <div class="info"><?= e($info) ?></div>
            <?php endif; ?>

            <!-- Fehlermeldung (erscheint nur, wenn $error nicht leer ist) -->
            <?php if ($error !== ""): ?>
                <div class="error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <!-- Sicherheits-Token (versteckt) -->
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <label for="email"><?= e($t['email']) ?></label>
                <div class="field">
                    <img class="icon" src="icons/mail.svg" alt="">
                    <input type="email" id="email" name="email" placeholder="<?= e($t['email_ph']) ?>"
                           value="<?= e($email) ?>" required>
                </div>

                <label for="password"><?= e($t['password']) ?></label>
                <div class="field password">
                    <img class="icon" src="icons/lock.svg" alt="">
                    <input type="password" id="password" name="password" placeholder="<?= e($t['password_ph']) ?>" required>
                    <button type="button" class="toggle" onclick="togglePassword()" aria-label="<?= e($t['toggle_label']) ?>">
                        <img id="eye-icon" src="icons/eye.svg" alt="">
                    </button>
                </div>

                <button type="submit" class="btn-login">
                    <?= e($t['signin']) ?>
                    <img src="icons/arrow-right.svg" alt="">
                </button>
            </form>

            <div class="divider"></div>
            <div class="register"><?= e($t['no_account']) ?> <a href="register.php"><?= e($t['register']) ?></a></div>
        </div>
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

<script>
    // Zeigt oder versteckt das Passwort, wenn man auf das Auge klickt
    function togglePassword() {
        var field = document.getElementById("password");
        var icon  = document.getElementById("eye-icon");

        if (field.type === "password") {
            field.type = "text";                // zeigt das Passwort
            icon.src = "icons/eye-off.svg";
        } else {
            field.type = "password";            // versteckt das Passwort
            icon.src = "icons/eye.svg";
        }
    }
</script>
</body>
</html>
