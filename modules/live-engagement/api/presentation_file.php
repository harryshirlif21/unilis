<?php
/**
 * Live Engagement Module - Presentation File Delivery
 *
 * Streams uploaded presentation files. Using PHP avoids the root .htaccess rule
 * that denies direct access to .pptx files.
 *
 * @package UNILIS\LiveEngagement\API
 */

require_once __DIR__ . '/../bootstrap.php';

le_require_auth();

$presentationId = (int) le_get('id', 0, true);
if (!$presentationId) {
    http_response_code(400);
    exit('Missing presentation id');
}

$presModel = new \LE\Models\PresentationModel();
$presentation = $presModel->find($presentationId);

if (!$presentation || empty($presentation['file_path'])) {
    http_response_code(404);
    exit('Presentation file not found');
}

$userId = le_current_user_id();
$role = le_current_user_role();

$sessionModel = new \LE\Models\SessionModel();
$session = $sessionModel->find((int) $presentation['session_id']);

$ownsPresentation = false;
if ($role === 'admin') {
    $ownsPresentation = true;
} elseif (in_array($role, ['lecturer', 'department_admin'], true) && $session) {
    $ownsPresentation = (int) $session['lecturer_id'] === $userId
        || ((int) ($presentation['created_by'] ?? 0) === $userId);
}

$participantAllowed = false;
if (!$ownsPresentation && $session && $userId) {
    $participant = le_db()->fetchOne(
        'SELECT id FROM live_participants WHERE session_id = ? AND user_id = ? AND is_online = 1 LIMIT 1',
        [(int) $session['id'], $userId],
        'ii'
    );
    $participantAllowed = (bool) $participant || (int) $presentation['is_active'] === 1;
}

if (!$ownsPresentation && !$participantAllowed) {
    http_response_code(403);
    exit('Unauthorized');
}

$uploadDir = realpath(__DIR__ . '/../uploads/presentations');
$filePath = realpath(__DIR__ . '/../uploads/presentations/' . basename($presentation['file_path']));

if ($uploadDir === false || $filePath === false || !str_starts_with($filePath, $uploadDir . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('File missing on disk');
}

$mimeTypes = [
    'pdf' => 'application/pdf',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'ppt' => 'application/vnd.ms-powerpoint',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
];

$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($presentation['original_filename'] ?: $filePath) . '"');
header('Cache-Control: private, max-age=3600');

readfile($filePath);
exit;
