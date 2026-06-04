<?php
// Production Application Configuration for UNILIS SmartLab
define('APP_NAME',              'UNILIS SmartLab');
define('APP_VERSION',           '1.0.0');
define('APP_URL', 'https://unilis.jhubafrica.com/smart-lab');
define('APP_ENV',               'production');
define('SESSION_LIFETIME',      3600);
define('BLOCKCHAIN_DIFFICULTY', 2);
define('QR_SECRET_KEY',         'unilis_qr_secret_2025');
define('BIOMETRIC_ENABLED',     true);
define('BIOMETRIC_SALT',        'unilis_biometric_salt_2025');
define('UPLOAD_PATH',           __DIR__.'/../public/uploads/');
define('LOG_PATH',              __DIR__.'/../logs/');
define('STAFF_REGISTRATION_KEY', 'UNILIS@Staff2025');

// Sensor Server Configuration (RFID/CO2)
// Use SMART_LAB_SENSOR_URL when the sensor server is behind a proxy or on another host.
$sensorServerUrl = getenv('SMART_LAB_SENSOR_URL') ?: '';
$sensorServerHost = getenv('SMART_LAB_SENSOR_HOST') ?: 'localhost';
$sensorServerPort = getenv('SMART_LAB_SENSOR_PORT') ?: 8765;
define('SENSOR_SERVER_URL',     $sensorServerUrl ?: sprintf('http://%s:%s', $sensorServerHost, $sensorServerPort));
define('SENSOR_SERVER_HOST',    $sensorServerHost);
define('SENSOR_SERVER_PORT',    (int)$sensorServerPort);

// CO2 JSON Storage Path
// On production, this should be writable by the sensor server process
define('SENSOR_JSON_PATH',      '/var/www/unilis/smart-lab/web-app/co2_data');

// Production-specific settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

?>
