<?php
/**
 * UNILIS SmartLabs - Academic Integrity Workflow Migration Runner
 * 
 * Run this script to create all workflow tables and apply schema changes.
 * 
 * Usage: php run_migration.php
 * Or via browser: https://your-domain/smart-lab/migrations/workflow/run_migration.php
 */

echo "<pre>";
echo "============================================\n";
echo "UNILIS SmartLabs - Workflow Migration Tool\n";
echo "============================================\n\n";

// Load database config
$basePath = __DIR__ . '/../../';
require_once $basePath . 'config/database.php';

try {
    $db = getDB();
    echo "[OK] Database connection established\n\n";
} catch (Exception $e) {
    die("[ERROR] Database connection failed: " . $e->getMessage() . "\n");
}

// Run migration SQL
$migrationFile = __DIR__ . '/001_strict_workflow_tables.sql';
if (!file_exists($migrationFile)) {
    die("[ERROR] Migration file not found: {$migrationFile}\n");
}

$sql = file_get_contents($migrationFile);
if (empty($sql)) {
    die("[ERROR] Migration file is empty\n");
}

// Split SQL into individual statements
$statements = explode(';', $sql);
$successCount = 0;
$failCount = 0;

echo "Executing migration statements...\n";
echo "--------------------------------------------\n";

foreach ($statements as $i => $statement) {
    $statement = trim($statement);
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    // Skip INSERT statements that might reference non-existent data
    if (stripos($statement, 'INSERT ') === 0 && stripos($statement, 'SELECT') === false) {
        echo "[SKIP] Statement " . ($i + 1) . ": INSERT skipped (data may reference non-existent records)\n";
        continue;
    }
    
    try {
        $stmt = $db->prepare($statement);
        $result = $stmt->execute();
        
        if ($result) {
            $successCount++;
            $rowCount = $stmt->rowCount();
            $action = strtok($statement, " \t\n\r\0\x0B(");
            echo "[OK] Statement " . ($i + 1) . ": {$action}... ({$rowCount} rows affected)\n";
        } else {
            $failCount++;
            $errorInfo = $stmt->errorInfo();
            echo "[FAIL] Statement " . ($i + 1) . ": " . ($errorInfo[2] ?? 'Unknown error') . "\n";
        }
    } catch (PDOException $e) {
        $failCount++;
        $action = strtok($statement, " \t\n\r\0\x0B(");
        
        // Some errors are acceptable (e.g. "already exists", "duplicate column")
        $acceptableErrors = [
            'already exists',
            'Duplicate column',
            'Duplicate key',
            'Duplicate entry'
        ];
        
        $isAcceptable = false;
        foreach ($acceptableErrors as $acceptable) {
            if (stripos($e->getMessage(), $acceptable) !== false) {
                $isAcceptable = true;
                break;
            }
        }
        
        if ($isAcceptable) {
            echo "[WARN] Statement " . ($i + 1) . ": {$action}... Already exists (skipped)\n";
            $failCount--; // Don't count as failure
        } else {
            echo "[FAIL] Statement " . ($i + 1) . ": {$action}... " . $e->getMessage() . "\n";
        }
    }
}

echo "\n--------------------------------------------\n";
echo "Migration Complete!\n";
echo "Successful: {$successCount} | Failed: {$failCount}\n\n";

// Verify tables were created
echo "Verifying created tables...\n";
echo "--------------------------------------------\n";

$tablesToCheck = [
    'attendance_verifications',
    'student_practical_sessions',
    'datasheet_submissions',
    'datasheet_qr_tokens'
];

foreach ($tablesToCheck as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->fetch()) {
            // Check row count
            $countStmt = $db->query("SELECT COUNT(*) as cnt FROM {$table}");
            $count = $countStmt->fetch()['cnt'];
            echo "[OK] Table '{$table}' exists ({$count} rows)\n";
        } else {
            echo "[MISSING] Table '{$table}' was NOT created!\n";
        }
    } catch (Exception $e) {
        echo "[ERROR] Could not check table '{$table}': " . $e->getMessage() . "\n";
    }
}

// Verify columns were added
echo "\nVerifying schema changes...\n";
echo "--------------------------------------------\n";

$columnsToCheck = [
    'practicals' => ['workflow_status', 'verification_window_opens_minutes', 'verification_window_closes_minutes', 'allowed_verification_methods'],
    'reports' => ['datasheet_submitted', 'datasheet_id'],
    'blockchain_blocks' => ['datasheet_reference']
];

foreach ($columnsToCheck as $table => $columns) {
    foreach ($columns as $column) {
        try {
            $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            if ($stmt->fetch()) {
                echo "[OK] Column '{$table}.{$column}' exists\n";
            } else {
                echo "[MISSING] Column '{$table}.{$column}' was NOT added!\n";
            }
        } catch (Exception $e) {
            echo "[ERROR] Could not check column '{$table}.{$column}': " . $e->getMessage() . "\n";
        }
    }
}

echo "\n============================================\n";
echo "Migration process completed!\n";
echo "============================================\n";
echo "</pre>";