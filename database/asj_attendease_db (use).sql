-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 12, 2026 at 09:15 AM
-- Server version: 8.0.41
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `asj_attendease_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AddColumnIfNotExists` (IN `tableName` VARCHAR(64), IN `columnName` VARCHAR(64), IN `columnDef` VARCHAR(255))   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `DropIndexIfExists` (IN `tableName` VARCHAR(64), IN `indexName` VARCHAR(64))   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetStudentAttendance` (IN `p_lrn` VARCHAR(13), IN `p_start_date` DATE, IN `p_end_date` DATE)   BEGIN
    SELECT 
        a.*,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.class,
        s.section
    FROM attendance a
    INNER JOIN students s ON a.lrn = s.lrn
    WHERE a.lrn = p_lrn
      AND a.date BETWEEN p_start_date AND p_end_date
    ORDER BY a.date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkAttendance_v3` (IN `p_user_type` ENUM('student','teacher'), IN `p_user_id` VARCHAR(20), IN `p_date` DATE, IN `p_time` TIME, IN `p_action` ENUM('time_in','time_out'), IN `p_section_id` INT)   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `RegisterStudent_v3` (IN `p_lrn` VARCHAR(13), IN `p_first_name` VARCHAR(50), IN `p_middle_name` VARCHAR(50), IN `p_last_name` VARCHAR(50), IN `p_sex` VARCHAR(10), IN `p_mobile_number` VARCHAR(15), IN `p_class` VARCHAR(50), IN `p_section` VARCHAR(50))   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `RegisterTeacher` (IN `p_employee_id` VARCHAR(20), IN `p_first_name` VARCHAR(50), IN `p_middle_name` VARCHAR(50), IN `p_last_name` VARCHAR(50), IN `p_sex` VARCHAR(10), IN `p_mobile_number` VARCHAR(15), IN `p_department` VARCHAR(100), IN `p_position` VARCHAR(100))   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `RenameColumnIfExists` (IN `tableName` VARCHAR(64), IN `oldColumnName` VARCHAR(64), IN `newColumnName` VARCHAR(64), IN `columnDef` VARCHAR(255))   BEGIN
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
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int NOT NULL,
  `admin_id` int DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Action performed',
  `details` text COLLATE utf8mb4_unicode_ci COMMENT 'Action details',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'Browser user agent',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin activity audit log';

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `admin_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, NULL, 'LOGOUT', 'Admin logged out', '::1', NULL, '2025-11-07 08:05:20'),
(2, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 0 attendance records.', '::1', NULL, '2025-11-07 08:14:57'),
(3, 1, NULL, 'EDIT_SECTION', 'Updated section: KALACHUCHI', '::1', NULL, '2025-11-07 08:49:22'),
(4, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 0 attendance records.', '::1', NULL, '2025-11-07 08:51:10'),
(5, 1, NULL, 'DELETE_SECTION', 'Deleted section: KALACHUCHI', '::1', NULL, '2025-11-07 08:51:23'),
(6, 1, NULL, 'MANUAL_ATTENDANCE', 'Marked time_in for LRN: 136514240419 on 2025-11-08 at 15:09', '::1', NULL, '2025-11-08 07:09:32'),
(7, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 1 attendance records.', '::1', NULL, '2025-11-08 07:34:30'),
(8, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 1 attendance records.', '::1', NULL, '2025-11-08 07:46:55'),
(9, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 1 attendance records.', '::1', NULL, '2025-11-08 08:19:15'),
(10, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 1 attendance records.', '::1', NULL, '2025-11-08 08:27:21'),
(11, 1, NULL, 'ADD_SECTION', 'Added section: Integrity', '::1', NULL, '2025-11-08 08:39:56'),
(12, 1, NULL, 'ADD_SECTION', 'Added section: Excellence', '::1', NULL, '2025-11-08 08:40:24'),
(13, 1, NULL, 'ADD_SECTION', 'Added section: Evangalization', '::1', NULL, '2025-11-08 08:40:40'),
(14, 1, NULL, 'ADD_SECTION', 'Added section: Social Responsibility', '::1', NULL, '2025-11-08 08:40:54'),
(15, 1, NULL, 'ADD_SECTION', 'Added section: Peace', '::1', NULL, '2025-11-08 08:41:06'),
(16, 1, NULL, 'ADD_SECTION', 'Added section: Justice', '::1', NULL, '2025-11-08 08:41:23'),
(17, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 1 attendance records.', '::1', NULL, '2025-11-08 08:55:33'),
(18, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 1 attendance records.', '::1', NULL, '2025-11-08 09:00:14'),
(19, 1, NULL, 'EDIT_BADGE', 'Updated badge: Perfect Attendance', '::1', NULL, '2026-02-03 09:21:55'),
(20, 1, NULL, 'DELETE_SCHEDULE', 'Deleted schedule: Default Schedule', '::1', NULL, '2026-02-03 09:48:32'),
(21, 1, NULL, 'DELETE_SCHEDULE', 'Deleted schedule: Default Schedule', '::1', NULL, '2026-02-03 09:48:35'),
(22, 1, NULL, 'DELETE_SCHEDULE', 'Deleted schedule: Default Schedule', '::1', NULL, '2026-02-03 09:48:38'),
(23, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Perfect Attendance', '::1', NULL, '2026-02-03 09:49:12'),
(24, 1, NULL, 'DELETE_BADGE', 'Deleted badge: On-Time Champ', '::1', NULL, '2026-02-03 09:49:14'),
(25, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Most Improved', '::1', NULL, '2026-02-03 09:49:17'),
(26, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Early Bird', '::1', NULL, '2026-02-03 09:49:19'),
(27, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Consistent Achiever', '::1', NULL, '2026-02-03 09:49:20'),
(28, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Perfect Attendance', '::1', NULL, '2026-02-03 09:49:22'),
(29, 1, NULL, 'DELETE_BADGE', 'Deleted badge: On-Time Champ', '::1', NULL, '2026-02-03 09:49:24'),
(30, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Most Improved', '::1', NULL, '2026-02-03 09:49:26'),
(31, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Early Bird', '::1', NULL, '2026-02-03 09:49:28'),
(32, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Consistent Achiever', '::1', NULL, '2026-02-03 09:49:30'),
(33, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Perfect Attendance', '::1', NULL, '2026-02-03 09:49:32'),
(34, 1, NULL, 'DELETE_BADGE', 'Deleted badge: On-Time Champ', '::1', NULL, '2026-02-03 09:49:34'),
(35, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Most Improved', '::1', NULL, '2026-02-03 09:49:36'),
(36, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Early Bird', '::1', NULL, '2026-02-03 09:49:38'),
(37, 1, NULL, 'DELETE_BADGE', 'Deleted badge: Consistent Achiever', '::1', NULL, '2026-02-03 09:49:40'),
(38, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 1 attendance records.', '::1', NULL, '2026-02-03 09:56:03'),
(39, 1, NULL, 'DELETE_STUDENT', 'Deleted student: Zach Reihge Jaudian (LRN: 136514240419). Also deleted 0 attendance records.', '::1', NULL, '2026-02-03 10:33:37'),
(40, 1, NULL, 'EDIT_SECTION', 'Updated section: KALACHUCHI', '::1', NULL, '2026-02-03 10:50:27'),
(41, 1, NULL, 'EDIT_SCHEDULE', 'Updated schedule: Default Schedule', '::1', NULL, '2026-02-03 10:53:15'),
(42, 1, NULL, 'LOGOUT', 'Admin logged out', '::1', NULL, '2026-02-03 11:34:27'),
(43, 1, NULL, 'ADD_TEACHER', 'Added teacher: Habib Jaudian (ID: 1)', '::1', NULL, '2026-02-03 11:38:04'),
(44, 1, NULL, 'DELETE_SCHEDULE', 'Deleted schedule: Default Schedule', '::1', NULL, '2026-02-05 08:39:31'),
(45, 1, NULL, 'EDIT_SCHEDULE', 'Updated schedule: Default Schedule', '::1', NULL, '2026-02-05 08:39:53'),
(46, 1, NULL, 'EDIT_SECTION', 'Updated section: BARBERA', '::1', NULL, '2026-02-05 09:10:29'),
(47, 1, NULL, 'EDIT_TEACHER', 'Updated teacher: Regine Agates (Num: 2233445)', '::1', NULL, '2026-02-08 06:08:42'),
(48, 1, NULL, 'MANUAL_ATTENDANCE', 'Marked teacher time_in for: 2233445 (Regine Agates) on 2026-02-08 at 15:04:00', '::1', NULL, '2026-02-08 07:05:03'),
(49, 1, NULL, 'MANUAL_ATTENDANCE', 'Marked teacher time_in for: 2233445 (Regine Agates) on 2026-02-08 at 15:05:00', '::1', NULL, '2026-02-08 07:05:31'),
(50, 1, NULL, 'EDIT_SECTION', 'Updated section: BARBERA', '::1', NULL, '2026-02-08 07:58:40'),
(51, 1, NULL, 'EDIT_TEACHER', 'Updated teacher: Habib Jaudian (Num: 0328061)', '::1', NULL, '2026-02-10 08:45:57'),
(52, 1, NULL, 'EDIT_BADGE', 'Updated badge: Perfect Attendance', '::1', NULL, '2026-02-10 09:17:38'),
(53, 1, NULL, 'AWARD_BADGE', 'Awarded badge ID 21 to student ID 136511140086', '::1', NULL, '2026-02-12 07:22:29'),
(54, 1, NULL, 'AWARD_BADGE', 'Awarded badge ID 21 to student ID 136511140086', '::1', NULL, '2026-02-12 07:49:42'),
(55, 1, NULL, 'AWARD_BADGE', 'Awarded badge ID 21 to student ID 136514240419', '::1', NULL, '2026-02-12 07:50:07');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hashed password (MD5 or bcrypt)',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Admin full name',
  `role` enum('admin','teacher','staff','student') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reset_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin and staff user accounts';

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`, `reset_token`, `reset_token_expires`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'asjclaveria.attendance@gmail.com', 'System Administrator', 'admin', 1, '2026-02-12 08:10:52', '2025-11-07 06:18:21', '2026-02-12 08:10:52', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `lrn` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Student LRN',
  `date` date NOT NULL COMMENT 'Attendance date',
  `morning_time_in` time DEFAULT NULL,
  `morning_time_out` time DEFAULT NULL,
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Student section at time of attendance',
  `status` enum('present','absent','late','half_day','morning_only','afternoon_only','time_in','time_out') COLLATE utf8mb4_unicode_ci DEFAULT 'present',
  `email_sent` tinyint(1) DEFAULT '0' COMMENT 'Email notification sent flag',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional remarks or notes',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `afternoon_time_in` time DEFAULT NULL,
  `afternoon_time_out` time DEFAULT NULL,
  `is_late_morning` tinyint(1) DEFAULT '0',
  `is_late_afternoon` tinyint(1) DEFAULT '0',
  `period_number` tinyint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily Time In/Out attendance records';

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `lrn`, `date`, `morning_time_in`, `morning_time_out`, `section`, `status`, `email_sent`, `remarks`, `created_at`, `updated_at`, `afternoon_time_in`, `afternoon_time_out`, `is_late_morning`, `is_late_afternoon`, `period_number`) VALUES
(8, '136511140086', '2026-01-30', '08:42:19', '08:43:11', 'Grade 12', 'present', 0, NULL, '2026-01-30 00:42:19', '2026-01-30 00:43:11', NULL, NULL, 0, 0, NULL),
(9, '136544140602', '2026-01-30', '08:45:57', NULL, 'Grade 12', 'present', 0, NULL, '2026-01-30 00:45:57', '2026-01-30 00:45:57', NULL, NULL, 0, 0, NULL),
(10, '136514240419', '2026-02-03', '18:48:03', '19:32:49', 'Grade 7', 'present', 0, NULL, '2026-02-03 10:48:03', '2026-02-03 11:32:49', NULL, NULL, 0, 0, NULL),
(15, '136514240419', '2026-02-05', '17:57:53', '17:58:18', 'KALACHUCHI', 'late', 0, NULL, '2026-02-05 08:40:23', '2026-02-05 09:58:18', '16:40:23', '16:40:51', 0, 0, NULL),
(16, '136511140086', '2026-02-05', '17:58:09', NULL, 'BARBERA', 'late', 0, NULL, '2026-02-05 09:45:55', '2026-02-05 09:58:09', NULL, NULL, 0, 0, NULL),
(17, '136544140602', '2026-02-10', '17:15:31', NULL, 'BARBERA', 'late', 0, NULL, '2026-02-10 09:02:44', '2026-02-10 09:15:31', NULL, NULL, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_schedules`
--

CREATE TABLE `attendance_schedules` (
  `id` int NOT NULL,
  `schedule_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Default Schedule',
  `grade_level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Specific grade level or NULL for all',
  `section_id` int DEFAULT NULL COMMENT 'Specific section or NULL for all',
  `morning_start` time NOT NULL DEFAULT '07:00:00',
  `morning_end` time NOT NULL DEFAULT '12:00:00',
  `morning_late_after` time NOT NULL DEFAULT '07:30:00' COMMENT 'Time after which student is marked late',
  `afternoon_start` time NOT NULL DEFAULT '13:00:00',
  `afternoon_end` time NOT NULL DEFAULT '17:00:00',
  `afternoon_late_after` time NOT NULL DEFAULT '13:30:00' COMMENT 'Time after which student is marked late',
  `is_default` tinyint(1) DEFAULT '0' COMMENT 'Default schedule if no specific match',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Attendance schedule rules for late detection';

--
-- Dumping data for table `attendance_schedules`
--

INSERT INTO `attendance_schedules` (`id`, `schedule_name`, `grade_level`, `section_id`, `morning_start`, `morning_end`, `morning_late_after`, `afternoon_start`, `afternoon_end`, `afternoon_late_after`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Default Schedule', NULL, NULL, '07:00:00', '12:00:00', '07:30:00', '13:00:00', '17:30:00', '13:30:00', 1, 1, '2026-02-03 07:58:57', '2026-02-05 08:39:53');

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int NOT NULL,
  `badge_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `badge_description` text COLLATE utf8mb4_unicode_ci,
  `badge_icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'fa-award' COMMENT 'FontAwesome icon class',
  `badge_color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#4CAF50' COMMENT 'Badge color hex',
  `criteria_type` enum('perfect_attendance','on_time_streak','most_improved','monthly_perfect','early_bird','consistent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `criteria_value` int DEFAULT NULL COMMENT 'Numeric criteria (e.g., streak days)',
  `criteria_period` enum('daily','weekly','monthly','yearly') COLLATE utf8mb4_unicode_ci DEFAULT 'monthly',
  `applicable_roles` set('student','teacher') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student,teacher',
  `points` int DEFAULT '10' COMMENT 'Points awarded for this badge',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Achievement badges for attendance';

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `badge_name`, `badge_description`, `badge_icon`, `badge_color`, `criteria_type`, `criteria_value`, `criteria_period`, `applicable_roles`, `points`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Perfect Attendance', 'No absences for the entire month', 'award', '#ffd700', 'perfect_attendance', 0, 'monthly', 'student,teacher', 50, 1, '2026-02-03 07:58:57', '2026-02-03 09:21:54'),
(2, 'On-Time Champ', '20 consecutive on-time arrivals', 'fa-clock', '#4CAF50', 'on_time_streak', 20, 'daily', 'student,teacher', 30, 1, '2026-02-03 07:58:57', '2026-02-03 07:58:57'),
(3, 'Most Improved', 'Significant attendance improvement over previous month', 'fa-chart-line', '#2196F3', 'most_improved', 20, 'monthly', 'student,teacher', 40, 1, '2026-02-03 07:58:57', '2026-02-03 07:58:57'),
(4, 'Early Bird', 'Arrived early for 10 consecutive days', 'fa-sun', '#FF9800', 'early_bird', 10, 'daily', 'student,teacher', 25, 1, '2026-02-03 07:58:57', '2026-02-03 07:58:57'),
(5, 'Consistent Achiever', '100% attendance for the week', 'fa-trophy', '#9C27B0', 'consistent', 5, 'weekly', 'student,teacher', 20, 1, '2026-02-03 07:58:57', '2026-02-03 07:58:57'),
(21, 'Perfect Attendance', 'No absences for the entire month', 'gem', '#ffd700', 'perfect_attendance', 0, 'monthly', 'student,teacher', 50, 1, '2026-02-05 08:22:09', '2026-02-10 09:17:38'),
(22, 'On-Time Champ', '20 consecutive on-time arrivals', 'fa-clock', '#4CAF50', 'on_time_streak', 20, 'daily', 'student,teacher', 30, 1, '2026-02-05 08:22:09', '2026-02-05 08:22:09'),
(23, 'Most Improved', 'Significant attendance improvement over previous month', 'fa-chart-line', '#2196F3', 'most_improved', 20, 'monthly', 'student,teacher', 40, 1, '2026-02-05 08:22:09', '2026-02-05 08:22:09'),
(24, 'Early Bird', 'Arrived early for 10 consecutive days', 'fa-sun', '#FF9800', 'early_bird', 10, 'daily', 'student,teacher', 25, 1, '2026-02-05 08:22:09', '2026-02-05 08:22:09'),
(25, 'Consistent Achiever', '100% attendance for the week', 'fa-trophy', '#9C27B0', 'consistent', 5, 'weekly', 'student,teacher', 20, 1, '2026-02-05 08:22:09', '2026-02-05 08:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `behavior_alerts`
--

CREATE TABLE `behavior_alerts` (
  `id` int NOT NULL,
  `user_type` enum('student','teacher') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'LRN for students, employee_id for teachers',
  `alert_type` enum('frequent_late','consecutive_absence','sudden_absence','attendance_drop','perfect_streak') COLLATE utf8mb4_unicode_ci NOT NULL,
  `alert_message` text COLLATE utf8mb4_unicode_ci,
  `occurrences` int DEFAULT '1',
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `date_detected` date NOT NULL,
  `severity` enum('info','warning','critical') COLLATE utf8mb4_unicode_ci DEFAULT 'warning',
  `is_acknowledged` tinyint(1) DEFAULT '0',
  `acknowledged_by` int DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Admin notes when acknowledging alert',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Behavior monitoring alerts';

--
-- Dumping data for table `behavior_alerts`
--

INSERT INTO `behavior_alerts` (`id`, `user_type`, `user_id`, `alert_type`, `alert_message`, `occurrences`, `period_start`, `period_end`, `date_detected`, `severity`, `is_acknowledged`, `acknowledged_by`, `acknowledged_at`, `notes`, `created_at`) VALUES
(1, 'student', '136511140086', 'consecutive_absence', 'Absent for 3 consecutive school days', 3, NULL, NULL, '2026-02-09', 'warning', 1, 1, '2026-02-10 15:20:17', 'Tangina mo haha', '2026-02-09 07:04:12'),
(2, 'student', '136544140602', 'consecutive_absence', 'Absent for 7 consecutive school days', 7, NULL, NULL, '2026-02-09', 'critical', 1, 1, '2026-02-10 17:17:23', 'test', '2026-02-09 07:04:12'),
(3, 'student', '136514240419', 'consecutive_absence', 'Absent for 3 consecutive school days', 3, NULL, NULL, '2026-02-09', 'warning', 1, 1, '2026-02-10 18:10:07', 'Testing', '2026-02-09 07:04:12');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int NOT NULL,
  `grade_level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Grade level (e.g., Grade 12)',
  `section_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Section name (e.g., BARBERRA)',
  `adviser` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Class adviser name',
  `school_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'School year (e.g., 2024-2025)',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Active/inactive status',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `am_start_time` time DEFAULT '07:30:00',
  `am_late_threshold` time DEFAULT '08:00:00',
  `am_end_time` time DEFAULT '12:00:00',
  `pm_start_time` time DEFAULT '13:00:00',
  `pm_late_threshold` time DEFAULT '13:30:00',
  `pm_end_time` time DEFAULT '17:00:00',
  `session` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule_id` int DEFAULT NULL,
  `uses_custom_schedule` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Section management for ASJ';

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `grade_level`, `section_name`, `adviser`, `school_year`, `is_active`, `created_at`, `updated_at`, `am_start_time`, `am_late_threshold`, `am_end_time`, `pm_start_time`, `pm_late_threshold`, `pm_end_time`, `session`, `schedule_id`, `uses_custom_schedule`) VALUES
(2, '7', 'KALACHUCHI', '', '2025-2026', 1, '2025-11-08 07:09:10', '2026-02-09 07:34:05', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00', NULL, 9, 0),
(3, '7', 'Integrity', '', '2025-2026', 1, '2025-11-08 08:39:56', '2026-02-09 07:34:05', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00', NULL, 9, 0),
(4, '8', 'Excellence', '', '2025-2026', 1, '2025-11-08 08:40:24', '2026-02-09 07:34:05', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00', NULL, 9, 0),
(5, '9', 'Evangalization', '', '2025-2026', 1, '2025-11-08 08:40:39', '2026-02-09 07:34:05', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00', NULL, 9, 0),
(6, '10', 'Social Responsibility', '', '2025-2026', 1, '2025-11-08 08:40:54', '2026-02-09 07:34:05', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00', NULL, 9, 0),
(7, '11', 'Peace', '', '2025-2026', 1, '2025-11-08 08:41:06', '2026-02-09 07:34:05', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00', NULL, 9, 0),
(8, '12', 'Justice', '', '2025-2026', 1, '2025-11-08 08:41:23', '2026-02-09 07:34:05', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00', NULL, 9, 0),
(9, '12', 'BARBERA', 'Regine Agates', '2026-2027', 1, '2026-01-30 00:41:27', '2026-02-09 07:34:05', '06:00:00', '06:15:00', '14:40:00', '13:00:00', '13:30:00', '17:00:00', 'morning', 9, 0);

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int NOT NULL,
  `recipient_type` enum('student','teacher','parent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile_number` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type` enum('late','absent','time_in','time_out','alert','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','sent','failed','queued') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `provider_response` text COLLATE utf8mb4_unicode_ci COMMENT 'SMS gateway response',
  `message_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Provider message ID',
  `cost` decimal(10,4) DEFAULT NULL COMMENT 'SMS cost if applicable',
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SMS notification logs';

-- --------------------------------------------------------

--
-- Table structure for table `sms_templates`
--

CREATE TABLE `sms_templates` (
  `id` int NOT NULL,
  `template_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_type` enum('late','absent','time_in','time_out','alert') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_template` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Use placeholders: {name}, {date}, {time}, {status}',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SMS message templates';

--
-- Dumping data for table `sms_templates`
--

INSERT INTO `sms_templates` (`id`, `template_name`, `template_type`, `message_template`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'student_late', 'late', 'LATE NOTICE: {name} arrived late at {time} on {date}. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(2, 'student_absent', 'absent', 'ABSENT NOTICE: {name} was marked absent on {date}. Please contact the school if this is incorrect. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(3, 'student_time_in', 'time_in', '{name} has arrived at school at {time} on {date}. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(4, 'student_time_out', 'time_out', '{name} has left school at {time} on {date}. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(5, 'behavior_alert', 'alert', 'ATTENTION: {name} has been flagged for {status}. Please contact the guidance office. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-05 08:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int NOT NULL,
  `lrn` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Learner Reference Number (11-13 digits)',
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Middle name for DepEd forms',
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sex` enum('Male','Female','M','F') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Male' COMMENT 'Sex for SF2 reporting',
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Parent email for alerts notifications',
  `class` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Grade level (e.g., Grade 12)',
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Section name (e.g., BARBERRA)',
  `qr_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'QR code data for scanning',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `mobile_number` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Parent mobile number for SMS notifications'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Student records for The Josephites';

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `lrn`, `first_name`, `middle_name`, `last_name`, `sex`, `email`, `class`, `section`, `qr_code`, `created_at`, `mobile_number`) VALUES
(1, '1', 'Vonny', 'Axcel', 'Wiffill', 'Female', 'vwiffill0@simplemachines.org', '7', NULL, '', '0000-00-00 00:00:00', '960-662-1543'),
(2, '2', 'Delano', 'Waslin', 'Folkard', 'Male', 'dfolkard1@youtube.com', '10', NULL, '', '0000-00-00 00:00:00', '492-777-1818'),
(3, '3', 'Edward', 'Capell', 'Freiburger', 'Male', 'efreiburger2@de.vu', '11', NULL, '', '0000-00-00 00:00:00', '625-788-7981'),
(4, '4', 'Terra', 'Cheesman', 'Baldelli', 'Female', 'tbaldelli3@paypal.com', '11', NULL, '', '0000-00-00 00:00:00', '908-571-5237'),
(5, '5', 'Kirstin', 'Father', 'Kaman', '', 'kkaman4@acquirethisname.com', '11', NULL, '', '0000-00-00 00:00:00', '573-654-8329'),
(6, '6', 'Nikki', 'Cozens', 'Drohan', 'Male', 'ndrohan5@examiner.com', '11', NULL, '', '0000-00-00 00:00:00', '108-866-0112'),
(7, '7', 'Ryley', 'Serris', 'Alywen', '', 'ralywen6@rambler.ru', '12', NULL, '', '0000-00-00 00:00:00', '458-298-3976'),
(8, '8', 'Nadia', 'Balston', 'Verlinde', 'Female', 'nverlinde7@wikia.com', '10', NULL, '', '0000-00-00 00:00:00', '233-956-0937'),
(9, '9', 'Marnie', 'Bellringer', 'Petr', 'Female', 'mpetr8@pagesperso-orange.fr', '7', NULL, '', '0000-00-00 00:00:00', '182-746-7863'),
(10, '136511140086', 'Mark Gian', 'Aquino', 'Jacob', 'Male', 'markgianjacob52', 'Grade 12', 'BARBERA', 'uploads/qrcodes/student_10.png', '2026-01-30 00:41:27', ''),
(11, '136544140602', 'John Carlo', 'Moises', 'Miode', 'Male', 'miodecarlo@gmai', 'Grade 12', 'BARBERA', 'uploads/qrcodes/student_11.png', '2026-01-30 00:44:32', ''),
(12, '12', 'Edmund', 'Kilgallen', 'Bech', 'Male', 'ebechb@cpanel.net', '8', NULL, '', '0000-00-00 00:00:00', '576-728-0298'),
(13, '136514240419', 'Zach Reihge', 'Dalisay', 'Jaudian', 'Male', 'welie10jaudian@gmail.com', 'Grade 7', 'KALACHUCHI', 'uploads/qrcodes/student_13.png', '2026-02-03 10:33:58', '09997670753'),
(14, '14', 'Stanislaus', 'Bitterton', 'Hatwells', 'Male', 'shatwellsd@dailymotion.com', '7', NULL, '', '0000-00-00 00:00:00', '265-174-3208'),
(15, '15', 'Andie', 'Drummond', 'Critch', 'Male', 'acritche@squarespace.com', '8', NULL, '', '0000-00-00 00:00:00', '321-577-5453'),
(16, '16', 'Dniren', 'Langfield', 'Adamolli', 'Female', 'dadamollif@house.gov', '12', NULL, '', '0000-00-00 00:00:00', '220-145-3397'),
(17, '17', 'Tomlin', 'Brewse', 'Lukins', 'Male', 'tlukinsg@rediff.com', '9', NULL, '', '0000-00-00 00:00:00', '395-120-8046'),
(18, '18', 'Christoper', 'Hargreaves', 'Oliveira', 'Male', 'coliveirah@wikispaces.com', '12', NULL, '', '0000-00-00 00:00:00', '314-126-7096'),
(19, '19', 'Torin', 'Southway', 'Minci', 'Male', 'tmincii@joomla.org', '12', NULL, '', '0000-00-00 00:00:00', '167-891-2022'),
(20, '20', 'Rica', 'Jeannet', 'Laurenceau', 'Female', 'rlaurenceauj@i2i.jp', '7', NULL, '', '0000-00-00 00:00:00', '138-771-0965'),
(21, '21', 'See', 'Morrison', 'Andrejevic', 'Male', 'sandrejevick@vinaora.com', '7', NULL, '', '0000-00-00 00:00:00', '343-251-1825'),
(22, '22', 'Julio', 'Aistrop', 'Fache', 'Male', 'jfachel@unblog.fr', '11', NULL, '', '0000-00-00 00:00:00', '278-288-0658'),
(23, '23', 'Andrew', 'Wreight', 'Shilvock', 'Male', 'ashilvockm@reddit.com', '7', NULL, '', '0000-00-00 00:00:00', '588-686-6349'),
(24, '24', 'Xena', 'Sergison', 'Rozet', 'Female', 'xrozetn@creativecommons.org', '11', NULL, '', '0000-00-00 00:00:00', '855-664-8304'),
(25, '25', 'Jedediah', 'Roly', 'Wiseman', 'Male', 'jwisemano@hostgator.com', '11', NULL, '', '0000-00-00 00:00:00', '858-794-9618'),
(26, '26', 'Kevin', 'Das', 'Coomer', 'Male', 'kcoomerp@mayoclinic.com', '9', NULL, '', '0000-00-00 00:00:00', '251-486-8786'),
(27, '27', 'Nerissa', 'Songer', 'Beavon', 'Female', 'nbeavonq@businesswire.com', '11', NULL, '', '0000-00-00 00:00:00', '506-696-2518'),
(28, '28', 'Marice', 'Guerola', 'Ludgrove', 'Female', 'mludgrover@newyorker.com', '8', NULL, '', '0000-00-00 00:00:00', '920-789-4426'),
(29, '29', 'Jessa', 'Carverhill', 'Suttaby', 'Female', 'jsuttabys@histats.com', '11', NULL, '', '0000-00-00 00:00:00', '331-604-9171'),
(30, '30', 'Alana', 'Izsak', 'Allsup', 'Female', 'aallsupt@webmd.com', '10', NULL, '', '0000-00-00 00:00:00', '842-251-6160'),
(31, '31', 'Loutitia', 'Chsteney', 'Notton', 'Female', 'lnottonu@imageshack.us', '8', NULL, '', '0000-00-00 00:00:00', '632-299-1893'),
(32, '32', 'Ronny', 'Statham', 'Crowson', 'Male', 'rcrowsonv@stumbleupon.com', '8', NULL, '', '0000-00-00 00:00:00', '504-436-4197'),
(33, '33', 'Tracey', 'Moscone', 'Melland', 'Male', 'tmellandw@adobe.com', '10', NULL, '', '0000-00-00 00:00:00', '198-518-4134'),
(34, '34', 'Val', 'Sedgwick', 'Fellows', 'Male', 'vfellowsx@sogou.com', '9', NULL, '', '0000-00-00 00:00:00', '509-547-8968'),
(35, '35', 'Tris', 'Dugget', 'Motion', 'Male', 'tmotiony@nifty.com', '10', NULL, '', '0000-00-00 00:00:00', '230-726-9326'),
(36, '36', 'Ozzy', 'Canceller', 'Shafe', 'Male', 'oshafez@loc.gov', '12', NULL, '', '0000-00-00 00:00:00', '741-505-5210'),
(37, '37', 'Frederick', 'Tomaino', 'Espasa', 'Male', 'fespasa10@imageshack.us', '7', NULL, '', '0000-00-00 00:00:00', '741-150-8135'),
(38, '38', 'Timmy', 'Sturch', 'Bardnam', 'Male', 'tbardnam11@businessweek.com', '9', NULL, '', '0000-00-00 00:00:00', '194-624-4468'),
(39, '39', 'Salomi', 'Balch', 'Menlow', '', 'smenlow12@shinystat.com', '10', NULL, '', '0000-00-00 00:00:00', '342-435-4269'),
(40, '40', 'Cassondra', 'Shakeshaft', 'Trahearn', 'Female', 'ctrahearn13@google.pl', '10', NULL, '', '0000-00-00 00:00:00', '349-558-6214'),
(41, '41', 'Darrell', 'March', 'Richarz', 'Male', 'dricharz14@gizmodo.com', '7', NULL, '', '0000-00-00 00:00:00', '269-939-9253'),
(42, '42', 'Gorden', 'Duguid', 'Dawton', 'Male', 'gdawton15@newyorker.com', '10', NULL, '', '0000-00-00 00:00:00', '190-408-9736'),
(43, '43', 'Koren', 'Evered', 'Zealey', 'Female', 'kzealey16@aol.com', '9', NULL, '', '0000-00-00 00:00:00', '650-538-8067'),
(44, '44', 'Nicoline', 'Dent', 'Labrenz', 'Female', 'nlabrenz17@about.com', '12', NULL, '', '0000-00-00 00:00:00', '494-238-1275'),
(45, '45', 'Corette', 'Bogges', 'Cursons', 'Female', 'ccursons18@cafepress.com', '12', NULL, '', '0000-00-00 00:00:00', '390-994-3266'),
(46, '46', 'Asher', 'Dunnett', 'Spellessy', 'Male', 'aspellessy19@cbsnews.com', '7', NULL, '', '0000-00-00 00:00:00', '372-678-4380'),
(47, '47', 'Rockie', 'Filinkov', 'Di Francecshi', '', 'rdifrancecshi1a@sogou.com', '9', NULL, '', '0000-00-00 00:00:00', '220-620-2246'),
(48, '48', 'Winni', 'Zoellner', 'Ferrers', 'Female', 'wferrers1b@craigslist.org', '8', NULL, '', '0000-00-00 00:00:00', '576-568-4827'),
(49, '49', 'Garry', 'Raatz', 'Gasker', 'Male', 'ggasker1c@cnn.com', '12', NULL, '', '0000-00-00 00:00:00', '441-143-9910'),
(50, '50', 'Cathrine', 'Cattrall', 'Gosson', 'Female', 'cgosson1d@spiegel.de', '11', NULL, '', '0000-00-00 00:00:00', '543-583-2020'),
(51, '51', 'Carlin', 'Giddons', 'De Maine', 'Male', 'cdemaine1e@amazon.com', '8', NULL, '', '0000-00-00 00:00:00', '222-309-0592'),
(52, '52', 'Ripley', 'Toomey', 'Jonson', 'Male', 'rjonson1f@wsj.com', '11', NULL, '', '0000-00-00 00:00:00', '226-151-7863'),
(53, '53', 'Alec', 'Marcussen', 'Marshalleck', 'Male', 'amarshalleck1g@edublogs.org', '12', NULL, '', '0000-00-00 00:00:00', '734-758-4369'),
(54, '54', 'Jae', 'Mowles', 'McIlwrick', 'Male', 'jmcilwrick1h@imageshack.us', '10', NULL, '', '0000-00-00 00:00:00', '304-834-1531'),
(55, '55', 'Benny', 'Yoslowitz', 'Grzesiewicz', 'Male', 'bgrzesiewicz1i@mashable.com', '8', NULL, '', '0000-00-00 00:00:00', '393-604-9069'),
(56, '56', 'Lydia', 'Coot', 'Skivington', '', 'lskivington1j@techcrunch.com', '8', NULL, '', '0000-00-00 00:00:00', '953-560-0555'),
(57, '57', 'Claudelle', 'Behning', 'MacSporran', 'Female', 'cmacsporran1k@twitpic.com', '11', NULL, '', '0000-00-00 00:00:00', '818-249-0102'),
(58, '58', 'Veriee', 'Dorant', 'Starton', 'Female', 'vstarton1l@over-blog.com', '11', NULL, '', '0000-00-00 00:00:00', '186-149-7172'),
(59, '59', 'Syd', 'Haps', 'McMeeking', 'Male', 'smcmeeking1m@foxnews.com', '12', NULL, '', '0000-00-00 00:00:00', '376-341-2535'),
(60, '60', 'Tawsha', 'McCuis', 'Decourt', 'Female', 'tdecourt1n@ezinearticles.com', '7', NULL, '', '0000-00-00 00:00:00', '495-687-5068'),
(61, '61', 'Haydon', 'Bartoloma', 'Broseke', 'Male', 'hbroseke1o@thetimes.co.uk', '7', NULL, '', '0000-00-00 00:00:00', '309-344-5634'),
(62, '62', 'Fabien', 'Goldin', 'Snedden', 'Male', 'fsnedden1p@cdbaby.com', '8', NULL, '', '0000-00-00 00:00:00', '193-314-8022'),
(63, '63', 'Edyth', 'Dax', 'Tringham', 'Female', 'etringham1q@jugem.jp', '8', NULL, '', '0000-00-00 00:00:00', '681-830-4447'),
(64, '64', 'Phil', 'Rochewell', 'Seabon', 'Male', 'pseabon1r@mashable.com', '8', NULL, '', '0000-00-00 00:00:00', '404-944-6169'),
(65, '65', 'Petunia', 'Pietersen', 'Kealy', 'Female', 'pkealy1s@google.ca', '11', NULL, '', '0000-00-00 00:00:00', '924-936-1950'),
(66, '66', 'Skipton', 'Patkin', 'Douch', 'Male', 'sdouch1t@uiuc.edu', '8', NULL, '', '0000-00-00 00:00:00', '485-392-1757'),
(67, '67', 'Elvis', 'Bouskill', 'Mattosoff', 'Male', 'emattosoff1u@g.co', '8', NULL, '', '0000-00-00 00:00:00', '743-137-5776'),
(68, '68', 'Janelle', 'Weiser', 'Govett', 'Female', 'jgovett1v@yahoo.co.jp', '11', NULL, '', '0000-00-00 00:00:00', '965-915-3243'),
(69, '69', 'Lew', 'Duval', 'Gower', 'Male', 'lgower1w@mapy.cz', '7', NULL, '', '0000-00-00 00:00:00', '823-365-8922'),
(70, '70', 'Daveta', 'Arend', 'Hallor', 'Female', 'dhallor1x@123-reg.co.uk', '7', NULL, '', '0000-00-00 00:00:00', '662-634-3689'),
(71, '71', 'Gradeigh', 'Mucci', 'Izaac', 'Male', 'gizaac1y@smh.com.au', '12', NULL, '', '0000-00-00 00:00:00', '322-249-6120'),
(72, '72', 'Adiana', 'Lemasney', 'Waadenburg', '', 'awaadenburg1z@seattletimes.com', '9', NULL, '', '0000-00-00 00:00:00', '215-500-1473'),
(73, '73', 'Herta', 'Biasioli', 'Stroder', 'Female', 'hstroder20@cmu.edu', '8', NULL, '', '0000-00-00 00:00:00', '601-397-2630'),
(74, '74', 'Jasper', 'Heinsh', 'Alvares', '', 'jalvares21@macromedia.com', '10', NULL, '', '0000-00-00 00:00:00', '211-753-1136'),
(75, '75', 'Ferdie', 'Izac', 'Gosz', 'Male', 'fgosz22@paypal.com', '10', NULL, '', '0000-00-00 00:00:00', '894-309-4860'),
(76, '76', 'Tyson', 'Robeiro', 'Menguy', 'Male', 'tmenguy23@wired.com', '7', NULL, '', '0000-00-00 00:00:00', '531-997-9415'),
(77, '77', 'Nappie', 'Conwell', 'Aronov', 'Male', 'naronov24@fastcompany.com', '11', NULL, '', '0000-00-00 00:00:00', '140-396-8261');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` enum('string','number','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `setting_group` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_editable` tinyint(1) DEFAULT '1',
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System configuration settings';

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`, `is_editable`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'morning_session_start', '06:00', 'string', 'attendance', 'Morning session start time', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(2, 'morning_session_end', '12:00', 'string', 'attendance', 'Morning session end time', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(3, 'afternoon_session_start', '12:00', 'string', 'attendance', 'Afternoon session start time', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(4, 'afternoon_session_end', '18:00', 'string', 'attendance', 'Afternoon session end time', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(5, 'sms_enabled', '0', 'boolean', 'notifications', 'Enable SMS notifications', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(6, 'sms_provider', 'semaphore', 'string', 'notifications', 'SMS gateway provider', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(7, 'sms_on_late', '1', 'boolean', 'notifications', 'Send SMS on late arrival', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(8, 'sms_on_absent', '1', 'boolean', 'notifications', 'Send SMS on absence', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(9, 'behavior_monitoring_enabled', '1', 'boolean', 'monitoring', 'Enable behavior monitoring', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(10, 'late_threshold_weekly', '3', 'number', 'monitoring', 'Late occurrences per week to trigger alert', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(11, 'absence_threshold_consecutive', '2', 'number', 'monitoring', 'Consecutive absences to trigger alert', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(12, 'badges_enabled', '1', 'boolean', 'badges', 'Enable badge system', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(13, 'badge_notifications', '1', 'boolean', 'badges', 'Notify users when badges are earned', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(14, 'school_name', 'Academy of St. Joseph Claveria, Cagayan Inc.', 'string', 'school', 'School name', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09'),
(15, 'school_year', '2025-2026', 'string', 'school', 'Current school year', 1, NULL, '2026-02-03 07:58:57', '2026-02-05 08:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sex` enum('Male','Female') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Male',
  `mobile_number` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Contact number',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email address for notifications',
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Department or subject area',
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Job position/title',
  `qr_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to QR code image',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `shift` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT 'morning' COMMENT 'Teacher shift: morning|afternoon|both',
  `employee_number` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teacher records for AttendEase v3.0';

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `first_name`, `middle_name`, `last_name`, `sex`, `mobile_number`, `email`, `department`, `position`, `qr_code`, `is_active`, `created_at`, `updated_at`, `shift`, `employee_number`) VALUES
(1, 'Habib', 'Dalisay', 'Jaudian', 'Male', '09997670753', 'jaudianhabib879@gmail.com', 'Science', 'Subject Teacher', 'uploads/qrcodes/teacher_1.png', 1, '2026-02-03 11:38:04', '2026-02-10 08:45:57', 'morning', '0328061'),
(2, 'Earl', 'Buggs', 'Rodrigo', 'Male', '653-386-9412', 'erodrigo1@google.co.uk', 'Marketing', 'Engineer', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJ/SURBVDjLY/j//z8DJZgsTV+9fAu+uHo8+GzvXECWAV+c3R//mTn9/ydLu4eka3ZyY/ts63T3k4Xt+4/GlqS74JONY+9Hc5tdH4wsmAmGgWv9xQKX2', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(3, 'Silvano', 'Faircliffe', 'Mably', 'Male', '520-208-3596', 'smably2@360.cn', 'Accounting', 'Subcontractor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJkSURBVDjLpZNbSJNhHIeli4jAKOhun9KNbUpaURFRREkFVjpNRcssXOKYZ9J0ihnN05zSUpflzMOnW5tuammajUkWpCbbrOxwEzZJw7Rt2pxJh/16/', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(4, 'Coreen', 'Giannini', 'Delooze', 'Female', '164-569-1691', 'cdelooze3@adobe.com', 'Research and Development', 'Project Manager', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKqSURBVDjLjZNdSFNhGMdPqJU3QXdCQXhT3XUdQqS0rNR2sRB3YWxMIyxEYtM22MJKY+kCo7ESgi5CK8MgWrimTN1xm24izuWGKUutOdP5se+zj/PvP', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(5, 'Emlen', 'Trotter', 'Arman', 'Male', '706-605-1097', 'earman4@stumbleupon.com', 'Sales', 'Supervisor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAK4SURBVDjLfVPtT9JRFPbfKNssUVQUSgH5AYriTNcU3xey+YIUaqilYr6ks/INWQjmJMv5EigmCJGomeg07YNttWrl6kMf2/rQ1qZWk7ny6fdzZaLS2', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(6, 'Alano', 'Klishin', 'Blois', 'Male', '841-890-1359', 'ablois5@java.com', 'Business Development', 'Estimator', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKbSURBVDjLpZNLbIxRGIaf/9Lfpa1rZ0hNtYpEVRBiNkUiEZFoKjSxQmLBglizIQiJILEgWHQlgkpLGg1BU6E3imorLkElLmOmM1XV6mXm/8/5LH5GI', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(7, 'Lamar', 'Benka', 'Beeswing', 'Male', '137-156-0871', 'lbeeswing6@goo.ne.jp', 'Sales', 'Supervisor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAESSURBVDjLtZMxTsNAEEW/k4gCIRdQcgNuwQ18CG7AOdL4AJyBgmNQ04JE4Q7REGHPzP8Uuw52nJggxEqr3dFq3vy/s1tIwl/GYu6wrusf6cVQwf3jZ', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(8, 'Moises', 'Cheves', 'Topliss', 'Male', '172-665-9318', 'mtopliss7@w3.org', 'Support', 'Engineer', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIISURBVDjLlZPRT1JxFMf5E+6fwL9Ri8lsI5cSV7swL82w0SZTB6zWXOuB0cLU8HKhuAooTNrFupcAsYWjh1sRIaDgTLGXxmubD2w+9Prth29tXMWH8', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(9, 'Derk', 'Carrivick', 'Pridham', 'Male', '332-190-3218', 'dpridham8@marriott.com', 'Legal', 'Project Manager', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAALsSURBVDjLjdBbTJJhGAdw6qKLuuiqi04XZoeLsnXQOecFfWGu8ECQJs08UCADLMQvUVI0UcksQj4NzM1DU6RUEFtj1eZMR02nkOWxNeUiFdfWTM1sK', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(10, 'Hazel', 'Purveys', 'McIlriach', 'Female', '443-666-1135', 'hmcilriach9@baidu.com', 'Product Management', 'Architect', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKXSURBVBgZBcFNiJR1HADg5//Ou767jblWM9qOyWZSXlqCNMpYWbKo3VMEhYJKEn1Rlzx0CSmIIq20o5fCQ3QSskOXCPqEuhQWXkLsg3V3xtVN23LW2', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(11, 'Nickolai', 'Crossingham', 'Gledhill', 'Male', '450-444-8951', 'ngledhilla@businessinsider.com', 'Support', 'Surveyor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAGZSURBVBgZpcHPi41hHMbhz/P2oJijzEKSQn4uFRsNK8lKKWZtFvMPKFulrKxZKGzs2MhismI3S5Qpg0YNZUFiaso5Z973e9+eJ45mYWo015VssxGZY', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(12, 'Kristi', 'Pulver', 'Favill', 'Female', '770-616-1722', 'kfavillb@alexa.com', 'Sales', 'Architect', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIuSURBVDjLjZNPiFJRFManVo24jYISClqli3KGp0VY7mSCBqKoIHIIahFtStwUCPVGIiloRJmkqQERiqBFIZGtcgrHrCGaFo+hCQlCU9/T558n/v+69', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(13, 'Shantee', 'Hanington', 'Davydochkin', '', '945-475-2822', 'sdavydochkinc@deliciousdays.com', 'Engineering', 'Construction Worker', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKRSURBVDjLhZHfT1JhHMb9F7ptXXXR2lw/Llp3blnNZauLtmwtp15oWsu6oJZ5bKyFQiGIEIRIKoEsJtikWM1JmiQhtpieo3ISUoEINiJNE2SgT5x3Z', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(14, 'Hyman', 'Kennicott', 'Weetch', 'Male', '784-527-9841', 'hweetchd@tinyurl.com', 'Engineering', 'Engineer', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAK3SURBVDjLdVM9TFNRFP5e+0p/KcQIrZZXYCCBdIMoaGqESGxCTBqCg5suxsRF44IjgzG6mZjYwTB2Mg6YOLQdNKUFTKwYEJ2koYZSqi20j9ff91rPv', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(15, 'Morgan', 'Mease', 'Pickersgill', 'Male', '987-992-0240', 'mpickersgille@a8.net', 'Engineering', 'Supervisor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAHUSURBVDjLxZM7a1RhEIafc3J2z6qJkIuCKChItBNSBQ0iIlZiK4gWItj6HwRbC7FRf4CVnSCIkH9gJVjYiCDximCyZ7/zfXOz2A0I2qVwmmFg3rm87', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(16, 'Terrijo', 'Hagerty', 'Brookshaw', 'Female', '557-141-4774', 'tbrookshawf@odnoklassniki.ru', 'Research and Development', 'Estimator', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIhSURBVDjLlZPrThNRFIWJicmJz6BWiYbIkYDEG0JbBiitDQgm0PuFXqSAtKXtpE2hNuoPTXwSnwtExd6w0pl2OtPlrphKLSXhx07OZM769qy19wwAG', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(17, 'Ulberto', 'Borrell', 'Swadlinge', '', '222-176-8655', 'uswadlingeg@360.cn', 'Product Management', 'Supervisor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJRSURBVDjLpZNLSJRhFIaff+Y3x3S8pKmjpBlRSKQGQUTbLhBCmxbSrk1CiyCJEAJxUe6qVQURtGvTJooQNLtYEbVKoVDJMUrFS6iMzs35zjkt/indB', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(18, 'Waldo', 'Bottini', 'Buckby', 'Male', '269-221-6997', 'wbuckbyh@businessinsider.com', 'Support', 'Construction Worker', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKpSURBVDjLpZPNa5xVFIef+877Tmcyk2Q+M0onSVOKnVQRMaBWNwq6c6NQ0IV/gLjspuBGkEKh2JUb14J24UZol5EKRaqhtjiNCamtDcSkNpkknUzn/', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(19, 'Minette', 'Tebbitt', 'Summersett', 'Female', '157-678-8942', 'msummersetti@yelp.com', 'Legal', 'Construction Worker', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKKSURBVDjLpZNdSBRRGIbnzOzubSxBRReBYhTRDziQQlKxbmoKItp0YVRUsBB2UVQsWdkfilHaj6GuZqEkhJaSf6knISqUYIgooogWS2uRwjFd25yZ3', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(20, 'Irina', 'Danihel', 'Glennon', 'Female', '859-840-6341', 'iglennonj@wikipedia.org', 'Sales', 'Project Manager', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAADzSURBVDjLxZMxTsRAEATL6EROSsDD+A4xv+EzJCQEiA9ccjtdBF7ba9nZBWw00k63qmd2J5V7zgN3nstSvH/8rChRBKoAwYQIlbmuwNvry7QzAHh+e', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(21, 'Darrick', 'McTrustie', 'O\'Doohaine', 'Male', '625-871-7145', 'dodoohainek@google.it', 'Research and Development', 'Electrician', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAK3SURBVDjLpZNNSFRRFMfP+5zxY2a0l81Mg6NDiosKRKFNYC6kIERpUaSbVuGmja0yp5A0aRG0CdolIa5CAqmNEzQQfoCamhDT4ETNjDOWOOW8eR8z7', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(22, 'Regine', 'Murrill', 'Agates', 'Female', '0999-584-8585', 'ragatesl@prlog.org', '', 'Architect', 'uploads/qrcodes/teacher_TEACHER2233445.png', 2, '0000-00-00 00:00:00', '2026-02-08 06:14:51', 'morning', '2233445'),
(23, 'Genia', 'Arden', 'Gaitung', 'Female', '719-104-2164', 'ggaitungm@youtube.com', 'Marketing', 'Estimator', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAALNSURBVDjLjZBbSNNRHMdn0x7qQXwoyoqYS3tIJUsR8UHnFEKayXCO8Dp0mje8zLVm3vJSGpk6539azQteZt6aYmuKoQ8q3uY9LSJHqFsUJGpqpNu3v', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(24, 'Fionnula', 'Gerok', 'Fere', 'Female', '928-253-1883', 'fferen@nydailynews.com', 'Product Management', 'Electrician', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAABlSURBVCjPY/jPgB8yDC4FilKKDfJXZa6KNwhKYVfQkPW/63/b/+T/XA1YFchd7fqf/j/2f+N/5qtYFUhe7fif9D/sf91/BuwKhBoS/jcBpcP/M2C3g', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(25, 'Gwyneth', 'Beuscher', 'Vasilik', '', '213-912-7357', 'gvasiliko@booking.com', 'Human Resources', 'Architect', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAG5SURBVDjLpdHNa9MAGMfx/BtBvO/i26XBw0DEocLUSift2Lp2LupYFh2CVLA6rIMVmqaiqxDZxaJQNehSspCksdYXRNGJzOKmNz0IDpRSvH+9SBVEa', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(26, 'Moishe', 'Wellsman', 'Snedden', '', '189-361-0248', 'msneddenp@home.pl', 'Human Resources', 'Construction Expeditor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAH4SURBVDjLlZM7i2JBEIUd4/kJu7D+g4FZxjXSRFHQwMBsMxFFE8VEMVEDhRURRREDTY18pAYKirHJBAui0YJv8fp+jme7mrmDjvtsONzuqq7vdp2mJ', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(27, 'Britney', 'Roberto', 'Powelee', 'Female', '651-501-4122', 'bpoweleeq@feedburner.com', 'Services', 'Project Manager', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIvSURBVDjLpZNPiBJRHMffG6aZHcd1RNaYSspxSbFkWTpIh+iwVEpsFC1EYO2hQ9BlDx067L1b0KVDRQUa3jzWEughiDDDZRXbDtauU5QV205R6jo6a', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(28, 'Georgina', 'Abramamovh', 'Chapiro', '', '245-229-6629', 'gchapiror@fema.gov', 'Human Resources', 'Construction Expeditor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJQSURBVDjLpZM7SJUBFMd/371FVnp9VVppoaI377UwKrgVREMQ0WPIoQgKGnJxiGjJoMUSpIaguanHFkFGDUFLQ0NlpRiBg4qV1e1mJr7u/c6jIVDDx', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(29, 'Moira', 'Haestier', 'Kunisch', 'Female', '462-198-6782', 'mkunischs@bing.com', 'Research and Development', 'Surveyor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAGdSURBVDjLlZNLSwJhFIa1Rb8iIWhRQUlluuoftDEtC5TKSgINily1CmoT0kJBqwlSaBGBLVxItGgZQQQVFe3bKN7wOjqO2tucwRGvqAMPMzDf+8w5Z', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(30, 'Euell', 'Monteaux', 'Layson', 'Male', '960-473-6443', 'elaysont@goo.ne.jp', 'Legal', 'Project Manager', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJ1SURBVDjLpVNdSFRBFP7u3S3Xh1U3LN0MQ5NsrcifKEMCC8wkpWCJKKGXiqAgiF4KLCiCIIKggtBCgpAI8qEkAmXVyCW13BbJWiRchWUzV4pSd9177', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(31, 'Darsey', 'Lindenbaum', 'Saller', 'Female', '732-724-6630', 'dsalleru@stanford.edu', 'Engineering', 'Estimator', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAALTSURBVBgZBcFNaNdlHADwz/P8fpvbfJnO+M+XtKz1ovSqSEaJIUWRXoQKIoI81MU6dAgPQXQphS4dIiOQhMp7h4hAD84iArVSK8FU0NRtbro52+b2/', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(32, 'Nowell', 'Castelin', 'Pudner', 'Male', '461-810-4769', 'npudnerv@arizona.edu', 'Product Management', 'Surveyor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAALASURBVDjLdZPLS1tBFMaDf4D7LLqviy66aulSsnBRaDWLSqFgmvRBUhG7UDQoJBpiSGpKTQrRIIqvYBRMKJHeRuMzPq/GNAbFx8JHLwoKLZau7v16z', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(33, 'Janith', 'Littley', 'Duffan', 'Female', '968-532-4777', 'jduffanw@jigsy.com', 'Support', 'Electrician', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKqSURBVDjLfVPRS1NhFP/du+suJLFN3VRcrQjZQxor7DECwSh6CHHgMgNB6kF871+IHtxeJTFiENFLVA/hy9yLYLWtLbSRFGbqbJtt624T57Z7O+fbE', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(34, 'Ebba', 'Aronstein', 'Stinchcombe', 'Female', '188-883-8585', 'estinchcombex@blogtalkradio.com', 'Research and Development', 'Subcontractor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIESURBVDjLpVLPS9NxGH7mjw3cZmvGclsFxcwQpFsQCRLBvIZJEC7LkyVdO5gnDx0i2qk/IAipyA5GBYoQkX0rWIaxTYvad9E85IbVcd/P834+HcZWK', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(35, 'Herold', 'Featherstonhaugh', 'Pask', 'Male', '841-850-0438', 'hpasky@hubpages.com', 'Business Development', 'Architect', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKESURBVDjLjZNPaJJhHMc9ddulU8ei/WkQRQwanbLD1JweDDaI/q2cDhQWOjGNwXTCBk5BbeRczZZjNx1sNWaBS802CNPDOpgo2y4h4kT8O53x7X2eo', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(36, 'Carline', 'Hogbin', 'Perkinson', 'Female', '174-220-6284', 'cperkinsonz@cbslocal.com', 'Services', 'Estimator', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAF8SURBVBgZBcFNiIwBAAbgN5etvQiJOPiuyk/YwqLk52v9NbTtarDGNpbhoCknHKgtOXxDIU60mVI4OOxVbtuchaTkoByUMpoc/NTjeSIi0irPd5q9U', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(37, 'Rosalynd', 'Gothard', 'Greenroad', '', '122-239-1037', 'rgreenroad10@paginegialle.it', 'Support', 'Construction Expeditor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIzSURBVDjLhZNLbxJRGIZP22la6KLpou1GN11QKGW4DFTH1phorZcYi8R6w2ooAlUSjUStC39A49I/4sK9f8OFLoyCwZAAgsMwc87x9RuoSXWgLt7Mv', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(38, 'Shirl', 'Greader', 'Scutts', 'Female', '596-732-3350', 'sscutts11@odnoklassniki.ru', 'Marketing', 'Estimator', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIrSURBVDjLxVPLjhJBFD3V3dCYSZjwDipDRjCOCcTgLGalJmxYufAD0E/QHV9g3LjSsGDhik/QjTEaTdgaEsQQINEFJjwEecmr6Yf3lkJmPwsruXWrq', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(39, 'Leoine', 'Barhem', 'Spinke', 'Female', '339-946-3047', 'lspinke12@yelp.com', 'Legal', 'Construction Foreman', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJMSURBVDjLpVPPaxNBGH3ZJCQkVSEkJJja0MSCabHoSUEKerAgggRy0ZP9F4QeDAhepKeeBA8GPFQCEutdCMRSSYkimouIVqUkNW1iyJZN0mR/zvrNw', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(40, 'Huberto', 'Gardner', 'Canacott', 'Male', '202-552-4186', 'hcanacott13@nydailynews.com', 'Legal', 'Electrician', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAInSURBVDjLpVPLahNRGP5mcrWZhGgaLEwtJFJJqQEXgoKIkKBkKfoAeYKA4M7S4kLyDF34AF4eIIOQMC7sooqbECExImnSGgwqTTtmcubm+Y9MSAWh0', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(41, 'Charyl', 'McIlmorie', 'MacNaughton', 'Female', '977-240-9152', 'cmacnaughton14@yahoo.com', 'Services', 'Architect', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIrSURBVDjLpdNfSFNhGMdx6Q9RDoIuLAQtYVuWpZSunKQXjYIKwdBqkJkEYRhIBEFRRmVbGYNB2hTnVKaGzWFq/im0mLgyLTNjpdSFJkX/hCxYY26db', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(42, 'Arvy', 'De Fries', 'McDavid', 'Male', '610-590-6864', 'amcdavid15@facebook.com', 'Support', 'Construction Manager', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJdSURBVDjLpZP7S1NhGMf9W7YfogSJboSEUVCY8zJ31trcps6zTI9bLGJpjp1hmkGNxVz4Q6ildtXKXzJNbJRaRmrXoeWx8tJOTWptnrNryre5YCYuI', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(43, 'Giacobo', 'Yanele', 'Redmond', 'Male', '268-846-0597', 'gredmond16@etsy.com', 'Product Management', 'Construction Manager', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJNSURBVDjLpZNLSNVhEMV/f7UCy+iaqWkpKVFqUIlRUS3SHkSPRYugVbuCaFPrEgIxsKCFix5QtIsWraIIS5MQa6G1CTN7iKa3SAyt7P7v983M1+Kqm', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(44, 'Bonita', 'Bastistini', 'Cauldwell', 'Female', '835-256-7763', 'bcauldwell17@blinklist.com', 'Legal', 'Electrician', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAACWSURBVCjPY/jPgB8y0ElB+YHyA8UTcg+kLYjfEP4Bm4ILxQa5Dqn/4xv+M/hdcHXAUFAc8J8hzSH+fzhQgauCjQJWN8Q7RPz3AyqwmWC0QfO/wgKJB', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(45, 'Toinette', 'Watters', 'Rodnight', 'Female', '516-677-5223', 'trodnight18@dot.gov', 'Human Resources', 'Subcontractor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAHdSURBVDjLpZNraxpBFIb3a0ggISmmNISWXmOboKihxpgUNGWNSpvaS6RpKL3Ry//Mh1wgf6PElaCyzq67O09nVjdVlJbSDy8Lw77PmfecMwZg/I/GD', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(46, 'Darryl', 'Echalie', 'Pendall', 'Female', '923-556-7944', 'dpendall19@hao123.com', 'Support', 'Estimator', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAMESURBVDjLXZNrSFNxGMYPgQQRfYv6EgR9kCgKohtFgRAVQUHQh24GQReqhViWlVYbZJlZmZmombfVpJXTdHa3reM8uszmWpqnmQuX5drmLsdjenR7e', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(47, 'Juan', 'Prandi', 'McRitchie', 'Male', '853-468-3061', 'jmcritchie1a@macromedia.com', 'Legal', 'Construction Worker', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAK4SURBVDjLjZPrT1JhHMfPq/NH+K6ty2bhJcswzUa2hTMaEmCsZmWuUU0HQuAVEWHMgCnLy2yOhiOKIs0L08ByXgab1TTRNlO7ULwylTOZ9iL9djiVr', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(48, 'Priscella', 'Lillegard', 'Vynehall', 'Female', '626-974-7067', 'pvynehall1b@issuu.com', 'Services', 'Engineer', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKVSURBVBgZBcFPaNV1AADwz/f7+73XnNveK9+2x6RVqMVGoaYjg6DsEtGhhLpEtzrXECGCYIkdO4wulZcgunQKhFpkan92iXBoWrRmI4e4tf9ue2/v/', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(49, 'Odille', 'Sainthill', 'Lowseley', 'Female', '534-951-1568', 'olowseley1c@booking.com', 'Sales', 'Subcontractor', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIzSURBVDjLhZNLbxJRGIZP22la6KLpou1GN11QKGW4DFTH1phorZcYi8R6w2ooAlUSjUStC39A49I/4sK9f8OFLoyCwZAAgsMwc87x9RuoSXWgLt7Mv', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(50, 'Annnora', 'Nazareth', 'Doughartie', 'Female', '447-302-5244', 'adoughartie1d@friendfeed.com', 'Legal', 'Project Manager', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAGvSURBVDjLxZPbSgJRGIV9BB+h+yikuomIJigo6UBnKtJOUFSkSBIhMUZmBywUNDtQG6KMCrITXVnzCANSYUNNOoMzzEjuR/jbXWjQjN0UdLFuNnt9e', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(51, 'Webb', 'Westoff', 'Bowton', '', '760-561-6216', 'wbowton1e@tinyurl.com', 'Training', 'Architect', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAI1SURBVDjLY/j//z8DJZgsTV+9fAu+uHo8+GzvXECWAV+c3R//mTn9/ydLu4eka3ZyY/ts63T3k4Xt+4/GlqS74JONY+9Hc5tdH4wsmAmGgWv9xQKX2', 2, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL),
(52, 'Beatrisa', 'Stockley', 'McAvin', 'Female', '293-199-7934', 'bmcavin1f@sun.com', 'Human Resources', 'Construction Foreman', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAI9SURBVDjLpZNBS9RhEMZ/u60aZAdNSXdLrcxNS82DaRQVRBCUGngwwkOnvkB0yEt0qy/QKSrq5DUSQgLTSi01d80gcrXSTTdTViTU//+ded8ORihFY', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'morning', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_attendance`
--

CREATE TABLE `teacher_attendance` (
  `id` int NOT NULL,
  `date` date NOT NULL COMMENT 'Attendance date',
  `morning_time_in` time DEFAULT NULL COMMENT 'Morning Time In',
  `morning_time_out` time DEFAULT NULL COMMENT 'Morning Time Out',
  `afternoon_time_in` time DEFAULT NULL COMMENT 'Afternoon Time In',
  `afternoon_time_out` time DEFAULT NULL COMMENT 'Afternoon Time Out',
  `is_late_morning` tinyint(1) DEFAULT '0',
  `is_late_afternoon` tinyint(1) DEFAULT '0',
  `status` enum('present','absent','late','half_day','on_leave') COLLATE utf8mb4_unicode_ci DEFAULT 'present',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `department` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `shift` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT 'morning',
  `employee_number` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teacher attendance records';

--
-- Dumping data for table `teacher_attendance`
--

INSERT INTO `teacher_attendance` (`id`, `date`, `morning_time_in`, `morning_time_out`, `afternoon_time_in`, `afternoon_time_out`, `is_late_morning`, `is_late_afternoon`, `status`, `remarks`, `created_at`, `updated_at`, `department`, `shift`, `employee_number`) VALUES
(1, '2026-02-08', '12:56:46', NULL, '15:05:00', NULL, 0, 0, 'late', NULL, '2026-02-08 04:56:46', '2026-02-08 07:05:31', 'Science', 'morning', NULL),
(6, '2026-02-10', '17:14:32', '17:15:40', NULL, NULL, 0, 0, 'late', NULL, '2026-02-10 09:14:32', '2026-02-10 09:15:40', '', 'morning', '0328061');

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int NOT NULL,
  `user_type` enum('student','teacher') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'LRN for students, employee_id for teachers',
  `badge_id` int NOT NULL,
  `date_earned` date NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `is_displayed` tinyint(1) DEFAULT '1' COMMENT 'Show on profile',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Badges earned by users';

--
-- Dumping data for table `user_badges`
--

INSERT INTO `user_badges` (`id`, `user_type`, `user_id`, `badge_id`, `date_earned`, `period_start`, `period_end`, `is_displayed`, `created_at`) VALUES
(1, 'student', '136511140086', 21, '2026-02-12', '2026-02-01', '2026-02-12', 1, '2026-02-12 07:22:29'),
(2, 'student', '136511140086', 21, '2026-02-12', '2026-02-01', '2026-02-12', 1, '2026-02-12 07:49:42'),
(3, 'student', '136514240419', 21, '2026-02-12', '2026-02-01', '2026-02-12', 1, '2026-02-12 07:50:07');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_daily_attendance_summary_v3`
-- (See below for the actual view)
--
CREATE TABLE `v_daily_attendance_summary_v3` (
`afternoon_late` decimal(23,0)
,`afternoon_present` decimal(23,0)
,`date` date
,`morning_late` decimal(23,0)
,`morning_present` decimal(23,0)
,`needs_afternoon_out` decimal(23,0)
,`needs_morning_out` decimal(23,0)
,`section` varchar(50)
,`total_records` bigint
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_roster_v3`
-- (See below for the actual view)
--
CREATE TABLE `v_student_roster_v3` (
`badge_count` bigint
,`class` varchar(50)
,`full_name` varchar(104)
,`id` int
,`last_attendance_date` date
,`lrn` varchar(13)
,`mobile_number` varchar(15)
,`section` varchar(50)
,`sex` enum('Male','Female','M','F')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_teacher_roster`
-- (See below for the actual view)
--
CREATE TABLE `v_teacher_roster` (
);

-- --------------------------------------------------------

--
-- Structure for view `v_daily_attendance_summary_v3`
--
DROP TABLE IF EXISTS `v_daily_attendance_summary_v3`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_daily_attendance_summary_v3`  AS SELECT `a`.`date` AS `date`, `a`.`section` AS `section`, count(0) AS `total_records`, sum((case when (`a`.`morning_time_in` is not null) then 1 else 0 end)) AS `morning_present`, sum((case when (`a`.`afternoon_time_in` is not null) then 1 else 0 end)) AS `afternoon_present`, sum((case when (`a`.`is_late_morning` = 1) then 1 else 0 end)) AS `morning_late`, sum((case when (`a`.`is_late_afternoon` = 1) then 1 else 0 end)) AS `afternoon_late`, sum((case when ((`a`.`morning_time_in` is not null) and (`a`.`morning_time_out` is null)) then 1 else 0 end)) AS `needs_morning_out`, sum((case when ((`a`.`afternoon_time_in` is not null) and (`a`.`afternoon_time_out` is null)) then 1 else 0 end)) AS `needs_afternoon_out` FROM `attendance` AS `a` GROUP BY `a`.`date`, `a`.`section` ORDER BY `a`.`date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_student_roster_v3`
--
DROP TABLE IF EXISTS `v_student_roster_v3`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_roster_v3`  AS SELECT `s`.`id` AS `id`, `s`.`lrn` AS `lrn`, concat(`s`.`first_name`,' ',coalesce(concat(left(`s`.`middle_name`,1),'. '),''),`s`.`last_name`) AS `full_name`, `s`.`class` AS `class`, `s`.`section` AS `section`, `s`.`mobile_number` AS `mobile_number`, `s`.`sex` AS `sex`, (select max(`attendance`.`date`) from `attendance` where (`attendance`.`lrn` = `s`.`lrn`)) AS `last_attendance_date`, (select count(0) from `user_badges` where ((`user_badges`.`user_type` = 'student') and (`user_badges`.`user_id` = `s`.`lrn`))) AS `badge_count` FROM `students` AS `s` ORDER BY `s`.`class` ASC, `s`.`section` ASC, `s`.`last_name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_teacher_roster`
--
DROP TABLE IF EXISTS `v_teacher_roster`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_teacher_roster`  AS SELECT `t`.`id` AS `id`, `t`.`employee_id` AS `employee_id`, concat(`t`.`first_name`,' ',coalesce(concat(left(`t`.`middle_name`,1),'. '),''),`t`.`last_name`) AS `full_name`, `t`.`department` AS `department`, `t`.`position` AS `position`, `t`.`mobile_number` AS `mobile_number`, `t`.`sex` AS `sex`, `t`.`is_active` AS `is_active`, (select max(`teacher_attendance`.`date`) from `teacher_attendance` where (`teacher_attendance`.`employee_id` = `t`.`employee_id`)) AS `last_attendance_date`, (select count(0) from `user_badges` where ((`user_badges`.`user_type` = 'teacher') and (`user_badges`.`user_id` = `t`.`employee_id`))) AS `badge_count` FROM `teachers` AS `t` ORDER BY `t`.`department` ASC, `t`.`last_name` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_active_users` (`is_active`,`role`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_attendance` (`lrn`,`date`),
  ADD KEY `idx_date_section` (`date`,`section`),
  ADD KEY `idx_lrn_date` (`lrn`,`date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email_sent` (`email_sent`),
  ADD KEY `idx_attendance_date_lrn` (`date`,`lrn`);

--
-- Indexes for table `attendance_schedules`
--
ALTER TABLE `attendance_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grade` (`grade_level`),
  ADD KEY `idx_section` (`section_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_default` (`is_default`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_criteria` (`criteria_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `behavior_alerts`
--
ALTER TABLE `behavior_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_type`,`user_id`),
  ADD KEY `idx_type` (`alert_type`),
  ADD KEY `idx_acknowledged` (`is_acknowledged`),
  ADD KEY `idx_date` (`date_detected`),
  ADD KEY `idx_severity` (`severity`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section` (`grade_level`,`section_name`),
  ADD KEY `idx_grade_section` (`grade_level`,`section_name`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_sections_schedule` (`am_start_time`,`pm_start_time`),
  ADD KEY `idx_sections_am_start` (`am_start_time`),
  ADD KEY `idx_sections_pm_start` (`pm_start_time`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient_type`,`recipient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`message_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `sms_templates`
--
ALTER TABLE `sms_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_template` (`template_name`),
  ADD KEY `idx_type` (`template_type`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lrn` (`lrn`),
  ADD KEY `idx_lrn` (`lrn`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_class` (`class`),
  ADD KEY `idx_gender` (`sex`),
  ADD KEY `idx_students_section` (`section`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting` (`setting_key`),
  ADD KEY `idx_group` (`setting_group`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_teachers_employee_number` (`employee_number`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_daily` (`date`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `ix_teacher_attendance_employee_number` (`employee_number`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_type`,`user_id`),
  ADD KEY `idx_badge` (`badge_id`),
  ADD KEY `idx_date` (`date_earned`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `attendance_schedules`
--
ALTER TABLE `attendance_schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `behavior_alerts`
--
ALTER TABLE `behavior_alerts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_templates`
--
ALTER TABLE `sms_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD CONSTRAINT `admin_activity_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `fk_user_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
