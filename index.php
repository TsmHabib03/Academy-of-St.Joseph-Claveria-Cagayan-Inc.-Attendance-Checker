<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6366f1">
    <meta name="description" content="Modern attendance tracking system with QR code scanning. Fast, secure, and effortless.">
    <title>AttendEase - Modern Attendance Made Simple</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    
    <!-- Modern Design CSS -->
    <link rel="stylesheet" href="css/modern-design.css">
    
    <style>
        /* Landing Page Specific Styles */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #ec4899;
            --success: #10b981;
            --gradient-hero: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            --gradient-cta: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow-x: hidden;
        }

        /* Sticky Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .logo i {
            font-size: 2rem;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-nav {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-nav-secondary {
            color: var(--primary);
            background: transparent;
        }

        .btn-nav-secondary:hover {
            background: #f3f4f6;
        }

        .btn-nav-primary {
            background: var(--gradient-hero);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-nav-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }

        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gradient-hero);
            overflow: hidden;
            padding-top: 80px;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.5) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.5) 0%, transparent 50%);
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            font-weight: 700;
            color: white;
            margin-bottom: 1.5rem;
            line-height: 1.1;
        }

        .hero-subtitle {
            font-size: clamp(1.125rem, 3vw, 1.5rem);
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }

        .btn-hero {
            padding: 1.25rem 2.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            border-radius: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-hero-primary {
            background: white;
            color: var(--primary);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            animation: pulse 2s infinite;
        }

        .btn-hero-primary:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            backdrop-filter: blur(10px);
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-4px);
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .scroll-indicator {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(10px); }
        }

        /* Section Styles */
        section {
            padding: 6rem 2rem;
        }

        .section-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            color: #111827;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.25rem;
            color: #6b7280;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Feature Cards */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--gradient-hero);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
        }

        .feature-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 1rem;
        }

        .feature-description {
            color: #6b7280;
            line-height: 1.6;
        }

        /* How It Works */
        .steps-container {
            display: flex;
            justify-content: center;
            align-items: stretch;
            gap: 3rem;
            position: relative;
            flex-wrap: wrap;
        }

        .steps-container::before {
            content: '';
            position: absolute;
            top: 50px;
            left: 10%;
            right: 10%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            z-index: 0;
        }

        .step {
            flex: 1;
            min-width: 250px;
            max-width: 350px;
            text-align: center;
            position: relative;
            z-index: 1;
            padding: 0 1.5rem;
        }

        .step-number {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            background: var(--gradient-hero);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
        }

        .step-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.75rem;
        }

        .step-description {
            color: #6b7280;
            line-height: 1.6;
            max-width: 100%;
        }

        /* Stats Section */
        .stats-section {
            background: #f9fafb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
        }

        .stat-number {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 1.125rem;
        }

        /* CTA Section */
        .cta-section {
            background: var(--gradient-cta);
            color: white;
            text-align: center;
        }

        .cta-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .cta-subtitle {
            font-size: 1.25rem;
            opacity: 0.95;
            margin-bottom: 2.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Footer */
        .footer {
            background: #111827;
            color: white;
            padding: 4rem 2rem 2rem;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-section h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-container {
                padding: 1rem;
            }

            .nav-buttons {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .hero {
                min-height: auto;
                padding: 8rem 1rem 4rem;
            }

            .hero-cta {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-hero {
                justify-content: center;
            }

            .steps-container {
                flex-direction: column;
                align-items: center;
                gap: 2.5rem;
            }

            .steps-container::before {
                display: none;
            }
            
            .step {
                padding: 0 1rem;
                max-width: 100%;
                width: 100%;
                min-width: auto;
            }
            
            .step-number {
                width: 90px;
                height: 90px;
                font-size: 2.25rem;
                margin-bottom: 1rem;
            }
            
            .step-title {
                font-size: 1.375rem;
                margin-bottom: 0.875rem;
            }
            
            .step-description {
                font-size: 1rem;
                line-height: 1.7;
                padding: 0 0.5rem;
            }

            section {
                padding: 4rem 1rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .feature-card {
                padding: 2rem 1.5rem;
            }
        }
        
        /* Tablet responsive */
        @media (max-width: 1024px) and (min-width: 769px) {
            .steps-container {
                gap: 2rem;
            }
            
            .step {
                min-width: 200px;
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="#" class="logo">
                <i class="fas fa-qrcode"></i>
                <span>AttendEase</span>
            </a>
            <nav class="nav-buttons">
                <a href="scan_attendance.php" class="btn-nav btn-nav-primary">
                    <i class="fas fa-qrcode"></i> Scan Attendance
                </a>
                <a href="admin/login.php" class="btn-nav btn-nav-secondary">
                    <i class="fas fa-lock"></i> Admin Login
                </a>
            </nav>
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-background"></div>
        <div class="hero-container">
            <h1 class="hero-title" data-aos="fade-up">
                Never Miss a Beat<br>Attendance in Seconds ⚡
            </h1>
            <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="100">
                Track student attendance with just a scan. Fast, secure, and effortless.
                Join thousands using the modern way to manage attendance.
            </p>
            <div class="hero-cta" data-aos="fade-up" data-aos-delay="200">
                <a href="scan_attendance.php" class="btn-hero btn-hero-primary">
                    <i class="fas fa-qrcode"></i>
                    Scan Attendance Now
                </a>
                <a href="admin/login.php" class="btn-hero btn-hero-secondary">
                    <i class="fas fa-shield-alt"></i>
                    Admin Portal
                </a>
            </div>
            <div class="scroll-indicator" data-aos="fade-in" data-aos-delay="400">
                <span>Scroll to Learn More</span>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section>
        <div class="section-container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">Why Choose AttendEase?</h2>
                <p class="section-subtitle">Everything you need for modern attendance tracking</p>
            </div>
            <div class="features-grid">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h3 class="feature-title">Instant Scanning</h3>
                    <p class="feature-description">
                        Scan QR codes in seconds using any device with a camera. 
                        No special hardware required.
                    </p>
                </div>
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="feature-title">Lightning Fast</h3>
                    <p class="feature-description">
                        Mark attendance instantly with real-time processing. 
                        See results immediately on your dashboard.
                    </p>
                </div>
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="feature-title">Real-Time Reports</h3>
                    <p class="feature-description">
                        View live attendance dashboards with detailed analytics 
                        and exportable reports.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section style="background: #f9fafb;">
        <div class="section-container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">How It Works</h2>
                <p class="section-subtitle">Three simple steps to get started</p>
            </div>
            <div class="steps-container">
                <div class="step" data-aos="fade-up" data-aos-delay="0">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Get Registered</h3>
                    <p class="step-description">
                        Contact your administrator to register. 
                        Receive your unique QR code instantly.
                    </p>
                </div>
                <div class="step" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-number">2</div>
                    <h3 class="step-title">Scan</h3>
                    <p class="step-description">
                        Scan your QR code or enter your LRN manually 
                        to mark your attendance.
                    </p>
                </div>
                <div class="step" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Done!</h3>
                    <p class="step-description">
                        Attendance recorded instantly. 
                        View your attendance history anytime.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="section-container">
            <div class="stats-grid">
                <div class="stat-card" data-aos="fade-up" data-aos-delay="0">
                    <div class="stat-number" id="totalStudents">1,250+</div>
                    <div class="stat-label">Registered Students</div>
                </div>
                <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Daily Scans</div>
                </div>
                <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-number">99.9%</div>
                    <div class="stat-label">Uptime</div>
                </div>
                <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-number">⭐⭐⭐⭐⭐</div>
                    <div class="stat-label">Student Rated</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA Section -->
    <section class="cta-section">
        <div class="section-container">
            <h2 class="cta-title" data-aos="fade-up">Ready to Get Started?</h2>
            <p class="cta-subtitle" data-aos="fade-up" data-aos-delay="100">
                Join thousands of students using the fastest attendance system
            </p>
            <div class="cta-buttons" data-aos="fade-up" data-aos-delay="200">
                <a href="scan_attendance.php" class="btn-hero btn-hero-primary">
                    <i class="fas fa-rocket"></i>
                    Scan Attendance
                </a>
                <a href="admin/login.php" class="btn-hero btn-hero-secondary">
                    <i class="fas fa-shield-alt"></i>
                    Admin Portal
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-section">
                    <h3><i class="fas fa-qrcode"></i> AttendEase</h3>
                    <p style="color: rgba(255, 255, 255, 0.7);">
                        Modern attendance tracking made simple and secure.
                    </p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="scan_attendance.php"><i class="fas fa-qrcode"></i> Scan Attendance</a></li>
                        <li><a href="view_students.php"><i class="fas fa-users"></i> View Students</a></li>
                        <li><a href="admin/login.php"><i class="fas fa-lock"></i> Admin Login</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-envelope"></i> attendease08@gmail.com</li>
                        <li><i class="fas fa-map-marker-alt"></i> 33 F. Balagtas st. Sta. Lucia, Novaliches, Quezon City 1117</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 AttendEase Attendance System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        // Load dashboard statistics
        async function loadStats() {
            try {
                const response = await fetch('api/get_dashboard_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalStudents').textContent = 
                        data.totalStudents.toLocaleString() + '+';
                }
            } catch (error) {
                console.log('Stats loading optional');
            }
        }

        loadStats();

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Mobile menu toggle
        function toggleMobileMenu() {
            alert('Mobile menu - To be implemented with your existing menu system');
        }
    </script>
</body>
</html>
