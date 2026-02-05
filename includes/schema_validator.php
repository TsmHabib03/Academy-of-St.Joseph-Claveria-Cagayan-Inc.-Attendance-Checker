<?php
/**
 * Simple schema validator used by APIs to check for required tables/columns
 */
function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Validate that required tables/columns exist. Returns array of missing items.
 * $requirements example:
 * ['tables' => ['teachers','attendance_schedules'], 'columns' => ['attendance.morning_time_in']]
 */
function validate_schema_requirements(PDO $pdo, array $requirements = []): array {
    $missing = [];

    $tables = $requirements['tables'] ?? [];
    foreach ($tables as $t) {
        if (!tableExists($pdo, $t)) {
            $missing[] = "table:{$t}";
        }
    }

    $columns = $requirements['columns'] ?? [];
    foreach ($columns as $col) {
        if (strpos($col, '.') === false) {
            continue;
        }
        list($table, $column) = explode('.', $col, 2);
        if (!tableExists($pdo, $table) || !columnExists($pdo, $table, $column)) {
            $missing[] = "column:{$table}.{$column}";
        }
    }

    return $missing;
}
