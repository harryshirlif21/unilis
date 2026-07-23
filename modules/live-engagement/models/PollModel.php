<?php
/**
 * Live Engagement Module - Poll Model
 * 
 * Manages polls, poll options, and poll responses.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

/**
 * PollModel - Interactive polling management
 */
class PollModel extends BaseModel
{
    protected string $table = 'live_polls';
    
    protected array $fillable = [
        'session_id', 'question', 'poll_type', 'is_anonymous',
        'is_multiple_answer', 'is_active', 'is_closed', 'display_order',
        'time_limit_seconds', 'created_by',
    ];

    protected array $orderBy = ['display_order' => 'ASC'];

    /**
     * Create a new poll with options
     * 
     * @param array $pollData Poll data
     * @param array $options Poll options
     * @return int|false Poll ID or false
     */
    public function createWithOptions(array $pollData, array $options)
    {
        $this->db->beginTransaction();
        
        $pollData['created_by'] = $pollData['created_by'] ?? \le_current_user_id();
        $pollId = $this->create($pollData);
        
        if (!$pollId) {
            $this->db->rollback();
            return false;
        }
        
        $optionModel = new PollOptionModel();
        foreach ($options as $index => $option) {
            $option['poll_id'] = $pollId;
            $option['display_order'] = $index;
            
            if (!$optionModel->create($option)) {
                $this->db->rollback();
                return false;
            }
        }
        
        $this->db->commit();
        return $pollId;
    }

    /**
     * Get polls for a session with their options
     * 
     * @param int $sessionId
     * @return array
     */
    public function getSessionPolls(int $sessionId): array
    {
        $polls = $this->db->select(
            "SELECT p.*, 
                    (SELECT COUNT(*) FROM live_poll_responses r WHERE r.poll_id = p.id) as total_responses
             FROM live_polls p
             WHERE p.session_id = ?
             ORDER BY p.display_order ASC",
            [$sessionId],
            'i'
        ) ?? [];

        // Attach options to each poll
        $optionModel = new PollOptionModel();
        foreach ($polls as &$poll) {
            $poll['options'] = $optionModel->getPollOptions($poll['id']);
        }

        return $polls;
    }

    /**
     * Activate a poll (for presenter to launch)
     * 
     * @param int $pollId
     * @return bool
     */
    public function activate(int $pollId): bool
    {
        // Deactivate all other polls in the same session first
        $poll = $this->find($pollId);
        if (!$poll) return false;

        $this->db->update(
            "UPDATE live_polls SET is_active = 0 WHERE session_id = ?",
            [$poll['session_id']],
            'i'
        );

        return $this->update($pollId, ['is_active' => 1, 'is_closed' => 0]) !== false;
    }

    /**
     * Close a poll (stop accepting responses)
     * 
     * @param int $pollId
     * @return bool
     */
    public function close(int $pollId): bool
    {
        return $this->update($pollId, [
            'is_active' => 0,
            'is_closed' => 1,
            'closed_at' => date('Y-m-d H:i:s'),
        ]) !== false;
    }

    /**
     * Get poll results with response counts
     * 
     * @param int $pollId
     * @return array|null
     */
    public function getResults(int $pollId): ?array
    {
        $poll = $this->find($pollId);
        if (!$poll) return null;

        $optionModel = new PollOptionModel();
        $options = $optionModel->getPollOptions($pollId);
        
        $totalResponses = 0;
        foreach ($options as &$option) {
            $option['response_count'] = $this->db->count(
                'live_poll_responses', 'option_id = ?', [$option['id']]
            );
            $totalResponses += $option['response_count'];
        }

        // Calculate percentages
        foreach ($options as &$option) {
            $option['percentage'] = $totalResponses > 0
                ? round(($option['response_count'] / $totalResponses) * 100, 1)
                : 0;
        }

        $poll['options'] = $options;
        $poll['total_responses'] = $totalResponses;

        return $poll;
    }

    /**
     * Submit a vote/response to a poll
     * 
     * @param int $pollId Poll ID
     * @param int|null $optionId Selected option ID
     * @param int|null $userId User ID
     * @param int|null $participantId Participant ID
     * @param int|null $ratingValue Rating value
     * @param string|null $responseText Text response
     * @return int|false
     */
    public function submitResponse(int $pollId, ?int $optionId, ?int $userId = null, 
                                    ?int $participantId = null, ?int $ratingValue = null,
                                    ?string $responseText = null)
    {
        return $this->db->insert(
            "INSERT INTO live_poll_responses (poll_id, option_id, user_id, session_participant_id, rating_value, response_text) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$pollId, $optionId, $userId, $participantId, $ratingValue, $responseText],
            'iiiiss'
        );
    }

    /**
     * Check if a user has already responded to a poll
     * 
     * @param int $pollId
     * @param int $userId
     * @return bool
     */
    public function hasResponded(int $pollId, int $userId): bool
    {
        return $this->db->count(
            'live_poll_responses', 'poll_id = ? AND user_id = ?', [$pollId, $userId]
        ) > 0;
    }
}
