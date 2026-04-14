-- Migration: Fix furn_orders status ENUM to include all statuses used by the app
USE `furniture_erp`;

ALTER TABLE `furn_orders`
    MODIFY COLUMN `status` ENUM(
        'pending',
        'pending_review',
        'pending_cost_approval',
        'cost_estimated',
        'waiting_for_deposit',
        'deposit_paid',
        'payment_verified',
        'in_production',
        'production_started',
        'production_completed',
        'ready_for_delivery',
        'final_payment_paid',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending_cost_approval';

SELECT 'Migration complete!' as Status;
