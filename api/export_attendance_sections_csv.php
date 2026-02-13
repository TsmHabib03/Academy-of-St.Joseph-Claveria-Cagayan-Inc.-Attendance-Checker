<?php
/**
 * Export Attendance to CSV - Section-Based
 * Generates CSV file with attendance records
 */

require_once __DIR__ . '/bootstrap.php';
// Require admin role to export CSV
api_require_schema_or_exit($pdo, [
    'tables' => ['attendance', 'students']
]);
api_require_admin();
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
        die('Error: Start date and end date are required');
    }
    
    // Build filename
    $section_name = !empty($section) ? $section : 'All_Sections';
    $start_formatted = date('Ymd', strtotime($start_date));
    $end_formatted = date('Ymd', strtotime($end_date));
    $filename = "Attendance_{$section_name}_{$start_formatted}_to_{$end_formatted}.csv";
    
    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Build query
        // Prefer V3 columns (morning/afternoon) with fallback to legacy `time_in`.
    if ($hasAttendanceId) {
        $query = "SELECT 
                                    a.lrn,
                                    {$studentNameExpr} as student_name,
                                    a.section,
                                    a.date,
                                    {$timeInExpr} AS resolved_time_in,
                                    a.status,
                                    " . ($hasLateMorningCol ? 'a.is_late_morning' : '0') . " AS is_late_morning,
                                    " . ($hasLateAfternoonCol ? 'a.is_late_afternoon' : '0') . " AS is_late_afternoon,
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
                                    a.lrn,
                                    {$studentNameExpr} as student_name,
                                    a.section,
                                    a.date,
                                    {$timeInExpr} AS resolved_time_in,
                                    a.status,
                                    " . ($hasLateMorningCol ? 'a.is_late_morning' : '0') . " AS is_late_morning,
                                    " . ($hasLateAfternoonCol ? 'a.is_late_afternoon' : '0') . " AS is_late_afternoon,
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
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write report header
    fputcsv($output, ['ATTENDANCE REPORT - SECTION-BASED SYSTEM']);
    fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
    fputcsv($output, ['Date Range:', date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date))]);
    fputcsv($output, ['Section:', !empty($section) ? $section : 'All Sections']);
    if (!empty($student_search)) {
        fputcsv($output, ['Search Filter:', $student_search]);
    }
    fputcsv($output, []); // Empty row
    
    // Write CSV headers
    $headers = [
        'LRN',
        'Student Name',
        'Section',
        'Date',
        'Day',
        'Time In',
        'Status',
        'Parent Email'
    ];
    fputcsv($output, $headers);
    
    // Write data rows
    $total_records = 0;
    $scanned_in_count = 0;
    $late_count = 0;
    $absent_count = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total_records++;
        
        $date_obj = strtotime($row['date']);
        $day_name = date('l', $date_obj);
        $date_formatted = date('M j, Y', $date_obj);
        
        $time_in = $row['resolved_time_in'] ? date('h:i A', strtotime($row['resolved_time_in'])) : '-';

        $hasTimeIn = !empty($row['resolved_time_in']);
        if ($hasTimeIn) {
            $scanned_in_count++;
        }

        $isLate = ((int)($row['is_late_morning'] ?? 0) === 1)
            || ((int)($row['is_late_afternoon'] ?? 0) === 1)
            || (strtolower((string)($row['status'] ?? '')) === 'late');
        if ($hasTimeIn && $isLate) {
            $late_count++;
        }

        $status = strtolower((string)($row['status'] ?? ''));
        if ($status === 'time_in' || $status === 'time_out') {
            $status = $isLate ? 'late' : 'present';
        } elseif ($status === '') {
            $status = $hasTimeIn ? ($isLate ? 'late' : 'present') : 'absent';
        }
        if ($status === 'absent') {
            $absent_count++;
        }
        
        $csv_row = [
            $row['lrn'],
            $row['student_name'],
            $row['section'],
            $date_formatted,
            $day_name,
            $time_in,
            ucwords(str_replace('_', ' ', $status)),
            $row['parent_email']
        ];
        
        fputcsv($output, $csv_row);
    }
    
    // Write summary footer
    fputcsv($output, []); // Empty row
    fputcsv($output, ['=== SUMMARY ===']);
    fputcsv($output, ['Total Records:', $total_records]);
    fputcsv($output, ['Scanned In:', $scanned_in_count]);
    fputcsv($output, ['Late Arrivals:', $late_count]);
    fputcsv($output, ['On-time Arrivals:', max(0, $scanned_in_count - $late_count)]);
    fputcsv($output, ['Absent Records:', $absent_count]);
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    header('Content-Type: text/plain');
    die('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    header('Content-Type: text/plain');
    die('Error: ' . $e->getMessage());
}
?>
