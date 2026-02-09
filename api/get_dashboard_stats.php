<?php
require_once __DIR__ . '/bootstrap.php';

api_require_schema_or_exit($pdo, [
    'tables' => ['students', 'attendance']
]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF]);

try {
    $today = date('Y-m-d');

    $tableExists = function (string $table) use ($pdo): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return ((int)$stmt->fetchColumn()) > 0;
    };

    $columnExists = function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return ((int)$stmt->fetchColumn()) > 0;
    };

    $exclusionClause = " AND (class NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6') AND class NOT LIKE 'Kinder%') ";

    // Total students
    $totalStudentsStmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE 1=1 {$exclusionClause}");
    $totalStudentsStmt->execute();
    $totalStudents = (int)$totalStudentsStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Attendance columns for present calculation
    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance'");
    $colsStmt->execute();
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

    $timeInCandidates = ['morning_time_in', 'afternoon_time_in', 'time_in'];
    $whereParts = [];
    foreach ($timeInCandidates as $c) {
        if (in_array($c, $cols, true)) {
            $whereParts[] = "a.$c IS NOT NULL";
        }
    }

    if (empty($whereParts)) {
        $presentToday = 0;
    } else {
        $presentQuery = "
            SELECT COUNT(DISTINCT a.lrn) as present
            FROM attendance a
            JOIN students s ON a.lrn = s.lrn
            WHERE a.date = :today
              AND (" . implode(' OR ', $whereParts) . ")
              AND (s.class NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6') AND s.class NOT LIKE 'Kinder%')
        ";
        $presentStmt = $pdo->prepare($presentQuery);
        $presentStmt->bindParam(':today', $today);
        $presentStmt->execute();
        $presentToday = (int)$presentStmt->fetch(PDO::FETCH_ASSOC)['present'];
    }

    // Total records (filtered to same students)
    $totalRecordsStmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance a JOIN students s ON a.lrn = s.lrn WHERE 1=1 " . str_replace('class', 's.class', $exclusionClause));
    $totalRecordsStmt->execute();
    $totalRecords = (int)$totalRecordsStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Teacher stats
    $presentTeachers = 0;
    $totalTeachers = 0;

    if ($tableExists('teachers')) {
        $teacherWhere = $columnExists('teachers', 'is_active') ? ' WHERE is_active = 1' : '';
        $totalTeachersStmt = $pdo->prepare("SELECT COUNT(*) as total FROM teachers{$teacherWhere}");
        $totalTeachersStmt->execute();
        $totalTeachers = (int)$totalTeachersStmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    if ($tableExists('teacher_attendance')) {
        $teacherColsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_attendance'");
        $teacherColsStmt->execute();
        $teacherCols = $teacherColsStmt->fetchAll(PDO::FETCH_COLUMN);

        $teacherWhereParts = [];
        foreach ($timeInCandidates as $c) {
            if (in_array($c, $teacherCols, true)) {
                $teacherWhereParts[] = "$c IS NOT NULL";
            }
        }

        if (!empty($teacherWhereParts)) {
            $hasEmpNum = in_array('employee_number', $teacherCols, true);
            $hasEmpId = in_array('employee_id', $teacherCols, true);
            if ($hasEmpNum && $hasEmpId) {
                $teacherIdExpr = "COALESCE(employee_number, employee_id)";
            } elseif ($hasEmpNum) {
                $teacherIdExpr = "employee_number";
            } else {
                $teacherIdExpr = "employee_id";
            }

            $presentTeachersQuery = "SELECT COUNT(DISTINCT {$teacherIdExpr}) as present_teachers FROM teacher_attendance WHERE date = :today AND (" . implode(' OR ', $teacherWhereParts) . ")";
            $presentTeachersStmt = $pdo->prepare($presentTeachersQuery);
            $presentTeachersStmt->bindParam(':today', $today);
            $presentTeachersStmt->execute();
            $presentTeachers = (int)$presentTeachersStmt->fetch(PDO::FETCH_ASSOC)['present_teachers'];
        }
    }

    $attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_students' => $totalStudents,
            'present_today' => $presentToday,
            'attendance_rate' => $attendanceRate,
            'total_records' => $totalRecords,
            'present_teachers' => $presentTeachers,
            'total_teachers' => $totalTeachers
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
