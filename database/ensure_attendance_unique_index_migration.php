<?php
/**
 * Idempotent migration: ensure `attendance` has a unique key on (lrn, date)
 * to prevent duplicate daily records per student account.
 *
 * It also removes older duplicate rows (keeps latest id) when possible.
 *
 * Usage:
 *   php ensure_attendance_unique_index_migration.php
 */

require_once __DIR__ . '/../config/db_config.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "Error: PDO \$pdo not available from config.\n";
    exit(1);
}

$table = 'attendance';

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

function hasUniqueIndexOnColumns(PDO $pdo, $table, array $columns)
{
    $stmt = $pdo->prepare("
        SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
        GROUP BY INDEX_NAME, NON_UNIQUE
    ");
    $stmt->execute([':table' => $table]);
    $target = implode(',', $columns);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$row['NON_UNIQUE'] === 0 && (string)$row['cols'] === $target) {
            return true;
        }
    }
    return false;
}

try {
    if (!tableExists($pdo, $table)) {
        echo "Table '{$table}' does not exist.\n";
        exit(1);
    }

    if (!columnExists($pdo, $table, 'lrn') || !columnExists($pdo, $table, 'date')) {
        echo "Table '{$table}' is missing required columns `lrn` and/or `date`.\n";
        exit(1);
    }

    $hasId = columnExists($pdo, $table, 'id');
    if ($hasId) {
        $dupCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM (
                SELECT lrn, date, COUNT(*) AS c
                FROM attendance
                GROUP BY lrn, date
                HAVING c > 1
            ) d
        ");
        $dupGroups = (int)$dupCountStmt->fetchColumn();
        if ($dupGroups > 0) {
            echo "Found {$dupGroups} duplicate attendance group(s). Cleaning older duplicates...\n";
            $deleted = $pdo->exec("
                DELETE a_old
                FROM attendance a_old
                INNER JOIN attendance a_new
                    ON a_old.lrn = a_new.lrn
                   AND a_old.date = a_new.date
                   AND a_old.id < a_new.id
            ");
            echo "Deleted {$deleted} duplicate row(s).\n";
        } else {
            echo "No duplicate attendance groups found.\n";
        }
    } else {
        echo "Warning: `attendance.id` not found. Duplicate cleanup skipped.\n";
    }

    if (hasUniqueIndexOnColumns($pdo, $table, ['lrn', 'date'])) {
        echo "Unique index on (lrn, date) already exists.\n";
    } else {
        echo "Adding unique index `ux_attendance_lrn_date` on (lrn, date)...\n";
        $pdo->exec("ALTER TABLE `attendance` ADD UNIQUE INDEX `ux_attendance_lrn_date` (`lrn`, `date`)");
        echo "Unique index added successfully.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>

