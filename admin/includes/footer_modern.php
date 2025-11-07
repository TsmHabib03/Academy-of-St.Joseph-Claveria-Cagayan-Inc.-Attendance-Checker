            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div class="mobile-menu-backdrop" id="admin-menu-backdrop" onclick="toggleAdminMenu()"></div>
    <div class="mobile-menu" id="admin-mobile-menu">
        <div class="mobile-menu-header">
            <h3 style="font-size: var(--text-xl); color: var(--asj-green-700); margin: 0; font-weight: 700;">
                <i class="fas fa-shield-alt" style="color: var(--asj-green-500);"></i> Admin Menu
            </h3>
        </div>
        <nav class="mobile-menu-nav">
            <div style="padding: var(--space-3) var(--space-4); font-size: var(--text-xs); font-weight: 600; color: var(--neutral-500); text-transform: uppercase; letter-spacing: 0.05em;">Main</div>
            <a href="dashboard.php" class="mobile-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <div style="padding: var(--space-3) var(--space-4); font-size: var(--text-xs); font-weight: 600; color: var(--neutral-500); text-transform: uppercase; letter-spacing: 0.05em;">Management</div>
            <a href="view_students.php" class="mobile-menu-item <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['view_students.php', 'manage_students.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Students
            </a>
            <a href="manage_sections.php" class="mobile-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_sections.php') ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Sections
            </a>
            
            <div style="padding: var(--space-3) var(--space-4); font-size: var(--text-xs); font-weight: 600; color: var(--neutral-500); text-transform: uppercase; letter-spacing: 0.05em;">Attendance</div>
            <a href="manual_attendance.php" class="mobile-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manual_attendance.php') ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check"></i> Manual Entry
            </a>
            <a href="attendance_reports_sections.php" class="mobile-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_reports_sections.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            
            <div style="padding: var(--space-3) var(--space-4); font-size: var(--text-xs); font-weight: 600; color: var(--neutral-500); text-transform: uppercase; letter-spacing: 0.05em;">Quick Actions</div>
            <a href="../scan_attendance.php" class="mobile-menu-item" target="_blank">
                <i class="fas fa-qrcode"></i> QR Scanner
            </a>
            <a href="../index.php" class="mobile-menu-item" target="_blank">
                <i class="fas fa-globe"></i> View Site
            </a>
            <a href="logout.php" class="mobile-menu-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <script>
        function toggleAdminMenu() {
            document.getElementById('admin-mobile-menu').classList.toggle('active');
            document.getElementById('admin-menu-backdrop').classList.toggle('active');
        }
    </script>

    <?php if (isset($additionalScripts)): ?>
        <?php foreach ($additionalScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
