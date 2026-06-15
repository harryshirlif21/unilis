<?php
require_once 'config/db.php';
//require_once 'vendor/autoload.php';
require_once 'vendor/autoload.php';
require_once 'includes/mailer.php';
require_once 'includes/notifications.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Add OpenAI API key (should be stored securely in environment variables)
$openai_key = getenv('OPENAI_API_KEY');

function convertSpeechToText($audioFile, $openai_key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openai_key
    ]);

    $post_data = [
        'file' => new CURLFile($audioFile),
        'model' => 'whisper-1',
        'language' => 'en'
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    $result = json_decode($response, true);
    return $result['text'] ?? null;
}
use Dompdf\Dompdf;
use Dompdf\Options;

// Helper: Safe action fetch
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// === SPEECH TO TEXT CONVERSION ===
if ($action === 'speech_to_text') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['audio'])) {
        echo json_encode(['success' => false, 'error' => 'No audio file received']);
        exit;
    }

    $audioFile = $_FILES['audio']['tmp_name'];
    $text = convertSpeechToText($audioFile, $openai_key);

    if ($text === null) {
        echo json_encode(['success' => false, 'error' => 'Failed to convert speech to text']);
        exit;
    }

    echo json_encode(['success' => true, 'text' => $text]);
    exit;
}

// === SAVE QUESTIONS ===
if ($action === 'save_questions' && isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'lecturer') {
    $assignment_id = $_POST['assignment_id'];
    $questions = $_POST['questions'];
    
    try {
        $conn->begin_transaction();

        // First, delete existing questions for this assignment
        $stmt = $conn->prepare("DELETE FROM questions WHERE assignment_id = ?");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();

        // Prepare statements for questions and options
        $questionStmt = $conn->prepare("
            INSERT INTO questions (assignment_id, question_text, question_type, points) 
            VALUES (?, ?, ?, ?)
        ");
        
        $optionStmt = $conn->prepare("
            INSERT INTO multiple_choice_options (question_id, option_text, is_correct) 
            VALUES (?, ?, ?)
        ");

        foreach ($questions as $question) {
            $questionStmt->bind_param("issi", 
                $assignment_id,
                $question['text'],
                $question['type'],
                $question['points']
            );
            $questionStmt->execute();
            $question_id = $conn->insert_id;

            // If it's a multiple choice question, save the options
            if ($question['type'] === 'multiple_choice' && isset($question['options'])) {
                foreach ($question['options'] as $index => $option_text) {
                    $is_correct = ($question['correct'] == ($index + 1)) ? 1 : 0;
                    $optionStmt->bind_param("isi",
                        $question_id,
                        $option_text,
                        $is_correct
                    );
                    $optionStmt->execute();
                }
            }
        }

        $conn->commit();
        header("Location: lecturer/view_assignment.php?id=" . $assignment_id);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Failed to save questions: " . $e->getMessage();
        header("Location: lecturer/create_questions.php?id=" . $assignment_id);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'signup_student') {

    // === 1. Collect and sanitize input ===
    $reg_no          = trim($_POST['reg_no'] ?? '');
    $name            = trim($_POST['name'] ?? '');
    $email           = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $university_id   = trim($_POST['university'] ?? 'JKUAT'); // Always JKUAT
    $department_id   = (int)($_POST['department'] ?? 0);
    $course_id       = (int)($_POST['course'] ?? 0);
    $year_of_study   = (int)($_POST['year_of_study'] ?? 0);
    $year_joined     = (int)($_POST['year_joined'] ?? 0);
    $password        = $_POST['password'] ?? '';
    $confirm_password= $_POST['confirm_password'] ?? '';

    // === 2. Validate inputs ===
    $errors = [];

    if (empty($reg_no)) $errors[] = "Registration number is required.";
    if (empty($name)) $errors[] = "Full name is required.";
    if (!$email) $errors[] = "Please enter a valid email address.";
    if ($department_id <= 0) $errors[] = "Please select a valid department.";
    if ($course_id <= 0) $errors[] = "Please select a valid course.";
    if ($year_of_study < 1 || $year_of_study > 6) $errors[] = "Year of study must be between 1 and 6.";
    if ($year_joined < 2000 || $year_joined > date('Y')) $errors[] = "Year joined must be between 2000 and " . date('Y') . ".";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (!empty($errors)) {
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['old_input'] = $_POST;
        header("Location: signup.php");
        exit;
    }

    // === 3. Check for duplicate reg_no or email ===
    $stmt = $conn->prepare("SELECT id FROM students WHERE reg_no = ? OR email = ?");
    $stmt->bind_param("ss", $reg_no, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        $_SESSION['signup_errors'] = ["Email or Registration number already registered. <a href='../login.php'>Login here</a>"];
        $_SESSION['old_input'] = $_POST;
        header("Location: signup.php");
        exit;
    }
    $stmt->close();

    // === 4. Insert student record ===
    $token       = bin2hex(random_bytes(32));
    $expires_at  = date('Y-m-d H:i:s', time() + (TOKEN_EXPIRY_MINUTES * 60));
    $hashed_pass = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO students (
            reg_no, name, email, university_id, department_id, course_id,
            year_of_study, year_joined, password,
            verification_code, token_expires_at, is_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->bind_param(
        "sssiiiiisss",
        $reg_no, $name, $email, $university_id, $department_id, $course_id,
        $year_of_study, $year_joined, $hashed_pass,
        $token, $expires_at
    );

    if (!$stmt->execute()) {
        $_SESSION['signup_errors'] = ["Database error: " . $stmt->error];
        $_SESSION['old_input'] = $_POST;
        $stmt->close();
        header("Location: signup.php");
        exit;
    }
    $stmt->close();

    // === 5. Send verification email ===
error_log("=== ATTEMPTING EMAIL TO: $email | TOKEN: $token | NAME: $name ===");
$email_sent = send_verification_email($email, $token, $name);
error_log("=== EMAIL RESULT: " . ($email_sent ? 'SUCCESS' : 'FAILED') . " ===");

    if ($email_sent) {
        $_SESSION['signup_success'] = "Account created! A verification email has been sent to $email.";
        header("Location: ../verify.php?sent=1&email=" . urlencode($email));
        exit;
    } else {
        $_SESSION['signup_errors'] = ["Account created, but failed to send verification email. Please try again or contact support."];
        header("Location: signup.php");
        exit;
    }
}

// === UNIVERSAL LOGIN WITH RETURN URL SUPPORT ===
if ($action === 'universal_login') {
    $email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $return   = $_GET['return'] ?? '';  // THIS IS THE KEY LINE

    if (!$email) {
        $_SESSION['login_error'] = "Please enter a valid email.";
        header("Location: login.php" . ($return ? "?return=" . urlencode($return) : ""));
        exit;
    }

    $roles = [
        'admin' => [
            'table' => 'admins',
            'fields' => ['id', 'password', 'name'],
            'redirect' => 'admin/dashboard.php',
            'session_map' => ['name' => 'user_name']
        ],
        'lecturer' => [
            'table' => 'lecturers',
            'fields' => ['id', 'password', 'name'],
            'redirect' => 'lecturer/dashboard.php',
            'session_map' => ['name' => 'user_name']
        ],
        'student' => [
            'table' => 'students',
            'fields' => ['id', 'password', 'name', 'course_id', 'year_of_study', 'is_verified'],
            'redirect' => 'student/dashboard.php',
            'session_map' => [
                'name' => 'user_name',
                'course_id' => 'course_id',
                'year_of_study' => 'year_of_study'
            ],
            'requires_verification' => true
        ]
    ];

    $login_success = false;

    foreach ($roles as $role => $config) {
        $table = $config['table'];
        $fields = $config['fields'];
        $field_list = implode(', ', $fields);

        $sql = "SELECT $field_list FROM $table WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) continue;

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            // Student verification check
            if (!empty($config['requires_verification']) && $user['is_verified'] == 0) {
                $_SESSION['pending_verification_email'] = $email;
                header("Location: verify.php?unverified=1");
                exit;
            }

            // SUCCESS: Create session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role']  = $role;

            foreach ($config['session_map'] as $db_col => $session_key) {
                if (isset($user[$db_col])) {
                    $_SESSION[$session_key] = $user[$db_col];
                }
            }

            // FINAL REDIRECT: If return URL exists AND it's the auto-mark page -> go there!
            if ($return && strpos($return, 'student_auto_mark.php') !== false) {
                header("Location: " . $return);
                exit;
            }

            // Otherwise go to normal dashboard
            header("Location: " . $config['redirect']);
            $login_success = true;
            exit;
        }
    }

    if (!$login_success) {
        $_SESSION['login_error'] = "Invalid email or password.";
        header("Location: login.php" . ($return ? "?return=" . urlencode($return) : ""));
        exit;
    }
}

// === ADD UNIVERSITY ===
if ($action === 'add_university') {
    $name = trim($_POST['university_name']);

    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM universities WHERE name = :name");
    $stmt->execute(['name' => $name]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['university_error'] = "University already exists.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO universities (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);
        $_SESSION['university_success'] = "University added.";
    }

    header("Location: admin/dashboard.php");
    exit;
}

// === ADD DEPARTMENT ===
if ($action === 'add_department') {
    $response = array();
    $name = trim($_POST['department_name']);
    $university_id = intval($_POST['university_id']);

    // First check if department exists
    $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ? AND university_id = ?");
    $stmt->bind_param("si", $name, $university_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $response['status'] = 'error';
        $response['message'] = "Department already exists in this university.";
    } else {
        // Insert new department with basic fields
        $stmt = $conn->prepare("INSERT INTO departments (name, university_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $university_id);
        
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = "Department added successfully.";
            $response['department_id'] = $stmt->insert_id;
            $response['department_name'] = $name;
        } else {
            $response['status'] = 'error';
            $response['message'] = "Error adding department: " . $stmt->error;
        }
    }
    $stmt->close();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
// === ADD COURSE ===
if ($action === 'add_course') {

    $name = trim($_POST['course_name']);
    $dept_id = intval($_POST['department_id']);
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
    $course_type = trim($_POST['course_type']);

    // Check if course already exists
    $stmt = $conn->prepare("SELECT id FROM courses WHERE name = ? AND department_id = ?");
    $stmt->bind_param("si", $name, $dept_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['course_error'] = "Course already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (name, department_id, duration, course_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siis", $name, $dept_id, $duration, $course_type);
        $stmt->execute();
        $_SESSION['course_success'] = "Course added successfully.";
    }

    header("Location: admin/dashboard.php");
    exit;
}

// === GET COURSE DETAILS ===
if (isset($_GET['action']) && $_GET['action'] === 'get_course_details') {
    header('Content-Type: application/json');
    $course_id = intval($_GET['course_id'] ?? 0);

    if ($course_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid course ID.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, name, department_id, duration, course_type FROM courses WHERE id = ?");
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();

    if (!$course) {
        echo json_encode(['status' => 'error', 'message' => 'Course not found.']);
        exit;
    }

    echo json_encode(['status' => 'success', 'course' => $course]);
    exit;
}

// === EDIT COURSE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_course') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $name = trim($_POST['course_name'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $duration = intval($_POST['duration'] ?? 0);
    $course_type = trim($_POST['course_type'] ?? '');

    if (!$course_id || !$name || !$department_id || !$duration || !$course_type) {
        $_SESSION['course_error'] = 'All course fields are required.';
        header('Location: admin/dashboard.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM courses WHERE name = ? AND department_id = ? AND id != ?");
    $stmt->bind_param('sii', $name, $department_id, $course_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $_SESSION['course_error'] = 'Another course with the same name already exists in that department.';
        header('Location: admin/dashboard.php');
        exit;
    }
    $stmt->close();

    $update = $conn->prepare("UPDATE courses SET name = ?, department_id = ?, duration = ?, course_type = ? WHERE id = ?");
    $update->bind_param('siisi', $name, $department_id, $duration, $course_type, $course_id);
    if ($update->execute()) {
        $_SESSION['course_success'] = 'Course updated successfully.';
    } else {
        $_SESSION['course_error'] = 'Failed to update course: ' . $update->error;
    }
    $update->close();
    header('Location: admin/dashboard.php');
    exit;
}

// === DELETE COURSE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_course') {
    header('Content-Type: application/json');
    $course_id = intval($_POST['course_id'] ?? 0);

    if ($course_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid course ID.']);
        exit;
    }

    $student_check = $conn->prepare("SELECT id FROM students WHERE course_id = ? LIMIT 1");
    $student_check->bind_param('i', $course_id);
    $student_check->execute();
    $student_check->store_result();
    if ($student_check->num_rows > 0) {
        $student_check->close();
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete this course because students are enrolled in it.']);
        exit;
    }
    $student_check->close();

    $delete_units = $conn->prepare("DELETE FROM units WHERE course_id = ?");
    $delete_units->bind_param('i', $course_id);
    $delete_units->execute();
    $delete_units->close();

    $delete_course = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $delete_course->bind_param('i', $course_id);
    if ($delete_course->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Course and its units deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete course: ' . $delete_course->error]);
    }
    $delete_course->close();
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
    <a href="lecturer/dashboard.php" style="padding: 12px 25px; background-color: #3498db; color: white; border-radius: 5px; text-decoration: none; font-size: 16px;">
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

// === ASSIGN UNIT TO LECTURER ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_unit') {
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $lecturer_id = intval($_SESSION['user_id'] ?? 0);

    if (!$unit_id || !$lecturer_id) {
        echo "error: missing unit_id or lecturer_id";
        exit;
    }

    // Check if unit exists
    $unit_check = $conn->prepare("SELECT id, name FROM units WHERE id = ?");
    $unit_check->bind_param("i", $unit_id);
    $unit_check->execute();
    $unit_result = $unit_check->get_result();
    
    if ($unit_result->num_rows === 0) {
        echo "error: unit not found";
        $unit_check->close();
        exit;
    }
    
    $unit = $unit_result->fetch_assoc();
    $unit_check->close();

    // Check if already assigned
    $check = $conn->prepare("SELECT id FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
    $check->bind_param("ii", $lecturer_id, $unit_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "duplicate";
        $check->close();
        exit;
    }
    $check->close();

    // Assign unit to lecturer
    $assign = $conn->prepare("INSERT INTO lecturer_units (lecturer_id, unit_id) VALUES (?, ?)");
    if (!$assign) {
        echo "error: prepare failed - " . $conn->error;
        exit;
    }

    $assign->bind_param("ii", $lecturer_id, $unit_id);
    if ($assign->execute()) {
        echo '
<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background-color: #f4f4f4; font-family: Arial, sans-serif; color: #333;">
    <h2 style="margin-bottom: 20px;">Unit Assigned Successfully!</h2>
    <p style="margin-bottom: 20px; color: #666;">Unit "' . htmlspecialchars($unit['name']) . '" has been added to your teaching units.</p>
    <a href="lecturer/dashboard.php" style="padding: 12px 25px; background-color: #3498db; color: white; border-radius: 5px; text-decoration: none; font-size: 16px;">
        Go Back to Dashboard
    </a>
</div>';
        exit;
    } else {
        echo "error: assign failed - " . $assign->error;
    }

    $assign->close();
    exit;
}

// === EDIT UNIT ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_unit') {
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $unit_name = trim($_POST['unit_name'] ?? '');
    $unit_code = trim($_POST['unit_code'] ?? '');
    $year = intval($_POST['year'] ?? 0);
    $semester = intval($_POST['semester'] ?? 0);

    if (!$unit_id || !$unit_name || !$unit_code || !$year || !$semester) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    // Check if unit exists
    $check = $conn->prepare("SELECT id FROM units WHERE id = ?");
    $check->bind_param("i", $unit_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Unit not found']);
        $check->close();
        exit;
    }
    $check->close();

    // Check for duplicate code (excluding current unit)
    $duplicate_check = $conn->prepare("SELECT id FROM units WHERE code = ? AND course_id = (SELECT course_id FROM units WHERE id = ?) AND year = ? AND semester = ? AND id != ?");
    $duplicate_check->bind_param("siiii", $unit_code, $unit_id, $year, $semester, $unit_id);
    $duplicate_check->execute();
    $duplicate_check->store_result();

    if ($duplicate_check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Unit with this code already exists in the same year/semester']);
        $duplicate_check->close();
        exit;
    }
    $duplicate_check->close();

    // Update unit
    $update = $conn->prepare("UPDATE units SET name = ?, code = ?, year = ?, semester = ? WHERE id = ?");
    $update->bind_param("ssiii", $unit_name, $unit_code, $year, $semester, $unit_id);
    
    if ($update->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Unit updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update unit: ' . $update->error]);
    }

    $update->close();
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
function send_notes_email_to_course_students($conn, $unit_id, $lecturer_id, $notes_id, $title, $message, $link) {
    // Get course_id for this unit
    $stmt = $conn->prepare("SELECT course_id FROM units WHERE id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $stmt->bind_result($course_id);
    if (!$stmt->fetch()) {
        $stmt->close();
        return false; // unit not found
    }
    $stmt->close();

    // Get students in that course
    $stmt = $conn->prepare("SELECT id, name, email FROM students WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    if (empty($students)) return false;

    // Insert **one notification** for all students
    $stmt = $conn->prepare("INSERT INTO notifications (title, message, link, notes_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssi", $title, $message, $link, $notes_id);
    $stmt->execute();
    $stmt->close();

    // Send email to each student
    foreach ($students as $student) {
        send_notes_email($student['email'], $title, $message, $link, $student['name']);
    }

    return true;
}


function send_notes_email($email, $title, $message, $link, $name = '') {
    $mail = getConfiguredMailer();
    $mail->addAddress($email);

    try {
        $mail->isHTML(true);
        $mail->Subject = $title;

        $mail->Body = "
        <html><body>
        <h2>$title</h2>
        <p>Hello <strong>$name</strong>,</p>
        <p>$message</p>
        <p><a href='$link'>Click here to view the notes</a></p>
        <p>If the link does not work, copy and paste this URL:<br>$link</p>
        <hr>
        <small>UNILIS Automated Notification</small>
        </body></html>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Notes email failed: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
    }
}


// === UPLOAD NOTES ===
if ($action === 'upload_notes') {
    if (!isset($_SESSION['user_id'])) {
        die("Lecturer not logged in or session expired.");
    }

    $lecturer_id = $_SESSION['user_id'];
    $unit_id = $_POST['unit_id'] ?? 0;
    
    $files = $_FILES['notes_file'];
    $success_count = 0;
    $error_count = 0;

    $upload_dir = "assets/uploads/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // Restructure files array for multiple uploads
    $file_array = [];
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            $file_array[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
        }
    } else {
        $file_array[] = $files;
    }

    foreach ($file_array as $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $filename = time() . "_" . basename($file['name']);
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {

                // Insert into notes table
                $stmt = $conn->prepare("
                    INSERT INTO notes (lecturer_id, unit_id, file_path, uploaded_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->bind_param("iis", $lecturer_id, $unit_id, $filename);

                if ($stmt->execute()) {
                    $success_count++;
                    $notes_id = $conn->insert_id;

                    // === SEND NOTIFICATIONS & EMAILS TO STUDENTS ===
                    $notes_title = pathinfo($filename, PATHINFO_FILENAME);
                    notify_students_notes_uploaded($conn, $unit_id, $lecturer_id, $notes_title, $notes_id);

                } else {
                    $error_count++;
                }

                $stmt->close();
            } else {
                $error_count++;
            }
        } else {
            $error_count++;
        }
    }

    // Set session messages
    if ($success_count > 0) {
        $_SESSION['upload_success'] = "$success_count file(s) uploaded successfully.";
        if ($error_count > 0) {
            $_SESSION['upload_success'] .= " $error_count file(s) failed to upload.";
        }
    } else {
        $_SESSION['upload_error'] = "Failed to upload any files.";
    }

    header("Location: lecturer/dashboard.php");
    exit;
}
function send_assignment_email_to_course_students($conn, $unit_id, $lecturer_id, $assignment_id, $title, $message, $link, $deadline) {
    // 1. Get course_id from unit
    $stmt = $conn->prepare("SELECT course_id FROM units WHERE id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $stmt->bind_result($course_id);
    if (!$stmt->fetch()) {
        $stmt->close();
        return false; // unit not found
    }
    $stmt->close();

    // 2. Get all students in that course
    $stmt = $conn->prepare("SELECT id, name, email FROM students WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    if (empty($students)) return false;

    // 3. Insert ONE notification for all students
    $stmt = $conn->prepare("
        INSERT INTO notifications (title, message, link, assignment_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("sssi", $title, $message, $link, $assignment_id);
    $stmt->execute();
    $stmt->close();

    // 4. Send email to each student
    foreach ($students as $student) {
        send_assignment_email(
            $student['email'],
            $title,
            $message,
            $link,
            $student['name'],
            $deadline
        );
    }

    return true;
}

function send_assignment_email($email, $title, $message, $link, $name = '') {
    $mail = getConfiguredMailer();
    $mail->addAddress($email);

    try {
        $mail->isHTML(true);
        $mail->Subject = $title;

        $mail->Body = "
        <html><body>
        <h2>$title</h2>
        <p>Hello <strong>$name</strong>,</p>
        <p>$message</p>
        <p><a href='$link'>Click here to view the notes</a></p>
        <p>If the link does not work, copy and paste this URL:<br>$link</p>
        <hr>
        <small>UNILIS Automated Notification</small>
        </body></html>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Notes email failed: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
    }
}


// === CREATE ASSIGNMENT ===
if ($action === 'create_assignment') {
    if (!isset($_SESSION['user_id'])) {
        die("Lecturer not logged in or session expired.");
    }
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

    if ($stmt->execute()) {
        $assignment_id = $conn->insert_id;
        $_SESSION['assignment_success'] = "Assignment created.";

        // === SEND NOTIFICATIONS & EMAILS TO STUDENTS ===
        notify_students_assignment_posted($conn, $unit_id, $assignment_id, $title, $due_date);

    } else {
        $_SESSION['assignment_error'] = "Failed to create assignment.";
    }

    $stmt->close();
    header("Location: lecturer/dashboard.php");
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

// === GET COURSE UNITS ===
if (isset($_GET['action']) && $_GET['action'] === 'get_course_units') {
    $course_id = intval($_GET['course_id']);
    
    // Get course info
    $course_query = $conn->prepare("
        SELECT c.name AS course_name, d.name AS department_name
        FROM courses c 
        JOIN departments d ON c.department_id = d.id 
        WHERE c.id = ?
    ");
    $course_query->bind_param("i", $course_id);
    $course_query->execute();
    $course_info = $course_query->get_result()->fetch_assoc();

    // Get units
    $units_query = $conn->prepare("
        SELECT id, name, code, year, semester
        FROM units 
        WHERE course_id = ?
        ORDER BY year, semester, name
    ");
    $units_query->bind_param("i", $course_id);
    $units_query->execute();
    $result = $units_query->get_result();

    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }

    $response = [
        'course' => $course_info,
        'units' => $units
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// === DELETE UNIT ===
if ($action === 'delete_unit') {
    $response = array();
    $unit_id = intval($_POST['unit_id']);
    
    // First check if the unit exists
    $check = $conn->prepare("SELECT id FROM units WHERE id = ?");
    $check->bind_param("i", $unit_id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows === 0) {
        $response['status'] = 'error';
        $response['message'] = "Unit not found.";
    } else {
        // Delete the unit
        $delete = $conn->prepare("DELETE FROM units WHERE id = ?");
        $delete->bind_param("i", $unit_id);
        
        if ($delete->execute()) {
            $response['status'] = 'success';
            $response['message'] = "Unit deleted successfully.";
        } else {
            $response['status'] = 'error';
            $response['message'] = "Error deleting unit: " . $delete->error;
        }
        $delete->close();
    }
    $check->close();
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// === GET UNIVERSITY DATA ===
if (isset($_GET['action']) && $_GET['action'] === 'get_university_data') {
    $university_id = intval($_GET['university_id']);
    
    // Get courses for this university
    $courses_query = $conn->prepare("
        SELECT c.id, c.name, COUNT(u.id) as unit_count
        FROM courses c
        LEFT JOIN units u ON c.id = u.course_id
        JOIN departments d ON c.department_id = d.id
        WHERE d.university_id = ?
        GROUP BY c.id, c.name
        ORDER BY c.name ASC
    ");
    
    $courses_query->bind_param("i", $university_id);
    $courses_query->execute();
    $result = $courses_query->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'unit_count' => $row['unit_count']
        ];
    }
    
    echo json_encode(['courses' => $courses]);
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
// approve student requests.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_student_email') {

    $email = trim($_POST['student_email'] ?? '');

    if (empty($email)) {
        $_SESSION['verify_error'] = "Email is required.";
        header("Location: lecturer/dashboard.php");
        exit;
    }

    // Check if student exists
    $stmt = $conn->prepare("SELECT id, is_verified FROM students WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['verify_error'] = "No student found with that email.";
        $stmt->close();
        header("Location: lecturer/dashboard.php");
        exit;
    }

    $student = $result->fetch_assoc();

    // If already verified
    if ($student['is_verified'] == 1) {
        $_SESSION['verify_success'] = "This email is already verified.";
        $stmt->close();
        header("Location: lecturer/dashboard.php");
        exit;
    }

    // Update verification
    $stmt->close();
    $stmt = $conn->prepare("UPDATE students SET is_verified = 1 WHERE email = ?");
    $stmt->bind_param("s", $email);

    if ($stmt->execute()) {
        $_SESSION['verify_success'] = "Student email verified successfully.";
    } else {
        $_SESSION['verify_error'] = "Failed to verify. Try again.";
    }

    $stmt->close();
    header("Location: lecturer/dashboard.php");
    exit;
}

// === GET ALL STUDENTS ===
if ($action === 'get_all_students') {
    header('Content-Type: application/json');

    $result = $conn->query("
        SELECT id, reg_no, name, email, year_of_study, year_joined, is_verified, verified_at
        FROM students
        ORDER BY name ASC
    ");

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    echo json_encode(['status' => 'success', 'students' => $students]);
    exit;
}

// === GET COURSE REGISTRATION STATS ===
if (isset($_GET['action']) && $_GET['action'] === 'get_course_registration_stats') {
    header('Content-Type: application/json');
    $course_id = intval($_GET['course_id'] ?? 0);

    if ($course_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Course ID is required.']);
        exit;
    }

    $course_stmt = $conn->prepare(
        "SELECT c.name AS course_name, d.name AS department_name
         FROM courses c
         JOIN departments d ON c.department_id = d.id
         WHERE c.id = ?"
    );
    $course_stmt->bind_param('i', $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $course = $course_result->fetch_assoc();
    $course_stmt->close();

    if (!$course) {
        echo json_encode(['status' => 'error', 'message' => 'Course not found.']);
        exit;
    }

    $stats_stmt = $conn->prepare(
        "SELECT year_of_study AS year, COUNT(*) AS count
         FROM students
         WHERE course_id = ?
         GROUP BY year_of_study
         ORDER BY year_of_study ASC"
    );
    $stats_stmt->bind_param('i', $course_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();

    $year_counts = [];
    while ($row = $stats_result->fetch_assoc()) {
        $year_counts[] = $row;
    }
    $stats_stmt->close();

    echo json_encode(['status' => 'success', 'course' => $course, 'year_counts' => $year_counts]);
    exit;
}

// === GET COURSE YEAR STUDENTS ===
if (isset($_GET['action']) && $_GET['action'] === 'get_course_year_students') {
    header('Content-Type: application/json');
    $course_id = intval($_GET['course_id'] ?? 0);
    $year = intval($_GET['year'] ?? 0);

    if ($course_id <= 0 || $year <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Course and year are required.']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT id, reg_no, name, email, year_of_study, year_joined, is_verified
         FROM students
         WHERE course_id = ? AND year_of_study = ?
         ORDER BY name ASC"
    );
    $stmt->bind_param('ii', $course_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    echo json_encode(['status' => 'success', 'students' => $students]);
    exit;
}

// === DOWNLOAD REGISTRATION PDF ===
if (isset($_GET['action']) && $_GET['action'] === 'download_registration_pdf') {
    $course_id = intval($_GET['course_id'] ?? 0);
    $year = intval($_GET['year'] ?? 0);

    if ($course_id <= 0 || $year <= 0) {
        header('Content-Type: text/plain');
        echo 'Course and year are required.';
        exit;
    }

    $course_stmt = $conn->prepare(
        "SELECT c.name AS course_name, d.name AS department_name
         FROM courses c
         JOIN departments d ON c.department_id = d.id
         WHERE c.id = ?"
    );
    $course_stmt->bind_param('i', $course_id);
    $course_stmt->execute();
    $course = $course_stmt->get_result()->fetch_assoc() ?? null;
    $course_stmt->close();

    if (!$course) {
        header('Content-Type: text/plain');
        echo 'Course not found.';
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT reg_no, name, email, year_of_study, year_joined, is_verified
         FROM students
         WHERE course_id = ? AND year_of_study = ?
         ORDER BY name ASC"
    );
    $stmt->bind_param('ii', $course_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    $safe_course_name = preg_replace('/[^a-zA-Z0-9-_ ]/', '_', $course['course_name']);
    $fileName = "Registration_{$safe_course_name}_Year_{$year}.pdf";

    $html = '<h1 style="font-family: Arial, sans-serif;">Student Registration</h1>';
    $html .= '<p style="font-family: Arial, sans-serif;">Course: <strong>' . htmlspecialchars($course['course_name']) . '</strong></p>';
    $html .= '<p style="font-family: Arial, sans-serif;">Department: <strong>' . htmlspecialchars($course['department_name']) . '</strong></p>';
    $html .= '<p style="font-family: Arial, sans-serif;">Year: <strong>' . htmlspecialchars($year) . '</strong></p>';
    $html .= '<p style="font-family: Arial, sans-serif;">Total Students: <strong>' . count($students) . '</strong></p>';
    $html .= '<table style="width:100%; border-collapse: collapse; font-family: Arial, sans-serif; margin-top: 18px;">'
           . '<thead><tr style="background:#f2f2f2;"><th style="border:1px solid #ddd; padding:8px;">Reg No</th><th style="border:1px solid #ddd; padding:8px;">Name</th><th style="border:1px solid #ddd; padding:8px;">Email</th><th style="border:1px solid #ddd; padding:8px;">Year</th><th style="border:1px solid #ddd; padding:8px;">Joined</th><th style="border:1px solid #ddd; padding:8px;">Status</th></tr></thead><tbody>';

    foreach ($students as $student) {
        $status = intval($student['is_verified']) === 1 ? 'Verified' : 'Unverified';
        $html .= '<tr>' .
                 '<td style="border:1px solid #ddd; padding:8px;">' . htmlspecialchars($student['reg_no']) . '</td>' .
                 '<td style="border:1px solid #ddd; padding:8px;">' . htmlspecialchars($student['name']) . '</td>' .
                 '<td style="border:1px solid #ddd; padding:8px;">' . htmlspecialchars($student['email']) . '</td>' .
                 '<td style="border:1px solid #ddd; padding:8px;">' . htmlspecialchars($student['year_of_study']) . '</td>' .
                 '<td style="border:1px solid #ddd; padding:8px;">' . htmlspecialchars($student['year_joined']) . '</td>' .
                 '<td style="border:1px solid #ddd; padding:8px;">' . htmlspecialchars($status) . '</td>' .
                 '</tr>';
    }

    $html .= '</tbody></table>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($fileName, ['Attachment' => true]);
    exit;
}

// === DELETE SINGLE STUDENT ===
if ($action === 'delete_student') {
    header('Content-Type: application/json');

    $student_id = (int)($_POST['student_id'] ?? 0);
    if ($student_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Student deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete student: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// === BULK DELETE STUDENTS ===
if ($action === 'bulk_delete_students') {
    header('Content-Type: application/json');

    $raw_ids = $_POST['student_ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $raw_ids)));

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid student IDs provided.']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $stmt = $conn->prepare("DELETE FROM students WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);

    if ($stmt->execute()) {
        $count = $stmt->affected_rows;
        echo json_encode(['status' => 'success', 'message' => "$count student(s) deleted successfully."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Bulk delete failed: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// === GET TEST DATA BY CATEGORY ===
if ($action === 'get_test_data') {
    header('Content-Type: application/json');
    
    $category = $_GET['category'] ?? 'notes';
    $records = [];
    
    switch ($category) {
        case 'notes':
            $result = $conn->query("
                SELECT id, CONCAT('File: ', file_path) as title, uploaded_at as date
                FROM notes
                ORDER BY uploaded_at DESC LIMIT 500
            ");
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            break;
            
        case 'assignments':
            $result = $conn->query("
                SELECT id, title, created_at as date
                FROM assignments
                ORDER BY created_at DESC LIMIT 500
            ");
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            break;
            
        case 'submissions':
            $result = $conn->query("
                SELECT sub.id, CONCAT(s.name, ' - ', a.title) as title, sub.submitted_at as date
                FROM submissions sub
                JOIN students s ON sub.student_id = s.id
                JOIN assignments a ON sub.assignment_id = a.id
                ORDER BY sub.submitted_at DESC LIMIT 500
            ");
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            break;
            
        case 'attendance':
            $result = $conn->query("
                SELECT ma.id, CONCAT(m.title, ' - ', s.name) as title, ma.join_time as date
                FROM meeting_attendance ma
                JOIN meetings m ON ma.meeting_id = m.id
                JOIN students s ON ma.student_id = s.id
                ORDER BY ma.join_time DESC LIMIT 500
            ");
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            break;
            
        case 'meetings':
            $result = $conn->query("
                SELECT id, title, scheduled_time as date
                FROM meetings
                ORDER BY scheduled_time DESC LIMIT 500
            ");
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            break;
            
        case 'enrollments':
            $result = $conn->query("
                SELECT se.id, CONCAT(s.name, ' - ', c.name) as title, se.enrolled_at as date
                FROM student_enrollments se
                JOIN students s ON se.student_id = s.id
                JOIN courses c ON se.course_id = c.id
                ORDER BY se.enrolled_at DESC LIMIT 500
            ");
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            break;
            
        case 'notifications':
            $result = $conn->query("
                SELECT id, title, created_at as date
                FROM notifications
                ORDER BY created_at DESC LIMIT 500
            ");
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            break;
    }

    echo json_encode(['status' => 'success', 'category' => $category, 'records' => $records]);
    exit;
}

// === BULK DELETE TEST DATA ===
if ($action === 'bulk_delete_test_data') {
    header('Content-Type: application/json');
    
    $category = $_POST['category'] ?? '';
    $raw_ids = $_POST['ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $raw_ids)));
    
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid IDs provided.']);
        exit;
    }
    
    $deleted_count = 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    
    try {
        $conn->begin_transaction();
        
        switch ($category) {
            case 'notes':
                $stmt = $conn->prepare("DELETE FROM notes WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $deleted_count = $stmt->affected_rows;
                $stmt->close();
                break;
                
            case 'assignments':
                // Delete associated submissions first
                $stmt = $conn->prepare("DELETE FROM submissions WHERE assignment_id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $stmt->close();
                
                // Then delete assignments
                $stmt = $conn->prepare("DELETE FROM assignments WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $deleted_count = $stmt->affected_rows;
                $stmt->close();
                break;
                
            case 'submissions':
                $stmt = $conn->prepare("DELETE FROM submissions WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $deleted_count = $stmt->affected_rows;
                $stmt->close();
                break;
                
            case 'attendance':
                $stmt = $conn->prepare("DELETE FROM meeting_attendance WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $deleted_count = $stmt->affected_rows;
                $stmt->close();
                break;
                
            case 'meetings':
                // Delete attendance records first
                $stmt = $conn->prepare("DELETE FROM meeting_attendance WHERE meeting_id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $stmt->close();
                
                // Then delete meetings
                $stmt = $conn->prepare("DELETE FROM meetings WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $deleted_count = $stmt->affected_rows;
                $stmt->close();
                break;
                
            case 'enrollments':
                $stmt = $conn->prepare("DELETE FROM student_enrollments WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $deleted_count = $stmt->affected_rows;
                $stmt->close();
                break;
                
            case 'notifications':
                $stmt = $conn->prepare("DELETE FROM notifications WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $deleted_count = $stmt->affected_rows;
                $stmt->close();
                break;
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "$deleted_count record(s) deleted successfully."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Deletion failed: ' . $e->getMessage()]);
    }
    
    exit;
}

?>