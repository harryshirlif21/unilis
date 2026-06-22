<?php
namespace SmartLab\Controllers;

use SmartLab\DatasheetPDFGenerator;
use SmartLab\DigitalSignature;
use SmartLab\QRCodeGenerator;
use SmartLab\Models\DatasheetModel;
use SmartLab\Models\PracticalModel;

class DatasheetController {
    private DatasheetModel $datasheetModel;
    private DigitalSignature $signature;
    private QRCodeGenerator $qrGenerator;
    private string $logoPath;
    private \PDO $db;

    public function __construct(\PDO $db = null, string $logoPath = '') {
        if ($db === null) {
            $db = getDB();
        }
        $this->db = $db;
        $this->datasheetModel = new DatasheetModel($db);
        $this->signature = new DigitalSignature();
        $this->qrGenerator = new QRCodeGenerator();
        $this->logoPath = $logoPath ?: (defined('DOCUMENT_ROOT')
            ? DOCUMENT_ROOT . '/smart-lab/jkuatlogo.jpg'
            : __DIR__ . '/../jkuatlogo.jpg');
    }

    public function generateDatasheet(
        string $studentId,
        string $practicalId,
        string $authenticationMethod = 'password'
    ): array {
        try {
            $stmt = $this->db->prepare(
                "SELECT p.*, l.name as lab_name 
                 FROM practicals p 
                 LEFT JOIN labs l ON p.lab_id = l.id 
                 WHERE p.id = ? LIMIT 1"
            );
            $stmt->execute([$practicalId]);
            $practical = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$practical) {
                return ['success' => false, 'error' => 'Practical not found'];
            }

            $student = $this->getStudentInfo($studentId);
            if (!$student) {
                return ['success' => false, 'error' => 'Student not found'];
            }

            $timestamp = date('Y-m-d H:i:s');
            $signatureHash = $this->signature->generateSignature(
                $studentId,
                $practicalId,
                $timestamp
            );

            $qrData = sprintf(
                'https://unilis.jhubafrica.com/smart-lab/verify.php?practical_id=%s&student_id=%s&status=approved&timestamp=%s',
                urlencode($practicalId),
                urlencode($studentId),
                urlencode($timestamp)
            );

            $qrCodePath = $this->qrGenerator->generateVerificationQR($practicalId, $studentId);

            $readings = $this->getReadingsTemplate($practicalId);

            $filename = sprintf('datasheet_%s_%s_%d.pdf', $studentId, $practicalId, time());

            $pdfGenerator = new DatasheetPDFGenerator($this->logoPath);
            $pdfGenerator
                ->setStudentDetails($student['full_name'], $student['reg_number'], $student['course'] ?? '')
                ->setPracticalDetails(
                    $practical['title'],
                    $practical['lab_number'] ?? 'Lab 1',
                    $practical['objective'] ?? 'Experiment',
                    $practical['description'] ?? ''
                )
                ->setReadings($readings)
                ->setQRCode($qrCodePath)
                ->setSignature($signatureHash, 'approved');

            $pdfPath = $pdfGenerator->generate($filename);

            $approvalStatus = in_array($authenticationMethod, ['biometric', 'rfid', 'qrcode', 'auth_code'])
                ? 'approved'
                : 'pending';

            $datasheetId = $this->datasheetModel->create([
                'student_id' => $studentId,
                'practical_id' => $practicalId,
                'pdf_filename' => $filename,
                'pdf_path' => $pdfPath,
                'signature_hash' => $signatureHash,
                'qr_code_data' => $qrData,
                'qr_code_path' => $qrCodePath,
                'authentication_method' => $authenticationMethod,
                'approval_status' => $approvalStatus,
                'status' => 'generated'
            ]);

            $this->datasheetModel->addReadings($datasheetId, $readings);

            return [
                'success' => true,
                'datasheet_id' => $datasheetId,
                'pdf_path' => $pdfPath,
                'signature_hash' => $signatureHash,
                'approval_status' => $approvalStatus,
                'qr_code_path' => $qrCodePath
            ];

        } catch (\Exception $e) {
            error_log('DatasheetController::generateDatasheet Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function downloadDatasheet(string $datasheetId): bool {
        try {
            $datasheet = $this->datasheetModel->getById($datasheetId);
            if (!$datasheet) {
                http_response_code(404);
                return false;
            }

            $filePath = $_SERVER['DOCUMENT_ROOT'] . $datasheet['pdf_path'];

            if (!file_exists($filePath)) {
                http_response_code(404);
                return false;
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($datasheet['pdf_filename']) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');

            readfile($filePath);

            $this->datasheetModel->updateStatus($datasheetId, 'submitted');

            return true;

        } catch (\Exception $e) {
            error_log('DatasheetController::downloadDatasheet Error: ' . $e->getMessage());
            http_response_code(500);
            return false;
        }
    }

    public function verifyDatasheet(string $datasheetId, string $signatureHash): array {
        try {
            $datasheet = $this->datasheetModel->getById($datasheetId);
            if (!$datasheet) {
                return ['valid' => false, 'message' => 'Datasheet not found'];
            }

            if (!$this->datasheetModel->verify($datasheetId, $signatureHash)) {
                return ['valid' => false, 'message' => 'Signature verification failed'];
            }

            if ($datasheet['approval_status'] !== 'approved') {
                return [
                    'valid' => false,
                    'message' => 'Datasheet not approved',
                    'status' => $datasheet['approval_status']
                ];
            }

            return [
                'valid' => true,
                'message' => 'Datasheet verified successfully',
                'student_name' => $datasheet['student_name'],
                'practical_title' => $datasheet['practical_title'],
                'approval_status' => $datasheet['approval_status'],
                'verified_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            error_log('DatasheetController::verifyDatasheet Error: ' . $e->getMessage());
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    public function getStudentDatasheets(string $studentId): array {
        try {
            return $this->datasheetModel->getByStudent($studentId);
        } catch (\Exception $e) {
            error_log('DatasheetController::getStudentDatasheets Error: ' . $e->getMessage());
            return [];
        }
    }

    public function approveDatasheet(string $datasheetId, string $approverId): bool {
        try {
            return $this->datasheetModel->updateApprovalStatus($datasheetId, 'approved', $approverId);
        } catch (\Exception $e) {
            error_log('DatasheetController::approveDatasheet Error: ' . $e->getMessage());
            return false;
        }
    }

    public function rejectDatasheet(string $datasheetId): bool {
        try {
            return $this->datasheetModel->updateApprovalStatus($datasheetId, 'rejected');
        } catch (\Exception $e) {
            error_log('DatasheetController::rejectDatasheet Error: ' . $e->getMessage());
            return false;
        }
    }

    private function getStudentInfo(string $studentId): ?array {
        // Try to fetch course/programme — column name may vary by installation
        try {
            $stmt = $this->db->prepare(
                "SELECT id, full_name, reg_number, email, '' AS course
                 FROM users WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$studentId]);
        } catch (\PDOException $e) {
            $stmt = $this->db->prepare(
                "SELECT id, full_name, reg_number, email, '' AS course
                 FROM users WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$studentId]);
        }
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function getReadingsTemplate(string $practicalId): array {
        try {
            $stmt = $this->db->prepare(
                "SELECT trial_number, measurement_label, units, observation_label 
                 FROM chemistry_practical_readings 
                 WHERE chemistry_practical_id = ? 
                 ORDER BY trial_number"
            );
            $stmt->execute([$practicalId]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(function($row) {
                return [
                    'trial' => $row['trial_number'],
                    'measurement' => '',
                    'units' => $row['units'] ?? '',
                    'observation' => ''
                ];
            }, $results);

        } catch (\Exception $e) {
            return [
                ['trial' => 1, 'measurement' => '', 'units' => 'ml', 'observation' => ''],
                ['trial' => 2, 'measurement' => '', 'units' => 'ml', 'observation' => '']
            ];
        }
    }
}
