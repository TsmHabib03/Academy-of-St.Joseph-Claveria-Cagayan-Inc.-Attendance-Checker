<?php
/**
 * DEPRECATED: This file is no longer used.
 * The system now uses ZXing library for QR code generation.
 * 
 * QR codes are now generated via:
 * - ZXing API (https://api.qrserver.com) - Primary method
 * - Local fallback generation - Backup method
 * 
 * See: includes/qrcode_helper.php for the new implementation
 * 
 * This file is kept for backward compatibility only and will be removed in future versions.
 */

// Legacy compatibility class - does nothing
class QRcode {
    public static function png($text, $outfile = false, $level = 'L', $size = 4, $margin = 1) {
        error_log("DEPRECATED: QRcode::png() called. Please use generateStudentQRCode() from qrcode_helper.php instead.");
        
        // Redirect to new implementation
        if (function_exists('generateQRCodeWithZXing')) {
            return generateQRCodeWithZXing($text, $outfile);
        }
        
        return false;
    }
}
