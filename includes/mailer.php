<?php
// includes/mailer.php — FINAL WORKING VERSION FOR DOCKER
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

function send_verification_email($email, $token, $name = '') {
    $mail = new PHPMailer(true);

    try {
        // CRITICAL: Turn on debug — this will show the real problem
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = 'error_log';  // Logs to Docker logs

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'unilis717@gmail.com';
        $mail->Password   = 'cornnvrapsuinyvk';           // CORRECT
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Fix for Docker (most common silent failure)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('unilis717@gmail.com', 'UNILIS');
        $mail->addAddress($email);

        $verify_link = "https://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . "/verify.php?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your UNILIS Account';
        $mail->Body    = "<h2>Welcome!</h2><p>Click to verify: <a href='$verify_link'>Verify Now</a></p>";

        $mail->send();
        error_log("EMAIL SENT SUCCESSFULLY to $email");
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer FAILED: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}