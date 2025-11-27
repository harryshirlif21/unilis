<?php
require_once '../config/db.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';
require_once 'phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

// ========================
// 1. LECTURER: CREATE ATTENDANCE SESSION
// ========================
function createAttendanceSession($unit_id, $lecturer_id, $duration_minutes, $send_email = false) {
    global $conn;

    // Generate unique 6-digit code
    do {
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT id FROM attendance_sessions WHERE session_code = '$code' LIMIT 1");
    } while ($check->num_rows > 0);

    $deadline = date('Y-m-d H:i:s', time() + ($duration_minutes * 60));

    $sql = "INSERT INTO attendance_sessions 
            (unit_id, lecturer_id, session_code, duration_minutes, deadline) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisis", $unit_id, $lecturer_id, $code, $duration_minutes, $deadline);
    
    if (!$stmt->execute()) return false;
    $session_id = $conn->insert_id;

    // Get all students enrolled in this unit
    $students = $conn->query("
        SELECT s.id, s.name, s.email 
        FROM students s 
        JOIN student_units su ON s.id = su.student_id 
        WHERE su.unit_id = $unit_id
    ");

    while ($student = $students->fetch_assoc()) {
        $title = "Attendance Required";
        $message = "Please mark your attendance for this lesson before " . date('d M Y, h:i A', strtotime($deadline));
        $link = "student_attendance.php?session=$session_id";

        $notif_sql = "INSERT INTO notifications 
                      (title, message, link, attendance_session_id, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";
        $nstmt = $conn->prepare($notif_sql);
        $nstmt->bind_param("sssi", $title, $message, $link, $session_id);
        $nstmt->execute();

        // Optional Email
        if ($send_email && !empty($student['email'])) {
            $mail = new PHPMailer(true);
            try {
                // Your SMTP settings here (same as your current system)
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'your-email@gmail.com';  // Change
                $mail->Password = 'your-app-password';     // Change
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('no-reply@yourschool.ac.ke', 'Attendance System');
                $mail->addAddress($student['email'], $student['name']);
                $mail->Subject = "Attendance Code: $code";
                $mail->Body = "Hello {$student['name']},\n\n"
                            . "Your lecturer has started attendance.\n\n"
                            . "CODE: <strong style='font-size:18px;color:#d32f2f;'>$code</strong>\n"
                            . "Valid until: " . date('d M Y, h:i A', strtotime($deadline)) . "\n\n"
                            . "Click here to mark: https://yoursite.com/$link";

                $mail->isHTML(true);
                $mail->send();
            } catch (Exception $e) {
                // Silent fail or log
            }
        }
    }

    return ['session_id' => $session_id, 'code' => $code, 'deadline' => $deadline];
}

// ========================
// 2. STUDENT: SUBMIT ATTENDANCE
// ========================
function submitAttendance($session_id, $student_id, $code_entered) {
    global $conn;

    $session_id = (int)$session_id;
    $student_id = (int)$student_id;

    $now = date('Y-m-d H:i:s');

    // Validate session + code + deadline + not already attended
    $sql = "SELECT s.*, ar.id as already_attended 
            FROM attendance_sessions s 
            LEFT JOIN attendance_records ar ON ar.session_id = s.id AND ar.student_id = ?
            WHERE s.id = ? AND s.session_code = ? AND s.deadline >= ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $student_id, $session_id, $code_entered, $now);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0 || $result->fetch_assoc()['already_attended']) {
        return ['success' => false, 'message' => 'Invalid or expired code'];
    }

    // Mark attendance
    $insert = "INSERT INTO attendance_records (session_id, student_id, attended, attended_at) 
               VALUES (?, ?, 1, NOW())";
    $istmt = $conn->prepare($insert);
    $istmt->bind_param("ii", $session_id, $student_id);
    $istmt->execute();

    // Update notification to read
    $conn->query("UPDATE notifications SET is_read = 1 WHERE attendance_session_id = $session_id AND link LIKE '%session=$session_id'");

    return ['success' => true, 'message' => 'Attendance recorded successfully!'];
}

// ========================
// 3. GET ATTENDANCE TABLE FOR LECTURER DASHBOARD
// ========================
function getAttendanceReport($unit_id, $lecturer_id) {
    global $conn;

    // Verify lecturer teaches this unit
    $check = $conn->query("SELECT 1 FROM lecturer_units WHERE lecturer_id = $lecturer_id AND unit_id = $unit_id");
    if ($check->num_rows === 0) return false;

    $sessions = $conn->query("
        SELECT id, session_code, created_at, deadline 
        FROM attendance_sessions 
        WHERE unit_id = $unit_id AND lecturer_id = $lecturer_id 
        ORDER BY created_at DESC
    ");

    $students = $conn->query("
        SELECT s.id, s.reg_no, s.name 
        FROM students s 
        JOIN student_units su ON s.id = su.student_id 
        WHERE su.unit_id = $unit_id 
        ORDER BY s.reg_no
    ");

    $report = [];
    $session_list = [];

    while ($sess = $sessions->fetch_assoc()) {
        $session_list[] = $sess;
    }

    while ($stu = $students->fetch_assoc()) {
        $row = ['student' => $stu, 'attendance' => [], 'total' => 0];
        foreach ($session_list as $sess) {
            $rec = $conn->query("
                SELECT attended FROM attendance_records 
                WHERE session_id = {$sess['id']} AND student_id = {$stu['id']}
            ")->fetch_assoc();
            $present = $rec ? $rec['attended'] : 0;
            $row['attendance'][] = $present;
            $row['total'] += $present;
        }
        $report[] = $row;
    }

    return ['sessions' => $session_list, 'report' => $report, 'total_sessions' => count($session_list)];
}
?>