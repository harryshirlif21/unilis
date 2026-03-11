<?php
ob_start();
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $err['message'] . ' in ' . basename($err['file']) . ' line ' . $err['line']]);
    }
});

try {
    require_once '../../config/db.php';
    require_once __DIR__ . '/../models/ActivityLog.php';
} catch (Throwable $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Startup error: ' . $e->getMessage()]);
    exit;
}

ob_clean();
header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ── Resolve course_id + year_of_study from session or DB ─────────────────────
$course_id     = (int)($_SESSION['course_id']     ?? 0);
$year_of_study = (int)($_SESSION['year_of_study'] ?? 0);

if (!$course_id || !$year_of_study) {
    $s = $conn->prepare("SELECT course_id, year_of_study FROM students WHERE id = ?");
    $s->bind_param("i", $user_id);
    $s->execute();
    $student = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student record not found']);
        exit;
    }

    $course_id     = (int)$student['course_id'];
    $year_of_study = (int)$student['year_of_study'];
    $_SESSION['course_id']     = $course_id;
    $_SESSION['year_of_study'] = $year_of_study;
}

if (!$course_id || !$year_of_study) {
    echo json_encode(['success' => false, 'message' => 'Could not determine your course or year']);
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
$title           = trim($data['title']           ?? '');
$unit_id         = intval($data['unit_id']        ?? 0);
$assessment_type = trim($data['assessment_type']  ?? '');

if (empty($title) || $unit_id <= 0 || empty($assessment_type)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required (title, unit, assessment type)']);
    exit;
}

// ── Validate assessment type ──────────────────────────────────────────────────
$allowed = ['assignment', 'cat', 'project', 'practical'];
if (!in_array($assessment_type, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid assessment type selected']);
    exit;
}

// ── Verify unit belongs to student's course + year ────────────────────────────
$chk = $conn->prepare("SELECT id FROM units WHERE id = ? AND course_id = ? AND year = ?");
$chk->bind_param("iii", $unit_id, $course_id, $year_of_study);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Selected unit does not belong to your course/year']);
    exit;
}
$chk->close();

// ── Insert team ───────────────────────────────────────────────────────────────
// teams columns: title, unit_id, course_id, assessment_type, created_by, year
$stmt = $conn->prepare("
    INSERT INTO teams (title, unit_id, course_id, assessment_type, created_by, year)
    VALUES (?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// s=title  i=unit_id  i=course_id  s=assessment_type  i=created_by  i=year
$stmt->bind_param("siisii", $title, $unit_id, $course_id, $assessment_type, $user_id, $year_of_study);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    exit;
}

$team_id = $stmt->insert_id;
$stmt->close();

// ── Add creator as leader ─────────────────────────────────────────────────────
$stmt_leader = $conn->prepare("
    INSERT INTO team_members (team_id, student_id, role) VALUES (?, ?, 'leader')
");
if ($stmt_leader) {
    $stmt_leader->bind_param("ii", $team_id, $user_id);
    $stmt_leader->execute();
    $stmt_leader->close();
}

// ── Log activity ──────────────────────────────────────────────────────────────
try {
    $logger = new ActivityLog($conn);
    $detail = sprintf(
        'Team created: "%s" | unit_id=%d | course_id=%d | year=%d | type=%s | by user=%d',
        $title, $unit_id, $course_id, $year_of_study, $assessment_type, $user_id
    );
    $logger->log($team_id, $user_id, 'team_create', $detail);
} catch (Throwable $e) {
    error_log('ActivityLog error: ' . $e->getMessage());
}

// ── Success ───────────────────────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'message' => 'Team created successfully!',
    'team_id' => $team_id
]);