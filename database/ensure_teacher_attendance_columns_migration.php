<?php
/**
 * Idempotent migration: ensure `teacher_attendance` table exists with
 * required columns and correct uniqueness (one record per teacher per day).
 *
 * Usage: php ensure_teacher_attendance_columns_migration.php
 */

require_once __DIR__ . '/../config/db_config.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "Error: PDO \$pdo not available from config.\n";
    exit(1);
}

$table = 'teacher_attendance';

function tableExists(PDO $pdo, $table)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
    $stmt->execute([':table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, $table, $indexName)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :idx");
    $stmt->execute([':table' => $table, ':idx' => $indexName]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexColumns(PDO $pdo, $table, $indexName)
{
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :idx ORDER BY SEQ_IN_INDEX");
    $stmt->execute([':table' => $table, ':idx' => $indexName]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

try {
    if (!tableExists($pdo, $table)) {
        echo "Table '$table' does not exist - creating...\n";
        $create = "CREATE TABLE `$table` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `date` DATE NOT NULL,
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
            INDEX `idx_employee_number` (`employee_number`),
            UNIQUE KEY `ux_teacher_attendance_employee_date` (`employee_number`, `date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($create);
        echo "Table '$table' created.\n";
        exit(0);
    }

    echo "Table '$table' exists - checking columns...\n";

    $cols = [
        'id' => "INT NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'date' => "DATE NOT NULL",
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
            echo "Column '$col' already exists - skipping.\n";
        }
    }

    if (!indexExists($pdo, $table, 'idx_employee_number')) {
        echo "Adding index idx_employee_number...\n";
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_employee_number` (`employee_number`)");
    } else {
        echo "Index 'idx_employee_number' already exists.\n";
    }

    // Drop date-only unique indexes that block multiple teachers on same day.
    $badUniqueStmt = $pdo->prepare("
        SELECT INDEX_NAME
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND NON_UNIQUE = 0
        GROUP BY INDEX_NAME
        HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = 'date'
    ");
    $badUniqueStmt->execute([':table' => $table]);
    $badUniqueIndexes = $badUniqueStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($badUniqueIndexes as $idx) {
        if ($idx !== 'PRIMARY') {
            echo "Dropping invalid unique index '$idx' (date-only)...\n";
            $pdo->exec("ALTER TABLE `$table` DROP INDEX `$idx`");
        }
    }

    // Ensure a valid composite unique key exists.
    if (columnExists($pdo, $table, 'employee_number')) {
        $targetUnique = 'ux_teacher_attendance_employee_date';
        $targetCols = ['employee_number', 'date'];
        $isValid = indexExists($pdo, $table, $targetUnique) && indexColumns($pdo, $table, $targetUnique) === $targetCols;
        if (!$isValid) {
            if (indexExists($pdo, $table, $targetUnique)) {
                $pdo->exec("ALTER TABLE `$table` DROP INDEX `$targetUnique`");
            }
            echo "Adding unique index '$targetUnique' on (employee_number, date)...\n";
            $pdo->exec("ALTER TABLE `$table` ADD UNIQUE INDEX `$targetUnique` (`employee_number`, `date`)");
        } else {
            echo "Unique index '$targetUnique' already valid.\n";
        }
    } elseif (columnExists($pdo, $table, 'employee_id')) {
        $targetUnique = 'ux_teacher_attendance_employee_id_date';
        $targetCols = ['employee_id', 'date'];
        $isValid = indexExists($pdo, $table, $targetUnique) && indexColumns($pdo, $table, $targetUnique) === $targetCols;
        if (!$isValid) {
            if (indexExists($pdo, $table, $targetUnique)) {
                $pdo->exec("ALTER TABLE `$table` DROP INDEX `$targetUnique`");
            }
            echo "Adding unique index '$targetUnique' on (employee_id, date)...\n";
            $pdo->exec("ALTER TABLE `$table` ADD UNIQUE INDEX `$targetUnique` (`employee_id`, `date`)");
        } else {
            echo "Unique index '$targetUnique' already valid.\n";
        }
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>

