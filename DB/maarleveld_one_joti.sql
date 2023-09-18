-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: maarleveld.one.mysql.service.one.com:3306
-- Generation Time: Sep 18, 2023 at 09:37 AM
-- Server version: 10.6.14-MariaDB-1:10.6.14+maria~ubu2204
-- PHP Version: 8.1.2-1ubuntu2.14

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
-- Table structure for table `Auto`
--

CREATE TABLE `Auto` (
  `id` int(11) NOT NULL,
  `kenteken` varchar(8) NOT NULL,
  `eigenaar` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Auto_Bijrijders`
--

CREATE TABLE `Auto_Bijrijders` (
  `auto_id` int(11) NOT NULL,
  `gebruiker_id` int(11) NOT NULL,
  `instaptijd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Auto_Positie`
--

CREATE TABLE `Auto_Positie` (
  `auto_id` int(11) NOT NULL,
  `gebruiker_id` int(11) NOT NULL,
  `datumtijd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lat` float(8,6) NOT NULL,
  `lon` float(9,6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Gebruikers`
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
  `lat` decimal(8,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `geotijd` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Groepen`
--

CREATE TABLE `Groepen` (
  `id` int(11) NOT NULL,
  `naam` varchar(64) NOT NULL,
  `lat` decimal(8,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
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
-- Table structure for table `Hints`
--

CREATE TABLE `Hints` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Nieuws`
--

CREATE TABLE `Nieuws` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Opdrachten`
--

CREATE TABLE `Opdrachten` (
  `id` int(11) NOT NULL,
  `titel` text NOT NULL,
  `inhoud` text NOT NULL,
  `datum` datetime NOT NULL,
  `eindtijd` datetime NOT NULL,
  `maxpunten` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Punten`
--

CREATE TABLE `Punten` (
  `groep_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Voslocaties`
--

CREATE TABLE `Voslocaties` (
  `id` int(11) NOT NULL,
  `ingestuurd_op` datetime NOT NULL,
  `type` varchar(16) NOT NULL,
  `deelgebied` varchar(8) NOT NULL,
  `ingeleverd` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Voslog`
--

CREATE TABLE `Voslog` (
  `id` int(11) NOT NULL,
  `datumtijd` datetime NOT NULL,
  `alpha` tinyint(4) NOT NULL,
  `bravo` tinyint(4) NOT NULL,
  `charlie` tinyint(4) NOT NULL,
  `delta` tinyint(4) NOT NULL,
  `echo` tinyint(4) NOT NULL,
  `foxtrot` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Auto`
--
ALTER TABLE `Auto`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Auto_Bijrijders`
--
ALTER TABLE `Auto_Bijrijders`
  ADD KEY `auto_id` (`auto_id`),
  ADD KEY `gebruiker_id` (`gebruiker_id`);

--
-- Indexes for table `Auto_Positie`
--
ALTER TABLE `Auto_Positie`
  ADD KEY `auto_id` (`auto_id`),
  ADD KEY `gebruiker_id` (`gebruiker_id`);

--
-- Indexes for table `Gebruikers`
--
ALTER TABLE `Gebruikers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Groepen`
--
ALTER TABLE `Groepen`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Hints`
--
ALTER TABLE `Hints`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Nieuws`
--
ALTER TABLE `Nieuws`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Opdrachten`
--
ALTER TABLE `Opdrachten`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Voslog`
--
ALTER TABLE `Voslog`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Auto`
--
ALTER TABLE `Auto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Gebruikers`
--
ALTER TABLE `Gebruikers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Voslog`
--
ALTER TABLE `Voslog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Auto_Bijrijders`
--
ALTER TABLE `Auto_Bijrijders`
  ADD CONSTRAINT `Auto_Bijrijders_ibfk_1` FOREIGN KEY (`auto_id`) REFERENCES `Auto` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Auto_Bijrijders_ibfk_2` FOREIGN KEY (`gebruiker_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Auto_Positie`
--
ALTER TABLE `Auto_Positie`
  ADD CONSTRAINT `Auto_Positie_ibfk_1` FOREIGN KEY (`auto_id`) REFERENCES `Auto` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Auto_Positie_ibfk_2` FOREIGN KEY (`gebruiker_id`) REFERENCES `Gebruikers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
