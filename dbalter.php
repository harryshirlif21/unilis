<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/mailer.php";
use PHPMailer\PHPMailer\PHPMailer;

// ========================
// SEND ATTENDANCE EMAIL
// ========================
function send_attendance_email($email, $name, $code, $unit_name, $deadline, $auto_link) {
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
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = "Attendance: $unit_name - Code $code";

        $mail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;background:#fff;padding:30px;border-radius:15px;text-align:center;'>
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
        error_log("Email failed for $email: " . $mail->ErrorInfo);
    }
}

// ========================
// HANDLE FORM SUBMISSION
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_id      = (int)($_POST['unit_id'] ?? 0);
    $duration     = (int)($_POST['duration'] ?? 10);
    $send_email   = isset($_POST['send_email']);
    $lecturer_id  = $_SESSION['user_id'] ?? 0;

    if ($unit_id && $lecturer_id) {
        // Generate unique 6-digit code
        do {
            $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $check = $conn->query("SELECT id FROM attendance_sessions WHERE session_code = '$code' LIMIT 1");
        } while ($check && $check->num_rows > 0);

        $deadline = date('Y-m-d H:i:s', time() + ($duration * 60));

        // Insert attendance session
        $stmt = $conn->prepare("
            INSERT INTO attendance_sessions (unit_id, lecturer_id, session_code, duration_minutes, deadline, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iisis", $unit_id, $lecturer_id, $code, $duration, $deadline);
        $stmt->execute();
        $session_id = $conn->insert_id;
        $stmt->close();

        // Get unit name and course_id
        $res = $conn->query("SELECT name, course_id FROM units WHERE id = $unit_id");
        $unit = $res->fetch_assoc();
        $unit_name = $unit['name'] ?? "Unit";
        $course_id = $unit['course_id'] ?? 0;

        // Fetch students for this course
        $students = $conn->query("SELECT id, name, email FROM students WHERE course_id = $course_id");

        while ($student = $students->fetch_assoc()) {
            $student_id    = $student['id'];
            $student_name  = $student['name'];
            $student_email = $student['email'];

            // Insert attendance record (default 0)
            $insert = $conn->prepare("
                INSERT INTO attendance_records (session_id, student_id, attended, attended_at, created_at)
                VALUES (?, ?, 0, NULL, NOW())
            ");
            $insert->bind_param("ii", $session_id, $student_id);
            $insert->execute();
            $insert->close();

            // Send email
            if ($send_email && filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
                $base_url = "https://unilis.jhubafrica.com";
                $token = base64_encode("$session_id|$student_id|" . hash('sha256', $session_id . $student_id . 'UNILIS2025'));
                $auto_link = "$base_url/student/student_auto_mark.php?token=" . urlencode($token);
                send_attendance_email($student_email, $student_name, $code, $unit_name, $deadline, $auto_link);
            }
        }

        // ========================
        // SHOW RECORDS IN TABLES
        // ========================
        echo "<h3 style='color:green;'>Attendance Session Created!</h3>";
        echo "<p>6-Digit Code: <strong>$code</strong> | Deadline: $deadline</p>";

        // Attendance Sessions
        echo "<h4>attendance_sessions</h4>";
        $session_res = $conn->query("SELECT * FROM attendance_sessions WHERE id = $session_id");
        echo "<pre>" . print_r($session_res->fetch_all(MYSQLI_ASSOC), true) . "</pre>";

        // Attendance Records
        echo "<h4>attendance_records</h4>";
        $records_res = $conn->query("SELECT * FROM attendance_records WHERE session_id = $session_id");
        echo "<pre>" . print_r($records_res->fetch_all(MYSQLI_ASSOC), true) . "</pre>";

        // Notifications
        echo "<h4>notifications</h4>";
        $notif_res = $conn->query("SELECT * FROM notifications WHERE attendance_session_id = $session_id");
        echo "<pre>" . print_r($notif_res->fetch_all(MYSQLI_ASSOC), true) . "</pre>";

    } else {
        echo "<p style='color:red;font-weight:bold;'>Please select a valid unit.</p>";
    }
}
?>

<!-- SIMPLE FORM -->
<h2>Create Attendance Session</h2>
<form method="POST">
    <label>Select Unit:</label>
    <select name="unit_id" required>
        <option value="">-- Choose Unit --</option>
        <?php
        $lecturer_id = $_SESSION['user_id'] ?? 0;
        if ($lecturer_id > 0) {
            $units_query = $conn->query("
                SELECT u.id, u.name, c.name AS course_name, u.year, u.semester
                FROM units u
                JOIN lecturer_units lu ON u.id = lu.unit_id
                LEFT JOIN courses c ON u.course_id = c.id
                WHERE lu.lecturer_id = $lecturer_id
                ORDER BY u.name
            ");
            while ($unit = $units_query->fetch_assoc()) {
                echo "<option value='{$unit['id']}' data-course='{$unit['course_name']}' data-year='{$unit['year']}' data-semester='{$unit['semester']}'>{$unit['name']}</option>";
            }
        }
        ?>
    </select>
    <br><br>

    <label>Duration (minutes):</label>
    <select name="duration" required>
        <option value="5">5</option>
        <option value="10" selected>10</option>
        <option value="15">15</option>
        <option value="30">30</option>
        <option value="60">60</option>
    </select>
    <br><br>

    <label>
        <input type="checkbox" name="send_email"> Send emails to students
    </label>
    <br><br>

    <button type="submit">Generate Attendance</button>
</form>
