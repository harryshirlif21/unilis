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

// Parent project vendor (has dompdf)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Smart-lab local vendor (chillerlan/php-qrcode etc.)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

return [
    'namespace' => 'SmartLab',
    'version' => '1.0.0',
    'initialized' => true
];
