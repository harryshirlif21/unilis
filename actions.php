<?php
require_once 'config/db.php';
require_once 'vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Dompdf\Dompdf;

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
    header("Location: signup.php");
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
    $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ? AND university_id = ?");
    $stmt->bind_param("si", $name, $university_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['department_error'] = "Department already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO departments (name, university_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $university_id);
        $stmt->execute();
        $_SESSION['department_success'] = "Department added.";
    }
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
        echo "success";
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
        header("Location: ../admin/dashboard.php");
        exit;
    }

    if (count($unit_names) !== count($unit_codes)) {
        $_SESSION['unit_error'] = "Mismatch in number of unit names and codes.";
        header("Location: ../admin/dashboard.php");
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

    header("Location: ../admin/dashboard.php");
    exit;
}


// === UPLOAD NOTES ===
if ($action === 'upload_notes') {
    $unit_id = $_POST['unit_id'];
    $lecturer_id = $_SESSION['user_id'];
    $file = $_FILES['notes_file'];

    $upload_dir = "assets/uploads/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $filename = time() . "_" . basename($file['name']);
    $target_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $stmt = $conn->prepare("INSERT INTO notes (lecturer_id, unit_id, file_path, uploaded_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $lecturer_id, $unit_id, $filename);
        $stmt->execute();
        $_SESSION['upload_success'] = "Notes uploaded.";
    } else {
        $_SESSION['upload_error'] = "File upload failed.";
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

    if ($filename) {
        $stmt = $conn->prepare("INSERT INTO assignments (lecturer_id, unit_id, title, description, deadline, file_path, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iissss", $lecturer_id, $unit_id, $title, $instructions, $due_date, $filename);
    } else {
        $stmt = $conn->prepare("INSERT INTO assignments (lecturer_id, unit_id, title, description, deadline, created_at)
                                VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisss", $lecturer_id, $unit_id, $title, $instructions, $due_date);
    }

    $stmt->execute() ?
        $_SESSION['assignment_success'] = "Assignment created." :
        $_SESSION['assignment_error'] = "Failed to create assignment.";
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

// === VIEW UNITS BY COURSE ===
if (isset($_POST['action']) && $_POST['action'] === 'view_units_by_course') {
    $course_id = intval($_POST['course_id']);

    $course_name_stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $course_name_stmt->bind_param("i", $course_id);
    $course_name_stmt->execute();
    $course_result = $course_name_stmt->get_result();
    $course = $course_result->fetch_assoc();
    $course_name = $course['name'] ?? 'Unknown Course';

    // Fetch units
    $units_stmt = $conn->prepare("
        SELECT name, code, year, semester 
        FROM units 
        WHERE course_id = ? 
        ORDER BY year, semester, name
    ");
    $units_stmt->bind_param("i", $course_id);
    $units_stmt->execute();
    $units_result = $units_stmt->get_result();

    $units_by_group = [];
    while ($unit = $units_result->fetch_assoc()) {
        $key = "Year {$unit['year']} - Semester {$unit['semester']}";
        $units_by_group[$key][] = $unit;
    }

    echo "<h3>Units for <strong>" . htmlspecialchars($course_name) . "</strong></h3>";

    if (!empty($units_by_group)) {
        echo "<div id='unitDisplay'>";
        foreach ($units_by_group as $group => $units) {
            echo "<h4>$group</h4><ul>";
            foreach ($units as $u) {
                echo "<li><strong>" . htmlspecialchars($u['code']) . "</strong>: " . htmlspecialchars($u['name']) . "</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        echo "<form method='POST' action='actions.php' target='_blank'>
                <input type='hidden' name='action' value='generate_unit_pdf'>
                <input type='hidden' name='course_id' value='$course_id'>
                <button type='submit'>Generate PDF</button>
              </form>";
    } else {
        echo "<p>No units found for this course.</p>";
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
?>