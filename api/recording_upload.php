<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

$response = ['success' => false, 'error' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? 0;
    $meeting_id = intval($input['meeting_id'] ?? 0);
    $recording_data = $input['recording_data'] ?? ''; // Base64 encoded
    $mime_type = sanitizeInput($input['mime_type'] ?? 'video/webm');
    
    if (!$user_id || !$meeting_id || empty($recording_data)) {
        throw new Exception('Missing required parameters');
    }
    
    // Validate lecturer access
    if (!validateUserMeetingAccess($user_id, $meeting_id, 'lecturer')) {
        throw new Exception('Unauthorized to upload recordings');
    }
    
    // Validate MIME type
    $allowed_mimes = ['video/webm', 'video/mp4', 'audio/webm', 'audio/mp4'];
    if (!in_array($mime_type, $allowed_mimes)) {
        throw new Exception('Invalid MIME type');
    }
    
    // Decode base64 data
    $binary_data = base64_decode($recording_data);
    $file_size = strlen($binary_data);
    
    // Limit file size (50MB)
    if ($file_size > 50 * 1024 * 1024) {
        throw new Exception('File size too large');
    }
    
    // Generate filename
    $extension = $mime_type === 'video/webm' ? 'webm' : 
                ($mime_type === 'video/mp4' ? 'mp4' : 
                ($mime_type === 'audio/webm' ? 'webm' : 'mp4'));
    
    $filename = 'recording_' . $meeting_id . '_' . time() . '.' . $extension;
    $upload_path = '../recordings/' . $filename;
    
    // Ensure recordings directory exists
    if (!is_dir('../recordings')) {
        mkdir('../recordings', 0755, true);
    }
    
    // Save file
    if (file_put_contents($upload_path, $binary_data) === false) {
        throw new Exception('Failed to save recording file');
    }
    
    // Create recording record in database
    $sql = "INSERT INTO recordings (meeting_id, file_path, file_name, mime_type, file_size, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $recording_id = executeQuery($sql, [
        $meeting_id, 
        $upload_path, 
        $filename, 
        $mime_type, 
        $file_size, 
        $user_id
    ], "isssii");
    
    if (!$recording_id) {
        // Clean up file if DB insert failed
        unlink($upload_path);
        throw new Exception('Failed to create recording record');
    }
    
    $response = [
        'success' => true,
        'recording_id' => $recording_id,
        'file_path' => $upload_path,
        'file_size' => $file_size
    ];
    
} catch (Exception $e) {
    error_log("Recording upload error: " . $e->getMessage());
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);
?>