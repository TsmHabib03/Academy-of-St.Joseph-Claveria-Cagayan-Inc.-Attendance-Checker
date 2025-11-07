<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Attendance Reports';
$pageIcon = 'chart-bar';

// Include the modern admin header
include 'includes/header_modern.php';
?>

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

    /* ===== PAGE HEADER ENHANCED ===== */
    .page-header-enhanced {
        background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%);
        border-radius: var(--radius-2xl);
        padding: var(--space-8);
        margin-bottom: var(--space-6);
        color: white;
        box-shadow: 0 10px 40px -10px rgba(76, 175, 80, 0.4);
        position: relative;
        overflow: hidden;
    }

    .page-header-enhanced::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle at center, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
        border-radius: 50%;
    }

    .page-header-content {
        position: relative;
        z-index: 1;
    }

    .page-header-content h1 {
        font-size: var(--text-3xl);
        font-weight: var(--font-bold);
        margin-bottom: var(--space-2);
        display: flex;
        align-items: center;
        gap: var(--space-3);
        color: white;
    }

    .page-header-content h1 i {
        font-size: var(--text-4xl);
    }

    .page-header-content p {
        font-size: var(--text-lg);
        opacity: 0.95;
        margin: 0;
    }

    /* ===== FILTERS SECTION ===== */
    .filters-card {
        background: white;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        margin-bottom: var(--space-6);
        overflow: hidden;
    }

    .filters-header {
        padding: var(--space-6);
        border-bottom: 1px solid var(--gray-200);
        background: linear-gradient(to right, var(--asj-green-50), var(--neutral-50));
    }

    .filters-header h2 {
        font-size: var(--text-xl);
        font-weight: var(--font-bold);
        color: var(--gray-900);
        margin: 0;
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .filters-header h2 i {
        color: var(--asj-green-600);
    }

    .filters-body {
        padding: var(--space-6);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: var(--space-4);
        margin-bottom: var(--space-6);
    }

    .filter-actions {
        display: flex;
        gap: var(--space-3);
        justify-content: flex-end;
        padding-top: var(--space-4);
        border-top: 1px solid var(--gray-200);
    }

    /* ===== STATS CARDS ===== */
    .stats-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: var(--space-4);
        margin-bottom: var(--space-6);
    }

    .stat-card-modern {
        background: white;
        border-radius: var(--radius-xl);
        padding: var(--space-6);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        transition: all var(--transition-base);
        position: relative;
        overflow: hidden;
    }

    .stat-card-modern::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        border-radius: 50%;
        opacity: 0.1;
        transition: all var(--transition-base);
    }

    .stat-card-modern:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
    }

    .stat-card-modern.primary::before {
        background: var(--asj-green-500);
    }

    .stat-card-modern.success::before {
        background: var(--success);
    }

    .stat-card-modern.warning::before {
        background: var(--warning);
    }

    .stat-card-modern.info::before {
        background: var(--info);
    }

    .stat-card-content {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        position: relative;
        z-index: 1;
    }

    .stat-icon-modern {
        width: 60px;
        height: 60px;
        border-radius: var(--radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--text-3xl);
        flex-shrink: 0;
    }

    .stat-card-modern.primary .stat-icon-modern {
        background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
        color: white;
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
    }

    .stat-card-modern.success .stat-icon-modern {
        background: linear-gradient(135deg, var(--success), var(--success-dark));
        color: white;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .stat-card-modern.warning .stat-icon-modern {
        background: linear-gradient(135deg, var(--warning), var(--warning-dark));
        color: white;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    .stat-card-modern.info .stat-icon-modern {
        background: linear-gradient(135deg, var(--info), var(--info-dark));
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .stat-details {
        flex: 1;
    }

    .stat-value {
        font-size: var(--text-3xl);
        font-weight: var(--font-bold);
        color: var(--gray-900);
        line-height: 1;
        margin-bottom: var(--space-2);
    }

    .stat-label {
        font-size: var(--text-sm);
        color: var(--gray-600);
        font-weight: var(--font-medium);
    }

    /* ===== RESULTS TABLE ===== */
    .results-card {
        background: white;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        overflow: hidden;
    }

    .results-header {
        padding: var(--space-6);
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--space-4);
        background: var(--gray-50);
    }

    .results-header h2 {
        font-size: var(--text-xl);
        font-weight: var(--font-bold);
        color: var(--gray-900);
        margin: 0;
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .results-actions {
        display: flex;
        gap: var(--space-2);
        flex-wrap: wrap;
    }

    .results-body {
        padding: var(--space-6);
    }

    .table-wrapper {
        overflow-x: auto;
        border-radius: var(--radius-lg);
        border: 1px solid var(--gray-200);
    }

    .modern-table {
        width: 100%;
        border-collapse: collapse;
        font-size: var(--text-sm);
    }

    .modern-table thead {
        background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%);
    }

    .modern-table th {
        padding: var(--space-4);
        text-align: left;
        font-weight: var(--font-semibold);
        color: white;
        white-space: nowrap;
        font-size: var(--text-sm);
    }

    .modern-table tbody tr {
        border-bottom: 1px solid var(--gray-200);
        transition: background-color var(--transition-base);
    }

    .modern-table tbody tr:hover {
        background: var(--asj-green-50);
    }

    .modern-table tbody tr:last-child {
        border-bottom: none;
    }

    .modern-table td {
        padding: var(--space-4);
        color: var(--gray-700);
    }

    .student-name-cell {
        font-weight: var(--font-semibold);
        color: var(--gray-900);
    }

    .status-badge-modern {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        padding: var(--space-1) var(--space-3);
        border-radius: var(--radius-full);
        font-size: var(--text-xs);
        font-weight: var(--font-semibold);
        white-space: nowrap;
    }

    .status-completed {
        background: var(--success-light);
        color: var(--success-dark);
    }

    .status-incomplete {
        background: var(--warning-light);
        color: var(--warning-dark);
    }

    .section-badge {
        display: inline-flex;
        align-items: center;
        padding: var(--space-1) var(--space-3);
        border-radius: var(--radius-full);
        font-size: var(--text-xs);
        font-weight: var(--font-semibold);
        background: var(--asj-green-100);
        color: var(--asj-green-700);
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: var(--space-16) var(--space-8);
    }

    .empty-state i {
        font-size: 80px;
        background: linear-gradient(135deg, var(--asj-green-400), var(--asj-green-600));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: var(--space-4);
    }

    .empty-state h3 {
        font-size: var(--text-xl);
        color: var(--gray-700);
        margin-bottom: var(--space-2);
    }

    .empty-state p {
        font-size: var(--text-base);
        color: var(--gray-500);
    }

    /* ===== PAGINATION ===== */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-6);
        border-top: 1px solid var(--gray-200);
    }

    .page-btn {
        padding: var(--space-2) var(--space-4);
        border: 1px solid var(--gray-300);
        background: white;
        color: var(--gray-700);
        border-radius: var(--radius-lg);
        font-size: var(--text-sm);
        font-weight: var(--font-medium);
        cursor: pointer;
        transition: all var(--transition-base);
        min-width: 40px;
    }

    .page-btn:hover:not(:disabled) {
        background: var(--asj-green-600);
        color: white;
        border-color: var(--asj-green-600);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(76, 175, 80, 0.2);
    }

    .page-btn.active {
        background: var(--asj-green-600);
        color: white;
        border-color: var(--asj-green-600);
    }

    .page-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .page-dots {
        padding: var(--space-2);
        color: var(--gray-500);
    }

    /* ===== LOADING OVERLAY ===== */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        color: white;
    }

    .spinner {
        width: 60px;
        height: 60px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: var(--space-4);
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .loading-text {
        font-size: var(--text-lg);
        font-weight: var(--font-semibold);
    }

    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 768px) {
        .page-header-enhanced {
            padding: var(--space-6);
        }

        .page-header-content h1 {
            font-size: var(--text-2xl);
        }

        .page-header-content h1 i {
            font-size: var(--text-3xl);
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            flex-direction: column;
        }

        .filter-actions .btn {
            width: 100%;
        }

        .stats-overview {
            grid-template-columns: 1fr;
        }

        .results-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .results-actions {
            width: 100%;
        }

        .results-actions .btn {
            flex: 1;
        }

        .table-wrapper {
            border-radius: 0;
            margin: 0 calc(-1 * var(--space-6));
        }

        .modern-table {
            font-size: var(--text-xs);
        }

        .modern-table th,
        .modern-table td {
            padding: var(--space-2);
        }

        .pagination-wrapper {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 480px) {
        .page-header-enhanced {
            padding: var(--space-4);
        }

        .filters-body,
        .results-body {
            padding: var(--space-4);
        }

        .stat-card-content {
            flex-direction: column;
            text-align: center;
        }

        .stat-value {
            font-size: var(--text-2xl);
        }
    }

    /* ===== PRINT STYLES ===== */
    @media print {
        .page-header-enhanced,
        .filters-card,
        .results-header .results-actions,
        .pagination-wrapper,
        .desktop-sidebar,
        .mobile-topbar,
        .filter-actions {
            display: none !important;
        }

        .results-card {
            box-shadow: none;
            border: 1px solid #000;
        }

        .modern-table {
            font-size: 10pt;
        }

        .modern-table thead {
            background: #333 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            background: white;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header-enhanced">
    <div class="page-header-content">
        <h1>
            <i class="fas fa-chart-bar"></i>
            Attendance Reports
        </h1>
        <p>Generate comprehensive attendance reports by section and date range</p>
    </div>
</div>

<!-- Filters Card -->
<div class="filters-card">
    <div class="filters-header">
        <h2><i class="fas fa-filter"></i> Report Filters</h2>
    </div>
    <div class="filters-body">
        <form id="reportFilters">
            <div class="filters-grid">
                <!-- Section Filter -->
                <div class="form-group">
                    <label for="section_filter" class="form-label">
                        <i class="fas fa-layer-group"></i> Section
                    </label>
                    <select id="section_filter" name="section" class="form-control">
                        <option value="">All Sections</option>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT section_name FROM sections WHERE status = 'active' ORDER BY section_name");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='" . htmlspecialchars($row['section_name']) . "'>" . htmlspecialchars($row['section_name']) . "</option>";
                            }
                        } catch (Exception $e) {
                            echo "<option value=''>Error loading sections</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Start Date -->
                <div class="form-group">
                    <label for="start_date" class="form-label">
                        <i class="fas fa-calendar-alt"></i> Start Date
                    </label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?= date('Y-m-01') ?>">
                </div>

                <!-- End Date -->
                <div class="form-group">
                    <label for="end_date" class="form-label">
                        <i class="fas fa-calendar-check"></i> End Date
                    </label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <!-- Student Search (Optional) -->
                <div class="form-group">
                    <label for="student_search" class="form-label">
                        <i class="fas fa-search"></i> Student Name/LRN
                    </label>
                    <input type="text" id="student_search" name="student_search" class="form-control" placeholder="Search by name or LRN...">
                </div>
            </div>

            <div class="filter-actions">
                <button type="button" id="resetFilters" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset Filters
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-chart-line"></i> Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div id="summarySection" style="display: none;">
    <div class="stats-overview">
        <div class="stat-card-modern primary">
            <div class="stat-card-content">
                <div class="stat-icon-modern">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value" id="total_records">0</div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>
        </div>

        <div class="stat-card-modern success">
            <div class="stat-card-content">
                <div class="stat-icon-modern">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value" id="completed_count">0</div>
                    <div class="stat-label">Completed (In & Out)</div>
                </div>
            </div>
        </div>

        <div class="stat-card-modern warning">
            <div class="stat-card-content">
                <div class="stat-icon-modern">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value" id="incomplete_count">0</div>
                    <div class="stat-label">Incomplete (In Only)</div>
                </div>
            </div>
        </div>

        <div class="stat-card-modern info">
            <div class="stat-card-content">
                <div class="stat-icon-modern">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value" id="sections_count">0</div>
                    <div class="stat-label">Sections Covered</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Results Table -->
<div id="resultsCard" class="results-card" style="display: none;">
    <div class="results-header">
        <h2>
            <i class="fas fa-table"></i> Attendance Records
        </h2>
        <div class="results-actions">
            <button id="printReport" class="btn btn-secondary btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <button id="exportCSV" class="btn btn-success btn-sm">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
        </div>
    </div>
    <div class="results-body">
        <!-- Table Container -->
        <div class="table-wrapper" id="tableContainer">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>LRN</th>
                        <th>Student Name</th>
                        <th>Section</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <!-- Populated via JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div id="noResults" class="empty-state" style="display: none;">
            <i class="fas fa-inbox"></i>
            <h3>No Records Found</h3>
            <p>No attendance records match your selected filters. Try adjusting your search criteria.</p>
        </div>

        <!-- Pagination -->
        <div id="paginationContainer" class="pagination-wrapper" style="display: none;">
            <!-- Populated via JavaScript -->
        </div>
    </div>
</div>

<script>
/**
 * Attendance Reports - Modern Implementation
 * Enhanced with responsive design and smooth animations
 */

// State Management
let currentPage = 1;
let rowsPerPage = 20;
let allRecords = [];
let currentFilters = {};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Attendance Reports System initialized');
    
    // Form submission handler
    document.getElementById('reportFilters').addEventListener('submit', async function(e) {
        e.preventDefault();
        await generateReport();
    });

    // Reset filters handler
    document.getElementById('resetFilters').addEventListener('click', function() {
        resetFilters();
    });

    // Export CSV handler
    document.getElementById('exportCSV').addEventListener('click', function() {
        exportToCSV();
    });

    // Print report handler
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });
});

// Generate Report Function
async function generateReport() {
    const formData = new FormData(document.getElementById('reportFilters'));
    const params = new URLSearchParams(formData);
    
    // Store current filters
    currentFilters = {
        section: formData.get('section'),
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        student_search: formData.get('student_search')
    };

    try {
        showLoading('Generating report...');
        
        const response = await fetch('../api/get_attendance_report_sections.php?' + params.toString());
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        hideLoading();

        if (data.success) {
            allRecords = data.records || [];
            displaySummary(data.summary || {});
            currentPage = 1;
            displayRecords();
            
            // Show summary and results sections
            document.getElementById('summarySection').style.display = 'block';
            document.getElementById('resultsCard').style.display = 'block';
            
            // Smooth scroll to results
            document.getElementById('summarySection').scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            showNotification('Report generated successfully!', 'success');
        } else {
            showNotification(data.message || 'Error generating report', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Report generation error:', error);
        showNotification('Error fetching report data. Please try again.', 'error');
    }
}

// Display Summary Statistics
function displaySummary(summary) {
    document.getElementById('total_records').textContent = summary.total_records || 0;
    document.getElementById('completed_count').textContent = summary.completed_count || 0;
    document.getElementById('incomplete_count').textContent = summary.incomplete_count || 0;
    document.getElementById('sections_count').textContent = summary.sections_count || 0;
}

// Display Records in Table
function displayRecords() {
    const tbody = document.getElementById('attendanceTableBody');
    const tableContainer = document.getElementById('tableContainer');
    const noResults = document.getElementById('noResults');
    const paginationContainer = document.getElementById('paginationContainer');
    
    tbody.innerHTML = '';

    if (allRecords.length === 0) {
        tableContainer.style.display = 'none';
        noResults.style.display = 'flex';
        paginationContainer.style.display = 'none';
        return;
    }

    tableContainer.style.display = 'block';
    noResults.style.display = 'none';
    paginationContainer.style.display = 'flex';

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const pageRecords = allRecords.slice(start, end);

    pageRecords.forEach((record, index) => {
        const row = document.createElement('tr');
        row.style.animation = `fadeIn 0.3s ease ${index * 0.05}s forwards`;
        row.style.opacity = '0';
        
        const statusClass = record.time_out ? 'status-completed' : 'status-incomplete';
        const statusText = record.time_out ? 'Completed' : 'Incomplete';
        const statusIcon = record.time_out ? 'fa-check-circle' : 'fa-clock';
        
        row.innerHTML = `
            <td><strong>${escapeHtml(record.lrn)}</strong></td>
            <td class="student-name-cell">${escapeHtml(record.student_name)}</td>
            <td><span class="section-badge">${escapeHtml(record.section)}</span></td>
            <td>${escapeHtml(record.date_formatted)}</td>
            <td>${escapeHtml(record.time_in || '-')}</td>
            <td>${escapeHtml(record.time_out || '-')}</td>
            <td>${escapeHtml(record.duration)}</td>
            <td>
                <span class="status-badge-modern ${statusClass}">
                    <i class="fas ${statusIcon}"></i>
                    ${statusText}
                </span>
            </td>
        `;
        
        tbody.appendChild(row);
    });

    renderPagination();
}

// Render Pagination Controls
function renderPagination() {
    const totalPages = Math.ceil(allRecords.length / rowsPerPage);
    const pagination = document.getElementById('paginationContainer');
    pagination.innerHTML = '';

    if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }

    pagination.style.display = 'flex';

    // Previous button
    const prevBtn = createPageButton('‹ Prev', currentPage === 1, () => {
        if (currentPage > 1) {
            currentPage--;
            displayRecords();
            scrollToTop();
        }
    });
    pagination.appendChild(prevBtn);

    // Page numbers
    const pageNumbers = generatePageNumbers(currentPage, totalPages);
    pageNumbers.forEach(page => {
        if (page === '...') {
            const dots = document.createElement('span');
            dots.className = 'page-dots';
            dots.textContent = '...';
            pagination.appendChild(dots);
        } else {
            const pageBtn = createPageButton(page, false, () => {
                currentPage = page;
                displayRecords();
                scrollToTop();
            }, page === currentPage);
            pagination.appendChild(pageBtn);
        }
    });

    // Next button
    const nextBtn = createPageButton('Next ›', currentPage === totalPages, () => {
        if (currentPage < totalPages) {
            currentPage++;
            displayRecords();
            scrollToTop();
        }
    });
    pagination.appendChild(nextBtn);
}

// Helper function to create page buttons
function createPageButton(text, disabled, onClick, active = false) {
    const btn = document.createElement('button');
    btn.textContent = text;
    btn.className = 'page-btn' + (active ? ' active' : '');
    btn.disabled = disabled;
    if (!disabled) {
        btn.onclick = onClick;
    }
    return btn;
}

// Generate page numbers with ellipsis
function generatePageNumbers(current, total) {
    const pages = [];
    
    if (total <= 7) {
        for (let i = 1; i <= total; i++) {
            pages.push(i);
        }
    } else {
        if (current <= 4) {
            for (let i = 1; i <= 5; i++) pages.push(i);
            pages.push('...');
            pages.push(total);
        } else if (current >= total - 3) {
            pages.push(1);
            pages.push('...');
            for (let i = total - 4; i <= total; i++) pages.push(i);
        } else {
            pages.push(1);
            pages.push('...');
            for (let i = current - 1; i <= current + 1; i++) pages.push(i);
            pages.push('...');
            pages.push(total);
        }
    }
    
    return pages;
}

// Export to CSV
function exportToCSV() {
    if (allRecords.length === 0) {
        showNotification('No data to export', 'warning');
        return;
    }

    const section = currentFilters.section || 'All_Sections';
    const startDate = currentFilters.start_date.replace(/-/g, '');
    const endDate = currentFilters.end_date.replace(/-/g, '');
    
    const params = new URLSearchParams(currentFilters);
    window.location.href = '../api/export_attendance_sections_csv.php?' + params.toString();
    
    showNotification('Exporting report to CSV...', 'info');
}

// Reset Filters
function resetFilters() {
    document.getElementById('reportFilters').reset();
    document.getElementById('start_date').value = '<?= date('Y-m-01') ?>';
    document.getElementById('end_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('summarySection').style.display = 'none';
    document.getElementById('resultsCard').style.display = 'none';
    
    allRecords = [];
    currentFilters = {};
    currentPage = 1;
    
    showNotification('Filters reset successfully', 'info');
}

// Show Loading Overlay
function showLoading(message = 'Loading...') {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div class="spinner"></div>
        <div class="loading-text">${message}</div>
    `;
    document.body.appendChild(overlay);
}

// Hide Loading Overlay
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
    }
}

// Show Notification
function showNotification(message, type = 'info') {
    const container = document.createElement('div');
    container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.cssText = 'min-width: 300px; animation: slideInRight 0.3s ease; box-shadow: var(--shadow-xl);';
    
    alert.innerHTML = `
        <div class="alert-icon">
            <i class="fas fa-${icons[type]}"></i>
        </div>
        <div class="alert-content">${message}</div>
    `;
    
    container.appendChild(alert);
    document.body.appendChild(container);
    
    setTimeout(() => {
        alert.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => container.remove(), 300);
    }, 4000);
}

// Scroll to top of results
function scrollToTop() {
    document.getElementById('resultsCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

// Add fadeIn animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'includes/footer_modern.php'; ?>
