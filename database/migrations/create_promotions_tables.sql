-- ============================================
-- PROMOTIONS & DISCOUNTS FEATURE
-- ============================================
-- This migration adds promotion/discount functionality
-- without affecting existing tables or data
-- ============================================

-- Create promotions table
CREATE TABLE IF NOT EXISTS furn_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Basic Info
    name VARCHAR(255) NOT NULL COMMENT 'Promotion name (e.g., "Summer Sale 2024")',
    description TEXT COMMENT 'Detailed description of the promotion',
    
    -- Discount Configuration
    discount_type ENUM('percentage', 'fixed_amount') DEFAULT 'percentage' COMMENT 'Type of discount',
    discount_value DECIMAL(10,2) NOT NULL COMMENT 'Discount value (e.g., 20 for 20% or 1000 for ETB 1000)',
    
    -- Time Period
    start_date DATETIME NOT NULL COMMENT 'When promotion starts',
    end_date DATETIME NOT NULL COMMENT 'When promotion ends',
    
    -- Applicability Rules
    applies_to ENUM('all', 'category', 'product', 'first_order') DEFAULT 'all' COMMENT 'What the promotion applies to',
    target_category VARCHAR(100) NULL COMMENT 'Category name if applies_to = category (e.g., "Sofa", "Table")',
    target_product_id INT NULL COMMENT 'Product ID if applies_to = product',
    
    -- Conditions
    min_order_value DECIMAL(10,2) DEFAULT 0 COMMENT 'Minimum order value to qualify',
    max_discount_amount DECIMAL(10,2) NULL COMMENT 'Maximum discount amount (cap)',
    
    -- Customer Eligibility
    customer_type ENUM('all', 'new', 'returning') DEFAULT 'all' COMMENT 'Which customers can use this',
    
    -- Usage Tracking
    usage_count INT DEFAULT 0 COMMENT 'How many times this promotion has been used',
    max_usage INT NULL COMMENT 'Maximum number of times this can be used (NULL = unlimited)',
    
    -- Display Settings
    banner_text VARCHAR(500) NULL COMMENT 'Text to show in banner',
    badge_text VARCHAR(50) NULL COMMENT 'Text for product badge (e.g., "20% OFF")',
    show_on_homepage TINYINT(1) DEFAULT 1 COMMENT 'Show banner on homepage',
    
    -- Status
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Is promotion currently active',
    
    -- Audit
    created_by INT COMMENT 'User ID who created this promotion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_dates (start_date, end_date),
    INDEX idx_active (is_active),
    INDEX idx_category (target_category),
    INDEX idx_applies_to (applies_to)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores promotion/discount campaigns';

-- Create order promotions tracking table
CREATE TABLE IF NOT EXISTS furn_order_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Links
    order_id INT NOT NULL COMMENT 'Order that used this promotion',
    promotion_id INT NOT NULL COMMENT 'Promotion that was applied',
    
    -- Price Tracking
    original_price DECIMAL(10,2) NOT NULL COMMENT 'Price before discount',
    discount_amount DECIMAL(10,2) NOT NULL COMMENT 'Amount discounted',
    final_price DECIMAL(10,2) NOT NULL COMMENT 'Price after discount',
    
    -- Metadata
    promotion_name VARCHAR(255) COMMENT 'Snapshot of promotion name',
    discount_percentage DECIMAL(5,2) COMMENT 'Snapshot of discount % for reporting',
    
    -- Audit
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_order (order_id),
    INDEX idx_promotion (promotion_id),
    INDEX idx_applied_date (applied_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks which promotions were applied to which orders';

-- Add promotion columns to furn_orders table (if not exists)
ALTER TABLE furn_orders 
    ADD COLUMN IF NOT EXISTS promotion_id INT NULL COMMENT 'Applied promotion ID',
    ADD COLUMN IF NOT EXISTS original_price DECIMAL(10,2) NULL COMMENT 'Price before discount',
    ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Discount applied',
    ADD INDEX IF NOT EXISTS idx_promotion (promotion_id);

-- Insert sample promotions for testing
INSERT INTO furn_promotions (
    name, description, discount_type, discount_value, 
    start_date, end_date, applies_to, target_category,
    banner_text, badge_text, show_on_homepage, is_active, created_by
) VALUES 
(
    'Summer Sofa Sale 2024',
    'Get 20% off all sofas this summer! Limited time offer.',
    'percentage',
    20.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 30 DAY),
    'category',
    'Sofa',
    '🎉 SUMMER SALE! 20% OFF ALL SOFAS - Limited Time Only!',
    '20% OFF',
    1,
    1,
    1
),
(
    'New Customer Welcome Discount',
    'First-time customers get 25% off their first order!',
    'percentage',
    25.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 90 DAY),
    'first_order',
    NULL,
    '🎁 Welcome! Get 25% OFF your first order!',
    '25% OFF',
    1,
    1,
    1
),
(
    'Table Collection Sale',
    '15% discount on all tables - dining, coffee, and side tables.',
    'percentage',
    15.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 14 DAY),
    'category',
    'Table',
    '💰 15% OFF ALL TABLES - 2 Weeks Only!',
    '15% OFF',
    1,
    1,
    1
);

-- Create view for active promotions (easier querying)
CREATE OR REPLACE VIEW vw_active_promotions AS
SELECT 
    p.*,
    CASE 
        WHEN NOW() < p.start_date THEN 'upcoming'
        WHEN NOW() > p.end_date THEN 'expired'
        WHEN p.max_usage IS NOT NULL AND p.usage_count >= p.max_usage THEN 'maxed_out'
        WHEN p.is_active = 0 THEN 'inactive'
        ELSE 'active'
    END as status,
    DATEDIFF(p.end_date, NOW()) as days_remaining,
    TIMESTAMPDIFF(HOUR, NOW(), p.end_date) as hours_remaining
FROM furn_promotions p
WHERE p.is_active = 1 
  AND NOW() BETWEEN p.start_date AND p.end_date
  AND (p.max_usage IS NULL OR p.usage_count < p.max_usage);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Run these to verify tables were created:
-- SELECT * FROM furn_promotions;
-- SELECT * FROM vw_active_promotions;
-- SELECT * FROM furn_order_promotions;
-- ============================================
