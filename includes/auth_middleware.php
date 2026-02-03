<?php
/**
 * Authentication Middleware for AttendEase v3.0
 * Role-based access control for Admin, Teacher, and Student users
 * 
 * @package AttendEase
 * @version 3.0
 */

// Prevent direct access
if (!defined('ATTENDEASE_VERSION')) {
    define('ATTENDEASE_VERSION', '3.0');
}

/**
 * User Role Constants
 */
define('ROLE_ADMIN', 'admin');
define('ROLE_TEACHER', 'teacher');
define('ROLE_STAFF', 'staff');
define('ROLE_STUDENT', 'student');

/**
 * Permission Constants
 */
define('PERM_VIEW_DASHBOARD', 'view_dashboard');
define('PERM_MANAGE_STUDENTS', 'manage_students');
define('PERM_MANAGE_TEACHERS', 'manage_teachers');
define('PERM_MANAGE_SECTIONS', 'manage_sections');
define('PERM_VIEW_REPORTS', 'view_reports');
define('PERM_EXPORT_DATA', 'export_data');
define('PERM_MANAGE_SETTINGS', 'manage_settings');
define('PERM_SCAN_ATTENDANCE', 'scan_attendance');
define('PERM_MANUAL_ATTENDANCE', 'manual_attendance');
define('PERM_VIEW_OWN_ATTENDANCE', 'view_own_attendance');
define('PERM_VIEW_SECTION_ATTENDANCE', 'view_section_attendance');
define('PERM_MANAGE_BADGES', 'manage_badges');
define('PERM_VIEW_BEHAVIOR_ALERTS', 'view_behavior_alerts');
define('PERM_MANAGE_SMS', 'manage_sms');

/**
 * Role-Permission Matrix
 * Defines what each role can do
 */
$ROLE_PERMISSIONS = [
    ROLE_ADMIN => [
        PERM_VIEW_DASHBOARD,
        PERM_MANAGE_STUDENTS,
        PERM_MANAGE_TEACHERS,
        PERM_MANAGE_SECTIONS,
        PERM_VIEW_REPORTS,
        PERM_EXPORT_DATA,
        PERM_MANAGE_SETTINGS,
        PERM_SCAN_ATTENDANCE,
        PERM_MANUAL_ATTENDANCE,
        PERM_VIEW_OWN_ATTENDANCE,
        PERM_VIEW_SECTION_ATTENDANCE,
        PERM_MANAGE_BADGES,
        PERM_VIEW_BEHAVIOR_ALERTS,
        PERM_MANAGE_SMS
    ],
    ROLE_TEACHER => [
        PERM_VIEW_DASHBOARD,
        PERM_SCAN_ATTENDANCE,
        PERM_VIEW_OWN_ATTENDANCE,
        PERM_VIEW_SECTION_ATTENDANCE,
        PERM_VIEW_REPORTS,
        PERM_EXPORT_DATA,
        PERM_MANUAL_ATTENDANCE
    ],
    ROLE_STAFF => [
        PERM_VIEW_DASHBOARD,
        PERM_SCAN_ATTENDANCE,
        PERM_VIEW_OWN_ATTENDANCE,
        PERM_VIEW_REPORTS
    ],
    ROLE_STUDENT => [
        PERM_VIEW_OWN_ATTENDANCE,
        PERM_SCAN_ATTENDANCE
    ]
];

/**
 * Check if user is authenticated
 * 
 * @return bool True if user is logged in
 */
function isAuthenticated(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Get current user's role
 * 
 * @return string|null User role or null if not logged in
 */
function getUserRole(): ?string {
    if (!isAuthenticated()) {
        return null;
    }
    return $_SESSION['admin_role'] ?? ROLE_ADMIN;
}

/**
 * Get current user's ID based on their role
 * 
 * @return string|null User ID (admin_id, employee_id, or lrn)
 */
function getUserId(): ?string {
    if (!isAuthenticated()) {
        return null;
    }
    
    $role = getUserRole();
    
    switch ($role) {
        case ROLE_STUDENT:
            return $_SESSION['user_lrn'] ?? null;
        case ROLE_TEACHER:
            return $_SESSION['user_employee_id'] ?? $_SESSION['admin_id'] ?? null;
        default:
            return $_SESSION['admin_id'] ?? null;
    }
}

/**
 * Check if current user has a specific permission
 * 
 * @param string $permission Permission to check
 * @return bool True if user has permission
 */
function hasPermission(string $permission): bool {
    global $ROLE_PERMISSIONS;
    
    $role = getUserRole();
    
    if (!$role) {
        return false;
    }
    
    return in_array($permission, $ROLE_PERMISSIONS[$role] ?? []);
}

/**
 * Check if current user has any of the specified permissions
 * 
 * @param array $permissions Array of permissions to check
 * @return bool True if user has at least one permission
 */
function hasAnyPermission(array $permissions): bool {
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if current user has all of the specified permissions
 * 
 * @param array $permissions Array of permissions to check
 * @return bool True if user has all permissions
 */
function hasAllPermissions(array $permissions): bool {
    foreach ($permissions as $permission) {
        if (!hasPermission($permission)) {
            return false;
        }
    }
    return true;
}

/**
 * Check if current user has a specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool True if user has the role
 */
function hasRole($roles): bool {
    $userRole = getUserRole();
    
    if (!$userRole) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    
    return $userRole === $roles;
}

/**
 * Require specific permission(s) to access a page
 * Redirects to appropriate page if permission denied
 * 
 * @param string|array $permissions Required permission(s)
 * @param string $redirectUrl URL to redirect if denied
 */
function requirePermission($permissions, string $redirectUrl = 'dashboard.php'): void {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
    
    $hasAccess = is_array($permissions) 
        ? hasAnyPermission($permissions) 
        : hasPermission($permissions);
    
    if (!$hasAccess) {
        $_SESSION['error_message'] = 'You do not have permission to access this page.';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Require specific role(s) to access a page
 * 
 * @param string|array $roles Required role(s)
 * @param string $redirectUrl URL to redirect if denied
 */
function requireRole($roles, string $redirectUrl = 'dashboard.php'): void {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
    
    if (!hasRole($roles)) {
        $_SESSION['error_message'] = 'You do not have permission to access this page.';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Get navigation menu items based on user role
 * 
 * @return array Array of menu items with title, url, icon, and permission
 */
function getNavigationMenu(): array {
    $role = getUserRole();
    
    $allMenuItems = [
        [
            'title' => 'Dashboard',
            'url' => 'dashboard.php',
            'icon' => 'fa-home',
            'permission' => PERM_VIEW_DASHBOARD,
            'roles' => [ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF]
        ],
        [
            'title' => 'Manage Students',
            'url' => 'manage_students.php',
            'icon' => 'fa-user-graduate',
            'permission' => PERM_MANAGE_STUDENTS,
            'roles' => [ROLE_ADMIN]
        ],
        [
            'title' => 'Manage Teachers',
            'url' => 'manage_teachers.php',
            'icon' => 'fa-chalkboard-teacher',
            'permission' => PERM_MANAGE_TEACHERS,
            'roles' => [ROLE_ADMIN]
        ],
        [
            'title' => 'Students Directory',
            'url' => 'students_directory.php',
            'icon' => 'fa-address-book',
            'permission' => PERM_VIEW_REPORTS,
            'roles' => [ROLE_ADMIN, ROLE_TEACHER]
        ],
        [
            'title' => 'Manage Sections',
            'url' => 'manage_sections.php',
            'icon' => 'fa-layer-group',
            'permission' => PERM_MANAGE_SECTIONS,
            'roles' => [ROLE_ADMIN]
        ],
        [
            'title' => 'Manual Attendance',
            'url' => 'manual_attendance.php',
            'icon' => 'fa-clipboard-check',
            'permission' => PERM_MANUAL_ATTENDANCE,
            'roles' => [ROLE_ADMIN, ROLE_TEACHER]
        ],
        [
            'title' => 'Attendance Reports',
            'url' => 'attendance_reports_sections.php',
            'icon' => 'fa-chart-bar',
            'permission' => PERM_VIEW_REPORTS,
            'roles' => [ROLE_ADMIN, ROLE_TEACHER]
        ],
        [
            'title' => 'Behavior Monitoring',
            'url' => 'behavior_monitoring.php',
            'icon' => 'fa-exclamation-triangle',
            'permission' => PERM_VIEW_BEHAVIOR_ALERTS,
            'roles' => [ROLE_ADMIN]
        ],
        [
            'title' => 'Manage Badges',
            'url' => 'manage_badges.php',
            'icon' => 'fa-award',
            'permission' => PERM_MANAGE_BADGES,
            'roles' => [ROLE_ADMIN]
        ],
        [
            'title' => 'Attendance Schedules',
            'url' => 'manage_schedules.php',
            'icon' => 'fa-clock',
            'permission' => PERM_MANAGE_SETTINGS,
            'roles' => [ROLE_ADMIN]
        ],
        [
            'title' => 'SMS Logs',
            'url' => 'sms_logs.php',
            'icon' => 'fa-sms',
            'permission' => PERM_MANAGE_SMS,
            'roles' => [ROLE_ADMIN]
        ],
        [
            'title' => 'My Attendance',
            'url' => 'my_attendance.php',
            'icon' => 'fa-calendar-check',
            'permission' => PERM_VIEW_OWN_ATTENDANCE,
            'roles' => [ROLE_TEACHER, ROLE_STUDENT]
        ]
    ];
    
    // Filter menu items based on user's role and permissions
    return array_filter($allMenuItems, function($item) use ($role) {
        // Check if user's role is in the allowed roles
        if (!in_array($role, $item['roles'])) {
            return false;
        }
        // Check if user has the required permission
        return hasPermission($item['permission']);
    });
}

/**
 * Check if a menu item should be active
 * 
 * @param string $itemUrl Menu item URL
 * @param string $currentPage Current page filename
 * @return bool True if menu item is active
 */
function isMenuActive(string $itemUrl, string $currentPage): bool {
    $itemFile = basename($itemUrl);
    return $itemFile === $currentPage;
}

/**
 * Get role display name
 * 
 * @param string $role Role code
 * @return string Human-readable role name
 */
function getRoleDisplayName(string $role): string {
    $roleNames = [
        ROLE_ADMIN => 'Administrator',
        ROLE_TEACHER => 'Teacher',
        ROLE_STAFF => 'Staff',
        ROLE_STUDENT => 'Student'
    ];
    
    return $roleNames[$role] ?? ucfirst($role);
}

/**
 * Get role badge HTML
 * 
 * @param string $role Role code
 * @return string HTML badge element
 */
function getRoleBadge(string $role): string {
    $colors = [
        ROLE_ADMIN => 'danger',
        ROLE_TEACHER => 'primary',
        ROLE_STAFF => 'info',
        ROLE_STUDENT => 'success'
    ];
    
    $color = $colors[$role] ?? 'secondary';
    $name = getRoleDisplayName($role);
    
    return "<span class=\"badge badge-{$color}\">{$name}</span>";
}

/**
 * Log access attempt for security auditing
 * 
 * @param string $action Action attempted
 * @param bool $success Whether access was granted
 * @param string $details Additional details
 */
function logAccessAttempt(string $action, bool $success, string $details = ''): void {
    global $pdo;
    
    if (!$pdo) {
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_id, username, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_SESSION['admin_id'] ?? null,
            $_SESSION['admin_username'] ?? 'guest',
            ($success ? 'ACCESS_GRANTED' : 'ACCESS_DENIED') . ': ' . $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log access attempt: " . $e->getMessage());
    }
}

/**
 * Validate session and refresh if needed
 * 
 * @return bool True if session is valid
 */
function validateSession(): bool {
    // Check if session exists
    if (!isAuthenticated()) {
        return false;
    }
    
    // Check session timeout
    $timeout = defined('ADMIN_TIMEOUT') ? ADMIN_TIMEOUT : 3600;
    
    if (isset($_SESSION['admin_last_activity'])) {
        $inactive = time() - $_SESSION['admin_last_activity'];
        if ($inactive > $timeout) {
            // Session expired
            session_destroy();
            return false;
        }
    }
    
    // Update last activity
    $_SESSION['admin_last_activity'] = time();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['session_created'])) {
        $_SESSION['session_created'] = time();
    } elseif (time() - $_SESSION['session_created'] > 1800) {
        // Regenerate session ID every 30 minutes
        session_regenerate_id(true);
        $_SESSION['session_created'] = time();
    }
    
    return true;
}
