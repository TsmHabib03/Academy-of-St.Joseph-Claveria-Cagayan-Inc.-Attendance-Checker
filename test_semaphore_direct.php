<?php
// Configuration from sms_config.php but hardcoded for debug to be sure
$apiKey = '1e6c84df0d4488fd33caf4a1198f80e5';
$number = '09997670753'; // Number from user's error message
$message = 'Test SMS Direct Debug';
$senderName = 'ASJ-ATTEND';

$params = [
    'apikey' => $apiKey,
    'number' => $number,
    'message' => $message,
    'sendername' => $senderName
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Disable SSL verification for development if needed (though not recommended for prod)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "cURL Error: " . $error . "\n";
echo "Response Body: " . $response . "\n";
?>
