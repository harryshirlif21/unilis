<?php
/**
 * Live Engagement Module - Upload API
 * 
 * Handles file uploads for presentations and other resources.
 * 
 * @package UNILIS\LiveEngagement\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
le_require_auth();

header('Content-Type: application/json');

$userId = le_current_user_id();
$role = le_current_user_role();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        le_error_response('Method not allowed', 405);
    }

    if (empty($_FILES)) {
        le_error_response('No files uploaded');
    }

    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        le_error_response('File upload failed');
    }

    // Validate file type
    $allowedTypes = ['application/pdf', 'application/vnd.ms-powerpoint', 
                     'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                     'image/jpeg', 'image/png', 'image/gif'];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        le_error_response('Invalid file type. Only PDF, PPT, PPTX, and images are allowed.');
    }

    // Validate file size (max 50MB)
    $maxSize = 50 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        le_error_response('File too large. Maximum size is 50MB.');
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('le_') . '.' . $extension;
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/presentations/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Move uploaded file
    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        le_error_response('Failed to save uploaded file');
    }

    // Return file info
    le_success_response([
        'filename' => $filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'mime_type' => $mimeType,
        'url' => le_module_url('uploads/presentations/' . $filename),
    ], 'File uploaded successfully');

} catch (Exception $e) {
    error_log("Upload API error: " . $e->getMessage());
    le_error_response('Internal server error', 500);
}
