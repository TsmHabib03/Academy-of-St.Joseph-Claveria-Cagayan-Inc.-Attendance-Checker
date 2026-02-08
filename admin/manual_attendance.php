<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Manual Attendance';
$pageIcon = 'clipboard-check';

// Add external CSS - matching manage_sections design
$additionalCSS = ['../css/manual-attendance-modern.css'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        $action = $_POST['action'] ?? '';
        $response = ['success' => false, 'message' => ''];
        
        switch ($action) {
            case 'mark_attendance':
                $identifier = trim($_POST['lrn'] ?? '');
                $date = trim($_POST['date'] ?? '');
                $time = trim($_POST['time'] ?? '');
                $action_type = $_POST['action_type'] ?? 'time_in';

                if (empty($identifier) || empty($date) || empty($time)) {
                    throw new Exception('All fields are required.');
                }

                // Student LRN (11-13 digits)
                if (preg_match('/^\d{11,13}$/', $identifier)) {
                    $student_stmt = $pdo->prepare("SELECT lrn, first_name, last_name, class as section FROM students WHERE lrn = ?");
                    $student_stmt->execute([$identifier]);
                    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$student) {
                        throw new Exception('Student with this LRN was not found.');
                    }

                    $student_name = $student['first_name'] . ' ' . $student['last_name'];

                    if ($action_type === 'time_in') {
                            // Determine session and preferred columns (respect section shift if defined)
                            $current_time = date('H:i:s', strtotime($time));
                            $t = strtotime($current_time);

                            // Lookup schedule for student's section
                            $student_section_name = $student['section'] ?? '';
                            $student_grade = $student['class'] ?? '';
                            try {
                                $sched_sql = "SELECT s.* FROM attendance_schedules s LEFT JOIN sections sec ON s.section_id = sec.id
                                              WHERE s.is_active = 1 AND (
                                                  (sec.section_name = :section_name AND s.section_id IS NOT NULL)
                                                  OR (s.grade_level = :grade_level AND s.grade_level IS NOT NULL)
                                                  OR s.is_default = 1
                                              ) ORDER BY (sec.section_name = :section_name) DESC, (s.grade_level = :grade_level) DESC, s.is_default DESC LIMIT 1";
                                $sched_stmt = $pdo->prepare($sched_sql);
                                $sched_stmt->bindParam(':section_name', $student_section_name, PDO::PARAM_STR);
                                $sched_stmt->bindParam(':grade_level', $student_grade, PDO::PARAM_STR);
                                $sched_stmt->execute();
                                $schedule = $sched_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                            } catch (Exception $e) {
                                $schedule = null;
                            }
                            if (!$schedule) {
                                $schedule = [
                                    'morning_start' => '06:00:00',
                                    'morning_end' => '11:59:00',
                                    'morning_late_after' => '07:30:00',
                                    'afternoon_start' => '12:00:00',
                                    'afternoon_end' => '18:00:00',
                                    'afternoon_late_after' => '13:30:00'
                                ];
                            }

                            // Normalize schedule times to H:i:s and fallback on parse errors
                            $defaults = [
                                'morning_start' => '06:00:00',
                                'morning_end' => '11:59:00',
                                'morning_late_after' => '07:30:00',
                                'afternoon_start' => '12:00:00',
                                'afternoon_end' => '18:00:00',
                                'afternoon_late_after' => '13:30:00'
                            ];
                            $keys = array_keys($defaults);
                            foreach ($keys as $k) {
                                $v = $schedule[$k] ?? '';
                                if (empty($v)) { $schedule[$k] = $defaults[$k]; continue; }
                                $parsed = false;
                                $fmts = ['H:i:s','H:i','g:i A','g:i a'];
                                foreach ($fmts as $f) {
                                    $dt = DateTime::createFromFormat($f, $v);
                                    if ($dt && $dt->format($f) === $v) { $schedule[$k] = $dt->format('H:i:s'); $parsed = true; break; }
                                }
                                if (!$parsed) {
                                    try { $dt = new DateTime($v); $schedule[$k] = $dt->format('H:i:s'); }
                                    catch (Exception $e) { $schedule[$k] = $defaults[$k]; }
                                }
                            }

                            // Check section-level shift assignment
                            $assignedSession = null;
                            try {
                                $secCol = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sections' AND COLUMN_NAME = 'shift'");
                                $secCol->execute();
                                if ($secCol->fetchColumn()) {
                                    $secStmt = $pdo->prepare("SELECT shift FROM sections WHERE section_name = ? LIMIT 1");
                                    $secStmt->execute([$student_section_name]);
                                    $shiftVal = $secStmt->fetchColumn();
                                    if ($shiftVal) {
                                        $sv = strtolower(trim($shiftVal));
                                        if (strpos($sv, 'am') !== false) { $assignedSession = 'morning'; }
                                        elseif (strpos($sv, 'pm') !== false) { $assignedSession = 'afternoon'; }
                                    }
                                }
                            } catch (Exception $e) { /* ignore */ }

                            $morning_late = strtotime($schedule['morning_late_after']);
                            $afternoon_late = strtotime($schedule['afternoon_late_after']);

                            if ($assignedSession === 'morning') {
                                $preferred_in = 'morning_time_in';
                                $preferred_out = 'morning_time_out';
                                $is_late = ($t > $morning_late);
                            } elseif ($assignedSession === 'afternoon') {
                                $preferred_in = 'afternoon_time_in';
                                $preferred_out = 'afternoon_time_out';
                                $is_late = ($t > $afternoon_late);
                            } else {
                                // Fallback: determine by time
                                $morning_start = strtotime($schedule['morning_start']);
                                $morning_end = strtotime($schedule['morning_end']);
                                $afternoon_start = strtotime($schedule['afternoon_start']);
                                $afternoon_end = strtotime($schedule['afternoon_end']);
                                if ($t >= $morning_start && $t <= $morning_end) {
                                    $preferred_in = 'morning_time_in';
                                    $preferred_out = 'morning_time_out';
                                    $is_late = ($t > $morning_late);
                                } else {
                                    $preferred_in = 'afternoon_time_in';
                                    $preferred_out = 'afternoon_time_out';
                                    $is_late = ($t > $afternoon_late);
                                }
                            }

                            // Determine which attendance columns exist and pick fallback
                            $colCheckStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance'");
                            $colCheckStmt->execute();
                            $attendanceCols = $colCheckStmt->fetchAll(PDO::FETCH_COLUMN);
                            $in_col = in_array($preferred_in, $attendanceCols) ? $preferred_in : (in_array('time_in', $attendanceCols) ? 'time_in' : null);
                            if ($in_col === null) { throw new Exception('Attendance table missing expected time_in columns.'); }

                            // Insert using the chosen column; make idempotent
                            if ($in_col === 'time_in') {
                                $stmt = $pdo->prepare(
                                    "INSERT INTO attendance (lrn, date, time_in, section, status) 
                                     VALUES (?, ?, ?, ?, 'time_in')
                                     ON DUPLICATE KEY UPDATE 
                                     time_in = VALUES(time_in), status = 'time_in', updated_at = NOW()"
                                );
                                $result = $stmt->execute([$identifier, $date, $time, $student['section']]);
                            } else {
                                $ins_sql = "INSERT INTO attendance (lrn, section, date, {$in_col}, status, created_at) VALUES (?, ?, ?, ?, 'time_in', NOW()) ON DUPLICATE KEY UPDATE {$in_col} = VALUES({$in_col}), status = VALUES(status), updated_at = NOW()";
                                $stmt = $pdo->prepare($ins_sql);
                                $result = $stmt->execute([$identifier, $student['section'] ?? '', $date, $time]);
                            }

                        if ($result) {
                            $response = [
                                'success' => true,
                                'message' => "Time In marked for {$student_name} at " . date('h:i A', strtotime($time)),
                                'student_name' => $student_name,
                                'time' => date('h:i A', strtotime($time))
                            ];
                            logAdminActivity('MANUAL_ATTENDANCE', "Marked time_in for LRN: $identifier on $date at $time");
                        }
                    } else {
                        // Determine out column similarly
                        $current_time = date('H:i:s', strtotime($time));
                        $t = strtotime($current_time);
                        // reuse $preferred_out from above if set; otherwise recompute
                        if (!isset($preferred_out)) {
                            // quick schedule lookup
                            $preferred_out = 'time_out';
                        }
                        $colCheckStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance'");
                        $colCheckStmt->execute();
                        $attendanceCols = $colCheckStmt->fetchAll(PDO::FETCH_COLUMN);
                        $out_col = in_array($preferred_out, $attendanceCols) ? $preferred_out : (in_array('time_out', $attendanceCols) ? 'time_out' : null);
                        if ($out_col === null) { throw new Exception('Attendance table missing expected time_out columns.'); }

                        if ($out_col === 'time_out') {
                            $stmt = $pdo->prepare("UPDATE attendance SET time_out = ?, status = 'time_out', updated_at = NOW() WHERE lrn = ? AND date = ? AND time_in IS NOT NULL");
                            $stmt->execute([$time, $identifier, $date]);
                        } else {
                            // Ensure the related in column exists and is set
                            $in_col = $in_col ?? (in_array('morning_time_in', $attendanceCols) ? 'morning_time_in' : (in_array('afternoon_time_in', $attendanceCols) ? 'afternoon_time_in' : (in_array('time_in', $attendanceCols) ? 'time_in' : null)));
                            if ($in_col === null) { throw new Exception('Missing in column to validate Time Out'); }
                            $upd = $pdo->prepare("UPDATE attendance SET {$out_col} = ?, status = 'time_out', updated_at = NOW() WHERE lrn = ? AND date = ? AND {$in_col} IS NOT NULL");
                            $upd->execute([$time, $identifier, $date]);
                            $stmt = $upd;
                        }

                        if ($stmt->rowCount() > 0) {
                            $response = [
                                'success' => true,
                                'message' => "Time Out marked for {$student_name} at " . date('h:i A', strtotime($time)),
                                'student_name' => $student_name,
                                'time' => date('h:i A', strtotime($time))
                            ];
                            logAdminActivity('MANUAL_ATTENDANCE', "Marked time_out for LRN: $identifier on $date at $time");
                        } else {
                            throw new Exception("No 'Time In' record found for this student on the selected date. Cannot mark Time Out.");
                        }
                    }

                // Teacher employee number (7 digits) - apply schedule/session logic
                } elseif (preg_match('/^\d{7}$/', $identifier)) {
                    // Check which identifier columns exist in `teachers` table to avoid SQL errors
                    $colCheckStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers'");
                    $colCheckStmt->execute();
                    $teacherCols = $colCheckStmt->fetchAll(PDO::FETCH_COLUMN);

                    $hasEmpNum = in_array('employee_number', $teacherCols);
                    $hasEmpId = in_array('employee_id', $teacherCols);

                    if ($hasEmpNum && $hasEmpId) {
                        $teacher_stmt = $pdo->prepare("SELECT id, employee_number, employee_id, first_name, last_name, department FROM teachers WHERE employee_number = ? OR employee_id = ? LIMIT 1");
                        $teacher_stmt->execute([$identifier, $identifier]);
                    } elseif ($hasEmpNum) {
                        $teacher_stmt = $pdo->prepare("SELECT id, employee_number, first_name, last_name, department FROM teachers WHERE employee_number = ? LIMIT 1");
                        $teacher_stmt->execute([$identifier]);
                    } elseif ($hasEmpId) {
                        $teacher_stmt = $pdo->prepare("SELECT id, employee_id, first_name, last_name, department FROM teachers WHERE employee_id = ? LIMIT 1");
                        $teacher_stmt->execute([$identifier]);
                    } else {
                        throw new Exception('Teachers table missing identifier columns. Run migrations.');
                    }

                    $teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$teacher) {
                        throw new Exception('Teacher with this Employee Number was not found.');
                    }

                    $teacher_name = trim($teacher['first_name'] . ' ' . $teacher['last_name']);

                    // Normalize time
                    $current_time = date('H:i:s', strtotime($time));
                    $t = strtotime($current_time);

                    // Lookup schedule (reuse attendance_schedules rules)
                    $student_section_name = $teacher['department'] ?? '';
                    $student_grade = '';
                    try {
                        $sched_sql = "SELECT s.* FROM attendance_schedules s LEFT JOIN sections sec ON s.section_id = sec.id
                                      WHERE s.is_active = 1 AND (
                                          (sec.section_name = :section_name AND s.section_id IS NOT NULL)
                                          OR (s.grade_level = :grade_level AND s.grade_level IS NOT NULL)
                                          OR s.is_default = 1
                                      )
                                      ORDER BY (sec.section_name = :section_name) DESC, (s.grade_level = :grade_level) DESC, s.is_default DESC
                                      LIMIT 1";
                        $sched_stmt = $pdo->prepare($sched_sql);
                        $sched_stmt->bindParam(':section_name', $student_section_name, PDO::PARAM_STR);
                        $sched_stmt->bindParam(':grade_level', $student_grade, PDO::PARAM_STR);
                        $sched_stmt->execute();
                        $schedule = $sched_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    } catch (Exception $e) {
                        $schedule = null;
                    }

                    if (!$schedule) {
                        $schedule = [
                            'morning_start' => '06:00:00',
                            'morning_end' => '11:59:00',
                            'morning_late_after' => '07:30:00',
                            'afternoon_start' => '12:00:00',
                            'afternoon_end' => '18:00:00',
                            'afternoon_late_after' => '13:30:00'
                        ];
                    }

                    $morning_start = strtotime($schedule['morning_start']);
                    $morning_end = strtotime($schedule['morning_end']);
                    $morning_late = strtotime($schedule['morning_late_after']);
                    $afternoon_start = strtotime($schedule['afternoon_start']);
                    $afternoon_end = strtotime($schedule['afternoon_end']);
                    $afternoon_late = strtotime($schedule['afternoon_late_after']);

                    // Respect teacher-assigned shift when present
                    $assignedSession = null;
                    if (isset($teacher['shift']) && $teacher['shift'] !== '') {
                        $sv = strtolower(trim($teacher['shift']));
                        if (strpos($sv, 'am') !== false) { $assignedSession = 'morning'; }
                        elseif (strpos($sv, 'pm') !== false) { $assignedSession = 'afternoon'; }
                    }

                    if ($assignedSession === 'morning') {
                        $session = 'morning';
                        $preferred_in = 'morning_time_in';
                        $preferred_out = 'morning_time_out';
                        $is_late = ($t > $morning_late);
                    } elseif ($assignedSession === 'afternoon') {
                        $session = 'afternoon';
                        $preferred_in = 'afternoon_time_in';
                        $preferred_out = 'afternoon_time_out';
                        $is_late = ($t > $afternoon_late);
                    } else {
                        if ($t >= $morning_start && $t <= $morning_end) {
                            $session = 'morning';
                            $preferred_in = 'morning_time_in';
                            $preferred_out = 'morning_time_out';
                            $is_late = ($t > $morning_late);
                        } elseif ($t >= $afternoon_start && $t <= $afternoon_end) {
                            $session = 'afternoon';
                            $preferred_in = 'afternoon_time_in';
                            $preferred_out = 'afternoon_time_out';
                            $is_late = ($t > $afternoon_late);
                        } else {
                            $session = 'morning';
                            $preferred_in = 'morning_time_in';
                            $preferred_out = 'morning_time_out';
                            $is_late = ($t > $morning_late);
                        }
                    }

                    // Inspect teacher_attendance columns
                    $colCheckStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_attendance'");
                    $colCheckStmt->execute();
                    $attendanceCols = $colCheckStmt->fetchAll(PDO::FETCH_COLUMN);

                    $idColumn = in_array('employee_number', $attendanceCols) ? 'employee_number' : (in_array('employee_id', $attendanceCols) ? 'employee_id' : null);
                    if ($idColumn === null) {
                        throw new Exception('teacher_attendance table is missing identifier columns. Run migrations.');
                    }

                    $in_col = in_array($preferred_in, $attendanceCols) ? $preferred_in : (in_array('time_in', $attendanceCols) ? 'time_in' : null);
                    $out_col = in_array($preferred_out, $attendanceCols) ? $preferred_out : (in_array('time_out', $attendanceCols) ? 'time_out' : null);
                    if ($in_col === null || $out_col === null) {
                        throw new Exception('Attendance table missing expected time columns.');
                    }

                    $pdo->beginTransaction();
                    try {
                        if ($idColumn === 'employee_number') {
                            $check_query = "SELECT * FROM teacher_attendance WHERE employee_number = :code AND date = :date FOR UPDATE";
                        } else {
                            $check_query = "SELECT * FROM teacher_attendance WHERE employee_id = :code AND date = :date FOR UPDATE";
                        }
                        $check_stmt = $pdo->prepare($check_query);
                        $check_stmt->execute([':code' => $identifier, ':date' => $date]);
                        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$existing) {
                            $status = $is_late ? 'late' : 'present';
                            if ($idColumn === 'employee_number') {
                                $ins = $pdo->prepare("INSERT INTO teacher_attendance (employee_number, department, date, {$in_col}, status, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE {$in_col} = VALUES({$in_col}), status = VALUES(status), updated_at = NOW()");
                                $ins->execute([$teacher['employee_number'] ?? $identifier, $teacher['department'] ?? '', $date, $current_time, $status]);
                            } else {
                                $ins = $pdo->prepare("INSERT INTO teacher_attendance (employee_id, department, date, {$in_col}, status, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE {$in_col} = VALUES({$in_col}), status = VALUES(status), updated_at = NOW()");
                                $ins->execute([$teacher['employee_id'] ?? $identifier, $teacher['department'] ?? '', $date, $current_time, $status]);
                            }
                            $pdo->commit();
                            $response = ['success' => true, 'message' => "Time In marked for Teacher {$teacher_name} at " . date('h:i A', strtotime($current_time)), 'teacher_name' => $teacher_name, 'time' => date('h:i A', strtotime($current_time))];
                            logAdminActivity('MANUAL_ATTENDANCE', "Marked teacher time_in for: $identifier ({$teacher_name}) on $date at $current_time");

                        } else {
                            $existing_in = $existing[$in_col] ?? null;
                            $existing_out = $existing[$out_col] ?? null;

                            if ($existing_in === null) {
                                $status = $is_late ? 'late' : 'present';
                                $upd = $pdo->prepare("UPDATE teacher_attendance SET {$in_col} = ?, status = ?, updated_at = NOW() WHERE id = ?");
                                $upd->execute([$current_time, $status, $existing['id']]);
                                $pdo->commit();
                                $response = ['success' => true, 'message' => "Time In recorded for Teacher {$teacher_name} at " . date('h:i A', strtotime($current_time)), 'teacher_name' => $teacher_name, 'time' => date('h:i A', strtotime($current_time))];
                                logAdminActivity('MANUAL_ATTENDANCE', "Updated teacher time_in for: $identifier ({$teacher_name}) on $date at $current_time");

                            } elseif ($existing_in !== null && $existing_out === null) {
                                $upd = $pdo->prepare("UPDATE teacher_attendance SET {$out_col} = ?, status = 'time_out', updated_at = NOW() WHERE id = ?");
                                $upd->execute([$current_time, $existing['id']]);
                                $pdo->commit();
                                $response = ['success' => true, 'message' => "Time Out recorded for Teacher {$teacher_name} at " . date('h:i A', strtotime($current_time)), 'teacher_name' => $teacher_name, 'time' => date('h:i A', strtotime($current_time))];
                                logAdminActivity('MANUAL_ATTENDANCE', "Marked teacher time_out for: $identifier ({$teacher_name}) on $date at $current_time");

                            } else {
                                $pdo->commit();
                                throw new Exception('Attendance already completed for this teacher today.');
                            }
                        }
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) { $pdo->rollBack(); }
                        throw $e;
                    }

                } else {
                    throw new Exception('Invalid identifier format. Enter 11-13 digit LRN or 7-digit Employee Number.');
                }
                break;
                
            case 'bulk_mark':
                $lrns = trim($_POST['bulk_lrns'] ?? '');
                $date = trim($_POST['bulk_date'] ?? '');
                $time = trim($_POST['bulk_time'] ?? '');
                
                if (empty($lrns) || empty($date) || empty($time)) {
                    throw new Exception('All fields are required for bulk attendance.');
                }
                
                $lrnList = array_filter(array_map('trim', explode("\n", $lrns)));
                $successCount = 0;
                $errorCount = 0;
                $errors = [];
                
                foreach ($lrnList as $lrn) {
                    if (!preg_match('/^\d{11,13}$/', $lrn)) {
                        $errors[] = "Invalid LRN: $lrn";
                        $errorCount++;
                        continue;
                    }
                    
                    try {
                        $student_stmt = $pdo->prepare("SELECT lrn, first_name, last_name, class as section FROM students WHERE lrn = ?");
                        $student_stmt->execute([$lrn]);
                        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($student) {
                            $stmt = $pdo->prepare(
                                "INSERT INTO attendance (lrn, date, time_in, section, status) 
                                 VALUES (?, ?, ?, ?, 'time_in')
                                 ON DUPLICATE KEY UPDATE 
                                 time_in = VALUES(time_in), status = 'time_in', updated_at = NOW()"
                            );
                            $stmt->execute([$lrn, $date, $time, $student['section']]);
                            $successCount++;
                        } else {
                            $errors[] = "Not found: $lrn";
                            $errorCount++;
                        }
                    } catch (Exception $e) {
                        $errors[] = "Error: $lrn";
                        $errorCount++;
                    }
                }
                
                $response = [
                    'success' => $successCount > 0,
                    'message' => "Bulk attendance: $successCount successful, $errorCount errors.",
                    'successCount' => $successCount,
                    'errorCount' => $errorCount,
                    'errors' => array_slice($errors, 0, 5)
                ];
                
                logAdminActivity('BULK_ATTENDANCE', "Bulk marked attendance: $successCount successful, $errorCount errors");
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Get today's date and current time for defaults
$today = date('Y-m-d');
$currentTime = date('H:i');

// Get students for quick selection
try {
    $stmt = $pdo->prepare("
        SELECT lrn, first_name, last_name, class 
        FROM students 
        ORDER BY class, last_name, first_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current day's schedule for time suggestions
    $currentDay = date('l'); // Full day name (Monday, Tuesday, etc.)
    $stmt = $pdo->prepare("
        SELECT DISTINCT start_time, end_time, subject, class, period_number
        FROM schedule 
        WHERE day_of_week = ? 
        ORDER BY start_time
    ");
    $stmt->execute([$currentDay]);
    $todaysSchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent attendance for reference
    $stmt = $pdo->prepare("
        SELECT a.lrn, s.first_name, s.last_name, s.class, a.subject, a.status, a.time, a.date,
               a.created_at
        FROM attendance a 
        JOIN students s ON a.lrn = s.lrn 
        WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
        ORDER BY a.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Manual attendance query error: " . $e->getMessage());
    $students = [];
    $todaysSchedule = [];
    $recentAttendance = [];
}

// Include the modern admin header
include 'includes/header_modern.php';
?>

<!-- External CSS loaded via $additionalCSS array -->

<!-- Page Header - Enhanced Design (Matching manage_sections.php) -->
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
                    <i class="fas fa-clock"></i>
                    <span>Mark time in/out manually or scan QR codes for fast check-in</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <button class="btn-header btn-header-secondary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
            <a href="view_students.php" class="btn-header btn-header-primary">
                <i class="fas fa-users"></i>
                <span>View Students</span>
            </a>
        </div>
    </div>
</div>

<!-- Info Alert -->
<div class="alert alert-info">
    <div class="alert-icon">
        <i class="fas fa-info-circle"></i>
    </div>
    <div class="alert-content">
        <strong>Manual Attendance System</strong>
        <p style="margin: var(--space-1) 0 0; line-height: 1.6;">
            Use this feature to mark Time In and Time Out for students who may have forgotten their QR codes or need retroactive attendance entries. The system now supports separate Time In and Time Out tracking similar to the main scanner.
        </p>
    </div>
</div>

<!-- Modern Tabs -->
<div class="modern-tabs">
    <button class="modern-tab active" data-tab="scanner">
        <i class="fas fa-qrcode"></i>
        <span>QR Scanner</span>
    </button>
    <button class="modern-tab" data-tab="single">
        <i class="fas fa-user-clock"></i>
        <span>Single Entry</span>
    </button>
    <button class="modern-tab" data-tab="bulk">
        <i class="fas fa-users-cog"></i>
        <span>Bulk Entry</span>
    </button>
</div>

<!-- QR Scanner Tab -->
<div id="scanner-tab" class="modern-tab-content active">
    <div class="dashboard-grid">
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">QR Code Scanner</h3>
                        <p class="card-subtitle-modern">
                            <i class="fas fa-camera"></i>
                            <span>Scan student QR codes for instant attendance</span>
                        </p>
                    </div>
                </div>
                <div class="scanner-controls">
                    <button id="start-scan-btn" class="btn btn-primary">
                        <i class="fas fa-play"></i>
                        <span>Start Scanner</span>
                    </button>
                    <button id="stop-scan-btn" class="btn btn-danger" style="display: none;">
                        <i class="fas fa-stop"></i>
                        <span>Stop Scanner</span>
                    </button>
                </div>
            </div>
            <div class="card-body-modern">
                <div class="scanner-container">
                    <div class="scanner-overlay" style="display: none;">
                        <div id="qr-reader-container">
                            <div id="qr-reader"></div>
                        </div>
                    </div>
                    <div id="scanner-status" class="scanner-status">
                        <p><i class="fas fa-qrcode"></i> Click "Start Scanner" to begin scanning QR codes</p>
                    </div>
                    
                    <!-- Performance Stats -->
                    <div id="scanner-stats" class="scanner-stats" style="display: none;">
                        <div class="stat-item">
                            <i class="fas fa-qrcode"></i>
                            <span class="stat-label">Scans</span>
                            <span class="stat-value" id="scan-count">0</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-check-circle"></i>
                            <span class="stat-label">Success</span>
                            <span class="stat-value" id="success-count">0</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-tachometer-alt"></i>
                            <span class="stat-label">Avg Time</span>
                            <span class="stat-value" id="avg-time">0ms</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Today's Attendance</h3>
                        <p class="card-subtitle-modern">
                            <i class="fas fa-calendar-day"></i>
                            <span>View scanned attendance records for today</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <div id="scan-result-container" class="scan-result-container" style="display: none;">
                    <div id="scan-result"></div>
                </div>
                
                <div class="today-attendance-section">
                    <div id="today-attendance-list" class="attendance-list">
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>No attendance yet today</h3>
                            <p>Scanned attendance will appear here</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Single Entry Tab -->
<div id="single-tab" class="modern-tab-content">
    <div class="dashboard-grid">
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Single Entry</h3>
                        <p class="card-subtitle-modern">
                            <i class="fas fa-edit"></i>
                            <span>Mark time in/out for individual students</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <!-- Action Type Selection -->
                <div class="action-type-buttons">
                    <button type="button" class="action-type-btn active" data-action-type="time_in">
                        <div class="action-type-icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <span class="action-type-label">Time In</span>
                        <span class="action-type-desc">Mark arrival</span>
                    </button>
                    <button type="button" class="action-type-btn" data-action-type="time_out">
                        <div class="action-type-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <span class="action-type-label">Time Out</span>
                        <span class="action-type-desc">Mark departure</span>
                    </button>
                </div>
                
                <form id="single-attendance-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="mark_attendance">
                    <input type="hidden" name="action_type" id="action_type" value="time_in">
                    
                    <div class="form-group-modern">
                              <label for="lrn" class="form-label-modern">
                                <i class="fas fa-id-card"></i>
                                <span>LRN or Teacher Employee Number</span>
                                <span class="required">*</span>
                               </label>
                               <input type="text" 
                                   id="lrn" 
                                   name="lrn" 
                                   class="form-input-modern" 
                                   placeholder="Enter 11-13 digit LRN or 7-digit employee number" 
                                   pattern="([0-9]{11,13}|[0-9]{7})"
                                   required>
                               <span class="form-hint">Enter the student's LRN (11-13 digits) or teacher's 7-digit Employee Number</span>
                    </div>
                    
                    <div class="form-grid-modern">
                        <div class="form-group-modern">
                            <label for="date" class="form-label-modern">
                                <i class="fas fa-calendar"></i>
                                <span>Date</span>
                                <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="date" 
                                   name="date" 
                                   class="form-input-modern" 
                                   value="<?php echo $today; ?>" 
                                   required>
                        </div>

                        <div class="form-group-modern">
                            <label for="time" class="form-label-modern">
                                <i class="fas fa-clock"></i>
                                <span>Time</span>
                                <span class="required">*</span>
                            </label>
                            <input type="time" 
                                   id="time" 
                                   name="time" 
                                   class="form-input-modern" 
                                   value="<?php echo $currentTime; ?>" 
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-check-circle"></i>
                        <span id="submit-btn-text"><i class="fas fa-sign-in-alt"></i> Mark Time In</span>
                    </button>
                </form>

<!-- Bulk Entry Tab -->
<div id="bulk-tab" class="modern-tab-content">
    <div class="dashboard-grid">
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Bulk Entry</h3>
                        <p class="card-subtitle-modern">
                            <i class="fas fa-list"></i>
                            <span>Mark time in/out for multiple students at once</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <!-- Action Type Selection for Bulk -->
                <div class="action-type-buttons">
                    <button type="button" class="action-type-btn active" data-bulk-action-type="time_in">
                        <div class="action-type-icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <span class="action-type-label">Bulk Time In</span>
                        <span class="action-type-desc">Mark multiple arrivals</span>
                    </button>
                    <button type="button" class="action-type-btn" data-bulk-action-type="time_out">
                        <div class="action-type-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <span class="action-type-label">Bulk Time Out</span>
                        <span class="action-type-desc">Mark multiple departures</span>
                    </button>
                </div>
                
                <form id="bulk-attendance-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="bulk_mark">
                    <input type="hidden" name="bulk_action_type" id="bulk_action_type" value="time_in">
                    
                    <div class="form-group-modern">
                        <label for="bulk_lrns" class="form-label-modern">
                            <i class="fas fa-list-ol"></i>
                            <span>Student LRNs (One per line)</span>
                            <span class="required">*</span>
                        </label>
                        <textarea 
                            id="bulk_lrns" 
                            name="bulk_lrns" 
                            class="form-textarea-modern" 
                            placeholder="123456789012&#10;234567890123&#10;345678901234"
                            rows="8"
                            required></textarea>
                        <span class="form-hint">Enter one LRN per line (11-13 digits each)</span>
                    </div>
                    
                    <div class="form-grid-modern">
                        <div class="form-group-modern">
                            <label for="bulk_date" class="form-label-modern">
                                <i class="fas fa-calendar"></i>
                                <span>Date</span>
                                <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="bulk_date" 
                                   name="bulk_date" 
                                   class="form-input-modern" 
                                   value="<?php echo $today; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="bulk_time" class="form-label-modern">
                                <i class="fas fa-clock"></i>
                                <span>Time</span>
                                <span class="required">*</span>
                            </label>
                            <input type="time" 
                                   id="bulk_time" 
                                   name="bulk_time" 
                                   class="form-input-modern" 
                                   value="<?php echo $currentTime; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-check-double"></i>
                        <span id="bulk-submit-btn-text">Mark Bulk Time In</span>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fas fa-copy"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Export by Class</h3>
                        <p class="card-subtitle-modern">
                            <i class="fas fa-download"></i>
                            <span>Copy LRNs by class for bulk operations</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <?php
                $studentsByClass = [];
                foreach ($students as $student) {
                    $className = $student['class'] ?? 'Unassigned';
                    if (!isset($studentsByClass[$className])) {
                        $studentsByClass[$className] = [];
                    }
                    $studentsByClass[$className][] = $student;
                }
                ksort($studentsByClass);
                ?>
                
                <div class="class-export-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--space-4);">
                    <?php foreach ($studentsByClass as $className => $classStudents): ?>
                        <div class="class-export-card" style="padding: var(--space-4); background: var(--gray-50); border: 2px solid var(--gray-200); border-radius: var(--radius-lg);">
                            <div class="class-export-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-3);">
                                <h4 class="class-export-title" style="display: flex; align-items: center; gap: var(--space-2); font-size: 0.9375rem; font-weight: 600; color: var(--gray-800); margin: 0;">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo htmlspecialchars($className); ?>
                                </h4>
                                <span class="student-count-badge" style="padding: var(--space-1) var(--space-2); background: var(--primary-100); color: var(--primary-700); border-radius: var(--radius-md); font-size: 0.75rem; font-weight: 600;">
                                    <?php echo count($classStudents); ?> students
                                </span>
                            </div>
                            <textarea 
                                id="class-<?php echo htmlspecialchars($className); ?>" 
                                class="form-textarea-modern" 
                                readonly 
                                rows="5"
                                style="font-family: 'Courier New', monospace; font-size: 0.75rem;"><?php foreach ($classStudents as $student): ?><?php echo $student['lrn'] . "\n"; ?><?php endforeach; ?></textarea>
                            <button 
                                class="btn btn-primary" 
                                data-action="copy-class-lrns" 
                                data-class="<?php echo htmlspecialchars($className); ?>"
                                style="width: 100%; margin-top: var(--space-3);">
                                <i class="fas fa-copy"></i>
                                <span>Copy LRNs</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include ZXing library for QR code scanning -->
<script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>

<!-- JAVASCRIPT SECTION -->
<script>
    /* ===== VARIABLES ===== */
    let codeReader = null;
    let selectedDeviceId = null;
    let isScanning = false;
    let scanCount = 0;
    let successCount = 0;
    let processingTimes = [];
    let isProcessing = false;
    
    /* ===== NOTIFICATION SYSTEM ===== */
    function showNotification(message, type = 'info') {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        
        const notification = document.createElement('div');
        notification.className = 'notification-container';
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.style.cssText = 'min-width: 320px; animation: slideInRight 0.3s ease;';
        
        alert.innerHTML = `
            <div class="alert-icon">
                <i class="fas fa-${icons[type]}"></i>
            </div>
            <div class="alert-content">
                <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong>
                <p style="margin: var(--space-1) 0 0;">${message}</p>
            </div>
        `;
        
        notification.appendChild(alert);
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    /* ===== TAB SWITCHING ===== */
    document.addEventListener('click', function(e) {
        const tab = e.target.closest('.modern-tab');
        if (!tab) return;
        
        const tabName = tab.dataset.tab;
        
        // Remove active from all tabs
        document.querySelectorAll('.modern-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.modern-tab-content').forEach(t => t.classList.remove('active'));
        
        // Add active to clicked tab
        tab.classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
    });
    
    /* ===== ACTION TYPE SELECTION (Single Entry) ===== */
    document.querySelectorAll('.action-type-btn[data-action-type]').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all
            document.querySelectorAll('.action-type-btn[data-action-type]').forEach(b => b.classList.remove('active'));
            
            // Add active to clicked
            this.classList.add('active');
            
            // Update hidden input and button text
            const actionType = this.dataset.actionType;
            document.getElementById('action_type').value = actionType;
            
            const submitBtnText = document.getElementById('submit-btn-text');
            if (actionType === 'time_in') {
                submitBtnText.innerHTML = '<i class="fas fa-sign-in-alt"></i> Mark Time In';
            } else {
                submitBtnText.innerHTML = '<i class="fas fa-sign-out-alt"></i> Mark Time Out';
            }
        });
    });
    
    /* ===== ACTION TYPE SELECTION (Bulk Entry) ===== */
    document.querySelectorAll('.action-type-btn[data-bulk-action-type]').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all
            document.querySelectorAll('.action-type-btn[data-bulk-action-type]').forEach(b => b.classList.remove('active'));
            
            // Add active to clicked
            this.classList.add('active');
            
            // Update hidden input and button text
            const actionType = this.dataset.bulkActionType;
            document.getElementById('bulk_action_type').value = actionType;
            
            const submitBtnText = document.getElementById('bulk-submit-btn-text');
            if (actionType === 'time_in') {
                submitBtnText.textContent = 'Mark Bulk Time In';
            } else {
                submitBtnText.textContent = 'Mark Bulk Time Out';
            }
        });
    });
    
    /* ===== QR SCANNER FUNCTIONS ===== */
    function updateScannerStats() {
        const statsContainer = document.getElementById('scanner-stats');
        if (!statsContainer) return;
        
        if (isScanning) {
            statsContainer.style.display = 'flex';
        }
        
        document.getElementById('scan-count').textContent = scanCount;
        document.getElementById('success-count').textContent = successCount;
        
        if (processingTimes.length > 0) {
            const avgTime = Math.round(processingTimes.reduce((a, b) => a + b, 0) / processingTimes.length);
            document.getElementById('avg-time').textContent = avgTime + 'ms';
        }
    }
    
    function resetScannerStats() {
        scanCount = 0;
        successCount = 0;
        processingTimes = [];
        updateScannerStats();
    }
    
    function updateScannerStatus(message, type = '') {
        const statusElement = document.getElementById('scanner-status');
        const icons = {
            scanning: 'spinner fa-spin',
            success: 'check-circle',
            error: 'exclamation-triangle',
            '': 'qrcode'
        };
        
        statusElement.innerHTML = `<p><i class="fas fa-${icons[type] || icons['']}"></i> ${message}</p>`;
        statusElement.className = `scanner-status ${type}`;
    }
    
    async function initializeQRScanner() {
        try {
            console.log('🚀 Initializing QR Scanner...');
            
            const hints = new Map();
            const formats = [ZXing.BarcodeFormat.QR_CODE];
            hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, formats);
            hints.set(ZXing.DecodeHintType.TRY_HARDER, false);
            
            codeReader = new ZXing.BrowserQRCodeReader(hints);
            const videoInputDevices = await codeReader.listVideoInputDevices();
            
            if (videoInputDevices && videoInputDevices.length > 0) {
                selectedDeviceId = videoInputDevices[0].deviceId;
                updateScannerStatus('Camera initialized successfully. Ready for scanning!', 'success');
            } else {
                updateScannerStatus('No camera devices found', 'error');
            }
        } catch (error) {
            console.error('❌ Error initializing scanner:', error);
            if (error.name === 'NotAllowedError') {
                updateScannerStatus('Camera access denied. Please allow camera permissions.', 'error');
            } else if (error.name === 'NotFoundError') {
                updateScannerStatus('No camera found on this device.', 'error');
            } else {
                updateScannerStatus('Error initializing camera: ' + error.message, 'error');
            }
        }
    }
    
    async function startQRScanning() {
        if (!codeReader) {
            await initializeQRScanner();
            if (!codeReader) return;
        }

        try {
            isScanning = true;
            resetScannerStats();
            document.getElementById('start-scan-btn').style.display = 'none';
            document.getElementById('stop-scan-btn').style.display = 'inline-flex';
            document.querySelector('.scanner-overlay').style.display = 'block';
            
            updateScannerStatus('🚀 Starting scanner...', 'scanning');
            updateScannerStats();
            console.log('▶️ Scanner starting...');

            let video = document.querySelector('#qr-reader video');
            if (!video) {
                video = document.createElement('video');
                video.setAttribute('playsinline', '');
                video.setAttribute('autoplay', '');
                video.setAttribute('muted', '');
                video.style.width = '100%';
                video.style.height = '100%';
                video.style.objectFit = 'cover';
                document.getElementById('qr-reader').appendChild(video);
            }

            video.style.display = 'block';

            const constraints = {
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 },
                    frameRate: { ideal: 60, min: 30 }
                }
            };

            try {
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                await video.play();
                
                const videoTrack = stream.getVideoTracks()[0];
                const capabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : {};
                if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
                    try {
                        await videoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                    } catch (e) {
                        console.log('Continuous focus mode not supported');
                    }
                }
            } catch (constraintError) {
                if (constraintError.name === 'OverconstrainedError') {
                    console.warn('Falling back to simpler constraints');
                    const fallbackConstraints = {
                        video: { facingMode: 'environment' }
                    };
                    const stream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
                    video.srcObject = stream;
                    await video.play();
                } else {
                    throw constraintError;
                }
            }

            // Use decodeFromVideoDevice instead of decodeFromVideoElement
            await codeReader.decodeFromVideoDevice(undefined, video, (result, err) => {
                if (result && isScanning && !isProcessing) {
                    handleQRCodeScan(result.text);
                }
            });

            console.log('✅ Scanner active!');
            updateScannerStatus('⚡ Camera active. Scan QR codes now!', 'scanning');

        } catch (error) {
            console.error('❌ Error starting scanner:', error);
            if (error.name === 'NotAllowedError') {
                updateScannerStatus('Camera access denied. Please allow camera permissions.', 'error');
            } else if (error.name === 'NotFoundError') {
                updateScannerStatus('No camera found on this device.', 'error');
            } else {
                updateScannerStatus('Error starting camera: ' + error.message, 'error');
            }
            stopQRScanning();
        }
    }

    function stopQRScanning() {
        try {
            if (codeReader) {
                codeReader.reset();
            }
        } catch (error) {
            console.error('❌ Error stopping scanner:', error);
        }
        
        isScanning = false;
        document.getElementById('start-scan-btn').style.display = 'inline-flex';
        document.getElementById('stop-scan-btn').style.display = 'none';
        document.querySelector('.scanner-overlay').style.display = 'none';
        
        const sessionSummary = scanCount > 0 ? ` (${scanCount} scan${scanCount !== 1 ? 's' : ''}, ${successCount} successful)` : '';
        updateScannerStatus('⏸️ Scanner stopped. Click "Start Scanner" to begin scanning.' + sessionSummary, '');
        console.log(`📊 Session stats: ${scanCount} total scans, ${successCount} successful`);
        
        if (scanCount > 0) {
            showNotification(`Session complete: ${successCount}/${scanCount} successful scans`, 'info');
        }
    }

    async function handleQRCodeScan(qrData) {
        if (isProcessing) {
            console.log('⚠️ Already processing a scan, ignoring...');
            return;
        }
        
        isProcessing = true;
        scanCount++;
        console.log(`🎯 QR Code scanned #${scanCount}:`, qrData.substring(0, 20) + '...');
        
        showQuickFeedback();
        updateScannerStatus(`Processing scan #${scanCount}...`, 'scanning');

        try {
            const lrn = qrData.split('|')[0].trim();
            console.log('📤 Sending attendance for LRN:', lrn);

            const startTime = Date.now();
            const response = await fetch('../api/mark_attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `lrn=${encodeURIComponent(lrn)}`
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            const processingTime = Date.now() - startTime;
            
            console.log('📥 Response:', data);
            console.log(`⏱️ Processing completed in ${processingTime}ms`);
            
            processingTimes.push(processingTime);
            if (processingTimes.length > 10) {
                processingTimes.shift();
            }

            if (data.success) {
                successCount++;
                showScanResult(true, data);
                updateScannerStatus(`✓ Success! Scan #${scanCount}: ${data.student_name || 'Student'}`, 'success');
                playSuccessSound();
                loadTodayAttendance();
            } else {
                showScanResult(false, data);
                updateScannerStatus(`✗ Error on scan #${scanCount}: ${data.message}`, 'error');
            }
            
            updateScannerStats();

        } catch (error) {
            console.error('❌ Error processing QR code:', error);
            
            let errorMessage = 'Network error. Please check your connection and try again.';
            if (error.message.includes('HTTP')) {
                errorMessage = `Server error: ${error.message}`;
            }
            
            showScanResult(false, { message: errorMessage });
            updateScannerStatus(`✗ Error on scan #${scanCount}: ${errorMessage}`, 'error');
        } finally {
            setTimeout(() => {
                isProcessing = false;
            }, 2000);
        }
    }

    function showQuickFeedback() {
        const video = document.querySelector('#qr-reader video');
        if (video) {
            video.style.borderColor = '#22c55e';
            setTimeout(() => {
                video.style.borderColor = '#d1d5db';
            }, 300);
        }
    }

    function playSuccessSound() {
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZUQ0MUqzn77BZGwtEoePy');
            audio.volume = 0.3;
            audio.play().catch(e => console.log('Audio play failed:', e));
        } catch (e) {
            // Silent fail
        }
    }

    function showScanResult(success, result) {
        const container = document.getElementById('scan-result-container');
        const resultDiv = document.getElementById('scan-result');
        
        container.style.display = 'block';
        container.style.animation = 'fadeIn 0.3s ease';
        
        if (success) {
            resultDiv.className = 'scan-result scan-result-success';
            resultDiv.innerHTML = `
                <h4><i class="fas fa-check-circle"></i> Attendance Marked Successfully</h4>
                <div class="scan-result-details">
                    <p><strong>Student:</strong> <span>${result.student_name || 'Unknown'}</span></p>
                    <p><strong>LRN:</strong> <span>${result.lrn || 'N/A'}</span></p>
                    <p><strong>Status:</strong> <span class="status-badge status-${result.status}">${result.status}</span></p>
                    <p><strong>Time:</strong> <span>${result.time || new Date().toLocaleTimeString()}</span></p>
                </div>
            `;
        } else {
            resultDiv.className = 'scan-result scan-result-error';
            resultDiv.innerHTML = `
                <h4><i class="fas fa-exclamation-circle"></i> Error</h4>
                <p>${result.message || 'Unknown error occurred'}</p>
            `;
        }

        setTimeout(() => {
            container.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                container.style.display = 'none';
            }, 300);
        }, 4000);
    }

    async function loadTodayAttendance() {
        try {
            const response = await fetch('../api/get_today_attendance.php');
            const result = await response.json();
            
            const listContainer = document.getElementById('today-attendance-list');
            
            if (result.success && result.attendance && result.attendance.length > 0) {
                let html = '';
                result.attendance.forEach((record, index) => {
                    html += `
                        <div class="attendance-item" style="animation-delay: ${index * 0.05}s;">
                            <div class="attendance-avatar">
                                ${record.first_name ? record.first_name.charAt(0).toUpperCase() : 'S'}
                            </div>
                            <div class="attendance-info">
                                <p class="attendance-name">${record.first_name || ''} ${record.last_name || 'Unknown'}</p>
                                <p class="attendance-details">
                                    <i class="fas fa-clock"></i> ${record.time || 'N/A'}
                                    <i class="fas fa-graduation-cap"></i> ${record.class || 'N/A'}
                                </p>
                            </div>
                            <span class="status-badge status-${record.status}">${record.status}</span>
                        </div>
                    `;
                });
                listContainer.innerHTML = html;
            } else {
                listContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No attendance yet today</h3>
                        <p>Scanned attendance will appear here</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading today\'s attendance:', error);
            document.getElementById('today-attendance-list').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error loading attendance</h3>
                    <p>Please try again later</p>
                </div>
            `;
        }
    }

    /* ===== EVENT DELEGATION ===== */
    document.addEventListener('click', async function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        
        const action = target.dataset.action;
        
        if (action === 'select-student') {
            const lrn = target.dataset.lrn;
            document.getElementById('lrn').value = lrn;
            document.getElementById('lrn').focus();
            showNotification('LRN selected: ' + lrn, 'success');
            
            // Switch to single entry tab
            document.querySelector('[data-tab="single"]').click();
        }
        else if (action === 'copy-class-lrns') {
            const className = target.dataset.class;
            const textarea = document.getElementById('class-' + className);
            
            try {
                await navigator.clipboard.writeText(textarea.value.trim());
                document.getElementById('bulk_lrns').value = textarea.value.trim();
                showNotification('LRNs copied for ' + className + '!', 'success');
                
                // Switch to bulk tab
                document.querySelector('[data-tab="bulk"]').click();
            } catch (err) {
                // Fallback
                textarea.select();
                document.execCommand('copy');
                document.getElementById('bulk_lrns').value = textarea.value.trim();
                showNotification('LRNs copied for ' + className + '!', 'success');
                document.querySelector('[data-tab="bulk"]').click();
            }
        }
    });

    /* ===== FORM SUBMISSIONS ===== */
    const singleForm = document.getElementById('single-attendance-form');
    if (singleForm) {
        singleForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch('manual_attendance.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    this.reset();
                    document.getElementById('date').value = '<?php echo $today; ?>';
                    document.getElementById('time').value = '<?php echo $currentTime; ?>';
                    document.getElementById('lrn').focus();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while marking attendance.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-loading');
                submitBtn.innerHTML = originalText;
            }
        });
    }

    const bulkForm = document.getElementById('bulk-attendance-form');
    if (bulkForm) {
        bulkForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch('manual_attendance.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const message = `Bulk attendance: ${data.successCount} successful, ${data.errorCount} errors.`;
                    showNotification(message, data.errorCount > 0 ? 'warning' : 'success');
                    
                    if (data.errors && data.errors.length > 0) {
                        console.log('Errors:', data.errors);
                    }
                    
                    this.reset();
                    document.getElementById('bulk_date').value = '<?php echo $today; ?>';
                    document.getElementById('bulk_time').value = '<?php echo $currentTime; ?>';
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while marking bulk attendance.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-loading');
                submitBtn.innerHTML = originalText;
            }
        });
    }

    /* ===== INITIALIZATION ===== */
    document.addEventListener('DOMContentLoaded', function() {
        initializeQRScanner();
        loadTodayAttendance();
        
        document.getElementById('start-scan-btn').addEventListener('click', startQRScanning);
        document.getElementById('stop-scan-btn').addEventListener('click', stopQRScanning);
        
        const lrnField = document.getElementById('lrn');
        if (lrnField) {
            lrnField.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && this.value.length >= 11) {
                    e.preventDefault();
                    document.getElementById('single-attendance-form').requestSubmit();
                }
            });
        }
        
        console.log('✅ Manual Attendance System initialized');
    });
</script>

<?php include 'includes/footer_modern.php'; ?>
