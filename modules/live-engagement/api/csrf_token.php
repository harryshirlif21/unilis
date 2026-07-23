<?php
/**
 * Live Engagement Module - CSRF Token API
 * 
 * Returns the current CSRF token for AJAX requests.
 * This keeps the token out of the HTML source for security.
 * 
 * @package UNILIS\LiveEngagement\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Require authentication
le_require_auth();

echo json_encode([
    'token' => le_csrf_token()
]);
