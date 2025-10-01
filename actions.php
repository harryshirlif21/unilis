<?php
require_once 'config/db.php';
//require_once 'vendor/autoload.php';
require_once 'vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Dompdf\Dompdf;
use Dompdf\Options;

// Helper: Safe action fetch
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// === STUDENT SIGNUP ===
if ($action === 'signup_student') {
    $reg_no = $_POST['reg_no'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $university_id = $_POST['university'];
    $department_id = $_POST['department'];
    $course_id = $_POST['course'];
    $year_of_study = $_POST['year_of_study'];
    $year_joined = $_POST['year_joined'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $_SESSION['signup_error'] = "Passwords do not match.";
        header("Location: signup.php");
        exit;
    }

    $password_hashed = password_hash($password, PASSWORD_BCRYPT);

    $check = $conn->prepare("SELECT id FROM students WHERE reg_no = ? OR email = ?");
    $check->bind_param("ss", $reg_no, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['signup_error'] = "Reg No or Email already registered.";
    } else {
        $stmt = $conn->prepare("INSERT INTO students (reg_no, name, email, university_id, department_id, course_id, year_of_study, year_joined, password)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiiiiss", $reg_no, $name, $email, $university_id, $department_id, $course_id, $year_of_study, $year_joined, $password_hashed);
        $stmt->execute() ? $_SESSION['signup_success'] = "Student registered successfully." : $_SESSION['signup_error'] = "Error: " . $stmt->error;
    }
    header("Location: index.html");
    exit;
}
// === UNIVERSAL LOGIN ===
if ($action === 'universal_login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Helper function for login
    function attemptLogin($conn, $table, $email, $password, $fields, $redirectPath, $role) {
    $query = "SELECT " . implode(", ", $fields) . " FROM $table WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        // Dynamically create the correct number of variables for bind_result
        $bindVars = [];
        for ($i = 0; $i < count($fields); $i++) {
            $bindVars[] = null;
        }

        // bind variables by reference
        $refs = [];
        foreach ($bindVars as $key => &$val) {
            $refs[$key] = &$val;
        }
        $stmt->bind_result(...$refs);
        $stmt->fetch();

        if (password_verify($password, $bindVars[1])) { // Assuming password is 2nd column
            $_SESSION['user_id'] = $bindVars[0]; // id
            $_SESSION['user_name'] = $bindVars[2]; // name
            $_SESSION['user_role'] = $role;

            if ($role === 'student') {
                $_SESSION['course_id'] = $bindVars[3];
                $_SESSION['year_of_study'] = $bindVars[4];
            }

            header("Location: $redirectPath");
            exit;
        }
    }

    $stmt->close();
    return false;
}

    // Try each role
    if (
        attemptLogin($conn, 'admins', $email, $password, ['id', 'password', 'name'], 'admin/dashboard.php', 'admin') ||
        attemptLogin($conn, 'lecturers', $email, $password, ['id', 'password', 'name'], 'lecturer/dashboard.php', 'lecturer') ||
        attemptLogin($conn, 'students', $email, $password, ['id', 'password', 'name', 'course_id', 'year_of_study'], 'student/dashboard.php', 'student')
    ) {
        // Already redirected inside attemptLogin if successful
        exit;
    }

    // If none matched
    $_SESSION['login_error'] = "Invalid credentials.";
    header("Location: login.php");
    exit;
}

// === ADD UNIVERSITY ===
if ($action === 'add_university') {
    $name = trim($_POST['university_name']);
    $stmt = $conn->prepare("SELECT id FROM universities WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['university_error'] = "University already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO universities (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $_SESSION['university_success'] = "University added.";
    }
    header("Location: admin/dashboard.php");
    exit;
}

// === ADD DEPARTMENT ===
if ($action === 'add_department') {
    $name = trim($_POST['department_name']);
    $university_id = intval($_POST['university_id']);

    // First check if department exists
    $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ? AND university_id = ?");
    $stmt->bind_param("si", $name, $university_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $_SESSION['department_error'] = "Department already exists in this university.";
    } else {
        // Insert new department with basic fields
        $stmt = $conn->prepare("INSERT INTO departments (name, university_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $university_id);
        
        if ($stmt->execute()) {
            $_SESSION['department_success'] = "Department added successfully.";
        } else {
            $_SESSION['department_error'] = "Error adding department: " . $stmt->error;
        }
    }
    $stmt->close();
    header("Location: admin/dashboard.php");
    exit;
}

// === ADD COURSE ===
if ($action === 'add_course') {
    $name = trim($_POST['course_name']);
    $dept_id = intval($_POST['department_id']);
    $duration = intval($_POST['duration']);
    $stmt = $conn->prepare("SELECT id FROM courses WHERE name = ? AND department_id = ?");
    $stmt->bind_param("si", $name, $dept_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['course_error'] = "Course already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (name, department_id, duration) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $name, $dept_id, $duration);
        $stmt->execute();
        $_SESSION['course_success'] = "Course added.";
    }
    header("Location: admin/dashboard.php");
    exit;
}

// === ADD LECTURER ===
if ($action === 'add_lecturer') {
    $name = $_POST['lecturer_name'];
    $email = $_POST['lecturer_email'];
    $password = password_hash($_POST['lecturer_password'], PASSWORD_DEFAULT);
    $university_id = $_POST['university_id'];
    $stmt = $conn->prepare("SELECT id FROM lecturers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['lecturer_error'] = "Lecturer already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO lecturers (name, email, password, university_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $email, $password, $university_id);
        $stmt->execute();
        $_SESSION['lecturer_success'] = "Lecturer added.";
    }
    header("Location: admin/dashboard.php");
    exit;
}
// add single unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_unit') {
    $unit_name = trim($_POST['unit_name'] ?? '');
    $unit_code = trim($_POST['unit_code'] ?? '');
    $course_id = intval($_POST['course_id'] ?? 0);
    $year = intval($_POST['year'] ?? 0);
    $semester = intval($_POST['semester'] ?? 0);

    if (!$unit_name || !$unit_code || !$course_id || !$year || !$semester) {
        echo "error: missing fields";
        exit;
    }

    // Check for duplicates
    $check = $conn->prepare("SELECT id FROM units WHERE code = ? AND course_id = ? AND year = ? AND semester = ?");
    $check->bind_param("siii", $unit_code, $course_id, $year, $semester);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "duplicate";
        $check->close();
        exit;
    }
    $check->close();

    // Insert unit
    $insert = $conn->prepare("INSERT INTO units (name, code, course_id, year, semester) VALUES (?, ?, ?, ?, ?)");
    if (!$insert) {
        echo "error: prepare failed - " . $conn->error;
        exit;
    }

    $insert->bind_param("ssiii", $unit_name, $unit_code, $course_id, $year, $semester);
    if ($insert->execute()) {
       // echo "success";
		echo '
<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background-color: #f4f4f4; font-family: Arial, sans-serif; color: #333;">
    <h2 style="margin-bottom: 20px;">Action Completed Successfully!</h2>
    <a href="admin/dashboard.php" style="padding: 12px 25px; background-color: #3498db; color: white; border-radius: 5px; text-decoration: none; font-size: 16px;">
        Go Back to Dashboard
    </a>
</div>';
exit;

    } else {
        echo "error: insert failed - " . $insert->error;
    }

    $insert->close();
    exit;
}

// === ADD MULTIPLE UNITS ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_multiple_units') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $year = intval($_POST['year'] ?? 0);
    $semester = intval($_POST['semester'] ?? 0);
    $unit_names = $_POST['unit_name'] ?? [];
    $unit_codes = $_POST['unit_code'] ?? [];

    if (!$course_id || !$year || !$semester) {
        $_SESSION['unit_error'] = "Course, Year, and Semester are required.";
        header("Location: admin/dashboard.php");
        exit;
    }

    if (count($unit_names) !== count($unit_codes)) {
        $_SESSION['unit_error'] = "Mismatch in number of unit names and codes.";
        header("Location: admin/dashboard.php");
        exit;
    }

    $inserted = 0;

    for ($i = 0; $i < count($unit_names); $i++) {
        $name = trim($unit_names[$i]);
        $code = trim($unit_codes[$i]);

        if ($name && $code) {
            $check = $conn->prepare("SELECT id FROM units WHERE code = ? AND course_id = ? AND year = ? AND semester = ?");
            $check->bind_param("siii", $code, $course_id, $year, $semester);
            $check->execute();
            $check->store_result();

            if ($check->num_rows === 0) {
                $insert = $conn->prepare("INSERT INTO units (name, code, course_id, year, semester) VALUES (?, ?, ?, ?, ?)");
                if ($insert) {
                    $insert->bind_param("ssiii", $name, $code, $course_id, $year, $semester);
                    if ($insert->execute()) {
                        $inserted++;
                    }
                    $insert->close();
                } else {
                    error_log("Insert prepare failed: " . $conn->error);
                }
            }
            $check->close();
        }
    }

    if ($inserted > 0) {
        $_SESSION['unit_success'] = "$inserted unit(s) added successfully.";
    } else {
        $_SESSION['unit_error'] = "No new units were added. They may already exist.";
    }

   echo '
<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background-color: #f4f4f4; font-family: Arial, sans-serif; color: #333;">
    <h2 style="margin-bottom: 20px;">Action Completed Successfully!</h2>
    <a href="admin/dashboard.php" style="padding: 12px 25px; background-color: #3498db; color: white; border-radius: 5px; text-decoration: none; font-size: 16px;">
        Go Back to Dashboard
    </a>
</div>';
    exit;
}


// === UPLOAD NOTES ===
if ($action === 'upload_notes') {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['upload_error'] = "Session expired. Please log in again.";
        header("Location: login.php");
        exit;
    }

    $unit_id = intval($_POST['unit_id']);
    $user_id = $_SESSION['user_id'];

    // 🔹 Get lecturer_id for the logged-in user
    $stmtL = $conn->prepare("SELECT id FROM lecturers WHERE user_id = ?");
    $stmtL->bind_param("i", $user_id);
    $stmtL->execute();
    $resultL = $stmtL->get_result();
    if ($resultL->num_rows === 0) {
        $_SESSION['upload_error'] = "Lecturer account not found.";
        header("Location: lecturer/dashboard.php");
        exit;
    }
    $lecturer = $resultL->fetch_assoc();
    $lecturer_id = $lecturer['id'];

    $files = $_FILES['notes_file'];
    $upload_dir = "assets/uploads/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // Normalize: handle single or multiple uploads
    $fileNames    = is_array($files['name']) ? $files['name'] : [$files['name']];
    $fileTmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];

    $uploadCount = 0;
    foreach ($fileNames as $index => $name) {
        if (empty($name)) continue; // skip empty input

        $filename = time() . "_" . basename($name);
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($fileTmpNames[$index], $target_path)) {
            // Save notes to DB
            $stmt = $conn->prepare("INSERT INTO notes (lecturer_id, unit_id, file_path, uploaded_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iis", $lecturer_id, $unit_id, $filename);
            $stmt->execute();

            // === Notifications ===
            $msg = "New notes uploaded for your unit.";
            $link = "student/notes.php?unit_id=" . $unit_id;

            // fetch students for this unit
            $students = $conn->query("SELECT student_id FROM student_units WHERE unit_id = $unit_id");
            while ($s = $students->fetch_assoc()) {
                $student_id = $s['student_id'];
                $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, message, link, created_at) VALUES (?, ?, ?, NOW())");
                $stmt2->bind_param("iss", $student_id, $msg, $link);
                $stmt2->execute();
            }

            $uploadCount++;
        }
    }

    if ($uploadCount > 0) {
        $_SESSION['upload_success'] = "$uploadCount file(s) uploaded successfully and students notified.";
    } else {
        $_SESSION['upload_error'] = "File upload failed. Please try again.";
    }

    header("Location: lecturer/dashboard.php");
    exit;
}

// === CREATE ASSIGNMENT ===
if ($action === 'create_assignment') {
    $unit_id = $_POST['unit_id'];
    $title = $_POST['title'];
    $instructions = $_POST['instructions'];
    $due_date = $_POST['due_date'];
    $lecturer_id = $_SESSION['user_id'];

    $filename = null;
    if (!empty($_FILES['assignment_file']['name'])) {
        $upload_dir = "assets/uploads/assignments/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $filename = time() . "_" . basename($_FILES['assignment_file']['name']);
        $target_path = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['assignment_file']['tmp_name'], $target_path)) {
            $_SESSION['assignment_error'] = "File upload failed.";
            header("Location: lecturer/dashboard.php");
            exit;
        }
    }

    // Insert into DB
    if ($filename) {
        $stmt = $conn->prepare("INSERT INTO assignments (lecturer_id, unit_id, title, description, deadline, file_path, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iissss", $lecturer_id, $unit_id, $title, $instructions, $due_date, $filename);
    } else {
        $stmt = $conn->prepare("INSERT INTO assignments (lecturer_id, unit_id, title, description, deadline, created_at)
                                VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisss", $lecturer_id, $unit_id, $title, $instructions, $due_date);
    }

    if ($stmt->execute()) {
        // === Notifications ===
        $msg = "New assignment: '$title' has been posted for your unit.";
        $link = "student/assignments.php?unit_id=" . $unit_id;

        // Fetch all students in this unit (adjust table if needed)
        $students = $conn->query("SELECT student_id FROM student_units WHERE unit_id = $unit_id");
        while ($s = $students->fetch_assoc()) {
            $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $s['student_id'], $msg, $link);
            $stmt2->execute();
        }

        $_SESSION['assignment_success'] = "Assignment created and students notified.";
    } else {
        $_SESSION['assignment_error'] = "Failed to create assignment.";
    }

    header("Location: lecturer/dashboard.php");
    exit;
}


// === SAVE MARKS ===
if ($action === 'save_marks') {
    $marks = $_POST['marks'] ?? [];
    $is_graded = $_POST['is_graded'] ?? [];
    $comments = $_POST['comment'] ?? [];

    foreach ($marks as $submission_id => $mark) {
        $graded = isset($is_graded[$submission_id]) ? 1 : 0;
        $comment = $comments[$submission_id] ?? NULL;

        $stmt = $conn->prepare("UPDATE submissions SET marks = ?, is_graded = ?, comment = ? WHERE id = ?");
        $stmt->bind_param("iisi", $mark, $graded, $comment, $submission_id);
        $stmt->execute();
    }

    $_SESSION['marks_success'] = "Marks, grading status, and comments saved successfully.";
    header("Location: lecturer/assignment_submissions.php");
    exit;
}

// === UPDATE PASSWORD ===
if ($action === 'update_password') {
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $csrf_token = $_POST['csrf_token'];

    // CSRF token validation
    if (!validate_csrf_token($csrf_token)) {
        $_SESSION['login_error'] = "Invalid CSRF token.";
        header("Location: update_password.php");
        exit;
    }

    // Password match check
    if ($new_password !== $confirm_password) {
        $_SESSION['login_error'] = "Passwords do not match.";
        header("Location: update_password.php");
        exit;
    }

    // Validate email existence in students, lecturers, admins
    $found = false;
    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
    $tables = ['students', 'lecturers', 'admins'];

    foreach ($tables as $table) {
        $check = $conn->prepare("SELECT id FROM $table WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $update = $conn->prepare("UPDATE $table SET password = ? WHERE email = ?");
            $update->bind_param("ss", $hashed, $email);
            $update->execute();
            $found = true;
            break;
        }
    }

    if ($found) {
        $_SESSION['login_success'] = "Password updated successfully.";
    } else {
        $_SESSION['login_error'] = "Email not found in system.";
    }

    header("Location: update_password.php");
    exit;
}

// === CSRF TOKEN FUNCTIONS ===
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// === ASSIGN SINGLE UNIT TO LECTURER ===
if ($action === 'add_single_lecturer_unit') {
    $lecturer_id = $_SESSION['user_id'];
    $unit_id = $_POST['unit_id'];

    $stmt = $conn->prepare("SELECT id FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
    $stmt->bind_param("ii", $lecturer_id, $unit_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['add_unit_error'] = "Unit already assigned.";
    } else {
        $stmt = $conn->prepare("INSERT INTO lecturer_units (lecturer_id, unit_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $lecturer_id, $unit_id);
        $stmt->execute();
        $_SESSION['add_unit_success'] = "Unit assigned.";
    }
    header("Location: lecturer/dashboard.php");
    exit;
}

// === GENERATE ASSIGNMENT REPORT PDF ===
if ($action === 'generate_pdf' && isset($_GET['assignment_id'])) {
    $assignment_id = intval($_GET['assignment_id']);
    $stmt = $conn->prepare("
        SELECT s.id, s.file_path, s.marks, st.name AS student_name, st.reg_no
        FROM submissions s
        JOIN students st ON s.student_id = st.id
        WHERE s.assignment_id = ?
    ");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $res = $stmt->get_result();

    ob_start();
    echo "<h2>Assignment Report</h2><table border='1'><tr><th>Reg No</th><th>Name</th><th>Marks</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['reg_no']}</td><td>{$row['student_name']}</td><td>{$row['marks']}</td></tr>";
    }
    echo "</table>";
    $html = ob_get_clean();

    $pdf = new Dompdf();
    $pdf->loadHtml($html);
    $pdf->render();
    $pdf->stream("assignment_report.pdf", ["Attachment" => true]);
    exit;
}

// === SCHEDULE MEETING ===
if ($action === 'schedule_meeting') {
    $title = $_POST['title'];
    $unit_id = $_POST['unit_id'];
    $scheduled_time = $_POST['scheduled_time'];
    $duration = intval($_POST['duration']);
    $lecturer_id = $_SESSION['user_id'];

    $meeting_id = time(); // for mock ID
    $meeting_link = "http://localhost/unilis/meeting_ide.php?meeting_id=" . $meeting_id;

    $stmt = $conn->prepare("INSERT INTO meetings (lecturer_id, unit_id, title, meeting_link, scheduled_time, duration)
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssi", $lecturer_id, $unit_id, $title, $meeting_link, $scheduled_time, $duration);
    $stmt->execute();
    $_SESSION['meeting_success'] = "Meeting scheduled.";
    header("Location: lecturer/meetings.php");
    exit;
}

// === LOG STUDENT ATTENDANCE ===
if ($action === 'log_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
        http_response_code(403);
        echo "Unauthorized";
        exit;
    }

    $meeting_id = intval($_POST['meeting_id']);
    $student_id = $_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');

    $check = $conn->prepare("SELECT id FROM meeting_attendance WHERE meeting_id = ? AND student_id = ?");
    $check->bind_param("ii", $meeting_id, $student_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO meeting_attendance (meeting_id, student_id, join_time) VALUES (?, ?, ?)");
        $insert->bind_param("iis", $meeting_id, $student_id, $now);
        $insert->execute();
    }
    $check->close();
    echo "Attendance logged";
    exit;
}

// === DOWNLOAD ATTENDANCE REGISTER ===
if ($action === 'download_register') {
    $type = $_GET['type'] ?? '';
    $pdf = new Dompdf();
    ob_start();

    if ($type === 'single' && isset($_GET['meeting_id'])) {
        $meeting_id = intval($_GET['meeting_id']);
        $stmt = $conn->prepare("
            SELECT s.name AS student_name, s.reg_no, a.status, a.timestamp
            FROM meeting_attendance a
            JOIN students s ON a.student_id = s.id
            WHERE a.meeting_id = ?
        ");
        $stmt->bind_param("i", $meeting_id);
        $stmt->execute();
        $res = $stmt->get_result();
        echo "<h2>Single Meeting Attendance</h2><table border='1'><tr><th>Reg No</th><th>Name</th><th>Status</th><th>Time</th></tr>";
        while ($row = $res->fetch_assoc()) {
            echo "<tr><td>{$row['reg_no']}</td><td>{$row['student_name']}</td><td>{$row['status']}</td><td>{$row['timestamp']}</td></tr>";
        }
        echo "</table>";
    }

    if ($type === 'full' && isset($_GET['unit_id'])) {
        $unit_id = intval($_GET['unit_id']);
        $stmt = $conn->prepare("
            SELECT m.title, s.name AS student_name, s.reg_no, a.status, a.timestamp
            FROM meeting_attendance a
            JOIN students s ON a.student_id = s.id
            JOIN meetings m ON a.meeting_id = m.id
            WHERE m.unit_id = ?
            ORDER BY m.scheduled_time DESC
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        echo "<h2>Full Unit Attendance</h2><table border='1'><tr><th>Meeting</th><th>Reg No</th><th>Name</th><th>Status</th><th>Time</th></tr>";
        while ($row = $res->fetch_assoc()) {
            echo "<tr><td>{$row['title']}</td><td>{$row['reg_no']}</td><td>{$row['student_name']}</td><td>{$row['status']}</td><td>{$row['timestamp']}</td></tr>";
        }
        echo "</table>";
    }

    $html = ob_get_clean();
    $pdf->loadHtml($html);
    $pdf->render();
    $pdf->stream("attendance_register.pdf", ["Attachment" => true]);
    exit;
}

// === GET COURSE UNITS (FOR AJAX) ===
if (isset($_GET['action']) && $_GET['action'] === 'get_course_units' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = intval($_POST['course_id']);
    $query = $conn->prepare("
        SELECT c.id AS course_id, c.name AS course_name, d.name AS department_name, 
               u.name AS unit_name, u.code AS unit_code, u.year, u.semester
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        LEFT JOIN units u ON c.id = u.course_id
        WHERE c.id = ?
        ORDER BY u.year, u.semester, u.name
    ");
    $query->bind_param('i', $course_id);
    $query->execute();
    $result = $query->get_result();

    $course_data = null;
    while ($row = $result->fetch_assoc()) {
        if (!$course_data) {
            $course_data = [
                'course_id' => $row['course_id'],
                'course_name' => $row['course_name'],
                'department_name' => $row['department_name'],
                'units' => []
            ];
        }
        if ($row['unit_name']) {
            $course_data['units'][] = [
                'unit_name' => $row['unit_name'],
                'unit_code' => $row['unit_code'],
                'year' => $row['year'],
                'semester' => $row['semester']
            ];
        }
    }
    $query->close();
    header('Content-Type: application/json');
    echo json_encode($course_data ?: ['course_id' => $course_id, 'course_name' => '', 'department_name' => '', 'units' => []]);
    exit;
}

// === DOWNLOAD PDF OF COURSE UNITS ===
if (isset($_GET['action']) && $_GET['action'] === 'download_pdf' && isset($_GET['course_id'])) {
    $course_id = intval($_GET['course_id']);
    $stmt = $conn->prepare("
        SELECT c.name AS course_name, d.name AS department_name, 
               u.name AS unit_name, u.code AS unit_code, u.year, u.semester
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        LEFT JOIN units u ON c.id = u.course_id
        WHERE c.id = ?
        ORDER BY u.year, u.semester, u.name
    ");
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $course_name = '';
    $department_name = '';
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $course_name = $row['course_name'];
        $department_name = $row['department_name'];
        if ($row['unit_name']) {
            $units[] = [
                'unit_name' => $row['unit_name'],
                'unit_code' => $row['unit_code'],
                'year' => $row['year'],
                'semester' => $row['semester']
            ];
        }
    }
    $stmt->close();

    // Generate PDF using Dompdf
    $dompdf = new Dompdf();
    $html = '
        <h1>Units for ' . htmlspecialchars($course_name) . '</h1>
        <p><strong>Department:</strong> ' . htmlspecialchars($department_name) . '</p>
        <p><strong>Total Units:</strong> ' . count($units) . '</p>
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="border: 1px solid #ddd; padding: 8px;">Year</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Semester</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Unit Name</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Unit Code</th>
                </tr>
            </thead>
            <tbody>
    ';
    foreach ($units as $unit) {
        $html .= '
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($unit['year']) . '</td>
                <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($unit['semester']) . '</td>
                <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($unit['unit_name']) . '</td>
                <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($unit['unit_code']) . '</td>
            </tr>
        ';
    }
    $html .= '
            </tbody>
        </table>
    ';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("$course_name_units.pdf", ['Attachment' => true]);
    exit;
}

if ($action === 'get_course_units') {
    $course_id = intval($_GET['course_id']);
    $course_res = $conn->query("
        SELECT c.id, c.name AS course_name, d.name AS department_name
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        WHERE c.id = $course_id
    ");

    if ($course_res && $course_res->num_rows > 0) {
        $course = $course_res->fetch_assoc();

        $units_res = $conn->query("
            SELECT id, name, code, year, semester
            FROM units
            WHERE course_id = $course_id
            ORDER BY year, semester
        ");

        $units = [];
        while ($u = $units_res->fetch_assoc()) {
            $units[] = [
                'id' => $u['id'],
                'name' => $u['name'],
                'code' => $u['code'],
                'year' => $u['year'],
                'semester' => $u['semester']
            ];
        }

        echo json_encode([
            'course' => $course,
            'units' => $units
        ]);
    } else {
        echo json_encode(['error' => 'Course not found']);
    }
    exit;
}


// === GENERATE PDF OF UNITS FOR A COURSE ===
if (isset($_POST['action']) && $_POST['action'] === 'generate_unit_pdf') {
    $course_id = intval($_POST['course_id']);

    $course_stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $course = $course_result->fetch_assoc();
    $course_name = $course['name'] ?? 'Unknown Course';

    $unit_stmt = $conn->prepare("SELECT name, code, year, semester FROM units WHERE course_id = ? ORDER BY year, semester, name");
    $unit_stmt->bind_param("i", $course_id);
    $unit_stmt->execute();
    $unit_result = $unit_stmt->get_result();

    $units_by_group = [];
    while ($unit = $unit_result->fetch_assoc()) {
        $group = "Year {$unit['year']} - Semester {$unit['semester']}";
        $units_by_group[$group][] = $unit;
    }

    // Build HTML for PDF
    $html = "<h2 style='text-align:center;'>Units for Course: $course_name</h2>";
    foreach ($units_by_group as $group => $units) {
        $html .= "<h3>$group</h3><ul>";
        foreach ($units as $u) {
            $html .= "<li><strong>{$u['code']}</strong>: {$u['name']}</li>";
        }
        $html .= "</ul>";
    }

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("Units_$course_name.pdf", ["Attachment" => true]);
    exit;
}

// === GET UNITS BY COURSE (LEGACY) ===
if (isset($_GET['action']) && $_GET['action'] === 'get_units_by_course') {
    $course_id = intval($_GET['course_id']);
    $query = $conn->query("SELECT u.name AS unit_name, u.code AS unit_code, 
                                  c.name AS course_name, d.name AS department_name,
                                  (SELECT COUNT(*) FROM units WHERE course_id = $course_id) AS total_units
                           FROM units u 
                           JOIN courses c ON u.course_id = c.id 
                           JOIN departments d ON c.department_id = d.id 
                           WHERE u.course_id = $course_id");

    $units = [];
    while ($row = $query->fetch_assoc()) {
        $units[] = $row;
    }
    echo json_encode($units);
    exit;
}
if ($action === 'generate_unit_submission_pdf') {
    require_once 'vendor/autoload.php'; // Composer autoload

    

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
        die("Access denied.");
    }

    $unit_id = (int)$_POST['unit_id'];
    $lecturer_id = $_SESSION['user_id'];

    // Fetch unit name
    $stmt = $conn->prepare("SELECT name FROM units WHERE id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $stmt->bind_result($unit_name);
    $stmt->fetch();
    $stmt->close();

    // Assignments
    $assignments = [];
    $res = $conn->query("SELECT id, title FROM assignments WHERE unit_id = $unit_id ORDER BY id ASC");
    while ($row = $res->fetch_assoc()) {
        $assignments[] = $row;
    }

    // Students (who submitted anything)
    $students = [];
    $res = $conn->query("
        SELECT DISTINCT s.id, s.name, s.reg_no FROM students s
        JOIN submissions sub ON sub.student_id = s.id
        JOIN assignments a ON sub.assignment_id = a.id
        WHERE a.unit_id = $unit_id
    ");
    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }

    // Generate HTML table
    $html = "<style>
        table { border-collapse: collapse; width: 100%; font-size: 12px; }
        th, td { border: 1px solid #444; padding: 5px; text-align: center; }
        th { background-color: #eee; }
    </style>";
    $html .= "<h2>Submission Report - $unit_name</h2>";
    $html .= "<table>";
    $html .= "<tr><th>Student Name</th><th>Reg No</th>";

    foreach ($assignments as $a) {
        $title = htmlspecialchars($a['title']);
        $html .= "<th>$title<br>✔️</th><th>Marks</th><th>Comment</th><th>View?</th>";
    }

    $html .= "<th>Submitted</th><th>Out Of</th></tr>";

    foreach ($students as $s) {
        $submitted = 0;
        $html .= "<tr><td>" . htmlspecialchars($s['name']) . "</td><td>" . htmlspecialchars($s['reg_no']) . "</td>";

        foreach ($assignments as $a) {
            $stmt = $conn->prepare("SELECT file_path, marks, comment, is_graded FROM submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1");
            $stmt->bind_param("ii", $a['id'], $s['id']);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $data = $res->fetch_assoc();
                $submitted++;
                $mark = $data['is_graded'] ? $data['marks'] : '-';
                $comment = $data['is_graded'] ? htmlspecialchars($data['comment']) : '-';
                $view = $data['is_graded'] ? '✅' : '❌';
                $html .= "<td>✔️</td><td>$mark</td><td>$comment</td><td>$view</td>";
            } else {
                $html .= "<td>❌</td><td>-</td><td>-</td><td>❌</td>";
            }
            $stmt->close();
        }

        $html .= "<td>$submitted</td><td>" . count($assignments) . "</td></tr>";
    }

    $html .= "</table>";

    // Create PDF with DOMPDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Output PDF to browser
    $dompdf->stream("submission_report_unit_$unit_id.pdf", ["Attachment" => false]);
    exit;
 }
 
 // === CREATE INTERACTIVE ASSIGNMENT ===
if ($action === 'create_interactive_assignment') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }

    $lecturer_id = $_SESSION['user_id'];
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $questions = $_POST['questions'] ?? [];

    $conn->begin_transaction();
    try {
        $ins = $conn->prepare("INSERT INTO interactive_assignments (lecturer_id, unit_id, title, description, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->bind_param("iisss", $lecturer_id, $unit_id, $title, $description, $due_date);
        $ins->execute();
        $assignment_id = $ins->insert_id;

        // Handle nested question files if any: $_FILES['questions'][i]['audio']
        $questionFiles = $_FILES['questions'] ?? null;

        foreach ($questions as $i => $q) {
            $qtype = $q['type'] ?? 'text';
            $qtext = $q['text'] ?? '';
            $qpoints = intval($q['points'] ?? 1);
            $qcorrect = $q['correct'] ?? null;

            $media_url = null;
            if ($questionFiles && isset($questionFiles['error'][$i]['audio']) && $questionFiles['error'][$i]['audio'] === UPLOAD_ERR_OK) {
                $tmpName = $questionFiles['tmp_name'][$i]['audio'];
                $orig = basename($questionFiles['name'][$i]['audio']);
                $uploadDir = __DIR__ . "/uploads/questions/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $safe = time() . "_" . preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $orig);
                $target = $uploadDir . $safe;
                if (move_uploaded_file($tmpName, $target)) {
                    $media_url = "uploads/questions/" . $safe;
                }
            }

            $qins = $conn->prepare("INSERT INTO interactive_questions (interactive_assignment_id, question_text, type, points, media_url) VALUES (?, ?, ?, ?, ?)");
            $qins->bind_param("issis", $assignment_id, $qtext, $qtype, $qpoints, $media_url);
            $qins->execute();
            $qid = $qins->insert_id;

            if ($qtype === 'multiple_choice' && !empty($q['options'])) {
                foreach ($q['options'] as $optIndex => $optText) {
                    if (trim($optText) === '') continue;
                    $is_correct = ($qcorrect !== null && intval($qcorrect) == ($optIndex + 1)) ? 1 : 0;
                    $oin = $conn->prepare("INSERT INTO interactive_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                    $oin->bind_param("isi", $qid, $optText, $is_correct);
                    $oin->execute();
                }
            }
        }

        $conn->commit();
        $_SESSION['success'] = 'Interactive assignment created.';
        header('Location: lecturer/create_questions.php');
        exit;
    } catch (Exception $ex) {
        $conn->rollback();
        $_SESSION['error'] = 'Create failed: ' . $ex->getMessage();
        header('Location: lecturer/create_questions.php');
        exit;
    }
}

// === GET MC QUESTIONS FOR AN INTERACTIVE ASSIGNMENT (AJAX) ===
if (isset($_GET['action']) && $_GET['action'] === 'get_mc_questions') {
    $assignment_id = intval($_GET['assignment_id'] ?? 0);
    if ($assignment_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid assignment id']);
        exit;
    }

    // Fetch assignment title
    $ast = $conn->prepare("SELECT title FROM interactive_assignments WHERE id = ?");
    $ast->bind_param("i", $assignment_id);
    $ast->execute();
    $ares = $ast->get_result();
    $assignment = $ares->fetch_assoc();
    if (!$assignment) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Assignment not found']);
        exit;
    }

    // Fetch only multiple_choice questions
    $q = $conn->prepare("SELECT id, question_text, points FROM interactive_questions WHERE interactive_assignment_id = ? AND type = 'multiple_choice' ORDER BY id ASC");
    $q->bind_param("i", $assignment_id);
    $q->execute();
    $qres = $q->get_result();
    $questions = [];
    while ($row = $qres->fetch_assoc()) {
        $row['options'] = [];
        $qo = $conn->prepare("SELECT id, option_text FROM interactive_options WHERE question_id = ? ORDER BY id ASC");
        $qo->bind_param("i", $row['id']);
        $qo->execute();
        $ores = $qo->get_result();
        while ($opt = $ores->fetch_assoc()) {
            $row['options'][] = $opt;
        }
        $questions[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode(['title' => $assignment['title'], 'questions' => $questions]);
    exit;
}

// === GET INTERACTIVE ASSIGNMENT (LECTURER) WITH QUESTIONS ===
if (isset($_GET['action']) && $_GET['action'] === 'get_assignment') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $lecturer_id = intval($_SESSION['user_id']);
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid id']);
        exit;
    }

    $chk = $conn->prepare("SELECT id, title, description, due_date, unit_id FROM interactive_assignments WHERE id=? AND lecturer_id=?");
    $chk->bind_param("ii", $id, $lecturer_id);
    $chk->execute();
    $assignment = $chk->get_result()->fetch_assoc();
    if (!$assignment) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not found or unauthorized']);
        exit;
    }

    $qstmt = $conn->prepare("SELECT id, question_text, type, points, media_url FROM interactive_questions WHERE interactive_assignment_id=? ORDER BY id ASC");
    $qstmt->bind_param("i", $id);
    $qstmt->execute();
    $qres = $qstmt->get_result();
    $questions = [];
    while ($q = $qres->fetch_assoc()) {
        $q['options'] = [];
        if ($q['type'] === 'multiple_choice') {
            $opts = $conn->prepare("SELECT id, option_text, is_correct FROM interactive_options WHERE question_id=? ORDER BY id ASC");
            $opts->bind_param("i", $q['id']);
            $opts->execute();
            $optRes = $opts->get_result();
            while ($o = $optRes->fetch_assoc()) $q['options'][] = $o;
        }
        $questions[] = $q;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'assignment' => $assignment, 'questions' => $questions]);
    exit;
}

// === DELETE INTERACTIVE ASSIGNMENT (LECTURER) ===
if (isset($_GET['action']) && $_GET['action'] === 'delete_interactive_assignment') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
        $_SESSION['error'] = 'Unauthorized';
        header('Location: lecturer/create_questions.php');
        exit;
    }
    $lecturer_id = intval($_SESSION['user_id']);
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM interactive_assignments WHERE id=? AND lecturer_id=?");
        $stmt->bind_param("ii", $id, $lecturer_id);
    $stmt->execute();
        $_SESSION['success'] = 'Assignment deleted successfully!';
    } else {
        $_SESSION['error'] = 'Invalid assignment id.';
    }
    header('Location: lecturer/create_questions.php');
    exit;
}

// === SUBMIT MCQ ANSWERS FOR INTERACTIVE ASSIGNMENT ===
if (isset($_POST['action']) && $_POST['action'] === 'submit_mc_answers') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
        http_response_code(403);
        echo "Unauthorized";
        exit;
    }

    $student_id = intval($_SESSION['user_id']);
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    $answers = $_POST['answers'] ?? [];

    if ($assignment_id <= 0) {
        $_SESSION['submission_error'] = 'Invalid assignment.';
        header('Location: student/dashboard.php');
    exit;
}

    // Prevent duplicate submissions
    $chk = $conn->prepare("SELECT id FROM interactive_submissions WHERE assignment_id = ? AND student_id = ?");
    $chk->bind_param("ii", $assignment_id, $student_id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $_SESSION['submission_error'] = 'You already submitted this interactive assignment.';
        header('Location: student/dashboard.php');
        exit;
    }
    $chk->close();

    // Create submission
    $ins = $conn->prepare("INSERT INTO interactive_submissions (assignment_id, student_id, submitted_at) VALUES (?, ?, NOW())");
    $ins->bind_param("ii", $assignment_id, $student_id);
    $ins->execute();
    $submission_id = $ins->insert_id;
    $ins->close();

    // Optionally persist per-question choices if schema supports it
    // Attempt to insert into interactive_answers if present columns exist
    foreach ($answers as $question_id => $option_id) {
        $qid = intval($question_id);
        $oid = intval($option_id);
        // Try an insert that stores choice if a suitable table exists; ignore on failure
        @$conn->query(
            "INSERT INTO interactive_answers (assignment_id, question_id, student_id, selected_option_id, submitted_at) " .
            "VALUES (" . intval($assignment_id) . ", " . intval($qid) . ", " . intval($student_id) . ", " . intval($oid) . ", NOW())"
        );
    }

    $_SESSION['submission_success'] = 'Interactive assignment submitted successfully.';
    header('Location: student/dashboard.php');
    exit;
}

// === LIST INTERACTIVE ASSIGNMENTS BY UNIT FOR STUDENT (course/year already enforced by unit choice) ===
if (isset($_GET['action']) && $_GET['action'] === 'get_interactive_assignments_by_unit') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $unit_id = intval($_GET['unit_id'] ?? 0);
    if ($unit_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid unit id']);
        exit;
    }
    $stmt = $conn->prepare("SELECT id, title, due_date FROM interactive_assignments WHERE unit_id = ? ORDER BY due_date ASC");
    $stmt->bind_param('i', $unit_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['assignments' => $res]);
    exit;
}

// === GET ASSIGNMENT DETAILS FOR STUDENT ===
if (isset($_GET['action']) && $_GET['action'] === 'get_assignment_details') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $assignment_id = intval($_GET['assignment_id'] ?? 0);
    if ($assignment_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid assignment id']);
        exit;
    }
    
    try {
        // Get assignment details
        $stmt = $conn->prepare("SELECT a.id, a.title, a.description, a.due_date, u.name AS unit_name FROM interactive_assignments a JOIN units u ON a.unit_id = u.id WHERE a.id = ?");
        $stmt->bind_param('i', $assignment_id);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$assignment) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Assignment not found']);
            exit;
        }
        
        // Get questions with options
        $stmt = $conn->prepare("SELECT id, question_text, type, points FROM interactive_questions WHERE interactive_assignment_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $assignment_id);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($questions as &$question) {
            $stmt = $conn->prepare("SELECT id, option_text, is_correct FROM interactive_options WHERE question_id = ? ORDER BY id ASC");
            $stmt->bind_param('i', $question['id']);
            $stmt->execute();
            $question['options'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'assignment' => $assignment,
            'questions' => $questions
        ]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// === SUBMIT INTERACTIVE ANSWERS (AUTO-MARK MCQ) ===
if (isset($_POST['action']) && $_POST['action'] === 'submit_interactive_answers') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }
    $student_id = intval($_SESSION['user_id']);
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    $answers = $_POST['answers'] ?? []; // answers[question_id] = text or option_id
    if ($assignment_id <= 0) {
        $_SESSION['submission_error'] = 'Invalid assignment.';
        header('Location: student/dashboard.php');
        exit;
    }

    // Prevent duplicate submissions
    $chk = $conn->prepare("SELECT id FROM interactive_submissions WHERE assignment_id = ? AND student_id = ?");
    $chk->bind_param('ii', $assignment_id, $student_id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $_SESSION['submission_error'] = 'You already submitted this interactive assignment.';
        header('Location: student/dashboard.php');
        exit;
    }
    $chk->close();

    $conn->begin_transaction();
    try {
        // create submission
        $ins = $conn->prepare("INSERT INTO interactive_submissions (assignment_id, student_id, submitted_at, score) VALUES (?, ?, NOW(), 0)");
        $ins->bind_param('ii', $assignment_id, $student_id);
        $ins->execute();
        $submission_id = $ins->insert_id;

        // fetch MCQ correctness map: question_id -> correct option id
        $correctMap = [];
        $pointsMap = [];
        $qres = $conn->prepare("SELECT id, type, points FROM interactive_questions WHERE interactive_assignment_id = ?");
        $qres->bind_param('i', $assignment_id);
        $qres->execute();
        $qr = $qres->get_result();
        $mcqIds = [];
        while ($row = $qr->fetch_assoc()) {
            $pointsMap[(int)$row['id']] = (int)$row['points'];
            if ($row['type'] === 'multiple_choice') $mcqIds[] = (int)$row['id'];
        }
        if (!empty($mcqIds)) {
            $in = implode(',', array_map('intval', $mcqIds));
            $rs = $conn->query("SELECT question_id, id, is_correct FROM interactive_options WHERE question_id IN ($in)");
            while ($r = $rs->fetch_assoc()) {
                if ((int)$r['is_correct'] === 1) $correctMap[(int)$r['question_id']] = (int)$r['id'];
            }
        }

        $totalScore = 0;
        foreach ($answers as $qidStr => $value) {
            $qid = (int)$qidStr;
            $selected_option_id = null;
            $answer_text = null;
            if (isset($correctMap[$qid])) {
                $selected_option_id = (int)$value;
                if ($selected_option_id === $correctMap[$qid]) {
                    $totalScore += $pointsMap[$qid] ?? 0;
                }
            } else {
                $answer_text = trim((string)$value);
            }
            $ain = $conn->prepare("INSERT INTO interactive_answers (assignment_id, question_id, student_id, answer_text, selected_option_id, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $ain->bind_param('iiisi', $assignment_id, $qid, $student_id, $answer_text, $selected_option_id);
            $ain->execute();
        }

        // update score
        $up = $conn->prepare("UPDATE interactive_submissions SET score = ? WHERE id = ?");
        $up->bind_param('ii', $totalScore, $submission_id);
        $up->execute();

        $conn->commit();
        $_SESSION['submission_success'] = 'Assignment submitted successfully! Your score: ' . $totalScore . ' points';
        header('Location: student/dashboard.php');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['submission_error'] = 'Submit failed: ' . $e->getMessage();
        header('Location: student/dashboard.php');
        exit;
    }
}
// === ADD SPEECH QUESTION TO INTERACTIVE ASSIGNMENT ===
if ($action === 'add_speech_question') {
    $assignment_id = intval($_POST['assignment_id']);
    $question_text = $_POST['question_text'];
    $type = "speech";
    $points = intval($_POST['points'] ?? 1);
    $question_type = "speech";

    $media_url = null;
    if (!empty($_FILES['audio_file']['name'])) {
        $uploadDir = "uploads/questions/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES['audio_file']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $targetFile)) {
            $media_url = $targetFile;
        }
    }

    $stmt = $conn->prepare("INSERT INTO interactive_questions 
        (assignment_id, question_text, type, points, question_type, media_url) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $assignment_id, $question_text, $type, $points, $question_type, $media_url);
    $stmt->execute();

    // Show single success div
    echo "<div style='padding:20px; border:1px solid #ccc; margin:20px; background:#f9f9f9;'>";
    echo "<h2>✅ Speech question added successfully</h2>";
    echo "<p><strong>Q:</strong> " . htmlspecialchars($question_text) . " (" . $type . ")</p>";
    if ($media_url) {
        echo "<audio controls><source src='" . htmlspecialchars($media_url) . "' type='audio/mpeg'></audio><br>";
    }
    echo "<div style='margin-top:20px;'>";
    echo "<a href='lecturer/manage_interactive.php?id=$assignment_id' style='padding:10px 15px; background:#28a745; color:#fff; text-decoration:none; margin-right:10px;'>✏️ Edit</a>";
    echo "<a href='lecturer_dashboard.php' style='padding:10px 15px; background:#007bff; color:#fff; text-decoration:none;'>✅ Finish</a>";
    echo "</div></div>";
    exit;
}

// === SUBMIT SPEECH ANSWER ===
if ($action === 'submit_speech_answer') {
    $assignment_id = intval($_POST['assignment_id']);
    $question_id = intval($_POST['question_id']);
    $student_id = $_SESSION['user_id'];
    $answer_text = $_POST['answer_text'] ?? null;
    $answer_audio = null;

    if (!empty($_FILES['audio_answer']['name'])) {
        $uploadDir = "uploads/answers/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES['audio_answer']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['audio_answer']['tmp_name'], $targetFile)) {
            $answer_audio = $targetFile;
        }
    }

    $stmt = $conn->prepare("INSERT INTO interactive_answers 
        (assignment_id, question_id, student_id, answer_text, answer_audio, submitted_at) 
        VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiiss", $assignment_id, $question_id, $student_id, $answer_text, $answer_audio);
    $stmt->execute();

    // Redirect student after submission
    header("Location: student/dashboard.php?success=1");
    exit;
}

// === SAVE INTERACTIVE QUESTIONS (TEXT / MCQ / SPEECH) ===
if ($action === 'save_questions') {
    $assignment_id = intval($_POST['assignment_id']);
    $questions = $_POST['questions'] ?? [];

    foreach ($questions as $q) {
        $type   = $q['type'] ?? 'text';
        $text   = $q['text'] ?? '';
        $points = intval($q['points'] ?? 1);
        $correct = $q['correct'] ?? null;
        $question_type = $type;

        $media_url = null;
        if (!empty($_FILES['audio']['name'])) {
            $uploadDir = "uploads/questions/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = time() . "_" . basename($_FILES['audio']['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['audio']['tmp_name'], $targetFile)) {
                $media_url = $targetFile;
            }
        }

        $stmt = $conn->prepare("INSERT INTO interactive_questions 
            (assignment_id, question_text, type, points, question_type, media_url) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $assignment_id, $text, $type, $points, $question_type, $media_url);
        $stmt->execute();
        $question_id = $stmt->insert_id;

        if ($type === 'multiple_choice' && !empty($q['options'])) {
            foreach ($q['options'] as $index => $optionText) {
                if (trim($optionText) === '') continue;
                $is_correct = ($correct == ($index + 1)) ? 1 : 0;
                $optStmt = $conn->prepare("INSERT INTO interactive_options 
                    (question_id, option_text, is_correct) 
                    VALUES (?, ?, ?)");
                $optStmt->bind_param("isi", $question_id, $optionText, $is_correct);
                $optStmt->execute();
            }
        }
    }

    // ✅ Display saved questions in one success div
    $res = $conn->query("SELECT * FROM interactive_questions WHERE interactive_assignment_id = $assignment_id");

    echo "<div style='padding:20px; border:1px solid #ccc; margin:20px; background:#f9f9f9;'>";
    echo "<h2>✅ Questions saved successfully</h2>";

    while ($row = $res->fetch_assoc()) {
        echo "<div style='border:1px solid #ddd; padding:10px; margin:10px 0;'>";
        echo "<strong>Q:</strong> " . htmlspecialchars($row['question_text']) . " <em>(" . $row['type'] . ")</em><br>";
        echo "Points: " . intval($row['points']) . "<br>";

        if ($row['media_url']) {
            echo "<audio controls><source src='" . htmlspecialchars($row['media_url']) . "' type='audio/mpeg'></audio><br>";
        }

        if ($row['type'] === 'multiple_choice') {
            $qid = $row['id'];
            $optRes = $conn->query("SELECT * FROM interactive_options WHERE question_id = $qid");
            echo "<ul>";
            while ($opt = $optRes->fetch_assoc()) {
                $correctMark = $opt['is_correct'] ? "✅" : "";
                echo "<li>" . htmlspecialchars($opt['option_text']) . " $correctMark</li>";
            }
            echo "</ul>";
        }

        echo "</div>";
    }

    echo "<div style='margin-top:20px;'>";
    echo "<a href='lecturer/manage_interactive.php?id=$assignment_id' style='padding:10px 15px; background:#28a745; color:#fff; text-decoration:none; margin-right:10px;'>✏️ Edit</a>";
    echo "<a href='lecturer_dashboard.php' style='padding:10px 15px; background:#007bff; color:#fff; text-decoration:none;'>✅ Finish</a>";
    echo "</div></div>";
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'update_interactive_assignment') {
    $id = intval($_POST['id']);
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $unit_id = intval($_POST['unit_id']);
    $questions = $_POST['questions'] ?? [];

    // === 1. Update assignment itself ===
    $stmt = $conn->prepare("UPDATE interactive_assignments 
                            SET title=?, description=?, due_date=?, unit_id=? 
                            WHERE id=? AND lecturer_id=?");
    $stmt->bind_param("sssiii", $title, $description, $due_date, $unit_id, $id, $_SESSION['user_id']);
    $stmt->execute();

    // === 2. Handle questions ===
    foreach ($questions as $q) {
        $qid    = intval($q['id'] ?? 0);
        $text   = $q['text'] ?? '';
        $type   = $q['type'] ?? 'text';
        $points = intval($q['points'] ?? 1);
        $correct = $q['correct'] ?? null;

        if ($qid > 0) {
            // Update existing question
            $stmtQ = $conn->prepare("UPDATE interactive_questions 
                                     SET question_text=?, type=?, points=? 
                                     WHERE id=? AND interactive_assignment_id=?");
            $stmtQ->bind_param("ssiii", $text, $type, $points, $qid, $id);
            $stmtQ->execute();

            // For multiple choice → update options
            if ($type === 'multiple_choice' && isset($q['options'])) {
                foreach ($q['options'] as $index => $opt) {
                    $opt_id = intval($opt['id'] ?? 0);
                    $opt_text = $opt['text'] ?? '';
                    $is_correct = ($correct == ($index + 1)) ? 1 : 0;

                    if ($opt_id > 0) {
                        // Update existing option
                        $optStmt = $conn->prepare("UPDATE interactive_options 
                                                   SET option_text=?, is_correct=? 
                                                   WHERE id=? AND question_id=?");
                        $optStmt->bind_param("siii", $opt_text, $is_correct, $opt_id, $qid);
                        $optStmt->execute();
                    } else {
                        // Insert new option
                        $optStmt = $conn->prepare("INSERT INTO interactive_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                        $optStmt->bind_param("isi", $qid, $opt_text, $is_correct);
                        $optStmt->execute();
                    }
                }
            }
        } else {
            // Insert new question
            $stmtQ = $conn->prepare("INSERT INTO interactive_questions (interactive_assignment_id, question_text, type, points) VALUES (?, ?, ?, ?)");
            $stmtQ->bind_param("issi", $id, $text, $type, $points);
            $stmtQ->execute();
            $new_qid = $stmtQ->insert_id;

            if ($type === 'multiple_choice' && isset($q['options'])) {
                foreach ($q['options'] as $index => $opt) {
                    $opt_text = $opt['text'] ?? '';
                    $is_correct = ($correct == ($index + 1)) ? 1 : 0;
                    $optStmt = $conn->prepare("INSERT INTO interactive_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                    $optStmt->bind_param("isi", $new_qid, $opt_text, $is_correct);
                    $optStmt->execute();
                }
            }
        }
    }

    $_SESSION['success'] = "Interactive Assignment (and questions) updated successfully!";
    header("Location: lecturer/manage_interactive.php");
    exit;
}

?>
