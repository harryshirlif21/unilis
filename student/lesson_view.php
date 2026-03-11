<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id  = $_SESSION['user_id'];
$lesson_id   = intval($_GET['lesson_id'] ?? 0);
$unit_id     = intval($_GET['unit_id']   ?? 0);

if (!$lesson_id) { header("Location: course_view.php"); exit; }

// Fetch lesson
$lesson = null;
try {
    $stmt = $conn->prepare("
        SELECT l.*, m.title AS module_title, m.id AS module_id
        FROM course_lessons l
        JOIN course_modules m ON l.module_id = m.id
        WHERE l.id = ? AND l.unit_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $unit_id);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

if (!$lesson) { header("Location: course_view.php?unit_id=$unit_id"); exit; }

// Fetch content blocks
$blocks = [];
try {
    $stmt = $conn->prepare("SELECT id, block_type, content, position FROM lesson_content_blocks WHERE lesson_id = ? ORDER BY position ASC");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $blocks[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Fetch all lessons in module (for prev/next nav)
$module_lessons = [];
try {
    $stmt = $conn->prepare("SELECT id, title, lesson_number FROM course_lessons WHERE module_id = ? ORDER BY position ASC");
    $stmt->bind_param("i", $lesson['module_id']);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $module_lessons[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

$current_idx = -1;
foreach ($module_lessons as $i => $ml) { if ($ml['id'] == $lesson_id) { $current_idx = $i; break; } }
$prev_lesson = $current_idx > 0 ? $module_lessons[$current_idx - 1] : null;
$next_lesson = $current_idx < count($module_lessons) - 1 ? $module_lessons[$current_idx + 1] : null;

// Check if already marked complete
$is_completed = false;
try {
    $stmt = $conn->prepare("SELECT id FROM student_progress WHERE student_id = ? AND lesson_id = ? AND event_type = 'lesson_completed'");
    $stmt->bind_param("ii", $student_id, $lesson_id);
    $stmt->execute();
    $is_completed = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Mark as viewed
try {
    $stmt = $conn->prepare("INSERT IGNORE INTO student_progress (student_id, unit_id, lesson_id, event_type) VALUES (?, ?, ?, 'lesson_viewed')");
    $stmt->bind_param("iii", $student_id, $unit_id, $lesson_id);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Unit name
$unit_name = '';
try {
    $stmt = $conn->prepare("SELECT name FROM units WHERE id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $unit_name = $row['name'] ?? '';
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>L<?= $lesson['lesson_number'] ?>: <?= htmlspecialchars($lesson['title']) ?> — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
    --bg:#f4f6fb;--surf:#fff;--surf2:#f8faff;--surf3:#eef1fa;
    --border:#e2e8f5;--accent:#4f6ef7;--green:#10b981;--amber:#f59e0b;--red:#ef4444;
    --text:#1e2235;--muted:#64748b;--dim:#a0aec0;
    --r:12px;--rs:7px;--tr:.15s ease;
    --shadow:0 2px 16px rgba(79,110,247,.07);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 28px;height:54px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:var(--shadow)}
.breadcrumb{display:flex;align-items:center;gap:8px;font-size:.82rem;color:var(--muted)}
.breadcrumb a{color:var(--accent);text-decoration:none}.breadcrumb a:hover{text-decoration:underline}
.breadcrumb .sep{color:var(--dim)}
.nav-right{display:flex;gap:8px}
.btn-nav{background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:5px 12px;border-radius:var(--rs);font-size:.77rem;cursor:pointer;text-decoration:none;transition:var(--tr);font-family:'DM Sans',sans-serif}
.btn-nav:hover{background:var(--accent);color:#fff;border-color:var(--accent)}

.layout{display:flex;min-height:calc(100vh - 54px)}

/* LESSON NAV SIDEBAR */
.lesson-nav{width:240px;min-width:240px;background:var(--surf);border-right:1px solid var(--border);padding:16px 12px;overflow-y:auto;position:sticky;top:54px;height:calc(100vh - 54px)}
.ln-title{font-family:'Syne',sans-serif;font-size:.67rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);margin-bottom:10px;display:block}
.ln-item{display:flex;align-items:center;gap:9px;padding:8px 11px;border-radius:var(--rs);text-decoration:none;color:var(--muted);font-size:.83rem;transition:var(--tr);margin-bottom:2px;border:1px solid transparent}
.ln-item:hover{background:var(--surf2);color:var(--text)}
.ln-item.current{background:rgba(79,110,247,.08);border-color:rgba(79,110,247,.2);color:var(--accent);font-weight:500}
.ln-num{font-family:'Syne',sans-serif;font-size:.65rem;font-weight:700;opacity:.7}
.ln-check{color:var(--green);font-size:.65rem}

/* MAIN CONTENT */
.content-area{flex:1;overflow-y:auto;padding:32px 40px;max-width:820px}

/* LESSON HEADER */
.lesson-header{margin-bottom:28px}
.lesson-header .module-tag{font-size:.75rem;color:var(--muted);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.lesson-header h1{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;line-height:1.2;margin-bottom:10px}
.lesson-meta{display:flex;align-items:center;gap:14px;font-size:.8rem;color:var(--muted)}

/* CONTENT BLOCKS */
.block{margin-bottom:24px;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

/* TEXT BLOCK */
.block-text{font-size:.95rem;line-height:1.8;color:var(--text)}
.block-text h2{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin:20px 0 10px;color:var(--text)}
.block-text h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin:16px 0 8px;color:var(--muted)}
.block-text p{margin-bottom:12px}
.block-text ul,.block-text ol{padding-left:20px;margin-bottom:12px}
.block-text li{margin-bottom:5px;line-height:1.6}
.block-text blockquote{border-left:3px solid var(--accent);padding:8px 16px;background:var(--surf2);border-radius:0 var(--rs) var(--rs) 0;margin:12px 0;color:var(--muted);font-style:italic}
.block-text code{font-family:'JetBrains Mono',monospace;font-size:.83rem;background:var(--surf3);padding:2px 7px;border-radius:4px;color:var(--accent)}
.block-text pre{background:var(--surf3);padding:14px 16px;border-radius:var(--rs);overflow-x:auto;margin:12px 0}
.block-text pre code{background:none;padding:0}

/* IMAGE BLOCK */
.block-image{text-align:center}
.block-image img{max-width:100%;border-radius:var(--rs);box-shadow:var(--shadow);border:1px solid var(--border)}
.block-caption{font-size:.8rem;color:var(--muted);margin-top:8px;font-style:italic}

/* VIDEO BLOCK */
.block-video{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow)}
.video-embed-wrap{position:relative;padding-top:56.25%;background:#000}
.video-embed-wrap iframe{position:absolute;inset:0;width:100%;height:100%;border:none}

/* AUDIO BLOCK */
.block-audio{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);padding:14px 18px;display:flex;align-items:center;gap:14px}
.block-audio i{font-size:1.4rem;color:var(--accent)}
.audio-info{flex:1}
.audio-name{font-size:.85rem;font-weight:500;margin-bottom:6px}
.block-audio audio{width:100%;accent-color:var(--accent);height:32px}

/* DIAGRAM BLOCK */
.block-diagram{text-align:center}
.block-diagram img{max-width:100%;border-radius:var(--rs);border:1px solid var(--border)}
.diagram-pdf{display:flex;align-items:center;gap:10px;padding:14px 18px;background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);text-decoration:none;color:var(--text)}
.diagram-pdf:hover{border-color:var(--accent);color:var(--accent)}

/* BOTTOM NAV */
.bottom-nav{display:flex;align-items:center;justify-content:space-between;margin-top:36px;padding-top:20px;border-top:1px solid var(--border);gap:12px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:500;cursor:pointer;border:none;transition:var(--tr);text-decoration:none}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:#4060e0;transform:translateY(-1px)}
.btn-success{background:var(--green);color:#fff}.btn-success:hover{background:#0da070}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn:disabled{opacity:.4;cursor:not-allowed;transform:none!important}
.complete-badge{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:var(--rs);background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:var(--green);font-size:.85rem;font-weight:500}

/* EMPTY */
.no-content{text-align:center;padding:60px 20px;color:var(--dim)}
.no-content i{font-size:2rem;margin-bottom:12px;display:block;opacity:.3}

::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
</style>
</head>
<body>

<header class="topbar">
    <div class="breadcrumb">
        <a href="course_view.php?unit_id=<?= $unit_id ?>"><i class="fas fa-home"></i> <?= htmlspecialchars($unit_name) ?></a>
        <span class="sep"><i class="fas fa-chevron-right" style="font-size:.6rem"></i></span>
        <span><?= htmlspecialchars($lesson['module_title']) ?></span>
        <span class="sep"><i class="fas fa-chevron-right" style="font-size:.6rem"></i></span>
        <span style="color:var(--text)">L<?= $lesson['lesson_number'] ?>: <?= htmlspecialchars($lesson['title']) ?></span>
    </div>
    <div class="nav-right">
        <a href="my_progress.php?unit_id=<?= $unit_id ?>" class="btn-nav"><i class="fas fa-chart-line"></i> Progress</a>
        <a href="../dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- LESSON NAV -->
    <aside class="lesson-nav">
        <span class="ln-title"><?= htmlspecialchars($lesson['module_title']) ?></span>
        <?php foreach ($module_lessons as $ml): ?>
        <?php $isCurrent = ($ml['id'] == $lesson_id); ?>
        <a class="ln-item <?= $isCurrent ? 'current' : '' ?>"
           href="lesson_view.php?lesson_id=<?= $ml['id'] ?>&unit_id=<?= $unit_id ?>">
            <span class="ln-num">L<?= $ml['lesson_number'] ?></span>
            <span style="flex:1"><?= htmlspecialchars($ml['title']) ?></span>
        </a>
        <?php endforeach; ?>
    </aside>

    <!-- CONTENT -->
    <main class="content-area">

        <div class="lesson-header">
            <div class="module-tag">
                <i class="fas fa-layer-group"></i>
                <?= htmlspecialchars($lesson['module_title']) ?>
            </div>
            <h1>L<?= $lesson['lesson_number'] ?>: <?= htmlspecialchars($lesson['title']) ?></h1>
            <div class="lesson-meta">
                <span><i class="fas fa-puzzle-piece"></i> <?= count($blocks) ?> content block<?= count($blocks)!=1?'s':'' ?></span>
                <?php if ($is_completed): ?>
                    <span style="color:var(--green)"><i class="fas fa-circle-check"></i> Completed</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($blocks)): ?>
            <div class="no-content">
                <i class="fas fa-file-pen"></i>
                <p>No content has been added to this lesson yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($blocks as $block): ?>
            <?php $data = json_decode($block['content'], true); ?>

            <div class="block">
            <?php if ($block['block_type'] === 'text'): ?>
                <div class="block-text"><?= $block['content'] ?></div>

            <?php elseif ($block['block_type'] === 'image'): ?>
                <div class="block-image">
                    <img src="<?= htmlspecialchars('../' . ($data['src'] ?? '')) ?>"
                         alt="<?= htmlspecialchars($data['caption'] ?? 'Image') ?>"
                         loading="lazy">
                    <?php if (!empty($data['caption'])): ?>
                        <p class="block-caption"><?= htmlspecialchars($data['caption']) ?></p>
                    <?php endif; ?>
                </div>

            <?php elseif ($block['block_type'] === 'video'): ?>
                <div class="block-video">
                    <?php if (!empty($data['embed'])): ?>
                    <div class="video-embed-wrap">
                        <iframe src="<?= htmlspecialchars($data['embed']) ?>"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen loading="lazy"></iframe>
                    </div>
                    <?php else: ?>
                        <div style="padding:20px;color:var(--muted);text-align:center"><i class="fas fa-video"></i> Video URL: <a href="<?= htmlspecialchars($data['url'] ?? '#') ?>" target="_blank" style="color:var(--accent)"><?= htmlspecialchars($data['url'] ?? '') ?></a></div>
                    <?php endif; ?>
                </div>

            <?php elseif ($block['block_type'] === 'audio'): ?>
                <div class="block-audio">
                    <i class="fas fa-headphones"></i>
                    <div class="audio-info">
                        <div class="audio-name"><?= htmlspecialchars($data['name'] ?? 'Audio') ?></div>
                        <audio controls src="<?= htmlspecialchars('../' . ($data['src'] ?? '')) ?>"></audio>
                    </div>
                </div>

            <?php elseif ($block['block_type'] === 'diagram'): ?>
                <?php
                    $src      = '../' . ($data['src'] ?? '');
                    $ext      = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                    $caption  = $data['caption'] ?? '';
                    $isPdf    = ($ext === 'pdf');
                ?>
                <div class="block-diagram">
                    <?php if ($isPdf): ?>
                        <a class="diagram-pdf" href="<?= htmlspecialchars($src) ?>" target="_blank">
                            <i class="fas fa-file-pdf" style="font-size:1.4rem;color:var(--red)"></i>
                            <div>
                                <div style="font-weight:500"><?= $caption ?: 'View Diagram (PDF)' ?></div>
                                <div style="font-size:.75rem;color:var(--muted)">Opens in new tab</div>
                            </div>
                            <i class="fas fa-external-link-alt" style="margin-left:auto;color:var(--dim)"></i>
                        </a>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($src) ?>"
                             alt="<?= htmlspecialchars($caption ?: 'Diagram') ?>"
                             loading="lazy">
                        <?php if ($caption): ?>
                            <p class="block-caption"><?= htmlspecialchars($caption) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </div>

            <?php endforeach; ?>
        <?php endif; ?>

        <!-- BOTTOM NAVIGATION -->
        <div class="bottom-nav">
            <?php if ($prev_lesson): ?>
                <a class="btn btn-ghost" href="lesson_view.php?lesson_id=<?= $prev_lesson['id'] ?>&unit_id=<?= $unit_id ?>">
                    <i class="fas fa-arrow-left"></i> L<?= $prev_lesson['lesson_number'] ?>: <?= htmlspecialchars(substr($prev_lesson['title'],0,28)) ?>
                </a>
            <?php else: ?>
                <a class="btn btn-ghost" href="course_view.php?unit_id=<?= $unit_id ?>">
                    <i class="fas fa-arrow-left"></i> Back to Course
                </a>
            <?php endif; ?>

            <div style="display:flex;gap:10px;align-items:center">
                <?php if ($is_completed): ?>
                    <span class="complete-badge">
                        <i class="fas fa-circle-check"></i> Lesson Completed
                    </span>
                <?php else: ?>
                    <button class="btn btn-success" id="complete-btn" onclick="markComplete()">
                        <i class="fas fa-check-circle"></i> Mark as Complete
                    </button>
                <?php endif; ?>

                <?php if ($next_lesson): ?>
                    <a class="btn btn-primary" href="lesson_view.php?lesson_id=<?= $next_lesson['id'] ?>&unit_id=<?= $unit_id ?>">
                        Next: L<?= $next_lesson['lesson_number'] ?> <i class="fas fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a class="btn btn-primary" href="course_view.php?unit_id=<?= $unit_id ?>">
                        Back to Course <i class="fas fa-home"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<script>
const LESSON_ID = <?= $lesson_id ?>;
const UNIT_ID   = <?= $unit_id ?>;

function markComplete() {
    const btn = document.getElementById('complete-btn');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving...';

    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('unit_id',   UNIT_ID);

    fetch('ajax/mark_lesson_complete.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                btn.outerHTML = '<span class="complete-badge"><i class="fas fa-circle-check"></i> Lesson Completed</span>';
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Mark as Complete';
            }
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Mark as Complete'; });
}
</script>
<style>
.spinner{width:12px;height:12px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</body>
</html>
