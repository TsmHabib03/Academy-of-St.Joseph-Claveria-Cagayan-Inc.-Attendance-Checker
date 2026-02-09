<?php
/**
 * Behavior Analyzer Class for AttendEase v3.0
 * Detects attendance patterns and generates alerts.
 */

class BehaviorAnalyzer
{
    private PDO $pdo;
    private bool $monitoringEnabled = true;
    private int $lateThresholdWeekly = 3;
    private int $absenceThresholdConsecutive = 2;
    private int $attendanceDropThreshold = 30;

    private array $tableCache = [];
    private array $columnCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $this->monitoringEnabled = $this->getSettingBool('behavior_monitoring_enabled', true);
        $this->lateThresholdWeekly = $this->getSettingInt('late_threshold_weekly', 3);
        $this->absenceThresholdConsecutive = $this->getSettingInt('absence_threshold_consecutive', 2);
        $this->attendanceDropThreshold = $this->getSettingInt('attendance_drop_threshold', 30);
    }

    public function analyze($userId, string $userType = 'student'): array
    {
        if (!$this->monitoringEnabled) {
            return [];
        }

        $userType = $userType === 'teacher' ? 'teacher' : 'student';
        $normalizedId = $this->normalizeUserId($userId, $userType);
        if (!$normalizedId) {
            return [];
        }

        $alerts = [];

        if ($alert = $this->checkFrequentLateness($userType, $normalizedId)) {
            $alerts[] = $alert;
        }
        if ($alert = $this->checkConsecutiveAbsences($userType, $normalizedId)) {
            $alerts[] = $alert;
        }
        if ($alert = $this->checkSuddenAbsence($userType, $normalizedId)) {
            $alerts[] = $alert;
        }
        if ($alert = $this->checkAttendanceDrop($userType, $normalizedId)) {
            $alerts[] = $alert;
        }

        return $alerts;
    }

    private function normalizeUserId($userId, string $userType): ?string
    {
        $id = trim((string)$userId);
        if ($id === '') {
            return null;
        }

        if ($userType === 'student') {
            if (preg_match('/^\d{11,13}$/', $id)) {
                return $id;
            }

            if ($this->tableExists('students') && $this->columnExists('students', 'id') && $this->columnExists('students', 'lrn')) {
                $stmt = $this->pdo->prepare("SELECT lrn FROM students WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $lrn = $stmt->fetchColumn();
                return $lrn ? (string)$lrn : null;
            }

            return null;
        }

        if ($this->tableExists('teachers') && $this->columnExists('teachers', 'id')) {
            if ($this->columnExists('teachers', 'employee_number')) {
                $stmt = $this->pdo->prepare("SELECT COALESCE(employee_number, employee_id) FROM teachers WHERE employee_number = ? OR employee_id = ? OR id = ? LIMIT 1");
                $stmt->execute([$id, $id, $userId]);
                $val = $stmt->fetchColumn();
                if ($val) {
                    return (string)$val;
                }
            } elseif ($this->columnExists('teachers', 'employee_id')) {
                $stmt = $this->pdo->prepare("SELECT employee_id FROM teachers WHERE employee_id = ? OR id = ? LIMIT 1");
                $stmt->execute([$id, $userId]);
                $val = $stmt->fetchColumn();
                if ($val) {
                    return (string)$val;
                }
            }
        }

        return $id;
    }

    private function getTimeInColumns(string $table): array
    {
        $candidates = ['morning_time_in', 'afternoon_time_in', 'time_in'];
        $cols = [];
        foreach ($candidates as $c) {
            if ($this->columnExists($table, $c)) {
                $cols[] = $c;
            }
        }
        return $cols;
    }

    private function buildPresenceCondition(string $alias, array $cols): ?string
    {
        if (empty($cols)) {
            return null;
        }

        $parts = [];
        foreach ($cols as $c) {
            $parts[] = $alias . '.' . $c . ' IS NOT NULL';
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    private function getTeacherIdExpression(string $table, string $alias): string
    {
        $prefix = $alias ? $alias . '.' : '';
        $hasEmpNum = $this->columnExists($table, 'employee_number');
        $hasEmpId = $this->columnExists($table, 'employee_id');

        if ($hasEmpNum && $hasEmpId) {
            return "COALESCE({$prefix}employee_number, {$prefix}employee_id)";
        }
        if ($hasEmpNum) {
            return "{$prefix}employee_number";
        }
        return "{$prefix}employee_id";
    }

    private function checkFrequentLateness(string $userType, string $userId): ?array
    {
        $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
        if (!$this->tableExists($table)) {
            return null;
        }

        $lateColumns = [];
        if ($this->columnExists($table, 'is_late_morning')) {
            $lateColumns[] = 'is_late_morning = 1';
        }
        if ($this->columnExists($table, 'is_late_afternoon')) {
            $lateColumns[] = 'is_late_afternoon = 1';
        }
        if (empty($lateColumns)) {
            return null;
        }

        $idExpr = $userType === 'student' ? 'lrn' : $this->getTeacherIdExpression($table, '');
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as late_count FROM {$table} WHERE {$idExpr} = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND (" . implode(' OR ', $lateColumns) . ")"
        );
        $stmt->execute([$userId]);
        $lateCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['late_count'] ?? 0);

        if ($lateCount >= $this->lateThresholdWeekly && !$this->alertExistsThisWeek($userType, $userId, 'frequent_late')) {
            return $this->createAlert($userType, $userId, 'frequent_late', "Arrived late {$lateCount} times in the past week", $lateCount, 'warning');
        }

        return null;
    }

    private function checkConsecutiveAbsences(string $userType, string $userId): ?array
    {
        $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
        if (!$this->tableExists($table)) {
            return null;
        }

        $cols = $this->getTimeInColumns($table);
        $presentCond = $this->buildPresenceCondition('a', $cols);
        if ($presentCond === null) {
            return null;
        }

        $idExpr = $userType === 'student' ? 'a.lrn' : $this->getTeacherIdExpression($table, 'a');
        $stmt = $this->pdo->prepare("SELECT a.date FROM {$table} a WHERE {$idExpr} = ? AND {$presentCond} ORDER BY a.date DESC LIMIT 1");
        $stmt->execute([$userId]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$last || empty($last['date'])) {
            return null;
        }

        $lastDate = new DateTime($last['date']);
        $today = new DateTime();
        $missed = $this->countSchoolDays($lastDate, $today);

        if ($missed >= $this->absenceThresholdConsecutive && !$this->alertExistsThisWeek($userType, $userId, 'consecutive_absence')) {
            $severity = $missed >= 5 ? 'critical' : 'warning';
            return $this->createAlert($userType, $userId, 'consecutive_absence', "Absent for {$missed} consecutive school days", $missed, $severity);
        }

        return null;
    }

    private function checkSuddenAbsence(string $userType, string $userId): ?array
    {
        $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
        if (!$this->tableExists($table)) {
            return null;
        }

        $cols = $this->getTimeInColumns($table);
        $presentCond = $this->buildPresenceCondition('a', $cols);
        if ($presentCond === null) {
            return null;
        }

        $idExpr = $userType === 'student' ? 'a.lrn' : $this->getTeacherIdExpression($table, 'a');
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT a.date) as consecutive_present
             FROM {$table} a
             WHERE {$idExpr} = ?
               AND a.date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 2 DAY)
               AND {$presentCond}"
        );
        $stmt->execute([$userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)($r['consecutive_present'] ?? 0) >= 5) {
            $stmt2 = $this->pdo->prepare(
                "SELECT COUNT(*) as recent_attendance FROM {$table} a WHERE {$idExpr} = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND {$presentCond}"
            );
            $stmt2->execute([$userId]);
            $recent = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ((int)($recent['recent_attendance'] ?? 0) === 0 && !$this->alertExistsThisWeek($userType, $userId, 'sudden_absence')) {
                return $this->createAlert($userType, $userId, 'sudden_absence', 'Suddenly absent after consistent attendance', 1, 'info');
            }
        }

        return null;
    }

    private function checkAttendanceDrop(string $userType, string $userId): ?array
    {
        $table = $userType === 'student' ? 'attendance' : 'teacher_attendance';
        if (!$this->tableExists($table)) {
            return null;
        }

        $cols = $this->getTimeInColumns($table);
        $presentCond = $this->buildPresenceCondition('a', $cols);
        if ($presentCond === null) {
            return null;
        }

        $idExpr = $userType === 'student' ? 'a.lrn' : $this->getTeacherIdExpression($table, 'a');
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN a.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as this_month,
                SUM(CASE WHEN a.date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND a.date < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as last_month
             FROM {$table} a
             WHERE {$idExpr} = ? AND {$presentCond}"
        );
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

        return null;
    }

    private function countSchoolDays(DateTime $start, DateTime $end): int
    {
        $count = 0;
        $current = clone $start;
        while ($current < $end) {
            $current->modify('+1 day');
            $weekday = (int)$current->format('N');
            if ($weekday <= 5) {
                $count++;
            }
        }
        return $count;
    }

    private function alertExistsThisWeek(string $userType, string $userId, string $alertType): bool
    {
        if (!$this->tableExists('behavior_alerts')) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM behavior_alerts WHERE user_type = ? AND user_id = ? AND alert_type = ? AND date_detected >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stmt->execute([$userType, $userId, $alertType]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            return ((int)($r['count'] ?? 0)) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function createAlert(string $userType, string $userId, string $alertType, string $message, int $occurrences, string $severity): array
    {
        if (!$this->tableExists('behavior_alerts')) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO behavior_alerts (user_type, user_id, alert_type, alert_message, occurrences, date_detected, severity, created_at) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, NOW())");
            $stmt->execute([$userType, $userId, $alertType, $message, $occurrences, $severity]);
            $id = $this->pdo->lastInsertId();
            return [
                'id' => $id,
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

    public function getUnacknowledgedAlerts(?string $userType = null, ?string $severity = null, int $limit = 50): array
    {
        if (!$this->tableExists('behavior_alerts')) {
            return [];
        }

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

            $teacherJoin = '';
            if ($this->tableExists('teachers')) {
                $teacherIdExpr = $this->getTeacherIdExpression('teachers', 't');
                $teacherJoin = "LEFT JOIN teachers t ON ba.user_type = 'teacher' AND ba.user_id = {$teacherIdExpr}";
            }

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
                {$teacherJoin}
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

    public function acknowledgeAlert(int $alertId, int $adminId, string $notes = ''): bool
    {
        if (!$this->tableExists('behavior_alerts')) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE behavior_alerts SET is_acknowledged = 1, acknowledged_by = ?, acknowledged_at = NOW(), notes = ? WHERE id = ?");
            return $stmt->execute([$adminId, $notes, $alertId]);
        } catch (Exception $e) {
            error_log("Failed to acknowledge alert: " . $e->getMessage());
            return false;
        }
    }

    public function getStatistics(): array
    {
        if (!$this->tableExists('behavior_alerts')) {
            return ['total' => 0, 'unacknowledged' => 0, 'critical' => 0, 'warning' => 0, 'today' => 0];
        }

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

            return $stmt->fetch(PDO::FETCH_ASSOC) ?? ['total' => 0, 'unacknowledged' => 0, 'critical' => 0, 'warning' => 0, 'today' => 0];
        } catch (Exception $e) {
            error_log("Failed to get alert statistics: " . $e->getMessage());
            return ['total' => 0, 'unacknowledged' => 0, 'critical' => 0, 'warning' => 0, 'today' => 0];
        }
    }

    private function getSettingValue(string $key): ?string
    {
        if (!$this->tableExists('system_settings')) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val === false ? null : (string)$val;
        } catch (Exception $e) {
            return null;
        }
    }

    private function getSettingInt(string $key, int $default): int
    {
        $val = $this->getSettingValue($key);
        if ($val === null || $val === '') {
            return $default;
        }
        return is_numeric($val) ? (int)$val : $default;
    }

    private function getSettingBool(string $key, bool $default): bool
    {
        $val = $this->getSettingValue($key);
        if ($val === null || $val === '') {
            return $default;
        }
        $normalized = strtolower(trim($val));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
        return $default;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$table]);
            $exists = ((int)$stmt->fetchColumn()) > 0;
            $this->tableCache[$table] = $exists;
            return $exists;
        } catch (Exception $e) {
            $this->tableCache[$table] = false;
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }
        if (!$this->tableExists($table)) {
            $this->columnCache[$key] = false;
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            $exists = ((int)$stmt->fetchColumn()) > 0;
            $this->columnCache[$key] = $exists;
            return $exists;
        } catch (Exception $e) {
            $this->columnCache[$key] = false;
            return false;
        }
    }
}
