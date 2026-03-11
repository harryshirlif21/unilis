<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$block_id    = isset($_POST['block_id'])  ? (int)$_POST['block_id']  : 0;
$lesson_id   = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
$block_type  = trim($_POST['block_type'] ?? '');
$content     = $_POST['content'] ?? '';
$position    = isset($_POST['position'])  ? (int)$_POST['position']  : 0;

$valid_types = ['text', 'image', 'video', 'audio', 'diagram'];
if (!$lesson_id || !in_array($block_type, $valid_types)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']); exit;
}

// Verify lecturer owns this lesson's unit (no unit_id needed from client)
$chk = $conn->prepare("
    SELECT lu.unit_id FROM course_lessons cl
    JOIN course_modules cm ON cl.module_id = cm.id
    JOIN lecturer_units lu ON cm.unit_id = lu.unit_id
    WHERE cl.id = ? AND lu.lecturer_id = ?
");
$chk->bind_param("ii", $lesson_id, $lecturer_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}
$chk->close();

if ($block_id) {
    $stmt = $conn->prepare("
        UPDATE lesson_content_blocks
        SET block_type=?, content=?, position=?
        WHERE id=? AND lesson_id=?
    ");
    $stmt->bind_param("ssiii", $block_type, $content, $position, $block_id, $lesson_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Block updated', 'block_id' => $block_id]);
} else {
    if (!$position) {
        $ps = $conn->prepare("SELECT COALESCE(MAX(position),0)+1 AS p FROM lesson_content_blocks WHERE lesson_id=?");
        $ps->bind_param("i", $lesson_id);
        $ps->execute();
        $position = (int)$ps->get_result()->fetch_assoc()['p'];
        $ps->close();
    }
    $stmt = $conn->prepare("
        INSERT INTO lesson_content_blocks (lesson_id, block_type, content, position)
        VALUES (?,?,?,?)
    ");
    $stmt->bind_param("issi", $lesson_id, $block_type, $content, $position);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Block added', 'block_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
}