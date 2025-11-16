<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#006400">
    <meta name="description" content="AttendEase - Smart Attendance Management System for Academy of St. Joseph, Claveria Cagayan Inc. The Josephites: Integrity, Excellence, Service.">
    <title>Academy of St. Joseph, Claveria | The Josephites</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Merriweather:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    
    <!-- ASJ Theme CSS -->
    <link rel="stylesheet" href="css/asj-theme.css?v=3.0">

</head>
<body>
    <!-- Header - Modern & Clean -->
    <header class="asj-header">
        <div class="container">
            <div class="header-content">
                <div class="school-branding">
                    <div class="logo-placeholder">
                        <img src="assets/asj-logo.png" alt="ASJ Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="school-info">
                        <h1>Academy of St. Joseph</h1>
                        <h2>Claveria, Cagayan Inc.</h2>
                    </div>
                </div>
                
                <div class="header-actions">
                    <a href="scan_attendance.php" class="btn-header btn-header-primary">
                        <i class="fas fa-qrcode"></i>
                        <span>Scan QR</span>
                    </a>
                    <a href="admin/login.php" class="btn-header btn-header-secondary">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section - Light & Welcoming -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <div class="hero-logo" data-aos="zoom-in">
                    <img src="assets/asj-logo.png" alt="Academy of St. Joseph Logo" onerror="this.style.display='none'">
                </div>
                
                <div class="hero-badge" data-aos="fade-down">
                    <i class="fas fa-shield-alt"></i>
                    <span>The Josephites</span>
                </div>
                
                <h1 class="hero-title" data-aos="fade-up" data-aos-delay="100">
                    Modern Attendance<br>Made Simple
                </h1>
                
                <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="200">
                    Fast, secure, and effortless attendance tracking for the<br>
                    Academy of St. Joseph community
                </p>
                
                <div class="hero-cta" data-aos="fade-up" data-aos-delay="300">
                    <a href="scan_attendance.php" class="btn-hero btn-hero-primary">
                        <i class="fas fa-qrcode"></i>
                        Mark Attendance
                    </a>
                    <a href="register_student.php" class="btn-hero btn-hero-secondary">
                        <i class="fas fa-user-plus"></i>
                        Register Student
                    </a>
                </div>
                
                <div class="hero-stats" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-item">
                        <span class="stat-number" id="totalStudents">1000+</span>
                        <span class="stat-label">Students</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">⚡</span>
                        <span class="stat-label">Instant Scan</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">99.9%</span>
                        <span class="stat-label">Uptime</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section - Clean Cards -->
    <section class="features-section">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">Quick Access</h2>
                <p class="section-subtitle">Choose what you'd like to do</p>
            </div>
            
            <div class="features-grid">
                <!-- QR Scanning -->
                <a href="scan_attendance.php" class="feature-card" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3>Scan QR Code</h3>
                    <p>Mark attendance instantly with QR code scanning</p>
                </a>

                <!-- Student Registration -->
                <a href="register_student.php" class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Register Student</h3>
                    <p>Add new students and generate their QR codes</p>
                </a>

                <!-- View Students -->
                <a href="view_students.php" class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>View Students</h3>
                    <p>Browse the complete student directory</p>
                </a>

                <!-- Admin Dashboard -->
                <a href="admin/login.php" class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Admin Dashboard</h3>
                    <p>Access reports, analytics, and settings</p>
                </a>
            </div>
        </div>
    </section>

    <!-- Core Values Section -->
    <section class="values-section">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">Our Core Values</h2>
                <p class="section-subtitle">Guiding principles of the Josephites</p>
            </div>
            
            <div class="values-grid">
                <div class="value-card" data-aos="fade-up" data-aos-delay="0">
                    <i class="fas fa-handshake"></i>
                    <h4>Integrity</h4>
                    <p>Honesty and strong moral principles</p>
                </div>
                <div class="value-card" data-aos="fade-up" data-aos-delay="100">
                    <i class="fas fa-hands-helping"></i>
                    <h4>Social Responsibility</h4>
                    <p>Serving our community with compassion</p>
                </div>
                <div class="value-card" data-aos="fade-up" data-aos-delay="200">
                    <i class="fas fa-trophy"></i>
                    <h4>Excellence</h4>
                    <p>Striving for the highest standards</p>
                </div>
                <div class="value-card" data-aos="fade-up" data-aos-delay="300">
                    <i class="fas fa-praying-hands"></i>
                    <h4>Evangelization</h4>
                    <p>Spreading faith and Catholic values</p>
                </div>
                
            </div>
        </div>
    </section>

    <!-- Vision, Mission & Goals Section -->
    <section class="vmg-section">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">Vision, Mission & Goals</h2>
                <p class="section-subtitle">Our commitment to excellence and service</p>
            </div>
            
            <div class="vmg-grid">
                <!-- Vision Card -->
                <div class="vmg-card vmg-vision" data-aos="fade-up" data-aos-delay="0">
                    <div class="vmg-card-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h4>Vision</h4>
                    <p>Academy of St. Joseph: Witness to the Word.</p>
                </div>

                <!-- Mission Card -->
                <div class="vmg-card vmg-mission" data-aos="fade-up" data-aos-delay="100">
                    <div class="vmg-card-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h4>Mission</h4>
                    <div class="vmg-list">
                        <div class="vmg-list-item">
                            <i class="fas fa-check-circle"></i>
                            <p><strong>As members of the institution,</strong> we are committed to the integral human formation and excellence in instruction.</p>
                        </div>
                        <div class="vmg-list-item">
                            <i class="fas fa-check-circle"></i>
                            <p><strong>As a Catholic school,</strong> we are committed to be Christ-centered in the Catholic tradition.</p>
                        </div>
                        <div class="vmg-list-item">
                            <i class="fas fa-check-circle"></i>
                            <p><strong>As an SVD school,</strong> we are committed to prophetic dialogue through mission awareness, Bible apostolate, communication, justice and peace and integrity of creation.</p>
                        </div>
                    </div>
                </div>

                <!-- Goals Card -->
                <div class="vmg-card vmg-goals" data-aos="fade-up" data-aos-delay="200">
                    <div class="vmg-card-icon">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <h4>Goals</h4>
                    <div class="vmg-list">
                        <div class="vmg-list-item">
                            <i class="fas fa-star"></i>
                            <p><strong>As an Academy,</strong> we aim to create a culture which places a high quality on the physical, spiritual, academic, character building, social affective and apostolic development of the students: service and values oriented achievers, and globally competitive Josephites.</p>
                        </div>
                        <div class="vmg-list-item">
                            <i class="fas fa-star"></i>
                            <p><strong>As a Catholic school,</strong> we aim to inculcate in our students and personnel Catholic education that is holistic, social, well-rounded and developmental in the context of the local church.</p>
                        </div>
                        <div class="vmg-list-item">
                            <i class="fas fa-star"></i>
                            <p><strong>As an SVD school,</strong> we aim to integrate prophetic dialogue and the SVD characteristic dimensions in the curricular and extra-curricular activities in the school; to develop our SVD spirituality and charism and share it with other schools in Cagayan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer - Light & Informative -->
    <footer class="asj-footer">
        <div class="container">
            <div class="footer-content">
                <!-- School Info -->
                <div class="footer-section">
                    <h4>Academy of St. Joseph</h4>
                    <p>A Catholic, private secondary school committed to providing integral human formation, academic excellence, and values-based learning.</p>
                </div>
                
                <!-- Contact Info -->
                <div class="footer-section">
                    <h4>Contact Us</h4>
                    <div class="footer-info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Barangay Centro I, Claveria, Cagayan, Philippines</span>
                    </div>
                    <div class="footer-info-item">
                        <i class="fas fa-phone"></i>
                        <span>Tel. (078) 866-1252</span>
                    </div>
                    <div class="footer-info-item">
                        <i class="fas fa-id-card"></i>
                        <span>DepEd School ID: 400362</span>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <div class="footer-info-item">
                        <i class="fas fa-qrcode"></i>
                        <a href="scan_attendance.php">Scan Attendance</a>
                    </div>
                    <div class="footer-info-item">
                        <i class="fas fa-shield-alt"></i>
                        <a href="admin/login.php">Admin Portal</a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 Academy of St. Joseph, Claveria Cagayan Inc. All Rights Reserved.</p>
                
            </div>
        </div>
    </footer>

    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Initialize AOS with modern settings
        AOS.init({
            duration: 600,
            easing: 'ease-out',
            once: true,
            offset: 50,
            delay: 0
        });

        // Load student statistics
        async function loadStats() {
            try {
                const response = await fetch('api/get_dashboard_stats.php');
                const data = await response.json();
                
                if (data.success && data.totalStudents) {
                    document.getElementById('totalStudents').textContent = 
                        data.totalStudents.toLocaleString() + '+';
                }
            } catch (error) {
                console.log('Stats loading optional');
            }
        }

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }
            });
        });

        // Load stats on page load
        window.addEventListener('DOMContentLoaded', loadStats);
    </script>
</body>
</html>
