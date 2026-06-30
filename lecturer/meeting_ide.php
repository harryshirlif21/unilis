<?php
/**
 * LECTURER MEETING INTERFACE
 * Google Meet-style video conferencing for lecturers
 * Features: WebRTC streaming, attendance, chat, participant management
 */

session_start();
require_once '../config/db.php'; // MySQLi connection as $conn

// Authentication guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);

if (!$meeting_id) {
    die("Meeting ID required");
}

// Fetch meeting details
$stmt = $conn->prepare("SELECT m.id, m.title, m.scheduled_time, m.duration, m.lecturer_id, u.course_id, u.year, u.name as unit_name
                        FROM meetings m 
                        LEFT JOIN units u ON m.unit_id = u.id 
                        WHERE m.id = ? AND m.lecturer_id = ?");
$stmt->bind_param("ii", $meeting_id, $lecturer_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die("Meeting not found or access denied");
}

// Create signals table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS meeting_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    from_student_id INT NULL,
    from_lecturer_id INT NULL,
    to_student_id INT NULL,
    to_lecturer_id INT NULL,
    type VARCHAR(50) NOT NULL,
    data TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_meeting (meeting_id),
    INDEX idx_recipient (to_student_id, to_lecturer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Update meeting as active/started
$conn->query("UPDATE meetings SET meeting_status = 'active', started_at = NOW() WHERE id = $meeting_id");

// ==================== AJAX HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    function respond($data) {
        echo json_encode($data);
        exit;
    }
    
    $action = $_POST['action'];
    
    switch ($action) {
        // Get all participants (students who joined)
        case 'get_participants':
            $sql = "SELECT ma.id, ma.student_id, COALESCE(s.name, ma.guest_name) as name, 
                    COALESCE(s.reg_no, ma.reg_no) as reg_no, ma.joined_at, ma.status
                    FROM meeting_attendance ma
                    LEFT JOIN students s ON ma.student_id = s.id
                    WHERE ma.meeting_id = ? AND ma.status = 'joined'
                    ORDER BY ma.joined_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $meeting_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            respond($result);
            break;
            
        // Get attendance for panel
        case 'get_attendance':
            $sql = "SELECT ma.id, COALESCE(s.name, ma.guest_name) as name,
                    COALESCE(s.reg_no, ma.reg_no) as reg_no, ma.joined_at,
                    TIMESTAMPDIFF(MINUTE, ma.joined_at, NOW()) as duration,
                    ma.active, ma.marks, ma.student_id
                    FROM meeting_attendance ma
                    LEFT JOIN students s ON ma.student_id = s.id
                    WHERE ma.meeting_id = ?
                    ORDER BY ma.joined_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $meeting_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            respond($result);
            break;
            
        // Award marks to student
        case 'award_marks':
            $attendance_id = (int)$_POST['attendance_id'];
            $marks = (int)$_POST['marks'];
            $stmt = $conn->prepare("UPDATE meeting_attendance SET marks = ? WHERE id = ? AND meeting_id = ?");
            $stmt->bind_param("iii", $marks, $attendance_id, $meeting_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // Toggle student active status
        case 'toggle_active':
            $attendance_id = (int)$_POST['attendance_id'];
            $stmt = $conn->prepare("UPDATE meeting_attendance SET active = NOT active WHERE id = ? AND meeting_id = ?");
            $stmt->bind_param("ii", $attendance_id, $meeting_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // Chat messages
        case 'get_chat':
            $stmt = $conn->prepare("SELECT user_id, user_name, message, created_at 
                                   FROM chat WHERE meeting_id = ? ORDER BY created_at ASC");
            $stmt->bind_param("i", $meeting_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            respond($result);
            break;
            
        case 'send_chat':
            $message = trim($_POST['message'] ?? '');
            if ($message === '') respond(['success' => false]);
            // Determine sender - allow students to send chat too
            $sender_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : $lecturer_id;
            $sender_name = trim($_POST['user_name'] ?? '');
            if (empty($sender_name)) {
                $sender_name = $sender_id == $lecturer_id ? $lecturer_name : "Student #{$sender_id}";
            }
            $stmt = $conn->prepare("INSERT INTO chat (meeting_id, user_id, user_name, message, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiss", $meeting_id, $sender_id, $sender_name, $message);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // WebRTC Signaling
        case 'send_signal':
            $to_student = (int)($_POST['to_student_id'] ?? 0);
            $type = $_POST['type'] ?? '';
            $data = $_POST['data'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiss", $meeting_id, $lecturer_id, $to_student, $type, $data);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        case 'get_signals':
            $stmt = $conn->prepare("SELECT id, from_student_id, type, data 
                                   FROM meeting_signals 
                                   WHERE meeting_id = ? AND to_lecturer_id = ?
                                   ORDER BY id ASC");
            $stmt->bind_param("ii", $meeting_id, $lecturer_id);
            $stmt->execute();
            $signals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Delete after fetching
            if (!empty($signals)) {
                $ids = array_column($signals, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $conn->prepare("DELETE FROM meeting_signals WHERE id IN ($placeholders)");
                $types = str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $stmt->close();
            }
            respond($signals);
            break;
            
        // Approve student publish request
        case 'approve_publish':
            $student_id = (int)$_POST['student_id'];
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) 
                                   VALUES (?, ?, ?, 'allow-publish', '', NOW())");
            $stmt->bind_param("iii", $meeting_id, $lecturer_id, $student_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        case 'approve_screen':
            $student_id = (int)$_POST['student_id'];
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) 
                                   VALUES (?, ?, ?, 'allow-screen', '', NOW())");
            $stmt->bind_param("iii", $meeting_id, $lecturer_id, $student_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // End meeting
        case 'end_meeting':
            // Notify all students
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) 
                                   SELECT ?, ?, student_id, 'end', '', NOW()
                                   FROM meeting_attendance WHERE meeting_id = ?");
            $stmt->bind_param("iii", $meeting_id, $lecturer_id, $meeting_id);
            $stmt->execute();
            $stmt->close();
            // Mark meeting as ended
            $conn->query("UPDATE meetings SET meeting_status = 'ended', ended = 1, ended_at = NOW() WHERE id = $meeting_id");
            respond(['success' => true]);
            break;
            
        default:
            respond(['success' => false, 'error' => 'Unknown action']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($meeting['title']) ?> - Lecturer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg-dark: #202124;
            --bg-darker: #171717;
            --bg-panel: #292929;
            --text-primary: #e8eaed;
            --text-secondary: #9aa0a6;
            --accent-blue: #8ab4f8;
            --accent-green: #81c995;
            --accent-red: #f28b82;
            --accent-yellow: #fdd663;
            --border-color: #3c4043;
        }
        
        body {
            font-family: 'Google Sans', Roboto, Arial, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            overflow: hidden;
            height: 100vh;
        }
        
        /* Top Navigation */
        .top-nav {
            height: 56px;
            background: var(--bg-darker);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            position: relative;
            z-index: 100;
        }
        
        .meeting-info h2 {
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .meeting-info h2 .live-badge {
            font-size: 11px;
            background: var(--accent-red);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .meeting-info p {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        /* Main Layout */
        .meeting-container {
            display: flex;
            height: calc(100vh - 136px); /* 56px nav + 80px controls */
            position: relative;
        }
        
        /* Main Video Area */
        .main-video-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--bg-darker);
            position: relative;
            overflow: hidden;
        }
        
        #mainVideo {
            max-width: 100%;
            max-height: 100%;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }
        
        /* Student Grid (shown when no screen share) */
        #studentGrid {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            padding: 20px;
            width: 100%;
            height: 100%;
            overflow-y: auto;
            align-content: center;
        }
        
        #studentGrid.active {
            display: grid;
        }
        
        .student-card {
            aspect-ratio: 1;
            background: linear-gradient(135deg, #2d2d2d, #1a1a1a);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            position: relative;
            min-height: 150px;
            cursor: default;
        }
        
        .student-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }
        
        .student-card .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), #5a9fd4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            color: white;
        }
        
        .student-card .s-name {
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }
        
        .student-card .s-reg {
            font-size: 11px;
            color: var(--text-secondary);
        }
        
        .student-card .status-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent-green);
            box-shadow: 0 0 8px rgba(129, 201, 149, 0.6);
        }
        
        .student-card .stream-indicators {
            display: flex;
            gap: 12px;
            margin-top: 4px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .student-card .stream-indicators i.active {
            color: var(--accent-green);
        }
        
        /* Empty state */
        .empty-state {
            color: var(--text-secondary);
            text-align: center;
            padding: 40px;
            font-size: 16px;
        }
        
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        /* Self Preview (corner) */
        .self-preview {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 200px;
            height: 112px;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            border: 2px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            z-index: 10;
        }
        
        .self-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .self-preview .label {
            position: absolute;
            bottom: 8px;
            left: 8px;
            background: rgba(0,0,0,0.7);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        
        /* Screen Share Overlay */
        .screen-share-indicator {
            position: absolute;
            top: 16px;
            left: 16px;
            background: rgba(0,0,0,0.7);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 10;
        }
        
        .screen-share-indicator.active {
            display: flex;
        }
        
        .screen-share-indicator i {
            color: var(--accent-red);
        }
        
        /* Control Bar */
        .control-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: var(--bg-panel);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            z-index: 50;
        }
        
        .control-btn {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--bg-darker);
            border: none;
            color: var(--text-primary);
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            position: relative;
        }
        
        .control-btn:hover {
            background: #3c4043;
            transform: scale(1.05);
        }
        
        .control-btn.active {
            background: var(--accent-blue);
        }
        
        .control-btn.active-danger {
            background: var(--accent-red);
        }
        
        .control-btn.danger {
            background: var(--accent-red);
            width: 56px;
            height: 56px;
        }
        
        .control-btn.danger:hover {
            background: #d93025;
        }
        
        .control-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .control-btn .label {
            position: absolute;
            bottom: -18px;
            font-size: 10px;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        
        /* Side Panel (Participants/Chat) */
        .side-panel {
            width: 340px;
            background: var(--bg-panel);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            position: relative;
            z-index: 40;
        }
        
        .side-panel.open {
            transform: translateX(0);
        }
        
        .panel-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        
        .panel-tab {
            flex: 1;
            padding: 14px;
            text-align: center;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            font-size: 13px;
        }
        
        .panel-tab.active {
            border-bottom-color: var(--accent-blue);
            color: var(--accent-blue);
        }
        
        .panel-tab i {
            margin-right: 6px;
        }
        
        .panel-content {
            flex: 1;
            overflow-y: auto;
            display: none;
        }
        
        .panel-content.active {
            display: block;
        }
        
        /* Participants */
        .participants-list {
            padding: 12px;
        }
        
        .participant-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 6px;
            background: var(--bg-darker);
        }
        
        .participant-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), #5a9fd4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .participant-info {
            flex: 1;
            min-width: 0;
        }
        
        .participant-name {
            font-size: 13px;
            font-weight: 500;
        }
        
        .participant-reg {
            font-size: 11px;
            color: var(--text-secondary);
        }
        
        .participant-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-green);
            flex-shrink: 0;
        }
        
        /* Chat */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }
        
        .chat-message {
            margin-bottom: 12px;
        }
        
        .chat-message.me {
            text-align: right;
        }
        
        .message-sender {
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }
        
        .message-bubble {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 12px;
            background: var(--bg-darker);
            max-width: 85%;
            word-wrap: break-word;
            font-size: 13px;
        }
        
        .chat-message.me .message-bubble {
            background: var(--accent-blue);
            color: #111;
        }
        
        .chat-input-area {
            padding: 12px;
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        
        .chat-input-area textarea {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            background: var(--bg-darker);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            resize: none;
            font-family: inherit;
            font-size: 13px;
        }
        
        .chat-input-area button {
            margin-top: 6px;
            width: 100%;
            padding: 8px;
            border-radius: 8px;
            background: var(--accent-blue);
            border: none;
            color: #111;
            font-weight: 500;
            cursor: pointer;
        }
        
        /* Attendance Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }
        
        .modal.open {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-panel);
            border-radius: 12px;
            padding: 24px;
            max-width: 900px;
            width: 92%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .modal-header h3 {
            font-size: 18px;
            font-weight: 500;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .attendance-table th {
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 12px;
        }
        
        .attendance-table input[type="number"] {
            width: 56px;
            padding: 5px;
            background: var(--bg-darker);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-primary);
        }
        
        .award-btn {
            padding: 5px 10px;
            background: var(--accent-green);
            border: none;
            border-radius: 4px;
            color: #111;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }
        
        .award-btn:hover {
            opacity: 0.9;
        }
        
        /* Notification Badge */
        .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--accent-red);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 300;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .toast {
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            animation: slideIn 0.3s ease;
            max-width: 320px;
        }
        
        .toast.info { background: #1a73e8; color: white; }
        .toast.success { background: var(--accent-green); color: #111; }
        .toast.error { background: var(--accent-red); color: white; }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .side-panel {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 80px;
                width: 100%;
                max-width: 320px;
            }
            
            .self-preview {
                width: 120px;
                height: 68px;
                bottom: 100px;
                right: 10px;
            }
            
            .control-btn {
                width: 44px;
                height: 44px;
                font-size: 16px;
            }
            
            .student-card {
                min-height: 120px;
            }
            
            .student-card .avatar {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="meeting-info">
            <h2>
                <?= htmlspecialchars($meeting['title']) ?>
                <span class="live-badge">LIVE</span>
            </h2>
            <p><i class="fas fa-book"></i> <?= htmlspecialchars($meeting['unit_name'] ?? '') ?></p>
        </div>
        <div>
            <a href="../lecturer/dashboard.php" style="color: var(--text-secondary); text-decoration: none; font-size: 13px;">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>
    
    <!-- Main Meeting Container -->
    <div class="meeting-container">
        <!-- Main Video Area -->
        <div class="main-video-area">
            <!-- Main video (lecturer camera or screen share) -->
            <video id="mainVideo" autoplay playsinline></video>
            
            <!-- Screen share indicator -->
            <div class="screen-share-indicator" id="screenShareIndicator">
                <i class="fas fa-desktop"></i>
                <span>You are sharing your screen</span>
            </div>
            
            <!-- Student Grid (shown when NO screen share) -->
            <div id="studentGrid">
                <div class="empty-state" id="noStudents">
                    <i class="fas fa-users"></i>
                    <div>Waiting for students to join...</div>
                </div>
            </div>
            
            <!-- Self Preview (corner) -->
            <div class="self-preview">
                <video id="selfVideo" autoplay muted playsinline></video>
                <div class="label">You (Lecturer)</div>
            </div>
        </div>
        
        <!-- Side Panel (Participants/Chat) -->
        <div id="sidePanel" class="side-panel">
            <div class="panel-tabs">
                <div class="panel-tab active" data-tab="participants">
                    <i class="fas fa-users"></i> <span id="participantCount">0</span>
                </div>
                <div class="panel-tab" data-tab="chat">
                    <i class="fas fa-comment"></i> Chat
                    <span id="chatBadge" class="badge" style="display: none;">0</span>
                </div>
            </div>
            
            <div id="participantsPanel" class="panel-content active">
                <div class="participants-list" id="participantsList"></div>
            </div>
            
            <div id="chatPanel" class="panel-content">
                <div class="chat-container">
                    <div class="chat-messages" id="chatMessages"></div>
                    <div class="chat-input-area">
                        <textarea id="chatInput" placeholder="Type a message..." rows="2"></textarea>
                        <button id="sendChatBtn">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Control Bar -->
    <div class="control-bar">
        <button id="toggleMicBtn" class="control-btn" title="Toggle Microphone">
            <i class="fas fa-microphone"></i>
            <span class="label">Mic</span>
        </button>
        
        <button id="toggleCameraBtn" class="control-btn" title="Toggle Camera">
            <i class="fas fa-video"></i>
            <span class="label">Camera</span>
        </button>
        
        <button id="shareScreenBtn" class="control-btn" title="Share Screen">
            <i class="fas fa-desktop"></i>
            <span class="label">Share</span>
        </button>
        
        <button id="participantsBtn" class="control-btn" title="Participants">
            <i class="fas fa-user-friends"></i>
            <span class="label">People</span>
        </button>
        
        <button id="chatBtn" class="control-btn" title="Chat">
            <i class="fas fa-comment-dots"></i>
            <span class="label">Chat</span>
        </button>
        
        <button id="attendanceBtn" class="control-btn" title="Attendance">
            <i class="fas fa-clipboard-check"></i>
            <span class="label">Attendance</span>
        </button>
        
        <button id="endMeetingBtn" class="control-btn danger" title="End Meeting">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>
    
    <!-- Attendance Modal -->
    <div id="attendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-clipboard-check"></i> Attendance</h3>
                <button class="close-btn" id="closeAttendance">&times;</button>
            </div>
            <div style="overflow-x: auto;">
                <table class="attendance-table" id="attendanceTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Reg No</th>
                            <th>Joined</th>
                            <th>Duration</th>
                            <th>Active</th>
                            <th>Marks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // ==================== CONFIG ====================
        const MEETING_ID = <?= $meeting_id ?>;
        const LECTURER_ID = <?= $lecturer_id ?>;
        const LECTURER_NAME = <?= json_encode($lecturer_name) ?>;
        
        // ==================== STATE ====================
        let localStream = null;
        let screenStream = null;
        let peerConnections = {}; // studentId -> RTCPeerConnection
        let micEnabled = true;
        let cameraEnabled = true;
        let isScreenSharing = false;
        let knownStudents = new Set();
        let lastChatCount = 0;
        let isChatOpen = false;
        
        // ==================== UTILITY FUNCTIONS ====================
        async function apiCall(action, data = {}) {
            const formData = new URLSearchParams({ action, ...data });
            const response = await fetch('', { method: 'POST', body: formData });
            return await response.json();
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        // ==================== MEDIA INITIALIZATION ====================
        async function initializeMedia() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });
                
                document.getElementById('selfVideo').srcObject = localStream;
                document.getElementById('mainVideo').srcObject = localStream;
                
                showToast('Camera and microphone ready', 'success');
            } catch (error) {
                console.error('Error accessing media devices:', error);
                showToast('Could not access camera/microphone', 'error');
                // Try audio-only
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                    document.getElementById('selfVideo').srcObject = localStream;
                    showToast('Microphone only mode', 'info');
                } catch (e) {
                    showToast('No media access available', 'error');
                }
            }
        }
        
        // ==================== WEBRTC FUNCTIONS ====================
        function createPeerConnection(studentId) {
            const pc = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            });
            
            // Add local tracks (lecturer sending to student)
            const streamToSend = isScreenSharing && screenStream ? screenStream : localStream;
            if (streamToSend) {
                streamToSend.getTracks().forEach(track => {
                    if (track.kind === 'video' || track.kind === 'audio') {
                        try { pc.addTrack(track, streamToSend); } catch(e) {}
                    }
                });
            }
            
            // ICE candidate handling
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    apiCall('send_signal', {
                        to_student_id: studentId,
                        type: 'candidate',
                        data: JSON.stringify({ candidate: event.candidate })
                    });
                }
            };
            
            // Handle incoming tracks from students
            pc.ontrack = (event) => {
                console.log('Received track from student:', studentId, event.track.kind);
                const [stream] = event.streams;
                updateStudentCardStream(studentId, stream);
            };
            
            pc.onconnectionstatechange = () => {
                if (pc.connectionState === 'failed' || pc.connectionState === 'closed') {
                    delete peerConnections[studentId];
                }
            };
            
            peerConnections[studentId] = pc;
            return pc;
        }
        
        async function sendOfferToStudent(studentId) {
            try {
                const pc = createPeerConnection(studentId);
                const offer = await pc.createOffer({
                    offerToReceiveAudio: true,
                    offerToReceiveVideo: true
                });
                await pc.setLocalDescription(offer);
                
                await apiCall('send_signal', {
                    to_student_id: studentId,
                    type: 'offer',
                    data: JSON.stringify({ sdp: offer.sdp })
                });
                
                console.log('Offer sent to student:', studentId);
            } catch (error) {
                console.error('Error sending offer to student', studentId, error);
            }
        }
        
        async function handleSignal(signal) {
            const { from_student_id, type, data } = signal;
            const parsedData = JSON.parse(data || '{}');
            
            switch (type) {
                case 'answer':
                    const pc = peerConnections[from_student_id];
                    if (pc && parsedData.sdp) {
                        try {
                            await pc.setRemoteDescription(new RTCSessionDescription({
                                type: 'answer',
                                sdp: parsedData.sdp
                            }));
                        } catch(e) {
                            console.error('Error setting remote description:', e);
                        }
                    }
                    break;
                    
                case 'candidate':
                    const peerConn = peerConnections[from_student_id];
                    if (peerConn && parsedData.candidate) {
                        try {
                            await peerConn.addIceCandidate(new RTCIceCandidate(parsedData.candidate));
                        } catch(e) {}
                    }
                    break;
                    
                case 'request-publish':
                    handlePublishRequest(from_student_id, parsedData.name);
                    break;
                    
                case 'request-screen':
                    handleScreenRequest(from_student_id, parsedData.name);
                    break;
                    
                case 'offer':
                    // Student sending their stream (after approval)
                    await handleStudentOffer(from_student_id, parsedData);
                    break;
            }
        }
        
        async function handleStudentOffer(studentId, data) {
            const pc = peerConnections[studentId] || createPeerConnection(studentId);
            
            try {
                await pc.setRemoteDescription(new RTCSessionDescription({
                    type: 'offer',
                    sdp: data.sdp
                }));
                
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                
                await apiCall('send_signal', {
                    to_student_id: studentId,
                    type: 'answer',
                    data: JSON.stringify({ sdp: answer.sdp })
                });
            } catch(e) {
                console.error('Error handling student offer:', e);
            }
        }
        
        function handlePublishRequest(studentId, name) {
            const displayName = name || `Student #${studentId}`;
            if (confirm(`${displayName} wants to publish camera/mic. Allow?`)) {
                apiCall('approve_publish', { student_id: studentId });
            }
        }
        
        function handleScreenRequest(studentId, name) {
            const displayName = name || `Student #${studentId}`;
            if (confirm(`${displayName} wants to share screen. Allow?`)) {
                apiCall('approve_screen', { student_id: studentId });
            }
        }
        
        function updateStudentCardStream(studentId, stream) {
            // Optional: upgrade student card with video preview
        }
        
        // ==================== SCREEN SHARING ====================
        async function toggleScreenShare() {
            if (isScreenSharing) {
                stopScreenShare();
            } else {
                await startScreenShare();
            }
        }
        
        async function startScreenShare() {
            try {
                screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: { cursor: 'always' },
                    audio: false
                });
                
                // Add audio from microphone if available
                if (localStream) {
                    const audioTrack = localStream.getAudioTracks()[0];
                    if (audioTrack) {
                        screenStream.addTrack(audioTrack);
                    }
                }
                
                // Show screen in main area
                document.getElementById('mainVideo').srcObject = screenStream;
                document.getElementById('mainVideo').style.display = 'block';
                document.getElementById('studentGrid').classList.remove('active');
                document.getElementById('screenShareIndicator').classList.add('active');
                isScreenSharing = true;
                
                // Update all peer connections
                Object.keys(peerConnections).forEach(studentId => {
                    const pc = peerConnections[studentId];
                    const senders = pc.getSenders();
                    
                    const videoSender = senders.find(s => s.track?.kind === 'video');
                    if (videoSender && screenStream.getVideoTracks()[0]) {
                        videoSender.replaceTrack(screenStream.getVideoTracks()[0]);
                    }
                });
                
                document.getElementById('shareScreenBtn').classList.add('active');
                showToast('Screen sharing started', 'success');
                
                // Handle screen share stop by user
                screenStream.getVideoTracks()[0].onended = () => {
                    stopScreenShare();
                };
                
            } catch (error) {
                console.error('Error sharing screen:', error);
                showToast('Failed to share screen', 'error');
            }
        }
        
        function stopScreenShare() {
            if (screenStream) {
                screenStream.getTracks().forEach(track => track.stop());
                screenStream = null;
            }
            
            // Restore camera to main area
            if (localStream) {
                document.getElementById('mainVideo').srcObject = localStream;
                document.getElementById('mainVideo').style.display = 'block';
            }
            
            // Show student grid since no screen share
            document.getElementById('studentGrid').classList.add('active');
            document.getElementById('screenShareIndicator').classList.remove('active');
            isScreenSharing = false;
            
            // Restore camera track to all peer connections
            Object.keys(peerConnections).forEach(studentId => {
                const pc = peerConnections[studentId];
                if (!pc) return;
                const senders = pc.getSenders();
                
                const videoSender = senders.find(s => s.track?.kind === 'video');
                if (videoSender && localStream) {
                    videoSender.replaceTrack(localStream.getVideoTracks()[0]);
                }
            });
            
            document.getElementById('shareScreenBtn').classList.remove('active');
            showToast('Screen sharing stopped', 'info');
        }
        
        // ==================== PARTICIPANTS (CARDS + LIST) ====================
        async function loadParticipants() {
            const participants = await apiCall('get_participants');
            const list = document.getElementById('participantsList');
            const count = document.getElementById('participantCount');
            const grid = document.getElementById('studentGrid');
            
            count.textContent = participants.length;
            
            // Update side panel list
            list.innerHTML = '';
            
            // Add lecturer to participants list
            const lecturerItem = document.createElement('div');
            lecturerItem.className = 'participant-item';
            lecturerItem.innerHTML = `
                <div class="participant-avatar" style="background: linear-gradient(135deg, #fdd663, #f59e0b);">${escapeHtml(LECTURER_NAME.charAt(0).toUpperCase())}</div>
                <div class="participant-info">
                    <div class="participant-name">${escapeHtml(LECTURER_NAME)} <span style="font-size:10px;color:var(--accent-yellow);">(You)</span></div>
                    <div class="participant-reg">Lecturer</div>
                </div>
                <div class="participant-status" style="background:var(--accent-yellow);"></div>
            `;
            list.appendChild(lecturerItem);
            
            if (participants.length === 0) {
                document.getElementById('noStudents').style.display = 'block';
                
                // Show empty grid when no screen share
                if (!isScreenSharing) {
                    grid.classList.add('active');
                }
                return;
            }
            
            document.getElementById('noStudents').style.display = 'none';
            
            // Build student cards grid
            let cardsHtml = '';
            
            participants.forEach(p => {
                const studentId = p.student_id;
                const initial = (p.name || '?').charAt(0).toUpperCase();
                
                // Add to side panel participants list
                const item = document.createElement('div');
                item.className = 'participant-item';
                item.innerHTML = `
                    <div class="participant-avatar">${escapeHtml(initial)}</div>
                    <div class="participant-info">
                        <div class="participant-name">${escapeHtml(p.name || 'Unknown')}</div>
                        <div class="participant-reg">${escapeHtml(p.reg_no || '')}</div>
                    </div>
                    <div class="participant-status"></div>
                `;
                list.appendChild(item);
                
                // Build grid card
                cardsHtml += `
                    <div class="student-card" id="scard-${studentId}">
                        <div class="status-dot"></div>
                        <div class="avatar">${escapeHtml(initial)}</div>
                        <div class="s-name">${escapeHtml(p.name || 'Student')}</div>
                        <div class="s-reg">${escapeHtml(p.reg_no || '')}</div>
                        <div class="stream-indicators">
                            <i class="fas fa-video${cameraEnabled ? ' active' : ''}"></i>
                            <i class="fas fa-microphone${micEnabled ? ' active' : ''}"></i>
                        </div>
                    </div>
                `;
                
                // Send offer to new participants
                if (!peerConnections[studentId] && !knownStudents.has(studentId)) {
                    knownStudents.add(studentId);
                    setTimeout(() => sendOfferToStudent(studentId), 500);
                }
            });
            
            // Show student grid only when NOT screen sharing
            if (!isScreenSharing) {
                grid.innerHTML = cardsHtml;
                grid.classList.add('active');
                document.getElementById('mainVideo').style.display = 'block';
            }
        }
        
        // ==================== CHAT ====================
        async function loadChat() {
            const messages = await apiCall('get_chat');
            const container = document.getElementById('chatMessages');
            container.innerHTML = '';
            
            messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = `chat-message ${msg.user_id == LECTURER_ID ? 'me' : ''}`;
                div.innerHTML = `
                    <div class="message-sender">${escapeHtml(msg.user_name)}</div>
                    <div class="message-bubble">${escapeHtml(msg.message)}</div>
                `;
                container.appendChild(div);
            });
            
            container.scrollTop = container.scrollHeight;
            
            // Update badge
            if (!isChatOpen && messages.length > lastChatCount) {
                const badge = document.getElementById('chatBadge');
                badge.textContent = messages.length - lastChatCount;
                badge.style.display = 'flex';
            }
            
            lastChatCount = messages.length;
        }
        
        async function sendChat() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message) {
                await apiCall('send_chat', { message });
                input.value = '';
                await loadChat();
                showToast('Message sent', 'success');
            }
        }
        
        // ==================== ATTENDANCE ====================
        async function loadAttendance() {
            const records = await apiCall('get_attendance');
            const tbody = document.getElementById('attendanceBody');
            tbody.innerHTML = '';
            
            records.forEach((record, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${escapeHtml(record.name)}</td>
                    <td>${escapeHtml(record.reg_no || 'N/A')}</td>
                    <td>${record.joined_at || 'N/A'}</td>
                    <td>${record.duration || 0} min</td>
                    <td>
                        <input type="checkbox" ${record.active ? 'checked' : ''} 
                               onchange="toggleActive(${record.id})">
                    </td>
                    <td>
                        <input type="number" min="0" max="10" value="${record.marks || 0}" 
                               id="marks_${record.id}" style="width:56px;">
                    </td>
                    <td>
                        <button class="award-btn" onclick="awardMarks(${record.id})">Award</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        async function awardMarks(attendanceId) {
            const marks = document.getElementById(`marks_${attendanceId}`).value;
            await apiCall('award_marks', { attendance_id: attendanceId, marks });
            showToast('Marks awarded!', 'success');
        }
        
        async function toggleActive(attendanceId) {
            await apiCall('toggle_active', { attendance_id: attendanceId });
        }
        
        window.awardMarks = awardMarks;
        window.toggleActive = toggleActive;
        
        // ==================== SIGNAL POLLING ====================
        async function pollSignals() {
            try {
                const signals = await apiCall('get_signals');
                for (const signal of signals) {
                    await handleSignal(signal);
                }
            } catch (error) {
                console.error('Error polling signals:', error);
            }
        }
        
        // ==================== UI CONTROLS ====================
        // MIC TOGGLE
        document.getElementById('toggleMicBtn').addEventListener('click', () => {
            if (localStream) {
                micEnabled = !micEnabled;
                localStream.getAudioTracks().forEach(track => { track.enabled = micEnabled; });
                
                const btn = document.getElementById('toggleMicBtn');
                btn.innerHTML = micEnabled ? 
                    '<i class="fas fa-microphone"></i><span class="label">Mic</span>' : 
                    '<i class="fas fa-microphone-slash"></i><span class="label">Mic</span>';
                btn.classList.toggle('active', !micEnabled);
                showToast(micEnabled ? 'Microphone on' : 'Microphone muted', 'info');
            }
        });
        
        // CAMERA TOGGLE
        document.getElementById('toggleCameraBtn').addEventListener('click', () => {
            if (localStream && !isScreenSharing) {
                cameraEnabled = !cameraEnabled;
                localStream.getVideoTracks().forEach(track => { track.enabled = cameraEnabled; });
                
                const btn = document.getElementById('toggleCameraBtn');
                btn.innerHTML = cameraEnabled ? 
                    '<i class="fas fa-video"></i><span class="label">Camera</span>' : 
                    '<i class="fas fa-video-slash"></i><span class="label">Camera</span>';
                btn.classList.toggle('active', !cameraEnabled);
                showToast(cameraEnabled ? 'Camera on' : 'Camera off', 'info');
            }
        });
        
        // SCREEN SHARE
        document.getElementById('shareScreenBtn').addEventListener('click', toggleScreenShare);
        
        // PARTICIPANTS PANEL TOGGLE
        document.getElementById('participantsBtn').addEventListener('click', () => {
            document.getElementById('sidePanel').classList.toggle('open');
            switchTab('participants');
        });
        
        // CHAT PANEL TOGGLE
        document.getElementById('chatBtn').addEventListener('click', () => {
            document.getElementById('sidePanel').classList.toggle('open');
            switchTab('chat');
            isChatOpen = true;
            document.getElementById('chatBadge').style.display = 'none';
        });
        
        // ATTENDANCE MODAL
        document.getElementById('attendanceBtn').addEventListener('click', () => {
            document.getElementById('attendanceModal').classList.add('open');
            loadAttendance();
        });
        
        document.getElementById('closeAttendance').addEventListener('click', () => {
            document.getElementById('attendanceModal').classList.remove('open');
        });
        
        // END MEETING
        document.getElementById('endMeetingBtn').addEventListener('click', async () => {
            if (confirm('End this meeting for everyone?')) {
                await apiCall('end_meeting');
                showToast('Meeting ended', 'success');
                setTimeout(() => {
                    window.location.href = '../lecturer/dashboard.php?tab=meetings';
                }, 1000);
            }
        });
        
        // CHAT SEND
        document.getElementById('sendChatBtn').addEventListener('click', sendChat);
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChat();
            }
        });
        
        // MODAL CLOSE ON BACKGROUND CLICK
        document.getElementById('attendanceModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('attendanceModal')) {
                document.getElementById('attendanceModal').classList.remove('open');
            }
        });
        
        // Panel tabs
        document.querySelectorAll('.panel-tab').forEach(tab => {
            tab.addEventListener('click', () => switchTab(tab.dataset.tab));
        });
        
        function switchTab(tabName) {
            document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.panel-content').forEach(c => c.classList.remove('active'));
            
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(`${tabName}Panel`).classList.add('active');
            
            if (tabName === 'chat') {
                isChatOpen = true;
                document.getElementById('chatBadge').style.display = 'none';
            } else {
                isChatOpen = false;
            }
        }
        
        // ==================== INITIALIZATION ====================
        async function initialize() {
            // Start with student grid visible
            document.getElementById('studentGrid').classList.add('active');
            
            await initializeMedia();
            await loadParticipants();
            await loadChat();
            
            // Start polling
            setInterval(pollSignals, 1500);
            setInterval(loadParticipants, 3000);
            setInterval(loadChat, 3000);
        }
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            Object.values(peerConnections).forEach(pc => pc.close());
            if (localStream) localStream.getTracks().forEach(track => track.stop());
            if (screenStream) screenStream.getTracks().forEach(track => track.stop());
        });
        
        // Start the application
        initialize();
    </script>
</body>
</html>