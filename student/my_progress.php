<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php"); exit;
}

$student_id   = $_SESSION['user_id'];
$student_name = $_SESSION['user_name'] ?? '';
$unit_id      = intval($_GET['unit_id'] ?? 0);

// ── Semester / academic year ──────────────────────────────────────────────
$current_year  = intval(date('Y'));
$default_ay    = $current_year . '/' . ($current_year + 1);
$academic_years = [];
for ($y = $current_year - 1; $y <= $current_year + 1; $y++) {
    $academic_years[] = $y . '/' . ($y + 1);
}

$semester      = intval($_GET['semester']     ?? $_SESSION['cv_semester']      ?? 1);
$academic_year = trim($_GET['academic_year']  ?? $_SESSION['cv_academic_year'] ?? $default_ay);
if ($semester < 1 || $semester > 2) $semester = 1;
if (!in_array($academic_year, $academic_years)) $academic_year = $default_ay;

$_SESSION['cv_semester']      = $semester;
$_SESSION['cv_academic_year'] = $academic_year;

// ── Enrolled units ────────────────────────────────────────────────────────
$enrolled_units = [];
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.semester
        FROM units u
        JOIN student_unit_enrollments sue ON sue.unit_id = u.id
        WHERE sue.student_id    = ?
          AND sue.semester      = ?
          AND sue.academic_year = ?
        ORDER BY u.name ASC
    ");
    $stmt->bind_param("iis", $student_id, $semester, $academic_year);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $enrolled_units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

if (!$unit_id && !empty($enrolled_units)) $unit_id = $enrolled_units[0]['id'];
$unit_name = '';
foreach ($enrolled_units as $u) {
    if ($u['id'] == $unit_id) { $unit_name = $u['name']; break; }
}

// ── Progress data ─────────────────────────────────────────────────────────
$total_lessons      = 0;
$done_lessons       = 0;
$assessment_results = [];
$recent_activity    = [];
$module_progress    = [];

if ($unit_id) {
    try {
        // Total lessons
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM course_lessons WHERE unit_id = ?
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $stmt->bind_result($total_lessons);
        $stmt->fetch();
        $stmt->close();

        // Completed lessons
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM student_progress
            WHERE student_id = ? AND unit_id = ? AND event_type = 'lesson_completed'
        ");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $stmt->bind_result($done_lessons);
        $stmt->fetch();
        $stmt->close();

        // Per-module progress
        $stmt = $conn->prepare("
            SELECT
                cm.id        AS module_id,
                cm.title     AS module_title,
                COUNT(cl.id) AS total,
                SUM(CASE WHEN sp.event_type = 'lesson_completed' THEN 1 ELSE 0 END) AS done
            FROM course_modules cm
            LEFT JOIN course_lessons cl ON cl.module_id = cm.id
            LEFT JOIN student_progress sp
                ON sp.lesson_id = cl.id
               AND sp.student_id = ?
               AND sp.event_type = 'lesson_completed'
            WHERE cm.unit_id = ?
            GROUP BY cm.id, cm.title
            ORDER BY cm.position ASC
        ");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $module_progress[] = $row;
        $stmt->close();

        // Assessment results
        $stmt = $conn->prepare("
            SELECT a.id, a.title, a.type, a.total_marks, a.pass_mark,
                   asub.score, asub.submitted_at, asub.status
            FROM assessments a
            LEFT JOIN assessment_submissions asub
                ON asub.assessment_id = a.id AND asub.student_id = ?
            WHERE a.unit_id = ? AND a.is_published = 1
            ORDER BY a.type ASC, a.created_at ASC
        ");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $assessment_results[] = $row;
        $stmt->close();

        // Recent activity — exclude lesson_viewed (too noisy), show meaningful events only
        $stmt = $conn->prepare("
            SELECT sp.event_type, sp.score, sp.completed_at,
                   cl.title  AS lesson_title,
                   a.title   AS assessment_title,
                   sp.lesson_id, sp.assessment_id
            FROM student_progress sp
            LEFT JOIN course_lessons cl ON sp.lesson_id     = cl.id
            LEFT JOIN assessments    a  ON sp.assessment_id = a.id
            WHERE sp.student_id  = ?
              AND sp.unit_id     = ?
              AND sp.event_type != 'lesson_viewed'
            ORDER BY sp.completed_at DESC
            LIMIT 15
        ");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $recent_activity[] = $row;
        $stmt->close();

    } catch (mysqli_sql_exception $e) { error_log("my_progress: " . $e->getMessage()); }
}

$lesson_pct      = $total_lessons > 0 ? round(($done_lessons / $total_lessons) * 100) : 0;
$submitted_count = count(array_filter($assessment_results, fn($a) => $a['score'] !== null));
$passed_count    = count(array_filter($assessment_results, fn($a) =>
    $a['score'] !== null && $a['pass_mark'] > 0 && $a['score'] >= $a['pass_mark']
));
$scores          = array_filter(array_column($assessment_results, 'score'), fn($s) => $s !== null);
$avg_score       = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Progress — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
    --bg:#f0f4ff;--surf:#fff;--surf2:#f7f9ff;--surf3:#eef1fa;
    --border:#dde3f5;--accent:#4f6ef7;--green:#10b981;--amber:#f59e0b;
    --red:#ef4444;--purple:#8b5cf6;--text:#1e2235;--muted:#64748b;--dim:#a0aec0;
    --r:12px;--rs:7px;--tr:.15s ease;--shadow:0 2px 16px rgba(79,110,247,.08);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* TOPBAR */
.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 28px;height:58px;
        display:flex;align-items:center;justify-content:space-between;
        position:sticky;top:0;z-index:100;box-shadow:var(--shadow)}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--accent)}
.brand span{color:var(--muted);font-weight:400;font-size:.8rem;margin-left:8px}
.nav-right{display:flex;align-items:center;gap:10px}
.btn-nav{background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:6px 13px;
         border-radius:var(--rs);font-size:.78rem;cursor:pointer;text-decoration:none;
         transition:var(--tr);font-family:'DM Sans',sans-serif}
.btn-nav:hover{background:var(--accent);color:#fff;border-color:var(--accent)}

.layout{display:flex;min-height:calc(100vh - 58px)}

/* SIDEBAR */
.sidebar{width:248px;min-width:248px;background:var(--surf);border-right:1px solid var(--border);
         padding:18px 14px;overflow-y:auto;position:sticky;top:58px;height:calc(100vh - 58px)}
.sb-label{font-family:'Syne',sans-serif;font-size:.67rem;font-weight:700;letter-spacing:.12em;
          text-transform:uppercase;color:var(--dim);display:block;margin-bottom:6px}
.sb-select{width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--text);
           padding:8px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.83rem;
           outline:none;cursor:pointer;transition:border-color var(--tr);appearance:none;
           background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
           background-repeat:no-repeat;background-position:right 10px center;padding-right:28px}
.sb-select:focus{border-color:var(--accent)}
.unit-link{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:var(--rs);
           text-decoration:none;color:var(--muted);font-size:.84rem;transition:var(--tr);
           border:1px solid transparent;margin-bottom:3px}
.unit-link:hover{background:var(--surf2);color:var(--text)}
.unit-link.active{background:rgba(79,110,247,.08);border-color:rgba(79,110,247,.2);
                  color:var(--accent);font-weight:500}

/* MAIN */
.main{flex:1;padding:28px 32px;max-width:960px;display:flex;flex-direction:column;gap:22px}

/* HERO */
.hero{background:linear-gradient(135deg,#4f6ef7,#7c3aed);border-radius:var(--r);
      padding:26px 30px;color:#fff;position:relative;overflow:hidden}
.hero::after{content:'';position:absolute;right:-40px;top:-40px;width:200px;height:200px;
             border-radius:50%;background:rgba(255,255,255,.06)}
.hero h1{font-family:'Syne',sans-serif;font-size:1.25rem;font-weight:800;margin-bottom:4px}
.hero-sub{font-size:.83rem;opacity:.8;margin-bottom:16px}
.progress-track{background:rgba(255,255,255,.22);border-radius:999px;height:9px;
                overflow:hidden;margin-bottom:6px}
.progress-fill{height:100%;border-radius:999px;background:#fff;transition:width .7s ease}
.progress-lbl{font-size:.77rem;opacity:.75;display:flex;justify-content:space-between}

/* STAT CARDS */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
@media(max-width:700px){.stat-row{grid-template-columns:repeat(2,1fr)}}
.stat-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);
           padding:16px 18px;box-shadow:var(--shadow);text-align:center}
.stat-num{font-family:'Syne',sans-serif;font-size:1.9rem;font-weight:800;line-height:1.1}
.stat-lbl{font-size:.72rem;color:var(--muted);margin-top:5px;text-transform:uppercase;letter-spacing:.06em}
.c-blue{color:var(--accent)}.c-green{color:var(--green)}
.c-amber{color:var(--amber)}.c-purple{color:var(--purple)}

/* SECTION TITLE */
.sec-title{font-family:'Syne',sans-serif;font-size:.8rem;font-weight:700;letter-spacing:.08em;
           text-transform:uppercase;color:var(--muted);margin-bottom:12px;
           display:flex;align-items:center;gap:8px}

/* MODULE PROGRESS */
.module-progress-list{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);
                      padding:6px 0;box-shadow:var(--shadow)}
.mod-row{display:flex;align-items:center;gap:14px;padding:11px 18px;
         border-bottom:1px solid var(--surf3)}
.mod-row:last-child{border-bottom:none}
.mod-name{flex:1;font-size:.87rem;font-weight:500;color:var(--text)}
.mod-count{font-size:.78rem;color:var(--muted);white-space:nowrap;margin-right:8px}
.mod-bar-wrap{width:120px;background:var(--surf3);border-radius:999px;
              height:6px;overflow:hidden;flex-shrink:0}
.mod-bar-fill{height:100%;border-radius:999px;background:var(--accent);transition:width .5s ease}
.mod-bar-fill.full{background:var(--green)}
.mod-pct{font-size:.72rem;font-weight:700;color:var(--muted);width:34px;text-align:right;flex-shrink:0}

/* ASSESSMENT TABLE */
.assess-table{width:100%;border-collapse:collapse;background:var(--surf);border-radius:var(--r);
              overflow:hidden;box-shadow:var(--shadow);border:1px solid var(--border)}
.assess-table th{background:var(--surf2);font-family:'Syne',sans-serif;font-size:.7rem;font-weight:700;
                 letter-spacing:.08em;text-transform:uppercase;color:var(--muted);
                 padding:10px 14px;text-align:left;border-bottom:1px solid var(--border)}
.assess-table td{padding:11px 14px;border-bottom:1px solid var(--surf3);
                 font-size:.85rem;vertical-align:middle}
.assess-table tr:last-child td{border-bottom:none}
.assess-table tr:hover td{background:var(--surf2)}
.type-pill{font-size:.67rem;padding:2px 8px;border-radius:999px;font-weight:700;
           text-transform:uppercase;letter-spacing:.04em;border:1px solid}
.pill-quiz{background:rgba(79,110,247,.08);color:var(--accent);border-color:rgba(79,110,247,.2)}
.pill-assignment{background:rgba(16,185,129,.08);color:var(--green);border-color:rgba(16,185,129,.2)}
.pill-cat{background:rgba(245,158,11,.08);color:var(--amber);border-color:rgba(245,158,11,.2)}
.pill-exam{background:rgba(239,68,68,.08);color:var(--red);border-color:rgba(239,68,68,.2)}
.score-chip{font-size:.74rem;padding:3px 9px;border-radius:999px;font-weight:600;border:1px solid}
.chip-pass{background:rgba(16,185,129,.1);color:var(--green);border-color:rgba(16,185,129,.25)}
.chip-fail{background:rgba(239,68,68,.1);color:var(--red);border-color:rgba(239,68,68,.25)}
.chip-pending{background:rgba(245,158,11,.1);color:var(--amber);border-color:rgba(245,158,11,.25)}
.chip-ns{background:var(--surf3);color:var(--dim);border-color:var(--border)}

/* ACTIVITY FEED */
.activity-feed{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);
               padding:16px 18px;box-shadow:var(--shadow)}
.activity-item{display:flex;align-items:flex-start;gap:12px;padding:10px 0;
               border-bottom:1px solid var(--surf3)}
.activity-item:last-child{border-bottom:none}
.act-icon{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;
          justify-content:center;font-size:.85rem;flex-shrink:0}
.act-lesson_completed{background:rgba(16,185,129,.1);color:var(--green)}
.act-quiz_score,.act-cat_score,.act-exam_score,.act-assignment_score{background:rgba(245,158,11,.1);color:var(--amber)}
.act-lab_completed{background:rgba(139,92,246,.1);color:var(--purple)}
.act-body{flex:1}
.act-title{font-size:.87rem;font-weight:500;color:var(--text);margin-bottom:2px}
.act-meta{font-size:.74rem;color:var(--dim)}

.empty{text-align:center;padding:36px;color:var(--dim);font-size:.85rem}
.empty i{font-size:1.8rem;margin-bottom:10px;display:block;opacity:.3}

::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">UNILIS <span>My Progress</span></div>
    <div class="nav-right">
        <span style="font-size:.82rem;color:var(--muted)">
            <i class="fas fa-user-graduate"></i> <?= htmlspecialchars($student_name) ?>
        </span>
        <a href="course_view.php?unit_id=<?= $unit_id ?>&semester=<?= $semester ?>&academic_year=<?= urlencode($academic_year) ?>"
           class="btn-nav">
            <i class="fas fa-book-open"></i> Course View
        </a>
        <a href="dashboard.php" class="btn-nav">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>
</header>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <!-- Semester + Academic Year -->
        <form method="GET" id="filter-form" style="margin-bottom:16px">
            <?php if ($unit_id): ?>
                <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
            <?php endif; ?>

            <span class="sb-label" style="margin-bottom:5px">
                <i class="fas fa-calendar-alt"></i> &nbsp;Semester
            </span>
            <select name="semester" class="sb-select"
                    onchange="document.getElementById('filter-form').submit()">
                <option value="1" <?= $semester===1?'selected':'' ?>>Semester 1</option>
                <option value="2" <?= $semester===2?'selected':'' ?>>Semester 2</option>
            </select>

            <span class="sb-label" style="margin-top:10px;margin-bottom:5px">
                <i class="fas fa-graduation-cap"></i> &nbsp;Academic Year
            </span>
            <select name="academic_year" class="sb-select"
                    onchange="document.getElementById('filter-form').submit()">
                <?php foreach ($academic_years as $ay): ?>
                <option value="<?= $ay ?>" <?= $academic_year===$ay?'selected':'' ?>>
                    <?= htmlspecialchars($ay) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div style="border-top:1px solid var(--border);margin-bottom:12px"></div>

        <span class="sb-label">
            <i class="fas fa-book"></i> &nbsp;My Units
            <span style="float:right;font-weight:400;text-transform:none;letter-spacing:0;font-size:.72rem;color:var(--dim)">
                <?= count($enrolled_units) ?>
            </span>
        </span>

        <?php if (empty($enrolled_units)): ?>
            <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 14px;margin-top:4px">
                <p style="font-size:.8rem;color:#c2410c;margin-bottom:6px">
                    <i class="fas fa-triangle-exclamation"></i>
                    No units for Semester <?= $semester ?>.
                </p>
                <a href="my_units.php" style="font-size:.8rem;color:#ea580c;font-weight:600;text-decoration:none">
                    <i class="fas fa-plus-circle"></i> Set up My Units
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($enrolled_units as $u): ?>
            <a class="unit-link <?= $unit_id==$u['id']?'active':'' ?>"
               href="my_progress.php?unit_id=<?= $u['id'] ?>&semester=<?= $semester ?>&academic_year=<?= urlencode($academic_year) ?>">
                <i class="fas fa-circle-dot" style="font-size:.45rem"></i>
                <?= htmlspecialchars($u['name']) ?>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <?php if (!$unit_id): ?>
            <div class="empty">
                <i class="fas fa-chart-line"></i>
                Select a unit from the sidebar to view your progress.
            </div>

        <?php else: ?>

        <!-- HERO -->
        <div class="hero">
            <h1><?= htmlspecialchars($unit_name) ?></h1>
            <div class="hero-sub">
                Semester <?= $semester ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($academic_year) ?> &nbsp;·&nbsp;
                <?= $done_lessons ?> of <?= $total_lessons ?> lessons complete
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width:<?= $lesson_pct ?>%"></div>
            </div>
            <div class="progress-lbl">
                <span><?= $lesson_pct ?>% complete</span>
                <span><?= $total_lessons - $done_lessons ?> lessons remaining</span>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-num c-blue"><?= $lesson_pct ?>%</div>
                <div class="stat-lbl">Lesson Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-num c-green"><?= $submitted_count ?></div>
                <div class="stat-lbl">Assessments Done</div>
            </div>
            <div class="stat-card">
                <div class="stat-num c-amber"><?= $passed_count ?></div>
                <div class="stat-lbl">Passed</div>
            </div>
            <div class="stat-card">
                <div class="stat-num c-purple"><?= $avg_score ?>%</div>
                <div class="stat-lbl">Avg Score</div>
            </div>
        </div>

        <!-- MODULE BREAKDOWN -->
        <?php if (!empty($module_progress)): ?>
        <div>
            <div class="sec-title"><i class="fas fa-layer-group"></i> Module Breakdown</div>
            <div class="module-progress-list">
                <?php foreach ($module_progress as $i => $mod):
                    $pct = $mod['total'] > 0 ? round(($mod['done'] / $mod['total']) * 100) : 0;
                ?>
                <div class="mod-row">
                    <span style="font-family:'Syne',sans-serif;font-size:.68rem;font-weight:700;
                                 color:var(--dim);min-width:24px">M<?= $i+1 ?></span>
                    <span class="mod-name"><?= htmlspecialchars($mod['module_title']) ?></span>
                    <span class="mod-count"><?= intval($mod['done']) ?>/<?= intval($mod['total']) ?></span>
                    <div class="mod-bar-wrap">
                        <div class="mod-bar-fill <?= $pct==100?'full':'' ?>"
                             style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="mod-pct"><?= $pct ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ASSESSMENT RESULTS -->
        <?php if (!empty($assessment_results)): ?>
        <div>
            <div class="sec-title"><i class="fas fa-tasks"></i> Assessment Results</div>
            <table class="assess-table">
                <thead>
                    <tr>
                        <th>Assessment</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Marks</th>
                        <th>Result</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assessment_results as $a):
                    $submitted = $a['score'] !== null;
                    $passed    = $submitted && $a['pass_mark'] > 0 && $a['score'] >= $a['pass_mark'];
                    $pending   = $submitted && $a['status'] !== 'graded';
                ?>
                <tr>
                    <td style="font-weight:500"><?= htmlspecialchars($a['title']) ?></td>
                    <td>
                        <span class="type-pill pill-<?= $a['type'] ?>">
                            <?= strtoupper($a['type']) ?>
                        </span>
                    </td>
                    <td>
                        <?= $submitted ? round($a['score'], 1) . '%' : '<span style="color:var(--dim)">—</span>' ?>
                    </td>
                    <td style="color:var(--muted)"><?= $a['total_marks'] ?></td>
                    <td>
                        <?php if (!$submitted): ?>
                            <span class="score-chip chip-ns">Not Submitted</span>
                        <?php elseif ($pending): ?>
                            <span class="score-chip chip-pending">
                                <i class="fas fa-hourglass-half"></i> Pending
                            </span>
                        <?php elseif ($passed): ?>
                            <span class="score-chip chip-pass">
                                <i class="fas fa-check"></i> Pass
                            </span>
                        <?php else: ?>
                            <span class="score-chip chip-fail">
                                <i class="fas fa-times"></i> Fail
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--muted);font-size:.79rem">
                        <?= $a['submitted_at'] ? date('d M Y', strtotime($a['submitted_at'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- RECENT ACTIVITY -->
        <div>
            <div class="sec-title"><i class="fas fa-history"></i> Recent Activity</div>
            <div class="activity-feed">
                <?php if (empty($recent_activity)): ?>
                    <div class="empty" style="padding:24px">
                        <i class="fas fa-clock" style="font-size:1.4rem"></i>
                        No activity yet. Start a lesson to see your progress here.
                    </div>
                <?php else: ?>
                    <?php
                    $event_icons = [
                        'lesson_completed'  => 'fa-circle-check',
                        'quiz_score'        => 'fa-star',
                        'assignment_score'  => 'fa-file-alt',
                        'cat_score'         => 'fa-clipboard-list',
                        'exam_score'        => 'fa-graduation-cap',
                        'lab_completed'     => 'fa-flask',
                    ];
                    $event_labels = [
                        'lesson_completed'  => 'Completed',
                        'quiz_score'        => 'Quiz',
                        'assignment_score'  => 'Assignment',
                        'cat_score'         => 'CAT',
                        'exam_score'        => 'Exam',
                        'lab_completed'     => 'Lab completed',
                    ];
                    foreach ($recent_activity as $act):
                        $type  = $act['event_type'] ?? 'lesson_completed';
                        $icon  = $event_icons[$type]  ?? 'fa-circle';
                        $label = $event_labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
                        $name  = $act['lesson_title'] ?? $act['assessment_title'] ?? 'Activity';
                        $ts    = $act['completed_at'] ? date('d M Y, H:i', strtotime($act['completed_at'])) : '';
                    ?>
                    <div class="activity-item">
                        <div class="act-icon act-<?= $type ?>">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div class="act-body">
                            <div class="act-title">
                                <?= $label ?>: <span style="color:var(--muted)"><?= htmlspecialchars($name) ?></span>
                                <?php if ($act['score'] !== null): ?>
                                    <span class="score-chip chip-pass" style="margin-left:8px;font-size:.72rem">
                                        <?= round($act['score'], 1) ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="act-meta"><?= $ts ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; // $unit_id ?>
    </main>
</div>

</body>
</html>