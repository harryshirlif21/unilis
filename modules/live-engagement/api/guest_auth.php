<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$action = le_post('action', '');

// Generate auth token for UNILIS SSO
if ($action === 'generate_unilis_token') {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes
    
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
            'redirect' => le_page_url('dashboard')
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
        if ($db->fetchOne("SELECT id FROM le_guest_users WHERE email = ? LIMIT 1", [$email])) {
            echo json_encode(['success' => false, 'errors' => ['An account with that email already exists.']]);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $userId = $db->insert(
            "INSERT INTO le_guest_users (name, email, organisation, role, password_hash)
             VALUES (?, ?, ?, ?, ?)",
            [$name, $email, $organisation, $role, $hash],
            'sssss'
        );
        if (!$userId) {
            echo json_encode(['success' => false, 'errors' => ['Could not create the account. Please try again.']]);
            exit;
        }

        // Start LE guest session
        $_SESSION['le_guest_id']   = $userId;
        $_SESSION['le_guest_name'] = $name;
        $_SESSION['le_guest_role'] = 'guest_presenter';

        echo json_encode(['success' => true, 'redirect' => le_page_url('presentations') . '&new=1']);
        break;

    // ----------------------------------------------------------
    // Quick guest join — no email/password account required. The visitor
    // only supplies a display name and is marked as an authenticated guest
    // participant so the session join API accepts them.
    case 'join_as_guest':
        $name = trim(le_post('name', ''));
        $email = trim(strtolower(le_post('email', '')));

        if (strlen($name) < 2) {
            echo json_encode(['success' => false, 'errors' => ['Please enter your display name.']]);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'errors' => ['Please enter a valid email address.']]);
            exit;
        }

        $guestId = null;
        try {
            $db = le_db();

            // Older deployments may have the Live Engagement tables but not
            // the guest identity table. Create it when database permissions
            // allow; otherwise continue with a session-only guest identity so
            // joining is not blocked by an optional persistence table.
            $db->rawQuery(
                "CREATE TABLE IF NOT EXISTS `le_guest_users` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(150) NOT NULL,
                    `email` VARCHAR(255) NOT NULL,
                    `organisation` VARCHAR(255) NOT NULL DEFAULT 'Live session guest',
                    `role` VARCHAR(100) NOT NULL DEFAULT 'participant',
                    `password_hash` VARCHAR(255) NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `last_login_at` DATETIME NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_le_guest_users_email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $guest = $db->fetchOne("SELECT id FROM le_guest_users WHERE email = ? LIMIT 1", [$email]);
            if ($guest) {
                $guestId = (int) $guest['id'];
                $db->update(
                    "UPDATE le_guest_users SET name = ?, is_active = 1, last_login_at = NOW() WHERE id = ?",
                    [$name, $guestId],
                    'si'
                );
            } else {
                $guestId = (int) $db->insert(
                    "INSERT INTO le_guest_users (name, email, organisation, role, password_hash)
                     VALUES (?, ?, 'Live session guest', 'participant', ?)",
                    [$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)],
                    'sss'
                );
            }
        } catch (Throwable $e) {
            error_log('Live Engagement guest persistence failed: ' . $e->getMessage());
            error_log($e->getTraceAsString());
        }

        $_SESSION['le_guest_access']   = true;
        $_SESSION['le_guest_id']       = $guestId;
        $_SESSION['le_guest_name']     = $name;
        $_SESSION['le_guest_email']    = $email;
        $_SESSION['le_guest_role']     = 'guest_participant';
        $_SESSION['le_guest_joined_at'] = date('Y-m-d H:i:s');

        echo json_encode([
            'success' => true,
            'guest_id' => $guestId,
            'name'    => $name,
            'email'   => $email,
        ]);
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
        $user = $db->fetchOne("SELECT * FROM le_guest_users WHERE email = ? AND is_active = 1 LIMIT 1", [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'errors' => ['Invalid email or password.']]);
            exit;
        }

        // Update last login
        $db->update("UPDATE le_guest_users SET last_login_at = NOW() WHERE id = ?", [(int) $user['id']], 'i');

        $_SESSION['le_guest_id']   = $user['id'];
        $_SESSION['le_guest_name'] = $user['name'];
        $_SESSION['le_guest_role'] = 'guest_presenter';

        echo json_encode(['success' => true, 'redirect' => le_page_url('dashboard')]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['Invalid action.']]);
}