<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/student_attendance.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json'); // Ensure JSON response

// ========================
// ENHANCED ATTENDANCE SYSTEM
// ========================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create_enhanced_attendance') {
        $unit_id = intval($_POST['unit_id'] ?? 0);
        $duration = intval($_POST['duration'] ?? 10);
        $send_email = isset($_POST['send_email']) ? true : false;
        $lecturer_id = $_SESSION['user_id'] ?? 0;
        
        if (!$unit_id || !$lecturer_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid unit or lecturer']);
            exit;
        }
        
        try {
            $result = createEnhancedAttendanceSession($conn, $unit_id, $lecturer_id, $duration, $send_email);
            
            echo json_encode([
                'success' => true,
                'message' => 'Enhanced attendance session created successfully',
                'data' => $result
            ]);
        } catch (Exception $e) {
            error_log("Error creating enhanced attendance: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create attendance session']);
        }
        exit;
    }
}

// ========================
// LEGACY ATTENDANCE EMAIL (Keep for backward compatibility)
// ========================
function send_attendance_email($email, $name, $code, $unit_name, $deadline, $auto_link) {
    $mail = getConfiguredMailer();
    $mail->addAddress($email, $name);

    try {
        $mail->isHTML(true);
        $mail->Subject = "Attendance: $unit_name - Code $code";

        $mail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;background:#fff;padding:30px;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,0.1);text-align:center;'>
            <h2 style='color:#f59e0b;'>Attendance Started!</h2>
            <p>Hello <strong>$name</strong>,</p>
            <h3 style='color:#333;'>$unit_name</h3>
            <div style='background:#fef3c7;padding:25px;border-radius:12px;margin:25px 0;'>
                <p style='margin:5px;font-size:18px;color:#92400e;'>Your 6-Digit Code:</p>
                <h1 style='margin:15px 0;font-size:52px;color:#f59e0b;letter-spacing:10px;font-weight:bold;'>$code</h1>
                <p style='color:#dc2626;font-weight:bold;'>Valid until: " . date('h:i A', strtotime($deadline)) . "</p>
            </div>
            <div style='margin:30px 0;'>
                <a href='$auto_link' style='background:#f59e0b;color:white;padding:18px 40px;text-decoration:none;border-radius:12px;font-size:20px;font-weight:bold;display:inline-block;'>
                    Click Here to Mark Attendance (Instant)
                </a>
            </div>
        </div>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("Email failed for $email: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
    }
}

// ========================
// CREATE ATTENDANCE SESSION
// ========================
function createAttendanceSession($conn, $unit_id, $lecturer_id, $duration_minutes, $send_email = false) {
    // Generate unique 6-digit code
    do {
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT id FROM attendance_sessions WHERE session_code = '$code' LIMIT 1");
    } while ($check && $check->num_rows > 0);

    $deadline = date('Y-m-d H:i:s', time() + ($duration_minutes * 60));

    // Insert session
    $stmt = $conn->prepare("
        INSERT INTO attendance_sessions 
        (unit_id, lecturer_id, session_code, duration_minutes, deadline, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iisis", $unit_id, $lecturer_id, $code, $duration_minutes, $deadline);
    $stmt->execute();
    $session_id = $conn->insert_id;
    $stmt->close();

    // Get unit & course info
    $unit_res = $conn->query("SELECT name, course_id FROM units WHERE id = $unit_id");
    $unit = $unit_res->fetch_assoc();
    $unit_name = $unit['name'];
    $course_id = $unit['course_id'];

    // Get students for this course
    $students = $conn->query("SELECT id, name, email FROM students WHERE course_id = $course_id");

    while ($student = $students->fetch_assoc()) {
        $student_id    = $student['id'];
        $student_name  = $student['name'];
        $student_email = $student['email'];

        // Pre-populate attendance_records (attended = 0)
        $stmt = $conn->prepare("
            INSERT INTO attendance_records (session_id, student_id, attended, attended_at, created_at)
            VALUES (?, ?, 0, NULL, NOW())
        ");
        $stmt->bind_param("ii", $session_id, $student_id);
        $stmt->execute();
        $stmt->close();

        // Prepare auto-mark link using 6-digit code
        $base_url = "https://unilis.jhubafrica.com";
        $auto_link = "$base_url/student/student_auto_mark.php?code=$code&student_id=$student_id";

        // Insert notifications for each student
        $title = "Attendance: $unit_name";
        $message = "Code: <strong style='color:#f59e0b;font-size:1.5em;'>$code</strong><br>Valid until " . date('h:i A', strtotime($deadline));
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, user_role, title, message, link, attendance_session_id, created_at) 
            VALUES (?, 'student', ?, ?, ?, ?, NOW())
        ");
        $notif_stmt->bind_param("isssi", $student_id, $title, $message, $auto_link, $session_id);
        $notif_stmt->execute();
        $notif_stmt->close();

        // Send email if requested
        if ($send_email && filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
            send_attendance_email($student_email, $student_name, $code, $unit_name, $deadline, $auto_link);
        }
    }

    return [
        'session_id' => $session_id,
        'code'       => $code,
        'deadline'   => $deadline,
        'unit_name'  => $unit_name
    ];
}

// ========================
// HANDLE AJAX REQUEST
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $duration = intval($_POST['duration'] ?? 10);
    $send_email = isset($_POST['send_email']) ? true : false;
    $lecturer_id = $_SESSION['user_id'] ?? 0;

    if (!$unit_id || !$lecturer_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid unit or lecturer ID']);
        exit;
    }

    try {
        $result = createAttendanceSession($conn, $unit_id, $lecturer_id, $duration, $send_email);
        echo json_encode([
            'success' => true,
            'message' => 'Attendance session created successfully',
            'data' => $result
        ]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create attendance session']);
    }
    exit;
}
?>
