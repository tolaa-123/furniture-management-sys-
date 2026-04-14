-- Section 11: Dashboard & Analytics Database Schema
-- Analytics and reporting system with Chart.js integration

USE furniture_erp;

-- Create analytics dashboard configuration table
CREATE TABLE `furn_dashboard_config` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `widget_key` VARCHAR(50) NOT NULL UNIQUE,
    `widget_name` VARCHAR(100) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT(11) NOT NULL DEFAULT 0,
    `chart_type` VARCHAR(20) NOT NULL DEFAULT 'line',
    `data_source` VARCHAR(100) NOT NULL,
    `refresh_interval` INT(11) NOT NULL DEFAULT 300, -- seconds
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create analytics cache table for performance
CREATE TABLE `furn_analytics_cache` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(100) NOT NULL UNIQUE,
    `cache_data` LONGTEXT NOT NULL,
    `data_type` VARCHAR(20) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cache_key` (`cache_key`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create dashboard widgets table
CREATE TABLE `furn_dashboard_widgets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `widget_config` JSON NOT NULL,
    `position_x` INT(11) NOT NULL DEFAULT 0,
    `position_y` INT(11) NOT NULL DEFAULT 0,
    `width` INT(11) NOT NULL DEFAULT 4,
    `height` INT(11) NOT NULL DEFAULT 3,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_dashboard_user` (`user_id`),
    CONSTRAINT `fk_dashboard_user` FOREIGN KEY (`user_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default dashboard configuration
INSERT INTO `furn_dashboard_config` (`widget_key`, `widget_name`, `display_order`, `chart_type`, `data_source`) VALUES
('monthly_revenue', 'Monthly Revenue', 1, 'line', 'orders'),
('orders_by_status', 'Orders by Status', 2, 'pie', 'orders'),
('employee_hours', 'Employee Working Hours', 3, 'bar', 'attendance'),
('low_stock_alerts', 'Low Stock Alerts', 4, 'doughnut', 'materials'),
('top_products', 'Top Selling Products', 5, 'bar', 'orders'),
('monthly_profit', 'Monthly Profit', 6, 'line', 'profit');

-- Create analytics views for performance
CREATE VIEW `vw_monthly_revenue` AS
SELECT 
    YEAR(o.created_at) as year,
    MONTH(o.created_at) as month,
    DATE_FORMAT(o.created_at, '%Y-%m') as month_year,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as total_revenue,
    AVG(o.total_amount) as avg_order_value
FROM furn_orders o
WHERE o.status IN ('completed', 'delivered', 'paid')
GROUP BY YEAR(o.created_at), MONTH(o.created_at)
ORDER BY YEAR(o.created_at) DESC, MONTH(o.created_at) DESC;

CREATE VIEW `vw_orders_by_status` AS
SELECT 
    status,
    COUNT(*) as count,
    SUM(total_amount) as total_value
FROM furn_orders
GROUP BY status;

CREATE VIEW `vw_employee_hours_summary` AS
SELECT 
    u.id as employee_id,
    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
    COUNT(a.id) as days_worked
FROM furn_users u
JOIN furn_attendance a ON u.id = a.employee_id
WHERE u.role = 'employee' AND a.status = 'present'
GROUP BY u.id, u.first_name, u.last_name
ORDER BY days_worked DESC;

CREATE VIEW `vw_low_stock_materials` AS
SELECT 
    m.id,
    m.name,
    m.current_stock,
    m.minimum_stock,
    m.unit,
    CASE 
        WHEN m.current_stock <= m.minimum_stock THEN 'critical'
        WHEN m.current_stock <= (m.minimum_stock * 1.5) THEN 'warning'
        ELSE 'normal'
    END as stock_status
FROM furn_materials m
WHERE m.current_stock <= (m.minimum_stock * 2) AND m.is_active = 1
ORDER BY m.current_stock ASC;

CREATE VIEW `vw_top_selling_products` AS
SELECT 
    p.id as product_id,
    p.name as product_name,
    p.category_id,
    COUNT(oc.id) as orders_count,
    SUM(oc.quantity) as total_quantity,
    SUM(o.total_amount) as total_revenue
FROM furn_products p
JOIN furn_order_customizations oc ON p.id = oc.product_id
JOIN furn_orders o ON oc.order_id = o.id
WHERE o.status IN ('completed', 'delivered', 'paid')
GROUP BY p.id, p.name, p.category_id
ORDER BY total_revenue DESC
LIMIT 10;

CREATE VIEW `vw_monthly_profit` AS
SELECT 
    YEAR(pc.calculated_at) as year,
    MONTH(pc.calculated_at) as month,
    DATE_FORMAT(pc.calculated_at, '%Y-%m') as month_year,
    COUNT(pc.id) as calculated_orders,
    SUM(pc.final_selling_price) as total_revenue,
    SUM(pc.total_cost) as total_cost,
    SUM(pc.profit) as total_profit,
    AVG(pc.profit_margin_percentage) as avg_profit_margin
FROM furn_profit_calculations pc
GROUP BY YEAR(pc.calculated_at), MONTH(pc.calculated_at)
ORDER BY YEAR(pc.calculated_at) DESC, MONTH(pc.calculated_at) DESC;

-- Add audit log entries
INSERT INTO `furn_audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `created_at`) 
VALUES 
(NULL, 'analytics_engine_created', 'database', 1, '{"message": "Dashboard analytics tables created"}', NOW()),
(NULL, 'schema_update', 'dashboard', NULL, '{"widgets": ["monthly_revenue", "orders_by_status", "employee_hours", "low_stock_alerts", "top_products", "monthly_profit"]}', NOW());