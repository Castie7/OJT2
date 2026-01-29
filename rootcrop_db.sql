-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 29, 2026 at 09:29 AM
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

INSERT INTO `researches` (`id`, `title`, `author`, `abstract`, `created_at`, `file_path`, `uploaded_by`, `is_archived`, `is_approved`, `updated_at`, `status`, `start_date`, `deadline_date`, `rejected_at`, `approved_at`, `archived_at`) VALUES
(1, 'Sample Study: Sweet Potato', 'Dr. Test', 'This is a test entry from the database.', '2026-01-28 10:56:54', NULL, 'System Admin', 0, 1, NULL, 'approved', NULL, NULL, NULL, NULL, NULL),
(2, 'asd', 'asd', '', '2026-01-28 10:59:48', NULL, 'System Admin', 1, 1, NULL, '', NULL, NULL, NULL, NULL, NULL),
(3, 'asdf', 'asdf', 'asdf', '2026-01-28 11:40:03', '1769571603_c059111ca0f1607b6f62.pdf', 'Super Admin', 1, 1, NULL, '', NULL, NULL, NULL, NULL, NULL),
(4, 'well', 'asd', 'asd', '2026-01-28 12:10:24', NULL, 'Super Admin', 1, 1, NULL, '', NULL, NULL, NULL, NULL, NULL),
(5, 'asdffffff', 'asdffff', 'asdffff', '2026-01-28 15:19:49', '1769584815_25ecc2d593570c132cdd.pdf', 'Super Admin', 1, 1, NULL, '', NULL, NULL, NULL, NULL, NULL),
(6, 'asdww', 'awww', 'wwww', '2026-01-28 15:55:16', '1769586916_81d3b1cf049180b3caae.pdf', 'Super Admin', 1, 1, NULL, '', NULL, NULL, NULL, NULL, NULL),
(7, 'aaa', 'aaa', 'aaa', '2026-01-28 16:21:34', NULL, 'Super Admin', 1, 1, NULL, '', NULL, NULL, NULL, NULL, NULL),
(8, 'asd', 'asda', 'sd', '2026-01-28 16:56:33', NULL, 'Super Admin', 1, 0, NULL, '', NULL, NULL, NULL, NULL, NULL),
(9, 'aaaaaaaaaaaaaaaaaa', 'asdsssssssss', 'asdsssssssss', '2026-01-28 17:01:31', '1769590958_d521b0e18faa385ace8d.pdf', 'Super Admin', 1, 0, NULL, '', NULL, NULL, NULL, NULL, NULL),
(10, 'Testing', 'Ni Syak', 'Well', '2026-01-29 09:03:33', '1769648613_29d0059c809502ffd950.pdf', 'Super Admin', 1, 0, NULL, '', NULL, NULL, NULL, NULL, NULL),
(11, 'aaaa', 'aaa', 'aaaaaaa', '2026-01-29 09:15:40', '1769649340_40cfd2df09e8eecba214.pdf', 'Super Admin', 0, 0, '2026-01-29 07:36:37', 'approved', NULL, NULL, NULL, '2026-01-29 07:36:37', NULL),
(12, 'Juan Only', 'Juan Only', 'asdf', '2026-01-29 09:54:54', '1769651694_9dae4ade6cd00f5373f2.pdf', '4', 0, 0, '2026-01-29 03:38:56', 'approved', NULL, NULL, NULL, '2026-01-29 03:38:56', NULL),
(13, 'Juan Only', 'Juannnnnn', 'wells', '2026-01-29 10:04:11', '1769652251_1f429d95e61f453e0d55.pdf', '4', 0, 0, '2026-01-29 07:01:18', 'approved', NULL, NULL, NULL, '2026-01-29 07:01:18', NULL),
(14, 'was', 'sa', 'sa', '2026-01-29 10:46:16', NULL, '3', 0, 0, '2026-01-29 07:01:21', 'approved', NULL, NULL, NULL, '2026-01-29 07:01:21', NULL),
(15, 'Research 1', 'Well Welll', 'well', '2026-01-29 10:47:01', '1769654821_fdc71c00cb08ba34a124.pdf', '3', 0, 0, '2026-01-29 07:01:24', 'approved', '2026-01-30', '2026-01-30', NULL, '2026-01-29 07:01:24', NULL),
(16, 'With Deadline', 'Dead', 'ohh no', '2026-01-29 03:08:08', '1769656088_df98624efd52ec73006d.pdf', '3', 0, 0, '2026-01-29 03:40:06', 'approved', '2026-01-29', '2026-01-31', NULL, '2026-01-29 03:40:06', NULL),
(17, 'Title', 'Author', 'asd', '2026-01-29 03:44:39', '1769658279_f16e7f7cb6b149f30c41.png', '3', 0, 0, '2026-01-29 05:47:37', 'approved', '2026-01-29', '2026-01-28', NULL, '2026-01-29 05:47:37', NULL),
(18, 'asd', 'asd', 'qww', '2026-01-29 03:53:45', '1769658825_16445d98b484fdf69672.pdf', '3', 0, 0, '2026-01-29 07:39:16', 'approved', '1111-11-11', '2026-12-12', NULL, '2026-01-29 07:39:16', NULL),
(19, '1aaaaa', 'shrek', 'we', '2026-01-29 07:01:06', '1769670066_17acee231a0ba30e770a.pdf', '3', 0, 0, '2026-01-29 07:01:15', 'approved', '2026-01-29', '2026-02-07', NULL, '2026-01-29 07:01:15', NULL),
(20, 'more', 'more', 'asdf', '2026-01-29 07:01:56', '1769670116_13c5f42eefb5bea2dc7e.pdf', '3', 0, 0, '2026-01-29 07:02:52', 'approved', '2026-01-29', '2026-02-07', NULL, '2026-01-29 07:02:52', NULL),
(21, 'asdfgg', 'asdgasd', 'asdff', '2026-01-29 07:02:17', '1769670137_810b6486307d8b187650.pdf', '3', 0, 0, '2026-01-29 07:02:55', 'approved', '2026-01-29', '2026-02-07', NULL, '2026-01-29 07:02:55', NULL),
(22, 'last', 'nigga', 'asd', '2026-01-29 07:02:46', '1769670166_84b4b455631c1180b595.pdf', '3', 0, 0, '2026-01-29 07:02:59', 'approved', '2026-01-29', '2026-02-13', NULL, '2026-01-29 07:02:59', NULL),
(23, 'Testing AProval', 'lets see', 'asd', '2026-01-29 07:42:15', '1769672535_64db70a1c697dc5ca806.pdf', '3', 0, 0, '2026-01-29 07:42:29', 'approved', '2025-12-03', '2026-01-29', NULL, '2026-01-29 07:42:29', NULL),
(24, 'Last', 'Well DOne', 'speed', '2026-01-29 07:51:26', '1769673086_7f25e26c11dfcedb0d49.pdf', '3', 0, 0, '2026-01-29 07:51:31', 'approved', '2026-01-29', '2026-01-29', NULL, NULL, NULL),
(25, 'Research 12', 'Well Done', 'a', '2026-01-29 07:57:48', '1769673468_fb462e66f2184b1537b4.pdf', '4', 1, 0, '2026-01-29 08:28:47', 'pending', '2026-01-29', '2026-01-30', NULL, NULL, NULL),
(26, 'asd', 'asd', 'asd', '2026-01-29 08:10:44', '1769674244_17a97f3db3d28f2da36b.pdf', '4', 1, 0, '2026-01-29 08:28:49', 'pending', '2026-01-21', '2026-01-29', NULL, NULL, NULL);

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
(2, 10, 0, 'Admin', 'admin', 'af', '2026-01-29 09:14:45'),
(3, 12, 3, 'Super Admin', 'admin', 'what if', '2026-01-29 10:11:31'),
(4, 12, 4, 'Juan Researcher', 'user', 'what iffff', '2026-01-29 10:12:30'),
(5, 12, 4, 'Juan Researcher', 'user', 'Sir What iff', '2026-01-29 10:17:19'),
(6, 15, 3, 'Super Admin', 'user', 'hello', '2026-01-29 11:12:10'),
(7, 15, 3, 'Super Admin', 'user', 'hello', '2026-01-29 11:12:15'),
(8, 15, 3, 'Super Admin', 'user', 'hello', '2026-01-29 11:12:15'),
(9, 15, 3, 'Super Admin', 'user', 'hello', '2026-01-29 11:12:16'),
(10, 12, 4, 'Juan Researcher', 'user', 'l', '2026-01-29 11:30:13'),
(11, 12, 4, 'Juan Researcher', 'user', 'wazuup', '2026-01-29 11:33:23'),
(12, 12, 4, 'Juan Researcher', 'user', 'test', '2026-01-29 11:33:34'),
(13, 12, 4, 'Juan Researcher', 'user', 'tes', '2026-01-29 11:33:38'),
(14, 15, 3, 'Super Admin', 'user', 'yes', '2026-01-29 11:34:30'),
(15, 15, 3, 'Super Admin', 'admin', 'test', '2026-01-29 11:37:46');

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
(3, 'Super Admin', 'admin@bsu.edu.ph', '$2y$10$DhW4Q7.TzFDP8EjZ8aGS4O8TeaFSfe2hNVoEAUK78DuW4781I1dMy', '2026-01-28 11:20:35', NULL, 'admin'),
(4, 'Juan Researcher', 'researcher@bsu.edu.ph', '$2y$10$DhW4Q7.TzFDP8EjZ8aGS4O8TeaFSfe2hNVoEAUK78DuW4781I1dMy', '2026-01-29 09:42:12', NULL, 'user');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `research_comments`
--
ALTER TABLE `research_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
