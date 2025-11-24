<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

$response = ['success' => false, 'data' => null, 'error' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? $input['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('No action specified');
    }
    
    $user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? 0;
    $meeting_id = $input['meeting_id'] ?? $_GET['meeting_id'] ?? 0;
    
    if (!$user_id || !$meeting_id) {
        throw new Exception('Invalid user or meeting ID');
    }
    
    switch ($action) {
        case 'send_offer':
            $response = sendOffer($user_id, $meeting_id, $input);
            break;
            
        case 'send_answer':
            $response = sendAnswer($user_id, $meeting_id, $input);
            break;
            
        case 'send_candidate':
            $response = sendCandidate($user_id, $meeting_id, $input);
            break;
            
        case 'get_signals':
            $response = getSignals($user_id, $meeting_id, $input);
            break;
            
        case 'delete_signal':
            $response = deleteSignal($user_id, $meeting_id, $input);
            break;
            
        case 'send_chunk':
            $response = sendChunk($user_id, $meeting_id, $input);
            break;
            
        case 'get_chunks':
            $response = getChunks($user_id, $meeting_id, $input);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Signaling API error: " . $e->getMessage());
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);

function sendOffer($user_id, $meeting_id, $data) {
    $offer = sanitizeInput($data['offer']);
    $to_user_id = intval($data['to_user_id'] ?? 0);
    
    $sql = "INSERT INTO signal_queue (meeting_id, from_user_id, to_user_id, signal_type, signal_data) 
            VALUES (?, ?, ?, 'offer', ?)";
    
    $result = executeQuery($sql, [$meeting_id, $user_id, $to_user_id, $offer], "iiis");
    
    return ['success' => (bool)$result, 'signal_id' => $result];
}

function sendAnswer($user_id, $meeting_id, $data) {
    $answer = sanitizeInput($data['answer']);
    $to_user_id = intval($data['to_user_id'] ?? 0);
    
    $sql = "INSERT INTO signal_queue (meeting_id, from_user_id, to_user_id, signal_type, signal_data) 
            VALUES (?, ?, ?, 'answer', ?)";
    
    $result = executeQuery($sql, [$meeting_id, $user_id, $to_user_id, $answer], "iiis");
    
    return ['success' => (bool)$result, 'signal_id' => $result];
}

function sendCandidate($user_id, $meeting_id, $data) {
    $candidate = sanitizeInput($data['candidate']);
    $to_user_id = intval($data['to_user_id'] ?? 0);
    
    $sql = "INSERT INTO signal_queue (meeting_id, from_user_id, to_user_id, signal_type, signal_data) 
            VALUES (?, ?, ?, 'candidate', ?)";
    
    $result = executeQuery($sql, [$meeting_id, $user_id, $to_user_id, $candidate], "iiis");
    
    return ['success' => (bool)$result, 'signal_id' => $result];
}

function getSignals($user_id, $meeting_id, $data) {
    $last_signal_id = intval($data['last_signal_id'] ?? 0);
    $limit = min(25, max(5, intval($data['limit'] ?? 10)));
    
    $sql = "SELECT sq.id, sq.from_user_id, sq.to_user_id, sq.signal_type, sq.signal_data, sq.created_at,
                   u.name as from_user_name, u.role as from_user_role
            FROM signal_queue sq
            JOIN users u ON u.id = sq.from_user_id
            WHERE sq.meeting_id = ? AND sq.consumed = 0 
            AND (sq.to_user_id IS NULL OR sq.to_user_id = ? OR sq.from_user_id = ?)
            AND sq.id > ?
            ORDER BY sq.id ASC
            LIMIT ?";
    
    $signals = executeQuery($sql, [$meeting_id, $user_id, $user_id, $last_signal_id, $limit], "iiiii");
    
    if (!empty($signals)) {
        // Mark signals as consumed for this user
        $signal_ids = array_column($signals, 'id');
        $placeholders = implode(',', array_fill(0, count($signal_ids), '?'));
        
        $update_sql = "UPDATE signal_queue SET consumed = 1 WHERE id IN ($placeholders)";
        executeQuery($update_sql, $signal_ids, str_repeat("i", count($signal_ids)));
    }
    
    return ['success' => true, 'signals' => $signals ?: []];
}

function deleteSignal($user_id, $meeting_id, $data) {
    $signal_id = intval($data['signal_id']);
    
    $sql = "DELETE FROM signal_queue WHERE id = ? AND meeting_id = ? AND (from_user_id = ? OR to_user_id = ?)";
    $result = executeQuery($sql, [$signal_id, $meeting_id, $user_id, $user_id], "iiii");
    
    return ['success' => (bool)$result];
}

function sendChunk($user_id, $meeting_id, $data) {
    $recording_id = intval($data['recording_id']);
    $chunk_index = intval($data['chunk_index']);
    $chunk_data = $data['chunk_data']; // Base64 encoded
    
    // Validate recording access
    $sql = "SELECT 1 FROM recordings WHERE id = ? AND meeting_id = ?";
    $valid = executeQuery($sql, [$recording_id, $meeting_id], "ii");
    
    if (empty($valid)) {
        throw new Exception('Invalid recording access');
    }
    
    $chunk_binary = base64_decode($chunk_data);
    $chunk_size = strlen($chunk_binary);
    
    $sql = "INSERT INTO recordings_chunks (recording_id, chunk_index, chunk_data, chunk_size) 
            VALUES (?, ?, ?, ?)";
    
    $result = executeQuery($sql, [$recording_id, $chunk_index, $chunk_binary, $chunk_size], "iibi");
    
    return ['success' => (bool)$result, 'chunk_id' => $result];
}

function getChunks($user_id, $meeting_id, $data) {
    $recording_id = intval($data['recording_id']);
    
    // Validate recording access
    $sql = "SELECT 1 FROM recordings r 
            JOIN meetings m ON m.id = r.meeting_id 
            WHERE r.id = ? AND (m.lecturer_id = ? OR ? IN (
                SELECT student_id FROM student_unit WHERE unit_id = m.unit_id
            ))";
    $valid = executeQuery($sql, [$recording_id, $user_id, $user_id], "iii");
    
    if (empty($valid)) {
        throw new Exception('Invalid recording access');
    }
    
    $sql = "SELECT chunk_index, chunk_data, chunk_size 
            FROM recordings_chunks 
            WHERE recording_id = ? 
            ORDER BY chunk_index ASC";
    
    $chunks = executeQuery($sql, [$recording_id], "i");
    
    // Convert blob to base64 for response
    foreach ($chunks as &$chunk) {
        $chunk['chunk_data'] = base64_encode($chunk['chunk_data']);
    }
    
    return ['success' => true, 'chunks' => $chunks ?: []];
}
?>