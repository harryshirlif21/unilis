<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a student
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
    if (!$student) throw new Exception("Student not found.");
    $course_id = $student['course_id'];
    $year_of_study = $student['year_of_study'];

    $course_stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course = $course_stmt->get_result()->fetch_assoc();
    $course_name = $course ? $course['name'] : 'Unknown Course';

    // Full Notifications + Count
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
            LEFT JOIN units u ON u.id = COALESCE(nt.unit_id, a.unit_id, ia.unit_id, m.unit_id)
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
      --golden: #f59e0b;
      --blue: #3b82f6;
      --green: #10b981;
      --orange: #f97316;
      --lime: #84cc16;
      --brown: #92400e;
    }
    .dark { background: #0f172a; color: #e2e8f0; }
    body { font-family: 'Inter', sans-serif; }

    /* Sidebar - Your Original Rich Brown Gradient */
    #sidebar {
      width: 280px;
      background: linear-gradient(180deg, #92400e 0%, #7c2d12 100%);
      color: white;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .sidebar-collapsed { width: 80px !important; }
    .sidebar-collapsed .nav-text,
    .sidebar-collapsed .logo-text,
    .sidebar-collapsed .section-title { opacity: 0; transform: translateX(-20px); pointer-events: none; }

    /* Colored Menu Items */
    .nav-dashboard { color: var(--golden) !important; }
    .nav-dashboard.active, .nav-dashboard:hover { background: rgba(245, 158, 11, 0.25) !important; }
    .nav-dashboard i { color: var(--golden) !important; }

    .nav-assignments { color: var(--blue) !important; }
    .nav-assignments.active, .nav-assignments:hover { background: rgba(59, 130, 246, 0.25) !important; }
    .nav-assignments i { color: var(--blue) !important; }

    .nav-notes { color: var(--green) !important; }
    .nav-notes.active, .nav-notes:hover { background: rgba(16, 185, 129, 0.25) !important; }
    .nav-notes i { color: var(--green) !important; }

    .nav-meetings { color: var(--orange) !important; }
    .nav-meetings.active, .nav-meetings:hover { background: rgba(249, 115, 22, 0.25) !important; }
    .nav-meetings i { color: var(--orange) !important; }

    .nav-notifications { color: var(--lime) !important; }
    .nav-notifications.active, .nav-notifications:hover { background: rgba(132, 204, 22, 0.25) !important; }
    .nav-notifications i { color: var(--lime) !important; }

    .nav-item {
      @apply flex items-center space-x-4 px-6 py-5 rounded-xl transition-all duration-300 relative cursor-pointer font-medium text-lg;
    }
    .nav-item.active {
      @apply shadow-xl backdrop-blur-sm font-bold scale-105;
    }

    .section-title {
      @apply text-amber-300 text-xs font-bold uppercase tracking-wider mt-8 mb-4 px-6;
    }

    .unit-tile {
      @apply bg-white dark:bg-slate-800 rounded-3xl p-10 shadow-2xl hover:shadow-3xl hover:-translate-y-4 transition-all duration-400 cursor-pointer border-4 border-transparent hover:border-amber-400;
    }

    .close-modal {
      @apply absolute top-6 right-6 bg-red-600 hover:bg-red-700 text-white w-14 h-14 rounded-full flex items-center justify-center text-3xl font-bold shadow-2xl z-10;
    }

    .confetti {
      position: fixed; width: 12px; height: 12px; animation: fall 3s linear forwards; z-index: 9999; pointer-events: none;
    }
    @keyframes fall { to { transform: translateY(100vh) rotate(720deg); opacity: 0; } }
  </style>
</head>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen">

  <!-- Static Left Sidebar with Colored Items -->
  <aside id="sidebar" class="fixed left-0 top-0 h-full text-white flex flex-col z-50">
    <div class="p-6 border-b border-white/10 flex items-center justify-between">
      <div class="flex items-center space-x-4 cursor-pointer" onclick="document.getElementById('profileCard').classList.toggle('hidden')">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?= $student['reg_no'] ?>" class="w-16 h-16 rounded-full ring-4 ring-amber-400">
        <div>
          <p class="font-bold text-xl logo-text"><?= htmlspecialchars($student['name']) ?></p>
          <p class="text-sm opacity-90 logo-text">Year <?= $year_of_study ?></p>
        </div>
      </div>
      <button onclick="toggleSidebar()" class="hidden lg:block p-3 hover:bg-white/10 rounded-xl">
        <i data-lucide="chevrons-left" id="collapseIcon" class="w-7 h-7"></i>
      </button>
    </div>

    <div id="profileCard" class="hidden bg-white/10 backdrop-blur p-6 border-b border-white/10 text-sm">
      <p><strong>Reg No:</strong> <?= htmlspecialchars($student['reg_no']) ?></p>
      <p><strong>Course:</strong> <?= htmlspecialchars($course_name) ?></p>
      <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-2">
      <div class="section-title">Main Menu</div>
      <a href="#" class="nav-item nav-dashboard active" data-section="dashboard">
        <i data-lucide="layout-dashboard"></i>
        <span class="nav-text">Dashboard</span>
      </a>
      <a href="#" class="nav-item nav-assignments" data-section="assignments">
        <i data-lucide="clipboard-list"></i>
        <span class="nav-text">Assignments</span>
      </a>
      <a href="#" class="nav-item nav-notes" data-section="notes">
        <i data-lucide="file-text"></i>
        <span class="nav-text">Notes</span>
      </a>
      <a href="#" class="nav-item nav-meetings" data-section="meetings">
        <i data-lucide="video"></i>
        <span class="nav-text">Meetings</span>
      </a>
      <a href="#" class="nav-item nav-notifications relative" id="notifBell">
        <i data-lucide="bell"></i>
        <span class="nav-text">Notifications</span>
        <?php if($unread_count > 0): ?>
          <span id="notifBadge" class="absolute top-4 right-4 bg-red-500 text-white text-xs px-2.5 py-1 rounded-full font-bold"><?= $unread_count ?></span>
        <?php endif; ?>
      </a>

      <div class="section-title">Account</div>
      <button onclick="toggleTheme()" class="w-full nav-item text-amber-300 hover:bg-white/10">
        <i data-lucide="moon" id="themeIcon"></i>
        <span class="nav-text">Dark Mode</span>
      </button>
      <a href="../logout.php" class="nav-item text-red-400 hover:bg-red-600/30">
        <i data-lucide="log-out"></i>
        <span class="nav-text">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="lg:ml-80 transition-all duration-300 p-8 min-h-screen">
    <div class="max-w-7xl mx-auto">

      <!-- Dashboard -->
      <section id="dashboard" class="space-y-12">
        <h1 class="text-5xl font-extrabold text-92400e">Welcome back, <?= explode(' ', $student['name'])[0] ?>!</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
          <div class="bg-white dark:bg-slate-800 p-10 rounded-3xl shadow-2xl">
            <div class="flex justify-between items-center">
              <p class="text-amber-600 text-xl font-bold">Active Units</p>
              <p class="text-6xl font-extrabold"><?= $units_count ?></p>
            </div>
            <i data-lucide="book-open" class="w-20 h-20 text-amber-400 opacity-20"></i>
          </div>
          <div class="bg-white dark:bg-slate-800 p-10 rounded-3xl shadow-2xl">
            <div class="flex justify-between items-center">
              <p class="text-blue-600 text-xl font-bold">Assignments Due</p>
              <p class="text-6xl font-extrabold"><?= $assignments_due ?></p>
            </div>
            <i data-lucide="clock" class="w-20 h-20 text-blue-400 opacity-20"></i>
          </div>
          <div class="bg-white dark:bg-slate-800 p-10 rounded-3xl shadow-2xl">
            <div class="flex justify-between items-center">
              <p class="text-orange-600 text-xl font-bold">Meetings</p>
              <p class="text-6xl font-extrabold"><?= $meetings_count ?></p>
            </div>
            <i data-lucide="video" class="w-20 h-20 text-orange-400 opacity-20"></i>
          </div>
          <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white p-10 rounded-3xl shadow-2xl cursor-pointer" onclick="confettiBurst()">
            <div class="flex justify-between items-center">
              <p class="text-white/90 text-xl">Study Streak</p>
              <p class="text-7xl font-extrabold">14</p>
            </div>
            <i data-lucide="flame" class="w-28 h-28"></i>
          </div>
        </div>
      </section>

      <!-- Assignments: Tiles → Modal -->
      <section id="assignments" class="hidden">
        <h2 class="text-4xl font-extrabold mb-12 text-92400e">Assignments by Unit</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-10">
          <?php
          $assignments_query = $conn->prepare("SELECT a.id, a.title, a.description, a.deadline, a.file_path, u.name AS unit_name FROM assignments a JOIN units u ON a.unit_id = u.id WHERE u.course_id = ? AND u.year = ? ORDER BY u.name ASC");
          $assignments_query->bind_param("ii", $course_id, $year_of_study);
          $assignments_query->execute();
          $assignments = $assignments_query->get_result();

          $units = [];
          while ($a = $assignments->fetch_assoc()) {
              $units[$a['unit_name']][] = $a;
          }

          foreach ($units as $unitName => $unitAssignments) {
              $modalId = "assign-modal-" . preg_replace('/[^a-z0-9]/i', '', $unitName);
              echo "<div class='unit-tile' onclick=\"document.getElementById('$modalId').classList.remove('hidden')\">
                <h3 class='text-3xl font-extrabold text-92400e'>" . htmlspecialchars($unitName) . "</h3>
                <p class='text-xl text-gray-600 mt-4'>" . count($unitAssignments) . " assignment" . (count($unitAssignments)>1?'s':'') . "</p>
                <div class='mt-10 text-right'><span class='text-3xl font-bold text-blue-600'>View →</span></div>
              </div>

              <div id='$modalId' class='fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden'>
                <div class='bg-white dark:bg-slate-800 p-10 rounded-3xl w-11/12 max-w-5xl max-h-[90vh] overflow-y-auto relative shadow-2xl'>
                  <h3 class='text-3xl font-bold mb-8 text-92400e'>Assignments — " . htmlspecialchars($unitName) . "</h3>
                  <button class='close-modal' onclick=\"this.closest('.modal').classList.add('hidden')\">X</button>
                  <table class='w-full text-left border-collapse'>
                    <thead><tr class='border-b-4 border-amber-400'><th class='py-4 text-lg'>Title</th><th class='py-4 text-lg'>Deadline</th><th class='py-4 text-lg'>Actions</th></tr></thead>
                    <tbody>";
                    foreach ($unitAssignments as $a) {
                        $filePath = !empty($a['file_path']) ? "../assets/uploads/assignments/" . htmlspecialchars($a['file_path']) : '';
                        $actions = $filePath && file_exists($filePath) ? "<a href='$filePath' target='_blank' class='text-blue-600 font-bold'>View</a> | <a href='$filePath' download class='text-blue-600 font-bold'>Download</a>" : "No file";
                        $form = "<form method='POST' enctype='multipart/form-data' action='submit_assignment.php' class='mt-4 inline-flex items-center gap-3'>
                          <input type='hidden' name='assignment_id' value='{$a['id']}'><input type='file' name='file' accept='.pdf,.doc,.docx' required class='p-3 border rounded-lg text-sm'>
                          <button type='submit' class='bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold'>Submit</button>
                        </form>";
                        echo "<tr class='border-b'><td class='py-6 text-lg'>" . htmlspecialchars($a['title']) . "</td>
                              <td class='py-6 text-lg'>" . date('d M Y, h:i A', strtotime($a['deadline'])) . "</td>
                              <td class='py-6'>$actions<br>$form</td></tr>";
                    }
                    echo "</tbody></table></div></div>";
          }
          ?>
        </div>
      </section>

      <!-- Notes: Tiles → Modal -->
      <section id="notes" class="hidden">
        <h2 class="text-4xl font-extrabold mb-12 text-92400e">Notes by Unit</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-10">
          <?php
          $notes_query = $conn->prepare("SELECT n.file_path, n.uploaded_at, u.name AS unit_name, u.code FROM notes n JOIN units u ON n.unit_id = u.id WHERE u.course_id = ? AND u.year = ? ORDER BY u.name ASC");
          $notes_query->bind_param("ii", $course_id, $year_of_study);
          $notes_query->execute();
          $notes = $notes_query->get_result();

          $units = [];
          while ($n = $notes->fetch_assoc()) {
              $units[$n['unit_name']][] = $n;
          }

          foreach ($units as $unitName => $unitNotes) {
              $modalId = "notes-modal-" . preg_replace('/[^a-z0-9]/i', '', $unitName);
              echo "<div class='unit-tile' onclick=\"document.getElementById('$modalId').classList.remove('hidden')\">
                <h3 class='text-3xl font-extrabold text-92400e'>" . htmlspecialchars($unitName) . "</h3>
                <p class='text-xl text-gray-600 mt-4'>" . count($unitNotes) . " note" . (count($unitNotes)>1?'s':'') . "</p>
                <div class='mt-10 text-right'><span class='text-3xl font-bold text-green-600'>View →</span></div>
              </div>

              <div id='$modalId' class='fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden'>
                <div class='bg-white dark:bg-slate-800 p-10 rounded-3xl w-11/12 max-w-5xl max-h-[90vh] overflow-y-auto relative shadow-2xl'>
                  <h3 class='text-3xl font-bold mb-8 text-92400e'>Notes — " . htmlspecialchars($unitName) . "</h3>
                  <button class='close-modal' onclick=\"this.closest('.modal').classList.add('hidden')\">X</button>
                  <table class='w-full text-left border-collapse'>
                    <thead><tr class='border-b-4 border-amber-400'><th class='py-4 text-lg'>Code</th><th class='py-4 text-lg'>File</th><th class='py-4 text-lg'>Uploaded</th><th class='py-4 text-lg'>Actions</th></tr></thead>
                    <tbody>";
                    foreach ($unitNotes as $n) {
                        $path = "../assets/uploads/" . htmlspecialchars($n['file_path']);
                        $actions = file_exists($path) ? "<a href='$path' target='_blank' class='text-green-600 font-bold'>View</a> | <a href='$path' download class='text-green-600 font-bold'>Download</a>" : "<span class='text-red-500'>Missing</span>";
                        echo "<tr class='border-b'><td class='py-6 text-lg'>{$n['code']}</td>
                              <td class='py-6 text-lg'>" . htmlspecialchars($n['file_path']) . "</td>
                              <td class='py-6 text-lg'>" . date('d M Y', strtotime($n['uploaded_at'])) . "</td>
                              <td class='py-6'>$actions</td></tr>";
                    }
                    echo "</tbody></table></div></div>";
          }
          ?>
        </div>
      </section>

      <!-- Meetings Modal -->
      <div id="meetingsModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50">
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-10 max-w-3xl w-full max-h-[85vh] overflow-y-auto shadow-2xl">
          <div class="flex justify-between items-center mb-8"><h2 class="text-3xl font-bold">Upcoming Meetings</h2><button onclick="this.closest('#meetingsModal').classList.add('hidden')"><i data-lucide="x" class="w-10 h-10"></i></button></div>
          <table class="w-full"><thead><tr class="border-b-4 border-amber-400"><th class="py-4 text-left text-lg">Title</th><th class="py-4 text-left text-lg">Unit</th><th class="py-4 text-left text-lg">Time</th><th class="py-4 text-left text-lg">Join</th></tr></thead><tbody>
            <?php
            $meeting_query = $conn->prepare("SELECT m.title, m.scheduled_time, u.name AS unit_name FROM meetings m JOIN units u ON m.unit_id = u.id WHERE u.course_id = ? AND u.year = ? AND m.scheduled_time >= NOW() ORDER BY m.scheduled_time ASC");
            $meeting_query->bind_param("ii", $course_id, $year_of_study);
            $meeting_query->execute();
            $meetings = $meeting_query->get_result();
            while ($m = $meetings->fetch_assoc()) {
                echo "<tr class='border-b'><td class='py-6 text-lg'>" . htmlspecialchars($m['title']) . "</td><td class='py-6 text-lg'>" . htmlspecialchars($m['unit_name']) . "</td><td class='py-6 text-lg'>" . date('d M Y, h:i A', strtotime($m['scheduled_time'])) . "</td><td class='py-6'><a href='meeting_ide.php?meeting_id=1' target='_blank' class='text-orange-600 font-bold text-lg hover:underline'>Join Meeting</a></td></tr>";
            }
            if ($meetings->num_rows === 0) echo "<tr><td colspan='4' class='py-16 text-center text-gray-500 text-xl'>No upcoming meetings</td></tr>";
            ?>
          </tbody></table>
        </div>
      </div>

      <!-- Notifications Modal -->
      <div id="notificationsModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50">
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-10 max-w-3xl w-full max-h-[85vh] overflow-y-auto shadow-2xl">
          <div class="flex justify-between items-center mb-8"><h2 class="text-3xl font-bold">Notifications</h2><button onclick="document.getElementById('notificationsModal').classList.add('hidden')"><i data-lucide="x" class="w-10 h-10"></i></button></div>
          <div class="space-y-6">
            <?php if (empty($notifications)): ?>
              <p class="text-center text-gray-500 text-xl py-12">No notifications yet.</p>
            <?php else: foreach ($notifications as $notif): ?>
              <div class="p-6 rounded-2xl border-2 <?= !$notif['is_read'] ? 'border-l-8 border-lime-500 bg-lime-50 dark:bg-lime-900/20' : 'border-gray-200 dark:border-slate-700' ?>">
                <h4 class="text-xl font-bold text-92400e"><?= htmlspecialchars($notif['title']) ?></h4>
                <p class="mt-2 text-lg"><?= htmlspecialchars($notif['message']) ?></p>
                <div class="flex justify-between items-center mt-4">
                  <span class="text-sm text-gray-500"><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></span>
                  <?php if (!empty($notif['link'])): ?>
                    <a href="<?= htmlspecialchars($notif['link']) ?>" class="text-lime-600 font-bold hover:underline">View</a>
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

    document.querySelectorAll('.nav-item').forEach(item => {
      item.addEventListener('click', function(e) {
        if (this.id === 'notifBell') {
          document.getElementById('notificationsModal').classList.remove('hidden');
          if (document.getElementById('notifBadge')) markAsRead();
          return;
        }
        e.preventDefault();
        document.querySelectorAll('section[id], #meetingsModal, #notificationsModal').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        this.classList.add('active');
        const sec = this.getAttribute('data-section');
        if (sec === 'meetings') document.getElementById('meetingsModal').classList.remove('hidden');
        else document.getElementById(sec).classList.remove('hidden');
      });
    });

    function markAsRead() {
      fetch('mark_notifications_read.php')
        .then(() => document.getElementById('notifBadge')?.remove());
    }

    document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.add('hidden'); }));

    function toggleTheme() {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', document.documentElement.classList.contains('dark')?'dark':'light');
      document.getElementById('themeIcon').setAttribute('data-lucide', document.documentElement.classList.contains('dark')?'sun':'moon');
      lucide.createIcons();
    }
    if (localStorage.getItem('theme') === 'dark') toggleTheme();

    function confettiBurst() {
      for(let i=0;i<120;i++){
        const c=document.createElement('div'); c.className='confetti';
        c.style.left=Math.random()*100+'vw';
        c.style.background=['#f59e0b','#3b82f6','#10b981','#f97316','#84cc16'][Math.floor(Math.random()*5)];
        c.style.animationDelay=Math.random()*2+'s';
        document.body.appendChild(c);
        setTimeout(()=>c.remove(),4000);
      }
    }
  </script>
</body>
</html>