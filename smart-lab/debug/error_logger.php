<?php
/**
 * SmartLab Production Error Logger
 * Captures detailed error information from practical creation
 * Returns JSON response with error details
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display to user
ini_set('log_errors', '1');

// Load configuration
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database_production.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => []
];

try {
    // Test database connection
    $db = getDB();
    $response['data']['database_connection'] = 'success';
    $response['data']['database_info'] = [
        'version' => $db->query("SELECT VERSION() as v")->fetch()['v'],
        'database' => $db->query("SELECT DATABASE() as d")->fetch()['d']
    ];
    
    // Check practicals table structure
    $practicalsColumns = $db->query("SHOW COLUMNS FROM practicals")->fetchAll();
    $response['data']['practicals_columns'] = array_map(function($col) {
        return [
            'field' => $col['Field'],
            'type' => $col['Type'],
            'null' => $col['Null'],
            'key' => $col['Key'],
            'default' => $col['Default']
        ];
    }, $practicalsColumns);
    
    // Check if required columns exist
    $requiredColumns = ['id', 'title', 'description', 'lab_id', 'lecturer_id', 'scheduled_date', 'start_time', 'end_time', 'max_students', 'status', 'duration_hours', 'results_template', 'calculations_template'];
    $missingColumns = [];
    $existingColumns = array_column($practicalsColumns, 'Field');
    
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $existingColumns)) {
            $missingColumns[] = $col;
        }
    }
    
    $response['data']['column_check'] = [
        'required' => $requiredColumns,
        'existing' => $existingColumns,
        'missing' => $missingColumns
    ];
    
    if (!empty($missingColumns)) {
        $response['data']['error'] = 'Missing database columns: ' . implode(', ', $missingColumns);
        $response['data']['fix'] = 'Run migration: php smart-lab/migrations/add_rich_content_fields.php';
    } else {
        $response['data']['error'] = 'All required columns exist';
        $response['success'] = true;
    }
    
    // Test INSERT with minimal data
    $testId = bin2hex(random_bytes(16));
    $testData = [
        'id' => $testId,
        'title' => 'Error Logger Test',
        'description' => null,
        'lab_id' => null,
        'lecturer_id' => null,
        'scheduled_date' => null,
        'duration_hours' => 2,
        'max_students' => 30,
        'status' => 'draft',
        'course_code' => null,
        'start_time' => null,
        'end_time' => null,
        'required_equipment' => null,
        'required_chemicals' => null,
        'safety_notes' => null,
        'results_template' => null,
        'calculations_template' => null
    ];
    
    try {
        $stmt = $db->prepare(
            "INSERT INTO practicals 
             (id, title, description, lab_id, lecturer_id, scheduled_date, 
              duration_hours, max_students, status, course_code, 
              start_time, end_time, required_equipment, required_chemicals, 
              safety_notes, results_template, calculations_template)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $result = $stmt->execute([
            $testData['id'],
            $testData['title'],
            $testData['description'],
            $testData['lab_id'],
            $testData['lecturer_id'],
            $testData['scheduled_date'],
            $testData['duration_hours'],
            $testData['max_students'],
            $testData['status'],
            $testData['course_code'],
            $testData['start_time'],
            $testData['end_time'],
            $testData['required_equipment'],
            $testData['required_chemicals'],
            $testData['safety_notes'],
            $testData['results_template'],
            $testData['calculations_template']
        ]);
        
        if ($result) {
            $response['data']['test_insert'] = 'success';
            // Clean up test record
            $db->prepare("DELETE FROM practicals WHERE id = ?")->execute([$testId]);
        } else {
            $response['data']['test_insert'] = 'failed';
            $response['data']['statement_error'] = $stmt->errorInfo();
        }
    } catch (PDOException $e) {
        $response['data']['test_insert'] = 'exception';
        $response['data']['pdo_error'] = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'error_info' => $e->errorInfo
        ];
    }
    
} catch (Exception $e) {
    $response['data']['database_connection'] = 'failed';
    $response['data']['error'] = $e->getMessage();
    $response['data']['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
