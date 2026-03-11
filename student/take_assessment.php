<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id    = $_SESSION['user_id'];
$student_name  = $_SESSION['user_name'];
$assessment_id = intval($_GET['assessment_id'] ?? 0);

if (!$assessment_id) {
    header("Location: ../student/course_view.php");
    exit;
}

// Fetch assessment
$assessment = null;
try {
    $stmt = $conn->prepare("
        SELECT a.*, u.name AS unit_name
        FROM assessments a
        JOIN units u ON a.unit_id = u.id
        WHERE a.id = ? AND a.is_published = 1
    ");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

if (!$assessment) {
    die("<p style='font-family:sans-serif;padding:40px;color:#f87171'>Assessment not found or not available.</p>");
}

// Check if already submitted
$existing_submission = null;
try {
    $stmt = $conn->prepare("SELECT id, score, status, submitted_at FROM assessment_submissions WHERE assessment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assessment_id, $student_id);
    $stmt->execute();
    $existing_submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Fetch questions + options
$questions = [];
try {
    $stmt = $conn->prepare("SELECT id, question_text, question_type, marks, position FROM assessment_questions WHERE assessment_id = ? ORDER BY position ASC, id ASC");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['options'] = [];
        $os = $conn->prepare("SELECT id, option_text, match_pair, position FROM question_options WHERE question_id = ? ORDER BY position ASC");
        $os->bind_param("i", $row['id']);
        $os->execute();
        $or = $os->get_result();
        while ($opt = $or->fetch_assoc()) $row['options'][] = $opt;
        $os->close();
        // Shuffle MCQ options (prevent pattern memorisation)
        if ($row['question_type'] === 'mcq' && !empty($row['options'])) {
            shuffle($row['options']);
        }
        // For matching: shuffle right-side pairs
        if ($row['question_type'] === 'matching' && !empty($row['options'])) {
            $pairs = array_column($row['options'], 'match_pair');
            shuffle($pairs);
            foreach ($row['options'] as $i => &$opt) { $opt['shuffled_pair'] = $pairs[$i]; }
            unset($opt);
        }
        $questions[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

$is_proctored  = in_array($assessment['type'], ['cat', 'exam']);
$total_marks   = array_sum(array_column($questions, 'marks'));
$q_count       = count($questions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($assessment['title']) ?> — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg:      #0d1117;
    --surf:    #161b22;
    --surf2:   #1c2128;
    --surf3:   #22272e;
    --border:  #30363d;
    --accent:  #58a6ff;
    --green:   #3fb950;
    --amber:   #d29922;
    --red:     #f85149;
    --purple:  #bc8cff;
    --text:    #e6edf3;
    --muted:   #8b949e;
    --dim:     #3d444d;
    --r:       10px;
    --rs:      6px;
    --tr:      .15s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;user-select:none;-webkit-user-select:none}

/* ── EXAM LOCKBAR ── */
.lockbar{background:#0a0d12;border-bottom:2px solid var(--red);padding:0 24px;height:52px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200}
.lockbar-left{display:flex;align-items:center;gap:14px}
.exam-title{font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;color:var(--text)}
.exam-type-badge{font-size:.68rem;padding:2px 9px;border-radius:999px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.badge-quiz{background:rgba(88,166,255,.12);color:var(--accent);border:1px solid rgba(88,166,255,.25)}
.badge-assignment{background:rgba(63,185,80,.12);color:var(--green);border:1px solid rgba(63,185,80,.25)}
.badge-cat{background:rgba(210,153,34,.12);color:var(--amber);border:1px solid rgba(210,153,34,.25)}
.badge-exam{background:rgba(248,81,73,.12);color:var(--red);border:1px solid rgba(248,81,73,.25)}
.lockbar-right{display:flex;align-items:center;gap:16px}

/* TIMER */
.timer-wrap{display:flex;align-items:center;gap:8px}
.timer-icon{color:var(--amber);font-size:.9rem}
#timer-display{font-family:'JetBrains Mono',monospace;font-size:1.05rem;font-weight:500;color:var(--text);min-width:60px}
#timer-display.warning{color:var(--amber);animation:pulse 1s infinite}
#timer-display.critical{color:var(--red);animation:pulse .5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* Security indicators */
.sec-indicators{display:flex;gap:8px}
.sec-dot{width:8px;height:8px;border-radius:50%;transition:var(--tr)}
.sec-dot.ok{background:var(--green)}
.sec-dot.warn{background:var(--red);animation:pulse .8s infinite}
.sec-label{font-size:.72rem;color:var(--muted)}

/* VIOLATION BANNER */
#violation-banner{
    background:rgba(248,81,73,.15);border-bottom:1px solid rgba(248,81,73,.4);
    padding:10px 24px;display:none;align-items:center;justify-content:space-between;gap:12px;
    font-size:.85rem;color:var(--red);position:sticky;top:52px;z-index:190;
}
#violation-banner i{font-size:1rem}
#violation-banner button{background:rgba(248,81,73,.2);border:1px solid rgba(248,81,73,.4);color:var(--red);padding:4px 12px;border-radius:var(--rs);cursor:pointer;font-family:'DM Sans',sans-serif;font-size:.78rem}

/* LAYOUT */
.exam-layout{display:flex;height:calc(100vh - 52px);overflow:hidden}

/* QUESTION NAV */
.q-nav{width:200px;min-width:200px;background:var(--surf);border-right:1px solid var(--border);padding:16px 12px;overflow-y:auto;display:flex;flex-direction:column;gap:8px}
.q-nav-title{font-family:'Syne',sans-serif;font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);margin-bottom:4px}
.q-nav-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:5px}
.q-nav-btn{
    aspect-ratio:1;border-radius:var(--rs);font-family:'JetBrains Mono',monospace;font-size:.75rem;
    font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surf2);
    color:var(--muted);transition:var(--tr);display:flex;align-items:center;justify-content:center;
}
.q-nav-btn.current {border-color:var(--accent);background:rgba(88,166,255,.12);color:var(--accent)}
.q-nav-btn.answered{border-color:var(--green);background:rgba(63,185,80,.1);color:var(--green)}
.q-nav-btn.flagged {border-color:var(--amber);background:rgba(210,153,34,.1);color:var(--amber)}

.nav-legend{margin-top:auto;display:flex;flex-direction:column;gap:5px;padding-top:12px;border-top:1px solid var(--border)}
.legend-row{display:flex;align-items:center;gap:6px;font-size:.72rem;color:var(--muted)}
.legend-dot{width:8px;height:8px;border-radius:2px;flex-shrink:0}
.legend-dot.answered{background:var(--green)}
.legend-dot.current{background:var(--accent)}
.legend-dot.flagged{background:var(--amber)}
.legend-dot.unanswered{background:var(--dim)}

/* QUESTION PANEL */
.q-panel{flex:1;overflow-y:auto;padding:28px 32px;display:flex;flex-direction:column;gap:20px}
.q-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.q-card-header{background:var(--surf2);padding:12px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)}
.q-num-badge{font-family:'JetBrains Mono',monospace;font-size:.7rem;background:rgba(88,166,255,.1);color:var(--accent);border:1px solid rgba(88,166,255,.2);padding:2px 9px;border-radius:999px}
.q-marks-badge{font-size:.7rem;color:var(--muted);margin-left:auto}
.flag-btn{background:none;border:1px solid var(--border);padding:3px 9px;border-radius:var(--rs);cursor:pointer;font-size:.75rem;color:var(--muted);transition:var(--tr);font-family:'DM Sans',sans-serif}
.flag-btn:hover,.flag-btn.flagged{border-color:var(--amber);color:var(--amber);background:rgba(210,153,34,.08)}
.q-body{padding:18px 20px;display:flex;flex-direction:column;gap:14px}
.q-text{font-size:.95rem;line-height:1.65;color:var(--text)}

/* MCQ OPTIONS */
.opt-list{display:flex;flex-direction:column;gap:8px}
.opt-item{
    display:flex;align-items:center;gap:12px;
    padding:11px 16px;border:1px solid var(--border);border-radius:var(--rs);
    background:var(--surf2);cursor:pointer;transition:var(--tr);
}
.opt-item:hover{border-color:var(--muted);background:var(--surf3)}
.opt-item.selected{border-color:var(--accent);background:rgba(88,166,255,.08)}
.opt-radio{width:16px;height:16px;accent-color:var(--accent);flex-shrink:0;cursor:pointer}
.opt-label{font-size:.88rem;color:var(--text);flex:1;cursor:pointer}
.opt-letter{font-family:'JetBrains Mono',monospace;font-size:.72rem;color:var(--dim);width:18px;text-align:center}

/* TRUE/FALSE */
.tf-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.tf-btn{
    padding:16px;border:1px solid var(--border);border-radius:var(--rs);
    background:var(--surf2);cursor:pointer;text-align:center;transition:var(--tr);
}
.tf-btn:hover{border-color:var(--muted)}
.tf-btn.selected-true{border-color:var(--green);background:rgba(63,185,80,.1);color:var(--green)}
.tf-btn.selected-false{border-color:var(--red);background:rgba(248,81,73,.1);color:var(--red)}
.tf-btn i{font-size:1.4rem;display:block;margin-bottom:6px}
.tf-btn span{font-family:'Syne',sans-serif;font-size:.85rem;font-weight:700}

/* MATCHING */
.match-table{width:100%;border-collapse:collapse}
.match-table th{font-family:'Syne',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--dim);padding:6px 12px;border-bottom:1px solid var(--border)}
.match-table td{padding:8px 12px;border-bottom:1px solid var(--dim)}
.match-term{font-size:.87rem;color:var(--text)}
.match-select{width:100%;background:var(--surf3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.84rem;outline:none;cursor:pointer}
.match-select:focus{border-color:var(--accent)}

/* SHORT ANSWER */
.short-input{width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.88rem;outline:none;transition:border-color var(--tr)}
.short-input:focus{border-color:var(--accent)}

/* ESSAY */
.essay-area{width:100%;min-height:160px;background:var(--surf2);border:1px solid var(--border);color:var(--text);padding:12px 14px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.88rem;line-height:1.6;outline:none;resize:vertical;transition:border-color var(--tr)}
.essay-area:focus{border-color:var(--accent)}
.word-count{font-size:.73rem;color:var(--dim);text-align:right;margin-top:4px}

/* FILE UPLOAD */
.file-upload-zone{border:2px dashed var(--border);border-radius:var(--rs);padding:24px;text-align:center;cursor:pointer;transition:var(--tr);color:var(--muted)}
.file-upload-zone:hover{border-color:var(--accent);color:var(--accent);background:rgba(88,166,255,.04)}
.file-upload-zone input{display:none}
.file-name{font-size:.8rem;color:var(--green);margin-top:8px}

/* BOTTOM NAV */
.q-bottom-nav{display:flex;align-items:center;gap:10px;padding-top:4px}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.84rem;font-weight:500;cursor:pointer;border:none;transition:var(--tr)}
.btn-primary{background:var(--accent);color:#0d1117}.btn-primary:hover{background:#4a97f0;transform:translateY(-1px)}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-success{background:var(--green);color:#050e07}.btn-success:hover{background:#35a844}
.btn-danger{background:var(--red);color:#fff}.btn-danger:hover{background:#e04040}
.btn-sm{padding:6px 13px;font-size:.78rem}
.btn:disabled{opacity:.4;cursor:not-allowed;transform:none!important}

/* RESULTS SCREEN */
.results-wrap{max-width:600px;margin:60px auto;padding:0 24px;text-align:center}
.score-ring{width:140px;height:140px;border-radius:50%;border:6px solid;display:flex;flex-direction:column;align-items:center;justify-content:center;margin:0 auto 24px}
.score-ring.pass{border-color:var(--green)}
.score-ring.fail{border-color:var(--red)}
.score-pct{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800}
.score-ring.pass .score-pct{color:var(--green)}
.score-ring.fail .score-pct{color:var(--red)}
.score-sub{font-size:.8rem;color:var(--muted)}

/* COVER SCREEN (pre-exam) */
.cover-wrap{max-width:580px;margin:60px auto;padding:0 24px}
.cover-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:32px}
.cover-card h1{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;margin-bottom:8px}
.cover-meta{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0;padding:12px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.cover-meta-item{display:flex;align-items:center;gap:6px;font-size:.83rem;color:var(--muted)}
.cover-meta-item i{color:var(--accent)}
.cover-rules{background:var(--surf2);border-radius:var(--rs);padding:14px 16px;margin:16px 0;font-size:.83rem;line-height:1.7;color:var(--muted)}
.cover-rules strong{color:var(--red);display:block;margin-bottom:6px}

/* ALREADY SUBMITTED */
.submitted-card{max-width:500px;margin:80px auto;padding:0 24px;text-align:center}

/* OVERLAY MODALS */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);backdrop-filter:blur(6px);z-index:500;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal-box{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:28px 32px;width:460px;max-width:92vw;text-align:center}
.modal-box h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:12px}
.modal-box p{font-size:.86rem;color:var(--muted);line-height:1.6;margin-bottom:20px}
.modal-actions{display:flex;gap:10px;justify-content:center}

/* FULLSCREEN nudge */
#fs-nudge{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:rgba(210,153,34,.15);border:1px solid rgba(210,153,34,.4);color:var(--amber);padding:10px 20px;border-radius:999px;font-size:.82rem;z-index:400;display:none;gap:10px;align-items:center}
#fs-nudge button{background:rgba(210,153,34,.2);border:1px solid rgba(210,153,34,.4);color:var(--amber);padding:3px 10px;border-radius:var(--rs);cursor:pointer;font-size:.75rem;font-family:'DM Sans',sans-serif}

::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--surf3);border-radius:2px}
</style>
</head>
<body>

<?php if ($existing_submission && $existing_submission['status'] !== 'flagged'): ?>
<!-- ── ALREADY SUBMITTED ── -->
<div style="padding:20px">
    <div class="submitted-card">
        <i class="fas fa-circle-check" style="font-size:3rem;color:var(--green);margin-bottom:16px;display:block"></i>
        <h2 style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;margin-bottom:8px">Already Submitted</h2>
        <p style="color:var(--muted);font-size:.88rem;margin-bottom:20px">
            You submitted this <?= $assessment['type'] ?> on
            <?= date('d M Y, H:i', strtotime($existing_submission['submitted_at'])) ?>.
        </p>
        <?php if ($existing_submission['score'] !== null): ?>
        <div class="score-ring <?= ($existing_submission['score'] >= $assessment['pass_mark']) ? 'pass' : 'fail' ?>" style="margin-bottom:16px">
            <span class="score-pct"><?= round($existing_submission['score']) ?>%</span>
            <span class="score-sub"><?= $existing_submission['score'] >= $assessment['pass_mark'] ? 'PASS' : 'FAIL' ?></span>
        </div>
        <?php else: ?>
        <p style="color:var(--amber);font-size:.85rem"><i class="fas fa-hourglass-half"></i> Awaiting grading</p>
        <?php endif; ?>
        <a href="course_view.php?unit_id=<?= $assessment['unit_id'] ?>" class="btn btn-ghost" style="margin-top:12px">
            <i class="fas fa-arrow-left"></i> Back to Course
        </a>
    </div>
</div>

<?php elseif (!isset($_GET['start'])): ?>
<!-- ── COVER / PRE-EXAM SCREEN ── -->
<div style="padding:20px">
<div class="cover-wrap">
    <div class="cover-card">
        <div style="margin-bottom:12px">
            <span class="exam-type-badge badge-<?= $assessment['type'] ?>"><?= strtoupper($assessment['type']) ?></span>
        </div>
        <h1><?= htmlspecialchars($assessment['title']) ?></h1>
        <p style="color:var(--muted);font-size:.87rem"><?= htmlspecialchars($assessment['unit_name']) ?></p>

        <div class="cover-meta">
            <div class="cover-meta-item"><i class="fas fa-circle-question"></i> <?= $q_count ?> Questions</div>
            <div class="cover-meta-item"><i class="fas fa-star"></i> <?= $total_marks ?> Marks</div>
            <div class="cover-meta-item"><i class="fas fa-check-circle"></i> Pass: <?= $assessment['pass_mark'] ?></div>
            <?php if ($assessment['time_limit_mins']): ?>
            <div class="cover-meta-item"><i class="fas fa-clock"></i> <?= $assessment['time_limit_mins'] ?> minutes</div>
            <?php else: ?>
            <div class="cover-meta-item"><i class="fas fa-infinity"></i> No time limit</div>
            <?php endif; ?>
        </div>

        <?php if ($assessment['instructions']): ?>
        <div style="background:rgba(88,166,255,.06);border:1px solid rgba(88,166,255,.2);border-radius:var(--rs);padding:12px 16px;margin-bottom:16px;font-size:.86rem;color:var(--muted);line-height:1.6">
            <strong style="color:var(--text)"><i class="fas fa-circle-info"></i> Instructions:</strong><br>
            <?= nl2br(htmlspecialchars($assessment['instructions'])) ?>
        </div>
        <?php endif; ?>

        <?php if ($is_proctored): ?>
        <div class="cover-rules">
            <strong><i class="fas fa-shield-halved"></i> &nbsp;Exam Security — Important</strong>
            This is a proctored <?= strtoupper($assessment['type']) ?>. The following restrictions apply:<br>
            • Switching tabs or windows will be detected and logged<br>
            • Copy, paste and screen capture are disabled<br>
            • Right-click is disabled during the exam<br>
            • Keyboard shortcuts (Ctrl+C, Ctrl+V, Ctrl+T, F12, etc.) are blocked<br>
            • The exam will request fullscreen mode<br>
            • All violations are logged and reported to your lecturer<br>
            • Multiple violations may result in your submission being flagged
        </div>
        <?php endif; ?>

        <a href="take_assessment.php?assessment_id=<?= $assessment_id ?>&start=1"
           class="btn btn-primary" style="width:100%;justify-content:center;padding:13px">
            <i class="fas fa-play-circle"></i>
            <?= $is_proctored ? 'Start Proctored ' . strtoupper($assessment['type']) : 'Start ' . ucfirst($assessment['type']) ?>
        </a>
    </div>
</div>
</div>

<?php else: ?>
<!-- ── EXAM INTERFACE ── -->

<!-- LOCKBAR -->
<div class="lockbar">
    <div class="lockbar-left">
        <span class="exam-title"><?= htmlspecialchars($assessment['title']) ?></span>
        <span class="exam-type-badge badge-<?= $assessment['type'] ?>"><?= strtoupper($assessment['type']) ?></span>
        <?php if ($is_proctored): ?>
        <div class="sec-indicators" title="Security status">
            <div class="sec-dot ok" id="tab-dot"   title="Tab monitoring"></div>
            <div class="sec-dot ok" id="focus-dot" title="Focus monitoring"></div>
            <div class="sec-dot ok" id="full-dot"  title="Fullscreen"></div>
        </div>
        <?php endif; ?>
    </div>
    <div class="lockbar-right">
        <?php if ($assessment['time_limit_mins']): ?>
        <div class="timer-wrap">
            <i class="fas fa-clock timer-icon"></i>
            <span id="timer-display">--:--</span>
        </div>
        <?php endif; ?>
        <span style="font-size:.78rem;color:var(--muted)" id="progress-label">0 / <?= $q_count ?> answered</span>
        <button class="btn btn-danger btn-sm" onclick="confirmSubmit()">
            <i class="fas fa-paper-plane"></i> Submit
        </button>
    </div>
</div>

<!-- VIOLATION BANNER -->
<div id="violation-banner">
    <div><i class="fas fa-triangle-exclamation"></i> &nbsp;<span id="violation-msg">Security violation detected.</span></div>
    <button onclick="dismissBanner()">Dismiss</button>
</div>

<!-- EXAM LAYOUT -->
<div class="exam-layout">

    <!-- Question Navigator -->
    <aside class="q-nav">
        <div class="q-nav-title">Questions</div>
        <div class="q-nav-grid" id="q-nav-grid">
            <?php foreach ($questions as $i => $q): ?>
            <button class="q-nav-btn <?= $i === 0 ? 'current' : '' ?>"
                    id="nav-<?= $i ?>"
                    onclick="scrollToQ(<?= $i ?>)"><?= $i + 1 ?></button>
            <?php endforeach; ?>
        </div>

        <div class="nav-legend">
            <div class="legend-row"><div class="legend-dot current"></div> Current</div>
            <div class="legend-row"><div class="legend-dot answered"></div> Answered</div>
            <div class="legend-row"><div class="legend-dot flagged"></div> Flagged</div>
            <div class="legend-row"><div class="legend-dot unanswered"></div> Not answered</div>
        </div>
    </aside>

    <!-- Questions -->
    <div class="q-panel" id="q-panel">

        <?php foreach ($questions as $i => $q): ?>
        <div class="q-card" id="qcard-<?= $i ?>" data-index="<?= $i ?>">
            <div class="q-card-header">
                <span class="q-num-badge">Q<?= $i + 1 ?></span>
                <span style="font-size:.78rem;color:var(--muted);text-transform:capitalize"><?= str_replace('_',' ', $q['question_type']) ?></span>
                <button class="flag-btn" id="flag-<?= $i ?>" onclick="toggleFlag(<?= $i ?>)">
                    <i class="fas fa-flag"></i> Flag
                </button>
                <span class="q-marks-badge"><?= $q['marks'] ?> mark<?= $q['marks'] != 1 ? 's' : '' ?></span>
            </div>
            <div class="q-body">
                <div class="q-text"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>

                <?php if ($q['question_type'] === 'mcq'): ?>
                <div class="opt-list" id="opts-<?= $i ?>">
                    <?php $letters = ['A','B','C','D','E','F','G','H']; ?>
                    <?php foreach ($q['options'] as $oi => $opt): ?>
                    <div class="opt-item" id="opt-<?= $i ?>-<?= $oi ?>"
                         onclick="selectMCQ(<?= $i ?>, <?= $opt['id'] ?>, this)">
                        <span class="opt-letter"><?= $letters[$oi] ?? '' ?></span>
                        <span class="opt-label"><?= htmlspecialchars($opt['option_text']) ?></span>
                        <input type="radio" class="opt-radio" name="q<?= $i ?>" value="<?= $opt['id'] ?>" style="display:none">
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php elseif ($q['question_type'] === 'true_false'): ?>
                <div class="tf-grid" id="opts-<?= $i ?>">
                    <?php foreach ($q['options'] as $opt): ?>
                    <?php $isTrueOpt = (strtolower($opt['option_text']) === 'true'); ?>
                    <div class="tf-btn" id="tf-<?= $i ?>-<?= $opt['id'] ?>"
                         onclick="selectTF(<?= $i ?>, <?= $opt['id'] ?>, <?= $isTrueOpt ? 'true' : 'false' ?>, this)">
                        <i class="fas <?= $isTrueOpt ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                        <span><?= htmlspecialchars($opt['option_text']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php elseif ($q['question_type'] === 'matching'): ?>
                <table class="match-table" id="opts-<?= $i ?>">
                    <thead>
                        <tr>
                            <th style="text-align:left">Term</th>
                            <th style="text-align:left">Match</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $allPairs = array_column($q['options'], 'match_pair');
                        shuffle($allPairs);
                        ?>
                        <?php foreach ($q['options'] as $oi => $opt): ?>
                        <tr>
                            <td class="match-term"><?= htmlspecialchars($opt['option_text']) ?></td>
                            <td>
                                <select class="match-select"
                                        data-opt-id="<?= $opt['id'] ?>"
                                        onchange="selectMatch(<?= $i ?>, <?= $opt['id'] ?>, this.value)">
                                    <option value="">— select —</option>
                                    <?php foreach ($allPairs as $pair): ?>
                                    <option value="<?= htmlspecialchars($pair) ?>"><?= htmlspecialchars($pair) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php elseif ($q['question_type'] === 'short_answer'): ?>
                <input type="text" class="short-input"
                       id="ans-<?= $i ?>"
                       placeholder="Your answer..."
                       oninput="saveTextAnswer(<?= $i ?>, this.value)">

                <?php elseif ($q['question_type'] === 'essay'): ?>
                <textarea class="essay-area"
                          id="ans-<?= $i ?>"
                          placeholder="Write your answer here..."
                          oninput="saveTextAnswer(<?= $i ?>, this.value); updateWordCount(<?= $i ?>)"></textarea>
                <div class="word-count" id="wc-<?= $i ?>">0 words</div>

                <?php elseif ($q['question_type'] === 'file_upload'): ?>
                <div class="file-upload-zone" onclick="document.getElementById('file-<?= $i ?>').click()">
                    <input type="file" id="file-<?= $i ?>"
                           onchange="handleFileUpload(<?= $i ?>, <?= $q['id'] ?>, this)">
                    <i class="fas fa-file-arrow-up" style="font-size:1.6rem;margin-bottom:8px;display:block;opacity:.5"></i>
                    <div>Click to upload your file</div>
                    <small style="color:var(--dim)">PDF, DOCX, ZIP — max 20MB</small>
                    <div class="file-name" id="fname-<?= $i ?>"></div>
                </div>
                <?php endif; ?>

            </div><!-- /.q-body -->
        </div><!-- /.q-card -->
        <?php endforeach; ?>

        <!-- Bottom spacer + submit -->
        <div class="q-bottom-nav">
            <button class="btn btn-ghost" onclick="scrollToQ(0)"><i class="fas fa-arrow-up"></i> Top</button>
            <div style="flex:1"></div>
            <button class="btn btn-success" onclick="confirmSubmit()">
                <i class="fas fa-paper-plane"></i> Submit <?= ucfirst($assessment['type']) ?>
            </button>
        </div>

    </div><!-- /.q-panel -->
</div><!-- /.exam-layout -->

<!-- Fullscreen nudge -->
<div id="fs-nudge">
    <i class="fas fa-expand"></i> Fullscreen recommended for this <?= $assessment['type'] ?>
    <button onclick="enterFullscreen()">Enter Fullscreen</button>
    <button onclick="document.getElementById('fs-nudge').style.display='none'" style="opacity:.6">Dismiss</button>
</div>

<!-- Submit confirm modal -->
<div class="modal-overlay" id="submit-modal">
    <div class="modal-box">
        <h3><i class="fas fa-paper-plane" style="color:var(--accent)"></i> &nbsp;Submit <?= ucfirst($assessment['type']) ?>?</h3>
        <p id="submit-modal-msg">You are about to submit. <span id="unanswered-warn" style="color:var(--amber)"></span> This cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('submit-modal')">Go Back</button>
            <button class="btn btn-success" id="confirm-submit-btn" onclick="submitExam()">
                <i class="fas fa-check"></i> Yes, Submit
            </button>
        </div>
    </div>
</div>

<!-- Violation modal (severe) -->
<div class="modal-overlay" id="violation-modal">
    <div class="modal-box">
        <i class="fas fa-triangle-exclamation" style="font-size:2rem;color:var(--red);margin-bottom:12px;display:block"></i>
        <h3 style="color:var(--red)">Security Violation Detected</h3>
        <p id="violation-modal-msg">A security violation has been recorded.</p>
        <div class="modal-actions">
            <button class="btn btn-primary" onclick="closeModal('violation-modal');enterFullscreen()">Return to Exam</button>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ──────────────────────────────────────────────────────────
     JAVASCRIPT — EXAM ENGINE + PROCTORING
────────────────────────────────────────────────────────────── -->
<script>
// ── CONFIG ───────────────────────────────────────────────
const ASSESSMENT_ID  = <?= $assessment_id ?>;
const STUDENT_ID     = <?= $student_id ?>;
const IS_PROCTORED   = <?= $is_proctored ? 'true' : 'false' ?>;
const TIME_LIMIT_SEC = <?= ($assessment['time_limit_mins'] ?? 0) * 60 ?>;
const PASS_MARK      = <?= $assessment['pass_mark'] ?? 0 ?>;
const Q_COUNT        = <?= $q_count ?>;
const STARTED        = <?= isset($_GET['start']) ? 'true' : 'false' ?>;

// ── STATE ─────────────────────────────────────────────────
let answers        = {};   // { qIndex: { type, value, optionId, matchAnswers:{optId:pair} } }
let flags          = {};   // { qIndex: bool }
let violations     = [];
let violationCount = 0;
let timerInterval  = null;
let timeLeft       = TIME_LIMIT_SEC;
let examStartTime  = Date.now();
let submitting     = false;

// ── INIT ─────────────────────────────────────────────────
if (STARTED) {
    initExam();
}

function initExam() {
    if (IS_PROCTORED) {
        initProctoring();
        setTimeout(() => {
            enterFullscreen();
            document.getElementById('fs-nudge').style.display = 'flex';
            setTimeout(() => document.getElementById('fs-nudge').style.display = 'none', 8000);
        }, 800);
    }
    if (TIME_LIMIT_SEC > 0) initTimer();
    initScrollSpy();
}

// ── TIMER ─────────────────────────────────────────────────
function initTimer() {
    timeLeft = TIME_LIMIT_SEC;
    updateTimerDisplay();
    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            toast('Time is up! Auto-submitting...', 'error');
            setTimeout(submitExam, 2000);
        }
    }, 1000);
}

function updateTimerDisplay() {
    const el = document.getElementById('timer-display');
    if (!el) return;
    const m = Math.floor(timeLeft / 60);
    const s = timeLeft % 60;
    el.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    if (timeLeft <= 60)  { el.className = 'critical'; }
    else if (timeLeft <= 300) { el.className = 'warning'; }
    else { el.className = ''; }
}

// ── SCROLL SPY ────────────────────────────────────────────
function initScrollSpy() {
    const panel = document.getElementById('q-panel');
    if (!panel) return;
    const observer = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                const idx = parseInt(e.target.dataset.index);
                setCurrentNav(idx);
            }
        });
    }, { root: panel, threshold: 0.5 });
    document.querySelectorAll('.q-card').forEach(card => observer.observe(card));
}

function scrollToQ(idx) {
    const card = document.getElementById(`qcard-${idx}`);
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function setCurrentNav(idx) {
    document.querySelectorAll('.q-nav-btn').forEach((btn, i) => {
        btn.classList.toggle('current', i === idx);
    });
}

// ── ANSWER RECORDING ──────────────────────────────────────
function selectMCQ(qIdx, optionId, el) {
    // Clear siblings
    el.closest('.opt-list').querySelectorAll('.opt-item').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;

    answers[qIdx] = { type: 'mcq', optionId };
    markAnswered(qIdx);
}

function selectTF(qIdx, optionId, isTrue, el) {
    const container = el.closest('.tf-grid');
    container.querySelectorAll('.tf-btn').forEach(b => b.className = 'tf-btn');
    el.classList.add(isTrue ? 'selected-true' : 'selected-false');

    answers[qIdx] = { type: 'true_false', optionId };
    markAnswered(qIdx);
}

function selectMatch(qIdx, optId, value) {
    if (!answers[qIdx]) answers[qIdx] = { type: 'matching', matchAnswers: {} };
    answers[qIdx].matchAnswers[optId] = value;

    // Check if all matched
    const card = document.getElementById(`qcard-${qIdx}`);
    const selects = card.querySelectorAll('.match-select');
    const allFilled = [...selects].every(s => s.value !== '');
    if (allFilled) markAnswered(qIdx);
}

function saveTextAnswer(qIdx, value) {
    answers[qIdx] = { type: 'text', value: value.trim() };
    const nav = document.getElementById(`nav-${qIdx}`);
    if (nav) nav.classList.toggle('answered', value.trim().length > 0);
    updateProgress();
}

function handleFileUpload(qIdx, questionId, input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById(`fname-${qIdx}`).textContent = `Selected: ${file.name}`;

    const fd = new FormData();
    fd.append('file',          file);
    fd.append('question_id',   questionId);
    fd.append('assessment_id', ASSESSMENT_ID);
    fd.append('student_id',    STUDENT_ID);

    fetch('../student/ajax/upload_answer_file.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                answers[qIdx] = { type: 'file', filePath: d.path };
                markAnswered(qIdx);
                toast('File uploaded', 'success');
            } else toast(d.message || 'Upload failed', 'error');
        })
        .catch(() => toast('Upload error', 'error'));
}

function markAnswered(qIdx) {
    const nav = document.getElementById(`nav-${qIdx}`);
    if (nav && !flags[qIdx]) {
        nav.classList.add('answered');
        nav.classList.remove('current');
    }
    updateProgress();
}

function toggleFlag(qIdx) {
    flags[qIdx] = !flags[qIdx];
    const btn = document.getElementById(`flag-${qIdx}`);
    const nav = document.getElementById(`nav-${qIdx}`);
    btn?.classList.toggle('flagged', flags[qIdx]);
    nav?.classList.toggle('flagged', flags[qIdx]);
    if (flags[qIdx]) nav?.classList.remove('answered');
    else if (answers[qIdx]) nav?.classList.add('answered');
}

function updateProgress() {
    const answered = Object.keys(answers).filter(k => {
        const a = answers[k];
        if (!a) return false;
        if (a.type === 'text')     return (a.value || '').length > 0;
        if (a.type === 'matching') return Object.keys(a.matchAnswers || {}).length > 0;
        return a.optionId || a.filePath;
    }).length;

    const el = document.getElementById('progress-label');
    if (el) el.textContent = `${answered} / ${Q_COUNT} answered`;
}

function updateWordCount(qIdx) {
    const ta = document.getElementById(`ans-${qIdx}`);
    const el = document.getElementById(`wc-${qIdx}`);
    if (ta && el) {
        const words = ta.value.trim().split(/\s+/).filter(w => w.length > 0).length;
        el.textContent = `${words} word${words !== 1 ? 's' : ''}`;
    }
}

// ── SUBMIT ────────────────────────────────────────────────
function confirmSubmit() {
    const answered = Object.keys(answers).length;
    const unanswered = Q_COUNT - answered;
    const warnEl = document.getElementById('unanswered-warn');
    if (unanswered > 0 && warnEl) {
        warnEl.textContent = `⚠ ${unanswered} question${unanswered > 1 ? 's' : ''} unanswered. `;
    }
    openModal('submit-modal');
}

function submitExam() {
    if (submitting) return;
    submitting = true;
    clearInterval(timerInterval);

    const btn = document.getElementById('confirm-submit-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Submitting...'; }

    const payload = {
        assessment_id: ASSESSMENT_ID,
        student_id:    STUDENT_ID,
        answers:       answers,
        violations:    violations,
        time_taken:    Math.floor((Date.now() - examStartTime) / 1000)
    };

    fetch('../student/ajax/submit_assessment.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            exitFullscreen();
            // Show results
            showResults(d);
        } else {
            toast(d.message || 'Submission failed', 'error');
            submitting = false;
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Yes, Submit'; }
        }
    })
    .catch(() => {
        toast('Network error — please try again', 'error');
        submitting = false;
    });
}

function showResults(data) {
    closeModal('submit-modal');
    document.querySelector('.exam-layout').innerHTML = `
        <div class="results-wrap" style="width:100%">
            <div class="score-ring ${data.passed ? 'pass' : 'fail'}">
                <span class="score-pct">${data.score !== null ? Math.round(data.score) + '%' : '?'}</span>
                <span class="score-sub">${data.score !== null ? (data.passed ? 'PASS' : 'FAIL') : 'PENDING'}</span>
            </div>
            <h2 style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;margin-bottom:8px">
                ${data.score !== null ? 'Submitted & Auto-Graded' : 'Submitted Successfully'}
            </h2>
            <p style="color:var(--muted);font-size:.88rem;margin-bottom:24px">
                ${data.score !== null
                    ? `You scored ${data.raw_score} / ${data.total_marks} marks.`
                    : 'Your submission has been received. Manual grading is pending.'}
            </p>
            ${violations.length > 0 ? `<p style="color:var(--amber);font-size:.82rem;margin-bottom:16px"><i class="fas fa-triangle-exclamation"></i> ${violations.length} security event${violations.length > 1 ? 's' : ''} were logged.</p>` : ''}
            <a href="course_view.php?unit_id=${data.unit_id || ''}" class="btn btn-ghost">
                <i class="fas fa-arrow-left"></i> Back to Course
            </a>
        </div>`;
    document.querySelector('.lockbar').style.display = 'none';
    document.getElementById('violation-banner').style.display = 'none';
}

// ── PROCTORING ENGINE ─────────────────────────────────────
function initProctoring() {
    // 1. Visibility / tab switch
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            logViolation('tab_switch', 'Student switched tabs or minimised window');
        }
    });

    // 2. Window blur (Alt+Tab, click outside)
    window.addEventListener('blur', () => {
        logViolation('window_blur', 'Exam window lost focus');
        updateSecDot('focus-dot', false);
    });
    window.addEventListener('focus', () => {
        updateSecDot('focus-dot', true);
    });

    // 3. Disable right-click
    document.addEventListener('contextmenu', e => {
        e.preventDefault();
        logViolation('right_click', 'Right-click attempted');
        showBanner('Right-click is disabled during this exam.');
    });

    // 4. Block keyboard shortcuts
    document.addEventListener('keydown', e => {
        const blocked = [
            e.ctrlKey && ['c','v','x','a','u','s','p'].includes(e.key.toLowerCase()),
            e.ctrlKey && e.shiftKey,
            e.ctrlKey && e.key === 'Tab',
            e.altKey  && e.key === 'Tab',
            e.key === 'F12',
            e.key === 'PrintScreen',
            e.ctrlKey && e.key === 't',
            e.ctrlKey && e.key === 'w',
            e.metaKey,
        ];
        if (blocked.some(Boolean)) {
            e.preventDefault();
            e.stopPropagation();
            logViolation('shortcut_blocked', `Blocked key: ${e.ctrlKey?'Ctrl+':''}${e.altKey?'Alt+':''}${e.key}`);
            showBanner('Keyboard shortcut blocked.');
            return false;
        }
    }, true);

    // 5. Block copy/paste/cut on document
    ['copy','paste','cut','selectstart'].forEach(evt => {
        document.addEventListener(evt, e => {
            e.preventDefault();
            logViolation(`${evt}_attempt`, `${evt} blocked`);
        });
    });

    // 6. Fullscreen change detection
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
            updateSecDot('full-dot', false);
            logViolation('fullscreen_exit', 'Exited fullscreen');
            showViolationModal('You exited fullscreen mode. This has been recorded.');
        } else {
            updateSecDot('full-dot', true);
        }
    });

    // 7. DevTools detection (size heuristic)
    setInterval(() => {
        const threshold = 160;
        if ((window.outerWidth - window.innerWidth > threshold) ||
            (window.outerHeight - window.innerHeight > threshold)) {
            logViolation('devtools_open', 'DevTools may be open');
        }
    }, 3000);
}

function logViolation(type, details) {
    violationCount++;
    const v = { type, details, ts: new Date().toISOString() };
    violations.push(v);

    // Send to server immediately (fire and forget)
    const fd = new FormData();
    fd.append('assessment_id',   ASSESSMENT_ID);
    fd.append('student_id',      STUDENT_ID);
    fd.append('violation_type',  type);
    fd.append('details',         details);
    // submission_id not known yet — server will create/find the pending submission
    fetch('../student/ajax/log_violation.php', { method: 'POST', body: fd }).catch(() => {});

    // Auto-submit if too many violations (CAT/Exam only)
    if (IS_PROCTORED && violationCount >= 10 && !submitting) {
        toast('Too many violations — auto-submitting', 'error');
        setTimeout(submitExam, 2500);
    }
}

function updateSecDot(id, ok) {
    const dot = document.getElementById(id);
    if (dot) { dot.className = `sec-dot ${ok ? 'ok' : 'warn'}`; }
}

function showBanner(msg) {
    const banner = document.getElementById('violation-banner');
    const msgEl  = document.getElementById('violation-msg');
    if (msgEl) msgEl.textContent = msg;
    banner.style.display = 'flex';
    clearTimeout(banner._timeout);
    banner._timeout = setTimeout(() => banner.style.display = 'none', 4000);
}

function dismissBanner() {
    document.getElementById('violation-banner').style.display = 'none';
}

function showViolationModal(msg) {
    document.getElementById('violation-modal-msg').textContent = msg;
    openModal('violation-modal');
}

// ── FULLSCREEN ────────────────────────────────────────────
function enterFullscreen() {
    const el = document.documentElement;
    if (el.requestFullscreen)       el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    else if (el.mozRequestFullScreen)    el.mozRequestFullScreen();
    document.getElementById('fs-nudge').style.display = 'none';
    updateSecDot('full-dot', true);
}
function exitFullscreen() {
    if (document.exitFullscreen && document.fullscreenElement) document.exitFullscreen();
}

// ── MODAL HELPERS ─────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

// ── TOAST ─────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.style.cssText = `position:fixed;bottom:80px;right:24px;z-index:999;
        background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);
        padding:10px 16px;font-size:.82rem;color:var(--text);
        border-left:3px solid var(--${type==='error'?'red':type==='success'?'green':'accent'});
        box-shadow:0 4px 20px rgba(0,0,0,.4);animation:toastIn .2s ease;max-width:280px;
        display:flex;align-items:center;gap:8px`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

// Prevent text selection in exam
document.addEventListener('selectstart', e => {
    if (IS_PROCTORED) e.preventDefault();
});
</script>

<?php if (isset($_GET['start'])): ?>
<style>
@keyframes toastIn{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:translateX(0)}}
.spinner{width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
<?php endif; ?>

</body>
</html>
