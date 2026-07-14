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
function le_join_session(int $sessionId, ?int $userId, string $displayName, string $role = 'participant')
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
    
    // Create new participant record
    return $db->insert(
        "INSERT INTO live_participants (session_id, user_id, display_name, role, joined_at, is_online, ip_address) 
         VALUES (?, ?, ?, ?, NOW(), 1, ?)",
        [$sessionId, $userId, $displayName, $role, $_SERVER['REMOTE_ADDR'] ?? ''],
        'iisss'
    );
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
    $db = le_db();
    return $db->insert(
        "INSERT INTO live_reactions (session_id, user_id, session_participant_id, reaction_type, target_type, target_id) 
         VALUES (?, ?, ?, ?, ?, ?)",
        [$sessionId, $userId, $participantId, $reactionType, $targetType, $targetId],
        'iiissi'
    );
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
    $db = le_db();
    $sql = "SELECT * FROM live_reactions WHERE session_id = ?";
    $params = [$sessionId];
    $types = 'i';
    
    if ($targetType) {
        $sql .= " AND target_type = ?";
        $params[] = $targetType;
        $types .= 's';
    }
    
    if ($targetId) {
        $sql .= " AND target_id = ?";
        $params[] = $targetId;
        $types .= 'i';
    }
    
    return $db->select($sql, $params, $types) ?? [];
}

/**
 * Get reaction counts grouped by type
 * 
 * @param int $sessionId
 * @return array
 */
function le_get_reaction_counts(int $sessionId): array
{
    $db = le_db();
    return $db->select(
        "SELECT reaction_type, COUNT(*) as count 
         FROM live_reactions WHERE session_id = ? 
         GROUP BY reaction_type ORDER BY count DESC",
        [$sessionId]
    ) ?? [];
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
            'total_reactions' => $db->count('live_reactions', 'session_id = ?', [$sessionId]),
        ];
    }
    
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
    $totalReactions = $db->count('live_reactions', 'session_id = ?', [$sessionId]);
    
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