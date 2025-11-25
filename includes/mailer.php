<?php
// includes/mailer.php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

function send_verification_email($email, $token, $name = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'unilis512@gmail.com';
       $mail->Password = 'cornnvrapsuinyvk';   // ← NO SPACES! Just 16 characters
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('unilis717@gmail.com', 'UNILIS');
        $mail->addAddress($email);

        $verify_link = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/verify.php?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your UNILIS Account';
        $mail->Body    = "
            <h2>Welcome to UNILIS" . ($name ? ", $name" : '') . "!</h2>
            <p>Click below to verify your email:</p>
            <p style='text-align:center;'>
                <a href='$verify_link' style='background:#007bff;color:white;padding:15px 40px;text-decoration:none;border-radius:8px;font-size:16px;'>Verify Email Now</a>
            </p>
            <p>Or copy: <code>$verify_link</code></p>
            <p>This link expires in " . TOKEN_EXPIRY_MINUTES . " minutes.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}