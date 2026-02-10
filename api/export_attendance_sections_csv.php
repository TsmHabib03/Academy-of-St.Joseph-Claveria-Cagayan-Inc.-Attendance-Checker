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
        // Prefer V3 columns (morning/afternoon) with fallback to legacy `time_in`/`time_out`.
    $query = "SELECT 
                                a.lrn,
                                {$studentNameExpr} as student_name,
                                a.section,
                                a.date,
                                {$timeInExpr} AS resolved_time_in,
                                {$timeOutExpr} AS resolved_time_out,
                                a.status,
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
        'Time Out',
        'Duration',
        'Status',
        'Parent Email'
    ];
    fputcsv($output, $headers);
    
    // Write data rows
    $total_records = 0;
    $completed_count = 0;
    $incomplete_count = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total_records++;
        
        $date_obj = strtotime($row['date']);
        $day_name = date('l', $date_obj);
        $date_formatted = date('M j, Y', $date_obj);
        
        $time_in = $row['resolved_time_in'] ? date('h:i A', strtotime($row['resolved_time_in'])) : '-';
        $time_out = $row['resolved_time_out'] ? date('h:i A', strtotime($row['resolved_time_out'])) : '-';
        
        // Calculate duration
        $duration = '-';
        if ($row['resolved_time_in'] && $row['resolved_time_out']) {
            $time_in_obj = strtotime($row['resolved_time_in']);
            $time_out_obj = strtotime($row['resolved_time_out']);
            $duration_seconds = $time_out_obj - $time_in_obj;
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $duration = sprintf('%d hrs %d mins', $hours, $minutes);
            $completed_count++;
        } elseif ($row['resolved_time_in']) {
            $duration = 'In Progress';
            $incomplete_count++;
        }
        
        $csv_row = [
            $row['lrn'],
            $row['student_name'],
            $row['section'],
            $date_formatted,
            $day_name,
            $time_in,
            $time_out,
            $duration,
            ucfirst($row['status']),
            $row['parent_email']
        ];
        
        fputcsv($output, $csv_row);
    }
    
    // Write summary footer
    fputcsv($output, []); // Empty row
    fputcsv($output, ['=== SUMMARY ===']);
    fputcsv($output, ['Total Records:', $total_records]);
    fputcsv($output, ['Completed (Time In & Out):', $completed_count]);
    fputcsv($output, ['Incomplete (Time In Only):', $incomplete_count]);
    fputcsv($output, ['Completion Rate:', $total_records > 0 ? round(($completed_count / $total_records) * 100, 1) . '%' : '0%']);
    
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
