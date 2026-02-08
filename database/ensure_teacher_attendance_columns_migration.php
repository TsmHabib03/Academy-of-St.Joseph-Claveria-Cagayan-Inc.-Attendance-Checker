<?php
/**
 * Idempotent migration: ensure `teacher_attendance` table exists and has
 * the required columns used by the application.
 *
 * Usage: php ensure_teacher_attendance_columns_migration.php
 */

require_once __DIR__ . '/../config/db_config.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "Error: PDO \$pdo not available from config.\n";
    exit(1);
}

$table = 'teacher_attendance';

function tableExists(PDO $pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
    $stmt->execute([':table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    if (!tableExists($pdo, $table)) {
        echo "Table '$table' does not exist — creating...\n";
        $create = "CREATE TABLE `$table` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `date` DATE NOT NULL UNIQUE,
            `morning_time_in` TIME NULL,
            `morning_time_out` TIME NULL,
            `afternoon_time_in` TIME NULL,
            `afternoon_time_out` TIME NULL,
            `is_late_morning` TINYINT(1) NOT NULL DEFAULT 0,
            `is_late_afternoon` TINYINT(1) NOT NULL DEFAULT 0,
            `status` ENUM('present','absent','late','half_day','on_leave') NOT NULL DEFAULT 'present',
            `remarks` TEXT NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `department` VARCHAR(64) NULL,
            `shift` VARCHAR(16) NULL,
            `employee_number` CHAR(7) NULL,
            INDEX `idx_employee_number` (`employee_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($create);
        echo "Table '$table' created.\n";
        exit(0);
    }

    echo "Table '$table' exists — checking columns...\n";

    $cols = [
        'id' => "INT NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'date' => "DATE NOT NULL UNIQUE",
        'morning_time_in' => "TIME NULL",
        'morning_time_out' => "TIME NULL",
        'afternoon_time_in' => "TIME NULL",
        'afternoon_time_out' => "TIME NULL",
        'is_late_morning' => "TINYINT(1) NOT NULL DEFAULT 0",
        'is_late_afternoon' => "TINYINT(1) NOT NULL DEFAULT 0",
        'status' => "ENUM('present','absent','late','half_day','on_leave') NOT NULL DEFAULT 'present'",
        'remarks' => "TEXT NULL",
        'created_at' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'department' => "VARCHAR(64) NULL",
        'shift' => "VARCHAR(16) NULL",
        'employee_number' => "CHAR(7) NULL"
    ];

    foreach ($cols as $col => $definition) {
        if (!columnExists($pdo, $table, $col)) {
            echo "Adding column '$col'...\n";
            $sql = "ALTER TABLE `$table` ADD COLUMN `$col` $definition";
            $pdo->exec($sql);
        } else {
            echo "Column '$col' already exists — skipping.\n";
        }
    }

    // Ensure an index on employee_number exists
    $idxCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = 'idx_employee_number'");
    $idxCheck->execute([':table' => $table]);
    if ((int)$idxCheck->fetchColumn() === 0) {
        echo "Adding index idx_employee_number...\n";
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_employee_number` (`employee_number`)");
    } else {
        echo "Index 'idx_employee_number' already exists.\n";
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>
