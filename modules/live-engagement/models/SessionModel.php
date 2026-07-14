<?php
/**
 * Live Engagement Module - Session Model
 * 
 * Manages live session lifecycle, creation, and management.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

/**
 * SessionModel - Core session management
 */
class SessionModel extends BaseModel
{
    protected string $table = 'live_sessions';
    
    protected array $fillable = [
        'title', 'description', 'session_code', 'course_id', 'unit_id',
        'meeting_id', 'lecturer_id', 'team_id', 'status', 'session_type',
        'allow_anonymous', 'allow_recording', 'max_participants',
        'scheduled_start', 'duration_minutes', 'passcode', 'is_template',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Create a new live session
     * 
     * @param array $data Session data
     * @return int|false
     */
    public function createSession(array $data)
    {
        $data['session_code'] = $data['session_code'] ?? \le_generate_session_code();
        $data['lecturer_id'] = $data['lecturer_id'] ?? \le_current_user_id();
        
        return $this->create($data);
    }

    /**
     * Get active sessions for a lecturer
     * 
     * @param int $lecturerId
     * @return array
     */
    public function getLecturerActiveSessions(int $lecturerId): array
    {
        return $this->db->select(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM live_participants p WHERE p.session_id = s.id AND p.is_online = 1) as online_count,
                    (SELECT COUNT(*) FROM live_participants p WHERE p.session_id = s.id) as total_participants
             FROM live_sessions s
             WHERE s.lecturer_id = ? AND s.status IN ('active', 'paused')
             ORDER BY s.updated_at DESC",
            [$lecturerId],
            'i'
        ) ?? [];
    }

    /**
     * Get session history for a lecturer
     * 
     * @param int $lecturerId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLecturerHistory(int $lecturerId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->select(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM live_participants p WHERE p.session_id = s.id) as total_participants,
                    (SELECT engagement_score FROM live_statistics st WHERE st.session_id = s.id ORDER BY st.snapshot_time DESC LIMIT 1) as engagement_score
             FROM live_sessions s
             WHERE s.lecturer_id = ? AND s.status = 'ended'
             ORDER BY s.actual_end DESC
             LIMIT ? OFFSET ?",
            [$lecturerId, $limit, $offset],
            'iii'
        ) ?? [];
    }

    /**
     * Get sessions available for a student (by course/unit enrollment)
     * 
     * @param int $studentId
     * @return array
     */
    public function getStudentAvailableSessions(int $studentId): array
    {
        // Get units the student is enrolled in
        $enrolledUnits = $this->db->select(
            "SELECT unit_id FROM student_unit WHERE student_id = ?
             UNION 
             SELECT unit_id FROM student_unit_enrollments WHERE student_id = ?",
            [$studentId, $studentId],
            'ii'
        );

        if (empty($enrolledUnits)) {
            return [];
        }

        $unitIds = array_column($enrolledUnits, 'unit_id');
        $placeholders = implode(',', array_fill(0, count($unitIds), '?'));

        return $this->db->select(
            "SELECT s.*, u.name as unit_name,
                    (SELECT COUNT(*) FROM live_participants p WHERE p.session_id = s.id AND p.is_online = 1) as online_count
             FROM live_sessions s
             LEFT JOIN units u ON s.unit_id = u.id
             WHERE (s.unit_id IN ({$placeholders}) OR s.course_id IN (
                 SELECT course_id FROM units WHERE id IN ({$placeholders})
             ))
             AND s.status IN ('active', 'scheduled')
             ORDER BY s.status ASC, s.scheduled_start ASC",
            array_merge($unitIds, $unitIds)
        ) ?? [];
    }

    /**
     * Get session details with related counts
     * 
     * @param int $sessionId
     * @return array|null
     */
    public function getSessionWithStats(int $sessionId): ?array
    {
        $session = $this->find($sessionId);
        if (!$session) {
            return null;
        }

        $session['participant_count'] = $this->db->count(
            'live_participants', 'session_id = ?', [$sessionId]
        );
        $session['online_count'] = $this->db->count(
            'live_participants', 'session_id = ? AND is_online = 1', [$sessionId]
        );
        $session['poll_count'] = $this->db->count(
            'live_polls', 'session_id = ?', [$sessionId]
        );
        $session['quiz_count'] = $this->db->count(
            'live_quizzes', 'session_id = ?', [$sessionId]
        );
        $session['reaction_count'] = $this->db->count(
            'live_reactions', 'session_id = ?', [$sessionId]
        );

        // Get latest statistics
        $stats = $this->db->fetchOne(
            "SELECT * FROM live_statistics WHERE session_id = ? ORDER BY snapshot_time DESC LIMIT 1",
            [$sessionId],
            'i'
        );
        $session['statistics'] = $stats;

        return $session;
    }

    /**
     * Find session by join code
     * 
     * @param string $code
     * @return array|null
     */
    public function findByCode(string $code): ?array
    {
        return $this->db->fetchOne(
            "SELECT s.*, u.name as unit_name, l.name as lecturer_name
             FROM live_sessions s
             LEFT JOIN units u ON s.unit_id = u.id
             LEFT JOIN lecturers l ON s.lecturer_id = l.id
             WHERE s.session_code = ? LIMIT 1",
            [strtoupper(trim($code))]
        );
    }

    /**
     * Start a session (set to active)
     * 
     * @param int $sessionId
     * @return bool
     */
    public function start(int $sessionId): bool
    {
        return \le_update_session_status($sessionId, 'active', true);
    }

    /**
     * Pause a session
     * 
     * @param int $sessionId
     * @return bool
     */
    public function pause(int $sessionId): bool
    {
        return \le_update_session_status($sessionId, 'paused');
    }

    /**
     * End a session
     * 
     * @param int $sessionId
     * @return bool
     */
    public function end(int $sessionId): bool
    {
        $result = \le_update_session_status($sessionId, 'ended', true);
        
        if ($result) {
            // Leave all participants
            $this->db->update(
                "UPDATE live_participants SET is_online = 0, left_at = NOW(),
                 duration_seconds = TIMESTAMPDIFF(SECOND, joined_at, NOW())
                 WHERE session_id = ? AND is_online = 1",
                [$sessionId],
                'i'
            );
            
            // Close all active polls
            $this->db->update(
                "UPDATE live_polls SET is_active = 0, is_closed = 1 WHERE session_id = ? AND is_active = 1",
                [$sessionId],
                'i'
            );
            
            // Final stats snapshot
            \le_update_stats($sessionId);
        }
        
        return $result;
    }

    /**
     * Search sessions
     * 
     * @param string $query Search term
     * @param int $userId User ID (for scoping)
     * @param string $role User role
     * @return array
     */
    public function search(string $query, int $userId, string $role): array
    {
        $searchTerm = '%' . $query . '%';
        
        if ($role === 'lecturer') {
            return $this->db->select(
                "SELECT * FROM live_sessions 
                 WHERE lecturer_id = ? AND (title LIKE ? OR session_code LIKE ? OR description LIKE ?)
                 ORDER BY created_at DESC LIMIT 20",
                [$userId, $searchTerm, $searchTerm, $searchTerm],
                'isss'
            ) ?? [];
        }
        
        // For students, search across their enrolled units
        return $this->db->select(
            "SELECT s.* FROM live_sessions s
             JOIN student_unit su ON s.unit_id = su.unit_id
             WHERE su.student_id = ? AND (s.title LIKE ? OR s.session_code LIKE ?)
             ORDER BY s.created_at DESC LIMIT 20",
            [$userId, $searchTerm, $searchTerm],
            'iss'
        ) ?? [];
    }

    /**
     * Get sessions for a specific course
     * 
     * @param int $courseId
     * @return array
     */
    public function getByCourse(int $courseId): array
    {
        return $this->db->select(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM live_participants p WHERE p.session_id = s.id) as participant_count
             FROM live_sessions s
             WHERE s.course_id = ?
             ORDER BY s.created_at DESC",
            [$courseId],
            'i'
        ) ?? [];
    }

    /**
     * Get sessions for a specific unit
     * 
     * @param int $unitId
     * @return array
     */
    public function getByUnit(int $unitId): array
    {
        return $this->db->select(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM live_participants p WHERE p.session_id = s.id) as participant_count
             FROM live_sessions s
             WHERE s.unit_id = ?
             ORDER BY s.created_at DESC",
            [$unitId],
            'i'
        ) ?? [];
    }

    /**
     * Get sessions linked to a meeting
     * 
     * @param int $meetingId
     * @return array|null
     */
    public function getByMeeting(int $meetingId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM live_sessions WHERE meeting_id = ? ORDER BY created_at DESC LIMIT 1",
            [$meetingId],
            'i'
        );
    }

    /**
     * Create session from meeting data
     * 
     * @param array $meeting Meeting data
     * @return int|false
     */
    public function createFromMeeting(array $meeting)
    {
        return $this->createSession([
            'title' => 'Live: ' . ($meeting['title'] ?? 'Session'),
            'course_id' => $meeting['course_id'] ?? null,
            'unit_id' => $meeting['unit_id'] ?? null,
            'meeting_id' => $meeting['id'] ?? null,
            'lecturer_id' => $meeting['lecturer_id'] ?? \le_current_user_id(),
            'session_type' => 'mixed',
            'status' => 'scheduled',
        ]);
    }

    /**
     * Archive old sessions
     * 
     * @param int $daysOlderThan Days threshold
     * @return int Number archived
     */
    public function archiveOldSessions(int $daysOlderThan = 30): int
    {
        return $this->db->update(
            "UPDATE live_sessions SET status = 'ended', actual_end = NOW()
             WHERE status = 'active' AND actual_start IS NOT NULL 
             AND actual_start < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$daysOlderThan],
            'i'
        );
    }
}