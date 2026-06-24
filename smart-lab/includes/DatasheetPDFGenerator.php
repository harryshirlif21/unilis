<?php
namespace SmartLab;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * DatasheetPDFGenerator
 *
 * Generates the Intelligent Laboratory Datasheet and Report Workbook (ILDRW).
 *
 * Page layout:
 *  1. Cover Page
 *  2. Official Laboratory Datasheet
 *  3. Results and Analysis
 *  4. Discussion
 *  5. Conclusion and Recommendations
 *  6. Lecturer Assessment (with QR verification block)
 */
class DatasheetPDFGenerator {

    // ── Student ───────────────────────────────────────────────
    private string $studentName     = '';
    private string $admissionNumber = '';
    private string $course          = '';
    private string $courseCode      = '';
    private string $group           = '';
    private string $academicYear    = '';
    private string $semester        = '';

    // ── Practical / Experiment ────────────────────────────────
    private string $practicalTitle        = '';
    private string $labNumber             = '';
    private string $experimentName        = '';
    private string $experimentDescription = '';
    private string $experimentNumber      = '';
    private string $sessionDate           = '';
    private string $sessionTime           = '';

    // ── Lab / Lecturer ────────────────────────────────────────
    private string $labName      = '';
    private string $lecturerName = '';

    // ── Objectives / Equipment / Procedure ───────────────────
    private array  $objectives       = [];
    private array  $equipment        = [];
    private string $procedureSummary = '';

    // ── Readings / Observations ───────────────────────────────
    private array $readings        = [];
    private array $observationRows = [];

    // ── Pre-filled report answers ─────────────────────────────
    private array $filledAnswers = [];

    // ── Attendance ────────────────────────────────────────────
    private int $attendanceCurrentPractical = 0;
    private int $attendanceAttended         = 0;
    private int $attendanceTotal            = 0;

    // ── Security / metadata ───────────────────────────────────
    private string $qrCodePath     = '';
    private string $signatureHash  = '';
    private string $approvalStatus = 'pending';
    private string $datasheetId    = '';
    private string $blockchainHash = '';
    private string $submittedAt    = '';

    // ── Infrastructure ────────────────────────────────────────
    private string $logoPath;
    private string $outputDir;

    private const TOTAL_PAGES = 6;

    public function __construct(string $logoPath, string $outputDir = '/assets/datasheets/') {
        $this->logoPath  = $logoPath;
        $this->outputDir = rtrim($outputDir, '/') . '/';
    }

    // ──────────────────────────────────────────────────────────
    //  Fluent setters  (all original signatures preserved)
    // ──────────────────────────────────────────────────────────

    public function setStudentDetails(string $name, string $admissionNumber, string $course): self {
        $this->studentName     = $name;
        $this->admissionNumber = $admissionNumber;
        $this->course          = $course;
        return $this;
    }

    public function setPracticalDetails(
        string $title,
        string $labNumber,
        string $experimentName,
        string $experimentDescription,
        string $date = '',
        string $time = ''
    ): self {
        $this->practicalTitle        = $title;
        $this->labNumber             = $labNumber;
        $this->experimentName        = $experimentName;
        $this->experimentDescription = $experimentDescription;
        $this->sessionDate           = $date;
        $this->sessionTime           = $time;
        return $this;
    }

    /**
     * Supply additional fields not covered by the basic setters.
     *
     * Keys: course_code, group, academic_year, semester, experiment_number,
     *       lab_name, lecturer_name, objectives (string[]),
     *       equipment (string[] or ['name'=>…,'specification'=>…][]),
     *       procedure_summary
     */
    public function setExtendedDetails(array $details): self {
        $this->courseCode       = $details['course_code']       ?? '';
        $this->group            = $details['group']             ?? '';
        $this->academicYear     = $details['academic_year']     ?? '';
        $this->semester         = $details['semester']          ?? '';
        $this->experimentNumber = $details['experiment_number'] ?? '';
        $this->labName          = $details['lab_name']          ?? '';
        $this->lecturerName     = $details['lecturer_name']     ?? '';
        $this->objectives       = (array)($details['objectives'] ?? []);
        $this->equipment        = (array)($details['equipment']  ?? []);
        $this->procedureSummary = $details['procedure_summary'] ?? '';
        return $this;
    }

    /** Set attendance statistics for the Laboratory Participation Index. */
    public function setAttendance(int $currentPractical, int $attended, int $total): self {
        $this->attendanceCurrentPractical = $currentPractical;
        $this->attendanceAttended         = $attended;
        $this->attendanceTotal            = $total;
        return $this;
    }

    public function setReadings(array $readings): self {
        $this->readings = $readings;
        return $this;
    }

    public function setQRCode(string $qrCodePath): self {
        $this->qrCodePath = $qrCodePath;
        return $this;
    }

    public function setSignature(string $signatureHash, string $approvalStatus): self {
        $this->signatureHash  = $signatureHash;
        $this->approvalStatus = $approvalStatus;
        return $this;
    }

    public function setDatasheetMeta(string $datasheetId, string $blockchainHash = '', string $submittedAt = ''): self {
        $this->datasheetId    = $datasheetId;
        $this->blockchainHash = $blockchainHash;
        $this->submittedAt    = $submittedAt;
        return $this;
    }

    /**
     * Pre-filled answers for report sections. Accepted keys:
     *   'Results & Analysis'  |  'Student Observations & Calculations'
     *   'Discussion'
     *   'Conclusion & Recommendations'  |  'Conclusion and Recommendations'
     */
    public function setFilledAnswers(array $answers): self {
        $this->filledAnswers = $answers;
        return $this;
    }

    /** Provide already-formatted observations table (for submitted reports). */
    public function setObservationRows(array $rows): self {
        $this->observationRows = $rows;
        return $this;
    }

    // ──────────────────────────────────────────────────────────
    //  Main generate methods
    // ──────────────────────────────────────────────────────────

    private function buildDompdf(): Dompdf {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->buildHtml());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf;
    }

    /** Save PDF to disk and return the web-root-relative path. */
    public function generate(string $filename): string {
        $dompdf = $this->buildDompdf();
        $outputPath = $_SERVER['DOCUMENT_ROOT'] . $this->outputDir . $filename;
        @mkdir(dirname($outputPath), 0755, true);
        file_put_contents($outputPath, $dompdf->output());
        return $this->outputDir . $filename;
    }

    /** Stream the PDF directly to the browser as a download. */
    public function stream(string $downloadFilename): void {
        $dompdf = $this->buildDompdf();
        $dompdf->stream($downloadFilename, ['Attachment' => true]);
    }

    // ──────────────────────────────────────────────────────────
    //  HTML builder
    // ──────────────────────────────────────────────────────────

    private function buildHtml(): string {
        $logoTag = '';
        if ($this->logoPath && file_exists($this->logoPath)) {
            $data    = base64_encode(file_get_contents($this->logoPath));
            $logoTag = '<img src="data:image/jpeg;base64,' . $data . '" style="height:58px;" alt="Logo">';
        }

        $qrLarge = $this->buildQrTag(90);
        $qrSmall = $this->buildQrTag(58);

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>' . $this->css() . '</style></head><body>';
        $html .= $this->pageCover($logoTag, $qrLarge);
        $html .= $this->pageDatasheet($logoTag);
        $html .= $this->pageResultsAnalysis($logoTag);
        $html .= $this->pageDiscussion($logoTag);
        $html .= $this->pageConclusion($logoTag);
        $html .= $this->pageLecturerAssessment($logoTag, $qrSmall);
        $html .= '</body></html>';
        return $html;
    }

    private function buildQrTag(int $sizePx): string {
        $qrFsPath = !empty($this->qrCodePath)
            ? rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . ltrim($this->qrCodePath, '/')
            : '';
        if (!$qrFsPath || !file_exists($qrFsPath)) return '';

        $ext = strtolower(pathinfo($qrFsPath, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $svg = preg_replace('/<\?xml[^?]*\?>/', '', file_get_contents($qrFsPath));
            return '<div style="width:' . $sizePx . 'px;height:' . $sizePx . 'px;overflow:hidden;">' . $svg . '</div>';
        }
        $data = base64_encode(file_get_contents($qrFsPath));
        return '<img src="data:image/png;base64,' . $data . '" width="' . $sizePx . '" height="' . $sizePx . '" alt="QR Code">';
    }

    // ──────────────────────────────────────────────────────────
    //  CSS  — black and white, professional academic
    // ──────────────────────────────────────────────────────────

    private function css(): string {
        return '
@page { margin: 19mm 16mm 21mm 16mm; }
body {
    font-family: "Times New Roman", Times, serif;
    font-size: 10.5pt;
    color: #000;
    line-height: 1.45;
}
.page            { page-break-after: always; }
.page:last-child { page-break-after: auto; }

/* Outer page border */
.page-box {
    border: 1.5pt solid #000;
    padding: 10px 13px 8px 13px;
    min-height: 228mm;
}

/* Running page header */
.pg-hdr {
    display: table; width: 100%;
    border-bottom: 1.5pt solid #000;
    padding-bottom: 5px; margin-bottom: 8px;
}
.pg-hdr-logo  { display: table-cell; width: 62px; vertical-align: middle; }
.pg-hdr-title { display: table-cell; text-align: center; vertical-align: middle; }
.pg-hdr-right { display: table-cell; width: 55px; text-align: right;
                vertical-align: middle; font-size: 8pt; white-space: nowrap; }
.pg-hdr h1 { font-size: 12.5pt; margin: 0 0 1px 0; font-variant: small-caps;
             letter-spacing: 0.4pt; }
.pg-hdr h2 { font-size: 8.5pt; margin: 0; font-weight: normal; }
.pg-hdr-section { font-size: 8pt; font-style: italic; margin-top: 1px; }

/* Running page footer */
.pg-ftr {
    border-top: 0.75pt solid #000;
    margin-top: 8px; padding-top: 3px;
    font-size: 7.5pt; display: table; width: 100%;
}
.pf-l { display: table-cell; vertical-align: middle; }
.pf-r { display: table-cell; text-align: right; vertical-align: middle;
        white-space: nowrap; font-family: "Courier New", Courier, monospace; }

/* Section headings */
.sec-h {
    font-size: 9.5pt; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.3pt; border-top: 1pt solid #000;
    border-bottom: 1pt solid #000;
    padding: 2.5px 0 2.5px 4px; margin: 8px 0 4px 0;
}
.sec-sub {
    font-size: 9.5pt; font-weight: bold; border-bottom: 0.5pt solid #555;
    padding: 2px 0; margin: 6px 0 3px 0;
}

/* Info table */
.info-t { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 3px; }
.info-t td { padding: 2.5px 5px; vertical-align: top; }
.info-t td:first-child { width: 145px; font-weight: bold; white-space: nowrap; }
.info-t td.v { border-bottom: 0.5pt solid #aaa; }

/* Data / readings table */
.data-t { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin: 3px 0 5px 0; }
.data-t th, .data-t td { border: 0.75pt solid #000; padding: 3px 5px; }
.data-t th { font-weight: bold; text-align: center; }
.data-t tr.z td { background: #f0f0f0; }

/* Writing lines */
.wl      { margin: 3px 0; }
.wl-line { border-bottom: 0.5pt solid #bbb; height: 7.8mm; }

/* Filled content block */
.filled {
    border: 0.75pt solid #888; padding: 6px 9px; font-size: 10pt;
    line-height: 1.65; white-space: pre-wrap; min-height: 28mm;
}

/* Cover page */
.cv-outer { border: 2pt solid #000; padding: 14px 18px; margin: 10px 0; }
.cv-title {
    font-size: 14pt; font-weight: bold; text-align: center;
    font-variant: small-caps; letter-spacing: 0.8pt; margin: 8px 0 3px 0;
}
.cv-sub { font-size: 9.5pt; text-align: center; font-style: italic; margin-bottom: 12px; }
.cv-t   { width: 100%; border-collapse: collapse; font-size: 10pt; }
.cv-t td { padding: 3.5px 7px; vertical-align: top; border-bottom: 0.5pt solid #ccc; }
.cv-t td:first-child { width: 170px; font-weight: bold; }
.cv-t tr:last-child td { border-bottom: none; }

/* Attendance tracker */
.att-box { border: 0.75pt solid #000; padding: 5px 8px; margin: 4px 0; font-size: 9pt; }

/* Assessment boxes */
.assess-box { border: 0.75pt solid #000; padding: 5px 8px; min-height: 16mm; margin: 3px 0; }
.sig-ln {
    display: inline-block; border-bottom: 0.75pt solid #000;
    height: 7mm; vertical-align: bottom; margin-right: 6mm;
}

/* Guiding question */
.guide-q {
    font-style: italic; font-size: 9pt; color: #333;
    margin: 2px 0 2px 6px; padding-left: 6px; border-left: 2pt solid #bbb;
}

/* Verification / QR block */
.vrfy { border-top: 1.5pt dashed #888; margin-top: 9px; padding-top: 8px; font-size: 8pt; }
.mono { font-family: "Courier New", Courier, monospace; font-size: 7.5pt; word-break: break-all; }
        ';
    }

    // ──────────────────────────────────────────────────────────
    //  Shared helpers
    // ──────────────────────────────────────────────────────────

    private function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function wlines(int $n): string {
        $out = '<div class="wl">';
        for ($i = 0; $i < $n; $i++) $out .= '<div class="wl-line"></div>';
        return $out . '</div>';
    }

    private function pageHeader(string $logoTag, string $section, int $pageNum): string {
        return '
        <div class="pg-hdr">
            <div class="pg-hdr-logo">' . $logoTag . '</div>
            <div class="pg-hdr-title">
                <h1>JKUAT Smart Lab System</h1>
                <h2>Jomo Kenyatta University of Agriculture and Technology</h2>
                <div class="pg-hdr-section">Intelligent Laboratory Datasheet &amp; Report Workbook &mdash; '
                    . $this->h($section) . '</div>
            </div>
            <div class="pg-hdr-right">Page&nbsp;' . $pageNum . '&nbsp;/&nbsp;' . self::TOTAL_PAGES . '</div>
        </div>';
    }

    private function pageFooter(int $pageNum): string {
        $id    = $this->datasheetId ? $this->h($this->datasheetId) : 'N/A';
        $token = $this->signatureHash
            ? $this->h(substr($this->signatureHash, 0, 20)) . '&hellip;'
            : 'N/A';
        return '
        <div class="pg-ftr">
            <div class="pf-l">Generated by UNILIS SmartLabs &nbsp;|&nbsp; ID:&nbsp;<strong>'
                . $id . '</strong></div>
            <div class="pf-r">Token:&nbsp;' . $token . '&nbsp;|&nbsp;Page&nbsp;'
                . $pageNum . '&nbsp;/&nbsp;' . self::TOTAL_PAGES . '</div>
        </div>';
    }

    private function reportMeta(): string {
        return '
        <table style="width:100%;border-collapse:collapse;border:0.5pt solid #999;
                      margin-bottom:6px;font-size:8.5pt;">
            <tr>
                <td style="padding:2.5px 6px;width:50%;">
                    <strong>Student:</strong> ' . $this->h($this->studentName) .
                    '&nbsp;&nbsp;<strong>Reg:</strong> ' . $this->h($this->admissionNumber) . '
                </td>
                <td style="padding:2.5px 6px;border-left:0.5pt solid #aaa;">
                    <strong>Practical:</strong> ' . $this->h($this->practicalTitle) . '
                </td>
            </tr>
        </table>';
    }

    // ──────────────────────────────────────────────────────────
    //  Page 1  —  Cover Page
    // ──────────────────────────────────────────────────────────

    private function pageCover(string $logoTag, string $qrTag): string {
        $id      = $this->h($this->datasheetId ?: 'N/A');
        $expNum  = $this->h($this->experimentNumber ?: $this->labNumber);
        $expName = $this->h($this->experimentName ?: $this->practicalTitle);
        $course  = $this->courseCode
            ? $this->h($this->courseCode . '  —  ' . $this->course)
            : $this->h($this->course);
        $acYear  = $this->h($this->academicYear ?: date('Y') . '/' . (date('Y') + 1));
        $sem     = $this->h($this->semester ?: '—');
        $group   = $this->h($this->group ?: '—');
        $genDate = date('d F Y,  H:i');
        $bHash   = $this->h($this->blockchainHash ?: 'N/A');
        $sig     = $this->h($this->signatureHash   ?: 'N/A');
        $status  = $this->approvalStatus === 'approved'
            ? '&#10003; APPROVED' : '&#9888; PENDING APPROVAL';

        $html  = '<div class="page"><div class="page-box">';
        $html .= '
        <div style="text-align:center;border-bottom:2pt solid #000;
                    padding-bottom:10px;margin-bottom:12px;">
            ' . $logoTag . '
            <div style="font-size:11pt;font-weight:bold;margin-top:5px;letter-spacing:0.4pt;">
                JOMO KENYATTA UNIVERSITY OF AGRICULTURE AND TECHNOLOGY
            </div>
            <div style="font-size:8.5pt;font-style:italic;">
                Smart Laboratory Management System &mdash; Academic Integrity Verified Document
            </div>
        </div>';

        $html .= '
        <div class="cv-outer">
            <div class="cv-title">Intelligent Laboratory Datasheet<br>and Report Workbook</div>
            <div class="cv-sub">Generated by UNILIS SmartLabs &mdash; Official Academic Document</div>
            <table class="cv-t">
                <tr><td>Datasheet ID:</td>       <td><span class="mono">' . $id . '</span></td></tr>
                <tr><td>Course:</td>             <td>' . $course . '</td></tr>
                <tr><td>Experiment No.:</td>     <td>' . $expNum . '</td></tr>
                <tr><td>Experiment Title:</td>   <td>' . $expName . '</td></tr>
                <tr><td>Student Name:</td>       <td>' . $this->h($this->studentName) . '</td></tr>
                <tr><td>Registration No.:</td>   <td>' . $this->h($this->admissionNumber) . '</td></tr>
                <tr><td>Programme:</td>          <td>' . $this->h($this->course) . '</td></tr>
                <tr><td>Group / Section:</td>    <td>' . $group . '</td></tr>
                <tr><td>Academic Year:</td>      <td>' . $acYear . '</td></tr>
                <tr><td>Semester:</td>           <td>' . $sem . '</td></tr>
                <tr><td>Date Generated:</td>     <td>' . $genDate . '</td></tr>
                <tr><td>Status:</td>             <td><strong>' . $status . '</strong></td></tr>
            </table>
        </div>';

        $html .= '
        <div style="display:table;width:100%;margin-top:10px;">
            <div style="display:table-cell;width:100px;vertical-align:middle;text-align:center;">
                ' . ($qrTag ?: '<div style="width:90px;height:90px;border:1pt dashed #999;
                                     display:inline-block;"></div>') . '
                <div style="font-size:7pt;margin-top:3px;">Scan to verify</div>
            </div>
            <div style="display:table-cell;vertical-align:middle;padding-left:10px;font-size:8pt;">
                <div style="font-weight:bold;margin-bottom:4px;">Document Verification Information</div>
                <div><strong>Blockchain Hash:</strong><br>
                    <span class="mono">' . $bHash . '</span></div>
                <div style="margin-top:3px;"><strong>Digital Signature:</strong><br>
                    <span class="mono">' . $sig . '</span></div>
                <div style="margin-top:5px;font-size:7.5pt;font-style:italic;">
                    This document is tamper-evident. Scan the QR code to verify online.<br>
                    Any modification will invalidate the embedded digital signature.
                </div>
            </div>
        </div>';

        $html .= $this->pageFooter(1);
        $html .= '</div></div>';
        return $html;
    }

    // ──────────────────────────────────────────────────────────
    //  Page 2  —  Official Laboratory Datasheet
    // ──────────────────────────────────────────────────────────

    private function pageDatasheet(string $logoTag): string {
        $dateDisplay = $this->sessionDate
            ? $this->h($this->sessionDate . ($this->sessionTime ? ',  ' . $this->sessionTime : ''))
            : date('d M Y');

        $html  = '<div class="page"><div class="page-box">';
        $html .= $this->pageHeader($logoTag, 'Official Laboratory Datasheet', 2);

        // Two-column: Student info | Practical info
        $html .= '<div style="display:table;width:100%;">';

        $html .= '<div style="display:table-cell;width:49%;vertical-align:top;padding-right:7px;">';
        $html .= '<div class="sec-h">Student Information</div>';
        $html .= '<table class="info-t">
            <tr><td>Full Name:</td>      <td class="v">' . $this->h($this->studentName) . '</td></tr>
            <tr><td>Reg. Number:</td>    <td class="v">' . $this->h($this->admissionNumber) . '</td></tr>
            <tr><td>Programme:</td>      <td class="v">' . $this->h($this->course) . '</td></tr>
            <tr><td>Group:</td>          <td class="v">' . $this->h($this->group ?: '—') . '</td></tr>
            <tr><td>Academic Year:</td>  <td class="v">' . $this->h($this->academicYear ?: '—') . '</td></tr>
            <tr><td>Semester:</td>       <td class="v">' . $this->h($this->semester ?: '—') . '</td></tr>
        </table></div>';

        $html .= '<div style="display:table-cell;width:49%;vertical-align:top;
                               padding-left:7px;border-left:0.5pt solid #aaa;">';
        $html .= '<div class="sec-h">Practical Information</div>';
        $html .= '<table class="info-t">
            <tr><td>Practical Title:</td> <td class="v">' . $this->h($this->practicalTitle) . '</td></tr>
            <tr><td>Course Code:</td>     <td class="v">' . $this->h($this->courseCode ?: '—') . '</td></tr>
            <tr><td>Experiment No.:</td>  <td class="v">'
                . $this->h($this->experimentNumber ?: $this->labNumber ?: '—') . '</td></tr>
            <tr><td>Date / Time:</td>     <td class="v">' . $dateDisplay . '</td></tr>
            <tr><td>Lab / Room:</td>      <td class="v">'
                . $this->h($this->labName ?: $this->labNumber ?: '—') . '</td></tr>
            <tr><td>Lecturer:</td>        <td class="v">' . $this->h($this->lecturerName ?: '—') . '</td></tr>
        </table></div>';

        $html .= '</div>';

        // Objectives
        $html .= '<div class="sec-h">Experiment Objectives</div>';
        if (!empty($this->objectives)) {
            $html .= '<ol style="margin:2px 0 3px 16px;font-size:9.5pt;">';
            foreach ($this->objectives as $o) $html .= '<li>' . $this->h((string)$o) . '</li>';
            $html .= '</ol>';
        } elseif ($this->experimentDescription) {
            $html .= '<div style="font-size:9.5pt;padding:2px 4px;">'
                . nl2br($this->h($this->experimentDescription)) . '</div>';
        } else {
            $html .= $this->wlines(2);
        }

        // Equipment
        $html .= '<div class="sec-h">Equipment and Materials Used</div>';
        $html .= '<table class="data-t"><thead><tr>
            <th style="width:28px;">#</th>
            <th>Item / Equipment</th>
            <th style="width:120px;">Quantity / Specification</th>
        </tr></thead><tbody>';
        if (!empty($this->equipment)) {
            foreach ($this->equipment as $i => $eq) {
                $name  = is_array($eq) ? ($eq['name'] ?? (string)$eq) : (string)$eq;
                $spec  = is_array($eq) ? ($eq['specification'] ?? '') : '';
                $class = $i % 2 === 1 ? ' class="z"' : '';
                $html .= '<tr' . $class . '><td style="text-align:center;">' . ($i + 1) . '</td>
                    <td>' . $this->h($name) . '</td>
                    <td>' . $this->h($spec) . '</td></tr>';
            }
        } else {
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr' . ($i % 2 === 0 ? ' class="z"' : '') . '>
                    <td style="text-align:center;">' . $i . '</td>
                    <td>&nbsp;</td><td>&nbsp;</td></tr>';
            }
        }
        $html .= '</tbody></table>';

        // Procedure
        $html .= '<div class="sec-h">Procedure Summary</div>';
        if ($this->procedureSummary) {
            $html .= '<div style="font-size:9.5pt;padding:2px 4px;">'
                . nl2br($this->h($this->procedureSummary)) . '</div>';
        } else {
            $html .= $this->wlines(3);
        }

        // Readings
        $html .= $this->buildReadingsSection();

        // Attendance
        $html .= $this->buildAttendanceSection();

        $html .= $this->pageFooter(2);
        $html .= '</div></div>';
        return $html;
    }

    private function buildReadingsSection(): string {
        $html = '<div class="sec-h">Observations and Measurements</div>';

        if (!empty($this->observationRows)) {
            $first = reset($this->observationRows);
            $ths   = '';
            if (is_array($first)) {
                foreach (array_keys($first) as $col) $ths .= '<th>' . $this->h($col) . '</th>';
            }
            $rows = '';
            foreach ($this->observationRows as $i => $row) {
                if (!is_array($row)) continue;
                $rows .= '<tr' . ($i % 2 === 1 ? ' class="z"' : '') . '>';
                foreach ($row as $v) $rows .= '<td>' . $this->h((string)$v) . '</td>';
                $rows .= '</tr>';
            }
            return $html
                . '<table class="data-t"><thead><tr>' . $ths . '</tr></thead><tbody>'
                . ($rows ?: '<tr><td colspan="4" style="text-align:center;font-style:italic;">No data</td></tr>')
                . '</tbody></table>';
        }

        $rows = '';
        foreach ($this->readings as $i => $r) {
            $rows .= '<tr' . ($i % 2 === 1 ? ' class="z"' : '') . '>
                <td style="text-align:center;width:36px;">' . $this->h((string)($r['trial'] ?? '')) . '</td>
                <td>' . $this->h($r['measurement'] ?? '') . '</td>
                <td style="text-align:center;width:55px;">' . $this->h($r['units'] ?? '') . '</td>
                <td>' . $this->h($r['observation'] ?? '') . '</td>
            </tr>';
        }
        if (empty($rows)) {
            for ($i = 1; $i <= 5; $i++) {
                $rows .= '<tr' . ($i % 2 === 0 ? ' class="z"' : '') . '>
                    <td style="text-align:center;">' . $i . '</td>
                    <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
            }
        }
        return $html
            . '<table class="data-t"><thead><tr>
                <th style="width:36px;">Trial</th>
                <th>Measurement / Parameter</th>
                <th style="width:55px;">Units</th>
                <th>Observation</th>
            </tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private function buildAttendanceSection(): string {
        $current  = max(1, $this->attendanceCurrentPractical);
        $attended = $this->attendanceAttended;
        $total    = $this->attendanceTotal > 0 ? $this->attendanceTotal : 12;
        $pct      = $total > 0 ? round(($attended / $total) * 100) : 0;
        $status   = $pct >= 75 ? 'Satisfactory' : ($pct >= 50 ? 'At Risk' : 'Unsatisfactory');
        $elig     = $pct >= 75 ? 'ELIGIBLE for examination' : 'NOT ELIGIBLE — attendance below 75%';

        $tracker = '';
        for ($i = 1; $i <= $total; $i++) {
            if ($i < $current) {
                $tracker .= '<span style="display:inline-block;width:9px;height:9px;
                    border:0.75pt solid #000;background:#000;margin:1px;"></span>';
            } elseif ($i === $current) {
                $tracker .= '<span style="display:inline-block;width:9px;height:9px;
                    border:2pt solid #000;margin:1px;"></span>';
            } else {
                $tracker .= '<span style="display:inline-block;width:9px;height:9px;
                    border:0.5pt solid #aaa;margin:1px;"></span>';
            }
        }

        return '
        <div class="sec-h">Laboratory Participation Index</div>
        <div class="att-box">
            <table style="width:100%;border-collapse:collapse;font-size:9pt;">
                <tr>
                    <td style="width:52%;padding:2px 4px;vertical-align:top;">
                        <strong>Current Practical No.:</strong> ' . $current . '<br>
                        <strong>Practicals Attended:</strong> ' . $attended . ' of ' . $total . '<br>
                        <strong>Attendance Percentage:</strong> ' . $pct . '%<br>
                        <strong>Attendance Status:</strong> ' . $status . '<br>
                        <strong>Eligibility Status:</strong> ' . $elig . '
                    </td>
                    <td style="width:48%;padding:2px 4px;text-align:center;vertical-align:middle;">
                        <div style="font-size:8pt;margin-bottom:3px;font-weight:bold;">
                            Attendance Tracker
                        </div>
                        <div style="line-height:1.6;">' . $tracker . '</div>
                        <div style="font-size:7.5pt;margin-top:3px;">
                            &#9632;&nbsp;Attended &nbsp; &#9633;&nbsp;Current / Upcoming
                        </div>
                    </td>
                </tr>
            </table>
        </div>';
    }

    // ──────────────────────────────────────────────────────────
    //  Page 3  —  Results and Analysis
    // ──────────────────────────────────────────────────────────

    private function pageResultsAnalysis(string $logoTag): string {
        $html  = '<div class="page"><div class="page-box">';
        $html .= $this->pageHeader($logoTag, 'Results and Analysis', 3);
        $html .= $this->reportMeta();

        $html .= '<div class="sec-h">Experiment Objectives (Reference)</div>';
        if (!empty($this->objectives)) {
            $html .= '<ol style="margin:2px 0 3px 16px;font-size:9.5pt;">';
            foreach ($this->objectives as $o) $html .= '<li>' . $this->h((string)$o) . '</li>';
            $html .= '</ol>';
        } elseif ($this->experimentDescription) {
            $html .= '<div style="font-size:9pt;padding:2px 4px;">'
                . nl2br($this->h($this->experimentDescription)) . '</div>';
        } else {
            $html .= '<div style="font-size:9pt;font-style:italic;padding:2px 4px;">(Refer to page 2)</div>';
        }

        $html .= '<div class="sec-h">Recorded Measurements and Observations</div>';
        $html .= $this->compactReadingsTable();

        $analysis = $this->filledAnswers['Results & Analysis']
            ?? $this->filledAnswers['Student Observations & Calculations']
            ?? '';

        $html .= '<div class="sec-h">Results and Analysis
            <span style="font-weight:normal;font-size:8.5pt;">&nbsp;— Complete this section</span>
        </div>';
        $html .= '<div class="guide-q">Present your calculated results, interpret your findings,
            and relate them to the experiment objectives. Show all calculations clearly.</div>';

        if ($analysis) {
            $html .= '<div class="filled">' . $this->h($analysis) . '</div>';
        } else {
            $html .= $this->wlines(17);
        }

        $html .= $this->pageFooter(3);
        $html .= '</div></div>';
        return $html;
    }

    private function compactReadingsTable(): string {
        if (!empty($this->observationRows)) {
            $first = reset($this->observationRows);
            $ths   = '';
            if (is_array($first)) {
                foreach (array_keys($first) as $col) $ths .= '<th>' . $this->h($col) . '</th>';
            }
            $rows = '';
            foreach ($this->observationRows as $i => $row) {
                if (!is_array($row)) continue;
                $rows .= '<tr' . ($i % 2 === 1 ? ' class="z"' : '') . '>';
                foreach ($row as $v) $rows .= '<td>' . $this->h((string)$v) . '</td>';
                $rows .= '</tr>';
            }
            return '<table class="data-t" style="font-size:9pt;"><thead><tr>' . $ths . '</tr></thead><tbody>'
                . ($rows ?: '<tr><td colspan="4" style="text-align:center;font-style:italic;">No data recorded</td></tr>')
                . '</tbody></table>';
        }
        if (!empty($this->readings)) {
            $rows = '';
            foreach ($this->readings as $i => $r) {
                $rows .= '<tr' . ($i % 2 === 1 ? ' class="z"' : '') . '>
                    <td style="text-align:center;">' . $this->h((string)($r['trial'] ?? '')) . '</td>
                    <td>' . $this->h($r['measurement'] ?? '') . '</td>
                    <td style="text-align:center;">' . $this->h($r['units'] ?? '') . '</td>
                    <td>' . $this->h($r['observation'] ?? '') . '</td>
                </tr>';
            }
            return '<table class="data-t" style="font-size:9pt;"><thead><tr>
                <th style="width:36px;">Trial</th><th>Measurement / Parameter</th>
                <th style="width:55px;">Units</th><th>Observation</th>
            </tr></thead><tbody>' . $rows . '</tbody></table>';
        }
        return '<div style="font-size:9pt;font-style:italic;padding:3px 6px;">'
            . '(Refer to measurements recorded on page 2)</div>';
    }

    // ──────────────────────────────────────────────────────────
    //  Page 4  —  Discussion
    // ──────────────────────────────────────────────────────────

    private function pageDiscussion(string $logoTag): string {
        $html  = '<div class="page"><div class="page-box">';
        $html .= $this->pageHeader($logoTag, 'Discussion', 4);
        $html .= $this->reportMeta();

        $content = $this->filledAnswers['Discussion'] ?? '';

        $html .= '<div class="sec-h">Discussion
            <span style="font-weight:normal;font-size:8.5pt;">&nbsp;— Complete this section</span>
        </div>';

        if ($content) {
            $html .= '<div class="filled">' . $this->h($content) . '</div>';
        } else {
            $sections = [
                'Interpretation of Results' =>
                    'What do your results indicate? Do they support or contradict the expected outcomes?',
                'Comparison of Expected vs. Actual Results' =>
                    'How closely did your results match theoretical values? Quantify differences where possible.',
                'Sources of Error and Limitations' =>
                    'Identify procedural, instrumental, or human errors that may have affected your results.',
                'Implications and Significance' =>
                    'What is the broader significance of these findings in relation to theory or real-world application?',
            ];
            foreach ($sections as $heading => $guide) {
                $html .= '<div class="sec-sub">' . $heading . '</div>';
                $html .= '<div class="guide-q">' . $guide . '</div>';
                $html .= $this->wlines(5);
            }
        }

        $html .= $this->pageFooter(4);
        $html .= '</div></div>';
        return $html;
    }

    // ──────────────────────────────────────────────────────────
    //  Page 5  —  Conclusion and Recommendations
    // ──────────────────────────────────────────────────────────

    private function pageConclusion(string $logoTag): string {
        $html  = '<div class="page"><div class="page-box">';
        $html .= $this->pageHeader($logoTag, 'Conclusion and Recommendations', 5);
        $html .= $this->reportMeta();

        $content = $this->filledAnswers['Conclusion & Recommendations']
            ?? $this->filledAnswers['Conclusion and Recommendations']
            ?? '';

        if ($content) {
            $html .= '<div class="sec-h">Conclusion and Recommendations</div>';
            $html .= '<div class="filled">' . $this->h($content) . '</div>';
        } else {
            $html .= '<div class="sec-h">Conclusion
                <span style="font-weight:normal;font-size:8.5pt;">&nbsp;— Complete this section</span>
            </div>';
            $html .= '<div class="guide-q">Summarise the key findings. Were the objectives achieved?
                State clearly whether your hypothesis was supported by the data.</div>';
            $html .= $this->wlines(7);

            $html .= '<div class="sec-h">Recommendations</div>';
            $html .= '<div class="guide-q">Suggest improvements to the procedure, equipment,
                or methodology that would improve accuracy or extend the findings.</div>';
            $html .= $this->wlines(5);

            $html .= '<div class="sec-h">Lessons Learned</div>';
            $html .= '<div class="guide-q">What new knowledge, skills, or insights did you gain
                from this practical session?</div>';
            $html .= $this->wlines(4);

            $html .= '<div class="sec-h">Additional Remarks</div>';
            $html .= $this->wlines(3);
        }

        $html .= $this->pageFooter(5);
        $html .= '</div></div>';
        return $html;
    }

    // ──────────────────────────────────────────────────────────
    //  Page 6  —  Lecturer Assessment  +  Verification Footer
    // ──────────────────────────────────────────────────────────

    private function pageLecturerAssessment(string $logoTag, string $qrSmall): string {
        $html  = '<div class="page"><div class="page-box">';
        $html .= $this->pageHeader($logoTag, 'Lecturer Assessment', 6);

        // Student declaration
        $html .= '<div class="sec-h">Student Declaration</div>';
        $html .= '<div style="font-size:9pt;font-style:italic;border:0.5pt solid #aaa;padding:5px 8px;">
            I declare that the work presented in this laboratory workbook is my own, completed
            during the scheduled practical session, and has not been plagiarised from any source.
        </div>';
        $html .= '<table style="width:100%;margin-top:6px;font-size:9pt;border-collapse:collapse;">
            <tr>
                <td style="width:55%;">Student Signature:&nbsp;
                    <span class="sig-ln" style="width:55mm;"></span>
                </td>
                <td>Date:&nbsp;<span class="sig-ln" style="width:40mm;"></span></td>
            </tr>
        </table>';

        // Lecturer comments
        $html .= '<div class="sec-h" style="margin-top:9px;">Lecturer Comments</div>';
        $html .= '<div class="assess-box"></div>';

        // Grading table
        $html .= '<div class="sec-h">Grading and Marks</div>';
        $html .= '<table class="data-t" style="font-size:9.5pt;">
            <thead><tr>
                <th>Section</th>
                <th style="width:72px;">Maximum&nbsp;Marks</th>
                <th style="width:72px;">Marks&nbsp;Awarded</th>
            </tr></thead>
            <tbody>
                <tr>          <td>Observations and Measurements (Page 2)</td>
                              <td style="text-align:center;">25</td><td></td></tr>
                <tr class="z"><td>Results and Analysis (Page 3)</td>
                              <td style="text-align:center;">25</td><td></td></tr>
                <tr>          <td>Discussion (Page 4)</td>
                              <td style="text-align:center;">25</td><td></td></tr>
                <tr class="z"><td>Conclusion and Recommendations (Page 5)</td>
                              <td style="text-align:center;">15</td><td></td></tr>
                <tr>          <td>Presentation and Neatness</td>
                              <td style="text-align:center;">10</td><td></td></tr>
                <tr class="z" style="font-weight:bold;">
                    <td>TOTAL</td><td style="text-align:center;">100</td><td></td>
                </tr>
            </tbody>
        </table>';

        // Grade + signature row
        $html .= '<table style="width:100%;margin-top:8px;font-size:9.5pt;border-collapse:collapse;">
            <tr>
                <td style="width:30%;vertical-align:top;">
                    <strong>Letter Grade:</strong><br>
                    <div style="border:0.75pt solid #000;width:22mm;height:14mm;
                                display:inline-block;margin-top:3px;"></div>
                </td>
                <td style="width:38%;vertical-align:top;">
                    <strong>Lecturer Signature:</strong><br>
                    <span class="sig-ln" style="width:52mm;margin-top:9mm;display:block;"></span>
                </td>
                <td style="width:32%;vertical-align:top;">
                    <strong>Date Marked:</strong><br>
                    <span class="sig-ln" style="width:42mm;margin-top:9mm;display:block;"></span>
                </td>
            </tr>
        </table>';

        // Verification block
        $id    = $this->h($this->datasheetId    ?: 'N/A');
        $bHash = $this->h($this->blockchainHash ?: 'N/A');
        $sig   = $this->h($this->signatureHash  ?: 'N/A');
        $genAt = date('M j, Y  H:i:s');
        $subAt = $this->submittedAt
            ? date('M j, Y  H:i:s', strtotime($this->submittedAt))
            : $genAt;
        $qrPlaceholder = '<div style="width:58px;height:58px;border:1pt dashed #aaa;
                               display:inline-block;"></div>';

        $html .= '
        <div class="vrfy">
            <div style="display:table;width:100%;">
                <div style="display:table-cell;width:80px;vertical-align:middle;text-align:center;">
                    ' . ($qrSmall ?: $qrPlaceholder) . '
                    <div style="font-size:7pt;margin-top:2px;">Scan to verify</div>
                </div>
                <div style="display:table-cell;vertical-align:middle;
                            padding-left:10px;font-size:8pt;">
                    <div>Scan this QR code to verify the authenticity of this datasheet online.</div>
                    <div style="margin-top:3px;">
                        <strong>Datasheet ID:</strong>&nbsp;
                        <span class="mono">' . $id . '</span>
                    </div>
                    <div style="margin-top:2px;">
                        <strong>Blockchain Hash:</strong>&nbsp;
                        <span class="mono">' . $bHash . '</span>
                    </div>
                    <div style="margin-top:2px;">
                        <strong>Digital Signature:</strong>&nbsp;
                        <span class="mono">' . $sig . '</span>
                    </div>
                </div>
            </div>
            <div style="border-top:0.5pt dashed #aaa;margin-top:7px;padding-top:4px;
                        font-size:7.5pt;text-align:center;">
                Generated by UNILIS SmartLabs &bull; ' . $genAt . '&nbsp;&nbsp;|&nbsp;&nbsp;
                Datasheet ID:&nbsp;' . $id . '&nbsp;&bull;&nbsp;Submitted:&nbsp;' . $subAt . '
            </div>
        </div>';

        $html .= $this->pageFooter(6);
        $html .= '</div></div>';
        return $html;
    }
}