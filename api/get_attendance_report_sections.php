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

    $hasLateMorningCol = columnExists($db, 'attendance', 'is_late_morning');
    $hasLateAfternoonCol = columnExists($db, 'attendance', 'is_late_afternoon');

    $gradeColumn = null;
    if (columnExists($db, 'students', 'grade_level')) {
        $gradeColumn = 'grade_level';
    } elseif (columnExists($db, 'students', 'class')) {
        $gradeColumn = 'class';
    }
    $gradeField = $gradeColumn ? ('s.`' . $gradeColumn . '`') : null;

    $hasMiddleName = columnExists($db, 'students', 'middle_name');
    $hasParentEmail = columnExists($db, 'students', 'email');
    $hasAttendanceId = columnExists($db, 'attendance', 'id');
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
    
        // Build query - prefer V3 columns (morning/afternoon) with fallback to legacy `time_in`.
        // If duplicate rows exist for the same student/date, keep only the latest row in reports.
        if ($hasAttendanceId) {
            $query = "SELECT 
                                    a.id,
                                    a.lrn,
                                    a.section,
                                    a.date,
                                    {$timeInExpr} AS resolved_time_in,
                                    a.status,
                                    " . ($hasLateMorningCol ? 'a.is_late_morning' : '0') . " AS is_late_morning,
                                    " . ($hasLateAfternoonCol ? 'a.is_late_afternoon' : '0') . " AS is_late_afternoon,
                                    {$studentNameExpr} as student_name,
                                    {$parentEmailExpr} as parent_email
                                FROM attendance a
                                INNER JOIN (
                                    SELECT lrn, date, MAX(id) AS latest_id
                                    FROM attendance
                                    WHERE date BETWEEN :start_date AND :end_date
                                    GROUP BY lrn, date
                                ) latest ON latest.latest_id = a.id
                                INNER JOIN students s ON a.lrn = s.lrn
                                WHERE 1=1";
        } else {
            $query = "SELECT 
                                    a.id,
                                    a.lrn,
                                    a.section,
                                    a.date,
                                    {$timeInExpr} AS resolved_time_in,
                                    a.status,
                                    " . ($hasLateMorningCol ? 'a.is_late_morning' : '0') . " AS is_late_morning,
                                    " . ($hasLateAfternoonCol ? 'a.is_late_afternoon' : '0') . " AS is_late_afternoon,
                                    {$studentNameExpr} as student_name,
                                    {$parentEmailExpr} as parent_email
                                FROM attendance a
                                INNER JOIN students s ON a.lrn = s.lrn
                                WHERE a.date BETWEEN :start_date AND :end_date";
        }

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
    $scanned_in_count = 0;
    $late_count = 0;
    $sections_set = [];
    
    foreach ($records as $record) {
        $hasTimeIn = !empty($record['resolved_time_in']);
        if ($hasTimeIn) {
            $scanned_in_count++;
        }

        $isLate = ((int)($record['is_late_morning'] ?? 0) === 1)
            || ((int)($record['is_late_afternoon'] ?? 0) === 1)
            || (strtolower((string)($record['status'] ?? '')) === 'late');
        if ($hasTimeIn && $isLate) {
            $late_count++;
        }
        
        // Track unique sections
        if (!in_array($record['section'], $sections_set)) {
            $sections_set[] = $record['section'];
        }

        $status = strtolower((string)($record['status'] ?? ''));
        if ($status === 'time_in' || $status === 'time_out') {
            $status = $isLate ? 'late' : 'present';
        } elseif ($status === '') {
            $status = $hasTimeIn ? ($isLate ? 'late' : 'present') : 'absent';
        }
        
        $formatted_records[] = [
            'id' => $record['id'],
            'lrn' => $record['lrn'],
            'student_name' => $record['student_name'],
            'section' => $record['section'],
            'date' => $record['date'],
            'date_formatted' => date('F j, Y', strtotime($record['date'])),
            'time_in' => $record['resolved_time_in'] ? date('h:i A', strtotime($record['resolved_time_in'])) : null,
            'status' => $status,
            'is_late' => $isLate,
            'parent_email' => $record['parent_email']
        ];
    }
    
    // Calculate summary
    $summary = [
        'total_records' => count($formatted_records),
        'scanned_in_count' => $scanned_in_count,
        'late_count' => $late_count,
        'on_time_count' => max(0, $scanned_in_count - $late_count),
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
