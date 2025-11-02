<?php
/**
 * Simple PHP Test - Check if PHP is working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'PHP is working!',
    'php_version' => phpversion(),
    'time' => date('Y-m-d H:i:s')
]);
?>
