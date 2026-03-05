<?php
// STRICT JSON OUTPUT MODE
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

session_start();
ob_start(); // buffer output

$logFile = __DIR__ . '/add_member_debug.log';
function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

debugLog("=== REQUEST START ===");
debugLog("Session ID: " . session_id());
debugLog("Session contents: " . print_r($_SESSION, true));

// AUTH CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    debugLog("Unauthorized access attempt.");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
debugLog("Authenticated user_id: $userId, role: $userRole");

if ($userRole !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

// READ INPUT
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$teamId     = $input['team_id'] ?? null;
$identifier = trim($input['identifier'] ?? '');
$csrfToken  = $input['csrf_token'] ?? '';

debugLog("Received input: " . json_encode($input));

// VALIDATION
if (!$teamId || !$identifier) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// CSRF CHECK
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    debugLog("CSRF validation failed.");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

debugLog("CSRF validation passed.");

// DATABASE & MODELS
require_once __DIR__ . '/../../config/db.php'; // $conn is mysqli
require_once __DIR__ . '/../controllers/MemberController.php';
require_once __DIR__ . '/../models/ActivityLog.php';

$controller = new MemberController($conn);
$activityLog = new ActivityLog($conn);

try {
    // 1. Find student by reg_no or email
    $stmt = $conn->prepare("SELECT id, name, reg_no, email FROM students WHERE reg_no = ? OR email = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();

    if (!$member) {
        throw new Exception("Member not found");
    }

    $studentId = (int)$member['id']; // <-- FIX: use 'id', not 'student_id'

    // 2. Check if already in team
    $stmt = $conn->prepare("SELECT * FROM team_members WHERE team_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $teamId, $studentId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("Member already in team");
    }

    // 3. Insert member
    $role = 'member';
    $stmt = $conn->prepare("INSERT INTO team_members (team_id, student_id, role, joined_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $teamId, $studentId, $role);
    $stmt->execute();

    debugLog("Member added successfully: $studentId to team $teamId");

    // 4. Log activity (best-effort; failure should not break main flow)
    $detail = sprintf(
        'Added member %d (%s, %s) to team %d by user %d',
        $studentId,
        $member['reg_no'] ?? '',
        $member['email'] ?? '',
        $teamId,
        $userId
    );
    $logged = $activityLog->log(
        (int)$teamId,
        (int)$userId,
        'member_add',
        $detail
    );
    debugLog('Activity log write: ' . ($logged ? 'ok' : 'failed'));

    echo json_encode(['success' => true, 'message' => 'Member added successfully']);

} catch (Exception $e) {
    debugLog("Exception: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

debugLog("=== REQUEST END ===\n");