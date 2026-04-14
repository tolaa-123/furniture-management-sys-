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
