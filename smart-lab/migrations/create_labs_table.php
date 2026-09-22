<?php
/**
 * Migration: Create `labs` table
 *
 * Creates the `labs` table (lab rooms/spaces) for the SmartLab module.
 * This table was previously created manually outside any tracked migration,
 * so it is missing in production and breaks the registration page query in
 * controllers/AuthController.php (SELECT id, name, lab_code FROM labs
 * WHERE is_active = 1 ORDER BY name).
 *
 * Schema source of truth: the working local dev table (SHOW CREATE TABLE labs)
 * on unilis_smartlab (MySQL 8.x). The columns `id`, `name`, `lab_code`,
 * `type`, `max_capacity`, `current_count`, `is_active` cover every reference
 * made against `labs` in the codebase.
 *
 * Run from CLI or browser. Safe to run multiple times (idempotent) - if the
 * table already exists the migration reports it as already present and exits.
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

// --- Check whether the table already exists -------------------------------
$check = $db->query("SHOW TABLES LIKE 'labs'");
if ($check->rowCount() > 0) {
    logMsg("⊘ labs table already exists, skipping creation", $isCLI);
    logMsg("Migration completed successfully (no changes needed).", $isCLI);
} else {
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `labs` (
            `id` char(36) NOT NULL DEFAULT uuid(),
            `name` varchar(150) NOT NULL,
            `lab_code` varchar(20) NOT NULL,
            `type` enum('physics','chemistry','engineering','clinical','computer','general') NOT NULL,
            `building` varchar(100) DEFAULT NULL,
            `room_number` varchar(30) DEFAULT NULL,
            `max_capacity` int(11) DEFAULT 30,
            `current_count` int(11) DEFAULT 0,
            `is_active` tinyint(1) DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `lab_code` (`lab_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    try {
        $db->exec($createTableSQL);
        logMsg("✓ labs table created successfully", $isCLI);
    } catch (Exception $e) {
        logMsg("✗ Failed to create labs table: " . $e->getMessage(), $isCLI);
        exit(1);
    }
}

// --- Verification ----------------------------------------------------------
$verify = $db->query("SHOW TABLES LIKE 'labs'");
if ($verify->rowCount() > 0) {
    $cols = $db->query("SHOW COLUMNS FROM labs")->fetchAll();
    $names = [];
    foreach ($cols as $col) {
        $names[] = $col['Field'];
    }
    logMsg("[OK] labs table exists. Columns: " . implode(', ', $names), $isCLI);
} else {
    logMsg("[MISSING] labs table was NOT created!", $isCLI);
    exit(1);
}

logMsg("Migration completed successfully!", $isCLI);
?>