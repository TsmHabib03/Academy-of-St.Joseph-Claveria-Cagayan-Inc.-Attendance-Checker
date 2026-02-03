# Academy of St. Joseph Claveria, Cagayan Inc. Attendance Checker

A comprehensive web-based attendance management system using QR code scanning technology and LRN (Learner Reference Number) identification. Built with modern web technologies including HTML5, CSS3, JavaScript, PHP 8+, and MySQL 8.

This system was developed as part of a research project to modernize and streamline the attendance tracking process at Academy of St. Joseph Claveria, Cagayan Inc., providing an efficient and accurate solution for monitoring student attendance through QR code technology.

## 🌟 Features

### Core Functionality (v3.0)
- **Multi-Role User System**: Support for Admin, Teacher, Staff, and Student roles with granular permissions
- **LRN-based Student Management**: Uses official 11-13 digit Learner Reference Numbers as unique identifiers
- **Teacher Attendance Tracking**: Separate attendance system for faculty with employee IDs
- **Dual QR Code Scanning Systems**: 
  - Full-screen instant scanner for quick attendance marking
  - Manual attendance page with integrated QR scanner option
- **AM/PM Session Tracking**: Morning and afternoon attendance sessions with separate time slots
- **Late Detection**: Automatic flagging when students/teachers arrive after scheduled time
- **Section-based Organization**: Manage students by grade level and section names
- **Real-time Dashboard**: Interactive admin dashboard with live statistics and charts
- **Philippine Timezone Support**: Accurate time recording in Asia/Manila timezone

### Attendance Management
- **Morning Session (AM)**: Track time-in and time-out for morning classes
- **Afternoon Session (PM)**: Separate attendance for afternoon classes
- **Late Arrival Flagging**: Automatic detection based on configurable schedules
- **Configurable Schedules**: Set different time schedules per grade level or section
- **Duplicate Prevention**: Smart detection prevents multiple scans per session per student
- **SMS Notifications**: Configurable SMS alerts for late arrival or absence (Semaphore, Twilio, Vonage)

### Teacher Management (NEW in v3.0)
- **Teacher Registration**: Add teachers with Employee ID, department, and position
- **QR Code Generation**: Automatic QR code for teacher attendance tracking
- **Separate Attendance Records**: Teacher attendance tracked independently from students
- **Department Organization**: Group teachers by department

### Behavior Monitoring (NEW in v3.0)
- **Attendance Pattern Analysis**: Automatic detection of problematic patterns
- **Alert Types**: 
  - Frequent Lateness Detection
  - Consecutive Absences Alert
  - Sudden Absence Warning
  - Attendance Drop Monitoring
- **Severity Levels**: Low, Medium, High, Critical alert classification
- **Alert Acknowledgment**: Track resolution of behavior concerns

### Achievement Badges (NEW in v3.0)
- **Automatic Badge Awards**: Earn badges for attendance milestones
- **Badge Types**:
  - Perfect Attendance
  - On-Time Streak
  - Most Improved
  - Early Bird
  - Consistent Attendance
- **Leaderboard**: View top students by points and badges
- **Manual Awards**: Admin can manually award custom badges

### SMS Notifications (NEW in v3.0)
- **Multi-Gateway Support**: Semaphore (PH), Twilio, Vonage, Custom API
- **Template Management**: Create and manage SMS templates
- **Notification Types**: Late alerts, absence alerts, time-in/out confirmations
- **Rate Limiting**: Prevent notification spam
- **Delivery Logging**: Track all sent messages

### Admin Dashboard
- **Real-time Statistics**: 
  - Today's total attendance count
  - Active sections count
  - Time In records
  - Total attendance records
  - Pending behavior alerts
- **Weekly Attendance Trends**: Interactive bar chart showing 7-day Present vs Absent comparison
- **Section-wise Analysis**: Donut chart displaying today's attendance distribution by section
- **Recent Activity Feed**: Live list of latest Time In/Time Out records with student details
- **Needs Attention List**: Quick view of incomplete attendance records (missing Time Out)
- **Responsive Charts**: Built with Chart.js 4.4.0 for smooth data visualization

### Section Management
- **Dynamic Section Creation**: Add, edit, and delete sections with grade levels
- **Section Metadata**: Track adviser names, school year, and active/inactive status
- **Student Assignment**: Assign students to specific sections during registration
- **Section Reports**: Generate attendance reports filtered by section

### Student Management
- **Comprehensive Registration**: Capture first name, middle name, last name, sex, mobile number, class, and section
- **Grade Level Restriction**: Optional filtering of students by assigned grade levels
- **QR Code Generation**: Automatic unique QR code creation for each student
- **Students Directory**: Browse students organized by section with filtering
- **Bulk Operations**: View, edit, search, and delete students with admin activity logging
- **Student Details API**: Quick lookup by LRN for real-time information display
- **Data Validation**: LRN format validation, unique mobile number enforcement, required field checks

### Manual Attendance Interface
- **Dual Entry Modes**: 
  - Single Entry: Mark one student at a time with Time In/Time Out
  - Bulk Entry: Mark multiple students simultaneously
- **Session Selection**: Choose AM or PM session for attendance marking
- **Integrated QR Scanner**: Built-in camera scanner using ZXing library with continuous autofocus
- **Today's Attendance View**: Real-time table showing all attendance records for current date
- **Student Auto-complete**: Quick LRN lookup with automatic name and section population
- **Action Selection**: Choose between Time In, Time Out, or both for flexible marking

### Reporting & Analytics
- **Date Range Filtering**: Generate reports for specific date periods
- **Section-based Reports**: Filter attendance by specific sections or grade levels
- **CSV Export**: Download attendance data in CSV format for external analysis
- **PDF Export**: Generate formatted PDF reports for printing (TCPDF integration)
- **Activity Logging**: Complete audit trail of all admin actions (login, logout, add, edit, delete)
- **Status Tracking**: Monitor Present, Absent, Late, Time In, and Time Out records

### Security & Authentication
- **Admin Login System**: Secure authentication with session management
- **Multi-Role Support**: Admin, Teacher, Staff, Student roles with permission matrix
- **Role-Based Navigation**: Menu items filtered by user permissions
- **Password Reset**: Email-based password recovery with token expiration
- **Activity Logging**: Track all administrative actions with IP address and timestamps
- **SQL Injection Protection**: Prepared statements and parameterized queries throughout

### Modern UI/UX
- **Responsive Design**: Fully mobile-friendly interface that works on all devices
- **Modern CSS Framework**: Custom design system with CSS variables and gradient themes
- **Poppins Typography**: Clean, professional font family for enhanced readability
- **Interactive Elements**: Smooth animations, hover effects, and loading states
- **Dark Mode Ready**: Color scheme prepared for future dark theme implementation
- **Toast Notifications**: Non-intrusive feedback messages for user actions

## 📋 Requirements

### Server Requirements
- **Web Server**: Apache 2.4+ or Nginx (XAMPP/WAMP recommended for Windows)
- **PHP**: Version 8.0 or higher
- **MySQL**: Version 8.0 or higher
- **PHP Extensions**: PDO, PDO_MySQL, GD (for QR code generation), OpenSSL (for password hashing)

### Client Requirements
- Modern web browser with camera support (Chrome, Firefox, Safari, Edge)
- HTTPS connection (required for camera access)
- JavaScript enabled
- Minimum screen resolution: 320px width (mobile-friendly)

## 🚀 Installation

### 1. Database Setup

1. Create a MySQL database named `asj_attendease_db`:
   ```sql
   CREATE DATABASE asj_attendease_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the database structure using the latest export:
   ```bash
   mysql -u your_username -p asj_attendease_db < database/asj_attendease_db.sql
   ```
   
   This will create all necessary tables:
   - `students` - Student records with LRN, names, email, section
   - `sections` - Section management with grade levels and advisers
   - `attendance` - Time In/Time Out records with date tracking
   - `admin_users` - Admin authentication and roles
   - `admin_activity_log` - Complete audit trail of admin actions

### 2. Configuration

1. **Database Configuration**: Edit `config/db_config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'asj_attendease_db');
   define('DB_USER', 'your_mysql_username');
   define('DB_PASS', 'your_mysql_password');
   ```

2. **Email Configuration** (optional): Edit `config/email_config.php` for password reset:
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_USER', 'your-email@gmail.com');
   define('SMTP_PASS', 'your-app-password');
   define('SMTP_FROM', 'attendease08@gmail.com');
   ```

3. **Timezone Setting**: Verify in `config/db_config.php`:
   ```php
   date_default_timezone_set('Asia/Manila');
   ```

### 3. File Permissions

Ensure the web server has write permissions for:
```bash
chmod 755 uploads/qrcodes/
chmod 644 logs/
```

### 4. Web Server Setup

#### For XAMPP (Windows/Mac/Linux):
1. Copy the `ACSCCI-Attendance-Checker` folder to `C:\xampp\htdocs\`
2. Start Apache and MySQL from XAMPP Control Panel
3. Access via `http://localhost/ACSCCI-Attendance-Checker/`

#### For Production (Linux):
1. Copy files to `/var/www/html/acscci-attendance-checker/`
2. Configure virtual host with SSL certificate
3. Set proper file ownership: `chown -R www-data:www-data /var/www/html/acscci-attendance-checker/`

### 5. Default Admin Account

After database import, log in with:
- **Username**: `admin`
- **Password**: `admin123456` (MD5 hash: `0192023a7bbd73250516f069df18b500`)

⚠️ **SECURITY WARNING**: Change the default password immediately after first login via the admin dashboard!

## 🏫 System Logic

### LRN (Learner Reference Number)
- **Primary Identifier**: All students are identified by their official 11-13 digit LRN (Philippines DepEd standard)
- **Format**: Numeric only (e.g., `136514240419`)
- **Validation**: System enforces 11-13 digit format during registration
- **Unique**: Each LRN must be unique in the system (database constraint)
- **QR Codes**: Generated automatically using the student's LRN as the primary data

### Time In/Time Out System
AttendEase uses a simple, flexible attendance tracking model:

1. **Time In**: Records when a student arrives
   - Captured via QR scan or manual entry
   - Stores exact time in `HH:MM:SS` format
   - Creates attendance record for current date
   - Prevents duplicate Time In for same student on same day

2. **Time Out**: Records when a student leaves
   - Captured via QR scan or manual entry
   - Updates existing attendance record with departure time
   - Status changes from `time_in` to `time_out` (complete)
   - Cannot Time Out without existing Time In record

3. **Status Logic**:
   - `time_in` - Student has arrived but not yet departed
   - `time_out` - Student has both arrival and departure recorded (complete)
   - `present` - Legacy status for completed attendance records
   - `absent` - Manual marking for absent students

### Section-Based Organization
- **Grade Levels**: Students organized by grade (e.g., Grade 1, Grade 11, Grade 12)
- **Section Names**: Named sections within grade levels (e.g., BARBERRA, ONGPIN, KALACHUCHI)
- **Section Metadata**: Each section tracks adviser name, school year, and active/inactive status
- **Student Assignment**: Every student belongs to one section via the `section` field

### Attendance Record Uniqueness
- **One Record Per Day**: Database constraint ensures only one attendance record per student per date
- **Composite Unique Key**: `(lrn, date)` prevents duplicates
- **Update on Duplicate**: If Time In already exists, Time Out updates the same record
- **Cascade Delete**: Deleting a student automatically removes all their attendance records

## 📁 File Structure

```
ACSCCI-Attendance-Checker/
├── index.php                        # Public landing page
├── scan_attendance.php              # Full-screen QR code scanner (instant attendance)
├── view_students.php                # Public student directory
├── test_attendance_email.php        # Email testing utility
├── GENERAL_PROCEDURE_FLOWCHART.md   # System flowcharts and procedures
│
├── admin/                           # Admin-only area (requires login)
│   ├── dashboard.php                # Real-time admin dashboard with charts
│   ├── login.php                    # Admin authentication
│   ├── logout.php                   # Session termination
│   ├── forgot_password.php          # Password reset request
│   ├── reset_password.php           # Password reset form with token
│   ├── manage_students.php          # Full student CRUD operations
│   ├── manage_sections.php          # Section management interface
│   ├── manual_attendance.php        # Manual attendance entry (single/bulk + QR scanner)
│   ├── attendance_reports_sections.php  # Generate reports by section/date
│   ├── view_students.php            # Admin student list view
│   │
│   ├── includes/                    # Admin UI components
│   │   ├── header_modern.php        # Admin header with navigation
│   │   └── footer_modern.php        # Admin footer scripts
│   │
│   └── api/                         # Admin-specific APIs
│       └── dashboard_stats.php      # Dashboard data endpoint
│
├── api/                             # Public/shared API endpoints
│   ├── register_student.php         # Student registration handler
│   ├── mark_attendance.php          # QR scan attendance marker
│   ├── get_students.php             # Fetch all students (datatables)
│   ├── list_students.php            # Student list for dropdowns
│   ├── get_student_details.php      # Single student lookup by LRN
│   ├── get_today_attendance.php     # Today's attendance records
│   ├── delete_student.php           # Student deletion with logging
│   ├── regenerate_qrcode.php        # QR code regeneration
│   ├── get_classes.php              # Available classes/sections
│   ├── get_attendance_report_sections.php  # Report data generator
│   ├── export_attendance_sections_csv.php  # CSV export handler
│   ├── request_password_reset.php   # Password reset email sender
│   └── update_password.php          # Password update handler
│
├── config/                          # Configuration files
│   ├── db_config.php                # Database credentials & timezone
│   └── email_config.php             # SMTP settings (PHPMailer)
│
├── includes/                        # Shared includes
│   ├── database.php                 # PDO database class
│   ├── navigation.php               # Public navigation menu
│   └── qrcode_helper.php            # QR code generation utilities
│
├── css/                             # Stylesheets
│   ├── style.css                    # Legacy/public styles
│   ├── asj-theme.css                # Main ASJ branding theme
│   ├── asj-admin-theme.css          # Admin area theme
│   ├── asj-dashboard-theme.css      # Dashboard styling
│   ├── asj-dashboard-modern.css     # Modern dashboard enhancements
│   ├── modern-design.css            # Modern design elements (gradients, variables)
│   ├── admin-login.css              # Login page styling
│   ├── manage-students.css          # Student management styles
│   ├── manage-sections-modern.css   # Section management styles
│   ├── manual-attendance-modern.css # Manual attendance styles
│   └── admin-students-mobile.css    # Mobile responsive styles
│
├── js/                              # JavaScript files
│   ├── main.js                      # General utilities
│   ├── admin-login.js               # Login form handler
│   ├── admin-students.js            # Student management logic
│   ├── view_students.js             # Student view interactions
│   └── qrcode.min.js                # QR code generation library
│
├── libs/                            # Third-party libraries
│   └── PHPMailer/                   # Email sending library
│       ├── PHPMailer.php
│       ├── SMTP.php
│       ├── Exception.php
│       └── ... (other PHPMailer files)
│
├── assets/                          # Static assets
│   └── asj-logo.png                 # School logo image
│
├── database/                        # Database scripts
│   └── asj_attendease_db.sql        # CURRENT DATABASE EXPORT (Feb 3, 2026)
│
├── uploads/                         # User-generated content
│   └── qrcodes/                     # Generated QR code images
│       └── ... (auto-generated)
│
├── GENERAL_PROCEDURE_FLOWCHART.md   # Development and system flowcharts
│
└── README.md                        # This documentation file
```

## � Database Schema

### Core Tables

**students**
- `id` - Auto-increment primary key
- `lrn` - Unique 11-13 digit Learner Reference Number
- `first_name`, `middle_name`, `last_name` - Student full name
- `gender` - ENUM: Male, Female, M, F
- `email` - Unique email address
- `class` - Grade level (e.g., "Grade 11")
- `section` - Section name (e.g., "BARBERRA")
- `qr_code` - Path to QR code image
- `created_at` - Registration timestamp

**sections**
- `id` - Auto-increment primary key
- `section_name` - Unique section identifier
- `grade_level` - Grade level (e.g., "11")
- `adviser` - Class adviser name
- `school_year` - Academic year (e.g., "2025-2026")
- `status` - ENUM: active, inactive
- `created_at`, `updated_at` - Timestamps

**attendance**
- `id` - Auto-increment primary key
- `lrn` - Foreign key to students.lrn (CASCADE DELETE)
- `date` - Attendance date
- `time_in` - Arrival time (TIME format)
- `time_out` - Departure time (TIME format)
- `section` - Student's section
- `status` - ENUM: present, absent, time_in, time_out
- `email_sent` - Boolean flag for notifications
- `created_at`, `updated_at` - Timestamps
- **UNIQUE KEY**: `(lrn, date)` - One record per student per day

**admin_users**
- `id` - Auto-increment primary key
- `username` - Unique admin username
- `password` - MD5 hashed password
- `email` - Unique email for password recovery
- `reset_token`, `reset_token_expires_at` - Password reset functionality
- `role` - ENUM: admin, teacher, staff
- `is_active` - Boolean active status
- `last_login` - Last login timestamp
- `created_at` - Account creation timestamp

**admin_activity_log**
- `id` - Auto-increment primary key
- `admin_id` - Reference to admin_users.id
- `action` - Action type (LOGIN, LOGOUT, ADD_SECTION, DELETE_STUDENT, etc.)
- `details` - Detailed description of action
- `ip_address` - IP address of admin
- `created_at` - Action timestamp

### Stored Procedures

**RegisterStudent** - Validates and registers new students with LRN format checking

**MarkTimeIn** - Records student arrival with automatic section lookup and duplicate prevention

**MarkTimeOut** - Records student departure and updates attendance status

## 🔧 Usage Guide

### For Students

#### 1. Self-Registration
1. Visit `http://localhost/ACSCCI-Attendance-Checker/register_student.php`
2. Enter your 11-13 digit LRN
3. Fill in your full name (first, middle, last)
4. Select gender and enter email address
5. Choose your grade level and section
6. Click "Register" - QR code is automatically generated
7. Download or print your QR code for attendance scanning

#### 2. Marking Attendance via QR Scan
1. Visit `http://localhost/ACSCCI-Attendance-Checker/scan_attendance.php`
2. Allow camera access when prompted
3. Hold your QR code in front of the camera
4. System instantly records your Time In or Time Out
5. View confirmation message with your details and timestamp

### For Teachers/Admins

#### 1. Admin Login
1. Navigate to `http://localhost/ACSCCI-Attendance-Checker/admin/login.php`
2. Enter username and password (default: `admin` / `admin123456`)
3. Access admin dashboard with all management features

#### 2. Dashboard Overview
- View real-time statistics: Today's attendance, active sections, Time In count
- Monitor weekly attendance trends with interactive bar chart
- Check section-wise attendance distribution with donut chart
- See recent Time In/Time Out activity feed
- Identify incomplete records needing Time Out

#### 3. Managing Sections
1. Go to **Admin → Manage Sections**
2. **Add Section**: Click "Add New Section", enter section name, grade level, adviser, school year
3. **Edit Section**: Click edit icon, update details, save changes
4. **Delete Section**: Click delete icon (warns if students assigned)
5. View student count per section in real-time table

#### 4. Managing Students
1. Go to **Admin → Manage Students**
2. **Add Student**: Click "Add New Student", fill registration form with LRN, names, email, section
3. **View Students**: Search, filter, sort using DataTables interface
4. **Edit Student**: Click edit icon, update information, regenerate QR code if needed
5. **Delete Student**: Click delete icon (removes all attendance records)
6. **Print QR Codes**: Click print icon to generate printable QR code card

#### 5. Manual Attendance Entry
1. Go to **Admin → Manual Attendance**
2. **Single Entry Mode**:
   - Enter student LRN (auto-fills name and section)
   - Select date and action (Time In, Time Out, or Both)
   - Enter specific time or use current time
   - Click "Mark Attendance"
3. **Bulk Entry Mode**:
   - Select multiple students from list
   - Choose date and action
   - Enter time for all selected students
   - Click "Mark Bulk Attendance"
4. **QR Scanner Mode**:
   - Click "QR Scanner" tab
   - Click "Start Scanner" button
   - Scan student QR codes directly in the interface
   - System populates LRN field automatically

#### 6. Generating Reports
1. Go to **Admin → Attendance Reports**
2. **Filter Options**:
   - Select date range (From Date - To Date)
   - Choose specific section or "All Sections"
   - Filter by status (All, Present, Time In, Time Out, Absent)
3. **View Report**: Click "Generate Report" to see results table
4. **Export CSV**: Click "Export to CSV" to download data
5. **Analyze**: View summary statistics (total records, present, absent, incomplete)

#### 7. Password Management
1. **Change Password**: Go to admin profile settings (if implemented)
2. **Forgot Password**:
   - Click "Forgot Password?" on login page
   - Enter registered email address
   - Check email for reset link
   - Click link and enter new password
   - Token expires after set duration for security

## 🛠️ Customization & Extension

### Adding New Sections
```sql
INSERT INTO sections (section_name, grade_level, adviser, school_year, status) 
VALUES ('SAMPAGUITA', '10', 'Ms. Maria Santos', '2025-2026', 'active');
```

### Bulk Student Import
Create a PHP script to import from CSV:
```php
// Import students from CSV file
$csv = array_map('str_getcsv', file('students.csv'));
foreach ($csv as $row) {
    // Insert into students table with QR generation
}
```

### Custom Attendance Rules
Edit `api/mark_attendance.php` to add custom logic:
```php
// Example: Block attendance outside school hours
$current_hour = (int)date('H');
if ($current_hour < 6 || $current_hour > 18) {
    echo json_encode(['success' => false, 'message' => 'Attendance only allowed 6 AM - 6 PM']);
    exit;
}
```

### Email Notification Customization
Modify `config/email_config.php` and attendance marking logic:
- Send email on Time In
- Send email on missing Time Out after school hours
- Daily attendance summary emails to advisers
- Weekly reports to parents

### Dashboard Widgets
Add custom widgets to `admin/dashboard.php`:
```javascript
// Example: Add "Late Students" widget
const lateStudents = data.todayAttendance.filter(record => {
    const timeIn = new Date(`2000-01-01 ${record.time_in}`);
    return timeIn.getHours() > 8; // After 8 AM = Late
});
```

### Custom Report Types
Create new report pages in `admin/` folder:
- Monthly attendance summary by section
- Student attendance percentage rankings
- Absent students daily report
- Section comparison analytics

## 🔍 Testing & Verification

### Quick System Test

1. **Test Student Registration**:
   ```
   LRN: 136514240419
   Name: Test Student
   Email: test@example.com
   Section: KALACHUCHI
   ```
   - Verify QR code is generated in `uploads/qrcodes/`
   - Check student appears in Manage Students table

2. **Test QR Scanner**:
   - Visit `scan_attendance.php`
   - Allow camera access
   - Scan generated QR code
   - Verify Time In is recorded in database
   - Scan again → should record Time Out

3. **Test Manual Attendance**:
   - Login as admin
   - Go to Manual Attendance
   - Enter test LRN → auto-fills student details
   - Mark Time In → check Today's Attendance table updates
   - Mark Time Out → verify record is complete

4. **Test Dashboard**:
   - Check statistics update in real-time
   - Verify charts display correctly (Chart.js loaded)
   - Check Recent Activity shows latest records
   - Verify Needs Attention list shows incomplete records

5. **Test Reports**:
   - Generate report for today's date
   - Filter by specific section
   - Export to CSV and verify data format
   - Check summary statistics accuracy

### Timezone & Time Verification
- All times displayed in **Asia/Manila** timezone
- PHP: `date_default_timezone_set('Asia/Manila')` in `config/db_config.php`
- Database: Stores TIME and TIMESTAMP in local timezone
- JavaScript: Uses browser's local time, adjust if needed

### Browser Compatibility Testing
- ✅ **Chrome/Edge**: Full support (recommended)
- ✅ **Firefox**: Full support
- ✅ **Safari**: Full support (iOS 11+)
- ⚠️ **Camera Access**: Requires HTTPS (except localhost)

## 🔒 Security Features

### Authentication & Authorization
- **Session-based authentication** for admin area
- **Password hashing** using MD5 (upgrade to bcrypt recommended)
- **Password reset tokens** with expiration timestamps
- **Role-based access control** (admin, teacher, staff roles)
- **Logout functionality** with session destruction
- **Login attempt tracking** via activity log

### Data Protection
- **SQL injection prevention** using PDO prepared statements throughout codebase
- **XSS protection** via htmlspecialchars() on user outputs
- **CSRF protection** recommended (add tokens to forms)
- **Input validation**:
  - LRN format: 11-13 digits numeric only
  - Email format: RFC 5322 validation
  - Date format: YYYY-MM-DD validation
  - Required field enforcement

### Database Security
- **Foreign key constraints** with CASCADE DELETE for data integrity
- **UNIQUE constraints** on LRN and email to prevent duplicates
- **Stored procedures** for complex operations (RegisterStudent, MarkTimeIn, MarkTimeOut)
- **Indexed queries** for performance and security
- **Connection encryption** (configure SSL for MySQL in production)

### File Security
- **QR code uploads** restricted to `uploads/qrcodes/` directory
- **File type validation** for QR code generation only
- **Directory listing disabled** (configure .htaccess)
- **Write permissions** only on uploads and logs directories

### Audit & Logging
- **Complete activity log** tracking all admin actions:
  - LOGIN, LOGOUT events with IP address
  - ADD_SECTION, EDIT_SECTION, DELETE_SECTION
  - DELETE_STUDENT with cascade delete count
  - MANUAL_ATTENDANCE with LRN and timestamp
- **IP address logging** for accountability
- **Timestamp tracking** on all database records (created_at, updated_at)

### Recommended Security Enhancements
1. **Upgrade password hashing**: Replace MD5 with `password_hash()` and `password_verify()`
   ```php
   $hash = password_hash($password, PASSWORD_BCRYPT);
   if (password_verify($input_password, $stored_hash)) { /* success */ }
   ```

2. **Add CSRF tokens**: Implement in all forms
   ```php
   $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
   ```

3. **Rate limiting**: Prevent brute force login attempts
4. **HTTPS enforcement**: Redirect HTTP to HTTPS in production
5. **Content Security Policy**: Add CSP headers
6. **Two-factor authentication**: Add 2FA for admin accounts

## 🐛 Troubleshooting

### Common Issues & Solutions

#### 1. Camera Not Working in QR Scanner
**Symptoms**: Black screen, "Camera not found" error, or permission denied

**Solutions**:
- **HTTPS Required**: Camera API requires HTTPS (except on localhost)
  ```
  Production: Use SSL certificate
  Development: http://localhost works fine
  ```
- **Browser Permissions**: Check browser settings → Site permissions → Camera → Allow
- **Browser Compatibility**: Use Chrome/Edge (recommended), Firefox, or Safari
- **Check Camera in Use**: Close other apps using camera (Zoom, Teams, etc.)
- **Mobile**: Ensure camera permission granted in phone settings

#### 2. QR Code Not Scanning
**Symptoms**: Scanner sees QR but doesn't register, or no detection

**Solutions**:
- **Lighting**: Ensure adequate lighting, avoid glare or shadows
- **Distance**: Hold QR code 15-30cm from camera
- **Focus**: Wait 1-2 seconds for camera to autofocus
- **QR Quality**: Regenerate QR code if damaged or low quality
- **Print Quality**: Use at least 300 DPI for printed QR codes
- **Check Browser Console**: Look for JavaScript errors (F12 → Console tab)

#### 3. Database Connection Failed
**Symptoms**: "Connection failed", blank pages, or 500 errors

**Solutions**:
```php
// Check config/db_config.php
define('DB_HOST', 'localhost');      // Correct host?
define('DB_NAME', 'asj_attendease_db'); // Database exists?
define('DB_USER', 'root');           // Correct username?
define('DB_PASS', '');               // Correct password?
```
- **Verify MySQL Running**: XAMPP Control Panel → MySQL → Start
- **Test Connection**: 
  ```bash
  mysql -u root -p
  USE asj_attendease_db;
  SHOW TABLES;
  ```
- **Check Firewall**: Allow MySQL port 3306
- **PDO Extension**: Verify `extension=pdo_mysql` in php.ini

#### 4. Admin Login Not Working
**Symptoms**: "Invalid credentials", redirect loop, or session errors

**Solutions**:
- **Default Credentials**: Username: `admin`, Password: `admin123456`
- **Check Database**: 
  ```sql
  SELECT * FROM admin_users WHERE username='admin';
  -- Password should be MD5 hash: 0192023a7bbd73250516f069df18b500
  ```
- **Session Issues**: 
  - Clear browser cache and cookies
  - Check `session_start()` in PHP files
  - Verify `session.save_path` in php.ini is writable
- **Password Reset**: Use "Forgot Password" feature or manually update:
  ```sql
  UPDATE admin_users SET password=MD5('newpassword') WHERE username='admin';
  ```

#### 5. QR Codes Not Generating
**Symptoms**: Missing QR code images, broken image links

**Solutions**:
- **Check Directory Permissions**:
  ```bash
  chmod 755 uploads/qrcodes/
  # Windows: Right-click folder → Properties → Security → Full Control
  ```
- **Verify GD Extension**: Check `extension=gd` in php.ini is enabled
- **Check File Path**: QR codes saved to `uploads/qrcodes/student_{ID}.png`
- **Library Present**: Ensure `libs/phpqrcode.php` exists
- **Regenerate**: Use "Regenerate QR Code" button in Manage Students

#### 6. Charts Not Displaying on Dashboard
**Symptoms**: Empty chart areas, "Chart is not defined" error

**Solutions**:
- **Check Chart.js CDN**: Verify internet connection or download locally
  ```html
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  ```
- **Browser Console**: Check for JavaScript errors (F12 → Console)
- **Data Issues**: Verify `getDashboardData()` returns valid data
- **Clear Cache**: Hard refresh (Ctrl+Shift+R / Cmd+Shift+R)

#### 7. Email Notifications Not Sending
**Symptoms**: No password reset emails, SMTP errors

**Solutions**:
- **Configure SMTP**: Edit `config/email_config.php`
  ```php
  define('SMTP_HOST', 'smtp.gmail.com');
  define('SMTP_PORT', 587);
  define('SMTP_USER', 'your-email@gmail.com');
  define('SMTP_PASS', 'app-specific-password'); // Not regular Gmail password
  ```
- **Gmail App Password**: Enable 2FA and generate app password
- **Check PHPMailer**: Verify `libs/PHPMailer/` folder exists
- **Test Email**: Check spam/junk folder
- **SMTP Logs**: Enable debug mode in PHPMailer
  ```php
  $mail->SMTPDebug = 2; // Detailed debug output
  ```

#### 8. Timezone Showing Wrong Time
**Symptoms**: Times are off by hours, wrong date displayed

**Solutions**:
- **PHP Timezone**: Verify in `config/db_config.php`
  ```php
  date_default_timezone_set('Asia/Manila');
  ```
- **MySQL Timezone**: Check and set if needed
  ```sql
  SET GLOBAL time_zone = '+08:00';
  SELECT NOW(); -- Should show Philippine time
  ```
- **Browser Time**: Check device clock is correct
- **Server Time**: Verify server timezone matches application

#### 9. Duplicate Attendance Records
**Symptoms**: Multiple Time In records for same student same day

**Solutions**:
- **Database Constraint**: Verify UNIQUE constraint exists
  ```sql
  ALTER TABLE attendance ADD UNIQUE KEY unique_daily_attendance (lrn, date);
  ```
- **Check API Logic**: `api/mark_attendance.php` should use INSERT...ON DUPLICATE KEY UPDATE
- **Clear Duplicates**: 
  ```sql
  DELETE a1 FROM attendance a1
  INNER JOIN attendance a2 
  WHERE a1.id > a2.id AND a1.lrn = a2.lrn AND a1.date = a2.date;
  ```

#### 10. Slow Performance
**Symptoms**: Pages load slowly, database queries timeout

**Solutions**:
- **Add Indexes**: Ensure indexes exist on frequently queried columns
  ```sql
  CREATE INDEX idx_lrn_date ON attendance(lrn, date);
  CREATE INDEX idx_date_section ON attendance(date, section);
  ```
- **Optimize Queries**: Use EXPLAIN to analyze slow queries
- **Limit Results**: Add pagination to large result sets
- **Cache Results**: Implement caching for dashboard statistics
- **MySQL Tuning**: Increase `innodb_buffer_pool_size` in my.cnf

## 🚀 Future Enhancements & Roadmap

### Phase 1: Core Improvements (Short-term)
- [ ] **Password Security Upgrade**: Replace MD5 with bcrypt/Argon2
- [ ] **CSRF Protection**: Add tokens to all forms
- [ ] **Rate Limiting**: Prevent brute force attacks on login
- [ ] **Advanced Search**: Full-text search for students with autocomplete
- [ ] **Bulk QR Print**: Generate PDF with multiple QR codes per page
- [ ] **Data Validation**: Enhanced client-side and server-side validation

### Phase 2: Feature Extensions (Mid-term)
- [ ] **Parent Portal**: Parents can view their child's attendance history
- [ ] **SMS Notifications**: Send SMS alerts for attendance events
- [ ] **Attendance Scheduling**: Set required attendance days/times per section
- [ ] **Holiday Management**: Mark holidays and exclude from reports
- [ ] **Late Threshold**: Configurable late time per section
- [ ] **Excuse Management**: Track excused absences with reason codes
- [ ] **Multi-school Support**: Tenant isolation for multiple schools

### Phase 3: Advanced Features (Long-term)
- [ ] **Mobile Apps**: Native iOS/Android apps with offline support
- [ ] **Biometric Integration**: Fingerprint and face recognition options
- [ ] **AI Analytics**: Predictive analytics for attendance patterns
- [ ] **API Gateway**: RESTful API for third-party integrations
- [ ] **Real-time Dashboard**: WebSocket-based live updates
- [ ] **Geofencing**: Location-based attendance (on-campus verification)
- [ ] **Voice Commands**: Voice-activated attendance marking
- [ ] **Blockchain Ledger**: Immutable attendance records

### Integration Possibilities
- **Google Classroom**: Sync attendance with Google Classroom
- **Canvas LMS**: Export attendance to Canvas gradebook
- **PowerSchool**: Bidirectional sync with PowerSchool SIS
- **Microsoft Teams**: Attendance bot for online classes
- **Zapier**: Automate workflows with 3000+ apps

### Analytics & Reporting Enhancements
- **Attendance Percentage Rankings**: Leaderboard of best attendance
- **Trend Analysis**: Identify patterns (Monday absences, etc.)
- **Cohort Analysis**: Compare attendance across sections/grades
- **Export Formats**: Excel, PDF, JSON, XML options
- **Automated Reports**: Schedule daily/weekly email reports
- **Visualization**: Heat maps, trend lines, pie charts

### Performance Optimizations
- **Redis Caching**: Cache frequent queries for faster load times
- **CDN Integration**: Serve static assets from CDN
- **Database Sharding**: Horizontal scaling for large deployments
- **Load Balancing**: Distribute traffic across multiple servers
- **Progressive Web App**: Installable PWA with offline capabilities

## 🤝 Contributing

We welcome contributions to improve AttendEase! Here's how you can help:

### Bug Reports
- Check existing issues before creating new ones
- Include steps to reproduce the bug
- Provide error messages and screenshots
- Specify your environment (PHP version, browser, OS)

### Feature Requests
- Describe the feature and its benefits
- Explain use cases and expected behavior
- Consider backward compatibility

### Pull Requests
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request with detailed description

### Development Guidelines
- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Comment complex logic blocks
- Test thoroughly before submitting
- Update README.md if adding new features

## 📞 Support & Contact

### Documentation
- **README**: You're reading it! Check troubleshooting section first
- **Code Comments**: Inline documentation in all major files
- **Database Schema**: See `database/asj_attendease_db.sql` for structure

### Community
- **GitHub Issues**: Report bugs and request features
- **GitHub Discussions**: Ask questions and share ideas
- **Email**: attendease08@gmail.com (official support)

### Professional Support
For custom development, training, or deployment assistance:
- Custom features and integrations
- On-site training for staff
- Cloud deployment and maintenance
- Multi-school enterprise setup

## 📄 License

This project is developed for educational purposes and is open-source.

**Terms of Use**:
- ✅ Free to use for educational institutions
- ✅ Modify and customize for your needs
- ✅ Deploy in your school/organization
- ❌ Do not sell as a commercial product without permission
- ❌ Do not remove attribution or credits

**Attribution**: Please retain credits and link back to the original repository.

## 🙏 Acknowledgments

### Technologies Used
- **PHP 8+**: Server-side programming
- **MySQL 8**: Relational database management
- **Chart.js 4.4.0**: Interactive data visualization
- **ZXing Library**: QR code scanning (BrowserQRCodeReader)
- **PHPQRCode**: Server-side QR code generation
- **PHPMailer**: Email functionality
- **DataTables**: Advanced table features
- **Bootstrap Icons**: UI icons
- **Google Fonts (Poppins)**: Typography

### Inspiration & Credits
- Built for educational institutions in the Philippines
- Designed to comply with DepEd (Department of Education) LRN standards
- Inspired by modern attendance management systems
- Special thanks to the open-source community

### Project Information
- **Project Name**: Academy of St. Joseph Claveria, Cagayan Inc. Attendance Checker
- **Institution**: Academy of St. Joseph Claveria, Cagayan Inc.
- **Repository**: Academy-of-St.Joseph-Claveria-Cagayan-Inc.-Attendance-Checker
- **Owner**: TsmHabib03
- **Current Version**: 3.0 (Multi-Role, AM/PM Sessions, Teacher Attendance, Behavior Monitoring)
- **Last Updated**: February 3, 2026
- **License**: Educational Open Source
- **Research Purpose**: Developed as part of a research study on modernizing attendance management systems in educational institutions

---

## 📊 Quick Start Summary

```bash
# 1. Import database
mysql -u root -p < database/asj_attendease_db.sql

# 2. Configure database
nano config/db_config.php  # Update credentials

# 3. Set permissions
chmod 755 uploads/qrcodes/

# 4. Access application
http://localhost/ACSCCI-Attendance-Checker/

# 5. Admin login
Username: admin
Password: admin123456
```

---

## 🎓 Research Context

This attendance management system was developed as a research project for the Academy of St. Joseph Claveria, Cagayan Inc. The research aims to:

- **Modernize Attendance Tracking**: Replace traditional manual attendance methods with automated QR code technology
- **Improve Accuracy**: Eliminate human error in attendance recording and reporting
- **Save Time**: Reduce time spent on attendance marking and report generation
- **Enhance Data Analysis**: Provide comprehensive analytics and insights into attendance patterns
- **Increase Security**: Ensure accurate identification through unique LRN-based QR codes
- **Support Decision Making**: Provide administrators with real-time data for informed decisions

### Technology Stack Overview

**Frontend Technologies:**
- HTML5, CSS3, JavaScript (ES6+)
- Chart.js 4.4.0 for data visualization
- ZXing library for QR code scanning
- Responsive design with mobile-first approach

**Backend Technologies:**
- PHP 8+ with PDO for database operations
- MySQL 8.0 with InnoDB engine
- PHPMailer for email notifications
- PHPQRCode for QR generation

**Security Features:**
- Prepared statements to prevent SQL injection
- Session-based authentication
- Password hashing and reset functionality
- Activity logging and audit trails

**Key Features for Research:**
- Real-time attendance monitoring
- Comprehensive reporting and analytics
- Section-based organization
- Time In/Time Out tracking
- Automated notifications
- Data export capabilities (CSV)

---

**📚 Academy of St. Joseph Cleveria, Cagayan Inc. - Attendance Management System**

For questions, issues, or contributions related to this research project, please visit the GitHub repository or contact support.

---
## 💡 Key Concepts & Technology Stack

### Frontend Technologies
- **HTML5**: Semantic markup, forms, media elements for structured content
- **CSS3**: Modern design system with CSS variables, gradients, and animations
- **JavaScript (ES6+)**: Async/await, fetch API, DOM manipulation for dynamic interactions
- **Chart.js 4.4.0**: Interactive bar and donut charts for attendance analytics and data visualization
- **ZXing Library**: Browser-based QR code scanning with continuous autofocus for real-time attendance marking
- **DataTables**: Advanced table features including search, sort, and pagination for managing large datasets
- **Responsive Design**: Mobile-first approach using flexbox and grid for cross-device compatibility

### Backend Technologies
- **PHP 8+**: Modern PHP with type declarations, named arguments, and improved error handling
- **PDO (PHP Data Objects)**: Database abstraction layer with prepared statements for security
- **MySQLi**: Alternative database interface for specific database operations
- **PHPMailer**: SMTP email sending library for password resets and notifications
- **PHPQRCode**: Server-side QR code image generation using GD library
- **Session Management**: Secure admin authentication with session lifecycle management

### Database Architecture
- **MySQL 8.0**: Relational database management system with InnoDB storage engine
- **Stored Procedures**: MarkTimeIn, MarkTimeOut, RegisterStudent for business logic encapsulation
- **Foreign Keys**: CASCADE DELETE relationships for maintaining data integrity
- **Database Indexes**: Optimized queries on lrn, date, and section columns for fast retrieval
- **UNIQUE Constraints**: Prevent duplicate attendance records using composite keys (lrn, date)
- **ENUM Data Types**: Status fields with predefined values for data consistency
- **Triggers**: Automatic timestamp updates for data auditing

### System Architecture
- **MVC-like Structure**: Clear separation of concerns between views, business logic, and data access
- **RESTful API Design**: JSON-based APIs for frontend-backend communication
- **Database Abstraction Layer**: Centralized `Database` class for all database connections
- **Configuration Management**: Environment-specific settings in dedicated config files
- **Error Handling Strategy**: Try-catch blocks with user-friendly error messages and logging
- **Activity Logging**: Complete audit trail of all administrative actions with timestamps

### Security Implementation
1. **Input Validation**: Multi-layer validation (client-side JavaScript and server-side PHP)
2. **SQL Injection Prevention**: Exclusively using prepared statements with parameterized queries
3. **XSS Protection**: Output escaping using htmlspecialchars() on all user-generated content
4. **Session Security**: Session regeneration, timeout mechanisms, and secure cookie flags
5. **Access Control**: Role-based access control (RBAC) with admin authentication checks
6. **Password Security**: MD5 hashing (upgradeable to bcrypt/Argon2 for enhanced security)
7. **CSRF Protection**: Token-based validation for state-changing operations
8. **Audit Logging**: Comprehensive tracking of all administrative actions with IP addresses and timestamps

### Performance Optimization
- **Database Indexing**: Strategic indexes on frequently queried columns
- **Query Optimization**: Efficient JOIN operations and subquery usage
- **Caching Strategy**: Session-based caching for frequently accessed data
- **Asset Optimization**: Minified CSS and JavaScript files
- **CDN Integration**: External libraries loaded from CDNs for faster delivery

## 📚 Research and Learning Outcomes

### Academic Learning Objectives

This attendance management system serves as a comprehensive case study for understanding modern web application development and its application in educational technology. Researchers and students can gain insights into:

**Database Design & Management**:
- Relational database schema design and entity-relationship modeling
- Foreign key relationships and referential integrity with CASCADE operations
- Indexing strategies for query performance optimization
- Stored procedures for encapsulating business logic
- Data normalization principles (1NF, 2NF, 3NF)
- Database constraints and data integrity enforcement

**Backend Development with PHP**:
- PDO (PHP Data Objects) and prepared statements for secure database access
- Session management and authentication mechanisms
- File system operations and image generation
- SMTP email sending for automated notifications
- Comprehensive error handling and logging strategies
- RESTful API endpoint development and best practices

**Frontend Development**:
- HTML5 Camera API and media stream manipulation
- QR code scanning implementation using ZXing library
- Asynchronous JavaScript with Fetch API and Promises
- Data visualization with Chart.js for attendance analytics
- DOM manipulation and event-driven programming
- Client-side form validation and user experience design

**Web Application Security**:
- SQL injection prevention through parameterized queries
- Cross-site scripting (XSS) mitigation techniques
- Authentication and authorization implementation
- Password hashing algorithms (MD5, bcrypt, Argon2)
- CSRF (Cross-Site Request Forgery) protection concepts
- Secure session management and cookie handling
- Input validation and sanitization strategies

**Full-Stack Integration**:
- Frontend-backend communication patterns
- RESTful API design and JSON data interchange
- File system operations and upload handling
- Database transactions and ACID properties
- Real-time data updates and dynamic content rendering
- Responsive web design principles and mobile-first approach

**Software Engineering Practices**:
- Code organization and modular architecture
- Version control with Git and GitHub
- Documentation and code commenting standards
- Testing strategies and quality assurance
- Deployment procedures and server configuration
- Maintenance and troubleshooting methodologies

### Research Applications

**Educational Technology Research**:
- Effectiveness of QR code-based attendance systems
- Time efficiency comparison: manual vs. automated attendance
- Accuracy and reliability of attendance data collection
- User acceptance and adoption of digital attendance solutions
- Impact on administrative workload reduction

**Data Analytics Research**:
- Attendance pattern analysis and trend identification
- Predictive modeling for student attendance behavior
- Correlation studies between attendance and academic performance
- Visualization techniques for educational data
- Report generation and data export methodologies

**System Performance Studies**:
- Database query optimization and response time analysis
- Scalability testing for large student populations
- Network latency and QR code scanning speed evaluation
- Cross-browser and cross-device compatibility assessment
- Security vulnerability testing and penetration analysis
