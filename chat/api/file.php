<?php
/**
 * GET /chat/api/file.php?message_id=12
 *
 * Serve a chat attachment to a member of its conversation.
 *
 * Attachments are addressed by message id, not by path, and every request
 * re-checks membership. That is the whole point of routing bytes through PHP:
 * a chat attachment is private to its conversation, so a URL that worked for
 * anyone who received it would leak coursework across the university.
 *
 * This endpoint does not use _bootstrap.php, because that one commits to a JSON
 * response and this one answers with a file.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

/**
 * Fail with a plain-text status. Bodies are terse on purpose: this endpoint is
 * reached by <img> and download links, not by code that parses errors.
 */
function chat_file_fail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message . "\n";
    exit;
}

$chatUser = chat_current_user();
if ($chatUser === null) {
    chat_file_fail(401, 'Unauthorized');
}

if (!chat_schema_ready($conn) || !chat_schema_supports_attachments($conn)) {
    chat_file_fail(503, 'File sharing is not set up on this installation.');
}

$messageId = (int)($_GET['message_id'] ?? 0);
if ($messageId <= 0) {
    chat_file_fail(400, 'message_id is required');
}

try {
    $stmt = $conn->prepare("
        SELECT conversation_id, attachment_path, attachment_name, attachment_mime, attachment_size
        FROM chat_messages
        WHERE id = ? AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$message || empty($message['attachment_path'])) {
        chat_file_fail(404, 'Not found');
    }

    // Membership is the permission. Answering 404 rather than 403 keeps this
    // from confirming which message ids exist to someone probing.
    if (chat_participant_row($conn, (int)$message['conversation_id'], $chatUser) === null) {
        chat_file_fail(404, 'Not found');
    }

    $absolute = chat_resolve_attachment((string)$message['attachment_path']);
    if ($absolute === null) {
        error_log('chat/file: row ' . $messageId . ' points at a missing or out-of-store file');
        chat_file_fail(404, 'Not found');
    }

    $mime = (string)$message['attachment_mime'];
    $name = (string)$message['attachment_name'];

    // Only a short allowlist renders in the browser. Everything else downloads,
    // so a document that could carry script never executes on this origin.
    $inline = in_array($mime, CHAT_INLINE_MIME, true);

    header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
    header(
        'Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
        // rawurlencode handles non-ASCII names, which a bare filename= mangles.
        . '; filename="' . preg_replace('/[^\x20-\x7e]/', '_', $name) . '"'
        . "; filename*=UTF-8''" . rawurlencode($name)
    );
    header('Content-Length: ' . (string)filesize($absolute));
    // Belt and braces against a sniffing browser overriding the type above.
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; sandbox');
    header('Cache-Control: private, max-age=300');

    readfile($absolute);
    exit;
} catch (Throwable $e) {
    error_log('chat/file: ' . $e->getMessage());
    chat_file_fail(500, 'Could not read the file');
}
