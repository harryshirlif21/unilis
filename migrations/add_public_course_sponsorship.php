<?php
/**
 * Migration: Add sponsorship columns to public_courses
 *
 * Adds the sponsorship flag, sponsor identity fields and the sponsorship
 * period dates used by the short course screens. Every column is added only
 * when missing, so the script is safe to run more than once.
 */

session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "Forbidden: only an admin may run database migrations.\n";
    exit;
}

if (!isset($conn) || !$conn instanceof mysqli) {
    http_response_code(500);
    echo "Database connection not available.\n";
    exit;
}

$columns = [
    'is_sponsored' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_methods",
    'sponsor_name' => "VARCHAR(255) DEFAULT NULL AFTER is_sponsored",
    'sponsor_details' => "TEXT NULL AFTER sponsor_name",
    'sponsor_logo' => "VARCHAR(500) DEFAULT NULL AFTER sponsor_details",
    'sponsorship_start_date' => "DATE DEFAULT NULL AFTER sponsor_logo",
    'sponsorship_end_date' => "DATE DEFAULT NULL AFTER sponsorship_start_date",
];

echo "Migration: add sponsorship columns to public_courses\n";
echo str_repeat('-', 52) . "\n";

$table = $conn->query("SHOW TABLES LIKE 'public_courses'");
if (!$table || $table->num_rows === 0) {
    http_response_code(500);
    echo "Required table public_courses does not exist.\n";
    exit;
}

$hasError = false;

foreach ($columns as $column => $definition) {
    $existing = $conn->query("SHOW COLUMNS FROM `public_courses` LIKE '{$column}'");
    if ($existing && $existing->num_rows > 0) {
        echo "SKIP: public_courses.{$column} already exists\n";
        continue;
    }

    // A missing anchor column (older installs) makes the AFTER clause fail, so
    // fall back to appending the column at the end of the table.
    if ($conn->query("ALTER TABLE `public_courses` ADD COLUMN `{$column}` {$definition}")) {
        echo "ADDED: public_courses.{$column}\n";
        continue;
    }

    $withoutAnchor = preg_replace('/\s+AFTER\s+\S+$/i', '', $definition);
    if ($conn->query("ALTER TABLE `public_courses` ADD COLUMN `{$column}` {$withoutAnchor}")) {
        echo "ADDED: public_courses.{$column} (appended at end of table)\n";
        continue;
    }

    $hasError = true;
    echo "ERROR: failed to add public_courses.{$column}: " . $conn->error . "\n";
}

echo str_repeat('-', 52) . "\n";
echo $hasError ? "Migration finished with errors.\n" : "Migration completed successfully.\n";

if ($hasError) {
    http_response_code(500);
}
