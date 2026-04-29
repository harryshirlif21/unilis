<?php
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../utils/helpers.php';
header('Content-Type: application/json');
\ = \['REQUEST_METHOD'];
\  = json_decode(file_get_contents('php://input'), true) ?? [];
// TODO: reports endpoints
jsonResponse(['module' => 'reports', 'status' => 'ok']);
