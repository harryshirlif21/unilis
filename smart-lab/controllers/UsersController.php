<?php
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class UsersController {

    public function index($param = null) {
        // Admin only access
        Auth::guard('admin');
        
        $db = getDB();
        
        // Get all users with role filter
        $roleFilter = sanitize($_GET['role'] ?? '');
        $statusFilter = sanitize($_GET['status'] ?? '');
        $search = sanitize($_GET['search'] ?? '');
        
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($roleFilter && $roleFilter !== 'all') {
            $whereClause .= " AND role = ?";
            $params[] = $roleFilter;
        }
        
        if ($statusFilter && $statusFilter !== 'all') {
            $whereClause .= " AND is_active = ?";
            $params[] = ($statusFilter === 'active') ? 1 : 0;
        }
        
        if ($search) {
            $whereClause .= " AND (full_name LIKE ? OR reg_number LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $query = "SELECT id, full_name, reg_number, email, role, department, lab_id, is_active, created_at 
                 FROM users $whereClause 
                 ORDER BY role ASC, full_name ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        // Get labs for lookup
        $labs = $db->query("SELECT id, name, lab_code FROM labs WHERE is_active = 1 ORDER BY name")->fetchAll();
        
        // Get role counts
        $roleCounts = [
            'all' => $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'],
            'student' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch()['count'],
            'lecturer' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'lecturer'")->fetch()['count'],
            'technician' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'technician'")->fetch()['count'],
            'admin' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch()['count']
        ];
        
        // Create lab lookup array
        $labLookup = [];
        foreach ($labs as $lab) {
            $labLookup[$lab['id']] = $lab['name'] . ' (' . $lab['lab_code'] . ')';
        }
        
        renderView('users/index', [
            'users' => $users,
            'labs' => $labLookup,
            'role_counts' => $roleCounts,
            'total_users' => $roleCounts['all'],
            'current_role_filter' => $roleFilter,
            'current_status_filter' => $statusFilter,
            'current_search' => $search
        ]);
    }
    
    public function toggle($param = null) {
        // Admin only access
        Auth::guard('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.0 405 Method Not Allowed');
            exit;
        }
        
        $userId = sanitize($_POST['user_id'] ?? '');
        
        if (empty($userId)) {
            $_SESSION['error'] = 'User ID is required';
            header('Location: ' . APP_URL . '/users');
            exit;
        }
        
        $db = getDB();
        
        // Don't allow deactivating the current admin
        if ($userId === Auth::id()) {
            $_SESSION['error'] = 'Cannot deactivate your own account';
            header('Location: ' . APP_URL . '/users');
            exit;
        }
        
        // Toggle user status
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'User status updated successfully';
            logActivity(Auth::id(), 'user_status_toggled', 'users', ['target_user_id' => $userId]);
        } else {
            $_SESSION['error'] = 'User not found or no changes made';
        }
        
        header('Location: ' . APP_URL . '/users');
        exit;
    }
    
    public function delete($param = null) {
        // Admin only access
        Auth::guard('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.0 405 Method Not Allowed');
            exit;
        }
        
        $userId = sanitize($_POST['user_id'] ?? '');
        
        if (empty($userId)) {
            $_SESSION['error'] = 'User ID is required';
            header('Location: ' . APP_URL . '/users');
            exit;
        }
        
        $db = getDB();
        
        // Don't allow deleting the current admin or other admins
        if ($userId === Auth::id()) {
            $_SESSION['error'] = 'Cannot delete your own account';
            header('Location: ' . APP_URL . '/users');
            exit;
        }
        
        // Check if target user is admin
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user && $user['role'] === 'admin') {
            $_SESSION['error'] = 'Cannot delete admin users';
            header('Location: ' . APP_URL . '/users');
            exit;
        }
        
        // Soft delete by setting is_active = 0
        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role != 'admin'");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'User deactivated successfully';
            logActivity(Auth::id(), 'user_deactivated', 'users', ['target_user_id' => $userId]);
        } else {
            $_SESSION['error'] = 'User not found or cannot be deactivated';
        }
        
        header('Location: ' . APP_URL . '/users');
        exit;
    }
    
    public function view($param = null) {
        // Admin only access
        Auth::guard('admin');
        
        $userId = sanitize($param[0] ?? '');
        
        if (empty($userId)) {
            $_SESSION['error'] = 'User ID is required';
            header('Location: ' . APP_URL . '/users');
            exit;
        }
        
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error'] = 'User not found';
            header('Location: ' . APP_URL . '/users');
            exit;
        }
        
        // Get user's recent activity
        $activityStmt = $db->prepare("
            SELECT * FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $activityStmt->execute([$userId]);
        $activities = $activityStmt->fetchAll();
        
        renderView('users/view', [
            'user' => $user,
            'activities' => $activities
        ]);
    }
}
