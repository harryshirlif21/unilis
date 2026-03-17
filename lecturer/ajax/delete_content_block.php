<?php
// ajax/delete_content_block.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$block_id    = intval($_POST['block_id']  ?? 0);
$lesson_id   = intval($_POST['lesson_id'] ?? 0);

if (!$block_id || !$lesson_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Verify ownership via lecturer_units (works even if cm.lecturer_id is 0)
    $stmt = $conn->prepare("
        SELECT lcb.id FROM lesson_content_blocks lcb
        JOIN course_lessons cl ON lcb.lesson_id = cl.id
        JOIN lecturer_units lu ON lu.unit_id = cl.unit_id
        WHERE lcb.id = ? AND lcb.lesson_id = ? AND lu.lecturer_id = ?
    ");
    $stmt->bind_param("iii", $block_id, $lesson_id, $lecturer_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Block not found']);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM lesson_content_blocks WHERE id = ? AND lesson_id = ?");
    $stmt->bind_param("ii", $block_id, $lesson_id);
    $stmt->execute();
    $stmt->close();

    // Reorder remaining blocks
    $stmt = $conn->prepare("SELECT id FROM lesson_content_blocks WHERE lesson_id = ? ORDER BY position ASC, id ASC");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pos = 0;
    $upd = $conn->prepare("UPDATE lesson_content_blocks SET position = ? WHERE id = ?");
    while ($row = $result->fetch_assoc()) {
        $upd->bind_param("ii", $pos, $row['id']);
        $upd->execute();
        $pos++;
    }
    $upd->close();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Block deleted']);
} catch (mysqli_sql_exception $e) {
    error_log("delete_content_block: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}