<?php
/**
 * SMS Logs - AttendEase v3.0
 * View sent SMS notifications and manage templates
 * 
 * @package AttendEase
 * @version 3.0
 */

require_once 'config.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Require admin role
requireRole([ROLE_ADMIN]);

$currentAdmin = getCurrentAdmin();
$pageTitle = 'SMS Logs & Settings';
$pageIcon = 'sms';

$additionalCSS = ['../css/manual-attendance-modern.css?v=' . time()];

// Get filter parameters
$filterStatus = $_GET['status'] ?? '';
$filterType = $_GET['type'] ?? '';
$filterDate = $_GET['date'] ?? '';

// Handle template updates
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_template') {
        $templateId = intval($_POST['template_id']);
        $templateContent = trim($_POST['template_content'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE sms_templates SET template_content = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$templateContent, $templateId]);
            
            logAdminActivity('UPDATE_SMS_TEMPLATE', "Updated SMS template ID: {$templateId}");
            
            $message = "Template updated successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error updating template: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'add_template') {
        $templateName = trim($_POST['template_name'] ?? '');
        $templateType = trim($_POST['template_type'] ?? '');
        $templateContent = trim($_POST['template_content'] ?? '');
        
        if (empty($templateName) || empty($templateContent)) {
            $message = "Template name and content are required.";
            $messageType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sms_templates (template_name, template_type, template_content, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$templateName, $templateType, $templateContent]);
                
                logAdminActivity('ADD_SMS_TEMPLATE', "Added SMS template: {$templateName}");
                
                $message = "Template added successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error adding template: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif ($action === 'delete_template') {
        $templateId = intval($_POST['template_id']);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM sms_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            
            logAdminActivity('DELETE_SMS_TEMPLATE', "Deleted SMS template ID: {$templateId}");
            
            $message = "Template deleted successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error deleting template: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'test_sms') {
        $testNumber = trim($_POST['test_number'] ?? '');
        
        if (empty($testNumber)) {
            $message = "Phone number is required for test.";
            $messageType = "error";
        } else {
            // In real implementation, this would send a test SMS
            $message = "Test SMS would be sent to: {$testNumber} (SMS gateway not configured)";
            $messageType = "info";
        }
    }
}

// Build logs query
$logsQuery = "
    SELECT sl.*,
           CASE 
               WHEN sl.recipient_type = 'student' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE id = sl.recipient_id)
               WHEN sl.recipient_type = 'teacher' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM teachers WHERE id = sl.recipient_id)
               ELSE 'Unknown'
           END as recipient_name
    FROM sms_logs sl
    WHERE 1=1
";
$params = [];

if ($filterStatus) {
    $logsQuery .= " AND sl.status = ?";
    $params[] = $filterStatus;
}

if ($filterType) {
    $logsQuery .= " AND sl.message_type = ?";
    $params[] = $filterType;
}

if ($filterDate) {
    $logsQuery .= " AND DATE(sl.sent_at) = ?";
    $params[] = $filterDate;
}

$logsQuery .= " ORDER BY sl.sent_at DESC LIMIT 100";

try {
    $logsStmt = $pdo->prepare($logsQuery);
    $logsStmt->execute($params);
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logs = [];
    // Table might not exist yet
}

// Fetch SMS templates
try {
    $templatesStmt = $pdo->query("SELECT * FROM sms_templates ORDER BY template_name");
    $templates = $templatesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $templates = [];
}

// Get SMS statistics
try {
    $statsQuery = "
        SELECT 
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN DATE(sent_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM sms_logs
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total_sent' => 0, 'successful' => 0, 'failed' => 0, 'today' => 0];
}

// Load SMS config
$smsConfig = [];
if (file_exists(__DIR__ . '/../config/sms_config.php')) {
    $smsConfig = include __DIR__ . '/../config/sms_config.php';
}

include 'includes/header_modern.php';
?>

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
                    <span>View SMS logs and manage notification templates</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <button class="btn-header btn-header-secondary" onclick="showTestSMSModal()">
                <i class="fas fa-paper-plane"></i>
                <span>Test SMS</span>
            </button>
            <button class="btn-header btn-header-primary" onclick="showAddTemplateModal()">
                <i class="fas fa-plus"></i>
                <span>Add Template</span>
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <div class="alert-icon">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
        </div>
        <div class="alert-content">
            <?php echo $message; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Info Alert -->
<div class="alert alert-info">
    <div class="alert-icon">
        <i class="fas fa-info-circle"></i>
    </div>
    <div class="alert-content">
        <strong>SMS Notification System</strong>
        <p style="margin: var(--space-1) 0 0; line-height: 1.6;">
            Manage SMS templates for attendance notifications. Configure templates for different scenarios like check-in confirmations, absence alerts, and late notifications.
        </p>
    </div>
</div>

<div class="content-wrapper">
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-paper-plane"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $stats['total_sent'] ?? 0; ?></span>
                <span class="stat-label">Total Sent (30 days)</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $stats['successful'] ?? 0; ?></span>
                <span class="stat-label">Successful</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $stats['failed'] ?? 0; ?></span>
                <span class="stat-label">Failed</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $stats['today'] ?? 0; ?></span>
                <span class="stat-label">Sent Today</span>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('logs')">
            <i class="fas fa-list"></i> SMS Logs
        </button>
        <button class="tab-btn" onclick="showTab('templates')">
            <i class="fas fa-file-alt"></i> Templates
        </button>
        <button class="tab-btn" onclick="showTab('settings')">
            <i class="fas fa-cog"></i> Settings
        </button>
    </div>

    <!-- SMS Logs Tab -->
    <div id="logs-tab" class="tab-content active">
        <div class="table-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent SMS Logs</h3>
                <div class="filter-controls">
                    <select id="filterStatus" onchange="applyFilters()">
                        <option value="">All Status</option>
                        <option value="sent" <?php echo $filterStatus === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                    <select id="filterType" onchange="applyFilters()">
                        <option value="">All Types</option>
                        <option value="late" <?php echo $filterType === 'late' ? 'selected' : ''; ?>>Late Alert</option>
                        <option value="absent" <?php echo $filterType === 'absent' ? 'selected' : ''; ?>>Absent Alert</option>
                        <option value="time_in" <?php echo $filterType === 'time_in' ? 'selected' : ''; ?>>Time In</option>
                        <option value="time_out" <?php echo $filterType === 'time_out' ? 'selected' : ''; ?>>Time Out</option>
                    </select>
                    <input type="date" id="filterDate" value="<?php echo $filterDate; ?>" onchange="applyFilters()">
                </div>
            </div>
            
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-sms"></i>
                    <h3>No SMS Logs</h3>
                    <p>No SMS messages have been sent yet, or the SMS feature is not configured.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Recipient</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('M d, Y g:i A', strtotime($log['sent_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['recipient_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo getTypeBadgeClass($log['message_type']); ?>">
                                            <?php echo ucfirst($log['message_type'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="message-cell" title="<?php echo htmlspecialchars($log['message_content']); ?>">
                                        <?php echo htmlspecialchars(substr($log['message_content'], 0, 50) . (strlen($log['message_content']) > 50 ? '...' : '')); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $log['status']; ?>">
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Templates Tab -->
    <div id="templates-tab" class="tab-content">
        <div class="templates-grid">
            <?php if (empty($templates)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Templates</h3>
                    <p>Create SMS templates for different notification types.</p>
                </div>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <div class="template-card">
                        <div class="template-header">
                            <h4><?php echo htmlspecialchars($template['template_name']); ?></h4>
                            <span class="badge badge-info"><?php echo ucfirst($template['template_type'] ?? 'general'); ?></span>
                        </div>
                        <div class="template-content">
                            <pre><?php echo htmlspecialchars($template['template_content'] ?? ''); ?></pre>
                        </div>
                        <div class="template-footer">
                            <small>Last updated: <?php echo date('M d, Y', strtotime($template['updated_at'] ?? $template['created_at'])); ?></small>
                            <div class="template-actions">
                                <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="editTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" 
                                      onsubmit="return confirm('Delete this template?');">
                                    <input type="hidden" name="action" value="delete_template">
                                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="template-variables">
            <h4><i class="fas fa-code"></i> Available Variables</h4>
            <div class="variables-list">
                <span class="variable">{student_name}</span>
                <span class="variable">{date}</span>
                <span class="variable">{time}</span>
                <span class="variable">{section}</span>
                <span class="variable">{grade_level}</span>
                <span class="variable">{school_name}</span>
            </div>
        </div>
    </div>

    <!-- Settings Tab -->
    <div id="settings-tab" class="tab-content">
        <div class="settings-card">
            <h3><i class="fas fa-cog"></i> SMS Gateway Configuration</h3>
            
            <div class="config-status">
                <?php if (isset($smsConfig['enabled']) && $smsConfig['enabled']): ?>
                    <div class="status-indicator active">
                        <i class="fas fa-check-circle"></i>
                        <span>SMS Gateway Active</span>
                    </div>
                    <p>Provider: <?php echo ucfirst($smsConfig['gateway'] ?? 'Not set'); ?></p>
                <?php else: ?>
                    <div class="status-indicator inactive">
                        <i class="fas fa-times-circle"></i>
                        <span>SMS Gateway Not Configured</span>
                    </div>
                    <p>Edit <code>config/sms_config.php</code> to configure SMS settings.</p>
                <?php endif; ?>
            </div>
            
            <div class="config-info">
                <h4>Supported Gateways</h4>
                <ul>
                    <li><strong>Semaphore</strong> - Philippine SMS gateway (recommended for PH)</li>
                    <li><strong>Twilio</strong> - International SMS gateway</li>
                    <li><strong>Vonage (Nexmo)</strong> - International SMS gateway</li>
                    <li><strong>Custom API</strong> - Use any HTTP-based SMS API</li>
                </ul>
            </div>
            
            <div class="config-features">
                <h4>Features</h4>
                <ul>
                    <li><i class="fas fa-check text-success"></i> Late arrival notifications</li>
                    <li><i class="fas fa-check text-success"></i> Absence alerts</li>
                    <li><i class="fas fa-check text-success"></i> Time-in/out confirmations</li>
                    <li><i class="fas fa-check text-success"></i> Custom templates with variables</li>
                    <li><i class="fas fa-check text-success"></i> Rate limiting to prevent spam</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Add Template Modal -->
<div id="templateModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="closeTemplateModal()">&times;</span>
        <h2 id="templateModalTitle"><i class="fas fa-file-alt"></i> Add Template</h2>
        
        <form method="POST" id="templateForm">
            <input type="hidden" name="action" id="templateAction" value="add_template">
            <input type="hidden" name="template_id" id="templateId">
            
            <div class="form-group">
                <label for="templateName">Template Name</label>
                <input type="text" id="templateName" name="template_name" required 
                       placeholder="e.g., Late Notification">
            </div>
            
            <div class="form-group">
                <label for="templateType">Template Type</label>
                <select id="templateType" name="template_type">
                    <option value="late">Late Alert</option>
                    <option value="absent">Absent Alert</option>
                    <option value="time_in">Time In</option>
                    <option value="time_out">Time Out</option>
                    <option value="general">General</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="templateContent">Template Content</label>
                <textarea id="templateContent" name="template_content" rows="4" required
                          placeholder="Enter your message template..."></textarea>
                <small>Use variables like {student_name}, {date}, {time}, etc.</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Template
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeTemplateModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Test SMS Modal -->
<div id="testSMSModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="closeTestSMSModal()">&times;</span>
        <h2><i class="fas fa-paper-plane"></i> Send Test SMS</h2>
        
        <form method="POST">
            <input type="hidden" name="action" value="test_sms">
            
            <div class="form-group">
                <label for="testNumber">Phone Number</label>
                <input type="tel" id="testNumber" name="test_number" required 
                       placeholder="09XX-XXX-XXXX">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Test
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeTestSMSModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<?php
function getTypeBadgeClass($type) {
    $classes = [
        'late' => 'warning',
        'absent' => 'danger',
        'time_in' => 'success',
        'time_out' => 'info'
    ];
    return $classes[$type] ?? 'secondary';
}
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
}

.stat-icon.blue { background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%); }
.stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-icon.red { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); }
.stat-icon.purple { background: linear-gradient(135deg, var(--asj-green-400) 0%, var(--asj-green-600) 100%); }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #333;
}

.stat-label {
    font-size: 0.875rem;
    color: #666;
}

.tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    background: #fff;
    padding: 0.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
}

.tab-btn {
    flex: 1;
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9375rem;
    font-weight: 500;
    color: #666;
    transition: all 0.2s ease;
}

.tab-btn:hover {
    background: #f3f4f6;
}

.tab-btn.active {
    background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%);
    color: #fff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.table-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.card-header h3 {
    margin: 0;
    font-size: 1.125rem;
    color: #333;
}

.filter-controls {
    display: flex;
    gap: 0.5rem;
}

.filter-controls select,
.filter-controls input {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.875rem;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #555;
}

.message-cell {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.badge {
    padding: 0.25rem 0.625rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-warning { background: #fff8e6; color: #d97706; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.badge-success { background: #dcfce7; color: #16a34a; }
.badge-info { background: #e0f2fe; color: #0284c7; }
.badge-secondary { background: #f3f4f6; color: #6b7280; }

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-sent { background: #dcfce7; color: #16a34a; }
.status-failed { background: #fee2e2; color: #dc2626; }
.status-pending { background: #fff8e6; color: #d97706; }

.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.template-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.template-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.template-header h4 {
    margin: 0;
    font-size: 1rem;
}

.template-content {
    padding: 1.25rem;
    background: #f8f9fa;
}

.template-content pre {
    margin: 0;
    white-space: pre-wrap;
    font-family: inherit;
    font-size: 0.875rem;
    color: #555;
}

.template-footer {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.template-footer small {
    color: #888;
}

.template-actions {
    display: flex;
    gap: 0.5rem;
}

.template-variables {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.template-variables h4 {
    margin: 0 0 1rem;
    color: #333;
}

.variables-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.variable {
    background: #e7f0ff;
    color: #3b82f6;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.875rem;
}

.settings-card {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: var(--shadow-sm);
}

.settings-card h3 {
    margin: 0 0 1.5rem;
    color: #333;
}

.config-status {
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.status-indicator.active {
    color: #16a34a;
}

.status-indicator.inactive {
    color: #dc2626;
}

.config-info,
.config-features {
    margin-top: 1.5rem;
}

.config-info h4,
.config-features h4 {
    margin: 0 0 0.75rem;
    color: #333;
}

.config-info ul,
.config-features ul {
    margin: 0;
    padding-left: 1.5rem;
    color: #555;
}

.config-features ul {
    list-style: none;
    padding-left: 0;
}

.config-features li {
    padding: 0.375rem 0;
}

.config-features li i {
    margin-right: 0.5rem;
}

.text-success { color: #16a34a; }

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #666;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #999;
}

/* Modal Styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fff;
    padding: 2rem;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    position: relative;
}

.close {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.close:hover {
    color: #333;
}

.modal-content h2 {
    margin-top: 0;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9375rem;
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: #888;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .tabs {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        flex: 1 0 45%;
    }
    
    .filter-controls {
        width: 100%;
        flex-wrap: wrap;
    }
    
    .templates-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId + '-tab').classList.add('active');
    event.target.classList.add('active');
}

function applyFilters() {
    const status = document.getElementById('filterStatus').value;
    const type = document.getElementById('filterType').value;
    const date = document.getElementById('filterDate').value;
    
    let url = 'sms_logs.php?';
    if (status) url += 'status=' + status + '&';
    if (type) url += 'type=' + type + '&';
    if (date) url += 'date=' + date + '&';
    
    window.location.href = url.slice(0, -1);
}

function showAddTemplateModal() {
    document.getElementById('templateModalTitle').innerHTML = '<i class="fas fa-file-alt"></i> Add Template';
    document.getElementById('templateAction').value = 'add_template';
    document.getElementById('templateId').value = '';
    document.getElementById('templateName').value = '';
    document.getElementById('templateType').value = 'late';
    document.getElementById('templateContent').value = '';
    document.getElementById('templateModal').style.display = 'flex';
}

function editTemplate(template) {
    document.getElementById('templateModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Template';
    document.getElementById('templateAction').value = 'update_template';
    document.getElementById('templateId').value = template.id;
    document.getElementById('templateName').value = template.template_name;
    document.getElementById('templateType').value = template.template_type || 'general';
    document.getElementById('templateContent').value = template.template_content || '';
    document.getElementById('templateModal').style.display = 'flex';
}

function closeTemplateModal() {
    document.getElementById('templateModal').style.display = 'none';
}

function showTestSMSModal() {
    document.getElementById('testSMSModal').style.display = 'flex';
}

function closeTestSMSModal() {
    document.getElementById('testSMSModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer_modern.php'; ?>
