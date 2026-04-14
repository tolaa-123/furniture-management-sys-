-- Section 10: Profit Calculation Engine Database Schema
-- Profit analysis and reporting system

USE furniture_erp;

-- Create profit calculation table to store calculated profits
CREATE TABLE `furn_profit_calculations` (
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
CREATE TABLE `furn_profit_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create monthly profit summary table
CREATE TABLE `furn_monthly_profit_summary` (
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
CREATE TABLE `furn_product_profit_summary` (
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
INSERT INTO `furn_profit_settings` (`setting_key`, `setting_value`, `description`) VALUES
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
CREATE VIEW `vw_profit_analysis` AS
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
CREATE VIEW `vw_monthly_profit_trends` AS
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
INSERT INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'profit_engine_created', 'database', 1, '{"message": "Profit calculation engine tables created"}', NOW()),
(NULL, 'schema_update', 'furn_orders', NULL, '{"columns_added": ["profit_calculated", "profit_calculation_date"]}', NOW());

