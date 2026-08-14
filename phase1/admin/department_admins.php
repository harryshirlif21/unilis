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
$is_department_admin = $user_role === 'department_admin';

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
        $sql = "SELECT id, name, email FROM lecturers WHERE email = '" . $conn->real_escape_string($email) . "'";
        if ($is_department_admin) $sql .= ' AND department_id = ' . (int)$department_id;
        $lec = $conn->query($sql . ' LIMIT 1');
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
            $checkDepartment = $conn->query("SHOW COLUMNS FROM lecturers LIKE 'department_id'");
            if ($is_department_admin && $checkDepartment && $checkDepartment->num_rows > 0) {
                $columns[] = 'department_id';
                $placeholders[] = '?';
                $params[] = (int)$department_id;
                $types .= 'i';
            }
            
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
    $department_id_input = $is_department_admin ? (int)$department_id : (int)($_POST['department_id'] ?? $department_id);
    
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
    $department_id_input = $is_department_admin ? (int)$department_id : (int)($_POST['department_id'] ?? 0);
    $banner = $_FILES['banner'] ?? null;
    $pricing = $_POST['pricing'] ?? 'free';
    $price = (float)($_POST['price'] ?? 0);
    $payment_methods = $_POST['payment_methods'] ?? [];
    
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
            
            // Ensure payment columns exist
            $ensureCols = [
                'is_paid' => "ALTER TABLE public_courses ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER pass_mark",
                'price' => "ALTER TABLE public_courses ADD COLUMN price DECIMAL(10,2) DEFAULT NULL AFTER is_paid",
                'payment_methods' => "ALTER TABLE public_courses ADD COLUMN payment_methods VARCHAR(255) DEFAULT NULL AFTER price",
            ];
            foreach ($ensureCols as $colName => $alterSql) {
                $colCheck = $conn->query("SHOW COLUMNS FROM public_courses LIKE '$colName'");
                if (!$colCheck || $colCheck->num_rows === 0) {
                    @$conn->query($alterSql);
                }
            }
            
            $is_paid = $pricing === 'paid' ? 1 : 0;
            $methods_str = $is_paid ? implode(',', array_map('trim', $payment_methods)) : '';
            
            $stmt = $conn->prepare("INSERT INTO public_courses (slug, title, code, summary, description, duration, department_id, cover_image, created_by_lecturer_id, is_published, is_paid, price, payment_methods) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)");
            // cover_image is a path string. Binding it as an integer turns every
            // uploaded banner into 0, which the public /learn catalogue hides.
            $stmt->bind_param('ssssssisiids', $slug, $name, $code, $description, $description, $duration, $department_id_input, $banner_path, $_SESSION['user_id'], $is_paid, $price, $methods_str);
            if ($stmt->execute()) {
                $message = "Short course added successfully! " . ($is_paid ? "Price: KSh " . number_format($price, 2) : "Free course");
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

// Edit Short Course
if ($action === 'edit_short_course') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $department_id_input = $is_department_admin ? (int)$department_id : (int)($_POST['department_id'] ?? 0);
    $banner = $_FILES['banner'] ?? null;
    $pricing = $_POST['pricing'] ?? 'free';
    $price = (float)($_POST['price'] ?? 0);
    $payment_methods = $_POST['payment_methods'] ?? [];

    if ($is_department_admin && $id) {
        $allowed = $conn->query("SELECT id FROM public_courses WHERE id = $id AND department_id = " . (int)$department_id);
        if (!$allowed || $allowed->num_rows === 0) {
            $id = 0;
            $message = 'You can only edit short courses in your department.';
            $message_type = 'error';
        }
    }

    if ($id && $name && $code && $duration && $department_id_input) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'public_courses'");
        if ($checkTable && $checkTable->num_rows > 0) {
            // Handle banner upload if provided
            $banner_path = null;
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

            // Ensure payment columns exist
            $ensureCols = [
                'is_paid' => "ALTER TABLE public_courses ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER pass_mark",
                'price' => "ALTER TABLE public_courses ADD COLUMN price DECIMAL(10,2) DEFAULT NULL AFTER is_paid",
                'payment_methods' => "ALTER TABLE public_courses ADD COLUMN payment_methods VARCHAR(255) DEFAULT NULL AFTER price",
            ];
            foreach ($ensureCols as $colName => $alterSql) {
                $colCheck = $conn->query("SHOW COLUMNS FROM public_courses LIKE '$colName'");
                if (!$colCheck || $colCheck->num_rows === 0) {
                    @$conn->query($alterSql);
                }
            }

            $is_paid = $pricing === 'paid' ? 1 : 0;
            $methods_str = $is_paid ? implode(',', array_map('trim', $payment_methods)) : '';

            // Build dynamic update query based on which columns exist
            $updates = ['slug = ?', 'title = ?', 'summary = ?', 'description = ?'];
            $params = [$slug, $name, $description, $description];
            $types = 'ssss';

            $editOptional = ['code', 'duration', 'department_id', 'is_paid', 'price', 'payment_methods'];
            $editValues = [
                'code' => $code,
                'duration' => $duration,
                'department_id' => (int)$department_id_input,
                'is_paid' => $is_paid,
                'price' => $price,
                'payment_methods' => $methods_str,
            ];
            $editTypes = [
                'code' => 's',
                'duration' => 's',
                'department_id' => 'i',
                'is_paid' => 'i',
                'price' => 'd',
                'payment_methods' => 's',
            ];

            foreach ($editOptional as $editCol) {
                $editCheck = $conn->query("SHOW COLUMNS FROM public_courses LIKE '$editCol'");
                if ($editCheck && $editCheck->num_rows > 0) {
                    $updates[] = "$editCol = ?";
                    $params[] = $editValues[$editCol];
                    $types .= $editTypes[$editCol];
                }
            }

            if ($banner_path) {
                $updates[] = 'cover_image = ?';
                $params[] = $banner_path;
                $types .= 's';
            }

            $params[] = $id;
            $types .= 'i';

            $stmt = $conn->prepare("UPDATE public_courses SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $message = "Short course updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to update short course: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Public courses table does not exist.";
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
if ($action === 'assign_self_to_short_course') {
    $short_course_id = (int)($_POST['short_course_id'] ?? 0);
    if (!$is_department_admin || !$short_course_id) {
        $message = 'Select a short course to assign to yourself.';
        $message_type = 'error';
    } else {
        $admin_id = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT created_by_lecturer_id FROM public_courses WHERE id = ? AND department_id = ? LIMIT 1');
        $stmt->bind_param('ii', $short_course_id, $department_id);
        $stmt->execute();
        $course = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$course) {
            $message = 'That short course was not found in your department.';
            $message_type = 'error';
        } elseif ((int)($course['created_by_lecturer_id'] ?? 0) === $admin_id) {
            // MySQL reports zero affected rows when the selected course is
            // already assigned to this administrator; that is not an error.
            $message = 'That short course is already assigned to you. You can open it in the course builder.';
            $message_type = 'success';
        } else {
            $stmt = $conn->prepare('UPDATE public_courses SET created_by_lecturer_id = ? WHERE id = ? AND department_id = ?');
            $stmt->bind_param('iii', $admin_id, $short_course_id, $department_id);
            $stmt->execute();
            $updated = $stmt->affected_rows > 0;
            $error = $stmt->error;
            $stmt->close();

            if ($updated) {
                $message = 'Short course assigned to you. You can now open it in the course builder.';
                $message_type = 'success';
            } else {
                $message = 'Unable to assign that short course to you' . ($error ? ': ' . $error : '.');
                $message_type = 'error';
            }
        }
    }
}

if ($action === 'assign_tutor_to_short_course') {
    $short_course_id = (int)($_POST['short_course_id'] ?? 0);
    $lecturer_email = trim($_POST['lecturer_email'] ?? '');
    $assigned_by = $_SESSION['user_id'];

    if ($is_department_admin && $short_course_id) {
        $course = $conn->query("SELECT id FROM public_courses WHERE id = $short_course_id AND department_id = " . (int)$department_id);
        if (!$course || $course->num_rows === 0) {
            $short_course_id = 0;
            $message = 'You can only assign tutors to short courses in your department.';
            $message_type = 'error';
        }
    }
    
    if ($short_course_id && $lecturer_email) {
        // Find lecturer by email (search across all lecturers)
        $lecSql = "SELECT id FROM lecturers WHERE email = '" . $conn->real_escape_string($lecturer_email) . "'";
        if ($is_department_admin) $lecSql .= ' AND department_id = ' . (int)$department_id;
        $lec = $conn->query($lecSql . ' LIMIT 1');
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
    $lecturerWhere = $is_department_admin ? ' WHERE department_id = ' . (int)$department_id : '';
    $lecturers = $conn->query("SELECT $lecturerFields FROM lecturers$lecturerWhere ORDER BY name");
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
$my_short_courses = [];
$checkPublicCourses = $conn->query("SHOW TABLES LIKE 'public_courses'");
if ($checkPublicCourses && $checkPublicCourses->num_rows > 0) {
    // Build dynamic column list based on what exists in the table
    $scColumns = ['id', 'title as name', 'summary as description', 'cover_image as banner'];
    $scOptional = ['code', 'duration', 'department_id', 'is_paid', 'price', 'payment_methods'];
    foreach ($scOptional as $col) {
        $colCheck = $conn->query("SHOW COLUMNS FROM public_courses LIKE '$col'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $scColumns[] = $col;
        }
    }
    $scFields = implode(', ', $scColumns);
    $shortCourseWhere = $is_department_admin ? ' WHERE department_id = ' . (int)$department_id : '';
    $short_courses = $conn->query("SELECT $scFields FROM public_courses$shortCourseWhere ORDER BY title");

    // "Assign to Me" marks the department admin as the course owner. Keep a
    // separate list so the dashboard can expose the same short-course entry
    // point lecturers receive, without showing courses assigned to others.
    if ($is_department_admin) {
        $adminId = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT id, title FROM public_courses WHERE created_by_lecturer_id = ? AND department_id = ? ORDER BY title');
        $stmt->bind_param('ii', $adminId, $department_id);
        $stmt->execute();
        $my_short_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
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
        " . ($is_department_admin ? 'WHERE pc.department_id = ' . (int)$department_id : '') . "
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
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface2: #f1f5f9;
            --border: #e2e8f0;
            --accent: #6366f1;
            --accent2: #8b5cf6;
            --accent3: #06b6d4;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text: #0f172a;
            --text-muted: #64748b;
            --text-dim: #94a3b8;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.08);
            --tr: 0.15s ease;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            color: var(--text);
        }

        /* ── TOP NAV ── */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 16px;
            color: var(--text);
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .brand small {
            font-weight: 400;
            color: var(--text-muted);
            font-size: 12px;
            margin-left: 4px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 13px;
            font-weight: 600;
        }

        .logout-btn {
            background: var(--surface2);
            color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: var(--tr);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .logout-btn:hover {
            background: #fee2e2;
            color: var(--danger);
            border-color: #fecaca;
        }

        /* ── LAYOUT ── */
        .layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 240px;
            min-width: 240px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 20px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            padding: 12px 12px 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--tr);
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: 'Inter', sans-serif;
        }

        .sidebar a.nav-item { text-decoration: none; }

        .nav-item i {
            width: 18px;
            text-align: center;
            font-size: 14px;
        }

        .nav-item:hover {
            background: var(--surface2);
            color: var(--text);
        }

        .nav-item.active {
            background: rgba(99, 102, 241, 0.08);
            color: var(--accent);
            font-weight: 600;
        }

        .nav-item .count {
            margin-left: auto;
            background: var(--surface2);
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .nav-item.active .count {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent);
        }

        /* ── MAIN ── */
        .main {
            flex: 1;
            padding: 28px 32px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .page-header p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ── MESSAGE ── */
        .message {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.25s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .message.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .message.warning { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

        /* ── STATS ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: var(--tr);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-bottom: 12px;
        }

        .stat-card .stat-icon.purple { background: rgba(99,102,241,0.1); color: var(--accent); }
        .stat-card .stat-icon.blue { background: rgba(6,182,212,0.1); color: var(--accent3); }
        .stat-card .stat-icon.green { background: rgba(16,185,129,0.1); color: var(--success); }
        .stat-card .stat-icon.orange { background: rgba(245,158,11,0.1); color: var(--warning); }
        .stat-card .stat-icon.red { background: rgba(239,68,68,0.1); color: var(--danger); }

        .stat-card .number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .stat-card .label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ── PANELS ── */
        .panel {
            display: none;
        }

        .panel.active {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ── CARDS ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .card-header .card-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            background: rgba(99,102,241,0.1);
            color: var(--accent);
        }

        .card-body {
            padding: 20px;
        }

        /* ── FORMS ── */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            transition: var(--tr);
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
            background: var(--surface);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
        }

        /* ── BUTTONS ── */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: var(--tr);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }

        .btn-danger {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .btn-danger:hover {
            background: #fee2e2;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* ── TABLE ── */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: var(--surface2);
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-dim);
        }

        .empty-state i {
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.4;
        }

        /* ── PRICING TOGGLE ── */
        .pricing-options {
            display: flex;
            gap: 16px;
            margin-top: 4px;
        }

        .pricing-options label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            cursor: pointer;
            font-size: 13px;
            color: var(--text);
        }

        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 4px;
        }

        .payment-methods label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            cursor: pointer;
            font-size: 13px;
            color: var(--text);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .layout { flex-direction: column; }
            .sidebar { width: 100%; min-width: unset; flex-direction: row; flex-wrap: wrap; padding: 12px; }
            .sidebar-label { display: none; }
            .nav-item { width: auto; }
            .main { padding: 16px; }
            .topbar { padding: 0 16px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
            UNILIS <small>Department Admin</small>
        </div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="user-avatar"><?= substr($user_name, 0, 1) ?></div>
                <span><?= htmlspecialchars($user_name) ?></span>
            </div>
            <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-label">Overview</div>
            <button class="nav-item active" data-panel="overview" onclick="switchPanel('overview', this)">
                <i class="fas fa-th-large"></i> Overview
            </button>

            <div class="sidebar-label">Add</div>
            <button class="nav-item" data-panel="lecturers" onclick="switchPanel('lecturers', this)">
                <i class="fas fa-chalkboard-teacher"></i> Lecturers
            </button>
            <button class="nav-item" data-panel="courses" onclick="switchPanel('courses', this)">
                <i class="fas fa-book"></i> Courses
            </button>
            <button class="nav-item" data-panel="units" onclick="switchPanel('units', this)">
                <i class="fas fa-cubes"></i> Units
            </button>
            <button class="nav-item" data-panel="short-courses" onclick="switchPanel('short-courses', this)">
                <i class="fas fa-graduation-cap"></i> Short Courses
            </button>
            <button class="nav-item" data-panel="technicians" onclick="switchPanel('technicians', this)">
                <i class="fas fa-tools"></i> Technicians
            </button>

            <div class="sidebar-label">Manage</div>
            <button class="nav-item" data-panel="tutors" onclick="switchPanel('tutors', this)">
                <i class="fas fa-user-plus"></i> Assign Tutors
            </button>

            <?php if ($is_department_admin && !empty($my_short_courses)): ?>
            <div class="sidebar-label">My Teaching</div>
            <a class="nav-item" href="../../lecturer/catalogue.php" title="Open your assigned short courses">
                <i class="fas fa-graduation-cap"></i> My Short Courses
                <span class="count"><?= count($my_short_courses) ?></span>
            </a>
            <?php endif; ?>
        </aside>

        <main class="main">
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- OVERVIEW -->
            <div class="panel active" id="panel-overview">
                <div class="page-header">
                    <h1>Overview</h1>
                    <p>Manage your department's academic resources</p>
                </div>
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="number"><?= $lecturers ? $lecturers->num_rows : 0 ?></div>
                        <div class="label">Lecturers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-book"></i></div>
                        <div class="number"><?= $courses ? $courses->num_rows : 0 ?></div>
                        <div class="label">Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-cubes"></i></div>
                        <div class="number"><?= $units ? $units->num_rows : 0 ?></div>
                        <div class="label">Units</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fas fa-graduation-cap"></i></div>
                        <div class="number"><?= $short_courses ? $short_courses->num_rows : 0 ?></div>
                        <div class="label">Short Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-tools"></i></div>
                        <div class="number"><?= $technicians ? $technicians->num_rows : 0 ?></div>
                        <div class="label">Technicians</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <h3>Lecturers</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Staff ID</th></tr></thead>
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
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-graduation-cap"></i></div>
                        <h3>Short Courses</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Name</th><th>Tutors</th><th>Banner</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php if ($short_courses && $short_courses->num_rows > 0): ?>
                                        <?php
                                        $short_courses->data_seek(0);
                                        while ($sc = $short_courses->fetch_assoc()):
                                            $tutors = [];
                                            if ($short_course_tutors) {
                                                $short_course_tutors->data_seek(0);
                                                while ($t = $short_course_tutors->fetch_assoc()) {
                                                    if ($t['short_course_id'] == $sc['id']) $tutors[] = $t['lecturer_name'];
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($sc['name']) ?></td>
                                                <td><?= !empty($tutors) ? implode(', ', array_map('htmlspecialchars', $tutors)) : '—' ?></td>
                                                <td><?= $sc['banner'] ? '<i class="fas fa-image"></i>' : '—' ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-sm" style="margin-right:4px;" data-edit-sc='<?= htmlspecialchars(json_encode($sc), ENT_QUOTES, 'UTF-8') ?>' onclick="openEditModalFromBtn(this)">
                                                        <i class="fas fa-pen"></i> Edit
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Delete this short course?')" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete_short_course">
                                                        <input type="hidden" name="id" value="<?= $sc['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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
                </div>
            </div>

            <!-- LECTURERS -->
            <div class="panel" id="panel-lecturers">
                <div class="page-header">
                    <h1>Lecturers</h1>
                    <p>Add and manage department lecturers</p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-user-plus"></i></div>
                        <h3>Add Lecturer</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_lecturer">
                            <div class="form-grid">
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
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Lecturer</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-list"></i></div>
                        <h3>All Lecturers</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Staff ID</th></tr></thead>
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
                </div>
            </div>

            <!-- COURSES -->
            <div class="panel" id="panel-courses">
                <div class="page-header">
                    <h1>Courses</h1>
                    <p>Add and manage department courses</p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-plus"></i></div>
                        <h3>Add Course</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_course">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Course Name *</label>
                                    <input type="text" name="name" required>
                                </div>
                                <div class="form-group">
                                    <label>Course Code</label>
                                    <input type="text" name="code">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Course</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-list"></i></div>
                        <h3>All Courses</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Name</th><th>Code</th></tr></thead>
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
                </div>
            </div>

            <!-- UNITS -->
            <div class="panel" id="panel-units">
                <div class="page-header">
                    <h1>Units</h1>
                    <p>Add and manage course units</p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-plus"></i></div>
                        <h3>Add Unit</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_unit">
                            <div class="form-grid">
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
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Unit</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-list"></i></div>
                        <h3>All Units</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Name</th><th>Code</th><th>Course</th></tr></thead>
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
                </div>
            </div>

            <!-- SHORT COURSES -->
            <div class="panel" id="panel-short-courses">
                <div class="page-header">
                    <h1>Short Courses</h1>
                    <p>Create and manage open short courses</p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-plus"></i></div>
                        <h3>Add Short Course</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_short_course">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Course Name *</label>
                                    <input type="text" name="name" required>
                                </div>
                                <div class="form-group">
                                    <label>Course Code *</label>
                                    <input type="text" name="code" required placeholder="e.g., SC-101">
                                </div>
                                <div class="form-group">
                                    <label>Duration *</label>
                                    <input type="text" name="duration" required placeholder="e.g., 4 weeks">
                                </div>
                                <div class="form-group">
                                    <label>Department *</label>
                                    <select name="department_id" required>
                                        <option value="">-- Select --</option>
                                        <?php if ($all_departments): while ($dept = $all_departments->fetch_assoc()): ?>
                                            <option value="<?= $dept['id'] ?>" <?= $dept['id'] == $department_id ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" rows="2"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Banner Image</label>
                                <input type="file" name="banner" accept="image/*">
                            </div>
                            <div class="form-group">
                                <label>Course Pricing</label>
                                <div class="pricing-options">
                                    <label><input type="radio" name="pricing" value="free" checked id="pricing_free" onchange="togglePricing()"> Free</label>
                                    <label><input type="radio" name="pricing" value="paid" id="pricing_paid" onchange="togglePricing()"> Paid</label>
                                </div>
                            </div>
                            <div class="form-group" id="price_group" style="display:none;">
                                <label>Price (KSh) *</label>
                                <input type="number" name="price" min="0" step="0.01" placeholder="e.g. 2000">
                            </div>
                            <div class="form-group" id="payment_methods_group" style="display:none;">
                                <label>Accepted Payment Methods</label>
                                <div class="payment-methods">
                                    <label><input type="checkbox" name="payment_methods[]" value="mpesa" checked> <i class="fas fa-mobile-alt" style="color:#10b981;"></i> M-Pesa (STK Push)</label>
                                    <label><input type="checkbox" name="payment_methods[]" value="card"> <i class="fas fa-credit-card" style="color:#6366f1;"></i> Card (Visa / Mastercard)</label>
                                    <label><input type="checkbox" name="payment_methods[]" value="bank"> <i class="fas fa-landmark" style="color:#8b5cf6;"></i> Bank Transfer</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Short Course</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-list"></i></div>
                        <h3>All Short Courses</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Name</th><th>Tutors</th><th>Banner</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php if ($short_courses && $short_courses->num_rows > 0): ?>
                                        <?php
                                        $short_courses->data_seek(0);
                                        while ($sc = $short_courses->fetch_assoc()):
                                            $tutors = [];
                                            if ($short_course_tutors) {
                                                $short_course_tutors->data_seek(0);
                                                while ($t = $short_course_tutors->fetch_assoc()) {
                                                    if ($t['short_course_id'] == $sc['id']) $tutors[] = $t['lecturer_name'];
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($sc['name']) ?></td>
                                                <td><?= !empty($tutors) ? implode(', ', array_map('htmlspecialchars', $tutors)) : '—' ?></td>
                                                <td><?= $sc['banner'] ? '<i class="fas fa-image"></i>' : '—' ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-sm" style="margin-right:4px;" data-edit-sc='<?= htmlspecialchars(json_encode($sc), ENT_QUOTES, 'UTF-8') ?>' onclick="openEditModalFromBtn(this)">
                                                        <i class="fas fa-pen"></i> Edit
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Delete this short course?')" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete_short_course">
                                                        <input type="hidden" name="id" value="<?= $sc['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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
                </div>
            </div>

            <!-- TECHNICIANS -->
            <div class="panel" id="panel-technicians">
                <div class="page-header">
                    <h1>Lab Technicians</h1>
                    <p>Add and manage lab technicians</p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-plus"></i></div>
                        <h3>Add Lab Technician</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_lab_technician">
                            <div class="form-grid">
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
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Technician</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-list"></i></div>
                        <h3>All Technicians</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Staff ID</th></tr></thead>
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
            </div>

            <!-- TUTORS -->
            <div class="panel" id="panel-tutors">
                <div class="page-header">
                    <h1>Assign Tutors</h1>
                    <p>Assign lecturers to short courses</p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-user-plus"></i></div>
                        <h3>Assign Tutor to Short Course</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assignTutorForm">
                            <input type="hidden" name="action" value="assign_tutor_to_short_course">
                            <div class="form-grid">
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
                                        <input type="email" name="lecturer_email" id="lecturerEmailInput" required placeholder="Enter lecturer email" style="flex:1;">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="searchLecturer()" style="white-space:nowrap;">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                    </div>
                                    <div id="lecturerSearchResult" style="margin-top:8px;padding:10px 14px;border-radius:8px;display:none;font-size:13px;"></div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" id="assignTutorBtn" disabled>
                                <i class="fas fa-user-plus"></i> Confirm & Assign Tutor
                            </button>
                        </form>
                        <?php if ($is_department_admin): ?>
                        <form method="POST" style="margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
                            <input type="hidden" name="action" value="assign_self_to_short_course">
                            <div class="form-group">
                                <label>Assign a Short Course to Yourself</label>
                                <select name="short_course_id" required>
                                    <option value="">-- Select Short Course --</option>
                                    <?php if ($short_courses): $short_courses->data_seek(0); while ($sc = $short_courses->fetch_assoc()): ?>
                                        <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary"><i class="fas fa-user-check"></i> Assign to Me</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($short_course_tutors && $short_course_tutors->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-list"></i></div>
                        <h3>Assigned Tutors</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Short Course</th><th>Lecturer</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php while ($t = $short_course_tutors->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($t['course_name']) ?></td>
                                            <td><?= htmlspecialchars($t['lecturer_name']) ?></td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Remove this tutor?')" style="display:inline;">
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
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Edit Short Course Modal -->
    <div id="editShortCourseModal" class="modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); overflow-y:auto;">
        <div style="background:var(--surface); max-width:640px; margin:5% auto; padding:28px 32px; border-radius:var(--radius); box-shadow:var(--shadow-lg); position:relative;">
            <button type="button" onclick="closeEditModal()" style="position:absolute; top:16px; right:16px; width:32px; height:32px; border-radius:50%; background:var(--surface2); border:1px solid var(--border); cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:14px;">&times;</button>
            <h3 style="font-size:1.1rem; font-weight:700; margin-bottom:20px; color:var(--text); display:flex; align-items:center; gap:10px;">
                <i class="fas fa-pen"></i> Edit Short Course
            </h3>
            <form method="POST" enctype="multipart/form-data" action="">
                <input type="hidden" name="action" value="edit_short_course">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course Name *</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Course Code *</label>
                        <input type="text" name="code" id="edit_code" required placeholder="e.g., SC-101">
                    </div>
                    <div class="form-group">
                        <label>Duration *</label>
                        <input type="text" name="duration" id="edit_duration" required placeholder="e.g., 4 weeks">
                    </div>
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department_id" id="edit_department_id" required>
                            <option value="">-- Select --</option>
                            <?php if ($all_departments): $all_departments->data_seek(0); while ($dept = $all_departments->fetch_assoc()): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Banner Image</label>
                    <input type="file" name="banner" accept="image/*">
                    <p style="font-size:0.75rem; color:var(--text-dim); margin-top:4px;">Leave empty to keep the current banner.</p>
                </div>
                <div class="form-group">
                    <label>Course Pricing</label>
                    <div class="pricing-options">
                        <label><input type="radio" name="pricing" value="free" id="edit_pricing_free" onchange="toggleEditPricing()"> Free</label>
                        <label><input type="radio" name="pricing" value="paid" id="edit_pricing_paid" onchange="toggleEditPricing()"> Paid</label>
                    </div>
                </div>
                <div class="form-group" id="edit_price_group" style="display:none;">
                    <label>Price (KSh) *</label>
                    <input type="number" name="price" id="edit_price" min="0" step="0.01" placeholder="e.g. 2000">
                </div>
                <div class="form-group" id="edit_payment_methods_group" style="display:none;">
                    <label>Accepted Payment Methods</label>
                    <div class="payment-methods">
                        <label><input type="checkbox" name="payment_methods[]" value="mpesa" id="edit_pay_mpesa"> <i class="fas fa-mobile-alt" style="color:#10b981;"></i> M-Pesa (STK Push)</label>
                        <label><input type="checkbox" name="payment_methods[]" value="card" id="edit_pay_card"> <i class="fas fa-credit-card" style="color:#6366f1;"></i> Card (Visa / Mastercard)</label>
                        <label><input type="checkbox" name="payment_methods[]" value="bank" id="edit_pay_bank"> <i class="fas fa-landmark" style="color:#8b5cf6;"></i> Bank Transfer</label>
                    </div>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="background:var(--surface2); color:var(--text-muted); border:1px solid var(--border);">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function switchPanel(panelId, el) {
        document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.getElementById('panel-' + panelId).classList.add('active');
        if (el) el.classList.add('active');
    }

    function openEditModalFromBtn(btn) {
        try {
            const sc = JSON.parse(btn.getAttribute('data-edit-sc'));
            openEditModal(sc);
        } catch (e) {
            console.error('Failed to parse edit data:', e);
        }
    }

    function openEditModal(sc) {
        document.getElementById('edit_id').value = sc.id || '';
        document.getElementById('edit_name').value = sc.name || '';
        document.getElementById('edit_code').value = sc.code || '';
        document.getElementById('edit_duration').value = sc.duration || '';
        document.getElementById('edit_description').value = sc.description || '';
        document.getElementById('edit_department_id').value = sc.department_id || '';
        document.getElementById('edit_price').value = sc.price || '';

        // Payment methods
        const methods = sc.payment_methods ? String(sc.payment_methods).split(',') : [];
        document.getElementById('edit_pay_mpesa').checked = methods.includes('mpesa');
        document.getElementById('edit_pay_card').checked = methods.includes('card');
        document.getElementById('edit_pay_bank').checked = methods.includes('bank');

        // Pricing radio
        const isPaid = parseInt(sc.is_paid) === 1;
        document.getElementById('edit_pricing_free').checked = !isPaid;
        document.getElementById('edit_pricing_paid').checked = isPaid;
        toggleEditPricing();

        document.getElementById('editShortCourseModal').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('editShortCourseModal').style.display = 'none';
    }

    function toggleEditPricing() {
        const isPaid = document.getElementById('edit_pricing_paid').checked;
        document.getElementById('edit_price_group').style.display = isPaid ? '' : 'none';
        document.getElementById('edit_payment_methods_group').style.display = isPaid ? '' : 'none';
        if (isPaid) {
            document.getElementById('edit_price').required = true;
        } else {
            document.getElementById('edit_price').required = false;
        }
    }

    // Close modal on backdrop click
    document.getElementById('editShortCourseModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    function togglePricing() {
        const isPaid = document.getElementById('pricing_paid').checked;
        document.getElementById('price_group').style.display = isPaid ? '' : 'none';
        document.getElementById('payment_methods_group').style.display = isPaid ? '' : 'none';
        if (isPaid) {
            document.querySelector('#price_group input[name="price"]').required = true;
        } else {
            document.querySelector('#price_group input[name="price"]').required = false;
        }
    }

    async function searchLecturer() {
        const email = document.getElementById('lecturerEmailInput').value.trim();
        const resultDiv = document.getElementById('lecturerSearchResult');
        const assignBtn = document.getElementById('assignTutorBtn');
        
        if (!email) {
            resultDiv.style.display = 'block';
            resultDiv.style.background = '#fef2f2';
            resultDiv.style.color = '#dc2626';
            resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter an email address.';
            assignBtn.disabled = true;
            return;
        }
        
        resultDiv.style.display = 'block';
        resultDiv.style.background = '#f1f5f9';
        resultDiv.style.color = '#334155';
        resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
        assignBtn.disabled = true;
        
        try {
            const formData = new FormData();
            formData.append('action', 'search_lecturer');
            formData.append('email', email);
            
            const response = await fetch('', { method: 'POST', body: formData });
            const text = await response.text();
            
            const jsonMatch = text.match(/\{.*\}/s);
            if (jsonMatch) {
                const data = JSON.parse(jsonMatch[0]);
                if (data.found) {
                    resultDiv.style.background = '#ecfdf5';
                    resultDiv.style.color = '#059669';
                    resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> <strong>' + data.name + '</strong> (' + data.email + ')';
                    assignBtn.disabled = false;
                } else {
                    resultDiv.style.background = '#fef2f2';
                    resultDiv.style.color = '#dc2626';
                    resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> No lecturer found with email: ' + email;
                    assignBtn.disabled = true;
                }
            } else {
                resultDiv.style.background = '#fef2f2';
                resultDiv.style.color = '#dc2626';
                resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error searching for lecturer.';
                assignBtn.disabled = true;
            }
        } catch (error) {
            resultDiv.style.background = '#fef2f2';
            resultDiv.style.color = '#dc2626';
            resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
            assignBtn.disabled = true;
        }
    }
    </script>
</body>
</html>
