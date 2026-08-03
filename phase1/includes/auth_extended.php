<?php
/**
 * Phase 1 - Extended Authentication
 * UNILIS Academic Foundation Expansion
 * 
 * Extends the existing authentication system to support new roles
 * (department_admin, technician) without modifying the original login flow.
 * 
 * This file should be included AFTER config/db.php and actions.php
 */

// Prevent direct access
if (!defined('PHASE1_ACCESS')) {
    // Allow inclusion from phase1 files
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
        header('HTTP/1.0 403 Forbidden');
        exit('Direct access to this file is forbidden.');
    }
}

// Load Phase 1 configuration
require_once __DIR__ . '/../config/phase1_config.php';

/**
 * Extended login handler for new roles
 * Called from actions.php when universal_login action is processed
 * 
 * @param string $email User email
 * @param string $password User password
 * @param mysqli $conn Database connection
 * @return array|null Login result or null if not matched
 */
function phase1_extended_login($email, $password, $conn) {
    // ── Check department_admin login ──────────────────────────────────────────
    // Department admins are stored in admins table but linked via department_admins
    $sql = "SELECT a.id, a.name, a.password, a.email, da.department_id, da.is_active
            FROM admins a
            JOIN department_admins da ON a.id = da.admin_id
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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = ROLE_DEPARTMENT_ADMIN;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['department_id'] = $user['department_id'];
            
            return [
                'success' => true,
                'role' => ROLE_DEPARTMENT_ADMIN,
                'redirect' => 'phase1/admin/department_admins.php',
            ];
        }
    }
    
    // ── Check technician login ────────────────────────────────────────────────
    $sql = "SELECT id, staff_id, name, email, password, department_id, is_active, is_verified
            FROM technicians
            WHERE email = ? AND is_active = 1
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
            if ($user['is_verified'] == 0) {
                $_SESSION['pending_verification_email'] = $email;
                return [
                    'success' => false,
                    'error' => 'unverified',
                    'redirect' => '../verify.php?unverified=1',
                ];
            }
            
            // Set session as technician
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = ROLE_TECHNICIAN;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['staff_id'] = $user['staff_id'];
            $_SESSION['department_id'] = $user['department_id'];
            
            return [
                'success' => true,
                'role' => ROLE_TECHNICIAN,
                'redirect' => 'phase1/technician/dashboard.php',
            ];
        }
    }
    
    return null; // Not matched
}

/**
 * Check if current user has a specific permission
 * 
 * @param string $permission_key The permission to check
 * @param mysqli $conn Database connection
 * @return bool True if user has permission
 */
function phase1_has_permission($permission_key, $conn) {
    $role = $_SESSION['user_role'] ?? '';
    
    // Global admin has all permissions
    if ($role === 'admin') {
        return true;
    }
    
    // Check role-based permissions from config
    global $ROLE_PERMISSION_MAP;
    if (isset($ROLE_PERMISSION_MAP[$role])) {
        // If role has explicit permissions defined
        if (!empty($ROLE_PERMISSION_MAP[$role])) {
            if (isset($ROLE_PERMISSION_MAP[$role][$permission_key])) {
                return true;
            }
        }
    }
    
    // Check academic assignment-based permissions (from upgraded assignments table)
    if (in_array($role, ['lecturer', 'department_admin'])) {
        $user_id = $_SESSION['user_id'] ?? 0;
        // Check if user has an active academic assignment that grants this permission
        $sql = "SELECT COUNT(*) as cnt
                FROM assignments a
                WHERE a.user_id = ? AND a.user_role = ? 
                AND a.is_active = 1 AND a.assignment_type IS NOT NULL";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row && $row['cnt'] > 0) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Get all permissions for the current user
 * 
 * @param mysqli $conn Database connection
 * @return array List of permission keys
 */
function phase1_get_user_permissions($conn) {
    $role = $_SESSION['user_role'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    $permissions = [];
    
    // Global admin has all permissions
    if ($role === 'admin') {
        global $ROLE_PERMISSION_MAP;
        foreach ($ROLE_PERMISSION_MAP as $role_perms) {
            foreach ($role_perms as $key => $label) {
                $permissions[$key] = $label;
            }
        }
        return $permissions;
    }
    
    // Add role-based permissions from config
    global $ROLE_PERMISSION_MAP;
    if (isset($ROLE_PERMISSION_MAP[$role])) {
        foreach ($ROLE_PERMISSION_MAP[$role] as $key => $label) {
            $permissions[$key] = $label;
        }
    }
    
    // Add assignment-based permissions (from upgraded assignments table)
    $sql = "SELECT a.assignment_type AS permission_key, a.title AS permission_label
            FROM assignments a
            WHERE a.user_id = ? AND a.user_role = ? AND a.is_active = 1 
            AND a.assignment_type IS NOT NULL";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('is', $user_id, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['permission_key']] = $row['permission_label'];
        }
        $stmt->close();
    }
    
    return $permissions;
}

/**
 * Get academic assignments for a user
 * 
 * @param int $user_id User ID
 * @param string $user_role User role
 * @param mysqli $conn Database connection
 * @return array List of assignments
 */
function phase1_get_user_assignments($user_id, $user_role, $conn) {
    $assignments = [];
    
    // Query the upgraded assignments table for academic assignments
    $sql = "SELECT a.*, 
            CASE 
                WHEN a.reference_type = 'unit' THEN (SELECT name FROM units WHERE id = a.reference_id)
                WHEN a.reference_type = 'course' THEN (SELECT name FROM courses WHERE id = a.reference_id)
                WHEN a.reference_type = 'department' THEN (SELECT name FROM departments WHERE id = a.reference_id)
                ELSE NULL 
            END as reference_name
            FROM assignments a
            WHERE a.user_id = ? AND a.user_role = ? AND a.is_active = 1
            AND a.assignment_type IS NOT NULL
            ORDER BY a.assignment_type, a.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('is', $user_id, $user_role);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
        $stmt->close();
    }
    
    return $assignments;
}

/**
 * Get the menu items for the current user based on role and assignments
 * 
 * @param mysqli $conn Database connection
 * @return array Menu structure
 */
function phase1_get_user_menu($conn) {
    $role = $_SESSION['user_role'] ?? '';
    global $MENU_STRUCTURE;
    
    // Return predefined menu for known roles
    if (isset($MENU_STRUCTURE[$role])) {
        return $MENU_STRUCTURE[$role];
    }
    
    // For legacy roles (lecturer, student), return empty
    // Their menus are handled by their existing dashboards
    return null;
}

/**
 * Guard function - redirect if user doesn't have required role
 * 
 * @param string|array $allowed_roles Role(s) allowed to access
 * @param string $redirect_url URL to redirect to if unauthorized
 */
function phase1_guard_role($allowed_roles, $redirect_url = '../login.php') {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header("Location: $redirect_url");
        exit;
    }
    
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Log an upgrade action
 * 
 * @param string $action Action name
 * @param string $description Description
 * @param string $status Status (success, error, warning, info)
 * @param array $details Additional details
 * @param mysqli $conn Database connection
 */
function phase1_log_upgrade($action, $description, $status = 'info', $details = [], $conn = null) {
    if (!$conn) {
        global $conn;
    }
    
    if (!$conn) return;
    
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $details_json = json_encode($details);
    
    $stmt = $conn->prepare("INSERT INTO system_upgrade_logs (action, description, status, details, ip_address, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sssssi', $action, $description, $status, $details_json, $ip, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}