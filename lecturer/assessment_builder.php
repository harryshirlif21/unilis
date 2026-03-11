<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];
$unit_id       = intval($_GET['unit_id']       ?? 0);
$assessment_id = intval($_GET['assessment_id'] ?? 0);

// Fetch units
$units = [];
try {
    $stmt = $conn->prepare("SELECT u.id, u.name FROM units u JOIN lecturer_units lu ON u.id = lu.unit_id WHERE lu.lecturer_id = ? ORDER BY u.name");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Fetch assessments for this unit
$assessments = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("SELECT id, title, type, is_published, total_marks, due_date, created_at FROM assessments WHERE unit_id = ? AND lecturer_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("ii", $unit_id, $lecturer_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $assessments[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Fetch assessment + questions if editing
$current_assessment = null;
$questions          = [];
if ($assessment_id && $unit_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM assessments WHERE id = ? AND unit_id = ? AND lecturer_id = ?");
        $stmt->bind_param("iii", $assessment_id, $unit_id, $lecturer_id);
        $stmt->execute();
        $current_assessment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current_assessment) {
            $stmt = $conn->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY position ASC, id ASC");
            $stmt->bind_param("i", $assessment_id);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                // Fetch options for this question
                $row['options'] = [];
                $os = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY position ASC");
                $os->bind_param("i", $row['id']);
                $os->execute();
                $or = $os->get_result();
                while ($opt = $or->fetch_assoc()) $row['options'][] = $opt;
                $os->close();
                $questions[] = $row;
            }
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Fetch modules for the context selector
$modules = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("SELECT id, title FROM course_modules WHERE unit_id = ? AND lecturer_id = ? ORDER BY position ASC");
        $stmt->bind_param("ii", $unit_id, $lecturer_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $modules[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assessment Builder — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg:        #0c0e14;
    --surf:      #13161f;
    --surf2:     #191d2b;
    --surf3:     #20263a;
    --border:    #252d44;
    --accent:    #5b8dee;
    --green:     #34d399;
    --amber:     #fbbf24;
    --red:       #f87171;
    --purple:    #a78bfa;
    --cyan:      #22d3ee;
    --text:      #e4e8f5;
    --muted:     #64748b;
    --dim:       #3a4260;
    --r:         11px;
    --rs:        6px;
    --tr:        0.15s ease;
    /* Assessment type colours */
    --quiz:   #5b8dee;
    --assign: #34d399;
    --cat:    #fbbf24;
    --exam:   #f87171;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* TOPBAR */
.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 28px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--accent);letter-spacing:.04em}
.brand span{color:var(--muted);font-weight:400;font-size:.8rem;margin-left:8px}
.nav-links{display:flex;gap:8px}
.btn-nav{background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:5px 12px;border-radius:var(--rs);font-size:.78rem;cursor:pointer;text-decoration:none;transition:var(--tr);font-family:'DM Sans',sans-serif}
.btn-nav:hover{color:var(--text);background:var(--surf2)}

/* LAYOUT */
.layout{display:flex;height:calc(100vh - 56px)}

/* LEFT SIDEBAR */
.sidebar{width:290px;min-width:290px;background:var(--surf);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.sb-top{padding:16px 16px 12px;border-bottom:1px solid var(--border);flex-shrink:0}
.sb-label{font-family:'Syne',sans-serif;font-size:.67rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);display:block;margin-bottom:7px}
.styled-select{width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--text);padding:9px 28px 9px 11px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.84rem;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.styled-select:focus{border-color:var(--accent)}

.sb-list{flex:1;overflow-y:auto;padding:10px 10px 16px}
.sb-new-btn{display:flex;align-items:center;gap:8px;width:100%;padding:9px 12px;background:transparent;border:1px dashed var(--border);border-radius:var(--rs);color:var(--muted);font-size:.82rem;cursor:pointer;transition:var(--tr);margin-bottom:10px;font-family:'DM Sans',sans-serif}
.sb-new-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(91,141,238,.06)}

.assess-item{display:flex;align-items:center;gap:8px;padding:9px 11px;border-radius:var(--rs);cursor:pointer;transition:var(--tr);border:1px solid transparent;margin-bottom:3px;text-decoration:none;color:var(--text)}
.assess-item:hover{background:var(--surf2);border-color:var(--border)}
.assess-item.active{background:rgba(91,141,238,.1);border-color:rgba(91,141,238,.3);color:var(--accent)}
.type-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.dot-quiz{background:var(--quiz)}.dot-assignment{background:var(--assign)}.dot-cat{background:var(--cat)}.dot-exam{background:var(--exam)}
.assess-title{flex:1;font-size:.83rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.assess-meta{font-size:.7rem;color:var(--muted)}
.pub-badge{font-size:.65rem;padding:2px 6px;border-radius:999px;font-weight:600}
.pub-yes{background:rgba(52,211,153,.12);color:var(--green);border:1px solid rgba(52,211,153,.25)}
.pub-no{background:rgba(100,116,139,.1);color:var(--muted);border:1px solid var(--border)}

/* MAIN */
.main{flex:1;overflow-y:auto;display:flex;flex-direction:column}

/* PLACEHOLDER */
.placeholder{flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;color:var(--dim)}
.placeholder i{font-size:2.8rem;opacity:.3}
.placeholder h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--muted)}
.placeholder p{font-size:.83rem;max-width:280px;text-align:center}

/* ASSESSMENT EDITOR AREA */
.editor-wrap{flex:1;overflow-y:auto;padding:28px 32px;max-width:900px;width:100%;margin:0 auto;display:flex;flex-direction:column;gap:22px}

/* SECTION CARD */
.card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.card-header{background:var(--surf2);padding:13px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)}
.card-header h3{font-family:'Syne',sans-serif;font-size:.88rem;font-weight:700;color:var(--text);letter-spacing:.03em}
.card-header .badge{font-size:.68rem;padding:2px 8px;border-radius:999px;font-weight:600;margin-left:auto}
.card-body{padding:18px 20px}

/* FORM */
.fg{margin-bottom:14px}
.fg label{display:block;font-size:.76rem;font-weight:500;color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.07em}
.fi,.fta,.fsel{width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--text);padding:9px 13px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.87rem;outline:none;transition:border-color var(--tr)}
.fi:focus,.fta:focus,.fsel:focus{border-color:var(--accent)}
.fta{resize:vertical;min-height:80px}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.frow3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:500;cursor:pointer;border:none;transition:var(--tr);text-decoration:none}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:#4a7de0;transform:translateY(-1px)}
.btn-success{background:var(--green);color:#052e16}.btn-success:hover{background:#2ec489}
.btn-amber{background:var(--amber);color:#1c1000}.btn-amber:hover{background:#f0b11e}
.btn-danger{background:var(--red);color:#fff}.btn-danger:hover{background:#e06060}
.btn-purple{background:var(--purple);color:#1a1030}.btn-purple:hover{background:#9575e8}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:5px 11px;font-size:.78rem}
.btn-xs{padding:3px 8px;font-size:.73rem}
.btn:disabled{opacity:.4;cursor:not-allowed;transform:none!important}

/* TYPE SELECTOR */
.type-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:4px}
.type-card{border:1px solid var(--border);border-radius:var(--rs);padding:10px 8px;text-align:center;cursor:pointer;transition:var(--tr);background:var(--surf2)}
.type-card:hover{border-color:var(--muted)}
.type-card.selected{border-width:2px}
.type-card[data-val="quiz"].selected{border-color:var(--quiz);background:rgba(91,141,238,.08)}
.type-card[data-val="assignment"].selected{border-color:var(--assign);background:rgba(52,211,153,.08)}
.type-card[data-val="cat"].selected{border-color:var(--cat);background:rgba(251,191,36,.08)}
.type-card[data-val="exam"].selected{border-color:var(--exam);background:rgba(248,113,113,.08)}
.type-card i{font-size:1.2rem;display:block;margin-bottom:5px}
.type-card span{font-size:.75rem;font-weight:600;font-family:'Syne',sans-serif;letter-spacing:.05em;text-transform:uppercase}
.type-card[data-val="quiz"]   i,.type-card[data-val="quiz"]   span{color:var(--quiz)}
.type-card[data-val="assignment"] i,.type-card[data-val="assignment"] span{color:var(--assign)}
.type-card[data-val="cat"]    i,.type-card[data-val="cat"]    span{color:var(--cat)}
.type-card[data-val="exam"]   i,.type-card[data-val="exam"]   span{color:var(--exam)}

/* QUESTION CARD */
.q-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);margin-bottom:12px;overflow:hidden;transition:var(--tr);animation:fadeIn .2s ease}
.q-card.dragging{opacity:.3}
.q-card.drag-over{border-color:var(--accent);box-shadow:0 0 0 2px rgba(91,141,238,.2)}
.q-header{background:var(--surf2);padding:11px 14px;display:flex;align-items:center;gap:10px;cursor:grab;border-bottom:1px solid var(--border);user-select:none}
.q-header:active{cursor:grabbing}
.q-num{font-family:'JetBrains Mono',monospace;font-size:.68rem;color:var(--accent);background:rgba(91,141,238,.1);border:1px solid rgba(91,141,238,.2);padding:2px 8px;border-radius:999px;white-space:nowrap}
.q-type-tag{font-size:.68rem;padding:2px 8px;border-radius:999px;font-weight:600;text-transform:uppercase;letter-spacing:.06em}
.tag-mcq{background:rgba(91,141,238,.12);color:var(--accent);border:1px solid rgba(91,141,238,.25)}
.tag-true_false{background:rgba(52,211,153,.12);color:var(--green);border:1px solid rgba(52,211,153,.25)}
.tag-matching{background:rgba(167,139,250,.12);color:var(--purple);border:1px solid rgba(167,139,250,.25)}
.tag-short_answer{background:rgba(34,211,238,.12);color:var(--cyan);border:1px solid rgba(34,211,238,.25)}
.tag-essay{background:rgba(251,191,36,.12);color:var(--amber);border:1px solid rgba(251,191,36,.25)}
.tag-file_upload{background:rgba(248,113,113,.12);color:var(--red);border:1px solid rgba(248,113,113,.25)}
.auto-badge{font-size:.65rem;padding:2px 7px;border-radius:999px}
.auto-yes{background:rgba(52,211,153,.1);color:var(--green);border:1px solid rgba(52,211,153,.2)}
.auto-no{background:rgba(251,191,36,.1);color:var(--amber);border:1px solid rgba(251,191,36,.2)}
.q-marks{font-family:'JetBrains Mono',monospace;font-size:.75rem;color:var(--muted);margin-left:auto}
.q-actions{display:flex;gap:4px}
.q-body{padding:14px 16px;display:flex;flex-direction:column;gap:12px}

/* OPTIONS */
.options-list{display:flex;flex-direction:column;gap:7px}
.opt-row{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--border);border-radius:var(--rs);background:var(--surf2);transition:border-color var(--tr)}
.opt-row.correct-opt{border-color:var(--green);background:rgba(52,211,153,.06)}
.opt-radio{accent-color:var(--green);width:15px;height:15px;cursor:pointer;flex-shrink:0}
.opt-text-input{flex:1;background:transparent;border:none;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.86rem;outline:none}
.opt-text-input::placeholder{color:var(--dim)}
.match-pair-input{width:160px;background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:4px 9px;border-radius:var(--rs);font-size:.8rem;outline:none}

/* Add option row */
.add-opt-row{display:flex;align-items:center;gap:8px;padding:6px 10px;border:1px dashed var(--border);border-radius:var(--rs);cursor:pointer;color:var(--dim);font-size:.8rem;transition:var(--tr)}
.add-opt-row:hover{border-color:var(--green);color:var(--green);background:rgba(52,211,153,.04)}

/* MARKS SUMMARY BAR */
.marks-bar{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);padding:12px 16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.marks-seg{text-align:center}
.marks-num{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:var(--accent)}
.marks-lbl{font-size:.7rem;color:var(--dim);text-transform:uppercase;letter-spacing:.08em}

/* ACTION BAR */
.action-bar{background:var(--surf);border-top:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;position:sticky;bottom:0}

/* ADD Q BUTTONS */
.add-q-grid{display:flex;flex-wrap:wrap;gap:8px}
.add-q-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.79rem;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surf2);color:var(--muted);transition:var(--tr)}
.add-q-btn:hover{transform:translateY(-1px)}
.add-q-btn[data-qtype="mcq"]:hover         {border-color:var(--accent);color:var(--accent);background:rgba(91,141,238,.08)}
.add-q-btn[data-qtype="true_false"]:hover  {border-color:var(--green);color:var(--green);background:rgba(52,211,153,.08)}
.add-q-btn[data-qtype="matching"]:hover    {border-color:var(--purple);color:var(--purple);background:rgba(167,139,250,.08)}
.add-q-btn[data-qtype="short_answer"]:hover{border-color:var(--cyan);color:var(--cyan);background:rgba(34,211,238,.08)}
.add-q-btn[data-qtype="essay"]:hover       {border-color:var(--amber);color:var(--amber);background:rgba(251,191,36,.08)}
.add-q-btn[data-qtype="file_upload"]:hover {border-color:var(--red);color:var(--red);background:rgba(248,113,113,.08)}

/* TOAST */
#toast{position:fixed;bottom:70px;right:24px;z-index:999;display:flex;flex-direction:column;gap:7px;pointer-events:none}
.toast-item{background:var(--surf2);border:1px solid var(--border);border-radius:var(--rs);padding:9px 15px;font-size:.82rem;color:var(--text);box-shadow:0 4px 20px rgba(0,0,0,.4);display:flex;align-items:center;gap:8px;animation:toastIn .2s ease,toastOut .2s ease 2.6s forwards;max-width:290px}
.toast-item.success{border-left:3px solid var(--green)}
.toast-item.error{border-left:3px solid var(--red)}
.toast-item.info{border-left:3px solid var(--accent)}
@keyframes toastIn{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:translateX(0)}}
@keyframes toastOut{from{opacity:1}to{opacity:0;transform:translateX(14px)}}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.spinner{width:13px;height:13px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}

/* MODAL */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s ease}
.overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:26px 30px;width:520px;max-width:92vw;box-shadow:0 8px 40px rgba(0,0,0,.5);transform:translateY(10px);transition:transform .2s ease}
.overlay.open .modal{transform:translateY(0)}
.modal h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:18px}
.modal-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:18px}

::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--surf3);border-radius:2px}
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">UNILIS <span>Assessment Builder</span></div>
    <div class="nav-links">
        <?php if ($unit_id): ?>
        <a href="course_builder.php?unit_id=<?= $unit_id ?>" class="btn-nav"><i class="fas fa-sitemap"></i> Course Builder</a>
        <a href="lesson_editor.php?unit_id=<?= $unit_id ?>"  class="btn-nav"><i class="fas fa-pen-nib"></i> Lesson Editor</a>
        <?php endif; ?>
        <a href="../dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sb-top">
            <span class="sb-label"><i class="fas fa-book"></i> &nbsp;Unit</span>
            <select class="styled-select" id="unit-sel" onchange="switchUnit(this.value)">
                <option value="">— select unit —</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sb-list" id="sb-list">
            <?php if (!$unit_id): ?>
                <p style="font-size:.8rem;color:var(--dim);padding:16px 8px">Select a unit to view assessments.</p>
            <?php else: ?>
                <button class="sb-new-btn" onclick="openNewAssessmentModal()">
                    <i class="fas fa-plus-circle"></i> New Assessment
                </button>
                <?php if (empty($assessments)): ?>
                    <p style="font-size:.8rem;color:var(--dim);padding:8px">No assessments yet.</p>
                <?php else: ?>
                    <?php foreach ($assessments as $a): ?>
                        <a class="assess-item <?= $assessment_id == $a['id'] ? 'active' : '' ?>"
                           href="assessment_builder.php?unit_id=<?= $unit_id ?>&assessment_id=<?= $a['id'] ?>">
                            <span class="type-dot dot-<?= $a['type'] ?>"></span>
                            <span class="assess-title"><?= htmlspecialchars($a['title']) ?></span>
                            <span class="pub-badge <?= $a['is_published'] ? 'pub-yes' : 'pub-no' ?>">
                                <?= $a['is_published'] ? 'Live' : 'Draft' ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main" id="main">

        <?php if (!$current_assessment): ?>
        <div class="placeholder">
            <i class="fas fa-tasks"></i>
            <h3><?= $unit_id ? 'No Assessment Selected' : 'No Unit Selected' ?></h3>
            <p><?= $unit_id ? 'Pick an assessment from the sidebar or create a new one.' : 'Select a unit from the sidebar to begin.' ?></p>
            <?php if ($unit_id): ?>
            <button class="btn btn-primary" onclick="openNewAssessmentModal()" style="margin-top:8px">
                <i class="fas fa-plus"></i> New Assessment
            </button>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <div class="editor-wrap" id="editor-wrap">

            <!-- HEADER CARD -->
            <div class="card">
                <div class="card-header">
                    <span class="type-dot dot-<?= $current_assessment['type'] ?>" style="width:10px;height:10px"></span>
                    <h3 id="assess-title-display"><?= htmlspecialchars($current_assessment['title']) ?></h3>
                    <span class="badge <?= $current_assessment['is_published'] ? 'pub-yes' : 'pub-no' ?>" id="pub-badge">
                        <?= $current_assessment['is_published'] ? '● Live' : '○ Draft' ?>
                    </span>
                    <button class="btn btn-ghost btn-sm" style="margin-left:8px" onclick="openEditDetailsModal()">
                        <i class="fas fa-edit"></i> Edit Details
                    </button>
                    <button class="btn btn-sm <?= $current_assessment['is_published'] ? 'btn-danger' : 'btn-success' ?>"
                            onclick="togglePublish()" id="pub-btn">
                        <?= $current_assessment['is_published'] ? '<i class="fas fa-eye-slash"></i> Unpublish' : '<i class="fas fa-eye"></i> Publish' ?>
                    </button>
                </div>
                <div class="card-body">
                    <!-- Marks summary -->
                    <div class="marks-bar" id="marks-bar">
                        <div class="marks-seg">
                            <div class="marks-num" id="total-marks"><?= $current_assessment['total_marks'] ?></div>
                            <div class="marks-lbl">Total Marks</div>
                        </div>
                        <div class="marks-seg">
                            <div class="marks-num" id="pass-marks" style="color:var(--green)"><?= $current_assessment['pass_mark'] ?></div>
                            <div class="marks-lbl">Pass Mark</div>
                        </div>
                        <div class="marks-seg">
                            <div class="marks-num" id="q-count" style="color:var(--purple)"><?= count($questions) ?></div>
                            <div class="marks-lbl">Questions</div>
                        </div>
                        <div class="marks-seg">
                            <div class="marks-num" style="color:var(--amber)"><?= $current_assessment['time_limit_mins'] ? $current_assessment['time_limit_mins'].' min' : '∞' ?></div>
                            <div class="marks-lbl">Time Limit</div>
                        </div>
                        <div class="marks-seg">
                            <div style="font-family:'Syne',sans-serif;font-size:.9rem;font-weight:700;color:var(--cyan);text-transform:uppercase"><?= $current_assessment['type'] ?></div>
                            <div class="marks-lbl">Type</div>
                        </div>
                    </div>

                    <?php if ($current_assessment['instructions']): ?>
                    <div style="margin-top:12px;padding:10px 14px;background:var(--surf2);border-radius:var(--rs);border-left:3px solid var(--accent);font-size:.85rem;color:var(--muted)">
                        <strong style="color:var(--text)">Instructions:</strong> <?= htmlspecialchars($current_assessment['instructions']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QUESTIONS -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list-check"></i> &nbsp;Questions</h3>
                    <span class="badge" style="background:rgba(91,141,238,.1);color:var(--accent);border:1px solid rgba(91,141,238,.2);font-size:.68rem;padding:2px 8px;border-radius:999px" id="q-badge"><?= count($questions) ?> question<?= count($questions)!==1?'s':'' ?></span>
                </div>
                <div class="card-body">

                    <!-- Add question buttons -->
                    <div class="add-q-grid" style="margin-bottom:16px">
                        <span style="font-family:'Syne',sans-serif;font-size:.67rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);display:flex;align-items:center">Add:</span>
                        <button class="add-q-btn" data-qtype="mcq"          onclick="addQuestion('mcq')"><i class="fas fa-list-check" style="color:var(--accent)"></i> MCQ</button>
                        <button class="add-q-btn" data-qtype="true_false"   onclick="addQuestion('true_false')"><i class="fas fa-toggle-on" style="color:var(--green)"></i> True/False</button>
                        <button class="add-q-btn" data-qtype="matching"     onclick="addQuestion('matching')"><i class="fas fa-arrows-left-right" style="color:var(--purple)"></i> Matching</button>
                        <button class="add-q-btn" data-qtype="short_answer" onclick="addQuestion('short_answer')"><i class="fas fa-keyboard" style="color:var(--cyan)"></i> Short Answer</button>
                        <button class="add-q-btn" data-qtype="essay"        onclick="addQuestion('essay')"><i class="fas fa-file-lines" style="color:var(--amber)"></i> Essay</button>
                        <button class="add-q-btn" data-qtype="file_upload"  onclick="addQuestion('file_upload')"><i class="fas fa-file-arrow-up" style="color:var(--red)"></i> File Upload</button>
                    </div>

                    <!-- Question list -->
                    <div id="questions-container">
                        <?php if (empty($questions)): ?>
                            <div id="no-questions" style="text-align:center;padding:36px;color:var(--dim);font-size:.85rem">
                                <i class="fas fa-circle-question" style="font-size:1.8rem;margin-bottom:10px;display:block;opacity:.3"></i>
                                No questions yet. Use the buttons above to add questions.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <button class="btn btn-success" onclick="saveAllQuestions()">
                <i class="fas fa-floppy-disk"></i> Save All Questions
            </button>
            <button class="btn btn-ghost" onclick="recalcTotals()">
                <i class="fas fa-calculator"></i> Recalc Totals
            </button>
            <div style="margin-left:auto;font-size:.78rem;color:var(--dim)" id="save-status-bar">
                <i class="fas fa-circle-info"></i> Questions auto-save when you click Save on each one
            </div>
        </div>

        <?php endif; ?>
    </main>
</div>

<!-- NEW ASSESSMENT MODAL -->
<div class="overlay" id="new-modal">
    <div class="modal">
        <h3><i class="fas fa-plus-circle" style="color:var(--accent)"></i> &nbsp;New Assessment</h3>

        <div class="fg">
            <label>Title</label>
            <input type="text" class="fi" id="nm-title" placeholder="e.g. Module 1 Quiz">
        </div>

        <div class="fg">
            <label>Type</label>
            <div class="type-grid">
                <div class="type-card selected" data-val="quiz" onclick="selectType(this)">
                    <i class="fas fa-circle-question"></i><span>Quiz</span>
                </div>
                <div class="type-card" data-val="assignment" onclick="selectType(this)">
                    <i class="fas fa-file-pen"></i><span>Assignment</span>
                </div>
                <div class="type-card" data-val="cat" onclick="selectType(this)">
                    <i class="fas fa-clipboard-check"></i><span>CAT</span>
                </div>
                <div class="type-card" data-val="exam" onclick="selectType(this)">
                    <i class="fas fa-graduation-cap"></i><span>Exam</span>
                </div>
            </div>
            <input type="hidden" id="nm-type" value="quiz">
        </div>

        <div class="fg">
            <label>Instructions (optional)</label>
            <textarea class="fta" id="nm-instructions" placeholder="Instructions shown to students..."></textarea>
        </div>

        <div class="frow">
            <div class="fg">
                <label>Time Limit (minutes, 0 = none)</label>
                <input type="number" class="fi" id="nm-time" value="0" min="0">
            </div>
            <div class="fg">
                <label>Due Date (optional)</label>
                <input type="datetime-local" class="fi" id="nm-due">
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('new-modal')">Cancel</button>
            <button class="btn btn-primary" id="nm-save-btn" onclick="saveNewAssessment()">
                <i class="fas fa-save"></i> Create Assessment
            </button>
        </div>
    </div>
</div>

<!-- EDIT DETAILS MODAL -->
<div class="overlay" id="edit-modal">
    <div class="modal">
        <h3><i class="fas fa-edit" style="color:var(--accent)"></i> &nbsp;Edit Assessment Details</h3>
        <div class="fg"><label>Title</label><input type="text" class="fi" id="em-title"></div>
        <div class="fg"><label>Instructions</label><textarea class="fta" id="em-instructions"></textarea></div>
        <div class="frow">
            <div class="fg"><label>Total Marks</label><input type="number" class="fi" id="em-total" min="0"></div>
            <div class="fg"><label>Pass Mark</label><input type="number" class="fi" id="em-pass" min="0"></div>
        </div>
        <div class="frow">
            <div class="fg"><label>Time Limit (mins)</label><input type="number" class="fi" id="em-time" min="0"></div>
            <div class="fg"><label>Due Date</label><input type="datetime-local" class="fi" id="em-due"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('edit-modal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveEditDetails()"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
// ─────────────────────────────────────────────────
// CONSTANTS
// ─────────────────────────────────────────────────
const UNIT_ID       = <?= $unit_id ?: 'null' ?>;
const ASSESSMENT_ID = <?= $assessment_id ?: 'null' ?>;
const LECTURER_ID   = <?= $lecturer_id ?>;

const EXISTING_QUESTIONS = <?= json_encode($questions) ?>;

// ─────────────────────────────────────────────────
// STATE
// ─────────────────────────────────────────────────
let questions  = [];   // {localId, dbId, type, marks, autoGrade, options:[{localId,dbId,text,isCorrect,matchPair}], saved}
let qCounter   = 0;
let optCounter = 0;
let dragSrcQ   = null;

// ─────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (!ASSESSMENT_ID) return;
    const container = document.getElementById('questions-container');
    container.innerHTML = '';
    EXISTING_QUESTIONS.forEach(q => {
        const lid = ++qCounter;
        const opts = (q.options || []).map(o => ({
            localId: ++optCounter, dbId: o.id,
            text: o.option_text, isCorrect: !!o.is_correct, matchPair: o.match_pair || ''
        }));
        questions.push({ localId: lid, dbId: q.id, type: q.question_type,
            text: q.question_text, marks: q.marks, autoGrade: !!q.auto_grade,
            options: opts, saved: true });
        container.appendChild(buildQCard(lid));
    });
    updateCounters();
});

// ─────────────────────────────────────────────────
// NAVIGATION
// ─────────────────────────────────────────────────
function switchUnit(uid) { if (uid) window.location.href = `assessment_builder.php?unit_id=${uid}`; }

// ─────────────────────────────────────────────────
// TYPE SELECTOR (new modal)
// ─────────────────────────────────────────────────
function selectType(el) {
    document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('nm-type').value = el.dataset.val;
}

// ─────────────────────────────────────────────────
// NEW ASSESSMENT
// ─────────────────────────────────────────────────
function openNewAssessmentModal() {
    document.getElementById('nm-title').value        = '';
    document.getElementById('nm-instructions').value = '';
    document.getElementById('nm-time').value         = '0';
    document.getElementById('nm-due').value          = '';
    // Reset type selector
    document.querySelectorAll('.type-card').forEach(c => {
        c.classList.toggle('selected', c.dataset.val === 'quiz');
    });
    document.getElementById('nm-type').value = 'quiz';
    openModal('new-modal');
    setTimeout(() => document.getElementById('nm-title').focus(), 150);
}

function saveNewAssessment() {
    const title = document.getElementById('nm-title').value.trim();
    const type  = document.getElementById('nm-type').value;
    if (!title) { toast('Title is required', 'error'); return; }

    const btn = document.getElementById('nm-save-btn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Creating...';

    const fd = new FormData();
    fd.append('unit_id',      UNIT_ID);
    fd.append('lecturer_id',  LECTURER_ID);
    fd.append('title',        title);
    fd.append('type',         type);
    fd.append('instructions', document.getElementById('nm-instructions').value.trim());
    fd.append('time_limit',   document.getElementById('nm-time').value || 0);
    fd.append('due_date',     document.getElementById('nm-due').value || '');

    fetch('ajax/save_assessment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toast('Assessment created', 'success');
                closeModal('new-modal');
                window.location.href = `assessment_builder.php?unit_id=${UNIT_ID}&assessment_id=${d.assessment_id}`;
            } else toast(d.message, 'error');
        })
        .catch(() => toast('Network error', 'error'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Create Assessment'; });
}

// ─────────────────────────────────────────────────
// EDIT DETAILS
// ─────────────────────────────────────────────────
function openEditDetailsModal() {
    const bar = document.getElementById('marks-bar');
    document.getElementById('em-title').value        = document.getElementById('assess-title-display').textContent.trim();
    document.getElementById('em-total').value        = document.getElementById('total-marks').textContent;
    document.getElementById('em-pass').value         = document.getElementById('pass-marks').textContent;
    const instrEl = document.querySelector('.card-body .border-left');
    document.getElementById('em-instructions').value = instrEl?.dataset.raw || '';
    openModal('edit-modal');
}

function saveEditDetails() {
    const fd = new FormData();
    fd.append('assessment_id', ASSESSMENT_ID);
    fd.append('title',        document.getElementById('em-title').value.trim());
    fd.append('instructions', document.getElementById('em-instructions').value.trim());
    fd.append('total_marks',  document.getElementById('em-total').value);
    fd.append('pass_mark',    document.getElementById('em-pass').value);
    fd.append('time_limit',   document.getElementById('em-time').value);
    fd.append('due_date',     document.getElementById('em-due').value);

    fetch('ajax/save_assessment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toast('Details updated', 'success');
                closeModal('edit-modal');
                document.getElementById('assess-title-display').textContent = document.getElementById('em-title').value.trim();
                document.getElementById('total-marks').textContent = document.getElementById('em-total').value;
                document.getElementById('pass-marks').textContent  = document.getElementById('em-pass').value;
            } else toast(d.message, 'error');
        })
        .catch(() => toast('Error', 'error'));
}

// ─────────────────────────────────────────────────
// PUBLISH TOGGLE
// ─────────────────────────────────────────────────
function togglePublish() {
    const fd = new FormData();
    fd.append('assessment_id', ASSESSMENT_ID);
    fetch('ajax/publish_assessment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toast(d.published ? 'Assessment published — students can now see it' : 'Assessment unpublished', d.published ? 'success' : 'info');
                const badge = document.getElementById('pub-badge');
                const btn   = document.getElementById('pub-btn');
                badge.className = d.published ? 'badge pub-yes' : 'badge pub-no';
                badge.textContent = d.published ? '● Live' : '○ Draft';
                btn.className = d.published ? 'btn btn-sm btn-danger' : 'btn btn-sm btn-success';
                btn.innerHTML = d.published ? '<i class="fas fa-eye-slash"></i> Unpublish' : '<i class="fas fa-eye"></i> Publish';
            } else toast(d.message, 'error');
        })
        .catch(() => toast('Error', 'error'));
}

// ─────────────────────────────────────────────────
// ADD QUESTION
// ─────────────────────────────────────────────────
const AUTO_GRADE_TYPES = ['mcq', 'true_false', 'matching'];

function addQuestion(type) {
    const noQ = document.getElementById('no-questions');
    if (noQ) noQ.remove();

    const lid = ++qCounter;
    const q = {
        localId: lid, dbId: null, type,
        text: '', marks: 1,
        autoGrade: AUTO_GRADE_TYPES.includes(type),
        options: [], saved: false
    };

    // True/False: pre-populate options
    if (type === 'true_false') {
        q.options = [
            { localId: ++optCounter, dbId: null, text: 'True',  isCorrect: false, matchPair: '' },
            { localId: ++optCounter, dbId: null, text: 'False', isCorrect: false, matchPair: '' }
        ];
    }
    // MCQ: start with 2 empty options
    if (type === 'mcq') {
        q.options = [
            { localId: ++optCounter, dbId: null, text: '', isCorrect: false, matchPair: '' },
            { localId: ++optCounter, dbId: null, text: '', isCorrect: false, matchPair: '' }
        ];
    }
    // Matching: start with 2 pairs
    if (type === 'matching') {
        q.options = [
            { localId: ++optCounter, dbId: null, text: '', isCorrect: true, matchPair: '' },
            { localId: ++optCounter, dbId: null, text: '', isCorrect: true, matchPair: '' }
        ];
    }

    questions.push(q);
    const container = document.getElementById('questions-container');
    const card = buildQCard(lid);
    container.appendChild(card);
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    updateCounters();
}

// ─────────────────────────────────────────────────
// BUILD QUESTION CARD
// ─────────────────────────────────────────────────
function buildQCard(localId) {
    const q    = questions.find(x => x.localId === localId);
    const card = document.createElement('div');
    card.className   = 'q-card';
    card.dataset.lid = localId;
    card.draggable   = true;

    const qIdx     = questions.findIndex(x => x.localId === localId);
    const autoText = q.autoGrade ? 'Auto-grade' : 'Manual grade';
    const autoCls  = q.autoGrade ? 'auto-yes' : 'auto-no';

    card.innerHTML = `
        <div class="q-header">
            <span class="q-num">Q${qIdx + 1}</span>
            <span class="q-type-tag tag-${q.type}">${qTypeName(q.type)}</span>
            <span class="auto-badge ${autoCls}">${autoText}</span>
            <span class="q-marks" id="qm-${localId}">${q.marks} mark${q.marks !== 1 ? 's' : ''}</span>
            <div class="q-actions">
                <button class="btn btn-ghost btn-xs" onclick="saveQuestion(${localId})" title="Save question">
                    <i class="fas fa-floppy-disk" style="color:var(--green)"></i>
                </button>
                <button class="btn btn-ghost btn-xs" onclick="deleteQuestion(${localId})" title="Delete question">
                    <i class="fas fa-trash" style="color:var(--red)"></i>
                </button>
            </div>
        </div>
        <div class="q-body" id="qbody-${localId}">
            ${buildQBody(localId, q)}
        </div>
    `;

    // Drag
    card.addEventListener('dragstart', e => { dragSrcQ = card; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.stopPropagation(); });
    card.addEventListener('dragend',   () => { card.classList.remove('dragging'); document.querySelectorAll('.q-card').forEach(c => c.classList.remove('drag-over')); dragSrcQ = null; });
    card.addEventListener('dragover',  e => { e.preventDefault(); e.stopPropagation(); if (card !== dragSrcQ) card.classList.add('drag-over'); });
    card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
    card.addEventListener('drop', e => {
        e.preventDefault(); e.stopPropagation();
        card.classList.remove('drag-over');
        if (!dragSrcQ || dragSrcQ === card) return;
        const c = document.getElementById('questions-container');
        const all = [...c.querySelectorAll('.q-card')];
        const si = all.indexOf(dragSrcQ), di = all.indexOf(card);
        if (si < di) c.insertBefore(dragSrcQ, card.nextSibling);
        else         c.insertBefore(dragSrcQ, card);
        saveQuestionOrder();
        reNumberQuestions();
    });

    return card;
}

function buildQBody(localId, q) {
    let html = `
        <div class="fg">
            <label>Question Text</label>
            <textarea class="fta" id="qt-${localId}" oninput="markQUnsaved(${localId})"
                      placeholder="Enter question...">${escHtml(q.text)}</textarea>
        </div>
        <div class="frow">
            <div class="fg">
                <label>Marks</label>
                <input type="number" class="fi" id="qmarks-${localId}" value="${q.marks}" min="1"
                       oninput="updateMarksBadge(${localId})">
            </div>
    `;

    // Short answer / essay / file_upload: no options, manual grading note
    if (['short_answer', 'essay', 'file_upload'].includes(q.type)) {
        html += `
            <div class="fg" style="display:flex;align-items:flex-end;padding-bottom:2px">
                <span style="font-size:.78rem;padding:6px 10px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);border-radius:var(--rs);color:var(--amber)">
                    <i class="fas fa-user-pen"></i> Requires manual grading
                </span>
            </div>
        `;
    } else {
        html += `<div class="fg"></div>`;
    }

    html += `</div>`; // close frow

    // MCQ options
    if (q.type === 'mcq') {
        html += `
            <div>
                <label style="font-size:.76rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;display:block">
                    Options <span style="color:var(--dim);font-weight:400;text-transform:none">(click radio to mark correct)</span>
                </label>
                <div class="options-list" id="opts-${localId}">
                    ${q.options.map(o => buildOptRowHTML(localId, o, 'mcq')).join('')}
                </div>
                <div class="add-opt-row" onclick="addOption(${localId},'mcq')">
                    <i class="fas fa-plus-circle"></i> Add option
                </div>
            </div>`;
    }

    // True/False
    if (q.type === 'true_false') {
        html += `
            <div>
                <label style="font-size:.76rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;display:block">
                    Correct Answer
                </label>
                <div class="options-list" id="opts-${localId}">
                    ${q.options.map(o => buildOptRowHTML(localId, o, 'true_false')).join('')}
                </div>
            </div>`;
    }

    // Matching
    if (q.type === 'matching') {
        html += `
            <div>
                <label style="font-size:.76rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;display:block">
                    Matching Pairs <span style="color:var(--dim);font-weight:400;text-transform:none">(left term → right match)</span>
                </label>
                <div class="options-list" id="opts-${localId}">
                    ${q.options.map(o => buildOptRowHTML(localId, o, 'matching')).join('')}
                </div>
                <div class="add-opt-row" onclick="addOption(${localId},'matching')">
                    <i class="fas fa-plus-circle"></i> Add pair
                </div>
            </div>`;
    }

    return html;
}

function buildOptRowHTML(localId, opt, type) {
    if (type === 'matching') {
        return `
            <div class="opt-row ${opt.isCorrect ? 'correct-opt' : ''}" id="opt-${opt.localId}">
                <i class="fas fa-arrows-left-right" style="color:var(--purple);font-size:.75rem;flex-shrink:0"></i>
                <input type="text" class="opt-text-input" placeholder="Term..."
                       value="${escAttr(opt.text)}"
                       oninput="updateOptText(${localId},${opt.localId},this.value)">
                <span style="color:var(--dim);font-size:.8rem;flex-shrink:0">→</span>
                <input type="text" class="match-pair-input" placeholder="Match..."
                       value="${escAttr(opt.matchPair)}"
                       oninput="updateOptMatch(${localId},${opt.localId},this.value)">
                <button class="btn btn-xs btn-ghost" onclick="removeOption(${localId},${opt.localId})" style="flex-shrink:0">
                    <i class="fas fa-times" style="color:var(--red)"></i>
                </button>
            </div>`;
    }
    return `
        <div class="opt-row ${opt.isCorrect ? 'correct-opt' : ''}" id="opt-${opt.localId}">
            <input type="radio" class="opt-radio" name="correct-${localId}"
                   ${opt.isCorrect ? 'checked' : ''}
                   onchange="setCorrect(${localId},${opt.localId})">
            <input type="text" class="opt-text-input"
                   placeholder="Option text..."
                   value="${escAttr(opt.text)}"
                   oninput="updateOptText(${localId},${opt.localId},this.value)"
                   ${type === 'true_false' ? 'readonly style="cursor:default"' : ''}>
            ${type !== 'true_false' ? `<button class="btn btn-xs btn-ghost" onclick="removeOption(${localId},${opt.localId})"><i class="fas fa-times" style="color:var(--red)"></i></button>` : ''}
        </div>`;
}

// ─────────────────────────────────────────────────
// OPTION CRUD
// ─────────────────────────────────────────────────
function addOption(localId, type) {
    const q   = questions.find(q => q.localId === localId);
    const opt = { localId: ++optCounter, dbId: null, text: '', isCorrect: false, matchPair: '' };
    q.options.push(opt);
    q.saved = false;
    const list = document.getElementById(`opts-${localId}`);
    list.insertAdjacentHTML('beforeend', buildOptRowHTML(localId, opt, type));
}

function removeOption(localId, optLocalId) {
    const q = questions.find(q => q.localId === localId);
    q.options = q.options.filter(o => o.localId !== optLocalId);
    q.saved   = false;
    document.getElementById(`opt-${optLocalId}`)?.remove();
}

function setCorrect(localId, optLocalId) {
    const q = questions.find(q => q.localId === localId);
    q.options.forEach(o => {
        o.isCorrect = (o.localId === optLocalId);
        const row = document.getElementById(`opt-${o.localId}`);
        if (row) row.classList.toggle('correct-opt', o.isCorrect);
    });
    q.saved = false;
}

function updateOptText(localId, optLocalId, val) {
    const q = questions.find(q => q.localId === localId);
    const o = q.options.find(o => o.localId === optLocalId);
    if (o) o.text = val;
    q.saved = false;
}
function updateOptMatch(localId, optLocalId, val) {
    const q = questions.find(q => q.localId === localId);
    const o = q.options.find(o => o.localId === optLocalId);
    if (o) o.matchPair = val;
    q.saved = false;
}
function updateMarksBadge(localId) {
    const val  = parseInt(document.getElementById(`qmarks-${localId}`)?.value) || 1;
    const el   = document.getElementById(`qm-${localId}`);
    if (el) el.textContent = `${val} mark${val !== 1 ? 's' : ''}`;
    const q = questions.find(q => q.localId === localId);
    if (q) { q.marks = val; q.saved = false; }
}
function markQUnsaved(localId) {
    const q = questions.find(q => q.localId === localId);
    if (q) q.saved = false;
}

// ─────────────────────────────────────────────────
// SAVE QUESTION
// ─────────────────────────────────────────────────
function saveQuestion(localId) {
    const q = questions.find(q => q.localId === localId);
    if (!q) return;

    const text = document.getElementById(`qt-${localId}`)?.value.trim();
    if (!text) { toast('Question text is required', 'error'); return; }

    const marks = parseInt(document.getElementById(`qmarks-${localId}`)?.value) || 1;
    q.text  = text;
    q.marks = marks;

    const fd = new FormData();
    fd.append('assessment_id', ASSESSMENT_ID);
    fd.append('question_text', text);
    fd.append('question_type', q.type);
    fd.append('marks',         marks);
    fd.append('auto_grade',    q.autoGrade ? 1 : 0);
    if (q.dbId) fd.append('question_id', q.dbId);

    // Position in DOM
    const cards = [...document.querySelectorAll('#questions-container .q-card')];
    fd.append('position', cards.findIndex(c => parseInt(c.dataset.lid) === localId));

    // Options (for MCQ, TF, Matching)
    const optsPayload = q.options.map(o => ({
        id:         o.dbId || null,
        text:       o.text,
        is_correct: o.isCorrect ? 1 : 0,
        match_pair: o.matchPair || ''
    }));
    fd.append('options', JSON.stringify(optsPayload));

    fetch('ajax/save_question.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                q.dbId  = d.question_id;
                q.saved = true;
                // Update option dbIds
                if (d.option_ids) {
                    q.options.forEach((o, i) => { if (d.option_ids[i]) o.dbId = d.option_ids[i]; });
                }
                toast('Question saved', 'success');
                recalcTotals();
            } else toast(d.message, 'error');
        })
        .catch(() => toast('Save failed', 'error'));
}

function saveAllQuestions() {
    const unsaved = questions.filter(q => !q.saved);
    if (unsaved.length === 0) { toast('All questions already saved', 'info'); return; }
    unsaved.forEach(q => saveQuestion(q.localId));
}

// ─────────────────────────────────────────────────
// DELETE QUESTION
// ─────────────────────────────────────────────────
function deleteQuestion(localId) {
    if (!confirm('Delete this question?')) return;
    const q    = questions.find(q => q.localId === localId);
    const card = document.querySelector(`.q-card[data-lid="${localId}"]`);

    if (!q.dbId) {
        questions = questions.filter(q => q.localId !== localId);
        card?.remove();
        reNumberQuestions();
        updateCounters();
        return;
    }

    const fd = new FormData();
    fd.append('question_id',   q.dbId);
    fd.append('assessment_id', ASSESSMENT_ID);
    fetch('ajax/delete_question.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                questions = questions.filter(q => q.localId !== localId);
                card?.remove();
                reNumberQuestions();
                updateCounters();
                recalcTotals();
                toast('Question deleted', 'success');
            } else toast(d.message, 'error');
        })
        .catch(() => toast('Delete failed', 'error'));
}

// ─────────────────────────────────────────────────
// REORDER / RENUMBER
// ─────────────────────────────────────────────────
function reNumberQuestions() {
    document.querySelectorAll('#questions-container .q-card').forEach((card, idx) => {
        const lid = parseInt(card.dataset.lid);
        const el  = card.querySelector('.q-num');
        if (el) el.textContent = `Q${idx + 1}`;
    });
}

function saveQuestionOrder() {
    const ids = [...document.querySelectorAll('#questions-container .q-card')]
                    .map(c => { const lid = parseInt(c.dataset.lid); const q = questions.find(q => q.localId === lid); return q?.dbId || null; })
                    .filter(id => id !== null);
    if (!ids.length) return;
    const fd = new FormData();
    fd.append('assessment_id', ASSESSMENT_ID);
    fd.append('order', JSON.stringify(ids));
    fetch('ajax/reorder_questions.php', { method: 'POST', body: fd }).catch(() => {});
}

// ─────────────────────────────────────────────────
// RECALC TOTALS
// ─────────────────────────────────────────────────
function recalcTotals() {
    const total = questions.filter(q => q.dbId).reduce((s, q) => s + q.marks, 0);
    document.getElementById('total-marks').textContent = total;

    // Also persist to DB
    const fd = new FormData();
    fd.append('assessment_id', ASSESSMENT_ID);
    fd.append('total_marks',   total);
    fetch('ajax/save_assessment.php', { method: 'POST', body: fd }).catch(() => {});
}

function updateCounters() {
    const n = questions.length;
    document.getElementById('q-count')?.setAttribute('textContent', n);
    document.getElementById('q-count').textContent = n;
    const badge = document.getElementById('q-badge');
    if (badge) badge.textContent = `${n} question${n !== 1 ? 's' : ''}`;
}

// ─────────────────────────────────────────────────
// MODAL
// ─────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.overlay').forEach(o => { o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); }); });

// ─────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────
function qTypeName(t) {
    return { mcq:'MCQ', true_false:'True / False', matching:'Matching', short_answer:'Short Answer', essay:'Essay', file_upload:'File Upload' }[t] || t;
}

function toast(msg, type = 'info') {
    const c = document.getElementById('toast');
    const e = document.createElement('div');
    e.className = `toast-item ${type}`;
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info' };
    e.innerHTML = `<i class="fas ${icons[type]||'fa-circle-info'}"></i> ${escHtml(msg)}`;
    c.appendChild(e);
    setTimeout(() => e.remove(), 2900);
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escAttr(s) { return String(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
</script>
</body>
</html>