<?php
// teams/api/get_submissions.php - Get team files for a team

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_access.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['student', 'lecturer', 'admin', 'technician'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

$team_id = $_GET['team_id'] ?? null;
if (!$team_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Team ID is required'
    ]);
    exit;
}

try {
    if (!team_user_can_access_team($conn, (int) $team_id, (int) $_SESSION['user_id'], $_SESSION['user_role'] ?? '')) {
        echo json_encode([
            'success' => false,
            'error' => 'Access denied for this team'
        ]);
        exit;
    }

    // Get team files from team_files table
    $stmt = $conn->prepare("
        SELECT 
            tf.id,
            tf.filepath,
            tf.original_name,
            tf.mime_type,
            tf.uploaded_at,
            tf.uploader_id,
            tf.file_size,
            s.name AS student_name,
            s.reg_no
        FROM team_files tf
        LEFT JOIN students s ON tf.uploader_id = s.id
        WHERE tf.team_id = ? 
        ORDER BY tf.uploaded_at DESC
    ");
    
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        // Get submission metadata from activity log
        $activityStmt = $conn->prepare("
            SELECT action_detail 
            FROM team_activity_log 
            WHERE team_id = ? AND user_id = ? AND action_type = 'file_upload' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $activityStmt->bind_param("ii", $team_id, $row['uploader_id']);
        $activityStmt->execute();
        $activityResult = $activityStmt->get_result();
        $activityRow = $activityResult->fetch_assoc();
        $activityStmt->close();
        
        $metadata = [];
        if ($activityRow) {
            $detail = json_decode($activityRow['action_detail'], true);
            if ($detail) {
                $metadata = [
                    'title' => $detail['submission_title'] ?? '',
                    'description' => $detail['submission_description'] ?? '',
                    'submission_type' => $detail['submission_type'] ?? 'individual'
                ];
            }
        }
        
        $submissions[] = array_merge($row, $metadata);
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'submissions' => $submissions
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
