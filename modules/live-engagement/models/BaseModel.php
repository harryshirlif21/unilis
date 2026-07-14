<?php
/**
 * Live Engagement Module - Base Model
 * 
 * Abstract base class for all models providing common CRUD operations.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

/**
 * BaseModel - Abstract CRUD base for Live Engagement models
 */
abstract class BaseModel
{
    /** @var \DatabaseHelper Database instance */
    protected $db;

    /** @var string Table name */
    protected string $table;

    /** @var string Primary key column */
    protected string $primaryKey = 'id';

    /** @var array Fillable fields for mass assignment */
    protected array $fillable = [];

    /** @var array Field validation rules */
    protected array $rules = [];

    /** @var array Default order */
    protected array $orderBy = ['id' => 'ASC'];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = \DatabaseHelper::getInstance();
    }

    /**
     * Find a record by primary key
     * 
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        return $this->db->fetchOne($sql, [$id], 'i');
    }

    /**
     * Find records by a field value
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $operator Comparison operator
     * @return array
     */
    public function findBy(string $field, $value, string $operator = '='): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$field}` {$operator} ?";
        $rows = $this->db->select($sql, [$value], 's');
        return $rows ?? [];
    }

    /**
     * Get all records
     * 
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function all(?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        
        if (!empty($this->orderBy)) {
            $orderParts = [];
            foreach ($this->orderBy as $field => $direction) {
                $orderParts[] = "`{$field}` {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }
        
        return $this->db->select($sql) ?? [];
    }

    /**
     * Create a new record
     * 
     * @param array $data Field data
     * @return int|false Inserted ID or false
     */
    public function create(array $data)
    {
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            return false;
        }

        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);
        $types = $this->inferTypes($values);

        $sql = "INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})";
        return $this->db->insert($sql, $values, $types);
    }

    /**
     * Update a record
     * 
     * @param int $id Record ID
     * @param array $data Field data
     * @return int|false Affected rows or false
     */
    public function update(int $id, array $data)
    {
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            return false;
        }

        $setClauses = [];
        $values = [];
        $types = '';

        foreach ($data as $column => $value) {
            $setClauses[] = "`{$column}` = ?";
            $values[] = $value;
            $types .= $this->getPhpType($value);
        }

        $values[] = $id;
        $types .= 'i';

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses) . 
               " WHERE `{$this->primaryKey}` = ?";

        return $this->db->update($sql, $values, $types);
    }

    /**
     * Delete a record
     * 
     * @param int $id Record ID
     * @return int|false Affected rows or false
     */
    public function delete(int $id)
    {
        return $this->db->delete(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id],
            'i'
        );
    }

    /**
     * Count records with optional WHERE
     * 
     * @param string|null $where WHERE clause
     * @param array $params Parameters
     * @return int
     */
    public function count(?string $where = null, array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM `{$this->table}`";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result ? (int)$result['cnt'] : 0;
    }

    /**
     * Check if a record exists
     * 
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        $result = $this->db->fetchOne(
            "SELECT 1 FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1",
            [$id],
            'i'
        );
        return $result !== null;
    }

    /**
     * Get paginated results
     * 
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param string|null $where WHERE clause
     * @param array $params Parameters
     * @return array{data: array, total: int, page: int, perPage: int, pages: int}
     */
    public function paginate(int $page = 1, int $perPage = 20, ?string $where = null, array $params = []): array
    {
        $total = $this->count($where, $params);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM `{$this->table}`";
        
        if ($where) {
            $sql .= " WHERE {$where}";
        }

        if (!empty($this->orderBy)) {
            $orderParts = [];
            foreach ($this->orderBy as $field => $direction) {
                $orderParts[] = "`{$field}` {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }

        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        return [
            'data' => $this->db->select($sql, $params) ?? [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * Filter data to only fillable fields
     * 
     * @param array $data
     * @return array
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Get PHP type character for prepared statement
     * 
     * @param mixed $value
     * @return string
     */
    protected function getPhpType($value): string
    {
        if (is_int($value)) return 'i';
        if (is_float($value)) return 'd';
        if (is_null($value)) return 's';
        return 's';
    }

    /**
     * Infer types for an array of values
     * 
     * @param array $values
     * @return string
     */
    protected function inferTypes(array $values): string
    {
        $types = '';
        foreach ($values as $value) {
            $types .= $this->getPhpType($value);
        }
        return $types;
    }

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): void
    {
        $this->db->rollback();
    }
}