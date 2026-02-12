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
    private $tableCache = [];
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureTables();
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

    private function tableExists(string $table): bool {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
            $stmt->execute([':table' => $table]);
            $exists = ((int)$stmt->fetchColumn()) > 0;
            $this->tableCache[$table] = $exists;
            return $exists;
        } catch (Exception $e) {
            $this->tableCache[$table] = false;
            return false;
        }
    }

    private function ensureTables(): void {
        $this->ensureBadgesTable();
        $this->ensureUserBadgesTable();
    }

    private function ensureBadgesTable(): void {
        if ($this->tableExists('badges')) {
            return;
        }
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS badges (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    badge_name VARCHAR(100) NOT NULL,
                    badge_description TEXT NULL,
                    badge_icon VARCHAR(50) NOT NULL DEFAULT 'award',
                    badge_color VARCHAR(20) NOT NULL DEFAULT '#4CAF50',
                    criteria_type VARCHAR(50) NOT NULL DEFAULT 'perfect_attendance',
                    criteria_value INT NOT NULL DEFAULT 0,
                    criteria_period VARCHAR(20) NOT NULL DEFAULT 'monthly',
                    points INT NOT NULL DEFAULT 0,
                    applicable_roles VARCHAR(50) NOT NULL DEFAULT 'student',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_active (is_active),
                    INDEX idx_type (criteria_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $this->pdo->exec($sql);
            $this->tableCache['badges'] = true;
        } catch (Exception $e) {
            error_log("Failed to ensure badges table: " . $e->getMessage());
            $this->tableCache['badges'] = false;
        }
    }

    private function ensureUserBadgesTable(): void {
        if ($this->tableExists('user_badges')) {
            return;
        }
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS user_badges (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_type VARCHAR(20) NOT NULL DEFAULT 'student',
                    user_id VARCHAR(20) NOT NULL,
                    badge_id INT NOT NULL,
                    date_earned DATE NULL,
                    period_start DATE NULL,
                    period_end DATE NULL,
                    awarded_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_user_badge_period (user_type, user_id, badge_id, period_start, period_end),
                    INDEX idx_user (user_type, user_id),
                    INDEX idx_badge (badge_id),
                    INDEX idx_date (date_earned)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $this->pdo->exec($sql);
            $this->tableCache['user_badges'] = true;
        } catch (Exception $e) {
            error_log("Failed to ensure user_badges table: " . $e->getMessage());
            $this->tableCache['user_badges'] = false;
        }
    }

    private function getStudentIdColumn(): ?string {
        if (!$this->tableExists('students')) {
            return null;
        }
        if ($this->columnExists('students', 'lrn')) {
            return 'lrn';
        }
        if ($this->columnExists('students', 'student_id')) {
            return 'student_id';
        }
        if ($this->columnExists('students', 'id')) {
            return 'id';
        }
        return null;
    }

    private function getTeacherIdColumn(): ?string {
        if (!$this->tableExists('teachers')) {
            return null;
        }
        if ($this->columnExists('teachers', 'employee_number')) {
            return 'employee_number';
        }
        if ($this->columnExists('teachers', 'employee_id')) {
            return 'employee_id';
        }
        if ($this->columnExists('teachers', 'id')) {
            return 'id';
        }
        return null;
    }

    private function getTimeInColumns(string $table): array {
        $candidates = ['morning_time_in', 'afternoon_time_in', 'time_in'];
        $cols = [];
        foreach ($candidates as $c) {
            if ($this->columnExists($table, $c)) {
                $cols[] = $c;
            }
        }
        return $cols;
    }

    private function buildPresenceCondition(string $alias, array $cols): ?string {
        if (empty($cols)) {
            return null;
        }
        $parts = [];
        foreach ($cols as $c) {
            $parts[] = $alias . '.' . $c . ' IS NOT NULL';
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    private function getAttendanceIdColumn(string $table, string $userType): ?string {
        if ($userType === 'student') {
            if ($this->columnExists($table, 'lrn')) {
                return 'lrn';
            }
            if ($this->columnExists($table, 'student_id')) {
                return 'student_id';
            }
            if ($this->columnExists($table, 'id')) {
                return 'id';
            }
            return null;
        }

        if ($this->columnExists($table, 'employee_number')) {
            return 'employee_number';
        }
        if ($this->columnExists($table, 'employee_id')) {
            return 'employee_id';
        }
        if ($this->columnExists($table, 'id')) {
            return 'id';
        }
        return null;
    }
    
    /**
     * Load active badges from database
     */
    private function loadBadges(): void {
        if (!$this->tableExists('badges')) {
            $this->badges = [];
            return;
        }
        try {
            $hasActive = $this->columnExists('badges', 'is_active');
            $sql = $hasActive ? "SELECT * FROM badges WHERE is_active = 1" : "SELECT * FROM badges";
            $stmt = $this->pdo->query($sql);
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
        $result = [
            'evaluated' => 0,
            'awarded' => 0,
            'awards' => []
        ];

        // Evaluate for students
        if ($this->tableExists('students')) {
            try {
                $studentIdCol = $this->getStudentIdColumn();
                if ($studentIdCol) {
                    $stmt = $this->pdo->query("SELECT {$studentIdCol} AS identifier FROM students");
                    while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $identifier = $student['identifier'] ?? null;
                        if ($identifier === null || $identifier === '') {
                            continue;
                        }
                        $result['evaluated']++;
                        $studentAwards = $this->evaluateUser('student', (string)$identifier);
                        $result['awarded'] += count($studentAwards);
                        $result['awards'] = array_merge($result['awards'], $studentAwards);
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to evaluate student badges: " . $e->getMessage());
            }
        }

        // Evaluate for teachers
        if ($this->tableExists('teachers')) {
            try {
                $teacherIdCol = $this->getTeacherIdColumn();
                if ($teacherIdCol) {
                    $where = $this->columnExists('teachers', 'is_active') ? "WHERE is_active = 1" : "";
                    $stmt = $this->pdo->query("SELECT {$teacherIdCol} AS identifier FROM teachers {$where}");
                    while ($teacher = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $identifier = $teacher['identifier'] ?? null;
                        if ($identifier === null || $identifier === '') {
                            continue;
                        }
                        $result['evaluated']++;
                        $teacherAwards = $this->evaluateUser('teacher', (string)$identifier);
                        $result['awarded'] += count($teacherAwards);
                        $result['awards'] = array_merge($result['awards'], $teacherAwards);
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to evaluate teacher badges: " . $e->getMessage());
            }
        }

        return $result;
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
            $roles = $badge['applicable_roles'] ?? '';
            if (trim($roles) === '') {
                $roles = 'student,teacher';
            }
            if (strpos($roles, $userType) === false) {
                continue;
            }
            
            // Check if user already has this badge for current period
            if ($this->hasBadgeForPeriod($userType, $userId, $badge['id'], $badge['criteria_period'])) {
                continue;
            }
            
            // Evaluate based on criteria type
            $qualified = false;
            
            $criteriaType = $badge['criteria_type'] ?? 'perfect_attendance';
            switch ($criteriaType) {
                case 'perfect_attendance':
                    $qualified = $this->checkPerfectAttendance($userType, $userId, $badge['criteria_period'] ?? 'monthly');
                    break;

                case 'monthly_perfect':
                    $qualified = $this->checkPerfectAttendance($userType, $userId, 'monthly');
                    break;
                    
                case 'on_time_streak':
                    $qualified = $this->checkOnTimeStreak($userType, $userId, (int)($badge['criteria_value'] ?? 0));
                    break;
                    
                case 'most_improved':
                    $qualified = $this->checkMostImproved($userType, $userId, (int)($badge['criteria_value'] ?? 0));
                    break;
                    
                case 'early_bird':
                    $qualified = $this->checkEarlyBird($userType, $userId, (int)($badge['criteria_value'] ?? 0));
                    break;
                    
                case 'consistent':
                    $qualified = $this->checkConsistent($userType, $userId, $badge['criteria_period'] ?? 'monthly');
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
            $dateField = null;
            if ($this->columnExists('user_badges', 'date_earned')) {
                $dateField = 'date_earned';
            } elseif ($this->columnExists('user_badges', 'awarded_at')) {
                $dateField = 'DATE(awarded_at)';
            }

            if ($dateField) {
                $periodCondition = match($period) {
                    'daily' => "{$dateField} = CURDATE()",
                    'weekly' => "{$dateField} >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)",
                    'monthly' => "{$dateField} >= DATE_FORMAT(CURDATE(), \"%Y-%m-01\")",
                    'yearly' => "{$dateField} >= DATE_FORMAT(CURDATE(), \"%Y-01-01\")",
                    default => "{$dateField} = CURDATE()"
                };
            } else {
                // No date field available; prevent duplicates by checking any existing badge record.
                $periodCondition = '1=1';
            }
            
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
            if (!$this->tableExists($table)) {
                return false;
            }
            $idField = $this->getAttendanceIdColumn($table, $userType);
            if (!$idField) {
                return false;
            }

            $timeCols = $this->getTimeInColumns($table);
            $presentCond = $this->buildPresenceCondition('a', $timeCols);
            if ($presentCond === null) {
                return false;
            }
            
            // Get period boundaries
            [$startDate, $endDate] = $this->getPeriodDates($period);
            
            // Count school days in period (excluding weekends)
            $schoolDays = $this->countSchoolDays($startDate, $endDate);
            
            // Count attendance days
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT a.date) as present_days
                FROM {$table} a
                WHERE a.{$idField} = ?
                AND a.date BETWEEN ? AND ?
                AND {$presentCond}
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
            if (!$this->tableExists($table)) {
                return false;
            }
            $idField = $this->getAttendanceIdColumn($table, $userType);
            if (!$idField) {
                return false;
            }

            $timeCols = $this->getTimeInColumns($table);
            $presentCond = $this->buildPresenceCondition('a', $timeCols);
            if ($presentCond === null) {
                return false;
            }

            $lateCols = [];
            if ($this->columnExists($table, 'is_late_morning')) {
                $lateCols[] = 'is_late_morning';
            }
            if ($this->columnExists($table, 'is_late_afternoon')) {
                $lateCols[] = 'is_late_afternoon';
            }
            if (empty($lateCols)) {
                return false;
            }
            $lateSelect = implode(', ', $lateCols);
            
            // Get recent attendance records
            $stmt = $this->pdo->prepare("
                SELECT a.date, {$lateSelect}
                FROM {$table} a
                WHERE a.{$idField} = ?
                AND {$presentCond}
                ORDER BY a.date DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $requiredStreak + 5]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count consecutive on-time days
            $streak = 0;
            foreach ($records as $record) {
                $isLate = false;
                foreach ($lateCols as $col) {
                    if (!empty($record[$col])) {
                        $isLate = true;
                        break;
                    }
                }
                if (!$isLate) {
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
            if (!$this->tableExists($table)) {
                return false;
            }
            $idField = $this->getAttendanceIdColumn($table, $userType);
            if (!$idField) {
                return false;
            }

            $timeCols = $this->getTimeInColumns($table);
            $presentCond = $this->buildPresenceCondition('a', $timeCols);
            if ($presentCond === null) {
                return false;
            }

            $lateCols = [];
            if ($this->columnExists($table, 'is_late_morning')) {
                $lateCols[] = 'is_late_morning';
            }
            if ($this->columnExists($table, 'is_late_afternoon')) {
                $lateCols[] = 'is_late_afternoon';
            }
            $onTimeCond = $presentCond;
            if (!empty($lateCols)) {
                $lateParts = [];
                foreach ($lateCols as $col) {
                    $lateParts[] = "a.{$col} = 0";
                }
                $onTimeCond = $presentCond . ' AND ' . implode(' AND ', $lateParts);
            }
            
            // Compare this month to last month
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN a.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$presentCond} THEN 1 ELSE 0 END) as this_month,
                    SUM(CASE WHEN a.date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') 
                             AND a.date < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$presentCond} THEN 1 ELSE 0 END) as last_month,
                    SUM(CASE WHEN a.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') 
                             AND {$onTimeCond} THEN 1 ELSE 0 END) as this_month_on_time,
                    SUM(CASE WHEN a.date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') 
                             AND a.date < DATE_FORMAT(CURDATE(), '%Y-%m-01')
                             AND {$onTimeCond} THEN 1 ELSE 0 END) as last_month_on_time
                FROM {$table} a
                WHERE a.{$idField} = ?
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
            
            // Calculate improvement in on-time percentage (or attendance if late data unavailable)
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
            if (!$this->tableExists($table)) {
                return false;
            }
            $idField = $this->getAttendanceIdColumn($table, $userType);
            if (!$idField) {
                return false;
            }
            
            // Get expected time
            $expectedTime = '07:30:00';
            if ($this->tableExists('attendance_schedules') && $this->columnExists('attendance_schedules', 'expected_time_in')) {
                $stmt = $this->pdo->prepare("
                    SELECT expected_time_in
                    FROM attendance_schedules
                    WHERE user_type = ? AND session = 'morning' AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$userType]);
                $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($schedule['expected_time_in'])) {
                    $expectedTime = $schedule['expected_time_in'];
                }
            }

            $timeCols = $this->getTimeInColumns($table);
            if (empty($timeCols)) {
                return false;
            }
            // Prefer morning time_in if available, otherwise first available time_in column.
            $timeCol = in_array('morning_time_in', $timeCols, true) ? 'morning_time_in' : $timeCols[0];
            
            // Count consecutive days arriving before expected time
            $stmt = $this->pdo->prepare("
                SELECT date, {$timeCol} as time_in
                FROM {$table}
                WHERE {$idField} = ?
                AND {$timeCol} IS NOT NULL
                ORDER BY date DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $requiredDays + 5]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $streak = 0;
            foreach ($records as $record) {
                if (!empty($record['time_in']) && $record['time_in'] < $expectedTime) {
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
            if (!$this->tableExists('user_badges')) {
                return null;
            }

            $criteriaPeriod = $badge['criteria_period'] ?? 'monthly';
            [$periodStart, $periodEnd] = $this->getPeriodDates($criteriaPeriod);
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');

            $columns = ['user_type', 'user_id', 'badge_id'];
            $placeholders = ['?', '?', '?'];
            $params = [$userType, $userId, $badge['id']];

            if ($this->columnExists('user_badges', 'date_earned')) {
                $columns[] = 'date_earned';
                $placeholders[] = '?';
                $params[] = $today;
            }
            if ($this->columnExists('user_badges', 'period_start')) {
                $columns[] = 'period_start';
                $placeholders[] = '?';
                $params[] = $periodStart;
            }
            if ($this->columnExists('user_badges', 'period_end')) {
                $columns[] = 'period_end';
                $placeholders[] = '?';
                $params[] = $periodEnd;
            }
            if ($this->columnExists('user_badges', 'awarded_at')) {
                $columns[] = 'awarded_at';
                $placeholders[] = '?';
                $params[] = $now;
            }
            if ($this->columnExists('user_badges', 'created_at')) {
                $columns[] = 'created_at';
                $placeholders[] = '?';
                $params[] = $now;
            }

            $sql = "INSERT INTO user_badges (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

            $updates = [];
            if ($this->columnExists('user_badges', 'date_earned')) {
                $updates[] = "date_earned = VALUES(date_earned)";
            }
            if ($this->columnExists('user_badges', 'period_start')) {
                $updates[] = "period_start = VALUES(period_start)";
            }
            if ($this->columnExists('user_badges', 'period_end')) {
                $updates[] = "period_end = VALUES(period_end)";
            }
            if ($this->columnExists('user_badges', 'awarded_at')) {
                $updates[] = "awarded_at = VALUES(awarded_at)";
            }
            if ($this->columnExists('user_badges', 'created_at')) {
                $updates[] = "created_at = VALUES(created_at)";
            }
            if (!empty($updates)) {
                $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
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
            if (!$this->tableExists($nameTable) || !$this->tableExists('user_badges') || !$this->tableExists('badges')) {
                return [];
            }

            $nameField = null;
            if ($userType === 'student') {
                $nameField = $this->getStudentIdColumn();
            } else {
                $nameField = $this->getTeacherIdColumn();
            }
            if (!$nameField) {
                return [];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    ub.user_id,
                    u.first_name,
                    u.last_name,
                    u.{$nameField} as identifier,
                    COUNT(ub.id) as badge_count,
                    COALESCE(SUM(b.points), 0) as total_points
                FROM user_badges ub
                JOIN {$nameTable} u ON ub.user_id = u.{$nameField}
                JOIN badges b ON ub.badge_id = b.id
                WHERE ub.user_type = ?
                GROUP BY ub.user_id, u.first_name, u.last_name, u.{$nameField}
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
