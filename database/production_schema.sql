-- Section 6: Production System Updates
-- Add production-related tables and columns

USE furniture_erp;

-- Add materials table
CREATE TABLE `furn_materials` (
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
CREATE TABLE `furn_product_materials` (
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
CREATE TABLE `furn_production_assignments` (
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
CREATE TABLE `furn_material_reservations` (
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
INSERT INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`) VALUES
('Premium Leather', 'High-quality full-grain leather for upholstery', 'square_feet', 500.00, 100.00, 150.00, 'Ethiopian Leather Co.'),
('Oak Wood', 'Premium solid oak wood for furniture frames', 'board_feet', 200.00, 50.00, 85.00, 'Addis Ababa Timber'),
('Steel Frame', 'Industrial steel frames for structural support', 'pieces', 50.00, 10.00, 1200.00, 'Metal Works Ltd'),
('Foam Padding', 'High-density foam for cushioning', 'pieces', 100.00, 20.00, 75.00, 'Comfort Materials Inc'),
('Fabric Upholstery', 'Premium fabric for seating surfaces', 'yards', 300.00, 50.00, 45.00, 'Textile Solutions'),
('Glass Tabletop', 'Tempered glass for table surfaces', 'pieces', 25.00, 5.00, 350.00, 'Glass Manufacturing Co.'),
('Stainless Steel Hardware', 'Quality hardware and fittings', 'pieces', 500.00, 100.00, 12.00, 'Hardware Distributors');

-- Insert sample product-material mappings
INSERT INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
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
INSERT INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'production_system_updated', 'database', 1, '{"message": "Production system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_production_assignments', NULL, '{"columns": ["id", "order_id", "employee_id", "assigned_by", "assigned_at", "started_at", "completed_at", "estimated_hours", "actual_hours", "status", "notes", "completion_notes"]}', NOW());

