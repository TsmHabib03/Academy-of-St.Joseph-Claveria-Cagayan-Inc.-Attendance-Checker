<?php
/**
 * Student Registration API - ADMIN ONLY
 * Returns JSON responses only
 */

// Security: Check admin authentication
session_start();

// Suppress ALL output except JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Start output buffering immediately
ob_start();

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Admin access check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied. Only administrators can register students.'
    ]);
    exit();
}

require_once __DIR__ . '/../includes/database.php';

// Clear any buffered output
ob_end_clean();
ob_start();

// Validate HTTP method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Invalid request method. POST required.'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check database connection
    if ($db === null) {
        throw new Exception('Database connection failed');
    }
    
    // Get and sanitize input
    $lrn = trim($_POST['lrn'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $class = trim($_POST['class'] ?? '');
    
    // Validate required fields
    if (empty($lrn) || empty($first_name) || empty($last_name) || empty($gender) || empty($email) || empty($class)) {
        throw new Exception('All required fields must be filled');
    }
    
    // Validate gender
    if (!in_array($gender, ['Male', 'Female'])) {
        throw new Exception('Please select a valid gender');
    }
    
    // Validate LRN format
    if (!preg_match('/^[0-9]{11,13}$/', $lrn)) {
        throw new Exception('LRN must be 11-13 digits (numeric only)');
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check for duplicates
    $check_query = "SELECT id FROM students WHERE lrn = :lrn OR email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':lrn', $lrn, PDO::PARAM_STR);
    $check_stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        throw new Exception('LRN or email already exists in the system');
    }
    
    // Auto-create section if it doesn't exist
    if (!empty($class)) {
        try {
            // Check if section already exists (case-insensitive)
            $checkSectionStmt = $db->prepare("SELECT id, section_name FROM sections WHERE LOWER(section_name) = LOWER(:section_name)");
            $checkSectionStmt->bindParam(':section_name', $class, PDO::PARAM_STR);
            $checkSectionStmt->execute();
            
            if ($checkSectionStmt->rowCount() === 0) {
                // Extract grade level from section name (e.g., "12-BARBERRA" -> "12")
                $gradeLevel = '';
                if (preg_match('/^(\d{1,2})[-_\s]/', $class, $matches)) {
                    $gradeLevel = $matches[1];
                }
                
                // Get current school year
                $currentYear = date('Y');
                $nextYear = $currentYear + 1;
                $schoolYear = $currentYear . '-' . $nextYear;
                
                // Insert new section with error handling
                try {
                    $insertSectionStmt = $db->prepare("
                        INSERT INTO sections (section_name, grade_level, school_year, is_active, created_at) 
                        VALUES (:section_name, :grade_level, :school_year, 1, NOW())
                    ");
                    $insertSectionStmt->bindParam(':section_name', $class, PDO::PARAM_STR);
                    $insertSectionStmt->bindParam(':grade_level', $gradeLevel, PDO::PARAM_STR);
                    $insertSectionStmt->bindParam(':school_year', $schoolYear, PDO::PARAM_STR);
                    
                    if ($insertSectionStmt->execute()) {
                        error_log("Section created successfully in API: $class (Grade: $gradeLevel)");
                    } else {
                        error_log("Failed to create section in API: $class");
                    }
                } catch (PDOException $sectionError) {
                    // Log but don't fail - section might already exist from concurrent request
                    error_log("Section creation error (likely duplicate): " . $sectionError->getMessage());
                }
            } else {
                error_log("Section already exists in API: $class");
            }
        } catch (Exception $sectionCheckError) {
            error_log("Section check error in API: " . $sectionCheckError->getMessage());
            // Continue - don't fail student registration if section check fails
        }
    }
    
    // Generate QR code data
    $qr_data = $lrn . '|' . time();
    
    // Insert student
    $query = "INSERT INTO students (lrn, first_name, last_name, middle_name, gender, email, class, qr_code) 
              VALUES (:lrn, :first_name, :last_name, :middle_name, :gender, :email, :class, :qr_code)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lrn', $lrn, PDO::PARAM_STR);
    $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
    $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
    $stmt->bindParam(':middle_name', $middle_name, PDO::PARAM_STR);
    $stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':class', $class, PDO::PARAM_STR);
    $stmt->bindParam(':qr_code', $qr_data, PDO::PARAM_STR);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to register student. Please try again.');
    }
    
    // Generate QR code image URL
    $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
    $qr_code_html = '<img src="' . htmlspecialchars($qr_code_url, ENT_QUOTES, 'UTF-8') . '" alt="QR Code for LRN ' . htmlspecialchars($lrn, ENT_QUOTES, 'UTF-8') . '">';
    
    // Clear buffer and send success response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Student registered successfully!',
        'qr_code' => $qr_code_html,
        'lrn' => $lrn,
        'student_name' => $first_name . ' ' . $last_name
    ]);
    
} catch (PDOException $e) {
    ob_end_clean();
    error_log("Registration DB Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    ob_end_clean();
    error_log("Registration Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

exit;
