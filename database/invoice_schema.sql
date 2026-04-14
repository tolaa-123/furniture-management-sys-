-- Professional PDF Invoice System Database Schema
-- Invoice generation and management system

USE furniture_erp;

-- Create invoice configuration table
CREATE TABLE `furn_invoice_config` (
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
CREATE TABLE `furn_invoices` (
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
CREATE TABLE `furn_invoice_items` (
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
CREATE TABLE `furn_invoice_payments` (
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
INSERT INTO `furn_invoice_config` (
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
CREATE VIEW `vw_invoice_overview` AS
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
INSERT INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'invoice_system_created', 'database', 1, '{"message": "Professional PDF invoice system tables created"}', NOW()),
(NULL, 'schema_update', 'furn_orders', NULL, '{"columns_added": ["invoice_generated", "invoice_id"]}', NOW());

