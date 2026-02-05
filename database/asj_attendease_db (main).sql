-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 05, 2026 at 08:30 AM
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
(43, 1, NULL, 'ADD_TEACHER', 'Added teacher: Habib Jaudian (ID: 1)', '::1', NULL, '2026-02-03 11:38:04');

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
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'asjclaveria.attendance@gmail.com', 'System Administrator', 'admin', 1, '2026-02-03 11:34:38', '2025-11-07 06:18:21', '2026-02-03 11:34:38', NULL, NULL);

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
  `is_late_afternoon` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily Time In/Out attendance records';

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `lrn`, `date`, `morning_time_in`, `morning_time_out`, `section`, `status`, `email_sent`, `remarks`, `created_at`, `updated_at`, `afternoon_time_in`, `afternoon_time_out`, `is_late_morning`, `is_late_afternoon`) VALUES
(8, '136511140086', '2026-01-30', '08:42:19', '08:43:11', 'Grade 12', 'present', 0, NULL, '2026-01-30 00:42:19', '2026-01-30 00:43:11', NULL, NULL, 0, 0),
(9, '136544140602', '2026-01-30', '08:45:57', NULL, 'Grade 12', 'present', 0, NULL, '2026-01-30 00:45:57', '2026-01-30 00:45:57', NULL, NULL, 0, 0),
(10, '136514240419', '2026-02-03', '18:48:03', '19:32:49', 'Grade 7', 'present', 0, NULL, '2026-02-03 10:48:03', '2026-02-03 11:32:49', NULL, NULL, 0, 0);

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
(1, 'Default Schedule', NULL, NULL, '07:00:00', '12:00:00', '07:30:00', '13:00:00', '21:30:00', '13:30:00', 1, 1, '2026-02-03 07:58:57', '2026-02-03 10:53:15');

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
(5, 'Consistent Achiever', '100% attendance for the week', 'fa-trophy', '#9C27B0', 'consistent', 5, 'weekly', 'student,teacher', 20, 1, '2026-02-03 07:58:57', '2026-02-03 07:58:57');

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
  `pm_end_time` time DEFAULT '17:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Section management for ASJ';

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `grade_level`, `section_name`, `adviser`, `school_year`, `is_active`, `created_at`, `updated_at`, `am_start_time`, `am_late_threshold`, `am_end_time`, `pm_start_time`, `pm_late_threshold`, `pm_end_time`) VALUES
(2, '7', 'KALACHUCHI', '', '2025-2026', 1, '2025-11-08 07:09:10', '2026-02-03 10:50:27', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00'),
(3, '7', 'Integrity', '', '2025-2026', 1, '2025-11-08 08:39:56', '2025-11-08 08:39:56', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00'),
(4, '8', 'Excellence', '', '2025-2026', 1, '2025-11-08 08:40:24', '2025-11-08 08:40:24', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00'),
(5, '9', 'Evangalization', '', '2025-2026', 1, '2025-11-08 08:40:39', '2025-11-08 08:40:39', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00'),
(6, '10', 'Social Responsibility', '', '2025-2026', 1, '2025-11-08 08:40:54', '2025-11-08 08:40:54', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00'),
(7, '11', 'Peace', '', '2025-2026', 1, '2025-11-08 08:41:06', '2025-11-08 08:41:06', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00'),
(8, '12', 'Justice', '', '2025-2026', 1, '2025-11-08 08:41:23', '2025-11-08 08:41:23', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00'),
(9, '12', 'BARBERA', NULL, '2026-2027', 1, '2026-01-30 00:41:27', '2026-01-30 00:41:27', '07:30:00', '08:00:00', '12:00:00', '13:00:00', '13:30:00', '17:00:00');

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
(1, 'student_late', 'late', 'LATE NOTICE: {name} arrived late at {time} on {date}. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(2, 'student_absent', 'absent', 'ABSENT NOTICE: {name} was marked absent on {date}. Please contact the school if this is incorrect. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(3, 'student_time_in', 'time_in', '{name} has arrived at school at {time} on {date}. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(4, 'student_time_out', 'time_out', '{name} has left school at {time} on {date}. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(5, 'behavior_alert', 'alert', 'ATTENTION: {name} has been flagged for {status}. Please contact the guidance office. - ASJ Attendance System', 1, '2026-02-03 07:58:57', '2026-02-03 09:47:40');

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
(10, '136511140086', 'Mark Gian', 'Aquino', 'Jacob', 'Male', 'markgianjacob52', 'Grade 12', 'BARBERA', 'uploads/qrcodes/student_10.png', '2026-01-30 00:41:27', ''),
(11, '136544140602', 'John Carlo', 'Moises', 'Miode', 'Male', 'miodecarlo@gmai', 'Grade 12', 'BARBERA', 'uploads/qrcodes/student_11.png', '2026-01-30 00:44:32', ''),
(13, '136514240419', 'Zach Reihge', 'Dalisay', 'Jaudian', 'Male', 'welie10jaudian@gmail.com', 'Grade 7', 'KALACHUCHI', 'uploads/qrcodes/student_13.png', '2026-02-03 10:33:58', '09997670753');

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
(1, 'morning_session_start', '06:00', 'string', 'attendance', 'Morning session start time', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(2, 'morning_session_end', '12:00', 'string', 'attendance', 'Morning session end time', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(3, 'afternoon_session_start', '12:00', 'string', 'attendance', 'Afternoon session start time', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(4, 'afternoon_session_end', '18:00', 'string', 'attendance', 'Afternoon session end time', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(5, 'sms_enabled', '0', 'boolean', 'notifications', 'Enable SMS notifications', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(6, 'sms_provider', 'semaphore', 'string', 'notifications', 'SMS gateway provider', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(7, 'sms_on_late', '1', 'boolean', 'notifications', 'Send SMS on late arrival', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(8, 'sms_on_absent', '1', 'boolean', 'notifications', 'Send SMS on absence', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(9, 'behavior_monitoring_enabled', '1', 'boolean', 'monitoring', 'Enable behavior monitoring', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(10, 'late_threshold_weekly', '3', 'number', 'monitoring', 'Late occurrences per week to trigger alert', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(11, 'absence_threshold_consecutive', '2', 'number', 'monitoring', 'Consecutive absences to trigger alert', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(12, 'badges_enabled', '1', 'boolean', 'badges', 'Enable badge system', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(13, 'badge_notifications', '1', 'boolean', 'badges', 'Notify users when badges are earned', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(14, 'school_name', 'Academy of St. Joseph Claveria, Cagayan Inc.', 'string', 'school', 'School name', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40'),
(15, 'school_year', '2025-2026', 'string', 'school', 'Current school year', 1, NULL, '2026-02-03 07:58:57', '2026-02-03 09:47:40');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int NOT NULL,
  `employee_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique Employee ID',
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teacher records for AttendEase v3.0';

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `employee_id`, `first_name`, `middle_name`, `last_name`, `sex`, `mobile_number`, `email`, `department`, `position`, `qr_code`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '1', 'Habib', 'Dalisay', 'Jaudian', 'Male', '09997670753', 'jaudianhabib879@gmail.com', 'Science', 'Subject Teacher', 'uploads/qrcodes/teacher_1.png', 1, '2026-02-03 11:38:04', '2026-02-03 11:38:04');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_attendance`
--

CREATE TABLE `teacher_attendance` (
  `id` int NOT NULL,
  `employee_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teacher Employee ID',
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teacher attendance records';

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
`badge_count` bigint
,`department` varchar(100)
,`employee_id` varchar(20)
,`full_name` varchar(104)
,`id` int
,`is_active` tinyint(1)
,`last_attendance_date` date
,`mobile_number` varchar(15)
,`position` varchar(100)
,`sex` enum('Male','Female')
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
  ADD KEY `idx_email_sent` (`email_sent`);

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
  ADD KEY `idx_active` (`is_active`);

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
  ADD KEY `idx_gender` (`sex`);

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
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_daily` (`employee_id`,`date`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `attendance_schedules`
--
ALTER TABLE `attendance_schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `behavior_alerts`
--
ALTER TABLE `behavior_alerts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
-- Constraints for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD CONSTRAINT `fk_teacher_attendance` FOREIGN KEY (`employee_id`) REFERENCES `teachers` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `fk_user_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
