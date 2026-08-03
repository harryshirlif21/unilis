<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 60); // 60 seconds timeout
ini_set('memory_limit', '256M'); // Increase memory limit

session_start();
require_once '../config/db.php';

// Check if lecturer is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    error_log("Session check failed: user_id=" . ($_SESSION['user_id'] ?? 'unset') . ", user_role=" . ($_SESSION['user_role'] ?? 'unset'));
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Fetch only units assigned to this lecturer
$units = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name 
    FROM units u
    INNER JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE lu.lecturer_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to prepare units query: " . $conn->error);
}

// Fetch short courses assigned to this lecturer
$short_courses = [];
$checkPublicCourses = $conn->query("SHOW TABLES LIKE 'public_courses'");
$checkSCTutorsTable = $conn->query("SHOW TABLES LIKE 'short_course_tutors'");
if ($checkPublicCourses && $checkPublicCourses->num_rows > 0 && $checkSCTutorsTable && $checkSCTutorsTable->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT pc.id, pc.title as name, pc.summary as description, pc.cover_image as banner
        FROM public_courses pc
        INNER JOIN short_course_tutors sct ON pc.id = sct.short_course_id
        WHERE sct.lecturer_id = ? AND sct.is_active = 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $short_courses[] = $row;
        }
        $stmt->close();
    }
}

// Get units where lecturer is supervising teams
$supervisedUnits = [];
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            u.id as unit_id,
            u.code as unit_code,
            u.name as unit_name,
            COUNT(DISTINCT ts.team_id) as team_count
        FROM team_supervisors tsup
        JOIN teams t ON tsup.team_id = t.id
        JOIN units u ON t.unit_id = u.id
        WHERE tsup.lecturer_id = ? 
          AND tsup.supervisor_type = 'lecturer'
          AND tsup.status = 'approved'
        GROUP BY u.id, u.code, u.name
        ORDER BY u.code ASC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $supervisedUnits[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching supervised units: " . $e->getMessage());
}

$supervisedTeams = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            t.id as team_id,
            t.title as team_title,
            u.code as unit_code,
            u.name as unit_name,
            t.status,
            COUNT(DISTINCT tm.student_id) as member_count
        FROM team_supervisors tsup
        JOIN teams t ON tsup.team_id = t.id
        JOIN units u ON t.unit_id = u.id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        WHERE tsup.lecturer_id = ?
          AND tsup.supervisor_type = 'lecturer'
          AND tsup.status = 'approved'
        GROUP BY t.id, t.title, u.code, u.name, t.status
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $supervisedTeams[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching supervised teams: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - UNILIS</title>
    <link rel="stylesheet" href="css/lecturer_dashboard.css">
    <style>
        .supervised-section { margin-top: 24px; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .supervised-section h3 { margin-bottom: 12px; color: #374151; }
        .supervised-list { display: grid; gap: 12px; }
        .supervised-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; background: #f9fafb; }
        .supervised-card a { color: #0369a1; text-decoration: none; font-weight: 600; }
        .supervised-meta { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; color: #fff; margin-right: 8px; }
        .badge.active { background: #10b981; }
        .badge.locked { background: #f59e0b; }
        .badge.archived { background: #6b7280; }
    </style>
</head>

<body>

    <div class="sidebar">
        <h2><?= htmlspecialchars($lecturer_name) ?></h2>
        <p>Lecturer - UNILIS</p>
        <button onclick="showModal('uploadModal')">Upload Notes</button>
        <button onclick="showModal('viewNotesModal')">View Notes</button>
        <button onclick="showModal('assignmentModal')">Create Assignment</button>
        <button onclick="window.location.href='assignment_submissions.php'">Assignment Submissions</button>
        <button onclick="window.location.href='submissions.php'">Assessment Submissions</button>
        <button onclick="showModal('shortCoursesModal')">Assigned Short Courses</button>
        <button onclick="window.location.href='../learn/'">Public Courses</button>
        <button onclick="window.location.href='../teams/views/lecturer_teams.php'">Teams</button>
        <button onclick="window.location.href='supervisor_units.php'">Supervised Units</button>
        <button onclick="window.location.href='../logout.php'">Logout</button>
    </div>

    <div class="content">
        <h2>Welcome, <?= htmlspecialchars($lecturer_name) ?>!</h2>
        <p>Use the buttons on the left to manage notes and assignments.</p>

        <div class="supervised-section">
            <h3>Supervised Teams</h3>
            <?php if (empty($supervisedTeams)): ?>
                <p style="color:#6b7280;">You are not currently supervising any teams.</p>
            <?php else: ?>
                <div class="supervised-list">
                    <?php foreach ($supervisedTeams as $team): ?>
                        <div class="supervised-card">
                            <a href="../teams/views/manage_team.php?team_id=<?= (int)$team['team_id'] ?>">
                                <?= htmlspecialchars($team['team_title']) ?>
                            </a>
                            <div class="supervised-meta">
                                <span class="badge <?= htmlspecialchars($team['status']) ?>"><?= ucfirst(htmlspecialchars($team['status'])) ?></span>
                                <?= htmlspecialchars($team['unit_code']) ?> · <?= htmlspecialchars($team['unit_name']) ?> · <?= (int)$team['member_count'] ?> member<?= (int)$team['member_count'] === 1 ? '' : 's' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload Notes Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('uploadModal')">×</span>
            <h3>Upload Notes</h3>
            <form action="../actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_notes">
                <label>Unit:</label>
                <select name="unit_id" required>
                    <option value="">-- Select Unit --</option>
                    <?php
                    foreach ($units as $unit) {
                        echo "<option value='{$unit['id']}'>" . htmlspecialchars($unit['name']) . "</option>";
                    }
                    ?>
                </select>
                <label>Upload File:</label>
               <input type="file" name="notes_file[]" multiple required>

                <button type="submit">Upload</button>
            </form>
        </div>
    </div>

    <!-- View Notes Modal -->
    <div id="viewNotesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('viewNotesModal')">×</span>
            <h3>Uploaded Notes</h3>
            <ul>
                <?php
                $stmt = $conn->prepare("
                SELECT notes.file_path, units.name AS unit 
                FROM notes 
                JOIN units ON notes.unit_id = units.id 
                JOIN lecturer_units lu ON lu.unit_id = units.id
                WHERE lu.lecturer_id = ?
            ");
                if ($stmt) {
                    $stmt->bind_param("i", $lecturer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $file = htmlspecialchars($row['file_path']);
                        $unit = htmlspecialchars($row['unit']);
                        echo "<li><strong>$unit</strong>: <a href='../assets/uploads/$file' target='_blank'>View</a></li>";
                    }
                    $stmt->close();
                }
                ?>
            </ul>
        </div>
    </div>

    <!-- Assignment Modal -->
    <div id="assignmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('assignmentModal')">×</span>
            <h3>Create Assignment</h3>
            <form action="../actions.php" method="POST">
                <input type="hidden" name="action" value="create_assignment">
                <label>Unit:</label>
                <select name="unit_id" required>
                    <option value="">-- Select Unit --</option>
                    <?php
                    foreach ($units as $unit) {
                        echo "<option value='{$unit['id']}'>" . htmlspecialchars($unit['name']) . "</option>";
                    }
                    ?>
                </select>
                <label>Instructions:</label>
                <textarea name="instructions" required></textarea>
                <label>Due Date:</label>
                <input type="date" name="due_date" required>
                <button type="submit">Create Assignment</button>
            </form>
        </div>
    </div>

    <!-- View Submissions Modal -->
    <div id="submissionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('submissionModal')">×</span>
            <h3>Student Submissions</h3>
            <ul>
                <?php
                $stmt = $conn->prepare("
                SELECT s.file_path, st.name AS student, u.name AS unit 
                FROM submissions s 
                JOIN students st ON s.student_id = st.id 
                JOIN assignments a ON s.assignment_id = a.id 
                JOIN units u ON a.unit_id = u.id 
                JOIN lecturer_units lu ON u.id = lu.unit_id
                WHERE lu.lecturer_id = ?
            ");
                if ($stmt) {
                    $stmt->bind_param("i", $lecturer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $student = htmlspecialchars($row['student']);
                        $unit = htmlspecialchars($row['unit']);
                        $file = htmlspecialchars($row['file_path']);
                        echo "<li><strong>$student</strong> - $unit: <a href='../assets/uploads/submissions/$file' target='_blank'>Download</a></li>";
                    }
                    $stmt->close();
                }
                ?>
            </ul>
        </div>
    </div>

    <!-- Short Courses Modal -->
    <div id="shortCoursesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('shortCoursesModal')">×</span>
            <h3>Assigned Short Courses</h3>
            <?php if (empty($short_courses)): ?>
                <p>No short courses assigned to you yet.</p>
            <?php else: ?>
                <div class="short-courses-list">
                    <?php foreach ($short_courses as $sc): ?>
                        <div class="short-course-item">
                            <h4><?= htmlspecialchars($sc['name']) ?></h4>
                            <?php if ($sc['description']): ?>
                                <p><?= htmlspecialchars($sc['description']) ?></p>
                            <?php endif; ?>
                            <?php if ($sc['banner']): ?>
                                <p><strong>Banner:</strong> <a href="../<?= htmlspecialchars($sc['banner']) ?>" target="_blank">View</a></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.style.display = 'block';
        }

        function hideModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.style.display = 'none';
        }
    </script>

</body>

</html>
