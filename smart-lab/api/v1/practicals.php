<?php
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../utils/helpers.php';
require_once __DIR__.'/../../auth/Auth.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$request = json_decode(file_get_contents('php://input'), true) ?? [];

// GET /api/v1/practicals/:id - Get practical details for students
if ($method === 'GET' && isset($_GET['id'])) {
    Auth::guard('student');
    
    $practicalId = sanitize($_GET['id']);
    
    try {
        $db = getDB();
        
        // Get practical details
        $stmt = $db->prepare("
            SELECT p.*, l.name as lab_name, l.lab_code,
                   u.full_name as lecturer_name
            FROM practicals p
            LEFT JOIN labs l ON p.lab_id = l.id
            LEFT JOIN users u ON p.lecturer_id = u.id
            WHERE p.id = ? AND p.status = 'published'
        ");
        $stmt->execute([$practicalId]);
        $practical = $stmt->fetch();
        
        if (!$practical) {
            jsonResponse(['error' => 'Practical not found'], 404);
        }
        
        // Parse JSON fields
        $practical['procedure'] = json_decode($practical['procedure_json'] ?? '[]', true);
        $practical['observations_table'] = json_decode($practical['observations_table_structure'] ?? '[]', true);
        $practical['apparatus'] = array_filter(explode("\n", $practical['required_equipment'] ?? ''));
        $practical['chemicals'] = array_filter(explode("\n", $practical['required_chemicals'] ?? ''));
        
        // Remove raw JSON fields
        unset($practical['procedure_json'], $practical['observations_table_structure']);
        
        jsonResponse([
            'practical' => $practical
        ]);
        
    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Database error'], 500);
    }
}

// POST /api/v1/practicals/:id/reports - Submit student lab report
elseif ($method === 'POST' && isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'submit-report') {
    Auth::guard('student');
    
    $practicalId = sanitize($_GET['id']);
    $studentId = Auth::id();
    
    try {
        $db = getDB();
        
        // Validate practical exists and is published
        $stmt = $db->prepare("SELECT id FROM practicals WHERE id = ? AND status = 'published'");
        $stmt->execute([$practicalId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Practical not found or not available'], 404);
        }
        
        // Check if student already submitted
        $stmt = $db->prepare("SELECT id FROM lab_reports WHERE practical_id = ? AND student_id = ?");
        $stmt->execute([$practicalId, $studentId]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Report already submitted for this practical'], 400);
        }
        
        // Insert report
        $stmt = $db->prepare("
            INSERT INTO lab_reports 
            (id, practical_id, student_id, observations_json, calculations, result, conclusion, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $reportId = bin2hex(random_bytes(16));
        $stmt->execute([
            $reportId,
            $practicalId,
            $studentId,
            json_encode($request['observations'] ?? []),
            $request['calculations'] ?? '',
            $request['result'] ?? '',
            $request['conclusion'] ?? ''
        ]);
        
        jsonResponse([
            'success' => true,
            'report_id' => $reportId,
            'message' => 'Lab report submitted successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Database error'], 500);
    }
}

else {
    jsonResponse(['error' => 'Invalid request'], 400);
}
