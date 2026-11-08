-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 08. Nov 2026 um 14:03
-- Server-Version: 10.4.32-MariaDB-log
-- PHP-Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Fremdschluesselpruefung waehrend des Imports aus (damit DROP TABLE auch bei bestehender Datenbank funktioniert)
SET FOREIGN_KEY_CHECKS=0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `mydb`
--
CREATE DATABASE IF NOT EXISTS `mydb` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `mydb`;

-- Alle Tabellen zuerst loeschen, damit beim Neuanlegen keine alten Fremdschluessel mehr auf sie zeigen
DROP TABLE IF EXISTS `reschedule_requests`, `payments`, `messages`, `meal_photos`, `documents`, `client_measurements`, `appointments`, `clients`, `nutritionist_profile`, `users`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time DEFAULT NULL,
  `meeting_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','rescheduled','completed','cancelled') DEFAULT NULL,
  `clients_client_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `appointment_date`, `appointment_time`, `meeting_url`, `notes`, `status`, `clients_client_id`) VALUES
(2, '2026-11-27', '13:30:00', NULL, NULL, 'completed', 11),
(3, '2026-12-27', '21:37:00', NULL, NULL, 'completed', 7),
(4, '2026-11-04', '01:46:00', 'https://www.google.com', NULL, 'cancelled', 9),
(5, '2026-11-13', '06:12:00', 'https://www.google.com', NULL, 'cancelled', 9),
(6, '2026-11-30', '14:50:00', 'https://www.google.com', 'none', 'rescheduled', 6),
(7, '2026-11-05', '17:40:00', 'https://www.google.com', 'updates', 'scheduled', 7),
(8, '2026-11-06', '13:45:00', 'https://www.google.com', NULL, 'scheduled', 11),
(9, '2026-11-05', '11:40:00', 'https://www.google.com', NULL, 'completed', 9),
(10, '2027-02-19', '13:16:00', 'https://www.google.com', NULL, 'scheduled', 6),
(11, '2026-11-29', '16:10:00', 'https://www.google.com', 'Primeira Observacao: Diabetes alta', 'scheduled', 12),
(12, '2026-11-07', '16:20:00', NULL, NULL, 'scheduled', 8),
(13, '2026-11-08', '14:00:00', 'https://www.google.com', 'link do zoom', 'rescheduled', 7),
(14, '2026-11-10', '15:10:00', NULL, NULL, 'scheduled', 9);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `preferred_language` int(11) DEFAULT NULL,
  `goal` varchar(255) DEFAULT NULL,
  `approval_status` int(11) DEFAULT NULL,
  `registration_date` datetime DEFAULT NULL,
  `monthly_fee` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT NULL,
  `review_note` longtext DEFAULT NULL,
  `users_user_id` int(11) NOT NULL,
  `nutritionist_profile_nutritionist_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `clients`
--

INSERT INTO `clients` (`client_id`, `first_name`, `last_name`, `profile_photo_path`, `phone`, `country`, `city`, `date_of_birth`, `preferred_language`, `goal`, `approval_status`, `registration_date`, `monthly_fee`, `currency`, `review_note`, `users_user_id`, `nutritionist_profile_nutritionist_id`) VALUES
(6, 'Leonardo', 'Barbacoa', NULL, '+43 660 8863542', 'Austria', 'Sankt Pölten', '1963-02-01', 2, 'Quero ganhar massa muscular', 1, '2026-11-02 19:17:20', 50.00, 'BRL', 'Blood test', 9, 3),
(7, 'Lucas', 'Pereira', 'uploads/profile/7c7a1e47c20339bd32e3c4acf88c9e61.png', '+55 11 40028922', 'Brazil', 'Parana', '1990-08-14', 2, 'comer saudavel <3', 1, '2026-11-02 19:25:34', 90.00, 'BRL', NULL, 10, 3),
(8, 'Anna', 'Fischer', NULL, '+43 664 537 2819', 'Austria', 'Graz', '2022-03-22', 1, 'I would like to improve my eating habits, reduce stress-related eating and reach a healthier body composition.', 1, '2026-11-02 19:40:48', 30.00, 'EUR', 'New Blood test', 11, 3),
(9, 'Emily', 'Carter', 'uploads/profile/e97b07039d2f08587b286f839d96da36.png', '+1 206 555 7842', 'United States', 'NYC', '1994-08-11', 0, 'I would like to improve my nutrition, build healthier eating habits and increase my energy levels while maintaining a balanced lifestyle.', 1, '2026-11-02 19:42:08', 50.00, 'USD', NULL, 12, 3),
(10, 'Lukas', 'Schneider', NULL, '+43 676 245 8193', 'Austria', 'Salzburg', '1985-05-17', 1, 'Ich möchte mein Gewicht reduzieren, meine Essgewohnheiten verbessern und meine allgemeine Gesundheit langfristig fördern.', 2, '2026-11-02 19:43:22', NULL, NULL, NULL, 13, 3),
(11, 'Beatriz', 'Oliveira', 'uploads/profile/e981500b2c76b7951434a5854a3849f7.png', '+55 21 99876 5432', 'Portugal', 'Porto', '1996-02-11', 2, 'Quero melhorar minha alimentação, reduzir o consumo de alimentos ultraprocessados e perder peso de forma saudável e sustentável.', 1, '2026-11-02 19:45:03', 1.00, 'USD', NULL, 14, 3),
(12, 'Roger', 'Batista', 'uploads/profile/0ab124093b27c9b95377639f005c8f6b.png', '+43 552 955562', 'Brazil', 'Osasco', '1990-01-01', 2, 'Emagrecer', 1, '2026-11-03 22:23:44', 150.00, 'BRL', 'another blood test', 15, 3),
(13, 'Amanda', 'Moura', NULL, '+55 11 963247856', 'Brazil', 'Sao Paulo', '1991-02-01', 2, 'Emagrecer', 2, '2026-11-05 11:02:02', NULL, NULL, 'Nao tem disponibilidade para consulta particular.', 16, 3),
(14, 'Sophie', 'Gruber', NULL, '+43 664 1234567', 'Austria', 'Graz', '1994-09-14', 1, 'Ich möchte gesünder essen, Körperfett reduzieren und langfristig bessere Ernährungsgewohnheiten entwickeln.', 0, '2026-11-07 11:27:17', NULL, NULL, NULL, 17, 3),
(15, 'Markus', 'Steiner', NULL, '+43 660 9876543', 'Austria', 'Linz', '1998-05-22', 1, 'Ich möchte mein Gewicht reduzieren, meine Ernährungsgewohnheiten verbessern und langfristig gesünder leben.', 0, '2026-11-07 11:36:44', NULL, NULL, NULL, 18, 3),
(16, '<b>Teste</b>', '<b>Teste</b>.', NULL, '+43 660 00000000', 'Austria', 'Linz', '1990-11-05', 2, 'Emagrecer', 0, '2026-11-08 08:16:03', NULL, NULL, NULL, 19, 3),
(17, 'Amanda', 'Silva', NULL, '+43 660 00000000', 'Portugal', 'Lisboa', '2005-07-15', 2, 'Comer saudavel', 0, '2026-11-08 12:06:00', NULL, NULL, NULL, 20, 3);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `client_measurements`
--

DROP TABLE IF EXISTS `client_measurements`;
CREATE TABLE `client_measurements` (
  `measurement_id` int(11) NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `measurement_date` date DEFAULT NULL,
  `clients_client_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `client_measurements`
--

INSERT INTO `client_measurements` (`measurement_id`, `weight`, `height`, `measurement_date`, `clients_client_id`) VALUES
(7, 63.20, 190.00, '2026-11-02', 6),
(9, 74.80, 165.00, '2026-11-02', 8),
(10, 68.50, 170.00, '2026-11-02', 9),
(11, 104.70, 182.00, '2026-11-02', 10),
(12, 81.30, 167.00, '2026-11-02', 11),
(14, 104.70, 167.00, '2026-11-03', 7),
(15, 102.10, 167.00, '2026-11-03', 7),
(16, 103.50, 167.00, '2026-11-03', 7),
(17, 99.20, 170.00, '2026-11-03', 12),
(18, 70.20, 170.00, '2026-11-05', 9),
(19, 102.20, 167.00, '2026-11-05', 7),
(20, 82.00, 167.00, '2026-11-05', 11),
(21, 80.90, 167.00, '2026-11-05', 11),
(22, 63.20, 190.00, '2026-11-05', 6),
(23, 75.00, 173.00, '2026-11-05', 13),
(24, 69.50, 170.00, '2026-11-06', 9),
(25, 101.80, 167.00, '2026-11-06', 7),
(26, 74.00, 168.00, '2026-11-07', 14),
(27, 98.00, 182.00, '2026-11-07', 15),
(28, 101.80, 167.00, '2026-11-07', 7),
(29, 102.00, 167.00, '2026-11-07', 7),
(30, 102.00, 168.00, '2026-11-07', 7),
(32, 63.20, 182.00, '2026-11-08', 16),
(33, 68.00, 170.00, '2026-11-08', 17);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `documents`
--

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `document_type` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `clients_client_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `documents`
--

INSERT INTO `documents` (`document_id`, `document_type`, `file_name`, `file_path`, `uploaded_at`, `clients_client_id`) VALUES
(8, 2, 'nutrition_plan.pdf', 'uploads/documents/eeb4e3b08268c39c305db7afc9c99db2.pdf', '2026-11-05 10:27:18', 11),
(9, 2, 'nutrition_plan.pdf', 'uploads/documents/366e8c239899e102fe7f550047050e18.pdf', '2026-11-05 10:27:34', 9),
(10, 2, 'plano_alimentar.pdf', 'uploads/documents/f7508f90a1f464f152b3db286fee13e8.pdf', '2026-11-05 10:27:51', 6),
(11, 2, 'plano_alimentar.pdf', 'uploads/documents/1dbd0b34b11b6682beb82fb1a0835c1a.pdf', '2026-11-05 10:31:40', 7),
(12, 4, 'request_test.pdf', 'uploads/documents/f82412c45e2c1dd1f6437d47f733558b.pdf', '2026-11-05 10:31:58', 9),
(13, 4, 'pedido_exame.pdf', 'uploads/documents/1974f147000bcce791960e924da39a81.pdf', '2026-11-05 10:32:06', 7),
(14, 3, 'comprovante_pagamento.pdf', 'uploads/documents/49d7f6436940a6b3622412a4fb7413e3.pdf', '2026-11-05 10:33:38', 7),
(15, 1, 'exame_de_sangue.pdf', 'uploads/documents/2567c8bbbd9199da81f3c950294b260f.pdf', '2026-11-05 10:33:51', 7),
(16, 4, 'extra.pdf', 'uploads/documents/e9263ca4c199064a3d596e8729a03e0b.pdf', '2026-11-05 10:34:06', 7),
(17, 1, 'blood_test.pdf', 'uploads/documents/73767ae7c77cc1df2007d79e1acdcbfc.pdf', '2026-11-05 10:40:53', 9),
(18, 4, 'exams.pdf', 'uploads/documents/0786a81813392047ed987decefb72b67.pdf', '2026-11-05 10:41:08', 9),
(19, 1, 'exame_de_sangue.pdf', 'uploads/documents/ba80fbd46ee49a8611980db47a017050.pdf', '2026-11-05 10:43:37', 11),
(20, 4, 'request_test.pdf', 'uploads/documents/34a0dc63e8a39390d3f309c130cec87a.pdf', '2026-11-05 10:47:56', 6),
(21, 3, 'comprovante_pagamento.pdf', 'uploads/documents/f67e97ff58058f64d4a5151efba7e394.pdf', '2026-11-05 10:49:11', 6),
(22, 1, 'exame_de_sangue.pdf', 'uploads/documents/7a8c915d996001e6f3b4a834b7ada669.pdf', '2026-11-05 11:02:02', 13),
(23, 2, 'nutrition_plan.pdf', 'uploads/documents/04d6cffc42bd03592e5a52af18a4b563.pdf', '2026-11-05 13:09:37', 9),
(24, 1, 'blood_test.pdf', 'uploads/documents/e3bb11e7690edbf0410cf8437ddce63b.pdf', '2026-11-05 14:02:28', 7),
(25, 3, 'blood_test.pdf', 'uploads/documents/8e4bb959313c0289ff50550acd5ee390.pdf', '2026-11-05 14:06:51', 7),
(26, 1, 'blood_test.pdf', 'uploads/documents/143ad39e7ab0cb2074f152160760b5d5.pdf', '2026-11-07 11:27:17', 14),
(27, 1, 'blood_test.pdf', 'uploads/documents/be17062e262fd514f354f761f5ebce4f.pdf', '2026-11-07 11:36:44', 15),
(28, 4, 'blood_test.pdf', 'uploads/documents/13938a8af906829d55870d3eb0cb0f23.pdf', '2026-11-07 11:45:44', 8),
(29, 4, 'exams.pdf', 'uploads/documents/7d8cad52e388cfebacbf293149a51fbd.pdf', '2026-11-07 14:11:34', 7),
(30, 2, 'nutrition_plan.pdf', 'uploads/documents/216d97dbaa1d794effc1bae97e729863.pdf', '2026-11-07 14:20:29', 7),
(31, 3, 'comprovante_pagamento.pdf', 'uploads/documents/d0f0b9c8e7ba4937e76d358b0851b63a.pdf', '2026-11-07 15:05:21', 7),
(32, 1, 'blood_test.pdf', 'uploads/documents/d4f3876ece961d6d7a406cb0a293b002.pdf', '2026-11-08 08:16:03', 16),
(33, 1, 'exame_de_sangue.pdf', 'uploads/documents/79be30830ce431c014deb146977a6d43.pdf', '2026-11-08 12:06:00', 17);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `meal_photos`
--

DROP TABLE IF EXISTS `meal_photos`;
CREATE TABLE `meal_photos` (
  `photo_id` int(11) NOT NULL,
  `meal_type` int(11) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `clients_client_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `meal_photos`
--

INSERT INTO `meal_photos` (`photo_id`, `meal_type`, `photo_path`, `comment`, `uploaded_at`, `clients_client_id`) VALUES
(2, 4, 'uploads/meal_photos/49e84c1154f4e7d08c444a78ed4a4b18.png', 'chocolate', '2026-11-03 22:52:25', 7),
(4, 1, 'uploads/meal_photos/874fe81746b605c366c01415e5712297.png', NULL, '2026-11-03 22:56:24', 7),
(6, 1, 'uploads/meal_photos/9e99c64f7f67e65e88b0884348ae5eb5.jpg', NULL, '2026-11-05 09:56:06', 9),
(7, 2, 'uploads/meal_photos/666dca33e124a885edb82e5dbca889b6.jpg', NULL, '2026-11-05 09:56:50', 9),
(8, 3, 'uploads/meal_photos/b7b97aebf2f45e4e1c5295b0681feb1a.jpg', NULL, '2026-11-05 09:57:20', 9),
(9, 4, 'uploads/meal_photos/3c3a056f021d797889feea9c88c129e7.jpg', 'wine', '2026-11-05 09:57:54', 9),
(10, 2, 'uploads/meal_photos/be6b7137cb0fec497e4d10bb7f1a0140.jpg', 'sushi light', '2026-11-05 09:58:32', 9),
(11, 3, 'uploads/meal_photos/fc3984104bbd75f7cca3836ff6f29c89.jpg', NULL, '2026-11-05 09:58:44', 9),
(12, 2, 'uploads/meal_photos/45c1a1373e6108b342fd400e5762ee28.jpg', NULL, '2026-11-05 10:35:19', 7),
(13, 4, 'uploads/meal_photos/713a8016c9c2c505f33c235209662143.jpg', NULL, '2026-11-05 10:35:41', 7),
(14, 3, 'uploads/meal_photos/6d8a2ced84b012825c703353ad80da4d.jpg', 'light cheese', '2026-11-05 10:36:20', 7),
(15, 4, 'uploads/meal_photos/57a0926a738f0aca2150162478fb88dc.jpg', 'daddys bday', '2026-11-05 10:40:25', 9),
(16, 4, 'uploads/meal_photos/b54a52bed2c0d525127e917602b49ab4.jpg', 'festa na empresa', '2026-11-05 10:44:34', 11),
(17, 3, 'uploads/meal_photos/a4adb1eb257607ab2ddc99b961d990b0.jpg', 'Jantar com a amiga', '2026-11-05 10:45:06', 11),
(18, 2, 'uploads/meal_photos/ab9d965434591dbf0bbc7b03fb9dd8d0.jpg', 'oops, mas nao comprei batata frita', '2026-11-05 10:46:18', 11),
(20, 1, 'uploads/meal_photos/a5785514cb8d62d39967af2d35702111.jpg', NULL, '2026-11-05 10:48:51', 6),
(21, 2, 'uploads/meal_photos/8715c5bc3e35e6d936c7ac3a7ba7e789.jpg', NULL, '2026-11-05 10:49:52', 6),
(22, 1, 'uploads/meal_photos/ad503888efcba58871c25de81ce04438.jpg', NULL, '2026-11-07 14:26:21', 7),
(23, 1, 'uploads/meal_photos/c37b3513ceb0d918fe5e369b60c7ed24.png', NULL, '2026-11-07 14:27:38', 7);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message_text` longtext DEFAULT NULL,
  `nutritionist_reply` longtext DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `answered_at` datetime DEFAULT NULL,
  `clients_client_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `messages`
--

INSERT INTO `messages` (`message_id`, `subject`, `message_text`, `nutritionist_reply`, `status`, `created_at`, `answered_at`, `clients_client_id`) VALUES
(2, 'Informationen', 'Kann ich Reis Essen?', 'yes', 2, '2026-11-03 21:27:12', '2026-11-03 21:32:25', 9),
(3, 'Dessert', 'Can I eat chocolate?', 'no', 2, '2026-11-03 21:33:20', '2026-11-03 21:33:48', 7),
(4, 'Reply to the request for more information', 'I can send by email', 'ok', 2, '2026-11-03 21:42:18', '2026-11-03 21:43:03', 6),
(5, 'Reply to the request for more information', 'ok', 'tudo certo :)', 1, '2026-11-03 22:30:29', '2026-11-05 08:44:07', 12),
(6, 'Resposta ao pedido de mais informações', 'done', NULL, 0, '2026-11-05 09:48:50', NULL, 8),
(7, 'Can I talk at 14:00 today?', 'I have dayoff', NULL, 0, '2026-11-05 10:01:39', NULL, 9),
(8, 'Question about bday', 'Can I eat chocolate cake on my Birthday?', NULL, 0, '2026-11-05 10:02:15', NULL, 9),
(9, 'Brigadeiros are allowed?', 'please...I love them.', 'yes, you can', 2, '2026-11-05 10:38:17', '2026-11-06 14:59:23', 7),
(10, 'Duvida', 'Posso comer KFC?', NULL, 0, '2026-11-05 10:52:02', NULL, 6),
(11, 'Resposta ao pedido de mais informações', 'blood_test.pdf datei - Ich habe gestern bekommen und habe gerade gesendet', NULL, 0, '2026-11-07 11:45:44', NULL, 8),
(12, 'Almoco', 'Posso comer sanduiche de atum no almoco?', 'Pode', 2, '2026-11-07 13:44:37', '2026-11-07 13:49:13', 7),
(13, '1. assunto', 'teste', NULL, 0, '2026-11-07 13:56:22', NULL, 7),
(14, '2. assunto', 'teste', NULL, 0, '2026-11-07 13:56:29', NULL, 7),
(15, '3. assunto', 'teste', ':)', 1, '2026-11-07 13:56:35', '2026-11-07 13:57:54', 7),
(16, '4. assunto', 'teste', NULL, 0, '2026-11-07 13:56:42', NULL, 7),
(17, '5. assunto', 'teste', 'teste ok', 2, '2026-11-07 13:56:48', '2026-11-07 14:07:07', 7),
(18, '<script>alert(1)</script> e <b>negrito</b>.', '<script>alert(1)</script> e <b>negrito</b>.', NULL, 0, '2026-11-08 08:13:20', NULL, 7);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `nutritionist_profile`
--

DROP TABLE IF EXISTS `nutritionist_profile`;
CREATE TABLE `nutritionist_profile` (
  `nutritionist_id` int(11) NOT NULL,
  `professional_name` varchar(150) DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `bio` longtext DEFAULT NULL,
  `studies` longtext DEFAULT NULL,
  `primary_color` varchar(50) DEFAULT NULL,
  `secundary_color` varchar(50) DEFAULT NULL,
  `background_color` varchar(50) DEFAULT NULL,
  `accent_color` varchar(50) DEFAULT NULL,
  `pix_image_path` varchar(255) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `users_user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `nutritionist_profile`
--

INSERT INTO `nutritionist_profile` (`nutritionist_id`, `professional_name`, `profile_photo_path`, `logo_path`, `bio`, `studies`, `primary_color`, `secundary_color`, `background_color`, `accent_color`, `pix_image_path`, `iban`, `phone`, `website`, `instagram_url`, `linkedin_url`, `facebook_url`, `users_user_id`) VALUES
(3, 'Marianne Böhm, MSc', 'uploads/profile/7794d44ba4c55af05f82953369a1680f.png', 'uploads/profile/668ab157f5f6dbd8d7843975af68e532.png', 'Marianne Böhm is an Austrian nutrition specialist with international academic experience in Austria, the United States and Brazil. She supports clients in achieving sustainable lifestyle changes, healthy weight management and long-term nutrition goals through personalized online coaching and evidence-based nutrition guidance. Consultations are available in German, English and Portuguese.', '- BSc in Nutritional Sciences, University of Vienna, Austria\n- International Exchange Program in Human Nutrition, Cornell University, USA\n- Academic Exchange Program in Nutrition and Public Health, University of São Paulo (USP), Brazil\n- MSc in Nutritional Sciences, University of Vienna, Austria', '#1A613C', '#82BF84', '#F5F7F5', '#8083B7', 'uploads/profile/a43e7bcc27eaf61949927cd4286c9b49.png', 'AT00 0000 0000 0000 0001', '+43 660 1234567', 'https://www.nutritrackweb.com', 'https://www.instagram.com/accounts/login/', 'https://www.linkedin.com/login', 'https://www.facebook.com/login', 7);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `clients_client_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `payments`
--

INSERT INTO `payments` (`payment_id`, `period_start`, `period_end`, `status`, `due_date`, `payment_date`, `clients_client_id`) VALUES
(2, '2026-10-01', '2026-10-31', 1, '2026-11-20', '2026-11-05', 9),
(3, '2026-04-01', '2026-04-30', 1, '2026-04-01', '2026-11-07', 7),
(4, '2026-05-01', '2026-05-31', 2, '2026-05-01', NULL, 7),
(5, '2026-09-01', '2026-09-30', 1, '2026-10-01', '2026-11-05', 9),
(6, '2026-06-01', '2026-06-30', 0, '2026-06-01', NULL, 7),
(7, '2026-10-01', '2026-10-31', 2, '2026-11-01', NULL, 11),
(8, '2026-08-01', '2026-08-31', 0, '2026-11-12', NULL, 11),
(9, '2026-10-01', '2026-10-31', 2, '2026-11-20', NULL, 6),
(10, '2026-01-01', '2026-01-31', 0, '2026-11-10', NULL, 7),
(11, '2026-04-01', '2026-04-29', 0, '2026-10-15', NULL, 7);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `reschedule_requests`
--

DROP TABLE IF EXISTS `reschedule_requests`;
CREATE TABLE `reschedule_requests` (
  `request_id` int(11) NOT NULL,
  `requested_date` date DEFAULT NULL,
  `requested_time` time DEFAULT NULL,
  `request_reason` text DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `appointments_appointment_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `reschedule_requests`
--

INSERT INTO `reschedule_requests` (`request_id`, `requested_date`, `requested_time`, `request_reason`, `status`, `appointments_appointment_id`) VALUES
(1, '2026-12-27', '21:37:00', 'Dia da minha folga', 1, 3),
(2, '2026-11-05', '08:45:00', 'besser tag für mich', 2, 4),
(3, '2026-11-27', '15:06:00', 'DayOff', 2, 9),
(4, '2026-11-30', '14:50:00', 'Domingo eu nao posso', 1, 6),
(5, '2026-11-08', '14:00:00', 'Preciso reagendar pq é aniversario da minha filha', 1, 13),
(6, '2026-11-09', '17:15:00', 'is better 4 me', 2, 14);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `role`, `status`, `created_at`) VALUES
(7, 'admin-demo@ntw.at', '$2y$10$ZBMDneyX94VQ20YWgzrwauK8OVtLbmEWvcaS1Iyxq9AZmQPUJluRW', 1, 1, '2026-11-02 18:29:38'),
(9, 'barbacoa@gmx.at', '$2y$10$ZFRp.ydBW7PvkAnYt0Cnxu02lJo9L7Nk9hC3TgwZ8xgMDD3Cc3Kkq', 0, 1, '2026-11-02 19:17:20'),
(10, 'user1-demo@ntw.at', '$2y$10$bn.ampI/RNalDdQfdgEGZeWOZOtOgRd3M2JkpeUEXHvjAJWTdj3le', 0, 1, '2026-11-02 19:25:34'),
(11, 'user2-demo@ntw.at', '$2y$10$mhGDnUT3oKAAJHm5Wn5FJOE/X92MyO66fECOwTpBG0muVEP4xBidC', 0, 1, '2026-11-02 19:40:48'),
(12, 'user3-demo@ntw.at', '$2y$10$Is2wtwqvEkXP8GruHsuZveb8EzxLunqTSe27oy6orE9DV5AIYCkNG', 0, 1, '2026-11-02 19:42:08'),
(13, 'user4-demo@ntw.at', '$2y$10$u3xKFFURlT7x/Z2HymAnzOJCnnNhGn5nUconZYkqQYeHmfYDGBfii', 0, 1, '2026-11-02 19:43:22'),
(14, 'user5-demo@ntw.at', '$2y$10$V6GbjZka3XqEOkOdVDCDFe6m0lJY.4ilW5NUE5XrLyCOeVatLcBV6', 0, 1, '2026-11-02 19:45:03'),
(15, 'user7-demo@ntw.at', '$2y$10$aJBPt6PPkwejLMefFDyJU.ZGJNhxA9cBxcCC7VQyzg9KLuJqMT5B6', 0, 1, '2026-11-03 22:23:44'),
(16, 'user8-demo@ntw.at', '$2y$10$Ve5SkCjuvwexruYUW/NmkOadVQhbcuIFMB8okepMH8PyQyupgKmum', 0, 1, '2026-11-05 11:02:02'),
(17, 'user9-demo@ntw.at', '$2y$10$saOr1D8f8si6eknbAxbgO.5r3YJPYbeeCsfDvoFk2sm5oZMlN7ipW', 0, 1, '2026-11-07 11:27:17'),
(18, 'user10-demo@ntw.at', '$2y$10$g7Q2PP3kTG9QXzbik374pumfiPx.kVpvS17QD78ItTzGbMwS2AOyq', 0, 1, '2026-11-07 11:36:44'),
(19, 'user11-demo@ntw.at', '$2y$10$K4EY6xqo5cck0cp3a.uqhuLQu99jwRPwHUZi/hlTwMKn0lu/mBG6y', 0, 1, '2026-11-08 08:16:03'),
(20, 'user12-demo@ntw.at', '$2y$10$45q9cP3ZdkRuf.CrMUun4es8ckWhHjkN0pq6o6xutJ1KPs9poWUKi', 0, 1, '2026-11-08 12:06:00');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appointments_clients1_idx` (`clients_client_id`);

--
-- Indizes für die Tabelle `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD KEY `fk_clients_users_idx` (`users_user_id`),
  ADD KEY `fk_clients_nutritionist_profile1_idx` (`nutritionist_profile_nutritionist_id`);

--
-- Indizes für die Tabelle `client_measurements`
--
ALTER TABLE `client_measurements`
  ADD PRIMARY KEY (`measurement_id`),
  ADD KEY `fk_client_measurements_clients1_idx` (`clients_client_id`);

--
-- Indizes für die Tabelle `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `fk_documents_clients1_idx` (`clients_client_id`);

--
-- Indizes für die Tabelle `meal_photos`
--
ALTER TABLE `meal_photos`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `fk_meal_photos_clients1_idx` (`clients_client_id`);

--
-- Indizes für die Tabelle `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `fk_messages_clients1_idx` (`clients_client_id`);

--
-- Indizes für die Tabelle `nutritionist_profile`
--
ALTER TABLE `nutritionist_profile`
  ADD PRIMARY KEY (`nutritionist_id`),
  ADD KEY `fk_nutritionist_profile_users1_idx` (`users_user_id`);

--
-- Indizes für die Tabelle `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payments_clients1_idx` (`clients_client_id`);

--
-- Indizes für die Tabelle `reschedule_requests`
--
ALTER TABLE `reschedule_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_reschedule_requests_appointments1_idx` (`appointments_appointment_id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT für Tabelle `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT für Tabelle `client_measurements`
--
ALTER TABLE `client_measurements`
  MODIFY `measurement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT für Tabelle `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT für Tabelle `meal_photos`
--
ALTER TABLE `meal_photos`
  MODIFY `photo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT für Tabelle `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT für Tabelle `nutritionist_profile`
--
ALTER TABLE `nutritionist_profile`
  MODIFY `nutritionist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT für Tabelle `reschedule_requests`
--
ALTER TABLE `reschedule_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_clients1` FOREIGN KEY (`clients_client_id`) REFERENCES `clients` (`client_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_clients_nutritionist_profile1` FOREIGN KEY (`nutritionist_profile_nutritionist_id`) REFERENCES `nutritionist_profile` (`nutritionist_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_clients_users` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `client_measurements`
--
ALTER TABLE `client_measurements`
  ADD CONSTRAINT `fk_client_measurements_clients1` FOREIGN KEY (`clients_client_id`) REFERENCES `clients` (`client_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_documents_clients1` FOREIGN KEY (`clients_client_id`) REFERENCES `clients` (`client_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `meal_photos`
--
ALTER TABLE `meal_photos`
  ADD CONSTRAINT `fk_meal_photos_clients1` FOREIGN KEY (`clients_client_id`) REFERENCES `clients` (`client_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_clients1` FOREIGN KEY (`clients_client_id`) REFERENCES `clients` (`client_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `nutritionist_profile`
--
ALTER TABLE `nutritionist_profile`
  ADD CONSTRAINT `fk_nutritionist_profile_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_clients1` FOREIGN KEY (`clients_client_id`) REFERENCES `clients` (`client_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `reschedule_requests`
--
ALTER TABLE `reschedule_requests`
  ADD CONSTRAINT `fk_reschedule_requests_appointments1` FOREIGN KEY (`appointments_appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
