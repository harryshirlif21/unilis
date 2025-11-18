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

/* ============================================================
   AJAX HANDLERS
   ============================================================ */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    // ========== LOAD ASSIGNMENTS FOR UNIT ==========
    if ($action === 'get_assignments' && isset($_GET['unit_id'])) {
        $unit_id = intval($_GET['unit_id']);
        $stmt = $conn->prepare("
            SELECT a.id, a.title, a.created_at, a.deadline,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submissions_count
            FROM assignments a
            WHERE a.unit_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        $items = [];
        while ($r = $rs->fetch_assoc()) $items[] = $r;
        echo json_encode(['status'=>'ok','items'=>$items]);
        exit;
    }

    // ========== LOAD ASSIGNMENT OVERVIEW ==========
    if ($action === 'get_assignment_overview' && isset($_GET['assignment_id'])) {
        $assignment_id = intval($_GET['assignment_id']);

        // Validate lecturer permission
        $check = $conn->prepare("
            SELECT a.unit_id, u.course_id, a.deadline
            FROM assignments a
            JOIN units u ON a.unit_id = u.id
            JOIN lecturer_units lu ON u.id = lu.unit_id
            WHERE a.id = ? AND lu.lecturer_id = ?
        ");
        $check->bind_param("ii", $assignment_id, $lecturer_id);
        $check->execute();
        $meta = $check->get_result()->fetch_assoc();

        if (!$meta) {
            echo json_encode(['status'=>'error','message'=>'Unauthorized']);
            exit;
        }

        $unit_id = $meta['unit_id'];
        $course_id = $meta['course_id'];
        $deadline = $meta['deadline'];

        // All students in course
        $students = [];
        $q = $conn->prepare("SELECT id,name,reg_no FROM students WHERE course_id=?");
        $q->bind_param("i", $course_id);
        $q->execute();
        $rs = $q->get_result();
        while ($s = $rs->fetch_assoc()) $students[$s['id']] = $s;

        // Submitted
        $submitted = [];
        $submitted_ids = [];
        $q2 = $conn->prepare("
            SELECT s.*, st.name AS student_name, st.reg_no
            FROM submissions s
            JOIN students st ON st.id = s.student_id
            WHERE s.assignment_id=?
        ");
        $q2->bind_param("i", $assignment_id);
        $q2->execute();
        $rs2 = $q2->get_result();

        while ($r = $rs2->fetch_assoc()) {
            $isLate = $deadline && $r['created_at'] > $deadline;
            $submitted[] = [
                'submission_id'=>$r['id'],
                'student_id'=>$r['student_id'],
                'student_name'=>$r['student_name'],
                'reg_no'=>$r['reg_no'],
                'file_path'=>$r['file_path'],
                'submitted_at'=>$r['created_at'],
                'is_late'=>$isLate,
                'is_graded'=>$r['is_graded']
            ];
            $submitted_ids[] = $r['student_id'];
        }

        // Not submitted
        $not_submitted = [];
        foreach ($students as $sid=>$s) {
            if (!in_array($sid, $submitted_ids)) {
                $not_submitted[] = [
                    'student_id'=>$sid,
                    'student_name'=>$s['name'],
                    'reg_no'=>$s['reg_no']
                ];
            }
        }

        echo json_encode([
            'status'=>'ok',
            'unit_id'=>$unit_id,
            'course_id'=>$course_id,
            'deadline'=>$deadline,
            'total_expected'=>count($students),
            'submitted_count'=>count($submitted),
            'not_submitted_count'=>count($not_submitted),
            'late_count'=>count(array_filter($submitted, fn($x)=>$x['is_late'])),
            'submitted'=>$submitted,
            'not_submitted'=>$not_submitted
        ]);
        exit;
    }

    // ========== LOAD SUBMISSIONS FOR TABLE ==========
    if ($action === 'get_submissions' && isset($_GET['assignment_id'])) {
        $assignment_id = intval($_GET['assignment_id']);

        // Validate lecturer
        $check = $conn->prepare("
            SELECT u.id
            FROM assignments a
            JOIN units u ON u.id=a.unit_id
            JOIN lecturer_units lu ON lu.unit_id=u.id
            WHERE a.id=? AND lu.lecturer_id=?
        ");
        $check->bind_param("ii", $assignment_id, $lecturer_id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            echo json_encode(['status'=>'error','message'=>'Unauthorized']);
            exit;
        }

        $items = [];
        $s = $conn->prepare("
            SELECT s.id AS submission_id, st.name AS student_name, st.reg_no,
                   s.file_path, s.marks, s.is_graded, s.comment
            FROM submissions s
            JOIN students st ON st.id = s.student_id
            WHERE s.assignment_id=?
        ");
        $s->bind_param("i", $assignment_id);
        $s->execute();
        $rs = $s->get_result();
        while ($r = $rs->fetch_assoc()) $items[] = $r;

        echo json_encode(['status'=>'ok','items'=>$items]);
        exit;
    }

    echo json_encode(['status'=>'error','message'=>'Invalid action']);
    exit;
}

/* ============================================================
   FETCH ALL UNITS FOR TILES VIEW
   ============================================================ */
$q = $conn->prepare("
    SELECT u.id AS unit_id, u.name AS unit_name, u.code AS unit_code,
           u.year, c.id AS course_id, c.name AS course_name,
           (SELECT COUNT(*) FROM assignments a WHERE a.unit_id=u.id) AS assignments_count,
           (SELECT COUNT(*) FROM submissions s
                JOIN assignments a ON a.id=s.assignment_id
                WHERE a.unit_id=u.id
           ) AS submissions_count,
           (SELECT MAX(created_at) FROM assignments a WHERE a.unit_id=u.id) AS last_sent,
           (SELECT MIN(deadline) FROM assignments a WHERE a.unit_id=u.id AND a.deadline >= NOW()) AS nearest_deadline
    FROM units u
    JOIN courses c ON c.id=u.course_id
    JOIN lecturer_units lu ON lu.unit_id=u.id
    WHERE lu.lecturer_id=?
");
$q->bind_param("i", $lecturer_id);
$q->execute();
$units = $q->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Assignment Dashboard</title>
<style>
body { font-family: Arial; background:#f4f6fa; padding:20px; }
.tiles { display:grid; grid-template-columns:repeat(auto-fill,260px); gap:14px; }
.tile { background:#fff; padding:16px; border-radius:10px; cursor:pointer; box-shadow:0 4px 20px rgba(0,0,0,.06); }
.tile:hover { transform:scale(1.02); }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.4); display:none; justify-content:center; align-items:center; }
.modal { background:#fff; padding:20px; border-radius:10px; width:800px; max-height:90vh; overflow:auto; }
.close-x { float:right; cursor:pointer; font-size:22px; }
.assign-grid { display:grid; grid-template-columns:repeat(auto-fill,200px); gap:10px; }
.assign-card { background:#f5f7fb; padding:12px; border-radius:6px; cursor:pointer; }
.submissions-wrap { display:none; background:#fff; padding:20px; border-radius:12px; margin-top:20px; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th,td { padding:10px; border:1px solid #ddd; }
.btn { padding:8px 12px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; }
.btn.secondary { background:#e0e7ff; color:#2563eb; }
</style>
</head>
<body>

<h1>📘 Assignment Submissions Dashboard</h1>

<div class="tiles" id="tilesGrid">
<?php while ($u = $units->fetch_assoc()): ?>
    <div class="tile" data-unit-id="<?= $u['unit_id'] ?>">
        <h3><?= htmlspecialchars($u['unit_name']) ?></h3>
        <small><?= htmlspecialchars($u['course_name']) ?> • Year <?= $u['year'] ?></small>
        <p>Assignments: <?= $u['assignments_count'] ?></p>
        <p>Submissions: <?= $u['submissions_count'] ?></p>
    </div>
<?php endwhile; ?>
</div>

<!-- ASSIGNMENTS MODAL -->
<div class="modal-overlay" id="modalOverlay">
<div class="modal">
    <div class="close-x" id="closeModal">✖</div>
    <h3 id="modalUnitTitle"></h3>
    <div class="assign-grid" id="assignGrid"></div>
</div>
</div>

<!-- OVERVIEW MODAL -->
<div class="modal-overlay" id="overviewOverlay">
<div class="modal">
    <div class="close-x" id="closeOverview">✖</div>
    <h3 id="overviewTitle"></h3>
    <div id="overviewContent"></div>
    <button class="btn" id="openTableBtn">Open Full Table</button>
</div>
</div>

<!-- OLD TABLE SYSTEM EXACTLY AS BEFORE -->
<section id="submissionsArea" class="submissions-wrap">
    <h2 id="subTitle">Submissions Table</h2>
    <p id="subMeta"></p>
    <form method="POST" action="../actions.php" id="marksForm">
        <input type="hidden" name="action" value="save_marks">
        <input type="hidden" name="assignment_id" id="tableAssignmentId">
        <div id="tableWrap"></div>
        <br>
        <button type="submit" class="btn">💾 Save Marks</button>
    </form>
</section>

<script>
const tiles = document.querySelectorAll(".tile");
const modalOverlay = document.getElementById("modalOverlay");
const overviewOverlay = document.getElementById("overviewOverlay");
const closeModal = document.getElementById("closeModal");
const closeOverview = document.getElementById("closeOverview");

tiles.forEach(t => {
    t.addEventListener("click", () => loadAssignments(t.dataset.unitId, t));
});

async function loadAssignments(unitId, tile) {
    document.getElementById("modalUnitTitle").textContent = tile.querySelector("h3").textContent + " — Assignments";
    modalOverlay.style.display = "flex";

    const res = await fetch("?ajax=get_assignments&unit_id="+unitId);
    const data = await res.json();
    const grid = document.getElementById("assignGrid");
    grid.innerHTML = "";

    data.items.forEach(a=>{
        let d=document.createElement("div");
        d.className="assign-card";
        d.textContent=a.title;
        d.onclick=()=>openOverview(a.id,a.title);
        grid.appendChild(d);
    });
}

function openOverview(id,title){
    modalOverlay.style.display="none";
    overviewOverlay.style.display="flex";
    document.getElementById("overviewTitle").textContent=title+" — Overview";

    fetch("?ajax=get_assignment_overview&assignment_id="+id)
        .then(r=>r.json())
        .then(d=>{
            document.getElementById("overviewContent").innerHTML =
                `<p><strong>Total expected:</strong> ${d.total_expected}</p>
                 <p><strong>Submitted:</strong> ${d.submitted_count}</p>
                 <p><strong>Not submitted:</strong> ${d.not_submitted_count}</p>
                 <p><strong>Late:</strong> ${d.late_count}</p>`;
            document.getElementById("openTableBtn").onclick=()=>loadTable(id,title);
        });
}

async function loadTable(id,title){
    overviewOverlay.style.display="none";
    submissionsArea.style.display="block";

    document.getElementById("subTitle").textContent = title+" — Submissions";
    document.getElementById("tableAssignmentId").value=id;

    const res=await fetch("?ajax=get_submissions&assignment_id="+id);
    const data=await res.json();

    document.getElementById("subMeta").textContent = data.items.length+" submissions";

    // ==== OLD TABLE SYSTEM EXACTLY ====
    let rows = "";
    data.items.forEach(s=>{
        rows += `
        <tr>
            <td>${s.student_name}</td>
            <td>${s.reg_no}</td>
            <td><a href="../assets/uploads/submissions/${s.file_path}" target="_blank">View</a></td>
            <td><input type="number" name="marks[${s.submission_id}]" value="${s.marks ?? ''}" min="0" max="100"></td>
            <td><input type="checkbox" name="is_graded[${s.submission_id}]" ${s.is_graded ? "checked":""}></td>
            <td><textarea name="comment[${s.submission_id}]">${s.comment ?? ""}</textarea></td>
        </tr>`;
    });

    document.getElementById("tableWrap").innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Reg No</th>
                    <th>Submission</th>
                    <th>Marks (out of 100)</th>
                    <th>Graded</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

closeModal.onclick=()=>modalOverlay.style.display="none";
closeOverview.onclick=()=>overviewOverlay.style.display="none";
</script>

</body>
</html>
