<?php
/**
 * Live Engagement Module - Session Helper
 * 
 * Manages session-related operations including participant tracking,
 * hand raising, reactions, and session state.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Check if a live session exists
 * 
 * @param int $sessionId
 * @return bool
 */
function le_session_exists(int $sessionId): bool
{
    $db = le_db();
    $result = $db->fetchOne(
        "SELECT 1 FROM live_sessions WHERE id = ? LIMIT 1",
        [$sessionId]
    );
    return $result !== null;
}

/**
 * Get session by code
 * 
 * @param string $code Session code
 * @return array|null
 */
function le_get_session_by_code(string $code): ?array
{
    $db = le_db();
    return $db->fetchOne(
        "SELECT * FROM live_sessions WHERE session_code = ? LIMIT 1",
        [strtoupper(trim($code))]
    );
}

/**
 * Get session details
 * 
 * @param int $sessionId
 * @return array|null
 */
function le_get_session(int $sessionId): ?array
{
    $db = le_db();
    return $db->fetchOne(
        "SELECT * FROM live_sessions WHERE id = ?",
        [$sessionId]
    );
}

/**
 * Update session status
 * 
 * @param int $sessionId
 * @param string $status New status
 * @param bool $updateTimestamps Update start/end times
 * @return bool
 */
function le_update_session_status(int $sessionId, string $status, bool $updateTimestamps = false): bool
{
    $db = le_db();
    $allowedStatuses = ['scheduled', 'active', 'paused', 'ended'];
    
    if (!in_array($status, $allowedStatuses)) {
        return false;
    }
    
    $sql = "UPDATE live_sessions SET status = ?";
    $params = [$status];
    $types = 's';
    
    if ($updateTimestamps) {
        if ($status === 'active') {
            $sql .= ", actual_start = NOW()";
        } elseif ($status === 'ended') {
            $sql .= ", actual_end = NOW()";
        }
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $sessionId;
    $types .= 'i';
    
    return $db->update($sql, $params, $types) !== false;
}

/**
 * Get active participants in a session
 * 
 * @param int $sessionId
 * @param bool $onlyOnline Only get online participants
 * @return array
 */
function le_get_participants(int $sessionId, bool $onlyOnline = false): array
{
    $db = le_db();
    $sql = "SELECT * FROM live_participants WHERE session_id = ?";
    $params = [$sessionId];
    $types = 'i';
    
    if ($onlyOnline) {
        $sql .= " AND is_online = 1";
    }
    
    $sql .= " ORDER BY role ASC, joined_at ASC";
    
    return $db->select($sql, $params, $types) ?? [];
}

/**
 * Register a participant in a session
 * 
 * @param int $sessionId
 * @param int|null $userId
 * @param string $displayName
 * @param string $role
 * @return int|false Participant ID or false
 */
function le_join_session(int $sessionId, ?int $userId, string $displayName, string $role = 'participant', ?int $guestId = null, ?string $email = null)
{
    $db = le_db();
    
    // Check if already registered
    $existing = null;
    if ($userId) {
        $existing = $db->fetchOne(
            "SELECT * FROM live_participants WHERE session_id = ? AND user_id = ?",
            [$sessionId, $userId]
        );
    }
    
    if ($existing) {
        // Update existing record
        $db->update(
            "UPDATE live_participants SET is_online = 1, left_at = NULL, joined_at = NOW() WHERE id = ?",
            [$existing['id']]
        );
        return (int)$existing['id'];
    }
    
    // Build the participant insert from the live schema so a legacy table
    // (with or without guest_id/email/ip_address) can never turn a join into
    // a 500. Only include optional columns that exist on the current
    // deployment. Guest participants have no UNILIS user id, so user_id is
    // inserted as SQL NULL rather than binding 0, which can violate the
    // optional user relationship.
    $columns = [];
    $columnResult = $db->getConnection()->query("SHOW COLUMNS FROM live_participants");
    if ($columnResult) {
        while ($column = $columnResult->fetch_assoc()) {
            $columns[$column['Field']] = true;
        }
        $columnResult->free();
    }

    $insertColumns = [];
    $insertValues = [];
    $params = [];
    $types = '';

    $insertColumns[] = 'session_id';
    $insertValues[] = '?';
    $params[] = $sessionId;
    $types .= 'i';

    $insertColumns[] = 'user_id';
    if ($userId) {
        $insertValues[] = '?';
        $params[] = $userId;
        $types .= 'i';
    } else {
        $insertValues[] = 'NULL';
    }

    $insertColumns[] = 'display_name';
    $insertValues[] = '?';
    $params[] = $displayName;
    $types .= 's';

    $insertColumns[] = 'role';
    $insertValues[] = '?';
    $params[] = $role;
    $types .= 's';

    $insertColumns[] = 'joined_at';
    $insertValues[] = 'NOW()';

    $insertColumns[] = 'is_online';
    $insertValues[] = '1';

    if (!$userId && isset($columns['guest_id'])) {
        $insertColumns[] = 'guest_id';
        $insertValues[] = '?';
        $params[] = $guestId;
        $types .= 'i';
    }
    if (isset($columns['email'])) {
        $insertColumns[] = 'email';
        $insertValues[] = '?';
        $params[] = $email;
        $types .= 's';
    }
    if (isset($columns['ip_address'])) {
        $insertColumns[] = 'ip_address';
        $insertValues[] = '?';
        $params[] = $_SERVER['REMOTE_ADDR'] ?? '';
        $types .= 's';
    }

    return $db->insert(
        'INSERT INTO live_participants (' . implode(', ', $insertColumns) . ')
         VALUES (' . implode(', ', $insertValues) . ')',
        $params,
        $types
    );
}

/**
 * Find the signed-in participant in a session using the current account or guest identity.
 */
function le_get_current_session_participant(int $sessionId, ?int $userId): ?array
{
    $db = le_db();
    if ($userId) {
        return $db->fetchOne(
            "SELECT * FROM live_participants
             WHERE session_id = ? AND user_id = ? AND is_online = 1
             ORDER BY joined_at DESC LIMIT 1",
            [$sessionId, $userId],
            'ii'
        );
    }

    $columns = [];
    $columnResult = $db->getConnection()->query("SHOW COLUMNS FROM live_participants");
    if ($columnResult) {
        while ($column = $columnResult->fetch_assoc()) {
            $columns[$column['Field']] = true;
        }
        $columnResult->free();
    }

    $guestId = (int) ($_SESSION['le_guest_id'] ?? 0);
    if ($guestId && isset($columns['guest_id'])) {
        return $db->fetchOne(
            "SELECT * FROM live_participants
             WHERE session_id = ? AND guest_id = ? AND is_online = 1
             ORDER BY joined_at DESC LIMIT 1",
            [$sessionId, $guestId],
            'ii'
        );
    }

    $email = strtolower(trim((string) le_current_user_email()));
    if ($email !== '' && isset($columns['email'])) {
        return $db->fetchOne(
            "SELECT * FROM live_participants
             WHERE session_id = ? AND LOWER(email) = ? AND is_online = 1
             ORDER BY joined_at DESC LIMIT 1",
            [$sessionId, $email],
            'is'
        );
    }

    return null;
}

/**
 * Read temporary live-room state, discarding expired questions and reactions.
 */
function le_get_live_room_state(int $sessionId): array
{
    return le_access_live_room_state($sessionId);
}

/**
 * Atomically update temporary live-room state shared by the session participants.
 */
function le_update_live_room_state(int $sessionId, callable $update): array
{
    return le_access_live_room_state($sessionId, $update);
}

function le_clear_live_room_state(int $sessionId): void
{
    le_update_live_room_state($sessionId, static function (array $state): array {
        return ['questions' => [], 'reactions' => []];
    });
}

function le_access_live_room_state(int $sessionId, ?callable $update = null): array
{
    if ($sessionId < 1) {
        throw new InvalidArgumentException('A valid session ID is required.');
    }

    $directory = LE_MODULE_PATH . '/storage/live-room-state';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create temporary live-room storage.');
    }

    $path = $directory . '/' . hash('sha256', (string) $sessionId) . '.state';
    $file = fopen($path, 'c+');
    if ($file === false) {
        throw new RuntimeException('Unable to open temporary live-room storage.');
    }

    try {
        if (!flock($file, LOCK_EX)) {
            throw new RuntimeException('Unable to lock temporary live-room storage.');
        }
        rewind($file);
        $contents = stream_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException('Unable to read temporary live-room state.');
        }
        $state = $contents === '' ? ['questions' => [], 'reactions' => []] : json_decode($contents, true);
        if (!is_array($state)
            || !isset($state['questions'], $state['reactions'])
            || !is_array($state['questions'])
            || !is_array($state['reactions'])) {
            throw new RuntimeException('Temporary live-room state is invalid.');
        }

        $now = time();
        $state['questions'] = array_values(array_filter(
            $state['questions'],
            static fn($question): bool => is_array($question) && (int) ($question['expires_at'] ?? 0) > $now
        ));
        $state['reactions'] = array_values(array_filter(
            $state['reactions'],
            static fn($reaction): bool => is_array($reaction) && (int) ($reaction['expires_at'] ?? 0) > $now
        ));

        if ($update !== null) {
            $state = $update($state);
            if (!is_array($state)) {
                throw new RuntimeException('Temporary live-room update returned invalid state.');
            }
        }

        $serialized = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($serialized === false) {
            throw new RuntimeException('Unable to encode temporary live-room state.');
        }
        if ($update !== null || $serialized !== $contents) {
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $serialized) !== strlen($serialized) || !fflush($file)) {
                throw new RuntimeException('Unable to write temporary live-room state.');
            }
        }

        flock($file, LOCK_UN);
        return $state;
    } finally {
        fclose($file);
    }
}

/**
 * Mark a participant as offline (left the session)
 * 
 * @param int $participantId
 * @return bool
 */
function le_leave_session(int $participantId): bool
{
    $db = le_db();
    return $db->update(
        "UPDATE live_participants SET is_online = 0, left_at = NOW(), 
         duration_seconds = TIMESTAMPDIFF(SECOND, joined_at, NOW()) 
         WHERE id = ?",
        [$participantId]
    ) !== false;
}

/**
 * Toggle hand raised for a participant
 * 
 * @param int $participantId
 * @param bool $raised
 * @return bool
 */
function le_set_hand_raised(int $participantId, bool $raised): bool
{
    $db = le_db();
    return $db->update(
        "UPDATE live_participants SET hand_raised = ?, hand_raised_at = ? WHERE id = ?",
        [$raised ? 1 : 0, $raised ? date('Y-m-d H:i:s') : null, $participantId],
        'isi'
    ) !== false;
}

/**
 * Get participants with hand raised
 * 
 * @param int $sessionId
 * @return array
 */
function le_get_raised_hands(int $sessionId): array
{
    $db = le_db();
    return $db->select(
        "SELECT * FROM live_participants WHERE session_id = ? AND hand_raised = 1 AND is_online = 1 
         ORDER BY hand_raised_at ASC",
        [$sessionId]
    ) ?? [];
}

/**
 * Add a reaction to a session
 * 
 * @param int $sessionId
 * @param int|null $userId
 * @param int|null $participantId
 * @param string $reactionType
 * @param string $targetType
 * @param int|null $targetId
 * @return int|false
 */
function le_add_reaction(int $sessionId, ?int $userId, ?int $participantId, string $reactionType, 
                          string $targetType = 'general', ?int $targetId = null)
{
    le_update_live_room_state($sessionId, static function (array $state) use ($participantId, $reactionType, $targetType, $targetId): array {
        $state['reactions'][] = [
            'id' => bin2hex(random_bytes(12)),
            'type' => $reactionType,
            'participant_id' => $participantId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'expires_at' => time() + 120,
        ];
        return $state;
    });
    return 1;
}

/**
 * Get reactions for a session
 * 
 * @param int $sessionId
 * @param string|null $targetType
 * @param int|null $targetId
 * @return array
 */
function le_get_reactions(int $sessionId, ?string $targetType = null, ?int $targetId = null): array
{
    $reactions = le_get_live_room_state($sessionId)['reactions'];
    return array_values(array_filter($reactions, static function (array $reaction) use ($targetType, $targetId): bool {
        return (!$targetType || ($reaction['target_type'] ?? 'general') === $targetType)
            && (!$targetId || (int) ($reaction['target_id'] ?? 0) === $targetId);
    }));
}

/**
 * Get reaction counts grouped by type
 * 
 * @param int $sessionId
 * @return array
 */
function le_get_reaction_counts(int $sessionId): array
{
    $counts = [];
    foreach (le_get_live_room_state($sessionId)['reactions'] as $reaction) {
        $type = $reaction['type'];
        $counts[$type] = ($counts[$type] ?? 0) + 1;
    }
    $result = [];
    foreach ($counts as $type => $count) {
        $result[] = ['reaction_type' => $type, 'count' => $count];
    }
    return $result;
}

/**
 * Create a presentation note
 * 
 * @param int $sessionId
 * @param int $userId
 * @param string $content
 * @param int|null $slideId
 * @return int|false
 */
function le_save_note(int $sessionId, int $userId, string $content, ?int $slideId = null)
{
    $db = le_db();
    
    // Check if note exists for this user/slide
    $existing = $db->fetchOne(
        "SELECT id FROM live_notes WHERE session_id = ? AND user_id = ? AND (slide_id = ? OR (slide_id IS NULL AND ? IS NULL)) LIMIT 1",
        [$sessionId, $userId, $slideId, $slideId],
        'iiii'
    );
    
    if ($existing) {
        return $db->update(
            "UPDATE live_notes SET content = ? WHERE id = ?",
            [$content, $existing['id']],
            'si'
        );
    }
    
    return $db->insert(
        "INSERT INTO live_notes (session_id, user_id, slide_id, content) VALUES (?, ?, ?, ?)",
        [$sessionId, $userId, $slideId, $content],
        'iiis'
    );
}

/**
 * Get notes for a session
 * 
 * @param int $sessionId
 * @param int $userId
 * @return array
 */
function le_get_notes(int $sessionId, int $userId): array
{
    $db = le_db();
    return $db->select(
        "SELECT * FROM live_notes WHERE session_id = ? AND user_id = ? ORDER BY slide_id ASC",
        [$sessionId, $userId]
    ) ?? [];
}

/**
 * Record attendance for a participant
 * 
 * @param int $sessionId
 * @param int $participantId
 * @return bool
 */
function le_record_attendance(int $sessionId, int $participantId): bool
{
    $db = le_db();
    
    // Mark attendance in live_participants
    $db->update(
        "UPDATE live_participants SET attendance_recorded = 1 WHERE id = ?",
        [$participantId]
    );
    
    // Try to record in UNILIS attendance system if available
    $participant = $db->fetchOne(
        "SELECT * FROM live_participants WHERE id = ?",
        [$participantId]
    );
    
    if ($participant && $participant['user_id']) {
        $session = le_get_session($sessionId);
        if ($session && $session['unit_id']) {
            // Check if UNILIS attendance function exists
            if (function_exists('recordStudentAttendance')) {
                try {
                    recordStudentAttendance(
                        $participant['user_id'],
                        $session['unit_id'],
                        'live_session_' . $sessionId
                    );
                } catch (Exception $e) {
                    error_log("Failed to record UNILIS attendance: " . $e->getMessage());
                }
            }
        }
    }
    
    return true;
}

/**
 * Get session statistics
 * 
 * @param int $sessionId
 * @return array
 */
function le_get_session_stats(int $sessionId): array
{
    $db = le_db();
    
    $stats = $db->fetchOne(
        "SELECT * FROM live_statistics WHERE session_id = ? ORDER BY snapshot_time DESC LIMIT 1",
        [$sessionId]
    );
    
    if (!$stats) {
        // Generate basic stats
        $totalParticipants = $db->count('live_participants', 'session_id = ?', [$sessionId]);
        $onlineNow = $db->count('live_participants', 'session_id = ? AND is_online = 1', [$sessionId]);
        $handRaised = $db->count('live_participants', 'session_id = ? AND hand_raised = 1', [$sessionId]);
        
        $stats = [
            'total_participants' => $totalParticipants,
            'peak_concurrent' => $onlineNow,
            'avg_participation_minutes' => null,
            'total_polls_created' => $db->count('live_polls', 'session_id = ?', [$sessionId]),
            'total_poll_responses' => 0,
            'total_quiz_attempts' => 0,
            'total_wordcloud_submissions' => 0,
            'total_open_responses' => 0,
            'total_hand_raises' => $handRaised,
            'total_reactions' => 0,
        ];
    }
    $stats['total_reactions'] = 0;

    return $stats;
}

/**
 * Update session statistics
 * 
 * @param int $sessionId
 * @return bool
 */
function le_update_stats(int $sessionId): bool
{
    $db = le_db();
    
    $totalParticipants = $db->count('live_participants', 'session_id = ?', [$sessionId]);
    $onlineNow = $db->count('live_participants', 'session_id = ? AND is_online = 1', [$sessionId]);
    $totalPolls = $db->count('live_polls', 'session_id = ?', [$sessionId]);
    $totalPollResponses = $db->count('live_poll_responses r JOIN live_polls p ON r.poll_id = p.id', 'p.session_id = ?', [$sessionId]);
    $totalQuizAttempts = $db->count('quiz_attempts a JOIN live_quizzes q ON a.quiz_id = q.id', 'q.session_id = ?', [$sessionId]);
    $totalHandRaised = $db->count('live_participants', 'session_id = ? AND hand_raised = 1', [$sessionId]);
    $totalReactions = 0;
    
    // Calculate engagement score (0-100)
    $engagementScore = 0;
    if ($totalParticipants > 0 && $onlineNow > 0) {
        $participationRate = $onlineNow / max($totalParticipants, 1);
        $interactionCount = $totalPollResponses + $totalQuizAttempts + $totalReactions + $totalHandRaised;
        $engagementScore = min(100, round(($participationRate * 40) + min($interactionCount, 60)));
    }
    
    return $db->insert(
        "INSERT INTO live_statistics 
         (session_id, total_participants, peak_concurrent, total_polls_created, total_poll_responses, 
          total_quiz_attempts, total_hand_raises, total_reactions, engagement_score) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$sessionId, $totalParticipants, $onlineNow, $totalPolls, $totalPollResponses, 
         $totalQuizAttempts, $totalHandRaised, $totalReactions, $engagementScore],
        'iiiiiiiii'
    ) !== false;
}