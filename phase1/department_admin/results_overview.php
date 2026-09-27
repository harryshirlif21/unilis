<?php
/**
 * Department Admin — Results overview.
 * Auto-scoped to the admin's own department(s) via department_admins ->
 * departments -> courses -> units. Lists each unit with enrolled count, class
 * average weighted grade, pass rate and how many students still lack a
 * resolvable grade (missing grading setup). Drilling into a unit shows the same
 * class-wide results the lecturer sees, read-only.
 */
define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/results_aggregator.php';
require_once __DIR__ . '/../includes/auth_extended.php';

phase1_guard_role(ROLE_DEPARTMENT_ADMIN, '../../login.php');

$adminId = (int)$_SESSION['user_id'];

$currentAY  = trim($_GET['academic_year'] ?? '');
$currentSem = (int)($_GET['semester'] ?? 1);
if ($currentSem < 1 || $currentSem > 2) $currentSem = 1;
$drillUnit = (int)($_GET['unit_id'] ?? 0);

// Units under this admin's department(s).
$units = [];
try {
    $stmt = $conn->prepare("SELECT u.id AS unit_id, u.name AS unit_name, u.code, c.name AS course_name, c.department_id
                            FROM units u
                            JOIN courses c ON c.id = u.course_id
                            JOIN department_admins da ON da.department_id = c.department_id AND da.is_active = 1
                            WHERE da.admin_id = ?
                            ORDER BY c.name, u.name");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    foreach ($stmt->get_result() as $row) $units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("[dept_results] " . $e->getMessage());
}

// Per-unit summary for the overview.
$overview = [];
foreach ($units as $u) {
    $overview[$u['unit_id']] = results_student_count_breakdown($conn, (int)$u['unit_id'], $currentAY ?: null, $currentSem ?: null);
}

// For the read-only drill-down of a single unit.
$drillName = ''; $drillEnrollments = []; $drillResults = [];
if ($drillUnit) {
    foreach ($units as $u) if ((int)$u['unit_id'] === $drillUnit) { $drillName = $u['unit_name']; break; }
    $drillEnrollments = results_enrollments_for_unit($conn, $drillUnit, $currentAY ?: null, $currentSem ?: null);
    foreach ($drillEnrollments as $enr) {
        try { $drillResults[(int)$enr['id']] = results_compute_for_enrollment($conn, $enr); } catch (Exception $e) {}
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Results Overview - UNILIS</title>
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
.selectbar { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 16px; margin-bottom: 18px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.selectbar label { font-size: .8rem; color: #6b7280; font-weight: 600; display:block; margin-bottom:4px; }
.selectbar input, .selectbar select, .selectbar button { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: .85rem; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 6px; font-size: .8rem; font-weight: 600; border: 1px solid transparent; cursor: pointer; text-decoration: none; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-ghost { background: #fff; color: #374151; border-color: #d1d5db; }
.panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 18px; overflow: hidden; }
.panel > h3 { font-size: 14px; color: #1e3a8a; padding: 13px 16px; border-bottom: 1px solid #eee; background: #f9fafb; }
.panel > .panel-body { padding: 16px; }
table.cw { width: 100%; border-collapse: collapse; font-size: .84rem; }
table.cw th { text-align: left; color: #6b7280; font-size: .74rem; text-transform: uppercase; letter-spacing: .03em; padding: 8px 10px; border-bottom: 1px solid #ecf0f1; }
table.cw td { padding: 9px 10px; border-bottom: 1px solid #f5f6f7; vertical-align: top; }
.chip { display:inline-block; padding:2px 8px; border-radius:12px; font-size:.74rem; font-weight:600; }
.chip.warn { background:#fef3c7; color:#92400e; }
.empty { padding: 26px; text-align: center; color: #6b7280; }
.drillnote { font-size:.8rem; color:#6b7280; }
</style>
</head>

<body>
<div class="header">
    <h1><i class="fas fa-chart-bar"></i> Results Overview</h1>
    <div class="user-info">
        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Department Admin') ?>
        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>
<div class="wrap">
    <div class="selectbar">
        <form method="get" style="display:flex;gap:12px;align-items:flex-end">
            <div><label>Academic year</label><input type="text" name="academic_year" value="<?= htmlspecialchars($currentAY) ?>" placeholder="e.g. 2025/2026"></div>
            <div><label>Semester</label><select name="semester"><option value="1" <?= $currentSem===1?'selected':'' ?>>1</option><option value="2" <?= $currentSem===2?'selected':'' ?>>2</option></select></div>
            <button class="btn btn-ghost" type="submit"><i class="fas fa-filter"></i> Apply</button>
            <?php if ($drillUnit): ?><button class="btn btn-ghost" type="button" onclick="location.href='results_overview.php'"><i class="fas fa-arrow-left"></i> Back to overview</button><?php endif; ?>
        </form>
    </div>

    <?php if ($drillUnit): // ── read-only class view for one unit ── ?>
        <div class="panel">
            <h3><i class="fas fa-users"></i> <?= htmlspecialchars($drillName) ?> <span style="font-weight:400;color:#6b7280;font-size:.8rem">(read-only) — <?= count($drillEnrollments) ?> enrolled</span></h3>
            <div class="panel-body">
                <?php if (empty($drillEnrollments)): ?>
                    <div class="empty">No enrolled students for this unit.</div>
                <?php else: ?>
                    <table class="cw">
                        <thead><tr><th>Student</th><th>Attempt</th><th>Quiz</th><th>Assignment</th><th>CAT</th><th>Exam</th><th>Weighted %</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($drillEnrollments as $enr):
                                $r = $drillResults[(int)$enr['id']] ?? null; if (!$r) continue;
                                $ta = $r['type_averages'];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($enr['student_name']) ?></strong><div class="drillnote"><?= htmlspecialchars($enr['reg_no'] ?? '') ?></div></td>
                                <td><?= (int)($enr['attempt_number'] ?? 1) ?></td>
                                <?php foreach (results_all_types() as $t): ?>
                                    <td><?= isset($ta[$t]) && $ta[$t]['avg'] !== null ? results_pct($ta[$t]['avg']) . '%' : '—' ?></td>
                                <?php endforeach; ?>
                                <td><strong><?= $r['weighted_grade'] !== null ? results_pct($r['weighted_grade']) . '%' : '—' ?></strong></td>
                                <td><?= results_status_badge($r['status']) ?></td>
                                <td><button class="btn btn-ghost" type="button" onclick="toggleDrill(this)"><i class="fas fa-eye"></i></button></td>
                            </tr>
                            <tr class="drill"><td colspan="9"><?= results_render_enrollment_result($conn, $r) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="panel">
            <h3><i class="fas fa-cube"></i> Units in your department(s)<?= $currentAY ? " — $currentAY" : '' ?></h3>
            <div class="panel-body">
                <?php if (empty($units)): ?>
                    <div class="empty">No units found under your department(s).</div>
                <?php else: ?>
                    <table class="cw">
                        <thead><tr><th>Unit</th><th>Course</th><th>Enrolled</th><th>Class Avg (weighted %)</th><th>Pass Rate</th><th>No Resolvable Grade</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($units as $u):
                                $o = $overview[$u['unit_id']] ?? [];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['unit_name']) ?></strong><div class="drillnote"><?= htmlspecialchars($u['code']) ?></div></td>
                                <td><?= htmlspecialchars($u['course_name']) ?></td>
                                <td><?= (int)($o['total'] ?? 0) ?></td>
                                <td><?= ($o['class_average'] ?? null) !== null ? results_pct($o['class_average']) . '%' : '—' ?></td>
                                <td>
                                    <?php if (($o['pass_rate'] ?? null) !== null): ?>
                                        <?= results_pct($o['pass_rate'], 0) . '%' ?> <span style="color:#6b7280">(<?= (int)($o['pass'] ?? 0) ?> passed / <?= (int)($o['total'] ?? 0) ?>)</span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php $nr = (int)($o['no_resolvable'] ?? 0); ?>
                                    <?php if ($nr > 0): ?><span class="chip warn"><i class="fas fa-exclamation-triangle"></i> <?= $nr ?> need grading setup</span>
                                    <?php else: ?><span style="color:#16a34a">none</span><?php endif; ?>
                                </td>
                                <td><a class="btn btn-ghost" href="results_overview.php?unit_id=<?= (int)$u['unit_id'] ?>&academic_year=<?= urlencode($currentAY) ?>&semester=<?= $currentSem ?>"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
function toggleDrill(btn){ const next = btn.closest('tr').nextElementSibling; if(next && next.classList.contains('drill')){ next.style.display = (next.style.display==='table-row'?'none':'table-row'); } }
</script>
</body>
</html>
