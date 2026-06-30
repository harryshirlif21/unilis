<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config/db.php';
require_once '../includes/notifications.php';
require_once __DIR__ . '/../config/meeting.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';

// Fetch lecturer info for profile popup
$lecturer_info = [];
$stmt = $conn->prepare("SELECT id, name, email FROM lecturers WHERE id = ?");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$lecturer_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get latest 5 notifications for current lecturer
$latest_notifications = get_latest_notifications($conn, 5, $lecturer_id, 'lecturer');

// Get unread count for current lecturer
$unread_count = get_unread_notification_count($conn, $lecturer_id, 'lecturer');

// Handle AJAX mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_notification_read') {
    header('Content-Type: application/json');
    $notif_id = intval($_POST['notification_id']);
    if (mark_notification_as_read($conn, $notif_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

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
    SELECT n.id, n.file_path, n.unit_id, n.uploaded_at, n.status
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

/* ================= FETCH NOTES COUNT PER UNIT ================= */
$notesCountByUnit = [];
$stmt = $conn->prepare("
    SELECT n.unit_id, COUNT(*) AS cnt
    FROM notes n
    JOIN lecturer_units lu ON lu.unit_id = n.unit_id
    WHERE lu.lecturer_id = ? AND n.status = 'active'
    GROUP BY n.unit_id
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $notesCountByUnit[$row['unit_id']] = (int)$row['cnt'];
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
    <style>
        /* ---------- Notes Sent specific styles ---------- */
        .notes-sent-btn {
            cursor: pointer;
            background: #16a34a;
            color: #fff;
            border-radius: 8px;
            padding: 10px 16px;
        }
        .notes-sent-btn:hover {
            background: #15803d;
        }
        .notes-sent-btn.active {
            background: rgba(59, 130, 246, 0.15);
            border-left: 4px solid #3b82f6;
        }
        .notes-sent-tile {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .notes-sent-tile:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59,130,246,0.15);
            transform: translateY(-1px);
        }
        .notes-sent-tile .unit-name {
            font-weight: 600;
            color: #1f2937;
        }
        .notes-sent-tile .notes-badge {
            background: #3b82f6;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            white-space: nowrap;
        }
        .notes-sent-tile .notes-badge.zero {
            background: #9ca3af;
        }
        .notes-sent-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .notes-sent-header h3 {
            margin: 0;
            font-size: 22px;
            color: #111827;
        }
        .notes-sent-header h3 i {
            color: #3b82f6;
            margin-right: 10px;
        }
        .notes-sent-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .no-notes-msg {
            text-align: center;
            color: #9ca3af;
            padding: 40px 20px;
            font-size: 15px;
        }
        /* Notes detail modal (reuse existing) */
        #notesSentModal .modal-content {
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
        }
        #notesSentModal .modal-content table {
            width: 100%;
            border-collapse: collapse;
        }
        #notesSentModal .modal-content th {
            background: #f3f4f6;
            text-align: left;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        #notesSentModal .modal-content td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        #notesSentModal .modal-content tr:hover td {
            background: #f9fafb;
        }
        #notesSentModal .modal-unit-title {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        #notesSentModal .modal-unit-title i {
            color: #3b82f6;
            margin-right: 8px;
        }
        /* ---------- Popup styles (notifications & profile) ---------- */
        .popup {
            position: fixed;
            top: 70px;
            right: 20px;
            width: 360px;
            max-width: calc(100vw - 40px);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            border: 1px solid #e5e7eb;
            z-index: 9999;
            display: none;
            overflow: hidden;
            animation: popupFadeIn 0.2s ease-out;
        }
        @keyframes popupFadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .popup h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 20px;
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        .popup h3 i {
            color: #3b82f6;
            font-size: 18px;
        }
        .popup-content {
            padding: 12px 0;
            max-height: 360px;
            overflow-y: auto;
        }
        .notification-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 20px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background 0.15s;
        }
        .notification-item:hover {
            background: #f9fafb;
        }
        .notification-item.unread {
            background: #eff6ff;
            border-left: 3px solid #3b82f6;
        }
        .notification-item .notif-title {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }
        .notification-item .notif-msg {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.4;
        }
        .notification-item .notif-time {
            font-size: 12px;
            color: #9ca3af;
        }
        .popup-footer {
            padding: 12px 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        .popup-footer a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .popup-footer a:hover {
            text-decoration: underline;
        }
        .profile-popup h3 i {
            color: #8b5cf6;
        }
        .profile-info {
            padding: 16px 20px;
        }
        .profile-info .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .profile-info .p-name {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }
        .profile-info .p-email {
            font-size: 14px;
            color: #6b7280;
            margin-top: 4px;
        }
        .profile-info .p-phone {
            font-size: 14px;
            color: #6b7280;
            margin-top: 2px;
        }
        .profile-actions {
            padding: 0 20px 16px;
            display: flex;
            gap: 10px;
        }
        .profile-actions .btn-profile {
            flex: 1;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .profile-actions .btn-profile.primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .profile-actions .btn-profile.primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .profile-actions .btn-profile.secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .profile-actions .btn-profile.secondary:hover {
            background: #e5e7eb;
        }
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -6px;
            min-width: 18px;
            height: 18px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            padding: 0 4px;
        }
        .meeting-flash {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 500;
        }
        .meeting-flash-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .meeting-flash-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .meeting-link-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 4px;
            flex-wrap: wrap;
        }
        .meeting-link-row label {
            min-width: 90px;
            font-size: 12px;
            font-weight: 700;
            color: inherit;
            opacity: 0.85;
        }
        .meeting-flash-note {
            font-size: 13px;
            opacity: 0.95;
        }
        .meeting-link-row input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        .modal-body .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            box-sizing: border-box;
            font-family: inherit;
        }
        .modal-body .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
    </style>
<style>
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.3); }
    }
    @keyframes pulse-glow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0.4); }
        50% { box-shadow: 0 0 0 10px rgba(245,158,11,0); }
    }
</style>
</head>
<body data-theme="light">
    <!-- Global Theme Manager -->
    <script src="../assets/js/theme-manager.js"></script>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="welcome-msg">
        <strong>👋 Welcome back, <?= htmlspecialchars($lecturer_name) ?>!</strong>
    </div>

    <div class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-ellipsis-v"></i>
    </div>

    <div class="nav-icon" id="notifications-icon" style="position:relative;">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="notification-badge" id="notificationCount"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
        <?php endif; ?>
    </div>

    <div class="nav-icon" id="profile-icon">
        <i class="fas fa-user"></i>
    </div>
</nav>

<!-- NOTIFICATIONS POPUP -->
<div class="popup" id="notifications-popup">
    <h3>
        <i class="fas fa-bell"></i>
        Notifications
    </h3>
    <div class="popup-content" id="notif-list">
        <?php if(empty($latest_notifications)): ?>
            <div style="text-align: center; padding: 2rem; color: #9ca3af;">
                <i class="fas fa-bell-slash" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                No notifications yet
            </div>
        <?php else: ?>
            <?php foreach($latest_notifications as $notif): ?>
                <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" id="quick-notif-<?= $notif['id'] ?>" onclick="quickMarkRead(<?= $notif['id'] ?>)">
                    <span class="notif-title"><?= htmlspecialchars($notif['title']) ?></span>
                    <span class="notif-msg"><?= htmlspecialchars(substr($notif['message'], 0, 100)) ?><?= strlen($notif['message']) > 100 ? '...' : '' ?></span>
                    <span class="notif-time">
                        <?php
                            $time = strtotime($notif['created_at']);
                            $now  = time();
                            $diff = $now - $time;
                            if ($diff < 60)        echo "Just now";
                            elseif ($diff < 3600)  echo floor($diff / 60) . "m ago";
                            elseif ($diff < 86400) echo floor($diff / 3600) . "h ago";
                            else                   echo date('M d', $time);
                        ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="popup-footer">
        <a href="notifications.php">View All Notifications <i class="fas fa-arrow-right"></i></a>
    </div>
</div>

<!-- PROFILE POPUP -->
<div class="popup profile-popup" id="profile-popup">
    <h3>
        <i class="fas fa-user-circle"></i>
        Profile
    </h3>
    <div class="profile-info">
        <div class="avatar">
            <?= strtoupper(substr(htmlspecialchars($lecturer_info['name'] ?? $lecturer_name), 0, 2)) ?>
        </div>
        <div class="p-name"><?= htmlspecialchars($lecturer_info['name'] ?? $lecturer_name) ?></div>
        <div class="p-email"><i class="fas fa-envelope" style="margin-right:6px;color:#9ca3af;"></i><?= htmlspecialchars($lecturer_info['email'] ?? '') ?></div>
        <?php if (!empty($lecturer_info['phone'])): ?>
            <div class="p-phone"><i class="fas fa-phone" style="margin-right:6px;color:#9ca3af;"></i><?= htmlspecialchars($lecturer_info['phone']) ?></div>
        <?php endif; ?>
    </div>
    <div class="profile-actions">
        <a href="profile.php" class="btn-profile primary">
            <i class="fas fa-user-edit"></i> View Profile
        </a>
        <a href="../logout.php" class="btn-profile secondary">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

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
        <a href="lesson_editor.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-chalkboard-teacher"></i><span>Lessons</span>
        </a>
    </li>
    <li class="blue">
        <a href="assignment_submissions.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-inbox"></i><span>Assignment Submissions</span>
        </a>
    </li>
    <li class="brown">
        <a href="submissions.php" style="color:inherit;text-decoration:none;">
            <i class="fas fa-chart-line"></i><span>Assessment Submissions</span>
        </a>
    </li>
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
    <!-- NEW: Notes Sent sidebar item -->
    <li class="notes-sent-btn" id="sidebarNotesSent">
        <i class="fas fa-paper-plane" style="color:#6366f1;"></i><span>Notes Sent</span>
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
                                    <th>Status</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes as $note):
                                    $file = htmlspecialchars($note['file_path']);
                                    $path = "../assets/uploads/" . $file;
                                    $status = $note['status'] ?? 'active';
                                    $statusLabel = $status === 'hidden' ? '🔶 Hidden' : ($status === 'deleted' ? '🗑️ Deleted' : '✅ Active');
                                ?>
                                <tr data-note-id="<?= $note['id'] ?>" data-note-status="<?= $status ?>">
                                    <td><?= $file ?></td>
                                    <td><?= $statusLabel ?></td>
                                    <td><?= date("d M Y • h:i A", strtotime($note['uploaded_at'])) ?></td>
                                    <td>
                                        <a href="<?= $path ?>" target="_blank" class="action-view" <?= $status === 'deleted' ? 'style="pointer-events:none;opacity:0.4;"' : '' ?>>View</a> |
                                        <a href="<?= $path ?>" download class="action-download" <?= $status === 'deleted' ? 'style="pointer-events:none;opacity:0.4;"' : '' ?>>Download</a> |
                                        <span class="action-btn-group" style="white-space:nowrap;">
                                            <?php if ($status === 'active'): ?>
                                                <a href="#" class="hide-note-btn" data-note-id="<?= $note['id'] ?>" style="color:#f59e0b;">Hide</a> |
                                                <a href="#" class="delete-note-btn" data-note-id="<?= $note['id'] ?>" style="color:#ef4444;">Delete</a>
                                            <?php elseif ($status === 'hidden'): ?>
                                                <a href="#" class="unhide-note-btn" data-note-id="<?= $note['id'] ?>" style="color:#10b981;">Unhide</a> |
                                                <a href="#" class="delete-note-btn" data-note-id="<?= $note['id'] ?>" style="color:#ef4444;">Delete</a>
                                            <?php elseif ($status === 'deleted'): ?>
                                                <span style="color:#9ca3af;font-size:12px;">Covered</span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==================== NOTES SENT SECTION ==================== -->
        <div class="notes-sent-content content-section" style="display:none;">
            <div class="notes-sent-header">
                <h3><i class="fas fa-paper-plane"></i> Notes Sent</h3>
            </div>

            <?php if (count($units) > 0): ?>
                <div class="notes-sent-grid" id="notesSentGrid">
                    <?php foreach ($units as $unit):
                        $cnt = $notesCountByUnit[$unit['id']] ?? 0;
                    ?>
                        <div class="notes-sent-tile" data-unit-id="<?= $unit['id'] ?>" data-unit-name="<?= htmlspecialchars($unit['name']) ?>">
                            <span class="unit-name">
                                <i class="fas fa-book" style="color:#6366f1;margin-right:10px;"></i>
                                <?= htmlspecialchars($unit['name']) ?>
                            </span>
                            <span class="notes-badge <?= $cnt === 0 ? 'zero' : '' ?>">
                                <?= $cnt ?> note<?= $cnt !== 1 ? 's' : '' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-notes-msg">
                    <i class="fas fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;color:#d1d5db;"></i>
                    No units assigned yet.
                </div>
            <?php endif; ?>
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
                    <button type="button" class="btn-primary" id="scheduleMeetingBtn">
                        <i class="fas fa-plus mr-2"></i> Schedule Meeting
                    </button>
                </div>
            </div>

            <?php if (!empty($_SESSION['meeting_success'])): ?>
                <div class="meeting-flash meeting-flash-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['meeting_success']) ?>
                    <?php if (!empty($_SESSION['meeting_unit_name'])): ?>
                        <div class="meeting-flash-note">
                            Students enrolled in <strong><?= htmlspecialchars($_SESSION['meeting_unit_name']) ?></strong> will see this on their dashboard.
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['meeting_link'])): ?>
                        <div class="meeting-link-row">
                            <label>Host link</label>
                            <input type="text" id="scheduledMeetingLink" readonly
                                   value="<?= htmlspecialchars($_SESSION['meeting_link']) ?>">
                            <button type="button" class="btn-secondary copy-link-btn" data-target="scheduledMeetingLink">Copy</button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['meeting_student_link'])): ?>
                        <div class="meeting-link-row">
                            <label>Student link</label>
                            <input type="text" id="scheduledStudentMeetingLink" readonly
                                   value="<?= htmlspecialchars($_SESSION['meeting_student_link']) ?>">
                            <button type="button" class="btn-secondary copy-link-btn" data-target="scheduledStudentMeetingLink">Copy</button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php unset($_SESSION['meeting_success'], $_SESSION['meeting_link'], $_SESSION['meeting_student_link'], $_SESSION['meeting_unit_name']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['meeting_error'])): ?>
                <div class="meeting-flash meeting-flash-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['meeting_error']) ?>
                </div>
                <?php unset($_SESSION['meeting_error']); ?>
            <?php endif; ?>

            <section class="card bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold mb-5 text-gray-800">Upcoming Meetings & Office Hours</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-amber-200 bg-gray-50">
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Title</th>
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Unit</th>
                                <th class="py-3 px-4 text-sm font-semibold text-amber-700 uppercase tracking-wide">Time</th>
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Student Link</th>
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-800">
                            <?php
                            try {
                                $now = date('Y-m-d H:i:s');
                                $stmt = $conn->prepare("
                                    SELECT m.id, m.title, m.scheduled_time, m.duration, u.id AS unit_id, u.name AS unit_name 
                                    FROM meetings m 
                                    JOIN units u ON m.unit_id = u.id 
                                    JOIN lecturer_units lu ON u.id = lu.unit_id
                                    WHERE lu.lecturer_id = ? AND DATE_ADD(m.scheduled_time, INTERVAL m.duration MINUTE) >= ?
                                    ORDER BY u.name ASC, m.scheduled_time ASC
                                ");
                                $stmt->bind_param("is", $lecturer_id, $now);
                                $stmt->execute();
                                $res = $stmt->get_result();

                                if ($res->num_rows > 0) {
                                    while ($meeting = $res->fetch_assoc()) {
                                        $timeFormatted = date("d M Y • h:i A", strtotime($meeting['scheduled_time']));
                                        $studentJoinUrl = getMeetingStudentJoinUrl((int)$meeting['id']);
                                        $meetingId = (int)$meeting['id'];
                                        $inputId = 'studentMeetingLink' . $meetingId;

                                        // Determine if meeting is within its scheduled time window
                                        $startTs = strtotime($meeting['scheduled_time']);
                                        $endTs = $startTs + ((int)$meeting['duration'] * 60);
                                        $nowTs = time();
                                        $isWithinTime = ($nowTs >= $startTs && $nowTs <= $endTs);

                                        // Check if meeting has meeting_status column and if it's active
                                        $isActive = false;
                                        $statusCheck = $conn->query("SHOW COLUMNS FROM meetings LIKE 'meeting_status'");
                                        if ($statusCheck && $statusCheck->num_rows > 0) {
                                            $stmtStatus = $conn->prepare("SELECT meeting_status FROM meetings WHERE id = ?");
                                            $stmtStatus->bind_param("i", $meetingId);
                                            $stmtStatus->execute();
                                            $statusRow = $stmtStatus->get_result()->fetch_assoc();
                                            $stmtStatus->close();
                                            $isActive = ($statusRow['meeting_status'] ?? '') === 'active';
                                        }

                                        $canStartOrJoin = $isActive || $isWithinTime;

                                        echo "<tr class='border-b border-gray-100 hover:bg-amber-50/40 transition-colors' id='meeting-row-{$meetingId}'>";
                                        echo "<td class='py-4 px-4 font-medium'>" . htmlspecialchars($meeting['title']) . "</td>";
                                        echo "<td class='py-4 px-4 text-gray-600'>" . htmlspecialchars($meeting['unit_name']) . "</td>";
                                        echo "<td class='py-4 px-4 text-sm text-amber-700 font-medium'>" . $timeFormatted . "</td>";
                                        echo "<td class='py-4 px-4'>";
                                        echo "<div class='meeting-link-row'>";
                                        echo "<input type='text' id='" . $inputId . "' readonly value='" . htmlspecialchars($studentJoinUrl) . "' style='min-width:220px;max-width:320px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;'>";
                                        echo "<button type='button' class='btn-secondary copy-link-btn' data-target='" . $inputId . "' style='padding:6px 10px;font-size:12px;'>Copy</button>";
                                        echo "</div>";
                                        echo "</td>";
                                        echo "<td class='py-4 px-4'>";
                                        echo "<div style='display:flex;gap:8px;align-items:center;flex-wrap:wrap;'>";
                                        // Smart button: Start Meeting if not active, Join Meeting if active
                                        if ($canStartOrJoin) {
                                            echo "<a class='inline-flex items-center gap-1.5 px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-200' ";
                                            if ($isActive) {
                                                echo "style='background:linear-gradient(135deg,#16a34a,#15803d);color:white;box-shadow:0 2px 8px rgba(22,163,74,0.3);' ";
                                                echo "href='meeting_ide.php?meeting_id={$meetingId}' ";
                                                echo "data-meeting-id='{$meetingId}' ";
                                                echo "onclick='startOrJoinMeeting({$meetingId}, this)'>";
                                                echo "<span class='live-dot' style='display:inline-block;width:8px;height:8px;background:#ff4444;border-radius:50%;animation:pulse-dot 1.5s infinite;margin-right:4px;'></span> Join Meeting";
                                            } else {
                                                echo "style='background:linear-gradient(135deg,#f59e0b,#d97706);color:white;box-shadow:0 2px 8px rgba(245,158,11,0.3);animation:pulse-glow 2s infinite;' ";
                                                echo "href='meeting_ide.php?meeting_id={$meetingId}' ";
                                                echo "data-meeting-id='{$meetingId}' ";
                                                echo "onclick='startOrJoinMeeting({$meetingId}, this)'>";
                                                echo "<span class='btn-icon' style='font-size:14px;'>▶</span> Start Meeting";
                                            }
                                            echo "</a>";
                                        } else {
                                            echo "<span style='font-size:0.75rem;color:#92400e;background:#fef3c7;padding:0.35rem 0.65rem;border-radius:999px;white-space:nowrap;'>Scheduled</span>";
                                        }
                                        // Always-visible Join button
                                        $joinBtnStyle = "display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border:1.5px solid #6366f1;border-radius:8px;font-size:12px;font-weight:600;color:#6366f1;background:#fff;text-decoration:none;transition:all 0.2s;";
                                        echo "<a href='meeting_ide.php?meeting_id={$meetingId}' style='{$joinBtnStyle}' onmouseover=\"this.style.background='#eef2ff'\" onmouseout=\"this.style.background='#fff'\"><i class='fas fa-sign-in-alt' style='font-size:11px;'></i> Join</a>";
                                        echo "</div>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='py-10 text-center text-gray-500 italic'>No upcoming meetings or office hours scheduled.</td></tr>";
                                }
                                $stmt->close();
                            } catch (Exception $e) {
                                echo "<tr><td colspan='5' class='py-10 text-center text-red-500 italic'>Error loading meetings: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
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

<!-- ==================== NOTES SENT MODAL ==================== -->
<div id="notesSentModal" class="modal hidden">
    <div class="modal-content" style="max-width:700px;max-height:80vh;overflow-y:auto;padding:0;border-radius:14px;background:#fff;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
            <h3 style="margin:0;font-size:18px;font-weight:700;color:#111827;" id="notesSentModalTitle">
                <i class="fas fa-paper-plane" style="color:#6366f1;margin-right:8px;"></i>
                Notes
            </h3>
            <span class="close" style="font-size:28px;font-weight:700;cursor:pointer;color:#6b7280;line-height:1;" id="notesSentModalClose">&times;</span>
        </div>
        <div style="padding:24px;" id="notesSentModalBody">
            <!-- Dynamically populated -->
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

/* Add Unit Modal Styles */
.modal-body .form-group {
    margin-bottom: 20px;
}

.modal-body .form-group label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 14px;
}

.modal-body .form-group input,
.modal-body .form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.modal-body .form-group input:focus,
.modal-body .form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.modal-body .form-group select:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
    opacity: 0.6;
}

.modal-body .grid {
    display: grid;
    gap: 16px;
}

.modal-body .grid.grid-cols-2 {
    grid-template-columns: 1fr 1fr;
}

.modal-body .btn-primary {
    width: 100%;
    padding: 14px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
}

.modal-body .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

@media (max-width: 640px) {
    .modal-body .grid.grid-cols-2 {
        grid-template-columns: 1fr;
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
        <h3 class="text-2xl font-bold stat-text-secondary mb-8 text-center pt-8">Add Unit to My Units</h3>
        <form action="../actions.php" method="POST">
            <input type="hidden" name="action" value="assign_unit">
            <div class="modal-body">
                <!-- Department Selection -->
                <div class="form-group">
                    <label>Department <span class="text-red-500">*</span></label>
                    <select name="department_id" id="departmentSelect" required>
                        <option value="">-- Select Department --</option>
                        <?php
                        $dept_stmt = $conn->prepare("SELECT id, name FROM departments ORDER BY name");
                        $dept_stmt->execute();
                        $dept_result = $dept_stmt->get_result();
                        while ($dept = $dept_result->fetch_assoc()): ?>
                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                        <?php endwhile;
                        $dept_stmt->close();
                        ?>
                    </select>
                </div>
                
                <!-- Course Selection -->
                <div class="form-group">
                    <label>Course <span class="text-red-500">*</span></label>
                    <select name="course_id" id="courseSelect" required disabled>
                        <option value="">-- Select Course --</option>
                    </select>
                </div>
                
                <!-- Unit Selection -->
                <div class="form-group">
                    <label>Unit <span class="text-red-500">*</span></label>
                    <select name="unit_id" id="unitSelect" required disabled>
                        <option value="">-- Select Unit --</option>
                    </select>
                    <small class="form-help">Select a unit from this course to add to your teaching units</small>
                </div>
                
                <button type="submit" class="btn-primary">Add Unit to My Units</button>
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
                        <input type="datetime-local" name="scheduled_time" id="meetingScheduledTime" required>
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
    function activateTab(tabClass) {
        const tab = document.querySelector(`.card-navbar li.${tabClass}`);
        if (!tab) return;
        document.querySelectorAll('.card-navbar li').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
        const section = document.querySelector(`.${tabClass}-content`);
        if (section) section.style.display = 'block';
    }

    // Tab switching
    document.querySelectorAll('.card-navbar li').forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.className.split(' ')[0];
            activateTab(target);
        });
    });

    // Open meetings tab when redirected after scheduling
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'meetings') {
        activateTab('meetings');
    }

    // Schedule meeting modal
    const scheduleMeetingBtn = document.getElementById('scheduleMeetingBtn');
    const scheduleMeetingModal = document.getElementById('scheduleMeetingModal');
    const scheduleMeetingModalClose = document.getElementById('scheduleMeetingModalClose');
    const meetingScheduledTime = document.getElementById('meetingScheduledTime');

    if (meetingScheduledTime) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        meetingScheduledTime.min = now.toISOString().slice(0, 16);
    }

    scheduleMeetingBtn?.addEventListener('click', () => {
        showModal('scheduleMeetingModal');
    });

    scheduleMeetingModalClose?.addEventListener('click', () => {
        hideModal('scheduleMeetingModal');
    });

    scheduleMeetingModal?.addEventListener('click', e => {
        if (e.target === scheduleMeetingModal) {
            hideModal('scheduleMeetingModal');
        }
    });

    document.querySelectorAll('.copy-link-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(() => {
                const original = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(() => { btn.textContent = original; }, 2000);
            }).catch(() => alert('Could not copy link. Please copy manually.'));
        });
    });

    // Notifications popup toggle
    const notifIcon = document.getElementById('notifications-icon');
    const notifPopup = document.getElementById('notifications-popup');
    const profileIcon = document.getElementById('profile-icon');
    const profilePopup = document.getElementById('profile-popup');

    notifIcon?.addEventListener('click', (e) => {
        e.stopPropagation();
        const visible = notifPopup.style.display === 'block';
        notifPopup.style.display = visible ? 'none' : 'block';
        profilePopup.style.display = 'none';
    });

    profileIcon?.addEventListener('click', (e) => {
        e.stopPropagation();
        const visible = profilePopup.style.display === 'block';
        profilePopup.style.display = visible ? 'none' : 'block';
        notifPopup.style.display = 'none';
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

        // Close popups on outside click
        if (notifPopup && !notifPopup.contains(e.target) && notifIcon && !notifIcon.contains(e.target)) {
            notifPopup.style.display = 'none';
        }
        if (profilePopup && !profilePopup.contains(e.target) && profileIcon && !profileIcon.contains(e.target)) {
            profilePopup.style.display = 'none';
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

    // ==================== NOTES SENT FEATURE ====================
    const notesSentBtn = document.getElementById('sidebarNotesSent');
    const notesSentContent = document.querySelector('.notes-sent-content');
    const notesSentModal = document.getElementById('notesSentModal');
    const notesSentModalBody = document.getElementById('notesSentModalBody');
    const notesSentModalTitle = document.getElementById('notesSentModalTitle');
    const notesSentModalClose = document.getElementById('notesSentModalClose');
    const notesData = document.getElementById('all-notes-data');

    // Click sidebar "Notes Sent" -> show the notes-sent content section
    notesSentBtn?.addEventListener('click', () => {
        // Switch to "Notes Sent" view
        document.querySelectorAll('.card-navbar li').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
        if (notesSentContent) notesSentContent.style.display = 'block';

        // Highlight sidebar item
        document.querySelectorAll('.sidebar ul li').forEach(li => li.classList.remove('active'));
        notesSentBtn.classList.add('active');
    });

    // Click a unit tile in the Notes Sent section -> open modal with notes
    document.querySelectorAll('.notes-sent-tile').forEach(tile => {
        tile.addEventListener('click', () => {
            const unitId = tile.dataset.unitId;
            const unitName = tile.dataset.unitName;

            // Update modal title
            notesSentModalTitle.innerHTML = `<i class="fas fa-paper-plane" style="color:#6366f1;margin-right:8px;"></i> ${escapeHtml(unitName)}`;

            // Find notes data for this unit
            const dataDiv = document.querySelector(`.unit-notes-data[data-unit-id="${unitId}"]`);
            if (dataDiv) {
                // Clone the table to avoid mutating the original
                const tableHtml = dataDiv.innerHTML;
                notesSentModalBody.innerHTML = `
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f3f4f6;">
                                <th style="padding:10px 12px;text-align:left;font-size:13px;font-weight:600;color:#374151;">File</th>
                                <th style="padding:10px 12px;text-align:left;font-size:13px;font-weight:600;color:#374151;">Uploaded</th>
                                <th style="padding:10px 12px;text-align:left;font-size:13px;font-weight:600;color:#374151;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${dataDiv.querySelector('tbody').innerHTML}
                        </tbody>
                    </table>
                `;
            } else {
                notesSentModalBody.innerHTML = `
                    <div style="text-align:center;padding:40px 20px;color:#9ca3af;">
                        <i class="fas fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;color:#d1d5db;"></i>
                        No notes uploaded yet for this unit.
                    </div>
                `;
            }

            // Show modal
            notesSentModal?.classList.remove('hidden');
        });
    });

    // Close Notes Sent modal
    notesSentModalClose?.addEventListener('click', () => notesSentModal?.classList.add('hidden'));
    notesSentModal?.addEventListener('click', e => {
        if (e.target === notesSentModal) notesSentModal.classList.add('hidden');
    });

    // Helper: escape HTML
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ==================== NOTE STATUS ACTIONS ====================
    // Use event delegation for dynamically loaded content
    document.addEventListener('click', async (e) => {
        const target = e.target;
        
        // Hide note
        if (target.classList.contains('hide-note-btn')) {
            e.preventDefault();
            const noteId = target.dataset.noteId;
            if (!confirm('Hide this note from students?')) return;
            await updateNoteStatus(noteId, 'hide_note', target);
        }
        
        // Delete note (soft delete - shows cover)
        if (target.classList.contains('delete-note-btn')) {
            e.preventDefault();
            const noteId = target.dataset.noteId;
            if (!confirm('Mark this note as deleted? Students will see it covered and cannot view/download.')) return;
            await updateNoteStatus(noteId, 'delete_note', target);
        }
        
        // Unhide note
        if (target.classList.contains('unhide-note-btn')) {
            e.preventDefault();
            const noteId = target.dataset.noteId;
            if (!confirm('Make this note visible to students again?')) return;
            await updateNoteStatus(noteId, 'unhide_note', target);
        }
    });

    async function updateNoteStatus(noteId, action, btnElement) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('note_id', noteId);

        try {
            const response = await fetch('../actions.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // Reload the page to reflect changes
                location.reload();
            } else {
                alert('Error: ' + (result.error || 'Failed to update note status'));
            }
        } catch (error) {
            console.error('Error updating note status:', error);
            alert('Error updating note status: ' + error.message);
        }
    }

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

    // Department → Course → Units selection for Add Unit modal
    const departmentSelect = document.getElementById('departmentSelect');
    const courseSelect = document.getElementById('courseSelect');
    const unitSelect = document.getElementById('unitSelect');
    
    if (departmentSelect && courseSelect && unitSelect) {
        // Handle department change
        departmentSelect.addEventListener('change', async function() {
            const departmentId = this.value;
            
            // Reset course and unit selections
            courseSelect.innerHTML = '<option value="">-- Select Course --</option>';
            unitSelect.innerHTML = '<option value="">-- Select Unit --</option>';
            courseSelect.disabled = !departmentId;
            unitSelect.disabled = true;
            
            if (departmentId) {
                try {
                    // Fetch courses for selected department
                    const response = await fetch('../api/get_courses.php?department_id=' + encodeURIComponent(departmentId));
                    if (response.ok) {
                        const courses = await response.json();
                        
                        courses.forEach(course => {
                            const option = document.createElement('option');
                            option.value = course.id;
                            option.textContent = course.name;
                            courseSelect.appendChild(option);
                        });
                    } else {
                        console.error('Failed to fetch courses');
                    }
                } catch (error) {
                    console.error('Error fetching courses:', error);
                }
            }
        });
        
        // Handle course change
        courseSelect.addEventListener('change', async function() {
            const courseId = this.value;
            
            // Reset unit selection
            unitSelect.innerHTML = '<option value="">-- Select Unit --</option>';
            unitSelect.disabled = !courseId;
            
            if (courseId) {
                try {
                    // Fetch units for selected course
                    const response = await fetch('../api/get_units.php?course_id=' + encodeURIComponent(courseId));
                    if (response.ok) {
                        const units = await response.json();
                        
                        units.forEach(unit => {
                            const option = document.createElement('option');
                            option.value = unit.id;
                            option.textContent = `${unit.code} - ${unit.name} (Year ${unit.year}, Semester ${unit.semester})`;
                            unitSelect.appendChild(option);
                        });
                    } else {
                        console.error('Failed to fetch units');
                    }
                } catch (error) {
                    console.error('Error fetching units:', error);
                }
            }
        });
    }

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

/**
 * Start or Join Meeting - marks meeting as active and navigates to IDE
 * This function is called when clicking "Start Meeting" or "Join Meeting" buttons
 */
function startOrJoinMeeting(meetingId, btnElement) {
    // Update button to show loading state
    const originalHtml = btnElement.innerHTML;
    btnElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';
    btnElement.style.pointerEvents = 'none';
    btnElement.style.opacity = '0.7';

    // Mark meeting as active in database
    fetch('../api/meeting_state.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'start_meeting',
            meeting_id: meetingId,
            user_id: <?= (int)$lecturer_id ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        // Navigate to the IDE regardless of API result
        window.location.href = btnElement.getAttribute('href');
    })
    .catch(error => {
        console.error('Error starting meeting:', error);
        // Navigate anyway even if API call fails
        window.location.href = btnElement.getAttribute('href');
    });

    return false; // Prevent default link navigation
}

// Global notification mark-as-read function (called from onclick)
function quickMarkRead(notificationId) {
    const formData = new FormData();
    formData.append('action', 'mark_notification_read');
    formData.append('notification_id', notificationId);

    fetch('dashboard.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;

            const item = document.getElementById('quick-notif-' + notificationId);
            if (item) {
                item.classList.remove('unread');
                item.style.background = 'white';
                const indicator = item.querySelector('[style*="background: #ef4444"]');
                if (indicator) indicator.remove();
            }

            const badge = document.getElementById('notificationCount');
            if (badge) {
                const count = parseInt(badge.textContent) || 0;
                if (count > 1) {
                    badge.textContent = count - 1;
                } else {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
}
</script>

</body>

</html>