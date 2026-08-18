<?php
/**
 * AJAX: Update lesson numbering for a course
 * 
 * This script renumbers all lessons in a course to ensure sequential ordering
 * based on their position within modules.
 */

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../learn/includes/authoring.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$course_id = (int)($_POST['course_id'] ?? 0);

if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Course ID required']);
    exit;
}

try {
    // Get all modules for the course
    $stmt = $conn->prepare("
        SELECT id FROM public_course_modules 
        WHERE course_id = ? 
        ORDER BY position ASC, id ASC
    ");
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $lessonsUpdated = 0;
    
    // Reposition lessons in each module
    foreach ($modules as $module) {
        $moduleId = (int)$module['id'];
        catalogue_reposition_lessons($conn, $moduleId);
        
        // Count lessons in this module
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM public_course_lessons WHERE module_id = ?");
        $countStmt->bind_param('i', $moduleId);
        $countStmt->execute();
        $result = $countStmt->get_result()->fetch_assoc();
        $lessonsUpdated += (int)$result['count'];
        $countStmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Lesson numbering updated successfully',
        'lessons_updated' => $lessonsUpdated
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
