<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$course_id   = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$lecturer_id = (int)$_SESSION['user_id'];

if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid course']);
    exit;
}

// Verify lecturer has access to this short course
$check = $conn->prepare("
    SELECT 1 FROM short_course_tutors 
    WHERE lecturer_id = ? AND short_course_id = ? AND is_active = 1
    UNION
    SELECT 1 FROM public_courses WHERE id = ? AND created_by_lecturer_id = ?
");
$check->bind_param("iiii", $lecturer_id, $course_id, $course_id, $lecturer_id);
$check->execute();
if (!$check->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$check->close();

// Fetch course info for outline (description)
$outline = null;
$stmt = $conn->prepare("SELECT description FROM public_courses WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) $outline = $row;
$stmt->close();

// Fetch modules from public_course_modules
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
    $mod['lessons'] = [];
    $modules[$mod['id']] = $mod;
}
$stmt->close();

// Fetch lessons for all modules
if (!empty($modules)) {
    $moduleIds = implode(',', array_keys($modules));
    $lessonQuery = $conn->query("
        SELECT id, module_id, title, position
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