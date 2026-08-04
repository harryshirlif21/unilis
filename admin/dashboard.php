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
$admin_success  = $_SESSION['admin_success'] ?? '';
$admin_error    = $_SESSION['admin_error'] ?? '';

unset($_SESSION['verify_success']);
unset($_SESSION['verify_error']);
unset($_SESSION['admin_success']);
unset($_SESSION['admin_error']);

function ensure_academic_year_settings_table(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS academic_year_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            academic_year_label VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($sql);
}

function get_academic_year_settings(mysqli $conn): array
{
    ensure_academic_year_settings_table($conn);
    $row = $conn->query("SELECT * FROM academic_year_settings ORDER BY is_active DESC, updated_at DESC LIMIT 1")
        ->fetch_assoc();

    if (!$row) {
        $defaultLabel = date('Y') . '/' . (date('Y') + 1);
        return [
            'id' => 0,
            'academic_year_label' => $defaultLabel,
            'start_date' => date('Y') . '-01-01',
            'end_date' => date('Y') . '-12-31',
            'is_active' => 1,
        ];
    }

    return $row;
}

function save_academic_year_settings(mysqli $conn, array $data): array
{
    ensure_academic_year_settings_table($conn);

    $label = trim((string)($data['academic_year_label'] ?? ''));
    $startDate = trim((string)($data['start_date'] ?? ''));
    $endDate = trim((string)($data['end_date'] ?? ''));

    if ($label === '') {
        $label = date('Y') . '/' . (date('Y') + 1);
    }
    if ($startDate === '') {
        $startDate = date('Y') . '-01-01';
    }
    if ($endDate === '') {
        $endDate = date('Y') . '-12-31';
    }

    $stmt = $conn->prepare(
        "INSERT INTO academic_year_settings (academic_year_label, start_date, end_date, is_active) VALUES (?, ?, ?, 1)"
    );
    if (!$stmt) {
        throw new Exception('Unable to save academic year settings: ' . $conn->error);
    }

    $stmt->bind_param('sss', $label, $startDate, $endDate);
    $stmt->execute();
    $stmt->close();

    return [
        'success' => true,
        'academic_year_label' => $label,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ];
}

function get_expected_year_of_study(int $registeredYear, string $academicYearLabel): int
{
    $registeredYear = max(1, (int)$registeredYear);
    $parts = preg_split('/[\\/-]/', trim($academicYearLabel));
    $startYear = isset($parts[0]) && is_numeric($parts[0]) ? (int)$parts[0] : date('Y');
    return max(1, ($startYear - $registeredYear) + 1);
}

function apply_academic_year_progression(mysqli $conn, array $setting, bool $force = false): int
{
    ensure_academic_year_settings_table($conn);

    $today = date('Y-m-d');
    if (!$force && $today < (string)($setting['end_date'] ?? '')) {
        return 0;
    }

    $label = trim((string)($setting['academic_year_label'] ?? ''));
    if ($label === '') {
        $label = date('Y') . '/' . (date('Y') + 1);
    }

    $stmt = $conn->prepare("SELECT id, year_joined, year_of_study FROM students WHERE year_joined IS NOT NULL AND year_joined != ''");
    if (!$stmt) {
        throw new Exception('Unable to load students for academic year progression: ' . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $updatedCount = 0;
    while ($student = $result->fetch_assoc()) {
        $registeredYear = (int)($student['year_joined'] ?? 0);
        $expectedYear = get_expected_year_of_study($registeredYear, $label);
        $currentYear = max(1, (int)($student['year_of_study'] ?? 0));

        if ($expectedYear > $currentYear) {
            $update = $conn->prepare("UPDATE students SET year_of_study = ? WHERE id = ?");
            $update->bind_param('ii', $expectedYear, $student['id']);
            $update->execute();
            $update->close();
            $updatedCount++;
        }
    }
    $stmt->close();

    return $updatedCount;
}

$academic_year_message = '';
$academic_year_error = '';
$academic_year_setting = get_academic_year_settings($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['submit_action'] ?? ($_POST['action'] ?? '');
    if ($action === 'save_academic_year_settings') {
        try {
            $saved = save_academic_year_settings($conn, $_POST);
            $academic_year_setting = [
                'id' => 0,
                'academic_year_label' => $saved['academic_year_label'],
                'start_date' => $saved['start_date'],
                'end_date' => $saved['end_date'],
                'is_active' => 1,
            ];
            $academic_year_message = 'Academic year settings saved successfully.';
        } catch (Throwable $e) {
            $academic_year_error = $e->getMessage();
        }
    } elseif ($action === 'run_academic_year_progression') {
        try {
            $updatedCount = apply_academic_year_progression($conn, $academic_year_setting, true);
            $academic_year_message = $updatedCount > 0
                ? "Student year progression applied to $updatedCount student(s)."
                : 'No student year changes were needed.';
        } catch (Throwable $e) {
            $academic_year_error = $e->getMessage();
        }
    }
}

if (($academic_year_setting['end_date'] ?? '') && date('Y-m-d') >= (string)$academic_year_setting['end_date']) {
    try {
        $autoPromoted = apply_academic_year_progression($conn, $academic_year_setting);
        if ($autoPromoted > 0) {
            $academic_year_message = trim(($academic_year_message === '' ? '' : $academic_year_message . ' ') . "Auto-promoted $autoPromoted student(s) to the next study year.");
        }
    } catch (Throwable $e) {
        error_log('Academic year progression failed: ' . $e->getMessage());
    }
}

$supervisedTeams = [];
// Check if team tables exist before querying
$teamTablesExist = false;
try {
    $checkTables = $conn->query("SHOW TABLES LIKE 'team_supervisors'");
    if ($checkTables && $checkTables->num_rows > 0) {
        $teamTablesExist = true;
    }
} catch (Exception $e) {
    // Tables don't exist, skip query
}

if ($teamTablesExist) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                t.id as team_id,
                t.title as team_title,
                u.code as unit_code,
                u.name as unit_name,
                t.status,
                COUNT(DISTINCT tm.student_id) as member_count
            FROM team_supervisors tsup
            JOIN teams t ON tsup.team_id = t.id
            JOIN units u ON t.unit_id = u.id
            LEFT JOIN team_members tm ON t.id = tm.team_id
            WHERE tsup.lecturer_id = ?
              AND tsup.supervisor_type = 'admin'
              AND tsup.status = 'approved'
            GROUP BY t.id, t.title, u.code, u.name, t.status
            ORDER BY t.created_at DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $supervisedTeams[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log('Error fetching supervised teams for admin: ' . $e->getMessage());
    }
}
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
        .supervised-section { margin: 24px 0; padding: 18px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
        .supervised-section h3 { margin-bottom: 12px; color: #374151; }
        .supervised-list { display: grid; gap: 10px; }
        .supervised-card { padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; }
        .supervised-card a { color: #0369a1; font-weight: 600; text-decoration: none; }
        .supervised-meta { color: #6b7280; font-size: 13px; margin-top: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; color: #fff; margin-right: 8px; }
        .badge.active { background: #10b981; }
        .badge.locked { background: #f59e0b; }
        .badge.archived { background: #6b7280; }
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
<body data-theme="light">
    <!-- Global Theme Manager -->
    <script src="../assets/js/theme-manager.js"></script>

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
    <div class="menu-section-title">Phase 1 - Academic Foundation</div>
    <button class="menu-item" onclick="window.location.href='../phase1/admin/upgrade_manager.php'"><i class="fas fa-database"></i> System Upgrade Manager</button>
    <button class="menu-item" onclick="openAddAdminModal()"><i class="fas fa-user-tie"></i> Department Admins</button>
    <button class="menu-item" onclick="openAddTechnicianModal()"><i class="fas fa-tools"></i> Technicians</button>
    <div class="menu-section-title">Public Learning</div>
    <button class="menu-item" onclick="window.location.href='../learn/'"><i class="fas fa-graduation-cap"></i> Public Courses</button>
    <div class="menu-section-title">System</div>
    <button class="menu-item" onclick="alert('System Settings not implemented yet!')"><i class="fas fa-cogs"></i> System Settings</button>
    <a href="../logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Overlay for Off-Canvas Menu -->
<div class="overlay" id="menuOverlay"></div>

<!-- Main Content Area -->
<div class="content">
    <?php if (!empty($admin_success)): ?>
        <div class="alert alert-success" style="background:#dcfce7; color:#166534; padding:12px 16px; border-radius:6px; margin-bottom:16px; border:1px solid #86efac;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($admin_success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($admin_error)): ?>
        <div class="alert alert-error" style="background:#fee2e2; color:#dc2626; padding:12px 16px; border-radius:6px; margin-bottom:16px; border:1px solid #fca5a5;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($admin_error) ?>
        </div>
    <?php endif; ?>
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
        <h3>Academic Year Settings</h3>
        <?php if ($academic_year_message !== ''): ?>
            <div class="success"><?= htmlspecialchars($academic_year_message) ?></div>
        <?php endif; ?>
        <?php if ($academic_year_error !== ''): ?>
            <div class="error"><?= htmlspecialchars($academic_year_error) ?></div>
        <?php endif; ?>
        <form method="POST" style="display:grid; gap:12px; margin-bottom:18px;">
            <input type="hidden" name="action" value="save_academic_year_settings">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
                <div>
                    <label for="academicYearLabel" style="display:block; margin-bottom:6px; font-weight:600;">Academic Year</label>
                    <input id="academicYearLabel" name="academic_year_label" type="text" value="<?= htmlspecialchars($academic_year_setting['academic_year_label'] ?? '') ?>" placeholder="2025/2026" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:8px;">
                </div>
                <div>
                    <label for="academicYearStart" style="display:block; margin-bottom:6px; font-weight:600;">Start Date</label>
                    <input id="academicYearStart" name="start_date" type="date" value="<?= htmlspecialchars($academic_year_setting['start_date'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:8px;">
                </div>
                <div>
                    <label for="academicYearEnd" style="display:block; margin-bottom:6px; font-weight:600;">End Date</label>
                    <input id="academicYearEnd" name="end_date" type="date" value="<?= htmlspecialchars($academic_year_setting['end_date'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:8px;">
                </div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary" name="submit_action" value="save_academic_year_settings">Save Academic Year</button>
                <button type="submit" class="btn btn-success" name="submit_action" value="run_academic_year_progression">Run Year Progression</button>
            </div>
        </form>
        <p style="margin:0 0 12px 0; color:#666;">This setting is used to determine the current academic year and to promote students based on the year they registered.</p>
        <div class="student-count-text">Current setting: <strong><?= htmlspecialchars($academic_year_setting['academic_year_label'] ?? 'Not set') ?></strong> (<?= htmlspecialchars($academic_year_setting['start_date'] ?? '—') ?> to <?= htmlspecialchars($academic_year_setting['end_date'] ?? '—') ?>)</div>
    </div>

    <?php
    // One-off schema scripts living in the application root. Each one gates itself
    // on the admin role, so this panel is only a launcher — it grants no access
    // that visiting the script directly would not. The list empties itself as
    // scripts are deleted after being applied.
    $migrationScripts = glob(dirname(__DIR__) . '/migrate_*.php') ?: [];
    // Also include Phase 1 migration scripts - preserve relative path
    $phase1Migrations = glob(dirname(__DIR__) . '/phase1/database/*.php') ?: [];
    
    // Convert root-level migrations to relative paths (just filename)
    $migrationScripts = array_map(function($path) {
        return basename($path);
    }, $migrationScripts);
    
    // Phase 1 migrations need their subdirectory path preserved
    $phase1Migrations = array_map(function($path) {
        return 'phase1/database/' . basename($path);
    }, $phase1Migrations);
    
    $migrationScripts = array_merge($migrationScripts, $phase1Migrations);
    sort($migrationScripts);
    ?>
    <div class="registration-stats-section">
        <h3><i class="fas fa-database" style="color:#8e44ad;"></i> Database Migrations</h3>
        <p style="margin:0 0 14px 0; color:#666;">
            One-off schema scripts awaiting a run. Each is safe to run more than once and
            reports what it changed. Delete a script from the repository once it has been applied.
        </p>

        <div style="border:1px solid #e1e4e8; border-radius:8px; padding:12px; margin-bottom: 20px; background-color: #f0f4f8;">
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <strong style="flex:1; min-width:240px;">Run all migrations at once</strong>
                <button type="button" class="btn btn-primary" id="run-all-migrations-btn" onclick="runAllMigrations(this)">
                    <i class="fas fa-cogs"></i> Run All Migrations
                </button>
            </div>
            <pre id="all-migrations-output" style="display:none; margin:10px 0 0 0; padding:10px; background:#1e1e1e; color:#e6e6e6; border-radius:6px; overflow:auto; max-height:480px; white-space:pre-wrap; font-size:0.82rem;"></pre>
        </div>

        <p style="margin:14px 0; color:#666;">Or run scripts individually:</p>

        <?php if (empty($migrationScripts)): ?>
            <div class="empty-state">No migration scripts are pending.</div>
        <?php else: ?>
            <div style="display:grid; gap:10px;">
                <?php foreach ($migrationScripts as $path):
                    $scriptName = $path; // Use full relative path, not just basename
                    // Build absolute path for filemtime
                    if (strpos($path, 'phase1/') === 0) {
                        $absolutePath = dirname(__DIR__) . '/' . $path;
                    } else {
                        $absolutePath = dirname(__DIR__) . '/' . $path;
                    }
                    $outputId = 'migration-output-' . md5($scriptName);
                    ?>
                    <div style="border:1px solid #e1e4e8; border-radius:8px; padding:12px;">
                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                            <code style="flex:1; min-width:240px; word-break:break-all;"><?= htmlspecialchars($scriptName) ?></code>
                            <span style="color:#888; font-size:0.85rem;">added <?= file_exists($absolutePath) ? date('Y-m-d H:i', filemtime($absolutePath)) : 'unknown' ?></span>
                            <button type="button" class="btn btn-success"
                                    onclick="runMigration('<?= htmlspecialchars($scriptName, ENT_QUOTES) ?>', '<?= $outputId ?>', this)">
                                <i class="fas fa-play"></i> Run
                            </button>
                        </div>
                        <pre id="<?= $outputId ?>" style="display:none; margin:10px 0 0 0; padding:10px; background:#1e1e1e; color:#e6e6e6; border-radius:6px; overflow:auto; max-height:320px; white-space:pre-wrap; font-size:0.82rem;"></pre>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
                            <th>Time Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="registrationStudentsBody"></tbody>
                </table>
            </div>
            <div id="registrationStudentCount" class="student-count-text"></div>
        </div>
    </div>

    <div class="supervised-section">
        <h3><i class="fas fa-users"></i> Supervised Teams</h3>
        <?php if (empty($supervisedTeams)): ?>
            <p style="color:#6b7280;">You are not currently supervising any teams.</p>
        <?php else: ?>
            <div class="supervised-list">
                <?php foreach ($supervisedTeams as $team): ?>
                    <div class="supervised-card">
                        <a href="../teams/views/manage_team.php?team_id=<?= (int)$team['team_id'] ?>">
                            <?= htmlspecialchars($team['team_title']) ?>
                        </a>
                        <div class="supervised-meta">
                            <span class="badge <?= htmlspecialchars($team['status']) ?>"><?= ucfirst(htmlspecialchars($team['status'])) ?></span>
                            <?= htmlspecialchars($team['unit_code']) ?> · <?= htmlspecialchars($team['unit_name']) ?> · <?= (int)$team['member_count'] ?> member<?= (int)$team['member_count'] === 1 ? '' : 's' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

    <!-- ===================== ADD DEPARTMENT ADMIN MODAL ===================== -->
    <div id="addAdminModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addAdminModal')">×</span>
            <h3><i class="fas fa-user-tie" style="color:#1e3a8a;"></i> Add Department Admin</h3>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_department_admin">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label>Staff ID</label>
                    <input type="text" name="staff_id">
                </div>
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department_id" required>
                        <option value="">-- Select Department --</option>
                        <?php
                        $dept_query = $conn->query("SELECT id, name FROM departments ORDER BY name");
                        while ($dept = $dept_query->fetch_assoc()) {
                            echo '<option value="' . $dept['id'] . '">' . htmlspecialchars($dept['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:16px;">Create Department Admin</button>
            </form>
        </div>
    </div>

    <!-- ===================== ADD TECHNICIAN MODAL ===================== -->
    <div id="addTechnicianModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addTechnicianModal')">×</span>
            <h3><i class="fas fa-tools" style="color:#1e3a8a;"></i> Add Technician</h3>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_technician">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label>Staff ID *</label>
                    <input type="text" name="staff_id" required>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id">
                        <option value="">-- Select Department --</option>
                        <?php
                        $dept_query = $conn->query("SELECT id, name FROM departments ORDER BY name");
                        while ($dept = $dept_query->fetch_assoc()) {
                            echo '<option value="' . $dept['id'] . '">' . htmlspecialchars($dept['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization">
                </div>
                <div class="form-group">
                    <label>Qualification</label>
                    <input type="text" name="qualification">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:16px;">Create Technician</button>
            </form>
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
   Database migrations
───────────────────────────────────────── */
async function runMigration(scriptName, outputId, button) {
    if (!confirm('Run ' + scriptName + '?\n\nThis alters the database schema.')) return;

    const output = document.getElementById(outputId);
    const originalLabel = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running';
    output.style.display = 'block';
    output.textContent = 'Running ' + scriptName + ' ...';

    try {
        // The script reports plain text and gates itself on the admin session, so the
        // cookie has to travel with the request.
        const res = await fetch('../' + scriptName, {
            credentials: 'same-origin',
            headers: { 'Accept': 'text/plain' },
            cache: 'no-store'
        });
        const body = (await res.text()).trim();
        output.textContent = res.ok ? (body || '(no output)') : ('HTTP ' + res.status + '\n\n' + body);
    } catch (err) {
        output.textContent = 'Request failed: ' + err.message;
    } finally {
        button.disabled = false;
        button.innerHTML = originalLabel;
    }
}

async function runAllMigrations(button) {
    if (!confirm('Run all migrations now?\n\nThis will alter the database schema and cannot be undone.')) return;

    const output = document.getElementById('all-migrations-output');
    const originalLabel = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running All...';
    output.style.display = 'block';
    output.textContent = 'Starting all migrations...';

    try {
        const res = await fetch('run_all_migrations.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'text/html' },
            cache: 'no-store'
        });
        const body = (await res.text()).trim();
        output.innerHTML = body || '(no output)';
    } catch (err) {
        output.textContent = 'Request failed: ' + err.message;
    } finally {
        button.disabled = false;
        button.innerHTML = originalLabel;
    }
}

/* ─────────────────────────────────────────
   Modal open/close
───────────────────────────────────────── */
function openModal(id) { const el = document.getElementById(id); if (el) el.style.display = 'block'; }
function closeModal(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
function openAddAdminModal() { openModal('addAdminModal'); }
function openAddTechnicianModal() { openModal('addTechnicianModal'); }
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
                if (tbody) tbody.innerHTML = `<tr><td colspan="7">${escapeHtml(data?.message || 'Unable to load students.')}</td></tr>`;
                return;
            }
            renderRegistrationStudents(data.students, year);
            if (pdfBtn) {
                pdfBtn.disabled = false;
            }
        })
        .catch(() => {
            if (tbody) tbody.innerHTML = '<tr><td colspan="7">Error loading students.</td></tr>';
        });
}

function renderRegistrationStudents(students, year) {
    const tbody = document.getElementById('registrationStudentsBody');
    const countText = document.getElementById('registrationStudentCount');
    const view = document.getElementById('registrationStudentsView');

    if (!tbody || !countText || !view) return;
    if (!students || !students.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="padding:18px;text-align:center;color:#666;">No students registered for this year.</td></tr>';
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
                <td>${escapeHtml(s.registered_at || 'N/A')}</td>
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
        if (!data || data.status !== 'success') {
            const msg = data?.message || 'Failed to load students.';
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:red;">${escapeHtml(msg)}</td></tr>`;
            return;
        }
        if (!Array.isArray(data.students)) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;">No students found.</td></tr>';
            return;
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
        const verifyBtn = verified
            ? '<button class="btn-delete-single" disabled style="opacity:.55;cursor:not-allowed;background:#adb5bd;"><i class="fas fa-check-circle"></i> Verified</button>'
            : `<button class="btn-delete-single" style="background:#28a745;" onclick="verifyStudent(${s.id}, '${escapeJs(s.name)}')"><i class="fas fa-user-check"></i> Verify</button>`;
        tr.innerHTML = `
            <td><input type="checkbox" class="student-checkbox" value="${s.id}" onchange="updateBulkDeleteBtn()"></td>
            <td>${escapeHtml(s.reg_no || '—')}</td>
            <td>${escapeHtml(s.name)}</td>
            <td>${escapeHtml(s.email)}</td>
            <td>Year ${escapeHtml(s.year_of_study || '—')}</td>
            <td><span class="${verified ? 'badge-verified' : 'badge-unverified'}">${verified ? '✔ Verified' : '✘ Unverified'}</span></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                ${verifyBtn}
                <button class="btn-delete-single" onclick="promptDeleteStudent(${s.id}, '${escapeJs(s.name)}')"><i class="fas fa-trash"></i> Delete</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    updateBulkDeleteBtn();
}

function verifyStudent(id, name) {
    if (!id) return;
    if (!confirm(`Verify "${name}" now?`)) return;

    fetch('../actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=verify_student_by_id&student_id=${encodeURIComponent(id)}`
    })
    .then(r => r.text())
    .then(text => {
        let data; try { data = parseJSONSafe(text); } catch(e) { showFloatingMessage('Invalid response', 'error'); return; }
        if (data?.status === 'success') {
            showFloatingMessage(data.message || 'Student verified successfully.', 'success');
            loadStudents();
        } else {
            showFloatingMessage(data?.message || 'Failed to verify student.', 'error');
        }
    })
    .catch(() => showFloatingMessage('Error verifying student.', 'error'));
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

<!-- ═══════════════════════════════════════════════════════════
     LIVE ENGAGEMENT DIAGNOSTIC PANEL

     Moved here from the public landing page (index.html). It probes the
     module's database, API endpoints and config files, and reports which
     internal paths exist - a map of the installation that no logged-out
     visitor should have been able to draw. This page is admin-gated at the
     top, so the panel is now behind the same check as everything else here.

     Paths are built from window.location.origin, so it works from any
     directory depth without adjustment.
     ═══════════════════════════════════════════════════════════ -->
<div id="le-diagnostic" style="
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 99999;
  background: #0f2415; color: #e0f0e0; font-family: 'Inter', 'Segoe UI', sans-serif;
  font-size: 13px; line-height: 1.5; border-top: 2px solid #F9A825;
  max-height: 60vh; overflow-y: auto; display: none;
">
  <!-- Toggle button -->
  <div style="position:sticky;top:0;z-index:1;display:flex;align-items:center;justify-content:space-between;padding:8px 16px;background:#1a3a1e;border-bottom:1px solid #2e7d32;">
    <span style="font-weight:700;color:#F9A825;">🔍 Live Engagement Diagnostics</span>
    <div>
      <button onclick="runLEDiagnostics()" style="background:#2e7d32;color:#fff;border:none;border-radius:6px;padding:4px 14px;cursor:pointer;margin-right:8px;font-size:12px;">Run All</button>
      <button onclick="toggleLeDiag()" style="background:transparent;color:#aaa;border:1px solid #555;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:14px;">✕</button>
    </div>
  </div>
  <div id="le-diag-results" style="padding:12px 16px 16px;">
    <div style="text-align:center;padding:20px;color:#aaa;">Click <strong>"Run All"</strong> to start diagnostics.</div>
  </div>
</div>

<!-- Floating diagnostic launcher -->
<div onclick="toggleLeDiag()" id="le-diag-launcher" style="
  position:fixed;bottom:20px;right:20px;z-index:99998;
  width:44px;height:44px;border-radius:50%;
  background:#1B5E20;color:#F9A825;border:2px solid #F9A825;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:20px;box-shadow:0 4px 16px rgba(0,0,0,.4);
  transition:transform .2s;
" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Live Engagement diagnostics">⚡</div>

<style>
  #le-diagnostic pre {
    background: rgba(0,0,0,.35);
    border-radius: 6px;
    padding: 6px 10px;
    overflow-x: auto;
    font-size: 12px;
    margin: 2px 0;
  }
  #le-diagnostic .ok { color: #4ECBA1; font-weight: 600; }
  #le-diagnostic .err { color: #FCA5A5; font-weight: 600; }
  #le-diagnostic .warn { color: #FFB74D; font-weight: 600; }
  table.le-diag-table { width:100%; border-collapse:collapse; margin:6px 0; }
  table.le-diag-table th, table.le-diag-table td {
    text-align:left; padding:4px 8px; border-bottom:1px solid rgba(255,255,255,.08);
  }
  table.le-diag-table th { color:#F9A825; font-weight:600; font-size:11px; text-transform:uppercase; }
  /* The spinner markup below has always asked for this animation, but it was
     never defined on the landing page, so the spinner sat still. */
  @keyframes le-spin { to { transform: rotate(360deg); } }
</style>

<script>
  var LE_DIAG_BASE = window.location.origin + '/modules/live-engagement';

  function toggleLeDiag() {
    var p = document.getElementById('le-diagnostic');
    var l = document.getElementById('le-diag-launcher');
    if (p.style.display === 'none' || !p.style.display) {
      p.style.display = 'block';
      l.style.display = 'none';
    } else {
      p.style.display = 'none';
      l.style.display = 'flex';
    }
  }

  function leDiagLog(html) {
    document.getElementById('le-diag-results').innerHTML += html;
  }

  function leDiagClear() {
    document.getElementById('le-diag-results').innerHTML = '';
  }

  async function runLEDiagnostics() {
    leDiagClear();
    var out = '<div style="margin-bottom:10px;font-size:12px;color:#aaa;">Running diagnostics… <span id="le-diag-spinner" style="display:inline-block;animation:le-spin .7s linear infinite;width:12px;height:12px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;vertical-align:middle;margin-left:6px;"></span></div>';
    leDiagLog(out);

    // 1. Environment
    var baseUrl = LE_DIAG_BASE;
    leDiagLog('<h3 style="color:#F9A825;margin:12px 0 4px;">1. Environment</h3>');
    leDiagLog('<pre>BASE URL: ' + baseUrl + '\nHost    : ' + window.location.host + '\nProtocol: ' + window.location.protocol + '</pre>');

    // 2. Database connection + table check
    leDiagLog('<h3 style="color:#F9A825;margin:12px 0 4px;">2. Database Tables</h3>');
    try {
      var r = await fetch(baseUrl + '/setup_database.php?diag=1&_=' + Date.now(), { method: 'GET', mode: 'cors' });
      if (r.ok) {
        var data = await r.json();
        leDiagLog('<span class="ok">✅ DB reachable</span> — messages: ' + (data.messages || []).length + '<br>');
        if (data.success) {
          leDiagLog('<span class="ok">✅ All tables OK</span><br>');
        }
        if (data.messages && data.messages.length) {
          leDiagLog('<div style="max-height:200px;overflow-y:auto;margin-top:4px;">');
          data.messages.forEach(function(m) { leDiagLog('<pre style="margin:1px 0;font-size:11px;">' + m + '</pre>'); });
          leDiagLog('</div>');
        }
      } else {
        var txt = await r.text();
        leDiagLog('<span class="err">❌ setup_database.php returned HTTP ' + r.status + '</span><pre>' + txt.substring(0, 300) + '</pre>');
      }
    } catch(e) {
      leDiagLog('<span class="err">❌ setup_database.php fetch failed: ' + e.message + '</span><br>');
    }

    // 3. API endpoint tests
    //
    // These are probed with no action parameter, so the healthy answer is a
    // JSON validation error - "you did not say what you wanted" - not a 200.
    // Grading on status alone painted every endpoint red while they were all
    // working correctly, so what is graded here is the shape of the reply:
    // JSON means the endpoint ran and validated its input; HTML means something
    // else answered (an auth redirect, or a PHP error page); 5xx means broken.
    leDiagLog('<h3 style="color:#F9A825;margin:12px 0 4px;">3. API Endpoints</h3>');
    var endpoints = [
      { name: 'session.php',      url: '/api/session.php' },
      { name: 'poll.php',         url: '/api/poll.php' },
      { name: 'quiz.php',         url: '/api/quiz.php' },
      { name: 'activity.php',     url: '/api/activity.php' },
      { name: 'presentation.php', url: '/api/presentation.php' },
      { name: 'guest_auth.php',   url: '/api/guest_auth.php' },
    ];
    // Rows are collected and written in one go: the previous version logged the
    // opening <table> tag on its own, and assigning a half-finished table to
    // innerHTML makes the browser close it immediately, so every row that
    // followed landed outside the table instead of inside it.
    var rows = '';
    for (var i = 0; i < endpoints.length; i++) {
      var ep = endpoints[i];
      try {
        var r2 = await fetch(baseUrl + ep.url + '?_=' + Date.now(), { method: 'GET', mode: 'cors' });
        var txt2 = await r2.text();
        var sta = r2.status;

        var isJson = false;
        try { JSON.parse(txt2); isJson = true; } catch (parseErr) { isJson = false; }

        var verdict;
        if (sta >= 500) {
          verdict = '<td class="err">' + sta + ' Server error</td>';
        } else if (isJson) {
          // 400 here means the endpoint validated a deliberately empty request.
          verdict = '<td class="ok">' + sta + ' Responding</td>';
        } else if (sta === 403 || sta === 401) {
          verdict = '<td class="warn">' + sta + ' Unauthorized</td>';
        } else {
          verdict = '<td class="err">' + sta + ' Not JSON</td>';
        }

        rows += '<tr><td>' + ep.name + '</td>' + verdict
             +  '<td><pre style="font-size:11px;max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
             +  txt2.substring(0, 120) + '</pre></td></tr>';
      } catch(e2) {
        rows += '<tr><td>' + ep.name + '</td><td class="err">❌ Network</td><td>' + e2.message + '</td></tr>';
      }
    }
    leDiagLog('<table class="le-diag-table"><tr><th>Endpoint</th><th>Status</th><th>Response</th></tr>' + rows + '</table>');

    // 4. Config file check
    //
    // This used to report "✅ Accessible" for 200, 401 and 403 alike, which gave
    // a correctly blocked config file and a publicly readable one the same green
    // tick. That is backwards for everything except index.php: for an internal
    // file, being reachable is the fault. Each path now carries what it is meant
    // to be, and is graded against that.
    leDiagLog('<h3 style="color:#F9A825;margin:12px 0 4px;">4. Configuration &amp; exposure</h3>');
    var configFiles = [
      { path: 'config/module.php',           expect: 'protected' },
      { path: 'config/database_helper.php',  expect: 'protected' },
      { path: 'helpers/security_helper.php', expect: 'protected' },
      { path: 'helpers/session_helper.php',  expect: 'protected' },
      { path: 'bootstrap.php',               expect: 'protected' },
      { path: '.htaccess',                   expect: 'protected' },
      // Backups and scratch files carry extensions Apache will not execute, so
      // if they are reachable at all they are handed over as source.
      { path: 'bootstrap.php.bak2',          expect: 'protected' },
      { path: 'models/BaseModel.php.bak',    expect: 'protected' },
      { path: 'debug_prompt.json',           expect: 'protected' },
      { path: 'try.php',                     expect: 'protected' },
      { path: 'index.php',                   expect: 'public' },
    ];
    var configRows = '';
    for (var j = 0; j < configFiles.length; j++) {
      var entry = configFiles[j];
      try {
        var r3 = await fetch(baseUrl + '/' + entry.path + '?_=' + Date.now(), { method: 'GET', mode: 'cors' });
        var blocked = (r3.status === 403 || r3.status === 401);
        var cell;

        if (entry.expect === 'protected') {
          if (blocked) {
            cell = '<td class="ok">🔒 Protected (' + r3.status + ')</td>';
          } else if (r3.status === 404) {
            cell = '<td class="ok">— Not present</td>';
          } else if (r3.ok) {
            var body = await r3.text();
            // Both markers are assembled from two pieces on purpose. This script
            // is embedded in a .php file, so writing either PHP open tag out in
            // one piece - even inside a JavaScript comment, which the PHP lexer
            // never sees as a comment - puts the parser into PHP mode partway
            // through the script and takes the whole page down with a 500.
            var phpOpen = '<' + '?php';
            var shortOpen = '<' + '?=';
            var leaks = body.indexOf(phpOpen) !== -1 || body.indexOf(shortOpen) !== -1;
            cell = '<td class="err">⚠ Publicly reachable (' + r3.status + ', ' + body.length + 'b'
                 + (leaks ? ', leaks PHP source' : '') + ')</td>';
          } else {
            cell = '<td class="warn">⚠ HTTP ' + r3.status + '</td>';
          }
        } else {
          cell = r3.ok
            ? '<td class="ok">✅ Reachable (' + r3.status + ')</td>'
            : '<td class="err">❌ HTTP ' + r3.status + '</td>';
        }

        configRows += '<tr><td>' + entry.path + '</td>' + cell + '</tr>';
      } catch(e3) {
        configRows += '<tr><td>' + entry.path + '</td><td class="err">❌ ' + e3.message + '</td></tr>';
      }
    }
    leDiagLog('<table class="le-diag-table"><tr><th>Path</th><th>Verdict</th></tr>' + configRows + '</table>');

    // 5. Module URL building test
    leDiagLog('<h3 style="color:#F9A825;margin:12px 0 4px;">5. URL Builder Test</h3>');
    leDiagLog('<pre>Trying module URL: ' + baseUrl + '/index.php?page=dashboard</pre>');
    try {
      var r4 = await fetch(baseUrl + '/index.php?page=dashboard&_=' + Date.now(), { method: 'GET', mode: 'cors' });
      if (r4.ok || r4.status === 302 || r4.status === 301) {
        leDiagLog('<span class="ok">✅ index.php?page=dashboard responds (HTTP ' + r4.status + ')</span><br>');
      } else {
        var txt4 = await r4.text();
        leDiagLog('<span class="warn">⚠ index.php responded HTTP ' + r4.status + '</span><pre>' + txt4.substring(0, 200) + '</pre>');
      }
    } catch(e4) {
      leDiagLog('<span class="err">❌ index.php failed: ' + e4.message + '</span><br>');
    }

    leDiagLog('<hr style="border-color:#2e7d32;margin:12px 0 4px;">');
    leDiagLog('<span class="ok">✅ Diagnostics complete.</span><br>');

    document.getElementById('le-diag-spinner').style.display = 'none';
  }
</script>
<script>
    function runAllMigrations(btn) {
        var output = document.getElementById('all-migrations-output');
        output.style.display = 'block';
        output.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running all migrations... This may take a moment.';
        btn.disabled = true;

        fetch('run_all_migrations.php')
            .then(response => response.text())
            .then(text => {
                output.innerHTML = text;
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-secondary');
                btn.innerHTML = '<i class="fas fa-check"></i> All Migrations Ran';
            })
            .catch(error => {
                output.innerHTML = '<strong>Error:</strong> ' + error;
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-danger');
                btn.innerHTML = '<i class="fas fa-times"></i> Error';
            });
    }
</script>
</body>
</html>