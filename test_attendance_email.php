<?php
/**
 * Test Email Template and SMTP Configuration
 * This file tests the attendance email system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

// Load email configuration
$emailConfig = require __DIR__ . '/config/email_config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Attendance Email</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #4CAF50; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #4CAF50; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .info { color: #2196F3; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 14px; }
        pre { background: #263238; color: #aed581; padding: 15px; border-radius: 4px; overflow-x: auto; }
        input, button { padding: 10px; margin: 5px 0; font-size: 14px; }
        button { background: #4CAF50; color: white; border: none; cursor: pointer; border-radius: 4px; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📧 Test Attendance Email System</h1>";

// Check configuration
echo "<div class='section'>
    <h3>1️⃣ Email Configuration Check</h3>";
echo "<table style='width:100%; border-collapse: collapse;'>";
echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>SMTP Host:</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" . htmlspecialchars($emailConfig['smtp_host']) . "</td></tr>";
echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>SMTP Port:</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" . htmlspecialchars($emailConfig['smtp_port']) . "</td></tr>";
echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>SMTP Security:</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" . htmlspecialchars($emailConfig['smtp_secure']) . "</td></tr>";
echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>Username:</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" . htmlspecialchars($emailConfig['smtp_username']) . "</td></tr>";
echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>Password Length:</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" . strlen($emailConfig['smtp_password']) . " characters</td></tr>";
echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>From Email:</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" . htmlspecialchars($emailConfig['from_email']) . "</td></tr>";
echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>Time In Notifications:</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" . ($emailConfig['send_on_time_in'] ? '✅ Enabled' : '❌ Disabled') . "</td></tr>";
echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>Time Out Notifications:</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" . ($emailConfig['send_on_time_out'] ? '✅ Enabled' : '❌ Disabled') . "</td></tr>";
echo "</table>";
echo "</div>";

// Send test email if requested
if (isset($_POST['send_test'])) {
    $testEmail = $_POST['test_email'] ?? '';
    $testType = $_POST['test_type'] ?? 'time_in';
    
    if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo "<div class='section'><p class='error'>❌ Invalid email address</p></div>";
    } else {
        echo "<div class='section'>
            <h3>2️⃣ Sending Test Email to: " . htmlspecialchars($testEmail) . "</h3>";
        
        // Create test student data
        $testStudent = [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'lrn' => '123456789012',
            'class' => 'Grade 10 - St. Joseph',
            'email' => $testEmail
        ];
        
        $testDetails = [
            'date' => date('F j, Y'),
            'time' => date('g:i A'),
            'section' => 'Grade 10 - St. Joseph'
        ];
        
        try {
            $mail = new PHPMailer(true);
            
            // Enable verbose debug output
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                echo "<div style='padding:4px; font-family:monospace; font-size:12px; color:#666;'>$str</div>";
            };
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $emailConfig['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['smtp_username'];
            $mail->Password = $emailConfig['smtp_password'];
            $mail->SMTPSecure = $emailConfig['smtp_secure'];
            $mail->Port = $emailConfig['smtp_port'];
            $mail->CharSet = $emailConfig['charset'];
            
            // Recipients
            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $mail->addReplyTo($emailConfig['reply_to_email'], $emailConfig['reply_to_name']);
            $mail->addAddress($testEmail, 'Test Parent/Guardian');
            
            // Content
            $studentName = trim($testStudent['first_name'] . ' ' . $testStudent['middle_name'] . ' ' . $testStudent['last_name']);
            $mail->Subject = str_replace('{student_name}', $studentName, $emailConfig['subject_' . $testType]);
            
            $mail->isHTML(true);
            
            // Generate email template (copy from mark_attendance.php)
            $statusColor = ($testType === 'time_in') ? '#4CAF50' : '#FF9800';
            $statusText = ($testType === 'time_in') ? 'Arrived at School' : 'Left School';
            
            $svgCheck = '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="12" fill="#4CAF50"/><path d="M7 13l3 3 7-7" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            $svgExit = '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="12" fill="#FF9800"/><path d="M10 8l4 4-4 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 12H6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            $svgUser = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="#4CAF50" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="7" r="4" stroke="#4CAF50" stroke-width="1.5"/></svg>';
            $svgDoc = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="#4CAF50" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="#4CAF50" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            $svgMap = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 10c0 6-9 11-9 11S3 16 3 10a9 9 0 1118 0z" stroke="#666" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="10" r="2" fill="#666"/></svg>';
            
            $badge = ($testType === 'time_in') ? $svgCheck : $svgExit;
            $displayDate = htmlspecialchars($testDetails['date']);
            $displayTime = htmlspecialchars($testDetails['time']);
            
            $mail->Body = '<!DOCTYPE html>' .
            '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' .
            '</head><body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f5f5f5;padding:24px 0;">' .
            '<tr><td align="center">' .
            '<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,0.08);">' .
            // Header with Logo on Left
            '<tr><td style="background:linear-gradient(135deg,#4CAF50 0%,#388E3C 100%);padding:24px 30px;">' .
                '<table width="100%" cellpadding="0" cellspacing="0" role="presentation">' .
                    '<tr>' .
                        // Logo Column
                        '<td style="width:80px;vertical-align:middle;">' .
                            '<div style="width:70px;height:70px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.15);">' .
                                '<img src="' . htmlspecialchars($emailConfig['base_url'] . '/assets/asj-logo.png') . '" alt="ASJ Logo" width="55" style="display:block;">' .
                            '</div>' .
                        '</td>' .
                        // School Info Column
                        '<td style="vertical-align:middle;padding-left:20px;">' .
                            '<h1 style="margin:0 0 6px;color:#fff;font-size:19px;font-weight:700;line-height:1.3;letter-spacing:-0.3px;">' . htmlspecialchars($emailConfig['school_name']) . '</h1>' .
                            '<p style="margin:0;color:rgba(255,255,255,0.92);font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:0.8px;">Attendance Monitoring System</p>' .
                        '</td>' .
                    '</tr>' .
                '</table>' .
            '</td></tr>' .
            '<tr><td style="padding:24px 20px 8px;text-align:center;">' .
                '<div style="display:inline-block;margin-bottom:12px;">' . $badge . '</div>' .
                '<h2 style="margin:8px 0 6px;color:' . $statusColor . ';font-size:18px;font-weight:700;">' . htmlspecialchars($statusText) . '</h2>' .
                '<p style="margin:0;color:#666;font-size:13px;">' . $displayDate . ' &nbsp;•&nbsp; ' . $displayTime . '</p>' .
            '</td></tr>' .
            '<tr><td style="padding:18px 20px 0;">' .
                '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-radius:8px;background:#fbfcfd;border:1px solid #eef2f6;">' .
                    '<tr><td style="padding:16px 16px;border-left:6px solid #4CAF50;">' .
                        '<table width="100%" cellpadding="0" cellspacing="0" role="presentation">' .
                            '<tr><td style="vertical-align:top;padding-bottom:10px;">' .
                                '<div style="font-size:14px;color:#888;text-transform:uppercase;letter-spacing:0.6px;font-weight:600;">Student</div>' .
                                '<div style="font-size:18px;color:#222;font-weight:700;margin-top:4px;">' . htmlspecialchars($studentName) . '</div>' .
                            '</td></tr>' .
                            '<tr><td style="padding-top:6px;">' .
                                '<table width="100%" cellpadding="6" cellspacing="0" role="presentation">' .
                                    '<tr><td style="width:30%;font-size:12px;color:#666;">LRN</td><td style="text-align:right;font-weight:600;color:#222;">' . htmlspecialchars($testStudent['lrn']) . '</td></tr>' .
                                    '<tr><td style="width:30%;font-size:12px;color:#666;">Section</td><td style="text-align:right;font-weight:600;color:#222;">' . htmlspecialchars($testStudent['class']) . '</td></tr>' .
                                '</table>' .
                            '</td></tr>' .
                        '</table>' .
                    '</td></tr>' .
                '</table>' .
            '</td></tr>' .
            '<tr><td style="padding:18px 20px 20px;">' .
                '<table width="100%" cellpadding="10" cellspacing="0" role="presentation" style="border:1px solid #eef2f6;border-radius:8px;">' .
                    '<tr style="background:#fafbfc;color:#666;font-size:12px;text-transform:uppercase;font-weight:700;">' .
                        '<td style="padding:10px 12px;">Action</td><td style="padding:10px 12px;text-align:right;">' . htmlspecialchars($statusText) . '</td>' .
                    '</tr>' .
                    '<tr>' .
                        '<td style="padding:10px 12px;color:#666;font-size:12px;">Timestamp</td><td style="padding:10px 12px;text-align:right;font-weight:600;color:#222;">' . $displayDate . ' • ' . $displayTime . '</td>' .
                    '</tr>' .
                '</table>' .
            '</td></tr>' .
            '<tr><td style="background:#f9fafb;padding:22px 20px;border-top:1px solid #eef2f6;text-align:center;color:#666;font-size:12px;">' .
                '<div style="font-weight:700;color:#222;">' . htmlspecialchars($emailConfig['school_name']) . '</div>' .
                '<div style="margin-top:6px;">' . htmlspecialchars($emailConfig['school_address']) . '</div>' .
                '<div style="margin-top:6px;">Email: <a href="mailto:' . htmlspecialchars($emailConfig['support_email']) . '" style="color:#4CAF50;text-decoration:none;">' . htmlspecialchars($emailConfig['support_email']) . '</a></div>' .
                '<div style="margin-top:10px;color:#999;font-size:11px;">This is an automated message from the ASJ Attendance Monitoring System. Please do not reply to this email.</div>' .
                '<div style="margin-top:8px;color:#bbb;font-size:11px;">&copy; ' . date('Y') . ' ' . htmlspecialchars($emailConfig['school_name']) . '. All rights reserved.</div>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</body></html>';
            
            // Plain text version
            $mail->AltBody = "ATTENDANCE ALERT\n\nDear Parent/Guardian,\n\nStatus: $statusText\n\nStudent Details:\nName: $studentName\nLRN: {$testStudent['lrn']}\nSection: {$testStudent['class']}\nDate: {$testDetails['date']}\nTime: {$testDetails['time']}\n\nThis is a test email from the ASJ Attendance Monitoring System.";
            
            // Send
            echo "<h4>📤 Sending email...</h4>";
            $mail->send();
            
            echo "<p class='success'>✅ Email sent successfully!</p>";
            echo "<p class='info'>Check your inbox at <strong>" . htmlspecialchars($testEmail) . "</strong></p>";
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Email sending failed!</p>";
            echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        echo "</div>";
    }
}

// Test form
echo "<div class='section'>
    <h3>🧪 Send Test Email</h3>
    <form method='post'>
        <div>
            <label><strong>Email Address:</strong></label><br>
            <input type='email' name='test_email' required placeholder='parent@example.com' style='width:300px;'>
        </div>
        <div>
            <label><strong>Test Type:</strong></label><br>
            <select name='test_type' style='padding:10px; width:320px;'>
                <option value='time_in'>Time IN (Green Badge)</option>
                <option value='time_out'>Time OUT (Orange Badge)</option>
            </select>
        </div>
        <div>
            <button type='submit' name='send_test'>📧 Send Test Email</button>
        </div>
    </form>
</div>";

echo "</div>
</body>
</html>";
?>
