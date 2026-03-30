<?php
/**
 * Input Validation Helper Functions
 * Provides secure input validation and sanitization
 */

/**
 * Validate and sanitize integer input
 */
function validate_int($input, $min = 0, $max = PHP_INT_MAX) {
    if (!is_numeric($input)) {
        return false;
    }
    
    $value = (int) $input;
    return ($value >= $min && $value <= $max) ? $value : false;
}

/**
 * Validate and sanitize string input
 */
function validate_string($input, $max_length = 1000, $allow_empty = false) {
    if (!is_string($input)) {
        return false;
    }
    
    $trimmed = trim($input);
    if (!$allow_empty && empty($trimmed)) {
        return false;
    }
    
    if (strlen($trimmed) > $max_length) {
        return false;
    }
    
    return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validate_email($email) {
    if (!is_string($email)) {
        return false;
    }
    
    $email = trim($email);
    if (empty($email)) {
        return false;
    }
    
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate JSON input
 */
function validate_json($json_string) {
    if (!is_string($json_string)) {
        return false;
    }
    
    $decoded = json_decode($json_string, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : false;
}

/**
 * Validate file upload
 */
function validate_file_upload($file_key, $allowed_types = ['pdf', 'doc', 'docx', 'txt'], $max_size = 5242880) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $file = $_FILES[$file_key];
    
    // Check file size
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // Check file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        return false;
    }
    
    // Check if it's actually a file
    if (!is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    return $file;
}

/**
 * Sanitize array of inputs
 */
function sanitize_array($array, $rules = []) {
    $sanitized = [];
    
    foreach ($array as $key => $value) {
        $rule = $rules[$key] ?? ['type' => 'string', 'max_length' => 1000];
        
        switch ($rule['type']) {
            case 'int':
                $sanitized[$key] = validate_int($value, $rule['min'] ?? 0, $rule['max'] ?? PHP_INT_MAX);
                break;
            case 'email':
                $sanitized[$key] = validate_email($value) ? $value : false;
                break;
            case 'string':
                $sanitized[$key] = validate_string($value, $rule['max_length'] ?? 1000, $rule['allow_empty'] ?? false);
                break;
            case 'json':
                $sanitized[$key] = validate_json($value);
                break;
            default:
                $sanitized[$key] = false;
        }
        
        // Remove invalid values
        if ($sanitized[$key] === false) {
            unset($sanitized[$key]);
        }
    }
    
    return $sanitized;
}
?>
