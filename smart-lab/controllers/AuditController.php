<?php
require_once __DIR__.'/../models/AuditModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class AuditController {
    private AuditModel $model;
    
    public function __construct() {
        $this->model = new AuditModel();
    }
    public function index($param = null) {
        renderView('audit/index', []);
    }
}
