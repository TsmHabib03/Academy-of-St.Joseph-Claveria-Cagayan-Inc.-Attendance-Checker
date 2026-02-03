<?php
/**
 * Manage Schedules - AttendEase v3.0
 * Configure AM/PM attendance schedules and late thresholds
 * 
 * @package AttendEase
 * @version 3.0
 */

require_once 'config.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Require admin role
requireRole([ROLE_ADMIN]);

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Manage Schedules';
$pageIcon = 'clock';

$additionalCSS = ['../css/manual-attendance-modern.css?v=' . time()];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_schedule') {
        $scheduleName = trim($_POST['schedule_name'] ?? '');
        $gradeLevel = trim($_POST['grade_level'] ?? '');
        $sectionId = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
        
        $morningStart = $_POST['morning_start'] ?? '07:00';
        $morningEnd = $_POST['morning_end'] ?? '12:00';
        $morningLateAfter = $_POST['morning_late_after'] ?? '07:30';
        
        $afternoonStart = $_POST['afternoon_start'] ?? '13:00';
        $afternoonEnd = $_POST['afternoon_end'] ?? '17:00';
        $afternoonLateAfter = $_POST['afternoon_late_after'] ?? '13:30';
        
        if (empty($scheduleName)) {
            $message = "Schedule name is required.";
            $messageType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_schedules 
                    (schedule_name, grade_level, section_id, 
                     morning_start, morning_end, morning_late_after,
                     afternoon_start, afternoon_end, afternoon_late_after,
                     created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $scheduleName, $gradeLevel ?: null, $sectionId,
                    $morningStart, $morningEnd, $morningLateAfter,
                    $afternoonStart, $afternoonEnd, $afternoonLateAfter
                ]);
                
                logAdminActivity('ADD_SCHEDULE', "Added schedule: {$scheduleName}");
                
                $message = "Schedule added successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error adding schedule: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif ($action === 'edit_schedule') {
        $scheduleId = intval($_POST['schedule_id']);
        $scheduleName = trim($_POST['schedule_name'] ?? '');
        $gradeLevel = trim($_POST['grade_level'] ?? '');
        $sectionId = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
        
        $morningStart = $_POST['morning_start'] ?? '07:00';
        $morningEnd = $_POST['morning_end'] ?? '12:00';
        $morningLateAfter = $_POST['morning_late_after'] ?? '07:30';
        
        $afternoonStart = $_POST['afternoon_start'] ?? '13:00';
        $afternoonEnd = $_POST['afternoon_end'] ?? '17:00';
        $afternoonLateAfter = $_POST['afternoon_late_after'] ?? '13:30';
        
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE attendance_schedules 
                SET schedule_name = ?, grade_level = ?, section_id = ?,
                    morning_start = ?, morning_end = ?, morning_late_after = ?,
                    afternoon_start = ?, afternoon_end = ?, afternoon_late_after = ?,
                    is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $scheduleName, $gradeLevel ?: null, $sectionId,
                $morningStart, $morningEnd, $morningLateAfter,
                $afternoonStart, $afternoonEnd, $afternoonLateAfter,
                $isActive, $scheduleId
            ]);
            
            logAdminActivity('EDIT_SCHEDULE', "Updated schedule: {$scheduleName}");
            
            $message = "Schedule updated successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error updating schedule: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'delete_schedule') {
        $scheduleId = intval($_POST['schedule_id']);
        
        try {
            // Get schedule name for logging
            $stmt = $pdo->prepare("SELECT schedule_name FROM attendance_schedules WHERE id = ?");
            $stmt->execute([$scheduleId]);
            $schedule = $stmt->fetch();
            
            $pdo->prepare("DELETE FROM attendance_schedules WHERE id = ?")->execute([$scheduleId]);
            
            logAdminActivity('DELETE_SCHEDULE', "Deleted schedule: " . ($schedule['schedule_name'] ?? 'Unknown'));
            
            $message = "Schedule deleted successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error deleting schedule: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'set_default') {
        $scheduleId = intval($_POST['schedule_id']);
        
        try {
            // Unset all defaults first
            $pdo->exec("UPDATE attendance_schedules SET is_default = 0");
            
            // Set new default
            $stmt = $pdo->prepare("UPDATE attendance_schedules SET is_default = 1 WHERE id = ?");
            $stmt->execute([$scheduleId]);
            
            logAdminActivity('SET_DEFAULT_SCHEDULE', "Set default schedule ID: {$scheduleId}");
            
            $message = "Default schedule updated!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error setting default: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Fetch all schedules
try {
    $schedulesStmt = $pdo->query("
        SELECT s.*, sec.section_name
        FROM attendance_schedules s
        LEFT JOIN sections sec ON s.section_id = sec.id
        ORDER BY s.is_default DESC, s.schedule_name
    ");
    $schedules = $schedulesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $schedules = [];
}

// Fetch sections for dropdown
try {
    $sectionsStmt = $pdo->query("SELECT id, section_name, grade_level FROM sections ORDER BY grade_level, section_name");
    $sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sections = [];
}

// Get unique grade levels
$gradeLevels = array_unique(array_column($sections, 'grade_level'));
sort($gradeLevels);

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
                    <span>Configure AM/PM attendance schedules and late thresholds</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <button class="btn-header btn-header-secondary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
            <button class="btn-header btn-header-primary" onclick="showAddScheduleModal()">
                <i class="fas fa-plus"></i>
                <span>Add Schedule</span>
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <div class="alert-icon">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        </div>
        <div class="alert-content">
            <?php echo $message; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Info Alert -->
<div class="alert alert-info">
    <div class="alert-icon">
        <i class="fas fa-info-circle"></i>
    </div>
    <div class="alert-content">
        <strong>How Schedules Work</strong>
        <p style="margin: var(--space-1) 0 0; line-height: 1.6;">
            Schedules define when students should arrive for AM and PM sessions. If a student scans after the "Late After" time, 
            they will be automatically marked as late. You can set different schedules for different grade levels or sections.
        </p>
    </div>
</div>

<!-- Schedules List -->
<div class="content-wrapper">
    <div class="schedules-grid">
        <?php if (empty($schedules)): ?>
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <h3>No Schedules Configured</h3>
                <p>Add a schedule to start tracking attendance times.</p>
            </div>
        <?php else: ?>
            <?php foreach ($schedules as $schedule): ?>
                <div class="schedule-card <?php echo $schedule['is_default'] ? 'default' : ''; ?> <?php echo !$schedule['is_active'] ? 'inactive' : ''; ?>">
                    <?php if ($schedule['is_default']): ?>
                        <div class="default-badge">
                            <i class="fas fa-star"></i> Default
                        </div>
                    <?php endif; ?>
                    
                    <div class="schedule-header">
                        <h3><?php echo htmlspecialchars($schedule['schedule_name']); ?></h3>
                        <div class="schedule-meta">
                            <?php if ($schedule['grade_level']): ?>
                                <span class="meta-badge grade">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo htmlspecialchars($schedule['grade_level']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($schedule['section_name']): ?>
                                <span class="meta-badge section">
                                    <i class="fas fa-chalkboard"></i>
                                    <?php echo htmlspecialchars($schedule['section_name']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!$schedule['grade_level'] && !$schedule['section_name']): ?>
                                <span class="meta-badge all">
                                    <i class="fas fa-users"></i>
                                    All Students
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="schedule-times">
                        <div class="time-block am">
                            <div class="time-label">
                                <i class="fas fa-sun"></i> Morning (AM)
                            </div>
                            <div class="time-details">
                                <div class="time-row">
                                    <span>Start:</span>
                                    <strong><?php echo date('g:i A', strtotime($schedule['morning_start'])); ?></strong>
                                </div>
                                <div class="time-row">
                                    <span>End:</span>
                                    <strong><?php echo date('g:i A', strtotime($schedule['morning_end'])); ?></strong>
                                </div>
                                <div class="time-row late">
                                    <span>Late After:</span>
                                    <strong><?php echo date('g:i A', strtotime($schedule['morning_late_after'])); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="time-block pm">
                            <div class="time-label">
                                <i class="fas fa-moon"></i> Afternoon (PM)
                            </div>
                            <div class="time-details">
                                <div class="time-row">
                                    <span>Start:</span>
                                    <strong><?php echo date('g:i A', strtotime($schedule['afternoon_start'])); ?></strong>
                                </div>
                                <div class="time-row">
                                    <span>End:</span>
                                    <strong><?php echo date('g:i A', strtotime($schedule['afternoon_end'])); ?></strong>
                                </div>
                                <div class="time-row late">
                                    <span>Late After:</span>
                                    <strong><?php echo date('g:i A', strtotime($schedule['afternoon_late_after'])); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="schedule-actions">
                        <button type="button" class="btn btn-sm btn-primary" 
                                onclick='editSchedule(<?php echo json_encode($schedule); ?>)'>
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <?php if (!$schedule['is_default']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="set_default">
                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-star"></i> Set Default
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" 
                              onsubmit="return confirm('Delete this schedule?');">
                            <input type="hidden" name="action" value="delete_schedule">
                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Schedule Modal -->
<div id="scheduleModal" class="modal" style="display:none;">
    <div class="modal-content modal-lg">
        <span class="close" onclick="closeScheduleModal()">&times;</span>
        <h2 id="scheduleModalTitle"><i class="fas fa-clock"></i> Add Schedule</h2>
        
        <form method="POST" id="scheduleForm">
            <input type="hidden" name="action" id="scheduleAction" value="add_schedule">
            <input type="hidden" name="schedule_id" id="scheduleId">
            
            <div class="form-section">
                <h4>Basic Information</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="scheduleName">Schedule Name <span class="required">*</span></label>
                        <input type="text" id="scheduleName" name="schedule_name" required 
                               placeholder="e.g., Regular Schedule">
                    </div>
                    
                    <div class="form-group">
                        <label for="gradeLevel">Grade Level</label>
                        <select id="gradeLevel" name="grade_level">
                            <option value="">All Grade Levels</option>
                            <?php foreach ($gradeLevels as $grade): ?>
                                <option value="<?php echo htmlspecialchars($grade); ?>">
                                    <?php echo htmlspecialchars($grade); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sectionId">Specific Section</label>
                        <select id="sectionId" name="section_id">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?php echo $sec['id']; ?>">
                                    <?php echo htmlspecialchars($sec['section_name'] . ' (' . $sec['grade_level'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="activeGroup" style="display:none;">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" id="scheduleActive" checked>
                            Schedule is Active
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-sun"></i> Morning Session (AM)</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="morningStart">Session Start</label>
                        <input type="time" id="morningStart" name="morning_start" value="07:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="morningEnd">Session End</label>
                        <input type="time" id="morningEnd" name="morning_end" value="12:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="morningLateAfter">Late After</label>
                        <input type="time" id="morningLateAfter" name="morning_late_after" value="07:30">
                        <small>Students arriving after this time are marked late</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-moon"></i> Afternoon Session (PM)</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="afternoonStart">Session Start</label>
                        <input type="time" id="afternoonStart" name="afternoon_start" value="13:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="afternoonEnd">Session End</label>
                        <input type="time" id="afternoonEnd" name="afternoon_end" value="17:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="afternoonLateAfter">Late After</label>
                        <input type="time" id="afternoonLateAfter" name="afternoon_late_after" value="13:30">
                        <small>Students arriving after this time are marked late</small>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Schedule
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.info-card {
    background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    color: #fff;
}

.info-icon {
    font-size: 2rem;
    opacity: 0.9;
}

.info-content h4 {
    margin: 0 0 0.5rem;
}

.info-content p {
    margin: 0;
    opacity: 0.9;
    line-height: 1.6;
}

.schedules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
}

@media (max-width: 768px) {
    .schedules-grid {
        grid-template-columns: 1fr;
    }
}

.schedule-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow: hidden;
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    min-height: 380px;
    display: flex;
    flex-direction: column;
}

.schedule-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.schedule-card.default {
    border: 2px solid var(--asj-green-500);
}

.schedule-card.inactive {
    opacity: 0.6;
}

.default-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: linear-gradient(135deg, var(--asj-green-400) 0%, var(--asj-green-600) 100%);
    color: #fff;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.schedule-header {
    padding: 1.75rem;
    border-bottom: 1px solid #eee;
    flex-shrink: 0;
}

.schedule-header h3 {
    margin: 0 0 0.875rem;
    font-size: 1.25rem;
    color: #333;
    font-weight: 600;
}

.schedule-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.meta-badge {
    padding: 0.25rem 0.625rem;
    border-radius: 6px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.meta-badge.grade {
    background: var(--asj-green-50);
    color: var(--asj-green-700);
}

.meta-badge.section {
    background: #dcfce7;
    color: #16a34a;
}

.meta-badge.all {
    background: var(--asj-green-100);
    color: var(--asj-green-700);
}

.schedule-times {
    padding: 1.5rem 1.75rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    flex: 1;
}

@media (max-width: 480px) {
    .schedule-times {
        grid-template-columns: 1fr;
        padding: 1.25rem;
    }
}

.time-block {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    min-height: 140px;
}

.time-block.am {
    background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
}

.time-block.pm {
    background: linear-gradient(135deg, var(--asj-green-50) 0%, #d4f5dc 100%);
}

.time-label {
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #333;
    font-size: 0.9375rem;
}

.time-block.am .time-label i { color: #f59e0b; }
.time-block.pm .time-label i { color: var(--asj-green-600); }

.time-details {
    font-size: 0.9rem;
}

.time-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px dashed rgba(0,0,0,0.1);
}

.time-row:last-child {
    border-bottom: none;
}

.time-row span {
    color: #666;
}

.time-row strong {
    color: #333;
}

.time-row.late {
    background: rgba(220, 38, 38, 0.1);
    margin: 0.5rem -0.5rem 0;
    padding: 0.5rem;
    border-radius: 6px;
    border-bottom: none;
}

.time-row.late span,
.time-row.late strong {
    color: #dc2626;
}

.schedule-actions {
    padding: 1.25rem 1.75rem;
    border-top: 1px solid #eee;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    flex-shrink: 0;
    background: var(--neutral-50);
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
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

/* Modal Styles */
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
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.modal-lg {
    max-width: 700px;
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
    margin-bottom: 1.5rem;
}

.form-section {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #eee;
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h4 {
    margin: 0 0 1rem;
    color: #333;
    font-size: 1rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
    font-size: 0.875rem;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9375rem;
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: #888;
    font-size: 0.75rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.required {
    color: #dc2626;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
    color: white;
    box-shadow: 0 2px 4px rgba(76, 175, 80, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--asj-green-600), var(--asj-green-700));
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(76, 175, 80, 0.4);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
    transform: translateY(-1px);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    transform: translateY(-1px);
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
}

/* Alert Styles */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.25rem;
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.alert-icon i {
    font-size: 1.25rem;
}

.alert-content {
    flex: 1;
}

.alert-content strong {
    display: block;
    margin-bottom: 0.25rem;
}

.alert-success {
    background: #f0fdf4;
    border: 1px solid #22c55e;
}

.alert-success .alert-icon {
    background: #22c55e;
    color: white;
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #ef4444;
}

.alert-error .alert-icon {
    background: #ef4444;
    color: white;
}

.alert-info {
    background: var(--asj-green-50);
    border: 1px solid var(--asj-green-500);
}

.alert-info .alert-icon {
    background: var(--asj-green-500);
    color: white;
}

/* Form Input Focus */
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--asj-green-500);
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.15);
}

/* Schedule Card Header Enhancement */
.schedule-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
    background: linear-gradient(135deg, #fafafa 0%, #fff 100%);
}

/* Schedule Actions */
.schedule-actions {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    background: #fafafa;
}

@media (max-width: 768px) {
    .schedules-grid {
        grid-template-columns: 1fr;
    }
    
    .schedule-times {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .info-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
function showAddScheduleModal() {
    document.getElementById('scheduleModalTitle').innerHTML = '<i class="fas fa-clock"></i> Add Schedule';
    document.getElementById('scheduleAction').value = 'add_schedule';
    document.getElementById('scheduleId').value = '';
    document.getElementById('scheduleName').value = '';
    document.getElementById('gradeLevel').value = '';
    document.getElementById('sectionId').value = '';
    document.getElementById('morningStart').value = '07:00';
    document.getElementById('morningEnd').value = '12:00';
    document.getElementById('morningLateAfter').value = '07:30';
    document.getElementById('afternoonStart').value = '13:00';
    document.getElementById('afternoonEnd').value = '17:00';
    document.getElementById('afternoonLateAfter').value = '13:30';
    document.getElementById('activeGroup').style.display = 'none';
    document.getElementById('scheduleModal').style.display = 'flex';
}

function editSchedule(schedule) {
    document.getElementById('scheduleModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Schedule';
    document.getElementById('scheduleAction').value = 'edit_schedule';
    document.getElementById('scheduleId').value = schedule.id;
    document.getElementById('scheduleName').value = schedule.schedule_name;
    document.getElementById('gradeLevel').value = schedule.grade_level || '';
    document.getElementById('sectionId').value = schedule.section_id || '';
    document.getElementById('morningStart').value = schedule.morning_start;
    document.getElementById('morningEnd').value = schedule.morning_end;
    document.getElementById('morningLateAfter').value = schedule.morning_late_after;
    document.getElementById('afternoonStart').value = schedule.afternoon_start;
    document.getElementById('afternoonEnd').value = schedule.afternoon_end;
    document.getElementById('afternoonLateAfter').value = schedule.afternoon_late_after;
    document.getElementById('scheduleActive').checked = schedule.is_active == 1;
    document.getElementById('activeGroup').style.display = 'block';
    document.getElementById('scheduleModal').style.display = 'flex';
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer_modern.php'; ?>
