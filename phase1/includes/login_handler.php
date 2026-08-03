<?php
/**
 * Phase 1 - Login Handler Integration
 * UNILIS Academic Foundation Expansion
 * 
 * This file integrates new role logins into the existing authentication system.
 * Include this in actions.php after the existing universal_login handler.
 * 
 * It handles login for:
 * - department_admin (stored in admins table, linked via department_admins)
 * - technician (stored in technicians table)
 */

// Prevent direct access
if (!defined('PHASE1_ACCESS')) {
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
        header('HTTP/1.0 403 Forbidden');
        exit;
    }
}

require_once __DIR__ . '/../config/phase1_config.php';

/**
 * Attempt Phase 1 role login. Called from actions.php when universal_login fails
 * to match student/lecturer/admin roles.
 * 
 * @param string $email User email
 * @param string $password User password
 * @param mysqli $conn Database connection
 * @return bool True if login succeeded
 */
function phase1_try_login($email, $password, $conn) {
    // ── 1. Check Department Admin login ──────────────────────────────────────
    // Department admins are regular admins who are assigned to departments
    $sql = "SELECT a.id, a.name, a.password, a.email, a.is_super_admin, a.is_verified,
                   da.department_id, da.is_active as dept_admin_active,
                   d.name as department_name
            FROM admins a
            JOIN department_admins da ON a.id = da.admin_id
            JOIN departments d ON da.department_id = d.id
            WHERE a.email = ? AND da.is_active = 1
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session as department_admin
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = ROLE_DEPARTMENT_ADMIN;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['department_id'] = (int)$user['department_id'];
            $_SESSION['department_name'] = $user['department_name'];
            
            return true;
        }
    }
    
    // ── 2. Check Technician login ────────────────────────────────────────────
    $sql = "SELECT t.id, t.staff_id, t.name, t.email, t.password, 
                   t.department_id, t.is_active, t.is_verified,
                   d.name as department_name
            FROM technicians t
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.email = ? AND t.is_active = 1
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check verification
            if ((int)$user['is_verified'] === 0) {
                $_SESSION['pending_verification_email'] = $email;
                $_SESSION['login_error'] = "Please verify your email first. Check your inbox.";
                return false;
            }
            
            // Set session as technician
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = ROLE_TECHNICIAN;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['staff_id'] = $user['staff_id'];
            $_SESSION['department_id'] = $user['department_id'] ? (int)$user['department_id'] : null;
            $_SESSION['department_name'] = $user['department_name'] ?? '';
            
            return true;
        }
    }
    
    return false;
}

/**
 * Get the redirect URL for a Phase 1 role
 * 
 * @param string $role The user role
 * @return string Redirect URL
 */
function phase1_get_role_redirect($role) {
    switch ($role) {
        case ROLE_DEPARTMENT_ADMIN:
            return 'phase1/department_admin/dashboard.php';
        case ROLE_TECHNICIAN:
            return 'phase1/technician/dashboard.php';
        default:
            return 'index.html';
    }
}