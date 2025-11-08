<?php
/**
 * Email Notification System for Student Attendance
 * 
 * This file contains a function to send email notifications when students
 * scan their QR codes for attendance (Time IN or Time OUT).
 * 
 * Requirements:
 * - PHPMailer library (included in libs/PHPMailer/)
 * 
 * @author Academy of St. Joseph Attendance System
 * @version 1.0
 */

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if PHPMailer is installed
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Fallback: Load PHPMailer manually
    if (file_exists(__DIR__ . '/../libs/PHPMailer/Exception.php')) {
        require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
        require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';
    }
}

/**
 * Send an email notification when a student scans their QR code for attendance
 * 
 * This function sends a formatted HTML email alert to notify about student attendance.
 * The email includes student details, status (IN or OUT), and timestamp.
 * 
 * @param string $studentName   Full name of the student
 * @param string $studentEmail  Email address where notification will be sent
 * @param string $status        Attendance status: "IN" or "OUT"
 * @param string $timestamp     Date and time of the attendance scan (e.g., "2024-01-15 08:30:00")
 * 
 * @return bool Returns true if email sent successfully, false on failure
 * 
 * @example
 * // Example usage:
 * $result = sendAttendanceEmailNotification(
 *     "Juan Dela Cruz",
 *     "parent@example.com",
 *     "IN",
 *     "2024-01-15 08:30:00"
 * );
 * 
 * if ($result) {
 *     echo "Email notification sent successfully!";
 * } else {
 *     echo "Failed to send email notification.";
 * }
 */
function sendAttendanceEmailNotification($studentName, $studentEmail, $status, $timestamp) {
    try {
        // Validate input parameters
        if (empty($studentName) || empty($studentEmail) || empty($status) || empty($timestamp)) {
            error_log("Email Notification Error: Missing required parameters");
            return false;
        }
        
        // Validate email format
        if (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Email Notification Error: Invalid email address - " . $studentEmail);
            return false;
        }
        
        // Validate status
        $status = strtoupper(trim($status));
        if ($status !== 'IN' && $status !== 'OUT') {
            error_log("Email Notification Error: Invalid status - " . $status);
            return false;
        }
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // ============================================================
        // SMTP Configuration - FILL IN YOUR CREDENTIALS HERE
        // ============================================================
        
        // Enable SMTP
        $mail->isSMTP();
        
        // SMTP server address (e.g., smtp.gmail.com, smtp.office365.com)
        $mail->Host = '';  // TODO: Enter your SMTP host here
        
        // Enable SMTP authentication
        $mail->SMTPAuth = true;
        
        // SMTP username (usually your email address)
        $mail->Username = '';  // TODO: Enter your SMTP username here
        
        // SMTP password
        $mail->Password = '';  // TODO: Enter your SMTP password here
        
        // Enable TLS encryption; `PHPMailer::ENCRYPTION_STARTTLS` also accepted
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // Use 'tls' for port 587
        
        // TCP port to connect to (465 for SSL, 587 for TLS)
        $mail->Port = 465;  // TODO: Change to 587 if using TLS
        
        // ============================================================
        // Sender and Recipient Configuration
        // ============================================================
        
        // Set sender email and name
        $mail->setFrom('', 'School Attendance System');  // TODO: Enter sender email
        
        // Add recipient (student or parent email)
        $mail->addAddress($studentEmail);
        
        // Optional: Add reply-to address
        // $mail->addReplyTo('info@school.com', 'School Information');
        
        // ============================================================
        // Email Content
        // ============================================================
        
        // Set email format to HTML
        $mail->isHTML(true);
        
        // Format the timestamp for display
        $formattedDateTime = date('F j, Y - g:i A', strtotime($timestamp));
        $formattedDate = date('F j, Y', strtotime($timestamp));
        $formattedTime = date('g:i A', strtotime($timestamp));
        
        // Determine status text and colors
        $statusText = ($status === 'IN') ? 'Time IN' : 'Time OUT';
        $statusColor = ($status === 'IN') ? '#4CAF50' : '#FF5722';
        $statusIcon = ($status === 'IN') ? '✅' : '🚪';
        
        // Email subject
        $mail->Subject = "Attendance Alert: " . htmlspecialchars($studentName) . " has " . $statusText;
        
        // HTML email body
        $mail->Body = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            background: ' . $statusColor . ';
            color: white;
            border-radius: 25px;
            font-weight: bold;
            font-size: 16px;
            margin: 15px 0;
        }
        .details-box {
            background: #f9f9f9;
            border-left: 4px solid ' . $statusColor . ';
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .detail-row {
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .detail-value {
            color: #333;
        }
        .note-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .note-box p {
            margin: 0;
            color: #856404;
        }
        .footer {
            background: #f4f4f4;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            .detail-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>' . $statusIcon . ' Attendance Alert</h1>
            <p>Academy of St. Joseph Attendance System</p>
        </div>
        
        <div class="content">
            <h2>Dear Parent/Guardian,</h2>
            <p>This is an automated attendance notification for your child.</p>
            
            <div class="status-badge">' . $statusText . '</div>
            
            <div class="details-box">
                <div class="detail-row">
                    <span class="detail-label">Student\'s Name:</span>
                    <span class="detail-value">' . htmlspecialchars($studentName) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">' . $statusText . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">' . $formattedDate . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">' . $formattedTime . '</span>
                </div>
            </div>
            
            <div class="note-box">
                <p><strong>📌 Note:</strong> This is an automated attendance notification. Please do not reply to this email.</p>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Academy of St. Joseph Claveria, Cagayan Inc.</strong></p>
            <p>Automated Attendance System</p>
            <p style="margin-top: 10px;">© ' . date('Y') . ' All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
        
        // Plain text alternative for email clients that don't support HTML
        $mail->AltBody = "ATTENDANCE ALERT\n\n" .
                         "Student's Name: " . $studentName . "\n" .
                         "Status: " . $statusText . "\n" .
                         "Date and Time: " . $formattedDateTime . "\n\n" .
                         "This is an automated attendance notification.\n" .
                         "Please do not reply to this email.\n\n" .
                         "Academy of St. Joseph Claveria, Cagayan Inc.\n" .
                         "Automated Attendance System";
        
        // Send the email
        $mail->send();
        
        // Log success
        error_log("Email notification sent successfully to: " . $studentEmail . " (Status: " . $status . ")");
        
        return true;
        
    } catch (Exception $e) {
        // Log the error
        error_log("Email notification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Configuration Helper Function
 * 
 * This function returns an array with the current SMTP configuration status.
 * Useful for checking if SMTP credentials have been configured.
 * 
 * @return array Configuration status
 */
function checkEmailConfiguration() {
    return [
        'configured' => false,
        'message' => 'Please configure SMTP settings in email_notification.php',
        'required_settings' => [
            'Host' => 'SMTP server address',
            'Username' => 'SMTP username',
            'Password' => 'SMTP password',
            'setFrom' => 'Sender email address'
        ]
    ];
}

?>
