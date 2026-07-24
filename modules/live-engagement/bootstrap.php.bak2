<?php
/**
 * Live Engagement Module - Bootstrap
 * 
 * Initializes the module, loads configuration, and sets up autoloading.
 * Include this file at the top of any Live Engagement page.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS')) {
    define('UNILIS_ACCESS', true);
}

// Module base path
define('LE_MODULE_PATH', __DIR__);
define('LE_MODULE_URL', 'modules/live-engagement');

// Load root database configuration first
try {
    require_once __DIR__ . '/../../config/db.php';
} catch (Throwable $e) {
    error_log(sprintf(
        'Live Engagement: Failed to load database config: %s | URI=%s | ENV DB_HOST=%s DB_USER=%s DB_NAME=%s',
        $e->getMessage(),
        $_SERVER['REQUEST_URI'] ?? 'unknown',
        getenv('DB_HOST') ?: 'unset',
        getenv('MYSQL_USER') ?: 'unset',
        getenv('MYSQL_DATABASE') ?: 'unset'
    ));
    error_log($e->getTraceAsString());

    http_response_code(500);
    echo '<h1>Live Engagement database connection failed</h1>';
    echo '<p>The module cannot connect to the database. Verify your Docker environment variables and UNILIS config.</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '<p>DB_HOST=' . htmlspecialchars(getenv('DB_HOST') ?: 'unset', ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>MYSQL_USER=' . htmlspecialchars(getenv('MYSQL_USER') ?: 'unset', ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>MYSQL_DATABASE=' . htmlspecialchars(getenv('MYSQL_DATABASE') ?: 'unset', ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

// Load configuration
$leConfig = require __DIR__ . '/config/module.php';

// Load database helper
require_once __DIR__ . '/config/database_helper.php';

// Load helpers
require_once __DIR__ . '/helpers/security_helper.php';
require_once __DIR__ . '/helpers/session_helper.php';

// Load CSRF token (session may already be started by root app)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize CSRF token
if (!isset($_SESSION[LE_CSRF_TOKEN_NAME])) {
    $_SESSION[LE_CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

/**
 * Simple autoloader for Live Engagement classes
 * 
 * Maps namespaces to directories:
 *   LE\Models\*     -> models/
 *   LE\Services\*   -> services/
 *   LE\Controllers\* -> controllers/
 *   LE\Helpers\*    -> helpers/
 *   LE\Components\* -> components/
 */
spl_autoload_register(function (string $class) {
    $namespaceMap = [
        'LE\\Models\\' => __DIR__ . '/models/',
        'LE\\Services\\' => __DIR__ . '/services/',
        'LE\\Controllers\\' => __DIR__ . '/controllers/',
        'LE\\Helpers\\' => __DIR__ . '/helpers/',
        'LE\\Components\\' => __DIR__ . '/components/',
    ];

    foreach ($namespaceMap as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        } else {
            error_log('Live Engagement autoloader failed to find file: ' . $file . ' for class: ' . $class);
        }
        return;
    }
}, true, true);

/**
 * Check if the current user is authenticated
 * 
 * @return bool
 */
function le_is_authenticated(): bool
{
    // Check for UNILIS authentication or Live Engagement authentication
    return (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ||
           (isset($_SESSION['le_authenticated']) && $_SESSION['le_authenticated'] === true);
}

/**
 * Get current user ID
 * 
 * @return int|null
 */
function le_current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * 
 * @return string|null
 */
function le_current_user_role(): ?string
{
    $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? $_SESSION['le_user_role'] ?? null;
    if (!is_string($role) || $role === '') {
        return null;
    }
    return strtolower(trim($role));
}

/**
 * Get current user name
 * 
 * @return string|null
 */
function le_current_user_name(): ?string
{
    return $_SESSION['user_name'] ?? $_SESSION['name'] ?? null;
}

/**
 * Get current user email
 * 
 * @return string|null
 */
function le_current_user_email(): ?string
{
    return $_SESSION['email'] ?? null;
}

/**
 * Require authentication - redirects if not logged in
 * 
 * @param string $redirectUrl URL to redirect to
 */
function le_base_url(): string
{
    static $baseUrl = null;

    if ($baseUrl !== null) {
        return $baseUrl;
    }

    if (!isset($_SERVER['HTTP_HOST'])) {
        $baseUrl = '/unilis';
        return $baseUrl;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/unilis/modules/live-engagement/index.php');

    // Determine the application root by trimming the module path from SCRIPT_NAME.
    // SCRIPT_NAME examples:
    //   /unilis/modules/live-engagement/index.php  → app root: /unilis
    //   /modules/live-engagement/index.php         → app root: '' (web root)
    $modulePath = '/modules/live-engagement';
    $pos = strpos($scriptName, $modulePath);
    if ($pos !== false) {
        $appRoot = substr($scriptName, 0, $pos);
    } else {
        // Fallback: walk up from the script dir
        $moduleDir = dirname($scriptName);
        $appRoot = dirname(dirname($moduleDir));
    }

    $appRoot = rtrim($appRoot, '/');
    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $appRoot;
    return $baseUrl;
}

/**
 * Get a URL within this module
 *
 * @param string $path Path relative to the module root
 */
function le_module_url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = le_base_url() . '/' . LE_MODULE_URL;

    return $path === '' ? $base : $base . '/' . $path;
}

/**
 * Build a router URL for this module
 *
 * @param string $page Page key for index.php routing
 * @param array<string, scalar|null> $params
 */
function le_page_url(string $page = 'dashboard', array $params = []): string
{
    $params['page'] = $page;

    return le_module_url('index.php') . '?' . http_build_query($params);
}

function le_require_auth(?string $redirectUrl = null): void
{
    if (!le_is_authenticated()) {
        if ($redirectUrl === null) {
            $returnTo = $_SERVER['REQUEST_URI'] ?? le_page_url();
            $redirectUrl = le_base_url() . '/login.php?redirect=' . rawurlencode($returnTo);
        }

        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Check if user has a specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool
 */
function le_has_role($roles): bool
{
    if (!le_is_authenticated()) {
        return false;
    }

    $userRole = le_current_user_role();
    if ($userRole === null) {
        return false;
    }
    
    if (is_array($roles)) {
        $normalizedRoles = array_map(static fn($r) => is_string($r) ? strtolower(trim($r)) : $r, $roles);
        return in_array($userRole, $normalizedRoles, true);
    }

    return is_string($roles) && $userRole === strtolower(trim($roles));
}

/**
 * Generate a unique session code
 * 
 * @param int $length Code length
 * @return string
 */
function le_generate_session_code(int $length = 8): string
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    $maxIndex = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, $maxIndex)];
    }
    
    // Ensure uniqueness in database
    $db = le_db();
    $exists = $db->fetchOne(
        "SELECT 1 FROM live_sessions WHERE session_code = ? LIMIT 1",
        [$code]
    );
    
    if ($exists) {
        return le_generate_session_code($length);
    }
    
    return $code;
}

/**
 * Format time duration for display
 * 
 * @param int $seconds Duration in seconds
 * @return string Formatted duration
 */
function le_format_duration(int $seconds): string
{
    if ($seconds < 60) {
        return "{$seconds}s";
    }
    
    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;
    
    if ($minutes < 60) {
        return $remainingSeconds > 0 
            ? "{$minutes}m {$remainingSeconds}s" 
            : "{$minutes}m";
    }
    
    $hours = floor($minutes / 60);
    $remainingMinutes = $minutes % 60;
    
    return $remainingMinutes > 0 
        ? "{$hours}h {$remainingMinutes}m" 
        : "{$hours}h";
}

/**
 * Get module asset URL
 * 
 * @param string $path Asset path relative to assets folder
 * @return string Full URL
 */
function le_asset_url(string $path): string
{
    return le_module_url('assets/' . ltrim($path, '/'));
}

/**
 * Get module config value with dot notation
 * 
 * @param string $key Config key (e.g., 'module.name')
 * @param mixed $default Default value
 * @return mixed
 */
function le_config(string $key, $default = null)
{
    static $config = null;
    
    if ($config === null) {
        $config = require __DIR__ . '/config/module.php';
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    
    return $value;
}

/**
 * Get module version
 * 
 * @return string
 */
function le_version(): string
{
    return le_config('module.version', '1.0.0');
}

/**
 * Check if a database table exists
 * 
 * @param string $tableName
 * @return bool
 */
function le_table_exists(string $tableName): bool
{
    try {
        return le_db()->tableExists($tableName);
    } catch (Exception $e) {
        error_log("le_table_exists check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Run database installation if needed
 * 
 * @return array{success: bool, messages: string[]}
 */
function le_install_database(): array
{
    require_once __DIR__ . '/database/install.php';
    return installLiveEngagementTables(le_db()->getConnection());
}

/**
 * Run database updates if needed
 * 
 * @return array{success: bool, messages: string[]}
 */
function le_update_database(): array
{
    require_once __DIR__ . '/database/update.php';
    return updateLiveEngagementTables(le_db()->getConnection());
}

/**
 * Format a date/timestamp as a relative time string ("2h ago", "just now", etc.)
 * 
 * @param string|int|null $time A date string, Unix timestamp, or null
 * @return string Relative time string
 */
function le_time_ago($time): string
{
    if ($time === null || $time === '') {
        return 'N/A';
    }

    // Convert to Unix timestamp if it's a date string
    $timestamp = is_numeric($time) ? (int)$time : strtotime((string)$time);
    if ($timestamp === false) {
        return 'N/A';
    }

    $seconds = time() - $timestamp;

    if ($seconds < 0) {
        $seconds = abs($seconds);
        if ($seconds < 60) return 'just now';
        if ($seconds < 3600) return ceil($seconds / 60) . 'm';
        if ($seconds < 86400) return ceil($seconds / 3600) . 'h';
        return ceil($seconds / 86400) . 'd';
    }

    if ($seconds < 60) return 'just now';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ago';
    if ($seconds < 86400) return floor($seconds / 3600) . 'h ago';
    if ($seconds < 2592000) return floor($seconds / 86400) . 'd ago';

    return date('M j, Y', $timestamp);
}
