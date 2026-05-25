<?php
/**
 * Ensures late-submission columns exist on assignments and submissions.
 */
function ensure_assignment_submission_schema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $col = $conn->query("SHOW COLUMNS FROM submissions LIKE 'is_late'");
    if ($col && $col->num_rows === 0) {
        if (!$conn->query("ALTER TABLE submissions ADD COLUMN is_late TINYINT(1) NOT NULL DEFAULT 0 AFTER submitted_at")) {
            throw new RuntimeException('Failed to add submissions.is_late: ' . $conn->error);
        }
    }

    $col = $conn->query("SHOW COLUMNS FROM assignments LIKE 'allow_late_submission'");
    if ($col && $col->num_rows === 0) {
        if (!$conn->query("ALTER TABLE assignments ADD COLUMN allow_late_submission TINYINT(1) NOT NULL DEFAULT 1 AFTER deadline")) {
            throw new RuntimeException('Failed to add assignments.allow_late_submission: ' . $conn->error);
        }
    }

    $ensured = true;
}
