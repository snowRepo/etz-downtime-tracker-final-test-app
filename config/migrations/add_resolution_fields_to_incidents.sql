-- Migration: Add missing columns for incident resolution
-- Date: 2026-02-11
-- Purpose: Add root_cause_file and lessons_learned columns to incidents table

ALTER TABLE incidents 
ADD COLUMN root_cause_file VARCHAR(255) NULL AFTER root_cause,
ADD COLUMN lessons_learned TEXT NULL AFTER root_cause_file,
ADD COLUMN lessons_learned_file VARCHAR(255) NULL AFTER lessons_learned;

-- Add comments for clarity
ALTER TABLE incidents 
MODIFY COLUMN root_cause_file VARCHAR(255) NULL COMMENT 'Path to uploaded root cause document',
MODIFY COLUMN lessons_learned TEXT NULL COMMENT 'Lessons learned from the incident',
MODIFY COLUMN lessons_learned_file VARCHAR(255) NULL COMMENT 'Path to uploaded lessons learned document';
