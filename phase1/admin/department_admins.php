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

/**
 * Upload an image file to a subdirectory under uploads/, creating the folder if
 * missing (Docker-safe: passes mkdir through and re-checks writability). Returns
 * the web path on success, or an error string in the shape ['error' => msg].
 */
function upload_short_course_image(array $file, string $subdir): array
{
    // Physical base = <project>/uploads (works in both XAMPP and Docker mount).
    $projectRoot = dirname(__DIR__, 2); // .../phase1/admin -> project root
    $absDir = $projectRoot . '/uploads/' . $subdir;

    // 1) Ensure folder exists (auto-create, including parent uploads/).
    if (!is_dir($absDir)) {
        @mkdir($absDir, 0755, true);
    }
    if (!is_dir($absDir)) {
        return ['ok' => false, 'msg' => "Upload folder could not be created: {$subdir}. Please create it manually and ensure the web server can write to it."];
    }
    if (!is_writable($absDir)) {
        return ['ok' => false, 'msg' => "Upload folder is not writable: {$subdir}. Fix permissions (e.g. chown www-data / chmod 775) and retry."];
    }

    // 2) Validate the uploaded file errors.
    if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the form.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded (network interruption).',
            UPLOAD_ERR_NO_FILE    => 'No file was submitted.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP has no temporary folder configured (upload_tmp_dir).',
            UPLOAD_ERR_CANT_WRITE => 'PHP could not write the file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];
        $code = (int)$file['error'];
        return ['ok' => false, 'msg' => 'Upload error: ' . ($uploadErrors[$code] ?? "code {$code}.")];
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'msg' => 'No valid uploaded file provided.'];
    }

    // 3. Sanitise name and write file.
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($file['name']));
    $storedName = time() . '_' . $safe_name;
    $webPath = '/uploads/' . $subdir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $absDir . '/' . $storedName)) {
        return ['ok' => false, 'msg' => "Failed to move uploaded file into {$subdir}. Check folder permissions."];
    }
    return ['ok' => true, 'path' => $webPath];
}

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

// AJAX: Get course sponsors (returns JSON only)
if ($action === 'get_course_sponsors') {
    header('Content-Type: application/json');
    $course_id = (int)($_POST['course_id'] ?? 0);
    if ($course_id) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'course_sponsors'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $stmt = $conn->prepare("SELECT id, sponsor_name, sponsor_details, sponsor_logo FROM course_sponsors WHERE course_id = ? ORDER BY id");
            $stmt->bind_param('i', $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $sponsors = [];
            while ($row = $result->fetch_assoc()) {
                $sponsors[] = $row;
            }
            echo json_encode(['success' => true, 'sponsors' => $sponsors]);
        } else {
            echo json_encode(['success' => true, 'sponsors' => []]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
    }
    exit;
}

// AJAX: Get course modules for tutor permissions
if ($action === 'get_course_modules') {
    header('Content-Type: application/json');
    $course_id = (int)($_POST['course_id'] ?? 0);
    if ($course_id) {
        // Check if table exists first
        $checkTable = $conn->query("SHOW TABLES LIKE 'public_course_modules'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Modules table does not exist. Please run database migrations.']);
            exit;
        }
        
        // Check which columns exist to handle different schema versions
        $columns = ['id', 'title'];
        $columnCheck = $conn->query("SHOW COLUMNS FROM public_course_modules LIKE 'summary'");
        if ($columnCheck && $columnCheck->num_rows > 0) {
            $columns[] = 'summary';
        }
        $columnCheck = $conn->query("SHOW COLUMNS FROM public_course_modules LIKE 'start_date'");
        if ($columnCheck && $columnCheck->num_rows > 0) {
            $columns[] = 'start_date';
        }
        $columnCheck = $conn->query("SHOW COLUMNS FROM public_course_modules LIKE 'end_date'");
        if ($columnCheck && $columnCheck->num_rows > 0) {
            $columns[] = 'end_date';
        }
        
        $columnList = implode(', ', $columns);
        $stmt = $conn->prepare("
            SELECT $columnList
            FROM public_course_modules
            WHERE course_id = ?
            ORDER BY position ASC, id ASC
        ");
        $stmt->bind_param('i', $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $modules = [];
        while ($row = $result->fetch_assoc()) {
            // Ensure all expected keys exist even if column doesn't
            if (!isset($row['summary'])) $row['summary'] = '';
            if (!isset($row['start_date'])) $row['start_date'] = '';
            if (!isset($row['end_date'])) $row['end_date'] = '';
            $modules[] = $row;
        }
        echo json_encode(['success' => true, 'modules' => $modules]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
    }
    exit;
}

// AJAX: Get lessons for a module (cascading tutor-assignment dropdown)
if ($action === 'get_module_lessons') {
    header('Content-Type: application/json');
    $module_id = (int)($_POST['module_id'] ?? 0);
    if ($module_id) {
        $stmt = $conn->prepare("
            SELECT id, title
            FROM public_course_lessons
            WHERE module_id = ?
            ORDER BY position ASC, id ASC
        ");
        $stmt->bind_param('i', $module_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lessons = [];
        while ($row = $result->fetch_assoc()) {
            $lessons[] = ['id' => (int)$row['id'], 'title' => $row['title']];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'lessons' => $lessons]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Module ID required']);
    }
    exit;
}

// AJAX: Get tutor module permissions
if ($action === 'get_tutor_module_permissions') {
    header('Content-Type: application/json');
    $tutor_id = (int)($_POST['tutor_id'] ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    if ($tutor_id && $course_id) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT tmp.module_id, tmp.can_edit, tmp.can_teach, m.title
                FROM tutor_module_permissions tmp
                JOIN public_course_modules m ON m.id = tmp.module_id
                WHERE tmp.tutor_id = ? AND m.course_id = ?
            ");
            $stmt->bind_param('ii', $tutor_id, $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $permissions = [];
            while ($row = $result->fetch_assoc()) {
                $permissions[$row['module_id']] = [
                    'can_edit' => (bool)$row['can_edit'],
                    'can_teach' => (bool)$row['can_teach'],
                    'title' => $row['title']
                ];
            }
            echo json_encode(['success' => true, 'permissions' => $permissions]);
        } else {
            echo json_encode(['success' => true, 'permissions' => []]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Tutor ID and Course ID required']);
    }
    exit;
}

// AJAX: Save tutor module permissions
if ($action === 'save_tutor_module_permissions') {
    header('Content-Type: application/json');
    $tutor_id = (int)($_POST['tutor_id'] ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    $permissions = $_POST['permissions'] ?? [];
    
    if (!$tutor_id || !$course_id) {
        echo json_encode(['success' => false, 'message' => 'Tutor ID and Course ID required']);
        exit;
    }
    
    $checkTable = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Permissions table not found']);
        exit;
    }
    
    try {
        $conn->begin_transaction();
        
        // Delete existing permissions for this tutor in this course
        $stmt = $conn->prepare("
            DELETE tmp FROM tutor_module_permissions tmp
            JOIN public_course_modules m ON m.id = tmp.module_id
            WHERE tmp.tutor_id = ? AND m.course_id = ?
        ");
        $stmt->bind_param('ii', $tutor_id, $course_id);
        $stmt->execute();
        $stmt->close();
        
        // Insert new permissions
        $assigned_by = $_SESSION['user_id'] ?? 0;
        $stmt = $conn->prepare("
            INSERT INTO tutor_module_permissions (tutor_id, module_id, can_edit, can_teach, assigned_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($permissions as $module_id => $perm) {
            $can_edit = isset($perm['can_edit']) && $perm['can_edit'] ? 1 : 0;
            $can_teach = isset($perm['can_teach']) && $perm['can_teach'] ? 1 : 0;
            
            if ($can_edit || $can_teach) {
                $stmt->bind_param('iiiii', $tutor_id, $module_id, $can_edit, $can_teach, $assigned_by);
                $stmt->execute();
            }
        }
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Permissions saved successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to save permissions: ' . $e->getMessage()]);
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
    
    // Handle multiple sponsors
    $sponsor_names = $_POST['sponsor_name'] ?? [];
    $sponsor_details = $_POST['sponsor_details'] ?? [];
    $sponsor_logos = $_FILES['sponsor_logo'] ?? [];
    
    $has_sponsors = false;
    $sponsors_valid = true;
    
    // Check if any sponsor data is provided
    if (is_array($sponsor_names) && !empty(array_filter($sponsor_names))) {
        $has_sponsors = true;
        // Validate each sponsor has required fields
        foreach ($sponsor_names as $index => $sname) {
            $sname = trim($sname);
            if (!empty($sname)) {
                if (!isset($sponsor_logos['name'][$index]) || $sponsor_logos['error'][$index] !== UPLOAD_ERR_OK) {
                    $sponsors_valid = false;
                    $message = "Sponsor at position " . ($index + 1) . " requires a logo.";
                    $message_type = 'error';
                    break;
                }
            }
        }
    }
    
    if ($name && $code && $duration && $department_id_input && (!$has_sponsors || $sponsors_valid)) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'public_courses'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $banner_path = '';
            if ($banner && $banner['error'] !== UPLOAD_ERR_NO_FILE) {
                $bannerRes = upload_short_course_image($banner, 'short_courses');
                if ($bannerRes['ok']) {
                    $banner_path = $bannerRes['path'];
                } else {
                    $message = 'Banner upload failed: ' . $bannerRes['msg'];
                    $message_type = 'error';
                }
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
            $is_sponsored = $has_sponsors ? 1 : 0;
            
            // Insert course without sponsor data (sponsors go in separate table)
            $stmt = $conn->prepare("INSERT INTO public_courses (slug, title, code, summary, description, duration, department_id, cover_image, created_by_lecturer_id, is_published, is_paid, price, payment_methods, is_sponsored) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssisiidsi', $slug, $name, $code, $description, $description, $duration, $department_id_input, $banner_path, $_SESSION['user_id'], $is_paid, $price, $methods_str, $is_sponsored);
            
            if ($stmt->execute()) {
                $course_id = $stmt->insert_id;
                $stmt->close();
                
                // Insert sponsors into course_sponsors table
                $sponsor_count = 0;
                if ($has_sponsors && $course_id) {
                    $sponsor_upload_dir = __DIR__ . '/../../uploads/short_courses/sponsors/';
                    if (!is_dir($sponsor_upload_dir)) mkdir($sponsor_upload_dir, 0755, true);
                    
                    foreach ($sponsor_names as $index => $sname) {
                        $sname = trim($sname);
                        if (!empty($sname)) {
                            $sdetails = trim($sponsor_details[$index] ?? '');
                            
                            // Handle sponsor logo upload
                            $slogo_path = '';
                            if (isset($sponsor_logos['name'][$index]) && !empty($sponsor_logos['name'][$index])) {
                                $logoFile = [
                                    'name' => $sponsor_logos['name'][$index],
                                    'tmp_name' => $sponsor_logos['tmp_name'][$index],
                                    'error' => $sponsor_logos['error'][$index],
                                    'size' => $sponsor_logos['size'][$index],
                                ];
                                $logoRes = upload_short_course_image($logoFile, 'short_courses/sponsors');
                                if ($logoRes['ok']) {
                                    $slogo_path = $logoRes['path'];
                                } else {
                                    $message = 'Sponsor logo upload failed for "' . $sname . '": ' . $logoRes['msg'];
                                    $message_type = 'error';
                                }
                            }
                            $sponsor_stmt = $conn->prepare("INSERT INTO course_sponsors (course_id, sponsor_name, sponsor_details, sponsor_logo) VALUES (?, ?, ?, ?)");
                            $sponsor_stmt->bind_param('isss', $course_id, $sname, $sdetails, $slogo_path);
                            $sponsor_stmt->execute();
                            $sponsor_stmt->close();
                            $sponsor_count++;
                        }
                    }
                }
                
                if ($message_type === 'error') {
                    // Preserve the upload error already set (banner/sponsor logo).
                    $message = rtrim($message, '.') . '. The banner upload did not persist — please check the upload folder and retry.';
                } else {
                    $message = "Short course added successfully! " . ($is_paid ? "Price: KSh " . number_format($price, 2) : "Free course") . ($sponsor_count > 0 ? " with $sponsor_count sponsor(s)." : "");
                    $message_type = 'success';
                }
                $short_courses_refreshed = true;
            } else {
                $message = "Failed to add short course: " . $stmt->error;
                $message_type = 'error';
                $stmt->close();
            }
        } else {
            $message = "Public courses table does not exist. Please run database migrations.";
            $message_type = 'error';
        }
    } else {
        $message = 'Course Name, Code, Duration, and Department are required.';
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
    
    // Debug logging
    error_log("Edit short course POST data: ID=$id, name=$name, code=$code, duration=$duration, dept_input=$department_id_input, is_dept_admin=$is_department_admin, session_dept=$department_id");
    
    $banner = $_FILES['banner'] ?? null;
    $pricing = $_POST['pricing'] ?? 'free';
    $price = (float)($_POST['price'] ?? 0);
    $payment_methods = $_POST['payment_methods'] ?? [];
    
    // Handle multiple sponsors
    $sponsor_ids = $_POST['sponsor_id'] ?? [];
    $sponsor_names = $_POST['sponsor_name'] ?? [];
    $sponsor_details = $_POST['sponsor_details'] ?? [];
    $sponsor_logos = $_FILES['sponsor_logo'] ?? [];
    
    $has_sponsors = false;
    $sponsors_valid = true;
    
    // Check if any sponsor data is provided
    if (is_array($sponsor_names) && !empty(array_filter($sponsor_names))) {
        $has_sponsors = true;
        // For editing, sponsor logos are optional - they can keep existing logos
        // Only validate sponsor names are provided
        foreach ($sponsor_names as $index => $sname) {
            $sname = trim($sname);
            if (!empty($sname)) {
                // Sponsor name is provided, that's enough for editing
                // Logo upload is optional since existing sponsors can keep their logos
            }
        }
    }

    if ($is_department_admin && $id) {
        $allowed = $conn->query("SELECT id FROM public_courses WHERE id = $id AND department_id = " . (int)$department_id);
        if (!$allowed || $allowed->num_rows === 0) {
            $id = 0;
            $message = 'You can only edit short courses in your department.';
            $message_type = 'error';
        }
    }

    if ($id && $name && $code && $duration && $department_id_input && (!$has_sponsors || $sponsors_valid)) {
        // Log for debugging
        error_log("Edit short course validation passed: ID=$id, name=$name, code=$code, duration=$duration, dept=$department_id_input");
        $checkTable = $conn->query("SHOW TABLES LIKE 'public_courses'");
        if ($checkTable && $checkTable->num_rows > 0) {
            // Handle banner upload if provided
            $banner_path = null;
            if ($banner && $banner['error'] !== UPLOAD_ERR_NO_FILE) {
                $bannerRes = upload_short_course_image($banner, 'short_courses');
                if ($bannerRes['ok']) {
                    $banner_path = $bannerRes['path'];
                } else {
                    $message = 'Banner upload failed: ' . $bannerRes['msg'];
                    $message_type = 'error';
                }
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
            $is_sponsored = $has_sponsors ? 1 : 0;

            // Build dynamic update query based on which columns exist
            $updates = ['slug = ?', 'title = ?', 'summary = ?', 'description = ?'];
            $params = [$slug, $name, $description, $description];
            $types = 'ssss';

            $editOptional = ['code', 'duration', 'department_id', 'is_paid', 'price', 'payment_methods', 'is_sponsored'];
            $editValues = [
                'code' => $code,
                'duration' => $duration,
                'department_id' => (int)$department_id_input,
                'is_paid' => $is_paid,
                'price' => $price,
                'payment_methods' => $methods_str,
                'is_sponsored' => $is_sponsored,
            ];
            $editTypes = [
                'code' => 's',
                'duration' => 's',
                'department_id' => 'i',
                'is_paid' => 'i',
                'price' => 'd',
                'payment_methods' => 's',
                'is_sponsored' => 'i',
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
                $stmt->close();
                
                // Handle sponsors: use UPDATE for existing, INSERT for new
                // First, get existing sponsors to track which ones are updated
                $existing_sponsors = [];
                $checkSponsors = $conn->query("SELECT id, sponsor_name, sponsor_details, sponsor_logo FROM course_sponsors WHERE course_id = $id");
                if ($checkSponsors) {
                    while ($row = $checkSponsors->fetch_assoc()) {
                        $existing_sponsors[] = $row;
                    }
                }
                
                $sponsor_upload_dir = __DIR__ . '/../../uploads/short_courses/sponsors/';
                if (!is_dir($sponsor_upload_dir)) mkdir($sponsor_upload_dir, 0755, true);
                
                $sponsor_count = 0;
                $matched_existing_ids = [];
                
                if ($has_sponsors) {
                    foreach ($sponsor_names as $index => $sname) {
                        $sname = trim($sname);
                        if (!empty($sname)) {
                            $sdetails = trim($sponsor_details[$index] ?? '');
                            $sponsor_id = !empty($sponsor_ids[$index]) ? (int)$sponsor_ids[$index] : null;
                            
                            // Handle sponsor logo upload
                            $slogo_path = null;
                            if (isset($sponsor_logos['name'][$index]) && !empty($sponsor_logos['name'][$index])) {
                                $logoFile = [
                                    'name' => $sponsor_logos['name'][$index],
                                    'tmp_name' => $sponsor_logos['tmp_name'][$index],
                                    'error' => $sponsor_logos['error'][$index],
                                    'size' => $sponsor_logos['size'][$index],
                                ];
                                $logoRes = upload_short_course_image($logoFile, 'short_courses/sponsors');
                                if ($logoRes['ok']) {
                                    $slogo_path = $logoRes['path'];
                                } else {
                                    $message = 'Sponsor logo upload failed for "' . $sname . '": ' . $logoRes['msg'];
                                    $message_type = 'error';
                                }
                            }
                            
                            if ($sponsor_id) {
                                // UPDATE existing sponsor by ID
                                // Get existing logo if no new logo uploaded
                                $existing_logo = '';
                                foreach ($existing_sponsors as $existing) {
                                    if ($existing['id'] == $sponsor_id) {
                                        $existing_logo = $existing['sponsor_logo'];
                                        break;
                                    }
                                }
                                
                                $logo_to_use = $slogo_path ? $slogo_path : $existing_logo;
                                $sponsor_stmt = $conn->prepare("UPDATE course_sponsors SET sponsor_name = ?, sponsor_details = ?, sponsor_logo = ? WHERE id = ?");
                                $sponsor_stmt->bind_param('sssi', $sname, $sdetails, $logo_to_use, $sponsor_id);
                                $sponsor_stmt->execute();
                                $sponsor_stmt->close();
                                $matched_existing_ids[] = $sponsor_id;
                                $sponsor_count++;
                            } else {
                                // INSERT new sponsor
                                $logo_to_use = $slogo_path ? $slogo_path : '';
                                $sponsor_stmt = $conn->prepare("INSERT INTO course_sponsors (course_id, sponsor_name, sponsor_details, sponsor_logo) VALUES (?, ?, ?, ?)");
                                $sponsor_stmt->bind_param('isss', $id, $sname, $sdetails, $logo_to_use);
                                $sponsor_stmt->execute();
                                $sponsor_stmt->close();
                                $sponsor_count++;
                            }
                        }
                    }
                    
                    // Delete sponsors that were not matched (removed by user)
                    foreach ($existing_sponsors as $existing) {
                        if (!in_array($existing['id'], $matched_existing_ids)) {
                            $conn->query("DELETE FROM course_sponsors WHERE id = " . (int)$existing['id']);
                        }
                    }
                } else {
                    // No sponsors in form, delete all existing sponsors
                    $conn->query("DELETE FROM course_sponsors WHERE course_id = $id");
                }
                
                if ($message_type === 'error') {
                    // Preserve the upload error already set (banner/sponsor logo).
                    $message = rtrim($message, '.') . '. The banner upload did not persist — please check the upload folder and retry.';
                } else {
                    $message = "Short course updated successfully!" . ($sponsor_count > 0 ? " Updated with $sponsor_count sponsor(s)." : "");
                    $message_type = 'success';
                }
            } else {
                $message = "Failed to update short course: " . $stmt->error;
                $message_type = 'error';
                $stmt->close();
            }
        } else {
            $message = "Public courses table does not exist.";
            $message_type = 'error';
        }
    } else {
        // Detailed error message for debugging
        $missing_fields = [];
        if (!$id) $missing_fields[] = "ID";
        if (!$name) $missing_fields[] = "Course Name";
        if (!$code) $missing_fields[] = "Course Code";
        if (!$duration) $missing_fields[] = "Duration";
        if (!$department_id_input) $missing_fields[] = "Department";
        if ($has_sponsors && !$sponsors_valid) $missing_fields[] = "Valid sponsor logos";
        
        $message = "Missing required fields: " . implode(', ', $missing_fields);
        $message_type = 'error';
        
        // Log for debugging
        error_log("Edit validation failed: ID=$id, name=$name, code=$code, duration=$duration, dept=$department_id_input, has_sponsors=$has_sponsors, sponsors_valid=$sponsors_valid");
    }
}

// Delete Short Course
if ($action === 'delete_short_course') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        // Delete tutor assignments
        $conn->query("DELETE FROM short_course_tutors WHERE short_course_id = $id");
        // Delete sponsors (foreign key will cascade, but explicit delete is safe)
        $conn->query("DELETE FROM course_sponsors WHERE course_id = $id");
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
                // A course-level assignment holds exactly one tutor. Deactivate any
                // other tutor already on the course so the newly assigned tutor is
                // the single active ("primary") course tutor.
                $deactivate = $conn->prepare("UPDATE short_course_tutors SET is_active = 0 WHERE short_course_id = ?");
                $deactivate->bind_param('i', $short_course_id);
                $deactivate->execute();
                $deactivate->close();

                $check = $conn->query("SELECT id FROM short_course_tutors WHERE short_course_id = $short_course_id AND lecturer_id = $lecturer_id");
                if ($check && $check->num_rows === 0) {
                    $stmt = $conn->prepare("INSERT INTO short_course_tutors (short_course_id, lecturer_id, assigned_by, is_active) VALUES (?, ?, ?, 1)");
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
                    // Reassign: bring the existing row back as the primary tutor.
                    $reactivate = $conn->prepare("UPDATE short_course_tutors SET is_active = 1, assigned_by = ? WHERE short_course_id = ? AND lecturer_id = ?");
                    $reactivate->bind_param('iii', $assigned_by, $short_course_id, $lecturer_id);
                    $reactivate->execute();
                    $reactivate->close();
                    $message = "Tutor reassigned as the course tutor.";
                    $message_type = 'success';
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

// Assign Tutor to a specific Module (module-level permission)
if ($action === 'assign_tutor_to_module') {
    header('Content-Type: application/json');
    $tutor_id  = (int)($_POST['tutor_id']  ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    $module_id = (int)($_POST['module_id'] ?? 0);

    if (!$tutor_id || !$course_id || !$module_id) {
        echo json_encode(['success' => false, 'message' => 'Course, module and tutor are required']);
        exit;
    }

    // Scope: module must belong to the course.
    $stmt = $conn->prepare("SELECT id FROM public_course_modules WHERE id = ? AND course_id = ? LIMIT 1");
    $stmt->bind_param('ii', $module_id, $course_id);
    $stmt->execute();
    $valid = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$valid) {
        echo json_encode(['success' => false, 'message' => 'That module is not part of the selected course']);
        exit;
    }

    // A department admin can only assign tutors to their own department's courses.
    if ($is_department_admin) {
        $stmt = $conn->prepare("SELECT id FROM public_courses WHERE id = ? AND department_id = ? LIMIT 1");
        $stmt->bind_param('ii', $course_id, $department_id);
        $stmt->execute();
        $validDept = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$validDept) {
            echo json_encode(['success' => false, 'message' => 'You can only assign tutors in your department']);
            exit;
        }
    }

    $checkTable = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Module permissions table missing. Run migrations.']);
        exit;
    }

    $assigned_by = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare("
        INSERT INTO tutor_module_permissions (tutor_id, module_id, can_edit, can_teach, assigned_by)
        VALUES (?, ?, 1, 1, ?)
        ON DUPLICATE KEY UPDATE can_edit = 1, can_teach = 1, assigned_by = VALUES(assigned_by)
    ");
    $stmt->bind_param('iii', $tutor_id, $module_id, $assigned_by);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Tutor assigned to module']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to assign tutor: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Assign Tutor to a specific Lesson (lesson-level permission)
if ($action === 'assign_tutor_to_lesson') {
    header('Content-Type: application/json');
    $tutor_id  = (int)($_POST['tutor_id']  ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    $lesson_id = (int)($_POST['lesson_id'] ?? 0);

    if (!$tutor_id || !$course_id || !$lesson_id) {
        echo json_encode(['success' => false, 'message' => 'Course, lesson and tutor are required']);
        exit;
    }

    // Scope: lesson must belong to the course (via its module).
    $stmt = $conn->prepare("
        SELECT l.id FROM public_course_lessons l
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE l.id = ? AND m.course_id = ? LIMIT 1
    ");
    $stmt->bind_param('ii', $lesson_id, $course_id);
    $stmt->execute();
    $valid = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$valid) {
        echo json_encode(['success' => false, 'message' => 'That lesson is not part of the selected course']);
        exit;
    }

    if ($is_department_admin) {
        $stmt = $conn->prepare("SELECT id FROM public_courses WHERE id = ? AND department_id = ? LIMIT 1");
        $stmt->bind_param('ii', $course_id, $department_id);
        $stmt->execute();
        $validDept = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$validDept) {
            echo json_encode(['success' => false, 'message' => 'You can only assign tutors in your department']);
            exit;
        }
    }

    // Create the lesson permissions table on demand so the feature works even
    // before the dedicated migration has been run.
    $checkTable = $conn->query("SHOW TABLES LIKE 'tutor_lesson_permissions'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS tutor_lesson_permissions (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tutor_id INT NOT NULL,
                lesson_id INT NOT NULL,
                can_edit TINYINT(1) DEFAULT 1,
                can_teach TINYINT(1) DEFAULT 1,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                assigned_by INT NULL,
                UNIQUE KEY uniq_tutor_lesson (tutor_id, lesson_id),
                KEY idx_tlp_tutor (tutor_id),
                KEY idx_tlp_lesson (lesson_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        if ($conn->error) {
            echo json_encode(['success' => false, 'message' => 'Could not create lesson permissions table: ' . $conn->error]);
            exit;
        }
    }

    $assigned_by = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare("
        INSERT INTO tutor_lesson_permissions (tutor_id, lesson_id, can_edit, can_teach, assigned_by)
        VALUES (?, ?, 1, 1, ?)
        ON DUPLICATE KEY UPDATE can_edit = 1, can_teach = 1, assigned_by = VALUES(assigned_by)
    ");
    $stmt->bind_param('iii', $tutor_id, $lesson_id, $assigned_by);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Tutor assigned to lesson']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to assign tutor: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
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
    $scOptional = ['code', 'duration', 'department_id', 'is_paid', 'price', 'payment_methods', 'is_sponsored'];
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
        SELECT sct.id, sct.short_course_id, sct.lecturer_id, sct.is_active, l.name as lecturer_name, pc.title as course_name
        FROM short_course_tutors sct
        JOIN lecturers l ON sct.lecturer_id = l.id
        JOIN public_courses pc ON sct.short_course_id = pc.id
        " . ($is_department_admin ? 'WHERE pc.department_id = ' . (int)$department_id : '') . "
        ORDER BY pc.title, l.name
    ");
}

// Module/lesson-level tutor assignments, for the listing under Assign Tutors.
$module_assignments = false;
$lesson_assignments = false;
$checkTmp = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
if ($checkTmp && $checkTmp->num_rows > 0) {
    $module_assignments = $conn->query("
        SELECT tmp.tutor_id, tmp.can_edit, tmp.can_teach,
               l.name AS tutor_name, m.title AS module_title, pc.title AS course_name
        FROM tutor_module_permissions tmp
        JOIN lecturers l ON l.id = tmp.tutor_id
        JOIN public_course_modules m ON m.id = tmp.module_id
        JOIN public_courses pc ON pc.id = m.course_id
        " . ($is_department_admin ? 'WHERE pc.department_id = ' . (int)$department_id : '') . "
        ORDER BY pc.title, m.position, l.name
    ");
}
$checkTlp = $conn->query("SHOW TABLES LIKE 'tutor_lesson_permissions'");
if ($checkTlp && $checkTlp->num_rows > 0) {
    $lesson_assignments = $conn->query("
        SELECT tlp.tutor_id, tlp.can_edit, tlp.can_teach,
               l.name AS tutor_name, ls.title AS lesson_title, m.title AS module_title, pc.title AS course_name
        FROM tutor_lesson_permissions tlp
        JOIN lecturers l ON l.id = tlp.tutor_id
        JOIN public_course_lessons ls ON ls.id = tlp.lesson_id
        JOIN public_course_modules m ON m.id = ls.module_id
        JOIN public_courses pc ON pc.id = m.course_id
        " . ($is_department_admin ? 'WHERE pc.department_id = ' . (int)$department_id : '') . "
        ORDER BY pc.title, m.position, ls.position, l.name
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ TOP NAV Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ LAYOUT Ã¢â€â‚¬Ã¢â€â‚¬ */
        .layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ SIDEBAR Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ MAIN Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ MESSAGE Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ STATS Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ PANELS Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ CARDS Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ FORMS Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ BUTTONS Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ TABLE Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ PRICING TOGGLE Ã¢â€â‚¬Ã¢â€â‚¬ */
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ RESPONSIVE Ã¢â€â‚¬Ã¢â€â‚¬ */
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
            <a class="nav-item" href="short_courses_analytics.php">
                <i class="fas fa-chart-line"></i> Short Courses Analytics
            </a>
            <a class="nav-item" href="../../modules/live-engagement/index.php?page=dashboard&amp;create=1&amp;type=presentation">
                <i class="fas fa-person-chalkboard"></i> Live Presentations
            </a>

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
                                <thead><tr><th>Name</th><th>Email</th></tr></thead>
                                <tbody>
                                    <?php if ($lecturers && $lecturers->num_rows > 0): ?>
                                        <?php while ($l = $lecturers->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($l['name']) ?></td>
                                                <td><?= htmlspecialchars($l['email']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="empty-state"><i class="fas fa-chalkboard-teacher"></i><p>No lecturers added yet</p></td></tr>
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
                                                <td><?= !empty($tutors) ? implode(', ', array_map('htmlspecialchars', $tutors)) : 'Ã¢â‚¬â€' ?></td>
                                                <td><?= $sc['banner'] ? '<i class="fas fa-image"></i>' : 'Ã¢â‚¬â€' ?></td>
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
                                                <td><?= htmlspecialchars($l['phone'] ?? 'Ã¢â‚¬â€') ?></td>
                                                <td><?= htmlspecialchars($l['staff_id'] ?? 'Ã¢â‚¬â€') ?></td>
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
                                                <td><?= htmlspecialchars($c['code'] ?? 'Ã¢â‚¬â€') ?></td>
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
                                <label>Course Sponsors</label>
                                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:8px;">Add one or more sponsors for this course (optional)</p>
                                <div id="sponsors_container">
                                    <!-- Sponsor fields will be added dynamically -->
                                </div>
                                <button type="button" class="btn btn-sm" onclick="addSponsorField()" style="margin-top:10px; background:var(--surface2); border:1px solid var(--border);">
                                    <i class="fas fa-plus"></i> Add Sponsor
                                </button>
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
                                                <td><?= !empty($tutors) ? implode(', ', array_map('htmlspecialchars', $tutors)) : 'Ã¢â‚¬â€' ?></td>
                                                <td><?= $sc['banner'] ? '<i class="fas fa-image"></i>' : 'Ã¢â‚¬â€' ?></td>
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
                                                <td><?= htmlspecialchars($t['phone'] ?? 'Ã¢â‚¬â€') ?></td>
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

                <?php
                // Lecturers available for module/lesson-level assignment.
                $assign_tutor_list = false;
                $checkLec = $conn->query("SHOW TABLES LIKE 'lecturers'");
                if ($checkLec && $checkLec->num_rows > 0) {
                    $lecSql = "SELECT id, name FROM lecturers";
                    if ($is_department_admin) $lecSql .= ' WHERE department_id = ' . (int)$department_id;
                    $lecSql .= ' ORDER BY name';
                    $assign_tutor_list = $conn->query($lecSql);
                }
                ?>
                <div class="card" style="margin-top:18px;">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-layer-group"></i></div>
                        <h3>Assign Tutor to a Module or Lesson</h3>
                    </div>
                    <div class="card-body">
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:14px;">
                            Give a tutor the right to <strong>edit</strong> a specific module or lesson. They can
                            open the course but only edit what they are assigned. The course-level tutor (one per
                            course, set above) may edit the whole course.
                        </p>
                        <div class="form-grid" id="assignModuleLessonForm">
                            <div class="form-group">
                                <label>Short Course *</label>
                                <select name="course_id" id="amlCourseId" required onchange="amlLoadModules()">
                                    <option value="">-- Select Course --</option>
                                    <?php if ($short_courses): $short_courses->data_seek(0); while ($sc = $short_courses->fetch_assoc()): ?>
                                        <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
                                    <?php endwhile; else: ?>
                                        <option value="" disabled>No courses available</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Tutor *</label>
                                <select name="tutor_id" id="amlTutorId" required>
                                    <option value="">-- Select Tutor --</option>
                                    <?php if ($assign_tutor_list): while ($at = $assign_tutor_list->fetch_assoc()): ?>
                                        <option value="<?= $at['id'] ?>"><?= htmlspecialchars($at['name']) ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Module *</label>
                                <select name="module_id" id="amlModuleId" required onchange="amlLoadLessons()">
                                    <option value="">-- Select Module --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Lesson (optional)</label>
                                <select name="lesson_id" id="amlLessonId">
                                    <option value="">Whole module (not a single lesson)</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:6px;">
                            <button type="button" class="btn btn-primary" id="amlSubmitBtn" disabled onclick="amlSubmit()">
                                <i class="fas fa-user-plus"></i> Assign Tutor
                            </button>
                        </div>
                        <div id="amlResult" style="margin-top:12px; display:none; font-size:0.9rem;"></div>
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
                                            <td><?= htmlspecialchars($t['lecturer_name']) ?>
                                                <?php if (!empty($t['is_active'])): ?>
                                                    <span style="display:inline-block; margin-left:6px; font-size:0.7rem; background:var(--success,#16a34a); color:#fff; padding:2px 8px; border-radius:10px;">Primary</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="openTutorPermissionsModal(<?= $t['id'] ?>, <?= $t['short_course_id'] ?>, '<?= htmlspecialchars($t['lecturer_name']) ?>')">
                                                    <i class="fas fa-key"></i> Permissions
                                                </button>
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

                <?php if (($module_assignments && $module_assignments->num_rows > 0) || ($lesson_assignments && $lesson_assignments->num_rows > 0)): ?>
                <div class="card" style="margin-top:18px;">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-key"></i></div>
                        <h3>Module / Lesson Assignments</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($module_assignments && $module_assignments->num_rows > 0): ?>
                        <div style="margin-bottom:14px;">
                            <h4 style="font-size:0.9rem; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.03em;">Module level</h4>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Course</th><th>Module</th><th>Tutor</th><th>Edit</th><th>Teach</th></tr></thead>
                                    <tbody>
                                        <?php while ($ma = $module_assignments->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($ma['course_name']) ?></td>
                                                <td><?= htmlspecialchars($ma['module_title']) ?></td>
                                                <td><?= htmlspecialchars($ma['tutor_name']) ?></td>
                                                <td><?= $ma['can_edit'] ? 'Ã¢Å“â€' : 'Ã¢â‚¬â€' ?></td>
                                                <td><?= $ma['can_teach'] ? 'Ã¢Å“â€' : 'Ã¢â‚¬â€' ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($lesson_assignments && $lesson_assignments->num_rows > 0): ?>
                        <div>
                            <h4 style="font-size:0.9rem; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.03em;">Lesson level</h4>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Course</th><th>Module</th><th>Lesson</th><th>Tutor</th><th>Edit</th><th>Teach</th></tr></thead>
                                    <tbody>
                                        <?php while ($la = $lesson_assignments->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($la['course_name']) ?></td>
                                                <td><?= htmlspecialchars($la['module_title']) ?></td>
                                                <td><?= htmlspecialchars($la['lesson_title']) ?></td>
                                                <td><?= htmlspecialchars($la['tutor_name']) ?></td>
                                                <td><?= $la['can_edit'] ? 'Ã¢Å“â€' : 'Ã¢â‚¬â€' ?></td>
                                                <td><?= $la['can_teach'] ? 'Ã¢Å“â€' : 'Ã¢â‚¬â€' ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
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
                        <?php if ($is_department_admin): ?>
                        <input type="hidden" name="department_id" id="edit_department_id" value="<?= $department_id ?>">
                        <input type="text" value="<?= htmlspecialchars($dept_name) ?>" disabled style="background:var(--surface2); width:100%; padding:8px; border:1px solid var(--border); border-radius:4px;">
                        <?php else: ?>
                        <select name="department_id" id="edit_department_id" required>
                            <option value="">-- Select --</option>
                            <?php if ($all_departments): $all_departments->data_seek(0); while ($dept = $all_departments->fetch_assoc()): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Banner Image</label>
                    <input type="file" name="banner" id="edit_banner_input" accept="image/*">
                    <div id="edit_banner_preview_wrap" style="margin-top:8px; display:none;">
                        <img id="edit_banner_preview" alt="Current banner" style="max-width:100%; max-height:140px; border-radius:6px; border:1px solid var(--border); display:block; object-fit:cover;">
                    </div>
                    <p style="font-size:0.75rem; color:var(--text-dim); margin-top:4px;">Leave empty to keep the current banner.</p>
                </div>
                <div class="form-group">
                    <label>Course Sponsors</label>
                    <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:8px;">Add one or more sponsors for this course (optional)</p>
                    <div id="edit_sponsors_container">
                        <!-- Sponsor fields will be added dynamically -->
                    </div>
                    <button type="button" class="btn btn-sm" onclick="addEditSponsorField()" style="margin-top:10px; background:var(--surface2); border:1px solid var(--border);">
                        <i class="fas fa-plus"></i> Add Sponsor
                    </button>
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
        console.log('Opening edit modal with data:', sc);
        
        document.getElementById('edit_id').value = sc.id || '';
        document.getElementById('edit_name').value = sc.name || sc.title || '';
        document.getElementById('edit_code').value = sc.code || '';
        document.getElementById('edit_duration').value = sc.duration || '';
        document.getElementById('edit_description').value = sc.description || sc.summary || '';
        
        // Handle department_id - it might be a select or hidden input
        const deptField = document.getElementById('edit_department_id');
        if (deptField) {
            deptField.value = sc.department_id || '';
        }
        
        document.getElementById('edit_price').value = sc.price || '';

        // Banner preview — show current cover image
        const bannerSrc = sc.banner || sc.cover_image || '';
        const bWrap = document.getElementById('edit_banner_preview_wrap');
        const bImg = document.getElementById('edit_banner_preview');
        if (bannerSrc) {
            bImg.src = bannerSrc;
            bWrap.style.display = 'block';
        } else {
            bImg.removeAttribute('src');
            bWrap.style.display = 'none';
        }

        // Clear existing sponsor fields
        const container = document.getElementById('edit_sponsors_container');
        container.innerHTML = '';
        editSponsorIndex = 0;

        // Load sponsors for this course
        if (sc.id) {
            loadCourseSponsors(sc.id);
        }

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

    // Live preview when a new banner file is chosen
    function initBannerPreview() {
        const input = document.getElementById('edit_banner_input');
        if (!input) return;
        input.onchange = function () {
            const wrap = document.getElementById('edit_banner_preview_wrap');
            const img = document.getElementById('edit_banner_preview');
            if (this.files && this.files[0]) {
                img.src = URL.createObjectURL(this.files[0]);
                wrap.style.display = 'block';
            }
        };
    }

    async function loadCourseSponsors(courseId) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_course_sponsors');
            formData.append('course_id', courseId);
            
            const response = await fetch('', { method: 'POST', body: formData });
            const text = await response.text();
            
            const jsonMatch = text.match(/\{.*\}/s);
            if (jsonMatch) {
                const data = JSON.parse(jsonMatch[0]);
                if (data.sponsors && Array.isArray(data.sponsors)) {
                    data.sponsors.forEach(sponsor => {
                        addEditSponsorFieldWithValue(sponsor.sponsor_name, sponsor.sponsor_details, sponsor.sponsor_logo, sponsor.id);
                    });
                }
            }
        } catch (error) {
            console.error('Failed to load sponsors:', error);
        }
    }

    function addEditSponsorFieldWithValue(name, details, logoPath, sponsorId = null) {
        const container = document.getElementById('edit_sponsors_container');
        const sponsorDiv = document.createElement('div');
        sponsorDiv.className = 'edit-sponsor-item';
        sponsorDiv.style.cssText = 'background:var(--surface2); padding:12px; border-radius:8px; margin-bottom:10px; border:1px solid var(--border);';
        sponsorDiv.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="font-size:13px;">Sponsor #${editSponsorIndex + 1}</strong>
                <button type="button" onclick="removeEditSponsorField(this)" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:14px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="hidden" name="sponsor_id[]" value="${sponsorId || ''}">
            <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:12px;">Sponsor Name *</label>
                <input type="text" name="sponsor_name[]" value="${name || ''}" style="padding:8px; font-size:13px;">
            </div>
            <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:12px;">Sponsor Details (optional)</label>
                <textarea name="sponsor_details[]" rows="2" placeholder="Describe the sponsor or sponsorship arrangement" style="padding:8px; font-size:13px;">${details || ''}</textarea>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px;">Sponsor Logo</label>
                <input type="file" name="sponsor_logo[]" accept="image/*" style="padding:8px; font-size:13px;">
                ${logoPath ? `<div style="margin-top:4px;">
                    <img src="${logoPath}" alt="sponsor logo" style="max-width:120px; max-height:60px; border-radius:4px; border:1px solid var(--border); object-fit:contain;">
                </div>` : ''}
                <p style="font-size:0.7rem; color:var(--text-dim); margin-top:2px;">Leave empty to keep existing logo</p>
            </div>
        `;
        container.appendChild(sponsorDiv);
        editSponsorIndex++;
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

    initBannerPreview();

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

    let sponsorIndex = 0;
    let editSponsorIndex = 0;

    function addSponsorField() {
        const container = document.getElementById('sponsors_container');
        const sponsorDiv = document.createElement('div');
        sponsorDiv.className = 'sponsor-item';
        sponsorDiv.style.cssText = 'background:var(--surface2); padding:12px; border-radius:8px; margin-bottom:10px; border:1px solid var(--border);';
        sponsorDiv.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="font-size:13px;">Sponsor #${sponsorIndex + 1}</strong>
                <button type="button" onclick="removeSponsorField(this)" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:14px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:12px;">Sponsor Name *</label>
                <input type="text" name="sponsor_name[]" required style="padding:8px; font-size:13px;">
            </div>
            <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:12px;">Sponsor Details (optional)</label>
                <textarea name="sponsor_details[]" rows="2" placeholder="Describe the sponsor or sponsorship arrangement" style="padding:8px; font-size:13px;"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px;">Sponsor Logo *</label>
                <input type="file" name="sponsor_logo[]" accept="image/*" required style="padding:8px; font-size:13px;">
            </div>
        `;
        container.appendChild(sponsorDiv);
        sponsorIndex++;
    }

    function removeSponsorField(btn) {
        const sponsorDiv = btn.closest('.sponsor-item');
        sponsorDiv.remove();
    }

    function addEditSponsorField() {
        const container = document.getElementById('edit_sponsors_container');
        const sponsorDiv = document.createElement('div');
        sponsorDiv.className = 'edit-sponsor-item';
        sponsorDiv.style.cssText = 'background:var(--surface2); padding:12px; border-radius:8px; margin-bottom:10px; border:1px solid var(--border);';
        sponsorDiv.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="font-size:13px;">Sponsor #${editSponsorIndex + 1}</strong>
                <button type="button" onclick="removeEditSponsorField(this)" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:14px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="hidden" name="sponsor_id[]" value="">
            <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:12px;">Sponsor Name *</label>
                <input type="text" name="sponsor_name[]" style="padding:8px; font-size:13px;">
            </div>
            <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:12px;">Sponsor Details (optional)</label>
                <textarea name="sponsor_details[]" rows="2" placeholder="Describe the sponsor or sponsorship arrangement" style="padding:8px; font-size:13px;"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px;">Sponsor Logo</label>
                <input type="file" name="sponsor_logo[]" accept="image/*" style="padding:8px; font-size:13px;">
                <p style="font-size:0.7rem; color:var(--text-dim); margin-top:2px;">Leave empty to keep existing logo</p>
            </div>
        `;
        container.appendChild(sponsorDiv);
        editSponsorIndex++;
    }

    function removeEditSponsorField(btn) {
        const sponsorDiv = btn.closest('.edit-sponsor-item');
        sponsorDiv.remove();
    }

    function toggleEditSponsorship() {
        // No longer needed with dynamic sponsor fields
    }

    function toggleSponsorship() {
        // No longer needed with dynamic sponsor fields
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

    // Assign Tutor to a Module / Lesson
    function amlLoadModules() {
        const courseId = document.getElementById('amlCourseId').value;
        const moduleSel = document.getElementById('amlModuleId');
        const lessonSel = document.getElementById('amlLessonId');
        moduleSel.innerHTML = '<option value="">-- Select Module --</option>';
        lessonSel.innerHTML = '<option value="">Whole module (not a single lesson)</option>';
        if (!courseId) { amlEnableSubmit(); return; }
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_course_modules&course_id=' + courseId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.modules) {
                data.modules.forEach(m => {
                    const o = document.createElement('option');
                    o.value = m.id;
                    o.textContent = m.title;
                    moduleSel.appendChild(o);
                });
            }
            amlEnableSubmit();
        })
        .catch(() => amlEnableSubmit());
    }

    function amlLoadLessons() {
        const moduleId = document.getElementById('amlModuleId').value;
        const lessonSel = document.getElementById('amlLessonId');
        lessonSel.innerHTML = '<option value="">Whole module (not a single lesson)</option>';
        if (!moduleId) { amlEnableSubmit(); return; }
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_module_lessons&module_id=' + moduleId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.lessons) {
                data.lessons.forEach(l => {
                    const o = document.createElement('option');
                    o.value = l.id;
                    o.textContent = l.title;
                    lessonSel.appendChild(o);
                });
            }
            amlEnableSubmit();
        })
        .catch(() => amlEnableSubmit());
    }

    function amlEnableSubmit() {
        const courseId = document.getElementById('amlCourseId').value;
        const tutorId = document.getElementById('amlTutorId').value;
        const moduleId = document.getElementById('amlModuleId').value;
        document.getElementById('amlSubmitBtn').disabled = !(courseId && tutorId && moduleId);
    }

    document.addEventListener('change', function (e) {
        if (e.target.id === 'amlCourseId' || e.target.id === 'amlTutorId' || e.target.id === 'amlModuleId') {
            amlEnableSubmit();
        }
    });

    function escapeHtml(text) {
        const span = document.createElement('span');
        span.textContent = text == null ? '' : String(text);
        return span.innerHTML;
    }

    function amlSubmit() {
        const courseId = document.getElementById('amlCourseId').value;
        const tutorId = document.getElementById('amlTutorId').value;
        const moduleId = document.getElementById('amlModuleId').value;
        const lessonId = document.getElementById('amlLessonId').value;
        const resultDiv = document.getElementById('amlResult');
        if (!courseId || !tutorId || !moduleId) {
            alert('Select a course, tutor and module.');
            return;
        }
        const isLesson = lessonId !== '';
        const action = isLesson ? 'assign_tutor_to_lesson' : 'assign_tutor_to_module';
        const params = new URLSearchParams();
        params.append('action', action);
        params.append('tutor_id', tutorId);
        params.append('course_id', courseId);
        params.append('module_id', moduleId);
        if (isLesson) params.append('lesson_id', lessonId);

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(r => r.json())
        .then(data => {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = data.success
                ? '<span style="color:var(--success,#16a34a);"><i class="fas fa-check-circle"></i> ' + escapeHtml(data.message) + '</span>'
                : '<span style="color:#dc2626;"><i class="fas fa-exclamation-circle"></i> ' + escapeHtml(data.message) + '</span>';
            if (data.success) setTimeout(function () { location.reload(); }, 900);
        })
        .catch(() => {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color:#dc2626;">Network error. Please try again.</span>';
        });
    }

    // Tutor Permissions Modal
    function openTutorPermissionsModal(tutorId, courseId, tutorName) {
        const modal = document.getElementById('tutorPermissionsModal');
        if (!modal) return;
        
        document.getElementById('perm_tutor_id').value = tutorId;
        document.getElementById('perm_course_id').value = courseId;
        document.getElementById('perm_tutor_name').textContent = tutorName;
        
        // Load course modules
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_course_modules&course_id=' + courseId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderModulePermissions(data.modules);
                // Load existing permissions
                loadTutorPermissions(tutorId, courseId);
            } else {
                alert('Failed to load modules: ' + data.message);
            }
        })
        .catch(e => alert('Error loading modules: ' + e));
        
        modal.style.display = 'block';
    }

    function closeTutorPermissionsModal() {
        document.getElementById('tutorPermissionsModal').style.display = 'none';
    }

    function renderModulePermissions(modules) {
        const container = document.getElementById('module_permissions_container');
        if (!container) return;
        
        if (modules.length === 0) {
            container.innerHTML = '<p style="color:var(--text-muted);">No modules found in this course.</p>';
            return;
        }
        
        container.innerHTML = modules.map(m => `
            <div class="module-permission-item" style="background:var(--surface2); padding:16px; border-radius:8px; margin-bottom:12px; border:1px solid var(--border);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <strong style="font-size:14px;">${m.title}</strong>
                    ${m.start_date || m.end_date ? `<small style="color:var(--text-muted);">${m.start_date || 'Ã¢â‚¬â€'} to ${m.end_date || 'Ã¢â‚¬â€'}</small>` : ''}
                </div>
                <div style="display:flex; gap:20px; align-items:center;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="perm_can_edit_${m.id}" value="1" onchange="updatePermission(${m.id}, 'can_edit', this.checked)">
                        Can Edit
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="perm_can_teach_${m.id}" value="1" onchange="updatePermission(${m.id}, 'can_teach', this.checked)">
                        Can Teach
                    </label>
                </div>
            </div>
        `).join('');
    }

    function loadTutorPermissions(tutorId, courseId) {
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_tutor_module_permissions&tutor_id=' + tutorId + '&course_id=' + courseId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Object.keys(data.permissions).forEach(moduleId => {
                    const perm = data.permissions[moduleId];
                    const editCheckbox = document.querySelector(`input[name="perm_can_edit_${moduleId}"]`);
                    const teachCheckbox = document.querySelector(`input[name="perm_can_teach_${moduleId}"]`);
                    
                    if (editCheckbox) editCheckbox.checked = perm.can_edit;
                    if (teachCheckbox) teachCheckbox.checked = perm.can_teach;
                });
            }
        })
        .catch(e => console.error('Error loading permissions:', e));
    }

    function updatePermission(moduleId, type, value) {
        // Store in a data attribute for saving later
        const container = document.getElementById('module_permissions_container');
        if (!container.dataset.permissions) {
            container.dataset.permissions = '{}';
        }
        
        const permissions = JSON.parse(container.dataset.permissions);
        if (!permissions[moduleId]) {
            permissions[moduleId] = { can_edit: false, can_teach: false };
        }
        permissions[moduleId][type] = value;
        container.dataset.permissions = JSON.stringify(permissions);
    }

    function saveTutorPermissions() {
        const container = document.getElementById('module_permissions_container');
        const permissions = container.dataset.permissions ? JSON.parse(container.dataset.permissions) : {};
        const tutorId = document.getElementById('perm_tutor_id').value;
        const courseId = document.getElementById('perm_course_id').value;
        
        const formData = new URLSearchParams();
        formData.append('action', 'save_tutor_module_permissions');
        formData.append('tutor_id', tutorId);
        formData.append('course_id', courseId);
        formData.append('permissions', JSON.stringify(permissions));
        
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Permissions saved successfully!');
                closeTutorPermissionsModal();
            } else {
                alert('Failed to save permissions: ' + data.message);
            }
        })
        .catch(e => alert('Error saving permissions: ' + e));
    }
    </script>

<!-- Tutor Permissions Modal -->
<div id="tutorPermissionsModal" class="modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); overflow-y:auto;">
    <div style="background:var(--surface); max-width:700px; margin:5% auto; padding:28px 32px; border-radius:var(--radius); box-shadow:var(--shadow-lg); position:relative;">
        <button type="button" onclick="closeTutorPermissionsModal()" style="position:absolute; top:16px; right:16px; width:32px; height:32px; border-radius:50%; background:var(--surface2); border:1px solid var(--border); cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:14px;">&times;</button>
        <h3 style="font-size:1.1rem; font-weight:700; margin-bottom:20px; color:var(--text); display:flex; align-items:center; gap:10px;">
            <i class="fas fa-key"></i> Module Permissions
        </h3>
        <p style="margin-bottom:20px; color:var(--text-muted); font-size:0.9rem;">
            Manage which modules <strong id="perm_tutor_name"></strong> can edit and teach.
        </p>
        <input type="hidden" id="perm_tutor_id" value="">
        <input type="hidden" id="perm_course_id" value="">
        
        <div id="module_permissions_container" style="margin-bottom:20px;">
            <div style="text-align:center; padding:20px; color:var(--text-muted);">
                <i class="fas fa-spinner fa-spin"></i> Loading modules...
            </div>
        </div>
        
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
            <button type="button" onclick="closeTutorPermissionsModal()" class="btn btn-secondary">Cancel</button>
            <button type="button" onclick="saveTutorPermissions()" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Permissions
            </button>
        </div>
    </div>
</div>
</body>
</html>
