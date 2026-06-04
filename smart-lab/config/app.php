<?php
define('APP_NAME',              'UNILIS SmartLab');
define('APP_VERSION',           '1.0.0');
define('APP_URL',               sprintf('http://%s/smart-lab', $_SERVER['HTTP_HOST'] ?? 'localhost'));
define('APP_ENV',               'development');
define('SESSION_LIFETIME',      3600);
define('BLOCKCHAIN_DIFFICULTY', 2);
define('QR_SECRET_KEY',         'unilis_qr_secret_2025');
define('BIOMETRIC_ENABLED',     true);
define('BIOMETRIC_SALT',        'unilis_biometric_salt_2025');
define('UPLOAD_PATH',           __DIR__.'/../public/uploads/');
define('LOG_PATH',              __DIR__.'/../logs/');
define('STAFF_REGISTRATION_KEY', 'UNILIS@Staff2025');

// Sensor Server Configuration (RFID/CO2)
// Prefer a full URL if the sensor server is proxied or hosted separately.
$sensorServerUrl = getenv('SMART_LAB_SENSOR_URL') ?: '';
$sensorServerHost = getenv('SMART_LAB_SENSOR_HOST') ?: 'localhost';
$sensorServerPort = getenv('SMART_LAB_SENSOR_PORT') ?: 8765;
define('SENSOR_SERVER_URL',     $sensorServerUrl ?: sprintf('http://%s:%s', $sensorServerHost, $sensorServerPort));
define('SENSOR_SERVER_HOST',    $sensorServerHost);
define('SENSOR_SERVER_PORT',    (int)$sensorServerPort);

// CO2 JSON Storage Path
// For local development, JSON files are stored relative to web-app
define('SENSOR_JSON_PATH',      __DIR__.'/../web-app/co2_data');
