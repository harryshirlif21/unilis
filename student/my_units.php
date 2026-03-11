<?php
require_once '../config/db.php';
session_start();

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
    error_log("my_units: " . $e->getMessage());
    die("Error loading student data.");
}

$course_id     = $student['course_id'];
$year_of_study = $student['year_of_study'];

// ── Handle semester filter (default semester 1) ───────────────────────────
$semester      = intval($_GET['semester']      ?? 1);
$academic_year = trim($_GET['academic_year']   ?? date('Y') . '/' . (date('Y') + 1));
if ($semester < 1 || $semester > 2) $semester = 1;

// ── Handle POST: save enrollment ──────────────────────────────────────────
$save_message = '';
$save_type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_units'])) {
    $post_semester      = intval($_POST['semester']      ?? 1);
    $post_academic_year = trim($_POST['academic_year']   ?? $academic_year);
    $selected_unit_ids  = $_POST['unit_ids'] ?? [];

    // Sanitise
    $selected_unit_ids = array_map('intval', $selected_unit_ids);
    $selected_unit_ids = array_filter($selected_unit_ids); // remove 0s

    try {
        // Verify all selected units belong to this student's course + year
        if (!empty($selected_unit_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_unit_ids), '?'));
            $types        = str_repeat('i', count($selected_unit_ids));
            $verify_stmt  = $conn->prepare("
                SELECT id FROM units
                WHERE id IN ($placeholders)
                  AND course_id = ?
                  AND year      = ?
            ");
            $params = array_merge($selected_unit_ids, [$course_id, $year_of_study]);
            $verify_stmt->bind_param($types . 'ii', ...$params);
            $verify_stmt->execute();
            $valid_result = $verify_stmt->get_result();
            $valid_ids    = [];
            while ($row = $valid_result->fetch_assoc()) $valid_ids[] = $row['id'];
            $verify_stmt->close();
        } else {
            $valid_ids = [];
        }

        // Delete existing enrollments for this student / semester / year
        $del = $conn->prepare("
            DELETE FROM student_unit_enrollments
            WHERE student_id = ? AND semester = ? AND academic_year = ?
        ");
        $del->bind_param("iis", $student_id, $post_semester, $post_academic_year);
        $del->execute();
        $del->close();

        // Insert newly selected units
        if (!empty($valid_ids)) {
            $ins = $conn->prepare("
                INSERT INTO student_unit_enrollments (student_id, unit_id, semester, academic_year)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($valid_ids as $uid) {
                $ins->bind_param("iiis", $student_id, $uid, $post_semester, $post_academic_year);
                $ins->execute();
            }
            $ins->close();
        }

        $save_message = count($valid_ids) . ' unit' . (count($valid_ids) !== 1 ? 's' : '') . ' saved for Semester ' . $post_semester . ' (' . htmlspecialchars($post_academic_year) . ').';
        $save_type    = 'success';
        $semester      = $post_semester;
        $academic_year = $post_academic_year;

    } catch (mysqli_sql_exception $e) {
        error_log("my_units save: " . $e->getMessage());
        $save_message = 'Error saving units. Please try again.';
        $save_type    = 'error';
    }
}

// ── Fetch all units for this course + year ────────────────────────────────
$available_units = [];
try {
    $stmt = $conn->prepare("
        SELECT id, name, code
        FROM units
        WHERE course_id = ? AND year = ?
        ORDER BY name ASC
    ");
    $stmt->bind_param("ii", $course_id, $year_of_study);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $available_units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("my_units available: " . $e->getMessage());
}

// ── Fetch already-enrolled unit IDs for current semester ─────────────────
$enrolled_ids = [];
try {
    $stmt = $conn->prepare("
        SELECT unit_id FROM student_unit_enrollments
        WHERE student_id = ? AND semester = ? AND academic_year = ?
    ");
    $stmt->bind_param("iis", $student_id, $semester, $academic_year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $enrolled_ids[] = intval($row['unit_id']);
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("my_units enrolled: " . $e->getMessage());
}

// ── Academic year options ─────────────────────────────────────────────────
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
/* ── Page wrapper ── */
.units-page {
    max-width: 780px;
    margin: 32px auto;
    padding: 0 20px 60px;
    font-family: inherit;
}

/* ── Page title ── */
.units-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 6px;
}
.units-subtitle {
    font-size: 0.88rem;
    color: #64748b;
    margin-bottom: 28px;
}

/* ── Alert ── */
.alert {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

/* ── Filters row ── */
.filters-row {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    align-items: flex-end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.filter-group label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}
.filter-select {
    padding: 9px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.88rem;
    background: #fff;
    color: #1e293b;
    cursor: pointer;
    outline: none;
    transition: border-color 0.15s;
    appearance: none;
    padding-right: 28px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
}
.filter-select:focus { border-color: #6366f1; }

.btn-filter {
    padding: 9px 18px;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    align-self: flex-end;
}
.btn-filter:hover { background: #4f46e5; transform: translateY(-1px); }

/* ── Info bar ── */
.info-bar {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
    color: #475569;
}
.info-bar i { color: #6366f1; }

/* ── Units form card ── */
.units-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.units-card-header {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.units-card-header h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}
.units-card-header span {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.75);
}

/* ── Unit list ── */
.unit-list {
    padding: 8px 0;
}
.unit-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 24px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.12s;
    cursor: pointer;
}
.unit-item:last-child { border-bottom: none; }
.unit-item:hover { background: #f8fafc; }
.unit-item.enrolled { background: #f0fdf4; }
.unit-item.enrolled:hover { background: #dcfce7; }

/* Custom checkbox */
.unit-checkbox {
    width: 20px; height: 20px;
    accent-color: #6366f1;
    cursor: pointer;
    flex-shrink: 0;
}

.unit-info { flex: 1; }
.unit-name {
    font-size: 0.92rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 2px;
}
.unit-code {
    font-size: 0.76rem;
    color: #94a3b8;
    font-family: monospace;
}

.enrolled-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
    white-space: nowrap;
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #94a3b8;
}
.empty-state i { font-size: 2.5rem; margin-bottom: 14px; display: block; opacity: 0.4; }
.empty-state h3 { font-size: 1rem; font-weight: 700; color: #64748b; margin-bottom: 6px; }
.empty-state p  { font-size: 0.85rem; }

/* ── Select all row ── */
.select-all-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.82rem;
    color: #64748b;
    cursor: pointer;
}
.select-all-row:hover { background: #f1f5f9; }
.select-all-row label { cursor: pointer; font-weight: 600; }

/* ── Footer actions ── */
.units-card-footer {
    padding: 16px 24px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.selected-count {
    font-size: 0.82rem;
    color: #64748b;
}
.selected-count strong { color: #6366f1; }

.btn-save {
    padding: 11px 28px;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-save:hover { background: #4f46e5; transform: translateY(-1px); }

.btn-back {
    padding: 10px 18px;
    background: transparent;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.85rem;
    color: #64748b;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: border-color 0.15s, color 0.15s;
}
.btn-back:hover { border-color: #6366f1; color: #6366f1; }

/* ── Responsive ── */
@media (max-width: 540px) {
    .filters-row { flex-direction: column; }
    .btn-filter  { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<!-- Reuse existing navbar/sidebar if included via require, otherwise minimal header -->
<div class="units-page">

    <!-- Back link -->
    <div style="margin-bottom:20px">
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="units-title">
        <i class="fas fa-book-open" style="color:#6366f1;margin-right:10px"></i>My Units
    </div>
    <p class="units-subtitle">
        Select the units you are studying this semester. These will appear across the LMS — lessons, assessments, labs, progress tracking, and attendance.
    </p>

    <!-- Save feedback -->
    <?php if ($save_message): ?>
    <div class="alert alert-<?= $save_type ?>">
        <i class="fas <?= $save_type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
        <?= $save_message ?>
    </div>
    <?php endif; ?>

    <!-- Info bar -->
    <div class="info-bar">
        <i class="fas fa-info-circle"></i>
        Showing units for <strong>&nbsp;Year <?= $year_of_study ?></strong> &nbsp;|&nbsp;
        Semester <strong><?= $semester ?></strong> &nbsp;|&nbsp;
        Academic Year <strong><?= htmlspecialchars($academic_year) ?></strong>
        &nbsp;— <?= count($available_units) ?> unit<?= count($available_units) !== 1 ? 's' : '' ?> available
    </div>

    <!-- Semester + Academic Year filter -->
    <form method="GET" action="my_units.php">
        <div class="filters-row">
            <div class="filter-group">
                <label><i class="fas fa-calendar-half"></i> Semester</label>
                <select name="semester" class="filter-select">
                    <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1</option>
                    <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Academic Year</label>
                <select name="academic_year" class="filter-select">
                    <?php foreach ($academic_years as $ay): ?>
                    <option value="<?= $ay ?>" <?= $academic_year === $ay ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ay) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Apply
            </button>
        </div>
    </form>

    <!-- Units selection form -->
    <form method="POST" action="my_units.php" id="units-form">
        <input type="hidden" name="save_units"    value="1">
        <input type="hidden" name="semester"      value="<?= $semester ?>">
        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>">

        <div class="units-card">
            <div class="units-card-header">
                <h3>
                    <i class="fas fa-list-check"></i>
                    Semester <?= $semester ?> Units
                </h3>
                <span><?= count($enrolled_ids) ?> currently enrolled</span>
            </div>

            <?php if (empty($available_units)): ?>
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <h3>No units found</h3>
                <p>No units are listed for Year <?= $year_of_study ?> of your course. Please contact your administrator.</p>
            </div>

            <?php else: ?>

            <!-- Select All -->
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
                <label class="unit-item <?= $is_enrolled ? 'enrolled' : '' ?>"
                       id="item-<?= $unit['id'] ?>">
                    <input type="checkbox"
                           class="unit-checkbox unit-check"
                           name="unit_ids[]"
                           value="<?= $unit['id'] ?>"
                           onchange="updateCount(); updateRowStyle(<?= $unit['id'] ?>, this.checked)"
                           <?= $is_enrolled ? 'checked' : '' ?>>
                    <div class="unit-info">
                        <div class="unit-name"><?= htmlspecialchars($unit['name']) ?></div>
                        <?php if ($unit['code']): ?>
                        <div class="unit-code"><?= htmlspecialchars($unit['code']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_enrolled): ?>
                    <span class="enrolled-badge" id="badge-<?= $unit['id'] ?>">
                        <i class="fas fa-check"></i> Enrolled
                    </span>
                    <?php else: ?>
                    <span class="enrolled-badge" id="badge-<?= $unit['id'] ?>"
                          style="display:none;background:#dbeafe;color:#1e40af;border-color:#bfdbfe">
                        <i class="fas fa-check"></i> Selected
                    </span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="units-card-footer">
                <div class="selected-count">
                    <strong id="footer-count"><?= count($enrolled_ids) ?></strong>
                    unit<?= count($enrolled_ids) !== 1 ? 's' : '' ?> selected
                </div>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save My Units
                </button>
            </div>

            <?php endif; ?>
        </div>
    </form>

</div><!-- /units-page -->

<script>
function updateCount() {
    const checked = document.querySelectorAll('.unit-check:checked').length;
    const total   = document.querySelectorAll('.unit-check').length;

    document.getElementById('count-label').textContent  = checked;
    document.getElementById('footer-count').textContent = checked;

    // Update select-all checkbox state
    const selectAll = document.getElementById('select-all');
    selectAll.checked       = checked === total && total > 0;
    selectAll.indeterminate = checked > 0 && checked < total;
}

function updateRowStyle(unitId, checked) {
    const row   = document.getElementById('item-' + unitId);
    const badge = document.getElementById('badge-' + unitId);
    if (checked) {
        row.classList.add('enrolled');
        badge.style.display = '';
    } else {
        row.classList.remove('enrolled');
        badge.style.display = 'none';
    }
}

function toggleAll() {
    const selectAll = document.getElementById('select-all');
    // Determine new state: if not all checked, check all; otherwise uncheck all
    const checks  = document.querySelectorAll('.unit-check');
    const checked = document.querySelectorAll('.unit-check:checked').length;
    const newState = checked < checks.length;

    checks.forEach(cb => {
        cb.checked = newState;
        const unitId = cb.value;
        updateRowStyle(unitId, newState);
    });
    selectAll.checked       = newState;
    selectAll.indeterminate = false;
    updateCount();
}

// Confirm before saving if deselecting previously enrolled units
document.getElementById('units-form')?.addEventListener('submit', function(e) {
    const enrolled  = <?= json_encode($enrolled_ids) ?>;
    const selected  = [...document.querySelectorAll('.unit-check:checked')].map(c => parseInt(c.value));
    const removed   = enrolled.filter(id => !selected.includes(id));

    if (removed.length > 0) {
        const ok = confirm(
            `You are removing ${removed.length} previously enrolled unit${removed.length > 1 ? 's' : ''}.\n\n` +
            `Your existing progress and submissions for those units will NOT be deleted, but they will no longer appear in your dashboard until re-enrolled.\n\n` +
            `Continue?`
        );
        if (!ok) e.preventDefault();
    }
});

// Init indeterminate state on load
(function() {
    const checks  = document.querySelectorAll('.unit-check');
    const checked = document.querySelectorAll('.unit-check:checked').length;
    const sa      = document.getElementById('select-all');
    if (sa && checked > 0 && checked < checks.length) sa.indeterminate = true;
})();
</script>

</body>
</html>