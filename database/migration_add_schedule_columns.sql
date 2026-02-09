-- Some MySQL versions do not support `ADD COLUMN IF NOT EXISTS`.
-- Use a small helper procedure to add columns only when missing (idempotent).

DELIMITER //

DROP PROCEDURE IF EXISTS AddColumnIfNotExists//
CREATE PROCEDURE AddColumnIfNotExists(
    IN p_table VARCHAR(64),
    IN p_col VARCHAR(64),
    IN p_def VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_col, '` ', p_def);
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//

DROP PROCEDURE IF EXISTS AddIndexIfNotExists//
CREATE PROCEDURE AddIndexIfNotExists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_cols VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_cols, ')');
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//

DELIMITER ;

-- Add AM/PM columns for `sections` (idempotent)
CALL AddColumnIfNotExists('sections','am_start_time','TIME DEFAULT \'07:30:00\'');
CALL AddColumnIfNotExists('sections','am_late_threshold','TIME DEFAULT \'08:00:00\'');
CALL AddColumnIfNotExists('sections','am_end_time','TIME DEFAULT \'12:00:00\'');
CALL AddColumnIfNotExists('sections','pm_start_time','TIME DEFAULT \'13:00:00\'');
CALL AddColumnIfNotExists('sections','pm_late_threshold','TIME DEFAULT \'13:30:00\'');
CALL AddColumnIfNotExists('sections','pm_end_time','TIME DEFAULT \'17:00:00\'');

-- Optionally add an index on section schedule columns
CALL AddIndexIfNotExists('sections','idx_sections_schedule','`am_start_time`,`pm_start_time`');

-- Cleanup helpers if you prefer
DELIMITER //
DROP PROCEDURE IF EXISTS AddColumnIfNotExists//
DROP PROCEDURE IF EXISTS AddIndexIfNotExists//
DELIMITER ;