<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$course_id   = (int)($_POST['course_id'] ?? 0);
$description = trim($_POST['description'] ?? '');

if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid course']);
    exit;
}

// Verify access
$check = $conn->prepare("SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? AND is_active = 1");
$check->bind_param("ii", $lecturer_id, $course_id);
$check->execute();
if (!$check->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$check->close();

$stmt = $conn->prepare("UPDATE public_courses SET description = ? WHERE id = ?");
$stmt->bind_param("si", $description, $course_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Description saved']);