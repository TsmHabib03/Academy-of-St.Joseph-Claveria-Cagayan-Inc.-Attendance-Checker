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

// Start session and require admin role
session_start();
requireRole([ROLE_ADMIN]);

try {
    $startTime = microtime(true);
    
    // Initialize analyzer
    $analyzer = new BehaviorAnalyzer($pdo);
    
    // Get all students (students table doesn't have status column, get all)
    $studentsStmt = $pdo->query("SELECT id FROM students");
    $students = $studentsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $newAlerts = 0;
    $studentsAnalyzed = count($students);
    
    // Analyze each student
    foreach ($students as $studentId) {
        $alerts = $analyzer->analyze($studentId, 'student');
        $newAlerts += count($alerts);
    }
    
    $duration = round(microtime(true) - $startTime, 2);
    
    echo json_encode([
        'success' => true,
        'students_analyzed' => $studentsAnalyzed,
        'new_alerts' => $newAlerts,
        'duration' => $duration
    ]);
    
} catch (Exception $e) {
    error_log("Behavior analysis error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error running analysis: ' . $e->getMessage()
    ]);
}
