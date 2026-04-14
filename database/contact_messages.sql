-- Contact Messages Database Table
-- Create this table in your MySQL database

CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('new', 'read', 'replied') DEFAULT 'new',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_contact_email` ON `contact_messages` (`email`);
CREATE INDEX IF NOT EXISTS `idx_contact_status` ON `contact_messages` (`status`);
CREATE INDEX IF NOT EXISTS `idx_contact_created` ON `contact_messages` (`created_at`);
