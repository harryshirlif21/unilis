<?php
/**
 * GET /chat/api/messages.php?conversation_id=1&since_id=0
 *
 * Messages in a conversation. With since_id it returns only what is newer,
 * which is the shape the poll loop uses; without it, the most recent page.
 *
 * Fetching also advances the caller's read marker to the newest message
 * returned. That makes this GET non-idempotent, which is a deliberate trade:
 * reading a conversation is exactly what "having read it" means here, and the
 * alternative is a second round trip on every poll. Nothing destructive
 * happens, and the marker only ever moves forward.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    $conversationId = (int)($_GET['conversation_id'] ?? 0);
    $sinceId = max(0, (int)($_GET['since_id'] ?? 0));

    if ($conversationId <= 0) {
        chat_json(['success' => false, 'error' => 'conversation_id is required'], 400);
    }

    // Membership is the read permission. A non-member gets 403 whether or not
    // the conversation exists, so this cannot be used to probe for valid ids.
    $participant = chat_participant_row($conn, $conversationId, $chatUser);
    if ($participant === null) {
        chat_json(['success' => false, 'error' => 'You are not a member of this conversation'], 403);
    }

    $messages = chat_fetch_messages($conn, $conversationId, $sinceId);

    if (!empty($messages)) {
        chat_mark_read($conn, $conversationId, $chatUser, (int)end($messages)['id']);
    }

    chat_json([
        'success' => true,
        'conversation_id' => $conversationId,
        'can_post' => (bool)$participant['can_post'],
        'messages' => $messages,
    ]);
} catch (Throwable $e) {
    error_log('chat/messages: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load messages'], 500);
}
