<?php
require_once '../config/db.php';
require_once '../includes/notifications.php';
require_once __DIR__ . '/../config/meeting.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

    // Semester filter
    $semester = intval($_GET['semester'] ?? $_SESSION['cv_semester'] ?? 1);
    if ($semester < 1 || $semester > 2) $semester = 1;
    $_SESSION['cv_semester'] = $semester;

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

// Get latest 5 notifications for current student
try {
    $latest_notifications = get_latest_notifications($conn, 5, $student_id, 'student');
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    $latest_notifications = [];
}

// Get unread count for current student
try {
    $unread_count = get_unread_notification_count($conn, $student_id, 'student');
} catch (Exception $e) {
    error_log("Error fetching unread count: " . $e->getMessage());
    $unread_count = 0;
}

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

try {
    $studentMeetingsByUnit = fetchStudentMeetingsByUnit($conn, (int)$student_id);
} catch (Exception $e) {
    error_log("Error fetching student meetings: " . $e->getMessage());
    $studentMeetingsByUnit = [];
}
$totalUpcomingMeetings = 0;
$liveMeetingsCount = 0;
$nextMeeting = null;

foreach ($studentMeetingsByUnit as $unitGroup) {
    foreach ($unitGroup['meetings'] as $meetingRow) {
        $totalUpcomingMeetings++;
        if (!empty($meetingRow['is_live'])) {
            $liveMeetingsCount++;
        }
        if (
            $nextMeeting === null
            || strtotime($meetingRow['scheduled_time']) < strtotime($nextMeeting['scheduled_time'])
        ) {
            $nextMeeting = $meetingRow;
            $nextMeeting['unit_name'] = $unitGroup['unit_name'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($student['name']) ?> - UNILIS Dashboard</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Modern Color Palette */
            --primary-50: #f0f9ff;
            --primary-100: #e0f2fe;
            --primary-200: #bae6fd;
            --primary-300: #7dd3fc;
            --primary-400: #38bdf8;
            --primary-500: #0ea5e9;
            --primary-600: #0284c7;
            --primary-700: #0369a1;
            --primary-800: #075985;
            --primary-900: #0c4a6e;
            
            --secondary-50: #fdf4ff;
            --secondary-100: #fae8ff;
            --secondary-200: #f5d0fe;
            --secondary-300: #f0abfc;
            --secondary-400: #e879f9;
            --secondary-500: #d946ef;
            --secondary-600: #c026d3;
            --secondary-700: #a21caf;
            --secondary-800: #86198f;
            --secondary-900: #701a75;
            
            --accent-50: #fefce8;
            --accent-100: #fef9c3;
            --accent-200: #fef08a;
            --accent-300: #fde047;
            --accent-400: #facc15;
            --accent-500: #eab308;
            --accent-600: #ca8a04;
            --accent-700: #a16207;
            --accent-800: #854d0e;
            --accent-900: #713f12;
            
            --success-50: #f0fdf4;
            --success-100: #dcfce7;
            --success-200: #bbf7d0;
            --success-300: #86efac;
            --success-400: #4ade80;
            --success-500: #22c55e;
            --success-600: #16a34a;
            --success-700: #15803d;
            --success-800: #166534;
            --success-900: #14532d;
            
            --warning-50: #fffbeb;
            --warning-100: #fef3c7;
            --warning-200: #fde68a;
            --warning-300: #fcd34d;
            --warning-400: #fbbf24;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            --warning-700: #b45309;
            --warning-800: #92400e;
            --warning-900: #78350f;
            
            --error-50: #fef2f2;
            --error-100: #fee2e2;
            --error-200: #fecaca;
            --error-300: #fca5a5;
            --error-400: #f87171;
            --error-500: #ef4444;
            --error-600: #dc2626;
            --error-700: #b91c1c;
            --error-800: #991b1b;
            --error-900: #7f1d1d;
            
            --neutral-50: #fafafa;
            --neutral-100: #f5f5f5;
            --neutral-200: #e5e5e5;
            --neutral-300: #d4d4d4;
            --neutral-400: #a3a3a3;
            --neutral-500: #737373;
            --neutral-600: #525252;
            --neutral-700: #404040;
            --neutral-800: #262626;
            --neutral-900: #171717;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            --radius-full: 9999px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
            min-height: 100vh;
            color: var(--neutral-900);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Modern Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 72px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--neutral-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-600);
        }
        
        .navbar-brand .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .navbar-center {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary-50);
            border-radius: var(--radius-full);
            border: 1px solid var(--primary-200);
        }
        
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .nav-button {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: var(--radius-full);
            background: var(--neutral-100);
            border: 1px solid var(--neutral-200);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            color: var(--neutral-600);
        }
        
        .nav-button:hover {
            background: var(--primary-50);
            border-color: var(--primary-200);
            color: var(--primary-600);
            transform: translateY(-1px);
        }
        
        .nav-button.active {
            background: var(--primary-500);
            border-color: var(--primary-500);
            color: white;
        }
        
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 20px;
            height: 20px;
            background: var(--error-500);
            color: white;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }
        
        /* Modern Sidebar */
        .sidebar {
            position: fixed;
            top: 72px;
            left: 0;
            width: 280px;
            height: calc(100vh - 72px);
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--neutral-200);
            padding: 1.5rem;
            overflow-y: auto;
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .sidebar-section {
            margin-bottom: 2rem;
        }
        
        .sidebar-section h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--neutral-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }
        
        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 0.25rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: var(--neutral-700);
            font-weight: 500;
        }
        
        .sidebar-item:hover {
            background: var(--primary-50);
            color: var(--primary-600);
            transform: translateX(4px);
        }
        
        .sidebar-item.active {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .sidebar-item i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 0;
            margin-top: 72px;
            padding: 2rem;
            min-height: calc(100vh - 72px);
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            border-radius: var(--radius-2xl);
            padding: 3rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-2xl);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, white, rgba(255,255,255,0.8));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            font-weight: 400;
        }
        
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .hero-stat {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .hero-stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .hero-stat-label {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* Modern Cards */
        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
            transform: scaleX(0);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-200);
        }
        
        .card:hover::before {
            transform: scaleX(1);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .card-icon.blue { background: linear-gradient(135deg, var(--primary-500), var(--primary-600)); color: white; }
        .card-icon.green { background: linear-gradient(135deg, var(--success-500), var(--success-600)); color: white; }
        .card-icon.purple { background: linear-gradient(135deg, var(--secondary-500), var(--secondary-600)); color: white; }
        .card-icon.orange { background: linear-gradient(135deg, var(--warning-500), var(--warning-600)); color: white; }
        .card-icon.red { background: linear-gradient(135deg, var(--error-500), var(--error-600)); color: white; }
        .card-icon.yellow { background: linear-gradient(135deg, var(--accent-500), var(--accent-600)); color: white; }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--neutral-900);
        }
        
        .card-subtitle {
            font-size: 0.875rem;
            color: var(--neutral-500);
            margin-top: 0.25rem;
        }
        
        .card-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .card-stat {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .card-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--neutral-900);
        }
        
        .card-stat-label {
            font-size: 0.75rem;
            color: var(--neutral-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .card-progress {
            margin: 1rem 0;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--neutral-200);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
            border-radius: var(--radius-full);
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        /* Modern Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: var(--neutral-100);
            color: var(--neutral-700);
            border: 1px solid var(--neutral-200);
        }
        
        .btn-secondary:hover {
            background: var(--neutral-200);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success-500), var(--success-600));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning-500), var(--warning-600));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--error-500), var(--error-600));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Popups */
        .popup {
            position: absolute;
            top: 80px;
            right: 20px;
            width: 320px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-2xl);
            padding: 1.5rem;
            display: none;
            z-index: 1001;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .popup h3 {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--neutral-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification-item {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--neutral-200);
        }
        
        .notification-item:hover {
            background: var(--primary-50);
            border-color: var(--primary-200);
        }
        
        .notification-item.unread {
            background: var(--primary-50);
            border-color: var(--primary-200);
            font-weight: 600;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: 0.25rem;
        }
        
        .notification-message {
            font-size: 0.875rem;
            color: var(--neutral-600);
            margin-bottom: 0.5rem;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: var(--neutral-500);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            margin: 5% auto;
            padding: 2rem;
            border-radius: var(--radius-2xl);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-2xl);
            border: 1px solid var(--neutral-200);
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-full);
            background: var(--neutral-100);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .modal-close:hover {
            background: var(--error-100);
            color: var(--error-600);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 1rem;
            }
            
            .navbar-center {
                display: none;
            }
            
            .sidebar {
                width: 100%;
                max-width: 300px;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .hero-section {
                padding: 2rem 1.5rem;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .card {
                padding: 1.25rem;
            }
            
            .popup {
                width: calc(100vw - 2rem);
                right: 1rem;
                left: 1rem;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .slide-in {
            animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        /* Loading Spinner */
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--neutral-200);
            border-top: 4px solid var(--primary-500);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Utility Classes */
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .hidden { display: none !important; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .gap-2 { gap: 0.5rem; }
        .gap-4 { gap: 1rem; }
        .text-center { text-align: center; }
        .w-full { width: 100%; }
        .h-full { height: 100%; }
    </style>
</head>
<body data-theme="light">
    <!-- Global Theme Manager -->
    <script src="../assets/js/theme-manager.js"></script>
    <!-- Modern Navigation -->
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="logo">U</div>
            <span>UNILIS</span>
        </div>
        
        <div class="navbar-center">
            <span class="material-symbols-outlined" style="font-size: 1.25rem; color: var(--primary-600);">school</span>
            <span style="font-weight: 600; color: var(--primary-700);"><?= htmlspecialchars($student['name']) ?></span>
        </div>
        
        <div class="navbar-actions">
            <button class="nav-button" id="mobileMenuToggle">
                <span class="material-symbols-outlined">menu</span>
            </button>
            
            <button class="nav-button" id="notifications-icon">
                <span class="material-symbols-outlined">notifications</span>
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                <?php endif; ?>
            </button>
            
            <button class="nav-button" id="profile-icon">
                <span class="material-symbols-outlined">account_circle</span>
            </button>
        </div>
    </nav>
    
    <!-- Modern Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <h4>Main Navigation</h4>
            <a href="dashboard.php" class="sidebar-item active">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a href="course_view.php" class="sidebar-item">
                <span class="material-symbols-outlined">school</span>
                <span>Training</span>
            </a>
            <a href="take_assessment.php" class="sidebar-item">
                <span class="material-symbols-outlined">assignment</span>
                <span>Exams</span>
            </a>
            <a href="take_assignment.php" class="sidebar-item">
                <span class="material-symbols-outlined">upload_file</span>
                <span>Assignments</span>
            </a>
            <div class="sidebar-item" onclick="showModal('studentAttendanceModal')">
                <span class="material-symbols-outlined">check_circle</span>
                <span>Attendance</span>
            </div>
            <a href="file_requests.php" class="sidebar-item">
                <span class="material-symbols-outlined">folder</span>
                <span>File Requests</span>
            </a>
            <a href="my_progress.php" class="sidebar-item">
                <span class="material-symbols-outlined">trending_up</span>
                <span>My Progress</span>
            </a>
            <a href="../teams/views/create_team.php" class="sidebar-item">
                <span class="material-symbols-outlined">groups</span>
                <span>Create Team</span>
            </a>
            <a href="../chat/views/chat.php" class="sidebar-item">
                <span class="material-symbols-outlined">chat</span>
                <span>Chat</span>
                <span class="chat-nav-badge" id="chatNavBadge" hidden>0</span>
            </a>
            <a href="my_units.php" class="sidebar-item">
                <span class="material-symbols-outlined">book</span>
                <span>My Units</span>
            </a>
            <a href="../learn/" class="sidebar-item">
                <span class="material-symbols-outlined">school</span>
                <span>Short Courses</span>
            </a>
        </div>
        
        <div class="sidebar-section">
            <h4>Account</h4>
            <a href="profile.php" class="sidebar-item">
                <span class="material-symbols-outlined">person</span>
                <span>Profile</span>
            </a>
            <a href="#" class="sidebar-item">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
            <div class="sidebar-item" onclick="logout()">
                <span class="material-symbols-outlined">logout</span>
                <span>Logout</span>
            </div>
        </div>
    </aside>
    
    <!-- Profile Popup -->
    <div class="popup" id="profile-popup">
        <h3>
            <span class="material-symbols-outlined">person</span>
            Profile
        </h3>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-500), var(--secondary-500)); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: 700;">
                <?= strtoupper(substr(htmlspecialchars($student['name']), 0, 2)) ?>
            </div>
            <div>
                <div style="font-weight: 700; color: var(--neutral-900);"><?= htmlspecialchars($student['name']) ?></div>
                <div style="font-size: 0.875rem; color: var(--neutral-500);"><?= htmlspecialchars($student['reg_no']) ?></div>
            </div>
        </div>
        <div style="space-y: 0.5rem;">
            <p style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span class="material-symbols-outlined" style="font-size: 1.25rem; color: var(--neutral-400);">email</span>
                <span><?= htmlspecialchars($student['email']) ?></span>
            </p>
            <p style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span class="material-symbols-outlined" style="font-size: 1.25rem; color: var(--neutral-400);">school</span>
                <span><?= htmlspecialchars($course_name) ?></span>
            </p>
            <p style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="material-symbols-outlined" style="font-size: 1.25rem; color: var(--neutral-400);">calendar_today</span>
                <span>Year <?= htmlspecialchars($student['year_of_study']) ?> • Joined <?= htmlspecialchars($student['year_joined']) ?></span>
            </p>
        </div>
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--neutral-200); display: flex; gap: 0.75rem;">
            <a href="my_progress.php" class="btn btn-primary flex-1">
                <span class="material-symbols-outlined">trending_up</span>
                Progress
            </a>
            <a href="../logout.php" class="btn btn-danger flex-1">
                <span class="material-symbols-outlined">logout</span>
                Logout
            </a>
        </div>
    </div>
    
    <!-- Notifications Popup -->
    <div class="popup" id="notifications-content">
        <h3>
            <span class="material-symbols-outlined">notifications</span>
            Notifications
        </h3>
        <div id="notif-list">
            <?php if(empty($latest_notifications)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--neutral-500);">
                    <span class="material-symbols-outlined" style="font-size: 3rem; display: block; margin-bottom: 1rem;">notifications_off</span>
                    No notifications yet
                </div>
            <?php else: ?>
                <?php foreach($latest_notifications as $notif): ?>
                    <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" id="quick-notif-<?= $notif['id'] ?>" onclick="quickMarkRead(<?= $notif['id'] ?>)">
                        <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                        <div class="notification-message"><?= htmlspecialchars(substr($notif['message'], 0, 80)) ?>...</div>
                        <div class="notification-time">
                            <?php
                                $time = strtotime($notif['created_at']);
                                $now  = time();
                                $diff = $now - $time;
                                if ($diff < 60)        echo "Just now";
                                elseif ($diff < 3600)  echo floor($diff / 60) . "m ago";
                                elseif ($diff < 86400) echo floor($diff / 3600) . "h ago";
                                else                   echo date('M d', $time);
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--neutral-200);">
            <a href="notifications.php" class="btn btn-primary w-full">
                <span class="material-symbols-outlined">arrow_forward</span>
                View All Notifications
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero-section fade-in">
            <div class="hero-content">
                <h1 class="hero-title">Welcome back, <?= htmlspecialchars($student['name']) ?>! 👋</h1>
                <p class="hero-subtitle">Ready to continue your learning journey? Let's make today productive!</p>
                
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="hero-stat-value">8</div>
                        <div class="hero-stat-label">Active Units</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">75%</div>
                        <div class="hero-stat-label">Progress</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">4.2</div>
                        <div class="hero-stat-label">GPA</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">12</div>
                        <div class="hero-stat-label">Achievements</div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Notes Card -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-icon blue">
                        <span class="material-symbols-outlined">menu_book</span>
                    </div>
                    <div>
                        <h3 class="card-title">Notes</h3>
                        <p class="card-subtitle">Semester <?= $semester ?></p>
                    </div>
                </div>
                
                <div class="card-stats">
                    <div class="card-stat">
                        <div class="card-stat-value">8</div>
                        <div class="card-stat-label">Units</div>
                    </div>
                    <div class="card-stat">
                        <div class="card-stat-value">24</div>
                        <div class="card-stat-label">Files</div>
                    </div>
                    <div class="card-stat">
                        <div class="card-stat-value">2</div>
                        <div class="card-stat-label">Pending</div>
                    </div>
                </div>
                
                <div class="card-progress">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.875rem; font-weight: 600;">Progress</span>
                        <span style="font-size: 0.875rem; color: var(--neutral-500);">75%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 75%;"></div>
                    </div>
                </div>
                
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--success-50); border-radius: var(--radius-lg); border-left: 4px solid var(--success-500);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--success-700); font-weight: 500;">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">trending_up</span>
                        Great progress! Keep going! 📚
                    </div>
                </div>
                
                <div class="card-actions">
                    <a href="viewnotes.php" class="btn btn-primary flex-1">
                        <span class="material-symbols-outlined">visibility</span>
                        View Notes
                    </a>
                    <button class="btn btn-secondary">
                        <span class="material-symbols-outlined">download</span>
                        Download
                    </button>
                </div>
            </div>
            
            <!-- Assignments Card -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-icon green">
                        <span class="material-symbols-outlined">assignment</span>
                    </div>
                    <div>
                        <h3 class="card-title">Assignments</h3>
                        <p class="card-subtitle">Semester <?= $semester ?></p>
                    </div>
                </div>
                
                <div class="card-stats">
                    <div class="card-stat">
                        <div class="card-stat-value">7</div>
                        <div class="card-stat-label">Total</div>
                    </div>
                    <div class="card-stat">
                        <div class="card-stat-value" style="color: var(--success-600);">4</div>
                        <div class="card-stat-label">Submitted</div>
                    </div>
                    <div class="card-stat">
                        <div class="card-stat-value" style="color: var(--warning-600);">3</div>
                        <div class="card-stat-label">Pending</div>
                    </div>
                </div>
                
                <div class="card-progress">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.875rem; font-weight: 600;">Completion</span>
                        <span style="font-size: 0.875rem; color: var(--neutral-500);">57%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 57%; background: linear-gradient(90deg, var(--success-500), var(--success-600));"></div>
                    </div>
                </div>
                
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--warning-50); border-radius: var(--radius-lg); border-left: 4px solid var(--warning-500);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--warning-700); font-weight: 500;">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">schedule</span>
                        2 assignments due this week
                    </div>
                </div>
                
                <div class="card-actions">
                    <a href="take_assignment.php" class="btn btn-success flex-1">
                        <span class="material-symbols-outlined">edit</span>
                        View Assignments
                    </a>
                    <button class="btn btn-secondary">
                        <span class="material-symbols-outlined">history</span>
                        History
                    </button>
                </div>
            </div>
            
            <!-- Meetings Card -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-icon purple">
                        <span class="material-symbols-outlined">video_camera_front</span>
                    </div>
                    <div>
                        <h3 class="card-title">Meetings</h3>
                        <p class="card-subtitle">By your enrolled units</p>
                    </div>
                </div>

                <?php if ($totalUpcomingMeetings === 0): ?>
                    <div style="margin: 1.5rem 0; padding: 1.25rem; background: var(--neutral-50); border-radius: var(--radius-lg); text-align: center; color: var(--neutral-500);">
                        <span class="material-symbols-outlined" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">event_busy</span>
                        No upcoming meetings for your enrolled units yet.
                    </div>
                <?php else: ?>
                    <?php if ($nextMeeting): ?>
                        <div style="margin: 1.5rem 0;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: var(--primary-50); border-radius: var(--radius-lg); border-left: 4px solid var(--primary-500);">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-500); display: flex; align-items: center; justify-content: center; color: white;">
                                    <span class="material-symbols-outlined"><?= !empty($nextMeeting['is_live']) ? 'sensors' : 'event' ?></span>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--primary-700); text-transform: uppercase;">
                                        <?= htmlspecialchars($nextMeeting['unit_name']) ?>
                                        <?php if (!empty($nextMeeting['is_live'])): ?>
                                            <span style="background:#16a34a;color:#fff;padding:2px 8px;border-radius:999px;margin-left:6px;">Live</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-weight: 600; color: var(--neutral-900);"><?= htmlspecialchars($nextMeeting['title']) ?></div>
                                    <div style="font-size: 0.875rem; color: var(--neutral-500);">
                                        <?= date('d M Y • h:i A', strtotime($nextMeeting['scheduled_time'])) ?>
                                        <?php if (!empty($nextMeeting['lecturer_name'])): ?>
                                            · <?= htmlspecialchars($nextMeeting['lecturer_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; flex-direction: column; gap: 1rem; margin: 1rem 0; max-height: 320px; overflow-y: auto;">
                        <?php foreach ($studentMeetingsByUnit as $unitGroup): ?>
                            <div style="border: 1px solid var(--neutral-200); border-radius: var(--radius-lg); overflow: hidden;">
                                <div style="padding: 0.75rem 1rem; background: var(--neutral-100); font-weight: 700; color: var(--neutral-800); font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size: 1rem; vertical-align: middle;">menu_book</span>
                                    <?= htmlspecialchars($unitGroup['unit_name']) ?>
                                </div>
                                <?php foreach ($unitGroup['meetings'] as $meetingRow): ?>
                                    <div style="padding: 0.9rem 1rem; border-top: 1px solid var(--neutral-200); display: flex; justify-content: space-between; gap: 0.75rem; align-items: center;">
                                        <div style="min-width: 0;">
                                            <div style="font-weight: 600; color: var(--neutral-900);"><?= htmlspecialchars($meetingRow['title']) ?></div>
                                            <div style="font-size: 0.8rem; color: var(--neutral-500);">
                                                <?= date('d M Y • h:i A', strtotime($meetingRow['scheduled_time'])) ?>
                                                · <?= (int)$meetingRow['duration'] ?> min
                                            </div>
                                        </div>
                                        <?php if (!empty($meetingRow['is_live'])): ?>
                                            <a href="<?= htmlspecialchars($meetingRow['join_url']) ?>" class="btn btn-primary" style="padding: 0.45rem 0.8rem; font-size: 0.8rem; white-space: nowrap;">
                                                Join
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars($meetingRow['join_url']) ?>" class="btn btn-secondary" style="padding: 0.45rem 0.8rem; font-size: 0.8rem; white-space: nowrap;">
                                                Join
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                    <div style="text-align: center; padding: 1rem; background: var(--neutral-50); border-radius: var(--radius-lg);">
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-600);"><?= $totalUpcomingMeetings ?></div>
                        <div style="font-size: 0.75rem; color: var(--neutral-500);">Upcoming</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: var(--neutral-50); border-radius: var(--radius-lg);">
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-600);"><?= $liveMeetingsCount ?></div>
                        <div style="font-size: 0.75rem; color: var(--neutral-500);">Live Now</div>
                    </div>
                </div>

                <div class="card-actions">
                    <?php if ($liveMeetingsCount > 0 && $nextMeeting && !empty($nextMeeting['is_live'])): ?>
                        <a href="<?= htmlspecialchars($nextMeeting['join_url']) ?>" class="btn btn-primary flex-1">
                            <span class="material-symbols-outlined">video_call</span>
                            Join Live Meeting
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary flex-1" disabled style="opacity:0.7;cursor:not-allowed;">
                            <span class="material-symbols-outlined">video_call</span>
                            Join when live
                        </button>
                    <?php endif; ?>
                    <a href="../modules/live-engagement/index.php?page=join" class="btn btn-primary">
                        <span class="material-symbols-outlined">group</span>
                        Join Presentation
                    </a>
                    <a href="my_units.php" class="btn btn-secondary">
                        <span class="material-symbols-outlined">menu_book</span>
                        My Units
                    </a>
                </div>
            </div>
            
            <!-- CATs Card -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-icon red">
                        <span class="material-symbols-outlined">quiz</span>
                    </div>
                    <div>
                        <h3 class="card-title">Online CATs</h3>
                        <p class="card-subtitle">Continuous Assessment</p>
                    </div>
                </div>
                
                <div class="card-stats">
                    <div class="card-stat">
                        <div class="card-stat-value">5</div>
                        <div class="card-stat-label">Available</div>
                    </div>
                    <div class="card-stat">
                        <div class="card-stat-value">2</div>
                        <div class="card-stat-label">Attempted</div>
                    </div>
                    <div class="card-stat">
                        <div class="card-stat-value">80%</div>
                        <div class="card-stat-label">Avg Score</div>
                    </div>
                </div>
                
                <div class="card-progress">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.875rem; font-weight: 600;">Progress</span>
                        <span style="font-size: 0.875rem; color: var(--neutral-500);">40%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 40%; background: linear-gradient(90deg, var(--error-500), var(--error-600));"></div>
                    </div>
                </div>
                
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--error-50); border-radius: var(--radius-lg); border-left: 4px solid var(--error-500);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--error-700); font-weight: 500;">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">psychology</span>
                        Stay sharp! Exams ready 🧠
                    </div>
                </div>
                
                <div class="card-actions">
                    <a href="http://40.125.84.240" class="btn btn-danger flex-1">
                        <span class="material-symbols-outlined">play_arrow</span>
                        Take CAT
                    </a>
                    <button class="btn btn-secondary">
                        <span class="material-symbols-outlined">bar_chart</span>
                        Results
                    </button>
                </div>
            </div>
            
            <!-- Academic Info Card -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-icon yellow">
                        <span class="material-symbols-outlined">school</span>
                    </div>
                    <div>
                        <h3 class="card-title">Academic Info</h3>
                        <p class="card-subtitle">Performance & Progress</p>
                    </div>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="text-align: center; padding: 1rem; background: var(--success-50); border-radius: var(--radius-lg);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-600);">4.2</div>
                            <div style="font-size: 0.75rem; color: var(--neutral-500);">Current GPA</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: var(--primary-50); border-radius: var(--radius-lg);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-600);">B+</div>
                            <div style="font-size: 0.75rem; color: var(--neutral-500);">Grade</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin: 1rem 0;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <span class="material-symbols-outlined" style="color: var(--success-600);">check_circle</span>
                        <span style="font-size: 0.875rem; color: var(--neutral-700);">Results Released</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-outlined" style="color: var(--primary-600);">trending_up</span>
                        <span style="font-size: 0.875rem; color: var(--neutral-700);">GPA Updated</span>
                    </div>
                </div>
                
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--accent-50); border-radius: var(--radius-lg); border-left: 4px solid var(--accent-500);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--accent-700); font-weight: 500;">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">emoji_events</span>
                        Track your academic journey 🎓
                    </div>
                </div>
                
                <div class="card-actions">
                    <a href="my_progress.php" class="btn btn-warning flex-1">
                        <span class="material-symbols-outlined">insights</span>
                        View Details
                    </a>
                    <button class="btn btn-secondary">
                        <span class="material-symbols-outlined">download</span>
                        Transcript
                    </button>
                </div>
            </div>
            
            <!-- Quick Actions Card -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-icon blue">
                        <span class="material-symbols-outlined">apps</span>
                    </div>
                    <div>
                        <h3 class="card-title">Quick Actions</h3>
                        <p class="card-subtitle">Common Tasks</p>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin: 1.5rem 0;">
                    <button class="btn btn-secondary" style="height: 60px; flex-direction: column; gap: 0.5rem;">
                        <span class="material-symbols-outlined">upload_file</span>
                        <span style="font-size: 0.75rem;">Upload</span>
                    </button>
                    <button class="btn btn-secondary" style="height: 60px; flex-direction: column; gap: 0.5rem;">
                        <span class="material-symbols-outlined">chat</span>
                        <span style="font-size: 0.75rem;">Forum</span>
                    </button>
                    <button class="btn btn-secondary" style="height: 60px; flex-direction: column; gap: 0.5rem;">
                        <span class="material-symbols-outlined">calendar_today</span>
                        <span style="font-size: 0.75rem;">Calendar</span>
                    </button>
                    <button class="btn btn-secondary" style="height: 60px; flex-direction: column; gap: 0.5rem;">
                        <span class="material-symbols-outlined">help</span>
                        <span style="font-size: 0.75rem;">Help</span>
                    </button>
                </div>
                
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--primary-50); border-radius: var(--radius-lg); border-left: 4px solid var(--primary-500);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--primary-700); font-weight: 500;">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">tips_and_updates</span>
                        Explore additional tools & resources ⚙️
                    </div>
                </div>
                
                <div class="card-actions">
                    <button class="btn btn-primary w-full">
                        <span class="material-symbols-outlined">explore</span>
                        Explore All Features
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Section -->
        <section style="margin-top: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">Recent Activity</h2>
            <div class="card">
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--neutral-50); border-radius: var(--radius-lg);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--success-100); display: flex; align-items: center; justify-content: center;">
                            <span class="material-symbols-outlined" style="color: var(--success-600);">check_circle</span>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--neutral-900);">Assignment submitted</div>
                            <div style="font-size: 0.875rem; color: var(--neutral-500);">Data Structures - 2 hours ago</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--neutral-50); border-radius: var(--radius-lg);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-100); display: flex; align-items: center; justify-content: center;">
                            <span class="material-symbols-outlined" style="color: var(--primary-600);">video_camera_front</span>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--neutral-900);">Meeting attended</div>
                            <div style="font-size: 0.875rem; color: var(--neutral-500);">Algorithm Class - Yesterday</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--neutral-50); border-radius: var(--radius-lg);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--warning-100); display: flex; align-items: center; justify-content: center;">
                            <span class="material-symbols-outlined" style="color: var(--warning-600);">quiz</span>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--neutral-900);">CAT completed</div>
                            <div style="font-size: 0.875rem; color: var(--neutral-500);">Database Systems - 2 days ago</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <!-- Attendance Modal -->
    <div id="studentAttendanceModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="hideModal('studentAttendanceModal')">
                <span class="material-symbols-outlined">close</span>
            </button>
            
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; text-align: center;">
                <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 0.5rem;">qr_code_scanner</span>
                Mark Your Attendance
            </h2>
            
            <div id="attendanceContent">
                <!-- Loading state -->
                <div id="attendanceLoading" class="hidden" style="text-align: center; padding: 2rem;">
                    <div class="spinner"></div>
                    <p style="margin-top: 1rem; color: var(--neutral-500);">Loading your attendance information...</p>
                </div>
                
                <!-- Attendance form -->
                <div id="attendanceForm" class="hidden">
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--neutral-700);">
                            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 0.5rem;">schedule</span>
                            Active Sessions
                        </label>
                        <div id="activeSessionsList">
                            <!-- Sessions will be loaded here -->
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--neutral-700);">
                            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 0.5rem;">pin</span>
                            Enter Your Personal Code
                        </label>
                        <input type="text" id="attendanceCodeInput" maxlength="6" placeholder="Enter 6-digit code"
                               style="width: 100%; padding: 1rem; border: 2px solid var(--neutral-200); border-radius: var(--radius-lg); font-size: 1.25rem; text-align: center; font-family: monospace; text-transform: uppercase; letter-spacing: 0.1em; transition: border-color 0.2s;">
                        <div style="text-align: center; margin-top: 0.5rem;">
                            <span id="codeTimer" style="font-size: 0.875rem; color: var(--neutral-500);"></span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button onclick="submitAttendanceCode()" class="btn btn-success flex-1">
                            <span class="material-symbols-outlined">check_circle</span>
                            Submit Attendance
                        </button>
                        <button onclick="requestNewCode()" class="btn btn-secondary">
                            <span class="material-symbols-outlined">refresh</span>
                            Request New Code
                        </button>
                    </div>
                </div>
                
                <!-- Success state -->
                <div id="attendanceSuccess" class="hidden" style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--success-100); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <span class="material-symbols-outlined" style="font-size: 3rem; color: var(--success-600);">check_circle</span>
                    </div>
                    <h3 style="font-size: 1.5rem; font-weight: 700; color: var(--success-600); margin-bottom: 1rem;">Attendance Marked!</h3>
                    <p style="color: var(--neutral-600); margin-bottom: 2rem;">Your attendance has been successfully recorded.</p>
                    <button onclick="hideModal('studentAttendanceModal')" class="btn btn-primary">
                        <span class="material-symbols-outlined">close</span>
                        Close
                    </button>
                </div>
                
                <!-- Error state -->
                <div id="attendanceError" class="hidden" style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--error-100); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <span class="material-symbols-outlined" style="font-size: 3rem; color: var(--error-600);">error</span>
                    </div>
                    <h3 style="font-size: 1.5rem; font-weight: 700; color: var(--error-600); margin-bottom: 1rem;">Error</h3>
                    <p id="errorMessage" style="color: var(--neutral-600); margin-bottom: 2rem;"></p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button onclick="resetAttendanceForm()" class="btn btn-secondary">
                            <span class="material-symbols-outlined">arrow_back</span>
                            Try Again
                        </button>
                        <button onclick="requestNewCode()" class="btn btn-primary">
                            <span class="material-symbols-outlined">refresh</span>
                            Request New Code
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        window.attendanceData = { sessions: [], currentSession: null };
        
        // Utility functions
        function logout() {
            window.location.href = "../logout.php";
        }
        
        function showModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Load sessions when attendance modal opens
                if (id === 'studentAttendanceModal') {
                    loadActiveAttendanceSessions();
                }
            }
        }
        
        function hideModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
        
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.getElementById('mobileMenuToggle');
            const profileIcon = document.getElementById('profile-icon');
            const profilePopup = document.getElementById('profile-popup');
            const notifIcon = document.getElementById('notifications-icon');
            const notifContent = document.getElementById('notifications-content');
            
            // Mobile menu toggle
            mobileToggle?.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside
            document.addEventListener('click', (e) => {
                if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
                
                // Close popups
                if (profilePopup && !profilePopup.contains(e.target) && !profileIcon.contains(e.target)) {
                    profilePopup.style.display = 'none';
                }
                
                if (notifContent && !notifContent.contains(e.target) && !notifIcon.contains(e.target)) {
                    notifContent.style.display = 'none';
                }
            });
            
            // Profile popup
            profileIcon?.addEventListener('click', (e) => {
                e.stopPropagation();
                const visible = profilePopup.style.display === 'block';
                profilePopup.style.display = visible ? 'none' : 'block';
                notifContent.style.display = 'none';
            });
            
            // Notifications popup
            notifIcon?.addEventListener('click', (e) => {
                e.stopPropagation();
                const visible = notifContent.style.display === 'block';
                notifContent.style.display = visible ? 'none' : 'block';
                profilePopup.style.display = 'none';
            });
            
            // Modal close on backdrop click
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    hideModal(e.target.id);
                }
            });
            
            // ESC key to close modals
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const openModal = document.querySelector('.modal[style*="block"]');
                    if (openModal) {
                        hideModal(openModal.id);
                    }
                }
            });
            
            // Add animation classes
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            });
            
            document.querySelectorAll('.card').forEach(card => {
                observer.observe(card);
            });
        });
        
        // Attendance System Functions
        function loadActiveAttendanceSessions() {
            showAttendanceLoading();
            
            fetch('get_attendance_sessions.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        attendanceData.sessions = data.sessions;
                        updateSessionsList();
                        hideAllAttendanceStates();
                        document.getElementById('attendanceForm').classList.remove('hidden');
                    } else {
                        showAttendanceError('Failed to load sessions');
                    }
                })
                .catch(error => {
                    console.error('Error loading attendance sessions:', error);
                    showAttendanceError('Network error. Please try again.');
                });
        }
        
        function updateSessionsList() {
            const list = document.getElementById('activeSessionsList');
            if (!list) return;
            
            if (!attendanceData.sessions.length) {
                list.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--neutral-500);"><span class="material-symbols-outlined" style="font-size: 3rem; display: block; margin-bottom: 1rem;">event_busy</span>No active attendance sessions</div>';
                return;
            }
            
            list.innerHTML = attendanceData.sessions.map(session => `
                <div style="padding: 1rem; border: 1px solid var(--neutral-200); border-radius: var(--radius-lg); margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div>
                            <h4 style="font-weight: 600; color: var(--neutral-900);">${session.unit_name}</h4>
                            <p style="font-size: 0.875rem; color: var(--neutral-500);">Session: ${session.main_code}</p>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 0.75rem; color: var(--neutral-500);">Expires: ${new Date(session.deadline).toLocaleString()}</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <button onclick="selectSession(${session.session_id})" class="btn btn-primary">
                            <span class="material-symbols-outlined">check_circle</span>
                            Use This Session
                        </button>
                        ${session.attended 
                            ? '<span style="color: var(--success-600); font-weight: 500;"><span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> Attended</span>'
                            : '<span style="color: var(--warning-600); font-weight: 500;"><span class="material-symbols-outlined" style="vertical-align: middle;">schedule</span> Pending</span>'
                        }
                    </div>
                </div>
            `).join('');
        }
        
        function selectSession(sessionId) {
            const session = attendanceData.sessions.find(s => s.session_id === sessionId);
            if (!session) return;
            
            attendanceData.currentSession = session;
            document.getElementById('attendanceCodeInput').value = '';
            document.getElementById('attendanceCodeInput').focus();
            updateCodeTimer(session.expires_at);
        }
        
        function updateCodeTimer(expiresAt) {
            const timerElement = document.getElementById('codeTimer');
            if (!timerElement) return;
            
            const updateTimer = () => {
                const now = new Date();
                const expires = new Date(expiresAt);
                const diff = expires - now;
                
                if (diff <= 0) {
                    timerElement.innerHTML = '<span style="color: var(--error-600);"><span class="material-symbols-outlined" style="vertical-align: middle;">error</span> EXPIRED</span>';
                    clearInterval(interval);
                } else {
                    const minutes = Math.floor(diff / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    timerElement.innerHTML = `<span class="material-symbols-outlined" style="vertical-align: middle;">schedule</span> ${minutes}:${seconds.toString().padStart(2, '0')} remaining`;
                }
            };
            
            updateTimer();
            const interval = setInterval(updateTimer, 1000);
            
            // Clear interval after 2 minutes
            setTimeout(() => {
                clearInterval(interval);
                timerElement.innerHTML = '<span style="color: var(--error-600);"><span class="material-symbols-outlined" style="vertical-align: middle;">error</span> EXPIRED</span>';
            }, 120000);
        }
        
        function submitAttendanceCode() {
            const code = document.getElementById('attendanceCodeInput').value.trim();
            if (!code) {
                showAttendanceError('Please enter your attendance code');
                return;
            }
            
            if (!attendanceData.currentSession) {
                showAttendanceError('Please select an attendance session first');
                return;
            }
            
            showAttendanceLoading();
            
            const formData = new FormData();
            formData.append('action', 'submit_attendance');
            formData.append('session_id', attendanceData.currentSession.session_id);
            formData.append('code', code);
            
            fetch('attendance_submit.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAttendanceSuccess();
                        const idx = attendanceData.sessions.findIndex(s => s.session_id === attendanceData.currentSession.session_id);
                        if (idx !== -1) attendanceData.sessions[idx].attended = true;
                    } else {
                        showAttendanceError(data.message || 'Invalid code');
                    }
                })
                .catch(() => {
                    showAttendanceError('Network error. Please try again.');
                });
        }
        
        function requestNewCode() {
            if (!attendanceData.currentSession) {
                showAttendanceError('Please select a session first');
                return;
            }
            
            showAttendanceLoading();
            
            const formData = new FormData();
            formData.append('action', 'request_new_code');
            formData.append('session_id', attendanceData.currentSession.session_id);
            
            fetch('attendance_submit.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resetAttendanceForm();
                        if (data.expires_at) updateCodeTimer(data.expires_at);
                    } else {
                        showAttendanceError(data.message || 'Failed to request new code');
                    }
                })
                .catch(() => {
                    showAttendanceError('Network error. Please try again.');
                });
        }
        
        function showAttendanceLoading() {
            hideAllAttendanceStates();
            document.getElementById('attendanceLoading').classList.remove('hidden');
        }
        
        function showAttendanceSuccess() {
            hideAllAttendanceStates();
            document.getElementById('attendanceSuccess').classList.remove('hidden');
        }
        
        function showAttendanceError(message) {
            hideAllAttendanceStates();
            document.getElementById('attendanceError').classList.remove('hidden');
            document.getElementById('errorMessage').textContent = message;
        }
        
        function resetAttendanceForm() {
            hideAllAttendanceStates();
            document.getElementById('attendanceForm').classList.remove('hidden');
            document.getElementById('attendanceCodeInput').value = '';
            document.getElementById('codeTimer').innerHTML = '';
        }
        
        function hideAllAttendanceStates() {
            ['attendanceLoading', 'attendanceForm', 'attendanceSuccess', 'attendanceError'].forEach(id => {
                document.getElementById(id)?.classList.add('hidden');
            });
        }
        
        // Notifications
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
                        item.style.fontWeight = 'normal';
                        item.style.background = 'white';
                        const indicator = item.querySelector('[style*="background: var(--error-500)"]');
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
        
        // Add input focus effects
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = document.querySelectorAll('input[type="text"]');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    input.style.borderColor = 'var(--primary-500)';
                    input.style.outline = 'none';
                    input.style.boxShadow = '0 0 0 3px rgba(14, 165, 233, 0.1)';
                });
                
                input.addEventListener('blur', () => {
                    input.style.borderColor = 'var(--neutral-200)';
                    input.style.boxShadow = 'none';
                });
            });
        });

        // ============================================================
        // AUTO-JOIN MEETING SYSTEM
        // ============================================================
        (function() {
            'use strict';

            // Configuration
            const AUTO_JOIN_CONFIG = {
                pollInterval: 10000,        // Check every 10 seconds
                autoJoinDelay: 3000,        // Wait 3 seconds before auto-joining
                knownLiveMeetings: new Set(), // Track meetings we already know about
                hasAutoJoined: false,        // Prevent multiple auto-joins
                lastCheckTime: null,
                isOnMeetingPage: window.location.pathname.includes('meeting_join.php')
            };

            // Don't run if we're already on a meeting page
            if (AUTO_JOIN_CONFIG.isOnMeetingPage) return;

            // Create the live meeting notification bar
            function createLiveMeetingBar() {
                // Remove existing if any
                const existing = document.getElementById('live-meeting-bar');
                if (existing) existing.remove();

                const bar = document.createElement('div');
                bar.id = 'live-meeting-bar';
                bar.style.cssText = `
                    display: none;
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: linear-gradient(135deg, #16a34a, #15803d);
                    color: white;
                    padding: 12px 24px;
                    z-index: 9999;
                    box-shadow: 0 -4px 20px rgba(0,0,0,0.2);
                    animation: slideUp 0.3s ease-out;
                    font-family: 'Inter', sans-serif;
                `;

                // Add keyframe animation
                if (!document.getElementById('live-meeting-styles')) {
                    const style = document.createElement('style');
                    style.id = 'live-meeting-styles';
                    style.textContent = `
                        @keyframes slideUp {
                            from { transform: translateY(100%); }
                            to { transform: translateY(0); }
                        }
                        @keyframes pulse-green {
                            0%, 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
                            50% { box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); }
                        }
                        .live-dot {
                            display: inline-block;
                            width: 10px;
                            height: 10px;
                            background: #ff4444;
                            border-radius: 50%;
                            animation: pulse-dot 1.5s infinite;
                            margin-right: 8px;
                        }
                        @keyframes pulse-dot {
                            0%, 100% { opacity: 1; transform: scale(1); }
                            50% { opacity: 0.5; transform: scale(1.3); }
                        }
                    `;
                    document.head.appendChild(style);
                }

                bar.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: space-between; max-width: 1200px; margin: 0 auto; gap: 16px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="live-dot"></span>
                            <div>
                                <div style="font-weight: 700; font-size: 15px;" id="live-meeting-title">Meeting is Live!</div>
                                <div style="font-size: 13px; opacity: 0.9;" id="live-meeting-info">Click to join now</div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <span id="auto-join-countdown" style="font-size: 13px; opacity: 0.9; display: none;"></span>
                            <button id="join-live-meeting-btn" style="
                                background: white;
                                color: #16a34a;
                                border: none;
                                padding: 10px 24px;
                                border-radius: 8px;
                                font-weight: 700;
                                font-size: 14px;
                                cursor: pointer;
                                transition: all 0.2s;
                                display: flex;
                                align-items: center;
                                gap: 8px;
                                animation: pulse-green 2s infinite;
                            ">
                                <span>▶</span>
                                Join Meeting Now
                            </button>
                            <button id="dismiss-live-meeting" style="
                                background: rgba(255,255,255,0.2);
                                color: white;
                                border: 1px solid rgba(255,255,255,0.3);
                                padding: 10px 16px;
                                border-radius: 8px;
                                font-size: 13px;
                                cursor: pointer;
                                transition: all 0.2s;
                            ">Dismiss</button>
                        </div>
                    </div>
                `;

                document.body.appendChild(bar);

                // Event listeners
                document.getElementById('join-live-meeting-btn').addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    if (url) window.location.href = url;
                });

                document.getElementById('dismiss-live-meeting').addEventListener('click', function() {
                    document.getElementById('live-meeting-bar').style.display = 'none';
                });

                return bar;
            }

            // Show live meeting bar
            function showLiveMeeting(meeting) {
                const bar = document.getElementById('live-meeting-bar') || createLiveMeetingBar();
                
                document.getElementById('live-meeting-title').textContent = 
                    `🔴 Live: ${meeting.title}`;
                document.getElementById('live-meeting-info').textContent = 
                    `${meeting.unit_name} • ${meeting.lecturer_name || 'Lecture'}`;
                
                const joinBtn = document.getElementById('join-live-meeting-btn');
                joinBtn.setAttribute('data-url', meeting.join_url);
                
                bar.style.display = 'flex';

                // Auto-join countdown
                const countdownEl = document.getElementById('auto-join-countdown');
                countdownEl.style.display = 'inline';
                let countdown = AUTO_JOIN_CONFIG.autoJoinDelay / 1000;
                countdownEl.textContent = `Auto-joining in ${countdown}s...`;

                const countdownInterval = setInterval(() => {
                    countdown--;
                    if (countdown > 0) {
                        countdownEl.textContent = `Auto-joining in ${countdown}s...`;
                    } else {
                        clearInterval(countdownInterval);
                        countdownEl.textContent = 'Joining now...';
                    }
                }, 1000);

                // Auto-join after delay
                setTimeout(() => {
                    if (!AUTO_JOIN_CONFIG.hasAutoJoined) {
                        AUTO_JOIN_CONFIG.hasAutoJoined = true;
                        window.location.href = meeting.join_url;
                    }
                }, AUTO_JOIN_CONFIG.autoJoinDelay);
            }

            // Poll for live meetings
            async function checkLiveMeetings() {
                try {
                    const timestamp = new Date().toISOString();
                    const url = `../api/check_live_meetings.php?t=${encodeURIComponent(timestamp)}`;
                    
                    const response = await fetch(url);
                    const data = await response.json();

                    if (!data.success) return;

                    // Process live meetings
                    if (data.live_meetings && data.live_meetings.length > 0) {
                        const liveMeeting = data.live_meetings[0];
                        
                        // Check if this is a new live meeting we haven't seen
                        if (!AUTO_JOIN_CONFIG.knownLiveMeetings.has(liveMeeting.id)) {
                            AUTO_JOIN_CONFIG.knownLiveMeetings.add(liveMeeting.id);
                            
                            // Show the live meeting bar
                            showLiveMeeting(liveMeeting);
                            
                            // Also trigger a browser notification if permitted
                            if ('Notification' in window && Notification.permission === 'granted') {
                                new Notification('🔴 Meeting is Live!', {
                                    body: `${liveMeeting.title} - ${liveMeeting.unit_name}`,
                                    icon: '../assets/logo.png'
                                });
                            }
                        }
                    }

                    // Update the meetings card on the dashboard to reflect live status
                    updateMeetingsCard(data.live_meetings || []);

                } catch (error) {
                    console.error('Auto-join: Error checking live meetings:', error);
                }
            }

            // Update the meetings card UI
            function updateMeetingsCard(liveMeetings) {
                // Find the meetings card
                const meetingsCard = document.querySelector('.card .card-icon.purple')?.closest('.card');
                if (!meetingsCard) return;

                // Update the live count
                const liveCountEl = meetingsCard.querySelector('.hero-stat-value');
                // Just update the visual - the PHP already handles this on page load
            }

            // Request notification permission
            function requestNotificationPermission() {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            }

            // Initialize auto-join system
            function initAutoJoin() {
                // Create the live meeting bar (hidden initially)
                createLiveMeetingBar();
                
                // Request notification permission
                requestNotificationPermission();

                // Start polling
                checkLiveMeetings(); // Immediate check
                setInterval(checkLiveMeetings, AUTO_JOIN_CONFIG.pollInterval);

                console.log('🔴 Auto-join meeting system initialized (polling every ' + (AUTO_JOIN_CONFIG.pollInterval/1000) + 's)');
            }

            // Start when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initAutoJoin);
            } else {
                initAutoJoin();
            }
        })();
    </script>
    <?php include __DIR__ . '/../chat/includes/nav_badge.php'; ?>
</body>
</html>
