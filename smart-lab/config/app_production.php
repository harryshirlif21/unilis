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

// Production-specific settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

?>
