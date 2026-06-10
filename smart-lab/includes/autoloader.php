<?php
namespace SmartLab;

spl_autoload_register(function ($class) {
    $prefix = 'SmartLab\\';

    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
});

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

return [
    'namespace' => 'SmartLab',
    'version' => '1.0.0',
    'initialized' => true
];
