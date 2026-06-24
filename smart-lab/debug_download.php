<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Auto-detect paths (works on both Linux/Docker and Windows/XAMPP)
$smartLabRoot = __DIR__;
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

echo "========================================" . PHP_EOL;
echo "  SMART-LAB DOWNLOAD DEBUG REPORT" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo "Running as       : " . get_current_user() . PHP_EOL;
echo "Script path      : " . __FILE__ . PHP_EOL;
echo "Smart-lab root   : " . $smartLabRoot . PHP_EOL;
echo "Is production    : " . ($is_production ? "YES" : "NO") . PHP_EOL;
echo "HTTP_HOST        : " . ($_SERVER['HTTP_HOST'] ?? 'CLI') . PHP_EOL;
echo PHP_EOL;

// -- 1. CONFIG --------------------------------------------
echo "--- [1] CONFIG & DATABASE ---" . PHP_EOL;
if ($is_production) {
    require_once $smartLabRoot . '/config/app_production.php';
    require_once $smartLabRoot . '/config/database_production.php';
} else {
    require_once $smartLabRoot . '/config/app.php';
    require_once $smartLabRoot . '/config/database.php';
}
echo "Config loaded    : OK" . PHP_EOL;

// -- 2. VENDOR / DEPENDENCIES -----------------------------
echo PHP_EOL . "--- [2] VENDOR / DEPENDENCIES ---" . PHP_EOL;
$localVendor  = $smartLabRoot . '/vendor/autoload.php';
$parentVendor = dirname($smartLabRoot) . '/vendor/autoload.php';

echo "Local vendor     : " . $localVendor  . " => " . (file_exists($localVendor)  ? "EXISTS" : "MISSING ***") . PHP_EOL;
echo "Parent vendor    : " . $parentVendor . " => " . (file_exists($parentVendor) ? "EXISTS" : "MISSING ***") . PHP_EOL;

if (file_exists($localVendor))  require_once $localVendor;
if (file_exists($parentVendor)) require_once $parentVendor;

echo "Dompdf loaded    : " . (class_exists('\Dompdf\Dompdf')  ? "YES" : "NO *** MISSING ***") . PHP_EOL;

require_once $smartLabRoot . '/includes/autoloader.php';
echo "DatasheetPDFGen  : " . (class_exists('\SmartLab\DatasheetPDFGenerator') ? "YES" : "NO *** MISSING ***") . PHP_EOL;
echo "QRCodeGenerator  : " . (class_exists('\SmartLab\QRCodeGenerator')       ? "YES" : "NO *** MISSING ***") . PHP_EOL;
echo "DigitalSignature : " . (class_exists('\SmartLab\DigitalSignature')       ? "YES" : "NO *** MISSING ***") . PHP_EOL;
echo PHP_EOL;

// -- 3. FILES & DIRECTORIES -------------------------------
echo "--- [3] FILES & DIRECTORIES ---" . PHP_EOL;
$paths = [
    'Logo (jkuatlogo.jpg)'      => $smartLabRoot . '/jkuatlogo.jpg',
    'assets/datasheets/'        => $smartLabRoot . '/assets/datasheets',
    'assets/qrcodes/'           => $smartLabRoot . '/assets/qrcodes',
    'assets/uploads/reports/'   => $smartLabRoot . '/assets/uploads/reports',
];
foreach ($paths as $label => $path) {
    $exists   = file_exists($path);
    $writable = $exists ? is_writable($path) : false;
    echo str_pad($label, 30) . ": " . ($exists ? "EXISTS" : "MISSING ***")
       . ($exists ? " | writable: " . ($writable ? "YES" : "NO ***") : "") . PHP_EOL;
}
echo PHP_EOL;

// -- 4. DATABASE TABLES -----------------------------------
echo "--- [4] DATABASE TABLES ---" . PHP_EOL;
try {
    $db = getDB();
    echo "DB Connection    : OK" . PHP_EOL . PHP_EOL;

    foreach (['lab_reports', 'practicals', 'labs', 'users', 'datasheets'] as $table) {
        echo "TABLE: $table" . PHP_EOL;
        try {
            $cols = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                $nullable = $col['Null'] === 'YES' ? 'nullable' : 'NOT NULL';
                $default  = $col['Default'] !== null ? " default={$col['Default']}" : '';
                echo "  " . str_pad($col['Field'], 30) . " {$col['Type']}  $nullable$default" . PHP_EOL;
            }
            echo "  => Rows: " . $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() . PHP_EOL;
        } catch (Exception $e) {
            echo "  *** ERROR: " . $e->getMessage() . PHP_EOL;
        }
        echo PHP_EOL;
    }

    // -- 5. SAMPLE ROW ------------------------------------
    echo "--- [5] LATEST SUBMITTED lab_report ---" . PHP_EOL;
    $row = $db->query("
        SELECT r.id, r.practical_id, r.student_id, r.status,
               r.calculations, r.result, r.conclusion,
               r.observations_json, r.submitted_at,
               p.title as practical_title, p.course_code,
               p.scheduled_date, p.start_time,
               p.objective, p.description,
               l.name as lab_name, l.lab_code,
               u.full_name as lecturer_name
        FROM lab_reports r
        JOIN practicals p ON r.practical_id = p.id
        LEFT JOIN labs l ON p.lab_id = l.id
        LEFT JOIN users u ON p.lecturer_id = u.id
        WHERE r.status = 'submitted'
        ORDER BY r.submitted_at DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        foreach ($row as $k => $v) {
            $display = $v === null ? 'NULL' : (strlen((string)$v) > 80 ? substr($v,0,80).'...' : $v);
            echo "  " . str_pad($k, 30) . ": $display" . PHP_EOL;
        }

        // -- 6. ATTEMPT PDF BUILD --------------------------
        echo PHP_EOL . "--- [6] PDF GENERATION TEST ---" . PHP_EOL;
        try {
            $stmt = $db->prepare("SELECT full_name, reg_number, '' AS course FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$row['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Student found    : " . ($student ? "YES ({$student['full_name']})" : "NO ***") . PHP_EOL;

            $logoPath = realpath($smartLabRoot . '/jkuatlogo.jpg') ?: '';
            echo "Logo realpath    : " . ($logoPath ?: "NOT FOUND ***") . PHP_EOL;

            $sigHash        = hash('sha256', $row['id'] . $row['student_id'] . ($row['submitted_at'] ?? ''));
            $blockchainHash = hash('sha256', $row['id'] . $sigHash);

            $generator = new \SmartLab\DatasheetPDFGenerator($logoPath);
            $generator
                ->setStudentDetails($student['full_name'] ?? 'N/A', $student['reg_number'] ?? 'N/A', '')
                ->setPracticalDetails(
                    $row['practical_title'],
                    $row['lab_code'] ?? 'Lab',
                    $row['objective'] ?? '',
                    $row['description'] ?? '',
                    $row['scheduled_date'] ?? '',
                    $row['start_time'] ?? ''
                )
                ->setExtendedDetails([
                    'course_code' => $row['course_code'] ?? '',
                    'lab_name'    => $row['lab_name']    ?? '',
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

            $outFile = $smartLabRoot . '/assets/datasheets/debug_test.pdf';
            $result  = $generator->generate(basename($outFile));
            echo "PDF generated    : $result" . PHP_EOL;
            echo PHP_EOL . "*** ALL CHECKS PASSED ***" . PHP_EOL;

        } catch (\Throwable $e) {
            echo PHP_EOL . "*** PDF GENERATION FAILED ***" . PHP_EOL;
            echo "Type    : " . get_class($e) . PHP_EOL;
            echo "Message : " . $e->getMessage() . PHP_EOL;
            echo "File    : " . $e->getFile() . PHP_EOL;
            echo "Line    : " . $e->getLine() . PHP_EOL;
            echo PHP_EOL . "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
        }
    } else {
        echo "*** No submitted lab_reports found. Submit a practical first then re-run. ***" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "DB FAILED: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "========================================" . PHP_EOL;
echo "  END OF DEBUG REPORT" . PHP_EOL;
echo "========================================" . PHP_EOL;
