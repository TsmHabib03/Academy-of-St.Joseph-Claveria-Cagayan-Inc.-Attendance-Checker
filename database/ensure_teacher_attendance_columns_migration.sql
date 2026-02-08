-- Idempotent SQL migration for MySQL 8+
-- Usage: mysql -u root -p your_database < ensure_teacher_attendance_columns_migration.sql

-- Add `employee_number` to `teachers` if missing
ALTER TABLE `teachers` ADD COLUMN IF NOT EXISTS `employee_number` CHAR(7) NULL;

-- Ensure teacher_attendance columns exist
ALTER TABLE `teacher_attendance`
  ADD COLUMN IF NOT EXISTS `date` DATE NOT NULL,
  ADD COLUMN IF NOT EXISTS `morning_time_in` TIME NULL,
  ADD COLUMN IF NOT EXISTS `morning_time_out` TIME NULL,
  ADD COLUMN IF NOT EXISTS `afternoon_time_in` TIME NULL,
  ADD COLUMN IF NOT EXISTS `afternoon_time_out` TIME NULL,
  ADD COLUMN IF NOT EXISTS `is_late_morning` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `is_late_afternoon` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `status` ENUM('present','absent','late','half_day','on_leave') NOT NULL DEFAULT 'present',
  ADD COLUMN IF NOT EXISTS `remarks` TEXT NULL,
  ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS `department` VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS `shift` VARCHAR(16) NULL,
  ADD COLUMN IF NOT EXISTS `employee_number` CHAR(7) NULL;

-- Create indexes if missing using a small stored procedure wrapper
DELIMITER $$
CREATE PROCEDURE ensure_teacher_indexes()
BEGIN
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_attendance' AND INDEX_NAME = 'idx_employee_number') = 0 THEN
    ALTER TABLE `teacher_attendance` ADD INDEX `idx_employee_number` (`employee_number`);
  END IF;

  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers' AND INDEX_NAME = 'idx_teachers_employee_number') = 0 THEN
    ALTER TABLE `teachers` ADD INDEX `idx_teachers_employee_number` (`employee_number`);
  END IF;
END$$

CALL ensure_teacher_indexes();
DROP PROCEDURE IF EXISTS ensure_teacher_indexes;
DELIMITER ;

-- Notes:
-- - This script relies on MySQL 8+ support for "ADD COLUMN IF NOT EXISTS".
-- - Run with appropriate user privileges. Backup the database before running.
