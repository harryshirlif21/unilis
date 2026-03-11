<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id   = $_SESSION['user_id'];
$student_name = $_SESSION['user_name'];
$unit_id      = intval($_GET['unit_id'] ?? 0);

// Semester / academic year filter (from GET or session)
$semester      = intval($_GET['semester']      ?? $_SESSION['cv_semester']      ?? 1);
$academic_year = trim($_GET['academic_year']   ?? $_SESSION['cv_academic_year'] ?? (date('Y') . '/' . (date('Y') + 1)));
if ($semester < 1 || $semester > 2) $semester = 1;
$_SESSION['cv_semester']      = $semester;
$_SESSION['cv_academic_year'] = $academic_year;

// Academic year options
$current_year   = intval(date('Y'));
$academic_years = [];
for ($y = $current_year - 1; $y <= $current_year + 1; $y++) {
    $academic_years[] = $y . '/' . ($y + 1);
}

// Fetch enrolled units for selected semester/year
$enrolled_units = [];
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.name
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

// If no unit selected, use first enrolled
if (!$unit_id && !empty($enrolled_units)) $unit_id = $enrolled_units[0]['id'];

$unit_name = '';
foreach ($enrolled_units as $u) { if ($u['id'] == $unit_id) { $unit_name = $u['name']; break; } }

// Fetch course outline
$outline = null;
if ($unit_id) {
    try {
        $stmt = $conn->prepare("SELECT description, outline FROM course_outlines WHERE unit_id = ? LIMIT 1");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $outline = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Fetch modules + lessons
$modules = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("SELECT id, title, position FROM course_modules WHERE unit_id = ? ORDER BY position ASC, id ASC");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) { $row['lessons'] = []; $modules[$row['id']] = $row; }
        $stmt->close();

        if (!empty($modules)) {
            $mids = array_keys($modules);
            $ph   = implode(',', array_fill(0, count($mids), '?'));
            $types = str_repeat('i', count($mids));
            $params = array_merge([$unit_id], $mids);
            $stmt = $conn->prepare("SELECT id, module_id, title, lesson_number, position FROM course_lessons WHERE unit_id = ? AND module_id IN ($ph) ORDER BY module_id ASC, position ASC");
            $stmt->bind_param('i'.$types, ...$params);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                if (isset($modules[$row['module_id']])) $modules[$row['module_id']]['lessons'][] = $row;
            }
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Fetch student progress for this unit
$completed_lessons = [];
$assessment_scores = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("SELECT lesson_id, event_type FROM student_progress WHERE student_id = ? AND unit_id = ? AND lesson_id IS NOT NULL");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $completed_lessons[$row['lesson_id']] = $row['event_type'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT assessment_id, score, event_type FROM student_progress WHERE student_id = ? AND unit_id = ? AND assessment_id IS NOT NULL");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $assessment_scores[$row['assessment_id']] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Fetch published assessments for this unit
$assessments = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("SELECT id, title, type, total_marks, pass_mark, time_limit_mins, due_date FROM assessments WHERE unit_id = ? AND is_published = 1 ORDER BY type ASC, created_at ASC");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $assessments[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Overall progress calc
$total_lessons    = array_sum(array_map(fn($m) => count($m['lessons']), $modules));
$done_lessons     = count($completed_lessons);
$progress_pct     = $total_lessons > 0 ? round(($done_lessons / $total_lessons) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($unit_name ?: 'Course') ?> — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
    --bg:#f0f4ff;--surf:#ffffff;--surf2:#f7f9ff;--surf3:#eef1fa;
    --border:#dde3f5;--accent:#4f6ef7;--accent2:#10b981;--accent3:#f59e0b;
    --red:#ef4444;--purple:#8b5cf6;--text:#1e2235;--muted:#64748b;--dim:#a0aec0;
    --r:12px;--rs:7px;--tr:.15s ease;
    --shadow:0 2px 16px rgba(79,110,247,.08);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* TOPBAR */
.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;box-shadow:var(--shadow);position:sticky;top:0;z-index:100}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--accent)}
.brand span{color:var(--muted);font-weight:400;font-size:.8rem;margin-left:8px}
.nav-right{display:flex;align-items:center;gap:10px}
.btn-nav{background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:6px 13px;border-radius:var(--rs);font-size:.78rem;cursor:pointer;text-decoration:none;transition:var(--tr);font-family:'DM Sans',sans-serif}
.btn-nav:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
.student-name{font-size:.82rem;color:var(--muted)}

/* LAYOUT */
.layout{display:flex;gap:0;min-height:calc(100vh - 58px)}

/* SIDEBAR */
.sidebar{width:270px;min-width:270px;background:var(--surf);border-right:1px solid var(--border);padding:20px 16px;overflow-y:auto}
.sb-label{font-family:'Syne',sans-serif;font-size:.67rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);display:block;margin-bottom:8px}
.unit-link{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:var(--rs);text-decoration:none;color:var(--muted);font-size:.85rem;transition:var(--tr);border:1px solid transparent;margin-bottom:3px}
.unit-link:hover{background:var(--surf2);color:var(--text)}
.unit-link.active{background:rgba(79,110,247,.08);border-color:rgba(79,110,247,.2);color:var(--accent);font-weight:500}

/* MAIN */
.main{flex:1;padding:28px 32px;display:flex;flex-direction:column;gap:24px;max-width:900px}

/* HERO CARD */
.hero-card{background:linear-gradient(135deg,#4f6ef7 0%,#7c3aed 100%);border-radius:var(--r);padding:28px 32px;color:#fff;position:relative;overflow:hidden}
.hero-card::after{content:'';position:absolute;right:-40px;top:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06)}
.hero-card h1{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;margin-bottom:6px}
.hero-card p{font-size:.87rem;opacity:.85;max-width:500px;line-height:1.6;margin-bottom:18px}
.progress-bar-wrap{background:rgba(255,255,255,.2);border-radius:999px;height:8px;overflow:hidden;margin-bottom:6px}
.progress-bar-fill{height:100%;border-radius:999px;background:#fff;transition:width .6s ease}
.progress-label{font-size:.78rem;opacity:.8}

/* SECTION HEADER */
.sec-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.sec-title{font-family:'Syne',sans-serif;font-size:.85rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}

/* MODULE CARD */
.module-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow);margin-bottom:12px}
.module-header{background:var(--surf2);padding:13px 18px;display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;border-bottom:1px solid var(--border)}
.module-num{font-family:'Syne',sans-serif;font-size:.68rem;font-weight:700;background:rgba(79,110,247,.1);color:var(--accent);border:1px solid rgba(79,110,247,.2);padding:2px 9px;border-radius:999px}
.module-title{font-family:'Syne',sans-serif;font-size:.9rem;font-weight:700;color:var(--text);flex:1}
.module-progress{font-size:.75rem;color:var(--muted)}
.chevron{color:var(--dim);transition:transform var(--tr)}
.module-header.collapsed .chevron{transform:rotate(-90deg)}

.lessons-list{padding:10px 14px 14px}
.lesson-row{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:var(--rs);text-decoration:none;color:var(--text);transition:var(--tr);border:1px solid transparent;margin-bottom:4px}
.lesson-row:hover{background:var(--surf2);border-color:var(--border)}
.lesson-num{font-family:'Syne',sans-serif;font-size:.68rem;font-weight:700;color:var(--accent);background:rgba(79,110,247,.08);border:1px solid rgba(79,110,247,.15);padding:2px 8px;border-radius:999px;min-width:36px;text-align:center;white-space:nowrap}
.lesson-title{flex:1;font-size:.87rem}
.lesson-status{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;flex-shrink:0}
.status-done{background:rgba(16,185,129,.12);color:var(--accent2);border:1px solid rgba(16,185,129,.25)}
.status-todo{background:var(--surf3);color:var(--dim);border:1px solid var(--border)}

/* ASSESSMENTS SECTION */
.assess-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
.assess-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;text-decoration:none;color:var(--text);transition:var(--tr);box-shadow:var(--shadow);display:flex;flex-direction:column;gap:8px}
.assess-card:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 6px 24px rgba(79,110,247,.12)}
.assess-type-row{display:flex;align-items:center;gap:8px}
.type-pill{font-size:.68rem;padding:2px 9px;border-radius:999px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
.pill-quiz{background:rgba(79,110,247,.1);color:var(--accent);border:1px solid rgba(79,110,247,.2)}
.pill-assignment{background:rgba(16,185,129,.1);color:var(--accent2);border:1px solid rgba(16,185,129,.2)}
.pill-cat{background:rgba(245,158,11,.1);color:var(--accent3);border:1px solid rgba(245,158,11,.2)}
.pill-exam{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2)}
.assess-title{font-family:'Syne',sans-serif;font-size:.9rem;font-weight:700}
.assess-meta{font-size:.78rem;color:var(--muted);display:flex;gap:12px;flex-wrap:wrap}
.assess-meta span{display:flex;align-items:center;gap:4px}
.score-chip{font-size:.75rem;padding:3px 9px;border-radius:999px;font-weight:600}
.chip-pass{background:rgba(16,185,129,.1);color:var(--accent2);border:1px solid rgba(16,185,129,.2)}
.chip-fail{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2)}
.chip-pending{background:rgba(245,158,11,.1);color:var(--accent3);border:1px solid rgba(245,158,11,.2)}

/* EMPTY */
.empty{text-align:center;padding:40px;color:var(--dim);font-size:.85rem}

.sb-select{
    width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--text);
    padding:8px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.83rem;
    outline:none;cursor:pointer;transition:border-color var(--tr);appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 10px center;padding-right:28px;
}
.sb-select:focus{border-color:var(--accent)}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">UNILIS <span>Course View</span></div>
    <div class="nav-right">
        <span class="student-name"><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($student_name) ?></span>
        <a href="my_progress.php?unit_id=<?= $unit_id ?>" class="btn-nav"><i class="fas fa-chart-line"></i> My Progress</a>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <!-- Semester & Academic Year dropdowns -->
        <form method="GET" action="course_view.php" id="semester-form" style="margin-bottom:16px">
            <?php if ($unit_id): ?>
            <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
            <?php endif; ?>

            <span class="sb-label" style="margin-bottom:6px"><i class="fas fa-calendar-alt"></i> &nbsp;Semester</span>
            <select name="semester" class="sb-select" onchange="document.getElementById('semester-form').submit()">
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2</option>
            </select>

            <span class="sb-label" style="margin-top:10px;margin-bottom:6px"><i class="fas fa-graduation-cap"></i> &nbsp;Academic Year</span>
            <select name="academic_year" class="sb-select" onchange="document.getElementById('semester-form').submit()">
                <?php foreach ($academic_years as $ay): ?>
                <option value="<?= $ay ?>" <?= $academic_year === $ay ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ay) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div style="border-top:1px solid var(--border);margin-bottom:14px"></div>

        <!-- Unit list -->
        <span class="sb-label"><i class="fas fa-book-open"></i> &nbsp;My Units
            <span style="float:right;font-weight:400;text-transform:none;letter-spacing:0;font-size:.72rem;color:var(--dim)"><?= count($enrolled_units) ?></span>
        </span>

        <?php if (empty($enrolled_units)): ?>
            <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 14px;margin-top:4px">
                <p style="font-size:.8rem;color:#c2410c;margin-bottom:8px">
                    <i class="fas fa-triangle-exclamation"></i>
                    No units enrolled for Semester <?= $semester ?>.
                </p>
                <a href="my_units.php" style="font-size:.8rem;color:#ea580c;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:5px">
                    <i class="fas fa-plus-circle"></i> Set up My Units
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($enrolled_units as $u): ?>
                <a class="unit-link <?= $unit_id == $u['id'] ? 'active' : '' ?>"
                   href="course_view.php?unit_id=<?= $u['id'] ?>&semester=<?= $semester ?>&academic_year=<?= urlencode($academic_year) ?>">
                    <i class="fas fa-circle-dot" style="font-size:.5rem"></i>
                    <?= htmlspecialchars($u['name']) ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:12px">
            <a href="my_units.php" style="font-size:.78rem;color:var(--accent);text-decoration:none;display:flex;align-items:center;gap:6px">
                <i class="fas fa-pen-to-square"></i> Edit enrolled units
            </a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <?php if (!$unit_id): ?>
            <div class="empty"><i class="fas fa-book-open" style="font-size:2rem;margin-bottom:12px;display:block"></i>Select a unit from the sidebar.</div>
        <?php else: ?>

        <!-- HERO -->
        <div class="hero-card">
            <h1><?= htmlspecialchars($unit_name) ?></h1>
            <p><?= htmlspecialchars($outline['description'] ?? 'No description available.') ?></p>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" style="width:<?= $progress_pct ?>%"></div>
            </div>
            <div class="progress-label">
                <?= $done_lessons ?> / <?= $total_lessons ?> lessons completed &nbsp;·&nbsp; <?= $progress_pct ?>%
            </div>
        </div>

        <!-- MODULES & LESSONS -->
        <div>
            <div class="sec-header">
                <span class="sec-title"><i class="fas fa-layer-group"></i> &nbsp;Course Content</span>
                <span style="font-size:.8rem;color:var(--muted)"><?= count($modules) ?> module<?= count($modules)!=1?'s':'' ?></span>
            </div>

            <?php if (empty($modules)): ?>
                <div class="empty">No content available yet.</div>
            <?php else: ?>
                <?php foreach (array_values($modules) as $mi => $mod): ?>
                <?php
                    $mod_done  = count(array_filter($mod['lessons'], fn($l) => isset($completed_lessons[$l['id']])));
                    $mod_total = count($mod['lessons']);
                ?>
                <div class="module-card">
                    <div class="module-header" onclick="toggleModule(this)">
                        <span class="module-num">M<?= $mi + 1 ?></span>
                        <span class="module-title"><?= htmlspecialchars($mod['title']) ?></span>
                        <span class="module-progress"><?= $mod_done ?>/<?= $mod_total ?></span>
                        <i class="fas fa-chevron-down chevron"></i>
                    </div>
                    <div class="lessons-list">
                        <?php if (empty($mod['lessons'])): ?>
                            <p style="color:var(--dim);font-size:.82rem;padding:8px 6px">No lessons yet.</p>
                        <?php else: ?>
                            <?php foreach ($mod['lessons'] as $lesson): ?>
                            <?php $done = isset($completed_lessons[$lesson['id']]); ?>
                            <a class="lesson-row"
                               href="lesson_view.php?lesson_id=<?= $lesson['id'] ?>&unit_id=<?= $unit_id ?>">
                                <span class="lesson-num">L<?= $lesson['lesson_number'] ?></span>
                                <span class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></span>
                                <span class="lesson-status <?= $done ? 'status-done' : 'status-todo' ?>">
                                    <i class="fas <?= $done ? 'fa-check' : 'fa-circle' ?>"></i>
                                </span>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ASSESSMENTS -->
        <?php if (!empty($assessments)): ?>
        <div>
            <div class="sec-header">
                <span class="sec-title"><i class="fas fa-tasks"></i> &nbsp;Assessments</span>
            </div>
            <div class="assess-grid">
                <?php foreach ($assessments as $a): ?>
                <?php
                    $score_data = $assessment_scores[$a['id']] ?? null;
                    $submitted  = $score_data !== null;
                    $score      = $score_data['score'] ?? null;
                    $passed     = $score !== null && $score >= $a['pass_mark'];
                ?>
                <a class="assess-card"
                   href="<?= $submitted ? '#' : 'take_assessment.php?assessment_id='.$a['id'] ?>"
                   <?= $submitted ? 'style="cursor:default;opacity:.85"' : '' ?>>
                    <div class="assess-type-row">
                        <span class="type-pill pill-<?= $a['type'] ?>"><?= strtoupper($a['type']) ?></span>
                        <?php if ($submitted): ?>
                            <?php if ($score !== null): ?>
                                <span class="score-chip <?= $passed ? 'chip-pass' : 'chip-fail' ?>">
                                    <?= round($score) ?>% — <?= $passed ? 'Pass' : 'Fail' ?>
                                </span>
                            <?php else: ?>
                                <span class="score-chip chip-pending"><i class="fas fa-hourglass-half"></i> Pending</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="assess-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="assess-meta">
                        <span><i class="fas fa-star"></i> <?= $a['total_marks'] ?> marks</span>
                        <span><i class="fas fa-check-circle"></i> Pass: <?= $a['pass_mark'] ?></span>
                        <?php if ($a['time_limit_mins']): ?>
                        <span><i class="fas fa-clock"></i> <?= $a['time_limit_mins'] ?>min</span>
                        <?php endif; ?>
                        <?php if ($a['due_date']): ?>
                        <span><i class="fas fa-calendar"></i> <?= date('d M', strtotime($a['due_date'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$submitted): ?>
                    <div style="margin-top:4px;font-size:.78rem;color:var(--accent);font-weight:500">
                        <i class="fas fa-arrow-right"></i> Start <?= ucfirst($a['type']) ?>
                    </div>
                    <?php else: ?>
                    <div style="margin-top:4px;font-size:.78rem;color:var(--muted)">
                        <i class="fas fa-circle-check"></i> Submitted
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </main>
</div>

<script>
function toggleModule(header) {
    header.classList.toggle('collapsed');
    const list = header.nextElementSibling;
    list.style.display = header.classList.contains('collapsed') ? 'none' : '';
}
</script>
</body>
</html>