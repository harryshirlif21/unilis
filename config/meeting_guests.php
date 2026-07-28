<?php
/**
 * Guest access to meetings.
 *
 * The rest of the meeting code answers "may this user join?" with a join: a
 * lecturer owns the meeting, or a student is enrolled in its unit. A guest has
 * neither, so the question here is different - does this request hold the link,
 * and the passcode if there is one.
 *
 * Kept out of config/meeting.php because every page in the LMS loads that file,
 * and only three pages need to be able to open a meeting to the public.
 *
 * Requires config/meeting.php for getMeetingAppBaseUrl() and isMeetingJoinable().
 */

/**
 * Participant ids for guests start here.
 *
 * The meeting room keys every participant by a single integer: the WebSocket
 * server indexes the room by it, and webrtc-core.js decides which side of a
 * peer connection makes the offer by comparing two of them. A guest id that
 * collided with a student id would put two people in one slot, so guests are
 * numbered from a base far above any real students table.
 *
 * A signed 32-bit int tops out at 2147483647, which leaves room for over a
 * billion guest rows above this base.
 */
const MEETING_GUEST_ID_BASE = 1000000000;

/** Where the guest's own identity is kept between requests. */
const MEETING_GUEST_SESSION_KEY = 'meeting_guest_sessions';

/**
 * Whether the guest-access migration has been applied.
 *
 * config/db.php puts mysqli into MYSQLI_REPORT_STRICT, so selecting a column
 * that does not exist throws rather than returning nothing. Every entry point
 * checks this first, so a missing migration reads as an install state instead of
 * a 500.
 */
function meeting_guests_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $result = $conn->query("SHOW TABLES LIKE 'meeting_guests'");
    $hasTable = $result->num_rows > 0;
    $result->free();

    if (!$hasTable) {
        return $ready = false;
    }

    $result = $conn->query("SHOW COLUMNS FROM meetings LIKE 'guest_token'");
    $hasColumn = $result->num_rows > 0;
    $result->free();

    return $ready = $hasColumn;
}

/**
 * The public join URL for a guest token.
 */
function getMeetingGuestUrl(string $token): string
{
    return getMeetingAppBaseUrl() . '/meeting_guest.php?t=' . urlencode($token);
}

/**
 * A passcode a host can read out over a phone line.
 *
 * Six characters from an alphabet with no 0/O or 1/I/L, because the failure mode
 * of a passcode is somebody mishearing it, not somebody brute-forcing six
 * characters they only get to try alongside a link they already have.
 */
function meeting_guest_new_passcode(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $code;
}

/**
 * Turn guest access on, minting a token if the meeting has none.
 *
 * $passcode: a string to set one, '' to clear it, or null to leave it as it is.
 * Returns the meeting's guest token.
 */
function meeting_guest_enable(
    mysqli $conn,
    int $meetingId,
    int $lecturerId,
    bool $listed,
    ?string $passcode = null
): string {
    $stmt = $conn->prepare('SELECT guest_token FROM meetings WHERE id = ? AND lecturer_id = ? LIMIT 1');
    $stmt->bind_param('ii', $meetingId, $lecturerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Meeting not found.');
    }

    $token = (string)($row['guest_token'] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(24));
    }

    $listedFlag = $listed ? 1 : 0;

    if ($passcode === null) {
        $stmt = $conn->prepare(
            'UPDATE meetings SET guest_access = 1, guest_listed = ?, guest_token = ?
             WHERE id = ? AND lecturer_id = ?'
        );
        $stmt->bind_param('isii', $listedFlag, $token, $meetingId, $lecturerId);
    } else {
        $value = $passcode !== '' ? strtoupper(trim($passcode)) : null;
        $stmt = $conn->prepare(
            'UPDATE meetings SET guest_access = 1, guest_listed = ?, guest_token = ?, guest_passcode = ?
             WHERE id = ? AND lecturer_id = ?'
        );
        $stmt->bind_param('issii', $listedFlag, $token, $value, $meetingId, $lecturerId);
    }

    $stmt->execute();
    $stmt->close();

    return $token;
}

/**
 * Turn guest access off.
 *
 * The token is cleared as well as the flag. Leaving it would mean that turning
 * guest access back on later silently re-enables every link that was ever
 * shared - including the one that prompted the host to switch it off.
 */
function meeting_guest_disable(mysqli $conn, int $meetingId, int $lecturerId): void
{
    $stmt = $conn->prepare(
        'UPDATE meetings
         SET guest_access = 0, guest_listed = 0, guest_token = NULL, guest_passcode = NULL
         WHERE id = ? AND lecturer_id = ?'
    );
    $stmt->bind_param('ii', $meetingId, $lecturerId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Issue a new token for a meeting that already has guest access, invalidating
 * every link shared so far.
 *
 * Returns the new token.
 */
function meeting_guest_rotate(mysqli $conn, int $meetingId, int $lecturerId): string
{
    $token = bin2hex(random_bytes(24));

    $stmt = $conn->prepare(
        'UPDATE meetings SET guest_token = ? WHERE id = ? AND lecturer_id = ? AND guest_access = 1'
    );
    $stmt->bind_param('sii', $token, $meetingId, $lecturerId);
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();

    if ($changed === 0) {
        throw new RuntimeException('Guest access is not enabled on that meeting.');
    }

    return $token;
}

/**
 * A meeting with guest access, found by its token, or null.
 *
 * Includes the unit and lecturer names so the join page can show what the
 * session is without a second query.
 */
function meeting_by_guest_token(mysqli $conn, string $token): ?array
{
    $token = trim($token);
    // A token is 48 hex characters. Rejecting anything else here keeps obviously
    // junk values out of the index lookup.
    if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/', $token)) {
        return null;
    }

    $statusSelect = meetingsTableHasColumn($conn, 'meeting_status') ? ', m.meeting_status' : '';
    $endedSelect = meetingsTableHasColumn($conn, 'ended') ? 'COALESCE(m.ended, 0)' : '0';

    $stmt = $conn->prepare("
        SELECT m.id, m.title, m.scheduled_time, m.duration, m.meeting_link,
               m.guest_access, m.guest_listed, m.guest_token, m.guest_passcode,
               {$endedSelect} AS ended{$statusSelect},
               u.name AS unit_name, l.name AS lecturer_name
        FROM meetings m
        LEFT JOIN units u ON m.unit_id = u.id
        LEFT JOIN lecturers l ON m.lecturer_id = l.id
        WHERE m.guest_token = ? AND m.guest_access = 1
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $meeting = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $meeting ?: null;
}

/**
 * Whether a supplied passcode satisfies a meeting.
 *
 * hash_equals rather than ===, so a wrong passcode takes the same time to
 * reject however much of it was right.
 */
function meeting_guest_passcode_ok(array $meeting, ?string $supplied): bool
{
    $expected = trim((string)($meeting['guest_passcode'] ?? ''));
    if ($expected === '') {
        return true;
    }

    return hash_equals($expected, strtoupper(trim((string)$supplied)));
}

/**
 * Record a guest and give them an identity for the room.
 *
 * The session_key is kept in the PHP session, so reloading the join page finds
 * the same row rather than adding another - otherwise one guest who refreshes
 * twice looks like three people in the attendance list.
 *
 * Returns ['id' => int, 'participant_id' => int, 'name' => string, 'session_key' => string].
 */
function meeting_guest_admit(
    mysqli $conn,
    int $meetingId,
    string $name,
    ?string $email,
    ?int $learnerId
): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $name = trim($name);
    if ($name === '') {
        $name = 'Guest';
    }
    $name = mb_substr($name, 0, 120);
    $email = $email !== null && trim($email) !== '' ? mb_substr(trim($email), 0, 190) : null;

    $existingKey = $_SESSION[MEETING_GUEST_SESSION_KEY][$meetingId]['session_key'] ?? null;

    if (is_string($existingKey) && $existingKey !== '') {
        $stmt = $conn->prepare(
            'SELECT id, name FROM meeting_guests WHERE session_key = ? AND meeting_id = ? LIMIT 1'
        );
        $stmt->bind_param('si', $existingKey, $meetingId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // A guest who rejoins under a different name keeps the same row: the
            // host cares that one person was present, not which spelling of
            // their name they used the second time.
            $stmt = $conn->prepare(
                'UPDATE meeting_guests SET name = ?, last_seen_at = NOW() WHERE id = ?'
            );
            $stmt->bind_param('si', $name, $existing['id']);
            $stmt->execute();
            $stmt->close();

            return meeting_guest_identity((int)$existing['id'], $name, $existingKey, $meetingId);
        }
    }

    $sessionKey = bin2hex(random_bytes(24));
    $ip = mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $ipOrNull = $ip !== '' ? $ip : null;

    $stmt = $conn->prepare(
        'INSERT INTO meeting_guests (meeting_id, learner_id, name, email, session_key, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iissss', $meetingId, $learnerId, $name, $email, $sessionKey, $ipOrNull);
    $stmt->execute();
    $guestId = (int)$conn->insert_id;
    $stmt->close();

    return meeting_guest_identity($guestId, $name, $sessionKey, $meetingId);
}

/**
 * Store a guest identity in the session and return it.
 */
function meeting_guest_identity(int $guestId, string $name, string $sessionKey, int $meetingId): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $identity = [
        'id' => $guestId,
        'participant_id' => MEETING_GUEST_ID_BASE + $guestId,
        'name' => $name,
        'session_key' => $sessionKey,
    ];

    if (!isset($_SESSION[MEETING_GUEST_SESSION_KEY]) || !is_array($_SESSION[MEETING_GUEST_SESSION_KEY])) {
        $_SESSION[MEETING_GUEST_SESSION_KEY] = [];
    }
    // Keyed by meeting, so someone who is a guest at two sessions does not lose
    // their identity in the first by joining the second.
    $_SESSION[MEETING_GUEST_SESSION_KEY][$meetingId] = $identity;

    return $identity;
}

/**
 * The guest identity this session already holds for a meeting, or null.
 */
function meeting_guest_current(int $meetingId): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $identity = $_SESSION[MEETING_GUEST_SESSION_KEY][$meetingId] ?? null;

    return is_array($identity) && !empty($identity['session_key']) ? $identity : null;
}

/**
 * Everyone who has joined a meeting as a guest, newest first.
 */
function meeting_guest_list(mysqli $conn, int $meetingId): array
{
    $stmt = $conn->prepare('
        SELECT id, learner_id, name, email, joined_at, last_seen_at, ip_address
        FROM meeting_guests WHERE meeting_id = ? ORDER BY joined_at DESC
    ');
    $stmt->bind_param('i', $meetingId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

/**
 * Guest counts for a set of meetings, as [meeting_id => count].
 *
 * One query for the whole list, so the lecturer's meetings table does not run
 * a count per row.
 */
function meeting_guest_counts(mysqli $conn, array $meetingIds): array
{
    $meetingIds = array_values(array_unique(array_map('intval', $meetingIds)));
    if (!$meetingIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($meetingIds), '?'));
    $stmt = $conn->prepare(
        "SELECT meeting_id, COUNT(*) AS total FROM meeting_guests
         WHERE meeting_id IN ($placeholders) GROUP BY meeting_id"
    );
    $stmt->bind_param(str_repeat('i', count($meetingIds)), ...$meetingIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $counts[(int)$row['meeting_id']] = (int)$row['total'];
    }
    $stmt->close();

    return $counts;
}

/**
 * Sessions advertised to external learners: guest access on, listed on, and not
 * yet over.
 *
 * A meeting that has finished is dropped rather than shown greyed out - a link
 * that cannot be used is worse than no link.
 */
function meeting_guest_listed_sessions(mysqli $conn): array
{
    $statusSelect = meetingsTableHasColumn($conn, 'meeting_status') ? ', m.meeting_status' : '';
    $endedClause = meetingsTableHasColumn($conn, 'ended') ? 'COALESCE(m.ended, 0) = 0' : '1';

    $sql = "
        SELECT m.id, m.title, m.scheduled_time, m.duration, m.guest_token{$statusSelect},
               u.name AS unit_name, l.name AS lecturer_name
        FROM meetings m
        LEFT JOIN units u ON m.unit_id = u.id
        LEFT JOIN lecturers l ON m.lecturer_id = l.id
        WHERE m.guest_access = 1
          AND m.guest_listed = 1
          AND m.guest_token IS NOT NULL
          AND {$endedClause}
          AND DATE_ADD(m.scheduled_time, INTERVAL m.duration MINUTE) >= NOW()
        ORDER BY m.scheduled_time ASC
        LIMIT 50
    ";

    $result = $conn->query($sql);
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    foreach ($rows as &$row) {
        // isMeetingJoinable() is true from well before the start time - the room
        // deliberately lets people in early. So "can I get in" and "has it
        // begun" are two different questions, and a page that shows a session as
        // happening now needs the second one.
        $startsAt = strtotime((string)$row['scheduled_time']);
        $row['is_live'] = isMeetingJoinable($row);
        $row['has_started'] = $startsAt !== false && $startsAt <= time();
        $row['join_url'] = getMeetingGuestUrl((string)$row['guest_token']);
    }
    unset($row);

    return $rows;
}
