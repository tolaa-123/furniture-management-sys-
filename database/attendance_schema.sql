-- Section 8: Attendance System Database Schema
-- Employee attendance tracking with time and IP validation

USE furniture_erp;

-- Create attendance table
CREATE TABLE `furn_attendance` (
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
CREATE TABLE `furn_attendance_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create attendance reports table for monthly/yearly summaries
CREATE TABLE `furn_attendance_reports` (
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
INSERT INTO `furn_attendance_settings` (`setting_key`, `setting_value`, `description`) VALUES
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
INSERT INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'attendance_system_created', 'database', 1, '{"message": "Attendance system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_users', NULL, '{"columns_added": ["employee_id", "department", "position"]}', NOW());
