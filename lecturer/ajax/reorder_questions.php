<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id   = (int)$_SESSION['user_id'];
$assessment_id = isset($_POST['assessment_id']) ? (int)$_POST['assessment_id'] : 0;
$order         = json_decode($_POST['order'] ?? '[]', true);

if (!$assessment_id || !is_array($order) || empty($order)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
}

// Verify ownership
$chk = $conn->prepare("SELECT 1 FROM assessments WHERE id=? AND lecturer_id=?");
$chk->bind_param("ii", $assessment_id, $lecturer_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}
$chk->close();

// questions table has no explicit position column — we use a workaround:
// rebuild by updating created_at offsets won't work cleanly.
// Instead we use a temp position stored via a multi-row UPDATE using CASE.
// Build CASE WHEN ... THEN ... END
$cases  = '';
$ids    = [];
foreach ($order as $pos => $qId) {
    $qId     = (int)$qId;
    $posVal  = $pos + 1;
    $cases  .= " WHEN $qId THEN $posVal";
    $ids[]   = $qId;
}
$idList = implode(',', $ids);

// Add a position column if it doesn't exist yet (safe — runs once, ignored if exists)
$conn->query("ALTER TABLE questions ADD COLUMN IF NOT EXISTS position INT NOT NULL DEFAULT 0");

$conn->query("UPDATE questions SET position = CASE id $cases END WHERE id IN ($idList) AND assignment_id = $assessment_id");

echo json_encode(['success' => true, 'message' => 'Question order saved']);
