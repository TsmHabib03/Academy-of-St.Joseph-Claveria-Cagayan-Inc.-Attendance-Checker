<?php
/**
 * API Endpoint: Regenerate QR Code for Student
 * Allows admin to manually regenerate QR code for existing student
 */

session_start();
require_once '../admin/config.php';
require_once '../includes/qrcode_helper.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Content-Type: application/json');

// Enforce admin role
requireRole([ROLE_ADMIN]);

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get student ID from request
$input = json_decode(file_get_contents('php://input'), true);
$studentId = isset($input['student_id']) ? intval($input['student_id']) : 0;

if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student ID'
    ]);
    exit;
}

try {
    // Get student details
    $stmt = $pdo->prepare("SELECT id, lrn, first_name, middle_name, last_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Student not found'
        ]);
        exit;
    }
    
    // Generate full name
    $fullName = trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']);
    
    // Regenerate QR code
    $qrCodePath = regenerateStudentQRCode($student['id'], $student['lrn'], $fullName);
    
    if ($qrCodePath) {
        // Update database with new QR code path
        $updateStmt = $pdo->prepare("UPDATE students SET qr_code = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$qrCodePath, $student['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'QR code regenerated successfully',
            'qr_code_path' => '../' . $qrCodePath . '?v=' . time() // Add timestamp to force refresh
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to regenerate QR code. Please check server permissions.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("QR regeneration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}
