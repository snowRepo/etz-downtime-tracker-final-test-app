-- Migration: Add incident_type_id to incident_templates
-- Date: 2026-03-04

ALTER TABLE `incident_templates`
    ADD COLUMN `incident_type_id` INT NULL AFTER `component_id`,
    ADD CONSTRAINT `fk_template_incident_type`
        FOREIGN KEY (`incident_type_id`) REFERENCES `incident_types` (`type_id`) ON DELETE SET NULL;

-- Add index for the new FK
ALTER TABLE `incident_templates`
    ADD INDEX `idx_incident_type` (`incident_type_id`);
