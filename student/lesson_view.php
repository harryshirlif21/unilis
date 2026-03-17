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
$completed_lessons = []; // all completed lessons in this module (for sidebar checks)
try {
    $stmt = $conn->prepare("SELECT id FROM student_progress WHERE student_id = ? AND lesson_id = ? AND event_type = 'lesson_completed' LIMIT 1");
    $stmt->bind_param("ii", $student_id, $lesson_id);
    $stmt->execute();
    $is_completed = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fetch all completed lesson IDs in this module for sidebar
    if (!empty($module_lessons)) {
        $mids  = array_column($module_lessons, 'id');
        $ph    = implode(',', array_fill(0, count($mids), '?'));
        $types = 'i' . str_repeat('i', count($mids));
        $stmt  = $conn->prepare("
            SELECT lesson_id FROM student_progress
            WHERE student_id = ? AND lesson_id IN ($ph) AND event_type = 'lesson_completed'
        ");
        $stmt->bind_param($types, $student_id, ...$mids);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $completed_lessons[$row['lesson_id']] = true;
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Mark as viewed (upsert — update completed_at if already exists)
try {
    $stmt = $conn->prepare("
        INSERT INTO student_progress (student_id, unit_id, lesson_id, event_type, completed_at)
        VALUES (?, ?, ?, 'lesson_viewed', NOW())
        ON DUPLICATE KEY UPDATE completed_at = NOW()
    ");
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

// ── Fetch assessments tied to this lesson (quiz/cat linked via lesson_id OR module_id) ──
// Also fetch unit-wide quizzes not yet submitted
$lesson_assessments = [];
try {
    // 1. Directly linked to this lesson
    // 2. Linked to the same module as this lesson
    // Both only quiz/cat types, published, not yet submitted by this student
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.type, a.time_limit_mins, a.total_marks, a.pass_mark
        FROM assessments a
        LEFT JOIN assessment_submissions asub
            ON asub.assessment_id = a.id AND asub.student_id = ?
        WHERE a.unit_id      = ?
          AND a.is_published  = 1
          AND a.type         IN ('quiz', 'cat')
          AND asub.id        IS NULL
          AND (
              a.lesson_id  = ?
              OR a.module_id = ?
          )
        ORDER BY a.type ASC, a.created_at ASC
    ");
    $stmt->bind_param("iiii", $student_id, $unit_id, $lesson_id, $lesson['module_id']);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $lesson_assessments[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log("lesson_assessments: " . $e->getMessage()); }
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
.video-upload-wrap{background:#000;border-radius:var(--r) var(--r) 0 0;overflow:hidden;aspect-ratio:16/9}
.video-upload-wrap video{width:100%;height:100%;display:block;object-fit:contain}
.video-meta{display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--surf2);border-top:1px solid var(--border)}
.video-meta i{color:var(--accent);font-size:1rem;flex-shrink:0}
.video-meta-name{font-size:.83rem;font-weight:500;color:var(--text);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* AUDIO BLOCK */
.block-audio{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);padding:14px 18px;display:flex;align-items:center;gap:14px}
.block-audio i{font-size:1.4rem;color:var(--accent)}
.audio-info{flex:1}
.audio-name{font-size:.85rem;font-weight:500;margin-bottom:6px}
.block-audio audio{width:100%;accent-color:var(--accent);height:32px}

/* DIAGRAM BLOCK */
.block-diagram{text-align:center}
.block-diagram img{max-width:100%;border-radius:var(--rs);border:1px solid var(--border)}

/* PDF BLOCK */
.block-pdf{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow)}
.pdf-topbar{display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--surf2);border-bottom:1px solid var(--border)}
.pdf-topbar i{color:#f97316;font-size:1.1rem;flex-shrink:0}
.pdf-topbar-name{flex:1;font-size:.85rem;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pdf-topbar-caption{font-size:.78rem;color:var(--muted);margin-right:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
.pdf-actions{display:flex;gap:6px;flex-shrink:0}
.pdf-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:var(--rs);font-size:.78rem;font-weight:600;text-decoration:none;border:1px solid;transition:var(--tr);cursor:pointer;font-family:inherit;background:transparent}
.pdf-btn-open{color:#f97316;border-color:rgba(249,115,22,.3)}.pdf-btn-open:hover{background:rgba(249,115,22,.1)}
.pdf-btn-dl{color:var(--accent);border-color:rgba(79,110,247,.3)}.pdf-btn-dl:hover{background:rgba(79,110,247,.1)}
.pdf-toggle{color:var(--muted);border-color:var(--border)}.pdf-toggle:hover{color:var(--text);border-color:var(--text)}
.pdf-frame-wrap{transition:height .3s ease;overflow:hidden}
.pdf-frame-wrap iframe{width:100%;height:560px;border:none;display:block}

/* READING PROGRESS BAR */
.read-progress-bar{position:fixed;top:0;left:0;height:3px;background:var(--accent);
                   width:0%;z-index:200;transition:width .1s linear;border-radius:0 2px 2px 0}

/* AUTO-COMPLETE TOAST */
.autocomplete-toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(20px);
                    background:var(--green);color:#fff;padding:12px 22px;border-radius:999px;
                    font-size:.88rem;font-weight:600;display:flex;align-items:center;gap:9px;
                    box-shadow:0 4px 20px rgba(16,185,129,.4);opacity:0;pointer-events:none;
                    transition:opacity .3s ease,transform .3s ease;z-index:150}
.autocomplete-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.bottom-nav{display:flex;align-items:center;justify-content:space-between;margin-top:36px;padding-top:20px;border-top:1px solid var(--border);gap:12px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:500;cursor:pointer;border:none;transition:var(--tr);text-decoration:none}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:#4060e0;transform:translateY(-1px)}
.btn-success{background:var(--green);color:#fff}.btn-success:hover{background:#0da070}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn:disabled{opacity:.4;cursor:not-allowed;transform:none!important}
.complete-badge{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:var(--rs);background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:var(--green);font-size:.85rem;font-weight:500}

/* ── ASSESSMENT PROMPT MODAL ────────────────────────────── */
.ap-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);
            z-index:300;display:flex;align-items:center;justify-content:center;
            padding:20px;opacity:0;pointer-events:none;transition:opacity .2s ease}
.ap-overlay.open{opacity:1;pointer-events:all}
.ap-modal{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);
          padding:0;width:480px;max-width:100%;box-shadow:0 8px 40px rgba(0,0,0,.15);
          transform:translateY(10px);transition:transform .2s ease;overflow:hidden}
.ap-overlay.open .ap-modal{transform:translateY(0)}
.ap-head{background:linear-gradient(135deg,#4f6ef7,#7c3aed);padding:20px 24px;color:#fff}
.ap-head h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;margin-bottom:4px}
.ap-head p{font-size:.83rem;opacity:.85}
.ap-body{padding:20px 24px}
.ap-assess-list{display:flex;flex-direction:column;gap:10px;margin-bottom:20px}
.ap-assess-item{display:flex;align-items:center;gap:14px;padding:13px 16px;
                background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);
                text-decoration:none;color:var(--text);transition:var(--tr)}
.ap-assess-item:hover{border-color:var(--accent);background:rgba(79,110,247,.04);transform:translateX(3px)}
.ap-type-badge{font-size:.68rem;padding:3px 10px;border-radius:999px;font-weight:700;
               text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;flex-shrink:0}
.ap-quiz{background:rgba(79,110,247,.1);color:var(--accent);border:1px solid rgba(79,110,247,.2)}
.ap-cat{background:rgba(245,158,11,.1);color:var(--amber);border:1px solid rgba(245,158,11,.2)}
.ap-assess-info{flex:1}
.ap-assess-title{font-size:.88rem;font-weight:600;margin-bottom:2px}
.ap-assess-meta{font-size:.75rem;color:var(--muted);display:flex;gap:10px}
.ap-skip{font-size:.8rem;color:var(--muted);text-align:center;cursor:pointer;
         padding:6px;border-radius:var(--rs);transition:var(--tr);border:none;
         background:transparent;width:100%;font-family:inherit}
.ap-skip:hover{color:var(--text);background:var(--surf2)}

/* ── LESSON COMPLETE PANEL ──────────────────────────────── */
.complete-panel{background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(79,110,247,.06));
                border:1px solid rgba(16,185,129,.25);border-radius:var(--r);
                padding:24px 28px;margin-top:8px;display:none}
.complete-panel.show{display:block}
.cp-top{display:flex;align-items:center;gap:14px;margin-bottom:16px}
.cp-icon{width:44px;height:44px;border-radius:50%;background:rgba(16,185,129,.15);
         display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cp-icon i{font-size:1.2rem;color:var(--green)}
.cp-title{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:var(--green)}
.cp-sub{font-size:.83rem;color:var(--muted);margin-top:2px}
.cp-actions{display:flex;gap:10px;flex-wrap:wrap}
.cp-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--rs);
        font-size:.85rem;font-weight:600;text-decoration:none;transition:var(--tr);
        font-family:'DM Sans',sans-serif;border:none;cursor:pointer}
.cp-btn-primary{background:var(--accent);color:#fff}.cp-btn-primary:hover{background:#3d5de8}
.cp-btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}
.cp-btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.cp-btn-green{background:var(--green);color:#fff}.cp-btn-green:hover{background:#0da070}

/* EMPTY */
.no-content{text-align:center;padding:60px 20px;color:var(--dim)}
.no-content i{font-size:2rem;margin-bottom:12px;display:block;opacity:.3}

::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
</style>
</head>
<body>
<!-- Reading progress bar at top of page -->
<div class="read-progress-bar" id="read-progress"></div>
<!-- Auto-complete toast notification -->
<div class="autocomplete-toast" id="ac-toast">
    <i class="fas fa-circle-check"></i> Lesson marked complete!
</div>

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
            <span id="lesson-check-<?= $ml['id'] ?>" class="ln-check">
                <?php if (isset($completed_lessons[$ml['id']])): ?>
                    <i class="fas fa-check-circle"></i>
                <?php endif; ?>
            </span>
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
                <?php
                    $vidType = $data['type'] ?? (isset($data['src']) ? 'upload' : 'url');
                    $embed   = $data['embed'] ?? '';
                    $vidUrl  = $data['url']   ?? '';
                    $vidSrc  = $data['src']   ?? '';
                    $vidName = $data['name']  ?? 'Video';
                ?>
                <div class="block-video">
                    <?php if ($vidType === 'upload' && $vidSrc): ?>
                        <div class="video-upload-wrap">
                            <video controls preload="metadata"
                                   src="<?= htmlspecialchars('../' . $vidSrc) ?>"
                                   controlsList="nodownload">
                                Your browser does not support HTML5 video.
                            </video>
                        </div>
                        <div class="video-meta">
                            <i class="fas fa-film"></i>
                            <span class="video-meta-name"><?= htmlspecialchars($vidName) ?></span>
                        </div>
                    <?php elseif ($embed): ?>
                        <div class="video-embed-wrap">
                            <iframe src="<?= htmlspecialchars($embed) ?>"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen loading="lazy"></iframe>
                        </div>
                    <?php elseif ($vidUrl): ?>
                        <div style="padding:18px 20px;display:flex;align-items:center;gap:12px;color:var(--muted)">
                            <i class="fas fa-video" style="font-size:1.2rem;color:var(--accent)"></i>
                            <a href="<?= htmlspecialchars($vidUrl) ?>" target="_blank"
                               style="color:var(--accent);font-size:.88rem;word-break:break-all">
                                <?= htmlspecialchars($vidUrl) ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="padding:20px;color:var(--muted);text-align:center;font-size:.85rem">
                            <i class="fas fa-video-slash"></i> No video available
                        </div>
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
                    $src     = '../' . ($data['src'] ?? '');
                    $caption = $data['caption'] ?? '';
                ?>
                <div class="block-diagram">
                    <img src="<?= htmlspecialchars($src) ?>"
                         alt="<?= htmlspecialchars($caption ?: 'Diagram') ?>"
                         loading="lazy">
                    <?php if ($caption): ?>
                        <p class="block-caption"><?= htmlspecialchars($caption) ?></p>
                    <?php endif; ?>
                </div>

            <?php elseif ($block['block_type'] === 'pdf'): ?>
                <?php
                    $pdfSrc     = '../' . ($data['src']     ?? '');
                    $pdfName    = $data['name']    ?? 'Document';
                    $pdfCaption = $data['caption'] ?? '';
                    $pdfId      = 'pdf-' . $block['id'];
                ?>
                <div class="block-pdf">
                    <div class="pdf-topbar">
                        <i class="fas fa-file-pdf"></i>
                        <span class="pdf-topbar-name"><?= htmlspecialchars($pdfName) ?></span>
                        <?php if ($pdfCaption): ?>
                            <span class="pdf-topbar-caption"><?= htmlspecialchars($pdfCaption) ?></span>
                        <?php endif; ?>
                        <div class="pdf-actions">
                            <button class="pdf-btn pdf-toggle" onclick="togglePdfPane('<?= $pdfId ?>')"
                                    id="toggle-<?= $pdfId ?>">
                                <i class="fas fa-eye" id="toggle-icon-<?= $pdfId ?>"></i>
                                <span id="toggle-label-<?= $pdfId ?>">Show</span>
                            </button>
                            <a class="pdf-btn pdf-btn-open"
                               href="<?= htmlspecialchars($pdfSrc) ?>" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Open
                            </a>
                            <a class="pdf-btn pdf-btn-dl"
                               href="<?= htmlspecialchars($pdfSrc) ?>" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                    <div class="pdf-frame-wrap" id="<?= $pdfId ?>" style="height:0;overflow:hidden">
                        <iframe src=""
                                data-src="<?= htmlspecialchars($pdfSrc) ?>#toolbar=1&navpanes=1&view=FitH"
                                title="<?= htmlspecialchars($pdfName) ?>"
                                loading="lazy"
                                id="frame-<?= $pdfId ?>"></iframe>
                    </div>
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

            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <?php if ($is_completed): ?>
                    <span class="complete-badge">
                        <i class="fas fa-circle-check"></i> Lesson Completed
                    </span>
                <?php else: ?>
                    <button class="btn btn-success" id="complete-btn" onclick="handleLessonEnd()">
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

        <!-- Scroll sentinel — auto-complete fires when this becomes visible -->
        <div id="scroll-sentinel" style="height:1px;margin-top:40px"></div>

    </main>
</div>

<!-- ── ASSESSMENT PROMPT MODAL ─────────────────────────────────── -->
<div class="ap-overlay" id="ap-overlay">
    <div class="ap-modal">
        <div class="ap-head">
            <h3><i class="fas fa-tasks"></i> &nbsp;Almost there — complete these first</h3>
            <p>You need to attempt the following before this lesson is marked complete.</p>
        </div>
        <div class="ap-body">
            <div class="ap-assess-list" id="ap-list">
                <?php foreach ($lesson_assessments as $a): ?>
                <a class="ap-assess-item"
                   href="take_assessment.php?assessment_id=<?= $a['id'] ?>&from_lesson=<?= $lesson_id ?>&unit_id=<?= $unit_id ?>">
                    <span class="ap-type-badge ap-<?= $a['type'] ?>"><?= strtoupper($a['type']) ?></span>
                    <div class="ap-assess-info">
                        <div class="ap-assess-title"><?= htmlspecialchars($a['title']) ?></div>
                        <div class="ap-assess-meta">
                            <span><i class="fas fa-star"></i> <?= $a['total_marks'] ?> marks</span>
                            <span><i class="fas fa-check-circle"></i> Pass: <?= $a['pass_mark'] ?></span>
                            <?php if ($a['time_limit_mins']): ?>
                            <span><i class="fas fa-clock"></i> <?= $a['time_limit_mins'] ?>min</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right" style="color:var(--accent);flex-shrink:0"></i>
                </a>
                <?php endforeach; ?>
            </div>
            <button class="ap-skip" onclick="skipAssessments()">
                <i class="fas fa-forward"></i> Skip for now — mark lesson complete anyway
            </button>
        </div>
    </div>
</div>

<!-- ── LESSON COMPLETE PANEL (shown after marking complete) ─────── -->
<div class="complete-panel" id="complete-panel">
    <div class="cp-top">
        <div class="cp-icon"><i class="fas fa-circle-check"></i></div>
        <div>
            <div class="cp-title">Lesson Complete!</div>
            <div class="cp-sub" id="cp-sub-text">Great work — keep going.</div>
        </div>
    </div>
    <div class="cp-actions" id="cp-actions">
        <?php if (!empty($lesson_assessments)): ?>
            <?php foreach ($lesson_assessments as $a): ?>
            <a class="cp-btn cp-btn-primary"
               href="take_assessment.php?assessment_id=<?= $a['id'] ?>&from_lesson=<?= $lesson_id ?>&unit_id=<?= $unit_id ?>">
                <i class="fas fa-<?= $a['type']==='quiz'?'circle-question':'clipboard-check' ?>"></i>
                Take <?= ucfirst($a['type']) ?>: <?= htmlspecialchars(mb_substr($a['title'],0,30)) ?>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($next_lesson): ?>
        <a class="cp-btn cp-btn-green"
           href="lesson_view.php?lesson_id=<?= $next_lesson['id'] ?>&unit_id=<?= $unit_id ?>">
            Next Lesson <i class="fas fa-arrow-right"></i>
        </a>
        <?php else: ?>
        <a class="cp-btn cp-btn-green" href="course_view.php?unit_id=<?= $unit_id ?>">
            Back to Course <i class="fas fa-home"></i>
        </a>
        <?php endif; ?>
    </div>
</div>

<script>
const LESSON_ID          = <?= $lesson_id ?>;
const UNIT_ID            = <?= $unit_id ?>;
const NEXT_LESSON_ID     = <?= $next_lesson  ? $next_lesson['id']  : 'null' ?>;
const LESSON_ASSESSMENTS = <?= json_encode(array_values($lesson_assessments)) ?>;
const ALREADY_COMPLETE   = <?= $is_completed ? 'true' : 'false' ?>;

// ── READING PROGRESS BAR ──────────────────────────────────
const progressBar = document.getElementById('read-progress');
const contentArea = document.querySelector('.content-area');

function updateReadProgress() {
    if (!contentArea || !progressBar) return;
    const scrollTop  = contentArea.scrollTop;
    const scrollMax  = contentArea.scrollHeight - contentArea.clientHeight;
    const pct        = scrollMax > 0 ? Math.min(100, (scrollTop / scrollMax) * 100) : 100;
    progressBar.style.width = pct + '%';
}
contentArea?.addEventListener('scroll', updateReadProgress, { passive: true });
updateReadProgress();

// ── AUTO-COMPLETE ON SCROLL TO BOTTOM ─────────────────────
// Fires when student scrolls to the bottom sentinel AND has been on the page
// for at least 3 seconds AND has scrolled at least once.
// Prevents instant trigger on short lessons.
let autoCompleteTriggered = ALREADY_COMPLETE;
let hasScrolled           = false;
let pageLoadTime          = Date.now();
const MIN_READ_MS         = 3000; // must spend at least 3s on page

// Track that the student has actually scrolled
contentArea?.addEventListener('scroll', () => { hasScrolled = true; }, { passive: true, once: true });

if (!ALREADY_COMPLETE && 'IntersectionObserver' in window) {
    const sentinel = document.getElementById('scroll-sentinel');
    if (sentinel) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting || autoCompleteTriggered) return;

                const timeOnPage = Date.now() - pageLoadTime;

                // Must have scrolled AND spent minimum time on page
                if (!hasScrolled || timeOnPage < MIN_READ_MS) {
                    // Not ready yet — check again after remaining time
                    const wait = Math.max(500, MIN_READ_MS - timeOnPage);
                    setTimeout(() => {
                        // Re-check: is the sentinel still visible?
                        const rect = sentinel.getBoundingClientRect();
                        const areaRect = contentArea.getBoundingClientRect();
                        const visible = rect.top >= areaRect.top && rect.bottom <= areaRect.bottom;
                        if (visible && hasScrolled && !autoCompleteTriggered) {
                            autoCompleteTriggered = true;
                            observer.disconnect();
                            showAutoCompleteToast();
                            markComplete();
                        }
                    }, wait);
                    return;
                }

                // All conditions met
                autoCompleteTriggered = true;
                observer.disconnect();
                setTimeout(() => {
                    showAutoCompleteToast();
                    markComplete();
                }, 600);
            });
        }, {
            root:      contentArea,
            threshold: 0.5,   // 50% visible is enough — sentinel is 1px so 1.0 can be unreliable
        });
        observer.observe(sentinel);
    }
}

// Fallback: if IntersectionObserver not supported, keep the manual button
function showAutoCompleteToast() {
    const toast = document.getElementById('ac-toast');
    if (!toast) return;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// ── PDF PANE TOGGLE ───────────────────────────────────────
function togglePdfPane(id) {
    const wrap  = document.getElementById(id);
    const frame = document.getElementById('frame-' + id);
    const label = document.getElementById('toggle-label-' + id);
    const icon  = document.getElementById('toggle-icon-' + id);
    if (!wrap) return;
    const isOpen = wrap.style.height !== '0px' && wrap.style.height !== '';
    if (isOpen) {
        wrap.style.height   = '0';
        wrap.style.overflow = 'hidden';
        if (label) label.textContent = 'Show';
        if (icon)  icon.className    = 'fas fa-eye';
    } else {
        if (frame) {
            const ds = frame.getAttribute('data-src');
            if (ds && (!frame.src || frame.src === window.location.href)) frame.src = ds;
        }
        wrap.style.height   = '';
        wrap.style.overflow = 'visible';
        if (label) label.textContent = 'Hide';
        if (icon)  icon.className    = 'fas fa-eye-slash';
    }
}

// ── HANDLE LESSON END (manual button) ────────────────────
// Called when "Mark as Complete" is clicked manually.
function handleLessonEnd() {
    if (autoCompleteTriggered) return; // already handled by scroll
    autoCompleteTriggered = true;
    if (LESSON_ASSESSMENTS.length > 0) {
        document.getElementById('ap-overlay').classList.add('open');
    } else {
        markComplete();
    }
}

// Close quiz modal by clicking outside
document.getElementById('ap-overlay')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});

// "Skip for now" in modal — mark complete and continue
function skipAssessments() {
    document.getElementById('ap-overlay').classList.remove('open');
    markComplete();
}

// ── MARK COMPLETE ─────────────────────────────────────────
function markComplete() {
    const btn = document.getElementById('complete-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>'; }

    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('unit_id',   UNIT_ID);

    fetch('ajax/mark_lesson_complete.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // Hide manual button
                if (btn) btn.style.display = 'none';

                // Show completion panel
                const panel = document.getElementById('complete-panel');
                if (panel) {
                    panel.classList.add('show');
                    // Scroll the content area to show the panel
                    setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 100);
                }

                // Update sidebar check icon
                const check = document.getElementById(`lesson-check-${LESSON_ID}`);
                if (check) {
                    check.className   = 'ln-check';
                    check.innerHTML   = '<i class="fas fa-check-circle"></i>';
                }

                // Update sub-text
                const sub = document.getElementById('cp-sub-text');
                if (sub) {
                    if (LESSON_ASSESSMENTS.length > 0) {
                        sub.textContent = `${LESSON_ASSESSMENTS.length} assessment${LESSON_ASSESSMENTS.length > 1 ? 's' : ''} ready — take ${LESSON_ASSESSMENTS.length > 1 ? 'them' : 'it'} now!`;
                    } else if (NEXT_LESSON_ID) {
                        sub.textContent = 'Ready for the next lesson?';
                    } else {
                        sub.textContent = "You've completed all lessons in this module!";
                    }
                }

                // If there are assessments, auto-show the quiz prompt after a brief moment
                if (LESSON_ASSESSMENTS.length > 0) {
                    setTimeout(() => {
                        document.getElementById('ap-overlay')?.classList.add('open');
                    }, 1200);
                }
            } else {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Mark as Complete'; }
                autoCompleteTriggered = false;
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Mark as Complete'; }
            autoCompleteTriggered = false;
        });
}
</script>
<style>
.spinner{width:12px;height:12px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</body>
</html>