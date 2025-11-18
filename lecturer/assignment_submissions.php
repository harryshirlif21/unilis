<?php
session_start();
require_once '../config/db.php';

// --- Authentication ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

/* ================================================================
   AJAX HANDLERS
================================================================ */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    /* --- 1. GET ASSIGNMENTS FOR A UNIT --- */
    if ($action === 'get_assignments' && isset($_GET['unit_id'])) {
        $unit_id = intval($_GET['unit_id']);
        $stmt = $conn->prepare("
            SELECT a.id, a.title, a.created_at, a.deadline,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submissions_count
            FROM assignments a
            WHERE a.unit_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->bind_param('i', $unit_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $assignments = [];
        while ($r = $res->fetch_assoc()) $assignments[] = $r;

        echo json_encode(['status' => 'ok', 'items' => $assignments]);
        exit;
    }

    /* --- 2. GET ASSIGNMENT OVERVIEW --- */
    if ($action === 'get_assignment_overview' && isset($_GET['assignment_id'])) {
        $assignment_id = intval($_GET['assignment_id']);

        $check = $conn->prepare("
            SELECT a.unit_id, u.course_id, a.deadline
            FROM assignments a
            JOIN units u ON a.unit_id = u.id
            JOIN lecturer_units lu ON u.id = lu.unit_id
            WHERE a.id = ? AND lu.lecturer_id = ?
        ");
        $check->bind_param('ii', $assignment_id, $lecturer_id);
        $check->execute();
        $meta = $check->get_result()->fetch_assoc();

        if (!$meta) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized or assignment not found']);
            exit;
        }

        $unit_id = (int)$meta['unit_id'];
        $course_id = (int)$meta['course_id'];
        $deadline = $meta['deadline'];

        // Students in course
        $totStmt = $conn->prepare("SELECT id, name, reg_no FROM students WHERE course_id = ?");
        $totStmt->bind_param('i', $course_id);
        $totStmt->execute();
        $studentsRes = $totStmt->get_result();

        $allStudents = [];
        while ($s = $studentsRes->fetch_assoc()) $allStudents[$s['id']] = $s;

        // Submissions
        $subStmt = $conn->prepare("
            SELECT s.id AS submission_id, s.student_id, st.name AS student_name, 
                   st.reg_no, s.file_path, s.created_at, s.marks, s.is_graded, s.comment
            FROM submissions s
            JOIN students st ON s.student_id = st.id
            WHERE s.assignment_id = ?
            ORDER BY s.created_at DESC
        ");
        $subStmt->bind_param('i', $assignment_id);
        $subStmt->execute();

        $subsRes = $subStmt->get_result();
        $submitted = [];
        $submittedIds = [];

        while ($r = $subsRes->fetch_assoc()) {
            $submitted[] = $r;
            $submittedIds[] = (int)$r['student_id'];
        }

        // Not Submitted
        $notSubmitted = [];
        foreach ($allStudents as $sid => $s) {
            if (!in_array($sid, $submittedIds, true)) {
                $notSubmitted[] = $s;
            }
        }

        echo json_encode([
            'status' => 'ok',
            'submitted' => $submitted,
            'not_submitted' => $notSubmitted,
            'total_expected' => count($allStudents),
            'submitted_count' => count($submitted),
            'not_submitted_count' => count($notSubmitted),
            'deadline' => $deadline,
            'unit_id' => $unit_id,
            'course_id' => $course_id
        ]);
        exit;
    }

    /* --- 3. GET SUBMISSIONS TABLE DATA --- */
    if ($action === 'get_submissions' && isset($_GET['assignment_id'])) {
        $assignment_id = intval($_GET['assignment_id']);

        $stmt = $conn->prepare("
            SELECT s.id AS submission_id, st.name AS student_name, 
                   st.reg_no, s.file_path, s.marks, s.is_graded, s.comment
            FROM submissions s
            JOIN students st ON s.student_id = st.id
            WHERE s.assignment_id = ?
            ORDER BY st.name ASC
        ");
        $stmt->bind_param('i', $assignment_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        while ($r = $res->fetch_assoc()) $items[] = $r;

        echo json_encode(['status' => 'ok', 'items' => $items]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

/* ==================================================================
   FETCH UNITS FOR MAIN TILE VIEW
================================================================== */

$unitsQuery = $conn->prepare("
    SELECT u.id AS unit_id, u.name AS unit_name, u.code AS unit_code, 
           c.name AS course_name, u.year,
           (SELECT COUNT(*) FROM assignments a WHERE a.unit_id = u.id) AS assignments_count,
           (SELECT COUNT(*) FROM submissions s 
                JOIN assignments aa ON s.assignment_id = aa.id 
                WHERE aa.unit_id = u.id) AS submissions_count
    FROM units u
    JOIN courses c ON u.course_id = c.id
    JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE lu.lecturer_id = ?
    ORDER BY c.name, u.name
");
$unitsQuery->bind_param('i', $lecturer_id);
$unitsQuery->execute();
$units = $unitsQuery->get_result();
$unitsQuery->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assignment Submissions — Dashboard</title>
<style>
/* ------- BASIC DASHBOARD UI STYLES (CLEAN & FIXED) ------- */
body{font-family:Arial, sans-serif;background:#f7f8fc;margin:0;padding:20px;}
.tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;}
.tile{background:white;padding:14px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.08);cursor:pointer;transition:.2s;}
.tile:hover{transform:translateY(-4px);}
.badge{background:#eef2ff;padding:4px 8px;border-radius:6px;font-size:12px;display:inline-block;margin-right:6px;}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:50;}
.modal-overlay.show{display:flex;}
.modal{background:white;padding:20px;border-radius:10px;width:90%;max-width:850px;position:relative;}
.close-x{position:absolute;top:10px;right:14px;font-size:20px;cursor:pointer;}
.assign-card{background:#fafafa;padding:12px;border-radius:8px;margin-bottom:8px;border:1px solid #eee;cursor:pointer;}
.assign-card:hover{background:#f0f4ff;}
.submissions-wrap{background:white;padding:20px;margin-top:20px;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.1);display:none;}
.table{width:100%;border-collapse:collapse;}
.table th{background:#eef1ff;padding:8px;text-align:left;font-size:14px;}
.table td{padding:8px;border-top:1px solid #eee;}
.btn{background:#2563eb;color:white;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;}
.btn.secondary{background:#e5edff;color:#2563eb;}
</style>
</head>

<body>

<h2>📄 Assignment Submissions — Unit Dashboard</h2>

<section class="tiles" id="tilesGrid">
<?php while($u = $units->fetch_assoc()): ?>
    <div class="tile" data-unit-id="<?= $u['unit_id'] ?>">
        <h3><?= htmlspecialchars($u['unit_name']) ?></h3>
        <div><?= htmlspecialchars($u['course_name']) ?> — Year <?= $u['year'] ?></div>
        <div class="badge">Assignments: <?= $u['assignments_count'] ?></div>
        <div class="badge">Submissions: <?= $u['submissions_count'] ?></div>
    </div>
<?php endwhile; ?>
</section>

<!-- ASSIGNMENTS MODAL -->
<div id="modalOverlay" class="modal-overlay">
    <div class="modal">
        <div id="closeModal" class="close-x">✖</div>
        <h3 id="modalUnitTitle">Assignments</h3>
        <div id="assignGrid"></div>
    </div>
</div>

<!-- OVERVIEW MODAL -->
<div id="overviewOverlay" class="modal-overlay">
    <div class="modal">
        <div id="closeOverview" class="close-x">✖</div>
        <h3 id="overviewTitle">Overview</h3>
        <div id="overviewContent"></div>
        <button id="openTableBtn" class="btn">Open Submissions Table</button>
    </div>
</div>

<!-- SUBMISSIONS TABLE -->
<section id="submissionsArea" class="submissions-wrap">
    <h3 id="subTitle">Submissions</h3>
    <div id="tableWrap"></div>
</section>

<script>
/* ======================================================
   FIXED & FULLY WORKING JAVASCRIPT
====================================================== */

const $ = s => document.querySelector(s);

const tilesGrid = $('#tilesGrid');
const modalOverlay = $('#modalOverlay');
const assignGrid = $('#assignGrid');
const closeModal = $('#closeModal');

const overviewOverlay = $('#overviewOverlay');
const closeOverview = $('#closeOverview');

const overviewContent = $('#overviewContent');
const openTableBtn = $('#openTableBtn');

const submissionsArea = $('#submissionsArea');
const tableWrap = $('#tableWrap');

/* ------ MODAL FUNCTIONS ------ */
function openModal(el) {
    el.classList.add('show');
}
function closeModalOverlay() {
    modalOverlay.classList.remove('show');
}
function closeOverviewOverlay() {
    overviewOverlay.classList.remove('show');
}

/* ------ TILE CLICK → LOAD ASSIGNMENTS ------ */
tilesGrid.addEventListener('click', function(e) {
    const tile = e.target.closest('.tile');
    if (!tile) return;

    const unitId = tile.dataset.unitId;

    modalOverlay.classList.add('show');
    assignGrid.innerHTML = "Loading...";

    fetch(`?ajax=get_assignments&unit_id=${unitId}`)
        .then(r => r.json())
        .then(data => {
            assignGrid.innerHTML = "";

            if (!data.items.length) {
                assignGrid.innerHTML = "<p>No assignments created for this unit.</p>";
                return;
            }

            data.items.forEach(a => {
                let div = document.createElement('div');
                div.className = "assign-card";
                div.innerHTML = `<strong>${a.title}</strong><br>
                                 Deadline: ${a.deadline ?? '—'}<br>
                                 Submissions: ${a.submissions_count}`;
                div.onclick = () => openAssignmentOverview(a.id, a.title);

                assignGrid.appendChild(div);
            });
        });
});

/* ------ CLOSE BUTTONS FIXED ------ */
closeModal.addEventListener('click', closeModalOverlay);
closeOverview.addEventListener('click', closeOverviewOverlay);

/* ------ ASSIGNMENT OVERVIEW ------ */
function openAssignmentOverview(id, title) {
    closeModalOverlay();
    overviewOverlay.classList.add('show');
    overviewContent.innerHTML = "Loading...";

    fetch(`?ajax=get_assignment_overview&assignment_id=${id}`)
        .then(r => r.json())
        .then(data => {
            overviewContent.innerHTML = `
                <p><strong>Total Expected:</strong> ${data.total_expected}</p>
                <p><strong>Submitted:</strong> ${data.submitted_count}</p>
                <p><strong>Not Submitted:</strong> ${data.not_submitted_count}</p>
                <hr>
                <h4>Submitted Students</h4>
                ${data.submitted.map(s => `
                    <div>
                        ${s.student_name} — 
                        <a href="../assets/uploads/submissions/${s.file_path}" target="_blank">View</a>
                    </div>
                `).join('')}
                <hr>
                <h4>Not Submitted</h4>
                ${data.not_submitted.map(s => `<div>${s.name} (${s.reg_no})</div>`).join('')}
            `;
        });

    openTableBtn.onclick = () => {
        closeOverviewOverlay();
        loadSubmissionTable(id, title);
    };
}

/* ------ SUBMISSIONS TABLE ------ */
function loadSubmissionTable(assignmentId, title) {
    submissionsArea.style.display = 'block';
    $('#subTitle').innerHTML = `${title} — Submissions`;

    tableWrap.innerHTML = "Loading...";

    fetch(`?ajax=get_submissions&assignment_id=${assignmentId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.items.length) {
                tableWrap.innerHTML = "<p>No submissions yet.</p>";
                return;
            }

            let rows = data.items.map(s => `
                <tr>
                    <td>${s.student_name}</td>
                    <td>${s.reg_no}</td>
                    <td><a href="../assets/uploads/submissions/${s.file_path}" target="_blank">View</a></td>
                    <td>${s.marks ?? '—'}</td>
                    <td>${s.is_graded ? 'Graded' : 'Pending'}</td>
                    <td>${s.comment ?? ''}</td>
                </tr>
            `).join('');

            tableWrap.innerHTML = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th><th>Reg No</th><th>Submission</th>
                            <th>Marks</th><th>Status</th><th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
        });
}

</script>

</body>
</html>
