-- Idempotent migration: add `shift` to `teachers` and add `department`, `shift` to `teacher_attendance`
-- Uses MySQL 8.0+ syntax `ADD COLUMN IF NOT EXISTS` for safe, idempotent migration.
-- Run on a test database first and backup production before applying.

ALTER TABLE `teachers`
  ADD COLUMN IF NOT EXISTS `shift` VARCHAR(16) DEFAULT 'morning' COMMENT 'Teacher shift: morning|afternoon|both';

ALTER TABLE `teacher_attendance`
  ADD COLUMN IF NOT EXISTS `department` VARCHAR(128) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `shift` VARCHAR(16) DEFAULT NULL;

-- Note: If your MySQL is older than 8.0 and does not support "IF NOT EXISTS" on ADD COLUMN,
-- run these checks manually or upgrade MySQL. Example manual check (run in client):
-- SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='teachers' AND COLUMN_NAME='shift';
-- If zero, run: ALTER TABLE `teachers` ADD COLUMN `shift` VARCHAR(16) DEFAULT 'morning';

SELECT 'migration_add_teacher_columns_complete' AS status;
