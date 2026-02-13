<?php
require_once __DIR__ . '/bootstrap.php';
// Require admin or teacher
api_require_schema_or_exit($pdo, ['tables' => ['students']]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER]);
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    // Schema-aware: detect if students table stores section by id or name
    $colStmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students'");
    $colStmt->execute();
    $studentCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasSectionId = in_array('section_id', $studentCols, true);
    $hasSectionName = in_array('section', $studentCols, true);

    // Optional filters
    $filterGrade = trim($_GET['grade_level'] ?? '');
    $filterSection = trim($_GET['section'] ?? ''); // may be id or name

    // Build base query (join to sections when filtering by grade)
    $base = "SELECT s.* FROM students s";
    $params = [];
    $join = '';
    $where = ' WHERE 1=1';

    if ($filterGrade !== '') {
        // Join to sections table to filter by grade_level
        $join = " LEFT JOIN sections sec ON (s.section_id = sec.id OR s.section = sec.section_name)";
        $where .= " AND sec.grade_level = ?";
        $params[] = $filterGrade;
    }

    if ($filterSection !== '') {
        if (is_numeric($filterSection)) {
            if ($hasSectionId) {
                $where .= " AND s.section_id = ?";
                $params[] = (int)$filterSection;
            } else {
                // Try to match against sections.id via join
                if ($join === '') {
                    $join = " LEFT JOIN sections sec ON (s.section_id = sec.id OR s.section = sec.section_name)";
                }
                $where .= " AND sec.id = ?";
                $params[] = (int)$filterSection;
            }
        } else {
            if ($hasSectionName) {
                $where .= " AND s.section = ?";
                $params[] = $filterSection;
            } else {
                if ($join === '') {
                    $join = " LEFT JOIN sections sec ON (s.section_id = sec.id OR s.section = sec.section_name)";
                }
                $where .= " AND sec.section_name = ?";
                $params[] = $filterSection;
            }
        }
    }

    $query = $base . $join . $where . " ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total' => count($students)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
