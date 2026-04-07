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
        .unit-actions { display:flex; gap:8px; }
        .edit-btn { background:#3498db; color:white; border:none; padding:6px 8px; border-radius:4px; cursor:pointer; font-size:12px; }
        .edit-btn:hover { background:#2980b9; }
        .delete-btn { background:#e74c3c; color:white; border:none; padding:6px 8px; border-radius:4px; cursor:pointer; font-size:12px; }
        .delete-btn:hover { background:#c0392b; }
        .year-block { margin-bottom:20px; border:1px solid #ddd; border-radius:8px; overflow:hidden; }
        .year-header { background:#34495e; color:white; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; }
        .year-header h4 { margin:0; font-size:16px; }
        .unit-count { background:rgba(255,255,255,0.2); padding:4px 8px; border-radius:12px; font-size:12px; }
        .year-units { padding:12px; display:grid; gap:8px; }
        .registration-stats-section { margin-top: 30px; padding: 22px; background: #fff; border: 1px solid #ececec; border-radius: 12px; }
        .registration-stats-section h3 { margin-bottom: 18px; }
        .registration-controls { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; margin-bottom: 18px; }
        .registration-controls .select-group { display: flex; flex-direction: column; min-width: 220px; }
        .registration-controls label { margin-bottom: 8px; font-weight: 600; color: #333; }
        .registration-controls select, .registration-controls button { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #ccc; font-size: 14px; }
        .registration-summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .registration-card { background: #f8f9fa; border: 1px solid #e3e6ea; border-radius: 10px; padding: 16px; min-height: 108px; }
        .registration-card h4 { margin: 0 0 10px; font-size: 14px; color: #555; }
        .registration-card .count { font-size: 28px; font-weight: 700; color: #2c3e50; }
        .registration-students-view { margin-top: 14px; }
        .students-table-wrapper { overflow-x: auto; border: 1px solid #e0e0e0; border-radius: 10px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; }
        .student-count-text { margin-top: 10px; color: #555; }
        .empty-state { color: #777; font-size: 14px; padding: 16px; background: #fafafa; border-radius: 10px; border: 1px dashed #ddd; }
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

        /* ── Delete Test Data Modal ── */
        #deleteTestDataModal .modal-content {
            max-width: 800px;
            width: 95%;
        }
        .test-data-category-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 12px;
        }
        .test-data-tab-btn {
            padding: 8px 16px;
            border: none;
            border-bottom: 3px solid transparent;
            background: #f5f5f5;
            color: #666;
            cursor: pointer;
            border-radius: 4px 4px 0 0;
            font-weight: 500;
            transition: all 0.2s;
        }
        .test-data-tab-btn.active {
            background: #fff;
            color: #2c3e50;
            border-bottom-color: #3498db;
        }
        .test-data-tab-btn:hover {
            background: #fff;
        }
        #testDataTableWrapper {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        #testDataTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        #testDataTable thead {
            position: sticky;
            top: 0;
            background: #2c3e50;
            color: #fff;
            z-index: 2;
        }
        #testDataTable th, #testDataTable td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        #testDataTable tbody tr:hover { background: #f9f9f9; }
        #testDataTable tbody tr.selected { background: #ffeec9; }
        #selectAllTestData { cursor: pointer; width: 15px; height: 15px; }
        .test-data-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        #testDataCount { font-size: 13px; color: #666; }
        .btn-bulk-delete-test {
            background: #d63031; color: #fff; border: none;
            padding: 8px 18px; border-radius: 6px; cursor: pointer;
            font-size: 14px; font-weight: 500;
        }
        .btn-bulk-delete-test:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-bulk-delete-test:not(:disabled):hover { background: #b92b26; }
        .btn-mark-all {
            background: #495057; color: #fff; border: none;
            padding: 8px 16px; border-radius: 6px; cursor: pointer;
            font-size: 13px; font-weight: 500; transition: all 0.2s;
        }
        .btn-mark-all:hover { background: #343a40; }
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
    <!-- NEW: Delete Test Data -->
    <button class="menu-item" onclick="openDeleteTestDataModal()" style="color:#d63031;">
        <i class="fas fa-flask"></i> Delete Test Data
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

    <div class="registration-stats-section">
        <h3>Student Registration by Course / Year</h3>
        <div class="registration-controls">
            <div class="select-group">
                <label for="registrationCourseSelect">Course</label>
                <select id="registrationCourseSelect" onchange="loadRegistrationStats(this.value)">
                    <option value="">-- Select a Course --</option>
                    <?php
                    $registration_courses = $conn->query("SELECT c.id, c.name AS course_name, d.name AS department_name FROM courses c JOIN departments d ON c.department_id = d.id ORDER BY d.name, c.name");
                    while ($course = $registration_courses->fetch_assoc()) {
                        $label = htmlspecialchars($course['department_name']) . ' - ' . htmlspecialchars($course['course_name']);
                        echo "<option value='" . $course['id'] . "'>" . $label . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="select-group">
                <label for="registrationYearSelect">Year</label>
                <select id="registrationYearSelect" onchange="loadStudentsForSelectedYear()" disabled>
                    <option value="">Select year after course</option>
                </select>
            </div>
            <button type="button" id="downloadRegistrationPdf" class="btn btn-success" onclick="downloadRegistrationPDF()" disabled>
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
        </div>
        <div class="registration-summary-cards" id="registrationYearCards">
            <div class="empty-state">Select a course to see registration counts by year.</div>
        </div>
        <div class="registration-students-view" id="registrationStudentsView" style="display:none;">
            <h4 id="selectedCourseTitle"></h4>
            <div class="students-table-wrapper">
                <table class="table" id="registrationStudentsTable">
                    <thead>
                        <tr>
                            <th>Reg No</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Year</th>
                            <th>Joined</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="registrationStudentsBody"></tbody>
                </table>
            </div>
            <div id="registrationStudentCount" class="student-count-text"></div>
        </div>
    </div>

    <div class="recent-activity-section">
        <h3>Course Units Management</h3>
        <div class="course-units-header">
            <div class="select-container">
                <select name="course_id" id="courseSelect" required onchange="onCourseChanged()">
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
                        echo "<option value='" . $course['id'] . "'>$label</option>";
                    }
                    ?>
                </select>
                <div class="button-group" style="display:grid; gap:8px; grid-template-columns: repeat(4, minmax(0, 1fr));">
                    <button type="button" onclick="viewCourseUnits()" class="btn btn-primary" id="viewUnitsBtn" disabled>
                        <i class="fas fa-eye"></i> View Units
                    </button>
                    <button type="button" onclick="openEditCourseModal()" class="btn btn-secondary" id="editCourseBtn" disabled>
                        <i class="fas fa-edit"></i> Edit Course
                    </button>
                    <button type="button" onclick="confirmDeleteCourse()" class="btn btn-danger" id="deleteCourseBtn" disabled>
                        <i class="fas fa-trash-alt"></i> Delete Course
                    </button>
                    <button type="button" onclick="exportUnitsPDF()" class="btn btn-success" id="exportPdfBtn" disabled>
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
        <!-- NEW action card for test data -->
        <div class="action-card" onclick="openDeleteTestDataModal()" style="border-color:#d63031;">
            <div class="icon" style="color:#d63031;"><i class="fas fa-flask"></i></div>
            <h3 style="color:#d63031;">Delete Test Data</h3>
            <p>Remove test records from various tables.</p>
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

    <div id="editCourseModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editCourseModal')">×</span>
            <h3>Edit Course</h3>
            <form id="editCourseForm" method="POST" action="../actions.php">
                <input type="hidden" name="action" value="edit_course">
                <input type="hidden" name="course_id" id="editCourseId">
                <label>Course Name:</label>
                <input type="text" name="course_name" id="editCourseName" required>
                <label>Department:</label>
                <select name="department_id" id="editCourseDepartment" required>
                    <option value="">-- Select Department --</option>
                    <?php
                    $departments = $conn->query("SELECT * FROM departments ORDER BY name ASC");
                    while ($d = $departments->fetch_assoc()) {
                        echo "<option value='" . $d['id'] . "'>" . htmlspecialchars($d['name']) . "</option>";
                    }
                    ?>
                </select>
                <label>Duration (Years):</label>
                <input type="number" name="duration" id="editCourseDuration" min="1" required>
                <label>Course Type:</label>
                <select name="course_type" id="editCourseType" required>
                    <option value="">-- Select Type --</option>
                    <option value="Degree">Degree</option>
                    <option value="Diploma">Diploma</option>
                    <option value="Certificate">Certificate</option>
                </select>
                <div style="display:flex; gap:10px; margin-top:18px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCourseModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
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

    <!-- Edit Unit Modal -->
    <div id="editUnitModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editUnitModal')">×</span>
            <h3>Edit Unit</h3>
            <form id="editUnitForm" method="POST" action="../actions.php">
                <input type="hidden" name="action" value="edit_unit">
                <input type="hidden" id="editUnitId" name="unit_id">
                
                <label>Unit Name:</label>
                <input type="text" id="editUnitName" name="unit_name" required>
                
                <label>Unit Code:</label>
                <input type="text" id="editUnitCode" name="unit_code" required>
                
                <label>Year:</label>
                <select id="editUnitYear" name="year" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">6</option>
                </select>
                
                <label>Semester:</label>
                <select id="editUnitSemester" name="semester" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                </select>
                
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" onclick="closeModal('editUnitModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Unit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Unit Modal -->
    <div id="deleteUnitModal" class="modal delete-modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deleteUnitModal')">×</span>
            <h3>Delete Unit</h3>
            <p>Are you sure you want to delete this unit?</p>
            <input type="hidden" id="deleteUnitId">
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button onclick="closeModal('deleteUnitModal')" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmDeleteUnit()" class="btn btn-danger">Delete</button>
            </div>
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

    <!-- ===================== DELETE TEST DATA MODAL ===================== -->
    <div id="deleteTestDataModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deleteTestDataModal')">×</span>
            <h3><i class="fas fa-flask" style="color:#d63031;"></i> Delete Test Data</h3>

            <!-- Category Tabs -->
            <div class="test-data-category-tabs">
                <button class="test-data-tab-btn active" onclick="switchTestDataCategory('notes')">
                    <i class="fas fa-sticky-note"></i> Notes
                </button>
                <button class="test-data-tab-btn" onclick="switchTestDataCategory('assignments')">
                    <i class="fas fa-tasks"></i> Assignments
                </button>
                <button class="test-data-tab-btn" onclick="switchTestDataCategory('submissions')">
                    <i class="fas fa-file-upload"></i> Submissions
                </button>
                <button class="test-data-tab-btn" onclick="switchTestDataCategory('attendance')">
                    <i class="fas fa-clipboard-list"></i> Attendance
                </button>
                <button class="test-data-tab-btn" onclick="switchTestDataCategory('meetings')">
                    <i class="fas fa-video"></i> Meetings
                </button>
                <button class="test-data-tab-btn" onclick="switchTestDataCategory('enrollments')">
                    <i class="fas fa-user-check"></i> Enrollments
                </button>
                <button class="test-data-tab-btn" onclick="switchTestDataCategory('notifications')">
                    <i class="fas fa-bell"></i> Notifications
                </button>
            </div>

            <!-- Mark All / Unmark All Buttons -->
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <button class="btn-mark-all" onclick="markAllTestData(true)">
                    <i class="fas fa-check-square"></i> Mark All
                </button>
                <button class="btn-mark-all" onclick="markAllTestData(false)" style="background:#6c757d;">
                    <i class="fas fa-square"></i> Unmark All
                </button>
            </div>

            <!-- Table -->
            <div id="testDataTableWrapper">
                <table id="testDataTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllTestData" title="Select all"></th>
                            <th id="testDataCol1">ID</th>
                            <th id="testDataCol2">Name/Title</th>
                            <th id="testDataCol3">Date</th>
                        </tr>
                    </thead>
                    <tbody id="testDataTableBody">
                        <tr><td colspan="4" class="loading-students"><i class="fas fa-spinner fa-spin"></i> Loading data...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="test-data-footer">
                <span id="testDataCount">0 records</span>
                <button class="btn-bulk-delete-test" id="bulkDeleteTestBtn" disabled onclick="confirmBulkDeleteTestData()">
                    <i class="fas fa-trash-alt"></i> Delete Selected
                </button>
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
        
        // Group units by year
        const unitsByYear = {};
        data.units.forEach(unit => {
            const year = unit.year || 1;
            if (!unitsByYear[year]) unitsByYear[year] = [];
            unitsByYear[year].push(unit);
        });
        
        ug.innerHTML = '';
        // Display units in year blocks (1-6)
        for (let year = 1; year <= 6; year++) {
            if (unitsByYear[year] && unitsByYear[year].length > 0) {
                const yearBlock = document.createElement('div');
                yearBlock.className = 'year-block';
                yearBlock.innerHTML = `
                    <div class="year-header">
                        <h4>Year ${year}</h4>
                        <span class="unit-count">${unitsByYear[year].length} unit${unitsByYear[year].length !== 1 ? 's' : ''}</span>
                    </div>
                    <div class="year-units"></div>
                `;
                
                const yearUnitsContainer = yearBlock.querySelector('.year-units');
                unitsByYear[year].sort((a,b) => (a.semester||0)-(b.semester||0)).forEach(unit => {
                    const card = document.createElement('div');
                    card.className = 'unit-card';
                    card.innerHTML = `
                        <div class="unit-info">
                            <h4>${escapeHtml(unit.name)}</h4>
                            <div class="unit-code">${escapeHtml(unit.code)}</div>
                            <div class="unit-meta">Semester ${unit.semester||1}</div>
                        </div>
                        <div class="unit-actions">
                            <button class="edit-btn" onclick="showEditUnitModal(${Number(unit.id)}, '${escapeJs(unit.name)}', '${escapeJs(unit.code)}', ${unit.year}, ${unit.semester||1})" title="Edit Unit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="delete-btn" onclick="showDeleteUnitModal(${Number(unit.id)},'${escapeJs(unit.code)}')" title="Delete Unit">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    yearUnitsContainer.appendChild(card);
                });
                
                ug.appendChild(yearBlock);
            }
        }
    }).catch(() => { if(ug) ug.innerHTML = '<div class="error-message">Error loading units.</div>'; });
}
function closeFloatingDisplay() { document.getElementById('floatingUnitsDisplay')?.classList.remove('active'); }
function showEditUnitModal(unitId, unitName, unitCode, year, semester) {
    document.getElementById('editUnitId').value = unitId;
    document.getElementById('editUnitName').value = unitName;
    document.getElementById('editUnitCode').value = unitCode;
    document.getElementById('editUnitYear').value = year;
    document.getElementById('editUnitSemester').value = semester;
    openModal('editUnitModal');
}
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

// Handle edit unit form submission
document.getElementById('editUnitForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('../actions.php', { method:'POST', body:formData })
    .then(r => r.text()).then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { showFloatingMessage('Invalid response', 'error'); return; }
        if (data?.status === 'success') { 
            closeModal('editUnitModal'); 
            viewCourseUnits(); 
            showFloatingMessage(data.message||'Unit updated', 'success'); 
        }
        else showFloatingMessage(data?.message||'Failed to update unit', 'error');
    }).catch(() => showFloatingMessage('Error updating unit', 'error'));
});
function exportUnitsPDF() {
    const courseId = document.getElementById('courseSelect')?.value;
    if (!courseId) { alert('Please select a course first'); return; }
    window.open(`../actions.php?action=generate_unit_pdf&course_id=${encodeURIComponent(courseId)}`, '_blank');
}

function onCourseChanged() {
    const courseId = document.getElementById('courseSelect')?.value;
    const viewBtn = document.getElementById('viewUnitsBtn');
    const editBtn = document.getElementById('editCourseBtn');
    const deleteBtn = document.getElementById('deleteCourseBtn');
    const pdfBtn = document.getElementById('exportPdfBtn');

    if (viewBtn) viewBtn.disabled = !courseId;
    if (editBtn) editBtn.disabled = !courseId;
    if (deleteBtn) deleteBtn.disabled = !courseId;
    if (pdfBtn) pdfBtn.disabled = !courseId;
}

function openEditCourseModal() {
    const courseId = document.getElementById('courseSelect')?.value;
    if (!courseId) {
        alert('Please select a course first.');
        return;
    }

    fetch(`../actions.php?action=get_course_details&course_id=${encodeURIComponent(courseId)}`)
        .then(r => r.text())
        .then(text => {
            const data = parseJSONSafe(text);
            if (!data || data.status !== 'success') {
                showFloatingMessage(data?.message || 'Unable to load course details.', 'error');
                return;
            }

            document.getElementById('editCourseId').value = data.course.id;
            document.getElementById('editCourseName').value = data.course.name;
            document.getElementById('editCourseDepartment').value = data.course.department_id;
            document.getElementById('editCourseDuration').value = data.course.duration;
            document.getElementById('editCourseType').value = data.course.course_type;
            openModal('editCourseModal');
        })
        .catch(() => showFloatingMessage('Failed to load course details.', 'error'));
}

function confirmDeleteCourse() {
    const courseId = document.getElementById('courseSelect')?.value;
    if (!courseId) {
        alert('Please select a course first.');
        return;
    }

    if (!confirm('Delete this course and all of its units? This cannot be undone.')) {
        return;
    }

    fetch('../actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_course&course_id=${encodeURIComponent(courseId)}`
    })
    .then(r => r.text())
    .then(text => {
        const data = parseJSONSafe(text);
        if (data?.status === 'success') {
            showFloatingMessage(data.message || 'Course deleted successfully.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showFloatingMessage(data?.message || 'Course deletion failed.', 'error');
        }
    })
    .catch(() => showFloatingMessage('Error deleting course.', 'error'));
}

let selectedRegistrationCourseId = null;
let selectedRegistrationYear = null;

function clearRegistrationSection() {
    const summaryCards = document.getElementById('registrationYearCards');
    const yearSelect = document.getElementById('registrationYearSelect');
    const studentsView = document.getElementById('registrationStudentsView');
    const courseTitle = document.getElementById('selectedCourseTitle');
    const pdfBtn = document.getElementById('downloadRegistrationPdf');

    if (summaryCards) summaryCards.innerHTML = '<div class="empty-state">Select a course to see registration counts by year.</div>';
    if (yearSelect) {
        yearSelect.innerHTML = '<option value="">Select year after course</option>';
        yearSelect.disabled = true;
    }
    if (studentsView) studentsView.style.display = 'none';
    if (courseTitle) courseTitle.textContent = '';
    if (pdfBtn) pdfBtn.disabled = true;
    selectedRegistrationCourseId = null;
    selectedRegistrationYear = null;
}

function loadRegistrationStats(courseId) {
    selectedRegistrationCourseId = courseId;
    const summaryCards = document.getElementById('registrationYearCards');
    const yearSelect = document.getElementById('registrationYearSelect');
    const studentsView = document.getElementById('registrationStudentsView');
    const courseTitle = document.getElementById('selectedCourseTitle');
    const pdfBtn = document.getElementById('downloadRegistrationPdf');

    if (!courseId) {
        clearRegistrationSection();
        return;
    }

    if (summaryCards) summaryCards.innerHTML = '<div class="empty-state">Loading registration counts...</div>';
    if (studentsView) studentsView.style.display = 'none';
    if (courseTitle) courseTitle.textContent = '';
    if (pdfBtn) pdfBtn.disabled = true;

    fetch(`../actions.php?action=get_course_registration_stats&course_id=${encodeURIComponent(courseId)}`)
        .then(r => r.text())
        .then(text => {
            const data = parseJSONSafe(text);
            if (!data || data.status !== 'success') {
                if (summaryCards) summaryCards.innerHTML = `<div class="empty-state">${escapeHtml(data?.message || 'No registration data available.')}</div>`;
                return;
            }
            renderRegistrationStats(data);
        })
        .catch(() => {
            if (summaryCards) summaryCards.innerHTML = '<div class="empty-state">Error loading registration data.</div>';
        });
}

function renderRegistrationStats(data) {
    const summaryCards = document.getElementById('registrationYearCards');
    const yearSelect = document.getElementById('registrationYearSelect');
    const courseTitle = document.getElementById('selectedCourseTitle');
    const pdfBtn = document.getElementById('downloadRegistrationPdf');

    if (!summaryCards || !yearSelect) return;
    courseTitle.textContent = `${data.course.department_name} - ${data.course.course_name}`;

    if (!data.year_counts || !data.year_counts.length) {
        summaryCards.innerHTML = '<div class="empty-state">No students registered for this course yet.</div>';
        yearSelect.innerHTML = '<option value="">No year data available</option>';
        yearSelect.disabled = true;
        if (pdfBtn) pdfBtn.disabled = true;
        return;
    }

    summaryCards.innerHTML = '';
    const options = ['<option value="">Select Year</option>'];
    data.year_counts.forEach(item => {
        const year = Number(item.year);
        const count = Number(item.count);
        const card = document.createElement('div');
        card.className = 'registration-card';
        card.innerHTML = `<h4>Year ${escapeHtml(year)}</h4><div class="count">${escapeHtml(count)}</div><div>${count === 1 ? 'student' : 'students'}</div>`;
        summaryCards.appendChild(card);
        options.push(`<option value="${year}">Year ${year} (${count})</option>`);
    });

    yearSelect.innerHTML = options.join('');
    yearSelect.disabled = false;
    if (pdfBtn) pdfBtn.disabled = true;
    selectedRegistrationYear = null;
    document.getElementById('registrationStudentsView').style.display = 'none';
}

function loadStudentsForSelectedYear() {
    const year = document.getElementById('registrationYearSelect')?.value;
    selectedRegistrationYear = year;
    const tbody = document.getElementById('registrationStudentsBody');
    const countText = document.getElementById('registrationStudentCount');
    const view = document.getElementById('registrationStudentsView');
    const pdfBtn = document.getElementById('downloadRegistrationPdf');

    if (!selectedRegistrationCourseId || !year) {
        if (view) view.style.display = 'none';
        if (pdfBtn) pdfBtn.disabled = true;
        return;
    }

    if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="loading-students"><i class="fas fa-spinner fa-spin"></i> Loading students...</td></tr>';
    if (countText) countText.textContent = '';

    fetch(`../actions.php?action=get_course_year_students&course_id=${encodeURIComponent(selectedRegistrationCourseId)}&year=${encodeURIComponent(year)}`)
        .then(r => r.text())
        .then(text => {
            const data = parseJSONSafe(text);
            if (!data || data.status !== 'success') {
                if (tbody) tbody.innerHTML = `<tr><td colspan="6">${escapeHtml(data?.message || 'Unable to load students.')}</td></tr>`;
                return;
            }
            renderRegistrationStudents(data.students, year);
            if (pdfBtn) {
                pdfBtn.disabled = false;
            }
        })
        .catch(() => {
            if (tbody) tbody.innerHTML = '<tr><td colspan="6">Error loading students.</td></tr>';
        });
}

function renderRegistrationStudents(students, year) {
    const tbody = document.getElementById('registrationStudentsBody');
    const countText = document.getElementById('registrationStudentCount');
    const view = document.getElementById('registrationStudentsView');

    if (!tbody || !countText || !view) return;
    if (!students || !students.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="padding:18px;text-align:center;color:#666;">No students registered for this year.</td></tr>';
        countText.textContent = '0 students found';
        view.style.display = 'block';
        return;
    }

    tbody.innerHTML = '';
    students.forEach(s => {
        const verified = Number(s.is_verified) === 1 ? 'Verified' : 'Unverified';
        tbody.innerHTML += `
            <tr>
                <td>${escapeHtml(s.reg_no || '')}</td>
                <td>${escapeHtml(s.name || '')}</td>
                <td>${escapeHtml(s.email || '')}</td>
                <td>Year ${escapeHtml(s.year_of_study || '')}</td>
                <td>${escapeHtml(s.year_joined || '')}</td>
                <td>${escapeHtml(verified)}</td>
            </tr>`;
    });

    countText.textContent = `${students.length} student${students.length !== 1 ? 's' : ''} found`;
    view.style.display = 'block';
}

function downloadRegistrationPDF() {
    if (!selectedRegistrationCourseId || !selectedRegistrationYear) {
        showFloatingMessage('Select a course and year first', 'error');
        return;
    }
    window.open(`../actions.php?action=download_registration_pdf&course_id=${encodeURIComponent(selectedRegistrationCourseId)}&year=${encodeURIComponent(selectedRegistrationYear)}`, '_blank');
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

/* ─────────────────────────────────────────
   DELETE TEST DATA FEATURE
───────────────────────────────────────── */
let allTestData = [];
let currentTestDataCategory = 'notes';

function openDeleteTestDataModal() {
    openModal('deleteTestDataModal');
    loadTestData('notes');
}

function switchTestDataCategory(category) {
    currentTestDataCategory = category;
    document.querySelectorAll('.test-data-tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.closest('.test-data-tab-btn').classList.add('active');
    document.getElementById('selectAllTestData').checked = false;
    loadTestData(category);
}

function loadTestData(category) {
    const tbody = document.getElementById('testDataTableBody');
    tbody.innerHTML = '<tr><td colspan="4" class="loading-students"><i class="fas fa-spinner fa-spin"></i> Loading data...</td></tr>';

    fetch(`../actions.php?action=get_test_data&category=${encodeURIComponent(category)}`)
    .then(r => r.text())
    .then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:red;">Error loading data.</td></tr>'; return;
        }
        if (!data || !Array.isArray(data.records)) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#888;">No records found.</td></tr>'; return;
        }
        allTestData = data.records;
        renderTestDataTable(allTestData, category);
    })
    .catch(() => {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:red;">Failed to load records.</td></tr>';
    });
}

function renderTestDataTable(records, category) {
    const tbody = document.getElementById('testDataTableBody');
    const countEl = document.getElementById('testDataCount');
    countEl.textContent = `${records.length} record${records.length !== 1 ? 's' : ''}`;

    if (!records.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#888;padding:20px;">No records found.</td></tr>';
        updateBulkDeleteTestBtn();
        return;
    }

    tbody.innerHTML = '';
    records.forEach(record => {
        const tr = document.createElement('tr');
        tr.dataset.id = record.id;
        tr.innerHTML = `
            <td><input type="checkbox" class="test-data-checkbox" value="${record.id}" onchange="updateBulkDeleteTestBtn()"></td>
            <td>${escapeHtml(String(record.id))}</td>
            <td>${escapeHtml(record.title || record.name || '—')}</td>
            <td>${escapeHtml(record.date || record.created_at || '—')}</td>
        `;
        tbody.appendChild(tr);
    });
    updateBulkDeleteTestBtn();
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAllBtn = document.getElementById('selectAllTestData');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('change', function() {
            document.querySelectorAll('.test-data-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkDeleteTestBtn();
        });
    }
});

function updateBulkDeleteTestBtn() {
    const checked = document.querySelectorAll('.test-data-checkbox:checked').length;
    const btn = document.getElementById('bulkDeleteTestBtn');
    btn.disabled = checked === 0;
    btn.textContent = checked > 0 ? `Delete Selected (${checked})` : 'Delete Selected';
}

function markAllTestData(check) {
    document.querySelectorAll('.test-data-checkbox').forEach(cb => cb.checked = check);
    document.getElementById('selectAllTestData').checked = check;
    updateBulkDeleteTestBtn();
}

function confirmBulkDeleteTestData() {
    const ids = [...document.querySelectorAll('.test-data-checkbox:checked')].map(cb => cb.value);
    if (!ids.length) return;
    if (!confirm(`Delete ${ids.length} selected record(s)? This cannot be undone.`)) return;

    fetch('../actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=bulk_delete_test_data&category=${encodeURIComponent(currentTestDataCategory)}&ids=${encodeURIComponent(ids.join(','))}`
    })
    .then(r => r.text())
    .then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { showFloatingMessage('Invalid response', 'error'); return; }
        if (data?.status === 'success') {
            showFloatingMessage(data.message || `${ids.length} record(s) deleted.`, 'success');
            document.getElementById('selectAllTestData').checked = false;
            loadTestData(currentTestDataCategory);
        } else {
            showFloatingMessage(data?.message || 'Bulk delete failed.', 'error');
        }
    }).catch(() => showFloatingMessage('Error during bulk delete.', 'error'));
}
</script>
</body>
</html>