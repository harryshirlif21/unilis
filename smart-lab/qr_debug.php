<?php
// No includes yet - test bare PHP first
echo "<h2>PHP Works</h2>";

// Test config load
$host = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false) ? 'production' : 'local';
echo "<p>Environment: <strong>$host</strong></p>";
echo "<p>Host: <strong>" . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "</strong></p>";

// Load config manually
try {
    if ($host === 'production') {
        require_once __DIR__.'/config/app_production.php';
        require_once __DIR__.'/config/database_production.php';
    } else {
        require_once __DIR__.'/config/app.php';
        require_once __DIR__.'/config/database.php';
    }
    echo "<p>✅ Config loaded. APP_URL: <strong>" . APP_URL . "</strong></p>";
} catch (Throwable $e) {
    echo "<p>❌ Config error: " . $e->getMessage() . "</p>";
}

// Test DB
try {
    $db = getDB();
    $db->query("SELECT 1");
    echo "<p>✅ DB connected</p>";

    // Check qr_sessions
    $tables = $db->query("SHOW TABLES LIKE 'qr_sessions'")->fetchAll();
    echo "<p>" . (empty($tables) ? "❌ qr_sessions MISSING - run patch_db2.php first!" : "✅ qr_sessions exists") . "</p>";

} catch (Throwable $e) {
    echo "<p>❌ DB error: " . $e->getMessage() . "</p>";
}

// Check controller file
echo "<p>Controller: " . (file_exists(__DIR__.'/controllers/QrAuthController.php') ? "✅ exists" : "❌ MISSING") . "</p>";
