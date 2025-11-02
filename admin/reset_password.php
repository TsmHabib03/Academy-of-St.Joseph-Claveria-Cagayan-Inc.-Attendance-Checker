<?php
session_start();
require_once '../config/db_config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Check if token is provided
if (!isset($_GET['token']) || empty(trim($_GET['token']))) {
    $error = 'invalid_link';
    $errorMessage = 'Invalid password reset link. Please request a new one.';
} else {
    $token = trim($_GET['token']);
    
    // Hash the token to match stored hash
    $hashedToken = hash('sha256', $token);
    
    try {
        // Validate token
        $stmt = $pdo->prepare("
            SELECT id, username, email, reset_token_expires_at 
            FROM admin_users 
            WHERE reset_token = :token 
            AND reset_token_expires_at > NOW()
            LIMIT 1
        ");
        
        $stmt->execute(['token' => $hashedToken]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            $error = 'expired_link';
            $errorMessage = 'This password reset link has expired or is invalid. Please request a new one.';
        }
    } catch (PDOException $e) {
        error_log("Database error in reset password: " . $e->getMessage());
        $error = 'system_error';
        $errorMessage = 'A system error occurred. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset Your Admin Password - Attendance Management System">
    <meta name="theme-color" content="#667eea">
    <title>Reset Password - Admin Portal</title>
    
    <!-- Preload critical assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../css/admin-login.css">
    
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
        <div class="login-card" id="resetPasswordCard">
            <?php if (isset($error)): ?>
                <!-- Error State -->
                <div class="login-header">
                    <div class="logo-container">
                        <svg class="shield-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #ef4444;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <h1 class="login-title">Link Invalid or Expired</h1>
                    <p class="login-subtitle"><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>

                <div class="alert alert-error">
                    <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div class="alert-content">
                        <strong>Reset Link Issue</strong>
                        <p>The password reset link is either invalid, has already been used, or has expired.</p>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="forgot_password.php" class="btn-signin" style="text-decoration: none; display: inline-block;">
                        <span class="btn-text">
                            <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 2L11 13"></path>
                                <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                            </svg>
                            Request New Reset Link
                        </span>
                    </a>
                    <a href="login.php" style="display: block; margin-top: 15px; color: #667eea; text-decoration: none; font-weight: 500;">
                        Back to Login
                    </a>
                </div>
            <?php else: ?>
                <!-- Valid Token - Show Reset Form -->
                <div class="login-header">
                    <div class="logo-container">
                        <svg class="shield-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <h1 class="login-title">Create New Password</h1>
                    <p class="login-subtitle">Enter a strong password for your account: <strong><?php echo htmlspecialchars($admin['username']); ?></strong></p>
                </div>

                <!-- Alert Messages -->
                <div class="alert alert-success hidden" id="successAlert">
                    <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <div class="alert-content">
                        <strong>Password Updated!</strong>
                        <p>Your password has been successfully changed. Redirecting to login...</p>
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

                <!-- Reset Password Form -->
                <form class="login-form" id="resetPasswordForm" novalidate>
                    <input type="hidden" id="token" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <!-- New Password Input -->
                    <div class="input-group" data-validate="password">
                        <div class="input-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password"
                            placeholder=" " 
                            autocomplete="new-password"
                            required
                            autofocus
                        >
                        <label for="new_password">New Password</label>
                        <button type="button" class="toggle-password" data-target="new_password" aria-label="Toggle password visibility">
                            <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-slash-icon hidden" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
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
                        <div class="input-error-message">Password must be at least 8 characters</div>
                    </div>

                    <!-- Password Strength Indicator -->
                    <div class="password-strength hidden" id="passwordStrength">
                        <div class="strength-label">Password Strength: <span id="strengthText">Weak</span></div>
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                    </div>

                    <!-- Confirm Password Input -->
                    <div class="input-group" data-validate="password">
                        <div class="input-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password"
                            placeholder=" " 
                            autocomplete="new-password"
                            required
                        >
                        <label for="confirm_password">Confirm New Password</label>
                        <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Toggle password visibility">
                            <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-slash-icon hidden" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
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
                        <div class="input-error-message">Passwords do not match</div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-signin" id="btnSubmit">
                        <span class="btn-text">
                            <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            Reset Password
                        </span>
                        <span class="btn-loader hidden">
                            <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)"/>
                            </svg>
                            Updating...
                        </span>
                    </button>
                </form>
            <?php endif; ?>

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
        /* Additional styles for password reset */
        .password-strength {
            margin: 15px 0;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .strength-label {
            font-size: 14px;
            margin-bottom: 8px;
            color: #6b7280;
            font-weight: 500;
        }

        .strength-label span {
            font-weight: 700;
        }

        .strength-bar {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
            border-radius: 3px;
        }

        .strength-fill.weak {
            width: 33%;
            background: #ef4444;
        }

        .strength-fill.medium {
            width: 66%;
            background: #f59e0b;
        }

        .strength-fill.strong {
            width: 100%;
            background: #10b981;
        }
    </style>

    <script>
        <?php if (!isset($error)): ?>
        /**
         * Reset Password Form Handler
         */
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            const btnSubmit = document.getElementById('btnSubmit');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const tokenInput = document.getElementById('token');
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            const passwordStrength = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('strengthText');
            const strengthFill = document.getElementById('strengthFill');

            // Password toggle functionality
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const eyeIcon = this.querySelector('.eye-icon');
                    const eyeSlashIcon = this.querySelector('.eye-slash-icon');

                    if (input.type === 'password') {
                        input.type = 'text';
                        eyeIcon.classList.add('hidden');
                        eyeSlashIcon.classList.remove('hidden');
                    } else {
                        input.type = 'password';
                        eyeIcon.classList.remove('hidden');
                        eyeSlashIcon.classList.add('hidden');
                    }
                });
            });

            // Password strength checker
            function checkPasswordStrength(password) {
                let strength = 0;
                
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/\d/.test(password)) strength++;
                if (/[^a-zA-Z\d]/.test(password)) strength++;

                return strength;
            }

            // Update password strength indicator
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                
                if (password.length === 0) {
                    passwordStrength.classList.add('hidden');
                    return;
                }

                passwordStrength.classList.remove('hidden');
                const strength = checkPasswordStrength(password);

                strengthFill.className = 'strength-fill';
                
                if (strength <= 2) {
                    strengthFill.classList.add('weak');
                    strengthText.textContent = 'Weak';
                    strengthText.style.color = '#ef4444';
                } else if (strength <= 4) {
                    strengthFill.classList.add('medium');
                    strengthText.textContent = 'Medium';
                    strengthText.style.color = '#f59e0b';
                } else {
                    strengthFill.classList.add('strong');
                    strengthText.textContent = 'Strong';
                    strengthText.style.color = '#10b981';
                }
            });

            // Validate passwords match
            function validatePasswordsMatch() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const confirmGroup = confirmPasswordInput.closest('.input-group');

                if (confirmPassword.length === 0) {
                    confirmGroup.classList.remove('valid', 'invalid');
                    return false;
                }

                if (newPassword === confirmPassword) {
                    confirmGroup.classList.add('valid');
                    confirmGroup.classList.remove('invalid');
                    return true;
                } else {
                    confirmGroup.classList.add('invalid');
                    confirmGroup.classList.remove('valid');
                    return false;
                }
            }

            // Real-time password match validation
            confirmPasswordInput.addEventListener('input', validatePasswordsMatch);
            newPasswordInput.addEventListener('input', function() {
                if (confirmPasswordInput.value.length > 0) {
                    validatePasswordsMatch();
                }
            });

            // Show/hide loading state
            function setLoadingState(loading) {
                const btnText = btnSubmit.querySelector('.btn-text');
                const btnLoader = btnSubmit.querySelector('.btn-loader');
                
                if (loading) {
                    btnText.classList.add('hidden');
                    btnLoader.classList.remove('hidden');
                    btnSubmit.disabled = true;
                    newPasswordInput.disabled = true;
                    confirmPasswordInput.disabled = true;
                } else {
                    btnText.classList.remove('hidden');
                    btnLoader.classList.add('hidden');
                    btnSubmit.disabled = false;
                    newPasswordInput.disabled = false;
                    confirmPasswordInput.disabled = false;
                }
            }

            // Show success and redirect
            function showSuccess() {
                errorAlert.classList.add('hidden');
                successAlert.classList.remove('hidden');
                form.reset();
                
                // Redirect to login after 3 seconds
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 3000);
            }

            // Show error alert
            function showError(message) {
                successAlert.classList.add('hidden');
                errorMessage.textContent = message;
                errorAlert.classList.remove('hidden');
                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // Form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const newPassword = newPasswordInput.value.trim();
                const confirmPassword = confirmPasswordInput.value.trim();
                const token = tokenInput.value;

                // Validate
                if (!newPassword || newPassword.length < 8) {
                    showError('Password must be at least 8 characters long');
                    newPasswordInput.focus();
                    return;
                }

                if (newPassword !== confirmPassword) {
                    showError('Passwords do not match');
                    confirmPasswordInput.focus();
                    return;
                }

                // Hide alerts
                successAlert.classList.add('hidden');
                errorAlert.classList.add('hidden');

                // Show loading state
                setLoadingState(true);

                try {
                    const response = await fetch('../api/update_password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            token: token,
                            new_password: newPassword,
                            confirm_password: confirmPassword
                        })
                    });

                    const data = await response.json();

                    setLoadingState(false);

                    if (data.success) {
                        showSuccess();
                    } else {
                        showError(data.message || 'Failed to reset password. Please try again.');
                    }
                } catch (error) {
                    setLoadingState(false);
                    console.error('Error:', error);
                    showError('Network error. Please check your connection and try again.');
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
