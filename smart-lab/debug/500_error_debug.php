<?php
/**
 * SmartLab 500 Error Debug Tool
 * Diagnoses HTTP 500 errors on practicals page
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load configuration
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database_production.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

try {
    // Check 1: Database connection
    $db = getDB();
    $response['checks']['database'] = 'success';
    
    // Check 2: Required files exist
    $requiredFiles = [
        __DIR__ . '/../models/PracticalModel.php',
        __DIR__ . '/../controllers/PracticalController.php',
        __DIR__ . '/../utils/helpers.php',
        __DIR__ . '/../config/app.php'
    ];
    
    $missingFiles = [];
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            $missingFiles[] = basename($file);
        }
    }
    
    $response['checks']['required_files'] = [
        'status' => empty($missingFiles) ? 'success' : 'failed',
        'missing' => $missingFiles
    ];
    
    // Check 3: Model instantiation
    if (empty($missingFiles)) {
        require_once __DIR__ . '/../models/PracticalModel.php';
        require_once __DIR__ . '/../utils/helpers.php';
        
        try {
            $model = new PracticalModel();
            $response['checks']['model_instantiation'] = 'success';
            
            // Check 4: Model methods exist
            $requiredMethods = ['getAll', 'create', 'checkLabAvailability', 'getLabs'];
            $missingMethods = [];
            
            foreach ($requiredMethods as $method) {
                if (!method_exists($model, $method)) {
                    $missingMethods[] = $method;
                }
            }
            
            $response['checks']['model_methods'] = [
                'status' => empty($missingMethods) ? 'success' : 'failed',
                'missing' => $missingMethods
            ];
            
            // Check 5: Test getAll method
            if (empty($missingMethods)) {
                try {
                    $practicals = $model->getAll();
                    $response['checks']['getall_method'] = 'success';
                    $response['checks']['getall_count'] = count($practicals);
                } catch (Exception $e) {
                    $response['checks']['getall_method'] = 'failed';
                    $response['checks']['getall_error'] = $e->getMessage();
                }
            }
            
        } catch (Exception $e) {
            $response['checks']['model_instantiation'] = 'failed';
            $response['checks']['model_error'] = $e->getMessage();
        }
    }
    
    // Check 6: Routes file
    $routesFile = __DIR__ . '/../routes/web.php';
    if (file_exists($routesFile)) {
        $routesContent = file_get_contents($routesFile);
        $hasPracticalsRoute = strpos($routesContent, 'practicals') !== false;
        $response['checks']['routes_file'] = [
            'exists' => true,
            'has_practicals_route' => $hasPracticalsRoute
        ];
    } else {
        $response['checks']['routes_file'] = [
            'exists' => false
        ];
    }
    
    // Check 7: .htaccess file
    $htaccessFile = __DIR__ . '/../.htaccess';
    if (file_exists($htaccessFile)) {
        $htaccessContent = file_get_contents($htaccessFile);
        $response['checks']['htaccess'] = [
            'exists' => true,
            'content' => substr($htaccessContent, 0, 500) . '...'
        ];
    } else {
        $response['checks']['htaccess'] = [
            'exists' => false
        ];
    }
    
    // Check 8: PHP error log for recent errors
    $errorLogPaths = [
        '/var/log/php-error.log',
        '/var/log/apache2/error.log',
        '/usr/local/var/log/php_errors.log',
        '/home/logs/php_errors.log'
    ];
    
    $recentErrors = [];
    foreach ($errorLogPaths as $path) {
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            $recentLines = array_slice($lines, -20);
            foreach ($recentLines as $line) {
                if (preg_match('/(practicals|PracticalModel|PracticalController)/i', $line)) {
                    $recentErrors[] = $line;
                }
            }
            break;
        }
    }
    
    $response['checks']['recent_errors'] = $recentErrors;
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
