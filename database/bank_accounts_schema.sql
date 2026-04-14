-- Create table for storing bank accounts for payments
CREATE TABLE IF NOT EXISTS furn_bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_holder VARCHAR(100) NOT NULL,
    branch VARCHAR(100),
    swift_code VARCHAR(50),
    bank_address VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(100)
);

-- Insert sample bank accounts
INSERT INTO furn_bank_accounts (bank_name, account_number, account_holder, branch, swift_code, bank_address, phone, email) VALUES
('Commercial Bank of Ethiopia', '1000123456789', 'FurnitureCraft PLC', 'Addis Ababa Main', 'CBETETAA', '123 Main St, Addis Ababa', '+251-11-1234567', 'info@furniturecraft.com'),
('Dashen Bank', '2000234567890', 'FurnitureCraft PLC', 'Bole Branch', 'DASHETAA', '456 Bole Rd, Addis Ababa', '+251-11-7654321', 'payments@furniturecraft.com'),
('Awash Bank', '3000345678901', 'FurnitureCraft PLC', 'Piazza Branch', 'AWASHTAA', '789 Piazza, Addis Ababa', '+251-11-5555555', 'finance@furniturecraft.com');
