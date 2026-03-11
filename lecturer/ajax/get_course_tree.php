<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$unit_id     = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;
$lecturer_id = (int)$_SESSION['user_id'];

if (!$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid unit']);
    exit;
}

// Verify lecturer owns this unit
$check = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
$check->bind_param("ii", $lecturer_id, $unit_id);
$check->execute();
if (!$check->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$check->close();

// Fetch course outline
$outline = null;
$stmt = $conn->prepare("SELECT description, outline FROM course_outlines WHERE unit_id = ? LIMIT 1");
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) $outline = $row;
$stmt->close();

// Fetch modules
$modules = [];
$stmt = $conn->prepare("
    SELECT id, title, position
    FROM course_modules
    WHERE unit_id = ?
    ORDER BY position ASC, id ASC
");
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$result = $stmt->get_result();
while ($mod = $result->fetch_assoc()) {
    $mod['lessons'] = [];
    $modules[$mod['id']] = $mod;
}
$stmt->close();

// Fetch lessons for all modules
if (!empty($modules)) {
    $moduleIds   = implode(',', array_keys($modules));
    $lessonQuery = $conn->query("
        SELECT id, module_id, title, lesson_number, position
        FROM course_lessons
        WHERE module_id IN ($moduleIds)
        ORDER BY position ASC, lesson_number ASC, id ASC
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