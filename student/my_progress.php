<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php"); exit;
}

$student_id   = $_SESSION['user_id'];
$student_name = $_SESSION['user_name'];
$unit_id      = intval($_GET['unit_id'] ?? 0);

// Enrolled units
$enrolled_units = [];
try {
    $stmt = $conn->prepare("SELECT u.id, u.name FROM units u JOIN student_units su ON u.id = su.unit_id WHERE su.student_id = ? ORDER BY u.name");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $enrolled_units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

if (!$unit_id && !empty($enrolled_units)) $unit_id = $enrolled_units[0]['id'];
$unit_name = '';
foreach ($enrolled_units as $u) { if ($u['id'] == $unit_id) { $unit_name = $u['name']; break; } }

// ── PROGRESS DATA ────────────────────────────────────────
$total_lessons = $done_lessons = 0;
$assessment_results = [];
$recent_activity = [];

if ($unit_id) {
    try {
        // Lesson counts
        $stmt = $conn->prepare("SELECT COUNT(*) FROM course_lessons WHERE unit_id = ?");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute(); $stmt->bind_result($total_lessons); $stmt->fetch(); $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) FROM student_progress WHERE student_id = ? AND unit_id = ? AND event_type = 'lesson_completed'");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute(); $stmt->bind_result($done_lessons); $stmt->fetch(); $stmt->close();

        // Assessment scores
        $stmt = $conn->prepare("
            SELECT a.id, a.title, a.type, a.total_marks, a.pass_mark,
                   sp.score, sp.created_at AS submitted_at,
                   asub.status
            FROM assessments a
            LEFT JOIN student_progress sp ON sp.assessment_id = a.id AND sp.student_id = ?
            LEFT JOIN assessment_submissions asub ON asub.assessment_id = a.id AND asub.student_id = ?
            WHERE a.unit_id = ? AND a.is_published = 1
            ORDER BY a.type ASC, a.created_at ASC
        ");
        $stmt->bind_param("iii", $student_id, $student_id, $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $assessment_results[] = $row;
        $stmt->close();

        // Recent activity (last 10 events)
        $stmt = $conn->prepare("
            SELECT sp.event_type, sp.score, sp.created_at,
                   COALESCE(l.title, a.title) AS item_title,
                   sp.lesson_id, sp.assessment_id
            FROM student_progress sp
            LEFT JOIN course_lessons l ON sp.lesson_id = l.id
            LEFT JOIN assessments a ON sp.assessment_id = a.id
            WHERE sp.student_id = ? AND sp.unit_id = ?
            ORDER BY sp.created_at DESC LIMIT 12
        ");
        $stmt->bind_param("ii", $student_id, $unit_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $recent_activity[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

$lesson_pct   = $total_lessons > 0 ? round(($done_lessons / $total_lessons) * 100) : 0;
$submitted_count = count(array_filter($assessment_results, fn($a) => $a['score'] !== null));
$passed_count    = count(array_filter($assessment_results, fn($a) => $a['score'] !== null && $a['score'] >= $a['pass_mark']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Progress — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
    --bg:#f2f5fb;--surf:#fff;--surf2:#f8faff;--surf3:#eef1f9;
    --border:#e0e8f5;--accent:#4f6ef7;--green:#10b981;--amber:#f59e0b;--red:#ef4444;--purple:#8b5cf6;
    --text:#1e2235;--muted:#64748b;--dim:#a0aec0;
    --r:12px;--rs:7px;--tr:.15s ease;
    --shadow:0 2px 16px rgba(79,110,247,.07);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text)}

.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 28px;height:54px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:var(--shadow)}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--accent)}
.nav-right{display:flex;align-items:center;gap:8px}
.btn-nav{background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:5px 12px;border-radius:var(--rs);font-size:.77rem;cursor:pointer;text-decoration:none;transition:var(--tr);font-family:'DM Sans',sans-serif}
.btn-nav:hover{background:var(--accent);color:#fff;border-color:var(--accent)}

.layout{display:flex;min-height:calc(100vh - 54px)}

.sidebar{width:240px;min-width:240px;background:var(--surf);border-right:1px solid var(--border);padding:18px 14px;overflow-y:auto}
.sb-label{font-family:'Syne',sans-serif;font-size:.67rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);display:block;margin-bottom:8px}
.unit-link{display:flex;align-items:center;gap:8px;padding:8px 11px;border-radius:var(--rs);text-decoration:none;color:var(--muted);font-size:.84rem;transition:var(--tr);border:1px solid transparent;margin-bottom:3px}
.unit-link:hover{background:var(--surf2);color:var(--text)}
.unit-link.active{background:rgba(79,110,247,.08);border-color:rgba(79,110,247,.2);color:var(--accent);font-weight:500}

.main{flex:1;padding:28px 32px;display:flex;flex-direction:column;gap:24px;max-width:960px}

/* STAT CARDS */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px}
.stat-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:18px 20px;box-shadow:var(--shadow)}
.stat-icon{width:36px;height:36px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:.9rem;margin-bottom:10px}
.stat-val{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;line-height:1}
.stat-lbl{font-size:.75rem;color:var(--muted);margin-top:4px}

/* PROGRESS RING */
.progress-section{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:22px 24px;box-shadow:var(--shadow);display:flex;align-items:center;gap:28px;flex-wrap:wrap}
.ring-wrap{position:relative;width:100px;height:100px;flex-shrink:0}
.ring-wrap svg{transform:rotate(-90deg)}
.ring-bg{fill:none;stroke:var(--surf3);stroke-width:8}
.ring-fill{fill:none;stroke:var(--accent);stroke-width:8;stroke-linecap:round;transition:stroke-dashoffset .8s ease}
.ring-text{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.ring-pct{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:800;color:var(--accent)}
.ring-sub{font-size:.65rem;color:var(--muted)}
.progress-info h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:6px}
.progress-info p{font-size:.85rem;color:var(--muted);margin-bottom:12px}
.mini-bars{display:flex;flex-direction:column;gap:8px;width:100%;max-width:320px}
.mini-bar-row{display:flex;align-items:center;gap:10px}
.mini-bar-lbl{font-size:.75rem;color:var(--muted);width:80px;flex-shrink:0}
.mini-bar-track{flex:1;height:6px;background:var(--surf3);border-radius:999px;overflow:hidden}
.mini-bar-fill{height:100%;border-radius:999px;transition:width .6s ease}
.mini-bar-val{font-size:.73rem;color:var(--muted);width:32px;text-align:right}

/* ASSESSMENT TABLE */
.sec-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow)}
.sec-head{background:var(--surf2);padding:13px 18px;border-bottom:1px solid var(--border);font-family:'Syne',sans-serif;font-size:.82rem;font-weight:700;display:flex;align-items:center;gap:8px}
.assess-table{width:100%;border-collapse:collapse}
.assess-table th{font-family:'Syne',sans-serif;font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--dim);padding:10px 16px;text-align:left;border-bottom:1px solid var(--border)}
.assess-table td{padding:11px 16px;border-bottom:1px solid var(--border);font-size:.85rem}
.assess-table tr:last-child td{border-bottom:none}
.assess-table tr:hover td{background:var(--surf2)}
.type-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px}
.td-quiz{background:var(--accent)}.td-assignment{background:var(--green)}.td-cat{background:var(--amber)}.td-exam{background:var(--red)}

.score-bar-wrap{width:100px;height:6px;background:var(--surf3);border-radius:999px;overflow:hidden;display:inline-block;vertical-align:middle;margin-right:6px}
.score-bar-fill{height:100%;border-radius:999px}
.chip{font-size:.7rem;padding:2px 8px;border-radius:999px;font-weight:600}
.chip-pass{background:rgba(16,185,129,.1);color:var(--green);border:1px solid rgba(16,185,129,.2)}
.chip-fail{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2)}
.chip-pending{background:rgba(245,158,11,.1);color:var(--amber);border:1px solid rgba(245,158,11,.2)}
.chip-ns{background:var(--surf3);color:var(--dim);border:1px solid var(--border)}

/* ACTIVITY */
.activity-list{padding:6px 0}
.activity-item{display:flex;align-items:flex-start;gap:12px;padding:10px 18px;border-bottom:1px solid var(--border)}
.activity-item:last-child{border-bottom:none}
.activity-icon{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0;margin-top:2px}
.icon-completed{background:rgba(16,185,129,.12);color:var(--green)}
.icon-viewed{background:rgba(79,110,247,.1);color:var(--accent)}
.icon-score{background:rgba(245,158,11,.1);color:var(--amber)}
.activity-text{flex:1;font-size:.84rem;line-height:1.45}
.activity-text strong{font-weight:500}
.activity-time{font-size:.73rem;color:var(--dim);margin-top:3px}

.empty{text-align:center;padding:36px;color:var(--dim);font-size:.85rem}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">UNILIS <span style="color:var(--muted);font-weight:400;font-size:.8rem;margin-left:8px">My Progress</span></div>
    <div class="nav-right">
        <?php if ($unit_id): ?>
        <a href="course_view.php?unit_id=<?= $unit_id ?>" class="btn-nav"><i class="fas fa-book-open"></i> Course View</a>
        <?php endif; ?>
        <a href="../dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">
        <span class="sb-label">My Units</span>
        <?php foreach ($enrolled_units as $u): ?>
        <a class="unit-link <?= $unit_id == $u['id'] ? 'active' : '' ?>"
           href="my_progress.php?unit_id=<?= $u['id'] ?>">
            <i class="fas fa-circle-dot" style="font-size:.45rem"></i>
            <?= htmlspecialchars($u['name']) ?>
        </a>
        <?php endforeach; ?>
    </aside>

    <main class="main">
    <?php if (!$unit_id): ?>
        <div class="empty"><i class="fas fa-chart-line" style="font-size:2rem;margin-bottom:12px;display:block"></i>Select a unit to view progress.</div>
    <?php else: ?>

        <!-- STAT CARDS -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(79,110,247,.1);color:var(--accent)"><i class="fas fa-book-open"></i></div>
                <div class="stat-val" style="color:var(--accent)"><?= $lesson_pct ?>%</div>
                <div class="stat-lbl">Course Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--green)"><i class="fas fa-circle-check"></i></div>
                <div class="stat-val" style="color:var(--green)"><?= $done_lessons ?>/<?= $total_lessons ?></div>
                <div class="stat-lbl">Lessons Done</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--amber)"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-val" style="color:var(--amber)"><?= $submitted_count ?>/<?= count($assessment_results) ?></div>
                <div class="stat-lbl">Submitted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(139,92,246,.1);color:var(--purple)"><i class="fas fa-trophy"></i></div>
                <div class="stat-val" style="color:var(--purple)"><?= $passed_count ?></div>
                <div class="stat-lbl">Assessments Passed</div>
            </div>
        </div>

        <!-- PROGRESS RING -->
        <div class="progress-section">
            <?php $circ = 2 * M_PI * 42; $offset = $circ * (1 - $lesson_pct / 100); ?>
            <div class="ring-wrap">
                <svg viewBox="0 0 100 100" width="100" height="100">
                    <circle class="ring-bg" cx="50" cy="50" r="42"/>
                    <circle class="ring-fill" cx="50" cy="50" r="42"
                            stroke-dasharray="<?= round($circ,2) ?>"
                            stroke-dashoffset="<?= round($offset,2) ?>"/>
                </svg>
                <div class="ring-text">
                    <span class="ring-pct"><?= $lesson_pct ?>%</span>
                    <span class="ring-sub">complete</span>
                </div>
            </div>
            <div class="progress-info">
                <h3><?= htmlspecialchars($unit_name) ?></h3>
                <p><?= $done_lessons ?> of <?= $total_lessons ?> lessons completed</p>
                <?php
                $types = [
                    'Quizzes'     => ['quiz',       '#4f6ef7'],
                    'Assignments' => ['assignment',  '#10b981'],
                    'CATs'        => ['cat',         '#f59e0b'],
                    'Exams'       => ['exam',        '#ef4444'],
                ];
                ?>
                <div class="mini-bars">
                <?php foreach ($types as $label => [$type, $colour]): ?>
                <?php
                    $type_assessments = array_filter($assessment_results, fn($a) => $a['type'] === $type);
                    $type_total  = count($type_assessments);
                    $type_passed = count(array_filter($type_assessments, fn($a) => $a['score'] !== null && $a['score'] >= $a['pass_mark']));
                    $type_pct    = $type_total > 0 ? round(($type_passed / $type_total) * 100) : 0;
                    if ($type_total === 0) continue;
                ?>
                <div class="mini-bar-row">
                    <span class="mini-bar-lbl"><?= $label ?></span>
                    <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?= $type_pct ?>%;background:<?= $colour ?>"></div></div>
                    <span class="mini-bar-val"><?= $type_passed ?>/<?= $type_total ?></span>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ASSESSMENT RESULTS TABLE -->
        <?php if (!empty($assessment_results)): ?>
        <div class="sec-card">
            <div class="sec-head"><i class="fas fa-tasks"></i> Assessment Results</div>
            <table class="assess-table">
                <thead>
                    <tr>
                        <th>Assessment</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Result</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assessment_results as $a): ?>
                <?php
                    $score   = $a['score'];
                    $passed  = $score !== null && $score >= $a['pass_mark'];
                    $pending = $a['status'] && $score === null;
                    $ns      = !$a['status'];
                    $barPct  = $score !== null ? min(100, round($score)) : 0;
                    $barCol  = $passed ? 'var(--green)' : ($score !== null ? 'var(--red)' : 'var(--dim)');
                ?>
                <tr>
                    <td style="font-weight:500"><?= htmlspecialchars($a['title']) ?></td>
                    <td>
                        <span class="type-dot td-<?= $a['type'] ?>"></span>
                        <?= ucfirst($a['type']) ?>
                    </td>
                    <td>
                        <?php if ($score !== null): ?>
                            <div class="score-bar-wrap"><div class="score-bar-fill" style="width:<?= $barPct ?>%;background:<?= $barCol ?>"></div></div>
                            <?= round($score) ?>%
                        <?php elseif ($pending): ?>
                            <span style="color:var(--amber);font-size:.82rem">Awaiting grading</span>
                        <?php else: ?>
                            <span style="color:var(--dim);font-size:.82rem">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($score !== null): ?>
                            <span class="chip <?= $passed ? 'chip-pass' : 'chip-fail' ?>"><?= $passed ? 'Pass' : 'Fail' ?></span>
                        <?php elseif ($pending): ?>
                            <span class="chip chip-pending">Pending</span>
                        <?php else: ?>
                            <span class="chip chip-ns">Not submitted</span>
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
        <?php if (!empty($recent_activity)): ?>
        <div class="sec-card">
            <div class="sec-head"><i class="fas fa-history"></i> Recent Activity</div>
            <div class="activity-list">
            <?php foreach ($recent_activity as $ev): ?>
            <?php
                $icon = 'icon-viewed'; $ico = 'fa-eye';
                if ($ev['event_type'] === 'lesson_completed') { $icon = 'icon-completed'; $ico = 'fa-check'; }
                elseif (str_contains($ev['event_type'], '_score')) { $icon = 'icon-score'; $ico = 'fa-star'; }
                $label = str_replace(['_', 'lesson ', 'quiz_', 'assignment_', 'cat_', 'exam_'], [' ', 'Lesson ', '', '', '', ''], $ev['event_type']);
            ?>
            <div class="activity-item">
                <div class="activity-icon <?= $icon ?>"><i class="fas <?= $ico ?>"></i></div>
                <div class="activity-text">
                    <strong><?= ucfirst($label) ?></strong>:
                    <?= htmlspecialchars($ev['item_title'] ?? 'Unknown') ?>
                    <?php if ($ev['score'] !== null): ?>
                        — <strong><?= round($ev['score']) ?>%</strong>
                    <?php endif; ?>
                    <div class="activity-time"><?= date('d M Y, H:i', strtotime($ev['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
    </main>
</div>
</body>
</html>
