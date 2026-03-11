<?php
ob_start();
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = intval($_SESSION['user_id']);

// ── Fetch student info ────────────────────────────────────────────────────
try {
    $stmt = $conn->prepare("SELECT id, name, course_id, year_of_study FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student) { header("Location: ../index.html"); exit; }
} catch (mysqli_sql_exception $e) {
    error_log("my_units fetch student: " . $e->getMessage());
    die("Error loading student data.");
}

$course_id     = $student['course_id'];
$year_of_study = $student['year_of_study'];

// ── Handle semester filter (default semester 1) ───────────────────────────
$semester      = intval($_GET['semester'] ?? 1);
$academic_year = trim($_GET['academic_year'] ?? date('Y') . '/' . (date('Y') + 1));
if ($semester < 1 || $semester > 2) $semester = 1;

// ── Handle POST: save enrollment ──────────────────────────────────────────
// NOTE: student_unit_enrollments has no semester/academic_year columns.
// We manage enrollment purely by student_id + unit_id using INSERT IGNORE / DELETE.
$save_message = '';
$save_type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_units'])) {
    $post_semester      = intval($_POST['semester']      ?? 1);
    $post_academic_year = trim($_POST['academic_year']   ?? $academic_year);
    $selected_unit_ids  = $_POST['unit_ids'] ?? [];

    // Sanitise
    $selected_unit_ids = array_values(array_filter(array_map('intval', $selected_unit_ids)));

    try {
        // Verify all selected units belong to this student's course + year + semester
        $valid_ids = [];
        if (!empty($selected_unit_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_unit_ids), '?'));
            $types        = str_repeat('i', count($selected_unit_ids));
            $verify_stmt  = $conn->prepare("
                SELECT id FROM units
                WHERE id IN ($placeholders)
                  AND course_id = ?
                  AND year      = ?
                  AND semester  = ?
            ");
            $params = array_merge($selected_unit_ids, [$course_id, $year_of_study, $post_semester]);
            $verify_stmt->bind_param($types . 'iii', ...$params);
            $verify_stmt->execute();
            $valid_result = $verify_stmt->get_result();
            while ($row = $valid_result->fetch_assoc()) $valid_ids[] = $row['id'];
            $verify_stmt->close();
        }

        // Get all unit IDs for this student's course + year + semester
        // so we only delete enrollments for THIS semester's units, not all
        $sem_units_stmt = $conn->prepare("
            SELECT id FROM units
            WHERE course_id = ? AND year = ? AND semester = ?
        ");
        $sem_units_stmt->bind_param("iii", $course_id, $year_of_study, $post_semester);
        $sem_units_stmt->execute();
        $sem_result  = $sem_units_stmt->get_result();
        $sem_unit_ids = [];
        while ($row = $sem_result->fetch_assoc()) $sem_unit_ids[] = $row['id'];
        $sem_units_stmt->close();

        // Delete existing enrollments only for units in this semester
        if (!empty($sem_unit_ids)) {
            $del_placeholders = implode(',', array_fill(0, count($sem_unit_ids), '?'));
            $del_types        = str_repeat('i', count($sem_unit_ids));
            $del = $conn->prepare("
                DELETE FROM student_unit_enrollments
                WHERE student_id = ?
                  AND unit_id IN ($del_placeholders)
            ");
            $del_params = array_merge([$student_id], $sem_unit_ids);
            $del->bind_param('i' . $del_types, ...$del_params);
            $del->execute();
            $del->close();
        }

        // Insert newly selected valid units
        if (!empty($valid_ids)) {
            $ins = $conn->prepare("
                INSERT IGNORE INTO student_unit_enrollments (student_id, unit_id)
                VALUES (?, ?)
            ");
            foreach ($valid_ids as $uid) {
                $ins->bind_param("ii", $student_id, $uid);
                $ins->execute();
            }
            $ins->close();
        }

        // Refresh session so get_enrolled_units.php picks up new enrollments
        $_SESSION['course_id']     = $course_id;
        $_SESSION['year_of_study'] = $year_of_study;

        $n            = count($valid_ids);
        $save_message = $n . ' unit' . ($n !== 1 ? 's' : '') . ' saved for Semester ' . $post_semester . '.';
        $save_type    = 'success';
        $semester      = $post_semester;
        $academic_year = $post_academic_year;

    } catch (mysqli_sql_exception $e) {
        error_log("my_units save error: " . $e->getMessage());
        $save_message = 'Error saving units: ' . $e->getMessage();
        $save_type    = 'error';
    }
}

// ── Fetch all units for this course + year + semester ─────────────────────
$available_units = [];
try {
    $stmt = $conn->prepare("
        SELECT id, name, code
        FROM units
        WHERE course_id = ? AND year = ? AND semester = ?
        ORDER BY name ASC
    ");
    $stmt->bind_param("iii", $course_id, $year_of_study, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $available_units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("my_units available: " . $e->getMessage());
}

// ── Fetch already-enrolled unit IDs for current semester ─────────────────
// Join with units table to filter by semester since enrollment table has no semester column
$enrolled_ids = [];
try {
    $stmt = $conn->prepare("
        SELECT sue.unit_id
        FROM student_unit_enrollments sue
        JOIN units u ON u.id = sue.unit_id
        WHERE sue.student_id = ?
          AND u.course_id    = ?
          AND u.year         = ?
          AND u.semester     = ?
    ");
    $stmt->bind_param("iiii", $student_id, $course_id, $year_of_study, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $enrolled_ids[] = intval($row['unit_id']);
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("my_units enrolled: " . $e->getMessage());
}

// ── Academic year options (display only) ──────────────────────────────────
$current_year   = intval(date('Y'));
$academic_years = [];
for ($y = $current_year - 1; $y <= $current_year + 1; $y++) {
    $academic_years[] = $y . '/' . ($y + 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Units — UNILIS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/styles.css">
<style>
.units-page { max-width: 780px; margin: 32px auto; padding: 0 20px 60px; font-family: inherit; }
.units-title { font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: 6px; }
.units-subtitle { font-size: 0.88rem; color: #64748b; margin-bottom: 28px; }

.alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: flex-start; gap: 10px; word-break: break-word; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.filters-row { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.07em; }
.filter-select { padding: 9px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.88rem; background: #fff; color: #1e293b; cursor: pointer; outline: none; transition: border-color 0.15s; appearance: none; padding-right: 28px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; }
.filter-select:focus { border-color: #6366f1; }

.btn-filter { padding: 9px 18px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: background 0.15s, transform 0.1s; display: inline-flex; align-items: center; gap: 7px; align-self: flex-end; }
.btn-filter:hover { background: #4f46e5; transform: translateY(-1px); }

.info-bar { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: #475569; flex-wrap: wrap; }
.info-bar i { color: #6366f1; }

.units-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.units-card-header { background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; }
.units-card-header h3 { font-size: 1rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; margin: 0; }
.units-card-header span { font-size: 0.78rem; color: rgba(255,255,255,0.75); }

.unit-list { padding: 8px 0; }
.unit-item { display: flex; align-items: center; gap: 14px; padding: 14px 24px; border-bottom: 1px solid #f1f5f9; transition: background 0.12s; cursor: pointer; }
.unit-item:last-child { border-bottom: none; }
.unit-item:hover { background: #f8fafc; }
.unit-item.enrolled { background: #f0fdf4; }
.unit-item.enrolled:hover { background: #dcfce7; }

.unit-checkbox { width: 20px; height: 20px; accent-color: #6366f1; cursor: pointer; flex-shrink: 0; }
.unit-info { flex: 1; }
.unit-name { font-size: 0.92rem; font-weight: 600; color: #1e293b; margin-bottom: 2px; }
.unit-code { font-size: 0.76rem; color: #94a3b8; font-family: monospace; }
.enrolled-badge { font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; white-space: nowrap; }

.empty-state { text-align: center; padding: 48px 24px; color: #94a3b8; }
.empty-state i { font-size: 2.5rem; margin-bottom: 14px; display: block; opacity: 0.4; }
.empty-state h3 { font-size: 1rem; font-weight: 700; color: #64748b; margin-bottom: 6px; }
.empty-state p  { font-size: 0.85rem; }

.select-all-row { display: flex; align-items: center; gap: 10px; padding: 12px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.82rem; color: #64748b; cursor: pointer; }
.select-all-row:hover { background: #f1f5f9; }
.select-all-row label { cursor: pointer; font-weight: 600; }

.units-card-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.selected-count { font-size: 0.82rem; color: #64748b; }
.selected-count strong { color: #6366f1; }

.btn-save { padding: 11px 28px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: background 0.15s, transform 0.1s; display: inline-flex; align-items: center; gap: 8px; }
.btn-save:hover { background: #4f46e5; transform: translateY(-1px); }

.btn-back { padding: 10px 18px; background: transparent; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #64748b; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; transition: border-color 0.15s, color 0.15s; }
.btn-back:hover { border-color: #6366f1; color: #6366f1; }

@media (max-width: 540px) { .filters-row { flex-direction: column; } .btn-filter { width: 100%; justify-content: center; } }
</style>
</head>
<body>
<div class="units-page">

    <div style="margin-bottom:20px">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="units-title">
        <i class="fas fa-book-open" style="color:#6366f1;margin-right:10px"></i>My Units
    </div>
    <p class="units-subtitle">
        Select the units you are studying this semester. These will appear across the LMS — lessons, assessments, labs, progress tracking, and attendance.
    </p>

    <?php if ($save_message): ?>
    <div class="alert alert-<?= $save_type ?>">
        <i class="fas <?= $save_type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>" style="margin-top:2px;flex-shrink:0"></i>
        <span><?= $save_message ?></span>
    </div>
    <?php endif; ?>

    <div class="info-bar">
        <i class="fas fa-info-circle"></i>
        Showing units for <strong>&nbsp;Year <?= $year_of_study ?></strong> &nbsp;|&nbsp;
        Semester <strong><?= $semester ?></strong> &nbsp;|&nbsp;
        <?= count($available_units) ?> unit<?= count($available_units) !== 1 ? 's' : '' ?> available
    </div>

    <!-- Semester filter -->
    <form method="GET" action="my_units.php">
        <div class="filters-row">
            <div class="filter-group">
                <label><i class="fas fa-calendar-half"></i> Semester</label>
                <select name="semester" class="filter-select">
                    <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1</option>
                    <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Apply
            </button>
        </div>
    </form>

    <!-- Units selection form -->
    <form method="POST" action="my_units.php" id="units-form">
        <input type="hidden" name="save_units" value="1">
        <input type="hidden" name="semester"   value="<?= $semester ?>">

        <div class="units-card">
            <div class="units-card-header">
                <h3><i class="fas fa-list-check"></i> Semester <?= $semester ?> Units</h3>
                <span><?= count($enrolled_ids) ?> currently enrolled</span>
            </div>

            <?php if (empty($available_units)): ?>
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <h3>No units found</h3>
                <p>No units are listed for Year <?= $year_of_study ?>, Semester <?= $semester ?> of your course.</p>
            </div>

            <?php else: ?>

            <div class="select-all-row" onclick="toggleAll()">
                <input type="checkbox" id="select-all" class="unit-checkbox"
                       onclick="event.stopPropagation(); toggleAll()"
                       <?= count($enrolled_ids) === count($available_units) && count($available_units) > 0 ? 'checked' : '' ?>>
                <label for="select-all">Select / Deselect All</label>
                <span style="margin-left:auto;font-size:0.78rem">
                    <span id="count-label"><?= count($enrolled_ids) ?></span> / <?= count($available_units) ?> selected
                </span>
            </div>

            <div class="unit-list">
                <?php foreach ($available_units as $unit):
                    $is_enrolled = in_array($unit['id'], $enrolled_ids);
                ?>
                <label class="unit-item <?= $is_enrolled ? 'enrolled' : '' ?>" id="item-<?= $unit['id'] ?>">
                    <input type="checkbox"
                           class="unit-checkbox unit-check"
                           name="unit_ids[]"
                           value="<?= $unit['id'] ?>"
                           onchange="updateCount(); updateRowStyle(<?= $unit['id'] ?>, this.checked)"
                           <?= $is_enrolled ? 'checked' : '' ?>>
                    <div class="unit-info">
                        <div class="unit-name"><?= htmlspecialchars($unit['name']) ?></div>
                        <?php if (!empty($unit['code'])): ?>
                        <div class="unit-code"><?= htmlspecialchars($unit['code']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="enrolled-badge" id="badge-<?= $unit['id'] ?>"
                          style="<?= $is_enrolled ? '' : 'display:none' ?>">
                        <i class="fas fa-check"></i> <?= $is_enrolled ? 'Enrolled' : 'Selected' ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="units-card-footer">
                <div class="selected-count">
                    <strong id="footer-count"><?= count($enrolled_ids) ?></strong> unit<?= count($enrolled_ids) !== 1 ? 's' : '' ?> selected
                </div>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save My Units
                </button>
            </div>

            <?php endif; ?>
        </div>
    </form>

</div>

<script>
function updateCount() {
    const checked = document.querySelectorAll('.unit-check:checked').length;
    const total   = document.querySelectorAll('.unit-check').length;
    document.getElementById('count-label').textContent  = checked;
    document.getElementById('footer-count').textContent = checked;
    const sa = document.getElementById('select-all');
    sa.checked       = checked === total && total > 0;
    sa.indeterminate = checked > 0 && checked < total;
}

function updateRowStyle(unitId, checked) {
    const row   = document.getElementById('item-'  + unitId);
    const badge = document.getElementById('badge-' + unitId);
    if (checked) {
        row.classList.add('enrolled');
        badge.style.display = '';
        badge.innerHTML = '<i class="fas fa-check"></i> Selected';
    } else {
        row.classList.remove('enrolled');
        badge.style.display = 'none';
    }
}

function toggleAll() {
    const checks   = document.querySelectorAll('.unit-check');
    const nChecked = document.querySelectorAll('.unit-check:checked').length;
    const newState = nChecked < checks.length;
    checks.forEach(cb => { cb.checked = newState; updateRowStyle(cb.value, newState); });
    const sa = document.getElementById('select-all');
    sa.checked = newState; sa.indeterminate = false;
    updateCount();
}

document.getElementById('units-form')?.addEventListener('submit', function(e) {
    const enrolled = <?= json_encode($enrolled_ids) ?>;
    const selected = [...document.querySelectorAll('.unit-check:checked')].map(c => parseInt(c.value));
    const removed  = enrolled.filter(id => !selected.includes(id));
    if (removed.length > 0) {
        const ok = confirm(
            `You are removing ${removed.length} previously enrolled unit${removed.length > 1 ? 's' : ''}.\n\n` +
            `Your existing progress and submissions will NOT be deleted, but those units will not appear in your dashboard until re-enrolled.\n\nContinue?`
        );
        if (!ok) e.preventDefault();
    }
});

(function() {
    const checks  = document.querySelectorAll('.unit-check');
    const checked = document.querySelectorAll('.unit-check:checked').length;
    const sa = document.getElementById('select-all');
    if (sa && checked > 0 && checked < checks.length) sa.indeterminate = true;
})();
</script>
</body>
</html>