-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 07, 2025 at 09:58 AM
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkTimeIn` (IN `p_lrn` VARCHAR(13), IN `p_date` DATE, IN `p_time` TIME)   BEGIN
    DECLARE v_section VARCHAR(50);
    DECLARE v_student_exists INT DEFAULT 0;
    
    -- Check if student exists
    SELECT COUNT(*), section 
    INTO v_student_exists, v_section
    FROM students 
    WHERE lrn = p_lrn;
    
    IF v_student_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Student not found';
    END IF;
    
    -- Insert or update attendance record
    INSERT INTO attendance (lrn, date, time_in, section, status, email_sent)
    VALUES (p_lrn, p_date, p_time, v_section, 'time_in', FALSE)
    ON DUPLICATE KEY UPDATE 
        time_in = p_time,
        status = 'time_in',
        updated_at = CURRENT_TIMESTAMP;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkTimeOut` (IN `p_lrn` VARCHAR(13), IN `p_date` DATE, IN `p_time` TIME)   BEGIN
    DECLARE v_rows_affected INT DEFAULT 0;
    
    -- Update existing attendance record
    UPDATE attendance 
    SET 
        time_out = p_time,
        status = 'time_out',
        updated_at = CURRENT_TIMESTAMP
    WHERE lrn = p_lrn 
      AND date = p_date;
    
    -- Check if record was found and updated
    SET v_rows_affected = ROW_COUNT();
    
    IF v_rows_affected = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'No Time In record found for this student today';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RegisterStudent` (IN `p_lrn` VARCHAR(13), IN `p_first_name` VARCHAR(50), IN `p_middle_name` VARCHAR(50), IN `p_last_name` VARCHAR(50), IN `p_gender` VARCHAR(10), IN `p_email` VARCHAR(100), IN `p_class` VARCHAR(50), IN `p_section` VARCHAR(50))   BEGIN
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
    
    -- Insert student record
    INSERT INTO students (
        lrn, first_name, middle_name, last_name, 
        gender, email, class, section
    )
    VALUES (
        p_lrn, p_first_name, p_middle_name, p_last_name,
        p_gender, p_email, p_class, p_section
    );
    
    COMMIT;
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
(5, 1, NULL, 'DELETE_SECTION', 'Deleted section: KALACHUCHI', '::1', NULL, '2025-11-07 08:51:23');

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
  `role` enum('admin','teacher','staff') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin and staff user accounts';

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'asjclaveria.attendance@gmail.com', 'System Administrator', 'admin', 1, '2025-11-07 08:06:16', '2025-11-07 06:18:21', '2025-11-07 08:06:16');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `lrn` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Student LRN',
  `date` date NOT NULL COMMENT 'Attendance date',
  `time_in` time DEFAULT NULL COMMENT 'Time In timestamp',
  `time_out` time DEFAULT NULL COMMENT 'Time Out timestamp',
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Student section at time of attendance',
  `status` enum('present','absent','time_in','time_out') COLLATE utf8mb4_unicode_ci DEFAULT 'present',
  `email_sent` tinyint(1) DEFAULT '0' COMMENT 'Email notification sent flag',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional remarks or notes',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily Time In/Out attendance records';

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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Section management for ASJ';

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
  `gender` enum('Male','Female','M','F') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Male' COMMENT 'Gender for SF2 reporting',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `class` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Grade level (e.g., Grade 12)',
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Section name (e.g., BARBERRA)',
  `qr_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'QR code data for scanning',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Student records for The Josephites';

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_daily_attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_daily_attendance_summary` (
`date` date
,`needs_time_out` decimal(23,0)
,`section` varchar(50)
,`total_records` bigint
,`with_time_in` decimal(23,0)
,`with_time_out` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_roster`
-- (See below for the actual view)
--
CREATE TABLE `v_student_roster` (
`class` varchar(50)
,`email` varchar(100)
,`full_name` varchar(104)
,`gender` enum('Male','Female','M','F')
,`id` int
,`last_attendance_date` date
,`lrn` varchar(13)
,`section` varchar(50)
);

-- --------------------------------------------------------

--
-- Structure for view `v_daily_attendance_summary`
--
DROP TABLE IF EXISTS `v_daily_attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_daily_attendance_summary`  AS SELECT `attendance`.`date` AS `date`, `attendance`.`section` AS `section`, count(0) AS `total_records`, sum((case when (`attendance`.`time_in` is not null) then 1 else 0 end)) AS `with_time_in`, sum((case when (`attendance`.`time_out` is not null) then 1 else 0 end)) AS `with_time_out`, sum((case when ((`attendance`.`time_in` is not null) and (`attendance`.`time_out` is null)) then 1 else 0 end)) AS `needs_time_out` FROM `attendance` GROUP BY `attendance`.`date`, `attendance`.`section` ORDER BY `attendance`.`date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_student_roster`
--
DROP TABLE IF EXISTS `v_student_roster`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_roster`  AS SELECT `s`.`id` AS `id`, `s`.`lrn` AS `lrn`, concat(`s`.`first_name`,' ',coalesce(concat(left(`s`.`middle_name`,1),'. '),''),`s`.`last_name`) AS `full_name`, `s`.`class` AS `class`, `s`.`section` AS `section`, `s`.`email` AS `email`, `s`.`gender` AS `gender`, (select max(`attendance`.`date`) from `attendance` where (`attendance`.`lrn` = `s`.`lrn`)) AS `last_attendance_date` FROM `students` AS `s` ORDER BY `s`.`class` ASC, `s`.`section` ASC, `s`.`last_name` ASC ;

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
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section` (`grade_level`,`section_name`),
  ADD KEY `idx_grade_section` (`grade_level`,`section_name`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lrn` (`lrn`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_lrn` (`lrn`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_class` (`class`),
  ADD KEY `idx_gender` (`gender`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
