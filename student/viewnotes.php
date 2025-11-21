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

if (!$student) {
    die("Student record not found.");
}

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

// Function to update student progress
function updateStudentProgress($conn, $student_id, $classnote_id, $status = 'in_progress') {
    try {
        // Check if progress record exists
        $check_stmt = $conn->prepare("
            SELECT id FROM student_classnotes_progress 
            WHERE student_id = ? AND classnote_id = ?
        ");
        $check_stmt->bind_param("ii", $student_id, $classnote_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $update_stmt = $conn->prepare("
                UPDATE student_classnotes_progress 
                SET status = ?, last_accessed = NOW() 
                WHERE student_id = ? AND classnote_id = ?
            ");
            $update_stmt->bind_param("sii", $status, $student_id, $classnote_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Insert new record
            $insert_stmt = $conn->prepare("
                INSERT INTO student_classnotes_progress (student_id, classnote_id, status, last_accessed) 
                VALUES (?, ?, ?, NOW())
            ");
            $insert_stmt->bind_param("iis", $student_id, $classnote_id, $status);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        
        $check_stmt->close();
        return true;
    } catch (mysqli_sql_exception $e) {
        error_log("Error updating progress: " . $e->getMessage());
        return false;
    }
}

// Handle progress update via AJAX
if (isset($_POST['update_progress']) && isset($_POST['classnote_id'])) {
    header('Content-Type: application/json');
    $classnote_id = intval($_POST['classnote_id']);
    $status = $_POST['status'] ?? 'in_progress';
    
    if (updateStudentProgress($conn, $student_id, $classnote_id, $status)) {
        echo json_encode(['success' => true, 'message' => 'Progress updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update progress']);
    }
    exit;
}

// Handle mark as complete
if (isset($_POST['mark_complete']) && isset($_POST['classnote_id'])) {
    header('Content-Type: application/json');
    $classnote_id = intval($_POST['classnote_id']);
    
    if (updateStudentProgress($conn, $student_id, $classnote_id, 'completed')) {
        echo json_encode(['success' => true, 'message' => 'Marked as completed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark as completed']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Notes</title>
<style>
    body { font-family: Arial, sans-serif; background: #f3f4f6; padding: 2rem; }
    h1 { margin-bottom: 2rem; }
    .units-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .unit-tile { background: #fff; padding: 1rem; border-radius: 1rem; box-shadow: 0 2px 6px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; }
    .unit-tile:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    .card { background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 2rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
    .table-row-hover:hover { background: #f9fafb; }
    .topic-title { color: #dc2626; font-weight: bold; }
    .subtopic-title { color: #16a34a; font-weight: bold; margin-left: 1rem; }
    .content-area { margin: 1rem 0; line-height: 1.6; }
    .content-area img { max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0; }
    .choices-list { background: #e2e8f0; padding: 15px; border-radius: 8px; margin: 10px 0; }
    .choice-item { padding: 5px 0; }
    .choice-correct { color: #16a34a; font-weight: bold; }
    .files-section { margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
    .file-item { display: inline-block; background: #f59e0b; color: white; padding: 4px 12px; border-radius: 20px; margin: 5px 5px 5px 0; font-size: 0.9em; text-decoration: none; }
    .file-item:hover { background: #d97706; }
    .progress-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; text-transform: uppercase; }
    .progress-not-started { background: #f1f5f9; color: #6b7280; }
    .progress-in-progress { background: #fef3c7; color: #f59e0b; }
    .progress-completed { background: #d1fae5; color: #10b981; }
    .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
</style>
</head>
<body>

<h1>Student Notes</h1>

<!-- Units Tiles -->
<div class="units-grid">
    <?php while ($unit = $units_result->fetch_assoc()): ?>
        <div class="unit-tile" data-unit-id="<?= htmlspecialchars($unit['id']) ?>">
            <h3><?= htmlspecialchars($unit['name']) ?></h3>
            <p><?= htmlspecialchars($unit['code']) ?></p>
        </div>
    <?php endwhile; ?>
</div>

<!-- Notes Content -->
<div id="notes-content"></div>

<script>
document.querySelectorAll('.unit-tile').forEach(tile => {
    tile.addEventListener('click', () => {
        const unitId = tile.dataset.unitId;
        const formData = new FormData();
        formData.append('unit_id', unitId);

        fetch('', {method: 'POST', body: formData})
            .then(res => res.text())
            .then(html => {
                document.getElementById('notes-content').innerHTML = html;
                // Auto-track progress when content is loaded
                document.querySelectorAll('.topic-card').forEach(card => {
                    const classnoteId = card.dataset.classnoteId;
                    const currentStatus = card.querySelector('.progress-badge').textContent.toLowerCase();
                    
                    // If not started, mark as in progress
                    if (currentStatus.includes('not_started') || currentStatus.includes('not started')) {
                        updateProgress(classnoteId, 'in_progress');
                    }
                });
            });
    });
});

function updateProgress(classnoteId, status) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'update_progress=1&classnote_id=' + classnoteId + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Progress updated to:', status);
            // Update the badge visually
            const badge = document.querySelector('[data-classnote-id="' + classnoteId + '"] .progress-badge');
            if (badge) {
                badge.textContent = status.replace('_', ' ');
                badge.className = 'progress-badge progress-' + status;
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unit_id'])):
    $unit_id = intval($_POST['unit_id']);

    // Fetch notes with progress
    $stmt = $conn->prepare("
        SELECT cn.id AS classnote_id, cn.title, cn.subtopics_json, cn.file_path, cn.uploaded_at, 
               scp.status
        FROM classnotes cn
        LEFT JOIN student_classnotes_progress scp 
            ON scp.classnote_id = cn.id AND scp.student_id = ?
        WHERE cn.unit_id = ?
        ORDER BY cn.uploaded_at DESC
    ");
    $stmt->bind_param("ii", $student_id, $unit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    ?>

    <section class="card">
        <h2>Class Notes</h2>
        <div class="topics-container">
            <?php if ($res->num_rows > 0): ?>
                <?php while ($note = $res->fetch_assoc()):
                    $file = htmlspecialchars($note['file_path'] ?? '');
                    $full_path = "../assets/uploads/" . $file;
                    $fileExists = !empty($file) && file_exists($full_path);
                    $subtopics = json_decode($note['subtopics_json'], true) ?? [];
                    $progress_status = $note['status'] ?? 'not_started';
                ?>
                <div class="topic-card" data-classnote-id="<?= $note['classnote_id'] ?>">
                    <div class="topic-header">
                        <h3><?= htmlspecialchars($note['title']) ?></h3>
                        <div class="topic-actions">
                            <span class="progress-badge progress-<?= $progress_status ?>">
                                <?= ucfirst(str_replace('_', ' ', $progress_status)) ?>
                            </span>
                            <button class="btn btn-primary" onclick="markAsComplete(<?= $note['classnote_id'] ?>)">
                                Mark Complete
                            </button>
                        </div>
                    </div>
                    
                    <div class="topic-meta">
                        <span>Uploaded: <?= date("d M Y, h:i A", strtotime($note['uploaded_at'])) ?></span>
                    </div>
                    
                    <div class="subtopics">
                        <?php foreach($subtopics as $subtopic): ?>
                            <?php if (is_array($subtopic)): ?>
                                <div class="subtopic">
                                    <h4 class="subtopic-title"><?= htmlspecialchars($subtopic['title'] ?? 'Untitled Subtopic') ?></h4>
                                    
                                    <?php if (!empty($subtopic['content'])): ?>
                                        <div class="content-area">
                                            <?= $subtopic['content'] ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($subtopic['choices'])): ?>
                                        <div class="choices-list">
                                            <strong>Questions:</strong>
                                            <?php foreach($subtopic['choices'] as $choice): ?>
                                                <div class="choice-item <?= ($subtopic['correctChoice'] == $choice['id']) ? 'choice-correct' : '' ?>">
                                                    <?= htmlspecialchars($choice['text'] ?? '') ?>
                                                    <?php if ($subtopic['correctChoice'] == $choice['id']): ?> ✓<?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($subtopic['files'])): ?>
                                        <div class="files-section">
                                            <strong>Attached Files:</strong><br>
                                            <?php foreach($subtopic['files'] as $file): ?>
                                                <a href="../uploads/files/<?= htmlspecialchars($file['name'] ?? '') ?>" 
                                                   class="file-item" 
                                                   target="_blank"
                                                   download="<?= htmlspecialchars($file['original_name'] ?? $file['name']) ?>">
                                                    📎 <?= htmlspecialchars($file['label'] ?? $file['original_name'] ?? 'File') ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($fileExists): ?>
                        <div class="file-actions">
                            <a href="<?= $full_path ?>" target="_blank" class="btn btn-primary">View File</a>
                            <a href="<?= $full_path ?>" download class="btn btn-primary">Download File</a>
                        </div>
                    <?php elseif (!empty($file)): ?>
                        <div style="color: red;">File missing: <?= htmlspecialchars($file) ?></div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center;">No notes uploaded yet for this unit.</p>
            <?php endif; ?>
        </div>
    </section>

<?php
    $stmt->close();
endif;
$conn->close();
?>

</body>
</html>