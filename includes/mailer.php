<?php
require_once __DIR__ . '/../config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_verification_email($email, $token, $name = '') {
    error_log("=== MAILER CALLED → To: $email | Name: $name ===");

    $mail = getConfiguredMailer();
    $mail->addAddress($email);
    $mail->addReplyTo(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);

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


function send_password_reset_email($email, $token, $name = '') {
    error_log("=== RESET EMAIL CALLED → To: $email | Name: $name ===");

    $mail = getConfiguredMailer();
    $mail->addAddress($email);
    $mail->addReplyTo(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);

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