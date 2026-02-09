-- Migration: Add lessons learned, root cause file support, and incident attachments table
-- Created: 2026-02-07
-- Description: Adds support for lessons learned, file uploads for root cause and lessons learned,
--              and multiple file attachments for incidents

-- Add new columns to incidents table
ALTER TABLE `incidents` 
ADD COLUMN `root_cause_file` VARCHAR(255) DEFAULT NULL AFTER `root_cause`,
ADD COLUMN `lessons_learned` TEXT DEFAULT NULL AFTER `root_cause_file`,
ADD COLUMN `lessons_learned_file` VARCHAR(255) DEFAULT NULL AFTER `lessons_learned`;

-- Create incident_attachments table for multiple file uploads
CREATE TABLE `incident_attachments` (
  `attachment_id` INT NOT NULL AUTO_INCREMENT,
  `incident_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(50) DEFAULT NULL,
  `file_size` INT DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  KEY `idx_incident_id` (`incident_id`),
  CONSTRAINT `fk_attachment_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Rollback instructions (commented out):
-- To rollback this migration, run:
-- DROP TABLE IF EXISTS `incident_attachments`;
-- ALTER TABLE `incidents` 
-- DROP COLUMN `lessons_learned_file`,
-- DROP COLUMN `lessons_learned`,
-- DROP COLUMN `root_cause_file`;
