<?php
/**
 * UNILIS SmartLab Mailer Utility
 * Handles sending HTML emails for OTP codes and authentication codes
 */

class Mailer {
    private const FROM_EMAIL = 'noreply@unilis.jhubafrica.com';
    private const FROM_NAME = 'UNILIS SmartLab';
    
    /**
     * Send OTP code for biometric login simulation
     */
    public static function sendOTP(string $email, string $name, string $code): bool {
        $subject = 'UNILIS SmartLab - Your Login OTP Code';
        
        $htmlBody = self::getOTPTemplate($name, $code);
        
        return self::send($email, $name, $subject, $htmlBody);
    }
    
    /**
     * Send authentication code for lab session
     */
    public static function sendAuthCode(string $email, string $name, string $code, string $labName, string $practicalTitle, string $sessionDate): bool {
        $subject = "UNILIS SmartLab - Lab Session Code for {$practicalTitle}";
        
        $htmlBody = self::getAuthCodeTemplate($name, $code, $labName, $practicalTitle, $sessionDate);
        
        return self::send($email, $name, $subject, $htmlBody);
    }
    
    /**
     * Core email sending method using PHP mail()
     */
    private static function send(string $to, string $toName, string $subject, string $htmlBody): bool {
        $headers = [
            'From: ' . self::FROM_NAME . ' <' . self::FROM_EMAIL . '>',
            'Reply-To: ' . self::FROM_EMAIL,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $headersString = implode("\r\n", $headers);
        
        // Log email attempt for debugging
        error_log("Mailer: Sending email to {$to} with subject: {$subject}");
        
        $result = mail($to, $subject, $htmlBody, $headersString);
        
        if ($result) {
            error_log("Mailer: Email sent successfully to {$to}");
        } else {
            error_log("Mailer: Failed to send email to {$to}");
        }
        
        return $result;
    }
    
    /**
     * Generate OTP email template
     */
    private static function getOTPTemplate(string $name, string $code): string {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://unilis.jhubafrica.com/smart-lab';
        
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>UNILIS SmartLab - Your Login OTP Code</title>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8f9fa;'>
    <div style='max-width: 600px; margin: 40px auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;'>
        
        <!-- Header -->
        <div style='background-color: #1e293b; padding: 30px 40px; text-align: center;'>
            <h1 style='color: white; margin: 0; font-size: 28px; font-weight: bold;'>UNILIS SmartLab</h1>
            <p style='color: #94a3b8; margin: 10px 0 0 0; font-size: 16px;'>Secure Laboratory Management System</p>
        </div>
        
        <!-- Body -->
        <div style='padding: 40px;'>
            <h2 style='color: #1e293b; margin: 0 0 20px 0; font-size: 24px;'>Your Login OTP Code</h2>
            
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;'>
                Hi {$name},<br><br>
                You requested a one-time password (OTP) to login to UNILIS SmartLab. Use the code below to complete your authentication:
            </p>
            
            <!-- OTP Code Display -->
            <div style='background-color: #6366f1; border-radius: 8px; padding: 30px; text-align: center; margin: 30px 0;'>
                <p style='color: #e0e7ff; margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>Your OTP Code</p>
                <div style='color: white; font-size: 48px; font-weight: bold; letter-spacing: 8px; font-family: monospace;'>{$code}</div>
            </div>
            
            <p style='color: #ef4444; font-size: 14px; margin: 20px 0; text-align: center;'>
                <strong>This code will expire in 10 minutes</strong>
            </p>
            
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin: 30px 0 0 0;'>
                If you didn't request this OTP code, please ignore this email or contact our support team if you have concerns about your account security.
            </p>
        </div>
        
        <!-- Footer -->
        <div style='background-color: #f1f5f9; padding: 30px 40px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='color: #64748b; margin: 0; font-size: 14px;'>
                © 2026 UNILIS SmartLab. All rights reserved.<br>
                <a href='{$appUrl}' style='color: #6366f1; text-decoration: none;'>unilis.jhubafrica.com</a>
            </p>
        </div>
        
    </div>
</body>
</html>";
    }
    
    /**
     * Generate Auth Code email template
     */
    private static function getAuthCodeTemplate(string $name, string $code, string $labName, string $practicalTitle, string $sessionDate): string {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://unilis.jhubafrica.com/smart-lab';
        
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>UNILIS SmartLab - Lab Session Code</title>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8f9fa;'>
    <div style='max-width: 600px; margin: 40px auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;'>
        
        <!-- Header -->
        <div style='background-color: #1e293b; padding: 30px 40px; text-align: center;'>
            <h1 style='color: white; margin: 0; font-size: 28px; font-weight: bold;'>UNILIS SmartLab</h1>
            <p style='color: #94a3b8; margin: 10px 0 0 0; font-size: 16px;'>Secure Laboratory Management System</p>
        </div>
        
        <!-- Body -->
        <div style='padding: 40px;'>
            <h2 style='color: #1e293b; margin: 0 0 20px 0; font-size: 24px;'>Lab Session Access Code</h2>
            
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;'>
                Hi {$name},<br><br>
                A lab session has been scheduled for you. Use the access code below to login and join the session:
            </p>
            
            <!-- Session Details -->
            <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin: 30px 0;'>
                <h3 style='color: #1e293b; margin: 0 0 15px 0; font-size: 18px;'>Session Details</h3>
                <div style='color: #475569; font-size: 16px; line-height: 1.8;'>
                    <p style='margin: 8px 0;'><strong>Practical:</strong> {$practicalTitle}</p>
                    <p style='margin: 8px 0;'><strong>Laboratory:</strong> {$labName}</p>
                    <p style='margin: 8px 0;'><strong>Date:</strong> {$sessionDate}</p>
                </div>
            </div>
            
            <!-- Auth Code Display -->
            <div style='background-color: #0f766e; border-radius: 8px; padding: 30px; text-align: center; margin: 30px 0;'>
                <p style='color: #ccfbf1; margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>Your Access Code</p>
                <div style='color: white; font-size: 48px; font-weight: bold; letter-spacing: 6px; font-family: monospace;'>{$code}</div>
            </div>
            
            <div style='background-color: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 20px; margin: 30px 0;'>
                <h4 style='color: #92400e; margin: 0 0 10px 0; font-size: 16px;'>How to Join:</h4>
                <ol style='color: #92400e; margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.6;'>
                    <li>Go to the UNILIS SmartLab login page</li>
                    <li>Select the 'Auth Code' tab</li>
                    <li>Enter the 6-character code shown above</li>
                    <li>Select your name from the list and login</li>
                </ol>
            </div>
            
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin: 30px 0 0 0;'>
                If you have any questions about this session, please contact your lecturer or lab technician.
            </p>
        </div>
        
        <!-- Footer -->
        <div style='background-color: #f1f5f9; padding: 30px 40px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='color: #64748b; margin: 0; font-size: 14px;'>
                © 2026 UNILIS SmartLab. All rights reserved.<br>
                <a href='{$appUrl}' style='color: #6366f1; text-decoration: none;'>unilis.jhubafrica.com</a>
            </p>
        </div>
        
    </div>
</body>
</html>";
    }
    
    /**
     * Mask email address for display (e.g., john@example.com -> j***@example.com)
     */
    public static function maskEmail(string $email): string {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $local = $parts[0];
        $domain = $parts[1];
        
        if (strlen($local) <= 2) {
            $masked = $local[0] . str_repeat('*', strlen($local) - 1);
        } else {
            $masked = $local[0] . str_repeat('*', strlen($local) - 2) . substr($local, -1);
        }
        
        return $masked . '@' . $domain;
    }
}
?>
