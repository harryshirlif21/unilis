<?php
session_start();
require_once '../config/db.php';
require_once 'attendance_functions.php';
require_once '../includes/mailer.php'; // <- your PHPMailer code

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Get unit_id safely
$unit_id = 0;
if (!empty($_POST['unit_id']) && is_numeric($_POST['unit_id'])) {
    $unit_id = (int)$_POST['unit_id'];
} elseif (!empty($_GET['unit']) && is_numeric($_GET['unit'])) {
    $unit_id = (int)$_GET['unit'];
}

if ($unit_id <= 0) {
    die("<h3 style='color:red;text-align:center;margin:100px;'>Invalid unit selected.</h3>");
}

// Verify lecturer teaches this unit
$stmt = $conn->prepare("SELECT name FROM units WHERE id = ? AND id IN (SELECT unit_id FROM lecturer_units WHERE lecturer_id = ?)");
$stmt->bind_param("ii", $unit_id, $lecturer_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("<h3 style='color:red;text-align:center;margin:100px;'>Unauthorized access.</h3>");
}
$unit_name = htmlspecialchars($res->fetch_assoc()['name']);
$stmt->close();

// PROCESS FORM
$success = false;
$code = $deadline = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $duration   = max(1, min(120, (int)($_POST['duration'] ?? 10)));
    $send_email = !empty($_POST['send_email']);

    // --- Step 1: Create attendance session ---
    $code = random_int(100000, 999999);
    $deadline = date('Y-m-d H:i:s', strtotime("+$duration minutes"));

    $stmt = $conn->prepare("INSERT INTO attendance_sessions (unit_id, lecturer_id, code, deadline, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $unit_id, $lecturer_id, $code, $deadline);
    if ($stmt->execute()) {
        $session_id = $conn->insert_id;
        $success = true;

        // --- Step 2: Get all students in this unit ---
        $students = $conn->query("SELECT id, name, email FROM students WHERE unit_id = $unit_id");
        while ($student = $students->fetch_assoc()) {
            $student_id = $student['id'];
            $student_name = $student['name'];
            $student_email = $student['email'];

            // --- Step 3: Insert notification ---
            $title = "Attendance Started for $unit_name";
            $message = "Your lecturer started attendance for <strong>$unit_name</strong>. Code: <strong>$code</strong>. Valid until <strong>".date('h:i A', strtotime($deadline))."</strong>.";
            $link = "https://unilis.jhubafrica.com/student/attendance.php?session=$session_id";

            $stmt_notif = $conn->prepare("INSERT INTO notifications (student_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_notif->bind_param("isss", $student_id, $title, $message, $link);
            $stmt_notif->execute();
            $stmt_notif->close();

            // --- Step 4: Send email if checked ---
            if ($send_email) {
                send_attendance_email($student_email, $student_name, $code, $unit_name, $deadline, $link);
            }
        }
    }
    $stmt->close();
}

// --- Helper email function ---
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
?>
