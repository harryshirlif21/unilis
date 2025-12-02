<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // DOMPDF
use Dompdf\Dompdf;

// Ensure lecturer is logged in
$lecturer_id = $_SESSION['user_id'] ?? 0;
if (!$lecturer_id) {
    header("Location: ../login.php");
    exit;
}

// =========================
// GET UNIT ID
// =========================
$unit_id = intval($_GET['unit'] ?? 0);

// =========================
// HANDLE PDF GENERATION
// =========================
if(isset($_GET['generate'])){
    $generate = $_GET['generate'];

    // Full attendance PDF
    if($generate=='full' && $unit_id>0){
        // Unit info
        $unit_res = $conn->query("SELECT u.name AS unit_name, u.course_id, u.year, c.name AS course_name
            FROM units u
            LEFT JOIN courses c ON u.course_id = c.id
            WHERE u.id = $unit_id");
        $unit = $unit_res->fetch_assoc();
        if(!$unit) die("Unit not found.");

        // Sessions (up to 14)
        $sessions_res = $conn->query("SELECT id, session_code FROM attendance_sessions 
            WHERE unit_id = $unit_id ORDER BY created_at ASC LIMIT 14");
        $sessions = []; while($row = $sessions_res->fetch_assoc()) $sessions[] = $row;
        $total_lessons = count($sessions);

        // Students in course/year
        $students_res = $conn->query("SELECT id, name, reg_no FROM students
            WHERE course_id = {$unit['course_id']} AND year_of_study = {$unit['year']}
            ORDER BY name");
        $students = []; while($row = $students_res->fetch_assoc()) $students[] = $row;

        // Attendance matrix
        $matrix = [];
        foreach($students as $stu){
            $matrix[$stu['id']] = [
                'name'=>$stu['name'],
                'reg_no'=>$stu['reg_no'],
                'attended'=>[],
                'total'=>0
            ];
            foreach($sessions as $s){
                $ar = $conn->query("SELECT attended FROM attendance_records 
                    WHERE student_id={$stu['id']} AND session_id={$s['id']}");
                $att = $ar->fetch_assoc();
                $present = ($att && $att['attended']) ? 1 : -1;
                $matrix[$stu['id']]['attended'][] = $present;
                if($present==1) $matrix[$stu['id']]['total']++;
            }
        }

        // HTML
        $html = '<h2>Attendance Report</h2>';
        $html .= '<p><strong>Unit:</strong> '.htmlspecialchars($unit['unit_name']).'</p>';
        $html .= '<p><strong>Course:</strong> '.htmlspecialchars($unit['course_name']).'</p>';
        $html .= '<p><strong>Year:</strong> '.htmlspecialchars($unit['year']).'</p>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">
        <thead><tr><th>#</th><th>Name</th><th>Reg No</th>';
        for($i=1;$i<=$total_lessons;$i++) $html.="<th>Lesson $i</th>";
        $html.='<th>Total</th></tr></thead><tbody>';

        $idx=1;
        foreach($matrix as $stu){
            $percent = ($total_lessons>0) ? ($stu['total']/$total_lessons)*100 : 0;
            $style = ($percent<70)?'color:red;':'';
            $html.="<tr style='$style'><td>$idx</td><td>{$stu['name']}</td><td>{$stu['reg_no']}</td>";
            foreach($stu['attended'] as $att) $html.="<td>".($att==1?'1':'–')."</td>";
            $html.="<td>{$stu['total']}</td></tr>";
            $idx++;
        }
        $html.='</tbody></table>';

        // Render PDF
        $dompdf = new Dompdf();
        $dompdf->setPaper('A4','portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();
        $dompdf->stream("attendance_report_unit_{$unit_id}.pdf", ["Attachment"=>1]);
        exit;
    }

    // Single session PDF
    if(strpos($generate,'session')!==false){
        $session_id = intval($_GET['session'] ?? 0);
        if(!$session_id) die("Session not specified.");

        $session_res = $conn->query("SELECT s.session_code, s.unit_id, u.name AS unit_name, c.name AS course_name, u.year, u.course_id
            FROM attendance_sessions s
            LEFT JOIN units u ON s.unit_id=u.id
            LEFT JOIN courses c ON u.course_id=c.id
            WHERE s.id=$session_id");
        $session = $session_res->fetch_assoc();
        if(!$session) die("Session not found.");

        $students_res = $conn->query("SELECT id, name, reg_no FROM students
            WHERE course_id={$session['course_id']} AND year_of_study={$session['year']} ORDER BY name");
        $students = []; while($row = $students_res->fetch_assoc()) $students[] = $row;

        $attendance = [];
        foreach($students as $stu){
            $ar = $conn->query("SELECT attended FROM attendance_records WHERE student_id={$stu['id']} AND session_id=$session_id");
            $att = $ar->fetch_assoc();
            $attendance[$stu['id']] = ($att && $att['attended'])?'Present':'Absent';
        }

        $html = "<h2>Session Attendance</h2>";
        $html .= "<p><strong>Unit:</strong>".htmlspecialchars($session['unit_name'])."</p>";
        $html .= "<p><strong>Course:</strong>".htmlspecialchars($session['course_name'])."</p>";
        $html .= "<p><strong>Year:</strong>".htmlspecialchars($session['year'])."</p>";
        $html .= "<p><strong>Session Code:</strong>".htmlspecialchars($session['session_code'])."</p>";
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">
        <thead><tr><th>#</th><th>Name</th><th>Reg No</th><th>Status</th></tr></thead><tbody>';

        $idx=1;
        foreach($students as $stu){
            $html.="<tr><td>$idx</td><td>{$stu['name']}</td><td>{$stu['reg_no']}</td><td>{$attendance[$stu['id']]}</td></tr>";
            $idx++;
        }
        $html.='</tbody></table>';

        $dompdf = new Dompdf();
        $dompdf->setPaper('A4','portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();
        $dompdf->stream("session_{$session_id}_attendance.pdf", ["Attachment"=>1]);
        exit;
    }
}

// =========================
// MAIN PAGE LOGIC
// =========================

// Fetch lecturer units
$units_query = $conn->query("SELECT u.id, u.name, c.name AS course_name
    FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE lu.lecturer_id = $lecturer_id
    ORDER BY u.name");

// Determine current unit
if($unit_id<=0){
    $first_unit = $units_query->fetch_assoc();
    if($first_unit) $unit_id = $first_unit['id'];
}

// Get selected unit details
$unit_res = $conn->query("SELECT u.id, u.name, c.name AS course_name
    FROM units u
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE u.id=$unit_id");
$unit_data = $unit_res->fetch_assoc();
$unit_name = $unit_data['name'] ?? "—";

// Fetch current/live session
$live_session_res = $conn->query("SELECT s.id, s.session_code, s.deadline
    FROM attendance_sessions s
    WHERE s.unit_id=$unit_id AND s.deadline>=NOW() ORDER BY s.created_at DESC LIMIT 1");
$current_session = $live_session_res->fetch_assoc();
$is_live = !empty($current_session);

// Fetch previous sessions
$prev_sessions_list = $conn->query("SELECT id, session_code, created_at FROM attendance_sessions
    WHERE unit_id=$unit_id ORDER BY created_at DESC");
$previous_sessions = [];
while($row=$prev_sessions_list->fetch_assoc()) $previous_sessions[] = $row;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lecturer Attendance</title>
<style>
.tile { background:#f3f4f6; padding:15px; border-radius:15px; margin:10px; display:inline-block; width:220px; vertical-align:top; }
.btn { padding:10px 15px; border-radius:10px; color:white; text-decoration:none; display:inline-block; margin-top:10px; cursor:pointer; }
.btn-view { background:#f59e0b; }
.btn-end { background:#dc2626; }
.modal { display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
.modal-content { background:#fff; margin:10% auto; padding:20px; border-radius:10px; width:80%; max-width:700px; }
.close { float:right; font-size:28px; font-weight:bold; cursor:pointer; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:8px; text-align:left; border-bottom:1px solid #ddd; }
th { background:#f59e0b20; }
</style>
</head>
<body>

<h1>Attendance Report</h1>

<h3>Select Unit</h3>
<select onchange="location.href='?unit='+this.value" style="padding:10px;font-size:16px;">
<?php
$units_query->data_seek(0);
while($unit = $units_query->fetch_assoc()):
?>
<option value="<?= $unit['id'] ?>" <?= ($unit['id']==$unit_id)?'selected':'' ?>>
<?= htmlspecialchars($unit['name']) ?> (<?= htmlspecialchars($unit['course_name']) ?>)
</option>
<?php endwhile; ?>
</select>

<!-- Generate full PDF -->
<a href="?unit=<?= $unit_id ?>&generate=full" class="btn btn-view" style="margin:10px 0;">Generate Full PDF</a>

<!-- Current Session -->
<?php if($is_live): ?>
<div style="background:#f59e0b20; padding:25px; border-radius:15px; margin-top:25px; margin-bottom:25px;">
<p><strong>Unit:</strong> <?= htmlspecialchars($unit_name) ?></p>
<p><strong>Session Code:</strong> <?= $current_session['session_code'] ?></p>
<button class="btn btn-view" onclick="openModal(<?= $current_session['id'] ?>)">View Students</button>
</div>
<?php endif; ?>

<!-- Previous Sessions -->
<h2>Previous Rollcalls</h2>
<div>
<?php if(!empty($previous_sessions)):
foreach($previous_sessions as $idx => $s): ?>
<div class="tile">
<p><strong>Lesson:</strong> <?= $idx+1 ?></p>
<p><strong>Code:</strong> <?= $s['session_code'] ?></p>
<p><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($s['created_at'])) ?></p>
<button class="btn btn-view" onclick="openModal(<?= $s['id'] ?>)">View Details</button>
</div>
<?php endforeach; else: ?>
<p>No previous sessions.</p>
<?php endif; ?>
</div>

<!-- Modal -->
<div id="attendanceModal" class="modal">
<div class="modal-content">
<span class="close" onclick="closeModal()">&times;</span>
<h2>Attendance Details</h2>
<table>
<thead><tr><th>#</th><th>Name</th><th>Reg No</th><th>Status</th></tr></thead>
<tbody id="modalBody"></tbody>
</table>
<button class="btn btn-view" id="generateSessionPdfBtn">Generate PDF</button>
</div>
</div>

<script>
function openModal(sessionId){
    const modal = document.getElementById('attendanceModal');
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML='<tr><td colspan="4">Loading...</td></tr>';
    modal.style.display='flex';
    document.getElementById('generateSessionPdfBtn').onclick=function(){
        window.open('?generate=session&session='+sessionId,'_blank');
    };

    fetch('?ajax_session='+sessionId)
    .then(res=>res.json())
    .then(data=>{
        if(data.length===0){ modalBody.innerHTML='<tr><td colspan="4">No records found</td></tr>'; return; }
        let html='';
        data.forEach((s,idx)=>{ html+=`<tr><td>${idx+1}</td><td>${s.name}</td><td>${s.reg_no}</td><td>${s.status}</td></tr>`; });
        modalBody.innerHTML=html;
    }).catch(err=>{ modalBody.innerHTML='<tr><td colspan="4">Error loading data</td></tr>'; console.error(err); });
}
function closeModal(){ document.getElementById('attendanceModal').style.display='none'; }
window.onclick=function(event){ if(event.target==document.getElementById('attendanceModal')) closeModal(); }
</script>

</body>
</html>
