<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid course']);
    exit;
}

// Check if tutor has any access to this course
$hasAccess = false;

// Check if course author
$checkAuthor = $conn->prepare("SELECT id FROM public_courses WHERE id = ? AND author_id = ?");
$checkAuthor->bind_param("ii", $course_id, $lecturer_id);
$checkAuthor->execute();
if ($checkAuthor->get_result()->fetch_row()) {
    $hasAccess = true;
}
$checkAuthor->close();

// Check if assigned via short_course_tutors
if (!$hasAccess) {
    $checkTutor = $conn->prepare("SELECT id FROM short_course_tutors WHERE short_course_id = ? AND lecturer_id = ? AND is_active = 1");
    $checkTutor->bind_param("ii", $course_id, $lecturer_id);
    $checkTutor->execute();
    if ($checkTutor->get_result()->fetch_row()) {
        $hasAccess = true;
    }
    $checkTutor->close();
}

// Check if has module permissions
if (!$hasAccess) {
    $checkPerm = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
    if ($checkPerm && $checkPerm->num_rows > 0) {
        $checkMod = $conn->prepare("
            SELECT tmp.id FROM tutor_module_permissions tmp
            JOIN public_course_modules m ON m.id = tmp.module_id
            WHERE tmp.tutor_id = ? AND m.course_id = ?
        ");
        $checkMod->bind_param("ii", $lecturer_id, $course_id);
        $checkMod->execute();
        if ($checkMod->get_result()->fetch_row()) {
            $hasAccess = true;
        }
        $checkMod->close();
    }
}

if (!$hasAccess) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Fetch tutor's module permissions
$editableModuleIds = [];
$viewOnlyModuleIds = [];
$editableLessonIds = [];
$viewOnlyLessonIds = [];

$permCheck = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
if ($permCheck && $permCheck->num_rows > 0) {
    $stmt = $conn->prepare("SELECT module_id, can_edit FROM tutor_module_permissions WHERE tutor_id = ?");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $permResult = $stmt->get_result();
    while ($perm = $permResult->fetch_assoc()) {
        if ($perm['can_edit']) {
            $editableModuleIds[] = $perm['module_id'];
        } else {
            $viewOnlyModuleIds[] = $perm['module_id'];
        }
    }
    $stmt->close();
}

$lessonPermCheck = $conn->query("SHOW TABLES LIKE 'tutor_lesson_permissions'");
if ($lessonPermCheck && $lessonPermCheck->num_rows > 0) {
    $stmt = $conn->prepare("SELECT lesson_id, can_edit FROM tutor_lesson_permissions WHERE tutor_id = ?");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $permResult = $stmt->get_result();
    while ($perm = $permResult->fetch_assoc()) {
        if ($perm['can_edit']) {
            $editableLessonIds[] = $perm['lesson_id'];
        } else {
            $viewOnlyLessonIds[] = $perm['lesson_id'];
        }
    }
    $stmt->close();
}

// Check if course author
$isAuthor = false;
$checkAuthor = $conn->prepare("SELECT id FROM public_courses WHERE id = ? AND author_id = ?");
$checkAuthor->bind_param("ii", $course_id, $lecturer_id);
$checkAuthor->execute();
if ($checkAuthor->get_result()->fetch_row()) {
    $isAuthor = true;
}
$checkAuthor->close();

// Fetch modules
$modules = [];
$stmt = $conn->prepare("
    SELECT id, title, position
    FROM public_course_modules
    WHERE course_id = ?
    ORDER BY position ASC, id ASC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
while ($mod = $result->fetch_assoc()) {
    // Determine edit permission
    if ($isAuthor) {
        $mod['can_edit'] = true;
    } elseif (in_array($mod['id'], $editableModuleIds)) {
        $mod['can_edit'] = true;
    } elseif (in_array($mod['id'], $viewOnlyModuleIds)) {
        $mod['can_edit'] = false;
    } else {
        // No specific permission - check if assigned to course
        $mod['can_edit'] = false;
    }
    $modules[] = $mod;
}
$stmt->close();

// Fetch lessons
$lessons = [];
if (!empty($modules)) {
    $moduleIds = array_column($modules, 'id');
    $moduleIdsStr = implode(',', array_map('intval', $moduleIds));
    
    $lessonQuery = $conn->query("
        SELECT id, module_id, title, position
        FROM public_course_lessons
        WHERE module_id IN ($moduleIdsStr)
        ORDER BY position ASC, id ASC
    ");
    while ($lesson = $lessonQuery->fetch_assoc()) {
        // Determine edit permission
        if ($isAuthor) {
            $lesson['can_edit'] = true;
        } elseif (in_array($lesson['id'], $editableLessonIds)) {
            $lesson['can_edit'] = true;
        } elseif (in_array($lesson['id'], $viewOnlyLessonIds)) {
            $lesson['can_edit'] = false;
        } elseif (in_array($lesson['module_id'], $editableModuleIds)) {
            // If module is editable, lessons are too
            $lesson['can_edit'] = true;
        } elseif (in_array($lesson['module_id'], $viewOnlyModuleIds)) {
            $lesson['can_edit'] = false;
        } else {
            $lesson['can_edit'] = false;
        }
        $lessons[] = $lesson;
    }
}

echo json_encode([
    'success' => true,
    'modules' => $modules,
    'lessons' => $lessons,
]);
