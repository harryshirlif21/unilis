<?php
// lecturer/grade_submission.php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php"); exit;
}

$lecturer_id   = intval($_SESSION['user_id']);
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';
$submission_id = intval($_GET['submission_id'] ?? 0);
$unit_id       = intval($_GET['unit_id'] ?? 0);

if (!$submission_id) { die("<p style='font-family:sans-serif;padding:40px;color:#f87171'>No submission specified.</p>"); }

// ── Fetch submission + assessment + student ────────────────────────
$submission = null;
try {
    $stmt = $conn->prepare("
        SELECT
            asub.id, asub.assessment_id, asub.student_id,
            asub.score, asub.status, asub.submitted_at,
            a.title AS assessment_title, a.type AS assessment_type,
            a.total_marks, a.pass_mark, a.unit_id,
            s.name AS student_name, s.reg_no,
            s.email AS student_email
        FROM assessment_submissions asub
        JOIN assessments a ON a.id = asub.assessment_id
        JOIN students    s ON s.id = asub.student_id
        WHERE asub.id = ? AND a.lecturer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $submission_id, $lecturer_id);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

if (!$submission) { die("<p style='font-family:sans-serif;padding:40px;color:#f87171'>Submission not found or access denied.</p>"); }

if (!$unit_id) $unit_id = intval($submission['unit_id']);

// ── Fetch all questions for this assessment ────────────────────────
$questions = [];
try {
    $stmt = $conn->prepare("
        SELECT id, question_text, question_type, marks, position, auto_grade
        FROM assessment_questions
        WHERE assessment_id = ?
        ORDER BY position ASC, id ASC
    ");
    $stmt->bind_param("i", $submission['assessment_id']);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $row['options'] = [];
        $os = $conn->prepare("SELECT id, option_text, is_correct, match_pair FROM question_options WHERE question_id = ? ORDER BY position ASC");
        $os->bind_param("i", $row['id']);
        $os->execute();
        $or = $os->get_result();
        while ($opt = $or->fetch_assoc()) $row['options'][] = $opt;
        $os->close();
        $questions[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// ── Fetch student's answers for this submission ────────────────────
$answers = []; // keyed by question_id
try {
    $stmt = $conn->prepare("
        SELECT question_id, answer_text, selected_option, file_path, marks_awarded, is_correct
        FROM submission_answers
        WHERE submission_id = ?
    ");
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $answers[$row['question_id']] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// ── Auto-grade any unanswered auto-gradable questions ─────────────
// (in case submit_assessment.php missed some or answers were saved separately)
$total_auto   = 0;
$auto_possible = 0;
$has_manual   = false;
$manual_awarded = 0;
$manual_possible = 0;

foreach ($questions as $q) {
    $ans = $answers[$q['id']] ?? null;
    if ($q['auto_grade']) {
        $auto_possible += $q['marks'];
        if ($ans) {
            // Re-compute auto grade for MCQ/TF from correct options
            if (in_array($q['question_type'], ['mcq', 'true_false'])) {
                $correct_opt = array_values(array_filter($q['options'], fn($o) => $o['is_correct']));
                $correct_id  = !empty($correct_opt) ? intval($correct_opt[0]['id']) : 0;
                $is_correct  = $ans['selected_option'] && intval($ans['selected_option']) === $correct_id ? 1 : 0;
                $awarded     = $is_correct ? $q['marks'] : 0;
                // Update DB if not already set correctly
                if ($ans['marks_awarded'] === null || intval($ans['is_correct']) !== $is_correct) {
                    try {
                        $upd = $conn->prepare("UPDATE submission_answers SET marks_awarded=?, is_correct=? WHERE submission_id=? AND question_id=?");
                        $upd->bind_param("diii", $awarded, $is_correct, $submission_id, $q['id']);
                        $upd->execute(); $upd->close();
                        $answers[$q['id']]['marks_awarded'] = $awarded;
                        $answers[$q['id']]['is_correct']    = $is_correct;
                    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
                }
                $total_auto += floatval($answers[$q['id']]['marks_awarded'] ?? $awarded);
            } elseif ($q['question_type'] === 'matching') {
                $total_auto += floatval($ans['marks_awarded'] ?? 0);
            }
        }
    } else {
        $has_manual = true;
        $manual_possible += $q['marks'];
        $manual_awarded  += floatval($ans['marks_awarded'] ?? 0);
    }
}

// Current total
$current_total = $total_auto + $manual_awarded;
$current_pct   = ($auto_possible + $manual_possible) > 0
    ? round(($current_total / ($auto_possible + $manual_possible)) * 100, 1)
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grade Submission — UNILIS</title>
<link rel="icon" href="data:,">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
    --bg:#080c12;--surf:#0e1420;--surf2:#141c2c;--surf3:#1a2438;
    --border:#1e2d45;--border2:#263652;
    --accent:#3d8ef8;--green:#22d3a0;--amber:#f5a623;--red:#f45b5b;
    --purple:#9b7ff8;--cyan:#22d8f0;
    --text:#dce8f8;--text2:#7a90b0;--dim:#2e4060;
    --r:12px;--rs:7px;--tr:.16s ease;--shadow:0 4px 28px rgba(0,0,0,.5);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 24px;height:56px;
        display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:.95rem;color:var(--accent)}
.brand span{color:var(--text2);font-weight:400;font-size:.78rem;margin-left:8px}
.nav-right{display:flex;align-items:center;gap:8px}
.btn-nav{background:var(--surf2);border:1px solid var(--border);color:var(--text2);padding:5px 12px;
         border-radius:var(--rs);font-size:.77rem;cursor:pointer;text-decoration:none;
         transition:var(--tr);font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:6px}
.btn-nav:hover{border-color:var(--accent);color:var(--accent)}

.wrap{max-width:900px;margin:0 auto;padding:28px 20px 60px}

/* ── HEADER CARD ── */
.header-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);
             padding:22px 26px;margin-bottom:20px;box-shadow:var(--shadow)}
.header-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px}
.header-title{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:800;margin-bottom:4px}
.header-meta{font-size:.82rem;color:var(--text2);display:flex;flex-wrap:wrap;gap:12px;margin-top:6px}
.header-meta span{display:flex;align-items:center;gap:5px}
.type-pill{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;
           padding:3px 9px;border-radius:999px;border:1px solid}
.pill-quiz      {background:rgba(61,142,248,.1); color:var(--accent);border-color:rgba(61,142,248,.25)}
.pill-assignment{background:rgba(34,211,160,.1); color:var(--green); border-color:rgba(34,211,160,.25)}
.pill-cat       {background:rgba(245,166,35,.1); color:var(--amber); border-color:rgba(245,166,35,.25)}
.pill-exam      {background:rgba(244,91,91,.1);  color:var(--red);   border-color:rgba(244,91,91,.25)}

/* ── SCORE SUMMARY ── */
.score-bar{display:flex;align-items:stretch;gap:12px;flex-wrap:wrap}
.score-seg{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);
           padding:12px 18px;flex:1;min-width:120px;text-align:center}
.score-num{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;line-height:1;margin-bottom:4px}
.score-lbl{font-size:.67rem;color:var(--text2);text-transform:uppercase;letter-spacing:.08em}
.score-seg.highlight{border-color:var(--accent);background:rgba(61,142,248,.06)}

/* ── PROGRESS RING ── */
.ring-wrap{display:flex;align-items:center;justify-content:center;gap:18px;flex-wrap:wrap;
           padding:16px 0;border-top:1px solid var(--border);margin-top:16px}
.ring-label{font-size:.8rem;color:var(--text2)}
.final-score{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;
             color:var(--accent);line-height:1}
.status-chip{font-size:.75rem;padding:4px 12px;border-radius:999px;font-weight:600;border:1px solid}
.chip-graded{background:rgba(34,211,160,.1);color:var(--green);border-color:rgba(34,211,160,.25)}
.chip-pending{background:rgba(245,166,35,.1);color:var(--amber);border-color:rgba(245,166,35,.25)}
.chip-flagged{background:rgba(244,91,91,.1); color:var(--red);  border-color:rgba(244,91,91,.25)}

/* ── SECTION HEADERS ── */
.section-hdr{font-family:'Syne',sans-serif;font-size:.72rem;font-weight:700;
             text-transform:uppercase;letter-spacing:.1em;color:var(--text2);
             display:flex;align-items:center;gap:8px;margin:24px 0 12px}
.section-hdr i{font-size:.8rem}

/* ── QUESTION CARD ── */
.q-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);
        margin-bottom:14px;box-shadow:var(--shadow);overflow:visible}
.q-card-top{background:var(--surf2);padding:11px 18px;display:flex;align-items:center;
            gap:8px;border-bottom:1px solid var(--border);border-radius:var(--r) var(--r) 0 0;
            flex-wrap:wrap}
.q-num{font-family:'JetBrains Mono',monospace;font-size:.68rem;
       background:rgba(61,142,248,.1);color:var(--accent);
       border:1px solid rgba(61,142,248,.2);padding:2px 9px;border-radius:999px}
.q-type{font-size:.68rem;color:var(--text2);text-transform:capitalize}
.q-marks-info{margin-left:auto;font-size:.75rem;color:var(--text2);
              font-family:'JetBrains Mono',monospace;display:flex;align-items:center;gap:8px}
.auto-tag{font-size:.62rem;padding:2px 7px;border-radius:999px;font-weight:600}
.auto-yes{background:rgba(34,211,160,.1);color:var(--green);border:1px solid rgba(34,211,160,.2)}
.auto-no {background:rgba(245,166,35,.1);color:var(--amber);border:1px solid rgba(245,166,35,.2)}

.q-body{padding:16px 18px;display:flex;flex-direction:column;gap:14px}
.q-text{font-size:.95rem;line-height:1.65;color:var(--text);word-break:break-word}

/* ── ANSWER DISPLAY ── */
.answer-wrap{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);padding:14px 16px}
.answer-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;
              color:var(--text2);margin-bottom:8px;display:flex;align-items:center;gap:6px}

/* MCQ options list */
.opt-list{display:flex;flex-direction:column;gap:6px}
.opt-row{display:flex;align-items:flex-start;gap:10px;padding:9px 13px;
         border:1px solid var(--border);border-radius:var(--rs);
         background:var(--surf3);font-size:.86rem;line-height:1.5}
.opt-row.correct  {border-color:var(--green);background:rgba(34,211,160,.07)}
.opt-row.selected {border-color:var(--accent);background:rgba(61,142,248,.08)}
.opt-row.correct.selected{border-color:var(--green);background:rgba(34,211,160,.12)}
.opt-row.wrong-selected{border-color:var(--red);background:rgba(244,91,91,.08)}
.opt-icon{width:18px;flex-shrink:0;margin-top:1px;font-size:.78rem}
.opt-text{flex:1;word-break:break-word}
.opt-badges{display:flex;gap:5px;flex-shrink:0;flex-wrap:wrap;margin-top:1px}
.opt-badge{font-size:.6rem;padding:1px 6px;border-radius:999px;font-weight:700;text-transform:uppercase}
.badge-correct {background:rgba(34,211,160,.15);color:var(--green);border:1px solid rgba(34,211,160,.3)}
.badge-selected{background:rgba(61,142,248,.15);color:var(--accent);border:1px solid rgba(61,142,248,.3)}
.badge-wrong   {background:rgba(244,91,91,.15); color:var(--red);   border:1px solid rgba(244,91,91,.3)}

/* Short answer / essay display */
.text-answer{font-size:.88rem;line-height:1.7;color:var(--text);
             white-space:pre-wrap;word-break:break-word;
             background:var(--surf3);border-radius:var(--rs);padding:12px 14px;
             border-left:3px solid var(--accent)}
.no-answer{font-size:.84rem;color:var(--dim);font-style:italic;padding:8px 0}

/* File upload display */
.file-display{display:flex;align-items:center;gap:10px;padding:10px 14px;
              background:var(--surf3);border:1px solid var(--border);border-radius:var(--rs)}
.file-display i{color:var(--accent);font-size:1.1rem}
.file-display a{color:var(--accent);text-decoration:none;font-size:.85rem;font-weight:500}
.file-display a:hover{text-decoration:underline}

/* Result indicator */
.result-row{display:flex;align-items:center;gap:10px;
            padding:10px 14px;border-radius:var(--rs);
            border:1px solid;margin-top:4px}
.result-correct  {background:rgba(34,211,160,.07);border-color:rgba(34,211,160,.25);color:var(--green)}
.result-wrong    {background:rgba(244,91,91,.07); border-color:rgba(244,91,91,.25); color:var(--red)}
.result-partial  {background:rgba(245,166,35,.07);border-color:rgba(245,166,35,.25);color:var(--amber)}
.result-pending  {background:rgba(155,127,248,.07);border-color:rgba(155,127,248,.25);color:var(--purple)}
.result-icon{font-size:.9rem}
.result-text{font-size:.82rem;font-weight:500;flex:1}
.result-score{font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:600}

/* ── MANUAL GRADING FORM ── */
.grade-form{background:rgba(155,127,248,.06);border:1px solid rgba(155,127,248,.2);
            border-radius:var(--rs);padding:14px 16px;margin-top:4px}
.grade-form-label{font-size:.72rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.09em;color:var(--purple);margin-bottom:10px;
                  display:flex;align-items:center;gap:6px}
.grade-form-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.grade-form label{font-size:.8rem;color:var(--text2)}
.grade-input{background:var(--surf2);border:1px solid var(--border2);color:var(--text);
             padding:7px 11px;border-radius:var(--rs);
             font-family:'JetBrains Mono',monospace;font-size:.9rem;
             width:90px;outline:none;transition:border-color var(--tr)}
.grade-input:focus{border-color:var(--purple)}
.grade-max{font-size:.78rem;color:var(--text2)}
.feedback-input{flex:1;min-width:200px;background:var(--surf2);border:1px solid var(--border2);
                color:var(--text);padding:7px 12px;border-radius:var(--rs);
                font-family:'DM Sans',sans-serif;font-size:.83rem;
                outline:none;transition:border-color var(--tr)}
.feedback-input:focus{border-color:var(--purple)}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:var(--rs);
     font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:500;
     cursor:pointer;border:none;transition:var(--tr);white-space:nowrap}
.btn-accent {background:var(--accent);color:#fff}.btn-accent:hover{filter:brightness(1.1)}
.btn-green  {background:var(--green);color:#041a12}.btn-green:hover{filter:brightness(1.08)}
.btn-purple {background:var(--purple);color:#fff}.btn-purple:hover{filter:brightness(1.1)}
.btn-ghost  {background:var(--surf2);border:1px solid var(--border);color:var(--text2)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:5px 11px;font-size:.76rem}
.btn:disabled{opacity:.4;cursor:not-allowed}

/* ── FINALIZE BAR ── */
.finalize-bar{position:sticky;bottom:0;z-index:100;background:var(--surf);
              border-top:1px solid var(--border);padding:14px 24px;
              display:flex;align-items:center;gap:12px;flex-wrap:wrap;
              box-shadow:0 -4px 20px rgba(0,0,0,.4)}
.finalize-score{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;
                color:var(--accent)}
.finalize-label{font-size:.8rem;color:var(--text2)}

/* ── TOAST ── */
#toast-wrap{position:fixed;bottom:80px;right:20px;z-index:999;
            display:flex;flex-direction:column;gap:7px;pointer-events:none}
.toast{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);
       padding:9px 15px;font-size:.81rem;color:var(--text);
       display:flex;align-items:center;gap:8px;
       box-shadow:var(--shadow);max-width:300px;
       animation:tIn .2s ease,tOut .2s ease 2.6s forwards}
.toast.ok {border-left:3px solid var(--green)}
.toast.err{border-left:3px solid var(--red)}
.toast.inf{border-left:3px solid var(--accent)}
@keyframes tIn {from{opacity:0;transform:translateX(12px)}to{opacity:1;transform:none}}
@keyframes tOut{from{opacity:1}to{opacity:0;transform:translateX(12px)}}
@keyframes spin{to{transform:rotate(360deg)}}
.spinner{width:13px;height:13px;border:2px solid var(--border);border-top-color:var(--accent);
         border-radius:50%;animation:spin .6s linear infinite;display:inline-block}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--surf3);border-radius:2px}
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">UNILIS <span>Grade Submission</span></div>
    <div class="nav-right">
        <a href="submissions.php?unit_id=<?= $unit_id ?>" class="btn-nav">
            <i class="fas fa-arrow-left"></i> Back to Submissions
        </a>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="wrap">

    <!-- HEADER CARD -->
    <div class="header-card">
        <div class="header-top">
            <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                    <div class="header-title"><?= htmlspecialchars($submission['assessment_title']) ?></div>
                    <span class="type-pill pill-<?= $submission['assessment_type'] ?>"><?= strtoupper($submission['assessment_type']) ?></span>
                    <span class="status-chip chip-<?= $submission['status'] ?>"><?= ucfirst($submission['status']) ?></span>
                </div>
                <div class="header-meta">
                    <span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($submission['student_name']) ?></span>
                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($submission['reg_no'] ?? '—') ?></span>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($submission['student_email']) ?></span>
                    <span><i class="fas fa-clock"></i> Submitted <?= date('d M Y, H:i', strtotime($submission['submitted_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Score summary -->
        <div class="score-bar">
            <div class="score-seg">
                <div class="score-num" style="color:var(--accent)"><?= $auto_possible ?></div>
                <div class="score-lbl">Auto Marks</div>
            </div>
            <div class="score-seg">
                <div class="score-num" style="color:var(--green)"><?= round($total_auto, 1) ?></div>
                <div class="score-lbl">Auto Scored</div>
            </div>
            <div class="score-seg">
                <div class="score-num" style="color:var(--amber)"><?= $manual_possible ?></div>
                <div class="score-lbl">Manual Marks</div>
            </div>
            <div class="score-seg">
                <div class="score-num" style="color:var(--purple)" id="manual-scored-display"><?= round($manual_awarded, 1) ?></div>
                <div class="score-lbl">Manual Scored</div>
            </div>
            <div class="score-seg highlight">
                <div class="score-num" id="total-display"><?= round($current_total, 1) ?> / <?= $auto_possible + $manual_possible ?></div>
                <div class="score-lbl">Total</div>
            </div>
            <div class="score-seg highlight">
                <div class="score-num" id="pct-display"><?= $current_pct !== null ? $current_pct.'%' : '—' ?></div>
                <div class="score-lbl">Score %</div>
            </div>
        </div>
    </div>

    <!-- AUTO-GRADED QUESTIONS -->
    <?php
    $auto_qs   = array_filter($questions, fn($q) => $q['auto_grade']);
    $manual_qs = array_filter($questions, fn($q) => !$q['auto_grade']);
    ?>

    <?php if (!empty($auto_qs)): ?>
    <div class="section-hdr">
        <i class="fas fa-robot"></i> Auto-Graded Questions
        <span style="font-size:.7rem;color:var(--green);background:rgba(34,211,160,.1);border:1px solid rgba(34,211,160,.2);padding:1px 8px;border-radius:999px;font-weight:600">
            <?= count($auto_qs) ?> question<?= count($auto_qs)!=1?'s':'' ?>
        </span>
    </div>

    <?php foreach ($auto_qs as $i => $q):
        $ans        = $answers[$q['id']] ?? null;
        $sel_opt_id = $ans ? intval($ans['selected_option']) : 0;
        $awarded    = $ans ? floatval($ans['marks_awarded'] ?? 0) : 0;
        $is_correct = $ans ? intval($ans['is_correct'] ?? 0) : -1; // -1 = no answer
        $correct_opt= array_values(array_filter($q['options'], fn($o) => $o['is_correct']));
        $correct_id = !empty($correct_opt) ? intval($correct_opt[0]['id']) : 0;
    ?>
    <div class="q-card">
        <div class="q-card-top">
            <span class="q-num">Q<?= $q['position'] + 1 ?></span>
            <span class="q-type"><?= str_replace('_',' ', $q['question_type']) ?></span>
            <span class="auto-tag auto-yes"><i class="fas fa-robot"></i> Auto</span>
            <div class="q-marks-info">
                <?php if ($ans): ?>
                    <span style="color:<?= $is_correct ? 'var(--green)' : 'var(--red)' ?>">
                        <?= $awarded ?> / <?= $q['marks'] ?> marks
                    </span>
                <?php else: ?>
                    <span style="color:var(--dim)">0 / <?= $q['marks'] ?> — not answered</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="q-body">
            <div class="q-text"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>

            <?php if (in_array($q['question_type'], ['mcq', 'true_false'])): ?>
            <div class="answer-wrap">
                <div class="answer-label"><i class="fas fa-list"></i> Options</div>
                <div class="opt-list">
                    <?php foreach ($q['options'] as $opt):
                        $is_sel  = $sel_opt_id === intval($opt['id']);
                        $is_cor  = intval($opt['is_correct']) === 1;
                        $classes = 'opt-row';
                        if ($is_cor && $is_sel) $classes .= ' correct selected';
                        elseif ($is_cor)        $classes .= ' correct';
                        elseif ($is_sel)        $classes .= ' wrong-selected';
                    ?>
                    <div class="<?= $classes ?>">
                        <span class="opt-icon">
                            <?php if ($is_cor && $is_sel): ?>
                                <i class="fas fa-circle-check" style="color:var(--green)"></i>
                            <?php elseif ($is_cor): ?>
                                <i class="fas fa-circle-check" style="color:var(--green);opacity:.6"></i>
                            <?php elseif ($is_sel): ?>
                                <i class="fas fa-circle-xmark" style="color:var(--red)"></i>
                            <?php else: ?>
                                <i class="far fa-circle" style="color:var(--dim)"></i>
                            <?php endif; ?>
                        </span>
                        <span class="opt-text"><?= htmlspecialchars($opt['option_text']) ?></span>
                        <span class="opt-badges">
                            <?php if ($is_cor): ?><span class="opt-badge badge-correct">Correct</span><?php endif; ?>
                            <?php if ($is_sel && $is_cor): ?><span class="opt-badge badge-selected">Student's answer ✓</span>
                            <?php elseif ($is_sel): ?><span class="opt-badge badge-wrong">Student's answer ✗</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!$ans): ?>
            <div class="result-row result-pending">
                <i class="fas fa-minus-circle result-icon"></i>
                <span class="result-text">Not answered</span>
                <span class="result-score">0 / <?= $q['marks'] ?></span>
            </div>
            <?php elseif ($is_correct): ?>
            <div class="result-row result-correct">
                <i class="fas fa-circle-check result-icon"></i>
                <span class="result-text">Correct answer</span>
                <span class="result-score"><?= $awarded ?> / <?= $q['marks'] ?></span>
            </div>
            <?php else: ?>
            <div class="result-row result-wrong">
                <i class="fas fa-circle-xmark result-icon"></i>
                <span class="result-text">Wrong answer</span>
                <span class="result-score">0 / <?= $q['marks'] ?></span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- MANUAL GRADING QUESTIONS -->
    <?php if (!empty($manual_qs)): ?>
    <div class="section-hdr">
        <i class="fas fa-pen-to-square"></i> Manual Grading Required
        <span style="font-size:.7rem;color:var(--amber);background:rgba(245,166,35,.1);border:1px solid rgba(245,166,35,.2);padding:1px 8px;border-radius:999px;font-weight:600">
            <?= count($manual_qs) ?> question<?= count($manual_qs)!=1?'s':'' ?>
        </span>
    </div>

    <?php foreach ($manual_qs as $q):
        $ans     = $answers[$q['id']] ?? null;
        $awarded = $ans ? floatval($ans['marks_awarded'] ?? '') : '';
        $already = $ans && $ans['marks_awarded'] !== null && $ans['marks_awarded'] !== '';
    ?>
    <div class="q-card" id="qcard-<?= $q['id'] ?>">
        <div class="q-card-top">
            <span class="q-num">Q<?= $q['position'] + 1 ?></span>
            <span class="q-type"><?= str_replace('_',' ', $q['question_type']) ?></span>
            <span class="auto-tag auto-no"><i class="fas fa-user-pen"></i> Manual</span>
            <div class="q-marks-info">
                <span id="qscore-<?= $q['id'] ?>" style="color:<?= $already ? 'var(--green)' : 'var(--amber)' ?>">
                    <?= $already ? $awarded.' / '.$q['marks'].' marks' : 'Awaiting grade — '.$q['marks'].' marks' ?>
                </span>
            </div>
        </div>
        <div class="q-body">
            <div class="q-text"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>

            <!-- Student's answer -->
            <div class="answer-wrap">
                <div class="answer-label"><i class="fas fa-user-graduate"></i> Student's Answer</div>

                <?php if ($q['question_type'] === 'file_upload'): ?>
                    <?php if ($ans && $ans['file_path']): ?>
                    <div class="file-display">
                        <i class="fas fa-file-arrow-down"></i>
                        <a href="../<?= htmlspecialchars($ans['file_path']) ?>" target="_blank" rel="noopener">
                            <?= htmlspecialchars(basename($ans['file_path'])) ?>
                        </a>
                        <span style="font-size:.75rem;color:var(--text2);margin-left:auto">Click to open/download</span>
                    </div>
                    <!-- Image preview if it's an image -->
                    <?php
                    $ext = strtolower(pathinfo($ans['file_path'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])):
                    ?>
                    <div style="margin-top:10px">
                        <img src="../<?= htmlspecialchars($ans['file_path']) ?>"
                             alt="Student upload"
                             style="max-width:100%;max-height:400px;border-radius:var(--rs);border:1px solid var(--border)">
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="no-answer">No file uploaded.</div>
                    <?php endif; ?>

                <?php elseif ($ans && trim($ans['answer_text'] ?? '') !== ''): ?>
                    <div class="text-answer"><?= htmlspecialchars($ans['answer_text']) ?></div>
                <?php else: ?>
                    <div class="no-answer">No answer provided.</div>
                <?php endif; ?>
            </div>

            <!-- Result indicator if already graded -->
            <?php if ($already): ?>
            <div class="result-row result-partial" id="result-<?= $q['id'] ?>">
                <i class="fas fa-star result-icon"></i>
                <span class="result-text">Graded</span>
                <span class="result-score"><?= $awarded ?> / <?= $q['marks'] ?></span>
            </div>
            <?php else: ?>
            <div class="result-row result-pending" id="result-<?= $q['id'] ?>">
                <i class="fas fa-hourglass-half result-icon"></i>
                <span class="result-text">Awaiting grade</span>
                <span class="result-score">— / <?= $q['marks'] ?></span>
            </div>
            <?php endif; ?>

            <!-- Manual grading form -->
            <div class="grade-form">
                <div class="grade-form-label">
                    <i class="fas fa-pen-to-square"></i> Award Marks
                </div>
                <div class="grade-form-row">
                    <label>Marks:</label>
                    <input type="number" class="grade-input"
                           id="gi-<?= $q['id'] ?>"
                           value="<?= $already ? $awarded : '' ?>"
                           placeholder="0"
                           min="0" max="<?= $q['marks'] ?>"
                           step="0.5">
                    <span class="grade-max">/ <?= $q['marks'] ?></span>
                    <input type="text" class="feedback-input"
                           id="fb-<?= $q['id'] ?>"
                           placeholder="Optional feedback to student…">
                    <button class="btn btn-purple btn-sm"
                            onclick="saveManualGrade(<?= $q['id'] ?>, <?= $q['marks'] ?>)"
                            id="gbtn-<?= $q['id'] ?>">
                        <i class="fas fa-floppy-disk"></i> Save
                    </button>
                </div>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /.wrap -->

<!-- FINALIZE BAR -->
<div class="finalize-bar">
    <div>
        <div class="finalize-score" id="fin-score"><?= round($current_total, 1) ?> / <?= $auto_possible + $manual_possible ?></div>
        <div class="finalize-label">Current total &nbsp;·&nbsp;
            <span id="fin-pct"><?= $current_pct !== null ? $current_pct.'%' : 'pending' ?></span>
            &nbsp;·&nbsp; Pass: <?= $submission['pass_mark'] ?>%
        </div>
    </div>
    <div style="flex:1"></div>
    <button class="btn btn-green" onclick="finalizeGrade()" id="finalize-btn">
        <i class="fas fa-check-double"></i> Finalize & Save Grade
    </button>
    <a href="submissions.php?unit_id=<?= $unit_id ?>" class="btn btn-ghost">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div id="toast-wrap"></div>

<script>
const SUBMISSION_ID  = <?= $submission_id ?>;
const ASSESSMENT_ID  = <?= $submission['assessment_id'] ?>;
const STUDENT_ID     = <?= $submission['student_id'] ?>;
const TOTAL_POSSIBLE = <?= $auto_possible + $manual_possible ?>;
const PASS_MARK      = <?= floatval($submission['pass_mark']) ?>;

// Track manually awarded marks per question
let manualScores = {
    <?php foreach ($manual_qs as $q):
        $ans = $answers[$q['id']] ?? null;
        $v = ($ans && $ans['marks_awarded'] !== null && $ans['marks_awarded'] !== '') ? floatval($ans['marks_awarded']) : 'null';
    ?>
    <?= $q['id'] ?>: <?= $v ?>,
    <?php endforeach; ?>
};
let autoTotal = <?= round($total_auto, 1) ?>;

function getManualTotal() {
    return Object.values(manualScores).reduce((s,v) => s + (v !== null ? v : 0), 0);
}

function updateTotals() {
    const total = autoTotal + getManualTotal();
    const pct   = TOTAL_POSSIBLE > 0 ? Math.round(total / TOTAL_POSSIBLE * 1000) / 10 : 0;
    document.getElementById('total-display').textContent       = `${Math.round(total*10)/10} / ${TOTAL_POSSIBLE}`;
    document.getElementById('pct-display').textContent         = `${pct}%`;
    document.getElementById('manual-scored-display').textContent = Math.round(getManualTotal()*10)/10;
    document.getElementById('fin-score').textContent           = `${Math.round(total*10)/10} / ${TOTAL_POSSIBLE}`;
    document.getElementById('fin-pct').textContent             = `${pct}%`;
}

function saveManualGrade(questionId, maxMarks) {
    const input = document.getElementById(`gi-${questionId}`);
    const marks = parseFloat(input.value);
    if (isNaN(marks) || marks < 0 || marks > maxMarks) {
        toast(`Enter a value between 0 and ${maxMarks}`, 'err'); return;
    }
    const btn = document.getElementById(`gbtn-${questionId}`);
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    const fd = new FormData();
    fd.append('submission_id', SUBMISSION_ID);
    fd.append('question_id',   questionId);
    fd.append('marks_awarded', marks);
    fd.append('feedback',      document.getElementById(`fb-${questionId}`)?.value || '');

    fetch('ajax/grade_answer.php', { method:'POST', body:fd })
        .then(r => r.text())          // read as text first so we can see PHP errors
        .then(raw => {
            let d;
            try { d = JSON.parse(raw); }
            catch(e) {
                // Show the raw PHP output so we can debug
                toast('Server error: ' + raw.substring(0, 200), 'err');
                console.error('grade_answer raw response:', raw);
                throw new Error('Invalid JSON');
            }
            if (d.success) {
                manualScores[questionId] = marks;
                updateTotals();

                // Update score badge
                const scoreEl = document.getElementById(`qscore-${questionId}`);
                if (scoreEl) {
                    scoreEl.textContent = `${marks} / ${maxMarks} marks`;
                    scoreEl.style.color = 'var(--green)';
                }
                // Update result row
                const resEl = document.getElementById(`result-${questionId}`);
                if (resEl) {
                    resEl.className = 'result-row result-partial';
                    resEl.innerHTML = `<i class="fas fa-star result-icon"></i>
                        <span class="result-text">Graded</span>
                        <span class="result-score">${marks} / ${maxMarks}</span>`;
                }
                toast(`Saved: ${marks} / ${maxMarks} marks`, 'ok');
            } else {
                toast(d.message || 'Save failed', 'err');
            }
        })
        .catch(err => { if(err.message !== 'Invalid JSON') toast('Network error: '+err.message, 'err'); })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-floppy-disk"></i> Save';
        });
}

function finalizeGrade() {
    const total = autoTotal + getManualTotal();
    const pct   = TOTAL_POSSIBLE > 0 ? Math.round(total / TOTAL_POSSIBLE * 1000) / 10 : 0;

    if (!confirm(`Finalize grade?\n\nTotal: ${Math.round(total*10)/10} / ${TOTAL_POSSIBLE} (${pct}%)\n${pct >= PASS_MARK ? '✓ PASS' : '✗ FAIL'}\n\nThis will update the student's submission record.`)) return;

    const btn = document.getElementById('finalize-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving…';

    const fd = new FormData();
    fd.append('submission_id', SUBMISSION_ID);
    fd.append('score',         pct);
    fd.append('status',        'graded');

    fetch('ajax/finalize_grade.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toast(`Grade finalized: ${pct}% — ${pct >= PASS_MARK ? 'PASS' : 'FAIL'}`, 'ok');
                setTimeout(() => window.location.href = `submissions.php?unit_id=<?= $unit_id ?>`, 1800);
            } else {
                toast(d.message || 'Failed to finalize', 'err');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-double"></i> Finalize & Save Grade';
            }
        })
        .catch(() => {
            toast('Network error', 'err');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-double"></i> Finalize & Save Grade';
        });
}

function toast(msg, type='inf') {
    const w = document.getElementById('toast-wrap');
    const e = document.createElement('div');
    e.className = `toast ${type}`;
    const icons = {ok:'fa-circle-check',err:'fa-circle-xmark',inf:'fa-circle-info'};
    e.innerHTML = `<i class="fas ${icons[type]||'fa-circle-info'}"></i> ${msg}`;
    w.appendChild(e);
    setTimeout(() => e.remove(), 3000);
}
</script>
</body>
</html>