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
