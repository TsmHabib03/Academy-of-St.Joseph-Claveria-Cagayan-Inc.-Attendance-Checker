-- Migration: Add schedule/time columns to `sections` table (idempotent)
-- Adds: session, am_start_time, am_late_threshold, am_end_time, pm_start_time, pm_late_threshold, pm_end_time
-- Safe to re-run.

SET @tbl = 'sections';

-- session (varchar)
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'session');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `session` VARCHAR(16) DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- am_start_time
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'am_start_time');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `am_start_time` TIME DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- am_late_threshold
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'am_late_threshold');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `am_late_threshold` TIME DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- am_end_time
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'am_end_time');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `am_end_time` TIME DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pm_start_time
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'pm_start_time');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `pm_start_time` TIME DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pm_late_threshold
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'pm_late_threshold');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `pm_late_threshold` TIME DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pm_end_time
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'pm_end_time');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `pm_end_time` TIME DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- schedule_id (reference to attendance_schedules)
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'schedule_id');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `schedule_id` INT DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- uses_custom_schedule flag
SET @cnt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'uses_custom_schedule');
SET @sql = IF(@cnt = 0, CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `uses_custom_schedule` TINYINT(1) NOT NULL DEFAULT 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optionally add indexes on start times if missing
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND INDEX_NAME = 'idx_sections_am_start');
SET @sql = IF(@idx = 0, CONCAT('CREATE INDEX idx_sections_am_start ON `', @tbl, '` (am_start_time)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND INDEX_NAME = 'idx_sections_pm_start');
SET @sql = IF(@idx = 0, CONCAT('CREATE INDEX idx_sections_pm_start ON `', @tbl, '` (pm_start_time)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'migration_add_section_schedule_columns.sql - completed' AS info;
