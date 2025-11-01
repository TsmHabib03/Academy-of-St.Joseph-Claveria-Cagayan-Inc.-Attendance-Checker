<?php
/**
 * Update Password API
 * Processes password reset form submission
 * Validates token and updates admin password
 */

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data || !isset($data['token']) || !isset($data['new_password']) || !isset($data['confirm_password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit();
}

$token = trim($data['token']);
$newPassword = trim($data['new_password']);
$confirmPassword = trim($data['confirm_password']);

// Validate token format
if (empty($token) || strlen($token) !== 64) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid token format'
    ]);
    exit();
}

// Validate passwords
if (empty($newPassword) || strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters long'
    ]);
    exit();
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Passwords do not match'
    ]);
    exit();
}

// Database connection
require_once '../config/db_config.php';

try {
    // Hash the token to match stored hash
    $hashedToken = hash('sha256', $token);
    
    // Verify token is valid and not expired
    $stmt = $pdo->prepare("
        SELECT id, username, email 
        FROM admins 
        WHERE reset_token = :token 
        AND reset_token_expires_at > NOW()
        LIMIT 1
    ");
    
    $stmt->execute(['token' => $hashedToken]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired reset token. Please request a new password reset.'
        ]);
        exit();
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password and clear reset token
    $stmt = $pdo->prepare("
        UPDATE admins 
        SET password = :password,
            reset_token = NULL,
            reset_token_expires_at = NULL
        WHERE id = :id
    ");
    
    $success = $stmt->execute([
        'password' => $hashedPassword,
        'id' => $admin['id']
    ]);
    
    if ($success) {
        // Log the password reset for security audit
        error_log("Password reset successful for admin: {$admin['username']} (ID: {$admin['id']})");
        
        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully. You can now login with your new password.'
        ]);
    } else {
        throw new Exception('Failed to update password');
    }
    
} catch (PDOException $e) {
    error_log("Database error in update password: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in update password: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while resetting your password. Please try again.'
    ]);
}
