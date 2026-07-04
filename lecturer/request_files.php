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

/* ================= HANDLE FORM SUBMISSION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $request_title = trim($_POST['request_title'] ?? '');
    $request_description = trim($_POST['request_description'] ?? '');
    
    // Validate inputs
    if (empty($unit_id) || empty($request_title)) {
        $error = "Unit and request title are required.";
    } else {
        // Create file request for all students in the unit
        try {
            // Get all students in this unit
            $studentsStmt = $conn->prepare("
                SELECT DISTINCT s.id, s.name, s.reg_no
                FROM students s
                JOIN student_units su ON s.id = su.student_id
                WHERE su.unit_id = ?
                ORDER BY s.name
            ");
            $studentsStmt->bind_param("i", $unit_id);
            $studentsStmt->execute();
            $students = $studentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $studentsStmt->close();
            
            // Create requests for each student
            $requestStmt = $conn->prepare("
                INSERT INTO lecturer_file_requests 
                (lecturer_id, unit_id, student_id, request_title, request_description, file_type, status)
                VALUES (?, ?, ?, ?, ?, 'document', 'pending')
            ");
            
            $success_count = 0;
            foreach ($students as $student) {
                $requestStmt->bind_param("iiisss", $lecturer_id, $unit_id, $student['id'], $request_title, $request_description);
                if ($requestStmt->execute()) {
                    $success_count++;
                    
                    // Create notification for student
                    $notification_title = "File Request: $request_title";
                    $notification_message = "Your lecturer has requested a document from you. Please upload the requested file.";
                    $notification_link = "../student/file_requests.php";
                    
                    $notifStmt = $conn->prepare("
                        INSERT INTO notifications 
                        (user_id, user_type, title, message, link, created_at)
                        VALUES (?, 'student', ?, ?, ?, NOW())
                    ");
                    $notifStmt->bind_param("issss", $student['id'], $notification_title, $notification_message, $notification_link);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
            }
            $requestStmt->close();
            
            if ($success_count > 0) {
                $success = "File request sent successfully to $success_count students in this unit!";
            } else {
                $error = "Failed to send file requests.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

/* ================= FETCH EXISTING REQUESTS ================= */
$requests = [];
try {
    $stmt = $conn->prepare("
        SELECT lfr.*, 
               s.name AS student_name, s.reg_no AS student_reg,
               u.name AS unit_name
        FROM lecturer_file_requests lfr
        JOIN students s ON lfr.student_id = s.id
        JOIN units u ON lfr.unit_id = u.id
        JOIN lecturer_units lu ON u.id = lu.unit_id
        WHERE lu.lecturer_id = ?
        ORDER BY lfr.created_at DESC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $error = "Error loading requests: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Files - Lecturer Dashboard</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Your CSS -->
    <link rel="stylesheet" href="./css/styles.css">
    
    <style>
        .request-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .request-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .submit-btn {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .submit-btn:hover {
            background: #218838;
        }
        
        .requests-list {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .request-item {
            padding: 20px;
            margin: 15px 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #fafafa;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 15px;
        }
        
        .status-pending { background: #ffc107; color: #333; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            color: #495057;
        }
    </style>
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
            <li class="blue">
                <a href="dashboard.php" style="color:inherit;text-decoration:none;">
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
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
            <li class="purple">
                <a href="../teams/views/lecturer_teams.php" style="color:inherit;text-decoration:none;">
                    <i class="fas fa-users-cog"></i><span>Teams</span>
                </a>
            </li>
            <li class="orange">
                <a href="request_files.php" style="color:inherit;text-decoration:none;">
                    <i class="fas fa-file-contract"></i><span>📁 Request Files</span>
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
    <div class="request-container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <h1>📁 Request Files from Students</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Request Form -->
        <div class="request-form">
            <h2>Create New Request</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Send a file request to all students in a specific unit. Students will be notified and can upload the requested files.
            </p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="unit_id">Unit *</label>
                    <select id="unit_id" name="unit_id" required>
                        <option value="">-- Select Unit --</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="request_title">Request Title *</label>
                    <input type="text" id="request_title" name="request_title" required 
                           placeholder="Enter a clear title for the file request">
                </div>
                
                <div class="form-group">
                    <label for="request_description">Description</label>
                    <textarea id="request_description" name="request_description" 
                              placeholder="Describe what file you need and any specific requirements"></textarea>
                </div>
                
                <button type="submit" name="submit_request" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Request to All Students
                </button>
            </form>
        </div>
        
        <!-- Existing Requests -->
        <div class="requests-list">
            <h2>Existing Requests</h2>
            
            <?php if (empty($requests)): ?>
                <p style="color: #666; font-style: italic; text-align: center; padding: 40px;">
                    No file requests found.
                </p>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="request-item">
                        <div class="request-header">
                            <div>
                                <strong style="font-size: 16px; color: #333;">
                                    <?= htmlspecialchars($request['request_title']) ?>
                                </strong>
                                <span class="status-badge status-<?= $request['status'] ?>">
                                    <?= strtoupper($request['status']) ?>
                                </span>
                            </div>
                            <small style="color: #666;">
                                <?= date("M d, Y • h:i A", strtotime($request['created_at'])) ?>
                            </small>
                        </div>
                        
                        <p style="margin: 8px 0; color: #555;">
                            <strong>Student:</strong> <?= htmlspecialchars($request['student_name']) ?> 
                            (<?= htmlspecialchars($request['student_reg']) ?>)
                        </p>
                        <p style="margin: 8px 0; color: #555;">
                            <strong>Unit:</strong> <?= htmlspecialchars($request['unit_name']) ?>
                        </p>
                        <p style="margin: 8px 0; color: #555;">
                            <strong>Description:</strong> 
                            <?= htmlspecialchars($request['request_description'] ?: 'No description provided') ?>
                        </p>
                        
                        <?php if ($request['uploaded_file_path']): ?>
                            <p style="margin: 8px 0;">
                                <strong>Uploaded File:</strong> 
                                <a href="../assets/uploads/requested_files/<?= htmlspecialchars($request['uploaded_file_path']) ?>" 
                                   target="_blank" 
                                   style="color: #28a745; text-decoration: none; font-weight: 600;">
                                    📄 Download File
                                </a>
                            </p>
                        <?php else: ?>
                            <p style="margin: 8px 0; color: #999; font-style: italic;">
                                File not yet uploaded
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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
</script>

</body>

</html>
