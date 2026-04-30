<?php
// Test script for QR functionality

// Environment detection
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once __DIR__.'/config/app_production.php';
    require_once __DIR__.'/config/database_production.php';
} else {
    require_once __DIR__.'/config/app.php';
    require_once __DIR__.'/config/database.php';
    
    // Override database connection for local development
    if (!defined('DB_HOST') || DB_HOST === 'smart-labs-db') {
        // Create local database connection
        class LocalDB {
            private static $pdo = null;
            public static function get() {
                if (self::$pdo === null) {
                    self::$pdo = new PDO('mysql:host=localhost;dbname=unilis_smartlab;charset=utf8mb4', 'root', '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                }
                return self::$pdo;
            }
        }
        
        // Override getDB function
        if (!function_exists('getDB')) {
            function getDB() {
                return LocalDB::get();
            }
        }
    }
}

require_once __DIR__.'/auth/Auth.php';
require_once __DIR__.'/utils/helpers.php';

header('Content-Type: application/json');

try {
    echo "Testing QR generation...\n";
    
    $db = getDB();
    echo "Database connected successfully\n";
    
    // Clean up expired sessions
    $db->exec("DELETE FROM qr_sessions WHERE expires_at < NOW()");
    echo "Cleaned expired sessions\n";
    
    // Generate new session
    $id      = bin2hex(random_bytes(8));
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 300);
    
    $stmt = $db->prepare("INSERT INTO qr_sessions (id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$id, $token, $expires]);
    
    echo "Created QR session: ID=$id, Token=$token, Expires=$expires\n";
    
    $scanUrl = APP_URL . '/qr/scan?token=' . $token;
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'url' => $scanUrl,
        'id' => $id,
        'app_url' => APP_URL
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
