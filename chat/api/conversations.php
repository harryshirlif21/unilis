<?php
/**
 * GET /chat/api/conversations.php
 *
 * The caller's conversation list, plus the total unread badge count.
 *
 * This is also where group discovery happens: joining a team or being enrolled
 * on a course puts the group in your list the next time you load chat, without
 * anything having had to notify the chat module. The sync is throttled per
 * session so that a list poll every few seconds does not re-run membership
 * queries; ?force=1 skips the throttle for the refresh button.
 */

require_once __DIR__ . '/_bootstrap.php';

// Kept short enough that a student who just joined a team sees the group
// almost immediately, long enough that polling does not drive the sync.
const CHAT_USER_SYNC_TTL_SECONDS = 60;

try {
    $force = isset($_GET['force']) && $_GET['force'] !== '0';
    $lastSync = (int)($_SESSION['chat_last_sync'] ?? 0);
    $synced = false;

    if ($force || (time() - $lastSync) > CHAT_USER_SYNC_TTL_SECONDS) {
        chat_sync_for_user($conn, $chatUser, $force);
        $_SESSION['chat_last_sync'] = time();
        $synced = true;
    }

    chat_json([
        'success' => true,
        'conversations' => chat_list_conversations($conn, $chatUser),
        'unread_total' => chat_unread_total($conn, $chatUser),
        'synced' => $synced,
        'me' => ['id' => $chatUser['id'], 'role' => $chatUser['role']],
        'csrf_token' => chat_csrf_token(),
    ]);
} catch (Throwable $e) {
    error_log('chat/conversations: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load conversations'], 500);
}
