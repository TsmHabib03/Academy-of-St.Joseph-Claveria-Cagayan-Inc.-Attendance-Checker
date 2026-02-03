<?php
/**
 * SMS Sender Class for AttendEase v3.0
 * Handles SMS notifications via multiple providers
 * 
 * @package AttendEase
 * @version 3.0
 */

class SmsSender {
    private $config;
    private $pdo;
    private $provider;
    private $enabled;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../config/sms_config.php';
        $this->enabled = $this->config['enabled'] ?? false;
        $this->provider = $this->config['provider'] ?? 'semaphore';
    }
    
    /**
     * Check if SMS is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }
    
    /**
     * Send SMS notification
     * 
     * @param string $mobileNumber Recipient mobile number
     * @param string $message Message content
     * @param string $recipientType Type of recipient (student, teacher, parent)
     * @param string $recipientId ID of the recipient
     * @param string $messageType Type of message (late, absent, etc.)
     * @return array Result with success status and message
     */
    public function send(
        string $mobileNumber, 
        string $message, 
        string $recipientType = 'parent',
        string $recipientId = '',
        string $messageType = 'custom'
    ): array {
        // Check if enabled
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'SMS notifications are disabled'];
        }
        
        // Validate mobile number
        $mobileNumber = $this->formatPhilippineNumber($mobileNumber);
        if (!$mobileNumber) {
            return ['success' => false, 'message' => 'Invalid mobile number format'];
        }
        
        // Check rate limiting
        if (!$this->checkRateLimit($mobileNumber)) {
            return ['success' => false, 'message' => 'SMS rate limit exceeded for this number'];
        }
        
        // Log the SMS attempt
        $logId = $this->logSms($recipientType, $recipientId, $mobileNumber, $messageType, $message, 'pending');
        
        // Send via configured provider
        try {
            $result = $this->sendViaProvider($mobileNumber, $message);
            
            // Update log with result
            $this->updateSmsLog($logId, $result);
            
            return $result;
        } catch (Exception $e) {
            $errorResult = ['success' => false, 'message' => $e->getMessage()];
            $this->updateSmsLog($logId, $errorResult);
            return $errorResult;
        }
    }
    
    /**
     * Send SMS via the configured provider
     * 
     * @param string $mobileNumber Formatted mobile number
     * @param string $message Message content
     * @return array Result
     */
    private function sendViaProvider(string $mobileNumber, string $message): array {
        switch ($this->provider) {
            case 'semaphore':
                return $this->sendViaSemaphore($mobileNumber, $message);
            case 'twilio':
                return $this->sendViaTwilio($mobileNumber, $message);
            case 'vonage':
                return $this->sendViaVonage($mobileNumber, $message);
            case 'custom':
                return $this->sendViaCustomApi($mobileNumber, $message);
            default:
                return ['success' => false, 'message' => 'Unknown SMS provider'];
        }
    }
    
    /**
     * Send SMS via Semaphore
     * 
     * @param string $mobileNumber Mobile number
     * @param string $message Message content
     * @return array Result
     */
    private function sendViaSemaphore(string $mobileNumber, string $message): array {
        $config = $this->config['semaphore'];
        
        if (empty($config['api_key'])) {
            return ['success' => false, 'message' => 'Semaphore API key not configured'];
        }
        
        $params = [
            'apikey' => $config['api_key'],
            'number' => $mobileNumber,
            'message' => $message,
            'sendername' => $config['sender_name'] ?? 'ASJ-ATTEND'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['api_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: ' . $error];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData[0]['message_id'])) {
            return [
                'success' => true, 
                'message' => 'SMS sent successfully',
                'message_id' => $responseData[0]['message_id'],
                'provider_response' => $response
            ];
        }
        
        return [
            'success' => false, 
            'message' => 'Failed to send SMS',
            'provider_response' => $response
        ];
    }
    
    /**
     * Send SMS via Twilio
     * 
     * @param string $mobileNumber Mobile number
     * @param string $message Message content
     * @return array Result
     */
    private function sendViaTwilio(string $mobileNumber, string $message): array {
        $config = $this->config['twilio'];
        
        if (empty($config['account_sid']) || empty($config['auth_token'])) {
            return ['success' => false, 'message' => 'Twilio credentials not configured'];
        }
        
        // Convert to international format if needed
        if (strpos($mobileNumber, '+') !== 0) {
            $mobileNumber = '+63' . ltrim($mobileNumber, '0');
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$config['account_sid']}/Messages.json";
        
        $params = [
            'From' => $config['from_number'],
            'To' => $mobileNumber,
            'Body' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $config['account_sid'] . ':' . $config['auth_token']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: ' . $error];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 201 && isset($responseData['sid'])) {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $responseData['sid'],
                'provider_response' => $response
            ];
        }
        
        return [
            'success' => false,
            'message' => $responseData['message'] ?? 'Failed to send SMS',
            'provider_response' => $response
        ];
    }
    
    /**
     * Send SMS via Vonage (Nexmo)
     * 
     * @param string $mobileNumber Mobile number
     * @param string $message Message content
     * @return array Result
     */
    private function sendViaVonage(string $mobileNumber, string $message): array {
        $config = $this->config['vonage'];
        
        if (empty($config['api_key']) || empty($config['api_secret'])) {
            return ['success' => false, 'message' => 'Vonage credentials not configured'];
        }
        
        // Convert to international format
        if (strpos($mobileNumber, '+') !== 0) {
            $mobileNumber = '63' . ltrim($mobileNumber, '0');
        }
        
        $params = [
            'api_key' => $config['api_key'],
            'api_secret' => $config['api_secret'],
            'from' => $config['from_name'] ?? 'ASJ-ATTEND',
            'to' => $mobileNumber,
            'text' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://rest.nexmo.com/sms/json');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: ' . $error];
        }
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['messages'][0]['status']) && $responseData['messages'][0]['status'] === '0') {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $responseData['messages'][0]['message-id'] ?? '',
                'provider_response' => $response
            ];
        }
        
        return [
            'success' => false,
            'message' => $responseData['messages'][0]['error-text'] ?? 'Failed to send SMS',
            'provider_response' => $response
        ];
    }
    
    /**
     * Send SMS via custom API
     * 
     * @param string $mobileNumber Mobile number
     * @param string $message Message content
     * @return array Result
     */
    private function sendViaCustomApi(string $mobileNumber, string $message): array {
        $config = $this->config['custom'];
        
        if (empty($config['api_url'])) {
            return ['success' => false, 'message' => 'Custom API URL not configured'];
        }
        
        $mapping = $config['param_mapping'] ?? [];
        $params = [
            ($mapping['to'] ?? 'to') => $mobileNumber,
            ($mapping['message'] ?? 'message') => $message,
            ($mapping['sender'] ?? 'sender') => 'ASJ-ATTEND'
        ];
        
        if (!empty($config['api_key'])) {
            $params['api_key'] = $config['api_key'];
        }
        
        $headers = $config['headers'] ?? ['Content-Type: application/json'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['api_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        
        if (in_array('Content-Type: application/json', $headers)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: ' . $error];
        }
        
        // Assume success if HTTP 200
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'provider_response' => $response
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to send SMS (HTTP ' . $httpCode . ')',
            'provider_response' => $response
        ];
    }
    
    /**
     * Format Philippine mobile number
     * 
     * @param string $number Raw mobile number
     * @return string|false Formatted number or false if invalid
     */
    private function formatPhilippineNumber(string $number) {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);
        
        // Handle different formats
        if (preg_match('/^639\d{9}$/', $number)) {
            // Already in 639 format
            return '0' . substr($number, 2); // Convert to 09XX format
        } elseif (preg_match('/^09\d{9}$/', $number)) {
            // Valid 09XX format
            return $number;
        } elseif (preg_match('/^9\d{9}$/', $number)) {
            // Missing leading 0
            return '0' . $number;
        }
        
        return false;
    }
    
    /**
     * Check rate limiting for a mobile number
     * 
     * @param string $mobileNumber Mobile number
     * @return bool True if within limits
     */
    private function checkRateLimit(string $mobileNumber): bool {
        $maxPerDay = $this->config['max_sms_per_day_per_number'] ?? 5;
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM sms_logs 
                WHERE mobile_number = ? 
                AND DATE(created_at) = CURDATE()
                AND status IN ('sent', 'pending')
            ");
            $stmt->execute([$mobileNumber]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($result['count'] ?? 0) < $maxPerDay;
        } catch (Exception $e) {
            error_log("SMS rate limit check failed: " . $e->getMessage());
            return true; // Allow if check fails
        }
    }
    
    /**
     * Log SMS to database
     * 
     * @param string $recipientType Type of recipient
     * @param string $recipientId Recipient ID
     * @param string $mobileNumber Mobile number
     * @param string $messageType Type of message
     * @param string $message Message content
     * @param string $status Initial status
     * @return int|null Log ID or null on failure
     */
    private function logSms(
        string $recipientType,
        string $recipientId,
        string $mobileNumber,
        string $messageType,
        string $message,
        string $status
    ): ?int {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sms_logs 
                (recipient_type, recipient_id, mobile_number, message_type, message_content, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$recipientType, $recipientId, $mobileNumber, $messageType, $message, $status]);
            return (int) $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to log SMS: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update SMS log with result
     * 
     * @param int|null $logId Log ID
     * @param array $result Send result
     */
    private function updateSmsLog(?int $logId, array $result): void {
        if (!$logId) {
            return;
        }
        
        try {
            $status = $result['success'] ? 'sent' : 'failed';
            $stmt = $this->pdo->prepare("
                UPDATE sms_logs 
                SET status = ?, 
                    provider_response = ?,
                    message_id = ?,
                    sent_at = IF(? = 'sent', NOW(), NULL)
                WHERE id = ?
            ");
            $stmt->execute([
                $status,
                $result['provider_response'] ?? $result['message'],
                $result['message_id'] ?? null,
                $status,
                $logId
            ]);
        } catch (Exception $e) {
            error_log("Failed to update SMS log: " . $e->getMessage());
        }
    }
    
    /**
     * Send attendance notification
     * 
     * @param string $userType Type of user (student/teacher)
     * @param array $userData User data array
     * @param string $notificationType Type of notification (late, absent, time_in, time_out)
     * @param array $attendanceData Attendance details
     * @return array Result
     */
    public function sendAttendanceNotification(
        string $userType,
        array $userData,
        string $notificationType,
        array $attendanceData = []
    ): array {
        // Check if this notification type is enabled
        $configKey = 'send_on_' . $notificationType;
        if (!($this->config[$configKey] ?? false)) {
            return ['success' => false, 'message' => "Notification type '{$notificationType}' is disabled"];
        }
        
        // Get mobile number
        $mobileNumber = $userData['mobile_number'] ?? '';
        if (empty($mobileNumber)) {
            return ['success' => false, 'message' => 'No mobile number available'];
        }
        
        // Get message template
        $template = $this->config['templates'][$notificationType][$userType] ?? null;
        if (!$template) {
            return ['success' => false, 'message' => 'Message template not found'];
        }
        
        // Build message from template
        $name = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
        $message = str_replace(
            ['{name}', '{date}', '{time}', '{status}', '{section}', '{school}'],
            [
                $name,
                $attendanceData['date'] ?? date('M d, Y'),
                $attendanceData['time'] ?? date('h:i A'),
                $notificationType,
                $userData['section'] ?? 'N/A',
                $this->config['school']['name'] ?? 'ASJ'
            ],
            $template
        );
        
        // Send the SMS
        return $this->send(
            $mobileNumber,
            $message,
            $userType === 'student' ? 'parent' : 'teacher',
            $userData['lrn'] ?? $userData['employee_id'] ?? '',
            $notificationType
        );
    }
    
    /**
     * Get SMS statistics
     * 
     * @param string $period Period (today, week, month)
     * @return array Statistics
     */
    public function getStatistics(string $period = 'today'): array {
        try {
            $dateCondition = match($period) {
                'today' => 'DATE(created_at) = CURDATE()',
                'week' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
                'month' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
                default => 'DATE(created_at) = CURDATE()'
            };
            
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM sms_logs
                WHERE {$dateCondition}
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?? [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'pending' => 0
            ];
        } catch (Exception $e) {
            error_log("Failed to get SMS statistics: " . $e->getMessage());
            return ['total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0];
        }
    }
}
