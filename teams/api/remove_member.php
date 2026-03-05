<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);

// ----------------------
// SESSION & DEV LOGIN
// ----------------------
session_start();

// TEMPORARY FOR DEVELOPMENT
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 3;
    $_SESSION['role'] = 'student';
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----------------------
// REQUIRE FILES
// ----------------------
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../controllers/MemberController.php';
require_once __DIR__ . '/../models/ActivityLog.php';

$response = [];

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $csrfToken = $input['csrf_token'] ?? '';

    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        throw new Exception("Invalid CSRF token");
    }

    if (empty($input['team_id']) || empty($input['student_id'])) {
        throw new Exception("team_id and student_id are required");
    }

    $controller = new MemberController($pdo);
    $result = $controller->removeMember($input);

    // Best-effort activity logging; if it fails we still return the main result.
    if (isset($conn) && $conn) {
        $logger = new ActivityLog($conn);
        $teamId = (int)$input['team_id'];
        $studentId = (int)$input['student_id'];
        $detail = sprintf(
            'Removed member %d from team %d by user %d',
            $studentId,
            $teamId,
            (int)$_SESSION['user_id']
        );
        $logger->log($teamId, (int)$_SESSION['user_id'], 'member_remove', $detail);
    }

    $response = $result;

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);