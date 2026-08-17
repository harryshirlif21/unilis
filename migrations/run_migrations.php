<?php
/**
 * Migration Runner
 * 
 * This script runs all pending migrations from the migrations directory.
 * It can be called from the admin dashboard or run manually.
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Only allow admin access
session_start();
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'department_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$migrationsDir = __DIR__;
$pendingMigrations = [];
$executedMigrations = [];

try {
    // Get list of migration files
    $files = scandir($migrationsDir);
    $migrationFiles = array_filter($files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'php' && $file !== 'run_migrations.php' && $file !== 'create_migration_tracking.php';
    });
    
    // Get already executed migrations
    $stmt = $conn->query("SELECT migration_name, executed_at, description FROM migrations ORDER BY executed_at ASC");
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $executedMigrations[$row['migration_name']] = $row;
        }
        $stmt->free();
    }
    
    // Identify pending migrations
    foreach ($migrationFiles as $file) {
        $migrationName = pathinfo($file, PATHINFO_FILENAME);
        if (!isset($executedMigrations[$migrationName])) {
            $pendingMigrations[] = [
                'file' => $file,
                'name' => $migrationName
            ];
        }
    }
    
    // If action is to run migrations
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_migrations') {
        $results = [];
        
        foreach ($pendingMigrations as $migration) {
            $migrationPath = $migrationsDir . '/' . $migration['file'];
            
            try {
                // Include and execute the migration
                ob_start();
                include $migrationPath;
                $output = ob_get_clean();
                
                $results[] = [
                    'name' => $migration['name'],
                    'status' => 'success',
                    'output' => $output
                ];
            } catch (Exception $e) {
                $results[] = [
                    'name' => $migration['name'],
                    'status' => 'error',
                    'output' => $e->getMessage()
                ];
                break; // Stop on first error
            }
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'executed_count' => count($results)
        ]);
        exit;
    }
    
    // Return status
    echo json_encode([
        'success' => true,
        'pending_count' => count($pendingMigrations),
        'executed_count' => count($executedMigrations),
        'pending_migrations' => $pendingMigrations,
        'executed_migrations' => array_values($executedMigrations)
    ]);
    
} catch (mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
