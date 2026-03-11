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
    if (!$student) {
        throw new Exception("Student not found.");
    }
    $course_id = $student['course_id'];
    $year_of_study = $student['year_of_study'];

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

// Get latest 5 notifications for small popup
$latest_notif_stmt = $conn->prepare("
    SELECT n.id, n.title, n.message, n.link, n.is_read, n.created_at
    FROM notifications n
    LEFT JOIN notes nt ON n.notes_id = nt.id
    LEFT JOIN assignments a ON n.assignment_id = a.id
    LEFT JOIN interactive_assignments ia ON n.interactive_assignment_id = ia.id
    LEFT JOIN meetings m ON n.meeting_id = m.id
    LEFT JOIN units u_nt ON u_nt.id = nt.unit_id
    LEFT JOIN units u_a  ON u_a.id  = a.unit_id
    LEFT JOIN units u_ia ON u_ia.id = ia.unit_id
    LEFT JOIN units u_m  ON u_m.id  = m.unit_id
    WHERE 
        (u_nt.course_id = ? AND u_nt.year = ?) OR
        (u_a.course_id  = ? AND u_a.year  = ?) OR
        (u_ia.course_id = ? AND u_ia.year = ?) OR
        (u_m.course_id  = ? AND u_m.year  = ?)
    ORDER BY n.created_at DESC
    LIMIT 5
");
$latest_notif_stmt->bind_param(
    "iiiiiiii",
    $course_id, $year_of_study,
    $course_id, $year_of_study,
    $course_id, $year_of_study,
    $course_id, $year_of_study
);
$latest_notif_stmt->execute();
$latest_notifications = $latest_notif_stmt->get_result();

// Count unread notifications for badge
$unread_stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications n
    LEFT JOIN notes nt ON n.notes_id = nt.id
    LEFT JOIN assignments a ON n.assignment_id = a.id
    LEFT JOIN interactive_assignments ia ON n.interactive_assignment_id = ia.id
    LEFT JOIN meetings m ON n.meeting_id = m.id
    LEFT JOIN units u_nt ON u_nt.id = nt.unit_id
    LEFT JOIN units u_a  ON u_a.id  = a.unit_id
    LEFT JOIN units u_ia ON u_ia.id = ia.unit_id
    LEFT JOIN units u_m  ON u_m.id  = m.unit_id
    WHERE ((u_nt.course_id = ? AND u_nt.year = ?) OR
           (u_a.course_id = ? AND u_a.year = ?) OR
           (u_ia.course_id = ? AND u_ia.year = ?) OR
           (u_m.course_id = ? AND u_m.year = ?))
      AND n.is_read = 0
");
$unread_stmt->bind_param(
    "iiiiiiii",
    $course_id, $year_of_study,
    $course_id, $year_of_study,
    $course_id, $year_of_study,
    $course_id, $year_of_study
);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard Navbar</title>

<!-- Font Awesome for profile avatar icon -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- External CSS -->
<link rel="stylesheet" href="css/styles.css">

</head>
<body>
<!-- Navbar -->
<nav class="navbar">
    <div class="welcome-msg">
        <strong>👋 Welcome back!</strong>
    </div>

    <!-- Notifications -->
    <div class="nav-icon" id="notifications-icon" style="position:relative; cursor:pointer;">
        <i class="fas fa-bell"></i>
        <!-- Red circle indicator for new notifications -->
        <span id="notificationCount" 
              style="position:absolute; top:0; right:0; width:12px; height:12px; background:red; border-radius:50%; display:block;">
        </span>
    </div>
    <div class="nav-icon" id="profile-icon">
        <i class="fas fa-user"></i>
    </div>
    <!-- Mobile Sidebar Toggle -->
    <div class="sidebar-toggle">
        <i class="fas fa-ellipsis-v"></i>
    </div>
</nav>

<!-- Sidebar -->
<aside class="sidebar">
    <!-- Main Navigation -->
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
                <a href="lesson_view.php">
                    <i class="fas fa-book"></i><span>Lessons</span>
                </a>
            </li>
            <!-- Attendance: no <a> tag — JS click handler opens the modal -->
            <li class="purple">
                <i class="fas fa-check-double"></i><span>Attendance</span>
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

<script>
function logout() {
    window.location.href = "../logout.php";
}
</script>

<!-- Profile popup -->
<div class="popup" id="profile-popup">
    <h3><?php echo htmlspecialchars($student['name']); ?></h3>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
    <p><strong>Reg No:</strong> <?php echo htmlspecialchars($student['reg_no']); ?></p>
    <p><strong>Course:</strong> <?php echo htmlspecialchars($course_name); ?></p>
    <p><strong>Year:</strong> <?php echo htmlspecialchars($student['year_of_study']); ?></p>
    <p><strong>Joined:</strong> <?php echo htmlspecialchars($student['year_joined']); ?></p>
</div>

<!-- Latest notifications popup -->
<div id="notifications-content" class="popup">
    <h3>Latest Notifications</h3>
    <ul>
        <?php if($latest_notifications->num_rows === 0): ?>
            <li>No notifications</li>
        <?php else: ?>
            <?php while($notif = $latest_notifications->fetch_assoc()): ?>
                <li style="<?php echo $notif['is_read'] ? '' : 'font-weight:bold;'; ?>">
                    <?php echo htmlspecialchars($notif['title']); ?>
                    <br>
                    <small><?php echo date('d M H:i', strtotime($notif['created_at'])); ?></small>
                </li>
            <?php endwhile; ?>
        <?php endif; ?>
    </ul>
    <button id="viewAllBtn">View All</button>
</div>

<!-- Modal -->
<div id="allNotificationsModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>All Notifications</h3>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $all_notif_stmt = $conn->prepare("
                    SELECT n.id, n.title, n.message, n.is_read, n.created_at
                    FROM notifications n
                    LEFT JOIN notes nt ON n.notes_id = nt.id
                    LEFT JOIN assignments a ON n.assignment_id = a.id
                    LEFT JOIN interactive_assignments ia ON n.interactive_assignment_id = ia.id
                    LEFT JOIN meetings m ON n.meeting_id = m.id
                    LEFT JOIN units u_nt ON u_nt.id = nt.unit_id
                    LEFT JOIN units u_a  ON u_a.id  = a.unit_id
                    LEFT JOIN units u_ia ON u_ia.id = ia.unit_id
                    LEFT JOIN units u_m  ON u_m.id  = m.unit_id
                    WHERE (u_nt.course_id = ? AND u_nt.year = ?) OR
                          (u_a.course_id  = ? AND u_a.year  = ?) OR
                          (u_ia.course_id = ? AND u_ia.year = ?) OR
                          (u_m.course_id  = ? AND u_m.year = ?)
                    ORDER BY n.created_at DESC
                ");
                $all_notif_stmt->bind_param(
                    "iiiiiiii",
                    $course_id, $year_of_study,
                    $course_id, $year_of_study,
                    $course_id, $year_of_study,
                    $course_id, $year_of_study
                );
                $all_notif_stmt->execute();
                $all_notifications = $all_notif_stmt->get_result();

                while($notif = $all_notifications->fetch_assoc()):
                    $row_style = $notif['is_read'] ? '' : 'style="background-color:#fffbea;"';
                ?>
                    <tr <?php echo $row_style; ?>>
                        <td><?php echo htmlspecialchars($notif['title']); ?></td>
                        <td><?php echo htmlspecialchars($notif['message']); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($notif['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hero Div below navbar -->
<div class="hero-section">
    <div class="hero-content">
        <p>👋 Welcome back! We are glad to have you on our system. Discover the latest tools and updates designed to make your learning journey smoother.</p>
        <h2>Explore New Features</h2>
        <button class="explore-btn">Explore</button>
    </div>
    <img src="images/lady.jpg" alt="Medical Lady Avatar" class="hero-avatar">
</div>

<div class="features-section">

  <!-- 1. Notes -->
  <div class="feature-card notes-card">
    <div class="feature-header">
      <i class="fas fa-book feature-avatar"></i>
      <h2>Notes</h2>
    </div>
    <p class="semester-label">Semester 1</p>
    <div class="stats">
      <span><i class="fas fa-layer-group"></i> 8 Units</span>
      <span><i class="fas fa-file"></i> 6 Files</span>
      <span class="warning"><i class="fas fa-clock"></i> 2 Pending</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width:75%"></div>
    </div>
    <p class="status">Updated 2 days ago</p>
    <p class="motivation">Keep going! You're doing great 📚</p>
    <div class="feature-buttons">
      <a href="viewnotes.php">
        <button class="interactive-btn">Visit notes page</button>
      </a>
    </div>
  </div>

  <!-- 2. Assignments -->
  <div class="feature-card assignments-card">
    <div class="feature-header">
      <i class="fas fa-file-signature feature-avatar"></i>
      <h2>Assignments</h2>
    </div>
    <p class="semester-label">Semester 1</p>
    <div class="stats">
      <span>7 Given</span>
      <span class="success">4 Submitted</span>
      <span class="danger">3 Pending</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width:60%"></div>
    </div>
    <p class="status">Deadline approaching ⚠️</p>
    <p class="motivation">Almost there — stay focused!</p>
    <div class="feature-buttons">
      <a href="take_assignment.php">
        <button class="interactive-btn">View Assignments</button>
      </a>
    </div>
  </div>

  <!-- 3. Meetings -->
  <div class="feature-card meetings-card">
    <div class="feature-header">
      <i class="fas fa-video feature-avatar"></i>
      <h2>Meetings</h2>
    </div>
    <p class="semester-label">Next Meeting</p>
    <div class="stats">
      <span><i class="far fa-calendar-check"></i> Today</span>
      <span><i class="far fa-clock"></i> 4:00 PM</span>
    </div>
    <p class="motivation">Stay connected with your class 💻</p>
    <div class="feature-buttons">
      <a href="meeting_ide.php">
        <button class="interactive-btn">Join Meeting</button>
      </a>
    </div>
  </div>

  <!-- 4. Online CATs -->
  <div class="feature-card cats-card">
    <div class="feature-header">
      <i class="fas fa-clipboard-check feature-avatar"></i>
      <h2>Online CATs</h2>
    </div>
    <div class="stats">
      <span>5 Available</span>
      <span>2 Attempted</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width:40%"></div>
    </div>
    <p class="motivation">Stay sharp! Exams ready 🧠</p>
    <div class="feature-buttons">
      <a href="take_assessment.php">
        <button class="interactive-btn">Take CAT</button>
      </a>
    </div>
  </div>

  <!-- 5. Academic Info -->
  <div class="feature-card academic-card">
    <div class="feature-header">
      <i class="fas fa-graduation-cap feature-avatar"></i>
      <h2>Academic Info</h2>
    </div>
    <div class="stats">
      <span>Results Released</span>
      <span class="success">GPA Updated</span>
    </div>
    <p class="motivation">Track your academic journey 🎓</p>
    <div class="feature-buttons">
      <a href="my_progress.php">
        <button class="interactive-btn">View Details</button>
      </a>
    </div>
  </div>

  <!-- 6. Other Features -->
  <div class="feature-card other-card">
    <div class="feature-header">
      <i class="fas fa-puzzle-piece feature-avatar"></i>
      <h2>Other Features</h2>
    </div>
    <p class="motivation">Explore additional tools & resources ⚙️</p>
    <div class="feature-buttons">
      <button class="interactive-btn">Explore</button>
    </div>
  </div>

</div>

<footer class="footer">
  <div class="footer-container">

    <!-- About Column -->
    <div class="footer-column footer-about">
      <h2>About Us</h2>
      <p>
        We deliver high-quality services and solutions tailored to help individuals and businesses thrive in the digital world. 
        Let's connect and build something great together.
      </p>
    </div>

    <!-- Quick Links -->
    <div class="footer-column footer-links">
      <h3>Quick Links</h3>
      <ul>
        <li><a href="#">Home</a></li>
        <li><a href="#">Services</a></li>
        <li><a href="#">About</a></li>
        <li><a href="#">Blog</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>

    <!-- Contact / Social -->
    <div class="footer-column footer-contact">
      <h3>Get in Touch</h3>
      <p><strong>WhatsApp:</strong> <a href="https://wa.me/254792451666">+254 792 451 666</a></p>
      <p><strong>Email:</strong> <a href="mailto:hello@yourcompany.com">hello@yourcompany.com</a></p>
      <div class="social-links">
        <a href="https://wa.me/254792451666" class="social whatsapp" target="_blank" aria-label="WhatsApp"><span>WhatsApp</span></a>
        <a href="https://facebook.com/yourpage" class="social facebook" target="_blank" aria-label="Facebook"><span>Facebook</span></a>
        <a href="https://instagram.com/yourhandle" class="social instagram" target="_blank" aria-label="Instagram"><span>Instagram</span></a>
        <a href="https://x.com/yourhandle" class="social twitter" target="_blank" aria-label="X / Twitter"><span>X</span></a>
      </div>
    </div>

    <!-- Newsletter -->
    <div class="footer-column footer-newsletter">
      <h3>Stay Updated</h3>
      <p>Subscribe to our newsletter for tips, updates & exclusive offers.</p>
      <form class="newsletter-form">
        <input type="email" placeholder="Your email address" required><br>
        <button type="submit">Subscribe</button>
      </form>
    </div>

  </div>

  <!-- Student Attendance Modal -->
  <div id="studentAttendanceModal" class="modal hidden">
      <div class="modal-content bg-white rounded-2xl border border-f5e6b2 shadow-2xl max-w-md mx-auto" 
           style="max-height: 90vh; overflow-y: auto;">
          
          <span class="close text-92400e text-3xl font-bold cursor-pointer hover:text-f59e0b absolute top-5 right-6 z-10"
                onclick="hideModal('studentAttendanceModal')">×</span>

          <h3 class="text-2xl font-bold stat-text-secondary mb-8 text-center pt-8">
              Enter Attendance Code
          </h3>

          <form id="studentAttendanceForm" class="px-8 pb-10" method="POST" action="attendance_submit.php">
              
              <div class="mb-6">
                  <label class="block text-sm font-medium stat-text-primary mb-3">
                      Unit <span class="text-red-500">*</span>
                  </label>
                  <select name="unit_id" id="studentUnitId" required
                          class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-lg 
                                 focus:ring-2 focus:ring-f59e0b focus:border-f59e0b transition">
                      <option value="">-- Choose Unit --</option>
                      <?php
                      $att_student_id = intval($_SESSION['user_id'] ?? 0);
                      $att_sem        = intval($_SESSION['semester'] ?? 1);
                      $att_acad_year  = $_SESSION['academic_year']  ?? (date('Y') . '/' . (date('Y') + 1));
                      if ($att_student_id > 0) {
                          $stmt = $conn->prepare("
                              SELECT u.id, u.name
                              FROM units u
                              JOIN student_unit_enrollments sue ON sue.unit_id = u.id
                              WHERE sue.student_id    = ?
                                AND sue.semester      = ?
                                AND sue.academic_year = ?
                              ORDER BY u.name ASC
                          ");
                          $stmt->bind_param("iis", $att_student_id, $att_sem, $att_acad_year);
                          $stmt->execute();
                          $result    = $stmt->get_result();
                          $att_units = [];
                          while ($unit = $result->fetch_assoc()) $att_units[] = $unit;
                          $stmt->close();
                          if (empty($att_units)) {
                              echo '<option value="" disabled>No units enrolled — <a href="my_units.php">set up My Units</a></option>';
                          } else {
                              foreach ($att_units as $unit) {
                                  echo "<option value='{$unit['id']}'>" . htmlspecialchars($unit['name']) . "</option>";
                              }
                          }
                      }
                      ?>
                  </select>
              </div>

              <div class="mb-6">
                  <label class="block text-sm font-medium stat-text-primary mb-3">
                      Attendance Code <span class="text-red-500">*</span>
                  </label>
                  <input type="text" name="attendance_code" required maxlength="8" placeholder="e.g. ABCD1234"
                         class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-xl text-center 
                                tracking-widest focus:ring-2 focus:ring-f59e0b focus:border-f59e0b transition uppercase">
              </div>

              <div class="text-center mt-8">
                  <button type="submit" class="btn-golden px-12 py-4 text-lg font-semibold rounded-xl shadow-lg">
                      Submit Attendance
                  </button>
              </div>
          </form>
      </div>
  </div>

  <!-- Bottom Bar -->
  <div class="footer-bottom">
    <p>&copy; 2026 Your Company Name. All rights reserved.</p>
    <div class="legal-links">
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Service</a>
      <a href="#">Cookie Policy</a>
    </div>
  </div>
</footer>


<script>
const profileIcon        = document.getElementById('profile-icon');
const profilePopup       = document.getElementById('profile-popup');
const notificationsIcon  = document.getElementById('notifications-icon');
const notificationsContent = document.getElementById('notifications-content');
const viewAllBtn         = document.getElementById('viewAllBtn');
const modal              = document.getElementById('allNotificationsModal');
const closeModal         = modal.querySelector('.close');
const notificationCount  = document.getElementById('notificationCount');

// Track read notifications
let readNotifications = new Set();
const notificationItems = notificationsContent.querySelectorAll('.notification-item');

function updateNotificationIndicator() {
    let unreadExists = false;
    notificationItems.forEach(item => {
        if (!readNotifications.has(item.dataset.id)) unreadExists = true;
    });
    notificationCount.style.display = unreadExists ? 'block' : 'none';
}

function showModal(modalId) {
    const m = document.getElementById(modalId);
    if (m) { m.classList.remove('hidden'); m.style.display = 'block'; }
}

function hideModal(modalId) {
    const m = document.getElementById(modalId);
    if (m) { m.classList.add('hidden'); m.style.display = 'none'; }
}

function logout() {
    window.location.href = "../logout.php";
}

// Sidebar click handler — only fires for items WITHOUT an <a> tag inside
// (i.e. Attendance). Items with <a> tags navigate naturally.
document.querySelectorAll('.sidebar-section li').forEach(item => {
    item.addEventListener('click', (e) => {
        // If the click originated from an <a> tag, let normal navigation happen
        if (e.target.closest('a')) return;

        const text = item.querySelector('span')?.textContent.trim();
        if (text === 'Attendance') {
            showModal('studentAttendanceModal');
            document.querySelectorAll('.sidebar-section li').forEach(li => li.classList.remove('active'));
            item.classList.add('active');
        }
    });
});

// Initialize: bold unread notifications
notificationItems.forEach(item => {
    item.style.fontWeight = readNotifications.has(item.dataset.id) ? 'normal' : 'bold';
    item.addEventListener('click', () => {
        readNotifications.add(item.dataset.id);
        item.style.fontWeight = 'normal';
        updateNotificationIndicator();
    });
});

// Toggle profile popup
profileIcon.addEventListener('click', () => {
    profilePopup.style.display = profilePopup.style.display === 'block' ? 'none' : 'block';
    notificationsContent.style.display = 'none';
});

// Toggle notifications popup
notificationsIcon.addEventListener('click', () => {
    notificationsContent.style.display = notificationsContent.style.display === 'block' ? 'none' : 'block';
    profilePopup.style.display = 'none';
});

// View all notifications modal
viewAllBtn.addEventListener('click', () => {
    modal.style.display = 'block';
    notificationsContent.style.display = 'none';
    notificationItems.forEach(item => {
        readNotifications.add(item.dataset.id);
        item.style.fontWeight = 'normal';
    });
    updateNotificationIndicator();
});

// Close modal
closeModal.addEventListener('click', () => { modal.style.display = 'none'; });

// Close popups & modal on outside click
document.addEventListener('click', e => {
    if (profilePopup && !profilePopup.contains(e.target) && !profileIcon.contains(e.target))
        profilePopup.style.display = 'none';
    if (notificationsContent && !notificationsContent.contains(e.target) && !notificationsIcon.contains(e.target))
        notificationsContent.style.display = 'none';
    if (modal && e.target === modal)
        modal.style.display = 'none';
});

// Sidebar mobile toggle
const sidebar      = document.querySelector('.sidebar');
const sidebarToggle = document.querySelector('.sidebar-toggle');
sidebarToggle?.addEventListener('click', () => sidebar.classList.toggle('show'));
document.addEventListener('click', (e) => {
    if (sidebar && !sidebar.contains(e.target) && !sidebarToggle?.contains(e.target))
        sidebar.classList.remove('show');
});

updateNotificationIndicator();
</script>

</body>
</html>