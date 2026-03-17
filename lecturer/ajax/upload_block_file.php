<?php
// lecturer/ajax/upload_block_file.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id = $_SESSION['user_id'];
$lesson_id   = intval($_POST['lesson_id'] ?? 0);
$folder      = trim($_POST['folder']      ?? '');

// Whitelist folders
$allowed_folders = [
    'course_images',
    'course_audio',
    'course_diagrams',
    'course_videos',
    'course_pdfs',
];
if (!in_array($folder, $allowed_folders)) {
    echo json_encode(['success' => false, 'message' => 'Invalid folder']); exit;
}

if (!$lesson_id) {
    echo json_encode(['success' => false, 'message' => 'lesson_id required']); exit;
}

// Verify lecturer owns the lesson via lecturer_units (works even if cm.lecturer_id is 0)
try {
    $stmt = $conn->prepare("
        SELECT cl.id FROM course_lessons cl
        JOIN lecturer_units lu ON lu.unit_id = cl.unit_id
        WHERE cl.id = ? AND lu.lecturer_id = ?
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
    $code = $_FILES['file']['error'] ?? -1;
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit. Ask your host to raise upload_max_filesize (currently ' . ini_get('upload_max_filesize') . ') and post_max_size (currently ' . ini_get('post_max_size') . ').',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded — try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was received by the server.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temp directory.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk (permissions issue).',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension on the server.',
    ];
    $msg = $msgs[$code] ?? "Upload error (PHP code $code).";
    echo json_encode(['success' => false, 'message' => $msg]); exit;
}

$file     = $_FILES['file'];
$tmp_path = $file['tmp_name'];
$orig_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// ── MIME + extension whitelist per folder ─────────────────────────────────
$rules = [
    'course_images'   => [
        'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'exts'  => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'limit' => 5 * 1024 * 1024,   // 5 MB
    ],
    'course_audio'    => [
        'mimes' => ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/x-wav', 'audio/x-m4a', 'audio/mp4'],
        'exts'  => ['mp3', 'wav', 'ogg', 'm4a'],
        'limit' => 25 * 1024 * 1024,  // 25 MB
    ],
    'course_diagrams' => [
        'mimes' => ['image/jpeg', 'image/png', 'image/svg+xml', 'image/gif', 'image/webp'],
        'exts'  => ['jpg', 'jpeg', 'png', 'svg', 'gif', 'webp'],
        'limit' => 10 * 1024 * 1024,  // 10 MB
    ],
    'course_videos'   => [
        'mimes' => [
            'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
            'video/x-msvideo', 'video/x-matroska', 'video/3gpp', 'video/3gpp2',
            'video/x-flv', 'video/x-ms-wmv', 'video/mpeg',
            'application/octet-stream', // many servers report this for all binary files
        ],
        'exts'  => ['mp4', 'webm', 'ogv', 'ogg', 'mov', 'avi', 'mkv', '3gp', 'flv', 'wmv', 'mpeg', 'mpg', 'm4v'],
        'limit' => 500 * 1024 * 1024, // 500 MB
    ],
    'course_pdfs'     => [
        'mimes' => ['application/pdf'],
        'exts'  => ['pdf'],
        'limit' => 50 * 1024 * 1024,  // 50 MB
    ],
];

$rule = $rules[$folder];

// ── Size check ────────────────────────────────────────────────────────────
if ($file['size'] > $rule['limit']) {
    $mb = round($rule['limit'] / 1024 / 1024);
    echo json_encode(['success' => false, 'message' => "File too large. Max {$mb}MB."]); exit;
}

// ── Extension check ───────────────────────────────────────────────────────
if (!in_array($orig_ext, $rule['exts'])) {
    echo json_encode(['success' => false, 'message' => "File extension .{$orig_ext} not allowed for this block type."]); exit;
}

// ── Real MIME check via finfo ─────────────────────────────────────────────
$finfo     = new finfo(FILEINFO_MIME_TYPE);
$real_mime = $finfo->file($tmp_path);

// Video files frequently report unreliable MIME types depending on the server's
// magic database. For course_videos, if the extension is whitelisted we trust it.
// We still run the MIME check for images, audio, and PDFs where it's reliable.
if ($folder === 'course_videos') {
    // Extension already verified above — skip MIME check for video
    // Just ensure it's not obviously something dangerous (e.g. PHP disguised as mp4)
    $dangerous_mimes = [
        'application/x-php', 'text/x-php', 'application/x-httpd-php',
        'text/html', 'application/javascript', 'text/x-shellscript',
        'application/x-sh', 'text/x-perl',
    ];
    if (in_array($real_mime, $dangerous_mimes)) {
        echo json_encode(['success' => false, 'message' => 'File rejected: dangerous content type detected.']); exit;
    }
    // All good — extension is whitelisted and content is not dangerous
} else {
    // For non-video types, enforce strict MIME match
    if (!in_array($real_mime, $rule['mimes'])) {
        echo json_encode(['success' => false, 'message' => "File type mismatch. Detected: {$real_mime}. Expected one of: " . implode(', ', $rule['mimes'])]); exit;
    }
}

// ── Build destination ─────────────────────────────────────────────────────
$upload_dir = __DIR__ . '/../../uploads/' . $folder . '/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Could not create upload directory']); exit;
    }
}

$safe_name = 'blk_' . $lesson_id . '_' . uniqid() . '.' . $orig_ext;
$dest_path = $upload_dir . $safe_name;

if (!move_uploaded_file($tmp_path, $dest_path)) {
    error_log("upload_block_file: move_uploaded_file failed — src=$tmp_path dst=$dest_path");
    echo json_encode(['success' => false, 'message' => 'Failed to save file to server']); exit;
}

// ── Return relative path from unilis root ─────────────────────────────────
$relative_path = 'uploads/' . $folder . '/' . $safe_name;

echo json_encode([
    'success' => true,
    'path'    => $relative_path,
    'name'    => $file['name'],
    'size'    => $file['size'],
    'mime'    => $real_mime,
    'message' => 'File uploaded successfully',
]);