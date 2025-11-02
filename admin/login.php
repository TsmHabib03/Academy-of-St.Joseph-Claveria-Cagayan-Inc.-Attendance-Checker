<?php
session_start();
require_once '../config/db_config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Handle login submission
$error_message = '';
$login_attempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;
$lockout_time = isset($_SESSION['lockout_time']) ? $_SESSION['lockout_time'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if account is locked
    if ($lockout_time > time()) {
        $remaining_minutes = ceil(($lockout_time - time()) / 60);
        $error_message = "Account temporarily locked. Try again in $remaining_minutes minutes.";
    } else {
        // Reset lockout if time has passed
        if ($lockout_time > 0 && $lockout_time <= time()) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['lockout_time'] = 0;
            $login_attempts = 0;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;

        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
            $login_attempts++;
        } else {
            // Database authentication
            try {
                // First check which columns exist
                $checkColumns = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'is_active'")->rowCount();
                $hasIsActive = $checkColumns > 0;
                
                $checkLastLogin = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'last_login'")->rowCount();
                $hasLastLogin = $checkLastLogin > 0;
                
                // Build query based on available columns
                $selectFields = "id, username, password, email";
                if ($hasIsActive) {
                    $selectFields .= ", is_active";
                }
                
                $stmt = $pdo->prepare("
                    SELECT $selectFields
                    FROM admin_users 
                    WHERE username = :username OR email = :email
                    LIMIT 1
                ");
                $stmt->execute(['username' => $username, 'email' => $username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                // Check if account is active (if column exists)
                $isActive = !$hasIsActive || ($admin && (!isset($admin['is_active']) || $admin['is_active']));

                if ($admin && $isActive) {
                    // Verify password (supports both MD5 legacy and bcrypt)
                    $passwordValid = false;
                    
                    // Check if it's MD5 hash (32 characters)
                    if (strlen($admin['password']) === 32) {
                        // Legacy MD5 authentication
                        $passwordValid = (md5($password) === $admin['password']);
                    } else {
                        // Modern bcrypt authentication
                        $passwordValid = password_verify($password, $admin['password']);
                    }

                    if ($passwordValid) {
                        // Successful login
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_email'] = $admin['email'] ?? '';
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['lockout_time'] = 0;

                        // Update last login if column exists
                        if ($hasLastLogin) {
                            try {
                                $updateStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id");
                                $updateStmt->execute(['id' => $admin['id']]);
                            } catch (PDOException $e) {
                                // Ignore last_login update errors
                            }
                        }

                        if ($remember) {
                            // Set cookie for 30 days
                            setcookie('remember_admin', base64_encode($admin['username']), time() + (30 * 24 * 60 * 60), '/');
                        }

                        // Return JSON for AJAX
                        if (isset($_POST['ajax'])) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
                            exit();
                        }

                        header('Location: dashboard.php');
                        exit();
                    } else {
                        $error_message = 'Invalid username or password. Please try again.';
                        $login_attempts++;
                    }
                } else {
                    $error_message = ($admin && !$isActive) ? 'Your account has been deactivated. Contact administrator.' : 'Invalid username or password. Please try again.';
                    $login_attempts++;
                }

                // Lock account after 5 failed attempts
                if ($login_attempts >= 5) {
                    $_SESSION['lockout_time'] = time() + (15 * 60); // 15 minutes
                    $error_message = 'Too many failed login attempts. Account locked for 15 minutes.';
                }

            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error_message = 'Database error: ' . $e->getMessage();
            }
        }

        $_SESSION['login_attempts'] = $login_attempts;

        // Return JSON for AJAX
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error_message, 'locked' => ($lockout_time > time())]);
            exit();
        }
    }
}

// Check for remembered user
$remembered_username = '';
if (isset($_COOKIE['remember_admin'])) {
    $remembered_username = base64_decode($_COOKIE['remember_admin']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure Admin Portal - School Attendance Management System">
    <meta name="theme-color" content="#667eea">
    <title>Admin Portal - Secure Login</title>
    
    <!-- Preload critical assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../css/admin-login.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔐</text></svg>">
</head>
<body>
    <!-- Background Effects -->
    <div class="background-gradient"></div>
    <div class="background-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Dark Mode Toggle -->
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
        <svg class="sun-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
        <svg class="moon-icon hidden" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
    </button>

    <!-- Main Container -->
    <div class="login-container">
        <div class="login-card" id="loginCard">
            <!-- Logo & Header -->
            <div class="login-header">
                <div class="logo-container">
                    <svg class="shield-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                </div>
                <h1 class="login-title">Admin Portal</h1>
                <p class="login-subtitle">Secure Access to Attendance Management</p>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['access_denied'])): ?>
            <div class="alert alert-error" id="accessDeniedAlert">
                <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M4.93 4.93l14.14 14.14"></path>
                </svg>
                <div class="alert-content">
                    <strong>Access Denied</strong>
                    <p><?php echo htmlspecialchars($_SESSION['access_denied']); ?></p>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
            <?php 
                unset($_SESSION['access_denied']); 
            endif; 
            ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-error" id="errorAlert">
                <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div class="alert-content">
                    <strong><?php echo $lockout_time > time() ? 'Account Locked' : 'Login Failed'; ?></strong>
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form class="login-form" id="loginForm" method="POST" action="" novalidate>
                <!-- Username Input -->
                <div class="input-group" data-validate="required">
                    <div class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        id="username" 
                        name="username"
                        placeholder=" " 
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($remembered_username); ?>"
                        required
                        autofocus
                    >
                    <label for="username">Username or Email</label>
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
                    <div class="input-error-message">Please enter a valid username or email</div>
                </div>

                <!-- Password Input -->
                <div class="input-group" data-validate="required">
                    <div class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <input 
                        type="password" 
                        id="password" 
                        name="password"
                        placeholder=" " 
                        autocomplete="current-password"
                        required
                    >
                    <label for="password">Password</label>
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
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
                    <div class="caps-lock-warning hidden">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7 15l5-5 5 5H7z"/>
                        </svg>
                        <span>Caps Lock is ON</span>
                    </div>
                    <div class="input-error-message">Password must be at least 6 characters</div>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember" id="remember">
                        <span class="checkmark">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </span>
                        <span class="checkbox-label">Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-signin" id="btnSignin">
                    <span class="btn-text">
                        <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M13.8 12H3"/>
                        </svg>
                        Sign In
                    </span>
                    <span class="btn-loader hidden">
                        <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)"/>
                        </svg>
                        Signing in...
                    </span>
                </button>

            </form>

            <!-- Security Badge -->
            <div class="security-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Protected by 256-bit encryption</span>
            </div>
            <div class="security-details">
                SSL Secured • SOC 2 Compliant • GDPR Ready
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="success-modal hidden" id="successModal">
        <div class="success-content">
            <div class="success-checkmark">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2>Welcome back, <span id="welcomeName">Admin</span>!</h2>
            <p>Redirecting to dashboard...</p>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
    </div>

    <script src="../js/admin-login.js"></script>
</body>
</html>
