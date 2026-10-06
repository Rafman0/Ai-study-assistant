<?php
/**
 * Study Guide -> PDF download endpoint
 *
 * Receives the AI-generated study guide (same data that was shown on the
 * web page) together with basic course metadata, and returns a professional,
 * paginated PDF with a cover page, page headers, footers and page numbers.
 *
 * Security model:
 * - Requires an authenticated session + CSRF token (same as every API call)
 * - JSON input size is capped; the summary is re-normalized server-side
 * - All strings are stripped of HTML/Markdown and converted to Latin-1 for FPDF
 * - Generated filename is derived from sanitized content (never raw input)
 * - Responses are marked private / no-store
 */

header('Content-Type: application/json');

// Buffer everything so stray PHP output can never corrupt the PDF stream;
// FPDF's own output-guard then remains reliable (ob_get_length() stays 0).
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/StudyGuide.php';
require_once __DIR__ . '/../utils/fpdf/fpdf.php';

const PDFG_MAX_BODY = 3000000;     // 3 MB cap on the incoming JSON payload
const PDFG_MAX_ROWS = 60;          // safety cap for table rows
const PDFG_MAX_COLS = 6;           // safety cap for table columns

// Brand palette (kept in sync with the web UI)
define('PDFG_C_PRIMARY',   [30, 58, 138]);   // deep indigo
define('PDFG_C_ACCENT',    [37, 99, 235]);   // vivid blue
define('PDFG_C_TEXT',      [31, 41, 55]);    // slate-800
define('PDFG_C_MUTED',     [75, 85, 99]);    // slate-500
define('PDFG_C_LIGHT',     [238, 242, 255]); // indigo-50
define('PDFG_C_BORDER',    [203, 213, 225]); // slate-300
define('PDFG_C_SOFT',      [248, 250, 252]); // slate-50

/**
 * Render a study guide as a paginated, professional PDF (FPDF subclass).
 */
class StudyGuidePdf extends FPDF
{
    private $courseTitle = '';
    private $studentName = 'Student';
    private $generatedOn = '';

    public function __construct($courseTitle, $studentName, $generatedOn)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->courseTitle = $courseTitle;
        $this->studentName = $studentName;
        $this->generatedOn = $generatedOn;

        $this->SetCompression(true);
        $this->SetMargins(18, 38, 18);
        $this->SetAutoPageBreak(true, 22);
        $this->AliasNbPages();
        $this->SetCreator(APP_NAME);
        $this->SetAuthor(APP_NAME);
        $this->SetTitle(StudyGuide::pdfText($courseTitle) . ' - AI Study Guide');
    }

    // -- page furniture ---------------------------------------------------

    public function Header()
    {
        // Cover page stays clean; content pages get a slim header
        if ($this->PageNo() <= 1) {
            return;
        }
        $this->SetY(9);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(PDFG_C_PRIMARY[0], PDFG_C_PRIMARY[1], PDFG_C_PRIMARY[2]);
        $this->Cell(0, 4, StudyGuide::pdfText(mb_substr($this->courseTitle, 0, 60)), 0, 0, 'L');

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
        $this->Cell(0, 4, StudyGuide::pdfText(APP_NAME) . ' - AI-Generated Study Guide', 0, 0, 'R');

        $this->SetDrawColor(PDFG_C_BORDER[0], PDFG_C_BORDER[1], PDFG_C_BORDER[2]);
        $this->SetLineWidth(0.3);
        $this->Line(18, 14, $this->w - 18, 14);
        $this->SetY(18);
    }

    public function Footer()
    {
        if ($this->PageNo() <= 1) {
            return;
        }
        $this->SetY(-15);
        $this->SetDrawColor(PDFG_C_BORDER[0], PDFG_C_BORDER[1], PDFG_C_BORDER[2]);
        $this->SetLineWidth(0.3);
        $this->Line(18, $this->GetY(), $this->w - 18, $this->GetY());
        $this->SetY(-13.5);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
        $this->Cell(0, 4, 'Generated with ' . StudyGuide::pdfText(APP_NAME), 0, 0, 'L');
        $this->Cell(0, 4, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'R');
    }

    // -- building blocks --------------------------------------------------

    private function ensureSpace($needed)
    {
        // Keep headings/tables glued to their content (avoid orphaned titles)
        if ($this->GetY() > ($this->h - $this->bMargin) - $needed) {
            $this->AddPage();
        }
    }

    private function sectionTitle($num, $txt)
    {
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(PDFG_C_PRIMARY[0], PDFG_C_PRIMARY[1], PDFG_C_PRIMARY[2]);
        $this->Cell(0, 8, $num . '.  ' . StudyGuide::pdfText($txt), 0, 1);
        $this->SetDrawColor(PDFG_C_ACCENT[0], PDFG_C_ACCENT[1], PDFG_C_ACCENT[2]);
        $this->SetLineWidth(0.4);
        $this->Line(18, $this->GetY(), $this->w - 18, $this->GetY());
        $this->Ln(3.5);
    }

    private function topicTitle($txt)
    {
        $this->Ln(1);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(PDFG_C_TEXT[0], PDFG_C_TEXT[1], PDFG_C_TEXT[2]);
        $this->Write(5, StudyGuide::pdfText($txt));
        $this->Ln(6);
    }

    private function prose($txt)
    {
        $lines = preg_split("/\n{2,}/", StudyGuide::pdfText($txt));
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
        foreach ($lines as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $this->MultiCell(0, 5, $p, 0, 'L');
            $this->Ln(1.5);
        }
    }

    private function bullet($txt)
    {
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
        $this->MultiCell(0, 5, "\x95  " . StudyGuide::pdfText($txt), 0, 'L');
        $this->Ln(0.8);
    }

    private function pairItem($label, $body)
    {
        $label = StudyGuide::pdfText($label);
        $body  = StudyGuide::pdfText($body);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(PDFG_C_TEXT[0], PDFG_C_TEXT[1], PDFG_C_TEXT[2]);
        if ($label !== '') {
            $this->Write(5, $label . ':  ');
        } else {
            $this->Write(5, "\x95  ");
        }
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
        $this->Write(5, $body);
        $this->Ln(6);
    }

    // -- table ------------------------------------------------------------

    private function splitCell($text, $width)
    {
        $text = StudyGuide::pdfText($text);
        $out = [];
        foreach (preg_split("/\n+/", $text) as $seg) {
            $words = preg_split('/\s+/', trim($seg));
            $cur = '';
            foreach ($words as $w) {
                if ($w === '') {
                    continue;
                }
                $test = ($cur === '') ? $w : $cur . ' ' . $w;
                if ($cur === '' || $this->GetStringWidth($test) <= $width) {
                    $cur = $test;
                } else {
                    $out[] = $cur;
                    $cur = $w;
                }
            }
            if ($cur !== '') {
                $out[] = $cur;
            }
        }
        return $out === [] ? [''] : $out;
    }

    private function renderTable($table)
    {
        $rows = array_values(array_filter((array)($table['rows'] ?? []), 'is_array'));
        if (empty($rows)) {
            return;
        }

        $colCount = 1;
        foreach ($rows as $r) {
            $colCount = max($colCount, count($r));
        }
        $colCount = min($colCount, PDFG_MAX_COLS);

        $full = $this->w - $this->lMargin - $this->rMargin;
        $colW = ($full - (($colCount - 1) * 1.5)) / $colCount;

        $x = $this->lMargin;
        $this->SetX($x);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->SetTextColor(PDFG_C_PRIMARY[0], PDFG_C_PRIMARY[1], PDFG_C_PRIMARY[2]);
        $this->Write(5, StudyGuide::pdfText($table['title'] ?? 'Comparison'));
        $this->Ln(4.5);

        $rowH = 10;
        $isHeaderRow = true;
        $rowIndex = 0;
        foreach ($rows as $r) {
            $cells = array_slice(array_values($r), 0, $colCount);
            while (count($cells) < $colCount) {
                $cells[] = '';
            }

            $maxLines = 1;
            $wrapped = [];
            foreach ($cells as $c) {
                $lines = $this->splitCell($c, $colW - 2.4);
                $wrapped[] = $lines;
                $maxLines = max($maxLines, count($lines));
            }
            $height = max($rowH, (int)($maxLines * 4.7) + 3.2);

            // Page-break away from the table mid-way: repeat header row
            if ($this->GetY() + $height > ($this->h - $this->bMargin)) {
                $this->AddPage();
                $isHeaderRow = true;
            }

            $yStart = $this->GetY();
            if ($isHeaderRow) {
                $this->SetFillColor(PDFG_C_PRIMARY[0], PDFG_C_PRIMARY[1], PDFG_C_PRIMARY[2]);
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('Helvetica', 'B', 9);
                $fill = 'DF';
            } elseif ($rowIndex % 2 === 1) {
                $this->SetFillColor(PDFG_C_LIGHT[0], PDFG_C_LIGHT[1], PDFG_C_LIGHT[2]);
                $this->SetTextColor(PDFG_C_TEXT[0], PDFG_C_TEXT[1], PDFG_C_TEXT[2]);
                $this->SetFont('Helvetica', '', 9);
                $fill = 'DF';
            } else {
                $this->SetTextColor(PDFG_C_TEXT[0], PDFG_C_TEXT[1], PDFG_C_TEXT[2]);
                $this->SetFont('Helvetica', '', 9);
                $fill = 'D';
            }
            $this->SetDrawColor(PDFG_C_BORDER[0], PDFG_C_BORDER[1], PDFG_C_BORDER[2]);
            $this->SetLineWidth(0.2);

            foreach ($cells as $ci => $cell) {
                $cx = $x + ($ci * ($colW + 1.5));
                $this->Rect($cx, $yStart, $colW, $height, $fill);
                $lines = $wrapped[$ci];
                $this->SetXY($cx + 1.2, $yStart + 1.6);
                $this->MultiCell($colW - 2.4, 4.7, implode("\n", $lines), 0, 'L');
            }
            $this->SetY($yStart + $height);
            $isHeaderRow = false;
            $rowIndex++;
        }
        $this->Ln(3);

        // Restore standard body styling
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
    }

    // -- content --------------------------------------------------------

    /**
     * Render a normalized guide from the given ordered plan.
     * @param array $active list of [key, label, number] for non-empty sections
     * @param array $guide  normalized study guide
     */
    public function renderSections($active, $guide)
    {
        foreach ($active as $step) {
            $key = $step[0];
            $label = $step[1];
            $num = $step[2];
            $this->AddPage();
            $this->sectionTitle($num, $label);

            switch ($key) {
                case 'overview':
                case 'conclusion':
                    $this->prose($guide[$key]);
                    break;

                case 'topics':
                    foreach ($guide['topics'] as $t) {
                        $this->topicTitle($t['title']);
                        $this->prose($t['content']);
                    }
                    break;

                case 'key_concepts':
                    foreach ($guide['key_concepts'] as $c) {
                        $this->pairItem($c['concept'], $c['explanation']);
                    }
                    break;

                case 'definitions':
                    foreach ($guide['definitions'] as $d) {
                        $this->pairItem($d['term'], $d['definition']);
                    }
                    break;

                case 'examples':
                case 'formulas_processes':
                    foreach ($guide[$key] as $e) {
                        $this->pairItem($e['title'], $e['explanation']);
                    }
                    break;

                case 'comparisons':
                    foreach ($guide['comparisons'] as $t) {
                        $this->renderTable($t);
                    }
                    break;

                default: // learning_objectives, important_points, exam_focus, quick_revision
                    foreach ($guide[$key] as $item) {
                        $this->bullet($item);
                    }
                    break;
            }
        }
    }

    public function addCover($courseTitle, $sourceName, $sourceSize, $sectionList)
    {
        // Deep band
        $this->SetFillColor(PDFG_C_PRIMARY[0], PDFG_C_PRIMARY[1], PDFG_C_PRIMARY[2]);
        $this->Rect(0, 0, $this->w, 70, 'F');
        $this->SetFillColor(PDFG_C_ACCENT[0], PDFG_C_ACCENT[1], PDFG_C_ACCENT[2]);
        $this->Rect(0, 70, $this->w, 2.5, 'F');

        $this->SetY(18);
        $this->SetX($this->lMargin);
        $this->SetFont('Helvetica', '', 12);
        $this->SetTextColor(191, 219, 254);
        $this->Cell(0, 6, strtoupper(StudyGuide::pdfText(APP_NAME)), 0, 1);

        $this->SetX($this->lMargin);
        $this->SetFont('Helvetica', 'B', 30);
        $this->SetTextColor(255, 255, 255);
        $this->MultiCell(0, 13, "AI-Generated\nStudy Guide", 0, 'L');

        $this->SetX($this->lMargin);
        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(203, 213, 225);
        $this->Cell(0, 6, 'Interactive study guide built from your course material', 0, 1);

        // Course meta panel
        $this->SetY(92);
        $this->SetFillColor(PDFG_C_SOFT[0], PDFG_C_SOFT[1], PDFG_C_SOFT[2]);
        $this->SetDrawColor(PDFG_C_BORDER[0], PDFG_C_BORDER[1], PDFG_C_BORDER[2]);
        $this->Rect($this->lMargin, $this->GetY(), $this->w - ($this->lMargin * 2), 66, 'DF');

        $this->SetXY($this->lMargin + 8, $this->GetY() + 8);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
        $this->Cell(60, 4, 'COURSE', 0, 1);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(PDFG_C_TEXT[0], PDFG_C_TEXT[1], PDFG_C_TEXT[2]);
        $this->SetX($this->lMargin + 8);
        $this->MultiCell($this->w - ($this->lMargin * 2) - 16, 6.5, StudyGuide::pdfText(mb_substr($courseTitle, 0, 110)), 0, 'L');

        $this->SetY($this->GetY() + 3);
        $this->SetX($this->lMargin + 8);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
        $this->Cell(0, 4, 'PREPARED FOR:  ' . $this->studentName, 0, 1, 'L');
        $this->SetX($this->lMargin + 8);
        $this->Cell(0, 4, 'GENERATED ON:  ' . $this->generatedOn, 0, 1, 'L');

        $src = '';
        if ($sourceName !== '') {
            $src = $sourceName;
            if ($sourceSize > 0) {
                $src .= '  |  ' . round($sourceSize / 1048576, 1) . ' MB';
            }
        }
        if ($src !== '') {
            $this->SetX($this->lMargin + 8);
            $this->Cell(0, 4, 'SOURCE DOCUMENT:  ' . StudyGuide::pdfText($src), 0, 1, 'L');
        }

        // What's Inside
        if (!empty($sectionList)) {
            $this->SetY($this->GetY() + 10);
            $this->SetX($this->lMargin);
            $this->SetFont('Helvetica', 'B', 11);
            $this->SetTextColor(PDFG_C_PRIMARY[0], PDFG_C_PRIMARY[1], PDFG_C_PRIMARY[2]);
            $this->Cell(0, 6, "What's Inside", 0, 1);
            $this->Ln(1);
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(PDFG_C_MUTED[0], PDFG_C_MUTED[1], PDFG_C_MUTED[2]);
            foreach ($sectionList as $s) {
                if ($this->GetY() > ($this->h - $this->bMargin) - 14) {
                    break;
                }
                $this->Cell(0, 5.2, "\x95  " . StudyGuide::pdfText($s), 0, 1);
            }
            $this->Ln(2);
        }
    }
}

// ----------------------------------------------------------------------
// Request handling
// ----------------------------------------------------------------------

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_logged_in()) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }
    if (strlen($raw) > PDFG_MAX_BODY) {
        throw new Exception('Study guide is too large to convert to PDF.');
    }
    if (!is_array($input) || !verify_csrf_token($input['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token - please reload the page and try again');
    }
    if (!isset($input['summary']) || !is_array($input['summary'])) {
        throw new Exception('No study guide data was received. Please generate a summary first.');
    }

    // Re-normalize everything server-side (never trust the client payload)
    $guide      = StudyGuide::normalize($input['summary']);
    $courseName = is_string($input['course_name'] ?? null) ? trim($input['course_name']) : '';
    $source     = is_array($input['source'] ?? null) ? $input['source'] : [];
    $srcName    = is_string($source['name'] ?? null) ? $source['name'] : '';
    $srcSize    = (int)($source['size'] ?? 0);

    $title = trim($guide['course_title']);
    if ($title === '') {
        $title = $courseName;
    }
    if ($title === '') {
        $title = $srcName;
    }
    if ($title === '') {
        $title = APP_NAME . ' Study Guide';
    }

    $student = isset($_SESSION['user_name']) ? trim((string)$_SESSION['user_name']) : '';
    if ($student === '') {
        $student = 'Student';
    }

    $pdf = new StudyGuidePdf($title, $student, date('F j, Y'));

    // Ordered render plan: [key, display title]
    $plan = [
        ['overview',           'Overview'],
        ['learning_objectives','Learning Objectives'],
        ['topics',             'Main Topics'],
        ['key_concepts',       'Key Concepts'],
        ['definitions',        'Definitions & Terms'],
        ['important_points',   'Important Points'],
        ['examples',           'Examples'],
        ['comparisons',        'Comparisons'],
        ['formulas_processes', 'Formulas & Processes'],
        ['exam_focus',         'Exam Focus'],
        ['quick_revision',     'Quick Revision'],
        ['conclusion',         'Conclusion'],
    ];

    $sectionList = [];
    $active = [];
    $i = 0;
    foreach ($plan as $step) {
        $i++;
        $key = $step[0];
        $label = $step[1];
        if (StudyGuide::isEmptyField($key, $guide[$key])) {
            continue;
        }
        $sectionList[] = $i . '. ' . $label;
        $active[] = [$key, $label, count($active) + 1];
    }

    $pdf->AddPage();
    $pdf->addCover($title, $srcName, $srcSize, $sectionList);

    $pdf->renderSections($active, $guide);

    // Secure download header + tidy response flags
    header('Cache-Control: private, no-store');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    $filename = 'AI_Study_Guide_' . StudyGuide::slug($title) . '.pdf';
    $pdf->Output('D', $filename);
    if (ob_get_level()) {
        ob_end_flush();
    }
    exit;
} catch (Throwable $e) {
    error_log('PDF generation error: ' . $e->getMessage());
    while (ob_get_level()) {
        ob_end_clean();
    }
    $message = $e->getMessage();
    if ($message === '' || preg_match('/(SQL|stack|path)/i', $message)) {
        $message = 'Could not generate the PDF. Please try again.';
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}