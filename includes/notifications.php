<?php
/**
 * Notification Management System
 * Handles creating, sending, and managing notifications and emails
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/email_system.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Detect whether notifications table supports per-user scoping columns.
 * Some deployments still use the legacy notifications schema without
 * user_id/user_role, so queries must gracefully fall back.
 */
function notifications_support_user_scope($conn) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    try {
        $hasUserId = false;
        $hasUserRole = false;

        $res = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_id'");
        if ($res && $res->num_rows > 0) {
            $hasUserId = true;
        }
        if ($res) {
            $res->close();
        }

        $res = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_role'");
        if ($res && $res->num_rows > 0) {
            $hasUserRole = true;
        }
        if ($res) {
            $res->close();
        }

        $cached = ($hasUserId && $hasUserRole);
    } catch (Exception $e) {
        error_log("notifications_support_user_scope check failed: " . $e->getMessage());
        $cached = false;
    }

    return $cached;
}

/**
 * Send notification and email to specific student on assignment submission
 */
function notify_student_assignment_submitted($conn, $student_id, $assignment_id, $student_name, $student_email) {
    try {
        // Get assignment details
        $stmt = $conn->prepare("
            SELECT a.title, a.description, u.name as unit_name 
            FROM assignments a 
            JOIN units u ON a.unit_id = u.id 
            WHERE a.id = ?
        ");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$assignment) return false;

        $title = "Assignment Submitted";
        $message = "You have successfully submitted the assignment: {$assignment['title']}";
        $link = "student/dashboard.php?view=assignments";

        // Create notification record
        $notif_stmt = $conn->prepare("INSERT INTO notifications (title, message, link, assignment_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $notif_stmt->bind_param("sssi", $title, $message, $link, $assignment_id);
        $notif_stmt->execute();
        $notif_stmt->close();

        // Send confirmation email
        send_email_student_submitted($student_email, $student_name, $assignment['title'], $assignment['unit_name']);

        return true;
    } catch (Exception $e) {
        error_log("Error notifying student submission: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification and email to lecturer when student submits assignment
 */
function notify_lecturer_assignment_submitted($conn, $lecturer_id, $student_name, $student_email, $assignment_id, $assignment_title) {
    try {
        // Get lecturer email
        $stmt = $conn->prepare("SELECT email FROM lecturers WHERE id = ?");
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $lecturer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$lecturer) return false;

        $title = "New Assignment Submission";
        $message = "{$student_name} has submitted the assignment: {$assignment_title}";
        $link = "lecturer/review_assignments.php?assignment_id={$assignment_id}";

        // Create notification record (scoped if columns exist, otherwise legacy global row)
        if (notifications_support_user_scope($conn)) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, link, assignment_id, is_read, created_at) VALUES (?, 'lecturer', ?, ?, ?, ?, 0, NOW())");
            $notif_stmt->bind_param("isssi", $lecturer_id, $title, $message, $link, $assignment_id);
        } else {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (title, message, link, assignment_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $notif_stmt->bind_param("sssi", $title, $message, $link, $assignment_id);
        }
        $notif_stmt->execute();
        $notif_stmt->close();

        // Send email to lecturer
        send_email_lecturer_submission($lecturer['email'], $student_name, $assignment_title);

        return true;
    } catch (Exception $e) {
        error_log("Error notifying lecturer submission: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notifications and emails to all students in a course when notes are uploaded
 */
function notify_students_notes_uploaded($conn, $unit_id, $lecturer_id, $notes_title, $notes_id) {
    try {
        $stats = [
            'success' => false,
            'students_total' => 0,
            'notifications_sent' => 0,
            'notifications_failed' => 0,
            'emails_sent' => 0,
            'emails_failed' => 0,
            'message' => ''
        ];

        // Get unit info (name + code)
        $stmt = $conn->prepare("
            SELECT name, code FROM units WHERE id = ?
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $unit = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$unit) {
            $stats['message'] = 'Unit not found.';
            return $stats;
        }

        // Get lecturer name
        $stmt = $conn->prepare("SELECT name FROM lecturers WHERE id = ?");
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $lecturer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $lecturer_name = $lecturer['name'] ?? 'Lecturer';
        $unit_code = $unit['code'] ?? $unit['name'];

        // Get the note file path for attachment
        $stmt = $conn->prepare("SELECT file_path FROM notes WHERE id = ?");
        $stmt->bind_param("i", $notes_id);
        $stmt->execute();
        $note_file = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $file_path = $note_file['file_path'] ?? '';

        // Get all students enrolled in this specific unit
        $stmt = $conn->prepare("
            SELECT s.id, s.name, s.email 
            FROM students s
            JOIN student_unit_enrollments sue ON s.id = sue.student_id
            WHERE sue.unit_id = ? AND s.is_verified = 1
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        $students = [];
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        if (empty($students)) {
            $stats['message'] = 'No enrolled students found for this unit.';
            return $stats;
        }

        $stats['students_total'] = count($students);

        $title = "New Notes Uploaded";
        $message = "New notes have been uploaded for {$unit['name']}: {$notes_title}";
        $link = "student/dashboard.php?view=notes&unit_id={$unit_id}";

        // Create individual notification rows (scoped if supported)
        if (notifications_support_user_scope($conn)) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, link, notes_id, is_read, created_at) VALUES (?, 'student', ?, ?, ?, ?, 0, NOW())");
        } else {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (title, message, link, notes_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        }
        
        foreach ($students as $student) {
            if (notifications_support_user_scope($conn)) {
                $notif_stmt->bind_param("isssi", $student['id'], $title, $message, $link, $notes_id);
            } else {
                $notif_stmt->bind_param("sssi", $title, $message, $link, $notes_id);
            }
            if ($notif_stmt->execute()) {
                $stats['notifications_sent']++;
            } else {
                $stats['notifications_failed']++;
            }
            
            // Send individual email with file attachment
            $emailSent = send_notes_email_with_attachment($student['email'], $student['name'], $lecturer_name, $unit_code, $notes_title, $file_path, $link);
            if ($emailSent) {
                $stats['emails_sent']++;
            } else {
                $stats['emails_failed']++;
            }
        }
        $notif_stmt->close();

        $stats['success'] = ($stats['notifications_sent'] > 0 || $stats['emails_sent'] > 0);
        $stats['message'] = 'Notifications and emails processed.';
        return $stats;
    } catch (Exception $e) {
        error_log("Error notifying notes uploaded: " . $e->getMessage());
        return [
            'success' => false,
            'students_total' => 0,
            'notifications_sent' => 0,
            'notifications_failed' => 0,
            'emails_sent' => 0,
            'emails_failed' => 0,
            'message' => 'Notification/email processing failed.'
        ];
    }
}

/**
 * Send individual email with notes file attached
 */
function send_notes_email_with_attachment($email, $student_name, $lecturer_name, $unit_code, $notes_title, $file_path, $link) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "{$lecturer_name} sent {$unit_code}";

        // Attach the file if it exists
        $full_path = __DIR__ . '/../assets/uploads/' . $file_path;
        if ($file_path && file_exists($full_path)) {
            $mail->addAttachment($full_path);
        }

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; background: #f4f4f4; margin: 0; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    <div style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0; font-size: 24px;'>📚 New Notes from {$lecturer_name}</h1>
                        <p style='margin: 5px 0 0; opacity: 0.9;'>{$unit_code}</p>
                    </div>
                    <div style='padding: 30px;'>
                        <p>Dear <strong>{$student_name}</strong>,</p>
                        <p><strong>{$lecturer_name}</strong> has uploaded new notes for <strong>{$unit_code}</strong>.</p>
                        <div style='background: #f8f9fa; padding: 20px; border-left: 4px solid #00f2fe; margin: 20px 0; border-radius: 0 8px 8px 0;'>
                            <p style='margin: 0;'><strong>File:</strong> {$notes_title}</p>
                        </div>
                        <p>The file is attached to this email. You can also view it online.</p>
                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='{$link}' style='background: #00f2fe; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;'>
                                📖 View Online
                            </a>
                        </div>
                        <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                        <p style='color: #7f8c8d; font-size: 12px;'>© UNILIS — This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Notes email failed to: {$email} - " . $e->getMessage());
        return false;
    }
}

/**
 * Send notifications and emails to all students when assignment is posted
 */
function notify_students_assignment_posted($conn, $unit_id, $assignment_id, $assignment_title, $deadline) {
    try {
        // Get unit and course info
        $stmt = $conn->prepare("
            SELECT name, course_id FROM units WHERE id = ?
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $unit = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$unit) return false;

        // Get all students enrolled in this specific unit
        $stmt = $conn->prepare("
            SELECT s.id, s.name, s.email 
            FROM students s
            JOIN student_unit_enrollments sue ON s.id = sue.student_id
            WHERE sue.unit_id = ? AND s.is_verified = 1
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        $students = [];
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        if (empty($students)) return false;

        $title = "New Assignment Posted";
        $message = "A new assignment has been posted for {$unit['name']}: {$assignment_title}";
        $link = "student/dashboard.php?view=assignments";

        // Create individual notification rows (scoped if supported)
        if (notifications_support_user_scope($conn)) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, link, assignment_id, is_read, created_at) VALUES (?, 'student', ?, ?, ?, ?, 0, NOW())");
        } else {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (title, message, link, assignment_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        }
        
        // Prepare recipients for bulk email
        $recipients = [];
        
        foreach ($students as $student) {
            if (notifications_support_user_scope($conn)) {
                $notif_stmt->bind_param("isssi", $student['id'], $title, $message, $link, $assignment_id);
            } else {
                $notif_stmt->bind_param("sssi", $title, $message, $link, $assignment_id);
            }
            $notif_stmt->execute();
            
            // Add to recipients list for email
            $recipients[] = [
                'email' => $student['email'],
                'name' => $student['name']
            ];
        }
        $notif_stmt->close();
        
        // Send bulk email notifications
        $email_subject = "✏️ New Assignment Posted: {$assignment_title}";
        send_bulk_notification_emails($recipients, $email_subject, $title, $message, $link, 'assignment');

        return true;
    } catch (Exception $e) {
        error_log("Error notifying assignment posted: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark notification as read
 */
function mark_notification_as_read($conn, $notification_id) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $notification_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count
 */
function get_unread_notification_count($conn, $user_id = null, $user_role = null) {
    try {
        // If user scope columns exist, filter by current user
        if ($user_id && $user_role && notifications_support_user_scope($conn)) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND user_role = ? AND is_read = 0");
            $stmt->bind_param("is", $user_id, $user_role);
        } else {
            // Fallback to original behavior (should not be used in production)
            $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE is_read = 0");
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get latest notifications
 */
function get_latest_notifications($conn, $limit = 5, $user_id = null, $user_role = null) {
    try {
        // If user scope columns exist, filter by current user
        if ($user_id && $user_role && notifications_support_user_scope($conn)) {
            $stmt = $conn->prepare("SELECT id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? AND user_role = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bind_param("isi", $user_id, $user_role, $limit);
        } else {
            // Fallback to original behavior (should not be used in production)
            $stmt = $conn->prepare("SELECT id, title, message, link, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT ?");
            $stmt->bind_param("i", $limit);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        return $notifications;
    } catch (Exception $e) {
        error_log("Error getting latest notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all notifications with pagination
 */
function get_all_notifications($conn, $page = 1, $per_page = 20, $user_id = null, $user_role = null) {
    try {
        $offset = ($page - 1) * $per_page;

        // Get total count
        if ($user_id && $user_role && notifications_support_user_scope($conn)) {
            $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND user_role = ?");
            $count_stmt->bind_param("is", $user_id, $user_role);
        } else {
            $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications");
        }
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();

        // Get notifications
        if ($user_id && $user_role && notifications_support_user_scope($conn)) {
            $stmt = $conn->prepare("SELECT id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? AND user_role = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("isii", $user_id, $user_role, $per_page, $offset);
        } else {
            $stmt = $conn->prepare("SELECT id, title, message, link, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $per_page, $offset);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();

        return [
            'notifications' => $notifications,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    } catch (Exception $e) {
        error_log("Error getting all notifications: " . $e->getMessage());
        return [
            'notifications' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => 0
        ];
    }
}

// ========================= EMAIL FUNCTIONS =========================

function send_email_student_submitted($email, $student_name, $assignment_title, $unit_name) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Assignment Submission Confirmation - {$assignment_title}";

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0;'>✓ Submission Received</h1>
                    </div>
                    <div style='padding: 30px;'>
                        <p>Dear <strong>{$student_name}</strong>,</p>
                        <p>Your assignment has been successfully submitted!</p>
                        <div style='background: #f5f5f5; padding: 20px; border-left: 4px solid #667eea; margin: 20px 0;'>
                            <p><strong>Assignment:</strong> {$assignment_title}</p>
                            <p><strong>Unit:</strong> {$unit_name}</p>
                            <p><strong>Submitted at:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                        </div>
                        <p>You can view your submission and any grades in your dashboard.</p>
                        <p>If you have any questions, please contact your lecturer.</p>
                        <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                        <p style='color: #888; font-size: 12px;'>This is an automated notification from UNILIS. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

function send_email_lecturer_submission($email, $student_name, $assignment_title) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "New Assignment Submission - {$assignment_title}";

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0;'>📝 New Submission</h1>
                    </div>
                    <div style='padding: 30px;'>
                        <p>Dear Lecturer,</p>
                        <p><strong>{$student_name}</strong> has submitted the assignment:</p>
                        <div style='background: #f5f5f5; padding: 20px; border-left: 4px solid #f5576c; margin: 20px 0;'>
                            <p><strong>Assignment:</strong> {$assignment_title}</p>
                            <p><strong>Submitted by:</strong> {$student_name}</p>
                            <p><strong>Submitted at:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                        </div>
                        <p>Please log in to your dashboard to review and grade this submission.</p>
                        <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                        <p style='color: #888; font-size: 12px;'>This is an automated notification from UNILIS.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

function send_email_notes_uploaded($email, $student_name, $unit_name, $notes_title) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "New Study Materials - {$unit_name}";

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0;'>📚 New Study Materials</h1>
                    </div>
                    <div style='padding: 30px;'>
                        <p>Dear <strong>{$student_name}</strong>,</p>
                        <p>New study materials have been uploaded for your unit:</p>
                        <div style='background: #f5f5f5; padding: 20px; border-left: 4px solid #00f2fe; margin: 20px 0;'>
                            <p><strong>Unit:</strong> {$unit_name}</p>
                            <p><strong>Materials:</strong> {$notes_title}</p>
                            <p><strong>Available since:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                        </div>
                        <p>Log in to your dashboard to download and review the materials.</p>
                        <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                        <p style='color: #888; font-size: 12px;'>This is an automated notification from UNILIS.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

function send_email_assignment_posted($email, $student_name, $unit_name, $assignment_title, $deadline) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "New Assignment - {$assignment_title}";

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0;'>✏️ New Assignment</h1>
                    </div>
                    <div style='padding: 30px;'>
                        <p>Dear <strong>{$student_name}</strong>,</p>
                        <p>A new assignment has been posted for your unit:</p>
                        <div style='background: #f5f5f5; padding: 20px; border-left: 4px solid #fa709a; margin: 20px 0;'>
                            <p><strong>Unit:</strong> {$unit_name}</p>
                            <p><strong>Assignment:</strong> {$assignment_title}</p>
                            <p><strong>Deadline:</strong> " . date('F j, Y \a\t g:i A', strtotime($deadline)) . "</p>
                        </div>
                        <p>Log in to your dashboard to view the assignment details and submit your work.</p>
                        <p style='color: #d32f2f; font-weight: bold;'>⏰ Make sure to submit before the deadline!</p>
                        <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                        <p style='color: #888; font-size: 12px;'>This is an automated notification from UNILIS.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}
?>
