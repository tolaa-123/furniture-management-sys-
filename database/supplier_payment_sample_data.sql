-- ═══════════════════════════════════════════════════════════════
-- SUPPLIER PAYMENT SYSTEM - SAMPLE DATA
-- ═══════════════════════════════════════════════════════════════
-- This file contains sample/test data for the supplier payment system
-- Use this to test the system functionality
-- ═══════════════════════════════════════════════════════════════

-- IMPORTANT: Replace user_id values with actual manager/admin IDs from your system
-- Get manager ID: SELECT id FROM furn_users WHERE user_role='manager' LIMIT 1;

-- ═══════════════════════════════════════════════════════════════
-- SAMPLE SUPPLIER INVOICES
-- ═══════════════════════════════════════════════════════════════

-- Invoice 1: ABC Wood Supplies (Fully Paid)
INSERT INTO furn_supplier_invoices 
(supplier_name, invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_due, status, payment_terms, description, notes, created_by, created_at)
VALUES 
('ABC Wood Supplies', 'INV-2024-001', '2024-04-01', '2024-05-01', 5000.00, 5000.00, 0.00, 'paid', 'Net 30', 'Oak and Pine Wood Purchase', 'Delivered on time, good quality', 1, '2024-04-01 10:30:00');

-- Invoice 2: XYZ Hardware Store (Partially Paid)
INSERT INTO furn_supplier_invoices 
(supplier_name, invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_due, status, payment_terms, description, notes, created_by, created_at)
VALUES 
('XYZ Hardware Store', 'INV-2024-002', '2024-04-10', '2024-05-10', 3500.00, 1500.00, 2000.00, 'approved', 'Net 30', 'Nails, Screws, and Tools', 'Partial payment made', 1, '2024-04-10 14:20:00');

-- Invoice 3: Premium Fabrics Ltd (Pending - Due Soon)
INSERT INTO furn_supplier_invoices 
(supplier_name, invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_due, status, payment_terms, description, notes, created_by, created_at)
VALUES 
('Premium Fabrics Ltd', 'INV-2024-003', '2024-04-20', '2024-05-05', 8500.00, 0.00, 8500.00, 'pending', 'Net 15', 'Upholstery Fabric - Various Colors', 'High quality fabric for sofas', 1, '2024-04-20 09:15:00');

-- Invoice 4: Steel & Metal Works (Overdue)
INSERT INTO furn_supplier_invoices 
(supplier_name, invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_due, status, payment_terms, description, notes, created_by, created_at)
VALUES 
('Steel & Metal Works', 'INV-2024-004', '2024-03-15', '2024-04-15', 12000.00, 0.00, 12000.00, 'overdue', 'Net 30', 'Metal Frames and Hinges', 'URGENT: Payment overdue', 1, '2024-03-15 11:45:00');

-- Invoice 5: Green Forest Timber (Pending)
INSERT INTO furn_supplier_invoices 
(supplier_name, invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_due, status, payment_terms, description, notes, created_by, created_at)
VALUES 
('Green Forest Timber', 'INV-2024-005', '2024-04-25', '2024-05-25', 15000.00, 0.00, 15000.00, 'pending', 'Net 30', 'Mahogany and Teak Wood', 'Premium quality timber', 1, '2024-04-25 08:30:00');

-- Invoice 6: Quality Paints Co (Partially Paid)
INSERT INTO furn_supplier_invoices 
(supplier_name, invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_due, status, payment_terms, description, notes, created_by, created_at)
VALUES 
('Quality Paints Co', 'INV-2024-006', '2024-04-18', '2024-05-18', 4200.00, 2000.00, 2200.00, 'approved', 'Net 30', 'Wood Stains and Varnish', 'First installment paid', 1, '2024-04-18 13:00:00');

-- ═══════════════════════════════════════════════════════════════
-- SAMPLE INVOICE LINE ITEMS
-- ═══════════════════════════════════════════════════════════════

-- Line items for Invoice 1 (ABC Wood Supplies)
INSERT INTO furn_supplier_invoice_items 
(invoice_id, material_name, quantity, unit, unit_price, total_price)
VALUES 
(1, 'Oak Wood', 100.00, 'kg', 35.00, 3500.00),
(1, 'Pine Wood', 50.00, 'kg', 30.00, 1500.00);

-- Line items for Invoice 2 (XYZ Hardware Store)
INSERT INTO furn_supplier_invoice_items 
(invoice_id, material_name, quantity, unit, unit_price, total_price)
VALUES 
(2, 'Steel Nails (3 inch)', 20.00, 'kg', 45.00, 900.00),
(2, 'Wood Screws (Assorted)', 15.00, 'kg', 60.00, 900.00),
(2, 'Hammer (Heavy Duty)', 5.00, 'pcs', 180.00, 900.00),
(2, 'Drill Bits Set', 4.00, 'set', 200.00, 800.00);

-- Line items for Invoice 3 (Premium Fabrics Ltd)
INSERT INTO furn_supplier_invoice_items 
(invoice_id, material_name, quantity, unit, unit_price, total_price)
VALUES 
(3, 'Leather Fabric (Brown)', 30.00, 'm', 150.00, 4500.00),
(3, 'Velvet Fabric (Blue)', 20.00, 'm', 120.00, 2400.00),
(3, 'Cotton Fabric (Beige)', 25.00, 'm', 64.00, 1600.00);

-- Line items for Invoice 4 (Steel & Metal Works)
INSERT INTO furn_supplier_invoice_items 
(invoice_id, material_name, quantity, unit, unit_price, total_price)
VALUES 
(4, 'Steel Frame (Chair)', 50.00, 'pcs', 180.00, 9000.00),
(4, 'Metal Hinges (Heavy Duty)', 100.00, 'pcs', 25.00, 2500.00),
(4, 'Steel Brackets', 50.00, 'pcs', 10.00, 500.00);

-- Line items for Invoice 5 (Green Forest Timber)
INSERT INTO furn_supplier_invoice_items 
(invoice_id, material_name, quantity, unit, unit_price, total_price)
VALUES 
(5, 'Mahogany Wood (Premium)', 80.00, 'kg', 120.00, 9600.00),
(5, 'Teak Wood (Grade A)', 60.00, 'kg', 90.00, 5400.00);

-- Line items for Invoice 6 (Quality Paints Co)
INSERT INTO furn_supplier_invoice_items 
(invoice_id, material_name, quantity, unit, unit_price, total_price)
VALUES 
(6, 'Wood Stain (Dark Oak)', 15.00, 'liter', 120.00, 1800.00),
(6, 'Varnish (Glossy)', 20.00, 'liter', 80.00, 1600.00),
(6, 'Paint Brushes (Professional)', 10.00, 'set', 80.00, 800.00);

-- ═══════════════════════════════════════════════════════════════
-- SAMPLE PAYMENTS
-- ═══════════════════════════════════════════════════════════════

-- Payment 1: Full payment for Invoice 1 (ABC Wood Supplies)
INSERT INTO furn_supplier_payments 
(invoice_id, supplier_name, payment_date, amount, payment_method, reference_number, bank_name, account_number, notes, paid_by, status, created_at)
VALUES 
(1, 'ABC Wood Supplies', '2024-04-15', 5000.00, 'bank_transfer', 'TXN-2024-001', 'Commercial Bank of Ethiopia', '1000123456', 'Full payment via bank transfer', 1, 'verified', '2024-04-15 10:00:00');

-- Payment 2: Partial payment for Invoice 2 (XYZ Hardware Store)
INSERT INTO furn_supplier_payments 
(invoice_id, supplier_name, payment_date, amount, payment_method, reference_number, notes, paid_by, status, created_at)
VALUES 
(2, 'XYZ Hardware Store', '2024-04-20', 1500.00, 'cash', 'CASH-001', 'First installment - cash payment', 1, 'verified', '2024-04-20 14:30:00');

-- Payment 3: Partial payment for Invoice 6 (Quality Paints Co)
INSERT INTO furn_supplier_payments 
(invoice_id, supplier_name, payment_date, amount, payment_method, reference_number, bank_name, notes, paid_by, status, created_at)
VALUES 
(6, 'Quality Paints Co', '2024-04-22', 2000.00, 'check', 'CHK-12345', 'Awash Bank', 'Payment by check #12345', 1, 'verified', '2024-04-22 11:15:00');

-- ═══════════════════════════════════════════════════════════════
-- SUMMARY OF SAMPLE DATA
-- ═══════════════════════════════════════════════════════════════

/*
INVOICES CREATED: 6
- INV-2024-001: ABC Wood Supplies (5,000 ETB) - PAID ✓
- INV-2024-002: XYZ Hardware Store (3,500 ETB) - PARTIAL (1,500 paid, 2,000 due)
- INV-2024-003: Premium Fabrics Ltd (8,500 ETB) - PENDING (due soon)
- INV-2024-004: Steel & Metal Works (12,000 ETB) - OVERDUE ⚠
- INV-2024-005: Green Forest Timber (15,000 ETB) - PENDING
- INV-2024-006: Quality Paints Co (4,200 ETB) - PARTIAL (2,000 paid, 2,200 due)

TOTAL INVOICED: 48,200 ETB
TOTAL PAID: 8,500 ETB
TOTAL OUTSTANDING: 39,700 ETB

PAYMENTS RECORDED: 3
- Payment 1: 5,000 ETB (Bank Transfer)
- Payment 2: 1,500 ETB (Cash)
- Payment 3: 2,000 ETB (Check)

DASHBOARD METRICS:
- Total Outstanding: 39,700 ETB
- Overdue Invoices: 1 (Steel & Metal Works)
- Due This Week: 8,500 ETB (Premium Fabrics Ltd)
- Paid This Month: 8,500 ETB
*/

-- ═══════════════════════════════════════════════════════════════
-- HOW TO USE THIS FILE
-- ═══════════════════════════════════════════════════════════════

/*
STEP 1: Update User IDs
- Replace 'created_by' and 'paid_by' values (currently 1) with actual manager ID
- Get manager ID: SELECT id FROM furn_users WHERE user_role='manager' LIMIT 1;

STEP 2: Run This File
- Method 1: phpMyAdmin → SQL tab → paste and execute
- Method 2: MySQL command line: mysql -u username -p database_name < supplier_payment_sample_data.sql

STEP 3: Verify Data
- Login as manager
- Go to Supplier Payments dashboard
- You should see:
  * 6 invoices in the system
  * 1 overdue invoice (red badge)
  * 3 payments in recent payments
  * Summary cards with totals

STEP 4: Test Functionality
- Try recording a payment for Invoice 2 (remaining 2,000 ETB)
- Try creating a new invoice
- Check that totals update correctly
*/

-- ═══════════════════════════════════════════════════════════════
-- CLEANUP (Optional - Run this to remove sample data)
-- ═══════════════════════════════════════════════════════════════

/*
-- Uncomment these lines to delete all sample data:

DELETE FROM furn_supplier_payments WHERE invoice_id IN (1,2,3,4,5,6);
DELETE FROM furn_supplier_invoice_items WHERE invoice_id IN (1,2,3,4,5,6);
DELETE FROM furn_supplier_invoices WHERE id IN (1,2,3,4,5,6);

-- Reset auto-increment (optional):
ALTER TABLE furn_supplier_invoices AUTO_INCREMENT = 1;
ALTER TABLE furn_supplier_invoice_items AUTO_INCREMENT = 1;
ALTER TABLE furn_supplier_payments AUTO_INCREMENT = 1;
*/

-- ═══════════════════════════════════════════════════════════════
-- END OF SAMPLE DATA
-- ═══════════════════════════════════════════════════════════════
