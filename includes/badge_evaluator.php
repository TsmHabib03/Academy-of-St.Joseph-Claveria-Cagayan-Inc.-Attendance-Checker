<?php
/**
 * Badge Evaluator Class for AttendEase v3.0
 * Evaluates and awards attendance achievement badges
 * 
 * @package AttendEase
 * @version 3.0
 */

class BadgeEvaluator {
    private $pdo;
    private $badges = [];
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadBadges();
    }

    /**
     * Column cache
     * @var array
     */
    private $colCache = [];

    private function columnExists(string $table, string $column): bool {
        $key = $table . '::' . $column;
        if (isset($this->colCache[$key])) return $this->colCache[$key];
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :col");
            $stmt->execute([':table' => $table, ':col' => $column]);
            $exists = $stmt->fetchColumn() > 0;
            $this->colCache[$key] = $exists;
            return $exists;
        } catch (Exception $e) {
            $this->colCache[$key] = false;
            return false;
        }
    }
    
    /**
     * Load active badges from database
     */
    private function loadBadges(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM badges WHERE is_active = 1
            ");
            $this->badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to load badges: " . $e->getMessage());
            $this->badges = [];
        }
    }
    
    /**
     * Evaluate all badges for all users
     * 
     * @return array Array of newly awarded badges
     */
    public function evaluateAll(): array {
        $awarded = [];
        
        // Evaluate for students
        try {
            $stmt = $this->pdo->query("SELECT lrn FROM students");
            while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $studentAwards = $this->evaluateUser('student', $student['lrn']);
                $awarded = array_merge($awarded, $studentAwards);
            }
        } catch (Exception $e) {
            error_log("Failed to evaluate student badges: " . $e->getMessage());
        }
        
        // Evaluate for teachers
        try {
            $stmt = $this->pdo->query("SELECT employee_number, employee_id FROM teachers WHERE is_active = 1");
            while ($teacher = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $teacherId = $teacher['employee_number'] ?? $teacher['employee_id'] ?? null;
                if ($teacherId) {
                    $teacherAwards = $this->evaluateUser('teacher', $teacherId);
                    $awarded = array_merge($awarded, $teacherAwards);
                }
            }
        } catch (Exception $e) {
            error_log("Failed to evaluate teacher badges: " . $e->getMessage());
        }
        
        return $awarded;
    }
    
    /**
     * Evaluate badges for a specific user
     * 
     * @param string $userType User type (student/teacher)
     * @param string $userId User ID
     * @return array Newly awarded badges
     */
    public function evaluateUser(string $userType, string $userId): array {
        $awarded = [];
        
        foreach ($this->badges as $badge) {
            // Check if badge is applicable to this user type
            if (strpos($badge['applicable_roles'], $userType) === false) {
                continue;
            }
            
            // Check if user already has this badge for current period
            if ($this->hasBadgeForPeriod($userType, $userId, $badge['id'], $badge['criteria_period'])) {
                continue;
            }
            
            // Evaluate based on criteria type
            $qualified = false;
            
            switch ($badge['criteria_type']) {
                case 'perfect_attendance':
                    $qualified = $this->checkPerfectAttendance($userType, $userId, $badge['criteria_period']);
                    break;
                    
                case 'on_time_streak':
                    $qualified = $this->checkOnTimeStreak($userType, $userId, $badge['criteria_value']);
                    break;
                    
                case 'most_improved':
                    $qualified = $this->checkMostImproved($userType, $userId, $badge['criteria_value']);
                    break;
                    
                case 'early_bird':
                    $qualified = $this->checkEarlyBird($userType, $userId, $badge['criteria_value']);
                    break;
                    
                case 'consistent':
                    $qualified = $this->checkConsistent($userType, $userId, $badge['criteria_period']);
                    break;
            }
            
            if ($qualified) {
                $awardResult = $this->awardBadge($userType, $userId, $badge);
                if ($awardResult) {
                    $awarded[] = $awardResult;
                }
            }
        }
        
        return $awarded;
    }
    
    /**
     * Check if user has badge for current period
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param int $badgeId Badge ID
     * @param string $period Period type
     * @return bool True if already has badge
     */
    private function hasBadgeForPeriod(string $userType, string $userId, int $badgeId, string $period): bool {
        try {
            $periodCondition = match($period) {
                'daily' => 'date_earned = CURDATE()',
                'weekly' => 'date_earned >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)',
                'monthly' => 'date_earned >= DATE_FORMAT(CURDATE(), "%Y-%m-01")',
                'yearly' => 'date_earned >= DATE_FORMAT(CURDATE(), "%Y-01-01")',
                default => 'date_earned = CURDATE()'
            };
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM user_badges
                WHERE user_type = ?
                AND user_id = ?
                AND badge_id = ?
                AND {$periodCondition}
            ");
            $stmt->execute([$userType, $userId, $badgeId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($result['count'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log("Failed to check badge period: " . $e->getMessage());
            return true; // Assume has badge on error to prevent duplicates
        }
    }
    
    /**
     * Check for perfect attendance in period
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param string $period Period type
     * @return bool True if qualified
     */
    private function checkPerfectAttendance(string $userType, string $userId, string $period): bool {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            if ($userType === 'student') {
                $idField = 'lrn';
            } else {
                $idField = $this->columnExists('teacher_attendance', 'employee_number') ? 'employee_number' : 'employee_id';
            }
            
            // Get period boundaries
            [$startDate, $endDate] = $this->getPeriodDates($period);
            
            // Count school days in period (excluding weekends)
            $schoolDays = $this->countSchoolDays($startDate, $endDate);
            
            // Count attendance days
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT date) as present_days
                FROM {$table}
                WHERE {$idField} = ?
                AND date BETWEEN ? AND ?
                AND (morning_time_in IS NOT NULL OR afternoon_time_in IS NOT NULL)
            ");
            $stmt->execute([$userId, $startDate, $endDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $presentDays = (int) ($result['present_days'] ?? 0);
            
            // Must be present at least 90% of school days to qualify
            // And at least 5 days minimum
            return $presentDays >= $schoolDays * 0.9 && $presentDays >= 5;
        } catch (Exception $e) {
            error_log("Failed to check perfect attendance: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for on-time streak
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param int $requiredStreak Required consecutive on-time days
     * @return bool True if qualified
     */
    private function checkOnTimeStreak(string $userType, string $userId, int $requiredStreak): bool {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            if ($userType === 'student') {
                $idField = 'lrn';
            } else {
                $idField = $this->columnExists('teacher_attendance', 'employee_number') ? 'employee_number' : 'employee_id';
            }
            
            // Get recent attendance records
            $stmt = $this->pdo->prepare("
                SELECT date, is_late_morning, is_late_afternoon
                FROM {$table}
                WHERE {$idField} = ?
                AND (morning_time_in IS NOT NULL OR afternoon_time_in IS NOT NULL)
                ORDER BY date DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $requiredStreak + 5]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count consecutive on-time days
            $streak = 0;
            foreach ($records as $record) {
                if (!$record['is_late_morning'] && !$record['is_late_afternoon']) {
                    $streak++;
                    if ($streak >= $requiredStreak) {
                        return true;
                    }
                } else {
                    break; // Streak broken
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Failed to check on-time streak: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for most improved attendance
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param int $improvementPercent Required improvement percentage
     * @return bool True if qualified
     */
    private function checkMostImproved(string $userType, string $userId, int $improvementPercent): bool {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            if ($userType === 'student') {
                $idField = 'lrn';
            } else {
                $idField = $this->columnExists('teacher_attendance', 'employee_number') ? 'employee_number' : 'employee_id';
            }
            
            // Compare this month to last month
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as this_month,
                    SUM(CASE WHEN date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') 
                             AND date < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as last_month,
                    SUM(CASE WHEN date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') 
                             AND is_late_morning = 0 AND is_late_afternoon = 0 THEN 1 ELSE 0 END) as this_month_on_time,
                    SUM(CASE WHEN date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') 
                             AND date < DATE_FORMAT(CURDATE(), '%Y-%m-01')
                             AND is_late_morning = 0 AND is_late_afternoon = 0 THEN 1 ELSE 0 END) as last_month_on_time
                FROM {$table}
                WHERE {$idField} = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $thisMonth = (int) ($result['this_month'] ?? 0);
            $lastMonth = (int) ($result['last_month'] ?? 0);
            $thisMonthOnTime = (int) ($result['this_month_on_time'] ?? 0);
            $lastMonthOnTime = (int) ($result['last_month_on_time'] ?? 0);
            
            // Need at least 10 days of data
            if ($lastMonth < 10 || $thisMonth < 10) {
                return false;
            }
            
            // Calculate improvement in on-time percentage
            $lastMonthRate = ($lastMonthOnTime / $lastMonth) * 100;
            $thisMonthRate = ($thisMonthOnTime / $thisMonth) * 100;
            $improvement = $thisMonthRate - $lastMonthRate;
            
            return $improvement >= $improvementPercent;
        } catch (Exception $e) {
            error_log("Failed to check most improved: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for early bird (arriving early)
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param int $requiredDays Required consecutive early days
     * @return bool True if qualified
     */
    private function checkEarlyBird(string $userType, string $userId, int $requiredDays): bool {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            if ($userType === 'student') {
                $idField = 'lrn';
            } else {
                $idField = $this->columnExists('teacher_attendance', 'employee_number') ? 'employee_number' : 'employee_id';
            }
            
            // Get expected time
            $stmt = $this->pdo->prepare("
                SELECT expected_time_in
                FROM attendance_schedules
                WHERE user_type = ? AND session = 'morning' AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$userType]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            $expectedTime = $schedule['expected_time_in'] ?? '07:30:00';
            
            // Count consecutive days arriving before expected time
            $stmt = $this->pdo->prepare("
                SELECT date, morning_time_in
                FROM {$table}
                WHERE {$idField} = ?
                AND morning_time_in IS NOT NULL
                ORDER BY date DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $requiredDays + 5]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $streak = 0;
            foreach ($records as $record) {
                if ($record['morning_time_in'] < $expectedTime) {
                    $streak++;
                    if ($streak >= $requiredDays) {
                        return true;
                    }
                } else {
                    break;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Failed to check early bird: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for consistent attendance in period
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param string $period Period type
     * @return bool True if qualified
     */
    private function checkConsistent(string $userType, string $userId, string $period): bool {
        // Consistent means 100% attendance for the period
        return $this->checkPerfectAttendance($userType, $userId, $period);
    }
    
    /**
     * Award badge to user
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param array $badge Badge data
     * @return array|null Award data or null on failure
     */
    private function awardBadge(string $userType, string $userId, array $badge): ?array {
        try {
            [$periodStart, $periodEnd] = $this->getPeriodDates($badge['criteria_period']);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_badges 
                (user_type, user_id, badge_id, date_earned, period_start, period_end, created_at)
                VALUES (?, ?, ?, CURDATE(), ?, ?, NOW())
            ");
            $stmt->execute([$userType, $userId, $badge['id'], $periodStart, $periodEnd]);
            
            return [
                'id' => $this->pdo->lastInsertId(),
                'user_type' => $userType,
                'user_id' => $userId,
                'badge_id' => $badge['id'],
                'badge_name' => $badge['badge_name'],
                'badge_description' => $badge['badge_description'],
                'badge_icon' => $badge['badge_icon'],
                'badge_color' => $badge['badge_color'],
                'points' => $badge['points'],
                'date_earned' => date('Y-m-d')
            ];
        } catch (Exception $e) {
            error_log("Failed to award badge: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get period start and end dates
     * 
     * @param string $period Period type
     * @return array [start_date, end_date]
     */
    private function getPeriodDates(string $period): array {
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
    
    /**
     * Count school days in date range
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return int Number of school days
     */
    private function countSchoolDays(string $startDate, string $endDate): int {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $count = 0;
        
        while ($start <= $end) {
            $dayOfWeek = (int) $start->format('N');
            if ($dayOfWeek < 6) { // Monday = 1, Friday = 5
                $count++;
            }
            $start->modify('+1 day');
        }
        
        return $count;
    }
    
    /**
     * Get user's badges
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param int $limit Maximum badges to return
     * @return array Array of badges
     */
    public function getUserBadges(string $userType, string $userId, int $limit = 10): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ub.*,
                    b.badge_name,
                    b.badge_description,
                    b.badge_icon,
                    b.badge_color,
                    b.points
                FROM user_badges ub
                JOIN badges b ON ub.badge_id = b.id
                WHERE ub.user_type = ? AND ub.user_id = ?
                ORDER BY ub.date_earned DESC
                LIMIT ?
            ");
            $stmt->execute([$userType, $userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get user badges: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's total points from badges
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @return int Total points
     */
    public function getUserPoints(string $userType, string $userId): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(b.points), 0) as total_points
                FROM user_badges ub
                JOIN badges b ON ub.badge_id = b.id
                WHERE ub.user_type = ? AND ub.user_id = ?
            ");
            $stmt->execute([$userType, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['total_points'] ?? 0);
        } catch (Exception $e) {
            error_log("Failed to get user points: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get badge leaderboard
     * 
     * @param string $userType User type
     * @param int $limit Maximum entries
     * @return array Leaderboard entries
     */
    public function getLeaderboard(string $userType, int $limit = 10): array {
        try {
            $nameTable = $userType === 'student' ? 'students' : 'teachers';
            $nameField = $userType === 'student' ? 'lrn' : ($this->columnExists('teacher_attendance', 'employee_number') ? 'employee_number' : 'employee_id');
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    ub.user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    COUNT(ub.id) as badge_count,
                    COALESCE(SUM(b.points), 0) as total_points
                FROM user_badges ub
                JOIN {$nameTable} u ON ub.user_id = u.{$nameField}
                JOIN badges b ON ub.badge_id = b.id
                WHERE ub.user_type = ?
                GROUP BY ub.user_id, u.first_name, u.last_name
                ORDER BY total_points DESC, badge_count DESC
                LIMIT ?
            ");
            $stmt->execute([$userType, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get leaderboard: " . $e->getMessage());
            return [];
        }
    }
}
