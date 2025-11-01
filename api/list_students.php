<?php
require_once __DIR__ . '/../includes/database.php';

$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("SELECT lrn, first_name, last_name, email, class FROM students LIMIT 5");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Registered Students:\n\n";
foreach ($students as $student) {
    echo "LRN: {$student['lrn']}\n";
    echo "Name: {$student['first_name']} {$student['last_name']}\n";
    echo "Email: {$student['email']}\n";
    echo "Class: {$student['class']}\n";
    echo "---\n";
}
?>
