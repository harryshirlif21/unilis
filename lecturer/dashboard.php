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
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #2ecc71;
            --text-color: #333;
            --light-bg: #ecf0f1;
            --white: #ffffff;
            --border-color: #ddd;
            --danger-color: #e74c3c;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            min-height: 100vh;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
        }

        .header {
            background-color: var(--secondary-color);
            color: var(--white);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 400;
        }

        .header .lecturer-info {
            font-size: 1.1em;
            font-weight: 300;
        }

        .hamburger-menu {
            font-size: 1.8em;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--white);
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }

        .hamburger-menu:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .off-canvas-menu {
            position: fixed;
            top: 0;
            right: -300px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            box-shadow: -4px 0 20px rgba(0, 0, 0, 0.3);
            transition: right 0.3s ease-in-out;
            z-index: 200;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        /* Menu Content Styles */
        .off-canvas-menu .menu-content {
            padding: 25px;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
            background: rgba(255, 255, 255, 0.03);
        }

        /* Scrollbar Styles */
        .off-canvas-menu .menu-content::-webkit-scrollbar {
            width: 6px;
        }

        .off-canvas-menu .menu-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .off-canvas-menu .menu-content::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .off-canvas-menu .menu-content::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .off-canvas-menu.active {
            right: 0;
        }

        .off-canvas-menu .close-btn {
            font-size: 2em;
            color: var(--white);
            align-self: flex-end;
            cursor: pointer;
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }

        .off-canvas-menu .close-btn:hover {
            color: var(--danger-color);
        }

        .off-canvas-menu h2 {
            color: var(--white);
            margin-bottom: 5px;
            font-size: 1.6em;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .off-canvas-menu p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 25px;
            font-size: 0.95em;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }

        .off-canvas-menu .menu-item {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 10px;
            border: none;
            background: rgba(255, 255, 255, 0.05);
            color: var(--white);
            border-radius: 8px;
            cursor: pointer;
            text-align: left;
            text-decoration: none;
            font-size: 1.05em;
            transition: all 0.3s ease;
            gap: 10px;
            box-sizing: border-box;
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(5px);
        }

        .off-canvas-menu .menu-item:hover {
            background: rgba(52, 152, 219, 0.2);
            border-color: rgba(52, 152, 219, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .off-canvas-menu .menu-item.logout {
            margin-top: auto;
            background: rgba(231, 76, 60, 0.1);
            border-color: rgba(231, 76, 60, 0.2);
        }

        .off-canvas-menu .menu-item.logout:hover {
            background: rgba(231, 76, 60, 0.2);
            border-color: rgba(231, 76, 60, 0.4);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 150;
            transition: opacity 0.3s ease;
            opacity: 0;
        }

        .overlay.active {
            display: block;
            opacity: 1;
        }

        .content {
            flex: 1;
            padding: 30px;
            background: var(--light-bg);
            overflow-y: auto;
            width: 100%;
            box-sizing: border-box;
        }

        .content h2 {
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-size: 2.2em;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
            text-align: center;
        }

        .stat-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
            padding: 0 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 25px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 2.8em;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 0.95em;
            color: #666;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
            padding: 0 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .chart-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 300px;
        }

        .chart-container h3 {
            margin-top: 0;
            color: var(--secondary-color);
            font-size: 1.2em;
            margin-bottom: 15px;
            text-align: center;
        }

        .chart-placeholder {
            width: 100%;
            height: 180px;
            background-color: #f0f0f0;
            border: 1px dashed var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-style: italic;
            font-size: 0.9em;
        }

        .recent-activity-section {
            margin-bottom: 40px;
            padding: 0 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .recent-activity-section h3 {
            color: var(--secondary-color);
            font-size: 1.8em;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .table-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 20px;
            overflow-x: auto;
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
            min-width: 600px;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        table th {
            background-color: var(--light-bg);
            color: var(--secondary-color);
            font-weight: bold;
            text-transform: uppercase;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tbody tr:hover {
            background-color: #f0f8ff;
        }

        table td .action-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s ease;
        }

        table td .action-link:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            padding: 0 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .action-card {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 180px;
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .action-card .icon {
            font-size: 3.5em;
            color: var(--primary-color);
            margin-bottom: 15px;
            transition: color 0.2s ease;
        }

        .action-card:hover .icon {
            color: var(--accent-color);
        }

        .action-card h3 {
            font-size: 1.4em;
            color: var(--secondary-color);
            margin-top: 0;
            margin-bottom: 10px;
        }

        .action-card p {
            font-size: 0.9em;
            color: #666;
            margin: 0;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 300;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal.active {
            display: block;
        }

        .modal-content {
            background-color: var(--white);
            margin: 10% auto;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            width: 80%;
            max-width: 600px;
        }

        .modal-content h3 {
            color: var(--secondary-color);
            margin-top: 0;
        }

        .modal-content .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-content .close:hover,
        .modal-content .close:focus {
            color: var(--danger-color);
            text-decoration: none;
        }

        .modal-content form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .modal-content label {
            font-weight: bold;
            color: var(--secondary-color);
        }

        .modal-content input[type="text"],
        .modal-content input[type="file"],
        .modal-content input[type="datetime-local"],
        .modal-content select,
        .modal-content textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1em;
        }

        .modal-content textarea {
            min-height: 100px;
            resize: vertical;
        }

        .voice-recorder {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
        }

        .voice-recorder button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .voice-recorder button:hover {
            background: var(--accent-color);
        }

        #recordingStatus {
            display: block;
            margin: 10px 0;
            color: #666;
            font-style: italic;
        }

        #audioPreview {
            width: 100%;
            margin-top: 10px;
        }

        #speechOptions {
            background: rgba(52, 152, 219, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid rgba(52, 152, 219, 0.1);
        }

        .modal-content button[type="submit"] {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease;
        }

        .modal-content button[type="submit"]:hover {
            background-color: var(--accent-color);
        }

        .modal-content ul {
            list-style: none;
            padding: 0;
        }

        .modal-content ul li {
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-content ul li a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
        }

        .modal-content ul li a:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        @media (max-width: 992px) {
            .stat-cards-grid, .charts-grid, .recent-activity-section, .action-grid {
                padding: 0 15px;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 10px 20px;
            }
            .header h1 {
                font-size: 1.5em;
            }
            .header .lecturer-info {
                font-size: 0.95em;
            }
            .content {
                padding: 20px;
            }
            .stat-cards-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 15px;
            }
            .stat-card .number {
                font-size: 2.2em;
            }
            .stat-card .label {
                font-size: 0.85em;
            }
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .chart-container {
                height: 250px;
            }
            .recent-activity-section h3 {
                font-size: 1.5em;
            }
            table {
                min-width: 500px;
            }
            .action-grid {
                gap: 15px;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            .action-card {
                padding: 20px;
                min-height: 160px;
            }
            .action-card .icon {
                font-size: 3em;
            }
            .action-card h3 {
                font-size: 1.2em;
            }
            .modal-content {
                width: 90%;
            }
        }

        @media (max-width: 480px) {
            .header .lecturer-info {
                display: none;
            }
            .content {
                padding: 15px;
            }
            .stat-cards-grid {
                grid-template-columns: 1fr;
            }
            .action-grid {
                grid-template-columns: 1fr;
            }
            .action-card {
                min-height: 150px;
            }
            .chart-container {
                min-height: 200px;
            }
            table {
                font-size: 0.85em;
                min-width: 400px;
            }
            table th, table td {
                padding: 8px 10px;
            }
            .modal-content {
                width: 95%;
                margin: 20% auto;
            }
        } 
        .dropdown {
    position: relative;
    width: 100%;
}

.dropdown-btn {
    width: 100%;
    background: none;
    border: none;
    color: inherit;
    text-align: left;
    padding: 10px;
    cursor: pointer;
    font-size: 16px;
}

.dropdown-content {
    display: none;
    flex-direction: column;
    background-color: #2c2f33;
    border-left: 3px solid #4cafef;
    margin-left: 15px;
}

.dropdown-content a {
    padding: 8px 12px;
    text-decoration: none;
    color: #ddd;
    font-size: 14px;
}

.dropdown-content a:hover {
    background-color: #3a3d42;
}

.dropdown.active .dropdown-content {
    display: flex;
}

    </style>
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
        document.querySelectorAll('.dropdown-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        this.parentElement.classList.toggle('active');
    });
});

    </script>
</body>
</html>