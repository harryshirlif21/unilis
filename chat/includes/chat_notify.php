<?php
/**
 * Fan-out for lecturer instructions: in-app notifications and optional email.
 *
 * Loaded on demand by the instruction endpoint rather than from config.php,
 * because pulling in the mailer costs a vendor/autoload on every chat poll
 * otherwise.
 */

/**
 * Write a notification row per recipient.
 *
 * The notifications table is not the same shape in every install - some have
 * user_id/user_role scoping columns and some do not, which is why
 * includes/notifications.php probes for them. This builds its INSERT from the
 * columns that actually exist, so a narrower table degrades to an unscoped
 * notification instead of a fatal error.
 *
 * Returns the number of rows written.
 */
function chat_notify_recipients(
    mysqli $conn,
    array $recipients,
    string $title,
    string $message,
    string $link = ''
): int {
    if (empty($recipients) || !chat_table_exists($conn, 'notifications')) {
        return 0;
    }

    $columns = ['title', 'message'];
    $types = 'ss';

    $scoped = chat_column_exists($conn, 'notifications', 'user_id')
        && chat_column_exists($conn, 'notifications', 'user_role');
    if ($scoped) {
        array_unshift($columns, 'user_id', 'user_role');
        $types = 'is' . $types;
    }

    $hasLink = chat_column_exists($conn, 'notifications', 'link');
    if ($hasLink) {
        $columns[] = 'link';
        $types .= 's';
    }

    $trailing = [];
    if (chat_column_exists($conn, 'notifications', 'is_read')) {
        $trailing[] = 'is_read';
    }
    if (chat_column_exists($conn, 'notifications', 'created_at')) {
        $trailing[] = 'created_at';
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $literals = [];
    foreach ($trailing as $column) {
        $literals[] = $column === 'is_read' ? '0' : 'NOW()';
    }

    $allColumns = array_merge($columns, $trailing);
    $allValues = $placeholders . (empty($literals) ? '' : ', ' . implode(', ', $literals));

    $sql = 'INSERT INTO notifications (`' . implode('`, `', $allColumns) . '`) VALUES (' . $allValues . ')';

    try {
        $stmt = $conn->prepare($sql);
    } catch (Throwable $e) {
        // A NOT NULL column this build does not know about (an older schema
        // with a mandatory assignment_id, for instance) fails here. The chat
        // message itself is already stored, so the instruction is not lost.
        error_log('chat_notify_recipients: could not prepare notification insert: ' . $e->getMessage());
        return 0;
    }

    $written = 0;
    foreach ($recipients as $recipient) {
        $params = [];
        if ($scoped) {
            $params[] = (int)$recipient['id'];
            $params[] = (string)$recipient['role'];
        }
        $params[] = $title;
        $params[] = $message;
        if ($hasLink) {
            $params[] = $link;
        }

        try {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $written++;
        } catch (Throwable $e) {
            error_log('chat_notify_recipients: insert failed: ' . $e->getMessage());
        }
    }
    $stmt->close();

    return $written;
}

/**
 * Email an instruction to recipients, reusing the LMS mail pipeline so that
 * chat mail looks like the rest of the system's mail and honours its config.
 *
 * Returns ['success' => int, 'failed' => int].
 */
function chat_email_recipients(
    array $recipients,
    string $subject,
    string $title,
    string $message,
    string $link = ''
): array {
    $addressed = array_values(array_filter(
        $recipients,
        static fn($r) => !empty($r['email'])
    ));

    if (empty($addressed)) {
        return ['success' => 0, 'failed' => 0];
    }

    $emailSystem = dirname(__DIR__, 2) . '/includes/email_system.php';
    if (!function_exists('send_bulk_notification_emails')) {
        if (!is_file($emailSystem)) {
            error_log('chat_email_recipients: includes/email_system.php is missing');
            return ['success' => 0, 'failed' => count($addressed)];
        }
        require_once $emailSystem;
    }

    try {
        $result = send_bulk_notification_emails($addressed, $subject, $title, $message, $link, 'general');
    } catch (Throwable $e) {
        error_log('chat_email_recipients: bulk send failed: ' . $e->getMessage());
        return ['success' => 0, 'failed' => count($addressed)];
    }

    return [
        'success' => (int)($result['success'] ?? 0),
        'failed' => (int)($result['failed'] ?? 0),
    ];
}
