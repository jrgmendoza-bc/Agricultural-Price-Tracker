-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 13, 2026 at 12:42 AM
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
-- Database: `agriprice_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) NOT NULL,
  `name` varchar(80) NOT NULL COMMENT 'unique, referenced by products.category',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Grains', '2026-10-07 12:47:27', NULL, NULL),
(2, 'Vegetables', '2026-10-07 12:47:27', NULL, NULL),
(3, 'Fruits', '2026-10-07 12:47:27', NULL, NULL),
(4, 'Plantation Crops', '2026-10-07 12:47:27', NULL, NULL),
(5, 'Livestock & Poultry', '2026-10-07 12:47:27', NULL, NULL),
(6, 'Cereals', '2026-10-07 12:47:27', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` bigint(20) NOT NULL,
  `crop_id` bigint(20) NOT NULL COMMENT 'the listing this thread is about',
  `farmer_id` bigint(20) NOT NULL,
  `buyer_id` bigint(20) NOT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `crop_id`, `farmer_id`, `buyer_id`, `last_message_at`, `created_at`) VALUES
(14, 15, 2, 3, NULL, '2026-10-09 04:07:28'),
(15, 16, 2, 3, '2026-10-10 11:15:49', '2026-10-10 11:15:37');

-- --------------------------------------------------------

--
-- Table structure for table `crops`
--

CREATE TABLE `crops` (
  `id` bigint(20) NOT NULL,
  `farmer_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `unit_id` bigint(20) NOT NULL COMMENT 'unit of quantity + asking price',
  `quantity` decimal(12,2) NOT NULL,
  `asking_price` decimal(12,4) NOT NULL,
  `normalized_asking_price` decimal(12,4) NOT NULL COMMENT 'comparable to market rate',
  `status` enum('available','reserved','sold') NOT NULL DEFAULT 'available',
  `pickup_lat` decimal(10,8) DEFAULT NULL COMMENT 'null = honestly unset',
  `pickup_lng` decimal(11,8) DEFAULT NULL,
  `pickup_location_name` varchar(190) DEFAULT NULL,
  `current_stage` varchar(60) DEFAULT NULL COMMENT 'cached latest growth stage',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `crops`
--

INSERT INTO `crops` (`id`, `farmer_id`, `product_id`, `unit_id`, `quantity`, `asking_price`, `normalized_asking_price`, `status`, `pickup_lat`, `pickup_lng`, `pickup_location_name`, `current_stage`, `created_at`, `updated_at`, `deleted_at`) VALUES
(15, 2, 2, 1, 5.00, 10.0000, 10.0000, 'available', NULL, NULL, 'QA fixture', 'planted', '2026-10-09 04:07:28', '2026-10-10 07:41:36', '2026-10-09 04:07:28'),
(16, 2, 2, 1, 500.00, 150.0000, 150.0000, 'available', NULL, NULL, 'Carmen', 'available_for_pickup', '2026-10-10 09:06:49', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `crop_growth_stages`
--

CREATE TABLE `crop_growth_stages` (
  `id` bigint(20) NOT NULL,
  `crop_id` bigint(20) NOT NULL,
  `stage` varchar(60) NOT NULL COMMENT 'crop or livestock stage text',
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) NOT NULL COMMENT 'farmer who recorded it',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `crop_growth_stages`
--

INSERT INTO `crop_growth_stages` (`id`, `crop_id`, `stage`, `recorded_at`, `updated_by`, `notes`) VALUES
(52, 15, 'planted', '2026-10-09 04:07:28', 2, 'Listing created'),
(53, 16, 'available_for_pickup', '2026-10-10 09:06:49', 2, 'Listing created');

-- --------------------------------------------------------

--
-- Table structure for table `crop_sales`
--

CREATE TABLE `crop_sales` (
  `id` bigint(20) NOT NULL,
  `crop_id` bigint(20) NOT NULL,
  `quantity_sold` decimal(12,2) NOT NULL,
  `recorded_by` bigint(20) NOT NULL,
  `sold_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `identifier` varchar(190) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `identifier`, `ip_address`, `attempts`, `last_attempt_at`, `created_at`) VALUES
(32, 'doctester1', '::1', 5, '2026-10-12 19:53:29', '2026-10-12 11:52:30');

-- --------------------------------------------------------

--
-- Table structure for table `markets`
--

CREATE TABLE `markets` (
  `id` bigint(20) NOT NULL,
  `market_name` varchar(120) NOT NULL,
  `location` varchar(190) NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `markets`
--

INSERT INTO `markets` (`id`, `market_name`, `location`, `lat`, `lng`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Carmen Public Market', 'Poblacion, Carmen, North Cotabato', 7.20405000, 124.78950000, '2026-10-07 12:47:27', NULL, NULL),
(2, 'Carmen Municipal Trading Post', 'Carmen, North Cotabato', 7.21100000, 124.79200000, '2026-10-07 12:47:27', NULL, NULL),
(3, 'Pikit Roadside Market', 'Pikit, North Cotabato', 7.28300000, 124.70500000, '2026-10-07 12:47:27', '2026-10-08 02:23:36', NULL),
(4, 'Kidapawan City Public Market', 'Kidapawan City, North Cotabato', 7.01000000, 125.04500000, '2026-10-07 12:47:27', '2026-10-10 09:04:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) NOT NULL,
  `conversation_id` bigint(20) NOT NULL,
  `sender_id` bigint(20) NOT NULL,
  `body` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `body`, `read_at`, `created_at`) VALUES
(13, 15, 3, 'ako na kuha ana boss', '2026-10-12 10:12:02', '2026-10-10 11:15:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL COMMENT 'UUID generated by the application',
  `type` varchar(120) NOT NULL COMMENT 'notification class name',
  `notifiable_type` varchar(60) NOT NULL DEFAULT 'App\\Models\\User',
  `notifiable_id` bigint(20) NOT NULL COMMENT 'the user it is for',
  `data` text NOT NULL COMMENT 'JSON content',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
('010f45f27f050d48586c962a68c5ed99', 'App\\Notifications\\NewConversation', 'App\\Models\\User', 2, '{\"message\":\"A verified buyer opened a conversation about your Corn listing.\"}', '2026-10-10 11:14:41', '2026-10-09 03:03:42', '2026-10-10 11:14:41'),
('1e143319e2469c73faa70de7309b827e', 'App\\Notifications\\NewMessage', 'App\\Models\\User', 2, '{\"message\":\"New message from Mila about Corn.\"}', '2026-10-12 10:12:00', '2026-10-10 11:15:49', '2026-10-12 10:12:00'),
('2c9fa4386e71a43cace90d40b3a5a4da', 'App\\Notifications\\AccountVerification', 'App\\Models\\User', 4, '{\"message\":\"Your buyer account has been verified. You can now message farmers.\"}', NULL, '2026-10-12 11:54:39', NULL),
('3be96257715e5489fb14b04404c82a58', 'App\\Notifications\\NewConversation', 'App\\Models\\User', 2, '{\"message\":\"A verified buyer opened a conversation about your Corn listing.\"}', '2026-10-10 11:14:41', '2026-10-09 04:07:28', '2026-10-10 11:14:41'),
('3f91ccfd1dd9bba64f577c657313ecf3', 'App\\Notifications\\SupportReply', 'App\\Models\\User', 2, '{\"message\":\"The administrator replied to your inquiry: How is the per-sack price converted to per kilogram?\"}', '2026-10-10 11:14:41', '2026-10-10 10:32:50', '2026-10-10 11:14:41'),
('435aa13b50f720132d8ec314a8beffe5', 'App\\Notifications\\NewConversation', 'App\\Models\\User', 2, '{\"message\":\"A verified buyer opened a conversation about your Corn listing.\"}', '2026-10-10 11:14:41', '2026-10-09 03:53:02', '2026-10-10 11:14:41'),
('5413d97589a6170ac967d53361dc47a6', 'App\\Notifications\\NewInquiry', 'App\\Models\\User', 1, '{\"message\":\"A user opened a Help inquiry: rtt\"}', '2026-10-10 11:14:18', '2026-10-10 11:05:24', '2026-10-10 11:14:18'),
('55f2d75ab801b69c68b6879f1a6ed82c', 'App\\Notifications\\NewInquiry', 'App\\Models\\User', 19, '{\"message\":\"A user opened a Help inquiry: rtt\"}', NULL, '2026-10-10 11:05:24', NULL),
('63a35d5e5a19209210acd8a677f6e142', 'App\\Notifications\\NewConversation', 'App\\Models\\User', 2, '{\"message\":\"A verified buyer opened a conversation about your Corn listing.\"}', '2026-10-10 11:14:41', '2026-10-09 03:53:14', '2026-10-10 11:14:41'),
('6f67fd0a4352152e9db2e578ebbc6b65', 'App\\Notifications\\NewConversation', 'App\\Models\\User', 2, '{\"message\":\"A verified buyer opened a conversation about your Corn listing.\"}', '2026-10-10 11:14:41', '2026-10-09 03:55:06', '2026-10-10 11:14:41'),
('7225506c094b55b8e7fec6a61265d6a4', 'App\\Notifications\\NewInquiry', 'App\\Models\\User', 17, '{\"message\":\"A user opened a Help inquiry: rtt\"}', NULL, '2026-10-10 11:05:24', NULL),
('8ea111543dc84fb699b3ba89541e7265', 'App\\Notifications\\AccountVerification', 'App\\Models\\User', 23, '{\"message\":\"Your farmer account has been verified \\u2014 your uploaded documents were approved.\"}', NULL, '2026-10-12 12:07:43', NULL),
('8ea2fa7075ac6c1942932ad96a06dd7f', 'App\\Notifications\\AccountVerification', 'App\\Models\\User', 20, '{\"message\":\"Your farmer account has been verified \\u2014 your uploaded documents were approved. Note from the administrator: Documents look good\"}', NULL, '2026-10-12 09:57:52', NULL),
('8f71db67fa3e40191d63164eb5becc92', 'App\\Notifications\\SupportClosed', 'App\\Models\\User', 2, '{\"message\":\"The administrator closed your inquiry: How is the per-sack price converted to per kilogram?\"}', '2026-10-10 11:14:41', '2026-10-10 10:32:01', '2026-10-10 11:14:41'),
('9c3827a6c37e354d8361a93f4004d429', 'App\\Notifications\\NewConversation', 'App\\Models\\User', 2, '{\"message\":\"A verified buyer opened a conversation about your Corn listing.\"}', '2026-10-10 11:14:41', '2026-10-09 03:54:52', '2026-10-10 11:14:41'),
('9d1da458dc2b1a3426a55bd6ad24c2d1', 'App\\Notifications\\AccountVerification', 'App\\Models\\User', 4, '{\"message\":\"Your verification was not approved. You can upload new documents from your Account status page, or use the Help channel to reach the administrators.\"}', NULL, '2026-10-12 11:54:52', NULL),
('a799f2a670227120cd1ef6e8caf7d6e1', 'App\\Notifications\\AccountVerification', 'App\\Models\\User', 4, '{\"message\":\"Your buyer account has been verified. You can now message farmers.\"}', NULL, '2026-10-12 11:54:57', NULL),
('acbce7faf4e52628f0669a6a2e2b27e6', 'App\\Notifications\\AccountVerification', 'App\\Models\\User', 21, '{\"message\":\"Your verification was not approved. You can upload new documents from your Account status page, or use the Help channel to reach the administrators. Note from the administrator: ID photo is blurry, please re-upload\"}', NULL, '2026-10-12 09:57:52', NULL),
('c0f878a0cd7af56099b167e01f8eac91', 'App\\Notifications\\NewConversation', 'App\\Models\\User', 2, '{\"message\":\"A verified buyer opened a conversation about your Corn listing.\"}', '2026-10-10 11:14:41', '2026-10-09 03:52:02', '2026-10-10 11:14:41'),
('e0e5737d9c9037b1b4313141eba72cf9', 'App\\Notifications\\BuyerVerification', 'App\\Models\\User', 16, '{\"message\":\"Your buyer account has been verified. You can now message farmers.\"}', NULL, '2026-10-10 09:16:51', NULL),
('f1c0fe24cc328bc8f86d5a02e2cb19e5', 'App\\Notifications\\SupportReply', 'App\\Models\\User', 3, '{\"message\":\"The administrator replied to your inquiry: rtt\"}', NULL, '2026-10-10 11:05:51', NULL),
('f1d8a14d2afff16afb3c23ad9e2903ae', 'App\\Notifications\\NewConversation', 'App\\Models\\User', 2, '{\"message\":\"A verified buyer opened a conversation about your Corn listing.\"}', '2026-10-12 10:12:00', '2026-10-10 11:15:37', '2026-10-12 10:12:00');

-- --------------------------------------------------------

--
-- Table structure for table `price_history`
--

CREATE TABLE `price_history` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `market_id` bigint(20) NOT NULL,
  `snapshot_date` date NOT NULL,
  `avg_normalized_price` decimal(12,4) NOT NULL,
  `min_normalized_price` decimal(12,4) NOT NULL,
  `max_normalized_price` decimal(12,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `price_history`
--

INSERT INTO `price_history` (`id`, `product_id`, `market_id`, `snapshot_date`, `avg_normalized_price`, `min_normalized_price`, `max_normalized_price`) VALUES
(1, 2, 2, '2026-10-09', 77.7700, 77.7700, 77.7700),
(6, 2, 1, '2026-10-10', 77.7700, 77.7700, 77.7700),
(7, 2, 3, '2026-10-10', 77.7700, 77.7700, 77.7700),
(65, 2, 2, '2026-10-10', 300.0000, 300.0000, 300.0000),
(75, 2, 3, '2026-10-02', 30.0000, 30.0000, 30.0000);

-- --------------------------------------------------------

--
-- Table structure for table `price_reports`
--

CREATE TABLE `price_reports` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `market_id` bigint(20) NOT NULL,
  `unit_id` bigint(20) NOT NULL COMMENT 'unit exactly as submitted',
  `price` decimal(12,4) NOT NULL COMMENT 'raw reported price',
  `normalized_price` decimal(12,4) NOT NULL COMMENT 'equivalent in product base unit',
  `source_type` enum('scraped','bulletin','manual') NOT NULL,
  `reported_by` bigint(20) DEFAULT NULL COMMENT 'admin who typed a manual entry',
  `baseline_price` decimal(12,4) DEFAULT NULL COMMENT 'avg of previous 7 days (>=3 reports)',
  `deviation_pct` decimal(8,2) DEFAULT NULL COMMENT '>20% = unusual price movement',
  `date_reported` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `price_reports`
--

INSERT INTO `price_reports` (`id`, `product_id`, `market_id`, `unit_id`, `price`, `normalized_price`, `source_type`, `reported_by`, `baseline_price`, `deviation_pct`, `date_reported`, `created_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 45.0000, 45.0000, 'manual', 1, NULL, -4.26, '2026-10-03 00:00:00', '2026-10-08 08:05:46', NULL),
(2, 1, 1, 1, 47.0000, 47.0000, 'manual', 1, NULL, 4.44, '2026-10-02 00:00:00', '2026-10-08 08:05:46', NULL),
(3, 1, 2, 1, 44.0000, 44.0000, 'manual', 1, NULL, -4.35, '2026-10-01 00:00:00', '2026-10-08 08:05:46', NULL),
(4, 1, 2, 1, 46.0000, 46.0000, 'manual', 1, NULL, 4.55, '2026-10-03 00:00:00', '2026-10-08 08:05:46', NULL),
(5, 2, 3, 1, 30.0000, 30.0000, 'manual', 1, NULL, -66.63, '2026-10-02 00:00:00', '2026-10-08 08:05:46', NULL),
(6, 2, 3, 1, 31.0000, 31.0000, 'manual', 1, NULL, 3.33, '2026-10-03 00:00:00', '2026-10-08 08:05:46', NULL),
(7, 2, 4, 1, 32.0000, 32.0000, 'manual', 1, NULL, -64.29, '2026-10-01 00:00:00', '2026-10-08 08:05:46', NULL),
(8, 2, 4, 1, 33.0000, 33.0000, 'manual', 1, NULL, 3.13, '2026-10-03 00:00:00', '2026-10-08 08:05:46', NULL),
(57, 2, 2, 1, 77.7700, 77.7700, 'manual', 1, NULL, -6.39, '2026-10-09 00:00:00', '2026-10-09 04:07:25', NULL),
(58, 2, 1, 1, 77.7700, 77.7700, 'manual', 1, NULL, NULL, '2026-10-09 00:00:00', '2026-10-09 04:07:25', '2026-10-10 09:04:48'),
(59, 2, 3, 1, 77.7700, 77.7700, 'manual', 1, NULL, NULL, '2026-10-09 00:00:00', '2026-10-09 04:07:25', '2026-10-10 09:04:41'),
(61, 2, 2, 1, 77.7700, 77.7700, 'manual', 1, NULL, NULL, '2026-10-10 00:00:00', '2026-10-09 04:07:25', '2026-10-10 07:29:55'),
(62, 2, 1, 1, 77.7700, 77.7700, 'manual', 1, NULL, -6.39, '2026-10-10 00:00:00', '2026-10-09 04:07:25', NULL),
(63, 2, 3, 1, 77.7700, 77.7700, 'manual', 1, NULL, 150.87, '2026-10-10 00:00:00', '2026-10-09 04:07:25', NULL),
(72, 2, 2, 1, 300.0000, 300.0000, 'manual', 1, NULL, 285.75, '2026-10-10 00:00:00', '2026-10-10 09:07:49', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) NOT NULL,
  `product_name` varchar(120) NOT NULL,
  `category` varchar(80) NOT NULL COMMENT 'FK to categories.name (cascades on rename)',
  `base_unit_id` bigint(20) NOT NULL COMMENT 'canonical unit every price normalizes to',
  `kind` enum('crop','livestock') NOT NULL DEFAULT 'crop' COMMENT 'decides which stage list a listing uses',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `category`, `base_unit_id`, `kind`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Rice', 'Cereals', 1, 'crop', '2026-10-08 08:06:54', NULL, NULL),
(2, 'Corn', 'Cereals', 1, 'crop', '2026-10-08 08:08:19', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_units`
--

CREATE TABLE `product_units` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `unit_id` bigint(20) NOT NULL,
  `factor_to_base` decimal(12,4) NOT NULL COMMENT '1 of this unit = X base units',
  `is_default` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_units`
--

INSERT INTO `product_units` (`id`, `product_id`, `unit_id`, `factor_to_base`, `is_default`) VALUES
(44, 1, 1, 1.0000, 1),
(45, 2, 1, 1.0000, 1);

-- --------------------------------------------------------

--
-- Table structure for table `support_inquiries`
--

CREATE TABLE `support_inquiries` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `subject` varchar(190) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open' COMMENT 'open | answered | closed',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_inquiries`
--

INSERT INTO `support_inquiries` (`id`, `user_id`, `subject`, `status`, `last_message_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'How is the per-sack price converted to per kilogram?', 'answered', '2026-10-10 10:32:50', '2026-10-07 12:47:27', '2026-10-10 10:32:50'),
(3, 3, 'rtt', 'answered', '2026-10-10 11:05:51', '2026-10-10 11:05:24', '2026-10-10 11:05:51');

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` bigint(20) NOT NULL,
  `support_inquiry_id` bigint(20) NOT NULL,
  `sender_id` bigint(20) NOT NULL,
  `body` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_messages`
--

INSERT INTO `support_messages` (`id`, `support_inquiry_id`, `sender_id`, `body`, `read_at`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'How is the per-sack price converted to per kilogram?', '2026-10-03 05:45:00', '2026-10-07 12:47:27', NULL),
(2, 1, 1, 'The system divides your price by the conversion factor stored for that unit (e.g. 1 sack = 50 kg), so it is always compared on a per-kilogram basis.', '2026-10-03 06:00:00', '2026-10-07 12:47:27', NULL),
(3, 1, 1, 'any more questions?', '2026-10-10 11:14:48', '2026-10-10 10:32:50', '2026-10-10 11:14:48'),
(7, 3, 3, 'erterter', '2026-10-10 11:05:44', '2026-10-10 11:05:24', '2026-10-10 11:05:44'),
(8, 3, 1, 'what', '2026-10-10 11:16:10', '2026-10-10 11:05:51', '2026-10-10 11:16:10');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` bigint(20) NOT NULL,
  `code` varchar(20) NOT NULL COMMENT 'kg, sack, ton, pc, crate, bundle, tray',
  `name` varchar(60) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `code`, `name`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'kg', 'Kilogram', '2026-10-07 12:47:27', NULL, NULL),
(2, 'g', 'Gram', '2026-10-07 12:47:27', NULL, NULL),
(3, 'sack', 'Sack', '2026-10-07 12:47:27', NULL, NULL),
(4, 'ton', 'Metric Ton', '2026-10-07 12:47:27', NULL, NULL),
(5, 'pc', 'Piece', '2026-10-07 12:47:27', NULL, NULL),
(6, 'crate', 'Crate', '2026-10-07 12:47:27', NULL, NULL),
(7, 'bundle', 'Bundle', '2026-10-07 12:47:27', NULL, NULL),
(8, 'tray', 'Tray', '2026-10-07 12:47:27', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `username` varchar(60) DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL COMMENT 'password_hash() value',
  `role` enum('farmer','buyer','admin') NOT NULL DEFAULT 'farmer' COMMENT 'farmer = seller (client), buyer = buyer, admin = validator',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `email_token_hash` char(64) DEFAULT NULL,
  `email_token_expires_at` datetime DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'verified' COMMENT 'Applies to buyer accounts only; admins/farmers auto-verified',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) DEFAULT NULL COMMENT 'Users.id of the reviewing admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete (Recycle Bin)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `username`, `email`, `phone_number`, `password`, `role`, `email_verified_at`, `email_token_hash`, `email_token_expires_at`, `verification_status`, `verified_at`, `verified_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Rynna Mae', 'Alegria', 'admin', 'admin@gmail.com', NULL, '$2y$12$MjpSLatQBm9bt5cgPDnQgufKMNUnVCcGbgKlxyl3R.41KH99iczKi', 'admin', '2026-10-07 12:47:27', NULL, NULL, 'verified', '2026-10-07 12:47:27', NULL, '2026-10-07 12:47:27', '2026-10-10 07:44:55', NULL),
(2, 'Alfonso', 'Ramos', 'farmer', 'farmer@gmail.com', NULL, '$2y$12$YqlQPsJ1AI417SmJsnKvi.P9n7QWinIDN6IxHrSfCpG2ve7pnvf0e', 'farmer', '2026-10-07 12:47:27', NULL, NULL, 'verified', '2026-10-07 12:47:27', NULL, '2026-10-07 12:47:27', '2026-10-10 07:44:55', NULL),
(3, 'Mila', 'Mercado', 'buyer', 'buyer@agriprice.local', NULL, '$2y$12$S2ZxdbeKfz3FiSZ0GSnlIeWs5Xhu3ZzgkwubVAQ6vMUkRQPVe.sSG', 'buyer', '2026-10-07 12:47:27', NULL, NULL, 'verified', '2026-10-07 12:47:27', NULL, '2026-10-07 12:47:27', '2026-10-10 07:44:55', NULL),
(4, 'Pedro', 'Trader', 'pedrotrader', 'buyer2@agriprice.local', NULL, '$2y$12$jFqeu3RHtVx6rJzFDbxUFe.srxJwqdlh6aGTM3dlknmAVcaLH76DS', 'buyer', '2026-10-07 12:47:27', NULL, NULL, 'verified', '2026-10-12 11:54:57', 1, '2026-10-07 12:47:27', '2026-10-12 11:54:57', NULL),
(16, 'Roy', 'Tapia', 'roytapz27', 'roytapz27@example.com', NULL, '$2y$12$ETVgIctLet2KpVGd8aPH9OmFnXgpMusx3Ia5BbsurrLLyQ5lb8PBa', 'buyer', '2026-10-10 08:45:03', '3f27ef71f71d50a0509785c52bcfe7a614d02380498141fe4dff09765ec88ca4', '2026-10-11 16:44:47', 'verified', '2026-10-10 09:16:51', 1, '2026-10-10 08:44:47', '2026-10-10 11:01:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `verification_documents`
--

CREATE TABLE `verification_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `doc_type` enum('id','business_permit','other') NOT NULL DEFAULT 'other',
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_name` (`name`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_conversation` (`crop_id`,`buyer_id`),
  ADD KEY `idx_conv_farmer` (`farmer_id`),
  ADD KEY `idx_conv_buyer` (`buyer_id`);

--
-- Indexes for table `crops`
--
ALTER TABLE `crops`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crops_farmer` (`farmer_id`),
  ADD KEY `idx_crops_product` (`product_id`),
  ADD KEY `idx_crops_status` (`status`),
  ADD KEY `fk_crops_unit` (`unit_id`);

--
-- Indexes for table `crop_growth_stages`
--
ALTER TABLE `crop_growth_stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stages_crop` (`crop_id`,`recorded_at`),
  ADD KEY `fk_stages_user` (`updated_by`);

--
-- Indexes for table `crop_sales`
--
ALTER TABLE `crop_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sales_crop` (`crop_id`),
  ADD KEY `fk_sales_user` (`recorded_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_login_identifier_ip` (`identifier`,`ip_address`),
  ADD KEY `idx_login_last_attempt` (`last_attempt_at`);

--
-- Indexes for table `markets`
--
ALTER TABLE `markets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_markets_name` (`market_name`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_conv` (`conversation_id`,`created_at`),
  ADD KEY `fk_messages_sender` (`sender_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`notifiable_id`,`read_at`);

--
-- Indexes for table `price_history`
--
ALTER TABLE `price_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_history_day` (`product_id`,`market_id`,`snapshot_date`),
  ADD KEY `fk_history_market` (`market_id`);

--
-- Indexes for table `price_reports`
--
ALTER TABLE `price_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_product_date` (`product_id`,`date_reported`),
  ADD KEY `idx_reports_market` (`market_id`),
  ADD KEY `fk_reports_unit` (`unit_id`),
  ADD KEY `fk_reports_user` (`reported_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_name` (`product_name`),
  ADD KEY `idx_products_category` (`category`),
  ADD KEY `idx_products_kind` (`kind`),
  ADD KEY `fk_products_base_unit` (`base_unit_id`);

--
-- Indexes for table `product_units`
--
ALTER TABLE `product_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_unit` (`product_id`,`unit_id`),
  ADD KEY `fk_pu_unit` (`unit_id`);

--
-- Indexes for table `support_inquiries`
--
ALTER TABLE `support_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inquiries_user` (`user_id`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_msgs` (`support_inquiry_id`,`created_at`),
  ADD KEY `fk_sm_sender` (`sender_id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_units_code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_role` (`role`,`deleted_at`),
  ADD KEY `idx_users_verification` (`role`,`verification_status`),
  ADD KEY `fk_users_verified_by` (`verified_by`);

--
-- Indexes for table `verification_documents`
--
ALTER TABLE `verification_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vd_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `crops`
--
ALTER TABLE `crops`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `crop_growth_stages`
--
ALTER TABLE `crop_growth_stages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `crop_sales`
--
ALTER TABLE `crop_sales`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `markets`
--
ALTER TABLE `markets`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `price_history`
--
ALTER TABLE `price_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `price_reports`
--
ALTER TABLE `price_reports`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `product_units`
--
ALTER TABLE `product_units`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `support_inquiries`
--
ALTER TABLE `support_inquiries`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `verification_documents`
--
ALTER TABLE `verification_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conv_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conv_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conv_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crops`
--
ALTER TABLE `crops`
  ADD CONSTRAINT `fk_crops_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_crops_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_crops_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

--
-- Constraints for table `crop_growth_stages`
--
ALTER TABLE `crop_growth_stages`
  ADD CONSTRAINT `fk_stages_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stages_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crop_sales`
--
ALTER TABLE `crop_sales`
  ADD CONSTRAINT `fk_sales_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sales_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_history`
--
ALTER TABLE `price_history`
  ADD CONSTRAINT `fk_history_market` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_reports`
--
ALTER TABLE `price_reports`
  ADD CONSTRAINT `fk_reports_market` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`),
  ADD CONSTRAINT `fk_reports_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reports_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  ADD CONSTRAINT `fk_reports_user` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_base_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `units` (`id`),
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category`) REFERENCES `categories` (`name`) ON UPDATE CASCADE;

--
-- Constraints for table `product_units`
--
ALTER TABLE `product_units`
  ADD CONSTRAINT `fk_pu_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pu_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

--
-- Constraints for table `support_inquiries`
--
ALTER TABLE `support_inquiries`
  ADD CONSTRAINT `fk_inquiries_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `fk_sm_inquiry` FOREIGN KEY (`support_inquiry_id`) REFERENCES `support_inquiries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `verification_documents`
--
ALTER TABLE `verification_documents`
  ADD CONSTRAINT `fk_vd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
