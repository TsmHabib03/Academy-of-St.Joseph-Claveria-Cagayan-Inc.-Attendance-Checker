/**
 * Admin Students Management System
 * Handles all student CRUD operations, modals, and UI interactions
 * Mobile-optimized with touch gestures
 */

// Global State
const AdminStudents = {
    deleteStudentId: null,
    currentQRData: null,
    modalManager: null
};

/**
 * Modal Management System
 */
class ModalManager {
    constructor() {
        this.activeModals = [];
        this.touchStartY = 0;
        this.init();
    }

    init() {
        // Click outside to close
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                const modalId = e.target.id;
                if (modalId) this.close(modalId);
            }
        });

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModals.length > 0) {
                const lastModal = this.activeModals[this.activeModals.length - 1];
                this.close(lastModal);
            }
        });

        // Initialize swipe gesture for mobile
        this.initSwipeGesture();
    }

    open(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.classList.add('active');
        modal.style.display = 'flex';
        this.activeModals.push(modalId);
        document.body.style.overflow = 'hidden';

        // Add mobile fullscreen class on small screens
        if (window.innerWidth < 480) {
            modal.classList.add('modal-mobile-fullscreen');
        }
    }

    close(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.classList.remove('active');
        modal.classList.remove('modal-mobile-fullscreen');
        modal.style.display = 'none';
        
        this.activeModals = this.activeModals.filter(id => id !== modalId);

        // Restore body scroll if no modals open
        if (this.activeModals.length === 0) {
            document.body.style.overflow = '';
        }
    }

    closeAll() {
        this.activeModals.forEach(modalId => this.close(modalId));
    }

    initSwipeGesture() {
        document.addEventListener('touchstart', (e) => {
            if (e.target.closest('.modal-content')) {
                this.touchStartY = e.touches[0].clientY;
            }
        }, { passive: true });

        document.addEventListener('touchmove', (e) => {
            if (this.touchStartY > 0) {
                const currentY = e.touches[0].clientY;
                const diff = currentY - this.touchStartY;

                // Swiped down more than 100px
                if (diff > 100 && this.activeModals.length > 0) {
                    const lastModal = this.activeModals[this.activeModals.length - 1];
                    this.close(lastModal);
                    this.touchStartY = 0;
                }
            }
        }, { passive: true });
    }
}

/**
 * View Student Details
 */
function viewStudent(student) {
    if (!student) return;

    // Populate modal with student data
    document.getElementById('view-id').textContent = '#' + String(student.id).padStart(5, '0');
    document.getElementById('view-lrn').textContent = student.lrn || 'N/A';
    document.getElementById('view-firstname').textContent = student.first_name || 'N/A';
    document.getElementById('view-middlename').textContent = student.middle_name || 'N/A';
    document.getElementById('view-lastname').textContent = student.last_name || 'N/A';
    
    // Sex badge with icon
    const sex = student.sex || student.gender || 'N/A';
    const sexBadge = `<span class="badge badge-${sex === 'Male' ? 'primary' : 'error'}">
        <i class="fas fa-${sex === 'Male' ? 'mars' : 'venus'}"></i> ${sex}
    </span>`;
    document.getElementById('view-gender').innerHTML = sexBadge;
    
    document.getElementById('view-email').textContent = student.email || 'N/A';
    document.getElementById('view-class').innerHTML = `<span class="badge badge-info">${student.class}</span>`;
    document.getElementById('view-attendance').textContent = (student.attendance_days || '0') + ' days';
    
    // Format dates
    const createdDate = student.created_at ? new Date(student.created_at).toLocaleDateString('en-US', { 
        year: 'numeric', month: 'long', day: 'numeric' 
    }) : 'N/A';
    document.getElementById('view-created').textContent = createdDate;
    
    const lastAttendance = student.last_attendance ? new Date(student.last_attendance).toLocaleDateString('en-US', { 
        year: 'numeric', month: 'long', day: 'numeric' 
    }) : 'Never';
    document.getElementById('view-last-attendance').textContent = lastAttendance;
    
    // Set edit link
    document.getElementById('editStudentLink').href = `manage_students.php?id=${student.id}`;
    
    AdminStudents.modalManager.open('viewModal');
}

function closeViewModal() {
    AdminStudents.modalManager.close('viewModal');
}

/**
 * QR Code Generation and Management
 */
function viewQRCode(lrn, name) {
    if (!lrn || !name) {
        showNotification('Invalid student data', 'error');
        return;
    }

    document.getElementById('qr-student-name').textContent = name;
    document.getElementById('qr-lrn').textContent = lrn;

    const container = document.getElementById('qr-code-display');
    container.innerHTML = '';

    // Generate QR Code using QRCode.js
    QRCode.toCanvas(lrn, {
        width: 256,
        height: 256,
        margin: 2,
        color: {
            dark: '#1e293b',
            light: '#ffffff'
        }
    }, function (error, canvas) {
        if (error) {
            console.error('QR Code Error:', error);
            container.innerHTML = '<p style="color: var(--red-500);">Error generating QR code</p>';
            showNotification('Failed to generate QR code', 'error');
        } else {
            container.appendChild(canvas);
            AdminStudents.currentQRData = { lrn, name, canvas };
        }
    });

    AdminStudents.modalManager.open('qrModal');
}

function closeQRModal() {
    AdminStudents.modalManager.close('qrModal');
    AdminStudents.currentQRData = null;
}

function downloadQR() {
    if (!AdminStudents.currentQRData || !AdminStudents.currentQRData.canvas) {
        showNotification('QR code not generated yet', 'error');
        return;
    }

    const { lrn, name, canvas } = AdminStudents.currentQRData;
    const link = document.createElement('a');
    const fileName = `QR_${lrn}_${name.replace(/\s+/g, '_')}.png`;
    
    link.download = fileName;
    link.href = canvas.toDataURL('image/png');
    link.click();

    showNotification('QR code downloaded successfully!', 'success');
}

function printQR() {
    if (!AdminStudents.currentQRData || !AdminStudents.currentQRData.canvas) {
        showNotification('QR code not generated yet', 'error');
        return;
    }

    const { lrn, name, canvas } = AdminStudents.currentQRData;
    const qrDataURL = canvas.toDataURL('image/png');
    
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    if (!printWindow) {
        showNotification('Popup blocked. Please allow popups for this site.', 'error');
        return;
    }

    const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Code - ${escapeHtml(name)}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 30px;
            background: #f8fafc;
        }
        .print-container {
            text-align: center;
            max-width: 400px;
            background: white;
            border: 3px solid #0ea5e9;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        h1 {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .lrn {
            font-size: 18px;
            color: #64748b;
            margin-bottom: 30px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
        .qr-wrapper {
            background: white;
            padding: 20px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        img {
            display: block;
            width: 256px;
            height: 256px;
        }
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .footer strong {
            color: #475569;
        }
        @media print {
            body { 
                background: white; 
            }
            .print-container { 
                border: 2px solid #e2e8f0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <h1>${escapeHtml(name)}</h1>
        <div class="lrn">LRN: ${escapeHtml(lrn)}</div>
        <div class="qr-wrapper">
            <img src="${qrDataURL}" alt="QR Code for ${escapeHtml(name)}" />
        </div>
        <div class="footer">
            <p><strong>Attendance Management System</strong></p>
            <p>Scan this QR code to mark attendance</p>
        </div>
    </div>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
                // Auto-close after print dialog is dismissed
                window.onafterprint = function() {
                    setTimeout(function() { window.close(); }, 500);
                };
            }, 500);
        };
    </script>
</body>
</html>`;

    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
}

/**
 * Generate QR Code (for manage_students.php)
 */
function generateQR(lrn, name) {
    viewQRCode(lrn, name);
}

/**
 * Delete Student Functions
 */
function deleteStudent(id, name) {
    if (!id || !name) {
        showNotification('Invalid student data', 'error');
        return;
    }

    AdminStudents.deleteStudentId = id;
    document.getElementById('delete-student-name').textContent = name;
    AdminStudents.modalManager.open('deleteModal');
}

function closeDeleteModal() {
    AdminStudents.modalManager.close('deleteModal');
    AdminStudents.deleteStudentId = null;
}

async function confirmDelete() {
    if (!AdminStudents.deleteStudentId) return;

    const btn = document.getElementById('confirmDeleteBtn');
    if (!btn) return;

    btn.classList.add('btn-loading');
    btn.disabled = true;

    try {
        const response = await fetch('../api/delete_student.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                student_id: AdminStudents.deleteStudentId 
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            showNotification('Student deleted successfully', 'success');
            closeDeleteModal();
            
            // Reload page after short delay
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            throw new Error(data.message || 'Failed to delete student');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification(error.message || 'Network error. Please try again.', 'error');
        
        btn.classList.remove('btn-loading');
        btn.disabled = false;
    }
}

/**
 * Export to CSV
 */
function exportToCSV() {
    const table = document.querySelector('.desktop-table');
    
    if (!table) {
        showNotification('No data to export', 'error');
        return;
    }

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let row of rows) {
        let cols = row.querySelectorAll('td, th');
        let csvRow = [];
        
        // Exclude last column (actions)
        for (let i = 0; i < cols.length - 1; i++) {
            let data = cols[i].textContent.trim().replace(/"/g, '""');
            // Remove extra whitespace and newlines
            data = data.replace(/\s+/g, ' ');
            csvRow.push('"' + data + '"');
        }
        
        if (csvRow.length > 0) {
            csv.push(csvRow.join(','));
        }
    }

    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    const fileName = `students_${new Date().toISOString().split('T')[0]}.csv`;

    link.setAttribute('href', url);
    link.setAttribute('download', fileName);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    URL.revokeObjectURL(url);

    showNotification('Student list exported successfully!', 'success');
}

/**
 * Notification System
 */
let notificationContainer = null;

function createNotificationContainer() {
    if (!notificationContainer) {
        notificationContainer = document.createElement('div');
        notificationContainer.className = 'notification-container';
        notificationContainer.id = 'notificationContainer';
        document.body.appendChild(notificationContainer);
    }
    return notificationContainer;
}

function showNotification(message, type = 'info') {
    const container = createNotificationContainer();
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    
    const iconMap = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    
    const icon = iconMap[type] || 'info-circle';
    
    notification.innerHTML = `
        <div class="alert-icon">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="alert-content">${escapeHtml(message)}</div>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notification);
    
    // Auto-dismiss after 4 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 4000);
    
    // Swipe to dismiss on mobile
    let startX = 0;
    notification.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
    }, { passive: true });
    
    notification.addEventListener('touchmove', (e) => {
        const currentX = e.touches[0].clientX;
        const diff = currentX - startX;
        
        if (diff > 100) { // Swiped right
            notification.style.animation = 'slideOutRight 0.2s ease';
            setTimeout(() => notification.remove(), 200);
        }
    }, { passive: true });
}

/**
 * Form Validation
 */
function initFormValidation() {
    const form = document.querySelector('.student-form');
    
    if (!form) return;

    // Form submission validation
    form.addEventListener('submit', function(e) {
        const lrn = document.getElementById('lrn');
        const email = document.getElementById('email');
        
        let isValid = true;

        // Validate LRN
        if (lrn && lrn.value) {
            if (!/^\d{11,13}$/.test(lrn.value)) {
                e.preventDefault();
                showNotification('LRN must be 11-13 digits only.', 'error');
                lrn.focus();
                lrn.style.borderColor = 'var(--red-500)';
                isValid = false;
            }
        }

        // Validate Email
        if (email && email.value && isValid) {
            if (!email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                e.preventDefault();
                showNotification('Please enter a valid email address.', 'error');
                email.focus();
                email.style.borderColor = 'var(--red-500)';
                isValid = false;
            }
        }
    });

    // Auto-format LRN input (digits only)
    const lrnInput = document.getElementById('lrn');
    if (lrnInput) {
        lrnInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        lrnInput.addEventListener('blur', function() {
            if (this.value && !/^\d{11,13}$/.test(this.value)) {
                this.style.borderColor = 'var(--red-500)';
                showNotification('LRN must be 11-13 digits', 'warning');
            } else {
                this.style.borderColor = '';
            }
        });

        lrnInput.addEventListener('focus', function() {
            this.style.borderColor = '';
        });
    }

    // Email validation feedback
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            if (this.value && !this.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                this.style.borderColor = 'var(--red-500)';
                showNotification('Invalid email format', 'warning');
            } else {
                this.style.borderColor = '';
            }
        });

        emailInput.addEventListener('focus', function() {
            this.style.borderColor = '';
        });
    }
}

/**
 * Debounced Search
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function initSearchFilter() {
    const searchInput = document.querySelector('input[name="search"]');
    const classSelect = document.querySelector('select[name="class"]');
    const filterForm = document.getElementById('filterForm');

    // Auto-submit on class change
    if (classSelect && filterForm) {
        classSelect.addEventListener('change', function() {
            filterForm.submit();
        });
    }

    // Debounced search (optional for better UX)
    if (searchInput && filterForm) {
        const debouncedSubmit = debounce(() => {
            filterForm.submit();
        }, 500);

        // Uncomment to enable auto-search on typing
        // searchInput.addEventListener('input', debouncedSubmit);
    }
}

/**
 * Utility Functions
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Manage Students Page Specific Handlers
 */
function initManageStudentsHandlers() {
    // Generate QR button handler
    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action="generate-qr"]');
        if (target) {
            e.preventDefault();
            const lrn = target.dataset.lrn;
            const name = target.dataset.name;
            generateQRForManage(lrn, name);
        }
    });
    
    // Show delete modal handler
    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action="show-delete-modal"]');
        if (target) {
            e.preventDefault();
            showDeleteModalManage();
        }
    });
    
    // Print QR from manage page
    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action="print-qr-manage"]');
        if (target) {
            e.preventDefault();
            printQRManage();
        }
    });
    
    // Close modal handlers (for manage_students.php compatibility)
    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action="close-modal"]');
        if (target && target.dataset.modal) {
            const modal = document.getElementById(target.dataset.modal);
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    });
}

function generateQRForManage(lrn, name) {
    const modal = document.getElementById('qr-modal');
    const nameElement = document.getElementById('qr-student-name');
    const container = document.getElementById('qr-code-container');
    
    if (!modal || !nameElement || !container) return;
    
    nameElement.textContent = name;
    container.innerHTML = '';
    
    QRCode.toCanvas(lrn, {
        width: 250,
        margin: 2,
        color: {
            dark: '#1e293b',
            light: '#ffffff'
        }
    }, (error, canvas) => {
        if (error) {
            console.error(error);
            container.innerHTML = '<p style="color: var(--red-500);">Error generating QR code</p>';
        } else {
            container.appendChild(canvas);
        }
    });
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function showDeleteModalManage() {
    const modal = document.getElementById('delete-modal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function printQRManage() {
    const canvas = document.querySelector('#qr-code-container canvas');
    const name = document.getElementById('qr-student-name').textContent;
    
    if (!canvas) {
        showNotification('QR code not generated yet', 'error');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student QR Code - ${escapeHtml(name)}</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Inter', sans-serif;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 20px;
                    background: white;
                }
                h1 {
                    font-size: 24px;
                    margin-bottom: 20px;
                    color: #1e293b;
                    text-align: center;
                }
                .qr-container {
                    border: 2px solid #e2e8f0;
                    padding: 20px;
                    border-radius: 12px;
                    background: white;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .qr-container img {
                    display: block;
                }
                p {
                    margin-top: 20px;
                    color: #64748b;
                    text-align: center;
                    font-size: 14px;
                }
                @media print {
                    body {
                        background: white;
                    }
                    @page {
                        margin: 0.5in;
                    }
                }
            </style>
        </head>
        <body>
            <h1>${escapeHtml(name)}</h1>
            <div class="qr-container">
                <img src="${canvas.toDataURL()}" alt="QR Code">
            </div>
            <p>Scan this QR code to mark attendance</p>
            <script>
                window.onload = function() {
                    setTimeout(function() { window.print(); }, 250);
                };
            <\/script>
        </body>
        </html>
    `;
    
    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
}

/**
 * Initialize Action Button Handlers
 */
function initActionButtons() {
    // Event delegation for all action buttons
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;

        switch (action) {
            case 'view':
                e.preventDefault();
                const studentData = target.dataset.student;
                if (studentData) {
                    try {
                        const student = JSON.parse(studentData);
                        viewStudent(student);
                    } catch (error) {
                        console.error('Error parsing student data:', error);
                        showNotification('Error loading student details', 'error');
                    }
                }
                break;

            case 'qr':
                e.preventDefault();
                const lrn = target.dataset.lrn;
                const name = target.dataset.name;
                if (lrn && name) {
                    viewQRCode(lrn, name);
                } else {
                    showNotification('Invalid QR code data', 'error');
                }
                break;

            case 'delete':
                e.preventDefault();
                const studentId = target.dataset.id;
                const studentName = target.dataset.name;
                if (studentId && studentName) {
                    deleteStudent(studentId, studentName);
                } else {
                    showNotification('Invalid student data', 'error');
                }
                break;

            case 'close-modal':
                e.preventDefault();
                const modalId = target.dataset.modal;
                if (modalId && AdminStudents.modalManager) {
                    AdminStudents.modalManager.close(modalId);
                }
                break;

            case 'download-qr':
                e.preventDefault();
                downloadQR();
                break;

            case 'print-qr':
                e.preventDefault();
                printQR();
                break;

            case 'confirm-delete':
                e.preventDefault();
                confirmDelete();
                break;

            case 'generate-qr':
                e.preventDefault();
                const qrLrn = target.dataset.lrn;
                const qrName = target.dataset.name;
                generateQRForManage(qrLrn, qrName);
                break;

            case 'show-delete-modal':
                e.preventDefault();
                showDeleteModalManage();
                break;

            case 'print-qr-manage':
                e.preventDefault();
                printQRManage();
                break;
        }
    });

    console.log('✅ Action button handlers initialized');
}

/**
 * Initialize Everything
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal manager
    AdminStudents.modalManager = new ModalManager();
    
    // Initialize action button handlers
    initActionButtons();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize search filters
    initSearchFilter();
    
    // Initialize manage students handlers (for manage_students.php page)
    initManageStudentsHandlers();
    
    // Log successful initialization
    console.log('✅ Admin Students Management System initialized successfully!');
    console.log('📱 Mobile optimizations active');
    console.log('🔄 All event listeners attached');
});

// Export for use in other scripts if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        viewStudent,
        viewQRCode,
        deleteStudent,
        exportToCSV,
        showNotification
    };
}
