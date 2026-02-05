<?php
require_once 'includes/database.php';
$database = new Database();
$db = $database->getConnection();

echo "Table: sections\n";
$stmt = $db->query("DESCRIBE sections");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
