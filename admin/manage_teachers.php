<?php
/**
 * Manage Teachers - AttendEase v3.0
 * Admin interface for teacher management
 * 
 * @package AttendEase
 * @version 3.0
 */

require_once 'config.php';
require_once __DIR__ . '/../includes/qrcode_helper.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Require admin role
requireRole([ROLE_ADMIN]);

// Initialize variables
$message = '';
$messageType = 'info';
$editMode = false;
$editTeacher = null;

// Page metadata
$currentAdmin = getCurrentAdmin();
$pageTitle = isset($_GET['id']) ? 'Edit Teacher' : 'Manage Teachers';
$pageIcon = 'chalkboard-teacher';
// Add enhanced admin CSS for header design
$additionalCSS = ['../css/manual-attendance-modern.css?v=' . time()];

// Check if editing
if (isset($_GET['id'])) {
    $editMode = true;
    $editId = intval($_GET['id']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
        $stmt->execute([$editId]);
        $editTeacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editTeacher) {
            $message = "Teacher not found.";
            $messageType = "error";
        }
    } catch (Exception $e) {
        $message = "Error retrieving teacher information.";
        $messageType = "error";
        error_log("Edit teacher error: " . $e->getMessage());
    }
}

// Resolve identifier columns across schema variants
$teacherIdentifierCandidates = ['Faculty_ID_Number', 'faculty_id_number', 'employee_number', 'employee_id'];
$teacherIdentifierColumn = null;
foreach ($teacherIdentifierCandidates as $candidate) {
    if (columnExists($pdo, 'teachers', $candidate)) {
        $teacherIdentifierColumn = $candidate;
        break;
    }
}

$attendanceIdentifierCandidates = ['Faculty_ID_Number', 'faculty_id_number', 'employee_number', 'employee_id'];
$attendanceIdentifierColumn = null;
foreach ($attendanceIdentifierCandidates as $candidate) {
    if (columnExists($pdo, 'teacher_attendance', $candidate)) {
        $attendanceIdentifierColumn = $candidate;
        break;
    }
}

$teacherHasEmployeeId = columnExists($pdo, 'teachers', 'employee_id');
$teacherHasUpdatedAt = columnExists($pdo, 'teachers', 'updated_at');
$teacherHasShift = columnExists($pdo, 'teachers', 'shift');
$teacherHasCreatedAt = columnExists($pdo, 'teachers', 'created_at');
$attendanceHasEmployeeNumber = columnExists($pdo, 'teacher_attendance', 'employee_number');
$attendanceHasEmployeeId = columnExists($pdo, 'teacher_attendance', 'employee_id');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        // Get form data
        $employeeNumber = trim($_POST['employee_number'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $sex = $_POST['sex'] ?? 'Male';
        $mobileNumber = trim($_POST['mobile_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $shift = trim($_POST['shift'] ?? 'morning');
        
        // Validation
        $errors = [];
        
        if (empty($employeeNumber) || !preg_match('/^\d{7}$/', $employeeNumber)) {
            $errors[] = "Faculty ID Number is required and must be a 7-digit number.";
        }
        
        if (empty($firstName)) {
            $errors[] = "First name is required.";
        }
        
        if (empty($lastName)) {
            $errors[] = "Last name is required.";
        }
        
        // Validate mobile number format (Philippine)
        if (!empty($mobileNumber)) {
            $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNumber);
            if (!preg_match('/^(09\d{9}|639\d{9}|9\d{9})$/', $cleanMobile)) {
                $errors[] = "Invalid mobile number format. Use 09XX-XXX-XXXX format.";
            }
        }
        
        // Validate email format
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address format.";
        }

        // Validate shift
        $shiftOptions = ['morning', 'afternoon', 'both'];
        if (!in_array($shift, $shiftOptions, true)) {
            $errors[] = "Invalid shift selected.";
        }

        if ($teacherIdentifierColumn === null) {
            $errors[] = "Teacher identifier column is missing in the database. Expected one of: Faculty_ID_Number, employee_number, or employee_id.";
        }
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $teacherIdColumnSql = '`' . $teacherIdentifierColumn . '`';

                    // Ensure faculty identifier is present and unique (7 digits)
                    if (!preg_match('/^\d{7}$/', $employeeNumber)) {
                        $message = "Faculty ID Number must be a 7-digit number.";
                        $messageType = "error";
                    } else {
                        $checkStmt = $pdo->prepare("SELECT id FROM teachers WHERE {$teacherIdColumnSql} = ?");
                        $checkStmt->execute([$employeeNumber]);
                        if ($checkStmt->fetch()) {
                            $message = "A teacher with this Faculty ID Number already exists.";
                            $messageType = "error";
                        } else {
                            // Generate QR code using Faculty ID Number
                            $qrData = 'TEACHER:' . $employeeNumber;
                            $qrCodePath = generateQRCode($qrData, 'teacher');

                            
                            $insertCols = [
                                $teacherIdColumnSql,
                                'first_name',
                                'middle_name',
                                'last_name',
                                'sex',
                                'mobile_number',
                                'email',
                                'department',
                                'position'
                            ];
                            $insertValues = [
                                $employeeNumber, $firstName, $middleName, $lastName,
                                $sex, $mobileNumber, $email, $department, $position
                            ];
                            if ($teacherHasShift) {
                                $insertCols[] = '`shift`';
                                $insertValues[] = $shift;
                            }
                            $insertCols[] = 'qr_code';
                            $insertValues[] = $qrCodePath;

                            if ($teacherHasCreatedAt) {
                                $insertCols[] = 'created_at';
                            }

                            $placeholders = array_fill(0, count($insertValues), '?');
                            if ($teacherHasCreatedAt) {
                                $placeholders[] = 'NOW()';
                            }

                            $stmt = $pdo->prepare(
                                "INSERT INTO teachers (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")"
                            );
                            $stmt->execute($insertValues);

                                // Generate a simple employee_id using the new row id
                                $newTeacherId = $pdo->lastInsertId();
                                if ($newTeacherId && $teacherHasEmployeeId) {
                                    $generatedEmpId = 'EMP-' . str_pad($newTeacherId, 6, '0', STR_PAD_LEFT);
                                    $upd = $pdo->prepare("UPDATE teachers SET employee_id = ? WHERE id = ?");
                                    $upd->execute([$generatedEmpId, $newTeacherId]);
                                }

                            logAdminActivity('ADD_TEACHER', "Added teacher: {$firstName} {$lastName} (Num: {$employeeNumber})");

                            $message = "Teacher added successfully!";
                            $messageType = "success";

                            // Clear form
                            $_POST = [];
                        }
                    }
                } else {
                    // Update existing teacher
                    $teacherId = intval($_POST['teacher_id']);
                    $teacherIdColumnSql = '`' . $teacherIdentifierColumn . '`';
                    
                    // Check for duplicate faculty identifier (excluding current teacher)
                    $checkStmt = $pdo->prepare("SELECT id FROM teachers WHERE {$teacherIdColumnSql} = ? AND id != ?");
                    $checkStmt->execute([$employeeNumber, $teacherId]);
                    if ($checkStmt->fetch()) {
                        $message = "Another teacher with this Faculty ID Number already exists.";
                        $messageType = "error";
                    } else {
                        $setParts = [
                            "{$teacherIdColumnSql} = ?",
                            "first_name = ?",
                            "middle_name = ?",
                            "last_name = ?",
                            "sex = ?",
                            "mobile_number = ?",
                            "email = ?",
                            "department = ?",
                            "position = ?"
                        ];
                        $updateValues = [
                            $employeeNumber, $firstName, $middleName, $lastName,
                            $sex, $mobileNumber, $email, $department, $position
                        ];
                        if ($teacherHasShift) {
                            $setParts[] = "`shift` = ?";
                            $updateValues[] = $shift;
                        }
                        if ($teacherHasUpdatedAt) {
                            $setParts[] = "updated_at = NOW()";
                        }
                        $updateValues[] = $teacherId;

                        $stmt = $pdo->prepare("
                            UPDATE teachers 
                            SET " . implode(', ', $setParts) . "
                            WHERE id = ?
                        ");
                        $stmt->execute($updateValues);

                        logAdminActivity('EDIT_TEACHER', "Updated teacher: {$firstName} {$lastName} (Num: {$employeeNumber})");

                        $message = "Teacher updated successfully!";
                        $messageType = "success";

                        // Refresh teacher data
                        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
                        $stmt->execute([$teacherId]);
                        $editTeacher = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Exception $e) {
                $message = "Error saving teacher: " . $e->getMessage();
                $messageType = "error";
                error_log("Save teacher error: " . $e->getMessage());
            }
        } else {
            $message = implode("<br>", $errors);
            $messageType = "error";
        }
    } elseif ($action === 'delete') {
        $teacherId = intval($_POST['teacher_id']);
        
        try {
            // Get teacher info for logging
            $teacherSelect = ['id', 'first_name', 'last_name'];
            if ($teacherIdentifierColumn !== null) {
                $teacherSelect[] = '`' . $teacherIdentifierColumn . '` AS teacher_identifier';
            }
            if ($teacherHasEmployeeId) {
                $teacherSelect[] = 'employee_id';
            }
            $stmt = $pdo->prepare("SELECT " . implode(', ', $teacherSelect) . " FROM teachers WHERE id = ?");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($teacher) {
                $identifierValue = $teacher['teacher_identifier'] ?? null;
                $deletedAttendance = 0;

                if ($attendanceIdentifierColumn !== null && !empty($identifierValue)) {
                    $attendanceIdColumnSql = '`' . $attendanceIdentifierColumn . '`';
                    $stmtAtt = $pdo->prepare("DELETE FROM teacher_attendance WHERE {$attendanceIdColumnSql} = ?");
                    $stmtAtt->execute([$identifierValue]);
                    $deletedAttendance = $stmtAtt->rowCount();
                } elseif ($attendanceHasEmployeeId && !empty($teacher['employee_id'])) {
                    $stmtAtt = $pdo->prepare("DELETE FROM teacher_attendance WHERE employee_id = ?");
                    $stmtAtt->execute([$teacher['employee_id']]);
                    $deletedAttendance = $stmtAtt->rowCount();
                }

                // Delete teacher
                $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
                $stmt->execute([$teacherId]);

                $idDisplay = $identifierValue ?? ($teacher['employee_id'] ?? 'N/A');
                logAdminActivity('DELETE_TEACHER', 
                    "Deleted teacher: {$teacher['first_name']} {$teacher['last_name']} (ID: {$idDisplay}). " .
                    "Also deleted {$deletedAttendance} attendance records.");
                
                $message = "Teacher deleted successfully.";
                $messageType = "success";
            } else {
                $message = "Teacher not found.";
                $messageType = "error";
            }
        } catch (Exception $e) {
            $message = "Error deleting teacher: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'regenerate_qr') {
        $teacherId = intval($_POST['teacher_id']);
        
        try {
                    // Select only the resolved identifier column and id
                    $selectCols = ['id'];
                    if ($teacherIdentifierColumn !== null) {
                        $selectCols[] = '`' . $teacherIdentifierColumn . '` AS teacher_identifier';
                    }
                    $stmt = $pdo->prepare("SELECT " . implode(', ', $selectCols) . " FROM teachers WHERE id = ?");
                    $stmt->execute([$teacherId]);
                    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($teacher) {
                        $identifierValue = $teacher['teacher_identifier'] ?? ('ID' . ($teacher['id'] ?? $teacherId));
                        $qrData = 'TEACHER:' . $identifierValue;
                        $qrCodePath = generateQRCode($qrData, 'teacher');

                        if ($qrCodePath === false) {
                            $message = "Failed to generate QR code. Check server permissions or network connectivity.";
                            $messageType = "error";
                        } else {
                            $updateStmt = $pdo->prepare("UPDATE teachers SET qr_code = ? WHERE id = ?");
                            $updateStmt->execute([$qrCodePath, $teacherId]);

                            $message = "QR code regenerated successfully!";
                            $messageType = "success";
                        }
                    }
        } catch (Exception $e) {
            $message = "Error regenerating QR code.";
            $messageType = "error";
        }
    }
}

// Fetch all teachers for listing
try {
    if ($attendanceIdentifierColumn !== null && $teacherIdentifierColumn !== null) {
        $attendanceIdColumnSql = '`' . $attendanceIdentifierColumn . '`';
        $teacherIdColumnSql = '`' . $teacherIdentifierColumn . '`';
        $sub = "(SELECT MAX(date) FROM teacher_attendance WHERE {$attendanceIdColumnSql} = t.{$teacherIdColumnSql}) AS last_attendance";
    } else {
        $sub = "NULL AS last_attendance";
    }

    $sql = "SELECT t.*, {$sub} FROM teachers t ORDER BY t.last_name, t.first_name";
    $stmt = $pdo->query($sql);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $teachers = [];
    error_log("Fetch teachers error: " . $e->getMessage());
}

// Fetch departments for dropdown
$departments = ['Science', 'Mathematics', 'English', 'Filipino', 'Social Studies', 'TLE', 'MAPEH', 'Values Education', 'Administration', 'Other'];
// Shift options for teacher scheduling
$shiftOptions = ['morning', 'afternoon', 'both'];

$editTeacherIdentifierValue = $_POST['employee_number'] ?? '';
if ($editMode && is_array($editTeacher)) {
    if ($teacherIdentifierColumn !== null && isset($editTeacher[$teacherIdentifierColumn])) {
        $editTeacherIdentifierValue = (string) $editTeacher[$teacherIdentifierColumn];
    } elseif (isset($editTeacher['employee_number'])) {
        $editTeacherIdentifierValue = (string) $editTeacher['employee_number'];
    } elseif (isset($editTeacher['employee_id'])) {
        $editTeacherIdentifierValue = (string) $editTeacher['employee_id'];
    } elseif (isset($editTeacher['Faculty_ID_Number'])) {
        $editTeacherIdentifierValue = (string) $editTeacher['Faculty_ID_Number'];
    }
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
                    <span>Manage teacher records and QR codes for attendance</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <button class="btn-header btn-header-secondary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
            <?php if ($editMode): ?>
                <a href="manage_teachers.php" class="btn-header btn-header-primary">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to List</span>
                </a>
            <?php else: ?>
                <button class="btn-header btn-header-primary" onclick="showAddForm()">
                    <i class="fas fa-plus"></i>
                    <span>Add Teacher</span>
                </button>
            <?php endif; ?>
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
        <strong>Teacher Management</strong>
        <p style="margin: var(--space-1) 0 0; line-height: 1.6;">
            Add, edit, and manage teacher records. Each teacher gets a unique QR code for attendance tracking.
            You can regenerate QR codes if needed.
        </p>
    </div>
</div>

<div class="content-wrapper">
    <!-- Add/Edit Form -->
    <div id="teacherForm" class="form-card" style="<?php echo (!$editMode && empty($_POST)) ? 'display:none;' : ''; ?>">
        <div class="form-card-header">
            <h2 class="form-card-title">
                <i class="fas fa-<?php echo $editMode ? 'edit' : 'user-plus'; ?>"></i>
                <?php echo $editMode ? 'Edit Teacher' : 'Add New Teacher'; ?>
            </h2>
        </div>
        <div class="form-card-body">
        <form method="POST" class="teacher-form">
            <input type="hidden" name="action" value="<?php echo $editMode ? 'edit' : 'add'; ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="teacher_id" value="<?php echo $editTeacher['id']; ?>">
            <?php endif; ?>
            
            <div class="form-grid two-col">
                <div class="form-group">
                    <label for="employee_number">Faculty ID Number <span class="required">*</span></label>
                    <input type="text" id="employee_number" name="employee_number" class="form-input"
                           value="<?php echo htmlspecialchars($editTeacherIdentifierValue); ?>"
                           placeholder="7-digit faculty ID e.g., 4354188" required pattern="\d{7}">
                </div>

                
                
                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-input"
                           value="<?php echo htmlspecialchars($editTeacher['first_name'] ?? $_POST['first_name'] ?? ''); ?>"
                           placeholder="Enter first name" required>
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" class="form-input"
                           value="<?php echo htmlspecialchars($editTeacher['middle_name'] ?? $_POST['middle_name'] ?? ''); ?>"
                           placeholder="Enter middle name">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-input"
                           value="<?php echo htmlspecialchars($editTeacher['last_name'] ?? $_POST['last_name'] ?? ''); ?>"
                           placeholder="Enter last name" required>
                </div>
                
                <div class="form-group">
                    <label for="sex">Sex <span class="required">*</span></label>
                    <select id="sex" name="sex" class="form-select" required>
                        <option value="Male" <?php echo (($editTeacher['sex'] ?? $_POST['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (($editTeacher['sex'] ?? $_POST['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="mobile_number">Mobile Number</label>
                    <input type="tel" id="mobile_number" name="mobile_number" class="form-input"
                           value="<?php echo htmlspecialchars($editTeacher['mobile_number'] ?? $_POST['mobile_number'] ?? ''); ?>"
                           placeholder="09XX-XXX-XXXX">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input"
                           value="<?php echo htmlspecialchars($editTeacher['email'] ?? $_POST['email'] ?? ''); ?>"
                           placeholder="teacher@example.com">
                    <small style="color: var(--gray-500); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Used for attendance notifications and alerts</small>
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>" 
                                <?php echo (($editTeacher['department'] ?? $_POST['department'] ?? '') === $dept) ? 'selected' : ''; ?>>
                                <?php echo $dept; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                    <div class="form-group">
                        <label for="shift">Shift</label>
                        <select id="shift" name="shift" class="form-select">
                            <?php foreach ($shiftOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ((($editTeacher['shift'] ?? $_POST['shift'] ?? 'morning') === $opt) ? 'selected' : ''); ?>><?php echo ucfirst($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                
                <div class="form-group">
                    <label for="position">Position</label>
                    <input type="text" id="position" name="position" class="form-input"
                           value="<?php echo htmlspecialchars($editTeacher['position'] ?? $_POST['position'] ?? ''); ?>"
                           placeholder="e.g., Subject Teacher, Department Head">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $editMode ? 'Update Teacher' : 'Add Teacher'; ?>
                </button>
                <?php if (!$editMode): ?>
                    <button type="button" class="btn btn-secondary" onclick="hideAddForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                <?php endif; ?>
            </div>
        </form>
        </div>
    </div>

    <!-- Teachers List -->
    <?php if (!$editMode): ?>
    <div class="form-card">
        <div class="form-card-header">
            <h2 class="form-card-title">
                <i class="fas fa-list"></i>
                Teachers List
            </h2>
            <div class="card-actions">
                <input type="text" id="searchTeachers" placeholder="Search teachers..." class="form-input" style="max-width: 250px;">
            </div>
        </div>
        <div class="form-card-body">
        <div class="table-responsive">
            <table class="data-table" id="teachersTable">
                <thead>
                    <tr>
                        <th>Faculty ID Number</th>
                        <th>Name</th>
                        <th>Sex</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Shift</th>
                        <th>Mobile</th>
                        <th>Last Attendance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <?php
                        // Admin diagnostic when no teachers are returned
                        $diag = [];
                        try {
                            $cntStmt = $pdo->query("SELECT COUNT(*) FROM teachers");
                            $diag['teachers_count'] = (int) $cntStmt->fetchColumn();
                        } catch (Exception $e) {
                            $diag['teachers_count'] = 'ERROR: ' . $e->getMessage();
                        }

                        try {
                            $diag['teachers_has_faculty_id_number'] = columnExists($pdo, 'teachers', 'Faculty_ID_Number') ? 'yes' : 'no';
                            $diag['teachers_has_employee_number'] = columnExists($pdo, 'teachers', 'employee_number') ? 'yes' : 'no';
                            $diag['teachers_has_employee_id'] = columnExists($pdo, 'teachers', 'employee_id') ? 'yes' : 'no';
                            $diag['attendance_has_faculty_id_number'] = columnExists($pdo, 'teacher_attendance', 'Faculty_ID_Number') ? 'yes' : 'no';
                            $diag['attendance_has_employee_number'] = columnExists($pdo, 'teacher_attendance', 'employee_number') ? 'yes' : 'no';
                            $diag['attendance_has_employee_id'] = columnExists($pdo, 'teacher_attendance', 'employee_id') ? 'yes' : 'no';
                            $diag['resolved_teacher_id_column'] = $teacherIdentifierColumn ?? 'none';
                            $diag['resolved_attendance_id_column'] = $attendanceIdentifierColumn ?? 'none';
                        } catch (Exception $e) {
                            $diag['schema_check_error'] = $e->getMessage();
                        }
                        ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                <div style="padding:12px;">
                                    <strong>No teachers found.</strong>
                                    <div style="margin-top:8px;font-size:0.95rem;color:#444;">
                                        <div>Teachers table count: <?php echo htmlspecialchars((string)$diag['teachers_count']); ?></div>
                                        <div>teachers.Faculty_ID_Number: <?php echo htmlspecialchars($diag['teachers_has_faculty_id_number'] ?? 'unknown'); ?></div>
                                        <div>teachers.employee_number: <?php echo htmlspecialchars($diag['teachers_has_employee_number'] ?? 'unknown'); ?></div>
                                        <div>teachers.employee_id: <?php echo htmlspecialchars($diag['teachers_has_employee_id'] ?? 'unknown'); ?></div>
                                        <div>teacher_attendance.Faculty_ID_Number: <?php echo htmlspecialchars($diag['attendance_has_faculty_id_number'] ?? 'unknown'); ?></div>
                                        <div>teacher_attendance.employee_number: <?php echo htmlspecialchars($diag['attendance_has_employee_number'] ?? 'unknown'); ?></div>
                                        <div>teacher_attendance.employee_id: <?php echo htmlspecialchars($diag['attendance_has_employee_id'] ?? 'unknown'); ?></div>
                                        <div>Resolved teachers identifier: <?php echo htmlspecialchars($diag['resolved_teacher_id_column'] ?? 'unknown'); ?></div>
                                        <div>Resolved attendance identifier: <?php echo htmlspecialchars($diag['resolved_attendance_id_column'] ?? 'unknown'); ?></div>
                                    </div>
                                    <div style="margin-top:8px;color:#666;font-size:0.9rem;">If this looks wrong, verify the teachers identifier column (e.g., <code>Faculty_ID_Number</code>) and ensure teacher attendance uses a matching identifier value.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($teachers as $teacher): ?>
                            <?php
                                $rowDisplayId = 'N/A';
                                if ($teacherIdentifierColumn !== null && isset($teacher[$teacherIdentifierColumn]) && $teacher[$teacherIdentifierColumn] !== '') {
                                    $rowDisplayId = $teacher[$teacherIdentifierColumn];
                                } elseif (isset($teacher['employee_number']) && $teacher['employee_number'] !== '') {
                                    $rowDisplayId = $teacher['employee_number'];
                                } elseif (isset($teacher['employee_id']) && $teacher['employee_id'] !== '') {
                                    $rowDisplayId = $teacher['employee_id'];
                                } elseif (isset($teacher['Faculty_ID_Number']) && $teacher['Faculty_ID_Number'] !== '') {
                                    $rowDisplayId = $teacher['Faculty_ID_Number'];
                                }
                                $fullName = $teacher['first_name'];
                                if (!empty($teacher['middle_name'])) {
                                    $fullName .= ' ' . substr($teacher['middle_name'], 0, 1) . '.';
                                }
                                $fullName .= ' ' . $teacher['last_name'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string)$rowDisplayId); ?></strong></td>
                                
                                <td>
                                    <?php 
                                    $fullName = $teacher['first_name'];
                                    if (!empty($teacher['middle_name'])) {
                                        $fullName .= ' ' . substr($teacher['middle_name'], 0, 1) . '.';
                                    }
                                    $fullName .= ' ' . $teacher['last_name'];
                                    echo htmlspecialchars($fullName);
                                    ?>
                                </td>
                                <td><?php echo $teacher['sex']; ?></td>
                                <td><?php echo htmlspecialchars($teacher['department'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['position'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($teacher['shift'] ?? 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars($teacher['mobile_number'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($teacher['last_attendance']): ?>
                                        <?php echo date('M d, Y', strtotime($teacher['last_attendance'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="manage_teachers.php?id=<?php echo $teacher['id']; ?>" 
                                       class="btn btn-sm btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                            <button type="button" class="btn btn-sm btn-info" 
                                                onclick='showQRCode(<?php echo json_encode($rowDisplayId); ?>, <?php echo json_encode($fullName); ?>, <?php echo json_encode($teacher['qr_code'] ?? ''); ?>)'
                                                title="View QR Code">
                                        <i class="fas fa-qrcode"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this teacher? This will also delete all attendance records.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- QR Code Display -->
    <?php if ($editMode && $editTeacher && !empty($editTeacher['qr_code'])): ?>
    <?php
        // Safe display values to avoid undefined index warnings
        $qrPathRel = '../' . ($editTeacher['qr_code'] ?? '');
        $displayId = 'N/A';
        if ($teacherIdentifierColumn !== null && isset($editTeacher[$teacherIdentifierColumn]) && $editTeacher[$teacherIdentifierColumn] !== '') {
            $displayId = $editTeacher[$teacherIdentifierColumn];
        } elseif (isset($editTeacher['employee_number']) && $editTeacher['employee_number'] !== '') {
            $displayId = $editTeacher['employee_number'];
        } elseif (isset($editTeacher['employee_id']) && $editTeacher['employee_id'] !== '') {
            $displayId = $editTeacher['employee_id'];
        } elseif (isset($editTeacher['Faculty_ID_Number']) && $editTeacher['Faculty_ID_Number'] !== '') {
            $displayId = $editTeacher['Faculty_ID_Number'];
        }
        $fullName = trim((string)($editTeacher['first_name'] ?? '') . ' ' . (string)($editTeacher['last_name'] ?? ''));
    ?>
    <div class="form-card">
        <div class="form-card-header">
            <h2 class="form-card-title">
                <i class="fas fa-qrcode"></i>
                Teacher QR Code
            </h2>
        </div>
        <div class="form-card-body" style="text-align: center;">
            <img src="<?php echo htmlspecialchars($qrPathRel); ?>" 
                 alt="QR Code for <?php echo htmlspecialchars($displayId); ?>"
                 class="qr-code-image">
            <p class="qr-code-label">
                <strong><?php echo htmlspecialchars($fullName); ?></strong><br>
                <?php echo htmlspecialchars($displayId); ?>
            </p>
            <div class="qr-actions">
                <a href="<?php echo htmlspecialchars($qrPathRel); ?>" 
                   download="<?php echo 'QR_' . htmlspecialchars($displayId); ?>.png" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download QR Code
                </a>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="regenerate_qr">
                    <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($editTeacher['id'] ?? ''); ?>">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-sync"></i> Regenerate QR
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="modal-overlay" style="display:none;">
    <div class="modal-container">
        <span class="close" onclick="closeQRModal()">&times;</span>
        <h2 id="qrModalTitle">Teacher QR Code</h2>
        <div class="qr-modal-body">
            <img id="qrModalImage" src="" alt="QR Code" class="qr-code-image">
            <p id="qrModalName" class="qr-code-label"></p>
            <p id="qrModalId" class="qr-code-id"></p>
        </div>
        <div class="modal-actions">
            <a id="qrDownloadLink" href="" download="" class="btn btn-primary">
                <i class="fas fa-download"></i> Download
            </a>
        </div>
    </div>
</div>

<style>
/* ============================================
   PAGE LAYOUT - FIX HEADER OVERLAP
   ============================================ */
.content-wrapper {
    margin-top: var(--space-6);
    padding: 0;
}

.alert {
    margin-top: var(--space-6);
}

.alert:first-of-type {
    margin-top: var(--space-4);
}

/* ============================================
   FORM CARD STYLES
   ============================================ */
.form-card {
    background: #fff;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    margin-bottom: var(--space-6);
    overflow: hidden;
    border: 1px solid var(--neutral-200);
}

.form-card-header {
    padding: var(--space-5) var(--space-6);
    background: linear-gradient(135deg, var(--asj-green-50), var(--neutral-50));
    border-bottom: 1px solid var(--neutral-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-3);
}

.form-card-title {
    margin: 0;
    font-size: var(--text-lg);
    font-weight: 600;
    color: var(--asj-green-700);
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.form-card-title i {
    color: var(--asj-green-500);
}

.form-card-body {
    padding: var(--space-6);
}

/* ============================================
   FORM ELEMENTS - ORIGINAL APPROVED DESIGN
   ============================================ */
.form-grid {
    display: grid;
    gap: var(--space-5);
}

.form-grid.two-col {
    grid-template-columns: repeat(2, 1fr);
}

@media (max-width: 768px) {
    .form-grid.two-col {
        grid-template-columns: 1fr;
    }
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.form-group label {
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.form-group .required {
    color: var(--danger-500);
    margin-left: var(--space-1);
}

.form-input,
.form-select {
    width: 100%;
    padding: var(--space-3) var(--space-4);
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-lg);
    font-size: 0.9375rem;
    color: var(--gray-900);
    background: white;
    transition: var(--transition-base);
}

.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: var(--asj-green-500);
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.form-input::placeholder {
    color: var(--gray-400);
}

.form-input:hover,
.form-select:hover {
    border-color: var(--gray-300);
}

.form-actions {
    display: flex;
    gap: var(--space-3);
    margin-top: var(--space-6);
    padding-top: var(--space-5);
    border-top: 1px solid var(--neutral-200);
    flex-wrap: wrap;
}

/* ============================================
   BUTTON STYLES
   ============================================ */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-5);
    border-radius: var(--radius-lg);
    font-weight: 600;
    font-size: var(--text-sm);
    cursor: pointer;
    transition: var(--transition-base);
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
    background: var(--neutral-100);
    color: var(--neutral-700);
    border: 1px solid var(--neutral-300);
}

.btn-secondary:hover {
    background: var(--neutral-200);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-500), var(--danger-600));
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, var(--danger-600), var(--danger-700));
    transform: translateY(-1px);
}

.btn-success {
    background: linear-gradient(135deg, var(--success-500), var(--success-600));
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, var(--success-600), var(--success-700));
}

/* ============================================
   TABLE STYLING
   ============================================ */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--text-sm);
}

.data-table thead {
    background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
}

.data-table th {
    padding: var(--space-4);
    text-align: left;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
    font-size: var(--text-xs);
    letter-spacing: 0.05em;
}

.data-table td {
    padding: var(--space-4);
    border-bottom: 1px solid var(--neutral-100);
    color: var(--neutral-700);
    vertical-align: middle;
}

.data-table tbody tr:hover {
    background: var(--asj-green-50);
}

.data-table .actions {
    white-space: nowrap;
    display: flex;
    gap: var(--space-2);
}

.btn-sm {
    padding: var(--space-2) var(--space-3);
    font-size: var(--text-xs);
}

.btn-info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    transform: translateY(-1px);
}

.text-center {
    text-align: center;
}

.text-muted {
    color: var(--neutral-400);
    font-style: italic;
}

/* ============================================
   CARD ACTIONS (Search box in header)
   ============================================ */
.card-actions .form-input {
    padding: var(--space-2) var(--space-3);
    font-size: var(--text-sm);
}

/* ============================================
   MODAL STYLING
   ============================================ */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-container {
    background-color: white;
    padding: var(--space-8);
    border-radius: var(--radius-2xl);
    max-width: 400px;
    width: 90%;
    text-align: center;
    position: relative;
    box-shadow: var(--shadow-2xl);
}

.modal-container .close {
    position: absolute;
    right: var(--space-4);
    top: var(--space-3);
    font-size: 1.75rem;
    font-weight: bold;
    cursor: pointer;
    color: var(--neutral-400);
    transition: color var(--transition-base);
    line-height: 1;
}

.modal-container .close:hover {
    color: var(--neutral-700);
}

.modal-container h2 {
    color: var(--asj-green-700);
    margin-bottom: var(--space-4);
    font-size: var(--text-xl);
}

.qr-modal-body {
    padding: var(--space-6) 0;
}

.qr-code-image {
    max-width: 200px;
    margin: 0 auto;
    display: block;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
}

.qr-code-label {
    margin-top: var(--space-4);
    font-weight: 600;
    font-size: var(--text-lg);
    color: var(--neutral-900);
}

.qr-code-id {
    color: var(--neutral-500);
    margin-top: var(--space-1);
    font-size: var(--text-sm);
}

.qr-actions {
    margin-top: var(--space-6);
    display: flex;
    gap: var(--space-3);
    justify-content: center;
    flex-wrap: wrap;
}

.modal-actions {
    margin-top: var(--space-6);
}

/* ============================================
   ALERT STYLES
   ============================================ */
.alert {
    display: flex;
    align-items: flex-start;
    gap: var(--space-4);
    padding: var(--space-4) var(--space-5);
    border-radius: var(--radius-xl);
    margin-bottom: var(--space-5);
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-lg);
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
    margin-bottom: var(--space-1);
}

.alert-success {
    background: var(--success-50);
    border: 1px solid var(--success-500);
}

.alert-success .alert-icon {
    background: var(--success-500);
    color: white;
}

.alert-error {
    background: var(--danger-50);
    border: 1px solid var(--danger-500);
}

.alert-error .alert-icon {
    background: var(--danger-500);
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

/* ============================================
   CARD ACTIONS - Search box
   ============================================ */
.card-actions {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.card-actions .form-input {
    padding: var(--space-2) var(--space-4);
    font-size: var(--text-sm);
    min-width: 200px;
}

@media (max-width: 768px) {
    .card-actions .form-input {
        min-width: 150px;
    }
    
    .form-card-header {
        flex-direction: column;
        align-items: stretch;
    }
}

/* ============================================
   RESPONSIVE ADJUSTMENTS
   ============================================ */
@media (max-width: 576px) {
    .form-card-body {
        padding: var(--space-4);
    }
    
    .form-card-header {
        padding: var(--space-4);
    }
    
    .data-table th,
    .data-table td {
        padding: var(--space-3);
        font-size: var(--text-xs);
    }
    
    .btn-sm {
        padding: var(--space-1) var(--space-2);
    }
}
</style>

<script>
function showAddForm() {
    document.getElementById('teacherForm').style.display = 'block';
    document.getElementById('employee_number').focus();
}

function hideAddForm() {
    document.getElementById('teacherForm').style.display = 'none';
}

function showQRCode(employeeId, name, qrPath) {
    document.getElementById('qrModalTitle').textContent = 'Teacher QR Code';
    document.getElementById('qrModalImage').src = '../' + qrPath;
    document.getElementById('qrModalName').textContent = name;
    document.getElementById('qrModalId').textContent = employeeId;
    document.getElementById('qrDownloadLink').href = '../' + qrPath;
    document.getElementById('qrDownloadLink').download = 'QR_' + employeeId + '.png';
    document.getElementById('qrModal').style.display = 'flex';
}

function closeQRModal() {
    document.getElementById('qrModal').style.display = 'none';
}

// Search functionality
document.getElementById('searchTeachers')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#teachersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('qrModal');
    if (event.target === modal) {
        closeQRModal();
    }
}
</script>

<?php include 'includes/footer_modern.php'; ?>
