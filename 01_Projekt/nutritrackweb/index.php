<?php
// index.php
// ÖFFENTLICHE STARTSEITE der Ernährungsberaterin: jede Person kann sie sehen, ohne Login.
// Sie zeigt, was die Ärztin in den "Profileinstellungen" (settings.php) geschrieben hat:
// Name, Foto, Biografie, Ausbildung, Telefon, Website und soziale Netzwerke, mit ihren Farben.
// Von hier geht die Person zum Login oder zur Registrierung. Auch der Logout führt hierher zurück.
// Die Sprache kommt aus dem Link (?lang=en, de oder pt).

// BLOCK 1: Mit der Datenbank verbinden
require 'config.php';

// Sprache aus dem Link (erlaubt sind nur en, de oder pt)
$lang = $_GET['lang'] ?? 'en';
if (!in_array($lang, ['en', 'de', 'pt'])) {
    $lang = 'en';
}

// BLOCK 2: Texte der Seite, ein Satz Texte für jede Sprache
$texts = [
    'en' => [
        'role' => 'Nutritionist', 'about' => 'About', 'contact' => 'Contact',
        'login' => 'Log in', 'register' => 'Register',
        'eyebrow' => 'Online nutrition care',
        'headline' => 'Healthy eating, with a plan made for you',
        'default_bio' => 'Personal nutrition guidance, online, with follow-up designed around you.',
        'cta' => 'Become a client',
        'sec_about' => 'About and studies', 'sec_contact' => 'Contact',
        'rights' => 'All rights reserved.', 'powered' => 'Powered by',
    ],
    'pt' => [
        'role' => 'Nutricionista', 'about' => 'Sobre', 'contact' => 'Contato',
        'login' => 'Entrar', 'register' => 'Cadastrar',
        'eyebrow' => 'Acompanhamento nutricional online',
        'headline' => 'Alimentação saudável, com um plano feito para você',
        'default_bio' => 'Orientação nutricional personalizada, online, com acompanhamento pensado para você.',
        'cta' => 'Quero ser cliente',
        'sec_about' => 'Sobre e formação', 'sec_contact' => 'Contato',
        'rights' => 'Todos os direitos reservados.', 'powered' => 'Powered by',
    ],
    'de' => [
        'role' => 'Ernährungsberaterin', 'about' => 'Über mich', 'contact' => 'Kontakt',
        'login' => 'Anmelden', 'register' => 'Registrieren',
        'eyebrow' => 'Online-Ernährungsberatung',
        'headline' => 'Gesund essen – mit einem Plan, der zu Ihnen passt',
        'default_bio' => 'Persönliche Ernährungsberatung online, mit einer Betreuung, die auf Sie zugeschnitten ist.',
        'cta' => 'Kunde werden',
        'sec_about' => 'Über mich und Ausbildung', 'sec_contact' => 'Kontakt',
        'rights' => 'Alle Rechte vorbehalten.', 'powered' => 'Powered by',
    ],
];
$t = $texts[$lang];

// BLOCK 3: Kleine Funktionen
function e($text) {
    // htmlspecialchars() macht aus Zeichen wie < und > harmlosen Text. So kann niemand Schadcode (JavaScript) in die Seite einschleusen (XSS-Schutz).
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// Nur http/https-Links sind erlaubt (der Link kommt aus der Datenbank, aber wir vertrauen nie blind)
function safe_url($url) {
    $url = trim((string)$url);
    return preg_match('#^https?://#i', $url) ? $url : '';
}

// BLOCK 4: Daten der Ärztin (alles, was sie in settings.php geschrieben hat)
$profile = $pdo->query(
    "SELECT professional_name, profile_photo_path, bio, studies, phone, website,
            instagram_url, linkedin_url, facebook_url,
            primary_color, secundary_color, background_color, accent_color
     FROM nutritionist_profile LIMIT 1"
)->fetch();

$name       = $profile['professional_name'] ?? "NutriTrackWeb";
$primary    = $profile['primary_color']      ?? "#2E7D32";
$secondary  = $profile['secundary_color']    ?? "#A5D6A7";
$background = $profile['background_color']   ?? "#F5F7F5";
$accent     = $profile['accent_color']       ?? "#FF9800";

// Farben nur im Format #rrggbb (wenn in der Datenbank etwas falsch ist, wird die Standardfarbe benutzt)
$colors = ['primary' => $primary, 'secondary' => $secondary, 'background' => $background, 'accent' => $accent];
$defaults = ['primary' => '#2E7D32', 'secondary' => '#A5D6A7', 'background' => '#F5F7F5', 'accent' => '#FF9800'];
foreach ($colors as $k => $c) {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c)) { $colors[$k] = $defaults[$k]; }
}
list($primary, $secondary, $background, $accent) = array_values($colors);

// Foto: nur aus dem Ordner uploads/profile/
$photo = (string)($profile['profile_photo_path'] ?? '');
if (!preg_match('#^uploads/profile/[A-Za-z0-9._-]+$#', $photo)) { $photo = ""; }

// Initialen ("Dra. Sofia Martins" wird zu "SM")
$initials = "";
foreach (explode(" ", trim($name)) as $word) {
    if ($word === "" || strpos($word, ".") !== false) { continue; }
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}
$initials = mb_substr($initials, 0, 2);

$bio = trim((string)($profile['bio'] ?? ''));
if ($bio === '') { $bio = $t['default_bio']; }

// Ausbildung: jede Zeile wird ein Listenpunkt (höchstens 12)
$studies = [];
foreach (preg_split('/\r\n|\r|\n/', (string)($profile['studies'] ?? '')) as $line) {
    $line = trim($line);
    if ($line !== '') { $studies[] = $line; }
}
$studies = array_slice($studies, 0, 12);

$phone     = trim((string)($profile['phone'] ?? ''));
$website   = safe_url($profile['website'] ?? '');
$instagram = safe_url($profile['instagram_url'] ?? '');
$linkedin  = safe_url($profile['linkedin_url'] ?? '');
$facebook  = safe_url($profile['facebook_url'] ?? '');
$has_contact = ($phone !== '' || $website !== '' || $instagram !== '' || $linkedin !== '' || $facebook !== '');

// Schöne Adresse der Website für die Anzeige (ohne "https://" und ohne "/" am Ende)
$website_label = rtrim(preg_replace('#^https?://#i', '', $website), '/');
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($name) ?></title>
    <meta name="description" content="<?= e(mb_substr($bio, 0, 150)) ?>">

    <style>
        /* Die 4 Farben kommen aus der Datenbank und werden zu CSS-Variablen */
        :root {
            --primary:    <?= e($primary) ?>;
            --secondary:  <?= e($secondary) ?>;
            --background: <?= e($background) ?>;
            --accent:     <?= e($accent) ?>;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #2B2A24;
            background: var(--background);
        }
        a { color: inherit; text-decoration: none; }

        .page { min-height: 100vh; display: flex; flex-direction: column; }

        /* oberer Streifen */
        .bar {
            background: var(--primary); color: #fff;
            padding: 14px 28px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        }
        .logo-c {
            width: 46px; height: 46px; border-radius: 50%; flex: none; overflow: hidden;
            background: var(--background); color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px;
        }
        .logo-c img { width: 100%; height: 100%; object-fit: cover; }
        .who .n { font-size: 18px; font-weight: bold; }
        .who .r { font-size: 12px; opacity: .85; }
        .bar .grow { flex: 1; }
        .links { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; font-size: 14px; font-weight: bold; }
                .lang a {
            display: inline-block; font-size: 11px; padding: 3px 9px; margin-left: 3px;
            border-radius: 99px; border: 1px solid rgba(255,255,255,.5); color: #fff;
        }
        .lang a.on { background: #fff; color: var(--primary); border-color: #fff; font-weight: bold; }
        .btn {
            display: inline-block; padding: 9px 18px; font-size: 14px; font-weight: bold;
            border-radius: 8px; border: 1px solid transparent; cursor: pointer;
        }
        .btn.out { border-color: #fff; color: #fff; }
        .btn.out:hover { background: rgba(255,255,255,.15); }
        .btn.fill { background: var(--accent); color: #fff; }
        .btn.fill:hover { opacity: .92; }
        .btn.out-d { border-color: var(--primary); color: var(--primary); background: transparent; }
        .btn.out-d:hover { background: rgba(0,0,0,.04); }

        .wrap { width: 100%; max-width: 1040px; margin: 0 auto; padding: 0 24px; flex: 1; }

        /* Hauptteil */
        .hero { display: flex; align-items: center; justify-content: space-between; gap: 32px; padding: 44px 0 30px; }
        .hero-text { flex: 1; min-width: 0; }
        .eyebrow { font-size: 14px; font-weight: bold; color: var(--accent); margin-bottom: 10px; }
        h1 { margin: 0 0 14px; font-size: 36px; line-height: 1.2; color: var(--primary); max-width: 520px; }
        .bio { font-size: 16px; line-height: 1.6; color: #55534a; max-width: 520px; margin: 0 0 22px; word-break: break-word; }
        .cta { display: flex; gap: 10px; flex-wrap: wrap; }
        .big {
            width: 220px; height: 220px; border-radius: 50%; flex: none; overflow: hidden;
            background: var(--secondary); color: var(--primary);
            border: 8px solid rgba(255,255,255,.55); outline: 2px solid var(--secondary);
            display: flex; align-items: center; justify-content: center; font-size: 64px; font-weight: bold;
        }
        .big img { width: 100%; height: 100%; object-fit: cover; }

        /* Karten */
        .cards { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; padding: 10px 0 40px; align-items: start; }
        .cards.one { grid-template-columns: minmax(0, 560px); }
        .box { background: #fff; border: 1px solid #D8DDD8; border-radius: 16px; padding: 20px 22px; min-width: 0; scroll-margin-top: 16px; }
        .box h2 { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; font-size: 16px; color: var(--primary); }
        .box h2 img, .row img { width: 18px; height: 18px; opacity: .75; flex: none; }
        .row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #E5E8E5; font-size: 15px; word-break: break-word; }
        .row:last-child { border-bottom: none; }
        .row .ck { color: var(--accent); font-weight: bold; flex: none; }
        .row a:hover { text-decoration: underline; }
        .social { display: flex; gap: 12px; padding-top: 12px; }
        .social a {
            width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--accent); color: var(--accent);
        }
        .social a:hover { background: var(--accent); color: #fff; }
        .social svg { width: 18px; height: 18px; }

        /* Fußzeile (rodapé) */
        .foot {
            background: var(--secondary); padding: 14px 28px; display: flex; justify-content: space-between;
            align-items: center; gap: 10px; flex-wrap: wrap; font-size: 13px; color: #3a3a33;
        }
        .foot b { color: var(--primary); }
        .powered { display: flex; align-items: center; gap: 6px; }

        @media (max-width: 800px) {
            .bar { padding: 12px 16px; }
            .grow { display: none; }
            .links { width: 100%; justify-content: space-between; }
            .hero { flex-direction: column-reverse; text-align: center; padding-top: 28px; }
            .cta { justify-content: center; }
            h1 { font-size: 28px; }
            .big { width: 150px; height: 150px; font-size: 44px; }
            .cards, .cards.one { grid-template-columns: 1fr; }
            .foot { padding: 14px 16px; justify-content: center; text-align: center; }
        }
    </style>
</head>
<body>
<div class="page">

    <!-- OBERER STREIFEN -->
    <header class="bar">
        <div class="logo-c">
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
        <div class="grow"></div>
        <nav class="links">
            <span class="lang">
                <a href="index.php?lang=en" class="<?= $lang === 'en' ? 'on' : '' ?>">EN</a>
                <a href="index.php?lang=de" class="<?= $lang === 'de' ? 'on' : '' ?>">DE</a>
                <a href="index.php?lang=pt" class="<?= $lang === 'pt' ? 'on' : '' ?>">PT</a>
            </span>
            <a class="btn out" href="login.php?lang=<?= e($lang) ?>"><?= e($t['login']) ?></a>
            <a class="btn fill" href="register.php?lang=<?= e($lang) ?>"><?= e($t['register']) ?></a>
        </nav>
    </header>

    <div class="wrap">

        <!-- HAUPTTEIL -->
        <section class="hero">
            <div class="hero-text">
                <div class="eyebrow"><?= e($t['eyebrow']) ?></div>
                <h1><?= e($t['headline']) ?></h1>
                <p class="bio"><?= nl2br(e($bio)) ?></p>
                <div class="cta">
                    <a class="btn fill" href="register.php?lang=<?= e($lang) ?>"><?= e($t['cta']) ?></a>
                    <a class="btn out-d" href="login.php?lang=<?= e($lang) ?>"><?= e($t['login']) ?></a>
                </div>
            </div>
            <div class="big">
                <?php if ($photo !== ""): ?>
                    <img src="<?= e($photo) ?>" alt="<?= e($name) ?>">
                <?php else: ?>
                    <?= e($initials) ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- AUSBILDUNG UND KONTAKT (erscheinen nur, wenn die Ärztin sie ausgefüllt hat) -->
        <?php if (count($studies) > 0 || $has_contact): ?>
        <section class="cards <?= (count($studies) > 0 && $has_contact) ? '' : 'one' ?>">

            <?php if (count($studies) > 0): ?>
            <div class="box" id="about">
                <h2><img src="icons/file-text.svg" alt=""><?= e($t['sec_about']) ?></h2>
                <?php foreach ($studies as $line): ?>
                    <div class="row"><span class="ck">&#10003;</span><span><?= e($line) ?></span></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($has_contact): ?>
            <div class="box" id="contact">
                <h2><img src="icons/contact-book.svg" alt=""><?= e($t['sec_contact']) ?></h2>
                <?php if ($phone !== ""): ?>
                    <div class="row"><img src="icons/phone.svg" alt=""><span><?= e($phone) ?></span></div>
                <?php endif; ?>
                <?php if ($website !== ""): ?>
                    <div class="row"><img src="icons/globe.svg" alt=""><a href="<?= e($website) ?>" target="_blank" rel="noopener noreferrer"><?= e($website_label) ?></a></div>
                <?php endif; ?>
                <?php if ($instagram !== "" || $linkedin !== "" || $facebook !== ""): ?>
                    <div class="social">
                        <?php if ($instagram !== ""): ?>
                            <a href="<?= e($instagram) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if ($linkedin !== ""): ?>
                            <a href="<?= e($linkedin) ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 11v5M8 8v.01M12 16v-5M12 13a2.5 2.5 0 0 1 5 0v3"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if ($facebook !== ""): ?>
                            <a href="<?= e($facebook) ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 8h2.5V4.5H14A4 4 0 0 0 10 8.5V11H7.5v3.5H10V21h3.5v-6.5H16l.5-3.5h-3V8.8c0-.5.3-.8 1-.8Z"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </section>
        <?php endif; ?>
    </div>

    <!-- FUSSZEILE -->
    <footer class="foot">
        <span><?= e($name) ?>. <?= e($t['rights']) ?></span>
        <span class="powered">
            <svg width="14" height="14" viewBox="0 0 48 48" aria-hidden="true">
                <path d="M8 40 C8 19 23 7 42 7 C42 27 31 42 8 40 Z" fill="#2F5D3A"/>
                <path d="M8 40 L31 17" stroke="#ffffff" stroke-width="3" stroke-linecap="round" fill="none"/>
                <circle cx="39" cy="38" r="5" fill="#C9803A"/>
            </svg>
            <?= e($t['powered']) ?> <b>NutriTrackWeb</b>
        </span>
    </footer>

</div>
</body>
</html>
