-- ================================================================================
-- ATTENDANCE SYSTEM - CORRECTED MASTER SQL SCRIPT
-- ================================================================================
-- Updated to match the current application's section-based Time In/Out system
-- Author: System Audit - January 2025
-- Changes: Removed schedule system, added sections, time_in/time_out tracking
-- ================================================================================

-- =============================================================================
-- SECTION 1: DATABASE SETUP
-- =============================================================================
-- Complete database installation for the Attendance System
-- WARNING: This will DELETE all existing data in the attendance_system database

-- 1.1 DATABASE SETUP
-- Drop existing database if it exists (USE WITH CAUTION!)
DROP DATABASE IF EXISTS attendance_system_new;

-- Create new database with proper charset
CREATE DATABASE attendance_system
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE attendance_system;

-- ================================================================================
-- CORE TABLES
-- ================================================================================

-- Students table with section and gender support
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(13) UNIQUE NOT NULL COMMENT 'Learner Reference Number',
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) DEFAULT NULL COMMENT 'Middle name for DepEd forms',
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'M', 'F') NOT NULL DEFAULT 'Male' COMMENT 'Gender for SF2 reporting',
    email VARCHAR(100) UNIQUE NOT NULL,
    class VARCHAR(50) NOT NULL COMMENT 'Grade level (e.g., Grade 12)',
    section VARCHAR(50) DEFAULT NULL COMMENT 'Section name (e.g., BARBERRA)',
    qr_code VARCHAR(255) COMMENT 'QR code data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_lrn (lrn),
    INDEX idx_section (section),
    INDEX idx_class (class),
    INDEX idx_gender (gender)
) ENGINE=InnoDB COMMENT='Students with section-based tracking';

-- Sections table for section management
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_level VARCHAR(20) NOT NULL COMMENT 'Grade level (e.g., Grade 12)',
    section_name VARCHAR(50) NOT NULL COMMENT 'Section name (e.g., BARBERRA)',
    adviser VARCHAR(100) DEFAULT NULL COMMENT 'Class adviser name',
    room VARCHAR(50) DEFAULT NULL COMMENT 'Room assignment',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_section (grade_level, section_name),
    INDEX idx_grade_section (grade_level, section_name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB COMMENT='Section management';

-- Attendance with Time In/Out tracking
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(13) NOT NULL COMMENT 'Student LRN',
    date DATE NOT NULL COMMENT 'Attendance date',
    time_in TIME DEFAULT NULL COMMENT 'Time In timestamp',
    time_out TIME DEFAULT NULL COMMENT 'Time Out timestamp',
    section VARCHAR(50) DEFAULT NULL COMMENT 'Student section',
    status ENUM('present', 'absent', 'time_in', 'time_out') DEFAULT 'present',
    email_sent BOOLEAN DEFAULT FALSE COMMENT 'Email notification status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (lrn) REFERENCES students(lrn) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Prevent duplicate daily attendance per student
    UNIQUE KEY unique_daily_attendance (lrn, date),
    
    INDEX idx_date_section (date, section),
    INDEX idx_lrn_date (lrn, date),
    INDEX idx_status (status),
    INDEX idx_email_sent (email_sent)
) ENGINE=InnoDB COMMENT='Daily Time In/Out attendance records';

-- Admin users
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Hashed password',
    email VARCHAR(100) UNIQUE,
    role ENUM('admin', 'teacher', 'staff') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_active_users (is_active, role)
) ENGINE=InnoDB;

-- Admin activity log (optional, created dynamically by app)
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ================================================================================
-- INITIAL DATA
-- ================================================================================

-- Default admin account (username: admin, password: admin123)
INSERT INTO admin_users (username, password, email, role) VALUES 
('admin', MD5('admin123'), 'admin@school.com', 'admin');

-- Sample sections
INSERT INTO sections (grade_level, section_name, adviser) VALUES
('Grade 12', 'BARBERRA', 'Ms. Teacher'),
('Grade 11', 'A', 'Mr. Adviser'),
('Grade 10', 'B', 'Mrs. Guide');

-- Sample students
INSERT INTO students (lrn, first_name, middle_name, last_name, gender, email, class, section) VALUES
('123456789012', 'John', 'M', 'Doe', 'Male', 'john.doe@school.com', 'Grade 12', 'BARBERRA'),
('123456789013', 'Jane', 'A', 'Smith', 'Female', 'jane.smith@school.com', 'Grade 12', 'BARBERRA'),
('123456789014', 'Mike', 'B', 'Johnson', 'Male', 'mike.johnson@school.com', 'Grade 12', 'BARBERRA');

-- ================================================================================
-- STORED PROCEDURES (Updated for new schema)
-- ================================================================================

DELIMITER //

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
    
    IF p_lrn NOT REGEXP '^[0-9]{11,13}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid LRN format';
    END IF;
    
    INSERT INTO students (lrn, first_name, middle_name, last_name, gender, email, class, section)
    VALUES (p_lrn, p_first_name, p_middle_name, p_last_name, p_gender, p_email, p_class, p_section);
    
    COMMIT;
END //

CREATE PROCEDURE MarkTimeIn(
    IN p_lrn VARCHAR(13),
    IN p_date DATE,
    IN p_time TIME
)
BEGIN
    DECLARE v_section VARCHAR(50);
    
    SELECT section INTO v_section FROM students WHERE lrn = p_lrn;
    
    IF v_section IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student not found';
    END IF;
    
    INSERT INTO attendance (lrn, date, time_in, section, status, email_sent)
    VALUES (p_lrn, p_date, p_time, v_section, 'time_in', FALSE)
    ON DUPLICATE KEY UPDATE 
        time_in = p_time,
        status = 'time_in',
        updated_at = CURRENT_TIMESTAMP;
END //

CREATE PROCEDURE MarkTimeOut(
    IN p_lrn VARCHAR(13),
    IN p_date DATE,
    IN p_time TIME
)
BEGIN
    UPDATE attendance 
    SET time_out = p_time,
        status = 'time_out',
        updated_at = CURRENT_TIMESTAMP
    WHERE lrn = p_lrn 
      AND date = p_date;
    
    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No Time In record found';
    END IF;
END //

DELIMITER ;

-- ================================================================================
-- VERIFICATION QUERIES
-- ================================================================================

SHOW TABLES;
DESCRIBE students;
DESCRIBE sections;
DESCRIBE attendance;
SELECT COUNT(*) as student_count FROM students;
SELECT COUNT(*) as section_count FROM sections;

-- ================================================================================
-- MIGRATION NOTES
-- ================================================================================
-- If upgrading from old schema:
-- 1. Backup your database first!
-- 2. Export existing student data
-- 3. Run this script to recreate tables
-- 4. Re-import student data (map old columns to new ones)
-- 5. Test thoroughly before going live
-- ================================================================================
