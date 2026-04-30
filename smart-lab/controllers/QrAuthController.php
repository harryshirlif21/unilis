<?php
// Environment detection and proper config loading
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once __DIR__.'/../config/app_production.php';
    require_once __DIR__.'/../config/database_production.php';
    require_once __DIR__.'/../auth/Auth.php';
    require_once __DIR__.'/../utils/helpers.php';
} else {
    // For local development, use standalone approach
    if (!defined('APP_URL')) {
        define('APP_URL', 'http://localhost/smart-lab');
    }
    
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
    
    function logActivity($userId, $action, $module = 'system') {
        // Simple logging for local development
        error_log("Activity: User $userId - $action in $module");
    }
}

// For Docker production, ensure database connection works
if ($is_production && defined('DB_HOST') && DB_HOST === 'smart-labs-db') {
    // In Docker production, the database should be accessible via the service name
    // No additional configuration needed if docker-compose.yml is correct
}

class QrAuthController {

    // Called by login page via AJAX — generates a QR session token
    public function generate($param = null) {
        header('Content-Type: application/json');
        $db = getDB();
        $db->exec("DELETE FROM qr_sessions WHERE expires_at < NOW()");
        $id      = bin2hex(random_bytes(8));
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 300);
        $db->prepare("INSERT INTO qr_sessions (id, token, expires_at) VALUES (?, ?, ?)")
           ->execute([$id, $token, $expires]);
        $scanUrl = APP_URL . '/qr/scan?token=' . $token;
        echo json_encode(['token' => $token, 'url' => $scanUrl, 'id' => $id]);
    }

    // Called by login page AJAX — polls whether phone has claimed the session
    public function poll($param = null) {
        header('Content-Type: application/json');
        $token = sanitize($_GET['token'] ?? '');
        if (!$token) { echo json_encode(['status' => 'invalid']); return; }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM qr_sessions WHERE token = ? LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) { echo json_encode(['status' => 'invalid']); return; }
        if ($session['expires_at'] < date('Y-m-d H:i:s')) {
            echo json_encode(['status' => 'expired']); return;
        }

        if ($session['status'] === 'claimed' && $session['user_id']) {
            $userStmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
            $userStmt->execute([$session['user_id']]);
            $user = $userStmt->fetch();

            if ($user) {
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['user_role']   = $user['role'];
                $_SESSION['user_name']   = $user['full_name'];
                $_SESSION['lab_id']      = $user['lab_id'] ?? '';
                $_SESSION['auth_method'] = 'qr';

                $db->prepare("UPDATE qr_sessions SET status='expired' WHERE token=?")
                   ->execute([$token]);

                logActivity($user['id'], 'login_qr', 'auth');
                echo json_encode(['status' => 'claimed', 'redirect' => APP_URL . '/dashboard']);
                return;
            }
        }

        echo json_encode(['status' => $session['status']]);
    }

    // Opened on the PHONE when QR is scanned
    public function scan($param = null) {
        $token = sanitize($_GET['token'] ?? '');
        $db    = getDB();

        $stmt = $db->prepare("SELECT * FROM qr_sessions WHERE token = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) {
            die("<!DOCTYPE html><html><head><meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width,initial-scale=1.0'>
                <title>Expired</title>
                <style>body{font-family:sans-serif;text-align:center;padding:40px;background:#0f172a;color:#fff}</style>
                </head><body>
                <h2>❌ QR Code Expired</h2>
                <p>Please refresh the login page and scan a new code.</p>
                </body></html>");
        }

        // Check if device is already registered
        $deviceId = $_COOKIE['sl_device'] ?? '';
        if ($deviceId) {
            $userStmt = $db->prepare("SELECT * FROM users WHERE device_fingerprint = ? AND is_active = 1 LIMIT 1");
            $userStmt->execute([$deviceId]);
            $user = $userStmt->fetch();

            if ($user) {
                $db->prepare("UPDATE qr_sessions SET status='claimed', user_id=? WHERE token=?")
                   ->execute([$user['id'], $token]);
                $this->showSuccess($user['full_name'], true);
                return;
            }
        }

        // First time — show user selection
        $users = $db->query("SELECT id, full_name, reg_number FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
        $this->showScanPage($token, $users);
    }

    // Phone submits their identity
    public function claim($param = null) {
        $token  = sanitize($_POST['token'] ?? '');
        $userId = sanitize($_POST['user_id'] ?? '');
        $db     = getDB();

        $stmt = $db->prepare("SELECT * FROM qr_sessions WHERE token = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session || !$userId) {
            die("<p style='color:red;font-family:sans-serif;text-align:center;padding:40px'>Invalid or expired session.</p>");
        }

        // Generate and save device fingerprint
        $deviceId = bin2hex(random_bytes(16));
        $db->prepare("UPDATE users SET device_fingerprint = ? WHERE id = ?")->execute([$deviceId, $userId]);

        // Claim the QR session
        $db->prepare("UPDATE qr_sessions SET status='claimed', user_id=? WHERE token=?")->execute([$userId, $token]);

        // Set device cookie on phone (1 year)
        setcookie('sl_device', $deviceId, time() + 31536000, '/', '', isset($_SERVER['HTTPS']), true);

        $userStmt = $db->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        $this->showSuccess($user['full_name'] ?? 'User', false);
    }

    private function showScanPage(string $token, array $users): void {
        $appUrl = APP_URL;
        echo '<!DOCTYPE html><html><head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>UNILIS SmartLab — QR Login</title>
        <style>
            *{box-sizing:border-box;margin:0;padding:0}
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#0f172a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .card{background:#1e293b;border-radius:20px;padding:32px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.4)}
            .logo-icon{width:56px;height:56px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:14px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;margin:0 auto 12px}
            h2{text-align:center;font-size:20px;margin-bottom:6px}
            p{text-align:center;color:#94a3b8;font-size:14px;margin-bottom:20px;line-height:1.5}
            select{width:100%;padding:14px;background:#0f172a;border:2px solid #334155;border-radius:12px;color:#fff;font-size:15px;margin-bottom:16px;outline:none;appearance:none}
            select:focus{border-color:#6366f1}
            button{width:100%;padding:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:600;cursor:pointer;transition:opacity 0.2s}
            button:active{opacity:0.85}
            .badge{background:#1e3a5f;color:#60a5fa;font-size:12px;padding:6px 12px;border-radius:20px;display:block;text-align:center;margin-bottom:20px}
        </style>
        </head><body>
        <div class="card">
            <div class="logo-icon" style="text-align:center">SL</div>
            <h2>QR Login</h2>
            <p>Select your name to log in.<br>Your device will be remembered for future scans.</p>
            <span class="badge">⚡ One-tap login after first use</span>
            <form method="POST" action="' . $appUrl . '/qr/claim">
                <input type="hidden" name="token" value="' . htmlspecialchars($token) . '">
                <select name="user_id" required>
                    <option value="">— Select your name —</option>';
        foreach ($users as $u) {
            echo '<option value="' . htmlspecialchars($u['id']) . '">'
                . htmlspecialchars($u['full_name'])
                . ' (' . htmlspecialchars($u['reg_number']) . ')</option>';
        }
        echo '      </select>
                <button type="submit">✓ Confirm &amp; Log In</button>
            </form>
        </div>
        </body></html>';
    }

    private function showSuccess(string $name, bool $autoLogin): void {
        $msg = $autoLogin
            ? '⚡ Device recognised — auto-login!'
            : '📱 Device saved — next scan will log you in instantly!';
        echo '<!DOCTYPE html><html><head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>Logged In</title>
        <style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#0f172a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .card{background:#1e293b;border-radius:20px;padding:40px;max-width:360px;width:100%;text-align:center}
            .check{font-size:64px;margin-bottom:16px}
            h2{font-size:22px;margin-bottom:8px}
            p{color:#94a3b8;font-size:14px;line-height:1.6;margin-bottom:16px}
            .badge{background:#134e4a;color:#6ee7b7;padding:10px 16px;border-radius:10px;font-size:13px;display:block}
        </style>
        </head><body>
        <div class="card">
            <div class="check">✅</div>
            <h2>Welcome, ' . htmlspecialchars($name) . '!</h2>
            <p>You are now logged in.<br>You can close this tab and return to the lab screen.</p>
            <span class="badge">' . $msg . '</span>
        </div>
        </body></html>';
    }
}