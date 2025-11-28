<?php
require_once '../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// ========================
// SEND ATTENDANCE EMAIL
// ========================
function send_attendance_email($email, $name, $code, $unit_name, $deadline, $link) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'unilis512@gmail.com'; 
        $mail->Password   = 'sbmxmiafbtfkmkck'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('unilis512@gmail.com', 'UNILIS');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Attendance Code for $unit_name";
        $mail->Body = "
        <html>
        <body>
            <p>Hello <strong>$name</strong>,</p>
            <p>Attendance for <strong>$unit_name</strong> has started.</p>
            <p><strong>6-Digit Code:</strong> $code</p>
            <p>Valid until: ".date('h:i A', strtotime($deadline))."</p>
            <p><a href='$link' style='padding:10px 15px;background:#f59e0b;color:white;border-radius:5px;text-decoration:none;'>Mark Attendance</a></p>
        </body>
        </html>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("Attendance email failed: " . $mail->ErrorInfo);
    }
}

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

    // Insert attendance session
    $sql = "INSERT INTO attendance_sessions 
            (unit_id, lecturer_id, session_code, duration_minutes, deadline, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisis", $unit_id, $lecturer_id, $code, $duration_minutes, $deadline);

    if (!$stmt->execute()) {
        return false;
    }
    $session_id = $conn->insert_id;

    // Get unit and course info
    $res = $conn->query("SELECT u.name AS unit_name, u.course_id FROM units u WHERE u.id = ".(int)$unit_id);
    $unit_data = $res->fetch_assoc();
    $unit_name = htmlspecialchars($unit_data['unit_name']);
    $course_id = (int)$unit_data['course_id'];

    // Get all students in this course and same year as unit (if needed)
    $students = $conn->query("
        SELECT s.id, s.name, s.email 
        FROM students s 
        WHERE s.course_id = $course_id
    ");

    while ($student = $students->fetch_assoc()) {
        $student_id = $student['id'];
        $student_name = $student['name'];
        $student_email = $student['email'];

        // Create notification
        $title = "Attendance Started for $unit_name";
        $message = "Your lecturer started attendance for <strong>$unit_name</strong>.<br>Code: <strong>$code</strong>.<br>Valid until <strong>".date('h:i A', strtotime($deadline))."</strong>.";
        $link = "student_attendance.php?session=$session_id";

        $stmt_notif = $conn->prepare("
            INSERT INTO notifications (student_id, title, message, link, attendance_session_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt_notif->bind_param("isssi", $student_id, $title, $message, $link, $session_id);
        $stmt_notif->execute();
        $stmt_notif->close();

        // Send email if requested
        if ($send_email && filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
            send_attendance_email($student_email, $student_name, $code, $unit_name, $deadline, $link);
        }
    }

    return [
        'session_id' => $session_id,
        'code'       => $code,
        'deadline'   => $deadline,
        'unit_name'  => $unit_name
    ];
}
?>
