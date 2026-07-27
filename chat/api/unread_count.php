<?php
/**
 * GET /chat/api/unread_count.php
 *
 * Badge count for the dashboard nav. Deliberately cheap: no group sync, no
 * conversation list - just one indexed count, because every dashboard page
 * polls it.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    chat_json([
        'success' => true,
        'unread_total' => chat_unread_total($conn, $chatUser),
    ]);
} catch (Throwable $e) {
    error_log('chat/unread_count: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load the unread count'], 500);
}
