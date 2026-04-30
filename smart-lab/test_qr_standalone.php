<?php
// Standalone test for QR functionality - bypasses all config files

// Define constants directly
define('APP_URL', 'http://localhost/smart-lab');

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

function getDB() {
    return LocalDB::get();
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

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
