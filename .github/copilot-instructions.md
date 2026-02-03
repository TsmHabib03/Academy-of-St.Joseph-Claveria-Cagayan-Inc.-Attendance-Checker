# AttendEase v3.0 - Copilot Instructions

## Project Overview
QR-based attendance management system for Academy of St. Joseph Claveria. PHP 8+ backend, MySQL 8.0 database, XAMPP on Windows.

## Architecture

### Directory Structure
- `admin/` - Admin panel pages (include `config.php` first, then `includes/header_modern.php`)
- `api/` - JSON REST endpoints (return `['success' => bool, 'message' => string]`)
- `config/` - Database/email configuration
- `includes/` - Shared utilities (`database.php`, `auth_middleware.php`, `qrcode_helper.php`)
- `css/` - Design system files (primary: `manual-attendance-modern.css`)

### Database Connection Pattern
```php
// In admin pages:
require_once 'config.php';  // Sets up $pdo, session, auth functions
requireAdmin();             // Enforces login

// In API endpoints:
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/database.php';
```

### Role-Based Access Control
Use `includes/auth_middleware.php` for permission checks:
```php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole([ROLE_ADMIN]);  // Constants: ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF, ROLE_STUDENT
```

## UI Design System

### CSS Framework
Use `manual-attendance-modern.css` for admin pages - it defines the ASJ green theme:
```php
$additionalCSS = ['../css/manual-attendance-modern.css?v=' . time()];
```

### Color Palette (CRITICAL - No violet/purple colors)
```css
--asj-green-50: #E8F5E9;   --asj-green-500: #4CAF50;
--asj-green-100: #C8E6C9;  --asj-green-600: #43A047;
--asj-green-400: #66BB6A;  --asj-green-700: #388E3C;
```

### Page Header Pattern
All admin pages use the enhanced header with gradient:
```php
<div class="page-header-enhanced">
    <div class="page-header-background">
        <div class="header-gradient-overlay"></div>
        <div class="header-pattern"></div>
    </div>
    <div class="page-header-content-enhanced">
        <div class="page-title-section">
            <div class="page-icon-enhanced">
                <i class="fas fa-<?php echo $pageIcon; ?>"></i>
            </div>
            <div class="page-title-content">
                <div class="breadcrumb-nav">...</div>
                <h1 class="page-title-enhanced"><?php echo $pageTitle; ?></h1>
            </div>
        </div>
    </div>
</div>
```

### Form & Card Classes
- `.form-card`, `.form-card-header`, `.form-card-body` - Card containers
- `.form-input`, `.form-select` - Form controls
- `.data-table` - Tables with green gradient header
- `.modal-overlay`, `.modal-container` - Modal dialogs
- `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-danger` - Buttons

## Common Patterns

### Admin Page Setup
```php
<?php
require_once 'config.php';
requireAdmin();
$currentAdmin = getCurrentAdmin();
$pageTitle = 'Page Name';
$pageIcon = 'icon-name';  // Font Awesome icon without 'fa-'
$additionalCSS = ['../css/manual-attendance-modern.css?v=' . time()];
// ... page logic ...
include 'includes/header_modern.php';
?>
<!-- Page content with page-header-enhanced -->
<?php include 'includes/footer_modern.php'; ?>
```

### API Response Pattern
```php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $result,
    'message' => 'Operation completed'
]);
```

### Philippine Timezone
Always use `Asia/Manila` timezone:
```php
date_default_timezone_set('Asia/Manila');
$pdo->exec("SET time_zone = '+08:00'");
```

## Key Tables
- `students` - LRN (primary key), personal info, section
- `teachers` - employee_id (primary key), department, position
- `attendance` - Student attendance (lrn, date, time_in, time_out, session AM/PM)
- `teacher_attendance` - Teacher attendance records
- `sections` - Grade levels and section names
- `schedules` - Configurable AM/PM time windows with late thresholds
- `badges`, `student_badges` - Achievement badge system
- `behavior_alerts` - Attendance pattern monitoring

## Activity Logging
Use `logAdminActivity()` for audit trails:
```php
logAdminActivity('ADD_STUDENT', "Added student: {$firstName} {$lastName} (LRN: {$lrn})");
```

## QR Code Generation
Use `includes/qrcode_helper.php`:
```php
require_once __DIR__ . '/../includes/qrcode_helper.php';
$qrPath = generateQRCode($lrn, 'student');  // or 'teacher' for employee_id
```

## Attendance Flow
1. QR scan hits `api/mark_attendance.php`
2. First scan = Time In, second scan = Time Out
3. Schedule lookup determines if late (based on `schedules` table)
4. Email notification sent if configured
