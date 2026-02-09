-- Migration: Add priority column to incidents table
-- Created: 2026-02-07

ALTER TABLE `incidents` 
ADD COLUMN `priority` ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium' 
AFTER `impact_level`;

-- Add index for priority filtering
CREATE INDEX `idx_priority` ON `incidents` (`priority`);
