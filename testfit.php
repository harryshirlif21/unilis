<?php
/**
 * Live Engagement — standalone diagnostics
 * Run via CLI inside the container: php testfit.php
 * (Bypasses HTTP/session/auth entirely — pure PHP + DB checks.)
 */

define('UNILIS_ACCESS', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

function section(string $title): void {
    echo "\n=== {$title} ===\n";
}

section('PHP / mysqli info');
echo "PHP version: " . PHP_VERSION . "\n";
echo "mysqli loaded: " . (extension_loaded('mysqli') ? 'yes' : 'NO') . "\n";

section('Loading config/db.php');
$appRoot = __DIR__;
$dbConfigCandidates = [
    $appRoot . '/config/db.php',
    $appRoot . '/../config/db.php',
    $appRoot . '/../../config/db.php',
];
$dbConfigPath = null;
foreach ($dbConfigCandidates as $candidate) {
    if (file_exists($candidate)) { $dbConfigPath = $candidate; break; }
}
if (!$dbConfigPath) {
    echo "COULD NOT FIND config/db.php near {$appRoot} — adjust script location.\n";
    exit(1);
}
echo "Using: {$dbConfigPath}\n";

try {
    require $dbConfigPath;
} catch (Throwable $e) {
    echo "FATAL loading db.php: " . get_class($e) . ": " . $e->getMessage() . "\n";
    exit(1);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "No valid \$conn mysqli object after including db.php.\n";
    exit(1);
}
echo "Connected OK. host_info: " . $conn->host_info . "\n";
$dbNameRow = $conn->query("SELECT DATABASE() AS db")->fetch_assoc();
echo "Active database: " . $dbNameRow['db'] . "\n";

section('mysqli_report exception behavior test');
try {
    $conn->query("SELECT * FROM __diagnostic_table_that_does_not_exist__");
    echo "Query on missing table returned normally (no exception thrown).\n";
} catch (\mysqli_sql_exception $e) {
    echo "CONFIRMED: mysqli threw an exception on a bad query -> " . $e->getMessage() . "\n";
    echo "This means ANY unguarded bad query anywhere in the request will produce an uncaught fatal error (500).\n";
}

section('Table existence check (tables dashboard.php / SessionModel rely on)');
$tablesToCheck = [
    'courses', 'units', 'lecturer_units',
    'live_sessions', 'live_presentations', 'live_slides',
    'live_polls', 'live_quizzes', 'live_participants',
];
foreach ($tablesToCheck as $t) {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    $exists = $res && $res->num_rows > 0;
    echo str_pad($t, 25) . ": " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

section('Column spot-check on live_sessions (if it exists)');
$res = $conn->query("SHOW TABLES LIKE 'live_sessions'");
if ($res && $res->num_rows > 0) {
    $cols = $conn->query("SHOW COLUMNS FROM live_sessions");
    while ($row = $cols->fetch_assoc()) {
        echo " - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "live_sessions table missing, skipping column check.\n";
}

section('Autoload + instantiate LE\\Models\\SessionModel');
$moduleDir = $appRoot;
spl_autoload_register(function (string $class) use ($moduleDir) {
    $prefix = 'LE\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $relativeClass = substr($class, strlen($prefix));
    $file = $moduleDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});
require_once $moduleDir . '/config/database_helper.php';
require_once $moduleDir . '/models/BaseModel.php';
require_once $moduleDir . '/models/SessionModel.php';

try {
    $model = new \LE\Models\SessionModel();
    echo "SessionModel instantiated OK.\n";

    try {
        $active = $model->getLecturerActiveSessions(1);
        echo "getLecturerActiveSessions(1) OK, returned " . (is_array($active) ? count($active) . " rows" : gettype($active)) . "\n";
    } catch (Throwable $e) {
        echo "getLecturerActiveSessions THREW: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }

    try {
        $scheduled = $model->getLecturerScheduledSessions(1);
        echo "getLecturerScheduledSessions(1) OK, returned " . (is_array($scheduled) ? count($scheduled) . " rows" : gettype($scheduled)) . "\n";
    } catch (Throwable $e) {
        echo "getLecturerScheduledSessions THREW: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }

    try {
        $history = $model->getLecturerHistory(1);
        echo "getLecturerHistory(1) OK, returned " . (is_array($history) ? count($history) . " rows" : gettype($history)) . "\n";
    } catch (Throwable $e) {
        echo "getLecturerHistory THREW: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
} catch (Throwable $e) {
    echo "SessionModel instantiation FAILED: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
}

section('Done');