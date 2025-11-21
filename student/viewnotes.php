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

// Function to fix image paths in content
function fixImagePaths($content) {
    // Fix relative paths - convert ../uploads to absolute path from web root
    $content = str_replace('src="../uploads/', 'src="/uploads/', $content);
    $content = str_replace("src='../uploads/", "src='/uploads/", $content);
    
    // Also fix double-relative paths if any
    $content = str_replace('src="../../uploads/', 'src="/uploads/', $content);
    $content = str_replace("src='../../uploads/", "src='/uploads/", $content);
    
    return $content;
}
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
.content-area { 
    line-height: 1.6;
    margin: 1rem 0;
}
.content-area img { 
    max-width: 100%; 
    height: auto; 
    border-radius: 8px; 
    margin: 10px 0; 
    cursor: pointer; 
    transition: transform 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.content-area img:hover { 
    transform: scale(1.02); 
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}
.choices-list { background:#e2e8f0; padding:15px; border-radius:8px; margin:10px 0; }
.choice-item { padding: 8px 0; }
.choice-correct { color:#16a34a; font-weight:bold; }
.files-section { margin-top:15px; padding-top:15px; border-top: 1px solid #e2e8f0; }
.file-item { display:inline-block; background:#f59e0b; color:#fff; padding:6px 12px; border-radius:20px; margin:5px; text-decoration:none; font-size:0.9em; }
.file-item:hover { background:#d97706; }

/* Progress badges */
.progress-badge { padding:4px 12px; border-radius:20px; font-size:0.8em; font-weight:600; text-transform:uppercase; display: inline-block; margin-left: 10px; }
.progress-not_started { background:#f1f5f9; color:#6b7280; }
.progress-in_progress { background:#fef3c7; color:#f59e0b; }
.progress-completed { background:#d1fae5; color:#10b981; }

/* Buttons */
.btn { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-weight:600; margin: 5px; }
.btn-primary { background:#3b82f6; color:white; }
.btn-primary:hover { background:#2563eb; }

/* Image Modal */
.image-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:2000; justify-content:center; align-items:center; }
.image-modal-content { max-width:90%; max-height:90%; object-fit:contain; border-radius:8px; }
.image-modal-close { position:absolute; top:20px; right:35px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer; z-index: 2001; }
.image-modal-close:hover { color:#ccc; }

/* Content formatting */
.content-area p { margin-bottom: 1rem; }
.content-area strong { font-weight: bold; }
.content-area em { font-style: italic; }
.content-area ul, .content-area ol { margin: 1rem 0; padding-left: 2rem; }
.content-area li { margin-bottom: 0.5rem; }
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

<!-- Notes Modal -->
<div id="notesModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div id="modalNotesContent"></div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal">
    <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
    <img class="image-modal-content" id="expandedImage">
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
        .then(html => { 
            contentDiv.innerHTML = html; 
            initializeImageModals();
        })
        .catch(err => { 
            contentDiv.innerHTML = "<p>Error loading notes</p>"; 
            console.error(err); 
        });
}

function closeModal() { 
    document.getElementById('notesModal').style.display = 'none'; 
}

function initializeImageModals() {
    document.querySelectorAll('.content-area img').forEach(img => {
        img.onclick = function() {
            openImageModal(this.src);
        };
    });
}

function openImageModal(imgSrc) {
    const expandedImg = document.getElementById('expandedImage');
    expandedImg.src = imgSrc;
    document.getElementById('imageModal').style.display = 'flex';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

window.onclick = function(event) {
    const notesModal = document.getElementById('notesModal');
    const imageModal = document.getElementById('imageModal');
    
    if(event.target == notesModal) notesModal.style.display = 'none';
    if(event.target == imageModal) imageModal.style.display = 'none';
}

document.addEventListener('keydown', function(event) {
    if(event.key === 'Escape') {
        closeModal();
        closeImageModal();
    }
});

function markAsComplete(classnoteId) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_complete=1&classnote_id=' + classnoteId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('[data-classnote-id="' + classnoteId + '"] .progress-badge');
            badge.textContent = 'completed';
            badge.className = 'progress-badge progress-completed';
            alert('Note marked as completed!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating progress');
    });
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
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0;"><?= htmlspecialchars($note['title']) ?></h3>
                <span class="progress-badge progress-<?= $progress_status ?>">
                    <?= ucfirst(str_replace('_',' ',$progress_status)) ?>
                </span>
            </div>
            <p><small>Uploaded: <?= date("d M Y, h:i A", strtotime($note['uploaded_at'])) ?></small></p>

            <?php foreach($subtopics as $sub): ?>
                <?php if(is_array($sub)): ?>
                    <div class="subtopic">
                        <h4 class="subtopic-title"><?= htmlspecialchars($sub['title'] ?? 'Untitled Subtopic') ?></h4>
                        
                        <?php if(!empty($sub['content'])): ?>
                            <div class="content-area">
                                <?= fixImagePaths($sub['content']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($sub['choices'])): ?>
                            <div class="choices-list">
                                <strong>Questions:</strong>
                                <?php foreach($sub['choices'] as $c): ?>
                                    <div class="choice-item <?= ($sub['correctChoice']==$c['id'])?'choice-correct':'' ?>">
                                        <?= htmlspecialchars($c['text'] ?? '') ?> 
                                        <?php if ($sub['correctChoice']==$c['id']): ?>
                                            <span style="color: #16a34a; font-weight: bold;">✓ Correct Answer</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($sub['files'])): ?>
                            <div class="files-section">
                                <strong>Attached Files:</strong><br>
                                <?php foreach($sub['files'] as $f): ?>
                                    <a href="/uploads/files/<?= htmlspecialchars($f['name'] ?? '') ?>" 
                                       target="_blank" 
                                       class="file-item" 
                                       download="<?= htmlspecialchars($f['original_name'] ?? $f['name'] ?? 'file') ?>">
                                        📎 <?= htmlspecialchars($f['label'] ?? $f['original_name'] ?? 'File') ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <div style="margin-top: 15px;">
                <button class="btn btn-primary" onclick="markAsComplete(<?= $note['classnote_id'] ?>)">
                    Mark as Completed
                </button>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No notes uploaded for this unit.</p>
    <?php endif;
    $stmt->close();
    exit;
endif;

// Handle progress updates
if (isset($_POST['mark_complete']) && isset($_POST['classnote_id'])) {
    $classnote_id = intval($_POST['classnote_id']);
    
    $update_stmt = $conn->prepare("
        INSERT INTO student_classnotes_progress (student_id, classnote_id, status, last_accessed) 
        VALUES (?, ?, 'completed', NOW())
        ON DUPLICATE KEY UPDATE status='completed', last_accessed=NOW()
    ");
    $update_stmt->bind_param("ii", $student_id, $classnote_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Marked as completed']);
    exit;
}

$conn->close();
?>
</body>
</html>