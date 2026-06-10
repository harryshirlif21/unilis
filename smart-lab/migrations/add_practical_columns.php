<?php
/**
 * Migration: Add missing columns to practicals table
 * 
 * This script adds columns that the PracticalModel::create() and 
 * related methods reference but were missing from the database schema.
 * 
 * Columns added:
 * - objective           (TEXT)        - Learning objectives
 * - theory              (TEXT)        - Theoretical background
 * - duration_hours      (INT)         - Duration in hours
 * - procedure_json      (LONGTEXT)    - Step-by-step procedure (JSON)
 * - observations_table_structure (LONGTEXT) - Table structure definition (JSON)
 * - results_template    (TEXT)        - Results table template (HTML)
 * - calculations_template (TEXT)      - Calculations template (HTML)
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

// --- Define columns to add ------------------------------------------------
$columns = [
    [
        'name' => 'objective',
        'definition' => "ALTER TABLE practicals ADD COLUMN objective TEXT DEFAULT NULL AFTER description",
        'check_sql'   => "SHOW COLUMNS FROM practicals LIKE 'objective'",
    ],
    [
        'name' => 'theory',
        'definition' => "ALTER TABLE practicals ADD COLUMN theory TEXT DEFAULT NULL AFTER objective",
        'check_sql'   => "SHOW COLUMNS FROM practicals LIKE 'theory'",
    ],
    [
        'name' => 'duration_hours',
        'definition' => "ALTER TABLE practicals ADD COLUMN duration_hours INT DEFAULT 2 AFTER end_time",
        'check_sql'   => "SHOW COLUMNS FROM practicals LIKE 'duration_hours'",
    ],
    [
        'name' => 'procedure_json',
        'definition' => "ALTER TABLE practicals ADD COLUMN procedure_json LONGTEXT DEFAULT NULL AFTER safety_notes",
        'check_sql'   => "SHOW COLUMNS FROM practicals LIKE 'procedure_json'",
    ],
    [
        'name' => 'observations_table_structure',
        'definition' => "ALTER TABLE practicals ADD COLUMN observations_table_structure LONGTEXT DEFAULT NULL AFTER procedure_json",
        'check_sql'   => "SHOW COLUMNS FROM practicals LIKE 'observations_table_structure'",
    ],
    [
        'name' => 'results_template',
        'definition' => "ALTER TABLE practicals ADD COLUMN results_template TEXT DEFAULT NULL AFTER observations_table_structure",
        'check_sql'   => "SHOW COLUMNS FROM practicals LIKE 'results_template'",
    ],
    [
        'name' => 'calculations_template',
        'definition' => "ALTER TABLE practicals ADD COLUMN calculations_template TEXT DEFAULT NULL AFTER results_template",
        'check_sql'   => "SHOW COLUMNS FROM practicals LIKE 'calculations_template'",
    ],
];

// --- Execute migration ----------------------------------------------------
$allSuccess = true;

logMsg("", $isCLI);
logMsg("=== Adding missing columns to 'practicals' table ===", $isCLI);
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
    logMsg("The following columns are now available in the practicals table:", $isCLI);
    foreach ($columns as $col) {
        logMsg("  - {$col['name']}", $isCLI);
    }
    logMsg("", $isCLI);
    logMsg("You can now create practicals without errors.", $isCLI);
} else {
    logMsg("=== Migration completed with some errors. Check messages above. ===", $isCLI);
    exit(1);
}