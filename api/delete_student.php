<?php
require_once '../admin/config.php';
require_once '../includes/qrcode_helper.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$studentId = $input['student_id'] ?? $_POST['student_id'] ?? null;

if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // First, get student info for logging
    $stmt = $pdo->prepare("SELECT lrn, first_name, last_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Delete attendance records first (foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE lrn = ?");
    $stmt->execute([$student['lrn']]);
    $attendanceDeleted = $stmt->rowCount();
    
    // Delete the student
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    
    if ($stmt->rowCount() > 0) {
        // Delete QR code file
        deleteStudentQRCode($studentId);
        
        // Commit transaction
        $pdo->commit();
        
        // Log the admin activity
        logAdminActivity(
            'DELETE_STUDENT', 
            "Deleted student: {$student['first_name']} {$student['last_name']} (LRN: {$student['lrn']}). Also deleted {$attendanceDeleted} attendance records."
        );
        
        echo json_encode([
            'success' => true, 
            'message' => "Student deleted successfully. {$attendanceDeleted} attendance records were also removed.",
            'student_name' => $student['first_name'] . ' ' . $student['last_name']
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Delete student error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
