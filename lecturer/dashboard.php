<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';

/* ================= FETCH UNITS ================= */
$units = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name
    FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE lu.lecturer_id = ?
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
$stmt->close();

/* ================= FETCH NOTES ================= */
$notesByUnit = [];
$stmt = $conn->prepare("
    SELECT n.file_path, n.unit_id, n.uploaded_at
    FROM notes n
    JOIN lecturer_units lu ON lu.unit_id = n.unit_id
    WHERE lu.lecturer_id = ?
    ORDER BY n.uploaded_at DESC
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$res = $stmt->get_result();
while ($note = $res->fetch_assoc()) {
    $notesByUnit[$note['unit_id']][] = $note;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Your CSS -->
    <link rel="stylesheet" href="./css/styles.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="welcome-msg">
        <strong>👋 Welcome back, <?= htmlspecialchars($lecturer_name) ?>!</strong>
    </div>

    <div class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-ellipsis-v"></i>
    </div>

    <div class="nav-icon" id="notifications-icon">
        <i class="fas fa-bell"></i>
    </div>

    <div class="nav-icon" id="profile-icon">
        <i class="fas fa-user"></i>
    </div>
</nav>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
        <h4>Main Navigation</h4>
        <ul>
    <li class="blue active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></li>
    <li class="green">
        <a href="../lecturer/course_builder.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-book"></i><span>Training</span>
        </a>
    </li>
    <li class="orange"><i class="fas fa-file-alt"></i><span>Exams</span></li>
    <li class="golden">
        <a href="../lecturer/lesson_editor.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-chalkboard-teacher"></i><span>Lessons</span>
        </a>
    </li>
    <li class="brown"><i class="fas fa-chart-line"></i><span>My Progress</span></li>
    <li class="teal"><i class="fas fa-users"></i><span>Create Team</span></li>
    <li class="purple">
        <a href="../teams/views/lecturer_teams.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-users-cog"></i><span>Lecturer Teams</span>
        </a>
    </li>
    <li class="orange">
        <a href="request_files.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-file-contract"></i><span>📁 Request Files</span>
        </a>
    </li>
</ul>
    </div>

    <div class="sidebar-section">
        <h4>Account</h4>
        <ul>
            <li class="blue"><i class="fas fa-user-circle"></i><span>Account</span></li>
            <li class="green"><i class="fas fa-user"></i><span>Profile</span></li>
            <li class="orange"><i class="fas fa-cog"></i><span>Settings</span></li>
            <li class="brown" onclick="window.location.href='../logout.php'">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </li>
        </ul>
    </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="dashboard-card">

        <nav class="card-navbar">
            <ul>
                <li class="notes active">Notes</li>
                <li class="assignments">Assignments</li>
                <li class="units">Units</li>
                <li class="meetings">Meetings</li>
                <li class="attendance">Attendance</li>
            </ul>
        </nav>

        <!-- NOTES SECTION -->
        <div class="notes-content content-section">
            <div class="notes-box">
                <h3>Welcome, <?= htmlspecialchars($lecturer_name) ?>!</h3>
                <p style="margin: 16px 0; color: #555; line-height: 1.5;">
                    Manage your lecture notes here.<br>
                    View existing materials or upload new ones for your assigned units.
                </p>
                <div style="margin-top: 20px; display: flex; gap: 16px; justify-content: center;">
                    <a href="upload_notes.php" class="btn btn-secondary" id="view-notes-btn">
                        View Interactive Notes
                    </a>
                    <button id="uploadNotesBtn" class="btn btn-green">Upload Notes</button>
                </div>
            </div>

            <div id="notes-tiles" class="hidden">
                <?php if (count($units) > 0): ?>
                    <?php foreach ($units as $unit): ?>
                        <div class="unit-tile" data-unit-id="<?= $unit['id'] ?>">
                            <?= htmlspecialchars($unit['name']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#777; padding:20px;">No units assigned yet.</p>
                <?php endif; ?>
            </div>

            <div id="all-notes-data" style="display:none;">
                <?php foreach ($notesByUnit as $unitId => $notes): ?>
                    <div class="unit-notes-data" data-unit-id="<?= $unitId ?>">
                        <table>
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes as $note):
                                    $file = htmlspecialchars($note['file_path']);
                                    $path = "../assets/uploads/" . $file;
                                ?>
                                <tr>
                                    <td><?= $file ?></td>
                                    <td><?= date("d M Y • h:i A", strtotime($note['uploaded_at'])) ?></td>
                                    <td>
                                        <a href="<?= $path ?>" target="_blank">View</a> |
                                        <a href="<?= $path ?>" download>Download</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ASSIGNMENTS SECTION -->
        <div class="assignments-content content-section" style="display:none;">
            <h3>Assignments</h3>
            <div class="assignments-controls">
                <button id="uploadAssignmentBtn" class="btn-primary">Upload Assignment</button>
                <button id="interactiveAssignmentsBtn" class="btn-secondary">Interactive Assignments</button>
                <button class="btn-secondary" id="viewSubmissionsBtn" onclick="window.location.href='assignment_submissions.php'">
                    View Submissions
                </button>
            </div>

            <div id="interactive-tiles-section" class="hidden">
                <div class="tiles-grid">
                    <a href="create_questions.php" class="tile-card">
                        <div class="icon-wrapper"><i class="fas fa-plus"></i></div>
                        <span>Create Assignment</span>
                    </a>
                    <a href="scores_overview.php" class="tile-card">
                        <div class="icon-wrapper"><i class="fas fa-chart-line"></i></div>
                        <span>View Student Scores</span>
                    </a>
                    <a href="submission_stats.php" class="tile-card">
                        <div class="icon-wrapper"><i class="fas fa-chart-bar"></i></div>
                        <span>Submission Stats</span>
                    </a>
                    <a href="AIGrading.php" class="tile-card">
                        <div class="icon-wrapper"><i class="fas fa-robot"></i></div>
                        <span>AI Grading</span>
                    </a>
                </div>
            </div>

            <div id="assignments-table-section" class="hidden">
                <!-- Your assignments table PHP here -->
            </div>

            <div id="submissions-section" class="hidden">
                <!-- Your submissions table PHP here -->
            </div>
        </div>

        <!-- UNITS SECTION -->
        <div class="units-content content-section" style="display:none;">
            <div class="units-header">
                <h3>My Units</h3>
                <button class="btn-primary" onclick="showModal('addUnitModal')">
                    + Add Unit
                </button>
            </div>

            <div class="units-grid">
                <?php if (count($units) > 0): ?>
                    <?php foreach ($units as $unit): ?>
                        <div class="unit-slim-tile">
                            <div class="unit-name">
                                <?= htmlspecialchars($unit['name']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-units">No units assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- MEETINGS SECTION -->
        <div class="meetings-content content-section" style="display:none;">
            <div class="meetings-header">
                <h3>Office Hours & Meetings</h3>
                <div class="header-actions">
                    <button class="btn-primary" id="scheduleMeetingBtn">
                        <i class="fas fa-plus mr-2"></i> Schedule Meeting
                    </button>
                </div>
            </div>

            <section class="card bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold mb-5 text-gray-800">Upcoming Meetings & Office Hours</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-amber-200 bg-gray-50">
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Title</th>
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Unit</th>
                                <th class="py-3 px-4 text-sm font-semibold text-amber-700 uppercase tracking-wide">Time</th>
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-800">
                            <?php
                            try {
                                $now = date('Y-m-d H:i:s');
                                $stmt = $conn->prepare("
                                    SELECT m.id, m.title, m.scheduled_time, u.name AS unit_name 
                                    FROM meetings m 
                                    JOIN units u ON m.unit_id = u.id 
                                    JOIN lecturer_units lu ON u.id = lu.unit_id
                                    WHERE lu.lecturer_id = ? AND m.scheduled_time >= ?
                                    ORDER BY m.scheduled_time ASC
                                ");
                                $stmt->bind_param("is", $lecturer_id, $now);
                                $stmt->execute();
                                $res = $stmt->get_result();

                                if ($res->num_rows > 0) {
                                    while ($meeting = $res->fetch_assoc()) {
                                        $timeFormatted = date("d M Y • h:i A", strtotime($meeting['scheduled_time']));
                                        echo "<tr class='border-b border-gray-100 hover:bg-amber-50/40 transition-colors'>";
                                        echo "<td class='py-4 px-4 font-medium'>" . htmlspecialchars($meeting['title']) . "</td>";
                                        echo "<td class='py-4 px-4 text-gray-600'>" . htmlspecialchars($meeting['unit_name']) . "</td>";
                                        echo "<td class='py-4 px-4 text-sm text-amber-700 font-medium'>" . $timeFormatted . "</td>";
                                        echo "<td class='py-4 px-4'>";
                                        echo "<a class='text-amber-600 hover:text-amber-800 hover:underline font-medium' ";
                                        echo "href='meeting_ide.php?meeting_id=" . htmlspecialchars($meeting['id']) . "' target='_blank'>Join</a>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='py-10 text-center text-gray-500 italic'>No upcoming meetings or office hours scheduled.</td></tr>";
                                }
                                $stmt->close();
                            } catch (Exception $e) {
                                echo "<tr><td colspan='4' class='py-10 text-center text-red-500 italic'>Error loading meetings: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ATTENDANCE SECTION -->
        <div class="attendance-content content-section" style="display:none;">
            <div class="attendance-box">
                <h3>Attendance Management</h3>
                <p style="margin: 16px 0; color: #555; line-height: 1.5;">
                    Take attendance for your units and track student participation.
                </p>
                <div style="margin-top: 24px;">
                    <button id="openAttendanceModalBtn" class="btn-golden px-6 py-3 text-lg font-medium">
                        Open Attendance Sheet
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Notes Modal -->
<div id="uploadNotesModal" class="modal hidden">
    <div class="modal-content upload-notes-modal">
        <div class="modal-header">
            <div class="modal-header-content">
                <h3 class="modal-title">
                    <i class="fas fa-book-medical"></i>
                    Upload Course Notes
                </h3>
                <p class="modal-subtitle">Share educational materials with your students</p>
            </div>
            <span class="close-btn" id="uploadModalClose">
                <i class="fas fa-times"></i>
            </span>
        </div>

        <!-- Success Message Bar -->
        <div id="uploadSuccessBar" class="upload-success-bar" style="display: none;">
            <div class="success-bar-content">
                <div class="success-bar-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="success-bar-text">
                    <p class="success-title">✓ Files uploaded successfully!</p>
                    <p class="success-files-label">Uploaded Files:</p>
                    <ul id="uploadedFilesList" class="uploaded-files-list"></ul>
                </div>
            </div>
        </div>

        <!-- Upload Form -->
        <form id="uploadNotesForm" enctype="multipart/form-data" class="modal-form">
            <input type="hidden" name="action" value="upload_notes">
            
            <!-- Unit Selection -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-book-open"></i>
                    Select Unit
                    <span class="required">*</span>
                </label>
                <select name="unit_id" required class="form-control unit-select">
                    <option value="">-- Choose a unit to upload to --</option>
                    <?php
                    $lecturer_id = $_SESSION['user_id'] ?? 0;
                    if ($lecturer_id > 0) {
                        $stmt = $conn->prepare("
                            SELECT u.id, u.name, u.code, c.name AS course_name, u.year, u.semester
                            FROM units u
                            JOIN lecturer_units lu ON u.id = lu.unit_id
                            LEFT JOIN courses c ON u.course_id = c.id
                            WHERE lu.lecturer_id = ?
                            ORDER BY c.name, u.year, u.semester, u.name
                        ");
                        $stmt->bind_param("i", $lecturer_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($unit = $result->fetch_assoc()): ?>
                            <option value="<?= $unit['id'] ?>"
                                    data-course="<?= htmlspecialchars($unit['course_name'] ?? '') ?>"
                                    data-code="<?= htmlspecialchars($unit['code'] ?? '') ?>"
                                    data-year="<?= $unit['year'] ?? '' ?>"
                                    data-semester="<?= $unit['semester'] ?? '' ?>">
                                <?= htmlspecialchars($unit['code'] ?? $unit['name']) ?> - <?= htmlspecialchars($unit['name']) ?>
                            </option>
                        <?php endwhile;
                        $stmt->close();
                    }
                    ?>
                </select>
                <small class="form-help">The students enrolled in this unit will receive notifications</small>
            </div>

            <!-- File Upload -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-file-upload"></i>
                    Upload Files
                    <span class="required">*</span>
                </label>
                <div class="file-upload-container">
                    <input type="file" name="notes_file[]" id="notesFileInput" required multiple 
                           accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.xls,.xlsx,.zip" 
                           class="file-input">
                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="file-upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <p class="file-upload-text">
                            <strong>Click to select files or drag & drop</strong>
                        </p>
                        <p class="file-upload-hint">
                            Supported: PDF, Word, PowerPoint, Excel, Text, ZIP (Max 50MB each)
                        </p>
                    </div>
                    <div id="fileList" class="file-list"></div>
                </div>
            </div>

            <!-- File List Preview -->
            <div id="selectedFilesPreview" class="selected-files" style="display: none;">
                <p class="selected-files-label">
                    <i class="fas fa-check-circle"></i>
                    Selected Files:
                </p>
                <ul id="selectedFilesList" class="files-list"></ul>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn-secondary" id="cancelUploadBtn">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-arrow-up"></i>
                    Upload Notes
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.upload-notes-modal {
    max-width: 600px !important;
    max-height: 90vh !important;
    border-radius: 16px !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    background: white !important;
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    position: relative;
    border-bottom: 3px solid #667eea;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.modal-header-content {
    flex: 1;
}

.modal-title {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-title i {
    font-size: 28px;
}

.modal-subtitle {
    font-size: 14px;
    opacity: 0.95;
    margin: 8px 0 0 0;
    color: rgba(255, 255, 255, 0.9);
}

.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: all 0.3s ease;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-form {
    padding: 30px;
    overflow-y: auto;
    flex: 1;
}

.form-group {
    margin-bottom: 24px;
}

.form-group:last-of-type {
    margin-bottom: 0;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 12px;
}

.form-label i {
    color: #667eea;
    font-size: 16px;
}

.required {
    color: #ff4757;
    font-weight: 700;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
    color: #1a1a2e;
    background: white;
    transition: all 0.3s ease;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-control option {
    padding: 10px;
    background: white;
    color: #1a1a2e;
}

.form-help {
    display: block;
    font-size: 12px;
    color: #888;
    margin-top: 8px;
}

.file-upload-container {
    position: relative;
}

.file-input {
    display: none;
}

.file-upload-area {
    border: 2px dashed #667eea;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-upload-area:hover {
    border-color: #764ba2;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.file-upload-area.dragover {
    border-color: #764ba2;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
    transform: scale(1.02);
}

.file-upload-icon {
    font-size: 48px;
    color: #667eea;
    margin-bottom: 12px;
}

.file-upload-text {
    color: #1a1a2e;
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 8px 0;
}

.file-upload-hint {
    color: #888;
    font-size: 13px;
    margin: 0;
}

.selected-files {
    background: #f0f4ff;
    border-left: 4px solid #667eea;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.selected-files-label {
    margin: 0 0 12px 0;
    color: #667eea;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.files-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.file-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    background: white;
    border-radius: 6px;
    margin-bottom: 8px;
    border: 1px solid #e0e0e0;
}

.file-item:last-child {
    margin-bottom: 0;
}

.file-item-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.file-item-icon {
    font-size: 18px;
    color: #667eea;
    flex-shrink: 0;
}

.file-item-name {
    color: #1a1a2e;
    font-size: 13px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-item-remove {
    background: #ff4757;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.file-item-remove:hover {
    background: #ee2d38;
    transform: scale(1.05);
}

.form-actions {
    display: flex;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
    margin-top: 24px;
}

.btn-primary,
.btn-secondary {
    flex: 1;
    padding: 14px 24px;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-secondary {
    background: #f0f0f0;
    color: #1a1a2e;
    border: 1px solid #d0d0d0;
}

.btn-secondary:hover {
    background: #e0e0e0;
    border-color: #c0c0c0;
}

.upload-success-bar {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 20px 30px;
    border-bottom: 3px solid #047857;
    display: none;
}

.success-bar-content {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.success-bar-icon {
    font-size: 32px;
    flex-shrink: 0;
    animation: scaleIn 0.4s ease;
}

.success-bar-text {
    flex: 1;
}

.success-title {
    margin: 0 0 12px 0;
    font-size: 16px;
    font-weight: 700;
}

.success-files-label {
    margin: 8px 0 8px 0;
    font-size: 13px;
    font-weight: 600;
    opacity: 0.95;
}

.uploaded-files-list {
    list-style: none;
    margin: 8px 0 0 0;
    padding: 0;
    max-height: 150px;
    overflow-y: auto;
}

.uploaded-file-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.uploaded-file-item:last-child {
    border-bottom: none;
}

.uploaded-file-item .file-icon {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.9);
}

.uploaded-file-item .file-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@keyframes scaleIn {
    from {
        transform: scale(0.6);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@media (max-width: 600px) {
    .upload-notes-modal {
        max-width: 95vw !important;
        max-height: 95vh !important;
    }

    .modal-header {
        padding: 20px;
    }

    .modal-title {
        font-size: 20px;
    }

    .modal-form {
        padding: 20px;
    }

    .form-actions {
        flex-direction: column;
    }

    .file-upload-area {
        padding: 30px 15px;
    }

    .file-upload-icon {
        font-size: 40px;
    }

    .success-bar-content {
        flex-direction: column;
        gap: 10px;
    }

    .upload-success-bar {
        padding: 15px 20px;
    }
}
</style>

<!-- Assignment Modal -->
<div id="assignmentModal" class="modal hidden">
    <div class="modal-content bg-white rounded-2xl border border-f5e6b2 shadow-2xl" style="max-width: 580px; max-height: 92vh; overflow-y: auto;">
        <span class="close text-92400e text-3xl font-bold cursor-pointer hover:text-f59e0b absolute top-5 right-6 z-10" id="assignmentModalClose">×</span>
        <h3 class="text-2xl font-bold stat-text-secondary mb-8 text-center pt-8">Create Assignment</h3>
        <form action="../actions.php" method="POST">
            <input type="hidden" name="action" value="create_assignment">
            <div class="modal-body">
                <div class="form-group">
                    <label>Unit <span class="text-red-500">*</span></label>
                    <select name="unit_id" required>
                        <option value="">— Select Unit —</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Instructions <span class="text-red-500">*</span></label>
                    <textarea name="instructions" required></textarea>
                </div>
                <div class="form-group">
                    <label>Due Date <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="due_date" required>
                </div>
                <div class="form-group">
                    <label>Attach File (optional):</label>
                    <input type="file" name="assignment_file">
                </div>
                <button type="submit" class="btn-primary">Create Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Unit Modal -->
<div id="addUnitModal" class="modal hidden">
    <div class="modal-content bg-white rounded-2xl border border-f5e6b2 shadow-2xl" style="max-width: 580px; max-height: 92vh; overflow-y: auto;">
        <span class="close text-92400e text-3xl font-bold cursor-pointer hover:text-f59e0b absolute top-5 right-6 z-10" id="addUnitModalClose">×</span>
        <h3 class="text-2xl font-bold stat-text-secondary mb-8 text-center pt-8">Add Unit</h3>
        <form action="../actions.php" method="POST">
            <input type="hidden" name="action" value="add_unit">
            <div class="modal-body">
                <div class="form-group">
                    <label>Unit Name <span class="text-red-500">*</span></label>
                    <input type="text" name="unit_name" required>
                </div>
                <div class="form-group">
                    <label>Unit Code <span class="text-red-500">*</span></label>
                    <input type="text" name="unit_code" required>
                </div>
                <div class="form-group">
                    <label>Course</label>
                    <select name="unit_id" id="unitSelect" required>
                        <option value="">-- Select Unit --</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Add Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- Schedule Meeting Modal -->
<div id="scheduleMeetingModal" class="modal hidden">
    <div class="modal-content bg-white rounded-2xl border border-f5e6b2 shadow-2xl" style="max-width: 580px; max-height: 92vh; overflow-y: auto;">
        <span class="close text-92400e text-3xl font-bold cursor-pointer hover:text-f59e0b absolute top-5 right-6 z-10" id="scheduleMeetingModalClose">×</span>
        <h3 class="text-2xl font-bold stat-text-secondary mb-8 text-center pt-8">Schedule Meeting</h3>
        <form action="../actions.php" method="POST">
            <input type="hidden" name="action" value="schedule_meeting">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Unit <span class="text-red-500">*</span></label>
                    <select name="unit_id" required>
                        <option value="">— Select Unit —</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label>Date & Time <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="scheduled_time" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration" min="15" max="180" value="60">
                    </div>
                </div>
                <div class="form-group">
                    <label>Meeting Link / Location (optional)</label>
                    <input type="url" name="meeting_link" placeholder="https://meet.google.com/...">
                </div>
                <div class="form-group">
                    <label>Notes / Agenda</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="btn-secondary" onclick="hideModal('scheduleMeetingModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Meeting</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Modal -->
<div id="attendanceModal" class="modal hidden">
    <div class="modal-content bg-white rounded-2xl border border-f5e6b2 shadow-2xl" style="max-width: 580px; max-height: 92vh; overflow-y: auto;">
        <span class="close text-92400e text-3xl font-bold cursor-pointer hover:text-f59e0b absolute top-5 right-6 z-10" id="attendanceModalClose">×</span>
        <h3 class="text-2xl font-bold stat-text-secondary mb-8 text-center pt-8">Take Attendance</h3>
        <form id="attendanceForm" method="POST" action="attendance_functions.php" class="px-10 pb-10">
            <div class="mb-6">
                <label class="block text-sm font-medium stat-text-primary mb-3">
                    Select Unit <span class="text-red-500">*</span>
                </label>
                <select name="unit_id" id="modalUnitId" required class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-lg focus:ring-2 focus:ring-f59e0b focus:border-f59e0b transition">
                    <option value="">-- Choose Unit --</option>
                    <?php
                    $lecturer_id = $_SESSION['user_id'] ?? 0;
                    if ($lecturer_id > 0) {
                        $stmt = $conn->prepare("
                            SELECT u.id, u.name, c.name AS course_name, u.year, u.semester
                            FROM units u
                            JOIN lecturer_units lu ON u.id = lu.unit_id
                            LEFT JOIN courses c ON u.course_id = c.id
                            WHERE lu.lecturer_id = ?
                            ORDER BY u.name
                        ");
                        $stmt->bind_param("i", $lecturer_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($unit = $result->fetch_assoc()): ?>
                            <option value="<?= $unit['id'] ?>"
                                    data-course="<?= htmlspecialchars($unit['course_name'] ?? '') ?>"
                                    data-year="<?= $unit['year'] ?? '' ?>"
                                    data-semester="<?= $unit['semester'] ?? '' ?>">
                                <?= htmlspecialchars($unit['name']) ?>
                            </option>
                        <?php endwhile;
                        $stmt->close();
                    }
                    ?>
                </select>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium stat-text-primary mb-3">Session Date</label>
                <input type="date" name="session_date" value="<?= date('Y-m-d') ?>" required class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-lg">
            </div>
            <div class="mb-8">
                <label class="block text-sm font-medium stat-text-primary mb-3">Remarks / Topic Covered (optional)</label>
                <textarea name="remarks" rows="3" class="w-full px-5 py-4 border border-f5e6b2 rounded-xl text-92400e text-lg resize-y"></textarea>
            </div>
            <div class="text-center">
                <button type="submit" class="btn-golden px-10 py-4 text-xl font-semibold">Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<script>
// Single tab switching logic
document.addEventListener('DOMContentLoaded', () => {
    // Tab switching
    document.querySelectorAll('.card-navbar li').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.card-navbar li').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');

            const target = tab.className.split(' ')[0];
            const section = document.querySelector(`.${target}-content`);
            if (section) section.style.display = 'block';
        });
    });

    // Mobile sidebar toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.toggle('show');
    });

    document.addEventListener('click', e => {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });

    // Notes functionality
    const viewBtn = document.getElementById('view-notes-btn');
    const tiles = document.getElementById('notes-tiles');
    const notesModal = document.getElementById('notes-modal');

    viewBtn?.addEventListener('click', () => tiles?.classList.toggle('hidden'));

    document.querySelectorAll('.unit-tile').forEach(tile => {
        tile.addEventListener('click', () => {
            const unitId = tile.dataset.unitId;
            document.getElementById('modal-unit-name').textContent = tile.textContent;
            const data = document.querySelector(`.unit-notes-data[data-unit-id="${unitId}"]`);
            document.getElementById('unit-notes-list').innerHTML = data 
                ? data.innerHTML 
                : '<p style="color:#777;">No notes uploaded yet.</p>';
            notesModal?.classList.remove('hidden');
        });
    });

    notesModal?.querySelector('.close')?.addEventListener('click', () => notesModal.classList.add('hidden'));
    notesModal?.addEventListener('click', e => {
        if (e.target === notesModal) notesModal.classList.add('hidden');
    });

    // Upload notes modal
    const uploadNotesModal = document.getElementById('uploadNotesModal');
    const uploadNotesBtn = document.getElementById('uploadNotesBtn');
    const uploadModalClose = document.getElementById('uploadModalClose');
    const uploadNotesForm = document.getElementById('uploadNotesForm');
    const notesFileInput = document.getElementById('notesFileInput');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const selectedFilesPreview = document.getElementById('selectedFilesPreview');
    const selectedFilesList = document.getElementById('selectedFilesList');
    const cancelUploadBtn = document.getElementById('cancelUploadBtn');

    // Open modal
    uploadNotesBtn?.addEventListener('click', () => {
        uploadNotesModal?.classList.remove('hidden');
    });

    // Close modal
    uploadModalClose?.addEventListener('click', () => {
        uploadNotesModal?.classList.add('hidden');
        resetUploadForm();
    });

    cancelUploadBtn?.addEventListener('click', () => {
        uploadNotesModal?.classList.add('hidden');
        resetUploadForm();
    });

    // Close on background click
    uploadNotesModal?.addEventListener('click', e => {
        if (e.target === uploadNotesModal) {
            uploadNotesModal?.classList.add('hidden');
            resetUploadForm();
        }
    });

    // Drag and drop
    if (fileUploadArea) {
        fileUploadArea.addEventListener('click', () => notesFileInput?.click());

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, () => {
                fileUploadArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, () => {
                fileUploadArea.classList.remove('dragover');
            }, false);
        });

        fileUploadArea.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            const files = dt.files;
            notesFileInput.files = files;
            updateFilePreview();
        }, false);
    }

    // File input change
    notesFileInput?.addEventListener('change', updateFilePreview);

    // Update file preview
    function updateFilePreview() {
        const files = notesFileInput?.files || [];
        if (files.length > 0) {
            selectedFilesList.innerHTML = '';
            let totalSize = 0;

            Array.from(files).forEach((file, index) => {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                totalSize += file.size;

                const fileIcon = getFileIcon(file.name);
                const li = document.createElement('li');
                li.className = 'file-item';
                li.innerHTML = `
                    <div class="file-item-info">
                        <span class="file-item-icon">${fileIcon}</span>
                        <span class="file-item-name" title="${file.name}">${file.name} (${fileSize}MB)</span>
                    </div>
                    <button type="button" class="file-item-remove" onclick="removeFile(${index})">Remove</button>
                `;
                selectedFilesList.appendChild(li);
            });

            selectedFilesPreview.style.display = 'block';
        } else {
            selectedFilesPreview.style.display = 'none';
        }
    }

    // Get file icon based on extension
    window.getFileIcon = function(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            'pdf': '<i class="fas fa-file-pdf"></i>',
            'doc': '<i class="fas fa-file-word"></i>',
            'docx': '<i class="fas fa-file-word"></i>',
            'ppt': '<i class="fas fa-file-powerpoint"></i>',
            'pptx': '<i class="fas fa-file-powerpoint"></i>',
            'xls': '<i class="fas fa-file-excel"></i>',
            'xlsx': '<i class="fas fa-file-excel"></i>',
            'txt': '<i class="fas fa-file-alt"></i>',
            'zip': '<i class="fas fa-file-archive"></i>'
        };
        return icons[ext] || '<i class="fas fa-file"></i>';
    };

    // Remove file
    window.removeFile = function(index) {
        const files = Array.from(notesFileInput.files);
        files.splice(index, 1);
        
        const dataTransfer = new DataTransfer();
        files.forEach(file => dataTransfer.items.add(file));
        notesFileInput.files = dataTransfer.files;
        
        updateFilePreview();
    };

    // Reset form
    function resetUploadForm() {
        uploadNotesForm?.reset();
        selectedFilesPreview.style.display = 'none';
        selectedFilesList.innerHTML = '';
        document.getElementById('uploadSuccessBar').style.display = 'none';
    }

    // Handle form submission via AJAX
    uploadNotesForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const unitId = document.querySelector('select[name="unit_id"]')?.value;
        if (!unitId) {
            alert('Please select a unit');
            return;
        }

        const files = notesFileInput?.files || [];
        if (files.length === 0) {
            alert('Please select files to upload');
            return;
        }

        const formData = new FormData(uploadNotesForm);
        const uploadButton = uploadNotesForm.querySelector('button[type="submit"]');
        const originalText = uploadButton.innerHTML;
        uploadButton.disabled = true;
        uploadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

        try {
            const response = await fetch('../actions.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                // Show success bar with uploaded file names
                const successBar = document.getElementById('uploadSuccessBar');
                const uploadedFilesList = document.getElementById('uploadedFilesList');
                uploadedFilesList.innerHTML = '';

                Array.from(files).forEach(file => {
                    const li = document.createElement('li');
                    li.className = 'uploaded-file-item';
                    const fileIcon = window.getFileIcon(file.name);
                    li.innerHTML = `<span class="file-icon">${fileIcon}</span><span class="file-name">${file.name}</span>`;
                    uploadedFilesList.appendChild(li);
                });

                successBar.style.display = 'block';
                resetUploadForm();

                // Keep modal open to show success message for 3 seconds
                await new Promise(resolve => setTimeout(resolve, 3000));

                // Close modal
                uploadNotesModal?.classList.add('hidden');
            } else {
                const text = await response.text();
                alert('Upload failed: ' + (text || 'Unknown error'));
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Error uploading files: ' + error.message);
        } finally {
            uploadButton.disabled = false;
            uploadButton.innerHTML = originalText;
        }
    });

    // Attendance modal
    document.getElementById('openAttendanceModalBtn')?.addEventListener('click', () => {
        document.getElementById('attendanceModal')?.classList.remove('hidden');
    });
    document.getElementById('attendanceModalClose')?.addEventListener('click', () => {
        document.getElementById('attendanceModal')?.classList.add('hidden');
    });
    document.getElementById('attendanceModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('attendanceModal')) {
            document.getElementById('attendanceModal')?.classList.add('hidden');
        }
    });

    // Generic modal helpers
    window.showModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };

    window.hideModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    };

    // Close modals on outside click or close button
    document.addEventListener('click', e => {
        if (e.target.classList.contains('modal') || e.target.classList.contains('close')) {
            const modal = e.target.closest('.modal');
            if (modal) hideModal(modal.id);
        }
    });

    // ESC key to close modals
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal:not(.hidden)');
            if (openModal) hideModal(openModal.id);
        }
    });
});
</script>

</body>

</html>
