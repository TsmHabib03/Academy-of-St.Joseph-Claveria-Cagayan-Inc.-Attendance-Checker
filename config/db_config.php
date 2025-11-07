<?php
/**
 * Database Configuration File
 * 
 * INSTRUCTIONS:
 * Change the DB_NAME below to match your actual database name.
 * This will be used by includes/database.php for all connections.
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'muning0328');
define('DB_NAME', 'asj_attendease_db'); // ← NEW DATABASE NAME
define('DB_CHARSET', 'utf8mb4');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Set this as environment variable for database.php to use
putenv('DB_NAME=' . DB_NAME);
putenv('DB_HOST=' . DB_HOST);
putenv('DB_USER=' . DB_USER);
putenv('DB_PASS=' . DB_PASS);

// Create PDO connection for APIs that need it
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Don't expose database errors in production
    error_log("Database connection error: " . $e->getMessage());
    if (php_sapi_name() === 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) {
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed. Please contact administrator.'
        ]));
    }
}

?>
