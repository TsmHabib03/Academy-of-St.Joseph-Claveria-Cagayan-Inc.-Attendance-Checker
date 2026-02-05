<?php
/**
 * SMS Gateway Configuration for AttendEase v3.0
 * Configure your SMS provider settings here
 * 
 * Supported providers:
 * - Semaphore (Philippines)
 * - Twilio (International)
 * - Nexmo/Vonage (International)
 * - Custom API
 * 
 * @package AttendEase
 * @version 3.0
 */

return [
    // =========================================================================
    // GENERAL SMS SETTINGS
    // =========================================================================
    
    'enabled' => true, // Set to true to enable SMS notifications
    'enabled' => true, // Set to true to enable SMS notifications
    'provider' => 'custom', // Switched to 'custom' for Android SMS Gateway
    
    // ... (lines 24-75 unchanged)

    'custom' => [
        // REPLACE WITH YOUR PHONE'S LOCAL IP ADDRESS
        // Example: http://192.168.1.5:8080/v1/sms
        // Make sure your PC and Phone are on the SAME WiFi network
        'api_url' => 'http://192.168.1.XXX:8080/v1/sms', 
        
        'api_key' => '', // Leave empty if your app doesn't use one, or put the password here
        'method' => 'POST',
        'headers' => [
            'Content-Type: application/json',
            // 'Authorization: Bearer YOUR_TOKEN' // Uncomment if needed
        ],
        // Parameter mapping - maps our fields to the API's expected fields
        // Adjust these to match your Android App's API requirements
        'param_mapping' => [
            'to' => 'phone',       // Most apps use 'phone' or 'number'
            'message' => 'message', // Most apps use 'message'
            'sender' => 'sender',   // Optional
        ],
    ],
    
    // =========================================================================
    // MESSAGE TEMPLATES
    // Available placeholders: {name}, {date}, {time}, {status}, {section}, {school}
    // =========================================================================
    
    'templates' => [
        'late' => [
            'student' => 'LATE NOTICE: {name} arrived late at {time} on {date}. - {school}',
            'teacher' => 'LATE: {name} (Teacher) arrived late at {time} on {date}. - {school}',
        ],
        'absent' => [
            'student' => 'ABSENT: {name} was marked absent on {date}. Please contact the school if incorrect. - {school}',
            'teacher' => 'ABSENT: {name} (Teacher) was marked absent on {date}. - {school}',
        ],
        'time_in' => [
            'student' => '{name} arrived at school at {time} on {date}. - {school}',
            'teacher' => '{name} (Teacher) checked in at {time} on {date}. - {school}',
        ],
        'time_out' => [
            'student' => '{name} left school at {time} on {date}. - {school}',
            'teacher' => '{name} (Teacher) checked out at {time} on {date}. - {school}',
        ],
        'behavior_alert' => [
            'frequent_late' => 'ATTENTION: {name} has been frequently late ({count} times this week). Please contact the guidance office. - {school}',
            'consecutive_absence' => 'ATTENTION: {name} has been absent for {count} consecutive days. Please contact the school. - {school}',
        ],
    ],
    
    // =========================================================================
    // SCHOOL INFORMATION (Used in templates)
    // =========================================================================
    
    'school' => [
        'name' => 'Academy of St. Joseph Claveria',
        'short_name' => 'ASJ',
        'contact' => '(078) 123-4567',
    ],
];
