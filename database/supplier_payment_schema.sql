-- ═══════════════════════════════════════════════════════════════
-- SUPPLIER PAYMENT SYSTEM - DATABASE SCHEMA
-- ═══════════════════════════════════════════════════════════════

-- 1. Supplier Invoices Table
CREATE TABLE IF NOT EXISTS furn_supplier_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT DEFAULT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    invoice_number VARCHAR(100) NOT NULL UNIQUE,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_due DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'approved', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_terms VARCHAR(50) DEFAULT 'Net 30',
    description TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(supplier_id),
    INDEX(invoice_number),
    INDEX(status),
    INDEX(due_date),
    INDEX(created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Supplier Invoice Items Table
CREATE TABLE IF NOT EXISTS furn_supplier_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    material_id INT DEFAULT NULL,
    material_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    purchase_id INT DEFAULT NULL COMMENT 'Link to furn_material_purchases',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(invoice_id),
    INDEX(material_id),
    INDEX(purchase_id),
    FOREIGN KEY (invoice_id) REFERENCES furn_supplier_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Supplier Payments Table
CREATE TABLE IF NOT EXISTS furn_supplier_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    supplier_id INT DEFAULT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'check', 'mobile_money', 'other') NOT NULL DEFAULT 'bank_transfer',
    reference_number VARCHAR(100) DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    paid_by INT NOT NULL,
    verified_by INT DEFAULT NULL,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('pending', 'verified', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(invoice_id),
    INDEX(supplier_id),
    INDEX(payment_date),
    INDEX(paid_by),
    INDEX(status),
    FOREIGN KEY (invoice_id) REFERENCES furn_supplier_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Update furn_material_purchases to link with invoices (if table exists)
-- Check if columns exist before adding
SET @dbname = DATABASE();
SET @tablename = 'furn_material_purchases';
SET @columnname1 = 'invoice_id';
SET @columnname2 = 'payment_status';

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename) > 0,
  CONCAT('ALTER TABLE ', @tablename, ' 
    ADD COLUMN IF NOT EXISTS ', @columnname1, ' INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS ', @columnname2, ' ENUM(\'unpaid\', \'partial\', \'paid\') DEFAULT \'unpaid\',
    ADD INDEX IF NOT EXISTS idx_invoice_id (invoice_id)'),
  'SELECT 1'
));

-- Note: MySQL 8.0 doesn't support IF NOT EXISTS in ALTER TABLE ADD COLUMN
-- We'll use a safer approach - ignore errors if columns exist
ALTER TABLE furn_material_purchases 
ADD COLUMN invoice_id INT DEFAULT NULL AFTER supplier;

ALTER TABLE furn_material_purchases 
ADD COLUMN payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid' AFTER invoice_id;

ALTER TABLE furn_material_purchases 
ADD INDEX idx_invoice_id (invoice_id);

-- 5. Update furn_suppliers table to track balances (if table exists)
ALTER TABLE furn_suppliers
ADD COLUMN current_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER contact_phone;

ALTER TABLE furn_suppliers
ADD COLUMN total_purchases DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER current_balance;

ALTER TABLE furn_suppliers
ADD COLUMN total_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_purchases;

ALTER TABLE furn_suppliers
ADD COLUMN last_payment_date DATE DEFAULT NULL AFTER total_paid;

ALTER TABLE furn_suppliers
ADD COLUMN payment_terms VARCHAR(50) DEFAULT 'Net 30' AFTER last_payment_date;

ALTER TABLE furn_suppliers
ADD COLUMN credit_limit DECIMAL(12,2) DEFAULT NULL AFTER payment_terms;

-- 6. Create view for accounts payable summary
CREATE OR REPLACE VIEW vw_accounts_payable_summary AS
SELECT 
    si.id,
    si.supplier_name,
    si.invoice_number,
    si.invoice_date,
    si.due_date,
    si.total_amount,
    si.paid_amount,
    si.balance_due,
    si.status,
    DATEDIFF(CURDATE(), si.due_date) as days_overdue,
    CASE 
        WHEN si.status = 'paid' THEN 'Paid'
        WHEN DATEDIFF(CURDATE(), si.due_date) > 0 THEN 'Overdue'
        WHEN DATEDIFF(si.due_date, CURDATE()) <= 7 THEN 'Due Soon'
        ELSE 'Current'
    END as aging_status
FROM furn_supplier_invoices si
WHERE si.status != 'cancelled';

-- 7. Create view for supplier payment history
CREATE OR REPLACE VIEW vw_supplier_payment_history AS
SELECT 
    sp.id,
    sp.supplier_name,
    si.invoice_number,
    sp.payment_date,
    sp.amount,
    sp.payment_method,
    sp.reference_number,
    sp.status,
    CONCAT(u.first_name, ' ', u.last_name) as paid_by_name
FROM furn_supplier_payments sp
JOIN furn_supplier_invoices si ON sp.invoice_id = si.id
LEFT JOIN furn_users u ON sp.paid_by = u.id
ORDER BY sp.payment_date DESC;

-- Insert sample data (optional - for testing)
-- INSERT INTO furn_supplier_invoices (supplier_name, invoice_number, invoice_date, due_date, total_amount, balance_due, created_by)
-- VALUES ('ABC Wood Supplies', 'INV-2024-001', '2024-04-01', '2024-05-01', 5000.00, 5000.00, 1);
