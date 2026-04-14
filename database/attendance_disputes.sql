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
