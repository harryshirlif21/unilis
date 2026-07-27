<?php
/**
 * POST /chat/api/post_instruction.php
 * { target_type: 'unit'|'course'|'course_year', target_id, year, body,
 *   send_email, csrf_token }
 *
 * A lecturer addresses instructions to a unit or a course. The instruction is
 * always posted into the matching chat channel and raised as an in-app
 * notification; email is opt-in per message via send_email.
 *
 * Delivery is deliberately ordered so a failure cannot lose the instruction:
 * the message is committed first, then notifications, then email. If the
 * mailer is down the instruction is still in the channel and the failure is
 * recorded in chat_instructions rather than being retried into a duplicate.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/chat_notify.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chat_json(['success' => false, 'error' => 'POST required'], 405);
    }
    if ($chatUser['role'] !== 'lecturer') {
        chat_json(['success' => false, 'error' => 'Only lecturers can post instructions'], 403);
    }

    $input = chat_request_input();
    chat_require_csrf($input);

    $targetType = (string)($input['target_type'] ?? '');
    $targetId = (int)($input['target_id'] ?? 0);
    $year = max(0, (int)($input['year'] ?? 0));
    $body = trim((string)($input['body'] ?? ''));
    $sendEmail = !empty($input['send_email']);

    if (!in_array($targetType, ['unit', 'course', 'course_year'], true)) {
        chat_json(['success' => false, 'error' => 'target_type must be unit, course or course_year'], 400);
    }
    if ($targetId <= 0) {
        chat_json(['success' => false, 'error' => 'target_id is required'], 400);
    }
    if ($targetType === 'course_year' && $year <= 0) {
        chat_json(['success' => false, 'error' => 'year is required for a course_year instruction'], 400);
    }
    if ($body === '') {
        chat_json(['success' => false, 'error' => 'Instruction cannot be empty'], 400);
    }
    if (mb_strlen($body) > CHAT_MAX_BODY_LENGTH) {
        chat_json([
            'success' => false,
            'error' => 'Instruction is too long (limit ' . CHAT_MAX_BODY_LENGTH . ' characters)',
        ], 400);
    }

    $lecturerId = (int)$chatUser['id'];
    $subjectLabel = '';

    if ($targetType === 'unit') {
        // Must actually teach it - lecturer_units is the authority, not the
        // list the composer happened to render.
        $stmt = $conn->prepare("
            SELECT u.id, u.code, u.name, u.course_id
            FROM lecturer_units lu
            JOIN units u ON u.id = lu.unit_id
            WHERE lu.lecturer_id = ? AND u.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $lecturerId, $targetId);
        $stmt->execute();
        $unit = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$unit) {
            chat_json(['success' => false, 'error' => 'You do not teach that unit'], 403);
        }

        $subjectLabel = trim((string)$unit['code'] . ' ' . (string)$unit['name']);
        $conversationId = chat_upsert_conversation($conn, 'unit:' . $targetId . ':announce', 'unit_announce', [
            'title' => $subjectLabel . ' · Instructions',
            'unit_id' => $targetId,
            'course_id' => $unit['course_id'] !== null ? (int)$unit['course_id'] : null,
        ]);
    } else {
        // A lecturer may address a course they teach at least one unit on.
        $stmt = $conn->prepare("
            SELECT c.id, c.name
            FROM lecturer_units lu
            JOIN units u ON u.id = lu.unit_id
            JOIN courses c ON c.id = u.course_id
            WHERE lu.lecturer_id = ? AND c.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $lecturerId, $targetId);
        $stmt->execute();
        $course = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$course) {
            chat_json(['success' => false, 'error' => 'You do not teach any unit on that course'], 403);
        }

        $courseName = (string)$course['name'];

        if ($targetType === 'course_year') {
            $subjectLabel = $courseName . ' Year ' . $year;
            $conversationId = chat_upsert_conversation($conn, "course:$targetId:y$year", 'course_year', [
                'title' => $courseName . ' · Year ' . $year,
                'course_id' => $targetId,
                'year_of_study' => $year,
            ]);
        } else {
            $subjectLabel = $courseName;
            $year = 0;
            $conversationId = chat_upsert_conversation($conn, "course:$targetId:all", 'course', [
                'title' => $courseName . ' · All years',
                'course_id' => $targetId,
            ]);
        }
    }

    // Forced: an instruction must reach everyone who qualifies right now, not
    // whoever was a member when the group was last synced.
    chat_sync_conversation_members($conn, $conversationId, true);

    // The lecturer is a member of course groups via chat_expected_members, but
    // a brand new channel may not have listed them yet if they teach no unit on
    // the course any more. Guarantee the sender is present and can post.
    chat_add_participants($conn, $conversationId, [
        ['id' => $lecturerId, 'role' => 'lecturer', 'can_post' => true],
    ]);

    $messageId = chat_send_message($conn, $conversationId, $chatUser, $body, true);

    $recipients = chat_message_recipients($conn, $conversationId, $chatUser);

    $lecturerName = chat_user_profile($conn, $lecturerId, 'lecturer')['name'] ?? 'Your lecturer';
    $title = 'Instructions: ' . $subjectLabel;
    $link = 'chat/views/chat.php?conversation=' . $conversationId;

    $notified = chat_notify_recipients($conn, $recipients, $title, $body, $link);

    $email = ['success' => 0, 'failed' => 0];
    if ($sendEmail) {
        $email = chat_email_recipients(
            $recipients,
            '📢 ' . $title,
            $title,
            $body . "\n\n— " . $lecturerName,
            $link
        );
    }

    $emailRequested = $sendEmail ? 1 : 0;
    $recipientCount = count($recipients);

    $stmt = $conn->prepare("
        INSERT INTO chat_instructions
            (message_id, lecturer_id, target_type, target_id, year_of_study,
             recipient_count, email_requested, emails_sent, emails_failed)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'iisiiiiii',
        $messageId,
        $lecturerId,
        $targetType,
        $targetId,
        $year,
        $recipientCount,
        $emailRequested,
        $email['success'],
        $email['failed']
    );
    $stmt->execute();
    $stmt->close();

    chat_json([
        'success' => true,
        'conversation_id' => $conversationId,
        'message_id' => $messageId,
        'recipients' => $recipientCount,
        'notified' => $notified,
        'emails_sent' => $email['success'],
        'emails_failed' => $email['failed'],
    ]);
} catch (Throwable $e) {
    error_log('chat/post_instruction: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not post the instruction'], 500);
}
