<?php
// Environment detection and proper config loading
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once __DIR__.'/../config/app_production.php';
    require_once __DIR__.'/../config/database_production.php';
    require_once __DIR__.'/../auth/Auth.php';
    require_once __DIR__.'/../utils/helpers.php';
} else {
    require_once __DIR__.'/../auth/Auth.php';
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

    // Called by login page via AJAX â€” generates a QR session token
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

    // Called by login page AJAX â€” polls whether phone has claimed the session
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

    // Attendance QR endpoints
    public function attendanceGenerate($param = null) {
        header('Content-Type: application/json');
        Auth::guard();

        $practicalId = sanitize($_GET['practical_id'] ?? '');
        if (empty($practicalId)) {
            echo json_encode(['error' => 'Practical ID is required']);
            return;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM practicals WHERE id = ? AND status = 'published'");
        $stmt->execute([$practicalId]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Practical not found or not available']);
            return;
        }

        $db->exec("DELETE FROM qr_sessions WHERE expires_at < NOW()");
        $id = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 300);
        $db->prepare("INSERT INTO qr_sessions (id, token, expires_at, status) VALUES (?, ?, ?, 'pending')")
            ->execute([$id, $token, $expires]);

        $scanUrl = APP_URL . '/attendance-qr/scan?token=' . $token . '&practical_id=' . urlencode($practicalId);
        echo json_encode(['token' => $token, 'url' => $scanUrl, 'id' => $id]);
    }

    public function attendancePoll($param = null) {
        header('Content-Type: application/json');
        $token = sanitize($_GET['token'] ?? '');
        if (!$token) {
            echo json_encode(['status' => 'invalid']);
            return;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM qr_sessions WHERE token = ? LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) {
            echo json_encode(['status' => 'invalid']);
            return;
        }

        if ($session['expires_at'] < date('Y-m-d H:i:s')) {
            echo json_encode(['status' => 'expired']);
            return;
        }

        if ($session['status'] === 'claimed' && $session['user_id']) {
            $userStmt = $db->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
            $userStmt->execute([$session['user_id']]);
            $user = $userStmt->fetch();
            echo json_encode([
                'status' => 'claimed',
                'student_name' => $user['full_name'] ?? null
            ]);
            return;
        }

        echo json_encode(['status' => $session['status']]);
    }

    public function verifyFingerprint($param = null) {
        header('Content-Type: application/json');
        Auth::guard();

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $fingerprintData = trim($payload['fingerprint_data'] ?? '');

        if (empty($fingerprintData)) {
            echo json_encode(['success' => false, 'error' => 'Fingerprint data is required']);
            return;
        }

        $biometricHash = hash('sha256', $fingerprintData . BIOMETRIC_SALT);
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE biometric_hash = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$biometricHash]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Fingerprint verification failed']);
            return;
        }

        echo json_encode(['success' => true, 'student_id' => $user['id']]);
    }

    public function verifyRFID($param = null) {
        header('Content-Type: application/json');
        Auth::guard();

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $uid = trim($payload['uid'] ?? '');

        if (empty($uid)) {
            echo json_encode(['success' => false, 'error' => 'RFID UID is required']);
            return;
        }

        $db = getDB();
        $stmt = $db->prepare(
            "SELECT u.id FROM users u
             JOIN rfid_cards r ON r.student_id = u.id
             WHERE r.uid = ? AND u.role = 'student' AND u.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'RFID verification failed']);
            return;
        }

        echo json_encode(['success' => true, 'student_id' => $user['id']]);
    }

    public function verifyCode($param = null) {
        header('Content-Type: application/json');
        Auth::guard();

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $practicalId = sanitize($payload['practical_id'] ?? '');
        $adminCode = strtoupper(trim($payload['admin_code'] ?? ''));

        if (empty($practicalId) || empty($adminCode)) {
            echo json_encode(['success' => false, 'error' => 'Practical ID and admin code are required']);
            return;
        }

        $db = getDB();
        $stmt = $db->prepare(
            "SELECT id FROM lab_sessions
             WHERE practical_id = ? AND confirmation_code = ?
             AND status IN ('open', 'active')
             LIMIT 1"
        );
        $stmt->execute([$practicalId, $adminCode]);
        $session = $stmt->fetch();

        if (!$session) {
            echo json_encode(['success' => false, 'error' => 'Invalid or expired admin code']);
            return;
        }

        echo json_encode(['success' => true, 'student_id' => Auth::id()]);
    }

    public function scan($param = null) {
        $token = sanitize($_GET['token'] ?? '');
        $db    = getDB();

        $stmt = $db->prepare("SELECT * FROM qr_sessions WHERE token = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) {
            die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Expired</title><style>body{font-family:sans-serif;text-align:center;padding:40px;background:#0f172a;color:#fff}</style></head><body><h2>âŒ QR Code Expired</h2><p>Please refresh the login page and scan a new code.</p></body></html>');
        }

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

        $users = $db->query("SELECT id, full_name, reg_number FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
        $this->showScanPage($token, $users);
    }

    public function attendanceScan($param = null) {
        $token = sanitize($_GET['token'] ?? '');
        $practicalId = sanitize($_GET['practical_id'] ?? '');
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM qr_sessions WHERE token = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session || !$practicalId) {
            die('<p style="font-family:sans-serif;padding:40px;color:#b91c1c">Invalid or expired attendance session.</p>');
        }

        $deviceId = $_COOKIE['sl_device'] ?? '';
        if ($deviceId) {
            $userStmt = $db->prepare("SELECT * FROM users WHERE device_fingerprint = ? AND is_active = 1 LIMIT 1");
            $userStmt->execute([$deviceId]);
            $user = $userStmt->fetch();
            if ($user) {
                $this->markAttendanceForUser($db, $user['id'], $practicalId);
                $db->prepare("UPDATE qr_sessions SET status='claimed', user_id=? WHERE token=?")
                    ->execute([$user['id'], $token]);
                $this->showAttendanceSuccess($user['full_name']);
                return;
            }
        }

        $stmt = $db->prepare(
            "SELECT u.id, u.full_name, u.reg_number FROM users u
             JOIN practicals p ON p.lab_id = u.lab_id
             WHERE p.id = ? AND u.role = 'student' AND u.is_active = 1
             ORDER BY u.full_name"
        );
        $stmt->execute([$practicalId]);
        $students = $stmt->fetchAll();

        $this->showAttendanceScanPage($token, $practicalId, $students);
    }

    public function attendanceClaim($param = null) {
        $token = sanitize($_POST['token'] ?? '');
        $practicalId = sanitize($_POST['practical_id'] ?? '');
        $userId = sanitize($_POST['user_id'] ?? '');
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM qr_sessions WHERE token = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session || !$practicalId || !$userId) {
            die('<p style="font-family:sans-serif;padding:40px;color:#b91c1c">Invalid attendance claim request.</p>');
        }

        $this->markAttendanceForUser($db, $userId, $practicalId);
        $db->prepare("UPDATE qr_sessions SET status='claimed', user_id=? WHERE token=?")
            ->execute([$userId, $token]);

        $userStmt = $db->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        $this->showAttendanceSuccess($user['full_name'] ?? 'Student');
    }

    private function markAttendanceForUser($db, string $userId, string $practicalId): void {
        $attendanceStmt = $db->prepare("SELECT COUNT(*) AS count FROM attendance WHERE student_id = ? AND practical_id = ?");
        $attendanceStmt->execute([$userId, $practicalId]);
        $row = $attendanceStmt->fetch();
        if ($row['count'] > 0) {
            return;
        }

        $insertStmt = $db->prepare(
            "INSERT INTO attendance (student_id, practical_id, verification_method, marked_at)
             VALUES (?, ?, 'qr', NOW())"
        );
        $insertStmt->execute([$userId, $practicalId]);
        logActivity($userId, 'attendance_marked_qr', 'practicals', $practicalId);
    }

    private function showAttendanceScanPage(string $token, string $practicalId, array $students): void {
        $appUrl = APP_URL;
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>UNILIS SmartLab â€” Attendance Scan</title><style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#0f172a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px} .card{background:#1e293b;border-radius:20px;padding:32px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.4)}h2{text-align:center;font-size:20px;margin-bottom:6px}p{text-align:center;color:#94a3b8;font-size:14px;margin-bottom:20px;line-height:1.5}select{width:100%;padding:14px;background:#0f172a;border:2px solid #334155;border-radius:12px;color:#fff;font-size:15px;margin-bottom:16px;outline:none;appearance:none}select:focus{border-color:#6366f1}button{width:100%;padding:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:600;cursor:pointer;transition:opacity 0.2s}button:active{opacity:0.85}.badge{background:#1e3a5f;color:#6ee7b7;font-size:12px;padding:6px 12px;border-radius:20px;display:block;text-align:center;margin-bottom:20px}</style></head><body><div class="card"><h2>Confirm Attendance</h2><p>Select your name to complete attendance for this practical.</p><span class="badge">Scan successful â€” select yourself to finish</span><form method="POST" action="' . $appUrl . '/attendance-qr/claim"><input type="hidden" name="token" value="' . htmlspecialchars($token) . '"><input type="hidden" name="practical_id" value="' . htmlspecialchars($practicalId) . '"><select name="user_id" required><option value="">â€” Select your name â€”</option>';
        foreach ($students as $student) {
            echo '<option value="' . htmlspecialchars($student['id']) . '">' . htmlspecialchars($student['full_name']) . ' (' . htmlspecialchars($student['reg_number']) . ')</option>';
        }
        echo '</select><button type="submit">Confirm Attendance</button></form></div></body></html>';
    }

    private function showAttendanceSuccess(string $fullName): void {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Attendance Confirmed</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#0f172a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px} .card{background:#1e293b;border-radius:20px;padding:40px;max-width:360px;width:100%;text-align:center} .check{font-size:64px;margin-bottom:16px}h2{font-size:22px;margin-bottom:8px}p{color:#94a3b8;font-size:14px;line-height:1.6;margin-bottom:16px}.badge{background:#134e4a;color:#6ee7b7;padding:10px 16px;border-radius:10px;font-size:13px;display:block}</style></head><body><div class="card"><div class="check">âœ…</div><h2>Attendance Marked</h2><p>' . htmlspecialchars($fullName) . ' has been recorded for this practical.</p><span class="badge">You may now close this window and return to the lab screen.</span></div></body></html>';
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
        <title>UNILIS SmartLab â€” QR Login</title>
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
            <span class="badge">âš¡ One-tap login after first use</span>
            <form method="POST" action="' . $appUrl . '/qr/claim">
                <input type="hidden" name="token" value="' . htmlspecialchars($token) . '">
                <select name="user_id" required>
                    <option value="">â€” Select your name â€”</option>';
        foreach ($users as $u) {
            echo '<option value="' . htmlspecialchars($u['id']) . '">'
                . htmlspecialchars($u['full_name'])
                . ' (' . htmlspecialchars($u['reg_number']) . ')</option>';
        }
        echo '      </select>
                <button type="submit">âœ“ Confirm &amp; Log In</button>
            </form>
        </div>
        </body></html>';
    }

    private function showSuccess(string $name, bool $autoLogin): void {
        $msg = $autoLogin
            ? 'âš¡ Device recognised â€” auto-login!'
            : 'ðŸ“± Device saved â€” next scan will log you in instantly!';
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
            <div class="check">âœ…</div>
            <h2>Welcome, ' . htmlspecialchars($name) . '!</h2>
            <p>You are now logged in.<br>You can close this tab and return to the lab screen.</p>
            <span class="badge">' . $msg . '</span>
        </div>
        </body></html>';
    }
}

