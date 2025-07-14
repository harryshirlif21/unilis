<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: get action if set
$action = $_POST['action'] ?? '';

// STUDENT SIGNUP HANDLER
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

        if ($stmt->execute()) {
            $_SESSION['signup_success'] = "Student registered successfully.";
        } else {
            $_SESSION['signup_error'] = "Error: " . $stmt->error;
        }
    }
    header("Location: signup.php");
    exit;
}

// UNIVERSAL LOGIN HANDLER
if ($action === 'universal_login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Admin
    $stmt = $conn->prepare("SELECT id, password, name FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashed, $name);
        $stmt->fetch();
        if (password_verify($password, $hashed)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'admin';
            header("Location: admin/dashboard.php");
            exit;
        }
    }

    // Lecturer
    $stmt = $conn->prepare("SELECT id, password, name FROM lecturers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashed, $name);
        $stmt->fetch();
        if (password_verify($password, $hashed)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'lecturer';
            header("Location: lecturer/dashboard.php");
            exit;
        }
    }

    // Student
    $stmt = $conn->prepare("SELECT id, password, name FROM students WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashed, $name);
        $stmt->fetch();
        if (password_verify($password, $hashed)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'student';
            header("Location: student/dashboard.php");
            exit;
        }
    }

    $_SESSION['login_error'] = "Invalid credentials or account not found.";
    header("Location: login.php");
    exit;
}

// ADD UNIVERSITY
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
        $stmt->execute() ? $_SESSION['university_success'] = "University added." : $_SESSION['university_error'] = "Error adding.";
    }
    header("Location: admin/dashboard.php");
    exit;
}

// ADD DEPARTMENT
if ($action === 'add_department') {
    $name = trim($_POST['department_name']);
    $university_id = $_POST['university_id'];

    $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ? AND university_id = ?");
    $stmt->bind_param("si", $name, $university_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['department_error'] = "Department already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO departments (name, university_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $university_id);
        $stmt->execute() ? $_SESSION['department_success'] = "Department added." : $_SESSION['department_error'] = "Error adding.";
    }
    header("Location: admin/dashboard.php");
    exit;
}

// ADD COURSE
if ($action === 'add_course') {
    $name = trim($_POST['course_name']);
    $dept_id = $_POST['department_id'];

    $stmt = $conn->prepare("SELECT id FROM courses WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['course_error'] = "Course already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (name, department_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $dept_id);
        $stmt->execute() ? $_SESSION['course_success'] = "Course added." : $_SESSION['course_error'] = "Error adding.";
    }
    header("Location: admin/dashboard.php");
    exit;
}

// ADD UNIT
if ($action === 'add_unit') {
    $name = trim($_POST['unit_name']);
    $code = strtoupper(trim($_POST['unit_code']));
    $course_id = $_POST['course_id'];

    $stmt = $conn->prepare("SELECT id FROM units WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['unit_error'] = "Unit code already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO units (name, code, course_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $code, $course_id);
        $stmt->execute() ? $_SESSION['unit_success'] = "Unit added." : $_SESSION['unit_error'] = "Error adding.";
    }
    header("Location: admin/dashboard.php");
    exit;
}

// ADD LECTURER
if ($action === 'add_lecturer') {
    $name = trim($_POST['lecturer_name']);
    $email = trim($_POST['lecturer_email']);
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
        $stmt->execute() ? $_SESSION['lecturer_success'] = "Lecturer added." : $_SESSION['lecturer_error'] = "Error adding lecturer.";
    }
    header("Location: admin/dashboard.php");
    exit;
}

// Upload Notes
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

// Create Assignment (with optional file)
if ($action === 'create_assignment') {
    $unit_id = $_POST['unit_id'];
    $instructions = $_POST['instructions'];
    $due_date = $_POST['due_date'];
    $lecturer_id = $_SESSION['user_id'];

    $file_uploaded = false;
    $filename = null;

    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "assets/uploads/assignments/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $filename = time() . "_" . basename($_FILES['assignment_file']['name']);
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $target_path)) {
            $file_uploaded = true;
        }
    }

    if ($file_uploaded) {
        $stmt = $conn->prepare("INSERT INTO assignments (lecturer_id, unit_id, description, deadline, file_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisss", $lecturer_id, $unit_id, $instructions, $due_date, $filename);
    } else {
        $stmt = $conn->prepare("INSERT INTO assignments (lecturer_id, unit_id, description, deadline, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $lecturer_id, $unit_id, $instructions, $due_date);
    }

    if ($stmt->execute()) {
        $_SESSION['assignment_success'] = "Assignment created.";
    } else {
        $_SESSION['assignment_error'] = "Error creating assignment.";
    }
    header("Location: lecturer/dashboard.php");
    exit;
}

// Assign units to lecturer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_lecturer_units') {
    $lecturer_id = $_SESSION['user_id'];
    $unit_ids = $_POST['unit_ids'] ?? [];

    foreach ($unit_ids as $unit_id) {
        $stmt = $conn->prepare("INSERT IGNORE INTO lecturer_units (lecturer_id, unit_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $lecturer_id, $unit_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: lecturer/dashboard.php");
    exit;
}


// Lecturer Adds One Unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_single_lecturer_unit') {
    $lecturer_id = $_SESSION['user_id'];
    $unit_id = $_POST['unit_id'];

    // Check if already added
    $stmt = $conn->prepare("SELECT id FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
    $stmt->bind_param("ii", $lecturer_id, $unit_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['add_unit_error'] = "You already teach this unit.";
    } else {
        $stmt = $conn->prepare("INSERT INTO lecturer_units (lecturer_id, unit_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $lecturer_id, $unit_id);
        if ($stmt->execute()) {
            $_SESSION['add_unit_success'] = "Unit added to your list.";
        } else {
            $_SESSION['add_unit_error'] = "Error adding unit.";
        }
    }

    header("Location: lecturer/dashboard.php");
    exit;
}

?>
