-- Custom Furniture Orders Schema
-- Add columns for custom furniture order system

USE `furniture_erp`;

-- Add custom furniture columns to furn_orders table if they don't exist
ALTER TABLE `furn_orders`
ADD COLUMN IF NOT EXISTS `furniture_type` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `furniture_name` VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `length` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `width` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `height` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `material` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `color` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `design_description` TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `design_image` VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `special_notes` TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `estimated_cost` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `status` VARCHAR(50) DEFAULT 'pending_approval';

-- Insert sample custom furniture orders
INSERT INTO `furn_orders` (
    `customer_id`, `order_number`, `furniture_type`, `furniture_name`,
    `length`, `width`, `height`, `material`, `color`,
    `design_description`, `special_notes`,
    `estimated_cost`, `deposit_amount`, `remaining_balance`,
    `status`, `created_at`
) VALUES
(10, 'ORD-2026-00101', 'Desk', 'Modern Wooden Study Table', 
 120.00, 60.00, 75.00, 'Oak Wood', 'Natural Wood',
 'I want a modern desk with two drawers and cable holes for computer wires. The design should be minimalist with clean lines.',
 'Please make the table legs removable for easy transport.',
 18500.00, 7400.00, 18500.00, 'pending_approval', '2026-03-07 10:30:00'),

(10, 'ORD-2026-00102', 'Bed', 'King Size Bed Frame',
 200.00, 180.00, 120.00, 'Mahogany Wood', 'Dark Brown',
 'King size bed with storage drawers underneath. Modern design with padded headboard.',
 'Need storage compartments on both sides.',
 32000.00, 12800.00, 32000.00, 'approved', '2026-03-06 14:20:00'),

(10, 'ORD-2026-00103', 'Wardrobe', 'Three Door Wardrobe',
 200.00, 60.00, 220.00, 'Pine Wood', 'White',
 '3-door wardrobe with mirror on the center door. Internal shelves and hanging rod.',
 'Mirror should be full-length on center door.',
 28000.00, 11200.00, 28000.00, 'in_production', '2026-03-05 09:15:00'),

(10, 'ORD-2026-00104', 'Table', 'Dining Table for 6',
 180.00, 90.00, 75.00, 'Teak Wood', 'Brown',
 'Rectangular dining table that can seat 6 people comfortably. Classic design with carved legs.',
 'Table should be sturdy and durable.',
 22000.00, 8800.00, 22000.00, 'completed', '2026-03-01 11:45:00'),

(10, 'ORD-2026-00105', 'Cabinet', 'Kitchen Storage Cabinet',
 100.00, 45.00, 180.00, 'Plywood', 'White',
 'Tall kitchen cabinet with multiple shelves and one drawer at bottom.',
 'Need adjustable shelves inside.',
 15000.00, 6000.00, 15000.00, 'pending_approval', '2026-03-07 16:00:00')

ON DUPLICATE KEY UPDATE furniture_type = VALUES(furniture_type);

-- Update existing orders to have status if NULL
UPDATE `furn_orders` SET `status` = 'pending_approval' WHERE `status` IS NULL OR `status` = '';

COMMIT;

SELECT 'Custom Furniture Orders Schema Applied!' as Status;
SELECT COUNT(*) as Sample_Orders FROM furn_orders WHERE customer_id = 10 AND furniture_type IS NOT NULL;

