-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 05, 2026 at 02:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rootcrop_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `research_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `sender_id`, `research_id`, `message`, `is_read`, `created_at`) VALUES
(1, 3, 3, 4, '🎉 Your research \'Suppanikka\' has been APPROVED!', 1, '2026-02-03 00:41:47'),
(2, 3, 4, 5, 'New comment by Juan Dela Cruz', 1, '2026-02-03 00:43:14'),
(3, 4, 3, 5, 'Admin commented: apayya...', 1, '2026-02-03 00:44:08'),
(4, 3, 4, 5, 'New comment by Juan Dela Cruz', 1, '2026-02-03 00:44:38'),
(5, 4, 3, 5, '⚠️ Your research \'suppanikka\' was returned for revision.', 1, '2026-02-03 18:43:39'),
(6, 5, 3, 6, 'Admin commented: ivan gae...', 1, '2026-02-04 17:56:58'),
(7, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:57:10'),
(8, 5, 3, 6, 'Admin commented: youre gae...', 0, '2026-02-04 17:57:25'),
(9, 5, 3, 6, '⚠️ Your research \'Karlo Gae von Hamo\' was returned for revision.', 0, '2026-02-04 17:57:31'),
(10, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:57:36');

-- --------------------------------------------------------

--
-- Table structure for table `researches`
--

CREATE TABLE `researches` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) NOT NULL,
  `crop_variation` varchar(255) DEFAULT NULL,
  `abstract` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(100) DEFAULT 'System Admin',
  `is_archived` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `start_date` date DEFAULT NULL,
  `deadline_date` date DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `researches`
--

INSERT INTO `researches` (`id`, `title`, `author`, `crop_variation`, `abstract`, `created_at`, `file_path`, `uploaded_by`, `is_archived`, `is_approved`, `updated_at`, `status`, `start_date`, `deadline_date`, `rejected_at`, `approved_at`, `archived_at`) VALUES
(1, 'Golden Roots Issue No. 01', 'Betty T. Gayao, Jovita M. Sim, Dalen T. Meldoz', 'Sweet Potato', NULL, '2026-02-03 08:29:57', NULL, '1', 0, 0, '2026-02-03 08:29:57', 'approved', NULL, NULL, NULL, NULL, NULL),
(2, 'Golden Roots Issue No. 04', 'D. T. Meldoz and B. T. Gayao', 'Sweet Potato', NULL, '2026-02-03 08:29:57', NULL, '1', 0, 0, '2026-02-03 08:29:57', 'approved', NULL, NULL, NULL, NULL, NULL),
(3, 'Golden Roots Issue No. 06', 'Hilda L. Quindara and Esther T. Botangen', 'Sweet Potato', NULL, '2026-02-03 08:29:57', NULL, '1', 0, 0, '2026-02-03 08:29:57', 'approved', NULL, NULL, NULL, NULL, NULL),
(4, 'Suppanikka', 'jdew', '', NULL, '2026-02-03 08:41:04', NULL, '3', 0, 0, '2026-02-04 02:43:50', 'archived', NULL, NULL, NULL, '2026-02-03 08:41:47', '2026-02-04 02:43:50'),
(5, 'suppanikka', 'adsc', 'Sweet Potato', NULL, '2026-02-03 08:43:02', NULL, '4', 0, 0, '2026-02-04 02:43:39', 'rejected', NULL, NULL, '2026-02-04 02:43:39', NULL, NULL),
(6, 'Karlo Gae von Hamo', 'Karla', 'Sweet Potato', NULL, '2026-02-05 01:56:40', NULL, '5', 0, 0, '2026-02-05 01:57:31', 'rejected', NULL, NULL, '2026-02-05 01:57:31', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `research_comments`
--

CREATE TABLE `research_comments` (
  `id` int(11) NOT NULL,
  `research_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `research_comments`
--

INSERT INTO `research_comments` (`id`, `research_id`, `user_id`, `user_name`, `role`, `comment`, `created_at`) VALUES
(1, 4, 3, 'Super Admin', 'admin', 'pa approve man', '2026-02-03 16:41:35'),
(2, 5, 4, 'Juan Dela Cruz', 'user', 'hoyyyy', '2026-02-03 16:43:14'),
(3, 5, 3, 'Super Admin', 'admin', 'apayya', '2026-02-03 16:44:08'),
(4, 5, 4, 'Juan Dela Cruz', 'user', 'pa approve', '2026-02-03 16:44:38'),
(5, 4, 3, 'Super Admin', 'admin', 'joebama', '2026-02-04 10:43:30'),
(6, 6, 3, 'Super Admin', 'admin', 'ivan gae', '2026-02-05 09:56:58'),
(7, 6, 5, 'Baron Ticag', 'user', 'YOU GAE', '2026-02-05 09:57:10'),
(8, 6, 3, 'Super Admin', 'admin', 'youre gae', '2026-02-05 09:57:25'),
(9, 6, 5, 'Baron Ticag', 'user', 'NO U GAE', '2026-02-05 09:57:36');

-- --------------------------------------------------------

--
-- Table structure for table `research_details`
--

CREATE TABLE `research_details` (
  `id` int(11) NOT NULL,
  `research_id` int(11) NOT NULL,
  `knowledge_type` varchar(100) DEFAULT 'Research Paper',
  `publication_date` date DEFAULT NULL,
  `edition` varchar(50) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `physical_description` text DEFAULT NULL,
  `isbn_issn` varchar(50) DEFAULT NULL,
  `subjects` text DEFAULT NULL,
  `shelf_location` varchar(100) DEFAULT NULL,
  `item_condition` varchar(50) DEFAULT 'Good',
  `link` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `research_details`
--

INSERT INTO `research_details` (`id`, `research_id`, `knowledge_type`, `publication_date`, `edition`, `publisher`, `physical_description`, `isbn_issn`, `subjects`, `shelf_location`, `item_condition`, `link`) VALUES
(1, 1, 'Journal', '0000-00-00', 'Golden Roots Issue No. 1', 'Northern Philippines Root Crops Research and Training Center - BSU', '16 Pages', 'ISSN 1656-5444', 'Contribution of Sweetpotato to Income and Nutrition of Farming Households', '6b', 'Good', ''),
(2, 2, 'Journal', '0000-00-00', 'Golden Roots Issue No. 4', 'Northern Philippines Root Crops Research and Training Center - BSU', '50 Pages', 'ISSN 1656-5444', 'Sweetpotato Recipes', '6b', 'Good', ''),
(3, 3, 'Journal', '0000-00-00', 'Golden Roots Issue No. 6', 'Northern Philippines Root Crops Research and Training Center - BSU', '24 Pages', 'ISSN 1656-5444', 'Sweetpotato Recipes for Better Health', '6b', 'Good', ''),
(4, 4, 'Research Paper', '2026-02-10', '2nd edition', 'heloo', '23', 'ssdfs', 'sdfvsdc', 'sd', 'Good', ''),
(5, 5, 'Research Paper', '2026-02-05', '455', 'asdcjadns', '34', 'dfr', 'df', 'sdfb', 'Damaged', ''),
(6, 6, 'Research Paper', '2026-02-05', 'BAMA', 'Joe BAMA', 'IDK', 'USADGYUSAJ', 'KAR', 'DSAGEAS', 'Good', '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `auth_token` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `auth_token`, `role`, `updated_at`) VALUES
(3, 'Super Admin', 'admin@bsu.edu.ph', '$2y$10$JtJ7rRhD70H86k35fuhb/OhbSpj4DfiBQby3y2W5cuGNZ77Rhrt36', '2026-01-28 11:20:35', 'd54379cfc2c19bfba6af8395acae64725fefae0523a2d4a8316de1b18b6c43ca', 'admin', '2026-02-05 01:52:09'),
(4, 'Juan Dela Cruz', 'researcher@bsu.edu.ph', '$2y$10$DhW4Q7.TzFDP8EjZ8aGS4O8TeaFSfe2hNVoEAUK78DuW4781I1dMy', '2026-01-29 09:42:12', '85743bf5da6493d1945038039b66e6d2682a60ee5e5f0071a4562f8846d67a7d', 'user', '2026-02-05 01:55:51'),
(5, 'Baron Ticag', 'baronticag@bsu.edu.ph', '$2y$10$KpR/DnjYvfyAs20X.5yTCum.CZ5a685w/t1Ts0Pfog6QGVAzBrKi.', '2026-02-05 01:52:41', '3af779080ec7e38fb89b099afdff7ede36f417010459222ab78c2908099859a3', 'user', '2026-02-05 01:54:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `researches`
--
ALTER TABLE `researches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `research_comments`
--
ALTER TABLE `research_comments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `research_details`
--
ALTER TABLE `research_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `research_id` (`research_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `researches`
--
ALTER TABLE `researches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `research_comments`
--
ALTER TABLE `research_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `research_details`
--
ALTER TABLE `research_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `research_details`
--
ALTER TABLE `research_details`
  ADD CONSTRAINT `fk_research_details` FOREIGN KEY (`research_id`) REFERENCES `researches` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
