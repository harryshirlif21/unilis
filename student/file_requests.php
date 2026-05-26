<?php
require_once '../config/db.php';
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];
$page_title = "File Requests - UniLIS";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $request_id = intval($_POST['request_id'] ?? 0);
    
    // Validate request exists and belongs to this student
    $verify_stmt = $conn->prepare("
        SELECT lfr.id, lfr.status 
        FROM lecturer_file_requests lfr
        WHERE lfr.id = ? AND lfr.student_id = ?
    ");
    $verify_stmt->bind_param("ii", $request_id, $student_id);
    $verify_stmt->execute();
    $request = $verify_stmt->get_result()->fetch_assoc();
    $verify_stmt->close();
    
    if (!$request) {
        $_SESSION['upload_error'] = "Request not found";
        header("Location: file_requests.php");
        exit;
    }
    
    if ($request['status'] !== 'pending') {
        $_SESSION['upload_error'] = "Request has already been processed";
        header("Location: file_requests.php");
        exit;
    }
    
    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
        if (!in_array($file_ext, $allowed_extensions)) {
            $_SESSION['upload_error'] = "Invalid file type. Allowed types: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, GIF, TXT";
            header("Location: file_requests.php");
            exit;
        }
        
        // Validate file size (max 10MB)
        if ($file_size > 10 * 1024 * 1024) {
            $_SESSION['upload_error'] = "File too large. Maximum size is 10MB";
            header("Location: file_requests.php");
            exit;
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../assets/uploads/requested_files/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $unique_filename = 'request_' . $request_id . '_' . $student_id . '_' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file_tmp, $upload_path)) {
            // Update database with file path
            $update_stmt = $conn->prepare("
                UPDATE lecturer_file_requests 
                SET uploaded_file_path = ?, uploaded_at = NOW(), status = 'approved'
                WHERE id = ?
            ");
            $update_stmt->bind_param("si", $unique_filename, $request_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['upload_success'] = "File uploaded successfully!";
                
                // Create notification for lecturer
                $notif_stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (user_id, user_type, title, message, link, created_at)
                    SELECT lfr.lecturer_id, 'lecturer', 
                           CONCAT('File Uploaded: ', lfr.request_title),
                           CONCAT('Student has uploaded the requested file for: ', lfr.request_title),
                           '../lecturer/request_files.php',
                           NOW()
                    FROM lecturer_file_requests lfr
                    WHERE lfr.id = ?
                ");
                $notif_stmt->bind_param("i", $request_id);
                $notif_stmt->execute();
                $notif_stmt->close();
                
            } else {
                $_SESSION['upload_error'] = "Error updating database: " . $conn->error;
                // Remove uploaded file if database update failed
                unlink($upload_path);
            }
            $update_stmt->close();
        } else {
            $_SESSION['upload_error'] = "Error uploading file. Please try again.";
        }
    } else {
        $_SESSION['upload_error'] = "Please select a file to upload.";
    }
    
    header("Location: file_requests.php");
    exit;
}

// Fetch student's file requests
$requests = [];
try {
    $stmt = $conn->prepare("
        SELECT lfr.*, u.name AS unit_name, l.name AS lecturer_name
        FROM lecturer_file_requests lfr
        JOIN units u ON lfr.unit_id = u.id
        JOIN lecturers l ON lfr.lecturer_id = l.id
        WHERE lfr.student_id = ?
        ORDER BY lfr.created_at DESC
    ");
    $stmt->bind_param("i", $student_id);
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
    <title>File Requests - Student Dashboard</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .request-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .request-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .request-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .request-description {
            color: #555;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #ffc107;
            color: #333;
        }
        
        .status-approved {
            background: #28a745;
            color: white;
        }
        
        .upload-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: block;
            padding: 12px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .file-input-label:hover {
            background: #e9ecef;
            border-color: #667eea;
        }
        
        .upload-btn {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s ease;
            margin-top: 10px;
        }
        
        .upload-btn:hover {
            background: #5a67d8;
        }
        
        .upload-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .file-info {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e8;
            border-radius: 6px;
            color: #155724;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: 500;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            color: #5a67d8;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="welcome-msg">
        <strong>👋 Welcome back!</strong>
    </div>
    <div class="nav-icon" id="notifications-icon" style="position:relative; cursor:pointer;">
        <i class="fas fa-bell"></i>
    </div>
    <div class="nav-icon" id="profile-icon">
        <i class="fas fa-user"></i>
    </div>
    <div class="sidebar-toggle">
        <i class="fas fa-ellipsis-v"></i>
    </div>
</nav>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-section">
        <h4>Main Navigation</h4>
        <ul>
            <li class="blue">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
            <li class="green">
                <a href="course_view.php">
                    <i class="fas fa-chalkboard-teacher"></i><span>Training</span>
                </a>
            </li>
            <li class="orange">
                <a href="take_assessment.php">
                    <i class="fas fa-file-alt"></i><span>Exams</span>
                </a>
            </li>
            <li class="golden">
                <a href="course_view.php">
                    <i class="fas fa-book"></i><span>Lessons</span>
                </a>
            </li>
            <li class="purple">
                <i class="fas fa-check-double"></i><span>Attendance</span>
            </li>
            <li class="teal active">
                <a href="file_requests.php">
                    <i class="fas fa-file-contract"></i><span>📁 File Requests</span>
                </a>
            </li>
            <li class="brown">
                <a href="my_progress.php">
                    <i class="fas fa-chart-line"></i><span>My Progress</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-section">
        <h4>Account</h4>
        <ul>
            <li class="blue"><i class="fas fa-user-circle"></i><span>Profile</span></li>
            <li class="green"><i class="fas fa-cog"></i><span>Settings</span></li>
            <li class="orange" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </li>
        </ul>
    </div>
</aside>

<!-- Main Content -->
<div class="container">
    <a href="dashboard.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    
    <div class="page-header">
        <h1>📁 File Requests</h1>
        <p>View and respond to file requests from your lecturers</p>
    </div>
    
    <?php if (isset($_SESSION['upload_success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['upload_success']) ?>
        </div>
        <?php unset($_SESSION['upload_success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['upload_error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['upload_error']) ?>
        </div>
        <?php unset($_SESSION['upload_error']); ?>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <i class="fas fa-file-contract"></i>
            <h3>No File Requests</h3>
            <p>You don't have any pending file requests from your lecturers.</p>
        </div>
    <?php else: ?>
        <div class="requests-grid">
            <?php foreach ($requests as $request): ?>
                <div class="request-card">
                    <h3 class="request-title"><?= htmlspecialchars($request['request_title']) ?></h3>
                    
                    <div class="request-meta">
                        <div><strong>Unit:</strong> <?= htmlspecialchars($request['unit_name']) ?></div>
                        <div><strong>Lecturer:</strong> <?= htmlspecialchars($request['lecturer_name']) ?></div>
                        <div><strong>Requested:</strong> <?= date("M d, Y • h:i A", strtotime($request['created_at'])) ?></div>
                    </div>
                    
                    <div class="request-description">
                        <?= htmlspecialchars($request['request_description'] ?: 'No description provided') ?>
                    </div>
                    
                    <div class="request-meta">
                        <span class="status-badge status-<?= $request['status'] ?>">
                            <?= ucfirst($request['status']) ?>
                        </span>
                    </div>
                    
                    <?php if ($request['status'] === 'pending'): ?>
                        <div class="upload-form">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                
                                <div class="file-input-wrapper">
                                    <input type="file" name="file" id="file_<?= $request['id'] ?>" required 
                                           accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt">
                                    <label for="file_<?= $request['id'] ?>" class="file-input-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <div>Click to choose file or drag and drop</div>
                                        <small>PDF, DOC, PPT, XLS, Images (Max 10MB)</small>
                                    </label>
                                </div>
                                
                                <button type="submit" name="upload_file" class="upload-btn">
                                    <i class="fas fa-upload"></i> Upload File
                                </button>
                            </form>
                        </div>
                    <?php elseif ($request['uploaded_file_path']): ?>
                        <div class="file-info">
                            <i class="fas fa-check-circle"></i>
                            <strong>File uploaded on:</strong> 
                            <?= date("M d, Y • h:i A", strtotime($request['uploaded_at'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function logout() {
    window.location.href = "../logout.php";
}

// Update file input labels when file is selected
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || '';
        const label = document.querySelector(`label[for="${e.target.id}"]`);
        if (label && fileName) {
            label.innerHTML = `
                <i class="fas fa-file"></i>
                <div><strong>${fileName}</strong></div>
                <small>Click to change file</small>
            `;
            label.style.background = '#e8f5e8';
            label.style.borderColor = '#28a745';
        }
    });
});

// Mobile sidebar toggle
document.querySelector('.sidebar-toggle')?.addEventListener('click', () => {
    document.querySelector('.sidebar')?.classList.toggle('show');
});

document.addEventListener('click', e => {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});
</script>

</body>

</html>
