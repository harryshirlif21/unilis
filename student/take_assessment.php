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

$existing_submission = null;
try {
    $stmt = $conn->prepare("SELECT id, score, status, submitted_at FROM assessment_submissions WHERE assessment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assessment_id, $student_id);
    $stmt->execute();
    $existing_submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

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
        if ($row['question_type'] === 'mcq' && !empty($row['options'])) shuffle($row['options']);
        if ($row['question_type'] === 'matching' && !empty($row['options'])) {
            $pairs = array_column($row['options'], 'match_pair');
            shuffle($pairs);
            foreach ($row['options'] as $i => &$opt) $opt['shuffled_pair'] = $pairs[$i];
            unset($opt);
        }
        $questions[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

$is_proctored = in_array($assessment['type'], ['cat', 'exam']);
$total_marks  = array_sum(array_column($questions, 'marks'));
$q_count      = count($questions);
$PER_PAGE     = 5;
$total_pages  = max(1, ceil($q_count / $PER_PAGE));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($assessment['title']) ?> — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── DESIGN TOKENS ─────────────────────────────────── */
:root {
    --bg:        #080b10;
    --surf:      #0f1419;
    --surf2:     #161d26;
    --surf3:     #1c2535;
    --border:    #243040;
    --border2:   #2e3d52;
    --accent:    #4f9eff;
    --accent-gl: rgba(79,158,255,.12);
    --green:     #34d399;
    --green-gl:  rgba(52,211,153,.12);
    --amber:     #fbbf24;
    --amber-gl:  rgba(251,191,36,.12);
    --red:       #f87171;
    --red-gl:    rgba(248,113,113,.12);
    --purple:    #a78bfa;
    --text:      #e2eaf5;
    --text2:     #8fa3bc;
    --dim:       #3d5066;
    --r:         14px;
    --rs:        8px;
    --tr:        .18s ease;
    --shadow:    0 4px 24px rgba(0,0,0,.45);
}
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}

/* ── LOCKBAR ─────────────────────────────────────── */
.lockbar {
    position: sticky; top: 0; z-index: 200;
    background: rgba(8,11,16,.92);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    height: 56px;
    display: flex; align-items: center;
    padding: 0 24px; gap: 16px;
}
.lockbar-brand {
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: .9rem;
    color: var(--accent); flex-shrink: 0;
}
.type-pill {
    font-size: .65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    padding: 3px 10px; border-radius: 999px;
    flex-shrink: 0;
}
.pill-quiz       { background: var(--accent-gl); color: var(--accent); border: 1px solid rgba(79,158,255,.3); }
.pill-assignment { background: var(--green-gl);  color: var(--green);  border: 1px solid rgba(52,211,153,.3); }
.pill-cat        { background: var(--amber-gl);  color: var(--amber);  border: 1px solid rgba(251,191,36,.3); }
.pill-exam       { background: var(--red-gl);    color: var(--red);    border: 1px solid rgba(248,113,113,.3); }

.lockbar-spacer { flex: 1; }

.timer-box {
    display: flex; align-items: center; gap: 8px;
    background: var(--surf2); border: 1px solid var(--border);
    border-radius: var(--rs); padding: 6px 14px;
}
.timer-box i { color: var(--amber); font-size: .85rem; }
#timer-display {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1rem; font-weight: 500;
    color: var(--text); min-width: 52px;
}
#timer-display.warning { color: var(--amber); animation: blink 1s infinite; }
#timer-display.critical { color: var(--red);   animation: blink .5s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }

.prog-badge {
    font-size: .78rem; color: var(--text2);
    background: var(--surf2); border: 1px solid var(--border);
    padding: 6px 12px; border-radius: var(--rs);
    white-space: nowrap;
}
.sec-dots { display: flex; gap: 5px; align-items: center; }
.sec-dot  { width: 7px; height: 7px; border-radius: 50%; }
.sec-dot.ok   { background: var(--green); }
.sec-dot.warn { background: var(--red); animation: blink .7s infinite; }

.btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 16px; border-radius: var(--rs);
    font-family: 'DM Sans', sans-serif; font-size: .82rem;
    font-weight: 500; cursor: pointer; border: none;
    transition: var(--tr); text-decoration: none;
    white-space: nowrap;
}
.btn-danger  { background: var(--red);   color: #fff; }
.btn-danger:hover  { filter: brightness(1.12); }
.btn-success { background: var(--green); color: #050e07; }
.btn-success:hover { filter: brightness(1.08); }
.btn-ghost   { background: var(--surf2); border: 1px solid var(--border); color: var(--text2); }
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
.btn-accent  { background: var(--accent); color: #05111f; }
.btn-accent:hover { filter: brightness(1.1); }
.btn-sm { padding: 6px 12px; font-size: .78rem; }
.btn:disabled { opacity: .4; cursor: not-allowed; }

/* ── VIOLATION BANNER ────────────────────────────── */
#vbanner {
    display: none; position: sticky; top: 56px; z-index: 190;
    background: rgba(248,113,113,.1); border-bottom: 1px solid rgba(248,113,113,.3);
    padding: 9px 24px; gap: 12px;
    align-items: center; justify-content: space-between;
    font-size: .82rem; color: var(--red);
}
#vbanner button {
    background: rgba(248,113,113,.15); border: 1px solid rgba(248,113,113,.3);
    color: var(--red); padding: 3px 10px; border-radius: var(--rs);
    cursor: pointer; font-size: .75rem; font-family: 'DM Sans', sans-serif;
}

/* ── PAGE WRAPPER ────────────────────────────────── */
.page-wrap {
    max-width: 860px;
    margin: 0 auto;
    padding: 32px 20px 100px;
}

/* ── PAGE HEADER ─────────────────────────────────── */
.page-header {
    margin-bottom: 28px;
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.page-header-left h1 {
    font-family: 'Syne', sans-serif;
    font-size: 1.3rem; font-weight: 800;
    color: var(--text); margin-bottom: 4px;
}
.page-header-left p {
    font-size: .82rem; color: var(--text2);
}
.page-info-chips {
    display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px;
}
.info-chip {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .75rem; color: var(--text2);
    background: var(--surf2); border: 1px solid var(--border);
    padding: 4px 10px; border-radius: 999px;
}
.info-chip i { color: var(--accent); font-size: .7rem; }

/* ── PAGINATION INDICATOR ───────────────────────── */
.pager-strip {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px; gap: 12px;
}
.pager-label {
    font-family: 'Syne', sans-serif;
    font-size: .75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    color: var(--text2);
}
.pager-dots {
    display: flex; gap: 6px; flex: 1;
    justify-content: center; flex-wrap: wrap;
}
.pager-dot {
    width: 28px; height: 6px; border-radius: 3px;
    background: var(--surf3); border: 1px solid var(--border);
    cursor: pointer; transition: var(--tr);
}
.pager-dot.active   { background: var(--accent); border-color: var(--accent); }
.pager-dot.done     { background: var(--green);  border-color: var(--green); }
.pager-dot.partial  { background: var(--amber);  border-color: var(--amber); }

/* ── QUESTION PAGES ─────────────────────────────── */
.q-page { display: none; flex-direction: column; gap: 20px; }
.q-page.active { display: flex; }

/* ── QUESTION CARD ──────────────────────────────── */
.q-card {
    background: var(--surf);
    border: 1px solid var(--border);
    border-radius: var(--r);
    box-shadow: var(--shadow);
    /* NO overflow:hidden — critical fix */
}

.q-card-top {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 18px;
    background: var(--surf2);
    border-bottom: 1px solid var(--border);
    border-radius: var(--r) var(--r) 0 0;
    flex-wrap: wrap; gap: 8px;
}
.q-num {
    font-family: 'JetBrains Mono', monospace;
    font-size: .7rem; font-weight: 500;
    background: var(--accent-gl); color: var(--accent);
    border: 1px solid rgba(79,158,255,.25);
    padding: 3px 10px; border-radius: 999px;
    flex-shrink: 0;
}
.q-type-label {
    font-size: .72rem; color: var(--text2);
    text-transform: capitalize;
    flex-shrink: 0;
}
.q-flag-btn {
    margin-left: auto;
    background: none; border: 1px solid var(--border);
    color: var(--text2); padding: 3px 10px;
    border-radius: var(--rs); cursor: pointer;
    font-size: .73rem; font-family: 'DM Sans', sans-serif;
    transition: var(--tr); display: flex; align-items: center; gap: 5px;
}
.q-flag-btn:hover, .q-flag-btn.flagged {
    border-color: var(--amber); color: var(--amber);
    background: var(--amber-gl);
}
.q-marks {
    font-size: .72rem; color: var(--text2);
    background: var(--surf3); border: 1px solid var(--border);
    padding: 3px 9px; border-radius: 999px;
    white-space: nowrap; flex-shrink: 0;
}

/* ── QUESTION BODY ──────────────────────────────── */
.q-body {
    padding: 20px 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.q-text {
    font-size: .97rem;
    line-height: 1.7;
    color: var(--text);
    word-break: break-word;
    white-space: pre-wrap;
}

/* ── MCQ OPTIONS ────────────────────────────────── */
.opt-list { display: flex; flex-direction: column; gap: 9px; }
.opt-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: var(--rs);
    background: var(--surf2);
    cursor: pointer;
    transition: var(--tr);
    user-select: none;
}
.opt-item:hover { border-color: var(--border2); background: var(--surf3); }
.opt-item.selected {
    border-color: var(--accent);
    background: var(--accent-gl);
}
.opt-letter {
    font-family: 'JetBrains Mono', monospace;
    font-size: .7rem; color: var(--dim);
    width: 20px; text-align: center;
    flex-shrink: 0; margin-top: 2px;
    font-weight: 500;
}
.opt-item.selected .opt-letter { color: var(--accent); }
.opt-text {
    font-size: .88rem;
    line-height: 1.55;
    color: var(--text);
    flex: 1;
    word-break: break-word;
}

/* ── TRUE / FALSE ───────────────────────────────── */
.tf-row { display: flex; gap: 12px; }
.tf-card {
    flex: 1; padding: 18px 12px;
    border: 1px solid var(--border);
    border-radius: var(--rs);
    background: var(--surf2);
    cursor: pointer; text-align: center;
    transition: var(--tr);
}
.tf-card:hover { border-color: var(--border2); }
.tf-card.sel-true  { border-color: var(--green); background: var(--green-gl); }
.tf-card.sel-false { border-color: var(--red);   background: var(--red-gl); }
.tf-card i { font-size: 1.5rem; display: block; margin-bottom: 8px; }
.tf-card.sel-true  i { color: var(--green); }
.tf-card.sel-false i { color: var(--red); }
.tf-card span { font-family: 'Syne', sans-serif; font-size: .85rem; font-weight: 700; }

/* ── MATCHING ───────────────────────────────────── */
.match-rows { display: flex; flex-direction: column; gap: 8px; }
.match-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px;
    background: var(--surf2); border: 1px solid var(--border);
    border-radius: var(--rs);
}
.match-term {
    flex: 1; font-size: .87rem; color: var(--text);
    word-break: break-word;
}
.match-arrow { color: var(--dim); font-size: .8rem; flex-shrink: 0; }
.match-sel {
    flex: 1; background: var(--surf3); border: 1px solid var(--border);
    color: var(--text); padding: 7px 10px; border-radius: var(--rs);
    font-family: 'DM Sans', sans-serif; font-size: .84rem;
    outline: none; cursor: pointer; transition: border-color var(--tr);
}
.match-sel:focus { border-color: var(--accent); }

/* ── SHORT ANSWER ───────────────────────────────── */
.short-inp {
    width: 100%;
    background: var(--surf2); border: 1px solid var(--border);
    color: var(--text); padding: 11px 14px;
    border-radius: var(--rs);
    font-family: 'DM Sans', sans-serif; font-size: .88rem;
    outline: none; transition: border-color var(--tr);
}
.short-inp:focus { border-color: var(--accent); }
.short-inp::placeholder { color: var(--dim); }

/* ── ESSAY ──────────────────────────────────────── */
.essay-wrap { display: flex; flex-direction: column; gap: 6px; }
.essay-ta {
    width: 100%; min-height: 140px;
    background: var(--surf2); border: 1px solid var(--border);
    color: var(--text); padding: 12px 14px;
    border-radius: var(--rs);
    font-family: 'DM Sans', sans-serif; font-size: .88rem;
    line-height: 1.6; outline: none; resize: vertical;
    transition: border-color var(--tr);
}
.essay-ta:focus { border-color: var(--accent); }
.essay-ta::placeholder { color: var(--dim); }
.wc-label { font-size: .72rem; color: var(--dim); text-align: right; }

/* ── FILE UPLOAD ────────────────────────────────── */
.file-zone {
    border: 2px dashed var(--border); border-radius: var(--rs);
    padding: 28px 20px; text-align: center; cursor: pointer;
    transition: var(--tr); color: var(--text2);
}
.file-zone:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-gl); }
.file-zone input { display: none; }
.file-zone i { font-size: 1.8rem; display: block; margin-bottom: 8px; opacity: .55; }
.file-zone small { color: var(--dim); font-size: .75rem; }
.file-picked { font-size: .8rem; color: var(--green); margin-top: 8px; display: block; }

/* ── STATUS BADGE on card ───────────────────────── */
.q-status-dot {
    width: 8px; height: 8px; border-radius: 50%;
    flex-shrink: 0; margin-left: 2px;
}
.q-status-dot.answered { background: var(--green); }
.q-status-dot.flagged  { background: var(--amber); }
.q-status-dot.empty    { background: var(--dim); }

/* ── PAGINATION NAV ─────────────────────────────── */
.page-nav {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; margin-top: 28px;
    padding-top: 20px; border-top: 1px solid var(--border);
    flex-wrap: wrap;
}
.page-nav-center {
    display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;
}
.pg-num-btn {
    width: 36px; height: 36px;
    border-radius: var(--rs);
    font-family: 'JetBrains Mono', monospace; font-size: .78rem;
    cursor: pointer; border: 1px solid var(--border);
    background: var(--surf2); color: var(--text2);
    transition: var(--tr); display: flex; align-items: center; justify-content: center;
}
.pg-num-btn:hover   { border-color: var(--accent); color: var(--accent); }
.pg-num-btn.active  { background: var(--accent); color: #05111f; border-color: var(--accent); font-weight: 700; }
.pg-num-btn.all-done { border-color: var(--green); color: var(--green); background: var(--green-gl); }

/* ── COVERS / RESULTS / SUBMITTED ───────────────── */
.center-wrap {
    max-width: 580px; margin: 50px auto; padding: 0 20px;
}
.cover-card, .result-card, .submitted-card {
    background: var(--surf);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 32px 28px;
    box-shadow: var(--shadow);
}
.cover-card h1, .result-card h2, .submitted-card h2 {
    font-family: 'Syne', sans-serif;
    font-weight: 800; margin-bottom: 8px;
}
.cover-meta {
    display: flex; flex-wrap: wrap; gap: 10px;
    padding: 14px 0; margin: 14px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
.cm-item { display: flex; align-items: center; gap: 6px; font-size: .82rem; color: var(--text2); }
.cm-item i { color: var(--accent); }
.cover-rules {
    background: var(--surf2); border: 1px solid var(--border);
    border-radius: var(--rs); padding: 14px 16px;
    font-size: .82rem; line-height: 1.75; color: var(--text2);
    margin-bottom: 20px;
}
.cover-rules strong { color: var(--red); display: block; margin-bottom: 6px; }
.cover-instructions {
    background: rgba(79,158,255,.06); border: 1px solid rgba(79,158,255,.2);
    border-radius: var(--rs); padding: 12px 16px;
    font-size: .84rem; color: var(--text2); line-height: 1.6; margin-bottom: 18px;
}

.score-ring {
    width: 130px; height: 130px; border-radius: 50%; border: 6px solid;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    margin: 0 auto 22px;
}
.score-ring.pass { border-color: var(--green); }
.score-ring.fail { border-color: var(--red); }
.score-pct { font-family: 'Syne', sans-serif; font-size: 1.9rem; font-weight: 800; }
.score-ring.pass .score-pct { color: var(--green); }
.score-ring.fail .score-pct { color: var(--red); }
.score-sub { font-size: .78rem; color: var(--text2); }

/* ── MODAL ──────────────────────────────────────── */
.modal-bg {
    position: fixed; inset: 0; z-index: 500;
    background: rgba(0,0,0,.8); backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none; transition: opacity .2s;
}
.modal-bg.open { opacity: 1; pointer-events: all; }
.modal-box {
    background: var(--surf); border: 1px solid var(--border);
    border-radius: var(--r); padding: 28px 28px 24px;
    width: 440px; max-width: 92vw; text-align: center;
    box-shadow: 0 24px 60px rgba(0,0,0,.6);
}
.modal-box h3 { font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 700; margin-bottom: 10px; }
.modal-box p  { font-size: .86rem; color: var(--text2); line-height: 1.6; margin-bottom: 22px; }
.modal-actions { display: flex; gap: 10px; justify-content: center; }

/* ── FS NUDGE ───────────────────────────────────── */
#fs-nudge {
    position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
    background: var(--amber-gl); border: 1px solid rgba(251,191,36,.35);
    color: var(--amber); padding: 10px 18px; border-radius: 999px;
    font-size: .8rem; z-index: 400; display: none;
    gap: 10px; align-items: center;
}
#fs-nudge button {
    background: var(--amber-gl); border: 1px solid rgba(251,191,36,.35);
    color: var(--amber); padding: 3px 10px; border-radius: var(--rs);
    cursor: pointer; font-size: .74rem; font-family: 'DM Sans', sans-serif;
}

::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--surf3); border-radius: 2px; }
</style>
</head>
<body>

<?php if ($existing_submission && $existing_submission['status'] !== 'flagged'): ?>
<!-- ── ALREADY SUBMITTED ─────────────────────── -->
<div class="center-wrap">
    <div class="submitted-card" style="text-align:center; margin-top:60px">
        <i class="fas fa-circle-check" style="font-size:2.8rem;color:var(--green);display:block;margin-bottom:16px"></i>
        <h2 style="font-size:1.3rem; margin-bottom:8px">Already Submitted</h2>
        <p style="color:var(--text2);font-size:.87rem;margin-bottom:20px">
            You submitted this <?= $assessment['type'] ?> on
            <?= date('d M Y, H:i', strtotime($existing_submission['submitted_at'])) ?>.
        </p>
        <?php if ($existing_submission['score'] !== null): ?>
        <div class="score-ring <?= $existing_submission['score'] >= $assessment['pass_mark'] ? 'pass':'fail' ?>" style="margin-bottom:16px">
            <span class="score-pct"><?= round($existing_submission['score']) ?>%</span>
            <span class="score-sub"><?= $existing_submission['score'] >= $assessment['pass_mark'] ? 'PASS':'FAIL' ?></span>
        </div>
        <?php else: ?>
        <p style="color:var(--amber);font-size:.85rem;margin-bottom:16px">
            <i class="fas fa-hourglass-half"></i> Awaiting grading
        </p>
        <?php endif; ?>
        <a href="course_view.php?unit_id=<?= $assessment['unit_id'] ?>" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Back to Course
        </a>
    </div>
</div>

<?php elseif (!isset($_GET['start'])): ?>
<!-- ── COVER SCREEN ──────────────────────────── -->
<div class="center-wrap">
    <div class="cover-card">
        <div style="margin-bottom:12px">
            <span class="type-pill pill-<?= $assessment['type'] ?>"><?= strtoupper($assessment['type']) ?></span>
        </div>
        <h1 style="font-size:1.45rem;margin-bottom:6px"><?= htmlspecialchars($assessment['title']) ?></h1>
        <p style="color:var(--text2);font-size:.84rem"><?= htmlspecialchars($assessment['unit_name']) ?></p>

        <div class="cover-meta">
            <div class="cm-item"><i class="fas fa-circle-question"></i> <?= $q_count ?> Questions</div>
            <div class="cm-item"><i class="fas fa-star"></i> <?= $total_marks ?> Marks</div>
            <div class="cm-item"><i class="fas fa-check-circle"></i> Pass: <?= $assessment['pass_mark'] ?></div>
            <?php if ($assessment['time_limit_mins']): ?>
            <div class="cm-item"><i class="fas fa-clock"></i> <?= $assessment['time_limit_mins'] ?> min</div>
            <?php else: ?>
            <div class="cm-item"><i class="fas fa-infinity"></i> No time limit</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($assessment['instructions'])): ?>
        <div class="cover-instructions">
            <strong style="color:var(--text);display:block;margin-bottom:5px">
                <i class="fas fa-circle-info"></i> Instructions
            </strong>
            <?= nl2br(htmlspecialchars($assessment['instructions'])) ?>
        </div>
        <?php endif; ?>

        <?php if ($is_proctored): ?>
        <div class="cover-rules">
            <strong><i class="fas fa-shield-halved"></i> &nbsp;Exam Security Notice</strong>
            • Tab switching, window blur and fullscreen exit are logged<br>
            • Copy, paste, right-click and dev tools are disabled<br>
            • Keyboard shortcuts (Ctrl+C, F12, PrintScreen, etc.) are blocked<br>
            • 10+ violations trigger automatic submission<br>
            • All events are reported to your lecturer
        </div>
        <?php endif; ?>

        <a href="take_assessment.php?assessment_id=<?= $assessment_id ?>&start=1"
           class="btn btn-accent" style="width:100%;justify-content:center;padding:13px;font-size:.9rem">
            <i class="fas fa-play-circle"></i>
            <?= $is_proctored ? 'Start Proctored '.strtoupper($assessment['type']) : 'Start '.ucfirst($assessment['type']) ?>
        </a>
    </div>
</div>

<?php else: ?>
<!-- ── EXAM INTERFACE ─────────────────────────── -->

<div class="lockbar">
    <span class="lockbar-brand">UNILIS</span>
    <span class="type-pill pill-<?= $assessment['type'] ?>"><?= strtoupper($assessment['type']) ?></span>
    <span style="font-size:.82rem;color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px">
        <?= htmlspecialchars($assessment['title']) ?>
    </span>
    <?php if ($is_proctored): ?>
    <div class="sec-dots" title="Security">
        <div class="sec-dot ok" id="dot-tab"></div>
        <div class="sec-dot ok" id="dot-focus"></div>
        <div class="sec-dot ok" id="dot-fs"></div>
    </div>
    <?php endif; ?>
    <div class="lockbar-spacer"></div>
    <?php if ($assessment['time_limit_mins']): ?>
    <div class="timer-box">
        <i class="fas fa-clock"></i>
        <span id="timer-display">--:--</span>
    </div>
    <?php endif; ?>
    <span class="prog-badge" id="prog-label">0 / <?= $q_count ?> answered</span>
    <button class="btn btn-danger btn-sm" onclick="confirmSubmit()">
        <i class="fas fa-paper-plane"></i> Submit
    </button>
</div>

<div id="vbanner">
    <div><i class="fas fa-triangle-exclamation"></i> &nbsp;<span id="vbanner-msg">Violation detected.</span></div>
    <button onclick="this.parentElement.style.display='none'">Dismiss</button>
</div>

<div class="page-wrap">

    <!-- Page header info -->
    <div class="page-header">
        <div class="page-header-left">
            <h1><?= htmlspecialchars($assessment['title']) ?></h1>
            <p><?= htmlspecialchars($assessment['unit_name']) ?></p>
            <div class="page-info-chips">
                <span class="info-chip"><i class="fas fa-circle-question"></i> <?= $q_count ?> questions</span>
                <span class="info-chip"><i class="fas fa-star"></i> <?= $total_marks ?> marks total</span>
                <span class="info-chip"><i class="fas fa-layer-group"></i> <?= $total_pages ?> page<?= $total_pages > 1 ? 's' : '' ?></span>
            </div>
        </div>
    </div>

    <!-- Page progress strip -->
    <div class="pager-strip">
        <span class="pager-label">Page <span id="cur-page-lbl">1</span> of <?= $total_pages ?></span>
        <div class="pager-dots" id="pager-dots">
            <?php for ($p = 0; $p < $total_pages; $p++): ?>
            <div class="pager-dot <?= $p === 0 ? 'active' : '' ?>" onclick="goPage(<?= $p ?>)" title="Page <?= $p+1 ?>"></div>
            <?php endfor; ?>
        </div>
        <span style="font-size:.75rem;color:var(--text2)" id="page-q-range"></span>
    </div>

    <!-- Question pages (each holds up to 5 questions) -->
    <?php
    $q_chunks = array_chunk($questions, $PER_PAGE, true);
    $global_i = 0;
    foreach ($q_chunks as $pi => $chunk):
    ?>
    <div class="q-page <?= $pi === 0 ? 'active' : '' ?>" id="qpage-<?= $pi ?>">
        <?php foreach ($chunk as $q):
            $qi = $global_i; $global_i++;
        ?>
        <div class="q-card" id="qcard-<?= $qi ?>">
            <!-- Card top bar -->
            <div class="q-card-top">
                <span class="q-num">Q<?= $qi + 1 ?></span>
                <span class="q-type-label"><?= str_replace('_', ' ', $q['question_type']) ?></span>
                <div class="q-status-dot empty" id="qdot-<?= $qi ?>"></div>
                <button class="q-flag-btn" id="flagbtn-<?= $qi ?>" onclick="toggleFlag(<?= $qi ?>)">
                    <i class="fas fa-flag"></i> Flag
                </button>
                <span class="q-marks"><?= $q['marks'] ?> mark<?= $q['marks'] != 1 ? 's' : '' ?></span>
            </div>

            <!-- Card body -->
            <div class="q-body">

                <!-- Question text -->
                <div class="q-text"><?= htmlspecialchars($q['question_text']) ?></div>

                <!-- ── MCQ ── -->
                <?php if ($q['question_type'] === 'mcq'): ?>
                <div class="opt-list" id="opts-<?= $qi ?>">
                    <?php $ltrs = ['A','B','C','D','E','F','G','H'];
                    foreach ($q['options'] as $oi => $opt): ?>
                    <div class="opt-item"
                         id="opt-<?= $qi ?>-<?= $oi ?>"
                         onclick="pickMCQ(<?= $qi ?>, <?= $opt['id'] ?>, this)">
                        <span class="opt-letter"><?= $ltrs[$oi] ?? '' ?></span>
                        <span class="opt-text"><?= htmlspecialchars($opt['option_text']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── TRUE/FALSE ── -->
                <?php elseif ($q['question_type'] === 'true_false'): ?>
                <div class="tf-row" id="opts-<?= $qi ?>">
                    <?php foreach ($q['options'] as $opt):
                        $isTrueOpt = strtolower($opt['option_text']) === 'true'; ?>
                    <div class="tf-card"
                         id="tf-<?= $qi ?>-<?= $opt['id'] ?>"
                         onclick="pickTF(<?= $qi ?>, <?= $opt['id'] ?>, <?= $isTrueOpt ? 'true':'false' ?>, this)">
                        <i class="fas <?= $isTrueOpt ? 'fa-circle-check':'fa-circle-xmark' ?>"></i>
                        <span><?= htmlspecialchars($opt['option_text']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── MATCHING ── -->
                <?php elseif ($q['question_type'] === 'matching'): ?>
                <?php $allPairs = array_column($q['options'], 'match_pair'); shuffle($allPairs); ?>
                <div class="match-rows" id="opts-<?= $qi ?>">
                    <?php foreach ($q['options'] as $oi => $opt): ?>
                    <div class="match-row">
                        <span class="match-term"><?= htmlspecialchars($opt['option_text']) ?></span>
                        <i class="fas fa-arrow-right match-arrow"></i>
                        <select class="match-sel"
                                data-opt="<?= $opt['id'] ?>"
                                onchange="pickMatch(<?= $qi ?>, <?= $opt['id'] ?>, this.value)">
                            <option value="">— select —</option>
                            <?php foreach ($allPairs as $pair): ?>
                            <option value="<?= htmlspecialchars($pair) ?>"><?= htmlspecialchars($pair) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── SHORT ANSWER ── -->
                <?php elseif ($q['question_type'] === 'short_answer'): ?>
                <input type="text" class="short-inp"
                       id="ans-<?= $qi ?>"
                       placeholder="Type your answer here…"
                       oninput="saveText(<?= $qi ?>, this.value)">

                <!-- ── ESSAY ── -->
                <?php elseif ($q['question_type'] === 'essay'): ?>
                <div class="essay-wrap">
                    <textarea class="essay-ta"
                              id="ans-<?= $qi ?>"
                              placeholder="Write your answer here…"
                              oninput="saveText(<?= $qi ?>, this.value); countWords(<?= $qi ?>)"></textarea>
                    <span class="wc-label" id="wc-<?= $qi ?>">0 words</span>
                </div>

                <!-- ── FILE UPLOAD ── -->
                <?php elseif ($q['question_type'] === 'file_upload'): ?>
                <div class="file-zone" onclick="document.getElementById('fu-<?= $qi ?>').click()">
                    <input type="file" id="fu-<?= $qi ?>"
                           onchange="handleUpload(<?= $qi ?>, <?= $q['id'] ?>, this)">
                    <i class="fas fa-file-arrow-up"></i>
                    <div>Click to upload your file</div>
                    <small>PDF, DOCX, ZIP — max 20 MB</small>
                    <span class="file-picked" id="fp-<?= $qi ?>"></span>
                </div>
                <?php endif; ?>

            </div><!-- /.q-body -->
        </div><!-- /.q-card -->
        <?php endforeach; ?>

        <!-- Per-page navigation -->
        <div class="page-nav">
            <button class="btn btn-ghost" onclick="prevPage()" <?= $pi === 0 ? 'style="visibility:hidden"':'' ?>>
                <i class="fas fa-arrow-left"></i> Previous
            </button>
            <div class="page-nav-center">
                <?php for ($p = 0; $p < $total_pages; $p++): ?>
                <button class="pg-num-btn <?= $p === $pi ? 'active':'' ?>"
                        id="pnbtn-<?= $p ?>"
                        onclick="goPage(<?= $p ?>)"><?= $p + 1 ?></button>
                <?php endfor; ?>
            </div>
            <?php if ($pi < $total_pages - 1): ?>
            <button class="btn btn-accent" onclick="nextPage()">
                Next <i class="fas fa-arrow-right"></i>
            </button>
            <?php else: ?>
            <button class="btn btn-success" onclick="confirmSubmit()">
                <i class="fas fa-paper-plane"></i> Submit <?= ucfirst($assessment['type']) ?>
            </button>
            <?php endif; ?>
        </div>

    </div><!-- /.q-page -->
    <?php endforeach; ?>

</div><!-- /.page-wrap -->

<!-- Fullscreen nudge -->
<div id="fs-nudge">
    <i class="fas fa-expand"></i>
    Fullscreen recommended
    <button onclick="goFullscreen()">Enter</button>
    <button onclick="this.parentElement.style.display='none'" style="opacity:.6">Dismiss</button>
</div>

<!-- Submit modal -->
<div class="modal-bg" id="modal-submit">
    <div class="modal-box">
        <h3><i class="fas fa-paper-plane" style="color:var(--accent)"></i> &nbsp;Submit <?= ucfirst($assessment['type']) ?>?</h3>
        <p>You are about to submit. <span id="warn-unanswered" style="color:var(--amber)"></span>This cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('modal-submit')">Go Back</button>
            <button class="btn btn-success" id="do-submit-btn" onclick="submitExam()">
                <i class="fas fa-check"></i> Yes, Submit
            </button>
        </div>
    </div>
</div>

<!-- Violation modal -->
<div class="modal-bg" id="modal-violation">
    <div class="modal-box">
        <i class="fas fa-triangle-exclamation" style="font-size:2rem;color:var(--red);display:block;margin-bottom:12px"></i>
        <h3 style="color:var(--red)">Security Violation</h3>
        <p id="vmodal-msg">A violation has been recorded.</p>
        <div class="modal-actions">
            <button class="btn btn-accent" onclick="closeModal('modal-violation');goFullscreen()">Return to Exam</button>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ── JAVASCRIPT ─────────────────────────────────────────────── -->
<script>
const ASSESSMENT_ID  = <?= $assessment_id ?>;
const STUDENT_ID     = <?= $student_id ?>;
const IS_PROCTORED   = <?= $is_proctored ? 'true' : 'false' ?>;
const TIME_LIMIT_SEC = <?= ($assessment['time_limit_mins'] ?? 0) * 60 ?>;
const Q_COUNT        = <?= $q_count ?>;
const TOTAL_PAGES    = <?= $total_pages ?>;
const PER_PAGE       = <?= $PER_PAGE ?>;
const STARTED        = <?= isset($_GET['start']) ? 'true' : 'false' ?>;

/* ── STATE ── */
let answers        = {};
let flags          = {};
let violations     = [];
let violationCount = 0;
let timerInterval  = null;
let timeLeft       = TIME_LIMIT_SEC;
let examStart      = Date.now();
let submitting     = false;
let curPage        = 0;

/* ── BOOT ── */
if (STARTED) {
    initExam();
    updatePageRange();
}

function initExam() {
    if (IS_PROCTORED) {
        initProctoring();
        setTimeout(() => {
            goFullscreen();
            const nudge = document.getElementById('fs-nudge');
            if (nudge) { nudge.style.display = 'flex'; setTimeout(() => nudge.style.display = 'none', 7000); }
        }, 600);
    }
    if (TIME_LIMIT_SEC > 0) startTimer();
}

/* ── TIMER ── */
function startTimer() {
    timeLeft = TIME_LIMIT_SEC;
    renderTimer();
    timerInterval = setInterval(() => {
        timeLeft--;
        renderTimer();
        if (timeLeft <= 0) { clearInterval(timerInterval); toast('Time up — auto submitting…', 'red'); setTimeout(submitExam, 2000); }
    }, 1000);
}
function renderTimer() {
    const el = document.getElementById('timer-display');
    if (!el) return;
    const m = String(Math.floor(timeLeft / 60)).padStart(2,'0');
    const s = String(timeLeft % 60).padStart(2,'0');
    el.textContent = `${m}:${s}`;
    el.className = timeLeft <= 60 ? 'critical' : timeLeft <= 300 ? 'warning' : '';
}

/* ── PAGINATION ── */
function goPage(p) {
    document.querySelector('.q-page.active')?.classList.remove('active');
    document.getElementById('qpage-' + p)?.classList.add('active');
    curPage = p;

    // update pager dots
    document.querySelectorAll('.pager-dot').forEach((d,i) => {
        d.classList.toggle('active', i === p);
    });
    // update page number buttons
    document.querySelectorAll('.pg-num-btn').forEach((b,i) => {
        b.classList.toggle('active', i === p);
    });
    document.getElementById('cur-page-lbl').textContent = p + 1;
    updatePageRange();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function nextPage() { if (curPage < TOTAL_PAGES - 1) goPage(curPage + 1); }
function prevPage() { if (curPage > 0) goPage(curPage - 1); }

function updatePageRange() {
    const el = document.getElementById('page-q-range');
    if (!el) return;
    const from = curPage * PER_PAGE + 1;
    const to   = Math.min((curPage + 1) * PER_PAGE, Q_COUNT);
    el.textContent = `Q${from}–Q${to}`;
}

/* ── ANSWER HANDLERS ── */
function pickMCQ(qi, optId, el) {
    el.closest('.opt-list').querySelectorAll('.opt-item').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    answers[qi] = { type: 'mcq', optionId: optId };
    markDot(qi, 'answered');
}

function pickTF(qi, optId, isTrue, el) {
    el.closest('.tf-row').querySelectorAll('.tf-card').forEach(c => c.className = 'tf-card');
    el.classList.add(isTrue ? 'sel-true' : 'sel-false');
    answers[qi] = { type: 'true_false', optionId: optId };
    markDot(qi, 'answered');
}

function pickMatch(qi, optId, val) {
    if (!answers[qi]) answers[qi] = { type: 'matching', matchAnswers: {} };
    answers[qi].matchAnswers[optId] = val;
    const card    = document.getElementById('qcard-' + qi);
    const allFill = [...card.querySelectorAll('.match-sel')].every(s => s.value !== '');
    markDot(qi, allFill ? 'answered' : 'empty');
}

function saveText(qi, val) {
    answers[qi] = { type: 'text', value: val.trim() };
    markDot(qi, val.trim().length > 0 ? 'answered' : 'empty');
}

function countWords(qi) {
    const ta = document.getElementById('ans-' + qi);
    const el = document.getElementById('wc-' + qi);
    if (ta && el) {
        const w = ta.value.trim().split(/\s+/).filter(w => w).length;
        el.textContent = w + ' word' + (w !== 1 ? 's' : '');
    }
}

function handleUpload(qi, questionId, input) {
    const file = input.files[0];
    if (!file) return;
    const fp = document.getElementById('fp-' + qi);
    if (fp) fp.textContent = 'Uploading: ' + file.name;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('question_id', questionId);
    fd.append('assessment_id', ASSESSMENT_ID);
    fd.append('student_id', STUDENT_ID);
    safeFetch('ajax/upload_answer_file.php', { method: 'POST', body: fd })
        .then(d => {
            if (d && d.success) {
                answers[qi] = { type: 'file', filePath: d.path };
                markDot(qi, 'answered');
                if (fp) fp.textContent = '✓ ' + file.name;
                toast('File uploaded', 'green');
            } else {
                if (fp) fp.textContent = '';
                toast((d && d.message) || 'Upload failed', 'red');
            }
        })
        .catch(err => { console.warn('Upload fetch error:', err); toast('Upload error — try again', 'red'); });
}

/* ── STATUS DOTS & FLAG ── */
function markDot(qi, state) {
    const dot = document.getElementById('qdot-' + qi);
    if (!dot) return;
    dot.className = 'q-status-dot ' + (flags[qi] ? 'flagged' : state);
    updateProgress();
    updatePagerDots();
}

function toggleFlag(qi) {
    flags[qi] = !flags[qi];
    const btn = document.getElementById('flagbtn-' + qi);
    btn?.classList.toggle('flagged', flags[qi]);
    const state = answers[qi] ? 'answered' : 'empty';
    markDot(qi, flags[qi] ? 'flagged' : state);
}

function updateProgress() {
    const n = Object.keys(answers).filter(k => {
        const a = answers[k];
        if (!a) return false;
        if (a.type === 'text')     return (a.value || '').length > 0;
        if (a.type === 'matching') return Object.keys(a.matchAnswers || {}).length > 0;
        return !!(a.optionId || a.filePath);
    }).length;
    const el = document.getElementById('prog-label');
    if (el) el.textContent = `${n} / ${Q_COUNT} answered`;
}

function updatePagerDots() {
    const dots = document.querySelectorAll('.pager-dot');
    dots.forEach((dot, pi) => {
        const from = pi * PER_PAGE;
        const to   = Math.min(from + PER_PAGE, Q_COUNT);
        let answered = 0, flagged = 0;
        for (let i = from; i < to; i++) {
            if (flags[i]) flagged++;
            else if (answers[i]) answered++;
        }
        dot.classList.remove('done', 'partial', 'active');
        if (pi === curPage) dot.classList.add('active');
        else if (flagged > 0) dot.classList.add('partial');
        else if (answered === (to - from)) dot.classList.add('done');
        else if (answered > 0) dot.classList.add('partial');
    });
}

/* ── SAFE FETCH (handles browser-extension interference) ── */
function safeFetch(url, options = {}) {
    return new Promise((resolve, reject) => {
        // Abort controller for timeout
        const controller = new AbortController();
        const timeout = setTimeout(() => { controller.abort(); reject(new Error('Request timed out')); }, 30000);

        fetch(url, { ...options, signal: controller.signal })
            .then(response => {
                clearTimeout(timeout);
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(text => {
                try { resolve(JSON.parse(text)); }
                catch (e) { reject(new Error('Invalid JSON response: ' + text.substring(0, 100))); }
            })
            .catch(err => {
                clearTimeout(timeout);
                // Swallow extension-caused channel-closed errors silently for violation logging
                if (err && err.message && err.message.includes('message channel closed')) {
                    resolve(null); // treat as no-op
                } else {
                    reject(err);
                }
            });
    });
}


/* ── SUBMIT ── */
function confirmSubmit() {
    const answered  = Object.keys(answers).length;
    const unanswered = Q_COUNT - answered;
    const w = document.getElementById('warn-unanswered');
    if (w) w.textContent = unanswered > 0 ? `⚠ ${unanswered} question${unanswered > 1 ? 's' : ''} unanswered. ` : '';
    openModal('modal-submit');
}

function submitExam() {
    if (submitting) return;
    submitting = true;
    clearInterval(timerInterval);
    const btn = document.getElementById('do-submit-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…'; }
    safeFetch('ajax/submit_assessment.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            assessment_id: ASSESSMENT_ID,
            student_id:    STUDENT_ID,
            answers,
            violations,
            time_taken: Math.floor((Date.now() - examStart) / 1000)
        })
    })
    .then(d => {
        if (d && d.success) { exitFullscreen(); showResult(d); }
        else {
            toast((d && d.message) || 'Submission failed', 'red');
            submitting = false;
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Yes, Submit'; }
        }
    })
    .catch(err => {
        console.warn('Submit error:', err);
        toast('Network error — try again', 'red');
        submitting = false;
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Yes, Submit'; }
    });
}

function showResult(data) {
    closeModal('modal-submit');
    document.querySelector('.page-wrap').innerHTML = `
        <div style="max-width:480px;margin:60px auto;text-align:center">
            <div class="score-ring ${data.passed ? 'pass':'fail'}">
                <span class="score-pct">${data.score !== null ? Math.round(data.score)+'%' : '?'}</span>
                <span class="score-sub">${data.score !== null ? (data.passed ? 'PASS':'FAIL') : 'PENDING'}</span>
            </div>
            <h2 style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;margin-bottom:8px">
                ${data.score !== null ? 'Submitted & Auto-Graded' : 'Submitted Successfully'}
            </h2>
            <p style="color:var(--text2);font-size:.87rem;margin-bottom:20px">
                ${data.score !== null ? `You scored ${data.raw_score} / ${data.total_marks} marks.` : 'Your submission is received. Manual grading pending.'}
            </p>
            ${violations.length ? `<p style="color:var(--amber);font-size:.8rem;margin-bottom:16px"><i class="fas fa-triangle-exclamation"></i> ${violations.length} security event(s) logged.</p>` : ''}
            <a href="course_view.php?unit_id=${data.unit_id||''}" class="btn btn-ghost">
                <i class="fas fa-arrow-left"></i> Back to Course
            </a>
        </div>`;
    const lb = document.querySelector('.lockbar');
    if (lb) lb.style.display = 'none';
}

/* ── PROCTORING ── */
function initProctoring() {
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) logV('tab_switch', 'Tab switched');
    });
    window.addEventListener('blur',  () => { logV('window_blur','Window lost focus'); setDot('dot-focus', false); });
    window.addEventListener('focus', () => setDot('dot-focus', true));
    document.addEventListener('contextmenu', e => { e.preventDefault(); logV('right_click','Right-click'); showBanner('Right-click is disabled.'); });
    document.addEventListener('keydown', e => {
        const bad = [
            e.ctrlKey && ['c','v','x','a','u','s','p'].includes(e.key.toLowerCase()),
            e.ctrlKey && e.shiftKey, e.ctrlKey && e.key==='Tab', e.altKey && e.key==='Tab',
            ['F12','PrintScreen'].includes(e.key),
            e.ctrlKey && ['t','w'].includes(e.key), e.metaKey
        ];
        if (bad.some(Boolean)) {
            e.preventDefault(); e.stopPropagation();
            logV('key_blocked', e.key);
            showBanner('Keyboard shortcut blocked.');
        }
    }, true);
    ['copy','paste','cut'].forEach(ev => document.addEventListener(ev, e => { e.preventDefault(); logV(ev, ev+' blocked'); }));
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
            setDot('dot-fs', false); logV('fullscreen_exit','Exited fullscreen');
            document.getElementById('vmodal-msg').textContent = 'You exited fullscreen. This has been recorded.';
            openModal('modal-violation');
        } else setDot('dot-fs', true);
    });
    setInterval(() => {
        if (window.outerWidth - window.innerWidth > 160 || window.outerHeight - window.innerHeight > 160)
            logV('devtools','DevTools detected');
    }, 3000);
}

function logV(type, detail) {
    violationCount++;
    violations.push({ type, detail, ts: new Date().toISOString() });
    const fd = new FormData();
    fd.append('assessment_id', ASSESSMENT_ID);
    fd.append('student_id',    STUDENT_ID);
    fd.append('violation_type', type);
    fd.append('details', detail);
    safeFetch('ajax/log_violation.php', { method:'POST', body:fd }).catch(()=>{});
    if (IS_PROCTORED && violationCount >= 10 && !submitting) {
        toast('Too many violations — auto submitting', 'red');
        setTimeout(submitExam, 2500);
    }
}

function setDot(id, ok) {
    const d = document.getElementById(id);
    if (d) d.className = 'sec-dot ' + (ok ? 'ok' : 'warn');
}

function showBanner(msg) {
    const b = document.getElementById('vbanner');
    document.getElementById('vbanner-msg').textContent = msg;
    b.style.display = 'flex';
    clearTimeout(b._t);
    b._t = setTimeout(() => b.style.display = 'none', 4000);
}

/* ── FULLSCREEN ── */
function goFullscreen() {
    const el = document.documentElement;
    (el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen).call(el);
    setDot('dot-fs', true);
}
function exitFullscreen() {
    if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen();
}

/* ── MODAL ── */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

/* ── TOAST ── */
function toast(msg, color = 'accent') {
    const c = color === 'red' ? 'red' : color === 'green' ? 'green' : 'accent';
    const el = document.createElement('div');
    el.style.cssText = `
        position:fixed;bottom:76px;right:20px;z-index:999;
        background:var(--surf2);border:1px solid var(--border);
        border-left:3px solid var(--${c});
        border-radius:var(--rs);padding:10px 16px;
        font-size:.82rem;color:var(--text);
        box-shadow:0 6px 24px rgba(0,0,0,.5);
        max-width:280px;animation:fadeUp .2s ease;
    `;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

document.addEventListener('selectstart', e => { if (IS_PROCTORED) e.preventDefault(); });
</script>

<style>
@keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
</style>

</body>
</html>