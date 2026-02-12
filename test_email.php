<?php
/**
 * Email Notification Test Script
 * 
 * This file allows you to manually test the email notification system
 * for the Academy of St. Joseph Attendance Checker.
 * 
 * INSTRUCTIONS:
 * 1. Configure the SMTP settings below (lines 30-40)
 * 2. Set the recipient email address (line 48)
 * 3. Run this script from command line: php test_email.php
 *    OR access it via browser: http://yourdomain.com/test_email.php
 * 
 * @author Academy of St. Joseph Attendance System
 * @version 1.0
 * @date November 8, 2025
 */

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if PHPMailer is installed via Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback: Load PHPMailer manually from libs directory
    if (file_exists(__DIR__ . '/libs/PHPMailer/Exception.php')) {
        require_once __DIR__ . '/libs/PHPMailer/Exception.php';
        require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/libs/PHPMailer/SMTP.php';
    } else {
        die("❌ ERROR: PHPMailer library not found. Please install PHPMailer first.\n");
    }
}

// ============================================================
// CONFIGURATION SECTION - MODIFY THESE VALUES
// ============================================================

// SMTP Server Configuration
$smtp_config = [
    'host'      => 'smtp.gmail.com',              // SMTP server (e.g., smtp.gmail.com, smtp.office365.com)
    'username'  => 'your-email@gmail.com',        // Your SMTP username (email address)
    'password'  => 'your-app-password',           // Your SMTP password or app password
    'port'      => 587,                           // Port: 587 for TLS, 465 for SSL
    'encryption'=> 'tls',                         // 'tls' or 'ssl'
];

// Email Configuration
$email_config = [
    'from_email' => 'attendance@school.edu.ph',   // Sender email address
    'from_name'  => 'ASJ Attendance System',      // Sender name
    'to_email'   => 'example@school.edu.ph',      // Recipient email (CHANGE THIS!)
    'to_name'    => 'Test Recipient',             // Recipient name
];

// Student Information (Example Data)
$student_info = [
    'name'   => 'Juan Dela Cruz',
    'status' => 'Present',
    'date'   => date('F j, Y'),
    'time'   => date('g:i A'),
];

// School Information
$school_info = [
    'name'     => 'Academy of St. Joseph, Claveria Cagayan Inc.',
    'address'  => 'Claveria, Cagayan, Philippines',
    'email'    => 'attendance@school.edu.ph',
    'phone'    => '(078) 123-4567',
    'logo_url' => 'https://yourdomain.com/assets/asj-logo.png', // Update with actual URL
];

// ============================================================
// EMAIL SENDING FUNCTION
// ============================================================

/**
 * Send a test email using PHPMailer with the school's branded template
 * 
 * @param array $smtp_config SMTP server configuration
 * @param array $email_config Email sender/recipient configuration
 * @param array $student_info Student information for the email
 * @param array $school_info School information for the email
 * @return array Result with success status and message
 */
function sendTestEmail($smtp_config, $email_config, $student_info, $school_info) {
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Enable verbose debug output (comment out in production)
        // $mail->SMTPDebug = 2;
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_config['username'];
        $mail->Password   = $smtp_config['password'];
        $mail->Port       = $smtp_config['port'];
        
        // Set encryption method
        if ($smtp_config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // Recipients
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addAddress($email_config['to_email'], $email_config['to_name']);
        
        // Optional: Add reply-to address
        $mail->addReplyTo($school_info['email'], $school_info['name']);
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        // Email subject
        $mail->Subject = '📩 Attendance Alert – Example Notification';
        
        // Build the HTML email body with inline CSS
        $mail->Body = buildEmailHTML($student_info, $school_info);
        
        // Plain text alternative for email clients that don't support HTML
        $mail->AltBody = buildPlainTextEmail($student_info, $school_info);
        
        // Send the email
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Email sent successfully to ' . $email_config['to_email']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Email sending failed: ' . $mail->ErrorInfo,
            'error'   => $e->getMessage()
        ];
    }
}

/**
 * Build HTML email body with modern design and school branding
 * 
 * @param array $student_info Student information
 * @param array $school_info School information
 * @return string HTML email content
 */
function buildEmailHTML($student_info, $school_info) {
    $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Attendance Alert Notification</title>
</head>
<body style="margin: 0; padding: 0; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background-color: #F9FAFB;">
    <div style="width: 100%; background-color: #F9FAFB; padding: 40px 0;">
        <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(30, 58, 138, 0.1);">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); padding: 40px 30px; text-align: center;">
                <div style="margin-bottom: 20px;">
                    <img src="' . htmlspecialchars($school_info['logo_url']) . '" alt="School Logo" style="max-width: 100px; height: auto; margin: 0 auto; border-radius: 50%; background-color: white; padding: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); display: block;" width="100" height="100">
                </div>
                <h1 style="color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; letter-spacing: 0.5px;">📩 Attendance Alert</h1>
                <p style="color: #E0E7FF; font-size: 14px; margin: 10px 0 0 0;">' . htmlspecialchars($school_info['name']) . '</p>
            </div>
            
            <!-- Content -->
            <div style="padding: 40px 30px;">
                <p style="font-size: 18px; color: #1E3A8A; font-weight: 600; margin: 0 0 20px 0;">Hello ' . htmlspecialchars($student_info['name']) . ',</p>
                
                <p style="color: #4B5563; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                    This is a sample email to test if the attendance notification alert is working properly. 
                    Your attendance has been successfully recorded in our system.
                </p>
                
                <div style="text-align: center;">
                    <span style="display: inline-block; padding: 12px 28px; background-color: #10B981; color: #ffffff; border-radius: 30px; font-weight: 700; font-size: 16px; margin: 0 0 30px 0; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">✅ ' . htmlspecialchars($student_info['status']) . '</span>
                </div>
                
                <!-- Details Box -->
                <div style="background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%); border-left: 5px solid #FBBF24; border-radius: 12px; padding: 30px; margin: 30px 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #E5E7EB;">
                            <td style="padding: 15px 0; font-weight: 600; color: #1E3A8A; font-size: 15px;">
                                <span style="margin-right: 8px;">👤</span> Student Name
                            </td>
                            <td style="padding: 15px 0; color: #374151; font-size: 15px; font-weight: 500; text-align: right;">
                                ' . htmlspecialchars($student_info['name']) . '
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E5E7EB;">
                            <td style="padding: 15px 0; font-weight: 600; color: #1E3A8A; font-size: 15px;">
                                <span style="margin-right: 8px;">📊</span> Status
                            </td>
                            <td style="padding: 15px 0; color: #374151; font-size: 15px; font-weight: 500; text-align: right;">
                                ' . htmlspecialchars($student_info['status']) . '
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E5E7EB;">
                            <td style="padding: 15px 0; font-weight: 600; color: #1E3A8A; font-size: 15px;">
                                <span style="margin-right: 8px;">📅</span> Date
                            </td>
                            <td style="padding: 15px 0; color: #374151; font-size: 15px; font-weight: 500; text-align: right;">
                                ' . htmlspecialchars($student_info['date']) . '
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 15px 0; font-weight: 600; color: #1E3A8A; font-size: 15px;">
                                <span style="margin-right: 8px;">⏰</span> Time
                            </td>
                            <td style="padding: 15px 0; color: #374151; font-size: 15px; font-weight: 500; text-align: right;">
                                ' . htmlspecialchars($student_info['time']) . '
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Message Box -->
                <div style="background-color: #FEF3C7; border: 2px solid #FBBF24; border-radius: 12px; padding: 20px; margin: 30px 0;">
                    <p style="margin: 0; color: #92400E; font-size: 14px; line-height: 1.6;">
                        <strong style="color: #78350F;">📌 Important Note:</strong> This is an automated attendance notification. 
                        Please do not reply to this email. If you have any questions or concerns, 
                        please contact the school office directly.
                    </p>
                </div>
                
                <p style="color: #6B7280; font-size: 14px; margin: 30px 0 0 0;">
                    Thank you,<br>
                    <strong>' . htmlspecialchars($school_info['name']) . '</strong>
                </p>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #1E3A8A; padding: 30px; text-align: center;">
                <img src="' . htmlspecialchars($school_info['logo_url']) . '" alt="School Logo" style="max-width: 60px; height: auto; margin: 0 auto 15px auto; opacity: 0.9; display: block;" width="60" height="60">
                <p style="color: #ffffff; font-size: 16px; font-weight: 700; margin: 0 0 5px 0;">' . htmlspecialchars($school_info['name']) . '</p>
                <p style="color: #E0E7FF; font-size: 13px; margin: 0 0 20px 0;">Excellence in Education</p>
                <p style="color: #BFDBFE; font-size: 12px; line-height: 1.8; margin: 0;">
                    📍 ' . htmlspecialchars($school_info['address']) . '<br>
                    📧 Email: <a href="mailto:' . htmlspecialchars($school_info['email']) . '" style="color: #FBBF24; text-decoration: none;">' . htmlspecialchars($school_info['email']) . '</a><br>
                    📞 Phone: ' . htmlspecialchars($school_info['phone']) . '
                </p>
                <p style="color: #93C5FD; font-size: 11px; margin: 20px 0 0 0; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                    © ' . date('Y') . ' Academy of St. Joseph. All rights reserved.<br>
                    Automated Attendance Tracking System
                </p>
            </div>
            
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Build plain text email for clients that don't support HTML
 * 
 * @param array $student_info Student information
 * @param array $school_info School information
 * @return string Plain text email content
 */
function buildPlainTextEmail($student_info, $school_info) {
    $text = "ATTENDANCE ALERT - EXAMPLE NOTIFICATION\n";
    $text .= str_repeat("=", 50) . "\n\n";
    $text .= "Hello " . $student_info['name'] . ",\n\n";
    $text .= "This is a sample email to test if the attendance notification alert is working.\n\n";
    $text .= "ATTENDANCE DETAILS:\n";
    $text .= str_repeat("-", 50) . "\n";
    $text .= "Student Name: " . $student_info['name'] . "\n";
    $text .= "Status: " . $student_info['status'] . "\n";
    $text .= "Date: " . $student_info['date'] . "\n";
    $text .= "Time: " . $student_info['time'] . "\n";
    $text .= str_repeat("-", 50) . "\n\n";
    $text .= "IMPORTANT NOTE:\n";
    $text .= "This is an automated attendance notification.\n";
    $text .= "Please do not reply to this email.\n\n";
    $text .= "Thank you,\n";
    $text .= $school_info['name'] . "\n\n";
    $text .= str_repeat("=", 50) . "\n";
    $text .= $school_info['name'] . "\n";
    $text .= $school_info['address'] . "\n";
    $text .= "Email: " . $school_info['email'] . "\n";
    $text .= "Phone: " . $school_info['phone'] . "\n";
    $text .= "\n© " . date('Y') . " Academy of St. Joseph. All rights reserved.\n";
    
    return $text;
}

// ============================================================
// MAIN EXECUTION
// ============================================================

// Display header
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║     EMAIL NOTIFICATION TEST - ATTENDANCE ALERT SYSTEM          ║\n";
echo "║     Academy of St. Joseph, Claveria Cagayan Inc.              ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Check if SMTP is configured
if ($smtp_config['username'] === 'your-email@gmail.com' || 
    $smtp_config['password'] === 'your-app-password') {
    echo "⚠️  WARNING: SMTP credentials not configured!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Please edit test_email.php and configure the following:\n";
    echo "  • SMTP Host (line 32)\n";
    echo "  • SMTP Username (line 33)\n";
    echo "  • SMTP Password (line 34)\n";
    echo "  • Sender Email (line 40)\n";
    echo "  • Recipient Email (line 42)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    exit(1);
}

// Display configuration
echo "📧 Email Configuration:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  SMTP Host: " . $smtp_config['host'] . "\n";
echo "  SMTP Port: " . $smtp_config['port'] . "\n";
echo "  Encryption: " . strtoupper($smtp_config['encryption']) . "\n";
echo "  From: " . $email_config['from_email'] . "\n";
echo "  To: " . $email_config['to_email'] . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Display test data
echo "📝 Test Data:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Student: " . $student_info['name'] . "\n";
echo "  Status: " . $student_info['status'] . "\n";
echo "  Date: " . $student_info['date'] . "\n";
echo "  Time: " . $student_info['time'] . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Send test email
echo "📤 Sending test email...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$result = sendTestEmail($smtp_config, $email_config, $student_info, $school_info);

// Display result
echo "\n";
if ($result['success']) {
    echo "✅ SUCCESS!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo $result['message'] . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "✓ Email sent successfully!\n";
    echo "✓ Check the inbox of: " . $email_config['to_email'] . "\n";
    echo "✓ Don't forget to check the spam/junk folder if you don't see it.\n\n";
    
    // Log success
    error_log("[" . date('Y-m-d H:i:s') . "] Test email sent successfully to: " . $email_config['to_email']);
} else {
    echo "❌ FAILED!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo $result['message'] . "\n";
    if (isset($result['error'])) {
        echo "Error details: " . $result['error'] . "\n";
    }
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "❌ Email sending failed!\n";
    echo "⚠️  Please check your SMTP configuration and try again.\n\n";
    
    // Log failure
    error_log("[" . date('Y-m-d H:i:s') . "] Test email failed: " . $result['message']);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "For more information, check the PHP error log.\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

?>
