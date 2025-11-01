-- ============================================
-- Manage Sections - Database Schema Fix
-- ============================================
-- Run these commands if you're missing columns
-- or getting "undefined array key" warnings
-- ============================================

-- 1. Check current table structure
DESCRIBE sections;

-- 2. Add missing 'status' column if it doesn't exist
-- (Skip if column already exists)
ALTER TABLE sections 
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active' 
AFTER school_year;

-- 3. Add missing 'school_year' column if it doesn't exist
-- (Skip if column already exists)
ALTER TABLE sections 
ADD COLUMN IF NOT EXISTS school_year VARCHAR(20) DEFAULT NULL 
AFTER adviser;

-- 4. Add missing 'adviser' column if it doesn't exist
-- (Skip if column already exists)
ALTER TABLE sections 
ADD COLUMN IF NOT EXISTS adviser VARCHAR(100) DEFAULT NULL 
AFTER grade_level;

-- 5. Update existing NULL values to defaults
UPDATE sections 
SET status = 'active' 
WHERE status IS NULL;

UPDATE sections 
SET school_year = '2024-2025' 
WHERE school_year IS NULL OR school_year = '';

-- 6. Verify the fixes
SELECT 
    id,
    section_name,
    grade_level,
    adviser,
    school_year,
    status,
    (SELECT COUNT(*) FROM students WHERE class = sections.section_name) as student_count
FROM sections
ORDER BY section_name;

-- ============================================
-- Complete Table Structure (Reference)
-- ============================================
-- If you need to recreate the table completely:

/*
CREATE TABLE sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(100) NOT NULL UNIQUE,
    grade_level VARCHAR(10) DEFAULT NULL,
    adviser VARCHAR(100) DEFAULT NULL,
    school_year VARCHAR(20) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_section_name (section_name),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

-- ============================================
-- Verification Queries
-- ============================================

-- Check for sections with NULL status
SELECT id, section_name, status 
FROM sections 
WHERE status IS NULL;

-- Check for sections with NULL school_year
SELECT id, section_name, school_year 
FROM sections 
WHERE school_year IS NULL;

-- Check for sections with NULL adviser
SELECT id, section_name, adviser 
FROM sections 
WHERE adviser IS NULL;

-- Count active vs inactive sections
SELECT 
    status,
    COUNT(*) as count
FROM sections
GROUP BY status;

-- ============================================
-- Data Cleanup (Optional)
-- ============================================

-- Set default school year for all sections
UPDATE sections 
SET school_year = '2024-2025' 
WHERE school_year IS NULL OR school_year = '';

-- Activate all sections by default
UPDATE sections 
SET status = 'active' 
WHERE status IS NULL;

-- ============================================
-- End of SQL Script
-- ============================================
