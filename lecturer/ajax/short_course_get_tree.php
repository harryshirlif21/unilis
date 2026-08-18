<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$course_id   = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$lecturer_id = (int)$_SESSION['user_id'];

if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid course']);
    exit;
}

if (!shortCourseCanManage($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

function hasOutlineColumn(mysqli $conn, string $table): bool
{
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'outline'");
    return $result && $result->num_rows > 0;
}

$courseHasOutline = hasOutlineColumn($conn, 'public_courses');
$moduleHasOutline = hasOutlineColumn($conn, 'public_course_modules');
$lessonHasOutline = hasOutlineColumn($conn, 'public_course_lessons');

// Check if summary, start_date, end_date columns exist in modules
$moduleHasSummary = hasOutlineColumn($conn, 'public_course_modules') && $conn->query("SHOW COLUMNS FROM public_course_modules LIKE 'summary'")->num_rows > 0;
$moduleHasDates = $conn->query("SHOW COLUMNS FROM public_course_modules LIKE 'start_date'")->num_rows > 0;

// Fetch course info for outline (description)
$outline = null;
$stmt = $conn->prepare('SELECT description' . ($courseHasOutline ? ', outline' : '') . ' FROM public_courses WHERE id = ? LIMIT 1');
$stmt->bind_param("i", $course_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) $outline = $row;
$stmt->close();

// Fetch tutor's module permissions
$editableModuleIds = [];
$viewOnlyModuleIds = [];
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

// If course author, they can edit all modules
$isAuthor = shortCourseIsAuthor();

// Fetch modules from public_course_modules
$modules = [];
$selectFields = "id, title, position";
if ($moduleHasOutline) $selectFields .= ", outline";
if ($moduleHasSummary) $selectFields .= ", summary";
if ($moduleHasDates) $selectFields .= ", start_date, end_date";

$stmt = $conn->prepare("
    SELECT $selectFields
    FROM public_course_modules
    WHERE course_id = ?
    ORDER BY position ASC, id ASC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
while ($mod = $result->fetch_assoc()) {
    $mod['lessons'] = [];
    // Determine edit permission
    if ($isAuthor) {
        $mod['can_edit'] = true;
    } elseif (in_array($mod['id'], $editableModuleIds)) {
        $mod['can_edit'] = true;
    } elseif (in_array($mod['id'], $viewOnlyModuleIds)) {
        $mod['can_edit'] = false;
    } else {
        // No permission assigned - default to view only if not author
        $mod['can_edit'] = false;
    }
    $modules[$mod['id']] = $mod;
}
$stmt->close();

// Fetch lessons for all modules
if (!empty($modules)) {
    $moduleIds = implode(',', array_keys($modules));
    $lessonQuery = $conn->query("
        SELECT id, module_id, title, position" . ($lessonHasOutline ? ', outline' : '') . "
        FROM public_course_lessons
        WHERE module_id IN ($moduleIds)
        ORDER BY position ASC, id ASC
    ");
    while ($lesson = $lessonQuery->fetch_assoc()) {
        $mid = $lesson['module_id'];
        if (isset($modules[$mid])) {
            $modules[$mid]['lessons'][] = $lesson;
        }
    }
}

echo json_encode([
    'success' => true,
    'modules' => array_values($modules),
    'outline' => $outline,
]);
