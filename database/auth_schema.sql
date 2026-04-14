-- Authentication System Database Schema
-- Production-ready with security features

USE `furniture_erp`;

-- Roles table
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO `roles` (`role_name`, `description`) VALUES
('admin', 'System Administrator'),
('manager', 'Production Manager'),
('employee', 'Production Employee'),
('customer', 'Customer')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Update users table structure
ALTER TABLE `furn_users` 
ADD COLUMN IF NOT EXISTS `role_id` INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `full_name` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `status` ENUM('active','inactive','suspended') DEFAULT 'active',
ADD COLUMN IF NOT EXISTS `failed_attempts` INT(11) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `password_reset_token` VARCHAR(255) NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `password_reset_expires` DATETIME NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `remember_token` VARCHAR(255) NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `ip_address` VARCHAR(45) NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `user_agent` VARCHAR(255) NULL DEFAULT NULL;

-- Foreign key for role_id already exists, skipping

-- Update existing users to have role_id based on role column
UPDATE `furn_users` u
JOIN `roles` r ON u.role = r.role_name
SET u.role_id = r.id
WHERE u.role_id IS NULL;

-- Activity logs table for security auditing
CREATE TABLE IF NOT EXISTS `furn_activity_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `activity_type` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_activity_type` (`activity_type`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session management table
CREATE TABLE IF NOT EXISTS `furn_sessions` (
    `id` VARCHAR(128) NOT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `payload` TEXT,
    `last_activity` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for performance
CREATE INDEX IF NOT EXISTS `idx_email` ON `furn_users` (`email`);
CREATE INDEX IF NOT EXISTS `idx_status` ON `furn_users` (`status`);
CREATE INDEX IF NOT EXISTS `idx_failed_attempts` ON `furn_users` (`failed_attempts`);
CREATE INDEX IF NOT EXISTS `idx_password_reset_token` ON `furn_users` (`password_reset_token`);

