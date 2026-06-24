<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "========================================" . PHP_EOL;
echo "  SMART-LAB DOWNLOAD DEBUG REPORT" . PHP_EOL;
echo "========================================" . PHP_EOL . PHP_EOL;

// -- 1. DEPENDENCIES --------------------------------------
echo "--- [1] VENDOR / DEPENDENCIES ---" . PHP_EOL;
define('DOCUMENT_ROOT', 'C:/xampp/htdocs/unilis/smart-lab');
require_once 'C:/xampp/htdocs/unilis/smart-lab/config/app.php';
require_once 'C:/xampp/htdocs/unilis/smart-lab/config/database.php';

$localVendor  = 'C:/xampp/htdocs/unilis/smart-lab/vendor/autoload.php';
$parentVendor = 'C:/xampp/htdocs/unilis/vendor/autoload.php';

echo "Local vendor (smart-lab/vendor)  : " . (file_exists($localVendor)  ? "YES" : "NO") . PHP_EOL;
echo "Parent vendor (unilis/vendor)    : " . (file_exists($parentVendor) ? "YES" : "NO") . PHP_EOL;

if (file_exists($localVendor))  require_once $localVendor;
if (file_exists($parentVendor)) require_once $parentVendor;

echo "Dompdf class loaded              : " . (class_exists('\Dompdf\Dompdf')  ? "YES" : "NO *** MISSING ***") . PHP_EOL;

require_once 'C:/xampp/htdocs/unilis/smart-lab/includes/autoloader.php';
echo "DatasheetPDFGenerator loaded     : " . (class_exists('\SmartLab\DatasheetPDFGenerator') ? "YES" : "NO *** MISSING ***") . PHP_EOL;
echo "QRCodeGenerator loaded           : " . (class_exists('\SmartLab\QRCodeGenerator')       ? "YES" : "NO *** MISSING ***") . PHP_EOL;
echo "DigitalSignature loaded          : " . (class_exists('\SmartLab\DigitalSignature')       ? "YES" : "NO *** MISSING ***") . PHP_EOL;
echo PHP_EOL;

// -- 2. FILES & DIRECTORIES -------------------------------
echo "--- [2] FILES & DIRECTORIES ---" . PHP_EOL;
$checks = [
    'Logo (jkuatlogo.jpg)'           => 'C:/xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg',
    'assets/datasheets/ exists'      => 'C:/xampp/htdocs/unilis/smart-lab/assets/datasheets',
    'assets/qrcodes/ exists'         => 'C:/xampp/htdocs/unilis/smart-lab/assets/qrcodes',
    'assets/uploads/reports/ exists' => 'C:/xampp/htdocs/unilis/smart-lab/assets/uploads/reports',
];
foreach ($checks as $label => $path) {
    $exists   = file_exists($path);
    $writable = $exists ? is_writable($path) : false;
    echo str_pad($label, 40) . ": " . ($exists ? "EXISTS" : "MISSING ***") . ($exists ? (" | writable: " . ($writable ? "YES" : "NO ***")) : "") . PHP_EOL;
}
echo PHP_EOL;

// -- 3. DATABASE TABLES -----------------------------------
echo "--- [3] DATABASE TABLES ---" . PHP_EOL;
try {
    $db = getDB();
    echo "DB Connection                    : OK" . PHP_EOL . PHP_EOL;

    $tables = ['lab_reports', 'practicals', 'labs', 'users', 'datasheets'];
    foreach ($tables as $table) {
        echo "TABLE: $table" . PHP_EOL;
        try {
            $cols = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                $nullable = $col['Null'] === 'YES' ? 'nullable' : 'NOT NULL';
                $default  = $col['Default'] !== null ? " default={$col['Default']}" : '';
                echo "  - " . str_pad($col['Field'], 30) . " {$col['Type']}  $nullable$default" . PHP_EOL;
            }

            // Row count
            $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "  => Row count: $count" . PHP_EOL;
        } catch (Exception $e) {
            echo "  *** TABLE ERROR: " . $e->getMessage() . PHP_EOL;
        }
        echo PHP_EOL;
    }

    // -- 4. SAMPLE lab_reports ROW ------------------------
    echo "--- [4] LATEST lab_reports ROW (submitted) ---" . PHP_EOL;
    try {
        $row = $db->query("
            SELECT r.id, r.practical_id, r.student_id, r.status,
                   r.calculations, r.result, r.conclusion,
                   r.observations_json, r.submitted_at,
                   p.title as practical_title, p.course_code,
                   p.scheduled_date, p.start_time, p.end_time,
                   p.objective, p.description,
                   p.required_equipment, p.required_chemicals,
                   l.name as lab_name, l.lab_code,
                   u.full_name as lecturer_name
            FROM lab_reports r
            JOIN practicals p ON r.practical_id = p.id
            LEFT JOIN labs l ON p.lab_id = l.id
            LEFT JOIN users u ON p.lecturer_id = u.id
            WHERE r.status = 'submitted'
            ORDER BY r.submitted_at DESC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            foreach ($row as $key => $val) {
                $display = $val === null ? 'NULL ***' : (strlen($val) > 80 ? substr($val,0,80).'...' : $val);
                echo "  " . str_pad($key, 30) . ": $display" . PHP_EOL;
            }

            // -- 5. TRY ACTUAL PDF GENERATION -------------
            echo PHP_EOL . "--- [5] ATTEMPTING PDF STREAM (dry run) ---" . PHP_EOL;
            try {
                $studentId = $row['student_id'];
                $stmt = $db->prepare("SELECT full_name, reg_number, '' AS course FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$studentId]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "Student record found             : " . ($student ? "YES ({$student['full_name']})" : "NO ***") . PHP_EOL;

                $logoPath = realpath('C:/xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg') ?: '';
                echo "Logo realpath                    : " . ($logoPath ?: "EMPTY ***") . PHP_EOL;

                $sigHash       = hash('sha256', $row['id'] . $studentId . ($row['submitted_at'] ?? date('Y-m-d H:i:s')));
                $blockchainHash = hash('sha256', $row['id'] . $sigHash);

                $generator = new \SmartLab\DatasheetPDFGenerator($logoPath);
                $generator
                    ->setStudentDetails(
                        $student['full_name'] ?? 'Unknown',
                        $student['reg_number'] ?? 'N/A',
                        $student['course'] ?? ''
                    )
                    ->setPracticalDetails(
                        $row['practical_title'],
                        $row['lab_code'] ?? 'Lab',
                        $row['objective'] ?? '',
                        $row['description'] ?? $row['objective'] ?? '',
                        $row['scheduled_date'] ?? '',
                        $row['start_time'] ?? ''
                    )
                    ->setExtendedDetails([
                        'course_code' => $row['course_code'] ?? '',
                        'lab_name'    => $row['lab_name']    ?? $row['lab_code'] ?? '',
                    ])
                    ->setDatasheetMeta($row['id'], $blockchainHash, $row['submitted_at'] ?? '')
                    ->setReadings([])
                    ->setObservationRows([])
                    ->setQRCode('')
                    ->setSignature($sigHash, 'approved')
                    ->setFilledAnswers([
                        'Student Observations & Calculations' => $row['calculations'] ?? '',
                        'Results & Analysis'                  => $row['result']       ?? '',
                        'Conclusion & Recommendations'        => $row['conclusion']   ?? '',
                    ]);

                echo "PDF Generator built successfully : YES" . PHP_EOL;
                echo "Calling stream() ..." . PHP_EOL;
                // Don't actually stream — just call generate() to a temp file
                $tmpFile = 'C:/xampp/htdocs/unilis/smart-lab/assets/datasheets/debug_test.pdf';
                $result = $generator->generate(basename($tmpFile));
                echo "PDF generated at                 : $result" . PHP_EOL;
                echo PHP_EOL . "*** ALL CHECKS PASSED — PDF generation works! ***" . PHP_EOL;

            } catch (\Throwable $e) {
                echo PHP_EOL . "*** PDF GENERATION FAILED ***" . PHP_EOL;
                echo "Error type : " . get_class($e) . PHP_EOL;
                echo "Message    : " . $e->getMessage() . PHP_EOL;
                echo "File       : " . $e->getFile() . PHP_EOL;
                echo "Line       : " . $e->getLine() . PHP_EOL;
                echo PHP_EOL . "Stack trace:" . PHP_EOL;
                echo $e->getTraceAsString() . PHP_EOL;
            }
        } else {
            echo "  *** No submitted lab_reports found. Submit a practical first. ***" . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "Query failed: " . $e->getMessage() . PHP_EOL;
    }

} catch (Exception $e) {
    echo "DB Connection FAILED: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "========================================" . PHP_EOL;
echo "  END OF DEBUG REPORT" . PHP_EOL;
echo "========================================" . PHP_EOL;
