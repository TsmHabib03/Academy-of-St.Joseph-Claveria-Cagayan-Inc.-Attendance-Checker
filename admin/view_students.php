<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Student List';
$pageIcon = 'users';

// Get all students with attendance stats
try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $classFilter = isset($_GET['class']) ? trim($_GET['class']) : '';
    $sectionFilter = isset($_GET['section']) ? trim($_GET['section']) : '';
    
    $sql = "SELECT s.*, 
            COUNT(DISTINCT a.date) as attendance_days,
            MAX(a.date) as last_attendance
            FROM students s
            LEFT JOIN attendance a ON s.lrn = a.lrn
            WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (s.lrn LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.mobile_number LIKE ? OR s.section LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_fill(0, 5, $searchTerm);
    }
    
    if (!empty($classFilter)) {
        $sql .= " AND s.class = ?";
        $params[] = $classFilter;
    }
    
    if (!empty($sectionFilter)) {
        $sql .= " AND s.section = ?";
        $params[] = $sectionFilter;
    }
    
    $sql .= " GROUP BY s.id ORDER BY s.last_name, s.first_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique classes and sections for filters
    $stmt = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND section != '' ORDER BY section");
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get total students count
    $totalStudents = count($students);
    
} catch (Exception $e) {
    error_log("View students error: " . $e->getMessage());
    $students = [];
    $classes = [];
    $sections = [];
    $totalStudents = 0;
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

    /* Page Header Stats */
    .stats-bar {
        background: white;
        padding: var(--space-5);
        border-radius: var(--radius-xl);
        margin-bottom: var(--space-6);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--space-4);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3);
        border-radius: var(--radius-lg);
        transition: all var(--transition-base);
    }

    .stat-item:hover {
        background: var(--gray-50);
        transform: translateY(-2px);
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-xl);
        background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: var(--text-xl);
        box-shadow: var(--shadow-md);
        flex-shrink: 0;
    }

    .stat-details {
        flex: 1;
        min-width: 0;
    }

    .stat-details h3 {
        font-size: var(--text-2xl);
        font-weight: 700;
        color: var(--gray-900);
        margin: 0;
        line-height: 1.2;
    }

    .stat-details p {
        font-size: var(--text-sm);
        color: var(--gray-600);
        margin: var(--space-1) 0 0;
        font-weight: 500;
    }

    /* Search and Filter Bar */
    .filter-bar {
        background: white;
        padding: var(--space-5);
        border-radius: var(--radius-xl);
        margin-bottom: var(--space-6);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
    }

    .filter-row {
        display: flex;
        gap: var(--space-3);
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-search {
        flex: 1;
        min-width: 280px;
        position: relative;
    }

    .filter-search input {
        padding-left: 48px;
        height: 44px;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: var(--text-base);
        transition: all var(--transition-base);
    }

    .filter-search input:focus {
        outline: none;
        border-color: var(--asj-green-500);
        box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
    }

    .filter-search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        pointer-events: none;
        font-size: var(--text-lg);
    }

    .filter-select {
        min-width: 180px;
    }

    .filter-select select {
        height: 44px;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: var(--text-base);
        padding: 0 var(--space-4);
        transition: all var(--transition-base);
    }

    .filter-select select:focus {
        outline: none;
        border-color: var(--asj-green-500);
        box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
    }

    /* Responsive Table */
    .responsive-table-wrapper {
        background: white;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        border: 1px solid var(--gray-200);
    }

    .table-header {
        padding: var(--space-5);
        border-bottom: 2px solid var(--neutral-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, var(--asj-green-50) 0%, var(--neutral-50) 100%);
    }

    .table-title {
        font-size: var(--text-xl);
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .table-title i {
        width: 40px;
        height: 40px;
        background: white;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--asj-green-600);
        box-shadow: var(--shadow-sm);
    }

    /* Desktop Table */
    .desktop-table {
        width: 100%;
        display: table;
        table-layout: auto;
        min-width: 100%;
    }

    .desktop-table thead {
        background: var(--gray-50);
    }

    .desktop-table th {
        padding: var(--space-4);
        text-align: left;
        font-weight: 600;
        color: var(--gray-700);
        font-size: var(--text-sm);
        border-bottom: 2px solid var(--gray-200);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    .desktop-table th:first-child {
        width: 130px;
    }

    .desktop-table th:nth-child(2) {
        width: auto;
        min-width: 180px;
    }

    .desktop-table th:nth-child(3) {
        width: 90px;
    }

    .desktop-table th:nth-child(4) {
        width: auto;
        min-width: 200px;
    }

    .desktop-table th:nth-child(5) {
        width: 120px;
    }

    .desktop-table th:last-child {
        width: 180px;
        text-align: center;
    }

    .desktop-table td {
        padding: var(--space-4);
        border-bottom: 1px solid var(--gray-100);
        font-size: var(--text-sm);
        vertical-align: middle;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .desktop-table tbody tr {
        transition: all 0.2s ease;
    }

    .desktop-table tbody tr:hover {
        background: var(--asj-green-50);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .desktop-table tr:last-child td {
        border-bottom: none;
    }

    /* Table wrapper with horizontal scroll on smaller screens */
    .table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 1200px) {
        .table-wrapper {
            margin: 0 calc(-1 * var(--space-4));
            padding: 0 var(--space-4);
        }
    }

    .student-name {
        font-weight: 600;
        color: var(--gray-900);
        word-break: break-word;
    }

    .student-lrn {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: var(--asj-green-600);
        white-space: nowrap;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        padding: 4px 8px;
        border-radius: var(--radius-md);
        font-size: var(--text-xs);
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-primary {
        background: var(--asj-green-50);
        color: var(--asj-green-700);
    }

    .badge-error {
        background: var(--error-light);
        color: var(--error-dark);
    }

    .badge-info {
        background: var(--info-light);
        color: var(--info-dark);
    }

    .badge i {
        font-size: 10px;
    }

    /* Button Sizes */
    .btn-sm {
        padding: 6px 10px;
        font-size: var(--text-xs);
        border-radius: var(--radius-md);
        min-height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-1);
        border: none;
        cursor: pointer;
        transition: all var(--transition-base);
        text-decoration: none;
    }

    .btn-sm i {
        font-size: 12px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--asj-green-600), var(--asj-green-700));
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success), var(--success-dark));
        color: white;
    }

    .btn-success:hover {
        background: linear-gradient(135deg, var(--success-dark), #047857);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning), var(--warning-dark));
        color: white;
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, var(--warning-dark), #b45309);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--error), var(--error-dark));
        color: white;
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, var(--error-dark), #b91c1c);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: var(--neutral-200);
        color: var(--neutral-700);
    }

    .btn-secondary:hover {
        background: var(--neutral-300);
    }

    .btn-outline {
        background: white;
        border: 2px solid var(--neutral-300);
        color: var(--neutral-700);
    }

    .btn-outline:hover {
        background: var(--asj-green-50);
        border-color: var(--asj-green-500);
        color: var(--asj-green-700);
    }

    /* Mobile Cards */
    .mobile-cards {
        display: none;
    }

    .student-card {
        padding: var(--space-4);
        border-bottom: 1px solid var(--gray-100);
        transition: all var(--transition-base);
    }

    .student-card:last-child {
        border-bottom: none;
    }

    .student-card:active {
        background: var(--gray-50);
    }

    .student-card-header {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        margin-bottom: var(--space-3);
    }

    .student-card-avatar {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-full);
        background: linear-gradient(135deg, var(--primary-400), var(--primary-600));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: var(--text-lg);
    }

    .student-card-info h4 {
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--gray-900);
        margin: 0 0 var(--space-1) 0;
    }

    .student-card-info p {
        font-size: var(--text-sm);
        color: var(--gray-500);
        margin: 0;
    }

    .student-card-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-3);
        margin-bottom: var(--space-3);
    }

    .student-card-field {
        display: flex;
        flex-direction: column;
    }

    .student-card-label {
        font-size: var(--text-xs);
        color: var(--gray-500);
        margin-bottom: var(--space-1);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .student-card-value {
        font-size: var(--text-sm);
        color: var(--gray-900);
        font-weight: 500;
    }

    .student-card-actions {
        display: flex;
        gap: var(--space-2);
    }

    .table-actions {
        display: flex;
        gap: var(--space-2);
        justify-content: center;
        flex-wrap: nowrap;
    }

    .table-actions .btn {
        min-width: 36px;
        padding: var(--space-2);
    }

    @media (max-width: 1200px) {
        .table-actions {
            gap: var(--space-1);
        }
        
        .table-actions .btn {
            min-width: 32px;
            padding: 6px;
            font-size: 12px;
        }
    }

    /* View Details Modal Styles */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: var(--space-4);
        animation: fadeIn 0.2s ease;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-container {
        width: 100%;
        max-width: 600px;
        animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-content {
        background: white;
        border-radius: var(--radius-2xl);
        box-shadow: var(--shadow-2xl);
        overflow: hidden;
    }

    .modal-header {
        padding: var(--space-5);
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, var(--primary-50) 0%, var(--blue-50) 100%);
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
        width: 40px;
        height: 40px;
        border-radius: var(--radius-lg);
        border: none;
        background: white;
        color: var(--gray-600);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .modal-close:hover {
        background: var(--red-500);
        color: white;
        transform: rotate(90deg);
    }

    .modal-body {
        padding: var(--space-6);
        max-height: 70vh;
        overflow-y: auto;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4);
        margin-bottom: var(--space-5);
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .detail-item.full-width {
        grid-column: 1 / -1;
    }

    .detail-label {
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--gray-500);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .detail-value {
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--gray-900);
    }

    .qr-code-section {
        text-align: center;
        padding: var(--space-5);
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        margin-bottom: var(--space-5);
    }

    .qr-code-container {
        display: inline-block;
        padding: var(--space-4);
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        margin-bottom: var(--space-3);
    }

    .modal-actions {
        display: flex;
        gap: var(--space-3);
        padding-top: var(--space-4);
        border-top: 1px solid var(--gray-200);
    }

    /* Tablet and Mobile view */
    @media (max-width: 1024px) {
        .desktop-table th:nth-child(2),
        .desktop-table td:nth-child(2) {
            min-width: 150px;
        }
        
        .desktop-table th:nth-child(4),
        .desktop-table td:nth-child(4) {
            min-width: 180px;
        }
    }

    @media (max-width: 768px) {
        .desktop-table {
            display: none;
        }

        .table-wrapper {
            margin: 0;
            padding: 0;
        }

        .mobile-cards {
            display: block;
        }

        .filter-row {
            flex-direction: column;
        }

        .filter-search,
        .filter-select {
            width: 100%;
            min-width: 100%;
        }

        .stats-bar {
            grid-template-columns: 1fr;
        }

        .stat-item {
            padding: var(--space-4);
        }

        .detail-grid {
            grid-template-columns: 1fr;
        }

        .table-header {
            flex-direction: column;
            gap: var(--space-3);
            align-items: flex-start;
        }

        .table-header > div {
            width: 100%;
            justify-content: flex-start;
        }
    }

    @media (max-width: 480px) {
        .stats-bar {
            padding: var(--space-3);
            gap: var(--space-2);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            font-size: var(--text-lg);
        }

        .stat-details h3 {
            font-size: var(--text-xl);
        }

        .stat-details p {
            font-size: var(--text-xs);
        }
    }

    .empty-state {
        text-align: center;
        padding: var(--space-12);
        color: var(--gray-500);
    }

    .empty-state i {
        font-size: var(--text-6xl);
        margin-bottom: var(--space-4);
        color: var(--gray-300);
    }

    .empty-state h3 {
        font-size: var(--text-xl);
        color: var(--gray-700);
        margin-bottom: var(--space-2);
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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

    /* Loading State */
    .btn-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.7;
    }

    .btn-loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
    }

    @keyframes spin {
        to { transform: translateY(-50%) rotate(360deg); }
    }

    /* Notification System */
    .notification-container {
        position: fixed;
        top: var(--space-4);
        right: var(--space-4);
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        max-width: 400px;
        pointer-events: none;
    }

    .alert {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        animation: slideInRight 0.3s ease;
        pointer-events: all;
        backdrop-filter: blur(10px);
        min-width: 300px;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
        color: white;
    }

    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95));
        color: white;
    }

    .alert-warning {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.95), rgba(217, 119, 6, 0.95));
        color: white;
    }

    .alert-info {
        background: linear-gradient(135deg, rgba(14, 165, 233, 0.95), rgba(2, 132, 199, 0.95));
        color: white;
    }

    .alert-icon {
        font-size: var(--text-xl);
        flex-shrink: 0;
    }

    .alert-content {
        flex: 1;
        font-weight: 500;
    }

    .alert-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: var(--radius-md);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .alert-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }

    @media (max-width: 480px) {
        .notification-container {
            right: var(--space-2);
            left: var(--space-2);
            max-width: none;
        }

        .alert {
            min-width: auto;
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
                    <span class="breadcrumb-current"><?php echo $pageTitle; ?></span>
                </div>
                <h1 class="page-title-enhanced"><?php echo $pageTitle; ?></h1>
                <p class="page-subtitle-enhanced">
                    <i class="fas fa-info-circle"></i>
                    <span>View and manage all registered students</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <button class="btn-header btn-header-secondary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
            <a href="manage_students.php" class="btn-header btn-header-primary">
                <i class="fas fa-user-plus"></i>
                <span>Add Student</span>
            </a>
        </div>
    </div>
</div>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="stat-item">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $totalStudents; ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon">
            <i class="fas fa-chalkboard"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo count($classes); ?></h3>
            <p>Active Classes</p>
        </div>
    </div>
    <?php if (!empty($_GET['search']) || !empty($_GET['class'])): ?>
    <div class="stat-item">
        <div class="stat-icon">
            <i class="fas fa-filter"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo count($students); ?></h3>
            <p>Filtered Results</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="" id="filterForm">
        <div class="filter-row">
            <div class="filter-search">
                <i class="fas fa-search filter-search-icon"></i>
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search by LRN, name, mobile number, or section..."
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                >
            </div>
            <div class="filter-select">
                <select name="class" class="form-control">
                    <option value="">All Grade Levels</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo htmlspecialchars($class); ?>" <?php echo (isset($_GET['class']) && $_GET['class'] === $class) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-select">
                <select name="section" class="form-control">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo htmlspecialchars($section); ?>" <?php echo (isset($_GET['section']) && $_GET['section'] === $section) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i>
                <span>Search</span>
            </button>
            <?php if (!empty($_GET['search']) || !empty($_GET['class']) || !empty($_GET['section'])): ?>
                <a href="view_students.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    <span>Clear</span>
                </a>
            <?php endif; ?>
            <a href="manage_students.php" class="btn btn-success">
                <i class="fas fa-plus"></i>
                <span>Add New</span>
            </a>
        </div>
    </form>
</div>

<!-- Table Wrapper -->
<div class="responsive-table-wrapper">
    <div class="table-header">
        <h3 class="table-title">
            <i class="fas fa-graduation-cap"></i>
            Students Directory
        </h3>
        <div style="display: flex; gap: var(--space-2);">
            <button onclick="exportToCSV()" class="btn btn-outline">
                <i class="fas fa-download"></i>
                <span>Export</span>
            </button>
        </div>
    </div>

    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <h3>No Students Found</h3>
            <p>Try adjusting your search criteria or add a new student.</p>
            <a href="manage_students.php" class="btn btn-primary" style="margin-top: var(--space-4);">
                <i class="fas fa-plus"></i>
                <span>Add Your First Student</span>
            </a>
        </div>
    <?php else: ?>
        <!-- Desktop Table -->
        <div class="table-wrapper">
            <table class="desktop-table">
                <thead>
                    <tr>
                        <th>LRN</th>
                        <th>Full Name</th>
                        <th>Sex</th>
                        <th>Mobile Number</th>
                        <th>Grade Level</th>
                        <th>Section</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <span class="student-lrn"><?php echo sanitizeOutput($student['lrn']); ?></span>
                        </td>
                        <td>
                            <span class="student-name"><?php echo sanitizeOutput(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name'])); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo ($student['sex'] ?? '') === 'Male' ? 'primary' : 'error'; ?>">
                                <i class="fas fa-<?php echo ($student['sex'] ?? '') === 'Male' ? 'mars' : 'venus'; ?>"></i>
                                <?php echo sanitizeOutput($student['sex'] ?? ''); ?>
                            </span>
                        </td>
                        <td><?php echo sanitizeOutput($student['mobile_number'] ?? ''); ?></td>
                        <td>
                            <span class="badge badge-primary"><?php echo sanitizeOutput($student['class'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-info"><?php echo sanitizeOutput($student['section'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button 
                                    class="btn btn-sm btn-primary" 
                                    data-action="view"
                                    data-student='<?php echo json_encode($student); ?>'
                                    title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a 
                                    href="manage_students.php?id=<?php echo $student['id']; ?>" 
                                    class="btn btn-sm btn-success" 
                                    title="Edit Student">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button 
                                    class="btn btn-sm btn-warning"
                                    data-action="qr"
                                    data-id="<?php echo $student['id']; ?>"
                                    data-lrn="<?php echo htmlspecialchars($student['lrn']); ?>"
                                    data-name="<?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])); ?>"
                                    data-qrcode="<?php echo htmlspecialchars($student['qr_code'] ?? ''); ?>"
                                    title="View QR Code">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                                <button 
                                    class="btn btn-sm btn-danger"
                                    data-action="delete"
                                    data-id="<?php echo $student['id']; ?>"
                                    data-name="<?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])); ?>"
                                    title="Delete Student">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile Cards -->
        <div class="mobile-cards">
            <?php foreach ($students as $student): ?>
                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-avatar">
                            <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                        </div>
                        <div class="student-card-info">
                            <h4><?php echo sanitizeOutput(trim($student['first_name'] . ' ' . $student['last_name'])); ?></h4>
                            <p><?php echo sanitizeOutput($student['class']); ?></p>
                        </div>
                    </div>
                    <div class="student-card-details">
                        <div class="student-card-field">
                            <span class="student-card-label">LRN</span>
                            <span class="student-card-value"><?php echo sanitizeOutput($student['lrn']); ?></span>
                        </div>
                        <div class="student-card-field">
                            <span class="student-card-label">Sex</span>
                            <span class="student-card-value">
                                <span class="badge badge-<?php echo ($student['sex'] ?? '') === 'Male' ? 'primary' : 'error'; ?>">
                                    <i class="fas fa-<?php echo ($student['sex'] ?? '') === 'Male' ? 'mars' : 'venus'; ?>"></i>
                                    <?php echo sanitizeOutput($student['sex'] ?? ''); ?>
                                </span>
                            </span>
                        </div>
                        <div class="student-card-field" style="grid-column: 1 / -1;">
                            <span class="student-card-label">Mobile Number</span>
                            <span class="student-card-value"><?php echo sanitizeOutput($student['mobile_number'] ?? ''); ?></span>
                        </div>
                    </div>
                    <div class="student-card-actions">
                        <button 
                            class="btn btn-sm btn-primary"
                            data-action="view"
                            data-student='<?php echo json_encode($student); ?>'
                            title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a 
                            href="manage_students.php?id=<?php echo $student['id']; ?>" 
                            class="btn btn-sm btn-success"
                            title="Edit Student">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button 
                            class="btn btn-sm btn-warning"
                            data-action="qr"
                            data-id="<?php echo $student['id']; ?>"
                            data-lrn="<?php echo htmlspecialchars($student['lrn']); ?>"
                            data-name="<?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])); ?>"
                            data-qrcode="<?php echo htmlspecialchars($student['qr_code'] ?? ''); ?>"
                            title="View QR Code">
                            <i class="fas fa-qrcode"></i>
                        </button>
                        <button 
                            class="btn btn-sm btn-danger"
                            data-action="delete"
                            data-id="<?php echo $student['id']; ?>"
                            data-name="<?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])); ?>"
                            title="Delete Student">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- View Student Details Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-graduate"></i>
                    Student Details
                </h3>
                <button class="modal-close" data-action="close-modal" data-modal="viewModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Student ID</span>
                        <span class="detail-value" id="view-id">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">LRN</span>
                        <span class="detail-value" id="view-lrn">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">First Name</span>
                        <span class="detail-value" id="view-firstname">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Middle Name</span>
                        <span class="detail-value" id="view-middlename">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Name</span>
                        <span class="detail-value" id="view-lastname">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Sex</span>
                        <span class="detail-value" id="view-sex">-</span>
                    </div>
                    <div class="detail-item full-width">
                        <span class="detail-label">Mobile Number</span>
                        <span class="detail-value" id="view-mobile">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Class/Section</span>
                        <span class="detail-value" id="view-class">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Attendance Days</span>
                        <span class="detail-value" id="view-attendance">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Registration Date</span>
                        <span class="detail-value" id="view-created">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Attendance</span>
                        <span class="detail-value" id="view-last-attendance">-</span>
                    </div>
                </div>
                <div class="modal-actions">
                    <a href="#" id="editStudentLink" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        <span>Edit Student</span>
                    </a>
                    <button class="btn btn-secondary" data-action="close-modal" data-modal="viewModal">
                        <i class="fas fa-times"></i>
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal-overlay" id="qrModal">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-qrcode"></i>
                    Student QR Code
                </h3>
                <button class="modal-close" data-action="close-modal" data-modal="qrModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="qr-code-section">
                    <h4 id="qr-student-name" style="margin-bottom: var(--space-3); color: var(--gray-900); text-align: center;">-</h4>
                    <div class="qr-code-container" style="text-align: center; padding: var(--space-6); background: var(--gray-50); border-radius: var(--radius-xl);">
                        <img id="qr-code-image" src="" alt="QR Code" style="max-width: 300px; width: 100%; height: auto; border: 4px solid white; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);">
                        <p style="color: var(--gray-600); margin-top: var(--space-4); font-size: var(--text-sm); font-style: italic;">
                            Scan this QR code for attendance
                        </p>
                    </div>
                    <p style="color: var(--gray-600); margin-top: var(--space-4); text-align: center;">
                        LRN: <strong id="qr-lrn" style="color: var(--primary-600); font-family: 'Courier New', monospace;">-</strong>
                    </p>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-warning" data-action="regenerate-qr" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="fas fa-sync-alt"></i>
                        <span>Regenerate</span>
                    </button>
                    <button class="btn btn-success" data-action="download-qr">
                        <i class="fas fa-download"></i>
                        <span>Download</span>
                    </button>
                    <button class="btn btn-primary" data-action="print-qr">
                        <i class="fas fa-print"></i>
                        <span>Print</span>
                    </button>
                    <button class="btn btn-secondary" data-action="close-modal" data-modal="qrModal">
                        <i class="fas fa-times"></i>
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-body" style="text-align: center; padding: var(--space-8);">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-5);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 32px; color: var(--red-500);"></i>
                </div>
                <h3 style="font-size: var(--text-2xl); font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-3);">Delete Student</h3>
                <p style="color: var(--gray-600); margin-bottom: var(--space-2);">
                    Are you sure you want to delete <strong id="delete-student-name">this student</strong>?
                </p>
                <p style="color: var(--red-600); font-weight: 600; font-size: var(--text-sm); margin-bottom: var(--space-6);">
                    ⚠️ This will permanently delete the student and all attendance records.
                </p>
                <div class="modal-actions" style="justify-content: center;">
                    <button class="btn btn-secondary" data-action="close-modal" data-modal="deleteModal" style="min-width: 140px;">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </button>
                    <button class="btn btn-danger" data-action="confirm-delete" id="confirmDeleteBtn" style="min-width: 140px;">
                        <i class="fas fa-trash"></i>
                        <span>Yes, Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * View Students - Modal and Button Functionality
 */

// State management
let currentDeleteId = null;
let currentDeleteName = '';

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// View Student Details
function viewStudentDetails(student) {
    // Populate modal fields
    document.getElementById('view-id').textContent = '#' + String(student.id).padStart(5, '0');
    document.getElementById('view-lrn').textContent = student.lrn || '-';
    document.getElementById('view-firstname').textContent = student.first_name || '-';
    document.getElementById('view-middlename').textContent = student.middle_name || '-';
    document.getElementById('view-lastname').textContent = student.last_name || '-';
    document.getElementById('view-sex').textContent = student.sex || '-';
    document.getElementById('view-mobile').textContent = student.mobile_number || '-';
    document.getElementById('view-class').textContent = student.class || '-';
    document.getElementById('view-attendance').textContent = student.attendance_days || '0';
    
    // Format dates
    if (student.created_at) {
        const createdDate = new Date(student.created_at);
        document.getElementById('view-created').textContent = createdDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } else {
        document.getElementById('view-created').textContent = '-';
    }
    
    if (student.last_attendance) {
        const lastDate = new Date(student.last_attendance);
        document.getElementById('view-last-attendance').textContent = lastDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } else {
        document.getElementById('view-last-attendance').textContent = 'Never';
    }
    
    // Update edit link
    document.getElementById('editStudentLink').href = 'manage_students.php?id=' + student.id;
    
    openModal('viewModal');
}

// State for QR operations
let currentQRStudentId = null;
let currentQRStudentLRN = null;

// Generate QR Code - Now displays the pre-generated image
function generateQRCode(studentId, lrn, name, qrCodePath) {
    currentQRStudentId = studentId;
    currentQRStudentLRN = lrn;
    
    document.getElementById('qr-student-name').textContent = name;
    document.getElementById('qr-lrn').textContent = lrn;
    
    const qrImage = document.getElementById('qr-code-image');
    
    if (qrCodePath) {
        // Display the pre-generated QR code
        qrImage.src = '../' + qrCodePath + '?v=' + Date.now(); // Add timestamp to prevent caching
        qrImage.style.display = 'block';
        qrImage.alt = 'QR Code for ' + name;
    } else {
        // No QR code available
        qrImage.style.display = 'none';
        showNotification('QR code not available for this student. Please regenerate.', 'warning');
    }
    
    openModal('qrModal');
}

// Regenerate QR Code
async function regenerateQRCode() {
    if (!currentQRStudentId) {
        showNotification('No student selected', 'error');
        return;
    }
    
    const regenerateBtn = document.querySelector('[data-action="regenerate-qr"]');
    const originalText = regenerateBtn.innerHTML;
    regenerateBtn.disabled = true;
    regenerateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Regenerating...</span>';
    
    try {
        const response = await fetch('../api/regenerate_qrcode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                student_id: currentQRStudentId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update the QR code image
            const qrImage = document.getElementById('qr-code-image');
            qrImage.src = data.qr_code_path;
            qrImage.style.display = 'block';
            
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message || 'Failed to regenerate QR code', 'error');
        }
    } catch (error) {
        console.error('Regenerate QR error:', error);
        showNotification('Network error. Please try again.', 'error');
    } finally {
        regenerateBtn.disabled = false;
        regenerateBtn.innerHTML = originalText;
    }
}

// Download QR Code
function downloadQRCode() {
    const qrImage = document.getElementById('qr-code-image');
    if (qrImage && qrImage.src) {
        const lrn = document.getElementById('qr-lrn').textContent;
        const name = document.getElementById('qr-student-name').textContent;
        
        // Create a temporary link to download the image
        const link = document.createElement('a');
        link.href = qrImage.src;
        link.download = `QR_${lrn}_${name.replace(/\s+/g, '_')}.png`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('QR code downloaded successfully!', 'success');
    } else {
        showNotification('No QR code available to download', 'error');
    }
}

// Print QR Code
function printQRCode() {
    const qrImage = document.getElementById('qr-code-image');
    if (qrImage && qrImage.src) {
        const lrn = document.getElementById('qr-lrn').textContent;
        const name = document.getElementById('qr-student-name').textContent;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Student QR Code - ${name}</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                    }
                    h1 { font-size: 28px; margin-bottom: 10px; color: #333; }
                    p { font-size: 18px; color: #666; margin: 5px 0; }
                    .qr-container { 
                        margin: 20px 0; 
                        padding: 30px; 
                        border: 3px solid var(--asj-green-500, #4CAF50); 
                        border-radius: 15px;
                        background: #f9fafb;
                    }
                    .qr-container img {
                        max-width: 300px;
                        width: 100%;
                        height: auto;
                    }
                    .scan-text {
                        font-size: 14px;
                        color: var(--asj-green-700, #388E3C);
                        font-weight: bold;
                        margin-top: 15px;
                        font-style: italic;
                    }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                <h1>${name}</h1>
                <p><strong>LRN:</strong> ${lrn}</p>
                <div class="qr-container">
                    <img src="${qrImage.src}" alt="QR Code" />
                </div>
                <p class="scan-text">Scan this QR code for attendance</p>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    } else {
        showNotification('No QR code available to print', 'error');
    }
}

// Show Delete Modal
function showDeleteModal(id, name) {
    currentDeleteId = id;
    currentDeleteName = name;
    document.getElementById('delete-student-name').textContent = name;
    openModal('deleteModal');
}

// Confirm Delete
async function confirmDelete() {
    if (!currentDeleteId) return;
    
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.classList.add('btn-loading');
    confirmBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('student_id', currentDeleteId);
        
        const response = await fetch('../api/delete_student.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Student deleted successfully!', 'success');
            closeModal('deleteModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to delete student', 'error');
            confirmBtn.classList.remove('btn-loading');
            confirmBtn.disabled = false;
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Network error. Please try again.', 'error');
        confirmBtn.classList.remove('btn-loading');
        confirmBtn.disabled = false;
    }
}

// Notification System
function showNotification(message, type = 'info') {
    let container = document.querySelector('.notification-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    notification.innerHTML = `
        <div class="alert-icon">
            <i class="fas fa-${icons[type] || 'info-circle'}"></i>
        </div>
        <div class="alert-content">${message}</div>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Export to CSV
function exportToCSV() {
    const table = document.querySelector('.desktop-table');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        cols.forEach((col, index) => {
            // Skip the last column (Actions)
            if (index < cols.length - 1) {
                let text = col.textContent.trim().replace(/\s+/g, ' ');
                // Escape quotes and wrap in quotes if contains comma
                text = text.replace(/"/g, '""');
                if (text.includes(',') || text.includes('"')) {
                    text = `"${text}"`;
                }
                csvRow.push(text);
            }
        });
        csv.push(csvRow.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const date = new Date().toISOString().split('T')[0];
    link.setAttribute('href', url);
    link.setAttribute('download', `students_list_${date}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showNotification('Student list exported successfully!', 'success');
}

// Event Delegation
document.addEventListener('DOMContentLoaded', function() {
    // Button click handlers
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        
        const action = target.dataset.action;
        
        if (action === 'view') {
            try {
                const student = JSON.parse(target.dataset.student);
                viewStudentDetails(student);
            } catch (error) {
                console.error('Error parsing student data:', error);
                showNotification('Error loading student details', 'error');
            }
        } else if (action === 'qr') {
            const studentId = target.dataset.id;
            const lrn = target.dataset.lrn;
            const name = target.dataset.name;
            const qrCodePath = target.dataset.qrcode;
            generateQRCode(studentId, lrn, name, qrCodePath);
        } else if (action === 'regenerate-qr') {
            regenerateQRCode();
        } else if (action === 'delete') {
            const id = target.dataset.id;
            const name = target.dataset.name;
            showDeleteModal(id, name);
        } else if (action === 'close-modal') {
            const modalId = target.dataset.modal;
            closeModal(modalId);
        } else if (action === 'confirm-delete') {
            confirmDelete();
        } else if (action === 'download-qr') {
            downloadQRCode();
        } else if (action === 'print-qr') {
            printQRCode();
        }
    });
    
    // Close modals on outside click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('viewModal');
            closeModal('qrModal');
            closeModal('deleteModal');
        }
    });
    
    console.log('✅ View Students functionality initialized');
});
</script>

<?php include 'includes/footer_modern.php'; ?>
