<?php
session_start();
define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

try {
    require_once DOCUMENT_ROOT . '/smart-lab/config/app.php';

    $action = $_GET['action'] ?? null;
    $datasheetId = $_GET['datasheet_id'] ?? null;

    if ($action !== 'download' || !$datasheetId) {
        http_response_code(400);
        die('Invalid request parameters');
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        die('Unauthorized: Please log in');
    }

    $db = getDB();

    $stmt = $db->prepare(
        "SELECT d.* FROM datasheets d 
         WHERE d.id = ? AND d.student_id = ? 
         LIMIT 1"
    );
    $stmt->execute([$datasheetId, $userId]);
    $datasheet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$datasheet) {
        http_response_code(403);
        die('Access denied: Datasheet not found or does not belong to you');
    }

    $filePath = $_SERVER['DOCUMENT_ROOT'] . $datasheet['pdf_path'];

    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File not found');
    }

    $filesize = filesize($filePath);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($datasheet['pdf_filename']) . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($filePath);

    $controller = new \SmartLab\Controllers\DatasheetController($db);
    $controller->downloadDatasheet($datasheetId);

    exit;

} catch (\Exception $e) {
    error_log('Download Error: ' . $e->getMessage());
    http_response_code(500);
    die('Download failed: ' . $e->getMessage());
}
?>
