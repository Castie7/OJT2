-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 29, 2026 at 02:28 AM
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
-- Table structure for table `researches`
--

CREATE TABLE `researches` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) NOT NULL,
  `abstract` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(100) DEFAULT 'System Admin',
  `is_archived` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `researches`
--

INSERT INTO `researches` (`id`, `title`, `author`, `abstract`, `created_at`, `file_path`, `uploaded_by`, `is_archived`, `is_approved`, `updated_at`, `status`) VALUES
(1, 'Sample Study: Sweet Potato', 'Dr. Test', 'This is a test entry from the database.', '2026-01-28 10:56:54', NULL, 'System Admin', 0, 1, NULL, 'approved'),
(2, 'asd', 'asd', '', '2026-01-28 10:59:48', NULL, 'System Admin', 1, 1, NULL, 'pending'),
(3, 'asdf', 'asdf', 'asdf', '2026-01-28 11:40:03', '1769571603_c059111ca0f1607b6f62.pdf', 'Super Admin', 1, 1, NULL, 'pending'),
(4, 'well', 'asd', 'asd', '2026-01-28 12:10:24', NULL, 'Super Admin', 1, 1, NULL, 'pending'),
(5, 'asdffffff', 'asdffff', 'asdffff', '2026-01-28 15:19:49', '1769584815_25ecc2d593570c132cdd.pdf', 'Super Admin', 1, 1, NULL, 'pending'),
(6, 'asdww', 'awww', 'wwww', '2026-01-28 15:55:16', '1769586916_81d3b1cf049180b3caae.pdf', 'Super Admin', 1, 1, NULL, 'pending'),
(7, 'aaa', 'aaa', 'aaa', '2026-01-28 16:21:34', NULL, 'Super Admin', 1, 1, NULL, 'pending'),
(8, 'asd', 'asda', 'sd', '2026-01-28 16:56:33', NULL, 'Super Admin', 0, 0, NULL, 'approved'),
(9, 'aaaaaaaaaaaaaaaaaa', 'asdsssssssss', 'asdsssssssss', '2026-01-28 17:01:31', '1769590958_d521b0e18faa385ace8d.pdf', 'Super Admin', 0, 0, NULL, 'approved'),
(10, 'Testing', 'Ni Syak', 'Well', '2026-01-29 09:03:33', '1769648613_29d0059c809502ffd950.pdf', 'Super Admin', 0, 0, NULL, 'approved'),
(11, 'aaaa', 'aaa', 'aaaaaaa', '2026-01-29 09:15:40', '1769649340_40cfd2df09e8eecba214.pdf', 'Super Admin', 0, 0, NULL, 'rejected');

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
(1, 10, 0, 'Admin', 'admin', 'well', '2026-01-29 09:14:08'),
(2, 10, 0, 'Admin', 'admin', 'af', '2026-01-29 09:14:45');

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
  `auth_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `auth_token`) VALUES
(3, 'Super Admin', 'admin@bsu.edu.ph', '$2y$10$DhW4Q7.TzFDP8EjZ8aGS4O8TeaFSfe2hNVoEAUK78DuW4781I1dMy', '2026-01-28 11:20:35', 'cc82f57c4be4110df971f70f88d5466029d080f70590192f5ddc4f1038f72b58');

--
-- Indexes for dumped tables
--

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `researches`
--
ALTER TABLE `researches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `research_comments`
--
ALTER TABLE `research_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
