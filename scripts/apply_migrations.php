<?php
/**
 * Simple migration runner for local use. Executes listed SQL files against DB.
 * Usage: php apply_migrations.php
 */
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/database.php';

$files = [
    __DIR__ . '/../database/ensure_v3_schema.sql',
    __DIR__ . '/../database/migration_v3.sql'
];

$database = new Database();
$pdo = $database->getConnection();
if ($pdo === null) {
    echo "Database connection failed\n";
    exit(1);
}

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "Migration file not found: $file\n";
        continue;
    }
    echo "Applying $file...\n";
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        echo "Applied: $file\n";
    } catch (PDOException $e) {
        echo "Error applying $file: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
