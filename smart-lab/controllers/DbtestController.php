<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../utils/helpers.php';

class DbtestController {
    public function index($param = null) {
        renderView('auth/dbtest', []);
    }
}
