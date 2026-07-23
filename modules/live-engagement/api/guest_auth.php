<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$action = le_post('action', '');

// Generate auth token for UNILIS SSO
if ($action === 'generate_unilis_token') {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes
    
    $db = le_db();
    $stmt = $db->prepare("
        INSERT INTO live_engagement_migrations (migration, description, applied_at)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE description = ?, applied_at = ?
    ");
    
    // Store token in session for validation
    $_SESSION['le_unilis_token'] = $token;
    $_SESSION['le_unilis_token_expires'] = $expiresAt;
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'expires' => $expiresAt,
        'login_url' => '/login.php?le_token=' . $token
    ]);
    exit;
}

// Validate UNILIS token after login
if ($action === 'validate_unilis_token') {
    $token = le_post('token', '');
    $storedToken = $_SESSION['le_unilis_token'] ?? '';
    $expires = $_SESSION['le_unilis_token_expires'] ?? '';
    
    if (!$token || $token !== $storedToken) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
    
    if (strtotime($expires) < time()) {
        echo json_encode(['success' => false, 'error' => 'Token expired']);
        exit;
    }
    
    // Token valid - check if user is logged in via UNILIS
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
        // Map UNILIS user to Live Engagement session
        $_SESSION['le_authenticated'] = true;
        $_SESSION['le_user_id'] = $_SESSION['user_id'];
        $_SESSION['le_user_role'] = $_SESSION['user_role'];
        $_SESSION['le_user_name'] = $_SESSION['user_name'] ?? '';
        
        // Clear token
        unset($_SESSION['le_unilis_token']);
        unset($_SESSION['le_unilis_token_expires']);
        
        echo json_encode([
            'success' => true,
            'redirect' => le_page_url('dashboard') . '&create=1&type=presentation'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not authenticated with UNILIS']);
    }
    exit;
}

switch ($action) {

    // ----------------------------------------------------------
    case 'signup':
        $name         = trim(le_post('name', ''));
        $email        = trim(strtolower(le_post('email', '')));
        $organisation = trim(le_post('organisation', ''));
        $role         = trim(le_post('role', ''));
        $password     = le_post('password', '');

        $errors = [];
        if (strlen($name) < 2)              $errors[] = 'Name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (strlen($password) < 8)          $errors[] = 'Password must be at least 8 characters.';
        if (strlen($organisation) < 2)      $errors[] = 'Organisation is required.';
        if (strlen($role) < 2)              $errors[] = 'Role is required.';

        if ($errors) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        $db = le_db();

        // Check duplicate
        $stmt = $db->prepare("SELECT id FROM le_guest_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'errors' => ['An account with that email already exists.']]);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO le_guest_users (name, email, organisation, role, password_hash)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $organisation, $role, $hash]);
        $userId = $db->lastInsertId();

        // Start LE guest session
        $_SESSION['le_guest_id']   = $userId;
        $_SESSION['le_guest_name'] = $name;
        $_SESSION['le_guest_role'] = 'guest_presenter';

        echo json_encode(['success' => true, 'redirect' => le_page_url('presentations') . '&new=1']);
        break;

    // ----------------------------------------------------------
    case 'login':
        $email    = trim(strtolower(le_post('email', '')));
        $password = le_post('password', '');

        if (!$email || !$password) {
            echo json_encode(['success' => false, 'errors' => ['Email and password are required.']]);
            exit;
        }

        $db   = le_db();
        $stmt = $db->prepare("SELECT * FROM le_guest_users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'errors' => ['Invalid email or password.']]);
            exit;
        }

        // Update last login
        $db->prepare("UPDATE le_guest_users SET last_login_at = NOW() WHERE id = ?")
           ->execute([$user['id']]);

        $_SESSION['le_guest_id']   = $user['id'];
        $_SESSION['le_guest_name'] = $user['name'];
        $_SESSION['le_guest_role'] = 'guest_presenter';

        echo json_encode(['success' => true, 'redirect' => le_page_url('dashboard')]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['Invalid action.']]);
}