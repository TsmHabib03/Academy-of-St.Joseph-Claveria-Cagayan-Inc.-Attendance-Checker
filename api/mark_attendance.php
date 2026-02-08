<?php
/**
 * Simple Time In / Time Out Attendance System
 * - First scan of the day = Time In
 * - Second scan of the day = Time Out
 * - No more scans allowed after Time Out
 * 
 * Returns JSON responses only
 */

// Suppress ALL output except JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Start output buffering immediately
ob_start();

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/database.php';

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

// Load email configuration
$emailConfig = require_once __DIR__ . '/../config/email_config.php';

// Load SMS sender for dual-channel notifications
require_once __DIR__ . '/../includes/sms_sender.php';

// Clear any buffered output
ob_end_clean();
ob_start();

// Validate HTTP method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Invalid request method. POST required.'
    ]);
    exit;
}

/**
 * Send Time In/Out email notification to parent
 * 
 * @param array $emailConfig Email configuration
 * @param array $student Student information
 * @param string $type 'time_in' or 'time_out'
 * @param array $details Attendance details (time, date, section)
 * @return bool True if email sent successfully
 */
function sendAttendanceEmail($emailConfig, $student, $type, $details, $userType = 'student') {
    try {
        // Check if email notifications are enabled
        if (!$emailConfig['send_on_' . $type]) {
            return true; // Disabled, return success
        }
        
        // Determine recipient email based on user type
        $recipientEmail = $student['email'] ?? '';
        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email for user: " . ($student['lrn'] ?? 'unknown'));
            return false;
        }
        
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = $emailConfig['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['smtp_username'];
        $mail->Password = $emailConfig['smtp_password'];
        $mail->SMTPSecure = $emailConfig['smtp_secure'];
        $mail->Port = $emailConfig['smtp_port'];
        $mail->CharSet = $emailConfig['charset'];
        
        // Sender and recipient
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addReplyTo($emailConfig['reply_to_email'], $emailConfig['reply_to_name']);
        // If teacher, send to teacher's email; otherwise send to parent/guardian
        $recipientName = ($userType === 'teacher') ? trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) : 'Parent/Guardian';
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Email subject
        $studentName = trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']);
        $mail->Subject = str_replace('{student_name}', $studentName, $emailConfig['subject_' . $type]);
        
        // HTML Email body
        $mail->isHTML(true);
        $mail->Body = generateEmailTemplate($emailConfig, $student, $type, $details, $userType);
        
        // Plain text alternative
        $mail->AltBody = generatePlainTextEmail($student, $type, $details, $userType);
        
        // Send email
        $mail->send();
        error_log("Email sent successfully to: " . $student['email'] . " (Type: $type)");
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate HTML email template
 */
function generateEmailTemplate($emailConfig, $student, $type, $details, $userType = 'student') {
    $studentName = trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']);
    $statusColor = ($type === 'time_in') ? '#4CAF50' : '#FF9800';
    $statusText = ($type === 'time_in') ? 'Arrived at School' : 'Left School';
        // SVG icons (inline for email compatibility)
        $svgCheck = '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="12" fill="#4CAF50"/><path d="M7 13l3 3 7-7" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $svgExit = '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="12" fill="#FF9800"/><path d="M10 8l4 4-4 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 12H6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $svgClock = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 7v6l4 2" stroke="#666" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9" stroke="#666" stroke-width="1.5"/></svg>';
        $svgUser = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="#4CAF50" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="7" r="4" stroke="#4CAF50" stroke-width="1.5"/></svg>';
        $svgDoc = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="#4CAF50" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="#4CAF50" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $svgMap = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 10c0 6-9 11-9 11S3 16 3 10a9 9 0 1118 0z" stroke="#666" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="10" r="2" fill="#666"/></svg>';

        $badge = ($type === 'time_in') ? $svgCheck : $svgExit;

        // Timestamp display (use details if provided)
        $displayDate = htmlspecialchars($details['date'] ?? date('F j, Y'));
        $displayTime = htmlspecialchars($details['time'] ?? date('g:i A'));

        // Build HTML email (table-based, inline CSS)
        return '<!DOCTYPE html>' .
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
        // Badge and summary
        '<tr><td style="padding:24px 20px 8px;text-align:center;">' .
            '<div style="display:inline-block;margin-bottom:12px;">' . $badge . '</div>' .
            '<h2 style="margin:8px 0 6px;color:' . $statusColor . ';font-size:18px;font-weight:700;">' . htmlspecialchars($statusText) . '</h2>' .
            '<p style="margin:0;color:#666;font-size:13px;">' . $displayDate . ' &nbsp;•&nbsp; ' . $displayTime . '</p>' .
        '</td></tr>' .
        // Student card
        '<tr><td style="padding:18px 20px 0;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-radius:8px;background:#fbfcfd;border:1px solid #eef2f6;">' .
                '<tr><td style="padding:16px 16px;border-left:6px solid #4CAF50;">' .
                    '<table width="100%" cellpadding="0" cellspacing="0" role="presentation">' .
                        '<tr><td style="vertical-align:top;padding-bottom:10px;">' .
                            '<div style="display:flex;align-items:center;gap:12px;">' .
                                '<div style="width:44px;height:44px;border-radius:6px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(0,0,0,0.06);">' . $svgUser . '</div>' .
                                '<div>' .
                                                            '<div style="font-size:14px;color:#888;text-transform:uppercase;letter-spacing:0.6px;font-weight:600;">' . ($userType === 'teacher' ? 'Teacher' : 'Student') . '</div>' .
                                                            '<div style="font-size:18px;color:#222;font-weight:700;margin-top:4px;">' . htmlspecialchars($studentName) . '</div>' .
                                '</div>' .
                            '</div>' .
                        '</td></tr>' .
                        '<tr><td style="padding-top:6px;">' .
                            '<table width="100%" cellpadding="6" cellspacing="0" role="presentation">' .
                                '<tr><td style="width:30%;font-size:12px;color:#666;">' . $svgDoc . ' <span style="margin-left:6px;">' . ($userType === 'teacher' ? 'Employee Number' : 'LRN') . '</span></td><td style="text-align:right;font-weight:600;color:#222;">' . htmlspecialchars(($userType === 'teacher') ? ($student['employee_number'] ?? $student['employee_id'] ?? '') : ($student['lrn'] ?? '')) . '</td></tr>' .
                                '<tr><td style="width:30%;font-size:12px;color:#666;">' . $svgMap . ' <span style="margin-left:6px;">' . ($userType === 'teacher' ? 'Department' : 'Section') . '</span></td><td style="text-align:right;font-weight:600;color:#222;">' . htmlspecialchars($student['class'] ?? $student['department'] ?? '') . '</td></tr>' .
                            '</table>' .
                        '</td></tr>' .
                    '</table>' .
                '</td></tr>' .
            '</table>' .
        '</td></tr>' .
        // Details table
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
        // Footer
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
}

/**
 * Generate plain text email (fallback)
 */
function generatePlainTextEmail($student, $type, $details, $userType = 'student') {
    $studentName = trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']);
    $statusText = ($type === 'time_in') ? 'Arrived at School' : 'Left School';
    
    $recipientLabel = ($userType === 'teacher') ? 'Teacher' : 'Parent/Guardian';
    $identifierLabel = ($userType === 'teacher') ? 'Employee ID' : 'LRN';
    $identifier = $student['lrn'] ?? $student['employee_number'] ?? $student['employee_id'] ?? '';

    return "ATTENDANCE ALERT\n\n" .
           "Dear $recipientLabel,\n\n" .
           "Status: $statusText\n\n" .
           "$recipientLabel Details:\n" .
           "Name: $studentName\n" .
           "$identifierLabel: $identifier\n" .
           "Section/Department: " . ($student['class'] ?? $student['department'] ?? '') . "\n" .
           "Date: {$details['date']}\n" .
           ($type === 'time_in' ? 'Time In' : 'Time Out') . ": {$details['time']}\n\n" .
           "This is an automated notification from the school attendance system.\n\n" .
           "Note: This is an automated message. Please do not reply to this email.";
}

/**
 * Send SMS notification for attendance events
 * Dual-channel notification - sends SMS alongside email
 * 
 * @param PDO $db Database connection
 * @param array $student Student information (must include mobile_number)
 * @param string $type 'time_in' or 'time_out'
 * @param array $details Attendance details (time, date, section)
 * @return bool True if SMS sent successfully
 */
function sendAttendanceSms($db, $student, $type, $details, $userType = 'student') {
    try {
        $smsSender = new SmsSender($db);
        
        // Check if SMS is enabled
        if (!$smsSender->isEnabled()) {
            error_log("SMS notifications disabled - skipping SMS for LRN: " . $student['lrn']);
            return true; // Not an error, just disabled
        }
        
        // Check if student has a valid mobile number
        if (empty($student['mobile_number'])) {
            error_log("No mobile number for LRN: " . $student['lrn']);
            return false;
        }
        
        // Build student name
        $studentName = trim($student['first_name'] . ' ' . $student['last_name']);
        
        // Get SMS config for template
        $smsConfig = require __DIR__ . '/../config/sms_config.php';
        
        // Check if SMS should be sent for this event type
        $configKey = 'send_on_' . $type;
        if (!($smsConfig[$configKey] ?? false)) {
            error_log("SMS disabled for event type: $type");
            return true; // Not an error, just disabled for this type
        }
        
        // Build SMS message using template; choose teacher template when appropriate
        $template = $smsConfig['templates'][$type][$userType] ?? $smsConfig['templates'][$type]['student'] ?? 
                "{name} has " . ($type === 'time_in' ? 'arrived at' : 'left') . " school at {time} on {date}.";
        
        $message = str_replace(
            ['{name}', '{date}', '{time}', '{section}', '{school}'],
            [
                $studentName,
                $details['date'],
                $details['time'],
                $student['class'],
                $smsConfig['school_name'] ?? 'ASJ Claveria'
            ],
            $template
        );
        
        // Send the SMS
        $recipientType = ($userType === 'teacher') ? 'teacher' : 'parent';
        $recipientId = $student['lrn'] ?? $student['employee_number'] ?? $student['employee_id'] ?? '';
        $result = $smsSender->send(
            $student['mobile_number'],
            $message,
            $recipientType,
            $recipientId,
            $type
        );
        
        if ($result['success']) {
            error_log("SMS sent successfully to: " . $student['mobile_number'] . " (Type: $type)");
            return true;
        } else {
            error_log("SMS sending failed: " . ($result['message'] ?? 'Unknown error'));
            return false;
        }
        
    } catch (Exception $e) {
        error_log("SMS notification error: " . $e->getMessage());
        return false;
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check database connection
    if ($db === null) {
        throw new Exception('Database connection failed');
    }
    
    $scannedCode = trim($_POST['lrn'] ?? '');

    if (empty($scannedCode)) {
        throw new Exception('QR code data is required');
    }

    // Allow teacher QR payloads prefixed with TEACHER: (e.g. TEACHER:EMP-2026-001)
    $isTeacher = false;
    $user = null;
    $userType = 'student';

    if (stripos($scannedCode, 'TEACHER:') === 0) {
        // Teacher QR payload (uses Employee Number - 7 digits)
        $employeeNumber = substr($scannedCode, 8);
        $isTeacher = true;
        $userType = 'teacher';
        $scannedCode = $employeeNumber; // normalize for further queries
    }

    // Determine if this is a student LRN (11-13 digits)
    $isStudent = preg_match('/^[0-9]{11,13}$/', $scannedCode);
    
    if ($isStudent) {
        // Get student details
        $student_query = "SELECT * FROM students WHERE lrn = :code";
        $student_stmt = $db->prepare($student_query);
        $student_stmt->bindParam(':code', $scannedCode, PDO::PARAM_STR);
        $student_stmt->execute();
        
        if ($student_stmt->rowCount() === 0) {
            // Not found as student, might be a teacher employee ID that happens to be numeric
            $isStudent = false;
        } else {
            $user = $student_stmt->fetch(PDO::FETCH_ASSOC);
            $user['lrn'] = $scannedCode; // Ensure LRN is set
            $userType = 'student';
        }
    }
    
    // If not a student, check if it's a teacher
    if (!$isStudent) {
        // Determine which identifier columns exist on `teachers`
        $colCheck = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers'");
        $colCheck->execute();
        $teacherCols = $colCheck->fetchAll(PDO::FETCH_COLUMN);

        $hasEmpNum = in_array('employee_number', $teacherCols);
        $hasEmpId = in_array('employee_id', $teacherCols);

        if ($hasEmpNum && $hasEmpId) {
            $teacher_query = "SELECT * FROM teachers WHERE employee_number = :code OR employee_id = :code LIMIT 1";
        } elseif ($hasEmpNum) {
            $teacher_query = "SELECT * FROM teachers WHERE employee_number = :code LIMIT 1";
        } elseif ($hasEmpId) {
            $teacher_query = "SELECT * FROM teachers WHERE employee_id = :code LIMIT 1";
        } else {
            // No identifier columns found on teachers table
            $teacher_query = null;
        }

        if ($teacher_query) {
            $teacher_stmt = $db->prepare($teacher_query);
            $teacher_stmt->bindParam(':code', $scannedCode, PDO::PARAM_STR);
            $teacher_stmt->execute();

            if ($teacher_stmt->rowCount() > 0) {
                $user = $teacher_stmt->fetch(PDO::FETCH_ASSOC);
                $user['lrn'] = $scannedCode; // Use employee_number/employee_id as identifier (backwards compatible)
                $user['class'] = $user['department'] ?? 'Faculty';
                $isTeacher = true;
                $userType = 'teacher';
            }
        }
    }
    
    // If neither student nor teacher found
    if (!$user) {
        throw new Exception('User not found in the system. Please register first.');
    }
    
    // Set common variables for compatibility
    $student = $user; // Use $student variable for backward compatibility
    $lrn = $scannedCode;
    
    // Get current date and time
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Start a transaction to prevent race conditions
    $db->beginTransaction();
    
    try {
        // Use the appropriate table based on user type
        $attendanceTable = $isTeacher ? 'teacher_attendance' : 'attendance';
        // idColumn will point to the identifier column in attendance table
        $idColumn = $isTeacher ? 'employee_id' : 'lrn';
        $userLabel = $isTeacher ? 'Teacher' : 'Student';

        // Determine student's section/grade for schedule lookup
        $student_section_name = trim($student['section'] ?? $student['class'] ?? '');
        $student_grade = trim($student['class'] ?? '');

        // Lookup applicable schedule: prefer section match, then grade_level, then default
        $schedule = null;
        try {
            $sched_sql = "SELECT s.* FROM attendance_schedules s LEFT JOIN sections sec ON s.section_id = sec.id
                          WHERE s.is_active = 1 AND (
                              (sec.section_name = :section_name AND s.section_id IS NOT NULL)
                              OR (s.grade_level = :grade_level AND s.grade_level IS NOT NULL)
                              OR s.is_default = 1
                          )
                          ORDER BY (sec.section_name = :section_name) DESC, (s.grade_level = :grade_level) DESC, s.is_default DESC
                          LIMIT 1";
            $sched_stmt = $db->prepare($sched_sql);
            $sched_stmt->bindParam(':section_name', $student_section_name, PDO::PARAM_STR);
            $sched_stmt->bindParam(':grade_level', $student_grade, PDO::PARAM_STR);
            $sched_stmt->execute();
            $schedule = $sched_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            // If schedule lookup fails, fallback to null (will use sensible defaults)
            error_log('Schedule lookup failed: ' . $e->getMessage());
            $schedule = null;
        }

        // Default schedule values if none found
        if (!$schedule) {
            $schedule = [
                'morning_start' => '06:00:00',
                'morning_end' => '11:59:00',
                'morning_late_after' => '07:30:00',
                'afternoon_start' => '12:00:00',
                'afternoon_end' => '18:00:00',
                'afternoon_late_after' => '13:30:00'
            ];
        }

        // Normalize schedule time fields to H:i:s and provide safe defaults on parse failure
        $scheduleDefaults = [
            'morning_start' => '06:00:00',
            'morning_end' => '11:59:00',
            'morning_late_after' => '07:30:00',
            'afternoon_start' => '12:00:00',
            'afternoon_end' => '18:00:00',
            'afternoon_late_after' => '13:30:00'
        ];
        $timeKeys = array_keys($scheduleDefaults);
        foreach ($timeKeys as $tk) {
            $val = $schedule[$tk] ?? '';
            if (empty($val)) { $schedule[$tk] = $scheduleDefaults[$tk]; continue; }

            $parsed = false;
            // Try common formats
            $formats = ['H:i:s', 'H:i', 'g:i A', 'g:i a'];
            foreach ($formats as $fmt) {
                $dt = DateTime::createFromFormat($fmt, $val);
                if ($dt && $dt->format($fmt) === $val) { $schedule[$tk] = $dt->format('H:i:s'); $parsed = true; break; }
            }
            if (!$parsed) {
                try {
                    $dt = new DateTime($val);
                    $schedule[$tk] = $dt->format('H:i:s');
                    $parsed = true;
                } catch (Exception $e) {
                    $schedule[$tk] = $scheduleDefaults[$tk];
                }
            }
        }

        // Determine assigned session (AM/PM) from teacher.shift or sections.shift when available
        $assignedSession = null; // 'morning' or 'afternoon' or null
        try {
            if ($isTeacher) {
                if (!empty($user['shift'])) {
                    $s = strtolower(trim($user['shift']));
                    if (strpos($s, 'am') !== false) { $assignedSession = 'morning'; }
                    elseif (strpos($s, 'pm') !== false) { $assignedSession = 'afternoon'; }
                }
            } else {
                // If sections table has a 'shift' column, prefer it
                $secCol = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sections' AND COLUMN_NAME = 'shift'");
                $secCol->execute();
                if ($secCol->fetchColumn()) {
                    $secStmt = $db->prepare("SELECT shift FROM sections WHERE section_name = :section LIMIT 1");
                    $secStmt->bindParam(':section', $student_section_name, PDO::PARAM_STR);
                    $secStmt->execute();
                    $shiftVal = $secStmt->fetchColumn();
                    if ($shiftVal !== false && $shiftVal !== null) {
                        $sv = strtolower(trim($shiftVal));
                        if (strpos($sv, 'am') !== false) { $assignedSession = 'morning'; }
                        elseif (strpos($sv, 'pm') !== false) { $assignedSession = 'afternoon'; }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Session assignment check failed: ' . $e->getMessage());
        }

        // Determine session (morning|afternoon) based on assigned session (from teacher/section) or current time
        $t = strtotime($current_time);
        $morning_start = strtotime($schedule['morning_start']);
        $morning_end = strtotime($schedule['morning_end']);
        $morning_late = strtotime($schedule['morning_late_after']);
        $afternoon_start = strtotime($schedule['afternoon_start']);
        $afternoon_end = strtotime($schedule['afternoon_end']);
        $afternoon_late = strtotime($schedule['afternoon_late_after']);

        if ($assignedSession === 'morning') {
            $session = 'morning';
            $preferred_in = 'morning_time_in';
            $preferred_out = 'morning_time_out';
            $is_late = ($t > $morning_late);
        } elseif ($assignedSession === 'afternoon') {
            $session = 'afternoon';
            $preferred_in = 'afternoon_time_in';
            $preferred_out = 'afternoon_time_out';
            $is_late = ($t > $afternoon_late);
        } else {
            if ($t >= $morning_start && $t <= $morning_end) {
                $session = 'morning';
                $preferred_in = 'morning_time_in';
                $preferred_out = 'morning_time_out';
                $is_late = ($t > $morning_late);
            } elseif ($t >= $afternoon_start && $t <= $afternoon_end) {
                $session = 'afternoon';
                $preferred_in = 'afternoon_time_in';
                $preferred_out = 'afternoon_time_out';
                $is_late = ($t > $afternoon_late);
            } else {
                // Out-of-schedule: default to morning logic
                $session = 'morning';
                $preferred_in = 'morning_time_in';
                $preferred_out = 'morning_time_out';
                $is_late = ($t > $morning_late);
            }
        }

        // Ensure chosen columns exist in the attendance table; fallback to legacy `time_in`/`time_out` if necessary
        $colCheckStmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . ($isTeacher ? 'teacher_attendance' : 'attendance') . "'");
        $colCheckStmt->execute();
        $attendanceCols = $colCheckStmt->fetchAll(PDO::FETCH_COLUMN);

        // If teacher and attendance table has employee_number column, prefer it
        if ($isTeacher && in_array('employee_number', $attendanceCols)) {
            $idColumn = 'employee_number';
        }

        $in_col = null;
        $out_col = null;

        // Prefer the session-specific columns, fallback to legacy names
        if (in_array($preferred_in, $attendanceCols)) {
            $in_col = $preferred_in;
        } elseif (in_array('time_in', $attendanceCols)) {
            $in_col = 'time_in';
        } else {
            // Look for any column that ends with '_time_in' as a fallback
            foreach ($attendanceCols as $ac) {
                if (preg_match('/time_in$/', $ac)) { $in_col = $ac; break; }
            }
        }

        if (in_array($preferred_out, $attendanceCols)) {
            $out_col = $preferred_out;
        } elseif (in_array('time_out', $attendanceCols)) {
            $out_col = 'time_out';
        } else {
            foreach ($attendanceCols as $ac) {
                if (preg_match('/time_out$/', $ac)) { $out_col = $ac; break; }
            }
        }

        if ($in_col === null || $out_col === null) {
            throw new Exception('Attendance table missing expected time columns. Please run the migration to add morning/afternoon time columns or ensure legacy time_in/time_out exist.');
        }

        // Use SELECT ... FOR UPDATE to lock the row and prevent race conditions
        $check_query = "SELECT * FROM {$attendanceTable} 
                        WHERE {$idColumn} = :code 
                        AND date = :today 
                        FOR UPDATE";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':code', $scannedCode, PDO::PARAM_STR);
        $check_stmt->bindParam(':today', $today, PDO::PARAM_STR);
        $check_stmt->execute();

        $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);

        // Normalize section value to store in attendance row (backwards-compatible)
        $section_value = $student['section'] ?? $student['class'] ?? '';

        // Helper: prepare email details
        $prepareEmailDetails = function() use ($today, $current_time, $student) {
            return [
                'date' => date('F j, Y', strtotime($today)),
                'time' => date('h:i A', strtotime($current_time)),
                'section' => $student['class'] ?? $student['section'] ?? ''
            ];
        };

        if (!$existing_record) {
            // ===== TIME IN (First Scan) =====
            $status = $is_late ? 'late' : 'present';

            if ($isTeacher) {
                $insert_query = "INSERT INTO teacher_attendance (employee_number, department, date, {$in_col}, status) 
                                VALUES (:code, :section, :date, :time_in, :status)";
            } else {
                $insert_query = "INSERT INTO attendance (lrn, section, date, {$in_col}, status) 
                                VALUES (:code, :section, :date, :time_in, :status)";
            }

            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':code', $scannedCode, PDO::PARAM_STR);
            $insert_stmt->bindParam(':section', $section_value, PDO::PARAM_STR);
            $insert_stmt->bindParam(':date', $today, PDO::PARAM_STR);
            $insert_stmt->bindParam(':time_in', $current_time, PDO::PARAM_STR);
            $insert_stmt->bindParam(':status', $status, PDO::PARAM_STR);

            if (!$insert_stmt->execute()) {
                throw new Exception('Failed to record Time In. Please try again.');
            }

            $db->commit();

            // Notifications (students and teachers)
            $emailDetails = $prepareEmailDetails();
            try { sendAttendanceEmail($emailConfig, $student, 'time_in', $emailDetails, $userType); } catch (Exception $e) { error_log('Email failed: '.$e->getMessage()); }
            try { sendAttendanceSms($db, $student, 'time_in', $emailDetails, $userType); } catch (Exception $e) { error_log('SMS failed: '.$e->getMessage()); }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'status' => 'time_in',
                'session' => $session,
                'late' => $is_late,
                'message' => $userLabel . ' Time In recorded successfully!',
                'student_name' => ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''),
                'user_type' => $userType,
                'time_in' => date('h:i A', strtotime($current_time)),
                'date' => date('F j, Y', strtotime($today))
            ]);

        } else {
            // Existing record for today - decide whether to write in or out for this session
            $existing_in = $existing_record[$in_col] ?? null;
            $existing_out = $existing_record[$out_col] ?? null;

            if ($existing_in === null) {
                // Session Time In (row exists but in_col not set)
                $status = $is_late ? 'late' : 'present';
                $update_query = "UPDATE {$attendanceTable} SET {$in_col} = :time_in, status = :status WHERE {$idColumn} = :code AND date = :today";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':time_in', $current_time, PDO::PARAM_STR);
                $update_stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $update_stmt->bindParam(':code', $scannedCode, PDO::PARAM_STR);
                $update_stmt->bindParam(':today', $today, PDO::PARAM_STR);

                if (!$update_stmt->execute()) {
                    throw new Exception('Failed to record Time In. Please try again.');
                }

                $db->commit();
                $emailDetails = $prepareEmailDetails(); try { sendAttendanceEmail($emailConfig, $student, 'time_in', $emailDetails, $userType); } catch (Exception $e) { error_log('Email failed: '.$e->getMessage()); } try { sendAttendanceSms($db, $student, 'time_in', $emailDetails, $userType); } catch (Exception $e) { error_log('SMS failed: '.$e->getMessage()); }

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'status' => 'time_in',
                    'session' => $session,
                    'late' => $is_late,
                    'message' => $userLabel . ' Time In recorded successfully!',
                    'student_name' => ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''),
                    'user_type' => $userType,
                    'identifier' => $scannedCode,
                    'time_in' => date('h:i A', strtotime($current_time)),
                    'date' => date('F j, Y', strtotime($today))
                ]);
                exit;

            } elseif ($existing_in !== null && ($existing_out === null)) {
                // Session Time Out (in exists but out missing)
                $update_query = "UPDATE {$attendanceTable} SET {$out_col} = :time_out WHERE {$idColumn} = :code AND date = :today";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':time_out', $current_time, PDO::PARAM_STR);
                $update_stmt->bindParam(':code', $scannedCode, PDO::PARAM_STR);
                $update_stmt->bindParam(':today', $today, PDO::PARAM_STR);

                if (!$update_stmt->execute()) {
                    throw new Exception('Failed to record Time Out. Please try again.');
                }

                $db->commit();

                // Notifications (students and teachers)
                $emailDetails = $prepareEmailDetails();
                try { sendAttendanceEmail($emailConfig, $student, 'time_out', $emailDetails, $userType); } catch (Exception $e) { error_log('Email failed: '.$e->getMessage()); }
                try { sendAttendanceSms($db, $student, 'time_out', $emailDetails, $userType); } catch (Exception $e) { error_log('SMS failed: '.$e->getMessage()); }

                // Calculate duration using session in time
                $time_in_val = $existing_in;
                $time_out = strtotime($current_time);
                $time_in_ts = strtotime($time_in_val);
                $duration_seconds = $time_out - $time_in_ts;
                $hours = floor($duration_seconds / 3600);
                $minutes = floor(($duration_seconds % 3600) / 60);
                $duration = sprintf('%d hours %d minutes', $hours, $minutes);

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'status' => 'time_out',
                    'session' => $session,
                    'message' => $userLabel . ' Time Out recorded successfully!',
                    'student_name' => ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''),
                    'user_type' => $userType,
                    'identifier' => $scannedCode,
                    'time_out' => date('h:i A', strtotime($current_time)),
                    'duration' => $duration,
                    'date' => date('F j, Y', strtotime($today))
                ]);
                exit;

            } else {
                // Already completed for this session
                $db->commit();
                $in_display = $existing_record[$in_col] ?? 'N/A';
                $out_display = $existing_record[$out_col] ?? 'N/A';
                throw new Exception('Attendance already completed for today. Time In: ' . date('h:i A', strtotime($in_display)) . ', Time Out: ' . ($out_display !== 'N/A' ? date('h:i A', strtotime($out_display)) : 'N/A'));
            }
        }
        
    } catch (Exception $e) {
        // Rollback transaction on any error
        if ($db->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
    
} catch (PDOException $e) {
    ob_end_clean();
    error_log("Attendance DB Error: " . $e->getMessage());

    // Default message for end users
    $userMessage = 'Database error occurred. Please try again.';

    // Expose debug details only when explicitly requested from localhost
    $debugSuffix = '';
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if ((isset($_POST['debug']) && $_POST['debug'] == '1') && in_array($remoteAddr, ['127.0.0.1', '::1'])) {
        $debugSuffix = ' Debug: ' . $e->getMessage();
    }

    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $userMessage . $debugSuffix
    ]);
} catch (Exception $e) {
    ob_end_clean();
    error_log("Attendance Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

exit;
