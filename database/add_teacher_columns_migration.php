<?php
/**
 * Idempotent migration: add `shift` to `teachers` and `department`,`shift` to `teacher_attendance`.
 * Usage: Run on a test DB first. From project root:
 *   php database/add_teacher_columns_migration.php
 */

date_default_timezone_set('Asia/Manila');

// Try to reuse existing DB config if present
$configPath = __DIR__ . '/../config/db_config.php';
if (!file_exists($configPath)) {
    echo "Missing config/db_config.php. Create or run with DB credentials manually.\n";
    exit(1);
}

require_once $configPath;

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "Expected $pdo PDO instance from config/db_config.php.\n";
    exit(1);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!$dbName) {
        throw new RuntimeException('Unable to determine current database (SELECT DATABASE()).');
    }

    $toAdd = [
        ['table' => 'teachers', 'column' => 'shift', 'sql' => "ALTER TABLE `teachers` ADD COLUMN `shift` VARCHAR(16) DEFAULT 'morning' COMMENT 'Teacher shift: morning|afternoon|both'"],
        ['table' => 'teacher_attendance', 'column' => 'department', 'sql' => "ALTER TABLE `teacher_attendance` ADD COLUMN `department` VARCHAR(64) DEFAULT ''"],
        ['table' => 'teacher_attendance', 'column' => 'shift', 'sql' => "ALTER TABLE `teacher_attendance` ADD COLUMN `shift` VARCHAR(16) DEFAULT 'morning'"]
    ];

    foreach ($toAdd as $item) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :col");
        $stmt->execute([':schema' => $dbName, ':table' => $item['table'], ':col' => $item['column']]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            echo "Skipping: {$item['table']}.{$item['column']} already exists.\n";
            continue;
        }

        echo "Adding column {$item['column']} to table {$item['table']}...\n";
        $pdo->exec($item['sql']);
        echo "Added: {$item['table']}.{$item['column']}.\n";
    }

    echo "Migration complete. Verify schema or run application tests.\n";
    exit(0);

} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
    exit(2);
} catch (Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(3);
}
