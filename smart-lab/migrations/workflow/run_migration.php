<?php
/**
 * UNILIS SmartLabs - Academic Integrity Workflow Migration Runner
 * 
 * Run this script to create all workflow tables and apply schema changes.
 * Compatible with MySQL 5.7+ and MariaDB 10.2+
 * 
 * Usage: php run_migration.php
 * Or via browser: https://your-domain/smart-lab/migrations/workflow/run_migration.php
 */

echo "<pre>";
echo "============================================\n";
echo "UNILIS SmartLabs - Workflow Migration Tool\n";
echo "============================================\n\n";

echo "--- System & Environment Diagnostics ---\n";
echo "--------------------------------------------\n";
echo "PHP Version         : " . phpversion() . "\n";
echo "PHP SAPI            : " . php_sapi_name() . "\n";
echo "Server Software     : " . ($_SERVER['SERVER_SOFTWARE'] ?? 'CLI') . "\n";
echo "PDO Drivers         : " . implode(', ', PDO::getAvailableDrivers()) . "\n";

$basePath = __DIR__ . '/../../';
require_once $basePath . 'config/database_production.php';

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[OK] Database connection established\n\n";

    // Gather MySQL / MariaDB version and server info
    $serverInfo  = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
    $serverInfoRaw = $db->query("SELECT VERSION() AS ver")->fetch(PDO::FETCH_ASSOC)['ver'];
    $dbNameQuery = $db->query("SELECT DATABASE() AS dbname")->fetch(PDO::FETCH_ASSOC);
    $currentDb   = $dbNameQuery ? $dbNameQuery['dbname'] : '(unknown)';
    $sqlMode     = $db->query("SELECT @@sql_mode AS mode")->fetch(PDO::FETCH_ASSOC)['mode'] ?? '(unknown)';
    $charset     = $db->query("SELECT @@character_set_database AS cs")->fetch(PDO::FETCH_ASSOC)['cs'] ?? '(unknown)';
    $collation   = $db->query("SELECT @@collation_database AS col")->fetch(PDO::FETCH_ASSOC)['col'] ?? '(unknown)';
    $driverName  = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    echo "--- Database Diagnostics ---\n";
    echo "--------------------------------------------\n";
    echo "DB Driver           : {$driverName}\n";
    echo "DB Server Version   : {$serverInfo}\n";
    echo "DB Version()        : {$serverInfoRaw}\n";
    echo "Current Database    : {$currentDb}\n";
    echo "SQL Mode            : {$sqlMode}\n";
    echo "DB Character Set    : {$charset}\n";
    echo "DB Collation        : {$collation}\n";
    echo "PDO::ATTR_SERVER_INFO : " . ($db->getAttribute(PDO::ATTR_SERVER_INFO) ?: '(none)') . "\n";
    echo "PDO::ATTR_CONNECTION_STATUS : " . ($db->getAttribute(PDO::ATTR_CONNECTION_STATUS) ?: '(none)') . "\n";
    echo "PDO::ATTR_CLIENT_VERSION : " . ($db->getAttribute(PDO::ATTR_CLIENT_VERSION) ?: '(none)') . "\n";

    // Detect if it's MariaDB vs MySQL
    if (stripos($serverInfo, 'MariaDB') !== false || stripos($serverInfoRaw, 'MariaDB') !== false) {
        echo "DB Flavor          : MariaDB\n";
    } else {
        echo "DB Flavor          : MySQL / Percona\n";
    }
    echo "\n";
} catch (Exception $e) {
    die("[ERROR] Database connection failed: " . $e->getMessage() . "\n");
}

// ============================================================================
// Helper: Check if a column exists in a table
// ============================================================================
function columnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================================
// Helper: Check if a table exists
// ============================================================================
function tableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================================
// Helper: Check if an index exists
// ============================================================================
function indexExists(PDO $db, string $table, string $index): bool {
    try {
        $stmt = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'");
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================================
// Read and parse the migration SQL file
// ============================================================================
$migrationFile = __DIR__ . '/001_strict_workflow_tables.sql';
if (!file_exists($migrationFile)) {
    die("[ERROR] Migration file not found: {$migrationFile}\n");
}

$sql = file_get_contents($migrationFile);
if (empty(trim($sql))) {
    die("[ERROR] Migration file is empty\n");
}

// Split into individual statements
$rawStatements = explode(';', $sql);
$statements = [];
foreach ($rawStatements as $stmt) {
    $stmt = trim($stmt);
    if (!empty($stmt)) {
        $statements[] = $stmt;
    }
}

// ============================================================================
// Define pre-check rules for each statement
// Each entry: [ 'type' => 'table'|'column'|'index', 'table' => ..., 'name' => ... ]
// null = no pre-check (execute directly)
// ============================================================================
$preChecks = [];
foreach ($statements as $i => $stmt) {
    $upper = strtoupper($stmt);
    if (preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $stmt, $m)) {
        // CREATE TABLE - always safe (IF NOT EXISTS already in the SQL)
        $preChecks[$i] = null;
    } elseif (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+(COLUMN\s+)?`?(\w+)`?/i', $stmt, $m)) {
        // ALTER TABLE ... ADD COLUMN - check if column already exists
        $preChecks[$i] = ['type' => 'column', 'table' => $m[1], 'name' => $m[3]];
    } elseif (preg_match('/^CREATE\s+(UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?INDEX\s+`?(\w+)`?\s+ON\s+`?(\w+)`?/i', $stmt, $m)) {
        // CREATE INDEX - check if index already exists
        $preChecks[$i] = ['type' => 'index', 'table' => $m[3], 'name' => $m[2]];
    } else {
        $preChecks[$i] = null;
    }
}

// ============================================================================
// Execute migration statements
// ============================================================================
$successCount = 0;
$failCount = 0;
$skipCount = 0;

echo "Executing migration statements...\n";
echo "--------------------------------------------\n";

foreach ($statements as $i => $statement) {
    $firstWord = strtok($statement, " \t\n\r\0\x0B(");

    // --- Pre-check ---
    $check = $preChecks[$i] ?? null;
    if ($check !== null) {
        if ($check['type'] === 'column') {
            if (columnExists($db, $check['table'], $check['name'])) {
                $skipCount++;
                echo "[SKIP] Statement " . ($i + 1) . ": {$firstWord}... Column '{$check['table']}.{$check['name']}' already exists (safe to skip)\n";
                continue;
            }
        } elseif ($check['type'] === 'index') {
            if (indexExists($db, $check['table'], $check['name'])) {
                $skipCount++;
                echo "[SKIP] Statement " . ($i + 1) . ": {$firstWord}... Index '{$check['name']}' on '{$check['table']}' already exists (safe to skip)\n";
                continue;
            }
        }
    }

    // --- Execute ---
    try {
        $stmt = $db->prepare($statement);
        $stmt->execute();
        $successCount++;
        $rowCount = $stmt->rowCount();
        echo "[OK] Statement " . ($i + 1) . ": {$firstWord}... ({$rowCount} rows affected)\n";
    } catch (PDOException $e) {
        $errCode = $e->getCode();
        $errMsg = $e->getMessage();

        // MySQL error codes / messages that are safe to ignore
        // We classify based on SQLSTATE ($errCode) which is a 5-character code.
        // NOTE: '42S21' = column already exists, '42S11' = key/index already exists,
        //       '1050' = table already exists, '1061' = duplicate key name,
        //       '1060' = duplicate column name
        $isAcceptable = false;

        if ($errCode === '42S21' || $errCode === '42S11' || $errCode === '42S01') {
            // Column already exists / Key already exists / Table already exists
            $isAcceptable = true;
        } elseif (in_array($errCode, ['1050', '1060', '1061'])) {
            // MySQL error codes (when PDO doesn't map to SQLSTATE)
            $isAcceptable = true;
        } elseif (stripos($errMsg, 'already exists') !== false) {
            $isAcceptable = true;
        } elseif (stripos($errMsg, 'Duplicate column') !== false) {
            $isAcceptable = true;
        } elseif (stripos($errMsg, 'Duplicate key name') !== false) {
            $isAcceptable = true;
        }

        if ($isAcceptable) {
            $skipCount++;
            echo "[SKIP] Statement " . ($i + 1) . ": {$firstWord}... Already exists (safe to skip)\n";
        } else {
            $failCount++;
            echo "[FAIL] Statement " . ($i + 1) . ": {$firstWord}... {$errMsg}\n";
        }
    }
}

echo "\n--------------------------------------------\n";
echo "Migration Complete!\n";
echo "Successful: {$successCount} | Skipped: {$skipCount} | Failed: {$failCount}\n\n";

// ============================================================================
// Verification: Tables
// ============================================================================
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
        if (tableExists($db, $table)) {
            $countStmt = $db->query("SELECT COUNT(*) as cnt FROM `{$table}`");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            echo "[OK] Table '{$table}' exists ({$count} rows)\n";
        } else {
            echo "[MISSING] Table '{$table}' was NOT created!\n";
        }
    } catch (Exception $e) {
        echo "[ERROR] Could not check table '{$table}': " . $e->getMessage() . "\n";
    }
}

// ============================================================================
// Verification: Columns
// ============================================================================
echo "\nVerifying schema changes...\n";
echo "--------------------------------------------\n";

$columnsToCheck = [
    'practicals' => ['workflow_status', 'verification_window_opens_minutes', 'verification_window_closes_minutes', 'allowed_verification_methods'],
    'reports' => ['datasheet_submitted', 'datasheet_id'],
    'blockchain_blocks' => ['datasheet_reference']
];

$allColumnsExist = true;
foreach ($columnsToCheck as $table => $columns) {
    foreach ($columns as $column) {
        try {
            if (columnExists($db, $table, $column)) {
                echo "[OK] Column '{$table}.{$column}' exists\n";
            } else {
                echo "[MISSING] Column '{$table}.{$column}' was NOT added!\n";
                $allColumnsExist = false;
            }
        } catch (Exception $e) {
            echo "[ERROR] Could not check column '{$table}.{$column}': " . $e->getMessage() . "\n";
        }
    }
}

// ============================================================================
// Summary
// ============================================================================
echo "\n============================================\n";
echo "Migration process completed!\n";
if ($failCount > 0) {
    echo "WARNING: {$failCount} statement(s) failed. Review above.\n";
    echo "Some features may not work correctly.\n";
} elseif (!$allColumnsExist) {
    echo "NOTE: Some columns are still missing. Review above.\n";
    echo "Some features may not work correctly until all columns are added.\n";
} else {
    echo "All operations completed successfully!\n";
}
echo "============================================\n";
echo "</pre>";