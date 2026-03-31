<?php
require_once __DIR__ . '/../config/email.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Unified Email System for UNILIS
 * Handles notification emails and deadline reminders
 */

/**
 * Send notification email to user
 * @param string $email Recipient email
 * @param string $user_name Recipient name
 * @param string $subject Email subject
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $link Optional link for action
 * @param string $type Type of notification (notes, assignment, attendance, etc.)
 * @return bool Success status
 */
function send_notification_email($email, $user_name, $subject, $title, $message, $link = '', $type = 'general', &$errorMessage = null) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Get email template based on type
        $email_body = get_email_template($type, $title, $message, $link, $user_name);
        $mail->Body = $email_body;

        $mail->send();
        error_log("Notification email sent successfully to: $email");
        return true;
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        error_log("Notification email failed to: $email - " . $errorMessage);
        return false;
    }
}

/**
 * Send deadline reminder email
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $assignment_title Assignment title
 * @param string $unit_name Unit name
 * @param string $deadline Deadline datetime
 * @param int $hours_until_deadline Hours until deadline (24 or 12)
 * @return bool Success status
 */
function send_deadline_reminder_email($email, $student_name, $assignment_title, $unit_name, $deadline, $hours_until_deadline) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "⏰ Deadline Reminder: {$assignment_title}";

        $deadline_formatted = date('F j, Y \a\t g:i A', strtotime($deadline));
        $urgency_color = $hours_until_deadline <= 12 ? '#e74c3c' : '#f39c12';
        $urgency_text = $hours_until_deadline <= 12 ? 'URGENT' : 'REMINDER';

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; background: #f4f4f4; margin: 0; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    <div style='background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0; font-size: 24px;'>⏰ {$urgency_text}</h1>
                        <p style='margin: 5px 0 0; opacity: 0.9;'>Assignment Deadline Approaching</p>
                    </div>
                    <div style='padding: 30px;'>
                        <p>Dear <strong>{$student_name}</strong>,</p>
                        <p>This is a friendly reminder that your assignment deadline is approaching:</p>
                        
                        <div style='background: #f8f9fa; padding: 25px; border-left: 5px solid {$urgency_color}; margin: 25px 0; border-radius: 0 8px 8px 0;'>
                            <h3 style='margin: 0 0 15px; color: #2c3e50;'>{$assignment_title}</h3>
                            <p style='margin: 5px 0;'><strong>Unit:</strong> {$unit_name}</p>
                            <p style='margin: 5px 0;'><strong>Deadline:</strong> <span style='color: {$urgency_color}; font-weight: bold;'>{$deadline_formatted}</span></p>
                            <p style='margin: 5px 0;'><strong>Time remaining:</strong> <span style='color: {$urgency_color}; font-weight: bold;'>{$hours_until_deadline} hours</span></p>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='https://unilis.jhubafrica.com/student/dashboard.php?view=assignments' 
                               style='background: {$urgency_color}; color: white; padding: 15px 30px; 
                                      text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;
                                      display: inline-block; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                                📝 Submit Assignment Now
                            </a>
                        </div>
                        
                        <p style='color: #666; font-size: 14px;'>Make sure to submit your work before the deadline to avoid any penalties.</p>
                        
                        <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                        <p style='color: #7f8c8d; font-size: 12px;'>If you have already submitted this assignment, please ignore this email.</p>
                        <p style='color: #7f8c8d; font-size: 12px;'>Need help? Contact your lecturer or visit your dashboard.</p>
                        <p style='color: #7f8c8d; font-size: 12px;'>© UNILIS — This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "
Dear {$student_name},

This is a reminder that your assignment deadline is approaching:

Assignment: {$assignment_title}
Unit: {$unit_name}
Deadline: {$deadline_formatted}
Time remaining: {$hours_until_deadline} hours

Please submit your work before the deadline to avoid penalties.
Visit your dashboard: https://unilis.jhubafrica.com/student/dashboard.php?view=assignments

If you have already submitted this assignment, please ignore this email.
© UNILIS
        ";

        $mail->send();
        error_log("Deadline reminder sent successfully to: $email ({$hours_until_deadline}h remaining)");
        return true;
    } catch (Exception $e) {
        error_log("Deadline reminder failed to: $email - " . $e->getMessage());
        return false;
    }
}

/**
 * Get email template based on notification type
 * @param string $type Type of notification
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $link Optional action link
 * @param string $user_name Recipient name
 * @return string HTML email body
 */
function get_email_template($type, $title, $message, $link, $user_name) {
    $base_template = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; background: #f4f4f4; margin: 0; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                {header}
                <div style='padding: 30px;'>
                    <p>Dear <strong>{$user_name}</strong>,</p>
                    {content}
                    {action_button}
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #7f8c8d; font-size: 12px;'>© UNILIS — This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    switch ($type) {
        case 'notes':
            $header = "
                <div style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>📚 New Study Materials</h1>
                    <p style='margin: 5px 0 0; opacity: 0.9;'>Notes Uploaded</p>
                </div>
            ";
            $content = "<p>{$message}</p>";
            $action_button = $link ? "
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='{$link}' style='background: #00f2fe; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;'>
                        📖 View Notes
                    </a>
                </div>
            " : "";
            break;

        case 'assignment':
            $header = "
                <div style='background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>✏️ New Assignment</h1>
                    <p style='margin: 5px 0 0; opacity: 0.9;'>Assignment Posted</p>
                </div>
            ";
            $content = "<p>{$message}</p>";
            $action_button = $link ? "
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='{$link}' style='background: #fa709a; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;'>
                        📝 View Assignment
                    </a>
                </div>
            " : "";
            break;

        case 'attendance':
            $header = "
                <div style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>📋 Attendance Session</h1>
                    <p style='margin: 5px 0 0; opacity: 0.9;'>Mark Your Attendance</p>
                </div>
            ";
            $content = "<p>{$message}</p>";
            $action_button = $link ? "
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='{$link}' style='background: #f5576c; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;'>
                        ✅ Mark Attendance
                    </a>
                </div>
            " : "";
            break;

        case 'submission':
            $header = "
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>✓ Submission Received</h1>
                    <p style='margin: 5px 0 0; opacity: 0.9;'>Assignment Submitted</p>
                </div>
            ";
            $content = "<p>{$message}</p>";
            $action_button = $link ? "
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='{$link}' style='background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;'>
                        👁️ View Submission
                    </a>
                </div>
            " : "";
            break;

        default:
            $header = "
                <div style='background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>🔔 Notification</h1>
                    <p style='margin: 5px 0 0; opacity: 0.9;'>UNILIS Update</p>
                </div>
            ";
            $content = "<p>{$message}</p>";
            $action_button = $link ? "
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='{$link}' style='background: #34495e; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;'>
                        🔗 View Details
                    </a>
                </div>
            " : "";
            break;
    }

    return str_replace(['{header}', '{content}', '{action_button}'], [$header, $content, $action_button], $base_template);
}

/**
 * Send bulk notification emails to multiple users
 * @param array $recipients Array of ['email' => '', 'name' => ''] 
 * @param string $subject Email subject
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $link Optional link
 * @param string $type Notification type
 * @return array Results with success/failure counts
 */
function send_bulk_notification_emails($recipients, $subject, $title, $message, $link = '', $type = 'general') {
    $results = ['success' => 0, 'failed' => 0, 'errors' => []];
    
    foreach ($recipients as $recipient) {
        if (empty($recipient['email'])) continue;
        
        $success = send_notification_email(
            $recipient['email'], 
            $recipient['name'] ?? 'Student', 
            $subject, 
            $title, 
            $message, 
            $link, 
            $type
        );
        
        if ($success) {
            $results['success']++;
        } else {
            $results['failed']++;
            $results['errors'][] = "Failed to send to: {$recipient['email']}";
        }
    }
    
    return $results;
}
?>
