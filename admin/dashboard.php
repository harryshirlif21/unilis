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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                        echo "<option value='{$course['id']}'>" . 
                             htmlspecialchars($course['department_name']) . " - " . 
                             htmlspecialchars($course['course_name']) . 
                             " (" . $course['unit_count'] . " units)</option>";
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
    <div id="deleteUnitModal" class="modal delete-modal">
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
    <div id="universityModal" class="modal">
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
    <div id="departmentModal" class="modal">
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

    <style>
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

    .floating-message.success {
        background-color: #28a745;
        color: white;
    }

    .floating-message.error {
        background-color: #dc3545;
        color: white;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    </style>

    

    <!-- COURSE MODAL -->
    <div id="courseModal" class="modal">
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
    <div id="unitSingleModal" class="modal">
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
    <div id="unitModal" class="modal">
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
    <div id="lecturerModal" class="modal">
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
</div>



<script>
// ================== Utility: Floating Message ==================
window.showFloatingMessage = function(message, type = 'success') {
    const messageDiv = document.getElementById('floatingMessage');
    if (messageDiv) {
        messageDiv.textContent = message;
        messageDiv.className = `floating-message ${type}`;
        messageDiv.style.display = 'block';
        setTimeout(() => { messageDiv.style.display = 'none'; }, 3000);
    } else {
        alert(`${type.toUpperCase()}: ${message}`);
    }
};

// ================== Modal Handlers ==================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'block';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

// ================== Off-Canvas Menu ==================
const hamburgerBtn = document.getElementById('hamburgerMenu');
const closeMenuBtn = document.getElementById('closeMenuBtn');
const offCanvasMenu = document.getElementById('offCanvasMenu');
const menuOverlay = document.getElementById('menuOverlay');

function toggleOffCanvasMenu() {
    if (offCanvasMenu) offCanvasMenu.classList.toggle('active');
    if (menuOverlay) menuOverlay.classList.toggle('active');
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleOffCanvasMenu);
if (closeMenuBtn) closeMenuBtn.addEventListener('click', toggleOffCanvasMenu);
if (menuOverlay) menuOverlay.addEventListener('click', toggleOffCanvasMenu);

const menuItems = document.querySelectorAll('.off-canvas-menu .menu-item');
menuItems.forEach(item => {
    item.addEventListener('click', () => setTimeout(toggleOffCanvasMenu, 150));
});

// ================== Close modals on outside click ==================
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let i = 0; i < modals.length; i++) {
        if (event.target === modals[i]) {
            modals[i].style.display = 'none';
        }
    }
};

// ================== Add Unit Logic ==================
let unitCount = 1;
function addUnit() {
    if (unitCount >= 8) {
        showFloatingMessage('Maximum of 8 units allowed.', 'error');
        return;
    }
    unitCount++;
    const container = document.getElementById('unitContainer');
    if (container) {
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
};

// ================== Populate Courses ==================
function filterByUniversity(universityId) {
    if (!universityId) return;
    fetch(`../actions.php?action=get_university_data&university_id=${universityId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const selects = document.querySelectorAll('select[name="course_id"]');
            selects.forEach(select => {
                select.innerHTML = '<option value="">-- Select a Course --</option>';
                if (Array.isArray(data.courses)) {
                    data.courses.forEach(course => {
                        const opt = document.createElement('option');
                        opt.value = course.id;
                        opt.textContent = `${course.name} (${course.unit_count} units)`;
                        select.appendChild(opt);
                    });
                }
            });
        })
        .catch(err => {
            console.error('Error:', err);
            showFloatingMessage('Error loading courses', 'error');
        });
};

// ================== Delete Unit ==================
window.openDeleteUnitModal = function(unitId, unitName) {
    const hidden = document.getElementById('deleteUnitId');
    const p = document.querySelector('#deleteUnitModal .modal-content p');
    if (hidden) hidden.value = unitId;
    if (p) p.textContent = `Delete unit "${unitName}"?`;
    openModal('deleteUnitModal');
};

window.confirmDeleteUnit = function() {
    const unitId = document.getElementById('deleteUnitId').value;
    if (!unitId) {
        showFloatingMessage('Invalid unit ID', 'error');
        return;
    }

    fetch('../actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_unit&unit_id=${encodeURIComponent(unitId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showFloatingMessage(data.message || 'Unit deleted successfully', 'success');
            closeModal('deleteUnitModal');
            viewCourseUnits(); // refresh list
        } else {
            showFloatingMessage(data.message || 'Error deleting unit', 'error');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        showFloatingMessage('Error deleting unit', 'error');
    });
};

// ================== Export Units PDF ==================
window.exportUnitsPDF = function() {
    const courseSelect = document.getElementById('courseSelect');
    const courseId = courseSelect ? courseSelect.value : '';
    if (!courseId) {
        showFloatingMessage('Please select a course first', 'error');
        return;
    }
    window.open(`../actions.php?action=generate_unit_pdf&course_id=${courseId}`, '_blank');
};

// ================== View Course Units ==================
window.viewCourseUnits = function() {
    const courseSelect = document.getElementById('courseSelect');
    const courseId = courseSelect ? courseSelect.value : '';
    if (!courseId) {
        showFloatingMessage('Please select a course first', 'error');
        return;
    }

    const floatingDisplay = document.getElementById('floatingUnitsDisplay');
    const unitsGrid = document.getElementById('unitsGrid');
    if (floatingDisplay) floatingDisplay.classList.add('active');
    if (unitsGrid) unitsGrid.innerHTML = '<div class="loading">Loading units...</div>';

    fetch(`../actions.php?action=get_course_units&course_id=${courseId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const header = document.querySelector('#floatingUnitsDisplay .floating-header h3');
            
            // Show course + department gracefully
            if (header) {
                let courseName = "";
                let deptName = "";
                if (typeof data.course === "object" && data.course !== null) {
                    courseName = data.course.course_name || "";
                    deptName = data.course.department_name || "";
                } else if (typeof data.course === "string") {
                    courseName = data.course;
                }
                header.textContent = deptName ? `${deptName} - ${courseName}` : courseName;
            }

            if (unitsGrid) unitsGrid.innerHTML = '';
            if (data.status !== 'success' || !data.units || data.units.length === 0) {
                if (unitsGrid) unitsGrid.innerHTML = '<div class="empty-message">No units found for this course.</div>';
                return;
            }

            const sortedUnits = data.units.slice().sort((a, b) => {
                const yearA = parseInt(a.year, 10);
                const yearB = parseInt(b.year, 10);
                if (yearA !== yearB) return yearA - yearB;
                return parseInt(a.semester, 10) - parseInt(b.semester, 10);
            });

            const frag = document.createDocumentFragment();
            sortedUnits.forEach(unit => {
                const card = document.createElement('div');
                card.className = 'unit-card';
                card.innerHTML = `
                    <div class="unit-info">
                        <h4>${unit.unit_name}</h4>
                        <span class="unit-code">${unit.unit_code}</span>
                        <div class="unit-meta">Year ${unit.year} | Semester ${unit.semester}</div>
                    </div>
                `;
                const delBtn = document.createElement('button');
                delBtn.className = 'delete-btn';
                delBtn.innerHTML = '<i class="fas fa-trash"></i>';
                delBtn.onclick = () => openDeleteUnitModal(unit.id, unit.unit_name);
                card.appendChild(delBtn);
                frag.appendChild(card);
            });
            if (unitsGrid) unitsGrid.appendChild(frag);
        })
        .catch(err => {
            console.error('Fetch error:', err);
            if (unitsGrid) unitsGrid.innerHTML = '<div class="error-message">Error loading units</div>';
            showFloatingMessage(err.message || 'Error loading units', 'error');
        });
};

// ================== Close Floating Display ==================
window.closeFloatingDisplay = function() {
    const floatingDisplay = document.getElementById('floatingUnitsDisplay');
    if (floatingDisplay) floatingDisplay.classList.remove('active');
};

// ================== Submit Department Form ==================
function submitDepartmentForm(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    fetch('../actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showFloatingMessage(data.message, 'success');
            closeModal('departmentModal');
            setTimeout(() => location.reload(), 2000);
        } else {
            showFloatingMessage(data.message, 'error');
        }
    })
    .catch(() => showFloatingMessage('An error occurred while submitting the form', 'error'));
};
</script>

</body>
</html>