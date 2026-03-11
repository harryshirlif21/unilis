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
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';

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
    
    <!-- Your CSS -->
    <link rel="stylesheet" href="./css/styles.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="welcome-msg">
        <strong>👋 Welcome back, <?= htmlspecialchars($lecturer_name) ?>!</strong>
    </div>

    <div class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-ellipsis-v"></i>
    </div>

    <div class="nav-icon" id="notifications-icon">
        <i class="fas fa-bell"></i>
    </div>

    <div class="nav-icon" id="profile-icon">
        <i class="fas fa-user"></i>
    </div>
</nav>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
        <h4>Main Navigation</h4>
        <ul>
    <li class="blue active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></li>
    <li class="green">
        <a href="../lecturer/course_builder.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-book"></i><span>Training</span>
        </a>
    </li>
    <li class="orange"><i class="fas fa-file-alt"></i><span>Exams</span></li>
    <li class="golden">
        <a href="../lecturer/lesson_editor.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-chalkboard-teacher"></i><span>Lessons</span>
        </a>
    </li>
    <li class="brown"><i class="fas fa-chart-line"></i><span>My Progress</span></li>
    <li class="teal"><i class="fas fa-users"></i><span>Create Team</span></li>
    <li class="purple">
        <a href="../teams/views/lecturer_teams.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-users-cog"></i><span>Lecturer Teams</span>
        </a>
    </li>
</ul>
    </div>

    <div class="sidebar-section">
        <h4>Account</h4>
        <ul>
            <li class="blue"><i class="fas fa-user-circle"></i><span>Account</span></li>
            <li class="green"><i class="fas fa-user"></i><span>Profile</span></li>
            <li class="orange"><i class="fas fa-cog"></i><span>Settings</span></li>
            <li class="brown" onclick="window.location.href='../logout.php'">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </li>
        </ul>
    </div>
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
                <li class="attendance">Attendance</li>
            </ul>
        </nav>

        <!-- NOTES SECTION -->
        <div class="notes-content content-section">
            <div class="notes-box">
                <h3>Welcome, <?= htmlspecialchars($lecturer_name) ?>!</h3>
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

        <!-- ASSIGNMENTS SECTION -->
        <div class="assignments-content content-section" style="display:none;">
            <h3>Assignments</h3>
            <div class="assignments-controls">
                <button id="uploadAssignmentBtn" class="btn-primary">Upload Assignment</button>
                <button id="interactiveAssignmentsBtn" class="btn-secondary">Interactive Assignments</button>
                <button class="btn-secondary" id="viewSubmissionsBtn" onclick="window.location.href='assignment_submissions.php'">
                    View Submissions
                </button>
            </div>

            <div id="interactive-tiles-section" class="hidden">
                <div class="tiles-grid">
                    <a href="create_questions.php" class="tile-card">
                        <div class="icon-wrapper"><i class="fas fa-plus"></i></div>
                        <span>Create Assignment</span>
                    </a>
                    <a href="scores_overview.php" class="tile-card">
                        <div class="icon-wrapper"><i class="fas fa-chart-line"></i></div>
                        <span>View Student Scores</span>
                    </a>
                    <a href="submission_stats.php" class="tile-card">
                        <div class="icon-wrapper"><i class="fas fa-chart-bar"></i></div>
                        <span>Submission Stats</span>
                    </a>
                    <a href="AIGrading.php" class="tile-card">
                        <div class="icon-wrapper"><i class="fas fa-robot"></i></div>
                        <span>AI Grading</span>
                    </a>
                </div>
            </div>

            <div id="assignments-table-section" class="hidden">
                <!-- Your assignments table PHP here -->
            </div>

            <div id="submissions-section" class="hidden">
                <!-- Your submissions table PHP here -->
            </div>
        </div>

        <!-- UNITS SECTION -->
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

        <!-- MEETINGS SECTION -->
        <div class="meetings-content content-section" style="display:none;">
            <div class="meetings-header">
                <h3>Office Hours & Meetings</h3>
                <div class="header-actions">
                    <button class="btn-primary" id="scheduleMeetingBtn">
                        <i class="fas fa-plus mr-2"></i> Schedule Meeting
                    </button>
                </div>
            </div>

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
                                echo "<tr><td colspan='4' class='py-10 text-center text-red-600'>Error loading meetings.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ATTENDANCE SECTION -->
        <div class="attendance-content content-section" style="display:none;">
            <div class="notes-box">
                <h3>Take Attendance</h3>
                <p style="margin: 16px 0; color: #555; line-height: 1.5;">
                    Select a unit and mark student attendance for today's session.<br>
                    Attendance will be recorded instantly.
                </p>
                <div style="margin-top: 24px;">
                    <button id="openAttendanceModalBtn" class="btn-golden px-6 py-3 text-lg font-medium">
                        Open Attendance Sheet
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODALS -->

<!-- Upload Notes Modal -->
<div id="uploadNotesModal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload Lecture Notes</h3>
            <span class="close" id="uploadModalClose">×</span>
        </div>
        <div class="modal-body">
            <form action="../actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_notes">
                <label>Unit</label>
                <select name="unit_id" required>
                    <option value="">— Select Unit —</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Files (PDF, Word, PowerPoint)</label>
                <input type="file" name="notes_file[]" required multiple accept=".pdf,.doc,.docx,.ppt,.pptx">
                <button type="submit" class="btn btn-green">Upload Files</button>
            </form>
        </div>
    </div>
</div>

<!-- View Notes Modal -->
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

<!-- Upload Assignment Modal -->
<div id="uploadAssignmentModal" class="modal hidden">
    <div class="modal-content bg-white p-6 rounded-2xl border border-f5e6b2">
        <span class="close text-92400e text-2xl font-bold cursor-pointer hover:text-f59e0b" id="uploadAssignmentClose">×</span>
        <h3 class="text-xl font-semibold stat-text-secondary mb-4">Create Assignment</h3>
        <form action="../actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_assignment">
            <label>Unit:</label>
            <select name="unit_id" id="assignmentUnit" required>
                <option value="">-- Select Unit --</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Assignment Title:</label>
            <input type="text" name="title" required>
            <label>Written Instructions:</label>
            <textarea name="instructions" required rows="4"></textarea>
            <label>Deadline:</label>
            <input type="datetime-local" name="due_date" required>
            <label>Attach File (optional):</label>
            <input type="file" name="assignment_file">
            <button type="submit" class="btn-primary">Create Assignment</button>
        </form>
    </div>
</div>

<!-- Add Unit Modal -->
<div id="addUnitModal" class="modal hidden">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addUnitModal')">×</span>
        <h3>Add Unit</h3>
        <form action="../actions.php" method="POST">
            <input type="hidden" name="action" value="add_single_lecturer_unit">
            <label>Select Course:</label>
            <select name="course_id" id="courseSelect" required>
                <option value="">-- Select Course --</option>
                <?php
                $courseRes = $conn->query("SELECT id, name FROM courses");
                while ($course = $courseRes->fetch_assoc()) {
                    echo "<option value='{$course['id']}'>" . htmlspecialchars($course['name']) . "</option>";
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

<!-- Schedule Meeting Modal -->
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
                    <input type="text" name="title" required>
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
                    <input type="url" name="meeting_link" placeholder="https://meet.google.com/...">
                </div>
                <div class="form-group">
                    <label>Notes / Agenda</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="btn-secondary" onclick="hideModal('scheduleMeetingModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Meeting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Attendance Modal -->
<div id="attendanceModal" class="modal hidden">
    <div class="modal-content bg-white rounded-2xl border border-f5e6b2 shadow-2xl" style="max-width: 580px; max-height: 92vh; overflow-y: auto;">
        <span class="close text-92400e text-3xl font-bold cursor-pointer hover:text-f59e0b absolute top-5 right-6 z-10" id="attendanceModalClose">×</span>
        <h3 class="text-2xl font-bold stat-text-secondary mb-8 text-center pt-8">Take Attendance</h3>
        <form id="attendanceForm" method="POST" action="attendance_functions.php" class="px-10 pb-10">
            <div class="mb-6">
                <label class="block text-sm font-medium stat-text-primary mb-3">
                    Select Unit <span class="text-red-500">*</span>
                </label>
                <select name="unit_id" id="modalUnitId" required class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-lg focus:ring-2 focus:ring-f59e0b focus:border-f59e0b transition">
                    <option value="">-- Choose Unit --</option>
                    <?php
                    $lecturer_id = $_SESSION['user_id'] ?? 0;
                    if ($lecturer_id > 0) {
                        $stmt = $conn->prepare("
                            SELECT u.id, u.name, c.name AS course_name, u.year, u.semester
                            FROM units u
                            JOIN lecturer_units lu ON u.id = lu.unit_id
                            LEFT JOIN courses c ON u.course_id = c.id
                            WHERE lu.lecturer_id = ?
                            ORDER BY u.name
                        ");
                        $stmt->bind_param("i", $lecturer_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($unit = $result->fetch_assoc()): ?>
                            <option value="<?= $unit['id'] ?>"
                                    data-course="<?= htmlspecialchars($unit['course_name'] ?? '') ?>"
                                    data-year="<?= $unit['year'] ?? '' ?>"
                                    data-semester="<?= $unit['semester'] ?? '' ?>">
                                <?= htmlspecialchars($unit['name']) ?>
                            </option>
                        <?php endwhile;
                        $stmt->close();
                    }
                    ?>
                </select>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium stat-text-primary mb-3">Session Date</label>
                <input type="date" name="session_date" value="<?= date('Y-m-d') ?>" required class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-lg">
            </div>
            <div class="mb-8">
                <label class="block text-sm font-medium stat-text-primary mb-3">Remarks / Topic Covered (optional)</label>
                <textarea name="remarks" rows="3" class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-lg resize-y"></textarea>
            </div>
            <div class="text-center">
                <button type="submit" class="btn-golden px-10 py-4 text-xl font-semibold">Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<script>
// Single tab switching logic
document.addEventListener('DOMContentLoaded', () => {
    // Tab switching
    document.querySelectorAll('.card-navbar li').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.card-navbar li').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');

            const target = tab.className.split(' ')[0];
            const section = document.querySelector(`.${target}-content`);
            if (section) section.style.display = 'block';
        });
    });

    // Mobile sidebar toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.toggle('show');
    });

    document.addEventListener('click', e => {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });

    // Notes functionality
    const viewBtn = document.getElementById('view-notes-btn');
    const tiles = document.getElementById('notes-tiles');
    const notesModal = document.getElementById('notes-modal');

    viewBtn?.addEventListener('click', () => tiles?.classList.toggle('hidden'));

    document.querySelectorAll('.unit-tile').forEach(tile => {
        tile.addEventListener('click', () => {
            const unitId = tile.dataset.unitId;
            document.getElementById('modal-unit-name').textContent = tile.textContent;
            const data = document.querySelector(`.unit-notes-data[data-unit-id="${unitId}"]`);
            document.getElementById('unit-notes-list').innerHTML = data 
                ? data.innerHTML 
                : '<p style="color:#777;">No notes uploaded yet.</p>';
            notesModal?.classList.remove('hidden');
        });
    });

    notesModal?.querySelector('.close')?.addEventListener('click', () => notesModal.classList.add('hidden'));
    notesModal?.addEventListener('click', e => {
        if (e.target === notesModal) notesModal.classList.add('hidden');
    });

    // Upload notes modal
    document.getElementById('uploadNotesBtn')?.addEventListener('click', () => {
        document.getElementById('uploadNotesModal')?.classList.remove('hidden');
    });
    document.getElementById('uploadModalClose')?.addEventListener('click', () => {
        document.getElementById('uploadNotesModal')?.classList.add('hidden');
    });
    document.getElementById('uploadNotesModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('uploadNotesModal')) {
            document.getElementById('uploadNotesModal')?.classList.add('hidden');
        }
    });

    // Attendance modal
    document.getElementById('openAttendanceModalBtn')?.addEventListener('click', () => {
        document.getElementById('attendanceModal')?.classList.remove('hidden');
    });
    document.getElementById('attendanceModalClose')?.addEventListener('click', () => {
        document.getElementById('attendanceModal')?.classList.add('hidden');
    });
    document.getElementById('attendanceModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('attendanceModal')) {
            document.getElementById('attendanceModal')?.classList.add('hidden');
        }
    });

    // Generic modal helpers
    window.showModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };

    window.hideModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    };

    // Close modals on outside click or close button
    document.addEventListener('click', e => {
        if (e.target.classList.contains('modal') || e.target.classList.contains('close')) {
            const modal = e.target.closest('.modal');
            if (modal) hideModal(modal.id);
        }
    });

    // ESC key to close modals
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal:not(.hidden)');
            if (openModal) hideModal(openModal.id);
        }
    });
});
</script>
</body>
</html>