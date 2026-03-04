-- Create incident_templates table
-- Migration: Create incident templates for common issues
-- Date: 2026-02-07

CREATE TABLE IF NOT EXISTS incident_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL UNIQUE,
    service_id INT NULL,
    component_id INT NULL,
    incident_type_id INT NULL,
    impact_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    description TEXT NOT NULL,
    root_cause TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    usage_count INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE SET NULL,
    FOREIGN KEY (component_id) REFERENCES service_components(component_id) ON DELETE SET NULL,
    FOREIGN KEY (incident_type_id) REFERENCES incident_types(type_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    
    INDEX idx_service (service_id),
    INDEX idx_active (is_active),
    INDEX idx_usage (usage_count),
    INDEX idx_incident_type (incident_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample templates
INSERT INTO incident_templates (template_name, service_id, component_id, incident_type_id, impact_level, description, root_cause, created_by) VALUES
(
    'Database Connection Timeout',
    NULL, NULL, NULL,
    'high',
    'Users are experiencing login failures and transaction errors due to database connection timeouts. The application cannot establish connections to the primary database server. This is affecting all services that rely on the database.',
    'Connection pool exhausted - maximum connections reached during peak load',
    (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1)
),
(
    'API Gateway Service Down',
    NULL, NULL, NULL,
    'critical',
    'API requests are failing with 503 Service Unavailable errors. All services dependent on the API gateway are affected. Users cannot access any features that require API calls.',
    'API Gateway service crashed due to Out of Memory (OOM) error',
    (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1)
),
(
    'Scheduled System Maintenance',
    NULL, NULL, NULL,
    'low',
    'Scheduled maintenance window for system updates and patches. Services may experience brief interruptions during deployment. Users have been notified in advance.',
    'Planned maintenance activity - no incident, informational only',
    (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1)
),
(
    'Network Connectivity Issue',
    NULL, NULL, NULL,
    'high',
    'Users are experiencing intermittent connectivity issues and slow response times. Network latency has increased significantly. This is affecting multiple services across the infrastructure.',
    'Network switch configuration issue after recent update',
    (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1)
),
(
    'Authentication Service Failure',
    NULL, NULL, NULL,
    'critical',
    'Users are unable to log in to any services. Authentication requests are timing out. This is a complete authentication system outage affecting all users.',
    'LDAP server connection failure - primary authentication server unreachable',
    (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1)
);
