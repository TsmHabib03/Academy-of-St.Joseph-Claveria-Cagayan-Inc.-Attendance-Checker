<?php
/**
 * Manage Badges - AttendEase v3.0
 * Configure and manage achievement badges
 * 
 * @package AttendEase
 * @version 3.0
 */

require_once 'config.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/badge_evaluator.php';

// Require admin role for badge management
requireRole([ROLE_ADMIN]);

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Manage Badges';
$pageIcon = 'award';

$additionalCSS = ['../css/manual-attendance-modern.css?v=' . time()];

// Initialize badge evaluator
$badgeEvaluator = new BadgeEvaluator($pdo);

// Schema helpers
$hasBadgesTable = tableExists($pdo, 'badges');
$hasUserBadgesTable = tableExists($pdo, 'user_badges');
$hasBadgeApplicableRoles = $hasBadgesTable && columnExists($pdo, 'badges', 'applicable_roles');
$hasBadgeActive = $hasBadgesTable && columnExists($pdo, 'badges', 'is_active');
$hasUserBadgesDateEarned = $hasUserBadgesTable && columnExists($pdo, 'user_badges', 'date_earned');
$hasUserBadgesPeriodStart = $hasUserBadgesTable && columnExists($pdo, 'user_badges', 'period_start');
$hasUserBadgesPeriodEnd = $hasUserBadgesTable && columnExists($pdo, 'user_badges', 'period_end');
$hasUserBadgesAwardedAt = $hasUserBadgesTable && columnExists($pdo, 'user_badges', 'awarded_at');
$hasUserBadgesCreatedAt = $hasUserBadgesTable && columnExists($pdo, 'user_badges', 'created_at');

$studentIdColumn = null;
if (tableExists($pdo, 'students')) {
    if (columnExists($pdo, 'students', 'lrn')) {
        $studentIdColumn = 'lrn';
    } elseif (columnExists($pdo, 'students', 'student_id')) {
        $studentIdColumn = 'student_id';
    } elseif (columnExists($pdo, 'students', 'id')) {
        $studentIdColumn = 'id';
    }
}

function badge_period_dates(string $period): array {
    $today = new DateTime();
    switch ($period) {
        case 'daily':
            return [$today->format('Y-m-d'), $today->format('Y-m-d')];
        case 'weekly':
            $start = clone $today;
            $start->modify('monday this week');
            return [$start->format('Y-m-d'), $today->format('Y-m-d')];
        case 'monthly':
            $start = clone $today;
            $start->modify('first day of this month');
            return [$start->format('Y-m-d'), $today->format('Y-m-d')];
        case 'yearly':
            $start = clone $today;
            $start->modify('first day of January this year');
            return [$start->format('Y-m-d'), $today->format('Y-m-d')];
        default:
            return [$today->format('Y-m-d'), $today->format('Y-m-d')];
    }
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_badge') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'award');
        $color = trim($_POST['color'] ?? '#4CAF50');
        $badgeType = trim($_POST['badge_type'] ?? 'perfect_attendance');
        $criteriaValue = intval($_POST['criteria_value'] ?? 0);
        $criteriaPeriod = trim($_POST['criteria_period'] ?? 'monthly');
        $points = intval($_POST['points'] ?? 10);
        
        if (empty($name)) {
            $message = "Badge name is required.";
            $messageType = "error";
        } else {
            try {
                $columns = [
                    'badge_name',
                    'badge_description',
                    'badge_icon',
                    'badge_color',
                    'criteria_type',
                    'criteria_value',
                    'criteria_period',
                    'points'
                ];
                $placeholders = array_fill(0, count($columns), '?');
                $params = [$name, $description, $icon, $color, $badgeType, $criteriaValue, $criteriaPeriod, $points];

                if ($hasBadgeApplicableRoles) {
                    $columns[] = 'applicable_roles';
                    $placeholders[] = '?';
                    $params[] = 'student,teacher';
                }
                if ($hasBadgeActive) {
                    $columns[] = 'is_active';
                    $placeholders[] = '?';
                    $params[] = 1;
                }
                if (columnExists($pdo, 'badges', 'created_at')) {
                    $columns[] = 'created_at';
                    $placeholders[] = 'NOW()';
                }

                $sql = "INSERT INTO badges (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                logAdminActivity('ADD_BADGE', "Added badge: {$name}");
                
                $message = "Badge added successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error adding badge: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif ($action === 'edit_badge') {
        $badgeId = intval($_POST['badge_id']);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'award');
        $color = trim($_POST['color'] ?? '#4CAF50');
        $badgeType = trim($_POST['badge_type'] ?? 'perfect_attendance');
        $criteriaValue = intval($_POST['criteria_value'] ?? 0);
        $criteriaPeriod = trim($_POST['criteria_period'] ?? 'monthly');
        $points = intval($_POST['points'] ?? 10);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $setParts = [
                'badge_name = ?',
                'badge_description = ?',
                'badge_icon = ?',
                'badge_color = ?',
                'criteria_type = ?',
                'criteria_value = ?',
                'criteria_period = ?',
                'points = ?'
            ];
            $params = [$name, $description, $icon, $color, $badgeType, $criteriaValue, $criteriaPeriod, $points];

            if ($hasBadgeApplicableRoles) {
                $setParts[] = 'applicable_roles = ?';
                $params[] = 'student,teacher';
            }
            if ($hasBadgeActive) {
                $setParts[] = 'is_active = ?';
                $params[] = $isActive;
            }

            $params[] = $badgeId;
            $sql = "UPDATE badges SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            logAdminActivity('EDIT_BADGE', "Updated badge: {$name}");
            
            $message = "Badge updated successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error updating badge: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'delete_badge') {
        $badgeId = intval($_POST['badge_id']);
        
        try {
            // Get badge name for logging
            $stmt = $pdo->prepare("SELECT badge_name FROM badges WHERE id = ?");
            $stmt->execute([$badgeId]);
            $badge = $stmt->fetch();
            
            // Delete user_badges first
            $pdo->prepare("DELETE FROM user_badges WHERE badge_id = ?")->execute([$badgeId]);
            
            // Delete badge
            $pdo->prepare("DELETE FROM badges WHERE id = ?")->execute([$badgeId]);
            
            logAdminActivity('DELETE_BADGE', "Deleted badge: " . ($badge['badge_name'] ?? 'Unknown'));
            
            $message = "Badge deleted successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error deleting badge: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'award_badge') {
        $badgeId = intval($_POST['badge_id']);
        $userId = trim($_POST['user_id'] ?? '');
        $userType = $_POST['user_type'] ?? 'student';
        
        try {
            if (!$hasUserBadgesTable) {
                throw new Exception('User badges table is missing. Please refresh the page and try again.');
            }
            if ($userId === '') {
                throw new Exception('Please select a user to award the badge.');
            }

            $criteriaPeriod = 'monthly';
            if ($hasBadgesTable && columnExists($pdo, 'badges', 'criteria_period')) {
                $pstmt = $pdo->prepare("SELECT criteria_period FROM badges WHERE id = ? LIMIT 1");
                $pstmt->execute([$badgeId]);
                $criteriaPeriod = $pstmt->fetchColumn() ?: 'monthly';
            }
            [$periodStart, $periodEnd] = badge_period_dates($criteriaPeriod);

            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            $columns = ['user_id', 'user_type', 'badge_id'];
            $placeholders = ['?', '?', '?'];
            $params = [$userId, $userType, $badgeId];

            if ($hasUserBadgesDateEarned) {
                $columns[] = 'date_earned';
                $placeholders[] = '?';
                $params[] = $today;
            }
            if ($hasUserBadgesPeriodStart) {
                $columns[] = 'period_start';
                $placeholders[] = '?';
                $params[] = $periodStart;
            }
            if ($hasUserBadgesPeriodEnd) {
                $columns[] = 'period_end';
                $placeholders[] = '?';
                $params[] = $periodEnd;
            }
            if ($hasUserBadgesAwardedAt) {
                $columns[] = 'awarded_at';
                $placeholders[] = '?';
                $params[] = $now;
            }
            if ($hasUserBadgesCreatedAt) {
                $columns[] = 'created_at';
                $placeholders[] = '?';
                $params[] = $now;
            }

            $sql = "INSERT INTO user_badges (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $updates = [];
            if ($hasUserBadgesDateEarned) {
                $updates[] = "date_earned = VALUES(date_earned)";
            }
            if ($hasUserBadgesPeriodStart) {
                $updates[] = "period_start = VALUES(period_start)";
            }
            if ($hasUserBadgesPeriodEnd) {
                $updates[] = "period_end = VALUES(period_end)";
            }
            if ($hasUserBadgesAwardedAt) {
                $updates[] = "awarded_at = VALUES(awarded_at)";
            }
            if ($hasUserBadgesCreatedAt) {
                $updates[] = "created_at = VALUES(created_at)";
            }
            if (!empty($updates)) {
                $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            logAdminActivity('AWARD_BADGE', "Awarded badge ID {$badgeId} to {$userType} ID {$userId}");
            
            $message = "Badge awarded successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error awarding badge: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'evaluate_badges') {
        try {
            $result = $badgeEvaluator->evaluateAll();
            $evaluated = $result['evaluated'] ?? 0;
            $awarded = $result['awarded'] ?? 0;
            $message = "Evaluated badges for {$evaluated} users. {$awarded} new badges awarded.";
            $messageType = "success";
            
            logAdminActivity('EVALUATE_BADGES', $message);
        } catch (Exception $e) {
            $message = "Error evaluating badges: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Fetch all badges
try {
    $badgeIsActiveField = $hasBadgeActive ? 'b.is_active' : '1';
    $badgeCreatedAtField = columnExists($pdo, 'badges', 'created_at') ? 'b.created_at' : 'b.id';
    $badgesSql = "
        SELECT b.id, b.badge_name as name, b.badge_description as description, 
               b.badge_icon as icon, b.badge_color as color, b.criteria_type as badge_type,
               b.criteria_value, b.criteria_period, b.points, {$badgeIsActiveField} as is_active, {$badgeCreatedAtField} as created_at,
               (SELECT COUNT(*) FROM user_badges ub WHERE ub.badge_id = b.id) as times_awarded
        FROM badges b
        ORDER BY {$badgeCreatedAtField} DESC
    ";
    $badgesStmt = $pdo->query($badgesSql);
    $badges = $badgesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $badges = [];
}

// Fetch leaderboard (students)
$leaderboard = $badgeEvaluator->getLeaderboard('student', 10);

// Fetch students for manual badge awarding
try {
    if ($studentIdColumn) {
        $studentsSql = "
            SELECT {$studentIdColumn} as identifier, first_name, last_name 
            FROM students 
            ORDER BY last_name, first_name
            LIMIT 200
        ";
        $studentsStmt = $pdo->query($studentsSql);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $students = [];
    }
} catch (Exception $e) {
    $students = [];
}

// Available icons for badges
$availableIcons = [
    'award', 'medal', 'trophy', 'star', 'crown', 'gem', 'certificate',
    'ribbon', 'shield-alt', 'check-circle', 'thumbs-up', 'heart',
    'clock', 'calendar-check', 'user-graduate', 'fire', 'bolt', 'sun'
];

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
                    <span>Configure achievement badges and view leaderboard</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <button class="btn-header btn-header-secondary" onclick="showEvaluateModal()">
                <i class="fas fa-sync"></i>
                <span>Evaluate All</span>
            </button>
            <button class="btn-header btn-header-primary" onclick="showAddBadgeModal()">
                <i class="fas fa-plus"></i>
                <span>Add Badge</span>
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <div class="alert-icon">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
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
        <strong>Achievement Badge System</strong>
        <p style="margin: var(--space-1) 0 0; line-height: 1.6;">
            Create and manage badges to reward students for perfect attendance, consistency, and other achievements.
            Use "Evaluate All" to automatically award badges based on criteria.
        </p>
    </div>
</div>

<div class="content-wrapper">
    <div class="badges-layout">
        <!-- Badges Grid -->
        <div class="badges-section">
            <div class="section-card">
                <div class="card-header">
                    <h3><i class="fas fa-award"></i> All Badges</h3>
                    <span class="badge badge-primary"><?php echo count($badges); ?> badges</span>
                </div>
                
                <?php if (empty($badges)): ?>
                    <div class="empty-state">
                        <i class="fas fa-award"></i>
                        <h3>No Badges Yet</h3>
                        <p>Create your first badge to start rewarding students!</p>
                    </div>
                <?php else: ?>
                    <div class="badges-grid">
                        <?php foreach ($badges as $badge): ?>
                            <div class="badge-card <?php echo $badge['is_active'] ? '' : 'inactive'; ?>">
                                <div class="badge-icon">
                                    <i class="fas fa-<?php echo htmlspecialchars($badge['icon']); ?>"></i>
                                </div>
                                <div class="badge-info">
                                    <h4><?php echo htmlspecialchars($badge['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($badge['description'] ?: 'No description'); ?></p>
                                    <div class="badge-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-tag"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $badge['badge_type'])); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-users"></i>
                                            <?php echo $badge['times_awarded']; ?> awarded
                                        </span>
                                    </div>
                                </div>
                                <div class="badge-actions">
                                    <button type="button" class="btn btn-sm btn-icon" 
                                            onclick="editBadge(<?php echo htmlspecialchars(json_encode($badge)); ?>)"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-icon btn-success" 
                                            onclick="showAwardModal(<?php echo $badge['id']; ?>, '<?php echo addslashes($badge['name']); ?>')"
                                            title="Award">
                                        <i class="fas fa-gift"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" 
                                          onsubmit="return confirm('Delete this badge? This will also remove it from all users.');">
                                        <input type="hidden" name="action" value="delete_badge">
                                        <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Leaderboard -->
        <div class="leaderboard-section">
            <div class="section-card">
                <div class="card-header">
                    <h3><i class="fas fa-trophy"></i> Leaderboard</h3>
                </div>
                
                <?php if (empty($leaderboard)): ?>
                    <div class="empty-state small">
                        <i class="fas fa-trophy"></i>
                        <p>No badges awarded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="leaderboard-list">
                        <?php foreach ($leaderboard as $index => $entry): ?>
                            <div class="leaderboard-item">
                                <div class="rank rank-<?php echo $index + 1; ?>">
                                    <?php if ($index < 3): ?>
                                        <i class="fas fa-medal"></i>
                                    <?php else: ?>
                                        <?php echo $index + 1; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="leader-info">
                                    <h5><?php echo htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']); ?></h5>
                                    <span class="leader-id"><?php echo htmlspecialchars($entry['identifier'] ?? ''); ?></span>
                                </div>
                                <div class="leader-stats">
                                    <span class="points"><?php echo $entry['total_points']; ?> pts</span>
                                    <span class="badge-count"><?php echo $entry['badge_count']; ?> badges</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Badge Modal -->
<div id="badgeModal" class="modal" style="display:none;">
    <div class="modal-content modal-lg">
        <span class="close" onclick="closeBadgeModal()">&times;</span>
        <h2 id="badgeModalTitle"><i class="fas fa-award"></i> Add Badge</h2>
        <form method="POST" id="badgeForm">
            <input type="hidden" name="action" id="badgeAction" value="add_badge">
            <input type="hidden" name="badge_id" id="badgeId">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="badgeName">Badge Name <span class="required">*</span></label>
                    <input type="text" id="badgeName" name="name" required placeholder="e.g., Perfect Attendance">
                </div>
                
                <div class="form-group">
                    <label for="badgeType">Badge Type</label>
                    <select id="badgeType" name="badge_type">
                        <option value="perfect_attendance">Perfect Attendance</option>
                        <option value="on_time_streak">On-Time Streak</option>
                        <option value="most_improved">Most Improved</option>
                        <option value="monthly_perfect">Monthly Perfect</option>
                        <option value="early_bird">Early Bird</option>
                        <option value="consistent">Consistent Attendance</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="badgeDescription">Description</label>
                    <textarea id="badgeDescription" name="description" rows="2" 
                              placeholder="Describe what this badge represents..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="badgeIcon">Icon</label>
                    <div class="icon-selector">
                        <?php foreach ($availableIcons as $icon): ?>
                            <label class="icon-option">
                                <input type="radio" name="icon" value="<?php echo $icon; ?>" 
                                       <?php echo $icon === 'award' ? 'checked' : ''; ?>>
                                <span class="icon-preview"><i class="fas fa-<?php echo $icon; ?>"></i></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="badgeColor">Color</label>
                    <input type="color" id="badgeColor" name="color" value="#4CAF50">
                </div>
                
                <div class="form-group">
                    <label for="criteriaValue">Criteria Value (e.g., streak days)</label>
                    <input type="number" id="criteriaValue" name="criteria_value" value="0" min="0">
                </div>
                
                <div class="form-group">
                    <label for="criteriaPeriod">Criteria Period</label>
                    <select id="criteriaPeriod" name="criteria_period">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly" selected>Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="badgePoints">Points Awarded</label>
                    <input type="number" id="badgePoints" name="points" value="10" min="0">
                </div>
                
                <div class="form-group" id="activeGroup" style="display:none;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" id="badgeActive" checked>
                        Badge is Active
                    </label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Badge
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBadgeModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Award Badge Modal -->
<div id="awardModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="closeAwardModal()">&times;</span>
        <h2><i class="fas fa-gift"></i> Award Badge</h2>
        <p id="awardBadgeName"></p>
        
        <form method="POST">
            <input type="hidden" name="action" value="award_badge">
            <input type="hidden" name="badge_id" id="awardBadgeId">
            
            <div class="form-group">
                <label for="awardUserType">User Type</label>
                <select id="awardUserType" name="user_type" onchange="updateUserList()">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="awardUserId">Select User</label>
                <select id="awardUserId" name="user_id" required>
                    <option value="">Select a user...</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo htmlspecialchars($student['identifier']); ?>">
                            <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['identifier'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-gift"></i> Award Badge
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeAwardModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Evaluate Modal -->
<div id="evaluateModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="closeEvaluateModal()">&times;</span>
        <h2><i class="fas fa-sync"></i> Evaluate Badges</h2>
        <p>This will check all students against badge criteria and award any earned badges automatically.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="evaluate_badges">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-play"></i> Start Evaluation
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEvaluateModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.badges-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .badges-layout {
        grid-template-columns: 1fr;
    }
}

.section-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    overflow: hidden;
    border: 1px solid var(--gray-200, #e5e7eb);
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--gray-200, #e5e7eb);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, var(--asj-green-50), var(--gray-50, #f9fafb));
}

.card-header h3 {
    margin: 0;
    font-size: 1.125rem;
    color: var(--asj-green-700);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-header h3 i {
    color: var(--asj-green-500);
}

.badge-primary {
    background: var(--asj-green-100);
    color: var(--asj-green-700);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8125rem;
    font-weight: 600;
}

.badges-grid {
    padding: 1.5rem;
    display: grid;
    gap: 1rem;
}

.badge-card {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem;
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.badge-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    border-color: var(--asj-green-300, #a5d6a7);
}

.badge-card.inactive {
    opacity: 0.6;
    background: var(--gray-50);
}

.badge-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    /* Use system green theme instead of dynamic colors */
    background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-600) 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.25);
}

.badge-info {
    flex: 1;
    min-width: 0;
}

.badge-info h4 {
    margin: 0 0 0.375rem;
    font-size: 1rem;
    color: var(--gray-800, #1f2937);
    font-weight: 600;
}

.badge-info p {
    margin: 0 0 0.625rem;
    font-size: 0.8125rem;
    color: var(--gray-500, #6b7280);
    line-height: 1.5;
}

.badge-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: var(--gray-500, #6b7280);
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.meta-item i {
    color: var(--asj-green-500);
}

.badge-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--gray-100, #f3f4f6);
    border: 1px solid var(--gray-200, #e5e7eb);
    color: var(--gray-600, #4b5563);
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background: var(--asj-green-500);
    border-color: var(--asj-green-500);
    color: #fff;
}

.btn-icon.btn-success {
    background: var(--success-50, #f0fdf4);
    border-color: var(--success-500, #22c55e);
    color: var(--success-600, #16a34a);
}

.btn-icon.btn-success:hover {
    background: var(--success-500, #22c55e);
    color: #fff;
}

.btn-icon.btn-danger {
    background: var(--danger-50, #fef2f2);
    border-color: var(--danger-500, #ef4444);
    color: var(--danger-600, #dc2626);
}

.btn-icon.btn-danger:hover {
    background: var(--danger-500, #ef4444);
    color: #fff;
}

.leaderboard-list {
    padding: 1.25rem;
}

.leaderboard-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
    transition: background 0.2s ease;
}

.leaderboard-item:hover {
    background: var(--asj-green-50);
}

.leaderboard-item:last-child {
    border-bottom: none;
}

.rank {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    background: var(--gray-100, #f3f4f6);
    color: var(--gray-600, #4b5563);
    flex-shrink: 0;
}

.rank-1 { 
    background: linear-gradient(135deg, #ffd700 0%, #ffb700 100%); 
    color: #fff; 
    box-shadow: 0 2px 8px rgba(255, 215, 0, 0.4);
}
.rank-2 { 
    background: linear-gradient(135deg, #c0c0c0 0%, #a0a0a0 100%); 
    color: #fff;
    box-shadow: 0 2px 8px rgba(192, 192, 192, 0.4);
}
.rank-3 { 
    background: linear-gradient(135deg, #cd7f32 0%, #a0522d 100%); 
    color: #fff;
    box-shadow: 0 2px 8px rgba(205, 127, 50, 0.4);
}

.leader-info {
    flex: 1;
    min-width: 0;
}

.leader-info h5 {
    margin: 0;
    font-size: 0.9375rem;
    color: var(--gray-800, #1f2937);
    font-weight: 600;
}

.leader-id {
    font-size: 0.75rem;
    color: var(--gray-500, #6b7280);
}

.leader-stats {
    text-align: right;
    flex-shrink: 0;
}

.leader-stats .points {
    display: block;
    font-weight: 700;
    font-size: 1rem;
    color: var(--asj-green-600);
}

.leader-stats .badge-count {
    font-size: 0.75rem;
    color: var(--gray-500, #6b7280);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state.small {
    padding: 2rem;
}

.empty-state i {
    font-size: 3rem;
    color: #ddd;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #666;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #999;
    margin: 0;
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
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}

.modal-lg {
    max-width: 700px;
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

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group.full-width {
    grid-column: span 2;
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

.form-group input[type="color"] {
    height: 40px;
    padding: 0.25rem;
}

.icon-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.icon-option {
    cursor: pointer;
}

.icon-option input {
    display: none;
}

.icon-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 1.25rem;
    color: #666;
    transition: all 0.2s ease;
}

.icon-option input:checked + .icon-preview {
    border-color: var(--asj-green-500);
    background: var(--asj-green-50);
    color: var(--asj-green-600);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.required {
    color: #dc2626;
}

@media (max-width: 992px) {
    .badges-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-group.full-width {
        grid-column: span 1;
    }
    
    .badge-card {
        flex-direction: column;
        text-align: center;
    }
    
    .badge-actions {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
function showAddBadgeModal() {
    document.getElementById('badgeModalTitle').innerHTML = '<i class="fas fa-award"></i> Add Badge';
    document.getElementById('badgeAction').value = 'add_badge';
    document.getElementById('badgeId').value = '';
    document.getElementById('badgeName').value = '';
    document.getElementById('badgeDescription').value = '';
    document.getElementById('badgeType').value = 'manual';
    document.getElementById('badgeColor').value = '#4CAF50';
    document.getElementById('badgePoints').value = '0';
    document.querySelector('input[name="icon"][value="award"]').checked = true;
    document.getElementById('activeGroup').style.display = 'none';
    document.getElementById('badgeModal').style.display = 'flex';
}

function editBadge(badge) {
    document.getElementById('badgeModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Badge';
    document.getElementById('badgeAction').value = 'edit_badge';
    document.getElementById('badgeId').value = badge.id;
    document.getElementById('badgeName').value = badge.name || '';
    document.getElementById('badgeDescription').value = badge.description || '';
    document.getElementById('badgeType').value = badge.badge_type || 'perfect_attendance';
    document.getElementById('badgeColor').value = badge.color || '#4CAF50';
    document.getElementById('criteriaValue').value = badge.criteria_value || 0;
    document.getElementById('criteriaPeriod').value = badge.criteria_period || 'monthly';
    document.getElementById('badgePoints').value = badge.points || 10;
    document.getElementById('badgeActive').checked = badge.is_active == 1;
    document.getElementById('activeGroup').style.display = 'block';
    
    // Select icon
    const iconRadio = document.querySelector('input[name="icon"][value="' + (badge.icon || 'award') + '"]');
    if (iconRadio) iconRadio.checked = true;
    
    document.getElementById('badgeModal').style.display = 'flex';
}

function closeBadgeModal() {
    document.getElementById('badgeModal').style.display = 'none';
}

function showAwardModal(badgeId, badgeName) {
    document.getElementById('awardBadgeId').value = badgeId;
    document.getElementById('awardBadgeName').textContent = 'Awarding: ' + badgeName;
    document.getElementById('awardModal').style.display = 'flex';
}

function closeAwardModal() {
    document.getElementById('awardModal').style.display = 'none';
}

function showEvaluateModal() {
    document.getElementById('evaluateModal').style.display = 'flex';
}

function closeEvaluateModal() {
    document.getElementById('evaluateModal').style.display = 'none';
}

function updateUserList() {
    // In a real implementation, this would fetch users based on type
    // For now, we only have students pre-loaded
    const userType = document.getElementById('awardUserType').value;
    if (userType === 'teacher') {
        alert('Teacher list would be loaded here. For now, only students are available.');
        document.getElementById('awardUserType').value = 'student';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer_modern.php'; ?>
