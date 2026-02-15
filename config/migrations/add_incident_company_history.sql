-- Migration: Create incident company history table for audit trail
-- This table tracks when companies are added or removed from incidents

CREATE TABLE IF NOT EXISTS incident_company_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    company_id INT NOT NULL,
    action ENUM('added', 'removed') NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_incident_id (incident_id),
    INDEX idx_company_id (company_id),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
