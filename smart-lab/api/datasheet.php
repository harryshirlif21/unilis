<?php
header('Content-Type: application/json');

session_start();
define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

try {
    require_once DOCUMENT_ROOT . '/smart-lab/config/app.php';
    require_once DOCUMENT_ROOT . '/smart-lab/config/database.php';
    require_once DOCUMENT_ROOT . '/smart-lab/includes/autoloader.php';

    $input = json_decode(file_get_contents('php://input'), true);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    if (empty($input['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing action parameter']);
        exit;
    }

    $action = $input['action'];

    if ($action === 'generate') {
        $studentId = $input['student_id'] ?? $_SESSION['user_id'] ?? null;
        $practicalId = $input['practical_id'] ?? null;
        $authMethod = $input['authentication_method'] ?? 'password';

        if (!$studentId || !$practicalId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing student_id or practical_id']);
            exit;
        }

        $db = getDB();
        $logoPath = realpath(__DIR__ . '/../jkuatlogo.jpg') ?: __DIR__ . '/../jkuatlogo.jpg';

        $controller = new \SmartLab\Controllers\DatasheetController($db, $logoPath);
        $result = $controller->generateDatasheet($studentId, $practicalId, $authMethod);

        if ($result['success']) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Datasheet generated successfully',
                'datasheet_id' => $result['datasheet_id'],
                'pdf_path' => $result['pdf_path'],
                'approval_status' => $result['approval_status'],
                'qr_code_path' => $result['qr_code_path']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }

    } elseif ($action === 'list') {
        $studentId = $input['student_id'] ?? $_SESSION['user_id'] ?? null;

        if (!$studentId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $db = getDB();
        $controller = new \SmartLab\Controllers\DatasheetController($db);
        $datasheets = $controller->getStudentDatasheets($studentId);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'datasheets' => $datasheets,
            'count' => count($datasheets)
        ]);

    } elseif ($action === 'verify') {
        $datasheetId = $input['datasheet_id'] ?? null;
        $signatureHash = $input['signature_hash'] ?? null;

        if (!$datasheetId || !$signatureHash) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing datasheet_id or signature_hash']);
            exit;
        }

        $db = getDB();
        $controller = new \SmartLab\Controllers\DatasheetController($db);
        $result = $controller->verifyDatasheet($datasheetId, $signatureHash);

        http_response_code($result['valid'] ? 200 : 400);
        echo json_encode($result);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
    }

} catch (\Exception $e) {
    error_log('Datasheet API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
