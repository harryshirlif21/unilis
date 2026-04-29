<?php
require_once __DIR__.'/../models/AssetModel.php';
require_once __DIR__.'/../blockchain/AssetTracker.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class AssetController {
    private AssetModel $model;
    private AssetTracker $tracker;
    
    public function __construct() {
        $this->model = new AssetModel();
        $this->tracker = new AssetTracker();
    }
    
    public function index($param = null) {
        Auth::guard();
        
        $labId = Auth::role() === 'admin' ? null : Auth::id();
        $assets = $this->model->getAll($labId);
        $labs = $this->model->getLabs();
        
        renderView('assets/index', [
            'assets' => $assets,
            'labs' => $labs,
            'chainStats' => $this->tracker->getChainStats()
        ]);
    }
    
    public function create($param = null) {
        Auth::guard('admin');
        
        $error = '';
        $success = '';
        $labs = $this->model->getLabs();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'id' => bin2hex(random_bytes(16)),
                'asset_code' => sanitize($_POST['asset_code'] ?? ''),
                'name' => sanitize($_POST['name'] ?? ''),
                'type' => sanitize($_POST['type'] ?? ''),
                'lab_id' => sanitize($_POST['lab_id'] ?? ''),
                'quantity' => floatval($_POST['quantity'] ?? 1),
                'unit' => sanitize($_POST['unit'] ?? ''),
                'serial_number' => sanitize($_POST['serial_number'] ?? ''),
                'purchase_date' => $_POST['purchase_date'] ?? '',
                'notes' => sanitize($_POST['notes'] ?? '')
            ];
            
            // Validate
            if (empty($data['asset_code']) || empty($data['name']) || empty($data['type'])) {
                $error = 'Asset code, name, and type are required.';
            } else {
                // Create asset in database
                if ($this->model->create($data)) {
                    // Register in blockchain
                    $this->tracker->register($data, Auth::id(), $data['lab_id']);
                    
                    logActivity(Auth::id(), 'asset_created', 'assets');
                    $success = 'Asset created successfully and registered in blockchain!';
                } else {
                    $error = 'Failed to create asset.';
                }
            }
        }
        
        renderView('assets/create', [
            'error' => $error,
            'success' => $success,
            'labs' => $labs
        ]);
    }
    
    public function view($assetId = null) {
        Auth::guard();
        
        if (!$assetId) {
            redirect('assets');
        }
        
        $asset = $this->model->getById($assetId);
        if (!$asset) {
            redirect('assets');
        }
        
        $blockchainHistory = $this->tracker->history($assetId);
        $transactions = $this->tracker->getAssetTransactions($assetId);
        
        renderView('assets/view', [
            'asset' => $asset,
            'blockchainHistory' => $blockchainHistory,
            'transactions' => $transactions
        ]);
    }
    
    public function issue($assetId = null) {
        Auth::guard('technician');
        
        if (!$assetId) {
            redirect('assets');
        }
        
        $asset = $this->model->getById($assetId);
        if (!$asset) {
            redirect('assets');
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = sanitize($_POST['user_id'] ?? '');
            $quantity = floatval($_POST['quantity'] ?? 1);
            $purpose = sanitize($_POST['purpose'] ?? '');
            
            if (empty($userId) || $quantity <= 0) {
                $error = 'User and quantity are required.';
            } elseif ($quantity > $asset['quantity']) {
                $error = 'Insufficient quantity available.';
            } else {
                // Issue asset
                $block = $this->tracker->issue($assetId, $userId, $asset['lab_id'], $quantity, [
                    'purpose' => $purpose
                ]);
                
                // Update asset quantity
                $this->model->updateQuantity($assetId, $asset['quantity'] - $quantity);
                
                logActivity(Auth::id(), 'asset_issued', 'assets');
                $success = 'Asset issued successfully! Block #' . $block->index . ' created.';
            }
        }
        
        $users = $this->model->getLabUsers($asset['lab_id']);
        
        renderView('assets/issue', [
            'asset' => $asset,
            'users' => $users,
            'error' => $error,
            'success' => $success
        ]);
    }
    
    public function returnAsset($assetId = null) {
        Auth::guard();
        
        if (!$assetId) {
            redirect('assets');
        }
        
        $asset = $this->model->getById($assetId);
        if (!$asset) {
            redirect('assets');
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $quantity = floatval($_POST['quantity'] ?? 1);
            $condition = sanitize($_POST['condition'] ?? 'good');
            $notes = sanitize($_POST['notes'] ?? '');
            
            if ($quantity <= 0) {
                $error = 'Quantity must be greater than 0.';
            } else {
                // Return asset
                $block = $this->tracker->returnAsset($assetId, Auth::id(), $asset['lab_id'], $quantity, [
                    'condition' => $condition,
                    'notes' => $notes
                ]);
                
                // Update asset quantity
                $this->model->updateQuantity($assetId, $asset['quantity'] + $quantity);
                
                logActivity(Auth::id(), 'asset_returned', 'assets');
                $success = 'Asset returned successfully! Block #' . $block->index . ' created.';
            }
        }
        
        renderView('assets/return', [
            'asset' => $asset,
            'error' => $error,
            'success' => $success
        ]);
    }
    
    public function transfer($assetId = null) {
        Auth::guard('technician');
        
        if (!$assetId) {
            redirect('assets');
        }
        
        $asset = $this->model->getById($assetId);
        if (!$asset) {
            redirect('assets');
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $targetLab = sanitize($_POST['target_lab_id'] ?? '');
            $quantity = floatval($_POST['quantity'] ?? 1);
            $reason = sanitize($_POST['reason'] ?? '');
            
            if (empty($targetLab) || $quantity <= 0) {
                $error = 'Target lab and quantity are required.';
            } elseif ($quantity > $asset['quantity']) {
                $error = 'Insufficient quantity available.';
            } else {
                // Transfer asset
                $block = $this->tracker->transfer($assetId, $asset['lab_id'], $targetLab, Auth::id(), $quantity, [
                    'reason' => $reason
                ]);
                
                // Update asset lab and quantity
                $this->model->transferAsset($assetId, $targetLab, $quantity);
                
                logActivity(Auth::id(), 'asset_transferred', 'assets');
                $success = 'Asset transferred successfully! Block #' . $block->index . ' created.';
            }
        }
        
        $labs = $this->model->getLabs();
        
        renderView('assets/transfer', [
            'asset' => $asset,
            'labs' => $labs,
            'error' => $error,
            'success' => $success
        ]);
    }
    
    public function blockchain($param = null) {
        Auth::guard();
        
        $stats = $this->tracker->getChainStats();
        $isValid = $this->tracker->validate();
        
        renderView('assets/blockchain', [
            'stats' => $stats,
            'isValid' => $isValid
        ]);
    }
}
