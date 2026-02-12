<?php
/**
 * Database Configuration File
 *
 * INSTRUCTIONS:
 * Use environment variables or config/secrets.local.php for credentials.
 * secrets.local.php is ignored by git to avoid leaking passwords.
 */

$secrets = [];
$secretsPath = __DIR__ . '/secrets.local.php';
if (file_exists($secretsPath)) {
    $loaded = require $secretsPath;
    if (is_array($loaded)) {
        $secrets = $loaded;
    }
}

$getConfig = static function (string $key, $default = '') use ($secrets) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $secrets[$key] ?? $default;
    }
    return $value;
};

// Database configuration
define('DB_HOST', $getConfig('DB_HOST', 'localhost'));
define('DB_USER', $getConfig('DB_USER', 'root')); // Default username should be root
define('DB_PASS', $getConfig('DB_PASS', '')); // Set in secrets.local.php or env
define('DB_NAME', $getConfig('DB_NAME', 'asj_attendease_db')); // Database name
define('DB_CHARSET', $getConfig('DB_CHARSET', 'utf8mb4'));

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
