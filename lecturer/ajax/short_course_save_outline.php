<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['lecturer', 'department_admin', 'admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$course_id   = (int)($_POST['course_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$outline     = trim($_POST['outline'] ?? '');
$levelOutlines = json_decode($_POST['level_outlines'] ?? '[]', true);

if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid course']);
    exit;
}

// Verify access. Department administrators are restricted to their own department.
$role = $_SESSION['user_role'];
if ($role === 'lecturer') {
$check = $conn->prepare("SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? UNION SELECT 1 FROM public_courses WHERE id = ? AND created_by_lecturer_id = ?");
    $check->bind_param("iiii", $lecturer_id, $course_id, $course_id, $lecturer_id);
} elseif ($role === 'department_admin') {
    $departmentId = (int)($_SESSION['department_id'] ?? 0);
    $check = $conn->prepare('SELECT 1 FROM public_courses WHERE id = ? AND department_id = ?');
    $check->bind_param('ii', $course_id, $departmentId);
} else {
    $check = $conn->prepare('SELECT 1 FROM public_courses WHERE id = ?');
    $check->bind_param('i', $course_id);
}
$check->execute();
if (!$check->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$check->close();

function outlineColumnExists(mysqli $conn, string $table): bool
{
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'outline'");
    return $result && $result->num_rows > 0;
}

if (!outlineColumnExists($conn, 'public_courses')
    || !outlineColumnExists($conn, 'public_course_modules')
    || !outlineColumnExists($conn, 'public_course_lessons')) {
    echo json_encode(['success' => false, 'message' => 'Outline fields are not installed. Run phase1/database/migration_002_short_course_outlines.php first.']);
    exit;
}

if (!is_array($levelOutlines)) {
    $levelOutlines = [];
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('UPDATE public_courses SET description = ?, outline = ? WHERE id = ?');
    $stmt->bind_param('ssi', $description, $outline, $course_id);
    $stmt->execute();
    $stmt->close();

    $moduleStmt = $conn->prepare('UPDATE public_course_modules SET outline = ? WHERE id = ? AND course_id = ?');
    $lessonStmt = $conn->prepare('UPDATE public_course_lessons l JOIN public_course_modules m ON m.id = l.module_id SET l.outline = ? WHERE l.id = ? AND m.course_id = ?');
    foreach ($levelOutlines as $item) {
        $type = $item['type'] ?? '';
        $id = (int)($item['id'] ?? 0);
        $itemOutline = substr(trim((string)($item['outline'] ?? '')), 0, 10000);
        if (!$id) {
            continue;
        }
        if ($type === 'module') {
            $moduleStmt->bind_param('sii', $itemOutline, $id, $course_id);
            $moduleStmt->execute();
        } elseif ($type === 'lesson') {
            $lessonStmt->bind_param('sii', $itemOutline, $id, $course_id);
            $lessonStmt->execute();
        }
    }
    $moduleStmt->close();
    $lessonStmt->close();
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Unable to save outlines.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Course, module, and lesson outlines saved']);
