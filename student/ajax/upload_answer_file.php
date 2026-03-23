<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$student_id    = intval($_SESSION['user_id']);
$question_id   = intval($_POST['question_id']   ?? 0);
$assessment_id = intval($_POST['assessment_id'] ?? 0);

if (!$question_id || !$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['file']['error'] ?? 'no file';
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $err]);
    exit;
}

// Validate file size (20 MB max)
$max_bytes = 20 * 1024 * 1024;
if ($_FILES['file']['size'] > $max_bytes) {
    echo json_encode(['success' => false, 'message' => 'File exceeds 20 MB limit']);
    exit;
}

// Allowed MIME types
$allowed_mimes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'application/x-zip-compressed',
    'image/jpeg',
    'image/png',
    'image/gif',
    'text/plain',
];

$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $_FILES['file']['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_mimes)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed: ' . $mime_type]);
    exit;
}

// Build upload directory: uploads/answers/{assessment_id}/{student_id}/
$upload_dir = __DIR__ . '/../../uploads/answers/' . $assessment_id . '/' . $student_id . '/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Could not create upload directory']);
        exit;
    }
}

// Safe unique filename
$original_name = basename($_FILES['file']['name']);
$extension     = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$safe_name     = 'q' . $question_id . '_s' . $student_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$dest_path     = $upload_dir . $safe_name;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

// Relative path to store in DB (relative to project root)
$relative_path = 'uploads/answers/' . $assessment_id . '/' . $student_id . '/' . $safe_name;

echo json_encode([
    'success'  => true,
    'path'     => $relative_path,
    'filename' => $original_name,
    'message'  => 'File uploaded successfully'
]);