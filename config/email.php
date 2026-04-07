<?php
/**
 * Email Configuration
 * Use environment variables for SMTP settings, with safe defaults for local delivery.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Email configuration - use environment variables
define('EMAIL_HOST', getenv('EMAIL_HOST') !== false ? getenv('EMAIL_HOST') : 'localhost');
define('EMAIL_PORT', getenv('EMAIL_PORT') !== false ? getenv('EMAIL_PORT') : 25);

define('EMAIL_USERNAME', getenv('EMAIL_USERNAME') !== false ? getenv('EMAIL_USERNAME') : '');
define('EMAIL_PASSWORD', getenv('EMAIL_PASSWORD') !== false ? getenv('EMAIL_PASSWORD') : '');

$envEmailAuth = getenv('EMAIL_AUTH');
define('EMAIL_AUTH', $envEmailAuth !== false ? filter_var($envEmailAuth, FILTER_VALIDATE_BOOLEAN) : false);

define('EMAIL_ENCRYPTION', getenv('EMAIL_ENCRYPTION') !== false ? strtolower(getenv('EMAIL_ENCRYPTION')) : '');

define('EMAIL_FROM_ADDRESS', getenv('EMAIL_FROM_ADDRESS') !== false ? getenv('EMAIL_FROM_ADDRESS') : 'noreply@unilis.jhubafrica.com');
define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') !== false ? getenv('EMAIL_FROM_NAME') : 'UNILIS');

define('EMAIL_DEBUG', getenv('EMAIL_DEBUG') !== false ? (int)getenv('EMAIL_DEBUG') : 0);

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Get configured PHPMailer instance
 */
function getConfiguredMailer() {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = EMAIL_HOST;
    $mail->SMTPAuth   = EMAIL_AUTH;
    $mail->Port       = EMAIL_PORT;

    if (EMAIL_AUTH) {
        $mail->Username = EMAIL_USERNAME;
        $mail->Password = EMAIL_PASSWORD;
    }

    if (EMAIL_ENCRYPTION === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif (EMAIL_ENCRYPTION === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = '';
    }

    $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
    $mail->addReplyTo(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);

    $mail->SMTPDebug  = EMAIL_DEBUG;
    $mail->Debugoutput = 'error_log';

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    return $mail;
}
?>
