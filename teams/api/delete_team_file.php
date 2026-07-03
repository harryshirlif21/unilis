<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/ActivityLog.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fileId = (int)($input['file_id'] ?? 0);
    $csrfToken = (string)($input['csrf_token'] ?? '');
    $userId = (int)$_SESSION['user_id'];

    if ($fileId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file_id']);
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $sql = "
        SELECT tf.id, tf.team_id, tf.filepath, tf.original_name
        FROM team_files tf
        JOIN team_members tm ON tm.team_id = tf.team_id
        WHERE tf.id = ? AND tm.student_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare file access check: ' . $conn->error);
    }

    $stmt->bind_param('ii', $fileId, $userId);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$file) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'File not found or access denied']);
        exit;
    }

    $teamId = (int)$file['team_id'];
    $dbFilePath = (string)$file['filepath'];
    $originalName = (string)$file['original_name'];
    $diskPath = __DIR__ . '/../../assets/uploads/' . $dbFilePath;

    $deleteStmt = $conn->prepare('DELETE FROM team_files WHERE id = ? LIMIT 1');
    if (!$deleteStmt) {
        throw new Exception('Failed to prepare file delete: ' . $conn->error);
    }

    $deleteStmt->bind_param('i', $fileId);
    $deleteStmt->execute();
    $affected = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($affected <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File could not be deleted']);
        exit;
    }

    if (is_file($diskPath)) {
        @unlink($diskPath);
    }

    $logger = new ActivityLog($conn);
    $logger->log($teamId, $userId, 'file_delete', 'Deleted file: ' . $originalName);

    echo json_encode([
        'success' => true,
        'message' => 'File deleted successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>