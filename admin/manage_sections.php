<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Manage Sections';
$pageIcon = 'layer-group';

// Add manage-sections CSS - New Modern Design with cache buster
$additionalCSS = ['../css/manage-sections-modern.css?v=' . time()];

// Initialize response array for AJAX
$response = ['success' => false, 'message' => ''];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    
    try {
        // Detect which optional columns exist on `sections` (safe runtime check)
        $currentDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $sectionCols = [];
        $hasShiftColumn = false;
        $hasSessionColumn = false;
        $hasAmStartTime = false;
        $hasAmLateThreshold = false;
        $hasAmEndTime = false;
        $hasPmStartTime = false;
        $hasPmLateThreshold = false;
        $hasPmEndTime = false;
        if ($currentDb) {
            $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sections'");
            $colStmt->execute([$currentDb]);
            $sectionCols = $colStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $hasShiftColumn = in_array('shift', $sectionCols, true);
            $hasSessionColumn = in_array('session', $sectionCols, true);
            $hasAmStartTime = in_array('am_start_time', $sectionCols, true);
            $hasAmLateThreshold = in_array('am_late_threshold', $sectionCols, true);
            $hasAmEndTime = in_array('am_end_time', $sectionCols, true);
            $hasPmStartTime = in_array('pm_start_time', $sectionCols, true);
            $hasPmLateThreshold = in_array('pm_late_threshold', $sectionCols, true);
            $hasPmEndTime = in_array('pm_end_time', $sectionCols, true);
                $hasScheduleId = in_array('schedule_id', $sectionCols, true);
                $hasUsesCustomSchedule = in_array('uses_custom_schedule', $sectionCols, true);
        }

        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            // Add new section
            $section_name = trim($_POST['section_name'] ?? '');
            $grade_level = trim($_POST['grade_level'] ?? '');
            $adviser = trim($_POST['adviser'] ?? '');
            $session = trim($_POST['session'] ?? '');
            $am_start_time = trim($_POST['am_start_time'] ?? '');
            $am_late_threshold = trim($_POST['am_late_threshold'] ?? '');
            $am_end_time = trim($_POST['am_end_time'] ?? '');
            $pm_start_time = trim($_POST['pm_start_time'] ?? '');
            $pm_late_threshold = trim($_POST['pm_late_threshold'] ?? '');
            $pm_end_time = trim($_POST['pm_end_time'] ?? '');
            $school_year = trim($_POST['school_year'] ?? '');
            
            if (empty($section_name)) {
                throw new Exception('Section name is required');
            }
            
            // Check if section already exists
            $check_stmt = $pdo->prepare("SELECT id FROM sections WHERE section_name = ?");
            $check_stmt->execute([$section_name]);
            if ($check_stmt->rowCount() > 0) {
                throw new Exception('Section already exists');
            }
            
            $shift = trim($_POST['shift'] ?? '');
            $columns = ['section_name', 'grade_level', 'adviser', 'school_year'];
            $values = [$section_name, $grade_level, $adviser, $school_year];

            $schedule_id = !empty($_POST['schedule_id']) ? intval($_POST['schedule_id']) : null;
            $uses_custom_schedule = !empty($_POST['uses_custom_schedule']) ? 1 : 0;

            if ($hasShiftColumn) {
                $columns[] = 'shift';
                $values[] = $shift;
            }
            if ($hasSessionColumn) {
                $columns[] = 'session';
                $values[] = $session;
            }
            if ($hasAmStartTime) {
                $columns[] = 'am_start_time';
                $values[] = $am_start_time;
            }
            if ($hasAmLateThreshold) {
                $columns[] = 'am_late_threshold';
                $values[] = $am_late_threshold;
            }
            if ($hasAmEndTime) {
                $columns[] = 'am_end_time';
                $values[] = $am_end_time;
            }
            if ($hasPmStartTime) {
                $columns[] = 'pm_start_time';
                $values[] = $pm_start_time;
            }
            if ($hasPmLateThreshold) {
                $columns[] = 'pm_late_threshold';
                $values[] = $pm_late_threshold;
            }
            if ($hasPmEndTime) {
                $columns[] = 'pm_end_time';
                $values[] = $pm_end_time;
            }
            if ($hasScheduleId) {
                $columns[] = 'schedule_id';
                $values[] = $schedule_id;
            }
            if ($hasUsesCustomSchedule) {
                $columns[] = 'uses_custom_schedule';
                $values[] = $uses_custom_schedule;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $stmt = $pdo->prepare("INSERT INTO sections (" . implode(', ', $columns) . ") VALUES ({$placeholders})");
            $stmt->execute($values);
            
            logAdminActivity('ADD_SECTION', "Added section: $section_name");
            
            $response = ['success' => true, 'message' => 'Section added successfully!'];
            
        } elseif ($action === 'edit') {
            // Edit section
            $id = intval($_POST['id'] ?? 0);
            $section_name = trim($_POST['section_name'] ?? '');
            $grade_level = trim($_POST['grade_level'] ?? '');
            $adviser = trim($_POST['adviser'] ?? '');
            $session = trim($_POST['session'] ?? '');
            $am_start_time = trim($_POST['am_start_time'] ?? '');
            $am_late_threshold = trim($_POST['am_late_threshold'] ?? '');
            $am_end_time = trim($_POST['am_end_time'] ?? '');
            $pm_start_time = trim($_POST['pm_start_time'] ?? '');
            $pm_late_threshold = trim($_POST['pm_late_threshold'] ?? '');
            $pm_end_time = trim($_POST['pm_end_time'] ?? '');
            $school_year = trim($_POST['school_year'] ?? '');
            // Keep existing is_active value; status is no longer editable from this form
            // We will not modify `is_active` here to avoid accidental deactivation.
            
            if (empty($section_name)) {
                throw new Exception('Section name is required');
            }
            
            // Get old section name for updating students
            $old_stmt = $pdo->prepare("SELECT section_name FROM sections WHERE id = ?");
            $old_stmt->execute([$id]);
            $old_section = $old_stmt->fetch();
            
            if (!$old_section) {
                throw new Exception('Section not found');
            }
            
            $pdo->beginTransaction();
            // Update section (do not change is_active here)
            $shift = trim($_POST['shift'] ?? '');
            $setParts = ['section_name = ?', 'grade_level = ?', 'adviser = ?', 'school_year = ?'];
            $values = [$section_name, $grade_level, $adviser, $school_year];

            $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : null;
            $uses_custom_schedule = !empty($_POST['uses_custom_schedule']) ? 1 : 0;

            if ($hasShiftColumn) {
                $setParts[] = 'shift = ?';
                $values[] = $shift;
            }
            if ($hasSessionColumn) {
                $setParts[] = 'session = ?';
                $values[] = $session;
            }
            if ($hasAmStartTime) {
                $setParts[] = 'am_start_time = ?';
                $values[] = $am_start_time;
            }
            if ($hasAmLateThreshold) {
                $setParts[] = 'am_late_threshold = ?';
                $values[] = $am_late_threshold;
            }
            if ($hasAmEndTime) {
                $setParts[] = 'am_end_time = ?';
                $values[] = $am_end_time;
            }
            if ($hasPmStartTime) {
                $setParts[] = 'pm_start_time = ?';
                $values[] = $pm_start_time;
            }
            if ($hasPmLateThreshold) {
                $setParts[] = 'pm_late_threshold = ?';
                $values[] = $pm_late_threshold;
            }
            if ($hasPmEndTime) {
                $setParts[] = 'pm_end_time = ?';
                $values[] = $pm_end_time;
            }
            if ($hasScheduleId) {
                $setParts[] = 'schedule_id = ?';
                $values[] = $schedule_id;
            }
            if ($hasUsesCustomSchedule) {
                $setParts[] = 'uses_custom_schedule = ?';
                $values[] = $uses_custom_schedule;
            }

            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE sections SET " . implode(', ', $setParts) . " WHERE id = ?");
            $stmt->execute($values);
            
            // Update students' section field if section name changed
            if ($old_section['section_name'] !== $section_name) {
                $update_students = $pdo->prepare("UPDATE students SET section = ?, class = ? WHERE section = ? OR class = ?");
                $update_students->execute([$section_name, $section_name, $old_section['section_name'], $old_section['section_name']]);
            }
            
            $pdo->commit();
            
            logAdminActivity('EDIT_SECTION', "Updated section: $section_name");
            
            $response = ['success' => true, 'message' => 'Section updated successfully!'];
            
        } elseif ($action === 'delete') {
            // Delete section
            $id = intval($_POST['id'] ?? 0);
            
            // Check if section has students
            $stmt = $pdo->prepare("SELECT section_name FROM sections WHERE id = ?");
            $stmt->execute([$id]);
            $section = $stmt->fetch();
            
            if (!$section) {
                throw new Exception('Section not found');
            }
            
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE section = ? OR class = ?");
            $count_stmt->execute([$section['section_name'], $section['section_name']]);
            $count = $count_stmt->fetch()['count'];
            
            if ($count > 0) {
                throw new Exception("Cannot delete section. It has $count student(s) enrolled. Please reassign students first.");
            }
            
            $delete_stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
            $delete_stmt->execute([$id]);
            
            logAdminActivity('DELETE_SECTION', "Deleted section: {$section['section_name']}");
            
            $response = ['success' => true, 'message' => 'Section deleted successfully!'];
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Get all sections with student count
try {
    $query = "SELECT s.*, 
              (SELECT COUNT(*) FROM students st WHERE st.section = s.section_name OR st.class = s.section_name) as student_count,
              CASE WHEN s.is_active = 1 THEN 'active' ELSE 'inactive' END as status
              FROM sections s
              WHERE s.grade_level NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6')
              AND s.grade_level NOT LIKE 'Kinder%'
              ORDER BY s.section_name";
    $stmt = $pdo->query($query);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Runtime: determine if `sections.shift` column exists to enable shift UI
    $currentDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $hasShiftColumn = false;
    if ($currentDb) {
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sections' AND COLUMN_NAME = 'shift'");
        $colStmt->execute([$currentDb]);
        $hasShiftColumn = (int)$colStmt->fetchColumn() > 0;
    }

    // Fetch registered teachers for adviser dropdown (non-fatal)
    $teachers = [];
    try {
        $sql = "SELECT id, COALESCE(employee_number, employee_id) as emp_uid, CONCAT(first_name, ' ', last_name) as fullname FROM teachers ORDER BY last_name, first_name";
        $tstmt = $pdo->query($sql);
        if ($tstmt !== false) {
            $teachers = $tstmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            error_log("manage_sections: teachers query returned false");
            $teachers = [];
        }
    } catch (Exception $e) {
        error_log("manage_sections: fetch teachers error: " . $e->getMessage());
        // Try a simpler fallback select in case CONCAT or COALESCE caused issues
        try {
            $fsql = "SELECT id, employee_number as emp_uid, first_name, last_name FROM teachers ORDER BY last_name, first_name";
            $ft = $pdo->query($fsql);
            if ($ft !== false) {
                $raw = $ft->fetchAll(PDO::FETCH_ASSOC);
                foreach ($raw as $r) {
                    $teachers[] = [
                        'id' => $r['id'],
                        'emp_uid' => $r['employee_number'] ?? $r['emp_uid'] ?? '',
                        'fullname' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))
                    ];
                }
            }
        } catch (Exception $e2) {
            error_log("manage_sections: fallback fetch teachers error: " . $e2->getMessage());
            $teachers = [];
        }
    }
    
    // Load available attendance schedules (if attendance_schedules table exists)
    $schedules = [];
    try {
        $tblStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'attendance_schedules'");
        $tblStmt->execute([$currentDb]);
        $hasSchedulesTable = (int)$tblStmt->fetchColumn() > 0;
        if ($hasSchedulesTable) {
            $sstmt = $pdo->query("SELECT id, schedule_name, grade_level, section_id, morning_start AS morning_start, morning_end AS morning_end, morning_late_after AS morning_late_after, afternoon_start AS afternoon_start, afternoon_end AS afternoon_end, afternoon_late_after AS afternoon_late_after, is_default FROM attendance_schedules WHERE is_active = 1 ORDER BY is_default DESC, schedule_name ASC");
            if ($sstmt !== false) {
                $schedules = $sstmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log('manage_sections: could not load schedules: ' . $e->getMessage());
        $schedules = [];
    }
    
    // Ensure all sections have required keys to prevent undefined array key warnings
    foreach ($sections as &$section) {
        $section['status'] = $section['status'] ?? 'active';
        $section['is_active'] = $section['is_active'] ?? 1;
        $section['adviser'] = $section['adviser'] ?? '';
        $section['school_year'] = $section['school_year'] ?? '';
        $section['grade_level'] = $section['grade_level'] ?? '';
        $section['shift'] = $section['shift'] ?? '';
        $section['session'] = $section['session'] ?? '';
        $section['am_start_time'] = $section['am_start_time'] ?? '';
        $section['am_late_threshold'] = $section['am_late_threshold'] ?? '';
        $section['am_end_time'] = $section['am_end_time'] ?? '';
        $section['pm_start_time'] = $section['pm_start_time'] ?? '';
        $section['pm_late_threshold'] = $section['pm_late_threshold'] ?? '';
        $section['pm_end_time'] = $section['pm_end_time'] ?? '';
        $section['schedule_id'] = $section['schedule_id'] ?? null;
        $section['uses_custom_schedule'] = $section['uses_custom_schedule'] ?? 0;
    }
    unset($section); // Break reference
} catch (Exception $e) {
    $sections = [];
    $message = 'Error loading sections: ' . $e->getMessage();
    $messageType = 'error';
    $teachers = [];
    $hasShiftColumn = false;
}

// Expose schedules to client-side JS
echo "<script>window.AVAILABLE_SCHEDULES = " . json_encode($schedules ?? []) . ";</script>\n";

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
                    <span>Manage school sections, grades, and advisers</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <button class="btn-header btn-header-secondary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
            <button class="btn-header btn-header-primary" data-action="add-section">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Section</span>
            </button>
        </div>
    </div>
</div>

<!-- Stats Overview - Enhanced -->
<div class="stats-grid stats-grid-enhanced">
    <div class="stat-card stat-card-animated" data-stat="total">
        <div class="stat-card-inner">
            <div class="stat-icon-wrapper">
                <div class="stat-icon stat-icon-purple">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-icon-bg"></div>
            </div>
            <div class="stat-content">
                <div class="stat-value-wrapper">
                    <h3 class="stat-value" data-count="<?php echo count($sections); ?>">0</h3>
                    <span class="stat-trend stat-trend-up">
                        <i class="fas fa-arrow-up"></i>
                    </span>
                </div>
                <p class="stat-label">Total Sections</p>
                <div class="stat-progress">
                    <div class="stat-progress-bar stat-progress-purple" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="stat-card stat-card-animated" data-stat="students" style="animation-delay: 0.1s;">
        <div class="stat-card-inner">
            <div class="stat-icon-wrapper">
                <div class="stat-icon stat-icon-pink">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-icon-bg"></div>
            </div>
            <div class="stat-content">
                <div class="stat-value-wrapper">
                    <h3 class="stat-value" data-count="<?php echo array_sum(array_column($sections, 'student_count')); ?>">0</h3>
                    <span class="stat-trend stat-trend-up">
                        <i class="fas fa-arrow-up"></i>
                    </span>
                </div>
                <p class="stat-label">Total Students</p>
                <div class="stat-progress">
                    <div class="stat-progress-bar stat-progress-pink" style="width: 85%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="stat-card stat-card-animated" data-stat="active" style="animation-delay: 0.2s;">
        <div class="stat-card-inner">
            <div class="stat-icon-wrapper">
                <div class="stat-icon stat-icon-blue">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-icon-bg"></div>
            </div>
            <div class="stat-content">
                <div class="stat-value-wrapper">
                    <h3 class="stat-value" data-count="<?php echo count(array_filter($sections, fn($s) => $s['status'] === 'active')); ?>">0</h3>
                    <span class="stat-trend stat-trend-neutral">
                        <i class="fas fa-minus"></i>
                    </span>
                </div>
                <p class="stat-label">Active Sections</p>
                <div class="stat-progress">
                    <div class="stat-progress-bar stat-progress-blue" style="width: 92%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="stat-card stat-card-animated" data-stat="advisers" style="animation-delay: 0.3s;">
        <div class="stat-card-inner">
            <div class="stat-icon-wrapper">
                <div class="stat-icon stat-icon-green">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-icon-bg"></div>
            </div>
            <div class="stat-content">
                <div class="stat-value-wrapper">
                    <h3 class="stat-value" data-count="<?php echo count(array_unique(array_filter(array_column($sections, 'adviser')))); ?>">0</h3>
                    <span class="stat-trend stat-trend-up">
                        <i class="fas fa-arrow-up"></i>
                    </span>
                </div>
                <p class="stat-label">Advisers</p>
                <div class="stat-progress">
                    <div class="stat-progress-bar stat-progress-green" style="width: 78%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sections List - Enhanced -->
<div class="content-card content-card-enhanced">
    <div class="card-header-modern card-header-enhanced">
        <div class="card-header-left">
            <div class="card-icon-badge">
                <i class="fas fa-list"></i>
            </div>
            <div>
                <h2 class="card-title-modern">
                    All Sections
                </h2>
                <p class="card-subtitle-modern">
                    <i class="fas fa-info-circle"></i>
                    View and manage all school sections
                </p>
            </div>
        </div>
        <div class="card-actions card-actions-enhanced">
            <div class="filter-group">
                <div class="search-box-enhanced">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchSections" placeholder="Search sections, advisers..." autocomplete="off">
                    <button class="search-clear" id="clearSearch" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="filter-dropdown">
                    <button class="filter-btn" id="filterBtn">
                        <i class="fas fa-filter"></i>
                        <span>Filter</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-menu" id="filterMenu">
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="statusFilter" value="all" checked>
                                <span>All Sections</span>
                            </label>
                        </div>
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="statusFilter" value="active">
                                <span>Active Only</span>
                            </label>
                        </div>
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="statusFilter" value="inactive">
                                <span>Inactive Only</span>
                            </label>
                        </div>
                        <hr class="filter-divider">
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="sortFilter" value="name">
                                <span>Sort by Name</span>
                            </label>
                        </div>
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="sortFilter" value="students">
                                <span>Sort by Students</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card-body-modern">
        <?php if (empty($sections)): ?>
            <!-- BEAUTIFUL NEW EMPTY STATE DESIGN -->
            <div class="empty-state-modern">
                <div class="empty-state-animation">
                    <div class="empty-state-circle circle-1"></div>
                    <div class="empty-state-circle circle-2"></div>
                    <div class="empty-state-circle circle-3"></div>
                    <div class="empty-state-icon-wrapper">
                        <div class="empty-state-icon-modern">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>
                <div class="empty-state-content-modern">
                    <h3 class="empty-state-title-modern">No Sections Yet</h3>
                    <p class="empty-state-text-modern">
                        Sections help you organize students by grade, strand, or class.<br>
                        Create your first section to get started!
                    </p>
                    <div class="empty-state-features">
                        <div class="empty-feature">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span>Organize Students</span>
                        </div>
                        <div class="empty-feature">
                            <div class="feature-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <span>Track by Grade</span>
                        </div>
                        <div class="empty-feature">
                            <div class="feature-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <span>Assign Advisers</span>
                        </div>
                    </div>
                    <button class="btn-empty-action" data-action="add-section">
                        <span class="btn-empty-icon">
                            <i class="fas fa-plus"></i>
                        </span>
                        <span class="btn-empty-text">Create Your First Section</span>
                        <span class="btn-empty-shine"></span>
                    </button>
                    <p class="empty-state-help">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Pro Tip:</strong> Sections are automatically created when you add students with new section names
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-container table-container-enhanced">
                <div class="table-wrapper">
                    <table class="modern-table modern-table-enhanced" id="sectionsTable">
                        <thead>
                            <tr>
                                <th class="th-sortable" data-sort="name">
                                    <div class="th-content">
                                        <i class="fas fa-layer-group th-icon"></i>
                                        <span>Section Name</span>
                                        <i class="fas fa-sort sort-icon"></i>
                                    </div>
                                </th>
                                <th class="th-sortable" data-sort="grade">
                                    <div class="th-content">
                                        <i class="fas fa-graduation-cap th-icon"></i>
                                        <span>Grade Level</span>
                                        <i class="fas fa-sort sort-icon"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <i class="fas fa-clock th-icon"></i>
                                        <span>Shift</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <i class="fas fa-chalkboard-teacher th-icon"></i>
                                        <span>Adviser</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <i class="fas fa-calendar-alt th-icon"></i>
                                        <span>School Year</span>
                                    </div>
                                </th>
                                <th class="th-sortable" data-sort="students">
                                    <div class="th-content">
                                        <i class="fas fa-users th-icon"></i>
                                        <span>Students</span>
                                        <i class="fas fa-sort sort-icon"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <i class="fas fa-info-circle th-icon"></i>
                                        <span>Status</span>
                                    </div>
                                </th>
                                <th class="th-actions">
                                    <div class="th-content">
                                        <i class="fas fa-cog th-icon"></i>
                                        <span>Actions</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="sectionsTableBody">
                            <?php foreach ($sections as $index => $section): ?>
                            <tr data-section-id="<?php echo $section['id']; ?>" 
                                data-status="<?php echo $section['status']; ?>"
                                data-students="<?php echo $section['student_count']; ?>"
                                class="table-row-animated" 
                                style="animation-delay: <?php echo ($index * 0.05); ?>s;">
                                <td class="td-primary">
                                    <div class="table-cell-content">
                                        <div class="section-name-wrapper">
                                            <div class="section-icon">
                                                <i class="fas fa-bookmark"></i>
                                            </div>
                                            <strong class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($section['grade_level']): ?>
                                        <span class="grade-badge grade-badge-enhanced">
                                            <i class="fas fa-graduation-cap"></i>
                                            <span>
                                                <?php
                                                $gradeLevel = $section['grade_level'];
                                                echo 'Grade ' . htmlspecialchars($gradeLevel);
                                                ?>
                                            </span>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted-custom">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($section['shift'])): ?>
                                        <span class="shift-badge shift-<?php echo strtolower($section['shift']); ?>"><?php echo htmlspecialchars(ucfirst($section['shift'])); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted-custom">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-adviser">
                                    <?php if ($section['adviser']): ?>
                                        <div class="adviser-info">
                                            <div class="adviser-avatar">
                                                <?php echo strtoupper(substr($section['adviser'], 0, 1)); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($section['adviser']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted-custom">No Adviser</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($section['school_year']): ?>
                                        <span class="school-year-badge">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo htmlspecialchars($section['school_year']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted-custom">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-centered">
                                    <span class="badge badge-info badge-enhanced">
                                        <i class="fas fa-users"></i>
                                        <span class="badge-text"><?php echo $section['student_count']; ?> student<?php echo $section['student_count'] != 1 ? 's' : ''; ?></span>
                                    </span>
                                </td>
                                <td class="td-centered">
                                    <?php 
                                    $status = $section['status'];
                                    $statusClass = strtolower($status);
                                    $statusIcon = $status === 'active' ? 'check-circle' : 'times-circle';
                                    ?>
                                    <span class="status-badge status-badge-enhanced status-<?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?> status-icon"></i>
                                        <span><?php echo ucfirst($status); ?></span>
                                    </span>
                                </td>
                                <td class="td-actions">
                                    <div class="action-buttons action-buttons-enhanced">
                                        <button 
                                            class="btn-action btn-action-edit" 
                                            data-action="edit"
                                            data-section='<?php echo json_encode($section); ?>'
                                            title="Edit Section">
                                            <i class="fas fa-edit"></i>
                                            <span class="btn-tooltip">Edit</span>
                                        </button>
                                        <button 
                                            class="btn-action btn-action-delete" 
                                            data-action="delete"
                                            data-id="<?php echo $section['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($section['section_name']); ?>"
                                            data-count="<?php echo $section['student_count']; ?>"
                                            title="Delete Section">
                                            <i class="fas fa-trash"></i>
                                            <span class="btn-tooltip">Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Table Footer with Pagination Info -->
                <div class="table-footer">
                    <div class="table-info">
                        Showing <strong id="visibleRows"><?php echo count($sections); ?></strong> of <strong id="totalRows"><?php echo count($sections); ?></strong> sections
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Section Modal - Enhanced -->
<div class="modal-overlay modal-overlay-enhanced" id="sectionModal">
    <div class="modal-container modal-container-enhanced">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-header modal-header-enhanced">
                <div class="modal-header-content">
                    <div class="modal-icon-wrapper">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <h3 class="modal-title" id="modalTitle">Add New Section</h3>
                        <p class="modal-subtitle">Fill in the section details below the shift (AM/PM)</p>
                    </div>
                </div>
                <button class="modal-close modal-close-enhanced" data-action="close-modal" data-modal="sectionModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body modal-body-enhanced">
                <form id="sectionForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="sectionId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="section_name" class="form-label">
                                Section Name
                                <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="section_name" 
                                id="section_name" 
                                class="form-input" 
                                placeholder="e.g., 12-BARBERRA, 11-A, 10-STEM" 
                                required>
                            <small class="form-help">Use format: Grade-Name (e.g., 12-BARBERRA)</small>
                        </div>
                    </div>
                    
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label for="grade_level" class="form-label">Grade Level</label>
                            <select name="grade_level" id="grade_level" class="form-select">
                                <option value="">Select Grade Level</option>
                                <!-- Removed Early Childhood and Elementary optgroups -->
                                <optgroup label="Junior High School">
                                    <option value="7">Grade 7</option>
                                    <option value="8">Grade 8</option>
                                    <option value="9">Grade 9</option>
                                    <option value="10">Grade 10</option>
                                </optgroup>
                                <optgroup label="Senior High School">
                                    <option value="11">Grade 11</option>
                                    <option value="12">Grade 12</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="school_year" class="form-label">School Year</label>
                            <input 
                                type="text" 
                                name="school_year" 
                                id="school_year" 
                                class="form-input" 
                                placeholder="e.g., 2024-2025" 
                                value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="adviser" class="form-label">Section Adviser</label>
                            <select name="adviser" id="adviser" class="form-select">
                                <option value="">-- Select Adviser --</option>
                                <?php foreach ($teachers as $t): 
                                    $display = $t['fullname'];
                                    if (!empty($t['emp_uid'])) { $display .= ' (' . $t['emp_uid'] . ')'; }
                                ?>
                                    <option value="<?php echo htmlspecialchars($t['fullname']); ?>"><?php echo htmlspecialchars($display); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($teachers)): ?>
                                <div class="form-help" style="margin-top: .5rem;">
                                    No registered teachers found. <a href="manage_teachers.php">Add teachers</a> to assign as advisers.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shift" class="form-label">Shift</label>
                            <select name="shift" id="shift" class="form-select">
                                <option value="">Auto (time-based)</option>
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                            <small class="form-help">Fill in the section details below the shift (AM/PM).</small>
                        </div>
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label for="session" class="form-label">Session</label>
                            <select name="session" id="session" class="form-select">
                                <option value="">Default</option>
                                <option value="morning">AM (Morning)</option>
                                <option value="afternoon">PM (Afternoon)</option>
                            </select>
                            <small class="form-help">Assign default session for this section (AM/PM). Leave blank to use schedule/time-based detection.</small>
                        </div>
                        <div class="form-group">
                            <!-- placeholder for alignment -->
                        </div>
                    </div>

                    <div class="form-grid schedule-grid">
                        <div class="form-group">
                            <label class="form-label">AM Session</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="time" name="am_start_time" id="am_start_time" class="form-input" placeholder="AM Start">
                                <input type="time" name="am_late_threshold" id="am_late_threshold" class="form-input" placeholder="Late After">
                                <input type="time" name="am_end_time" id="am_end_time" class="form-input" placeholder="AM End">
                            </div>
                            <small class="form-help">AM start / late threshold / end times</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">PM Session</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="time" name="pm_start_time" id="pm_start_time" class="form-input" placeholder="PM Start">
                                <input type="time" name="pm_late_threshold" id="pm_late_threshold" class="form-input" placeholder="Late After">
                                <input type="time" name="pm_end_time" id="pm_end_time" class="form-input" placeholder="PM End">
                            </div>
                            <small class="form-help">PM start / late threshold / end times</small>
                        </div>
                    </div>
                    
                    <!-- Status is managed separately; removed from this form -->
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="sectionModal">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i>
                            <span>Save Section</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal - Enhanced -->
<div class="modal-overlay modal-overlay-enhanced" id="deleteModal">
    <div class="modal-container modal-container-small">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-body modal-body-centered">
                <div class="delete-icon-wrapper delete-icon-animated">
                    <div class="delete-icon-circle">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="delete-icon-ripple"></div>
                </div>
                <h3 class="delete-title">Delete Section?</h3>
                <p class="delete-message">
                    Are you sure you want to permanently delete<br>
                    <strong class="delete-section-highlight" id="deleteSectionName"></strong>?
                </p>
                <p id="deleteWarning" class="delete-warning delete-warning-enhanced" style="display: none;">
                    <i class="fas fa-shield-alt"></i>
                    <span></span>
                </p>
                
                <div class="modal-actions modal-actions-centered">
                    <button 
                        type="button" 
                        class="btn btn-secondary btn-modal" 
                        data-action="close-modal" 
                        data-modal="deleteModal">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </button>
                    <button 
                        type="button" 
                        class="btn btn-danger btn-modal" 
                        id="confirmDeleteBtn"
                        data-action="confirm-delete">
                        <i class="fas fa-trash"></i>
                        <span>Yes, Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Manage Sections - Modern Implementation
 */

// State Management
const SectionManager = {
    currentSection: null,
    deleteId: null,
    deleteName: '',
    deleteCount: 0
};

// Notification System
function showNotification(message, type = 'info') {
    const container = document.createElement('div');
    container.className = 'notification-container';
    container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.cssText = 'min-width: 300px; animation: slideInRight 0.3s ease;';
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    notification.innerHTML = `
        <div class="alert-icon">
            <i class="fas fa-${icons[type] || 'info-circle'}"></i>
        </div>
        <div class="alert-content">${message}</div>
    `;
    
    container.appendChild(notification);
    document.body.appendChild(container);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => container.remove(), 300);
    }, 3000);
}

// Modal Management
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Prevent body scroll without layout shift
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = scrollbarWidth + 'px';
        
        modal.classList.add('active');
        
        // Mobile full-screen
        if (window.innerWidth < 480) {
            modal.classList.add('modal-mobile-fullscreen');
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        
        // Restore body scroll
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        if (window.innerWidth < 480) {
            modal.classList.remove('modal-mobile-fullscreen');
        }
    }
}

// Add Section
function openAddModal() {
    const titleElement = document.querySelector('#modalTitle');
    
    titleElement.innerHTML = '<i class="fas fa-plus-circle"></i> Add New Section';
    
    document.getElementById('formAction').value = 'add';
    document.getElementById('sectionForm').reset();
    document.getElementById('school_year').value = '<?php echo date('Y') . '-' . (date('Y') + 1); ?>';
    // Reset adviser and shift selects if present
    const adviserSelect = document.getElementById('adviser');
    if (adviserSelect) adviserSelect.value = '';
    const shiftSelect = document.getElementById('shift');
    if (shiftSelect) shiftSelect.value = '';
    const sessionEl = document.getElementById('session');
    if (sessionEl) sessionEl.value = '';
    const amStart = document.getElementById('am_start_time');
    if (amStart) amStart.value = '';
    const amLate = document.getElementById('am_late_threshold');
    if (amLate) amLate.value = '';
    const amEnd = document.getElementById('am_end_time');
    if (amEnd) amEnd.value = '';
    // Ensure schedule controls exist and set default schedule if available
    ensureScheduleControls();
    try {
        const sel = document.getElementById('schedule_id');
        if (sel) {
            const def = (window.AVAILABLE_SCHEDULES || []).find(s => s.is_default == 1);
            sel.value = def ? def.id : '';
        }
        const uses = document.getElementById('uses_custom_schedule');
        if (uses) uses.checked = false;
    } catch(e) {}
    if (pmStart) pmStart.value = '';
    const pmLate = document.getElementById('pm_late_threshold');
    if (pmLate) pmLate.value = '';
    const pmEnd = document.getElementById('pm_end_time');
    if (pmEnd) pmEnd.value = '';
    
    openModal('sectionModal');
}

// Inject schedule selector and override toggle into the form when schedules are available
function ensureScheduleControls() {
    if (!window.AVAILABLE_SCHEDULES || !Array.isArray(window.AVAILABLE_SCHEDULES) || window.AVAILABLE_SCHEDULES.length === 0) return;
    const form = document.getElementById('sectionForm');
    if (!form) return;
    if (document.getElementById('schedule_select_container')) return; // already added

    const container = document.createElement('div');
    container.id = 'schedule_select_container';
    container.className = 'form-row form-row-schedule';
    const options = ['<option value="">(Use default schedule)</option>'];
    window.AVAILABLE_SCHEDULES.forEach(s => {
        const label = s.schedule_name || ('Schedule #' + s.id);
        options.push(`<option value="${s.id}">${label}</option>`);
    });

    container.innerHTML = `
        <label class="form-label">Assigned Schedule</label>
        <div class="form-row-inline">
            <select id="schedule_id" name="schedule_id" class="form-select">${options.join('')}</select>
            <label class="form-checkbox-label" style="margin-left:12px;display:flex;align-items:center;gap:8px;"><input type="checkbox" id="uses_custom_schedule" name="uses_custom_schedule"> Use custom schedule for this section</label>
        </div>
        <p class="form-help">Select a predefined schedule from Manage Schedules. Enable custom schedule to override times per-section.</p>
    `;

    // Insert near top of form
    const firstRow = form.querySelector('.form-row') || form.firstChild;
    form.insertBefore(container, firstRow);

    // Toggle handler: when uses_custom_schedule is checked enable time inputs
    const toggle = document.getElementById('uses_custom_schedule');
    toggle.addEventListener('change', function() {
        const enabled = this.checked;
        ['am_start_time','am_late_threshold','am_end_time','pm_start_time','pm_late_threshold','pm_end_time'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = !enabled;
            el.closest('.form-row')?.classList.toggle('muted', !enabled);
        });
    });

}

// Edit Section
function editSection(section) {
    const titleElement = document.querySelector('#modalTitle');
    titleElement.innerHTML = '<i class="fas fa-edit"></i> Edit Section';
    
    document.getElementById('formAction').value = 'edit';
    document.getElementById('sectionId').value = section.id;
    document.getElementById('section_name').value = section.section_name;
    document.getElementById('grade_level').value = section.grade_level || '';
    if (document.getElementById('adviser')) {
        const adviserEl = document.getElementById('adviser');
        let set = false;
        if (section.adviser) {
            for (let i = 0; i < adviserEl.options.length; i++) {
                if (adviserEl.options[i].value === section.adviser) { adviserEl.selectedIndex = i; set = true; break; }
            }
            if (!set) {
                for (let i = 0; i < adviserEl.options.length; i++) {
                    if (adviserEl.options[i].text && adviserEl.options[i].text.indexOf(section.adviser) !== -1) { adviserEl.selectedIndex = i; set = true; break; }
                }
            }
        }
        if (!set) adviserEl.value = '';
    }
    document.getElementById('school_year').value = section.school_year || '';
    if (document.getElementById('shift')) document.getElementById('shift').value = section.shift || '';

    // set session/schedule values if present
    try { document.getElementById('session').value = section.session || ''; } catch(e) {}
    try { document.getElementById('am_start_time').value = section.am_start_time || ''; } catch(e) {}
    try { document.getElementById('am_late_threshold').value = section.am_late_threshold || ''; } catch(e) {}
    try { document.getElementById('am_end_time').value = section.am_end_time || ''; } catch(e) {}
    try { document.getElementById('pm_start_time').value = section.pm_start_time || ''; } catch(e) {}
    try { document.getElementById('pm_late_threshold').value = section.pm_late_threshold || ''; } catch(e) {}
    try { document.getElementById('pm_end_time').value = section.pm_end_time || ''; } catch(e) {}
    // If schedule controls exist, set them
    try {
        ensureScheduleControls();
        const sel = document.getElementById('schedule_id');
        if (sel) sel.value = section.schedule_id || '';
        const uses = document.getElementById('uses_custom_schedule');
        if (uses) uses.checked = !!section.uses_custom_schedule;
        // Trigger change to enable/disable time inputs
        if (uses) uses.dispatchEvent(new Event('change'));
    } catch(e) {}
    
    SectionManager.currentSection = section;
    openModal('sectionModal');
}

// Delete Section
function deleteSection(id, name, studentCount) {
    SectionManager.deleteId = id;
    SectionManager.deleteName = name;
    SectionManager.deleteCount = studentCount;
    
    document.getElementById('deleteSectionName').textContent = name;
    
    const warning = document.getElementById('deleteWarning');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    if (studentCount > 0) {
        warning.querySelector('span').textContent = `This section has ${studentCount} student(s) enrolled. You must reassign or remove them first.`;
        warning.style.display = 'flex';
        confirmBtn.disabled = true;
        confirmBtn.style.opacity = '0.5';
        confirmBtn.style.cursor = 'not-allowed';
    } else {
        warning.style.display = 'none';
        confirmBtn.disabled = false;
        confirmBtn.style.opacity = '1';
        confirmBtn.style.cursor = 'pointer';
    }
    
    openModal('deleteModal');
}

// Confirm Delete
async function confirmDelete() {
    if (SectionManager.deleteCount > 0) {
        showNotification('Cannot delete section with enrolled students', 'error');
        return;
    }
    
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.classList.add('btn-loading');
    confirmBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', SectionManager.deleteId);
        
        const response = await fetch('manage_sections.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal('deleteModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to delete section', 'error');
            confirmBtn.classList.remove('btn-loading');
            confirmBtn.disabled = false;
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Network error. Please try again.', 'error');
        confirmBtn.classList.remove('btn-loading');
        confirmBtn.disabled = false;
    }
}

// Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const sectionForm = document.getElementById('sectionForm');
    
    if (sectionForm) {
        sectionForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            
            const formData = new FormData(sectionForm);
            
            try {
                const response = await fetch('manage_sections.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('sectionModal');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Failed to save section', 'error');
                    submitBtn.classList.remove('btn-loading');
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Form submission error:', error);
                showNotification('Network error. Please try again.', 'error');
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
            }
        });
    }
    
    // Event Delegation for Buttons
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        
        const action = target.dataset.action;
        
        if (action === 'add-section') {
            openAddModal();
        } else if (action === 'edit') {
            const section = JSON.parse(target.dataset.section);
            editSection(section);
        } else if (action === 'delete') {
            const id = parseInt(target.dataset.id);
            const name = target.dataset.name;
            const count = parseInt(target.dataset.count);
            deleteSection(id, name, count);
        } else if (action === 'close-modal') {
            const modalId = target.dataset.modal;
            closeModal(modalId);
        } else if (action === 'confirm-delete') {
            confirmDelete();
        }
    });
    
    // Close modals on outside click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('sectionModal');
            closeModal('deleteModal');
        }
    });
    
    // Enhanced Search Functionality
    const searchInput = document.getElementById('searchSections');
    const clearSearchBtn = document.getElementById('clearSearch');
    const totalRowsSpan = document.getElementById('totalRows');
    const visibleRowsSpan = document.getElementById('visibleRows');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#sectionsTableBody tr');
            let visibleCount = 0;
            
            // Show/hide clear button
            clearSearchBtn.style.display = searchTerm ? 'flex' : 'none';
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const isVisible = text.includes(searchTerm);
                row.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });
            
            // Update visible count
            visibleRowsSpan.textContent = visibleCount;
            
            // Show "no results" message if needed
            updateEmptyState(visibleCount === 0, searchTerm);
        });
        
        // Clear search
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        });
    }
    
    // Filter Functionality
    const filterBtn = document.getElementById('filterBtn');
    const filterMenu = document.getElementById('filterMenu');
    
    if (filterBtn && filterMenu) {
        filterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            filterMenu.classList.toggle('active');
        });
        
        // Close filter menu when clicking outside
        document.addEventListener('click', function() {
            filterMenu.classList.remove('active');
        });
        
        filterMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Status filter
        const statusFilters = document.querySelectorAll('input[name="statusFilter"]');
        statusFilters.forEach(filter => {
            filter.addEventListener('change', function() {
                applyFilters();
            });
        });
    }
    
    function applyFilters() {
        const statusFilter = document.querySelector('input[name="statusFilter"]:checked')?.value || 'all';
        const rows = document.querySelectorAll('#sectionsTableBody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const status = row.dataset.status;
            let shouldShow = true;
            
            if (statusFilter !== 'all') {
                shouldShow = status === statusFilter;
            }
            
            // Also apply search filter
            const searchTerm = searchInput.value.toLowerCase().trim();
            if (searchTerm && shouldShow) {
                const text = row.textContent.toLowerCase();
                shouldShow = text.includes(searchTerm);
            }
            
            row.style.display = shouldShow ? '' : 'none';
            if (shouldShow) visibleCount++;
        });
        
        visibleRowsSpan.textContent = visibleCount;
        updateEmptyState(visibleCount === 0);
    }
    
    function updateEmptyState(isEmpty, searchTerm = '') {
        let emptyState = document.querySelector('.empty-state-search');
        
        if (isEmpty && !emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'empty-state-search';
            emptyState.innerHTML = `
                <div class="empty-state-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>No sections found</h3>
                <p>${searchTerm ? `No results for "${searchTerm}"` : 'Try adjusting your filters'}</p>
            `;
            document.querySelector('.table-wrapper').appendChild(emptyState);
        } else if (!isEmpty && emptyState) {
            emptyState.remove();
        }
    }
    
    // Animate stat cards on load
    function animateStats() {
        const statValues = document.querySelectorAll('.stat-value[data-count]');
        statValues.forEach(stat => {
            const target = parseInt(stat.dataset.count);
            let current = 0;
            const increment = Math.ceil(target / 30);
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                stat.textContent = current;
            }, 30);
        });
    }
    
    // Run animations
    setTimeout(animateStats, 100);
    // Ensure schedule controls are present on load when schedules are available
    try { ensureScheduleControls(); } catch(e) {}

    console.log('✅ Enhanced Sections Management initialized');
});
</script>

<?php include 'includes/footer_modern.php'; ?>
