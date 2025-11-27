<?php
// attendance_functions.php — FINAL 100% WORKING VERSION
require_once '../config/db.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

// ========================
// CREATE ATTENDANCE SESSION
// ========================
function createAttendanceSession($unit_id, $lecturer_id, $duration_minutes, $send_email = false) {
    global $conn;

    // Generate unique 6-digit code
    do {
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT id FROM attendance_sessions WHERE session_code = '$code' LIMIT 1");
    } while ($check && $check->num_rows > 0);

    $deadline = date('Y-m-d H:i:s', time() + ($duration_minutes * 60));

    $sql = "INSERT INTO attendance_sessions 
            (unit_id, lecturer_id, session_code, duration_minutes, deadline, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisis", $unit_id, $lecturer_id, $code, $duration_minutes, $deadline);
    
    if (!$stmt->execute()) {
        return false;
    }
    $session_id = $conn->insert_id;

    // Get all students in the unit
    $students = $conn->query("
        SELECT s.id, s.name, s.email 
        FROM students s 
        JOIN student_units su ON s.id = su.student_id 
        WHERE su.unit_id = " . (int)$unit_id
    );

    while ($student = $students->fetch_assoc()) {
        // Create notification
        $title = "Attendance Code: $code";
        $message = "Mark your attendance for this lesson.\nCode expires at " . date('h:i A', strtotime($deadline));
        $link = "student_attendance.php?session=$session_id";

        $notif_sql = "INSERT INTO notifications 
                      (title, message, link, attendance_session_id, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";
        $nstmt = $conn->prepare($notif_sql);
        $nstmt->bind_param("sssi", $title, $message, $link, $session_id);
        $nstmt->execute();

        // Optional: Send email
        if ($send_email && filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
            $mail = new PHPMailer(true);
            try {
                // UPDATE THESE WITH YOUR REAL SMTP SETTINGS
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'your-email@gmail.com';     // CHANGE THIS
                $mail->Password   = 'your-app-password';        // CHANGE THIS
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('no-reply@unilis.ac.ke', 'UNILIS Attendance');
                $mail->addAddress($student['email']);
                $mail->Subject = "Attendance Code: $code";
                $mail->Body    = "<h2>Attendance Required</h2>
                                  <p><strong>Code:</strong> <span style='font-size:24px;color:#f59e0b;'>$code</span></p>
                                  <p>Valid until: " . date('h:i A', strtotime($deadline)) . "</p>
                                  <a href='https://yourdomain.com/$link'>Click here to mark attendance</a>";
                $mail->isHTML(true);
                $mail->send();
            } catch (Exception $e) {
                // Silent fail - don't break attendance
            }
        }
    }

    return [
        'session_id' => $session_id,
        'code'       => $code,
        'deadline'   => $deadline
    ];
}
?>