-- Section 7: Raw Material Management Updates
-- Enhanced materials table and related functionality

USE furniture_erp;

-- Add supplier table for better supplier management
CREATE TABLE `furn_suppliers` (
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
CREATE TABLE `furn_material_categories` (
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
CREATE TABLE `furn_material_transactions` (
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
CREATE TABLE `furn_low_stock_alerts` (
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
INSERT INTO `furn_suppliers` (`name`, `contact_person`, `email`, `phone`, `address`, `payment_terms`) VALUES
('Ethiopian Leather Co.', 'Abebe Kebede', 'abebe@leatherco.et', '+251-11-123-4567', 'Addis Ababa, Industrial Zone', '30 days'),
('Addis Ababa Timber', 'Mekonnen Haile', 'mekonnen@timber.et', '+251-11-234-5678', 'Addis Ababa, Wood District', '15 days'),
('Metal Works Ltd', 'Kebede Tesfaye', 'kebede@metalworks.et', '+251-11-345-6789', 'Addis Ababa, Metal Industrial Park', '45 days'),
('Comfort Materials Inc', 'Alemu Getachew', 'alemu@comfort.et', '+251-11-456-7890', 'Addis Ababa, Textile Zone', '30 days'),
('Textile Solutions', 'Berhane Weldu', 'berhane@textiles.et', '+251-11-567-8901', 'Addis Ababa, Garment District', '60 days'),
('Glass Manufacturing Co.', 'Tadesse Lemma', 'tadesse@glassco.et', '+251-11-678-9012', 'Addis Ababa, Glass Industrial Area', '30 days'),
('Hardware Distributors', 'Solomon Admassu', 'solomon@hardware.et', '+251-11-789-0123', 'Addis Ababa, Hardware Market', '15 days');

-- Insert material categories
INSERT INTO `furn_material_categories` (`name`, `description`) VALUES
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
INSERT INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'material_management_updated', 'database', 1, '{"message": "Material management system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_materials', NULL, '{"columns_added": ["category_id", "reorder_point", "last_purchase_date", "last_purchase_price", "average_cost", "shelf_life_days", "storage_location", "notes"]}', NOW());

