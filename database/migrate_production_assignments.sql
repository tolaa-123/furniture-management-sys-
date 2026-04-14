-- Migration: Add missing columns to furn_production_assignments
-- These columns are expected by the production.php view

USE `furniture_erp`;

-- Add progress column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'furn_production_assignments';
SET @columnname = 'progress';

SET @sql = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = @dbname 
        AND table_name = @tablename 
        AND column_name = @columnname
    ),
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT(11) NOT NULL DEFAULT 0'),
    'SELECT "Column progress already exists" as Message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add deadline column if it doesn't exist (used by production.php)
SET @columnname = 'deadline';

SET @sql = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = @dbname 
        AND table_name = @tablename 
        AND column_name = @columnname
    ),
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DATE NULL DEFAULT NULL'),
    'SELECT "Column deadline already exists" as Message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add updated_at column if it doesn't exist
SET @columnname = 'updated_at';

SET @sql = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = @dbname 
        AND table_name = @tablename 
        AND column_name = @columnname
    ),
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
    'SELECT "Column updated_at already exists" as Message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show current table structure
DESCRIBE furn_production_assignments;

SELECT 'Migration completed: furn_production_assignments table updated' as Status;
