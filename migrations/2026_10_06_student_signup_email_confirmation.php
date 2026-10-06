<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'department_admin'], true)) {
    http_response_code(403);
    exit('Unauthorized.');
}

header('Content-Type: text/plain; charset=utf-8');

if (!$conn->query("
    CREATE TABLE IF NOT EXISTS student_signup_email_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        used_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uq_student_signup_email (email),
        UNIQUE KEY uq_student_signup_token_hash (token_hash),
        KEY idx_student_signup_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
")) {
    http_response_code(500);
    throw new RuntimeException('Unable to create student_signup_email_tokens: ' . $conn->error);
}

echo "OK: student_signup_email_tokens is ready.\n";
