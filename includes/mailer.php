<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
// stmp mail password: sbmx miaf btfk mkck
use PHPMailer\PHPMailer\Exception;

function send_verification_email($email, $token, $name = '') {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'unilis.jhubafrica.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'noreply@unilis.jhubafrica.com';
        $mail->Password   = 'Man.18hattan'; // ← from hosting
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port       = 465;

        $mail->setFrom('noreply@unilis.jhubafrica.com', 'UNILIS');
        $mail->addAddress($email);

        $verify_link = "https://unilis.jhubafrica.com/verify.php?token=$token";

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your UNILIS Account';
        $mail->Body = "
            <p>Hello <strong>{$name}</strong>,</p>
            <p>Please verify your email by clicking the link below:</p>
            <p><a href='{$verify_link}'>{$verify_link}</a></p>
        ";

        $mail->send();
        error_log("VERIFICATION EMAIL SENT → $email");
        return true;

    } catch (Exception $e) {
        error_log("Verification email failed: " . $mail->ErrorInfo);
        return false;
    }
}
