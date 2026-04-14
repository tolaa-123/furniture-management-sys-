-- Products table already created in schema.sql, only insert sample data below

-- Insert sample products
INSERT IGNORE INTO furn_products (name, category_id, description, base_price) VALUES
('Modern Office Chair', (SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), 'Ergonomic office chair with lumbar support', 3500.00),
('Dining Table Set', (SELECT id FROM furn_categories WHERE name='Table' LIMIT 1), '6-seater wooden dining table', 15000.00),
('L-Shaped Sofa', (SELECT id FROM furn_categories WHERE name='Sofa' LIMIT 1), 'Comfortable 5-seater L-shaped sofa', 25000.00),
('King Size Bed', (SELECT id FROM furn_categories WHERE name='Bed' LIMIT 1), 'Solid wood king size bed frame', 18000.00),
('Kitchen Cabinet', (SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), 'Modular kitchen storage cabinet', 12000.00),
('Executive Desk', (SELECT id FROM furn_categories WHERE name='Table' LIMIT 1), 'Large executive office desk with drawers', 8500.00),
('Sliding Wardrobe', (SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), '3-door sliding wardrobe with mirror', 22000.00),
('Wall Bookshelf', (SELECT id FROM furn_categories WHERE name='Chair' LIMIT 1), '5-tier wall-mounted bookshelf', 6500.00);
