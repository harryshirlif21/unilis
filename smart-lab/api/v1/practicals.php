<?php
$_is_prod = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);
if ($_is_prod) {
    require_once __DIR__.'/../../config/app_production.php';
    require_once __DIR__.'/../../config/database_production.php';
} else {
    require_once __DIR__.'/../../config/app.php';
    require_once __DIR__.'/../../config/database_production.php';
}
require_once __DIR__.'/../../utils/helpers.php';
require_once __DIR__.'/../../auth/Auth.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$request = json_decode(file_get_contents('php://input'), true) ?? [];

function ensureStudentAuth(): void {
    if (!Auth::check()) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }
    if (Auth::role() !== 'student') {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
}


// GET /api/v1/practicals/:id - Get practical details for students
if ($method === 'GET' && isset($_GET['id'])) {
    ensureStudentAuth();
    
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

// POST /api/v1/practicals/:id/start - Confirm attendance and allow practical access
elseif ($method === 'POST' && isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'start') {
    ensureStudentAuth();

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

        // Ensure attendance has been marked first
        $stmt = $db->prepare("SELECT id FROM attendance WHERE student_id = ? AND practical_id = ?");
        $stmt->execute([$studentId, $practicalId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Attendance not found. Please verify your attendance first.'], 400);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Attendance confirmed. You can proceed to the practical session.'
        ]);

    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Database error'], 500);
    }
}

// GET /api/v1/practicals/:id/report - Get student's report for this practical
elseif ($method === 'GET' && isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'report') {
    ensureStudentAuth();

    $practicalId = sanitize($_GET['id']);
    $studentId = Auth::id();

    try {
        $db = getDB();

        // Get student's report
        $stmt = $db->prepare("
            SELECT * FROM lab_reports
            WHERE practical_id = ? AND student_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$practicalId, $studentId]);
        $report = $stmt->fetch();

        if ($report) {
            $report['observations'] = json_decode($report['observations_json'] ?? '[]', true);
            unset($report['observations_json']);

            jsonResponse([
                'report' => $report
            ]);
        } else {
            jsonResponse(['error' => 'No report found'], 404);
        }

    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Database error'], 500);
    }
}

// POST /api/v1/practicals/:id/save-draft - Save draft report
elseif ($method === 'POST' && isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'save-draft') {
    ensureStudentAuth();

    $practicalId = sanitize($_GET['id']);
    $studentId = Auth::id();

    try {
        $db = getDB();

        // Check if student has an in-progress report
        $stmt = $db->prepare("SELECT id FROM lab_reports WHERE practical_id = ? AND student_id = ? AND status = 'in_progress'");
        $stmt->execute([$practicalId, $studentId]);
        $existingReport = $stmt->fetch();

        if (!$existingReport) {
            // Create a new report record only when a draft is being saved
            $newReportId = bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO lab_reports (id, practical_id, student_id, status, created_at) VALUES (?, ?, ?, 'in_progress', NOW())");
            $stmt->execute([$newReportId, $practicalId, $studentId]);
            $existingReport = ['id' => $newReportId];
        }

        // Update draft data (don't change status)
        $stmt = $db->prepare("
            UPDATE lab_reports
            SET observations_json = ?, calculations = ?, result = ?, conclusion = ?, updated_at = NOW()
            WHERE id = ? AND practical_id = ? AND student_id = ? AND status = 'in_progress'
        ");

        $stmt->execute([
            json_encode($request['observations'] ?? []),
            $request['calculations'] ?? '',
            $request['result'] ?? '',
            $request['conclusion'] ?? '',
            $existingReport['id'],
            $practicalId,
            $studentId
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Failed to save draft'], 400);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Draft saved successfully'
        ]);

    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Database error'], 500);
    }
}

// POST /api/v1/practicals/:id/submit-report - Submit student lab report
elseif ($method === 'POST' && isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'submit-report') {
    ensureStudentAuth();

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

        // Check if student has an in-progress report
        $stmt = $db->prepare("SELECT id FROM lab_reports WHERE practical_id = ? AND student_id = ? AND status = 'in_progress'");
        $stmt->execute([$practicalId, $studentId]);
        $existingReport = $stmt->fetch();

        if (!$existingReport) {
            // Create a new in-progress report if one does not exist
            $newReportId = bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO lab_reports (id, practical_id, student_id, status, created_at) VALUES (?, ?, ?, 'in_progress', NOW())");
            $stmt->execute([$newReportId, $practicalId, $studentId]);
            $existingReport = ['id' => $newReportId];
        }

        // Update report with submission data and change status to submitted
        $stmt = $db->prepare("
            UPDATE lab_reports
            SET observations_json = ?, calculations = ?, result = ?, conclusion = ?,
                status = 'submitted', submitted_at = NOW(), updated_at = NOW()
            WHERE id = ? AND practical_id = ? AND student_id = ? AND status = 'in_progress'
        ");

        $stmt->execute([
            json_encode($request['observations'] ?? []),
            $request['calculations'] ?? '',
            $request['result'] ?? '',
            $request['conclusion'] ?? '',
            $existingReport['id'],
            $practicalId,
            $studentId
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Failed to submit report'], 400);
        }

        jsonResponse([
            'success' => true,
            'report_id' => $existingReport['id'],
            'message' => 'Lab report submitted successfully'
        ]);

    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Database error'], 500);
    }
}

// POST /api/v1/practicals/start - Initialize practical session after attendance
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'start') {
    ensureStudentAuth();

    $studentId = Auth::id();
    $practicalId = sanitize($request['practical_id'] ?? '');

    if (empty($practicalId)) {
        jsonResponse(['error' => 'Practical ID is required'], 400);
    }

    try {
        $db = getDB();

        // Check attendance exists
        $stmt = $db->prepare("SELECT id FROM attendance WHERE student_id = ? AND practical_id = ?");
        $stmt->execute([$studentId, $practicalId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Attendance not found. Please verify your attendance first.'], 400);
        }

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
            jsonResponse(['error' => 'Practical not found or not available'], 404);
        }

        // Parse JSON fields
        $practical['procedure'] = json_decode($practical['procedure_json'] ?? '[]', true);
        $practical['observations_table'] = json_decode($practical['observations_table_structure'] ?? '[]', true);
        $practical['apparatus'] = array_filter(explode("\n", $practical['required_equipment'] ?? ''));
        $practical['chemicals'] = array_filter(explode("\n", $practical['required_chemicals'] ?? ''));

        // Remove raw JSON fields
        unset($practical['procedure_json'], $practical['observations_table_structure']);

        // Check if report already exists
        $stmt = $db->prepare("SELECT id, status FROM lab_reports WHERE practical_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$practicalId, $studentId]);
        $existingReport = $stmt->fetch();

        if ($existingReport) {
            if ($existingReport['status'] === 'submitted') {
                jsonResponse(['error' => 'You have already submitted a report for this practical'], 400);
            }

            // Return existing in-progress report
            jsonResponse([
                'success' => true,
                'report_id' => $existingReport['id'],
                'practical' => $practical,
                'message' => 'Continuing existing practical attempt'
            ]);
        } else {
            // Create new report
            $reportId = bin2hex(random_bytes(16));
            $stmt = $db->prepare("
                INSERT INTO lab_reports
                (id, practical_id, student_id, status, created_at)
                VALUES (?, ?, ?, 'in_progress', NOW())
            ");
            $stmt->execute([$reportId, $practicalId, $studentId]);

            jsonResponse([
                'success' => true,
                'report_id' => $reportId,
                'practical' => $practical,
                'message' => 'Practical session started successfully'
            ]);
        }

    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Database error'], 500);
    }
}


