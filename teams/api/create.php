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
require_once __DIR__ . '/../includes/team_limits.php';
require_once __DIR__ . '/../includes/team_membership.php';
require_once __DIR__ . '/../includes/ensure_team_registrations.php';
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
$max_members     = (int)($data['max_members'] ?? TEAM_MEMBERS_ABSOLUTE_CAP);

if (empty($title) || $unit_id <= 0 || empty($assessment_type)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required (title, unit, assessment type)']);
    exit;
}

if ($max_members < 2 || $max_members > TEAM_MEMBERS_ABSOLUTE_CAP) {
    echo json_encode(['success' => false, 'message' => 'Max members must be between 2 and 15']);
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

// Ensure schema supports configurable team size.
ensure_team_max_members_column($conn);

// ── Insert team ───────────────────────────────────────────────────────────────
// teams columns: title, unit_id, course_id, assessment_type, created_by, year, max_members
$conn->begin_transaction();

try {
    ensure_student_not_in_other_team_for_unit($conn, 0, $user_id, $unit_id);

    $stmt = $conn->prepare("
        INSERT INTO teams (title, unit_id, course_id, assessment_type, created_by, year, max_members)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // s=title  i=unit_id  i=course_id  s=assessment_type  i=created_by  i=year  i=max_members
    $stmt->bind_param("siisiii", $title, $unit_id, $course_id, $assessment_type, $user_id, $year_of_study, $max_members);

    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $team_id = $stmt->insert_id;
    $stmt->close();

    // ── Add creator as leader ─────────────────────────────────────────────────────
    $stmt_leader = $conn->prepare("
        INSERT INTO team_members (team_id, student_id, role) VALUES (?, ?, 'leader')
    ");
    if ($stmt_leader) {
        ensure_student_not_in_other_team_for_unit($conn, $team_id, $user_id);
        $stmt_leader->bind_param("ii", $team_id, $user_id);
        $stmt_leader->execute();
        $stmt_leader->close();
    }

    team_add_registration($conn, (int) $team_id, $unit_id, $assessment_type, $user_id);

    // ── Auto-assign unit lecturer as primary supervisor ────────────────────────
    $lecturerStmt = $conn->prepare("
        SELECT lu.lecturer_id 
        FROM lecturer_units lu
        WHERE lu.unit_id = ?
        LIMIT 1
    ");
    $lecturerStmt->bind_param("i", $unit_id);
    $lecturerStmt->execute();
    $lecturerResult = $lecturerStmt->get_result();
    $unit_lecturer = $lecturerResult->fetch_assoc();
    $lecturerStmt->close();

    if ($unit_lecturer) {
        $supervisorStmt = $conn->prepare("
            INSERT INTO team_supervisors (team_id, lecturer_id, supervisor_type, is_primary, status, requested_by, approved_by, approved_at)
            VALUES (?, ?, 'lecturer', TRUE, 'approved', ?, ?, NOW())
        ");
        $supervisorStmt->bind_param("iiii", $team_id, $unit_lecturer['lecturer_id'], $user_id, $unit_lecturer['lecturer_id']);
        $supervisorStmt->execute();
        $supervisorStmt->close();
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
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