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
