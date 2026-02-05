<?php
require_once 'includes/database.php';
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = file_get_contents('database/migration_add_schedule_columns.sql');
    $db->exec($sql);
    echo "Successfully added schedule columns to sections table.\n";
} catch (Exception $e) {
    echo "Error applying migration: " . $e->getMessage() . "\n";
}
?>
