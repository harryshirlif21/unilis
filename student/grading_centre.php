<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php"); exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];
$unit_id       = intval($_GET['unit_id']       ?? 0);
$assessment_id = intval($_GET['assessment_id'] ?? 0);
$submission_id = intval($_GET['submission_id'] ?? 0);

// Units
$units = [];
try {
    $stmt = $conn->prepare("SELECT u.id, u.name FROM units u JOIN lecturer_units lu ON u.id = lu.unit_id WHERE lu.lecturer_id = ? ORDER BY u.name");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Assessments for unit
$assessments = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("
            SELECT a.id, a.title, a.type, a.total_marks,
                   COUNT(DISTINCT asub.id) AS total_submissions,
                   SUM(CASE WHEN asub.status='submitted' THEN 1 ELSE 0 END) AS pending_grade,
                   SUM(CASE WHEN asub.status='graded'    THEN 1 ELSE 0 END) AS graded_count,
                   SUM(CASE WHEN asub.status='flagged'   THEN 1 ELSE 0 END) AS flagged_count
            FROM assessments a
            LEFT JOIN assessment_submissions asub ON asub.assessment_id = a.id
            WHERE a.unit_id = ? AND a.lecturer_id = ?
            GROUP BY a.id
            ORDER BY a.created_at DESC
        ");
        $stmt->bind_param("ii", $unit_id, $lecturer_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $assessments[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Submissions list for selected assessment
$submissions = [];
if ($assessment_id) {
    try {
        $stmt = $conn->prepare("
            SELECT asub.id, asub.score, asub.status, asub.submitted_at,
                   u.id AS student_id, u.name AS student_name,
                   (SELECT COUNT(*) FROM exam_violations WHERE submission_id = asub.id) AS vcount
            FROM assessment_submissions asub
            JOIN users u ON asub.student_id = u.id
            WHERE asub.assessment_id = ?
            ORDER BY asub.submitted_at ASC
        ");
        $stmt->bind_param("i", $assessment_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $submissions[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Current submission detail
$current_sub   = null;
$sub_answers   = [];
$sub_questions = [];
if ($submission_id) {
    try {
        $stmt = $conn->prepare("
            SELECT asub.*, u.name AS student_name, a.title AS assess_title, a.total_marks, a.pass_mark, a.type
            FROM assessment_submissions asub
            JOIN users u ON asub.student_id = u.id
            JOIN assessments a ON asub.assessment_id = a.id
            WHERE asub.id = ?
        ");
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $current_sub = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current_sub) {
            $assessment_id = $current_sub['assessment_id'];
            $stmt = $conn->prepare("
                SELECT sa.*, aq.question_text, aq.question_type, aq.marks, aq.auto_grade, aq.position
                FROM submission_answers sa
                JOIN assessment_questions aq ON sa.question_id = aq.id
                WHERE sa.submission_id = ?
                ORDER BY aq.position ASC
            ");
            $stmt->bind_param("i", $submission_id);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) $sub_answers[] = $row;
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grading Centre — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
    --bg:#0c0e14;--surf:#13161f;--surf2:#191d2b;--surf3:#20263a;
    --border:#252d44;--accent:#5b8dee;--green:#34d399;--amber:#fbbf24;--red:#f87171;--purple:#a78bfa;--cyan:#22d3ee;
    --text:#e4e8f5;--muted:#64748b;--dim:#3a4260;
    --r:11px;--rs:6px;--tr:.15s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 28px;height:54px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--accent)}
.brand span{color:var(--muted);font-weight:400;font-size:.8rem;margin-left:8px}
.nav-links{display:flex;gap:8px}
.btn-nav{background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:5px 12px;border-radius:var(--rs);font-size:.77rem;cursor:pointer;text-decoration:none;transition:var(--tr);font-family:'DM Sans',sans-serif}
.btn-nav:hover{color:var(--text);background:var(--surf2)}

.layout{display:flex;height:calc(100vh - 54px)}

/* SIDEBAR */
.sidebar{width:260px;min-width:260px;background:var(--surf);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.sb-top{padding:14px 14px 10px;border-bottom:1px solid var(--border);flex-shrink:0}
.sb-label{font-family:'Syne',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);display:block;margin-bottom:6px}
.styled-select{width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--text);padding:8px 28px 8px 10px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.sb-list{flex:1;overflow-y:auto;padding:10px}

.assess-item{display:flex;flex-direction:column;gap:5px;padding:10px 12px;border-radius:var(--rs);cursor:pointer;transition:var(--tr);border:1px solid transparent;text-decoration:none;color:var(--text);margin-bottom:4px}
.assess-item:hover{background:var(--surf2);border-color:var(--border)}
.assess-item.active{background:rgba(91,141,238,.08);border-color:rgba(91,141,238,.25)}
.ai-top{display:flex;align-items:center;gap:8px}
.ai-title{font-size:.84rem;font-weight:500;flex:1}
.type-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.dot-quiz{background:var(--accent)}.dot-assignment{background:var(--green)}.dot-cat{background:var(--amber)}.dot-exam{background:var(--red)}
.ai-stats{display:flex;gap:8px;font-size:.72rem}
.stat-chip{padding:1px 7px;border-radius:999px}
.chip-pending{background:rgba(251,191,36,.1);color:var(--amber);border:1px solid rgba(251,191,36,.2)}
.chip-graded{background:rgba(52,211,153,.1);color:var(--green);border:1px solid rgba(52,211,153,.2)}
.chip-flagged{background:rgba(248,113,113,.1);color:var(--red);border:1px solid rgba(248,113,113,.2)}

/* SUBMISSION LIST (MIDDLE) */
.sub-panel{width:240px;min-width:240px;background:var(--surf2);border-right:1px solid var(--border);overflow-y:auto}
.sub-panel-head{padding:12px 14px;border-bottom:1px solid var(--border);font-family:'Syne',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim)}
.sub-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:var(--tr);text-decoration:none;color:var(--text)}
.sub-item:hover{background:var(--surf3)}
.sub-item.active{background:rgba(91,141,238,.08)}
.sub-avatar{width:30px;height:30px;border-radius:50%;background:var(--surf3);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:.75rem;font-weight:700;color:var(--accent);flex-shrink:0}
.sub-name{font-size:.84rem;font-weight:500;flex:1}
.sub-status-dot{width:8px;height:8px;border-radius:50%}
.dot-submitted{background:var(--amber)}.dot-graded{background:var(--green)}.dot-flagged{background:var(--red)}

/* GRADING AREA */
.grade-area{flex:1;overflow-y:auto;padding:24px 28px;display:flex;flex-direction:column;gap:18px}

.grade-header{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:16px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.gh-name{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700}
.gh-meta{font-size:.8rem;color:var(--muted);display:flex;gap:14px;flex-wrap:wrap;margin-top:4px}
.status-badge{font-size:.72rem;padding:3px 10px;border-radius:999px;font-weight:600;margin-left:auto}
.sb-submitted{background:rgba(251,191,36,.1);color:var(--amber);border:1px solid rgba(251,191,36,.2)}
.sb-graded{background:rgba(52,211,153,.1);color:var(--green);border:1px solid rgba(52,211,153,.2)}
.sb-flagged{background:rgba(248,113,113,.1);color:var(--red);border:1px solid rgba(248,113,113,.2)}

.answer-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.ac-header{background:var(--surf2);padding:10px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)}
.q-badge{font-family:'JetBrains Mono',monospace;font-size:.68rem;background:rgba(91,141,238,.1);color:var(--accent);border:1px solid rgba(91,141,238,.2);padding:2px 8px;border-radius:999px}
.q-type-tag{font-size:.67rem;padding:2px 7px;border-radius:999px;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.tag-mcq{background:rgba(91,141,238,.12);color:var(--accent)}.tag-short_answer{background:rgba(34,211,238,.12);color:var(--cyan)}.tag-essay{background:rgba(251,191,36,.12);color:var(--amber)}.tag-file_upload{background:rgba(248,113,113,.12);color:var(--red)}.tag-true_false{background:rgba(52,211,153,.12);color:var(--green)}.tag-matching{background:rgba(167,139,250,.12);color:var(--purple)}
.q-marks-tag{font-size:.72rem;color:var(--muted);margin-left:auto}
.auto-chip{font-size:.65rem;padding:1px 6px;border-radius:999px;background:rgba(52,211,153,.08);color:var(--green);border:1px solid rgba(52,211,153,.2)}
.manual-chip{font-size:.65rem;padding:1px 6px;border-radius:999px;background:rgba(251,191,36,.08);color:var(--amber);border:1px solid rgba(251,191,36,.2)}
.ac-body{padding:14px 16px;display:flex;flex-direction:column;gap:12px}
.question-text{font-size:.9rem;color:var(--muted);font-style:italic;padding-bottom:8px;border-bottom:1px solid var(--dim)}
.answer-box{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);padding:12px 14px;font-size:.88rem;line-height:1.6;white-space:pre-wrap}
.answer-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--dim);margin-bottom:5px}
.correct-indicator{display:inline-flex;align-items:center;gap:5px;font-size:.78rem;padding:4px 10px;border-radius:999px}
.ind-correct{background:rgba(52,211,153,.1);color:var(--green);border:1px solid rgba(52,211,153,.2)}
.ind-wrong{background:rgba(248,113,113,.1);color:var(--red);border:1px solid rgba(248,113,113,.2)}

/* MARKS INPUT */
.marks-input-row{display:flex;align-items:center;gap:10px}
.marks-input-row label{font-size:.78rem;color:var(--muted)}
.marks-fi{width:80px;background:var(--surf3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.88rem;outline:none}
.marks-fi:focus{border-color:var(--accent)}
.marks-max{font-size:.78rem;color:var(--dim)}

/* FEEDBACK */
.feedback-ta{width:100%;background:var(--surf3);border:1px solid var(--border);color:var(--text);padding:10px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.85rem;resize:vertical;min-height:60px;outline:none}
.feedback-ta:focus{border-color:var(--accent)}

/* FINAL SCORE + SAVE */
.final-bar{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:14px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;position:sticky;bottom:0}
.final-total{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:800}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:500;cursor:pointer;border:none;transition:var(--tr)}
.btn-success{background:var(--green);color:#052e16}.btn-success:hover{background:#2ec489}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:#4a7de0}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:5px 11px;font-size:.78rem}
.btn:disabled{opacity:.4;cursor:not-allowed}

/* PLACEHOLDER */
.placeholder{flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;color:var(--dim)}
.placeholder i{font-size:2.5rem;opacity:.25}
.placeholder h3{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;color:var(--muted)}

/* TOAST */
#toast{position:fixed;bottom:60px;right:22px;z-index:999;display:flex;flex-direction:column;gap:7px;pointer-events:none}
.toast-item{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);padding:9px 14px;font-size:.82rem;color:var(--text);box-shadow:0 4px 20px rgba(0,0,0,.4);display:flex;align-items:center;gap:8px;animation:toastIn .2s ease,toastOut .2s ease 2.6s forwards}
.toast-item.success{border-left:3px solid var(--green)}.toast-item.error{border-left:3px solid var(--red)}.toast-item.info{border-left:3px solid var(--accent)}
@keyframes toastIn{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:translateX(0)}}
@keyframes toastOut{from{opacity:1}to{opacity:0;transform:translateX(14px)}}

::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--surf3);border-radius:2px}
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">UNILIS <span>Grading Centre</span></div>
    <div class="nav-links">
        <?php if ($unit_id): ?>
        <a href="assessment_builder.php?unit_id=<?= $unit_id ?>" class="btn-nav"><i class="fas fa-tasks"></i> Assessments</a>
        <a href="exam_reports.php?unit_id=<?= $unit_id ?>"       class="btn-nav"><i class="fas fa-shield-halved"></i> Exam Reports</a>
        <?php endif; ?>
        <a href="../dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- SIDEBAR: ASSESSMENTS -->
    <aside class="sidebar">
        <div class="sb-top">
            <span class="sb-label">Unit</span>
            <select class="styled-select" onchange="window.location.href='grading_centre.php?unit_id='+this.value">
                <option value="">— select —</option>
                <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sb-list">
        <?php if (empty($assessments)): ?>
            <p style="font-size:.8rem;color:var(--dim);padding:12px 4px"><?= $unit_id ? 'No assessments found.' : 'Select a unit.' ?></p>
        <?php else: ?>
            <?php foreach ($assessments as $a): ?>
            <a class="assess-item <?= $assessment_id == $a['id'] ? 'active' : '' ?>"
               href="grading_centre.php?unit_id=<?= $unit_id ?>&assessment_id=<?= $a['id'] ?>">
                <div class="ai-top">
                    <span class="type-dot dot-<?= $a['type'] ?>"></span>
                    <span class="ai-title"><?= htmlspecialchars($a['title']) ?></span>
                </div>
                <div class="ai-stats">
                    <?php if ($a['pending_grade']): ?><span class="stat-chip chip-pending"><?= $a['pending_grade'] ?> pending</span><?php endif; ?>
                    <?php if ($a['graded_count']): ?><span class="stat-chip chip-graded"><?= $a['graded_count'] ?> graded</span><?php endif; ?>
                    <?php if ($a['flagged_count']): ?><span class="stat-chip chip-flagged"><?= $a['flagged_count'] ?> flagged</span><?php endif; ?>
                    <?php if (!$a['pending_grade'] && !$a['graded_count'] && !$a['flagged_count']): ?><span style="font-size:.72rem;color:var(--dim)">No submissions</span><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </aside>

    <!-- SUBMISSION LIST -->
    <?php if ($assessment_id && !empty($submissions)): ?>
    <div class="sub-panel">
        <div class="sub-panel-head">Submissions (<?= count($submissions) ?>)</div>
        <?php foreach ($submissions as $sub): ?>
        <a class="sub-item <?= $submission_id == $sub['id'] ? 'active' : '' ?>"
           href="grading_centre.php?unit_id=<?= $unit_id ?>&assessment_id=<?= $assessment_id ?>&submission_id=<?= $sub['id'] ?>">
            <div class="sub-avatar"><?= strtoupper(substr($sub['student_name'],0,1)) ?></div>
            <div style="flex:1;min-width:0">
                <div class="sub-name"><?= htmlspecialchars($sub['student_name']) ?></div>
                <div style="font-size:.73rem;color:var(--muted)">
                    <?= date('d M, H:i', strtotime($sub['submitted_at'])) ?>
                    <?php if ($sub['vcount'] > 0): ?>
                        &nbsp;<span style="color:var(--red)"><i class="fas fa-triangle-exclamation"></i> <?= $sub['vcount'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sub-status-dot dot-<?= $sub['status'] ?>"></div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php elseif ($assessment_id): ?>
    <div class="sub-panel">
        <div class="sub-panel-head">Submissions</div>
        <div style="padding:24px;color:var(--dim);font-size:.83rem;text-align:center">No submissions yet.</div>
    </div>
    <?php endif; ?>

    <!-- GRADING AREA -->
    <?php if ($current_sub): ?>
    <div class="grade-area" id="grade-area">

        <!-- SUBMISSION HEADER -->
        <div class="grade-header">
            <div class="sub-avatar" style="width:40px;height:40px;font-size:.9rem"><?= strtoupper(substr($current_sub['student_name'],0,1)) ?></div>
            <div>
                <div class="gh-name"><?= htmlspecialchars($current_sub['student_name']) ?></div>
                <div class="gh-meta">
                    <span><i class="fas fa-file-alt"></i> <?= htmlspecialchars($current_sub['assess_title']) ?></span>
                    <span><i class="fas fa-clock"></i> <?= date('d M Y, H:i', strtotime($current_sub['submitted_at'])) ?></span>
                    <span><i class="fas fa-star"></i> Total: <?= $current_sub['total_marks'] ?> marks</span>
                </div>
            </div>
            <span class="status-badge sb-<?= $current_sub['status'] ?>"><?= ucfirst($current_sub['status']) ?></span>
        </div>

        <!-- ANSWERS -->
        <?php $manual_total = 0; $auto_total = 0; ?>
        <?php foreach ($sub_answers as $idx => $ans): ?>
        <?php
            $is_auto   = $ans['auto_grade'];
            $awarded   = $ans['marks_awarded'];
            $is_correct = $ans['is_correct'];
        ?>
        <div class="answer-card">
            <div class="ac-header">
                <span class="q-badge">Q<?= $idx + 1 ?></span>
                <span class="q-type-tag tag-<?= $ans['question_type'] ?>"><?= str_replace('_',' ', $ans['question_type']) ?></span>
                <?php if ($is_auto): ?>
                    <span class="auto-chip"><i class="fas fa-bolt"></i> Auto</span>
                <?php else: ?>
                    <span class="manual-chip"><i class="fas fa-user-pen"></i> Manual</span>
                <?php endif; ?>
                <span class="q-marks-tag"><?= $ans['marks'] ?> mark<?= $ans['marks']!=1?'s':'' ?></span>
            </div>
            <div class="ac-body">
                <div class="question-text"><?= htmlspecialchars($ans['question_text']) ?></div>

                <?php if ($ans['question_type'] === 'essay' || $ans['question_type'] === 'short_answer'): ?>
                    <div>
                        <div class="answer-label">Student Answer</div>
                        <div class="answer-box"><?= nl2br(htmlspecialchars($ans['answer_text'] ?? '(no answer)')) ?></div>
                    </div>
                <?php elseif ($ans['question_type'] === 'file_upload'): ?>
                    <div>
                        <div class="answer-label">Submitted File</div>
                        <?php if ($ans['file_path']): ?>
                            <a href="../<?= htmlspecialchars($ans['file_path']) ?>" target="_blank"
                               class="btn btn-ghost btn-sm"><i class="fas fa-download"></i> Download File</a>
                        <?php else: ?>
                            <span style="color:var(--dim);font-size:.84rem">No file submitted</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Auto-graded: show result -->
                    <?php if ($is_correct !== null): ?>
                    <span class="correct-indicator <?= $is_correct ? 'ind-correct' : 'ind-wrong' ?>">
                        <i class="fas <?= $is_correct ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                        <?= $is_correct ? 'Correct' : 'Incorrect' ?>
                        — <?= $awarded ?> / <?= $ans['marks'] ?> marks
                    </span>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Manual marks input -->
                <?php if (!$is_auto): ?>
                <div class="marks-input-row">
                    <label>Marks Awarded:</label>
                    <input type="number" class="marks-fi manual-marks-input"
                           id="marks-<?= $ans['question_id'] ?>"
                           data-qid="<?= $ans['question_id'] ?>"
                           data-max="<?= $ans['marks'] ?>"
                           value="<?= $awarded ?? '' ?>"
                           min="0" max="<?= $ans['marks'] ?>"
                           step="0.5"
                           oninput="updateTotal()">
                    <span class="marks-max">/ <?= $ans['marks'] ?></span>
                </div>
                <div>
                    <div class="answer-label" style="margin-bottom:5px">Feedback (optional)</div>
                    <textarea class="feedback-ta"
                              id="fb-<?= $ans['question_id'] ?>"
                              data-qid="<?= $ans['question_id'] ?>"
                              placeholder="Comments for student..."><?= htmlspecialchars($ans['feedback'] ?? '') ?></textarea>
                </div>
                <?php else: ?>
                    <?php $auto_total += ($awarded ?? 0); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- FINAL SCORE BAR -->
        <div class="final-bar">
            <div>
                <div style="font-size:.72rem;color:var(--dim);text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px">Total Score</div>
                <div class="final-total">
                    <span id="computed-score" style="color:var(--accent)">
                        <?= $current_sub['score'] !== null ? round($current_sub['score']) . '%' : '—' ?>
                    </span>
                    &nbsp;<span style="color:var(--dim);font-size:.85rem;font-weight:400">
                        Pass: <?= $current_sub['pass_mark'] ?>%
                    </span>
                </div>
            </div>

            <div style="margin-left:auto;display:flex;gap:8px">
                <button class="btn btn-success" onclick="submitGrades()">
                    <i class="fas fa-check-circle"></i> Save & Finalise Grades
                </button>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="placeholder">
        <i class="fas fa-pen-nib"></i>
        <h3><?= $assessment_id ? 'Select a Submission' : 'Select an Assessment' ?></h3>
        <p style="font-size:.82rem;max-width:220px;text-align:center"><?= $assessment_id ? 'Pick a student from the list.' : 'Choose an assessment from the sidebar.' ?></p>
    </div>
    <?php endif; ?>

</div>

<div id="toast"></div>

<script>
const SUBMISSION_ID  = <?= $submission_id ?: 'null' ?>;
const UNIT_ID        = <?= $unit_id ?: 'null' ?>;
const ASSESSMENT_ID  = <?= $assessment_id ?: 'null' ?>;
const AUTO_TOTAL     = <?= $auto_total ?? 0 ?>;
const TOTAL_MARKS    = <?= $current_sub['total_marks'] ?? 0 ?>;

function updateTotal() {
    let manual = 0;
    document.querySelectorAll('.manual-marks-input').forEach(inp => {
        const val = parseFloat(inp.value) || 0;
        const max = parseFloat(inp.dataset.max) || 0;
        manual += Math.min(val, max);
    });
    const rawScore = AUTO_TOTAL + manual;
    const pct      = TOTAL_MARKS > 0 ? Math.round((rawScore / TOTAL_MARKS) * 100) : 0;
    const el = document.getElementById('computed-score');
    if (el) el.textContent = pct + '%';
}

function submitGrades() {
    const answers = [];
    document.querySelectorAll('.manual-marks-input').forEach(inp => {
        answers.push({
            question_id:   parseInt(inp.dataset.qid),
            marks_awarded: parseFloat(inp.value) || 0,
            feedback:      document.getElementById('fb-' + inp.dataset.qid)?.value || ''
        });
    });

    let manual = 0;
    answers.forEach(a => manual += a.marks_awarded);
    const rawScore = AUTO_TOTAL + manual;
    const pct      = TOTAL_MARKS > 0 ? ((rawScore / TOTAL_MARKS) * 100).toFixed(2) : 0;

    const fd = new FormData();
    fd.append('submission_id',  SUBMISSION_ID);
    fd.append('answers',        JSON.stringify(answers));
    fd.append('final_score',    pct);
    fd.append('unit_id',        UNIT_ID);

    fetch('ajax/grade_submission.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toast('Grades saved successfully', 'success');
                setTimeout(() => window.location.reload(), 1200);
            } else toast(d.message, 'error');
        })
        .catch(() => toast('Save failed', 'error'));
}

function toast(msg, type = 'info') {
    const c = document.getElementById('toast');
    const e = document.createElement('div');
    e.className = `toast-item ${type}`;
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info' };
    e.innerHTML = `<i class="fas ${icons[type]||'fa-circle-info'}"></i> ${msg}`;
    c.appendChild(e);
    setTimeout(() => e.remove(), 2900);
}

// Init total
updateTotal();
</script>
</body>
</html>
