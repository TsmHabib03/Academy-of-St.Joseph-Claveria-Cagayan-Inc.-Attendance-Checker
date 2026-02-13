<?php
require_once __DIR__ . '/bootstrap.php';
// Require authenticated school staff (admin/teacher/staff)
api_require_roles([ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF]);
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $classes = [];
    $tableCheckStmt = $db->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('sections', 'students')");
    $tableCheckStmt->execute();
    $availableTables = $tableCheckStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($availableTables)) {
        throw new Exception('Required tables are missing (sections/students).');
    }

    // Prefer canonical sections table for current structure
    $hasSectionsTableStmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sections'");
    $hasSectionsTableStmt->execute();
    $hasSectionsTable = ((int)$hasSectionsTableStmt->fetchColumn()) > 0;

    if ($hasSectionsTable) {
        $secColsStmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sections'");
        $secColsStmt->execute();
        $sectionCols = $secColsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('section_name', $sectionCols, true)) {
            if (in_array('grade_level', $sectionCols, true)) {
                $query = "SELECT DISTINCT section_name FROM sections WHERE section_name IS NOT NULL AND section_name != '' ORDER BY grade_level, section_name";
            } else {
                $query = "SELECT DISTINCT section_name FROM sections WHERE section_name IS NOT NULL AND section_name != '' ORDER BY section_name";
            }
            $stmt = $db->prepare($query);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $classes[] = trim((string)($row['section_name'] ?? ''));
            }
        }
    }

    // Fallback for legacy datasets: read from students.section/class
    if (empty($classes)) {
        $stuColsStmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students'");
        $stuColsStmt->execute();
        $studentCols = $stuColsStmt->fetchAll(PDO::FETCH_COLUMN);

        $legacyQueries = [];
        if (in_array('section', $studentCols, true)) {
            $legacyQueries[] = "SELECT DISTINCT section AS class_value FROM students WHERE section IS NOT NULL AND section != ''";
        }
        if (in_array('class', $studentCols, true)) {
            $legacyQueries[] = "SELECT DISTINCT class AS class_value FROM students WHERE class IS NOT NULL AND class != ''";
        }

        foreach ($legacyQueries as $legacyQuery) {
            $stmt = $db->prepare($legacyQuery);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $classes[] = trim((string)($row['class_value'] ?? ''));
            }
        }
    }

    $classes = array_values(array_unique(array_filter($classes, static function ($v) {
        return $v !== '';
    })));
    sort($classes, SORT_NATURAL | SORT_FLAG_CASE);
    
    echo json_encode([
        'success' => true,
        'classes' => $classes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving classes: ' . $e->getMessage()
    ]);
}
?>
