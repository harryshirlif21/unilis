<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Create upload directories if they don't exist
$upload_dirs = ['../uploads/images', '../uploads/files'];
foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => "Failed to create upload directory: $dir"]);
            exit;
        }
    }
}

try {
    $lecturer_id = $_SESSION['user_id'];
    $unit_id = $_POST['unit_id'] ?? null;
    $topics_json = $_POST['topics'] ?? '[]';
    $action = $_POST['action'] ?? 'create';
    $topic_id = $_POST['topic_id'] ?? null;
    
    if (!$unit_id) {
        throw new Exception('Unit ID is required');
    }
    
    // Verify that the lecturer teaches this unit
    $stmt = $conn->prepare("SELECT id FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
    $stmt->bind_param("ii", $lecturer_id, $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('You are not assigned to teach this unit');
    }
    $stmt->close();
    
    $topics = json_decode($topics_json, true);
    if (!is_array($topics)) {
        throw new Exception('Invalid topics data');
    }
    
    // Handle UPDATE action
    if ($action === 'update') {
        if (!$topic_id) {
            throw new Exception('Topic ID is required for update');
        }
        
        // Verify the topic belongs to this lecturer
        $check_stmt = $conn->prepare("SELECT id FROM classnotes WHERE id = ? AND lecturer_id = ?");
        $check_stmt->bind_param("ii", $topic_id, $lecturer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception('Topic not found or you do not have permission to edit it');
        }
        $check_stmt->close();
        
        // Process the topic for update
        $topic = $topics[0] ?? null;
        if (!$topic) {
            throw new Exception('No topic data provided for update');
        }
        
        $topic_title = $topic['title'] ?? '';
        $subtopics = $topic['subtopics'] ?? [];
        
        if (empty($topic_title)) {
            throw new Exception('Topic title is required');
        }
        
        // Process subtopics
        $processed_subtopics = processSubtopics($subtopics);
        $subtopics_json = json_encode($processed_subtopics);
        
        // Update the topic
        $stmt = $conn->prepare("
            UPDATE classnotes 
            SET title = ?, subtopics_json = ?, uploaded_at = NOW()
            WHERE id = ? AND lecturer_id = ?
        ");
        
        $stmt->bind_param("ssii", $topic_title, $subtopics_json, $topic_id, $lecturer_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update topic: ' . $stmt->error);
        }
        
        $message = 'Notes updated successfully!';
        $stmt->close();
        
    } else {
        // Handle CREATE action
        if (empty($topics)) {
            throw new Exception('No topics to save');
        }
        
        foreach ($topics as $topic) {
            $topic_title = $topic['title'] ?? '';
            $subtopics = $topic['subtopics'] ?? [];
            
            if (empty($topic_title)) {
                continue;
            }
            
            // Process subtopics
            $processed_subtopics = processSubtopics($subtopics);
            $subtopics_json = json_encode($processed_subtopics);
            
            // Create new topic
            $stmt = $conn->prepare("
                INSERT INTO classnotes (unit_id, lecturer_id, title, subtopics_json, uploaded_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("iiss", $unit_id, $lecturer_id, $topic_title, $subtopics_json);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to save topic: ' . $stmt->error);
            }
            
            $stmt->close();
        }
        
        $message = 'Notes saved successfully!';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message
    ]);
    
} catch (Exception $e) {
    error_log("Error saving notes: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function processSubtopics($subtopics) {
    $processed_subtopics = [];
    
    foreach ($subtopics as $subtopic) {
        $subtopic_id = $subtopic['id'] ?? generateId();
        $content = $subtopic['content'] ?? '';
        
        $processed_subtopic = [
            'id' => $subtopic_id,
            'title' => $subtopic['title'] ?? '',
            'content' => $content,
            'choices' => $subtopic['choices'] ?? [],
            'correctChoice' => $subtopic['correctChoice'] ?? null,
            'images' => [],
            'files' => []
        ];
        
        // Extract and save base64 images from content
        $content = saveBase64Images($content, $processed_subtopic['images']);
        $processed_subtopic['content'] = $content;
        
        // Handle file attachments from $_FILES
        if (isset($_FILES["subtopic_files"]) && isset($_FILES["subtopic_files"]["name"][$subtopic_id])) {
            $file_names = rearrayFiles($_FILES["subtopic_files"]["name"][$subtopic_id]);
            $file_tmp_names = rearrayFiles($_FILES["subtopic_files"]["tmp_name"][$subtopic_id]);
            $file_errors = rearrayFiles($_FILES["subtopic_files"]["error"][$subtopic_id]);
            
            foreach ($file_names as $index => $file_name) {
                if ($file_name && $file_tmp_names[$index] && $file_errors[$index] === UPLOAD_ERR_OK) {
                    $saved_file_name = uniqid() . '_' . basename($file_name);
                    $file_path = "../uploads/files/" . $saved_file_name;
                    
                    if (move_uploaded_file($file_tmp_names[$index], $file_path)) {
                        $processed_subtopic['files'][] = [
                            'name' => $saved_file_name,
                            'original_name' => $file_name,
                            'label' => $file_name
                        ];
                    }
                }
            }
        }
        
        $processed_subtopics[] = $processed_subtopic;
    }
    
    return $processed_subtopics;
}

/**
 * Find all base64 images in content, save them to files, and replace with file paths
 */
function saveBase64Images($content, &$images_array) {
    // Pattern to match base64 image in src attribute
    $pattern = '/(<img[^>]*?)src=["\']data:image\/([a-zA-Z]+);base64,([^"\']+)["\']([^>]*>)/i';
    
    $content = preg_replace_callback($pattern, function($matches) use (&$images_array) {
        $before = $matches[1];  // Everything before src
        $type = $matches[2];    // image type (jpeg, png, gif, etc.)
        $base64 = $matches[3];  // base64 data
        $after = $matches[4];   // Everything after the src value including >
        
        // Decode base64 data
        $image_data = base64_decode($base64);
        
        if ($image_data === false) {
            // If decoding fails, return original
            return $matches[0];
        }
        
        // Generate filename
        $ext = ($type === 'jpeg') ? 'jpg' : $type;
        $filename = uniqid() . '_image.' . $ext;
        $filepath = "../uploads/images/" . $filename;
        
        // Save the image
        if (file_put_contents($filepath, $image_data)) {
            // Add to images array
            $images_array[] = [
                'name' => $filename,
                'original_name' => $filename
            ];
            
            // Remove data-placeholder attribute if present
            $before = preg_replace('/\s*data-placeholder=["\'][^"\']*["\']\s*/i', ' ', $before);
            $after = preg_replace('/\s*data-placeholder=["\'][^"\']*["\']\s*/i', ' ', $after);
            
            // Build new img tag with file path
            $new_tag = $before . 'src="../uploads/images/' . $filename . '"' . $after;
            
            // Clean up extra spaces
            $new_tag = preg_replace('/\s+/', ' ', $new_tag);
            
            return $new_tag;
        }
        
        // If save fails, return original
        return $matches[0];
        
    }, $content);
    
    return $content;
}

function rearrayFiles($file_array) {
    $rearrayed = [];
    if (is_array($file_array)) {
        foreach ($file_array as $key => $value) {
            $rearrayed[] = $value;
        }
    } else {
        $rearrayed[] = $file_array;
    }
    return $rearrayed;
}

function generateId() {
    return rand(1000, 9999);
}
?>