<?php
// Manage Students - Add/Edit Form Only
require_once 'config.php';
require_once __DIR__ . '/../includes/qrcode_helper.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = isset($_GET['id']) ? 'Edit Student' : 'Add Student';
$pageIcon = isset($_GET['id']) ? 'user-edit' : 'user-plus';

// Add external CSS for manage students with cache buster
$additionalCSS = ['../css/manage-students.css?v=' . time()];

// Initialize variables
$message = '';
$messageType = 'info';
$editMode = false;
$editStudent = null;

// Check if editing
if (isset($_GET['id'])) {
    $editMode = true;
    $editId = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$editId]);
        $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editStudent) {
            $message = "Student not found.";
            $messageType = "error";
        }
    } catch (Exception $e) {
        $message = "Error retrieving student information.";
        $messageType = "error";
        error_log("Edit student error: " . $e->getMessage());
    }
}

// Function to auto-create section if it doesn't exist
function autoCreateSection($pdo, $sectionName, $studentClass = '') {
    if (empty($sectionName)) {
        return ['success' => false, 'message' => 'Section name is required'];
    }
    
    try {
        // Check if section already exists (case-insensitive)
        $checkStmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE LOWER(section_name) = LOWER(?)");
        $checkStmt->execute([$sectionName]);
        $existingSection = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSection) {
            // Section already exists
            return ['success' => true, 'message' => 'Section already exists', 'section_id' => $existingSection['id'], 'exists' => true];
        }
        
        // Extract grade level from student's class field (e.g., "Grade 12" -> "12", "Kindergarten" -> "K")
        $gradeLevel = '';
        if (!empty($studentClass)) {
             if (preg_match('/^Grade\s+(\d{1,2})$/i', $studentClass, $matches)) {
                $gradeLevel = $matches[1];
            }
        }
        
        // Fallback: Extract from section name if class didn't provide (e.g., "12-BARBERRA" -> "12")
        if (empty($gradeLevel) && preg_match('/^(\d{1,2})[-_\s]/', $sectionName, $matches)) {
            $gradeLevel = $matches[1];
        }
        
        // Get current school year
        $currentYear = date('Y');
        $nextYear = $currentYear + 1;
        $schoolYear = $currentYear . '-' . $nextYear;
        
            // Insert new section with proper error handling
            $insertStmt = $pdo->prepare("
                INSERT INTO sections (section_name, grade_level, school_year, is_active, created_at) 
                VALUES (?, ?, ?, 1, NOW())
            ");
            
            $inserted = $insertStmt->execute([$sectionName, $gradeLevel, $schoolYear]);        if ($inserted) {
            $newSectionId = $pdo->lastInsertId();
            error_log("Section created successfully: $sectionName (ID: $newSectionId, Grade: $gradeLevel)");
            return ['success' => true, 'message' => 'Section created successfully', 'section_id' => $newSectionId, 'exists' => false];
        } else {
            error_log("Failed to insert section: $sectionName");
            return ['success' => false, 'message' => 'Failed to create section'];
        }
        
    } catch (PDOException $e) {
        // Handle duplicate entry errors specifically
        if ($e->getCode() == 23000) {
            error_log("Duplicate section detected: $sectionName - " . $e->getMessage());
            // Try to get the existing section ID
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM sections WHERE LOWER(section_name) = LOWER(?)");
                $checkStmt->execute([$sectionName]);
                $existingSection = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if ($existingSection) {
                    return ['success' => true, 'message' => 'Section already exists', 'section_id' => $existingSection['id'], 'exists' => true];
                }
            } catch (Exception $inner) {
                error_log("Error fetching existing section: " . $inner->getMessage());
            }
            return ['success' => false, 'message' => 'Section already exists but could not be retrieved'];
        }
        error_log("Auto-create section error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("Auto-create section error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error creating section: ' . $e->getMessage()];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "Security validation failed. Please try again.";
        $messageType = "error";
    } else {
        $action = $_POST['action'] ?? '';
        $lrn = trim($_POST['lrn'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $sex = trim($_POST['sex'] ?? '');
        $mobileNumber = trim($_POST['mobile_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $section = trim($_POST['section'] ?? '');
        
        // Normalize class input (auto-format grade levels)
        if (!empty($class)) {
            // If user entered just a number (1-12), convert to "Grade X"
            if (preg_match('/^(\d{1,2})$/', $class, $matches)) {
                $gradeNum = intval($matches[1]);
                if ($gradeNum >= 1 && $gradeNum <= 12) {
                    $class = "Grade " . $gradeNum;
                }
            }
            // Note: Kindergarten and Elementary (G1-6) are not supported in this system
        }
        
        // Validation
        if (empty($lrn) || empty($firstName) || empty($lastName) || empty($sex) || empty($mobileNumber) || empty($class) || empty($section)) {
            $message = "All required fields must be filled.";
            $messageType = "error";
        } elseif (!in_array($sex, ['Male', 'Female'])) {
            $message = "Please select a valid sex.";
            $messageType = "error";
        } elseif (!preg_match('/^\d{11,13}$/', $lrn)) {
            $message = "LRN must be 11-13 digits only.";
            $messageType = "error";
        } elseif (!preg_match('/^(09|\+639)\d{9}$/', preg_replace('/[^0-9+]/', '', $mobileNumber))) {
            $message = "Please enter a valid Philippine mobile number (09XX-XXX-XXXX).";
            $messageType = "error";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = "error";
        } elseif (!preg_match('/^Grade\s+(7|8|9|10|11|12)$/i', $class)) {
            $message = "Grade Level must be Grade 7 to Grade 12 (Junior/Senior High School only).";
            $messageType = "error";
        } else {
            try {
                if ($action === 'add') {
                    // Check if LRN already exists
                    $stmt = $pdo->prepare("SELECT id FROM students WHERE lrn = ?");
                    $stmt->execute([$lrn]);
                    if ($stmt->fetch()) {
                        $message = "A student with this LRN already exists.";
                        $messageType = "error";
                    } else {
                        // Auto-create section if it doesn't exist (pass student's class for grade level extraction)
                        $sectionResult = autoCreateSection($pdo, $section, $class);
                        
                        // Log the section creation result for debugging
                        if ($sectionResult['success']) {
                            error_log("Section check for '$section': " . ($sectionResult['exists'] ? 'Already exists' : 'Created new'));
                        } else {
                            error_log("Section creation failed for '$section': " . $sectionResult['message']);
                        }
                        
                        // Insert new student with both mobile and email
                        $stmt = $pdo->prepare("
                            INSERT INTO students (lrn, first_name, last_name, middle_name, sex, mobile_number, email, class, section, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$lrn, $firstName, $lastName, $middleName, $sex, $mobileNumber, $email, $class, $section]);
                        
                        // Get the newly inserted student ID
                        $newStudentId = $pdo->lastInsertId();
                        
                        // Generate QR code automatically
                        $studentFullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                        $qrCodePath = generateStudentQRCode($newStudentId, $lrn, $studentFullName);
                        
                        // Update student record with QR code path
                        if ($qrCodePath) {
                            $updateStmt = $pdo->prepare("UPDATE students SET qr_code = ? WHERE id = ?");
                            $updateStmt->execute([$qrCodePath, $newStudentId]);
                            
                            $message = "Student added successfully with QR code generated!";
                        } else {
                            $message = "Student added successfully, but QR code generation failed. You can regenerate it later.";
                        }
                        
                        $messageType = "success";
                        
                        // Clear form data
                        $_POST = [];
                    }
                } elseif ($action === 'edit' && $editStudent) {
                    // Check if LRN exists for other students
                    $stmt = $pdo->prepare("SELECT id FROM students WHERE lrn = ? AND id != ?");
                    $stmt->execute([$lrn, $editStudent['id']]);
                    if ($stmt->fetch()) {
                        $message = "Another student with this LRN already exists.";
                        $messageType = "error";
                    } else {
                        // Auto-create section if it doesn't exist (in case section changed, pass student's class for grade level)
                        $sectionResult = autoCreateSection($pdo, $section, $class);
                        
                        // Log the section creation result for debugging
                        if ($sectionResult['success']) {
                            error_log("Section check for '$section': " . ($sectionResult['exists'] ? 'Already exists' : 'Created new'));
                        } else {
                            error_log("Section update failed for '$section': " . $sectionResult['message']);
                        }
                        
                        // Update student
                        // Update student with both mobile and email
                        $stmt = $pdo->prepare("
                            UPDATE students 
                            SET lrn = ?, first_name = ?, last_name = ?, middle_name = ?, sex = ?, mobile_number = ?, email = ?, class = ?, section = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$lrn, $firstName, $lastName, $middleName, $sex, $mobileNumber, $email, $class, $section, $editStudent['id']]);
                        
                        // If LRN changed, regenerate QR code
                        if ($lrn !== $editStudent['lrn']) {
                            $studentFullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                            $qrCodePath = regenerateStudentQRCode($editStudent['id'], $lrn, $studentFullName);
                            
                            if ($qrCodePath) {
                                $updateStmt = $pdo->prepare("UPDATE students SET qr_code = ? WHERE id = ?");
                                $updateStmt->execute([$qrCodePath, $editStudent['id']]);
                                
                                $message = "Student updated successfully with QR code regenerated!";
                            } else {
                                $message = "Student updated successfully, but QR code regeneration failed.";
                            }
                        } else {
                            $message = "Student updated successfully!";
                        }
                        
                        $messageType = "success";
                        
                        // Refresh edit student data
                        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                        $stmt->execute([$editStudent['id']]);
                        $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Exception $e) {
                $message = "Database error occurred. Please try again.";
                $messageType = "error";
                error_log("Student form error: " . $e->getMessage());
            }
        }
    }
}

// Get available classes and sections for suggestions
$availableClasses = [];
$availableSections = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class");
    $availableClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND section != '' ORDER BY section");
    $availableSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Ignore error, just won't have suggestions
}

include 'includes/header_modern.php';
?>

<style>
    /* ===== ASJ MODERN CSS VARIABLES ===== */
    :root {
        /* ASJ Brand Colors */
        --asj-green-50: #E8F5E9;
        --asj-green-100: #C8E6C9;
        --asj-green-400: #66BB6A;
        --asj-green-500: #4CAF50;
        --asj-green-600: #43A047;
        --asj-green-700: #388E3C;
        --asj-gold-50: #FFF9E6;
        --asj-gold-400: #FFD54F;
        --asj-gold-500: #FFC107;
        --asj-gold-600: #FFB300;
        
        /* Modern Neutrals */
        --neutral-50: #FAFBFC;
        --neutral-100: #F4F6F8;
        --neutral-200: #E5E9ED;
        --neutral-300: #D0D7DE;
        --neutral-400: #8B96A5;
        --neutral-500: #6E7C8C;
        --neutral-600: #556575;
        --neutral-700: #3E4C59;
        --neutral-900: #1F2937;
        
        /* Semantic Colors */
        --success-light: #D1FAE5;
        --success: #10B981;
        --success-dark: #059669;
        --error-light: #FEE2E2;
        --error: #EF4444;
        --error-dark: #DC2626;
        --warning-light: #FEF3C7;
        --warning: #F59E0B;
        --warning-dark: #D97706;
        --info-light: #DBEAFE;
        --info: #3B82F6;
        --info-dark: #2563EB;
        
        /* Legacy compatibility */
        --primary-50: #E8F5E9;
        --primary-100: #C8E6C9;
        --primary-400: #66BB6A;
        --primary-500: #4CAF50;
        --primary-600: #43A047;
        --primary-700: #388E3C;
        --accent-500: #FFC107;
        --accent-600: #FFB300;
        --blue-50: #DBEAFE;
        --red-500: #EF4444;
        --red-600: #DC2626;
        --gray-50: #FAFBFC;
        --gray-100: #F4F6F8;
        --gray-200: #E5E9ED;
        --gray-300: #D0D7DE;
        --gray-400: #8B96A5;
        --gray-500: #6E7C8C;
        --gray-600: #556575;
        --gray-700: #3E4C59;
        --gray-900: #1F2937;
        
        /* Spacing */
        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.25rem;
        --space-6: 1.5rem;
        --space-8: 2rem;
        --space-12: 3rem;
        
        /* Border Radius */
        --radius-sm: 6px;
        --radius-md: 10px;
        --radius-lg: 14px;
        --radius-xl: 18px;
        --radius-2xl: 24px;
        --radius-full: 9999px;
        
        /* Typography */
        --text-xs: 0.75rem;
        --text-sm: 0.875rem;
        --text-base: 1rem;
        --text-lg: 1.125rem;
        --text-xl: 1.25rem;
        --text-2xl: 1.5rem;
        --text-6xl: 3.75rem;
        
        /* Shadows */
        --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.04);
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07), 0 2px 4px rgba(0, 0, 0, 0.05);
        --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.12), 0 10px 10px rgba(0, 0, 0, 0.04);
        --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.15);
        
        /* Transitions */
        --transition-fast: 150ms ease-in-out;
        --transition-base: 200ms ease-in-out;
        --transition-slow: 300ms ease-in-out;
    }

    /* ===== PAGE HEADER ENHANCED - ASJ THEMED ===== */
    .page-header-enhanced {
        position: relative;
        background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%);
        border-radius: var(--radius-2xl);
        margin-bottom: var(--space-8);
        overflow: hidden;
        box-shadow: 0 10px 40px -10px rgba(76, 175, 80, 0.4);
        animation: headerSlideIn 0.6s ease-out;
    }

    @keyframes headerSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .page-header-background {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        overflow: hidden;
    }

    .header-gradient-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.1) 0%, transparent 60%);
    }

    .header-pattern {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        opacity: 0.05;
        background-image: 
            repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255, 255, 255, 0.1) 10px, rgba(255, 255, 255, 0.1) 20px),
            repeating-linear-gradient(-45deg, transparent, transparent 10px, rgba(255, 255, 255, 0.1) 10px, rgba(255, 255, 255, 0.1) 20px);
    }

    .page-header-content-enhanced {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--space-8) var(--space-6);
        gap: var(--space-6);
        z-index: 1;
    }

    .page-title-section {
        display: flex;
        align-items: center;
        gap: var(--space-5);
        flex: 1;
    }

    .page-icon-enhanced {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: var(--radius-2xl);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .page-icon-enhanced:hover {
        transform: translateY(-4px) rotate(5deg);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
    }

    .page-title-content {
        flex: 1;
        min-width: 0;
    }

    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        margin-bottom: var(--space-2);
        font-size: var(--text-sm);
    }

    .breadcrumb-link {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all var(--transition-base);
        padding: var(--space-1) var(--space-2);
        border-radius: var(--radius-md);
    }

    .breadcrumb-link:hover {
        color: white;
        background: rgba(255, 255, 255, 0.1);
    }

    .breadcrumb-separator {
        color: rgba(255, 255, 255, 0.5);
        font-size: 10px;
    }

    .breadcrumb-current {
        color: white;
        font-weight: 600;
    }

    .page-title-enhanced {
        color: white;
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
        line-height: 1.2;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .page-subtitle-enhanced {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        color: rgba(255, 255, 255, 0.9);
        font-size: var(--text-base);
        margin: var(--space-2) 0 0;
        font-weight: 400;
    }

    .page-actions-enhanced {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        flex-shrink: 0;
    }

    .btn-header {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        padding: var(--space-3) var(--space-5);
        border: none;
        border-radius: var(--radius-lg);
        font-size: var(--text-base);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
        position: relative;
        overflow: hidden;
        text-decoration: none;
    }

    .btn-header::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn-header:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn-header-secondary {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .btn-header-secondary:hover {
        background: rgba(255, 255, 255, 0.25);
        border-color: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .btn-header-primary {
        background: white;
        color: var(--primary-600);
        border: 2px solid white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-header-primary:hover {
        background: var(--gray-50);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .btn-header i {
        position: relative;
        z-index: 1;
    }

    .btn-header span {
        position: relative;
        z-index: 1;
    }

    /* Responsive Header */
    @media (max-width: 992px) {
        .page-header-content-enhanced {
            flex-direction: column;
            align-items: flex-start;
            padding: var(--space-6) var(--space-5);
        }
        
        .page-title-section {
            width: 100%;
        }
        
        .page-actions-enhanced {
            width: 100%;
            justify-content: flex-start;
        }
        
        .page-icon-enhanced {
            width: 64px;
            height: 64px;
            font-size: 26px;
        }
        
        .page-title-enhanced {
            font-size: 2rem;
        }
    }

    @media (max-width: 768px) {
        .page-header-content-enhanced {
            padding: var(--space-5) var(--space-4);
        }
        
        .page-title-section {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }
        
        .page-icon-enhanced {
            width: 56px;
            height: 56px;
            font-size: 22px;
        }
        
        .page-title-enhanced {
            font-size: 1.75rem;
        }
        
        .page-actions-enhanced {
            flex-direction: column;
            width: 100%;
        }
        
        .btn-header {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .page-header-enhanced {
            border-radius: var(--radius-xl);
            margin-bottom: var(--space-6);
        }
        
        .page-header-content-enhanced {
            padding: var(--space-4) var(--space-3);
        }
        
        .breadcrumb-nav {
            flex-wrap: wrap;
        }
        
        .page-title-enhanced {
            font-size: 1.5rem;
        }
        
        .page-subtitle-enhanced {
            font-size: var(--text-sm);
        }
        
        .btn-header {
            padding: var(--space-2) var(--space-4);
            font-size: var(--text-sm);
        }
    }
</style>

<!-- Page Header - Enhanced Design -->
<div class="page-header-enhanced">
    <div class="page-header-background">
        <div class="header-gradient-overlay"></div>
        <div class="header-pattern"></div>
    </div>
    <div class="page-header-content-enhanced">
        <div class="page-title-section">
            <div class="page-icon-enhanced">
                <i class="fas fa-<?php echo $pageIcon; ?>"></i>
            </div>
            <div class="page-title-content">
                <div class="breadcrumb-nav">
                    <a href="dashboard.php" class="breadcrumb-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <i class="fas fa-chevron-right breadcrumb-separator"></i>
                    <a href="view_students.php" class="breadcrumb-link">
                        <i class="fas fa-users"></i>
                        <span>Students</span>
                    </a>
                    <i class="fas fa-chevron-right breadcrumb-separator"></i>
                    <span class="breadcrumb-current"><?php echo $pageTitle; ?></span>
                </div>
                <h1 class="page-title-enhanced"><?php echo $pageTitle; ?></h1>
                <p class="page-subtitle-enhanced">
                    <i class="fas fa-info-circle"></i>
                    <span><?php echo $editMode ? 'Update student information and manage records' : 'Register a new student in the system'; ?></span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <?php if ($editMode): ?>
                <button class="btn-header btn-header-secondary" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </button>
                <a href="manage_students.php" class="btn-header btn-header-primary">
                    <i class="fas fa-user-plus"></i>
                    <span>Add New</span>
                </a>
            <?php else: ?>
                <a href="view_students.php" class="btn-header btn-header-secondary">
                    <i class="fas fa-list"></i>
                    <span>View All</span>
                </a>
                <button class="btn-header btn-header-primary" onclick="document.getElementById('lrn').focus()">
                    <i class="fas fa-keyboard"></i>
                    <span>Quick Start</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Page Content -->
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: var(--space-6); animation: slideDown 0.3s ease;">
        <div class="alert-icon">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        </div>
        <div class="alert-content">
            <?php echo sanitizeOutput($message); ?>
        </div>
    </div>
<?php endif; ?>

<!-- Student Form -->
<div class="form-card">
    <div class="form-card-header">
        <h3 class="form-card-title">
            <i class="fas fa-<?php echo $editMode ? 'user-edit' : 'user-plus'; ?>"></i>
            <?php echo $editMode ? 'Edit Student' : 'Add New Student'; ?>
        </h3>
        <div class="card-actions">
            <a href="view_students.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                <span>Back to List</span>
            </a>
        </div>
    </div>
    <div class="form-card-body">
        <?php if ($editMode && !$editStudent): ?>
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    Student not found. <a href="view_students.php" style="color: inherit; text-decoration: underline;">Return to student list</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="" class="student-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'edit' : 'add'; ?>">
                <?php if ($editMode): ?>
                    <input type="hidden" name="id" value="<?php echo $editStudent['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label for="lrn">
                            LRN (Learner Reference Number)
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="lrn" 
                               name="lrn" 
                               class="form-input" 
                               required 
                               pattern="[0-9]{11,13}" 
                               maxlength="13" 
                               minlength="11"
                               placeholder="Enter 11-13 digit LRN"
                               value="<?php echo sanitizeOutput($editStudent['lrn'] ?? ''); ?>">
                        <small class="form-help">Must be 11-13 digits (e.g., 123456789012)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="class">
                            Grade Level (Class)
                            <span class="required">*</span>
                        </label>
                        <select id="class" 
                                name="class" 
                                class="form-select" 
                                required>
                            <option value="">Select Grade Level</option>
                            <optgroup label="Junior High School">
                                <?php 
                                $juniorGrades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'];
                                foreach ($juniorGrades as $grade): 
                                    $selected = (($editStudent['class'] ?? '') === $grade) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $grade; ?>" <?php echo $selected; ?>><?php echo $grade; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Senior High School">
                                <?php 
                                $seniorGrades = ['Grade 11', 'Grade 12'];
                                foreach ($seniorGrades as $grade): 
                                    $selected = (($editStudent['class'] ?? '') === $grade) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $grade; ?>" <?php echo $selected; ?>><?php echo $grade; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <small class="form-help">Student's grade level (Grade 7 to Grade 12)</small>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="section">
                            Section
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="section" 
                               name="section" 
                               class="form-input" 
                               required 
                               maxlength="100"
                               placeholder="Enter section name (e.g., BARBERRA, SAMPAGUITA, A, B)"
                               value="<?php echo sanitizeOutput($editStudent['section'] ?? ''); ?>"
                               list="section-suggestions">
                        <datalist id="section-suggestions">
                            <?php foreach ($availableSections as $sectionName): ?>
                                <option value="<?php echo sanitizeOutput($sectionName); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <small class="form-help">Student's section (e.g., BARBERRA, SAMPAGUITA, Kalachuchi)</small>
                    </div>
                </div>
                
                <div class="form-grid three-col">
                    <div class="form-group">
                        <label for="first_name">
                            First Name
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               class="form-input" 
                               required 
                               maxlength="50"
                               placeholder="Enter first name"
                               value="<?php echo sanitizeOutput($editStudent['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" 
                               id="middle_name" 
                               name="middle_name" 
                               class="form-input" 
                               maxlength="50"
                               placeholder="Enter middle name (optional)"
                               value="<?php echo sanitizeOutput($editStudent['middle_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">
                            Last Name
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               class="form-input" 
                               required 
                               maxlength="50"
                               placeholder="Enter last name"
                               value="<?php echo sanitizeOutput($editStudent['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label for="sex">
                            Sex
                            <span class="required">*</span>
                        </label>
                        <select id="sex" 
                                name="sex" 
                                class="form-select" 
                                required>
                            <option value="">Select Sex</option>
                            <option value="Male" <?php echo (($editStudent['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($editStudent['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                        <small class="form-help">Required for SF2 reporting</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="mobile_number">
                            Mobile Number
                            <span class="required">*</span>
                        </label>
                        <input type="tel" 
                               id="mobile_number" 
                               name="mobile_number" 
                               class="form-input" 
                               required 
                               maxlength="15"
                               placeholder="09XX-XXX-XXXX"
                               value="<?php echo sanitizeOutput($editStudent['mobile_number'] ?? ''); ?>">
                        <small class="form-help">Parent's mobile number for SMS notifications</small>
                    </div>
                </div>
                
                <!-- Note: Email field removed - mobile stored in email column until migration -->
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="email">
                            Parent/Guardian Email
                            <span class="optional">(optional)</span>
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               maxlength="100"
                               placeholder="parent@example.com"
                               value="<?php echo sanitizeOutput($editStudent['email'] ?? ''); ?>">
                        <small class="form-help">Optional email for additional notifications</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-<?php echo $editMode ? 'save' : 'plus'; ?>"></i>
                        <span><?php echo $editMode ? 'Update Student' : 'Add Student'; ?></span>
                    </button>
                    <a href="view_students.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </a>
                    <?php if ($editMode): ?>
                        <a href="manage_students.php" class="btn btn-success">
                            <i class="fas fa-plus"></i>
                            <span>Add New Student</span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($editMode && $editStudent): ?>
    <!-- Additional Information -->
    <div class="info-card">
        <h3 class="form-card-title" style="margin-bottom: var(--space-5);">
            <i class="fas fa-info-circle"></i>
            Student Information
        </h3>
        
        <div class="info-grid">
            <div class="info-item">
                <label>Student ID</label>
                <span>#<?php echo str_pad($editStudent['id'], 5, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-item">
                <label>Registration Date</label>
                <span><?php echo date('F d, Y', strtotime($editStudent['created_at'])); ?></span>
            </div>
            <div class="info-item">
                <label>Last Updated</label>
                <span><?php echo date('F d, Y', strtotime($editStudent['updated_at'] ?? $editStudent['created_at'])); ?></span>
            </div>
        </div>
        
        <div class="quick-actions-section">
            <h4 class="quick-actions-title">Quick Actions</h4>
            <div class="action-buttons">
                <button 
                    class="btn btn-primary"
                    data-action="generate-qr"
                    data-lrn="<?php echo htmlspecialchars($editStudent['lrn']); ?>"
                    data-name="<?php echo htmlspecialchars($editStudent['first_name'] . ' ' . $editStudent['last_name']); ?>">
                    <i class="fas fa-qrcode"></i>
                    <span>Generate QR Code</span>
                </button>
                <a href="attendance_reports.php?lrn=<?php echo urlencode($editStudent['lrn']); ?>" 
                   class="btn btn-success" target="_blank">
                    <i class="fas fa-chart-line"></i>
                    <span>View Attendance</span>
                </a>
                <button class="btn btn-danger" data-action="show-delete-modal">
                    <i class="fas fa-trash"></i>
                    <span>Delete Student</span>
                </button>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qr-modal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <i class="fas fa-qrcode"></i>
                        Student QR Code
                    </h3>
                    <button class="modal-close" data-action="close-modal" data-modal="qr-modal" aria-label="Close modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="text-align: center;">
                    <h4 id="qr-student-name" style="margin-bottom: var(--space-4); color: var(--gray-900);"></h4>
                    <div id="qr-code-container" style="display: inline-block; padding: var(--space-4); background: white; border-radius: var(--radius-lg); margin-bottom: var(--space-4);"></div>
                    <p style="color: var(--gray-600); margin-bottom: var(--space-5);">Student can scan this QR code to mark attendance.</p>
                    <div class="modal-actions">
                        <button class="btn btn-primary" data-action="print-qr-manage">
                            <i class="fas fa-print"></i>
                            <span>Print QR Code</span>
                        </button>
                        <button class="btn btn-secondary" data-action="close-modal" data-modal="qr-modal">
                            <i class="fas fa-times"></i>
                            <span>Close</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <div class="modal-content">
                <div class="modal-body" style="text-align: center; padding: var(--space-8);">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-5);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 32px; color: var(--red-500);"></i>
                    </div>
                    <h3 style="font-size: var(--text-2xl); font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-3);">Delete Student</h3>
                    <p style="color: var(--gray-600); margin-bottom: var(--space-2);">
                        Are you sure you want to delete <strong><?php echo sanitizeOutput($editStudent['first_name'] . ' ' . $editStudent['last_name']); ?></strong>?
                    </p>
                    <p style="color: var(--red-600); font-weight: 600; font-size: var(--text-sm); margin-bottom: var(--space-6);">
                        ⚠️ This action cannot be undone and will delete all attendance records.
                    </p>
                    <div class="modal-actions" style="gap: var(--space-3);">
                        <form method="POST" action="../api/delete_student.php" style="display: inline-block;">
                            <input type="hidden" name="student_id" value="<?php echo $editStudent['id']; ?>">
                            <button type="submit" class="btn btn-danger" style="min-width: 160px;">
                                <i class="fas fa-trash"></i>
                                <span>Yes, Delete</span>
                            </button>
                        </form>
                        <button class="btn btn-secondary" data-action="close-modal" data-modal="delete-modal" style="min-width: 160px;">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<style>
    /* Modern Modal Styles */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: var(--space-4);
        animation: fadeIn 0.2s ease;
    }

    .modal-container {
        width: 100%;
        max-width: 500px;
        animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-content {
        background: white;
        border-radius: var(--radius-2xl);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    .modal-header {
        padding: var(--space-5);
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--gray-50);
    }

    .modal-title {
        font-size: var(--text-xl);
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .modal-title i {
        color: var(--asj-green-600);
    }

    .modal-close {
        width: 36px;
        height: 36px;
        border-radius: var(--radius-lg);
        border: none;
        background: transparent;
        color: var(--gray-500);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: var(--gray-200);
        color: var(--gray-700);
    }

    .modal-body {
        padding: var(--space-6);
    }

    .modal-actions {
        display: flex;
        gap: var(--space-3);
        justify-content: center;
        flex-wrap: wrap;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
<?php endif; ?>

<!-- Include QR Code Library -->
<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<!-- Mobile-First Responsive CSS -->
<link rel="stylesheet" href="../css/admin-students-mobile.css">

<!-- External JavaScript - All functionality moved to external file -->
<script src="../js/admin-students.js"></script>

<script>
// Auto-format section name to uppercase
document.addEventListener('DOMContentLoaded', function() {
    const sectionInput = document.getElementById('section');
    if (sectionInput) {
        sectionInput.addEventListener('input', function() {
            // Convert to uppercase for consistency
            this.value = this.value.toUpperCase();
        });
    }
    
    // Add visual feedback for class selection
    const classSelect = document.getElementById('class');
    if (classSelect) {
        classSelect.addEventListener('change', function() {
            if (this.value) {
                this.style.borderColor = '#10b981';
                setTimeout(() => {
                    this.style.borderColor = '';
                }, 1000);
            }
        });
    }
});
</script>

<?php include 'includes/footer_modern.php'; ?>
