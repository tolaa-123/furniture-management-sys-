-- =====================================================
-- Payroll Table Schema
-- FurnitureCraft Workshop ERP System
-- =====================================================
-- This script creates the payroll table for managing
-- employee salary payments, bonuses, and deductions
-- =====================================================

-- Create payroll table
CREATE TABLE IF NOT EXISTS furn_payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month INT NOT NULL COMMENT 'Month number (1-12)',
    year INT NOT NULL COMMENT 'Year (e.g., 2026)',
    base_salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Base monthly salary in ETB',
    bonus DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Additional bonus amount in ETB',
    deductions DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Deductions (tax, insurance, etc.) in ETB',
    net_salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Final amount paid (base + bonus - deductions)',
    payment_date DATE NOT NULL COMMENT 'Date when payment was made',
    status VARCHAR(50) DEFAULT 'paid' COMMENT 'Payment status: paid, pending, cancelled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for better query performance
    INDEX idx_employee (employee_id),
    INDEX idx_date (payment_date),
    INDEX idx_month_year (month, year),
    INDEX idx_status (status)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Employee payroll records with salary, bonus, and deductions';

-- =====================================================
-- Verification Query
-- =====================================================
-- Run this to verify the table was created successfully:
-- SELECT COUNT(*) as payroll_records FROM furn_payroll;

-- =====================================================
-- Sample Insert (Optional)
-- =====================================================
-- Uncomment to insert a sample payroll record:
/*
INSERT INTO furn_payroll 
(employee_id, month, year, base_salary, bonus, deductions, net_salary, payment_date, status) 
VALUES 
(1, 3, 2026, 15000.00, 2000.00, 1500.00, 15500.00, '2026-03-08', 'paid');
*/

-- =====================================================
-- Useful Queries
-- =====================================================

-- View all payroll records with employee names:
/*
SELECT 
    p.id,
    u.full_name as employee_name,
    p.month,
    p.year,
    p.base_salary,
    p.bonus,
    p.deductions,
    p.net_salary,
    p.payment_date,
    p.status
FROM furn_payroll p
LEFT JOIN furn_users u ON p.employee_id = u.id
ORDER BY p.payment_date DESC;
*/

-- Monthly payroll summary:
/*
SELECT 
    month,
    year,
    COUNT(*) as employees_paid,
    SUM(base_salary) as total_base,
    SUM(bonus) as total_bonus,
    SUM(deductions) as total_deductions,
    SUM(net_salary) as total_paid
FROM furn_payroll
GROUP BY year, month
ORDER BY year DESC, month DESC;
*/
