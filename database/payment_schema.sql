-- Payment Management Schema
-- Create payments table for custom furniture orders

USE `furniture_erp`;

-- Create furn_payments table
CREATE TABLE IF NOT EXISTS `furn_payments` (
    `payment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `customer_id` INT(11) NOT NULL,
    `payment_type` ENUM('prepayment', 'postpayment') NOT NULL COMMENT 'prepayment = deposit, postpayment = final payment',
    `payment_method` ENUM('bank', 'cash') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `receipt_file` VARCHAR(255) DEFAULT NULL COMMENT 'Path to uploaded receipt file',
    `transaction_reference` VARCHAR(100) DEFAULT NULL COMMENT 'Bank transaction reference number',
    `bank_name` VARCHAR(100) DEFAULT NULL COMMENT 'Name of bank for transfer',
    `payment_date` DATE NOT NULL,
    `payment_notes` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `verified_by` INT(11) DEFAULT NULL COMMENT 'Manager who verified payment',
    `verified_at` DATETIME DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`payment_id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Payment records for custom furniture orders';

-- Insert sample payment data
INSERT INTO `furn_payments` (
    `order_id`, `customer_id`, `payment_type`, `payment_method`,
    `amount`, `transaction_reference`, `bank_name`, `payment_date`,
    `payment_notes`, `status`, `created_at`
) VALUES
-- Payment for ORD-2026-00102 (Approved deposit)
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00102' LIMIT 1), 
 10, 'prepayment', 'bank', 12800.00, 'TXN20260306001', 'Commercial Bank of Ethiopia',
 '2026-03-06', 'Deposit payment for King Size Bed', 'approved', '2026-03-06 15:00:00'),

-- Payment for ORD-2026-00103 (Approved deposit)
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00103' LIMIT 1),
 10, 'prepayment', 'cash', 11200.00, NULL, NULL,
 '2026-03-05', 'Cash deposit payment', 'approved', '2026-03-05 10:00:00'),

-- Payment for ORD-2026-00104 (Approved deposit)
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00104' LIMIT 1),
 10, 'prepayment', 'bank', 8800.00, 'TXN20260301001', 'Dashen Bank',
 '2026-03-01', 'Deposit for dining table', 'approved', '2026-03-01 12:00:00'),

-- Payment for ORD-2026-00104 (Approved final payment)
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00104' LIMIT 1),
 10, 'postpayment', 'bank', 13200.00, 'TXN20260305001', 'Dashen Bank',
 '2026-03-05', 'Final payment for dining table', 'approved', '2026-03-05 14:00:00'),

-- Pending payment for ORD-2026-00101
((SELECT id FROM furn_orders WHERE order_number = 'ORD-2026-00101' LIMIT 1),
 10, 'prepayment', 'bank', 7400.00, 'TXN20260307001', 'Awash Bank',
 '2026-03-07', 'Deposit payment for study table', 'pending', '2026-03-07 11:00:00')

ON DUPLICATE KEY UPDATE payment_type = VALUES(payment_type);

-- Update deposit_amount for orders (40% of estimated cost) - only if columns exist
UPDATE furn_orders 
SET deposit_amount = ROUND(total_amount * 0.4, 2)
WHERE total_amount > 0 AND deposit_amount IS NULL;

COMMIT;

-- Display results
SELECT 'Payment Schema Created Successfully!' as Status;
SELECT COUNT(*) as Total_Payments FROM furn_payments;
SELECT 
    payment_type,
    status,
    COUNT(*) as count,
    SUM(amount) as total_amount
FROM furn_payments
GROUP BY payment_type, status;

