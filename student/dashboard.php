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
    :root { --amber: #f59e0b; --brown: #92400e; }
    .dark { background: #0f172a; color: #e2e8f0; }
    .sidebar-collapsed { width: 80px !important; }
    .sidebar-collapsed .nav-text, .sidebar-collapsed .logo-text { opacity: 0; transform: translateX(-20px); }
    .nav-item { @apply flex items-center space-x-4 px-6 py-4 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-amber-50 dark:hover:bg-slate-800 transition relative cursor-pointer; }
    .nav-item.active { @apply bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-lg; }
    .nav-text, .logo-text { @apply transition-all duration-300; }
    .unit-tile { @apply bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 cursor-pointer border border-amber-100 dark:border-slate-700; }
    .close-modal { @apply absolute top-4 right-6 bg-red-500 hover:bg-red-600 text-white w-10 h-10 rounded-full flex items-center justify-center text-xl font-bold shadow-lg; }
    .btn-primary { @apply bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-medium transition; }
    .streak-fire { animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }
    .confetti { position: fixed; width: 10px; height: 10px; animation: fall 3s linear forwards; z-index: 9999; pointer-events: none; }
    @keyframes fall { to { transform: translateY(100vh) rotate(720deg); opacity: 0; } }
    .notification-unread { @apply bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500; }
  </style>
</head>
<body class="min-h-screen">

  <!-- Static Left Sidebar -->
  <aside id="sidebar" class="fixed left-0 top-0 h-full bg-white dark:bg-slate-900 shadow-2xl w-80 lg:w-64 transition-all duration-300 z-50 flex flex-col">
    <div class="p-6 border-b dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center space-x-4 cursor-pointer" onclick="document.getElementById('profileCall').classList.toggle('hidden')">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?= $student['reg_no'] ?>" class="w-14 h-14 rounded-full ring-4 ring-amber-500/20">
        <div class="overflow-hidden">
          <p class="font-bold text-lg logo-text"><?= htmlspecialchars($student['name']) ?></p>
          <p class="text-sm text-gray-500 logo-text">Year <?= $year_of_study ?></p>
        </div>
      </div>
      <button onclick="toggleSidebar()" class="hidden lg:block p-2 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg">
        <i data-lucide="chevrons-left" id="collapseIcon" class="w-6 h-6"></i>
      </button>
    </div>

    <div id="profileCard" class="hidden bg-amber-50 dark:bg-slate-800 p-6 border-b dark:border-slate-700 text-sm">
      <p><strong>Reg No:</strong> <?= htmlspecialchars($student['reg_no']) ?></p>
      <p><strong>Course:</strong> <?= htmlspecialchars($course_name) ?></p>
      <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-2">
      <a href="#" class="nav-item active" data-section="dashboard"><i data-lucide="layout-dashboard"></i><span class="nav-text">Dashboard</span></a>
      <a href="#" class="nav-item" data-section="assignments"><i data-lucide="clipboard-list"></i><span class="nav-text">Assignments</span></a>
      <a href="#" class="nav-item" data-section="notes"><i data-lucide="file-text"></i><span class="nav-text">Notes</span></a>
      <a href="#" class="nav-item" data-section="meetings"><i data-lucide="video"></i><span class="nav-text">Meetings</span></a>
      <a href="#" class="nav-item relative" id="notifBell" <?php if($unread_count > 0): ?>onclick="openNotifications(); markAsRead();"<?php endif; ?>>
        <i data-lucide="bell"></i><span class="nav-text">Notifications</span>
        <?php if($unread_count > 0): ?><span id="notifBadge" class="absolute top-3 right-3 bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?= $unread_count ?></span><?php endif; ?>
      </a>
    </nav>

    <div class="p-4 border-t dark:border-slate-800 space-y-2">
      <button onclick="toggleTheme()" class="w-full nav-item"><i data-lucide="moon" id="themeIcon"></i><span class="nav-text">Dark Mode</span></button>
      <a href="../logout.php" class="nav-item text-red-600"><i data-lucide="log-out"></i><span class="nav-text">Logout</span></a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="lg:ml-64 transition-all duration-300 p-6 min-h-screen">
    <div class="max-w-7xl mx-auto">

      <!-- Dashboard -->
      <section id="dashboard" class="space-y-10">
        <h1 class="text-4xl font-bold text-92400e">Welcome back, <?= explode(' ', $student['name'])[0] ?>!</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-lg"><div class="flex justify-between"><p class="text-amber-600 font-semibold">Active Units</p><p class="text-4xl font-bold"><?= $units_count ?></p></div><i data-lucide="book-open" class="w-12 h-12 text-amber-500 opacity-30"></i></div>
          <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-lg"><div class="flex justify-between"><p class="text-orange-600 font-semibold">Assignments Due</p><p class="text-4xl font-bold"><?= $assignments_due ?></p></div><i data-lucide="clock" class="w-12 h-12 text-orange-500 opacity-30"></i></div>
          <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-lg"><div class="flex justify-between"><p class="text-green-600 font-semibold">Meetings</p><p class="text-4xl font-bold"><?= $meetings_count ?></p></div><i data-lucide="video" class="w-12 h-12 text-green-500 opacity-30"></i></div>
          <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white p-6 rounded-2xl shadow-lg cursor-pointer" onclick="confettiBurst()"><div class="flex justify-between"><p class="opacity-90">Streak</p><p class="text-5xl font-bold streak-fire">14</p></div><i data-lucide="flame" class="w-16 h-16"></i></div>
        </div>
      </section>

      <!-- Assignments: Tiles → Modal -->
      <section id="assignments" class="hidden">
        <h2 class="text-3xl font-bold mb-8 text-92400e">Assignments by Unit</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php
          $assignments_query = $conn->prepare("SELECT a.id, a.title, a.description, a.deadline, a.file_path, u.name AS unit_name FROM assignments a JOIN units u ON a.unit_id = u.id WHERE u.course_id = ? AND u.year = ? ORDER BY u.name ASC");
          $assignments_query->bind_param("ii", $course_id, $year_of_study);
          $assignments_query->execute();
          $assignments = $assignments_query->get_result();
          $units = [];
          while ($a = $assignments->fetch_assoc()) $units[$a['unit_name']][] = $a;
          foreach ($units as $unitName => $unitAssignments) {
              $modalId = "assign-modal-" . preg_replace('/[^a-z0-9]/i', '', $unitName);
              echo "<div class='unit-tile' onclick=\"document.getElementById('$modalId').classList.remove('hidden')\">
                <h3 class='text-xl font-bold text-92400e'>" . htmlspecialchars($unitName) . "</h3>
                <p class='text-sm text-gray-600 mt-2'>" . count($unitAssignments) . " assignment" . (count($unitAssignments)>1?'s':'') . "</p>
                <div class='mt-4 text-right'><span class='text-amber-600 font-medium'>View</span></div>
              </div>

              <div id='$modalId' class='fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden'>
                <div class='bg-white dark:bg-slate-800 p-8 rounded-3xl w-11/12 max-w-4xl max-h-[85vh] overflow-y-auto relative'>
                  <h3 class='text-2xl font-bold mb-6'>Assignments — " . htmlspecialchars($unitName) . "</h3>
                  <button class='close-modal' onclick=\"this.closest('.modal').classList.add('hidden')\">X</button>
                  <table class='w-full text-left border-collapse'><thead><tr class='border-b-2 border-amber-200'><th class='py-3'>Title</th><th class='py-3'>Deadline</th><th class='py-3'>Actions</th></tr></thead><tbody>";
                  foreach ($unitAssignments as $a) {
                      $filePath = !empty($a['file_path']) ? "../assets/uploads/assignments/" . htmlspecialchars($a['file_path']) : '';
                      $actions = $filePath && file_exists($filePath) ? "<a href='$filePath' target='_blank' class='text-amber-600 hover:underline'>View</a> | <a href='$filePath' download>Download</a>" : "No file";
                      $form = "<form method='POST' enctype='multipart/form-data' action='submit_assignment.php' class='mt-3'><input type='hidden' name='assignment_id' value='{$a['id']}'><input type='file' name='file' required class='text-sm'><button type='submit' class='btn-primary ml-2'>Submit</button></form>";
                      echo "<tr class='border-b'><td class='py-4'>" . htmlspecialchars($a['title']) . "</td><td class='py-4'>" . date('d M Y, h:i A', strtotime($a['deadline'])) . "</td><td class='py-4'>$actions<br>$form</td></tr>";
                  }
                  echo "</tbody></table></div></div>";
          }
          ?>
        </div>
      </section>

      <!-- Notes: Tiles → Modal -->
      <section id="notes" class="hidden">
        <h2 class="text-3xl font-bold mb-8 text-92400e">Notes by Unit</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php
          $notes_query = $conn->prepare("SELECT n.file_path, n.uploaded_at, u.name AS unit_name, u.code FROM notes n JOIN units u ON n.unit_id = u.id WHERE u.course_id = ? AND u.year = ? ORDER BY u.name ASC");
          $notes_query->bind_param("ii", $course_id, $year_of_study);
          $notes_query->execute();
          $notes = $notes_query->get_result();
          $units = [];
          while ($n = $notes->fetch_assoc()) $units[$n['unit_name']][] = $n;
          foreach ($units as $unitName => $unitNotes) {
              $modalId = "notes-modal-" . preg_replace('/[^a-z0-9]/i', '', $unitName);
              echo "<div class='unit-tile' onclick=\"document.getElementById('$modalId').classList.remove('hidden')\">
                <h3 class='text-xl font-bold text-92400e'>" . htmlspecialchars($unitName) . "</h3>
                <p class='text-sm text-gray-600 mt-2'>" . count($unitNotes) . " note" . (count($unitNotes)>1?'s':'') . "</p>
                <div class='mt-4 text-right'><span class='text-amber-600 font-medium'>View</span></div>
              </div>

              <div id='$modalId' class='fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden'>
                <div class='bg-white dark:bg-slate-800 p-8 rounded-3xl w-11/12 max-w-4xl max-h-[85vh] overflow-y-auto relative'>
                  <h3 class='text-2xl font-bold mb-6'>Notes — " . htmlspecialchars($unitName) . "</h3>
                  <button class='close-modal' onclick=\"this.closest('.modal').classList.add('hidden')\">X</button>
                  <table class='w-full text-left border-collapse'><thead><tr class='border-b-2 border-amber-200'><th class='py-3'>Code</th><th class='py-3'>File</th><th class='py-3'>Uploaded</th><th class='py-3'>Actions</th></tr></thead><tbody>";
                  foreach ($unitNotes as $n) {
                      $path = "../assets/uploads/" . htmlspecialchars($n['file_path']);
                      $actions = file_exists($path) ? "<a href='$path' target='_blank' class='text-amber-600 hover:underline'>View</a> | <a href='$path' download>Download</a>" : "<span class='text-red-500'>Missing</span>";
                      echo "<tr class='border-b'><td class='py-4'>{$n['code']}</td><td class='py-4'>" . htmlspecialchars($n['file_path']) . "</td><td class='py-4'>" . date('d M Y', strtotime($n['uploaded_at'])) . "</td><td class='py-4'>$actions</td></tr>";
                  }
                  echo "</tbody></table></div></div>";
          }
          ?>
        </div>
      </section>

      <!-- Meetings Modal -->
      <div id="meetingsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-8 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
          <div class="flex justify-between items-center mb-6"><h2 class="text-2xl font-bold">Upcoming Meetings</h2><button onclick="this.closest('#meetingsModal').classList.add('hidden')"><i data-lucide="x" class="w-8 h-8"></i></button></div>
          <table class="w-full"><thead><tr class="border-b-2 border-amber-200"><th class="py-3 text-left">Title</th><th class="py-3 text-left">Unit</th><th class="py-3 text-left">Time</th><th class="py-3 text-left">Join</th></tr></thead><tbody>
            <?php
            $meeting_query = $conn->prepare("SELECT m.title, m.scheduled_time, u.name AS unit_name FROM meetings m JOIN units u ON m.unit_id = u.id WHERE u.course_id = ? AND u.year = ? AND m.scheduled_time >= NOW() ORDER BY m.scheduled_time ASC");
            $meeting_query->bind_param("ii", $course_id, $year_of_study);
            $meeting_query->execute();
            $meetings = $meeting_query->get_result();
            while ($m = $meetings->fetch_assoc()) {
                echo "<tr class='border-b'><td class='py-4'>" . htmlspecialchars($m['title']) . "</td><td class='py-4'>" . htmlspecialchars($m['unit_name']) . "</td><td class='py-4'>" . date('d M Y, h:i A', strtotime($m['scheduled_time'])) . "</td><td class='py-4'><a href='meeting_ide.php?meeting_id=1' target='_blank' class='text-amber-600 font-semibold hover:underline'>Join</a></td></tr>";
            }
            if ($meetings->num_rows === 0) echo "<tr><td colspan='4' class='py-8 text-center text-gray-500'>No upcoming meetings</td></tr>";
            ?>
          </tbody></table>
        </div>
      </div>

      <!-- Notifications Modal -->
      <div id="notificationsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-8 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">Notifications</h2>
            <button onclick="document.getElementById('notificationsModal').classList.add('hidden')"><i data-lucide="x" class="w-8 h-8"></i></button>
          </div>
          <div class="space-y-4">
            <?php if (empty($notifications)): ?>
              <p class="text-center text-gray-500 py-8">No notifications yet.</p>
            <?php else: foreach ($notifications as $notif): ?>
              <div class="p-4 rounded-xl border <?= !$notif['is_read'] ? 'notification-unread' : 'border-gray-200 dark:border-slate-700' ?>">
                <h4 class="font-bold text-92400e"><?= htmlspecialchars($notif['title']) ?></h4>
                <p class="text-sm mt-1"><?= htmlspecialchars($notif['message']) ?></p>
                <div class="flex justify-between items-center mt-3">
                  <span class="text-xs text-gray-500"><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></span>
                  <?php if (!empty($notif['link'])): ?>
                    <a href="<?= htmlspecialchars($notif['link']) ?>" class="text-amber-600 text-sm font-medium hover:underline">View</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();

    function toggleSidebar() {
      const s = document.getElementById('sidebar');
      s.classList.toggle('sidebar-collapsed');
      document.getElementById('collapseIcon').setAttribute('data-lucide', s.classList.contains('sidebar-collapsed') ? 'chevrons-right' : 'chevrons-left');
      lucide.createIcons();
    }

    function openNotifications() {
      document.getElementById('notificationsModal').classList.remove('hidden');
    }

    function markAsRead() {
      fetch('mark_notifications_read.php')
        .then(() => {
          document.getElementById('notifBadge')?.remove();
        });
    }

    document.querySelectorAll('.nav-item').forEach(item => {
      item.addEventListener('click', function(e) {
        if (this.getAttribute('data-section') === 'notifications') return;
        e.preventDefault();
        document.querySelectorAll('section[id], #meetingsModal, #notificationsModal').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        this.classList.add('active');
        const sec = this.getAttribute('data-section');
        if (sec === 'meetings') document.getElementById('meetingsModal').classList.remove('hidden');
        else document.getElementById(sec).classList.remove('hidden');
      });
    });

    document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.add('hidden'); }));

    function toggleTheme() {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', document.documentElement.classList.contains('dark')?'dark':'light');
      document.getElementById('themeIcon').setAttribute('data-lucide', document.documentElement.classList.contains('dark')?'sun':'moon');
      lucide.createIcons();
    }
    if (localStorage.getItem('theme') === 'dark') toggleTheme();

    function confettiBurst() {
      for(let i=0;i<80;i++){
        const c=document.createElement('div'); c.className='confetti';
        c.style.left=Math.random()*100+'vw';
        c.style.background=['#f59e0b','#10b981','#92400e','#ef4444'][Math.floor(Math.random()*4)];
        c.style.animationDelay=Math.random()*2+'s';
        document.body.appendChild(c);
        setTimeout(()=>c.remove(),4000);
      }
    }
  </script>
</body>
</html>