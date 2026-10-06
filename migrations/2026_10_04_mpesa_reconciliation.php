<?php
/**
 * Short-course M-Pesa reconciliation migration.
 *
 * Extends the short_course_payments.status enum with the states Daraja callbacks
 * and an administrator's reconciliation pass actually produce:
 *
 *   - cancelled                 the payer declined / cancelled the prompt
 *   - timeout                   the prompt expired or the callback was never seen
 *   - reconciliation_required   the callback was inconsistent (e.g. amount
 *                               mismatch) or missing and an operator must decide
 *
 * It is safe to run more than once: every statement checks whether its change
 * already exists before altering anything. Run while authenticated as the global
 * administrator (the same guard the original M-Pesa migration used).
 */

if (!defined('MIGRATION_ACCESS')) {
    define('MIGRATION_ACCESS', true);
}
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$messages = [];

function migration_table_exists(mysqli $conn, string $table): bool
{
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    return $exists;
}

function migration_enum_has(mysqli $conn, string $table, string $column, string $value): bool
{
    $result = $conn->query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $conn->real_escape_string($column) . "'");
    if (!$result || $result->num_rows === 0) {
        return false;
    }
    $row = $result->fetch_assoc();
    $result->free();
    $type = strtolower((string)($row['Type'] ?? ''));
    return strpos($type, "'" . strtolower($value) . "'") !== false;
}

try {
    if (($_SESSION['user_role'] ?? '') !== 'admin'
        || strtolower(trim((string)($_SESSION['user_email'] ?? ''))) !== 'admin@unilis.com') {
        http_response_code(403);
        exit('Forbidden.');
    }

    if (!migration_table_exists($conn, 'short_course_payments')) {
        $messages[] = 'short_course_payments table not found - run short_course_mpesa.php first.';
    } else {
        $neededStates = ['pending', 'paid', 'failed', 'payout_pending', 'payout_paid', 'payout_failed'];
        $newStates = ['cancelled', 'timeout', 'reconciliation_required'];
        if (!migration_enum_has($conn, 'short_course_payments', 'status', 'reconciliation_required')) {
            $enum = "'" . implode("','", array_merge($neededStates, $newStates)) . "'";
            $conn->query("ALTER TABLE short_course_payments
                MODIFY COLUMN status ENUM(" . $enum . ") NOT NULL DEFAULT 'pending'");
            $messages[] = 'Extended status enum with cancelled / timeout / reconciliation_required.';
        } else {
            $messages[] = 'Status enum already extended.';
        }

        // Reconciliation usually targets stale pending rows, so help the query.
        $indexCheck = $conn->query("SHOW INDEX FROM short_course_payments");
        $hasReconcileIndex = false;
        if ($indexCheck) {
            while ($row = $indexCheck->fetch_assoc()) {
                if (in_array((string)($row['Column_name'] ?? ''), ['status', 'created_at'], true)) {
                    $hasReconcileIndex = true;
                    break;
                }
            }
            $indexCheck->free();
        }
        if (!$hasReconcileIndex) {
            mysqli_report(MYSQLI_REPORT_ERROR); // tolerate a duplicate-key race
            $conn->query("ALTER TABLE short_course_payments ADD KEY idx_payment_status_created (status, created_at)");
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }

        $messages[] = 'M-Pesa reconciliation fields are ready.';
    }
} catch (Throwable $e) {
    $messages[] = 'Migration error: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<title>M-Pesa reconciliation migration</title>
<body style="font-family:Arial,sans-serif;max-width:720px;margin:40px auto;padding:20px">
<?php foreach ($messages as $message): ?>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endforeach; ?>
</body>
</html>