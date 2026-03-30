<?php
require_once '../config/db.php';
session_start();

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

// Get unit ID from URL
if (!isset($_GET['unit_id'])) {
    header("Location: viewnotes.php");
    exit;
}

$unit_id = (int) $_GET['unit_id'];
$student_id = (int) $_SESSION['user_id'];

// Verify unit belongs to student
$verify_stmt = $conn->prepare("
    SELECT u.id, u.name, u.code, u.course_id, u.year
    FROM units u
    INNER JOIN students s ON s.course_id = u.course_id AND s.year_of_study = u.year
    WHERE u.id = ? AND s.id = ?
");
$verify_stmt->bind_param("ii", $unit_id, $student_id);
$verify_stmt->execute();
$unit = $verify_stmt->get_result()->fetch_assoc();
$verify_stmt->close();

if (!$unit) {
    echo "<h1>Access Denied</h1><p>This unit is not available for your course.</p>";
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'mark_complete' && isset($_POST['note_id'])) {
        $note_id = (int) $_POST['note_id'];
        
        // Check if already completed
        $check = $conn->prepare("SELECT id FROM student_classnotes_progress WHERE student_id = ? AND classnote_id = ?");
        $check->bind_param("ii", $student_id, $note_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        
        if ($exists) {
            $stmt = $conn->prepare("UPDATE student_classnotes_progress SET status = 'completed', last_accessed = NOW() WHERE student_id = ? AND classnote_id = ?");
        } else {
            $stmt = $conn->prepare("INSERT INTO student_classnotes_progress (student_id, classnote_id, status, last_accessed) VALUES (?, ?, 'completed', NOW())");
        }
        
        $stmt->bind_param("ii", $student_id, $note_id);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => $success]);
        exit;
    }
}

// Fetch file notes
$file_notes_stmt = $conn->prepare("
    SELECT n.id, n.file_path, n.uploaded_at
    FROM notes n
    WHERE n.unit_id = ?
    ORDER BY n.uploaded_at DESC
");
$file_notes_stmt->bind_param("i", $unit_id);
$file_notes_stmt->execute();
$file_notes = $file_notes_stmt->get_result();

// Fetch interactive notes
$interactive_notes_stmt = $conn->prepare("
    SELECT cn.id, cn.title, cn.subtopics_json, cn.uploaded_at,
           scp.status as progress_status
    FROM classnotes cn
    LEFT JOIN student_classnotes_progress scp ON scp.classnote_id = cn.id AND scp.student_id = ?
    WHERE cn.unit_id = ?
    ORDER BY cn.uploaded_at ASC
");
$interactive_notes_stmt->bind_param("ii", $student_id, $unit_id);
$interactive_notes_stmt->execute();
$interactive_notes = $interactive_notes_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($unit['name']) ?> - Notes</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        body {
            background-color: #f5f5f5 !important;
        }
        
        .unit-notes-container {
            margin-top: 90px;
            margin-left: 0;
            padding: 20px;
            min-height: calc(100vh - 90px);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .unit-header {
            background: linear-gradient(135deg, #4A90E2, #357ABD);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        .unit-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .unit-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-3px);
        }

        .notes-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4A90E2;
        }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .note-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .note-card.file-note::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10B981, #059669);
        }

        .note-card.interactive-note::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #F97316, #ea580c);
        }

        .note-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .file-note .note-icon {
            background: linear-gradient(135deg, #10B981, #059669);
        }

        .interactive-note .note-icon {
            background: linear-gradient(135deg, #F97316, #ea580c);
        }

        .note-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .note-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .note-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #999;
            font-size: 12px;
            margin-bottom: 15px;
        }

        .note-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4A90E2, #357ABD);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #357ABD, #2968AA);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
        }

        .btn-success.completed {
            background: linear-gradient(135deg, #6B7280, #4B5563);
        }

        .subtopics {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .subtopic {
            margin-bottom: 15px;
        }

        .subtopic:last-child {
            margin-bottom: 0;
        }

        .subtopic h5 {
            color: #333;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .subtopic-content {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .unit-notes-container {
                margin-left: 0;
                margin-top: 70px;
                padding: 15px;
            }

            .unit-header {
                padding: 20px;
            }

            .unit-header h1 {
                font-size: 24px;
            }

            .notes-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .note-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="welcome-msg">
            <strong>👋 Welcome back!</strong>
        </div>

        <!-- Navigation Icons Container -->
        <div class="nav-icons-container">
            <!-- Notifications -->
            <div class="nav-icon" id="notifications-icon" style="position:relative; cursor:pointer;">
                <i class="fas fa-bell"></i>
                <!-- Red circle indicator for new notifications -->
                <span id="notificationCount" 
                      style="position:absolute; top:0; right:0; width:12px; height:12px; background:red; border-radius:50%; display:block;">
                </span>
            </div>
            <div class="nav-icon" id="profile-icon" style="cursor: pointer;">
                <i class="fas fa-user"></i>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="unit-notes-container">
        <div class="unit-header">
            <a href="viewnotes.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Units
            </a>
            <h1><?= htmlspecialchars($unit['name']) ?></h1>
            <p><?= htmlspecialchars($unit['code']) ?> • Year <?= htmlspecialchars($unit['year']) ?></p>
        </div>

        <!-- File Notes Section -->
        <div class="notes-section">
            <h2 class="section-title">📁 File Notes</h2>
            <?php if ($file_notes->num_rows > 0): ?>
                <div class="notes-grid">
                    <?php while ($note = $file_notes->fetch_assoc()): ?>
                        <div class="note-card file-note">
                            <div class="note-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="note-title">File Note</div>
                            <div class="note-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($note['uploaded_at'])) ?></span>
                                <span><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($note['uploaded_at'])) ?></span>
                            </div>
                            <div class="note-actions">
                                <?php 
                                $filePath = "../assets/uploads/" . htmlspecialchars($note['file_path']);
                                if (file_exists($filePath)): ?>
                                    <a href="<?= $filePath ?>" target="_blank" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?= $filePath ?>" download class="btn btn-success">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-size: 12px;">
                                        <i class="fas fa-exclamation-triangle"></i> File not found
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file"></i>
                    <h3>No File Notes</h3>
                    <p>No file notes have been uploaded for this unit yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Interactive Notes Section -->
        <div class="notes-section">
            <h2 class="section-title">💻 Interactive Notes</h2>
            <?php if ($interactive_notes->num_rows > 0): ?>
                <div class="notes-grid">
                    <?php while ($note = $interactive_notes->fetch_assoc()): ?>
                        <?php 
                        $subtopics = json_decode($note['subtopics_json'], true) ?? [];
                        $isCompleted = $note['progress_status'] === 'completed';
                        ?>
                        <div class="note-card interactive-note">
                            <div class="note-icon">
                                <i class="fas fa-laptop-code"></i>
                            </div>
                            <div class="note-title"><?= htmlspecialchars($note['title']) ?></div>
                            <div class="note-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($note['uploaded_at'])) ?></span>
                                <span><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($note['uploaded_at'])) ?></span>
                            </div>
                            
                            <?php if (!empty($subtopics)): ?>
                                <div class="subtopics">
                                    <?php foreach ($subtopics as $index => $subtopic): ?>
                                        <div class="subtopic">
                                            <h5><?= htmlspecialchars($subtopic['title'] ?? "Subtopic " . ($index + 1)) ?></h5>
                                            <div class="subtopic-content">
                                                <?= nl2br(htmlspecialchars($subtopic['content'] ?? 'No content available')) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="note-actions">
                                <button class="btn btn-success <?= $isCompleted ? 'completed' : '' ?>" 
                                        onclick="markAsComplete(<?= $note['id'] ?>)"
                                        <?= $isCompleted ? 'disabled' : '' ?>>
                                    <i class="fas <?= $isCompleted ? 'fa-check' : 'fa-check-circle' ?>"></i>
                                    <?= $isCompleted ? 'Completed' : 'Mark as Complete' ?>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-laptop"></i>
                    <h3>No Interactive Notes</h3>
                    <p>No interactive notes have been created for this unit yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile popup -->
    <div class="popup" id="profile-popup">
        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
        <p><strong>Reg No:</strong> <?php echo htmlspecialchars($student['reg_no']); ?></p>
        <p><strong>Course:</strong> <?php echo htmlspecialchars($course_name); ?></p>
        <p><strong>Year:</strong> <?php echo htmlspecialchars($student['year_of_study']); ?></p>
        <p><strong>Joined:</strong> <?php echo htmlspecialchars($student['year_joined']); ?></p>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; text-align: center;">
            <a href="my_progress.php" style="background: #667eea; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px; text-decoration: none; display: inline-block;">
                <i class="fas fa-chart-line"></i> My Progress
            </a>
            <a href="../logout.php" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Latest notifications popup -->
    <div id="notifications-content" class="popup">
        <h3>Latest Notifications</h3>
        <ul>
            <?php if($latest_notifications->num_rows === 0): ?>
                <li>No notifications</li>
            <?php else: ?>
                <?php while($notif = $latest_notifications->fetch_assoc()): ?>
                    <li>
                        <strong><?= htmlspecialchars($notif['title']) ?></strong>
                        <p><?= htmlspecialchars($notif['message']) ?></p>
                        <small><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></small>
                    </li>
                <?php endwhile; ?>
            <?php endif; ?>
        </ul>
        <div style="margin-top: 15px; text-align: center;">
            <button onclick="window.location.href='notifications.php'" style="background: #4A90E2; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer;">
                View All Notifications
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Profile popup functionality
        const profileIcon = document.getElementById('profile-icon');
        const profilePopup = document.getElementById('profile-popup');
        
        if (profileIcon && profilePopup) {
            profileIcon.addEventListener('click', () => {
                const isVisible = profilePopup.style.display === 'block';
                profilePopup.style.display = isVisible ? 'none' : 'block';
                
                // Hide notifications popup
                const notificationsPopup = document.getElementById('notifications-content');
                if (notificationsPopup) {
                    notificationsPopup.style.display = 'none';
                }
            });
        }

        // Notifications functionality
        const notificationsIcon = document.getElementById('notifications-icon');
        const notificationsPopup = document.getElementById('notifications-content');
        
        if (notificationsIcon && notificationsPopup) {
            notificationsIcon.addEventListener('click', () => {
                const isVisible = notificationsPopup.style.display === 'block';
                notificationsPopup.style.display = isVisible ? 'none' : 'block';
                
                // Hide profile popup
                if (profilePopup) {
                    profilePopup.style.display = 'none';
                }
            });
        }

        // Close popups when clicking outside
        document.addEventListener('click', (e) => {
            if (!profileIcon?.contains(e.target) && !profilePopup?.contains(e.target)) {
                profilePopup.style.display = 'none';
            }
            if (!notificationsIcon?.contains(e.target) && !notificationsPopup?.contains(e.target)) {
                notificationsPopup.style.display = 'none';
            }
        });
    });

    function markAsComplete(noteId) {
        const formData = new FormData();
        formData.append('action', 'mark_complete');
        formData.append('note_id', noteId);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to mark as complete. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }

    function logout() {
        window.location.href = "../logout.php";
    }
    </script>
</body>
</html>
