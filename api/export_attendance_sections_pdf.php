<?php
/**
 * Export Attendance to PDF - Section-Based
 * Generates a PDF file with attendance records.
 */

require_once __DIR__ . '/bootstrap.php';
api_require_schema_or_exit($pdo, [
    'tables' => ['attendance', 'students']
]);
api_require_roles([ROLE_ADMIN, ROLE_TEACHER]);
require_once '../includes/database.php';

class SimplePdf {
    private array $pages = [];
    private int $currentPage = -1;
    private float $pageWidth;
    private float $pageHeight;
    private string $fontKey = 'F1';
    private int $fontSize = 10;

    public function __construct(string $orientation = 'P') {
        if (strtoupper($orientation) === 'L') {
            $this->pageWidth = 792;  // 11 in
            $this->pageHeight = 612; // 8.5 in
        } else {
            $this->pageWidth = 612;  // 8.5 in
            $this->pageHeight = 792; // 11 in
        }
    }

    public function getPageWidth(): float {
        return $this->pageWidth;
    }

    public function getPageHeight(): float {
        return $this->pageHeight;
    }

    public function addPage(): void {
        $this->pages[] = [];
        $this->currentPage = count($this->pages) - 1;
    }

    public function setFont(string $fontKey, int $size): void {
        $this->fontKey = $fontKey;
        $this->fontSize = $size;
    }

    public function text(float $x, float $y, string $text): void {
        $safeText = $this->escapeText($this->normalizeText($text));
        $this->pages[$this->currentPage][] = sprintf(
            'BT /%s %d Tf %s %s Td (%s) Tj ET',
            $this->fontKey,
            $this->fontSize,
            $this->formatNumber($x),
            $this->formatNumber($y),
            $safeText
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): void {
        $this->pages[$this->currentPage][] = sprintf(
            '%s w %s %s m %s %s l S',
            $this->formatNumber($width),
            $this->formatNumber($x1),
            $this->formatNumber($y1),
            $this->formatNumber($x2),
            $this->formatNumber($y2)
        );
    }

    public function output(): string {
        $objects = [];
        $addObject = function (string $content) use (&$objects): int {
            $objects[] = $content;
            return count($objects);
        };

        $fontRegularId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $fontBoldId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
        $pagesId = $addObject('');

        $pageIds = [];
        foreach ($this->pages as $pageContent) {
            $stream = implode("\n", $pageContent);
            $contentId = $addObject("<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream");
            $pageId = $addObject(
                "<< /Type /Page /Parent {$pagesId} 0 R " .
                "/MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] " .
                "/Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> >> " .
                "/Contents {$contentId} 0 R >>"
            );
            $pageIds[] = $pageId;
        }

        $kids = implode(' ', array_map(static fn($id) => "{$id} 0 R", $pageIds));
        $objects[$pagesId - 1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageIds) . " >>";

        $catalogId = $addObject("<< /Type /Catalog /Pages {$pagesId} 0 R >>");

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogId} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function normalizeText(string $text): string {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
            if ($converted !== false) {
                return $converted;
            }
        }
        return $text;
    }

    private function escapeText(string $text): string {
        $text = str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", " ", " "], $text);
        return $text;
    }

    private function formatNumber(float $value): string {
        $formatted = number_format($value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }
}

function truncate_text(string $text, int $maxChars): string {
    $text = trim($text);
    if (strlen($text) <= $maxChars) {
        return $text;
    }
    if ($maxChars <= 3) {
        return substr($text, 0, $maxChars);
    }
    return substr($text, 0, $maxChars - 3) . '...';
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $timeInParts = [];
    if (columnExists($db, 'attendance', 'morning_time_in')) {
        $timeInParts[] = 'a.morning_time_in';
    }
    if (columnExists($db, 'attendance', 'afternoon_time_in')) {
        $timeInParts[] = 'a.afternoon_time_in';
    }
    if (columnExists($db, 'attendance', 'time_in')) {
        $timeInParts[] = 'a.time_in';
    }
    if (count($timeInParts) === 0) {
        $timeInParts[] = 'NULL';
    }
    $timeInExpr = count($timeInParts) > 1 ? 'COALESCE(' . implode(', ', $timeInParts) . ')' : $timeInParts[0];

    $hasLateMorningCol = columnExists($db, 'attendance', 'is_late_morning');
    $hasLateAfternoonCol = columnExists($db, 'attendance', 'is_late_afternoon');

    $gradeColumn = null;
    if (columnExists($db, 'students', 'grade_level')) {
        $gradeColumn = 'grade_level';
    } elseif (columnExists($db, 'students', 'class')) {
        $gradeColumn = 'class';
    }
    $gradeField = $gradeColumn ? ('s.`' . $gradeColumn . '`') : null;

    $hasMiddleName = columnExists($db, 'students', 'middle_name');
    $hasParentEmail = columnExists($db, 'students', 'email');
    $hasAttendanceId = columnExists($db, 'attendance', 'id');
    $studentNameExpr = $hasMiddleName
        ? "CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name)"
        : "CONCAT(s.first_name, ' ', s.last_name)";
    $parentEmailExpr = $hasParentEmail ? 's.email' : 'NULL';

    $section = $_GET['section'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $student_search = trim($_GET['student_search'] ?? '');

    if (empty($start_date) || empty($end_date)) {
        throw new Exception('Start date and end date are required');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }

    $section_name = !empty($section) ? $section : 'All_Sections';
    $start_formatted = date('Ymd', strtotime($start_date));
    $end_formatted = date('Ymd', strtotime($end_date));
    $filename = "Attendance_{$section_name}_{$start_formatted}_to_{$end_formatted}.pdf";

    if ($hasAttendanceId) {
        $query = "SELECT 
                        a.lrn,
                        {$studentNameExpr} as student_name,
                        a.section,
                        a.date,
                        {$timeInExpr} AS resolved_time_in,
                        a.status,
                        " . ($hasLateMorningCol ? 'a.is_late_morning' : '0') . " AS is_late_morning,
                        " . ($hasLateAfternoonCol ? 'a.is_late_afternoon' : '0') . " AS is_late_afternoon,
                        {$parentEmailExpr} as parent_email
                    FROM attendance a
                    INNER JOIN (
                        SELECT lrn, date, MAX(id) AS latest_id
                        FROM attendance
                        WHERE date BETWEEN :start_date AND :end_date
                        GROUP BY lrn, date
                    ) latest ON latest.latest_id = a.id
                    INNER JOIN students s ON a.lrn = s.lrn
                    WHERE 1=1";
    } else {
        $query = "SELECT 
                        a.lrn,
                        {$studentNameExpr} as student_name,
                        a.section,
                        a.date,
                        {$timeInExpr} AS resolved_time_in,
                        a.status,
                        " . ($hasLateMorningCol ? 'a.is_late_morning' : '0') . " AS is_late_morning,
                        " . ($hasLateAfternoonCol ? 'a.is_late_afternoon' : '0') . " AS is_late_afternoon,
                        {$parentEmailExpr} as parent_email
                    FROM attendance a
                    INNER JOIN students s ON a.lrn = s.lrn
                    WHERE a.date BETWEEN :start_date AND :end_date";
    }

    if ($gradeField) {
        $query .= " AND {$gradeField} NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6')
                    AND {$gradeField} NOT LIKE 'Kinder%'";
    }

    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];

    if (!empty($section)) {
        $query .= " AND a.section = :section";
        $params[':section'] = $section;
    }

    if (!empty($student_search)) {
        $query .= " AND (
            s.lrn LIKE :search 
            OR s.first_name LIKE :search 
            OR s.last_name LIKE :search
            OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search
        )";
        $params[':search'] = '%' . $student_search . '%';
    }

    $query .= " ORDER BY a.date DESC, a.section ASC, s.last_name ASC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_records = [];
    $scanned_in_count = 0;
    $late_count = 0;
    $absent_count = 0;
    $sections_set = [];

    foreach ($records as $record) {
        $time_in = $record['resolved_time_in'] ? date('h:i A', strtotime($record['resolved_time_in'])) : '-';
        $hasTimeIn = !empty($record['resolved_time_in']);
        if ($hasTimeIn) {
            $scanned_in_count++;
        }

        $isLate = ((int)($record['is_late_morning'] ?? 0) === 1)
            || ((int)($record['is_late_afternoon'] ?? 0) === 1)
            || (strtolower((string)($record['status'] ?? '')) === 'late');
        if ($hasTimeIn && $isLate) {
            $late_count++;
        }

        $status = strtolower((string)($record['status'] ?? ''));
        if ($status === 'time_in' || $status === 'time_out') {
            $status = $isLate ? 'late' : 'present';
        } elseif ($status === '') {
            $status = $hasTimeIn ? ($isLate ? 'late' : 'present') : 'absent';
        }
        if ($status === 'absent') {
            $absent_count++;
        }

        if (!in_array($record['section'], $sections_set, true)) {
            $sections_set[] = $record['section'];
        }

        $formatted_records[] = [
            'lrn' => $record['lrn'],
            'student_name' => $record['student_name'],
            'section' => $record['section'],
            'date_formatted' => date('M j, Y', strtotime($record['date'])),
            'time_in' => $time_in,
            'status_text' => ucwords(str_replace('_', ' ', $status))
        ];
    }

    $total_records = count($formatted_records);
    $sections_count = count($sections_set);

    $pdf = new SimplePdf('L');
    $marginLeft = 30;
    $marginRight = 30;
    $marginTop = 36;
    $marginBottom = 36;
    $lineHeight = 12;

    $columns = [
        ['label' => 'LRN', 'key' => 'lrn', 'width' => 80, 'max' => 13],
        ['label' => 'Student Name', 'key' => 'student_name', 'width' => 220, 'max' => 42],
        ['label' => 'Section', 'key' => 'section', 'width' => 80, 'max' => 16],
        ['label' => 'Date', 'key' => 'date_formatted', 'width' => 80, 'max' => 12],
        ['label' => 'Time In', 'key' => 'time_in', 'width' => 90, 'max' => 12],
        ['label' => 'Status', 'key' => 'status_text', 'width' => 100, 'max' => 14],
    ];

    $tableWidth = array_sum(array_column($columns, 'width'));

    $startPage = function () use (
        $pdf,
        $marginLeft,
        $marginTop,
        $lineHeight,
        $columns,
        $tableWidth,
        $start_date,
        $end_date,
        $section,
        $student_search
    ): float {
        $pdf->addPage();
        $y = $pdf->getPageHeight() - $marginTop;

        $pdf->setFont('F2', 14);
        $pdf->text($marginLeft, $y, 'ATTENDANCE REPORT - SECTION-BASED');
        $y -= 18;

        $pdf->setFont('F1', 10);
        $pdf->text($marginLeft, $y, 'Generated: ' . date('F j, Y g:i A'));
        $y -= 14;

        $dateRange = date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date));
        $pdf->text($marginLeft, $y, 'Date Range: ' . $dateRange);
        $y -= 14;

        $pdf->text($marginLeft, $y, 'Section: ' . (!empty($section) ? $section : 'All Sections'));
        $y -= 14;

        if (!empty($student_search)) {
            $pdf->text($marginLeft, $y, 'Search Filter: ' . $student_search);
            $y -= 14;
        }

        $pdf->setFont('F2', 9);
        $x = $marginLeft;
        foreach ($columns as $col) {
            $pdf->text($x, $y, $col['label']);
            $x += $col['width'];
        }
        $y -= 4;
        $pdf->line($marginLeft, $y, $marginLeft + $tableWidth, $y, 0.7);
        $y -= 10;

        return $y;
    };

    $y = $startPage();
    $pdf->setFont('F1', 9);

    if (empty($formatted_records)) {
        $pdf->text($marginLeft, $y, 'No records found for the selected filters.');
        $y -= $lineHeight;
    } else {
        foreach ($formatted_records as $record) {
            if ($y < $marginBottom + $lineHeight) {
                $y = $startPage();
                $pdf->setFont('F1', 9);
            }

            $x = $marginLeft;
            foreach ($columns as $col) {
                $value = $record[$col['key']] ?? '';
                $value = truncate_text((string)$value, $col['max']);
                $pdf->text($x, $y, $value);
                $x += $col['width'];
            }
            $y -= $lineHeight;
        }
    }

    $summaryLines = [
        'Total Records: ' . $total_records,
        'Scanned In: ' . $scanned_in_count,
        'Late Arrivals: ' . $late_count,
        'On-time Arrivals: ' . max(0, $scanned_in_count - $late_count),
        'Absent Records: ' . $absent_count,
        'Sections Covered: ' . $sections_count,
    ];

    if ($y < $marginBottom + (count($summaryLines) + 2) * $lineHeight) {
        $y = $startPage();
        $pdf->setFont('F1', 9);
    }

    $pdf->setFont('F2', 10);
    $pdf->text($marginLeft, $y, 'Summary');
    $y -= $lineHeight;

    $pdf->setFont('F1', 9);
    foreach ($summaryLines as $line) {
        $pdf->text($marginLeft, $y, $line);
        $y -= $lineHeight;
    }

    $pdfData = $pdf->output();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($pdfData));
    echo $pdfData;
    exit;
} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    echo 'Error: ' . $e->getMessage();
}
?>
