<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php"); exit;
}

$student_id   = $_SESSION['user_id'];
$student_name = $_SESSION['user_name'];
$unit_id      = intval($_GET['unit_id'] ?? 0);

// ── Enrolled units ────────────────────────────────────────────────────────
// Use semester + academic_year from student_unit_enrollments (now in live DB)
$semester      = intval($_GET['semester']      ?? $_SESSION['cv_semester']      ?? 1);
$academic_year = trim($_GET['academic_year']   ?? $_SESSION['cv_academic_year'] ?? date('Y') . '/' . (date('Y') + 1));
if ($semester < 1 || $semester > 2) $semester = 1;

$enrolled_units = [];
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.semester
        FROM units u
        JOIN student_unit_enrollments sue ON sue.unit_id = u.id
        WHERE sue.student_id     = ?
          AND sue.semester       = ?
          AND sue.academic_year  = ?
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
foreach ($enrolled_units as $u) { if ($u['id'] == $unit_id) { $unit_name = $u['name']; break; } }

// ── Progress data ─────────────────────────────────────────────────────────
$total_lessons      = 0;
$done_lessons       = 0;
$assessment_results = [];
$recent_activity    = [];

if ($unit_id) {
    try {
        // Total lessons for this unit (via course_modules → course_lessons)
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM course_lessons cl
            JOIN course_modules cm ON cm.id = cl.module_id
            WHERE cl.unit_id = ?
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $stmt->bind_result($total_lessons);
        $stmt->fetch();
        $stmt->close();

        // Completed lessons from student_progress
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM student_progress
            WHERE student_id = ? AND unit_id = ? AND event_type = 'lesson_completed'
        ");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $stmt->bind_result($done_lessons);
        $stmt->fetch();
        $stmt->close();

        // Assessment results — join assessments with assessment_submissions (live schema)
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

        // Recent activity from student_progress
        $stmt = $conn->prepare("
            SELECT sp.event_type, sp.score, sp.completed_at AS created_at,
                   cl.title AS lesson_title,
                   a.title  AS assessment_title,
                   sp.lesson_id, sp.assessment_id
            FROM student_progress sp
            LEFT JOIN course_lessons cl  ON sp.lesson_id    = cl.id
            LEFT JOIN assessments a      ON sp.assessment_id = a.id
            WHERE sp.student_id = ? AND sp.unit_id = ?
              AND sp.event_type IS NOT NULL
            ORDER BY sp.completed_at DESC LIMIT 12
        ");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $recent_activity[] = $row;
        $stmt->close();

    } catch (mysqli_sql_exception $e) { error_log("my_progress: " . $e->getMessage()); }
}

$lesson_pct = $total_lessons > 0 ? round(($done_lessons / $total_lessons) * 100) : 0;
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
    --border:#dde3f5;--accent:#4f6ef7;--green:#10b981;--amber:#f59e0b;--red:#ef4444;
    --purple:#8b5cf6;--text:#1e2235;--muted:#64748b;--dim:#a0aec0;
    --r:12px;--rs:7px;--tr:.15s ease;--shadow:0 2px 16px rgba(79,110,247,.08);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 28px;height:58px;
        display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:var(--shadow)}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--accent)}
.brand span{color:var(--muted);font-weight:400;font-size:.8rem;margin-left:8px}
.nav-right{display:flex;align-items:center;gap:10px}
.btn-nav{background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:6px 13px;
         border-radius:var(--rs);font-size:.78rem;cursor:pointer;text-decoration:none;transition:var(--tr);font-family:'DM Sans',sans-serif}
.btn-nav:hover{background:var(--accent);color:#fff;border-color:var(--accent)}

.layout{display:flex;min-height:calc(100vh - 58px)}

/* SIDEBAR */
.sidebar{width:240px;min-width:240px;background:var(--surf);border-right:1px solid var(--border);padding:20px 14px;overflow-y:auto}
.sb-label{font-family:'Syne',sans-serif;font-size:.67rem;font-weight:700;letter-spacing:.12em;
          text-transform:uppercase;color:var(--dim);display:block;margin-bottom:8px}
.unit-link{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:var(--rs);
           text-decoration:none;color:var(--muted);font-size:.84rem;transition:var(--tr);
           border:1px solid transparent;margin-bottom:3px}
.unit-link:hover{background:var(--surf2);color:var(--text)}
.unit-link.active{background:rgba(79,110,247,.08);border-color:rgba(79,110,247,.2);color:var(--accent);font-weight:500}
.sem-chip{font-size:.65rem;padding:1px 6px;border-radius:999px;background:var(--surf3);
          color:var(--dim);border:1px solid var(--border);margin-left:auto;white-space:nowrap}

/* MAIN */
.main{flex:1;padding:28px 32px;max-width:960px;display:flex;flex-direction:column;gap:24px}

/* HERO */
.hero{background:linear-gradient(135deg,#4f6ef7,#7c3aed);border-radius:var(--r);padding:28px 32px;
      color:#fff;position:relative;overflow:hidden}
.hero::after{content:'';position:absolute;right:-40px;top:-40px;width:200px;height:200px;
             border-radius:50%;background:rgba(255,255,255,.06)}
.hero h1{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;margin-bottom:4px}
.hero p{font-size:.85rem;opacity:.8;margin-bottom:18px}
.progress-track{background:rgba(255,255,255,.2);border-radius:999px;height:9px;overflow:hidden;margin-bottom:6px}
.progress-fill{height:100%;border-radius:999px;background:#fff;transition:width .6s ease}
.progress-lbl{font-size:.78rem;opacity:.75}

/* STAT CARDS */
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}
.stat-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);
           padding:16px 18px;box-shadow:var(--shadow);text-align:center}
.stat-num{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;line-height:1}
.stat-lbl{font-size:.75rem;color:var(--muted);margin-top:5px;text-transform:uppercase;letter-spacing:.06em}
.c-blue{color:var(--accent)}.c-green{color:var(--green)}.c-amber{color:var(--amber)}.c-purple{color:var(--purple)}

/* SECTION */
.sec-title{font-family:'Syne',sans-serif;font-size:.82rem;font-weight:700;letter-spacing:.08em;
           text-transform:uppercase;color:var(--muted);margin-bottom:12px;display:flex;align-items:center;gap:8px}

/* ASSESSMENT TABLE */
.assess-table{width:100%;border-collapse:collapse;background:var(--surf);border-radius:var(--r);
              overflow:hidden;box-shadow:var(--shadow);border:1px solid var(--border)}
.assess-table th{background:var(--surf2);font-family:'Syne',sans-serif;font-size:.72rem;font-weight:700;
                 letter-spacing:.08em;text-transform:uppercase;color:var(--muted);padding:10px 14px;
                 text-align:left;border-bottom:1px solid var(--border)}
.assess-table td{padding:12px 14px;border-bottom:1px solid var(--surf3);font-size:.85rem;vertical-align:middle}
.assess-table tr:last-child td{border-bottom:none}
.assess-table tr:hover td{background:var(--surf2)}

.type-pill{font-size:.68rem;padding:2px 8px;border-radius:999px;font-weight:700;text-transform:uppercase;
           letter-spacing:.05em;border:1px solid}
.pill-quiz{background:rgba(79,110,247,.08);color:var(--accent);border-color:rgba(79,110,247,.2)}
.pill-assignment{background:rgba(16,185,129,.08);color:var(--green);border-color:rgba(16,185,129,.2)}
.pill-cat{background:rgba(245,158,11,.08);color:var(--amber);border-color:rgba(245,158,11,.2)}
.pill-exam{background:rgba(239,68,68,.08);color:var(--red);border-color:rgba(239,68,68,.2)}

.score-chip{font-size:.75rem;padding:3px 9px;border-radius:999px;font-weight:600;border:1px solid}
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
.act-icon{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;
          font-size:.85rem;flex-shrink:0}
.act-lesson-completed{background:rgba(16,185,129,.1);color:var(--green)}
.act-lesson-viewed{background:rgba(79,110,247,.1);color:var(--accent)}
.act-quiz-score,.act-assignment-score,.act-cat-score,.act-exam-score{background:rgba(245,158,11,.1);color:var(--amber)}
.act-lab-completed{background:rgba(139,92,246,.1);color:var(--purple)}
.act-body{flex:1}
.act-title{font-size:.87rem;font-weight:500;color:var(--text);margin-bottom:2px}
.act-meta{font-size:.75rem;color:var(--dim)}

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
        <a href="course_view.php<?= $unit_id ? '?unit_id='.$unit_id : '' ?>" class="btn-nav">
            <i class="fas fa-book-open"></i> Course View
        </a>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <span class="sb-label"><i class="fas fa-book"></i> &nbsp;My Units</span>
        <?php if (empty($enrolled_units)): ?>
            <p style="font-size:.8rem;color:var(--dim);padding:8px 4px">
                No units enrolled. <a href="my_units.php" style="color:var(--accent)">Set up My Units</a>
            </p>
        <?php else: ?>
            <?php foreach ($enrolled_units as $u): ?>
            <a class="unit-link <?= $unit_id == $u['id'] ? 'active' : '' ?>"
               href="my_progress.php?unit_id=<?= $u['id'] ?>">
                <i class="fas fa-circle-dot" style="font-size:.45rem"></i>
                <?= htmlspecialchars($u['name']) ?>
                <span class="sem-chip">S<?= $u['semester'] ?></span>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <?php if (!$unit_id): ?>
            <div class="empty"><i class="fas fa-chart-line"></i>Select a unit to view your progress.</div>

        <?php else: ?>

        <!-- HERO -->
        <div class="hero">
            <h1><?= htmlspecialchars($unit_name) ?></h1>
            <p>Lessons completed &nbsp;·&nbsp; <?= $done_lessons ?> of <?= $total_lessons ?></p>
            <div class="progress-track">
                <div class="progress-fill" style="width:<?= $lesson_pct ?>%"></div>
            </div>
            <div class="progress-lbl"><?= $lesson_pct ?>% lesson progress</div>
        </div>

        <!-- STAT CARDS -->
        <?php
        $total_assess   = count($assessment_results);
        $submitted_count = count(array_filter($assessment_results, fn($a) => $a['score'] !== null));
        $passed_count    = count(array_filter($assessment_results, fn($a) => $a['score'] !== null && $a['pass_mark'] && $a['score'] >= $a['pass_mark']));
        $avg_score       = $submitted_count > 0
            ? round(array_sum(array_column(array_filter($assessment_results, fn($a) => $a['score'] !== null), 'score')) / $submitted_count, 1)
            : 0;
        ?>
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
                <div class="stat-lbl">Assessments Passed</div>
            </div>
            <div class="stat-card">
                <div class="stat-num c-purple"><?= $avg_score ?>%</div>
                <div class="stat-lbl">Avg Score</div>
            </div>
        </div>

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
                        <th>Out of</th>
                        <th>Result</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assessment_results as $a):
                    $submitted = $a['score'] !== null;
                    $passed    = $submitted && $a['pass_mark'] && $a['score'] >= $a['pass_mark'];
                    $pending   = $submitted && ($a['status'] !== 'graded');
                ?>
                <tr>
                    <td style="font-weight:500"><?= htmlspecialchars($a['title']) ?></td>
                    <td><span class="type-pill pill-<?= $a['type'] ?? 'quiz' ?>"><?= strtoupper($a['type'] ?? 'Quiz') ?></span></td>
                    <td>
                        <?php if ($submitted): ?>
                            <?= round($a['score'], 1) ?>%
                        <?php else: ?>
                            <span style="color:var(--dim)">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $a['total_marks'] ?></td>
                    <td>
                        <?php if (!$submitted): ?>
                            <span class="score-chip chip-ns">Not Submitted</span>
                        <?php elseif ($pending): ?>
                            <span class="score-chip chip-pending"><i class="fas fa-hourglass-half"></i> Pending</span>
                        <?php elseif ($passed): ?>
                            <span class="score-chip chip-pass"><i class="fas fa-check"></i> Pass</span>
                        <?php else: ?>
                            <span class="score-chip chip-fail"><i class="fas fa-times"></i> Fail</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--muted);font-size:.8rem">
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
                    <div class="empty" style="padding:20px">
                        <i class="fas fa-clock" style="font-size:1.4rem"></i>
                        No activity yet for this unit.
                    </div>
                <?php else: ?>
                    <?php
                    $event_icons = [
                        'lesson_completed'  => 'fa-circle-check',
                        'lesson_viewed'     => 'fa-eye',
                        'quiz_score'        => 'fa-star',
                        'assignment_score'  => 'fa-file-alt',
                        'cat_score'         => 'fa-clipboard-list',
                        'exam_score'        => 'fa-graduation-cap',
                        'lab_completed'     => 'fa-flask',
                    ];
                    $event_labels = [
                        'lesson_completed'  => 'Completed lesson',
                        'lesson_viewed'     => 'Viewed lesson',
                        'quiz_score'        => 'Quiz submitted',
                        'assignment_score'  => 'Assignment submitted',
                        'cat_score'         => 'CAT submitted',
                        'exam_score'        => 'Exam submitted',
                        'lab_completed'     => 'Lab completed',
                    ];
                    foreach ($recent_activity as $act):
                        $type    = $act['event_type'] ?? 'lesson_viewed';
                        $icon    = $event_icons[$type]  ?? 'fa-circle';
                        $label   = $event_labels[$type] ?? ucfirst(str_replace('_',' ',$type));
                        $title   = $act['lesson_title'] ?? $act['assessment_title'] ?? 'Activity';
                        $ts      = $act['created_at'] ? date('d M, H:i', strtotime($act['created_at'])) : '';
                    ?>
                    <div class="activity-item">
                        <div class="act-icon act-<?= $type ?>">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div class="act-body">
                            <div class="act-title"><?= $label ?>: <?= htmlspecialchars($title) ?>
                                <?php if ($act['score'] !== null): ?>
                                    <span class="score-chip chip-pass" style="margin-left:6px"><?= round($act['score'],1) ?>%</span>
                                <?php endif; ?>
                            </div>
                            <div class="act-meta"><?= $ts ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </main>
</div>

</body>
</html>