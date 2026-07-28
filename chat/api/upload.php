<?php
/**
 * POST /chat/api/upload.php   (multipart/form-data)
 * fields: conversation_id, csrf_token, body (optional), file
 *
 * Send a file to a conversation, optionally with a caption. The attachment and
 * the message are one row: a chat message either has text, a file, or both.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/chat_files.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chat_json(['success' => false, 'error' => 'POST required'], 405);
    }

    // Multipart, so the token arrives as a form field rather than in a JSON body.
    chat_require_csrf($_POST);

    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    $body = trim((string)($_POST['body'] ?? ''));

    if ($conversationId <= 0) {
        chat_json(['success' => false, 'error' => 'conversation_id is required'], 400);
    }
    if (mb_strlen($body) > CHAT_MAX_BODY_LENGTH) {
        chat_json(['success' => false, 'error' => 'Caption is too long'], 400);
    }

    $participant = chat_participant_row($conn, $conversationId, $chatUser);
    if ($participant === null) {
        chat_json(['success' => false, 'error' => 'You are not a member of this conversation'], 403);
    }
    if (!$participant['can_post']) {
        chat_json(['success' => false, 'error' => 'You cannot post in this conversation'], 403);
    }

    if (!isset($_FILES['file'])) {
        chat_json(['success' => false, 'error' => 'No file was uploaded'], 400);
    }

    $check = chat_validate_upload($_FILES['file']);
    if (!$check['ok']) {
        chat_json(['success' => false, 'error' => $check['error']], 400);
    }

    if (!chat_schema_supports_attachments($conn)) {
        chat_json([
            'success' => false,
            'error' => 'File sharing is not set up yet. An administrator needs to run migrate_chat_attachments.php.',
            'code' => 'attachments_missing',
        ], 503);
    }

    $stored = chat_store_upload($_FILES['file'], $check['ext'], $conversationId);
    if ($stored === null) {
        chat_json(['success' => false, 'error' => 'The file could not be saved'], 500);
    }

    $originalName = (string)$_FILES['file']['name'];
    $size = (int)$_FILES['file']['size'];
    // Trust the extension we validated over the browser-supplied type, which is
    // attacker-controlled and only used to decide inline vs download.
    $mime = chat_mime_for_extension($check['ext']);

    $messageId = chat_send_message(
        $conn,
        $conversationId,
        $chatUser,
        $body,
        false,
        [
            'path' => $stored,
            'name' => $originalName,
            'size' => $size,
            'mime' => $mime,
        ]
    );

    chat_json([
        'success' => true,
        'message_id' => $messageId,
        'attachment' => [
            'name' => $originalName,
            'size' => $size,
            'size_label' => chat_format_bytes($size),
            'mime' => $mime,
        ],
    ]);
} catch (Throwable $e) {
    error_log('chat/upload: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not send the file'], 500);
}
