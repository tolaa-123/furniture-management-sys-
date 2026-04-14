-- Complete Payment System Setup
-- Run this file to set up everything needed for the payment system

USE `furniture_erp`;

-- Step 1: Add missing columns to furn_orders table (if they don't exist)
-- Check and add remaining_balance
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'remaining_balance';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN `remaining_balance` DECIMAL(10,2) DEFAULT NULL AFTER `deposit_paid`', 
    'SELECT "Column remaining_balance already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add final_payment_required
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'final_payment_required';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN `final_payment_required` DECIMAL(10,2) DEFAULT NULL AFTER `remaining_balance`', 
    'SELECT "Column final_payment_required already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add final_payment_paid
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'final_payment_paid';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN `final_payment_paid` DECIMAL(10,2) DEFAULT NULL AFTER `final_payment_required`', 
    'SELECT "Column final_payment_paid already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add final_payment_paid_at
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'final_payment_paid_at';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN `final_payment_paid_at` TIMESTAMP NULL DEFAULT NULL AFTER `final_payment_paid`', 
    'SELECT "Column final_payment_paid_at already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add estimated_completion_date
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'estimated_completion_date';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN `estimated_completion_date` DATE NULL DEFAULT NULL AFTER `production_completed_at`', 
    'SELECT "Column estimated_completion_date already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add actual_completion_date
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'furniture_erp' AND TABLE_NAME = 'furn_orders' AND COLUMN_NAME = 'actual_completion_date';
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE `furn_orders` ADD COLUMN `actual_completion_date` DATE NULL DEFAULT NULL AFTER `estimated_completion_date`', 
    'SELECT "Column actual_completion_date already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Create payments table
CREATE TABLE IF NOT EXISTS `furn_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `customer_id` INT(11) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_type` ENUM('deposit','final') NOT NULL,
    `payment_method` ENUM('cash','bank','mobile') NOT NULL,
    `receipt_path` VARCHAR(255) DEFAULT NULL,
    `transaction_ref` VARCHAR(100) DEFAULT NULL,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `mobile_provider` VARCHAR(50) DEFAULT NULL,
    `payment_date` DATE NOT NULL,
    `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by` INT(11) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_payment_order` (`order_id`),
    KEY `fk_payment_customer` (`customer_id`),
    KEY `fk_payment_approver` (`approved_by`),
    KEY `idx_status` (`status`),
    KEY `idx_payment_date` (`payment_date`),
    CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payment_approver` FOREIGN KEY (`approved_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Ensure we have test users
INSERT IGNORE INTO `furn_users` (`id`, `username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `phone`, `address`, `is_active`) 
VALUES 
(10, 'customer', 'customer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'John', 'Doe', '0911234567', 'Addis Ababa, Ethiopia', 1),
(11, 'customer2', 'customer2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'Jane', 'Smith', '0922345678', 'Jimma, Ethiopia', 1),
(20, 'manager', 'manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Mike', 'Manager', '0933456789', 1);

-- Step 4: Insert sample orders
INSERT INTO `furn_orders` (`id`, `customer_id`, `order_number`, `status`, `total_amount`, `deposit_amount`, `deposit_paid`, `remaining_balance`, `special_instructions`, `created_at`) VALUES
(101, 10, 'ORD-2026-0001', 'waiting_for_deposit', 25000.00, 10000.00, 0.00, 25000.00, '{"delivery_address":"123 Main St, Addis Ababa","payment_method":"bank"}', '2026-03-01 10:00:00'),
(102, 10, 'ORD-2026-0002', 'deposit_paid', 18200.00, 7280.00, 7280.00, 10920.00, '{"delivery_address":"456 Oak Ave, Addis Ababa","payment_method":"mobile"}', '2026-03-05 11:30:00'),
(103, 10, 'ORD-2026-0003', 'in_production', 32500.00, 13000.00, 13000.00, 19500.00, '{"delivery_address":"789 Pine Rd, Addis Ababa","payment_method":"bank"}', '2026-03-08 14:20:00'),
(104, 10, 'ORD-2026-0004', 'ready_for_delivery', 15800.00, 6320.00, 6320.00, 9480.00, '{"delivery_address":"321 Elm St, Addis Ababa","payment_method":"cash"}', '2026-03-10 09:15:00'),
(105, 10, 'ORD-2026-0005', 'completed', 22500.00, 9000.00, 22500.00, 0.00, '{"delivery_address":"654 Maple Dr, Addis Ababa","payment_method":"mobile"}', '2026-02-20 16:45:00'),
(106, 11, 'ORD-2026-0006', 'waiting_for_deposit', 28000.00, 11200.00, 0.00, 28000.00, '{"delivery_address":"987 Cedar Ln, Jimma","payment_method":"bank"}', '2026-03-12 13:00:00')
ON DUPLICATE KEY UPDATE 
    `status` = VALUES(`status`),
    `total_amount` = VALUES(`total_amount`),
    `deposit_amount` = VALUES(`deposit_amount`),
    `deposit_paid` = VALUES(`deposit_paid`),
    `remaining_balance` = VALUES(`remaining_balance`);

-- Step 5: Insert sample order items
INSERT INTO `furn_order_customizations` (`order_id`, `product_id`, `quantity`, `base_price`, `adjusted_price`) VALUES
(101, 1, 1, 15000.00, 15000.00),
(101, 2, 2, 5000.00, 10000.00),
(102, 3, 1, 12000.00, 12000.00),
(102, 4, 2, 3100.00, 6200.00),
(103, 1, 2, 15000.00, 30000.00),
(103, 4, 1, 2500.00, 2500.00),
(104, 2, 1, 8000.00, 8000.00),
(104, 3, 1, 7800.00, 7800.00),
(105, 1, 1, 15000.00, 15000.00),
(105, 4, 3, 2500.00, 7500.00),
(106, 2, 2, 14000.00, 28000.00)
ON DUPLICATE KEY UPDATE 
    `quantity` = VALUES(`quantity`),
    `base_price` = VALUES(`base_price`),
    `adjusted_price` = VALUES(`adjusted_price`);

-- Step 6: Insert sample payments
INSERT INTO `furn_payments` (`id`, `order_id`, `customer_id`, `amount`, `payment_type`, `payment_method`, `receipt_path`, `transaction_ref`, `mobile_provider`, `payment_date`, `submitted_at`, `status`, `approved_by`, `approved_at`) VALUES
(1, 102, 10, 7280.00, 'deposit', 'mobile', '/uploads/receipts/receipt_102_sample.jpg', 'TXN-M-20260305-001', 'TeleBirr', '2026-03-05', '2026-03-05 12:00:00', 'approved', 20, '2026-03-05 14:30:00'),
(2, 103, 10, 13000.00, 'deposit', 'bank', '/uploads/receipts/receipt_103_sample.jpg', 'BNK-20260308-456', 'Commercial Bank of Ethiopia', '2026-03-08', '2026-03-08 15:00:00', 'approved', 20, '2026-03-08 16:45:00'),
(3, 104, 10, 6320.00, 'deposit', 'cash', NULL, NULL, NULL, '2026-03-10', '2026-03-10 10:00:00', 'approved', 20, '2026-03-10 10:30:00'),
(4, 105, 10, 9000.00, 'deposit', 'mobile', '/uploads/receipts/receipt_105_deposit.jpg', 'TXN-M-20260220-789', 'M-PESA', '2026-02-20', '2026-02-20 17:00:00', 'approved', 20, '2026-02-20 18:00:00'),
(5, 105, 10, 13500.00, 'final', 'mobile', '/uploads/receipts/receipt_105_final.jpg', 'TXN-M-20260228-321', 'M-PESA', '2026-02-28', '2026-02-28 10:00:00', 'approved', 20, '2026-02-28 11:00:00'),
(6, 101, 10, 10000.00, 'deposit', 'bank', '/uploads/receipts/receipt_101_pending.jpg', 'BNK-20260313-789', 'Commercial Bank of Ethiopia', '2026-03-13', '2026-03-13 09:00:00', 'pending', NULL, NULL),
(7, 104, 10, 9480.00, 'final', 'bank', '/uploads/receipts/receipt_104_final.jpg', 'BNK-20260314-123', 'Awash Bank', '2026-03-14', '2026-03-14 11:30:00', 'pending', NULL, NULL),
(8, 106, 11, 10000.00, 'deposit', 'bank', '/uploads/receipts/receipt_106_rejected.jpg', 'BNK-20260312-999', 'Dashen Bank', '2026-03-12', '2026-03-12 14:00:00', 'rejected', 20, '2026-03-12 16:00:00')
ON DUPLICATE KEY UPDATE 
    `amount` = VALUES(`amount`),
    `status` = VALUES(`status`);

COMMIT;

-- Display summary
SELECT 'Payment System Setup Complete!' as Status;
SELECT COUNT(*) as Total_Orders FROM furn_orders WHERE customer_id IN (10, 11);
SELECT COUNT(*) as Total_Payments FROM furn_payments;
SELECT status, COUNT(*) as count FROM furn_payments GROUP BY status;
