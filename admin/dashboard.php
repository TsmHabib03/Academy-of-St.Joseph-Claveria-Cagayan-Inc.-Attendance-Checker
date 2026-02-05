<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Dashboard';
$pageIcon = 'home';

/**
 * Fetch all dashboard data in optimized queries
 * Returns comprehensive dashboard statistics
 */
function getDashboardData($pdo) {
    $data = [];
    
    // Common exclusion clause
    $exclusionClause = " AND (class NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6') AND class NOT LIKE 'Kinder%') ";
    $studentExclusionClause = " JOIN students s ON a.lrn = s.lrn WHERE (s.class NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6') AND s.class NOT LIKE 'Kinder%') ";
    
    try {
        // 1. STAT CARDS DATA
        // Total students
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE 1=1" . $exclusionClause);
        $data['totalStudents'] = (int)$stmt->fetch()['total'];
        
        // Today's attendance
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT a.lrn) as present
                FROM attendance a
                JOIN students s ON a.lrn = s.lrn
                WHERE a.date = CURDATE() AND (
                    a.morning_time_in IS NOT NULL OR a.afternoon_time_in IS NOT NULL OR a.time_in IS NOT NULL
                )
                AND (s.class NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6') AND s.class NOT LIKE 'Kinder%')
            ");
        $stmt->execute();
        $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['presentToday'] = (int)$todayStats['present'];
        
        // Absent students today
        $data['absentToday'] = $data['totalStudents'] - $data['presentToday'];
        
        // Today's attendance rate
        $data['attendanceRate'] = $data['totalStudents'] > 0 
            ? round(($data['presentToday'] / $data['totalStudents']) * 100, 1) 
            : 0;
        
        // 2. WEEKLY ATTENDANCE TREND (Last 7 days - Present vs Absent)
        $stmt = $pdo->prepare("
            WITH RECURSIVE dates AS (
                SELECT DATE_SUB(CURDATE(), INTERVAL 6 DAY) as date
                UNION ALL
                SELECT DATE_ADD(date, INTERVAL 1 DAY)
                FROM dates
                WHERE date < CURDATE()
            )
            SELECT 
                dates.date,
                COALESCE(COUNT(DISTINCT CASE WHEN (s.class NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6') AND s.class NOT LIKE 'Kinder%') AND (a.morning_time_in IS NOT NULL OR a.afternoon_time_in IS NOT NULL OR a.time_in IS NOT NULL) THEN a.lrn END), 0) as present,
                (SELECT COUNT(*) FROM students WHERE 1=1 " . $exclusionClause . ") - COALESCE(COUNT(DISTINCT CASE WHEN (s.class NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6') AND s.class NOT LIKE 'Kinder%') AND (a.morning_time_in IS NOT NULL OR a.afternoon_time_in IS NOT NULL OR a.time_in IS NOT NULL) THEN a.lrn END), 0) as absent
            FROM dates
            LEFT JOIN attendance a ON dates.date = a.date
            LEFT JOIN students s ON a.lrn = s.lrn
            GROUP BY dates.date
            ORDER BY dates.date ASC
        ");
        $stmt->execute();
        $data['weeklyTrend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. ATTENDANCE BY SECTION (Today)
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(s.section, 'No Section') as section,
                COUNT(DISTINCT CASE WHEN a.date = CURDATE() AND (a.morning_time_in IS NOT NULL OR a.afternoon_time_in IS NOT NULL OR a.time_in IS NOT NULL) THEN a.lrn END) as present,
                COUNT(DISTINCT s.lrn) as total
            FROM students s
            LEFT JOIN attendance a ON s.lrn = a.lrn
            WHERE 1=1 " . str_replace('class', 's.class', $exclusionClause) . "
            GROUP BY s.section
            HAVING total > 0
            ORDER BY section
        ");
        $stmt->execute();
        $data['sectionAttendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. RECENT ACTIVITY (Last 10 records with time_out info)
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.lrn,
                s.first_name,
                s.last_name,
                COALESCE(s.section, 'N/A') as section,
                COALESCE(a.morning_time_in, a.afternoon_time_in, a.time_in) AS time_in,
                COALESCE(a.morning_time_out, a.afternoon_time_out, a.time_out) AS time_out,
                a.date,
                CASE 
                    WHEN a.time_out IS NULL AND a.date < CURDATE() THEN 'incomplete'
                    WHEN a.time_out IS NOT NULL THEN 'complete'
                    ELSE 'present'
                END as status,
                a.created_at
            FROM attendance a
            JOIN students s ON a.lrn = s.lrn
            WHERE 1=1 " . str_replace('class', 's.class', $exclusionClause) . "
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $data['recentActivity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 5. NEEDS ATTENTION (Incomplete attendance records)
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.lrn,
                s.first_name,
                s.last_name,
                COALESCE(s.section, 'N/A') as section,
                a.date,
                COALESCE(a.morning_time_in, a.afternoon_time_in, a.time_in) AS time_in,
                DATEDIFF(CURDATE(), a.date) as days_ago
            FROM attendance a
            JOIN students s ON a.lrn = s.lrn
            WHERE a.time_out IS NULL 
            AND a.date < CURDATE()
            " . str_replace('class', 's.class', $exclusionClause) . "
            ORDER BY a.date DESC
            LIMIT 15
        ");
        $stmt->execute();
        $data['needsAttention'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 6. ADDITIONAL STATS
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM attendance a JOIN students s ON a.lrn = s.lrn WHERE 1=1 " . str_replace('class', 's.class', $exclusionClause));
        $data['totalRecords'] = (int)$stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT COALESCE(section, 'default')) as total FROM students WHERE 1=1 " . $exclusionClause);
        $data['activeSections'] = (int)$stmt->fetch()['total'];
        
        return $data;
        
    } catch (Exception $e) {
        error_log("Dashboard data fetch error: " . $e->getMessage());
        // Return safe defaults
        return [
            'totalStudents' => 0,
            'presentToday' => 0,
            'absentToday' => 0,
            'attendanceRate' => 0,
            'weeklyTrend' => [],
            'sectionAttendance' => [],
            'recentActivity' => [],
            'needsAttention' => [],
            'totalRecords' => 0,
            'activeSections' => 0
        ];
    }
}

// Fetch all dashboard data
$dashboardData = getDashboardData($pdo);

// Extract for easy access
$totalStudents = $dashboardData['totalStudents'];
$presentToday = $dashboardData['presentToday'];
$absentToday = $dashboardData['absentToday'];
$attendanceRate = $dashboardData['attendanceRate'];
$totalRecords = $dashboardData['totalRecords'];
$activeSections = $dashboardData['activeSections'];
$recentAttendance = $dashboardData['recentActivity'];

// Include the modern admin header
include 'includes/header_modern.php';
?>

<!-- ASJ Modern Dashboard Theme -->
<link rel="stylesheet" href="../css/asj-dashboard-modern.css">

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Loading Overlay -->
<div id="dashboardLoader" class="dashboard-loader">
    <div class="loader-content">
        <div class="spinner"></div>
        <p>Loading Dashboard...</p>
    </div>
</div>

<!-- Dashboard Data (JSON) -->
<script>
    window.dashboardData = <?php echo json_encode($dashboardData); ?>;
</script>

<!-- Stats Cards -->
<div class="stats-grid">
    <!-- Total Students -->
    <div class="stat-card stat-card-primary">
        <div class="stat-card-header">
            <div class="stat-card-icon">
                <i class="fas fa-users"></i>
            </div>
            <span class="stat-card-label">Total Students</span>
        </div>
        <div class="stat-card-value"><?php echo number_format($totalStudents); ?></div>
        <div class="stat-card-footer">
            <span><?php echo $activeSections; ?> sections</span>
        </div>
    </div>

    <!-- Present Today -->
    <div class="stat-card stat-card-success">
        <div class="stat-card-header">
            <div class="stat-card-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <span class="stat-card-label">Present Today</span>
        </div>
        <div class="stat-card-value"><?php echo number_format($presentToday); ?></div>
        <div class="stat-card-footer">
            <span><i class="fas fa-arrow-up"></i> <?php echo $attendanceRate; ?>% rate</span>
        </div>
    </div>

    <!-- Absent Today -->
    <div class="stat-card stat-card-error">
        <div class="stat-card-header">
            <div class="stat-card-icon">
                <i class="fas fa-user-xmark"></i>
            </div>
            <span class="stat-card-label">Absent Today</span>
        </div>
        <div class="stat-card-value"><?php echo number_format($absentToday); ?></div>
        <div class="stat-card-footer">
            <span><?php echo number_format(100 - $attendanceRate, 1); ?>% of total</span>
        </div>
    </div>

    <!-- Total Records -->
    <div class="stat-card stat-card-info">
        <div class="stat-card-header">
            <div class="stat-card-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <span class="stat-card-label">Total Records</span>
        </div>
        <div class="stat-card-value"><?php echo number_format($totalRecords); ?></div>
        <div class="stat-card-footer">
            <span>All time attendance</span>
        </div>
    </div>
</div>

<!-- Dashboard Grid -->
<div class="dashboard-grid">
    <!-- Left Column -->
    <div style="display: flex; flex-direction: column; gap: var(--space-6);">
        <!-- Weekly Attendance Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> Weekly Attendance Trend</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clock-rotate-left"></i> Recent Attendance</h3>
                <a href="attendance_reports_sections.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (!empty($recentAttendance)): ?>
                    <?php foreach ($recentAttendance as $record): ?>
                        <div class="recent-item">
                            <div class="recent-student-info">
                                <div class="recent-avatar">
                                    <?php echo strtoupper(substr($record['first_name'], 0, 1)); ?>
                                </div>
                                <div class="recent-details">
                                    <h4><?php echo sanitizeOutput($record['first_name'] . ' ' . $record['last_name']); ?></h4>
                                    <p><?php echo sanitizeOutput($record['section']); ?> • In: <?php echo date('g:i A', strtotime($record['time_in'])); ?><?php echo $record['time_out'] ? ' • Out: ' . date('g:i A', strtotime($record['time_out'])) : ''; ?></p>
                                </div>
                            </div>
                            <span class="badge badge-<?php echo $record['status'] === 'incomplete' ? 'warning' : ($record['status'] === 'complete' ? 'success' : 'primary'); ?>">
                                <?php echo $record['status'] === 'complete' ? 'Complete' : ($record['status'] === 'incomplete' ? 'Incomplete' : 'Present'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: var(--space-8); text-align: center; color: var(--gray-500);">
                        <i class="fas fa-inbox" style="font-size: var(--text-4xl); margin-bottom: var(--space-4);"></i>
                        <p>No attendance records yet today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div style="display: flex; flex-direction: column; gap: var(--space-6);">
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions-grid">
                    <a href="manage_students.php" class="quick-action-card">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Student</span>
                    </a>
                    <a href="manual_attendance.php" class="quick-action-card">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Manual Entry</span>
                    </a>
                    <a href="../scan_attendance.php" class="quick-action-card" target="_blank">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Scanner</span>
                    </a>
                    <a href="attendance_reports_sections.php" class="quick-action-card">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Section-wise Attendance -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie"></i> Today's Attendance by Section</h3>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 280px;">
                    <canvas id="sectionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Needs Attention -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-triangle" style="color: var(--red-500);"></i> 
                    Needs Attention
                </h3>
                <a href="manual_attendance.php" class="btn btn-sm btn-outline">Fix Records</a>
            </div>
            <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;">
                <div id="needsAttentionList">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle"></i> System Information</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                    <div style="display: flex; justify-content: space-between; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-lg);">
                        <span style="color: var(--gray-600); font-size: var(--text-sm);">Total Records</span>
                        <strong style="color: var(--gray-900);"><?php echo number_format($totalRecords); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-lg);">
                        <span style="color: var(--gray-600); font-size: var(--text-sm);">Active Sections</span>
                        <strong style="color: var(--gray-900);"><?php echo $activeSections; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-lg);">
                        <span style="color: var(--gray-600); font-size: var(--text-sm);">Last Updated</span>
                        <strong style="color: var(--gray-900);"><?php echo date('g:i A'); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * AttendEase Dashboard - Interactive Data Visualization
 * Fully functional dashboard with real-time data and Chart.js integration
 */

(function() {
    'use strict';
    
    // Get dashboard data
    const data = window.dashboardData;
    
    if (!data) {
        console.error('Dashboard data not available');
        return;
    }
    
    // Chart instances
    let weeklyChart = null;
    let sectionChart = null;
    
    /**
     * Initialize Weekly Attendance Trend Chart
     * Bar chart showing Present vs Absent for last 7 days
     */
    function initWeeklyChart() {
        const ctx = document.getElementById('weeklyChart');
        if (!ctx) return;
        
        const weeklyData = data.weeklyTrend || [];
        
        // Prepare data
        const labels = weeklyData.map(day => {
            const date = new Date(day.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const presentData = weeklyData.map(day => parseInt(day.present) || 0);
        const absentData = weeklyData.map(day => parseInt(day.absent) || 0);
        
        // ASJ Color Palette
        const asjColors = {
            green: {
                primary: '#4CAF50',
                light: 'rgba(76, 175, 80, 0.8)',
                lighter: 'rgba(76, 175, 80, 0.5)'
            },
            red: {
                primary: '#EF4444',
                light: 'rgba(239, 68, 68, 0.8)',
                lighter: 'rgba(239, 68, 68, 0.5)'
            }
        };
        
        // Create chart
        weeklyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Present',
                        data: presentData,
                        backgroundColor: asjColors.green.light,
                        borderColor: asjColors.green.primary,
                        borderWidth: 2,
                        borderRadius: 6,
                        borderSkipped: false
                    },
                    {
                        label: 'Absent',
                        data: absentData,
                        backgroundColor: asjColors.red.light,
                        borderColor: asjColors.red.primary,
                        borderWidth: 2,
                        borderRadius: 6,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            boxWidth: 12,
                            boxHeight: 12,
                            padding: 15,
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 13,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        },
                        bodySpacing: 6,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            title: function(context) {
                                return context[0].label || '';
                            },
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.parsed.y || 0;
                                const total = presentData[context.dataIndex] + absentData[context.dataIndex];
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11,
                                weight: '500'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Initialize Section Attendance Chart
     * Donut chart showing today's attendance by section
     */
    function initSectionChart() {
        const ctx = document.getElementById('sectionChart');
        if (!ctx) return;
        
        const sectionData = data.sectionAttendance || [];
        
        // Prepare data - only show sections with present students today
        const labels = sectionData.map(s => s.section || 'Unknown');
        const presentData = sectionData.map(s => parseInt(s.present) || 0);
        const totalData = sectionData.map(s => parseInt(s.total) || 0);
        
        // ASJ Color Palette for sections - vibrant greens, golds, and complementary colors
        const asjSectionColors = [
            'rgba(76, 175, 80, 0.8)',    // ASJ Green Primary
            'rgba(255, 193, 7, 0.8)',    // ASJ Gold
            'rgba(102, 187, 106, 0.8)',  // ASJ Green Medium
            'rgba(255, 179, 0, 0.8)',    // Gold Dark
            'rgba(16, 185, 129, 0.8)',   // Success Green
            'rgba(255, 224, 130, 0.8)',  // Light Gold
            'rgba(46, 125, 50, 0.8)',    // ASJ Green Dark
            'rgba(255, 213, 79, 0.8)',   // Amber
            'rgba(129, 199, 132, 0.8)',  // Light Green
            'rgba(255, 204, 128, 0.8)'   // Peach Gold
        ];
        
        const borderColors = asjSectionColors.map(c => c.replace('0.8', '1'));
        
        // Create chart
        sectionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: presentData,
                    backgroundColor: asjSectionColors,
                    borderColor: borderColors,
                    borderWidth: 2,
                    hoverOffset: 10,
                    spacing: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            boxHeight: 12,
                            padding: 12,
                            font: {
                                size: 11,
                                weight: '600'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle',
                            generateLabels: function(chart) {
                                const data = chart.data;
                                return data.labels.map((label, i) => {
                                    const value = data.datasets[0].data[i];
                                    const total = totalData[i];
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(0) : 0;
                                    return {
                                        text: `${label}: ${value}/${total} (${percentage}%)`,
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        strokeStyle: data.datasets[0].borderColor[i],
                                        lineWidth: 2,
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                        },
                        onClick: function(e, legendItem, legend) {
                            const index = legendItem.index;
                            const chart = legend.chart;
                            const meta = chart.getDatasetMeta(0);
                            
                            // Toggle visibility
                            meta.data[index].hidden = !meta.data[index].hidden;
                            chart.update();
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 13,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        },
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = totalData[context.dataIndex] || 0;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                const absent = total - value;
                                return [
                                    `Present: ${value} students`,
                                    `Absent: ${absent} students`,
                                    `Rate: ${percentage}%`
                                ];
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Populate Recent Activity List
     */
    function populateRecentActivity() {
        // Already populated by PHP, but we can add animations
        const items = document.querySelectorAll('.recent-item');
        items.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, index * 50);
        });
    }
    
    /**
     * Populate Needs Attention List
     */
    function populateNeedsAttention() {
        const container = document.getElementById('needsAttentionList');
        if (!container) return;
        
        const needsAttention = data.needsAttention || [];
        
        if (needsAttention.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>All attendance records are complete!</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        needsAttention.forEach(record => {
            const daysAgo = parseInt(record.days_ago) || 0;
            const daysText = daysAgo === 1 ? '1 day ago' : `${daysAgo} days ago`;
            const date = new Date(record.date);
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const timeIn = record.time_in ? new Date(`2000-01-01 ${record.time_in}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : 'N/A';
            
            html += `
                <div class="attention-item">
                    <div class="attention-info">
                        <div class="attention-icon">
                            <i class="fas fa-exclamation"></i>
                        </div>
                        <div class="attention-details">
                            <h4>${escapeHtml(record.first_name)} ${escapeHtml(record.last_name)}</h4>
                            <p>${escapeHtml(record.section)} • ${dateStr} • In: ${timeIn} • Missing Time Out</p>
                        </div>
                    </div>
                    <span class="badge badge-error">${daysText}</span>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        // Animate items
        const items = container.querySelectorAll('.attention-item');
        items.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(20px)';
            setTimeout(() => {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, index * 50);
        });
    }
    
    /**
     * Utility: Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Hide loading overlay
     */
    function hideLoader() {
        const loader = document.getElementById('dashboardLoader');
        if (loader) {
            setTimeout(() => {
                loader.classList.add('hidden');
            }, 500);
        }
    }
    
    /**
     * Initialize Dashboard
     */
    function init() {
        console.log('Initializing dashboard with data:', data);
        
        // Initialize charts
        initWeeklyChart();
        initSectionChart();
        
        // Populate lists
        populateRecentActivity();
        populateNeedsAttention();
        
        // Hide loader
        hideLoader();
        
        console.log('Dashboard initialization complete');
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Auto-refresh dashboard every 5 minutes
    setInterval(() => {
        console.log('Auto-refreshing dashboard...');
        window.location.reload();
    }, 5 * 60 * 1000);
    
})();
</script>

<?php include 'includes/footer_modern.php'; ?>
