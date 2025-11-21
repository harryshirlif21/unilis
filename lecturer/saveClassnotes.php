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
    global $conn;
    
    $processed_subtopics = [];
    foreach ($subtopics as $subtopic) {
        $processed_subtopic = [
            'id' => $subtopic['id'] ?? generateId(),
            'title' => $subtopic['title'] ?? '',
            'content' => $subtopic['content'] ?? '',
            'choices' => $subtopic['choices'] ?? [],
            'correctChoice' => $subtopic['correctChoice'] ?? null,
            'images' => [],
            'files' => []
        ];
        
        $subtopic_id = $processed_subtopic['id'];
        
        // Handle inline images
        if (isset($_FILES["subtopic_images"]) && isset($_FILES["subtopic_images"]["name"][$subtopic_id])) {
            $image_files = rearrayFiles($_FILES["subtopic_images"]["name"][$subtopic_id]);
            $image_tmp_names = rearrayFiles($_FILES["subtopic_images"]["tmp_name"][$subtopic_id]);
            $image_errors = rearrayFiles($_FILES["subtopic_images"]["error"][$subtopic_id]);
            
            foreach ($image_files as $index => $image_file) {
                if ($image_file && $image_tmp_names[$index] && $image_errors[$index] === UPLOAD_ERR_OK) {
                    $image_name = uniqid() . '_' . basename($image_file);
                    $image_path = "../uploads/images/" . $image_name;
                    
                    if (move_uploaded_file($image_tmp_names[$index], $image_path)) {
                        $processed_subtopic['images'][] = [
                            'name' => $image_name,
                            'original_name' => $image_file
                        ];
                        
                        // Replace placeholder with actual image path
                        if (isset($subtopic['images'][$index])) {
                            $placeholder = $subtopic['images'][$index]['placeholder'] ?? '';
                            if ($placeholder) {
                                $processed_subtopic['content'] = str_replace(
                                    'data-placeholder="' . $placeholder . '"', 
                                    'src="../uploads/images/' . $image_name . '"',
                                    $processed_subtopic['content']
                                );
                            }
                        }
                    }
                }
            }
        }
        
        // Handle file attachments
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