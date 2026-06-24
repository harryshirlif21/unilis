<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$smartLabRoot = __DIR__;
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once $smartLabRoot . '/config/app_production.php';
    require_once $smartLabRoot . '/config/database_production.php';
} else {
    require_once $smartLabRoot . '/config/app.php';
    require_once $smartLabRoot . '/config/database.php';
}

$parentVendor = dirname($smartLabRoot) . '/vendor/autoload.php';
if (file_exists($parentVendor)) require_once $parentVendor;

require_once $smartLabRoot . '/includes/autoloader.php';

echo "STEP 5a: Check DatasheetPDFGenerator file exists" . PHP_EOL;
$f = $smartLabRoot . '/includes/DatasheetPDFGenerator.php';
echo "File exists: " . (file_exists($f) ? "YES" : "NO ***") . PHP_EOL;
echo "STEP 5a: OK" . PHP_EOL;

echo "STEP 5b: Manually require DatasheetPDFGenerator" . PHP_EOL;
require_once $smartLabRoot . '/includes/DatasheetPDFGenerator.php';
echo "STEP 5b: OK" . PHP_EOL;

echo "STEP 5c: class_exists DatasheetPDFGenerator" . PHP_EOL;
echo "Result: " . (class_exists('\SmartLab\DatasheetPDFGenerator') ? "YES" : "NO ***") . PHP_EOL;
echo "STEP 5c: OK" . PHP_EOL;

echo "STEP 5d: Check QRCodeGenerator file exists" . PHP_EOL;
$f2 = $smartLabRoot . '/includes/QRCodeGenerator.php';
echo "File exists: " . (file_exists($f2) ? "YES" : "NO ***") . PHP_EOL;
echo "STEP 5d: OK" . PHP_EOL;

echo "STEP 5e: Manually require QRCodeGenerator" . PHP_EOL;
require_once $smartLabRoot . '/includes/QRCodeGenerator.php';
echo "STEP 5e: OK" . PHP_EOL;

echo "STEP 5f: Check DigitalSignature file exists" . PHP_EOL;
$f3 = $smartLabRoot . '/includes/DigitalSignature.php';
echo "File exists: " . (file_exists($f3) ? "YES" : "NO ***") . PHP_EOL;
echo "STEP 5f: OK" . PHP_EOL;

echo "STEP 5g: Manually require DigitalSignature" . PHP_EOL;
require_once $smartLabRoot . '/includes/DigitalSignature.php';
echo "STEP 5g: OK" . PHP_EOL;

echo "ALL STEP 5 CHECKS PASSED" . PHP_EOL;
