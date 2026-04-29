<?php
require_once __DIR__.'/../models/InventoryModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class InventoryController {
    private InventoryModel $model;
    
    public function __construct() {
        $this->model = new InventoryModel();
    }
    
    public function index($param = null) {
        Auth::guard();
        
        $inventory = $this->model->getInventory();
        $stats = $this->model->getInventoryStats();
        $labs = $this->model->getLabs();
        
        renderView('inventory/index', [
            'inventory' => $inventory,
            'stats' => $stats,
            'labs' => $labs
        ]);
    }
}
