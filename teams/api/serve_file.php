<?php
// Secure file serving endpoint
session_start();

// Auth check - only lecturers can serve team files
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    die('Unauthorized access');
}

require_once __DIR__ . '/../../config/db.php';

try {
    $fileId = (int)($_GET['file_id'] ?? 0);
    $submissionId = (int)($_GET['submission_id'] ?? 0);
    $lecturerId = $_SESSION['user_id'];
    
    error_log("File serving request - file_id: $fileId, submission_id: $submissionId, lecturer_id: $lecturerId");
    
    if ($fileId <= 0 && $submissionId <= 0) {
        throw new Exception('Invalid file ID or submission ID');
    }

    if ($submissionId > 0) {
        // Handle submission file
        error_log("Processing submission file with ID: $submissionId");
        
        // Check if new columns exist in submissions table
        $checkColumns = $conn->query("DESCRIBE submissions");
        if (!$checkColumns) {
            throw new Exception('Error checking submissions table structure: ' . $conn->error);
        }
        
        $columns = [];
        while ($row = $checkColumns->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        error_log("Submissions table columns: " . json_encode($columns));
        
        // Build query based on available columns
        $hasNewColumns = in_array('title', $columns) && in_array('description', $columns) && in_array('submission_type', $columns);
        
        if ($hasNewColumns) {
            // New schema with metadata columns
            $sql = "
                SELECT 
                    s.file_path,
                    s.student_id,
                    s.title,
                    s.description,
                    s.submission_type,
                    a.title AS assignment_title
                FROM submissions s
                LEFT JOIN assignments a ON s.assignment_id = a.id
                WHERE s.id = ? AND s.file_path IS NOT NULL AND s.file_path != ''
                LIMIT 1
            ";
            error_log("Using new schema query with metadata columns");
        } else {
            // Old schema - fallback to basic query
            $sql = "
                SELECT 
                    s.file_path,
                    s.student_id,
                    a.title AS assignment_title
                FROM submissions s
                LEFT JOIN assignments a ON s.assignment_id = a.id
                WHERE s.id = ? AND s.file_path IS NOT NULL AND s.file_path != ''
                LIMIT 1
            ";
            error_log("Using old schema query without metadata columns");
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Query preparation failed: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $submissionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("Submission not found for ID: $submissionId");
            throw new Exception('Submission not found or no file associated');
        }
        
        $submission = $result->fetch_assoc();
        $stmt->close();
        
        error_log("Submission found: " . json_encode([
            'file_path' => $submission['file_path'],
            'student_id' => $submission['student_id'],
            'title' => $submission['title'] ?? 'N/A',
            'assignment_title' => $submission['assignment_title'] ?? 'N/A'
        ]));
        
        // Construct the full file path
        $filePath = __DIR__ . '/../../assets/uploads/' . $submission['file_path'];
        $originalName = basename($submission['file_path']);
        
        // Use submission title if available, otherwise use filename
        $displayName = !empty($submission['title']) ? $submission['title'] : $originalName;
        
        // For old schema, ensure we have default values for missing fields
        if (!$hasNewColumns) {
            $submission['title'] = $submission['title'] ?? $originalName;
            $submission['description'] = $submission['description'] ?? null;
            $submission['submission_type'] = $submission['submission_type'] ?? 'individual';
        }
        
    } else {
        // Handle team file (existing logic)
        error_log("Processing team file with ID: $fileId");
        
        $sql = "
            SELECT 
                tf.original_name,
                tf.filepath,
                tf.mime_type,
                tf.team_id
            FROM team_files tf
            JOIN teams t ON tf.team_id = t.id
            JOIN units u ON t.unit_id = u.id
            JOIN lecturer_units lu ON u.id = lu.unit_id
            WHERE tf.id = ? AND lu.lecturer_id = ?
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $fileId, $lecturerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("Team file not found for ID: $fileId");
            throw new Exception('File not found or access denied');
        }
        
        $file = $result->fetch_assoc();
        $stmt->close();
        
        // Construct the full file path
        $filePath = __DIR__ . '/../../assets/uploads/' . $file['filepath'];
        $originalName = $file['original_name'];
        $displayName = $originalName;
    }
    
    error_log("Checking file existence: $filePath");
    
    if (!file_exists($filePath)) {
        // Log the attempted access for debugging
        error_log("File not found: " . $filePath);
        error_log("Looking for file with ID: " . ($fileId > 0 ? $fileId : $submissionId));
        error_log("File path in database: " . ($submission['file_path'] ?? $file['filepath'] ?? 'Not set'));
        
        // Check if uploads directory exists
        $uploadsDir = __DIR__ . '/../../assets/uploads/';
        if (!is_dir($uploadsDir)) {
            error_log("Uploads directory does not exist: " . $uploadsDir);
        }
        
        // List some files in uploads directory for debugging
        if (is_dir($uploadsDir)) {
            $files = scandir($uploadsDir);
            error_log("Files in uploads directory: " . implode(', ', array_slice($files, 0, 10)));
        }
        
        throw new Exception('File not found on server. The file may have been deleted or moved.');
    }

    // Log the file access
    if ($submissionId > 0) {
        // For submissions, we need to get the team_id from the submission
        $teamSql = "
            SELECT tm.team_id
            FROM team_members tm
            WHERE tm.student_id = ?
            LIMIT 1
        ";
        $teamStmt = $conn->prepare($teamSql);
        $teamStmt->bind_param("i", $submission['student_id']);
        $teamStmt->execute();
        $teamResult = $teamStmt->get_result();
        $teamIdForLog = 0;
        
        if ($teamResult->num_rows > 0) {
            $teamRow = $teamResult->fetch_assoc();
            $teamIdForLog = $teamRow['team_id'];
        }
        $teamStmt->close();
        
        error_log("Team ID for logging: $teamIdForLog (submission: $submissionId)");
    } else {
        $teamIdForLog = $file['team_id'] ?? 0;
        error_log("Team ID for logging: $teamIdForLog (team file: $fileId)");
    }
    
    // Only log if we have a valid team_id
    if ($teamIdForLog > 0) {
        // Check if user is lecturer or student to handle foreign key constraints
        $userIsLecturer = $_SESSION['user_role'] === 'lecturer';
        
        if ($userIsLecturer) {
            // For lecturers, we need to check if there's a lecturer-specific activity log table
            // or skip logging if the table only accepts student IDs
            error_log("Skipping activity log for lecturer access - foreign key constraint");
        } else {
            // Only log for students to avoid foreign key constraint issues
            $logSql = "
                INSERT INTO team_activity_log
                (team_id, user_id, action_type, action_detail, created_at)
                VALUES (?, ?, 'file_access', ?, NOW())
            ";
            $logStmt = $conn->prepare($logSql);
            $logDetail = 'File accessed: ' . $displayName;
            $logStmt->bind_param("iis", $teamIdForLog, $lecturerId, $logDetail);
            $logStmt->execute();
            $logStmt->close();
            error_log("Activity logged successfully for student");
        }
    } else {
        error_log("Skipping activity log - no valid team_id found for submission: " . $submissionId);
    }

    // Get MIME type
    if ($submissionId > 0) {
        $mimeType = mime_content_type($filePath);
    } else {
        $mimeType = $file['mime_type'] ?? mime_content_type($filePath);
    }
    
    error_log("File details - Path: $filePath, MIME: $mimeType, Size: " . filesize($filePath));
    
    // Set headers for file download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $displayName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output file
    readfile($filePath);
    exit;

} catch (Exception $e) {
    error_log("File serving error: " . $e->getMessage());
    http_response_code(404);
    echo 'Error: ' . $e->getMessage();
}
?>
