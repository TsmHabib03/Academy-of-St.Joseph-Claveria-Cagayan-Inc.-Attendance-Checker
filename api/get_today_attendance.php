<?php
require_once __DIR__ . '/bootstrap.php';
api_require_schema_or_exit($pdo, ['tables' => ['attendance']]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF]);
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $today = date('Y-m-d');
    
    // Get today's attendance with student details and subjects
    $query = "SELECT a.*, s.first_name, s.last_name, s.class, s.lrn 
              FROM attendance a 
              JOIN students s ON a.lrn = s.lrn 
              WHERE a.date = :today 
              ORDER BY a.period_number ASC, a.time ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format time for display
    foreach ($attendance as &$record) {
        $record['time'] = date('h:i:s A', strtotime($record['time']));
    }
    
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'date' => $today,
        'total' => count($attendance)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
