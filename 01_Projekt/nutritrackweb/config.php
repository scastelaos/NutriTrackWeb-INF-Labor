<?php
// config.php
// Datenbank-Einstellungen: Server (host), Name der Datenbank (dbname), Benutzer (username) und Passwort (password).

$host = "localhost";
$dbname = "mydb";
$username = "castelao";
$password = "";

// Verbindung zur Datenbank (PDO = PHP Data Objects). Jede Seite lädt diese Datei mit require 'config.php' und kann dann $pdo benutzen.
// Die Zugangsdaten gelten für die lokale Entwicklung mit XAMPP (dort gibt es kein Passwort). Auf einem echten Server gehört ein Passwort nie ins GitHub.
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    // Bei einem SQL-Fehler wirft PDO eine Exception (Ausnahme), statt still weiterzumachen. So sieht man Fehler sofort.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Wenn die Verbindung nicht klappt (z. B. MariaDB ist nicht gestartet), wird die Seite mit die() beendet und zeigt eine Fehlermeldung (die = stoppe alles).
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}