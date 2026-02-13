<?php
/**
 * Run Behavior Analysis API - AttendEase v3.0
 * Analyzes all students for behavior patterns
 * 
 * @package AttendEase
 * @version 3.0
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/behavior_analyzer.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Start session and require admin/teacher role
session_start();
if (!isAuthenticated() || !hasRole([ROLE_ADMIN, ROLE_TEACHER])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please log in again.'
    ]);
    exit;
}

try {
    $startTime = microtime(true);
    
    // Initialize analyzer
    $analyzer = new BehaviorAnalyzer($pdo);
    
    // Get all students
    $studentsStmt = $pdo->query("SELECT id FROM students");
    $students = $studentsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get teacher identifiers (prefer employee_number, then employee_id, then id)
    $teacherIdentifiers = [];
    try {
        $teacherTableStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers'");
        $teacherTableStmt->execute();
        $hasTeachersTable = ((int)$teacherTableStmt->fetchColumn()) > 0;

        if ($hasTeachersTable) {
            $teacherColsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers'");
            $teacherColsStmt->execute();
            $teacherCols = $teacherColsStmt->fetchAll(PDO::FETCH_COLUMN);

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
                $teacherIdentifiers = $teachersStmt->fetchAll(PDO::FETCH_COLUMN);
                $teacherIdentifiers = array_values(array_unique(array_filter(array_map('strval', $teacherIdentifiers), static function ($v) {
                    return trim($v) !== '';
                })));
            }
        }
    } catch (Exception $e) {
        error_log("Behavior analysis teacher load warning: " . $e->getMessage());
        $teacherIdentifiers = [];
    }

    $newAlertsStudents = 0;
    $newAlertsTeachers = 0;
    $studentsAnalyzed = count($students);
    $teachersAnalyzed = count($teacherIdentifiers);
    
    // Analyze each student
    foreach ($students as $studentId) {
        $alerts = $analyzer->analyze($studentId, 'student');
        $newAlertsStudents += count($alerts);
    }

    // Analyze each teacher
    foreach ($teacherIdentifiers as $teacherId) {
        $alerts = $analyzer->analyze($teacherId, 'teacher');
        $newAlertsTeachers += count($alerts);
    }
    
    $duration = round(microtime(true) - $startTime, 2);
    $newAlertsTotal = $newAlertsStudents + $newAlertsTeachers;
    
    echo json_encode([
        'success' => true,
        'students_analyzed' => $studentsAnalyzed,
        'teachers_analyzed' => $teachersAnalyzed,
        'users_analyzed' => $studentsAnalyzed + $teachersAnalyzed,
        'new_alerts_students' => $newAlertsStudents,
        'new_alerts_teachers' => $newAlertsTeachers,
        'new_alerts' => $newAlertsTotal,
        'duration' => $duration
    ]);
    
} catch (Exception $e) {
    error_log("Behavior analysis error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error running analysis: ' . $e->getMessage()
    ]);
}
