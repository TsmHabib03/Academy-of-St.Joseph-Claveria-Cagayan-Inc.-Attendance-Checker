-- ============================================================================
-- AttendEase v3.0 Database Migration Script
-- Academy of St. Joseph Claveria, Cagayan Inc.
-- Migration Date: February 2026
-- ============================================================================
-- IMPORTANT: Run this migration AFTER backing up your database!
-- Execute this script in phpMyAdmin or MySQL CLI
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- ============================================================================
-- PHASE 1: CORE CHANGES - User Roles, Sex Field, Mobile Number
-- ============================================================================

-- 1.1 Update admin_users table - Add student role
-- Note: MySQL doesn't support ADD COLUMN IF NOT EXISTS, so we use procedures

DELIMITER //

-- Drop procedure if exists and recreate
DROP PROCEDURE IF EXISTS AddColumnIfNotExists//
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(64),
    IN columnName VARCHAR(64),
    IN columnDef VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = tableName 
        AND COLUMN_NAME = columnName
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

-- Procedure to check and rename column
DROP PROCEDURE IF EXISTS RenameColumnIfExists//
CREATE PROCEDURE RenameColumnIfExists(
    IN tableName VARCHAR(64),
    IN oldColumnName VARCHAR(64),
    IN newColumnName VARCHAR(64),
    IN columnDef VARCHAR(255)
)
BEGIN
    IF EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = tableName 
        AND COLUMN_NAME = oldColumnName
    ) AND NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = tableName 
        AND COLUMN_NAME = newColumnName
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` CHANGE COLUMN `', oldColumnName, '` `', newColumnName, '` ', columnDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

-- Procedure to drop index if exists
DROP PROCEDURE IF EXISTS DropIndexIfExists//
CREATE PROCEDURE DropIndexIfExists(
    IN tableName VARCHAR(64),
    IN indexName VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = tableName 
        AND INDEX_NAME = indexName
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` DROP INDEX `', indexName, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- 1.1a Modify role ENUM in admin_users
ALTER TABLE `admin_users`
    MODIFY COLUMN `role` ENUM('admin', 'teacher', 'staff', 'student') DEFAULT 'admin';

-- 1.1b Add reset_token columns if they don't exist
CALL AddColumnIfNotExists('admin_users', 'reset_token', 'VARCHAR(64) DEFAULT NULL');
CALL AddColumnIfNotExists('admin_users', 'reset_token_expires', 'DATETIME DEFAULT NULL');

-- 1.2 Rename 'gender' to 'sex' in students table (keeping same data)
CALL RenameColumnIfExists('students', 'gender', 'sex', "ENUM('Male', 'Female', 'M', 'F') NOT NULL DEFAULT 'Male' COMMENT 'Sex for SF2 reporting'");

-- 1.3 Replace email with mobile_number in students table
-- First drop the email index if it exists
CALL DropIndexIfExists('students', 'email');

-- Rename email to mobile_number
CALL RenameColumnIfExists('students', 'email', 'mobile_number', "VARCHAR(15) NOT NULL DEFAULT '' COMMENT 'Parent mobile number for SMS notifications'");

-- Add index for mobile_number if column exists
CALL AddColumnIfNotExists('students', 'mobile_number', "VARCHAR(15) NOT NULL DEFAULT '' COMMENT 'Parent mobile number for SMS'");

-- ============================================================================
-- PHASE 2: ATTENDANCE ENHANCEMENTS - Morning/Afternoon Sessions
-- ============================================================================

-- 2.1 Modify attendance table for AM/PM sessions
-- Rename time_in to morning_time_in
CALL RenameColumnIfExists('attendance', 'time_in', 'morning_time_in', 'TIME DEFAULT NULL');
CALL RenameColumnIfExists('attendance', 'time_out', 'morning_time_out', 'TIME DEFAULT NULL');

-- Add new columns for afternoon sessions and late flags
CALL AddColumnIfNotExists('attendance', 'afternoon_time_in', 'TIME DEFAULT NULL');
CALL AddColumnIfNotExists('attendance', 'afternoon_time_out', 'TIME DEFAULT NULL');
CALL AddColumnIfNotExists('attendance', 'is_late_morning', 'TINYINT(1) DEFAULT 0');
CALL AddColumnIfNotExists('attendance', 'is_late_afternoon', 'TINYINT(1) DEFAULT 0');

-- Update status enum (this will preserve existing data)
ALTER TABLE `attendance`
    MODIFY COLUMN `status` ENUM('present', 'absent', 'late', 'half_day', 'morning_only', 'afternoon_only', 'time_in', 'time_out') DEFAULT 'present';

-- ============================================================================
-- PHASE 2: TEACHER MANAGEMENT
-- ============================================================================

-- 2.2 Create teachers table
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(20) NOT NULL COMMENT 'Unique Employee ID',
    `first_name` VARCHAR(50) NOT NULL,
    `middle_name` VARCHAR(50) DEFAULT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `sex` ENUM('Male', 'Female') NOT NULL DEFAULT 'Male',
    `mobile_number` VARCHAR(15) DEFAULT NULL COMMENT 'Contact number',
    `email` VARCHAR(100) DEFAULT NULL COMMENT 'Email address for notifications',
    `department` VARCHAR(100) DEFAULT NULL COMMENT 'Department or subject area',
    `position` VARCHAR(100) DEFAULT NULL COMMENT 'Job position/title',
    `qr_code` VARCHAR(255) DEFAULT NULL COMMENT 'Path to QR code image',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_id` (`employee_id`),
    INDEX `idx_employee_id` (`employee_id`),
    INDEX `idx_department` (`department`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Teacher records for AttendEase v3.0';

-- Add email column to teachers table if it doesn't exist (for existing databases)
CALL AddColumnIfNotExists('teachers', 'email', "VARCHAR(100) DEFAULT NULL COMMENT 'Email address for notifications' AFTER mobile_number");

-- 2.3 Create teacher_attendance table
CREATE TABLE IF NOT EXISTS `teacher_attendance` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(20) NOT NULL COMMENT 'Teacher Employee ID',
    `date` DATE NOT NULL COMMENT 'Attendance date',
    `morning_time_in` TIME DEFAULT NULL COMMENT 'Morning Time In',
    `morning_time_out` TIME DEFAULT NULL COMMENT 'Morning Time Out',
    `afternoon_time_in` TIME DEFAULT NULL COMMENT 'Afternoon Time In',
    `afternoon_time_out` TIME DEFAULT NULL COMMENT 'Afternoon Time Out',
    `is_late_morning` TINYINT(1) DEFAULT 0,
    `is_late_afternoon` TINYINT(1) DEFAULT 0,
    `status` ENUM('present', 'absent', 'late', 'half_day', 'on_leave') DEFAULT 'present',
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_teacher_daily` (`employee_id`, `date`),
    INDEX `idx_date` (`date`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_teacher_attendance` FOREIGN KEY (`employee_id`) 
        REFERENCES `teachers` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Teacher attendance records';

-- ============================================================================
-- PHASE 2: LATE DETECTION SYSTEM
-- ============================================================================

-- 2.4 Create attendance schedules table
CREATE TABLE IF NOT EXISTS `attendance_schedules` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `schedule_name` VARCHAR(100) NOT NULL DEFAULT 'Default Schedule',
    `grade_level` VARCHAR(50) DEFAULT NULL COMMENT 'Specific grade level or NULL for all',
    `section_id` INT DEFAULT NULL COMMENT 'Specific section or NULL for all',
    `morning_start` TIME NOT NULL DEFAULT '07:00:00',
    `morning_end` TIME NOT NULL DEFAULT '12:00:00',
    `morning_late_after` TIME NOT NULL DEFAULT '07:30:00' COMMENT 'Time after which student is marked late',
    `afternoon_start` TIME NOT NULL DEFAULT '13:00:00',
    `afternoon_end` TIME NOT NULL DEFAULT '17:00:00',
    `afternoon_late_after` TIME NOT NULL DEFAULT '13:30:00' COMMENT 'Time after which student is marked late',
    `is_default` TINYINT(1) DEFAULT 0 COMMENT 'Default schedule if no specific match',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_grade` (`grade_level`),
    INDEX `idx_section` (`section_id`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Attendance schedule rules for late detection';

-- Insert default schedule
INSERT INTO `attendance_schedules` 
(`schedule_name`, `grade_level`, `section_id`, `morning_start`, `morning_end`, `morning_late_after`, `afternoon_start`, `afternoon_end`, `afternoon_late_after`, `is_default`) 
VALUES
('Default Schedule', NULL, NULL, '07:00:00', '12:00:00', '07:30:00', '13:00:00', '17:00:00', '13:30:00', 1)
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- ============================================================================
-- PHASE 3: BEHAVIOR MONITORING
-- ============================================================================

-- 3.1 Create behavior alerts table
CREATE TABLE IF NOT EXISTS `behavior_alerts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_type` ENUM('student', 'teacher') NOT NULL,
    `user_id` VARCHAR(20) NOT NULL COMMENT 'LRN for students, employee_id for teachers',
    `alert_type` ENUM('frequent_late', 'consecutive_absence', 'sudden_absence', 'attendance_drop', 'perfect_streak') NOT NULL,
    `alert_message` TEXT,
    `occurrences` INT DEFAULT 1,
    `period_start` DATE DEFAULT NULL,
    `period_end` DATE DEFAULT NULL,
    `date_detected` DATE NOT NULL,
    `severity` ENUM('info', 'warning', 'critical') DEFAULT 'warning',
    `is_acknowledged` TINYINT(1) DEFAULT 0,
    `acknowledged_by` INT DEFAULT NULL,
    `acknowledged_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_type`, `user_id`),
    INDEX `idx_type` (`alert_type`),
    INDEX `idx_acknowledged` (`is_acknowledged`),
    INDEX `idx_date` (`date_detected`),
    INDEX `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Behavior monitoring alerts';

-- Add notes column to behavior_alerts table if it doesn't exist (for existing databases)
CALL AddColumnIfNotExists('behavior_alerts', 'notes', "TEXT DEFAULT NULL COMMENT 'Admin notes when acknowledging alert' AFTER acknowledged_at");

-- ============================================================================
-- PHASE 3: ATTENDANCE BADGES
-- ============================================================================

-- 3.2 Create badges table
CREATE TABLE IF NOT EXISTS `badges` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `badge_name` VARCHAR(100) NOT NULL,
    `badge_description` TEXT,
    `badge_icon` VARCHAR(100) DEFAULT 'fa-award' COMMENT 'FontAwesome icon class',
    `badge_color` VARCHAR(20) DEFAULT '#4CAF50' COMMENT 'Badge color hex',
    `criteria_type` ENUM('perfect_attendance', 'on_time_streak', 'most_improved', 'monthly_perfect', 'early_bird', 'consistent') NOT NULL,
    `criteria_value` INT DEFAULT NULL COMMENT 'Numeric criteria (e.g., streak days)',
    `criteria_period` ENUM('daily', 'weekly', 'monthly', 'yearly') DEFAULT 'monthly',
    `applicable_roles` SET('student', 'teacher') NOT NULL DEFAULT 'student,teacher',
    `points` INT DEFAULT 10 COMMENT 'Points awarded for this badge',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_criteria` (`criteria_type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Achievement badges for attendance';

-- Insert default badges
INSERT INTO `badges` (`badge_name`, `badge_description`, `badge_icon`, `badge_color`, `criteria_type`, `criteria_value`, `criteria_period`, `applicable_roles`, `points`) VALUES
('Perfect Attendance', 'No absences for the entire month', 'fa-star', '#FFD700', 'perfect_attendance', NULL, 'monthly', 'student,teacher', 50),
('On-Time Champ', '20 consecutive on-time arrivals', 'fa-clock', '#4CAF50', 'on_time_streak', 20, 'daily', 'student,teacher', 30),
('Most Improved', 'Significant attendance improvement over previous month', 'fa-chart-line', '#2196F3', 'most_improved', 20, 'monthly', 'student,teacher', 40),
('Early Bird', 'Arrived early for 10 consecutive days', 'fa-sun', '#FF9800', 'early_bird', 10, 'daily', 'student,teacher', 25),
('Consistent Achiever', '100% attendance for the week', 'fa-trophy', '#9C27B0', 'consistent', 5, 'weekly', 'student,teacher', 20)
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- 3.3 Create user_badges table
CREATE TABLE IF NOT EXISTS `user_badges` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_type` ENUM('student', 'teacher') NOT NULL,
    `user_id` VARCHAR(20) NOT NULL COMMENT 'LRN for students, employee_id for teachers',
    `badge_id` INT NOT NULL,
    `date_earned` DATE NOT NULL,
    `period_start` DATE DEFAULT NULL,
    `period_end` DATE DEFAULT NULL,
    `is_displayed` TINYINT(1) DEFAULT 1 COMMENT 'Show on profile',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_type`, `user_id`),
    INDEX `idx_badge` (`badge_id`),
    INDEX `idx_date` (`date_earned`),
    CONSTRAINT `fk_user_badge` FOREIGN KEY (`badge_id`) 
        REFERENCES `badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Badges earned by users';

-- ============================================================================
-- PHASE 4: SMS NOTIFICATIONS
-- ============================================================================

-- 4.1 Create SMS logs table
CREATE TABLE IF NOT EXISTS `sms_logs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `recipient_type` ENUM('student', 'teacher', 'parent') NOT NULL,
    `recipient_id` VARCHAR(20) NOT NULL,
    `mobile_number` VARCHAR(15) NOT NULL,
    `message_type` ENUM('late', 'absent', 'time_in', 'time_out', 'alert', 'custom') NOT NULL,
    `message_content` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'failed', 'queued') DEFAULT 'pending',
    `provider_response` TEXT DEFAULT NULL COMMENT 'SMS gateway response',
    `message_id` VARCHAR(100) DEFAULT NULL COMMENT 'Provider message ID',
    `cost` DECIMAL(10,4) DEFAULT NULL COMMENT 'SMS cost if applicable',
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_recipient` (`recipient_type`, `recipient_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`message_type`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='SMS notification logs';

-- 4.2 Create SMS templates table
CREATE TABLE IF NOT EXISTS `sms_templates` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `template_name` VARCHAR(50) NOT NULL,
    `template_type` ENUM('late', 'absent', 'time_in', 'time_out', 'alert') NOT NULL,
    `message_template` TEXT NOT NULL COMMENT 'Use placeholders: {name}, {date}, {time}, {status}',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_template` (`template_name`),
    INDEX `idx_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='SMS message templates';

-- Insert default SMS templates
INSERT INTO `sms_templates` (`template_name`, `template_type`, `message_template`) VALUES
('student_late', 'late', 'LATE NOTICE: {name} arrived late at {time} on {date}. - ASJ Attendance System'),
('student_absent', 'absent', 'ABSENT NOTICE: {name} was marked absent on {date}. Please contact the school if this is incorrect. - ASJ Attendance System'),
('student_time_in', 'time_in', '{name} has arrived at school at {time} on {date}. - ASJ Attendance System'),
('student_time_out', 'time_out', '{name} has left school at {time} on {date}. - ASJ Attendance System'),
('behavior_alert', 'alert', 'ATTENTION: {name} has been flagged for {status}. Please contact the guidance office. - ASJ Attendance System')
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- ============================================================================
-- PHASE 4: SYSTEM SETTINGS
-- ============================================================================

-- 4.3 Create system settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `setting_type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `description` TEXT,
    `is_editable` TINYINT(1) DEFAULT 1,
    `updated_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_setting` (`setting_key`),
    INDEX `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='System configuration settings';

-- Insert default settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`) VALUES
-- Session times
('morning_session_start', '06:00', 'string', 'attendance', 'Morning session start time'),
('morning_session_end', '12:00', 'string', 'attendance', 'Morning session end time'),
('afternoon_session_start', '12:00', 'string', 'attendance', 'Afternoon session start time'),
('afternoon_session_end', '18:00', 'string', 'attendance', 'Afternoon session end time'),
-- SMS settings
('sms_enabled', '0', 'boolean', 'notifications', 'Enable SMS notifications'),
('sms_provider', 'semaphore', 'string', 'notifications', 'SMS gateway provider'),
('sms_on_late', '1', 'boolean', 'notifications', 'Send SMS on late arrival'),
('sms_on_absent', '1', 'boolean', 'notifications', 'Send SMS on absence'),
-- Behavior monitoring
('behavior_monitoring_enabled', '1', 'boolean', 'monitoring', 'Enable behavior monitoring'),
('late_threshold_weekly', '3', 'number', 'monitoring', 'Late occurrences per week to trigger alert'),
('absence_threshold_consecutive', '2', 'number', 'monitoring', 'Consecutive absences to trigger alert'),
-- Badges
('badges_enabled', '1', 'boolean', 'badges', 'Enable badge system'),
('badge_notifications', '1', 'boolean', 'badges', 'Notify users when badges are earned'),
-- School info
('school_name', 'Academy of St. Joseph Claveria, Cagayan Inc.', 'string', 'school', 'School name'),
('school_year', '2025-2026', 'string', 'school', 'Current school year')
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- ============================================================================
-- UPDATE STORED PROCEDURES FOR v3.0
-- ============================================================================

-- Drop old procedures (legacy names)
DROP PROCEDURE IF EXISTS `MarkTimeIn`;
DROP PROCEDURE IF EXISTS `MarkTimeOut`;
DROP PROCEDURE IF EXISTS `RegisterStudent`;

-- Drop v3.0 procedures to allow re-creation (safe for re-running migration)
DROP PROCEDURE IF EXISTS `MarkAttendance_v3`;
DROP PROCEDURE IF EXISTS `RegisterStudent_v3`;
DROP PROCEDURE IF EXISTS `RegisterTeacher`;

DELIMITER $$

-- New MarkAttendance procedure with AM/PM session support
CREATE PROCEDURE `MarkAttendance_v3` (
    IN p_user_type ENUM('student', 'teacher'),
    IN p_user_id VARCHAR(20),
    IN p_date DATE,
    IN p_time TIME,
    IN p_action ENUM('time_in', 'time_out'),
    IN p_section_id INT
)
BEGIN
    DECLARE v_session VARCHAR(10);
    DECLARE v_is_late TINYINT DEFAULT 0;
    DECLARE v_late_after TIME;
    DECLARE v_morning_end TIME DEFAULT '12:00:00';
    
    -- Determine session based on time
    IF p_time < v_morning_end THEN
        SET v_session = 'morning';
    ELSE
        SET v_session = 'afternoon';
    END IF;
    
    -- Get schedule for late detection (check section-specific first, then default)
    IF v_session = 'morning' THEN
        SELECT morning_late_after INTO v_late_after
        FROM attendance_schedules 
        WHERE (section_id = p_section_id OR (section_id IS NULL AND is_default = 1))
            AND is_active = 1
        ORDER BY section_id DESC
        LIMIT 1;
    ELSE
        SELECT afternoon_late_after INTO v_late_after
        FROM attendance_schedules 
        WHERE (section_id = p_section_id OR (section_id IS NULL AND is_default = 1))
            AND is_active = 1
        ORDER BY section_id DESC
        LIMIT 1;
    END IF;
    
    -- Check if late (only for time_in)
    IF p_action = 'time_in' AND v_late_after IS NOT NULL THEN
        IF p_time > v_late_after THEN
            SET v_is_late = 1;
        END IF;
    END IF;
    
    -- Handle student attendance
    IF p_user_type = 'student' THEN
        IF v_session = 'morning' THEN
            IF p_action = 'time_in' THEN
                INSERT INTO attendance (lrn, date, morning_time_in, is_late_morning, status)
                VALUES (p_user_id, p_date, p_time, v_is_late, IF(v_is_late, 'late', 'present'))
                ON DUPLICATE KEY UPDATE 
                    morning_time_in = p_time,
                    is_late_morning = v_is_late,
                    status = IF(v_is_late, 'late', 'present'),
                    updated_at = CURRENT_TIMESTAMP;
            ELSE
                UPDATE attendance 
                SET morning_time_out = p_time, updated_at = CURRENT_TIMESTAMP
                WHERE lrn = p_user_id AND date = p_date;
            END IF;
        ELSE
            IF p_action = 'time_in' THEN
                INSERT INTO attendance (lrn, date, afternoon_time_in, is_late_afternoon, status)
                VALUES (p_user_id, p_date, p_time, v_is_late, IF(v_is_late, 'late', 'present'))
                ON DUPLICATE KEY UPDATE 
                    afternoon_time_in = p_time,
                    is_late_afternoon = v_is_late,
                    updated_at = CURRENT_TIMESTAMP;
            ELSE
                UPDATE attendance 
                SET afternoon_time_out = p_time, updated_at = CURRENT_TIMESTAMP
                WHERE lrn = p_user_id AND date = p_date;
            END IF;
        END IF;
    -- Handle teacher attendance
    ELSE
        IF v_session = 'morning' THEN
            IF p_action = 'time_in' THEN
                INSERT INTO teacher_attendance (employee_id, date, morning_time_in, is_late_morning, status)
                VALUES (p_user_id, p_date, p_time, v_is_late, IF(v_is_late, 'late', 'present'))
                ON DUPLICATE KEY UPDATE 
                    morning_time_in = p_time,
                    is_late_morning = v_is_late,
                    status = IF(v_is_late, 'late', 'present'),
                    updated_at = CURRENT_TIMESTAMP;
            ELSE
                UPDATE teacher_attendance 
                SET morning_time_out = p_time, updated_at = CURRENT_TIMESTAMP
                WHERE employee_id = p_user_id AND date = p_date;
            END IF;
        ELSE
            IF p_action = 'time_in' THEN
                INSERT INTO teacher_attendance (employee_id, date, afternoon_time_in, is_late_afternoon, status)
                VALUES (p_user_id, p_date, p_time, v_is_late, IF(v_is_late, 'late', 'present'))
                ON DUPLICATE KEY UPDATE 
                    afternoon_time_in = p_time,
                    is_late_afternoon = v_is_late,
                    updated_at = CURRENT_TIMESTAMP;
            ELSE
                UPDATE teacher_attendance 
                SET afternoon_time_out = p_time, updated_at = CURRENT_TIMESTAMP
                WHERE employee_id = p_user_id AND date = p_date;
            END IF;
        END IF;
    END IF;
    
    -- Return the late status
    SELECT v_is_late AS is_late, v_session AS session;
END$$

-- Register Student procedure (updated for v3.0)
CREATE PROCEDURE `RegisterStudent_v3` (
    IN p_lrn VARCHAR(13),
    IN p_first_name VARCHAR(50),
    IN p_middle_name VARCHAR(50),
    IN p_last_name VARCHAR(50),
    IN p_sex VARCHAR(10),
    IN p_mobile_number VARCHAR(15),
    IN p_class VARCHAR(50),
    IN p_section VARCHAR(50)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Validate LRN format (11-13 digits)
    IF p_lrn NOT REGEXP '^[0-9]{11,13}$' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Invalid LRN format. Must be 11-13 digits.';
    END IF;
    
    -- Validate mobile number format (Philippine format)
    IF p_mobile_number NOT REGEXP '^(09|\\+639)[0-9]{9}$' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Invalid mobile number format. Use 09XX-XXX-XXXX or +639XX-XXX-XXXX.';
    END IF;
    
    -- Insert student record
    INSERT INTO students (
        lrn, first_name, middle_name, last_name, 
        sex, mobile_number, class, section
    )
    VALUES (
        p_lrn, p_first_name, p_middle_name, p_last_name,
        p_sex, p_mobile_number, p_class, p_section
    );
    
    COMMIT;
END$$

-- Register Teacher procedure
CREATE PROCEDURE `RegisterTeacher` (
    IN p_employee_id VARCHAR(20),
    IN p_first_name VARCHAR(50),
    IN p_middle_name VARCHAR(50),
    IN p_last_name VARCHAR(50),
    IN p_sex VARCHAR(10),
    IN p_mobile_number VARCHAR(15),
    IN p_department VARCHAR(100),
    IN p_position VARCHAR(100)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Insert teacher record
    INSERT INTO teachers (
        employee_id, first_name, middle_name, last_name, 
        sex, mobile_number, department, position
    )
    VALUES (
        p_employee_id, p_first_name, p_middle_name, p_last_name,
        p_sex, p_mobile_number, p_department, p_position
    );
    
    COMMIT;
END$$

DELIMITER ;

-- ============================================================================
-- CREATE VIEWS FOR REPORTING
-- ============================================================================

-- Drop old views first (legacy names)
DROP VIEW IF EXISTS `v_daily_attendance_summary`;
DROP VIEW IF EXISTS `v_student_roster`;

-- Drop v3.0 views to allow re-creation (safe for re-running migration)
DROP VIEW IF EXISTS `v_daily_attendance_summary_v3`;
DROP VIEW IF EXISTS `v_student_roster_v3`;
DROP VIEW IF EXISTS `v_teacher_roster`;

-- Updated daily attendance summary view
CREATE VIEW `v_daily_attendance_summary_v3` AS
SELECT 
    a.date,
    a.section,
    COUNT(*) AS total_records,
    SUM(CASE WHEN a.morning_time_in IS NOT NULL THEN 1 ELSE 0 END) AS morning_present,
    SUM(CASE WHEN a.afternoon_time_in IS NOT NULL THEN 1 ELSE 0 END) AS afternoon_present,
    SUM(CASE WHEN a.is_late_morning = 1 THEN 1 ELSE 0 END) AS morning_late,
    SUM(CASE WHEN a.is_late_afternoon = 1 THEN 1 ELSE 0 END) AS afternoon_late,
    SUM(CASE WHEN a.morning_time_in IS NOT NULL AND a.morning_time_out IS NULL THEN 1 ELSE 0 END) AS needs_morning_out,
    SUM(CASE WHEN a.afternoon_time_in IS NOT NULL AND a.afternoon_time_out IS NULL THEN 1 ELSE 0 END) AS needs_afternoon_out
FROM attendance a
GROUP BY a.date, a.section
ORDER BY a.date DESC;

-- Updated student roster view
CREATE VIEW `v_student_roster_v3` AS
SELECT 
    s.id,
    s.lrn,
    CONCAT(s.first_name, ' ', COALESCE(CONCAT(LEFT(s.middle_name, 1), '. '), ''), s.last_name) AS full_name,
    s.class,
    s.section,
    s.mobile_number,
    s.sex,
    (SELECT MAX(date) FROM attendance WHERE lrn = s.lrn) AS last_attendance_date,
    (SELECT COUNT(*) FROM user_badges WHERE user_type = 'student' AND user_id = s.lrn) AS badge_count
FROM students s
ORDER BY s.class ASC, s.section ASC, s.last_name ASC;

-- Teacher roster view
CREATE VIEW `v_teacher_roster` AS
SELECT 
    t.id,
    t.employee_id,
    CONCAT(t.first_name, ' ', COALESCE(CONCAT(LEFT(t.middle_name, 1), '. '), ''), t.last_name) AS full_name,
    t.department,
    t.position,
    t.mobile_number,
    t.sex,
    t.is_active,
    (SELECT MAX(date) FROM teacher_attendance WHERE employee_id = t.employee_id) AS last_attendance_date,
    (SELECT COUNT(*) FROM user_badges WHERE user_type = 'teacher' AND user_id = t.employee_id) AS badge_count
FROM teachers t
ORDER BY t.department ASC, t.last_name ASC;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

COMMIT;

-- Display migration summary
SELECT 'AttendEase v3.0 Migration Complete!' AS status;
SELECT 'New tables created: teachers, teacher_attendance, attendance_schedules, behavior_alerts, badges, user_badges, sms_logs, sms_templates, system_settings' AS tables_created;
SELECT 'Updated tables: admin_users, students, attendance' AS tables_updated;
SELECT 'New procedures: MarkAttendance_v3, RegisterStudent_v3, RegisterTeacher' AS procedures_created;
SELECT 'New views: v_daily_attendance_summary_v3, v_student_roster_v3, v_teacher_roster' AS views_created;
