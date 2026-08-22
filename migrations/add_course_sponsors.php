<?php
/**
 * Migration: Add course_sponsors table for multiple sponsors per course
 * This allows short courses to have multiple sponsors instead of just one
 */

define('MIGRATION_ACCESS', true);
require_once __DIR__ . '/../config/db.php';

$message = '';
$messageType = '';

try {
    // Apply sponsor columns to public_courses if missing (idempotent).
    $alter = "ALTER TABLE public_courses
        ADD COLUMN is_sponsored     TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_methods,
        ADD COLUMN sponsor_name     VARCHAR(255) DEFAULT NULL AFTER is_sponsored,
        ADD COLUMN sponsor_details  TEXT AFTER sponsor_name,
        ADD COLUMN sponsor_logo     VARCHAR(500) DEFAULT NULL AFTER sponsor_name";

    $applyColumns = false;
    $cols = ['is_sponsored', 'sponsor_name', 'sponsor_details', 'sponsor_logo'];
    foreach ($cols as $col) {
        $check = $conn->query("SHOW COLUMNS FROM public_courses LIKE '$col'");
        if (!$check || $check->num_rows === 0) {
            $applyColumns = true;
            break;
        }
    }

    if ($applyColumns) {
        $conn->multi_query($alter);
        $conn->query("SELECT 1"); // flush multi_query results
    }

    // Ensure course_sponsors table exists.
    $checkTable = $conn->query("SHOW TABLES LIKE 'course_sponsors'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        $conn->query("CREATE TABLE course_sponsors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            sponsor_name VARCHAR(255) NOT NULL,
            sponsor_details TEXT,
            sponsor_logo VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES public_courses(id) ON DELETE CASCADE
        )");
    }

    $message = "Sponsor columns and course_sponsors table are present in the database.";
    $messageType = 'success';
} catch (Exception $e) {
    $message = "Migration error: " . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - Add Course Sponsors</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        h1 { color: #333; }
        a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        a:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Migration: Add Course Sponsors Table</h1>
    
    <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= nl2br(htmlspecialchars($message)) ?>
        </div>
    <?php endif; ?>
    
    <a href="../phase1/admin/department_admins.php">Return to Department Admin Dashboard</a>
</body>
</html>
