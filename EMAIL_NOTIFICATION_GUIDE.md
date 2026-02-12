# Email Notification Alert System - User Guide

## Overview

This email notification system allows the Academy of St. Joseph Attendance Checker to send beautiful, branded email alerts to students and parents when attendance is recorded. The system uses PHPMailer and features a modern, responsive HTML email template with the school's official colors.

---

## 📁 Files Included

| File | Description |
|------|-------------|
| `test_email.php` | Test script to manually send sample emails |
| `email_template.html` | Standalone HTML email template (for reference) |
| `includes/email_notification.php` | Production-ready email function (existing) |

---

## 🎨 Design Features

### School Branding
- **Primary Color**: `#1E3A8A` (Deep Blue) - Used in header and text
- **Secondary Color**: `#FBBF24` (Gold) - Used for accents and highlights
- **Background Color**: `#F9FAFB` (Light Gray) - Clean, professional background

### Modern Design Elements
- ✅ **Rounded corners** on all containers (16px border-radius)
- ✅ **Subtle shadows** for depth (box-shadow with blue tint)
- ✅ **Gradient header** with school logo
- ✅ **Status badge** with color-coded indicators
- ✅ **Responsive design** that works on mobile and desktop
- ✅ **Professional typography** with proper spacing
- ✅ **Icon integration** for visual appeal (📩, 👤, 📊, 📅, ⏰)

### Email Structure
1. **Header Section**
   - School logo (circular with white background)
   - Gradient blue background
   - "Attendance Alert" title
   - School name

2. **Content Section**
   - Personalized greeting
   - Status badge (Present/Absent)
   - Attendance details box with:
     - Student name
     - Status
     - Date
     - Time
   - Important note section

3. **Footer Section**
   - School logo (smaller)
   - School name and tagline
   - Contact information:
     - Address
     - Email
     - Phone
   - Copyright notice

---

## 🚀 Quick Start Guide

### Step 1: Configure SMTP Settings

Open `test_email.php` and update the configuration section (lines 32-42):

```php
// SMTP Server Configuration
$smtp_config = [
    'host'      => 'smtp.gmail.com',              // Your SMTP server
    'username'  => 'your-email@gmail.com',        // Your email address
    'password'  => 'your-app-password',           // Your password/app password
    'port'      => 587,                           // 587 for TLS, 465 for SSL
    'encryption'=> 'tls',                         // 'tls' or 'ssl'
];

// Email Configuration
$email_config = [
    'from_email' => 'attendance@school.edu.ph',   // Sender email
    'from_name'  => 'ASJ Attendance System',      // Sender name
    'to_email'   => 'example@school.edu.ph',      // Recipient email (CHANGE THIS!)
    'to_name'    => 'Test Recipient',             // Recipient name
];
```

### Step 2: Update School Information

Update the school details (lines 54-60):

```php
$school_info = [
    'name'     => 'Academy of St. Joseph, Claveria Cagayan Inc.',
    'address'  => 'Claveria, Cagayan, Philippines',
    'email'    => 'attendance@school.edu.ph',
    'phone'    => '(078) 123-4567',
    'logo_url' => 'https://yourdomain.com/assets/asj-logo.png',
];
```

### Step 3: Run the Test Script

**From Command Line:**
```bash
php test_email.php
```

**From Web Browser:**
Navigate to:
```
http://yourdomain.com/test_email.php
```

### Step 4: Check Your Email

The test email will be sent to the address specified in `$email_config['to_email']`. Check:
- ✅ Inbox
- ✅ Spam/Junk folder (if not in inbox)
- ✅ All Mail folder (for Gmail)

---

## 📧 SMTP Configuration Examples

### Gmail

```php
$smtp_config = [
    'host'      => 'smtp.gmail.com',
    'username'  => 'youremail@gmail.com',
    'password'  => 'your-16-char-app-password',  // Use App Password!
    'port'      => 587,
    'encryption'=> 'tls',
];
```

**Important for Gmail:**
1. Enable 2-Step Verification in your Google Account
2. Generate an App Password:
   - Go to Google Account → Security
   - Click "2-Step Verification" → "App passwords"
   - Generate new app password for "Mail"
   - Use the 16-character password (no spaces)

### Outlook / Office 365

```php
$smtp_config = [
    'host'      => 'smtp.office365.com',
    'username'  => 'youremail@outlook.com',
    'password'  => 'your-password',
    'port'      => 587,
    'encryption'=> 'tls',
];
```

### Yahoo Mail

```php
$smtp_config = [
    'host'      => 'smtp.mail.yahoo.com',
    'username'  => 'youremail@yahoo.com',
    'password'  => 'your-app-password',
    'port'      => 587,
    'encryption'=> 'tls',
];
```

### Custom SMTP Server

```php
$smtp_config = [
    'host'      => 'mail.yourdomain.com',
    'username'  => 'noreply@yourdomain.com',
    'password'  => 'your-password',
    'port'      => 465,  // Or as provided by your host
    'encryption'=> 'ssl',
];
```

---

## 🔧 Customization Guide

### Changing Colors

Edit the HTML in `test_email.php` (in the `buildEmailHTML()` function):

```php
// Header gradient (lines ~185)
background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);

// Status badge background (line ~206)
background-color: #10B981;  // Green for present

// Border accent color (line ~215)
border-left: 5px solid #FBBF24;  // Gold accent
```

### Changing Student Status Color

For different statuses, update the status badge color:

```php
// Green for Present
background-color: #10B981;

// Red for Absent
background-color: #EF4444;

// Orange for Late
background-color: #F59E0B;
```

### Adding Your School Logo

1. Upload your logo to your web server
2. Update the `logo_url` in `$school_info`:
   ```php
   'logo_url' => 'https://yourdomain.com/assets/school-logo.png',
   ```

**Logo Requirements:**
- Format: PNG with transparent background (recommended)
- Size: 200x200px minimum
- Square aspect ratio for best results

---

## 🧪 Testing Checklist

Before using in production, test the following:

- [ ] **SMTP Connection**: Test email sends successfully
- [ ] **Email Delivery**: Email arrives in inbox (not spam)
- [ ] **HTML Rendering**: Email displays correctly in:
  - [ ] Gmail (web)
  - [ ] Outlook (web)
  - [ ] Mobile email apps
  - [ ] Apple Mail
- [ ] **Logo Display**: School logo loads properly
- [ ] **Link Functionality**: Email links work correctly
- [ ] **Responsive Design**: Email looks good on mobile devices
- [ ] **Plain Text Fallback**: Plain text version displays correctly

---

## 📝 Expected Output

### Success Output

```
╔═══════════════════════════════════════════════════════════════╗
║     EMAIL NOTIFICATION TEST - ATTENDANCE ALERT SYSTEM          ║
║     Academy of St. Joseph, Claveria Cagayan Inc.              ║
╚═══════════════════════════════════════════════════════════════╝

📧 Email Configuration:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  SMTP Host: smtp.gmail.com
  SMTP Port: 587
  Encryption: TLS
  From: attendance@school.edu.ph
  To: example@school.edu.ph
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📝 Test Data:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Student: Juan Dela Cruz
  Status: Present
  Date: November 8, 2025
  Time: 8:30 AM
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📤 Sending test email...
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ SUCCESS!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Email sent successfully to example@school.edu.ph
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✓ Email sent successfully!
✓ Check the inbox of: example@school.edu.ph
✓ Don't forget to check the spam/junk folder if you don't see it.
```

### Email Preview

**Subject Line:**
```
📩 Attendance Alert – Example Notification
```

**Email Content:**
```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[School Logo]

📩 Attendance Alert
Academy of St. Joseph, Claveria Cagayan Inc.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Hello Juan Dela Cruz,

This is a sample email to test if the 
attendance notification alert is working 
properly.

          ✅ PRESENT

┌──────────────────────────────────────┐
│ 👤 Student Name    Juan Dela Cruz   │
│ 📊 Status          Present          │
│ 📅 Date            November 8, 2025  │
│ ⏰ Time            8:30 AM           │
└──────────────────────────────────────┘

📌 Important Note: This is an automated
   attendance notification. Please do not
   reply to this email.

Thank you,
Academy of St. Joseph, Claveria Cagayan Inc.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[Footer with school info]
© 2025 Academy of St. Joseph
```

---

## ⚠️ Troubleshooting

### Email Not Sending

**Problem**: Script shows "Email sending failed"

**Solutions**:
1. ✅ Verify SMTP credentials are correct
2. ✅ Check if port 587 (TLS) or 465 (SSL) is open on your server
3. ✅ For Gmail, ensure you're using an App Password, not your regular password
4. ✅ Enable "Less secure app access" if not using 2FA
5. ✅ Check PHP error logs for detailed error messages

### Email Goes to Spam

**Problem**: Email arrives in spam/junk folder

**Solutions**:
1. ✅ Use a verified domain email address (not generic Gmail)
2. ✅ Set up SPF, DKIM, and DMARC records for your domain
3. ✅ Use a consistent "From" email address
4. ✅ Avoid spam trigger words in subject/body
5. ✅ Ask recipients to whitelist your sender email

### Images Not Loading

**Problem**: School logo doesn't display

**Solutions**:
1. ✅ Use absolute URLs (https://...) not relative paths
2. ✅ Ensure logo file is publicly accessible
3. ✅ Check file permissions (should be readable)
4. ✅ Use supported formats (PNG, JPG, GIF)
5. ✅ Keep file size under 100KB for faster loading

### HTML Not Rendering

**Problem**: Email shows plain text or broken HTML

**Solutions**:
1. ✅ Ensure `$mail->isHTML(true)` is set
2. ✅ Use inline CSS (already implemented)
3. ✅ Test in multiple email clients
4. ✅ Check if email client supports HTML
5. ✅ Plain text fallback is automatically included

---

## 🔐 Security Best Practices

1. **Never commit SMTP passwords to version control**
   - Use environment variables or config files (listed in .gitignore)

2. **Use App Passwords instead of account passwords**
   - Especially for Gmail, Yahoo, and other providers

3. **Validate email addresses before sending**
   - Already implemented in the function

4. **Rate limiting**
   - Consider adding delays between bulk emails to avoid being flagged as spam

5. **Log email activity**
   - Already implemented with `error_log()`

---

## 📊 Integration with Attendance System

To integrate this email system into your existing attendance tracking:

### Option 1: Using the Existing Function

Use the existing `email_notification.php` in the `includes/` directory:

```php
// Include the email function
require_once __DIR__ . '/includes/email_notification.php';

// After marking attendance
$result = sendAttendanceEmailNotification(
    $studentName,
    $parentEmail,
    "IN",  // or "OUT"
    date('Y-m-d H:i:s')
);

if ($result) {
    error_log("Email sent to: " . $parentEmail);
}
```

### Option 2: Using the Test Script Functions

Copy the functions from `test_email.php` and adapt them:

```php
require_once __DIR__ . '/test_email.php';

// Your custom implementation
$student_data = [
    'name'   => $student['first_name'] . ' ' . $student['last_name'],
    'status' => $attendance_status,
    'date'   => date('F j, Y'),
    'time'   => date('g:i A'),
];

$result = sendTestEmail($smtp_config, $email_config, $student_data, $school_info);
```

---

## 📞 Support

For questions or issues:

- **Email**: attendance@school.edu.ph
- **Phone**: (078) 123-4567
- **Documentation**: See `includes/EMAIL_NOTIFICATION_README.md`

---

## 📄 License

This email notification system is part of the Academy of St. Joseph Attendance Checker project.

**Version**: 1.0  
**Last Updated**: November 8, 2025  
**Maintained by**: Academy of St. Joseph IT Team

---

## ✅ Deliverables Checklist

- [x] `test_email.php` - Functional PHP test script
- [x] `email_template.html` - Beautiful HTML email template
- [x] Modern design with school branding colors
- [x] School logo integration (placeholder ready)
- [x] Rounded corners and shadows
- [x] Responsive mobile-friendly design
- [x] Professional footer with contact info
- [x] Easy-to-modify SMTP settings
- [x] Plain text fallback
- [x] Comprehensive documentation
- [x] Test functionality confirmed

---

**Ready to use!** Configure your SMTP settings and start sending beautiful attendance notifications. 📧✨
