<?php
/**
 * Password Reset Request Handler
 * Generates a secure token and sends password reset email
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors for debugging
ini_set('log_errors', 1);

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
    }
});

header('Content-Type: application/json');

// Start session
session_start();

// Include PHPMailer first
require_once '../libs/PHPMailer/Exception.php';
require_once '../libs/PHPMailer/PHPMailer.php';
require_once '../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include database configuration
require_once '../config/db_config.php';

// Include email configuration
$emailConfig = require_once '../config/email_config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['email']) || empty(trim($input['email']))) {
    echo json_encode([
        'success' => false,
        'message' => 'Email address is required'
    ]);
    exit();
}

$email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email address format'
    ]);
    exit();
}

try {
    // Check if admin exists with this email
    $stmt = $pdo->prepare("SELECT id, username, email FROM admin_users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always return success to prevent email enumeration
    // But only send email if account exists
    if ($admin) {
        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(32)); // 64 character hex string
        
        // Hash the token for database storage
        $hashedToken = hash('sha256', $token);
        
        // Set expiration time (1 hour from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store hashed token and expiration in database
        $stmt = $pdo->prepare("
            UPDATE admin_users 
            SET reset_token = :token, 
                reset_token_expires_at = :expires_at 
            WHERE id = :id
        ");
        
        $stmt->execute([
            'token' => $hashedToken,
            'expires_at' => $expiresAt,
            'id' => $admin['id']
        ]);

        // Create reset link with plain (unhashed) token
        $resetLink = "http://localhost/AttendEase/admin/reset_password.php?token=" . urlencode($token);

        // Send email using PHPMailer
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $emailConfig['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['smtp_username'];
            $mail->Password = $emailConfig['smtp_password'];
            $mail->SMTPSecure = $emailConfig['smtp_secure'];
            $mail->Port = $emailConfig['smtp_port'];

            // Recipients
            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $mail->addAddress($admin['email'], $admin['username']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - AttendEase Admin';
            
            // HTML email body
            $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Password Reset Request</h1>
        </div>
        <div class="content">
            <p>Hello <strong>' . htmlspecialchars($admin['username']) . '</strong>,</p>
            
            <p>We received a request to reset your password for your AttendEase Admin account.</p>
            
            <p>Click the button below to reset your password:</p>
            
            <div style="text-align: center;">
                <a href="' . $resetLink . '" class="button">Reset My Password</a>
            </div>
            
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; background: white; padding: 10px; border-radius: 4px; font-size: 12px;">' . $resetLink . '</p>
            
            <div class="warning">
                <strong>⚠️ Important Security Information:</strong>
                <ul>
                    <li>This link will expire in <strong>1 hour</strong></li>
                    <li>This link can only be used <strong>once</strong></li>
                    <li>If you didn\'t request this reset, please ignore this email</li>
                    <li>Your password will remain unchanged until you create a new one</li>
                </ul>
            </div>
            
            <p><strong>Security Tip:</strong> Never share this link with anyone. AttendEase staff will never ask for your password or reset link.</p>
        </div>
        <div class="footer">
            <p>This email was sent from <strong>AttendEase Admin Portal</strong></p>
            <p>If you have any questions, please contact your system administrator.</p>
            <p style="font-size: 12px; color: #9ca3af;">© ' . date('Y') . ' AttendEase. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';

            // Plain text alternative
            $mail->AltBody = "Hello {$admin['username']},\n\n"
                . "We received a request to reset your password for your AttendEase Admin account.\n\n"
                . "Click the link below to reset your password:\n"
                . $resetLink . "\n\n"
                . "This link will expire in 1 hour and can only be used once.\n\n"
                . "If you didn't request this reset, please ignore this email. Your password will remain unchanged.\n\n"
                . "Best regards,\n"
                . "AttendEase Team";

            $mail->send();
            
            // Log the reset request (optional, for security auditing)
            error_log("Password reset requested for email: {$email} at " . date('Y-m-d H:i:s'));
            
        } catch (Exception $e) {
            // Log email error but don't reveal it to user
            error_log("Email sending failed: {$mail->ErrorInfo}");
            
            // Still return success to prevent email enumeration
            echo json_encode([
                'success' => true,
                'message' => 'If an account exists with this email, you will receive password reset instructions.'
            ]);
            exit();
        }
    }

    // Always return success message (prevents user enumeration)
    echo json_encode([
        'success' => true,
        'message' => 'If an account exists with this email, you will receive password reset instructions shortly. Please check your inbox and spam folder.'
    ]);

} catch (PDOException $e) {
    // Log database error
    error_log("Database error in password reset: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'A system error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    // Log general error
    error_log("Error in password reset: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}
