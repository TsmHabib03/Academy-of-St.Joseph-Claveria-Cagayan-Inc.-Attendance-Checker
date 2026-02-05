<?php
require_once __DIR__ . '/bootstrap.php';
// Require admin or teacher
api_require_schema_or_exit($pdo, ['tables' => ['students','attendance']]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER]);
require_once '../includes/database.php';

if (!isset($_GET['lrn'])) {
    echo json_encode(['success' => false, 'message' => 'LRN is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $lrn = $_GET['lrn'];
    
    // Get student details
    $student_query = "SELECT * FROM students WHERE lrn = :lrn";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':lrn', $lrn);
    $student_stmt->execute();
    
    if ($student_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent attendance (last 30 days)
    $attendance_query = "SELECT * FROM attendance WHERE lrn = :lrn 
                        ORDER BY date DESC, time DESC LIMIT 30";
    $attendance_stmt = $db->prepare($attendance_query);
    $attendance_stmt->bindParam(':lrn', $lrn);
    $attendance_stmt->execute();
    
    $attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'student' => $student,
        'attendance' => $attendance
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
