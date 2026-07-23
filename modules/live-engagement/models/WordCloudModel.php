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