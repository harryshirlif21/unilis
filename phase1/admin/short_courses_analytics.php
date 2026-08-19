<?php
/**
 * Short Courses Analytics Page
 * UNILIS Academic Foundation Expansion
 * Consolidated view for course management, student analytics, revenue, tutors, and sponsors
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
phase1_guard_role(['admin', 'department_admin'], '../../login.php');

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

// Fetch courses with analytics
$where_clause = implode(' AND ', $where_conditions);
$sql = "
    SELECT 
        c.id, c.slug, c.title, c.code, c.summary, c.duration, c.department_id,
        c.cover_image, c.is_published, c.created_at, c.updated_at,
        c.certificate_enabled, c.pass_mark,
        c.is_paid, c.price, c.payment_methods, c.is_sponsored,
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

// Calculate total revenue for paid courses
$total_revenue = 0;
$paid_course_count = 0;
foreach ($courses as $course) {
    if (isset($course['is_paid']) && $course['is_paid'] == 1 && $course['price']) {
        $total_revenue += ($course['price'] * $course['learner_count']);
        $paid_course_count++;
    }
}

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
                            <th>Revenue</th>
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
                                            <div style="font-weight: 600;"><?= htmlspecialchars($course['title']) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <?= htmlspecialchars($course['code'] ?? 'N/A') ?> · 
                                                <?= $course['lesson_count'] ?> lessons · 
                                                <?= $course['assessment_count'] ?> assessments
                                            </div>
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
                                    <?php if (isset($course['is_paid']) && $course['is_paid'] == 1): ?>
                                        KSh <?= number_format($course['price'], 2) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Free</span>
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
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_publish">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <button type="submit" class="action-btn" title="<?= $course['is_published'] ? 'Unpublish' : 'Publish' ?>">
                                            <i class="fas fa-<?= $course['is_published'] ? 'eye-slash' : 'eye' ?>"></i>
                                        </button>
                                    </form>
                                    <a href="department_admins.php#edit-course-<?= $course['id'] ?>" class="action-btn" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
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
</body>
</html>
