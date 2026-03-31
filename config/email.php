<?php
/**
 * Email Configuration
 * Move email credentials to environment variables for security
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Email configuration - use environment variables
define('EMAIL_HOST', getenv('EMAIL_HOST') ?: 'smtp.gmail.com');
define('EMAIL_PORT', getenv('EMAIL_PORT') ?: 587);
define('EMAIL_USERNAME', getenv('EMAIL_USERNAME') ?: 'unilis512@gmail.com');
define('EMAIL_PASSWORD', getenv('EMAIL_PASSWORD') ?: 'sbmxmiafbtfkmkck');
define('EMAIL_ENCRYPTION', getenv('EMAIL_ENCRYPTION') ?: 'tls');

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Get configured PHPMailer instance
 */
function getConfiguredMailer() {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = EMAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_USERNAME;
        $mail->Password   = EMAIL_PASSWORD;
        $mail->SMTPSecure = EMAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = EMAIL_PORT;

        // Optional SMTP debug output for debugging environments
        $mail->SMTPDebug  = 0; // Set to 2 for detailed debug output in development
        $mail->Debugoutput = 'error_log';

        // Workaround for local self-signed cert / restrictive environments
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(EMAIL_USERNAME, 'UNILIS');
        
        return $mail;
    } catch (Exception $e) {
        error_log("Mailer configuration failed: " . $e->getMessage());
        throw $e;
    }
}
?>
