-- Add Ethiopian Furniture Materials to Database
-- This script adds all materials needed for Ethiopian furniture production

USE furniture_erp;

-- Ensure we have the right categories
INSERT IGNORE INTO `furn_material_categories` (`name`, `description`) VALUES
('Wood', 'Various types of wood materials for furniture construction'),
('Upholstery', 'Fabric, leather, and cushioning materials'),
('Hardware', 'Metal components, fittings, and accessories'),
('Adhesives', 'Wood glue, contact cement, epoxy for furniture assembly'),
('Finishing', 'Stains, paints, varnish, and protective coatings'),
('Foam', 'Padding and cushioning materials'),
('Tools', 'Sandpaper and finishing tools');

-- Add Ethiopian Wood Types
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Hardwood Frame (Tid)', 'African Pencil Cedar - Most common, strong, aromatic', 'board_feet', 500.00, 100.00, 95.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Hardwood Frame (Zigba)', 'African Juniper - Dense, used for quality furniture', 'board_feet', 300.00, 60.00, 120.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Hardwood Frame (Weira)', 'Olive wood - Hard, beautiful grain', 'board_feet', 200.00, 40.00, 150.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Kerero Wood', 'Lightweight wood, used for frames', 'board_feet', 250.00, 50.00, 60.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Bisana Wood', 'Used for beds and heavy furniture', 'board_feet', 180.00, 35.00, 110.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Imported Pine', 'Cheaper, widely used now', 'board_feet', 400.00, 80.00, 50.00, 'Imported Timber Co.', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1));

-- Add Upholstery Materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Fabric Upholstery', 'Premium fabric for seating surfaces - ጨርቅ (Cherq)', 'yards', 500.00, 100.00, 85.00, 'Textile Solutions', (SELECT id FROM furn_material_categories WHERE name='Upholstery' LIMIT 1)),
('Leather Upholstery', 'High-quality leather - ቆዳ (Qoda)', 'square_feet', 300.00, 60.00, 180.00, 'Ethiopian Leather Co.', (SELECT id FROM furn_material_categories WHERE name='Upholstery' LIMIT 1)),
('Jute Webbing', 'Jute fabric for sofa base - ጁት ጨርቅ (Jut Cherq)', 'yards', 200.00, 40.00, 45.00, 'Textile Solutions', (SELECT id FROM furn_material_categories WHERE name='Upholstery' LIMIT 1));

-- Add Foam Materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Foam Padding', 'High-density foam for cushioning - ፎም (Foam)', 'pieces', 200.00, 40.00, 120.00, 'Comfort Materials Inc', (SELECT id FROM furn_material_categories WHERE name='Foam' LIMIT 1)),
('Mattress Foam', 'Special foam for beds - የፎም ፍራሽ (Ye-Foam Firash)', 'pieces', 100.00, 20.00, 250.00, 'Comfort Materials Inc', (SELECT id FROM furn_material_categories WHERE name='Foam' LIMIT 1));

-- Add Spring & Support Materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Spring Coils', 'Coil springs for sofa/chair seats - ስፕሪንግ (Spring)', 'pieces', 300.00, 60.00, 45.00, 'Metal Works Ltd', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Wood Legs', 'Pre-made furniture legs for sofa/chair - እግር (Igir)', 'pcs', 200.00, 40.00, 150.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Metal Legs', 'Metal furniture legs - የብረት እግር (Ye-Biret Igir)', 'pieces', 150.00, 30.00, 200.00, 'Metal Works Ltd', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Wood Slats', 'Bed mattress support slats - የአልጋ ድጋፍ', 'pcs', 400.00, 80.00, 45.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1));

-- Add Adhesives
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Wood Glue PVA', 'Polyvinyl acetate wood adhesive - ማጣበቂያ (Matabequiya)', 'L', 80.00, 15.00, 280.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Adhesives' LIMIT 1)),
('Contact Cement', 'Strong adhesive for laminates and fabric', 'L', 50.00, 10.00, 500.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Adhesives' LIMIT 1));

-- Add Finishing Materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Wood Varnish', 'Clear protective wood finish - ቀለም (Qelem)', 'L', 60.00, 12.00, 380.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Lacquer', 'High-gloss protective coating - ቀለም / ላሚኔት', 'L', 40.00, 8.00, 550.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Wood Stain', 'Color stain for wood finishing', 'L', 50.00, 10.00, 450.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Edge Banding', 'Edge covering for plywood - የጠርዝ ሽፋን (Ye-Terz Shifan)', 'rolls', 100.00, 20.00, 120.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Finishing' LIMIT 1)),
('Sandpaper', 'Sandpaper for wood finishing - ሳንድፔፐር (Sandpaper)', 'pack', 150.00, 30.00, 35.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Tools' LIMIT 1));

-- Add Hardware & Fasteners
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Wood Screws', 'Various sizes for furniture assembly - ምስማር (Mismar)', 'box', 300.00, 60.00, 180.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Bolts', 'Metal bolts for bed frame assembly - ምስማር / ቦልት', 'box', 200.00, 40.00, 220.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Nails & Staples', 'Various sizes for wood joining - ምስማር (Mismar)', 'box', 250.00, 50.00, 120.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Drawer Slides', 'Metal slides for smooth drawer operation - የሳጥን መንሸራተቻ', 'pairs', 150.00, 30.00, 180.00, 'Metal Works Ltd', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Hinges', 'Metal hinges for doors - ማጠፊያ ብረት (Matefefiya Biret)', 'pcs', 400.00, 80.00, 35.00, 'Metal Works Ltd', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Shelf Pins', 'Support pins for adjustable shelves - የመደርደሪያ ምስማር', 'pcs', 600.00, 120.00, 12.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Metal Brackets', 'Heavy-duty support brackets - የግድግዳ መያዣ', 'pcs', 350.00, 70.00, 55.00, 'Metal Works Ltd', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Wall Anchors', 'Anchors for wall-mounted shelves - የግድግዳ መያዣ', 'pcs', 300.00, 60.00, 25.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1)),
('Cable Grommets', 'Cable hole covers for desks - የሽቦ ቀዳዳ ክዳን', 'pcs', 200.00, 40.00, 15.00, 'Hardware Distributors', (SELECT id FROM furn_material_categories WHERE name='Hardware' LIMIT 1));

-- Add Board Materials
INSERT IGNORE INTO `furn_materials` (`name`, `description`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `supplier`, `category_id`) VALUES
('Plywood', 'Layered wood board for shelves and backs - ፕላይዉድ (Plywood)', 'sheets', 200.00, 40.00, 350.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('MDF Board', 'Medium-density fiberboard for smooth surfaces - MDF', 'sheets', 150.00, 30.00, 280.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1)),
('Back Panel', 'Thin plywood for shelf/desk back - የኋላ ሽፋን (Ye-Hwala Shifan)', 'sheets', 100.00, 20.00, 200.00, 'Addis Ababa Timber', (SELECT id FROM furn_material_categories WHERE name='Wood' LIMIT 1));

-- Verify additions
SELECT 
    c.name as Category,
    COUNT(m.id) as Material_Count,
    SUM(m.current_stock) as Total_Stock
FROM furn_material_categories c
LEFT JOIN furn_materials m ON c.id = m.category_id
GROUP BY c.name
ORDER BY Material_Count DESC;

SELECT 'Ethiopian furniture materials added successfully!' as Status;
