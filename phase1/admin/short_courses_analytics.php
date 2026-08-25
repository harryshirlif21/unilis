<?php
/**
 * Short Courses Analytics Page
 * UNILIS Academic Foundation Expansion
 * Consolidated view for course management, student analytics, revenue, tutors, and sponsors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../config/db.php';

// Simple auth check - only allow admin and department_admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: ../../login.php");
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['admin', 'department_admin'])) {
    header("Location: ../../login.php");
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Admin';
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

// Handle actions
$action = $_POST['action'] ?? '';

// Toggle publish status
if ($action === 'toggle_publish') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    if ($course_id) {
        // Verify department access for department_admin
        if ($is_department_admin) {
            $check = $conn->prepare("SELECT id FROM public_courses WHERE id = ? AND department_id = ?");
            $check->bind_param('ii', $course_id, $department_id);
            $check->execute();
            if (!$check->get_result()->fetch_row()) {
                $message = 'You can only toggle courses in your department.';
                $message_type = 'error';
                $check->close();
            } else {
                $check->close();
                $stmt = $conn->prepare("UPDATE public_courses SET is_published = NOT is_published WHERE id = ?");
                $stmt->bind_param('i', $course_id);
                $stmt->execute();
                $message = 'Course publish status updated.';
                $message_type = 'success';
                $stmt->close();
            }
        } else {
            // Global admin can toggle any course
            $stmt = $conn->prepare("UPDATE public_courses SET is_published = NOT is_published WHERE id = ?");
            $stmt->bind_param('i', $course_id);
            $stmt->execute();
            $message = 'Course publish status updated.';
            $message_type = 'success';
            $stmt->close();
        }
    }
}

// Delete course
if ($action === 'delete_course') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    if ($course_id) {
        // Verify department access for department_admin
        if ($is_department_admin) {
            $check = $conn->prepare("SELECT id FROM public_courses WHERE id = ? AND department_id = ?");
            $check->bind_param('ii', $course_id, $department_id);
            $check->execute();
            if (!$check->get_result()->fetch_row()) {
                $message = 'You can only delete courses in your department.';
                $message_type = 'error';
                $check->close();
            } else {
                $check->close();
                // Delete course (cascade will handle related records)
                $stmt = $conn->prepare("DELETE FROM public_courses WHERE id = ?");
                $stmt->bind_param('i', $course_id);
                $stmt->execute();
                $message = 'Course deleted successfully.';
                $message_type = 'success';
                $stmt->close();
            }
        } else {
            // Global admin can delete any course
            $stmt = $conn->prepare("DELETE FROM public_courses WHERE id = ?");
            $stmt->bind_param('i', $course_id);
            $stmt->execute();
            $message = 'Course deleted successfully.';
            $message_type = 'success';
            $stmt->close();
        }
    }
}

// AJAX: Update course field
if ($action === 'update_course_field') {
    header('Content-Type: application/json');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    if (!$course_id || !$field) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    // Validate field name - expanded to include all editable fields
    $allowed_fields = ['title', 'code', 'summary', 'duration', 'price', 'pass_mark', 'estimated_hours', 'level', 'payment_methods', 'outline'];
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['success' => false, 'message' => 'Invalid field']);
        exit;
    }

    // Verify department access for department_admin
    if ($is_department_admin) {
        $check = $conn->prepare("SELECT id FROM public_courses WHERE id = ? AND department_id = ?");
        $check->bind_param('ii', $course_id, $department_id);
        $check->execute();
        if (!$check->get_result()->fetch_row()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            $check->close();
            exit;
        }
        $check->close();
    }

    // Build update query based on field type
    $sql = "UPDATE public_courses SET $field = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($field === 'price' || $field === 'pass_mark' || $field === 'estimated_hours') {
        $stmt->bind_param('di', $value, $course_id);
    } else {
        $stmt->bind_param('si', $value, $course_id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Field updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Build query with filters
$where_conditions = ["1=1"];
$params = [];
$types = '';

// Department filter for department_admin
if ($is_department_admin) {
    $where_conditions[] = "c.department_id = ?";
    $params[] = $department_id;
    $types .= 'i';
}

// Search filter
$search = trim($_GET['search'] ?? '');
if ($search) {
    $where_conditions[] = "(c.title LIKE ? OR c.code LIKE ? OR c.summary LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Publish status filter
$publish_filter = $_GET['publish'] ?? '';
if ($publish_filter !== '') {
    $where_conditions[] = "c.is_published = ?";
    $params[] = (int)$publish_filter;
    $types .= 'i';
}

// Paid/free filter
$paid_filter = $_GET['paid'] ?? '';
if ($paid_filter !== '') {
    $checkCol = $conn->query("SHOW COLUMNS FROM public_courses LIKE 'is_paid'");
    if ($checkCol && $checkCol->num_rows > 0) {
        $where_conditions[] = "c.is_paid = ?";
        $params[] = (int)$paid_filter;
        $types .= 'i';
    }
    $checkCol->free();
}

// Sponsored filter
$sponsored_filter = $_GET['sponsored'] ?? '';
if ($sponsored_filter !== '') {
    $checkCol = $conn->query("SHOW COLUMNS FROM public_courses LIKE 'is_sponsored'");
    if ($checkCol && $checkCol->num_rows > 0) {
        $where_conditions[] = "c.is_sponsored = ?";
        $params[] = (int)$sponsored_filter;
        $types .= 'i';
    }
    $checkCol->free();
}

// Department filter for admin
$dept_filter = (int)($_GET['department'] ?? 0);
if (!$is_department_admin && $dept_filter > 0) {
    $where_conditions[] = "c.department_id = ?";
    $params[] = $dept_filter;
    $types .= 'i';
}

// Sort
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$allowed_sort = ['title', 'code', 'created_at', 'learner_count', 'is_published'];
if (!in_array($sort, $allowed_sort)) {
    $sort = 'created_at';
}
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Fetch courses with analytics - build dynamic column list based on what exists
$courseColumns = ['c.id', 'c.slug', 'c.title', 'c.code', 'c.summary', 'c.duration', 'c.department_id', 'c.cover_image', 'c.is_published', 'c.created_at', 'c.updated_at', 'c.certificate_enabled', 'c.pass_mark'];
$courseOptional = ['c.is_paid', 'c.price', 'c.payment_methods', 'c.is_sponsored', 'c.estimated_hours'];

foreach ($courseOptional as $col) {
    $colCheck = $conn->query("SHOW COLUMNS FROM public_courses LIKE '" . str_replace('c.', '', $col) . "'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $courseColumns[] = $col;
    }
}

$courseFields = implode(', ', $courseColumns);

$where_clause = implode(' AND ', $where_conditions);
$sql = "
    SELECT 
        $courseFields,
        d.name as department_name,
        (SELECT COUNT(*) FROM public_course_lessons l
         JOIN public_course_modules m ON m.id = l.module_id
         WHERE m.course_id = c.id) AS lesson_count,
        (SELECT COUNT(*) FROM public_course_assessments a
         WHERE a.course_id = c.id) AS assessment_count,
        (SELECT COUNT(*) FROM external_enrollments e
         WHERE e.course_id = c.id) AS learner_count,
        (SELECT COUNT(*) FROM certificates cert
         WHERE cert.course_id = c.id AND cert.revoked_at IS NULL) AS certificate_count
    FROM public_courses c
    LEFT JOIN departments d ON d.id = c.department_id
    WHERE $where_clause
    ORDER BY c.$sort $order
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch departments for filter
$departments = [];
if (!$is_department_admin) {
    $deptResult = $conn->query("SELECT id, name FROM departments ORDER BY name");
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Fetch tutor assignments for each course
$tutor_assignments = [];
$checkTutors = $conn->query("SHOW TABLES LIKE 'short_course_tutors'");
if ($checkTutors && $checkTutors->num_rows > 0) {
    $course_ids = array_column($courses, 'id');
    if (!empty($course_ids)) {
        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        $tutorStmt = $conn->prepare("
            SELECT sct.short_course_id, l.name as lecturer_name, l.email as lecturer_email, sct.is_active
            FROM short_course_tutors sct
            JOIN lecturers l ON l.id = sct.lecturer_id
            WHERE sct.short_course_id IN ($placeholders)
        ");
        $tutorStmt->bind_param(str_repeat('i', count($course_ids)), ...$course_ids);
        $tutorStmt->execute();
        $tutorResult = $tutorStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $tutorStmt->close();
        
        foreach ($tutorResult as $tutor) {
            $course_id = $tutor['short_course_id'];
            if (!isset($tutor_assignments[$course_id])) {
                $tutor_assignments[$course_id] = [];
            }
            $tutor_assignments[$course_id][] = $tutor;
        }
    }
}

// Fetch sponsors for each course
$course_sponsors = [];
$checkSponsors = $conn->query("SHOW TABLES LIKE 'course_sponsors'");
if ($checkSponsors && $checkSponsors->num_rows > 0) {
    $course_ids = array_column($courses, 'id');
    if (!empty($course_ids)) {
        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        $sponsorStmt = $conn->prepare("
            SELECT course_id, sponsor_name, sponsor_details, sponsor_logo
            FROM course_sponsors
            WHERE course_id IN ($placeholders)
            ORDER BY id
        ");
        $sponsorStmt->bind_param(str_repeat('i', count($course_ids)), ...$course_ids);
        $sponsorStmt->execute();
        $sponsorResult = $sponsorStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $sponsorStmt->close();
        
        foreach ($sponsorResult as $sponsor) {
            $course_id = $sponsor['course_id'];
            if (!isset($course_sponsors[$course_id])) {
                $course_sponsors[$course_id] = [];
            }
            $course_sponsors[$course_id][] = $sponsor;
        }
    }
}

// Fetch enrolled students for each course (learners subscribed via the public catalogue)
$course_enrollments = [];
$checkEnrollments = $conn->query("SHOW TABLES LIKE 'external_enrollments'");
if ($checkEnrollments && $checkEnrollments->num_rows > 0) {
    $course_ids = array_column($courses, 'id');
    if (!empty($course_ids)) {
        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        $enrollStmt = $conn->prepare("
            SELECT ee.course_id, el.name, el.email, el.phone, el.country, el.organisation,
                   ee.enrolled_at, ee.completed_at
            FROM external_enrollments ee
            JOIN external_learners el ON el.id = ee.learner_id
            WHERE ee.course_id IN ($placeholders)
            ORDER BY ee.enrolled_at DESC
        ");
        $enrollStmt->bind_param(str_repeat('i', count($course_ids)), ...$course_ids);
        $enrollStmt->execute();
        $enrollResult = $enrollStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $enrollStmt->close();

        foreach ($enrollResult as $enr) {
            $course_id = (int)$enr['course_id'];
            if (!isset($course_enrollments[$course_id])) {
                $course_enrollments[$course_id] = [];
            }
            $course_enrollments[$course_id][] = $enr;
        }
    }
}

// Calculate total revenue for paid courses
$total_revenue = 0;
$paid_course_count = 0;
foreach ($courses as $course) {
    if (isset($course['is_paid']) && $course['is_paid'] == 1 && $course['price']) {
        $total_revenue += ($course['price'] * $course['learner_count']);
        $paid_course_count++;
    }
}
// Download enrolled students as a PDF for a given course
if (isset($_GET['action']) && $_GET['action'] === 'download_enrollments_pdf') {
    $course_id = (int)($_GET['course_id'] ?? 0);
    if ($course_id <= 0) {
        http_response_code(400);
        exit('Invalid course ID.');
    }

    // Fetch the course (needed for title + department scope check)
    $course = null;
    $stmt = $conn->prepare("SELECT id, title, code, duration, department_id FROM public_courses WHERE id = ?");
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$course) {
        http_response_code(404);
        exit('Course not found.');
    }

    // Department admins may only export courses within their department
    if ($is_department_admin && (int)$course['department_id'] !== $department_id) {
        http_response_code(403);
        exit('Access denied.');
    }

    // Fetch all enrolled learners
    $enrollments = [];
    $estmt = $conn->prepare("
        SELECT el.name, el.email, el.phone, el.country, el.organisation,
               ee.enrolled_at, ee.completed_at
        FROM external_enrollments ee
        JOIN external_learners el ON el.id = ee.learner_id
        WHERE ee.course_id = ?
        ORDER BY ee.enrolled_at DESC
    ");
    $estmt->bind_param('i', $course_id);
    $estmt->execute();
    $enrollments = $estmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $estmt->close();

    $courseTitle = htmlspecialchars($course['title'] ?? 'Course', ENT_QUOTES, 'UTF-8');
    $courseCode  = htmlspecialchars((string)($course['code'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Build the enrollee table rows
    $rows = '';
    if (empty($enrollments)) {
        $rows = '<tr><td colspan="6" style="text-align:center; color:#888; padding:24px;">No students enrolled yet.</td></tr>';
    } else {
        foreach ($enrollments as $e) {
            $name     = htmlspecialchars((string)($e['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $email    = htmlspecialchars((string)($e['email'] ?? ''), ENT_QUOTES, 'UTF-8');
            $phone    = htmlspecialchars((string)($e['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
            $org      = htmlspecialchars((string)($e['organisation'] ?? ''), ENT_QUOTES, 'UTF-8');
            $enr      = htmlspecialchars(date('Y-m-d', strtotime((string)$e['enrolled_at'])), ENT_QUOTES, 'UTF-8');
            $status   = !empty($e['completed_at']) ? 'Completed' : 'Active';
            $rows .= '<tr>'
                . '<td>' . $name . '</td>'
                . '<td>' . $email . '</td>'
                . '<td>' . $phone . '</td>'
                . '<td>' . $org . '</td>'
                . '<td>' . $enr . '</td>'
                . '<td>' . $status . '</td>'
                . '</tr>';
        }
    }

    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 18mm 14mm; }
            body { font-family: Arial, Helvetica, sans-serif; color: #222; line-height: 1.4; }
            h1 { font-size: 22px; color: #1f2937; border-bottom: 3px solid #4f46e5; padding-bottom: 8px; margin: 0 0 4px; }
            .subtitle { color: #6b7280; font-size: 13px; margin-bottom: 18px; }
            .meta { font-size: 12px; color: #555; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th { background: #4f46e5; color: #fff; text-align: left; padding: 8px 10px; }
            td { padding: 7px 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) td { background: #f5f7fa; }
            .footer { margin-top: 28px; text-align: center; color: #888; font-size: 11px; }
        </style>
    </head>
    <body>
        <h1>Enrolled Students</h1>
        <div class="subtitle">' . $courseTitle . ($courseCode !== '' ? ' &mdash; ' . $courseCode : '') . '</div>
        <div class="meta">Total students: ' . count($enrollments) . ' &nbsp;|&nbsp; Generated: ' . date('F j, Y H:i') . '</div>
        <table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>Organisation</th><th>Enrolled</th><th>Status</th></tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>
        <div class="footer">UNILIS &mdash; Short Courses &bull; Downloaded from Short Courses Analytics</div>
    </body>
    </html>';

    require_once __DIR__ . '/../../vendor/autoload.php';
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $pdf = new \Dompdf\Dompdf($options);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'landscape');
    $pdf->render();

    $filename = 'enrolled_students_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $course['title']) . '_' . date('Y-m-d') . '.pdf';
    $pdf->stream($filename, ['Attachment' => true]);
    exit;
}

?>

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Short Courses Analytics - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f5f7fa;
            --surface: #ffffff;
            --surface2: #f8f9fa;
            --surface3: #e9ecef;
            --accent: #4f46e5;
            --accent2: #10b981;
            --accent3: #f59e0b;
            --danger: #ef4444;
            --text: #1f2937;
            --text-muted: #6b7280;
            --text-dim: #9ca3af;
            --border: #e5e7eb;
            --radius: 8px;
            --radius-sm: 4px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        
        .header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .header-nav {
            display: flex;
            gap: 12px;
        }
        
        .header-nav a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }
        
        .header-nav a:hover {
            background: var(--surface2);
            color: var(--text);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
        }
        
        .stat-card h3 {
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text);
        }
        
        .stat-card .trend {
            font-size: 0.75rem;
            color: var(--accent2);
            margin-top: 4px;
        }
        
        .filters-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .filters-bar input,
        .filters-bar select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            background: var(--surface);
            color: var(--text);
        }
        
        .filters-bar input:focus,
        .filters-bar select:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .filters-bar button {
            padding: 8px 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .filters-bar button:hover {
            background: #4338ca;
        }
        
        .courses-table {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        
        .courses-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .courses-table th,
        .courses-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .courses-table th {
            background: var(--surface2);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            cursor: pointer;
        }
        
        .courses-table th:hover {
            background: var(--surface3);
        }
        
        .courses-table tr:last-child td {
            border-bottom: none;
        }
        
        .courses-table tr:hover {
            background: var(--surface2);
        }
        
        .course-cover {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            background: var(--surface3);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-published {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent2);
        }
        
        .status-draft {
            background: rgba(245, 158, 11, 0.1);
            color: var(--accent3);
        }
        
        .action-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: var(--surface2);
        }
        
        .action-btn.danger {
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .action-btn.danger:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .message {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            font-size: 0.875rem;
        }
        
        .message.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent2);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .editable {
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background 0.2s;
            position: relative;
        }

        .editable:hover {
            background: var(--surface2);
        }

        .editable:hover::after {
            content: '\f303';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: var(--text-muted);
            opacity: 0.5;
        }

        .editable input {
            width: 100%;
            padding: 4px 8px;
            border: 1px solid var(--accent);
            border-radius: 4px;
            font-size: inherit;
            font-family: inherit;
            outline: none;
        }

        .editable.saving {
            opacity: 0.6;
            pointer-events: none;
        }

        .editable.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: var(--accent2);
            color: white;
        }

        .toast.error {
            background: var(--danger);
            color: white;
        }

        /* Enrollments modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-box {
            background: var(--surface);
            border-radius: var(--radius);
            width: 92%;
            max-width: 780px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            animation: slideIn 0.2s ease;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h3 {
            font-size: 1rem;
            color: var(--accent);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            color: var(--text-muted);
        }

        .modal-close:hover {
            color: var(--danger);
        }

        .modal-course {
            padding: 10px 20px 0;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .modal-course strong {
            color: var(--text);
        }

        .modal-body {
            overflow-y: auto;
            padding: 12px 20px 16px;
        }

        .enroll-table {
            width: 100%;
            border-collapse: collapse;
        }

        .enroll-table th,
        .enroll-table td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }

        .enroll-table th {
            background: var(--surface2);
            font-size: 0.72rem;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            text-align: right;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>Short Courses Analytics</h1>
        <nav class="header-nav">
            <a href="department_admins.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="../../learn/"><i class="fas fa-external-link-alt"></i> View Public Site</a>
        </nav>
    </header>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Courses</h3>
                <div class="value"><?= count($courses) ?></div>
            </div>
            <div class="stat-card">
                <h3>Published Courses</h3>
                <div class="value"><?= count(array_filter($courses, fn($c) => $c['is_published'])) ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Enrollments</h3>
                <div class="value"><?= array_sum(array_column($courses, 'learner_count')) ?></div>
            </div>
            <div class="stat-card">
                <h3>Certificates Issued</h3>
                <div class="value"><?= array_sum(array_column($courses, 'certificate_count')) ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value">KSh <?= number_format($total_revenue, 2) ?></div>
                <div class="trend"><?= $paid_course_count ?> paid courses</div>
            </div>
        </div>
        
        <!-- Filters -->
        <form class="filters-bar" method="GET">
            <input type="text" name="search" placeholder="Search courses..." value="<?= htmlspecialchars($search) ?>">
            
            <select name="publish">
                <option value="">All Status</option>
                <option value="1" <?= $publish_filter === '1' ? 'selected' : '' ?>>Published</option>
                <option value="0" <?= $publish_filter === '0' ? 'selected' : '' ?>>Draft</option>
            </select>
            
            <select name="paid">
                <option value="">All Pricing</option>
                <option value="1" <?= $paid_filter === '1' ? 'selected' : '' ?>>Paid</option>
                <option value="0" <?= $paid_filter === '0' ? 'selected' : '' ?>>Free</option>
            </select>
            
            <select name="sponsored">
                <option value="">All Sponsorship</option>
                <option value="1" <?= $sponsored_filter === '1' ? 'selected' : '' ?>>Sponsored</option>
                <option value="0" <?= $sponsored_filter === '0' ? 'selected' : '' ?>>Not Sponsored</option>
            </select>
            
            <?php if (!$is_department_admin): ?>
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $dept_filter == $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <button type="submit"><i class="fas fa-search"></i> Filter</button>
            <a href="short_courses_analytics.php" style="padding: 8px 16px; color: var(--text-muted); text-decoration: none; font-size: 0.875rem;">Clear</a>
        </form>
        
        <!-- Courses Table -->
        <div class="courses-table">
            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No courses found</h3>
                    <p>Try adjusting your filters or create a new course.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Enrollments</th>
                            <th>Certificates</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Pass Mark</th>
                            <th>Tutors</th>
                            <th>Sponsors</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <?php if ($course['cover_image']): ?>
                                            <img src="<?= htmlspecialchars($course['cover_image']) ?>" alt="" class="course-cover">
                                        <?php else: ?>
                                            <div class="course-cover" style="display: flex; align-items: center; justify-content: center; background: var(--surface3);">
                                                <i class="fas fa-graduation-cap" style="color: var(--text-dim);"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="editable" data-field="title" data-course-id="<?= $course['id'] ?>" style="font-weight: 600;"><?= htmlspecialchars($course['title']) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <span class="editable" data-field="code" data-course-id="<?= $course['id'] ?>"><?= htmlspecialchars($course['code'] ?? 'N/A') ?></span> · 
                                                <?= $course['lesson_count'] ?> lessons · 
                                                <?= $course['assessment_count'] ?> assessments
                                            </div>
                                            <?php if ($course['summary']): ?>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">
                                                <span class="editable" data-field="summary" data-course-id="<?= $course['id'] ?>"><?= htmlspecialchars(substr($course['summary'], 0, 60)) ?><?= strlen($course['summary']) > 60 ? '...' : '' ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($course['department_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $course['is_published'] ? 'status-published' : 'status-draft' ?>">
                                        <?= $course['is_published'] ? 'Published' : 'Draft' ?>
                                    </span>
                                </td>
                                <td><?= $course['learner_count'] ?></td>
                                <td><?= $course['certificate_count'] ?></td>
                                <td>
                                    <?php if (isset($course['duration'])): ?>
                                        <span class="editable" data-field="duration" data-course-id="<?= $course['id'] ?>"><?= htmlspecialchars($course['duration']) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($course['is_paid']) && $course['is_paid'] == 1): ?>
                                        <span class="editable" data-field="price" data-course-id="<?= $course['id'] ?>" data-type="number" data-step="0.01">KSh <?= number_format($course['price'], 2) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Free</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($course['pass_mark'])): ?>
                                        <span class="editable" data-field="pass_mark" data-course-id="<?= $course['id'] ?>" data-type="number" data-step="1"><?= $course['pass_mark'] ?>%</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($tutor_assignments[$course['id']])): ?>
                                        <?php foreach ($tutor_assignments[$course['id']] as $tutor): ?>
                                            <div style="font-size: 0.75rem; <?= $tutor['is_active'] ? '' : 'color: var(--text-muted); text-decoration: line-through;' ?>">
                                                <?= htmlspecialchars($tutor['lecturer_name']) ?>
                                                <?= $tutor['is_active'] ? '' : '(inactive)' ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">No tutors</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($course_sponsors[$course['id']])): ?>
                                        <?php foreach ($course_sponsors[$course['id']] as $sponsor): ?>
                                            <div style="font-size: 0.75rem;"><?= htmlspecialchars($sponsor['sponsor_name']) ?></div>
                                        <?php endforeach; ?>
                                    <?php elseif (isset($course['is_sponsored']) && $course['is_sponsored'] == 1): ?>
                                        <span style="color: var(--danger); font-size: 0.75rem;">Missing sponsor info</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">Not sponsored</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?= date('M j, Y', strtotime($course['created_at'])) ?>
                                </td>
                                <td>
                                    <button type="button" class="action-btn" data-course-id="<?= (int)$course['id'] ?>" data-course-title="<?= htmlspecialchars($course['title'], ENT_QUOTES) ?>" onclick="openEnrollmentsModal(this)" title="View enrolled students">
                                        <i class="fas fa-users"></i> Students
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_publish">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <button type="submit" class="action-btn" title="<?= $course['is_published'] ? 'Unpublish' : 'Publish' ?>">
                                            <i class="fas fa-<?= $course['is_published'] ? 'eye-slash' : 'eye' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_course">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <button type="submit" class="action-btn danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const COURSE_ENROLLMENTS = <?= json_encode($course_enrollments, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

        function escapeHtml(value) {
            const d = document.createElement('div');
            d.textContent = value == null ? '' : String(value);
            return d.innerHTML;
        }

        function openEnrollmentsModal(btn) {
            const courseId = btn.dataset.courseId;
            const courseTitle = btn.dataset.courseTitle || 'Course';

            const downloadBtn = document.getElementById('enrollments-download-btn');
            if (downloadBtn) {
                downloadBtn.href = 'short_courses_analytics.php?action=download_enrollments_pdf&course_id=' + encodeURIComponent(courseId);
            }

            if (courseTitle) {
                const titleEl = document.getElementById('enrollments-course-title');
                if (titleEl) titleEl.innerHTML = 'Course: <strong>' + escapeHtml(courseTitle) + '</strong>';
            }

            const body = document.getElementById('enrollments-body');
            body.innerHTML = '';
            const list = COURSE_ENROLLMENTS[courseId] || [];

            if (!list.length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 6;
                td.style.textAlign = 'center';
                td.style.color = 'var(--text-muted)';
                td.style.padding = '24px';
                td.textContent = 'No students enrolled yet.';
                tr.appendChild(td);
                body.appendChild(tr);
                document.getElementById('enrollments-modal').style.display = 'flex';
                return;
            }

            list.forEach(e => {
                const status = e.completed_at ? 'Completed' : 'Active';
                const statusClass = status === 'Completed' ? 'status-published' : 'status-draft';
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + escapeHtml(e.name) + '</td>' +
                    '<td>' + escapeHtml(e.email) + '</td>' +
                    '<td>' + escapeHtml(e.phone || '') + '</td>' +
                    '<td>' + escapeHtml(e.organisation || '') + '</td>' +
                    '<td>' + escapeHtml((e.enrolled_at || '').slice(0, 10)) + '</td>' +
                    '<td><span class="status-badge ' + statusClass + '">' + status + '</span></td>';
                body.appendChild(tr);
            });

            document.getElementById('enrollments-modal').style.display = 'flex';
        }

        function closeEnrollmentsModal() {
            document.getElementById('enrollments-modal').style.display = 'none';
        }

        document.addEventListener('click', function (e) {
            const overlay = document.getElementById('enrollments-modal');
            if (overlay && e.target === overlay) closeEnrollmentsModal();
        });
    </script>

    <!-- Enrollments Modal -->
    <div class="modal-overlay" id="enrollments-modal" style="display:none;">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-users"></i> Enrolled Students</h3>
                <button type="button" class="modal-close" onclick="closeEnrollmentsModal()" title="Close">&times;</button>
            </div>
            <div class="modal-course" id="enrollments-course-title"></div>
            <div class="modal-body">
                <table class="enroll-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Organisation</th>
                            <th>Enrolled</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="enrollments-body"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <a href="#" class="action-btn" id="enrollments-download-btn" title="Download enrolled students as PDF"><i class="fas fa-file-pdf"></i> Download PDF</a>
                <button type="button" class="action-btn" onclick="closeEnrollmentsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editableFields = document.querySelectorAll('.editable');
            
            editableFields.forEach(field => {
                field.addEventListener('click', function(e) {
                    if (this.querySelector('input')) return; // Already editing
                    
                    const currentValue = this.textContent.trim();
                    const courseId = this.dataset.courseId;
                    const fieldName = this.dataset.field;
                    const fieldType = this.dataset.type || (fieldName === 'price' || fieldName === 'pass_mark' || fieldName === 'estimated_hours' ? 'number' : 'text');
                    const fieldStep = this.dataset.step || (fieldName === 'price' || fieldName === 'estimated_hours' ? '0.01' : '1');
                    
                    // Strip currency prefix for price fields
                    let cleanValue = currentValue;
                    if (fieldName === 'price' && currentValue.startsWith('KSh ')) {
                        cleanValue = currentValue.replace('KSh ', '').replace(/,/g, '');
                    }
                    
                    // Create input element
                    const input = document.createElement('input');
                    input.type = fieldType;
                    input.value = cleanValue;
                    input.step = fieldStep;
                    
                    // Replace content with input
                    this.innerHTML = '';
                    this.appendChild(input);
                    input.focus();
                    input.select();
                    
                    this.classList.add('editing');
                    
                    // Save on blur or Enter
                    const saveEdit = () => {
                        const newValue = input.value.trim();
                        
                        if (newValue !== currentValue) {
                            this.classList.add('saving');
                            
                            const formData = new FormData();
                            formData.append('action', 'update_course_field');
                            formData.append('course_id', courseId);
                            formData.append('field', fieldName);
                            formData.append('value', newValue);
                            
                            fetch('short_courses_analytics.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Reformat price with currency prefix
                                    let displayValue = newValue;
                                    if (fieldName === 'price') {
                                        const numValue = parseFloat(newValue);
                                        if (!isNaN(numValue)) {
                                            displayValue = 'KSh ' + numValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                        }
                                    }
                                    this.textContent = displayValue;
                                    this.classList.remove('saving', 'error');
                                    showToast('Updated successfully', 'success');
                                } else {
                                    this.textContent = currentValue;
                                    this.classList.remove('saving');
                                    this.classList.add('error');
                                    showToast(data.message || 'Update failed', 'error');
                                }
                            })
                            .catch(error => {
                                this.textContent = currentValue;
                                this.classList.remove('saving');
                                this.classList.add('error');
                                showToast('Network error', 'error');
                            });
                        } else {
                            this.textContent = currentValue;
                            this.classList.remove('editing');
                        }
                    };
                    
                    input.addEventListener('blur', saveEdit);
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            input.blur();
                        } else if (e.key === 'Escape') {
                            this.textContent = currentValue;
                            this.classList.remove('editing');
                        }
                    });
                });
            });
            
            function showToast(message, type) {
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.textContent = message;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    toast.style.transition = 'all 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }
        });
    </script>
</body>
</html>
