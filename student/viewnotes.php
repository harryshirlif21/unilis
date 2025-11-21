<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch student info
$stmt = $conn->prepare("SELECT course_id, year_of_study FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$course_id = $student['course_id'];
$year_of_study = $student['year_of_study'];

// Fetch units for this student (course + year)
$units_stmt = $conn->prepare("
    SELECT id, name, code
    FROM units
    WHERE course_id = ? AND year = ?
    ORDER BY name
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
body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 2rem; }
h1 { text-align:center; margin-bottom:2rem; }

/* Unit tiles */
.units-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
    justify-items: center;
}
.unit-tile {
    background: #fff;
    padding: 1rem;
    border-radius: 1rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    text-align: center;
    transition: all 0.2s;
}
.unit-tile:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.unit-tile button {
    margin-top: 8px;
    padding: 6px 12px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.unit-tile button:hover { background:#2563eb; }

/* Modal */
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000; }
.modal-content { background:#fff; padding:1.5rem; width:90%; max-width:900px; max-height:90%; overflow-y:auto; border-radius:1rem; position:relative; }
.modal-close { position:absolute; top:10px; right:10px; font-size:1.5rem; font-weight:bold; cursor:pointer; color:#555; }
.modal-close:hover { color:#000; }

/* Topic/Subtopic */
.topic-card { background:#fefefe; border-radius:8px; margin-bottom:1.5rem; padding:1rem; box-shadow:0 1px 4px rgba(0,0,0,0.1); }
.topic-card h3 { color:#dc2626; margin-bottom:0.5rem; }
.subtopic-title { color:#16a34a; font-weight:bold; margin-top:1rem; }
.content-area img { max-width:200px; max-height:180px; object-fit:cover; border-radius:12px; margin:5px; cursor:pointer; transition:transform 0.2s; }
.content-area img:hover { transform:scale(1.05); }
.choices-list { background:#e2e8f0; padding:10px; border-radius:8px; margin:5px 0; }
.choice-correct { color:#16a34a; font-weight:bold; }
.files-section { margin-top:10px; }
.file-item { display:inline-block; background:#f59e0b; color:#fff; padding:4px 12px; border-radius:20px; margin:3px; text-decoration:none; font-size:0.9em; }
.file-item:hover { background:#d97706; }

/* Progress badges */
.progress-badge { padding:4px 12px; border-radius:20px; font-size:0.8em; font-weight:600; text-transform:uppercase; }
.progress-not_started { background:#f1f5f9; color:#6b7280; }
.progress-in_progress { background:#fef3c7; color:#f59e0b; }
.progress-completed { background:#d1fae5; color:#10b981; }

/* Buttons */
.btn { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
.btn-primary { background:#3b82f6; color:white; }
.btn-primary:hover { background:#2563eb; }
</style>
</head>
<body>

<h1>Student Notes</h1>

<div class="units-grid">
<?php while ($unit = $units_result->fetch_assoc()): ?>
    <div class="unit-tile">
        <h4><?= htmlspecialchars($unit['name']) ?></h4>
        <p><?= htmlspecialchars($unit['code']) ?></p>
        <button onclick="openNotesModal(<?= $unit['id'] ?>)">View Notes</button>
    </div>
<?php endwhile; ?>
</div>

<!-- Modal -->
<div id="notesModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div id="modalNotesContent"></div>
    </div>
</div>

<script>
function openNotesModal(unitId) {
    const contentDiv = document.getElementById('modalNotesContent');
    contentDiv.innerHTML = "<p>Loading notes...</p>";
    document.getElementById('notesModal').style.display = 'flex';

    const formData = new FormData();
    formData.append('unit_id', unitId);

    fetch('', { method:'POST', body: formData })
        .then(res => res.text())
        .then(html => { contentDiv.innerHTML = html; })
        .catch(err => { contentDiv.innerHTML = "<p>Error loading notes</p>"; console.error(err); });
}

function closeModal() { document.getElementById('notesModal').style.display = 'none'; }

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('notesModal');
    if(event.target == modal) modal.style.display = 'none';
}
</script>

<?php
// Load notes for modal
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['unit_id'])):
    $unit_id = intval($_POST['unit_id']);
    $stmt = $conn->prepare("
        SELECT cn.id AS classnote_id, cn.title, cn.subtopics_json, cn.uploaded_at, scp.status
        FROM classnotes cn
        LEFT JOIN student_classnotes_progress scp 
            ON scp.classnote_id=cn.id AND scp.student_id=?
        WHERE cn.unit_id=?
        ORDER BY cn.uploaded_at DESC
    ");
    $stmt->bind_param("ii",$student_id,$unit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    ?>
    <?php if($res->num_rows>0): ?>
        <?php while($note=$res->fetch_assoc()):
            $subtopics = json_decode($note['subtopics_json'],true) ?? [];
            $progress_status = $note['status'] ?? 'not_started';
        ?>
        <div class="topic-card" data-classnote-id="<?= $note['classnote_id'] ?>">
            <h3><?= htmlspecialchars($note['title']) ?></h3>
            <span class="progress-badge progress-<?= $progress_status ?>"><?= ucfirst(str_replace('_',' ',$progress_status)) ?></span>
            <p>Uploaded: <?= date("d M Y, h:i A", strtotime($note['uploaded_at'])) ?></p>

            <?php foreach($subtopics as $sub): ?>
                <h4 class="subtopic-title"><?= htmlspecialchars($sub['title'] ?? 'Untitled Subtopic') ?></h4>
                <?php if(!empty($sub['content'])): ?>
                    <div class="content-area"><?= $sub['content'] ?></div>
                <?php endif; ?>

                <?php if(!empty($sub['choices'])): ?>
                    <div class="choices-list">
                        <strong>Questions:</strong>
                        <?php foreach($sub['choices'] as $c): ?>
                            <div class="choice-item <?= ($sub['correctChoice']==$c['id'])?'choice-correct':'' ?>">
                                <?= htmlspecialchars($c['text'] ?? '') ?> <?= ($sub['correctChoice']==$c['id'])?'✓':'' ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($sub['files'])): ?>
                    <div class="files-section">
                        <strong>Files:</strong>
                        <?php foreach($sub['files'] as $f): ?>
                            <a href="../uploads/files/<?= htmlspecialchars($f['name']) ?>" target="_blank" class="file-item" download="<?= htmlspecialchars($f['original_name'] ?? $f['name']) ?>">📎 <?= htmlspecialchars($f['label'] ?? $f['original_name'] ?? 'File') ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No notes uploaded for this unit.</p>
    <?php endif;
    $stmt->close();
    exit;
endif;
$conn->close();
?>
</body>
</html>
