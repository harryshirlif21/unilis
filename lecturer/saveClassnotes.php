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
    
    // Process each topic
    foreach ($topics as $topic) {
        $topic_title = $topic['title'] ?? '';
        $subtopics = $topic['subtopics'] ?? [];
        
        if (empty($topic_title)) {
            continue; // Skip empty topics
        }
        
        // Process subtopics to handle images and files
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
            
            // Handle inline images
            $subtopic_id = $processed_subtopic['id'];
            if (isset($_FILES["subtopic_images"]) && isset($_FILES["subtopic_images"]["name"][$subtopic_id])) {
                $image_files = $_FILES["subtopic_images"]["name"][$subtopic_id];
                $image_tmp_names = $_FILES["subtopic_images"]["tmp_name"][$subtopic_id];
                $image_errors = $_FILES["subtopic_images"]["error"][$subtopic_id];
                
                // Ensure we have arrays
                if (!is_array($image_files)) {
                    $image_files = [$image_files];
                    $image_tmp_names = [$image_tmp_names];
                    $image_errors = [$image_errors];
                }
                
                for ($i = 0; $i < count($image_files); $i++) {
                    if ($image_files[$i] && $image_tmp_names[$i] && $image_errors[$i] === UPLOAD_ERR_OK) {
                        $image_name = uniqid() . '_' . basename($image_files[$i]);
                        $image_path = "../uploads/images/" . $image_name;
                        
                        if (move_uploaded_file($image_tmp_names[$i], $image_path)) {
                            $processed_subtopic['images'][] = [
                                'name' => $image_name,
                                'original_name' => $image_files[$i]
                            ];
                            
                            // Find the corresponding image placeholder from the subtopic data
                            if (isset($subtopic['images'][$i])) {
                                $placeholder = $subtopic['images'][$i]['placeholder'];
                                // Replace the data URL with the actual file path
                                $processed_subtopic['content'] = str_replace(
                                    'src="data:image/', 
                                    'data-placeholder="' . $placeholder . '" src="../uploads/images/' . $image_name . '"',
                                    $processed_subtopic['content']
                                );
                            }
                        }
                    }
                }
            }
            
            // Handle file attachments
            if (isset($_FILES["subtopic_files"]) && isset($_FILES["subtopic_files"]["name"][$subtopic_id])) {
                $file_names = $_FILES["subtopic_files"]["name"][$subtopic_id];
                $file_tmp_names = $_FILES["subtopic_files"]["tmp_name"][$subtopic_id];
                $file_errors = $_FILES["subtopic_files"]["error"][$subtopic_id];
                
                // Ensure we have arrays
                if (!is_array($file_names)) {
                    $file_names = [$file_names];
                    $file_tmp_names = [$file_tmp_names];
                    $file_errors = [$file_errors];
                }
                
                for ($i = 0; $i < count($file_names); $i++) {
                    if ($file_names[$i] && $file_tmp_names[$i] && $file_errors[$i] === UPLOAD_ERR_OK) {
                        $file_name = uniqid() . '_' . basename($file_names[$i]);
                        $file_path = "../uploads/files/" . $file_name;
                        
                        if (move_uploaded_file($file_tmp_names[$i], $file_path)) {
                            $processed_subtopic['files'][] = [
                                'name' => $file_name,
                                'original_name' => $file_names[$i],
                                'label' => $file_names[$i]
                            ];
                        }
                    }
                }
            }
            
            $processed_subtopics[] = $processed_subtopic;
        }
        
        // FIXED: Use the correct column names for your database
        // Your table has: unit_id, lecturer_id, title, subtopics_json, uploaded_at
        $stmt = $conn->prepare("
            INSERT INTO classnotes (unit_id, lecturer_id, title, subtopics_json, uploaded_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $subtopics_json = json_encode($processed_subtopics);
        $stmt->bind_param("iiss", $unit_id, $lecturer_id, $topic_title, $subtopics_json);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save topic: ' . $stmt->error);
        }
        
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Notes saved successfully!'
    ]);
    
} catch (Exception $e) {
    error_log("Error saving notes: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error saving notes: ' . $e->getMessage()
    ]);
}

function generateId() {
    return rand(1000, 9999);
}
?>