-- Add Comprehensive Product-Material Mappings for All Furniture Types
-- This script adds material requirements for Sofa, Chair, Bed, Table, Desk, and Shelf

USE furniture_erp;

-- First, let's ensure we have all the furniture categories
INSERT IGNORE INTO `furn_categories` (`name`, `description`) VALUES
('Desk', 'Office and study desks'),
('Shelf', 'Storage shelves and bookcases');

-- Add more sample products for each furniture type
INSERT IGNORE INTO `furn_products` (`category_id`, `name`, `description`, `base_price`, `estimated_production_time`, `materials_used`) VALUES
-- Additional Sofas
((SELECT id FROM furn_categories WHERE name='Sofa' LIMIT 1), 'Modern Sectional Sofa', 'Contemporary L-shaped sectional sofa with chaise', 22000.00, 25, 'Fabric upholstery, plywood frame, foam cushions'),
((SELECT id FROM furn_categories WHERE name='Sofa' LIMIT 1), 'Loveseat Sofa', 'Compact 2-seater sofa for small spaces', 9500.00, 15, 'Leather, solid wood frame, foam padding'),

-- Additional Chairs
((SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), 'Dining Chair Set', 'Set of 4 wooden dining chairs with cushions', 6000.00, 12, 'Oak wood, fabric upholstery, foam'),
((SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), 'Recliner Chair', 'Comfortable reclining armchair', 7500.00, 14, 'Leather, steel mechanism, foam, wood frame'),

-- Additional Beds
((SELECT id FROM furn_categories WHERE name='Bed' LIMIT 1), 'Queen Size Platform Bed', 'Modern platform bed with storage', 18000.00, 22, 'Plywood, wood slats, metal hardware'),
((SELECT id FROM furn_categories WHERE name='Bed' LIMIT 1), 'Bunk Bed', 'Twin over twin bunk bed', 16000.00, 20, 'Solid pine wood, metal brackets, safety rails'),

-- Additional Tables
((SELECT id FROM furn_categories WHERE name='Table' LIMIT 1), 'Coffee Table', 'Modern rectangular coffee table', 4500.00, 10, 'Oak wood, glass top, metal legs'),
((SELECT id FROM furn_categories WHERE name='Table' LIMIT 1), 'Computer Desk', 'Home office computer desk with drawers', 7500.00, 15, 'MDF wood, metal hardware, laminate'),

-- Desks
((SELECT id FROM furn_categories WHERE name='Desk' LIMIT 1), 'Executive Desk', 'Large executive office desk with hutch', 15000.00, 25, 'Solid oak wood, leather pad, metal hardware'),
((SELECT id FROM furn_categories WHERE name='Desk' LIMIT 1), 'Standing Desk', 'Adjustable height standing desk', 12000.00, 18, 'Steel frame, wood top, electronic motor'),

-- Shelves
((SELECT id FROM furn_categories WHERE name='Shelf' LIMIT 1), 'Bookshelf 5-Tier', 'Tall 5-tier open bookshelf', 5500.00, 12, 'Plywood, wood veneer, shelf pins'),
((SELECT id FROM furn_categories WHERE name='Shelf' LIMIT 1), 'Wall-Mounted Shelf', 'Floating wall shelf set', 3000.00, 8, 'Solid wood, metal brackets, wall anchors');

-- Now add comprehensive product-material mappings
-- Clear existing mappings first (optional - comment out if you want to keep existing)
-- DELETE FROM furn_product_materials;

-- Get product IDs for mapping
-- Note: Adjust product IDs based on your actual database

-- SOFA MATERIALS
-- Premium Leather Sofa (Product ID: 1)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(1, 1, 25.00),  -- Premium Leather: 25 sq ft
(1, 2, 15.00),  -- Oak Wood: 15 board ft
(1, 4, 6.00),   -- Foam Padding: 6 pieces
(1, 5, 10.00),  -- Fabric Upholstery: 10 yards (for backing)
(1, 7, 50.00);  -- Stainless Steel Hardware: 50 pieces

-- Modern Sectional Sofa (Product ID: 5)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(5, 5, 30.00),  -- Fabric Upholstery: 30 yards
(5, 8, 20.00),  -- Plywood Sheets: 20 sheets
(5, 4, 12.00),  -- Foam Padding: 12 pieces
(5, 7, 80.00),  -- Hardware: 80 pieces
(5, 9, 8.00);   -- Wood Glue: 8 liters

-- Loveseat Sofa (Product ID: 6)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(6, 1, 15.00),  -- Premium Leather: 15 sq ft
(6, 2, 10.00),  -- Oak Wood: 10 board ft
(6, 4, 4.00),   -- Foam Padding: 4 pieces
(6, 7, 40.00);  -- Hardware: 40 pieces

-- CHAIR MATERIALS
-- Executive Office Chair (Product ID: 4)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(4, 5, 3.00),   -- Fabric Upholstery: 3 yards
(4, 2, 8.00),   -- Oak Wood: 8 board ft
(4, 4, 2.00),   -- Foam Padding: 2 pieces
(4, 3, 1.00),   -- Steel Frame: 1 piece
(4, 7, 30.00);  -- Hardware: 30 pieces

-- Dining Chair Set (Product ID: 7)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(7, 2, 20.00),  -- Oak Wood: 20 board ft (for 4 chairs)
(7, 5, 8.00),   -- Fabric Upholstery: 8 yards
(7, 4, 4.00),   -- Foam Padding: 4 pieces
(7, 7, 60.00),  -- Hardware: 60 pieces
(7, 9, 2.00);   -- Wood Glue: 2 liters

-- Recliner Chair (Product ID: 8)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(8, 1, 18.00),  -- Premium Leather: 18 sq ft
(8, 2, 12.00),  -- Oak Wood: 12 board ft
(8, 4, 5.00),   -- Foam Padding: 5 pieces
(8, 3, 1.00),   -- Steel Frame: 1 piece
(8, 10, 1.00);  -- Recliner Mechanism: 1 piece

-- BED MATERIALS
-- King Size Wooden Bed (Product ID: 2)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(2, 2, 30.00),  -- Oak Wood: 30 board ft
(2, 3, 1.00),   -- Steel Frame: 1 piece
(2, 7, 40.00),  -- Hardware: 40 pieces
(2, 11, 12.00), -- Wood Slats: 12 pieces
(2, 9, 3.00);   -- Wood Glue: 3 liters

-- Queen Size Platform Bed (Product ID: 9)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(9, 8, 15.00),  -- Plywood Sheets: 15 sheets
(9, 11, 10.00), -- Wood Slats: 10 pieces
(9, 7, 50.00),  -- Hardware: 50 pieces
(9, 2, 10.00);  -- Oak Wood: 10 board ft

-- Bunk Bed (Product ID: 10)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(10, 12, 40.00), -- Pine Wood: 40 board ft
(10, 7, 80.00),  -- Hardware: 80 pieces
(10, 13, 2.00),  -- Safety Rails: 2 pieces
(10, 11, 20.00); -- Wood Slats: 20 pieces

-- TABLE MATERIALS
-- Modern Dining Table (Product ID: 3)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(3, 2, 12.00),  -- Oak Wood: 12 board ft
(3, 6, 1.00),   -- Glass Tabletop: 1 piece
(3, 7, 20.00),  -- Hardware: 20 pieces
(3, 9, 2.00);   -- Wood Glue: 2 liters

-- Coffee Table (Product ID: 11)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(11, 2, 8.00),   -- Oak Wood: 8 board ft
(11, 6, 1.00),   -- Glass Tabletop: 1 piece
(11, 14, 4.00),  -- Metal Legs: 4 pieces
(11, 7, 15.00);  -- Hardware: 15 pieces

-- Computer Desk (Product ID: 12)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(12, 15, 10.00), -- MDF Boards: 10 sheets
(12, 7, 40.00),  -- Hardware: 40 pieces
(12, 16, 8.00),  -- Laminate Sheets: 8 sheets
(12, 9, 2.00);   -- Wood Glue: 2 liters

-- DESK MATERIALS
-- Executive Desk (Product ID: 13)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(13, 2, 35.00),  -- Oak Wood: 35 board ft
(13, 1, 8.00),   -- Premium Leather: 8 sq ft (for desk pad)
(13, 7, 60.00),  -- Hardware: 60 pieces
(13, 9, 4.00);   -- Wood Glue: 4 liters

-- Standing Desk (Product ID: 14)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(14, 3, 2.00),   -- Steel Frame: 2 pieces
(14, 2, 15.00),  -- Oak Wood: 15 board ft
(14, 17, 1.00),  -- Electronic Motor: 1 piece
(14, 7, 30.00);  -- Hardware: 30 pieces

-- SHELF MATERIALS
-- Bookshelf 5-Tier (Product ID: 15)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(15, 8, 8.00),    -- Plywood Sheets: 8 sheets
(15, 18, 20.00),  -- Wood Veneer: 20 sq ft
(15, 19, 20.00),  -- Shelf Pins: 20 pieces
(15, 7, 40.00);   -- Hardware: 40 pieces

-- Wall-Mounted Shelf (Product ID: 16)
INSERT IGNORE INTO `furn_product_materials` (`product_id`, `material_id`, `quantity_required`) VALUES
(16, 2, 6.00),    -- Oak Wood: 6 board ft
(16, 20, 4.00),   -- Metal Brackets: 4 pieces
(16, 7, 15.00);   -- Hardware: 15 pieces

-- Verify the additions
SELECT 
    c.name as Furniture_Type,
    COUNT(DISTINCT p.id) as Product_Count,
    COUNT(pm.id) as Material_Mappings
FROM furn_categories c
LEFT JOIN furn_products p ON c.id = p.category_id AND p.is_active = 1
LEFT JOIN furn_product_materials pm ON p.id = pm.product_id
WHERE c.name IN ('Sofa', 'Chair', 'Bed', 'Table', 'Desk', 'Shelf')
GROUP BY c.name
ORDER BY Material_Mappings DESC;

SELECT 'Product-Material mappings added successfully!' as Status;
