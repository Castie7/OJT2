-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 10, 2026 at 02:34 AM
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
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `user_name` varchar(255) NOT NULL DEFAULT 'Guest',
  `role` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_name`, `role`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 3, 'Super Admin', 'admin', 'LOGOUT', 'User logged out', '192.168.60.93', '2026-02-10 01:33:14');

-- --------------------------------------------------------

--
-- Table structure for table `ci_sessions`
--

CREATE TABLE `ci_sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `data` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ci_sessions`
--

INSERT INTO `ci_sessions` (`id`, `ip_address`, `timestamp`, `data`) VALUES
('ci_session:56addc7f6f7d1527961562901a5f1482', '192.168.60.70', '2026-02-06 02:08:43', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334333732333b),
('ci_session:bc2fbd6c346c8672d4fcc3c4fcf5f5e3', '192.168.60.70', '2026-02-06 02:13:44', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334333733393b),
('ci_session:235e7cef722672af9694718691b116e3', '192.168.60.70', '2026-02-06 02:13:55', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334343033353b),
('ci_session:7f75fc905ae684f971db32705c5802d1', '192.168.60.70', '2026-02-06 02:17:14', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334343136313b),
('ci_session:e2658f2eaebb9ae4537c1f195ef6ea09', '192.168.60.70', '2026-02-06 02:20:17', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334343234313b),
('ci_session:22170d6ec513dface8b4254207fee226', '192.168.60.70', '2026-02-06 02:26:23', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334343738333b),
('ci_session:658d2ac860fd0183661151d2d6137f6e', '192.168.60.70', '2026-02-06 02:26:42', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334343738333b),
('ci_session:944882f17a5206dd360bf1dd817bf4b4', '192.168.60.70', '2026-02-06 03:15:17', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334373731373b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:0c0ea57074b64214aa2ec3d8e2c6a738', '192.168.60.70', '2026-02-06 03:27:55', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334383437353b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:f8c35ff74426b28fe31347dd0e88f360', '192.168.60.70', '2026-02-06 03:22:34', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334383135343b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:b9a1d25bf5760ca9471510b8eb899aff', '192.168.60.69', '2026-02-06 03:29:58', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334383537343b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:d423a4fffe1c878eedd5c7b3d18d548a', '192.168.60.70', '2026-02-06 03:50:29', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334393832393b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:7ec0c4a6d35965218fc34396dd9cb7b8', '192.168.60.69', '2026-02-06 03:35:41', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334383739313b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:de9f9493fd997806f5ffd3319f58fea0', '192.168.60.70', '2026-02-06 03:51:08', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303334393832393b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:bfbb4b6c2d33cb30960a4de7ed05f6db', '192.168.60.70', '2026-02-06 08:51:51', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303336373931313b),
('ci_session:d518ad6cc28ada54ff0e9bde6045b920', '192.168.60.70', '2026-02-06 08:52:36', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303336373931313b69647c733a313a2236223b6e616d657c733a31353a224b61726c6f204a696d204c65616e6f223b656d61696c7c733a32343a226b61726c6f6a696d6c65616e6f406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:7a50513dc6f72207aaa1faf9ec5f3e96', '192.168.60.12', '2026-02-09 00:47:11', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303539373733383b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:fafae2a18d078549957b7acd580cd564', '192.168.60.12', '2026-02-09 06:36:01', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303631383835313b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:9bd433bccd4d1199a500a48705d8daa8', '192.168.60.93', '2026-02-10 00:39:31', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303638333937313b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:f0bbb509e55b6c24fe2336c9185a35b1', '192.168.60.93', '2026-02-10 00:44:39', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303638343237393b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:fdba4cbe66fb64cf500113bed865f262', '192.168.60.93', '2026-02-10 00:44:52', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303638343237393b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:a3ef8c2169cc2599c51049c10204e110', '192.168.60.93', '2026-02-10 00:53:40', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303638343832303b),
('ci_session:abbd8ad24276a069799289fb74bee62d', '192.168.60.93', '2026-02-10 00:55:17', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303638343839363b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:1af48ebbbef001841a9f3cc0d3bf0d62', '192.168.60.93', '2026-02-10 01:06:28', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303638353538383b),
('ci_session:bb4af578b4976130e6a85e350defde30', '192.168.60.93', '2026-02-10 01:22:47', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303638363536373b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b),
('ci_session:5af22095ee47de5842daec7f50ccf457', '192.168.60.93', '2026-02-10 01:29:25', 0x5f5f63695f6c6173745f726567656e65726174657c693a313737303638363936353b69647c733a313a2233223b6e616d657c733a31313a2253757065722041646d696e223b656d61696c7c733a31363a2261646d696e406273752e6564752e7068223b726f6c657c733a353a2261646d696e223b69734c6f67676564496e7c623a313b);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES
(1, '2026-02-10-000001', 'App\\Database\\Migrations\\CreateActivityLogsTable', 'default', 'App', 1770686455, 1);

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
(8, 5, 3, 6, 'Admin commented: youre gae...', 1, '2026-02-04 17:57:25'),
(9, 5, 3, 6, '⚠️ Your research \'Karlo Gae von Hamo\' was returned for revision.', 1, '2026-02-04 17:57:31'),
(10, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:57:36'),
(11, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:59:06'),
(12, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:59:19'),
(13, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:59:24'),
(14, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:59:28'),
(15, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:59:45'),
(16, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 17:59:53'),
(17, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 21:29:22'),
(18, 3, 5, 6, 'New comment by Baron Ticag', 1, '2026-02-04 21:29:35'),
(19, 3, 5, 7, 'New comment by Baron Ticag', 1, '2026-02-04 21:30:15'),
(20, 3, 5, 7, 'New comment by Baron Ticag', 1, '2026-02-04 21:30:21'),
(21, 3, 5, 7, 'New comment by Baron Ticag', 1, '2026-02-04 21:30:23'),
(22, 3, 5, 7, 'New comment by Baron Ticag', 1, '2026-02-04 21:30:25'),
(23, 3, 5, 7, 'New comment by Baron Ticag', 1, '2026-02-04 21:31:05'),
(24, 3, 5, 7, 'New comment by Baron Ticag', 1, '2026-02-04 21:31:21'),
(25, 5, 6, 7, 'Admin commented: test...', 1, '2026-02-04 21:31:51'),
(26, 3, 5, 7, 'New comment by Baron Ticag', 1, '2026-02-04 21:31:59'),
(27, 3, 5, 7, 'New comment by Baron Ticag', 1, '2026-02-04 21:32:12'),
(28, 3, 6, 4, 'Admin commented: check...', 1, '2026-02-04 21:52:17'),
(29, 3, 5, 8, 'New comment by Baron Ticag', 1, '2026-02-04 21:52:48'),
(30, 6, 5, 8, 'New comment by Baron Ticag', 1, '2026-02-04 21:52:48'),
(31, 5, 6, 8, 'Admin commented: check...', 0, '2026-02-04 21:53:02'),
(32, 3, 3, 4, '⚠️ Your research \'Suppanikka\' was returned for revision.', 1, '2026-02-05 19:50:39');

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
(4, 'Suppanikka', 'jdew', '', NULL, '2026-02-03 08:41:04', NULL, '3', 0, 0, '2026-02-06 03:50:39', 'rejected', NULL, NULL, '2026-02-06 03:50:39', '2026-02-03 08:41:47', NULL),
(5, 'suppanikka', 'adsc', 'Sweet Potato', NULL, '2026-02-03 08:43:02', NULL, '4', 0, 0, '2026-02-04 02:43:39', 'rejected', NULL, NULL, '2026-02-04 02:43:39', NULL, NULL),
(6, 'Karlo Gae von Hamo', 'Karla', 'Sweet Potato', NULL, '2026-02-05 01:56:40', NULL, '5', 0, 0, '2026-02-05 01:57:31', 'rejected', NULL, NULL, '2026-02-05 01:57:31', NULL, NULL),
(7, 'safgds', 'DSFDSAFD', '', NULL, '2026-02-05 05:29:56', NULL, '5', 0, 0, '2026-02-05 05:29:56', 'pending', NULL, NULL, NULL, NULL, NULL),
(8, 'Karlo Von Gae', 'Karlo ', '', NULL, '2026-02-05 05:34:33', NULL, '5', 0, 0, '2026-02-05 05:34:33', 'pending', NULL, NULL, NULL, NULL, NULL);

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
(9, 6, 5, 'Baron Ticag', 'user', 'NO U GAE', '2026-02-05 09:57:36'),
(10, 6, 5, 'Baron Ticag', 'user', 'U GAE why you reject', '2026-02-05 09:59:06'),
(11, 6, 5, 'Baron Ticag', 'user', 'hey jewboy fuck you ', '2026-02-05 09:59:19'),
(12, 6, 5, 'Baron Ticag', 'user', 'you nigger', '2026-02-05 09:59:24'),
(13, 6, 5, 'Baron Ticag', 'user', 'nigga whst', '2026-02-05 09:59:28'),
(14, 6, 5, 'Baron Ticag', 'user', '*what', '2026-02-05 09:59:45'),
(15, 6, 5, 'Baron Ticag', 'user', 'nigger please', '2026-02-05 09:59:53'),
(16, 6, 5, 'Baron Ticag', 'user', 'jo', '2026-02-05 13:29:22'),
(17, 6, 5, 'Baron Ticag', 'user', 'ndskl', '2026-02-05 13:29:35'),
(18, 7, 5, 'Baron Ticag', 'user', 'KAR', '2026-02-05 13:30:15'),
(19, 7, 5, 'Baron Ticag', 'user', 'K', '2026-02-05 13:30:21'),
(20, 7, 5, 'Baron Ticag', 'user', 'K', '2026-02-05 13:30:23'),
(21, 7, 5, 'Baron Ticag', 'user', 'K', '2026-02-05 13:30:25'),
(22, 7, 5, 'Baron Ticag', 'user', 'K', '2026-02-05 13:31:05'),
(23, 7, 5, 'Baron Ticag', 'user', 'K', '2026-02-05 13:31:21'),
(24, 7, 6, 'Karlo Jim Leano', 'admin', 'test', '2026-02-05 13:31:51'),
(25, 7, 5, 'Baron Ticag', 'user', 'J', '2026-02-05 13:31:59'),
(26, 7, 5, 'Baron Ticag', 'user', 'K', '2026-02-05 13:32:12'),
(27, 4, 6, 'Karlo Jim Leano', 'admin', 'check', '2026-02-05 13:52:17'),
(28, 8, 5, 'Baron Ticag', 'user', 'fkshcxb', '2026-02-05 13:52:48'),
(29, 8, 6, 'Karlo Jim Leano', 'admin', 'check', '2026-02-05 13:53:02');

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
(6, 6, 'Research Paper', '2026-02-05', 'BAMA', 'Joe BAMA', 'IDK', 'USADGYUSAJ', 'KAR', 'DSAGEAS', 'Good', ''),
(7, 7, 'Journal', '2026-02-05', '', '', '', '', '', '', 'Good', ''),
(8, 8, 'Research Paper', '2026-02-05', '', '', '', '', '', '', 'Good', '');

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
(3, 'Super Admin', 'admin@bsu.edu.ph', '$2y$10$JtJ7rRhD70H86k35fuhb/OhbSpj4DfiBQby3y2W5cuGNZ77Rhrt36', '2026-01-28 11:20:35', '48df225040b5d42219660148c1837022c09629c90b2cd14a2970b97f925ea82b', 'admin', '2026-02-06 02:05:21'),
(4, 'Juan Dela Cruz', 'researcher@bsu.edu.ph', '$2y$10$DhW4Q7.TzFDP8EjZ8aGS4O8TeaFSfe2hNVoEAUK78DuW4781I1dMy', '2026-01-29 09:42:12', '85743bf5da6493d1945038039b66e6d2682a60ee5e5f0071a4562f8846d67a7d', 'user', '2026-02-05 01:55:51'),
(5, 'Baron Ticag', 'baronticag@bsu.edu.ph', '$2y$10$KpR/DnjYvfyAs20X.5yTCum.CZ5a685w/t1Ts0Pfog6QGVAzBrKi.', '2026-02-05 01:52:41', NULL, 'user', '2026-02-05 08:19:14'),
(6, 'Karlo Jim Leano', 'karlojimleano@bsu.edu.ph', '$2y$10$bZ5otWfWXqkDkvP0uNLwXecOQaqTqeQ.ytf6qLoMMobtC6.yfNtme', '2026-02-05 05:28:19', NULL, 'admin', '2026-02-05 08:40:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`);

--
-- Indexes for table `ci_sessions`
--
ALTER TABLE `ci_sessions`
  ADD KEY `ci_sessions_timestamp` (`timestamp`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `researches`
--
ALTER TABLE `researches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `research_comments`
--
ALTER TABLE `research_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `research_details`
--
ALTER TABLE `research_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
