<?php
/**
 * Take and grade an assessment.
 *
 * Grading happens server-side and correct answers are never sent to the browser
 * before submission - otherwise the whole thing is decoration, since the answer
 * key would be sitting in the page source.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/layout.php';

$learner = learn_require_login($conn);
$assessmentId = (int)($_GET['a'] ?? $_POST['assessment_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT a.*, c.slug, c.title AS course_title, c.pass_mark AS course_pass_mark, c.id AS course_id
    FROM public_course_assessments a
    JOIN public_courses c ON c.id = a.course_id
    WHERE a.id = ? AND c.is_published = 1 LIMIT 1
");
$stmt->bind_param('i', $assessmentId);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assessment) {
    learn_head(['title' => 'Assessment not found', 'learner' => $learner, 'narrow' => true]);
    echo '<div class="ln-empty"><span class="material-symbols-rounded">search_off</span>'
       . '<h2>Assessment not found</h2><p><a href="/learn/dashboard.php">Back to my learning</a></p></div>';
    learn_foot();
    exit;
}

$courseId = (int)$assessment['course_id'];

// Enrolment is the permission: an assessment is part of a course, not a
// standalone quiz anyone can sit.
if (!learn_is_enrolled($conn, $learner['id'], $courseId)) {
    header('Location: /learn/course.php?c=' . urlencode((string)$assessment['slug']));
    exit;
}

$passMark = (int)($assessment['pass_mark'] ?? 0) > 0
    ? (int)$assessment['pass_mark']
    : (int)$assessment['course_pass_mark'];

$stmt = $conn->prepare("
    SELECT id, question, type, options, marks, position
    FROM public_course_questions WHERE assessment_id = ? ORDER BY position, id
");
$stmt->bind_param('i', $assessmentId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Previous attempts, for the attempt cap and the "best so far" line.
$stmt = $conn->prepare("
    SELECT COUNT(*) AS attempts, MAX(percentage) AS best, MAX(passed) AS ever_passed
    FROM external_assessment_attempts WHERE learner_id = ? AND assessment_id = ?
");
$stmt->bind_param('ii', $learner['id'], $assessmentId);
$stmt->execute();
$history = $stmt->get_result()->fetch_assoc();
$stmt->close();

$attemptCount = (int)($history['attempts'] ?? 0);
$maxAttempts = (int)$assessment['max_attempts'];
$attemptsLeft = $maxAttempts === 0 ? null : max(0, $maxAttempts - $attemptCount);
$everPassed = (int)($history['ever_passed'] ?? 0) === 1;

$result = null;
$errors = [];

/**
 * Compare a submitted answer with the stored key.
 *
 * Multiple-choice keys are stored as a comma-separated list, so both sides are
 * normalised to a sorted set before comparing - the order a learner ticked the
 * boxes in must not decide whether they were right.
 */
function learn_answer_is_correct(array $question, $submitted): bool
{
    $key = trim((string)($question['correct_answer'] ?? ''));

    if ($question['type'] === 'multiple') {
        $expected = array_filter(array_map('trim', explode(',', $key)), static fn($v) => $v !== '');
        $given = is_array($submitted) ? array_map('trim', $submitted) : [];
        sort($expected);
        sort($given);

        return $expected === $given && $expected !== [];
    }

    if ($question['type'] === 'short_text') {
        // Free text is compared case- and whitespace-insensitively. Anything
        // subtler needs a human, which this flow does not have.
        return $key !== ''
            && mb_strtolower(trim((string)$submitted)) === mb_strtolower($key);
    }

    return $key !== '' && trim((string)$submitted) === $key;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!learn_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please submit again.';
    } elseif (!$questions) {
        $errors[] = 'This assessment has no questions yet.';
    } elseif ($attemptsLeft === 0) {
        $errors[] = 'You have used all your attempts on this assessment.';
    } else {
        // Re-read the keys here rather than trusting anything from the form.
        $stmt = $conn->prepare("
            SELECT id, question, type, correct_answer, marks
            FROM public_course_questions WHERE assessment_id = ?
        ");
        $stmt->bind_param('i', $assessmentId);
        $stmt->execute();
        $keys = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $submitted = $_POST['answers'] ?? [];
        $score = 0.0;
        $maxScore = 0.0;
        $record = [];

        foreach ($keys as $question) {
            $qid = (int)$question['id'];
            $marks = (float)$question['marks'];
            $maxScore += $marks;

            $given = $submitted[$qid] ?? null;
            $correct = learn_answer_is_correct($question, $given);
            if ($correct) {
                $score += $marks;
            }

            $record[$qid] = ['given' => $given, 'correct' => $correct];
        }

        $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0.0;
        $passed = $percentage >= $passMark ? 1 : 0;
        $answersJson = json_encode($record);

        $stmt = $conn->prepare("
            INSERT INTO external_assessment_attempts
                (learner_id, assessment_id, score, max_score, percentage, passed, answers)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'iidddis',
            $learner['id'], $assessmentId, $score, $maxScore, $percentage, $passed, $answersJson
        );
        $stmt->execute();
        $stmt->close();

        $result = [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => (bool)$passed,
            'record' => $record,
        ];

        // Passing the last outstanding item is what completes a course.
        $awarded = $passed ? learn_maybe_award_certificate($conn, $learner['id'], $courseId) : null;
        $result['certificate'] = $awarded;

        $attemptCount++;
        $attemptsLeft = $maxAttempts === 0 ? null : max(0, $maxAttempts - $attemptCount);
        $everPassed = $everPassed || (bool)$passed;
    }
}

learn_head(['title' => $assessment['title'], 'learner' => $learner]);
?>
<section class="ln-hero">
    <h1><?= learn_e($assessment['title']) ?></h1>
    <p>
        <a href="/learn/course.php?c=<?= learn_e($assessment['slug']) ?>"><?= learn_e($assessment['course_title']) ?></a>
        · pass mark <?= (int)$passMark ?>%
        <?php if ($maxAttempts > 0): ?>
            · <?= (int)$attemptsLeft ?> of <?= (int)$maxAttempts ?> attempts left
        <?php endif; ?>
        <?php if ($attemptCount > 0 && $history['best'] !== null): ?>
            · best so far <?= (float)$history['best'] ?>%
        <?php endif; ?>
    </p>
</section>

<?php learn_errors($errors); ?>

<?php if ($result !== null): ?>
    <div class="ln-alert ln-alert-<?= $result['passed'] ? 'success' : 'error' ?>">
        <span class="material-symbols-rounded"><?= $result['passed'] ? 'check_circle' : 'cancel' ?></span>
        <div>
            <strong><?= $result['passed'] ? 'Passed' : 'Not passed' ?></strong> —
            <?= (float)$result['score'] ?>/<?= (float)$result['max_score'] ?>
            (<?= (float)$result['percentage'] ?>%), pass mark <?= (int)$passMark ?>%.
            <?php if ($result['certificate'] !== null): ?>
                <br>You have completed the course —
                <a href="/learn/certificate.php?code=<?= learn_e($result['certificate']['verification_code']) ?>">view your certificate</a>.
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:26px;">
        <a class="ln-btn ln-btn-primary" href="/learn/course.php?c=<?= learn_e($assessment['slug']) ?>">
            <span class="material-symbols-rounded">arrow_back</span> Back to the course
        </a>
        <?php if (!$result['passed'] && $attemptsLeft !== 0): ?>
            <a class="ln-btn ln-btn-ghost" href="/learn/assessment.php?a=<?= (int)$assessmentId ?>">
                <span class="material-symbols-rounded">refresh</span> Try again
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($result === null): ?>
    <?php if (!$questions): ?>
        <div class="ln-empty">
            <span class="material-symbols-rounded">construction</span>
            <h2>No questions yet</h2>
            <p>This assessment has not been written yet.</p>
        </div>
    <?php elseif ($attemptsLeft === 0): ?>
        <div class="ln-empty">
            <span class="material-symbols-rounded">block</span>
            <h2>No attempts left</h2>
            <p>You have used all <?= (int)$maxAttempts ?> attempts on this assessment.</p>
        </div>
    <?php else: ?>
        <?php if ($everPassed): ?>
            <?php learn_notice('You have already passed this assessment. Another attempt will not remove that.', 'info'); ?>
        <?php endif; ?>

        <form method="post" class="ln-card">
            <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
            <input type="hidden" name="assessment_id" value="<?= (int)$assessmentId ?>">

            <?php if (!empty($assessment['instructions'])): ?>
                <p class="ln-sub"><?= learn_e($assessment['instructions']) ?></p>
            <?php endif; ?>

            <?php foreach ($questions as $index => $question):
                $options = [];
                if (!empty($question['options'])) {
                    $decoded = json_decode((string)$question['options'], true);
                    if (is_array($decoded)) {
                        $options = $decoded;
                    }
                }
                $qid = (int)$question['id'];
                ?>
                <fieldset style="border:none; padding:0; margin:0 0 26px;">
                    <legend style="font-weight:600; color:var(--ln-ink); margin-bottom:10px; padding:0;">
                        <?= (int)$index + 1 ?>. <?= learn_e($question['question']) ?>
                        <span style="font-weight:400; color:var(--ln-muted); font-size:0.85rem;">
                            (<?= (int)$question['marks'] ?> mark<?= (int)$question['marks'] === 1 ? '' : 's' ?>)
                        </span>
                    </legend>

                    <?php if ($question['type'] === 'short_text'): ?>
                        <input name="answers[<?= $qid ?>]" type="text"
                               style="width:100%; padding:11px 14px; border:1px solid var(--ln-line); border-radius:10px; font:inherit;">

                    <?php elseif ($question['type'] === 'true_false'): ?>
                        <?php foreach (['True', 'False'] as $choice): ?>
                            <label style="display:flex; gap:9px; align-items:center; padding:8px 0; cursor:pointer;">
                                <input type="radio" name="answers[<?= $qid ?>]" value="<?= learn_e($choice) ?>">
                                <span><?= $choice ?></span>
                            </label>
                        <?php endforeach; ?>

                    <?php else:
                        $multiple = $question['type'] === 'multiple';
                        foreach ($options as $option): ?>
                            <label style="display:flex; gap:9px; align-items:center; padding:8px 0; cursor:pointer;">
                                <input type="<?= $multiple ? 'checkbox' : 'radio' ?>"
                                       name="answers[<?= $qid ?>]<?= $multiple ? '[]' : '' ?>"
                                       value="<?= learn_e((string)$option) ?>">
                                <span><?= learn_e((string)$option) ?></span>
                            </label>
                        <?php endforeach;
                    endif; ?>
                </fieldset>
            <?php endforeach; ?>

            <button class="ln-btn ln-btn-primary" type="submit">
                <span class="material-symbols-rounded">send</span> Submit answers
            </button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php
learn_foot();
