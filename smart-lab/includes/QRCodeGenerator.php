<?php
namespace SmartLab;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRMarkupSVG;

/**
 * QRCodeGenerator
 *
 * Generates QR code SVGs using chillerlan/php-qrcode (no GD required).
 * The QR code encodes a verification URL so lecturers can scan and confirm
 * a student completed the practical.
 */
class QRCodeGenerator {
    private string $outputDir;
    private int $scale = 4;

    public function __construct(string $outputDir = '/assets/qrcodes/') {
        $this->outputDir = rtrim($outputDir, '/') . '/';
    }

    /**
     * Generate a QR code SVG file for the given data.
     *
     * @return string  Web-root-relative path to the saved .svg file
     */
    public function generate(string $data, string $filename): string {
        $outputPath = $_SERVER['DOCUMENT_ROOT'] . $this->outputDir;
        @mkdir($outputPath, 0755, true);

        $fullPath = $outputPath . $filename . '.svg';

        $options = new QROptions([
            'outputType'       => QRMarkupSVG::class,
            'eccLevel'         => QRCode::ECC_M,
            'scale'            => $this->scale,
            'imageBase64'      => false,
            'svgAddXmlHeader'  => false,
        ]);

        $svg = (new QRCode($options))->render($data);
        file_put_contents($fullPath, $svg);

        return $this->outputDir . $filename . '.svg';
    }

    /**
     * Generate a verification QR code for a practical/student pair.
     * The URL it encodes allows lecturers to scan and verify attendance.
     *
     * @return string  Web-root-relative path to the .svg file
     */
    public function generateVerificationQR(
        string $practicalId,
        string $studentId,
        string $status = 'pending'
    ): string {
        $baseUrl   = defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://unilis.jhubafrica.com/smart-lab';
        $verifyUrl = $baseUrl . '/verify.php?' . http_build_query([
            'practical_id' => $practicalId,
            'student_id'   => $studentId,
            'status'       => $status,
            'ts'           => time(),
        ]);

        $filename = 'datasheet_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $practicalId)
                  . '_' . time();

        return $this->generate($verifyUrl, $filename);
    }

    /**
     * Return the full verification URL (for storage, not for QR generation).
     */
    public function getVerificationUrl(string $practicalId, string $status = 'approved'): string {
        $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://unilis.jhubafrica.com/smart-lab';
        return $baseUrl . '/verify.php?' . http_build_query([
            'practical_id' => $practicalId,
            'status'       => $status,
        ]);
    }
}
