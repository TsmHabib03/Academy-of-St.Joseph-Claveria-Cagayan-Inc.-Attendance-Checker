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
    <meta name="theme-color" content="#4CAF50">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin - ASJ AttendEase</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
        /* ============================================================================
           ASJ MODERN ADMIN LAYOUT STYLES
           ============================================================================ */
        
        :root {
            /* ASJ Brand Colors */
            --asj-green-50: #E8F5E9;
            --asj-green-100: #C8E6C9;
            --asj-green-500: #4CAF50;
            --asj-green-600: #43A047;
            --asj-green-700: #388E3C;
            --asj-gold-500: #FFC107;
            --asj-gold-50: #FFF9E6;
            
            /* Modern Neutrals */
            --neutral-50: #FAFBFC;
            --neutral-100: #F4F6F8;
            --neutral-200: #E5E9ED;
            --neutral-500: #6E7C8C;
            --neutral-700: #3E4C59;
            --neutral-900: #1F2937;
            
            /* Shadows */
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07), 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.12), 0 10px 10px rgba(0, 0, 0, 0.04);
            
            /* Spacing */
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-6: 1.5rem;
            
            /* Radius */
            --radius-lg: 14px;
            --radius-full: 9999px;
            
            /* Typography */
            --font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --text-xs: 0.75rem;
            --text-sm: 0.875rem;
            --text-base: 1rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            
            /* Transitions */
            --transition-base: 200ms ease-in-out;
            --transition-slow: 300ms ease-in-out;
            
            /* Legacy compatibility */
            --primary-50: #E8F5E9;
            --primary-500: #4CAF50;
            --primary-600: #43A047;
            --gray-50: #FAFBFC;
            --gray-100: #F4F6F8;
            --gray-200: #E5E9ED;
            --gray-500: #6E7C8C;
            --gray-700: #3E4C59;
            --gray-900: #1F2937;
        }
        
        /* Admin Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: var(--neutral-50);
            font-family: var(--font-family);
        }

        /* Desktop Sidebar - ASJ Branded */
        .desktop-sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--neutral-200);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            display: none;
            flex-direction: column;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .desktop-sidebar-header {
            padding: var(--space-6);
            border-bottom: 2px solid var(--asj-green-50);
            background: linear-gradient(135deg, var(--asj-green-50), white);
        }

        .desktop-sidebar-logo {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--asj-green-600);
        }

        .desktop-sidebar-logo i {
            font-size: var(--text-2xl);
            color: var(--asj-green-500);
        }

        .desktop-sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: var(--space-4);
        }

        .nav-section-title {
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--neutral-500);
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
            color: var(--neutral-700);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-base);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .nav-link:hover {
            background: var(--asj-green-50);
            color: var(--asj-green-700);
            transform: translateX(4px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-link.active i {
            color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            color: var(--asj-green-500);
            transition: color var(--transition-base);
        }

        .desktop-sidebar-footer {
            padding: var(--space-4);
            border-top: 1px solid var(--neutral-200);
            background: var(--neutral-50);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3);
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--neutral-200);
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: var(--text-lg);
            box-shadow: var(--shadow-sm);
        }

        .admin-info {
            flex: 1;
        }

        .admin-name {
            display: block;
            font-weight: 600;
            color: var(--neutral-900);
            font-size: var(--text-sm);
        }

        .admin-role {
            display: block;
            font-size: var(--text-xs);
            color: var(--neutral-500);
            text-transform: capitalize;
        }

        /* Main Content Area */
        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* Mobile Top Bar - ASJ Branded */
        .mobile-topbar {
            background: white;
            border-bottom: 2px solid var(--asj-green-50);
            padding: var(--space-4);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 90;
            box-shadow: var(--shadow-sm);
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
            background: var(--asj-green-50);
            color: var(--asj-green-600);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
        }

        .menu-toggle-btn:hover {
            background: var(--asj-green-500);
            color: white;
            transform: scale(1.05);
        }

        .topbar-title {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--neutral-900);
        }

        .topbar-title i {
            color: var(--asj-green-500);
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
            
            .topbar-title {
                font-size: var(--text-base);
            }
        }

        /* Mobile Menu - ASJ Branded */
        .mobile-menu-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
            backdrop-filter: blur(4px);
        }

        .mobile-menu-backdrop.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
            border-bottom: 2px solid var(--asj-green-50);
            background: linear-gradient(135deg, var(--asj-green-50), var(--asj-green-100));
        }

        .mobile-menu-header h3 {
            font-size: var(--text-xl);
            color: var(--asj-green-700);
            margin: 0;
            font-weight: 700;
        }

        .mobile-menu-header h3 i {
            color: var(--asj-green-500);
        }

        .mobile-menu-nav {
            padding: var(--space-4) 0;
        }

        .mobile-menu-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-6);
            color: var(--neutral-700);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-base);
            border-left: 3px solid transparent;
        }

        .mobile-menu-item:hover {
            background: var(--asj-green-50);
            color: var(--asj-green-700);
            border-left-color: var(--asj-green-500);
        }

        .mobile-menu-item.active {
            background: linear-gradient(90deg, var(--asj-green-50), white);
            color: var(--asj-green-600);
            border-left-color: var(--asj-green-500);
            font-weight: 600;
        }

        .mobile-menu-item i {
            width: 20px;
            text-align: center;
            color: var(--asj-green-500);
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
                <a href="students_directory.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'students_directory.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Students Directory
                </a>
                <a href="manage_students.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_students.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-edit"></i> Manage Students
                </a>
                <a href="manage_teachers.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_teachers.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i> Teachers
                </a>
                <a href="manage_sections.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_sections.php') ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> Sections
                </a>
                
                <div class="nav-section-title">Attendance</div>
                <a href="manual_attendance.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manual_attendance.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i> Manual Entry
                </a>
                <a href="manage_schedules.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_schedules.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Schedules
                </a>
                <a href="attendance_reports_sections.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_reports_sections.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                
                <div class="nav-section-title">Monitoring</div>
                <a href="behavior_monitoring.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'behavior_monitoring.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i> Behavior Alerts
                </a>
                <a href="manage_badges.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_badges.php') ? 'active' : ''; ?>">
                    <i class="fas fa-award"></i> Badges
                </a>
                <a href="sms_logs.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'sms_logs.php') ? 'active' : ''; ?>">
                    <i class="fas fa-sms"></i> SMS Logs
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
