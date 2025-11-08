<?php
/**
 * Email Notification Function - Usage Examples
 * 
 * This file demonstrates how to use the sendAttendanceEmailNotification() function
 * to send email alerts when students scan their QR codes for attendance.
 * 
 * BEFORE USING:
 * 1. Open includes/email_notification.php
 * 2. Fill in your SMTP credentials:
 *    - Host (e.g., smtp.gmail.com)
 *    - Username (your email)
 *    - Password (your email password or app password)
 *    - setFrom (sender email address)
 * 3. Configure Port (465 for SSL, 587 for TLS)
 */

// Include the email notification function
require_once __DIR__ . '/email_notification.php';

// ============================================================
// EXAMPLE 1: Send Time IN notification
// ============================================================
echo "Example 1: Sending Time IN notification...\n";

$result1 = sendAttendanceEmailNotification(
    "Juan Dela Cruz",              // Student's name
    "parent@example.com",           // Parent/guardian email
    "IN",                           // Status: IN or OUT
    "2024-01-15 08:30:00"          // Timestamp
);

if ($result1) {
    echo "✅ Time IN notification sent successfully!\n\n";
} else {
    echo "❌ Failed to send Time IN notification.\n\n";
}

// ============================================================
// EXAMPLE 2: Send Time OUT notification
// ============================================================
echo "Example 2: Sending Time OUT notification...\n";

$result2 = sendAttendanceEmailNotification(
    "Maria Santos",                 // Student's name
    "guardian@example.com",         // Parent/guardian email
    "OUT",                          // Status: IN or OUT
    "2024-01-15 16:45:00"          // Timestamp
);

if ($result2) {
    echo "✅ Time OUT notification sent successfully!\n\n";
} else {
    echo "❌ Failed to send Time OUT notification.\n\n";
}

// ============================================================
// EXAMPLE 3: Using current timestamp
// ============================================================
echo "Example 3: Using current timestamp...\n";

$currentTimestamp = date('Y-m-d H:i:s');  // Current date and time

$result3 = sendAttendanceEmailNotification(
    "Pedro Reyes",
    "parent2@example.com",
    "IN",
    $currentTimestamp
);

if ($result3) {
    echo "✅ Notification with current timestamp sent successfully!\n\n";
} else {
    echo "❌ Failed to send notification.\n\n";
}

// ============================================================
// EXAMPLE 4: Integration with attendance system
// ============================================================
echo "Example 4: Integration example...\n\n";

// Simulated student data from database
$studentData = [
    'full_name' => 'Anna Cruz',
    'email' => 'parent.anna@example.com',
    'lrn' => '123456789012'
];

// Simulated attendance data
$attendanceStatus = 'IN';  // This would come from your QR scan logic
$scanTimestamp = date('Y-m-d H:i:s');

// Send notification
$emailSent = sendAttendanceEmailNotification(
    $studentData['full_name'],
    $studentData['email'],
    $attendanceStatus,
    $scanTimestamp
);

if ($emailSent) {
    echo "✅ Attendance recorded and notification sent for LRN: " . $studentData['lrn'] . "\n";
} else {
    echo "⚠️ Attendance recorded but notification failed for LRN: " . $studentData['lrn'] . "\n";
    echo "   (This is non-critical - attendance is still saved)\n";
}

// ============================================================
// EXAMPLE 5: Error handling
// ============================================================
echo "\nExample 5: Demonstrating error handling...\n";

// Example with invalid email
$result5 = sendAttendanceEmailNotification(
    "Test Student",
    "invalid-email",  // Invalid email format
    "IN",
    date('Y-m-d H:i:s')
);

if (!$result5) {
    echo "❌ As expected, invalid email was rejected.\n";
}

// Example with invalid status
$result6 = sendAttendanceEmailNotification(
    "Test Student",
    "valid@example.com",
    "INVALID_STATUS",  // Invalid status
    date('Y-m-d H:i:s')
);

if (!$result6) {
    echo "❌ As expected, invalid status was rejected.\n";
}

echo "\n✅ All examples completed!\n";
echo "\n📝 Remember to configure SMTP settings in email_notification.php before using in production.\n";

// ============================================================
// INTEGRATION GUIDE
// ============================================================
/*

HOW TO INTEGRATE WITH YOUR ATTENDANCE SYSTEM:
----------------------------------------------

1. In your attendance marking script (e.g., api/mark_attendance.php):

   // Include the email notification function
   require_once __DIR__ . '/../includes/email_notification.php';

2. After successfully recording attendance to the database:

   // Get student details from database
   $studentName = $student['first_name'] . ' ' . $student['last_name'];
   $parentEmail = $student['email'];  // Or parent_email field
   $status = ($timeIn) ? 'IN' : 'OUT';
   $timestamp = date('Y-m-d H:i:s');
   
   // Send email notification (non-blocking)
   $emailResult = sendAttendanceEmailNotification(
       $studentName,
       $parentEmail,
       $status,
       $timestamp
   );
   
   // Log the result (optional)
   if ($emailResult) {
       error_log("Email sent to: " . $parentEmail);
   } else {
       error_log("Email failed for: " . $parentEmail);
   }

3. IMPORTANT: Don't let email failures affect attendance recording!
   - Always record attendance first
   - Send email as a secondary operation
   - If email fails, attendance is still saved

SMTP CONFIGURATION EXAMPLES:
----------------------------

For Gmail:
    Host: smtp.gmail.com
    Port: 587 (TLS) or 465 (SSL)
    Username: your-email@gmail.com
    Password: your-app-password (not regular password!)
    SMTPSecure: PHPMailer::ENCRYPTION_STARTTLS (for 587)

For Outlook/Office365:
    Host: smtp.office365.com
    Port: 587
    Username: your-email@outlook.com
    Password: your-password
    SMTPSecure: PHPMailer::ENCRYPTION_STARTTLS

For Yahoo:
    Host: smtp.mail.yahoo.com
    Port: 587 or 465
    Username: your-email@yahoo.com
    Password: your-password
    SMTPSecure: PHPMailer::ENCRYPTION_STARTTLS

For custom SMTP:
    Host: mail.yourdomain.com
    Port: As provided by your host
    Username: As provided by your host
    Password: As provided by your host
    SMTPSecure: As required (SSL/TLS)

*/
?>
