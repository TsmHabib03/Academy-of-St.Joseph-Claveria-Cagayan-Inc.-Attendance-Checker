<?php
/**
 * Idempotent migration: add `employee_number` (7-digit) to teachers and teacher_attendance
 * Run: php database/add_teacher_employee_number_migration.php
 */

require_once __DIR__ . '/../config/db_config.php';

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        // Try common variable name
        if (isset($db) && $db instanceof PDO) {
            $pdo = $db;
        } else {
            throw new Exception('PDO connection ($pdo) not found. Ensure config/db_config.php sets $pdo.');
        }
    }

    $schema = $pdo->query('SELECT DATABASE()')->fetchColumn();

    // Helper to check column
    $hasColumn = function($table, $column) use ($pdo, $schema) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :col");
        $stmt->execute([':schema' => $schema, ':table' => $table, ':col' => $column]);
        return $stmt->fetchColumn() > 0;
    };

    // Add employee_number to teachers
    if (!$hasColumn('teachers', 'employee_number')) {
        echo "Adding column teachers.employee_number...\n";
        $pdo->exec("ALTER TABLE teachers ADD COLUMN employee_number CHAR(7) NULL AFTER employee_id;");
        // Add unique index
        $pdo->exec("ALTER TABLE teachers ADD UNIQUE INDEX ux_teachers_employee_number (employee_number);");
    } else {
        echo "Column teachers.employee_number already exists; skipping.\n";
    }

    // Add employee_number to teacher_attendance
    if (!$hasColumn('teacher_attendance', 'employee_number')) {
        echo "Adding column teacher_attendance.employee_number...\n";
        $pdo->exec("ALTER TABLE teacher_attendance ADD COLUMN employee_number CHAR(7) NULL AFTER employee_id;");
        $pdo->exec("ALTER TABLE teacher_attendance ADD INDEX ix_teacher_attendance_employee_number (employee_number);");
    } else {
        echo "Column teacher_attendance.employee_number already exists; skipping.\n";
    }

    echo "Migration completed.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
