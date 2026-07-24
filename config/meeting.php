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

function isInternalMeetingUrl(string $url): bool
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return true;
    }

    $path = parse_url($trimmed, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return false;
    }

    $normalized = '/' . ltrim(str_replace('\\', '/', $path), '/');
    $internalPaths = [
        '/meeting_ide.php',
        '/lecturer/meeting_host.php',
        '/student/meeting_join.php',
        '/lecturer/meeting_ide.php',
        '/student/meeting_ide.php',
    ];

    foreach ($internalPaths as $internalPath) {
        if (substr($normalized, -strlen($internalPath)) === $internalPath) {
            return true;
        }
    }

    return false;
}

function hasLaunchableMeetingLink(?string $url): bool
{
    return $url !== null && trim($url) !== '' && !isInternalMeetingUrl($url);
}

function isLocalMeetingHost(string $host): bool
{
    $normalized = strtolower(trim($host));
    return in_array($normalized, ['localhost', '127.0.0.1', '::1'], true);
}

function normalizeMeetingPythonBaseUrl(string $baseUrl): string
{
    $trimmed = trim($baseUrl);
    if ($trimmed === '') {
        return '';
    }

    $trimmed = rtrim($trimmed, '/');
    $parseTarget = $trimmed;
    $hasScheme = preg_match('#^[a-z][a-z0-9+.-]*://#i', $parseTarget) === 1;
    if (!$hasScheme && strpos($parseTarget, '//') !== 0) {
        // Accept values like "example.com:8765" by adding a temporary scheme for parsing.
        $parseTarget = 'https://' . $parseTarget;
    }

    $parts = parse_url($parseTarget);
    if (!is_array($parts) || empty($parts['host'])) {
        return $trimmed;
    }

    $scheme = $hasScheme ? ($parts['scheme'] ?? 'https') : 'https';
    $host = $parts['host'];
    $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
    $port = $parts['port'] ?? null;

    // Production should prefer the proxied app domain over direct :8765 access.
    if ($port === 8765 && !isLocalMeetingHost($host)) {
        return $scheme . '://' . $host . $path;
    }

    return $trimmed;
}

function getMeetingPythonAppBaseUrl(): string
{
    $explicit = getenv('MEETING_PYTHON_APP_URL');
    if ($explicit !== false && $explicit !== '') {
        return normalizeMeetingPythonBaseUrl($explicit);
    }

    if (PHP_SAPI === 'cli') {
        return 'http://127.0.0.1:8765';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/:\d+$/', '', $host);

    if (isLocalMeetingHost($host)) {
        return $scheme . '://' . $host . ':8765';
    }

    return $scheme . '://' . $host;
}

function buildMeetingPythonUiUrl(
    string $role,
    array $meeting,
    int $userId,
    string $displayName,
    string $backUrl
): string {
    $path = $role === 'lecturer' ? '/meeting-ui/host' : '/meeting-ui/join';
    $params = [
        'meeting_id' => (int)($meeting['id'] ?? 0),
        'user_id' => $userId,
        'role' => $role,
        'display_name' => $displayName,
        'title' => (string)($meeting['title'] ?? ''),
        'unit_name' => (string)($meeting['unit_name'] ?? ''),
        'lecturer_name' => (string)($meeting['lecturer_name'] ?? ''),
        'scheduled_time' => (string)($meeting['scheduled_time'] ?? ''),
        'duration' => (int)($meeting['duration'] ?? 0),
        'external_link' => (string)($meeting['meeting_link'] ?? ''),
        'back_url' => $backUrl,
    ];

    return getMeetingPythonAppBaseUrl() . $path . '?' . http_build_query($params);
}

/**
 * Build URL for the new Google Meet-style frontend.
 * Serves the meeting page directly from PHP assets.
 */
function buildMeetingFrontendUrl(
    string $role,
    array $meeting,
    int $userId,
    string $displayName,
    string $backUrl
): string {
    $params = [
        'meeting_id' => (int)($meeting['id'] ?? 0),
        'user_id' => $userId,
        'role' => $role,
        'display_name' => $displayName,
        'title' => (string)($meeting['title'] ?? ''),
        'unit_name' => (string)($meeting['unit_name'] ?? ''),
        'lecturer_name' => (string)($meeting['lecturer_name'] ?? ''),
        'scheduled_time' => (string)($meeting['scheduled_time'] ?? ''),
        'duration' => (int)($meeting['duration'] ?? 0),
        'external_link' => (string)($meeting['meeting_link'] ?? ''),
        'back_url' => $backUrl,
    ];

    return getMeetingAppBaseUrl() . '/assets/meetings/meeting.html?' . http_build_query($params);
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

function getStudentEnrollmentTable(mysqli $conn): ?string
{
    static $table = null;
    static $resolved = false;

    if ($resolved) {
        return $table;
    }

    foreach (['student_unit_enrollments', 'student_units'] as $candidate) {
        $safe = $conn->real_escape_string($candidate);
        $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
        if ($result && $result->num_rows > 0) {
            $table = $candidate;
            $resolved = true;
            return $table;
        }
    }

    $resolved = true;
    return null;
}

/**
 * Whether a student can enter the live meeting room.
 * Students can join anytime from the scheduled time onwards (including before if not ended).
 */
function isMeetingJoinable(array $meeting): bool
{
    // Meeting has ended - cannot join
    if (!empty($meeting['ended'])) {
        return false;
    }

    // Check explicit meeting status
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

    // Students can join anytime starting from the scheduled time,
    // even if they join early (before the meeting technically starts)
    // They just can't join after the meeting duration has elapsed.
    return $now <= $end;
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
    $enrollmentTable = getStudentEnrollmentTable($conn);

    if ($enrollmentTable === null) {
        return [];
    }

    $sql = "
        SELECT m.id, m.title, m.scheduled_time, m.duration, m.meeting_link,
               COALESCE(m.ended, 0) AS ended{$statusSelect},
               u.id AS unit_id, u.name AS unit_name,
               l.name AS lecturer_name
        FROM meetings m
        JOIN units u ON m.unit_id = u.id
        JOIN {$enrollmentTable} sue ON sue.unit_id = u.id AND sue.student_id = ?
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

    if (isLocalMeetingHost($host)) {
        return "{$scheme}://{$host}:{$port}{$path}";
    }

    return "{$scheme}://{$host}{$path}";
}
