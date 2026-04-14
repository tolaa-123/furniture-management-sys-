SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
USE `furniture_erp`;

-- === database/schema.sql ===
-- Custom Furniture ERP System Database Schema
-- Section 4: Manager Cost Approval Workflow

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `furniture_erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `furniture_erp`;

-- Users table with role-based access
CREATE TABLE IF NOT EXISTS `furn_users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'manager', 'employee', 'customer') NOT NULL DEFAULT 'customer',
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20),
    `address` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_role` (`role`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product categories
CREATE TABLE IF NOT EXISTS `furn_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE IF NOT EXISTS `furn_products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `base_price` DECIMAL(10,2) NOT NULL,
    `estimated_production_time` INT(11) NOT NULL COMMENT 'In days',
    `materials_used` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_category` (`category_id`),
    CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `furn_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product images
CREATE TABLE IF NOT EXISTS `furn_product_images` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_product_image` (`product_id`),
    CONSTRAINT `fk_product_image` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE IF NOT EXISTS `furn_orders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `order_number` VARCHAR(20) NOT NULL UNIQUE,
    `status` ENUM('pending_cost_approval', 'waiting_for_deposit', 'deposit_paid', 'in_production', 'ready_for_delivery', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_cost_approval',
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deposit_amount` DECIMAL(10,2) DEFAULT NULL,
    `deposit_paid` DECIMAL(10,2) DEFAULT NULL,
    `deposit_paid_at` TIMESTAMP NULL DEFAULT NULL,
    `production_started_at` TIMESTAMP NULL DEFAULT NULL,
    `production_completed_at` TIMESTAMP NULL DEFAULT NULL,
    `delivery_date` DATE NULL DEFAULT NULL,
    `special_instructions` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_customer` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_order_number` (`order_number`),
    CONSTRAINT `fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order customizations
CREATE TABLE IF NOT EXISTS `furn_order_customizations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `size_modifications` TEXT,
    `color_selection` VARCHAR(50),
    `material_upgrade` VARCHAR(100),
    `additional_features` TEXT,
    `reference_image_path` VARCHAR(255),
    `notes` TEXT,
    `base_price` DECIMAL(10,2) NOT NULL,
    `adjusted_price` DECIMAL(10,2) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_order` (`order_id`),
    KEY `fk_product_customization` (`product_id`),
    CONSTRAINT `fk_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_customization` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs for critical actions
CREATE TABLE IF NOT EXISTS `furn_audit_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` INT(11) DEFAULT NULL,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_audit_user` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_table_record` (`table_name`, `record_id`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT IGNORE INTO `furn_categories` (`name`, `description`) VALUES
('Sofa', 'Comfortable seating solutions for living rooms'),
('Bed', 'Quality beds and bedroom furniture'),
('Table', 'Dining tables, coffee tables, and work desks'),
('Chair', 'Various types of chairs for different purposes');

-- Insert sample products
INSERT IGNORE INTO `furn_products` (`category_id`, `name`, `description`, `base_price`, `estimated_production_time`, `materials_used`) VALUES
(1, 'Premium Leather Sofa', 'Luxurious 3-seater leather sofa with premium cushioning', 15000.00, 21, 'Full-grain leather, solid wood frame, high-density foam'),
(2, 'King Size Wooden Bed', 'Handcrafted king size bed with premium wood finish', 25000.00, 30, 'Solid oak wood, premium mattress support, eco-friendly finish'),
(3, 'Modern Dining Table', 'Contemporary 6-seater dining table with glass top', 12000.00, 18, 'Tempered glass, stainless steel frame, wooden base'),
(4, 'Executive Office Chair', 'Ergonomic office chair with lumbar support', 3500.00, 10, 'Premium fabric, steel frame, adjustable mechanisms');

-- Insert sample admin user (password: admin123)
INSERT IGNORE INTO `furn_users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`) VALUES
('admin', 'admin@furniture.com', '$2y$10$R9Ene/egMPkE7i6wmR72puQYarFUGYn1EIORQ8ud67DhEbj7S/Ul2', 'admin', 'System', 'Administrator', 1);

-- Insert sample manager user (password: manager123)
INSERT IGNORE INTO `furn_users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`) VALUES
('manager', 'manager@furniture.com', '$2y$10$mGabhp3LuWe8ZQF5oYcX/.5uZXivrmmWfMFgwWv4qa.qOmei7aVGO', 'manager', 'John', 'Manager', 1);

-- Insert sample customer (password: customer123)
INSERT IGNORE INTO `furn_users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `phone`, `address`, `is_active`) VALUES
('customer', 'customer@furniture.com', '$2y$10$hSWa.iw9zcaOZSePhhISj.glaGDYPVsems6dXouFvTWv8M9.Vh4MK', 'customer', 'Alice', 'Customer', '+251911123456', 'Addis Ababa, Ethiopia', 1);

-- Insert sample employee (password: employee123)
INSERT IGNORE INTO `furn_users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`) VALUES
('employee', 'employee@furniture.com', '$2y$10$J0d9EGNvRolPUg9qOfY6NOy/LDkG5acfF.wlbfjesFx3/Dvn2yN5e', 'employee', 'John', 'Employee', 1);

COMMIT;

-- === database/auth_schema.sql ===
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
INSERT IGNORE INTO `roles` (`role_name`, `description`) VALUES
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



-- === database/settings_schema.sql ===
-- Admin Settings Database Schema
-- Run this to create necessary tables for admin settings

-- 1. System Settings Table
CREATE TABLE IF NOT EXISTS furn_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    category VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Company Information Table
CREATE TABLE IF NOT EXISTS furn_company_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Ethiopia',
    phone_primary VARCHAR(20),
    phone_secondary VARCHAR(20),
    email_primary VARCHAR(100),
    email_secondary VARCHAR(100),
    website VARCHAR(255),
    logo_path VARCHAR(255),
    tax_id VARCHAR(50),
    registration_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Email Configuration Table
CREATE TABLE IF NOT EXISTS furn_email_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    smtp_encryption ENUM('none', 'tls', 'ssl') DEFAULT 'tls',
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) DEFAULT 'FurnitureCraft Workshop',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tax Configuration Table
CREATE TABLE IF NOT EXISTS furn_tax_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_name VARCHAR(100) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL,
    tax_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    is_compound BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Payment Methods Table
CREATE TABLE IF NOT EXISTS furn_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(100) NOT NULL,
    method_type ENUM('cash', 'bank_transfer', 'card', 'mobile_money', 'check', 'other') DEFAULT 'cash',
    account_details TEXT,
    instructions TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Settings
INSERT INTO furn_settings (setting_key, setting_value, setting_type, category) VALUES
-- General Settings
('site_name', 'FurnitureCraft Workshop', 'text', 'general'),
('currency', 'ETB', 'text', 'general'),
('timezone', 'Africa/Addis_Ababa', 'text', 'general'),
('date_format', 'Y-m-d', 'text', 'general'),
('language', 'en', 'text', 'general'),

-- Business Settings
('fiscal_year_start', '01-01', 'text', 'business'),
('default_deposit_percentage', '50', 'number', 'business'),
('allow_backorders', '0', 'boolean', 'business'),
('auto_approve_orders', '0', 'boolean', 'business'),

-- Notification Settings
('email_notifications', '1', 'boolean', 'notifications'),
('sms_notifications', '0', 'boolean', 'notifications'),
('order_confirmation_email', '1', 'boolean', 'notifications'),
('order_status_updates', '1', 'boolean', 'notifications'),
('payment_received_email', '1', 'boolean', 'notifications'),
('low_stock_alert', '1', 'boolean', 'notifications'),
('new_order_alert', '1', 'boolean', 'notifications'),

-- Security Settings
('session_timeout', '3600', 'number', 'security'),
('password_min_length', '8', 'number', 'security'),
('require_special_char', '1', 'boolean', 'security'),
('max_login_attempts', '5', 'number', 'security'),
('lockout_duration', '900', 'number', 'security'),

-- System Settings
('maintenance_mode', '0', 'boolean', 'system'),
('cache_enabled', '1', 'boolean', 'system'),
('debug_mode', '0', 'boolean', 'system'),
('log_retention_days', '30', 'number', 'system')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Insert Default Company Info
INSERT INTO furn_company_info (
    company_name, address_line1, city, country, phone_primary, 
    email_primary, website
) VALUES (
    'FurnitureCraft Workshop',
    'Addis Ababa',
    'Addis Ababa',
    'Ethiopia',
    '+251-911-123456',
    'info@furniturecraft.com',
    'www.furniturecraft.com'
) ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

-- Insert Default Tax Configuration
INSERT INTO furn_tax_config (tax_name, tax_rate, tax_type, is_active) VALUES
('VAT', 15.00, 'percentage', TRUE),
('Service Charge', 10.00, 'percentage', FALSE)
ON DUPLICATE KEY UPDATE tax_name = VALUES(tax_name);

-- Insert Default Payment Methods
INSERT INTO furn_payment_methods (method_name, method_type, is_active, display_order) VALUES
('Cash', 'cash', TRUE, 1),
('Bank Transfer', 'bank_transfer', TRUE, 2),
('CBE Birr', 'mobile_money', TRUE, 3),
('Telebirr', 'mobile_money', TRUE, 4),
('Check', 'check', TRUE, 5)
ON DUPLICATE KEY UPDATE method_name = VALUES(method_name);


-- === database/contact_messages.sql ===
-- Contact Messages Database Table
-- Create this table in your MySQL database

CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('new', 'read', 'replied') DEFAULT 'new',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_contact_email` ON `contact_messages` (`email`);
CREATE INDEX IF NOT EXISTS `idx_contact_status` ON `contact_messages` (`status`);
CREATE INDEX IF NOT EXISTS `idx_contact_created` ON `contact_messages` (`created_at`);


-- === database/attendance_schema.sql ===
-- Section 8: Attendance System Database Schema
-- Employee attendance tracking with time and IP validation

USE furniture_erp;

-- Create attendance table
CREATE TABLE IF NOT EXISTS `furn_attendance` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `check_in_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `is_late` TINYINT(1) NOT NULL DEFAULT 0,
    `late_minutes` INT(11) DEFAULT NULL,
    `status` ENUM('present', 'late', 'absent') NOT NULL DEFAULT 'present',
    `notes` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_date` (`employee_id`, `check_in_time`),
    KEY `fk_attendance_employee` (`employee_id`),
    CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create attendance settings table for configuration
CREATE TABLE IF NOT EXISTS `furn_attendance_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create attendance reports table for monthly/yearly summaries
CREATE TABLE IF NOT EXISTS `furn_attendance_reports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `report_month` DATE NOT NULL, -- First day of month
    `total_days` INT(11) NOT NULL DEFAULT 0,
    `present_days` INT(11) NOT NULL DEFAULT 0,
    `late_days` INT(11) NOT NULL DEFAULT 0,
    `absent_days` INT(11) NOT NULL DEFAULT 0,
    `total_late_minutes` INT(11) NOT NULL DEFAULT 0,
    `attendance_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_month` (`employee_id`, `report_month`),
    KEY `fk_report_employee` (`employee_id`),
    CONSTRAINT `fk_report_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default attendance settings
INSERT IGNORE INTO `furn_attendance_settings` (`setting_key`, `setting_value`, `description`) VALUES
('check_in_start_time', '07:00:00', 'Daily check-in start time (24-hour format)'),
('check_in_end_time', '09:00:00', 'Daily check-in end time (24-hour format)'),
('company_ip_address', '192.168.1.100', 'Authorized company IP address for check-in'),
('late_threshold_minutes', '30', 'Minutes after start time to mark as late'),
('working_days_per_month', '22', 'Expected working days per month for calculations');

-- Add attendance-related columns to users table if needed
ALTER TABLE `furn_users` 
ADD COLUMN IF NOT EXISTS `employee_id` VARCHAR(20) UNIQUE DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `department` VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `position` VARCHAR(50) DEFAULT NULL;

-- Add missing columns to furn_attendance if needed
ALTER TABLE `furn_attendance`
ADD COLUMN IF NOT EXISTS `is_late` TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS `late_minutes` INT(11) DEFAULT NULL;

-- Update existing employees with sample data
UPDATE `furn_users` SET 
    `employee_id` = CONCAT('EMP', LPAD(`id`, 4, '0')),
    `department` = 'Production',
    `position` = 'Worker'
WHERE `role` = 'employee' AND `employee_id` IS NULL;

-- Create view for attendance summary
CREATE OR REPLACE VIEW `vw_attendance_summary` AS
SELECT 
    u.id as employee_id,
    u.employee_id as emp_code,
    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
    u.department,
    u.position,
    a.check_in_time,
    a.ip_address,
    a.status,
    DATE(a.check_in_time) as attendance_date
FROM furn_users u
LEFT JOIN furn_attendance a ON u.id = a.employee_id
WHERE u.role = 'employee' OR u.role = 'manager';

-- Add audit log entries
INSERT IGNORE INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'attendance_system_created', 'database', 1, '{"message": "Attendance system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_users', NULL, '{"columns_added": ["employee_id", "department", "position"]}', NOW());


-- === database/attendance_disputes.sql ===
-- Attendance dispute/question table
USE furniture_erp;

CREATE TABLE IF NOT EXISTS `furn_attendance_disputes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `attendance_id` INT(11) NOT NULL,
    `employee_id` INT(11) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
    `manager_reply` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_employee` (`employee_id`),
    KEY `idx_attendance` (`attendance_id`),
    CONSTRAINT `fk_dispute_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `furn_attendance` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dispute_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- === database/employee_reports_schema.sql ===
-- Employee General Reports Table
CREATE TABLE IF NOT EXISTS `furn_employee_reports` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id`   INT NOT NULL,
    `report_type`   ENUM('task_progress','material_usage','incident','daily_summary','leave_request') NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `report_data`   JSON NOT NULL COMMENT 'Flexible JSON payload per report type',
    `status`        ENUM('submitted','reviewed','acknowledged') DEFAULT 'submitted',
    `manager_note`  TEXT DEFAULT NULL,
    `reviewed_by`   INT DEFAULT NULL,
    `reviewed_at`   DATETIME DEFAULT NULL,
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `furn_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- === database/activity_logs_schema.sql ===
-- Activity Logs Table for SmartWorkshop ERP
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(action)
);

-- === database/invoice_schema.sql ===
-- Professional PDF Invoice System Database Schema
-- Invoice generation and management system

USE furniture_erp;

-- Create invoice configuration table
CREATE TABLE IF NOT EXISTS `furn_invoice_config` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `company_name` VARCHAR(100) NOT NULL,
    `company_address` TEXT NOT NULL,
    `company_phone` VARCHAR(20) DEFAULT NULL,
    `company_email` VARCHAR(100) DEFAULT NULL,
    `company_website` VARCHAR(100) DEFAULT NULL,
    `tax_id` VARCHAR(50) DEFAULT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `bank_account_number` VARCHAR(50) NOT NULL,
    `bank_account_name` VARCHAR(100) NOT NULL,
    `bank_branch` VARCHAR(100) DEFAULT NULL,
    `swift_code` VARCHAR(20) DEFAULT NULL,
    `logo_path` VARCHAR(255) DEFAULT NULL,
    `invoice_prefix` VARCHAR(10) NOT NULL DEFAULT 'INV',
    `next_invoice_number` INT(11) NOT NULL DEFAULT 1001,
    `due_days` INT(11) NOT NULL DEFAULT 30,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'ETB',
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create invoices table
CREATE TABLE IF NOT EXISTS `furn_invoices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(20) NOT NULL UNIQUE,
    `order_id` INT(11) NOT NULL,
    `customer_id` INT(11) NOT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `deposit_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `remaining_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft',
    `pdf_path` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_invoice_order` (`order_id`),
    KEY `fk_invoice_customer` (`customer_id`),
    KEY `fk_invoice_created_by` (`created_by`),
    CONSTRAINT `fk_invoice_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoice_created_by` FOREIGN KEY (`created_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create invoice items table
CREATE TABLE IF NOT EXISTS `furn_invoice_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` INT(11) NOT NULL,
    `product_id` INT(11) DEFAULT NULL,
    `description` TEXT NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(12,2) NOT NULL,
    `total_price` DECIMAL(12,2) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_invoice_item_invoice` (`invoice_id`),
    KEY `fk_invoice_item_product` (`product_id`),
    CONSTRAINT `fk_invoice_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `furn_invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoice_item_product` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create invoice payments table
CREATE TABLE IF NOT EXISTS `furn_invoice_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` INT(11) NOT NULL,
    `payment_date` DATE NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `reference_number` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_invoice_payment_invoice` (`invoice_id`),
    KEY `fk_invoice_payment_created_by` (`created_by`),
    CONSTRAINT `fk_invoice_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `furn_invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoice_payment_created_by` FOREIGN KEY (`created_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default company configuration
INSERT IGNORE INTO `furn_invoice_config` (
    `company_name`, 
    `company_address`, 
    `company_phone`, 
    `company_email`, 
    `bank_name`, 
    `bank_account_number`, 
    `bank_account_name`,
    `notes`
) VALUES (
    'Custom Furniture Co.',
    '123 Main Street, Addis Ababa, Ethiopia',
    '+251 11 123 4567',
    'info@customfurniture.com',
    'Commercial Bank of Ethiopia',
    '1000123456789',
    'Custom Furniture Co. Ltd',
    'Thank you for your business. Please make payment within 30 days.'
);

-- Add invoice-related columns to orders table
ALTER TABLE `furn_orders` 
ADD COLUMN IF NOT EXISTS `invoice_generated` TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS `invoice_id` INT(11) DEFAULT NULL;

-- Foreign key for invoice_id already handled

-- Create view for invoice overview
CREATE OR REPLACE VIEW `vw_invoice_overview` AS
SELECT 
    i.id,
    i.invoice_number,
    i.invoice_date,
    i.due_date,
    i.total_amount,
    i.deposit_paid,
    i.remaining_balance,
    i.status,
    o.order_number,
    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
    u.email as customer_email,
    u.phone as customer_phone,
    DATEDIFF(i.due_date, CURDATE()) as days_until_due,
    CASE 
        WHEN i.status = 'paid' THEN 'success'
        WHEN i.status = 'overdue' THEN 'danger'
        WHEN DATEDIFF(i.due_date, CURDATE()) <= 7 THEN 'warning'
        ELSE 'info'
    END as status_color
FROM furn_invoices i
JOIN furn_orders o ON i.order_id = o.id
JOIN furn_users u ON i.customer_id = u.id;

-- Add audit log entries
INSERT IGNORE INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'invoice_system_created', 'database', 1, '{"message": "Professional PDF invoice system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_orders', NULL, '{"columns_added": ["invoice_generated", "invoice_id"]}', NOW());



-- === database/payment_schema.sql ===
-- Payment Management Schema
-- Create payments table for custom furniture orders

USE `furniture_erp`;

-- Create furn_payments table
CREATE TABLE IF NOT EXISTS `furn_payments` (
    `payment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `customer_id` INT(11) NOT NULL,
    `payment_type` ENUM('prepayment', 'postpayment') NOT NULL COMMENT 'prepayment = deposit, postpayment = final payment',
    `payment_method` ENUM('bank', 'cash') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `receipt_file` VARCHAR(255) DEFAULT NULL COMMENT 'Path to uploaded receipt file',
    `transaction_reference` VARCHAR(100) DEFAULT NULL COMMENT 'Bank transaction reference number',
    `bank_name` VARCHAR(100) DEFAULT NULL COMMENT 'Name of bank for transfer',
    `payment_date` DATE NOT NULL,
    `payment_notes` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `verified_by` INT(11) DEFAULT NULL COMMENT 'Manager who verified payment',
    `verified_at` DATETIME DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`payment_id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Payment records for custom furniture orders';

-- Insert sample payment data
INSERT IGNORE INTO `furn_payments` (
    `order_id`, `customer_id`, `payment_type`, `payment_method`,
    `amount`, `transaction_reference`, `bank_name`, `payment_date`,
    `payment_notes`, `status`, `created_at`
) VALUES
-- Payment for ORD-2026-00102 (Approved deposit)
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00102' LIMIT 1), 
 10, 'prepayment', 'bank', 12800.00, 'TXN20260306001', 'Commercial Bank of Ethiopia',
 '2026-03-06', 'Deposit payment for King Size Bed', 'approved', '2026-03-06 15:00:00'),

-- Payment for ORD-2026-00103 (Approved deposit)
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00103' LIMIT 1),
 10, 'prepayment', 'cash', 11200.00, NULL, NULL,
 '2026-03-05', 'Cash deposit payment', 'approved', '2026-03-05 10:00:00'),

-- Payment for ORD-2026-00104 (Approved deposit)
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00104' LIMIT 1),
 10, 'prepayment', 'bank', 8800.00, 'TXN20260301001', 'Dashen Bank',
 '2026-03-01', 'Deposit for dining table', 'approved', '2026-03-01 12:00:00'),

-- Payment for ORD-2026-00104 (Approved final payment)
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00104' LIMIT 1),
 10, 'postpayment', 'bank', 13200.00, 'TXN20260305001', 'Dashen Bank',
 '2026-03-05', 'Final payment for dining table', 'approved', '2026-03-05 14:00:00'),

-- Pending payment for ORD-2026-00101
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00101' LIMIT 1),
 10, 'prepayment', 'bank', 7400.00, 'TXN20260307001', 'Awash Bank',
 '2026-03-07', 'Deposit payment for study table', 'pending', '2026-03-07 11:00:00')

ON DUPLICATE KEY UPDATE payment_type = VALUES(payment_type);

-- Update deposit_amount for orders (40% of estimated cost) - only if columns exist
UPDATE furn_orders 
SET deposit_amount = ROUND(total_amount * 0.4, 2)
WHERE total_amount > 0 AND deposit_amount IS NULL;

COMMIT;

-- Display results
SELECT 'Payment Schema Created Successfully!' as Status;
SELECT COUNT(*) as Total_Payments FROM furn_payments;
SELECT 
    payment_type,
    status,
    COUNT(*) as count,
    SUM(amount) as total_amount
FROM furn_payments
GROUP BY payment_type, status;



-- === database/payroll_schema.sql ===
-- =====================================================
-- Payroll Table Schema
-- FurnitureCraft Workshop ERP System
-- =====================================================
-- This script creates the payroll table for managing
-- employee salary payments, bonuses, and deductions
-- =====================================================

-- Create payroll table
CREATE TABLE IF NOT EXISTS furn_payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month INT NOT NULL COMMENT 'Month number (1-12)',
    year INT NOT NULL COMMENT 'Year (e.g., 2026)',
    base_salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Base monthly salary in ETB',
    bonus DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Additional bonus amount in ETB',
    deductions DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Deductions (tax, insurance, etc.) in ETB',
    net_salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Final amount paid (base + bonus - deductions)',
    payment_date DATE NOT NULL COMMENT 'Date when payment was made',
    status VARCHAR(50) DEFAULT 'paid' COMMENT 'Payment status: paid, pending, cancelled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for better query performance
    INDEX idx_employee (employee_id),
    INDEX idx_date (payment_date),
    INDEX idx_month_year (month, year),
    INDEX idx_status (status)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Employee payroll records with salary, bonus, and deductions';

-- =====================================================
-- Verification Query
-- =====================================================
-- Run this to verify the table was created successfully:
-- SELECT COUNT(*) as payroll_records FROM furn_payroll;

-- =====================================================
-- Sample Insert (Optional)
-- =====================================================
-- Uncomment to insert a sample payroll record:
/*
INSERT INTO furn_payroll 
(employee_id, month, year, base_salary, bonus, deductions, net_salary, payment_date, status) 
VALUES 
(1, 3, 2026, 15000.00, 2000.00, 1500.00, 15500.00, '2026-03-08', 'paid');
*/

-- =====================================================
-- Useful Queries
-- =====================================================

-- View all payroll records with employee names:
/*
SELECT 
    p.id,
    u.full_name as employee_name,
    p.month,
    p.year,
    p.base_salary,
    p.bonus,
    p.deductions,
    p.net_salary,
    p.payment_date,
    p.status
FROM furn_payroll p
LEFT JOIN furn_users u ON p.employee_id = u.id
ORDER BY p.payment_date DESC;
*/

-- Monthly payroll summary:
/*
SELECT 
    month,
    year,
    COUNT(*) as employees_paid,
    SUM(base_salary) as total_base,
    SUM(bonus) as total_bonus,
    SUM(deductions) as total_deductions,
    SUM(net_salary) as total_paid
FROM furn_payroll
GROUP BY year, month
ORDER BY year DESC, month DESC;
*/


-- === database/production_schema.sql ===
-- Section 6: Production System Updates
-- Add production-related tables and columns

USE furniture_erp;

-- Add materials table
CREATE TABLE IF NOT EXISTS `furn_materials` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'pieces',
    `current_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reserved_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `minimum_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cost_per_unit` DECIMAL(10,2) DEFAULT NULL,
    `supplier` VARCHAR(100),
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_material_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add product materials mapping table
CREATE TABLE IF NOT EXISTS `furn_product_materials` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `material_id` INT(11) NOT NULL,
    `quantity_required` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_product_material_product` (`product_id`),
    KEY `fk_product_material_material` (`material_id`),
    CONSTRAINT `fk_product_material_product` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_material_material` FOREIGN KEY (`material_id`) REFERENCES `furn_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add production assignments table
CREATE TABLE IF NOT EXISTS `furn_production_assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `employee_id` INT(11) NOT NULL,
    `assigned_by` INT(11) NOT NULL,
    `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `estimated_hours` DECIMAL(5,2) DEFAULT NULL,
    `actual_hours` DECIMAL(5,2) DEFAULT NULL,
    `status` ENUM('assigned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'assigned',
    `notes` TEXT,
    `completion_notes` TEXT,
    PRIMARY KEY (`id`),
    KEY `fk_production_order` (`order_id`),
    KEY `fk_production_employee` (`employee_id`),
    KEY `fk_production_assigned_by` (`assigned_by`),
    CONSTRAINT `fk_production_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_production_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_production_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add material reservations table
CREATE TABLE IF NOT EXISTS `furn_material_reservations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `material_id` INT(11) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL,
    `reserved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `released_at` TIMESTAMP NULL DEFAULT NULL,
    `status` ENUM('reserved', 'used', 'cancelled') NOT NULL DEFAULT 'reserved',
    PRIMARY KEY (`id`),
    KEY `fk_reservation_order` (`order_id`),
    KEY `fk_reservation_material` (`material_id`),
    CONSTRAINT `fk_reservation_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reservation_material` FOREIGN KEY (`material_id`) REFERENCES `furn_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add production tracking columns to orders table
ALTER TABLE `furn_orders` 
ADD COLUMN IF NOT EXISTS `production_started_at` TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `production_completed_at` TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `estimated_completion_date` DATE NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `actual_completion_date` DATE NULL DEFAULT NULL;

-- Insert sample materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`) VALUES
('Premium Leather', 'High-quality full-grain leather for upholstery', 'square_feet', 500.00, 100.00, 150.00, 'Ethiopian Leather Co.'),
('Oak Wood', 'Premium solid oak wood for furniture frames', 'board_feet', 200.00, 50.00, 85.00, 'Addis Ababa Timber'),
('Steel Frame', 'Industrial steel frames for structural support', 'pieces', 50.00, 10.00, 1200.00, 'Metal Works Ltd'),
('Foam Padding', 'High-density foam for cushioning', 'pieces', 100.00, 20.00, 75.00, 'Comfort Materials Inc'),
('Fabric Upholstery', 'Premium fabric for seating surfaces', 'yards', 300.00, 50.00, 45.00, 'Textile Solutions'),
('Glass Tabletop', 'Tempered glass for table surfaces', 'pieces', 25.00, 5.00, 350.00, 'Glass Manufacturing Co.'),
('Stainless Steel Hardware', 'Quality hardware and fittings', 'pieces', 500.00, 100.00, 12.00, 'Hardware Distributors');

-- Insert sample product-material mappings
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(1, 1, 25.00), -- Premium Leather Sofa needs 25 sq ft leather
(1, 2, 15.00), -- Premium Leather Sofa needs 15 board ft oak
(1, 4, 6.00),  -- Premium Leather Sofa needs 6 pieces foam
(2, 2, 30.00), -- King Size Bed needs 30 board ft oak
(2, 3, 1.00),  -- King Size Bed needs 1 steel frame
(3, 2, 12.00), -- Modern Dining Table needs 12 board ft oak
(3, 6, 1.00),  -- Modern Dining Table needs 1 glass tabletop
(4, 2, 8.00),  -- Executive Chair needs 8 board ft oak
(4, 4, 2.00),  -- Executive Chair needs 2 pieces foam
(4, 5, 3.00);  -- Executive Chair needs 3 yards fabric

-- Add audit log entries for production system
INSERT IGNORE INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'production_system_updated', 'database', 1, '{"message": "Production system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_production_assignments', NULL, '{"columns": ["id", "order_id", "employee_id", "assigned_by", "assigned_at", "started_at", "completed_at", "estimated_hours", "actual_hours", "status", "notes", "completion_notes"]}', NOW());



-- === database/material_schema.sql ===
-- Section 7: Raw Material Management Updates
-- Enhanced materials table and related functionality

USE furniture_erp;

-- Add supplier table for better supplier management
CREATE TABLE IF NOT EXISTS `furn_suppliers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `contact_person` VARCHAR(100),
    `email` VARCHAR(100),
    `phone` VARCHAR(20),
    `address` TEXT,
    `website` VARCHAR(255),
    `payment_terms` VARCHAR(100),
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_supplier_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add material categories for better organization
CREATE TABLE IF NOT EXISTS `furn_material_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhanced materials table with additional tracking fields
ALTER TABLE `furn_materials` 
ADD COLUMN IF NOT EXISTS `category_id` INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `reorder_point` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS `last_purchase_date` DATE NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `last_purchase_price` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `average_cost` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `shelf_life_days` INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `storage_location` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL;

-- Add foreign key constraints
ALTER TABLE `furn_materials` 
ADD CONSTRAINT `fk_material_category` FOREIGN KEY (`category_id`) REFERENCES `furn_material_categories` (`id`) ON DELETE SET NULL;

-- Add material transactions table for detailed tracking
CREATE TABLE IF NOT EXISTS `furn_material_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) NOT NULL,
    `transaction_type` ENUM('purchase', 'usage', 'adjustment', 'transfer_in', 'transfer_out', 'return') NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL,
    `unit_cost` DECIMAL(10,2) DEFAULT NULL,
    `total_cost` DECIMAL(10,2) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `notes` TEXT,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_transaction_material` (`material_id`),
    KEY `fk_transaction_user` (`created_by`),
    CONSTRAINT `fk_transaction_material` FOREIGN KEY (`material_id`) REFERENCES `furn_materials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transaction_user` FOREIGN KEY (`created_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add low stock alerts table
CREATE TABLE IF NOT EXISTS `furn_low_stock_alerts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) NOT NULL,
    `current_stock` DECIMAL(10,2) NOT NULL,
    `minimum_stock` DECIMAL(10,2) NOT NULL,
    `alert_level` ENUM('low', 'critical') NOT NULL,
    `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `resolved_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_alert_material` (`material_id`),
    KEY `fk_alert_resolved_by` (`resolved_by`),
    CONSTRAINT `fk_alert_material` FOREIGN KEY (`material_id`) REFERENCES `furn_materials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_alert_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample suppliers
INSERT IGNORE INTO `furn_suppliers` (`name`, `contact_person`, `email`, `phone`, `address`, `payment_terms`) VALUES
('Ethiopian Leather Co.', 'Abebe Kebede', 'abebe@leatherco.et', '+251-11-123-4567', 'Addis Ababa, Industrial Zone', '30 days'),
('Addis Ababa Timber', 'Mekonnen Haile', 'mekonnen@timber.et', '+251-11-234-5678', 'Addis Ababa, Wood District', '15 days'),
('Metal Works Ltd', 'Kebede Tesfaye', 'kebede@metalworks.et', '+251-11-345-6789', 'Addis Ababa, Metal Industrial Park', '45 days'),
('Comfort Materials Inc', 'Alemu Getachew', 'alemu@comfort.et', '+251-11-456-7890', 'Addis Ababa, Textile Zone', '30 days'),
('Textile Solutions', 'Berhane Weldu', 'berhane@textiles.et', '+251-11-567-8901', 'Addis Ababa, Garment District', '60 days'),
('Glass Manufacturing Co.', 'Tadesse Lemma', 'tadesse@glassco.et', '+251-11-678-9012', 'Addis Ababa, Glass Industrial Area', '30 days'),
('Hardware Distributors', 'Solomon Admassu', 'solomon@hardware.et', '+251-11-789-0123', 'Addis Ababa, Hardware Market', '15 days');

-- Insert material categories
INSERT IGNORE INTO `furn_material_categories` (`name`, `description`) VALUES
('Wood', 'Various types of wood materials for furniture construction'),
('Upholstery', 'Fabric, leather, and cushioning materials'),
('Hardware', 'Metal components, fittings, and accessories'),
('Glass', 'Glass materials for tabletops and decorative elements'),
('Foam', 'Padding and cushioning materials'),
('Finishing', 'Stains, paints, and protective coatings');

-- Update existing materials with categories and enhanced data
UPDATE `furn_materials` SET 
    `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name` = 'Upholstery' LIMIT 1),
    `reorder_point` = 50.00,
    `last_purchase_date` = '2026-02-01',
    `last_purchase_price` = 150.00,
    `average_cost` = 145.00,
    `storage_location` = 'Warehouse A-Section 1'
WHERE `name` = 'Premium Leather';

UPDATE `furn_materials` SET 
    `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name` = 'Wood' LIMIT 1),
    `reorder_point` = 25.00,
    `last_purchase_date` = '2026-02-05',
    `last_purchase_price` = 85.00,
    `average_cost` = 82.50,
    `storage_location` = 'Warehouse B-Section 2'
WHERE `name` = 'Oak Wood';

-- Add audit log entries for material management system
INSERT IGNORE INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'material_management_updated', 'database', 1, '{"message": "Material management system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_materials', NULL, '{"columns_added": ["category_id", "reorder_point", "last_purchase_date", "last_purchase_price", "average_cost", "shelf_life_days", "storage_location", "notes"]}', NOW());



-- === database/production_control_schema.sql ===
-- Production Control System Database Schema
-- Manager Dashboard and Production Management

USE furniture_erp;

-- Create production assignments table
CREATE TABLE IF NOT EXISTS `furn_production_assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `assigned_employee_ids` TEXT NOT NULL,
    `assigned_by` INT(11) DEFAULT NULL,
    `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deadline` DATE NOT NULL,
    `required_materials` TEXT,
    `materials_reserved` TINYINT(1) NOT NULL DEFAULT 0,
    `progress` INT(11) NOT NULL DEFAULT 0,
    `status` ENUM('assigned', 'in_progress', 'completed', 'delayed') NOT NULL DEFAULT 'assigned',
    `notes` TEXT,
    `progress_notes` TEXT,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `final_notes` TEXT,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_production_order` (`order_id`),
    KEY `fk_production_assigner` (`assigned_by`),
    CONSTRAINT `fk_production_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_production_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create production logs table
CREATE TABLE IF NOT EXISTS `furn_production_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `production_id` INT(11) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `details` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_log_production` (`production_id`),
    CONSTRAINT `fk_log_production` FOREIGN KEY (`production_id`) REFERENCES `furn_production_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add material reservation tracking to materials table
ALTER TABLE `furn_materials` 
ADD COLUMN IF NOT EXISTS `reserved_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Insert sample production assignments
INSERT IGNORE INTO `furn_production_assignments` (`order_id`, `assigned_employee_ids`, `assigned_by`, `deadline`, `required_materials`, `progress`, `status`, `notes`) VALUES
(1, '2,3', 1, DATE_ADD(NOW(), INTERVAL 14 DAY), '1:25.00,2:15.00,4:6.00', 45, 'in_progress', 'Upholstery work in progress'),
(2, '4,5', 1, DATE_ADD(NOW(), INTERVAL 21 DAY), '2:30.00,3:1.00', 25, 'in_progress', 'Frame construction started'),
(3, '2', 1, DATE_ADD(NOW(), INTERVAL 10 DAY), '2:12.00,6:1.00', 75, 'in_progress', 'Nearly complete, final finishing needed');

-- Insert sample production logs
INSERT IGNORE INTO `furn_production_logs` (`production_id`, `action`, `details`, `created_at`) VALUES
(1, 'order_assigned', '{"employee_ids":["2","3"],"deadline":"2026-03-08"}', NOW()),
(1, 'progress_updated', '{"progress":25,"notes":"Frame completed"}', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'progress_updated', '{"progress":45,"notes":"Upholstery 50% complete"}', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'order_assigned', '{"employee_ids":["4","5"],"deadline":"2026-03-15"}', NOW()),
(2, 'progress_updated', '{"progress":25,"notes":"Initial cutting and preparation done"}', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 'order_assigned', '{"employee_ids":["2"],"deadline":"2026-03-04"}', NOW()),
(3, 'progress_updated', '{"progress":75,"notes":"Assembly complete, finishing work"}', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Update existing orders for production workflow
UPDATE `furn_orders` SET 
    `status` = 'in_production',
    `updated_at` = NOW()
WHERE `id` IN (1, 2, 3);

-- Add audit log entries
INSERT IGNORE INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'production_system_created', 'database', 1, '{"message": "Production control system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_orders', NULL, '{"reference": "Production workflow integration"}', NOW()),
(NULL, 'schema_update', 'furn_materials', NULL, '{"columns_added": ["reserved_stock"]}', NOW());

-- Create indexes for better performance
CREATE INDEX `idx_production_status` ON `furn_production_assignments` (`status`);
CREATE INDEX `idx_production_deadline` ON `furn_production_assignments` (`deadline`);
CREATE INDEX `idx_production_progress` ON `furn_production_assignments` (`progress`);
CREATE INDEX `idx_materials_reserved` ON `furn_materials` (`reserved_stock`);

-- Add sample data for dashboard statistics
INSERT IGNORE INTO `furn_orders` (`order_id`, `customer_name`, `customer_email`, `product_name`, `customization_details`, `status`, `total_amount`, `deposit_paid`, `created_at`) VALUES
('ORD20260301001', 'New Customer 1', 'customer1@email.com', 'Custom Dining Set', 'Oak wood with leather upholstery', 'pending_cost_approval', 0, 0, NOW()),
('ORD20260301002', 'New Customer 2', 'customer2@email.com', 'Executive Office Chair', 'Ergonomic design with premium fabric', 'waiting_for_deposit', 4500, 1350, NOW()),
('ORD20260301003', 'New Customer 3', 'customer3@email.com', 'Modern Bookshelf', 'Walnut finish with glass doors', 'ready_for_production', 8500, 2550, NOW());

COMMIT;



-- === database/production_tasks_schema.sql ===
-- Production Tasks Schema
-- Creates table for tracking employee production tasks

USE `furniture_erp`;

-- Create production tasks table if it doesn't exist
CREATE TABLE IF NOT EXISTS `furn_production_tasks` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `employee_id` INT(11) NOT NULL,
    `product_id` INT(11) DEFAULT NULL,
    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    `progress` INT(3) DEFAULT 0 COMMENT 'Progress percentage 0-100',
    `deadline` DATE DEFAULT NULL,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deadline` (`deadline`),
    CONSTRAINT `fk_task_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_task_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_task_order_employee` ON `furn_production_tasks` (`order_id`, `employee_id`);
CREATE INDEX IF NOT EXISTS `idx_task_status_deadline` ON `furn_production_tasks` (`status`, `deadline`);

COMMIT;

SELECT 'Production Tasks Schema Applied!' as Status;
SELECT COUNT(*) as Total_Tasks FROM furn_production_tasks;


-- === database/production_completion_schema.sql ===
-- Production Completion & Gallery Schema
-- Adds columns for finished product tracking and creates gallery table

USE `furniture_erp`;

-- Add completion columns to production tasks table
ALTER TABLE `furn_production_tasks`
ADD COLUMN IF NOT EXISTS `finished_image` VARCHAR(255) DEFAULT NULL COMMENT 'Path to finished product image',
ADD COLUMN IF NOT EXISTS `materials_used` TEXT DEFAULT NULL COMMENT 'List of materials used in production',
ADD COLUMN IF NOT EXISTS `completion_notes` TEXT DEFAULT NULL COMMENT 'Notes about the completed product',
ADD COLUMN IF NOT EXISTS `actual_hours` DECIMAL(5,2) DEFAULT NULL COMMENT 'Actual hours spent on production';

-- Create gallery table for finished products
CREATE TABLE IF NOT EXISTS `furn_gallery` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `category` ENUM('finished_products', 'customer_inspiration', 'showcase') DEFAULT 'finished_products',
    `furniture_type` VARCHAR(100) DEFAULT NULL,
    `material` VARCHAR(100) DEFAULT NULL,
    `dimensions` VARCHAR(100) DEFAULT NULL,
    `employee_id` INT(11) DEFAULT NULL COMMENT 'Employee who created this',
    `employee_name` VARCHAR(255) DEFAULT NULL,
    `order_id` INT(11) DEFAULT NULL COMMENT 'Related order if applicable',
    `materials_used` TEXT DEFAULT NULL,
    `production_hours` DECIMAL(5,2) DEFAULT NULL,
    `views` INT(11) DEFAULT 0,
    `likes` INT(11) DEFAULT 0,
    `status` ENUM('active', 'inactive', 'featured') DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_status` (`status`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_gallery_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_gallery_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_gallery_category_status` ON `furn_gallery` (`category`, `status`);
CREATE INDEX IF NOT EXISTS `idx_gallery_furniture_type` ON `furn_gallery` (`furniture_type`);

COMMIT;

SELECT 'Production Completion & Gallery Schema Applied!' as Status;
SELECT COUNT(*) as Gallery_Items FROM furn_gallery;


-- === database/profit_schema.sql ===
-- Section 10: Profit Calculation Engine Database Schema
-- Profit analysis and reporting system

USE furniture_erp;

-- Create profit calculation table to store calculated profits
CREATE TABLE IF NOT EXISTS `furn_profit_calculations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `final_selling_price` DECIMAL(12,2) NOT NULL,
    `material_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `labor_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `production_time_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `profit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `profit_margin_percentage` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `calculated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `calculated_by` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_order_calculation` (`order_id`),
    KEY `fk_profit_order` (`order_id`),
    KEY `fk_profit_product` (`product_id`),
    KEY `fk_profit_calculated_by` (`calculated_by`),
    CONSTRAINT `fk_profit_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_profit_product` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_profit_calculated_by` FOREIGN KEY (`calculated_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create profit settings table for configuration
CREATE TABLE IF NOT EXISTS `furn_profit_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create monthly profit summary table
CREATE TABLE IF NOT EXISTS `furn_monthly_profit_summary` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `year` INT(4) NOT NULL,
    `month` INT(2) NOT NULL,
    `total_orders` INT(11) NOT NULL DEFAULT 0,
    `total_revenue` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_material_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_labor_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_production_time_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `average_profit_margin` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_month_year` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create product profit summary table
CREATE TABLE IF NOT EXISTS `furn_product_profit_summary` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `total_sold` INT(11) NOT NULL DEFAULT 0,
    `total_revenue` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_material_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_labor_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_production_time_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `average_profit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `average_profit_margin` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `last_calculated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_product_summary` (`product_id`),
    KEY `fk_product_summary_product` (`product_id`),
    CONSTRAINT `fk_product_summary_product` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default profit settings
INSERT IGNORE INTO `furn_profit_settings` (`setting_key`, `setting_value`, `description`) VALUES
('labor_hourly_rate', '50.00', 'Standard hourly rate for labor cost calculation'),
('production_time_cost_rate', '30.00', 'Cost rate per hour of production time'),
('profit_margin_target', '25.00', 'Target profit margin percentage'),
('calculation_method', 'actual', 'Calculation method: actual or estimated');

-- Add profit-related columns to existing tables if needed
ALTER TABLE `furn_orders` 
ADD COLUMN IF NOT EXISTS `profit_calculated` TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS `profit_calculation_date` TIMESTAMP NULL DEFAULT NULL;

-- Add cost tracking columns to production assignments
ALTER TABLE `furn_production_assignments` 
ADD COLUMN IF NOT EXISTS `labor_cost` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `production_time_cost` DECIMAL(10,2) DEFAULT NULL;

-- Create view for profit analysis
CREATE OR REPLACE VIEW `vw_profit_analysis` AS
SELECT 
    pc.id,
    pc.order_id,
    pc.product_id,
    o.order_number,
    p.name as product_name,
    p.category,
    pc.final_selling_price,
    pc.material_cost,
    pc.labor_cost,
    pc.production_time_cost,
    pc.total_cost,
    pc.profit,
    pc.profit_margin_percentage,
    pc.calculated_at,
    o.status as order_status,
    o.created_at as order_date
FROM furn_profit_calculations pc
JOIN furn_orders o ON pc.order_id = o.id
JOIN furn_products p ON pc.product_id = p.id;

-- Create view for monthly profit trends
CREATE OR REPLACE VIEW `vw_monthly_profit_trends` AS
SELECT 
    year,
    month,
    DATE_FORMAT(STR_TO_DATE(CONCAT(year, '-', month, '-01'), '%Y-%m-%d'), '%M %Y') as month_name,
    total_orders,
    total_revenue,
    total_cost,
    total_profit,
    average_profit_margin,
    generated_at
FROM furn_monthly_profit_summary
ORDER BY year DESC, month DESC;

-- Add audit log entries
INSERT IGNORE INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'profit_engine_created', 'database', 1, '{"message": "Profit calculation engine tables created"}', NOW()),
(NULL, 'schema_update', 'furn_orders', NULL, '{"columns_added": ["profit_calculated", "profit_calculation_date"]}', NOW());



-- === database/ratings_schema.sql ===
-- Rating system for completed orders
CREATE TABLE IF NOT EXISTS `furn_ratings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `review_text` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_order_rating` (`order_id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- === database/setup_payments_complete.sql ===
-- Complete Payment System Setup
-- Run this file to set up everything needed for the payment system

USE `furniture_erp`;

-- Step 1: Add missing columns to furn_orders table (if they don't exist)
-- Check and add remaining_balance
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'remaining_balance';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN IF NOT EXISTS `remaining_balance` DECIMAL(10,2) DEFAULT NULL', 
    'SELECT "Column remaining_balance already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add final_payment_required
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'final_payment_required';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN IF NOT EXISTS `final_payment_required` DECIMAL(10,2) DEFAULT NULL', 
    'SELECT "Column final_payment_required already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add final_payment_paid
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'final_payment_paid';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN IF NOT EXISTS `final_payment_paid` DECIMAL(10,2) DEFAULT NULL', 
    'SELECT "Column final_payment_paid already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add final_payment_paid_at
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'final_payment_paid_at';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN IF NOT EXISTS `final_payment_paid_at` TIMESTAMP NULL DEFAULT NULL', 
    'SELECT "Column final_payment_paid_at already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add estimated_completion_date
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'estimated_completion_date';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN IF NOT EXISTS `estimated_completion_date` DATE NULL DEFAULT NULL', 
    'SELECT "Column estimated_completion_date already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add actual_completion_date
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'actual_completion_date';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN IF NOT EXISTS `actual_completion_date` DATE NULL DEFAULT NULL', 
    'SELECT "Column actual_completion_date already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Create payments table
CREATE TABLE IF NOT EXISTS `furn_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `customer_id` INT(11) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_type` ENUM('deposit','final') NOT NULL,
    `payment_method` ENUM('cash','bank','mobile') NOT NULL,
    `receipt_path` VARCHAR(255) DEFAULT NULL,
    `transaction_ref` VARCHAR(100) DEFAULT NULL,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `mobile_provider` VARCHAR(50) DEFAULT NULL,
    `payment_date` DATE NOT NULL,
    `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by` INT(11) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_payment_order` (`order_id`),
    KEY `fk_payment_customer` (`customer_id`),
    KEY `fk_payment_approver` (`approved_by`),
    KEY `idx_status` (`status`),
    KEY `idx_payment_date` (`payment_date`),
    CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payment_approver` FOREIGN KEY (`approved_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Ensure we have test users
INSERT IGNORE INTO `furn_users` (`id`, `username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `phone`, `address`, `is_active`) 
VALUES 
(10, 'customer', 'customer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'John', 'Doe', '0911234567', 'Addis Ababa, Ethiopia', 1),
(11, 'customer2', 'customer2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'Jane', 'Smith', '0922345678', 'Jimma, Ethiopia', 1),
(20, 'manager', 'manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Mike', 'Manager', '0933456789', 1);

-- Step 4: Insert sample orders
INSERT IGNORE INTO `furn_orders` (`id`, `customer_id`, `order_number`, `status`, `total_amount`, `deposit_amount`, `deposit_paid`, `remaining_balance`, `special_instructions`, `created_at`) VALUES
(101, 10, 'ORD-2026-0001', 'waiting_for_deposit', 25000.00, 10000.00, 0.00, 25000.00, '{"delivery_address":"123 Main St, Addis Ababa","payment_method":"bank"}', '2026-03-01 10:00:00'),
(102, 10, 'ORD-2026-0002', 'deposit_paid', 18200.00, 7280.00, 7280.00, 10920.00, '{"delivery_address":"456 Oak Ave, Addis Ababa","payment_method":"mobile"}', '2026-03-05 11:30:00'),
(103, 10, 'ORD-2026-0003', 'in_production', 32500.00, 13000.00, 13000.00, 19500.00, '{"delivery_address":"789 Pine Rd, Addis Ababa","payment_method":"bank"}', '2026-03-08 14:20:00'),
(104, 10, 'ORD-2026-0004', 'ready_for_delivery', 15800.00, 6320.00, 6320.00, 9480.00, '{"delivery_address":"321 Elm St, Addis Ababa","payment_method":"cash"}', '2026-03-10 09:15:00'),
(105, 10, 'ORD-2026-0005', 'completed', 22500.00, 9000.00, 22500.00, 0.00, '{"delivery_address":"654 Maple Dr, Addis Ababa","payment_method":"mobile"}', '2026-02-20 16:45:00'),
(106, 11, 'ORD-2026-0006', 'waiting_for_deposit', 28000.00, 11200.00, 0.00, 28000.00, '{"delivery_address":"987 Cedar Ln, Jimma","payment_method":"bank"}', '2026-03-12 13:00:00')
ON DUPLICATE KEY UPDATE 
    `status` = VALUES(`status`),
    `total_amount` = VALUES(`total_amount`),
    `deposit_amount` = VALUES(`deposit_amount`),
    `deposit_paid` = VALUES(`deposit_paid`),
    `remaining_balance` = VALUES(`remaining_balance`);

-- Step 5: Insert sample order items
INSERT IGNORE INTO `furn_order_customizations` (`order_id`, `product_id`, `quantity`, `base_price`, `adjusted_price`) VALUES
(101, 1, 1, 15000.00, 15000.00),
(101, 2, 2, 5000.00, 10000.00),
(102, 3, 1, 12000.00, 12000.00),
(102, 4, 2, 3100.00, 6200.00),
(103, 1, 2, 15000.00, 30000.00),
(103, 4, 1, 2500.00, 2500.00),
(104, 2, 1, 8000.00, 8000.00),
(104, 3, 1, 7800.00, 7800.00),
(105, 1, 1, 15000.00, 15000.00),
(105, 4, 3, 2500.00, 7500.00),
(106, 2, 2, 14000.00, 28000.00)
ON DUPLICATE KEY UPDATE 
    `quantity` = VALUES(`quantity`),
    `base_price` = VALUES(`base_price`),
    `adjusted_price` = VALUES(`adjusted_price`);

-- Step 6: Insert sample payments
INSERT IGNORE INTO `furn_payments` (`id`, `order_id`, `customer_id`, `amount`, `payment_type`, `payment_method`, `receipt_path`, `transaction_ref`, `mobile_provider`, `payment_date`, `submitted_at`, `status`, `approved_by`, `approved_at`) VALUES
(1, 102, 10, 7280.00, 'deposit', 'mobile', '/uploads/receipts/receipt_102_sample.jpg', 'TXN-M-20260305-001', 'TeleBirr', '2026-03-05', '2026-03-05 12:00:00', 'approved', 20, '2026-03-05 14:30:00'),
(2, 103, 10, 13000.00, 'deposit', 'bank', '/uploads/receipts/receipt_103_sample.jpg', 'BNK-20260308-456', 'Commercial Bank of Ethiopia', '2026-03-08', '2026-03-08 15:00:00', 'approved', 20, '2026-03-08 16:45:00'),
(3, 104, 10, 6320.00, 'deposit', 'cash', NULL, NULL, NULL, '2026-03-10', '2026-03-10 10:00:00', 'approved', 20, '2026-03-10 10:30:00'),
(4, 105, 10, 9000.00, 'deposit', 'mobile', '/uploads/receipts/receipt_105_deposit.jpg', 'TXN-M-20260220-789', 'M-PESA', '2026-02-20', '2026-02-20 17:00:00', 'approved', 20, '2026-02-20 18:00:00'),
(5, 105, 10, 13500.00, 'final', 'mobile', '/uploads/receipts/receipt_105_final.jpg', 'TXN-M-20260228-321', 'M-PESA', '2026-02-28', '2026-02-28 10:00:00', 'approved', 20, '2026-02-28 11:00:00'),
(6, 101, 10, 10000.00, 'deposit', 'bank', '/uploads/receipts/receipt_101_pending.jpg', 'BNK-20260313-789', 'Commercial Bank of Ethiopia', '2026-03-13', '2026-03-13 09:00:00', 'pending', NULL, NULL),
(7, 104, 10, 9480.00, 'final', 'bank', '/uploads/receipts/receipt_104_final.jpg', 'BNK-20260314-123', 'Awash Bank', '2026-03-14', '2026-03-14 11:30:00', 'pending', NULL, NULL),
(8, 106, 11, 10000.00, 'deposit', 'bank', '/uploads/receipts/receipt_106_rejected.jpg', 'BNK-20260312-999', 'Dashen Bank', '2026-03-12', '2026-03-12 14:00:00', 'rejected', 20, '2026-03-12 16:00:00')
ON DUPLICATE KEY UPDATE 
    `amount` = VALUES(`amount`),
    `status` = VALUES(`status`);

COMMIT;

-- Display summary
SELECT 'Payment System Setup Complete!' as Status;
SELECT COUNT(*) as Total_Orders FROM furn_orders WHERE customer_id IN (10, 11);
SELECT COUNT(*) as Total_Payments FROM furn_payments;
SELECT status, COUNT(*) as count FROM furn_payments GROUP BY status;


-- === database/custom_furniture_orders.sql ===
-- Custom Furniture Orders Schema
-- Add columns for custom furniture order system

USE `furniture_erp`;

-- Add custom furniture columns to furn_orders table if they don't exist
ALTER TABLE `furn_orders`
ADD COLUMN IF NOT EXISTS `furniture_type` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `furniture_name` VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `length` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `width` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `height` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `material` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `color` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `design_description` TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `design_image` VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `special_notes` TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `estimated_cost` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `status` VARCHAR(50) DEFAULT 'pending_approval';

-- Insert sample custom furniture orders
INSERT IGNORE INTO `furn_orders` (
    `customer_id`, `order_number`, `furniture_type`, `furniture_name`,
    `length`, `width`, `height`, `material`, `color`,
    `design_description`, `special_notes`,
    `estimated_cost`, `deposit_amount`, `remaining_balance`,
    `status`, `created_at`
) VALUES
(10, 'ORD-2026-00101', 'Desk', 'Modern Wooden Study Table', 
 120.00, 60.00, 75.00, 'Oak Wood', 'Natural Wood',
 'I want a modern desk with two drawers and cable holes for computer wires. The design should be minimalist with clean lines.',
 'Please make the table legs removable for easy transport.',
 18500.00, 7400.00, 18500.00, 'pending_approval', '2026-03-07 10:30:00'),

(10, 'ORD-2026-00102', 'Bed', 'King Size Bed Frame',
 200.00, 180.00, 120.00, 'Mahogany Wood', 'Dark Brown',
 'King size bed with storage drawers underneath. Modern design with padded headboard.',
 'Need storage compartments on both sides.',
 32000.00, 12800.00, 32000.00, 'approved', '2026-03-06 14:20:00'),

(10, 'ORD-2026-00103', 'Wardrobe', 'Three Door Wardrobe',
 200.00, 60.00, 220.00, 'Pine Wood', 'White',
 '3-door wardrobe with mirror on the center door. Internal shelves and hanging rod.',
 'Mirror should be full-length on center door.',
 28000.00, 11200.00, 28000.00, 'in_production', '2026-03-05 09:15:00'),

(10, 'ORD-2026-00104', 'Table', 'Dining Table for 6',
 180.00, 90.00, 75.00, 'Teak Wood', 'Brown',
 'Rectangular dining table that can seat 6 people comfortably. Classic design with carved legs.',
 'Table should be sturdy and durable.',
 22000.00, 8800.00, 22000.00, 'completed', '2026-03-01 11:45:00'),

(10, 'ORD-2026-00105', 'Cabinet', 'Kitchen Storage Cabinet',
 100.00, 45.00, 180.00, 'Plywood', 'White',
 'Tall kitchen cabinet with multiple shelves and one drawer at bottom.',
 'Need adjustable shelves inside.',
 15000.00, 6000.00, 15000.00, 'pending_approval', '2026-03-07 16:00:00')

ON DUPLICATE KEY UPDATE furniture_type = VALUES(furniture_type);

-- Update existing orders to have status if NULL
UPDATE `furn_orders` SET `status` = 'pending_approval' WHERE `status` IS NULL OR `status` = '';

COMMIT;

SELECT 'Custom Furniture Orders Schema Applied!' as Status;
SELECT COUNT(*) as Sample_Orders FROM furn_orders WHERE customer_id = 10 AND furniture_type IS NOT NULL;



-- === database/wishlist_schema.sql ===
USE `furniture_erp`;

CREATE TABLE IF NOT EXISTS `furn_wishlist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_wishlist` (`customer_id`, `product_id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- === database/products_schema.sql ===
-- Products table already created in schema.sql, only insert sample data below

-- Insert sample products
INSERT IGNORE INTO furn_products (name, category_id, description, base_price) VALUES
('Modern Office Chair', (SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), 'Ergonomic office chair with lumbar support', 3500.00),
('Dining Table Set', (SELECT id FROM furn_categories WHERE name='Table' LIMIT 1), '6-seater wooden dining table', 15000.00),
('L-Shaped Sofa', (SELECT id FROM furn_categories WHERE name='Sofa' LIMIT 1), 'Comfortable 5-seater L-shaped sofa', 25000.00),
('King Size Bed', (SELECT id FROM furn_categories WHERE name='Bed' LIMIT 1), 'Solid wood king size bed frame', 18000.00),
('Kitchen Cabinet', (SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), 'Modular kitchen storage cabinet', 12000.00),
('Executive Desk', (SELECT id FROM furn_categories WHERE name='Table' LIMIT 1), 'Large executive office desk with drawers', 8500.00),
('Sliding Wardrobe', (SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), '3-door sliding wardrobe with mirror', 22000.00),
('Wall Bookshelf', (SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), '5-tier wall-mounted bookshelf', 6500.00);


-- === database/analytics_schema.sql ===
-- Section 11: Dashboard & Analytics Database Schema
-- Analytics and reporting system with Chart.js integration

USE furniture_erp;

-- Create analytics dashboard configuration table
CREATE TABLE IF NOT EXISTS `furn_dashboard_config` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `widget_key` VARCHAR(50) NOT NULL UNIQUE,
    `widget_name` VARCHAR(100) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT(11) NOT NULL DEFAULT 0,
    `chart_type` VARCHAR(20) NOT NULL DEFAULT 'line',
    `data_source` VARCHAR(100) NOT NULL,
    `refresh_interval` INT(11) NOT NULL DEFAULT 300, -- seconds
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create analytics cache table for performance
CREATE TABLE IF NOT EXISTS `furn_analytics_cache` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(100) NOT NULL UNIQUE,
    `cache_data` LONGTEXT NOT NULL,
    `data_type` VARCHAR(20) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cache_key` (`cache_key`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create dashboard widgets table
CREATE TABLE IF NOT EXISTS `furn_dashboard_widgets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `widget_config` JSON NOT NULL,
    `position_x` INT(11) NOT NULL DEFAULT 0,
    `position_y` INT(11) NOT NULL DEFAULT 0,
    `width` INT(11) NOT NULL DEFAULT 4,
    `height` INT(11) NOT NULL DEFAULT 3,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_dashboard_user` (`user_id`),
    CONSTRAINT `fk_dashboard_user` FOREIGN KEY (`user_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default dashboard configuration
INSERT IGNORE INTO `furn_dashboard_config` (`widget_key`, `widget_name`, `display_order`, `chart_type`, `data_source`) VALUES
('monthly_revenue', 'Monthly Revenue', 1, 'line', 'orders'),
('orders_by_status', 'Orders by Status', 2, 'pie', 'orders'),
('employee_hours', 'Employee Working Hours', 3, 'bar', 'attendance'),
('low_stock_alerts', 'Low Stock Alerts', 4, 'doughnut', 'materials'),
('top_products', 'Top Selling Products', 5, 'bar', 'orders'),
('monthly_profit', 'Monthly Profit', 6, 'line', 'profit');

-- Create analytics views for performance
CREATE OR REPLACE VIEW `vw_monthly_revenue` AS
SELECT 
    YEAR(o.created_at) as year,
    MONTH(o.created_at) as month,
    DATE_FORMAT(o.created_at, '%Y-%m') as month_year,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as total_revenue,
    AVG(o.total_amount) as avg_order_value
FROM furn_orders o
WHERE o.status IN ('completed', 'delivered', 'paid')
GROUP BY YEAR(o.created_at), MONTH(o.created_at)
ORDER BY YEAR(o.created_at) DESC, MONTH(o.created_at) DESC;

CREATE OR REPLACE VIEW `vw_orders_by_status` AS
SELECT 
    status,
    COUNT(*) as count,
    SUM(total_amount) as total_value
FROM furn_orders
GROUP BY status;

CREATE OR REPLACE VIEW `vw_employee_hours_summary` AS
SELECT 
    u.id as employee_id,
    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
    COUNT(a.id) as days_worked
FROM furn_users u
JOIN furn_attendance a ON u.id = a.employee_id
WHERE u.role = 'employee' AND a.status = 'present'
GROUP BY u.id, u.first_name, u.last_name
ORDER BY days_worked DESC;

CREATE OR REPLACE VIEW `vw_low_stock_materials` AS
SELECT 
    m.id,
    m.name,
    m.current_stock,
    m.minimum_stock,
    m.unit,
    CASE 
        WHEN m.current_stock <= m.minimum_stock THEN 'critical'
        WHEN m.current_stock <= (m.minimum_stock * 1.5) THEN 'warning'
        ELSE 'normal'
    END as stock_status
FROM furn_materials m
WHERE m.current_stock <= (m.minimum_stock * 2) AND m.is_active = 1
ORDER BY m.current_stock ASC;

CREATE OR REPLACE VIEW `vw_top_selling_products` AS
SELECT 
    p.id as product_id,
    p.name as product_name,
    p.category_id,
    COUNT(oc.id) as orders_count,
    SUM(oc.quantity) as total_quantity,
    SUM(o.total_amount) as total_revenue
FROM furn_products p
JOIN furn_order_customizations oc ON p.id = oc.product_id
JOIN furn_orders o ON oc.order_id = o.id
WHERE o.status IN ('completed', 'delivered', 'paid')
GROUP BY p.id, p.name, p.category_id
ORDER BY total_revenue DESC
LIMIT 10;

CREATE OR REPLACE VIEW `vw_monthly_profit` AS
SELECT 
    YEAR(pc.calculated_at) as year,
    MONTH(pc.calculated_at) as month,
    DATE_FORMAT(pc.calculated_at, '%Y-%m') as month_year,
    COUNT(pc.id) as calculated_orders,
    SUM(pc.final_selling_price) as total_revenue,
    SUM(pc.total_cost) as total_cost,
    SUM(pc.profit) as total_profit,
    AVG(pc.profit_margin_percentage) as avg_profit_margin
FROM furn_profit_calculations pc
GROUP BY YEAR(pc.calculated_at), MONTH(pc.calculated_at)
ORDER BY YEAR(pc.calculated_at) DESC, MONTH(pc.calculated_at) DESC;

-- Add audit log entries
INSERT IGNORE INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'analytics_engine_created', 'database', 1, '{"message": "Dashboard analytics tables created"}', NOW()),
(NULL, 'schema_update', 'dashboard', NULL, '{"widgets": ["monthly_revenue", "orders_by_status", "employee_hours", "low_stock_alerts", "top_products", "monthly_profit"]}', NOW());

SET FOREIGN_KEY_CHECKS = 1;

