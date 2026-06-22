<?php
namespace SmartLab;

spl_autoload_register(function ($class) {
    $prefix = 'SmartLab\\';

    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $base = dirname(__DIR__); // smart-lab root

        // Check Controllers/ and Models/ in their real directories first
        $subParts = explode('\\', $relative_class);
        if ($subParts[0] === 'Controllers') {
            $filename = $subParts[count($subParts) - 1] . '.php';
            $file = $base . '/controllers/' . $filename;
        } elseif ($subParts[0] === 'Models') {
            $filename = $subParts[count($subParts) - 1] . '.php';
            $file = $base . '/models/' . $filename;
        } else {
            // Fallback: look in includes/ directory
            $file = __DIR__ . '/' . str_replace('\\', '/', $relative_class) . '.php';
        }

        if (file_exists($file)) {
            require $file;
        }
    }
});

// Smart-lab local vendor (dompdf + chillerlan/php-qrcode) — loaded first so
// it wins on production Docker where no parent vendor exists.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Parent project vendor (local XAMPP dev) — only loaded if the class wasn't
// already resolved by the smart-lab vendor above.
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

return [
    'namespace' => 'SmartLab',
    'version' => '1.0.0',
    'initialized' => true
];
