-- Migration: Add actual_start_time column to incidents table
-- Purpose: Allow users to specify when an incident actually occurred (vs when it was reported)
-- Date: 2026-01-25

-- Add the actual_start_time column
ALTER TABLE incidents 
ADD COLUMN actual_start_time DATETIME NULL AFTER attachment_path;

-- Add a comment to the column
ALTER TABLE incidents 
MODIFY COLUMN actual_start_time DATETIME NULL 
COMMENT 'The actual time when the incident occurred (user-specified, can be in the past)';

-- Update existing records to set actual_start_time to created_at
-- This ensures backward compatibility for existing incidents
UPDATE incidents 
SET actual_start_time = created_at 
WHERE actual_start_time IS NULL;

-- Now make the column NOT NULL with a default value
ALTER TABLE incidents 
MODIFY COLUMN actual_start_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Add an index for better query performance
CREATE INDEX idx_actual_start_time ON incidents(actual_start_time);
