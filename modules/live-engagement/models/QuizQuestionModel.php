<?php
/**
 * Live Engagement Module - Quiz Question Model
 * 
 * Manages quiz questions.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

class QuizQuestionModel extends BaseModel
{
    protected string $table = 'quiz_questions';
    
    protected array $fillable = [
        'quiz_id', 'question_text', 'question_type', 'points', 'display_order', 'explanation',
    ];

    protected array $orderBy = ['display_order' => 'ASC'];

    /**
     * Get all questions for a quiz with answers
     * 
     * @param int $quizId
     * @param bool $includeCorrect Include correct answers
     * @return array
     */
    public function getQuizQuestions(int $quizId, bool $includeCorrect = false): array
    {
        $questions = $this->findBy('quiz_id', $quizId);
        
        $answerModel = new QuizAnswerModel();
        foreach ($questions as &$question) {
            $question['answers'] = $answerModel->getQuestionAnswers($question['id'], $includeCorrect);
        }

        return $questions;
    }
}
