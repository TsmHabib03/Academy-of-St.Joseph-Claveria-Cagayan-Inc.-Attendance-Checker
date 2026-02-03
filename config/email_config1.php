<?php
/**
 * Email Configuration for ACSCCI Attendance System
 * 
 * This file contains SMTP settings for sending emails through Gmail.
 * Uses Gmail App Password (16-character password from Google Account security settings)
 * 
 * Setup Instructions:
 * 1. Go to your Google Account (https://myaccount.google.com/)
 * 2. Navigate to Security > 2-Step Verification
 * 3. Scroll down to "App passwords"
 * 4. Generate a new app password for "Mail" on "Windows Computer"
 * 5. Copy the 16-character password (no spaces)
 * 6. Replace YOUR_16_CHAR_APP_PASSWORD below with your actual app password
 * 7. Replace your-email@gmail.com with your actual Gmail address
 */

// Email Configuration Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465); // Use 587 for TLS or 465 for SSL
define('SMTP_SECURE', 'ssl'); // 'tls' or 'ssl'
define('SMTP_AUTH', true);

// Gmail Account Credentials
define('SMTP_USERNAME', 'asjclaveriaattendance@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'jrrcjuheofdgdhbr'); // Your 16-character app password (no spaces)

// Sender Information
define('MAIL_FROM_EMAIL', 'asjclaveriaattendance@gmail.com'); // Same as SMTP_USERNAME
define('MAIL_FROM_NAME', 'ACSCCI Attendance System'); // Display name for sent emails

// Email Settings
define('MAIL_CHARSET', 'UTF-8');
define('MAIL_DEBUG', 0); // Set to 2 for debugging SMTP connection issues, 0 for production

// Password Reset Email Settings
define('PASSWORD_RESET_SUBJECT', 'Password Reset Request - ACSCCI Attendance System');
define('PASSWORD_RESET_EXPIRY_HOURS', 1); // Token expires after 1 hour

// System URL (used in email links)
define('SYSTEM_BASE_URL', 'http://localhost/ACSCCI-Attendance-Checker'); // Update this for production

// ============================================================
// RETURN ARRAY CONFIGURATION FOR mark_attendance.php
// This ensures both constant-based and array-based email systems work
// ============================================================
return [
    // SMTP Server Settings
    'smtp_host' => SMTP_HOST,
    'smtp_port' => SMTP_PORT,
    'smtp_secure' => SMTP_SECURE, // 'tls' or 'ssl'
    'smtp_username' => SMTP_USERNAME,
    'smtp_password' => SMTP_PASSWORD,
    
    // Sender Information
    'from_email' => MAIL_FROM_EMAIL,
    'from_name' => MAIL_FROM_NAME,
    'reply_to_email' => MAIL_FROM_EMAIL,
    'reply_to_name' => MAIL_FROM_NAME,
    
    // Email Settings
    'charset' => MAIL_CHARSET,
    'debug' => MAIL_DEBUG,
    
    // Notification Settings (enable/disable emails)
    'send_on_time_in' => true,  // Send email when student scans Time IN
    'send_on_time_out' => true, // Send email when student scans Time OUT
    
    // Email Subjects
    'subject_time_in' => 'Attendance Alert: {student_name} has arrived at school',
    'subject_time_out' => 'Attendance Alert: {student_name} has left school',
    
    // School Information (for email templates)
    'school_name' => 'Academy of St. Joseph Claveria, Cagayan Inc.',
    'school_address' => 'Claveria, Cagayan, Philippines',
    'support_email' => MAIL_FROM_EMAIL,
    
    // System Settings
    'base_url' => SYSTEM_BASE_URL,
];
?>
