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
    error_log("my_units fetch student: " . $e->getMessage());
    die("Error loading student data.");
}

$course_id     = $student['course_id'];
$year_of_study = $student['year_of_study'];

// ── Semester filter — units table has its own semester column ─────────────
$semester = intval($_GET['semester'] ?? 1);
if ($semester < 1 || $semester > 2) $semester = 1;

// ── Handle POST: save enrollment ──────────────────────────────────────────
$save_message = '';
$save_type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_units'])) {
    $post_semester     = intval($_POST['semester'] ?? 1);
    $selected_unit_ids = array_filter(array_map('intval', $_POST['unit_ids'] ?? []));

    try {
        // Verify selected units belong to this student's course + year + semester
        $valid_ids = [];
        if (!empty($selected_unit_ids)) {
            $ph    = implode(',', array_fill(0, count($selected_unit_ids), '?'));
            $types = str_repeat('i', count($selected_unit_ids));
            $stmt  = $conn->prepare("
                SELECT id FROM units
                WHERE id IN ($ph) AND course_id = ? AND year = ? AND semester = ?
            ");
            $params = array_merge(array_values($selected_unit_ids), [$course_id, $year_of_study, $post_semester]);
            $stmt->bind_param($types . 'iii', ...$params);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) $valid_ids[] = $row['id'];
            $stmt->close();
        }

        // Get units in this semester already enrolled
        $stmt = $conn->prepare("
            SELECT sue.unit_id FROM student_unit_enrollments sue
            JOIN units u ON u.id = sue.unit_id
            WHERE sue.student_id = ? AND u.semester = ? AND u.year = ? AND u.course_id = ?
        ");
        $stmt->bind_param("iiii", $student_id, $post_semester, $year_of_study, $course_id);
        $stmt->execute();
        $r = $stmt->get_result();
        $existing_ids = [];
        while ($row = $r->fetch_assoc()) $existing_ids[] = $row['unit_id'];
        $stmt->close();

        // Remove deselected units
        $to_remove = array_diff($existing_ids, $valid_ids);
        if (!empty($to_remove)) {
            $ph    = implode(',', array_fill(0, count($to_remove), '?'));
            $types = 'i' . str_repeat('i', count($to_remove));
            $del   = $conn->prepare("DELETE FROM student_unit_enrollments WHERE student_id = ? AND unit_id IN ($ph)");
            $del->bind_param($types, $student_id, ...array_values($to_remove));
            $del->execute();
            $del->close();
        }

        // Add newly selected units
        $to_add = array_diff($valid_ids, $existing_ids);
        if (!empty($to_add)) {
            $ins = $conn->prepare("INSERT IGNORE INTO student_unit_enrollments (student_id, unit_id) VALUES (?, ?)");
            foreach ($to_add as $uid) {
                $ins->bind_param("ii", $student_id, $uid);
                $ins->execute();
            }
            $ins->close();
        }

        $total        = count($valid_ids);
        $save_message = $total . ' unit' . ($total !== 1 ? 's' : '') . ' saved for Semester ' . $post_semester . '.';
        $save_type    = 'success';
        $semester     = $post_semester;

    } catch (mysqli_sql_exception $e) {
        error_log("my_units save: " . $e->getMessage());
        $save_message = 'Error saving units. Please try again.';
        $save_type    = 'error';
    }
}

// ── Fetch all units for this course + year + semester ─────────────────────
$available_units = [];
try {
    $stmt = $conn->prepare("
        SELECT id, name, code FROM units
        WHERE course_id = ? AND year = ? AND semester = ?
        ORDER BY name ASC
    ");
    $stmt->bind_param("iii", $course_id, $year_of_study, $semester);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $available_units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("my_units available: " . $e->getMessage());
}

// ── Fetch already-enrolled unit IDs for this semester ─────────────────────
$enrolled_ids = [];
try {
    $stmt = $conn->prepare("
        SELECT sue.unit_id FROM student_unit_enrollments sue
        JOIN units u ON u.id = sue.unit_id
        WHERE sue.student_id = ? AND u.semester = ? AND u.year = ? AND u.course_id = ?
    ");
    $stmt->bind_param("iiii", $student_id, $semester, $year_of_study, $course_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $enrolled_ids[] = intval($row['unit_id']);
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("my_units enrolled: " . $e->getMessage());
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
:root {
    --bg: #ffffff;
    --surface: #ffffff;
    --line: #dbe4ea;
    --line-soft: #ebeff2;
    --text: #0f1720;
    --muted: #5a6a78;
    --accent: #0f766e;
    --accent-weak: #dff4f1;
    --danger: #b42318;
}

body {
    background: var(--bg) !important;
    color: var(--text);
}

.units-page {
    max-width: 760px;
    margin: 30px auto;
    padding: 0 20px 56px;
}

.page-title {font-size:1.5rem;font-weight:800;color:var(--text);margin-bottom:6px;letter-spacing:.2px}
.page-sub {font-size:.9rem;color:var(--muted);margin-bottom:24px;line-height:1.65}

.alert {padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:.88rem;display:flex;align-items:center;gap:10px;background:#fff}
.alert-success {color:#065f46;border:1px solid #b7e3dc;border-left:4px solid #0f766e}
.alert-error   {color:var(--danger);border:1px solid #f2c5c2;border-left:4px solid var(--danger)}

.sem-tabs {display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap}
.sem-tab {
    padding:9px 16px;border-radius:999px;border:1px solid var(--line);background:#fff;
    color:var(--muted);font-size:.86rem;font-weight:700;cursor:pointer;text-decoration:none;
    transition:all .16s;display:inline-flex;align-items:center;gap:7px
}
.sem-tab:hover {border-color:var(--accent);color:var(--accent)}
.sem-tab.active {border-color:var(--accent);color:var(--accent);box-shadow:0 0 0 3px var(--accent-weak)}

.info-bar {
    border:1px dashed var(--line);border-radius:10px;padding:11px 14px;
    margin-bottom:18px;font-size:.82rem;color:var(--muted);display:flex;align-items:center;gap:7px;flex-wrap:wrap
}
.info-bar i {color:var(--accent)}

.units-card {
    background:var(--surface);border:1px solid var(--line);border-radius:16px;overflow:hidden;
    box-shadow:0 8px 24px rgba(15, 23, 32, .06)
}
.card-head {
    padding:16px 20px;display:flex;align-items:center;justify-content:space-between;
    border-bottom:1px solid var(--line-soft);background:#fff
}
.card-head h3 {font-size:.96rem;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
.card-head span {font-size:.78rem;color:var(--muted)}

.sel-all {
    display:flex;align-items:center;gap:10px;padding:11px 20px;
    border-bottom:1px solid var(--line-soft);font-size:.82rem;color:var(--muted);cursor:pointer
}
.sel-all:hover {background:#fcfdfd}
.sel-all label {cursor:pointer;font-weight:700}

.unit-list {padding:4px 0}
.unit-item {
    display:flex;align-items:center;gap:14px;padding:13px 20px;
    border-bottom:1px solid var(--line-soft);cursor:pointer;transition:background .12s, border-color .12s
}
.unit-item:last-child {border-bottom:none}
.unit-item:hover {background:#fcfdfd}
.unit-item.enrolled {background:#fbfefd;border-left:3px solid #8ecfc7;padding-left:17px}
.unit-cb {width:19px;height:19px;accent-color:var(--accent);cursor:pointer;flex-shrink:0}
.unit-info {flex:1}
.unit-name {font-size:.92rem;font-weight:700;color:var(--text);margin-bottom:2px}
.unit-code {font-size:.74rem;color:#7b8b96;font-family:Consolas, Monaco, 'Courier New', monospace}
.enroll-badge {font-size:.7rem;font-weight:800;padding:3px 9px;border-radius:999px;white-space:nowrap}
.badge-enrolled {background:#fff;border:1px solid #b7e3dc;color:#0b635c}
.badge-selected {background:#fff;border:1px solid #bdd5df;color:#245d70}

.empty-units {text-align:center;padding:42px 24px;color:#8a99a5}
.empty-units i {font-size:2.2rem;margin-bottom:12px;display:block;opacity:.32}
.empty-units h3 {font-size:.96rem;font-weight:800;color:#4d5f6d;margin-bottom:6px}
.empty-units p {font-size:.84rem;line-height:1.6}

.card-foot {
    padding:14px 20px;border-top:1px solid var(--line-soft);
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;background:#fff
}
.sel-count {font-size:.83rem;color:var(--muted)}
.sel-count strong {color:var(--accent)}
.btn-save {
    padding:10px 20px;background:var(--accent);color:#fff;border:none;border-radius:10px;
    font-size:.87rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;
    gap:7px;transition:opacity .15s, transform .1s
}
.btn-save:hover {opacity:.92;transform:translateY(-1px)}
.btn-back {
    padding:9px 14px;background:#fff;border:1px solid var(--line);border-radius:9px;
    font-size:.83rem;color:var(--muted);cursor:pointer;text-decoration:none;
    display:inline-flex;align-items:center;gap:6px;transition:border-color .15s,color .15s
}
.btn-back:hover {border-color:var(--accent);color:var(--accent)}

@media (max-width: 640px) {
    .units-page {padding: 0 14px 42px; margin-top: 20px}
    .card-head, .sel-all, .unit-item, .card-foot {padding-left: 14px; padding-right: 14px}
    .page-title {font-size:1.34rem}
}
</style>
</head>
<body>

<div class="units-page">

    <div style="margin-bottom:20px">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="page-title"><i class="fas fa-book-open" style="color:#0f766e;margin-right:8px"></i>My Units</div>
    <p class="page-sub">
        Select the units you are studying this semester. They will appear in your lessons, assessments, labs, progress tracking and attendance.
        <br><small style="color:#94a3b8">Showing Year <?= $year_of_study ?> units that match your current year of study.</small>
    </p>

    <?php if ($save_message): ?>
    <div class="alert alert-<?= $save_type ?>">
        <i class="fas <?= $save_type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
        <?= htmlspecialchars($save_message) ?>
    </div>
    <?php endif; ?>

    <!-- Semester tabs -->
    <div class="sem-tabs">
        <a href="my_units.php?semester=1" class="sem-tab <?= $semester === 1 ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Semester 1
        </a>
        <a href="my_units.php?semester=2" class="sem-tab <?= $semester === 2 ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Semester 2
        </a>
    </div>

    <!-- Info bar -->
    <div class="info-bar">
        <i class="fas fa-info-circle"></i>
        Semester <strong>&nbsp;<?= $semester ?>&nbsp;</strong> &mdash;
        Year <strong>&nbsp;<?= $year_of_study ?>&nbsp;</strong> &mdash;
        <strong><?= count($available_units) ?></strong> unit<?= count($available_units) !== 1 ? 's' : '' ?> available &mdash;
        <strong><?= count($enrolled_ids) ?></strong> enrolled
    </div>

    <!-- Units selection form -->
    <form method="POST" action="my_units.php?semester=<?= $semester ?>" id="units-form">
        <input type="hidden" name="save_units" value="1">
        <input type="hidden" name="semester"   value="<?= $semester ?>">

        <div class="units-card">
            <div class="card-head">
                <h3><i class="fas fa-list-check"></i> Semester <?= $semester ?> Units</h3>
                <span><?= count($enrolled_ids) ?> currently enrolled</span>
            </div>

            <?php if (empty($available_units)): ?>
            <div class="empty-units">
                <i class="fas fa-book"></i>
                <h3>No units found</h3>
                <p>No Semester <?= $semester ?> units are listed for Year <?= $year_of_study ?> of your course.<br>Please contact your administrator.</p>
            </div>

            <?php else: ?>

            <!-- Select All -->
            <div class="sel-all" onclick="toggleAll()">
                <input type="checkbox" id="sel-all" class="unit-cb"
                       onclick="event.stopPropagation();toggleAll()"
                       <?= count($enrolled_ids) === count($available_units) && count($available_units) > 0 ? 'checked' : '' ?>>
                <label for="sel-all">Select / Deselect All</label>
                <span style="margin-left:auto;font-size:.77rem">
                    <span id="count-label"><?= count($enrolled_ids) ?></span> / <?= count($available_units) ?> selected
                </span>
            </div>

            <div class="unit-list">
                <?php foreach ($available_units as $unit):
                    $checked = in_array($unit['id'], $enrolled_ids);
                ?>
                <label class="unit-item <?= $checked ? 'enrolled' : '' ?>" id="item-<?= $unit['id'] ?>">
                    <input type="checkbox"
                           class="unit-cb unit-check"
                           name="unit_ids[]"
                           value="<?= $unit['id'] ?>"
                           onchange="updateCount(); styleRow(<?= $unit['id'] ?>, this.checked)"
                           <?= $checked ? 'checked' : '' ?>>
                    <div class="unit-info">
                        <div class="unit-name"><?= htmlspecialchars($unit['name']) ?></div>
                        <?php if ($unit['code']): ?>
                        <div class="unit-code"><?= htmlspecialchars($unit['code']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="enroll-badge <?= $checked ? 'badge-enrolled' : 'badge-selected' ?>"
                          id="badge-<?= $unit['id'] ?>"
                          style="<?= $checked ? '' : 'display:none' ?>">
                        <i class="fas fa-check"></i> <?= $checked ? 'Enrolled' : 'Selected' ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="card-foot">
                <div class="sel-count">
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

</div>

<script>
function updateCount() {
    const n = document.querySelectorAll('.unit-check:checked').length;
    const t = document.querySelectorAll('.unit-check').length;
    document.getElementById('count-label').textContent  = n;
    document.getElementById('footer-count').textContent = n;
    const sa = document.getElementById('sel-all');
    if (!sa) return;
    sa.checked       = n === t && t > 0;
    sa.indeterminate = n > 0 && n < t;
}

function styleRow(unitId, checked) {
    const row   = document.getElementById('item-'  + unitId);
    const badge = document.getElementById('badge-' + unitId);
    if (checked) {
        row.classList.add('enrolled');
        badge.className   = 'enroll-badge badge-enrolled';
        badge.innerHTML   = '<i class="fas fa-check"></i> Enrolled';
        badge.style.display = '';
    } else {
        row.classList.remove('enrolled');
        badge.style.display = 'none';
    }
}

function toggleAll() {
    const checks  = document.querySelectorAll('.unit-check');
    const checked = document.querySelectorAll('.unit-check:checked').length;
    const newState = checked < checks.length;
    checks.forEach(cb => { cb.checked = newState; styleRow(parseInt(cb.value), newState); });
    const sa = document.getElementById('sel-all');
    sa.checked = newState; sa.indeterminate = false;
    updateCount();
}

// Warn before removing previously enrolled units
document.getElementById('units-form')?.addEventListener('submit', function(e) {
    const enrolled = <?= json_encode($enrolled_ids) ?>;
    const selected = [...document.querySelectorAll('.unit-check:checked')].map(c => parseInt(c.value));
    const removed  = enrolled.filter(id => !selected.includes(id));
    if (removed.length > 0) {
        const ok = confirm(
            `You are removing ${removed.length} previously enrolled unit${removed.length > 1 ? 's' : ''}.\n\n` +
            `Your existing progress and submissions will NOT be deleted, but those units will no longer appear in your dashboard until re-enrolled.\n\nContinue?`
        );
        if (!ok) e.preventDefault();
    }
});

// Init indeterminate state on load
(function(){
    const n = document.querySelectorAll('.unit-check:checked').length;
    const t = document.querySelectorAll('.unit-check').length;
    const sa = document.getElementById('sel-all');
    if (sa && n > 0 && n < t) sa.indeterminate = true;
})();
</script>
</body>
</html>