<?php
/**
 * Department Admin Dashboard
 * UNILIS Academic Foundation Expansion
 * Department Admin can manage lecturers, units, courses, short courses, and lab technicians
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';

// Load Phase 1 configuration and auth
if (file_exists(__DIR__ . '/../config/phase1_config.php')) {
    require_once __DIR__ . '/../config/phase1_config.php';
}
if (file_exists(__DIR__ . '/../includes/auth_extended.php')) {
    require_once __DIR__ . '/../includes/auth_extended.php';
}

// Simple auth check if extended auth not available
if (!function_exists('phase1_guard_role')) {
    function phase1_guard_role($allowed_roles, $redirect_url = '../../login.php') {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            header("Location: $redirect_url");
            exit;
        }
        
        if (is_string($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        
        if (!in_array($_SESSION['user_role'], $allowed_roles)) {
            header("Location: $redirect_url");
            exit;
        }
    }
}

// Only Global Admin or Department Admin can access
phase1_guard_role(['admin', 'department_admin', 'lecturer'], '../../login.php');

$user_name = $_SESSION['user_name'] ?? 'Department Admin';
$user_role = $_SESSION['user_role'] ?? '';
$department_id = $_SESSION['department_id'] ?? 0;

// If department_id is 0, try to get it from department_admins table
if ($department_id == 0 && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $checkTable = $conn->query("SHOW TABLES LIKE 'department_admins'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $deptQuery = $conn->query("SELECT department_id FROM department_admins WHERE admin_id = $user_id AND is_active = 1 LIMIT 1");
        if ($deptQuery && $deptQuery->num_rows > 0) {
            $department_id = $deptQuery->fetch_assoc()['department_id'];
            $_SESSION['department_id'] = $department_id;
        }
    }
}

$message = '';
$message_type = '';
$short_courses_refreshed = false;

// Handle actions
$action = $_POST['action'] ?? '';

// AJAX: Search lecturer by email (returns JSON only)
if ($action === 'search_lecturer') {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $lec = $conn->query("SELECT id, name, email FROM lecturers WHERE email = '" . $conn->real_escape_string($email) . "' LIMIT 1");
        if ($lec && $lec->num_rows > 0) {
            $row = $lec->fetch_assoc();
            echo json_encode(['found' => true, 'id' => (int)$row['id'], 'name' => $row['name'], 'email' => $row['email']]);
        } else {
            echo json_encode(['found' => false]);
        }
    } else {
        echo json_encode(['found' => false, 'error' => 'Email is required']);
    }
    exit;
}

// Add Lecturer
if ($action === 'add_lecturer') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    
    if ($name && $email && $password) {
        // Check if lecturers table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'lecturers'");
        if ($checkTable && $checkTable->num_rows > 0) {
            // Build dynamic insert query based on available columns
            $columns = ['name', 'email', 'password'];
            $placeholders = ['?', '?', '?'];
            $params = [$name, $email, password_hash($password, PASSWORD_DEFAULT)];
            $types = 'sss';
            
            $checkPhone = $conn->query("SHOW COLUMNS FROM lecturers LIKE 'phone'");
            if ($checkPhone && $checkPhone->num_rows > 0 && $phone) {
                $columns[] = 'phone';
                $placeholders[] = '?';
                $params[] = $phone;
                $types .= 's';
            }
            
            $checkStaffId = $conn->query("SHOW COLUMNS FROM lecturers LIKE 'staff_id'");
            if ($checkStaffId && $checkStaffId->num_rows > 0 && $staff_id) {
                $columns[] = 'staff_id';
                $placeholders[] = '?';
                $params[] = $staff_id;
                $types .= 's';
            }
            
            $fieldList = implode(', ', $columns);
            $placeholderList = implode(', ', $placeholders);
            
            $stmt = $conn->prepare("INSERT INTO lecturers ($fieldList) VALUES ($placeholderList)");
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $message = "Lecturer added successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to add lecturer: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Lecturers table does not exist. Please run database migrations.";
            $message_type = 'error';
        }
    }
}

// Add Unit
if ($action === 'add_unit') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $course_id = (int)($_POST['course_id'] ?? 0);
    
    if ($name && $code && $course_id) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'units'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO units (name, code, course_id) VALUES (?, ?, ?)");
            $stmt->bind_param('ssi', $name, $code, $course_id);
            if ($stmt->execute()) {
                $message = "Unit added successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to add unit: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Units table does not exist. Please run database migrations.";
            $message_type = 'error';
        }
    }
}

// Add Course
if ($action === 'add_course') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $department_id_input = (int)($_POST['department_id'] ?? $department_id);
    
    if ($name) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'courses'");
        if ($checkTable && $checkTable->num_rows > 0) {
            // Build dynamic insert query based on available columns
            $columns = ['name', 'department_id'];
            $placeholders = ['?', '?'];
            $params = [$name, $department_id_input];
            $types = 'si';
            
            $checkCode = $conn->query("SHOW COLUMNS FROM courses LIKE 'code'");
            if ($checkCode && $checkCode->num_rows > 0 && $code) {
                $columns[] = 'code';
                $placeholders[] = '?';
                $params[] = $code;
                $types .= 's';
            }
            
            $fieldList = implode(', ', $columns);
            $placeholderList = implode(', ', $placeholders);
            
            $stmt = $conn->prepare("INSERT INTO courses ($fieldList) VALUES ($placeholderList)");
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $message = "Course added successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to add course: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Courses table does not exist. Please run database migrations.";
            $message_type = 'error';
        }
    }
}

// Add Short Course
if ($action === 'add_short_course') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $department_id_input = (int)($_POST['department_id'] ?? 0);
    $banner = $_FILES['banner'] ?? null;
    
    if ($name && $code && $duration && $department_id_input) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'public_courses'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $banner_path = '';
            if ($banner && $banner['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/short_courses/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $banner_path = 'uploads/short_courses/' . time() . '_' . basename($banner['name']);
                move_uploaded_file($banner['tmp_name'], __DIR__ . '/../../' . $banner_path);
            }
            
            // Generate slug from name
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
            
            $stmt = $conn->prepare("INSERT INTO public_courses (slug, title, code, summary, description, duration, department_id, cover_image, created_by_lecturer_id, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param('sssssssii', $slug, $name, $code, $description, $description, $duration, $department_id_input, $banner_path, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $message = "Short course added successfully!";
                $message_type = 'success';
                // Mark that short_courses needs to be refreshed
                $short_courses_refreshed = true;
            } else {
                $message = "Failed to add short course: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Public courses table does not exist. Please run database migrations.";
            $message_type = 'error';
        }
    } else {
        $message = "Course Name, Code, Duration, and Department are required.";
        $message_type = 'error';
    }
}

// Delete Short Course
if ($action === 'delete_short_course') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        // Delete tutor assignments
        $conn->query("DELETE FROM short_course_tutors WHERE short_course_id = $id");
        // Delete course from public_courses
        if ($conn->query("DELETE FROM public_courses WHERE id = $id")) {
            $message = "Short course deleted successfully.";
            $message_type = 'success';
        }
    }
}

// Add Short Course Unit - REMOVED (using public_course_modules instead via course_builder.php)
// This functionality is now handled by the ICLM course builder

// Assign Tutor to Short Course (short course dropdown + lecturer email text)
if ($action === 'assign_tutor_to_short_course') {
    $short_course_id = (int)($_POST['short_course_id'] ?? 0);
    $lecturer_email = trim($_POST['lecturer_email'] ?? '');
    $assigned_by = $_SESSION['user_id'];
    
    if ($short_course_id && $lecturer_email) {
        // Find lecturer by email (search across all lecturers)
        $lec = $conn->query("SELECT id FROM lecturers WHERE email = '" . $conn->real_escape_string($lecturer_email) . "' LIMIT 1");
        $lecturer_id = ($lec && $lec->num_rows > 0) ? (int)$lec->fetch_assoc()['id'] : 0;
        
        if (!$lecturer_id) {
            $message = "Lecturer not found with email: '$lecturer_email'. Please check the email.";
            $message_type = 'error';
        } else {
            $checkTable = $conn->query("SHOW TABLES LIKE 'short_course_tutors'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $check = $conn->query("SELECT id FROM short_course_tutors WHERE short_course_id = $short_course_id AND lecturer_id = $lecturer_id");
                if ($check && $check->num_rows === 0) {
                    $stmt = $conn->prepare("INSERT INTO short_course_tutors (short_course_id, lecturer_id, assigned_by) VALUES (?, ?, ?)");
                    $stmt->bind_param('iii', $short_course_id, $lecturer_id, $assigned_by);
                    if ($stmt->execute()) {
                        $message = "Tutor assigned to short course successfully!";
                        $message_type = 'success';
                    } else {
                        $message = "Failed to assign tutor: " . $stmt->error;
                        $message_type = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = "This tutor is already assigned to this short course.";
                    $message_type = 'warning';
                }
            } else {
                $message = "Short course tutors table does not exist. Please run database migrations.";
                $message_type = 'error';
            }
        }
    } else {
        $message = "Both Short Course Name and Lecturer Email are required.";
        $message_type = 'error';
    }
}

// Remove Tutor from Short Course
if ($action === 'remove_tutor_from_short_course') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'short_course_tutors'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM short_course_tutors WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $message = "Tutor removed from short course successfully.";
                $message_type = 'success';
            }
            $stmt->close();
        }
    }
}

// Add Lab Technician
if ($action === 'add_lab_technician') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    
    if ($name && $email && $password && $staff_id) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'technicians'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO technicians (staff_id, name, email, password, phone, department_id, is_active, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
            $stmt->bind_param('sssssi', $staff_id, $name, $email, $hashed_password, $phone, $department_id);
            if ($stmt->execute()) {
                $message = "Lab technician added successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to add lab technician: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Technicians table does not exist. Please run database migrations.";
            $message_type = 'error';
        }
    }
}

// Get data - with table existence checks
$lecturers = false;
$checkLecturers = $conn->query("SHOW TABLES LIKE 'lecturers'");
if ($checkLecturers && $checkLecturers->num_rows > 0) {
    // Check which columns exist in lecturers table
    $lecturerColumns = ['id', 'name', 'email'];
    $checkPhone = $conn->query("SHOW COLUMNS FROM lecturers LIKE 'phone'");
    if ($checkPhone && $checkPhone->num_rows > 0) {
        $lecturerColumns[] = 'phone';
    }
    $checkStaffId = $conn->query("SHOW COLUMNS FROM lecturers LIKE 'staff_id'");
    if ($checkStaffId && $checkStaffId->num_rows > 0) {
        $lecturerColumns[] = 'staff_id';
    }
    $lecturerFields = implode(', ', $lecturerColumns);
    $lecturers = $conn->query("SELECT $lecturerFields FROM lecturers ORDER BY name");
}

// Check if courses table exists before querying
$courses = false;
$checkCourses = $conn->query("SHOW TABLES LIKE 'courses'");
if ($checkCourses && $checkCourses->num_rows > 0) {
    // Check which columns exist in courses table
    $courseColumns = ['id', 'name'];
    $checkCode = $conn->query("SHOW COLUMNS FROM courses LIKE 'code'");
    if ($checkCode && $checkCode->num_rows > 0) {
        $courseColumns[] = 'code';
    }
    $courseFields = implode(', ', $courseColumns);
    $courses = $conn->query("SELECT $courseFields FROM courses WHERE department_id = $department_id ORDER BY name");
}

// Check if units table exists before querying
$units = false;
$checkUnits = $conn->query("SHOW TABLES LIKE 'units'");
if ($checkUnits && $checkUnits->num_rows > 0) {
    $units = $conn->query("SELECT u.id, u.name, u.code, c.name as course_name FROM units u JOIN courses c ON u.course_id = c.id WHERE c.department_id = $department_id ORDER BY u.name");
}

// Check if public_courses table exists before querying - load ALL short courses
$short_courses = false;
$checkPublicCourses = $conn->query("SHOW TABLES LIKE 'public_courses'");
if ($checkPublicCourses && $checkPublicCourses->num_rows > 0) {
    $short_courses = $conn->query("SELECT id, title as name, summary as description, cover_image as banner FROM public_courses ORDER BY title");
}

// Get short course tutors
$short_course_tutors = false;
$checkSCTutors = $conn->query("SHOW TABLES LIKE 'short_course_tutors'");
if ($checkSCTutors && $checkSCTutors->num_rows > 0) {
    $short_course_tutors = $conn->query("
        SELECT sct.id, sct.short_course_id, sct.lecturer_id, l.name as lecturer_name, pc.title as course_name
        FROM short_course_tutors sct
        JOIN lecturers l ON sct.lecturer_id = l.id
        JOIN public_courses pc ON sct.short_course_id = pc.id
        ORDER BY pc.title, l.name
    ");
}

$technicians = $conn->query("SELECT id, staff_id, name, email, phone FROM technicians WHERE department_id = $department_id ORDER BY name") ?: false;
$all_departments = $conn->query("SELECT id, name FROM departments ORDER BY name") ?: false;

// Get department name
$dept_name = 'Unknown Department';
if ($department_id) {
    $dept = $conn->query("SELECT name FROM departments WHERE id = $department_id");
    if ($dept && $dept->num_rows > 0) {
        $dept_name = $dept->fetch_assoc()['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Admin Dashboard - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #1a1a2e; }
        
        .glass-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 700;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .user-details h2 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .user-details p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
        }
        
        .message {
            padding: 16px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .message.success { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .message.error { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            padding: 24px 28px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-header i {
            font-size: 24px;
            padding: 12px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
        }
        
        .card-body {
            padding: 28px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th {
            background: rgba(102, 126, 234, 0.05);
            padding: 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #4a5568;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        tr:hover td {
            background: rgba(102, 126, 234, 0.02);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .banner-preview {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            margin-top: 12px;
        }
        
        .stats-row {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card i {
            font-size: 28px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 800;
            color: #1a1a2e;
        }
        
        .stat-card .label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="glass-header">
        <div class="user-info">
            <div class="user-avatar"><?= substr($user_name, 0, 1) ?></div>
            <div class="user-details">
                <h2><?= htmlspecialchars($user_name) ?></h2>
                <p><?= htmlspecialchars($dept_name) ?> • Department Admin</p>
            </div>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-row">
            <div class="stat-card">
                <i class="fas fa-chalkboard-teacher"></i>
                <div class="number"><?= $lecturers ? $lecturers->num_rows : 0 ?></div>
                <div class="label">Lecturers</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <div class="number"><?= $courses ? $courses->num_rows : 0 ?></div>
                <div class="label">Courses</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-cubes"></i>
                <div class="number"><?= $units ? $units->num_rows : 0 ?></div>
                <div class="label">Units</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-graduation-cap"></i>
                <div class="number"><?= $short_courses ? $short_courses->num_rows : 0 ?></div>
                <div class="label">Short Courses</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-tools"></i>
                <div class="number"><?= $technicians ? $technicians->num_rows : 0 ?></div>
                <div class="label">Technicians</div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Add Lecturer -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3>Add Lecturer</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_lecturer">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone">
                        </div>
                        <div class="form-group">
                            <label>Staff ID</label>
                            <input type="text" name="staff_id">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Lecturer</button>
                    </form>
                </div>
            </div>
            
            <!-- Add Course -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-book"></i>
                    <h3>Add Course</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_course">
                        <div class="form-group">
                            <label>Course Name *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Course Code</label>
                            <input type="text" name="code">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Course</button>
                    </form>
                </div>
            </div>
            
            <!-- Add Unit -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-cubes"></i>
                    <h3>Add Unit</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_unit">
                        <div class="form-group">
                            <label>Unit Name *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Unit Code *</label>
                            <input type="text" name="code" required>
                        </div>
                        <div class="form-group">
                            <label>Course *</label>
                            <select name="course_id" required>
                                <option value="">-- Select Course --</option>
                                <?php if ($courses): while ($c = $courses->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?><?= isset($c['code']) && $c['code'] ? ' (' . htmlspecialchars($c['code']) . ')' : '' ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Unit</button>
                    </form>
                </div>
            </div>
            
            <!-- Add Short Course -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Add Short Course</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_short_course">
                        <div class="form-group">
                            <label>Course Name *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Course Code *</label>
                            <input type="text" name="code" required placeholder="e.g., SC-101">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Duration *</label>
                            <input type="text" name="duration" required placeholder="e.g., 4 weeks">
                        </div>
                        <div class="form-group">
                            <label>Department *</label>
                            <select name="department_id" required>
                                <option value="">-- Select Department --</option>
                                <?php if ($all_departments): while ($dept = $all_departments->fetch_assoc()): ?>
                                    <option value="<?= $dept['id'] ?>" <?= $dept['id'] == $department_id ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Banner Image</label>
                            <input type="file" name="banner" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Short Course</button>
                    </form>
                </div>
            </div>
            
            <!-- Assign Tutor to Short Course -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-plus"></i>
                    <h3>Assign Tutor to Short Course</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="assignTutorForm">
                        <input type="hidden" name="action" value="assign_tutor_to_short_course">
                        <div class="form-group">
                            <label>Short Course *</label>
                            <select name="short_course_id" id="assignShortCourseId" required>
                                <option value="">-- Select Short Course --</option>
                                <?php 
                                if ($short_courses): 
                                    $short_courses->data_seek(0); 
                                    while ($sc = $short_courses->fetch_assoc()): 
                                ?>
                                    <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
                                <?php 
                                    endwhile; 
                                else: 
                                ?>
                                    <option value="" disabled>No short courses available. Create one first.</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Lecturer Email *</label>
                            <div style="display:flex;gap:8px;">
                                <input type="email" name="lecturer_email" id="lecturerEmailInput" required placeholder="Enter lecturer email address" style="flex:1;">
                                <button type="button" class="btn btn-primary btn-sm" onclick="searchLecturer()" style="padding:12px 16px;white-space:nowrap;">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                            <div id="lecturerSearchResult" style="margin-top:8px;padding:10px 14px;border-radius:8px;display:none;font-size:13px;"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="assignTutorBtn" disabled>
                            <i class="fas fa-user-plus"></i> Confirm & Assign Tutor
                        </button>
                    </form>
                </div>
            </div>
            
<script>
async function searchLecturer() {
    const email = document.getElementById('lecturerEmailInput').value.trim();
    const resultDiv = document.getElementById('lecturerSearchResult');
    const assignBtn = document.getElementById('assignTutorBtn');
    
    if (!email) {
        resultDiv.style.display = 'block';
        resultDiv.style.background = '#fee2e2';
        resultDiv.style.color = '#dc2626';
        resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter an email address.';
        assignBtn.disabled = true;
        return;
    }
    
    resultDiv.style.display = 'block';
    resultDiv.style.background = '#f3f4f6';
    resultDiv.style.color = '#374151';
    resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
    assignBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'search_lecturer');
        formData.append('email', email);
        
        const response = await fetch('', { method: 'POST', body: formData });
        const text = await response.text();
        
        // Parse the JSON from the response (it's at the start before any HTML)
        const jsonMatch = text.match(/\{.*\}/s);
        if (jsonMatch) {
            const data = JSON.parse(jsonMatch[0]);
            if (data.found) {
                resultDiv.style.background = '#dcfce7';
                resultDiv.style.color = '#166534';
                resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> <strong>' + data.name + '</strong> (' + data.email + ')';
                assignBtn.disabled = false;
            } else {
                resultDiv.style.background = '#fee2e2';
                resultDiv.style.color = '#dc2626';
                resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> No lecturer found with email: ' + email;
                assignBtn.disabled = true;
            }
        } else {
            resultDiv.style.background = '#fee2e2';
            resultDiv.style.color = '#dc2626';
            resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error searching for lecturer.';
            assignBtn.disabled = true;
        }
    } catch (error) {
        resultDiv.style.background = '#fee2e2';
        resultDiv.style.color = '#dc2626';
        resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
        assignBtn.disabled = true;
    }
}
</script>
            
            <!-- Add Lab Technician -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tools"></i>
                    <h3>Add Lab Technician</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_lab_technician">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone">
                        </div>
                        <div class="form-group">
                            <label>Staff ID *</label>
                            <input type="text" name="staff_id" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Technician</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Lecturers List -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>Lecturers</h3>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Staff ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lecturers && $lecturers->num_rows > 0): ?>
                            <?php while ($l = $lecturers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($l['name']) ?></td>
                                    <td><?= htmlspecialchars($l['email']) ?></td>
                                    <td><?= htmlspecialchars($l['phone'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($l['staff_id'] ?? '—') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="empty-state"><i class="fas fa-chalkboard-teacher"></i><p>No lecturers added yet</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Courses List -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <i class="fas fa-book"></i>
                <h3>Courses</h3>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($courses && $courses->num_rows > 0): ?>
                            <?php while ($c = $courses->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['name']) ?></td>
                                    <td><?= htmlspecialchars($c['code'] ?? '—') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="empty-state"><i class="fas fa-book"></i><p>No courses added yet</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Units List -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <i class="fas fa-cubes"></i>
                <h3>Units</h3>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Course</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($units && $units->num_rows > 0): ?>
                            <?php while ($u = $units->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['name']) ?></td>
                                    <td><?= htmlspecialchars($u['code']) ?></td>
                                    <td><?= htmlspecialchars($u['course_name']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="empty-state"><i class="fas fa-cubes"></i><p>No units added yet</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Short Courses List -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <i class="fas fa-graduation-cap"></i>
                <h3>Short Courses</h3>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Tutors</th>
                            <th>Banner</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($short_courses && $short_courses->num_rows > 0): ?>
                            <?php 
                            // Reset pointer for second iteration
                            $short_courses->data_seek(0);
                            while ($sc = $short_courses->fetch_assoc()): 
                                // Get tutors for this short course
                                $tutors = [];
                                if ($short_course_tutors) {
                                    $short_course_tutors->data_seek(0);
                                    while ($t = $short_course_tutors->fetch_assoc()) {
                                        if ($t['short_course_id'] == $sc['id']) {
                                            $tutors[] = $t['lecturer_name'];
                                        }
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($sc['name']) ?></td>
                                    <td><?= !empty($tutors) ? implode(', ', array_map('htmlspecialchars', $tutors)) : '—' ?></td>
                                    <td><?= $sc['banner'] ? '<i class="fas fa-image"></i>' : '—' ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this short course and all its modules/tutors?')" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_short_course">
                                            <input type="hidden" name="id" value="<?= $sc['id'] ?>">
                                            <button type="submit" class="btn btn-sm" style="background:#ef4444;color:#fff;padding:4px 12px;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="empty-state"><i class="fas fa-graduation-cap"></i><p>No short courses added yet</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Short Course Tutors List -->
        <?php if ($short_course_tutors && $short_course_tutors->num_rows > 0): ?>
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <i class="fas fa-user-graduate"></i>
                <h3>Assigned Tutors</h3>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Short Course</th>
                            <th>Lecturer</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($t = $short_course_tutors->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['course_name']) ?></td>
                                <td><?= htmlspecialchars($t['lecturer_name']) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Remove this tutor from the course?')" style="display:inline;">
                                        <input type="hidden" name="action" value="remove_tutor_from_short_course">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Lab Technicians List -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <i class="fas fa-tools"></i>
                <h3>Lab Technicians</h3>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Staff ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($technicians && $technicians->num_rows > 0): ?>
                            <?php while ($t = $technicians->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['name']) ?></td>
                                    <td><?= htmlspecialchars($t['email']) ?></td>
                                    <td><?= htmlspecialchars($t['phone'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($t['staff_id']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="empty-state"><i class="fas fa-tools"></i><p>No technicians added yet</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>