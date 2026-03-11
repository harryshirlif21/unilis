<?php
session_start();
require_once '../../config/db.php';
require_once __DIR__ . '/../models/ActivityLog.php';


header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$title           = trim($data['title'] ?? '');
$unit_id         = intval($data['unit_id'] ?? 0);
$assessment_type = trim($data['assessment_type'] ?? '');   // ← changed name

if (empty($title) || $unit_id <= 0 || empty($assessment_type)) {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required (title, unit, assessment type)'
    ]);
    exit;
}

// Optional: validate allowed types
$allowed = ['assignment', 'cat', 'project', 'practical'];
if (!in_array($assessment_type, $allowed)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid assessment type selected'
    ]);
    exit;
}

// Insert (use assessment_type column – see table change below)
$stmt = $conn->prepare("
    INSERT INTO teams 
    (title, unit_id, assessment_type, created_by) 
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("sssi", $title, $unit_id, $assessment_type, $_SESSION['user_id']);

if ($stmt->execute()) {
    $team_id = $stmt->insert_id;

    // Add creator as leader
    $stmt_leader = $conn->prepare("
        INSERT INTO team_members (team_id, student_id, role) 
        VALUES (?, ?, 'leader')
    ");
    $stmt_leader->bind_param("ii", $team_id, $_SESSION['user_id']);
    $stmt_leader->execute();

    // Log activity (best-effort)
    $logger = new ActivityLog($conn);
    $detail = sprintf(
        'Team created with title "%s", unit_id %d, type %s by user %d',
        $title,
        $unit_id,
        $assessment_type,
        (int)$_SESSION['user_id']
    );
    $logger->log($team_id, (int)$_SESSION['user_id'], 'team_create', $detail);

    echo json_encode([
        'success' => true,
        'message' => 'Team created successfully!',
        'team_id' => $team_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
}