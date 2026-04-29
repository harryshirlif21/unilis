<?php
require_once __DIR__.'/../models/NotebookModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class NotebookController {
    private NotebookModel $model;
    
    public function __construct() {
        $this->model = new NotebookModel();
    }
    
    public function index($param = null) {
        Auth::guard();
        
        $userRole = Auth::role();
        $userId = Auth::id();
        
        if ($userRole === 'student') {
            // Students see their own notebooks
            $notebooks = $this->model->getByStudent($userId);
            renderView('notebooks/student_index', ['notebooks' => $notebooks]);
        } elseif ($userRole === 'technician') {
            // Technicians see pending approvals
            $labId = Auth::guard() ? $_SESSION['lab_id'] : '';
            $pending = $this->model->getPendingApprovals($labId);
            renderView('notebooks/technician_index', ['pending' => $pending]);
        } else {
            // Admins and lecturers see all
            $notebooks = $this->model->getAll();
            renderView('notebooks/admin_index', ['notebooks' => $notebooks]);
        }
    }
    
    public function create($sessionId = null) {
        Auth::guard();
        
        $userRole = Auth::role();
        $userId = Auth::id();
        
        // Get user's existing notebooks
        $existingNotebooks = $this->model->getUserNotebooks($userId);
        
        if (!$sessionId) {
            // If no session ID provided, show available sessions or redirect
            if ($userRole === 'student') {
                // Show notebook management page with existing notebooks
                renderView('notebooks/manage', [
                    'notebooks' => $existingNotebooks,
                    'userRole' => $userRole,
                    'sessions' => $this->getAvailableSessions(),
                    'error' => '',
                    'success' => ''
                ]);
                return;
            } else {
                // For other roles, show available sessions to choose from
                $availableSessions = $this->getAvailableSessions();
                renderView('notebooks/select_session', [
                    'sessions' => $availableSessions,
                    'userRole' => $userRole,
                    'notebooks' => $existingNotebooks,
                    'error' => '',
                    'success' => ''
                ]);
                return;
            }
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = sanitize($_POST['title'] ?? '');
            $sessionId = sanitize($_POST['session_id'] ?? '');
            $content = sanitize($_POST['content'] ?? '');
            $practicalType = sanitize($_POST['practical_type'] ?? 'experiment');
            $difficulty = sanitize($_POST['difficulty'] ?? 'basic');
            $objectives = sanitize($_POST['objectives'] ?? '');
            $materials = sanitize($_POST['materials'] ?? '');
            $safetyNotes = sanitize($_POST['safety_notes'] ?? '');
            $duration = sanitize($_POST['duration'] ?? '');
            $groupWork = sanitize($_POST['group_work'] ?? 'individual');
            $conclusions = sanitize($_POST['conclusions'] ?? '');
            
            if (empty($title)) {
                $error = 'Notebook title is required.';
            } else {
                // Create notebook data with enhanced content
                $notebookData = [
                    'id' => bin2hex(random_bytes(16)),
                    'session_id' => $sessionId ?: null,
                    'student_id' => $userId,
                    'group_id' => null,
                    'title' => $title,
                    'content' => $this->formatNotebookContent([
                        'main_content' => $content,
                        'practical_type' => $practicalType,
                        'difficulty' => $difficulty,
                        'objectives' => $objectives,
                        'materials' => $materials,
                        'safety_notes' => $safetyNotes,
                        'duration' => $duration,
                        'group_work' => $groupWork,
                        'conclusions' => $conclusions
                    ]),
                    'created_by' => $userId,
                    'creator_role' => $userRole
                ];
                
                if ($this->model->create($notebookData)) {
                    logActivity(Auth::id(), 'notebook_created', 'notebooks');
                    $success = 'Notebook created successfully!';
                    
                    // Refresh notebooks list
                    $existingNotebooks = $this->model->getUserNotebooks($userId);
                } else {
                    $error = 'Failed to create notebook. Please try again.';
                }
            }
        }
        
        // Render the manage view with updated data
        renderView('notebooks/manage', [
            'notebooks' => $existingNotebooks,
            'userRole' => $userRole,
            'sessions' => $this->getAvailableSessions(),
            'error' => $error,
            'success' => $success
        ]);
    }
    
    private function formatNotebookContent(array $data): string {
        $content = "";
        
        if (!empty($data['main_content'])) {
            $content .= "## Lab Notes & Observations\n" . $data['main_content'] . "\n\n";
        }
        
        if (!empty($data['practical_type'])) {
            $content .= "## Type of Work\n" . ucfirst($data['practical_type']) . "\n\n";
        }
        
        if (!empty($data['difficulty'])) {
            $content .= "## Difficulty Level\n" . ucfirst($data['difficulty']) . "\n\n";
        }
        
        if (!empty($data['objectives'])) {
            $content .= "## Learning Objectives\n" . $data['objectives'] . "\n\n";
        }
        
        if (!empty($data['materials'])) {
            $content .= "## Materials Used\n" . $data['materials'] . "\n\n";
        }
        
        if (!empty($data['safety_notes'])) {
            $content .= "## Safety Considerations\n" . $data['safety_notes'] . "\n\n";
        }
        
        if (!empty($data['duration'])) {
            $content .= "## Duration\n" . $data['duration'] . "\n\n";
        }
        
        if (!empty($data['group_work'])) {
            $content .= "## Group Work\n" . ucfirst(str_replace('_', ' ', $data['group_work'])) . "\n\n";
        }
        
        if (!empty($data['conclusions'])) {
            $content .= "## Conclusions & Reflections\n" . $data['conclusions'] . "\n\n";
        }
        
        return trim($content);
    }
    
    public function edit($notebookId = null) {
        Auth::guard();
        
        if (!$notebookId) {
            redirect('notebooks');
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            $action = sanitize($_POST['action'] ?? 'save');
            
            if ($this->model->updateContent($notebookId, $content)) {
                if ($action === 'submit') {
                    if ($this->model->submitForApproval($notebookId)) {
                        logActivity(Auth::id(), 'notebook_submitted', 'notebooks');
                        $success = 'Notebook submitted for approval!';
                    } else {
                        $error = 'Failed to submit notebook.';
                    }
                } else {
                    $success = 'Notebook saved successfully!';
                }
                
                // Refresh notebook data
                $notebook = $this->model->getById($notebookId);
            } else {
                $error = 'Failed to save notebook.';
            }
        }
        
        $versions = $this->model->getVersions($notebookId);
        
        renderView('notebooks/edit', [
            'notebook' => $notebook,
            'versions' => $versions,
            'error' => $error,
            'success' => $success
        ]);
    }
    
    public function view($notebookId = null) {
        Auth::guard();
        
        if (!$notebookId) {
            redirect('notebooks');
        }
        
        $notebook = $this->model->getById($notebookId);
        if (!$notebook) {
            redirect('notebooks');
        }
        
        // Check permissions
        $userRole = Auth::role();
        $userId = Auth::id();
        
        if ($userRole === 'student' && $notebook['student_id'] !== $userId) {
            redirect('notebooks');
        }
        
        $versions = $this->model->getVersions($notebookId);
        
        renderView('notebooks/view', [
            'notebook' => $notebook,
            'versions' => $versions,
            'canEdit' => $userRole === 'student' && $notebook['student_id'] === $userId && $notebook['status'] === 'draft'
        ]);
    }
    
    public function submit($notebookId = null) {
        Auth::guard();
        
        if (!$notebookId) {
            redirect('notebooks');
        }
        
        $notebook = $this->model->getById($notebookId);
        
        if (!$notebook) {
            http_response_code(404);
            echo '404 — Notebook not found';
            exit;
        }
        
        // Check permissions
        $userRole = Auth::role();
        $userId = Auth::id();
        
        if (($notebook['student_id'] !== $userId && $notebook['created_by'] !== $userId) || 
            $notebook['status'] !== 'draft') {
            http_response_code(403);
            echo '403 — You cannot submit this notebook';
            exit;
        }
        
        // Update notebook status to submitted
        if ($this->model->updateStatus($notebookId, 'submitted')) {
            logActivity(Auth::id(), 'notebook_submitted', 'notebooks');
            
            // Redirect to notebook management page
            redirect('notebooks/create');
        } else {
            $error = 'Failed to submit notebook. Please try again.';
            
            renderView('notebooks/view', [
                'notebook' => $notebook,
                'error' => $error,
                'versions' => $this->model->getVersions($notebookId),
                'canEdit' => false
            ]);
        }
    }
    
    public function approve($notebookId = null) {
        Auth::guard('technician');
        
        if (!$notebookId) {
            redirect('notebooks');
        }
        
        $notebook = $this->model->getById($notebookId);
        if (!$notebook || $notebook['status'] !== 'submitted') {
            redirect('notebooks');
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = sanitize($_POST['approval_action'] ?? '');
            
            if ($action === 'approve') {
                $signature = generateSignature(Auth::id(), $notebookId);
                
                if ($this->model->approve($notebookId, Auth::id(), $signature)) {
                    logActivity(Auth::id(), 'notebook_approved', 'notebooks');
                    $success = 'Notebook approved successfully!';
                    $notebook = $this->model->getById($notebookId);
                } else {
                    $error = 'Failed to approve notebook.';
                }
            } elseif ($action === 'reject') {
                $comments = sanitize($_POST['comments'] ?? '');
                
                if (empty($comments)) {
                    $error = 'Comments are required for rejection.';
                } elseif ($this->model->reject($notebookId, Auth::id(), $comments)) {
                    logActivity(Auth::id(), 'notebook_rejected', 'notebooks');
                    $success = 'Notebook rejected with feedback.';
                    $notebook = $this->model->getById($notebookId);
                } else {
                    $error = 'Failed to reject notebook.';
                }
            }
        }
        
        renderView('notebooks/approve', [
            'notebook' => $notebook,
            'error' => $error,
            'success' => $success
        ]);
    }
    
    public function version($notebookId = null, $version = null) {
        Auth::guard();
        
        if (!$notebookId || !$version) {
            redirect('notebooks');
        }
        
        $notebook = $this->model->getById($notebookId);
        $versionData = $this->model->getVersion($notebookId, intval($version));
        
        if (!$notebook || !$versionData) {
            redirect('notebooks');
        }
        
        renderView('notebooks/version', [
            'notebook' => $notebook,
            'version' => $versionData
        ]);
    }
    
    public function autosave() {
        Auth::guard('student');
        
        $notebookId = sanitize($_POST['notebook_id'] ?? '');
        $content = $_POST['content'] ?? '';
        
        if (empty($notebookId)) {
            jsonResponse(['success' => false, 'message' => 'Notebook ID required']);
        }
        
        $notebook = $this->model->getById($notebookId);
        if (!$notebook || $notebook['student_id'] !== Auth::id()) {
            jsonResponse(['success' => false, 'message' => 'Access denied']);
        }
        
        if ($this->model->updateContent($notebookId, $content, false)) {
            jsonResponse(['success' => true, 'message' => 'Autosaved']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Autosave failed']);
        }
    }
    
    public function syncGroup() {
        Auth::guard('student');
        
        $groupId = sanitize($_POST['group_id'] ?? '');
        $content = $_POST['content'] ?? '';
        
        if (empty($groupId)) {
            jsonResponse(['success' => false, 'message' => 'Group ID required']);
        }
        
        // Verify user is part of this group
        $groupNotebooks = $this->model->getGroupNotebooks($groupId);
        $userInGroup = false;
        
        foreach ($groupNotebooks as $nb) {
            if ($nb['student_id'] === Auth::id()) {
                $userInGroup = true;
                break;
            }
        }
        
        if (!$userInGroup) {
            jsonResponse(['success' => false, 'message' => 'Access denied']);
        }
        
        if ($this->model->syncGroupContent($groupId, $content)) {
            logActivity(Auth::id(), 'group_notebook_synced', 'notebooks');
            jsonResponse(['success' => true, 'message' => 'Group notebook synced']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Sync failed']);
        }
    }
    
    private function getAvailableSessions(): array {
        $userRole = Auth::role();
        $userId = Auth::id();
        
        $db = getDB();
        
        if ($userRole === 'lecturer') {
            // Lecturers see their own sessions
            $stmt = $db->prepare(
                "SELECT ls.*, p.title as practical_title, l.name as lab_name
                 FROM lab_sessions ls
                 JOIN practicals p ON ls.practical_id = p.id
                 JOIN labs l ON ls.lab_id = l.id
                 WHERE p.lecturer_id = ? AND ls.status = 'completed'
                 ORDER BY ls.started_at DESC"
            );
            $stmt->execute([$userId]);
        } elseif ($userRole === 'admin') {
            // Admins see all sessions
            $stmt = $db->query(
                "SELECT ls.*, p.title as practical_title, l.name as lab_name
                 FROM lab_sessions ls
                 JOIN practicals p ON ls.practical_id = p.id
                 JOIN labs l ON ls.lab_id = l.id
                 WHERE ls.status = 'completed'
                 ORDER BY ls.started_at DESC"
            );
        } elseif ($userRole === 'technician') {
            // Technicians see sessions in their lab
            $labId = $_SESSION['lab_id'] ?? '';
            $stmt = $db->prepare(
                "SELECT ls.*, p.title as practical_title, l.name as lab_name
                 FROM lab_sessions ls
                 JOIN practicals p ON ls.practical_id = p.id
                 JOIN labs l ON ls.lab_id = l.id
                 WHERE ls.lab_id = ? AND ls.status = 'completed'
                 ORDER BY ls.started_at DESC"
            );
            $stmt->execute([$labId]);
        } else {
            return [];
        }
        
        return $stmt->fetchAll();
    }
    
    private function getSessionStudents(string $sessionId): array {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT u.id, u.full_name, u.reg_number
             FROM users u
             JOIN lab_session_students lss ON u.id = lss.student_id
             WHERE lss.session_id = ? AND u.role = 'student'
             ORDER BY u.full_name"
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll();
    }
}
