<?php
/**
 * Lecturer unit results & grading setup.
 * For a selected unit: (a) Grading Setup (weights + default total marks + pass
 * threshold), (b) a class-wide table of enrolled students with type averages,
 * weighted grade and status, (c) retake actions (create for below-threshold
 * students, enter marks for pending retakes), (d) drill-down into each student's
 * detailed per-item view.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/results_aggregator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php"); exit;
}
$lecturer_id   = (int)$_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';

// ---- Units this lecturer teaches ----------------------------------------
$units = [];
try {
    $res = $conn->query("SELECT u.id, u.name, u.code FROM units u
                         JOIN lecturer_units lu ON lu.unit_id = u.id AND lu.lecturer_id = " . (int)$lecturer_id . "
                         ORDER BY u.name");
    if ($res) foreach ($res as $row) $units[] = $row;
} catch (mysqli_sql_exception $e) { error_log("[unit_results] " . $e->getMessage()); }

$unit_id    = (int)($_GET['unit_id'] ?? $_POST['unit_id'] ?? ($units[0]['id'] ?? 0));
$currentAY  = trim($_GET['academic_year'] ?? $_POST['academic_year'] ?? '');
$currentSem = (int)($_GET['semester'] ?? $_POST['semester'] ?? 1);
if ($currentSem < 1 || $currentSem > 2) $currentSem = 1;

$flash = ''; $flashType = 'error';

/* ─────────────────────────── POST actions ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $unit_id) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_setup') {
        try {
            $threshold = (float)($_POST['pass_threshold_percent'] ?? 40);
            if ($threshold < 1 || $threshold > 100) $threshold = 40;
            $rows = [];
            foreach (results_all_types() as $t) {
                $w  = (float)($_POST['weight_' . $t] ?? 0); if ($w < 0) $w = 0;
                $dt = (isset($_POST['dtotal_' . $t]) && $_POST['dtotal_' . $t] !== '') ? (int)$_POST['dtotal_' . $t] : null;
                if ($dt !== null && $dt <= 0) $dt = null;
                $rows[] = ['type' => $t, 'weight' => $w, 'dtotal' => $dt];
            }
            $sum = array_sum(array_column($rows, 'weight'));
            if ($sum > 100.001) {
                $flash = 'Weights must not exceed 100%.';
            } else {
                $conn->begin_transaction();
                foreach ($rows as $r) {
                    $stmt = $conn->prepare("INSERT INTO assessment_weights (unit_id, assessment_type, weight_percent, default_total_marks)
                                            VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE weight_percent = VALUES(weight_percent), default_total_marks = VALUES(default_total_marks)");
                    $stmt->bind_param('isdi', $unit_id, $r['type'], $r['weight'], $r['dtotal']);
                    $stmt->execute(); $stmt->close();
                }
                $stmt = $conn->prepare("INSERT INTO unit_grading_settings (unit_id, pass_threshold_percent) VALUES (?,?)
                                        ON DUPLICATE KEY UPDATE pass_threshold_percent = VALUES(pass_threshold_percent)");
                $stmt->bind_param('id', $unit_id, $threshold);
                $stmt->execute(); $stmt->close();
                $conn->commit();
                $flash = $sum < 99.99 ? 'Grading setup saved. Note: weights total ' . round($sum,2) . '% (not 100%).' : 'Grading setup saved.';
                $flashType = 'success';
            }
        } catch (mysqli_sql_exception $e) {
            if ($conn->inTransaction) $conn->rollback();
            error_log("[unit_results] " . $e->getMessage());
            $flash = 'Could not save grading setup: ' . $e->getMessage();
        }
    }

    if ($action === 'create_retake') {
        $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
        $totalMarks   = (int)($_POST['total_marks'] ?? 0);
        if ($enrollmentId <= 0 || $totalMarks <= 0) {
            $flash = 'Retake requires a valid enrollment and total marks.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO unit_retakes (enrollment_id, total_marks) VALUES (?,?)");
                $stmt->bind_param('ii', $enrollmentId, $totalMarks);
                $stmt->execute(); $stmt->close();
                $flash = 'Retake created (total ' . $totalMarks . ').'; $flashType = 'success';
            } catch (mysqli_sql_exception $e) {
                error_log("[unit_results] " . $e->getMessage());
                $flash = 'Could not create retake: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'grade_retake') {
        $retakeId      = (int)($_POST['retake_id'] ?? 0);
        $marksObtained = (float)($_POST['marks_obtained'] ?? 0);
        if ($retakeId <= 0 || $marksObtained < 0) {
            $flash = 'Invalid retake marks.';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE unit_retakes SET marks_obtained = ?, status = 'graded', graded_at = NOW() WHERE id = ?");
                $stmt->bind_param('di', $marksObtained, $retakeId);
                $stmt->execute(); $stmt->close();
                $flash = 'Retake graded.'; $flashType = 'success';
            } catch (mysqli_sql_exception $e) {
                error_log("[unit_results] " . $e->getMessage());
                $flash = 'Could not grade retake: ' . $e->getMessage();
            }
        }
    }

    header("Location: unit_results.php?unit_id={$unit_id}&academic_year=" . urlencode($currentAY)
         . "&semester={$currentSem}&flash=" . urlencode($flash) . "&ft=" . $flashType);
    exit;
}

// Carry a flash across the ?flash= redirect.
if (isset($_GET['flash']) && $_GET['flash'] !== '') { $flash = $_GET['flash']; $flashType = $_GET['ft'] ?? 'error'; }



// ---- Data for the selected unit ------------------------------------------
$config = null; $enrollments = []; $results = []; $unitName = '';
if ($unit_id) {
    $config = results_load_unit_config($conn, $unit_id);
    foreach ($units as $u) if ((int)$u['id'] === $unit_id) { $unitName = $u['name']; break; }
    $enrollments = results_enrollments_for_unit($conn, $unit_id, $currentAY ?: null, $currentSem ?: null);
    try { results_refresh_unit_statuses($conn, $unit_id, $currentAY ?: null, $currentSem ?: null); } catch (Exception $e) {}
    foreach ($enrollments as $enr) {
        try { $results[(int)$enr['id']] = results_compute_for_enrollment($conn, $enr); } catch (Exception $e) {}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unit Results - UNILIS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #1f2937; }
.header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
.header h1 { font-size: 19px; }
.header .user-info { font-size: 13px; opacity: .85; }
.header a { color: #fff; text-decoration: none; opacity: .85; margin-left: 16px; }
.header a:hover { opacity: 1; }
.wrap { max-width: 1180px; margin: 0 auto; padding: 24px; }
.banner { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: .9rem; }
.banner.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.banner.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.selectbar { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; margin-bottom: 18px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.selectbar label { font-size: .8rem; color: #6b7280; font-weight: 600; }
.selectbar select, .selectbar input[type=submit] { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: .85rem; }
.panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 18px; overflow: hidden; }
.panel > h3 { font-size: 14px; color: #1e3a8a; padding: 13px 16px; border-bottom: 1px solid #eee; background: #f9fafb; }
.panel > .panel-body { padding: 16px; }
table.cw { width: 100%; border-collapse: collapse; font-size: .84rem; }
table.cw th { text-align: left; color: #6b7280; font-size: .74rem; text-transform: uppercase; letter-spacing: .03em; padding: 8px 10px; border-bottom: 1px solid #ecf0f1; }
table.cw td { padding: 9px 10px; border-bottom: 1px solid #f5f6f7; vertical-align: top; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 6px; font-size: .8rem; font-weight: 600; border: 1px solid transparent; cursor: pointer; text-decoration: none; }

<body>
<div class="header">
    <h1><i class="fas fa-chart-line"></i> Unit Results &amp; Grading Setup</h1>
    <div class="user-info">
        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($lecturer_name) ?>
        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>
<div class="wrap">
    <?php if ($flash !== ''): ?>
        <div class="banner <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <div class="selectbar">
        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div><label>Unit</label><br>
                <select name="unit_id" onchange="this.form.submit()">
                    <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ((int)$u['id'] === $unit_id) ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Academic year</label><br>
                <input type="text" name="academic_year" value="<?= htmlspecialchars($currentAY) ?>" placeholder="e.g. 2025/2026">
            </div>
            <div><label>Semester</label><br>
                <select name="semester"><option value="1" <?= $currentSem === 1 ? 'selected' : '' ?>>Semester 1</option><option value="2" <?= $currentSem === 2 ? 'selected' : '' ?>>Semester 2</option></select>
            </div>
            <div><button class="btn btn-ghost" type="submit"><i class="fas fa-filter"></i> Apply</button></div>
        </form>
    </div>

    <?php if (!$unit_id): ?>
        <div class="panel"><div class="empty">Please select a unit to configure and view results.</div></div>
    <?php else: ?>

    <!-- Grading Setup -->
    <div class="panel">
        <h3><i class="fas fa-sliders-h"></i> Grading Setup — <?= htmlspecialchars($unitName) ?></h3>
        <div class="panel-body">
            <?php if ($config && $config['weight_sum_percent'] <= 0): ?>
                <div class="banner success" style="margin-top:0;margin-bottom:14px">
                    <i class="fas fa-info-circle"></i> This unit has <strong>no weights configured yet</strong>.
                    Set weights (and the legacy assignment default total marks) below so a transcript can be computed.
                </div>
            <?php endif; ?>
            <form method="post" action="unit_results.php">
                <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
                <input type="hidden" name="action" value="save_setup">
                <table class="cw" style="max-width:720px">
                    <thead><tr><th>Type</th><th>Weight %</th><th>Default total marks (legacy)</th></tr></thead>
                    <tbody>
                    <?php foreach (results_all_types() as $t):
                        $c = $config['types'][$t];
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars(results_type_label($t)) ?></strong>
                                <div class="note"><?= $t === 'assignment' ? 'Used for both new assessments and legacy submissions' : '' ?></div></td>
                            <td><input type="number" step="0.01" min="0" max="100" name="weight_<?= $t ?>" value="<?= htmlspecialchars($c['weight_percent'] !== null ? $c['weight_percent'] : '0') ?>" style="width:90px"></td>
                            <td><input type="number" step="1" min="0" name="dtotal_<?= $t ?>" value="<?= htmlspecialchars($c['default_total_marks'] !== null ? $c['default_total_marks'] : '') ?>" style="width:110px" placeholder="—"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="field" style="max-width:320px;margin-top:14px">
                    <label>Pass threshold (%)</label>
                    <input type="number" step="0.01" min="1" max="100" name="pass_threshold_percent" value="<?= htmlspecialchars(results_pct($config['pass_threshold_percent'])) ?>">
                    <div class="note"><?= $config['uses_system_default_pass'] ? 'Currently the system default (40%) is used because no lecturer-confirmed value exists.' : 'Lecturer-confirmed pass threshold.' ?></div>
                </div>
                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Grading Setup</button>
            </form>
        </div>
    </div>

.btn-primary { background: #2563eb; color: #fff; }
.btn-ok { background: #16a34a; color: #fff; }
.btn-ghost { background: #fff; color: #374151; border-color: #d1d5db; }
.drill { display: none; }
.field { margin-bottom: 10px; }
.field label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: 4px; }
.field input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; }
.note { font-size: .78rem; color: #6b7280; margin-top: 4px; }
.empty { padding: 26px; text-align: center; color: #6b7280; }
</style>
</head>

    <!-- Class results -->
    <div class="panel">
        <h3><i class="fas fa-users"></i> Class Results — <?= htmlspecialchars($unitName) ?>
            <span style="font-weight:400;color:#6b7280;font-size:.8rem"> (<?= count($enrollments) ?> enrolled)</span></h3>
        <div class="panel-body">
            <?php if (empty($enrollments)): ?>
                <div class="empty">No enrolled students for this unit<?= $currentAY ? " in $currentAY" : '' ?>.</div>
            <?php else: ?>
                <table class="cw">
                    <thead><tr>
                        <th>Student</th><th>Attempt</th>
                        <th>Quiz</th><th>Assignment</th><th>CAT</th><th>Exam</th>
                        <th>Weighted %</th><th>Status</th><th>Retake</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($enrollments as $enr):
                        $r = $results[(int)$enr['id']] ?? null;
                        if (!$r) continue;
                        $ta = $r['type_averages'];
                        $wgrade = $r['weighted_grade'] !== null ? results_pct($r['weighted_grade']) . '%' : '—';
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($enr['student_name']) ?></strong>
                                <div class="note"><?= htmlspecialchars($enr['reg_no'] ?? '') ?></div></td>
                            <td><?= (int)($enr['attempt_number'] ?? 1) ?><?= $enr['semester'] ? ' · S' . (int)$enr['semester'] : '' ?></td>
                            <?php foreach (results_all_types() as $t): ?>
                                <td><?= isset($ta[$t]) && $ta[$t]['avg'] !== null ? results_pct($ta[$t]['avg']) . '%' : '—' ?>
                                    <?= !empty($ta[$t]['needs_setup']) ? '<div class="note" style="color:#b45309">needs setup</div>' : '' ?></td>
                            <?php endforeach; ?>
                            <td><strong><?= $wgrade ?></strong></td>
                            <td><?= results_status_badge($r['status']) ?></td>
                            <td>
                            <?php if ($r['retake'] !== null):
                                    $rt = $r['retake'];
                                    if ($rt['status'] === 'graded'): ?>
                                        <span class="note"><?= (float)$rt['marks_obtained'] ?>/<?= (int)$rt['total_marks'] ?> (<?= results_pct($rt['retake_percent']) ?>%)</span>
                                    <?php else: ?>
                                        <form method="post" action="unit_results.php" style="display:flex;gap:6px;align-items:center">
                                            <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
                                            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($currentAY) ?>">
                                            <input type="hidden" name="semester" value="<?= $currentSem ?>">
                                            <input type="hidden" name="action" value="grade_retake">
                                            <input type="hidden" name="retake_id" value="<?= (int)$rt['id'] ?>">
                                            <input type="number" step="0.01" min="0" name="marks_obtained" required placeholder="marks / <?= (int)$rt['total_marks'] ?>" style="width:90px;padding:6px;border:1px solid #d1d5db;border-radius:6px">
                                            <button class="btn btn-ok" type="submit"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                            <?php elseif ($r['retake_eligible']): ?>
                                <form method="post" action="unit_results.php" style="display:flex;gap:6px;align-items:center">
                                    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
                                    <input type="hidden" name="academic_year" value="<?= htmlspecialchars($currentAY) ?>">
                                    <input type="hidden" name="semester" value="<?= $currentSem ?>">
                                    <input type="hidden" name="action" value="create_retake">
                                    <input type="hidden" name="enrollment_id" value="<?= (int)$enr['id'] ?>">
                                    <input type="number" step="1" min="1" name="total_marks" placeholder="total" style="width:64px;padding:6px;border:1px solid #d1d5db;border-radius:6px">
                                    <button class="btn btn-primary" type="submit" title="Create supplementary retake"><i class="fas fa-plus"></i></button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><button class="btn btn-ghost" type="button" onclick="toggleDrill(this)"><i class="fas fa-eye"></i></button></td>
                        </tr>
                        <tr class="drill"><td colspan="10"><?= results_render_enrollment_result($conn, $r) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; /* end unit selected */ ?>
</div>
<script>
function toggleDrill(btn){ const tr = btn.closest('tr'); const next = tr.nextElementSibling; if(next && next.classList.contains('drill')){ next.classList.toggle('drill-open'); next.style.display = (next.style.display==='table-row'?'none':'table-row'); } }
</script>
</body>
</html>

