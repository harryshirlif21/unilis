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

$migrationFile = __DIR__ . '/001_strict_workflow_tables.sql';
if (!file_exists($migrationFile)) {
    die("[ERROR] Migration file not found: {$migrationFile}\n");
}

$sql = file_get_contents($migrationFile);
if (empty(trim($sql))) {
    die("[ERROR] Migration file is empty\n");
}

$statements = explode(';', $sql);
$successCount = 0;
$failCount = 0;
$skipCount = 0;

echo "Executing migration statements...\n";
echo "--------------------------------------------\n";

foreach ($statements as $i => $statement) {
    $statement = trim($statement);
    if (empty($statement)) {
        continue;
    }

    $firstWord = strtok($statement, " \t\n\r\0\x0B(");

    try {
        $stmt = $db->prepare($statement);
        $stmt->execute();
        $successCount++;
        $rowCount = $stmt->rowCount();
        echo "[OK] Statement " . ($i + 1) . ": {$firstWord}... ({$rowCount} rows affected)\n";
    } catch (PDOException $e) {
        $errCode = $e->getCode();
        $errMsg = $e->getMessage();
        $isAcceptable = false;

        // MySQL error codes that are safe to ignore
        $acceptablePatterns = [
            '42S21' => 'Duplicate column',       // Column already exists
            'Duplicate column',
            '1060'   => 'Duplicate column',       // MySQL error code
            '42S11'  => 'Duplicate key',          // Key already exists
            '1061'   => 'Duplicate key name',     // Index already exists
            '1050'   => 'Table already exists',   // Table '\''...'\'' already exists
            'Table \'' => 'Table already exists',
            'already exists',
            'Duplicate key name',
            'Duplicate entry',
        ];

        foreach ($acceptablePatterns as $code => $pattern) {
            if (stripos($errCode, $code) !== false || stripos($errMsg, $pattern) !== false) {
                $isAcceptable = true;
                break;
            }
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
            $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
            $stmt->execute([':col' => $column]);
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
if ($failCount > 0) {
    echo "WARNING: {$failCount} statement(s) failed. Review above.\n";
    echo "Some features may not work correctly.\n";
} else {
    echo "All operations completed successfully!\n";
}
echo "============================================\n";
echo "</pre>";