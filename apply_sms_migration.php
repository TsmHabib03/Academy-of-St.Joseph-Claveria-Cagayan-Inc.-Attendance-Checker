<?php
require_once 'includes/database.php';
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = file_get_contents('database/create_sms_logs.sql');
    $db->exec($sql);
    echo "Successfully created sms_logs table.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
