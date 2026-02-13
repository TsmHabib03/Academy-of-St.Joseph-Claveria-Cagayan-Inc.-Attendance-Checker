<?php
/**
 * Behavior Monitoring - AttendEase v3.0
 * Monitor student attendance patterns and behavior alerts
 * 
 * @package AttendEase
 * @version 3.0
 */

require_once 'config.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/behavior_analyzer.php';
require_once __DIR__ . '/../includes/email_notifications.php';

// Allow admin and teacher roles
requireRole([ROLE_ADMIN, ROLE_TEACHER]);

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Behavior Monitoring';
$pageIcon = 'chart-line';

$additionalCSS = ['../css/manual-attendance-modern.css?v=' . time()];
$csrfToken = generateCSRFToken();

// Initialize behavior analyzer
$analyzer = new BehaviorAnalyzer($pdo);

// Handle alert acknowledgment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_alert'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = "Invalid or missing CSRF token.";
    } else {
        $alertId = intval($_POST['alert_id']);
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            $acknowledged = $analyzer->acknowledgeAlert($alertId, $currentAdmin['id'], $notes);
            if (!$acknowledged) {
                throw new Exception('Failed to acknowledge alert.');
            }

            $emailConfig = require __DIR__ . '/../config/email_config.php';
            $emailSent = sendBehaviorAcknowledgementEmail($pdo, $alertId, $currentAdmin, $notes, $emailConfig);

            if ($emailSent) {
                $successMessage = "Alert acknowledged successfully. Email notification sent.";
            } else {
                $successMessage = "Alert acknowledged successfully. Email notification could not be sent.";
            }
        } catch (Exception $e) {
            $errorMessage = "Error acknowledging alert: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'pending';
$filterType = $_GET['type'] ?? '';
$filterSection = $_GET['section'] ?? '';

// Detect schema variants
$hasBehaviorAlerts = tableExists($pdo, 'behavior_alerts');
$hasStudents = tableExists($pdo, 'students');
$hasSections = tableExists($pdo, 'sections');
$hasStudentSectionId = $hasStudents && columnExists($pdo, 'students', 'section_id');
$hasStudentSectionName = $hasStudents && columnExists($pdo, 'students', 'section');
$hasSectionsGrade = $hasSections && columnExists($pdo, 'sections', 'grade_level');

// Fetch sections for filter
try {
    if ($hasSections) {
        $sectionsSql = "SELECT id, section_name";
        if ($hasSectionsGrade) {
            $sectionsSql .= ", grade_level";
        } else {
            $sectionsSql .= ", '' as grade_level";
        }
        $sectionsSql .= " FROM sections ORDER BY ";
        if ($hasSectionsGrade) {
            $sectionsSql .= "grade_level, ";
        }
        $sectionsSql .= "section_name";
        $sectionsStmt = $pdo->query($sectionsSql);
        $sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sections = [];
    }
} catch (Exception $e) {
    $sections = [];
}

// Build alerts query
$alerts = [];
$params = [];
$alertsQuery = '';

if ($hasBehaviorAlerts) {
    $studentJoin = '';
    $studentJoinConditions = [];
    if ($hasStudents && columnExists($pdo, 'students', 'lrn')) {
        $studentJoinConditions[] = "ba.user_id = s.lrn";
    }
    if ($hasStudents && columnExists($pdo, 'students', 'id')) {
        $studentJoinConditions[] = "ba.user_id = s.id";
    }
    if (!empty($studentJoinConditions)) {
        $studentJoin = "LEFT JOIN students s ON ba.user_type = 'student' AND (" . implode(' OR ', $studentJoinConditions) . ")";
    }

    $sectionJoin = '';
    if ($hasSections && $hasStudents) {
        if ($hasStudentSectionId) {
            $sectionJoin = "LEFT JOIN sections sec ON s.section_id = sec.id";
        } elseif ($hasStudentSectionName) {
            $sectionJoin = "LEFT JOIN sections sec ON s.section = sec.section_name";
        }
    }

    $firstNameExpr = ($hasStudents && columnExists($pdo, 'students', 'first_name')) ? 's.first_name' : "''";
    $lastNameExpr = ($hasStudents && columnExists($pdo, 'students', 'last_name')) ? 's.last_name' : "''";
    $studentIdExpr = 'ba.user_id';
    if ($hasStudents && columnExists($pdo, 'students', 'lrn')) {
        $studentIdExpr = 'COALESCE(s.lrn, ba.user_id)';
    } elseif ($hasStudents && columnExists($pdo, 'students', 'id')) {
        $studentIdExpr = 'COALESCE(s.id, ba.user_id)';
    }

    $sectionNameExpr = 'NULL';
    if ($hasStudentSectionId) {
        $sectionNameExpr = 'sec.section_name';
    } elseif ($hasStudentSectionName) {
        $sectionNameExpr = $hasSections ? 'COALESCE(sec.section_name, s.section)' : 's.section';
    }
    $gradeLevelExpr = ($hasSections && $hasSectionsGrade) ? 'sec.grade_level' : 'NULL';

    $alertsQuery = "
        SELECT ba.*, 
               {$firstNameExpr} as first_name,
               {$lastNameExpr} as last_name,
               {$studentIdExpr} as sid,
               {$sectionNameExpr} as section_name,
               {$gradeLevelExpr} as grade_level,
               au.username as acknowledged_by_name
        FROM behavior_alerts ba
        {$studentJoin}
        {$sectionJoin}
        LEFT JOIN admin_users au ON ba.acknowledged_by = au.id
        WHERE 1=1
    ";
}

if ($alertsQuery) {
    if ($filterStatus === 'pending') {
        $alertsQuery .= " AND ba.is_acknowledged = 0";
    } elseif ($filterStatus === 'acknowledged') {
        $alertsQuery .= " AND ba.is_acknowledged = 1";
    }

    if ($filterType) {
        $alertsQuery .= " AND ba.alert_type = ?";
        $params[] = $filterType;
    }

    if ($filterSection && $hasStudents) {
        if ($hasStudentSectionId) {
            $alertsQuery .= " AND s.section_id = ?";
            $params[] = $filterSection;
        } elseif ($hasStudentSectionName) {
            $alertsQuery .= " AND s.section = ?";
            $params[] = $filterSection;
        }
    }

    $alertsQuery .= " ORDER BY ba.created_at DESC LIMIT 100";

    try {
        $stmt = $pdo->prepare($alertsQuery);
        $stmt->execute($params);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $alerts = [];
        error_log("Fetch alerts error: " . $e->getMessage());
    }
}

// Get statistics
$stats = $analyzer->getStatistics();

// Get alert type labels and icons
function getAlertTypeInfo($type) {
    $types = [
        'frequent_lateness' => [
            'label' => 'Frequent Lateness',
            'icon' => 'clock',
            'color' => 'warning'
        ],
        'consecutive_absences' => [
            'label' => 'Consecutive Absences',
            'icon' => 'calendar-times',
            'color' => 'danger'
        ],
        'sudden_absence' => [
            'label' => 'Sudden Absence',
            'icon' => 'user-slash',
            'color' => 'info'
        ],
        'attendance_drop' => [
            'label' => 'Attendance Drop',
            'icon' => 'chart-line',
            'color' => 'info'
        ]
    ];
    
    return $types[$type] ?? ['label' => ucfirst(str_replace('_', ' ', $type)), 'icon' => 'exclamation-circle', 'color' => 'secondary'];
}

// Get severity class
function getSeverityClass($severity) {
    $classes = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'danger',
        'warning' => 'warning',
        'info' => 'info'
    ];
    return $classes[$severity] ?? 'secondary';
}

include 'includes/header_modern.php';
?>

<!-- Page Header - Enhanced Design -->
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
                <div class="breadcrumb-nav">
                    <a href="dashboard.php" class="breadcrumb-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <i class="fas fa-chevron-right breadcrumb-separator"></i>
                    <span class="breadcrumb-current"><?php echo $pageTitle; ?></span>
                </div>
                <h1 class="page-title-enhanced"><?php echo $pageTitle; ?></h1>
                <p class="page-subtitle-enhanced">
                    <i class="fas fa-info-circle"></i>
                    <span>Monitor student attendance patterns and behavior alerts</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <button class="btn-header btn-header-secondary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
            <button class="btn-header btn-header-primary" onclick="runAnalysis()">
                <i class="fas fa-play"></i>
                <span>Run Analysis</span>
            </button>
        </div>
    </div>
</div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success">
        <div class="alert-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="alert-content">
            <?php echo $successMessage; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-error">
        <div class="alert-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="alert-content">
            <?php echo $errorMessage; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Info Alert -->
<div class="alert alert-info">
    <div class="alert-icon">
        <i class="fas fa-info-circle"></i>
    </div>
    <div class="alert-content">
        <strong>Behavior Monitoring System</strong>
        <p style="margin: var(--space-1) 0 0; line-height: 1.6;">
            This system automatically detects attendance patterns like frequent lateness, consecutive absences, and sudden drops in attendance.
            Run the analysis regularly to identify students who may need intervention.
        </p>
    </div>
</div>

<div class="content-wrapper">
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $stats['pending_alerts'] ?? 0; ?></span>
                <span class="stat-label">Pending Alerts</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $stats['frequent_lateness'] ?? 0; ?></span>
                <span class="stat-label">Late Students</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $stats['consecutive_absences'] ?? 0; ?></span>
                <span class="stat-label">Absent Streaks</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $stats['acknowledged_this_month'] ?? 0; ?></span>
                <span class="stat-label">Resolved This Month</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="acknowledged" <?php echo $filterStatus === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                    <option value="" <?php echo $filterStatus === '' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="type">Alert Type</label>
                <select id="type" name="type">
                    <option value="">All Types</option>
                    <option value="frequent_lateness" <?php echo $filterType === 'frequent_lateness' ? 'selected' : ''; ?>>Frequent Lateness</option>
                    <option value="consecutive_absences" <?php echo $filterType === 'consecutive_absences' ? 'selected' : ''; ?>>Consecutive Absences</option>
                    <option value="sudden_absence" <?php echo $filterType === 'sudden_absence' ? 'selected' : ''; ?>>Sudden Absence</option>
                    <option value="attendance_drop" <?php echo $filterType === 'attendance_drop' ? 'selected' : ''; ?>>Attendance Drop</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="section">Section</label>
                <select id="section" name="section">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $sec): ?>
                        <?php
                            $sectionValue = $hasStudentSectionId ? $sec['id'] : $sec['section_name'];
                            $sectionLabel = $sec['section_name'];
                            if (!empty($sec['grade_level'])) {
                                $sectionLabel .= ' (' . $sec['grade_level'] . ')';
                            }
                        ?>
                        <option value="<?php echo htmlspecialchars($sectionValue); ?>" <?php echo $filterSection == $sectionValue ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sectionLabel); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="behavior_monitoring.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Alerts List -->
    <div class="alerts-card">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> Behavior Alerts</h3>
            <span class="badge badge-primary"><?php echo count($alerts); ?> alerts</span>
        </div>
        
        <?php if (empty($alerts)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Alerts Found</h3>
                <p>No behavior alerts match your current filters.</p>
            </div>
        <?php else: ?>
            <div class="alerts-list">
                <?php foreach ($alerts as $alert): 
                    $typeInfo = getAlertTypeInfo($alert['alert_type']);
                    $severityClass = getSeverityClass($alert['severity']);
                    $studentName = trim(($alert['first_name'] ?? '') . ' ' . ($alert['last_name'] ?? ''));
                    if ($studentName === '') {
                        $studentName = 'Unknown Student';
                    }
                    $studentIdDisplay = $alert['sid'] ?? $alert['user_id'] ?? 'N/A';
                    $sectionDisplay = $alert['section_name'] ?? 'N/A';
                ?>
                    <div class="alert-item <?php echo $alert['is_acknowledged'] ? 'acknowledged' : ''; ?>">
                        <div class="alert-icon <?php echo $typeInfo['color']; ?>">
                            <i class="fas fa-<?php echo $typeInfo['icon']; ?>"></i>
                        </div>
                        
                        <div class="alert-content">
                            <div class="alert-header">
                                <h4><?php echo htmlspecialchars($studentName); ?></h4>
                                <div class="alert-badges">
                                    <span class="badge badge-<?php echo $typeInfo['color']; ?>">
                                        <?php echo $typeInfo['label']; ?>
                                    </span>
                                    <span class="badge badge-<?php echo $severityClass; ?>">
                                        <?php echo ucfirst($alert['severity']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="alert-meta">
                                <span><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($studentIdDisplay); ?></span>
                                <span><i class="fas fa-chalkboard"></i> <?php echo htmlspecialchars($sectionDisplay); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y g:i A', strtotime($alert['created_at'])); ?></span>
                            </div>
                            
                            <p class="alert-description"><?php echo htmlspecialchars($alert['alert_message'] ?? ''); ?></p>
                            
                            <?php if ($alert['is_acknowledged']): ?>
                                <div class="alert-resolved">
                                    <i class="fas fa-check-circle"></i>
                                    Acknowledged by <?php echo htmlspecialchars($alert['acknowledged_by_name'] ?? 'Admin'); ?>
                                    on <?php echo date('M d, Y', strtotime($alert['acknowledged_at'])); ?>
                                    <?php if (!empty($alert['notes'])): ?>
                                        <br><em>Notes: <?php echo htmlspecialchars($alert['notes']); ?></em>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$alert['is_acknowledged']): ?>
                            <div class="alert-actions">
                                <button type="button" class="btn btn-success btn-sm" 
                                        onclick="showAcknowledgeModal(<?php echo $alert['id']; ?>, '<?php echo addslashes($studentName); ?>')">
                                    <i class="fas fa-check"></i> Acknowledge
                                </button>
                                <a href="students_directory.php?search=<?php echo urlencode($studentIdDisplay); ?>" 
                                   class="btn btn-info btn-sm">
                                    <i class="fas fa-user"></i> View Student
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Acknowledge Modal -->
<div id="acknowledgeModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="closeAcknowledgeModal()">&times;</span>
        <h2><i class="fas fa-check-circle"></i> Acknowledge Alert</h2>
        <p id="acknowledgeStudent"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="acknowledge_alert" value="1">
            <input type="hidden" name="alert_id" id="acknowledgeAlertId">
            
            <div class="form-group">
                <label for="notes">Notes (Optional)</label>
                <textarea name="notes" id="notes" rows="3" 
                          placeholder="Add any notes about this alert or actions taken..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Acknowledge
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeAcknowledgeModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Analysis Modal -->
<div id="analysisModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="closeAnalysisModal()">&times;</span>
        <h2><i class="fas fa-sync"></i> Running Analysis</h2>
        <div id="analysisContent">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i> Analyzing attendance patterns...
            </div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
}

.stat-icon.orange { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
.stat-icon.blue { background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%); }
.stat-icon.red { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); }
.stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #333;
}

.stat-label {
    font-size: 0.875rem;
    color: #666;
}

.filters-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.filters-form {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #555;
}

.filter-group select {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9375rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.alerts-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.125rem;
    color: #333;
}

.alerts-list {
    padding: 0;
}

.alert-item {
    display: flex;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #eee;
    gap: 1rem;
    align-items: flex-start;
    transition: background-color 0.2s ease;
}

.alert-item:hover {
    background-color: #f8f9fa;
}

.alert-item.acknowledged {
    opacity: 0.7;
    background-color: #f9fff9;
}

.alert-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #fff;
    flex-shrink: 0;
}

.alert-icon.warning { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
.alert-icon.danger { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); }
.alert-icon.info { background: linear-gradient(135deg, var(--asj-green-400) 0%, var(--asj-green-600) 100%); }
.alert-icon.purple { background: linear-gradient(135deg, var(--asj-green-300, #81C784) 0%, var(--asj-green-500) 100%); }

.alert-content {
    flex: 1;
}

.alert-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.alert-header h4 {
    margin: 0;
    font-size: 1rem;
    color: #333;
}

.alert-badges {
    display: flex;
    gap: 0.5rem;
}

.badge {
    padding: 0.25rem 0.625rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-primary { background: var(--asj-green-50); color: var(--asj-green-700); }
.badge-warning { background: #fff8e6; color: #d97706; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.badge-info { background: #e0f2fe; color: #0284c7; }
.badge-purple { background: var(--asj-green-100); color: var(--asj-green-700); }
.badge-success { background: #dcfce7; color: #16a34a; }
.badge-secondary { background: #f3f4f6; color: #6b7280; }

.alert-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8125rem;
    color: #666;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}

.alert-meta i {
    margin-right: 0.25rem;
}

.alert-description {
    margin: 0;
    color: #555;
    font-size: 0.9375rem;
    line-height: 1.5;
}

.alert-resolved {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: #dcfce7;
    border-radius: 8px;
    font-size: 0.8125rem;
    color: #16a34a;
}

.alert-resolved i {
    margin-right: 0.375rem;
}

.alert-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-shrink: 0;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #666;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #999;
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fff;
    padding: 2rem;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    position: relative;
}

.close {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.close:hover {
    color: #333;
}

.modal-content h2 {
    margin-top: 0;
    margin-bottom: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    resize: vertical;
    font-family: inherit;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.loading {
    text-align: center;
    padding: 2rem;
    color: #666;
}

.analysis-results {
    padding: 1rem 0;
}

.analysis-result-item {
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.analysis-result-item.success { background: #dcfce7; color: #16a34a; }
.analysis-result-item.warning { background: #fff8e6; color: #d97706; }
.analysis-result-item.info { background: #e0f2fe; color: #0284c7; }

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filters-form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .alert-item {
        flex-direction: column;
    }
    
    .alert-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .alert-actions {
        flex-direction: row;
        width: 100%;
        margin-top: 1rem;
    }
}
</style>

<script>
function showAcknowledgeModal(alertId, studentName) {
    document.getElementById('acknowledgeAlertId').value = alertId;
    document.getElementById('acknowledgeStudent').textContent = 'Acknowledging alert for: ' + studentName;
    document.getElementById('acknowledgeModal').style.display = 'flex';
}

function closeAcknowledgeModal() {
    document.getElementById('acknowledgeModal').style.display = 'none';
}

function runAnalysis() {
    const modal = document.getElementById('analysisModal');
    const content = document.getElementById('analysisContent');
    
    modal.style.display = 'flex';
    content.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Analyzing attendance patterns...</div>';
    
    fetch('../api/run_behavior_analysis.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="analysis-results">';
                html += '<div class="analysis-result-item info"><i class="fas fa-users"></i> Students analyzed: ' + data.students_analyzed + '</div>';
                if (typeof data.teachers_analyzed !== 'undefined') {
                    html += '<div class="analysis-result-item info"><i class="fas fa-chalkboard-teacher"></i> Teachers analyzed: ' + data.teachers_analyzed + '</div>';
                }
                html += '<div class="analysis-result-item warning"><i class="fas fa-bell"></i> New alerts generated: ' + data.new_alerts + '</div>';
                if (typeof data.new_alerts_students !== 'undefined' || typeof data.new_alerts_teachers !== 'undefined') {
                    html += '<div class="analysis-result-item warning"><i class="fas fa-user-graduate"></i> Student alerts: ' + (data.new_alerts_students || 0) + '</div>';
                    html += '<div class="analysis-result-item warning"><i class="fas fa-user-tie"></i> Teacher alerts: ' + (data.new_alerts_teachers || 0) + '</div>';
                }
                html += '<div class="analysis-result-item success"><i class="fas fa-clock"></i> Analysis completed in ' + data.duration + ' seconds</div>';
                html += '</div>';
                html += '<div class="form-actions"><button class="btn btn-primary" onclick="location.reload()"><i class="fas fa-sync"></i> Refresh Page</button></div>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<p class="error">' + (data.message || 'Analysis failed.') + '</p>';
            }
        })
        .catch(error => {
            content.innerHTML = '<p class="error">Error running analysis.</p>';
            console.error('Error:', error);
        });
}

function closeAnalysisModal() {
    document.getElementById('analysisModal').style.display = 'none';
}

window.onclick = function(event) {
    const acknowledgeModal = document.getElementById('acknowledgeModal');
    const analysisModal = document.getElementById('analysisModal');
    
    if (event.target === acknowledgeModal) {
        closeAcknowledgeModal();
    }
    if (event.target === analysisModal) {
        closeAnalysisModal();
    }
}
</script>

<?php include 'includes/footer_modern.php'; ?>
