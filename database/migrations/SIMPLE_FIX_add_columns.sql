-- ============================================
-- SIMPLE FIX: Add promotion columns to furn_orders
-- Run this in phpMyAdmin SQL tab
-- ============================================

-- Add the three columns needed for promotions
ALTER TABLE furn_orders 
ADD COLUMN promotion_id INT NULL COMMENT 'Applied promotion ID',
ADD COLUMN original_price DECIMAL(10,2) NULL COMMENT 'Price before discount',
ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Discount applied';

-- Add index for better performance
ALTER TABLE furn_orders 
ADD INDEX idx_promotion (promotion_id);

-- Verify columns were added
DESCRIBE furn_orders;
