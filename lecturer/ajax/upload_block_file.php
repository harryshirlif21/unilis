<?php
// lecturer/ajax/upload_block_file.php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id = $_SESSION['user_id'];
$lesson_id   = intval($_POST['lesson_id'] ?? 0);
$folder      = trim($_POST['folder']      ?? '');

// Whitelist folders
$allowed_folders = ['course_images', 'course_audio', 'course_diagrams'];
if (!in_array($folder, $allowed_folders)) {
    echo json_encode(['success' => false, 'message' => 'Invalid folder']); exit;
}

if (!$lesson_id) {
    echo json_encode(['success' => false, 'message' => 'lesson_id required']); exit;
}

// Verify lecturer owns the lesson
try {
    $stmt = $conn->prepare("
        SELECT cl.id FROM course_lessons cl
        JOIN course_modules cm ON cl.module_id = cm.id
        WHERE cl.id = ? AND cm.lecturer_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $lecturer_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']); exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']); exit;
}

$file     = $_FILES['file'];
$tmp_path = $file['tmp_name'];
$orig_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// MIME + extension whitelist per folder
$rules = [
    'course_images'   => [
        'mimes' => ['image/jpeg','image/png','image/gif','image/webp'],
        'exts'  => ['jpg','jpeg','png','gif','webp'],
        'limit' => 5 * 1024 * 1024  // 5MB
    ],
    'course_audio'    => [
        'mimes' => ['audio/mpeg','audio/mp3','audio/wav','audio/ogg','audio/x-wav'],
        'exts'  => ['mp3','wav','ogg'],
        'limit' => 25 * 1024 * 1024 // 25MB
    ],
    'course_diagrams' => [
        'mimes' => ['image/jpeg','image/png','image/svg+xml','application/pdf','image/gif','image/webp'],
        'exts'  => ['jpg','jpeg','png','svg','pdf','gif','webp'],
        'limit' => 10 * 1024 * 1024 // 10MB
    ],
];

$rule = $rules[$folder];

// Check size
if ($file['size'] > $rule['limit']) {
    $mb = round($rule['limit'] / 1024 / 1024);
    echo json_encode(['success' => false, 'message' => "File too large. Max {$mb}MB."]); exit;
}

// Check extension
if (!in_array($orig_ext, $rule['exts'])) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed']); exit;
}

// Check real MIME via finfo
$finfo     = new finfo(FILEINFO_MIME_TYPE);
$real_mime = $finfo->file($tmp_path);
if (!in_array($real_mime, $rule['mimes'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file content (MIME mismatch)']); exit;
}

// Build destination
$upload_dir = __DIR__ . '/../../uploads/' . $folder . '/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$safe_name = 'blk_' . $lesson_id . '_' . uniqid() . '.' . $orig_ext;
$dest_path = $upload_dir . $safe_name;

if (!move_uploaded_file($tmp_path, $dest_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']); exit;
}

// Return relative path from unilis root
$relative_path = 'uploads/' . $folder . '/' . $safe_name;

echo json_encode([
    'success' => true,
    'path'    => $relative_path,
    'name'    => $file['name'],
    'message' => 'File uploaded successfully'
]);