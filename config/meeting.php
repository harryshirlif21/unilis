<?php
/**
 * Meeting configuration and shared helpers.
 */

/**
 * Application base URL for meeting join/host links.
 */
function getMeetingAppBaseUrl(): string
{
    if (PHP_SAPI === 'cli') {
        return 'http://localhost/unilis';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/:\d+$/', '', $host);

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (preg_match('#^(.*?)/(lecturer|student|admin|api)/#', $script, $matches)) {
        $basePath = $matches[1];
    } else {
        $basePath = rtrim(dirname($script), '/\\');
    }

    return rtrim($scheme . '://' . $host . $basePath, '/');
}

function getMeetingHostUrl(int $meetingId): string
{
    return getMeetingAppBaseUrl() . '/lecturer/meeting_host.php?meeting_id=' . $meetingId;
}

function getMeetingStudentJoinUrl(int $meetingId): string
{
    return getMeetingAppBaseUrl() . '/student/meeting_join.php?meeting_id=' . $meetingId;
}

/**
 * Whether a student can enter the live meeting room.
 */
function meetingsTableHasColumn(mysqli $conn, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $safe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM meetings LIKE '{$safe}'");
    $cache[$column] = $result && $result->num_rows > 0;
    return $cache[$column];
}

/**
 * Whether a student can enter the live meeting room.
 */
function isMeetingJoinable(array $meeting): bool
{
    if (!empty($meeting['ended'])) {
        return false;
    }

    if (isset($meeting['meeting_status']) && $meeting['meeting_status'] !== '') {
        return $meeting['meeting_status'] === 'active';
    }

    $start = strtotime($meeting['scheduled_time'] ?? '');
    $duration = (int)($meeting['duration'] ?? 60);
    if (!$start) {
        return false;
    }

    $end = $start + ($duration * 60);
    $now = time();

    return $now >= $start && $now <= $end;
}

/**
 * Upcoming/live meetings for a student, grouped by enrolled unit.
 *
 * @return array<int, array{unit_id:int, unit_name:string, meetings:array}>
 */
function fetchStudentMeetingsByUnit(mysqli $conn, int $studentId): array
{
    $now = date('Y-m-d H:i:s');
    $grouped = [];
    $statusSelect = meetingsTableHasColumn($conn, 'meeting_status') ? ', m.meeting_status' : '';

    $sql = "
        SELECT m.id, m.title, m.scheduled_time, m.duration, m.meeting_link,
               COALESCE(m.ended, 0) AS ended{$statusSelect},
               u.id AS unit_id, u.name AS unit_name,
               l.name AS lecturer_name
        FROM meetings m
        JOIN units u ON m.unit_id = u.id
        JOIN student_unit_enrollments sue ON sue.unit_id = u.id AND sue.student_id = ?
        LEFT JOIN lecturers l ON m.lecturer_id = l.id
        WHERE COALESCE(m.ended, 0) = 0
          AND DATE_ADD(m.scheduled_time, INTERVAL m.duration MINUTE) >= ?
        ORDER BY u.name ASC, m.scheduled_time ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('is', $studentId, $now);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $unitId = (int)$row['unit_id'];
        if (!isset($grouped[$unitId])) {
            $grouped[$unitId] = [
                'unit_id' => $unitId,
                'unit_name' => $row['unit_name'],
                'meetings' => [],
            ];
        }
        $row['join_url'] = getMeetingStudentJoinUrl((int)$row['id']);
        $row['is_live'] = isMeetingJoinable($row);
        $grouped[$unitId]['meetings'][] = $row;
    }

    $stmt->close();
    return $grouped;
}

/**
 * WebSocket URL for the Python meeting media server.
 *
 * Override with MEETING_MEDIA_WS_URL for production (e.g. wss://your-domain.com/ws/media).
 * Otherwise derives host from the current HTTP request so online tests work without extra setup.
 */
function getMeetingMediaWsUrl(): string
{
    $explicit = getenv('MEETING_MEDIA_WS_URL');
    if ($explicit !== false && $explicit !== '') {
        return $explicit;
    }

    $port = getenv('MEETING_MEDIA_PORT') ?: '8765';
    $path = '/ws/media';

    if (PHP_SAPI === 'cli') {
        return "ws://localhost:{$port}{$path}";
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/:\d+$/', '', $host);

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $isSecure ? 'wss' : 'ws';

    return "{$scheme}://{$host}:{$port}{$path}";
}
