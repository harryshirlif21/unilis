<?php
// lecturer/ajax/save_content_block.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id = $_SESSION['user_id'];
$lesson_id   = intval($_POST['lesson_id']  ?? 0);
$block_id    = intval($_POST['block_id']   ?? 0);
$block_type  = trim($_POST['block_type']   ?? '');
$content     = $_POST['content']           ?? '';
$position    = intval($_POST['position']   ?? 0);

// Supported lesson content block types.
$allowed_types = ['text', 'image', 'video', 'audio', 'diagram', 'pdf', 'ppt'];

if (!$lesson_id || !in_array($block_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => "Invalid parameters (type: $block_type)"]); exit;
}

try {
    // Verify lecturer owns this lesson via lecturer_units (works even if cm.lecturer_id is 0)
    $stmt = $conn->prepare("
        SELECT cl.id FROM course_lessons cl
        JOIN lecturer_units lu ON lu.unit_id = cl.unit_id
        WHERE cl.id = ? AND lu.lecturer_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $lecturer_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found or access denied']); exit;
    }
    $stmt->close();

    if ($block_id) {
        // UPDATE existing block
        $stmt = $conn->prepare("
            UPDATE lesson_content_blocks
            SET block_type = ?, content = ?, position = ?
            WHERE id = ? AND lesson_id = ?
        ");
        $stmt->bind_param("ssiii", $block_type, $content, $position, $block_id, $lesson_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Block updated', 'block_id' => $block_id]);

    } else {
        // INSERT new block
        // FIX: use isset check on $_POST rather than falsy check on $position
        // so position=0 is respected (first block)
        if (!isset($_POST['position']) || $_POST['position'] === '') {
            $stmt = $conn->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM lesson_content_blocks WHERE lesson_id = ?");
            $stmt->bind_param("i", $lesson_id);
            $stmt->execute();
            $stmt->bind_result($position);
            $stmt->fetch();
            $stmt->close();
        }

        $stmt = $conn->prepare("
            INSERT INTO lesson_content_blocks (lesson_id, block_type, content, position)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("issi", $lesson_id, $block_type, $content, $position);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Block created', 'block_id' => $new_id]);
    }

} catch (mysqli_sql_exception $e) {
    error_log("save_content_block: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
