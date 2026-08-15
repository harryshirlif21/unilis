<?php
/**
 * Open Courses Studio — the builder for one course.
 *
 * Three views over the same course, chosen by query string:
 *   ?course_id=N                 the outline: details, modules, lessons, publish
 *   ?course_id=N&lesson=L        one lesson's body
 *   ?course_id=N&assessment=A    one assessment and its questions
 *
 * One file rather than three, because every view shares the same ownership
 * check, the same CSRF token and the same set of mutations, and splitting them
 * would mean repeating all three.
 *
 * Every mutation is a POST that redirects. The alternative - rendering the
 * result of the POST - means a refresh re-submits, and here that would add a
 * second module or a duplicate question.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../learn/config.php';
require_once __DIR__ . '/../learn/includes/authoring.php';
require_once __DIR__ . '/catalogue_layout.php';

$actor = catalogue_require_author();
studio_require_schema($conn);

$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$course = catalogue_require_course($conn, $actor, $courseId);

/** Where to send the browser after a mutation. */
function studio_back(int $courseId, array $extra = []): string
{
    $params = array_merge(['course_id' => $courseId], $extra);

    return 'catalogue_builder.php?' . http_build_query($params);
}

function studio_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ── Mutations ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!catalogue_csrf_valid($_POST['csrf_token'] ?? null)) {
        studio_flash('Your session expired. Please try that again.', 'error');
        studio_redirect(studio_back($courseId));
    }

    $action = (string)($_POST['action'] ?? '');
    $isPublished = (int)$course['is_published'] === 1;

    // Every id below is re-checked against this course before use. Without that,
    // changing a hidden module_id would let an author edit somebody else's
    // course through their own builder page.

    switch ($action) {

        // ── Course ────────────────────────────────────────────────────────
        case 'save_course': {
            $check = catalogue_validate_course($_POST);
            if ($check['errors']) {
                studio_flash('The course was not saved.', 'error', $check['errors']);
                studio_redirect(studio_back($courseId));
            }

            catalogue_update_course($conn, $courseId, $check['values'], $isPublished);

            $message = 'Course details saved.';
            if (!empty($_FILES['cover']['name'])) {
                $upload = catalogue_store_upload($_FILES['cover'], 'cover');
                if (!$upload['ok']) {
                    studio_flash('Details saved, but the cover image was not: ' . $upload['error'], 'error');
                    studio_redirect(studio_back($courseId));
                }
                catalogue_discard_upload($course['cover_image']);
                catalogue_set_cover($conn, $courseId, $upload['path']);
                $message = 'Course details and cover image saved.';
            }

            if ($isPublished) {
                $message .= ' The public URL was left alone, because the course is published.';
            }

            studio_flash($message);
            studio_redirect(studio_back($courseId));
        }

        case 'remove_cover': {
            catalogue_discard_upload($course['cover_image']);
            catalogue_set_cover($conn, $courseId, null);
            studio_flash('Cover image removed.');
            studio_redirect(studio_back($courseId));
        }

        case 'publish': {
            $result = catalogue_set_published($conn, $courseId, true);
            if (!$result['ok']) {
                studio_flash('This course is not ready to publish yet.', 'error', $result['blockers']);
            } else {
                studio_flash('Published. It is now in the catalogue at /learn.');
            }
            studio_redirect(studio_back($courseId));
        }

        case 'unpublish': {
            catalogue_set_published($conn, $courseId, false);
            studio_flash(
                'Unpublished. It has left the catalogue. Learners already enrolled keep their '
                . 'progress and certificates, but cannot open the course while it is hidden.',
                'info'
            );
            studio_redirect(studio_back($courseId));
        }

        case 'delete_course': {
            $result = catalogue_delete_course($conn, $courseId);
            if (!$result['ok']) {
                studio_flash($result['error'], 'error');
                studio_redirect(studio_back($courseId));
            }
            catalogue_discard_upload($course['cover_image']);
            studio_flash('Course deleted.');
            studio_redirect('catalogue.php');
        }

        // ── Modules ───────────────────────────────────────────────────────
        case 'add_module': {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') {
                studio_flash('A module needs a title.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $summary = trim((string)($_POST['summary'] ?? ''));
            catalogue_add_module($conn, $courseId, mb_substr($title, 0, 200), $summary !== '' ? mb_substr($summary, 0, 400) : null);
            studio_flash('Module added.');
            studio_redirect(studio_back($courseId));
        }

        case 'save_module': {
            $module = catalogue_module_in_course($conn, (int)($_POST['module_id'] ?? 0), $courseId);
            if ($module === null) {
                studio_flash('That module is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') {
                studio_flash('A module needs a title.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $summary = trim((string)($_POST['summary'] ?? ''));
            catalogue_update_module(
                $conn,
                (int)$module['id'],
                mb_substr($title, 0, 200),
                $summary !== '' ? mb_substr($summary, 0, 400) : null
            );
            studio_flash('Module saved.');
            studio_redirect(studio_back($courseId));
        }

        case 'delete_module': {
            $module = catalogue_module_in_course($conn, (int)($_POST['module_id'] ?? 0), $courseId);
            if ($module === null) {
                studio_flash('That module is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            catalogue_delete_module($conn, (int)$module['id']);
            studio_flash('Module and its lessons deleted. Any assessment it held is now a course-level assessment.');
            studio_redirect(studio_back($courseId));
        }

        case 'move_module': {
            $module = catalogue_module_in_course($conn, (int)($_POST['module_id'] ?? 0), $courseId);
            if ($module !== null) {
                catalogue_move(
                    $conn,
                    'public_course_modules',
                    'course_id',
                    (int)$module['id'],
                    $courseId,
                    ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down'
                );
            }
            studio_redirect(studio_back($courseId));
        }

        // ── Lessons ───────────────────────────────────────────────────────
        case 'add_lesson': {
            $module = catalogue_module_in_course($conn, (int)($_POST['module_id'] ?? 0), $courseId);
            if ($module === null) {
                studio_flash('That module is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $check = catalogue_validate_lesson($_POST);
            if ($check['errors']) {
                studio_flash('The lesson was not added.', 'error', $check['errors']);
                studio_redirect(studio_back($courseId));
            }
            $lessonId = catalogue_add_lesson($conn, (int)$module['id'], $check['values']);
            studio_flash('Lesson added. Write the body here.');
            studio_redirect(studio_back($courseId, ['lesson' => $lessonId]));
        }

        case 'save_lesson': {
            $lesson = catalogue_lesson_in_course($conn, (int)($_POST['lesson_id'] ?? 0), $courseId);
            if ($lesson === null) {
                studio_flash('That lesson is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $check = catalogue_validate_lesson($_POST);
            if ($check['errors']) {
                studio_flash('The lesson was not saved.', 'error', $check['errors']);
                studio_redirect(studio_back($courseId, ['lesson' => (int)$lesson['id']]));
            }
            catalogue_update_lesson($conn, (int)$lesson['id'], $check['values']);

            $message = 'Lesson saved.';
            if (!empty($_FILES['attachment']['name'])) {
                $upload = catalogue_store_upload($_FILES['attachment'], 'attachment');
                if (!$upload['ok']) {
                    studio_flash('Lesson saved, but the attachment was not: ' . $upload['error'], 'error');
                    studio_redirect(studio_back($courseId, ['lesson' => (int)$lesson['id']]));
                }
                catalogue_discard_upload($lesson['attachment_path']);
                catalogue_set_lesson_attachment($conn, (int)$lesson['id'], $upload['path']);
                $message = 'Lesson and attachment saved.';
            }

            studio_flash($message);
            studio_redirect(studio_back($courseId, ['lesson' => (int)$lesson['id']]));
        }

        case 'remove_attachment': {
            $lesson = catalogue_lesson_in_course($conn, (int)($_POST['lesson_id'] ?? 0), $courseId);
            if ($lesson !== null) {
                catalogue_discard_upload($lesson['attachment_path']);
                catalogue_set_lesson_attachment($conn, (int)$lesson['id'], null);
                studio_flash('Attachment removed.');
                studio_redirect(studio_back($courseId, ['lesson' => (int)$lesson['id']]));
            }
            studio_redirect(studio_back($courseId));
        }

        case 'delete_lesson': {
            $lesson = catalogue_lesson_in_course($conn, (int)($_POST['lesson_id'] ?? 0), $courseId);
            if ($lesson === null) {
                studio_flash('That lesson is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            catalogue_discard_upload($lesson['attachment_path']);
            catalogue_delete_lesson($conn, (int)$lesson['id']);
            studio_flash('Lesson deleted. It no longer counts towards completion for anyone.');
            studio_redirect(studio_back($courseId));
        }

        case 'move_lesson': {
            $lesson = catalogue_lesson_in_course($conn, (int)($_POST['lesson_id'] ?? 0), $courseId);
            if ($lesson !== null) {
                catalogue_move(
                    $conn,
                    'public_course_lessons',
                    'module_id',
                    (int)$lesson['id'],
                    (int)$lesson['module_id'],
                    ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down'
                );
            }
            studio_redirect(studio_back($courseId));
        }

        // ── Assessments ───────────────────────────────────────────────────
        case 'add_assessment': {
            // A blank module_id means a course-level assessment, e.g. a final
            // exam. Anything else has to be a module of this course.
            $moduleId = (int)($_POST['module_id'] ?? 0);
            if ($moduleId > 0 && catalogue_module_in_course($conn, $moduleId, $courseId) === null) {
                studio_flash('That module is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $check = catalogue_validate_assessment($_POST);
            if ($check['errors']) {
                studio_flash('The assessment was not added.', 'error', $check['errors']);
                studio_redirect(studio_back($courseId));
            }
            $assessmentId = catalogue_add_assessment(
                $conn,
                $courseId,
                $moduleId > 0 ? $moduleId : null,
                $check['values']
            );
            studio_flash('Assessment added. Now write its questions.');
            studio_redirect(studio_back($courseId, ['assessment' => $assessmentId]));
        }

        case 'save_assessment': {
            $assessment = catalogue_assessment_in_course($conn, (int)($_POST['assessment_id'] ?? 0), $courseId);
            if ($assessment === null) {
                studio_flash('That assessment is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $moduleId = (int)($_POST['module_id'] ?? 0);
            if ($moduleId > 0 && catalogue_module_in_course($conn, $moduleId, $courseId) === null) {
                studio_flash('That module is not part of this course.', 'error');
                studio_redirect(studio_back($courseId, ['assessment' => (int)$assessment['id']]));
            }
            $check = catalogue_validate_assessment($_POST);
            if ($check['errors']) {
                studio_flash('The assessment was not saved.', 'error', $check['errors']);
                studio_redirect(studio_back($courseId, ['assessment' => (int)$assessment['id']]));
            }
            catalogue_update_assessment(
                $conn,
                (int)$assessment['id'],
                $moduleId > 0 ? $moduleId : null,
                $check['values']
            );
            studio_flash('Assessment saved.');
            studio_redirect(studio_back($courseId, ['assessment' => (int)$assessment['id']]));
        }

        case 'delete_assessment': {
            $assessment = catalogue_assessment_in_course($conn, (int)($_POST['assessment_id'] ?? 0), $courseId);
            if ($assessment === null) {
                studio_flash('That assessment is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            catalogue_delete_assessment($conn, (int)$assessment['id']);
            studio_flash('Assessment deleted, along with its questions and every attempt at it.');
            studio_redirect(studio_back($courseId));
        }

        case 'move_assessment': {
            $assessment = catalogue_assessment_in_course($conn, (int)($_POST['assessment_id'] ?? 0), $courseId);
            if ($assessment !== null) {
                catalogue_move(
                    $conn,
                    'public_course_assessments',
                    'course_id',
                    (int)$assessment['id'],
                    $courseId,
                    ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down'
                );
            }
            studio_redirect(studio_back($courseId));
        }

        // ── Questions ─────────────────────────────────────────────────────
        case 'add_question': {
            $assessment = catalogue_assessment_in_course($conn, (int)($_POST['assessment_id'] ?? 0), $courseId);
            if ($assessment === null) {
                studio_flash('That assessment is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $check = catalogue_validate_question($_POST);
            if ($check['errors']) {
                studio_flash('The question was not added.', 'error', $check['errors']);
                studio_redirect(studio_back($courseId, ['assessment' => (int)$assessment['id']]));
            }
            catalogue_add_question($conn, (int)$assessment['id'], $check['values']);
            studio_flash('Question added.');
            studio_redirect(studio_back($courseId, ['assessment' => (int)$assessment['id']]));
        }

        case 'save_question': {
            $question = catalogue_question_in_course($conn, (int)($_POST['question_id'] ?? 0), $courseId);
            if ($question === null) {
                studio_flash('That question is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            $check = catalogue_validate_question($_POST);
            if ($check['errors']) {
                studio_flash('The question was not saved.', 'error', $check['errors']);
                studio_redirect(studio_back($courseId, [
                    'assessment' => (int)$question['assessment_id'],
                    'question' => (int)$question['id'],
                ]));
            }
            catalogue_update_question($conn, (int)$question['id'], $check['values']);
            studio_flash('Question saved.');
            studio_redirect(studio_back($courseId, ['assessment' => (int)$question['assessment_id']]));
        }

        case 'delete_question': {
            $question = catalogue_question_in_course($conn, (int)($_POST['question_id'] ?? 0), $courseId);
            if ($question === null) {
                studio_flash('That question is not part of this course.', 'error');
                studio_redirect(studio_back($courseId));
            }
            catalogue_delete_question($conn, (int)$question['id']);
            studio_flash('Question deleted.');
            studio_redirect(studio_back($courseId, ['assessment' => (int)$question['assessment_id']]));
        }

        case 'move_question': {
            $question = catalogue_question_in_course($conn, (int)($_POST['question_id'] ?? 0), $courseId);
            if ($question !== null) {
                catalogue_move(
                    $conn,
                    'public_course_questions',
                    'assessment_id',
                    (int)$question['id'],
                    (int)$question['assessment_id'],
                    ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down'
                );
                studio_redirect(studio_back($courseId, ['assessment' => (int)$question['assessment_id']]));
            }
            studio_redirect(studio_back($courseId));
        }

        default:
            studio_flash('Unknown action.', 'error');
            studio_redirect(studio_back($courseId));
    }
}

// ── Which view ────────────────────────────────────────────────────────────
$isPublished = (int)$course['is_published'] === 1;
$csrf = catalogue_csrf_token();

$openLesson = null;
if ((int)($_GET['lesson'] ?? 0) > 0) {
    $openLesson = catalogue_lesson_in_course($conn, (int)$_GET['lesson'], $courseId);
}

$openAssessment = null;
if ($openLesson === null && (int)($_GET['assessment'] ?? 0) > 0) {
    $openAssessment = catalogue_assessment_in_course($conn, (int)$_GET['assessment'], $courseId);
}

$outline = catalogue_outline($conn, $courseId);
$modules = $outline['modules'];
$finalAssessments = $outline['final_assessments'];

$crumbs = [
    ['label' => 'My courses', 'url' => 'catalogue.php'],
    ['label' => $course['title'], 'url' => $openLesson || $openAssessment ? studio_back($courseId) : null],
];
if ($openLesson) {
    $crumbs[] = ['label' => $openLesson['title'], 'url' => null];
}
if ($openAssessment) {
    $crumbs[] = ['label' => $openAssessment['title'], 'url' => null];
}

studio_head($course['title'], $crumbs);

/**
 * Hidden fields every form on this page needs.
 */
function studio_form_fields(string $csrf, int $courseId, string $action): void
{
    echo '<input type="hidden" name="csrf_token" value="' . studio_e($csrf) . '">'
       . '<input type="hidden" name="course_id" value="' . $courseId . '">'
       . '<input type="hidden" name="action" value="' . studio_e($action) . '">';
}

/**
 * Up/down buttons for one row in an ordered list.
 */
function studio_move_buttons(
    string $csrf,
    int $courseId,
    string $action,
    string $idField,
    int $id,
    bool $isFirst,
    bool $isLast
): void {
    foreach ([['up', 'fa-arrow-up', $isFirst], ['down', 'fa-arrow-down', $isLast]] as [$direction, $icon, $disabled]) {
        echo '<form method="post" style="display:inline;">';
        studio_form_fields($csrf, $courseId, $action);
        echo '<input type="hidden" name="' . studio_e($idField) . '" value="' . $id . '">'
           . '<input type="hidden" name="direction" value="' . $direction . '">'
           . '<button class="st-btn-icon" type="submit" title="Move ' . $direction . '"'
           . ($disabled ? ' disabled' : '') . '><i class="fas ' . $icon . '"></i></button>'
           . '</form> ';
    }
}
?>

<?php if ($openLesson !== null): ?>
    <?php // ── Lesson editor ─────────────────────────────────────────────── ?>
    <div class="st-page-head">
        <div>
            <h1>Lesson</h1>
            <p class="st-sub">The body is HTML and is rendered as written, so headings, lists and embeds all work.</p>
        </div>
        <a class="st-btn st-btn-ghost" href="<?= studio_e(studio_back($courseId)) ?>">
            <i class="fas fa-arrow-left"></i> Back to the outline
        </a>
    </div>

    <form class="st-card" method="post" enctype="multipart/form-data">
        <?php studio_form_fields($csrf, $courseId, 'save_lesson'); ?>
        <input type="hidden" name="lesson_id" value="<?= (int)$openLesson['id'] ?>">

        <div class="st-field">
            <label for="lesson_title">Title</label>
            <input id="lesson_title" name="title" type="text" required maxlength="200"
                   value="<?= studio_e($openLesson['title']) ?>">
        </div>

        <div class="st-field">
            <label for="content_html">Body</label>
            <textarea id="content_html" name="content_html" rows="18" class="st-mono"><?= studio_e($openLesson['content_html']) ?></textarea>
            <span class="st-hint">
                HTML. Use &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;img&gt;, &lt;iframe&gt; and so on.
                Learners see exactly this markup.
            </span>
        </div>

        <div class="st-row">
            <div class="st-field">
                <label for="video_url">Video URL</label>
                <input id="video_url" name="video_url" type="url" maxlength="500"
                       value="<?= studio_e($openLesson['video_url']) ?>"
                       placeholder="https://...">
            </div>
            <div class="st-field">
                <label for="duration_minutes">Length (minutes)</label>
                <input id="duration_minutes" name="duration_minutes" type="number" min="1"
                       value="<?= $openLesson['duration_minutes'] !== null ? (int)$openLesson['duration_minutes'] : '' ?>">
            </div>
        </div>

        <div class="st-field">
            <label for="attachment">Attachment (PDF)</label>
            <input id="attachment" name="attachment" type="file" accept="application/pdf">
            <?php if (!empty($openLesson['attachment_path'])): ?>
                <span class="st-hint">
                    Currently: <a href="<?= studio_e($openLesson['attachment_path']) ?>" target="_blank" rel="noopener">
                        <?= studio_e(basename((string)$openLesson['attachment_path'])) ?></a>.
                    Uploading another replaces it.
                </span>
            <?php endif; ?>
        </div>

        <div class="st-actions">
            <button class="st-btn" type="submit"><i class="fas fa-floppy-disk"></i> Save lesson</button>
        </div>
    </form>

    <div class="st-card">
        <h3>Remove things</h3>
        <div class="st-actions" style="margin-top:10px;">
            <?php if (!empty($openLesson['attachment_path'])): ?>
                <form method="post">
                    <?php studio_form_fields($csrf, $courseId, 'remove_attachment'); ?>
                    <input type="hidden" name="lesson_id" value="<?= (int)$openLesson['id'] ?>">
                    <button class="st-btn st-btn-ghost st-btn-small" type="submit">
                        <i class="fas fa-paperclip"></i> Remove attachment
                    </button>
                </form>
            <?php endif; ?>
            <form method="post"
                  onsubmit="return confirm('Delete this lesson? Any learner progress on it is deleted too.');">
                <?php studio_form_fields($csrf, $courseId, 'delete_lesson'); ?>
                <input type="hidden" name="lesson_id" value="<?= (int)$openLesson['id'] ?>">
                <button class="st-btn st-btn-danger st-btn-small" type="submit">
                    <i class="fas fa-trash"></i> Delete lesson
                </button>
            </form>
        </div>
    </div>

<?php elseif ($openAssessment !== null):
    $questions = catalogue_questions($conn, (int)$openAssessment['id']);
    $editingQuestion = null;
    if ((int)($_GET['question'] ?? 0) > 0) {
        $candidate = catalogue_question_in_course($conn, (int)$_GET['question'], $courseId);
        if ($candidate !== null && (int)$candidate['assessment_id'] === (int)$openAssessment['id']) {
            $editingQuestion = $candidate;
        }
    }

    // The form below is the same for adding and editing, so it needs values for
    // both cases up front.
    $qType = (string)($editingQuestion['type'] ?? 'single');
    $qOptions = [];
    if ($editingQuestion !== null && !empty($editingQuestion['options'])) {
        $decoded = json_decode((string)$editingQuestion['options'], true);
        if (is_array($decoded)) {
            $qOptions = $decoded;
        }
    }
    // Stored keys are text; the form marks correctness by option index. Only a
    // multiple-answer key is a comma-separated list - a single-answer key is one
    // option verbatim, and splitting it would fail to match an option that
    // legitimately contains a comma.
    if ($editingQuestion === null) {
        $qCorrect = [];
    } elseif ($qType === 'multiple') {
        $qCorrect = array_map('trim', explode(',', (string)$editingQuestion['correct_answer']));
    } else {
        $qCorrect = [(string)$editingQuestion['correct_answer']];
    }
    $optionSlots = max(4, count($qOptions) + 1);
    ?>
    <?php // ── Assessment editor ─────────────────────────────────────────── ?>
    <div class="st-page-head">
        <div>
            <h1>Assessment</h1>
            <p class="st-sub">
                <?= count($questions) ?> question<?= count($questions) === 1 ? '' : 's' ?>,
                <?= array_sum(array_map(static fn($q) => (int)$q['marks'], $questions)) ?> marks in total.
            </p>
        </div>
        <a class="st-btn st-btn-ghost" href="<?= studio_e(studio_back($courseId)) ?>">
            <i class="fas fa-arrow-left"></i> Back to the outline
        </a>
    </div>

    <form class="st-card" method="post">
        <?php studio_form_fields($csrf, $courseId, 'save_assessment'); ?>
        <input type="hidden" name="assessment_id" value="<?= (int)$openAssessment['id'] ?>">

        <div class="st-field">
            <label for="assessment_title">Title</label>
            <input id="assessment_title" name="title" type="text" required maxlength="200"
                   value="<?= studio_e($openAssessment['title']) ?>">
        </div>

        <div class="st-field">
            <label for="instructions">Instructions</label>
            <textarea id="instructions" name="instructions" rows="3"><?= studio_e($openAssessment['instructions']) ?></textarea>
            <span class="st-hint">Shown as plain text above the questions.</span>
        </div>

        <div class="st-row">
            <div class="st-field">
                <label for="assessment_module">Attached to</label>
                <select id="assessment_module" name="module_id">
                    <option value="0"<?= $openAssessment['module_id'] === null ? ' selected' : '' ?>>
                        The course (a final assessment)
                    </option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= (int)$module['id'] ?>"
                            <?= (int)$openAssessment['module_id'] === (int)$module['id'] ? ' selected' : '' ?>>
                            <?= studio_e($module['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="st-field">
                <label for="assessment_pass">Pass mark (%)</label>
                <input id="assessment_pass" name="pass_mark" type="number" min="1" max="100"
                       value="<?= $openAssessment['pass_mark'] !== null ? (int)$openAssessment['pass_mark'] : '' ?>"
                       placeholder="<?= (int)$course['pass_mark'] ?>">
                <span class="st-hint">Blank uses the course pass mark (<?= (int)$course['pass_mark'] ?>%).</span>
            </div>
            <div class="st-field">
                <label for="max_attempts">Attempts allowed</label>
                <input id="max_attempts" name="max_attempts" type="number" min="0" max="100"
                       value="<?= (int)$openAssessment['max_attempts'] ?>">
                <span class="st-hint">0 means unlimited.</span>
            </div>
        </div>

        <div class="st-actions">
            <button class="st-btn" type="submit"><i class="fas fa-floppy-disk"></i> Save assessment</button>
        </div>
    </form>

    <div class="st-card">
        <h2>Questions</h2>
        <?php if (!$questions): ?>
            <div class="st-empty">
                No questions yet. An assessment with no questions can never be passed, which blocks
                the whole course from being completed — so this one has to be written before you publish.
            </div>
        <?php else: ?>
            <ul class="st-items">
                <?php foreach ($questions as $index => $question):
                    $options = [];
                    if (!empty($question['options'])) {
                        $decoded = json_decode((string)$question['options'], true);
                        if (is_array($decoded)) {
                            $options = $decoded;
                        }
                    }
                    $typeLabels = [
                        'single' => 'One answer',
                        'multiple' => 'Several answers',
                        'true_false' => 'True or false',
                        'short_text' => 'Short text',
                    ];
                    ?>
                    <li class="st-item">
                        <span class="st-item-icon st-quiz"><?= $index + 1 ?></span>
                        <div class="st-item-main">
                            <strong><?= studio_e($question['question']) ?></strong>
                            <span class="st-item-note">
                                <?= studio_e($typeLabels[$question['type']] ?? $question['type']) ?>
                                · <?= (int)$question['marks'] ?> mark<?= (int)$question['marks'] === 1 ? '' : 's' ?>
                                <?php if ($options): ?>
                                    · <?= count($options) ?> options
                                <?php endif; ?>
                                · answer: <?= studio_e((string)$question['correct_answer']) ?>
                            </span>
                        </div>
                        <div class="st-actions">
                            <?php studio_move_buttons(
                                $csrf,
                                $courseId,
                                'move_question',
                                'question_id',
                                (int)$question['id'],
                                $index === 0,
                                $index === count($questions) - 1
                            ); ?>
                            <a class="st-btn st-btn-ghost st-btn-small"
                               href="<?= studio_e(studio_back($courseId, [
                                   'assessment' => (int)$openAssessment['id'],
                                   'question' => (int)$question['id'],
                               ])) ?>#question-form">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this question?');">
                                <?php studio_form_fields($csrf, $courseId, 'delete_question'); ?>
                                <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                                <button class="st-btn st-btn-danger st-btn-small" type="submit">Delete</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="st-card" id="question-form">
        <h2><?= $editingQuestion !== null ? 'Edit question' : 'Add a question' ?></h2>

        <form method="post" style="margin-top:14px;">
            <?php studio_form_fields($csrf, $courseId, $editingQuestion !== null ? 'save_question' : 'add_question'); ?>
            <?php if ($editingQuestion !== null): ?>
                <input type="hidden" name="question_id" value="<?= (int)$editingQuestion['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="assessment_id" value="<?= (int)$openAssessment['id'] ?>">
            <?php endif; ?>

            <div class="st-field">
                <label for="question">Question</label>
                <textarea id="question" name="question" rows="2" required><?= studio_e($editingQuestion['question'] ?? '') ?></textarea>
            </div>

            <div class="st-row">
                <div class="st-field">
                    <label for="question_type">Type</label>
                    <select id="question_type" name="type" onchange="studioSyncType()">
                        <option value="single"<?= $qType === 'single' ? ' selected' : '' ?>>One correct answer</option>
                        <option value="multiple"<?= $qType === 'multiple' ? ' selected' : '' ?>>Several correct answers</option>
                        <option value="true_false"<?= $qType === 'true_false' ? ' selected' : '' ?>>True or false</option>
                        <option value="short_text"<?= $qType === 'short_text' ? ' selected' : '' ?>>Short text</option>
                    </select>
                </div>
                <div class="st-field">
                    <label for="marks">Marks</label>
                    <input id="marks" name="marks" type="number" min="1" max="1000"
                           value="<?= (int)($editingQuestion['marks'] ?? 1) ?>">
                </div>
            </div>

            <div class="st-field" id="choiceBlock">
                <label>Options — tick the correct one<span id="choicePlural">s</span></label>
                <?php for ($slot = 0; $slot < $optionSlots; $slot++):
                    $text = $qOptions[$slot] ?? '';
                    $ticked = $text !== '' && in_array($text, $qCorrect, true);
                    ?>
                    <div class="st-option">
                        <label class="st-option-mark">
                            <input type="checkbox" class="studio-correct" name="correct[]" value="<?= $slot ?>"
                                <?= $ticked ? ' checked' : '' ?>>
                            correct
                        </label>
                        <input type="text" name="options[<?= $slot ?>]" maxlength="255"
                               value="<?= studio_e((string)$text) ?>"
                               placeholder="Option <?= $slot + 1 ?><?= $slot > 1 ? ' (optional)' : '' ?>">
                    </div>
                <?php endfor; ?>
                <span class="st-hint">
                    Blank options are ignored. For a several-answers question, options cannot contain a
                    comma — the answer key is a comma-separated list.
                </span>
            </div>

            <div class="st-field" id="trueFalseBlock">
                <label for="tf_answer">Correct answer</label>
                <select id="tf_answer" name="correct_answer_tf">
                    <option value="True"<?= ($qType === 'true_false' && ($editingQuestion['correct_answer'] ?? 'True') !== 'False') ? ' selected' : '' ?>>True</option>
                    <option value="False"<?= ($qType === 'true_false' && ($editingQuestion['correct_answer'] ?? '') === 'False') ? ' selected' : '' ?>>False</option>
                </select>
            </div>

            <div class="st-field" id="shortTextBlock">
                <label for="short_answer">Expected answer</label>
                <input id="short_answer" name="correct_answer_text" type="text"
                       value="<?= $qType === 'short_text' ? studio_e($editingQuestion['correct_answer'] ?? '') : '' ?>">
                <span class="st-hint">Marked case- and whitespace-insensitively, but otherwise exactly.</span>
            </div>

            <!--
              The true/false and short-text answers post under their own field
              names rather than sharing one hidden input filled in by script. The
              server picks the one the chosen type uses, so the form still saves
              the right answer with JavaScript disabled - a shared hidden field
              would silently save an empty or stale one.
            -->

            <div class="st-actions">
                <button class="st-btn" type="submit">
                    <i class="fas fa-<?= $editingQuestion !== null ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $editingQuestion !== null ? 'Save question' : 'Add question' ?>
                </button>
                <?php if ($editingQuestion !== null): ?>
                    <a class="st-btn st-btn-ghost"
                       href="<?= studio_e(studio_back($courseId, ['assessment' => (int)$openAssessment['id']])) ?>#question-form">
                        Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
    /**
     * Show only the answer fields the chosen question type uses, and keep the
     * single-answer case to one tick.
     */
    function studioSyncType() {
        const type = document.getElementById('question_type').value;
        document.getElementById('choiceBlock').style.display = (type === 'single' || type === 'multiple') ? '' : 'none';
        document.getElementById('trueFalseBlock').style.display = type === 'true_false' ? '' : 'none';
        document.getElementById('shortTextBlock').style.display = type === 'short_text' ? '' : 'none';
        document.getElementById('choicePlural').style.display = type === 'multiple' ? '' : 'none';

        const boxes = document.querySelectorAll('.studio-correct');
        if (type === 'single') {
            let seen = false;
            boxes.forEach(box => {
                if (box.checked && seen) { box.checked = false; }
                if (box.checked) { seen = true; }
            });
        }
    }

    document.querySelectorAll('.studio-correct').forEach(box => {
        box.addEventListener('change', () => {
            if (document.getElementById('question_type').value !== 'single' || !box.checked) { return; }
            // One answer means one tick, so ticking a second clears the first.
            document.querySelectorAll('.studio-correct').forEach(other => {
                if (other !== box) { other.checked = false; }
            });
        });
    });

    studioSyncType();
    </script>

<?php else: ?>
    <?php // ── Outline ───────────────────────────────────────────────────── ?>
    <div class="st-page-head">
        <div>
            <h1><?= studio_e($course['title']) ?></h1>
            <p class="st-sub">
                <span class="st-chip <?= $isPublished ? 'st-chip-live' : 'st-chip-draft' ?>">
                    <i class="fas <?= $isPublished ? 'fa-circle-check' : 'fa-pen-ruler' ?>"></i>
                    <?= $isPublished ? 'Published' : 'Draft' ?>
                </span>
                Public URL: <code>/learn/course.php?c=<?= studio_e($course['slug']) ?></code>
            </p>
        </div>
        <div class="st-actions">
            <?php if ($isPublished): ?>
                <a class="st-btn st-btn-ghost" target="_blank" rel="noopener"
                   href="/learn/course.php?c=<?= urlencode((string)$course['slug']) ?>">
                    <i class="fas fa-arrow-up-right-from-square"></i> View live
                </a>
                <form method="post">
                    <?php studio_form_fields($csrf, $courseId, 'unpublish'); ?>
                    <button class="st-btn st-btn-amber" type="submit"><i class="fas fa-eye-slash"></i> Unpublish</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <?php studio_form_fields($csrf, $courseId, 'publish'); ?>
                    <button class="st-btn st-btn-green" type="submit"><i class="fas fa-rocket"></i> Publish</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $blockers = catalogue_publish_blockers($conn, $courseId);
    if (!$isPublished && $blockers):
        ?>
        <div class="st-flash st-flash-info">
            <i class="fas fa-circle-info"></i>
            <div>
                <div>Before this course can be published:</div>
                <ul class="st-flash-list">
                    <?php foreach ($blockers as $blocker): ?>
                        <li><?= studio_e($blocker) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="st-card">
        <h2>Modules and lessons</h2>
        <p class="st-sub">
            Learners work through this in order. A course is complete — and a certificate issued —
            when every lesson is marked done and every assessment passed.
        </p>
    </div>

    <?php foreach ($modules as $moduleIndex => $module):
        $lessons = $module['lessons'];
        $moduleAssessments = $module['assessments'];
        ?>
        <section class="st-module">
            <div class="st-module-head">
                <span class="st-item-icon"><?= $moduleIndex + 1 ?></span>
                <div class="st-module-title">
                    <h3><?= studio_e($module['title']) ?></h3>
                    <?php if (!empty($module['summary'])): ?>
                        <p><?= studio_e($module['summary']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="st-actions">
                    <?php studio_move_buttons(
                        $csrf,
                        $courseId,
                        'move_module',
                        'module_id',
                        (int)$module['id'],
                        $moduleIndex === 0,
                        $moduleIndex === count($modules) - 1
                    ); ?>
                    <form method="post"
                          onsubmit="return confirm('Delete this module and its <?= count($lessons) ?> lesson(s)? Learner progress on those lessons is deleted too.');">
                        <?php studio_form_fields($csrf, $courseId, 'delete_module'); ?>
                        <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
                        <button class="st-btn st-btn-danger st-btn-small" type="submit">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!$lessons && !$moduleAssessments): ?>
                <div class="st-empty">Nothing in this module yet.</div>
            <?php else: ?>
                <ul class="st-items">
                    <?php foreach ($lessons as $lessonIndex => $lesson): ?>
                        <li class="st-item">
                            <span class="st-item-icon"><i class="fas fa-file-lines"></i></span>
                            <div class="st-item-main">
                                <strong><?= studio_e($lesson['title']) ?></strong>
                                <span class="st-item-note">
                                    <?php
                                    $notes = [];
                                    if ($lesson['duration_minutes'] !== null) {
                                        $notes[] = (int)$lesson['duration_minutes'] . ' min';
                                    }
                                    if (trim((string)$lesson['content_html']) === '') {
                                        $notes[] = 'no body written yet';
                                    }
                                    if (!empty($lesson['video_url'])) {
                                        $notes[] = 'video';
                                    }
                                    if (!empty($lesson['attachment_path'])) {
                                        $notes[] = 'attachment';
                                    }
                                    echo studio_e($notes ? implode(' · ', $notes) : 'lesson');
                                    ?>
                                </span>
                            </div>
                            <div class="st-actions">
                                <?php studio_move_buttons(
                                    $csrf,
                                    $courseId,
                                    'move_lesson',
                                    'lesson_id',
                                    (int)$lesson['id'],
                                    $lessonIndex === 0,
                                    $lessonIndex === count($lessons) - 1
                                ); ?>
                                <a class="st-btn st-btn-ghost st-btn-small"
                                   href="<?= studio_e(studio_back($courseId, ['lesson' => (int)$lesson['id']])) ?>">
                                    <i class="fas fa-pen"></i> Edit
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>

                    <?php foreach ($moduleAssessments as $assessment): ?>
                        <li class="st-item">
                            <span class="st-item-icon st-quiz"><i class="fas fa-list-check"></i></span>
                            <div class="st-item-main">
                                <strong><?= studio_e($assessment['title']) ?></strong>
                                <span class="st-item-note">
                                    assessment · <?= (int)$assessment['question_count'] ?> question<?= (int)$assessment['question_count'] === 1 ? '' : 's' ?>
                                    · pass <?= $assessment['pass_mark'] !== null ? (int)$assessment['pass_mark'] : (int)$course['pass_mark'] ?>%
                                    · <?= (int)$assessment['max_attempts'] === 0 ? 'unlimited attempts' : (int)$assessment['max_attempts'] . ' attempts' ?>
                                </span>
                            </div>
                            <div class="st-actions">
                                <a class="st-btn st-btn-ghost st-btn-small"
                                   href="<?= studio_e(studio_back($courseId, ['assessment' => (int)$assessment['id']])) ?>">
                                    <i class="fas fa-pen"></i> Edit
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <details class="st-inline-form">
                <summary><i class="fas fa-plus"></i> Add a lesson to this module</summary>
                <form method="post">
                    <?php studio_form_fields($csrf, $courseId, 'add_lesson'); ?>
                    <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
                    <div class="st-row">
                        <div class="st-field">
                            <label>Lesson title</label>
                            <input name="title" type="text" required maxlength="200">
                        </div>
                        <div class="st-field" style="flex:0 0 150px;">
                            <label>Minutes</label>
                            <input name="duration_minutes" type="number" min="1">
                        </div>
                    </div>
                    <button class="st-btn st-btn-small" type="submit">Add lesson</button>
                </form>
            </details>

            <details class="st-inline-form">
                <summary><i class="fas fa-plus"></i> Add an assessment to this module</summary>
                <form method="post">
                    <?php studio_form_fields($csrf, $courseId, 'add_assessment'); ?>
                    <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
                    <div class="st-row">
                        <div class="st-field">
                            <label>Assessment title</label>
                            <input name="title" type="text" required maxlength="200">
                        </div>
                        <div class="st-field" style="flex:0 0 150px;">
                            <label>Pass mark (%)</label>
                            <input name="pass_mark" type="number" min="1" max="100"
                                   placeholder="<?= (int)$course['pass_mark'] ?>">
                        </div>
                        <div class="st-field" style="flex:0 0 150px;">
                            <label>Attempts</label>
                            <input name="max_attempts" type="number" min="0" max="100" value="0">
                        </div>
                    </div>
                    <button class="st-btn st-btn-small" type="submit">Add assessment</button>
                </form>
            </details>
        </section>
    <?php endforeach; ?>

    <?php if (!$modules): ?>
        <div class="st-card">
            <div class="st-empty">No modules yet. Add the first one below.</div>
        </div>
    <?php endif; ?>

    <div class="st-card">
        <h3>Add a module</h3>
        <form method="post" style="margin-top:12px;">
            <?php studio_form_fields($csrf, $courseId, 'add_module'); ?>
            <div class="st-row">
                <div class="st-field">
                    <label>Title</label>
                    <input name="title" type="text" required maxlength="200" placeholder="e.g. Getting started">
                </div>
                <div class="st-field">
                    <label>Summary</label>
                    <input name="summary" type="text" maxlength="400">
                </div>
            </div>
            <button class="st-btn" type="submit"><i class="fas fa-plus"></i> Add module</button>
        </form>
    </div>

    <div class="st-card">
        <h2>Final assessments</h2>
        <p class="st-sub">Assessments that cover the whole course rather than one module.</p>

        <?php if (!$finalAssessments): ?>
            <div class="st-empty">None. A course does not need one.</div>
        <?php else: ?>
            <ul class="st-items">
                <?php foreach ($finalAssessments as $index => $assessment): ?>
                    <li class="st-item">
                        <span class="st-item-icon st-quiz"><i class="fas fa-graduation-cap"></i></span>
                        <div class="st-item-main">
                            <strong><?= studio_e($assessment['title']) ?></strong>
                            <span class="st-item-note">
                                <?= (int)$assessment['question_count'] ?> question<?= (int)$assessment['question_count'] === 1 ? '' : 's' ?>
                                · pass <?= $assessment['pass_mark'] !== null ? (int)$assessment['pass_mark'] : (int)$course['pass_mark'] ?>%
                            </span>
                        </div>
                        <div class="st-actions">
                            <a class="st-btn st-btn-ghost st-btn-small"
                               href="<?= studio_e(studio_back($courseId, ['assessment' => (int)$assessment['id']])) ?>">
                                <i class="fas fa-pen"></i> Edit
                            </a>
                            <form method="post" onsubmit="return confirm('Delete this assessment and every attempt at it?');">
                                <?php studio_form_fields($csrf, $courseId, 'delete_assessment'); ?>
                                <input type="hidden" name="assessment_id" value="<?= (int)$assessment['id'] ?>">
                                <button class="st-btn st-btn-danger st-btn-small" type="submit">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <details class="st-inline-form" style="border-top:none; background:transparent; padding-left:0; padding-right:0;">
            <summary><i class="fas fa-plus"></i> Add a final assessment</summary>
            <form method="post">
                <?php studio_form_fields($csrf, $courseId, 'add_assessment'); ?>
                <input type="hidden" name="module_id" value="0">
                <div class="st-row">
                    <div class="st-field">
                        <label>Title</label>
                        <input name="title" type="text" required maxlength="200" placeholder="e.g. Final exam">
                    </div>
                    <div class="st-field" style="flex:0 0 150px;">
                        <label>Pass mark (%)</label>
                        <input name="pass_mark" type="number" min="1" max="100"
                               placeholder="<?= (int)$course['pass_mark'] ?>">
                    </div>
                    <div class="st-field" style="flex:0 0 150px;">
                        <label>Attempts</label>
                        <input name="max_attempts" type="number" min="0" max="100" value="0">
                    </div>
                </div>
                <button class="st-btn st-btn-small" type="submit">Add assessment</button>
            </form>
        </details>
    </div>

    <form class="st-card" method="post" enctype="multipart/form-data">
        <?php studio_form_fields($csrf, $courseId, 'save_course'); ?>
        <h2>Course details</h2>

        <div class="st-field">
            <label for="c_title">Title</label>
            <input id="c_title" name="title" type="text" required maxlength="200"
                   value="<?= studio_e($course['title']) ?>">
            <?php if ($isPublished): ?>
                <span class="st-hint">
                    The public URL stays <code>/learn/course.php?c=<?= studio_e($course['slug']) ?></code>
                    even if you rename the course, so existing links keep working.
                </span>
            <?php endif; ?>
        </div>

        <div class="st-field">
            <label for="c_summary">Summary</label>
            <input id="c_summary" name="summary" type="text" maxlength="400"
                   value="<?= studio_e($course['summary']) ?>">
        </div>

        <div class="st-field">
            <label for="c_description">Description</label>
            <textarea id="c_description" name="description" rows="5"><?= studio_e($course['description']) ?></textarea>
        </div>

        <div class="st-row">
            <div class="st-field">
                <label for="c_level">Level</label>
                <select id="c_level" name="level">
                    <?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label): ?>
                        <option value="<?= $value ?>"<?= $course['level'] === $value ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="st-field">
                <label for="c_hours">Estimated hours</label>
                <input id="c_hours" name="estimated_hours" type="number" step="0.5" min="0.5"
                       value="<?= $course['estimated_hours'] !== null ? (float)$course['estimated_hours'] : '' ?>">
            </div>
            <div class="st-field">
                <label for="c_pass">Pass mark (%)</label>
                <input id="c_pass" name="pass_mark" type="number" min="1" max="100" required
                       value="<?= (int)$course['pass_mark'] ?>">
            </div>
        </div>

        <label class="st-check">
            <input type="checkbox" name="certificate_enabled" value="1"
                <?= (int)$course['certificate_enabled'] === 1 ? ' checked' : '' ?>>
            <div>
                Award a certificate on completion
                <span>Certificates already issued are not withdrawn by turning this off.</span>
            </div>
        </label>

        <div class="st-field">
            <label for="cover">Cover image</label>
            <input id="cover" name="cover" type="file" accept="image/*">
            <?php if (!empty($course['cover_image'])): ?>
                <span class="st-hint">Uploading another replaces the current one.</span>
                <div style="margin-top:10px;">
                    <img src="<?= studio_e(studio_asset_url($course['cover_image'])) ?>" alt=""
                         style="max-width:240px; border-radius:10px; border:1px solid var(--st-line);">
                </div>
            <?php endif; ?>
        </div>

        <div class="st-actions">
            <button class="st-btn" type="submit"><i class="fas fa-floppy-disk"></i> Save details</button>
        </div>
    </form>

    <?php if (!empty($course['cover_image'])): ?>
        <form method="post" style="margin:-10px 0 20px;">
            <?php studio_form_fields($csrf, $courseId, 'remove_cover'); ?>
            <button class="st-btn st-btn-ghost st-btn-small" type="submit">
                <i class="fas fa-image"></i> Remove cover image
            </button>
        </form>
    <?php endif; ?>

    <div class="st-card">
        <h3>Delete this course</h3>
        <p class="st-sub">
            <?php if (catalogue_course_has_learners($conn, $courseId)): ?>
                Learners have enrolled, so this course cannot be deleted — deleting it would erase their
                progress and revoke their certificates. Unpublish it instead.
            <?php else: ?>
                Nobody has enrolled yet, so this removes the course and everything in it.
            <?php endif; ?>
        </p>
        <?php if (!catalogue_course_has_learners($conn, $courseId)): ?>
            <form method="post" style="margin-top:12px;"
                  onsubmit="return confirm('Delete this course and all of its content?');">
                <?php studio_form_fields($csrf, $courseId, 'delete_course'); ?>
                <button class="st-btn st-btn-danger" type="submit"><i class="fas fa-trash"></i> Delete course</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php
studio_foot();
