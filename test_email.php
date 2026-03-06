<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mwendihillary21@gmail.com';
    $mail->Password   = 'mjiu ogiv oedp wlik';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('noreply@unilis.jhubafrica.com', 'UNILIS');
    $mail->addReplyTo('noreply@unilis.jhubafrica.com', 'UNILIS');
    $mail->addAddress('mwendihillary21@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'UNILIS Email Test';
    $mail->Body    = '<h2>UNILIS Mail Server Working ✅</h2>';

    $mail->send();
    echo "✅ Email sent successfully!";

} catch (Exception $e) {
    echo "❌ Email sending failed: {$mail->ErrorInfo}";
}