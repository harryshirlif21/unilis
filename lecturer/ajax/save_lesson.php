<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$lesson_id   = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
$module_id   = isset($_POST['module_id']) ? (int)$_POST['module_id'] : 0;
$unit_id     = isset($_POST['unit_id'])   ? (int)$_POST['unit_id']   : 0;
$title       = trim($_POST['title'] ?? '');

if (!$module_id || !$unit_id || !$title) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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

if ($lesson_id) {
    // UPDATE existing lesson title
    $stmt = $conn->prepare("
        UPDATE course_lessons SET title = ?
        WHERE id = ? AND module_id = ?
    ");
    $stmt->bind_param("sii", $title, $lesson_id, $module_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Lesson renamed', 'lesson_id' => $lesson_id]);

} else {
    // INSERT new lesson
    // Get next lesson_number for this module
    $numStmt = $conn->prepare("
        SELECT COALESCE(MAX(lesson_number), 0) + 1 AS num
        FROM course_lessons WHERE module_id = ?
    ");
    $numStmt->bind_param("i", $module_id);
    $numStmt->execute();
    $lesson_number = (int)$numStmt->get_result()->fetch_assoc()['num'];
    $numStmt->close();

    // Get next position
    $posStmt = $conn->prepare("
        SELECT COALESCE(MAX(position), 0) + 1 AS pos
        FROM course_lessons WHERE module_id = ?
    ");
    $posStmt->bind_param("i", $module_id);
    $posStmt->execute();
    $position = (int)$posStmt->get_result()->fetch_assoc()['pos'];
    $posStmt->close();

    $stmt = $conn->prepare("
        INSERT INTO course_lessons (module_id, unit_id, title, lesson_number, position)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisii", $module_id, $unit_id, $title, $lesson_number, $position);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Lesson added', 'lesson_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
}