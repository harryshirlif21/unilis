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

// Get comprehensive user profile data
$profile_data = [];
$analytics_data = [];
$activity_data = [];
$user_settings = [];

try {
    if ($user_role === 'student') {
        // First get basic student info
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
        
        // Get analytics data separately
        $analytics_sql = "SELECT 
            COUNT(DISTINCT su.unit_id) as courses_enrolled,
            COUNT(DISTINCT sub.id) as assignments_completed,
            COUNT(DISTINCT n.id) as notifications,
            COALESCE(AVG(sa.marks), 0) as performance_score
            FROM students s
            LEFT JOIN student_units su ON s.id = su.student_id
            LEFT JOIN submissions sub ON s.id = sub.student_id
            LEFT JOIN notifications n ON s.id = n.user_id AND n.user_role = 'student'
            LEFT JOIN student_answers sa ON sub.id = sa.submission_id
            WHERE s.id = ?";
        $analytics_stmt = $conn->prepare($analytics_sql);
        $analytics_stmt->bind_param("i", $user_id);
        $analytics_stmt->execute();
        $analytics_data = $analytics_stmt->get_result()->fetch_assoc();
        
        // Merge analytics data with profile data
        if ($analytics_data) {
            $profile_data = array_merge($profile_data, $analytics_data);
        }
        
        // Get recent activity
        $activity_sql = "SELECT 'Assignment Submitted' as activity, a.title as details, sub.created_at as date
                         FROM submissions sub
                         LEFT JOIN assignments a ON sub.assignment_id = a.id
                         WHERE sub.student_id = ?
                         ORDER BY sub.created_at DESC
                         LIMIT 5";
        $activity_stmt = $conn->prepare($activity_sql);
        $activity_stmt->bind_param("i", $user_id);
        $activity_stmt->execute();
        $activity_result = $activity_stmt->get_result();
        while ($row = $activity_result->fetch_assoc()) {
            $activity_data[] = $row;
        }
        
    } elseif ($user_role === 'lecturer') {
        $sql = "SELECT l.*, d.name as department_name, u.name as university_name,
                       COUNT(DISTINCT lu.unit_id) as courses_teaching,
                       COUNT(DISTINCT a.id) as assignments_created,
                       COUNT(DISTINCT n.id) as notifications,
                       COUNT(DISTINCT sub.id) as total_submissions
                FROM lecturers l
                LEFT JOIN departments d ON l.department_id = d.id
                LEFT JOIN universities u ON l.university_id = u.id
                LEFT JOIN lecturer_units lu ON l.id = lu.lecturer_id
                LEFT JOIN assignments a ON l.id = a.lecturer_id
                LEFT JOIN notifications n ON l.id = n.user_id AND n.user_role = 'lecturer'
                LEFT JOIN submissions sub ON a.id = sub.assignment_id
                WHERE l.id = ?
                GROUP BY l.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile_data = $stmt->get_result()->fetch_assoc();
        
        // Get recent activity
        $activity_sql = "SELECT 'Assignment Created' as activity, a.title as details, a.created_at as date
                         FROM assignments a
                         WHERE a.lecturer_id = ?
                         ORDER BY a.created_at DESC
                         LIMIT 5";
        $activity_stmt = $conn->prepare($activity_sql);
        $activity_stmt->bind_param("i", $user_id);
        $activity_stmt->execute();
        $activity_result = $activity_stmt->get_result();
        while ($row = $activity_result->fetch_assoc()) {
            $activity_data[] = $row;
        }
        
    } elseif ($user_role === 'admin') {
        $sql = "SELECT a.*, u.name as university_name,
                       COUNT(DISTINCT s.id) as total_students,
                       COUNT(DISTINCT l.id) as total_lecturers,
                       COUNT(DISTINCT c.id) as total_courses,
                       COUNT(DISTINCT n.id) as notifications
                FROM admins a
                LEFT JOIN universities u ON a.university_id = u.id
                LEFT JOIN students s ON 1=1
                LEFT JOIN lecturers l ON 1=1
                LEFT JOIN courses c ON 1=1
                LEFT JOIN notifications n ON a.id = n.user_id AND n.user_role = 'admin'
                WHERE a.id = ?
                GROUP BY a.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile_data = $stmt->get_result()->fetch_assoc();
        
        // Get recent activity
        $activity_data = [
            ['activity' => 'System Update', 'details' => 'Database maintenance completed', 'date' => date('Y-m-d H:i:s')],
            ['activity' => 'User Management', 'details' => 'New user registrations approved', 'date' => date('Y-m-d H:i:s', strtotime('-1 day'))],
            ['activity' => 'Security Check', 'details' => 'Security audit completed', 'date' => date('Y-m-d H:i:s', strtotime('-2 days'))]
        ];
    }
    
    // Debug: Add some debug information
    error_log("User ID: $user_id, User Role: $user_role");
    error_log("Profile Data: " . print_r($profile_data, true));
    
    // Get user settings
    $settings_sql = "SELECT theme, language, timezone, notifications_enabled, email_notifications, 
                    two_factor_enabled, privacy_profile_visible, privacy_show_email, privacy_show_phone
                    FROM user_settings WHERE user_id = ? AND user_role = ?";
    $settings_stmt = $conn->prepare($settings_sql);
    $settings_stmt->bind_param("is", $user_id, $user_role);
    $settings_stmt->execute();
    $user_settings = $settings_stmt->get_result()->fetch_assoc();
    
    // Default settings if none exist
    if (!$user_settings) {
        $user_settings = [
            'theme' => 'light',
            'language' => 'en',
            'timezone' => 'Africa/Nairobi',
            'notifications_enabled' => 1,
            'email_notifications' => 1,
            'two_factor_enabled' => 0,
            'privacy_profile_visible' => 1,
            'privacy_show_email' => 0,
            'privacy_show_phone' => 0
        ];
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
            $name = filter_var($_POST['name'] ?? '', FILTER_SANITIZE_STRING);
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $phone = filter_var($_POST['phone'] ?? '', FILTER_SANITIZE_STRING);
            $bio = filter_var($_POST['bio'] ?? '', FILTER_SANITIZE_STRING);
            $date_of_birth = filter_var($_POST['date_of_birth'] ?? '', FILTER_SANITIZE_STRING);
            $address = filter_var($_POST['address'] ?? '', FILTER_SANITIZE_STRING);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address");
            }
            
            if ($user_role === 'student') {
                $sql = "UPDATE students SET name = ?, email = ?, phone = ?, bio = ?, date_of_birth = ?, address = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $name, $email, $phone, $bio, $date_of_birth, $address, $user_id);
            } elseif ($user_role === 'lecturer') {
                $sql = "UPDATE lecturers SET name = ?, email = ?, phone = ?, bio = ?, date_of_birth = ?, address = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $name, $email, $phone, $bio, $date_of_birth, $address, $user_id);
            } elseif ($user_role === 'admin') {
                $sql = "UPDATE admins SET name = ?, email = ?, phone = ?, bio = ?, date_of_birth = ?, address = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $name, $email, $phone, $bio, $date_of_birth, $address, $user_id);
            }
            
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            
        } elseif ($_POST['action'] === 'update_settings') {
            $theme = filter_var($_POST['theme'] ?? 'dark', FILTER_SANITIZE_STRING);
            $language = filter_var($_POST['language'] ?? 'en', FILTER_SANITIZE_STRING);
            $timezone = filter_var($_POST['timezone'] ?? 'Africa/Nairobi', FILTER_SANITIZE_STRING);
            $notifications_enabled = isset($_POST['notifications_enabled']) ? 1 : 0;
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $privacy_profile_visible = isset($_POST['privacy_profile_visible']) ? 1 : 0;
            $privacy_show_email = isset($_POST['privacy_show_email']) ? 1 : 0;
            $privacy_show_phone = isset($_POST['privacy_show_phone']) ? 1 : 0;
            
            // Update or insert settings
            $sql = "INSERT INTO user_settings (user_id, user_role, theme, language, timezone, 
                    notifications_enabled, email_notifications, privacy_profile_visible, privacy_show_email, privacy_show_phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE theme = VALUES(theme), language = VALUES(language), 
                    timezone = VALUES(timezone), notifications_enabled = VALUES(notifications_enabled),
                    email_notifications = VALUES(email_notifications), privacy_profile_visible = VALUES(privacy_profile_visible),
                    privacy_show_email = VALUES(privacy_show_email), privacy_show_phone = VALUES(privacy_show_phone)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssiiiii", $user_id, $user_role, $theme, $language, $timezone, 
                              $notifications_enabled, $email_notifications, $privacy_profile_visible, 
                              $privacy_show_email, $privacy_show_phone);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
            
        } elseif ($_POST['action'] === 'upload_image') {
            if (!isset($_FILES['profile_image'])) {
                throw new Exception("No file uploaded");
            }
            
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024;
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Invalid file type");
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception("File size too large");
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $upload_path = '../uploads/profile_pictures/' . $filename;
            
            if (!file_exists('../uploads/profile_pictures')) {
                mkdir('../uploads/profile_pictures', 0755, true);
            }
            
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload file");
            }
            
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
    <title>Profile Dashboard - UNILIS</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <style>
        :root {
            /* Dark Theme - 21st.magic Design System */
            --bg-primary: #0a0a0f;
            --bg-secondary: #1a1a2e;
            --bg-tertiary: #16213e;
            --bg-glass: rgba(255, 255, 255, 0.05);
            --bg-glass-hover: rgba(255, 255, 255, 0.08);
            
            --text-primary: #ffffff;
            --text-secondary: #b8b8d1;
            --text-muted: #6b7280;
            
            --accent-primary: #6366f1;
            --accent-secondary: #8b5cf6;
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-error: #ef4444;
            
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-hero: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.25);
            
            --blur-sm: blur(4px);
            --blur-md: blur(8px);
            --blur-lg: blur(16px);
            
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --radius-3xl: 2rem;
            --radius-full: 9999px;
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-normal: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Light Theme */
        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --bg-glass: rgba(0, 0, 0, 0.05);
            --bg-glass-hover: rgba(0, 0, 0, 0.08);
            
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            
            /* Enhanced contrast for better visibility */
            --accent-primary: #4f46e5;
            --accent-secondary: #7c3aed;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-hero: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transition: background-color var(--transition-normal), color var(--transition-normal);
        }
        
        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--bg-secondary);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem 1.5rem;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
            overflow-y: auto;
            transition: transform var(--transition-normal);
        }
        
        [data-theme="light"] .sidebar {
            border-right: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 3rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        [data-theme="light"] .sidebar-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.25rem;
        }
        
        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .sidebar-nav {
            list-style: none;
        }
        
        .sidebar-nav-item {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border-radius: var(--radius-lg);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left var(--transition-slow);
        }
        
        .sidebar-nav-link:hover::before {
            left: 100%;
        }
        
        .sidebar-nav-link:hover {
            background: var(--bg-glass-hover);
            color: var(--text-primary);
            transform: translateX(4px);
        }
        
        .sidebar-nav-link.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .sidebar-nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            background: var(--bg-primary);
        }
        
        /* Hero Section */
        .hero-section {
            position: relative;
            padding: 3rem 2rem;
            background: var(--gradient-hero);
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .hero-profile {
            display: flex;
            align-items: center;
            gap: 3rem;
            margin-bottom: 2rem;
        }
        
        .hero-avatar-container {
            position: relative;
        }
        
        .hero-avatar {
            width: 140px;
            height: 140px;
            border-radius: var(--radius-full);
            background: var(--gradient-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 800;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-2xl);
            border: 4px solid rgba(255, 255, 255, 0.2);
        }
        
        .hero-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-full);
        }
        
        .hero-avatar-glow {
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
            filter: blur(20px);
            opacity: 0.5;
            z-index: -1;
            animation: pulse 3s ease-in-out infinite;
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 40px;
            height: 40px;
            background: var(--accent-primary);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-lg);
            transition: all var(--transition-normal);
        }
        
        .avatar-upload:hover {
            transform: scale(1.1);
            background: var(--accent-secondary);
        }
        
        .avatar-upload input {
            display: none;
        }
        
        .hero-info {
            flex: 1;
        }
        
        .hero-name {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .hero-email {
            font-size: 1.125rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1rem;
        }
        
        .hero-meta {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }
        
        .hero-bio {
            max-width: 600px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-normal);
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
            transition: left var(--transition-slow);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: var(--blur-md);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: transparent;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        /* Progress Bar */
        .hero-progress {
            max-width: 400px;
            margin-top: 1.5rem;
        }
        
        .progress-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-full);
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
            transition: width var(--transition-slow);
        }
        
        /* Analytics Strip */
        .analytics-strip {
            padding: 2rem;
            background: var(--bg-secondary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        [data-theme="light"] .analytics-strip {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: #f8fafc;
        }
        
        .analytics-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .analytics-card {
            background: var(--bg-glass);
            backdrop-filter: var(--blur-md);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            text-align: center;
            transition: all var(--transition-normal);
        }
        
        [data-theme="light"] .analytics-card {
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .analytics-card:hover {
            transform: translateY(-4px);
            background: var(--bg-glass-hover);
            box-shadow: var(--shadow-xl);
        }
        
        .analytics-icon {
            width: 48px;
            height: 48px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .analytics-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .analytics-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        /* Content Sections */
        .content-sections {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .sections-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .section-card {
            background: var(--bg-glass);
            backdrop-filter: var(--blur-md);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            transition: all var(--transition-normal);
        }
        
        [data-theme="light"] .section-card {
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .section-card:hover {
            background: var(--bg-glass-hover);
            box-shadow: var(--shadow-xl);
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
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-icon {
            width: 32px;
            height: 32px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            backdrop-filter: var(--blur-lg);
            animation: fadeIn 0.3s ease-out;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease-out;
        }
        
        [data-theme="light"] .modal-content {
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: #ffffff;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .modal-close {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            background: var(--bg-glass);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-normal);
            color: var(--text-secondary);
        }
        
        .modal-close:hover {
            background: var(--bg-glass-hover);
            color: var(--text-primary);
            transform: rotate(90deg);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all var(--transition-normal);
        }
        
        [data-theme="light"] .form-input {
            border: 1px solid rgba(0, 0, 0, 0.2);
            background: #ffffff;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all var(--transition-normal);
            cursor: pointer;
        }
        
        [data-theme="light"] .form-select {
            border: 1px solid rgba(0, 0, 0, 0.2);
            background: #ffffff;
        }
        
        .form-switch {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .switch {
            position: relative;
            width: 48px;
            height: 24px;
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: all var(--transition-normal);
        }
        
        [data-theme="light"] .switch {
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .switch.active {
            background: var(--accent-primary);
        }
        
        .switch-thumb {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: var(--radius-full);
            transition: transform var(--transition-normal);
        }
        
        .switch.active .switch-thumb {
            transform: translateX(24px);
        }
        
        /* Settings Sections */
        .settings-section {
            margin-bottom: 2rem;
        }
        
        .settings-section h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Activity Table */
        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .activity-table th,
        .activity-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        [data-theme="light"] .activity-table th,
        [data-theme="light"] .activity-table td {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .activity-table th {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .activity-table td {
            color: var(--text-primary);
        }
        
        /* Security Panel */
        .security-panel {
            background: var(--bg-glass);
            backdrop-filter: var(--blur-md);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        [data-theme="light"] .security-panel {
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .security-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        [data-theme="light"] .security-item {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .security-item:last-child {
            border-bottom: none;
        }
        
        .security-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .security-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .security-details h4 {
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .security-details p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .security-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.2);
            color: var(--accent-success);
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.2);
            color: var(--accent-error);
        }
        
        /* Light Theme Security Panel Button Fixes */
        [data-theme="light"] .btn {
            background: var(--accent-primary);
            color: white;
            border: 1px solid var(--accent-primary);
        }
        
        [data-theme="light"] .btn:hover {
            background: var(--accent-secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 237, 0.3);
        }
        
        [data-theme="light"] .btn-secondary {
            background: transparent;
            color: var(--accent-primary);
            border: 2px solid var(--accent-primary);
        }
        
        [data-theme="light"] .btn-secondary:hover {
            background: var(--accent-primary);
            color: white;
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1000;
            width: 48px;
            height: 48px;
            background: var(--bg-glass);
            backdrop-filter: var(--blur-md);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-primary);
        }
        
        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            background: var(--bg-glass);
            backdrop-filter: var(--blur-md);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: 3000;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 300px;
            transform: translateX(400px);
            transition: transform var(--transition-normal);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            border-color: var(--accent-success);
            background: rgba(16, 185, 129, 0.1);
        }
        
        .toast.error {
            border-color: var(--accent-error);
            background: rgba(239, 68, 68, 0.1);
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: flex;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .hero-profile {
                flex-direction: column;
                text-align: center;
                gap: 2rem;
            }
            
            .hero-name {
                font-size: 2rem;
                justify-content: center;
            }
            
            .hero-meta {
                justify-content: center;
            }
            
            .hero-actions {
                justify-content: center;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .content-sections {
                padding: 1rem;
            }
            
            .section-card {
                padding: 1.5rem;
            }
            
            .modal-content {
                padding: 1.5rem;
            }
            
            .toast {
                right: 1rem;
                left: 1rem;
                min-width: auto;
            }
        }
        
        /* Animations */
        @keyframes pulse {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Loading Spinner */
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid var(--accent-primary);
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
<body data-theme="<?= htmlspecialchars($user_settings['theme'] ?? 'light') ?>">
    <div class="app-container">
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <span class="material-symbols-outlined">menu</span>
        </button>
        
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">U</div>
                <div class="sidebar-title">UNILIS</div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="sidebar-nav-item">
                    <a href="dashboard.php" class="sidebar-nav-link">
                        <span class="sidebar-nav-icon">
                            <span class="material-symbols-outlined">dashboard</span>
                        </span>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="profile.php" class="sidebar-nav-link active">
                        <span class="sidebar-nav-icon">
                            <span class="material-symbols-outlined">person</span>
                        </span>
                        <span>Profile</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="#" class="sidebar-nav-link">
                        <span class="sidebar-nav-icon">
                            <span class="material-symbols-outlined">notifications</span>
                        </span>
                        <span>Notifications</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="#" class="sidebar-nav-link" onclick="openSettingsModal()">
                        <span class="sidebar-nav-icon">
                            <span class="material-symbols-outlined">settings</span>
                        </span>
                        <span>Settings</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="#" class="sidebar-nav-link">
                        <span class="sidebar-nav-icon">
                            <span class="material-symbols-outlined">security</span>
                        </span>
                        <span>Security</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Hero Section -->
            <section class="hero-section">
                <div class="hero-content">
                    <div class="hero-profile">
                        <div class="hero-avatar-container">
                            <div class="hero-avatar-glow"></div>
                            <div class="hero-avatar" id="heroAvatar">
                                <?php
                                if (isset($_SESSION['profile_picture']) && file_exists('../uploads/profile_pictures/' . $_SESSION['profile_picture'])) {
                                    echo "<img src='../uploads/profile_pictures/" . htmlspecialchars($_SESSION['profile_picture']) . "' alt='Profile Picture'>";
                                } else {
                                    echo strtoupper(substr(htmlspecialchars($profile_data['name'] ?? 'U'), 0, 2));
                                }
                                ?>
                            </div>
                            <label class="avatar-upload" id="avatarUploadBtn">
                                <span class="material-symbols-outlined">camera_alt</span>
                                <input type="file" id="profileImageInput" accept="image/*">
                            </label>
                        </div>
                        
                        <div class="hero-info">
                            <h1 class="hero-name">
                                <?= htmlspecialchars($profile_data['name'] ?? 'User') ?>
                                <span class="material-symbols-outlined" style="color: #10b981; font-size: 1.5rem;">verified</span>
                            </h1>
                            <p class="hero-email"><?= htmlspecialchars($profile_data['email'] ?? 'N/A') ?></p>
                            
                            <div class="hero-meta">
                                <div class="hero-meta-item">
                                    <span class="material-symbols-outlined" style="font-size: 1.25rem;">school</span>
                                    <span><?= ucfirst($user_role) ?></span>
                                </div>
                                <div class="hero-meta-item">
                                    <span class="material-symbols-outlined" style="font-size: 1.25rem;">location_on</span>
                                    <span><?= htmlspecialchars($profile_data['university_name'] ?? 'UNILIS University') ?></span>
                                </div>
                                <div class="hero-meta-item">
                                    <span class="material-symbols-outlined" style="font-size: 1.25rem;">calendar_today</span>
                                    <span>Joined <?= date('Y', strtotime($profile_data['year_joined'] ?? date('Y'))) ?></span>
                                </div>
                                <?php if ($user_role === 'student'): ?>
                                    <div class="hero-meta-item">
                                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">book</span>
                                        <span><?= htmlspecialchars($profile_data['course_name'] ?? 'Computer Science') ?></span>
                                    </div>
                                    <?php if (!empty($profile_data['reg_no'])): ?>
                                    <div class="hero-meta-item">
                                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">badge</span>
                                        <span><?= htmlspecialchars($profile_data['reg_no']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                <?php elseif ($user_role === 'lecturer'): ?>
                                    <div class="hero-meta-item">
                                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">psychology</span>
                                        <span><?= htmlspecialchars($profile_data['department_name'] ?? 'Computer Science Department') ?></span>
                                    </div>
                                    <?php if (!empty($profile_data['staff_id'])): ?>
                                    <div class="hero-meta-item">
                                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">badge</span>
                                        <span><?= htmlspecialchars($profile_data['staff_id']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                <?php elseif ($user_role === 'admin'): ?>
                                    <div class="hero-meta-item">
                                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">admin_panel_settings</span>
                                        <span>System Administrator</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <p class="hero-bio" id="heroBio">
                                <?= htmlspecialchars($profile_data['bio'] ?? 'Passionate about education and technology. Committed to excellence in academic pursuits and continuous learning.') ?>
                            </p>
                            
                            <div class="hero-actions">
                                <button class="btn btn-primary" onclick="openPersonalInfoModal()">
                                    <span class="material-symbols-outlined">edit</span>
                                    Edit Personal Info
                                </button>
                                <button class="btn btn-secondary" onclick="openSettingsModal()">
                                    <span class="material-symbols-outlined">settings</span>
                                    Settings
                                </button>
                            </div>
                            
                            <div class="hero-progress">
                                <div class="progress-label">
                                    <span>Profile Completion</span>
                                    <span>85%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 85%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Analytics Strip -->
            <section class="analytics-strip">
                <div class="analytics-container">
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="analytics-icon">
                                <span class="material-symbols-outlined">school</span>
                            </div>
                            <div class="analytics-value">
                                <?= $profile_data['courses_enrolled'] ?? $profile_data['courses_teaching'] ?? $profile_data['total_courses'] ?? '0' ?>
                            </div>
                            <div class="analytics-label">
                                <?php
                                if ($user_role === 'student') echo 'Courses Enrolled';
                                elseif ($user_role === 'lecturer') echo 'Courses Teaching';
                                else echo 'Total Courses';
                                ?>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="analytics-icon">
                                <span class="material-symbols-outlined">assignment</span>
                            </div>
                            <div class="analytics-value">
                                <?= $profile_data['assignments_completed'] ?? $profile_data['assignments_created'] ?? '0' ?>
                            </div>
                            <div class="analytics-label">
                                <?php
                                if ($user_role === 'student') echo 'Assignments Completed';
                                elseif ($user_role === 'lecturer') echo 'Assignments Created';
                                else echo 'Total Assignments';
                                ?>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="analytics-icon">
                                <span class="material-symbols-outlined">trending_up</span>
                            </div>
                            <div class="analytics-value">
                                <?= round($profile_data['performance_score'] ?? 85, 1) ?>%
                            </div>
                            <div class="analytics-label">Performance Score</div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="analytics-icon">
                                <span class="material-symbols-outlined">people</span>
                            </div>
                            <div class="analytics-value">
                                <?= $profile_data['total_students'] ?? $profile_data['total_lecturers'] ?? '0' ?>
                            </div>
                            <div class="analytics-label">
                                <?php
                                if ($user_role === 'admin') echo 'Total Users';
                                else echo 'Projects';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Content Sections -->
            <section class="content-sections">
                <div class="sections-grid">
                    <!-- Academic Information -->
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">
                                <div class="section-icon">
                                    <span class="material-symbols-outlined">school</span>
                                </div>
                                Academic Information
                            </h3>
                        </div>
                        
                        <?php if ($user_role === 'student'): ?>
                            <div class="form-group">
                                <label class="form-label">Registration Number</label>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['reg_no'] ?? 'SC/2023/001') ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Course</label>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['course_name'] ?? 'Computer Science') ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Year of Study</label>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['year_of_study'] ?? 'Year 3') ?>" readonly>
                            </div>
                            
                        <?php elseif ($user_role === 'lecturer'): ?>
                            <div class="form-group">
                                <label class="form-label">Staff ID</label>
                                <input type="text" class="form-input" value="STF000123" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['department_name'] ?? 'Computer Science') ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Specialization</label>
                                <input type="text" class="form-input" value="Machine Learning" readonly>
                            </div>
                            
                        <?php elseif ($user_role === 'admin'): ?>
                            <div class="form-group">
                                <label class="form-label">Admin ID</label>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($profile_data['id'] ?? 'ADM000001') ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Access Level</label>
                                <input type="text" class="form-input" value="Super Administrator" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-input" value="IT Department" readonly>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Account Status -->
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">
                                <div class="section-icon">
                                    <span class="material-symbols-outlined">account_circle</span>
                                </div>
                                Account Status
                            </h3>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Account Status</label>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="status-badge status-active">Active</span>
                                <span style="color: var(--text-secondary);">Your account is in good standing</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Verification</label>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="status-badge status-active">Verified</span>
                                <span style="color: var(--text-secondary);">Email verified on <?= date('M j, Y') ?></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Two-Factor Authentication</label>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="status-badge status-inactive">Disabled</span>
                                <span style="color: var(--text-secondary);">Enable 2FA for better security</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Last Login</label>
                            <input type="text" class="form-input" value="<?= date('M j, Y \a\t g:i A') ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Security Panel -->
                <div class="security-panel">
                    <div class="section-header">
                        <h3 class="section-title">
                            <div class="section-icon">
                                <span class="material-symbols-outlined">security</span>
                            </div>
                            Security Settings
                        </h3>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-info">
                            <div class="security-icon">
                                <span class="material-symbols-outlined">password</span>
                            </div>
                            <div class="security-details">
                                <h4>Change Password</h4>
                                <p>Update your password regularly for security</p>
                            </div>
                        </div>
                        <a href="update_password.php" class="btn btn-primary">Change</a>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-info">
                            <div class="security-icon">
                                <span class="material-symbols-outlined">gpp_maybe</span>
                            </div>
                            <div class="security-details">
                                <h4>Two-Factor Authentication</h4>
                                <p>Add an extra layer of security to your account</p>
                            </div>
                        </div>
                        <div class="security-status">
                            <span class="status-badge status-inactive">Disabled</span>
                            <button class="btn btn-primary">Enable</button>
                        </div>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-info">
                            <div class="security-icon">
                                <span class="material-symbols-outlined">devices</span>
                            </div>
                            <div class="security-details">
                                <h4>Active Sessions</h4>
                                <p>Manage devices logged into your account</p>
                            </div>
                        </div>
                        <button class="btn btn-secondary">Manage</button>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <div class="section-icon">
                                <span class="material-symbols-outlined">history</span>
                            </div>
                            Recent Activity
                        </h3>
                        <button class="btn btn-secondary">View All</button>
                    </div>
                    
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Activity</th>
                                <th>Details</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity_data as $activity): ?>
                                <tr>
                                    <td><?= htmlspecialchars($activity['activity']) ?></td>
                                    <td><?= htmlspecialchars($activity['details']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($activity['date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    
    <!-- Personal Info Modal -->
    <div class="modal" id="personalInfoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Personal Information</h2>
                <button class="modal-close" onclick="closePersonalInfoModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <form id="personalInfoForm">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-input" id="fullName" name="name" 
                           value="<?= htmlspecialchars($profile_data['name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" id="email" name="email" 
                           value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-input" id="phone" name="phone" 
                           value="<?= htmlspecialchars($profile_data['phone'] ?? '+254 700 000 000') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" class="form-input" id="dateOfBirth" name="date_of_birth" 
                           value="<?= htmlspecialchars($profile_data['date_of_birth'] ?? '1995-01-01') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-input" id="address" name="address" 
                           value="<?= htmlspecialchars($profile_data['address'] ?? 'Nairobi, Kenya') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Bio</label>
                    <textarea class="form-input form-textarea" id="bio" name="bio"><?= htmlspecialchars($profile_data['bio'] ?? '') ?></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closePersonalInfoModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Settings</h2>
                <button class="modal-close" onclick="closeSettingsModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <form id="settingsForm">
                <!-- Appearance Settings -->
                <div class="settings-section">
                    <h3>
                        <span class="material-symbols-outlined">palette</span>
                        Appearance
                    </h3>
                    <div class="settings-grid">
                        <div class="form-group">
                            <label class="form-label">Theme</label>
                            <select class="form-select" id="theme" name="theme">
                                <option value="dark" <?= ($user_settings['theme'] ?? 'dark') === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
                                <option value="light" <?= ($user_settings['theme'] ?? 'dark') === 'light' ? 'selected' : '' ?>>Light Mode</option>
                                <option value="auto">Auto (System)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Language</label>
                            <select class="form-select" id="language" name="language">
                                <option value="en" <?= ($user_settings['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="sw" <?= ($user_settings['language'] ?? 'en') === 'sw' ? 'selected' : '' ?>>Swahili</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Notification Settings -->
                <div class="settings-section">
                    <h3>
                        <span class="material-symbols-outlined">notifications</span>
                        Notifications
                    </h3>
                    <div class="form-group">
                        <div class="form-switch">
                            <label class="form-label" style="margin: 0;">Enable Notifications</label>
                            <div class="switch <?= ($user_settings['notifications_enabled'] ?? 1) ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                <div class="switch-thumb"></div>
                            </div>
                            <input type="hidden" name="notifications_enabled" value="<?= $user_settings['notifications_enabled'] ?? 1 ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-switch">
                            <label class="form-label" style="margin: 0;">Email Notifications</label>
                            <div class="switch <?= ($user_settings['email_notifications'] ?? 1) ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                <div class="switch-thumb"></div>
                            </div>
                            <input type="hidden" name="email_notifications" value="<?= $user_settings['email_notifications'] ?? 1 ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Privacy Settings -->
                <div class="settings-section">
                    <h3>
                        <span class="material-symbols-outlined">privacy_tip</span>
                        Privacy
                    </h3>
                    <div class="form-group">
                        <div class="form-switch">
                            <label class="form-label" style="margin: 0;">Profile Visible to Others</label>
                            <div class="switch <?= ($user_settings['privacy_profile_visible'] ?? 1) ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                <div class="switch-thumb"></div>
                            </div>
                            <input type="hidden" name="privacy_profile_visible" value="<?= $user_settings['privacy_profile_visible'] ?? 1 ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-switch">
                            <label class="form-label" style="margin: 0;">Show Email in Profile</label>
                            <div class="switch <?= ($user_settings['privacy_show_email'] ?? 0) ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                <div class="switch-thumb"></div>
                            </div>
                            <input type="hidden" name="privacy_show_email" value="<?= $user_settings['privacy_show_email'] ?? 0 ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-switch">
                            <label class="form-label" style="margin: 0;">Show Phone in Profile</label>
                            <div class="switch <?= ($user_settings['privacy_show_phone'] ?? 0) ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                <div class="switch-thumb"></div>
                            </div>
                            <input type="hidden" name="privacy_show_phone" value="<?= $user_settings['privacy_show_phone'] ?? 0 ?>">
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeSettingsModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div id="toastContainer"></div>
    
    <!-- Global Theme Manager -->
    <script src="../assets/js/theme-manager.js"></script>
    
    <script>
        // Global variables
        let currentTheme = '<?= $user_settings['theme'] ?? 'light' ?>';
        
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
        
        // Modal functions
        function openPersonalInfoModal() {
            document.getElementById('personalInfoModal').classList.add('show');
        }
        
        function closePersonalInfoModal() {
            document.getElementById('personalInfoModal').classList.remove('show');
        }
        
        function openSettingsModal() {
            document.getElementById('settingsModal').classList.add('show');
        }
        
        function closeSettingsModal() {
            document.getElementById('settingsModal').classList.remove('show');
        }
        
        // Switch toggle function
        function toggleSwitch(element) {
            element.classList.toggle('active');
            const hiddenInput = element.parentElement.querySelector('input[type="hidden"]');
            if (hiddenInput) {
                hiddenInput.value = element.classList.contains('active') ? '1' : '0';
            }
        }
        
        // Theme management - using global theme manager
        function applyTheme(theme) {
            if (theme === 'auto') {
                theme = themeManager.detectSystemTheme();
            }
            themeManager.applyTheme(theme);
            currentTheme = theme;
        }
        
        // Save profile
        async function saveProfile() {
            const formData = new FormData();
            formData.append('action', 'update_profile');
            
            // Add form data
            document.querySelectorAll('#personalInfoForm input, #personalInfoForm textarea').forEach(input => {
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
                    closePersonalInfoModal();
                    
                    // Update display elements
                    document.querySelector('.hero-name').firstChild.textContent = document.getElementById('fullName').value;
                    document.querySelector('.hero-email').textContent = document.getElementById('email').value;
                    document.querySelector('.hero-bio').textContent = document.getElementById('bio').value;
                } else {
                    showToast(result.message || 'Failed to update profile', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Save settings using JavaScript array
        function saveSettings() {
            // Collect all settings into an object
            const settings = {
                action: 'update_settings',
                theme: document.getElementById('theme').value,
                language: document.getElementById('language').value,
                notifications_enabled: document.querySelector('input[name="notifications_enabled"]').value,
                email_notifications: document.querySelector('input[name="email_notifications"]').value,
                privacy_profile_visible: document.querySelector('input[name="privacy_profile_visible"]').value,
                privacy_show_email: document.querySelector('input[name="privacy_show_email"]').value,
                privacy_show_phone: document.querySelector('input[name="privacy_show_phone"]').value
            };
            
            // Store settings in localStorage for immediate effect
            localStorage.setItem('userSettings', JSON.stringify(settings));
            
            // Apply theme change immediately using global theme manager
            const newTheme = settings.theme;
            themeManager.applyTheme(newTheme);
            currentTheme = newTheme;
            
            // Show success message
            showToast('Settings updated successfully!', 'success');
            closeSettingsModal();
            
            // Optional: Send to server in background (non-blocking)
            sendSettingsToServer(settings);
        }
        
        // Send settings to server in background
        function sendSettingsToServer(settings) {
            const formData = new FormData();
            Object.keys(settings).forEach(key => {
                formData.append(key, settings[key]);
            });
            
            fetch('profile.php', {
                method: 'POST',
                body: formData
            }).catch(error => {
                console.log('Settings saved locally, server sync failed:', error);
            });
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
                    const avatar = document.getElementById('heroAvatar');
                    avatar.innerHTML = `<img src="../uploads/profile_pictures/${result.filename}" alt="Profile Picture">`;
                } else {
                    showToast(result.message || 'Failed to upload image', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Load settings from localStorage
        function loadSettingsFromStorage() {
            const savedSettings = localStorage.getItem('userSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                
                // Update form elements with saved values
                if (document.getElementById('theme')) {
                    document.getElementById('theme').value = settings.theme || 'light';
                }
                if (document.getElementById('language')) {
                    document.getElementById('language').value = settings.language || 'en';
                }
                
                // Update switch states
                const switches = [
                    { name: 'notifications_enabled', value: settings.notifications_enabled },
                    { name: 'email_notifications', value: settings.email_notifications },
                    { name: 'privacy_profile_visible', value: settings.privacy_profile_visible },
                    { name: 'privacy_show_email', value: settings.privacy_show_email },
                    { name: 'privacy_show_phone', value: settings.privacy_show_phone }
                ];
                
                switches.forEach(({ name, value }) => {
                    const hiddenInput = document.querySelector(`input[name="${name}"]`);
                    const switchElement = hiddenInput?.parentElement?.querySelector('.switch');
                    if (switchElement && value !== undefined) {
                        if (value === '1' || value === true) {
                            switchElement.classList.add('active');
                        } else {
                            switchElement.classList.remove('active');
                        }
                        if (hiddenInput) {
                            hiddenInput.value = value === '1' || value === true ? '1' : '0';
                        }
                    }
                });
                
                // Apply saved theme using global theme manager
                const themeToApply = settings.theme || 'light';
                themeManager.applyTheme(themeToApply);
                currentTheme = themeToApply;
            }
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', () => {
            // Load settings from localStorage
            loadSettingsFromStorage();
            
            // Apply initial theme if not loaded from storage
            if (!localStorage.getItem('userSettings')) {
                applyTheme(currentTheme);
            }
            
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
            
            // Close modals when clicking outside
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });
            
            // Personal info form submission
            document.getElementById('personalInfoForm').addEventListener('submit', (e) => {
                e.preventDefault();
                saveProfile();
            });
            
            // Settings form submission
            const settingsForm = document.getElementById('settingsForm');
            if (settingsForm) {
                settingsForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    saveSettings();
                });
            }
            
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
            
            // Watch for system theme changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (document.getElementById('theme').value === 'auto') {
                    applyTheme('auto');
                }
            });
        });
    </script>
</body>
</html>
