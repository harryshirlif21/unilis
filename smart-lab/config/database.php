<?php
/**
 * Database bootstrap for local SmartLab requests.
 *
 * The application entry point and several SmartLab endpoints load this file
 * outside the production hostname. Keep the connection implementation in one
 * place so local and container environments use the same DB_* configuration
 * and host fallbacks.
 */
require_once __DIR__ . '/database_production.php';
