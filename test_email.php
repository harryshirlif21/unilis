<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // SMTP Server Settings
// SMTP Server Settings (Local Mail Server)
$mail->isSMTP();
$mail->Host       = 'localhost';   // Use local mail server
$mail->Port       = 25;            // Default local SMTP port
$mail->SMTPAuth   = false;         // No authentication needed locally
$mail->SMTPSecure = false;         // No SSL needed for localhost
    

    // Sender
    $mail->setFrom('noreply@unilis.jhubafrica.com', 'UNILIS');

    // Recipient (change to your personal email)
    $mail->addAddress('mwendihillary21@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'UNILIS Email Test';
    $mail->Body    = '<h2>UNILIS Mail Server Working ✅</h2>';

    $mail->send();
    echo "✅ Email sent successfully!";
} catch (Exception $e) {
    echo "❌ Email failed: {$mail->ErrorInfo}";
}