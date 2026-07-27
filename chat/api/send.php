<?php
/**
 * POST /chat/api/send.php
 * { conversation_id, body, csrf_token }
 *
 * Post a message to a conversation the caller belongs to.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chat_json(['success' => false, 'error' => 'POST required'], 405);
    }

    $input = chat_request_input();
    chat_require_csrf($input);

    $conversationId = (int)($input['conversation_id'] ?? 0);
    $body = trim((string)($input['body'] ?? ''));

    if ($conversationId <= 0) {
        chat_json(['success' => false, 'error' => 'conversation_id is required'], 400);
    }
    if ($body === '') {
        chat_json(['success' => false, 'error' => 'Message cannot be empty'], 400);
    }
    if (mb_strlen($body) > CHAT_MAX_BODY_LENGTH) {
        chat_json([
            'success' => false,
            'error' => 'Message is too long (limit ' . CHAT_MAX_BODY_LENGTH . ' characters)',
        ], 400);
    }

    $participant = chat_participant_row($conn, $conversationId, $chatUser);
    if ($participant === null) {
        chat_json(['success' => false, 'error' => 'You are not a member of this conversation'], 403);
    }

    // can_post is 0 for students in a unit instruction channel. Lecturers post
    // there through post_instruction.php, which also handles the fan-out.
    if (!$participant['can_post']) {
        chat_json(['success' => false, 'error' => 'This channel is read-only'], 403);
    }

    $messageId = chat_send_message($conn, $conversationId, $chatUser, $body);

    chat_json([
        'success' => true,
        'message_id' => $messageId,
        // Returned so the client can append immediately without another fetch.
        'message' => [
            'id' => $messageId,
            'sender_id' => $chatUser['id'],
            'sender_role' => $chatUser['role'],
            'body' => $body,
            'is_instruction' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    error_log('chat/send: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not send message'], 500);
}
