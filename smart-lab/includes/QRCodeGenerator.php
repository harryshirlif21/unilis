<?php
namespace SmartLab;

class QRCodeGenerator {
    private string $outputDir;
    private int $size = 10;
    private int $margin = 2;
    private string $errorCorrection = 'L';

    public function __construct(string $outputDir = '/assets/qrcodes/') {
        $this->outputDir = $outputDir;
    }

    public function generate(
        string $data,
        string $filename,
        int $size = 10,
        int $margin = 2
    ): string {
        if (!class_exists('QRcode')) {
            throw new \Exception('QRcode library not found. Install phpqrcode package.');
        }

        $outputPath = $_SERVER['DOCUMENT_ROOT'] . $this->outputDir;
        @mkdir($outputPath, 0755, true);

        $fullPath = $outputPath . $filename . '.png';

        \QRcode::png($data, $fullPath, $this->errorCorrection, $size, $margin);

        if (!file_exists($fullPath)) {
            throw new \Exception("QR code generation failed for: $filename");
        }

        return $this->outputDir . $filename . '.png';
    }

    public function generateVerificationQR(
        string $practicalId,
        string $studentId,
        string $status = 'pending'
    ): string {
        $baseUrl = 'https://unilis.jhubafrica.com/smart-lab/verify.php';
        $queryParams = http_build_query([
            'practical_id' => $practicalId,
            'student_id' => $studentId,
            'status' => $status,
            'timestamp' => time()
        ]);

        $qrData = $baseUrl . '?' . $queryParams;
        $filename = 'datasheet_' . $practicalId . '_' . time();

        return $this->generate($qrData, $filename, 8, 2);
    }

    public function getVerificationUrl(
        string $practicalId,
        string $status = 'approved'
    ): string {
        return sprintf(
            'https://unilis.jhubafrica.com/smart-lab/verify.php?practical_id=%s&status=%s',
            urlencode($practicalId),
            urlencode($status)
        );
    }

    public function setErrorCorrection(string $level): self {
        $valid = ['L', 'M', 'Q', 'H'];
        if (!in_array($level, $valid)) {
            throw new \InvalidArgumentException("Invalid error correction level: $level");
        }
        $this->errorCorrection = $level;
        return $this;
    }

    public function setSize(int $size): self {
        if ($size < 1 || $size > 40) {
            throw new \InvalidArgumentException("Size must be between 1 and 40");
        }
        $this->size = $size;
        return $this;
    }

    public function setMargin(int $margin): self {
        if ($margin < 0 || $margin > 10) {
            throw new \InvalidArgumentException("Margin must be between 0 and 10");
        }
        $this->margin = $margin;
        return $this;
    }
}
