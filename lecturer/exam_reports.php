<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php"); exit;
}

$lecturer_id   = $_SESSION['user_id'];
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

// Assessments (proctored only: cat/exam)
$assessments = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("
            SELECT a.id, a.title, a.type,
                   COUNT(DISTINCT asub.id) AS sub_count,
                   COUNT(DISTINCT ev.submission_id) AS flagged_subs,
                   COUNT(ev.id) AS total_violations
            FROM assessments a
            LEFT JOIN assessment_submissions asub ON asub.assessment_id = a.id
            LEFT JOIN exam_violations ev ON ev.submission_id = asub.id
            WHERE a.unit_id = ? AND a.lecturer_id = ? AND a.type IN ('cat','exam')
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

// Flagged submissions for assessment
$flagged_submissions = [];
if ($assessment_id) {
    try {
        $stmt = $conn->prepare("
            SELECT asub.id, asub.status, asub.score, asub.submitted_at,
                   u.id AS student_id, u.name AS student_name,
                   COUNT(ev.id) AS violation_count
            FROM assessment_submissions asub
            JOIN users u ON asub.student_id = u.id
            LEFT JOIN exam_violations ev ON ev.submission_id = asub.id
            WHERE asub.assessment_id = ?
            GROUP BY asub.id
            ORDER BY violation_count DESC, asub.submitted_at ASC
        ");
        $stmt->bind_param("i", $assessment_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $flagged_submissions[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Violation log for selected submission
$violations = [];
$selected_sub = null;
if ($submission_id) {
    try {
        $stmt = $conn->prepare("
            SELECT asub.id, asub.status, asub.score, asub.submitted_at,
                   u.name AS student_name, a.title AS assess_title, a.type
            FROM assessment_submissions asub
            JOIN users u ON asub.student_id = u.id
            JOIN assessments a ON asub.assessment_id = a.id
            WHERE asub.id = ?
        ");
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $selected_sub = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT id, violation_type, details, occurred_at FROM exam_violations WHERE submission_id = ? ORDER BY occurred_at ASC");
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $violations[] = $row;
        $stmt->close();

        if ($selected_sub) $assessment_id = $selected_sub['assessment_id'] ?? $assessment_id;
    } catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }
}

// Violation type labels
$vtype_labels = [
    'tab_switch'       => ['Tab Switch',       'fa-window-restore',     '#f59e0b'],
    'window_blur'      => ['Window Blur',       'fa-eye-slash',          '#64748b'],
    'right_click'      => ['Right Click',       'fa-computer-mouse',     '#a78bfa'],
    'shortcut_blocked' => ['Blocked Shortcut',  'fa-keyboard',           '#22d3ee'],
    'copy_attempt'     => ['Copy Attempt',      'fa-copy',               '#ef4444'],
    'paste_attempt'    => ['Paste Attempt',     'fa-paste',              '#ef4444'],
    'cut_attempt'      => ['Cut Attempt',       'fa-scissors',           '#ef4444'],
    'fullscreen_exit'  => ['Fullscreen Exit',   'fa-compress',           '#f87171'],
    'devtools_open'    => ['DevTools Detected', 'fa-code',               '#f87171'],
    'unknown'          => ['Unknown',           'fa-triangle-exclamation','#64748b'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Reports — UNILIS</title>
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
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--red)}
.brand span{color:var(--muted);font-weight:400;font-size:.8rem;margin-left:8px}
.nav-links{display:flex;gap:8px}
.btn-nav{background:var(--surf3);border:1px solid var(--border);color:var(--muted);padding:5px 12px;border-radius:var(--rs);font-size:.77rem;cursor:pointer;text-decoration:none;transition:var(--tr);font-family:'DM Sans',sans-serif}
.btn-nav:hover{color:var(--text);background:var(--surf2)}

.layout{display:flex;height:calc(100vh - 54px)}

/* SIDEBAR */
.sidebar{width:260px;min-width:260px;background:var(--surf);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.sb-top{padding:14px;border-bottom:1px solid var(--border);flex-shrink:0}
.sb-label{font-family:'Syne',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);display:block;margin-bottom:6px}
.styled-select{width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--text);padding:8px 28px 8px 10px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.sb-list{flex:1;overflow-y:auto;padding:10px}

.assess-item{padding:10px 12px;border-radius:var(--rs);cursor:pointer;text-decoration:none;color:var(--text);margin-bottom:4px;border:1px solid transparent;display:block;transition:var(--tr)}
.assess-item:hover{background:var(--surf2);border-color:var(--border)}
.assess-item.active{background:rgba(248,113,113,.06);border-color:rgba(248,113,113,.2)}
.ai-title{font-size:.84rem;font-weight:500;display:flex;align-items:center;gap:7px;margin-bottom:5px}
.ai-stats{display:flex;gap:8px;flex-wrap:wrap}
.stat-chip{font-size:.7rem;padding:1px 7px;border-radius:999px}
.chip-violation{background:rgba(248,113,113,.1);color:var(--red);border:1px solid rgba(248,113,113,.2)}
.chip-sub{background:rgba(91,141,238,.1);color:var(--accent);border:1px solid rgba(91,141,238,.2)}
.badge-cat{background:rgba(251,191,36,.1);color:var(--amber);border:1px solid rgba(251,191,36,.2);font-size:.65rem;padding:1px 6px;border-radius:999px}
.badge-exam{background:rgba(248,113,113,.1);color:var(--red);border:1px solid rgba(248,113,113,.2);font-size:.65rem;padding:1px 6px;border-radius:999px}

/* STUDENT LIST */
.sub-panel{width:240px;min-width:240px;background:var(--surf2);border-right:1px solid var(--border);overflow-y:auto}
.sub-panel-head{padding:12px 14px;border-bottom:1px solid var(--border);font-family:'Syne',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim)}
.sub-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:var(--tr);text-decoration:none;color:var(--text)}
.sub-item:hover{background:var(--surf3)}
.sub-item.active{background:rgba(248,113,113,.07)}
.sub-avatar{width:30px;height:30px;border-radius:50%;background:var(--surf3);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:.75rem;font-weight:700;color:var(--accent);flex-shrink:0}
.v-count-badge{font-family:'JetBrains Mono',monospace;font-size:.72rem;padding:2px 8px;border-radius:999px}
.vbadge-high{background:rgba(248,113,113,.15);color:var(--red);border:1px solid rgba(248,113,113,.25)}
.vbadge-med{background:rgba(251,191,36,.12);color:var(--amber);border:1px solid rgba(251,191,36,.2)}
.vbadge-low{background:rgba(91,141,238,.1);color:var(--accent);border:1px solid rgba(91,141,238,.2)}
.vbadge-none{background:rgba(52,211,153,.08);color:var(--green);border:1px solid rgba(52,211,153,.15)}

/* REPORT AREA */
.report-area{flex:1;overflow-y:auto;padding:24px 28px;display:flex;flex-direction:column;gap:18px}

.report-header{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:16px 20px}
.rh-top{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.rh-name{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700}
.status-badge{font-size:.72rem;padding:3px 10px;border-radius:999px;font-weight:600}
.sb-flagged{background:rgba(248,113,113,.1);color:var(--red);border:1px solid rgba(248,113,113,.2)}
.sb-graded{background:rgba(52,211,153,.1);color:var(--green);border:1px solid rgba(52,211,153,.2)}
.sb-submitted{background:rgba(251,191,36,.1);color:var(--amber);border:1px solid rgba(251,191,36,.2)}
.rh-meta{display:flex;gap:14px;font-size:.8rem;color:var(--muted);flex-wrap:wrap}

/* SUMMARY CHIPS */
.v-summary{display:flex;flex-wrap:wrap;gap:10px}
.v-chip{display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--surf);border:1px solid var(--border);border-radius:var(--rs)}
.v-chip i{font-size:.9rem}
.v-chip-count{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800}
.v-chip-lbl{font-size:.73rem;color:var(--muted)}

/* TIMELINE */
.timeline{display:flex;flex-direction:column;gap:0}
.tl-item{display:flex;gap:14px;padding:11px 16px;border-bottom:1px solid var(--dim);background:var(--surf);transition:var(--tr)}
.tl-item:first-child{border-radius:var(--rs) var(--rs) 0 0;border-top:1px solid var(--border)}
.tl-item:last-child{border-radius:0 0 var(--rs) var(--rs);border-bottom:1px solid var(--border)}
.tl-icon{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0}
.tl-body{flex:1}
.tl-type{font-size:.83rem;font-weight:500}
.tl-detail{font-size:.78rem;color:var(--muted);margin-top:2px}
.tl-time{font-family:'JetBrains Mono',monospace;font-size:.72rem;color:var(--dim);white-space:nowrap;margin-top:4px}

/* PLACEHOLDER */
.placeholder{flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;color:var(--dim)}
.placeholder i{font-size:2.5rem;opacity:.2}
.placeholder h3{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;color:var(--muted)}

/* CARD */
.sec-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.sec-head{background:var(--surf2);padding:12px 16px;border-bottom:1px solid var(--border);font-family:'Syne',sans-serif;font-size:.78rem;font-weight:700;display:flex;align-items:center;gap:8px}

::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--surf3);border-radius:2px}
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">UNILIS <span>Exam Security Reports</span></div>
    <div class="nav-links">
        <?php if ($unit_id): ?>
        <a href="grading_centre.php?unit_id=<?= $unit_id ?>" class="btn-nav"><i class="fas fa-pen-to-square"></i> Grading Centre</a>
        <a href="assessment_builder.php?unit_id=<?= $unit_id ?>" class="btn-nav"><i class="fas fa-tasks"></i> Assessments</a>
        <?php endif; ?>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sb-top">
            <span class="sb-label">Unit</span>
            <select class="styled-select" onchange="window.location.href='exam_reports.php?unit_id='+this.value">
                <option value="">— select —</option>
                <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sb-list">
            <?php if (empty($assessments)): ?>
                <p style="font-size:.8rem;color:var(--dim);padding:12px 4px"><?= $unit_id ? 'No proctored assessments (CAT/Exam).' : 'Select a unit.' ?></p>
            <?php else: ?>
                <?php foreach ($assessments as $a): ?>
                <a class="assess-item <?= $assessment_id == $a['id'] ? 'active' : '' ?>"
                   href="exam_reports.php?unit_id=<?= $unit_id ?>&assessment_id=<?= $a['id'] ?>">
                    <div class="ai-title">
                        <span class="badge-<?= $a['type'] ?>"><?= strtoupper($a['type']) ?></span>
                        <?= htmlspecialchars($a['title']) ?>
                    </div>
                    <div class="ai-stats">
                        <span class="stat-chip chip-sub"><?= $a['sub_count'] ?> submissions</span>
                        <?php if ($a['total_violations']): ?>
                        <span class="stat-chip chip-violation"><?= $a['total_violations'] ?> violations</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- STUDENT LIST -->
    <?php if ($assessment_id && !empty($flagged_submissions)): ?>
    <div class="sub-panel">
        <div class="sub-panel-head">Students (<?= count($flagged_submissions) ?>)</div>
        <?php foreach ($flagged_submissions as $sub): ?>
        <?php
            $vc = $sub['violation_count'];
            $vclass = $vc >= 5 ? 'vbadge-high' : ($vc >= 2 ? 'vbadge-med' : ($vc >= 1 ? 'vbadge-low' : 'vbadge-none'));
        ?>
        <a class="sub-item <?= $submission_id == $sub['id'] ? 'active' : '' ?>"
           href="exam_reports.php?unit_id=<?= $unit_id ?>&assessment_id=<?= $assessment_id ?>&submission_id=<?= $sub['id'] ?>">
            <div class="sub-avatar"><?= strtoupper(substr($sub['student_name'],0,1)) ?></div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.84rem;font-weight:500;margin-bottom:2px"><?= htmlspecialchars($sub['student_name']) ?></div>
                <div style="font-size:.73rem;color:var(--muted)"><?= $sub['score'] !== null ? round($sub['score']).'%' : 'Ungraded' ?></div>
            </div>
            <span class="v-count-badge <?= $vclass ?>"><?= $vc ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php elseif ($assessment_id): ?>
    <div class="sub-panel">
        <div class="sub-panel-head">Students</div>
        <div style="padding:20px;color:var(--dim);font-size:.82rem;text-align:center">No submissions.</div>
    </div>
    <?php endif; ?>

    <!-- REPORT -->
    <?php if ($selected_sub): ?>
    <div class="report-area">

        <div class="report-header">
            <div class="rh-top">
                <div class="sub-avatar" style="width:38px;height:38px;font-size:.88rem"><?= strtoupper(substr($selected_sub['student_name'],0,1)) ?></div>
                <div style="flex:1">
                    <div class="rh-name"><?= htmlspecialchars($selected_sub['student_name']) ?></div>
                </div>
                <span class="status-badge sb-<?= $selected_sub['status'] ?>"><?= ucfirst($selected_sub['status']) ?></span>
            </div>
            <div class="rh-meta">
                <span><i class="fas fa-file-shield"></i> <?= htmlspecialchars($selected_sub['assess_title']) ?></span>
                <span><i class="fas fa-clock"></i> <?= date('d M Y, H:i', strtotime($selected_sub['submitted_at'])) ?></span>
                <?php if ($selected_sub['score'] !== null): ?>
                <span><i class="fas fa-star"></i> Score: <?= round($selected_sub['score']) ?>%</span>
                <?php endif; ?>
                <span style="color:<?= count($violations) >= 5 ? 'var(--red)' : 'var(--amber)' ?>">
                    <i class="fas fa-triangle-exclamation"></i> <?= count($violations) ?> violation<?= count($violations)!=1?'s':'' ?> logged
                </span>
            </div>
        </div>

        <?php if (!empty($violations)): ?>

        <!-- VIOLATION SUMMARY -->
        <?php
        $type_counts = [];
        foreach ($violations as $v) {
            $type_counts[$v['violation_type']] = ($type_counts[$v['violation_type']] ?? 0) + 1;
        }
        arsort($type_counts);
        ?>
        <div class="sec-card">
            <div class="sec-head"><i class="fas fa-chart-bar"></i> Violation Summary</div>
            <div style="padding:14px 16px">
                <div class="v-summary">
                    <?php foreach ($type_counts as $vtype => $count): ?>
                    <?php $info = $vtype_labels[$vtype] ?? $vtype_labels['unknown']; ?>
                    <div class="v-chip">
                        <i class="fas <?= $info[1] ?>" style="color:<?= $info[2] ?>"></i>
                        <div>
                            <div class="v-chip-count" style="color:<?= $info[2] ?>"><?= $count ?></div>
                            <div class="v-chip-lbl"><?= $info[0] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- VIOLATION TIMELINE -->
        <div class="sec-card">
            <div class="sec-head"><i class="fas fa-timeline"></i> Violation Timeline (<?= count($violations) ?> events)</div>
            <div class="timeline">
                <?php foreach ($violations as $v): ?>
                <?php $info = $vtype_labels[$v['violation_type']] ?? $vtype_labels['unknown']; ?>
                <div class="tl-item">
                    <div class="tl-icon" style="background:rgba(0,0,0,.2);color:<?= $info[2] ?>">
                        <i class="fas <?= $info[1] ?>"></i>
                    </div>
                    <div class="tl-body">
                        <div class="tl-type" style="color:<?= $info[2] ?>"><?= $info[0] ?></div>
                        <?php if ($v['details']): ?>
                        <div class="tl-detail"><?= htmlspecialchars($v['details']) ?></div>
                        <?php endif; ?>
                        <div class="tl-time"><?= date('H:i:s', strtotime($v['occurred_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ACTIONS -->
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if ($selected_sub['status'] === 'flagged'): ?>
            <button onclick="clearFlag(<?= $submission_id ?>)"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:500;cursor:pointer;background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.2);color:var(--green);transition:var(--tr)">
                <i class="fas fa-flag-checkered"></i> Clear Flag — Mark as Reviewed
            </button>
            <?php endif; ?>
            <a href="grading_centre.php?unit_id=<?= $unit_id ?>&assessment_id=<?= $assessment_id ?>&submission_id=<?= $submission_id ?>"
               style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:500;text-decoration:none;background:var(--surf2);border:1px solid var(--border);color:var(--muted);transition:var(--tr)">
                <i class="fas fa-pen-to-square"></i> Go to Grading Centre
            </a>
        </div>

        <?php else: ?>
        <div style="text-align:center;padding:48px;color:var(--green)">
            <i class="fas fa-shield-check" style="font-size:2.5rem;margin-bottom:12px;display:block;opacity:.5"></i>
            <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:6px">No Violations</div>
            <div style="font-size:.84rem;color:var(--muted)">This student completed the exam with no security events recorded.</div>
        </div>
        <?php endif; ?>

    </div>

    <?php else: ?>
    <div class="placeholder">
        <i class="fas fa-shield-halved"></i>
        <h3><?= $assessment_id ? 'Select a Student' : 'Select a CAT or Exam' ?></h3>
        <p style="font-size:.82rem;max-width:220px;text-align:center"><?= $assessment_id ? 'View their violation report.' : 'Only proctored assessments are shown.' ?></p>
    </div>
    <?php endif; ?>

</div>

<script>
function clearFlag(submissionId) {
    if (!confirm('Mark this submission as reviewed? This will change status from Flagged to Submitted.')) return;
    const fd = new FormData();
    fd.append('submission_id', submissionId);
    fd.append('action', 'clear_flag');
    fetch('ajax/get_violations.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) window.location.reload(); })
        .catch(() => {});
}
</script>
</body>
</html>