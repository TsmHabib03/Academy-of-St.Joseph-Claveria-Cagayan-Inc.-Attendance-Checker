<?php
/**
 * Database Configuration File
 * 
 * INSTRUCTIONS:
 * Change the DB_NAME below to match your actual database name.
 * This will be used by includes/database.php for all connections.
 */

// Your database name - CHANGE THIS to your actual database
define('DB_NAME', 'attendance_system');

// Database credentials (usually these stay the same)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'muning0328');

// Set this as environment variable for database.php to use
putenv('DB_NAME=' . DB_NAME);
putenv('DB_HOST=' . DB_HOST);
putenv('DB_USER=' . DB_USER);
putenv('DB_PASS=' . DB_PASS);

?>
