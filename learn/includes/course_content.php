<?php
/**
 * Course content tree (module → lesson → topic → subtopic → assessment).
 * Rendered by learn/course.php to mirror the "teach" course-builder hierarchy,
 * using borders / indentation only (no background colors) and the --ln-* tokens.
 *
 * Requires in scope: $conn, $courseId, $modules, $finals, $enrolled,
 * $doneLessons, $slug.
 */

// Group assessments by lesson (quizzes) and by module (CATs / lesson_id 0) so
// each level shows exactly its own items and nothing is double-counted.
$assessmentsByLesson = [];
$assessmentsByModule = [];
$assessRows = [];
$as = $conn->prepare("
    SELECT id, module_id, lesson_id, title, type
    FROM public_course_assessments
    WHERE course_id = ?
    ORDER BY position, id
");
if ($as) {
    $as->bind_param('i', $courseId);
    $as->execute();
    $assessRows = $as->get_result()->fetch_all(MYSQLI_ASSOC);
    $as->close();
}
foreach ($assessRows as $a) {
    $lesId = (int)$a['lesson_id'];
    $modId = (int)$a['module_id'];
    if ($lesId > 0) {
        $assessmentsByLesson[$lesId][] = $a;
    } elseif ($modId > 0) {
        $assessmentsByModule[$modId][] = $a;
    }
}
?>
<style>
.ln-cur{display:flex;flex-direction:column;gap:14px;}
.ln-cur-m{border:1px solid var(--ln-line);border-radius:var(--ln-r);overflow:hidden;}
.ln-cur-mh{display:flex;align-items:flex-start;gap:12px;padding:15px 18px;border-bottom:1px solid var(--ln-line);}
.ln-cur-mh .mi{color:var(--ln-green-mid);font-size:22px;margin-top:1px;}
.ln-cur-mt{font-size:1.02rem;font-weight:700;color:var(--ln-ink);margin:0;}
.ln-cur-ms{color:var(--ln-muted);font-size:.84rem;margin:4px 0 0;}
.ln-cur-mn{margin-left:auto;flex:none;font-size:.78rem;color:var(--ln-muted);display:inline-flex;align-items:center;gap:6px;}
.ln-cur-b{padding:13px 18px 16px;}
.ln-cur-l{border:1px solid var(--ln-line);border-radius:var(--ln-r-sm);padding:11px 14px;margin:0 0 9px;}
.ln-cur-l:last-child{margin:0;}
.ln-cur-lr{display:flex;align-items:flex-start;gap:10px;}
.ln-cur-lr .li{font-size:20px;color:var(--ln-muted);flex:none;margin-top:1px;}
.ln-cur-lr .li.done{color:var(--ln-success);}
.ln-cur-lt{font-weight:650;color:var(--ln-ink);font-size:.97rem;line-height:1.3;}
.ln-cur-lm{font-size:.78rem;color:var(--ln-muted);margin-top:2px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.ln-cur .ln-cur-lm span{display:inline-flex;align-items:center;gap:4px;}
.ln-cur-tc{border-left:2px solid var(--ln-line);margin:12px 0 0;padding-left:14px;}
.ln-cur-t{padding:6px 0;border-bottom:1px dotted var(--ln-line);}
.ln-cur-t:last-child{border-bottom:none;}
.ln-cur-tr{display:flex;align-items:flex-start;gap:8px;}
.ln-cur-tr .ti{font-size:18px;color:var(--ln-green-light);flex:none;margin-top:1px;}
.ln-cur-tt{color:var(--ln-ink);font-weight:600;font-size:.9rem;}
.ln-cur-tx{color:var(--ln-muted);font-size:.82rem;margin:2px 0 0;}
.ln-cur-subs{margin:6px 0 0;padding-left:14px;border-left:1px dashed var(--ln-line);}
.ln-cur-s{display:flex;align-items:flex-start;gap:8px;padding:4px 0;font-size:.85rem;color:var(--ln-body);}
.ln-cur-s .si{font-size:15px;color:var(--ln-muted);flex:none;margin-top:1px;}
.ln-cur-a{display:flex;align-items:center;gap:8px;padding:7px 10px;margin-top:8px;border:1px solid var(--ln-line);border-radius:var(--ln-r-sm);font-size:.86rem;}
.ln-cur-a .ai{color:var(--ln-amber);font-size:18px;flex:none;}
.ln-cur-a .aty{margin-left:auto;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--ln-muted);}
.ln-cur a{color:var(--ln-ink);text-decoration:none;}
.ln-cur a:hover{color:var(--ln-green-mid);}
</style>
<?php if (!$modules && !$finals && empty($assessRows)): ?>
    <div class="ln-empty">
        <span class="material-symbols-rounded">construction</span>
        <h2>No content yet</h2>
        <p>This course has been published but has no lessons in it.</p>
    </div>
<?php else: ?>
    <h2 style="font-size:1.15rem; color:var(--ln-ink); margin:0 0 14px;">Course content</h2>
    <div class="ln-cur">
        <?php foreach ($modules as $module): ?>
            <?php
            $moduleLessons = $module['lessons'] ?? [];
            $moduleAssess = $assessmentsByModule[(int)$module['id']] ?? [];
            $doneInModule = count(array_filter($moduleLessons, static fn($l) => in_array((int)$l['id'], $doneLessons, true)));
            ?>
            <section class="ln-cur-m">
                <div class="ln-cur-mh">
                    <span class="material-symbols-rounded mi">folder</span>
                    <div style="flex:1;min-width:0;">
                        <h3 class="ln-cur-mt"><?= learn_e($module['title']) ?></h3>
                        <?php if (!empty($module['summary'])): ?>
                            <p class="ln-cur-ms"><?= learn_e($module['summary']) ?></p>
                        <?php endif; ?>
                        <?php if (count($moduleLessons) > 0 && $enrolled): ?>
                            <p class="ln-cur-ms" style="margin-top:6px;font-size:.76rem;color:var(--ln-success);">
                                <?= $doneInModule ?>/<?= count($moduleLessons) ?> lesson<?= count($moduleLessons) !== 1 ? 's' : '' ?> completed
                            </p>
                        <?php endif; ?>
                    </div>
                    <span class="ln-cur-mn">
                        <span class="material-symbols-rounded" style="font-size:16px;">play_circle</span>
                        <?= count($moduleLessons) ?> lesson<?= count($moduleLessons) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="ln-cur-b">
<?php foreach ($moduleLessons as $lesson): ?>
                        <?php
                        $lid = (int)$lesson['id'];
                        $lessonTopics = learn_lesson_topics($conn, $lid);
                        $lessonAssess = $assessmentsByLesson[$lid] ?? [];
                        $ldone = $enrolled && in_array($lid, $doneLessons, true);
                        $lessonUrl = '/learn/course.php?c=' . rawurlencode($slug) . '&lesson=' . $lid;
                        ?>
                        <div class="ln-cur-l">
                            <div class="ln-cur-lr">
                                <span class="material-symbols-rounded li<?= $ldone ? ' done' : '' ?>">
                                    <?= $ldone ? 'check_circle' : 'radio_button_unchecked' ?>
                                </span>
                                <div style="flex:1;min-width:0;">
                                    <div class="ln-cur-lt">
                                        <?php if ($enrolled): ?>
                                            <a href="<?= learn_e($lessonUrl) ?>"><?= learn_e($lesson['title']) ?></a>
                                        <?php else: ?>
                                            <?= learn_e($lesson['title']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ((int)($lesson['duration_minutes'] ?? 0) > 0 || $lessonTopics || $lessonAssess): ?>
                                        <div class="ln-cur-lm">
                                            <?php if ((int)($lesson['duration_minutes'] ?? 0) > 0): ?>
                                                <span>
                                                    <span class="material-symbols-rounded" style="font-size:14px;">schedule</span>
                                                    <?= (int)$lesson['duration_minutes'] ?> min
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($lessonTopics): ?>
                                                <span>
                                                    <span class="material-symbols-rounded" style="font-size:14px;">topic</span>
                                                    <?= count($lessonTopics) ?> topic<?= count($lessonTopics) !== 1 ? 's' : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
<?php if ($lessonTopics || $lessonAssess): ?>
                                <div class="ln-cur-tc">
                                    <?php foreach ($lessonTopics as $topic): ?>
                                        <div class="ln-cur-t">
                                            <div class="ln-cur-tr">
                                                <span class="material-symbols-rounded ti">article</span>
                                                <div style="flex:1;min-width:0;">
                                                    <div class="ln-cur-tt"><?= learn_e($topic['title']) ?></div>
                                                    <?php if (!empty($topic['content_html'])): ?>
                                                        <p class="ln-cur-tx"><?= learn_e($topic['content_html']) ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($topic['subs'])): ?>
                                                        <div class="ln-cur-subs">
                                                            <?php foreach ($topic['subs'] as $sub): ?>
                                                                <div class="ln-cur-s">
                                                                    <span class="material-symbols-rounded si">subdirectory_arrow_right</span>
                                                                    <span><?= learn_e($sub['title']) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php foreach ($lessonAssess as $assessment): ?>
                                        <div class="ln-cur-a">
                                            <span class="material-symbols-rounded ai">quiz</span>
                                            <?php if ($enrolled): ?>
                                                <a href="/learn/assessment.php?a=<?= (int)$assessment['id'] ?>">
                                                    <?= learn_e($assessment['title']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span><?= learn_e($assessment['title']) ?></span>
                                            <?php endif; ?>
                                            <span class="aty"><?= learn_e(ucfirst((string)($assessment['type'] ?? 'assessment'))) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
<?php if (empty($moduleLessons) && empty($moduleAssess)): ?>
                        <p style="margin:0;font-size:.85rem;color:var(--ln-muted);">No lessons in this module yet.</p>
                    <?php endif; ?>

                    <?php foreach ($moduleAssess as $assessment): ?>
                        <div class="ln-cur-a">
                            <span class="material-symbols-rounded ai">fact_check</span>
                            <?php if ($enrolled): ?>
                                <a href="/learn/assessment.php?a=<?= (int)$assessment['id'] ?>">
                                    <?= learn_e($assessment['title']) ?>
                                </a>
                            <?php else: ?>
                                <span><?= learn_e($assessment['title']) ?></span>
                            <?php endif; ?>
                            <span class="aty"><?= learn_e(ucfirst((string)($assessment['type'] ?? 'cat'))) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($finals): ?>
            <section class="ln-cur-m">
                <div class="ln-cur-mh">
                    <span class="material-symbols-rounded mi">workspace_premium</span>
                    <div style="flex:1;min-width:0;">
                        <h3 class="ln-cur-mt">Final assessment</h3>
                        <p class="ln-cur-ms">Complete the modules above, then finish the course with the final assessment.</p>
                    </div>
                </div>
                <div class="ln-cur-b">
                    <?php foreach ($finals as $assessment): ?>
                        <div class="ln-cur-a">
                            <span class="material-symbols-rounded ai">workspace_premium</span>
                            <?php if ($enrolled): ?>
                                <a href="/learn/assessment.php?a=<?= (int)$assessment['id'] ?>">
                                    <?= learn_e($assessment['title']) ?>
                                </a>
                            <?php else: ?>
                                <span><?= learn_e($assessment['title']) ?></span>
                            <?php endif; ?>
                            <span class="aty">Final</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>