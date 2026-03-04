CREATE TABLE IF NOT EXISTS `incident_officers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `incident_id` INT(11) NOT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `external_name` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
