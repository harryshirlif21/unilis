<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id  = (int)$_SESSION['user_id'];
$lab_id       = isset($_POST['lab_id'])    ? (int)$_POST['lab_id']    : 0;
$unit_id      = isset($_POST['unit_id'])   ? (int)$_POST['unit_id']   : 0;
$module_id    = isset($_POST['module_id']) && $_POST['module_id'] !== '' ? (int)$_POST['module_id'] : null;
$lesson_id    = isset($_POST['lesson_id']) && $_POST['lesson_id'] !== '' ? (int)$_POST['lesson_id'] : null;
$title        = trim($_POST['title']        ?? '');
$mode         = trim($_POST['mode']         ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$html_content = $_POST['html_content']      ?? null;
$due_date     = !empty($_POST['due_date'])  ? $_POST['due_date'] : null;

$valid_modes  = ['pdf_manual', 'fillable_pdf', 'html_worksheet'];
if (!$unit_id || !$title || !in_array($mode, $valid_modes)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']); exit;
}

// Verify lecturer owns unit
$chk = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id=? AND unit_id=?");
$chk->bind_param("ii", $lecturer_id, $unit_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}
$chk->close();

// Handle file upload for pdf modes
$file_path = null;
if (isset($_FILES['lab_file']) && $_FILES['lab_file']['error'] === UPLOAD_ERR_OK) {
    $ext      = strtolower(pathinfo($_FILES['lab_file']['name'], PATHINFO_EXTENSION));
    $allowed  = ['pdf'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF files allowed']); exit;
    }
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/unilis/uploads/labs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $filename  = 'lab_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['lab_file']['name']);
    $dest      = $upload_dir . $filename;
    if (move_uploaded_file($_FILES['lab_file']['tmp_name'], $dest)) {
        $file_path = '/unilis/uploads/labs/' . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'File upload failed']); exit;
    }
}

if ($lab_id) {
    // Keep existing file_path if no new file uploaded
    if (!$file_path) {
        $fp = $conn->prepare("SELECT file_path FROM labs WHERE id=? AND lecturer_id=?");
        $fp->bind_param("ii", $lab_id, $lecturer_id);
        $fp->execute();
        $row = $fp->get_result()->fetch_assoc();
        $fp->close();
        $file_path = $row['file_path'] ?? null;
    }
    $stmt = $conn->prepare("
        UPDATE labs
        SET title=?, mode=?, instructions=?, html_content=?, file_path=?,
            module_id=?, lesson_id=?, due_date=?
        WHERE id=? AND unit_id=? AND lecturer_id=?
    ");
    $stmt->bind_param("sssssiiisii",
        $title, $mode, $instructions, $html_content, $file_path,
        $module_id, $lesson_id, $due_date,
        $lab_id, $unit_id, $lecturer_id
    );
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Lab updated', 'lab_id' => $lab_id]);
} else {
    $stmt = $conn->prepare("
        INSERT INTO labs
            (unit_id, lecturer_id, module_id, lesson_id, title, mode,
             instructions, html_content, file_path, due_date)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param("iiisssssss",
        $unit_id, $lecturer_id, $module_id, $lesson_id, $title, $mode,
        $instructions, $html_content, $file_path, $due_date
    );
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Lab created', 'lab_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
}
