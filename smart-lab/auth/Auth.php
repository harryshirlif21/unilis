<?php
require_once __DIR__.'/../config/app.php';

class Auth {
    public static function login(string $regNo, string $password): bool {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE reg_number = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$regNo]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['lab_id']    = $user['lab_id'] ?? '';
            $_SESSION['auth_method'] = 'password';
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    public static function loginByEmail(string $email, string $password): bool {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['lab_id']    = $user['lab_id'] ?? '';
            $_SESSION['auth_method'] = 'password';
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    public static function loginBiometric(string $biometricHash): bool {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE biometric_hash = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$biometricHash]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['lab_id']    = $user['lab_id'] ?? '';
            $_SESSION['auth_method'] = 'biometric';
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    public static function loginByQR(string $qrToken, string $sessionId): bool {
        if (!self::verifyQR($qrToken, $sessionId)) {
            return false;
        }
        
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT u.* FROM users u 
             JOIN lab_sessions ls ON u.lab_id = ls.lab_id 
             WHERE ls.id = ? AND u.role = 'student' AND u.is_active = 1 
             LIMIT 1"
        );
        $stmt->execute([$sessionId]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['lab_id']    = $user['lab_id'] ?? '';
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['auth_method'] = 'qr';
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    public static function loginByCode(string $confirmationCode): array {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT ls.*, l.name as lab_name FROM lab_sessions ls
             JOIN labs l ON ls.lab_id = l.id
             WHERE ls.confirmation_code = ? AND ls.status = 'open'
             AND ls.started_at >= DATE_SUB(NOW(), INTERVAL 8 HOUR)
             LIMIT 1"
        );
        $stmt->execute([$confirmationCode]);
        $session = $stmt->fetch();
        
        if (!$session) {
            return ['success' => false, 'message' => 'Invalid or expired confirmation code'];
        }
        
        // Get available students in this lab
        $studentsStmt = $db->prepare(
            "SELECT id, full_name, reg_number FROM users
             WHERE lab_id = ? AND role = 'student' AND is_active = 1"
        );
        $studentsStmt->execute([$session['lab_id']]);
        $students = $studentsStmt->fetchAll();
        
        return [
            'success' => true, 
            'session' => $session,
            'students' => $students
        ];
    }
    
    public static function selectStudentForSession(string $userId, string $sessionId): bool {
        $db = getDB();
        
        // Verify session is still valid
        $sessionStmt = $db->prepare(
            "SELECT id FROM lab_sessions 
             WHERE id = ? AND status = 'open' 
             AND started_at >= DATE_SUB(NOW(), INTERVAL 8 HOUR)
             LIMIT 1"
        );
        $sessionStmt->execute([$sessionId]);
        if (!$sessionStmt->fetch()) {
            return false;
        }
        
        // Get user details
        $userStmt = $db->prepare(
            "SELECT id, full_name, role, lab_id FROM users 
             WHERE id = ? AND is_active = 1 LIMIT 1"
        );
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['lab_id']    = $user['lab_id'] ?? '';
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['auth_method'] = 'code';
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    public static function registerBiometric(string $userId, string $biometricData): bool {
        $db = getDB();
        $biometricHash = hash('sha256', $biometricData . BIOMETRIC_SALT);
        
        $stmt = $db->prepare(
            "UPDATE users SET biometric_hash = ? WHERE id = ?"
        );
        return $stmt->execute([$biometricHash, $userId]);
    }
    
    public static function getAuthMethods(): array {
        $methods = ['password', 'qr', 'code'];
        
        // Check if biometric is available
        if (defined('BIOMETRIC_ENABLED') && BIOMETRIC_ENABLED) {
            $methods[] = 'biometric';
        }
        
        return $methods;
    }
    
    public static function getCurrentAuthMethod(): string {
        return $_SESSION['auth_method'] ?? 'unknown';
    }
    
    public static function requireMultiFactor(): bool {
        // Require 2FA for admin and lecturer roles
        $role = self::role();
        return in_array($role, ['admin', 'lecturer']);
    }
    
    public static function initiateMultiFactor(string $userId): string {
        $code = self::generateCode();
        $_SESSION['mfa_code'] = $code;
        $_SESSION['mfa_expires'] = time() + 300; // 5 minutes
        
        // In a real implementation, send this via SMS/email
        // For demo, we'll store it in session
        
        return $code;
    }
    
    public static function verifyMultiFactor(string $code): bool {
        return isset($_SESSION['mfa_code']) && 
               isset($_SESSION['mfa_expires']) &&
               time() < $_SESSION['mfa_expires'] &&
               strtoupper($code) === $_SESSION['mfa_code'];
    }
    public static function logout(): void {
        session_destroy();
        header('Location: '.APP_URL.'/auth/login'); exit;
    }
    
    public static function check(): bool {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $inactive_time = time() - $_SESSION['last_activity'];
            $session_lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600; // Default 1 hour
            
            if ($inactive_time > $session_lifetime) {
                self::logout();
                return false;
            }
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function role(): string  { return $_SESSION['user_role'] ?? ''; }
    public static function id(): string    { return $_SESSION['user_id']   ?? ''; }
    public static function name(): string  { return $_SESSION['user_name'] ?? ''; }
    public static function guard(string $role = ''): void {
        if (!self::check()) { header('Location: '.APP_URL.'/auth/login'); exit; }
        if ($role && self::role() !== $role) { http_response_code(403); echo '403 Forbidden'; exit; }
    }
    public static function generateQR(string $sessionId): string {
        $payload = json_encode(['sid' => $sessionId, 'ts' => time()]);
        return base64_encode(hash_hmac('sha256', $payload, QR_SECRET_KEY).'|'.$payload);
    }
    public static function verifyQR(string $token, string $sessionId): bool {
        $decoded = base64_decode($token);
        [$sig, $payload] = explode('|', $decoded, 2);
        $data = json_decode($payload, true);
        if (($data['sid'] ?? '') !== $sessionId) return false;
        if (time() - ($data['ts']  ?? 0) > 300)  return false;
        return hash_hmac('sha256', $payload, QR_SECRET_KEY) === $sig;
    }
    public static function generateCode(): string {
        return strtoupper(bin2hex(random_bytes(3)));
    }
}
