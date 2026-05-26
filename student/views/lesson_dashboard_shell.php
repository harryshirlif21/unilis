<?php
/** @var array $lesson, $blocks, $module_lessons, $lesson_id, $unit_id, $unit_name */
/** @var bool $is_completed, $prev_lesson, $next_lesson */
/** @var array $lesson_assessments, $completed_lessons, $lesson_chart */
/** @var int $module_progress_pct, $completed_count, $total_module_lessons */
$has_assessments = !empty($lesson_assessments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>L<?= (int)$lesson['lesson_number'] ?>: <?= htmlspecialchars($lesson['title']) ?> — UniLIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/lesson-dashboard.css">
</head>
<body>
<div class="bg-mesh" aria-hidden="true"></div>

<div class="autocomplete-toast" id="ac-toast">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    Lesson marked complete!
</div>

<div class="app-shell" id="app-shell">
    <!-- Top Navbar -->
    <header class="top-navbar">
        <button type="button" id="lessonNavToggle" class="btn btn-ghost btn-icon" aria-expanded="false" aria-label="Open lessons menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div class="nav-brand">
            <span class="nav-eyebrow"><?= htmlspecialchars($unit_name) ?> · <?= htmlspecialchars($lesson['module_title']) ?></span>
            <h1 class="nav-title">L<?= (int)$lesson['lesson_number'] ?>: <?= htmlspecialchars($lesson['title']) ?></h1>
        </div>
        <div class="progress-wrap">
            <div class="progress-label">
                <span>Module progress</span>
                <span><strong id="module-pct-label"><?= $module_progress_pct ?>%</strong> · Reading <span id="read-pct">0%</span></span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="module-progress-fill" style="width:<?= $module_progress_pct ?>%"></div>
            </div>
        </div>
        <div class="nav-actions">
            <a href="dashboard.php" class="btn btn-ghost" title="Dashboard">Home</a>
            <a href="course_view.php?unit_id=<?= $unit_id ?>" class="btn btn-ghost" title="Training">Training</a>
            <button type="button" id="voice-narrate" class="btn btn-ghost btn-icon" title="Voice narration">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
            </button>
            <button type="button" id="theme-toggle" class="btn btn-ghost btn-icon" title="Toggle theme"></button>
            <button type="button" id="ai-toggle" class="btn btn-ghost btn-icon" title="AI assistant">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="4"/></svg>
            </button>
            <?php if (!$is_completed): ?>
            <button type="button" class="btn btn-success" id="complete-btn" onclick="handleLessonEnd()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Mark complete
            </button>
            <?php else: ?>
            <span class="complete-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Completed
            </span>
            <?php endif; ?>
            <?php if ($next_lesson): ?>
            <a class="btn btn-primary" href="lesson_view.php?lesson_id=<?= (int)$next_lesson['id'] ?>&unit_id=<?= $unit_id ?>">
                Next lesson
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
            <?php endif; ?>
        </div>
        <div class="read-progress-bar" id="read-progress"></div>
    </header>

    <!-- Sidebar -->
    <aside class="lesson-sidebar" id="lesson-sidebar">
        <a href="course_view.php?unit_id=<?= $unit_id ?>&browse=1" class="btn btn-ghost" style="width:100%;margin-bottom:8px;font-size:0.78rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            Back to course
        </a>
        <span class="sidebar-label"><?= htmlspecialchars($lesson['module_title']) ?></span>
        <?php foreach ($module_lessons as $ml):
            $isCurrent = ((int)$ml['id'] === $lesson_id);
            $isDone = isset($completed_lessons[$ml['id']]);
        ?>
        <a class="lesson-link <?= $isCurrent ? 'active' : '' ?>"
           href="lesson_view.php?lesson_id=<?= (int)$ml['id'] ?>&unit_id=<?= $unit_id ?>">
            <span class="lesson-link-icon">L<?= (int)$ml['lesson_number'] ?></span>
            <span class="lesson-link-title"><?= htmlspecialchars($ml['title']) ?></span>
            <span id="lesson-check-<?= (int)$ml['id'] ?>" class="lesson-link-check">
                <?php if ($isDone): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php endif; ?>
            </span>
        </a>
        <?php endforeach; ?>

        <div class="chart-mini">
            <h4>Learning progress</h4>
            <canvas id="progress-chart" height="80" style="width:100%;height:80px"></canvas>
            <p style="font-size:0.72rem;color:var(--text-muted);margin-top:8px;">
                <?= $completed_count ?> / <?= $total_module_lessons ?> lessons complete
            </p>
        </div>

        <span class="sidebar-label" style="margin-top:12px">Quick links</span>
        <a href="dashboard.php" class="lesson-link">
            <span class="lesson-link-icon">H</span>
            <span class="lesson-link-title">Dashboard</span>
        </a>
        <a href="take_assignment.php" class="lesson-link">
            <span class="lesson-link-icon">A</span>
            <span class="lesson-link-title">Assignments</span>
        </a>
        <a href="take_assessment.php" class="lesson-link">
            <span class="lesson-link-icon">E</span>
            <span class="lesson-link-title">Exams &amp; CATs</span>
        </a>
        <a href="my_progress.php?unit_id=<?= $unit_id ?>" class="lesson-link">
            <span class="lesson-link-icon">P</span>
            <span class="lesson-link-title">My Progress</span>
        </a>
        <a href="my_units.php" class="lesson-link">
            <span class="lesson-link-icon">U</span>
            <span class="lesson-link-title">My Units</span>
        </a>
    </aside>

    <!-- Main -->
    <div class="main-stage">
        <div class="content-scroll" id="content-scroll">
            <div class="bento-dashboard">

                <!-- Utility row -->
                <div class="utility-row col-span-full">
                    <article class="glass-card bento-card bento-fade ai-summary-box">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/></svg>
                            Quick Summary
                        </h3>
                        <p class="ai-summary-text" id="ai-summary-text">Loading summary from lesson content…</p>
                    </article>
                    <article class="glass-card bento-card bento-fade quiz-cta-card">
                        <h3 style="font-size:1rem;font-weight:700;">Ready to test yourself?</h3>
                        <p><?= $has_assessments ? count($lesson_assessments) . ' assessment(s) linked to this lesson.' : 'No quiz linked yet — mark complete when you finish reading.' ?></p>
                        <button type="button" class="btn btn-primary" onclick="openQuizModal()" style="width:fit-content">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
                            Take Quiz
                        </button>
                    </article>
                </div>

                <?php if (empty($blocks)): ?>
                <div class="no-content glass-card bento-card">
                    <p>No content has been added to this lesson yet.</p>
                </div>
                <?php else: ?>
                <?php foreach ($blocks as $block):
                    $data = json_decode($block['content'], true);
                ?>
                <?php if ($block['block_type'] === 'text'): ?>
                    <?= render_lesson_text_bento($block['content']) ?>

                <?php elseif ($block['block_type'] === 'image'): ?>
                <article class="bento-card bento-fade media-card col-span-full">
                    <div class="block-image" style="padding:16px">
                        <img src="<?= htmlspecialchars('../' . ($data['src'] ?? '')) ?>" alt="<?= htmlspecialchars($data['caption'] ?? 'Image') ?>" loading="lazy">
                        <?php if (!empty($data['caption'])): ?>
                        <p class="block-caption" style="margin-top:10px;font-size:0.85rem;color:var(--text-muted);font-style:italic"><?= htmlspecialchars($data['caption']) ?></p>
                        <?php endif; ?>
                    </div>
                </article>

                <?php elseif ($block['block_type'] === 'video'): ?>
                <?php
                    $vidType = $data['type'] ?? (isset($data['src']) ? 'upload' : 'url');
                    $embed   = $data['embed'] ?? '';
                    $vidSrc  = $data['src'] ?? '';
                    $vidName = $data['name'] ?? 'Video';
                ?>
                <article class="bento-card bento-fade media-card col-span-full block-video">
                    <?php if ($vidType === 'upload' && $vidSrc): ?>
                    <div class="video-upload-wrap"><video controls preload="metadata" src="<?= htmlspecialchars('../' . $vidSrc) ?>"></video></div>
                    <div class="video-meta" style="padding:12px 16px;display:flex;gap:10px;align-items:center"><span><?= htmlspecialchars($vidName) ?></span></div>
                    <?php elseif ($embed): ?>
                    <div class="video-embed-wrap"><iframe src="<?= htmlspecialchars($embed) ?>" allowfullscreen loading="lazy"></iframe></div>
                    <?php endif; ?>
                </article>

                <?php elseif ($block['block_type'] === 'audio'): ?>
                <article class="bento-card bento-fade col-span-full block-audio">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                    <div style="flex:1">
                        <div style="font-weight:600;margin-bottom:8px"><?= htmlspecialchars($data['name'] ?? 'Audio') ?></div>
                        <audio controls src="<?= htmlspecialchars('../' . ($data['src'] ?? '')) ?>"></audio>
                    </div>
                </article>

                <?php elseif ($block['block_type'] === 'diagram'): ?>
                <article class="bento-card bento-fade media-card col-span-full">
                    <div class="block-diagram" style="padding:16px">
                        <img src="<?= htmlspecialchars('../' . ($data['src'] ?? '')) ?>" alt="<?= htmlspecialchars($data['caption'] ?? 'Diagram') ?>" loading="lazy" style="width:100%;border-radius:12px">
                    </div>
                </article>

                <?php elseif ($block['block_type'] === 'pdf'): ?>
                <?php
                    $pdfSrc  = '../' . ($data['src'] ?? '');
                    $pdfName = $data['name'] ?? 'Document';
                    $pdfId   = 'pdf-' . $block['id'];
                ?>
                <article class="bento-card bento-fade media-card col-span-full block-pdf">
                    <div class="pdf-topbar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span style="flex:1;font-weight:600"><?= htmlspecialchars($pdfName) ?></span>
                        <button type="button" class="pdf-btn" onclick="togglePdfPane('<?= $pdfId ?>')" id="toggle-<?= $pdfId ?>">Show</button>
                        <a class="pdf-btn" href="<?= htmlspecialchars($pdfSrc) ?>" target="_blank">Open</a>
                    </div>
                    <div class="pdf-frame-wrap" id="<?= $pdfId ?>" style="height:0;overflow:hidden">
                        <iframe id="frame-<?= $pdfId ?>" data-src="<?= htmlspecialchars($pdfSrc) ?>#toolbar=1" title="<?= htmlspecialchars($pdfName) ?>" loading="lazy"></iframe>
                    </div>
                </article>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>

                <div class="complete-panel col-span-full" id="complete-panel">
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
                        <div style="width:44px;height:44px;border-radius:50%;background:rgba(16,185,129,.15);display:flex;align-items:center;justify-content:center;color:var(--success)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div>
                            <div style="font-weight:800;color:var(--success)">Lesson Complete!</div>
                            <div style="font-size:0.85rem;color:var(--text-muted)" id="cp-sub-text">Great work — keep going.</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap" id="cp-actions">
                        <?php if ($has_assessments): foreach ($lesson_assessments as $a): ?>
                        <a class="btn btn-primary" href="take_assessment.php?assessment_id=<?= (int)$a['id'] ?>&from_lesson=<?= $lesson_id ?>&unit_id=<?= $unit_id ?>">
                            Take <?= ucfirst($a['type']) ?>
                        </a>
                        <?php endforeach; endif; ?>
                        <?php if ($next_lesson): ?>
                        <a class="btn btn-success" href="lesson_view.php?lesson_id=<?= (int)$next_lesson['id'] ?>&unit_id=<?= $unit_id ?>">Next Lesson</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bottom-bar col-span-full">
                    <?php if ($prev_lesson): ?>
                    <a class="btn btn-ghost" href="lesson_view.php?lesson_id=<?= (int)$prev_lesson['id'] ?>&unit_id=<?= $unit_id ?>">← Previous</a>
                    <?php else: ?>
                    <a class="btn btn-ghost" href="course_view.php?unit_id=<?= $unit_id ?>&browse=1">← Course</a>
                    <?php endif; ?>
                    <a href="my_progress.php?unit_id=<?= $unit_id ?>" class="btn btn-ghost">View progress</a>
                </div>

                <div id="scroll-sentinel" style="height:1px;grid-column:1/-1"></div>
            </div>
        </div>
    </div>

    <!-- AI Assistant -->
    <aside class="ai-panel" id="ai-panel">
        <div class="ai-panel-head">
            <h3>AI Study Assistant</h3>
            <button type="button" class="btn btn-ghost btn-icon" id="ai-close">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="ai-messages" id="ai-messages"></div>
        <form class="ai-input-row" id="ai-form">
            <input type="text" id="ai-input" placeholder="Ask about this lesson…" autocomplete="off">
            <button type="submit" class="btn btn-primary btn-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            </button>
        </form>
    </aside>
</div>

<!-- Quiz modal -->
<div class="modal-overlay" id="quiz-modal">
    <div class="modal-panel">
        <div class="modal-head">
            <h3 style="font-weight:800;font-size:1.05rem">Lesson Assessments</h3>
            <p style="font-size:0.85rem;opacity:.9;margin-top:4px">Complete a quiz or CAT linked to this lesson.</p>
        </div>
        <div class="modal-body">
            <?php if ($has_assessments): foreach ($lesson_assessments as $a): ?>
            <a href="take_assessment.php?assessment_id=<?= (int)$a['id'] ?>&from_lesson=<?= $lesson_id ?>&unit_id=<?= $unit_id ?>"
               style="display:flex;align-items:center;gap:12px;padding:14px;margin-bottom:10px;border-radius:10px;border:1px solid var(--stroke);text-decoration:none;color:var(--text);transition:var(--tr)"
               onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--stroke)'">
                <span style="font-size:0.7rem;font-weight:700;padding:4px 10px;border-radius:999px;background:rgba(99,102,241,.1);color:var(--accent)"><?= strtoupper($a['type']) ?></span>
                <span style="flex:1;font-weight:600"><?= htmlspecialchars($a['title']) ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
            <?php endforeach; else: ?>
            <p style="color:var(--text-muted)">No assessments are linked to this lesson yet.</p>
            <?php endif; ?>
            <button type="button" class="btn btn-ghost" data-close-modal style="width:100%;margin-top:12px">Close</button>
        </div>
    </div>
</div>

<!-- Assessment gate modal (on complete) -->
<div class="modal-overlay ap-overlay" id="ap-overlay">
    <div class="modal-panel">
        <div class="modal-head">
            <h3 style="font-weight:800">Complete assessments first</h3>
            <p style="font-size:0.85rem;opacity:.9;margin-top:4px">Attempt these before marking the lesson complete.</p>
        </div>
        <div class="modal-body">
            <?php foreach ($lesson_assessments as $a): ?>
            <a href="take_assessment.php?assessment_id=<?= (int)$a['id'] ?>&from_lesson=<?= $lesson_id ?>&unit_id=<?= $unit_id ?>"
               style="display:block;padding:12px;margin-bottom:8px;border-radius:8px;border:1px solid var(--stroke);text-decoration:none;color:var(--text)">
                <?= htmlspecialchars($a['title']) ?> (<?= strtoupper($a['type']) ?>)
            </a>
            <?php endforeach; ?>
            <button type="button" class="btn btn-ghost" onclick="skipAssessments()" style="width:100%;margin-top:8px">Skip — mark complete anyway</button>
        </div>
    </div>
</div>

<script>
window.LESSON_DASHBOARD = {
    moduleProgress: <?= (int)$module_progress_pct ?>,
    lessonChart: <?= json_encode($lesson_chart) ?>,
    lessonTitle: <?= json_encode($lesson['title']) ?>,
    hasAssessments: <?= $has_assessments ? 'true' : 'false' ?>
};
const LESSON_ID = <?= $lesson_id ?>;
const UNIT_ID = <?= $unit_id ?>;
const NEXT_LESSON_ID = <?= $next_lesson ? (int)$next_lesson['id'] : 'null' ?>;
const LESSON_ASSESSMENTS = <?= json_encode(array_values($lesson_assessments)) ?>;
const ALREADY_COMPLETE = <?= $is_completed ? 'true' : 'false' ?>;
let shouldNavigateAfterComplete = false;
let autoCompleteTriggered = ALREADY_COMPLETE;
let hasScrolled = false;
const pageLoadTime = Date.now();
const MIN_READ_MS = 3000;
</script>
<script src="js/lesson-dashboard.js"></script>
<script>
// Lesson completion logic (uses window.lessonContentScroll from lesson-dashboard.js)
const contentArea = window.lessonContentScroll || document.getElementById('content-scroll');

function showAutoCompleteToast() {
    document.getElementById('ac-toast')?.classList.add('show');
    setTimeout(() => document.getElementById('ac-toast')?.classList.remove('show'), 3000);
}

contentArea?.addEventListener('scroll', () => { hasScrolled = true; }, { passive: true, once: true });

if (!ALREADY_COMPLETE && 'IntersectionObserver' in window) {
    const sentinel = document.getElementById('scroll-sentinel');
    if (sentinel && contentArea) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting || autoCompleteTriggered) return;
                const timeOnPage = Date.now() - pageLoadTime;
                const finish = () => {
                    if (autoCompleteTriggered) return;
                    autoCompleteTriggered = true;
                    if (LESSON_ASSESSMENTS.length === 0) shouldNavigateAfterComplete = true;
                    observer.disconnect();
                    showAutoCompleteToast();
                    markComplete();
                };
                if (!hasScrolled || timeOnPage < MIN_READ_MS) {
                    setTimeout(finish, Math.max(500, MIN_READ_MS - timeOnPage));
                    return;
                }
                setTimeout(finish, 600);
            });
        }, { root: contentArea, threshold: 0.5 });
        observer.observe(sentinel);
    }
}

function handleLessonEnd() {
    if (autoCompleteTriggered) return;
    autoCompleteTriggered = true;
    if (LESSON_ASSESSMENTS.length > 0) {
        document.getElementById('ap-overlay')?.classList.add('open');
    } else {
        markComplete();
    }
}

document.getElementById('ap-overlay')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});

function skipAssessments() {
    shouldNavigateAfterComplete = true;
    document.getElementById('ap-overlay')?.classList.remove('open');
    markComplete();
}

function markComplete() {
    const btn = document.getElementById('complete-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>'; }

    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('unit_id', UNIT_ID);

    fetch('ajax/mark_lesson_complete.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                if (NEXT_LESSON_ID && shouldNavigateAfterComplete) {
                    setTimeout(() => {
                        window.location.href = `lesson_view.php?lesson_id=${NEXT_LESSON_ID}&unit_id=${UNIT_ID}`;
                    }, 800);
                    return;
                }
                if (btn) btn.style.display = 'none';
                document.getElementById('complete-panel')?.classList.add('show');
                const check = document.getElementById(`lesson-check-${LESSON_ID}`);
                if (check) check.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                const fill = document.getElementById('module-progress-fill');
                const label = document.getElementById('module-pct-label');
                if (fill && label) {
                    const pct = Math.min(100, parseInt(label.textContent) + Math.round(100 / <?= max(1, $total_module_lessons) ?>));
                    fill.style.width = pct + '%';
                    label.textContent = pct + '%';
                }
                if (LESSON_ASSESSMENTS.length > 0) {
                    setTimeout(() => document.getElementById('ap-overlay')?.classList.add('open'), 1200);
                }
            } else {
                if (btn) { btn.disabled = false; btn.innerHTML = 'Mark complete'; }
                autoCompleteTriggered = false;
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; }
            autoCompleteTriggered = false;
        });
}
</script>
</body>
</html>
