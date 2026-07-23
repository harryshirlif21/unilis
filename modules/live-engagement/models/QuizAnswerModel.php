<?php
/**
 * Live Engagement Module - Quiz Answer Model
 * 
 * Manages quiz question answers.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

class QuizAnswerModel extends BaseModel
{
    protected string $table = 'quiz_answers';
    
    protected array $fillable = [
        'question_id', 'answer_text', 'is_correct', 'display_order',
    ];

    protected array $orderBy = ['display_order' => 'ASC'];

    /**
     * Get all answers for a question
     * 
     * @param int $questionId
     * @param bool $includeCorrect Include correct flag
     * @return array
     */
    public function getQuestionAnswers(int $questionId, bool $includeCorrect = false): array
    {
        if ($includeCorrect) {
            return $this->findBy('question_id', $questionId);
        }
        
        // Exclude correct flag for students
        $answers = $this->findBy('question_id', $questionId);
        foreach ($answers as &$answer) {
            unset($answer['is_correct']);
        }
        return $answers;
    }
}
