<?php
// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Get current page to set active class
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

/**
 * Check if a navigation item should be active
 */
function isActive($page, $currentPage, $currentDir = '') {
    if ($currentDir === 'admin' && strpos($page, 'admin/') === 0) {
        return basename($page) === $currentPage ? 'active' : '';
    }
    return $page === $currentPage ? 'active' : '';
}
?>

<nav class="navigation">
    <ul>
        <?php if ($currentDir !== 'admin'): // Public navigation ?>
            <li><a href="index.php" class="<?= isActive('index.php', $currentPage) ?>">
                <i class="fas fa-home"></i> Home
            </a></li>
            <li><a href="scan_attendance.php" class="<?= isActive('scan_attendance.php', $currentPage) ?>">
                <i class="fas fa-qrcode"></i> Scan Attendance
            </a></li>
            <li><a href="admin/login.php" class="admin-link">
                <i class="fas fa-shield-alt"></i> Admin Portal
            </a></li>
        <?php else: // Admin navigation ?>
            <?php if ($isAdmin): ?>
                <li><a href="dashboard.php" class="<?= isActive('dashboard.php', $currentPage) ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="manage_students.php" class="<?= isActive('manage_students.php', $currentPage) ?>">
                    <i class="fas fa-users"></i> Manage Students
                </a></li>
                <li><a href="manage_schedules.php" class="<?= isActive('manage_schedules.php', $currentPage) ?>">
                    <i class="fas fa-calendar-alt"></i> Manage Schedules
                </a></li>
                <li><a href="manual_attendance.php" class="<?= isActive('manual_attendance.php', $currentPage) ?>">
                    <i class="fas fa-clipboard-check"></i> Manual Attendance
                </a></li>
                <li><a href="../view_students.php" target="_blank">
                    <i class="fas fa-eye"></i> View Students
                </a></li>
                <li><a href="admin/attendance_reports.php" target="_blank">
                    <i class="fas fa-chart-bar"></i> Attendance Reports
                </a></li>
                <li><a href="../index.php" class="public-link">
                    <i class="fas fa-globe"></i> Public Site
                </a></li>
                <li><a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            <?php endif; ?>
        <?php endif; ?>
    </ul>
</nav>
