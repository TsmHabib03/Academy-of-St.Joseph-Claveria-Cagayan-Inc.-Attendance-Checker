-- ===================================================================
-- ACSCCI Attendance Checker - Database Fix
-- Adds both mobile_number and email columns to students table
-- Compatible with MySQL 5.7+ and MariaDB
-- Run this script in phpMyAdmin
-- ===================================================================
-- Step 1: Add mobile_number column (ignore error if exists)
ALTER TABLE `students`
ADD COLUMN `mobile_number` VARCHAR(15) NOT NULL DEFAULT '' COMMENT 'Parent mobile number for SMS notifications';
-- If you get "Duplicate column name" error, that's OK - column already exists!
-- Step 2: Make sure email column exists and is optional
-- If you need to add email column (ignore error if exists):
ALTER TABLE `students`
ADD COLUMN `email` VARCHAR(100) DEFAULT NULL COMMENT 'Parent/Guardian email (optional)';
-- Step 3: Verify columns exist
-- Run this to check:
-- DESCRIBE students;
-- ===================================================================
-- ALTERNATIVE: If the above gives errors, run each line one at a time
-- in phpMyAdmin SQL tab. Ignore "Duplicate column" errors.
-- ===================================================================