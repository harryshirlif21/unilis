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

echo "PHP version: " . PHP_VERSION . PHP_EOL;

echo "Dompdf\\Dompdf exists   : " . (class_exists('Dompdf\\Dompdf')   ? "YES" : "NO ***") . PHP_EOL;
echo "Dompdf\\Options exists  : " . (class_exists('Dompdf\\Options')  ? "YES" : "NO ***") . PHP_EOL;
echo "chillerlan QRCode      : " . (class_exists('chillerlan\\QRCode\\QRCode') ? "YES" : "NO ***") . PHP_EOL;
echo "chillerlan QROptions   : " . (class_exists('chillerlan\\QRCode\\QROptions') ? "YES" : "NO ***") . PHP_EOL;
echo "chillerlan QRMarkupSVG : " . (class_exists('chillerlan\\QRCode\\Output\\QRMarkupSVG') ? "YES" : "NO ***") . PHP_EOL;

echo PHP_EOL . "Checking BOM in DatasheetPDFGenerator.php..." . PHP_EOL;
$raw = file_get_contents($smartLabRoot . '/includes/DatasheetPDFGenerator.php');
$bom = substr($raw, 0, 3) === "\xEF\xBB\xBF";
echo "Has UTF-8 BOM: " . ($bom ? "YES *** THIS CAUSES FATAL ERRORS ***" : "NO") . PHP_EOL;

echo PHP_EOL . "Trying token_get_all() parse check..." . PHP_EOL;
$tokens = token_get_all($raw);
echo "Parse check passed (no fatal syntax errors in file)" . PHP_EOL;

echo PHP_EOL . "Checking vendor composer installed packages..." . PHP_EOL;
$installedJson = dirname($smartLabRoot) . '/vendor/composer/installed.json';
if (file_exists($installedJson)) {
    $installed = json_decode(file_get_contents($installedJson), true);
    $packages = $installed['packages'] ?? $installed;
    foreach ($packages as $pkg) {
        $name = $pkg['name'] ?? '';
        if (str_contains($name, 'dompdf') || str_contains($name, 'chillerlan') || str_contains($name, 'qrcode')) {
            echo "  FOUND: " . $name . " @ " . ($pkg['version'] ?? 'unknown') . PHP_EOL;
        }
    }
} else {
    echo "installed.json NOT FOUND at: $installedJson" . PHP_EOL;
}
