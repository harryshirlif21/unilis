<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {

    // SMTP SERVER SETTINGS
    $mail->isSMTP();
    $mail->Host       = 'unilis.jhubafrica.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'noreply@unilis.jhubafrica.com';
    $mail->Password   = 'Man.18hattan';   // your email password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
    $mail->Port       = 465;

    // SENDER
    $mail->setFrom('noreply@unilis.jhubafrica.com', 'UNILIS');

    // RECIPIENT
    $mail->addAddress('mwendihillary21@gmail.com');

    // EMAIL CONTENT
    $mail->isHTML(true);
    $mail->Subject = 'UNILIS Email Test';
    $mail->Body    = '<h2>UNILIS Mail Server Working ✅</h2>';

    $mail->send();

    echo "✅ Email sent successfully!";

} catch (Exception $e) {

    echo "❌ Email failed: {$mail->ErrorInfo}";

}