<?php
/**
 * Live Engagement Module - Quiz Model
 * 
 * Manages quizzes, questions, answers, and quiz attempts.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

/**
 * QuizModel - Interactive quiz management
 */
class QuizModel extends BaseModel
{
    protected string $table = 'live_quizzes';
    
    protected array $fillable = [
        'session_id', 'title', 'description', 'time_limit_minutes',
        'passing_score', 'shuffle_questions', 'show_results',
        'max_attempts', 'is_active', 'is_locked', 'created_by',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Create a quiz with questions and answers
     * 
     * @param array $quizData Quiz data
     * @param array $questions Questions with answers
     * @return int|false Quiz ID or false
     */
    public function createWithQuestions(array $quizData, array $questions)
    {
        $this->db->beginTransaction();
        
        $quizData['created_by'] = $quizData['created_by'] ?? \le_current_user_id();
        $quizId = $this->create($quizData);
        
        if (!$quizId) {
            $this->db->rollback();
            return false;
        }

        $questionModel = new QuizQuestionModel();
        foreach ($questions as $qIndex => $question) {
            $question['quiz_id'] = $quizId;
            $question['display_order'] = $qIndex;
            
            // Extract answers from question data
            $answers = $question['answers'] ?? [];
            unset($question['answers']);
            
            $questionId = $questionModel->create($question);
            if (!$questionId) {
                $this->db->rollback();
                return false;
            }
            
            // Create answers for this question
            $answerModel = new QuizAnswerModel();
            foreach ($answers as $aIndex => $answer) {
                $answer['question_id'] = $questionId;
                $answer['display_order'] = $aIndex;
                
                if (!$answerModel->create($answer)) {
                    $this->db->rollback();
                    return false;
                }
            }
        }
        
        $this->db->commit();
        return $quizId;
    }

    /**
     * Get quiz with all questions and answers
     * 
     * @param int $quizId
     * @param bool $includeCorrect Include correct answers
     * @return array|null
     */
    public function getQuizWithQuestions(int $quizId, bool $includeCorrect = false): ?array
    {
        $quiz = $this->find($quizId);
        if (!$quiz) return null;

        $questionModel = new QuizQuestionModel();
        $questions = $questionModel->getQuizQuestions($quizId, $includeCorrect);
        
        $quiz['questions'] = $questions;
        $quiz['total_points'] = array_sum(array_column($questions, 'points'));
        
        // Get attempt count
        $quiz['attempt_count'] = $this->db->count(
            'quiz_attempts', 'quiz_id = ?', [$quizId]
        );

        return $quiz;
    }

    /**
     * Activate a quiz for participants
     * 
     * @param int $quizId
     * @return bool
     */
    public function activate(int $quizId): bool
    {
        $quiz = $this->find($quizId);
        if (!$quiz) return false;

        $this->db->update(
            "UPDATE live_quizzes SET is_active = 0 WHERE session_id = ?",
            [$quiz['session_id']],
            'i'
        );

        return $this->update($quizId, ['is_active' => 1, 'is_locked' => 0]) !== false;
    }

    /**
     * Lock a quiz (prevent further attempts)
     * 
     * @param int $quizId
     * @return bool
     */
    public function lock(int $quizId): bool
    {
        return $this->update($quizId, ['is_active' => 0, 'is_locked' => 1]) !== false;
    }

    /**
     * Start a quiz attempt for a user
     * 
     * @param int $quizId
     * @param int $userId
     * @param int|null $participantId
     * @return int|false Attempt ID
     */
    public function startAttempt(int $quizId, int $userId, ?int $participantId = null)
    {
        $quiz = $this->find($quizId);
        if (!$quiz) return false;

        // Check max attempts
        if ($quiz['max_attempts'] > 0) {
            $attemptCount = $this->db->count(
                'quiz_attempts', 'quiz_id = ? AND user_id = ? AND status = ?',
                [$quizId, $userId, 'completed']
            );
            if ($attemptCount >= $quiz['max_attempts']) {
                return false;
            }
        }

        $attemptNumber = $this->db->count(
            'quiz_attempts', 'quiz_id = ? AND user_id = ?', [$quizId, $userId]
        ) + 1;

        return $this->db->insert(
            "INSERT INTO quiz_attempts (quiz_id, user_id, session_participant_id, started_at, attempt_number, status) 
             VALUES (?, ?, ?, NOW(), ?, 'in_progress')",
            [$quizId, $userId, $participantId, $attemptNumber],
            'iiii'
        );
    }

    /**
     * Submit an answer for a quiz attempt
     * 
     * @param int $attemptId
     * @param int $questionId
     * @param int|null $answerId
     * @param string|null $answerText
     * @return bool
     */
    public function submitAnswer(int $attemptId, int $questionId, ?int $answerId = null, 
                                  ?string $answerText = null): bool
    {
        // Check if answer is correct
        $isCorrect = false;
        $pointsEarned = 0;
        
        if ($answerId) {
            $answerModel = new QuizAnswerModel();
            $answer = $answerModel->find($answerId);
            if ($answer) {
                $isCorrect = (bool)$answer['is_correct'];
                $questionModel = new QuizQuestionModel();
                $question = $questionModel->find($questionId);
                $pointsEarned = $isCorrect ? ($question['points'] ?? 1) : 0;
            }
        }

        return $this->db->insert(
            "INSERT INTO quiz_attempt_answers (attempt_id, question_id, answer_id, answer_text, is_correct, points_earned) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$attemptId, $questionId, $answerId, $answerText, $isCorrect ? 1 : 0, $pointsEarned],
            'iiisid'
        ) !== false;
    }

    /**
     * Complete a quiz attempt and calculate score
     * 
     * @param int $attemptId
     * @return array|null Attempt results
     */
    public function completeAttempt(int $attemptId): ?array
    {
        $attempt = $this->db->fetchOne(
            "SELECT * FROM quiz_attempts WHERE id = ?",
            [$attemptId],
            'i'
        );
        if (!$attempt) return null;

        $quiz = $this->find($attempt['quiz_id']);
        if (!$quiz) return null;

        // Calculate scores
        $answers = $this->db->select(
            "SELECT * FROM quiz_attempt_answers WHERE attempt_id = ?",
            [$attemptId],
            'i'
        ) ?? [];

        $totalScore = array_sum(array_column($answers, 'points_earned'));
        
        $questions = $this->db->select(
            "SELECT * FROM quiz_questions WHERE quiz_id = ?",
            [$quiz['id']],
            'i'
        );
        $totalPoints = array_sum(array_column($questions, 'points'));
        
        $percentage = $totalPoints > 0 ? round(($totalScore / $totalPoints) * 100, 2) : 0;

        $timeTaken = $this->db->fetchOne(
            "SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) as seconds FROM quiz_attempts WHERE id = ?",
            [$attemptId],
            'i'
        );

        // Update attempt record
        $this->db->update(
            "UPDATE quiz_attempts SET score = ?, total_points = ?, percentage = ?, 
             completed_at = NOW(), time_taken_seconds = ?, status = 'completed' 
             WHERE id = ?",
            [$totalScore, $totalPoints, $percentage, $timeTaken['seconds'] ?? 0, $attemptId],
            'dddii'
        );

        return [
            'attempt' => $attempt,
            'answers' => $answers,
            'score' => $totalScore,
            'total_points' => $totalPoints,
            'percentage' => $percentage,
            'passed' => $percentage >= ($quiz['passing_score'] ?? 50),
            'time_taken' => $timeTaken['seconds'] ?? 0,
        ];
    }

    /**
     * Get leaderboard for a quiz
     * 
     * @param int $quizId
     * @param int $limit
     * @return array
     */
    public function getLeaderboard(int $quizId, int $limit = 10): array
    {
        return $this->db->select(
            "SELECT a.id, a.user_id, a.score, a.total_points, a.percentage, 
                    a.time_taken_seconds, a.completed_at, a.attempt_number,
                    COALESCE(u.name, u.username, p.display_name) as user_name
             FROM quiz_attempts a
             LEFT JOIN users u ON a.user_id = u.id
             LEFT JOIN live_participants p ON a.session_participant_id = p.id
             WHERE a.quiz_id = ? AND a.status = 'completed'
             ORDER BY a.percentage DESC, a.time_taken_seconds ASC
             LIMIT ?",
            [$quizId, $limit],
            'ii'
        ) ?? [];
    }

    /**
     * Get quiz statistics
     * 
     * @param int $quizId
     * @return array
     */
    public function getQuizStats(int $quizId): array
    {
        $quiz = $this->find($quizId);
        if (!$quiz) return [];

        $attempts = $this->db->select(
            "SELECT * FROM quiz_attempts WHERE quiz_id = ?",
            [$quizId],
            'i'
        ) ?? [];

        $completedAttempts = array_filter($attempts, fn($a) => $a['status'] === 'completed');
        $totalAttempts = count($attempts);
        $completedCount = count($completedAttempts);
        
        $avgScore = $completedCount > 0 
            ? array_sum(array_column($completedAttempts, 'percentage')) / $completedCount 
            : 0;
        
        $passedCount = count(array_filter($completedAttempts, fn($a) => ($a['percentage'] ?? 0) >= ($quiz['passing_score'] ?? 50)));
        $uniqueUsers = count(array_unique(array_column($attempts, 'user_id')));

        return [
            'total_attempts' => $totalAttempts,
            'completed_attempts' => $completedCount,
            'unique_participants' => $uniqueUsers,
            'average_score' => round($avgScore, 1),
            'pass_count' => $passedCount,
            'pass_rate' => $completedCount > 0 ? round(($passedCount / $completedCount) * 100, 1) : 0,
            'leaderboard' => $this->getLeaderboard($quizId),
        ];
    }
}
