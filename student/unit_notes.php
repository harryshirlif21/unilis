<?php
require_once '../config/db.php';
session_start();

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

// Get unit ID from URL
if (!isset($_GET['unit_id'])) {
    header("Location: viewnotes.php");
    exit;
}

$unit_id = (int) $_GET['unit_id'];
$student_id = (int) $_SESSION['user_id'];

// Verify unit belongs to student
$verify_stmt = $conn->prepare("
    SELECT u.id, u.name, u.code, u.course_id, u.year
    FROM units u
    INNER JOIN students s ON s.course_id = u.course_id AND s.year_of_study = u.year
    WHERE u.id = ? AND s.id = ?
");
$verify_stmt->bind_param("ii", $unit_id, $student_id);
$verify_stmt->execute();
$unit = $verify_stmt->get_result()->fetch_assoc();
$verify_stmt->close();

if (!$unit) {
    echo "<h1>Access Denied</h1><p>This unit is not available for your course.</p>";
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'mark_complete' && isset($_POST['note_id'])) {
        $note_id = (int) $_POST['note_id'];
        
        // Check if already completed
        $check = $conn->prepare("SELECT id FROM student_classnotes_progress WHERE student_id = ? AND classnote_id = ?");
        $check->bind_param("ii", $student_id, $note_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        
        if ($exists) {
            $stmt = $conn->prepare("UPDATE student_classnotes_progress SET status = 'completed', last_accessed = NOW() WHERE student_id = ? AND classnote_id = ?");
        } else {
            $stmt = $conn->prepare("INSERT INTO student_classnotes_progress (student_id, classnote_id, status, last_accessed) VALUES (?, ?, 'completed', NOW())");
        }
        
        $stmt->bind_param("ii", $student_id, $note_id);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => $success]);
        exit;
    }
}

// Fetch file notes
$file_notes_stmt = $conn->prepare("
    SELECT n.id, n.file_path, n.uploaded_at
    FROM notes n
    WHERE n.unit_id = ?
    ORDER BY n.uploaded_at DESC
");
$file_notes_stmt->bind_param("i", $unit_id);
$file_notes_stmt->execute();
$file_notes = $file_notes_stmt->get_result();

// Fetch interactive notes
$interactive_notes_stmt = $conn->prepare("
    SELECT cn.id, cn.title, cn.subtopics_json, cn.uploaded_at,
           scp.status as progress_status
    FROM classnotes cn
    LEFT JOIN student_classnotes_progress scp ON scp.classnote_id = cn.id AND scp.student_id = ?
    WHERE cn.unit_id = ?
    ORDER BY cn.uploaded_at ASC
");
$interactive_notes_stmt->bind_param("ii", $student_id, $unit_id);
$interactive_notes_stmt->execute();
$interactive_notes = $interactive_notes_stmt->get_result();

// Content sanitization function
function sanitizeRichContent($content) {
    if (empty($content)) return $content;
    
    // Remove dangerous tags
    $dangerous_tags = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button'];
    foreach ($dangerous_tags as $tag) {
        $content = preg_replace('/<\/?' . $tag . '[^>]*>/i', '', $content);
    }
    
    // Remove dangerous attributes
    $dangerous_attrs = ['onclick', 'onload', 'onerror', 'onmouseover', 'onmouseout', 'onfocus', 'onblur'];
    foreach ($dangerous_attrs as $attr) {
        $content = preg_replace('/\s*' . $attr . '\s*=\s*["\'][^"\']*["\']/', '', $content);
    }
    
    // Fix image sources
    $content = preg_replace_callback('/<img([^>]+)>/i', function($matches) {
        $imgTag = $matches[0];
        
        // Fix malformed src attributes
        if (preg_match('/src=["\']([^"\']*)["\']/', $imgTag, $srcMatch)) {
            $src = $srcMatch[1];
            
            // Handle mixed base64 and file path
            if (strpos($src, 'data:image') !== false && strpos($src, '/uploads/') !== false) {
                // Extract file path from mixed content
                if (preg_match('/(\/uploads\/[^\'"\s]+)/', $src, $pathMatch)) {
                    $cleanSrc = $pathMatch[1];
                    $imgTag = str_replace($srcMatch[0], 'src="' . $cleanSrc . '"', $imgTag);
                }
            } elseif (strpos($src, '../uploads/') === 0) {
                $cleanSrc = '/' . substr($src, 3);
                $imgTag = str_replace($srcMatch[0], 'src="' . $cleanSrc . '"', $imgTag);
            } elseif (strpos($src, 'uploads/') === 0) {
                $cleanSrc = '/' . $src;
                $imgTag = str_replace($srcMatch[0], 'src="' . $cleanSrc . '"', $imgTag);
            }
            
            // Add alt attribute if missing
            if (!preg_match('/alt=["\']/', $imgTag)) {
                $imgTag = str_replace('<img', '<img alt="Content image"', $imgTag);
            }
            
            // Add loading="lazy" for performance
            if (!preg_match('/loading=["\']/', $imgTag)) {
                $imgTag = str_replace('<img', '<img loading="lazy"', $imgTag);
            }
        }
        
        // Remove inline styles and dangerous attributes
        $imgTag = preg_replace('/\s+(style|width|height|class|id)=["\'][^"\']*["\']/', '', $imgTag);
        
        return $imgTag;
    }, $content);
    
    // Clean up excessive class names and automation attributes
    $content = preg_replace('/\s+class=["\'][^"\']*css-[^"\']*["\']/', '', $content);
    $content = preg_replace('/\s+automation-testid=["\'][^"\']*["\']/', '', $content);
    $content = preg_replace('/\s+data-[^=]*=["\'][^"\']*["\']/', '', $content);
    
    // Remove empty tags
    $content = preg_replace('/<[^>]*><\/[^>]*>/', '', $content);
    
    // Fix broken HTML entities
    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    
    return $content;
}

// Fix image paths in content
function fixImagePathsInContent($content) {
    if (empty($content)) return $content;
    
    // Fix image src attributes
    $content = preg_replace_callback('/src=["\']([^"\']*)["\']/', function($matches) {
        $src = $matches[1];
        
        // Fix relative paths
        if (strpos($src, '../uploads/') === 0) {
            return 'src="' . str_replace('../', '/', $src) . '"';
        } elseif (strpos($src, 'uploads/') === 0) {
            return 'src="/' . $src . '"';
        }
        
        return 'src="' . $src . '"';
    }, $content);
    
    // Add inline-img class to images if missing
    $content = preg_replace_callback('/<img([^>]*?)class=["\']([^"\']*?)["\']([^>]*?)>/', function($matches) {
        $attrs = $matches[1] . $matches[3];
        $existingClass = $matches[2];
        
        if (strpos($existingClass, 'inline-img') === false) {
            return '<img' . $attrs . ' class="' . $existingClass . ' inline-img">';
        }
        
        return '<img' . $attrs . ' class="' . $existingClass . '">';
    }, $content);
    
    // Add class to images without class attribute
    $content = preg_replace('/<img(?![^>]*class)([^>]*?)>/', '<img class="inline-img"$1>', $content);
    
    return $content;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($unit['name']) ?> - Notes</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        body {
            background-color: #f5f5f5 !important;
        }
        
        .unit-notes-container {
            margin-top: 90px;
            margin-left: 0;
            padding: 20px;
            min-height: calc(100vh - 90px);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .unit-header {
            background: linear-gradient(135deg, #4A90E2, #357ABD);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        .unit-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .unit-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-3px);
        }

        .notes-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4A90E2;
        }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .note-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .note-card.file-note::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10B981, #059669);
        }

        .note-card.interactive-note::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #F97316, #ea580c);
        }

        .note-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .file-note .note-icon {
            background: linear-gradient(135deg, #10B981, #059669);
        }

        .interactive-note .note-icon {
            background: linear-gradient(135deg, #F97316, #ea580c);
        }

        .note-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .note-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .note-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #999;
            font-size: 12px;
            margin-bottom: 15px;
        }

        .note-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4A90E2, #357ABD);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #357ABD, #2968AA);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
        }

        .btn-success.completed {
            background: linear-gradient(135deg, #6B7280, #4B5563);
        }

        .subtopics {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .subtopic {
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .subtopic:last-child {
            margin-bottom: 0;
        }

        .subtopic:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .subtopic-header {
            background: linear-gradient(135deg, #F97316, #ea580c);
            color: white;
            padding: 12px 15px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }

        .subtopic-header:hover {
            background: linear-gradient(135deg, #ea580c, #dc2626);
        }

        .subtopic-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .subtopic-toggle {
            font-size: 14px;
            transition: transform 0.3s ease;
        }

        .subtopic-toggle i {
            transition: transform 0.3s ease;
        }

        .subtopic.expanded .subtopic-toggle {
            transform: rotate(180deg);
        }

        .subtopic-content {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            padding: 15px;
            background: white;
            display: none;
            animation: slideDown 0.3s ease-out;
        }

        /* Fix inline images in content */
        .subtopic-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: block;
        }

        .subtopic-content .inline-img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: block;
        }

        /* Fix image paths */
        .subtopic-content img[src*="../uploads/"] {
            content: attr(src);
            src: attr(src, url("../uploads/69201953be63d_image.png"));
        }

        .subtopic.expanded .subtopic-content {
            display: block;
        }

        /* Rich Text Content Styling */
        .rich-text-content {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #374151;
            line-height: 1.7;
        }

        .rich-text-p {
            font-size: 16px;
            line-height: 1.7;
            color: #374151;
            margin-bottom: 16px;
            text-align: left;
        }

        .rich-img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: block;
        }

        .rich-link {
            color: #4dabf7;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .rich-link:hover {
            color: #339af0;
            text-decoration: underline;
        }

        .rich-heading {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin: 24px 0 16px 0;
            line-height: 1.3;
        }

        .rich-list {
            margin: 16px 0;
            padding-left: 24px;
        }

        .rich-list li {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        .rich-code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 14px;
        }

        .rich-blockquote {
            border-left: 4px solid #4dabf7;
            padding-left: 16px;
            margin: 16px 0;
            font-style: italic;
            color: #6b7280;
        }

        /* Dark mode compatibility */
        @media (prefers-color-scheme: dark) {
            .rich-text-content {
                color: #e5e7eb;
            }
            
            .rich-text-p {
                color: #e5e7eb;
            }
            
            .rich-heading {
                color: #f9fafb;
            }
            
            .rich-code {
                background: #374151;
                color: #e5e7eb;
            }
            
            .rich-blockquote {
                color: #9ca3af;
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .interactive-features {
            margin-top: 15px;
            padding: 15px;
            background: #e8f5e8;
            border-radius: 8px;
            border-left: 4px solid #10B981;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 10px 0 0 0;
        }

        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #d1fae5;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list li i {
            color: #10B981;
            width: 20px;
            text-align: center;
        }

        .progress-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .study-timer {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }

        .note-progress-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
        }

        .note-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10B981, #059669);
            border-radius: 3px;
            transition: width 0.5s ease;
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

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .unit-notes-container {
                margin-left: 0;
                margin-top: 70px;
                padding: 15px;
            }

            .unit-header {
                padding: 20px;
            }

            .unit-header h1 {
                font-size: 24px;
            }

            .notes-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .note-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="welcome-msg">
            <strong>👋 Welcome back!</strong>
        </div>

        <!-- Navigation Icons Container -->
        <div class="nav-icons-container">
            <!-- Notifications -->
            <div class="nav-icon" id="notifications-icon" style="position:relative; cursor:pointer;">
                <i class="fas fa-bell"></i>
                <!-- Red circle indicator for new notifications -->
                <span id="notificationCount" 
                      style="position:absolute; top:0; right:0; width:12px; height:12px; background:red; border-radius:50%; display:block;">
                </span>
            </div>
            <div class="nav-icon" id="profile-icon" style="cursor: pointer;">
                <i class="fas fa-user"></i>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="unit-notes-container">
        <div class="unit-header">
            <a href="viewnotes.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Units
            </a>
            <h1><?= htmlspecialchars($unit['name']) ?></h1>
            <p><?= htmlspecialchars($unit['code']) ?> • Year <?= htmlspecialchars($unit['year']) ?></p>
        </div>

        <!-- File Notes Section -->
        <div class="notes-section">
            <h2 class="section-title">📁 File Notes</h2>
            <?php if ($file_notes->num_rows > 0): ?>
                <div class="notes-grid">
                    <?php while ($note = $file_notes->fetch_assoc()): ?>
                        <div class="note-card file-note">
                            <div class="note-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="note-title">File Note</div>
                            <div class="note-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($note['uploaded_at'])) ?></span>
                                <span><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($note['uploaded_at'])) ?></span>
                            </div>
                            <div class="note-actions">
                                <?php 
                                $filePath = "../assets/uploads/" . htmlspecialchars($note['file_path']);
                                if (file_exists($filePath)): ?>
                                    <a href="<?= $filePath ?>" target="_blank" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?= $filePath ?>" download class="btn btn-success">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-size: 12px;">
                                        <i class="fas fa-exclamation-triangle"></i> File not found
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file"></i>
                    <h3>No File Notes</h3>
                    <p>No file notes have been uploaded for this unit yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Interactive Notes Section -->
        <div class="notes-section">
            <h2 class="section-title">💻 Interactive Notes</h2>
            <?php if ($interactive_notes->num_rows > 0): ?>
                <div class="notes-grid">
                    <?php while ($note = $interactive_notes->fetch_assoc()): ?>
                        <?php 
                        $subtopics = json_decode($note['subtopics_json'], true) ?? [];
                        $isCompleted = $note['progress_status'] === 'completed';
                        ?>
                        <div class="note-card interactive-note">
                            <div class="note-icon">
                                <i class="fas fa-laptop-code"></i>
                            </div>
                            <div class="note-title">
                                <?= htmlspecialchars($note['title']) ?>
                                <?php if ($isCompleted): ?>
                                    <span class="progress-indicator">
                                        <i class="fas fa-check-circle"></i> Completed
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="note-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($note['uploaded_at'])) ?></span>
                                <span><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($note['uploaded_at'])) ?></span>
                                <span><i class="fas fa-list"></i> <?= count($subtopics) ?> subtopics</span>
                            </div>
                            <?php if (!empty($subtopics)): ?>
                                <div class="note-progress-bar">
                                    <div class="note-progress-fill" style="width: <?= $isCompleted ? '100' : '0' ?>%"></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($subtopics)): ?>
                                <div class="subtopics">
                                    <?php foreach ($subtopics as $index => $subtopic): ?>
                                        <div class="subtopic" id="subtopic-<?= $note['id'] ?>-<?= $index ?>">
                                            <div class="subtopic-header" onclick="var elem = document.getElementById('content-<?= $note['id'] ?>-<?= $index ?>'); var toggle = this.querySelector('.subtopic-toggle i'); if (elem.style.display === 'none') { elem.style.display = 'block'; toggle.style.transform = 'rotate(180deg)'; fixImagesInContent(elem); } else { elem.style.display = 'none'; toggle.style.transform = 'rotate(0deg)'; }">
                                                <h5><?= htmlspecialchars($subtopic['title'] ?? "Subtopic " . ($index + 1)) ?></h5>
                                                <span class="subtopic-toggle">
                                                    <i class="fas fa-chevron-down"></i>
                                                </span>
                                            </div>
                                            <div class="subtopic-content" id="content-<?= $note['id'] ?>-<?= $index ?>" style="display: none;">
                                                <?= fixImagePathsInContent($subtopic['content'] ?? 'No content available') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($subtopics)): ?>
                                <div class="interactive-features">
                                    <strong><i class="fas fa-star"></i> Interactive Features:</strong>
                                    <ul class="feature-list">
                                        <li><i class="fas fa-expand-alt"></i> Expandable subtopics</li>
                                        <li><i class="fas fa-eye"></i> Click to view content</li>
                                        <li><i class="fas fa-check-circle"></i> Progress tracking</li>
                                        <li><i class="fas fa-bookmark"></i> Bookmark important sections</li>
                                    </ul>
                                    <div class="study-timer" id="timer-<?= $note['id'] ?>">
                                        <i class="fas fa-clock"></i> <span id="timer-text-<?= $note['id'] ?>">Start Study Timer</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="note-actions">
                                <button class="btn btn-success <?= $isCompleted ? 'completed' : '' ?>" 
                                        onclick="markAsComplete(<?= $note['id'] ?>)"
                                        <?= $isCompleted ? 'disabled' : '' ?>>
                                    <i class="fas <?= $isCompleted ? 'fa-check' : 'fa-check-circle' ?>"></i>
                                    <?= $isCompleted ? 'Completed' : 'Mark as Complete' ?>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-laptop"></i>
                    <h3>No Interactive Notes</h3>
                    <p>No interactive notes have been created for this unit yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile popup -->
    <div class="popup" id="profile-popup">
        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
        <p><strong>Reg No:</strong> <?php echo htmlspecialchars($student['reg_no']); ?></p>
        <p><strong>Course:</strong> <?php echo htmlspecialchars($course_name); ?></p>
        <p><strong>Year:</strong> <?php echo htmlspecialchars($student['year_of_study']); ?></p>
        <p><strong>Joined:</strong> <?php echo htmlspecialchars($student['year_joined']); ?></p>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; text-align: center;">
            <a href="my_progress.php" style="background: #667eea; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px; text-decoration: none; display: inline-block;">
                <i class="fas fa-chart-line"></i> My Progress
            </a>
            <a href="../logout.php" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Latest notifications popup -->
    <div id="notifications-content" class="popup">
        <h3>Latest Notifications</h3>
        <ul>
            <?php if($latest_notifications->num_rows === 0): ?>
                <li>No notifications</li>
            <?php else: ?>
                <?php while($notif = $latest_notifications->fetch_assoc()): ?>
                    <li>
                        <strong><?= htmlspecialchars($notif['title']) ?></strong>
                        <p><?= htmlspecialchars($notif['message']) ?></p>
                        <small><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></small>
                    </li>
                <?php endwhile; ?>
            <?php endif; ?>
        </ul>
        <div style="margin-top: 15px; text-align: center;">
            <button onclick="window.location.href='notifications.php'" style="background: #4A90E2; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer;">
                View All Notifications
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Profile popup functionality
        const profileIcon = document.getElementById('profile-icon');
        const profilePopup = document.getElementById('profile-popup');
        
        if (profileIcon && profilePopup) {
            profileIcon.addEventListener('click', () => {
                const isVisible = profilePopup.style.display === 'block';
                profilePopup.style.display = isVisible ? 'none' : 'block';
                
                // Hide notifications popup
                const notificationsPopup = document.getElementById('notifications-content');
                if (notificationsPopup) {
                    notificationsPopup.style.display = 'none';
                }
            });
        }

        // Notifications functionality
        const notificationsIcon = document.getElementById('notifications-icon');
        const notificationsPopup = document.getElementById('notifications-content');
        
        if (notificationsIcon && notificationsPopup) {
            notificationsIcon.addEventListener('click', () => {
                const isVisible = notificationsPopup.style.display === 'block';
                notificationsPopup.style.display = isVisible ? 'none' : 'block';
                
                // Hide profile popup
                if (profilePopup) {
                    profilePopup.style.display = 'none';
                }
            });
        }

        // Close popups when clicking outside
        document.addEventListener('click', (e) => {
            if (!profileIcon?.contains(e.target) && !profilePopup?.contains(e.target)) {
                profilePopup.style.display = 'none';
            }
            if (!notificationsIcon?.contains(e.target) && !notificationsPopup?.contains(e.target)) {
                notificationsPopup.style.display = 'none';
            }
        });
    });

    function toggleSubtopic(subtopicId) {
        console.log('Toggling subtopic:', subtopicId); // Debug log
        const subtopic = document.getElementById(`subtopic-${subtopicId}`);
        console.log('Found subtopic element:', subtopic); // Debug log
        
        if (subtopic) {
            // Toggle expanded class
            subtopic.classList.toggle('expanded');
            console.log('Toggled expanded class'); // Debug log
            
            // Update progress bar
            updateNoteProgress(subtopicId.split('-')[0]);
        } else {
            console.error('Subtopic element not found:', subtopicId);
        }
    }

    function fixImagesInContent(contentElement) {
        if (!contentElement) return;
        
        const images = contentElement.querySelectorAll('img');
        images.forEach(img => {
            let src = img.getAttribute('src');
            
            // Fix relative paths
            if (src && src.startsWith('../uploads/')) {
                img.src = src.replace('../', '/');
            } else if (src && src.startsWith('uploads/')) {
                img.src = '/' + src;
            }
            
            // Add proper styling if missing
            if (!img.className || !img.className.includes('inline-img')) {
                img.className = 'inline-img';
            }
            
            // Add alt attribute if missing
            if (!img.getAttribute('alt')) {
                img.setAttribute('alt', 'Content image');
            }
        });
    }

    // Rich Text Content Sanitization and Optimization
    function sanitizeAndOptimizeContent(rawContent) {
        if (!rawContent) return '';
        
        // Create a temporary div to parse HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = rawContent;
        
        // Sanitize and optimize content
        const sanitizedContent = sanitizeHTML(tempDiv);
        
        return sanitizedContent.innerHTML;
    }

    function sanitizeHTML(element) {
        // Remove script tags and dangerous content
        const scripts = element.querySelectorAll('script');
        scripts.forEach(script => script.remove());
        
        // Remove dangerous attributes
        const allElements = element.querySelectorAll('*');
        allElements.forEach(el => {
            // Remove unsafe attributes
            const unsafeAttrs = ['onclick', 'onload', 'onerror', 'onmouseover', 'onmouseout', 'style'];
            unsafeAttrs.forEach(attr => {
                if (el.hasAttribute(attr)) {
                    el.removeAttribute(attr);
                }
            });
            
            // Clean up class attributes
            if (el.className) {
                const classes = el.className.split(' ').filter(cls => 
                    !cls.includes('css-') && !cls.includes('automation-testid') && !cls.includes('tw-')
                );
                el.className = classes.join(' ');
            }
        });
        
        // Fix images
        const images = element.querySelectorAll('img');
        images.forEach(img => {
            fixImageTag(img);
        });
        
        // Fix links
        const links = element.querySelectorAll('a');
        links.forEach(link => {
            fixLinkTag(link);
        });
        
        // Wrap text nodes in paragraphs
        wrapTextNodes(element);
        
        // Apply standardized classes
        applyStandardClasses(element);
        
        return element;
    }

    function fixImageTag(img) {
        let src = img.getAttribute('src');
        if (!src) return;
        
        // Fix malformed image sources
        if (src.includes('data:image') && src.includes('../uploads/')) {
            // Extract the file path from mixed content
            const pathMatch = src.match(/(\/uploads\/[^'"\s]+)/);
            if (pathMatch) {
                src = pathMatch[1];
            }
        } else if (src.startsWith('../uploads/')) {
            src = src.replace('../', '/');
        } else if (src.startsWith('uploads/')) {
            src = '/' + src;
        }
        
        // Set clean src
        img.setAttribute('src', src);
        img.setAttribute('alt', img.getAttribute('alt') || 'Content image');
        img.setAttribute('loading', 'lazy');
        
        // Apply standardized class
        img.className = 'rich-img';
        
        // Remove unnecessary attributes
        ['class', 'style', 'width', 'height'].forEach(attr => {
            if (attr !== 'class' && img.hasAttribute(attr)) {
                img.removeAttribute(attr);
            }
        });
    }

    function fixLinkTag(link) {
        // Clean up href
        let href = link.getAttribute('href');
        if (href && !href.startsWith('http') && !href.startsWith('#')) {
            // Ensure external links open in new tab
            if (href.includes('http')) {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            }
        }
        
        // Apply standardized class
        link.className = 'rich-link';
        
        // Remove unsafe attributes
        ['onclick', 'style'].forEach(attr => {
            if (link.hasAttribute(attr)) {
                link.removeAttribute(attr);
            }
        });
    }

    function wrapTextNodes(element) {
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            if (node.textContent.trim().length > 0) {
                textNodes.push(node);
            }
        }
        
        textNodes.forEach(textNode => {
            const text = textNode.textContent.trim();
            if (text.length > 0) {
                const p = document.createElement('p');
                p.className = 'rich-text-p';
                p.textContent = text;
                textNode.parentNode.replaceChild(p, textNode);
            }
        });
    }

    function applyStandardClasses(element) {
        // Apply classes to common elements
        const tagClasses = {
            'p': 'rich-text-p',
            'h1': 'rich-heading',
            'h2': 'rich-heading',
            'h3': 'rich-heading',
            'h4': 'rich-heading',
            'h5': 'rich-heading',
            'h6': 'rich-heading',
            'ul': 'rich-list',
            'ol': 'rich-list',
            'blockquote': 'rich-blockquote',
            'code': 'rich-code'
        };
        
        Object.entries(tagClasses).forEach(([tag, className]) => {
            const elements = element.querySelectorAll(tag);
            elements.forEach(el => {
                el.className = className;
            });
        });
        
        // Remove empty tags
        const emptyTags = element.querySelectorAll('*:empty');
        emptyTags.forEach(tag => tag.remove());
        
        // Fix nesting errors
        fixNestingErrors(element);
    }

    function fixNestingErrors(element) {
        // Fix invalid nesting (e.g., block elements inside inline elements)
        const blockElements = ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre'];
        const inlineElements = ['span', 'a', 'strong', 'em', 'code'];
        
        blockElements.forEach(blockTag => {
            const blocks = element.querySelectorAll(blockTag);
            blocks.forEach(block => {
                inlineElements.forEach(inlineTag => {
                    const inlines = block.querySelectorAll(inlineTag);
                    inlines.forEach(inline => {
                        // Move inline elements outside if they contain block elements
                        if (inline.querySelector(blockElements.join(','))) {
                            const parent = inline.parentNode;
                            while (inline.firstChild) {
                                parent.insertBefore(inline.firstChild, inline);
                            }
                            parent.removeChild(inline);
                        }
                    });
                });
            });
        });
    }

    // Performance optimization: Lazy load content when needed
    function lazyLoadContent(subtopicId) {
        const content = document.getElementById(`content-${subtopicId}`);
        if (content && !content.dataset.optimized) {
            const rawContent = content.innerHTML;
            const optimizedContent = sanitizeAndOptimizeContent(rawContent);
            content.innerHTML = optimizedContent;
            content.classList.add('rich-text-content');
            content.dataset.optimized = 'true';
            
            // Initialize lazy loading for images
            initializeLazyImages(content);
        }
    }

    function initializeLazyImages(container) {
        const images = container.querySelectorAll('img[loading="lazy"]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    }

    // Enhanced toggle with lazy loading
    function toggleSubtopicWithLazyLoad(subtopicId) {
        const subtopic = document.getElementById(`subtopic-${subtopicId}`);
        const content = document.getElementById(`content-${subtopicId}`);
        
        if (subtopic && content) {
            const isExpanding = content.style.display === 'none';
            
            // Lazy load content only when expanding
            if (isExpanding) {
                lazyLoadContent(subtopicId);
            }
            
            // Toggle content display
            content.style.display = isExpanding ? 'block' : 'none';
            
            // Toggle expanded class
            if (isExpanding) {
                subtopic.classList.add('expanded');
            } else {
                subtopic.classList.remove('expanded');
            }
            
            // Update chevron
            const toggle = subtopic.querySelector('.subtopic-toggle i');
            if (toggle) {
                toggle.style.transform = isExpanding ? 'rotate(180deg)' : 'rotate(0deg)';
            }
            
            // Update progress bar
            updateNoteProgress(subtopicId.split('-')[0]);
        }
    }

    // Initialize content optimization on page load (only for visible content)
    document.addEventListener('DOMContentLoaded', () => {
        // Optimize only the first subtopic of each note for initial load
        const firstSubtopics = document.querySelectorAll('.subtopic[id$="-0"] .subtopic-content');
        firstSubtopics.forEach(content => {
            lazyLoadContent(content.id.replace('content-', ''));
        });
    });

    function updateNoteProgress(noteId) {
        const noteCard = document.querySelector(`#subtopic-${noteId}-0`).closest('.note-card');
        const subtopics = noteCard.querySelectorAll('.subtopic');
        const expandedSubtopics = noteCard.querySelectorAll('.subtopic.expanded');
        const progress = subtopics.length > 0 ? (expandedSubtopics.length / subtopics.length) * 100 : 0;
        
        const progressBar = noteCard.querySelector('.note-progress-fill');
        if (progressBar) {
            progressBar.style.width = `${progress}%`;
        }
    }

    function markAsComplete(noteId) {
        const formData = new FormData();
        formData.append('action', 'mark_complete');
        formData.append('note_id', noteId);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showToast('Note marked as complete!', 'success');
                
                // Update UI without reload
                const button = document.querySelector(`button[onclick="markAsComplete(${noteId})"]`);
                if (button) {
                    button.classList.add('completed');
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-check"></i> Completed';
                }
                
                // Update progress bar to 100%
                const noteCard = button.closest('.note-card');
                const progressBar = noteCard.querySelector('.note-progress-fill');
                if (progressBar) {
                    progressBar.style.width = '100%';
                }
                
                // Add completed indicator
                const title = noteCard.querySelector('.note-title');
                if (title && !title.querySelector('.progress-indicator')) {
                    const indicator = document.createElement('span');
                    indicator.className = 'progress-indicator';
                    indicator.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                    title.appendChild(indicator);
                }
            } else {
                showToast('Failed to mark as complete. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'error');
        });
    }

    function showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        
        // Add toast styles
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        `;
        
        document.body.appendChild(toast);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }

    // Study Timer functionality
    let studyTimers = {};

    function startStudyTimer(noteId) {
        const timerElement = document.getElementById(`timer-${noteId}`);
        const timerTextElement = document.getElementById(`timer-text-${noteId}`);
        
        if (!studyTimers[noteId]) {
            studyTimers[noteId] = {
                startTime: Date.now(),
                interval: null
            };
            
            studyTimers[noteId].interval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - studyTimers[noteId].startTime) / 1000);
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                
                timerTextElement.textContent = `Studying: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }, 1000);
            
            timerElement.style.background = 'linear-gradient(135deg, #10B981, #059669)';
            timerElement.onclick = () => stopStudyTimer(noteId);
        } else {
            stopStudyTimer(noteId);
        }
    }

    function stopStudyTimer(noteId) {
        if (studyTimers[noteId]) {
            clearInterval(studyTimers[noteId].interval);
            const elapsed = Math.floor((Date.now() - studyTimers[noteId].startTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            
            const timerTextElement = document.getElementById(`timer-text-${noteId}`);
            timerTextElement.textContent = `Studied: ${minutes} min`;
            
            const timerElement = document.getElementById(`timer-${noteId}`);
            timerElement.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
            timerElement.onclick = () => startStudyTimer(noteId);
            
            delete studyTimers[noteId];
            
            showToast(`Study session completed: ${minutes} minutes`, 'success');
        }
    }

    // Add timer click handlers and subtopic click handlers
    document.addEventListener('DOMContentLoaded', () => {
        // Study timer handlers
        document.querySelectorAll('.study-timer').forEach(timer => {
            const noteId = timer.id.replace('timer-', '');
            timer.addEventListener('click', () => startStudyTimer(noteId));
        });
        
        // Subtopic click handlers - alternative approach
        document.querySelectorAll('.subtopic-header').forEach(header => {
            header.addEventListener('click', function(e) {
                e.preventDefault();
                const subtopic = this.closest('.subtopic');
                if (subtopic) {
                    const subtopicId = subtopic.id.replace('subtopic-', '');
                    console.log('Header clicked, subtopicId:', subtopicId);
                    toggleSubtopic(subtopicId);
                }
            });
        });
    });

    function logout() {
        window.location.href = "../logout.php";
    }
    </script>
</body>
</html>
