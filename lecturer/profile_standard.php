<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: ../index.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user profile data from existing tables
$profile_data = [];

try {
    if ($user_role === 'student') {
        $sql = "SELECT s.*, c.name as course_name, d.name as department_name, u.name as university_name
                FROM students s
                LEFT JOIN courses c ON s.course_id = c.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN universities u ON s.university_id = u.id
                WHERE s.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile_data = $stmt->get_result()->fetch_assoc();
        
        // Get additional student stats
        $stats_sql = "SELECT 
                        COUNT(DISTINCT su.unit_id) as enrolled_units,
                        COUNT(DISTINCT sub.assignment_id) as assignments_submitted,
                        AVG(sa.marks) as average_marks
                      FROM student_units su
                      LEFT JOIN submissions sub ON su.student_id = sub.student_id
                      LEFT JOIN student_answers sa ON sub.id = sa.submission_id
                      WHERE su.student_id = ?";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param("i", $user_id);
        $stats_stmt->execute();
        $student_stats = $stats_stmt->get_result()->fetch_assoc();
        $profile_data = array_merge($profile_data, $student_stats);
        
    } elseif ($user_role === 'lecturer') {
        $sql = "SELECT l.*, d.name as department_name, u.name as university_name,
                       COUNT(DISTINCT lu.unit_id) as units_count,
                       COUNT(DISTINCT a.id) as assignments_count
                FROM lecturers l
                LEFT JOIN departments d ON l.department_id = d.id
                LEFT JOIN universities u ON l.university_id = u.id
                LEFT JOIN lecturer_units lu ON l.id = lu.lecturer_id
                LEFT JOIN assignments a ON l.id = a.lecturer_id
                WHERE l.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile_data = $stmt->get_result()->fetch_assoc();
        
    } elseif ($user_role === 'admin') {
        $sql = "SELECT a.*, u.name as university_name
                FROM admins a
                LEFT JOIN universities u ON a.university_id = u.id
                WHERE a.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile_data = $stmt->get_result()->fetch_assoc();
    }
    
} catch (Exception $e) {
    error_log("Error fetching profile data: " . $e->getMessage());
    $_SESSION['error'] = "Error loading profile data.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'update_profile') {
            // Sanitize inputs
            $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address");
            }
            
            // Update profile based on role
            if ($user_role === 'student') {
                $sql = "UPDATE students SET name = ?, email = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $name, $email, $user_id);
            } elseif ($user_role === 'lecturer') {
                $sql = "UPDATE lecturers SET name = ?, email = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $name, $email, $user_id);
            } elseif ($user_role === 'admin') {
                $sql = "UPDATE admins SET name = ?, email = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $name, $email, $user_id);
            }
            
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            
        } elseif ($_POST['action'] === 'upload_image') {
            // Handle image upload (simplified version)
            if (!isset($_FILES['profile_image'])) {
                throw new Exception("No file uploaded");
            }
            
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WebP are allowed");
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception("File size too large. Maximum size is 5MB");
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $upload_path = '../uploads/profile_pictures/' . $filename;
            
            // Create directory if it doesn't exist
            if (!file_exists('../uploads/profile_pictures')) {
                mkdir('../uploads/profile_pictures', 0755, true);
            }
            
            // Move file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload file");
            }
            
            // Store image info in session (simplified approach)
            $_SESSION['profile_picture'] = $filename;
            
            echo json_encode(['success' => true, 'message' => 'Profile picture updated', 'filename' => $filename]);
            
        } else {
            throw new Exception("Invalid action");
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - UNILIS Academic System</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <style>
        :root {
            /* Academic Color Palette */
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
        
        /* Navigation */
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
        
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .nav-button {
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
        
        /* Sidebar */
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
        
        /* Main Content */
        .main-content {
            margin-left: 0;
            margin-top: 72px;
            padding: 2rem;
            min-height: calc(100vh - 72px);
        }
        
        /* Profile Container */
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
        }
        
        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .profile-avatar-section {
            position: relative;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 700;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-full);
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 36px;
            height: 36px;
            background: var(--primary-500);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: var(--shadow-md);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .avatar-upload:hover {
            background: var(--primary-600);
            transform: scale(1.1);
        }
        
        .avatar-upload input {
            display: none;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 800;
            color: var(--neutral-900);
            margin-bottom: 0.5rem;
        }
        
        .profile-role {
            font-size: 1.125rem;
            color: var(--primary-600);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .profile-stat {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .profile-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--neutral-900);
        }
        
        .profile-stat-label {
            font-size: 0.875rem;
            color: var(--neutral-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Profile Sections */
        .profile-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .profile-section {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .profile-section::before {
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
        
        .profile-section:hover::before {
            transform: scaleX(1);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--neutral-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .section-icon.blue { background: linear-gradient(135deg, var(--primary-500), var(--primary-600)); color: white; }
        .section-icon.green { background: linear-gradient(135deg, var(--success-500), var(--success-600)); color: white; }
        .section-icon.purple { background: linear-gradient(135deg, var(--secondary-500), var(--secondary-600)); color: white; }
        .section-icon.orange { background: linear-gradient(135deg, var(--accent-500), var(--accent-600)); color: white; }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--neutral-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--neutral-200);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        
        .form-input:disabled {
            background: var(--neutral-50);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        /* Buttons */
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
        
        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 100px;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 300px;
            transform: translateX(400px);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: var(--success-500);
            color: white;
        }
        
        .toast.error {
            background: #ef4444;
            color: white;
        }
        
        .toast.info {
            background: var(--primary-500);
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 1rem;
            }
            
            .sidebar {
                width: 100%;
                max-width: 300px;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .profile-header-content {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .profile-stats {
                justify-content: center;
                gap: 1rem;
            }
            
            .profile-sections {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .profile-section {
                padding: 1.5rem;
            }
            
            .toast {
                right: 1rem;
                left: 1rem;
                min-width: auto;
            }
        }
        
        /* Utility Classes */
        .hidden { display: none !important; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .gap-2 { gap: 0.5rem; }
        .gap-4 { gap: 1rem; }
        .text-center { text-align: center; }
        .w-full { width: 100%; }
        .h-full { height: 100%; }
        
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="logo">U</div>
            <span>UNILIS</span>
        </div>
        
        <div class="navbar-actions">
            <button class="nav-button" id="mobileMenuToggle">
                <span class="material-symbols-outlined">menu</span>
            </button>
            
            <button class="nav-button" onclick="window.history.back()">
                <span class="material-symbols-outlined">arrow_back</span>
            </button>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <?php
        $sidebar_links = [];
        if ($user_role === 'student') {
            $sidebar_links = [
                ['../student/dashboard.php', 'dashboard', 'Dashboard'],
                ['../student/course_view.php', 'school', 'Training'],
                ['../student/take_assessment.php', 'assignment', 'Exams'],
                ['../student/take_assignment.php', 'upload_file', 'Assignments'],
                ['../student/my_progress.php', 'trending_up', 'My Progress'],
                ['../student/profile.php', 'person', 'Profile'],
            ];
        } elseif ($user_role === 'lecturer') {
            $sidebar_links = [
                ['dashboard.php', 'dashboard', 'Dashboard'],
                ['course_builder.php', 'school', 'Course Builder'],
                ['lesson_editor.php', 'menu_book', 'Lesson Editor'],
                ['assignment_submissions.php', 'inbox', 'Assignment Submissions'],
                ['submissions.php', 'assignment', 'Assessment Submissions'],
                ['../teams/views/lecturer_teams.php', 'groups', 'Lecturer Teams'],
                ['request_files.php', 'folder', 'Request Files'],
                ['attendance.php', 'check_circle', 'Attendance'],
                ['profile.php', 'person', 'Profile'],
            ];
        } elseif ($user_role === 'admin') {
            $sidebar_links = [
                ['dashboard.php', 'dashboard', 'Dashboard'],
                ['users.php', 'people', 'Users'],
                ['courses.php', 'school', 'Courses'],
                ['profile.php', 'person', 'Profile'],
                ['settings.php', 'settings', 'Settings'],
            ];
        }
        
        foreach ($sidebar_links as $link) {
            $active = basename($_SERVER['PHP_SELF']) === $link[0] ? 'active' : '';
            echo "<a href='{$link[0]}' class='sidebar-item {$active}'>";
            echo "<span class='material-symbols-outlined'>{$link[1]}</span>";
            echo "<span>{$link[2]}</span>";
            echo "</a>";
        }
        ?>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-header-content">
                    <div class="profile-avatar-section">
                        <div class="profile-avatar" id="profileAvatar">
                            <?php
                            if (isset($_SESSION['profile_picture']) && file_exists('../uploads/profile_pictures/' . $_SESSION['profile_picture'])) {
                                echo "<img src='../uploads/profile_pictures/" . htmlspecialchars($_SESSION['profile_picture']) . "' alt='Profile Picture'>";
                            } else {
                                echo strtoupper(substr(htmlspecialchars($profile_data['name'] ?? 'U'), 0, 2));
                            }
                            ?>
                        </div>
                        <label class="avatar-upload" id="avatarUploadBtn">
                            <span class="material-symbols-outlined" style="font-size: 1.25rem;">camera_alt</span>
                            <input type="file" id="profileImageInput" accept="image/*">
                        </label>
                    </div>
                    
                    <div class="profile-info">
                        <h1 class="profile-name"><?= htmlspecialchars($profile_data['name'] ?? 'User') ?></h1>
                        <div class="profile-role">
                            <span class="material-symbols-outlined">
                                <?php
                                switch($user_role) {
                                    case 'student': echo 'school'; break;
                                    case 'lecturer': echo 'psychology'; break;
                                    case 'admin': echo 'admin_panel_settings'; break;
                                }
                                ?>
                            </span>
                            <?= ucfirst($user_role) ?>
                        </div>
                        
                        <div class="profile-stats">
                            <?php if ($user_role === 'student'): ?>
                                <div class="profile-stat">
                                    <div class="profile-stat-value"><?= $profile_data['year_of_study'] ?? 'N/A' ?></div>
                                    <div class="profile-stat-label">Year</div>
                                </div>
                                <div class="profile-stat">
                                    <div class="profile-stat-value"><?= $profile_data['enrolled_units'] ?? '0' ?></div>
                                    <div class="profile-stat-label">Units</div>
                                </div>
                                <div class="profile-stat">
                                    <div class="profile-stat-value"><?= $profile_data['assignments_submitted'] ?? '0' ?></div>
                                    <div class="profile-stat-label">Assignments</div>
                                </div>
                            <?php elseif ($user_role === 'lecturer'): ?>
                                <div class="profile-stat">
                                    <div class="profile-stat-value"><?= $profile_data['units_count'] ?? '0' ?></div>
                                    <div class="profile-stat-label">Units</div>
                                </div>
                                <div class="profile-stat">
                                    <div class="profile-stat-value"><?= $profile_data['assignments_count'] ?? '0' ?></div>
                                    <div class="profile-stat-label">Assignments</div>
                                </div>
                                <div class="profile-stat">
                                    <div class="profile-stat-value"><?= $profile_data['department_name'] ?? 'N/A' ?></div>
                                    <div class="profile-stat-label">Department</div>
                                </div>
                            <?php elseif ($user_role === 'admin'): ?>
                                <div class="profile-stat">
                                    <div class="profile-stat-value">System</div>
                                    <div class="profile-stat-label">Scope</div>
                                </div>
                                <div class="profile-stat">
                                    <div class="profile-stat-value">Full</div>
                                    <div class="profile-stat-label">Access</div>
                                </div>
                                <div class="profile-stat">
                                    <div class="profile-stat-value"><?= $profile_data['university_name'] ?? 'N/A' ?></div>
                                    <div class="profile-stat-label">University</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button class="btn btn-primary" id="editProfileBtn">
                            <span class="material-symbols-outlined">edit</span>
                            Edit Profile
                        </button>
                        <button class="btn btn-secondary" id="cancelEditBtn" style="display: none;">
                            <span class="material-symbols-outlined">close</span>
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Profile Sections -->
            <div class="profile-sections">
                <!-- Basic Information -->
                <div class="profile-section">
                    <div class="section-header">
                        <div class="section-title">
                            <div class="section-icon blue">
                                <span class="material-symbols-outlined">person</span>
                            </div>
                            Basic Information
                        </div>
                    </div>
                    
                    <form id="profileForm">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-input" id="fullName" name="name" 
                                   value="<?= htmlspecialchars($profile_data['name'] ?? '') ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input" id="email" name="email" 
                                   value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>" readonly>
                        </div>
                        
                        <?php if ($user_role === 'student'): ?>
                            <div class="form-group">
                                <label class="form-label">Registration Number</label>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['reg_no'] ?? '') ?>" readonly>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">University</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['university_name'] ?? 'N/A') ?>" readonly>
                        </div>
                    </form>
                </div>
                
                <!-- Academic/Professional Information -->
                <div class="profile-section">
                    <div class="section-header">
                        <div class="section-title">
                            <div class="section-icon green">
                                <span class="material-symbols-outlined">
                                    <?php
                                    switch($user_role) {
                                        case 'student': echo 'school'; break;
                                        case 'lecturer': echo 'psychology'; break;
                                        case 'admin': echo 'admin_panel_settings'; break;
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php
                            switch($user_role) {
                                case 'student': echo 'Academic Information'; break;
                                case 'lecturer': echo 'Professional Information'; break;
                                case 'admin': echo 'System Information'; break;
                            }
                            ?>
                        </div>
                    </div>
                    
                    <?php if ($user_role === 'student'): ?>
                        <div class="form-group">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['course_name'] ?? 'N/A') ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['department_name'] ?? 'N/A') ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year of Study</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['year_of_study'] ?? 'N/A') ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year Joined</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['year_joined'] ?? 'N/A') ?>" readonly>
                        </div>
                        
                    <?php elseif ($user_role === 'lecturer'): ?>
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['department_name'] ?? 'N/A') ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Units Teaching</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['units_count'] ?? '0') ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Assignments Created</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['assignments_count'] ?? '0') ?>" readonly>
                        </div>
                        
                    <?php elseif ($user_role === 'admin'): ?>
                        <div class="form-group">
                            <label class="form-label">Admin ID</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['id'] ?? 'N/A') ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Access Level</label>
                            <input type="text" class="form-input" value="Full Administrator" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">System Scope</label>
                            <input type="text" class="form-input" value="University-wide" readonly>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Save Button (shown in edit mode) -->
            <div class="profile-section" id="saveSection" style="display: none; margin-top: 2rem;">
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button class="btn btn-success" id="saveProfileBtn">
                        <span class="material-symbols-outlined">save</span>
                        Save Changes
                    </button>
                    <button class="btn btn-secondary" id="cancelEditBtn2">
                        <span class="material-symbols-outlined">close</span>
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Toast Container -->
    <div id="toastContainer"></div>
    
    <script>
        // Global variables
        let isEditMode = false;
        
        // Utility functions
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <span class="material-symbols-outlined">
                    ${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}
                </span>
                <span>${message}</span>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Toggle edit mode
        function toggleEditMode(enable) {
            isEditMode = enable;
            const formInputs = document.querySelectorAll('.form-input');
            const editBtn = document.getElementById('editProfileBtn');
            const cancelBtns = document.querySelectorAll('[id^="cancelEditBtn"]');
            const saveBtn = document.getElementById('saveProfileBtn');
            const saveSection = document.getElementById('saveSection');
            
            formInputs.forEach(input => {
                if (enable) {
                    input.removeAttribute('readonly');
                    input.style.background = 'white';
                    input.style.borderColor = 'var(--primary-300)';
                } else {
                    input.setAttribute('readonly', true);
                    input.style.background = 'var(--neutral-50)';
                    input.style.borderColor = 'var(--neutral-200)';
                }
            });
            
            if (enable) {
                editBtn.style.display = 'none';
                cancelBtns.forEach(btn => btn.style.display = 'inline-flex');
                saveBtn.style.display = 'inline-flex';
                saveSection.style.display = 'block';
            } else {
                editBtn.style.display = 'inline-flex';
                cancelBtns.forEach(btn => btn.style.display = 'none');
                saveBtn.style.display = 'none';
                saveSection.style.display = 'none';
            }
        }
        
        // Save profile
        async function saveProfile() {
            const formData = new FormData();
            formData.append('action', 'update_profile');
            
            // Add form data
            document.querySelectorAll('#profileForm input').forEach(input => {
                if (input.name) {
                    formData.append(input.name, input.value);
                }
            });
            
            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Profile updated successfully!', 'success');
                    toggleEditMode(false);
                    
                    // Update display name
                    document.querySelector('.profile-name').textContent = document.getElementById('fullName').value;
                } else {
                    showToast(result.message || 'Failed to update profile', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Upload profile image
        async function uploadProfileImage(file) {
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('profile_image', file);
            
            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Profile picture updated!', 'success');
                    // Update avatar
                    const avatar = document.getElementById('profileAvatar');
                    avatar.innerHTML = `<img src="../uploads/profile_pictures/${result.filename}" alt="Profile Picture">`;
                } else {
                    showToast(result.message || 'Failed to upload image', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', () => {
            // Mobile menu toggle
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.getElementById('mobileMenuToggle');
            
            mobileToggle?.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside
            document.addEventListener('click', (e) => {
                if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
            
            // Edit profile buttons
            document.getElementById('editProfileBtn').addEventListener('click', () => {
                toggleEditMode(true);
            });
            
            document.querySelectorAll('[id^="cancelEditBtn"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    toggleEditMode(false);
                    // Reset form values
                    location.reload();
                });
            });
            
            // Save profile button
            document.getElementById('saveProfileBtn').addEventListener('click', saveProfile);
            
            // Profile image upload
            const avatarUploadBtn = document.getElementById('avatarUploadBtn');
            const profileImageInput = document.getElementById('profileImageInput');
            
            avatarUploadBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                profileImageInput.click();
            });
            
            profileImageInput?.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    uploadProfileImage(file);
                }
            });
            
            // Form validation
            document.getElementById('email')?.addEventListener('blur', (e) => {
                const email = e.target.value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    e.target.setCustomValidity('Please enter a valid email address');
                    showToast('Please enter a valid email address', 'error');
                } else {
                    e.target.setCustomValidity('');
                }
            });
        });
    </script>
</body>
</html>
