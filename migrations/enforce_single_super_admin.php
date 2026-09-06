<?php
/**
 * Ensures admin@unilis.com is the only super administrator.
 * Run once from the global admin dashboard migration panel.
 */

define('MIGRATION_ACCESS', true);
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
try {
    if (($_SESSION['user_role'] ?? '') !== 'admin'
        || strtolower(trim((string)($_SESSION['user_email'] ?? ''))) !== 'admin@unilis.com') {
        http_response_code(403);
        exit('Forbidden.');
    }
    $column = $conn->query("SHOW COLUMNS FROM admins LIKE 'is_super_admin'");
    if (!$column || $column->num_rows === 0) {
        $conn->query("ALTER TABLE admins ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified");
    }

    $stmt = $conn->prepare("
        UPDATE admins
        SET is_super_admin = CASE WHEN LOWER(TRIM(email)) = 'admin@unilis.com' THEN 1 ELSE 0 END
    ");
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $check = $conn->prepare("SELECT id FROM admins WHERE LOWER(TRIM(email)) = 'admin@unilis.com' LIMIT 1");
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    $message = $exists
        ? "Single super-admin policy applied. Updated rows: {$affected}."
        : 'Policy applied, but admin@unilis.com does not yet exist. Create that account before testing.';
} catch (Throwable $e) {
    $message = 'Migration error: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<title>Single super admin migration</title>
<body style="font-family:Arial,sans-serif;max-width:720px;margin:40px auto;padding:20px">
<p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
</body>
</html>
