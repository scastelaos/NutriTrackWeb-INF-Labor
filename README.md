# NutriTrackWeb-INF-Labor

INF-Labor Projekt: NutriTrackWeb – Verwaltungsplattform für Ernährungsberaterinnen.

**Live-Demo:** https://nutritrackweb.freehosting.dev/

NutriTrackWeb wurde als Schulprojekt (INF-Labor) entwickelt und ist online veröffentlicht. Die Demo läuft bei InfinityFree (kostenloses Hosting) und enthält nur Testdaten. Bitte keine echten Gesundheitsdaten eingeben.

## NutriTrackWeb

NutriTrackWeb ist eine Webplattform für selbstständige Ernährungsberaterinnen zur Verwaltung von Kunden, Terminen, Dokumenten, Mahlzeitenfotos, Zahlungen und Kommunikation. Die Oberfläche gibt es auf Deutsch, Englisch und Portugiesisch.

## Projektinformationen

- Projekt: NutriTrackWeb
- Projekttyp: INF-Labor (Klasse 3. IT INF – Labor, Gruppe 3APP1)
- Entwicklerin: Stefany Castelão dos Santos
- Version: 1.0 (Abgabe November 2026)

## Funktionen

**Ernährungsberaterin (Admin):**
- Registrierungen prüfen: freigeben, ablehnen oder weitere Informationen anfordern
- Kundenliste mit Suche und Filter, Kundenprofil mit Gewichtsverlauf
- Termine verwalten und Umterminungsanfragen entscheiden
- Dokumente hochladen und ansehen (nur PDF)
- Mahlzeitenfotos als Galerie ansehen
- Nachrichten beantworten
- Zahlungen verwalten (offen, bezahlt, storniert)
- Profil, Farben und Zahlungsinformationen einstellen

**Kunde (User):**
- Registrieren mit Messwerten und Bluttest (PDF)
- Eigene Termine ansehen und eine Umterminung anfragen
- Dokumente und Ernährungspläne ansehen
- Mahlzeitenfotos hochladen und löschen
- Der Beraterin Fragen senden (höchstens 5 offene Unterhaltungen)
- Eigene Messwerte und Zahlungen ansehen, Profil und Passwort ändern

## Technologien

- PHP (ohne Framework) mit PDO
- MariaDB / MySQL
- HTML und CSS
- XAMPP (lokale Entwicklung) und InfinityFree (Online-Demo)
- GitHub Projects (Kanban) und Figma / HTML-Prototypen

## Sicherheit

- Passwörter werden mit `password_hash` gespeichert (kein Klartext)
- Zwei Rollen (Admin, User); jede Seite prüft die Rolle serverseitig in der Datenbank
- Prepared Statements gegen SQL-Injection, `htmlspecialchars` gegen XSS
- CSRF-Token in allen Formularen
- Sitzung läuft nach 30 Minuten ohne Aktivität ab
- Uploads: Prüfung des echten Dateiinhalts, zufällige Dateinamen, geschützte Ordner (Zugriff nur über `download.php` und `view_image.php`)
- HTTPS

## Datenbank

10 Tabellen mit Fremdschlüsseln, in dritter Normalform: `users`, `clients`, `nutritionist_profile`, `client_measurements`, `appointments`, `reschedule_requests`, `documents`, `meal_photos`, `messages`, `payments`.

## Repository-Struktur

| Ordner | Inhalt |
|---|---|
| `01_Projekt/nutritrackweb` | Quellcode der Anwendung |
| `02_Datenbank` | SQL-Dump und ER-Diagramm |
| `03_Dokumentation` | Pflichtenheft, Benutzer- und technische Dokumentation, Arbeitsaufwand |
| `06_Prototypen` | UI/UX-Prototypen |

## Entwicklung und Veröffentlichung

**Entwicklung (Schulprojekt):** Das Projekt wurde lokal mit XAMPP entwickelt, getestet und in der Schule präsentiert. Die folgenden Schritte beschreiben diese lokale Version.

1. Ordner `01_Projekt/nutritrackweb` nach `C:\xampp\htdocs\` kopieren.
2. Apache und MySQL im XAMPP Control Panel starten.
3. In phpMyAdmin den Dump `02_Datenbank/nutritrackweb_database_dump.sql` importieren (legt die Datenbank `mydb` an).
4. In `config.php` die Zugangsdaten der eigenen Datenbank eintragen.
5. `https://localhost/nutritrackweb/` im Browser öffnen.

**Veröffentlichung (Online-Demo):** Nach der Abgabe wurde dieselbe Anwendung bei InfinityFree veröffentlicht.
- Dateien per FTP in den Ordner `htdocs` hochgeladen.
- Eigene Datenbank beim Anbieter angelegt, der Dump per phpMyAdmin importiert (ohne die Zeilen `CREATE DATABASE` und `USE`).
- Nur die `config.php` wurde angepasst. Sie enthält die Zugangsdaten des Anbieters und liegt deshalb nicht auf GitHub.
- HTTPS ist aktiv (Zertifikat des Anbieters).

Hinweis: Die Beispielbilder und Beispiel-PDFs der Demo sind nicht im Repository enthalten.

## Projektstatus

Das Projekt ist abgeschlossen, wurde als Schulprojekt abgegeben (Note: Sehr gut) und ist als Online-Demo erreichbar.

Mögliche spätere Erweiterungen sind im Pflichtenheft beschrieben, z. B. E-Mail-Benachrichtigungen, Deaktivieren von Kunden und ein kostenpflichtiges Hosting (Hostinger).
