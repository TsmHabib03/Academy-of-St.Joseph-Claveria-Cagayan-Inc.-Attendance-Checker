-- Migration: Add V3 schema additions (safe, idempotent where supported)
-- Run on MySQL 8+ (backup database before running)

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE;

-- Create teachers table
CREATE TABLE IF NOT EXISTS `teachers` (
  `employee_id` VARCHAR(64) NOT NULL,
  `first_name` VARCHAR(128) NOT NULL,
  `middle_name` VARCHAR(128) DEFAULT NULL,
  `last_name` VARCHAR(128) NOT NULL,
  `department` VARCHAR(128) DEFAULT NULL,
  `position` VARCHAR(128) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create teacher_attendance table
CREATE TABLE IF NOT EXISTS `teacher_attendance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` VARCHAR(64) NOT NULL,
  `date` DATE NOT NULL,
  `morning_time_in` TIME DEFAULT NULL,
  `morning_time_out` TIME DEFAULT NULL,
  `afternoon_time_in` TIME DEFAULT NULL,
  `afternoon_time_out` TIME DEFAULT NULL,
  `status` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_teacher_date` (`employee_id`, `date`),
  CONSTRAINT `fk_teacher_attendance_teacher` FOREIGN KEY (`employee_id`) REFERENCES `teachers` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create attendance_schedules table
CREATE TABLE IF NOT EXISTS `attendance_schedules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grade_level` VARCHAR(64) DEFAULT NULL,
  `section` VARCHAR(64) DEFAULT NULL,
  `expected_time_in` TIME NOT NULL DEFAULT '07:30:00',
  `expected_time_out` TIME DEFAULT NULL,
  `late_threshold_minutes` INT DEFAULT 15,
  `session` ENUM('AM','PM','BOTH') DEFAULT 'BOTH',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grade_section` (`grade_level`, `section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add V3 columns to attendance (idempotent if server supports IF NOT EXISTS)
ALTER TABLE `attendance`
  ADD COLUMN IF NOT EXISTS `morning_time_in` TIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `morning_time_out` TIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `afternoon_time_in` TIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `afternoon_time_out` TIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `period_number` TINYINT UNSIGNED DEFAULT NULL,
  ADD INDEX IF NOT EXISTS `idx_attendance_date_lrn` (`date`, `lrn`);

-- Optional: ensure students and attendance have needed indexes
ALTER TABLE `students`
  ADD INDEX IF NOT EXISTS `idx_students_section` (`section`);

-- Restore checks
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET SQL_MODE=@OLD_SQL_MODE;

-- Notes:
-- - This script creates `teachers`, `teacher_attendance`, and `attendance_schedules`.
-- - It adds four time columns for morning/afternoon attendance on the existing `attendance` table.
-- - If your MySQL version does not support `ADD COLUMN IF NOT EXISTS` or `ADD INDEX IF NOT EXISTS`, run the ALTERs manually after checking `INFORMATION_SCHEMA.COLUMNS`/`STATISTICS`.
-- - Backup your DB before running and run on MySQL 8+.
