<?php
session_start();
require_once '../config/db.php';

/* ============================================================
   AUTHENTICATION
   ============================================================ */
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

/* ============================================================
   AJAX ENDPOINT CONTROLLER
   ============================================================ */
if (isset($_GET['ajax'])) {
    header("Content-Type: application/json; charset=utf-8");
    $action = $_GET['ajax'];

    // 1) LOAD ASSIGNMENTS FOR A UNIT
    if ($action === "get_assignments" && isset($_GET['unit_id'])) {
        $unit_id = intval($_GET['unit_id']);

        $sql = "
            SELECT 
                a.id,
                a.title,
                a.created_at,
                a.deadline,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submissions_count,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.submitted_at > a.deadline) AS late_count,
                (SELECT COUNT(*) 
                    FROM students st 
                    JOIN student_units su ON su.student_id = st.id 
                    WHERE su.unit_id = ?
                ) AS expected_students
            FROM assignments a
            WHERE a.unit_id = ?
            ORDER BY a.created_at DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $unit_id, $unit_id);
        $stmt->execute();
        $rs = $stmt->get_result();

        $items = [];
        while ($row = $rs->fetch_assoc()) {
            $progress = 0;
            if ($row['expected_students'] > 0) {
                $progress = round(($row['submissions_count'] / $row['expected_students']) * 100);
            }
            $row['progress'] = $progress;
            $items[] = $row;
        }

        echo json_encode(['status' => 'ok', 'items' => $items]);
        exit;
    }

    // 2) LOAD SUBMISSIONS FOR GRADING
    if ($action === "get_submissions" && isset($_GET['assignment_id'])) {
        $aid = intval($_GET['assignment_id']);

        $q = $conn->prepare("
            SELECT a.id
            FROM assignments a
            JOIN units u ON u.id = a.unit_id
            JOIN lecturer_units lu ON lu.unit_id = u.id
            WHERE a.id = ? AND lu.lecturer_id = ?
        ");
        $q->bind_param("ii", $aid, $lecturer_id);
        $q->execute();
        if (!$q->get_result()->fetch_assoc()) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }

        $sql = "
            SELECT 
                s.id AS submission_id,
                st.name AS student_name,
                st.reg_no,
                s.file_path,
                s.marks,
                s.is_graded,
                s.comment AS ai_feedback,
                s.submitted_at,
                CASE WHEN s.submitted_at > a.deadline THEN 1 ELSE 0 END AS is_lated,
                a.title AS assignment_title
            FROM submissions s
            JOIN students st ON st.id = s.student_id
            JOIN assignments a ON a.id = s.assignment_id
            WHERE s.assignment_id = ?
            ORDER BY st.name ASC
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("i", $aid);
        $st->execute();
        $rs = $st->get_result();

        $items = [];
        while ($r = $rs->fetch_assoc()) {
            $items[] = $r;
        }

        echo json_encode(['status' => 'ok', 'items' => $items]);
        exit;
    }

    // 3) GRADE SUBMISSION
    if ($action === "grade_submission" && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $submission_id = intval($_POST['submission_id']);
        $marks = floatval($_POST['marks']);
        $ai_feedback = $_POST['ai_feedback'] ?? '';

        $stmt = $conn->prepare("
            UPDATE submissions 
            SET marks = ?, comment = ?, is_graded = 1 
            WHERE id = ?
        ");
        $stmt->bind_param("dsi", $marks, $ai_feedback, $submission_id);

        if ($stmt->execute()) {
            $assignment_id = $conn->query("SELECT assignment_id FROM submissions WHERE id = $submission_id")->fetch_assoc()['assignment_id'];
            echo json_encode(['status' => 'ok', 'assignment_id' => $assignment_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save grade']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

/* ============================================================
   LOAD ALL UNITS
   ============================================================ */
$sql = "
    SELECT 
        u.id AS unit_id,
        u.name AS unit_name,
        u.code AS unit_code,
        u.year,
        (SELECT COUNT(*) FROM assignments a WHERE a.unit_id = u.id) AS assignments_count,
        (SELECT COUNT(*) 
           FROM submissions s
           JOIN assignments a ON a.id = s.assignment_id
           WHERE a.unit_id = u.id
        ) AS submissions_count,
        (SELECT MAX(created_at) FROM assignments a WHERE a.unit_id = u.id) AS last_sent,
        (SELECT MIN(deadline) FROM assignments a WHERE a.unit_id = u.id AND deadline >= NOW()) AS nearest_deadline,
        (SELECT COUNT(*) FROM student_units su WHERE su.unit_id = u.id) AS expected_students
    FROM units u
    JOIN lecturer_units lu ON lu.unit_id = u.id
    WHERE lu.lecturer_id = ?
";
$q = $conn->prepare($sql);
$q->bind_param("i", $lecturer_id);
$q->execute();
$units = $q->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lecturer Dashboard</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<link rel="stylesheet" href="assets/css/modern-blue.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
.modal-content { background:#fff; margin:50px auto; padding:20px; width:80%; border-radius:8px; position:relative; }
.close-modal { position:absolute; top:10px; right:20px; font-size:28px; cursor:pointer; }
.progress-bar { background:#eee; height:20px; border-radius:10px; overflow:hidden; margin:5px 0; }
.progress-fill { background:#3498db; height:100%; width:0%; transition: width 0.5s; }
.progress-bar-small { background:#eee; height:10px; border-radius:5px; overflow:hidden; margin:0; }
.progress-fill-small { background:#3498db; height:100%; width:0%; }
.unit-grid { display:flex; flex-wrap:wrap; gap:20px; }
.unit-tile { background:#f0f8ff; padding:15px; border-radius:10px; width:250px; box-shadow:0 2px 5px rgba(0,0,0,0.1); }
.btn-view-assignments, .btn-view-submissions, .btn-grade, .btn-pdf { background:#3498db; color:#fff; border:none; padding:6px 12px; cursor:pointer; border-radius:5px; margin:2px; }
.btn-grade { background:#28a745; }
tr.graded { background:#d4edda; }
</style>
</head>
<body>
<div class="dashboard-container">
    <h2>Welcome, <?= htmlspecialchars($lecturer_name) ?></h2>

    <!-- UNITS -->
    <div class="unit-grid">
        <?php while ($unit = $units->fetch_assoc()): ?>
        <?php
            $progress = 0;
            if ($unit['expected_students'] > 0) {
                $progress = round(($unit['submissions_count'] / $unit['expected_students']) * 100);
            }
        ?>
        <div class="unit-tile" data-unit-id="<?= $unit['unit_id'] ?>">
            <h3><?= htmlspecialchars($unit['unit_name']) ?> (<?= htmlspecialchars($unit['unit_code']) ?>)</h3>
            <p>Assignments: <?= $unit['assignments_count'] ?></p>
            <p>Submissions: <?= $unit['submissions_count'] ?></p>
            <div class="progress-bar"><div class="progress-fill" style="width: <?= $progress ?>%;"></div></div>
            <button class="btn-view-assignments" data-unit-id="<?= $unit['unit_id'] ?>">View Assignments</button>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- ASSIGNMENTS MODAL -->
    <div id="assignmentsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Assignments</h3>
       <a href="assgenpdf.php?unit_id=<?= $selected_unit ?>" target="_blank">
    <button id="generateAssignmentsPDF" class="btn-pdf">Generate PDF</button>
</a>


            <table id="assignmentsTable" class="display" width="100%">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Created</th>
                        <th>Deadline</th>
                        <th>Submissions</th>
                        <th>Late</th>
                        <th>Progress</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- SUBMISSIONS MODAL -->
    <div id="submissionsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Submissions</h3>
            <button id="generateSubmissionsPDF" class="btn-pdf">Generate PDF</button>
            <table id="submissionsTable" class="display" width="100%">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Reg No</th>
                        <th>Submitted At</th>
                        <th>File</th>
                        <th>Late</th>
                        <th>Graded</th>
                        <th>Marks</th>
                        <th>AI Feedback</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- GRADING MODAL -->
    <div id="gradingModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Grade Submission</h3>
            <div id="submissionDetails">
                <p><strong>Student:</strong> <span id="studentName"></span></p>
                <p><strong>Assignment:</strong> <span id="assignmentTitle"></span></p>
                <p><strong>Submitted At:</strong> <span id="submittedAt"></span></p>
                <p><strong>File:</strong> <span id="fileLink"></span></p>
            </div>
            <hr>
            <form id="gradingForm">
                <label>Marks Awarded:</label>
                <input type="number" name="marks" id="marks" min="0" step="0.1" required><br><br>
                <label>AI Feedback:</label>
                <textarea name="ai_feedback" id="ai_feedback" rows="4"></textarea><br><br>
                <input type="hidden" name="submission_id" id="submissionId">
                <button type="submit" class="btn-grade">Save Grade</button>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // --- GLOBAL VARIABLE ---
    let selectedUnitId = null; // stores currently selected unit

    // --- MODAL HELPERS ---
    function openModal(id) { $('#' + id).fadeIn(); }
    function closeModal(id) { $('#' + id).fadeOut(); }
    $('.close-modal').click(function(){ closeModal($(this).closest('.modal').attr('id')); });

    // --- VIEW ASSIGNMENTS ---
    $('.btn-view-assignments').click(function(){
        const unit_id = $(this).data('unit-id');
        selectedUnitId = unit_id; // store selected unit globally

        $.getJSON('?ajax=get_assignments&unit_id=' + unit_id, function(res){
            if(res.status==='ok'){
                const tbody = $('#assignmentsTable tbody'); 
                tbody.empty();
                res.items.forEach(a=>{
                    tbody.append(`
                        <tr>
                            <td>${a.title}</td>
                            <td>${a.created_at}</td>
                            <td>${a.deadline}</td>
                            <td>${a.submissions_count} / ${a.expected_students}</td>
                            <td>${a.late_count}</td>
                            <td>
                                <div class="progress-bar-small">
                                    <div class="progress-fill-small" style="width:${a.progress}%"></div>
                                </div>
                            </td>
                            <td><button class="btn-view-submissions" data-assignment-id="${a.id}">View Submissions</button></td>
                        </tr>
                    `);
                });
                if ($.fn.DataTable.isDataTable('#assignmentsTable')) $('#assignmentsTable').DataTable().clear().destroy();
                $('#assignmentsTable').DataTable();
                openModal('assignmentsModal');
            } else alert(res.message);
        });
    });

    // --- VIEW SUBMISSIONS ---
    $(document).on('click','.btn-view-submissions',function(){
        const assignment_id = $(this).data('assignment-id');
        $.getJSON('?ajax=get_submissions&assignment_id=' + assignment_id, function(res){
            if(res.status==='ok'){
                const tbody = $('#submissionsTable tbody'); 
                tbody.empty();
                res.items.forEach(s=>{
                    tbody.append(`
                        <tr class="${s.is_graded?'graded':''}">
                            <td>${s.student_name}</td>
                            <td>${s.reg_no}</td>
                            <td>${s.submitted_at}</td>
                            <td>${s.file_path ? `<a href="${s.file_path}" target="_blank">Download</a>` : '—'}</td>
                            <td>${s.is_lated?'Yes':'No'}</td>
                            <td>${s.is_graded?'Yes':'No'}</td>
                            <td>${s.marks || '—'}</td>
                            <td>${s.ai_feedback || '—'}</td>
                            <td><button class="btn-grade-submission" data-submission='${JSON.stringify(s)}'>Grade</button></td>
                        </tr>
                    `);
                });
                if ($.fn.DataTable.isDataTable('#submissionsTable')) $('#submissionsTable').DataTable().clear().destroy();
                $('#submissionsTable').DataTable();
                openModal('submissionsModal');
            } else alert(res.message);
        });
    });

    // --- GRADING MODAL ---
    $(document).on('click','.btn-grade-submission',function(){
        const s = $(this).data('submission');
        $('#studentName').text(s.student_name);
        $('#assignmentTitle').text(s.assignment_title || '');
        $('#submittedAt').text(s.submitted_at);
        $('#fileLink').html(s.file_path?`<a href="${s.file_path}" target="_blank">Download</a>`:'No file.');
        $('#marks').val(s.marks||'');
        $('#ai_feedback').val(s.ai_feedback||'');
        $('#submissionId').val(s.submission_id);
        openModal('gradingModal');
    });

    $('#gradingForm').submit(function(e){
        e.preventDefault();
        $.post('?ajax=grade_submission', $(this).serialize(), function(res){
            if(res.status==='ok'){
                alert('Grade saved successfully!');
                closeModal('gradingModal');
                $('.btn-view-submissions[data-assignment-id=' + res.assignment_id + ']').click();
            } else alert('Error: '+res.message);
        },'json');
    });

 // --- PDF GENERATION HELPER (UPDATED) ---
function generatePDF(tableId, filename) {
    const doc = new jspdf.jsPDF();
    const table = $(tableId).DataTable();

    // Extract table headers
    let headers = [];
    $(tableId + ' thead th').each(function () {
        headers.push($(this).text());
    });

    // Extract rows
    let rows = [];
    table.rows({ search: 'applied' }).every(function () {
        let cleanRow = [];
        let rowData = this.data();

        rowData.forEach(cell => {
            if (typeof cell === "string") {
                let clean = $('<div>').html(cell).text().trim();
                cleanRow.push(clean);
            } else {
                cleanRow.push(cell);
            }
        });

        rows.push(cleanRow);
    });

    doc.autoTable({
        head: [headers],
        body: rows,
        theme: 'grid'
    });

    doc.save(filename);
}

    // --- PDF BUTTONS ---
    $('#generateAssignmentsPDF').click(function(){
        if(!selectedUnitId){
            alert('Please select a unit first.');
            return;
        }
        // Open server-side PDF with unit_id
        window.open('assgenpdf.php?unit_id=' + selectedUnitId, '_blank');

        // Optional: generate client-side PDF as fallback
        // generatePDF('#assignmentsTable', [0,1,2,3,4,5], 'assignments.pdf');
    });

    $('#generateSubmissionsPDF').click(()=> generatePDF('#submissionsTable', [0,1,2,3,4,5,6,7], 'submissions.pdf'));
});
</script>

</body>
</html>
