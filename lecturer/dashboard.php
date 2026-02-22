<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

/* ================= FETCH UNITS ================= */
$units = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name
    FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE lu.lecturer_id = ?
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
$stmt->close();

/* ================= FETCH NOTES ================= */
$notesByUnit = [];
$stmt = $conn->prepare("
    SELECT n.file_path, n.unit_id, n.uploaded_at
    FROM notes n
    JOIN lecturer_units lu ON lu.unit_id = n.unit_id
    WHERE lu.lecturer_id = ?
    ORDER BY n.uploaded_at DESC
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$res = $stmt->get_result();
while ($note = $res->fetch_assoc()) {
    $notesByUnit[$note['unit_id']][] = $note;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Your local CSS - this path is correct for your folder structure -->
    <link rel="stylesheet" href="./css/styles.css">
    
    <!-- Temporary test - remove after confirming CSS works -->
    <!-- <style> body { background: #ffebee !important; } </style> -->
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <h2>Lecturer Dashboard</h2>
    <div class="navbar-right">
        <div class="notification">
            <i class="fa fa-bell"></i>
        </div>
        <div class="profile">
            <div class="avatar"><?= strtoupper(substr($lecturer_name ?? 'L', 0, 1)) ?></div>
        </div>
    </div>
</nav>

<!-- SIDEBAR -->
<aside class="sidebar">
    <ul>
        <li class="active"><i class="fa fa-home"></i> Dashboard</li>
        <li><i class="fa fa-book"></i> Training</li>
        <li><i class="fa fa-file-alt"></i> Exams</li>
        <li><i class="fa fa-chalkboard-teacher"></i> Lessons</li>
        <li><i class="fa fa-chart-line"></i> My Progress</li>
        <li><i class="fa fa-user"></i> Account</li>
        <li><i class="fa fa-user-circle"></i> Profile</li>
        <li><i class="fa fa-cog"></i> Settings</li>
        <li><i class="fa fa-sign-out-alt"></i> Logout</li>
    </ul>
</aside>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="dashboard-card">
        <nav class="card-navbar">
            <ul>
                <li class="notes active">Notes</li>
                <li class="assignments">Assignments</li>
                <li class="units">Units</li>
                <li class="meetings">Meetings</li>
            </ul>
        </nav>

        <!-- NOTES SECTION -->
        <div class="notes-content content-section">
            <div class="notes-box">
                <h3>Welcome, <?= htmlspecialchars($lecturer_name ?? 'Lecturer') ?>!</h3>
                <p style="margin: 16px 0; color: #555; line-height: 1.5;">
                    Manage your lecture notes here.<br>
                    View existing materials or upload new ones for your assigned units.
                </p>
                <div style="margin-top: 20px; display: flex; gap: 16px; justify-content: center;">
                   <a href="upload_notes.php" class="btn btn-secondary" id="view-notes-btn">
    View Interactive Notes
</a>
                    <button id="uploadNotesBtn" class="btn btn-green">Upload Notes</button>
                </div>
            </div>

            <div id="notes-tiles" class="hidden">
                <?php if (count($units) > 0): ?>
                    <?php foreach ($units as $unit): ?>
                        <div class="unit-tile" data-unit-id="<?= $unit['id'] ?>">
                            <?= htmlspecialchars($unit['name']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#777; padding:20px;">No units assigned yet.</p>
                <?php endif; ?>
            </div>

            <!-- Hidden notes data containers -->
            <div id="all-notes-data" style="display:none;">
                <?php foreach ($notesByUnit as $unitId => $notes): ?>
                    <div class="unit-notes-data" data-unit-id="<?= $unitId ?>">
                        <table>
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes as $note):
                                    $file = htmlspecialchars($note['file_path']);
                                    $path = "../assets/uploads/" . $file;
                                ?>
                                <tr>
                                    <td><?= $file ?></td>
                                    <td><?= date("d M Y • h:i A", strtotime($note['uploaded_at'])) ?></td>
                                    <td>
                                        <a href="<?= $path ?>" target="_blank">View</a> |
                                        <a href="<?= $path ?>" download>Download</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Other tabs placeholders -->
        <div class="assignments-content content-section" style="display:none;">
            <h3>Assignments</h3>
            <div class="assignments-controls">
    <button id="uploadAssignmentBtn" class="btn-primary">Upload Assignment</button>
    <button id="interactiveAssignmentsBtn" class="btn-secondary">Interactive Assignments</button>
    <button class="btn-secondary" onclick="window.location.href='assignment_submissions.php'">
    View Submissions
</button>
</div>
<div id="interactive-tiles-section">
    <div class="tiles-grid">

        <a href="create_questions.php" class="tile-card">
            <div class="icon-wrapper">
                <i class="fas fa-plus"></i>
            </div>
            <span>Create Assignment</span>
        </a>

        <a href="scores_overview.php" class="tile-card">
            <div class="icon-wrapper">
                <i class="fas fa-chart-line"></i>
            </div>
            <span>View Student Scores</span>
        </a>

        <a href="submission_stats.php" class="tile-card">
            <div class="icon-wrapper">
                <i class="fas fa-chart-bar"></i>
            </div>
            <span>Submission Stats</span>
        </a>

        <a href="AIGrading.php" class="tile-card">
            <div class="icon-wrapper">
                <i class="fas fa-robot"></i>
            </div>
            <span>AI Grading</span>
        </a>

    </div>
</div>

    <!-- Assignments Table Section -->
    <div id="assignments-table-section" class="hidden">
        <!-- Keep your existing assignments table PHP here -->
    </div>

    <!-- Submissions Section -->
    <div id="submissions-section" class="hidden">
        <!-- Keep your existing submissions PHP table here -->
    </div>

</div>
<div id="uploadAssignmentModal" class="modal hidden">
    <div class="modal-content bg-white p-6 rounded-2xl border border-f5e6b2">
        <span class="close text-92400e text-2xl font-bold cursor-pointer hover:text-f59e0b" id="uploadAssignmentClose">&times;</span>
        <h3 class="text-xl font-semibold stat-text-secondary mb-4">Create Assignment</h3>

        <form action="../actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_assignment">

            <!-- Unit selection -->
            <label class="block text-sm font-medium stat-text-primary mb-2">Unit:</label>
            <select name="unit_id" id="assignmentUnit" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">
                <option value="">-- Select Unit --</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Assignment Title -->
            <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Assignment Title:</label>
            <input type="text" name="title" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">

            <!-- Instructions / Description -->
            <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Written Instructions:</label>
            <textarea name="instructions" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e" rows="4"></textarea>

            <!-- Deadline -->
            <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Deadline:</label>
            <input type="datetime-local" name="due_date" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">

            <!-- Optional File -->
            <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Attach File (optional):</label>
            <input type="file" name="assignment_file" class="text-sm text-92400e">

            <button type="submit" class="btn-primary px-4 py-2 mt-4 rounded-lg">Create Assignment</button>
        </form>
    </div>
</div>
       <div class="units-content content-section" style="display:none;">

    <div class="units-header">
        <h3>My Units</h3>
        <button class="btn-primary" onclick="showModal('addUnitModal')">
            + Add Unit
        </button>
    </div>

    <div class="units-grid">
        <?php if (count($units) > 0): ?>
            <?php foreach ($units as $unit): ?>
                <div class="unit-slim-tile">
                    <div class="unit-name">
                        <?= htmlspecialchars($unit['name']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-units">No units assigned yet.</p>
        <?php endif; ?>
    </div>

</div>
        <div id="addUnitModal" class="modal hidden">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addUnitModal')">&times;</span>
        <h3>Add Unit</h3>

        <form action="../actions.php" method="POST">
            <input type="hidden" name="action" value="add_single_lecturer_unit">

            <label>Select Course:</label>
            <select name="course_id" id="courseSelect" required>
                <option value="">-- Select Course --</option>
                <?php
                try {
                    $courseRes = $conn->query("SELECT id, name FROM courses");
                    while ($course = $courseRes->fetch_assoc()) {
                        echo "<option value='{$course['id']}'>" . htmlspecialchars($course['name']) . "</option>";
                    }
                } catch (mysqli_sql_exception $e) {
                    echo "<option value=''>Error loading courses</option>";
                }
                ?>
            </select>

            <label>Select Unit:</label>
            <select name="unit_id" id="unitSelect" required>
                <option value="">-- Select Unit --</option>
            </select>

            <button type="submit" class="btn-primary">Add Unit</button>
        </form>
    </div>
</div>
        
       <!-- Meetings / Office Hours Section -->
<div class="meetings-content content-section" style="display:none;">

    <!-- Header -->
    <div class="meetings-header">
        <h3>Office Hours & Meetings</h3>
        <div class="header-actions">
            <button class="btn-primary" id="scheduleMeetingBtn">
                <i class="fas fa-plus mr-2"></i> Schedule Meeting
            </button>
            <!-- Optional future button -->
            <!-- <button class="btn-secondary"><i class="far fa-calendar-alt mr-2"></i> My Calendar</button> -->
        </div>
    </div>

    <!-- Quick Actions (optional - can be removed if you prefer only table) -->
    <div class="meetings-quick-actions hidden md:grid">
        <div class="action-tile" data-action="availability">
            <div class="icon-wrapper"><i class="fas fa-calendar-check"></i></div>
            <span>Set Availability</span>
        </div>
        <div class="action-tile" data-action="reminders">
            <div class="icon-wrapper"><i class="fas fa-bell"></i></div>
            <span>Send Reminders</span>
        </div>
    </div>

    <!-- Upcoming Meetings Table (your original logic preserved + styled) -->
    <section class="card bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold mb-5 text-gray-800">Upcoming Meetings & Office Hours</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-amber-200 bg-gray-50">
                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Title</th>
                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Unit</th>
                        <th class="py-3 px-4 text-sm font-semibold text-amber-700 uppercase tracking-wide">Time</th>
                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="text-gray-800">
                    <?php
                    try {
                        $now = date('Y-m-d H:i:s');
                        $stmt = $conn->prepare("
                            SELECT m.id, m.title, m.scheduled_time, u.name AS unit_name 
                            FROM meetings m 
                            JOIN units u ON m.unit_id = u.id 
                            JOIN lecturer_units lu ON u.id = lu.unit_id
                            WHERE lu.lecturer_id = ? AND m.scheduled_time >= ?
                            ORDER BY m.scheduled_time ASC
                        ");
                        $stmt->bind_param("is", $lecturer_id, $now);
                        $stmt->execute();
                        $res = $stmt->get_result();

                        if ($res->num_rows > 0) {
                            while ($meeting = $res->fetch_assoc()) {
                                $timeFormatted = date("d M Y • h:i A", strtotime($meeting['scheduled_time']));
                                echo "<tr class='border-b border-gray-100 hover:bg-amber-50/40 transition-colors'>";
                                echo "<td class='py-4 px-4 font-medium'>" . htmlspecialchars($meeting['title']) . "</td>";
                                echo "<td class='py-4 px-4 text-gray-600'>" . htmlspecialchars($meeting['unit_name']) . "</td>";
                                echo "<td class='py-4 px-4 text-sm text-amber-700 font-medium'>" . $timeFormatted . "</td>";
                                echo "<td class='py-4 px-4'>";
                                echo "<a class='text-amber-600 hover:text-amber-800 hover:underline font-medium' ";
                                echo "href='meeting_ide.php?meeting_id=" . htmlspecialchars($meeting['id']) . "' target='_blank'>Join</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='py-10 text-center text-gray-500 italic'>No upcoming meetings or office hours scheduled.</td></tr>";
                        }
                        $stmt->close();
                    } catch (mysqli_sql_exception $e) {
                        echo "<tr><td colspan='4' class='py-10 text-center text-red-600'>Error loading meetings. Please try again later.</td></tr>";
                        error_log("Meetings fetch error: " . $e->getMessage());
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </section>

</div>


<!-- ── Schedule Meeting Modal ── -->
<div id="scheduleMeetingModal" class="modal hidden">
    <div class="modal-content max-w-lg mx-auto">
        <div class="modal-header">
            <h3>Schedule New Meeting / Office Hour</h3>
            <span class="close" onclick="hideModal('scheduleMeetingModal')">×</span>
        </div>
        <div class="modal-body">
            <form action="../actions.php" method="POST">
                <input type="hidden" name="action" value="schedule_meeting">

                <div class="form-group">
                    <label>Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Office Hours - Week 5" class="w-full">
                </div>

                <div class="form-group">
                    <label>Unit <span class="text-red-500">*</span></label>
                    <select name="unit_id" required>
                        <option value="">— Select Unit —</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label>Date & Time <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="scheduled_time" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration" min="15" max="180" value="60">
                    </div>
                </div>

                <div class="form-group">
                    <label>Meeting Link / Location (optional)</label>
                    <input type="url" name="meeting_link" placeholder="https://meet.google.com/xxx-yyyy-zzz">
                </div>

                <div class="form-group">
                    <label>Notes / Agenda</label>
                    <textarea name="description" rows="3" placeholder="Topics to cover, preparation needed..."></textarea>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="btn-secondary" onclick="hideModal('scheduleMeetingModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Meeting</button>
                </div>
            </form>
        </div>
    </div>
</div>
  

<!-- UPLOAD MODAL -->
<div id="uploadNotesModal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload Lecture Notes</h3>
            <span class="close" id="uploadModalClose">×</span>
        </div>
        <div class="modal-body">
            <form action="../actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_notes">
                <label style="display:block; margin:0 0 8px; font-weight:500;">Unit</label>
                <select name="unit_id" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; margin-bottom:20px;">
                    <option value="">— Select Unit —</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label style="display:block; margin:0 0 8px; font-weight:500;">Files (PDF, Word, PowerPoint)</label>
                <input type="file" name="notes_file[]" required multiple accept=".pdf,.doc,.docx,.ppt,.pptx"
                       style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">
                <div style="margin-top:28px; text-align:right;">
                    <button type="submit" class="btn btn-green">Upload Files</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW NOTES MODAL -->
<div id="notes-modal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-unit-name">Unit Notes</h3>
            <span class="close">×</span>
        </div>
        <div class="modal-body">
            <div id="unit-notes-list"></div>
        </div>
    </div>
</div>

<script>
// TAB SWITCHING
document.querySelectorAll('.card-navbar li').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.card-navbar li').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
        const target = tab.className.split(' ')[0];
        document.querySelector(`.${target}-content`).style.display = 'block';
    });
});

// VIEW NOTES
const viewBtn = document.getElementById('view-notes-btn');
const tiles = document.getElementById('notes-tiles');
const notesModal = document.getElementById('notes-modal');
const modalClose = notesModal.querySelector('.close');
const unitNameEl = document.getElementById('modal-unit-name');
const notesListEl = document.getElementById('unit-notes-list');

viewBtn?.addEventListener('click', () => tiles.classList.toggle('hidden'));

document.querySelectorAll('.unit-tile').forEach(tile => {
    tile.addEventListener('click', () => {
        const unitId = tile.dataset.unitId;
        unitNameEl.textContent = tile.textContent;
        const data = document.querySelector(`.unit-notes-data[data-unit-id="${unitId}"]`);
        notesListEl.innerHTML = data ? data.innerHTML : '<p style="color:#777;">No notes uploaded yet.</p>';
        notesModal.classList.remove('hidden');
    });
});

modalClose?.addEventListener('click', () => notesModal.classList.add('hidden'));
notesModal?.addEventListener('click', e => {
    if (e.target === notesModal) notesModal.classList.add('hidden');
});

// UPLOAD MODAL
const uploadBtn = document.getElementById('uploadNotesBtn');
const uploadModal = document.getElementById('uploadNotesModal');
const uploadClose = document.getElementById('uploadModalClose');

uploadBtn?.addEventListener('click', () => uploadModal.classList.remove('hidden'));
uploadClose?.addEventListener('click', () => uploadModal.classList.add('hidden'));
uploadModal?.addEventListener('click', e => {
    if (e.target === uploadModal) uploadModal.classList.add('hidden');
});
document.addEventListener('DOMContentLoaded', () => {

    const uploadBtn = document.getElementById('uploadAssignmentBtn');
    const interactiveBtn = document.getElementById('interactiveAssignmentsBtn');
    const submissionsBtn = document.getElementById('viewSubmissionsBtn');

    const uploadModal = document.getElementById('uploadAssignmentModal');
    const uploadClose = document.getElementById('uploadAssignmentClose');

    const tilesSection = document.getElementById('interactive-tiles-section');
    const assignmentsTable = document.getElementById('assignments-table-section');
    const submissionsSection = document.getElementById('submissions-section');

    function hideAllSections() {
        tilesSection.classList.add('hidden');
        assignmentsTable.classList.add('hidden');
        submissionsSection.classList.add('hidden');
    }

    // Upload Assignment
    uploadBtn.addEventListener('click', () => {
        uploadModal.classList.remove('hidden');
    });

    uploadClose.addEventListener('click', () => {
        uploadModal.classList.add('hidden');
    });

    uploadModal.addEventListener('click', (e) => {
        if (e.target === uploadModal) {
            uploadModal.classList.add('hidden');
        }
    });

    // Interactive Assignments
    interactiveBtn.addEventListener('click', () => {
        hideAllSections();
        tilesSection.classList.remove('hidden');
    });

    // View Submissions
    submissionsBtn.addEventListener('click', () => {
        hideAllSections();
        submissionsSection.classList.remove('hidden');
    });

});
function showModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

function hideModal(id) {
    document.getElementById(id).classList.add('hidden');
}
document.addEventListener('DOMContentLoaded', () => {

    // Schedule Meeting button
    const scheduleBtn = document.getElementById('scheduleMeetingBtn');
    if (scheduleBtn) {
        scheduleBtn.addEventListener('click', () => {
            showModal('scheduleMeetingModal');
        });
    }

    // Reuse your existing modal helpers if you have them, or add these:
    function showModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    function hideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    // Close on outside click or close button
    document.addEventListener('click', e => {
        if (e.target.classList.contains('modal') || e.target.classList.contains('close')) {
            const modal = e.target.closest('.modal');
            if (modal) hideModal(modal.id);
        }
    });

    // Optional: ESC key to close
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            const open = document.querySelector('.modal:not(.hidden)');
            if (open) hideModal(open.id);
        }
    });
});
</script>
</body>
</html>