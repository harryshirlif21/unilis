<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$course_id   = (int)($_POST['course_id'] ?? 0);
$module_id   = (int)($_POST['module_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');

if (!$course_id || !$title) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!shortCourseCanManage($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($module_id > 0) {
    // Update existing module
    $stmt = $conn->prepare("UPDATE public_course_modules SET title = ? WHERE id = ? AND course_id = ?");
    $stmt->bind_param("sii", $title, $module_id, $course_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Module renamed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Module not found']);
    }
} else {
    // Create new module
    // Get next position
    $posResult = $conn->query("SELECT COALESCE(MAX(position), -1) + 1 AS next FROM public_course_modules WHERE course_id = $course_id");
    $posRow = $posResult->fetch_assoc();
    $position = (int)$posRow['next'];
    $posResult->free();

    $stmt = $conn->prepare("INSERT INTO public_course_modules (course_id, title, position) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $course_id, $title, $position);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Module added', 'module_id' => $newId]);
}
