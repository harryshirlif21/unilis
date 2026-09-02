<?php
/**
 * Assign Lecturers to Lessons
 * UNILIS Department Admin - Manage which lecturers teach which lessons
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_extended.php';

// Only department_admin can access
phase1_guard_role(ROLE_DEPARTMENT_ADMIN, '../../login.php');

$admin_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];

// Get department info
$dept = $conn->query("SELECT name FROM departments WHERE id = $department_id")->fetch_assoc();
$department_name = $dept ? $dept['name'] : 'Unknown';

// Handle assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'assign_lecturer') {
        $lecturer_id = (int)$_POST['lecturer_id'];
        $lesson_id = (int)$_POST['lesson_id'];
        $can_edit = isset($_POST['can_edit']) ? 1 : 0;
        $can_teach = isset($_POST['can_teach']) ? 1 : 0;
        
        // Verify lesson belongs to department's courses
        $lesson_check = $conn->prepare("
            SELECT l.id FROM public_course_lessons l
            JOIN public_course_modules m ON m.id = l.module_id
            JOIN public_courses c ON c.id = m.course_id
            WHERE l.id = ? AND c.department_id = ? LIMIT 1
        ");
        $lesson_check->bind_param('ii', $lesson_id, $department_id);
        $lesson_check->execute();
        if ($lesson_check->get_result()->num_rows > 0) {
            // Insert or update permission
            $stmt = $conn->prepare("
                INSERT INTO tutor_lesson_permissions (tutor_id, lesson_id, can_edit, can_teach, assigned_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE can_edit = VALUES(can_edit), can_teach = VALUES(can_teach)
            ");
            $stmt->bind_param('iiiii', $lecturer_id, $lesson_id, $can_edit, $can_teach, $admin_id);
            $stmt->execute();
            $stmt->close();
        }
        $lesson_check->close();
    } elseif ($action === 'remove_lecturer') {
        $lecturer_id = (int)$_POST['lecturer_id'];
        $lesson_id = (int)$_POST['lesson_id'];
        
        $stmt = $conn->prepare("
            DELETE FROM tutor_lesson_permissions
            WHERE tutor_id = ? AND lesson_id = ?
        ");
        $stmt->bind_param('ii', $lecturer_id, $lesson_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Get all courses in department
$courses = [];
$courses_result = $conn->query("
    SELECT id, title FROM public_courses 
    WHERE department_id = $department_id 
    ORDER BY title ASC
");
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}

// Get selected course (default to first)
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : ($courses[0]['id'] ?? 0);

// Get modules and lessons for selected course
$modules = [];
$lessons_list = [];
if ($selected_course_id > 0) {
    $modules_result = $conn->query("
        SELECT id, title FROM public_course_modules 
        WHERE course_id = $selected_course_id 
        ORDER BY position ASC, id ASC
    ");
    
    while ($module = $modules_result->fetch_assoc()) {
        $module_id = $module['id'];
        $module['lessons'] = [];
        
        $lessons_result = $conn->query("
            SELECT id, title FROM public_course_lessons 
            WHERE module_id = $module_id 
            ORDER BY position ASC, id ASC
        ");
        
        while ($lesson = $lessons_result->fetch_assoc()) {
            $module['lessons'][] = $lesson;
            $lessons_list[] = [
                'id' => $lesson['id'],
                'title' => $lesson['title'],
                'module_title' => $module['title'],
                'module_id' => $module_id
            ];
        }
        $lessons_result->close();
        
        $modules[] = $module;
    }
    $modules_result->close();
}

// Get all lecturers in department
$lecturers = [];
$lecturers_result = $conn->query("
    SELECT id, name, email FROM lecturers 
    WHERE department_id = $department_id 
    ORDER BY name ASC
");
while ($row = $lecturers_result->fetch_assoc()) {
    $lecturers[] = $row;
}

// Get current assignments for selected course
$assignments = [];
if ($selected_course_id > 0) {
    $stmt = $conn->prepare("
        SELECT tlp.tutor_id, tlp.lesson_id, tlp.can_edit, tlp.can_teach
        FROM tutor_lesson_permissions tlp
        JOIN public_course_lessons l ON l.id = tlp.lesson_id
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE m.course_id = ?
    ");
    $stmt->bind_param('i', $selected_course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $key = $row['lesson_id'] . '_' . $row['tutor_id'];
        $assignments[$key] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Lecturers to Lessons - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; }
        .header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; }
        .header a { color: #fff; text-decoration: none; margin-left: 16px; }
        .header a:hover { opacity: .8; }
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .back-btn { display: inline-block; margin-bottom: 20px; }
        .back-btn a { color: #2563eb; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .back-btn a:hover { text-decoration: underline; }
        .course-selector { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .course-selector label { font-weight: 600; margin-bottom: 8px; display: block; }
        .course-selector select { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        
        .lessons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .lesson-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.1); border-left: 4px solid #2563eb; }
        .lesson-card h3 { font-size: 16px; margin-bottom: 4px; color: #1e3a8a; }
        .lesson-card .module-name { font-size: 12px; color: #6b7280; margin-bottom: 12px; }
        
        .lecturers-list { border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px; }
        .lecturer-item { display: flex; align-items: center; gap: 8px; padding: 8px; margin-bottom: 8px; background: #f9fafb; border-radius: 6px; font-size: 13px; }
        .lecturer-checkbox { cursor: pointer; }
        .lecturer-info { flex: 1; }
        .lecturer-name { font-weight: 600; }
        .lecturer-email { font-size: 11px; color: #6b7280; }
        .permission-flags { display: flex; gap: 4px; margin-left: 8px; font-size: 11px; }
        .permission-flag { background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 3px; }
        .remove-btn { background: #ef4444; color: #fff; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer; font-size: 11px; }
        .remove-btn:hover { background: #dc2626; }
        
        .empty-state { text-align: center; padding: 40px; color: #6b7280; }
        .empty-state i { font-size: 48px; opacity: .3; margin-bottom: 16px; }
        
        .assignment-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
        .assignment-modal.show { display: flex; }
        .modal-content { background: #fff; border-radius: 10px; padding: 24px; max-width: 400px; width: 90%; }
        .modal-close { position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; }
        .modal-content h2 { margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
        .form-group select { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .form-group-checkboxes { display: flex; gap: 16px; }
        .checkbox-item { display: flex; align-items: center; gap: 6px; }
        .modal-buttons { display: flex; gap: 8px; margin-top: 20px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-chalkboard-teacher"></i> Assign Lecturers to Lessons</h1>
        <div>
            <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="back-btn">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        
        <div class="course-selector">
            <label><i class="fas fa-book"></i> Select Course</label>
            <select onchange="location.href='assign_lecturers_lessons.php?course_id=' + this.value">
                <?php foreach ($courses as $course): ?>
                    <option value="<?= $course['id'] ?>" <?= $course['id'] === $selected_course_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($course['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if (empty($lessons_list)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h2>No Lessons Found</h2>
                <p>This course doesn't have any lessons yet.</p>
            </div>
        <?php else: ?>
            <div class="lessons-grid">
                <?php foreach ($modules as $module): ?>
                    <?php foreach ($module['lessons'] as $lesson): ?>
                        <div class="lesson-card">
                            <h3><?= htmlspecialchars($lesson['title']) ?></h3>
                            <div class="module-name">
                                <i class="fas fa-folder"></i>
                                <?= htmlspecialchars($module['title']) ?>
                            </div>
                            
                            <div class="lecturers-list">
                                <?php 
                                $lesson_id = $lesson['id'];
                                $assigned_count = 0;
                                
                                foreach ($lecturers as $lecturer):
                                    $key = $lesson_id . '_' . $lecturer['id'];
                                    $is_assigned = isset($assignments[$key]);
                                    if ($is_assigned) $assigned_count++;
                                ?>
                                    <div class="lecturer-item">
                                        <input type="checkbox" class="lecturer-checkbox" 
                                               data-lecturer-id="<?= $lecturer['id'] ?>"
                                               data-lesson-id="<?= $lesson_id ?>"
                                               <?= $is_assigned ? 'checked' : '' ?>
                                               onchange="toggleAssignment(this)">
                                        <div class="lecturer-info">
                                            <div class="lecturer-name"><?= htmlspecialchars($lecturer['name']) ?></div>
                                            <div class="lecturer-email"><?= htmlspecialchars($lecturer['email']) ?></div>
                                        </div>
                                        <?php if ($is_assigned): ?>
                                            <div class="permission-flags">
                                                <?php if ($assignments[$key]['can_teach']): ?>
                                                    <span class="permission-flag">Teach</span>
                                                <?php endif; ?>
                                                <?php if ($assignments[$key]['can_edit']): ?>
                                                    <span class="permission-flag">Edit</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <p style="margin-top: 12px; font-size: 12px; color: #6b7280;">
                                <strong><?= $assigned_count ?></strong> lecturer<?= $assigned_count !== 1 ? 's' : '' ?> assigned
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hidden assignment modal form -->
    <form id="assignmentForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="lecturer_id" id="formLecturerId">
        <input type="hidden" name="lesson_id" id="formLessonId">
        <input type="hidden" name="can_edit" id="formCanEdit" value="1">
        <input type="hidden" name="can_teach" id="formCanTeach" value="1">
    </form>

    <script>
        function toggleAssignment(checkbox) {
            const lecturerId = parseInt(checkbox.dataset.lecturerId);
            const lessonId = parseInt(checkbox.dataset.lessonId);
            
            const form = document.getElementById('assignmentForm');
            form.action = '?course_id=<?= $selected_course_id ?>';
            
            if (checkbox.checked) {
                // Assign
                document.getElementById('formAction').value = 'assign_lecturer';
                document.getElementById('formLecturerId').value = lecturerId;
                document.getElementById('formLessonId').value = lessonId;
                document.getElementById('formCanEdit').value = '1';
                document.getElementById('formCanTeach').value = '1';
            } else {
                // Remove
                document.getElementById('formAction').value = 'remove_lecturer';
                document.getElementById('formLecturerId').value = lecturerId;
                document.getElementById('formLessonId').value = lessonId;
            }
            
            form.submit();
        }
    </script>
</body>
</html>
