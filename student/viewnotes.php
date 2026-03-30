<?php
require_once '../config/db.php';
session_start();

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = (int) $_SESSION['user_id'];

// Get student course/year
$stmt = $conn->prepare("SELECT course_id, year_of_study FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$course_id = $student['course_id'];
$year_of_study = $student['year_of_study'];

// Fetch units that have notes
$units_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.code, 
           (SELECT COUNT(*) FROM notes WHERE unit_id = u.id) as file_count,
           (SELECT COUNT(*) FROM classnotes WHERE unit_id = u.id) as interactive_count
    FROM units u
    WHERE u.course_id = ? AND u.year = ?
    ORDER BY u.name
");
$units_stmt->bind_param("ii", $course_id, $year_of_study);
$units_stmt->execute();
$units_result = $units_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Notes - UniLis</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        
        body {
            background: #fff;
        }
        
        .notes-container {
            margin-top: 90px;
            margin-left: 0;
            padding: 20px;
            min-height: calc(100vh - 90px);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            background: #fff;
        }

        .notes-header {
            margin-bottom: 30px;
        }

        .notes-header h1 {
            color: #1a1a2e;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .notes-header p {
            color: #555;
            font-size: 16px;
        }

        .units-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .unit-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .unit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: transparent;
        }

        .unit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }

        .unit-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .unit-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 20px;
            margin-right: 15px;
        }

        .unit-info h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .unit-info p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .unit-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-item i {
            color: #667eea;
            font-size: 16px;
        }

        .stat-item span {
            color: #555;
            font-size: 14px;
            font-weight: 500;
        }

        .stat-count {
            background: transparent;
            color: #667eea;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #e0e0e0;
        }

        .view-notes-btn {
            width: 100%;
            padding: 12px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .view-notes-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }

        .empty-state p {
            font-size: 16px;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .notes-container {
                margin-left: 0;
                margin-top: 70px;
                padding: 15px;
            }

            .units-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .unit-card {
                padding: 20px;
            }

            .unit-stats {
                gap: 15px;
            }

            .notes-header h1 {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .unit-header {
                flex-direction: column;
                text-align: center;
            }

            .unit-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .unit-stats {
                justify-content: center;
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
    <div class="notes-container">
        <div class="notes-header">
            <h1>📚 My Notes</h1>
            <p>Year <?= htmlspecialchars($year_of_study) ?> • Select a unit to view available notes</p>
        </div>

        <?php if ($units_result->num_rows > 0): ?>
            <div class="units-grid">
                <?php while ($unit = $units_result->fetch_assoc()): ?>
                    <div class="unit-card" onclick="window.location.href='unit_notes.php?unit_id=<?= $unit['id'] ?>'">
                        <div class="unit-header">
                            <div class="unit-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="unit-info">
                                <h3><?= htmlspecialchars($unit['name']) ?></h3>
                                <p><?= htmlspecialchars($unit['code']) ?></p>
                            </div>
                        </div>
                        
                        <div class="unit-stats">
                            <div class="stat-item">
                                <i class="fas fa-file-alt"></i>
                                <span>Files:</span>
                                <span class="stat-count"><?= $unit['file_count'] ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-laptop-code"></i>
                                <span>Interactive:</span>
                                <span class="stat-count"><?= $unit['interactive_count'] ?></span>
                            </div>
                        </div>
                        
                        <button class="view-notes-btn">
                            <i class="fas fa-arrow-right"></i> View Notes
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No Notes Available</h3>
                <p>There are currently no notes available for your course and year of study. Please check back later.</p>
            </div>
        <?php endif; ?>
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

    function logout() {
        window.location.href = "../logout.php";
    }
    </script>
</body>
</html>
