<?php
/**
 * Email Configuration Test
 */

header('Content-Type: application/json');

try {
    // Check if email config file exists
    if (!file_exists('../config/email_config.php')) {
        echo json_encode([
            'success' => false,
            'message' => 'Email configuration file not found'
        ]);
        exit();
    }

    // Load email config
    $emailConfig = require_once '../config/email_config.php';

    // Check PHPMailer
    $phpmailerExists = file_exists('../libs/PHPMailer/PHPMailer.php');

    echo json_encode([
        'success' => true,
        'smtp_host' => $emailConfig['smtp_host'] ?? 'Not set',
        'smtp_port' => $emailConfig['smtp_port'] ?? 'Not set',
        'smtp_username' => $emailConfig['smtp_username'] ?? 'Not set',
        'password_set' => !empty($emailConfig['smtp_password']),
        'password_length' => isset($emailConfig['smtp_password']) ? strlen($emailConfig['smtp_password']) : 0,
        'phpmailer_exists' => $phpmailerExists,
        'message' => 'Email configuration loaded successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
