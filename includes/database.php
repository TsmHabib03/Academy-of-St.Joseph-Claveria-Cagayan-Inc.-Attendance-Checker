<?php
// Set PHP timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Load database configuration from config file if it exists
$config_file = __DIR__ . '/../config/db_config.php';
if (file_exists($config_file)) {
    require_once $config_file;
}

// Database configuration
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    
    public function __construct() {
        // Try to get from environment variables (set by config file)
        // If not set, use default values
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'attendance_system';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: 'muning0328';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Set MySQL timezone to Philippines
            $this->conn->exec("SET time_zone = '+08:00'");
            
        } catch(PDOException $exception) {
            // Log error instead of echoing it
            error_log("Database connection error: " . $exception->getMessage());
            return null;
        }
        return $this->conn;
    }
}
?>
