-- ETZ Downtime Tracker Database Schema
-- Generated: 2026-01-27
-- MySQL 8.0.44

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================
-- Table: users
-- ============================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `changed_password` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `unique_username` (`username`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample admin user (password: admin123)
INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`, `changed_password`) VALUES
(9, 'admin', 'admin@etranzact.com', '$2y$12$Uve0lGdSjaf5aEfpMf3gu.DPbqrLc.zTK5bY98cjMWozW44wuOOXC', 'Admin User', 'admin', 1, '2026-01-27 07:47:01', '2026-01-23 09:28:18', '2026-01-27 07:47:01', 0);

-- ============================================
-- Table: activity_logs
-- ============================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample activity logs
INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(47, 9, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 09:29:43'),
(48, 9, 'incident_created', 'Reported multiple incidents: Multiple Services (1)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:01:04'),
(49, 9, 'incident_created', 'Reported multiple incidents: Multiple Services (1)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:07:06'),
(50, 9, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:50:09'),
(51, 9, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:50:46'),
(52, 9, 'user_created', 'Created new user: eric.arthur', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:56:11'),
(53, 9, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 11:27:52'),
(57, 9, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 19:46:11'),
(58, 9, 'user_created', 'Created new user: cyber.nii', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 19:58:05'),
(59, 9, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 19:58:12'),
(62, 9, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 20:05:17'),
(63, 9, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-25 09:45:14'),
(64, 9, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-25 09:46:24'),
(65, 9, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-26 08:01:10'),
(66, 9, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-26 08:01:27'),
(67, 9, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-26 08:12:13'),
(68, 9, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-27 07:47:01'),
(69, 9, 'incident_created', 'Reported multiple incidents: Multiple Services (1)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-27 07:49:20');

-- ============================================
-- Table: companies
-- ============================================
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `company_id` int NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `companies` (`company_id`, `company_name`, `category`, `created_at`, `updated_at`) VALUES
(1, 'Abii National', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(2, 'AirtelTigo', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(3, 'All', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(4, 'Atwima', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(5, 'Bestpoint', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(6, 'BOA', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(7, 'ECG', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(8, 'eTranzanct', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(9, 'MTN', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(10, 'Multi Choice', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(11, 'NIB', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(12, 'NLA', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(13, 'PBL', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(14, 'SISL', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(15, 'STCCU', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(16, 'Telecel', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(17, 'VisionFund', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23');

-- ============================================
-- Table: services
-- ============================================
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `service_id` int NOT NULL AUTO_INCREMENT,
  `service_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `services` (`service_id`, `service_name`, `created_at`, `updated_at`) VALUES
(1, 'Mobile Money', '2025-12-10 13:33:46', '2025-12-10 13:33:46');

-- ============================================
-- Table: service_components
-- ============================================
DROP TABLE IF EXISTS `service_components`;
CREATE TABLE `service_components` (
  `component_id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`component_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `fk_component_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `service_components` (`component_id`, `service_id`, `name`, `is_active`) VALUES
(14, 1, 'MTN', 1),
(15, 1, 'Telecel', 1),
(16, 1, 'AT', 1);

-- ============================================
-- Table: incident_types
-- ============================================
DROP TABLE IF EXISTS `incident_types`;
CREATE TABLE `incident_types` (
  `type_id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`type_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `fk_type_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `incident_types` (`type_id`, `service_id`, `name`, `is_active`) VALUES
(13, 1, 'Credit', 1),
(14, 1, 'Debit', 1),
(15, 1, 'Double Deduction', 1),
(16, 1, 'Callback', 1);

-- ============================================
-- Table: incidents
-- ============================================
DROP TABLE IF EXISTS `incidents`;
CREATE TABLE `incidents` (
  `incident_id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `component_id` int DEFAULT NULL,
  `incident_type_id` int DEFAULT NULL,
  `impact_level` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',
  `root_cause` text,
  `attachment_path` varchar(255) DEFAULT NULL,
  `actual_start_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
  `reported_by` int NOT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`incident_id`),
  KEY `service_id` (`service_id`),
  KEY `component_id` (`component_id`),
  KEY `incident_type_id` (`incident_type_id`),
  KEY `reported_by` (`reported_by`),
  KEY `idx_actual_start_time` (`actual_start_time`),
  CONSTRAINT `fk_incident_component` FOREIGN KEY (`component_id`) REFERENCES `service_components` (`component_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_incident_reporter` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_incident_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_incident_type` FOREIGN KEY (`incident_type_id`) REFERENCES `incident_types` (`type_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `incidents` (`incident_id`, `service_id`, `component_id`, `incident_type_id`, `impact_level`, `root_cause`, `attachment_path`, `actual_start_time`, `status`, `reported_by`, `resolved_by`, `resolved_at`, `created_at`, `updated_at`) VALUES
(7, 1, 14, 13, 'High', 'Root cause unknown', 'uploads/incidents/fd7f2fddfa2f87ed6d7db408f59899d6.pdf', '2026-01-26 10:00:00', 'resolved', 9, 9, '2026-01-27 07:50:30', '2026-01-27 07:49:20', '2026-01-27 07:50:30');

-- ============================================
-- Table: incident_affected_companies
-- ============================================
DROP TABLE IF EXISTS `incident_affected_companies`;
CREATE TABLE `incident_affected_companies` (
  `incident_id` int NOT NULL,
  `company_id` int NOT NULL,
  PRIMARY KEY (`incident_id`,`company_id`),
  KEY `fk_iac_company` (`company_id`),
  CONSTRAINT `fk_iac_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_iac_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `incident_affected_companies` (`incident_id`, `company_id`) VALUES
(7, 1),
(7, 4);

-- ============================================
-- Table: incident_updates
-- ============================================
DROP TABLE IF EXISTS `incident_updates`;
CREATE TABLE `incident_updates` (
  `update_id` int NOT NULL AUTO_INCREMENT,
  `incident_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) NOT NULL,
  `update_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_id`),
  KEY `incident_id` (`incident_id`),
  CONSTRAINT `fk_updates_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `incident_updates` (`update_id`, `incident_id`, `user_id`, `user_name`, `update_text`, `created_at`) VALUES
(3, 7, 9, 'System', 'Incident has been marked as resolved by Admin User', '2026-01-27 07:50:30');

-- ============================================
-- Table: downtime_incidents
-- ============================================
DROP TABLE IF EXISTS `downtime_incidents`;
CREATE TABLE `downtime_incidents` (
  `downtime_id` int NOT NULL AUTO_INCREMENT,
  `incident_id` int NOT NULL,
  `actual_start_time` datetime NOT NULL,
  `actual_end_time` datetime DEFAULT NULL,
  `downtime_minutes` int DEFAULT NULL,
  `is_planned` tinyint(1) DEFAULT '0',
  `downtime_category` enum('Network','Server','Maintenance','Third-party','Other') DEFAULT 'Other',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`downtime_id`),
  KEY `incident_id` (`incident_id`),
  CONSTRAINT `fk_downtime_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `downtime_incidents` (`downtime_id`, `incident_id`, `actual_start_time`, `actual_end_time`, `downtime_minutes`, `is_planned`, `downtime_category`, `created_at`, `updated_at`) VALUES
(7, 7, '2026-01-26 10:00:00', NULL, NULL, 0, 'Network', '2026-01-27 07:49:20', '2026-01-27 07:49:20');

-- ============================================
-- Table: sla_targets
-- ============================================
DROP TABLE IF EXISTS `sla_targets`;
CREATE TABLE `sla_targets` (
  `target_id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `service_id` int DEFAULT NULL,
  `target_uptime` decimal(5,2) NOT NULL DEFAULT '99.99',
  `business_hours_start` time DEFAULT '09:00:00',
  `business_hours_end` time DEFAULT '17:00:00',
  `business_days` set('Mon','Tue','Wed','Thu','Fri','Sat','Sun') DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`target_id`),
  UNIQUE KEY `unique_company_service` (`company_id`,`service_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `fk_sla_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sla_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Triggers
-- ============================================
DELIMITER ;;

CREATE TRIGGER `calculate_downtime_minutes` BEFORE UPDATE ON `downtime_incidents` FOR EACH ROW
BEGIN
    IF NEW.actual_end_time IS NOT NULL AND (OLD.actual_end_time IS NULL OR NEW.actual_end_time != OLD.actual_end_time) THEN
        SET NEW.downtime_minutes = TIMESTAMPDIFF(MINUTE, NEW.actual_start_time, NEW.actual_end_time);
    END IF;
END;;

DELIMITER ;

-- ============================================
-- Re-enable foreign key checks
-- ============================================
SET foreign_key_checks = 1;
