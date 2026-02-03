<?php
/**
 * Behavior Analyzer Class for AttendEase v3.0
 * Detects attendance patterns and generates alerts
 * 
 * @package AttendEase
 * @version 3.0
 */

class BehaviorAnalyzer {
    private $pdo;
    private $settings;
    
    // Default thresholds
    private $lateThresholdWeekly = 3;
    private $absenceThresholdConsecutive = 2;
    private $attendanceDropThreshold = 20; // Percentage drop
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }
    
    /**
     * Load settings from database
     */
    private function loadSettings(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT setting_key, setting_value 
                FROM system_settings 
                WHERE setting_group = 'monitoring'
            ");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                switch ($row['setting_key']) {
                    case 'late_threshold_weekly':
                        $this->lateThresholdWeekly = (int) $row['setting_value'];
                        break;
                    case 'absence_threshold_consecutive':
                        $this->absenceThresholdConsecutive = (int) $row['setting_value'];
                        break;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to load behavior analyzer settings: " . $e->getMessage());
        }
    }
    
    /**
     * Run full behavior analysis
     * 
     * @param string|null $userType Limit to specific user type
     * @return array Array of generated alerts
     */
    public function analyze(?string $userType = null): array {
        $alerts = [];
        
        // Analyze students
        if (!$userType || $userType === 'student') {
            $alerts = array_merge($alerts, $this->analyzeStudents());
        }
        
        // Analyze teachers
        if (!$userType || $userType === 'teacher') {
            $alerts = array_merge($alerts, $this->analyzeTeachers());
        }
        
        return $alerts;
    }
    
    /**
     * Analyze student attendance patterns
     * 
     * @return array Generated alerts
     */
    private function analyzeStudents(): array {
        $alerts = [];
        
        // Get all active students
        try {
            $stmt = $this->pdo->query("SELECT lrn, first_name, last_name, section FROM students");
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($students as $student) {
                // Check frequent lateness
                $lateAlert = $this->checkFrequentLateness('student', $student['lrn']);
                if ($lateAlert) {
                    $alerts[] = $lateAlert;
                }
                
                // Check consecutive absences
                $absenceAlert = $this->checkConsecutiveAbsences('student', $student['lrn']);
                if ($absenceAlert) {
                    $alerts[] = $absenceAlert;
                }
                
                // Check sudden absence (was attending regularly, then suddenly absent)
                $suddenAlert = $this->checkSuddenAbsence('student', $student['lrn']);
                if ($suddenAlert) {
                    $alerts[] = $suddenAlert;
                }
                
                // Check attendance drop
                $dropAlert = $this->checkAttendanceDrop('student', $student['lrn']);
                if ($dropAlert) {
                    $alerts[] = $dropAlert;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to analyze students: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    /**
     * Analyze teacher attendance patterns
     * 
     * @return array Generated alerts
     */
    private function analyzeTeachers(): array {
        $alerts = [];
        
        try {
            $stmt = $this->pdo->query("
                SELECT employee_id, first_name, last_name, department 
                FROM teachers 
                WHERE is_active = 1
            ");
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($teachers as $teacher) {
                // Check frequent lateness
                $lateAlert = $this->checkFrequentLateness('teacher', $teacher['employee_id']);
                if ($lateAlert) {
                    $alerts[] = $lateAlert;
                }
                
                // Check consecutive absences
                $absenceAlert = $this->checkConsecutiveAbsences('teacher', $teacher['employee_id']);
                if ($absenceAlert) {
                    $alerts[] = $absenceAlert;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to analyze teachers: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    /**
     * Check for frequent lateness this week
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @return array|null Alert data or null
     */
    private function checkFrequentLateness(string $userType, string $userId): ?array {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            $idField = $userType === 'student' ? 'lrn' : 'employee_id';
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as late_count
                FROM {$table}
                WHERE {$idField} = ?
                AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND (is_late_morning = 1 OR is_late_afternoon = 1)
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $lateCount = (int) ($result['late_count'] ?? 0);
            
            if ($lateCount >= $this->lateThresholdWeekly) {
                // Check if alert already exists for this week
                if (!$this->alertExistsThisWeek($userType, $userId, 'frequent_late')) {
                    return $this->createAlert(
                        $userType,
                        $userId,
                        'frequent_late',
                        "Arrived late {$lateCount} times in the past week",
                        $lateCount,
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            error_log("Failed to check frequent lateness: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Check for consecutive absences
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @return array|null Alert data or null
     */
    private function checkConsecutiveAbsences(string $userType, string $userId): ?array {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            $idField = $userType === 'student' ? 'lrn' : 'employee_id';
            
            // Get the last N school days and check attendance
            $stmt = $this->pdo->prepare("
                SELECT date
                FROM {$table}
                WHERE {$idField} = ?
                ORDER BY date DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $lastAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastAttendance) {
                $lastDate = new DateTime($lastAttendance['date']);
                $today = new DateTime();
                $daysDiff = $today->diff($lastDate)->days;
                
                // Exclude weekends from count (simple approximation)
                $schoolDaysMissed = $this->countSchoolDays($lastDate, $today);
                
                if ($schoolDaysMissed >= $this->absenceThresholdConsecutive) {
                    if (!$this->alertExistsThisWeek($userType, $userId, 'consecutive_absence')) {
                        return $this->createAlert(
                            $userType,
                            $userId,
                            'consecutive_absence',
                            "Absent for {$schoolDaysMissed} consecutive school days",
                            $schoolDaysMissed,
                            $schoolDaysMissed >= 5 ? 'critical' : 'warning'
                        );
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Failed to check consecutive absences: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Check for sudden absence after regular attendance
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @return array|null Alert data or null
     */
    private function checkSuddenAbsence(string $userType, string $userId): ?array {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            $idField = $userType === 'student' ? 'lrn' : 'employee_id';
            
            // Check if user was present for at least 5 days in a row, then absent
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(DISTINCT date) as consecutive_present,
                    MAX(date) as last_present
                FROM {$table}
                WHERE {$idField} = ?
                AND date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 2 DAY)
                AND (morning_time_in IS NOT NULL OR afternoon_time_in IS NOT NULL)
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (($result['consecutive_present'] ?? 0) >= 5) {
                // Check if absent yesterday and today
                $stmt2 = $this->pdo->prepare("
                    SELECT COUNT(*) as recent_attendance
                    FROM {$table}
                    WHERE {$idField} = ?
                    AND date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                ");
                $stmt2->execute([$userId]);
                $recent = $stmt2->fetch(PDO::FETCH_ASSOC);
                
                if (($recent['recent_attendance'] ?? 0) == 0) {
                    if (!$this->alertExistsThisWeek($userType, $userId, 'sudden_absence')) {
                        return $this->createAlert(
                            $userType,
                            $userId,
                            'sudden_absence',
                            "Suddenly absent after consistent attendance",
                            1,
                            'info'
                        );
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Failed to check sudden absence: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Check for significant attendance drop
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @return array|null Alert data or null
     */
    private function checkAttendanceDrop(string $userType, string $userId): ?array {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            $idField = $userType === 'student' ? 'lrn' : 'employee_id';
            
            // Compare this month's attendance to last month
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as this_month,
                    SUM(CASE WHEN date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') 
                             AND date < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as last_month
                FROM {$table}
                WHERE {$idField} = ?
                AND (morning_time_in IS NOT NULL OR afternoon_time_in IS NOT NULL)
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $thisMonth = (int) ($result['this_month'] ?? 0);
            $lastMonth = (int) ($result['last_month'] ?? 0);
            
            // Calculate expected days this month so far
            $dayOfMonth = (int) date('j');
            $expectedThisMonth = $lastMonth > 0 ? ($lastMonth / 30) * $dayOfMonth : 0;
            
            if ($lastMonth > 5 && $expectedThisMonth > 0) {
                $dropPercentage = (($expectedThisMonth - $thisMonth) / $expectedThisMonth) * 100;
                
                if ($dropPercentage >= $this->attendanceDropThreshold) {
                    if (!$this->alertExistsThisWeek($userType, $userId, 'attendance_drop')) {
                        return $this->createAlert(
                            $userType,
                            $userId,
                            'attendance_drop',
                            sprintf("Attendance dropped by %.0f%% compared to last month", $dropPercentage),
                            (int) $dropPercentage,
                            'warning'
                        );
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Failed to check attendance drop: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Count school days between two dates (excluding weekends)
     * 
     * @param DateTime $start Start date
     * @param DateTime $end End date
     * @return int Number of school days
     */
    private function countSchoolDays(DateTime $start, DateTime $end): int {
        $count = 0;
        $current = clone $start;
        $current->modify('+1 day');
        
        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('N');
            if ($dayOfWeek < 6) { // Monday = 1, Friday = 5
                $count++;
            }
            $current->modify('+1 day');
        }
        
        return $count;
    }
    
    /**
     * Check if an alert already exists this week
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param string $alertType Alert type
     * @return bool True if alert exists
     */
    private function alertExistsThisWeek(string $userType, string $userId, string $alertType): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM behavior_alerts
                WHERE user_type = ?
                AND user_id = ?
                AND alert_type = ?
                AND date_detected >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$userType, $userId, $alertType]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($result['count'] ?? 0) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create and save an alert
     * 
     * @param string $userType User type
     * @param string $userId User ID
     * @param string $alertType Alert type
     * @param string $message Alert message
     * @param int $occurrences Number of occurrences
     * @param string $severity Alert severity
     * @return array Alert data
     */
    private function createAlert(
        string $userType,
        string $userId,
        string $alertType,
        string $message,
        int $occurrences,
        string $severity
    ): array {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO behavior_alerts 
                (user_type, user_id, alert_type, alert_message, occurrences, date_detected, severity, created_at)
                VALUES (?, ?, ?, ?, ?, CURDATE(), ?, NOW())
            ");
            $stmt->execute([$userType, $userId, $alertType, $message, $occurrences, $severity]);
            
            $alertId = $this->pdo->lastInsertId();
            
            return [
                'id' => $alertId,
                'user_type' => $userType,
                'user_id' => $userId,
                'alert_type' => $alertType,
                'message' => $message,
                'occurrences' => $occurrences,
                'severity' => $severity,
                'date_detected' => date('Y-m-d')
            ];
        } catch (Exception $e) {
            error_log("Failed to create alert: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unacknowledged alerts
     * 
     * @param string|null $userType Filter by user type
     * @param string|null $severity Filter by severity
     * @param int $limit Maximum number of alerts
     * @return array Array of alerts with user details
     */
    public function getUnacknowledgedAlerts(
        ?string $userType = null,
        ?string $severity = null,
        int $limit = 50
    ): array {
        try {
            $where = ['ba.is_acknowledged = 0'];
            $params = [];
            
            if ($userType) {
                $where[] = 'ba.user_type = ?';
                $params[] = $userType;
            }
            
            if ($severity) {
                $where[] = 'ba.severity = ?';
                $params[] = $severity;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $sql = "
                SELECT 
                    ba.*,
                    CASE 
                        WHEN ba.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                        WHEN ba.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                    END as user_name,
                    CASE 
                        WHEN ba.user_type = 'student' THEN s.section
                        WHEN ba.user_type = 'teacher' THEN t.department
                    END as user_group
                FROM behavior_alerts ba
                LEFT JOIN students s ON ba.user_type = 'student' AND ba.user_id = s.lrn
                LEFT JOIN teachers t ON ba.user_type = 'teacher' AND ba.user_id = t.employee_id
                WHERE {$whereClause}
                ORDER BY 
                    FIELD(ba.severity, 'critical', 'warning', 'info'),
                    ba.date_detected DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get alerts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Acknowledge an alert
     * 
     * @param int $alertId Alert ID
     * @param int $adminId Admin ID acknowledging
     * @param string $notes Optional notes for the acknowledgment
     * @return bool Success
     */
    public function acknowledgeAlert(int $alertId, int $adminId, string $notes = ''): bool {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE behavior_alerts 
                SET is_acknowledged = 1, acknowledged_by = ?, acknowledged_at = NOW(), notes = ?
                WHERE id = ?
            ");
            return $stmt->execute([$adminId, $notes, $alertId]);
        } catch (Exception $e) {
            error_log("Failed to acknowledge alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get alert statistics
     * 
     * @return array Statistics
     */
    public function getStatistics(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_acknowledged = 0 THEN 1 ELSE 0 END) as unacknowledged,
                    SUM(CASE WHEN severity = 'critical' AND is_acknowledged = 0 THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN severity = 'warning' AND is_acknowledged = 0 THEN 1 ELSE 0 END) as warning,
                    SUM(CASE WHEN date_detected = CURDATE() THEN 1 ELSE 0 END) as today
                FROM behavior_alerts
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?? [
                'total' => 0,
                'unacknowledged' => 0,
                'critical' => 0,
                'warning' => 0,
                'today' => 0
            ];
        } catch (Exception $e) {
            error_log("Failed to get alert statistics: " . $e->getMessage());
            return ['total' => 0, 'unacknowledged' => 0, 'critical' => 0, 'warning' => 0, 'today' => 0];
        }
    }
}
