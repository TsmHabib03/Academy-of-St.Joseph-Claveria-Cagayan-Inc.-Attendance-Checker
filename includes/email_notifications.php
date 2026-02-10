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
