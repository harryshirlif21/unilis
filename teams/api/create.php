<?php
ob_start();
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../../config/db.php';
require_once __DIR__ . '/../models/ActivityLog.php';

ob_clean();
header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (empty($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// ── Inputs ────────────────────────────────────────────────────────────────────
$title           = trim($data['title'] ?? '');
$unit_id         = intval($data['unit_id'] ?? 0);
$assessment_type = trim($data['assessment_type'] ?? '');

if (empty($title) || $unit_id <= 0 || empty($assessment_type)) {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required (title, unit, assessment type)'
    ]);
    exit;
}

// ── Validate assessment type ──────────────────────────────────────────────────
$allowed = ['assignment', 'cat', 'project', 'practical'];
if (!in_array($assessment_type, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid assessment type selected']);
    exit;
}

// ── Insert team ───────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO teams (title, unit_id, assessment_type, created_by)
    VALUES (?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// s=title  i=unit_id  s=assessment_type  i=created_by
$stmt->bind_param("sisi", $title, $unit_id, $assessment_type, $_SESSION['user_id']);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    exit;
}

$team_id = $stmt->insert_id;
$stmt->close();

// ── Add creator as leader ─────────────────────────────────────────────────────
$stmt_leader = $conn->prepare("
    INSERT INTO team_members (team_id, student_id, role)
    VALUES (?, ?, 'leader')
");

if ($stmt_leader) {
    $stmt_leader->bind_param("ii", $team_id, $_SESSION['user_id']);
    $stmt_leader->execute();
    $stmt_leader->close();
}

// ── Log activity (best-effort) ────────────────────────────────────────────────
try {
    $logger = new ActivityLog($conn);
    $detail = sprintf(
        'Team created: "%s" | unit_id=%d | type=%s | by user=%d',
        $title, $unit_id, $assessment_type, (int)$_SESSION['user_id']
    );
    $logger->log($team_id, (int)$_SESSION['user_id'], 'team_create', $detail);
} catch (Throwable $e) {
    error_log('ActivityLog error: ' . $e->getMessage());
}

// ── Success ───────────────────────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'message' => 'Team created successfully!',
    'team_id' => $team_id
]);