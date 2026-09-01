<?php
/** Lesson reader partial. Requires $openLesson, $openLessonTopics, $readTopicIds,
 *  $openLessonQuiz, $openLessonCat, $doneLessons, $learner, $conn. */
if ($openLesson !== null):
?>
<article class="ln-card" style="margin-bottom:26px;">
        <h1 style="font-size:1.3rem;"><?= learn_e($openLesson['title']) ?></h1>
        <?php if (!empty($openLesson['video_url'])): ?>
            <p class="ln-sub">
                <a href="<?= learn_e($openLesson['video_url']) ?>" target="_blank" rel="noopener">
                    Watch the video for this lesson
                </a>
            </p>
        <?php endif; ?>

        <?php if (!empty($openLesson['attachment_path'])): ?>
            <p class="ln-sub">
                <a href="<?= learn_e($openLesson['attachment_path']) ?>" target="_blank" rel="noopener>
                    Download the notes for this lesson (PDF)
                </a>
            </p>
        <?php endif; ?>

        <div class="ln-lesson-body"><?= render_lesson_content($openLesson['content_html'] ?? '') ?></div>
<?php if ($openLessonTopics): ?>
            <div style="margin-top:20px; border-top:1px solid var(--ln-line); padding-top:14px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                    <h2 style="font-size:1.05rem; color:var(--ln-ink); margin:0;">Reading topics</h2>
                    <?php
                    $readFlatTopics = [];
                    foreach ($openLessonTopics as $rt) {
                        $readFlatTopics[] = (int)$rt['id'];
                        foreach (($rt['subs'] ?? []) as $rs) { $readFlatTopics[] = (int)$rs['id']; }
                    }
                    $readCount = count(array_intersect($readTopicIds, $readFlatTopics));
                    ?>
                    <span style="font-size:0.8rem; color:var(--ln-muted);"><?= $readCount ?> read</span>
                </div>
                <?php foreach ($openLessonTopics as $topic):
                    $tRead = in_array((int)$topic['id'], $readTopicIds, true);
                    ?>
                    <div style="border:1px solid var(--ln-line); border-radius:10px; padding:14px 16px; margin-bottom:12px;">
                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                            <?php if ($learner !== null): ?>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="mark_topic_read">
                                    <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                                    <button type="submit" style="background:none;border:none;padding:0;cursor:pointer;" title="<?= $tRead ? 'Marked as read' : 'Mark this topic as read' ?>">
                                        <span class="material-symbols-rounded" style="font-size:26px; color:<?= $tRead ? 'var(--ln-green-mid)' : 'var(--ln-muted)' ?>;"><?= $tRead ? 'check_circle' : 'radio_button_unchecked' ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:650; color:var(--ln-ink);"><?= learn_e($topic['title']) ?></div>
                            </div>
                        </div>
                        <?php if ($topic['subs']): ?>
                            <?php foreach ($topic['subs'] as $sub):
                                $sRead = in_array((int)$sub['id'], $readTopicIds, true);
                                ?>
                                <div style="margin:10px 0 0 26px; padding-top:10px; border-top:1px dashed var(--ln-line);">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
                                            <input type="hidden" name="action" value="mark_topic_read">
                                            <input type="hidden" name="topic_id" value="<?= (int)$sub['id'] ?>">
                                            <button type="submit" class="material-symbols-rounded" style="background:none;border:none;padding:0;cursor:pointer;font-size:18px;color:<?= $sRead ? 'var(--ln-green-mid)' : 'var(--ln-muted)' ?>;"><?= $sRead ? 'check_circle' : 'radio_button_unchecked' ?></button>
                                        </form>
                                        <div style="flex:1; min-width:0;">
                                            <div style="font-size:0.9rem; color:var(--ln-ink);"><?= learn_e($sub['title']) ?></div>
                                            <?php if (!empty($sub['content_html'])): ?>
                                                <p style="margin:4px 0 0; font-size:0.82rem; color:var(--ln-muted); white-space:pre-wrap;"><?= learn_e($sub['content_html']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php // small completion trace at the bottom of each topic ?>
                        <div style="margin-top:10px; padding-top:8px; border-top:1px dashed var(--ln-line); font-size:0.78rem; color:var(--ln-muted);">
                            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:middle; color:var(--ln-green-mid);">visibility</span>
                            <?= (int)learn_topic_reader_count($conn, (int)$topic['id']) ?> learner<?= learn_topic_reader_count($conn, (int)$topic['id']) === 1 ? '' : 's' ?> have completed reading this topic
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
<?php if ($openLessonQuiz): ?>
            <div class="ln-card" style="margin-top:20px; padding:16px 18px; display:flex; align-items:center; gap:12px;">
                <span class="material-symbols-rounded" style="font-size:24px; color:var(--ln-green-mid);">quiz</span>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:650; color:var(--ln-ink);"><?= learn_e($openLessonQuiz['title']) ?></div>
                    <div style="font-size:0.8rem; color:var(--ln-muted);">Quiz for this topic</div>
                </div>
                <?php if ($openLessonQuiz['passed']): ?>
                    <span class="ln-chip ln-chip-done"><span class="material-symbols-rounded">check_circle</span> Passed</span>
                <?php else: ?>
                    <a class="ln-btn ln-btn-primary" href="/learn/assessment.php?a=<?= (int)$openLessonQuiz['id'] ?>">
                        <span class="material-symbols-rounded">quiz</span> Take Quiz
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (in_array((int)$openLesson['id'], $doneLessons, true)): ?>
            <p style="margin-top:22px;">
                <span class="ln-chip ln-chip-done">
                    <span class="material-symbols-rounded">check_circle</span> Completed
                </span>
            </p>
        <?php endif; ?>

        <?php if ($openLessonCat): ?>
            <div class="ln-card" style="margin-top:20px; padding:16px 18px; display:flex; align-items:center; gap:12px;">
                <span class="material-symbols-rounded" style="font-size:24px; color:var(--ln-amber);">flag</span>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:650; color:var(--ln-ink);"><?= learn_e($openLessonCat['title']) ?></div>
                    <div style="font-size:0.8rem; color:var(--ln-muted);">CAT at the end of this lesson</div>
                </div>
                <?php if ($openLessonCat['passed']): ?>
                    <span class="ln-chip ln-chip-done"><span class="material-symbols-rounded">check_circle</span> Passed</span>
                <?php else: ?>
                    <a class="ln-btn ln-btn-primary" href="/learn/assessment.php?a=<?= (int)$openLessonCat['id'] ?>">
                        <span class="material-symbols-rounded">fact_check</span> Take CAT
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
<?php endif; ?>
