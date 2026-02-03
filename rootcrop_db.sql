-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 03, 2026 at 09:33 AM
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
(3, 'Golden Roots Issue No. 06', 'Hilda L. Quindara and Esther T. Botangen', 'Sweet Potato', NULL, '2026-02-03 08:29:57', NULL, '1', 0, 0, '2026-02-03 08:29:57', 'approved', NULL, NULL, NULL, NULL, NULL);

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
(3, 3, 'Journal', '0000-00-00', 'Golden Roots Issue No. 6', 'Northern Philippines Root Crops Research and Training Center - BSU', '24 Pages', 'ISSN 1656-5444', 'Sweetpotato Recipes for Better Health', '6b', 'Good', '');

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
  `auth_token` varchar(64) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `auth_token`, `role`) VALUES
(3, 'Super Admin', 'admin@bsu.edu.ph', '$2y$10$JtJ7rRhD70H86k35fuhb/OhbSpj4DfiBQby3y2W5cuGNZ77Rhrt36', '2026-01-28 11:20:35', 'e762f6ffeb734afa7e9fdf1998c155dccdb422b7d6003c316f2cfc8bf5fa1786', 'admin'),
(4, 'Juan Dela Cruz', 'researcher@bsu.edu.ph', '$2y$10$DhW4Q7.TzFDP8EjZ8aGS4O8TeaFSfe2hNVoEAUK78DuW4781I1dMy', '2026-01-29 09:42:12', NULL, 'user');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `researches`
--
ALTER TABLE `researches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `research_comments`
--
ALTER TABLE `research_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `research_details`
--
ALTER TABLE `research_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
