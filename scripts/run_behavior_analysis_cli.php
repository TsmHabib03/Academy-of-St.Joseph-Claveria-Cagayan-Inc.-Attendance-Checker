<?php
/**
 * CLI runner for behavior analysis (students + teachers).
 *
 * Usage:
 *   php scripts/run_behavior_analysis_cli.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script must be run from CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/behavior_analyzer.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection is not available in config/db_config.php\n");
    exit(1);
}

try {
    $start = microtime(true);
    $analyzer = new BehaviorAnalyzer($pdo);

    $studentsStmt = $pdo->query("SELECT lrn FROM students WHERE lrn IS NOT NULL AND lrn != ''");
    $students = $studentsStmt ? $studentsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    $teachers = [];
    $tableStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers'");
    $tableStmt->execute();
    $hasTeachersTable = ((int)$tableStmt->fetchColumn()) > 0;
    if ($hasTeachersTable) {
        $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers'");
        $colStmt->execute();
        $teacherCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);

        $idExpr = null;
        if (in_array('employee_number', $teacherCols, true) && in_array('employee_id', $teacherCols, true)) {
            $idExpr = "COALESCE(NULLIF(employee_number, ''), NULLIF(employee_id, ''), id)";
        } elseif (in_array('employee_number', $teacherCols, true)) {
            $idExpr = "COALESCE(NULLIF(employee_number, ''), id)";
        } elseif (in_array('employee_id', $teacherCols, true)) {
            $idExpr = "COALESCE(NULLIF(employee_id, ''), id)";
        } elseif (in_array('id', $teacherCols, true)) {
            $idExpr = "id";
        }

        if ($idExpr !== null) {
            $teachersStmt = $pdo->query("SELECT {$idExpr} AS identifier FROM teachers");
            $teachers = $teachersStmt ? $teachersStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        }
    }

    $students = array_values(array_unique(array_filter(array_map('strval', $students), static function ($v) {
        return trim($v) !== '';
    })));
    $teachers = array_values(array_unique(array_filter(array_map('strval', $teachers), static function ($v) {
        return trim($v) !== '';
    })));

    $newAlertsStudents = 0;
    foreach ($students as $studentId) {
        $alerts = $analyzer->analyze($studentId, 'student');
        $newAlertsStudents += count($alerts);
    }

    $newAlertsTeachers = 0;
    foreach ($teachers as $teacherId) {
        $alerts = $analyzer->analyze($teacherId, 'teacher');
        $newAlertsTeachers += count($alerts);
    }

    $duration = round(microtime(true) - $start, 2);
    $result = [
        'success' => true,
        'students_analyzed' => count($students),
        'teachers_analyzed' => count($teachers),
        'users_analyzed' => count($students) + count($teachers),
        'new_alerts_students' => $newAlertsStudents,
        'new_alerts_teachers' => $newAlertsTeachers,
        'new_alerts' => $newAlertsStudents + $newAlertsTeachers,
        'duration' => $duration
    ];

    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Behavior analysis failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

