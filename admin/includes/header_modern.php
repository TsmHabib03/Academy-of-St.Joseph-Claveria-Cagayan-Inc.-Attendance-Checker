<?php
// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0ea5e9">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin - Attendance System</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Modern Design CSS -->
    <link rel="stylesheet" href="../css/modern-design.css">
    
    <!-- Chart.js for dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        /* Admin-specific styles */
        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: var(--gray-50);
        }

        /* Desktop Sidebar */
        .desktop-sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--gray-200);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            display: none;
            flex-direction: column;
            z-index: 100;
        }

        .desktop-sidebar-header {
            padding: var(--space-6);
            border-bottom: 1px solid var(--gray-200);
        }

        .desktop-sidebar-logo {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--primary-600);
        }

        .desktop-sidebar-logo i {
            font-size: var(--text-2xl);
        }

        .desktop-sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: var(--space-4);
        }

        .nav-section-title {
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: var(--space-3) var(--space-4);
            margin-top: var(--space-4);
        }

        .nav-section-title:first-child {
            margin-top: 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-base);
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--gray-100);
            color: var(--primary-600);
        }

        .nav-link.active {
            background: var(--primary-50);
            color: var(--primary-600);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .desktop-sidebar-footer {
            padding: var(--space-4);
            border-top: 1px solid var(--gray-200);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3);
            background: var(--gray-50);
            border-radius: var(--radius-lg);
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: var(--text-lg);
        }

        .admin-info {
            flex: 1;
        }

        .admin-name {
            display: block;
            font-weight: 600;
            color: var(--gray-900);
            font-size: var(--text-sm);
        }

        .admin-role {
            display: block;
            font-size: var(--text-xs);
            color: var(--gray-500);
        }

        /* Main Content Area */
        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* Mobile Top Bar */
        .mobile-topbar {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: var(--space-4);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .menu-toggle-btn {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-lg);
            border: none;
            background: var(--gray-100);
            color: var(--gray-700);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
        }

        .menu-toggle-btn:hover {
            background: var(--gray-200);
        }

        .topbar-title {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--gray-900);
        }

        .topbar-title i {
            color: var(--primary-600);
            margin-right: var(--space-2);
        }

        /* Content Area */
        .content-wrapper {
            flex: 1;
            padding: var(--space-6) var(--space-4);
            overflow-y: auto;
        }

        /* Desktop Layout */
        @media (min-width: 1024px) {
            .desktop-sidebar {
                display: flex;
            }

            .admin-main {
                margin-left: 260px;
            }

            .menu-toggle-btn {
                display: none;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: var(--space-4) var(--space-3);
            }
        }

        /* Mobile Menu */
        .mobile-menu-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
            backdrop-filter: blur(2px);
        }

        .mobile-menu-backdrop.active {
            display: block;
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            left: -100%;
            bottom: 0;
            width: 280px;
            max-width: 85vw;
            background: white;
            z-index: 999;
            transition: left var(--transition-slow);
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
        }

        .mobile-menu.active {
            left: 0;
        }

        .mobile-menu-header {
            padding: var(--space-6);
            border-bottom: 1px solid var(--gray-200);
            background: linear-gradient(135deg, var(--primary-50), var(--primary-100));
        }

        .mobile-menu-nav {
            padding: var(--space-4) 0;
        }

        .mobile-menu-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-6);
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-base);
            border-left: 3px solid transparent;
        }

        .mobile-menu-item:hover {
            background: var(--gray-50);
            color: var(--primary-600);
        }

        .mobile-menu-item.active {
            background: var(--primary-50);
            color: var(--primary-600);
            border-left-color: var(--primary-600);
        }

        .mobile-menu-item i {
            width: 20px;
            text-align: center;
        }

        @media (min-width: 1024px) {
            .mobile-menu-backdrop,
            .mobile-menu {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Desktop Sidebar -->
        <aside class="desktop-sidebar">
            <div class="desktop-sidebar-header">
                <div class="desktop-sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <span>Admin Panel</span>
                </div>
            </div>
            
            <nav class="desktop-sidebar-nav">
                <div class="nav-section-title">Main</div>
                <a href="dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                
                <div class="nav-section-title">Management</div>
                <a href="view_students.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['view_students.php', 'manage_students.php'])) ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Students
                </a>
                <a href="manage_sections.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_sections.php') ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> Sections
                </a>
                
                <div class="nav-section-title">Attendance</div>
                <a href="manual_attendance.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manual_attendance.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i> Manual Entry
                </a>
                <a href="attendance_reports_sections.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_reports_sections.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                
                <div class="nav-section-title">Quick Actions</div>
                <a href="../scan_attendance.php" class="nav-link" target="_blank">
                    <i class="fas fa-qrcode"></i> QR Scanner
                </a>
                <a href="../index.php" class="nav-link" target="_blank">
                    <i class="fas fa-globe"></i> View Site
                </a>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
            
            <div class="desktop-sidebar-footer">
                <div class="admin-profile">
                    <div class="admin-avatar">
                        <?php echo isset($currentAdmin) ? strtoupper(substr($currentAdmin['username'], 0, 1)) : 'A'; ?>
                    </div>
                    <div class="admin-info">
                        <span class="admin-name"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['username']) : 'Admin'; ?></span>
                        <span class="admin-role"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['role']) : 'Administrator'; ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Mobile Top Bar -->
            <div class="mobile-topbar">
                <div class="topbar-left">
                    <button class="menu-toggle-btn" onclick="toggleAdminMenu()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="topbar-title">
                        <i class="fas fa-<?php echo isset($pageIcon) ? $pageIcon : 'home'; ?>"></i>
                        <?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?>
                    </h1>
                </div>
                <div class="admin-avatar" style="width: 36px; height: 36px; font-size: var(--text-base);">
                    <?php echo isset($currentAdmin) ? strtoupper(substr($currentAdmin['username'], 0, 1)) : 'A'; ?>
                </div>
            </div>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
