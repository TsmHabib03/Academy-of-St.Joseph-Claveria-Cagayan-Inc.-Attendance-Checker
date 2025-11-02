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
function sendAttendanceEmail($emailConfig, $student, $type, $details) {
    try {
        // Check if email notifications are enabled
        if (!$emailConfig['send_on_' . $type]) {
            return true; // Disabled, return success
        }
        
        // Validate parent email
        if (empty($student['email']) || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid parent email for LRN: " . $student['lrn']);
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
        $mail->addAddress($student['email'], 'Parent/Guardian');
        
        // Email subject
        $studentName = trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']);
        $mail->Subject = str_replace('{student_name}', $studentName, $emailConfig['subject_' . $type]);
        
        // HTML Email body
        $mail->isHTML(true);
        $mail->Body = generateEmailTemplate($emailConfig, $student, $type, $details);
        
        // Plain text alternative
        $mail->AltBody = generatePlainTextEmail($student, $type, $details);
        
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
function generateEmailTemplate($emailConfig, $student, $type, $details) {
    $studentName = trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']);
    $statusColor = ($type === 'time_in') ? '#4CAF50' : '#FF9800';
    $statusText = ($type === 'time_in') ? 'Arrived at School' : 'Left School';
    $icon = ($type === 'time_in') ? '🟢' : '🔴';
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; }
            .status-badge { display: inline-block; padding: 12px 24px; background: ' . $statusColor . '; color: white; border-radius: 25px; font-weight: bold; margin: 15px 0; font-size: 16px; }
            .details { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd; }
            .detail-row:last-child { border-bottom: none; }
            .label { font-weight: bold; color: #555; }
            .value { color: #333; font-weight: 600; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #777; border-radius: 0 0 10px 10px; }
            .note { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . $icon . ' Attendance Alert</h1>
                <p>' . htmlspecialchars($emailConfig['school_name']) . '</p>
            </div>
            <div class="content">
                <h2>Dear Parent/Guardian,</h2>
                <p>This is an automated notification regarding your child\'s attendance today.</p>
                
                <div class="status-badge">' . htmlspecialchars($statusText) . '</div>
                
                <div class="details">
                    <div class="detail-row">
                        <span class="label">Student Name:</span>
                        <span class="value">' . htmlspecialchars($studentName) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">LRN:</span>
                        <span class="value">' . htmlspecialchars($student['lrn']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Section:</span>
                        <span class="value">' . htmlspecialchars($student['class']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Date:</span>
                        <span class="value">' . htmlspecialchars($details['date']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">' . ($type === 'time_in' ? 'Time In' : 'Time Out') . ':</span>
                        <span class="value">' . htmlspecialchars($details['time']) . '</span>
                    </div>
                </div>
                
                <div class="note">
                    <strong>📌 Note:</strong> This is an automated notification from the school attendance system. 
                    If you have any concerns or questions, please contact the school administration.
                </div>
            </div>
            <div class="footer">
                <p><strong>' . htmlspecialchars($emailConfig['school_name']) . '</strong><br>
                ' . htmlspecialchars($emailConfig['school_address']) . '</p>
                <p>For inquiries: ' . htmlspecialchars($emailConfig['support_email']) . '</p>
                <p style="margin-top: 15px; font-size: 11px; color: #999;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Generate plain text email (fallback)
 */
function generatePlainTextEmail($student, $type, $details) {
    $studentName = trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']);
    $statusText = ($type === 'time_in') ? 'Arrived at School' : 'Left School';
    
    return "ATTENDANCE ALERT\n\n" .
           "Dear Parent/Guardian,\n\n" .
           "Status: $statusText\n\n" .
           "Student Details:\n" .
           "Name: $studentName\n" .
           "LRN: {$student['lrn']}\n" .
           "Section: {$student['class']}\n" .
           "Date: {$details['date']}\n" .
           ($type === 'time_in' ? 'Time In' : 'Time Out') . ": {$details['time']}\n\n" .
           "This is an automated notification from the school attendance system.\n\n" .
           "Note: This is an automated message. Please do not reply to this email.";
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check database connection
    if ($db === null) {
        throw new Exception('Database connection failed');
    }
    
    $lrn = trim($_POST['lrn'] ?? '');
    
    if (empty($lrn)) {
        throw new Exception('LRN is required');
    }
    
    // Validate LRN format
    if (!preg_match('/^[0-9]{11,13}$/', $lrn)) {
        throw new Exception('Invalid LRN format. Must be 11-13 digits.');
    }
    
    // Get student details
    $student_query = "SELECT * FROM students WHERE lrn = :lrn";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':lrn', $lrn, PDO::PARAM_STR);
    $student_stmt->execute();
    
    if ($student_stmt->rowCount() === 0) {
        throw new Exception('Student not found in the system. Please register first.');
    }
    
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current date and time
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Start a transaction to prevent race conditions
    $db->beginTransaction();
    
    try {
        // Use SELECT ... FOR UPDATE to lock the row and prevent race conditions
        $check_query = "SELECT * FROM attendance 
                        WHERE lrn = :lrn 
                        AND date = :today 
                        FOR UPDATE";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':lrn', $lrn, PDO::PARAM_STR);
        $check_stmt->bindParam(':today', $today, PDO::PARAM_STR);
        $check_stmt->execute();
        
        $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_record) {
            // ===== TIME IN (First Scan) =====
            
            // Insert new attendance record with Time In
            $insert_query = "INSERT INTO attendance (lrn, section, date, time_in, status) 
                            VALUES (:lrn, :section, :date, :time_in, :status)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':lrn', $lrn, PDO::PARAM_STR);
            $insert_stmt->bindParam(':section', $student['class'], PDO::PARAM_STR);
            $insert_stmt->bindParam(':date', $today, PDO::PARAM_STR);
            $insert_stmt->bindParam(':time_in', $current_time, PDO::PARAM_STR);
            $status = 'present';
            $insert_stmt->bindParam(':status', $status, PDO::PARAM_STR);
            
            if (!$insert_stmt->execute()) {
                throw new Exception('Failed to record Time In. Please try again.');
            }
            
            // Commit the transaction before proceeding with email
            $db->commit();
            
            // Prepare email details for Time In
            $emailDetails = [
                'date' => date('F j, Y', strtotime($today)),
                'time' => date('h:i A', strtotime($current_time)),
                'section' => $student['class']
            ];
            
            // Send Time In email (non-blocking)
            $emailSent = false;
            try {
                $emailSent = sendAttendanceEmail($emailConfig, $student, 'time_in', $emailDetails);
            } catch (Exception $e) {
                error_log("Email notification failed: " . $e->getMessage());
            }
            
            // Return success response for Time In (minimal data for security)
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'status' => 'time_in',
                'message' => 'Time In recorded successfully!',
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'time_in' => date('h:i A', strtotime($current_time)),
                'date' => date('F j, Y', strtotime($today))
            ]);
            
        } elseif ($existing_record['time_in'] !== null && $existing_record['time_out'] === null) {
            // ===== TIME OUT (Second Scan) =====
            
            // Update existing record with Time Out
            $update_query = "UPDATE attendance 
                            SET time_out = :time_out
                            WHERE lrn = :lrn 
                            AND date = :today";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':time_out', $current_time, PDO::PARAM_STR);
            $update_stmt->bindParam(':lrn', $lrn, PDO::PARAM_STR);
            $update_stmt->bindParam(':today', $today, PDO::PARAM_STR);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to record Time Out. Please try again.');
            }
            
            // Commit the transaction before proceeding with email
            $db->commit();
            
            // Prepare email details for Time Out
            $emailDetails = [
                'date' => date('F j, Y', strtotime($today)),
                'time' => date('h:i A', strtotime($current_time)),
                'section' => $student['class']
            ];
            
            // Send Time Out email (non-blocking)
            $emailSent = false;
            try {
                $emailSent = sendAttendanceEmail($emailConfig, $student, 'time_out', $emailDetails);
            } catch (Exception $e) {
                error_log("Email notification failed: " . $e->getMessage());
            }
            
            // Calculate duration
            $time_in = strtotime($existing_record['time_in']);
            $time_out = strtotime($current_time);
            $duration_seconds = $time_out - $time_in;
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $duration = sprintf('%d hours %d minutes', $hours, $minutes);
            
            // Return success response for Time Out (minimal data for security)
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'status' => 'time_out',
                'message' => 'Time Out recorded successfully!',
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'time_out' => date('h:i A', strtotime($current_time)),
                'duration' => $duration,
                'date' => date('F j, Y', strtotime($today))
            ]);
            
        } else {
            // ===== ALREADY COMPLETED =====
            $db->commit(); // Complete the transaction
            
            throw new Exception(
                'Attendance already completed for today. ' .
                'Time In: ' . date('h:i A', strtotime($existing_record['time_in'])) . ', ' .
                'Time Out: ' . date('h:i A', strtotime($existing_record['time_out']))
            );
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
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database error occurred. Please try again.'
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
