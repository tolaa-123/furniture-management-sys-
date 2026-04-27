-- ============================================
-- PROMOTION FEATURE VERIFICATION SCRIPT
-- Run this after deploying the migration to verify everything works
-- ============================================

-- 1. Check if tables exist
SELECT 'Checking tables...' as step;
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME IN ('furn_promotions', 'furn_order_promotions')
ORDER BY TABLE_NAME;

-- 2. Check if view exists
SELECT 'Checking view...' as step;
SELECT 
    TABLE_NAME,
    TABLE_TYPE
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'vw_active_promotions';

-- 3. Check promotion columns in orders table
SELECT 'Checking furn_orders columns...' as step;
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'furn_orders'
  AND COLUMN_NAME IN ('promotion_id', 'original_price', 'discount_amount')
ORDER BY COLUMN_NAME;

-- 4. List all promotions
SELECT 'All promotions:' as step;
SELECT 
    id,
    name,
    discount_type,
    discount_value,
    applies_to,
    target_category,
    DATE_FORMAT(start_date, '%Y-%m-%d') as start_date,
    DATE_FORMAT(end_date, '%Y-%m-%d') as end_date,
    is_active,
    show_on_homepage
FROM furn_promotions
ORDER BY id;

-- 5. List active promotions (using view)
SELECT 'Active promotions (from view):' as step;
SELECT 
    id,
    name,
    discount_value,
    applies_to,
    target_category,
    status,
    days_remaining,
    show_on_homepage
FROM vw_active_promotions
ORDER BY discount_value DESC;

-- 6. Test promotion selection for different categories
SELECT 'Best promotion for SOFA category:' as step;
SELECT 
    name,
    discount_value,
    applies_to,
    target_category,
    customer_type
FROM vw_active_promotions
WHERE applies_to = 'all' 
   OR (applies_to = 'category' AND target_category = 'Sofa')
   OR applies_to = 'first_order'
ORDER BY discount_value DESC
LIMIT 1;

SELECT 'Best promotion for TABLE category:' as step;
SELECT 
    name,
    discount_value,
    applies_to,
    target_category,
    customer_type
FROM vw_active_promotions
WHERE applies_to = 'all' 
   OR (applies_to = 'category' AND target_category = 'Table')
   OR applies_to = 'first_order'
ORDER BY discount_value DESC
LIMIT 1;

SELECT 'Best promotion for CHAIR category (should be first_order only):' as step;
SELECT 
    name,
    discount_value,
    applies_to,
    target_category,
    customer_type
FROM vw_active_promotions
WHERE applies_to = 'all' 
   OR (applies_to = 'category' AND target_category = 'Chair')
   OR applies_to = 'first_order'
ORDER BY discount_value DESC
LIMIT 1;

-- 7. Check orders with promotions (if any exist)
SELECT 'Orders with promotions applied:' as step;
SELECT 
    o.id,
    o.order_number,
    o.furniture_type,
    o.promotion_id,
    p.name as promotion_name,
    p.discount_value,
    o.created_at
FROM furn_orders o
LEFT JOIN furn_promotions p ON o.promotion_id = p.id
WHERE o.promotion_id IS NOT NULL
ORDER BY o.created_at DESC
LIMIT 10;

-- 8. Summary statistics
SELECT 'Summary Statistics:' as step;
SELECT 
    (SELECT COUNT(*) FROM furn_promotions) as total_promotions,
    (SELECT COUNT(*) FROM vw_active_promotions) as active_promotions,
    (SELECT COUNT(*) FROM furn_promotions WHERE show_on_homepage = 1 AND is_active = 1) as homepage_promotions,
    (SELECT COUNT(*) FROM furn_orders WHERE promotion_id IS NOT NULL) as orders_with_promotions,
    (SELECT SUM(usage_count) FROM furn_promotions) as total_promotion_usage;

-- 9. Test discount calculation examples
SELECT 'Discount Calculation Examples:' as step;
SELECT 
    name,
    discount_type,
    discount_value,
    10000 as original_price,
    CASE 
        WHEN discount_type = 'percentage' THEN 10000 * (discount_value / 100)
        ELSE discount_value
    END as discount_amount,
    CASE 
        WHEN discount_type = 'percentage' THEN 10000 - (10000 * (discount_value / 100))
        ELSE 10000 - discount_value
    END as final_price
FROM furn_promotions
WHERE is_active = 1
ORDER BY discount_value DESC;

-- ============================================
-- VERIFICATION CHECKLIST
-- ============================================
-- ✓ Tables exist: furn_promotions, furn_order_promotions
-- ✓ View exists: vw_active_promotions
-- ✓ Orders table has: promotion_id, original_price, discount_amount
-- ✓ 3 sample promotions inserted
-- ✓ All promotions are active
-- ✓ Promotion selection logic works correctly
-- ✓ Discount calculations are accurate
-- ============================================
