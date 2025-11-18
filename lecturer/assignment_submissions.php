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

// --- AJAX Endpoints ---
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    // GET ASSIGNMENTS FOR A UNIT
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

    // GET ASSIGNMENT OVERVIEW
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
            SELECT s.id AS submission_id, s.student_id, st.name AS student_name, st.reg_no, s.file_path, s.created_at, s.marks, s.is_graded, s.comment
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
            $isLate = $deadline && $r['created_at'] ? strtotime($r['created_at']) > strtotime($deadline) : false;
            $submitted[] = [
                'submission_id' => (int)$r['submission_id'],
                'student_id' => (int)$r['student_id'],
                'student_name' => $r['student_name'],
                'reg_no' => $r['reg_no'],
                'file_path' => $r['file_path'],
                'submitted_at' => $r['created_at'],
                'marks' => $r['marks'],
                'is_graded' => (bool)$r['is_graded'],
                'is_late' => $isLate,
                'comment' => $r['comment']
            ];
            $submittedIds[] = (int)$r['student_id'];
        }

        $notSubmitted = [];
        foreach ($allStudents as $sid => $s) {
            if (!in_array($sid, $submittedIds, true)) {
                $notSubmitted[] = ['student_id' => $sid, 'student_name' => $s['name'], 'reg_no' => $s['reg_no']];
            }
        }

        $lateCount = count(array_filter($submitted, fn($s) => $s['is_late']));

        echo json_encode([
            'status' => 'ok',
            'assignment_id' => $assignment_id,
            'unit_id' => $unit_id,
            'course_id' => $course_id,
            'total_expected' => count($allStudents),
            'submitted_count' => count($submitted),
            'not_submitted_count' => count($notSubmitted),
            'late_count' => $lateCount,
            'deadline' => $deadline,
            'submitted' => $submitted,
            'not_submitted' => $notSubmitted
        ]);
        exit;
    }

    // GET SUBMISSIONS
    if ($action === 'get_submissions' && isset($_GET['assignment_id'])) {
        $assignment_id = intval($_GET['assignment_id']);
        $check = $conn->prepare("
            SELECT u.id FROM assignments a
            JOIN units u ON a.unit_id = u.id
            JOIN lecturer_units lu ON u.id = lu.unit_id
            WHERE a.id = ? AND lu.lecturer_id = ?
        ");
        $check->bind_param('ii', $assignment_id, $lecturer_id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized or assignment not found']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT s.id AS submission_id, st.name AS student_name, st.reg_no, s.file_path, s.marks, s.is_graded, s.comment
            FROM submissions s
            JOIN students st ON s.student_id = st.id
            WHERE s.assignment_id = ?
            ORDER BY st.name ASC
        ");
        $stmt->bind_param('i', $assignment_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $subs = [];
        while ($r = $res->fetch_assoc()) $subs[] = $r;

        echo json_encode(['status' => 'ok', 'items' => $subs]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

// --- Fetch units taught by lecturer ---
$unitsQuery = $conn->prepare("
    SELECT u.id AS unit_id, u.name AS unit_name, u.code AS unit_code, c.id AS course_id, c.name AS course_name, u.year AS year,
    (SELECT COUNT(*) FROM assignments a WHERE a.unit_id = u.id) AS assignments_count,
    (SELECT COUNT(*) FROM submissions s JOIN assignments aa ON s.assignment_id = aa.id WHERE aa.unit_id = u.id) AS submissions_count,
    (SELECT MAX(created_at) FROM assignments a WHERE a.unit_id = u.id) AS last_sent,
    (SELECT MIN(deadline) FROM assignments a WHERE a.unit_id = u.id AND a.deadline >= NOW()) AS nearest_deadline
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Unit Dashboard — Assignment Submissions</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Minimal CSS for dashboard layout */
:root{--bg:#f4f7fb;--card:#fff;--muted:#6b7280;--accent:#2563eb;--success:#16a34a;--danger:#ef4444}
body{font-family:Inter,sans-serif;margin:0;padding:24px;background:var(--bg);color:#0f172a}
.container{max-width:1200px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
h1{font-size:20px;margin:0}
.top-actions{display:flex;gap:10px;align-items:center}
.search{display:flex;align-items:center;background:#fff;padding:6px 10px;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,0.05)}
.search input{border:0;outline:none;width:220px}
.tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;margin-top:12px}
.tile{background:var(--card);border-radius:12px;padding:14px;box-shadow:0 6px 18px rgba(0,0,0,0.06);transition:.2s;cursor:pointer;position:relative}
.tile:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,0.08)}
.tile .title{font-weight:600;margin-bottom:6px}
.tile .meta{font-size:13px;color:var(--muted);margin-bottom:10px}
.badges{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.badge{background:#f1f5f9;padding:6px 8px;border-radius:8px;font-size:13px;color:#0f172a}
.progress{height:8px;background:#eef2ff;border-radius:8px;overflow:hidden}
.progress > i{display:block;height:100%;background:linear-gradient(90deg,var(--accent),#7c3aed);width:60%}
.tile .footer{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
.small-muted{font-size:12px;color:var(--muted)}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:60;opacity:0;pointer-events:none;transition:opacity .2s}
.modal{width:860px;max-width:95%;background:var(--card);border-radius:12px;padding:16px;box-shadow:0 18px 48px rgba(0,0,0,0.3)}
.modal.show{opacity:1;pointer-events:auto}
.assign-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.assign-card{padding:12px;border-radius:10px;background:#f8fafc;border:1px solid #eef2ff;cursor:pointer}
.assign-title{font-weight:600;margin-bottom:6px}
.assign-meta{font-size:13px;color:var(--muted)}
.assign-card .small{font-size:12px}
.close-x{position:absolute;right:14px;top:12px;font-size:18px;color:var(--muted);cursor:pointer}
.overview-grid{display:grid;grid-template-columns:1fr 320px;gap:16px}
.submitted-list,.not-submitted-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
.stu-card{padding:10px;border-radius:8px;background:#fff;border:1px solid #eef2ff}
.stu-card.late{border-color:var(--danger);background:#fff7f7}
.stu-card.graded{border-color:var(--success);background:#f7fffb}
.submissions-wrap{margin-top:22px;background:var(--card);padding:16px;border-radius:12px;box-shadow:0 8px 22px rgba(0,0,0,0.04);display:none}
.table{width:100%;border-collapse:collapse}
.table th{background:#f8fafc;padding:10px;text-align:left;font-size:13px;color:var(--muted);position:sticky;top:0}
.table td{padding:10px;border-top:1px solid #f1f5f9}
.status-graded{color:var(--success);font-weight:600}
.status-pending{color:var(--danger);font-weight:600}
.btn{background:var(--accent);color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer}
.btn.secondary{background:#e6eefc;color:var(--accent)}
@media (max-width:920px){.overview-grid{grid-template-columns:1fr}}
@media (max-width:720px){.top-actions .search input{width:120px}.tiles{grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}.modal{width:95%}}
</style>
</head>
<body>
<div class="container">
<header>
    <h1>📄 Assignment Submissions — Unit Dashboard</h1>
    <div class="top-actions">
        <div class="search"><i class="fa fa-search" style="margin-right:8px;color:var(--muted)"></i>
            <input id="unitSearch" placeholder="Search units" aria-label="Search units">
        </div>
        <button class="btn secondary" id="refreshBtn" title="Refresh">🔄 Refresh</button>
        <a href="dashboard.php" class="btn">🏠 Home</a>
    </div>
</header>

<section class="tiles" id="tilesGrid">
<?php while ($unit = $units->fetch_assoc()):
    $uid = (int)$unit['unit_id'];
    $assignCount = (int)$unit['assignments_count'];
    $subCount = (int)$unit['submissions_count'];
    $lastSent = $unit['last_sent'] ? date('d M Y', strtotime($unit['last_sent'])) : '—';
    $deadline = $unit['nearest_deadline'] ? date('d M Y', strtotime($unit['nearest_deadline'])) : '—';
?>
    <div class="tile" data-unit-id="<?= $uid ?>" tabindex="0">
        <div class="title"><?= htmlspecialchars($unit['unit_name']) ?></div>
        <div class="meta"><?= htmlspecialchars($unit['course_name']) ?> • Year <?= htmlspecialchars($unit['year']) ?> • <?= htmlspecialchars($unit['unit_code'] ?? '') ?></div>
        <div class="badges">
            <div class="badge">📘 Assignments: <?= $assignCount ?></div>
            <div class="badge">📥 Submissions: <?= $subCount ?></div>
            <div class="badge">📅 Last sent: <?= $lastSent ?></div>
            <div class="badge">⏳ Nearest deadline: <?= $deadline ?></div>
        </div>
        <div class="progress" aria-hidden="true"><i style="width:<?= $assignCount ? min(100, (int)($subCount / max(1,$assignCount) * 100)) : 0 ?>%"></i></div>
        <div class="footer">
            <div class="small-muted">Unit ID: <?= $uid ?></div>
            <div class="small-muted">Click or press Enter to open</div>
        </div>
    </div>
<?php endwhile; ?>
</section>

<!-- Modals -->
<div id="modalOverlay" class="modal-overlay"><div class="modal"><div class="close-x" id="closeModal">✖</div><h3 id="modalUnitTitle">Unit assignments</h3><div id="assignGrid" class="assign-grid"></div></div></div>
<div id="overviewOverlay" class="modal-overlay"><div class="modal"><div class="close-x" id="closeOverview">✖</div><h3 id="overviewTitle">Submission Overview</h3><div id="overviewContent"></div><div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px"><button id="downloadZip" class="btn secondary">📥 Download All</button><button id="openTableBtn" class="btn">Open Submissions Table</button></div></div></div>

<section id="submissionsArea" class="submissions-wrap">
    <div class="sub-header">
        <div>
            <strong id="subTitle">Submissions</strong>
            <div class="small-muted" id="subMeta">Select an assignment to view submissions</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <button id="downloadPdf" class="btn secondary">📥 Generate PDF</button>
            <button id="saveMarks" class="btn">💾 Save Marks</button>
        </div>
    </div>
    <div id="tableWrap"></div>
</section>

<script>
// JavaScript (same logic as your previous, cleaned up for async/await & safety)
const $ = s => document.querySelector(s), $$ = s => Array.from(document.querySelectorAll(s));
const tilesGrid = $('#tilesGrid'), modalOverlay = $('#modalOverlay'), assignGrid = $('#assignGrid'), modalUnitTitle = $('#modalUnitTitle');
const closeModal = $('#closeModal'), overviewOverlay = $('#overviewOverlay'), closeOverview = $('#closeOverview');
const overviewTitle = $('#overviewTitle'), overviewContent = $('#overviewContent');
const openTableBtn = $('#openTableBtn'), downloadZip = $('#downloadZip'), submissionsArea = $('#submissionsArea');
const tableWrap = $('#tableWrap'), unitSearch = $('#unitSearch'), refreshBtn = $('#refreshBtn');

// Event listeners
tilesGrid.addEventListener('click', e=>{const tile=e.target.closest('.tile');tile&&openAssignmentsModal(tile.getAttribute('data-unit-id'),tile);});
tilesGrid.addEventListener('keydown', e=>{if(e.key==='Enter'){const tile=e.target.closest('.tile');tile&&openAssignmentsModal(tile.getAttribute('data-unit-id'),tile);}});

closeModal.addEventListener('click',()=>{modal
Overlay.classList.remove('show'); setTimeout(()=>modalOverlay.style.display='none',200); assignGrid.innerHTML='';});
modalOverlay.addEventListener('click', e=>{if(e.target===modalOverlay){closeModal.click();}});

closeOverview.addEventListener('click',()=>{overviewOverlay.classList.remove('show'); setTimeout(()=>overviewOverlay.style.display='none',200); overviewContent.innerHTML='';});
overviewOverlay.addEventListener('click', e=>{if(e.target===overviewOverlay){closeOverview.click();}});

// Search filter
unitSearch.addEventListener('input', e=>{
    const q=e.target.value.toLowerCase().trim();
    $$('.tile').forEach(t=>{
        const txt=(t.querySelector('.title').textContent+' '+t.querySelector('.meta').textContent).toLowerCase();
        t.style.display=txt.includes(q)?'':'none';
    });
});

// Refresh
refreshBtn.addEventListener('click', ()=>location.reload());

// Helpers
function formatDate(d){if(!d) return '—'; const x=new Date(d); if(isNaN(x)) return d; return x.toLocaleDateString();}
function escapeHtml(s){if(s==null) return ''; return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

// Assignments modal
async function openAssignmentsModal(unitId,tileEl){
    modalUnitTitle.textContent = tileEl.querySelector('.title').textContent + ' — Assignments';
    assignGrid.innerHTML='<div style="grid-column:1/-1;padding:12px;color:var(--muted)">Loading…</div>';
    modalOverlay.style.display='flex';
    setTimeout(()=>modalOverlay.classList.add('show'),20);
    try{
        const res=await fetch('?ajax=get_assignments&unit_id='+encodeURIComponent(unitId));
        const data=await res.json();
        if(data.status==='ok') renderAssignments(data.items);
        else assignGrid.innerHTML='<div style="grid-column:1/-1;color:var(--danger)">Error loading assignments</div>';
    }catch(err){assignGrid.innerHTML='<div style="grid-column:1/-1;color:var(--danger)">Network error</div>';}
}

function renderAssignments(items){
    if(!items.length){assignGrid.innerHTML='<div style="grid-column:1/-1;padding:12px;color:var(--muted)">No assignments for this unit</div>'; return;}
    assignGrid.innerHTML='';
    items.forEach(a=>{
        const card=document.createElement('div'); card.className='assign-card'; card.tabIndex=0;
        card.innerHTML=`<div class="assign-title">${escapeHtml(a.title)}</div>
        <div class="assign-meta">Submissions: ${a.submissions_count} • ${formatDate(a.deadline)} • ${formatDate(a.created_at)}</div>
        <div style="margin-top:8px" class="small">Click to view submissions</div>`;
        card.addEventListener('click', ()=>openOverviewModal(a.id,a.title));
        card.addEventListener('keydown', e=>{if(e.key==='Enter') openOverviewModal(a.id,a.title);});
        assignGrid.appendChild(card);
    });
}

// Overview modal
async function openOverviewModal(assignmentId,title){
    overviewTitle.textContent=title+' — Overview';
    overviewContent.innerHTML='<div style="padding:12px;color:var(--muted)">Loading overview…</div>';
    overviewOverlay.style.display='flex';
    setTimeout(()=>overviewOverlay.classList.add('show'),20);
    closeModal.click();
    try{
        const res=await fetch('?ajax=get_assignment_overview&assignment_id='+encodeURIComponent(assignmentId));
        const data=await res.json();
        if(data.status==='ok') renderOverview(data); else overviewContent.innerHTML='<div style="color:var(--danger)">Error loading overview</div>';
    }catch(err){overviewContent.innerHTML='<div style="color:var(--danger)">Network error</div>';}
    openTableBtn.onclick=()=>{loadSubmissions(assignmentId,title); closeOverview.click();};
    downloadZip.onclick=()=>{window.location.href='../actions.php?action=download_all&assignment_id='+encodeURIComponent(assignmentId);};
}

function renderOverview(data){
    const stats=`<div style="display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:12px">
    <div><div style="font-weight:600">Total expected: ${data.total_expected}</div>
    <div class="small-muted">Submitted: ${data.submitted_count} • Not submitted: ${data.not_submitted_count} • Late: ${data.late_count}</div></div>
    <div style="width:220px"><div class="small-muted">Submission rate</div>
    <div class="progress" style="height:12px;border-radius:12px;margin-top:6px"><i style="width:${data.total_expected?Math.round((data.submitted_count/data.total_expected)*100):0}%"></i></div></div></div>`;

    let submittedHtml='<div class="submitted-list">';
    data.submitted.forEach(s=>{
        submittedHtml+=`<div class="stu-card ${s.is_late?'late':''} ${s.is_graded?'graded':''}">
            <div style="font-weight:600">${escapeHtml(s.student_name)}</div>
            <div class="small-muted">${escapeHtml(s.reg_no)}</div>
            <div class="small-muted">${s.submitted_at?formatDate(s.submitted_at):'—'}</div>
            <div style="margin-top:8px"><a href="../assets/uploads/submissions/${encodeURIComponent(s.file_path)}" target="_blank">View</a></div>
        </div>`;
    });
    submittedHtml+='</div>';

    let notHtml='<div class="not-submitted-list">';
    data.not_submitted.forEach(ns=>{
        notHtml+=`<div class="stu-card">
            <div style="font-weight:600">${escapeHtml(ns.student_name)}</div>
            <div class="small-muted">${escapeHtml(ns.reg_no)}</div>
            <div style="margin-top:8px"><span style="color:var(--danger);font-weight:600">Not submitted</span></div>
        </div>`;
    });
    notHtml+='</div>';

    overviewContent.innerHTML=`${stats}
    <div class="overview-grid">
        <div><h4>Submitted Students</h4>${submittedHtml}<div style="margin-top:14px"><h4>Not Submitted</h4>${notHtml}</div></div>
        <div><h4>Assignment Details</h4>
            <div class="stu-card" style="background:#fff7f3;border:1px solid #fff0ea">
                <div><strong>Deadline:</strong> ${data.deadline?formatDate(data.deadline):'—'}</div>
                <div style="margin-top:6px"><strong>Unit ID:</strong> ${data.unit_id}</div>
                <div style="margin-top:6px"><strong>Course ID:</strong> ${data.course_id}</div>
            </div>
        </div>
    </div>`;
}

// Submissions table
async function loadSubmissions(assignmentId,title){
    submissionsArea.style.display='block';
    $('#subTitle').textContent=title+' — Submissions';
    $('#subMeta').textContent='Loading submissions…';
    tableWrap.innerHTML='<div style="padding:18px;color:var(--muted)">Loading submissions…</div>';
    try{
        const res=await fetch('?ajax=get_submissions&assignment_id='+encodeURIComponent(assignmentId));
        const data=await res.json();
        if(data.status==='ok') renderSubmissionsTable(data.items,assignmentId); else tableWrap.innerHTML='<div style="color:var(--danger)">Error loading submissions</div>';
    }catch(err){tableWrap.innerHTML='<div style="color:var(--danger)">Network error</div>';}
}

function renderSubmissionsTable(items,assignmentId){
    $('#subMeta').textContent=`${items.length} submissions received`;
    if(!items.length){tableWrap.innerHTML='<div style="padding:12px;color:var(--muted)">No submissions yet.</div>'; return;}

    const rows=items.map((s,idx)=>`
        <tr class="${idx%2?'row-odd':''}">
            <td>${escapeHtml(s.student_name)}</td>
            <td>${escapeHtml(s.reg_no)}</td>
            <td><a href="../assets/uploads/submissions/${encodeURIComponent(s.file_path)}" target="_blank">View</a></td>
            <td><input type="number" data-id="${s.submission_id}" value="${s.marks ?? ''}" min="0" max="100" style="width:80px;padding:6px;border-radius:8px;border:1px solid #e6eefc"></td>
            <td>${s.is_graded?'<span class="status-graded">Graded</span>':'<span class="status-pending">Pending</span>'}</td>
            <td><textarea data-id="${s.submission_id}" style="width:100%;height:52px;border-radius:8px;border:1px solid #eef2ff">${escapeHtml(s.comment ?? '')}</textarea></td>
        </tr>`).join('');

    tableWrap.innerHTML=`<form id="marksForm">
        <table class="table" role="table" aria-label="Submissions table">
            <thead>
                <tr><th>Student Name</th><th>Reg No</th><th>Submission</th><th>Marks</th><th>Graded</th><th>Comment</th></tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
        <input type="hidden" name="assignment_id" value="${assignmentId}">
    </form>`;

    $('#saveMarks').onclick=async ()=>{
        const form=$('#marksForm');
        const payload={action:'save_marks', assignment_id:assignmentId, marks:{}, comments:{}};
        form.querySelectorAll('input[type="number"][data-id]').forEach(i=>payload.marks[i.dataset.id]=i.value);
        form.querySelectorAll('textarea[data-id]').forEach(i=>payload.comments[i.dataset.id]=i.value);
        try{
            const res=await fetch('../actions.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
            const j=await res.json();
            alert(j.status==='ok'?'Marks saved':'Error saving marks');
        }catch(err){alert('Network error saving marks');}
    };
}
</script>
</body>
</html>
