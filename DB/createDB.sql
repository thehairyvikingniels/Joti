-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: maarleveld.one.mysql.service.one.com:3306
-- Gegenereerd op: 15 okt 2025 om 13:20
-- Serverversie: 10.6.23-MariaDB-ubu2204
-- PHP-versie: 8.1.2-1ubuntu2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `maarleveld_one_joti`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Auto`
--

CREATE TABLE `Auto` (
  `kenteken` char(8) NOT NULL,
  `eigenaar` int(11) NOT NULL,
  `aangemaakt_op` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Auto_Bijrijders`
--

CREATE TABLE `Auto_Bijrijders` (
  `auto` char(8) NOT NULL,
  `gebruiker_id` int(11) NOT NULL,
  `instaptijd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Auto_Positie`
--

CREATE TABLE `Auto_Positie` (
  `auto` char(8) NOT NULL,
  `gebruiker_id` int(11) NOT NULL,
  `datumtijd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lat` float(8,6) NOT NULL,
  `lon` float(9,6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Cronjobs`
--

CREATE TABLE `Cronjobs` (
  `name` varchar(16) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `URL` varchar(1024) NOT NULL,
  `description` varchar(2048) NOT NULL,
  `interval` int(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Cronlogs`
--

CREATE TABLE `Cronlogs` (
  `name` varchar(16) NOT NULL,
  `exec_time` datetime NOT NULL,
  `exec_length` int(11) NOT NULL,
  `exec_stat` int(11) NOT NULL,
  `exec_output` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Gebruikers`
--

CREATE TABLE `Gebruikers` (
  `id` int(11) NOT NULL,
  `voornaam` varchar(16) NOT NULL,
  `achternaam` varchar(32) NOT NULL,
  `email` varchar(128) NOT NULL,
  `gebruikersnaam` varchar(128) NOT NULL,
  `wachtwoord` varchar(256) NOT NULL,
  `telefoon` varchar(12) NOT NULL,
  `api` varchar(16) NOT NULL,
  `priv` int(1) NOT NULL,
  `lat` decimal(8,6) DEFAULT NULL,
  `lon` decimal(9,6) DEFAULT NULL,
  `geotijd` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Groepen`
--

CREATE TABLE `Groepen` (
  `id` int(11) NOT NULL,
  `naam` varchar(64) NOT NULL,
  `lat` decimal(7,5) NOT NULL,
  `lon` decimal(8,5) NOT NULL,
  `deelgebied` varchar(8) NOT NULL,
  `gebruikersnaam` varchar(5) NOT NULL,
  `straat` varchar(64) NOT NULL,
  `huisnummer` varchar(32) NOT NULL,
  `postcode` varchar(8) NOT NULL,
  `plaats` varchar(32) NOT NULL,
  `url` varchar(512) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Hints`
--

CREATE TABLE `Hints` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Nieuws`
--

CREATE TABLE `Nieuws` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Opdrachten`
--

CREATE TABLE `Opdrachten` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL,
  `eindtijd` datetime NOT NULL,
  `maxpunten` int(11) NOT NULL,
  `ingestuurd_op` timestamp NULL DEFAULT NULL COMMENT 'Timestamp of when the group submitted this assignment'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Punten`
--

CREATE TABLE `Punten` (
  `groep_id` int(11) NOT NULL,
  `hunts` int(11) DEFAULT 0,
  `tegenhunts` int(11) DEFAULT 0,
  `opdrachten` int(11) DEFAULT 0,
  `foto_opdrachten` int(11) DEFAULT 0,
  `hints` int(11) DEFAULT 0,
  `strafpunten` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Site_Instellingen`
--

CREATE TABLE `Site_Instellingen` (
  `Instelling` varchar(32) NOT NULL,
  `Waarde` varchar(128) NOT NULL,
  `Omschrijving` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Site_Instellingen`
--

INSERT INTO `Site_Instellingen` (`Instelling`, `Waarde`, `Omschrijving`) VALUES
('API_KEY_FIREBASE', 'jouw_firebase_api_key_hier', 'The API key for the Firebase configuration.'),
('API_KEY_MAPBOX', 'jouw_mapbox_api_key_hier', 'The public access token for Mapbox.'),
('FOXEXCHANGE_ENDDATE', '2025-10-12T23:15:00+02:00', 'Datumtijd van het einde van de vossenwissel in ISO 8601 met offset formaat. Bepaald ook wanneer de 2e speelhelft begint.'),
('FOXEXCHANGE_STARTDATE', '2025-10-11T22:45:00+02:00', 'Datumtijd van de begin van de vossenwissel in ISO 8601 met offset formaat. Bepaald ook wanneer de 2e speelhelft begint.'),
('GAME_ENDDATE', '2025-10-12T12:00:00+02:00', 'Datumtijd van het einde van de Jotihunt in ISO 8601 met offset formaat.'),
('GAME_STARTDATE', '2025-10-11T10:00:00+02:00', 'Datumtijd van de start van de Jotihunt in ISO 8601 met offset formaat.'),
('GROUP_LOGO_LARGE_URL', 'media/geusje_bevosd.png', 'A local or external URL for the group logo.'),
('GROUP_LOGO_SMALL_URL', 'media/geusje_bevosd.png', 'A local or external URL for the group logo. '),
('GROUP_ID', '0', 'The ID of the scout group using this website. Used for point calculations.'),
('GROUP_URL', 'https://example.com/', 'The URL of the scout group using this website. ');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Voslocaties`
--

CREATE TABLE `Voslocaties` (
  `id` int(11) NOT NULL,
  `ingestuurd_op` datetime DEFAULT NULL,
  `type` enum('Hint','Hunt','Spot','Voorspelling') NOT NULL,
  `deelgebied` varchar(8) NOT NULL,
  `ingeleverd` tinyint(4) NOT NULL DEFAULT 0,
  `ingeleverd_door` int(11) DEFAULT NULL,
  `coordinaat_x` decimal(8,6) NOT NULL,
  `coordinaat_y` decimal(9,6) NOT NULL,
  `code` varchar(32) DEFAULT NULL,
  `opmerking` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Voslog`
--

CREATE TABLE `Voslog` (
  `id` int(11) NOT NULL,
  `datumtijd` datetime NOT NULL,
  `alpha` tinyint(4) NOT NULL,
  `bravo` tinyint(4) NOT NULL,
  `charlie` tinyint(4) NOT NULL,
  `delta` tinyint(4) NOT NULL,
  `echo` tinyint(4) NOT NULL,
  `foxtrot` tinyint(4) NOT NULL,
  `golf` tinyint(4) NOT NULL,
  `hotel` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `Auto`
--
ALTER TABLE `Auto`
  ADD PRIMARY KEY (`kenteken`),
  ADD KEY `eigenaar` (`eigenaar`);

--
-- Indexen voor tabel `Auto_Bijrijders`
--
ALTER TABLE `Auto_Bijrijders`
  ADD PRIMARY KEY (`gebruiker_id`),
  ADD KEY `Auto_Bijrijders_ibfk_3` (`auto`);

--
-- Indexen voor tabel `Auto_Positie`
--
ALTER TABLE `Auto_Positie`
  ADD PRIMARY KEY (`auto`,`datumtijd`),
  ADD KEY `gebruiker_id` (`gebruiker_id`);

--
-- Indexen voor tabel `Cronjobs`
--
ALTER TABLE `Cronjobs`
  ADD PRIMARY KEY (`name`);

--
-- Indexen voor tabel `Cronlogs`
--
ALTER TABLE `Cronlogs`
  ADD PRIMARY KEY (`name`,`exec_time`),
  ADD UNIQUE KEY `name` (`name`,`exec_time`);

--
-- Indexen voor tabel `Gebruikers`
--
ALTER TABLE `Gebruikers`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Groepen`
--
ALTER TABLE `Groepen`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Hints`
--
ALTER TABLE `Hints`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Nieuws`
--
ALTER TABLE `Nieuws`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Opdrachten`
--
ALTER TABLE `Opdrachten`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Punten`
--
ALTER TABLE `Punten`
  ADD PRIMARY KEY (`groep_id`);

--
-- Indexen voor tabel `Site_Instellingen`
--
ALTER TABLE `Site_Instellingen`
  ADD PRIMARY KEY (`Instelling`);

--
-- Indexen voor tabel `Voslocaties`
--
ALTER TABLE `Voslocaties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ingeleverd_door` (`ingeleverd_door`);

--
-- Indexen voor tabel `Voslog`
--
ALTER TABLE `Voslog`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `Gebruikers`
--
ALTER TABLE `Gebruikers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `Voslocaties`
--
ALTER TABLE `Voslocaties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `Voslog`
--
ALTER TABLE `Voslog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `Auto`
--
ALTER TABLE `Auto`
  ADD CONSTRAINT `Auto_ibfk_1` FOREIGN KEY (`eigenaar`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Beperkingen voor tabel `Auto_Bijrijders`
--
ALTER TABLE `Auto_Bijrijders`
  ADD CONSTRAINT `Auto_Bijrijders_ibfk_2` FOREIGN KEY (`gebruiker_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Auto_Bijrijders_ibfk_3` FOREIGN KEY (`auto`) REFERENCES `Auto` (`kenteken`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Beperkingen voor tabel `Auto_Positie`
--
ALTER TABLE `Auto_Positie`
  ADD CONSTRAINT `Auto_Positie_ibfk_2` FOREIGN KEY (`gebruiker_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Auto_Positie_ibfk_3` FOREIGN KEY (`auto`) REFERENCES `Auto` (`kenteken`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Beperkingen voor tabel `Cronlogs`
--
ALTER TABLE `Cronlogs`
  ADD CONSTRAINT `Cronlogs_ibfk_1` FOREIGN KEY (`name`) REFERENCES `Cronjobs` (`name`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Beperkingen voor tabel `Voslocaties`
--
ALTER TABLE `Voslocaties`
  ADD CONSTRAINT `Voslocaties_ibfk_1` FOREIGN KEY (`ingeleverd_door`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
