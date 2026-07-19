<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$action = le_post('action', '');

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