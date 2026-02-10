<?php
require_once __DIR__ . '/bootstrap.php';
// Require admin or teacher for attendance reports
api_require_schema_or_exit($pdo, [
    'tables' => ['attendance', 'students']
]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER]);

/**
 * Get Attendance Report - Section-Based
 * Returns attendance records filtered by section, date range, and student search
 */

header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $timeInParts = [];
    if (columnExists($db, 'attendance', 'morning_time_in')) {
        $timeInParts[] = 'a.morning_time_in';
    }
    if (columnExists($db, 'attendance', 'afternoon_time_in')) {
        $timeInParts[] = 'a.afternoon_time_in';
    }
    if (columnExists($db, 'attendance', 'time_in')) {
        $timeInParts[] = 'a.time_in';
    }
    if (count($timeInParts) === 0) {
        $timeInParts[] = 'NULL';
    }
    $timeInExpr = count($timeInParts) > 1 ? 'COALESCE(' . implode(', ', $timeInParts) . ')' : $timeInParts[0];

    $timeOutParts = [];
    if (columnExists($db, 'attendance', 'morning_time_out')) {
        $timeOutParts[] = 'a.morning_time_out';
    }
    if (columnExists($db, 'attendance', 'afternoon_time_out')) {
        $timeOutParts[] = 'a.afternoon_time_out';
    }
    if (columnExists($db, 'attendance', 'time_out')) {
        $timeOutParts[] = 'a.time_out';
    }
    if (count($timeOutParts) === 0) {
        $timeOutParts[] = 'NULL';
    }
    $timeOutExpr = count($timeOutParts) > 1 ? 'COALESCE(' . implode(', ', $timeOutParts) . ')' : $timeOutParts[0];

    $gradeColumn = null;
    if (columnExists($db, 'students', 'grade_level')) {
        $gradeColumn = 'grade_level';
    } elseif (columnExists($db, 'students', 'class')) {
        $gradeColumn = 'class';
    }
    $gradeField = $gradeColumn ? ('s.`' . $gradeColumn . '`') : null;

    $hasMiddleName = columnExists($db, 'students', 'middle_name');
    $hasParentEmail = columnExists($db, 'students', 'email');
    $studentNameExpr = $hasMiddleName
        ? "CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name)"
        : "CONCAT(s.first_name, ' ', s.last_name)";
    $parentEmailExpr = $hasParentEmail ? 's.email' : 'NULL';
    
    // Get filter parameters
    $section = $_GET['section'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $student_search = trim($_GET['student_search'] ?? '');
    
    // Validate required dates
    if (empty($start_date) || empty($end_date)) {
        throw new Exception('Start date and end date are required');
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }
    
        // Build query - prefer V3 columns (morning/afternoon) with fallback to legacy `time_in`/`time_out`.
        $query = "SELECT 
                                a.id,
                                a.lrn,
                                a.section,
                                a.date,
                                {$timeInExpr} AS resolved_time_in,
                                {$timeOutExpr} AS resolved_time_out,
                                a.status,
                                {$studentNameExpr} as student_name,
                                {$parentEmailExpr} as parent_email
                            FROM attendance a
                            INNER JOIN students s ON a.lrn = s.lrn
                            WHERE a.date BETWEEN :start_date AND :end_date";

        if ($gradeField) {
            $query .= " AND {$gradeField} NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6')
                        AND {$gradeField} NOT LIKE 'Kinder%'";
        }
    
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    // Add section filter
    if (!empty($section)) {
        $query .= " AND a.section = :section";
        $params[':section'] = $section;
    }
    
    // Add student search filter
    if (!empty($student_search)) {
        $query .= " AND (
            s.lrn LIKE :search 
            OR s.first_name LIKE :search 
            OR s.last_name LIKE :search
            OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search
        )";
        $params[':search'] = '%' . $student_search . '%';
    }
    
    $query .= " ORDER BY a.date DESC, a.section ASC, s.last_name ASC";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format records
    $formatted_records = [];
    $completed_count = 0;
    $incomplete_count = 0;
    $sections_set = [];
    
    foreach ($records as $record) {
        $time_in_obj = $record['resolved_time_in'] ? strtotime($record['resolved_time_in']) : null;
        $time_out_obj = $record['resolved_time_out'] ? strtotime($record['resolved_time_out']) : null;
        
        // Calculate duration
        $duration = '-';
        if ($time_in_obj && $time_out_obj) {
            $duration_seconds = $time_out_obj - $time_in_obj;
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $duration = sprintf('%d hrs %d mins', $hours, $minutes);
            $completed_count++;
        } elseif ($time_in_obj) {
            $duration = 'In Progress';
            $incomplete_count++;
        }
        
        // Track unique sections
        if (!in_array($record['section'], $sections_set)) {
            $sections_set[] = $record['section'];
        }
        
        $formatted_records[] = [
            'id' => $record['id'],
            'lrn' => $record['lrn'],
            'student_name' => $record['student_name'],
            'section' => $record['section'],
            'date' => $record['date'],
            'date_formatted' => date('F j, Y', strtotime($record['date'])),
            'time_in' => $record['resolved_time_in'] ? date('h:i A', strtotime($record['resolved_time_in'])) : null,
            'time_out' => $record['resolved_time_out'] ? date('h:i A', strtotime($record['resolved_time_out'])) : null,
            'duration' => $duration,
            'status' => $record['status'],
            'parent_email' => $record['parent_email']
        ];
    }
    
    // Calculate summary
    $summary = [
        'total_records' => count($formatted_records),
        'completed_count' => $completed_count,
        'incomplete_count' => $incomplete_count,
        'sections_count' => count($sections_set),
        'date_range' => date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)),
        'sections' => $sections_set
    ];
    
    echo json_encode([
        'success' => true,
        'records' => $formatted_records,
        'summary' => $summary,
        'filters' => [
            'section' => $section ?: 'All Sections',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'student_search' => $student_search
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
