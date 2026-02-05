<?php
// CLI test runner: scripts/test_all.php
// Usage: php scripts/test_all.php

require_once __DIR__ . '/../config/db_config.php';

$errors = [];
$report = ['ok' => true, 'checks' => []];

function add_result(&$report, $key, $ok, $message = '', $details = null) {
    $report['checks'][] = ['check' => $key, 'ok' => $ok, 'message' => $message, 'details' => $details];
    if (!$ok) $report['ok'] = false;
}

// Helper: check table exists
function table_exists($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $stmt->execute([':t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

// Helper: check column exists
function column_exists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

// Helper: check index exists
function index_exists($pdo, $table, $index) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i");
    $stmt->execute([':t' => $table, ':i' => $index]);
    return (int)$stmt->fetchColumn() > 0;
}

// 1) Tables to check
$tables = [
    'students', 'attendance', 'admin_users', 'attendance_schedules', 'teachers', 'teacher_attendance',
    'badges', 'user_badges', 'sms_logs', 'sms_templates', 'system_settings', 'behavior_alerts'
];
foreach ($tables as $t) {
    $ok = table_exists($pdo, $t);
    add_result($report, "table:$t", $ok, $ok ? "exists" : "missing");
}

// 2) Attendance columns
$attendance_columns = ['morning_time_in','morning_time_out','afternoon_time_in','afternoon_time_out','is_late_morning','is_late_afternoon','period_number'];
foreach ($attendance_columns as $col) {
    $ok = column_exists($pdo, 'attendance', $col);
    add_result($report, "column:attendance.$col", $ok, $ok ? 'present' : 'missing');
}

// 3) Students columns
$student_cols = ['mobile_number','email'];
foreach ($student_cols as $col) {
    $ok = column_exists($pdo, 'students', $col);
    add_result($report, "column:students.$col", $ok, $ok ? 'present' : 'missing');
}

// 4) Indexes
$indexes = [ ['attendance','idx_attendance_date_lrn'], ['students','idx_students_section'] ];
foreach ($indexes as $ix) {
    $ok = index_exists($pdo, $ix[0], $ix[1]);
    add_result($report, "index:{$ix[0]}.{$ix[1]}", $ok, $ok ? 'present' : 'missing');
}

// 5) Routines (procedures)
$routines = ['MarkAttendance_v3','RegisterStudent_v3','RegisterTeacher'];
foreach ($routines as $r) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME = :r");
    $stmt->execute([':r' => $r]);
    $ok = (int)$stmt->fetchColumn() > 0;
    add_result($report, "routine:$r", $ok, $ok ? 'exists' : 'missing');
}

// 6) Basic data sanity: row counts for critical tables
foreach (['students','attendance','teachers'] as $t) {
    if (table_exists($pdo,$t)) {
        try {
            $cstmt = $pdo->query("SELECT COUNT(*) AS c FROM `{$t}`");
            $c = (int)$cstmt->fetchColumn();
            add_result($report, "count:$t", true, "rows={$c}");
        } catch (Exception $e) {
            add_result($report, "count:$t", false, 'query failed', $e->getMessage());
        }
    }
}

// 7) Lint selected PHP API files using `php -l` if available
$php_files = [
    __DIR__ . '/../api/mark_attendance.php',
    __DIR__ . '/../api/bootstrap.php',
    __DIR__ . '/../api/get_attendance_report_sections.php',
    __DIR__ . '/../admin/login.php'
];
// Check PHP CLI availability once
$phpCheck = shell_exec('php -v 2>&1');
$phpCliAvailable = ($phpCheck !== null && stripos($phpCheck, 'PHP') !== false);
foreach ($php_files as $f) {
    if (file_exists($f)) {
        if (!$phpCliAvailable) {
            add_result($report, "php_lint:" . basename($f), false, 'php CLI not available; lint skipped');
            continue;
        }
        $out = null; $rc = null;
        $cmd = 'php -l ' . escapeshellarg($f);
        $out = shell_exec($cmd . ' 2>&1');
        $ok = (strpos($out, 'No syntax errors detected') !== false);
        add_result($report, "php_lint:" . basename($f), $ok, trim($out));
    } else {
        add_result($report, "php_lint:" . basename($f), false, 'file missing');
    }
}

// 8) Quick functional test: try calling MarkAttendance_v3 with harmless params (wrap in transaction and rollback)
try {
    if (table_exists($pdo,'attendance')) {
        $pdo->beginTransaction();
        $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='MarkAttendance_v3'");
        if ($stmt && (int)$stmt->fetchColumn() > 0) {
            // Use existing student LRN to avoid FK constraint failures
            $lrnStmt = $pdo->query("SELECT lrn FROM students LIMIT 1");
            $existingLrn = $lrnStmt ? $lrnStmt->fetchColumn() : null;
            if (!$existingLrn) {
                add_result($report, 'call:MarkAttendance_v3', false, 'no student LRN available to test procedure');
            } else {
                $call = $pdo->prepare("CALL MarkAttendance_v3(:type,:id,:date,:time,:action,:section)");
                $call->execute([':type'=>'student',':id'=>$existingLrn,':date'=>date('Y-m-d'),':time'=>date('H:i:s'),':action'=>'time_in',':section'=>null]);
                // Consume any result sets returned by the procedure to avoid "pending result sets" errors
                try {
                    do {
                        if ($call->columnCount()) {
                            $call->fetchAll();
                        }
                    } while ($call->nextRowset());
                } catch (Exception $e) {
                    // ignore fetch errors from empty result sets
                }
                $call->closeCursor();
                add_result($report, 'call:MarkAttendance_v3', true, 'procedure executed with existing LRN (transaction will rollback)');
            }
        } else {
            add_result($report, 'call:MarkAttendance_v3', false, 'procedure missing');
        }
        $pdo->rollBack();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    add_result($report, 'call:MarkAttendance_v3', false, 'call failed', $e->getMessage());
}

// Output summary JSON and human-friendly text
echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
echo "Summary: " . ($report['ok'] ? "OK" : "ERRORS FOUND") . "\n";
foreach ($report['checks'] as $c) {
    $status = $c['ok'] ? '[OK] ' : '[FAIL]';
    echo sprintf("%s %s - %s\n", $status, $c['check'], $c['message']);
    if (!$c['ok'] && !empty($c['details'])) echo "    details: " . $c['details'] . "\n";
}

exit($report['ok'] ? 0 : 2);
