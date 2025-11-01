-- Add section column to students table
ALTER TABLE `students` 
ADD COLUMN IF NOT EXISTS `section` VARCHAR(100) NULL AFTER `class`;

-- Show the updated table structure
DESCRIBE `students`;

-- Optional: Update existing records to extract section from class field if they follow pattern like "12-BARBERRA"
-- UPDATE students SET section = SUBSTRING_INDEX(class, '-', -1) WHERE class LIKE '%-%' AND (section IS NULL OR section = '');
