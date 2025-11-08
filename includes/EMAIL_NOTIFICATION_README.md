# Email Notification System for QR Code Attendance

## Overview

This module provides a standalone PHP function that sends email notifications when students scan their QR codes for attendance. The system automatically sends formatted HTML emails to parents/guardians when a student marks Time IN or Time OUT.

## Features

- ✅ **PHPMailer Integration**: Uses PHPMailer for reliable email delivery
- ✅ **Formatted HTML Emails**: Professional, mobile-responsive email templates
- ✅ **Error Handling**: Comprehensive try-catch with detailed error logging
- ✅ **Input Validation**: Validates email addresses, status values, and required parameters
- ✅ **Configurable SMTP**: Easy-to-configure SMTP settings for any email provider
- ✅ **Plain Text Alternative**: Includes plain text version for email clients that don't support HTML
- ✅ **Clean Code**: Well-documented, readable PHP code following best practices

## Function Signature

```php
sendAttendanceEmailNotification($studentName, $studentEmail, $status, $timestamp)
```

### Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `$studentName` | string | Full name of the student | "Juan Dela Cruz" |
| `$studentEmail` | string | Email address (parent/guardian) | "parent@example.com" |
| `$status` | string | Attendance status: "IN" or "OUT" | "IN" |
| `$timestamp` | string | Date and time of attendance | "2024-01-15 08:30:00" |

### Return Value

- **Type**: `boolean`
- **Returns**: `true` if email sent successfully, `false` on failure

## Installation & Setup

### Step 1: Prerequisites

Ensure PHPMailer is available in your project:
- Located at: `/libs/PHPMailer/` (already installed in this project)
- Or installed via Composer: `composer require phpmailer/phpmailer`

### Step 2: Configure SMTP Settings

Open `includes/email_notification.php` and fill in your SMTP credentials:

```php
// SMTP server address
$mail->Host = 'smtp.gmail.com';  // Your SMTP host

// SMTP username (your email)
$mail->Username = 'your-email@example.com';

// SMTP password
$mail->Password = 'your-password-or-app-password';

// Set sender email
$mail->setFrom('noreply@school.com', 'School Attendance System');

// Port (465 for SSL, 587 for TLS)
$mail->Port = 465;
```

### Step 3: Usage

Include the function in your PHP file and call it:

```php
<?php
require_once __DIR__ . '/includes/email_notification.php';

// Send notification
$result = sendAttendanceEmailNotification(
    "Juan Dela Cruz",           // Student name
    "parent@example.com",        // Parent email
    "IN",                        // Status: IN or OUT
    "2024-01-15 08:30:00"       // Timestamp
);

if ($result) {
    echo "Email sent successfully!";
} else {
    echo "Failed to send email.";
}
?>
```

## Email Template

The function sends a beautifully formatted HTML email that includes:

### Subject Line
```
Attendance Alert: [Student Name] has Time IN/OUT
```

### Email Body Contains:
- 🎨 **Professional Header**: Gradient background with school name
- 📛 **Student's Name**: Clearly displayed
- 📊 **Status Badge**: Color-coded (Green for IN, Red for OUT)
- 📅 **Date**: Formatted date (e.g., "January 15, 2024")
- ⏰ **Time**: Formatted time (e.g., "8:30 AM")
- 📝 **Automated Notice**: "This is an automated attendance notification."
- 📱 **Mobile Responsive**: Looks great on all devices

### Example Email Preview

```
┌─────────────────────────────────────┐
│     ✅ Attendance Alert             │
│  Academy of St. Joseph System       │
├─────────────────────────────────────┤
│ Dear Parent/Guardian,               │
│                                     │
│ [Time IN]                           │
│                                     │
│ Student's Name: Juan Dela Cruz      │
│ Status: Time IN                     │
│ Date: January 15, 2024              │
│ Time: 8:30 AM                       │
│                                     │
│ 📌 Note: This is an automated       │
│    attendance notification.          │
├─────────────────────────────────────┤
│ Academy of St. Joseph               │
│ Automated Attendance System         │
└─────────────────────────────────────┘
```

## SMTP Configuration Examples

### Gmail

```php
$mail->Host = 'smtp.gmail.com';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';  // Use App Password, not regular password
```

**Note**: For Gmail, you need to generate an App Password:
1. Go to Google Account Settings
2. Security → 2-Step Verification → App Passwords
3. Generate a new app password
4. Use this password instead of your regular password

### Outlook/Office365

```php
$mail->Host = 'smtp.office365.com';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Username = 'your-email@outlook.com';
$mail->Password = 'your-password';
```

### Yahoo Mail

```php
$mail->Host = 'smtp.mail.yahoo.com';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Username = 'your-email@yahoo.com';
$mail->Password = 'your-password';
```

### Custom SMTP Server

```php
$mail->Host = 'mail.yourdomain.com';
$mail->Port = 465;  // Or as provided by your host
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Username = 'noreply@yourdomain.com';
$mail->Password = 'your-password';
```

## Integration with Attendance System

### Basic Integration

Add this code to your attendance marking script (e.g., `api/mark_attendance.php`):

```php
// Include the email notification function
require_once __DIR__ . '/../includes/email_notification.php';

// After successfully recording attendance to database
$studentName = $student['first_name'] . ' ' . $student['last_name'];
$parentEmail = $student['email'];
$status = ($isTimeIn) ? 'IN' : 'OUT';
$timestamp = date('Y-m-d H:i:s');

// Send email notification
$emailResult = sendAttendanceEmailNotification(
    $studentName,
    $parentEmail,
    $status,
    $timestamp
);

// Optional: Log the result
if ($emailResult) {
    error_log("Email notification sent to: " . $parentEmail);
} else {
    error_log("Email notification failed for: " . $parentEmail);
}
```

### Best Practices

1. **Non-Blocking**: Always record attendance first, then send email
2. **Error Handling**: Don't let email failures prevent attendance recording
3. **Logging**: Log email send results for debugging
4. **Validation**: The function validates inputs automatically
5. **Testing**: Test with a real email address before production use

## Error Handling

The function includes comprehensive error handling:

```php
try {
    $result = sendAttendanceEmailNotification(
        $studentName,
        $studentEmail,
        $status,
        $timestamp
    );
    
    if ($result) {
        // Email sent successfully
        $message = "Attendance recorded and notification sent.";
    } else {
        // Email failed but attendance is still recorded
        $message = "Attendance recorded. Email notification failed.";
    }
} catch (Exception $e) {
    // Handle any exceptions
    error_log("Unexpected error: " . $e->getMessage());
}
```

### Common Error Scenarios

| Error | Cause | Solution |
|-------|-------|----------|
| Invalid email | Email format is incorrect | Validate email before calling |
| SMTP connection failed | Incorrect SMTP settings | Check Host, Port, Username, Password |
| Authentication failed | Wrong credentials | Verify SMTP username and password |
| Invalid status | Status not "IN" or "OUT" | Use only "IN" or "OUT" |

## Testing

See `includes/email_notification_example.php` for complete testing examples:

```bash
php includes/email_notification_example.php
```

This will run through various examples including:
- Time IN notification
- Time OUT notification
- Current timestamp usage
- Error handling scenarios

## Files

- **`includes/email_notification.php`**: Main function file
- **`includes/email_notification_example.php`**: Usage examples and testing
- **`includes/EMAIL_NOTIFICATION_README.md`**: This documentation file

## Security Considerations

1. **SMTP Credentials**: Never commit SMTP passwords to version control
2. **Environment Variables**: Consider using environment variables for sensitive data
3. **Input Validation**: All inputs are validated before processing
4. **Error Logging**: Errors are logged, not displayed to users
5. **Email Validation**: Email addresses are validated using `filter_var()`

## Troubleshooting

### Email not sending?

1. **Check SMTP credentials**: Ensure all settings are correct
2. **Check error logs**: Look in your PHP error log for details
3. **Test SMTP connection**: Try connecting manually to verify credentials
4. **Firewall**: Ensure your server allows outbound SMTP connections
5. **Port**: Try different ports (465 for SSL, 587 for TLS)

### Gmail-specific issues?

- Use App Password, not your regular Gmail password
- Enable "Less secure app access" (if not using App Password)
- Check for security alerts in your Gmail account

## License

This code is part of the Academy of St. Joseph Attendance System.

## Support

For questions or issues, please contact the system administrator or refer to the main project documentation.

---

**Created**: 2024
**Version**: 1.0
**Author**: Academy of St. Joseph Attendance System Team
