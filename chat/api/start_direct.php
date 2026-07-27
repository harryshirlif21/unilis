<?php
/**
 * POST /chat/api/start_direct.php
 * { user_id, user_role, csrf_token }
 *
 * Open the direct thread with someone, creating it on first use. Calling this
 * twice returns the same conversation - the thread's identity is derived from
 * the sorted pair of participants, not from who opened it.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chat_json(['success' => false, 'error' => 'POST required'], 405);
    }

    $input = chat_request_input();
    chat_require_csrf($input);

    $targetId = (int)($input['user_id'] ?? 0);
    $targetRole = (string)($input['user_role'] ?? '');

    if ($targetId <= 0 || !in_array($targetRole, ['student', 'lecturer'], true)) {
        chat_json(['success' => false, 'error' => 'A valid user_id and user_role are required'], 400);
    }

    // Re-checked here rather than trusting that the id came from the directory:
    // the directory is only the UI's view of this rule.
    if (!chat_can_contact($conn, $chatUser, $targetId, $targetRole)) {
        chat_json([
            'success' => false,
            'error' => 'You can only message people you share a unit, team or course with, and your lecturers',
        ], 403);
    }

    $conversationId = chat_direct_conversation(
        $conn,
        $chatUser,
        ['id' => $targetId, 'role' => $targetRole]
    );

    $profile = chat_user_profile($conn, $targetId, $targetRole);

    chat_json([
        'success' => true,
        'conversation_id' => $conversationId,
        'title' => $profile['name'] ?? 'Direct message',
    ]);
} catch (Throwable $e) {
    error_log('chat/start_direct: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not open the conversation'], 500);
}
