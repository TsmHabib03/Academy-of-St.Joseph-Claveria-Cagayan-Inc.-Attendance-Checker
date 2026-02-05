<?php
/**
 * QR Code Helper Functions
 * Handles QR code generation for students using ZXing API
 */

/**
 * Generate QR code for a student using ZXing API
 * @param int $studentId Student ID
 * @param string $lrn Student LRN
 * @param string $name Student full name
 * @return string|false Path to QR code file or false on failure
 */
function generateStudentQRCode($studentId, $lrn, $name = '') {
    try {
        // Define upload directory
        $uploadDir = __DIR__ . '/../uploads/qrcodes/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                error_log("Failed to create QR code directory: " . $uploadDir);
                return false;
            }
        }
        
        // Ensure directory is writable
        if (!is_writable($uploadDir)) {
            chmod($uploadDir, 0777);
        }
        
        // Generate filename
        $filename = 'student_' . $studentId . '.png';
        $filepath = $uploadDir . $filename;
        
        // QR code data (using LRN as the primary identifier)
        $qrData = $lrn;
        
        // Generate QR code using ZXing API
        $success = generateQRCodeWithZXing($qrData, $filepath);
        
        if ($success && file_exists($filepath)) {
            // Return relative path for database storage
            return 'uploads/qrcodes/' . $filename;
        } else {
            error_log("QR code file was not created: " . $filepath);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("QR code generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate QR code using ZXing API
 * @param string $data Data to encode in QR code
 * @param string $filepath Path where to save the QR code image
 * @return bool Success status
 */
function generateQRCodeWithZXing($data, $filepath) {
    try {
        // Use ZXing API endpoint for QR code generation
        // Format: https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=YOUR_DATA
        $size = 300;
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/';
        $params = http_build_query([
            'size' => $size . 'x' . $size,
            'data' => $data,
            'format' => 'png',
            'margin' => 10,
            'qzone' => 1,
            'color' => '000000',
            'bgcolor' => 'FFFFFF'
        ]);
        
        $url = $apiUrl . '?' . $params;
        
        // Initialize cURL session
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Execute request
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || $imageData === false) {
            error_log("ZXing API error: HTTP $httpCode - $error");
            // Fallback to local generation if API fails
            return generateQRCodeLocally($data, $filepath);
        }
        
        // Save image to file
        $result = file_put_contents($filepath, $imageData);
        
        if ($result === false) {
            error_log("Failed to write QR code file: " . $filepath);
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("ZXing QR generation error: " . $e->getMessage());
        return generateQRCodeLocally($data, $filepath);
    }
}

/**
 * Fallback: Generate QR code locally (only if GD extension available)
 * This creates a simple visual identifier with the LRN
 * @param string $data Data to encode (LRN)
 * @param string $filepath Path to save file
 * @return bool Success status
 */
function generateQRCodeLocally($data, $filepath) {
    try {
        // Check if GD extension is available (optional fallback)
        if (!function_exists('imagecreatetruecolor')) {
            error_log("GD extension not available - Cannot generate fallback image");
            error_log("Please ensure internet connection for ZXing API or enable GD extension");
            
            // Create a simple text file as last resort
            $textContent = "LRN: $data\nGenerated: " . date('Y-m-d H:i:s') . "\nNote: Enable GD extension or check internet for QR generation";
            file_put_contents(str_replace('.png', '.txt', $filepath), $textContent);
            
            return false;
        }
        
        // GD is available - create a simple image with the LRN text
        $img = imagecreatetruecolor(300, 300);
        
        if (!$img) {
            error_log("Failed to create image resource");
            return false;
        }
        
        // Colors
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        $gray = imagecolorallocate($img, 200, 200, 200);
        
        // Fill white background
        imagefill($img, 0, 0, $white);
        
        // Draw border
        imagerectangle($img, 10, 10, 289, 289, $gray);
        imagerectangle($img, 11, 11, 288, 288, $gray);
        
        // Add "QR CODE" text at top
        $text1 = "QR CODE";
        imagestring($img, 5, 110, 50, $text1, $black);
        
        // Add LRN in center (large)
        $lrnText = substr($data, 0, 13); // Limit to 13 chars (LRN length)
        imagestring($img, 5, 90, 140, $lrnText, $black);
        
        // Add instruction text at bottom
        $text2 = "Scan to mark attendance";
        imagestring($img, 3, 65, 240, $text2, $gray);
        
        // Draw decorative corner squares (QR code style)
        $cornerSize = 30;
        // Top-left
        imagefilledrectangle($img, 30, 30, 30 + $cornerSize, 30 + $cornerSize, $black);
        imagefilledrectangle($img, 35, 35, 35 + $cornerSize - 10, 35 + $cornerSize - 10, $white);
        // Top-right
        imagefilledrectangle($img, 240, 30, 240 + $cornerSize, 30 + $cornerSize, $black);
        imagefilledrectangle($img, 245, 35, 245 + $cornerSize - 10, 35 + $cornerSize - 10, $white);
        // Bottom-left
        imagefilledrectangle($img, 30, 240, 30 + $cornerSize, 240 + $cornerSize, $black);
        imagefilledrectangle($img, 35, 245, 35 + $cornerSize - 10, 245 + $cornerSize - 10, $white);
        
        // Save to file
        $result = imagepng($img, $filepath);
        imagedestroy($img);
        
        if (!$result) {
            error_log("Failed to save fallback QR image");
            return false;
        }
        
        error_log("Generated fallback QR code for: " . $data . " (using GD)");
        return true;
        
    } catch (Exception $e) {
        error_log("Local QR generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Regenerate QR code for existing student
 * @param int $studentId Student ID
 * @param string $lrn Student LRN
 * @param string $name Student full name
 * @return string|false Path to QR code file or false on failure
 */
function regenerateStudentQRCode($studentId, $lrn, $name = '') {
    // Delete old QR code if exists
    $oldPath = __DIR__ . '/../uploads/qrcodes/student_' . $studentId . '.png';
    if (file_exists($oldPath)) {
        unlink($oldPath);
    }
    
    // Generate new QR code
    return generateStudentQRCode($studentId, $lrn, $name);
}

/**
 * Get QR code path for a student
 * @param int $studentId Student ID
 * @return string|false Path to QR code or false if not exists
 */
function getStudentQRCodePath($studentId) {
    $filename = 'student_' . $studentId . '.png';
    $filepath = __DIR__ . '/../uploads/qrcodes/' . $filename;
    
    if (file_exists($filepath)) {
        return 'uploads/qrcodes/' . $filename;
    }
    
    return false;
}

/**
 * Delete QR code for a student
 * @param int $studentId Student ID
 * @return bool Success status
 */
function deleteStudentQRCode($studentId) {
    $filepath = __DIR__ . '/../uploads/qrcodes/student_' . $studentId . '.png';
    
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return true; // Already deleted
}

/**
 * Check if QR code exists for a student
 * @param int $studentId Student ID
 * @return bool
 */
function studentQRCodeExists($studentId) {
    $filepath = __DIR__ . '/../uploads/qrcodes/student_' . $studentId . '.png';
    return file_exists($filepath);
}

/**
 * Generate generic QR code (used for teachers)
 * @param string $identifier Unique identifier (Data content and part of filename)
 * @param string $type Type of entity (student, teacher)
 * @return string|false Path to QR code file or false on failure
 */
function generateQRCode($identifier, $type = 'student') {
    try {
        // Define upload directory
        $uploadDir = __DIR__ . '/../uploads/qrcodes/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                error_log("Failed to create QR code directory: " . $uploadDir);
                return false;
            }
        }
        
        // Ensure directory is writable
        if (!is_writable($uploadDir)) {
            chmod($uploadDir, 0777);
        }
        
        // Generate filename based on type
        // For teachers, we use employee_id in filename since we might not have DB ID yet
        $prefix = ($type === 'teacher') ? 'teacher_' : 'student_';
        
        // Sanitize identifier for filename
        $safeIdentifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $identifier);
        $filename = $prefix . $safeIdentifier . '.png';
        $filepath = $uploadDir . $filename;
        
        // Generate QR code using ZXing API
        $success = generateQRCodeWithZXing($identifier, $filepath);
        
        if ($success && file_exists($filepath)) {
            // Return relative path for database storage
            return 'uploads/qrcodes/' . $filename;
        } else {
            error_log("QR code file was not created: " . $filepath);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("QR code generation error: " . $e->getMessage());
        return false;
    }
}
