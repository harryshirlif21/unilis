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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - UNILIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/dashboard.css">
</head>

<body class="relative">

    <!-- Top Navigation Bar -->
<nav class="bg-white shadow-md p-4 flex justify-between items-center text-slate-800 sticky top-0 z-30 border-b border-f5e6b2">

    <!-- Logo -->
    <a href="#" class="text-2xl font-bold text-92400e">
        UNILIS
    </a>

    <!-- Desktop Navigation Links -->
    <div class="hidden md:flex space-x-8 items-center text-92400e">
        <a href="#" class="nav-link active font-medium px-3 py-2 rounded-lg text-green-600 hover:bg-green-100" data-target="dashboard-content">Dashboard</a>
        <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg text-orange-600 hover:bg-orange-100" data-target="courses-content">Courses</a>
        <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg text-blue-600 hover:bg-blue-100" data-target="assignments-content">Assignments</a>
        <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg text-green-600 hover:bg-green-100" data-target="notes-content">Notes</a>
        <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg text-orange-600 hover:bg-orange-100" data-target="meetings-content">Meetings</a>
        <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg text-blue-600 hover:bg-blue-100" data-target="profile-content">Profile</a>

        <?php
        // Ensure these session variables are set
        $course_id = $_SESSION['course_id'] ?? null;
        $year_of_study = $_SESSION['year_of_study'] ?? null;
        $unread_count = 0;

        if ($course_id && $year_of_study) {
            $notif_count_query = $conn->prepare("
                SELECT COUNT(DISTINCT n.id) AS unread_count
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
                WHERE u.course_id = ? AND u.year = ? AND n.is_read = 0
            ");
            $notif_count_query->bind_param("ii", $course_id, $year_of_study);
            $notif_count_query->execute();
            $result = $notif_count_query->get_result();

            if ($row = $result->fetch_assoc()) {
                $unread_count = $row['unread_count'];
            }

            $notif_count_query->close();
        }
        ?>

        <!-- Notifications -->
        <a href="#" class="nav-link font-medium px-3 py-2 rounded-lg relative text-green-700 hover:bg-green-100" data-target="notifications-content" id="notifBell">
            🔔 Notifications
            <span id="notifCount" class="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full px-2">
                <?= $unread_count ?>
            </span>
        </a>
    </div>

    <!-- Mobile Hamburger Button -->
    <button id="menu-toggle" class="md:hidden p-2 rounded-full text-92400e hover:bg-fef3c7 transition">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>
</nav>

<!-- Mobile Navigation Dropdown -->
<div id="mobile-menu" class="hidden bg-white shadow-md md:hidden flex flex-col text-92400e py-4">

    <a href="#" class="block px-4 py-2 hover:bg-green-100" data-target="dashboard-content">Dashboard</a>
    <a href="#" class="block px-4 py-2 hover:bg-orange-100" data-target="courses-content">Courses</a>
    <a href="#" class="block px-4 py-2 hover:bg-blue-100" data-target="assignments-content">Assignments</a>
    <a href="#" class="block px-4 py-2 hover:bg-green-100" data-target="notes-content">Notes</a>
    <a href="#" class="block px-4 py-2 hover:bg-orange-100" data-target="meetings-content">Meetings</a>
    <a href="#" class="block px-4 py-2 hover:bg-blue-100" data-target="profile-content">Profile</a>

    <!-- Notifications -->
    <a href="#" class="block px-4 py-2 relative hover:bg-green-100" data-target="notifications-content">
        🔔 Notifications
        <span class="absolute top-2 right-4 bg-red-600 text-white text-xs rounded-full px-2">
            <?= $unread_count ?>
        </span>
    </a>

</div>

<!-- Mobile Toggle Script -->
<script>
document.getElementById("menu-toggle").addEventListener("click", () => {
    document.getElementById("mobile-menu").classList.toggle("hidden");
});
</script>


        <!-- Mobile Menu Toggle Button (right-aligned) -->
        <button id="menu-toggle" class="p-2 rounded-full text-92400e hover:bg-fef3c7 focus:outline-none focus:ring-2 focus:ring-f59e0b transition-all">
            <svg class="hamburger-icon-menu h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </nav>

    <!-- Off-Canvas Sidebar -->
    <!-- Off-Canvas Menu -->
    <div id="offCanvasMenu" class="sidebar">
        <button id="closeMenuBtn" class="close-btn">&times;</button>

        <!-- Student Info -->
        <h2 class="text-2xl font-bold mb-2 text-center text-92400e">
            <?= htmlspecialchars($student['name']) ?>
        </h2>
        <p class="text-base mb-2 text-center text-a16207">
            Student ID: <?= htmlspecialchars($student['reg_no']) ?>
        </p>
        <p class="text-base mb-2 text-center text-a16207">
            Program: <?= htmlspecialchars($course_name) ?>
        </p>
        <p class="text-base mb-2 text-center text-a16207">
            Year of Study: Year <?= htmlspecialchars($year_of_study) ?>
        </p>
        <p class="text-base mb-2 text-center text-a16207">
            Email: <?= htmlspecialchars($student['email']) ?>
        </p>
        <p class="text-base mb-6 text-center text-a16207">
            Joined: <?= htmlspecialchars($student['year_joined']) ?>
        </p>

        <!-- Dashboard -->
        <button class="menu-item green" onclick="alert('Dashboard Overview clicked!')">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </button>

        <!-- Academic Section -->
        <div class="menu-section-title blue">Academic</div>
        <button class="menu-item orange" onclick="alert('My Courses clicked!')">
            <i class="fas fa-book"></i> My Courses
        </button>
        <a href="submit_assignment.php" class="menu-item orange">
            <i class="fas fa-upload"></i> Submit Assignment
        </a>
        <button class="menu-item orange" onclick="alert('Grades clicked!')">
            <i class="fas fa-medal"></i> Grades
        </button>
        <button class="menu-item orange" onclick="alert('Schedule clicked!')">
            <i class="fas fa-calendar-alt"></i> Schedule
        </button>

        <!-- Resources Section -->
        <div class="menu-section-title green">Resources</div>
        <button class="menu-item orange" onclick="alert('Notes & Files clicked!')">
            <i class="fas fa-file-alt"></i> Notes & Files
        </button>
        <button class="menu-item orange" onclick="alert('Library Services clicked!')">
            <i class="fas fa-book-reader"></i> Library Services
        </button>

        <!-- Communication & Support Section -->
        <div class="menu-section-title blue">Communication & Support</div>
        <button class="menu-item" onclick="alert('Announcements clicked!')">
            <i class="fas fa-bullhorn"></i> Announcements
        </button>
        <a href="#" class="menu-item" onclick="alert('Messages not implemented yet!')">
            <i class="fas fa-envelope"></i> Messages
        </a>
        <button class="menu-item" onclick="alert('Help & Support clicked!')">
            <i class="fas fa-question-circle"></i> Help & Support
        </button>

        <!-- Account Section -->
        <div class="menu-section-title orange">Account</div>
        <a href="#" class="menu-item" onclick="alert('Profile Settings not implemented yet!')">
            <i class="fas fa-user-circle"></i> My Profile
        </a>
        <button class="menu-item" onclick="alert('Change Password clicked!')">
            <i class="fas fa-key"></i> Change Password
        </button>
        <a href="../logout.php" class="menu-item logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>


    <!-- Overlay to close the sidebar -->
    <div id="overlay" class="overlay"></div>

    <!-- Main Content Area -->
    <div class="p-6 md:p-10">
        <!-- New Hero Div with Image and Overlay Message -->
        <div class="relative bg-cover bg-center h-[60vh] mb-8 md:mb-12 rounded-2xl overflow-hidden" style="background-image: url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');">
            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                <h1 class="text-3xl md:text-4xl font-extrabold hero-text text-center px-4">
                    Welcome to the University Integrated Learning System
                </h1>
            </div>
        </div>

        <!-- Dynamic Content Sections -->
        <div id="dashboard-content">
            <!-- Overview Statistics Section -->
            <?php
            try {
                $units_stmt = $conn->prepare("SELECT COUNT(*) as count FROM units WHERE course_id = ? AND year = ?");
                $units_stmt->bind_param("ii", $course_id, $year_of_study);
                $units_stmt->execute();
                $units_count = $units_stmt->get_result()->fetch_assoc()['count'];
                $units_stmt->close();
            } catch (mysqli_sql_exception $e) {
                error_log("Error fetching units count: " . $e->getMessage());
                $units_count = 0;
                $_SESSION['error'] = "Unable to load units count.";
            }

            try {
                $assignments_stmt = $conn->prepare("SELECT COUNT(*) as count FROM assignments WHERE unit_id IN (SELECT id FROM units WHERE course_id = ? AND year = ?) AND deadline >= NOW()");
                $assignments_stmt->bind_param("ii", $course_id, $year_of_study);
                $assignments_stmt->execute();
                $assignments_count = $assignments_stmt->get_result()->fetch_assoc()['count'];
                $assignments_stmt->close();
            } catch (mysqli_sql_exception $e) {
                error_log("Error fetching assignments count: " . $e->getMessage());
                $assignments_count = 0;
                $_SESSION['error'] = "Unable to load assignments count.";
            }

            try {
                $meetings_stmt = $conn->prepare("SELECT COUNT(*) as count FROM meetings WHERE unit_id IN (SELECT id FROM units WHERE course_id = ? AND year = ?) AND scheduled_time >= NOW()");
                $meetings_stmt->bind_param("ii", $course_id, $year_of_study);
                $meetings_stmt->execute();
                $meetings_count = $meetings_stmt->get_result()->fetch_assoc()['count'];
                $meetings_stmt->close();
            } catch (mysqli_sql_exception $e) {
                error_log("Error fetching meetings count: " . $e->getMessage());
                $meetings_count = 0;
                $_SESSION['error'] = "Unable to load meetings count.";
            }

            try {
                $submitted_stmt = $conn->prepare("SELECT COUNT(*) as count FROM submissions WHERE student_id = ? AND assignment_id IN (SELECT id FROM assignments WHERE unit_id IN (SELECT id FROM units WHERE course_id = ? AND year = ?))");
                $submitted_stmt->bind_param("iii", $student_id, $course_id, $year_of_study);
                $submitted_stmt->execute();
                $submitted_count = $submitted_stmt->get_result()->fetch_assoc()['count'];
                $submitted_stmt->close();
            } catch (mysqli_sql_exception $e) {
                error_log("Error fetching submitted assignments count: " . $e->getMessage());
                $submitted_count = 0;
                $_SESSION['error'] = "Unable to load submitted assignments count.";
            }
            ?>
            <!-- Stats Section -->
            <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card bg-white rounded-2xl p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-amber-100 text-amber-700 stat-icon">
                            <i class="fas fa-book-open h-6 w-6"></i>
                        </div>
                        <div>
                           <p class="text-sm stat-text-primary" style="color: #6b8e23;">Active Units</p>

                            <h2 class="text-3xl font-bold stat-text-secondary"><?= $units_count ?></h2>
                        </div>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-700 stat-icon">
                            <i class="fas fa-hourglass-half h-6 w-6"></i>
                        </div>
                        <div>
                           <p class="text-sm stat-text-secondary" style="color: #2563eb;">Assignments Due</p>

                            <h2 class="text-3xl font-bold stat-text-accent"><?= $assignments_count ?></h2>
                        </div>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-700 stat-icon">
                            <i class="fas fa-users h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm stat-text-accent">Upcoming Meetings</p>
                            <h2 class="text-3xl font-bold stat-text-primary"><?= $meetings_count ?></h2>
                        </div>
                    </div>
                </div>
                <div class="card bg-white rounded-2xl p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-amber-200 text-amber-800 stat-icon">
                            <i class="fas fa-check-double h-6 w-6"></i>
                        </div>
                        <div>
                           <p class="text-sm stat-text-primary" style="color: #fb923c;">Assignments Submitted</p>

                            <h2 class="text-3xl font-bold stat-text-secondary"><?= $submitted_count ?></h2>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Data Visualization Section (Placeholders) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="card bg-white rounded-2xl p-6">
                    <h3 class="text-xl font-semibold mb-4 stat-text-secondary">Unit Progress</h3>
                    <div class="h-48 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 italic">Progress Bars Placeholder (e.g., per unit)</div>
                </div>
                <div class="card bg-white rounded-2xl p-6">
                    <h3 class="text-xl font-semibold mb-4 stat-text-secondary">Assignment Status</h3>
                    <div class="h-48 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 italic">Pie Chart Placeholder (Submitted vs. Pending)</div>
                </div>
            </div>

            <!-- Messages -->
            <?php
            if (isset($_SESSION['submission_success'])) {
                echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6'>" . htmlspecialchars($_SESSION['submission_success']) . "</div>";
                unset($_SESSION['submission_success']);
            }
            if (isset($_SESSION['submission_error'])) {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>" . htmlspecialchars($_SESSION['submission_error']) . "</div>";
                unset($_SESSION['submission_error']);
            }
            if (isset($_SESSION['error'])) {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>" . htmlspecialchars($_SESSION['error']) . "</div>";
                unset($_SESSION['error']);
            }
            ?>

            <!-- Notes Section -->




            <!-- Quick Action Cards -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="submit_assignment.php" class="card bg-white rounded-2xl p-6 text-center hover:shadow-lg">
                    <div class="flex flex-col items-center">
                        <div class="p-4 rounded-full bg-amber-100 mb-4">
                            <i class="fas fa-upload text-amber-600 h-10 w-10 stat-icon"></i>
                        </div>
                        <span class="font-semibold text-lg stat-text-accent">Submit Assignment</span>
                        <p class="text-sm text-gray-600 mt-2">Upload your completed assignments.</p>
                    </div>
                </a>
                <a href="#" onclick="alert('Messages not implemented yet!')" class="card bg-white rounded-2xl p-6 text-center hover:shadow-lg">
                    <div class="flex flex-col items-center">
                        <div class="p-4 rounded-full bg-orange-100 mb-4">
                            <i class="fas fa-envelope text-orange-600 h-10 w-10 stat-icon"></i>
                        </div>
                        <span class="font-semibold text-lg stat-text-secondary">Check Messages</span>
                        <p class="text-sm text-gray-600 mt-2">Communicate with lecturers and peers.</p>
                    </div>
                </a>
                <a href="#" onclick="alert('Profile Settings not implemented yet!')" class="card bg-white rounded-2xl p-6 text-center hover:shadow-lg">
                    <div class="flex flex-col items-center">
                        <div class="p-4 rounded-full bg-yellow-100 mb-4">
                            <i class="fas fa-user-circle text-yellow-600 h-10 w-10 stat-icon"></i>
                        </div>
                        <span class="font-semibold text-lg stat-text-accent">Update Profile</span>
                        <p class="text-sm text-gray-600 mt-2">Manage your personal information.</p>
                    </div>
                </a>
            </section>
        </div>

        <div id="courses-content" class="hidden">
            <!-- Placeholder for courses content -->
            <h2 class="text-3xl font-bold stat-text-primary mb-8">Available Courses</h2>
            <p class="text-lg text-92400e">Courses content will be loaded here.</p>
        </div>


        <!-- Assignments Content (Hidden by default) -->
        <div id="assignments-content" class="hidden">

            <!-- Interactive Assignments / CATs Section -->
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Interactive Assignments / CATs</h2>
                <div class="mb-4">
                    <label class="block text-sm font-medium stat-text-primary mb-2"><b>Filter by Unit:</b></label>
                    <select id="ia-unit-filter" class="w-full max-w-xs px-3 py-2 border border-f5e6b2 rounded-lg text-92400e">
                        <option value="">-- Select Unit --</option>
                        <?php
                        // populate units for this student course/year
                        try {
                            $uf = $conn->prepare("SELECT id, name FROM units WHERE course_id = ? AND year = ? ORDER BY name ASC");
                            $uf->bind_param("ii", $course_id, $year_of_study);
                            $uf->execute();
                            $ul = $uf->get_result();
                            while ($urow = $ul->fetch_assoc()) {
                                echo '<option value="' . intval($urow['id']) . '">' . htmlspecialchars($urow['name']) . "</option>";
                            }
                            $uf->close();
                        } catch (mysqli_sql_exception $e) { /* ignore */
                        }
                        ?>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Title</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Deadline</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e" id="ia-tbody">
                            <?php
                            try {
                                $int_assignments_query = $conn->prepare("
                                    SELECT a.id, a.title, a.due_date, u.name AS unit_name
                                    FROM interactive_assignments a
                                    JOIN units u ON a.unit_id = u.id
                                    WHERE u.course_id = ?
                                      AND u.year = ?
                                    ORDER BY a.due_date ASC
                                ");

                                $int_assignments_query->bind_param("ii", $course_id, $year_of_study);
                                $int_assignments_query->execute();
                                $int_assignments = $int_assignments_query->get_result();

                                if ($int_assignments->num_rows === 0) {
                                    echo "<tr><td colspan='4' class='py-4 text-center'>No interactive assignments or CATs for your class.</td></tr>";
                                } else {
                                    while ($ia = $int_assignments->fetch_assoc()) {
                                        $check_submitted = $conn->prepare("SELECT id FROM interactive_submissions WHERE assignment_id = ? AND student_id = ?");
                                        $check_submitted->bind_param("ii", $ia['id'], $student_id);
                                        $check_submitted->execute();
                                        $check_submitted->store_result();
                                        $submitted = $check_submitted->num_rows > 0;
                                        $check_submitted->close();

                                        $action = $submitted
                                            ? "<span class='text-green-600'>Submitted</span>"
                                            : "<a href='take_assignment.php?id=" . $ia['id'] . "' class='text-f59e0b hover:underline'>Answer MCQs</a>";

                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>
                                            <td class='py-4 table-text-primary'>" . htmlspecialchars($ia['unit_name']) . "</td>
                                            <td class='py-4 table-text-secondary'>" . htmlspecialchars($ia['title']) . "</td>
                                            <td class='py-4 text-sm table-text-accent'>" . date("d M Y, h:i A", strtotime($ia['due_date'])) . "</td>
                                            <td class='py-4 table-text-primary'>$action</td>
                                        </tr>";
                                    }
                                }
                                $int_assignments_query->close();
                            } catch (mysqli_sql_exception $e) {
                                error_log("Error fetching interactive assignments: " . $e->getMessage());
                                echo "<tr><td colspan='4' class='py-4 text-center text-red-500'>Error loading interactive assignments.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>


            <!-- Submitted Assignments Section -->
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Submitted Assignments</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Title</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Date Submitted</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Marks</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Comment</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
                            <?php
                            try {
                                $submissions_query = $conn->prepare("
                                    SELECT s.file_path, s.submitted_at, s.comment, s.marks, a.title, u.name AS unit_name
                                    FROM submissions s
                                    JOIN assignments a ON s.assignment_id = a.id
                                    JOIN units u ON a.unit_id = u.id
                                    WHERE s.student_id = ? AND u.course_id = ? AND u.year = ?
                                    ORDER BY s.submitted_at DESC
                                ");
                                $submissions_query->bind_param("iii", $student_id, $course_id, $year_of_study);
                                $submissions_query->execute();
                                $submissions = $submissions_query->get_result();

                                if ($submissions->num_rows === 0) {
                                    echo "<tr><td colspan='6' class='py-4 text-center'>No assignments submitted yet.</td></tr>";
                                } else {
                                    while ($submission = $submissions->fetch_assoc()) {
                                        $filePath = htmlspecialchars($submission['file_path']);
                                        $fullPath = "../assets/uploads/submissions/" . $filePath;
                                        $actions = file_exists($fullPath) ?
                                            "<a href='$fullPath' target='_blank' class='text-f59e0b hover:underline mr-2'>View</a> | <a href='$fullPath' download class='text-f59e0b hover:underline'>Download</a>" :
                                            "<span style='color: red;'>File missing</span>";

                                        $marksDisplay = is_null($submission['marks']) ? "<em>Not graded</em>" : htmlspecialchars($submission['marks']);
                                        $commentDisplay = !empty($submission['comment']) ? htmlspecialchars($submission['comment']) : "<em>No comment</em>";

                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>
                                            <td class='py-4 table-text-primary'>" . htmlspecialchars($submission['unit_name']) . "</td>
                                            <td class='py-4 table-text-secondary'>" . htmlspecialchars($submission['title']) . "</td>
                                            <td class='py-4 text-sm table-text-accent'>" . date("d M Y, h:i A", strtotime($submission['submitted_at'])) . "</td>
                                            <td class='py-4 table-text-primary'>$marksDisplay</td>
                                            <td class='py-4 table-text-secondary'>$commentDisplay</td>
                                            <td class='py-4 table-text-accent'>$actions</td>
                                        </tr>";
                                    }
                                }
                                $submissions_query->close();
                            } catch (mysqli_sql_exception $e) {
                                error_log("Error fetching submissions: " . $e->getMessage());
                                echo "<tr><td colspan='6' class='py-4 text-center text-red-500'>Error loading submitted assignments. Please contact the administrator.</td></tr>";
                                $_SESSION['error'] = "Unable to load submitted assignments.";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>



            <!-- Assignments Section -->
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Assignments for Year <?= htmlspecialchars($year_of_study) ?></h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Title</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Deadline</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
                            <?php
                            try {
                                $assignments_query = $conn->prepare("
                                    SELECT a.id, a.title, a.description, a.deadline, a.file_path, u.name AS unit_name
                                    FROM assignments a
                                    JOIN units u ON a.unit_id = u.id
                                    WHERE u.course_id = ? AND u.year = ?
                                    ORDER BY a.deadline DESC
                                ");
                                $assignments_query->bind_param("ii", $course_id, $year_of_study);
                                $assignments_query->execute();
                                $assignments = $assignments_query->get_result();

                                if ($assignments->num_rows === 0) {
                                    echo "<tr><td colspan='4' class='py-4 text-center'>No assignments found for your course and year.</td></tr>";
                                } else {
                                    $now = new DateTime();
                                    while ($assignment = $assignments->fetch_assoc()) {
                                        $filePath = !empty($assignment['file_path']) ? htmlspecialchars($assignment['file_path']) : '';
                                        $fullPath = "../assets/uploads/assignments/" . $filePath;

                                        // Check if deadline passed
                                        $deadline = new DateTime($assignment['deadline']);
                                        $deadlinePassed = $now > $deadline;

                                        $actions = '';
                                        if (!empty($filePath) && file_exists($fullPath)) {
                                            $actions .= "<a href='$fullPath' target='_blank' class='text-f59e0b hover:underline mr-2'>View</a> | <a href='$fullPath' download class='text-f59e0b hover:underline mr-2'>Download</a><br>";
                                        }

                                        // Submission form - disable if deadline passed
                                        $disabledAttr = $deadlinePassed ? "disabled title='Deadline passed, submission closed'" : "";

                                        $actions .= "
                                            <form method='POST' enctype='multipart/form-data' action='submit_assignment.php' class='flex items-center space-x-2 mt-2'>
                                                <input type='hidden' name='assignment_id' value='{$assignment['id']}'>
                                                <input type='file' name='file' accept='.pdf,.doc,.docx' required $disabledAttr class='text-sm'>
                                                <button type='submit' $disabledAttr class='btn-primary px-4 py-1 rounded-lg text-sm'>Submit</button>
                                            </form>";

                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>
                                            <td class='py-4 table-text-primary'>" . htmlspecialchars($assignment['unit_name']) . "</td>
                                            <td class='py-4 table-text-secondary'>" . htmlspecialchars($assignment['title']) . "</td>
                                            <td class='py-4 text-sm table-text-accent'>" . date("d M Y, h:i A", strtotime($assignment['deadline'])) . "</td>
                                            <td class='py-4 table-text-primary'>$actions</td>
                                        </tr>";
                                    }
                                }
                                $assignments_query->close();
                            } catch (mysqli_sql_exception $e) {
                                error_log("Error fetching assignments: " . $e->getMessage());
                                echo "<tr><td colspan='4' class='py-4 text-center text-red-500'>Error loading assignments. Please contact the administrator.</td></tr>";
                                $_SESSION['error'] = "Unable to load assignments.";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>


        <div id="notes-content" class="hidden">
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Notes for Year <?= htmlspecialchars($year_of_study) ?></h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Unit Code</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">File</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Uploaded At</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
                            <?php
                            try {
                                $units_query = $conn->prepare("SELECT id, name, code FROM units WHERE course_id = ? AND year = ?");
                                $units_query->bind_param("ii", $course_id, $year_of_study);
                                $units_query->execute();
                                $units = $units_query->get_result();

                                if ($units->num_rows === 0) {
                                    echo "<tr><td colspan='5' class='py-4 text-center'>No units found for your course and year.</td></tr>";
                                } else {
                                    while ($unit = $units->fetch_assoc()) {
                                        $unit_id = $unit['id'];
                                        $unit_name = htmlspecialchars($unit['name']);
                                        $unit_code = htmlspecialchars($unit['code']);

                                        $notes_query = $conn->prepare("SELECT file_path, uploaded_at FROM notes WHERE unit_id = ? ORDER BY uploaded_at DESC");
                                        $notes_query->bind_param("i", $unit_id);
                                        $notes_query->execute();
                                        $notes = $notes_query->get_result();

                                        if ($notes->num_rows > 0) {
                                            while ($note = $notes->fetch_assoc()) {
                                                $file = htmlspecialchars($note['file_path']);
                                                $uploaded_at = date("d M Y, h:i A", strtotime($note['uploaded_at']));
                                                $full_path = "../assets/uploads/" . $file;
                                                $fileExists = file_exists($full_path);
                                                $fileDisplay = $file ? $file : '<span style="color:red;">No filename</span>';

                                                echo "<tr class='border-b border-f5e6b2 table-row-hover'>
                                                    <td class='py-4 table-text-primary'>$unit_name</td>
                                                    <td class='py-4 table-text-secondary'>$unit_code</td>
                                                    <td class='py-4 table-text-accent'>$fileDisplay</td>
                                                    <td class='py-4 text-sm table-text-primary'>$uploaded_at</td>
                                                    <td class='py-4 table-text-secondary'>";
                                                if ($fileExists) {
                                                    echo "<a href='$full_path' target='_blank' class='text-f59e0b hover:underline mr-2'>View</a> | <a href='$full_path' download class='text-f59e0b hover:underline'>Download</a>";
                                                } else {
                                                    echo "<span style='color: red;'>File missing</span>";
                                                }
                                                echo "</td></tr>";
                                            }
                                        } else {
                                            echo "<tr class='table-row-hover'>
                                                <td class='py-4 table-text-primary'>$unit_name</td>
                                                <td class='py-4 table-text-secondary'>$unit_code</td>
                                                <td class='py-4 table-text-accent' colspan='3'>No notes uploaded yet.</td>
                                            </tr>";
                                        }
                                        $notes_query->close();
                                    }
                                }
                                $units_query->close();
                            } catch (mysqli_sql_exception $e) {
                                error_log("Error fetching notes: " . $e->getMessage());
                                echo "<tr><td colspan='5' class='py-4 text-center text-red-500'>Error loading notes. Please contact the administrator.</td></tr>";
                                $_SESSION['error'] = "Unable to load notes.";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div id="meetings-content" class="hidden">
            <!-- Meetings Section -->
            <section class="card bg-white rounded-2xl p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Upcoming Meetings</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-f5e6b2">
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Title</th>
                                <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Unit</th>
                                <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Scheduled Time</th>
                                <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-92400e">
                            <?php
                            try {
                                $now = date('Y-m-d H:i:s');
                                $meeting_query = $conn->prepare("
                                    SELECT m.id, m.title, m.scheduled_time, u.name AS unit_name 
                                    FROM meetings m 
                                    JOIN units u ON m.unit_id = u.id 
                                    WHERE u.course_id = ? AND u.year = ? AND m.scheduled_time >= ?
                                    ORDER BY m.scheduled_time ASC
                                ");
                                $meeting_query->bind_param("iis", $course_id, $year_of_study, $now);
                                $meeting_query->execute();
                                $meetings = $meeting_query->get_result();

                                if ($meetings->num_rows === 0) {
                                    echo "<tr><td colspan='4' class='py-4 text-center'>No meetings scheduled.</td></tr>";
                                } else {
                                    while ($meeting = $meetings->fetch_assoc()) {
                                        echo "<tr class='border-b border-f5e6b2 table-row-hover'>
                                            <td class='py-4 table-text-primary'>" . htmlspecialchars($meeting['title']) . "</td>
                                            <td class='py-4 table-text-secondary'>" . htmlspecialchars($meeting['unit_name']) . "</td>
                                            <td class='py-4 text-sm table-text-accent'>" . date("d M Y, h:i A", strtotime($meeting['scheduled_time'])) . "</td>
                                            <td class='py-4 table-text-primary'><a class='text-f59e0b hover:underline' href='meeting_ide.php?meeting_id=" . htmlspecialchars($meeting['id']) . "' target='_blank'>Join Meeting</a></td>
                                        </tr>";
                                    }
                                }
                                $meeting_query->close();
                            } catch (mysqli_sql_exception $e) {
                                error_log("Error fetching meetings: " . $e->getMessage());
                                echo "<tr><td colspan='4' class='py-4 text-center text-red-500'>Error loading meetings. Please contact the administrator.</td></tr>";
                                $_SESSION['error'] = "Unable to load meetings.";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        
       <?php
// Make sure PHP mode starts before this logic if you're mixing with HTML
?>
<div id="notifications-content" class="hidden">
    <section class="card bg-white rounded-2xl p-6 mb-8">
        <h2 class="text-2xl font-semibold mb-4 stat-text-secondary">Notifications</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-f5e6b2">
                        <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Title</th>
                        <th class="py-3 text-sm font-semibold stat-text-secondary uppercase">Message</th>
                        <th class="py-3 text-sm font-semibold stat-text-accent uppercase">Date</th>
                        <th class="py-3 text-sm font-semibold stat-text-primary uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-92400e">
                    <?php
                    try {
                        
                        $course_id = $_SESSION['course_id'] ?? null;
                        $year_of_study = $_SESSION['year_of_study'] ?? null;

                        if (!$course_id || !$year_of_study) {
                            echo "<tr><td colspan='4' class='py-4 text-center text-red-500'>Missing student context.</td></tr>";
                        } else {
                            // Fetch notifications relevant to student's course and year
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
                            $notifications = $notif_query->get_result();

                            if ($notifications->num_rows === 0) {
                                echo "<tr><td colspan='4' class='py-4 text-center'>No notifications yet.</td></tr>";
                            } else {
                                while ($notif = $notifications->fetch_assoc()) {
                                    $title = htmlspecialchars($notif['title']);
                                    $message = htmlspecialchars($notif['message']);
                                    $created_at = date("d M Y, h:i A", strtotime($notif['created_at']));
                                    $link = !empty($notif['link'])
                                        ? "<a href='" . htmlspecialchars($notif['link']) . "' class='text-f59e0b hover:underline'>View</a>"
                                        : "-";

                                    // Highlight unread notifications
                                    $row_style = $notif['is_read'] ? '' : "style='background-color:#fffbea;'";

                                    echo "<tr class='border-b border-f5e6b2 table-row-hover' $row_style>
                                        <td class='py-4 table-text-primary'>$title</td>
                                        <td class='py-4 table-text-secondary'>$message</td>
                                        <td class='py-4 text-sm table-text-accent'>$created_at</td>
                                        <td class='py-4'>$link</td>
                                    </tr>";
                                }
                            }

                            $notif_query->close();
                        }
                    } catch (mysqli_sql_exception $e) {
                        error_log('Error fetching notifications: ' . $e->getMessage());
                        echo "<tr><td colspan='4' class='py-4 text-center text-red-500'>Error loading notifications.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
// Optionally close PHP again after if more HTML follows
?>


        <script>
            // Enhanced JavaScript for better UX: Navigation, Sidebar Toggle, and Content Switching
            document.addEventListener('DOMContentLoaded', function() {
                const menuToggle = document.getElementById('menu-toggle');
                const sidebar = document.getElementById('offCanvasMenu');
                const overlay = document.getElementById('overlay');
                const closeBtn = document.getElementById('closeMenuBtn');
                const navLinks = document.querySelectorAll('.nav-link');

                // Sidebar Toggle
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('open');
                    menuToggle.querySelector('svg').classList.toggle('open');
                });

                // Close sidebar on overlay click
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('open');
                    menuToggle.querySelector('svg').classList.remove('open');
                });

                // Close sidebar on close button click
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.remove('open');
                    overlay.classList.remove('open');
                    menuToggle.querySelector('svg').classList.remove('open');
                });

                // Navigation Content Switching
                navLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const target = this.getAttribute('data-target');
                        if (target) {
                            // Hide all sections
                            const contentSections = document.querySelectorAll('[id$="-content"]');
                            contentSections.forEach(section => section.classList.add('hidden'));
                            // Show target section if it exists
                            const targetSection = document.getElementById(target);
                            if (targetSection) {
                                targetSection.classList.remove('hidden');
                            }
                            // Update active nav
                            navLinks.forEach(l => l.classList.remove('active'));
                            this.classList.add('active');
                            // Close sidebar on mobile
                            if (window.innerWidth < 768) {
                                sidebar.classList.remove('open');
                                overlay.classList.remove('open');
                                menuToggle.querySelector('svg').classList.remove('open');
                            }
                        }
                    });
                });

                // Improved focus management for accessibility
                menuToggle.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });

                // Unit filter functionality
                (function() {
                    const sel = document.getElementById('ia-unit-filter');
                    const tbody = document.getElementById('ia-tbody');
                    if (!sel || !tbody) return;
                    sel.addEventListener('change', function() {
                        const unitId = parseInt(this.value || '0', 10);
                        if (!unitId) return;
                        tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center">Loading...</td></tr>';
                        fetch(`../actions.php?action=get_interactive_assignments_by_unit&unit_id=${unitId}`)
                            .then(r => r.json())
                            .then(data => {
                                const items = data.assignments || [];
                                if (!items.length) {
                                    tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center">No interactive assignments for this unit.</td></tr>';
                                    return;
                                }
                                tbody.innerHTML = '';
                                items.forEach(a => {
                                    const d = a.due_date ? new Date(a.due_date).toLocaleString() : '';
                                    const tr = document.createElement('tr');
                                    tr.className = 'border-b border-f5e6b2 table-row-hover';
                                    tr.innerHTML = `<td class="py-4 table-text-primary">-</td><td class="py-4 table-text-secondary">${a.title}</td><td class="py-4 text-sm table-text-accent">${d}</td><td class="py-4 table-text-primary"><a href="take_assignment.php?id=${a.id}" class="text-f59e0b hover:underline">Answer MCQs</a></td>`;
                                    tbody.appendChild(tr);
                                });
                            })
                            .catch(() => {
                                tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-red-500">Failed to load.</td></tr>';
                            });
                    });
                })();
            });

            document.getElementById("notifBell").addEventListener("click", function() {
                // AJAX call to mark notifications as read
                fetch("mark_notifications_read.php")
                    .then(response => response.text())
                    .then(data => {
                        // Reset count to 0 in badge
                        document.getElementById("notifCount").innerText = "0";
                    })
                    .catch(err => console.error("Error marking notifications:", err));
            });
        </script>
</body>

</html>
