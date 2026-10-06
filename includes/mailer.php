<?php
require_once __DIR__ . '/../config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_verification_email($email, $token, $name = '') {
    error_log("=== MAILER CALLED → To: $email | Name: $name ===");

    $mail = getConfiguredMailer();
    $mail->addAddress($email);
    $mail->addReplyTo(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);

    try {
        $verify_link = "https://unilis.jhubafrica.com/verify.php?token=$token";

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your UNILIS Account';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 30px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                <div style='background-color: #2c3e50; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px;'>UNILIS</h1>
                    <p style='color: #bdc3c7; margin: 5px 0 0;'>University Learning Information System</p>
                </div>
                <div style='padding: 30px;'>
                    <h2 style='color: #2c3e50;'>Verify Your Email Address</h2>
                    <p style='color: #555;'>Hello <strong>{$name}</strong>,</p>
                    <p style='color: #555;'>Thank you for registering with UNILIS. Please verify your email address by clicking the button below:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$verify_link}' 
                           style='background-color: #2c3e50; color: white; padding: 14px 35px; 
                                  text-decoration: none; border-radius: 5px; font-size: 16px;
                                  display: inline-block;'>
                            ✅ Verify My Account
                        </a>
                    </div>
                    <p style='color: #555;'>Or copy and paste this link into your browser:</p>
                    <p style='background: #f4f4f4; padding: 10px; border-radius: 4px; word-break: break-all;'>
                        <a href='{$verify_link}' style='color: #3498db;'>{$verify_link}</a>
                    </p>
                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 25px 0;'>
                    <p style='color: #7f8c8d; font-size: 12px;'>⏳ This link expires in <strong>24 hours</strong>.</p>
                    <p style='color: #7f8c8d; font-size: 12px;'>If you did not create a UNILIS account, please ignore this email.</p>
                    <p style='color: #7f8c8d; font-size: 12px;'>© UNILIS — This is an automated message, please do not reply.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hello {$name},\n\nPlease verify your UNILIS account by visiting:\n{$verify_link}\n\nThis link expires in 24 hours.\n\nIf you did not register, please ignore this email.\n\n© UNILIS";

        $mail->send();
        error_log("=== VERIFICATION EMAIL SENT SUCCESSFULLY → $email ===");
        return true;

    } catch (Exception $e) {
        error_log("=== VERIFICATION EMAIL FAILED → " . $mail->ErrorInfo . " | Exception: " . $e->getMessage() . " ===");
        return false;
    }
}

function send_student_registration_start_email(string $email, string $token): bool
{
    $configuredBaseUrl = getenv('APP_BASE_URL');
    if ($configuredBaseUrl !== false && trim($configuredBaseUrl) !== '') {
        $baseUrl = rtrim(trim($configuredBaseUrl), '/');
    } elseif (PHP_SAPI === 'cli') {
        $baseUrl = 'http://localhost/unilis';
    } else {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (preg_match('/^(localhost|127\.0\.0\.1)(:\d{1,5})?$/', $host)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                ? 'https' : 'http';
            $baseUrl = $scheme . '://' . $host;
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            if (preg_match('#^(.*?)/student/#', $script, $matches)) {
                $baseUrl .= $matches[1];
            }
        } else {
            $baseUrl = 'https://unilis.jhubafrica.com';
        }
    }

    $link = $baseUrl . '/student/signup.php?email_token=' . rawurlencode($token);
    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Start your UNILIS student registration';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:28px;border:1px solid #e5e7eb;border-radius:10px;'>
                <h1 style='color:#1e3a8a;'>UNILIS student registration</h1>
                <p>Confirm that you can access this email address to begin creating your student account.</p>
                <p style='text-align:center;margin:28px 0;'>
                    <a href='{$safeLink}' style='background:#1e3a8a;color:#fff;padding:13px 24px;border-radius:7px;text-decoration:none;'>Confirm email and start registration</a>
                </p>
                <p>If the button does not work, open this link:</p>
                <p><a href='{$safeLink}'>{$safeLink}</a></p>
                <p style='color:#6b7280;font-size:13px;'>This link expires in 30 minutes. If you did not request it, you can ignore this email.</p>
            </div>
        ";
        $mail->AltBody = "Confirm your email and start UNILIS student registration:\n{$link}\n\nThis link expires in 30 minutes. If you did not request it, ignore this email.";
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Student registration email confirmation failed: ' . $e->getMessage());
        return false;
    }
}


function send_password_reset_email($email, $token, $name = '') {
    error_log("=== RESET EMAIL CALLED → To: $email | Name: $name ===");

    $mail = getConfiguredMailer();
    $mail->addAddress($email);
    $mail->addReplyTo(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);

    try {
        $reset_link = "https://unilis.jhubafrica.com/reset_password.php?token=$token";

        $mail->isHTML(true);
        $mail->Subject = 'Reset Your UNILIS Password';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 30px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                <div style='background-color: #2c3e50; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px;'>UNILIS</h1>
                    <p style='color: #bdc3c7; margin: 5px 0 0;'>University Learning Information System</p>
                </div>
                <div style='padding: 30px;'>
                    <h2 style='color: #2c3e50;'>Password Reset Request</h2>
                    <p style='color: #555;'>Hello <strong>{$name}</strong>,</p>
                    <p style='color: #555;'>We received a request to reset your UNILIS password. Click the button below to proceed:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$reset_link}' 
                           style='background-color: #e74c3c; color: white; padding: 14px 35px; 
                                  text-decoration: none; border-radius: 5px; font-size: 16px;
                                  display: inline-block;'>
                            🔑 Reset My Password
                        </a>
                    </div>
                    <p style='color: #555;'>Or copy and paste this link into your browser:</p>
                    <p style='background: #f4f4f4; padding: 10px; border-radius: 4px; word-break: break-all;'>
                        <a href='{$reset_link}' style='color: #3498db;'>{$reset_link}</a>
                    </p>
                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 25px 0;'>
                    <p style='color: #7f8c8d; font-size: 12px;'>⏳ This link expires in <strong>1 hour</strong>.</p>
                    <p style='color: #7f8c8d; font-size: 12px;'>If you did not request a password reset, please ignore this email. Your password will not be changed.</p>
                    <p style='color: #7f8c8d; font-size: 12px;'>© UNILIS — This is an automated message, please do not reply.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hello {$name},\n\nReset your UNILIS password by visiting:\n{$reset_link}\n\nThis link expires in 1 hour.\n\nIf you did not request this, please ignore this email.\n\n© UNILIS";

        $mail->send();
        error_log("=== RESET EMAIL SENT SUCCESSFULLY → $email ===");
        return true;

    } catch (Exception $e) {
        error_log("=== RESET EMAIL FAILED → " . $mail->ErrorInfo . " | Exception: " . $e->getMessage() . " ===");
        return false;
    }
}