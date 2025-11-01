-- Add school_year column to sections table if it doesn't exist
ALTER TABLE `sections` 
ADD COLUMN IF NOT EXISTS `school_year` VARCHAR(20) NULL AFTER `grade_level`,
ADD COLUMN IF NOT EXISTS `adviser` VARCHAR(100) NULL AFTER `school_year`,
ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'inactive') DEFAULT 'active' AFTER `adviser`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `status`,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Show the updated table structure
DESCRIBE `sections`;
