-- Add mobile_number column to users table
-- Column is nullable; existing users will have NULL until updated
ALTER TABLE users
  ADD COLUMN mobile_number VARCHAR(20) NULL AFTER full_name;
