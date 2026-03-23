<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php"); exit;
}

$lecturer_id   = intval($_SESSION['user_id']);
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';
$unit_id       = intval($_GET['unit_id'] ?? 0);

// Fetch lecturer's units
$units = [];
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.name
        FROM units u
        JOIN lecturer_units lu ON u.id = lu.unit_id
        WHERE lu.lecturer_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

$unit_name = '';
foreach ($units as $u) { if ($u['id'] == $unit_id) { $unit_name = $u['name']; break; } }

// ── Weight settings for this unit ────────────────────────────────
$weights = ['quiz' => 20, 'assignment' => 20, 'cat' => 30, 'exam' => 30];
try {
    $stmt = $conn->prepare("SELECT assessment_type, weight_percent FROM assessment_weights WHERE unit_id = ? AND lecturer_id = ?");
    $stmt->bind_param("ii", $unit_id, $lecturer_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $weights[$row['assessment_type']] = floatval($row['weight_percent']);
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// ── Fetch all assessments for this unit ───────────────────────────
$assessments = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("
            SELECT id, title, type, total_marks, pass_mark, is_published, created_at
            FROM assessments
            WHERE unit_id = ? AND lecturer_id = ?
            ORDER BY type ASC, created_at ASC
        ");
        $stmt->bind_param("ii", $unit_id, $lecturer_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $assessments[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// ── Fetch all enrolled students ─────────────────────────────────
// students table has: id, reg_no, name, email (no separate users table)
$students = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("
            SELECT s.id,
                   COALESCE(s.reg_no, '')   AS registration_number,
                   COALESCE(s.name, '')     AS student_name,
                   COALESCE(s.email, '')    AS email
            FROM student_unit_enrollments sue
            JOIN students s ON s.id = sue.student_id
            WHERE sue.unit_id = ?
            ORDER BY s.name ASC
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $students[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("submissions students fetch: " . $e->getMessage());
    }
}

// ── Fetch all submissions for this unit ──────────────────────────────
// Merges assessment_submissions (formal submit) WITH student_progress
// (event-based score) so both recording paths are covered.
$submissions = [];
if ($unit_id && !empty($assessments)) {
    $assess_ids = array_column($assessments, 'id');
    $ph = implode(',', array_fill(0, count($assess_ids), '?'));
    $types = str_repeat('i', count($assess_ids));

    // 1. Formal submissions table
    try {
        $stmt = $conn->prepare("
            SELECT id, assessment_id, student_id,
                   score, status, submitted_at
            FROM assessment_submissions
            WHERE assessment_id IN ($ph)
        ");
        $stmt->bind_param($types, ...$assess_ids);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            // Keep id so JS can build grade_submission.php link
            $submissions[$row['student_id']][$row['assessment_id']] = $row;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log("submissions fetch: " . $e->getMessage()); }

    // 2. student_progress event scores (quiz_score, cat_score, exam_score, assignment_score)
    // These are set when a student submits via take_assessment.php
    try {
        $stmt = $conn->prepare("
            SELECT NULL AS id, assessment_id, student_id,
                   score,
                   'submitted' AS status,
                   completed_at AS submitted_at
            FROM student_progress
            WHERE assessment_id IN ($ph)
              AND event_type IN ('quiz_score','assignment_score','cat_score','exam_score')
              AND assessment_id IS NOT NULL
        ");
        $stmt->bind_param($types, ...$assess_ids);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $sid = $row['student_id'];
            $aid = $row['assessment_id'];
            // Only use progress record if no formal submission exists for this pair
            if (!isset($submissions[$sid][$aid])) {
                $submissions[$sid][$aid] = $row;
            }
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log("submissions progress fetch: " . $e->getMessage()); }
}

// ── Compute per-student weighted totals ──────────────────────────
$student_totals = [];
foreach ($students as $s) {
    $sid = $s['id'];
    $type_scores = ['quiz' => [], 'assignment' => [], 'cat' => [], 'exam' => []];
    foreach ($assessments as $a) {
        $sub = $submissions[$sid][$a['id']] ?? null;
        if ($sub && $sub['score'] !== null) {
            $type_scores[$a['type']][] = floatval($sub['score']);
        }
    }
    // Average per type, then weighted sum
    $weighted = 0;
    $weight_used = 0;
    foreach ($type_scores as $type => $scores) {
        if (!empty($scores)) {
            $avg = array_sum($scores) / count($scores);
            $w   = floatval($weights[$type] ?? 0);
            $weighted   += ($avg * $w / 100);
            $weight_used += $w;
        }
    }
    $student_totals[$sid] = [
        'weighted'     => $weight_used > 0 ? round($weighted, 1) : null,
        'type_scores'  => $type_scores,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Submissions — UNILIS</title>
<link rel="icon" href="data:,">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- jsPDF + AutoTable for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<!-- SheetJS for Excel export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
:root {
    --bg:      #080c12;
    --surf:    #0e1420;
    --surf2:   #141c2c;
    --surf3:   #1a2438;
    --border:  #1e2d45;
    --border2: #263652;
    --accent:  #3d8ef8;
    --green:   #22d3a0;
    --amber:   #f5a623;
    --red:     #f45b5b;
    --purple:  #9b7ff8;
    --cyan:    #22d8f0;
    --text:    #dce8f8;
    --text2:   #7a90b0;
    --dim:     #2e4060;
    --r:       12px;
    --rs:      7px;
    --tr:      .16s ease;
    --shadow:  0 4px 28px rgba(0,0,0,.5);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* ── TOPBAR ── */
.topbar{
    background:var(--surf);border-bottom:1px solid var(--border);
    padding:0 24px;height:56px;display:flex;align-items:center;
    justify-content:space-between;position:sticky;top:0;z-index:200;
}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:.95rem;color:var(--accent)}
.brand span{color:var(--text2);font-weight:400;font-size:.78rem;margin-left:8px}
.nav-right{display:flex;align-items:center;gap:8px}
.btn-nav{
    background:var(--surf2);border:1px solid var(--border);color:var(--text2);
    padding:5px 12px;border-radius:var(--rs);font-size:.77rem;
    cursor:pointer;text-decoration:none;transition:var(--tr);
    font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;
}
.btn-nav:hover{border-color:var(--accent);color:var(--accent)}

/* ── LAYOUT ── */
.layout{display:flex;height:calc(100vh - 56px);overflow:hidden}

/* ── SIDEBAR ── */
.sidebar{
    width:260px;min-width:260px;background:var(--surf);
    border-right:1px solid var(--border);
    display:flex;flex-direction:column;overflow:hidden;
}
.sb-head{padding:16px 14px 12px;border-bottom:1px solid var(--border);flex-shrink:0}
.sb-label{
    font-family:'Syne',sans-serif;font-size:.64rem;font-weight:700;
    letter-spacing:.12em;text-transform:uppercase;color:var(--dim);
    display:block;margin-bottom:6px;
}
.styled-sel{
    width:100%;background:var(--surf2);border:1px solid var(--border);
    color:var(--text);padding:8px 28px 8px 11px;border-radius:var(--rs);
    font-family:'DM Sans',sans-serif;font-size:.83rem;
    outline:none;cursor:pointer;appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%237a90b0' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 10px center;
    transition:border-color var(--tr);
}
.styled-sel:focus{border-color:var(--accent)}

.sb-body{flex:1;overflow-y:auto;padding:12px 10px}
.sb-menu-item{
    display:flex;align-items:center;gap:9px;padding:9px 12px;
    border-radius:var(--rs);cursor:pointer;transition:var(--tr);
    border:1px solid transparent;margin-bottom:2px;
    font-size:.84rem;color:var(--text2);text-decoration:none;
}
.sb-menu-item:hover{background:var(--surf2);color:var(--text)}
.sb-menu-item.active{
    background:rgba(61,142,248,.1);border-color:rgba(61,142,248,.25);
    color:var(--accent);font-weight:500;
}
.sb-menu-item i{width:16px;text-align:center;font-size:.8rem}

/* ── MAIN ── */
.main{flex:1;overflow-y:auto;display:flex;flex-direction:column}

/* ── TAB STRIP ── */
.tab-strip{
    background:var(--surf);border-bottom:1px solid var(--border);
    padding:0 28px;display:flex;align-items:center;gap:4px;
    flex-shrink:0;
}
.tab{
    padding:14px 18px;font-size:.83rem;font-weight:500;
    cursor:pointer;border-bottom:2px solid transparent;
    color:var(--text2);transition:var(--tr);white-space:nowrap;
    display:flex;align-items:center;gap:7px;
}
.tab:hover{color:var(--text)}
.tab.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab-badge{
    font-size:.65rem;background:var(--surf3);color:var(--text2);
    padding:1px 6px;border-radius:999px;border:1px solid var(--border);
}
.tab.active .tab-badge{background:rgba(61,142,248,.15);color:var(--accent);border-color:rgba(61,142,248,.3)}

/* ── CONTENT AREA ── */
.content{padding:24px 28px;flex:1}

/* ── STAT ROW ── */
.stat-row{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:22px}
.stat-card{
    background:var(--surf);border:1px solid var(--border);
    border-radius:var(--r);padding:14px 16px;
}
.stat-num{
    font-family:'Syne',sans-serif;font-size:1.7rem;
    font-weight:800;line-height:1;margin-bottom:4px;
}
.stat-lbl{font-size:.68rem;color:var(--text2);text-transform:uppercase;letter-spacing:.08em}

/* ── TOOLBAR ── */
.toolbar{
    display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;
}
.search-box{
    display:flex;align-items:center;gap:8px;
    background:var(--surf2);border:1px solid var(--border);
    border-radius:var(--rs);padding:6px 12px;flex:1;min-width:200px;max-width:320px;
}
.search-box i{color:var(--text2);font-size:.8rem}
.search-box input{
    background:none;border:none;color:var(--text);
    font-family:'DM Sans',sans-serif;font-size:.84rem;
    outline:none;flex:1;
}
.search-box input::placeholder{color:var(--dim)}
.btn{
    display:inline-flex;align-items:center;gap:7px;
    padding:7px 14px;border-radius:var(--rs);
    font-family:'DM Sans',sans-serif;font-size:.8rem;
    font-weight:500;cursor:pointer;border:none;
    transition:var(--tr);white-space:nowrap;text-decoration:none;
}
.btn-accent {background:var(--accent);color:#fff}
.btn-accent:hover{filter:brightness(1.1)}
.btn-green {background:var(--green);color:#041a12}
.btn-green:hover{filter:brightness(1.08)}
.btn-ghost {background:var(--surf2);border:1px solid var(--border);color:var(--text2)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-amber {background:var(--amber);color:#1a0e00}
.btn-amber:hover{filter:brightness(1.08)}
.btn-purple{background:var(--purple);color:#fff}
.btn-purple:hover{filter:brightness(1.1)}
.btn-sm{padding:5px 11px;font-size:.76rem}
.btn:disabled{opacity:.4;cursor:not-allowed}

/* ── TABLE ── */
.tbl-wrap{
    background:var(--surf);border:1px solid var(--border);
    border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow);
}
.tbl-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:900px}
thead th{
    background:var(--surf2);
    font-family:'Syne',sans-serif;font-size:.64rem;font-weight:700;
    letter-spacing:.1em;text-transform:uppercase;color:var(--text2);
    padding:11px 14px;text-align:left;border-bottom:1px solid var(--border);
    white-space:nowrap;position:sticky;top:0;
}
thead th.sortable{cursor:pointer}
thead th.sortable:hover{color:var(--accent)}
tbody tr{transition:background var(--tr)}
tbody tr:hover td{background:rgba(61,142,248,.04)}
tbody td{
    padding:11px 14px;border-bottom:1px solid var(--surf3);
    font-size:.84rem;vertical-align:middle;
}
tbody tr:last-child td{border-bottom:none}
.student-name{font-weight:500;color:var(--text)}
.reg-num{font-family:'JetBrains Mono',monospace;font-size:.72rem;color:var(--text2)}

/* ── SCORE CHIPS ── */
.score-cell{
    font-family:'JetBrains Mono',monospace;font-size:.79rem;
    font-weight:500;text-align:center;
}
.chip{
    display:inline-block;padding:2px 8px;border-radius:999px;
    font-size:.72rem;font-weight:600;border:1px solid;
}
.chip-pass  {background:rgba(34,211,160,.1);color:var(--green);border-color:rgba(34,211,160,.25)}
.chip-fail  {background:rgba(244,91,91,.1); color:var(--red);  border-color:rgba(244,91,91,.25)}
.chip-pend  {background:rgba(245,166,35,.1);color:var(--amber);border-color:rgba(245,166,35,.25)}
.chip-none  {background:var(--surf3);color:var(--dim);border-color:var(--border)}
.chip-total {font-size:.8rem;padding:3px 10px}

/* ── MINI SCORE BAR ── */
.score-bar-wrap{width:60px;height:4px;background:var(--surf3);border-radius:2px;display:inline-block;vertical-align:middle;margin-left:4px}
.score-bar-fill{height:100%;border-radius:2px;transition:width .4s ease}

/* ── TYPE HEADER CHIPS ── */
.type-hdr{
    display:inline-flex;align-items:center;gap:4px;
    padding:2px 8px;border-radius:999px;font-size:.65rem;
    font-weight:700;text-transform:uppercase;letter-spacing:.06em;
}
.hdr-quiz      {background:rgba(61,142,248,.12);color:var(--accent);border:1px solid rgba(61,142,248,.25)}
.hdr-assignment{background:rgba(34,211,160,.12);color:var(--green); border:1px solid rgba(34,211,160,.25)}
.hdr-cat       {background:rgba(245,166,35,.12);color:var(--amber); border:1px solid rgba(245,166,35,.25)}
.hdr-exam      {background:rgba(244,91,91,.12); color:var(--red);   border:1px solid rgba(244,91,91,.25)}

/* ── WEIGHT PANEL ── */
.weight-panel{
    background:var(--surf);border:1px solid var(--border);
    border-radius:var(--r);padding:22px 24px;margin-bottom:22px;
}
.weight-panel h3{
    font-family:'Syne',sans-serif;font-size:.85rem;font-weight:700;
    color:var(--text);margin-bottom:4px;
}
.weight-panel p{font-size:.8rem;color:var(--text2);margin-bottom:18px}
.weight-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.weight-item{display:flex;flex-direction:column;gap:6px}
.weight-item label{
    font-size:.72rem;font-weight:600;text-transform:uppercase;
    letter-spacing:.08em;display:flex;align-items:center;gap:6px;
}
.weight-input-wrap{
    display:flex;align-items:center;gap:6px;
    background:var(--surf2);border:1px solid var(--border);
    border-radius:var(--rs);padding:6px 10px;
    transition:border-color var(--tr);
}
.weight-input-wrap:focus-within{border-color:var(--accent)}
.weight-input-wrap input{
    background:none;border:none;color:var(--text);
    font-family:'JetBrains Mono',monospace;font-size:.95rem;
    font-weight:500;width:50px;outline:none;text-align:right;
}
.weight-input-wrap span{color:var(--text2);font-size:.82rem}
.weight-total{
    display:flex;align-items:center;gap:10px;
    padding:10px 14px;border-radius:var(--rs);
    font-size:.8rem;margin-top:12px;
}
.weight-total.ok   {background:rgba(34,211,160,.08);border:1px solid rgba(34,211,160,.2);color:var(--green)}
.weight-total.warn {background:rgba(244,91,91,.08); border:1px solid rgba(244,91,91,.2); color:var(--red)}

/* ── SUBMISSION DETAIL TABLE ── */
.assess-section{margin-bottom:28px}
.assess-section h4{
    font-family:'Syne',sans-serif;font-size:.78rem;font-weight:700;
    text-transform:uppercase;letter-spacing:.08em;color:var(--text2);
    margin-bottom:10px;display:flex;align-items:center;gap:8px;
}
.sub-tbl{width:100%;border-collapse:collapse;background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.sub-tbl th{background:var(--surf2);font-family:'Syne',sans-serif;font-size:.63rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--text2);padding:9px 12px;text-align:left;border-bottom:1px solid var(--border)}
.sub-tbl td{padding:10px 12px;border-bottom:1px solid var(--surf3);font-size:.83rem;vertical-align:middle}
.sub-tbl tr:last-child td{border-bottom:none}
.sub-tbl tr:hover td{background:rgba(61,142,248,.04)}

/* ── MANUAL GRADE FORM ── */
.grade-input{
    background:var(--surf2);border:1px solid var(--border);
    color:var(--text);padding:4px 9px;border-radius:var(--rs);
    font-family:'JetBrains Mono',monospace;font-size:.82rem;
    width:70px;outline:none;transition:border-color var(--tr);
}
.grade-input:focus{border-color:var(--accent)}

/* ── MODAL ── */
.overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.82);
    backdrop-filter:blur(6px);z-index:500;
    display:flex;align-items:center;justify-content:center;
    padding:20px;opacity:0;pointer-events:none;transition:opacity .2s;
}
.overlay.open{opacity:1;pointer-events:all}
.modal{
    background:var(--surf);border:1px solid var(--border);
    border-radius:var(--r);width:640px;max-width:100%;
    max-height:88vh;display:flex;flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.7);
    transform:translateY(14px);transition:transform .2s;
}
.overlay.open .modal{transform:translateY(0)}
.modal-head{
    padding:18px 22px;border-bottom:1px solid var(--border);flex-shrink:0;
    display:flex;align-items:center;justify-content:space-between;
}
.modal-head h3{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700}
.modal-close{background:none;border:none;color:var(--text2);cursor:pointer;font-size:1rem;padding:4px}
.modal-close:hover{color:var(--text)}
.modal-body{flex:1;overflow-y:auto;padding:18px 22px}
.modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;flex-shrink:0}

/* ── EMPTY ── */
.empty{text-align:center;padding:56px 24px;color:var(--dim)}
.empty i{font-size:2.2rem;display:block;margin-bottom:12px;opacity:.3}
.empty p{font-size:.85rem}

/* ── TOAST ── */
#toast-wrap{position:fixed;bottom:20px;right:20px;z-index:999;display:flex;flex-direction:column;gap:7px;pointer-events:none}
.toast{
    background:var(--surf2);border:1px solid var(--border);
    border-radius:var(--rs);padding:9px 15px;font-size:.81rem;
    color:var(--text);display:flex;align-items:center;gap:8px;
    box-shadow:var(--shadow);animation:tIn .2s ease,tOut .2s ease 2.6s forwards;
    max-width:300px;
}
.toast.ok  {border-left:3px solid var(--green)}
.toast.err {border-left:3px solid var(--red)}
.toast.inf {border-left:3px solid var(--accent)}
@keyframes tIn {from{opacity:0;transform:translateX(12px)}to{opacity:1;transform:none}}
@keyframes tOut{from{opacity:1}to{opacity:0;transform:translateX(12px)}}
@keyframes spin{to{transform:rotate(360deg)}}
.spinner{width:13px;height:13px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;display:inline-block}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--surf3);border-radius:2px}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <div class="brand">UNILIS <span>Submissions & Grades</span></div>
    <div class="nav-right">
        <span style="font-size:.8rem;color:var(--text2)"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($lecturer_name) ?></span>
        <a href="assessment_builder.php<?= $unit_id ? '?unit_id='.$unit_id : '' ?>" class="btn-nav"><i class="fas fa-tasks"></i> Assessments</a>
        <a href="course_builder.php<?= $unit_id ? '?unit_id='.$unit_id : '' ?>" class="btn-nav"><i class="fas fa-sitemap"></i> Course Builder</a>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sb-head">
            <span class="sb-label"><i class="fas fa-book"></i> &nbsp;Unit</span>
            <select class="styled-sel" id="unit-sel" onchange="window.location.href='submissions.php?unit_id='+this.value">
                <option value="">— select unit —</option>
                <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sb-body">
            <?php if ($unit_id): ?>
            <div class="sb-menu-item active" onclick="showTab('overview')"><i class="fas fa-chart-bar"></i> Overview</div>
            <div class="sb-menu-item" onclick="showTab('matrix')"><i class="fas fa-table"></i> Grade Matrix</div>
            <div class="sb-menu-item" onclick="showTab('submissions')"><i class="fas fa-inbox"></i> By Assessment</div>
            <div class="sb-menu-item" onclick="showTab('weights')"><i class="fas fa-sliders"></i> Weighting</div>
            <div style="border-top:1px solid var(--border);margin:12px 4px;"></div>
            <div class="sb-menu-item" onclick="exportPDF()"><i class="fas fa-file-pdf" style="color:var(--red)"></i> Export PDF</div>
            <div class="sb-menu-item" onclick="exportExcel()"><i class="fas fa-file-excel" style="color:var(--green)"></i> Export Excel</div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main" id="main">

        <?php if (!$unit_id): ?>
        <div class="empty" style="margin:auto">
            <i class="fas fa-chart-bar"></i>
            <p>Select a unit from the sidebar to view submissions and grades.</p>
        </div>

        <?php else: ?>

        <!-- TAB STRIP -->
        <div class="tab-strip">
            <div class="tab active" id="tab-overview"    onclick="showTab('overview')">
                <i class="fas fa-chart-bar"></i> Overview
            </div>
            <div class="tab" id="tab-matrix"      onclick="showTab('matrix')">
                <i class="fas fa-table"></i> Grade Matrix
                <span class="tab-badge"><?= count($students) ?></span>
            </div>
            <div class="tab" id="tab-submissions" onclick="showTab('submissions')">
                <i class="fas fa-inbox"></i> By Assessment
                <span class="tab-badge"><?= count($assessments) ?></span>
            </div>
            <div class="tab" id="tab-weights"     onclick="showTab('weights')">
                <i class="fas fa-sliders"></i> Weighting
            </div>
        </div>

        <!-- ═══════════════ TAB: OVERVIEW ═══════════════ -->
        <div id="pane-overview" class="content">

            <?php
            $total_submitted = 0;
            foreach ($submissions as $s_subs) $total_submitted += count($s_subs);
            $total_possible  = count($students) * count($assessments);
            $pass_count      = 0;
            foreach ($submissions as $sid => $s_subs) {
                foreach ($s_subs as $aid => $sub) {
                    $a = array_values(array_filter($assessments, fn($x) => $x['id'] == $aid));
                    if (!empty($a) && $sub['score'] !== null && $sub['score'] !== ''
                        && floatval($sub['score']) >= floatval($a[0]['pass_mark'])) $pass_count++;
                }
            }
            $all_scores = [];
            foreach ($submissions as $s_subs) {
                foreach ($s_subs as $sub) {
                    if ($sub['score'] !== null) $all_scores[] = floatval($sub['score']);
                }
            }
            $class_avg = count($all_scores) > 0 ? round(array_sum($all_scores)/count($all_scores),1) : 0;
            ?>

            <div class="stat-row">
                <div class="stat-card">
                    <div class="stat-num" style="color:var(--accent)"><?= count($students) ?></div>
                    <div class="stat-lbl">Enrolled Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:var(--purple)"><?= count($assessments) ?></div>
                    <div class="stat-lbl">Assessments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:var(--green)"><?= $total_submitted ?></div>
                    <div class="stat-lbl">Submissions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:var(--amber)"><?= $pass_count ?></div>
                    <div class="stat-lbl">Passes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:var(--cyan)"><?= $class_avg ?>%</div>
                    <div class="stat-lbl">Class Average</div>
                </div>
            </div>

            <!-- Per-assessment summary -->
            <?php if (!empty($assessments)): ?>
            <?php $type_groups = []; foreach ($assessments as $a) $type_groups[$a['type']][] = $a; ?>
            <?php foreach (['quiz','assignment','cat','exam'] as $type):
                if (empty($type_groups[$type])) continue; ?>
            <div class="assess-section">
                <h4><span class="type-hdr hdr-<?= $type ?>"><?= strtoupper($type) ?>S</span></h4>
                <div class="tbl-wrap">
                <div class="tbl-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Assessment</th>
                            <th>Total Marks</th>
                            <th>Pass Mark</th>
                            <th>Submissions</th>
                            <th>Avg Score</th>
                            <th>Pass Rate</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($type_groups[$type] as $a):
                        $subs_for = array_filter(array_map(fn($s) => $submissions[$s['id']][$a['id']] ?? null, $students), fn($x) => $x !== null);
                        $scored   = array_filter($subs_for, fn($x) => $x['score'] !== null && $x['score'] !== '');
                        $scores_a = array_map(fn($x) => floatval($x['score']), $scored);
                        $avg_a    = count($scores_a) > 0 ? round(array_sum($scores_a)/count($scores_a),1) : null;
                        $passes_a = count(array_filter($scores_a, fn($s) => $s >= floatval($a['pass_mark'])));
                        $pass_rate= count($scores_a) > 0 ? round($passes_a/count($scores_a)*100) : 0;
                    ?>
                    <tr>
                        <td style="font-weight:500"><?= htmlspecialchars($a['title']) ?></td>
                        <td class="score-cell"><?= intval($a['total_marks']) ?></td>
                        <td class="score-cell"><?= intval($a['pass_mark']) ?>%</td>
                        <td class="score-cell"><?= count($subs_for) ?> / <?= count($students) ?></td>
                        <td class="score-cell">
                            <?= $avg_a !== null ? $avg_a.'%' : '<span style="color:var(--dim)">—</span>' ?>
                        </td>
                        <td class="score-cell">
                            <?php if (count($scores_a) > 0): ?>
                            <?= $pass_rate ?>%
                            <div class="score-bar-wrap"><div class="score-bar-fill" style="width:<?= $pass_rate ?>%;background:<?= $pass_rate >= 50 ? 'var(--green)' : 'var(--red)' ?>"></div></div>
                            <?php else: echo '<span style="color:var(--dim)">—</span>'; endif; ?>
                        </td>
                        <td>
                            <span class="chip <?= $a['is_published'] ? 'chip-pass' : 'chip-none' ?>">
                                <?= $a['is_published'] ? 'Live' : 'Draft' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div></div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty"><i class="fas fa-tasks"></i><p>No assessments created yet for this unit.</p></div>
            <?php endif; ?>
        </div>

        <!-- ═══════════════ TAB: GRADE MATRIX ═══════════════ -->
        <div id="pane-matrix" class="content" style="display:none">
            <div class="toolbar">
                <div class="search-box">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="matrix-search" placeholder="Search student…" oninput="filterMatrix(this.value)">
                </div>
                <button class="btn btn-accent btn-sm" onclick="exportPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn btn-green btn-sm"  onclick="exportExcel()"><i class="fas fa-file-excel"></i> Excel</button>
            </div>

            <?php if (empty($students)): ?>
            <div class="empty"><i class="fas fa-users"></i><p>No students enrolled in this unit.</p></div>
            <?php else: ?>
            <div class="tbl-wrap">
            <div class="tbl-scroll">
            <table id="matrix-table">
                <thead>
                    <tr>
                        <th style="min-width:180px">Student</th>
                        <th style="min-width:110px">Reg No.</th>
                        <?php foreach ($assessments as $a): ?>
                        <th style="min-width:90px;text-align:center">
                            <div><?= htmlspecialchars(mb_strimwidth($a['title'],0,20,'…')) ?></div>
                            <span class="type-hdr hdr-<?= $a['type'] ?>" style="margin-top:3px;display:inline-flex"><?= strtoupper($a['type']) ?></span>
                        </th>
                        <?php endforeach; ?>
                        <th style="min-width:100px;text-align:center;background:rgba(61,142,248,.06)">
                            Weighted Total
                        </th>
                    </tr>
                </thead>
                <tbody id="matrix-body">
                <?php foreach ($students as $s):
                    $sid     = $s['id'];
                    $wtotal  = $student_totals[$sid]['weighted'] ?? null;
                ?>
                <tr class="matrix-row" data-name="<?= htmlspecialchars(strtolower($s['student_name'])) ?>">
                    <td class="student-name"><?= htmlspecialchars($s['student_name']) ?></td>
                    <td class="reg-num"><?= htmlspecialchars($s['registration_number'] ?? '—') ?></td>
                    <?php foreach ($assessments as $a):
                        $sub    = $submissions[$sid][$a['id']] ?? null;
                        $score  = $sub ? $sub['score'] : null;
                        $passed = $score !== null && floatval($score) >= floatval($a['pass_mark']);
                        $status = $sub ? $sub['status'] : null;
                    ?>
                    <td class="score-cell">
                        <?php if ($score !== null): ?>
                            <span class="chip <?= $passed ? 'chip-pass' : 'chip-fail' ?>">
                                <?= round(floatval($score),1) ?>%
                            </span>
                        <?php elseif ($sub && in_array($status, ['submitted','in_progress','flagged'])): ?>
                            <span class="chip chip-pend">
                                <i class="fas fa-hourglass-half" style="font-size:.6rem"></i>
                                <?= $status === 'in_progress' ? 'In progress' : 'Pending' ?>
                            </span>
                        <?php else: ?>
                            <span class="chip chip-none">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="score-cell" style="background:rgba(61,142,248,.04)">
                        <?php if ($wtotal !== null): ?>
                        <span class="chip chip-total <?= $wtotal >= 50 ? 'chip-pass' : 'chip-fail' ?>">
                            <?= $wtotal ?>%
                        </span>
                        <?php else: ?>
                        <span class="chip chip-none">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div></div>
            <?php endif; ?>
        </div>

        <!-- ═══════════════ TAB: BY ASSESSMENT ═══════════════ -->
        <div id="pane-submissions" class="content" style="display:none">

            <?php if (empty($assessments)): ?>
            <div class="empty"><i class="fas fa-inbox"></i><p>No assessments yet.</p></div>
            <?php else: ?>

            <!-- Assessment picker -->
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
                <span style="font-size:.8rem;color:var(--text2)">Assessment:</span>
                <select class="styled-sel" style="width:auto;min-width:260px" id="assess-picker" onchange="loadAssessSubmissions(this.value)">
                    <option value="">— pick an assessment —</option>
                    <?php foreach ($assessments as $a): ?>
                    <option value="<?= $a['id'] ?>">
                        [<?= strtoupper($a['type']) ?>] <?= htmlspecialchars($a['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="assess-subs-content">
                <div class="empty"><i class="fas fa-hand-pointer"></i><p>Select an assessment above to view submissions.</p></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ═══════════════ TAB: WEIGHTING ═══════════════ -->
        <div id="pane-weights" class="content" style="display:none">
            <div class="weight-panel">
                <h3><i class="fas fa-sliders" style="color:var(--accent)"></i> &nbsp;Assessment Weighting</h3>
                <p>Set the percentage contribution of each assessment type to the final weighted score. Total must equal 100%.</p>
                <div class="weight-grid">
                    <?php
                    $wdefs = [
                        'quiz'       => ['Quiz',       'var(--accent)', 'fa-circle-question'],
                        'assignment' => ['Assignment',  'var(--green)',  'fa-file-pen'],
                        'cat'        => ['CAT',         'var(--amber)',  'fa-clipboard-check'],
                        'exam'       => ['Exam',        'var(--red)',    'fa-graduation-cap'],
                    ];
                    foreach ($wdefs as $type => [$label, $color, $icon]): ?>
                    <div class="weight-item">
                        <label style="color:<?= $color ?>">
                            <i class="fas <?= $icon ?>"></i> <?= $label ?>
                        </label>
                        <div class="weight-input-wrap">
                            <input type="number" id="w-<?= $type ?>" min="0" max="100"
                                   value="<?= intval($weights[$type]) ?>"
                                   oninput="updateWeightTotal()">
                            <span>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="weight-total ok" id="weight-total-row">
                    <i class="fas fa-check-circle"></i>
                    <span id="weight-total-text">Total: <?= array_sum($weights) ?>% — OK</span>
                </div>
                <div style="margin-top:14px;display:flex;gap:10px">
                    <button class="btn btn-accent" onclick="saveWeights()">
                        <i class="fas fa-floppy-disk"></i> Save Weights
                    </button>
                    <button class="btn btn-ghost" onclick="resetWeights()">
                        <i class="fas fa-rotate-left"></i> Reset to Default
                    </button>
                </div>
            </div>

            <!-- Weight preview table -->
            <?php if (!empty($students)): ?>
            <div style="margin-top:4px">
                <div style="font-family:'Syne',sans-serif;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--text2);margin-bottom:10px">
                    <i class="fas fa-eye"></i> &nbsp;Weighted Score Preview
                </div>
                <div class="tbl-wrap"><div class="tbl-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th style="text-align:center">Quiz Avg</th>
                            <th style="text-align:center">Assignment Avg</th>
                            <th style="text-align:center">CAT Avg</th>
                            <th style="text-align:center">Exam Avg</th>
                            <th style="text-align:center;background:rgba(61,142,248,.06)">Weighted Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s):
                        $sid    = $s['id'];
                        $ts     = $student_totals[$sid];
                        $avgs   = [];
                        foreach (['quiz','assignment','cat','exam'] as $t) {
                            $sc = $ts['type_scores'][$t];
                            $avgs[$t] = count($sc) > 0 ? round(array_sum($sc)/count($sc),1) : null;
                        }
                    ?>
                    <tr>
                        <td class="student-name"><?= htmlspecialchars($s['student_name']) ?></td>
                        <?php foreach (['quiz','assignment','cat','exam'] as $t): ?>
                        <td class="score-cell">
                            <?= $avgs[$t] !== null ? '<span class="chip '.($avgs[$t]>=50?'chip-pass':'chip-fail').'">'.$avgs[$t].'%</span>' : '<span class="chip chip-none">—</span>' ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="score-cell" style="background:rgba(61,142,248,.04)">
                            <?php $wt = $ts['weighted']; ?>
                            <?= $wt !== null ? '<span class="chip chip-total '.($wt>=50?'chip-pass':'chip-fail').'">'.$wt.'%</span>' : '<span class="chip chip-none">—</span>' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div></div>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; // unit_id ?>
    </main>
</div>

<!-- Student Detail Modal -->
<div class="overlay" id="student-modal">
    <div class="modal">
        <div class="modal-head">
            <h3 id="modal-student-name">Student Detail</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modal-body-content"></div>
        <div class="modal-foot">
            <button class="btn btn-accent btn-sm" onclick="exportStudentPDF()"><i class="fas fa-file-pdf"></i> Export PDF</button>
            <button class="btn btn-ghost btn-sm" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<div id="toast-wrap"></div>

<!-- ═══════ JAVASCRIPT ═══════ -->
<script>
const UNIT_ID     = <?= $unit_id ?: 'null' ?>;
const ASSESSMENTS = <?= json_encode(array_values($assessments)) ?>;
const STUDENTS    = <?= json_encode(array_values($students)) ?>;
const SUBMISSIONS = <?= json_encode($submissions) ?>;
const WEIGHTS_PHP = <?= json_encode($weights) ?>;

let currentWeights = {...WEIGHTS_PHP};
let activeStudentId = null;

// ── TABS ───────────────────────────────────────────────────
function showTab(name) {
    ['overview','matrix','submissions','weights'].forEach(t => {
        document.getElementById('pane-'+t).style.display = t === name ? '' : 'none';
        const tab = document.getElementById('tab-'+t);
        if (tab) tab.classList.toggle('active', t === name);
    });
    // update sidebar
    document.querySelectorAll('.sb-menu-item').forEach(el => {
        el.classList.toggle('active', el.textContent.trim().toLowerCase().startsWith(name === 'overview' ? 'over' : name.slice(0,4)));
    });
}

// ── MATRIX SEARCH ─────────────────────────────────────────
function filterMatrix(q) {
    const rows = document.querySelectorAll('#matrix-body .matrix-row');
    q = q.toLowerCase();
    rows.forEach(r => {
        r.style.display = !q || r.dataset.name.includes(q) ? '' : 'none';
    });
}

// ── LOAD ASSESSMENT SUBMISSIONS ───────────────────────────
function loadAssessSubmissions(assessId) {
    if (!assessId) return;
    const assess = ASSESSMENTS.find(a => a.id == assessId);
    if (!assess) return;
    const cont = document.getElementById('assess-subs-content');

    // Build rows
    let rows = '';
    STUDENTS.forEach(s => {
        const sub        = (SUBMISSIONS[s.id] || {})[assessId];
        const submitted  = !!sub;  // true if any submission record exists
        const score      = sub ? sub.score : null;
        const hasScore   = score !== null && score !== undefined && String(score) !== '';
        const passed     = hasScore && parseFloat(score) >= parseFloat(assess.pass_mark);
        const dt         = sub ? new Date(sub.submitted_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';
        const status     = sub ? sub.status : null;
        const isPending  = submitted && !hasScore;

        rows += `<tr>
            <td class="student-name">${esc(s.student_name)}</td>
            <td class="reg-num">${esc(s.registration_number||'—')}</td>
            <td class="score-cell">
                ${!submitted
                    ? `<span class="chip chip-none">—</span>`
                    : isPending
                        ? `<span class="chip chip-pend"><i class="fas fa-hourglass-half"></i> Pending</span>`
                        : `<span class="chip ${passed?'chip-pass':'chip-fail'}">${parseFloat(score).toFixed(1)}%</span>`}
            </td>
            <td style="font-size:.79rem;color:var(--text2)">${submitted ? dt : '—'}</td>
            <td class="score-cell">
                ${!submitted
                    ? `<span class="chip chip-none">—</span>`
                    : isPending
                        ? `<span class="chip chip-pend">Pending</span>`
                        : `<span class="chip ${passed?'chip-pass':'chip-fail'}">${passed?'Pass':'Fail'}</span>`}
            </td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <input type="number" class="grade-input" id="gi-${s.id}-${assessId}"
                           value="${hasScore ? parseFloat(score).toFixed(1) : ''}"
                           placeholder="0–100" min="0" max="100" step="0.5">
                    <button class="btn btn-accent btn-sm" onclick="saveGrade(${s.id},${assessId},${assess.total_marks})">
                        <i class="fas fa-floppy-disk"></i>
                    </button>
                </div>
            </td>
            <td style="display:flex;gap:6px;flex-wrap:wrap">
                ${submitted
                    ? `<a href="grade_submission.php?submission_id=${sub.id}&unit_id=${UNIT_ID}"
                          class="btn btn-purple btn-sm">
                           <i class="fas fa-pen-to-square"></i> Grade
                       </a>`
                    : ''}
                <button class="btn btn-ghost btn-sm" onclick="openStudentModal(${s.id})">
                    <i class="fas fa-eye"></i> Detail
                </button>
            </td>
        </tr>`;
    });

    cont.innerHTML = `
        <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem">${esc(assess.title)}</span>
            <span class="type-hdr hdr-${assess.type}">${assess.type.toUpperCase()}</span>
            <span style="font-size:.78rem;color:var(--text2)">Total Marks: ${assess.total_marks} &nbsp;|&nbsp; Pass: ${assess.pass_mark}%</span>
        </div>
        <div class="tbl-wrap"><div class="tbl-scroll">
        <table>
            <thead><tr>
                <th>Student</th><th>Reg No.</th><th>Score</th>
                <th>Submitted</th><th>Result</th><th>Manual Grade</th><th></th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>
        </div></div>`;
}

// ── SAVE MANUAL GRADE ──────────────────────────────────────
function saveGrade(studentId, assessmentId, totalMarks) {
    const input = document.getElementById(`gi-${studentId}-${assessmentId}`);
    const score = parseFloat(input.value);
    if (isNaN(score) || score < 0 || score > 100) {
        toast('Enter a valid score (0–100)', 'err'); return;
    }
    const fd = new FormData();
    fd.append('student_id',    studentId);
    fd.append('assessment_id', assessmentId);
    fd.append('score',         score);
    fd.append('unit_id',       UNIT_ID);
    fetch('ajax/save_grade.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { toast('Grade saved', 'ok'); }
            else toast(d.message || 'Save failed', 'err');
        })
        .catch(() => toast('Network error', 'err'));
}

// ── STUDENT DETAIL MODAL ───────────────────────────────────
function openStudentModal(studentId) {
    activeStudentId = studentId;
    const s = STUDENTS.find(x => x.id == studentId);
    if (!s) return;
    document.getElementById('modal-student-name').textContent = s.student_name + ' — ' + (s.registration_number||'');

    // Build full performance breakdown
    const typeOrder = ['quiz','assignment','cat','exam'];
    let html = '';
    typeOrder.forEach(type => {
        const typeAssess = ASSESSMENTS.filter(a => a.type === type);
        if (!typeAssess.length) return;
        html += `<h4 style="font-family:'Syne',sans-serif;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--text2);margin:16px 0 8px;display:flex;align-items:center;gap:7px">
            <span class="type-hdr hdr-${type}">${type.toUpperCase()}S</span>
        </h4>
        <table class="sub-tbl"><thead><tr>
            <th>Assessment</th><th>Marks</th><th>Score</th><th>Result</th><th>Date</th>
        </tr></thead><tbody>`;
        typeAssess.forEach(a => {
            const sub    = (SUBMISSIONS[studentId] || {})[a.id];
            const score    = sub ? sub.score : null;
            const hasScore = sub && score !== null && String(score) !== '';
            const passed   = hasScore && parseFloat(score) >= parseFloat(a.pass_mark);
            const dt       = sub ? new Date(sub.submitted_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—';
            html += `<tr>
                <td style="font-weight:500">${esc(a.title)}</td>
                <td class="score-cell">${a.total_marks}</td>
                <td class="score-cell">
                    ${!sub
                        ? '<span class="chip chip-none">—</span>'
                        : !hasScore
                            ? '<span class="chip chip-pend"><i class="fas fa-hourglass-half" style="font-size:.6rem"></i> Pending</span>'
                            : `<span class="chip ${passed?'chip-pass':'chip-fail'}">${parseFloat(score).toFixed(1)}%</span>`}
                </td>
                <td>
                    ${!sub
                        ? '<span class="chip chip-none">Not submitted</span>'
                        : !hasScore
                            ? '<span class="chip chip-pend">Pending grading</span>'
                            : `<span class="chip ${passed?'chip-pass':'chip-fail'}">${passed?'Pass':'Fail'}</span>`}
                </td>
                <td style="font-size:.78rem;color:var(--text2)">${dt}</td>
            </tr>`;
        });
        html += '</tbody></table>';
    });

    // Weighted total
    const subs  = SUBMISSIONS[studentId] || {};
    const wt    = computeWeightedTotal(studentId);
    html += `<div style="margin-top:18px;padding:14px 16px;background:rgba(61,142,248,.06);border:1px solid rgba(61,142,248,.2);border-radius:var(--rs);display:flex;align-items:center;justify-content:space-between">
        <span style="font-family:'Syne',sans-serif;font-size:.82rem;font-weight:700;color:var(--text)">Weighted Total</span>
        <span class="chip chip-total ${wt!==null&&wt>=50?'chip-pass':'chip-fail'}" style="font-size:.88rem">${wt !== null ? wt+'%' : '—'}</span>
    </div>`;

    document.getElementById('modal-body-content').innerHTML = html;
    document.getElementById('student-modal').classList.add('open');
}

function computeWeightedTotal(studentId) {
    const typeScores = {quiz:[],assignment:[],cat:[],exam:[]};
    ASSESSMENTS.forEach(a => {
        const sub = (SUBMISSIONS[studentId]||{})[a.id];
        if (sub && sub.score !== null && sub.score !== '' && !isNaN(parseFloat(sub.score))) {
            typeScores[a.type].push(parseFloat(sub.score));
        }
    });
    let weighted = 0, used = 0;
    Object.entries(typeScores).forEach(([t, sc]) => {
        if (sc.length > 0) {
            const avg = sc.reduce((a,b)=>a+b,0)/sc.length;
            const w   = currentWeights[t] || 0;
            weighted += avg * w / 100;
            used     += w;
        }
    });
    return used > 0 ? Math.round(weighted * 10) / 10 : null;
}

function closeModal() {
    document.getElementById('student-modal').classList.remove('open');
    activeStudentId = null;
}
document.getElementById('student-modal').addEventListener('click', e => {
    if (e.target === document.getElementById('student-modal')) closeModal();
});

// ── WEIGHT CONTROLS ────────────────────────────────────────
function updateWeightTotal() {
    const total = ['quiz','assignment','cat','exam'].reduce((s,t) => {
        return s + (parseFloat(document.getElementById('w-'+t)?.value) || 0);
    }, 0);
    const row  = document.getElementById('weight-total-row');
    const text = document.getElementById('weight-total-text');
    const ok   = Math.abs(total - 100) < 0.01;
    row.className  = 'weight-total ' + (ok ? 'ok' : 'warn');
    row.querySelector('i').className = ok ? 'fas fa-check-circle' : 'fas fa-triangle-exclamation';
    text.textContent = `Total: ${total}% — ${ok ? 'OK' : 'Must equal 100%'}`;
}

function saveWeights() {
    const types = ['quiz','assignment','cat','exam'];
    const total = types.reduce((s,t) => s + (parseFloat(document.getElementById('w-'+t)?.value)||0), 0);
    if (Math.abs(total - 100) > 0.01) { toast('Weights must total 100%', 'err'); return; }
    const fd = new FormData();
    fd.append('unit_id', UNIT_ID);
    types.forEach(t => {
        fd.append('weight_'+t, document.getElementById('w-'+t).value);
        currentWeights[t] = parseFloat(document.getElementById('w-'+t).value);
    });
    fetch('ajax/save_weights.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => toast(d.success ? 'Weights saved!' : (d.message||'Error'), d.success?'ok':'err'))
        .catch(() => toast('Network error','err'));
}

function resetWeights() {
    const defaults = {quiz:20, assignment:20, cat:30, exam:30};
    Object.entries(defaults).forEach(([t,v]) => {
        const el = document.getElementById('w-'+t);
        if (el) el.value = v;
    });
    updateWeightTotal();
}

// ── EXPORT PDF ─────────────────────────────────────────────
function exportPDF() {
    if (!UNIT_ID) { toast('Select a unit first','err'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation:'landscape', unit:'mm', format:'a4' });

    const unitName = document.getElementById('unit-sel')?.selectedOptions[0]?.text || 'Unit';
    doc.setFont('helvetica','bold');
    doc.setFontSize(14);
    doc.text('UNILIS — Grade Report: ' + unitName, 14, 16);
    doc.setFontSize(9);
    doc.setFont('helvetica','normal');
    doc.text('Generated: ' + new Date().toLocaleString(), 14, 22);

    const headers = [['Student', 'Reg No.', ...ASSESSMENTS.map(a => a.title.substring(0,18)), 'Weighted Total']];
    const rows    = STUDENTS.map(s => {
        const row = [s.student_name, s.registration_number || '—'];
        ASSESSMENTS.forEach(a => {
            const sub   = (SUBMISSIONS[s.id]||{})[a.id];
            const score = sub ? sub.score : null;
            row.push(score !== null ? parseFloat(score).toFixed(1)+'%' : '—');
        });
        const wt = computeWeightedTotal(s.id);
        row.push(wt !== null ? wt+'%' : '—');
        return row;
    });

    doc.autoTable({
        startY: 28,
        head:   headers,
        body:   rows,
        styles: { fontSize: 7, cellPadding: 2 },
        headStyles: { fillColor: [26, 36, 56], textColor: [200,220,255], fontStyle:'bold' },
        alternateRowStyles: { fillColor: [15, 22, 36] },
        theme: 'grid',
    });

    doc.save(`grades_${unitName.replace(/\s+/g,'_')}.pdf`);
    toast('PDF downloaded', 'ok');
}

function exportStudentPDF() {
    if (!activeStudentId) return;
    const s = STUDENTS.find(x => x.id == activeStudentId);
    if (!s) return;
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit:'mm', format:'a4' });
    const unitName = document.getElementById('unit-sel')?.selectedOptions[0]?.text || 'Unit';

    doc.setFont('helvetica','bold');
    doc.setFontSize(14);
    doc.text('UNILIS — Student Report', 14, 16);
    doc.setFontSize(10);
    doc.text(`Student: ${s.student_name}  |  Reg: ${s.registration_number||'—'}  |  Unit: ${unitName}`, 14, 24);
    doc.setFont('helvetica','normal');
    doc.setFontSize(8);
    doc.text('Generated: ' + new Date().toLocaleString(), 14, 30);

    const rows = ASSESSMENTS.map(a => {
        const sub    = (SUBMISSIONS[s.id]||{})[a.id];
        const score  = sub ? sub.score : null;
        const passed = score !== null && parseFloat(score) >= parseFloat(a.pass_mark);
        return [
            a.title, a.type.toUpperCase(), a.total_marks,
            score !== null ? parseFloat(score).toFixed(1)+'%' : 'Not submitted',
            score !== null ? (passed ? 'Pass' : 'Fail') : '—',
            sub ? new Date(sub.submitted_at).toLocaleDateString() : '—',
        ];
    });
    const wt = computeWeightedTotal(s.id);
    doc.autoTable({
        startY: 36,
        head: [['Assessment','Type','Marks','Score','Result','Date']],
        body: rows,
        foot: [['','','','Weighted Total →', wt !== null ? wt+'%' : '—', '']],
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [26,36,56], textColor:[200,220,255] },
        footStyles: { fillColor: [20,44,80], textColor:[100,200,255], fontStyle:'bold' },
        theme: 'grid',
    });
    doc.save(`report_${s.student_name.replace(/\s+/g,'_')}.pdf`);
    toast('Student PDF downloaded','ok');
}

// ── EXPORT EXCEL ───────────────────────────────────────────
function exportExcel() {
    if (!UNIT_ID) { toast('Select a unit first','err'); return; }
    const unitName = document.getElementById('unit-sel')?.selectedOptions[0]?.text || 'Unit';
    const wb = XLSX.utils.book_new();

    // Sheet 1: Grade Matrix
    const header = ['Student', 'Registration No.', ...ASSESSMENTS.map(a=>a.title), 'Weighted Total (%)'];
    const data = [header];
    STUDENTS.forEach(s => {
        const row = [s.student_name, s.registration_number||''];
        ASSESSMENTS.forEach(a => {
            const sub   = (SUBMISSIONS[s.id]||{})[a.id];
            const score = sub ? sub.score : null;
            row.push(score !== null ? parseFloat(score).toFixed(1) : '');
        });
        const wt = computeWeightedTotal(s.id);
        row.push(wt !== null ? wt : '');
        data.push(row);
    });
    const ws1 = XLSX.utils.aoa_to_sheet(data);
    // Column widths
    ws1['!cols'] = [
        {wch:28},{wch:16},
        ...ASSESSMENTS.map(()=>({wch:18})),
        {wch:18}
    ];
    XLSX.utils.book_append_sheet(wb, ws1, 'Grade Matrix');

    // Sheet 2: Per-assessment breakdown
    const data2 = [['Assessment','Type','Total Marks','Pass Mark','Student','Reg No.','Score (%)','Result','Submitted At']];
    ASSESSMENTS.forEach(a => {
        STUDENTS.forEach(s => {
            const sub    = (SUBMISSIONS[s.id]||{})[a.id];
            const score  = sub ? sub.score : null;
            const passed = score !== null && parseFloat(score) >= parseFloat(a.pass_mark);
            data2.push([
                a.title, a.type, a.total_marks, a.pass_mark,
                s.student_name, s.registration_number||'',
                score !== null ? parseFloat(score).toFixed(1) : '',
                score !== null ? (passed?'Pass':'Fail') : 'Not submitted',
                sub ? sub.submitted_at : '',
            ]);
        });
    });
    const ws2 = XLSX.utils.aoa_to_sheet(data2);
    ws2['!cols'] = [{wch:24},{wch:12},{wch:12},{wch:10},{wch:28},{wch:16},{wch:10},{wch:14},{wch:20}];
    XLSX.utils.book_append_sheet(wb, ws2, 'By Assessment');

    // Sheet 3: Weights
    const ws3 = XLSX.utils.aoa_to_sheet([
        ['Assessment Type','Weight (%)'],
        ['Quiz',           currentWeights.quiz],
        ['Assignment',     currentWeights.assignment],
        ['CAT',            currentWeights.cat],
        ['Exam',           currentWeights.exam],
    ]);
    XLSX.utils.book_append_sheet(wb, ws3, 'Weights');

    XLSX.writeFile(wb, `grades_${unitName.replace(/\s+/g,'_')}.xlsx`);
    toast('Excel downloaded','ok');
}

// ── UTILS ──────────────────────────────────────────────────
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function toast(msg, type='inf') {
    const w = document.getElementById('toast-wrap');
    const e = document.createElement('div');
    const icons = {ok:'fa-circle-check',err:'fa-circle-xmark',inf:'fa-circle-info'};
    e.className = `toast ${type}`;
    e.innerHTML = `<i class="fas ${icons[type]||'fa-circle-info'}"></i> ${msg}`;
    w.appendChild(e);
    setTimeout(()=>e.remove(), 3000);
}
</script>

</body>
</html>