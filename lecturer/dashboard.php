<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';

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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
     <link rel="stylesheet" href="css/dashboard.css">
    
</head>
<body class="relative">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-md p-4 flex justify-between items-center text-slate-800 sticky top-0 z-30 border-b border-f5e6b2">
        <a href="#" class="text-2xl font-bold text-92400e">UNILIS</a>
        <div class="hidden md:flex space-x-8 items-center text-92400e">
            <a href="#" class="nav-link active font-medium px-3 py-2 rounded-lg" data-target="dashboard-content">Dashboard</a>
            <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg" data-target="assignments-content">Assignments</a>
            <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg" data-target="submissions-content">Submissions</a>
            <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg" data-target="notes-content">Notes</a>
            <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg" data-target="meetings-content">Meetings</a>
        
    </div>
        </div>
        <button id="menu-toggle" class="p-2 rounded-full text-92400e hover:bg-fef3c7 focus:outline-none focus:ring-2 focus:ring-f59e0b transition-all">
            <svg class="hamburger-icon-menu h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </nav>

    <!-- Off-Canvas Sidebar -->
    <div id="offCanvasMenu" class="sidebar">
        <button id="closeMenuBtn" class="close-btn">&times;</button>
        <h2 class="text-2xl font-bold mb-2 text-center text-92400e"><?= htmlspecialchars($lecturer_name) ?></h2>
        <p class="text-base mb-6 text-center text-a16207">Lecturer - UNILIS</p>
        <button class="menu-item" data-target="dashboard-content"><i class="fas fa-tachometer-alt"></i> Dashboard</button>
        <div class="menu-item dropdown">
            <button class="dropdown-btn"><i class="fas fa-edit"></i> Interactive Assignments <i class="fas fa-caret-down"></i></button>
            <div class="dropdown-content">
                <a href="create_questions.php"><i class="fas fa-plus"></i> Create Assignment</a>
                <a href="scores_overview.php"><i class="fas fa-chart-line"></i> View Student Scores</a>
                <a href="submission_stats.php"><i class="fas fa-chart-bar"></i> Submission Stats</a>
                <a href="AIGrading.php"><i class="fas fa-robot"></i> AI Grading</a>
            </div>
        </div>
       <a  class="menu-item" href="upload_notes.php">Upload interactive Notes</a>
        <a class="menu-item" href="assignment_submissions.php">
    <i class="fas fa-inbox"></i> View Submissions
</a>

        <button class="menu-item" onclick="showModal('uploadModal')"><i class="fas fa-upload"></i> Upload Notes</button>
        <button class="menu-item" data-target="notes-content"><i class="fas fa-file-alt"></i> View Notes</button>
        <a href="meetings.php" class="menu-item"><i class="fas fa-calendar-alt"></i> Create Meeting</a>
        <button class="menu-item" onclick="showModal('attendanceModal')"><i class="fas fa-upload"></i> Take Attendance</button>
        <button class="menu-item" onclick="showModal('addUnitModal')"><i class="fas fa-plus-circle"></i> Add Unit</button>
        <a href="../logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Overlay -->
    <div id="overlay" class="overlay"></div>

    <!-- Main Content Area -->
    <div class="p-6 md:p-10">
        <!-- Hero Section -->
        <div class="relative bg-cover bg-center h-[60vh] mb-8 md:mb-12 rounded-2xl overflow-hidden" style="background-image: url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');">
            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                <h1 class="text-3xl md:text-4xl font-extrabold hero-text text-center px-4">
                    Welcome to UNILIS, <?= htmlspecialchars($lecturer_name) ?>
                </h1>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div id="dashboard-content">
            <!-- Stats Section -->
            <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card bg-white rounded-2xl p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-amber-100 text-amber-700 stat-icon">
                            <i class="fas fa-book-open h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm stat-text-primary">Units Taught</p>
                            <h2 class="text-3xl font-bold stat-text-secondary"><?= $unit_count ?></h2>
                        </div>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-700 stat-icon">
                            <i class="fas fa-clipboard-list h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm stat-text-secondary">Total Assignments</p>
                            <h2 class="text-3xl font-bold stat-text-accent"><?= $total_assignments ?></h2>
                        </div>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-700 stat-icon">
                            <i class="fas fa-hourglass-half h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm stat-text-accent">Active Assignments</p>
                            <h2 class="text-3xl font-bold stat-text-primary"><?= $active_assignments ?></h2>
                        </div>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-amber-200 text-amber-800 stat-icon">
                            <i class="fas fa-inbox h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm stat-text-primary">Pending Submissions</p>
                            <h2 class="text-3xl font-bold stat-text-secondary"><?= $pending_submissions ?></h2>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="card bg-white rounded-2xl p-6">
                    <h3 class="text-xl font-semibold mb-4 stat-text-secondary">Assignment Status per Unit</h3>
                    <div class="chart-container">
                        <canvas id="assignmentStatusChart"></canvas>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6">
                    <h3 class="text-xl font-semibold mb-4 stat-text-secondary">Submission Rate Trends</h3>
                    <div class="chart-container">
                        <canvas id="submissionRateChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Recent Submissions</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Student</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Assignment</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Submitted On</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Status</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
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
                                        $status = $row['marks'] !== null ? '<span class="text-green-600">Graded</span>' : '<span class="text-orange-600">Pending</span>';
                                        $action_text = $row['marks'] !== null ? 'View Marks' : 'Download';
                                        $action_url = $row['marks'] !== null ? '#' : '../assets/uploads/submissions/' . htmlspecialchars($row['file_path']);
                                        $onclick = $row['marks'] !== null ? "alert('View marks not implemented')" : '';
                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>";
                                        echo "<td class='py-4 table-text-primary'>" . htmlspecialchars($row['student']) . "</td>";
                                        echo "<td class='py-4 table-text-secondary'>" . htmlspecialchars($row['unit']) . "</td>";
                                        echo "<td class='py-4 table-text-accent'>" . htmlspecialchars($row['assignment_title']) . "</td>";
                                        echo "<td class='py-4 text-sm table-text-primary'>" . date("d M Y, h:i A", strtotime($row['submitted_at'])) . "</td>";
                                        echo "<td class='py-4 table-text-secondary'>$status</td>";
                                        echo "<td class='py-4 table-text-accent'><a href='$action_url' class='text-f59e0b hover:underline' " . ($onclick ? "onclick=\"$onclick\"" : "target='_blank'") . ">$action_text</a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='py-4 text-center'>No submissions yet.</td></tr>";
                                }
                                $stmt->close();
                            } catch (mysqli_sql_exception $e) {
                                echo "<tr><td colspan='6' class='py-4 text-center text-red-500'>Error loading submissions.</td></tr>";
                                error_log("Error fetching submissions: " . $e->getMessage());
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Quick Action Cards -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="card bg-white rounded-2xl p-6 text-center hover:shadow-lg" onclick="showModal('uploadModal')">
                    <div class="flex flex-col items-center">
                        <div class="p-4 rounded-full bg-amber-100 mb-4">
                            <i class="fas fa-upload text-amber-600 h-10 w-10 stat-icon"></i>
                        </div>
                        <span class="font-semibold text-lg stat-text-accent">Upload Notes</span>
                        <p class="text-sm text-gray-600 mt-2">Share lecture materials with your students.</p>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6 text-center hover:shadow-lg" onclick="showModal('assignmentModal')">
                    <div class="flex flex-col items-center">
                        <div class="p-4 rounded-full bg-orange-100 mb-4">
                            <i class="fas fa-edit text-orange-600 h-10 w-10 stat-icon"></i>
                        </div>
                        <span class="font-semibold text-lg stat-text-secondary">Create Assignment</span>
                        <p class="text-sm text-gray-600 mt-2">Set new tasks for your units.</p>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6 text-center hover:shadow-lg" onclick="showModal('addUnitModal')">
                    <div class="flex flex-col items-center">
                        <div class="p-4 rounded-full bg-yellow-100 mb-4">
                            <i class="fas fa-plus-circle text-yellow-600 h-10 w-10 stat-icon"></i>
                        </div>
                        <span class="font-semibold text-lg stat-text-accent">Add Unit</span>
                        <p class="text-sm text-gray-600 mt-2">Register a new unit you are teaching.</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Assignments Content -->
        <div id="assignments-content" class="hidden">
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Assignments</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Title</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Deadline</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
                            <?php
                            try {
                                $stmt = $conn->prepare("
                                    SELECT a.id, a.title, a.deadline, u.name AS unit
                                    FROM assignments a
                                    JOIN units u ON a.unit_id = u.id
                                    JOIN lecturer_units lu ON u.id = lu.unit_id
                                    WHERE lu.lecturer_id = ?
                                    ORDER BY a.deadline DESC
                                ");
                                $stmt->bind_param("i", $lecturer_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res->num_rows > 0) {
                                    while ($row = $res->fetch_assoc()) {
                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>";
                                        echo "<td class='py-4 table-text-primary'>" . htmlspecialchars($row['unit']) . "</td>";
                                        echo "<td class='py-4 table-text-secondary'>" . htmlspecialchars($row['title']) . "</td>";
                                        echo "<td class='py-4 text-sm table-text-accent'>" . date("d M Y, h:i A", strtotime($row['deadline'])) . "</td>";
                                        echo "<td class='py-4 table-text-primary'><a href='#' class='text-f59e0b hover:underline' onclick=\"alert('Edit assignment not implemented')\">Edit</a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='py-4 text-center'>No assignments found.</td></tr>";
                                }
                                $stmt->close();
                            } catch (mysqli_sql_exception $e) {
                                echo "<tr><td colspan='4' class='py-4 text-center text-red-500'>Error loading assignments.</td></tr>";
                                error_log("Error fetching assignments: " . $e->getMessage());
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Submissions Content -->
        <div id="submissions-content" class="hidden">
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Student Submissions</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Student</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Assignment</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Submitted On</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Status</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
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
                                ");
                                $stmt->bind_param("i", $lecturer_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res->num_rows > 0) {
                                    while ($row = $res->fetch_assoc()) {
                                        $status = $row['marks'] !== null ? '<span class="text-green-600">Graded</span>' : '<span class="text-orange-600">Pending</span>';
                                        $action_text = $row['marks'] !== null ? 'View Marks' : 'Grade';
                                        $action_url = $row['marks'] !== null ? '#' : '../assets/uploads/submissions/' . htmlspecialchars($row['file_path']);
                                        $onclick = $row['marks'] !== null ? "alert('View marks not implemented')" : '';
                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>";
                                        echo "<td class='py-4 table-text-primary'>" . htmlspecialchars($row['student']) . "</td>";
                                        echo "<td class='py-4 table-text-secondary'>" . htmlspecialchars($row['unit']) . "</td>";
                                        echo "<td class='py-4 table-text-accent'>" . htmlspecialchars($row['assignment_title']) . "</td>";
                                        echo "<td class='py-4 text-sm table-text-primary'>" . date("d M Y, h:i A", strtotime($row['submitted_at'])) . "</td>";
                                        echo "<td class='py-4 table-text-secondary'>$status</td>";
                                        echo "<td class='py-4 table-text-accent'><a href='$action_url' class='text-f59e0b hover:underline' " . ($onclick ? "onclick=\"$onclick\"" : "target='_blank'") . ">$action_text</a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='py-4 text-center'>No submissions yet.</td></tr>";
                                }
                                $stmt->close();
                            } catch (mysqli_sql_exception $e) {
                                echo "<tr><td colspan='6' class='py-4 text-center text-red-500'>Error loading submissions.</td></tr>";
                                error_log("Error fetching submissions: " . $e->getMessage());
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Notes Content -->
        <div id="notes-content" class="hidden">
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Uploaded Notes</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">File</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Uploaded At</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
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
                                        $file = htmlspecialchars($note['file_path']);
                                        $full_path = "../assets/uploads/" . $file;
                                        $fileExists = file_exists($full_path);
                                        $fileDisplay = $file ? $file : '<span style="color:red;">No filename</span>';
                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>";
                                        echo "<td class='py-4 table-text-primary'>" . htmlspecialchars($note['unit']) . "</td>";
                                        echo "<td class='py-4 table-text-secondary'>$fileDisplay</td>";
                                        echo "<td class='py-4 text-sm table-text-accent'>" . date("d M Y, h:i A", strtotime($note['uploaded_at'])) . "</td>";
                                        echo "<td class='py-4 table-text-primary'>";
                                        if ($fileExists) {
                                            echo "<a href='$full_path' target='_blank' class='text-f59e0b hover:underline mr-2'>View</a> | <a href='$full_path' download class='text-f59e0b hover:underline'>Download</a>";
                                        } else {
                                            echo "<span style='color: red;'>File missing</span>";
                                        }
                                        echo "</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='py-4 text-center'>No notes uploaded yet.</td></tr>";
                                }
                                $stmt->close();
                            } catch (mysqli_sql_exception $e) {
                                echo "<tr><td colspan='4' class='py-4 text-center text-red-500'>Error loading notes.</td></tr>";
                                error_log("Error fetching notes: " . $e->getMessage());
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Meetings Content -->
        <div id="meetings-content" class="hidden">
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Upcoming Meetings</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Title</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Scheduled Time</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
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
                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>";
                                        echo "<td class='py-4 table-text-primary'>" . htmlspecialchars($meeting['title']) . "</td>";
                                        echo "<td class='py-4 table-text-secondary'>" . htmlspecialchars($meeting['unit_name']) . "</td>";
                                        echo "<td class='py-4 text-sm table-text-accent'>" . date("d M Y, h:i A", strtotime($meeting['scheduled_time'])) . "</td>";
                                        echo "<td class='py-4 table-text-primary'><a class='text-f59e0b hover:underline' href='meeting_ide.php?meeting_id=" . htmlspecialchars($meeting['id']) . "' target='_blank'>Join Meeting</a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='py-4 text-center'>No meetings scheduled.</td></tr>";
                                }
                                $stmt->close();
                            } catch (mysqli_sql_exception $e) {
                                echo "<tr><td colspan='4' class='py-4 text-center text-red-500'>Error loading meetings.</td></tr>";
                                error_log("Error fetching meetings: " . $e->getMessage());
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Modals -->
        <div id="uploadModal" class="modal">
            <div class="modal-content bg-white p-6 rounded-2xl border border-f5e6b2">
                <span class="close text-92400e text-2xl font-bold cursor-pointer hover:text-f59e0b" onclick="hideModal('uploadModal')">&times;</span>
                <h3 class="text-xl font-semibold stat-text-secondary mb-4">Upload Notes</h3>
                <form action="../actions.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_notes">
                    <label class="block text-sm font-medium stat-text-primary mb-2">Unit:</label>
                    <select name="unit_id" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">
                        <option value="">-- Select Unit --</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Upload Files:</label>
                    <input type="file" name="notes_file[]" required multiple accept=".pdf,.doc,.docx,.ppt,.pptx" class="text-sm text-92400e">
                    <button type="submit" class="btn-primary px-4 py-2 mt-4 rounded-lg">Upload Files</button>
                </form>
            </div>
        </div>
        
      <!-- Attendance Modal - YOUR Exact Style + Unit Selector -->
<div id="attendanceModal" class="modal">
    <div class="modal-content bg-white p-6 rounded-2xl border border-f5e6b2" style="max-width: 520px;">
        <span class="close text-92400e text-3xl font-bold cursor-pointer hover:text-f59e0b float-right" 
              onclick="hideModal('attendanceModal')">&times;</span>

        <h3 class="text-2xl font-bold stat-text-secondary mb-6 text-center">
            Take Attendance
        </h3>

        <form action="lecturer_take_attendance.php" method="POST" id="attendanceForm">

            <!-- Unit Selection -->
            <div class="mb-5">
                <label class="block text-sm font-medium stat-text-primary mb-2">
                    Select Unit <span class="text-red-500">*</span>
                </label>
                <select name="unit_id" id="modalUnitId" required 
                        class="w-full px-4 py-3 border border-f5e6b2 rounded-xl text-92400e text-lg 
                               focus:ring-2 focus:ring-f59e0b focus:border-f59e0b transition">
                    <option value="">-- Choose Unit --</option>
                    <?php
                    $lecturer_id = $_SESSION['user_id'];
                    $units_query = $conn->query("
                        SELECT u.id, u.name 
                        FROM units u 
                        JOIN lecturer_units lu ON u.id = lu.unit_id 
                        WHERE lu.lecturer_id = $lecturer_id 
                        ORDER BY u.name
                    ");
                    while ($unit = $units_query->fetch_assoc()): ?>
                        <option value="<?= $unit['id'] ?>">
                            <?= htmlspecialchars($unit['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Unit Name Preview -->
            <div class="hidden bg-gradient-to-r from-f59e0b to-f59e0b/20 text-white p-4 rounded-xl mb-5 text-center" 
                 id="selectedUnitPreview">
                <p class="text-sm opacity-90">Selected Unit</p>
                <p class="text-xl font-bold" id="selectedUnitName">—</p>
            </div>

            <!-- Duration -->
            <label class="block text-sm font-medium stat-text-primary mb-2">
                Code Valid For:
            </label>
            <select name="duration" required 
                    class="w-full px-4 py-3 border border-f5e6b2 rounded-xl text-92400e text-lg mb-5
                           focus:ring-2 focus:ring-f59e0b focus:border-f59e0b">
                <option value="5">5 minutes</option>
                <option value="10" selected>10 minutes</option>
                <option value="15">15 minutes</option>
                <option value="30">30 minutes</option>
                <option value="60">60 minutes</option>
            </select>

            <!-- Email Notify -->
            <div class="mt-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="send_email" class="w-5 h-5 text-f59e0b rounded focus:ring-f59e0b">
                    <span class="text-base stat-text-primary font-medium">
                        Send code via email too
                    </span>
                </label>
            </div>

            <!-- Generate Code Button -->
            <div class="mt-7 text-center">
                <button type="submit" class="btn-primary px-8 py-4 rounded-xl text-lg font-bold shadow-lg 
                                             hover:shadow-xl transform hover:scale-105 transition">
                    Generate 6-Digit Code
                </button>
            </div>

            <!-- Info -->
            <div class="mt-4 text-center text-sm text-gray-600">
                Students will receive notification instantly
            </div>

          <div class="mt-6 text-center">
    <a id="viewAttendanceRecords"
       href="#"
       class="text-f59e0b font-semibold underline opacity-50 pointer-events-none">
       View Attendance Records
    </a>
</div>

<script>
document.getElementById('modalUnitId').addEventListener('change', function () {
    const unitId = this.value;
    const link = document.getElementById('viewAttendanceRecords');

    if (unitId) {
        link.href = 'lecturer_attendance_report.php?unit=' + unitId;
        link.classList.remove('opacity-50', 'pointer-events-none');
    } else {
        link.href = '#';
        link.classList.add('opacity-50', 'pointer-events-none');
    }

    // Optional: update live preview of selected unit
    const preview = document.getElementById('selectedUnitPreview');
    const name = document.getElementById('selectedUnitName');
    if (unitId) {
        name.textContent = this.options[this.selectedIndex].text;
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
        name.textContent = '—';
    }
});
</script>

            </div>

        </form>
    </div>
</div>



        <div id="assignmentModal" class="modal">
            <div class="modal-content bg-white p-6 rounded-2xl border border-f5e6b2">
                <span class="close text-92400e text-2xl font-bold cursor-pointer hover:text-f59e0b" onclick="hideModal('assignmentModal')">&times;</span>
                <h3 class="text-xl font-semibold stat-text-secondary mb-4">Create Assignment</h3>
                <form action="../actions.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_assignment">
                    <label class="block text-sm font-medium stat-text-primary mb-2">Unit:</label>
                    <select name="unit_id" id="assignmentUnit" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">
                        <option value="">-- Select Unit --</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Assignment Title:</label>
                    <input type="text" name="title" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">
                   <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Written Instructions:</label>
                    <textarea name="instructions" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e" rows="4"></textarea>
                    <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Deadline:</label>
                    <input type="datetime-local" name="due_date" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">
                    <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Attach File (optional):</label>
                    <input type="file" name="assignment_file" class="text-sm text-92400e">
                    <button type="submit" class="btn-primary px-4 py-2 mt-4 rounded-lg">Create Assignment</button>
                </form>
            </div>
        </div>

        <div id="addUnitModal" class="modal">
            <div class="modal-content bg-white p-6 rounded-2xl border border-f5e6b2">
                <span class="close text-92400e text-2xl font-bold cursor-pointer hover:text-f59e0b" onclick="hideModal('addUnitModal')">&times;</span>
                <h3 class="text-xl font-semibold stat-text-secondary mb-4">Add Unit</h3>
                <form action="../actions.php" method="POST">
                    <input type="hidden" name="action" value="add_single_lecturer_unit">
                    <label class="block text-sm font-medium stat-text-primary mb-2">Select Course:</label>
                    <select name="course_id" id="courseSelect" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">
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
                    <label class="block text-sm font-medium stat-text-primary mt-4 mb-2">Select Unit:</label>
                    <select name="unit_id" id="unitSelect" required class="w-full px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">
                        <option value="">-- Select Unit --</option>
                    </select>
                    <button type="submit" class="btn-primary px-4 py-2 mt-4 rounded-lg">Add Unit</button>
                </form>
            </div>
        </div>

        <div id="viewNotesModal" class="modal">
            <div class="modal-content bg-white p-6 rounded-2xl border border-f5e6b2">
                <span class="close text-92400e text-2xl font-bold cursor-pointer hover:text-f59e0b" onclick="hideModal('viewNotesModal')">&times;</span>
                <h3 class="text-xl font-semibold stat-text-secondary mb-4">Uploaded Notes</h3>
                <ul class="space-y-2">
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
                                $file = htmlspecialchars($note['file_path']);
                                $full_path = "../assets/uploads/" . $file;
                                $fileExists = file_exists($full_path);
                                $fileDisplay = $file ? $file : '<span style="color:red;">No filename</span>';
                                echo "<li class='flex justify-between items-center border-b border-f5e6b2 py-2'>";
                                echo "<span class='text-92400e'><strong>" . htmlspecialchars($note['unit']) . "</strong>: $fileDisplay (Uploaded: " . date("M d, Y", strtotime($note['uploaded_at'])) . ")</span>";
                                if ($fileExists) {
                                    echo "<a href='$full_path' target='_blank' class='text-f59e0b hover:underline'><i class='fas fa-eye'></i> View</a>";
                                } else {
                                    echo "<span class='text-red-500'>File missing</span>";
                                }
                                echo "</li>";
                            }
                        } else {
                            echo "<li class='py-2 text-center text-92400e'>No notes uploaded yet.</li>";
                        }
                        $stmt->close();
                    } catch (mysqli_sql_exception $e) {
                        echo "<li class='py-2 text-center text-red-500'>Error loading notes.</li>";
                        error_log("Error fetching notes: " . $e->getMessage());
                    }
                    ?>
                </ul>
            </div>
        </div>

        <div id="submissionModal" class="modal">
            <div class="modal-content bg-white p-6 rounded-2xl border border-f5e6b2">
                <span class="close text-92400e text-2xl font-bold cursor-pointer hover:text-f59e0b" onclick="hideModal('submissionModal')">&times;</span>
                <h3 class="text-xl font-semibold stat-text-secondary mb-4">Student Submissions</h3>
                <ul class="space-y-2">
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
                                $file = htmlspecialchars($row['file_path']);
                                $full_path = "../assets/uploads/submissions/" . $file;
                                $fileExists = file_exists($full_path);
                                echo "<li class='flex justify-between items-center border-b border-f5e6b2 py-2'>";
                                echo "<span class='text-92400e'><strong>" . htmlspecialchars($row['student']) . "</strong> - " . htmlspecialchars($row['unit']) . " (Assignment: " . htmlspecialchars($row['assignment_title']) . ")</span>";
                                if ($fileExists) {
                                    echo "<a href='$full_path' target='_blank' class='text-f59e0b hover:underline'><i class='fas fa-download'></i> Download</a>";
                                } else {
                                    echo "<span class='text-red-500'>File missing</span>";
                                }
                                echo "</li>";
                            }
                        } else {
                            echo "<li class='py-2 text-center text-92400e'>No submissions yet.</li>";
                        }
                        $stmt->close();
                    } catch (mysqli_sql_exception $e) {
                        echo "<li class='py-2 text-center text-red-500'>Error loading submissions.</li>";
                        error_log("Error fetching submissions: " . $e->getMessage());
                    }
                    ?>
                </ul>
            </div>
        </div>

        <script>
            // Sidebar and Navigation Logic
            document.addEventListener('DOMContentLoaded', function() {
                const menuToggle = document.getElementById('menu-toggle');
                const sidebar = document.getElementById('offCanvasMenu');
                const overlay = document.getElementById('overlay');
                const closeBtn = document.getElementById('closeMenuBtn');
                const navLinks = document.querySelectorAll('.nav-link, .menu-item[data-target]');
                const dropdowns = document.querySelectorAll('.dropdown-btn');

                // Sidebar Toggle
                function toggleSidebar() {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('open');
                    const isOpen = sidebar.classList.contains('open');
                    menuToggle.querySelector('svg').innerHTML = isOpen ?
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />' :
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
                }

                menuToggle.addEventListener('click', toggleSidebar);

                overlay.addEventListener('click', toggleSidebar);

                closeBtn.addEventListener('click', toggleSidebar);

                // Navigation Content Switching
                navLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const target = this.getAttribute('data-target');
                        if (target) {
                            document.querySelectorAll('[id$="-content"]').forEach(section => section.classList.add('hidden'));
                            const targetSection = document.getElementById(target);
                            if (targetSection) {
                                targetSection.classList.remove('hidden');
                            }
                            navLinks.forEach(l => l.classList.remove('active'));
                            this.classList.add('active');
                            if (window.innerWidth < 768) {
                                toggleSidebar();
                            }
                        }
                    });
                });

                // Dropdown Logic
                dropdowns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const dropdown = this.parentElement;
                        dropdown.classList.toggle('active');
                    });
                });

                // Modal Logic
                window.showModal = function(id) {
                    const modal = document.getElementById(id);
                    if (modal) modal.classList.add('open');
                };

                window.hideModal = function(id) {
                    const modal = document.getElementById(id);
                    if (modal) modal.classList.remove('open');
                };

                document.querySelectorAll('.modal').forEach(modal => {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) hideModal(modal.id);
                    });
                });

                // Dynamic Unit Loading
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
                                unitSelect.innerHTML = '<option value="">No units found</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching units:', error);
                            unitSelect.innerHTML = '<option value="">Error loading units</option>';
                        });
                });

                // Voice Recording Logic
                let mediaRecorder;
                let audioChunks = [];
                let isRecording = false;

                window.toggleRecording = async function() {
                    const recordButton = document.getElementById('recordButton');
                    const recordingStatus = document.getElementById('recordingStatus');
                    const audioPreview = document.getElementById('audioPreview');

                    if (!isRecording) {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            mediaRecorder = new MediaRecorder(stream);
                            audioChunks = [];

                            mediaRecorder.ondataavailable = (event) => {
                                audioChunks.push(event.data);
                            };

                            mediaRecorder.onstop = () => {
                                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                                const audioUrl = URL.createObjectURL(audioBlob);
                                audioPreview.src = audioUrl;
                                audioPreview.classList.remove('hidden');

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
                };

                window.handleModeChange = function() {
                    const mode = document.getElementById('assignmentMode').value;
                    const speechOptions = document.getElementById('speechOptions');
                    speechOptions.classList.toggle('hidden', mode === 'text');
                };

                // Chart.js for Assignment Status
                const assignmentStats = <?= json_encode($assignment_stats) ?>;
                new Chart(document.getElementById('assignmentStatusChart'), {
                    type: 'bar',
                    data: {
                        labels: assignmentStats.map(stat => stat.unit_name),
                        datasets: [{
                            label: 'Total Assignments',
                            data: assignmentStats.map(stat => stat.total_assignments),
                            backgroundColor: 'rgba(245, 158, 11, 0.6)', // Golden
                            borderColor: '#f59e0b',
                            borderWidth: 1
                        }, {
                            label: 'Submissions Received',
                            data: assignmentStats.map(stat => stat.total_submissions),
                            backgroundColor: 'rgba(217, 119, 6, 0.6)', // Darker golden
                            borderColor: '#d97706',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                        plugins: {
                            legend: { position: 'top', labels: { color: '#92400e' } },
                            title: { display: true, text: 'Assignments vs Submissions per Unit', color: '#92400e' }
                        }
                    }
                });

                // Chart.js for Submission Rate
                const submissionTrends = <?= json_encode($submission_trends) ?>;
                const uniqueUnits = [...new Set(submissionTrends.map(trend => trend.unit_name))];
                const uniqueDates = [...new Set(submissionTrends.map(trend => trend.submission_date))];
                const datasets = uniqueUnits.map(unit => {
                    return {
                        label: unit,
                        data: uniqueDates.map(date => {
                            const match = submissionTrends.find(trend => trend.unit_name === unit && trend.submission_date === date);
                            return match ? match.submission_count : 0;
                        }),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.2)',
                        fill: false,
                        tension: 0.4
                    };
                });

                new Chart(document.getElementById('submissionRateChart'), {
                    type: 'line',
                    data: { labels: uniqueDates, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                        plugins: {
                            legend: { position: 'top', labels: { color: '#92400e' } },
                            title: { display: true, text: 'Daily Submission Trends', color: '#92400e' }
                        }
                    }
                });
            });
        </script>
</body>
</html>
