<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'localhost';
    $mail->SMTPAuth   = false;
    $mail->SMTPSecure = false;
    $mail->Port       = 25;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ];

    $mail->setFrom('noreply@unilis.jhubafrica.com', 'UNILIS');
    $mail->addReplyTo('noreply@unilis.jhubafrica.com', 'UNILIS');
    $mail->addAddress('mwendihillary21@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'UNILIS Email Test';
    $mail->Body    = '
        <div style="font-family: Arial, sans-serif; padding: 20px;">
            <h2 style="color: #2c3e50;">UNILIS Mail Server Working ✅</h2>
            <p>This is a test email from the UNILIS system.</p>
            <p style="color: #7f8c8d; font-size: 12px;">This is an automated message, please do not reply.</p>
        </div>
    ';
    $mail->AltBody = 'UNILIS Mail Server Working. This is a test email from the UNILIS system.';

    $mail->send();
    echo "✅ Email sent successfully!";

} catch (Exception $e) {
    echo "❌ Email sending failed: {$mail->ErrorInfo}";
}