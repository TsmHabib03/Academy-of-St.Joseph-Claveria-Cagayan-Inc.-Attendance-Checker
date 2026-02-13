<?php
require_once __DIR__ . '/bootstrap.php';
api_require_schema_or_exit($pdo, ['tables' => ['attendance']]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF]);
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $today = date('Y-m-d');

    $attendanceColsStmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance'");
    $attendanceColsStmt->execute();
    $attendanceCols = $attendanceColsStmt->fetchAll(PDO::FETCH_COLUMN);

    $studentColsStmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students'");
    $studentColsStmt->execute();
    $studentCols = $studentColsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Single-scan mode: prefer Time In columns for display.
    $timeCandidates = ['afternoon_time_in', 'morning_time_in', 'time_in', 'time', 'afternoon_time_out', 'morning_time_out', 'time_out'];
    $timeExprParts = [];
    foreach ($timeCandidates as $tc) {
        if (in_array($tc, $attendanceCols, true)) {
            $timeExprParts[] = "a.{$tc}";
        }
    }
    $displayTimeExpr = !empty($timeExprParts) ? ('COALESCE(' . implode(', ', $timeExprParts) . ')') : 'NULL';

    if (in_array('section', $studentCols, true) && in_array('class', $studentCols, true)) {
        $classExpr = 'COALESCE(s.section, s.class)';
    } elseif (in_array('section', $studentCols, true)) {
        $classExpr = 's.section';
    } elseif (in_array('class', $studentCols, true)) {
        $classExpr = 's.class';
    } else {
        $classExpr = "''";
    }

    $orderParts = [];
    if (in_array('period_number', $attendanceCols, true)) {
        $orderParts[] = 'a.period_number ASC';
    }
    if (in_array('created_at', $attendanceCols, true)) {
        $orderParts[] = 'a.created_at DESC';
    } elseif (in_array('updated_at', $attendanceCols, true)) {
        $orderParts[] = 'a.updated_at DESC';
    } elseif (in_array('id', $attendanceCols, true)) {
        $orderParts[] = 'a.id DESC';
    }
    $orderBy = !empty($orderParts) ? implode(', ', $orderParts) : 'a.lrn ASC';

    // Get today's attendance with schema-aware time and class fields
    $query = "SELECT a.*, s.first_name, s.last_name, {$classExpr} AS class, s.lrn, {$displayTimeExpr} AS display_time
              FROM attendance a
              JOIN students s ON a.lrn = s.lrn
              WHERE a.date = :today
              ORDER BY {$orderBy}";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format time for display
    foreach ($attendance as &$record) {
        $displayTime = $record['display_time'] ?? null;
        $record['time'] = $displayTime ? date('h:i:s A', strtotime($displayTime)) : 'N/A';
        unset($record['display_time']);
    }
    
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'date' => $today,
        'total' => count($attendance)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
