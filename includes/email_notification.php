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
 * @param string $studentName    Full name of the student
 * @param string $studentEmail   Email address where notification will be sent
 * @param string $status         Attendance status: "IN" or "OUT"
 * @param string $timestamp      Date and time of the attendance scan (e.g., "2024-01-15 08:30:00")
 * @param string $studentLRN     Student's Learner Reference Number (optional)
 * @param string $studentSection Student's section (optional)
 * 
 * @return bool Returns true if email sent successfully, false on failure
 * 
 * @example
 * // Example usage:
 * $result = sendAttendanceEmailNotification(
 *     "Juan Dela Cruz",
 *     "parent@example.com",
 *     "IN",
 *     "2024-01-15 08:30:00",
 *     "123456789012",
 *     "Grade 10 - A"
 * );
 * 
 * if ($result) {
 *     echo "Email notification sent successfully!";
 * } else {
 *     echo "Failed to send email notification.";
 * }
 */
function sendAttendanceEmailNotification($studentName, $studentEmail, $status, $timestamp, $studentLRN = '', $studentSection = '') {
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
        $statusColor = ($status === 'IN') ? '#15803D' : '#DC2626'; // Green for IN, Red for OUT
        $statusIconPath = ($status === 'IN') 
            ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' // Check circle
            : 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'; // Logout
        
        // Email subject
        $mail->Subject = "Attendance Alert: " . htmlspecialchars($studentName) . " has " . $statusText;
        
        // Load the HTML email template
        $templatePath = __DIR__ . '/email_template.html';
        if (!file_exists($templatePath)) {
            error_log("Email Notification Error: Template file not found - " . $templatePath);
            return false;
        }
        
        $htmlTemplate = file_get_contents($templatePath);
        
        // Replace placeholders with actual values
        $replacements = [
            '{{STUDENT_NAME}}' => htmlspecialchars($studentName),
            '{{STUDENT_LRN}}' => htmlspecialchars($studentLRN ?: 'N/A'),
            '{{STUDENT_SECTION}}' => htmlspecialchars($studentSection ?: 'N/A'),
            '{{STATUS_TEXT}}' => $statusText,
            '{{STATUS_COLOR}}' => $statusColor,
            '{{STATUS_ICON_PATH}}' => $statusIconPath,
            '{{FORMATTED_DATE}}' => $formattedDate,
            '{{FORMATTED_TIME}}' => $formattedTime,
            '{{YEAR}}' => date('Y')
        ];
        
        $mail->Body = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $htmlTemplate
        );
        
        // Plain text alternative for email clients that don't support HTML
        $mail->AltBody = "ATTENDANCE ALERT\n\n" .
                         "Student's Name: " . $studentName . "\n" .
                         ($studentLRN ? "LRN: " . $studentLRN . "\n" : "") .
                         ($studentSection ? "Section: " . $studentSection . "\n" : "") .
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
