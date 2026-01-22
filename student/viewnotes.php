<?php
require_once '../config/db.php';
session_start();

/* ==========================================================
   AJAX HANDLERS (MARK COMPLETE & LOAD NOTES)
   ========================================================== */

// ---------- MARK AS COMPLETE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_complete'], $_POST['classnote_id'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit;
    }

    $student_id = (int) $_SESSION['user_id'];
    $classnote_id = (int) $_POST['classnote_id'];

    $check = $conn->prepare("SELECT id FROM student_classnotes_progress WHERE student_id = ? AND classnote_id = ?");
    $check->bind_param("ii", $student_id, $classnote_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE student_classnotes_progress SET status = 'completed', last_accessed = NOW() WHERE student_id = ? AND classnote_id = ?");
    } else {
        $stmt = $conn->prepare("INSERT INTO student_classnotes_progress (student_id, classnote_id, status, last_accessed) VALUES (?, ?, 'completed', NOW())");
    }

    $stmt->bind_param("ii", $student_id, $classnote_id);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $success]);
    exit;
}

// ---------- LOAD NOTES ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unit_id'])) {
    if (!isset($_SESSION['user_id'])) {
        exit('<p>Session expired. Refresh page.</p>');
    }

    $student_id = (int) $_SESSION['user_id'];
    $unit_id = (int) $_POST['unit_id'];

    // Verify that this unit belongs to student's course/year
    $auth = $conn->prepare("
        SELECT 1
        FROM units u
        INNER JOIN students s ON s.course_id = u.course_id
        WHERE u.id = ?
          AND s.id = ?
          AND u.year = s.year_of_study
        LIMIT 1
    ");
    $auth->bind_param("ii", $unit_id, $student_id);
    $auth->execute();
    $authorized = $auth->get_result()->num_rows === 1;
    $auth->close();

    if (!$authorized) {
        exit('<p>Unauthorized unit.</p>');
    }

    // Fetch notes for this unit (classnotes only)
    $stmt = $conn->prepare("
        SELECT id AS classnote_id, title, subtopics_json, uploaded_at
        FROM classnotes
        WHERE unit_id = ?
        ORDER BY uploaded_at ASC
    ");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        exit('<p>No notes available for this unit.</p>');
    }

    while ($note = $res->fetch_assoc()):
        $subtopics = json_decode($note['subtopics_json'], true) ?? [];
    ?>
        <div class="topic-card" data-classnote-id="<?= $note['classnote_id'] ?>">
            <h3><?= htmlspecialchars($note['title']) ?></h3>
            <p><small>Uploaded: <?= date("d M Y, h:i A", strtotime($note['uploaded_at'])) ?></small></p>

            <?php foreach ($subtopics as $sub): ?>
                <h4 class="subtopic-title"><?= htmlspecialchars($sub['title'] ?? '') ?></h4>
                <?php if (!empty($sub['content'])): ?>
                    <div class="content-area"><?= $sub['content'] ?></div>
                <?php endif; ?>
            <?php endforeach; ?>

            <button class="btn btn-primary" onclick="markAsComplete(<?= $note['classnote_id'] ?>)">
                Mark as Completed
            </button>
        </div>
    <?php
    endwhile;
    exit;
}

/* ==========================================================
   NORMAL PAGE LOAD
   ========================================================== */

// Redirect if not logged in or not student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = (int) $_SESSION['user_id'];

// Get student course/year
$stmt = $conn->prepare("SELECT course_id, year_of_study FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$course_id = $student['course_id'];
$year_of_study = $student['year_of_study'];

// Fetch units that have notes (DISTINCT)
$units_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.code
    FROM classnotes cn
    INNER JOIN units u ON u.id = cn.unit_id
    WHERE u.course_id = ? AND u.year = ?
    ORDER BY u.name
");
$units_stmt->bind_param("ii", $course_id, $year_of_study);
$units_stmt->execute();
$units_result = $units_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Notes</title>
<style>
body { font-family: Arial; background:#f0f2f5; padding:2rem; }
.units-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:1rem; }
.unit-tile { background:#fff; padding:1rem; border-radius:1rem; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,.1); }
.unit-tile button { margin-top:8px; padding:6px 12px; background:#3b82f6; color:#fff; border:none; border-radius:6px; cursor:pointer; }
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); justify-content:center; align-items:center; }
.modal-content { background:#fff; width:90%; max-width:900px; max-height:90%; overflow:auto; padding:1.5rem; border-radius:1rem; }
.modal-close { float:right; font-size:24px; cursor:pointer; }
.topic-card { background:#fff; padding:1rem; border-radius:8px; margin-bottom:1rem; }
.progress-badge { padding:4px 12px; border-radius:20px; font-size:.8em; }
.progress-in_progress { background:#fef3c7; color:#b45309; }
.progress-completed { background:#d1fae5; color:#047857; }
.btn-primary { background:#3b82f6; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; }
.content-area img { max-width:100%; margin:5px 0; border-radius:6px; cursor:pointer; }
</style>
</head>
<body>

<h1>Student Notes</h1>

<div class="units-grid">
<?php while ($u = $units_result->fetch_assoc()): ?>
    <div class="unit-tile">
        <h4><?= htmlspecialchars($u['name']) ?></h4>
        <p><?= htmlspecialchars($u['code']) ?></p>
        <button onclick="openNotesModal(<?= $u['id'] ?>)">View Notes</button>
    </div>
<?php endwhile; ?>
</div>

<div id="notesModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div id="modalNotesContent"></div>
    </div>
</div>

<script>
function openNotesModal(unitId) {
    const modal = document.getElementById('notesModal');
    const content = document.getElementById('modalNotesContent');
    content.innerHTML = 'Loading...';
    modal.style.display = 'flex';

    const fd = new FormData();
    fd.append('unit_id', unitId);

    fetch('', { method:'POST', body: fd })
        .then(r => r.text())
        .then(html => content.innerHTML = html)
        .catch(() => content.innerHTML = '<p>Error loading notes.</p>');
}

function closeModal() {
    document.getElementById('notesModal').style.display = 'none';
}

function markAsComplete(id) {
    const fd = new FormData();
    fd.append('mark_complete', 1);
    fd.append('classnote_id', id);

    fetch('', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if(d.success) location.reload();
            else alert('Failed to mark as completed.');
        })
        .catch(() => alert('Error marking note as completed.'));
}
</script>

</body>
</html>
