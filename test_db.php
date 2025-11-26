<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];
try {
    $student_stmt = $conn->prepare("SELECT id, name, email, reg_no, course_id, year_of_study, year_joined FROM students WHERE id = ?");
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    $course_id = $student['course_id'];
    $year_of_study = $student['year_of_study'];

    $course_stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course = $course_stmt->get_result()->fetch_assoc();
    $course_name = $course ? $course['name'] : 'Unknown Course';

    // Notification Count + Full List
    $unread_count = 0;
    $notifications = [];
    if ($course_id && $year_of_study) {
        $notif_query = $conn->prepare("
            SELECT DISTINCT n.id, n.title, n.message, n.link, n.is_read, n.created_at
            FROM notifications n
            LEFT JOIN notes nt ON n.notes_id = nt.id
            LEFT JOIN assignments a ON n.assignment_id = a.id
            LEFT JOIN interactive_assignments ia ON n.interactive_assignment_id = ia.id
            LEFT JOIN meetings m ON n.meeting_id = m.id
            LEFT JOIN units u 
                ON u.id = nt.unit_id 
                OR u.id = a.unit_id 
                OR u.id = ia.unit_id 
                OR u.id = m.unit_id
            WHERE u.course_id = ? AND u.year = ?
            ORDER BY n.created_at DESC
        ");
        $notif_query->bind_param("ii", $course_id, $year_of_study);
        $notif_query->execute();
        $result = $notif_query->get_result();

        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
            if (!$row['is_read']) $unread_count++;
        }
    }

    // Stats
    $units_count = $conn->query("SELECT COUNT(*) FROM units WHERE course_id = $course_id AND year = $year_of_study")->fetch_row()[0] ?? 0;
    $assignments_due = $conn->query("SELECT COUNT(*) FROM assignments a JOIN units u ON a.unit_id = u.id WHERE u.course_id = $course_id AND u.year = $year_of_study AND a.deadline >= NOW()")->fetch_row()[0] ?? 0;
    $meetings_count = $conn->query("SELECT COUNT(*) FROM meetings m JOIN units u ON m.unit_id = u.id WHERE u.course_id = $course_id AND u.year = $year_of_study AND m.scheduled_time >= NOW()")->fetch_row()[0] ?? 0;
    $submitted_count = $conn->query("SELECT COUNT(*) FROM submissions WHERE student_id = $student_id")->fetch_row()[0] ?? 0;

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    die("System error.");
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UNILIS | <?= htmlspecialchars($student['name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { 
      --primary: #2563eb;
      --primary-light: #3b82f6;
      --primary-dark: #1d4ed8;
      --amber: #f59e0b; 
      --brown: #92400e; 
    }
    
    body { 
      font-family: 'Inter', sans-serif;
      background-color: #f8fafc;
    }
    
    .dark { 
      background: #0f172a; 
      color: #e2e8f0; 
    }
    
    .sidebar-collapsed { 
      width: 80px !important; 
    }
    
    .sidebar-collapsed .nav-text, 
    .sidebar-collapsed .logo-text { 
      opacity: 0; 
      transform: translateX(-20px); 
    }
    
    .nav-item { 
      @apply flex items-center space-x-4 px-6 py-4 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-slate-800 transition relative cursor-pointer; 
    }
    
    .nav-item.active { 
      @apply bg-gradient-to-r from-blue-500 to-indigo-600 text-white shadow-lg; 
    }
    
    .nav-text, .logo-text { 
      @apply transition-all duration-300; 
    }
    
    .unit-tile { 
      @apply bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 cursor-pointer border border-blue-100 dark:border-slate-700; 
    }
    
    .close-modal { 
      @apply absolute top-4 right-6 bg-red-500 hover:bg-red-600 text-white w-10 h-10 rounded-full flex items-center justify-center text-xl font-bold shadow-lg; 
    }
    
    .btn-primary { 
      @apply bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition; 
    }
    
    .streak-fire { 
      animation: pulse 2s infinite; 
    }
    
    @keyframes pulse { 
      0%,100% { opacity: 1; } 
      50% { opacity: 0.7; } 
    }
    
    .confetti { 
      position: fixed; 
      width: 10px; 
      height: 10px; 
      animation: fall 3s linear forwards; 
      z-index: 9999; 
      pointer-events: none; 
    }
    
    @keyframes fall { 
      to { 
        transform: translateY(100vh) rotate(720deg); 
        opacity: 0; 
      } 
    }
    
    .notification-unread { 
      @apply bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500; 
    }
    
    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
    }
    
    .content-card {
      @apply bg-white dark:bg-slate-800 rounded-xl p-6 shadow-md transition-all duration-300;
    }
    
    .content-card:hover {
      @apply shadow-lg transform -translate-y-1;
    }
    
    .right-sidebar {
      transform: translateX(100%);
      transition: transform 0.3s ease;
    }
    
    .right-sidebar.open {
      transform: translateX(0);
    }
    
    .mobile-menu-btn {
      display: none;
    }
    
    @media (max-width: 768px) {
      .mobile-menu-btn {
        display: flex;
      }
      
      .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
      }
      
      .sidebar.open {
        transform: translateX(0);
      }
    }
  </style>
</head>
<body class="min-h-screen flex">

  <!-- Mobile Menu Button -->
  <button id="mobileMenuBtn" class="mobile-menu-btn fixed top-4 left-4 z-50 bg-blue-500 text-white p-2 rounded-lg shadow-lg">
    <i data-lucide="menu" class="w-6 h-6"></i>
  </button>

  <!-- Left Sidebar Navigation -->
  <aside id="sidebar" class="w-64 bg-white dark:bg-slate-900 shadow-lg h-screen overflow-y-auto z-40 transition-transform duration-300">
    <div class="p-6 border-b dark:border-slate-800 flex items-center space-x-4">
      <div class="flex items-center space-x-4 cursor-pointer" onclick="toggleProfileCard()">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?= $student['reg_no'] ?>" class="w-14 h-14 rounded-full ring-4 ring-blue-500/20">
        <div class="overflow-hidden">
          <p class="font-bold text-lg logo-text"><?= htmlspecialchars($student['name']) ?></p>
          <p class="text-sm text-gray-500 logo-text">Year <?= $year_of_study ?></p>
        </div>
      </div>
    </div>

    <div id="profileCard" class="hidden bg-blue-50 dark:bg-slate-800 p-6 border-b dark:border-slate-700 text-sm">
      <p><strong>Reg No:</strong> <?= htmlspecialchars($student['reg_no']) ?></p>
      <p><strong>Course:</strong> <?= htmlspecialchars($course_name) ?></p>
      <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-2">
      <a href="#" class="nav-item active" data-section="dashboard">
        <i data-lucide="layout-dashboard"></i>
        <span class="nav-text">Dashboard</span>
      </a>
      <a href="#" class="nav-item" data-section="courses">
        <i data-lucide="book-open"></i>
        <span class="nav-text">Courses</span>
      </a>
      <a href="#" class="nav-item" data-section="assignments">
        <i data-lucide="clipboard-list"></i>
        <span class="nav-text">Assignments</span>
      </a>
      <a href="#" class="nav-item" data-section="notes">
        <i data-lucide="file-text"></i>
        <span class="nav-text">Notes</span>
      </a>
      <a href="#" class="nav-item" data-section="meetings">
        <i data-lucide="video"></i>
        <span class="nav-text">Meetings</span>
      </a>
      <a href="#" class="nav-item relative" id="notifBell" <?php if($unread_count > 0): ?>onclick="openNotifications(); markAsRead();"<?php endif; ?>>
        <i data-lucide="bell"></i>
        <span class="nav-text">Notifications</span>
        <?php if($unread_count > 0): ?>
          <span id="notifBadge" class="absolute top-3 right-3 bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?= $unread_count ?></span>
        <?php endif; ?>
      </a>
    </nav>

    <div class="p-4 border-t dark:border-slate-800 space-y-2">
      <button onclick="toggleTheme()" class="w-full nav-item">
        <i data-lucide="moon" id="themeIcon"></i>
        <span class="nav-text">Dark Mode</span>
      </button>
      <a href="../logout.php" class="nav-item text-red-600">
        <i data-lucide="log-out"></i>
        <span class="nav-text">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main Content Area -->
  <main class="flex-1 p-6 overflow-y-auto">
    <div class="max-w-7xl mx-auto">
      <!-- Dashboard Section -->
      <section id="dashboard" class="space-y-8">
        <div class="flex justify-between items-center">
          <h1 class="text-3xl font-bold text-blue-800 dark:text-blue-200">Welcome back, <?= explode(' ', $student['name'])[0] ?>!</h1>
          <button id="rightSidebarToggle" class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg">
            <i data-lucide="menu" class="w-5 h-5"></i>
          </button>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="content-card">
            <div class="flex justify-between items-center">
              <div>
                <p class="text-blue-600 font-semibold">Active Units</p>
                <p class="text-3xl font-bold mt-2"><?= $units_count ?></p>
              </div>
              <i data-lucide="book-open" class="w-12 h-12 text-blue-500 opacity-30"></i>
            </div>
          </div>
          
          <div class="content-card">
            <div class="flex justify-between items-center">
              <div>
                <p class="text-orange-600 font-semibold">Assignments Due</p>
                <p class="text-3xl font-bold mt-2"><?= $assignments_due ?></p>
              </div>
              <i data-lucide="clock" class="w-12 h-12 text-orange-500 opacity-30"></i>
            </div>
          </div>
          
          <div class="content-card">
            <div class="flex justify-between items-center">
              <div>
                <p class="text-green-600 font-semibold">Meetings</p>
                <p class="text-3xl font-bold mt-2"><?= $meetings_count ?></p>
              </div>
              <i data-lucide="video" class="w-12 h-12 text-green-500 opacity-30"></i>
            </div>
          </div>
          
          <div class="content-card bg-gradient-to-br from-blue-500 to-indigo-600 text-white cursor-pointer" onclick="confettiBurst()">
            <div class="flex justify-between items-center">
              <div>
                <p class="opacity-90">Study Streak</p>
                <p class="text-4xl font-bold mt-2 streak-fire">14</p>
              </div>
              <i data-lucide="flame" class="w-12 h-12"></i>
            </div>
          </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="content-card">
          <h2 class="text-xl font-bold mb-4">Recent Activity</h2>
          <div class="space-y-4">
            <div class="flex items-center space-x-4 p-3 bg-blue-50 dark:bg-slate-700 rounded-lg">
              <div class="bg-blue-100 dark:bg-slate-600 p-2 rounded-full">
                <i data-lucide="file-text" class="w-5 h-5 text-blue-500"></i>
              </div>
              <div>
                <p class="font-medium">Submitted Database Assignment</p>
                <p class="text-sm text-gray-500">2 hours ago</p>
              </div>
            </div>
            <div class="flex items-center space-x-4 p-3 bg-blue-50 dark:bg-slate-700 rounded-lg">
              <div class="bg-green-100 dark:bg-slate-600 p-2 rounded-full">
                <i data-lucide="book-open" class="w-5 h-5 text-green-500"></i>
              </div>
              <div>
                <p class="font-medium">Completed Algorithms Chapter</p>
                <p class="text-sm text-gray-500">Yesterday</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Courses Section -->
      <section id="courses" class="hidden">
        <h2 class="text-2xl font-bold mb-6 text-blue-800 dark:text-blue-200">Your Courses</h2>
        <div class="card-grid">
          <?php
          $courses_query = $conn->prepare("SELECT id, name, code, description FROM units WHERE course_id = ? AND year = ?");
          $courses_query->bind_param("ii", $course_id, $year_of_study);
          $courses_query->execute();
          $courses = $courses_query->get_result();
          
          while ($course = $courses->fetch_assoc()) {
            echo "<div class='unit-tile'>
              <div class='flex justify-between items-start mb-4'>
                <h3 class='text-xl font-bold text-blue-800 dark:text-blue-200'>" . htmlspecialchars($course['name']) . "</h3>
                <span class='bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full'>" . htmlspecialchars($course['code']) . "</span>
              </div>
              <p class='text-gray-600 dark:text-gray-300 mb-4'>" . htmlspecialchars($course['description']) . "</p>
              <div class='flex justify-between items-center'>
                <div class='w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700'>
                  <div class='bg-blue-600 h-2.5 rounded-full' style='width: 65%'></div>
                </div>
                <span class='ml-2 text-sm font-medium text-blue-600'>65%</span>
              </div>
            </div>";
          }
          ?>
        </div>
      </section>

      <!-- Assignments Section -->
      <section id="assignments" class="hidden">
        <h2 class="text-2xl font-bold mb-6 text-blue-800 dark:text-blue-200">Your Assignments</h2>
        <div class="card-grid">
          <?php
          $assignments_query = $conn->prepare("SELECT a.id, a.title, a.description, a.deadline, a.file_path, u.name AS unit_name FROM assignments a JOIN units u ON a.unit_id = u.id WHERE u.course_id = ? AND u.year = ? ORDER BY a.deadline ASC");
          $assignments_query->bind_param("ii", $course_id, $year_of_study);
          $assignments_query->execute();
          $assignments = $assignments_query->get_result();
          
          while ($assignment = $assignments->fetch_assoc()) {
            $days_left = floor((strtotime($assignment['deadline']) - time()) / (60 * 60 * 24));
            $status_class = $days_left < 0 ? 'bg-red-100 text-red-800' : ($days_left <= 3 ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800');
            $status_text = $days_left < 0 ? 'Overdue' : ($days_left == 0 ? 'Due today' : ($days_left == 1 ? '1 day left' : "$days_left days left"));
            
            echo "<div class='unit-tile'>
              <div class='flex justify-between items-start mb-2'>
                <h3 class='text-xl font-bold text-blue-800 dark:text-blue-200'>" . htmlspecialchars($assignment['title']) . "</h3>
                <span class='text-xs font-medium px-2.5 py-0.5 rounded-full $status_class'>$status_text</span>
              </div>
              <p class='text-gray-600 dark:text-gray-300 mb-2'>" . htmlspecialchars($assignment['unit_name']) . "</p>
              <p class='text-gray-500 text-sm mb-4'>Due: " . date('M j, Y g:i A', strtotime($assignment['deadline'])) . "</p>
              <div class='flex space-x-2'>
                <button class='btn-primary text-sm'>View Details</button>
                <button class='bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-2 rounded-lg text-sm font-medium transition'>Submit Work</button>
              </div>
            </div>";
          }
          ?>
        </div>
      </section>

      <!-- Notes Section -->
      <section id="notes" class="hidden">
        <h2 class="text-2xl font-bold mb-6 text-blue-800 dark:text-blue-200">Course Notes</h2>
        <div class="card-grid">
          <?php
          $notes_query = $conn->prepare("SELECT n.id, n.title, n.description, n.file_path, n.uploaded_at, u.name AS unit_name FROM notes n JOIN units u ON n.unit_id = u.id WHERE u.course_id = ? AND u.year = ? ORDER BY n.uploaded_at DESC");
          $notes_query->bind_param("ii", $course_id, $year_of_study);
          $notes_query->execute();
          $notes = $notes_query->get_result();
          
          while ($note = $notes->fetch_assoc()) {
            echo "<div class='unit-tile'>
              <div class='flex justify-between items-start mb-2'>
                <h3 class='text-xl font-bold text-blue-800 dark:text-blue-200'>" . htmlspecialchars($note['title']) . "</h3>
                <i data-lucide='file-text' class='w-6 h-6 text-blue-500'></i>
              </div>
              <p class='text-gray-600 dark:text-gray-300 mb-2'>" . htmlspecialchars($note['unit_name']) . "</p>
              <p class='text-gray-500 text-sm mb-4'>Uploaded: " . date('M j, Y', strtotime($note['uploaded_at'])) . "</p>
              <div class='flex space-x-2'>
                <button class='btn-primary text-sm'>View Notes</button>
                <button class='bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-2 rounded-lg text-sm font-medium transition'>Download</button>
              </div>
            </div>";
          }
          ?>
        </div>
      </section>
    </div>
  </main>

  <!-- Right Sidebar -->
  <aside id="rightSidebar" class="right-sidebar w-80 bg-white dark:bg-slate-900 shadow-lg h-screen overflow-y-auto fixed top-0 right-0 z-30">
    <div class="p-6 border-b dark:border-slate-800 flex justify-between items-center">
      <h2 class="text-xl font-bold">Quick Access</h2>
      <button id="closeRightSidebar" class="text-gray-500 hover:text-gray-700">
        <i data-lucide="x" class="w-6 h-6"></i>
      </button>
    </div>
    
    <div class="p-6">
      <div class="mb-8">
        <h3 class="text-lg font-semibold mb-4">Academic</h3>
        <div class="space-y-3">
          <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 transition">
            <i data-lucide="book-open" class="w-5 h-5 text-blue-500"></i>
            <span>My Courses</span>
          </a>
          <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 transition">
            <i data-lucide="file-text" class="w-5 h-5 text-blue-500"></i>
            <span>Read Notes</span>
          </a>
          <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 transition">
            <i data-lucide="bar-chart" class="w-5 h-5 text-blue-500"></i>
            <span>Grades</span>
          </a>
        </div>
      </div>
      
      <div class="mb-8">
        <h3 class="text-lg font-semibold mb-4">Resources</h3>
        <div class="space-y-3">
          <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 transition">
            <i data-lucide="folder" class="w-5 h-5 text-blue-500"></i>
            <span>Notes & Files</span>
          </a>
          <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 transition">
            <i data-lucide="library" class="w-5 h-5 text-blue-500"></i>
            <span>Library Services</span>
          </a>
        </div>
      </div>
      
      <div class="mb-8">
        <h3 class="text-lg font-semibold mb-4">Account</h3>
        <div class="space-y-3">
          <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 transition">
            <i data-lucide="user" class="w-5 h-5 text-blue-500"></i>
            <span>My Profile</span>
          </a>
          <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 transition">
            <i data-lucide="settings" class="w-5 h-5 text-blue-500"></i>
            <span>Settings</span>
          </a>
        </div>
      </div>
    </div>
  </aside>

  <!-- Meetings Modal -->
  <div id="meetingsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Upcoming Meetings</h2>
        <button onclick="document.getElementById('meetingsModal').classList.add('hidden')">
          <i data-lucide="x" class="w-8 h-8"></i>
        </button>
      </div>
      <div class="space-y-4">
        <?php
        $meeting_query = $conn->prepare("SELECT m.title, m.scheduled_time, u.name AS unit_name FROM meetings m JOIN units u ON m.unit_id = u.id WHERE u.course_id = ? AND u.year = ? AND m.scheduled_time >= NOW() ORDER BY m.scheduled_time ASC");
        $meeting_query->bind_param("ii", $course_id, $year_of_study);
        $meeting_query->execute();
        $meetings = $meeting_query->get_result();
        
        while ($meeting = $meetings->fetch_assoc()) {
          echo "<div class='p-4 rounded-xl border border-gray-200 dark:border-slate-700'>
            <h4 class='font-bold text-lg'>" . htmlspecialchars($meeting['title']) . "</h4>
            <p class='text-gray-600 dark:text-gray-300 mt-1'>" . htmlspecialchars($meeting['unit_name']) . "</p>
            <div class='flex justify-between items-center mt-3'>
              <span class='text-sm text-gray-500'>" . date('M j, Y g:i A', strtotime($meeting['scheduled_time'])) . "</span>
              <button class='btn-primary text-sm'>Join Meeting</button>
            </div>
          </div>";
        }
        
        if ($meetings->num_rows === 0) {
          echo "<p class='text-center text-gray-500 py-8'>No upcoming meetings</p>";
        }
        ?>
      </div>
    </div>
  </div>

  <!-- Notifications Modal -->
  <div id="notificationsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Notifications</h2>
        <button onclick="document.getElementById('notificationsModal').classList.add('hidden')">
          <i data-lucide="x" class="w-8 h-8"></i>
        </button>
      </div>
      <div class="space-y-4">
        <?php if (empty($notifications)): ?>
          <p class="text-center text-gray-500 py-8">No notifications yet.</p>
        <?php else: 
          foreach ($notifications as $notif): ?>
            <div class="p-4 rounded-xl border <?= !$notif['is_read'] ? 'notification-unread' : 'border-gray-200 dark:border-slate-700' ?>">
              <h4 class="font-bold"><?= htmlspecialchars($notif['title']) ?></h4>
              <p class="text-sm mt-1"><?= htmlspecialchars($notif['message']) ?></p>
              <div class="flex justify-between items-center mt-3">
                <span class="text-xs text-gray-500"><?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?></span>
                <?php if (!empty($notif['link'])): ?>
                  <a href="<?= htmlspecialchars($notif['link']) ?>" class="text-blue-600 text-sm font-medium hover:underline">View</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; 
        endif; ?>
      </div>
    </div>
  </div>

  <script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Toggle profile card
    function toggleProfileCard() {
      document.getElementById('profileCard').classList.toggle('hidden');
    }

    // Navigation functionality
    document.querySelectorAll('.nav-item').forEach(item => {
      item.addEventListener('click', function(e) {
        if (this.getAttribute('data-section') === 'meetings') {
          e.preventDefault();
          document.getElementById('meetingsModal').classList.remove('hidden');
          return;
        }
        
        if (this.getAttribute('data-section') === 'notifications') {
          e.preventDefault();
          document.getElementById('notificationsModal').classList.remove('hidden');
          return;
        }
        
        e.preventDefault();
        document.querySelectorAll('section').forEach(section => {
          section.classList.add('hidden');
        });
        
        document.querySelectorAll('.nav-item').forEach(nav => {
          nav.classList.remove('active');
        });
        
        this.classList.add('active');
        const sectionId = this.getAttribute('data-section');
        document.getElementById(sectionId).classList.remove('hidden');
      });
    });

    // Right sidebar toggle
    document.getElementById('rightSidebarToggle').addEventListener('click', function() {
      document.getElementById('rightSidebar').classList.add('open');
    });

    document.getElementById('closeRightSidebar').addEventListener('click', function() {
      document.getElementById('rightSidebar').classList.remove('open');
    });

    // Mobile menu toggle
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
      document.getElementById('sidebar').classList.toggle('open');
    });

    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          this.classList.add('hidden');
        }
      });
    });

    // Theme toggle
    function toggleTheme() {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
      document.getElementById('themeIcon').setAttribute('data-lucide', 
        document.documentElement.classList.contains('dark') ? 'sun' : 'moon');
      lucide.createIcons();
    }

    // Apply saved theme
    if (localStorage.getItem('theme') === 'dark') {
      document.documentElement.classList.add('dark');
      document.getElementById('themeIcon').setAttribute('data-lucide', 'sun');
      lucide.createIcons();
    }

    // Confetti effect
    function confettiBurst() {
      for(let i = 0; i < 80; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.background = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'][Math.floor(Math.random() * 4)];
        confetti.style.animationDelay = Math.random() * 2 + 's';
        document.body.appendChild(confetti);
        setTimeout(() => confetti.remove(), 4000);
      }
    }

    // Mark notifications as read
    function markAsRead() {
      fetch('mark_notifications_read.php')
        .then(() => {
          document.getElementById('notifBadge')?.remove();
        });
    }
  </script>
</body>
</html>