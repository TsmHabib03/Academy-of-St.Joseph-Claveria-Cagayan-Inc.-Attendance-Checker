-- ================================================================================
-- ASJ ATTENDEASE - MASTER DATABASE INSTALLATION SCRIPT
-- ================================================================================
-- Academy of St. Joseph, Claveria Cagayan Inc.
-- AttendEase - Smart Attendance Management System
-- Author: System Setup - January 2025
-- Database: Clean installation with no sample data
-- ================================================================================

-- =============================================================================
-- SECTION 1: DATABASE SETUP
-- =============================================================================
-- ⚠️ WARNING: This will DELETE the existing database!
-- Only run this on a fresh installation or after backing up your data!

-- Drop existing database if it exists
DROP DATABASE IF EXISTS asj_attendease_db;

-- Create new database with proper charset for international characters
CREATE DATABASE asj_attendease_db
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Use the new database
USE asj_attendease_db;

-- =============================================================================
-- SECTION 2: CORE TABLES
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: students
-- Purpose: Store student information with LRN and section assignment
-- -----------------------------------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(13) UNIQUE NOT NULL COMMENT 'Learner Reference Number (11-13 digits)',
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) DEFAULT NULL COMMENT 'Middle name for DepEd forms',
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'M', 'F') NOT NULL DEFAULT 'Male' COMMENT 'Gender for SF2 reporting',
    email VARCHAR(100) UNIQUE NOT NULL,
    class VARCHAR(50) NOT NULL COMMENT 'Grade level (e.g., Grade 12)',
    section VARCHAR(50) DEFAULT NULL COMMENT 'Section name (e.g., BARBERRA)',
    qr_code VARCHAR(255) COMMENT 'QR code data for scanning',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_lrn (lrn),
    INDEX idx_section (section),
    INDEX idx_class (class),
    INDEX idx_gender (gender)
) ENGINE=InnoDB 
COMMENT='Student records for The Josephites';

-- -----------------------------------------------------------------------------
-- Table: sections
-- Purpose: Manage class sections by grade level
-- -----------------------------------------------------------------------------
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_level VARCHAR(20) NOT NULL COMMENT 'Grade level (e.g., Grade 12)',
    section_name VARCHAR(50) NOT NULL COMMENT 'Section name (e.g., BARBERRA)',
    adviser VARCHAR(100) DEFAULT NULL COMMENT 'Class adviser name',
    room VARCHAR(50) DEFAULT NULL COMMENT 'Room assignment',
    school_year VARCHAR(20) DEFAULT NULL COMMENT 'School year (e.g., 2024-2025)',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Active/inactive status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Ensure unique grade-section combinations
    UNIQUE KEY unique_section (grade_level, section_name),
    
    -- Indexes
    INDEX idx_grade_section (grade_level, section_name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB 
COMMENT='Section management for ASJ';

-- -----------------------------------------------------------------------------
-- Table: attendance
-- Purpose: Track daily Time In and Time Out for each student
-- -----------------------------------------------------------------------------
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(13) NOT NULL COMMENT 'Student LRN',
    date DATE NOT NULL COMMENT 'Attendance date',
    time_in TIME DEFAULT NULL COMMENT 'Time In timestamp',
    time_out TIME DEFAULT NULL COMMENT 'Time Out timestamp',
    section VARCHAR(50) DEFAULT NULL COMMENT 'Student section at time of attendance',
    status ENUM('present', 'absent', 'time_in', 'time_out') DEFAULT 'present',
    email_sent BOOLEAN DEFAULT FALSE COMMENT 'Email notification sent flag',
    remarks TEXT DEFAULT NULL COMMENT 'Optional remarks or notes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key to students table
    FOREIGN KEY (lrn) REFERENCES students(lrn) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    
    -- Prevent duplicate daily attendance per student
    UNIQUE KEY unique_daily_attendance (lrn, date),
    
    -- Indexes for common queries
    INDEX idx_date_section (date, section),
    INDEX idx_lrn_date (lrn, date),
    INDEX idx_status (status),
    INDEX idx_email_sent (email_sent)
) ENGINE=InnoDB 
COMMENT='Daily Time In/Out attendance records';

-- -----------------------------------------------------------------------------
-- Table: admin_users
-- Purpose: Store admin/staff credentials for system access
-- -----------------------------------------------------------------------------
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Hashed password (MD5 or bcrypt)',
    email VARCHAR(100) UNIQUE,
    full_name VARCHAR(100) DEFAULT NULL COMMENT 'Admin full name',
    role ENUM('admin', 'teacher', 'staff') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_username (username),
    INDEX idx_active_users (is_active, role)
) ENGINE=InnoDB
COMMENT='Admin and staff user accounts';

-- -----------------------------------------------------------------------------
-- Table: admin_activity_log
-- Purpose: Track admin actions for security and auditing
-- -----------------------------------------------------------------------------
CREATE TABLE admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    username VARCHAR(50) DEFAULT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'Action performed',
    details TEXT DEFAULT NULL COMMENT 'Action details',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address',
    user_agent TEXT DEFAULT NULL COMMENT 'Browser user agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key (optional, allows NULL for deleted admins)
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) 
        ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB
COMMENT='Admin activity audit log';

-- ================================================================================
-- SECTION 3: DEFAULT ADMIN ACCOUNT
-- ================================================================================
-- Create default admin account for initial login
-- Username: admin
-- Password: admin123
-- ⚠️ IMPORTANT: Change this password immediately after first login!

INSERT INTO admin_users (username, password, email, full_name, role, is_active) 
VALUES (
    'admin',
    MD5('admin123'),  -- Change to PASSWORD() or bcrypt in production!
    'admin@asj-claveria.edu.ph',
    'System Administrator',
    'admin',
    TRUE
);

-- ================================================================================
-- SECTION 4: STORED PROCEDURES
-- ================================================================================

DELIMITER //

-- -----------------------------------------------------------------------------
-- Procedure: RegisterStudent
-- Purpose: Register a new student with validation
-- -----------------------------------------------------------------------------
CREATE PROCEDURE RegisterStudent(
    IN p_lrn VARCHAR(13),
    IN p_first_name VARCHAR(50),
    IN p_middle_name VARCHAR(50),
    IN p_last_name VARCHAR(50),
    IN p_gender VARCHAR(10),
    IN p_email VARCHAR(100),
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
END //

-- -----------------------------------------------------------------------------
-- Procedure: MarkTimeIn
-- Purpose: Record student Time In
-- -----------------------------------------------------------------------------
CREATE PROCEDURE MarkTimeIn(
    IN p_lrn VARCHAR(13),
    IN p_date DATE,
    IN p_time TIME
)
BEGIN
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
END //

-- -----------------------------------------------------------------------------
-- Procedure: MarkTimeOut
-- Purpose: Record student Time Out
-- -----------------------------------------------------------------------------
CREATE PROCEDURE MarkTimeOut(
    IN p_lrn VARCHAR(13),
    IN p_date DATE,
    IN p_time TIME
)
BEGIN
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
END //

-- -----------------------------------------------------------------------------
-- Procedure: GetStudentAttendance
-- Purpose: Get attendance records for a student in a date range
-- -----------------------------------------------------------------------------
CREATE PROCEDURE GetStudentAttendance(
    IN p_lrn VARCHAR(13),
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
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
END //

DELIMITER ;

-- ================================================================================
-- SECTION 5: VIEWS (Optional but useful)
-- ================================================================================

-- Daily attendance summary view
CREATE VIEW v_daily_attendance_summary AS
SELECT 
    date,
    section,
    COUNT(*) as total_records,
    SUM(CASE WHEN time_in IS NOT NULL THEN 1 ELSE 0 END) as with_time_in,
    SUM(CASE WHEN time_out IS NOT NULL THEN 1 ELSE 0 END) as with_time_out,
    SUM(CASE WHEN time_in IS NOT NULL AND time_out IS NULL THEN 1 ELSE 0 END) as needs_time_out
FROM attendance
GROUP BY date, section
ORDER BY date DESC;

-- Student roster with latest attendance
CREATE VIEW v_student_roster AS
SELECT 
    s.id,
    s.lrn,
    CONCAT(s.first_name, ' ', 
           COALESCE(CONCAT(LEFT(s.middle_name, 1), '. '), ''), 
           s.last_name) as full_name,
    s.class,
    s.section,
    s.email,
    s.gender,
    (SELECT MAX(date) FROM attendance WHERE lrn = s.lrn) as last_attendance_date
FROM students s
ORDER BY s.class, s.section, s.last_name;

-- ================================================================================
-- SECTION 6: VERIFICATION QUERIES
-- ================================================================================

-- Show all created tables
SHOW TABLES;

-- Show table structures
DESCRIBE students;
DESCRIBE sections;
DESCRIBE attendance;
DESCRIBE admin_users;

-- Verify default admin account
SELECT id, username, email, role, is_active 
FROM admin_users 
WHERE username = 'admin';

-- ================================================================================
-- SECTION 7: INSTALLATION COMPLETE
-- ================================================================================
-- ✅ Database 'asj_attendease_db' has been created successfully!
-- ✅ All tables, procedures, and views are ready
-- ✅ Default admin account created (username: admin, password: admin123)
--
-- 📝 NEXT STEPS:
-- 1. Update your db_config.php with the new database name
-- 2. Login to admin panel and change the default password
-- 3. Import your existing student data (if any)
-- 4. Create sections for the current school year
-- 5. Test the attendance marking system
--
-- 🔒 SECURITY REMINDERS:
-- - Change the default admin password immediately
-- - Use strong passwords for all admin accounts
-- - Regularly backup your database
-- - Keep your PHP and MySQL versions updated
-- ================================================================================
