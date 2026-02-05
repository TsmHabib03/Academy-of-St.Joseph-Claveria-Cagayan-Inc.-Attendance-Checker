-- ===================================================================
-- ACSCCI Attendance Checker - Fix Database Schema (V3 Support)
-- COMPATIBLE VERSION (MySQL 5.7 / MariaDB)
-- Run this in phpMyAdmin
-- ===================================================================
-- 1. Create teachers table (IF NOT EXISTS is supported in standard MySQL)
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(20) NOT NULL COMMENT 'Unique Employee ID',
    `first_name` VARCHAR(50) NOT NULL,
    `middle_name` VARCHAR(50) DEFAULT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `sex` ENUM('Male', 'Female') NOT NULL DEFAULT 'Male',
    `mobile_number` VARCHAR(15) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_id` (`employee_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 2. Create teacher_attendance table
CREATE TABLE IF NOT EXISTS `teacher_attendance` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(20) NOT NULL,
    `date` DATE NOT NULL,
    `morning_time_in` TIME DEFAULT NULL,
    `morning_time_out` TIME DEFAULT NULL,
    `afternoon_time_in` TIME DEFAULT NULL,
    `afternoon_time_out` TIME DEFAULT NULL,
    `status` ENUM(
        'present',
        'absent',
        'late',
        'half_day',
        'on_leave'
    ) DEFAULT 'present',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_teacher_daily` (`employee_id`, `date`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 3. Add V3 columns to attendance table
-- NOTE: If you get "Duplicate column name" error, verify the column exists and ignore the error.
-- You can run these lines one by one.
ALTER TABLE `attendance`
ADD COLUMN `morning_time_in` TIME DEFAULT NULL;
ALTER TABLE `attendance`
ADD COLUMN `morning_time_out` TIME DEFAULT NULL;
ALTER TABLE `attendance`
ADD COLUMN `afternoon_time_in` TIME DEFAULT NULL;
ALTER TABLE `attendance`
ADD COLUMN `afternoon_time_out` TIME DEFAULT NULL;
-- 4. Add 'mobile_number' to students (if you missed the previous fix)
ALTER TABLE `students`
ADD COLUMN `mobile_number` VARCHAR(15) NOT NULL DEFAULT '';