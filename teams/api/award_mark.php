<?php
header('Content-Type: application/json');
session_start();

// Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$response = [];

try {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid CSRF token');
    }

    $lecturerId = $_SESSION['user_id'];
    $teamId = (int)($_POST['team_id'] ?? 0);
    $markType = $_POST['mark_type'] ?? '';
    $component = trim($_POST['component'] ?? '');
    $mark = (float)($_POST['mark'] ?? 0);
    $maxMark = (float)($_POST['max_mark'] ?? 100);
    $notes = trim($_POST['notes'] ?? '');
    $studentId = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;

    // Validation
    if ($teamId <= 0) {
        throw new Exception('Invalid team ID');
    }
    
    if (!in_array($markType, ['team', 'individual'])) {
        throw new Exception('Invalid mark type');
    }
    
    if (empty($component)) {
        throw new Exception('Component is required');
    }
    
    if ($mark < 0 || $mark > $maxMark) {
        throw new Exception('Mark must be between 0 and max mark');
    }
    
    if ($markType === 'individual' && (!$studentId || $studentId <= 0)) {
        throw new Exception('Student is required for individual marks');
    }

    // Verify lecturer has access to this team
    $authSql = "
        SELECT 1
        FROM teams t
        JOIN units u ON t.unit_id = u.id
        JOIN lecturer_units lu ON u.id = lu.unit_id
        WHERE t.id = ? AND lu.lecturer_id = ?
        LIMIT 1
    ";
    $authStmt = $conn->prepare($authSql);
    $authStmt->bind_param("ii", $teamId, $lecturerId);
    $authStmt->execute();
    if ($authStmt->get_result()->num_rows === 0) {
        throw new Exception('You do not have access to this team');
    }
    $authStmt->close();

    // If individual mark, verify student is in the team
    if ($markType === 'individual') {
        $studentSql = "
            SELECT 1
            FROM team_members
            WHERE team_id = ? AND student_id = ?
            LIMIT 1
        ";
        $studentStmt = $conn->prepare($studentSql);
        $studentStmt->bind_param("ii", $teamId, $studentId);
        $studentStmt->execute();
        if ($studentStmt->get_result()->num_rows === 0) {
            throw new Exception('Student is not a member of this team');
        }
        $studentStmt->close();
    }

    // Insert the mark
    $insertSql = "
        INSERT INTO team_marks
        (team_id, student_id, awarded_by, mark, max_mark, mark_type, component, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param(
        "iididsss",
        $teamId,
        $studentId,
        $lecturerId,
        $mark,
        $maxMark,
        $markType,
        $component,
        $notes
    );
    
    if (!$insertStmt->execute()) {
        throw new Exception('Failed to award mark: ' . $conn->error);
    }
    $insertStmt->close();

    // Log the activity
    $activityDetail = sprintf(
        '%s mark awarded: %s (%.2f/%.2f)',
        $markType,
        $component,
        $mark,
        $maxMark
    );
    
    $logSql = "
        INSERT INTO team_activity_log
        (team_id, user_id, action_type, action_detail, created_at)
        VALUES (?, ?, 'mark_awarded', ?, NOW())
    ";
    $logStmt = $conn->prepare($logSql);
    $logStmt->bind_param("iis", $teamId, $lecturerId, $activityDetail);
    $logStmt->execute();
    $logStmt->close();

    $response = [
        'success' => true,
        'message' => 'Mark awarded successfully'
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
?>
