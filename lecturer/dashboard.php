<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php'; // Ensure this path is correct

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Fetch units taught by lecturer
$units = [];
$stmt = $conn->prepare("SELECT u.id, u.name FROM units u JOIN lecturer_units lu ON u.id = lu.unit_id WHERE lu.lecturer_id = ?");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
$stmt->close();

// Fetch stats for dashboard
$unit_count = count($units);
$total_assignments = $conn->query("SELECT COUNT(*) FROM assignments a JOIN lecturer_units lu ON a.unit_id = lu.unit_id WHERE lu.lecturer_id = $lecturer_id")->fetch_row()[0];
$active_assignments = $conn->query("SELECT COUNT(*) FROM assignments a JOIN lecturer_units lu ON a.unit_id = lu.unit_id WHERE lu.lecturer_id = $lecturer_id AND a.deadline > NOW()")->fetch_row()[0];
$pending_submissions = $conn->query("SELECT COUNT(*) FROM submissions s JOIN assignments a ON s.assignment_id = a.id JOIN lecturer_units lu ON a.unit_id = lu.unit_id WHERE lu.lecturer_id = $lecturer_id AND s.marks IS NULL")->fetch_row()[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - UNILIS</title>
	<link rel="stylesheet" href="text.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   
</head>
<body>
    <header class="header">
        <h1>UNILIS Lecturer Dashboard</h1>
        <div class="lecturer-info">Welcome, <?= htmlspecialchars($lecturer_name) ?></div>
        <button class="hamburger-menu" id="hamburgerMenu"><i class="fas fa-bars"></i></button>
    </header>

    <div class="off-canvas-menu" id="offCanvasMenu">
        <button class="close-btn" id="closeMenuBtn">×</button>
        <h2><?= htmlspecialchars($lecturer_name) ?></h2>
        <p>Lecturer - UNILIS</p>
        <button class="menu-item" onclick="showModal('uploadModal')"><i class="fas fa-upload"></i> Upload Notes</button>
        <button class="menu-item" onclick="showModal('viewNotesModal')"><i class="fas fa-file-alt"></i> View Notes</button>
        <button class="menu-item" onclick="showModal('assignmentModal')"><i class="fas fa-edit"></i> Create Assignment</button>
        <button class="menu-item" onclick="showModal('submissionModal')"><i class="fas fa-inbox"></i> View Submissions</button>
        <button class="menu-item" onclick="showModal('addUnitModal')"><i class="fas fa-plus-circle"></i> Add My Units</button>
        <a href="assignment_submissions.php" class="menu-item"><i class="fas fa-chart-bar"></i> View Submission Stats</a>
        <a href="meetings.php" class="menu-item"><i class="fas fa-calendar-alt"></i> Create Meeting</a>
        <a href="../logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="overlay" id="menuOverlay"></div>

    <div class="content">
        <h2>Your Dashboard Overview</h2>

        <div class="stat-cards-grid">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-book"></i></div>
                <div class="number"><?= $unit_count ?></div>
                <div class="label">Units Taught</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="number"><?= $total_assignments ?></div>
                <div class="label">Total Assignments</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="number"><?= $active_assignments ?></div>
                <div class="label">Active Assignments</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-inbox"></i></div>
                <div class="number"><?= $pending_submissions ?></div>
                <div class="label">Pending Submissions</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-container">
                <h3>Assignment Status</h3>
                <div class="chart-placeholder">Bar Chart Placeholder</div>
            </div>
            <div class="chart-container">
                <h3>Submission Rate by Unit</h3>
                <div class="chart-placeholder">Stacked Bar Chart Placeholder</div>
            </div>
            <div class="chart-container">
                <h3>Recent Notes Engagement</h3>
                <div class="chart-placeholder">Line Chart Placeholder</div>
            </div>
        </div>

        <div class="recent-activity-section">
            <h3>Recent Submissions</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Unit</th>
                            <th>Assignment</th>
                            <th>Submitted On</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT s.file_path, st.name AS student, u.name AS unit, a.title AS assignment_title, s.submitted_at, s.marks
                            FROM submissions s
                            JOIN students st ON s.student_id = st.id
                            JOIN assignments a ON s.assignment_id = a.id
                            JOIN units u ON a.unit_id = u.id
                            JOIN lecturer_units lu ON lu.unit_id = u.id
                            WHERE lu.lecturer_id = ?
                            ORDER BY s.submitted_at DESC
                            LIMIT 4
                        ");
                        $stmt->bind_param("i", $lecturer_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                $status = $row['marks'] !== null ? '<span style="color: green;">Graded</span>' : '<span style="color: orange;">Pending Grade</span>';
                                $action_text = $row['marks'] !== null ? 'View marks' : 'Download';
                                $action_url = $row['marks'] !== null ? '#' : '../assets/uploads/submissions/' . htmlspecialchars($row['file_path']);
                                $onclick = $row['marks'] !== null ? "alert('marks for {$row['student']} not implemented')" : '';
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['student']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['unit']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['assignment_title']) . "</td>";
                                echo "<td>" . date("Y-m-d", strtotime($row['submitted_at'])) . "</td>";
                                echo "<td>$status</td>";
                                echo "<td><a href='$action_url' class='action-link' " . ($onclick ? "onclick=\"$onclick\"" : "target='_blank'") . ">$action_text</a></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No submissions yet.</td></tr>";
                        }
                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            </div>

            <h3>Upcoming Assignments</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Assignment Title</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT a.id, a.title, a.deadline, u.name AS unit
                            FROM assignments a
                            JOIN units u ON a.unit_id = u.id
                            JOIN lecturer_units lu ON u.id = lu.unit_id
                            WHERE lu.lecturer_id = ? AND a.deadline > NOW()
                            ORDER BY a.deadline ASC
                            LIMIT 4
                        ");
                        $stmt->bind_param("i", $lecturer_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['unit']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                                echo "<td>" . date("Y-m-d H:i", strtotime($row['deadline'])) . "</td>";
                                echo "<td><span style='color: blue;'>Active</span></td>";
                                echo "<td><a href='#' class='action-link' onclick=\"alert('Edit assignment not implemented')\">Edit</a></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No upcoming assignments.</td></tr>";
                        }
                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="action-grid">
            <div class="action-card" onclick="showModal('uploadModal')">
                <div class="icon"><i class="fas fa-upload"></i></div>
                <h3>Upload Notes</h3>
                <p>Share lecture materials with your students.</p>
            </div>
            <div class="action-card" onclick="showModal('assignmentModal')">
                <div class="icon"><i class="fas fa-edit"></i></div>
                <h3>Create Assignment</h3>
                <p>Set new tasks and projects for your units.</p>
            </div>
            <div class="action-card" onclick="showModal('addUnitModal')">
                <div class="icon"><i class="fas fa-plus-circle"></i></div>
                <h3>Add New Unit</h3>
                <p>Register a new unit you are teaching.</p>
            </div>
            <div class="action-card" onclick="showModal('submissionModal')">
                <div class="icon"><i class="fas fa-inbox"></i></div>
                <h3>View All Submissions</h3>
                <p>Access all student submissions for review.</p>
            </div>
        </div>
    </div>

    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('uploadModal')">×</span>
            <h3>Upload Notes</h3>
            <form action="../actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_notes">
                <label for="uploadUnit">Unit:</label>
                <select name="unit_id" id="uploadUnit" required>
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="notesFile">Upload File:</label>
                <input type="file" name="notes_file" id="notesFile" required>
                <button type="submit">Upload</button>
            </form>
        </div>
    </div>

    <div id="viewNotesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('viewNotesModal')">×</span>
            <h3>Uploaded Notes</h3>
            <ul>
                <?php
                $stmt = $conn->prepare("
                    SELECT n.file_path, u.name AS unit, n.uploaded_at
                    FROM notes n
                    JOIN units u ON n.unit_id = u.id
                    JOIN lecturer_units lu ON lu.unit_id = u.id
                    WHERE lu.lecturer_id = ?
                    ORDER BY n.uploaded_at DESC
                ");
                $stmt->bind_param("i", $lecturer_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    while ($note = $res->fetch_assoc()) {
                        echo "<li>";
                        echo "<span><strong>" . htmlspecialchars($note['unit']) . "</strong>: " . basename(htmlspecialchars($note['file_path'])) . " (Uploaded: " . date("M d, Y", strtotime($note['uploaded_at'])) . ")</span>";
                        echo "<a href='../assets/uploads/" . htmlspecialchars($note['file_path']) . "' target='_blank'><i class='fas fa-eye'></i> View</a>";
                        echo "</li>";
                    }
                } else {
                    echo "<li>No notes uploaded yet.</li>";
                }
                $stmt->close();
                ?>
            </ul>
        </div>
    </div>

    <div id="assignmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('assignmentModal')">×</span>
            <h3>Create Assignment</h3>
            <form action="../actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_assignment">
                <label for="assignmentUnit">Unit:</label>
                <select name="unit_id" id="assignmentUnit" required>
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="assignmentTitle">Assignment Title:</label>
                <input type="text" name="title" id="assignmentTitle" required>
                <label for="instructions">Instructions:</label>
                <textarea name="instructions" id="instructions" required></textarea>
                <label for="dueDate">Deadline:</label>
                <input type="datetime-local" name="due_date" id="dueDate" required>
                <label for="assignmentFile">Attach File (optional):</label>
                <input type="file" name="assignment_file" id="assignmentFile">
                <button type="submit">Create Assignment</button>
            </form>
        </div>
    </div>

    <div id="submissionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('submissionModal')">×</span>
            <h3>Student Submissions</h3>
            <ul>
                <?php
                $stmt = $conn->prepare("
                    SELECT s.file_path, st.name AS student, u.name AS unit, a.title AS assignment_title, s.submitted_at
                    FROM submissions s
                    JOIN students st ON s.student_id = st.id
                    JOIN assignments a ON s.assignment_id = a.id
                    JOIN units u ON a.unit_id = u.id
                    JOIN lecturer_units lu ON lu.unit_id = u.id
                    WHERE lu.lecturer_id = ?
                    ORDER BY s.submitted_at DESC
                ");
                $stmt->bind_param("i", $lecturer_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        echo "<li>";
                        echo "<span><strong>" . htmlspecialchars($row['student']) . "</strong> - " .
                             htmlspecialchars($row['unit']) . " (Assignment: " . htmlspecialchars($row['assignment_title']) . ")</span>";
                        echo "<a href='../assets/uploads/submissions/" .
                             htmlspecialchars($row['file_path']) . "' target='_blank'><i class='fas fa-download'></i> Download</a>";
                        echo "</li>";
                    }
                } else {
                    echo "<li>No submissions yet.</li>";
                }
                $stmt->close();
                ?>
            </ul>
        </div>
    </div>

    <div id="addUnitModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('addUnitModal')">×</span>
            <h3>Add Unit You Teach</h3>
            <form action="../actions.php" method="POST">
                <input type="hidden" name="action" value="add_single_lecturer_unit">
                <label for="courseSelect">Select Course:</label>
                <select name="course_id" id="courseSelect" required>
                    <option value="">-- Select Course --</option>
                    <?php
                    $courseRes = $conn->query("SELECT id, name FROM courses");
                    while ($course = $courseRes->fetch_assoc()) {
                        echo "<option value='{$course['id']}'>" . htmlspecialchars($course['name']) . "</option>";
                    }
                    ?>
                </select>
                <label for="unitSelect">Select Unit:</label>
                <select name="unit_id" id="unitSelect" required>
                    <option value="">-- Select Unit --</option>
                </select>
                <button type="submit">Add Unit</button>
            </form>
        </div>
    </div>

    <script>
        // Off-Canvas Menu Logic
        const hamburgerBtn = document.getElementById('hamburgerMenu');
        const closeMenuBtn = document.getElementById('closeMenuBtn');
        const offCanvasMenu = document.getElementById('offCanvasMenu');
        const menuOverlay = document.getElementById('menuOverlay');

        function toggleOffCanvasMenu() {
            offCanvasMenu.classList.toggle('active');
            menuOverlay.classList.toggle('active');
        }

        hamburgerBtn.addEventListener('click', toggleOffCanvasMenu);
        closeMenuBtn.addEventListener('click', toggleOffCanvasMenu);
        menuOverlay.addEventListener('click', toggleOffCanvasMenu);

        // Modal Logic
        function showModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('active');
            }
        }

        function hideModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('active');
            }
        }

        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal.active');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // Dynamic Unit Loading for Add Unit Modal
        document.getElementById('courseSelect').addEventListener('change', function () {
            const courseId = this.value;
            const unitSelect = document.getElementById('unitSelect');
            unitSelect.innerHTML = '<option value="">Loading...</option>';

            if (!courseId) {
                unitSelect.innerHTML = '<option value="">-- Select Unit --</option>';
                return;
            }

            fetch(`../load_units.php?course_id=${courseId}`)
                .then(response => response.json())
                .then(data => {
                    unitSelect.innerHTML = '<option value="">-- Select Unit --</option>';
                    if (data.length > 0) {
                        data.forEach(unit => {
                            const option = document.createElement('option');
                            option.value = unit.id;
                            option.textContent = unit.name;
                            unitSelect.appendChild(option);
                        });
                    } else {
                        unitSelect.innerHTML = '<option value="">No units found for this course</option>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching units:', error);
                    unitSelect.innerHTML = '<option value="">Error loading units</option>';
                });
        });
    </script>
</body>
</html>