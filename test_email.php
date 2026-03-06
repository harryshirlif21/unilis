<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'unilis.jhubafrica.com'; // ✅ Fixed: was 'localhost'
    $mail->SMTPAuth   = true;
    $mail->Username   = 'noreply@unilis.jhubafrica.com';
    $mail->Password   = 'Man.18hattan';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ✅ Proper SSL constant
    $mail->Port       = 465;

    $mail->setFrom('noreply@unilis.jhubafrica.com', 'UNILIS');
    $mail->addAddress('mwendihillary21@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'UNILIS Email Test';
    $mail->Body    = '<h2>UNILIS Mail Server Working ✅</h2>';

    $mail->send();
    echo "✅ Email sent successfully!";

} catch (Exception $e) {
    echo "❌ Email sending try failed: {$mail->ErrorInfo}";
}