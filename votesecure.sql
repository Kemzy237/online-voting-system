-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 18, 2026 at 11:36 AM
-- Server version: 8.4.7
-- PHP Version: 8.5.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `votesecure`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `location` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Douala, Cameroon',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `contact`, `email`, `password`, `role`, `location`) VALUES
(1, 'kemzy', '+237 653 426 838', 'kemzywil@gmail.com', '$2y$12$tOnmcH1YLbthk9aAXSOURu/ITMTWqAVAGHYaqXEnn78wx6ZqTsTg.', 'admin', 'Douala, Cameroon');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
CREATE TABLE IF NOT EXISTS `candidates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `voter_id` int NOT NULL,
  `election_id` int NOT NULL,
  `party_affiliation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biography` text COLLATE utf8mb4_unicode_ci,
  `campaign_statement` text COLLATE utf8mb4_unicode_ci,
  `profile_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','disqualified','withdrawn') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `voter_id` (`voter_id`),
  KEY `election_id` (`election_id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `voter_id`, `election_id`, `party_affiliation`, `biography`, `campaign_statement`, `profile_image`, `status`, `created_at`) VALUES
(1, 1, 1, 'Unity Party', 'Third-year law student, former class rep.', 'I will fight for better campus Wi-Fi and affordable cafeteria prices.', 'uploads/candidates/arnold.jpg', 'approved', '2026-11-18 10:37:34'),
(2, 2, 1, 'Progress Alliance', 'Engineering student and debate club president.', 'Transparency in student funds and 24/7 library access.', 'uploads/candidates/brenda.jpg', 'approved', '2026-11-18 10:37:34'),
(3, 3, 1, 'Independent', 'Business student and entrepreneur.', 'More startup grants for students and career fairs.', 'uploads/candidates/claude.jpg', 'approved', '2026-11-18 10:37:34'),
(4, 4, 1, 'Youth Movement', 'First-year student, community organizer.', 'Better mental health support on campus.', NULL, 'withdrawn', '2026-11-18 10:37:34'),
(5, 5, 2, 'Science Forward', 'PhD candidate in Physics.', 'More research funding and lab equipment.', 'uploads/candidates/eric.jpg', 'approved', '2026-11-18 10:37:34'),
(6, 7, 2, 'Student Voice', 'Masters student in Computer Science.', 'Bridge the gap between students and faculty administration.', 'uploads/candidates/georges.jpg', 'approved', '2026-11-18 10:37:34'),
(7, 9, 2, 'Independent', 'Biology student.', 'Free lab coats for all first-year science students.', NULL, 'pending', '2026-11-18 10:37:34'),
(8, 2, 3, 'Community First', 'Long-time resident and volunteer.', 'Safer streets and better waste management.', NULL, 'approved', '2026-11-18 10:37:34'),
(9, 10, 3, 'Independent', 'Retired teacher.', 'More youth recreational facilities.', NULL, 'pending', '2026-11-18 10:37:34'),
(10, 3, 6, 'Unity Party', 'Finance professional.', 'Balanced budget and transparent accounting.', NULL, 'disqualified', '2026-11-18 10:37:34'),
(11, 2, 7, 'Team Blue', '', 'For success', NULL, 'approved', '2026-11-18 11:16:27'),
(12, 10, 7, 'Team Red', '', 'For Hardwork', NULL, 'approved', '2026-11-18 11:17:31');

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

DROP TABLE IF EXISTS `elections`;
CREATE TABLE IF NOT EXISTS `elections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `status` enum('draft','upcoming','active','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`id`, `title`, `description`, `start_datetime`, `end_datetime`, `status`, `created_at`) VALUES
(1, 'Student Union President 2025', 'Annual election for the Student Union President of the University of Douala.', '2025-05-01 08:00:00', '2025-05-05 18:00:00', 'completed', '2026-11-18 10:37:07'),
(2, 'Faculty Representative Election', 'Election for Faculty of Science representative.', '2025-04-10 08:00:00', '2025-04-12 17:00:00', 'completed', '2026-11-18 10:37:07'),
(3, 'Community Council Chairman', 'Local community council chairman election.', '2025-06-15 07:00:00', '2025-06-15 19:00:00', 'completed', '2026-11-18 10:37:07'),
(4, 'Sports Club Captain', 'Election for captain of the university sports club.', '2025-03-01 09:00:00', '2025-03-02 18:00:00', 'completed', '2026-11-18 10:37:07'),
(5, 'Alumni Association Board', 'Election for the alumni association board members.', '2025-07-20 08:00:00', '2025-07-25 18:00:00', 'draft', '2026-11-18 10:37:07'),
(6, 'Treasurer Election (Cancelled)', 'Treasurer election that was cancelled due to low nominations.', '2025-02-10 08:00:00', '2025-02-11 18:00:00', 'cancelled', '2026-11-18 10:37:07'),
(7, 'Class Delegate Election', 'Election for the class delegate', '2026-11-18 12:15:00', '2026-11-21 00:00:00', 'active', '2026-11-18 11:03:54'),
(8, 'School Prefect', 'Election to determine the school prefect', '2026-11-21 00:00:00', '2026-11-25 00:00:00', 'upcoming', '2026-11-18 11:05:23');

-- --------------------------------------------------------

--
-- Table structure for table `voters`
--

DROP TABLE IF EXISTS `voters`;
CREATE TABLE IF NOT EXISTS `voters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date NOT NULL,
  `contact` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','verified','suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `voters`
--

INSERT INTO `voters` (`id`, `full_name`, `email`, `dob`, `contact`, `address`, `status`, `created_at`, `password`) VALUES
(1, 'Arnold Nkeng', 'arnold@gmail.com', '1995-03-14', '+237 670 123 456', 'Bonapriso, Douala, Cameroon', 'verified', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(2, 'Brenda Fotso', 'brenda.fotso@gmail.com', '1992-07-22', '+237 671 234 567', 'Bastos, Yaoundé, Cameroon', 'verified', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(3, 'Claude Mballa', 'claude.mballa@yahoo.com', '1988-11-05', '+237 672 345 678', 'Akwa, Douala, Cameroon', 'verified', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(4, 'Divine Ngono', 'divine.ngono@outlook.com', '1999-01-30', '+237 673 456 789', 'Buea, South West, Cameroon', 'pending', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(5, 'Eric Tchoumi', 'eric.tchoumi@gmail.com', '1985-06-18', '+237 674 567 890', 'Bamenda, North West, Cameroon', 'verified', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(6, 'Francine Abena', 'francine.abena@gmail.com', '2000-09-09', '+237 675 678 901', 'Molyko, Buea, Cameroon', 'suspended', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(7, 'Georges Kamdem', 'georges.kamdem@gmail.com', '1990-12-25', '+237 676 789 012', 'Bafoussam, West, Cameroon', 'verified', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(8, 'Hortense Ndongo', 'hortense.ndongo@gmail.com', '1997-04-11', '+237 677 890 123', 'Bonaberi, Douala, Cameroon', 'pending', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(9, 'Ismael Bello', 'ismael.bello@gmail.com', '1993-08-02', '+237 678 901 234', 'Garoua, North, Cameroon', 'verified', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(10, 'Josiane Mbarga', 'josiane.mbarga@gmail.com', '1991-02-19', '+237 679 012 345', 'Ebolowa, South, Cameroon', 'verified', '2026-11-18 10:36:56', '$2y$10$Q9Qm2Xc6VzY7sH3wJ1kL9uK8pR5tN4bM7vC2dE6fG8hA0jB1lD3e'),
(11, 'Kemzy Wil', 'kemzywil@gmail.com', '2008-11-06', '654987321', 'Douala', 'verified', '2026-11-18 11:00:44', '$2y$12$la28Zvb9W5DroKF.S4ti6OqxNNcqPEFI0iTLyFshhupsk3Q6Bj0PW');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
CREATE TABLE IF NOT EXISTS `votes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `voter_id` int NOT NULL,
  `election_id` int NOT NULL,
  `candidate_id` int NOT NULL,
  `vote_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','verified','invalid','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `voter_id` (`voter_id`),
  KEY `election_id` (`election_id`),
  KEY `candidate_id` (`candidate_id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `voter_id`, `election_id`, `candidate_id`, `vote_timestamp`, `status`, `verified_at`) VALUES
(1, 1, 1, 1, '2026-11-18 10:37:51', 'verified', '2025-05-01 08:15:00'),
(2, 2, 1, 2, '2026-11-18 10:37:51', 'verified', '2025-05-01 09:02:00'),
(3, 3, 1, 3, '2026-11-18 10:37:51', 'verified', '2025-05-01 10:45:00'),
(4, 4, 1, 1, '2026-11-18 10:37:51', 'pending', NULL),
(5, 5, 1, 2, '2026-11-18 10:37:51', 'verified', '2025-05-02 07:30:00'),
(6, 6, 1, 3, '2026-11-18 10:37:51', 'invalid', NULL),
(7, 7, 1, 1, '2026-11-18 10:37:51', 'verified', '2025-05-02 13:20:00'),
(8, 8, 1, 2, '2026-11-18 10:37:51', 'pending', NULL),
(9, 5, 2, 5, '2026-11-18 10:37:51', 'verified', '2025-04-10 08:00:00'),
(10, 7, 2, 6, '2026-11-18 10:37:51', 'verified', '2025-04-10 08:30:00'),
(11, 9, 2, 5, '2026-11-18 10:37:51', 'verified', '2025-04-10 09:15:00'),
(12, 10, 2, 6, '2026-11-18 10:37:51', 'verified', '2025-04-11 10:00:00'),
(13, 1, 2, 5, '2026-11-18 10:37:51', 'rejected', NULL),
(14, 1, 6, 10, '2026-11-18 10:37:51', 'invalid', NULL),
(15, 2, 6, 10, '2026-11-18 10:37:51', 'invalid', NULL),
(16, 11, 7, 11, '2026-11-18 11:26:08', 'pending', NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
