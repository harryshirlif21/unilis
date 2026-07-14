<?php
/**
 * Live Engagement Module - Word Cloud Model
 * 
 * Manages word cloud prompts and submissions.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

/**
 * WordCloudModel - Word cloud activity management
 */
class WordCloudModel extends BaseModel
{
    protected string $table = 'live_wordcloud';
    
    protected array $fillable = [
        'session_id', 'prompt', 'is_active', 'max_words',
        'min_word_length', 'allow_profanity', 'created_by',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Activate a word cloud prompt
     * 
     * @param int $wordcloudId
     * @return bool
     */
    public function activate(int $wordcloudId): bool
    {
        $wc = $this->find($wordcloudId);
        if (!$wc) return false;

        $this->db->update("UPDATE live_wordcloud SET is_active = 0 WHERE session_id = ?", [$wc['session_id']], 'i');
        return $this->update($wordcloudId, ['is_active' => 1]) !== false;
    }

    /**
     * Close a word cloud
     * 
     * @param int $wordcloudId
     * @return bool
     */
    public function close(int $wordcloudId): bool
    {
        return $this->update($wordcloudId, ['is_active' => 0, 'closed_at' => date('Y-m-d H:i:s')]) !== false;
    }

    /**
     * Submit a word to the word cloud
     * 
     * @param int $wordcloudId
     * @param string $word
     * @param int|null $userId
     * @param int|null $participantId
     * @return int|false
     */
    public function submitWord(int $wordcloudId, string $word, ?int $userId = null, ?int $participantId = null)
    {
        $word = strtolower(trim($word));
        if (strlen($word) < 2) return false;

        // Check if word already exists
        $existing = $this->db->fetchOne(
            "SELECT id, weight FROM wordcloud_submissions WHERE wordcloud_id = ? AND word = ? LIMIT 1",
            [$wordcloudId, $word],
            'is'
        );

        if ($existing) {
            return $this->db->update(
                "UPDATE wordcloud_submissions SET weight = weight + 1 WHERE id = ?",
                [$existing['id']],
                'i'
            );
        }

        return $this->db->insert(
            "INSERT INTO wordcloud_submissions (wordcloud_id, word, user_id, session_participant_id) VALUES (?, ?, ?, ?)",
            [$wordcloudId, $word, $userId, $participantId],
            'isii'
        );
    }

    /**
     * Get word cloud data with frequencies
     * 
     * @param int $wordcloudId
     * @return array
     */
    public function getWords(int $wordcloudId): array
    {
        return $this->db->select(
            "SELECT word, SUM(weight) as weight, COUNT(*) as frequency
             FROM wordcloud_submissions WHERE wordcloud_id = ? AND is_approved = 1
             GROUP BY word ORDER BY weight DESC LIMIT ?",
            [$wordcloudId, 100],
            'ii'
        ) ?? [];
    }
}

/**
 * OpenResponseModel - Manages open-ended question responses
 */
class OpenResponseModel extends BaseModel
{
    protected string $table = 'live_open_responses';
    
    protected array $fillable = [
        'session_id', 'prompt', 'response_type', 'is_anonymous',
        'is_moderated', 'is_active', 'max_characters', 'created_by',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Submit a response
     * 
     * @param int $openResponseId
     * @param string $responseText
     * @param int|null $userId
     * @param int|null $participantId
     * @param bool $anonymous
     * @return int|false
     */
    public function submitResponse(int $openResponseId, string $responseText, ?int $userId = null,
                                    ?int $participantId = null, bool $anonymous = false)
    {
        $or = $this->find($openResponseId);
        if (!$or) return false;

        // Check character limit
        if ($or['max_characters'] && strlen($responseText) > $or['max_characters']) {
            $responseText = substr($responseText, 0, $or['max_characters']);
        }

        return $this->db->insert(
            "INSERT INTO open_response_submissions (open_response_id, user_id, session_participant_id, response_text, is_anonymous) 
             VALUES (?, ?, ?, ?, ?)",
            [$openResponseId, $userId, $participantId, $responseText, $anonymous ? 1 : 0],
            'iiisi'
        );
    }

    /**
     * Get responses for moderation
     * 
     * @param int $openResponseId
     * @param bool $approvedOnly
     * @return array
     */
    public function getResponses(int $openResponseId, bool $approvedOnly = true): array
    {
        $sql = "SELECT * FROM open_response_submissions WHERE open_response_id = ?";
        $params = [$openResponseId];

        if ($approvedOnly) {
            $sql .= " AND is_approved = 1";
        }

        return $this->db->select($sql, $params, 'i') ?? [];
    }

    /**
     * Approve or reject a response
     * 
     * @param int $submissionId
     * @param bool $approve
     * @return bool
     */
    public function moderateResponse(int $submissionId, bool $approve): bool
    {
        return $this->db->update(
            "UPDATE open_response_submissions SET is_approved = ? WHERE id = ?",
            [$approve ? 1 : 0, $submissionId],
            'ii'
        ) !== false;
    }
}

/**
 * WhiteboardModel - Manages whiteboard canvases
 */
class WhiteboardModel extends BaseModel
{
    protected string $table = 'live_whiteboards';
    
    protected array $fillable = [
        'session_id', 'title', 'width', 'height', 'background_color',
        'is_active', 'is_collaborative', 'created_by',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Add an object to the whiteboard
     * 
     * @param int $whiteboardId
     * @param string $objectType
     * @param array $objectData
     * @param array $styleData
     * @param int|null $userId
     * @return int|false
     */
    public function addObject(int $whiteboardId, string $objectType, array $objectData, 
                               array $styleData = [], ?int $userId = null)
    {
        $maxZ = $this->db->fetchOne(
            "SELECT MAX(z_index) as max_z FROM whiteboard_objects WHERE whiteboard_id = ?",
            [$whiteboardId],
            'i'
        );

        return $this->db->insert(
            "INSERT INTO whiteboard_objects (whiteboard_id, object_type, object_data, style_data, z_index, created_by) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$whiteboardId, $objectType, json_encode($objectData), json_encode($styleData), 
             ($maxZ['max_z'] ?? 0) + 1, $userId],
            'isssii'
        );
    }

    /**
     * Get all objects for a whiteboard
     * 
     * @param int $whiteboardId
     * @return array
     */
    public function getObjects(int $whiteboardId): array
    {
        return $this->db->select(
            "SELECT * FROM whiteboard_objects WHERE whiteboard_id = ? ORDER BY z_index ASC",
            [$whiteboardId],
            'i'
        ) ?? [];
    }

    /**
     * Clear all objects from a whiteboard
     * 
     * @param int $whiteboardId
     * @return bool
     */
    public function clearObjects(int $whiteboardId): bool
    {
        return $this->db->delete(
            "DELETE FROM whiteboard_objects WHERE whiteboard_id = ?",
            [$whiteboardId],
            'i'
        ) !== false;
    }
}

/**
 * ReportModel - Manages session reports and analytics
 */
class ReportModel extends BaseModel
{
    protected string $table = 'live_reports';
    
    protected array $fillable = [
        'session_id', 'report_type', 'title', 'summary_data',
        'generated_by', 'file_path', 'file_format', 'is_auto_generated',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Generate a comprehensive session report
     * 
     * @param int $sessionId
     * @param int $userId
     * @return array|null
     */
    public function generateComprehensiveReport(int $sessionId, int $userId): ?array
    {
        $session = \le_get_session($sessionId);
        if (!$session) return null;

        $stats = \le_get_session_stats($sessionId);
        $participants = \le_get_participants($sessionId);
        $polls = (new PollModel())->getSessionPolls($sessionId);
        
        $reportData = [
            'session' => $session,
            'statistics' => $stats,
            'participants' => [
                'total' => count($participants),
                'list' => $participants,
            ],
            'polls' => $polls,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $reportId = $this->create([
            'session_id' => $sessionId,
            'report_type' => 'comprehensive',
            'title' => "Report: {$session['title']}",
            'summary_data' => json_encode($reportData),
            'generated_by' => $userId,
            'file_format' => 'html',
        ]);

        if (!$reportId) return null;

        return $this->find($reportId);
    }

    /**
     * Get reports for a session
     * 
     * @param int $sessionId
     * @return array
     */
    public function getSessionReports(int $sessionId): array
    {
        return $this->findBy('session_id', $sessionId);
    }
}