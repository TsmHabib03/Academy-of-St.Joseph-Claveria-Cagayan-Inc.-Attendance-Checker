<?php
/**
 * Database Connection Test
 */

header('Content-Type: application/json');

try {
    // Include database configuration
    require_once '../config/db_config.php';

    // Check if admin_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    $tableExists = $stmt->rowCount() > 0;

    // Check if reset columns exist
    $columnsExist = false;
    if ($tableExists) {
        $stmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'reset_token%'");
        $columnsExist = $stmt->rowCount() >= 2;
    }

    // Count admins
    $adminCount = 0;
    if ($tableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $adminCount = $result['count'];
    }

    echo json_encode([
        'success' => true,
        'table_exists' => $tableExists,
        'columns_exist' => $columnsExist,
        'admin_count' => $adminCount,
        'message' => 'Database connection successful'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
