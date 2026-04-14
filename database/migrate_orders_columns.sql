-- Migration: Add missing columns to furn_orders table
USE `furniture_erp`;

-- Fix status ENUM to include all used values
ALTER TABLE `furn_orders`
    MODIFY COLUMN `status` ENUM(
        'pending',
        'pending_review',
        'pending_cost_approval',
        'cost_estimated',
        'waiting_for_deposit',
        'deposit_paid',
        'in_production',
        'production_started',
        'ready_for_delivery',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending_cost_approval';

-- Add missing columns
ALTER TABLE `furn_orders`
    ADD COLUMN IF NOT EXISTS `furniture_type` VARCHAR(100) DEFAULT NULL AFTER `order_number`,
    ADD COLUMN IF NOT EXISTS `furniture_name` VARCHAR(255) DEFAULT NULL AFTER `furniture_type`,
    ADD COLUMN IF NOT EXISTS `length` DECIMAL(10,2) DEFAULT NULL AFTER `furniture_name`,
    ADD COLUMN IF NOT EXISTS `width` DECIMAL(10,2) DEFAULT NULL AFTER `length`,
    ADD COLUMN IF NOT EXISTS `height` DECIMAL(10,2) DEFAULT NULL AFTER `width`,
    ADD COLUMN IF NOT EXISTS `material` VARCHAR(100) DEFAULT NULL AFTER `height`,
    ADD COLUMN IF NOT EXISTS `color` VARCHAR(100) DEFAULT NULL AFTER `material`,
    ADD COLUMN IF NOT EXISTS `quantity` INT(11) DEFAULT 1 AFTER `color`,
    ADD COLUMN IF NOT EXISTS `budget_range` VARCHAR(100) DEFAULT NULL AFTER `quantity`,
    ADD COLUMN IF NOT EXISTS `preferred_delivery_date` DATE DEFAULT NULL AFTER `budget_range`,
    ADD COLUMN IF NOT EXISTS `design_description` TEXT DEFAULT NULL AFTER `preferred_delivery_date`,
    ADD COLUMN IF NOT EXISTS `design_image` VARCHAR(255) DEFAULT NULL AFTER `design_description`,
    ADD COLUMN IF NOT EXISTS `special_notes` TEXT DEFAULT NULL AFTER `design_image`,
    ADD COLUMN IF NOT EXISTS `estimated_cost` DECIMAL(10,2) DEFAULT NULL AFTER `special_notes`,
    ADD COLUMN IF NOT EXISTS `estimated_production_days` INT(11) DEFAULT NULL AFTER `estimated_cost`,
    ADD COLUMN IF NOT EXISTS `manager_notes` TEXT DEFAULT NULL AFTER `estimated_production_days`,
    ADD COLUMN IF NOT EXISTS `reviewed_by` INT(11) DEFAULT NULL AFTER `manager_notes`,
    ADD COLUMN IF NOT EXISTS `reviewed_at` TIMESTAMP NULL DEFAULT NULL AFTER `reviewed_by`,
    ADD COLUMN IF NOT EXISTS `remaining_balance` DECIMAL(10,2) DEFAULT NULL AFTER `deposit_paid`,
    ADD COLUMN IF NOT EXISTS `final_payment_required` DECIMAL(10,2) DEFAULT NULL AFTER `remaining_balance`,
    ADD COLUMN IF NOT EXISTS `final_payment_paid` DECIMAL(10,2) DEFAULT NULL AFTER `final_payment_required`,
    ADD COLUMN IF NOT EXISTS `final_payment_paid_at` TIMESTAMP NULL DEFAULT NULL AFTER `final_payment_paid`,
    ADD COLUMN IF NOT EXISTS `estimated_completion_date` DATE DEFAULT NULL AFTER `delivery_date`,
    ADD COLUMN IF NOT EXISTS `actual_completion_date` DATE DEFAULT NULL AFTER `estimated_completion_date`;

SELECT 'Migration complete!' as Status;
