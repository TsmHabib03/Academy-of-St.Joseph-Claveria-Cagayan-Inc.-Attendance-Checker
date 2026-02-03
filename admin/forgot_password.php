<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset Your Admin Password - Academy of St. Joseph">
    <meta name="theme-color" content="#4CAF50">
    <title>Forgot Password - Academy of St. Joseph</title>
    
    <!-- Preload critical assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../css/asj-admin-theme.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔑</text></svg>">
</head>
<body>
    <!-- Background Effects -->
    <div class="background-gradient"></div>
    <div class="background-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Main Container -->
    <div class="login-container">
        <div class="login-card" id="forgotPasswordCard">
            <!-- Back Button -->
            <a href="login.php" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Login
            </a>

            <!-- Logo & Header -->
            <div class="login-header">
                <div class="logo-container">
                    <img src="../assets/asj-logo.png" alt="ASJ Logo" onerror="this.style.display='none';">
                </div>
                <h1 class="login-title">Forgot Password?</h1>
                <h2 class="login-subtitle-small">Academy of St. Joseph</h2>
                <p class="login-subtitle">Enter your email address and we'll send you a link to reset your password</p>
            </div>

            <!-- Alert Messages -->
            <div class="alert alert-success hidden" id="successAlert">
                <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <div class="alert-content">
                    <strong>Email Sent!</strong>
                    <p id="successMessage">If an account exists with this email, you will receive password reset instructions.</p>
                </div>
            </div>

            <div class="alert alert-error hidden" id="errorAlert">
                <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div class="alert-content">
                    <strong>Error</strong>
                    <p id="errorMessage">Something went wrong. Please try again.</p>
                </div>
                <button class="alert-close" onclick="this.parentElement.classList.add('hidden')">×</button>
            </div>

            <!-- Forgot Password Form -->
            <form class="login-form" id="forgotPasswordForm" novalidate>
                <!-- Email Input -->
                <div class="input-group" data-validate="email">
                    <div class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </div>
                    <input 
                        type="email" 
                        id="email" 
                        name="email"
                        placeholder=" " 
                        autocomplete="email"
                        required
                        autofocus
                    >
                    <label for="email">Email Address</label>
                    <div class="input-border"></div>
                    <div class="input-status">
                        <svg class="check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <svg class="error-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </div>
                    <div class="input-error-message">Please enter a valid email address</div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-signin" id="btnSubmit">
                    <span class="btn-text">
                        <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 2L11 13"></path>
                            <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                        </svg>
                        Send Reset Link
                    </span>
                    <span class="btn-loader hidden">
                        <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)"/>
                        </svg>
                        Sending...
                    </span>
                </button>
            </form>

            <!-- School Footer -->
            <div class="school-footer">
                <p>© 2025 Academy of St. Joseph, Claveria Cagayan Inc.</p>
                <p class="footer-subtitle">The Josephites: Integrity, Service, Excellence, Empowerment</p>
            </div>

            <!-- Security Badge -->
            <div class="security-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Your information is secure with us</span>
            </div>
        </div>
    </div>

    <style>
        /* Additional styles for forgot password page */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: var(--asj-green-600, #43A047);
        }

        .back-link svg {
            transition: transform 0.3s ease;
        }

        .back-link:hover svg {
            transform: translateX(-3px);
        }
    </style>

    <script>
        /**
         * Forgot Password Form Handler
         */
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const btnSubmit = document.getElementById('btnSubmit');
            const emailInput = document.getElementById('email');
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');

            // Email validation
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            // Show/hide loading state
            function setLoadingState(loading) {
                const btnText = btnSubmit.querySelector('.btn-text');
                const btnLoader = btnSubmit.querySelector('.btn-loader');
                
                if (loading) {
                    btnText.classList.add('hidden');
                    btnLoader.classList.remove('hidden');
                    btnSubmit.disabled = true;
                    emailInput.disabled = true;
                } else {
                    btnText.classList.remove('hidden');
                    btnLoader.classList.add('hidden');
                    btnSubmit.disabled = false;
                    emailInput.disabled = false;
                }
            }

            // Show success alert
            function showSuccess() {
                errorAlert.classList.add('hidden');
                successAlert.classList.remove('hidden');
                form.reset();
                
                // Scroll to alert
                successAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // Show error alert
            function showError(message) {
                successAlert.classList.add('hidden');
                errorMessage.textContent = message;
                errorAlert.classList.remove('hidden');
                
                // Scroll to alert
                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // Input validation on blur
            emailInput.addEventListener('blur', function() {
                const inputGroup = this.closest('.input-group');
                
                if (this.value.trim() === '') {
                    inputGroup.classList.remove('valid', 'invalid');
                } else if (validateEmail(this.value)) {
                    inputGroup.classList.add('valid');
                    inputGroup.classList.remove('invalid');
                } else {
                    inputGroup.classList.add('invalid');
                    inputGroup.classList.remove('valid');
                }
            });

            // Form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const email = emailInput.value.trim();

                // Validate email
                if (!email) {
                    showError('Please enter your email address');
                    emailInput.focus();
                    return;
                }

                if (!validateEmail(email)) {
                    showError('Please enter a valid email address');
                    emailInput.focus();
                    return;
                }

                // Hide alerts
                successAlert.classList.add('hidden');
                errorAlert.classList.add('hidden');

                // Show loading state
                setLoadingState(true);

                try {
                    console.log('Sending request to API...');
                    const response = await fetch('../api/request_password_reset.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ email: email })
                    });

                    console.log('Response status:', response.status);
                    console.log('Response ok:', response.ok);

                    // Try to get response text first
                    const responseText = await response.text();
                    console.log('Response text:', responseText);

                    setLoadingState(false);

                    // Try to parse as JSON
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        showError('Server error: Invalid response format. Response: ' + responseText.substring(0, 100));
                        return;
                    }

                    if (data.success) {
                        showSuccess();
                    } else {
                        showError(data.message || 'An error occurred. Please try again.');
                    }
                } catch (error) {
                    setLoadingState(false);
                    console.error('Fetch error:', error);
                    showError('Network error: ' + error.message + '. Please check browser console (F12) for details.');
                }
            });

            // Real-time validation
            emailInput.addEventListener('input', function() {
                const inputGroup = this.closest('.input-group');
                
                if (this.value.trim() !== '') {
                    if (validateEmail(this.value)) {
                        inputGroup.classList.add('valid');
                        inputGroup.classList.remove('invalid');
                    } else {
                        inputGroup.classList.add('invalid');
                        inputGroup.classList.remove('valid');
                    }
                } else {
                    inputGroup.classList.remove('valid', 'invalid');
                }
            });
        });
    </script>
</body>
</html>
