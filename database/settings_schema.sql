-- Admin Settings Database Schema
-- Run this to create necessary tables for admin settings

-- 1. System Settings Table
CREATE TABLE IF NOT EXISTS furn_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    category VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Company Information Table
CREATE TABLE IF NOT EXISTS furn_company_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Ethiopia',
    phone_primary VARCHAR(20),
    phone_secondary VARCHAR(20),
    email_primary VARCHAR(100),
    email_secondary VARCHAR(100),
    website VARCHAR(255),
    logo_path VARCHAR(255),
    tax_id VARCHAR(50),
    registration_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Email Configuration Table
CREATE TABLE IF NOT EXISTS furn_email_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    smtp_encryption ENUM('none', 'tls', 'ssl') DEFAULT 'tls',
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) DEFAULT 'FurnitureCraft Workshop',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tax Configuration Table
CREATE TABLE IF NOT EXISTS furn_tax_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_name VARCHAR(100) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL,
    tax_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    is_compound BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Payment Methods Table
CREATE TABLE IF NOT EXISTS furn_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(100) NOT NULL,
    method_type ENUM('cash', 'bank_transfer', 'card', 'mobile_money', 'check', 'other') DEFAULT 'cash',
    account_details TEXT,
    instructions TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Settings
INSERT INTO furn_settings (setting_key, setting_value, setting_type, category) VALUES
-- General Settings
('site_name', 'FurnitureCraft Workshop', 'text', 'general'),
('currency', 'ETB', 'text', 'general'),
('timezone', 'Africa/Addis_Ababa', 'text', 'general'),
('date_format', 'Y-m-d', 'text', 'general'),
('language', 'en', 'text', 'general'),

-- Business Settings
('fiscal_year_start', '01-01', 'text', 'business'),
('default_deposit_percentage', '50', 'number', 'business'),
('allow_backorders', '0', 'boolean', 'business'),
('auto_approve_orders', '0', 'boolean', 'business'),

-- Notification Settings
('email_notifications', '1', 'boolean', 'notifications'),
('sms_notifications', '0', 'boolean', 'notifications'),
('order_confirmation_email', '1', 'boolean', 'notifications'),
('order_status_updates', '1', 'boolean', 'notifications'),
('payment_received_email', '1', 'boolean', 'notifications'),
('low_stock_alert', '1', 'boolean', 'notifications'),
('new_order_alert', '1', 'boolean', 'notifications'),

-- Security Settings
('session_timeout', '3600', 'number', 'security'),
('password_min_length', '8', 'number', 'security'),
('require_special_char', '1', 'boolean', 'security'),
('max_login_attempts', '5', 'number', 'security'),
('lockout_duration', '900', 'number', 'security'),

-- System Settings
('maintenance_mode', '0', 'boolean', 'system'),
('cache_enabled', '1', 'boolean', 'system'),
('debug_mode', '0', 'boolean', 'system'),
('log_retention_days', '30', 'number', 'system')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Insert Default Company Info
INSERT INTO furn_company_info (
    company_name, address_line1, city, country, phone_primary, 
    email_primary, website
) VALUES (
    'FurnitureCraft Workshop',
    'Addis Ababa',
    'Addis Ababa',
    'Ethiopia',
    '+251-911-123456',
    'info@furniturecraft.com',
    'www.furniturecraft.com'
) ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

-- Insert Default Tax Configuration
INSERT INTO furn_tax_config (tax_name, tax_rate, tax_type, is_active) VALUES
('VAT', 15.00, 'percentage', TRUE),
('Service Charge', 10.00, 'percentage', FALSE)
ON DUPLICATE KEY UPDATE tax_name = VALUES(tax_name);

-- Insert Default Payment Methods
INSERT INTO furn_payment_methods (method_name, method_type, is_active, display_order) VALUES
('Cash', 'cash', TRUE, 1),
('Bank Transfer', 'bank_transfer', TRUE, 2),
('CBE Birr', 'mobile_money', TRUE, 3),
('Telebirr', 'mobile_money', TRUE, 4),
('Check', 'check', TRUE, 5)
ON DUPLICATE KEY UPDATE method_name = VALUES(method_name);
