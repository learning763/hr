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
CREATE DATABASE IF NOT EXISTS `hr` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.events: ~0 rows (approximately)

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
  `personnel_id` varchar(20) DEFAULT NULL,
  `gharpari_bida_days` decimal(5,1) DEFAULT 0.0,
  `parba_bida_days` decimal(5,1) DEFAULT 0.0,
  `bhaeepari_bida_days` decimal(5,1) DEFAULT 0.0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `personnel_id` (`personnel_id`),
  UNIQUE KEY `idx_personnel_id` (`personnel_id`),
  UNIQUE KEY `unique_personnel` (`personnel_id`)
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.leave_balance: ~5 rows (approximately)
INSERT INTO `leave_balance` (`id`, `personnel_id`, `gharpari_bida_days`, `parba_bida_days`, `bhaeepari_bida_days`, `last_updated`) VALUES
	(183, '8512', 15.0, 12.0, 10.0, '2026-06-04 04:38:43'),
	(184, '8571', 15.0, 12.0, 10.0, '2026-06-04 09:20:18'),
	(185, '8572', 14.0, 12.0, 10.0, '2026-06-04 04:46:38'),
	(186, '8589', 30.0, 12.0, 10.0, '2026-06-04 06:37:00'),
	(187, '8590', 15.0, 12.0, 10.0, '2026-06-04 09:20:28'),
	(188, '857', 15.0, 12.0, 10.0, '2026-06-04 09:20:45');

-- Dumping structure for table hr.leave_requests
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_id` varchar(20) DEFAULT NULL,
  `leave_type` enum('annual','sick','casual','emergency','study','maternity','paternity','gharpari_bida','parba_bida','bhaeepari_bida') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `leave_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `initiating_officer` varchar(20) DEFAULT NULL,
  `accepting_officer` varchar(20) DEFAULT NULL,
  `verifying_officer` varchar(20) DEFAULT NULL,
  `verifying_officer_approved` tinyint(1) DEFAULT 0,
  `verifying_officer_approved_by` varchar(20) DEFAULT NULL,
  `verifying_officer_approved_at` datetime DEFAULT NULL,
  `verifying_officer_remarks` text DEFAULT NULL,
  `initiating_officer_approved` tinyint(1) DEFAULT 0,
  `initiating_officer_approved_by` varchar(20) DEFAULT NULL,
  `initiating_officer_approved_at` datetime DEFAULT NULL,
  `initiating_officer_remarks` text DEFAULT NULL,
  `accepting_officer_approved` tinyint(1) DEFAULT 0,
  `accepting_officer_approved_by` varchar(20) DEFAULT NULL,
  `accepting_officer_approved_at` datetime DEFAULT NULL,
  `accepting_officer_remarks` text DEFAULT NULL,
  `receiver_id` varchar(20) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `alternate_officer` varchar(100) DEFAULT NULL,
  `support_officer` int(11) DEFAULT NULL,
  `finalizing_officer` int(11) DEFAULT NULL,
  `status` enum('pending','verified','initiating_approved','forwarded','approved','rejected','cancelled') DEFAULT 'pending',
  `created_by` varchar(20) DEFAULT NULL,
  `approved_by` varchar(20) DEFAULT NULL,
  `forwarded_by` varchar(20) DEFAULT NULL,
  `forwarded_at` datetime DEFAULT NULL,
  `forwarded_to` varchar(20) DEFAULT NULL,
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
  KEY `idx_receiver_id` (`receiver_id`),
  KEY `idx_verifying_officer` (`verifying_officer`),
  KEY `fk_leave_verifying_officer_approved_by` (`verifying_officer_approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.leave_requests: ~5 rows (approximately)
INSERT INTO `leave_requests` (`id`, `personnel_id`, `leave_type`, `start_date`, `end_date`, `leave_days`, `reason`, `initiating_officer`, `accepting_officer`, `verifying_officer`, `verifying_officer_approved`, `verifying_officer_approved_by`, `verifying_officer_approved_at`, `verifying_officer_remarks`, `initiating_officer_approved`, `initiating_officer_approved_by`, `initiating_officer_approved_at`, `initiating_officer_remarks`, `accepting_officer_approved`, `accepting_officer_approved_by`, `accepting_officer_approved_at`, `accepting_officer_remarks`, `receiver_id`, `contact_number`, `alternate_officer`, `support_officer`, `finalizing_officer`, `status`, `created_by`, `approved_by`, `forwarded_by`, `forwarded_at`, `forwarded_to`, `forward_remarks`, `approved_at`, `approver_remarks`, `created_at`, `updated_at`) VALUES
	(72, '8572', 'gharpari_bida', '2026-06-04', '2026-06-04', 1, 'ok', '857', '8512', '8589', 1, NULL, '2026-06-04 10:30:41', 'ok', 1, NULL, '2026-06-04 10:31:18', 'ok', 1, NULL, '2026-06-04 10:31:38', 'okok', '8589', NULL, NULL, NULL, NULL, 'approved', '8589', NULL, NULL, NULL, NULL, NULL, '2026-06-04 10:31:38', NULL, '2026-06-04 04:45:09', '2026-06-04 04:46:38'),
	(73, '8572', 'gharpari_bida', '2026-06-26', '2026-06-27', 2, 'ok', '857', '8512', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8572', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:53:08', '2026-06-04 04:53:08'),
	(74, '8571', 'gharpari_bida', '2026-06-04', '2026-06-04', 1, 'ok', '8512', '8589', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8512', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 09:18:24', '2026-06-04 09:21:03'),
	(75, '8572', 'bhaeepari_bida', '2026-06-21', '2026-06-22', 2, 'ok', '8571', '8512', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8572', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 10:30:55', '2026-06-04 10:30:55'),
	(76, '8512', 'gharpari_bida', '2026-06-24', '2026-06-26', 3, 'ok', '8572', '8571', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8572', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 10:31:26', '2026-06-04 10:31:26'),
	(77, '8571', 'parba_bida', '2026-06-23', '2026-06-26', 4, 'ok', '8572', '8512', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8572', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 10:32:03', '2026-06-04 10:32:03');

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

-- Dumping structure for table hr.leave_types
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_name` varchar(100) NOT NULL,
  `available_days` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_name` (`leave_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.leave_types: ~0 rows (approximately)

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
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.military_personnel_status: ~0 rows (approximately)

-- Dumping structure for table hr.personnel
CREATE TABLE IF NOT EXISTS `personnel` (
  `personnel_number` varchar(20) NOT NULL,
  `full_name_en` varchar(100) DEFAULT NULL,
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
  `appointment` varchar(255) DEFAULT NULL,
  `profile_picture_path` varchar(255) DEFAULT NULL,
  `received_person_name` varchar(255) DEFAULT NULL,
  `professional_trainings` longtext DEFAULT NULL COMMENT 'Dynamic professional trainings stored as JSON array' CHECK (json_valid(`professional_trainings`)),
  `foreign_trainings` longtext DEFAULT NULL COMMENT 'Dynamic foreign trainings stored as JSON array' CHECK (json_valid(`foreign_trainings`)),
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
INSERT INTO `personnel` (`personnel_number`, `full_name_en`, `full_name_ne`, `dob`, `gender`, `blood_group`, `rank`, `current_status`, `unit`, `email`, `signature`, `joint_date`, `contact`, `phone`, `address`, `province`, `district`, `municipality`, `ward_number`, `village_tole`, `religion`, `military_status`, `password`, `remember_token`, `recruitment_date`, `commission_date`, `father_name`, `mother_name`, `spouse_name`, `children_names`, `family_notes`, `grandfather_name`, `higher_education`, `military_trainings`, `training`, `training_address`, `training1`, `training1_address`, `training2`, `training2_address`, `training3`, `training4`, `training5`, `foreign_training`, `created_at`, `updated_at`, `role`, `password_updated_at`, `appointment`, `profile_picture_path`, `received_person_name`, `professional_trainings`, `foreign_trainings`) VALUES
	('8512', 'Suraj shahi', NULL, NULL, 'Other', NULL, 'Lieutenant', 'Active', 'Artillery', 'superadmin@gmail.com', '/uploads/signatures/8512_signature_1780548947.png', '2026-06-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$5XeiiICmCOyh6r7PAPibXOLGaK5R0JP.BqofEoNhHWRiJLwkr54tW', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:38:43', '2026-06-04 04:55:47', 2, NULL, NULL, 'uploads/profile_photos/8512_photo_1780548939.jpeg', NULL, NULL, NULL),
	('8571', 'Dikshya Thapa', NULL, NULL, 'Other', NULL, 'Lieutenant', 'Active', 'Intelligence Corps', 'admin@gmail.com', '/uploads/signatures/857_signature_1780548928.png', '2026-06-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$nIOXIsopIfey72s7mlLKjeloQjARQcfuc33sOB2OKXMGBUpYv/VdC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:40:59', '2026-06-04 09:20:02', 1, NULL, NULL, 'uploads/profile_photos/857_photo_1780548918.jpeg', NULL, NULL, NULL),
	('8572', 'Susmin Basnet', NULL, NULL, 'Other', NULL, 'Lieutenant', 'Active', 'Signals', 'user@gmail.com', '/uploads/signatures/8572_signature_1780548905.png', '2026-06-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$pcmPO8kxrTAfbddmTcfkbe5k90pl6tu/LGM04nTvG1ufmy4A6Hhcm', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:41:51', '2026-06-04 04:55:05', 0, NULL, NULL, 'uploads/profile_photos/8572_photo_1780548896.jpeg', NULL, NULL, NULL),
	('8589', 'Manish Ghimire', NULL, NULL, 'Other', NULL, 'Lieutenant Colonel', 'Active', 'Corps of Engineers', 'manish@gmail.com', '/uploads/signatures/8589_signature_1780548879.png', '2026-06-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$JCIm.C8mfKlpBy6nV2hqTuchedQsgD6X2Cp7HXVE9/gJI8gOtYxCq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:43:09', '2026-06-04 04:54:39', 0, NULL, NULL, 'uploads/profile_photos/8589_photo_1780548865.jpeg', NULL, NULL, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
