-- Custom Furniture ERP System Database Schema
-- Section 4: Manager Cost Approval Workflow

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `furniture_erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `furniture_erp`;

-- Users table with role-based access
CREATE TABLE `furn_users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'manager', 'employee', 'customer') NOT NULL DEFAULT 'customer',
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20),
    `address` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_role` (`role`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product categories
CREATE TABLE `furn_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE `furn_products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `base_price` DECIMAL(10,2) NOT NULL,
    `estimated_production_time` INT(11) NOT NULL COMMENT 'In days',
    `materials_used` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_category` (`category_id`),
    CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `furn_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product images
CREATE TABLE `furn_product_images` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_product_image` (`product_id`),
    CONSTRAINT `fk_product_image` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE `furn_orders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `order_number` VARCHAR(20) NOT NULL UNIQUE,
    `status` ENUM('pending_cost_approval', 'waiting_for_deposit', 'deposit_paid', 'in_production', 'ready_for_delivery', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_cost_approval',
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deposit_amount` DECIMAL(10,2) DEFAULT NULL,
    `deposit_paid` DECIMAL(10,2) DEFAULT NULL,
    `deposit_paid_at` TIMESTAMP NULL DEFAULT NULL,
    `production_started_at` TIMESTAMP NULL DEFAULT NULL,
    `production_completed_at` TIMESTAMP NULL DEFAULT NULL,
    `delivery_date` DATE NULL DEFAULT NULL,
    `special_instructions` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_customer` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_order_number` (`order_number`),
    CONSTRAINT `fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order customizations
CREATE TABLE `furn_order_customizations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `size_modifications` TEXT,
    `color_selection` VARCHAR(50),
    `material_upgrade` VARCHAR(100),
    `additional_features` TEXT,
    `reference_image_path` VARCHAR(255),
    `notes` TEXT,
    `base_price` DECIMAL(10,2) NOT NULL,
    `adjusted_price` DECIMAL(10,2) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_order` (`order_id`),
    KEY `fk_product_customization` (`product_id`),
    CONSTRAINT `fk_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_customization` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs for critical actions
CREATE TABLE `furn_audit_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` INT(11) DEFAULT NULL,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_audit_user` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_table_record` (`table_name`, `record_id`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO `furn_categories` (`name`, `description`) VALUES
('Sofa', 'Comfortable seating solutions for living rooms'),
('Bed', 'Quality beds and bedroom furniture'),
('Table', 'Dining tables, coffee tables, and work desks'),
('Chair', 'Various types of chairs for different purposes');

-- Insert sample products
INSERT INTO `furn_products` (`category_id`, `name`, `description`, `base_price`, `estimated_production_time`, `materials_used`) VALUES
(1, 'Premium Leather Sofa', 'Luxurious 3-seater leather sofa with premium cushioning', 15000.00, 21, 'Full-grain leather, solid wood frame, high-density foam'),
(2, 'King Size Wooden Bed', 'Handcrafted king size bed with premium wood finish', 25000.00, 30, 'Solid oak wood, premium mattress support, eco-friendly finish'),
(3, 'Modern Dining Table', 'Contemporary 6-seater dining table with glass top', 12000.00, 18, 'Tempered glass, stainless steel frame, wooden base'),
(4, 'Executive Office Chair', 'Ergonomic office chair with lumbar support', 3500.00, 10, 'Premium fabric, steel frame, adjustable mechanisms');

-- Insert sample admin user (password: admin123)
INSERT IGNORE INTO `furn_users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`) VALUES
('admin', 'admin@furniture.com', '$2y$10$R9Ene/egMPkE7i6wmR72puQYarFUGYn1EIORQ8ud67DhEbj7S/Ul2', 'admin', 'System', 'Administrator', 1);

-- Insert sample manager user (password: manager123)
INSERT IGNORE INTO `furn_users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`) VALUES
('manager', 'manager@furniture.com', '$2y$10$mGabhp3LuWe8ZQF5oYcX/.5uZXivrmmWfMFgwWv4qa.qOmei7aVGO', 'manager', 'John', 'Manager', 1);

-- Insert sample customer (password: customer123)
INSERT IGNORE INTO `furn_users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `phone`, `address`, `is_active`) VALUES
('customer', 'customer@furniture.com', '$2y$10$hSWa.iw9zcaOZSePhhISj.glaGDYPVsems6dXouFvTWv8M9.Vh4MK', 'customer', 'Alice', 'Customer', '+251911123456', 'Addis Ababa, Ethiopia', 1);

-- Insert sample employee (password: employee123)
INSERT IGNORE INTO `furn_users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`) VALUES
('employee', 'employee@furniture.com', '$2y$10$J0d9EGNvRolPUg9qOfY6NOy/LDkG5acfF.wlbfjesFx3/Dvn2yN5e', 'employee', 'John', 'Employee', 1);

COMMIT;