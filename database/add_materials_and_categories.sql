-- Add New Material Categories and Materials for Furniture Production
-- This migration adds missing categories and materials for SOFA, CHAIR, TABLE, BED, DESK, SHELF production

USE furniture_erp;

-- Step 1: Add missing material categories
INSERT IGNORE INTO `furn_material_categories` (`name`, `description`) VALUES
('Adhesives', 'Wood glue, contact cement, epoxy for furniture assembly'),
('Finishing Tools', 'Sandpaper, brushes, rags for applying finishes');

-- Step 2: Categorize existing materials that are uncategorized
UPDATE `furn_materials` SET `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name`='Wood' LIMIT 1) WHERE `name`='Oak Wood' AND `category_id` IS NULL;
UPDATE `furn_materials` SET `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name`='Upholstery' LIMIT 1) WHERE `name`='Premium Leather' AND `category_id` IS NULL;
UPDATE `furn_materials` SET `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name`='Hardware' LIMIT 1) WHERE `name`='Steel Frame' AND `category_id` IS NULL;
UPDATE `furn_materials` SET `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name`='Foam' LIMIT 1) WHERE `name`='Foam Padding' AND `category_id` IS NULL;
UPDATE `furn_materials` SET `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name`='Upholstery' LIMIT 1) WHERE `name`='Fabric Upholstery' AND `category_id` IS NULL;
UPDATE `furn_materials` SET `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name`='Glass' LIMIT 1) WHERE `name`='Glass Tabletop' AND `category_id` IS NULL;
UPDATE `furn_materials` SET `category_id` = (SELECT id FROM `furn_material_categories` WHERE `name`='Hardware' LIMIT 1) WHERE `name`='Stainless Steel Hardware' AND `category_id` IS NULL;

-- Step 3: Add new Wood materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Pine Wood', 'Softwood for affordable furniture frames', 'board_feet', 300.00, 50.00, 45.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Mahogany Wood', 'Premium hardwood for luxury furniture', 'board_feet', 100.00, 20.00, 180.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Plywood', 'Layered wood board for shelves and backs', 'sheets', 150.00, 30.00, 250.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('MDF Board', 'Medium-density fiberboard for smooth surfaces', 'sheets', 100.00, 20.00, 200.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1));

-- Step 4: Add new Hardware materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Wood Screws', 'Various sizes for furniture assembly', 'box', 200.00, 50.00, 150.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Nails', 'Various sizes for wood joining', 'box', 200.00, 50.00, 80.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Corner Brackets', 'Metal L-brackets for frame support', 'pcs', 500.00, 100.00, 25.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Drawer Handles', 'Wooden/metal handles for drawers', 'pcs', 300.00, 50.00, 45.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Drawer Slides', 'Metal slides for smooth drawer operation', 'pcs', 200.00, 40.00, 120.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Wood Dowels', 'Wooden pegs for joint connections', 'box', 150.00, 30.00, 60.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Shelf Pins', 'Support pins for adjustable shelves', 'pcs', 500.00, 100.00, 8.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Seat Springs', 'Coil springs for sofa/chair seats', 'pcs', 200.00, 40.00, 35.00, 'Metal Works Ltd', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Bolts', 'Metal bolts for bed frame assembly', 'box', 150.00, 30.00, 180.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Metal Brackets', 'Heavy-duty support brackets', 'pcs', 300.00, 60.00, 45.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1));

-- Step 5: Add Adhesives
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Wood Glue PVA', 'Polyvinyl acetate wood adhesive', 'L', 50.00, 10.00, 250.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Adhesives' LIMIT 1)),
('Contact Cement', 'Strong adhesive for laminates and fabric', 'L', 30.00, 5.00, 450.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Adhesives' LIMIT 1)),
('Epoxy Resin', 'Two-part adhesive for strong bonds', 'pcs', 20.00, 5.00, 600.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Adhesives' LIMIT 1));

-- Step 6: Add Finishing materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Wood Varnish', 'Clear protective wood finish', 'L', 40.00, 10.00, 350.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Wood Stain', 'Color stain for wood finishing', 'L', 30.00, 8.00, 400.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Lacquer', 'High-gloss protective coating', 'L', 25.00, 5.00, 500.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Polyurethane', 'Durable clear finish', 'L', 30.00, 8.00, 380.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Sandpaper Assorted', 'Grits 80, 120, 220 for smoothing', 'box', 100.00, 20.00, 150.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Wood Filler', 'Putty for filling gaps and holes', 'pcs', 50.00, 10.00, 120.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Edge Banding', 'PVC/veneer edge trim for particle board', 'pcs', 30.00, 5.00, 280.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1));

-- Step 7: Add Finishing Tools
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Paint Brushes', 'Brushes for applying finishes', 'pcs', 80.00, 15.00, 85.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing Tools' LIMIT 1)),
('Cleaning Rags', 'Cloths for cleaning and applying finishes', 'pcs', 200.00, 40.00, 25.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing Tools' LIMIT 1));

-- Step 8: Add additional Upholstery materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Sewing Thread', 'Heavy-duty thread for upholstery', 'pcs', 100.00, 20.00, 95.00, 'Textile Solutions', (SELECT id FROM furn_material_categories WHERE name='Upholstery' LIMIT 1)),
('Cotton Fabric', 'Cotton upholstery fabric', 'yards', 200.00, 40.00, 65.00, 'Textile Solutions', (SELECT id FROM furn_material_categories WHERE name='Upholstery' LIMIT 1)),
('Polyester Fabric', 'Durable polyester upholstery fabric', 'yards', 250.00, 50.00, 55.00, 'Textile Solutions', (SELECT id FROM furn_material_categories WHERE name='Upholstery' LIMIT 1));

-- Step 9: Add Wood Legs and Supports
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Wood Legs', 'Pre-made furniture legs for sofa/chair', 'pcs', 200.00, 40.00, 120.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Wood Slats', 'Bed mattress support slats', 'pcs', 300.00, 60.00, 35.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Back Panel', 'Plywood/MDF panel for shelf/desk back', 'sheets', 80.00, 15.00, 180.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1));

-- Verify the additions
SELECT COUNT(*) as Total_Materials FROM furn_materials;
SELECT c.name as Category, COUNT(m.id) as Material_Count 
FROM furn_material_categories c 
LEFT JOIN furn_materials m ON c.id = m.category_id 
GROUP BY c.name 
ORDER BY Material_Count DESC;
