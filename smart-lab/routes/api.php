<?php
header('Content-Type: application/json');
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../utils/helpers.php';
$endpoint = $_GET['endpoint'] ?? '';
