<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('attendease_load_mailer_dependencies')) {
    function attendease_load_mailer_dependencies(): void
    {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return;
        }

        $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
            return;
        }

        $base = __DIR__ . '/../libs/PHPMailer/';
        if (file_exists($base . 'Exception.php')) {
            require_once $base . 'Exception.php';
            require_once $base . 'PHPMailer.php';
            require_once $base . 'SMTP.php';
        } else {
            error_log('PHPMailer not found in vendor or libs directory.');
        }
    }
}

if (!function_exists('attendease_format_alert_label')) {
    function attendease_format_alert_label(string $type): string
    {
        $types = [
            'frequent_lateness' => 'Frequent Lateness',
            'consecutive_absences' => 'Consecutive Absences',
            'sudden_absence' => 'Sudden Absence',
            'attendance_drop' => 'Attendance Drop'
        ];

        return $types[$type] ?? ucwords(str_replace('_', ' ', $type));
    }
}

if (!function_exists('attendease_fetch_behavior_alert_for_email')) {
    function attendease_fetch_behavior_alert_for_email(PDO $pdo, int $alertId): ?array
    {
        if (!function_exists('tableExists') || !tableExists($pdo, 'behavior_alerts')) {
            return null;
        }

        $select = ['ba.*'];
        $joins = [];

        $hasStudents = function_exists('tableExists') ? tableExists($pdo, 'students') : false;
        $hasTeachers = function_exists('tableExists') ? tableExists($pdo, 'teachers') : false;

        if ($hasStudents) {
            $studentJoinConditions = [];
            if (function_exists('columnExists') && columnExists($pdo, 'students', 'lrn')) {
                $studentJoinConditions[] = "ba.user_id = s.lrn";
            }
            if (function_exists('columnExists') && columnExists($pdo, 'students', 'id')) {
                $studentJoinConditions[] = "ba.user_id = s.id";
            }
            if (!empty($studentJoinConditions)) {
                $joins[] = "LEFT JOIN students s ON ba.user_type = 'student' AND (" . implode(' OR ', $studentJoinConditions) . ")";

                if (function_exists('columnExists') && columnExists($pdo, 'students', 'first_name')) {
                    $select[] = 's.first_name AS student_first_name';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'students', 'middle_name')) {
                    $select[] = 's.middle_name AS student_middle_name';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'students', 'last_name')) {
                    $select[] = 's.last_name AS student_last_name';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'students', 'email')) {
                    $select[] = 's.email AS student_email';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'students', 'section')) {
                    $select[] = 's.section AS student_section';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'students', 'class')) {
                    $select[] = 's.class AS student_class';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'students', 'lrn')) {
                    $select[] = 's.lrn AS student_lrn';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'students', 'id')) {
                    $select[] = 's.id AS student_id';
                }
            }
        }

        if ($hasTeachers) {
            $teacherJoinConditions = [];
            if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'employee_number')) {
                $teacherJoinConditions[] = "ba.user_id = t.employee_number";
            }
            if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'employee_id')) {
                $teacherJoinConditions[] = "ba.user_id = t.employee_id";
            }
            if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'id')) {
                $teacherJoinConditions[] = "ba.user_id = t.id";
            }
            if (!empty($teacherJoinConditions)) {
                $joins[] = "LEFT JOIN teachers t ON ba.user_type = 'teacher' AND (" . implode(' OR ', $teacherJoinConditions) . ")";

                if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'first_name')) {
                    $select[] = 't.first_name AS teacher_first_name';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'middle_name')) {
                    $select[] = 't.middle_name AS teacher_middle_name';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'last_name')) {
                    $select[] = 't.last_name AS teacher_last_name';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'email')) {
                    $select[] = 't.email AS teacher_email';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'department')) {
                    $select[] = 't.department AS teacher_department';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'employee_number')) {
                    $select[] = 't.employee_number AS teacher_employee_number';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'employee_id')) {
                    $select[] = 't.employee_id AS teacher_employee_id';
                }
                if (function_exists('columnExists') && columnExists($pdo, 'teachers', 'id')) {
                    $select[] = 't.id AS teacher_id';
                }
            }
        }

        $sql = "SELECT " . implode(', ', $select) . " FROM behavior_alerts ba ";
        if (!empty($joins)) {
            $sql .= implode(' ', $joins) . ' ';
        }
        $sql .= "WHERE ba.id = ? LIMIT 1";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$alertId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log('Failed to fetch behavior alert for email: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('attendease_build_behavior_ack_email_html')) {
    function attendease_build_behavior_ack_email_html(array $data): string
    {
        $safe = static function ($value): string {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };

        $notes = trim((string)($data['notes'] ?? ''));
        $notesDisplay = $notes !== '' ? nl2br($safe($notes)) : 'None';

        $schoolName = $safe($data['school_name'] ?? 'ASJ Attendance System');
        $schoolAddress = $safe($data['school_address'] ?? '');
        $supportEmail = $safe($data['support_email'] ?? '');

        return '<!DOCTYPE html>' .
            '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>' .
            '<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f5f5f5;padding:24px 0;">' .
            '<tr><td align="center">' .
            '<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,0.08);">' .
            '<tr><td style="background:#2e7d32;padding:18px 24px;color:#fff;">' .
            '<h2 style="margin:0;font-size:18px;font-weight:700;">Behavior Alert Acknowledged</h2>' .
            '</td></tr>' .
            '<tr><td style="padding:20px 24px;font-size:14px;color:#333;">' .
            '<p style="margin:0 0 12px;">Dear ' . $safe($data['recipient_label'] ?? 'Parent/Guardian') . ',</p>' .
            '<p style="margin:0 0 16px;">This is to inform you that the following alert has been acknowledged by ' . $safe($data['acknowledged_by'] ?? 'Admin') . '.</p>' .
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;border:1px solid #eee;">' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;width:40%;">Name</td><td style="padding:8px 10px;border:1px solid #eee;">' . $safe($data['display_name'] ?? '') . '</td></tr>' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;">Identifier</td><td style="padding:8px 10px;border:1px solid #eee;">' . $safe($data['identifier'] ?? '') . '</td></tr>' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;">Group</td><td style="padding:8px 10px;border:1px solid #eee;">' . $safe($data['group'] ?? 'N/A') . '</td></tr>' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;">Alert Type</td><td style="padding:8px 10px;border:1px solid #eee;">' . $safe($data['alert_type_label'] ?? '') . '</td></tr>' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;">Severity</td><td style="padding:8px 10px;border:1px solid #eee;">' . $safe($data['severity'] ?? '') . '</td></tr>' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;">Detected On</td><td style="padding:8px 10px;border:1px solid #eee;">' . $safe($data['date_detected'] ?? '') . '</td></tr>' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;">Acknowledged On</td><td style="padding:8px 10px;border:1px solid #eee;">' . $safe($data['acknowledged_at'] ?? '') . '</td></tr>' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;">Alert Message</td><td style="padding:8px 10px;border:1px solid #eee;">' . $safe($data['alert_message'] ?? '') . '</td></tr>' .
            '<tr><td style="padding:8px 10px;border:1px solid #eee;font-weight:600;">Notes</td><td style="padding:8px 10px;border:1px solid #eee;">' . $notesDisplay . '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '<tr><td style="background:#f9fafb;padding:16px 24px;font-size:12px;color:#666;text-align:center;">' .
            '<div style="font-weight:600;color:#333;">' . $schoolName . '</div>' .
            ($schoolAddress !== '' ? '<div style="margin-top:6px;">' . $schoolAddress . '</div>' : '') .
            ($supportEmail !== '' ? '<div style="margin-top:6px;">Email: <a href="mailto:' . $supportEmail . '" style="color:#2e7d32;text-decoration:none;">' . $supportEmail . '</a></div>' : '') .
            '<div style="margin-top:8px;color:#999;font-size:11px;">This is an automated message. Please do not reply.</div>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</body></html>';
    }
}

if (!function_exists('attendease_build_behavior_ack_email_text')) {
    function attendease_build_behavior_ack_email_text(array $data): string
    {
        $notes = trim((string)($data['notes'] ?? ''));
        if ($notes === '') {
            $notes = 'None';
        }

        return "BEHAVIOR ALERT ACKNOWLEDGED\n\n" .
            "Dear " . ($data['recipient_label'] ?? 'Parent/Guardian') . ",\n\n" .
            "The following alert has been acknowledged by " . ($data['acknowledged_by'] ?? 'Admin') . ".\n\n" .
            "Name: " . ($data['display_name'] ?? '') . "\n" .
            "Identifier: " . ($data['identifier'] ?? '') . "\n" .
            "Group: " . ($data['group'] ?? 'N/A') . "\n" .
            "Alert Type: " . ($data['alert_type_label'] ?? '') . "\n" .
            "Severity: " . ($data['severity'] ?? '') . "\n" .
            "Detected On: " . ($data['date_detected'] ?? '') . "\n" .
            "Acknowledged On: " . ($data['acknowledged_at'] ?? '') . "\n" .
            "Alert Message: " . ($data['alert_message'] ?? '') . "\n" .
            "Notes: " . $notes . "\n\n" .
            "This is an automated message. Please do not reply.";
    }
}

if (!function_exists('sendBehaviorAcknowledgementEmail')) {
    function sendBehaviorAcknowledgementEmail(PDO $pdo, int $alertId, array $admin, string $notes, array $emailConfig): bool
    {
        if (empty($emailConfig) || !is_array($emailConfig)) {
            error_log('Email configuration missing for behavior acknowledgment email.');
            return false;
        }

        attendease_load_mailer_dependencies();
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            error_log('PHPMailer class not available for behavior acknowledgment email.');
            return false;
        }

        $alert = attendease_fetch_behavior_alert_for_email($pdo, $alertId);
        if (!$alert) {
            error_log('Behavior alert not found for email send: ' . $alertId);
            return false;
        }

        $userType = $alert['user_type'] ?? 'student';
        $recipientLabel = $userType === 'teacher' ? 'Teacher' : 'Parent/Guardian';

        if ($userType === 'teacher') {
            $first = $alert['teacher_first_name'] ?? '';
            $middle = $alert['teacher_middle_name'] ?? '';
            $last = $alert['teacher_last_name'] ?? '';
            $recipientEmail = $alert['teacher_email'] ?? '';
            $identifier = $alert['teacher_employee_number'] ?? $alert['teacher_employee_id'] ?? $alert['teacher_id'] ?? $alert['user_id'] ?? '';
            $group = $alert['teacher_department'] ?? 'N/A';
        } else {
            $first = $alert['student_first_name'] ?? '';
            $middle = $alert['student_middle_name'] ?? '';
            $last = $alert['student_last_name'] ?? '';
            $recipientEmail = $alert['student_email'] ?? '';
            $identifier = $alert['student_lrn'] ?? $alert['student_id'] ?? $alert['user_id'] ?? '';
            $group = $alert['student_section'] ?? $alert['student_class'] ?? 'N/A';
        }

        $displayName = trim($first . ' ' . $middle . ' ' . $last);
        if ($displayName === '') {
            $displayName = (string)($identifier !== '' ? $identifier : 'User');
        }

        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid recipient email for behavior acknowledgment: ' . $recipientEmail);
            return false;
        }

        $alertType = $alert['alert_type'] ?? 'alert';
        $alertTypeLabel = attendease_format_alert_label($alertType);
        $severity = ucfirst($alert['severity'] ?? 'warning');

        $dateDetected = $alert['date_detected'] ?? '';
        if ($dateDetected !== '') {
            $dateDetected = date('M d, Y', strtotime($dateDetected));
        }

        $ackAt = $alert['acknowledged_at'] ?? '';
        if ($ackAt !== '') {
            $ackAt = date('M d, Y g:i A', strtotime($ackAt));
        } else {
            $ackAt = date('M d, Y g:i A');
        }

        $data = [
            'recipient_label' => $recipientLabel,
            'display_name' => $displayName,
            'identifier' => $identifier,
            'group' => $group,
            'alert_type_label' => $alertTypeLabel,
            'severity' => $severity,
            'date_detected' => $dateDetected,
            'acknowledged_at' => $ackAt,
            'acknowledged_by' => $admin['username'] ?? 'Admin',
            'alert_message' => $alert['alert_message'] ?? '',
            'notes' => $notes !== '' ? $notes : ($alert['notes'] ?? ''),
            'school_name' => $emailConfig['school_name'] ?? 'ASJ Attendance System',
            'school_address' => $emailConfig['school_address'] ?? '',
            'support_email' => $emailConfig['support_email'] ?? ''
        ];

        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $emailConfig['smtp_host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['smtp_username'] ?? '';
            $mail->Password = $emailConfig['smtp_password'] ?? '';
            $mail->SMTPSecure = $emailConfig['smtp_secure'] ?? '';
            $mail->Port = $emailConfig['smtp_port'] ?? 25;
            $mail->CharSet = $emailConfig['charset'] ?? 'UTF-8';

            if (!empty($emailConfig['debug'])) {
                $mail->SMTPDebug = (int)$emailConfig['debug'];
                $mail->Debugoutput = function ($str, $level) {
                    error_log('PHPMailer: ' . $str);
                };
            }

            $mail->setFrom($emailConfig['from_email'] ?? '', $emailConfig['from_name'] ?? '');
            $mail->addReplyTo($emailConfig['reply_to_email'] ?? '', $emailConfig['reply_to_name'] ?? '');
            $mail->addAddress($recipientEmail, $displayName);

            $mail->Subject = 'Behavior Alert Acknowledged: ' . $displayName;
            $mail->isHTML(true);
            $mail->Body = attendease_build_behavior_ack_email_html($data);
            $mail->AltBody = attendease_build_behavior_ack_email_text($data);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Behavior acknowledgment email failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('attendease_fetch_badge_for_email')) {
    function attendease_fetch_badge_for_email(PDO $pdo, int $badgeId): ?array
    {
        if (!function_exists('tableExists') || !tableExists($pdo, 'badges')) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM badges WHERE id = ? LIMIT 1");
            $stmt->execute([$badgeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log('Failed to fetch badge for email: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('attendease_fetch_badge_recipient_for_email')) {
    function attendease_fetch_badge_recipient_for_email(PDO $pdo, string $userType, string $userId): ?array
    {
        $userType = $userType === 'teacher' ? 'teacher' : 'student';
        $table = $userType === 'teacher' ? 'teachers' : 'students';
        $alias = $userType === 'teacher' ? 't' : 's';

        if (!function_exists('tableExists') || !tableExists($pdo, $table)) {
            return null;
        }
        if (!function_exists('columnExists')) {
            return null;
        }

        $conditions = [];
        $params = [];

        if ($userType === 'teacher') {
            if (columnExists($pdo, $table, 'employee_number')) {
                $conditions[] = "{$alias}.employee_number = ?";
                $params[] = $userId;
            }
            if (columnExists($pdo, $table, 'employee_id')) {
                $conditions[] = "{$alias}.employee_id = ?";
                $params[] = $userId;
            }
            if (columnExists($pdo, $table, 'id')) {
                $conditions[] = "{$alias}.id = ?";
                $params[] = $userId;
            }
        } else {
            if (columnExists($pdo, $table, 'lrn')) {
                $conditions[] = "{$alias}.lrn = ?";
                $params[] = $userId;
            }
            if (columnExists($pdo, $table, 'student_id')) {
                $conditions[] = "{$alias}.student_id = ?";
                $params[] = $userId;
            }
            if (columnExists($pdo, $table, 'id')) {
                $conditions[] = "{$alias}.id = ?";
                $params[] = $userId;
            }
        }

        if (empty($conditions)) {
            return null;
        }

        $sql = "SELECT {$alias}.* FROM {$table} {$alias} WHERE " . implode(' OR ', $conditions) . " LIMIT 1";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log('Failed to fetch badge recipient for email: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('attendease_build_badge_award_email_html')) {
    function attendease_build_badge_award_email_html(array $data): string
    {
        $safe = static function ($value): string {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };

        $badgeName = $safe($data['badge_name'] ?? 'Achievement Badge');
        $badgeDescriptionRaw = trim((string)($data['badge_description'] ?? ''));
        $badgeDescription = $badgeDescriptionRaw !== '' ? $safe($badgeDescriptionRaw) : 'No description provided';
        $points = (int)($data['points'] ?? 0);

        $criteriaType = trim((string)($data['criteria_type'] ?? 'manual'));
        $criteriaValue = (int)($data['criteria_value'] ?? 0);
        $criteriaPeriod = trim((string)($data['criteria_period'] ?? ''));

        $typeLabel = $criteriaType !== '' ? ucwords(str_replace('_', ' ', $criteriaType)) : 'Manual';
        $periodLabel = $criteriaPeriod !== '' ? ucfirst($criteriaPeriod) : '';
        $criteriaLabel = $typeLabel;
        if ($criteriaValue > 0) {
            $criteriaLabel .= ' (' . $criteriaValue . ($periodLabel !== '' ? ' ' . $periodLabel : '') . ')';
        } elseif ($periodLabel !== '') {
            $criteriaLabel .= ' (' . $periodLabel . ')';
        }

        $accent = trim((string)($data['badge_color'] ?? '#4CAF50'));
        if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $accent)) {
            $accent = '#4CAF50';
        }

        $recipientLabel = $safe($data['recipient_label'] ?? 'Student');
        $displayName = $safe($data['display_name'] ?? 'Student');
        $identifierLabel = $safe($data['identifier_label'] ?? 'Identifier');
        $identifier = $safe($data['identifier'] ?? '');
        $groupLabel = $safe($data['group_label'] ?? 'Group');
        $group = $safe($data['group'] ?? 'N/A');

        $awardedAt = $safe($data['awarded_at'] ?? '');
        $awardedBy = $safe($data['awarded_by'] ?? 'Admin');

        $schoolName = $safe($data['school_name'] ?? 'ASJ Attendance System');
        $schoolAddress = $safe($data['school_address'] ?? '');
        $supportEmail = $safe($data['support_email'] ?? '');
        $baseUrl = trim((string)($data['base_url'] ?? ''));

        $logoHtml = '';
        if ($baseUrl !== '') {
            $logoHtml = '<div style="width:70px;height:70px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.15);">' .
                '<img src="' . $safe($baseUrl) . '/assets/asj-logo.png" alt="School Logo" width="55" style="display:block;">' .
                '</div>';
        }

        $svgTrophy = '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="12" fill="' . $accent . '"/><path d="M8 6h8v3a4 4 0 01-8 0V6z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/><path d="M6 7H4v2a4 4 0 004 4" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/><path d="M18 7h2v2a4 4 0 01-4 4" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/><path d="M10 18h4" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/><path d="M12 13v5" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/></svg>';
        $svgUser = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="' . $accent . '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="7" r="4" stroke="' . $accent . '" stroke-width="1.5"/></svg>';
        $svgId = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="14" rx="2" stroke="#666" stroke-width="1.5"/><path d="M7 9h6" stroke="#666" stroke-width="1.5" stroke-linecap="round"/><path d="M7 13h8" stroke="#666" stroke-width="1.5" stroke-linecap="round"/></svg>';
        $svgGroup = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4z" stroke="#666" stroke-width="1.5"/><path d="M4 10h16" stroke="#666" stroke-width="1.5"/><path d="M8 14h4" stroke="#666" stroke-width="1.5" stroke-linecap="round"/></svg>';

        return '<!DOCTYPE html>' .
            '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>' .
            '<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f5f5f5;padding:24px 0;">' .
            '<tr><td align="center">' .
            '<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,0.08);">' .
            '<tr><td style="background:linear-gradient(135deg,#4CAF50 0%,#388E3C 100%);padding:24px 30px;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>' .
            '<td style="width:80px;vertical-align:middle;">' . $logoHtml . '</td>' .
            '<td style="vertical-align:middle;padding-left:20px;">' .
            '<h1 style="margin:0 0 6px;color:#fff;font-size:19px;font-weight:700;line-height:1.3;letter-spacing:-0.3px;">' . $schoolName . '</h1>' .
            '<p style="margin:0;color:rgba(255,255,255,0.92);font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:0.8px;">Achievement Badge Notification</p>' .
            '</td>' .
            '</tr></table>' .
            '</td></tr>' .
            '<tr><td style="padding:22px 20px 8px;text-align:center;">' .
            '<div style="display:inline-block;margin-bottom:10px;">' . $svgTrophy . '</div>' .
            '<h2 style="margin:8px 0 6px;color:' . $accent . ';font-size:18px;font-weight:700;">Badge Awarded</h2>' .
            '<p style="margin:0;color:#666;font-size:13px;">' . $badgeName . '</p>' .
            '</td></tr>' .
            '<tr><td style="padding:16px 20px 0;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-radius:8px;background:#fbfcfd;border:1px solid #eef2f6;">' .
            '<tr><td style="padding:16px 16px;border-left:6px solid ' . $accent . ';">' .
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation">' .
            '<tr><td style="vertical-align:top;padding-bottom:10px;">' .
            '<div style="display:flex;align-items:center;gap:12px;">' .
            '<div style="width:44px;height:44px;border-radius:6px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(0,0,0,0.06);">' . $svgUser . '</div>' .
            '<div>' .
            '<div style="font-size:12px;color:#888;text-transform:uppercase;letter-spacing:0.6px;font-weight:600;">' . $recipientLabel . '</div>' .
            '<div style="font-size:18px;color:#222;font-weight:700;margin-top:4px;">' . $displayName . '</div>' .
            '</div>' .
            '</div>' .
            '</td></tr>' .
            '<tr><td style="padding-top:6px;">' .
            '<table width="100%" cellpadding="6" cellspacing="0" role="presentation">' .
            '<tr><td style="width:30%;font-size:12px;color:#666;">' . $svgId . ' <span style="margin-left:6px;">' . $identifierLabel . '</span></td><td style="text-align:right;font-weight:600;color:#222;">' . $identifier . '</td></tr>' .
            '<tr><td style="width:30%;font-size:12px;color:#666;">' . $svgGroup . ' <span style="margin-left:6px;">' . $groupLabel . '</span></td><td style="text-align:right;font-weight:600;color:#222;">' . $group . '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '<tr><td style="padding:18px 20px 20px;">' .
            '<table width="100%" cellpadding="10" cellspacing="0" role="presentation" style="border:1px solid #eef2f6;border-radius:8px;">' .
            '<tr style="background:#fafbfc;color:#666;font-size:12px;text-transform:uppercase;font-weight:700;">' .
            '<td style="padding:10px 12px;">Badge Details</td><td style="padding:10px 12px;text-align:right;">' . $badgeName . '</td>' .
            '</tr>' .
            '<tr><td style="padding:10px 12px;color:#666;font-size:12px;">Description</td><td style="padding:10px 12px;text-align:right;font-weight:600;color:#222;">' . $badgeDescription . '</td></tr>' .
            '<tr><td style="padding:10px 12px;color:#666;font-size:12px;">Points</td><td style="padding:10px 12px;text-align:right;font-weight:600;color:#222;">' . $points . '</td></tr>' .
            '<tr><td style="padding:10px 12px;color:#666;font-size:12px;">Criteria</td><td style="padding:10px 12px;text-align:right;font-weight:600;color:#222;">' . $safe($criteriaLabel) . '</td></tr>' .
            '<tr><td style="padding:10px 12px;color:#666;font-size:12px;">Awarded On</td><td style="padding:10px 12px;text-align:right;font-weight:600;color:#222;">' . $awardedAt . '</td></tr>' .
            '<tr><td style="padding:10px 12px;color:#666;font-size:12px;">Awarded By</td><td style="padding:10px 12px;text-align:right;font-weight:600;color:#222;">' . $awardedBy . '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '<tr><td style="background:#f9fafb;padding:22px 20px;border-top:1px solid #eef2f6;text-align:center;color:#666;font-size:12px;">' .
            '<div style="font-weight:700;color:#222;">' . $schoolName . '</div>' .
            ($schoolAddress !== '' ? '<div style="margin-top:6px;">' . $schoolAddress . '</div>' : '') .
            ($supportEmail !== '' ? '<div style="margin-top:6px;">Email: <a href="mailto:' . $supportEmail . '" style="color:' . $accent . ';text-decoration:none;">' . $supportEmail . '</a></div>' : '') .
            '<div style="margin-top:10px;color:#999;font-size:11px;">This is an automated message. Please do not reply.</div>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</body></html>';
    }
}

if (!function_exists('attendease_build_badge_award_email_text')) {
    function attendease_build_badge_award_email_text(array $data): string
    {
        $badgeName = $data['badge_name'] ?? 'Achievement Badge';
        $badgeDescription = trim((string)($data['badge_description'] ?? ''));
        if ($badgeDescription === '') {
            $badgeDescription = 'No description provided';
        }

        $criteriaType = trim((string)($data['criteria_type'] ?? 'manual'));
        $criteriaValue = (int)($data['criteria_value'] ?? 0);
        $criteriaPeriod = trim((string)($data['criteria_period'] ?? ''));
        $typeLabel = $criteriaType !== '' ? ucwords(str_replace('_', ' ', $criteriaType)) : 'Manual';
        $periodLabel = $criteriaPeriod !== '' ? ucfirst($criteriaPeriod) : '';
        $criteriaLabel = $typeLabel;
        if ($criteriaValue > 0) {
            $criteriaLabel .= ' (' . $criteriaValue . ($periodLabel !== '' ? ' ' . $periodLabel : '') . ')';
        } elseif ($periodLabel !== '') {
            $criteriaLabel .= ' (' . $periodLabel . ')';
        }

        return "BADGE AWARDED\n\n" .
            "Hello " . ($data['display_name'] ?? 'Student') . ",\n\n" .
            "You have received a new achievement badge.\n\n" .
            "Badge: " . $badgeName . "\n" .
            "Description: " . $badgeDescription . "\n" .
            "Points: " . (int)($data['points'] ?? 0) . "\n" .
            "Criteria: " . $criteriaLabel . "\n" .
            "Awarded On: " . ($data['awarded_at'] ?? '') . "\n" .
            "Awarded By: " . ($data['awarded_by'] ?? 'Admin') . "\n\n" .
            "Recipient: " . ($data['display_name'] ?? '') . "\n" .
            ($data['identifier_label'] ?? 'Identifier') . ": " . ($data['identifier'] ?? '') . "\n" .
            ($data['group_label'] ?? 'Group') . ": " . ($data['group'] ?? 'N/A') . "\n\n" .
            "This is an automated message. Please do not reply.";
    }
}

if (!function_exists('sendBadgeAwardedEmail')) {
    function sendBadgeAwardedEmail(PDO $pdo, int $badgeId, string $userType, string $userId, array $admin, array $emailConfig, ?string $awardedAt = null): bool
    {
        if (empty($emailConfig) || !is_array($emailConfig)) {
            error_log('Email configuration missing for badge award email.');
            return false;
        }

        attendease_load_mailer_dependencies();
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            error_log('PHPMailer class not available for badge award email.');
            return false;
        }

        $badge = attendease_fetch_badge_for_email($pdo, $badgeId);
        if (!$badge) {
            error_log('Badge not found for email send: ' . $badgeId);
            return false;
        }

        $recipient = attendease_fetch_badge_recipient_for_email($pdo, $userType, $userId);
        if (!$recipient) {
            error_log('Badge recipient not found for email send: ' . $userType . ' ' . $userId);
            return false;
        }

        $email = $recipient['email'] ?? '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid recipient email for badge award: ' . $email);
            return false;
        }

        $first = $recipient['first_name'] ?? '';
        $middle = $recipient['middle_name'] ?? '';
        $last = $recipient['last_name'] ?? '';
        $displayName = trim($first . ' ' . $middle . ' ' . $last);
        if ($displayName === '') {
            $displayName = 'Student';
        }

        if ($userType === 'teacher') {
            $identifier = $recipient['employee_number'] ?? $recipient['employee_id'] ?? $recipient['id'] ?? $userId;
            $group = $recipient['department'] ?? 'N/A';
            $identifierLabel = 'Employee ID';
            $groupLabel = 'Department';
            $recipientLabel = 'Teacher';
        } else {
            $identifier = $recipient['lrn'] ?? $recipient['student_id'] ?? $recipient['id'] ?? $userId;
            $group = $recipient['section'] ?? $recipient['class'] ?? 'N/A';
            $identifierLabel = 'LRN';
            $groupLabel = 'Section';
            $recipientLabel = 'Student';
        }

        $awardedAtFormatted = $awardedAt ?: date('Y-m-d H:i:s');
        if ($awardedAtFormatted !== '') {
            $awardedAtFormatted = date('M d, Y g:i A', strtotime($awardedAtFormatted));
        }

        $data = [
            'recipient_label' => $recipientLabel,
            'display_name' => $displayName,
            'identifier_label' => $identifierLabel,
            'identifier' => $identifier,
            'group_label' => $groupLabel,
            'group' => $group,
            'badge_name' => $badge['badge_name'] ?? 'Achievement Badge',
            'badge_description' => $badge['badge_description'] ?? '',
            'badge_color' => $badge['badge_color'] ?? '#4CAF50',
            'criteria_type' => $badge['criteria_type'] ?? 'manual',
            'criteria_value' => $badge['criteria_value'] ?? 0,
            'criteria_period' => $badge['criteria_period'] ?? '',
            'points' => $badge['points'] ?? 0,
            'awarded_at' => $awardedAtFormatted,
            'awarded_by' => $admin['username'] ?? 'Admin',
            'school_name' => $emailConfig['school_name'] ?? 'ASJ Attendance System',
            'school_address' => $emailConfig['school_address'] ?? '',
            'support_email' => $emailConfig['support_email'] ?? '',
            'base_url' => $emailConfig['base_url'] ?? ''
        ];

        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $emailConfig['smtp_host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['smtp_username'] ?? '';
            $mail->Password = $emailConfig['smtp_password'] ?? '';
            $mail->SMTPSecure = $emailConfig['smtp_secure'] ?? '';
            $mail->Port = $emailConfig['smtp_port'] ?? 25;
            $mail->CharSet = $emailConfig['charset'] ?? 'UTF-8';

            if (!empty($emailConfig['debug'])) {
                $mail->SMTPDebug = (int)$emailConfig['debug'];
                $mail->Debugoutput = function ($str, $level) {
                    error_log('PHPMailer: ' . $str);
                };
            }

            $mail->setFrom($emailConfig['from_email'] ?? '', $emailConfig['from_name'] ?? '');
            $mail->addReplyTo($emailConfig['reply_to_email'] ?? '', $emailConfig['reply_to_name'] ?? '');
            $mail->addAddress($email, $displayName);

            $mail->Subject = 'Badge Awarded: ' . ($data['badge_name'] ?? 'Achievement Badge');
            $mail->isHTML(true);
            $mail->Body = attendease_build_badge_award_email_html($data);
            $mail->AltBody = attendease_build_badge_award_email_text($data);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Badge award email failed: ' . $e->getMessage());
            return false;
        }
    }
}
