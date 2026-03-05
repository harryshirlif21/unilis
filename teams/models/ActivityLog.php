<?php

// teams/models/ActivityLog.php

/**
 * ActivityLog
 *
 * Lightweight helper around the team_activity_log table.
 * This class is intentionally simple and uses the shared mysqli $conn
 * so it can be reused from APIs, controllers, and other models
 * without introducing new connection logic.
 */
class ActivityLog
{
    /**
     * @var mysqli
     */
    private $conn;

    /**
     * Accept the existing shared mysqli connection.
     * We do not create a new connection here to keep everything
     * consistent with the rest of the app and to avoid leaking resources.
     *
     * @param mysqli $conn
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Log a single activity entry for a team.
     *
     * This method is intentionally narrow:
     *  - It only knows how to insert into team_activity_log.
     *  - It leaves any higher-level validation (e.g. "is this user allowed?")
     *    to the caller (API/controller), which matches how other models behave.
     *
     * @param int    $teamId      Team primary key (teams.id).
     * @param int    $userId      The acting user (usually students.id or lecturers.id).
     * @param string $actionType  Short, machine-friendly identifier (e.g. 'file_upload', 'task_update').
     * @param string $detail      Optional human-readable detail (JSON blob or free text).
     *
     * @return bool  true on success, false on failure.
     */
    public function log($teamId, $userId, $actionType, $detail = '')
    {
        // Basic defensive checks so we fail fast on obviously bad data.
        if (empty($teamId) || empty($userId) || empty($actionType)) {
            return false;
        }

        // We only depend on the minimal, commonly expected columns:
        // team_id, user_id, action_type, action_detail, created_at (TIMESTAMP/DATETIME).
        $sql = "
            INSERT INTO team_activity_log
                (team_id, user_id, action_type, action_detail, created_at)
            VALUES
                (?, ?, ?, ?, NOW())
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            // If prepare() fails we return false; the caller can decide whether
            // to degrade gracefully or surface an error.
            return false;
        }

        // We bind strongly typed parameters to avoid SQL injection and
        // to keep the query plan stable.
        $stmt->bind_param(
            "iiss",
            $teamId,
            $userId,
            $actionType,
            $detail
        );

        $ok = $stmt->execute();

        // Always close the statement so we don't leak resources
        // when this helper is used in high-traffic endpoints.
        $stmt->close();

        return $ok;
    }
}

?>
