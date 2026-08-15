<?php
/**
 * Live Engagement Module - Upload API
 *
 * Handles presentation uploads (PDF, PPT, PPTX) and creates the associated
 * live_presentations record plus slide rows for the presenter runtime.
 *
 * @package UNILIS\LiveEngagement\API
 * @version 2.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

le_require_auth();

header('Content-Type: application/json');

if (!le_can_present()) {
    le_error_response('Only lecturers can upload presentations', 403);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        le_error_response('Method not allowed', 405);
    }

    le_require_csrf();

    if (empty($_FILES['file'])) {
        le_error_response('No file uploaded');
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        le_error_response('File upload failed');
    }

    $sessionId = (int) ($_POST['session_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if (!$sessionId) {
        le_error_response('Session is required');
    }

    if ($title === '') {
        $title = pathinfo($file['name'], PATHINFO_FILENAME) ?: 'Uploaded presentation';
    }

    $sessionModel = new \LE\Models\SessionModel();
    $session = $sessionModel->find($sessionId);
    if (!$session) {
        le_error_response('Session not found', 404);
    }

    $userId = le_current_user_id();
    $role = le_current_user_role();
    if ($role !== 'admin' && (int) $session['lecturer_id'] !== $userId) {
        le_error_response('Unauthorized for this session', 403);
    }

    $allowedMimes = array_merge(
        le_config('uploads.allowed_mime_types', []),
        ['application/vnd.ms-powerpoint']
    );

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']) ?: '';
    finfo_close($finfo);

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileType = le_document_file_type($mimeType, $extension);

    if ($fileType === null) {
        le_error_response('Invalid file type. Upload a PDF or PowerPoint (.ppt / .pptx).');
    }

    if (!in_array($mimeType, $allowedMimes, true) && !in_array($extension, ['pdf', 'ppt', 'pptx'], true)) {
        le_error_response('Invalid file type. Upload a PDF or PowerPoint (.ppt / .pptx).');
    }

    $maxSize = (int) le_config('uploads.max_file_size', 50 * 1024 * 1024);
    if ($file['size'] > $maxSize) {
        le_error_response('File too large. Maximum size is 50MB.');
    }

    $uploadDir = __DIR__ . '/../uploads/presentations/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        le_error_response('Upload directory is not writable', 500);
    }

    $filename = uniqid('le_', true) . '.' . $extension;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        le_error_response('Failed to save uploaded file', 500);
    }

    $presModel = new \LE\Models\PresentationModel();
    $slideModel = new \LE\Models\SlideModel();

    // Create the record before building slides so embedded viewers (used for
    // legacy .ppt decks, which can't be unpacked into slides) can reference
    // the final presentation id / served file URL.
    $presId = $presModel->create([
        'session_id' => $sessionId,
        'title' => $title,
        'description' => $description,
        'file_path' => $filename,
        'file_type' => $fileType,
        'file_size' => (int) $file['size'],
        'original_filename' => $file['name'],
        'total_slides' => 1,
        'current_slide' => 1,
        'is_active' => 0,
        'allow_download' => 1,
        'allow_annotations' => 0,
        'created_by' => $userId,
    ]);

    if (!$presId) {
        @unlink($destination);
        le_error_response('Failed to create presentation record', 500);
    }

    $slideDefinitions = le_build_uploaded_slides($destination, $fileType, (int) $presId);
    $totalSlides = max(1, count($slideDefinitions));

    if ($totalSlides > 1) {
        $presModel->update((int) $presId, ['total_slides' => $totalSlides]);
    }

    foreach ($slideDefinitions as $slide) {
        $slideModel->create([
            'presentation_id' => (int) $presId,
            'slide_number' => (int) $slide['slide_number'],
            'content_html' => $slide['content_html'] ?? '',
            'image_path' => $slide['image_path'] ?? null,
            'duration_seconds' => 30,
        ]);
    }

    le_success_response([
        'presentation_id' => (int) $presId,
        'filename' => $filename,
        'original_name' => $file['name'],
        'size' => (int) $file['size'],
        'mime_type' => $mimeType,
        'file_type' => $fileType,
        'total_slides' => $totalSlides,
        'url' => le_presentation_file_url((int) $presId),
    ], 'Presentation uploaded successfully');
} catch (Exception $e) {
    error_log('Upload API error: ' . $e->getMessage());
    le_error_response('Internal server error', 500);
}
