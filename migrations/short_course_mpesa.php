<?php
/**
 * Short-course M-Pesa payment and department B2B settlement migration.
 *
 * Run once while authenticated as a global administrator.
 */

define('MIGRATION_ACCESS', true);
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$messages = [];

try {
    if (($_SESSION['user_role'] ?? '') !== 'admin'
        || strtolower(trim((string)($_SESSION['user_email'] ?? ''))) !== 'admin@unilis.com') {
        http_response_code(403);
        exit('Forbidden.');
    }
    $columns = [
        'payout_type' => "ALTER TABLE department_admins ADD COLUMN payout_type ENUM('till','paybill') DEFAULT NULL AFTER is_active",
        'payout_shortcode' => "ALTER TABLE department_admins ADD COLUMN payout_shortcode VARCHAR(32) DEFAULT NULL AFTER payout_type",
    ];
    foreach ($columns as $name => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM department_admins LIKE '" . $conn->real_escape_string($name) . "'");
        if (!$check || $check->num_rows === 0) {
            $conn->query($sql);
        }
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS short_course_payments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            learner_id INT NOT NULL,
            course_id INT NOT NULL,
            phone VARCHAR(20) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            course_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            platform_fee DECIMAL(12,2) NOT NULL,
            department_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            merchant_request_id VARCHAR(128) DEFAULT NULL,
            checkout_request_id VARCHAR(128) DEFAULT NULL,
            mpesa_receipt VARCHAR(64) DEFAULT NULL,
            status ENUM('pending','paid','failed','payout_pending','payout_paid','payout_failed') NOT NULL DEFAULT 'pending',
            result_code INT DEFAULT NULL,
            result_description VARCHAR(255) DEFAULT NULL,
            raw_callback JSON DEFAULT NULL,
            payout_reference VARCHAR(128) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_checkout_request (checkout_request_id),
            KEY idx_payment_learner_course (learner_id, course_id),
            KEY idx_payment_status (status),
            CONSTRAINT fk_scp_learner FOREIGN KEY (learner_id) REFERENCES external_learners(id) ON DELETE CASCADE,
            CONSTRAINT fk_scp_course FOREIGN KEY (course_id) REFERENCES public_courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $messages[] = 'Short-course M-Pesa tables and payout fields are ready.';
} catch (Throwable $e) {
    $messages[] = 'Migration error: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<title>Short-course M-Pesa migration</title>
<body style="font-family:Arial,sans-serif;max-width:720px;margin:40px auto;padding:20px">
<?php foreach ($messages as $message): ?>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endforeach; ?>
</body>
</html>
