-- Migration: Add lessons_learned column to incidents table
-- Purpose: Store lessons learned when resolving an incident
-- Date: 2026-02-06

-- Add the lessons_learned column
ALTER TABLE incidents 
ADD COLUMN lessons_learned TEXT NULL AFTER root_cause;

-- Add a comment to the column
ALTER TABLE incidents 
MODIFY COLUMN lessons_learned TEXT NULL 
COMMENT 'Lessons learned from the incident, filled when resolving';
