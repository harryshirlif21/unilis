<?php
namespace SmartLab;

use TCPDF;

class DatasheetPDFGenerator {
    private TCPDF $pdf;
    private string $studentName;
    private string $admissionNumber;
    private string $course;
    private string $practicalTitle;
    private string $labNumber;
    private string $experimentName;
    private string $experimentDescription;
    private array $readings;
    private string $qrCodePath;
    private string $signatureHash;
    private string $approvalStatus;
    private string $logoPath;
    private string $outputDir;

    public function __construct(string $logoPath, string $outputDir = '/assets/datasheets/') {
        $this->logoPath = $logoPath;
        $this->outputDir = $outputDir;
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_PAGE_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $this->pdf->SetCreator('JKUAT SmartLab System');
        $this->pdf->SetAuthor('JKUAT');
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $this->pdf->SetMargins(10, 10, 10);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->SetFont('helvetica', '', 10);
    }

    public function setStudentDetails(string $name, string $admissionNumber, string $course): self {
        $this->studentName = $name;
        $this->admissionNumber = $admissionNumber;
        $this->course = $course;
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
        $this->practicalTitle = $title;
        $this->labNumber = $labNumber;
        $this->experimentName = $experimentName;
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
        $this->signatureHash = $signatureHash;
        $this->approvalStatus = $approvalStatus;
        return $this;
    }

    public function generate(string $filename): string {
        $this->pdf->AddPage();
        $this->addHeader();
        $this->addPracticalDetails();
        $this->addStudentDetails();
        $this->addExperimentSection();
        $this->addReadingsTable();
        $this->addResultsSection();
        $this->addFooter();

        $this->addBlankPage('Calculations');
        $this->addBlankPage('Inferences');
        $this->addBlankPage('Additional Notes');

        $outputPath = $_SERVER['DOCUMENT_ROOT'] . $this->outputDir . $filename;
        @mkdir(dirname($outputPath), 0755, true);

        $this->pdf->Output($outputPath, 'F');
        return $this->outputDir . $filename;
    }

    private function addHeader(): void {
        $this->pdf->SetXY(10, 10);

        if (file_exists($this->logoPath)) {
            $this->pdf->Image($this->logoPath, 85, 10, 40);
            $this->pdf->SetY(50);
        }

        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetTextColor(0, 51, 102);
        $this->pdf->Cell(0, 8, 'JKUAT SMART LAB SYSTEM', 0, 1, 'C');

        $this->pdf->SetFont('helvetica', '', 11);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell(0, 6, 'Jomo Kenyatta University of Agriculture and Technology', 0, 1, 'C');

        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 8, 'LAB DATASHEET', 0, 1, 'C');

        $this->pdf->SetDrawColor(0, 51, 102);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line(10, $this->pdf->GetY(), 200, $this->pdf->GetY());
        $this->pdf->Ln(5);
    }

    private function addPracticalDetails(): void {
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetFillColor(230, 240, 255);
        $this->pdf->Cell(0, 7, 'PRACTICAL DETAILS', 0, 1, 'L', true);

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetFillColor(255, 255, 255);

        $y_start = $this->pdf->GetY();
        $this->pdf->SetX(15);
        $this->pdf->MultiCell(40, 6, 'Practical Name:', 0, 'L', false);
        $this->pdf->SetXY(55, $y_start);
        $this->pdf->MultiCell(0, 6, $this->practicalTitle, 0, 'L', false);

        $y_start = $this->pdf->GetY();
        $this->pdf->SetX(15);
        $this->pdf->MultiCell(40, 6, 'Lab Number:', 0, 'L', false);
        $this->pdf->SetXY(55, $y_start);
        $this->pdf->MultiCell(0, 6, $this->labNumber, 0, 'L', false);

        $this->pdf->Ln(2);
    }

    private function addStudentDetails(): void {
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetFillColor(230, 240, 255);
        $this->pdf->Cell(0, 7, 'STUDENT DETAILS', 0, 1, 'L', true);

        $this->pdf->SetFont('helvetica', '', 10);

        $y_start = $this->pdf->GetY();
        $this->pdf->SetX(15);
        $this->pdf->MultiCell(40, 6, 'Name:', 0, 'L', false);
        $this->pdf->SetXY(55, $y_start);
        $this->pdf->MultiCell(0, 6, $this->studentName, 0, 'L', false);

        $y_start = $this->pdf->GetY();
        $this->pdf->SetX(15);
        $this->pdf->MultiCell(40, 6, 'Admission Number:', 0, 'L', false);
        $this->pdf->SetXY(55, $y_start);
        $this->pdf->MultiCell(0, 6, $this->admissionNumber, 0, 'L', false);

        $y_start = $this->pdf->GetY();
        $this->pdf->SetX(15);
        $this->pdf->MultiCell(40, 6, 'Course:', 0, 'L', false);
        $this->pdf->SetXY(55, $y_start);
        $this->pdf->MultiCell(0, 6, $this->course, 0, 'L', false);

        $this->pdf->Ln(2);
    }

    private function addExperimentSection(): void {
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetFillColor(230, 240, 255);
        $this->pdf->Cell(0, 7, 'EXPERIMENT DESCRIPTION', 0, 1, 'L', true);

        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, $this->experimentName, 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(80, 80, 80);
        $this->pdf->MultiCell(0, 5, $this->experimentDescription, 0, 'L');
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Ln(3);
    }

    private function addReadingsTable(): void {
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetFillColor(230, 240, 255);
        $this->pdf->Cell(0, 7, 'READINGS TABLE', 0, 1, 'L', true);

        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->SetFillColor(200, 220, 255);
        $this->pdf->SetLineWidth(0.3);

        $this->pdf->Cell(20, 6, 'Trial', 1, 0, 'C', true);
        $this->pdf->Cell(40, 6, 'Measurement', 1, 0, 'C', true);
        $this->pdf->Cell(30, 6, 'Units', 1, 0, 'C', true);
        $this->pdf->Cell(0, 6, 'Observations', 1, 1, 'C', true);

        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetFillColor(245, 245, 245);

        foreach ($this->readings as $reading) {
            $this->pdf->Cell(20, 6, $reading['trial'] ?? '', 1, 0, 'C');
            $this->pdf->Cell(40, 6, $reading['measurement'] ?? '', 1, 0, 'L');
            $this->pdf->Cell(30, 6, $reading['units'] ?? '', 1, 0, 'C');
            $this->pdf->MultiCell(0, 6, $reading['observation'] ?? '', 1, 'L');
        }

        $this->pdf->Ln(3);
    }

    private function addResultsSection(): void {
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetFillColor(230, 240, 255);
        $this->pdf->Cell(0, 7, 'RESULTS & ANALYSIS', 0, 1, 'L', true);

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->MultiCell(0, 30, '[Space for student results and analysis]', 1, 'L', false);
        $this->pdf->Ln(3);
    }

    private function addBlankPage(string $title): void {
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetTextColor(0, 51, 102);
        $this->pdf->Cell(0, 15, $title, 0, 1, 'C');
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Ln(5);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->MultiCell(0, 200, '', 1, 'L', false);
    }

    private function addFooter(): void {
        $this->pdf->SetY(-20);
        $this->pdf->SetFont('helvetica', '', 8);

        $footerY = $this->pdf->GetY();

        if (!empty($this->qrCodePath) && file_exists($this->qrCodePath)) {
            $this->pdf->Image($this->qrCodePath, 12, $footerY + 2, 20);
        }

        $this->pdf->SetX(45);
        $this->pdf->SetY($footerY);
        $this->pdf->SetFont('helvetica', 'B', 8);
        $this->pdf->Cell(0, 5, 'Digital Signature: ' . substr($this->signatureHash, 0, 16) . '...', 0, 1, 'L');

        if ($this->approvalStatus === 'approved') {
            $this->pdf->SetFont('helvetica', 'B', 9);
            $this->pdf->SetTextColor(0, 128, 0);
            $this->pdf->Cell(0, 5, 'APPROVED', 0, 1, 'L');
            $this->pdf->SetTextColor(0, 0, 0);
        }

        $this->pdf->SetFont('helvetica', '', 7);
        $this->pdf->Cell(0, 4, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'R');
    }
}
