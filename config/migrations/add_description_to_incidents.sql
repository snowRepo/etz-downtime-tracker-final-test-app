-- Migration: Add description column to incidents table
-- Date: 2026-02-10
-- Purpose: Add incident description field to capture detailed incident information

ALTER TABLE incidents 
ADD COLUMN description TEXT NULL 
AFTER incident_type_id;

-- Update comment for clarity
ALTER TABLE incidents 
MODIFY COLUMN description TEXT NULL COMMENT 'Detailed description of what happened during the incident';
