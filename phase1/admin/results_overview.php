<?php
/** Read-only, department-scoped results overview for department administrators. */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/results_aggregator.php';

if (($_SESSION['user_role'] ?? '') !== 'department_admin' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$adminId = (int)$_SESSION['user_id'];
$activeColumn = $conn->query("SHOW COLUMNS FROM department_admins LIKE 'is_active'");
$assignmentSql = "SELECT department_id FROM department_admins WHERE admin_id = ?";
if ($activeColumn && $activeColumn->num_rows > 0) $assignmentSql .= " AND is_active = 1";
$assignmentSql .= " LIMIT 1";
$stmt = $conn->prepare($assignmentSql);
$stmt->bind_param('i', $adminId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$assignment) {
    http_response_code(403);
    exit('No active department assignment was found for this account.');
}

$departmentId = (int)$assignment['department_id'];
$embedded = ($_GET['embedded'] ?? '') === '1';
$selectedUnitId = max(0, (int)($_GET['unit_id'] ?? 0));
$selectedYear = trim((string)($_GET['academic_year'] ?? ''));
$selectedSemester = (int)($_GET['semester'] ?? 0);
if ($selectedSemester < 1 || $selectedSemester > 2) $selectedSemester = 0;
$departmentName = 'Department';
$departmentStmt = $conn->prepare('SELECT name FROM departments WHERE id = ? LIMIT 1');
$departmentStmt->bind_param('i', $departmentId);
$departmentStmt->execute();
$departmentName = $departmentStmt->get_result()->fetch_assoc()['name'] ?? $departmentName;
$departmentStmt->close();

$ready = true;
$setupMessage = '';
foreach ([
    "SHOW TABLES LIKE 'student_units'",
    "SHOW TABLES LIKE 'assessment_weights'",
    "SHOW TABLES LIKE 'assessments'",
    "SHOW TABLES LIKE 'assessment_submissions'",
    "SHOW TABLES LIKE 'assignments'",
    "SHOW TABLES LIKE 'submissions'",
    "SHOW TABLES LIKE 'unit_grading_settings'",
    "SHOW TABLES LIKE 'unit_retakes'",
    "SHOW COLUMNS FROM student_units LIKE 'academic_year'",
    "SHOW COLUMNS FROM student_units LIKE 'semester'",
    "SHOW COLUMNS FROM assessment_weights LIKE 'default_total_marks'",
] as $checkSql) {
    try {
        $check = $conn->query($checkSql);
        if (!$check || $check->num_rows === 0) $ready = false;
    } catch (Throwable $e) {
        $ready = false;
    }
}
if (!$ready) {
    $setupMessage = 'Results reporting needs the Results / Transcript database migration. Ask the system administrator to apply migrations/2026_09_26_results_transcript.php.';
}

$units = [];
$years = [];
$summaries = [];
$detailRows = [];
$selectedUnit = null;
if ($ready) {
    $unitsStmt = $conn->prepare("SELECT u.id, u.name, u.code, c.name AS course_name
        FROM units u JOIN courses c ON c.id = u.course_id
        WHERE c.department_id = ? ORDER BY c.name, u.name");
    $unitsStmt->bind_param('i', $departmentId);
    $unitsStmt->execute();
    $unitsResult = $unitsStmt->get_result();
    while ($row = $unitsResult->fetch_assoc()) $units[] = $row;
    $unitsStmt->close();

    $yearsStmt = $conn->prepare("SELECT DISTINCT su.academic_year, su.semester
        FROM student_units su JOIN units u ON u.id = su.unit_id JOIN courses c ON c.id = u.course_id
        WHERE c.department_id = ? AND su.academic_year IS NOT NULL AND su.academic_year <> ''
        ORDER BY su.academic_year DESC, su.semester DESC");
    $yearsStmt->bind_param('i', $departmentId);
    $yearsStmt->execute();
    $yearsResult = $yearsStmt->get_result();
    while ($row = $yearsResult->fetch_assoc()) $years[] = $row;
    $yearsStmt->close();

    foreach ($units as $unit) {
        $summaries[(int)$unit['id']] = results_student_count_breakdown($conn, (int)$unit['id'], $selectedYear !== '' ? $selectedYear : null, $selectedSemester ?: null);
        if ((int)$unit['id'] === $selectedUnitId) $selectedUnit = $unit;
    }

    if ($selectedUnit) {
        $enrollments = results_enrollments_for_unit($conn, $selectedUnitId, $selectedYear !== '' ? $selectedYear : null, $selectedSemester ?: null);
        foreach ($enrollments as $enrollment) {
            $detailRows[] = ['enrollment' => $enrollment, 'result' => results_compute_for_enrollment($conn, $enrollment)];
        }
    } elseif ($selectedUnitId > 0) {
        http_response_code(404);
    }
}

function results_admin_pct($value): string {
    return $value === null ? 'Not available' : number_format((float)$value, 2) . '%';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Results | UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6fb; color: #172033; font: 14px/1.5 Inter, Arial, sans-serif; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px 22px; color:#fff; background:#1e3a8a; }
        .top h1 { margin:0; font-size:18px; }
        .top .actions { display:flex; gap:8px; align-items:center; }
        .btn { border:1px solid #cbd5e1; border-radius:7px; padding:8px 12px; color:#1e3a8a; background:#fff; text-decoration:none; cursor:pointer; font:inherit; }
        .wrap { max-width:1200px; margin:0 auto; padding:22px; }
        .intro { margin:0 0 16px; color:#64748b; }
        .filter, .panel { background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 2px 8px #0f172a0a; }
        .filter { display:flex; flex-wrap:wrap; align-items:end; gap:12px; padding:14px; margin-bottom:16px; }
        .filter label { display:grid; gap:5px; color:#475569; font-size:12px; font-weight:600; }
        .filter select { min-width:180px; padding:9px 10px; border:1px solid #cbd5e1; border-radius:7px; background:#fff; font:inherit; }
        .panel { padding:16px; margin-bottom:16px; }
        .panel h2 { margin:0 0 4px; color:#1e3a8a; font-size:16px; }
        .muted { color:#64748b; font-size:12px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:720px; }
        th, td { text-align:left; padding:10px 9px; border-bottom:1px solid #edf0f5; vertical-align:top; }
        th { color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
        .unit-link { color:#1d4ed8; font-weight:600; text-decoration:none; }
        .unit-link:hover { text-decoration:underline; }
        .notice { padding:14px 16px; color:#854d0e; background:#fefce8; border:1px solid #fde68a; border-radius:9px; }
        .cards { display:grid; grid-template-columns:repeat(4,minmax(140px,1fr)); gap:10px; margin:14px 0; }
        .stat { padding:12px; border-radius:9px; background:#f8fafc; border:1px solid #e2e8f0; }
        .stat strong { display:block; font-size:19px; color:#1e3a8a; }
        details { margin-top:8px; }
        details summary { color:#1d4ed8; cursor:pointer; font-weight:600; }
        .back { margin-right:auto; }
        @media(max-width:700px) { .top { align-items:flex-start; flex-direction:column; } .wrap { padding:12px; } .cards { grid-template-columns:repeat(2,minmax(120px,1fr)); } }
    </style>
</head>
<body>
    <header class="top">
        <h1><i class="fa-solid fa-chart-column"></i> Results overview - <?= htmlspecialchars($departmentName) ?></h1>
        <div class="actions">
            <?php if ($selectedUnit): ?><a class="btn back" href="results_overview.php?embedded=<?= $embedded ? '1' : '0' ?>&amp;academic_year=<?= urlencode($selectedYear) ?>&amp;semester=<?= $selectedSemester ?>"><i class="fa-solid fa-arrow-left"></i> All units</a><?php endif; ?>
            <?php if ($embedded): ?><button class="btn" type="button" onclick="if(window.parent && window.parent!==window) window.parent.closeDepartmentResultsModal()">Close</button><?php else: ?><a class="btn" href="department_admins.php">Dashboard</a><?php endif; ?>
        </div>
    </header>
    <main class="wrap">
        <p class="intro">Read-only results for units in your assigned department. Student records and grading controls remain within their existing workflows. Pass outcomes use each unit's configured threshold, or the 40% system default when none is configured.</p>
        <?php if (!$ready): ?>
            <div class="notice"><i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($setupMessage) ?></div>
        <?php else: ?>
            <form class="filter" method="get">
                <?php if ($embedded): ?><input type="hidden" name="embedded" value="1"><?php endif; ?>
                <label>Academic year
                    <select name="academic_year"><option value="">All available years</option>
                        <?php foreach ($years as $year): $label = (string)$year['academic_year']; ?>
                            <option value="<?= htmlspecialchars($label) ?>" <?= $selectedYear === $label ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Semester
                    <select name="semester"><option value="0">All semesters</option>
                        <?php for ($sem = 1; $sem <= 2; $sem++): ?><option value="<?= $sem ?>" <?= $selectedSemester === $sem ? 'selected' : '' ?>>Semester <?= $sem ?></option><?php endfor; ?>
                    </select>
                </label>
                <?php if ($selectedUnit): ?><input type="hidden" name="unit_id" value="<?= (int)$selectedUnitId ?>"><?php endif; ?>
                <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Apply filters</button>
            </form>

            <?php if ($selectedUnit): ?>
                <?php $summary = $summaries[$selectedUnitId] ?? []; ?>
                <section class="panel">
                    <h2><?= htmlspecialchars($selectedUnit['name']) ?> <span class="muted">(<?= htmlspecialchars($selectedUnit['code'] ?? '') ?>) · <?= htmlspecialchars($selectedUnit['course_name']) ?></span></h2>
                    <div class="cards">
                        <div class="stat"><span class="muted">Enrolled</span><strong><?= (int)($summary['total'] ?? 0) ?></strong></div>
                        <div class="stat"><span class="muted">Class average</span><strong><?= htmlspecialchars(results_admin_pct($summary['class_average'] ?? null)) ?></strong></div>
                        <div class="stat"><span class="muted">Pass rate</span><strong><?= htmlspecialchars(results_admin_pct($summary['pass_rate'] ?? null)) ?></strong></div>
                        <div class="stat"><span class="muted">No resolvable grade</span><strong><?= (int)($summary['no_resolvable'] ?? 0) ?></strong></div>
                    </div>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Student</th><th>Registration no.</th><th>Attempt</th><th>Weighted grade</th><th>Status</th><th>Details</th></tr></thead>
                        <tbody>
                        <?php if (!$detailRows): ?><tr><td colspan="6" class="muted">No enrollments match these filters.</td></tr><?php endif; ?>
                        <?php foreach ($detailRows as $row): $enr = $row['enrollment']; $r = $row['result']; ?>
                            <tr>
                                <td><?= htmlspecialchars($enr['student_name'] ?? 'Student') ?></td>
                                <td><?= htmlspecialchars($enr['reg_no'] ?? '') ?></td>
                                <td><?= (int)($enr['attempt_number'] ?? 1) ?></td>
                                <td><?= htmlspecialchars(results_admin_pct($r['weighted_grade'])) ?></td>
                                <td><?= strip_tags(results_status_badge($r['status']), '<span><i>') ?></td>
                                <td><details><summary>View items</summary><?= results_render_enrollment_result($conn, $r) ?></details></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </section>
            <?php else: ?>
                <section class="panel">
                    <h2>Department units</h2>
                    <p class="muted">Class averages include enrollments with a resolvable weighted grade. The no-resolvable-grade count includes students without a computable weighted grade, including missing marks or grading setup.</p>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Course</th><th>Unit</th><th>Enrolled</th><th>Class average</th><th>Pass rate</th><th>No resolvable grade</th></tr></thead>
                        <tbody>
                        <?php if (!$units): ?><tr><td colspan="6" class="muted">No units are assigned to courses in this department.</td></tr><?php endif; ?>
                        <?php foreach ($units as $unit): $summary = $summaries[(int)$unit['id']]; ?>
                            <tr>
                                <td><?= htmlspecialchars($unit['course_name']) ?></td>
                                <td><a class="unit-link" href="?embedded=<?= $embedded ? '1' : '0' ?>&amp;unit_id=<?= (int)$unit['id'] ?>&amp;academic_year=<?= urlencode($selectedYear) ?>&amp;semester=<?= $selectedSemester ?>"><?= htmlspecialchars($unit['name']) ?> (<?= htmlspecialchars($unit['code'] ?? '') ?>)</a></td>
                                <td><?= (int)$summary['total'] ?></td>
                                <td><?= htmlspecialchars(results_admin_pct($summary['class_average'])) ?></td>
                                <td><?= htmlspecialchars(results_admin_pct($summary['pass_rate'])) ?></td>
                                <td><?= (int)$summary['no_resolvable'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
