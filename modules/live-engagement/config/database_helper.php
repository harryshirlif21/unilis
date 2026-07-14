<?php
/**
 * Live Engagement Module - Database Helper
 * 
 * Database access layer following UNILIS patterns.
 * Provides safe query execution and CRUD operations.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * DatabaseHelper - Database access layer for the Live Engagement Module
 * 
 * Uses the existing UNILIS database connection ($conn from config/db.php)
 * Provides prepared statement helpers and CRUD operations.
 */
class DatabaseHelper
{
    /** @var mysqli Database connection */
    private $conn;

    /** @var DatabaseHelper|null Singleton instance */
    private static $instance = null;

    /**
     * Constructor - uses existing UNILIS connection
     * 
     * @throws Exception If no database connection is available
     */
    public function __construct()
    {
        global $conn;

        if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
            $this->conn = $conn;
        } elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli && !$GLOBALS['conn']->connect_errno) {
            $this->conn = $GLOBALS['conn'];
        } else {
            // Try to load the UNILIS database config
            $dbConfigPath = __DIR__ . '/../../config/db.php';
            if (file_exists($dbConfigPath)) {
                require_once $dbConfigPath;
                if (isset($conn) && $conn instanceof mysqli) {
                    $this->conn = $conn;
                }
            }
            
            if (!isset($this->conn)) {
                throw new Exception('No database connection available for Live Engagement Module');
            }
        }
    }

    /**
     * Get singleton instance
     * 
     * @return DatabaseHelper
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the database connection
     * 
     * @return mysqli
     */
    public function getConnection(): mysqli
    {
        return $this->conn;
    }

    /**
     * Execute a SELECT query with parameters
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameter values
     * @param string $types Parameter type string (i, s, d, b)
     * @return array|null Array of associative rows or null on failure
     */
    public function select(string $sql, array $params = [], string $types = ''): ?array
    {
        $stmt = $this->prepareAndExecute($sql, $params, $types);
        if (!$stmt) {
            return null;
        }

        $result = $stmt->get_result();
        if (!$result) {
            error_log("DatabaseHelper::select - get_result failed: " . $stmt->error);
            $stmt->close();
            return null;
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        $stmt->close();

        return $rows;
    }

    /**
     * Fetch a single row
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameter values
     * @param string $types Parameter type string
     * @return array|null Associative row or null
     */
    public function fetchOne(string $sql, array $params = [], string $types = ''): ?array
    {
        $rows = $this->select($sql, $params, $types);
        return ($rows && count($rows) > 0) ? $rows[0] : null;
    }

    /**
     * Execute an INSERT query and return the inserted ID
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameter values
     * @param string $types Parameter type string
     * @return int|false Insert ID or false on failure
     */
    public function insert(string $sql, array $params = [], string $types = '')
    {
        $stmt = $this->prepareAndExecute($sql, $params, $types);
        if (!$stmt) {
            return false;
        }

        $insertId = $stmt->insert_id;
        $stmt->close();

        return $insertId;
    }

    /**
     * Execute an UPDATE query and return affected rows
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameter values
     * @param string $types Parameter type string
     * @return int|false Number of affected rows or false on failure
     */
    public function update(string $sql, array $params = [], string $types = '')
    {
        $stmt = $this->prepareAndExecute($sql, $params, $types);
        if (!$stmt) {
            return false;
        }

        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        return $affectedRows;
    }

    /**
     * Execute a DELETE query and return affected rows
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameter values
     * @param string $types Parameter type string
     * @return int|false Number of affected rows or false on failure
     */
    public function delete(string $sql, array $params = [], string $types = '')
    {
        return $this->update($sql, $params, $types);
    }

    /**
     * Execute a raw query (for DDL operations)
     * 
     * @param string $sql Raw SQL query
     * @return bool Success
     */
    public function rawQuery(string $sql): bool
    {
        return $this->conn->query($sql) !== false;
    }

    /**
     * Prepare and execute a statement
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameter values
     * @param string $types Parameter type string
     * @return mysqli_stmt|false Prepared statement or false on failure
     */
    private function prepareAndExecute(string $sql, array $params = [], string $types = '')
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("DatabaseHelper::prepare - failed: " . $this->conn->error . " SQL: " . $sql);
            return false;
        }

        if (!empty($params)) {
            if (empty($types)) {
                $types = $this->inferTypes($params);
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            error_log("DatabaseHelper::execute - failed: " . $stmt->error . " SQL: " . $sql);
            $stmt->close();
            return false;
        }

        return $stmt;
    }

    /**
     * Infer parameter types from PHP values
     * 
     * @param array $params Parameter values
     * @return string Type string for bind_param
     */
    private function inferTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 's'; // Default to string for null, bool, etc.
            }
        }
        return $types;
    }

    /**
     * Escape a string for use in SQL (when prepared statements aren't suitable)
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape(string $value): string
    {
        return $this->conn->real_escape_string($value);
    }

    /**
     * Get the last insert ID
     * 
     * @return int|false
     */
    public function lastInsertId()
    {
        return $this->conn->insert_id;
    }

    /**
     * Begin a transaction
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->conn->begin_transaction();
    }

    /**
     * Commit a transaction
     * 
     * @return bool
     */
    public function commit(): bool
    {
        return $this->conn->commit();
    }

    /**
     * Rollback a transaction
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->conn->rollback();
    }

    /**
     * Check if a table exists
     * 
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        $result = $this->conn->query("SHOW TABLES LIKE '" . $this->escape($tableName) . "'");
        return $result && $result->num_rows > 0;
    }

    /**
     * Count rows in a table with optional WHERE clause
     * 
     * @param string $table
     * @param string $where WHERE clause (without 'WHERE')
     * @param array $params Parameters
     * @return int
     */
    public function count(string $table, string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM `{$table}`";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        $result = $this->fetchOne($sql, $params);
        return $result ? (int)$result['cnt'] : 0;
    }
}

/**
 * Global helper function to get the database helper instance
 * 
 * @return DatabaseHelper
 */
function le_db(): DatabaseHelper
{
    return DatabaseHelper::getInstance();
}