<?php
// includes/mailer.php — MAILTRAP SANDBOX (WORKS IMMEDIATELY IN DOCKER)
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

function send_verification_email($email, $token, $name = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = '541b1fd18a9d8c';           // Your real username
        $mail->Password   = 'your-full-16-char-password'; // ← PUT YOUR REAL PASSWORD HERE (the one ending with ed07)
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 2525;

        $mail->setFrom('no-reply@unilis.edu', 'UNILIS');
        $mail->addAddress($email);

        $verify_link = "http://localhost/verify.php?token=$token";

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your UNILIS Account';
        $mail->Body    = "
            <h2>Welcome to UNILIS" . ($name ? ", $name" : '') . "!</h2>
            <p>Please verify your email by clicking the button below:</p>
            <div style='text-align:center;margin:40px 0;'>
                <a href='$verify_link' style='background:#007bff;color:white;padding:16px 40px;text-decoration:none;border-radius:8px;font-size:18px;display:inline-block;'>Verify Email Now</a>
            </div>
            <p>Or copy this link:<br><code>$verify_link</code></p>
            <p><small>This link expires in " . (defined('TOKEN_EXPIRY_MINUTES') ? TOKEN_EXPIRY_MINUTES : 30) . " minutes.</small></p>
        ";

        $mail->send();
        error_log("EMAIL SENT via Mailtrap Sandbox to: $email");
        return true;
    } catch (Exception $e) {
        error_log("Mailtrap failed: " . $mail->ErrorInfo);
        return false;
    }
}