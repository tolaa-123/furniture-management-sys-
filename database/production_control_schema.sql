-- Production Control System Database Schema
-- Manager Dashboard and Production Management

USE furniture_erp;

-- Create production assignments table
CREATE TABLE `furn_production_assignments` (
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
CREATE TABLE `furn_production_logs` (
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
INSERT INTO `furn_production_assignments` (`order_id`, `assigned_employee_ids`, `assigned_by`, `deadline`, `required_materials`, `progress`, `status`, `notes`) VALUES
(1, '2,3', 1, DATE_ADD(NOW(), INTERVAL 14 DAY), '1:25.00,2:15.00,4:6.00', 45, 'in_progress', 'Upholstery work in progress'),
(2, '4,5', 1, DATE_ADD(NOW(), INTERVAL 21 DAY), '2:30.00,3:1.00', 25, 'in_progress', 'Frame construction started'),
(3, '2', 1, DATE_ADD(NOW(), INTERVAL 10 DAY), '2:12.00,6:1.00', 75, 'in_progress', 'Nearly complete, final finishing needed');

-- Insert sample production logs
INSERT INTO `furn_production_logs` (`production_id`, `action`, `details`, `created_at`) VALUES
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
INSERT INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
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
INSERT INTO `furn_orders` (`order_id`, `customer_name`, `customer_email`, `product_name`, `customization_details`, `status`, `total_amount`, `deposit_paid`, `created_at`) VALUES
('ORD20260301001', 'New Customer 1', 'customer1@email.com', 'Custom Dining Set', 'Oak wood with leather upholstery', 'pending_cost_approval', 0, 0, NOW()),
('ORD20260301002', 'New Customer 2', 'customer2@email.com', 'Executive Office Chair', 'Ergonomic design with premium fabric', 'waiting_for_deposit', 4500, 1350, NOW()),
('ORD20260301003', 'New Customer 3', 'customer3@email.com', 'Modern Bookshelf', 'Walnut finish with glass doors', 'ready_for_production', 8500, 2550, NOW());

COMMIT;

