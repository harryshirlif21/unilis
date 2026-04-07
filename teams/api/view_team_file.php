<?php
// teams/api/view_team_file.php
// Secure inline file viewer for team members (students) and lecturers.

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    $fileId = (int)($_GET['file_id'] ?? 0);
    if ($fileId <= 0) {
        throw new Exception('Invalid file ID');
    }

    $userId = (int)$_SESSION['user_id'];
    $role = (string)$_SESSION['user_role'];

    if ($role === 'student') {
        $sql = "
            SELECT tf.id, tf.original_name, tf.filepath, tf.mime_type, tf.team_id
            FROM team_files tf
            JOIN team_members tm ON tm.team_id = tf.team_id
            WHERE tf.id = ? AND tm.student_id = ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Query preparation failed: ' . $conn->error);
        $stmt->bind_param("ii", $fileId, $userId);
    } else {
        // Lecturer access (unit ownership)
        $sql = "
            SELECT tf.id, tf.original_name, tf.filepath, tf.mime_type, tf.team_id
            FROM team_files tf
            JOIN teams t ON t.id = tf.team_id
            JOIN lecturer_units lu ON lu.unit_id = t.unit_id
            WHERE tf.id = ? AND lu.lecturer_id = ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Query preparation failed: ' . $conn->error);
        $stmt->bind_param("ii", $fileId, $userId);
    }

    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$file) {
        throw new Exception('File not found or access denied');
    }

    $diskPath = __DIR__ . '/../../assets/uploads/' . $file['filepath'];
    if (!file_exists($diskPath)) {
        throw new Exception('File not found on server');
    }

    $mime = $file['mime_type'] ?: mime_content_type($diskPath);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
    header('Content-Length: ' . filesize($diskPath));
    header('Cache-Control: private, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($diskPath);
    exit;
} catch (Exception $e) {
    http_response_code(404);
    echo 'Error: ' . $e->getMessage();
}

?>

