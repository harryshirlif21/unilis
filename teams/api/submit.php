<?php
// teams/api/submit.php - Updated to use team_files table per original roadmap

// Capture any output that might be generated
ob_start();

require_once __DIR__ . '/../../config/db.php';
session_start();

// Check for any output before headers
$output = ob_get_clean();
if (!empty($output)) {
    error_log("Unexpected output before headers: " . $output);
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode([
        'success' => false, 
        'error' => 'Unauthorized - Students only',
        'debug' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_role' => $_SESSION['user_role'] ?? null
        ]
    ]);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid CSRF token'
    ]);
    exit;
}

// Get form data
$team_id = $_POST['team_id'] ?? null;
$submission_title = $_POST['submission_title'] ?? '';
$submission_description = $_POST['submission_description'] ?? '';
$submission_type = $_POST['submission_type'] ?? 'individual';
$student_id = $_SESSION['user_id'];

if (!$team_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Team ID is required'
    ]);
    exit;
}

// Verify student is a member of the team
$memberCheck = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ?");
$memberCheck->bind_param("ii", $team_id, $student_id);
$memberCheck->execute();
$memberResult = $memberCheck->get_result();

if ($memberResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'You are not a member of this team'
    ]);
    exit;
}

$memberData = $memberResult->fetch_assoc();
$student_role = $memberData['role'];
$memberCheck->close();

// Check if team leader is submitting team files
if ($submission_type === 'team' && $student_role !== 'leader') {
    echo json_encode([
        'success' => false,
        'error' => 'Only team leaders can submit team files'
    ]);
    exit;
}

// Check if files were uploaded
if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
    echo json_encode([
        'success' => false,
        'error' => 'No files were uploaded'
    ]);
    exit;
}

try {
    $upload_dir = __DIR__ . '/../../assets/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $uploaded_files = [];
    
    foreach ($_FILES['files']['name'] as $i => $file_name) {
        $file_tmp = $_FILES['files']['tmp_name'][$i];
        $file_error = $_FILES['files']['error'][$i];
        $file_size = $_FILES['files']['size'][$i];
        
        if ($file_error === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_filename = time() . '_' . uniqid() . '.' . $file_extension;
            
            if (move_uploaded_file($file_tmp, $upload_dir . $unique_filename)) {
                // Insert into team_files table as per original roadmap
                $stmt = $conn->prepare("
                    INSERT INTO team_files 
                    (team_id, uploader_id, original_name, stored_name, filepath, file_size, mime_type, uploaded_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                if (!$stmt) {
                    throw new Exception('Error preparing insert query: ' . $conn->error);
                }
                
                $mime_type = mime_content_type($upload_dir . $unique_filename);
                $actual_file_size = filesize($upload_dir . $unique_filename);
                $stmt->bind_param("iisssis", $team_id, $student_id, $file_name, $unique_filename, $unique_filename, $actual_file_size, $mime_type);
                
                if (!$stmt->execute()) {
                    throw new Exception('Error executing team_files insert: ' . $stmt->error);
                }
                
                $file_id = $stmt->insert_id;
                $stmt->close();
                
                // Log activity to team_activity_log
                $activity_log = $conn->prepare("
                    INSERT INTO team_activity_log (team_id, user_id, action_type, action_detail, created_at) 
                    VALUES (?, ?, 'file_upload', ?, NOW())
                ");
                $action_detail = json_encode([
                    'file_id' => $file_id,
                    'original_name' => $file_name,
                    'submission_title' => $submission_title,
                    'submission_description' => $submission_description,
                    'submission_type' => $submission_type
                ]);
                $activity_log->bind_param("iis", $team_id, $student_id, $action_detail);
                $activity_log->execute();
                $activity_log->close();
                
                $uploaded_files[] = [
                    'id' => $file_id,
                    'original_name' => $file_name,
                    'filepath' => $unique_filename,
                    'mime_type' => $mime_type,
                    'file_size' => $actual_file_size
                ];
            } else {
                throw new Exception('Failed to move uploaded file: ' . $file_name);
            }
        } else {
            throw new Exception('File upload error: ' . $file_error);
        }
    }
    
    if (!empty($uploaded_files)) {
        echo json_encode([
            'success' => true,
            'message' => 'Files uploaded successfully!',
            'files' => $uploaded_files
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No files were successfully uploaded'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Submit error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
