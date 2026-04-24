<?php
require_once '../config/db.php';
require_once '../includes/notifications.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$student_id = $_SESSION['user_id'];
try {
    $student_stmt = $conn->prepare("SELECT id, name, email, reg_no, course_id, year_of_study, year_joined FROM students WHERE id = ?");
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    if (!$student) {
        throw new Exception("Student not found.");
    }
    $course_id = $student['course_id'];
    $year_of_study = $student['year_of_study'];

    // Semester filter
    $semester = intval($_GET['semester'] ?? $_SESSION['cv_semester'] ?? 1);
    if ($semester < 1 || $semester > 2) $semester = 1;
    $_SESSION['cv_semester'] = $semester;

    // Fetch course name
    $course_stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course = $course_stmt->get_result()->fetch_assoc();
    $course_name = $course ? $course['name'] : 'Unknown Course';
    $course_stmt->close();
    $student_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching student/course: " . $e->getMessage());
    $_SESSION['error'] = "Error loading student data.";
    header("Location: ../index.php");
    exit;
}

// Get latest 5 notifications for current student
$latest_notifications = get_latest_notifications($conn, 5, $student_id, 'student');

// Get unread count for current student
$unread_count = get_unread_notification_count($conn, $student_id, 'student');

// Handle AJAX mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_notification_read') {
    header('Content-Type: application/json');
    $notif_id = intval($_POST['notification_id']);
    if (mark_notification_as_read($conn, $notif_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>

<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          navy: '#1e3a8a',
          gold: '#d4af37',
        }
      }
    }
  }
</script>

<!-- Font Awesome for profile avatar icon -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
  --primary: #2563eb;
  --secondary: #1e40af;
  --accent: #f59e0b;
  --bg-light: #f9fafb;
  --text-dark: #1f2937;
  --text-light: #6b7280;
}

body {
  background: white !important;
}

/* =====================
   SIDEBAR MOBILE FIX
   ===================== */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1000;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    overflow-y: auto;
  }

  .sidebar.show {
    transform: translateX(0);
  }

  /* Dark overlay behind sidebar */
  .sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 999;
  }

  .sidebar-overlay.show {
    display: block;
  }
}
</style>

</head>
<body>

<!-- Navbar -->
<nav class="bg-navy text-white p-4 flex justify-between items-center shadow-lg">
    <!-- Mobile Three-Dot Menu -->
    <div class="md:hidden cursor-pointer" id="mobileMenuToggle">
        <i class="fas fa-ellipsis-v text-xl"></i>
    </div>

    <!-- Welcome Message (center) -->
    <div class="flex-1 text-center md:flex-none md:ml-4">
        <strong class="text-lg">👋 Welcome back, <?= htmlspecialchars($student['name']) ?>!</strong>
    </div>

    <!-- Navigation Icons Container -->
    <div class="flex items-center space-x-4">
        <!-- Notifications -->
        <div class="relative cursor-pointer" id="notifications-icon">
            <i class="fas fa-bell text-xl"></i>
            <span id="notificationCount"
                  class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center text-white text-xs font-bold border-2 border-white <?= $unread_count > 0 ? '' : 'hidden' ?>">
                <?= $unread_count > 99 ? '99+' : $unread_count ?>
            </span>
        </div>
        <div class="cursor-pointer" id="profile-icon">
            <i class="fas fa-user text-xl"></i>
        </div>
    </div>
</nav>

<!-- Sidebar overlay (mobile backdrop) -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden" id="sidebarOverlay"></div>

<!-- Sidebar — id="sidebar" is REQUIRED for the JS toggle to work -->
<aside class="fixed md:static inset-y-0 left-0 w-64 bg-gray-800 text-white transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50 md:z-auto overflow-y-auto" id="sidebar">
    <!-- Main Navigation -->
    <div class="p-6">
        <h4 class="text-lg font-semibold mb-4 text-gray-300">Main Navigation</h4>
        <ul class="space-y-2">
            <li>
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-navy text-white hover:bg-navy/80 transition">
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="course_view.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <i class="fas fa-chalkboard-teacher"></i><span>Training</span>
                </a>
            </li>
            <li>
                <a href="take_assessment.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <i class="fas fa-file-alt"></i><span>Exams</span>
                </a>
            </li>
            <li>
                <a href="lesson_view.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <i class="fas fa-book"></i><span>Lessons</span>
                </a>
            </li>
            <!-- Attendance: no <a> tag — JS click handler opens the modal -->
            <li class="purple">
                <i class="fas fa-check-double"></i><span>Attendance</span>
            </li>
            <li class="orange">
                <a href="file_requests.php">
                    <i class="fas fa-file-contract"></i><span>📁 File Requests</span>
                </a>
            </li>
            <li class="brown">
                <a href="my_progress.php">
                    <i class="fas fa-chart-line"></i><span>My Progress</span>
                </a>
            </li>
            <li class="teal">
                <a href="../teams/views/create_team.php">
                    <i class="fas fa-users"></i><span>Create Team</span>
                </a>
            </li>
            <li style="background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:8px;margin-top:4px">
                <a href="my_units.php" style="color:#fff !important">
                    <i class="fas fa-book-open"></i><span>My Units</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Account Section -->
    <div class="sidebar-section">
        <h4>Account</h4>
        <ul>
            <li class="blue"><i class="fas fa-user-circle"></i><span>Profile</span></li>
            <li class="green"><i class="fas fa-cog"></i><span>Settings</span></li>
            <li class="orange" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </li>
        </ul>
    </div>
</aside>

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
    <h3><i class="fas fa-bell"></i> Notifications</h3>
    <div id="notif-list">
        <?php if(empty($latest_notifications)): ?>
            <div style="padding: 20px; text-align: center; color: #999;">
                <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                No notifications yet
            </div>
        <?php else: ?>
            <ul style="list-style: none; max-height: 400px; overflow-y: auto;">
                <?php foreach($latest_notifications as $notif): ?>
                    <li style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; <?php echo !$notif['is_read'] ? 'font-weight: bold; background: #f9f9f9;' : ''; ?>" id="quick-notif-<?= $notif['id'] ?>" onclick="quickMarkRead(<?= $notif['id'] ?>)">
                        <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                            <div style="flex: 1;">
                                <strong><?= htmlspecialchars($notif['title']) ?></strong>
                                <br>
                                <small style="color: #666;"><?= htmlspecialchars(substr($notif['message'], 0, 60)) ?>...</small>
                                <br>
                                <small style="color: #999; font-size: 11px;">
                                    <?php
                                        $time = strtotime($notif['created_at']);
                                        $now  = time();
                                        $diff = $now - $time;
                                        if ($diff < 60)        echo "Just now";
                                        elseif ($diff < 3600)  echo floor($diff / 60) . "m ago";
                                        elseif ($diff < 86400) echo floor($diff / 3600) . "h ago";
                                        else                   echo date('M d', $time);
                                    ?>
                                </small>
                            </div>
                            <?php if (!$notif['is_read']): ?>
                                <span style="width: 8px; height: 8px; background: #ff6b6b; border-radius: 50%; flex-shrink: 0; margin-top: 4px;"></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div style="padding: 12px; border-top: 1px solid #eee; text-align: center; display: flex; gap: 8px; justify-content: center;">
        <a href="notifications.php" style="flex: 1; padding: 10px 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 13px; display: flex; align-items: center; justify-content: center; gap: 5px;">
            <i class="fas fa-arrow-right"></i>
            More
        </a>
    </div>
</div>

<!-- Main Content -->
<main class="flex-1 md:ml-64 p-6 bg-gray-50 min-h-screen">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-navy to-blue-600 text-white rounded-2xl p-8 mb-8 shadow-lg">
        <div class="flex flex-col md:flex-row items-center justify-between">
            <div class="mb-6 md:mb-0">
                <p class="text-lg mb-4">👋 Welcome back! We are glad to have you on our system.</p>
                <h2 class="text-3xl font-bold mb-4">Discover the latest tools and updates</h2>
                <button class="bg-gold text-gray-900 px-6 py-3 rounded-lg font-semibold hover:bg-gold/90 transition">Explore Features</button>
            </div>
            <div class="w-32 h-32 bg-white/20 rounded-full flex items-center justify-center">
                <i class="fas fa-graduation-cap text-6xl text-gold"></i>
            </div>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

  <!-- 1. Notes -->
  <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition">
    <div class="flex items-center mb-4">
      <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
        <i class="fas fa-book text-blue-600 text-xl"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-800">Notes</h2>
    </div>
    <p class="text-gray-600 mb-4">Semester 1</p>
    <div class="flex justify-between text-sm text-gray-600 mb-4">
      <span><i class="fas fa-layer-group"></i> 8 Units</span>
      <span><i class="fas fa-file"></i> 6 Files</span>
      <span class="text-orange-600"><i class="fas fa-clock"></i> 2 Pending</span>
    </div>
    <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
      <div class="bg-blue-600 h-2 rounded-full" style="width:75%"></div>
    </div>
    <p class="text-sm text-gray-600 mb-2">Updated 2 days ago</p>
    <p class="text-sm text-green-600 mb-4">Keep going! You're doing great 📚</p>
    <a href="viewnotes.php" class="bg-navy text-white px-4 py-2 rounded-lg hover:bg-navy/90 transition inline-block">Visit notes page</a>
  </div>

  <!-- 2. Assignments -->
  <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition">
    <div class="flex items-center mb-4">
      <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
        <i class="fas fa-file-signature text-green-600 text-xl"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-800">Assignments</h2>
    </div>
    <p class="text-gray-600 mb-4">Semester 1</p>
    <div class="flex justify-between text-sm text-gray-600 mb-4">
      <span>7 Given</span>
      <span class="text-green-600">4 Submitted</span>
      <span class="text-red-600">3 Pending</span>
    </div>
    <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
      <div class="bg-green-600 h-2 rounded-full" style="width:60%"></div>
    </div>
    <p class="text-sm text-orange-600 mb-2">Deadline approaching ⚠️</p>
    <p class="text-sm text-blue-600 mb-4">Almost there — stay focused!</p>
    <a href="take_assignment.php" class="bg-navy text-white px-4 py-2 rounded-lg hover:bg-navy/90 transition inline-block">View Assignments</a>
  </div>

  <!-- 3. Meetings -->
  <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition">
    <div class="flex items-center mb-4">
      <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
        <i class="fas fa-video text-purple-600 text-xl"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-800">Meetings</h2>
    </div>
    <p class="text-gray-600 mb-4">Next Meeting</p>
    <div class="flex justify-between text-sm text-gray-600 mb-4">
      <span><i class="far fa-calendar-check"></i> Today</span>
      <span><i class="far fa-clock"></i> 4:00 PM</span>
    </div>
    <p class="text-sm text-purple-600 mb-4">Stay connected with your class 💻</p>
    <a href="meeting_ide.php" class="bg-navy text-white px-4 py-2 rounded-lg hover:bg-navy/90 transition inline-block">Join Meeting</a>
  </div>

  <!-- 4. Online CATs -->
  <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition">
    <div class="flex items-center mb-4">
      <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
        <i class="fas fa-clipboard-check text-red-600 text-xl"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-800">Online CATs</h2>
    </div>
    <div class="flex justify-between text-sm text-gray-600 mb-4">
      <span>5 Available</span>
      <span>2 Attempted</span>
    </div>
    <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
      <div class="bg-red-600 h-2 rounded-full" style="width:40%"></div>
    </div>
    <p class="text-sm text-red-600 mb-4">Stay sharp! Exams ready 🧠</p>
    <a href="take_assessment.php" class="bg-navy text-white px-4 py-2 rounded-lg hover:bg-navy/90 transition inline-block">Take CAT</a>
  </div>

  <!-- 5. Academic Info -->
  <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition">
    <div class="flex items-center mb-4">
      <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
        <i class="fas fa-graduation-cap text-yellow-600 text-xl"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-800">Academic Info</h2>
    </div>
    <div class="flex justify-between text-sm text-gray-600 mb-4">
      <span>Results Released</span>
      <span class="text-green-600">GPA Updated</span>
    </div>
    <p class="text-sm text-yellow-600 mb-4">Track your academic journey 🎓</p>
    <a href="my_progress.php" class="bg-navy text-white px-4 py-2 rounded-lg hover:bg-navy/90 transition inline-block">View Details</a>
  </div>

  <!-- 6. Other Features -->
  <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition">
    <div class="flex items-center mb-4">
      <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
        <i class="fas fa-puzzle-piece text-indigo-600 text-xl"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-800">Other Features</h2>
    </div>
    <p class="text-sm text-indigo-600 mb-4">Explore additional tools & resources ⚙️</p>
    <button class="bg-navy text-white px-4 py-2 rounded-lg hover:bg-navy/90 transition">Explore</button>
  </div>
</div>

<footer class="bg-gray-900 text-white py-12 mt-12">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">

      <!-- About Column -->
      <div class="text-center lg:text-left">
        <h2 class="text-xl font-bold mb-4">About Us</h2>
        <p class="text-gray-300 leading-relaxed">
          We deliver high-quality services and solutions tailored to help individuals and businesses thrive in the digital world.
          Let's connect and build something great together.
        </p>
      </div>

      <!-- Quick Links -->
      <div class="text-center lg:text-left">
        <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
        <ul class="space-y-2">
          <li><a href="#" class="text-gray-300 hover:text-yellow-400 transition">Home</a></li>
          <li><a href="#" class="text-gray-300 hover:text-yellow-400 transition">Services</a></li>
          <li><a href="#" class="text-gray-300 hover:text-yellow-400 transition">About</a></li>
          <li><a href="#" class="text-gray-300 hover:text-yellow-400 transition">Blog</a></li>
          <li><a href="#" class="text-gray-300 hover:text-yellow-400 transition">Contact</a></li>
        </ul>
      </div>

      <!-- Contact / Social -->
      <div class="text-center lg:text-left">
        <h3 class="text-lg font-semibold mb-4">Get in Touch</h3>
        <p class="text-gray-300 mb-2"><i class="fab fa-whatsapp mr-2"></i> <strong>WhatsApp:</strong> <a href="https://wa.me/254792451666" class="hover:text-yellow-400 transition">+254 792 451 666</a></p>
        <p class="text-gray-300 mb-4"><i class="fas fa-envelope mr-2"></i> <strong>Email:</strong> <a href="mailto:mwendihillary@gmail.com" class="hover:text-yellow-400 transition">mwendihillary@gmail.com</a></p>
        <div class="flex justify-center lg:justify-start space-x-4">
          <a href="https://wa.me/254792451666" class="text-gray-300 hover:text-green-400 transition text-xl" target="_blank" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
          <a href="https://facebook.com/yourpage" class="text-gray-300 hover:text-blue-400 transition text-xl" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="https://instagram.com/yourhandle" class="text-gray-300 hover:text-pink-400 transition text-xl" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="https://x.com/yourhandle" class="text-gray-300 hover:text-blue-300 transition text-xl" target="_blank" aria-label="X / Twitter"><i class="fab fa-twitter"></i></a>
        </div>
      </div>

      <!-- Newsletter -->
      <div class="text-center lg:text-left">
        <h3 class="text-lg font-semibold mb-4">Stay Updated</h3>
        <p class="text-gray-300 mb-4">Subscribe to our newsletter for tips, updates &amp; exclusive offers.</p>
        <form class="flex flex-col space-y-3">
          <input type="email" placeholder="Your email address" class="px-4 py-2 rounded-lg border border-gray-600 bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" required>
          <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 px-4 py-2 rounded-lg transition font-semibold"><i class="fas fa-paper-plane mr-2"></i> Subscribe</button>
        </form>
      </div>

    </div>

    <!-- Bottom Bar -->
    <div class="border-t border-gray-700 mt-8 pt-8 text-center">
      <p class="text-gray-400">&copy; 2026 UNILIS. All rights reserved.</p>
    </div>
  </div>
</footer>

  <!-- Enhanced Student Attendance Modal -->
  <div id="studentAttendanceModal" class="modal hidden">
      <div class="modal-content bg-white rounded-2xl border border-f5e6b2 shadow-2xl max-w-md mx-auto"
           style="max-height: 90vh; overflow-y: auto;">

          <span class="close text-92400e text-3xl font-bold cursor-pointer hover:text-f59e0b absolute top-5 right-6 z-10"
                onclick="hideModal('studentAttendanceModal')">×</span>

          <h3 class="text-2xl font-bold stat-text-secondary mb-8 text-center pt-8">
              <i class="fas fa-qrcode"></i> Mark Your Attendance
          </h3>

          <div id="attendanceContent" class="px-8 pb-10">
              <!-- Loading state -->
              <div id="attendanceLoading" class="text-center py-10 hidden">
                  <div class="spinner"></div>
                  <p class="mt-4 text-gray-600">Loading your attendance information...</p>
              </div>

              <!-- Attendance form -->
              <div id="attendanceForm" class="hidden">
                  <div class="mb-6">
                      <label class="block text-sm font-medium stat-text-primary mb-3">
                          <i class="fas fa-clock"></i> Active Sessions
                      </label>
                      <div id="activeSessionsList" class="space-y-3">
                          <!-- Sessions will be loaded here -->
                      </div>
                  </div>

                  <div class="mb-6">
                      <label class="block text-sm font-medium stat-text-primary mb-3">
                          <i class="fas fa-keyboard"></i> Enter Your Personal Code
                      </label>
                      <input type="text" id="attendanceCodeInput" maxlength="6" placeholder="Enter 6-digit code"
                             class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-xl text-center
                                    tracking-widest focus:ring-2 focus:ring-f59e0b focus:border-f59e0b transition uppercase font-mono">
                      <div class="text-center mt-3">
                          <span id="codeTimer" class="text-sm text-gray-600"></span>
                      </div>
                  </div>

                  <div class="text-center space-y-3">
                      <button type="button" onclick="submitAttendanceCode()"
                              class="btn-golden px-12 py-4 text-lg font-semibold rounded-xl shadow-lg">
                          <i class="fas fa-check-circle"></i> Submit Attendance
                      </button>
                      <button type="button" onclick="requestNewCode()"
                              class="btn-secondary px-8 py-3 text-lg rounded-xl">
                          <i class="fas fa-redo"></i> Request New Code
                      </button>
                  </div>
              </div>

              <!-- Success state -->
              <div id="attendanceSuccess" class="text-center py-10 hidden">
                  <div class="success-icon mb-6">
                      <i class="fas fa-check-circle text-6xl text-green-500"></i>
                  </div>
                  <h3 class="text-2xl font-bold text-green-600 mb-4">Attendance Marked!</h3>
                  <p class="text-gray-600 mb-6">Your attendance has been successfully recorded.</p>
                  <button onclick="hideModal('studentAttendanceModal')"
                          class="btn-primary px-8 py-3 rounded-xl">
                      <i class="fas fa-times"></i> Close
                  </button>
              </div>

              <!-- Error state -->
              <div id="attendanceError" class="text-center py-10 hidden">
                  <div class="error-icon mb-6">
                      <i class="fas fa-exclamation-triangle text-6xl text-red-500"></i>
                  </div>
                  <h3 class="text-2xl font-bold text-red-600 mb-4">Error</h3>
                  <p id="errorMessage" class="text-gray-600 mb-6"></p>
                  <div class="space-x-3">
                      <button onclick="resetAttendanceForm()"
                              class="btn-secondary px-8 py-3 rounded-xl">
                          <i class="fas fa-arrow-left"></i> Try Again
                      </button>
                      <button onclick="requestNewCode()"
                              class="btn-primary px-8 py-3 rounded-xl">
                          <i class="fas fa-redo"></i> Request New Code
                      </button>
                  </div>
              </div>
          </div>
      </div>
  </div>



<script>
// =============================================
//  UTILITY
// =============================================
function logout() {
    window.location.href = "../logout.php";
}

// =============================================
//  MODAL HELPERS
// =============================================
window.showModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    // Load sessions when attendance modal opens
    if (id === 'studentAttendanceModal') {
        loadActiveAttendanceSessions();
    }
};

window.hideModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

// =============================================
//  SIDEBAR TOGGLE (mobile three-dot menu)
// =============================================
document.addEventListener('DOMContentLoaded', () => {
    const sidebar        = document.getElementById('sidebar');
    const overlay        = document.getElementById('sidebarOverlay');
    const mobileToggle   = document.getElementById('mobileMenuToggle');
    const profileIcon    = document.getElementById('profile-icon');
    const profilePopup   = document.getElementById('profile-popup');
    const notifIcon      = document.getElementById('notifications-icon');
    const notifContent   = document.getElementById('notifications-content');

    // ----- Open / close sidebar -----
    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }

    // Three-dot button
    mobileToggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
    });

    // Clicking the overlay closes the sidebar
    overlay?.addEventListener('click', closeSidebar);

    // ESC key closes sidebar
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSidebar();
            // Also close any open modal
            const openModal = document.querySelector('.modal:not(.hidden)');
            if (openModal) hideModal(openModal.id);
        }
    });

    // ----- Profile popup -----
    profileIcon?.addEventListener('click', (e) => {
        e.stopPropagation();
        const visible = profilePopup.style.display === 'block';
        profilePopup.style.display = visible ? 'none' : 'block';
        notifContent.style.display = 'none'; // close the other popup

        if (!visible) {
            const rect = profileIcon.getBoundingClientRect();
            profilePopup.style.top   = (rect.bottom + 10) + 'px';
            profilePopup.style.right = '20px';
        }
    });

    // ----- Notifications popup -----
    notifIcon?.addEventListener('click', (e) => {
        e.stopPropagation();
        const visible = notifContent.style.display === 'block';
        notifContent.style.display = visible ? 'none' : 'block';
        profilePopup.style.display = 'none'; // close the other popup

        if (!visible) {
            const rect = notifIcon.getBoundingClientRect();
            notifContent.style.top   = (rect.bottom + 10) + 'px';
            notifContent.style.right = '20px';
        }
    });

    // ----- Global click: close popups / modals when clicking outside -----
    document.addEventListener('click', (e) => {
        // Close profile popup
        if (profilePopup && !profilePopup.contains(e.target) && !profileIcon?.contains(e.target)) {
            profilePopup.style.display = 'none';
        }
        // Close notifications popup
        if (notifContent && !notifContent.contains(e.target) && !notifIcon?.contains(e.target)) {
            notifContent.style.display = 'none';
        }
        // Close modal on backdrop click or × button
        if (e.target.classList.contains('modal') || e.target.classList.contains('close')) {
            const modal = e.target.closest('.modal');
            if (modal) hideModal(modal.id);
        }
    });

    // ----- Sidebar item click handlers -----
    document.querySelectorAll('.sidebar-section li').forEach(item => {
        item.addEventListener('click', (e) => {
            // If clicking a real link, let it navigate
            if (e.target.closest('a')) return;

            const text = item.querySelector('span')?.textContent.trim();
            if (text === 'Attendance') {
                closeSidebar(); // close sidebar first on mobile
                showModal('studentAttendanceModal');
                document.querySelectorAll('.sidebar-section li').forEach(li => li.classList.remove('active'));
                item.classList.add('active');
            }
        });
    });
});


// =============================================
//  ATTENDANCE SYSTEM
// =============================================
window.attendanceData = { sessions: [], currentSession: null };

function loadActiveAttendanceSessions() {
    fetch('includes/get_attendance_sessions.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                attendanceData.sessions = data.sessions;
                updateSessionsList();
                hideAllAttendanceStates();
                document.getElementById('attendanceForm').classList.remove('hidden');
            }
        })
        .catch(err => console.error('Error loading attendance sessions:', err));
}

function updateSessionsList() {
    const list = document.getElementById('activeSessionsList');
    if (!list) return;

    if (!attendanceData.sessions.length) {
        list.innerHTML = '<p class="text-gray-500 text-center py-4">No active attendance sessions</p>';
        return;
    }

    list.innerHTML = attendanceData.sessions.map(s => `
        <div class="border border-gray-200 rounded-lg p-4 mb-3">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h4 class="font-semibold text-lg">${s.unit_name}</h4>
                    <p class="text-sm text-gray-600">Session: ${s.main_code}</p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-500">Expires: ${new Date(s.deadline).toLocaleString()}</span>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <button onclick="selectSession(${s.session_id})"
                        class="btn-primary px-4 py-2 text-sm rounded">
                    Use This Session
                </button>
                ${s.attended
                    ? '<span class="text-green-600 text-sm"><i class="fas fa-check-circle"></i> Attended</span>'
                    : '<span class="text-orange-600 text-sm"><i class="fas fa-clock"></i> Pending</span>'
                }
            </div>
        </div>
    `).join('');
}

function selectSession(sessionId) {
    const session = attendanceData.sessions.find(s => s.session_id === sessionId);
    if (!session) return;
    attendanceData.currentSession = session;
    document.getElementById('attendanceCodeInput').value = '';
    document.getElementById('attendanceCodeInput').focus();
    updateCodeTimer(session.expires_at);
}

function updateCodeTimer(expiresAt) {
    const el = document.getElementById('codeTimer');
    if (!el) return;
    let interval = setInterval(() => {
        const diff = new Date(expiresAt) - new Date();
        if (diff <= 0) {
            el.innerHTML = '<span style="color:#dc2626"><i class="fas fa-exclamation-triangle"></i> EXPIRED</span>';
            clearInterval(interval);
        } else {
            const m = Math.floor(diff / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            el.innerHTML = `<i class="fas fa-clock"></i> ${m}:${String(s).padStart(2,'0')} remaining`;
        }
    }, 1000);
}

function submitAttendanceCode() {
    const code = document.getElementById('attendanceCodeInput').value.trim();
    if (!code)                        { showAttendanceError('Please enter your attendance code'); return; }
    if (!attendanceData.currentSession) { showAttendanceError('Please select an attendance session first'); return; }

    showAttendanceLoading();

    const fd = new FormData();
    fd.append('action',     'submit_attendance');
    fd.append('session_id', attendanceData.currentSession.session_id);
    fd.append('code',       code);

    fetch('attendance_submit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAttendanceSuccess();
                const idx = attendanceData.sessions.findIndex(s => s.session_id === attendanceData.currentSession.session_id);
                if (idx !== -1) attendanceData.sessions[idx].attended = true;
            } else {
                showAttendanceError(data.message || 'Invalid code');
            }
        })
        .catch(() => showAttendanceError('Network error. Please try again.'));
}

function requestNewCode() {
    if (!attendanceData.currentSession) { showAttendanceError('Please select a session first'); return; }
    showAttendanceLoading();

    const fd = new FormData();
    fd.append('action',     'request_new_code');
    fd.append('session_id', attendanceData.currentSession.session_id);

    fetch('attendance_submit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                resetAttendanceForm();
                if (data.expires_at) updateCodeTimer(data.expires_at);
            } else {
                showAttendanceError(data.message || 'Failed to request new code');
            }
        })
        .catch(() => showAttendanceError('Network error. Please try again.'));
}

function showAttendanceLoading()      { hideAllAttendanceStates(); document.getElementById('attendanceLoading').classList.remove('hidden'); }
function showAttendanceSuccess()      { hideAllAttendanceStates(); document.getElementById('attendanceSuccess').classList.remove('hidden'); }
function showAttendanceError(msg)     { hideAllAttendanceStates(); document.getElementById('attendanceError').classList.remove('hidden'); document.getElementById('errorMessage').textContent = msg; }
function resetAttendanceForm()        { hideAllAttendanceStates(); document.getElementById('attendanceForm').classList.remove('hidden'); document.getElementById('attendanceCodeInput').value = ''; document.getElementById('codeTimer').innerHTML = ''; }

function hideAllAttendanceStates() {
    ['attendanceLoading','attendanceForm','attendanceSuccess','attendanceError'].forEach(id => {
        document.getElementById(id)?.classList.add('hidden');
    });
}

// =============================================
//  NOTIFICATIONS — mark as read
// =============================================
function quickMarkRead(notificationId) {
    const fd = new FormData();
    fd.append('action',          'mark_notification_read');
    fd.append('notification_id', notificationId);

    fetch('dashboard.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const item = document.getElementById('quick-notif-' + notificationId);
            if (item) {
                item.style.fontWeight = 'normal';
                item.style.background = 'white';
                item.querySelector('[style*="background: #ff6b6b"]')?.remove();
            }
            const badge = document.getElementById('notificationCount');
            if (badge) {
                const count = parseInt(badge.textContent) || 0;
                if (count > 1) { badge.textContent = count - 1; }
                else           { badge.style.display = 'none'; }
            }
        })
        .catch(err => console.error('Error:', err));
}

// =============================================
//  TEAM INVITATIONS
// =============================================
async function loadTeamInvitations() {
    const statusEl = document.getElementById('teamInviteStatus');
    const listEl   = document.getElementById('teamInviteList');
    if (!statusEl || !listEl) return;

    statusEl.textContent = 'Loading invitations...';
    listEl.innerHTML = '';

    try {
        const res  = await fetch('../teams/api/get_invitations.php', { credentials: 'same-origin' });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.success) throw new Error(data?.error || ('HTTP ' + res.status));

        const invites = data.invitations || [];
        if (!invites.length) { statusEl.textContent = 'No pending team invitations.'; return; }

        statusEl.textContent = '';
        fetch('../teams/api/cleanup_expired_invitations.php', { credentials: 'same-origin' }).catch(() => {});

        invites.forEach(inv => {
            const row = document.createElement('div');
            row.style.cssText = 'border:1px solid #e5e7eb;border-radius:8px;padding:.75rem;margin-bottom:.5rem';
            row.innerHTML = `
                <div style="font-weight:600;">${inv.team_title || ('Team #' + inv.team_id)}</div>
                <div style="font-size:12px;color:#666;">Invited by: ${inv.inviter_name || ('User #' + inv.invited_by)}</div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-top:.5rem;">
                    <input type="text" id="inviteCode_${inv.id}" placeholder="Enter confirmation code"
                           style="padding:.45rem;border:1px solid #d1d5db;border-radius:6px;">
                    <button type="button" onclick="acceptTeamInvitation(${inv.id})"
                            style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:.45rem .7rem;cursor:pointer;">
                        Accept
                    </button>
                </div>`;
            listEl.appendChild(row);
        });
    } catch (err) {
        statusEl.textContent = 'Failed to load invitations: ' + err.message;
    }
}

async function acceptTeamInvitation(invitationId) {
    const code = (document.getElementById('inviteCode_' + invitationId)?.value || '').trim();
    if (!code) { alert('Enter the confirmation code from your email.'); return; }

    try {
        const res  = await fetch('../teams/api/accept_invitation.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                invitation_id: invitationId,
                code,
                csrf_token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
            })
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.success) throw new Error(data?.error || ('HTTP ' + res.status));
        alert(data.message || 'Invitation accepted');
        loadTeamInvitations();
    } catch (err) {
        alert('Accept invitation failed: ' + err.message);
    }
}

loadTeamInvitations();


// =============================================
//  ATTENDANCE SPINNER CSS (injected once)
// =============================================
document.head.insertAdjacentHTML('beforeend', `<style>
.spinner{border:4px solid #f3f3f3;border-top:4px solid #f59e0b;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin:0 auto 20px}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
.btn-secondary{background:#6c757d;color:white;border:none;padding:.5rem 1rem;border-radius:.5rem;font-size:1rem;cursor:pointer;transition:all .3s ease}
.btn-secondary:hover{background:#5a6268;transform:translateY(-1px)}
.space-y-3>*+*{margin-top:.75rem}
.space-x-2>*+*{margin-left:.5rem}
.space-x-3>*+*{margin-left:.75rem}
</style>`);
</script>

</body>
</html>