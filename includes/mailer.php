<?php
// includes/mailer.php — FINAL VERSION FOR LIVE SERVER + MAILTRAP
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

function send_verification_email($email, $token, $name = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = '541b1fd18a9d8c';
        $mail->Password   = 'cornnvrapsuinyvk'; // ← PUT YOUR REAL ONE (ends with ed07)
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 2525;

        $mail->setFrom('no-reply@unilis.jhubafrica.com', 'UNILIS');
        $mail->addAddress($email);

        // CORRECT LIVE VERIFICATION LINK
        $verify_link = "https://unilis.jhubafrica.com/verify.php?token=$token";

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your UNILIS Account';
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:40px auto;padding:30px;background:#f9f9f9;border-radius:12px;text-align:center;'>
                <h1 style='color:#007bff;'>Welcome to UNILIS!</h1>
                <p>Hi" . ($name ? " <strong>$name</strong>" : '') . ",</p>
                <p>Please verify your email address to activate your account:</p>
                <p style='margin:40px 0;'>
                    <a href='$verify_link' style='background:#007bff;color:white;padding:16px 40px;text-decoration:none;border-radius:8px;font-size:18px;display:inline-block;'>Verify Email Now</a>
                </p>
                <p>Or copy this link:<br>
                   <code style='background:#eee;padding:10px;word-break:break-all;display:inline-block;'>$verify_link</code>
                </p>
                <p><small>This link expires in 30 minutes.</small></p>
                <hr style='margin:40px 0;border:none;border-top:1px solid #ddd;'>
                <p style='color:#666;font-size:12px;'>© 2025 UNILIS • University Learning Information System</p>
            </div>
        ";

        $mail->send();
        error_log("EMAIL SENT via Mailtrap to: $email | Link: $verify_link");
        return true;
    } catch (Exception $e) {
        error_log("Mailtrap failed: " . $mail->ErrorInfo);
        return false;
    }
}