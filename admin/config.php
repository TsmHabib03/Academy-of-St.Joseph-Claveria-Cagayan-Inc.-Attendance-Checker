<?php
// Admin Configuration File
// This file contains configuration settings specific to the admin area

// Start session management
session_start();

// Include main database configuration and initialize connection
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/schema_validator.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Check if database connection failed
if (!$pdo) {
    die("Database connection failed. Please check your database configuration.");
}

// Startup schema check for admin area: ensure core tables exist
$admin_required = [
    'tables' => ['admin_users', 'students', 'attendance', 'sections']
];
$missing = validate_schema_requirements($pdo, $admin_required);
if (!empty($missing)) {
    // Stop admin pages and show a concise message
    die('Database schema incomplete. Missing: ' . implode(', ', $missing) . '. Please run migrations.');
}

// Admin-specific configuration
define('ADMIN_TIMEOUT', 3600); // 1 hour session timeout in seconds
define('ADMIN_PER_PAGE', 25);  // Default records per page for admin listings
define('ADMIN_TITLE', 'Admin Dashboard - Attendance System');

/**
 * Check if user is logged in as admin
 * Redirects to login page if not authenticated
 */
function requireAdmin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit();
    }
    
    // Check session timeout
    if (isset($_SESSION['admin_last_activity']) && 
        (time() - $_SESSION['admin_last_activity']) > ADMIN_TIMEOUT) {
        session_destroy();
        header('Location: login.php?timeout=1');
        exit();
    }
    
    // Update last activity time
    $_SESSION['admin_last_activity'] = time();
}

/**
 * Get current admin user information
 */
function getCurrentAdmin() {
    return [
        'id' => $_SESSION['admin_id'] ?? 0,
        'username' => $_SESSION['admin_username'] ?? '',
        'email' => $_SESSION['admin_email'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'admin'
    ];
}

/**
 * Generate CSRF token for forms
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input for display
 */
function sanitizeOutput($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Format Philippine time
 */
function formatPhilippineTime($timestamp = null) {
    if ($timestamp === null) {
        $timestamp = time();
    }
    $timezone = new DateTimeZone('Asia/Manila');
    $date = new DateTime('@' . $timestamp);
    $date->setTimezone($timezone);
    return $date->format('Y-m-d H:i:s');
}

/**
 * Log admin activity
 */
function logAdminActivity($action, $details = '') {
    global $pdo;
    
    try {
        $admin = getCurrentAdmin();
        
        // Check if admin_activity_log table exists, if not create it
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_activity_log'");
        if ($stmt->rowCount() == 0) {
            $createTable = "
                CREATE TABLE admin_activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT,
                    action VARCHAR(100) NOT NULL,
                    details TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_admin_id (admin_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB COMMENT='Admin activity log for audit trail'
            ";
            $pdo->exec($createTable);
        }
        
        $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $admin['id'],
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Silently fail - logging shouldn't break the application
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}
?>
