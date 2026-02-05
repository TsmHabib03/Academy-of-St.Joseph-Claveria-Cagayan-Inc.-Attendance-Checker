<?php
require_once __DIR__ . '/bootstrap.php';
// Require admin or teacher
api_require_schema_or_exit($pdo, ['tables' => ['students']]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER]);
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all students
    $query = "SELECT * FROM students ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
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
