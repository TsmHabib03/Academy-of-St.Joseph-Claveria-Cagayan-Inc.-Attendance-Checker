<?php
require_once __DIR__ . '/bootstrap.php';
// Require authenticated school staff (admin/teacher/staff)
api_require_schema_or_exit($pdo, ['tables' => ['sections']]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF]);
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get distinct classes from students table
    $query = "SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $classes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $classes[] = $row['class'];
    }
    
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
