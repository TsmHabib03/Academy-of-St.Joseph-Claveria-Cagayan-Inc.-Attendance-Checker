CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type VARCHAR(50) NOT NULL,
    recipient_id VARCHAR(50) NOT NULL,
    -- Can be int or string (LRN/EmployeeID)
    mobile_number VARCHAR(20) NOT NULL,
    message_type VARCHAR(50) NOT NULL,
    message_content TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    -- pending, sent, failed
    provider_response TEXT,
    message_id VARCHAR(100),
    sent_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;