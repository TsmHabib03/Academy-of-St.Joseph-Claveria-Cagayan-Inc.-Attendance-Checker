<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0ea5e9">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Scan Attendance - Attendance Checker</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="css/modern-design.css">
    
    <style>
        /* ===== ASJ COLOR SYSTEM ===== */
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
        }

        /* Full-Screen Scanner Specific Styles */
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: 'Poppins', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        }

        .scanner-app {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%);
        }

        /* Top Bar */
        .scanner-topbar {
            background: linear-gradient(135deg, var(--asj-green-600), var(--asj-green-700));
            backdrop-filter: blur(10px);
            padding: var(--space-3) var(--space-4);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 16px rgba(76, 175, 80, 0.3);
            z-index: 100;
        }

        .scanner-logo {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            text-decoration: none;
        }

        .scanner-logo-img {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            background: white;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            object-fit: contain;
        }

        .scanner-logo-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .scanner-logo-title {
            font-size: var(--text-base);
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            color: white;
            line-height: 1.2;
        }

        .scanner-logo-subtitle {
            font-size: var(--text-xs);
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1;
        }
        
        .scanner-menu-btn {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-full);
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.15);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
            backdrop-filter: blur(10px);
        }

        .scanner-menu-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.05);
        }

        .scanner-menu-btn:active {
            transform: scale(0.95);
        }

        /* Scanner Container */
        .scanner-container {
            flex: 1;
            position: relative;
            display: flex;
            flex-direction: column;
            background: #000;
        }

        #qr-reader {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        #qr-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Scanner Overlay */
        .scanner-overlay-frame {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .scanner-frame {
            width: min(80vmin, 400px);
            height: min(80vmin, 400px);
            position: relative;
            border: 3px solid rgba(16, 185, 129, 0.6);
            border-radius: 20px;
            animation: framePulse 2s ease-in-out infinite;
        }

        @keyframes framePulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            50% {
                box-shadow: 0 0 20px 10px rgba(16, 185, 129, 0.3);
            }
        }

        .scanner-frame::before,
        .scanner-frame::after,
        .scanner-frame-tl::before,
        .scanner-frame-tr::before,
        .scanner-frame-bl::before,
        .scanner-frame-br::before {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.8);
        }

        .scanner-frame::before {
            top: 0;
            left: 0;
            border-right: none;
            border-bottom: none;
        }

        .scanner-frame::after {
            top: 0;
            right: 0;
            border-left: none;
            border-bottom: none;
        }

        .scanner-frame-bl {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 40px;
        }

        .scanner-frame-bl::before {
            top: 0;
            left: 0;
            border-right: none;
            border-top: none;
        }

        .scanner-frame-br {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 40px;
        }

        .scanner-frame-br::before {
            top: 0;
            left: 0;
            border-left: none;
            border-top: none;
        }

        .scanner-line {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #10b981, transparent);
            animation: scan 2s ease-in-out infinite;
        }

        @keyframes scan {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(100%); }
        }

        .scanner-hint {
            position: absolute;
            bottom: var(--space-8);
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: var(--space-3) var(--space-6);
            border-radius: var(--radius-full);
            font-size: var(--text-sm);
            font-family: 'Poppins', sans-serif;
            backdrop-filter: blur(10px);
        }

        /* Bottom Controls */
        .scanner-controls {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: var(--space-6);
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }

        .scanner-control-btn {
            width: 100%;
            padding: var(--space-4);
            border-radius: var(--radius-xl);
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: var(--text-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-3);
        }

        .scanner-control-btn-primary {
            background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
            color: white;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
        }

        .scanner-control-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(76, 175, 80, 0.5);
        }

        .scanner-control-btn-primary:active {
            transform: translateY(0);
        }

        .scanner-control-btn-secondary {
            background: white;
            color: var(--asj-green-600);
            border: 2px solid var(--neutral-300);
        }

        .scanner-control-btn-secondary:hover {
            background: var(--asj-green-50);
            border-color: var(--asj-green-500);
        }

        .scanner-control-btn-danger {
            background: linear-gradient(135deg, var(--error), var(--error-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        /* Success/Error Overlay */
        .result-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition-slow);
        }

        .result-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .result-overlay-success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.97), rgba(56, 142, 60, 0.97));
            backdrop-filter: blur(10px);
        }

        .result-overlay-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.97), rgba(220, 38, 38, 0.97));
            backdrop-filter: blur(10px);
        }

        .result-icon {
            width: 120px;
            height: 120px;
            border-radius: var(--radius-full);
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-6);
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .result-icon i {
            font-size: 60px;
            color: white;
        }

        .result-content {
            color: white;
            text-align: center;
            padding: 0 var(--space-6);
            animation: slideUp 0.5s ease-out 0.2s both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-title {
            font-family: 'Poppins', sans-serif;
            font-size: var(--text-2xl);
            font-weight: 700;
            margin-bottom: var(--space-2);
        }

        .result-message {
            font-family: 'Poppins', sans-serif;
            font-size: var(--text-lg);
            margin-bottom: var(--space-3);
        }

        .result-time {
            font-family: 'Poppins', sans-serif;
            font-size: var(--text-sm);
            opacity: 0.9;
        }        /* Manual Entry Modal */
        .manual-modal {
            position: fixed;
            bottom: -100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: var(--radius-2xl) var(--radius-2xl) 0 0;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transition: bottom var(--transition-slow);
            max-height: 70vh;
            overflow-y: auto;
        }

        .manual-modal.active {
            bottom: 0;
        }

        .manual-modal-header {
            padding: var(--space-6);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .manual-modal-title {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--gray-900);
        }

        .manual-modal-close {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-full);
            border: none;
            background: var(--gray-100);
            color: var(--gray-600);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .manual-modal-body {
            padding: var(--space-6);
        }

        .manual-help-text {
            background: var(--asj-green-50);
            border-left: 4px solid var(--asj-green-500);
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            color: var(--asj-green-700);
            font-size: var(--text-sm);
        }

        /* Loading State */
        .scanner-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #000;
            color: white;
            z-index: 50;
        }

        .scanner-loading .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: var(--space-4);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Status Bar */
        .status-bar {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: var(--space-2) var(--space-4);
            font-size: var(--text-sm);
            text-align: center;
            backdrop-filter: blur(10px);
        }

        /* Mobile Menu Styles */
        .mobile-menu-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1998;
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition-base);
        }

        .mobile-menu-backdrop.active {
            opacity: 1;
            pointer-events: all;
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            right: -300px;
            width: 280px;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 20px rgba(0, 0, 0, 0.2);
            z-index: 1999;
            transition: right var(--transition-slow);
            overflow-y: auto;
        }

        .mobile-menu.active {
            right: 0;
        }

        .mobile-menu-header {
            padding: var(--space-6);
            background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-700));
            color: white;
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .mobile-menu-logo {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            background: white;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            object-fit: contain;
        }

        .mobile-menu-title {
            flex: 1;
        }

        .mobile-menu-title h3 {
            font-size: var(--text-lg);
            font-weight: 700;
            margin-bottom: 4px;
            color: white;
        }

        .mobile-menu-title p {
            font-size: var(--text-xs);
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
        }

        .mobile-menu-nav {
            padding: var(--space-4);
        }

        .mobile-menu-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            font-family: 'Poppins', sans-serif;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-base);
            margin-bottom: var(--space-2);
        }

        .mobile-menu-item:hover {
            background: var(--asj-green-50);
            color: var(--asj-green-600);
        }

        .mobile-menu-item.active {
            background: var(--asj-green-100);
            color: var(--asj-green-700);
            font-weight: 600;
            border-left: 4px solid var(--asj-green-600);
        }

        .mobile-menu-item i {
            font-size: var(--text-lg);
            width: 24px;
            text-align: center;
        }

        /* Button Styles */
        .btn {
            padding: var(--space-3) var(--space-6);
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
        }

        .btn-lg {
            padding: var(--space-4) var(--space-6);
            font-size: var(--text-lg);
        }

        .btn-full {
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
            color: white;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(76, 175, 80, 0.4);
        }

        .form-control {
            width: 100%;
            padding: var(--space-4);
            border: 2px solid var(--neutral-300);
            border-radius: var(--radius-lg);
            font-size: var(--text-base);
            transition: all var(--transition-base);
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--asj-green-500);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-group {
            margin-bottom: var(--space-4);
        }

        .form-label {
            display: block;
            margin-bottom: var(--space-2);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--gray-700);
            font-size: var(--text-sm);
        }        .invalid-feedback {
            color: var(--error);
            font-size: var(--text-sm);
            margin-top: var(--space-2);
            display: none;
        }

        .invalid-feedback.active {
            display: block;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(10px);
        }

        .badge-late { background: var(--error) !important; }
        .badge-on-time { background: var(--success) !important; }
        .badge-assigned { background: rgba(255,255,255,0.12) !important; color: white !important; }

        /* Desktop adjustments */
        @media (min-width: 768px) {
            .scanner-app {
                max-width: 480px;
                margin: 0 auto;
                box-shadow: var(--shadow-2xl);
            }

            .scanner-hint {
                bottom: var(--space-12);
            }
        }

        /* Landscape mode adjustments */
        @media (max-height: 600px) and (orientation: landscape) {
            .scanner-controls {
                flex-direction: row;
                padding: var(--space-4);
            }

            .scanner-control-btn {
                flex: 1;
            }

            .scanner-hint {
                bottom: var(--space-4);
                font-size: var(--text-xs);
                padding: var(--space-2) var(--space-4);
            }
        }
    </style>
</head>
<body>
    <div class="scanner-app">
        <!-- Top Bar -->
        <div class="scanner-topbar">
            <a href="index.php" class="scanner-logo">
                <img src="assets/asj-logo.png" alt="ASJ Logo" class="scanner-logo-img" onerror="this.style.display='none'">
                <div class="scanner-logo-text">
                    <span class="scanner-logo-title">ASJ Attendance</span>
                    <span class="scanner-logo-subtitle">QR Scanner</span>
                </div>
            </a>
            <button class="scanner-menu-btn" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Scanner Container -->
        <div class="scanner-container">
            <!-- Status Bar -->
            <div class="status-bar">
                <i class="fas fa-clock"></i> <span id="current-time">--:--</span> | <span id="schedule-info">Loading...</span>
            </div>

            <!-- Loading State -->
            <div class="scanner-loading" id="scanner-loading">
                <div class="spinner"></div>
                <p>Initializing Camera...</p>
            </div>

            <!-- QR Reader -->
            <div id="qr-reader">
                <video id="qr-video" style="width: 100%; height: 100%; object-fit: cover;"></video>
            </div>

            <!-- Scanner Overlay -->
            <div class="scanner-overlay-frame">
                <div class="scanner-frame">
                    <div class="scanner-frame-bl"></div>
                    <div class="scanner-frame-br"></div>
                    <div class="scanner-line"></div>
                </div>
            </div>

            <div class="scanner-hint">
                <i class="fas fa-mobile-screen"></i> Align QR code within the frame
            </div>
        </div>

        <!-- Bottom Controls -->
        <div class="scanner-controls">
            <button id="start-scan-btn" class="scanner-control-btn scanner-control-btn-primary">
                <i class="fas fa-camera"></i> Start Scanning
            </button>
            <button id="stop-scan-btn" class="scanner-control-btn scanner-control-btn-danger" style="display: none;">
                <i class="fas fa-stop-circle"></i> Stop Scanner
            </button>
            <button class="scanner-control-btn scanner-control-btn-secondary" onclick="openManualEntry()">
                <i class="fas fa-keyboard"></i> Manual Entry
            </button>
        </div>

        <!-- Success Overlay -->
        <div class="result-overlay result-overlay-success" id="success-overlay">
            <div class="result-icon">
                <i class="fas fa-check"></i>
            </div>
            <div class="result-content">
                <h2 class="result-title" id="success-title">Success!</h2>
                <p class="result-message" id="success-student">John Doe</p>
                <p class="result-time" id="success-time">Time In: 08:15 AM</p>
                <button class="btn btn-lg btn-full" onclick="closeResultOverlay()" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white;">
                    Continue Scanning
                </button>
            </div>
        </div>

        <!-- Error Overlay -->
        <div class="result-overlay result-overlay-error" id="error-overlay">
            <div class="result-icon">
                <i class="fas fa-times"></i>
            </div>
            <div class="result-content">
                <h2 class="result-title">Oops!</h2>
                <p class="result-message" id="error-message">Student not found</p>
                <button class="btn btn-lg btn-full" onclick="closeResultOverlay()" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white;">
                    Try Again
                </button>
            </div>
        </div>

        <!-- Manual Entry Modal -->
        <div class="manual-modal" id="manual-modal">
            <div class="manual-modal-header">
                <h3 class="manual-modal-title">
                    <i class="fas fa-keyboard"></i> Manual Entry
                </h3>
                <button class="manual-modal-close" onclick="closeManualEntry()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="manual-modal-body">
                <div class="manual-help-text">
                    <i class="fas fa-info-circle"></i> If the camera isn't working, you can manually enter the student's LRN (11-13 digits) or a teacher's 7-digit Employee Number.
                </div>
                <form id="manual-form">
                    <div class="form-group">
                        <label class="form-label" for="manual-lrn">
                            <i class="fas fa-id-card"></i> LRN (Learner Reference Number)
                        </label>
                        <input 
                            type="text" 
                            id="manual-lrn" 
                            name="lrn" 
                            class="form-control" 
                            placeholder="Enter 11-13 digit LRN or 7-digit Employee Number"
                            pattern="([0-9]{11,13}|[0-9]{7})"
                            maxlength="13"
                            inputmode="numeric"
                            required
                        >
                        <div class="invalid-feedback" id="lrn-error"></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-full">
                        <i class="fas fa-check-circle"></i> Mark Attendance
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Menu Modal (Hidden by default) -->
    <div class="mobile-menu-backdrop" id="menu-backdrop" onclick="toggleMenu()"></div>
    <div class="mobile-menu" id="mobile-menu">
        <div class="mobile-menu-header">
            <img src="assets/asj-logo.png" alt="ASJ Logo" class="mobile-menu-logo" onerror="this.style.display='none'">
            <div class="mobile-menu-title">
                <h3>Academy of St. Joseph</h3>
                <p>Attendance System</p>
            </div>
        </div>
        <nav class="mobile-menu-nav">
            <a href="index.php" class="mobile-menu-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="scan_attendance.php" class="mobile-menu-item active">
                <i class="fas fa-qrcode"></i>
                <span>Scan Attendance</span>
            </a>
            <a href="admin/login.php" class="mobile-menu-item">
                <i class="fas fa-shield-halved"></i>
                <span>Admin Portal</span>
            </a>
        </nav>
    </div>

    <!-- ZXing QR Library -->
    <script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>

    <script>
        let codeReader = null;
        let isScanning = false;

        // Time Display
        function updateTime() {
            const now = new Date();
            const options = { 
                hour: '2-digit', 
                minute: '2-digit',
                timeZone: 'Asia/Manila'
            };
            document.getElementById('current-time').textContent = 
                now.toLocaleTimeString('en-US', options);
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Initialize Scanner with ULTRA-FAST Configuration
        async function initializeScanner() {
            try {
                // Check if ZXing library is loaded
                if (typeof ZXing === 'undefined') {
                    throw new Error('ZXing library not loaded. Please check your internet connection.');
                }
                
                // Check if browser supports getUserMedia
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Camera not supported in this browser');
                }

                // Configure decoder hints for MAXIMUM SPEED
                const hints = new Map();
                hints.set(ZXing.DecodeHintType.TRY_HARDER, false); // FALSE for faster scanning
                hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.QR_CODE]);
                hints.set(ZXing.DecodeHintType.CHARACTER_SET, 'UTF-8');
                
                // Enable pure barcode mode for speed
                hints.set(ZXing.DecodeHintType.PURE_BARCODE, false);
                
                codeReader = new ZXing.BrowserQRCodeReader(hints);
                document.getElementById('scanner-loading').style.display = 'none';
                
                // Auto-start scanning on page load
                setTimeout(() => {
                    startScanning();
                }, 500);
            } catch (error) {
                document.getElementById('scanner-loading').style.display = 'none';
                
                let errorMessage = 'Camera initialization failed';
                if (error.message.includes('not supported')) {
                    errorMessage = 'Your browser does not support camera access. Please use Chrome, Firefox, or Safari.';
                } else {
                    errorMessage = 'Unable to initialize camera. Please refresh the page and allow camera permissions.';
                }
                
                showError(errorMessage);
            }
        }

        // Start ULTRA-FAST Scanning - Optimized for instant detection
        async function startScanning() {
            if (!codeReader) await initializeScanner();
            
            try {
                isScanning = true;
                document.getElementById('start-scan-btn').style.display = 'none';
                document.getElementById('stop-scan-btn').style.display = 'flex';

                // Get video element
                const videoElement = document.getElementById('qr-video');

                // OPTIMIZED constraints for FAST scanning and auto-focus
                const constraints = {
                    video: {
                        facingMode: 'environment', // Use back camera on mobile
                        width: { ideal: 1280, max: 1920 }, // Balanced for speed
                        height: { ideal: 720, max: 1080 },
                        frameRate: { ideal: 60, min: 30 }, // HIGH frame rate for speed
                        focusMode: 'continuous', // Auto-focus
                        focusDistance: { ideal: 0 }, // Focus on close objects
                        zoom: { ideal: 1 }
                    }
                };

                // Get media stream with optimized settings
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                videoElement.srcObject = stream;
                
                // Apply advanced camera settings if supported
                const videoTrack = stream.getVideoTracks()[0];
                const capabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : {};
                
                if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
                    await videoTrack.applyConstraints({
                        advanced: [{ focusMode: 'continuous' }]
                    });
                }
                
                await videoElement.play();

                // Use ZXing's built-in continuous decode (MUCH FASTER)
                await codeReader.decodeFromVideoDevice(undefined, videoElement, (result, err) => {
                    if (result && result.text && isScanning && !isProcessing) {
                        handleScanResult(result.text);
                    }
                    // Suppress all scanning errors - these are normal during continuous scanning
                });

            } catch (error) {
                // Provide specific error messages based on error type
                let errorMessage = 'Failed to start camera';
                if (error.name === 'NotAllowedError') {
                    errorMessage = 'Camera access denied. Please allow camera permissions in your browser settings and try again.';
                } else if (error.name === 'NotFoundError') {
                    errorMessage = 'No camera found on this device. Please check your camera connection.';
                } else if (error.name === 'NotReadableError') {
                    errorMessage = 'Camera is already in use by another application. Please close other apps using the camera.';
                } else if (error.name === 'OverconstrainedError') {
                    errorMessage = 'Camera does not support the required settings. Trying fallback mode...';
                    // Try again with simpler constraints
                    try {
                        const simpleStream = await navigator.mediaDevices.getUserMedia({ 
                            video: { facingMode: 'environment' } 
                        });
                        videoElement.srcObject = simpleStream;
                        await videoElement.play();
                        
                        await codeReader.decodeFromVideoDevice(undefined, videoElement, (result, err) => {
                            if (result && result.text && isScanning && !isProcessing) {
                                handleScanResult(result.text);
                            }
                            // Suppress all scanning errors - these are normal during continuous scanning
                        });
                        
                        return; // Success with fallback
                    } catch (fallbackError) {
                        errorMessage = 'Failed to start camera even with basic settings.';
                    }
                } else if (error.name === 'SecurityError') {
                    errorMessage = 'Camera access blocked due to security restrictions. Please use HTTPS or localhost.';
                }
                
                showError(errorMessage);
                
                // Reset buttons on error
                document.getElementById('start-scan-btn').style.display = 'flex';
                document.getElementById('stop-scan-btn').style.display = 'none';
                isScanning = false;
            }
        }

        // Stop Scanning
        function stopScanning() {
            isScanning = false;
            
            // Stop video stream
            const videoElement = document.getElementById('qr-video');
            if (videoElement.srcObject) {
                const tracks = videoElement.srcObject.getTracks();
                tracks.forEach(track => track.stop());
                videoElement.srcObject = null;
            }
            
            if (codeReader) {
                codeReader.reset();
            }
            
            document.getElementById('start-scan-btn').style.display = 'flex';
            document.getElementById('stop-scan-btn').style.display = 'none';
        }

        // Handle Scan Result
        let isProcessing = false; // Prevent duplicate scans
        
        async function handleScanResult(qrCode) {
            // Prevent duplicate processing
            if (isProcessing) {
                return;
            }
            
            isProcessing = true;
            stopScanning();
            
            // Show processing state
            document.getElementById('scanner-loading').style.display = 'flex';
            document.getElementById('scanner-loading').querySelector('p').textContent = 'Processing attendance...';
            
            try {
                // Parse QR payload. Supported formats:
                // 1) Plain LRN: "12345678901"
                // 2) Pipe-delimited: "LRN|SECTION" or "LRN|SECTION|SESSION"
                // 3) JSON: {"lrn":"...","section":"...","session":"morning"}
                let payload = qrCode.trim();
                let lrn = '';
                let providedSection = '';
                let providedSession = '';

                // Try JSON first
                if (payload.startsWith('{')) {
                    try {
                        const obj = JSON.parse(payload);
                        lrn = (obj.lrn || obj.LRN || obj.id || obj.code || '').toString().trim();
                        providedSection = (obj.section || obj.class || obj.section_name || '').toString().trim();
                        providedSession = (obj.session || obj.assigned_session || obj.shift || '').toString().trim();
                    } catch (e) {
                        // fallback to plain parsing
                        payload = payload;
                    }
                }

                // If not parsed as JSON, handle pipe-delimited or plain
                if (!lrn) {
                    if (payload.includes('|')) {
                        const parts = payload.split('|').map(p => p.trim());
                        lrn = parts[0] || '';
                        providedSection = parts[1] || '';
                        providedSession = parts[2] || '';
                    } else {
                        lrn = payload;
                    }
                }

                // Trim and sanitize
                lrn = lrn.trim();
                providedSection = providedSection.trim();
                providedSession = providedSession.trim().toLowerCase();

                // Build form body
                const form = new URLSearchParams();
                form.append('lrn', lrn);
                if (providedSection) form.append('section', providedSection);
                if (providedSession) form.append('assigned_session', providedSession);

                // Send to API (no logging of sensitive data)
                const response = await fetch('api/mark_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: form.toString()
                });

                // Always read raw response text for better debugging
                const responseText = await response.text();

                // If server returned non-2xx, include server body in the error
                if (!response.ok) {
                    console.error('Attendance API error', response.status, responseText);
                    throw new Error(`Server returned ${response.status}: ${responseText}`);
                }

                // Parse JSON response
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Invalid JSON from server:', responseText);
                    throw new Error('Invalid server response: ' + (responseText || ''));
                }
                
                // Hide loading
                document.getElementById('scanner-loading').style.display = 'none';
                document.getElementById('scanner-loading').querySelector('p').textContent = 'Initializing Camera...';
                
                if (data.success) {
                    showSuccess(data);
                } else {
                    showError(data.message || 'Failed to mark attendance');
                }
            } catch (error) {
                document.getElementById('scanner-loading').style.display = 'none';
                document.getElementById('scanner-loading').querySelector('p').textContent = 'Initializing Camera...';
                
                let errorMessage = 'Network error. Please check your connection and try again.';
                if (error.message && error.message.includes('Server error')) {
                    errorMessage = 'Server error. Please contact the administrator.';
                }
                
                showError(errorMessage);
            } finally {
                // Reset processing flag after a delay
                setTimeout(() => {
                    isProcessing = false;
                }, 2000);
            }
        }

        // Show Success
        function showSuccess(data) {
            // Update success title based on action
            const titleElement = document.getElementById('success-title');
            // If API marked a teacher, adjust labels accordingly
            const userType = data.user_type || 'student';
            if (data.status === 'time_in') {
                titleElement.textContent = (userType === 'teacher') ? 'Welcome Teacher! ✓' : 'Welcome! ✓';
            } else if (data.status === 'time_out') {
                titleElement.textContent = (userType === 'teacher') ? 'See You Teacher! ✓' : 'See You! ✓';
            } else {
                titleElement.textContent = 'Success! ✓';
            }
            // Update displayed name/label; include identifier for teachers
            const displayName = data.student_name || (userType === 'teacher' ? 'Teacher' : 'Student');
            if (userType === 'teacher' && data.identifier) {
                document.getElementById('success-student').textContent = `${displayName} (${data.identifier})`;
            } else {
                document.getElementById('success-student').textContent = displayName;
            }

            // Format time display
            const timeLabel = data.status === 'time_in' ? 'Time In' :
                             data.status === 'time_out' ? 'Time Out' : 'Time';
            const timeValue = data.time_in || data.time_out || new Date().toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

            // Remove any previous meta bar
            const prevMeta = document.getElementById('success-meta');
            if (prevMeta) prevMeta.remove();

            // Build session/late/assigned meta
            const sessionRaw = (data.session || data.assigned_session || '').toString().toLowerCase();
            let metaHtml = '<div id="success-meta" style="margin:8px 0;display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;">';
            if (sessionRaw) {
                const sessionText = sessionRaw.includes('afternoon') || sessionRaw.includes('pm')
                    ? 'PM'
                    : (sessionRaw.includes('morning') || sessionRaw.includes('am') ? 'AM' : sessionRaw.toUpperCase());
                const isLate = !!data.late;
                const badgeClass = isLate ? 'badge-late' : 'badge-on-time';
                metaHtml += `<span class="status-badge ${badgeClass}">${sessionText}${isLate ? ' • Late' : ''}</span>`;
            }
            if (data.assigned_session && data.assigned_session !== data.session) {
                metaHtml += `<span class="status-badge badge-assigned">Assigned: ${data.assigned_session}</span>`;
            }
            if (data.auto_marked_absent) {
                metaHtml += `<span style="color:#fff;font-size:13px;">Auto-marked absent: ${data.auto_marked_absent}</span>`;
            }
            metaHtml += '</div>';

            // Insert meta right after student name
            document.getElementById('success-student').insertAdjacentHTML('afterend', metaHtml);

            document.getElementById('success-time').textContent = `${timeLabel}: ${timeValue}`;
            document.getElementById('success-overlay').classList.add('active');
            
            // Play success sound if available
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBQ==');
                audio.play().catch(() => {});
            } catch (e) {}
            
            // Auto-close after 3 seconds
            setTimeout(() => {
                closeResultOverlay();
            }, 3000);
        }

        // Show Error
        function showError(message) {
            document.getElementById('error-message').textContent = message;
            document.getElementById('error-overlay').classList.add('active');
            
            // Auto-close after 3 seconds
            setTimeout(() => {
                closeResultOverlay();
            }, 3000);
        }

        // Close Result Overlay
        function closeResultOverlay() {
            document.getElementById('success-overlay').classList.remove('active');
            document.getElementById('error-overlay').classList.remove('active');
            // Remove injected meta bar if present
            const meta = document.getElementById('success-meta');
            if (meta) meta.remove();
            startScanning();
        }

        // Manual Entry Functions
        function openManualEntry() {
            document.getElementById('manual-modal').classList.add('active');
        }

        function closeManualEntry() {
            document.getElementById('manual-modal').classList.remove('active');
        }

        // Manual Form Submit
        document.getElementById('manual-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const lrnInput = document.getElementById('manual-lrn');
            const lrn = lrnInput.value.trim();
            const errorDiv = document.getElementById('lrn-error');
            
            // Validate LRN or Employee Number (7 OR 11-13 digits)
            if (!/^(?:\d{11,13}|\d{7})$/.test(lrn)) {
                errorDiv.textContent = 'Please enter a valid 11-13 digit LRN or 7-digit Employee Number';
                errorDiv.classList.add('active');
                lrnInput.style.borderColor = 'var(--error-500)';
                return;
            }
            
            // Clear validation
            errorDiv.classList.remove('active');
            lrnInput.style.borderColor = '';
            
            closeManualEntry();
            await handleScanResult(lrn);
            
            lrnInput.value = '';
        });

        // Real-time validation for manual LRN input
        document.getElementById('manual-lrn').addEventListener('input', (e) => {
            const input = e.target;
            const value = input.value;
            const errorDiv = document.getElementById('lrn-error');
            
            // Only allow numbers
            input.value = value.replace(/[^\d]/g, '');

            // Accept either 7 digits (employee number) or 11-13 digits (LRN)
            if ((input.value.length >= 11 && input.value.length <= 13) || input.value.length === 7) {
                errorDiv.classList.remove('active');
                input.style.borderColor = 'var(--success-500)';
            } else if (input.value.length > 0) {
                input.style.borderColor = 'var(--error-500)';
            } else {
                input.style.borderColor = '';
                errorDiv.classList.remove('active');
            }
        });

        // Menu Toggle
        function toggleMenu() {
            document.getElementById('mobile-menu').classList.toggle('active');
            document.getElementById('menu-backdrop').classList.toggle('active');
        }

        // Event Listeners
        document.getElementById('start-scan-btn').addEventListener('click', startScanning);
        document.getElementById('stop-scan-btn').addEventListener('click', stopScanning);

        // Initialize
        window.addEventListener('load', () => {
            initializeScanner();
            
            // Set default schedule info
            document.getElementById('schedule-info').textContent = 'Ready to scan';
        });
    </script>
</body>
</html>

