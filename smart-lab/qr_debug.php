<?php
require_once __DIR__.'/config/app.php';

echo "<h2>QR Debug</h2>";

// 1. Check DB table
try {
    $db = getDB();
    $tables = $db->query("SHOW TABLES LIKE 'qr_sessions'")->fetchAll();
    echo "<p>" . (empty($tables) ? "❌ qr_sessions table MISSING" : "✅ qr_sessions table exists") . "</p>";
} catch (Exception $e) {
    echo "<p>❌ DB Error: " . $e->getMessage() . "</p>";
}

// 2. Check APP_URL
echo "<p>APP_URL: <strong>" . APP_URL . "</strong></p>";

// 3. Test generate endpoint directly
echo "<p>Generate URL: <strong>" . APP_URL . "/qr/generate</strong></p>";

// 4. Check QrAuthController exists
$file = __DIR__ . '/controllers/QrAuthController.php';
echo "<p>" . (file_exists($file) ? "✅ QrAuthController.php exists" : "❌ QrAuthController.php MISSING") . "</p>";

// 5. Try generating a token
try {
    $db->exec("DELETE FROM qr_sessions WHERE expires_at < NOW()");
    $id = bin2hex(random_bytes(8));
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 300);
    $db->prepare("INSERT INTO qr_sessions (id, token, expires_at) VALUES (?, ?, ?)")
       ->execute([$id, $token, $expires]);
    echo "<p>✅ Token generated: <code>" . substr($token, 0, 20) . "...</code></p>";
    echo "<p>Scan URL: <strong>" . APP_URL . "/qr/scan?token=" . $token . "</strong></p>";
} catch (Exception $e) {
    echo "<p>❌ Token generation failed: " . $e->getMessage() . "</p>";
}
