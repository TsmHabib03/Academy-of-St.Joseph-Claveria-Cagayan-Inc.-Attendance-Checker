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

    $timeOutParts = [];
    if (columnExists($db, 'attendance', 'morning_time_out')) {
        $timeOutParts[] = 'a.morning_time_out';
    }
    if (columnExists($db, 'attendance', 'afternoon_time_out')) {
        $timeOutParts[] = 'a.afternoon_time_out';
    }
    if (columnExists($db, 'attendance', 'time_out')) {
        $timeOutParts[] = 'a.time_out';
    }
    if (count($timeOutParts) === 0) {
        $timeOutParts[] = 'NULL';
    }
    $timeOutExpr = count($timeOutParts) > 1 ? 'COALESCE(' . implode(', ', $timeOutParts) . ')' : $timeOutParts[0];

    $gradeColumn = null;
    if (columnExists($db, 'students', 'grade_level')) {
        $gradeColumn = 'grade_level';
    } elseif (columnExists($db, 'students', 'class')) {
        $gradeColumn = 'class';
    }
    $gradeField = $gradeColumn ? ('s.`' . $gradeColumn . '`') : null;

    $hasMiddleName = columnExists($db, 'students', 'middle_name');
    $hasParentEmail = columnExists($db, 'students', 'email');
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

    $query = "SELECT 
                    a.lrn,
                    {$studentNameExpr} as student_name,
                    a.section,
                    a.date,
                    {$timeInExpr} AS resolved_time_in,
                    {$timeOutExpr} AS resolved_time_out,
                    a.status,
                    {$parentEmailExpr} as parent_email
                FROM attendance a
                INNER JOIN students s ON a.lrn = s.lrn
                WHERE a.date BETWEEN :start_date AND :end_date";

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
    $completed_count = 0;
    $incomplete_count = 0;
    $sections_set = [];

    foreach ($records as $record) {
        $time_in = $record['resolved_time_in'] ? date('h:i A', strtotime($record['resolved_time_in'])) : '-';
        $time_out = $record['resolved_time_out'] ? date('h:i A', strtotime($record['resolved_time_out'])) : '-';

        $duration = '-';
        if ($record['resolved_time_in'] && $record['resolved_time_out']) {
            $time_in_obj = strtotime($record['resolved_time_in']);
            $time_out_obj = strtotime($record['resolved_time_out']);
            $duration_seconds = $time_out_obj - $time_in_obj;
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $duration = sprintf('%d hrs %d mins', $hours, $minutes);
            $completed_count++;
        } elseif ($record['resolved_time_in']) {
            $duration = 'In Progress';
            $incomplete_count++;
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
            'time_out' => $time_out,
            'duration' => $duration,
            'status_text' => $record['resolved_time_out'] ? 'Completed' : 'Incomplete'
        ];
    }

    $total_records = count($formatted_records);
    $sections_count = count($sections_set);
    $completion_rate = $total_records > 0 ? round(($completed_count / $total_records) * 100, 1) : 0;

    $pdf = new SimplePdf('L');
    $marginLeft = 30;
    $marginRight = 30;
    $marginTop = 36;
    $marginBottom = 36;
    $lineHeight = 12;

    $columns = [
        ['label' => 'LRN', 'key' => 'lrn', 'width' => 80, 'max' => 13],
        ['label' => 'Student Name', 'key' => 'student_name', 'width' => 200, 'max' => 38],
        ['label' => 'Section', 'key' => 'section', 'width' => 80, 'max' => 16],
        ['label' => 'Date', 'key' => 'date_formatted', 'width' => 80, 'max' => 12],
        ['label' => 'Time In', 'key' => 'time_in', 'width' => 60, 'max' => 10],
        ['label' => 'Time Out', 'key' => 'time_out', 'width' => 60, 'max' => 10],
        ['label' => 'Duration', 'key' => 'duration', 'width' => 80, 'max' => 12],
        ['label' => 'Status', 'key' => 'status_text', 'width' => 80, 'max' => 12],
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
        'Completed (Time In & Out): ' . $completed_count,
        'Incomplete (Time In Only): ' . $incomplete_count,
        'Sections Covered: ' . $sections_count,
        'Completion Rate: ' . $completion_rate . '%'
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
