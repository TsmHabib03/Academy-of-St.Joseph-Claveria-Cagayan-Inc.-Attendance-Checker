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
    'provider' => 'semaphore', // Options: 'semaphore', 'twilio', 'vonage', 'custom'
    
    // When to send SMS notifications
    'send_on_late' => true,
    'send_on_absent' => true,
    'send_on_time_in' => false, // Can be spammy, disabled by default
    'send_on_time_out' => false,
    'send_behavior_alerts' => true,
    
    // Rate limiting
    'max_sms_per_day_per_number' => 5, // Prevent spam
    'queue_enabled' => true, // Queue SMS for batch sending
    
    // =========================================================================
    // SEMAPHORE CONFIGURATION (Recommended for Philippines)
    // https://semaphore.co/
    // =========================================================================
    
    'semaphore' => [
        'api_key' => '', // Your Semaphore API key
        'sender_name' => 'ASJ-ATTEND', // Max 11 characters, alphanumeric
        'api_url' => 'https://api.semaphore.co/api/v4/messages',
        
        // Optional: Priority messages (costs more but faster delivery)
        'use_priority' => false,
    ],
    
    // =========================================================================
    // TWILIO CONFIGURATION (International)
    // https://www.twilio.com/
    // =========================================================================
    
    'twilio' => [
        'account_sid' => '', // Your Twilio Account SID
        'auth_token' => '', // Your Twilio Auth Token
        'from_number' => '', // Your Twilio phone number (e.g., +1234567890)
    ],
    
    // =========================================================================
    // VONAGE/NEXMO CONFIGURATION (International)
    // https://www.vonage.com/
    // =========================================================================
    
    'vonage' => [
        'api_key' => '',
        'api_secret' => '',
        'from_name' => 'ASJ-ATTEND',
    ],
    
    // =========================================================================
    // CUSTOM API CONFIGURATION
    // For other SMS providers
    // =========================================================================
    
    'custom' => [
        'api_url' => '', // POST endpoint
        'api_key' => '',
        'method' => 'POST',
        'headers' => [
            'Content-Type' => 'application/json',
            // Add custom headers as needed
        ],
        // Parameter mapping - maps our fields to the API's expected fields
        'param_mapping' => [
            'to' => 'mobile', // Field name for recipient number
            'message' => 'message', // Field name for message content
            'sender' => 'sender_id', // Field name for sender ID
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
