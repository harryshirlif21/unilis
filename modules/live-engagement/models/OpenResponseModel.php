<?php
/**
 * Live Engagement Module - Open Response Model
 * 
 * Manages open-ended question responses.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

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
