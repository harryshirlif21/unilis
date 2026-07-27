<?php
/**
 * Shared preamble for every chat API endpoint.
 *
 * Establishes the JSON contract used across the module:
 *   { "success": true,  ...payload }
 *   { "success": false, "error": "..." }
 *
 * After including this file, $conn and $chatUser are available and the caller
 * is known to be a signed-in student or lecturer whose chat tables exist.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

$chatUser = chat_require_user();

if (!chat_schema_ready($conn)) {
    chat_json([
        'success' => false,
        'error' => 'Chat is not set up yet. An administrator needs to run migrate_chat_system.php.',
        'code' => 'schema_missing',
    ], 503);
}
