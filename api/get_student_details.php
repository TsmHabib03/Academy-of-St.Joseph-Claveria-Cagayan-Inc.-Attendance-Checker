<?php
require_once __DIR__ . '/bootstrap.php';
// Require admin, teacher, or staff
api_require_schema_or_exit($pdo, ['tables' => ['students','attendance']]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF]);
require_once '../includes/database.php';

$identifier = null;
$idType = null;

if (isset($_GET['lrn']) && $_GET['lrn'] !== '') {
    $identifier = trim((string)$_GET['lrn']);
    $idType = 'lrn';
} elseif (isset($_GET['id']) && $_GET['id'] !== '') {
    $identifier = trim((string)$_GET['id']);
    $idType = isset($_GET['id_type']) ? strtolower(trim((string)$_GET['id_type'])) : null;
} elseif (isset($_GET['student_id']) && $_GET['student_id'] !== '') {
    $identifier = trim((string)$_GET['student_id']);
    $idType = 'student_id';
}

if ($identifier === null || $identifier === '') {
    echo json_encode(['success' => false, 'message' => 'Student identifier is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $hasLrn = columnExists($db, 'students', 'lrn');
    $hasStudentId = columnExists($db, 'students', 'student_id');
    $hasId = columnExists($db, 'students', 'id');
    $hasSectionId = columnExists($db, 'students', 'section_id');
    $hasSectionName = columnExists($db, 'students', 'section');
    $hasStudentGrade = columnExists($db, 'students', 'grade_level');
    $hasStudentClass = columnExists($db, 'students', 'class');
    $hasSections = tableExists($db, 'sections');

    $allowedTypes = ['lrn', 'student_id', 'id'];
    if ($idType !== null && !in_array($idType, $allowedTypes, true)) {
        $idType = null;
    }

    $resolvedType = null;
    if ($idType === 'lrn' && $hasLrn) {
        $resolvedType = 'lrn';
    } elseif ($idType === 'student_id' && $hasStudentId) {
        $resolvedType = 'student_id';
    } elseif ($idType === 'id' && $hasId) {
        $resolvedType = 'id';
    }

    if ($resolvedType === null) {
        if ($hasLrn && preg_match('/^\d{11,13}$/', $identifier)) {
            $resolvedType = 'lrn';
        } elseif ($hasStudentId) {
            $resolvedType = 'student_id';
        } elseif ($hasId) {
            $resolvedType = 'id';
        }
    }

    if ($resolvedType === null) {
        echo json_encode(['success' => false, 'message' => 'No supported identifier column found']);
        exit;
    }

    $selectParts = ['s.*'];
    $join = '';
    if ($hasSections && ($hasSectionId || $hasSectionName)) {
        if ($hasSectionId) {
            $join = 'LEFT JOIN sections sec ON s.section_id = sec.id';
        } else {
            $join = 'LEFT JOIN sections sec ON s.section = sec.section_name';
        }
        $selectParts[] = 'sec.section_name AS section_name';
        $selectParts[] = 'sec.grade_level AS grade_level';
    } else {
        $selectParts[] = $hasSectionName ? 's.section AS section_name' : 'NULL AS section_name';
        if ($hasStudentGrade) {
            $selectParts[] = 's.grade_level AS grade_level';
        } elseif ($hasStudentClass) {
            $selectParts[] = 's.class AS grade_level';
        } else {
            $selectParts[] = 'NULL AS grade_level';
        }
    }

    $student_query = "SELECT " . implode(', ', $selectParts) . " FROM students s {$join} WHERE s.{$resolvedType} = :identifier LIMIT 1";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':identifier', $identifier);
    $student_stmt->execute();

    if ($student_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

    // Determine attendance identifier field
    $attendanceIdField = null;
    if (columnExists($db, 'attendance', 'lrn') && $hasLrn) {
        $attendanceIdField = 'lrn';
    } elseif (columnExists($db, 'attendance', 'student_id') && $hasStudentId) {
        $attendanceIdField = 'student_id';
    }

    $attendance = [];
    if ($attendanceIdField !== null) {
        $attendanceKey = null;
        if ($attendanceIdField === 'lrn') {
            $attendanceKey = $student['lrn'] ?? ($resolvedType === 'lrn' ? $identifier : null);
        } elseif ($attendanceIdField === 'student_id') {
            $attendanceKey = $student['student_id'] ?? ($resolvedType === 'student_id' ? $identifier : null);
        }

        if ($attendanceKey !== null && $attendanceKey !== '') {
            $orderBy = 'a.date DESC';
            $timeColumns = ['time', 'time_in', 'time_out', 'morning_time_in', 'afternoon_time_in', 'updated_at', 'created_at', 'id'];
            foreach ($timeColumns as $col) {
                if (columnExists($db, 'attendance', $col)) {
                    $orderBy .= ", a.{$col} DESC";
                    break;
                }
            }

            $attendance_query = "SELECT * FROM attendance a WHERE a.{$attendanceIdField} = :att_id ORDER BY {$orderBy} LIMIT 30";
            $attendance_stmt = $db->prepare($attendance_query);
            $attendance_stmt->bindParam(':att_id', $attendanceKey);
            $attendance_stmt->execute();
            $attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'success' => true,
        'student' => $student,
        'attendance' => $attendance
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
