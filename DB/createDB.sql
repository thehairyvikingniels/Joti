-- phpMyAdmin SQL Dump
-- version 5.2.1
-- Generation Time: Sep 02, 2026
-- Server version: 10.6.18-MariaDB-0ubuntu0.22.04.1
-- PHP Version: 8.2.22

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jotihunt`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Auto`
--

CREATE TABLE IF NOT EXISTS `Auto` (
  `kenteken` char(8) NOT NULL,
  `eigenaar` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `aangemaakt_op` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`kenteken`),
  KEY `eigenaar` (`eigenaar`),
  CONSTRAINT `Auto_ibfk_1` FOREIGN KEY (`eigenaar`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Auto_Bijrijders`
--

CREATE TABLE IF NOT EXISTS `Auto_Bijrijders` (
  `auto` char(8) NOT NULL,
  `gebruiker_id` int(11) NOT NULL,
  `boarding_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_driver` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`gebruiker_id`),
  KEY `Auto_Bijrijders_ibfk_3` (`auto`),
  CONSTRAINT `Auto_Bijrijders_ibfk_2` FOREIGN KEY (`gebruiker_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Auto_Bijrijders_ibfk_3` FOREIGN KEY (`auto`) REFERENCES `Auto` (`kenteken`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Auto_Positie`
--

CREATE TABLE IF NOT EXISTS `Auto_Positie` (
  `auto` char(8) NOT NULL,
  `gebruiker_id` int(11) DEFAULT NULL,
  `datumtijd` datetime NOT NULL,
  `lat` float(8,6) NOT NULL,
  `lon` float(9,6) NOT NULL,
  PRIMARY KEY (`auto`,`datumtijd`),
  KEY `gebruiker_id` (`gebruiker_id`),
  CONSTRAINT `Auto_Positie_ibfk_2` FOREIGN KEY (`gebruiker_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Auto_Positie_ibfk_3` FOREIGN KEY (`auto`) REFERENCES `Auto` (`kenteken`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Auto_Toewijzingen`
--

CREATE TABLE IF NOT EXISTS `Auto_Toewijzingen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auto` char(8) NOT NULL,
  `type` varchar(32) NOT NULL,
  `referentie_id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `auto` (`auto`),
  CONSTRAINT `Auto_Toewijzingen_ibfk_1` FOREIGN KEY (`auto`) REFERENCES `Auto` (`kenteken`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Cronjobs`
--

CREATE TABLE IF NOT EXISTS `Cronjobs` (
  `name` varchar(16) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `URL` varchar(1024) NOT NULL,
  `description` varchar(2048) NOT NULL,
  `interval` int(4) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Cronjobs` (`name`, `enabled`, `URL`, `description`, `interval`) VALUES
('auto_backup', 1, 'cron/backup.php', 'Automatische database- en mediaback-up met getrapte bewaartermijn', 3600)
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`), `interval` = VALUES(`interval`);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Cronlogs`
--

CREATE TABLE IF NOT EXISTS `Cronlogs` (
  `name` varchar(16) NOT NULL,
  `exec_time` datetime NOT NULL,
  `exec_length` int(11) DEFAULT NULL,
  `exec_stat` int(11) DEFAULT NULL,
  `exec_output` text DEFAULT NULL,
  PRIMARY KEY (`name`,`exec_time`),
  UNIQUE KEY `name` (`name`,`exec_time`),
  CONSTRAINT `Cronlogs_ibfk_1` FOREIGN KEY (`name`) REFERENCES `Cronjobs` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Gebruikers`
--

CREATE TABLE IF NOT EXISTS `Gebruikers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voornaam` varchar(16) NOT NULL,
  `achternaam` varchar(32) NOT NULL,
  `email` varchar(128) NOT NULL,
  `telegram_chat_id` varchar(64) DEFAULT NULL,
  `telegram_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `gebruikersnaam` varchar(128) NOT NULL,
  `wachtwoord` varchar(256) NOT NULL,
  `phone` varchar(12) NOT NULL,
  `api` varchar(16) NOT NULL,
  `priv` int(1) NOT NULL,
  `lat` decimal(8,6) DEFAULT NULL,
  `lon` decimal(9,6) DEFAULT NULL,
  `geotijd` datetime DEFAULT NULL,
  `first_login` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `theme` varchar(32) NOT NULL DEFAULT 'light',
  `profile_picture` varchar(255) DEFAULT NULL,
  `notification_prefs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_prefs`)),
  `telegram_link_code` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_telegram_link_code` (`telegram_link_code`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Gebruikers_Tokens`
--

CREATE TABLE IF NOT EXISTS `Gebruikers_Tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `selector` varchar(64) NOT NULL,
  `hashed_validator` varchar(64) NOT NULL,
  `expiry` datetime NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_selector` (`selector`),
  KEY `fk_gebruikers_tokens_user` (`user_id`),
  CONSTRAINT `fk_gebruikers_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Groepen`
--

CREATE TABLE IF NOT EXISTS `Groepen` (
  `id` int(11) NOT NULL,
  `naam` varchar(255) NOT NULL,
  `lat` decimal(7,5) NOT NULL,
  `lon` decimal(8,5) NOT NULL,
  `deelgebied` varchar(255) NOT NULL,
  `gebruikersnaam` varchar(255) NOT NULL,
  `straat` varchar(255) NOT NULL,
  `huisnummer` varchar(255) NOT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `plaats` varchar(255) NOT NULL,
  `url` varchar(512) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Hints`
--

CREATE TABLE IF NOT EXISTS `Hints` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Kiosk_Accounts`
--

CREATE TABLE IF NOT EXISTS `Kiosk_Accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auth_token` varchar(64) NOT NULL,
  `naam` varchar(255) NOT NULL,
  `doel_pagina` varchar(255) NOT NULL,
  `rechten` int(11) NOT NULL DEFAULT 0,
  `ip_whitelist` varchar(255) DEFAULT NULL,
  `refresh_interval` int(11) NOT NULL DEFAULT 0,
  `laatst_gezien` datetime DEFAULT NULL,
  `laatst_ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auth_token` (`auth_token`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Nieuws`
--

CREATE TABLE IF NOT EXISTS `Nieuws` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Notification_Backlog`
--

CREATE TABLE IF NOT EXISTS `Notification_Backlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `url` varchar(1024) NOT NULL DEFAULT '/',
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `initiator` varchar(255) NOT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `send_before` datetime DEFAULT NULL,
  `sent` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_backlog_user_id` (`user_id`),
  CONSTRAINT `notification_backlog_user_id` FOREIGN KEY (`user_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=270 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Notification_Subscriptions`
--

CREATE TABLE IF NOT EXISTS `Notification_Subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `device_name` varchar(255) DEFAULT 'Onbekend apparaat',
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notification_subscriptions_user_id` FOREIGN KEY (`user_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Opdrachten`
--

CREATE TABLE IF NOT EXISTS `Opdrachten` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL,
  `eindtijd` datetime NOT NULL,
  `maxpunten` int(11) NOT NULL,
  `ingestuurd_op` datetime DEFAULT NULL,
  `toegekende_punten` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Punten`
--

CREATE TABLE IF NOT EXISTS `Punten` (
  `groep_id` int(11) NOT NULL,
  `hunts` int(11) DEFAULT 0,
  `tegenhunts` int(11) DEFAULT NULL,
  `opdrachten` int(11) DEFAULT NULL,
  `foto_opdrachten` int(11) DEFAULT NULL,
  `hints` int(11) DEFAULT 0,
  `strafpunten` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `bonus` int(11) DEFAULT 0,
  PRIMARY KEY (`groep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Site_Instellingen`
--

CREATE TABLE IF NOT EXISTS `Site_Instellingen` (
  `Instelling` varchar(32) NOT NULL,
  `Waarde` varchar(128) NOT NULL,
  `Omschrijving` varchar(128) NOT NULL,
  PRIMARY KEY (`Instelling`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Tegenhunt_Breadcrumbs`
--

CREATE TABLE IF NOT EXISTS `Tegenhunt_Breadcrumbs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lat` decimal(10,8) NOT NULL,
  `lon` decimal(11,8) NOT NULL,
  `accuracy` float NOT NULL DEFAULT 10,
  `recorded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_session_time` (`session_id`,`recorded_at`),
  CONSTRAINT `Tegenhunt_Breadcrumbs_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `Tegenhunt_Sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `Tegenhunt_Breadcrumbs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Tegenhunt_Sessions`
--

CREATE TABLE IF NOT EXISTS `Tegenhunt_Sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `wind_direction` varchar(10) NOT NULL,
  `compass_degrees` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('active','found','expired','cancelled') NOT NULL DEFAULT 'active',
  `found_by_user_id` int(11) DEFAULT NULL,
  `found_code` varchar(50) DEFAULT NULL,
  `found_lat` decimal(10,8) DEFAULT NULL,
  `found_lon` decimal(11,8) DEFAULT NULL,
  `found_photo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `found_by_user_id` (`found_by_user_id`),
  CONSTRAINT `Tegenhunt_Sessions_ibfk_1` FOREIGN KEY (`found_by_user_id`) REFERENCES `Gebruikers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Telegram_Messages`
--

CREATE TABLE IF NOT EXISTS `Telegram_Messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_message_id` bigint(20) DEFAULT NULL,
  `sender` varchar(64) NOT NULL DEFAULT '@Jotihunt_bot',
  `message_text` text NOT NULL,
  `parsed_type` varchar(32) NOT NULL DEFAULT 'unknown',
  `parsed_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `forwarded_to` text DEFAULT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_telegram_msg_id` (`telegram_message_id`),
  KEY `idx_parsed_type` (`parsed_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Toewijzingen`
--

CREATE TABLE IF NOT EXISTS `Toewijzingen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gebruiker_id` int(11) NOT NULL,
  `type` varchar(32) NOT NULL,
  `referentie_id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_toewijzingen_gebruiker` (`gebruiker_id`),
  CONSTRAINT `fk_toewijzingen_gebruiker` FOREIGN KEY (`gebruiker_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=229 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Voslocaties`
--

CREATE TABLE IF NOT EXISTS `Voslocaties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ingestuurd_op` datetime DEFAULT NULL,
  `type` enum('Hint','Hunt','Spot','Voorspelling','Tegenhunt') NOT NULL,
  `deelgebied` varchar(8) NOT NULL,
  `ingeleverd` tinyint(4) NOT NULL DEFAULT 0,
  `ingeleverd_door` int(11) DEFAULT NULL,
  `coordinaat_x` decimal(8,6) NOT NULL,
  `coordinaat_y` decimal(9,6) NOT NULL,
  `code` varchar(128) DEFAULT NULL,
  `opmerking` varchar(128) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `toegekende_punten` int(11) DEFAULT 0,
  `status` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ingeleverd_door` (`ingeleverd_door`),
  CONSTRAINT `Voslocaties_ibfk_1` FOREIGN KEY (`ingeleverd_door`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Voslog`
--

CREATE TABLE IF NOT EXISTS `Voslog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datumtijd` datetime NOT NULL,
  `vos` varchar(32) NOT NULL,
  `status` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_voslog_vos_datumtijd` (`vos`,`datumtijd`)
) ENGINE=InnoDB AUTO_INCREMENT=277 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Whiteboard_Categorieen`
--

CREATE TABLE IF NOT EXISTS `Whiteboard_Categorieen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `naam` varchar(64) NOT NULL,
  `kleur` varchar(16) NOT NULL DEFAULT '#3B82F6',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Gegevens voor tabel `Site_Instellingen`
--

INSERT IGNORE INTO `Site_Instellingen` (`Instelling`, `Waarde`, `Omschrijving`) VALUES
('FOX_NAMES', 'Alpha,Bravo,Charlie,Delta,Echo,Foxtrot,Golf,Hotel,Oscar', 'Comma separated list of fox names'),
('FOX_COLORS', '#9829FF,#36D12B,#FF8A00,#F5F02C,#FFA12E,#F52E2B,#FF6F6F,#00BFA5,#333333', 'Comma separated list of fox colors'),
('API_KEY_FIREBASE', 'jouw_firebase_api_key_hier', 'The API key for the Firebase configuration.'),
('API_KEY_MAPBOX', 'jouw_mapbox_api_key_hier', 'The public access token for Mapbox.'),
('FOXEXCHANGE_ENDDATE', '2025-10-12T23:15:00+02:00', 'Datumtijd van het einde van de vossenwissel in ISO 8601 met offset formaat. Bepaald ook wanneer de 2e speelhelft begint.'),
('FOXEXCHANGE_STARTDATE', '2025-10-11T22:45:00+02:00', 'Datumtijd van de begin van de vossenwissel in ISO 8601 met offset formaat. Bepaald ook wanneer de 2e speelhelft begint.'),
('GAME_ENDDATE', '2025-10-12T12:00:00+02:00', 'Datumtijd van het einde van de Jotihunt in ISO 8601 met offset formaat.'),
('GAME_STARTDATE', '2025-10-11T10:00:00+02:00', 'Datumtijd van de start van de Jotihunt in ISO 8601 met offset formaat.'),
('GROUP_LOGO_LARGE_URL', 'media/geusje_bevosd.png', 'A local or external URL for the group logo.'),
('GROUP_LOGO_SMALL_URL', 'media/geusje_bevosd.png', 'A local or external URL for the group logo. '),
('GROUP_ID', '0', 'The ID of the scout group using this website. Used for point calculations.'),
('GROUP_URL', 'https://example.com/', 'The URL of the scout group using this website. '),
('JOTIHUNT_CREDENTIALS',	'{\"username\":\"example@domain.com\",\"password\":\"example_password\"}',	'Credentials of the official Jotihunt website in JSON format.'),
('REMEMBER_ME_HOURS', '72', 'Aantal uur dat een normale gebruiker (priv 0-1) ingelogd kan blijven. 0 = uitgeschakeld.'),
('REMEMBER_ME_HOURS_ADMIN', '24', 'Aantal uur dat een admin (priv 2-3) ingelogd kan blijven. 0 = uitgeschakeld.'),
('HAPPY_HOUR', '0', 'Active status flag for Jotihunt Happy Hour indicating double points for fox locations (0 or 1).'),
('TELEGRAM_API_ID', '0', 'Telegram API App ID from my.telegram.org for MTProto listener authentication.'),
('TELEGRAM_API_HASH', 'placeholder_api_hash', 'Telegram API App Hash from my.telegram.org for MTProto listener authentication.'),
('TELEGRAM_GROUP_CHAT_ID', '-1001234567890', 'Central Telegram group or channel chat ID where all game messages are forwarded.'),
('TELEGRAM_FORWARD_MODE', 'forward', 'Delivery mode for subscriber messages: forward (keeps bot header) or relay (clean text).'),
('TELEGRAM_INGEST_SECRET', 'placeholder_secret', 'Shared secret token required to authorize incoming Webhook and MTProto ingest requests.'),
('TELEGRAM_REGISTRATION_CODE', 'placeholder_code', 'Latest registration token scraped from the Jotihunt portal used to pair with @Jotihunt_bot.'),
('TELEGRAM_BOT_TOKEN', '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ', 'Optional Telegram Bot API token from @BotFather used for sending outbound broadcast notifications.');

--
-- Standaardgroep voor schone installatie
--
INSERT INTO `Groepen` (`id`, `naam`, `lat`, `lon`, `deelgebied`, `gebruikersnaam`, `straat`, `huisnummer`, `postal_code`, `plaats`, `url`) VALUES
(1, 'Mijn Scoutinggroep', 52.00000, 5.90000, 'Alpha', 'placeholder', 'Dorpsstraat', '1', '1234 AB', 'Arnhem', '')
ON DUPLICATE KEY UPDATE `id` = `id`;

--
-- Standaardpunten voor de standaardgroep
--
INSERT INTO `Punten` (`groep_id`, `hunts`, `tegenhunts`, `opdrachten`, `foto_opdrachten`, `hints`, `strafpunten`, `bonus`) VALUES
(1, 0, 0, 0, 0, 0, 0, 0)
ON DUPLICATE KEY UPDATE `groep_id` = `groep_id`;


COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
