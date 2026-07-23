<?php
/**
 * Live Engagement Module - Security Helper
 * 
 * Provides CSRF protection, input sanitization, XSS prevention,
 * and request validation utilities.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS')) {
    die('Direct access not permitted');
}

// CSRF token name constant
define('LE_CSRF_TOKEN_NAME', 'le_csrf_token');

/**
 * Generate a CSRF token for forms
 * 
 * @return string
 */
function le_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION[LE_CSRF_TOKEN_NAME])) {
        $_SESSION[LE_CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION[LE_CSRF_TOKEN_NAME];
}

/**
 * Generate a CSRF hidden input field
 * 
 * @return string HTML input field
 */
function le_csrf_field(): string
{
    // Return empty string - token should be sent via headers only for security
    return '';
}

/**
 * Generate a CSRF meta tag for AJAX requests
 * 
 * @return string HTML meta tag
 */
function le_csrf_meta(): string
{
    // Return empty string - token should be retrieved via JavaScript for security
    return '';
}

/**
 * Validate a CSRF token
 * 
 * @param string|null $token Token to validate (uses POST/header if null)
 * @return bool
 */
function le_validate_csrf(?string $token = null): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $storedToken = $_SESSION[LE_CSRF_TOKEN_NAME] ?? '';
    
    if (empty($storedToken)) {
        return false;
    }
    
    // If no token provided, check POST or header
    if ($token === null) {
        $token = $_POST[LE_CSRF_TOKEN_NAME] 
            ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
            ?? $_SERVER['HTTP_X_XSRF_TOKEN'] 
            ?? '';
    }
    
    return hash_equals($storedToken, $token);
}

/**
 * Require a valid CSRF token - dies with 403 if invalid
 */
function le_require_csrf(): void
{
    if (!le_validate_csrf()) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

/**
 * Sanitize a string for HTML output (XSS prevention)
 * 
 * @param string $value Value to sanitize
 * @return string Sanitized value
 */
function le_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
}

/**
 * Sanitize an array of strings for HTML output
 * 
 * @param array $values Values to sanitize
 * @return array
 */
function le_escape_array(array $values): array
{
    $result = [];
    foreach ($values as $key => $value) {
        if (is_array($value)) {
            $result[$key] = le_escape_array($value);
        } elseif (is_string($value)) {
            $result[$key] = le_escape($value);
        } else {
            $result[$key] = $value;
        }
    }
    return $result;
}

/**
 * Sanitize input data - trim and strip tags
 * 
 * @param string $input Raw input
 * @return string Sanitized input
 */
function le_sanitize_input(string $input): string
{
    return trim(strip_tags($input));
}

/**
 * Sanitize an array of inputs
 * 
 * @param array $inputs Associative array of inputs
 * @param array $fields Fields to sanitize (null for all)
 * @return array
 */
function le_sanitize_array(array $inputs, ?array $fields = null): array
{
    $result = [];
    $targets = $fields ?? array_keys($inputs);
    
    foreach ($targets as $field) {
        if (isset($inputs[$field])) {
            if (is_string($inputs[$field])) {
                $result[$field] = le_sanitize_input($inputs[$field]);
            } elseif (is_array($inputs[$field])) {
                $result[$field] = le_sanitize_array($inputs[$field]);
            } else {
                $result[$field] = $inputs[$field];
            }
        }
    }
    
    return $result;
}

/**
 * Validate an email address
 * 
 * @param string $email
 * @return bool
 */
function le_validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate a URL
 * 
 * @param string $url
 * @return bool
 */
function le_validate_url(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate an integer within a range
 * 
 * @param mixed $value Value to check
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @return bool
 */
function le_validate_int_range($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool
{
    if (!is_numeric($value)) {
        return false;
    }
    $intVal = (int)$value;
    return $intVal >= $min && $intVal <= $max;
}

/**
 * Validate that a value is one of the allowed values
 * 
 * @param mixed $value Value to check
 * @param array $allowed Allowed values
 * @return bool
 */
function le_validate_in_enum($value, array $allowed): bool
{
    return in_array($value, $allowed, true);
}

/**
 * Get and validate a JSON request body
 * 
 * @return array|null Decoded JSON or null on failure
 */
function le_get_json_input(): ?array
{
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return null;
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    
    return $data;
}

/**
 * Get a sanitized GET parameter
 * 
 * @param string $key Parameter name
 * @param mixed $default Default value
 * @param bool $allowHtml Allow HTML tags
 * @return mixed
 */
function le_get(string $key, $default = null, bool $allowHtml = false)
{
    $value = $_GET[$key] ?? $default;
    if (is_string($value) && !$allowHtml) {
        $value = le_sanitize_input($value);
    }
    return $value;
}

/**
 * Get a sanitized POST parameter
 * 
 * @param string $key Parameter name
 * @param mixed $default Default value
 * @param bool $allowHtml Allow HTML tags
 * @return mixed
 */
function le_post(string $key, $default = null, bool $allowHtml = false)
{
    $value = $_POST[$key] ?? $default;
    if (is_string($value) && !$allowHtml) {
        $value = le_sanitize_input($value);
    }
    return $value;
}

/**
 * Send a JSON response
 * 
 * @param mixed $data Data to encode
 * @param int $statusCode HTTP status code
 * @param array $headers Additional headers
 */
function le_json_response($data, int $statusCode = 200, array $headers = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    
    foreach ($headers as $name => $value) {
        header("{$name}: {$value}");
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send an error JSON response
 * 
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 * @param array $extra Extra fields
 */
function le_error_response(string $message, int $statusCode = 400, array $extra = []): void
{
    le_json_response(array_merge(['error' => $message], $extra), $statusCode);
}

/**
 * Send a success JSON response
 * 
 * @param mixed $data Response data
 * @param string $message Success message
 */
function le_success_response($data = null, string $message = 'Success'): void
{
    le_json_response([
        'success' => true,
        'message' => $message,
        'data' => $data,
    ]);
}

/**
 * Validate file upload
 * 
 * @param array $file $_FILES array element
 * @param array $allowedTypes Allowed MIME types
 * @param int $maxSize Max file size in bytes
 * @return string|null Error message or null on success
 */
function le_validate_upload(array $file, array $allowedTypes = [], int $maxSize = 52428800): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        ];
        return $errors[$file['error']] ?? 'Unknown upload error';
    }
    
    if ($file['size'] > $maxSize) {
        return 'File exceeds maximum size of ' . ($maxSize / 1048576) . 'MB';
    }
    
    if (!empty($allowedTypes)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes, true)) {
            return 'File type not allowed: ' . $mimeType;
        }
    }
    
    return null;
}

/**
 * Rate limiting check
 * 
 * @param string $action Action identifier
 * @param int $maxAttempts Max attempts in window
 * @param int $windowSeconds Time window in seconds
 * @return bool True if allowed
 */
function le_check_rate_limit(string $action, int $maxAttempts = 30, int $windowSeconds = 60): bool
{
    $key = 'le_rate_limit_' . md5($action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $now = time();
    $window = $_SESSION[$key] ?? ['count' => 0, 'start' => $now];
    
    // Reset window if expired
    if ($now - $window['start'] > $windowSeconds) {
        $window = ['count' => 0, 'start' => $now];
    }
    
    $window['count']++;
    $_SESSION[$key] = $window;
    
    return $window['count'] <= $maxAttempts;
}

/**
 * Hash a value for non-reversible storage
 * 
 * @param string $value Value to hash
 * @return string Hash
 */
function le_hash(string $value): string
{
    return hash('sha256', $value . ($_SERVER['REMOTE_ADDR'] ?? ''));
}