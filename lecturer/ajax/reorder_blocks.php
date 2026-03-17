<?php
// ajax/reorder_blocks.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lesson_id   = intval($_POST['lesson_id'] ?? 0);
$order_json  = $_POST['order'] ?? '[]';
$order       = json_decode($order_json, true);

if (!$lesson_id || !is_array($order) || empty($order)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Verify ownership via lecturer_units (works even if cm.lecturer_id is 0)
    $stmt = $conn->prepare("
        SELECT cl.id FROM course_lessons cl
        JOIN lecturer_units lu ON lu.unit_id = cl.unit_id
        WHERE cl.id = ? AND lu.lecturer_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $lecturer_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE lesson_content_blocks SET position = ? WHERE id = ? AND lesson_id = ?
    ");
    foreach ($order as $pos => $bid) {
        $p = intval($pos);
        $b = intval($bid);
        $stmt->bind_param("iii", $p, $b, $lesson_id);
        $stmt->execute();
    }
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Blocks reordered']);
} catch (mysqli_sql_exception $e) {
    error_log("reorder_blocks: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}