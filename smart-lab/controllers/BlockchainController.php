<?php
require_once __DIR__.'/../models/BlockchainModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class BlockchainController {
    private BlockchainModel $model;
    
    public function __construct() {
        $this->model = new BlockchainModel();
    }
    public function index($param = null) {
        renderView('blockchain/index', []);
    }
}
