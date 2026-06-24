<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$smartLabRoot = __DIR__;
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

echo "STEP 1: Config load" . PHP_EOL;
if ($is_production) {
    require_once $smartLabRoot . '/config/app_production.php';
    require_once $smartLabRoot . '/config/database_production.php';
} else {
    require_once $smartLabRoot . '/config/app.php';
    require_once $smartLabRoot . '/config/database.php';
}
echo "STEP 1: OK" . PHP_EOL;

echo "STEP 2: Parent vendor load" . PHP_EOL;
$parentVendor = dirname($smartLabRoot) . '/vendor/autoload.php';
if (file_exists($parentVendor)) require_once $parentVendor;
echo "STEP 2: OK" . PHP_EOL;

echo "STEP 3: Dompdf check" . PHP_EOL;
echo "Dompdf: " . (class_exists('\Dompdf\Dompdf') ? "YES" : "NO ***") . PHP_EOL;
echo "STEP 3: OK" . PHP_EOL;

echo "STEP 4: autoloader.php load" . PHP_EOL;
require_once $smartLabRoot . '/includes/autoloader.php';
echo "STEP 4: OK" . PHP_EOL;

echo "STEP 5: Class existence checks" . PHP_EOL;
echo "DatasheetPDFGenerator : " . (class_exists('\SmartLab\DatasheetPDFGenerator') ? "YES" : "NO ***") . PHP_EOL;
echo "QRCodeGenerator       : " . (class_exists('\SmartLab\QRCodeGenerator')       ? "YES" : "NO ***") . PHP_EOL;
echo "DigitalSignature      : " . (class_exists('\SmartLab\DigitalSignature')       ? "YES" : "NO ***") . PHP_EOL;
echo "STEP 5: OK" . PHP_EOL;

echo "STEP 6: Instantiate DatasheetPDFGenerator" . PHP_EOL;
$logoPath = realpath($smartLabRoot . '/jkuatlogo.jpg') ?: '';
echo "Logo path: " . ($logoPath ?: "MISSING ***") . PHP_EOL;
$gen = new \SmartLab\DatasheetPDFGenerator($logoPath);
echo "STEP 6: OK" . PHP_EOL;

echo "STEP 7: DB connection" . PHP_EOL;
$db = getDB();
echo "STEP 7: OK" . PHP_EOL;

echo "STEP 8: Fetch latest submitted report" . PHP_EOL;
$row = $db->query("
    SELECT r.id, r.practical_id, r.student_id, r.status,
           r.calculations, r.result, r.conclusion,
           r.observations_json, r.submitted_at,
           p.title as practical_title, p.course_code,
           p.scheduled_date, p.start_time,
           p.objective, p.description,
           l.name as lab_name, l.lab_code
    FROM lab_reports r
    JOIN practicals p ON r.practical_id = p.id
    LEFT JOIN labs l ON p.lab_id = l.id
    WHERE r.status = 'submitted'
    ORDER BY r.submitted_at DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
echo "Report found: " . ($row ? "YES (id={$row['id']})" : "NO — no submitted reports yet") . PHP_EOL;
echo "STEP 8: OK" . PHP_EOL;

if ($row) {
    echo "STEP 9: Fetch student" . PHP_EOL;
    $stmt = $db->prepare("SELECT full_name, reg_number, '' AS course FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$row['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Student: " . ($student ? $student['full_name'] : "NOT FOUND ***") . PHP_EOL;
    echo "STEP 9: OK" . PHP_EOL;

    echo "STEP 10: QRCodeGenerator instantiate" . PHP_EOL;
    $qrGen = new \SmartLab\QRCodeGenerator();
    echo "STEP 10: OK" . PHP_EOL;

    echo "STEP 11: Build generator chain" . PHP_EOL;
    $sigHash        = hash('sha256', $row['id'] . $row['student_id'] . ($row['submitted_at'] ?? ''));
    $blockchainHash = hash('sha256', $row['id'] . $sigHash);
    $gen
        ->setStudentDetails($student['full_name'] ?? 'N/A', $student['reg_number'] ?? 'N/A', '')
        ->setPracticalDetails(
            $row['practical_title'], $row['lab_code'] ?? 'Lab',
            $row['objective'] ?? '', $row['description'] ?? '',
            $row['scheduled_date'] ?? '', $row['start_time'] ?? ''
        )
        ->setExtendedDetails(['course_code' => $row['course_code'] ?? '', 'lab_name' => $row['lab_name'] ?? ''])
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
    echo "STEP 11: OK" . PHP_EOL;

    echo "STEP 12: generate() PDF to file" . PHP_EOL;
    $result = $gen->generate('debug_test.pdf');
    echo "STEP 12: OK — saved to: $result" . PHP_EOL;

    echo PHP_EOL . "*** ALL STEPS PASSED — PDF generation works! ***" . PHP_EOL;
}
