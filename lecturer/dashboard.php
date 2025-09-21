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
try {
    $stmt = $conn->prepare("SELECT u.id, u.name FROM units u JOIN lecturer_units lu ON u.id = lu.unit_id WHERE lu.lecturer_id = ?");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching units: " . $e->getMessage());
    $units = [];
}

// Fetch stats for dashboard
$unit_count = count($units);
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM assignments a JOIN lecturer_units lu ON a.unit_id = lu.unit_id WHERE lu.lecturer_id = ?");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $total_assignments = $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM assignments a JOIN lecturer_units lu ON a.unit_id = lu.unit_id WHERE lu.lecturer_id = ? AND a.deadline > NOW()");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $active_assignments = $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM submissions s JOIN assignments a ON s.assignment_id = a.id JOIN lecturer_units lu ON a.unit_id = lu.unit_id WHERE lu.lecturer_id = ? AND s.marks IS NULL");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $pending_submissions = $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    // Fetch assignment statistics per unit
    $stmt = $conn->prepare("
        SELECT 
            u.name as unit_name,
            COUNT(a.id) as total_assignments,
            COUNT(DISTINCT s.id) as total_submissions
        FROM units u
        JOIN lecturer_units lu ON u.id = lu.unit_id
        LEFT JOIN assignments a ON u.id = a.unit_id
        LEFT JOIN submissions s ON a.id = s.assignment_id
        WHERE lu.lecturer_id = ?
        GROUP BY u.id, u.name
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $assignment_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch submission rate over time
    $stmt = $conn->prepare("
        SELECT 
            u.name as unit_name,
            DATE(s.submitted_at) as submission_date,
            COUNT(s.id) as submission_count
        FROM units u
        JOIN lecturer_units lu ON u.id = lu.unit_id
        JOIN assignments a ON u.id = a.unit_id
        JOIN submissions s ON a.id = s.assignment_id
        WHERE lu.lecturer_id = ?
        AND s.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY u.id, u.name, DATE(s.submitted_at)
        ORDER BY submission_date
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $submission_trends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $total_assignments = $active_assignments = $pending_submissions = 0;
    $assignment_stats = [];
    $submission_trends = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/dashboard.css">
</head>

<body>
    <header class="header">
        <h1>UNILIS Lecturer Dashboard</h1>
        <div class="lecturer-info">Welcome, <?= htmlspecialchars($lecturer_name) ?></div>
        <button class="hamburger-menu" id="hamburgerMenu"><i class="fas fa-bars"></i></button>
    </header>

    <div class="off-canvas-menu" id="offCanvasMenu">
        <div class="menu-content">
            <button class="close-btn" id="closeMenuBtn">×</button>
            <h2><?= htmlspecialchars($lecturer_name) ?></h2>
            <p>Lecturer - UNILIS</p>

            <button class="menu-item" onclick="showModal('uploadModal')"><i class="fas fa-upload"></i> Upload Notes</button>
            <button class="menu-item" onclick="showModal('viewNotesModal')"><i class="fas fa-file-alt"></i> View Notes</button>

            <!-- 🔽 Dropdown for Interactive Assignments -->
            <!-- 🔽 Dropdown for Interactive Assignments -->
            <div class="menu-item dropdown">
                <button type="button" class="dropdown-btn">
                    <i class="fas fa-edit"></i> Interactive Assignments <i class="fas fa-caret-down"></i>
                </button>
                <div class="dropdown-content">
                    <a href="create_questions.php"><i class="fas fa-plus"></i> Create Assignment</a>
                    <a href="scores_overview.php"><i class="fas fa-chart-line"></i> View Student Scores</a>
                    <a href="assignment_submissions.php"><i class="fas fa-inbox"></i> View Submissions</a>
                    <a href="submission_stats.php"><i class="fas fa-chart-bar"></i> Submission Stats</a>
                    <a href="AIGrading.php"><i class="fas fa-robot"></i> AI Grading</a>
                </div>
            </div>
            <!-- 🔼 End Dropdown -->

            <!-- 🔼 End Dropdown -->

            <button class="menu-item" onclick="showModal('submissionModal')"><i class="fas fa-inbox"></i> View Submissions</button>
            <button class="menu-item" onclick="showModal('addUnitModal')"><i class="fas fa-plus-circle"></i> Add My Units</button>
            <a href="assignment_submissions.php" class="menu-item"><i class="fas fa-chart-bar"></i> View Submission Stats</a>
            <a href="meetings.php" class="menu-item"><i class="fas fa-calendar-alt"></i> Create Meeting</a>
            <a href="../logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
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
                <h3>Assignment Status per Unit</h3>
                <canvas id="assignmentStatusChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Submission Rate Trends (Last 30 Days)</h3>
                <canvas id="submissionRateChart"></canvas>
            </div>
        </div>

        <script>
            // Assignment Status Chart
            const assignmentStats = <?= json_encode($assignment_stats) ?>;

            new Chart(document.getElementById('assignmentStatusChart'), {
                type: 'bar',
                data: {
                    labels: assignmentStats.map(stat => stat.unit_name),
                    datasets: [{
                        label: 'Total Assignments',
                        data: assignmentStats.map(stat => stat.total_assignments),
                        backgroundColor: 'rgba(52, 152, 219, 0.6)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Submissions Received',
                        data: assignmentStats.map(stat => stat.total_submissions),
                        backgroundColor: 'rgba(46, 204, 113, 0.6)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Assignments vs Submissions per Unit'
                        }
                    }
                }
            });

            // Submission Rate Chart
            const submissionTrends = <?= json_encode($submission_trends) ?>;
            const uniqueUnits = [...new Set(submissionTrends.map(trend => trend.unit_name))];
            const uniqueDates = [...new Set(submissionTrends.map(trend => trend.submission_date))];

            const datasets = uniqueUnits.map(unit => {
                const color = `hsl(${Math.random() * 360}, 70%, 50%)`;
                return {
                    label: unit,
                    data: uniqueDates.map(date => {
                        const match = submissionTrends.find(trend =>
                            trend.unit_name === unit && trend.submission_date === date
                        );
                        return match ? match.submission_count : 0;
                    }),
                    borderColor: color,
                    fill: false,
                    tension: 0.4
                };
            });

            new Chart(document.getElementById('submissionRateChart'), {
                type: 'line',
                data: {
                    labels: uniqueDates,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Daily Submission Trends'
                        }
                    }
                }
            });
        </script>

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
                        try {
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
                        } catch (mysqli_sql_exception $e) {
                            echo "<tr><td colspan='6'>Error loading submissions: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                            error_log("Database error in Recent Submissions: " . $e->getMessage());
                        }
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
                        try {
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
                        } catch (mysqli_sql_exception $e) {
                            echo "<tr><td colspan='5'>Error loading assignments: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                            error_log("Database error in Upcoming Assignments: " . $e->getMessage());
                        }
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
                <label for="notesFile">Upload Files:</label>
                <input type="file" name="notes_file[]" id="notesFile" required multiple accept=".pdf,.doc,.docx,.ppt,.pptx">
                <small style="color: #666; margin-top: 5px;">You can select multiple files. Accepted formats: PDF, DOC, DOCX, PPT, PPTX</small>
                <button type="submit">Upload Files</button>
            </form>
        </div>
    </div>

    <div id="viewNotesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('viewNotesModal')">×</span>
            <h3>Uploaded Notes</h3>
            <ul>
                <?php
                try {
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
                } catch (mysqli_sql_exception $e) {
                    echo "<li>Error loading notes: " . htmlspecialchars($e->getMessage()) . "</li>";
                    error_log("Database error in View Notes: " . $e->getMessage());
                }
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
                <label for="assignmentMode">Exam/Assignment Mode:</label>
                <select name="mode" id="assignmentMode" required onchange="handleModeChange()">
                    <option value="text">Text (Written Answers)</option>
                    <option value="speech">Speech (Spoken Answers)</option>
                    <option value="hybrid">Hybrid (Student's Choice)</option>
                </select>
                <div id="speechOptions" style="display: none;">
                    <label for="voiceInstructions">Voice Instructions (Optional):</label>
                    <div class="voice-recorder">
                        <button type="button" id="recordButton" onclick="toggleRecording()">
                            <i class="fas fa-microphone"></i> Record Instructions
                        </button>
                        <span id="recordingStatus"></span>
                        <audio id="audioPreview" controls style="display: none;"></audio>
                        <input type="hidden" name="voice_instructions" id="voiceInstructions">
                    </div>
                    <label for="rubric">Grading Rubric for Speech:</label>
                    <textarea name="rubric" id="rubric" placeholder="Enter criteria for speech evaluation (e.g., pronunciation, fluency, content accuracy)"></textarea>
                </div>
                <label for="instructions">Written Instructions:</label>
                <textarea name="instructions" id="instructions" required></textarea>
                <label for="due_date">Deadline:</label>
                <input type="datetime-local" name="due_date" id="dueDate" required>
                <label for="assignmentFile">Attach File (optional):</label>
                <input type="file" name="assignment_file" id="assignmentFile">
                <button type="submit">Create Assignment</button>

                <script>
                    function handleModeChange() {
                        const mode = document.getElementById('assignmentMode').value;
                        const speechOptions = document.getElementById('speechOptions');
                        speechOptions.style.display = (mode === 'text' ? 'none' : 'block');
                    }

                    let mediaRecorder;
                    let audioChunks = [];
                    let isRecording = false;

                    async function toggleRecording() {
                        const recordButton = document.getElementById('recordButton');
                        const recordingStatus = document.getElementById('recordingStatus');
                        const audioPreview = document.getElementById('audioPreview');

                        if (!isRecording) {
                            try {
                                const stream = await navigator.mediaDevices.getUserMedia({
                                    audio: true
                                });
                                mediaRecorder = new MediaRecorder(stream);
                                audioChunks = [];

                                mediaRecorder.ondataavailable = (event) => {
                                    audioChunks.push(event.data);
                                };

                                mediaRecorder.onstop = () => {
                                    const audioBlob = new Blob(audioChunks, {
                                        type: 'audio/wav'
                                    });
                                    const audioUrl = URL.createObjectURL(audioBlob);
                                    audioPreview.src = audioUrl;
                                    audioPreview.style.display = 'block';

                                    // Convert to base64 for storage
                                    const reader = new FileReader();
                                    reader.readAsDataURL(audioBlob);
                                    reader.onloadend = () => {
                                        document.getElementById('voiceInstructions').value = reader.result;
                                    };
                                };

                                mediaRecorder.start();
                                isRecording = true;
                                recordButton.innerHTML = '<i class="fas fa-stop"></i> Stop Recording';
                                recordingStatus.textContent = 'Recording...';
                            } catch (err) {
                                console.error('Error accessing microphone:', err);
                                alert('Could not access microphone. Please check permissions.');
                            }
                        } else {
                            mediaRecorder.stop();
                            isRecording = false;
                            recordButton.innerHTML = '<i class="fas fa-microphone"></i> Record Instructions';
                            recordingStatus.textContent = 'Recording saved';
                        }
                    }
                </script>
            </form>
        </div>
    </div>

    <div id="submissionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('submissionModal')">×</span>
            <h3>Student Submissions</h3>
            <ul>
                <?php
                try {
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
                } catch (mysqli_sql_exception $e) {
                    echo "<li>Error loading submissions: " . htmlspecialchars($e->getMessage()) . "</li>";
                    error_log("Database error in View Submissions: " . $e->getMessage());
                }
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
                    try {
                        $courseRes = $conn->query("SELECT id, name FROM courses");
                        while ($course = $courseRes->fetch_assoc()) {
                            echo "<option value='{$course['id']}'>" . htmlspecialchars($course['name']) . "</option>";
                        }
                    } catch (mysqli_sql_exception $e) {
                        echo "<option value=''>Error loading courses</option>";
                        error_log("Database error in Course Select: " . $e->getMessage());
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
        document.getElementById('courseSelect').addEventListener('change', function() {
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
        document.querySelectorAll('.dropdown-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.parentElement.classList.toggle('active');
            });
        });
    </script>
</body>

</html>