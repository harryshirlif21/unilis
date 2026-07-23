<?php
/**
 * Live Engagement Module - Poll Option Model
 * 
 * Manages poll options.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

class PollOptionModel extends BaseModel
{
    protected string $table = 'live_poll_options';
    
    protected array $fillable = [
        'poll_id', 'option_text', 'option_value', 'display_order', 'is_correct', 'color',
    ];

    protected array $orderBy = ['display_order' => 'ASC'];

    /**
     * Get all options for a poll
     * 
     * @param int $pollId
     * @return array
     */
    public function getPollOptions(int $pollId): array
    {
        return $this->findBy('poll_id', $pollId);
    }
}
