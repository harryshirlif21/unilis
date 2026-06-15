<?php
/**
 * Migration: Add 'graded_by' column to reports table
 *
 * The reports table in production is missing the `graded_by` foreign key column
 * that the ReportModel queries reference. This script adds it along with a few
 * other potentially missing columns.
 *
 * Run from browser or CLI. Safe to run multiple times (idempotent).
 */

// --- Bootstrap ------------------------------------------------------------
$isCLI = (php_sapi_name() === 'cli');
$separator = $isCLI ? "\n" : "<br>\n";

function logMsg(string $msg, bool $isCLI): void {
    echo $msg . ($isCLI ? "\n" : "<br>\n");
}

// Determine project root and include database config
$projectRoot = dirname(__DIR__); // smart-lab/
$configFile  = $projectRoot . '/config/database.php';

if (!file_exists($configFile)) {
    logMsg("ERROR: Database config not found at $configFile", $isCLI);
    exit(1);
}

require_once $configFile;

// Get PDO connection using the app's existing function
try {
    $db = getDB();
    logMsg("Database connection established.", $isCLI);
} catch (Exception $e) {
    logMsg("ERROR: Failed to connect to database - " . $e->getMessage(), $isCLI);
    exit(1);
}

// --- Define columns to add to reports table ---------------------------------
$columns = [
    [
        'name' => 'graded_by',
        'definition' => "ALTER TABLE reports ADD COLUMN graded_by varchar(36) DEFAULT NULL AFTER feedback",
        'check_sql'   => "SHOW COLUMNS FROM reports LIKE 'graded_by'",
    ],
    [
        'name' => 'graded_at',
        'definition' => "ALTER TABLE reports ADD COLUMN graded_at timestamp NULL DEFAULT NULL AFTER graded_by",
        'check_sql'   => "SHOW COLUMNS FROM reports LIKE 'graded_at'",
    ],
];

// --- Execute migration ----------------------------------------------------
$allSuccess = true;

logMsg("", $isCLI);
logMsg("=== Adding missing columns to 'reports' table ===", $isCLI);
logMsg("", $isCLI);

foreach ($columns as $col) {
    // Check if column already exists
    $checkStmt = $db->query($col['check_sql']);
    if ($checkStmt->fetch()) {
        logMsg("  [SKIP] Column '{$col['name']}' already exists.", $isCLI);
        continue;
    }

    // Add the column
    try {
        $db->exec($col['definition']);
        logMsg("  [ OK ] Column '{$col['name']}' added successfully.", $isCLI);
    } catch (Exception $e) {
        logMsg("  [FAIL] Column '{$col['name']}' – " . $e->getMessage(), $isCLI);
        $allSuccess = false;
    }
}

logMsg("", $isCLI);

if ($allSuccess) {
    logMsg("=== Migration completed successfully! ===", $isCLI);
    logMsg("", $isCLI);
    logMsg("The following columns have been added to the reports table:", $isCLI);
    foreach ($columns as $col) {
        logMsg("  - {$col['name']}", $isCLI);
    }
    logMsg("", $isCLI);
    logMsg("The 'graded_by' column error in reports should now be resolved.", $isCLI);
} else {
    logMsg("=== Migration completed with some errors. Check messages above. ===", $isCLI);
    exit(1);
}