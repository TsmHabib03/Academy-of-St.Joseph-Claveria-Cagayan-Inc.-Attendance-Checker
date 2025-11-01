<?php
// Security: Admin-only access
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to login page with error message
    $_SESSION['access_denied'] = 'Access Denied. Only administrators can add students.';
    header('Location: admin/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6366f1">
    <title>Add Student - AttendEase (Admin Only)</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Modern Design CSS -->
    <link rel="stylesheet" href="css/modern-design.css">
    
    <style>
        /* Registration Form Specific Styles */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --error: #ef4444;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f9fafb;
            min-height: 100vh;
        }

        /* Header */
        .form-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .form-header-container {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-btn:hover {
            color: var(--primary);
        }

        .form-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }

        .help-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f3f4f6;
            border: none;
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .help-btn:hover {
            background: var(--primary);
            color: white;
        }

        /* Form Container */
        .form-container {
            max-width: 600px;
            margin: 3rem auto;
            padding: 0 1rem;
        }

        .form-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 3rem 2.5rem;
        }

        /* Welcome Section */
        .welcome-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .welcome-emoji {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .welcome-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.75rem;
        }

        .welcome-subtitle {
            color: #6b7280;
            font-size: 1.125rem;
        }

        /* Progress Indicator */
        .progress-section {
            margin-bottom: 3rem;
        }

        .progress-label {
            font-weight: 600;
            color: #111827;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-percentage {
            color: var(--primary);
            font-size: 0.875rem;
        }

        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--gradient-primary);
            border-radius: 999px;
            transition: width 0.5s ease;
        }

        /* Form Steps */
        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Floating Label Input */
        .input-group {
            position: relative;
            margin-bottom: 2rem;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 1.25rem 1rem 0.5rem;
            font-size: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            outline: none;
            transition: all 0.3s ease;
            background: white;
        }

        .input-group input:focus,
        .input-group select:focus {
            border-color: var(--primary);
        }

        .input-group input.valid {
            border-color: var(--success);
        }

        .input-group input.invalid {
            border-color: var(--error);
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .input-group label {
            position: absolute;
            left: 1rem;
            top: 1.25rem;
            font-size: 1rem;
            color: #9ca3af;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label,
        .input-group select:focus + label,
        .input-group select:not([value=""]) + label {
            top: 0.5rem;
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 600;
        }

        .input-group input.valid + label {
            color: var(--success);
        }

        .input-group input.invalid + label {
            color: var(--error);
        }

        /* Input Icons */
        .input-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.25rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .input-icon.success {
            color: var(--success);
        }

        .input-icon.error {
            color: var(--error);
        }

        .input-group input.valid ~ .input-icon.success,
        .input-group input.invalid ~ .input-icon.error {
            opacity: 1;
        }

        /* Help Text */
        .help-text {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .help-text i {
            font-size: 1rem;
        }

        /* Error Message */
        .error-message {
            display: none;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: var(--error);
        }

        .error-message.show {
            display: flex;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            flex: 1;
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            padding: 1rem 1.5rem;
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        /* Review Step */
        .review-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .review-item {
            padding: 1rem;
            background: #f9fafb;
            border-radius: 12px;
        }

        .review-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .review-value {
            font-weight: 600;
            color: #111827;
        }

        .confirmation-box {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            background: #f0fdf4;
            border: 2px solid #86efac;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .confirmation-box input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            cursor: pointer;
        }

        .confirmation-box label {
            flex: 1;
            color: #166534;
            font-weight: 500;
            cursor: pointer;
        }

        /* Success State */
        .success-state {
            display: none;
            text-align: center;
            padding: 2rem 0;
        }

        .success-state.show {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        .success-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        .success-icon i {
            font-size: 4rem;
            color: white;
        }

        .success-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 1rem;
        }

        .success-message {
            color: #6b7280;
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }

        .success-info {
            font-weight: 600;
            color: #111827;
            font-size: 1.25rem;
            margin-bottom: 2rem;
        }

        .success-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 400px;
            margin: 0 auto;
        }

        .qr-code-container {
            padding: 2rem;
            background: #f9fafb;
            border-radius: 16px;
            margin: 2rem 0;
        }

        /* Loading State */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 640px) {
            .form-card {
                padding: 2rem 1.5rem;
            }

            .welcome-title {
                font-size: 1.5rem;
            }

            .review-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn-secondary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="form-header">
        <div class="form-header-container">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back
            </a>
            <h1 class="form-title">Student Registration</h1>
            <button class="help-btn" onclick="showHelp()">
                <i class="fas fa-question"></i>
            </button>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-card">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-emoji">👋</div>
                <h2 class="welcome-title">Let's get you registered!</h2>
                <p class="welcome-subtitle">Quick and easy - takes less than 2 minutes</p>
            </div>

            <!-- Progress Indicator -->
            <div class="progress-section">
                <div class="progress-label">
                    <span id="progress-text">Step 1 of 3: Basic Information</span>
                    <span class="progress-percentage" id="progress-percentage">33%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill" style="width: 33%;"></div>
                </div>
            </div>

            <!-- Registration Form -->
            <form id="registration-form">
                <!-- Step 1: Basic Information -->
                <div class="form-step active" id="step1">
                    <div class="input-group">
                        <input type="text" id="firstName" name="first_name" placeholder=" " required>
                        <label for="firstName">First Name *</label>
                        <i class="fas fa-check-circle input-icon success"></i>
                        <i class="fas fa-exclamation-circle input-icon error"></i>
                        <div class="error-message" id="firstName-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Please enter a valid first name</span>
                        </div>
                    </div>

                    <div class="input-group">
                        <input type="text" id="lastName" name="last_name" placeholder=" " required>
                        <label for="lastName">Last Name *</label>
                        <i class="fas fa-check-circle input-icon success"></i>
                        <i class="fas fa-exclamation-circle input-icon error"></i>
                        <div class="error-message" id="lastName-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Please enter a valid last name</span>
                        </div>
                    </div>

                    <div class="input-group">
                        <input type="text" id="middleName" name="middle_name" placeholder=" ">
                        <label for="middleName">Middle Name (Optional)</label>
                    </div>

                    <div class="input-group">
                        <input type="text" id="lrn" name="lrn" placeholder=" " pattern="[0-9]{12}" maxlength="12" required>
                        <label for="lrn">LRN (Learner Reference Number) *</label>
                        <i class="fas fa-check-circle input-icon success"></i>
                        <i class="fas fa-exclamation-circle input-icon error"></i>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i>
                            <span>12-digit number found on your school ID</span>
                        </div>
                        <div class="error-message" id="lrn-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>LRN must be exactly 12 digits</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" onclick="nextStep(1)">
                            Continue
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Contact & Details -->
                <div class="form-step" id="step2">
                    <div class="input-group">
                        <input type="email" id="email" name="email" placeholder=" " required>
                        <label for="email">Email Address *</label>
                        <i class="fas fa-check-circle input-icon success"></i>
                        <i class="fas fa-exclamation-circle input-icon error"></i>
                        <div class="error-message" id="email-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Please enter a valid email address</span>
                        </div>
                    </div>

                    <div class="input-group">
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        <label for="gender">Gender *</label>
                        <i class="fas fa-check-circle input-icon success"></i>
                        <i class="fas fa-exclamation-circle input-icon error"></i>
                    </div>

                    <div class="input-group">
                        <input type="text" id="class" name="class" placeholder=" " required>
                        <label for="class">Grade Level / Section *</label>
                        <i class="fas fa-check-circle input-icon success"></i>
                        <i class="fas fa-exclamation-circle input-icon error"></i>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i>
                            <span>e.g., Grade 7 - Section A</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                            <i class="fas fa-arrow-left"></i>
                            Back
                        </button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                            Continue
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Review & Submit -->
                <div class="form-step" id="step3">
                    <div class="review-grid">
                        <div class="review-item">
                            <div class="review-label">Full Name</div>
                            <div class="review-value" id="review-name">-</div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">LRN</div>
                            <div class="review-value" id="review-lrn">-</div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">Email</div>
                            <div class="review-value" id="review-email">-</div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">Gender</div>
                            <div class="review-value" id="review-gender">-</div>
                        </div>
                        <div class="review-item" style="grid-column: 1 / -1;">
                            <div class="review-label">Grade Level / Section</div>
                            <div class="review-value" id="review-class">-</div>
                        </div>
                    </div>

                    <div class="confirmation-box">
                        <input type="checkbox" id="confirm" required>
                        <label for="confirm">
                            I confirm that all the information provided above is correct and accurate.
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="prevStep(3)">
                            <i class="fas fa-arrow-left"></i>
                            Back
                        </button>
                        <button type="submit" class="btn btn-primary" id="submit-btn">
                            <i class="fas fa-check-circle"></i>
                            Submit Registration
                        </button>
                    </div>
                </div>
            </form>

            <!-- Success State -->
            <div class="success-state" id="success-state">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h2 class="success-title">Registration Successful!</h2>
                <p class="success-message">Welcome, <strong id="success-name">Student</strong>!</p>
                <p class="success-info">Your LRN: <strong id="success-lrn">123456789012</strong></p>
                <p class="success-message">You can now scan your QR code to mark attendance.</p>
                
                <div class="qr-code-container" id="qr-code-display">
                    <!-- QR Code will be inserted here -->
                </div>

                <div class="success-actions">
                    <a href="scan_attendance.php" class="btn btn-primary">
                        <i class="fas fa-qrcode"></i>
                        Go to Scanner
                    </a>
                    <a href="index_modern.php" class="btn btn-secondary" style="width: 100%;">
                        <i class="fas fa-home"></i>
                        Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 3;

        // Form validation
        function validateField(fieldId) {
            const field = document.getElementById(fieldId);
            const value = field.value.trim();
            
            if (!value && field.required) {
                field.classList.add('invalid');
                field.classList.remove('valid');
                return false;
            }

            // Specific validation
            if (fieldId === 'lrn' && !/^[0-9]{12}$/.test(value)) {
                field.classList.add('invalid');
                field.classList.remove('valid');
                document.getElementById('lrn-error').classList.add('show');
                return false;
            } else {
                document.getElementById('lrn-error')?.classList.remove('show');
            }

            if (fieldId === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                field.classList.add('invalid');
                field.classList.remove('valid');
                document.getElementById('email-error').classList.add('show');
                return false;
            } else {
                document.getElementById('email-error')?.classList.remove('show');
            }

            if ((fieldId === 'firstName' || fieldId === 'lastName') && /[0-9]/.test(value)) {
                field.classList.add('invalid');
                field.classList.remove('valid');
                return false;
            }

            field.classList.add('valid');
            field.classList.remove('invalid');
            return true;
        }

        // Real-time validation
        ['firstName', 'lastName', 'lrn', 'email', 'gender', 'class'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.addEventListener('blur', () => validateField(fieldId));
            field.addEventListener('input', () => {
                if (field.classList.contains('invalid')) {
                    validateField(fieldId);
                }
            });
        });

        // Step navigation
        function nextStep(step) {
            // Validate current step
            let isValid = true;
            
            if (step === 1) {
                isValid = validateField('firstName') && 
                         validateField('lastName') && 
                         validateField('lrn');
            } else if (step === 2) {
                isValid = validateField('email') && 
                         validateField('gender') && 
                         validateField('class');
            }

            if (!isValid) return;

            // Update review section
            if (step === 2) {
                updateReview();
            }

            // Hide current step
            document.getElementById(`step${step}`).classList.remove('active');
            
            // Show next step
            currentStep = step + 1;
            document.getElementById(`step${currentStep}`).classList.add('active');
            
            // Update progress
            updateProgress();

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function prevStep(step) {
            document.getElementById(`step${step}`).classList.remove('active');
            currentStep = step - 1;
            document.getElementById(`step${currentStep}`).classList.add('active');
            updateProgress();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateProgress() {
            const progress = (currentStep / totalSteps) * 100;
            document.getElementById('progress-fill').style.width = progress + '%';
            document.getElementById('progress-percentage').textContent = Math.round(progress) + '%';
            
            const stepNames = [
                'Basic Information',
                'Contact & Details',
                'Review & Submit'
            ];
            document.getElementById('progress-text').textContent = 
                `Step ${currentStep} of ${totalSteps}: ${stepNames[currentStep - 1]}`;
        }

        function updateReview() {
            const firstName = document.getElementById('firstName').value;
            const lastName = document.getElementById('lastName').value;
            const middleName = document.getElementById('middleName').value;
            
            const fullName = middleName ? 
                `${firstName} ${middleName} ${lastName}` : 
                `${firstName} ${lastName}`;
            
            document.getElementById('review-name').textContent = fullName;
            document.getElementById('review-lrn').textContent = document.getElementById('lrn').value;
            document.getElementById('review-email').textContent = document.getElementById('email').value;
            document.getElementById('review-gender').textContent = document.getElementById('gender').value;
            document.getElementById('review-class').textContent = document.getElementById('class').value;
        }

        // Form submission
        document.getElementById('registration-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!document.getElementById('confirm').checked) {
                alert('Please confirm that all information is correct');
                return;
            }

            // Show loading
            document.getElementById('loading').classList.add('show');

            try {
                const formData = new FormData();
                formData.append('lrn', document.getElementById('lrn').value);
                formData.append('first_name', document.getElementById('firstName').value);
                formData.append('last_name', document.getElementById('lastName').value);
                formData.append('middle_name', document.getElementById('middleName').value);
                formData.append('gender', document.getElementById('gender').value);
                formData.append('email', document.getElementById('email').value);
                formData.append('class', document.getElementById('class').value);

                const response = await fetch('api/register_student.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                // Hide loading
                document.getElementById('loading').classList.remove('show');

                if (data.success) {
                    // Show success state
                    document.getElementById('registration-form').style.display = 'none';
                    document.querySelector('.welcome-section').style.display = 'none';
                    document.querySelector('.progress-section').style.display = 'none';
                    
                    const successState = document.getElementById('success-state');
                    successState.classList.add('show');
                    
                    document.getElementById('success-name').textContent = data.student_name;
                    document.getElementById('success-lrn').textContent = data.lrn;
                    document.getElementById('qr-code-display').innerHTML = data.qr_code;
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                document.getElementById('loading').classList.remove('show');
                alert('Network error. Please try again.');
            }
        });

        function showHelp() {
            alert('Need help?\n\nContact us:\n📧 info@attendease.edu\n📞 +63 123 456 7890');
        }
    </script>
</body>
</html>
