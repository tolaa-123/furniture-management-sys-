-- ============================================
-- ADD PROMOTION COLUMNS TO ORDERS TABLE
-- Compatible with MySQL 5.7 and 8.0+
-- ============================================

-- Check if columns exist and add them if they don't
-- This script is safe to run multiple times

-- Add promotion_id column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'furn_orders' 
    AND COLUMN_NAME = 'promotion_id');

SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE furn_orders ADD COLUMN promotion_id INT NULL COMMENT "Applied promotion ID"',
    'SELECT "Column promotion_id already exists" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add original_price column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'furn_orders' 
    AND COLUMN_NAME = 'original_price');

SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE furn_orders ADD COLUMN original_price DECIMAL(10,2) NULL COMMENT "Price before discount"',
    'SELECT "Column original_price already exists" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add discount_amount column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'furn_orders' 
    AND COLUMN_NAME = 'discount_amount');

SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE furn_orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0 COMMENT "Discount applied"',
    'SELECT "Column discount_amount already exists" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on promotion_id
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'furn_orders' 
    AND INDEX_NAME = 'idx_promotion');

SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE furn_orders ADD INDEX idx_promotion (promotion_id)',
    'SELECT "Index idx_promotion already exists" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify columns were added
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'furn_orders'
  AND COLUMN_NAME IN ('promotion_id', 'original_price', 'discount_amount')
ORDER BY COLUMN_NAME;

SELECT 'Promotion columns added successfully!' AS status;
