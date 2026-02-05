<?php
require_once 'includes/database.php';
$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("SELECT * FROM sms_logs ORDER BY id DESC LIMIT 1");
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if ($log) {
    echo "Last Log Status: " . $log['status'] . "\n";
    echo "Provider Response: " . $log['provider_response'] . "\n";
    echo "Message Content: " . $log['message_content'] . "\n";
} else {
    echo "No logs found.\n";
}
?>
