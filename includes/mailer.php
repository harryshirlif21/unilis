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
        $mail->Body = "
<html>
<head>
    <style>
        .email-container {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 20px;
        }
        .box {
            max-width: 600px;
            background: white;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .btn {
            display: inline-block;
            background: #007bff;
            color: white !important;
            padding: 14px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
            margin-top: 20px;
        }
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>

<body>
<div class='email-container'>
    <div class='box'>
        <div class='title'>UNILIS Email Verification</div>

        <p>Hello <strong>{$name}</strong>,</p>

        <p>Thank you for creating an account on <strong>UNILIS</strong>.<br>
           Please click the button below to verify your email address:</p>

        <a class='btn' href='{$verify_link}'>Verify Your Email</a>

        <p>If the button above does not work, copy and paste this link into your browser:</p>
        <p><a href='{$verify_link}'>{$verify_link}</a></p>

        <div class='footer'>
            © " . date('Y') . " UNILIS. All rights reserved.
        </div>
    </div>
</div>
</body>
</html>
";

        $mail->send();
        error_log("VERIFICATION EMAIL SENT (Gmail) → $email | $verify_link");
        return true;
    } catch (Exception $e) {
        error_log("Gmail send failed: " . $mail->ErrorInfo);
        return false;
    }
}