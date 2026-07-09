<?php
/**
 * Database configuration for the meeting module
 * Docker-compatible + keeps Database class for executeQuery()
 */

// Environment variables for Docker support
$host = getenv('DB_HOST');
if (!$host) {
    // Auto-detect Docker container
    $host = file_exists('/.dockerenv') ? 'db' : '127.0.0.1';
}

$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: '';
$dbname = getenv('MYSQL_DATABASE') ?: 'university_system';

// Retry logic
$maxRetries = 5;
$retryDelay = 3;

// Singleton connection
$GLOBALS['conn'] = null;

/**
 * Database class used by executeQuery()
 */
class Database {
    public $conn;

    public function __construct() {
        global $host, $user, $password, $dbname, $maxRetries, $retryDelay;

        if ($this->conn) {
            return;
        }

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

                $this->conn = new mysqli($host, $user, $password, $dbname);
                $this->conn->set_charset("utf8mb4");

                // SQL mode cleanup for Docker/MySQL
                $this->conn->query("SET SESSION sql_mode = ''");

                // Set timezone
                $this->conn->query("SET time_zone = '+03:00'");

                // Store globally for legacy code
                $GLOBALS['conn'] = $this->conn;

                break;

            } catch (mysqli_sql_exception $e) {
                error_log("Database connection attempt " . ($i+1) .
                    " failed: " . $e->getMessage());

                if ($i === $maxRetries - 1) {
                    throw new Exception("Database connection failed after retries: " . $e->getMessage());
                }

                sleep($retryDelay);
            }
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

/**
 * Safe query execution using the Database class
 */
function executeQuery($sql, $params = [], $types = "") {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $result = $stmt->execute();

    if (!$result) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    // SELECT queries
    if (stripos(trim($sql), 'SELECT') === 0) {
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // INSERT/UPDATE/DELETE
    $insert_id = $stmt->insert_id;
    $stmt->close();

    return $insert_id ?: $result;
}

/**
 * Sanitization helper
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map("sanitizeInput", $input);
    }

    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Meeting access validation
 */
function validateUserMeetingAccess($user_id, $meeting_id, $role = 'lecturer') {
    $sql = "SELECT m.id, m.lecturer_id, u.role
            FROM meetings m 
            JOIN users u ON u.id = ?
            WHERE m.id = ?";

    $result = executeQuery($sql, [$user_id, $meeting_id], "ii");

    if (empty($result)) {
        return false;
    }

    $meeting = $result[0];

    if ($role === 'lecturer') {
        return $meeting['lecturer_id'] == $user_id && $meeting['role'] === 'lecturer';
    }

    // Student access
    $sql = "SELECT 1 FROM student_unit su
            JOIN meetings m ON m.unit_id = su.unit_id
            WHERE su.student_id = ? AND m.id = ?";

    $result = executeQuery($sql, [$user_id, $meeting_id], "ii");

    return !empty($result);
}

?>
