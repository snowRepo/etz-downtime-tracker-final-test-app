-- Migration: Create Activity Logs Table
-- Description: Creates the activity_logs table for tracking all user actions and system events
-- Created: 2026-01-23

-- Create activity_logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'User who performed the action (NULL for system actions)',
    username VARCHAR(50) NULL COMMENT 'Username at time of action (preserved even if user deleted)',
    action_type ENUM(
        'login', 'logout', 'login_failed',
        'user_created', 'user_updated', 'user_deleted', 'user_role_changed',
        'incident_created', 'incident_updated', 'incident_deleted',
        'incident_viewed', 'incident_exported',
        'report_generated', 'report_exported',
        'analytics_viewed', 'analytics_exported',
        'sla_report_viewed', 'sla_report_exported',
        'password_changed', 'profile_updated',
        'settings_changed', 'other'
    ) NOT NULL COMMENT 'Type of action performed',
    entity_type VARCHAR(50) NULL COMMENT 'Type of entity affected (user, incident, etc.)',
    entity_id INT NULL COMMENT 'ID of the affected entity',
    description TEXT NOT NULL COMMENT 'Human-readable description of the action',
    ip_address VARCHAR(45) NULL COMMENT 'IP address of the user (supports IPv4 and IPv6)',
    user_agent TEXT NULL COMMENT 'Browser/client user agent string',
    metadata JSON NULL COMMENT 'Additional context data in JSON format',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action occurred',
    
    -- Indexes for performance
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_username (username),
    
    -- Foreign key (SET NULL on delete to preserve logs even if user is deleted)
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Activity logs for audit trail and user action tracking';

-- Insert initial log entry for migration
INSERT INTO activity_logs (
    user_id, 
    username, 
    action_type, 
    description, 
    ip_address
) VALUES (
    NULL,
    'system',
    'other',
    'Activity logging system initialized - migration 002 completed',
    '127.0.0.1'
);
