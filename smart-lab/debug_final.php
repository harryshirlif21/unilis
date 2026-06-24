<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
header('Content-Type: text/plain; charset=UTF-8');

$failures = [];
$stepNumber = 0;

function debug_value($value): string {
    if ($value === null) {
        return 'NULL';
    }
    if ($value === '') {
        return '(empty string)';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    return (string)$value;
}

function fail_step(string $label, Throwable $e, string $output = ''): void {
    global $failures;

    $message = get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine();

    if ($output !== '') {
        $message .= "\nCaptured output:\n" . $output;
    }

    $failures[] = [$label, $message];
    echo $label . ': FAILED' . PHP_EOL;
    echo '  ' . str_replace("\n", "\n  ", $message) . PHP_EOL;
}

function run_step(string $description, callable $callback): void {
    global $stepNumber;

    $stepNumber++;
    $label = 'STEP ' . $stepNumber . ' - ' . $description;
    ob_start();

    try {
        $result = $callback();
        $output = (string)ob_get_clean();

        if ($output !== '') {
            throw new RuntimeException('Unexpected output was emitted during this step.');
        }

        echo $label . ': OK';
        if ($result !== null && $result !== '') {
            echo ' - ' . debug_value($result);
        }
        echo PHP_EOL;
    } catch (Throwable $e) {
        $output = '';
        if (ob_get_level() > 0) {
            $output = (string)ob_get_clean();
        }
        fail_step($label, $e, $output);
    }
}

function require_silently(string $path): string {
    if (!is_file($path)) {
        throw new RuntimeException('Required file not found: ' . $path);
    }

    ob_start();
    try {
        require_once $path;
        $output = (string)ob_get_clean();
    } catch (Throwable $e) {
        $output = '';
        if (ob_get_level() > 0) {
            $output = (string)ob_get_clean();
        }
        if ($output !== '') {
            throw new RuntimeException(
                'require_once failed after emitting output from ' . $path . "\n" . $output,
                0,
                $e
            );
        }
        throw $e;
    }

    if ($output !== '') {
        throw new RuntimeException('require_once emitted output from ' . $path . "\n" . $output);
    }

    return $path;
}

function assert_writable_directory(string $path): string {
    if (!is_dir($path) && !mkdir($path, 0755, true)) {
        throw new RuntimeException('Directory does not exist and could not be created: ' . $path);
    }

    if (!is_writable($path)) {
        $owner = function_exists('posix_getpwuid') ? @posix_getpwuid((int)@fileowner($path)) : null;
        $group = function_exists('posix_getgrgid') ? @posix_getgrgid((int)@filegroup($path)) : null;
        throw new RuntimeException(
            'Directory is not writable: ' . $path
            . ' owner=' . ($owner['name'] ?? (string)@fileowner($path))
            . ' group=' . ($group['name'] ?? (string)@filegroup($path))
            . ' perms=' . substr(sprintf('%o', (int)@fileperms($path)), -4)
        );
    }

    $probe = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'debug_write_probe_' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($probe, 'ok') === false) {
        throw new RuntimeException('file_put_contents probe failed for: ' . $probe);
    }
    if (!unlink($probe)) {
        throw new RuntimeException('Could not remove write probe: ' . $probe);
    }

    return $path;
}

echo 'Smart Lab final PDF diagnostic' . PHP_EOL;
echo 'Script path: ' . __FILE__ . PHP_EOL;
echo 'Timestamp: ' . date('c') . PHP_EOL . PHP_EOL;

$smartLabRoot = __DIR__;
$parentRoot = dirname(__DIR__);
$localVendor = $smartLabRoot . '/vendor/autoload.php';
$parentVendor = $parentRoot . '/vendor/autoload.php';
$datasheetDir = $smartLabRoot . '/assets/datasheets';
$qrDir = $smartLabRoot . '/assets/qrcodes';

run_step('PHP runtime', function () {
    if (PHP_VERSION_ID < 80200) {
        throw new RuntimeException('Expected PHP 8.2+, got ' . PHP_VERSION);
    }
    return PHP_VERSION . ' (' . PHP_SAPI . ')';
});

run_step('Server variables used by the controller', function () use ($smartLabRoot) {
    $required = [
        'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? null,
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
    ];

    $missing = [];
    foreach ($required as $key => $value) {
        if ($value === null || $value === '') {
            $missing[] = $key;
        }
    }

    if (!empty($missing)) {
        throw new RuntimeException('Missing or empty $_SERVER keys: ' . implode(', ', $missing));
    }

    $documentRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\');
    $expectedSmartLabRoot = $documentRoot . DIRECTORY_SEPARATOR . 'smart-lab';
    if (realpath($expectedSmartLabRoot) !== realpath($smartLabRoot)) {
        throw new RuntimeException(
            'DOCUMENT_ROOT/smart-lab does not match this app root. DOCUMENT_ROOT='
            . $_SERVER['DOCUMENT_ROOT'] . ' expected=' . $expectedSmartLabRoot
            . ' actual=' . $smartLabRoot
        );
    }

    return 'DOCUMENT_ROOT=' . $_SERVER['DOCUMENT_ROOT'] . ', HTTP_HOST=' . $_SERVER['HTTP_HOST'];
});

run_step('Production config require_once', function () use ($smartLabRoot) {
    return require_silently($smartLabRoot . '/config/app_production.php');
});

run_step('Production database config require_once', function () use ($smartLabRoot) {
    return require_silently($smartLabRoot . '/config/database_production.php');
});

run_step('Composer autoload availability', function () use ($localVendor, $parentVendor) {
    $loaded = [];

    if (is_file($localVendor)) {
        $loaded[] = require_silently($localVendor);
    }
    if (is_file($parentVendor)) {
        $loaded[] = require_silently($parentVendor);
    }
    if (empty($loaded)) {
        throw new RuntimeException('No Composer autoload file found at ' . $localVendor . ' or ' . $parentVendor);
    }

    return implode(', ', $loaded);
});

run_step('Vendor classes resolve', function () {
    $classes = [
        '\\Dompdf\\Dompdf',
        '\\Dompdf\\Options',
        '\\chillerlan\\QRCode\\QRCode',
        '\\chillerlan\\QRCode\\QROptions',
        '\\chillerlan\\QRCode\\Output\\QRMarkupSVG',
    ];

    $missing = [];
    foreach ($classes as $class) {
        if (!class_exists($class)) {
            $missing[] = $class;
        }
    }

    if (!empty($missing)) {
        throw new RuntimeException('Missing vendor classes: ' . implode(', ', $missing));
    }

    return 'Dompdf and chillerlan classes found';
});

run_step('SmartLab autoloader require_once', function () use ($smartLabRoot) {
    return require_silently($smartLabRoot . '/includes/autoloader.php');
});

run_step('DatasheetPDFGenerator require_once', function () use ($smartLabRoot) {
    return require_silently($smartLabRoot . '/includes/DatasheetPDFGenerator.php');
});

run_step('QRCodeGenerator require_once', function () use ($smartLabRoot) {
    return require_silently($smartLabRoot . '/includes/QRCodeGenerator.php');
});

run_step('SmartLab classes resolve', function () {
    $classes = [
        '\\SmartLab\\DatasheetPDFGenerator',
        '\\SmartLab\\QRCodeGenerator',
    ];

    $missing = [];
    foreach ($classes as $class) {
        if (!class_exists($class)) {
            $missing[] = $class;
        }
    }

    if (!empty($missing)) {
        throw new RuntimeException('Missing SmartLab classes: ' . implode(', ', $missing));
    }

    return 'SmartLab classes found';
});

run_step('Assets datasheets directory writable', function () use ($datasheetDir) {
    return assert_writable_directory($datasheetDir);
});

run_step('Assets qrcodes directory writable', function () use ($qrDir) {
    return assert_writable_directory($qrDir);
});

run_step('DOCUMENT_ROOT asset path check', function () use ($smartLabRoot) {
    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    if ($documentRoot === '') {
        throw new RuntimeException('DOCUMENT_ROOT is empty.');
    }

    $defaultGeneratorPath = $documentRoot . '/assets/qrcodes';
    $correctSmartLabPath = $smartLabRoot . '/assets/qrcodes';
    if (realpath($defaultGeneratorPath) !== realpath($correctSmartLabPath)) {
        throw new RuntimeException(
            'Default generator path /assets/qrcodes resolves to ' . $defaultGeneratorPath
            . ', but smart-lab qrcodes are at ' . $correctSmartLabPath
            . '. Use /smart-lab/assets/qrcodes/ and /smart-lab/assets/datasheets/ as generator output dirs.'
        );
    }

    return $defaultGeneratorPath;
});

run_step('End-to-end QR generation', function () {
    $qr = new \SmartLab\QRCodeGenerator('/smart-lab/assets/qrcodes/');
    $path = $qr->generate(
        'https://unilis.jhubafrica.com/smart-lab/verify.php?debug=1',
        'debug_final_' . date('Ymd_His')
    );

    $fullPath = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim($path, '/\\');
    if (!is_file($fullPath)) {
        throw new RuntimeException('QR generator returned ' . $path . ' but file was not found at ' . $fullPath);
    }

    return $path;
});

run_step('End-to-end PDF generation with mock data', function () use ($smartLabRoot) {
    $logoPath = realpath($smartLabRoot . '/jkuatlogo.jpg') ?: '';
    $reportId = 'debug-final-' . bin2hex(random_bytes(6));
    $signatureHash = hash('sha256', $reportId . '|debug-student|' . date('c'));
    $blockchainHash = hash('sha256', $reportId . '|' . $signatureHash);

    $qr = new \SmartLab\QRCodeGenerator('/smart-lab/assets/qrcodes/');
    $qrPath = $qr->generate(
        'https://unilis.jhubafrica.com/smart-lab/verify.php?' . http_build_query([
            'report_id' => $reportId,
            'student_id' => 'debug-student',
            'type' => 'lab_report',
        ]),
        'report_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $reportId)
    );

    $generator = new \SmartLab\DatasheetPDFGenerator($logoPath, '/smart-lab/assets/datasheets/');
    $generator
        ->setStudentDetails('Debug Student', 'DEBUG/001', 'BSc Laboratory Science')
        ->setPracticalDetails(
            'Debug Practical',
            'LAB-DEBUG',
            'PDF Pipeline Verification',
            'Mock practical used to verify QR generation, Dompdf rendering, and datasheet file output.',
            date('Y-m-d'),
            date('H:i:s')
        )
        ->setExtendedDetails([
            'course_code' => 'DBG 101',
            'group' => 'Debug Group',
            'academic_year' => date('Y') . '/' . ((int)date('Y') + 1),
            'semester' => 'Debug Semester',
            'experiment_number' => 'DBG-EXP-001',
            'lab_name' => 'Debug Lab',
            'lecturer_name' => 'Debug Lecturer',
            'objectives' => ['Confirm Dompdf can render the datasheet.', 'Confirm filesystem paths are writable.'],
            'equipment' => [['name' => 'Diagnostic Script', 'specification' => 'debug_final.php']],
            'procedure_summary' => 'Generate mock QR code and render a PDF datasheet to disk.',
        ])
        ->setAttendance(1, 1, 12)
        ->setObservationRows([
            ['Parameter' => 'Temperature', 'Value' => '25', 'Units' => 'C'],
            ['Parameter' => 'Status', 'Value' => 'OK', 'Units' => '-'],
        ])
        ->setQRCode($qrPath)
        ->setSignature($signatureHash, 'approved')
        ->setDatasheetMeta($reportId, $blockchainHash, date('Y-m-d H:i:s'))
        ->setFilledAnswers([
            'Results & Analysis' => 'Dompdf rendered mock results successfully.',
            'Discussion' => 'This confirms class loading, QR embedding, and PDF rendering.',
            'Conclusion & Recommendations' => 'If this step passes, investigate route auth/session/database rows next.',
        ]);

    $relativePath = $generator->generate($reportId . '.pdf');
    $fullPath = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim($relativePath, '/\\');
    if (!is_file($fullPath)) {
        throw new RuntimeException('PDF generator returned ' . $relativePath . ' but file was not found at ' . $fullPath);
    }
    if (filesize($fullPath) < 1024) {
        throw new RuntimeException('Generated PDF is unexpectedly small: ' . filesize($fullPath) . ' bytes at ' . $fullPath);
    }

    return $relativePath . ' (' . filesize($fullPath) . ' bytes)';
});

echo PHP_EOL . 'SUMMARY' . PHP_EOL;
if (empty($failures)) {
    echo 'All diagnostic steps passed.' . PHP_EOL;
} else {
    foreach ($failures as $failure) {
        echo '- ' . $failure[0] . PHP_EOL;
        echo '  ' . str_replace("\n", "\n  ", $failure[1]) . PHP_EOL;
    }
}
