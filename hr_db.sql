-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for hr
CREATE DATABASE IF NOT EXISTS `hr` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `hr`;

-- Dumping structure for table hr.events
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `event_type` varchar(50) DEFAULT 'meeting',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'medium',
  `status` varchar(20) DEFAULT 'upcoming',
  `personnel_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.events: ~0 rows (approximately)
INSERT INTO `events` (`id`, `event_title`, `event_description`, `event_type`, `start_date`, `end_date`, `location`, `priority`, `status`, `personnel_id`, `created_by`, `created_at`) VALUES
	(24, 'Libero necessitatibu', 'Natus aut minim omni', 'other', '2026-05-22', '2026-05-22', 'Officia eu delectus', 'low', 'upcoming', 38, 8512, '2026-05-22 10:07:32');

-- Dumping structure for table hr.event_participants
CREATE TABLE IF NOT EXISTS `event_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.event_participants: ~0 rows (approximately)

-- Dumping structure for table hr.leave_balance
CREATE TABLE IF NOT EXISTS `leave_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_id` int(11) NOT NULL,
  `gharpari_bida_days` decimal(5,1) DEFAULT 0.0,
  `parba_bida_days` decimal(5,1) DEFAULT 0.0,
  `bhaeepari_bida_days` decimal(5,1) DEFAULT 0.0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `personnel_id` (`personnel_id`),
  UNIQUE KEY `idx_personnel_id` (`personnel_id`),
  CONSTRAINT `leave_balance_ibfk_1` FOREIGN KEY (`personnel_id`) REFERENCES `military_personnel_status` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.leave_balance: ~3 rows (approximately)
INSERT INTO `leave_balance` (`id`, `personnel_id`, `gharpari_bida_days`, `parba_bida_days`, `bhaeepari_bida_days`, `last_updated`) VALUES
	(140, 36, 7.0, 1.0, 7.0, '2026-05-22 04:21:02'),
	(141, 37, 15.0, 12.0, 10.0, '2026-05-20 11:00:22'),
	(142, 38, 15.0, 12.0, 10.0, '2026-05-20 11:00:57'),
	(143, 39, 14.0, 12.0, 10.0, '2026-05-22 08:35:19');

-- Dumping structure for table hr.leave_requests
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_id` int(11) NOT NULL,
  `leave_type` enum('annual','sick','casual','emergency','study','maternity','paternity','gharpari_bida','parba_bida','bhaeepari_bida') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `leave_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `initiating_officer` int(11) DEFAULT NULL,
  `accepting_officer` int(11) DEFAULT NULL,
  `initiating_officer_approved` tinyint(1) DEFAULT 0,
  `initiating_officer_approved_by` int(11) DEFAULT NULL,
  `initiating_officer_approved_at` datetime DEFAULT NULL,
  `initiating_officer_remarks` text DEFAULT NULL,
  `accepting_officer_approved` tinyint(1) DEFAULT 0,
  `accepting_officer_approved_by` int(11) DEFAULT NULL,
  `accepting_officer_approved_at` datetime DEFAULT NULL,
  `accepting_officer_remarks` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `alternate_officer` varchar(100) DEFAULT NULL,
  `support_officer` int(11) DEFAULT NULL,
  `finalizing_officer` int(11) DEFAULT NULL,
  `status` enum('pending','initiating_approved','forwarded','approved','rejected','cancelled') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `forwarded_by` int(11) DEFAULT NULL,
  `forwarded_at` datetime DEFAULT NULL,
  `forwarded_to` int(11) DEFAULT NULL,
  `forward_remarks` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approver_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_personnel` (`personnel_id`),
  KEY `fk_leave_initiating_officer_approved_by` (`initiating_officer_approved_by`),
  KEY `fk_leave_accepting_officer_approved_by` (`accepting_officer_approved_by`),
  KEY `idx_initiating_officer` (`initiating_officer`),
  KEY `idx_accepting_officer` (`accepting_officer`),
  CONSTRAINT `fk_leave_accepting_officer` FOREIGN KEY (`accepting_officer`) REFERENCES `military_personnel_status` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_accepting_officer_approved_by` FOREIGN KEY (`accepting_officer_approved_by`) REFERENCES `military_personnel_status` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_initiating_officer` FOREIGN KEY (`initiating_officer`) REFERENCES `military_personnel_status` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_initiating_officer_approved_by` FOREIGN KEY (`initiating_officer_approved_by`) REFERENCES `military_personnel_status` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`personnel_id`) REFERENCES `military_personnel_status` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.leave_requests: ~11 rows (approximately)
INSERT INTO `leave_requests` (`id`, `personnel_id`, `leave_type`, `start_date`, `end_date`, `leave_days`, `reason`, `initiating_officer`, `accepting_officer`, `initiating_officer_approved`, `initiating_officer_approved_by`, `initiating_officer_approved_at`, `initiating_officer_remarks`, `accepting_officer_approved`, `accepting_officer_approved_by`, `accepting_officer_approved_at`, `accepting_officer_remarks`, `contact_number`, `alternate_officer`, `support_officer`, `finalizing_officer`, `status`, `created_by`, `approved_by`, `forwarded_by`, `forwarded_at`, `forwarded_to`, `forward_remarks`, `approved_at`, `approver_remarks`, `created_at`, `updated_at`) VALUES
	(34, 36, 'gharpari_bida', '2026-05-20', '2026-05-20', 1, 'short break for marraige ceremony', 37, 38, 1, 37, '2026-05-20 16:45:42', 'go ahead', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 36, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 10:59:53', '2026-05-20 11:00:42'),
	(35, 36, 'parba_bida', '2026-05-20', '2026-05-20', 1, 'Voluptas irure nihil', 37, 38, 1, 37, '2026-05-20 16:48:14', 'sdsds', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 36, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 11:02:46', '2026-05-20 11:03:14'),
	(36, 36, 'parba_bida', '2026-05-20', '2026-05-20', 1, 'Quod proident dolor', 37, 38, 1, 37, '2026-05-20 16:59:45', 'dffd', 1, 38, '2026-05-20 17:00:20', 'done go ahead. chill bro', NULL, NULL, NULL, NULL, 'approved', 36, 38, NULL, NULL, NULL, NULL, '2026-05-20 17:00:20', NULL, '2026-05-20 11:14:26', '2026-05-20 11:15:20'),
	(37, 36, 'parba_bida', '2026-05-28', '2026-05-30', 3, 'vacation', 37, 38, 1, 37, '2026-05-21 09:48:27', 'ok', 1, 38, '2026-05-21 09:50:14', 'ok', NULL, NULL, NULL, NULL, 'approved', 36, 38, NULL, NULL, NULL, NULL, '2026-05-21 09:50:14', NULL, '2026-05-21 03:45:10', '2026-05-21 04:05:14'),
	(38, 36, 'bhaeepari_bida', '2026-05-31', '2026-05-31', 1, 'brother ceremony', 37, 38, 1, 37, '2026-05-21 09:48:22', 'ok', 1, 38, '2026-05-21 09:50:09', 'ok', NULL, NULL, NULL, NULL, 'approved', 36, 38, NULL, NULL, NULL, NULL, '2026-05-21 09:50:09', NULL, '2026-05-21 03:46:51', '2026-05-21 04:05:09'),
	(39, 36, 'bhaeepari_bida', '2026-06-22', '2026-06-23', 2, 'okay', 37, 38, 1, 37, '2026-05-21 09:48:15', 'ok', 1, 38, '2026-05-21 09:50:04', 'ok', NULL, NULL, NULL, NULL, 'approved', 36, 38, NULL, NULL, NULL, NULL, '2026-05-21 09:50:04', NULL, '2026-05-21 03:48:31', '2026-05-21 04:05:04'),
	(40, 36, 'gharpari_bida', '2026-06-28', '2026-06-29', 2, 'ghumna jaane', 37, 38, 1, 37, '2026-05-21 09:48:31', 'ok', 1, 38, '2026-05-21 09:50:18', 'ok', NULL, NULL, NULL, NULL, 'approved', 36, 38, NULL, NULL, NULL, NULL, '2026-05-21 09:50:18', NULL, '2026-05-21 03:49:12', '2026-05-21 04:05:18'),
	(41, 36, 'parba_bida', '2026-08-23', '2026-08-29', 7, 'okay', 37, 38, 1, 37, '2026-05-21 09:48:44', 'ok', 1, 38, '2026-05-21 09:50:22', 'ok', NULL, NULL, NULL, NULL, 'approved', 36, 38, NULL, NULL, NULL, NULL, '2026-05-21 09:50:22', NULL, '2026-05-21 03:49:51', '2026-05-21 04:05:22'),
	(42, 36, 'gharpari_bida', '2026-11-23', '2026-11-27', 5, 'oossdsd', 37, 38, 1, 37, '2026-05-21 09:54:07', 'oko', 1, 38, '2026-05-21 09:54:41', 'ok', NULL, NULL, NULL, NULL, 'approved', 36, 38, NULL, NULL, NULL, NULL, '2026-05-21 09:54:41', NULL, '2026-05-21 04:08:43', '2026-05-21 04:09:41'),
	(43, 36, 'gharpari_bida', '2026-05-25', '2026-05-25', 1, 'ok', 37, 38, 1, 37, '2026-05-22 10:05:36', 'ok forward', 1, 38, '2026-05-22 10:06:02', 'go ahead', NULL, NULL, NULL, NULL, 'approved', 36, 38, NULL, NULL, NULL, NULL, '2026-05-22 10:06:02', NULL, '2026-05-22 04:20:06', '2026-05-22 04:21:02'),
	(44, 39, 'gharpari_bida', '2026-05-22', '2026-05-22', 1, 'okay', 37, 38, 1, 37, '2026-05-22 14:19:54', 'ok', 1, 38, '2026-05-22 14:20:19', 'ok', NULL, NULL, NULL, NULL, 'approved', 39, 38, NULL, NULL, NULL, NULL, '2026-05-22 14:20:19', NULL, '2026-05-22 08:34:05', '2026-05-22 08:35:19');

-- Dumping structure for table hr.leave_settings
CREATE TABLE IF NOT EXISTS `leave_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `gharpari_bida_default` decimal(5,1) DEFAULT 0.0,
  `parba_bida_default` decimal(5,1) DEFAULT 0.0,
  `bhaeepari_bida_default` decimal(5,1) DEFAULT 0.0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.leave_settings: ~1 rows (approximately)

-- Dumping structure for table hr.military_personnel_status
CREATE TABLE IF NOT EXISTS `military_personnel_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_number` varchar(20) DEFAULT NULL,
  `personnel_name` varchar(255) NOT NULL,
  `rank` varchar(100) NOT NULL,
  `status` enum('present','leave','sick','work','workout','tdy','course') NOT NULL,
  `record_date` date NOT NULL,
  `in_time` time DEFAULT NULL,
  `out_time` time DEFAULT NULL,
  `remarks` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`record_date`),
  KEY `idx_personnel_number` (`personnel_number`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.military_personnel_status: ~2 rows (approximately)
INSERT INTO `military_personnel_status` (`id`, `personnel_number`, `personnel_name`, `rank`, `status`, `record_date`, `in_time`, `out_time`, `remarks`, `created_at`, `updated_at`) VALUES
	(36, '8572', 'Susmin Basnet', 'Lieutenant', 'present', '2026-05-20', NULL, NULL, 'Auto-created from login', '2026-05-20 06:16:23', '2026-05-20 06:16:23'),
	(37, '8571', 'Dikshya Thapa', 'Lieutenant', 'present', '2026-05-20', NULL, NULL, 'Auto-created from login', '2026-05-20 06:17:30', '2026-05-20 06:17:30'),
	(38, '8512', 'Suraj shahi', 'Lieutenant', 'present', '2026-05-20', NULL, NULL, 'Auto-created from login', '2026-05-20 06:20:14', '2026-05-20 06:20:14'),
	(39, '9999', 'Shad Mcguire', 'Praesentium voluptat', 'present', '2026-05-22', NULL, NULL, 'Auto-created from login', '2026-05-22 08:31:21', '2026-05-22 08:31:21');

-- Dumping structure for table hr.personnel
CREATE TABLE IF NOT EXISTS `personnel` (
  `personnel_number` varchar(20) NOT NULL,
  `full_name_en` varchar(100) NOT NULL,
  `full_name_ne` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Other',
  `blood_group` enum('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
  `rank` varchar(50) DEFAULT NULL,
  `current_status` varchar(30) DEFAULT 'Active',
  `unit` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `signature` varchar(500) DEFAULT NULL,
  `joint_date` date DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `municipality` varchar(100) DEFAULT NULL,
  `ward_number` varchar(10) DEFAULT NULL,
  `village_tole` varchar(200) DEFAULT NULL,
  `religion` varchar(50) DEFAULT 'Hindu',
  `military_status` varchar(20) DEFAULT 'Single',
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `recruitment_date` date DEFAULT NULL,
  `commission_date` date DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `spouse_name` varchar(100) DEFAULT NULL,
  `children_names` text DEFAULT NULL,
  `family_notes` text DEFAULT NULL,
  `grandfather_name` varchar(100) DEFAULT NULL,
  `higher_education` text DEFAULT NULL,
  `military_trainings` text DEFAULT NULL,
  `training` text DEFAULT NULL,
  `training_address` varchar(255) DEFAULT NULL,
  `training1` varchar(200) DEFAULT NULL,
  `training1_address` varchar(255) DEFAULT NULL,
  `training2` varchar(200) DEFAULT NULL,
  `training2_address` varchar(255) DEFAULT NULL,
  `training3` varchar(200) DEFAULT NULL,
  `training4` varchar(200) DEFAULT NULL,
  `training5` varchar(200) DEFAULT NULL,
  `foreign_training` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` tinyint(4) NOT NULL DEFAULT 0,
  `password_updated_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when password was last updated',
  PRIMARY KEY (`personnel_number`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_personnel_number` (`personnel_number`),
  KEY `idx_full_name_en` (`full_name_en`),
  KEY `idx_current_status` (`current_status`),
  KEY `idx_province` (`province`),
  KEY `idx_district` (`district`),
  KEY `idx_municipality` (`municipality`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.personnel: ~5 rows (approximately)
INSERT INTO `personnel` (`personnel_number`, `full_name_en`, `full_name_ne`, `dob`, `gender`, `blood_group`, `rank`, `current_status`, `unit`, `email`, `signature`, `joint_date`, `contact`, `phone`, `address`, `province`, `district`, `municipality`, `ward_number`, `village_tole`, `religion`, `military_status`, `password`, `remember_token`, `recruitment_date`, `commission_date`, `father_name`, `mother_name`, `spouse_name`, `children_names`, `family_notes`, `grandfather_name`, `higher_education`, `military_trainings`, `training`, `training_address`, `training1`, `training1_address`, `training2`, `training2_address`, `training3`, `training4`, `training5`, `foreign_training`, `created_at`, `updated_at`, `role`, `password_updated_at`) VALUES
	('8512', 'Suraj shahi', NULL, NULL, 'Other', NULL, 'Lieutenant', 'Active', 'Corps of Engineers', 'superadmin@gmail.com', '/uploads/signatures/8512_signature_1779338833.jpg', '2026-05-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$tzVrrDP8hdA3t/0.8Eb1mebckAPdKr7v44zzlsdwbtX60I5eOqAjq', 'b51d7e67aed2b300b2aa0a4ab877cceceaaf9d08589613843b223a7df579e21e', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 03:58:12', '2026-05-22 06:26:02', 2, NULL),
	('8571', 'Dikshya Thapa', NULL, NULL, 'Other', NULL, 'Lieutenant', 'Active', 'Signals', 'admin@gmail.com', '/uploads/signatures/8571_signature_1779338848.jpg', '2026-05-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$oe28ENJFCJ/OuLPmNUCXWucX3yEM/RjRYqvx0HFk2awxTTr873F92', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 03:57:21', '2026-05-21 04:47:28', 1, NULL),
	('8572', 'Susmin Basnet', NULL, NULL, 'Other', NULL, 'Lieutenant', 'Active', 'Intelligence', 'user@gmail.com', '/uploads/signatures/8572_signature_1779338857.jpg', '2026-05-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$Hl9bjp8ogKAvVZoHs8ar6Om1nbkf0cgueB51CMXvREwoza/czoBEi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 03:56:20', '2026-05-21 04:47:37', 0, NULL),
	('9000', 'Bijay khadka', '', '2026-05-07', 'Male', 'A+', 'Captain', 'Active', 'Brigade', 'bijay@gmail.com', '/uploads/signatures/9000_signature_1779338810.jpg', NULL, '9763608859', '', 'okay', NULL, NULL, NULL, NULL, NULL, '', '', '', NULL, '2026-05-21', NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-21 04:38:00', '2026-05-21 04:46:50', 0, NULL),
	('9999', 'Shad Mcguire', '', '2012-08-15', 'Female', 'B-', 'Praesentium voluptat', 'Leave', 'Veritatis beatae ear', 'test@gmail.com', '/uploads/signatures/9999_signature_1779444981.png', NULL, '9763608859', '', 'balkumari', 'Madhesh Province', 'Kanchanpur', 'bhimdatta', '2', 'thapathali', '', '', '$2y$10$tzVrrDP8hdA3t/0.8Eb1mebckAPdKr7v44zzlsdwbtX60I5eOqAjq', NULL, '1994-09-23', NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-22 06:58:46', '2026-05-22 10:16:21', 1, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
