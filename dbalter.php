<?php
session_start();
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/mailer.php";
use PHPMailer\PHPMailer\PHPMailer;

// Hardcoded lecturer for testing
$lecturer_id = 1;

// Function to send attendance email
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
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:30px;border-radius:15px;text-align:center;background:#fff;'>
            <h2 style='color:#f59e0b;'>Attendance Started!</h2>
            <p>Hello <strong>$name</strong>,</p>
            <h3 style='color:#333;'>$unit_name</h3>
            <div style='background:#fef3c7;padding:25px;border-radius:12px;margin:25px 0;'>
                <p style='margin:5px;font-size:18px;color:#92400e;'>Your 6-Digit Code:</p>
                <h1 style='margin:15px 0;font-size:52px;color:#f59e0b;letter-spacing:10px;font-weight:bold;'>$code</h1>
                <p style='color:#dc2626;font-weight:bold;'>Valid until: ".date('h:i A', strtotime($deadline))."</p>
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
        echo "Email failed: " . $mail->ErrorInfo;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_id = (int)$_POST['unit_id'];
    $duration = (int)$_POST['duration'];

    global $conn;

    // Generate unique 6-digit code
    do {
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT id FROM attendance_sessions WHERE session_code='$code' LIMIT 1");
    } while ($check && $check->num_rows > 0);

    $deadline = date('Y-m-d H:i:s', time() + $duration*60);

    // Insert attendance session
    $stmt = $conn->prepare("INSERT INTO attendance_sessions (unit_id, lecturer_id, session_code, duration_minutes, deadline, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisis", $unit_id, $lecturer_id, $code, $duration, $deadline);
    $stmt->execute();
    $session_id = $conn->insert_id;
    $stmt->close();

    // Get unit info
    $unit_res = $conn->query("SELECT name, course_id, year, semester FROM units WHERE id=$unit_id");
    $unit = $unit_res->fetch_assoc();
    $unit_name = $unit['name'];

    // Get students for this course
    $course_id = (int)$unit['course_id'];
    $students_res = $conn->query("SELECT id, name, email FROM students WHERE course_id=$course_id");

    while ($student = $students_res->fetch_assoc()) {
        $student_id = $student['id'];
        $student_name = $student['name'];
        $student_email = $student['email'];

        // Auto-mark link
        $token = base64_encode("$session_id|$student_id|" . hash('sha256', $session_id.$student_id.'UNILIS2025'));
        $auto_link = "https://unilis.jhubafrica.com/student/student_auto_mark.php?token=".urlencode($token);

        // Insert attendance record
        $stmt = $conn->prepare("INSERT INTO attendance_records (session_id, student_id, attended, attended_at, created_at) VALUES (?, ?, 1, NOW(), NOW())");
        $stmt->bind_param("ii", $session_id, $student_id);
        $stmt->execute();
        $stmt->close();

        // Insert notification
        $title = "Attendance: $unit_name";
        $message = "Code: <strong>$code</strong> - Valid until ".date('h:i A', strtotime($deadline));
        $notif_stmt = $conn->prepare("INSERT INTO notifications (title, message, link, attendance_session_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $notif_stmt->bind_param("sssi", $title, $message, $auto_link, $session_id);
        $notif_stmt->execute();
        $notif_stmt->close();

        // Send email to test address
        send_attendance_email("mwendihillary21@gmail.com", $student_name, $code, $unit_name, $deadline, $auto_link);
    }

    echo "<h3 style='color:green;'>Attendance session created successfully!</h3>";
}

// Fetch units for lecturer 1
$units_res = $conn->query("
    SELECT u.id, u.name, c.name AS course_name, u.year, u.semester
    FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE lu.lecturer_id = 1
    ORDER BY u.name
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Attendance System</title>
</head>
<body>
    <h2>Test Attendance Generation</h2>
    <form method="POST">
        <label>Select Unit:</label>
        <select name="unit_id" required>
            <option value="">-- Choose Unit --</option>
            <?php while($unit = $units_res->fetch_assoc()): ?>
                <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['name'])." (".$unit['course_name']." Y".$unit['year']." S".$unit['semester'].")" ?></option>
            <?php endwhile; ?>
        </select>
        <br><br>
        <label>Duration (minutes):</label>
        <select name="duration">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="15">15</option>
        </select>
        <br><br>
        <button type="submit">Generate Attendance</button>
    </form>

    <?php
    // Display newly created session, records, and notifications
    if (isset($session_id)) {
        echo "<h3>Attendance Session (Newly Created):</h3>";
        $res = $conn->query("SELECT * FROM attendance_sessions WHERE id=$session_id");
        echo "<pre>"; print_r($res->fetch_assoc()); echo "</pre>";

        echo "<h3>Attendance Records (Newly Created):</h3>";
        $res = $conn->query("SELECT * FROM attendance_records WHERE session_id=$session_id");
        echo "<pre>"; print_r($res->fetch_all(MYSQLI_ASSOC)); echo "</pre>";

        echo "<h3>Notifications (Newly Created):</h3>";
        $res = $conn->query("SELECT * FROM notifications WHERE attendance_session_id=$session_id");
        echo "<pre>"; print_r($res->fetch_all(MYSQLI_ASSOC)); echo "</pre>";
    }

    // Display **all records in the three tables** for full verification
    echo "<h3>All Attendance Sessions:</h3>";
    $res = $conn->query("SELECT * FROM attendance_sessions ORDER BY id DESC");
    echo "<pre>"; print_r($res->fetch_all(MYSQLI_ASSOC)); echo "</pre>";

    echo "<h3>All Attendance Records:</h3>";
    $res = $conn->query("SELECT * FROM attendance_records ORDER BY id DESC");
    echo "<pre>"; print_r($res->fetch_all(MYSQLI_ASSOC)); echo "</pre>";

    echo "<h3>All Notifications:</h3>";
    $res = $conn->query("SELECT * FROM notifications ORDER BY id DESC");
    echo "<pre>"; print_r($res->fetch_all(MYSQLI_ASSOC)); echo "</pre>";

    $conn->close();
    ?>
</body>
</html>
