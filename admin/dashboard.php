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
            background: #d4edda; color: #155724; padding: 12px;
            border: 1px solid #c3e6cb; border-radius: 6px; margin-bottom: 15px;
        }
        .error {
            background: #f8d7da; color: #721c24; padding: 12px;
            border: 1px solid #f5c6cb; border-radius: 6px; margin-bottom: 15px;
        }
        .floating-message {
            position: fixed; top: 20px; right: 20px; padding: 15px 25px;
            border-radius: 5px; z-index: 9999; display: none;
            animation: slideIn 0.5s ease-out;
        }
        .floating-message.success { background-color: #28a745; color: white; }
        .floating-message.error   { background-color: #dc3545; color: white; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }
        .floating-display { display:none; position:fixed; right:20px; top:80px; width:420px; max-height:70vh; overflow:auto; background:#fff; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.15); z-index:999; }
        .floating-display.active { display:block; }
        .floating-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #eee; }
        .units-grid { padding:12px 16px; display:grid; gap:8px; }
        .unit-card { display:flex; justify-content:space-between; align-items:center; padding:10px; border-radius:6px; background:#fafafa; border:1px solid #eee; }
        .delete-modal .modal-content { max-width:400px; }

        /* ── Students Modal ── */
        #deleteStudentsModal .modal-content {
            max-width: 720px;
            width: 95%;
        }
        .students-toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
            align-items: center;
        }
        .students-toolbar input[type="text"] {
            flex: 1;
            min-width: 180px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .students-toolbar select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        #studentsTableWrapper {
            max-height: 380px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        #studentsTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        #studentsTable thead {
            position: sticky;
            top: 0;
            background: #2c3e50;
            color: #fff;
            z-index: 2;
        }
        #studentsTable th, #studentsTable td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        #studentsTable tbody tr:hover { background: #f9f9f9; }
        #studentsTable tbody tr.selected { background: #fff3cd; }
        .badge-verified   { background:#d4edda; color:#155724; padding:2px 8px; border-radius:12px; font-size:11px; }
        .badge-unverified { background:#f8d7da; color:#721c24; padding:2px 8px; border-radius:12px; font-size:11px; }
        .btn-delete-single {
            background: #dc3545; color: #fff; border: none;
            padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;
        }
        .btn-delete-single:hover { background: #c82333; }
        .students-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        #studentCount { font-size: 13px; color: #666; }
        .btn-bulk-delete {
            background: #dc3545; color: #fff; border: none;
            padding: 8px 18px; border-radius: 6px; cursor: pointer;
            font-size: 14px; font-weight: 500;
        }
        .btn-bulk-delete:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-bulk-delete:not(:disabled):hover { background: #c82333; }
        #selectAllStudents { cursor: pointer; width: 15px; height: 15px; }
        .loading-students { text-align:center; padding: 30px; color: #888; }
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
    <!-- NEW: Delete Students -->
    <button class="menu-item" onclick="openDeleteStudentsModal()" style="color:#e74c3c;">
        <i class="fas fa-user-times"></i> Delete Students
    </button>
    <div class="menu-section-title">System</div>
    <button class="menu-item" onclick="alert('System Settings not implemented yet!')"><i class="fas fa-cogs"></i> System Settings</button>
    <a href="../logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Overlay for Off-Canvas Menu -->
<div class="overlay" id="menuOverlay"></div>

<!-- Main Content Area -->
<div class="content">
    <h2>System Overview</h2>

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

    <div id="floatingUnitsDisplay" class="floating-display">
        <div class="floating-header">
            <h3>Course Units</h3>
            <button class="close-btn" onclick="closeFloatingDisplay()">×</button>
        </div>
        <div class="units-grid" id="unitsGrid"></div>
    </div>

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
        <!-- NEW action card -->
        <div class="action-card" onclick="openDeleteStudentsModal()" style="border-color:#e74c3c;">
            <div class="icon" style="color:#e74c3c;"><i class="fas fa-user-times"></i></div>
            <h3 style="color:#e74c3c;">Delete Students</h3>
            <p>Remove registered students from the system.</p>
        </div>
    </div>

    <!-- ===================== MODALS ===================== -->

    <div id="universityModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('universityModal')">×</span>
            <h3>Add University</h3>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_university">
                <label>University Name:</label>
                <input type="text" name="university_name" required>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>

    <div id="floatingMessage" class="floating-message" style="display: none;"></div>

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

    <div id="courseModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('courseModal')">×</span>
            <h3>Add Course</h3>
            <?php if (!empty($_SESSION['course_success'])): ?>
                <p class="success"><?php echo $_SESSION['course_success']; unset($_SESSION['course_success']); ?></p>
            <?php endif; ?>
            <?php if (!empty($_SESSION['course_error'])): ?>
                <p class="error"><?php echo $_SESSION['course_error']; unset($_SESSION['course_error']); ?></p>
            <?php endif; ?>
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
                <label>Duration (Years):</label>
                <input type="number" name="duration" min="1" required>
                <label>Course Type:</label>
                <select name="course_type" required>
                    <option value="">-- Select Type --</option>
                    <option value="Degree">Degree</option>
                    <option value="Diploma">Diploma</option>
                    <option value="Certificate">Certificate</option>
                </select>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>

    <div id="unitSingleModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('unitSingleModal')">×</span>
            <h3>Add Single Unit</h3>
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

    <div id="unitModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('unitModal')">×</span>
            <h3>Add Units (Max 8)</h3>
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

    <div id="lecturerModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('lecturerModal')">×</span>
            <h3>Add Lecturer</h3>
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

    <div id="verifyEmailModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('verifyEmailModal')">×</span>
            <h3>Verify Student Email</h3>
            <?php if (!empty($verify_success)): ?>
                <div class="success"><?= $verify_success ?></div>
            <?php endif; ?>
            <?php if (!empty($verify_error)): ?>
                <div class="error"><?= $verify_error ?></div>
            <?php endif; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="verify_student_email">
                <label>Student Email:</label>
                <input type="email" name="student_email" required>
                <button type="submit">Verify</button><br><br>
            </form>
            <div style="margin-top:15px; text-align:center;">
                <a href="pendingreq.php" class="btn-secondary">View Pending Approvals</a>
            </div>
        </div>
    </div>

    <!-- ===================== DELETE STUDENTS MODAL ===================== -->
    <div id="deleteStudentsModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deleteStudentsModal')">×</span>
            <h3><i class="fas fa-user-times" style="color:#e74c3c;"></i> Manage Students</h3>

            <!-- Toolbar: search + filter -->
            <div class="students-toolbar">
                <input type="text" id="studentSearch" placeholder="🔍 Search by name, email or reg no..." oninput="filterStudentsTable()">
                <select id="studentVerifiedFilter" onchange="filterStudentsTable()">
                    <option value="">All Students</option>
                    <option value="1">Verified</option>
                    <option value="0">Unverified</option>
                </select>
            </div>

            <!-- Table -->
            <div id="studentsTableWrapper">
                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllStudents" title="Select all"></th>
                            <th>Reg No</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <tr><td colspan="7" class="loading-students"><i class="fas fa-spinner fa-spin"></i> Loading students...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="students-footer">
                <span id="studentCount">0 students</span>
                <button class="btn-bulk-delete" id="bulkDeleteBtn" disabled onclick="confirmBulkDelete()">
                    <i class="fas fa-trash-alt"></i> Delete Selected
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm single delete -->
    <div id="confirmDeleteStudentModal" class="modal delete-modal" style="display:none;">
        <div class="modal-content">
            <h3>⚠️ Confirm Delete</h3>
            <p id="confirmDeleteStudentMsg">Are you sure you want to delete this student?</p>
            <input type="hidden" id="deleteStudentId">
            <div class="modal-actions" style="display:flex; gap:10px; margin-top:16px;">
                <button onclick="closeModal('confirmDeleteStudentModal')" class="btn btn-secondary">Cancel</button>
                <button onclick="executeDeleteStudent()" class="btn btn-danger" style="background:#dc3545; color:#fff; border:none; padding:8px 18px; border-radius:6px; cursor:pointer;">Delete</button>
            </div>
        </div>
    </div>

</div><!-- end .content -->

<script>
/* ─────────────────────────────────────────
   Helpers
───────────────────────────────────────── */
function parseJSONSafe(text) {
    if (!text || !text.trim()) return null;
    try { return JSON.parse(text); } catch(e) { console.error('JSON parse error:', text); throw e; }
}
function showFloatingMessage(message, type = 'success') {
    const d = document.getElementById('floatingMessage');
    if (!d) return;
    d.textContent = message;
    d.className = `floating-message ${type}`;
    d.style.display = 'block';
    setTimeout(() => { d.style.display = 'none'; }, 3000);
}
function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[&<>"'`=\/]/g, s => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","`":"&#x60;","/":"&#x2F;"})[s]);
}
function escapeJs(str) {
    if (str == null) return '';
    return String(str).replace(/'/g,"\\'").replace(/"/g,'\\"').replace(/\n/g,'\\n').replace(/\r/g,'');
}

/* ─────────────────────────────────────────
   Modal open/close
───────────────────────────────────────── */
function openModal(id) { const el = document.getElementById(id); if (el) el.style.display = 'block'; }
function closeModal(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
window.onclick = function(e) {
    document.querySelectorAll('.modal').forEach(m => { if (e.target === m) m.style.display = 'none'; });
};

/* ─────────────────────────────────────────
   Off-Canvas Menu
───────────────────────────────────────── */
const hamburgerBtn   = document.getElementById('hamburgerMenu');
const closeMenuBtn   = document.getElementById('closeMenuBtn');
const offCanvasMenu  = document.getElementById('offCanvasMenu');
const menuOverlay    = document.getElementById('menuOverlay');
function toggleOffCanvasMenu() {
    if (offCanvasMenu) offCanvasMenu.classList.toggle('active');
    if (menuOverlay)   menuOverlay.classList.toggle('active');
}
if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleOffCanvasMenu);
if (closeMenuBtn) closeMenuBtn.addEventListener('click', toggleOffCanvasMenu);
if (menuOverlay)  menuOverlay.addEventListener('click', toggleOffCanvasMenu);
document.querySelectorAll('.off-canvas-menu .menu-item').forEach(item => {
    item.addEventListener('click', () => setTimeout(toggleOffCanvasMenu, 150));
});

/* ─────────────────────────────────────────
   Units
───────────────────────────────────────── */
let unitCount = 1;
function addUnit() {
    if (unitCount >= 8) { alert('Maximum of 8 units allowed.'); return; }
    unitCount++;
    const container = document.getElementById('unitContainer');
    const box = document.createElement('div');
    box.className = 'unit-box';
    box.innerHTML = `<h4>Unit ${unitCount}</h4><div class="unit-inputs"><label>Unit Name:</label><input type="text" name="unit_name[]" required><label>Unit Code:</label><input type="text" name="unit_code[]" required></div>`;
    container.appendChild(box);
}
function filterByUniversity(universityId) {
    const courseSelect = document.getElementById('courseSelect');
    if (!courseSelect) return;
    if (!universityId) { courseSelect.innerHTML = '<option value="">-- Select a Course --</option>'; return; }
    fetch(`../actions.php?action=get_university_data&university_id=${encodeURIComponent(universityId)}`)
    .then(r => r.text()).then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { showFloatingMessage('Invalid response', 'error'); return; }
        courseSelect.innerHTML = '<option value="">-- Select a Course --</option>';
        if (data && Array.isArray(data.courses)) {
            data.courses.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name + (c.unit_count ? ` (${c.unit_count} units)` : ''); courseSelect.appendChild(o); });
        }
    }).catch(() => showFloatingMessage('Error loading courses', 'error'));
}
function viewCourseUnits() {
    const courseId = document.getElementById('courseSelect')?.value;
    if (!courseId) { showFloatingMessage('Please select a course first', 'error'); return; }
    const fd = document.getElementById('floatingUnitsDisplay');
    const ug = document.getElementById('unitsGrid');
    if (fd) fd.classList.add('active');
    if (ug) ug.innerHTML = '<div class="loading">Loading units...</div>';
    fetch(`../actions.php?action=get_course_units&course_id=${encodeURIComponent(courseId)}`)
    .then(r => r.text()).then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { ug.innerHTML = '<div class="error-message">Invalid response.</div>'; return; }
        if (!data || !data.course) { ug.innerHTML = '<div class="error-message">Course not found.</div>'; return; }
        const h = fd?.querySelector('.floating-header h3');
        if (h) h.textContent = `${data.course.department_name||''} - ${data.course.course_name||''}`;
        if (!data.units || !data.units.length) { ug.innerHTML = '<div class="empty-message">No units found.</div>'; return; }
        ug.innerHTML = '';
        data.units.sort((a,b) => a.year !== b.year ? a.year - b.year : (a.semester||0)-(b.semester||0)).forEach(unit => {
            const card = document.createElement('div'); card.className = 'unit-card';
            card.innerHTML = `<div class="unit-info"><h4>${escapeHtml(unit.name)}</h4><div class="unit-code">${escapeHtml(unit.code)}</div><div class="unit-meta">Year ${escapeHtml(unit.year)}${unit.semester?', Semester '+escapeHtml(unit.semester):''}</div></div><button class="delete-btn" onclick="showDeleteUnitModal(${Number(unit.id)},'${escapeJs(unit.code)}')" title="Delete Unit"><i class="fas fa-trash-alt"></i></button>`;
            ug.appendChild(card);
        });
    }).catch(() => { if(ug) ug.innerHTML = '<div class="error-message">Error loading units.</div>'; });
}
function closeFloatingDisplay() { document.getElementById('floatingUnitsDisplay')?.classList.remove('active'); }
function showDeleteUnitModal(unitId, unitCode) {
    document.getElementById('deleteUnitId').value = unitId;
    document.querySelector('#deleteUnitModal p').textContent = `Delete ${unitCode}?`;
    openModal('deleteUnitModal');
}
function confirmDeleteUnit() {
    const unitId = document.getElementById('deleteUnitId')?.value;
    if (!unitId) return;
    fetch('../actions.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=delete_unit&unit_id=${encodeURIComponent(unitId)}` })
    .then(r => r.text()).then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { showFloatingMessage('Invalid response', 'error'); return; }
        if (data?.status === 'success') { closeModal('deleteUnitModal'); viewCourseUnits(); showFloatingMessage(data.message||'Unit deleted', 'success'); }
        else showFloatingMessage(data?.message||'Failed to delete unit', 'error');
    }).catch(() => showFloatingMessage('Error deleting unit', 'error'));
}
function exportUnitsPDF() {
    const courseId = document.getElementById('courseSelect')?.value;
    if (!courseId) { alert('Please select a course first'); return; }
    window.open(`../actions.php?action=generate_unit_pdf&course_id=${encodeURIComponent(courseId)}`, '_blank');
}
function submitDepartmentForm(event) {
    event.preventDefault();
    fetch('../actions.php', { method:'POST', body: new FormData(event.target) })
    .then(r => r.text()).then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { showFloatingMessage('Invalid response', 'error'); return; }
        if (data?.status === 'success') { showFloatingMessage(data.message, 'success'); closeModal('departmentModal'); setTimeout(() => location.reload(), 1200); }
        else showFloatingMessage(data?.message||'Failed to add department', 'error');
    }).catch(() => showFloatingMessage('Error submitting form', 'error'));
}

/* ─────────────────────────────────────────
   DELETE STUDENTS FEATURE
───────────────────────────────────────── */
let allStudents = [];

function openDeleteStudentsModal() {
    openModal('deleteStudentsModal');
    loadStudents();
}

function loadStudents() {
    const tbody = document.getElementById('studentsTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="loading-students"><i class="fas fa-spinner fa-spin"></i> Loading students...</td></tr>';

    fetch('../actions.php?action=get_all_students')
    .then(r => r.text())
    .then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:red;">Error loading students.</td></tr>'; return;
        }
        if (!data || !Array.isArray(data.students)) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;">No students found.</td></tr>'; return;
        }
        allStudents = data.students;
        renderStudentsTable(allStudents);
    })
    .catch(() => {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:red;">Failed to load students.</td></tr>';
    });
}

function renderStudentsTable(students) {
    const tbody = document.getElementById('studentsTableBody');
    const countEl = document.getElementById('studentCount');
    countEl.textContent = `${students.length} student${students.length !== 1 ? 's' : ''}`;

    if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;padding:20px;">No students match your search.</td></tr>';
        updateBulkDeleteBtn();
        return;
    }

    tbody.innerHTML = '';
    students.forEach(s => {
        const tr = document.createElement('tr');
        tr.dataset.id = s.id;
        const verified = parseInt(s.is_verified) === 1;
        tr.innerHTML = `
            <td><input type="checkbox" class="student-checkbox" value="${s.id}" onchange="updateBulkDeleteBtn()"></td>
            <td>${escapeHtml(s.reg_no || '—')}</td>
            <td>${escapeHtml(s.name)}</td>
            <td>${escapeHtml(s.email)}</td>
            <td>Year ${escapeHtml(s.year_of_study || '—')}</td>
            <td><span class="${verified ? 'badge-verified' : 'badge-unverified'}">${verified ? '✔ Verified' : '✘ Unverified'}</span></td>
            <td><button class="btn-delete-single" onclick="promptDeleteStudent(${s.id}, '${escapeJs(s.name)}')"><i class="fas fa-trash"></i> Delete</button></td>
        `;
        tbody.appendChild(tr);
    });
    updateBulkDeleteBtn();
}

function filterStudentsTable() {
    const search  = document.getElementById('studentSearch').value.toLowerCase();
    const verified = document.getElementById('studentVerifiedFilter').value;
    const filtered = allStudents.filter(s => {
        const matchSearch = !search ||
            (s.name  && s.name.toLowerCase().includes(search)) ||
            (s.email && s.email.toLowerCase().includes(search)) ||
            (s.reg_no && s.reg_no.toLowerCase().includes(search));
        const matchVerified = verified === '' || String(s.is_verified) === verified;
        return matchSearch && matchVerified;
    });
    renderStudentsTable(filtered);
}

document.getElementById('selectAllStudents').addEventListener('change', function() {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
    updateBulkDeleteBtn();
});

function updateBulkDeleteBtn() {
    const checked = document.querySelectorAll('.student-checkbox:checked').length;
    const btn = document.getElementById('bulkDeleteBtn');
    btn.disabled = checked === 0;
    btn.textContent = checked > 0 ? `Delete Selected (${checked})` : 'Delete Selected';
}

function promptDeleteStudent(id, name) {
    document.getElementById('deleteStudentId').value = id;
    document.getElementById('confirmDeleteStudentMsg').textContent = `Are you sure you want to delete "${name}"? This cannot be undone.`;
    openModal('confirmDeleteStudentModal');
}

function executeDeleteStudent() {
    const id = document.getElementById('deleteStudentId').value;
    fetch('../actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_student&student_id=${encodeURIComponent(id)}`
    })
    .then(r => r.text())
    .then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { showFloatingMessage('Invalid response', 'error'); return; }
        if (data?.status === 'success') {
            closeModal('confirmDeleteStudentModal');
            showFloatingMessage(data.message || 'Student deleted.', 'success');
            loadStudents();
        } else {
            showFloatingMessage(data?.message || 'Failed to delete student.', 'error');
        }
    }).catch(() => showFloatingMessage('Error deleting student.', 'error'));
}

function confirmBulkDelete() {
    const ids = [...document.querySelectorAll('.student-checkbox:checked')].map(cb => cb.value);
    if (!ids.length) return;
    if (!confirm(`Delete ${ids.length} selected student(s)? This cannot be undone.`)) return;

    fetch('../actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=bulk_delete_students&student_ids=${encodeURIComponent(ids.join(','))}`
    })
    .then(r => r.text())
    .then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { showFloatingMessage('Invalid response', 'error'); return; }
        if (data?.status === 'success') {
            showFloatingMessage(data.message || `${ids.length} students deleted.`, 'success');
            document.getElementById('selectAllStudents').checked = false;
            loadStudents();
        } else {
            showFloatingMessage(data?.message || 'Bulk delete failed.', 'error');
        }
    }).catch(() => showFloatingMessage('Error during bulk delete.', 'error'));
}
</script>
</body>
</html>