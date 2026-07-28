<?php
/**
 * Attachment handling for chat.
 *
 * Files are stored outside any path a browser can reach directly and handed out
 * only by chat/api/file.php after a membership check. That matters more here
 * than in a public upload area: a chat attachment is private to its
 * conversation, so a guessable URL would leak it to the whole university.
 */

// Deliberately narrower than the teams module's list. Chat is for coursework
// discussion, not a general file drop, and every extra executable or
// script-bearing type is another way to get something nasty onto disk.
const CHAT_ALLOWED_EXTENSIONS = [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'txt', 'csv', 'md', 'rtf',
    'png', 'jpg', 'jpeg', 'gif', 'webp',
    'zip',
];

// Only these are safe to render in the browser. Anything else downloads.
// SVG is excluded on purpose: it is a document that can carry script, so
// displaying one inline would run the sender's markup on the viewer's origin.
const CHAT_INLINE_MIME = [
    'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf',
];

define('CHAT_MAX_UPLOAD_BYTES', 15 * 1024 * 1024);

/**
 * Absolute path to the attachment store, created on first use.
 */
function chat_upload_dir(): string
{
    $dir = APP_ROOT . '/uploads/chat';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * Human-readable size, for the message bubble.
 */
function chat_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / (1024 * 1024), 1) . ' MB';
}

/**
 * Validate one entry from $_FILES.
 *
 * Returns ['ok' => true, 'ext' => string] or ['ok' => false, 'error' => string].
 */
function chat_validate_upload(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Malformed upload'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['ok' => false, 'error' => 'No file was selected'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['ok' => false, 'error' => 'That file is larger than the server accepts'];
        default:
            return ['ok' => false, 'error' => 'Upload failed (code ' . (int)$file['error'] . ')'];
    }

    if (($file['size'] ?? 0) <= 0) {
        return ['ok' => false, 'error' => 'That file is empty'];
    }
    if ($file['size'] > CHAT_MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'Files are limited to ' . chat_format_bytes(CHAT_MAX_UPLOAD_BYTES)];
    }

    // is_uploaded_file guards against a path being passed off as an upload.
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Upload could not be verified'];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, CHAT_ALLOWED_EXTENSIONS, true)) {
        return [
            'ok' => false,
            'error' => 'That file type is not allowed. Accepted: ' . implode(', ', CHAT_ALLOWED_EXTENSIONS),
        ];
    }

    // A double extension like report.php.pdf passes the check above but would be
    // executed by a mis-configured Apache, so reject any inner PHP-ish part.
    $name = strtolower((string)$file['name']);
    if (preg_match('/\.(php[0-9]?|phtml|phar|htaccess|cgi|pl|py|sh|exe)\b/', $name)) {
        return ['ok' => false, 'error' => 'That filename is not allowed'];
    }

    return ['ok' => true, 'ext' => $ext];
}

/**
 * Move a validated upload into the store.
 *
 * The stored name is random and the original is kept only in the database, so
 * nothing about the path is guessable and a hostile filename never touches the
 * filesystem. Returns the path relative to uploads/chat/.
 */
function chat_store_upload(array $file, string $ext, int $conversationId): ?string
{
    $dir = chat_upload_dir() . '/' . $conversationId;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        error_log('chat_store_upload: could not create ' . $dir);
        return null;
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        error_log('chat_store_upload: move_uploaded_file failed into ' . $dir);
        return null;
    }

    return $conversationId . '/' . $stored;
}

/**
 * MIME type for a validated extension.
 *
 * Derived from the extension we already allow-listed rather than from the
 * browser's Content-Type, which the sender controls and could set to
 * image/png on an HTML file to get it rendered inline.
 */
function chat_mime_for_extension(string $ext): string
{
    static $map = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'md'   => 'text/plain',
        'rtf'  => 'application/rtf',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'zip'  => 'application/zip',
    ];

    return $map[strtolower($ext)] ?? 'application/octet-stream';
}

/**
 * Resolve a stored relative path to an absolute one, refusing anything that
 * escapes the store.
 *
 * The value comes from our own database rather than a request, but a traversal
 * check here is what makes that guarantee hold if a row is ever tampered with.
 */
function chat_resolve_attachment(string $relativePath): ?string
{
    if ($relativePath === '' || str_contains($relativePath, "\0")) {
        return null;
    }

    $base = realpath(chat_upload_dir());
    $full = realpath(chat_upload_dir() . '/' . $relativePath);

    if ($base === false || $full === false) {
        return null;
    }
    if (!str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return is_file($full) ? $full : null;
}
