<?php
namespace SmartLab;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * DatasheetPDFGenerator
 *
 * Generates a multi-page lab datasheet PDF using Dompdf.
 *
 * Page layout:
 *  1. Cover / data page  - header, student info, practical details, readings table
 *  2+. Ruled answer pages - horizontal writing lines, section title, student name,
 *      supervisor signature block.
 */
class DatasheetPDFGenerator {

    private string $studentName    = '';
    private string $admissionNumber = '';
    private string $course         = '';
    private string $practicalTitle = '';
    private string $labNumber      = '';
    private string $experimentName = '';
    private string $experimentDescription = '';
    private array  $readings       = [];
    private string $qrCodePath     = '';
    private string $signatureHash  = '';
    private string $approvalStatus = 'pending';
    private string $logoPath;
    private string $outputDir;

    /** Ruled answer pages appended after the data page */
    private array $answerPages = [
        'Student Observations & Calculations',
        'Results & Analysis',
        'Conclusion & Recommendations',
    ];

    public function __construct(string $logoPath, string $outputDir = '/assets/datasheets/') {
        $this->logoPath  = $logoPath;
        $this->outputDir = rtrim($outputDir, '/') . '/';
    }

    public function setStudentDetails(string $name, string $admissionNumber, string $course): self {
        $this->studentName      = $name;
        $this->admissionNumber  = $admissionNumber;
        $this->course           = $course;
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

    /* ------------------------------------------------------------------ */
    /*  Main generate method                                                */
    /* ------------------------------------------------------------------ */

    public function generate(string $filename): string {
        $html = $this->buildHtml();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $outputPath = $_SERVER['DOCUMENT_ROOT'] . $this->outputDir . $filename;
        @mkdir(dirname($outputPath), 0755, true);
        file_put_contents($outputPath, $dompdf->output());

        return $this->outputDir . $filename;
    }

    /* ------------------------------------------------------------------ */
    /*  HTML builder                                                        */
    /* ------------------------------------------------------------------ */

    private function buildHtml(): string {
        $logoTag = '';
        if ($this->logoPath && file_exists($this->logoPath)) {
            $logoData = base64_encode(file_get_contents($this->logoPath));
            $logoTag  = '<img src="data:image/jpeg;base64,' . $logoData
                      . '" style="height:55px;" alt="JKUAT Logo">';
        }

        $qrTag = '';
        // qrCodePath is a web-root-relative path like /assets/qrcodes/xxx.svg
        $qrFsPath = !empty($this->qrCodePath)
            ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($this->qrCodePath, '/')
            : '';
        if ($qrFsPath && file_exists($qrFsPath)) {
            $ext = strtolower(pathinfo($qrFsPath, PATHINFO_EXTENSION));
            if ($ext === 'svg') {
                // Inline SVG so dompdf renders it natively
                $svgContent = file_get_contents($qrFsPath);
                // Remove XML declaration if present
                $svgContent = preg_replace('/<\?xml[^?]*\?>/', '', $svgContent);
                $qrTag = '<div style="width:60px;height:60px;overflow:hidden;">' . $svgContent . '</div>';
            } else {
                $qrData = base64_encode(file_get_contents($qrFsPath));
                $qrTag  = '<img src="data:image/png;base64,' . $qrData
                        . '" style="height:55px;width:55px;" alt="QR Code">';
            }
        }

        $approvalBadge = $this->approvalStatus === 'approved'
            ? '<span style="color:#155724;background:#d4edda;padding:2px 10px;border-radius:10px;font-weight:bold;">&#10003; APPROVED</span>'
            : '<span style="color:#856404;background:#fff3cd;padding:2px 10px;border-radius:10px;font-weight:bold;">&#9888; PENDING</span>';

        $sigShort = !empty($this->signatureHash)
            ? htmlspecialchars(substr($this->signatureHash, 0, 20) . '...')
            : 'N/A';

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>' . $this->css() . '</style></head><body>';

        /* ---- Page 1: Data page ---- */
        $html .= '<div class="page">';
        $html .= $this->headerSection($logoTag, $approvalBadge);
        $html .= $this->practicalDetailsSection();
        $html .= $this->studentDetailsSection();
        $html .= $this->experimentSection();
        $html .= $this->readingsTableSection();
        $html .= $this->footerSection($qrTag, $sigShort);
        $html .= '</div>';

        /* ---- Ruled answer pages ---- */
        foreach ($this->answerPages as $pageTitle) {
            $html .= '<div class="page">';
            $html .= $this->answerPageHeader($pageTitle);
            $html .= $this->ruledLines();
            $html .= $this->answerPageFooter();
            $html .= '</div>';
        }

        $html .= '</body></html>';
        return $html;
    }

    /* ------------------------------------------------------------------ */
    /*  CSS                                                                 */
    /* ------------------------------------------------------------------ */

    private function css(): string {
        return '
            @page { margin: 18mm 15mm 18mm 15mm; }
            body  { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #222; }
            .page { page-break-after: always; }

            /* Header */
            .header-wrap {
                display: table; width: 100%;
                border-bottom: 2px solid #003366;
                padding-bottom: 6px; margin-bottom: 10px;
            }
            .header-logo  { display: table-cell; width: 65px; vertical-align: middle; }
            .header-title { display: table-cell; text-align: center; vertical-align: middle; }
            .header-badge { display: table-cell; width: 115px; text-align: right; vertical-align: middle; font-size:9pt; }
            h1 { color:#003366; font-size:14pt; margin:0 0 2px 0; }
            h2 { color:#003366; font-size:10pt; margin:0; font-weight:normal; }

            /* Section heading */
            .section-heading {
                background:#e6f0ff; color:#003366; font-weight:bold;
                font-size:10pt; padding:4px 8px; margin:10px 0 4px 0;
                border-left:4px solid #003366;
            }

            /* Info tables */
            .info-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
            .info-table td { padding:3px 6px; font-size:9.5pt; }
            .info-table td:first-child { width:140px; font-weight:bold; color:#444; }

            /* Readings table */
            .readings-table { width:100%; border-collapse:collapse; margin-top:4px; }
            .readings-table th {
                background:#c8dcff; color:#003366;
                border:1px solid #aaa; padding:4px 6px;
                font-size:9pt; text-align:center;
            }
            .readings-table td { border:1px solid #aaa; padding:4px 6px; font-size:9pt; }
            .readings-table tr:nth-child(even) td { background:#f5f8ff; }

            /* Footer */
            .footer-wrap {
                display:table; width:100%;
                border-top:1px solid #ccc; margin-top:10px;
                padding-top:6px; font-size:8pt; color:#555;
            }
            .footer-left  { display:table-cell; width:70px; vertical-align:middle; }
            .footer-right { display:table-cell; vertical-align:middle; }

            /* ---- Answer pages ---- */
            .answer-header {
                border-bottom:2px solid #003366;
                padding-bottom:6px; margin-bottom:10px;
            }
            .answer-header h2 {
                color:#003366; font-size:13pt; font-weight:bold; margin:0 0 6px 0;
            }
            .meta-table { width:100%; border-collapse:collapse; }
            .meta-table td { font-size:9pt; padding:3px 4px 3px 0; vertical-align:bottom; }
            .meta-label  { width:130px; font-weight:bold; color:#444; white-space:nowrap; }
            .meta-line   { border-bottom:1px solid #333; min-width:160px; }

            /* Ruled lines */
            .ruled-lines { margin-top:6px; }
            .ruled-line  { border-bottom:1px solid #b0b8cc; height:8.5mm; }

            /* Supervisor block */
            .sig-block {
                margin-top:10px; border-top:1px dashed #aaa;
                padding-top:8px; font-size:8.5pt; color:#444;
            }
            .sig-inner { display:table; width:100%; }
            .sig-cell  { display:table-cell; padding-right:20px; white-space:nowrap; }
            .sig-line2 {
                display:inline-block; border-bottom:1px solid #333;
                vertical-align:bottom;
            }
            .page-label {
                font-size:7.5pt; color:#888; text-align:right; margin-top:4px;
            }
        ';
    }

    /* ------------------------------------------------------------------ */
    /*  Data page sections                                                  */
    /* ------------------------------------------------------------------ */

    private function headerSection(string $logoTag, string $approvalBadge): string {
        return '
        <div class="header-wrap">
            <div class="header-logo">' . $logoTag . '</div>
            <div class="header-title">
                <h1>JKUAT SMART LAB SYSTEM</h1>
                <h2>Jomo Kenyatta University of Agriculture and Technology</h2>
                <strong style="font-size:11pt;color:#003366;">LAB DATASHEET</strong>
            </div>
            <div class="header-badge">' . $approvalBadge . '</div>
        </div>';
    }

    private function practicalDetailsSection(): string {
        return '
        <div class="section-heading">PRACTICAL DETAILS</div>
        <table class="info-table">
            <tr><td>Practical Name:</td><td>' . htmlspecialchars($this->practicalTitle) . '</td></tr>
            <tr><td>Lab Number:</td><td>' . htmlspecialchars($this->labNumber) . '</td></tr>
            <tr><td>Date Generated:</td><td>' . date('d M Y, H:i') . '</td></tr>
        </table>';
    }

    private function studentDetailsSection(): string {
        return '
        <div class="section-heading">STUDENT DETAILS</div>
        <table class="info-table">
            <tr><td>Full Name:</td><td>' . htmlspecialchars($this->studentName) . '</td></tr>
            <tr><td>Admission Number:</td><td>' . htmlspecialchars($this->admissionNumber) . '</td></tr>
            <tr><td>Course / Programme:</td><td>' . htmlspecialchars($this->course) . '</td></tr>
        </table>';
    }

    private function experimentSection(): string {
        return '
        <div class="section-heading">EXPERIMENT DESCRIPTION</div>
        <div style="padding:4px 8px;">
            <strong>' . htmlspecialchars($this->experimentName) . '</strong><br>
            <span style="color:#555;font-size:9pt;">'
                . nl2br(htmlspecialchars($this->experimentDescription))
            . '</span>
        </div>';
    }

    private function readingsTableSection(): string {
        $rows = '';
        foreach ($this->readings as $r) {
            $rows .= '<tr>
                <td style="text-align:center;">' . htmlspecialchars((string)($r['trial'] ?? '')) . '</td>
                <td>' . htmlspecialchars($r['measurement'] ?? '') . '</td>
                <td style="text-align:center;">' . htmlspecialchars($r['units'] ?? '') . '</td>
                <td>' . htmlspecialchars($r['observation'] ?? '') . '</td>
            </tr>';
        }
        if (empty($rows)) {
            $rows = '<tr><td colspan="4" style="text-align:center;color:#888;font-style:italic;">
                No readings template defined for this practical.</td></tr>';
        }
        return '
        <div class="section-heading">READINGS TABLE</div>
        <table class="readings-table">
            <thead><tr>
                <th style="width:50px;">Trial</th>
                <th>Measurement / Parameter</th>
                <th style="width:70px;">Units</th>
                <th>Observations</th>
            </tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>';
    }

    private function footerSection(string $qrTag, string $sigShort): string {
        return '
        <div class="footer-wrap">
            <div class="footer-left">' . $qrTag . '</div>
            <div class="footer-right">
                <div><strong>Digital Signature:</strong> ' . $sigShort . '</div>
                <div style="font-size:7.5pt;color:#888;">
                    Generated: ' . date('Y-m-d H:i:s') . '&nbsp;|&nbsp;
                    Verify at: unilis.jhubafrica.com/smart-lab/verify.php
                </div>
            </div>
        </div>';
    }

    /* ------------------------------------------------------------------ */
    /*  Ruled answer pages                                                  */
    /* ------------------------------------------------------------------ */

    private function answerPageHeader(string $pageTitle): string {
        return '
        <div class="answer-header">
            <h2>' . htmlspecialchars($pageTitle) . '</h2>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Student Name:</td>
                    <td class="meta-line">' . htmlspecialchars($this->studentName) . '</td>
                    <td style="width:18px;"></td>
                    <td class="meta-label">Adm. No.:</td>
                    <td class="meta-line">' . htmlspecialchars($this->admissionNumber) . '</td>
                </tr>
                <tr>
                    <td class="meta-label">Practical:</td>
                    <td colspan="4" class="meta-line">' . htmlspecialchars($this->practicalTitle) . '</td>
                </tr>
            </table>
        </div>';
    }

    /**
     * 27 ruled lines ≈ fills the body of an A4 page after header and footer.
     */
    private function ruledLines(): string {
        $lines = '';
        for ($i = 0; $i < 27; $i++) {
            $lines .= '<div class="ruled-line"></div>';
        }
        return '<div class="ruled-lines">' . $lines . '</div>';
    }

    private function answerPageFooter(): string {
        return '
        <div class="sig-block">
            <div class="sig-inner">
                <div class="sig-cell">
                    Supervisor&apos;s Signature:&nbsp;<span class="sig-line2" style="width:130px;">&nbsp;</span>
                </div>
                <div class="sig-cell">
                    Date:&nbsp;<span class="sig-line2" style="width:100px;">&nbsp;</span>
                </div>
                <div class="sig-cell">
                    Marks Awarded:&nbsp;<span class="sig-line2" style="width:70px;">&nbsp;</span>&nbsp;/ &nbsp;
                </div>
            </div>
        </div>
        <div class="page-label">
            ' . htmlspecialchars($this->practicalTitle) . ' &mdash; '
            . htmlspecialchars($this->admissionNumber) . '
            &nbsp;|&nbsp; JKUAT Smart Lab &nbsp;|&nbsp; ' . date('d M Y') . '
        </div>';
    }
}
