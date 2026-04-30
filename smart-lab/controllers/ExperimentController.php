<?php
// Environment detection
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once __DIR__.'/../config/app_production.php';
    require_once __DIR__.'/../config/database_production.php';
} else {
    require_once __DIR__.'/../config/app.php';
    require_once __DIR__.'/../config/database.php';
}

require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class ExperimentController {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Create new experiment
    public function create($param = null) {
        Auth::guard('technician');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = sanitize($_POST['title'] ?? '');
            $unit_code = sanitize($_POST['unit_code'] ?? '');
            $unit_name = sanitize($_POST['unit_name'] ?? '');
            $group = sanitize($_POST['group'] ?? '');
            
            if (empty($title) || empty($unit_code) || empty($unit_name)) {
                $_SESSION['error'] = 'Title, Unit Code, and Unit Name are required';
                renderView('experiments/create', []);
                return;
            }
            
            try {
                $this->db->beginTransaction();
                
                // Insert experiment
                $stmt = $this->db->prepare("
                    INSERT INTO experiments (title, unit_code, unit_name, `group`, technician_id, status)
                    VALUES (?, ?, ?, ?, ?, 'draft')
                ");
                $stmt->execute([$title, $unit_code, $unit_name, $group, Auth::id()]);
                $experiment_id = $this->db->lastInsertId();
                
                $this->db->commit();
                
                $_SESSION['success'] = 'Experiment created successfully';
                header('Location: '.APP_URL.'/experiments/edit/'.$experiment_id);
                exit;
                
            } catch (Exception $e) {
                $this->db->rollback();
                error_log("Experiment creation error: " . $e->getMessage());
                $_SESSION['error'] = 'Failed to create experiment';
                renderView('experiments/create', []);
            }
        } else {
            renderView('experiments/create', []);
        }
    }
    
    // Edit experiment
    public function edit($param = null) {
        Auth::guard('technician');
        
        $experiment_id = intval($param);
        if (!$experiment_id) {
            header('Location: '.APP_URL.'/experiments');
            exit;
        }
        
        // Get experiment details
        $stmt = $this->db->prepare("
            SELECT * FROM experiments 
            WHERE id = ? AND technician_id = ?
        ");
        $stmt->execute([$experiment_id, Auth::id()]);
        $experiment = $stmt->fetch();
        
        if (!$experiment) {
            $_SESSION['error'] = 'Experiment not found';
            header('Location: '.APP_URL.'/experiments');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->updateExperiment($experiment_id);
        } else {
            // Load experiment sections
            $sections = $this->getExperimentSections($experiment_id);
            $apparatus = $this->getExperimentApparatus($experiment_id);
            $procedure_steps = $this->getExperimentProcedureSteps($experiment_id);
            $results_structure = $this->getExperimentResultsStructure($experiment_id);
            
            renderView('experiments/edit', [
                'experiment' => $experiment,
                'sections' => $sections,
                'apparatus' => $apparatus,
                'procedure_steps' => $procedure_steps,
                'results_structure' => $results_structure
            ]);
        }
    }
    
    // Update experiment details
    private function updateExperiment($experiment_id) {
        $title = sanitize($_POST['title'] ?? '');
        $unit_code = sanitize($_POST['unit_code'] ?? '');
        $unit_name = sanitize($_POST['unit_name'] ?? '');
        $group = sanitize($_POST['group'] ?? '');
        
        try {
            $this->db->beginTransaction();
            
            // Update experiment basic info
            $stmt = $this->db->prepare("
                UPDATE experiments 
                SET title = ?, unit_code = ?, unit_name = ?, `group` = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND technician_id = ?
            ");
            $stmt->execute([$title, $unit_code, $unit_name, $group, $experiment_id, Auth::id()]);
            
            // Update sections
            $this->updateExperimentSections($experiment_id);
            
            // Update apparatus
            $this->updateExperimentApparatus($experiment_id);
            
            // Update procedure steps
            $this->updateExperimentProcedureSteps($experiment_id);
            
            // Update results structure
            $this->updateExperimentResultsStructure($experiment_id);
            
            $this->db->commit();
            
            $_SESSION['success'] = 'Experiment updated successfully';
            header('Location: '.APP_URL.'/experiments');
            exit;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Experiment update error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to update experiment';
        }
    }
    
    // Get experiment sections
    private function getExperimentSections($experiment_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM experiment_sections 
            WHERE experiment_id = ? 
            ORDER BY display_order ASC
        ");
        $stmt->execute([$experiment_id]);
        return $stmt->fetchAll();
    }
    
    // Get experiment apparatus
    private function getExperimentApparatus($experiment_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM experiment_apparatus 
            WHERE experiment_id = ? 
            ORDER BY display_order ASC
        ");
        $stmt->execute([$experiment_id]);
        return $stmt->fetchAll();
    }
    
    // Get experiment procedure steps
    private function getExperimentProcedureSteps($experiment_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM experiment_procedure_steps 
            WHERE experiment_id = ? 
            ORDER BY step_number ASC
        ");
        $stmt->execute([$experiment_id]);
        return $stmt->fetchAll();
    }
    
    // Get experiment results structure
    private function getExperimentResultsStructure($experiment_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM experiment_results_structure 
            WHERE experiment_id = ? 
            ORDER BY column_order ASC
        ");
        $stmt->execute([$experiment_id]);
        return $stmt->fetchAll();
    }
    
    // Update experiment sections
    private function updateExperimentSections($experiment_id) {
        // Delete existing sections
        $stmt = $this->db->prepare("DELETE FROM experiment_sections WHERE experiment_id = ?");
        $stmt->execute([$experiment_id]);
        
        // Insert new sections
        $sections = $_POST['sections'] ?? [];
        foreach ($sections as $index => $section) {
            if (!empty($section['content'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO experiment_sections (experiment_id, section_type, section_title, content, display_order)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $experiment_id,
                    $section['type'],
                    $section['title'] ?? '',
                    $section['content'],
                    $index
                ]);
            }
        }
    }
    
    // Update experiment apparatus
    private function updateExperimentApparatus($experiment_id) {
        // Delete existing apparatus
        $stmt = $this->db->prepare("DELETE FROM experiment_apparatus WHERE experiment_id = ?");
        $stmt->execute([$experiment_id]);
        
        // Insert new apparatus
        $apparatus = $_POST['apparatus'] ?? [];
        foreach ($apparatus as $index => $item) {
            if (!empty($item['name'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO experiment_apparatus (experiment_id, item_name, quantity, display_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $experiment_id,
                    $item['name'],
                    $item['quantity'] ?? '',
                    $index
                ]);
            }
        }
    }
    
    // Update experiment procedure steps
    private function updateExperimentProcedureSteps($experiment_id) {
        // Delete existing steps
        $stmt = $this->db->prepare("DELETE FROM experiment_procedure_steps WHERE experiment_id = ?");
        $stmt->execute([$experiment_id]);
        
        // Insert new steps
        $steps = $_POST['procedure_steps'] ?? [];
        foreach ($steps as $index => $step) {
            if (!empty($step['description'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO experiment_procedure_steps (experiment_id, step_number, step_description, display_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $experiment_id,
                    $index + 1,
                    $step['description'],
                    $index
                ]);
            }
        }
    }
    
    // Update experiment results structure
    private function updateExperimentResultsStructure($experiment_id) {
        // Delete existing structure
        $stmt = $this->db->prepare("DELETE FROM experiment_results_structure WHERE experiment_id = ?");
        $stmt->execute([$experiment_id]);
        
        // Insert new structure
        $columns = $_POST['results_columns'] ?? [];
        foreach ($columns as $index => $column) {
            if (!empty($column['name'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO experiment_results_structure (experiment_id, column_name, column_type, column_order, formula)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $experiment_id,
                    $column['name'],
                    $column['type'] ?? 'text',
                    $index,
                    $column['formula'] ?? null
                ]);
            }
        }
    }
    
    // List experiments
    public function index($param = null) {
        Auth::guard('technician');
        
        $page = intval($_GET['page'] ?? 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->db->prepare("
            SELECT e.*, COUNT(ls.id) as schedule_count
            FROM experiments e
            LEFT JOIN lab_schedules ls ON e.id = ls.experiment_id
            WHERE e.technician_id = ?
            GROUP BY e.id
            ORDER BY e.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([Auth::id(), $limit, $offset]);
        $experiments = $stmt->fetchAll();
        
        // Get total count
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM experiments WHERE technician_id = ?");
        $stmt->execute([Auth::id()]);
        $total = $stmt->fetch()['total'];
        
        $total_pages = ceil($total / $limit);
        
        renderView('experiments/index', [
            'experiments' => $experiments,
            'page' => $page,
            'total_pages' => $total_pages,
            'total' => $total
        ]);
    }
    
    // Delete experiment
    public function delete($param = null) {
        Auth::guard('technician');
        
        $experiment_id = intval($param);
        if (!$experiment_id) {
            header('Location: '.APP_URL.'/experiments');
            exit;
        }
        
        // Check if experiment belongs to technician
        $stmt = $this->db->prepare("
            SELECT id FROM experiments 
            WHERE id = ? AND technician_id = ?
        ");
        $stmt->execute([$experiment_id, Auth::id()]);
        $experiment = $stmt->fetch();
        
        if (!$experiment) {
            $_SESSION['error'] = 'Experiment not found';
            header('Location: '.APP_URL.'/experiments');
            exit;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Delete experiment (cascade will delete related records)
            $stmt = $this->db->prepare("DELETE FROM experiments WHERE id = ? AND technician_id = ?");
            $stmt->execute([$experiment_id, Auth::id()]);
            
            $this->db->commit();
            
            $_SESSION['success'] = 'Experiment deleted successfully';
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Experiment deletion error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to delete experiment';
        }
        
        header('Location: '.APP_URL.'/experiments');
        exit;
    }
    
    // Publish experiment
    public function publish($param = null) {
        Auth::guard('technician');
        
        $experiment_id = intval($param);
        if (!$experiment_id) {
            header('Location: '.APP_URL.'/experiments');
            exit;
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE experiments 
                SET status = 'published', updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND technician_id = ? AND status = 'draft'
            ");
            $stmt->execute([$experiment_id, Auth::id()]);
            
            $_SESSION['success'] = 'Experiment published successfully';
            
        } catch (Exception $e) {
            error_log("Experiment publish error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to publish experiment';
        }
        
        header('Location: '.APP_URL.'/experiments');
        exit;
    }
}
