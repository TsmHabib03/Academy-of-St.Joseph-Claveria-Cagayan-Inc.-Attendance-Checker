-- ============================================
-- Fix Section Unique Constraint Issue
-- ============================================
-- This script resolves the duplicate section creation problem
-- by ensuring proper unique constraints and indexes
-- ============================================

-- Step 1: Show current table structure
DESCRIBE sections;

-- Step 2: Check for duplicate sections
SELECT section_name, COUNT(*) as count
FROM sections
GROUP BY section_name
HAVING count > 1;

-- Step 3: Remove old UNIQUE constraint if it exists
-- Note: Replace 'unique_section' with actual constraint name from SHOW CREATE TABLE
ALTER TABLE sections DROP INDEX IF EXISTS unique_section;

-- Step 4: Remove old UNIQUE constraint on section_name if it exists
ALTER TABLE sections DROP INDEX IF EXISTS section_name;

-- Step 5: Add new UNIQUE constraint on section_name only (case-insensitive)
-- This prevents duplicate sections like "12-BARBERRA" and "12-barberra"
ALTER TABLE sections 
ADD UNIQUE KEY unique_section_name (section_name);

-- Step 6: Add index for better query performance
ALTER TABLE sections 
ADD INDEX IF NOT EXISTS idx_section_name_lower ((LOWER(section_name)));

-- Step 7: Verify the changes
SHOW CREATE TABLE sections;

-- Step 8: Check for any remaining duplicates
SELECT 
    section_name,
    grade_level,
    school_year,
    COUNT(*) as count
FROM sections
GROUP BY LOWER(section_name)
HAVING count > 1;

-- ============================================
-- Optional: Cleanup Duplicates (if any exist)
-- ============================================
-- If you have duplicates, run this to keep only the first entry:

/*
DELETE s1 FROM sections s1
INNER JOIN sections s2 
WHERE s1.id > s2.id 
AND LOWER(s1.section_name) = LOWER(s2.section_name);
*/

-- ============================================
-- Verification Query
-- ============================================
-- Run this to see all sections with student counts
SELECT 
    s.id,
    s.section_name,
    s.grade_level,
    s.adviser,
    s.school_year,
    s.status,
    (SELECT COUNT(*) FROM students WHERE section = s.section_name OR class = s.section_name) as student_count
FROM sections s
ORDER BY s.section_name;
