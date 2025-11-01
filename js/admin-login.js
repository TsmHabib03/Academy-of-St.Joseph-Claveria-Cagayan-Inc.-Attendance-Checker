/**
 * ADMIN LOGIN - MODERN INTERFACE
 * Enhanced authentication experience with validation, 
 * password toggle, caps lock detection, and smooth animations
 */

// ===== DOM Element References =====
const loginForm = document.getElementById('loginForm');
const loginCard = document.getElementById('loginCard');
const usernameInput = document.getElementById('username');
const passwordInput = document.getElementById('password');
const btnSignin = document.getElementById('btnSignin');
const themeToggle = document.getElementById('themeToggle');
const successModal = document.getElementById('successModal');

// Get all toggle password buttons
const togglePasswordBtns = document.querySelectorAll('.toggle-password');

// ===== Theme Management =====
function initializeTheme() {
    // Load saved theme preference or default to light
    const savedTheme = localStorage.getItem('adminTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Update toggle button icon
    updateThemeIcon(savedTheme);
}

function updateThemeIcon(theme) {
    const sunIcon = themeToggle.querySelector('.sun-icon');
    const moonIcon = themeToggle.querySelector('.moon-icon');
    
    if (theme === 'dark') {
        sunIcon.classList.remove('hidden');
        moonIcon.classList.add('hidden');
    } else {
        sunIcon.classList.add('hidden');
        moonIcon.classList.remove('hidden');
    }
}

// Theme toggle event
themeToggle.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('adminTheme', newTheme);
    updateThemeIcon(newTheme);
});

// ===== Input Validation =====
function validateField(input) {
    const inputGroup = input.closest('.input-group');
    const value = input.value.trim();
    let isValid = false;
    
    if (input.id === 'username') {
        // Username: At least 3 characters OR valid email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        isValid = value.length >= 3 || emailRegex.test(value);
    } else if (input.id === 'password') {
        // Password: At least 6 characters
        isValid = value.length >= 6;
    } else {
        // Generic: Not empty
        isValid = value.length > 0;
    }
    
    updateFieldState(inputGroup, isValid);
    return isValid;
}

function updateFieldState(inputGroup, isValid) {
    // Remove previous states
    inputGroup.classList.remove('valid', 'invalid');
    
    // Add appropriate state if input has value
    const input = inputGroup.querySelector('input');
    if (input.value.trim() !== '') {
        if (isValid) {
            inputGroup.classList.add('valid');
        } else {
            inputGroup.classList.add('invalid');
        }
    }
}

// Real-time validation on blur
function setupValidation() {
    const inputs = [usernameInput, passwordInput];
    
    inputs.forEach(input => {
        // Validate on blur (when user leaves the field)
        input.addEventListener('blur', () => {
            if (input.value.trim() !== '') {
                validateField(input);
            }
        });
        
        // Re-validate on input if field is invalid
        input.addEventListener('input', () => {
            const inputGroup = input.closest('.input-group');
            if (inputGroup.classList.contains('invalid')) {
                validateField(input);
            }
        });
        
        // Clear validation state when input is cleared
        input.addEventListener('input', () => {
            if (input.value.trim() === '') {
                const inputGroup = input.closest('.input-group');
                inputGroup.classList.remove('valid', 'invalid');
            }
        });
    });
}

// ===== Password Toggle =====
function setupPasswordToggle() {
    togglePasswordBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input[type="password"], input[type="text"]');
            const eyeIcon = btn.querySelector('.eye-icon');
            const eyeSlashIcon = btn.querySelector('.eye-slash-icon');
            
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
}

// ===== Caps Lock Detection =====
function setupCapsLockDetection() {
    passwordInput.addEventListener('keydown', (e) => {
        const capsLockWarning = passwordInput.closest('.input-group').querySelector('.caps-lock-warning');
        
        if (capsLockWarning) {
            const isCapsLock = e.getModifierState && e.getModifierState('CapsLock');
            
            if (isCapsLock) {
                capsLockWarning.classList.remove('hidden');
            } else {
                capsLockWarning.classList.add('hidden');
            }
        }
    });
    
    // Hide caps lock warning when input loses focus
    passwordInput.addEventListener('blur', () => {
        const capsLockWarning = passwordInput.closest('.input-group').querySelector('.caps-lock-warning');
        if (capsLockWarning) {
            capsLockWarning.classList.add('hidden');
        }
    });
}

// ===== Form Submission =====
function setupFormSubmission() {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Validate all fields
        const isUsernameValid = validateField(usernameInput);
        const isPasswordValid = validateField(passwordInput);
        
        if (!isUsernameValid || !isPasswordValid) {
            // Shake the card to indicate error
            shakeCard();
            return;
        }
        
        // Show loading state
        setLoadingState(true);
        
        // Prepare form data
        const formData = new FormData(loginForm);
        formData.append('ajax', '1'); // Flag for AJAX request
        
        try {
            // Send login request (using same PHP file)
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success modal
                showSuccessModal(usernameInput.value);
                
                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = result.redirect || 'dashboard.php';
                }, 2000);
            } else {
                // Show error
                showError(result.error || 'Invalid credentials. Please try again.');
                setLoadingState(false);
                shakeCard();
                
                // Clear password field on error
                passwordInput.value = '';
                passwordInput.closest('.input-group').classList.remove('valid', 'invalid');
                passwordInput.focus();
            }
        } catch (error) {
            console.error('Login error:', error);
            showError('An error occurred. Please try again.');
            setLoadingState(false);
            shakeCard();
        }
    });
}

// ===== Loading State =====
function setLoadingState(isLoading) {
    if (isLoading) {
        btnSignin.classList.add('loading');
        btnSignin.disabled = true;
    } else {
        btnSignin.classList.remove('loading');
        btnSignin.disabled = false;
    }
}

// ===== Card Shake Animation =====
function shakeCard() {
    loginCard.classList.add('shake');
    setTimeout(() => {
        loginCard.classList.remove('shake');
    }, 400);
}

// ===== Error Display =====
function showError(message) {
    // Check if error alert already exists
    let errorAlert = document.querySelector('.alert-error');
    
    if (!errorAlert) {
        // Create new error alert
        errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-error';
        errorAlert.innerHTML = `
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div class="alert-content">
                <strong>Login Failed</strong>
                <p>${message}</p>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Insert before the form
        loginForm.parentElement.insertBefore(errorAlert, loginForm);
    } else {
        // Update existing error message
        const errorMessage = errorAlert.querySelector('.alert-content p');
        if (errorMessage) {
            errorMessage.textContent = message;
        }
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (errorAlert && errorAlert.parentElement) {
            errorAlert.remove();
        }
    }, 5000);
}

// ===== Success Modal =====
function showSuccessModal(username) {
    const welcomeName = document.getElementById('welcomeName');
    if (welcomeName) {
        welcomeName.textContent = username || 'Admin';
    }
    
    successModal.classList.remove('hidden');
}

// ===== Enter Key Handling =====
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Submit form on Enter key if focus is on an input
        if (e.key === 'Enter' && (document.activeElement === usernameInput || document.activeElement === passwordInput)) {
            e.preventDefault();
            loginForm.dispatchEvent(new Event('submit'));
        }
        
        // Close modals on Escape key (reserved for future use)
        if (e.key === 'Escape') {
            // Modal handling can be added here if needed
        }
    });
}

// ===== Auto-focus Username Field =====
function setupAutofocus() {
    // Focus username field on page load if it's empty
    if (usernameInput && usernameInput.value.trim() === '') {
        usernameInput.focus();
    } else if (passwordInput) {
        // If username is pre-filled (remembered), focus password
        passwordInput.focus();
    }
}

// ===== Form Autofill Detection =====
function setupAutofillDetection() {
    // Browser autofill detection
    const checkAutofill = () => {
        [usernameInput, passwordInput].forEach(input => {
            if (input && input.value) {
                const inputGroup = input.closest('.input-group');
                // Trigger validation for autofilled inputs
                if (input.value.trim() !== '') {
                    validateField(input);
                }
            }
        });
    };
    
    // Check after a delay to allow browser autofill
    setTimeout(checkAutofill, 100);
    setTimeout(checkAutofill, 500);
    
    // Also check on page visibility change (when user returns to tab)
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            checkAutofill();
        }
    });
}

// ===== Prevent Double Submission =====
function setupDoubleSubmitPrevention() {
    let isSubmitting = false;
    
    loginForm.addEventListener('submit', (e) => {
        if (isSubmitting) {
            e.preventDefault();
            return;
        }
        isSubmitting = true;
        
        // Reset after 3 seconds as a fallback
        setTimeout(() => {
            isSubmitting = false;
        }, 3000);
    });
}

// ===== Remember Me Functionality =====
function setupRememberMe() {
    const rememberCheckbox = document.getElementById('remember');
    
    if (rememberCheckbox && usernameInput.value) {
        // If username is pre-filled, check the remember me box
        rememberCheckbox.checked = true;
    }
}

// ===== Security Features =====
function setupSecurityFeatures() {
    // Disable paste in password field (optional security measure)
    // Uncomment if needed:
    // passwordInput.addEventListener('paste', (e) => {
    //     e.preventDefault();
    //     showError('Pasting is not allowed in the password field for security reasons.');
    // });
    
    // Disable right-click context menu on password field (optional)
    // passwordInput.addEventListener('contextmenu', (e) => {
    //     e.preventDefault();
    // });
}

// ===== Page Visibility Handling =====
function setupPageVisibility() {
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            // Page is now visible
            // Re-focus the appropriate input
            if (usernameInput.value.trim() === '') {
                usernameInput.focus();
            } else if (passwordInput.value.trim() === '') {
                passwordInput.focus();
            }
        }
    });
}

// ===== Performance Monitoring =====
function logPerformance() {
    if (window.performance && window.performance.timing) {
        window.addEventListener('load', () => {
            setTimeout(() => {
                const perfData = window.performance.timing;
                const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
                const connectTime = perfData.responseEnd - perfData.requestStart;
                const renderTime = perfData.domComplete - perfData.domLoading;
                
                console.log('Performance Metrics:');
                console.log(`Page Load Time: ${pageLoadTime}ms`);
                console.log(`Connection Time: ${connectTime}ms`);
                console.log(`Render Time: ${renderTime}ms`);
                
                // Warn if page load is slow
                if (pageLoadTime > 3000) {
                    console.warn('Page load time exceeds 3 seconds. Consider optimization.');
                }
            }, 0);
        });
    }
}

// ===== Accessibility Enhancements =====
function setupAccessibility() {
    // Add ARIA live region for error announcements
    const errorRegion = document.createElement('div');
    errorRegion.setAttribute('role', 'alert');
    errorRegion.setAttribute('aria-live', 'polite');
    errorRegion.className = 'sr-only';
    errorRegion.id = 'errorAnnouncement';
    document.body.appendChild(errorRegion);
    
    // Announce errors to screen readers
    const originalShowError = window.showError || showError;
    window.showError = function(message) {
        originalShowError(message);
        const announcer = document.getElementById('errorAnnouncement');
        if (announcer) {
            announcer.textContent = message;
        }
    };
}

// ===== Initialize All Features =====
function initialize() {
    console.log('Initializing admin login interface...');
    
    // Initialize theme
    initializeTheme();
    
    // Setup all features
    setupValidation();
    setupPasswordToggle();
    setupCapsLockDetection();
    setupFormSubmission();
    setupKeyboardShortcuts();
    setupAutofocus();
    setupAutofillDetection();
    setupDoubleSubmitPrevention();
    setupRememberMe();
    setupSecurityFeatures();
    setupPageVisibility();
    setupAccessibility();
    
    // Log performance metrics (development only)
    if (window.location.hostname === 'localhost') {
        logPerformance();
    }
    
    console.log('Admin login interface ready!');
}

// ===== Run on DOM Content Loaded =====
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
} else {
    // DOM already loaded
    initialize();
}

// ===== Export for Testing =====
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        validateField,
        showError,
        showSuccessModal,
        setLoadingState,
        shakeCard
    };
}
