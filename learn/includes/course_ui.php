<?php
/**
 * Two-pane "teach"-style layout for a public short course page.
 *
 * Left sidebar = course structure tree (module → lesson → topic → subtopic →
 * assessment), every node selectable. Right main panel = details of the
 * currently selected item.
 *
 * Selection is query-driven (?view=module-5 | lesson-7 | topic-12 | assessment-9)
 * so choices are deep-linkable and no client-side state is needed.
 *
 * Requires in scope: $conn, $courseId, $slug, $course, $modules, $finals,
 * $enrolled, $learner, $progress, $doneLessons, $openLessonId.
 */

// ── Selection ----------------------------------------------------------
$viewType = 'lesson';
$viewId   = $openLessonId;
if (isset($_GET['view'])) {
    $vp = explode('-', (string)$_GET['view'], 2);
    if (count($vp) === 2 && in_array($vp[0], ['module', 'lesson', 'topic', 'assessment'], true)) {
        $id = (int)$vp[1];
        if ($id > 0) {
            $viewType = $vp[0];
            $viewId   = $id;
        }
    }
}
$viewAssessPassed = null; // passed flag for the selected item when it is an assessment

// Build a friendly URL to select an item.
$uiUrl = static function (string $type, int $id) use ($slug): string {
    return '/learn/course.php?c=' . rawurlencode($slug) . '&view=' . $type . '-' . $id;
};
// ── Data ----------------------------------------------------------------
// All assessments for the course, plus the learner's passing status.
$assessments = [];
$passedAssessIds = [];
$stmt = $conn->prepare("
    SELECT id, module_id, lesson_id, title, type, position
    FROM public_course_assessments
    WHERE course_id = ?
    ORDER BY position, id
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
foreach ($stmt->get_result() as $a) {
    $assessments[(int)$a['id']] = $a;
    if ($enrolled) {
        $passedAssessIds[(int)$a['id']] = false;
    }
}
$stmt->close();

if ($enrolled) {
    $ids = array_keys($passedAssessIds);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $ps = $conn->prepare("SELECT assessment_id, MAX(passed) p FROM external_assessment_attempts WHERE learner_id = ? AND assessment_id IN ($ph) GROUP BY assessment_id");
        $ps->bind_param('i' . str_repeat('i', count($ids)), $learner['id'], ...$ids);
        $ps->execute();
        while ($r = $ps->get_result()->fetch_assoc()) {
            $passedAssessIds[(int)$r['assessment_id']] = (int)$r['p'] === 1;
        }
        $ps->close();
    }
}

// Read-topics set for the learner (all lesson topics in this course).
$readTopicIds = [];
if ($enrolled) {
    $rs = $conn->prepare("
        SELECT DISTINCT p.topic_id
        FROM external_lesson_topic_progress p
        JOIN public_course_lesson_topics t ON t.id = p.topic_id
        JOIN public_course_lessons l ON l.id = t.lesson_id
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE p.learner_id = ? AND m.course_id = ?
    ");
    $rs->bind_param('ii', $learner['id'], $courseId);
    $rs->execute();
    foreach ($rs->get_result() as $r) {
        $readTopicIds[] = (int)$r['topic_id'];
    }
    $rs->close();
}
// Build a fully populated module tree: each lesson gains its topics (with
// subtopics) and its linked assessments.
$uiModules = [];
foreach ($modules as $m) {
    $mod = [
        'id' => (int)$m['id'],
        'title' => (string)$m['title'],
        'summary' => (string)($m['summary'] ?? ''),
        'lessons' => [],
        'assessments' => [],
    ];
    foreach (($m['lessons'] ?? []) as $l) {
        $lid = (int)$l['id'];
        $mod['lessons'][] = [
            'id' => $lid,
            'title' => (string)$l['title'],
            'duration_minutes' => (int)($l['duration_minutes'] ?? 0),
            'topics' => learn_lesson_topics($conn, $lid),
            'assessments' => array_values(array_filter($assessments, static fn($a) => (int)$a['lesson_id'] === $lid)),
        ];
    }
    $mod['assessments'] = array_values(array_filter($assessments, static fn($a) => (int)$a['module_id'] === (int)$m['id'] && (int)$a['lesson_id'] === 0));
    $uiModules[] = $mod;
}
$uiFinals = array_values(array_filter($assessments, static fn($a) => (int)$a['module_id'] === 0));

// ── Individual counts for the summary ------------------------------------------------------------------
$uiTotalLessons = 0;
$uiTotalTopLevels = 0;
$uiTotalSubTopLevels = 0;
$uiTotalAssess = count($uiFinals);
foreach ($uiModules as $m) {
    $uiTotalLessons += count($m['lessons']);
    foreach ($m['lessons'] as $l) {
        foreach ($l['topics'] as $t) {
            $uiTotalTopLevels++;
            $uiTotalSubTopLevels += count($t['subs'] ?? []);
        }
    }
    $uiTotalAssess += count($m['assessments']);
    foreach ($m['lessons'] as $l) { $uiTotalAssess += count($l['assessments']); }
}
// ── Resolve the selected item for the main panel ---------------------------------------------------------
$selModule = null;
$selLesson = null;
$selTopic  = null;
$selAssess = null;

foreach ($uiModules as $m) {
    if ($viewType === 'module' && $viewId === (int)$m['id']) { $selModule = $m; break; }
    if ($viewType === 'lesson') {
        foreach ($m['lessons'] as $l) { if ($viewId === (int)$l['id']) { $selLesson = $l; $selModule = $m; break 2; } }
    }
    if ($viewType === 'topic') {
        foreach ($m['lessons'] as $l) { foreach ($l['topics'] as $t) { if ($viewId === (int)$t['id']) { $selLesson = $l; $selModule = $m; $selTopic = $t; break 3; } } }
    }
    if ($viewType === 'assessment') {
        $cands = array_merge($m['assessments'], ...array_map(static fn($l) => $l['assessments'], $m['lessons']));
        foreach ($cands as $a) { if ($viewId === (int)$a['id']) { $selAssess = $a; $selModule = $m; break 2; } }
    }
}
if ($viewType === 'assessment' && $selAssess === null) {
    foreach ($uiFinals as $a) { if ($viewId === (int)$a['id']) { $selAssess = $a; $selModule = null; break; } }
}
if ($viewType === 'assessment' && $selAssess === null) {
    foreach ($uiModules as $m) { foreach ($m['lessons'] as $l) { foreach ($l['assessments'] as $a) { if ($viewId === (int)$a['id']) { $selAssess = $a; $selLesson = $l; $selModule = $m; break 3; } } } }
}
// Default: the open lesson (first incomplete), else first module.
if ($selLesson === null && $selModule === null && $selTopic === null && $selAssess === null) {
    foreach ($uiModules as $m) { foreach ($m['lessons'] as $l) { if ((int)$l['id'] === $openLessonId) { $selLesson = $l; $selModule = $m; break 2; } } }
    if ($selLesson === null && $uiModules) {
        $selModule = $uiModules[0];
        if ($selModule['lessons']) { $selLesson = $selModule['lessons'][0]; }
    }
}
if ($selAssess !== null) { $viewAssessPassed = $passedAssessIds[(int)$selAssess['id']] ?? false; }
?>
<style>
.ln-tp{display:grid;grid-template-columns:320px minmax(0,1fr);gap:22px;align-items:start;}
.ln-tp-side{border:1px solid var(--ln-line);border-radius:var(--ln-r);padding:14px;position:sticky;top:16px;max-height:calc(100vh - 40px);overflow:auto;}
.ln-tp-side-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:6px 4px 12px;border-bottom:1px solid var(--ln-line);margin-bottom:12px;}
.ln-tp-side-head h2{margin:0;font-size:1rem;color:var(--ln-ink);display:inline-flex;align-items:center;gap:8px;}
.ln-tp-prog{margin:0 2px 14px;font-size:.8rem;color:var(--ln-muted);}
.ln-tp-prog .ln-bar{margin:0 0 6px;}
.ln-tp-tree{display:flex;flex-direction:column;gap:8px;}
.ln-tp-module,.ln-tp-lesson{border:1px solid var(--ln-line);border-radius:10px;background:var(--ln-surface, #fff);}
.ln-tp-module summary,.ln-tp-lesson summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;color:var(--ln-ink);font-weight:600;font-size:.85rem;}
.ln-tp-module summary::-webkit-details-marker,.ln-tp-lesson summary::-webkit-details-marker{display:none;}
.ln-tp-module summary:hover,.ln-tp-lesson summary:hover{background:var(--ln-bg);}
.ln-tp-module summary .mat,.ln-tp-lesson summary .mat{font-size:18px;color:var(--ln-green-mid);}
.ln-tp-module summary .grow,.ln-tp-lesson summary .grow{flex:1;min-width:0;}
.ln-tp-module summary .st,.ln-tp-lesson summary .st{font-size:15px;color:var(--ln-muted);margin-left:auto;}
.ln-tp-module summary .st.done,.ln-tp-lesson summary .st.done{color:var(--ln-success);}
.ln-tp-module-body,.ln-tp-lesson-body{padding:4px 8px 10px 10px;border-top:1px solid var(--ln-line);}
.ln-tp-node{display:flex;align-items:flex-start;gap:8px;padding:6px 8px;border-radius:8px;color:var(--ln-ink);text-decoration:none;font-size:.88rem;line-height:1.25;}
.ln-tp-node .mat{flex:none;font-size:18px;margin-top:1px;}
.ln-tp-node:hover{background:var(--ln-bg);}
.ln-tp-node.active{box-shadow:inset 0 0 0 1px var(--ln-green-mid);}
.ln-tp-node .grow{flex:1;min-width:0;}
.ln-tp-node .st{font-size:15px;color:var(--ln-muted);margin-left:auto;}
.ln-tp-node .st.done{color:var(--ln-success);}
.ln-mod{padding:9px 6px;font-weight:700;color:var(--ln-green-mid);border-radius:8px;}
.ln-lvl2{padding-left:10px;border-left:2px solid var(--ln-line);margin-left:8px;}
.ln-lvl3{padding-left:10px;border-left:1px dashed var(--ln-line);margin-left:8px;}
.ln-tp-main{min-width:0;}
.ln-tp-head{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
.ln-tp-head .k{margin-left:auto;display:flex;gap:8px;align-items:center;}
.ln-tp-head h1{margin:0;font-size:1.3rem;color:var(--ln-ink);}
.ln-tp-item{border:1px solid var(--ln-line);border-radius:var(--ln-r);padding:20px 22px;}
.ln-topic-block{border:1px solid var(--ln-line);border-radius:10px;padding:13px 14px;margin:14px 0;}
.ln-subs{margin:8px 0 0;padding-left:16px;border-left:1px dashed var(--ln-line);}
.ln-subrow{display:flex;gap:8px;padding:3px 0;font-size:.85rem;color:var(--ln-body);}
.ln-assess{display:flex;align-items:center;gap:10px;border:1px solid var(--ln-line);border-radius:10px;padding:12px 14px;margin:10px 0;}
.ln-lbl{font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;color:var(--ln-muted);font-weight:700;margin:10px 0 6px;}
.ln-col{color:var(--ln-muted);font-size:.78rem;}
.ln-topic-read{margin-left:auto;color:var(--ln-green-mid);display:inline-flex;align-items:center;gap:6px;font-size:.8rem;}
@media (max-width:820px){.ln-tp{grid-template-columns:1fr;}.ln-tp-side{position:static;max-height:none;}}
</style>
<div class="ln-tp">
<aside class="ln-tp-side">
        <div class="ln-tp-side-head">
            <h2><span class="material-symbols-rounded">account_tree</span> Modules</h2>
        </div>
        <?php if ($enrolled && $progress): ?>
            <div class="ln-tp-prog">
                <div class="ln-bar" role="progressbar" aria-valuenow="<?= (int)$progress['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="ln-bar-fill" style="width:<?= (int)$progress['percent'] ?>%"></div>
                </div>
                <?= (int)$progress['done_lessons'] ?>/<?= (int)$progress['total_lessons'] ?> lessons ·
                <?php if ((int)$progress['total_assessments'] > 0): ?><?= (int)$progress['passed_assessments'] ?>/<?= (int)$progress['total_assessments'] ?> assessments · <?php endif; ?>
                <?= (int)$progress['percent'] ?>% complete
            </div>
        <?php endif; ?>

        <div class="ln-tp-tree">
<?php foreach ($uiModules as $m): ?>
                <?php $mDone = count(array_filter($m['lessons'], static fn($l) => in_array((int)$l['id'], $doneLessons, true))); ?>
                <details class="ln-tp-module" <?= $selModule && $selModule['id'] === $m['id'] ? 'open' : '' ?>>
                    <summary>
                        <span class="mat material-symbols-rounded">folder</span>
                        <span class="grow"><?= learn_e($m['title']) ?></span>
                        <?php if ($enrolled): ?>
                            <span class="st<?= $mDone === count($m['lessons']) && count($m['lessons']) > 0 ? ' done' : '' ?> material-symbols-rounded"><?= $mDone === count($m['lessons']) && count($m['lessons']) > 0 ? 'check_circle' : '' ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="ln-tp-module-body">
                        <a class="ln-tp-node ln-mod<?= $selModule && $selModule['id'] === $m['id'] && $selTopic === null && $selAssess === null ? ' active' : '' ?>" href="<?= learn_e(($uiUrl)('module', $m['id'])) ?>">
                            <span class="mat material-symbols-rounded">info</span>
                            <span class="grow">Module overview</span>
                        </a>

                        <div class="ln-lvl2">
                            <?php foreach ($m['lessons'] as $l): ?>
                                <?php
                                $lDone = in_array((int)$l['id'], $doneLessons, true);
                                $lActive = $selAssess === null && $selTopic === null && $selLesson !== null && $selLesson['id'] === (int)$l['id'];
                                ?>
                                <details class="ln-tp-lesson" <?= $lActive ? 'open' : '' ?>>
                                    <summary>
                                        <span class="mat material-symbols-rounded"><?= $lDone ? 'check_circle' : 'radio_button_unchecked' ?></span>
                                        <span class="grow"><?= learn_e($l['title']) ?></span>
                                        <?php if ((int)$l['duration_minutes'] > 0): ?><span class="st ln-col"><?= (int)$l['duration_minutes'] ?>m</span><?php endif; ?>
                                    </summary>
                                    <div class="ln-tp-lesson-body">
                                        <a class="ln-tp-node<?= $lActive ? ' active' : '' ?>" href="<?= learn_e(($uiUrl)('lesson', (int)$l['id'])) ?>">
                                            <span class="mat material-symbols-rounded">menu_book</span>
                                            <span class="grow">Lesson content</span>
                                        </a>
                                        <?php if ($l['topics'] || $l['assessments']): ?>
                                            <div class="ln-lvl3">
<?php foreach ($l['topics'] as $t): ?>
                                                    <a class="ln-tp-node<?= $selTopic && (int)$selTopic['id'] === (int)$t['id'] ? ' active' : '' ?>" href="<?= learn_e(($uiUrl)('topic', (int)$t['id'])) ?>">
                                                        <span class="mat material-symbols-rounded" style="font-size:16px;"><?= in_array((int)$t['id'], $readTopicIds, true) ? 'check_circle' : 'article' ?></span>
                                                        <span class="grow"><?= learn_e($t['title']) ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                                <?php foreach ($l['assessments'] as $a): ?>
                                                    <a class="ln-tp-node<?= $selAssess && (int)$selAssess['id'] === (int)$a['id'] ? ' active' : '' ?>" href="<?= learn_e(($uiUrl)('assessment', (int)$a['id'])) ?>">
                                                        <span class="mat material-symbols-rounded" style="font-size:16px;color:var(--ln-amber);">checklist</span>
                                                        <span class="grow"><?= learn_e($a['title']) ?></span>
                                                        <?php if ($enrolled): ?><span class="st<?= ($passedAssessIds[(int)$a['id']] ?? false) ? ' done' : '' ?> material-symbols-rounded"><?= ($passedAssessIds[(int)$a['id']] ?? false) ? 'check_circle' : 'help' ?></span><?php endif; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
<?php foreach ($m['assessments'] as $a): ?>
                                <a class="ln-tp-node<?= $selAssess && (int)$selAssess['id'] === (int)$a['id'] ? ' active' : '' ?>" href="<?= learn_e(($uiUrl)('assessment', (int)$a['id'])) ?>">
                                    <span class="mat material-symbols-rounded" style="font-size:16px;color:var(--ln-amber);">fact_check</span>
                                    <span class="grow"><?= learn_e($a['title']) ?></span>
                                    <?php if ($enrolled): ?><span class="st<?= ($passedAssessIds[(int)$a['id']] ?? false) ? ' done' : '' ?> material-symbols-rounded"><?= ($passedAssessIds[(int)$a['id']] ?? false) ? 'check_circle' : 'help' ?></span><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>

            <?php if ($uiFinals): ?>
                <div class="ln-lbl"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">workspace_premium</span> Final assessment</div>
                <?php foreach ($uiFinals as $a): ?>
                    <a class="ln-tp-node<?= $selAssess && (int)$selAssess['id'] === (int)$a['id'] ? ' active' : '' ?>" href="<?= learn_e(($uiUrl)('assessment', (int)$a['id'])) ?>">
                        <span class="mat material-symbols-rounded" style="font-size:16px;color:var(--ln-amber);">workspace_premium</span>
                        <span class="grow"><?= learn_e($a['title']) ?></span>
                        <?php if ($enrolled): ?><span class="st<?= ($passedAssessIds[(int)$a['id']] ?? false) ? ' done' : '' ?> material-symbols-rounded"><?= ($passedAssessIds[(int)$a['id']] ?? false) ? 'check_circle' : 'help' ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
    <main class="ln-tp-main">
<?php
        // Load full lesson content if a lesson is selected, so the main panel
        // can render video / attachment / rich-text body.
        $selLessonFull = null;
        $selLessonTopics = [];
        $selLessonQuiz = [];
        $selLessonCat = null;
        $selLessonRead = [];
        $selLessonQuizPassed = false;
        $selLessonCatPassed = false;
        if ($selLesson !== null) {
            $ls = $conn->prepare("SELECT l.* FROM public_course_lessons l JOIN public_course_modules m ON m.id = l.module_id WHERE l.id = ? AND m.course_id = ? LIMIT 1");
            $ls->bind_param('ii', (int)$selLesson['id'], $courseId);
            $ls->execute();
            $selLessonFull = $ls->get_result()->fetch_assoc() ?: null;
            $ls->close();
            if ($selLessonFull) { $selLessonTopics = $selLesson['topics']; }
            if ($enrolled) {
                $flatTopics = array_merge($selLessonTopics, ...array_map(static fn($t) => $t['subs'] ?? [], $selLessonTopics));
                $selLessonRead = array_values(array_intersect($readTopicIds, array_column($flatTopics, 'id')));
                $selLessonQuiz = array_values(array_filter($assessments, static fn($a) => (int)$a['lesson_id'] === (int)$selLesson['id']));
                foreach ($selLessonQuiz as $a) { $selLessonQuizPassed = $selLessonQuizPassed || ($passedAssessIds[(int)$a['id']] ?? false); }
                if ($selModule) {
                    foreach (array_values(array_filter($assessments, static fn($a) => (int)$a['module_id'] === (int)$selModule['id'] && (int)$a['lesson_id'] === 0)) as $a) {
                        $selLessonCat = $a; $selLessonCatPassed = $passedAssessIds[(int)$a['id']] ?? false;
                    }
                }
            }
        }
        ?>
<?php if ($selModule !== null && $selLesson === null && $selTopic === null && $selAssess === null): ?>
            <?php // Module detail ?>
            <div class="ln-tp-head">
                <span class="material-symbols-rounded" style="font-size:30px;color:var(--ln-green-mid);">folder</span>
                <h1><?= learn_e($selModule['title']) ?></h1>
                <span class="k ln-chip">Module</span>
            </div>
            <div class="ln-tp-item">
                <?php if (!empty($selModule['summary'])): ?><p class="sub"><?= learn_e($selModule['summary']) ?></p><?php endif; ?>
                <p class="ln-col" style="margin:10px 0 0;"><?= count($selModule['lessons']) ?> lesson<?= count($selModule['lessons']) !== 1 ? 's' : '' ?> · <?= count($selModule['assessments']) ?> assessment<?= count($selModule['assessments']) !== 1 ? 's' : '' ?></p>
                <?php if ($selModule['lessons']): ?>
                    <div class="ln-lbl" style="margin-top:16px;">Lessons in this module</div>
                    <?php foreach ($selModule['lessons'] as $ll): ?>
                        <div class="ln-assess">
                            <span class="material-symbols-rounded" style="color:var(--ln-green-light);"><?= in_array((int)$ll['id'], $doneLessons, true) ? 'check_circle' : 'play_circle' ?></span>
                            <a href="<?= learn_e(($uiUrl)('lesson', (int)$ll['id'])) ?>" style="color:var(--ln-ink);text-decoration:none;"><?= learn_e($ll['title']) ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($selTopic !== null): ?>
            <?php
            $topicInfo = trim((string)($selTopic['content_html'] ?? ''));
            $topicSubs = $selTopic['subs'] ?? [];
            ?>
            <div class="ln-tp-head">
                <span class="material-symbols-rounded" style="font-size:30px;color:var(--ln-green-light);">article</span>
                <h1><?= learn_e($selTopic['title']) ?></h1>
                <span class="k ln-chip">Topic</span>
            </div>
            <div class="ln-tp-item">
                <?php if (!empty($selLesson)): ?><p class="ln-col">In lesson « <?= learn_e($selLesson['title']) ?> »</p><?php endif; ?>

                <?php if ($topicInfo !== ''): ?>
                    <div class="ln-topic-block">
                        <div class="ln-lbl">Topic info</div>
                        <p style="margin:0; white-space:pre-wrap; color:var(--ln-ink);"><?= learn_e($topicInfo) ?></p>
                    </div>
                <?php else: ?>
                    <div class="ln-topic-block" style="border-style:dashed; color:var(--ln-muted);">
                        <div class="ln-lbl">Topic info</div>
                        <p style="margin:0; color:var(--ln-muted);">No info yet for this topic.</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($topicSubs)): ?>
                    <div class="ln-lbl" style="margin-top:14px;">Subtopics</div>
                    <div class="ln-subs">
                        <?php foreach ($topicSubs as $s): ?>
                            <?php $subInfo = trim((string)($s['content_html'] ?? '')); ?>
                            <div class="ln-subrow" style="display:block; margin-bottom:10px; padding:10px 12px; border:1px solid var(--ln-line); border-radius:8px; background:var(--ln-bg, rgba(0,0,0,0.01));">
                                <div style="display:flex;align-items:flex-start;gap:8px; margin-bottom:6px;">
                                    <span class="material-symbols-rounded" style="font-size:16px;color:var(--ln-muted);">subdirectory_arrow_right</span>
                                    <strong style="color:var(--ln-ink); font-size:0.9rem;"><?= learn_e($s['title']) ?></strong>
                                </div>
                                <?php if ($subInfo !== ''): ?>
                                    <p style="margin:0; white-space:pre-wrap; color:var(--ln-ink); font-size:0.85rem;"><?= learn_e($subInfo) ?></p>
                                <?php else: ?>
                                    <p style="margin:0; color:var(--ln-muted); font-size:0.82rem;">No info yet for this subtopic.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($enrolled): ?>
                    <p style="margin:16px 0 0;border-top:1px dashed var(--ln-line);padding-top:12px;font-size:.82rem;color:var(--ln-muted);">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;color:var(--ln-green-mid);">visibility</span>
                        <?= in_array((int)$selTopic['id'], $readTopicIds, true) ? 'You have marked this topic as read.' : 'Not yet marked as read.' ?>
                        <?php if ($selLesson): ?> <a href="<?= learn_e(($uiUrl)('lesson', (int)$selLesson['id'])) ?>" style="color:var(--ln-green-mid);">Open the lesson to mark it read</a>.<?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if ($selLesson): ?>
                    <a class="ln-btn ln-btn-primary" style="margin-top:16px;" href="<?= learn_e(($uiUrl)('lesson', (int)$selLesson['id'])) ?>"><span class="material-symbols-rounded">menu_book</span> Read in lesson</a>
                <?php endif; ?>
            </div>
        <?php elseif ($selAssess !== null): ?>
<?php // Assessment detail ?>
            <div class="ln-tp-head">
                <span class="material-symbols-rounded" style="font-size:30px;color:var(--ln-amber);"><?= (int)$selAssess['module_id'] === 0 ? 'workspace_premium' : 'fact_check' ?></span>
                <h1><?= learn_e($selAssess['title']) ?></h1>
                <span class="k ln-chip"><?= learn_e(ucfirst((string)($selAssess['type'] ?? 'assessment'))) ?></span>
            </div>
            <div class="ln-tp-item">
                <?php if ((int)$selAssess['module_id'] === 0): ?><p class="sub">Final assessment — finish the modules above, then complete this to earn the certificate.</p><?php endif; ?>
                <?php if ($enrolled && $viewAssessPassed): ?>
                    <p style="margin:0 0 14px;color:var(--ln-success);display:flex;align-items:center;gap:8px;"><span class="material-symbols-rounded">check_circle</span> You have passed this assessment.</p>
                <?php elseif ($enrolled): ?>
                    <p class="ln-col" style="margin:0 0 14px;">You have not passed this assessment yet.</p>
                <?php else: ?>
                    <p class="sub"><?= $learner === null ? 'Sign in to attempt this assessment.' : 'Enrol on this course to attempt this assessment.' ?></p>
                <?php endif; ?>
                <?php if ($enrolled): ?>
                    <a class="ln-btn ln-btn-primary" href="/learn/assessment.php?a=<?= (int)$selAssess['id'] ?>"><span class="material-symbols-rounded"><?= (int)$selAssess['module_id'] === 0 ? 'workspace_premium' : 'quiz' ?></span> <?= $viewAssessPassed ? 'Retake' : 'Take' ?> <?= learn_e(ucfirst((string)($selAssess['type'] ?? 'assessment'))) ?></a>
                <?php else: ?>
                    <a class="ln-btn ln-btn-primary" href="/learn/register.php">Create account</a>
                <?php endif; ?>
            </div>
        <?php elseif ($selLesson !== null): ?>
            <?php // Lesson detail (reader) ?>
            <div class="ln-tp-head">
                <span class="material-symbols-rounded" style="font-size:30px;color:var(--ln-green-light);">menu_book</span>
                <h1><?= $selLessonFull ? learn_e($selLessonFull['title']) : learn_e($selLesson['title']) ?></h1>
                <?php if (!empty($selLesson)): ?><span class="k ln-chip">Lesson</span><?php endif; ?>
            </div>
            <?php require __DIR__ . '/course_reader.php'; ?>
        </main>
    </div>
<?php endif; ?>