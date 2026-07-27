<?php
/**
 * Reading and writing conversations and messages.
 *
 * Sender names are resolved with a pair of LEFT JOINs keyed on sender_role,
 * because a sender id alone does not say which table it came from.
 */

/**
 * Conversations the user belongs to, most recently active first.
 *
 * Direct threads have no stored title - they are named after the other person,
 * resolved in one extra query rather than per row.
 */
function chat_list_conversations(mysqli $conn, array $user): array
{
    $stmt = $conn->prepare("
        SELECT
            c.id, c.type, c.title, c.team_id, c.course_id, c.unit_id,
            c.year_of_study, c.created_at, c.last_message_at,
            p.can_post, p.last_read_message_id, p.muted,
            (
                SELECT COUNT(*) FROM chat_messages m
                WHERE m.conversation_id = c.id
                  AND m.id > p.last_read_message_id
                  AND m.deleted_at IS NULL
                  AND NOT (m.sender_id = ? AND m.sender_role = ?)
            ) AS unread_count,
            (
                SELECT m2.body FROM chat_messages m2
                WHERE m2.conversation_id = c.id AND m2.deleted_at IS NULL
                ORDER BY m2.id DESC LIMIT 1
            ) AS last_body
        FROM chat_participants p
        JOIN chat_conversations c ON c.id = p.conversation_id
        WHERE p.user_id = ? AND p.user_role = ?
        ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
    ");
    $stmt->bind_param('isis', $user['id'], $user['role'], $user['id'], $user['role']);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $directIds = [];
    foreach ($rows as $row) {
        if ($row['type'] === 'direct') {
            $directIds[] = (int)$row['id'];
        }
    }
    $counterparts = chat_direct_counterparts($conn, $directIds, $user);

    $conversations = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $title = $row['type'] === 'direct'
            ? ($counterparts[$id]['name'] ?? 'Direct message')
            : (string)$row['title'];

        $conversations[] = [
            'id' => $id,
            'type' => $row['type'],
            'title' => $title,
            'subtitle' => chat_conversation_subtitle($row, $counterparts[$id] ?? null),
            'can_post' => (bool)$row['can_post'],
            'unread_count' => (int)$row['unread_count'],
            'last_message_at' => $row['last_message_at'],
            'last_body' => $row['last_body'] !== null
                ? mb_substr((string)$row['last_body'], 0, 120)
                : null,
        ];
    }

    return $conversations;
}

/**
 * A one-line description of what a conversation is, for the list.
 */
function chat_conversation_subtitle(array $row, ?array $counterpart): string
{
    switch ($row['type']) {
        case 'direct':
            return $counterpart !== null
                ? ($counterpart['role'] === 'lecturer' ? 'Lecturer' : 'Student')
                : 'Direct message';
        case 'team':
            return 'Team';
        case 'course':
            return 'Course group';
        case 'course_year':
            return 'Classmates';
        case 'unit_announce':
            return 'Instructions';
        default:
            return '';
    }
}

/**
 * The other person in each of the given direct conversations, keyed by
 * conversation id. One query for all of them rather than one per row.
 */
function chat_direct_counterparts(mysqli $conn, array $conversationIds, array $user): array
{
    if (empty($conversationIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
    $types = str_repeat('i', count($conversationIds)) . 'is';
    $params = array_merge($conversationIds, [$user['id'], $user['role']]);

    $stmt = $conn->prepare("
        SELECT
            p.conversation_id, p.user_id, p.user_role,
            COALESCE(s.name, l.name) AS name
        FROM chat_participants p
        LEFT JOIN students s ON p.user_role = 'student' AND s.id = p.user_id
        LEFT JOIN lecturers l ON p.user_role = 'lecturer' AND l.id = p.user_id
        WHERE p.conversation_id IN ($placeholders)
          AND NOT (p.user_id = ? AND p.user_role = ?)
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $counterparts = [];
    foreach ($rows as $row) {
        $counterparts[(int)$row['conversation_id']] = [
            'id' => (int)$row['user_id'],
            'role' => $row['user_role'],
            'name' => (string)($row['name'] ?? 'Unknown user'),
        ];
    }

    return $counterparts;
}

/**
 * Messages in a conversation.
 *
 * $sinceId > 0 fetches only what is newer, which is what polling asks for.
 * Otherwise the most recent page is returned, oldest-first for rendering.
 */
function chat_fetch_messages(mysqli $conn, int $conversationId, int $sinceId = 0, int $limit = CHAT_PAGE_SIZE): array
{
    if ($sinceId > 0) {
        $stmt = $conn->prepare("
            SELECT
                m.id, m.sender_id, m.sender_role, m.body, m.is_instruction, m.created_at,
                COALESCE(s.name, l.name) AS sender_name
            FROM chat_messages m
            LEFT JOIN students s ON m.sender_role = 'student' AND s.id = m.sender_id
            LEFT JOIN lecturers l ON m.sender_role = 'lecturer' AND l.id = m.sender_id
            WHERE m.conversation_id = ? AND m.id > ? AND m.deleted_at IS NULL
            ORDER BY m.id ASC
            LIMIT ?
        ");
        $stmt->bind_param('iii', $conversationId, $sinceId, $limit);
    } else {
        // Newest page first from the database, then reversed below so the
        // caller always receives messages in chronological order.
        $stmt = $conn->prepare("
            SELECT
                m.id, m.sender_id, m.sender_role, m.body, m.is_instruction, m.created_at,
                COALESCE(s.name, l.name) AS sender_name
            FROM chat_messages m
            LEFT JOIN students s ON m.sender_role = 'student' AND s.id = m.sender_id
            LEFT JOIN lecturers l ON m.sender_role = 'lecturer' AND l.id = m.sender_id
            WHERE m.conversation_id = ? AND m.deleted_at IS NULL
            ORDER BY m.id DESC
            LIMIT ?
        ");
        $stmt->bind_param('ii', $conversationId, $limit);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($sinceId <= 0) {
        $rows = array_reverse($rows);
    }

    return array_map(static fn($row) => [
        'id' => (int)$row['id'],
        'sender_id' => (int)$row['sender_id'],
        'sender_role' => $row['sender_role'],
        'sender_name' => (string)($row['sender_name'] ?? 'Unknown user'),
        'body' => (string)$row['body'],
        'is_instruction' => (bool)$row['is_instruction'],
        'created_at' => $row['created_at'],
    ], $rows);
}

/**
 * Store a message and bump the conversation's activity timestamp.
 *
 * Callers are responsible for having checked that the sender may post here;
 * this function does not re-derive permission.
 */
function chat_send_message(
    mysqli $conn,
    int $conversationId,
    array $user,
    string $body,
    bool $isInstruction = false
): int {
    $flag = $isInstruction ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO chat_messages (conversation_id, sender_id, sender_role, body, is_instruction)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iissi', $conversationId, $user['id'], $user['role'], $body, $flag);
    $stmt->execute();
    $messageId = (int)$conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("UPDATE chat_conversations SET last_message_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $conversationId);
    $stmt->execute();
    $stmt->close();

    // Sending is also reading: without this the sender's own message would
    // come back to them as unread on the next conversation-list poll.
    chat_mark_read($conn, $conversationId, $user, $messageId);

    return $messageId;
}

/**
 * Advance the caller's read marker. Never moves it backwards, so an
 * out-of-order poll response cannot resurrect messages as unread.
 */
function chat_mark_read(mysqli $conn, int $conversationId, array $user, int $upToMessageId): void
{
    $stmt = $conn->prepare("
        UPDATE chat_participants
        SET last_read_message_id = ?
        WHERE conversation_id = ? AND user_id = ? AND user_role = ?
          AND last_read_message_id < ?
    ");
    $stmt->bind_param('iiisi', $upToMessageId, $conversationId, $user['id'], $user['role'], $upToMessageId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Total unread messages across every conversation, for the nav badge.
 */
function chat_unread_total(mysqli $conn, array $user): int
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM chat_participants p
        JOIN chat_messages m
          ON m.conversation_id = p.conversation_id
         AND m.id > p.last_read_message_id
         AND m.deleted_at IS NULL
        WHERE p.user_id = ? AND p.user_role = ?
          AND p.muted = 0
          AND NOT (m.sender_id = ? AND m.sender_role = ?)
    ");
    $stmt->bind_param('isis', $user['id'], $user['role'], $user['id'], $user['role']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

/**
 * Members of a conversation, for the header and the members panel.
 */
function chat_conversation_members(mysqli $conn, int $conversationId): array
{
    $stmt = $conn->prepare("
        SELECT
            p.user_id, p.user_role, p.can_post,
            COALESCE(s.name, l.name) AS name,
            s.reg_no
        FROM chat_participants p
        LEFT JOIN students s ON p.user_role = 'student' AND s.id = p.user_id
        LEFT JOIN lecturers l ON p.user_role = 'lecturer' AND l.id = p.user_id
        WHERE p.conversation_id = ?
        ORDER BY p.user_role DESC, name ASC
    ");
    $stmt->bind_param('i', $conversationId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static fn($row) => [
        'id' => (int)$row['user_id'],
        'role' => $row['user_role'],
        'name' => (string)($row['name'] ?? 'Unknown user'),
        'reg_no' => $row['reg_no'],
        'can_post' => (bool)$row['can_post'],
    ], $rows);
}

/**
 * Recipients of a message, excluding the sender - used to fan out notifications
 * and emails for an instruction.
 */
function chat_message_recipients(mysqli $conn, int $conversationId, array $sender): array
{
    $stmt = $conn->prepare("
        SELECT p.user_id, p.user_role, s.name, s.email
        FROM chat_participants p
        JOIN students s ON s.id = p.user_id
        WHERE p.conversation_id = ?
          AND p.user_role = 'student'
          AND NOT (p.user_id = ? AND p.user_role = ?)
    ");
    $stmt->bind_param('iis', $conversationId, $sender['id'], $sender['role']);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static fn($row) => [
        'id' => (int)$row['user_id'],
        'role' => $row['user_role'],
        'name' => (string)($row['name'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
    ], $rows);
}
