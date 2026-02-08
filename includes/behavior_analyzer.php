<?php
<?php
/**
 * Behavior Analyzer Class for AttendEase v3.0
 * Detects attendance patterns and generates alerts
 */

class BehaviorAnalyzer {

                if ($a = $this->checkConsecutiveAbsences('teacher', $id)) $alerts[] = $a;
                if ($a = $this->checkSuddenAbsence('teacher', $id)) $alerts[] = $a;
                if ($a = $this->checkAttendanceDrop('teacher', $id)) $alerts[] = $a;
            }
        } catch (Exception $e) {
            error_log("Failed to analyze teachers: " . $e->getMessage());
        }
        return $alerts;
    }

    private function checkFrequentLateness(string $userType, string $userId): ?array {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            $idField = $userType === 'student' ? 'lrn' : ($this->columnExists('teacher_attendance','employee_number') ? 'employee_number' : 'employee_id');
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as late_count FROM {$table} WHERE {$idField} = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND (is_late_morning = 1 OR is_late_afternoon = 1)");
            $stmt->execute([$userId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $lateCount = (int)($r['late_count'] ?? 0);
            if ($lateCount >= $this->lateThresholdWeekly && !$this->alertExistsThisWeek($userType, $userId, 'frequent_late')) {
                return $this->createAlert($userType, $userId, 'frequent_late', "Arrived late {$lateCount} times in the past week", $lateCount, 'warning');
            }
        } catch (Exception $e) {
            error_log("Failed to check frequent lateness: " . $e->getMessage());
        }
        return null;
    }

    private function checkConsecutiveAbsences(string $userType, string $userId): ?array {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            $idField = $userType === 'student' ? 'lrn' : ($this->columnExists('teacher_attendance','employee_number') ? 'employee_number' : 'employee_id');
            $stmt = $this->pdo->prepare("SELECT date FROM {$table} WHERE {$idField} = ? ORDER BY date DESC LIMIT 1");
            $stmt->execute([$userId]);
            $last = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($last) {
                $lastDate = new DateTime($last['date']);
                $today = new DateTime();
                $missed = $this->countSchoolDays($lastDate, $today);
                if ($missed >= $this->absenceThresholdConsecutive && !$this->alertExistsThisWeek($userType, $userId, 'consecutive_absence')) {
                    return $this->createAlert($userType, $userId, 'consecutive_absence', "Absent for {$missed} consecutive school days", $missed, $missed >= 5 ? 'critical' : 'warning');
                }
            }
        } catch (Exception $e) {
            error_log("Failed to check consecutive absences: " . $e->getMessage());
        }
        return null;
    }

    private function checkSuddenAbsence(string $userType, string $userId): ?array {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            $idField = $userType === 'student' ? 'lrn' : ($this->columnExists('teacher_attendance','employee_number') ? 'employee_number' : 'employee_id');
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT date) as consecutive_present, MAX(date) as last_present FROM {$table} WHERE {$idField} = ? AND date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 2 DAY) AND (morning_time_in IS NOT NULL OR afternoon_time_in IS NOT NULL)");
            $stmt->execute([$userId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (($r['consecutive_present'] ?? 0) >= 5) {
                $stmt2 = $this->pdo->prepare("SELECT COUNT(*) as recent_attendance FROM {$table} WHERE {$idField} = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
                $stmt2->execute([$userId]);
                $recent = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ((int)($recent['recent_attendance'] ?? 0) === 0 && !$this->alertExistsThisWeek($userType, $userId, 'sudden_absence')) {
                    return $this->createAlert($userType, $userId, 'sudden_absence', 'Suddenly absent after consistent attendance', 1, 'info');
                }
            }
        } catch (Exception $e) {
            error_log("Failed to check sudden absence: " . $e->getMessage());
        }
        return null;
    }

    private function checkAttendanceDrop(string $userType, string $userId): ?array {
        try {
            $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
            $idField = $userType === 'student' ? 'lrn' : ($this->columnExists('teacher_attendance','employee_number') ? 'employee_number' : 'employee_id');
            $stmt = $this->pdo->prepare("SELECT SUM(CASE WHEN date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as this_month, SUM(CASE WHEN date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND date < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as last_month FROM {$table} WHERE {$idField} = ? AND (morning_time_in IS NOT NULL OR afternoon_time_in IS NOT NULL)");
            $stmt->execute([$userId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $thisMonth = (int)($r['this_month'] ?? 0);
            $lastMonth = (int)($r['last_month'] ?? 0);
            $dayOfMonth = (int)date('j');
            $expectedThis = $lastMonth > 0 ? ($lastMonth / 30) * $dayOfMonth : 0;
            if ($lastMonth > 5 && $expectedThis > 0) {
                $drop = (($expectedThis - $thisMonth) / $expectedThis) * 100;
                if ($drop >= $this->attendanceDropThreshold && !$this->alertExistsThisWeek($userType, $userId, 'attendance_drop')) {
                    return $this->createAlert($userType, $userId, 'attendance_drop', sprintf("Attendance dropped by %.0f%% compared to last month", $drop), (int)$drop, 'warning');
                }
            }
        } catch (Exception $e) {
            error_log("Failed to check attendance drop: " . $e->getMessage());
        }
        return null;
    }

    private function countSchoolDays(DateTime $start, DateTime $end): int {
        $count = 0;
        $current = clone $start;
        while ($current < $end) {
            $current->modify('+1 day');
            $weekday = (int)$current->format('N');
            if ($weekday <= 5) $count++;
        }
        return $count;
    }

    private function alertExistsThisWeek(string $userType, string $userId, string $alertType): bool {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM behavior_alerts WHERE user_type = ? AND user_id = ? AND alert_type = ? AND date_detected >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stmt->execute([$userType, $userId, $alertType]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            return ((int)($r['count'] ?? 0)) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function createAlert(string $userType, string $userId, string $alertType, string $message, int $occurrences, string $severity): array {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO behavior_alerts (user_type, user_id, alert_type, alert_message, occurrences, date_detected, severity, created_at) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, NOW())");
            $stmt->execute([$userType, $userId, $alertType, $message, $occurrences, $severity]);
            $id = $this->pdo->lastInsertId();
            return ['id' => $id, 'user_type' => $userType, 'user_id' => $userId, 'alert_type' => $alertType, 'message' => $message, 'occurrences' => $occurrences, 'severity' => $severity, 'date_detected' => date('Y-m-d')];
        } catch (Exception $e) {
            error_log("Failed to create alert: " . $e->getMessage());
            return [];
        }
    }

    public function getUnacknowledgedAlerts(?string $userType = null, ?string $severity = null, int $limit = 50): array {
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
                    CASE WHEN ba.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name) WHEN ba.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name) END as user_name,
                    CASE WHEN ba.user_type = 'student' THEN s.section WHEN ba.user_type = 'teacher' THEN t.department END as user_group
                FROM behavior_alerts ba
                LEFT JOIN students s ON ba.user_type = 'student' AND ba.user_id = s.lrn
                LEFT JOIN teachers t ON ba.user_type = 'teacher' AND (ba.user_id = t.employee_number OR ba.user_id = t.employee_id)
                WHERE {$whereClause}
                ORDER BY FIELD(ba.severity, 'critical', 'warning', 'info'), ba.date_detected DESC
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

    public function acknowledgeAlert(int $alertId, int $adminId, string $notes = ''): bool {
        try {
            $stmt = $this->pdo->prepare("UPDATE behavior_alerts SET is_acknowledged = 1, acknowledged_by = ?, acknowledged_at = NOW(), notes = ? WHERE id = ?");
            return $stmt->execute([$adminId, $notes, $alertId]);
        } catch (Exception $e) {
            error_log("Failed to acknowledge alert: " . $e->getMessage());
            return false;
        }
    }

    public function getStatistics(): array {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_acknowledged = 0 THEN 1 ELSE 0 END) as unacknowledged, SUM(CASE WHEN severity = 'critical' AND is_acknowledged = 0 THEN 1 ELSE 0 END) as critical, SUM(CASE WHEN severity = 'warning' AND is_acknowledged = 0 THEN 1 ELSE 0 END) as warning, SUM(CASE WHEN date_detected = CURDATE() THEN 1 ELSE 0 END) as today FROM behavior_alerts");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?? ['total'=>0,'unacknowledged'=>0,'critical'=>0,'warning'=>0,'today'=>0];
        } catch (Exception $e) {
            error_log("Failed to get alert statistics: " . $e->getMessage());
            return ['total'=>0,'unacknowledged'=>0,'critical'=>0,'warning'=>0,'today'=>0];
        }
    }
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
                LEFT JOIN teachers t ON ba.user_type = 'teacher' AND (ba.user_id = t.employee_number OR ba.user_id = t.employee_id)
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
            $count = 0;
            $current = clone $start;
            while ($current < $end) {
                $current->modify('+1 day');
                $weekday = (int) $current->format('N'); // 1 (Mon) .. 7 (Sun)
                if ($weekday <= 5) $count++;
            }
        
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
