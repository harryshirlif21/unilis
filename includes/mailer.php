<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
// stmp mail password: sbmx miaf btfk mkck
function send_verification_email($email, $token, $name = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'unilis512@gmail.com';           // ← CHANGE THIS
        $mail->Password   = 'sbmxmiafbtfkmkck';     // ← CHANGE THIS
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('unilis512@gmail.com', 'UNILIS');

        $mail->addAddress($email);

        $verify_link = "https://unilis.jhubafrica.com/verify.php?token=$token";

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your UNILIS Account';
        $mail->Body    = " [same HTML as before] ";

        $mail->send();
        error_log("VERIFICATION EMAIL SENT (Gmail) → $email | $verify_link");
        return true;
    } catch (Exception $e) {
        error_log("Gmail send failed: " . $mail->ErrorInfo);
        return false;
    }
}