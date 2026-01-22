<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$admin_res = $conn->query("SELECT * FROM admins WHERE id = $user_id");
$admin = $admin_res->fetch_assoc();

$verify_success = $_SESSION['verify_success'] ?? '';
$verify_error   = $_SESSION['verify_error'] ?? '';

unset($_SESSION['verify_success']);
unset($_SESSION['verify_error']);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .success {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    border: 1px solid #c3e6cb;
    border-radius: 6px;
    margin-bottom: 15px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border: 1px solid #f5c6cb;
    border-radius: 6px;
    margin-bottom: 15px;
}

        /* small in-file styles kept (you can move to styles.css) */
        .floating-message {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px;
            z-index: 9999;
            display: none;
            animation: slideIn 0.5s ease-out;
        }
        .floating-message.success { background-color: #28a745; color: white; }
        .floating-message.error   { background-color: #dc3545; color: white; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }
        /* basic floating display and units grid */
        .floating-display { display:none; position:fixed; right:20px; top:80px; width:420px; max-height:70vh; overflow:auto; background:#fff; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.15); z-index:999; }
        .floating-display.active { display:block; }
        .floating-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #eee; }
        .units-grid { padding:12px 16px; display:grid; gap:8px; }
        .unit-card { display:flex; justify-content:space-between; align-items:center; padding:10px; border-radius:6px; background:#fafafa; border:1px solid #eee; }
        .delete-modal .modal-content { max-width:400px; }
    </style>
</head>
<body>

<!-- Top Header Bar -->
<header class="header">
    <h1>UNILIS Admin Dashboard</h1>
    <div class="admin-info">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></div>
    <button class="hamburger-menu" id="hamburgerMenu"><i class="fas fa-bars"></i></button>
</header>

<!-- Off-Canvas Menu -->
<div class="off-canvas-menu" id="offCanvasMenu">
    <button class="close-btn" id="closeMenuBtn">×</button>
    <h2><?= htmlspecialchars($_SESSION['user_name']) ?></h2>
    <p>Role: System Administrator</p>
    <div class="menu-section-title">Management</div>
    <button class="menu-item" onclick="openModal('universityModal')"><i class="fas fa-university"></i> Add University</button>
    <button class="menu-item" onclick="openModal('departmentModal')"><i class="fas fa-building"></i> Add Department</button>
    <button class="menu-item" onclick="openModal('courseModal')"><i class="fas fa-book"></i> Add Course</button>
    <button class="menu-item" onclick="openModal('unitSingleModal')"><i class="fas fa-cube"></i> Add Single Unit</button>
    <button class="menu-item" onclick="openModal('unitModal')"><i class="fas fa-cubes"></i> Add Multiple Units</button>
    <button class="menu-item" onclick="openModal('verifyEmailModal')">Verify Student Email</button>
    <button class="menu-item" onclick="openModal('lecturerModal')"><i class="fas fa-chalkboard-teacher"></i> Add Lecturer</button>
    <div class="menu-section-title">System</div>
    <button class="menu-item" onclick="alert('System Settings not implemented yet!')"><i class="fas fa-cogs"></i> System Settings</button>
    <a href="../logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Overlay for Off-Canvas Menu -->
<div class="overlay" id="menuOverlay"></div>

<!-- Main Content Area -->
<div class="content">
    <h2>System Overview</h2>

    <!-- Overview Statistics Section -->
    <?php
    $users_count = $conn->query("SELECT COUNT(*) as count FROM (SELECT id FROM students UNION SELECT id FROM lecturers UNION SELECT id FROM admins) as users")->fetch_assoc()['count'];
    $courses_count = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
    $departments_count = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
    $universities_count = $conn->query("SELECT COUNT(*) as count FROM universities")->fetch_assoc()['count'];
    ?>
    <div class="stat-cards-grid">
        <div class="stat-card users">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="number"><?= $users_count ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="stat-card courses">
            <div class="icon"><i class="fas fa-book"></i></div>
            <div class="number"><?= $courses_count ?></div>
            <div class="label">Active Courses</div>
        </div>
        <div class="stat-card departments">
            <div class="icon"><i class="fas fa-building"></i></div>
            <div class="number"><?= $departments_count ?></div>
            <div class="label">Total Departments</div>
        </div>
        <div class="stat-card universities">
            <div class="icon"><i class="fas fa-university"></i></div>
            <div class="number"><?= $universities_count ?></div>
            <div class="label">Total Universities</div>
            <div class="university-select">
                <select id="universityFilter" onchange="filterByUniversity(this.value)">
                    <option value="">Select University</option>
                    <?php
                    $universities = $conn->query("SELECT id, name FROM universities ORDER BY name ASC");
                    while ($uni = $universities->fetch_assoc()) {
                        echo "<option value='" . $uni['id'] . "'>" . htmlspecialchars($uni['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Data Visualization Section (Placeholders) -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3>User Registration Trends</h3>
            <div class="chart-placeholder">Line Chart Placeholder (e.g., last 12 months)</div>
        </div>
        <div class="chart-container">
            <h3>Content Upload Activity</h3>
            <div class="chart-placeholder">Bar Chart Placeholder (Notes, Assignments, Submissions)</div>
        </div>
    </div>

    <!-- Course Units Section -->
    <div class="recent-activity-section">
        <h3>Course Units Management</h3>
        <div class="course-units-header">
            <div class="select-container">
                <select name="course_id" id="courseSelect" required>
                    <option value="">-- Select a Course --</option>
                    <?php
                    $courses_query = $conn->query("
                        SELECT c.id, c.name AS course_name, d.name AS department_name, COUNT(u.id) AS unit_count
                        FROM courses c
                        LEFT JOIN units u ON c.id = u.course_id
                        JOIN departments d ON c.department_id = d.id
                        GROUP BY c.id, c.name, d.name
                        ORDER BY d.name, c.name
                    ");
                    while ($course = $courses_query->fetch_assoc()) {
                        $label = htmlspecialchars($course['department_name']) . " - " . htmlspecialchars($course['course_name']) . " (" . $course['unit_count'] . " units)";
                        echo "<option value='{$course['id']}'>$label</option>";
                    }
                    ?>
                </select>
                <div class="button-group">
                    <button type="button" onclick="viewCourseUnits()" class="btn btn-primary">
                        <i class="fas fa-eye"></i> View Units
                    </button>
                    <button type="button" onclick="exportUnitsPDF()" class="btn btn-success">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Units Display -->
    <div id="floatingUnitsDisplay" class="floating-display">
        <div class="floating-header">
            <h3>Course Units</h3>
            <button class="close-btn" onclick="closeFloatingDisplay()">×</button>
        </div>
        <div class="units-grid" id="unitsGrid">
            <!-- Units will be dynamically inserted here -->
        </div>
    </div>

    <!-- Delete Unit Confirmation Modal -->
    <div id="deleteUnitModal" class="modal delete-modal" style="display:none;">
        <div class="modal-content">
            <h3>Delete Unit?</h3>
            <p></p>
            <input type="hidden" id="deleteUnitId">
            <div class="modal-actions">
                <button onclick="closeModal('deleteUnitModal')" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmDeleteUnit()" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>

    <!-- Quick Admin Action Cards Section -->
    <div class="action-grid">
        <div class="action-card" onclick="openModal('universityModal')">
            <div class="icon"><i class="fas fa-university"></i></div>
            <h3>Add University</h3>
            <p>Create a new university in the system.</p>
        </div>
        <div class="action-card" onclick="openModal('departmentModal')">
            <div class="icon"><i class="fas fa-building"></i></div>
            <h3>Add Department</h3>
            <p>Add a new department to a university.</p>
        </div>
        <div class="action-card" onclick="openModal('courseModal')">
            <div class="icon"><i class="fas fa-book"></i></div>
            <h3>Add Course</h3>
            <p>Register a new academic course.</p>
        </div>
        <div class="action-card" onclick="openModal('unitSingleModal')">
            <div class="icon"><i class="fas fa-cube"></i></div>
            <h3>Add Single Unit</h3>
            <p>Add a single unit to a course.</p>
        </div>
        <div class="action-card" onclick="openModal('unitModal')">
            <div class="icon"><i class="fas fa-cubes"></i></div>
            <h3>Add Multiple Units</h3>
            <p>Add multiple units to a course.</p>
        </div>
        <div class="action-card" onclick="openModal('lecturerModal')">
            <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <h3>Add Lecturer</h3>
            <p>Register a new lecturer in the system.</p>
        </div>
    </div>

    <!-- UNIVERSITY MODAL -->
    <div id="universityModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('universityModal')">×</span>
            <h3>Add University</h3>
            <?php if (!empty($university_success)) echo "<p class='success'>$university_success</p>"; ?>
            <?php if (!empty($university_error)) echo "<p class='error'>$university_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_university">
                <label>University Name:</label>
                <input type="text" name="university_name" required>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>

    <!-- Floating Message Div -->
    <div id="floatingMessage" class="floating-message" style="display: none;"></div>

    <!-- DEPARTMENT MODAL -->
    <div id="departmentModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('departmentModal')">×</span>
            <h3>Add Department</h3>
            <form id="departmentForm" onsubmit="submitDepartmentForm(event)">
                <input type="hidden" name="action" value="add_department">
                <div class="form-group">
                    <label>Department Name:</label>
                    <input type="text" name="department_name" placeholder="Enter department name" required>
                </div>
                <div class="form-group">
                    <label>Select University:</label>
                    <select name="university_id" required>
                        <option value="">-- Select University --</option>
                        <?php
                        $res = $conn->query("SELECT id, name FROM universities ORDER BY name ASC");
                        while ($row = $res->fetch_assoc()) {
                            echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('departmentModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- COURSE MODAL -->
    <div id="courseModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('courseModal')">×</span>
            <h3>Add Course</h3>
            <?php if (!empty($course_success)) echo "<p class='success'>$course_success</p>"; ?>
            <?php if (!empty($course_error)) echo "<p class='error'>$course_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_course">
                <label>Course Name:</label>
                <input type="text" name="course_name" required>
                <label>Department:</label>
                <select name="department_id" required>
                    <option value="">-- Select Department --</option>
                    <?php
                    $departments = $conn->query("SELECT * FROM departments");
                    while ($d = $departments->fetch_assoc()) {
                        echo "<option value='{$d['id']}'>" . htmlspecialchars($d['name']) . "</option>";
                    }
                    ?>
                </select>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>

    <!-- UNIT SINGLE MODAL -->
    <div id="unitSingleModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('unitSingleModal')">×</span>
            <h3>Add Single Unit</h3>
            <?php if (!empty($unit_success)) echo "<p class='success'>$unit_success</p>"; ?>
            <?php if (!empty($unit_error)) echo "<p class='error'>$unit_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_unit">
                <div class="unit-selection">
                    <label>Course:</label>
                    <select name="course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php
                        $courses = $conn->query("SELECT * FROM courses");
                        while ($c = $courses->fetch_assoc()) {
                            echo "<option value='{$c['id']}'>" . htmlspecialchars($c['name']) . "</option>";
                        }
                        ?>
                    </select>
                    <label>Year:</label>
                    <select name="year" required>
                      <option value="">-- Select Year --</option>
                      <option value="1">First Year</option>
                      <option value="2">Second Year</option>
                      <option value="3">Third Year</option>
                      <option value="4">Fourth Year</option>
                    </select>

                    <label>Semester:</label>
                    <select name="semester" required>
                      <option value="">-- Select Semester --</option>
                      <option value="1">Semester 1</option>
                      <option value="2">Semester 2</option>
                    </select>
                </div>
                <label>Unit Name:</label>
                <input type="text" name="unit_name" required>
                <label>Unit Code:</label>
                <input type="text" name="unit_code" required>
                <button type="submit">Save Unit</button>
            </form>
        </div>
    </div>

    <!-- UNIT MODAL (Multiple Units) -->
    <div id="unitModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('unitModal')">×</span>
            <h3>Add Units (Max 8)</h3>
            <?php if (!empty($unit_success)) echo "<p class='success'>$unit_success</p>"; ?>
            <?php if (!empty($unit_error)) echo "<p class='error'>$unit_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_multiple_units">
                <div class="unit-selection">
                    <label>Course:</label>
                    <select name="course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php
                        $courses = $conn->query("SELECT * FROM courses");
                        while ($c = $courses->fetch_assoc()) {
                            echo "<option value='{$c['id']}'>" . htmlspecialchars($c['name']) . "</option>";
                        }
                        ?>
                    </select>
                    <label>Year:</label>
                    <select name="year" required>
                      <option value="">-- Select Year --</option>
                      <option value="1">First Year</option>
                      <option value="2">Second Year</option>
                      <option value="3">Third Year</option>
                      <option value="4">Fourth Year</option>
                    </select>

                    <label>Semester:</label>
                    <select name="semester" required>
                      <option value="">-- Select Semester --</option>
                      <option value="1">Semester 1</option>
                      <option value="2">Semester 2</option>
                    </select>
                </div>
                <hr>
                <div id="unitContainer">
                    <div class="unit-box">
                        <h4>Unit 1</h4>
                        <div class="unit-inputs">
                            <label>Unit Name:</label>
                            <input type="text" name="unit_name[]" required>
                            <label>Unit Code:</label>
                            <input type="text" name="unit_code[]" required>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="addUnit()">+ Add Another Unit</button>
                <button type="submit">Save All Units</button>
            </form>
        </div>
    </div>

    <!-- LECTURER MODAL -->
    <div id="lecturerModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('lecturerModal')">×</span>
            <h3>Add Lecturer</h3>
            <?php if (!empty($lecturer_success)) echo "<p class='success'>$lecturer_success</p>"; ?>
            <?php if (!empty($lecturer_error)) echo "<p class='error'>$lecturer_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_lecturer">
                <label>Name:</label>
                <input type="text" name="lecturer_name" required>
                <label>Email:</label>
                <input type="email" name="lecturer_email" required>
                <label>Password:</label>
                <input type="password" name="lecturer_password" required>
                <label>University:</label>
                <select name="university_id" required>
                    <option value="">-- Select University --</option>
                    <?php
                    $universities = $conn->query("SELECT * FROM universities");
                    while ($u = $universities->fetch_assoc()) {
                        echo "<option value='{$u['id']}'>" . htmlspecialchars($u['name']) . "</option>";
                    }
                    ?>
                </select>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>

<!-- EMAIL VERIFICATION MODAL -->
<div id="verifyEmailModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal('verifyEmailModal')">×</span>
        <h3>Verify Student Email</h3>

        <?php if (!empty($verify_success)) : ?>
    <div class="success">
        <?php echo $verify_success; ?>
    </div>
<?php endif; ?>

<?php if (!empty($verify_error)) : ?>
    <div class="error">
        <?php echo $verify_error; ?>
    </div>
<?php endif; ?>


        <form method="POST" action="../actions.php">
            <input type="hidden" name="action" value="verify_student_email">

            <label>Student Email:</label>
            <input type="email" name="student_email" required>

            <button type="submit">Verify</button><br><br>

        </form>
        <!-- Pending Approval Button -->
        <div style="margin-top:15px; text-align:center;">
            <a href="pendingreq.php" class="btn-secondary">
                View Pending Approvals
            </a>
        </div>
    </div>
</div>

</div> <!-- end .content -->

<!-- ===========================
     All JS consolidated below
     =========================== -->
<script>
    function openModal(id) {
    document.getElementById(id).style.display = "block";
}

function closeModal(id) {
    document.getElementById(id).style.display = "none";
}

/* Helper: safe JSON parse with debugging */
function parseJSONSafe(text) {
    if (text === null || text === undefined || text.trim() === '') {
        return null;
    }
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Failed to parse JSON. Raw response:', text);
        throw e;
    }
}

/* Floating message UI (single implementation) */
function showFloatingMessage(message, type = 'success') {
    const messageDiv = document.getElementById('floatingMessage');
    if (!messageDiv) return;
    messageDiv.textContent = message;
    messageDiv.className = `floating-message ${type}`;
    messageDiv.style.display = 'block';
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 3000);
}

/* Off-Canvas Menu Logic: defensive checks */
const hamburgerBtn = document.getElementById('hamburgerMenu');
const closeMenuBtn = document.getElementById('closeMenuBtn');
const offCanvasMenu = document.getElementById('offCanvasMenu');
const menuOverlay = document.getElementById('menuOverlay');

function toggleOffCanvasMenu() {
    if (!offCanvasMenu || !menuOverlay) return;
    offCanvasMenu.classList.toggle('active');
    menuOverlay.classList.toggle('active');
}
if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleOffCanvasMenu);
if (closeMenuBtn) closeMenuBtn.addEventListener('click', toggleOffCanvasMenu);
if (menuOverlay) menuOverlay.addEventListener('click', toggleOffCanvasMenu);

const menuItems = document.querySelectorAll('.off-canvas-menu .menu-item');
if (menuItems && menuItems.length) {
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            setTimeout(toggleOffCanvasMenu, 150);
        });
    });
}

/* Basic modal functions */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'block';
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let i = 0; i < modals.length; i++) {
        if (event.target === modals[i]) {
            modals[i].style.display = 'none';
        }
    }
}

/* Add Unit Logic for Multiple Units Modal */
let unitCount = 1;
function addUnit() {
    if (unitCount >= 8) {
        alert('Maximum of 8 units allowed.');
        return;
    }
    unitCount++;
    const container = document.getElementById('unitContainer');
    if (!container) return;
    const box = document.createElement('div');
    box.className = 'unit-box';
    box.innerHTML = `
        <h4>Unit ${unitCount}</h4>
        <div class="unit-inputs">
            <label>Unit Name:</label>
            <input type="text" name="unit_name[]" required>
            <label>Unit Code:</label>
            <input type="text" name="unit_code[]" required>
        </div>
    `;
    container.appendChild(box);
}

/* Filter courses by university (safe JSON parsing) */
function filterByUniversity(universityId) {
    const courseSelect = document.querySelector('select[name="course_id"]') || document.getElementById('courseSelect');
    if (!courseSelect) return;
    if (!universityId) {
        // Reset select to original or to a placeholder
        // Optionally you could reload the page or repopulate from a stored list
        courseSelect.innerHTML = '<option value="">-- Select a Course --</option>';
        return;
    }
    fetch(`../actions.php?action=get_university_data&university_id=${encodeURIComponent(universityId)}`)
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = parseJSONSafe(text);
        } catch (e) {
            showFloatingMessage('Invalid response from server', 'error');
            return;
        }
        if (!data || !Array.isArray(data.courses)) {
            courseSelect.innerHTML = '<option value="">-- Select a Course --</option>';
            return;
        }
        courseSelect.innerHTML = '<option value="">-- Select a Course --</option>';
        data.courses.forEach(course => {
            const opt = document.createElement('option');
            opt.value = course.id;
            const label = course.name + (course.unit_count ? ` (${course.unit_count} units)` : '');
            opt.textContent = label;
            courseSelect.appendChild(opt);
        });
    })
    .catch(err => {
        console.error('Error fetching university data:', err);
        showFloatingMessage('Error loading courses for that university', 'error');
    });
}

/* View Course Units (safe response handling) */
function viewCourseUnits() {
    const courseSelect = document.getElementById('courseSelect');
    if (!courseSelect) return;
    const courseId = courseSelect.value;
    if (!courseId) {
        showFloatingMessage('Please select a course first', 'error');
        return;
    }

    const floatingDisplay = document.getElementById('floatingUnitsDisplay');
    const unitsGrid = document.getElementById('unitsGrid');
    if (floatingDisplay) floatingDisplay.classList.add('active');
    if (unitsGrid) unitsGrid.innerHTML = '<div class="loading">Loading units...</div>';

    fetch(`../actions.php?action=get_course_units&course_id=${encodeURIComponent(courseId)}`)
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = parseJSONSafe(text);
        } catch (e) {
            unitsGrid.innerHTML = '<div class="error-message">Invalid server response.</div>';
            showFloatingMessage('Error parsing server response', 'error');
            return;
        }

        if (!data || !data.course) {
            unitsGrid.innerHTML = '<div class="error-message">Course not found.</div>';
            showFloatingMessage('Course not found', 'error');
            return;
        }

        // Update header
        if (floatingDisplay) {
            const header = floatingDisplay.querySelector('.floating-header h3');
            if (header) header.textContent = `${data.course.department_name || ''} - ${data.course.course_name || ''}`;
        }

        if (!data.units || data.units.length === 0) {
            unitsGrid.innerHTML = '<div class="empty-message">No units found for this course.</div>';
            return;
        }

        // Sort units by year then semester
        const sortedUnits = data.units.sort((a,b) => {
            if (a.year !== b.year) return a.year - b.year;
            return (a.semester || 0) - (b.semester || 0);
        });

        unitsGrid.innerHTML = '';
        sortedUnits.forEach(unit => {
            const unitCard = document.createElement('div');
            unitCard.className = 'unit-card';
            unitCard.innerHTML = `
                <div class="unit-info">
                    <h4>${escapeHtml(unit.name)}</h4>
                    <div class="unit-code">${escapeHtml(unit.code)}</div>
                    <div class="unit-meta">Year ${escapeHtml(unit.year)}${unit.semester ? ', Semester ' + escapeHtml(unit.semester) : ''}</div>
                </div>
                <button class="delete-btn" onclick="showDeleteUnitModal(${Number(unit.id)}, '${escapeJs(unit.code)}')" title="Delete Unit">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
            unitsGrid.appendChild(unitCard);
        });
    })
    .catch(err => {
        console.error('Error:', err);
        if (unitsGrid) unitsGrid.innerHTML = '<div class="error-message">Error loading units. Please try again.</div>';
        showFloatingMessage('Error loading units: ' + (err.message || err), 'error');
    });
}

function closeFloatingDisplay() {
    const floatingDisplay = document.getElementById('floatingUnitsDisplay');
    if (floatingDisplay) floatingDisplay.classList.remove('active');
}

/* Delete unit modal/confirm (safe JSON handling) */
function showDeleteUnitModal(unitId, unitCode) {
    const idInput = document.getElementById('deleteUnitId');
    const modalContent = document.querySelector('#deleteUnitModal p');
    if (idInput) idInput.value = unitId;
    if (modalContent) modalContent.textContent = `Delete ${unitCode}?`;
    openModal('deleteUnitModal');
}

function confirmDeleteUnit() {
    const unitId = document.getElementById('deleteUnitId')?.value;
    if (!unitId) {
        showFloatingMessage('Invalid unit selected', 'error');
        return;
    }

    fetch('../actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_unit&unit_id=${encodeURIComponent(unitId)}`
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = parseJSONSafe(text);
        } catch (e) {
            showFloatingMessage('Invalid server response', 'error');
            return;
        }
        if (data && data.status === 'success') {
            closeModal('deleteUnitModal');
            viewCourseUnits();
            showFloatingMessage(data.message || 'Unit deleted', 'success');
        } else {
            showFloatingMessage((data && data.message) ? data.message : 'Failed to delete unit', 'error');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        showFloatingMessage('An error occurred while deleting the unit', 'error');
    });
}

/* Export units PDF */
function exportUnitsPDF() {
    const courseId = document.getElementById('courseSelect')?.value;
    if (!courseId) {
        alert('Please select a course first');
        return;
    }
    window.open(`../actions.php?action=generate_unit_pdf&course_id=${encodeURIComponent(courseId)}`, '_blank');
}

/* Department form submit via AJAX */
function submitDepartmentForm(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    fetch('../actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = parseJSONSafe(text);
        } catch (e) {
            showFloatingMessage('Invalid server response', 'error');
            return;
        }
        if (data && data.status === 'success') {
            showFloatingMessage(data.message, 'success');
            closeModal('departmentModal');
            setTimeout(() => { location.reload(); }, 1200);
        } else {
            showFloatingMessage((data && data.message) ? data.message : 'Failed to add department', 'error');
        }
    })
    .catch(err => {
        console.error('Department submit error:', err);
        showFloatingMessage('An error occurred while submitting the form', 'error');
    });
}

/* simple escaping helpers for DOM insertion */
function escapeHtml(str) {
    if (str === undefined || str === null) return '';
    return String(str).replace(/[&<>"'`=\/]/g, function(s) {
        return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","`":"&#x60;","/":"&#x2F;"}[s];
    });
}
/* escape for JS string literal (used when injecting into onclick attr) */
function escapeJs(str) {
    if (str === undefined || str === null) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '');
}

/* End of consolidated JS */
</script>
</body>
</html>
