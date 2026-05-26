<?php

declare(strict_types=1);

/**
 * Class Database
 *
 * A simple database abstraction layer on top of PDO.
 * Supports CRUD operations, raw queries, and transactions.
 * Uses prepared statements to prevent SQL injection.
 */

namespace Fastpress\Memory;

class Database
{
    private ?\PDO $connection = null;
    private array $config;
    private array $queries = [];

    /**
     * Constructor.
     *
     * @param array $config Configuration array with keys: host, port, database, charset, username, password
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Quotes an identifier (table or column name) to prevent issues with reserved keywords.
     *
     * @param string $identifier The identifier to quote.
     * @return string The quoted identifier.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Sanitizes an ORDER BY clause to prevent SQL injection.
     * Only allows column names (with optional table prefix) and ASC/DESC direction.
     *
     * @param string $orderBy The ORDER BY string (e.g. 'name ASC', 'id DESC').
     * @return string The sanitized ORDER BY clause.
     * @throws \InvalidArgumentException If the ORDER BY clause contains invalid characters.
     */
    private function sanitizeOrderBy(string $orderBy): string
    {
        $parts = array_map('trim', explode(',', $orderBy));
        $sanitized = [];

        foreach ($parts as $part) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*(\s+(ASC|DESC))?$/i', $part)) {
                throw new \InvalidArgumentException("Invalid ORDER BY clause: {$part}");
            }
            $sanitized[] = $part;
        }

        return implode(', ', $sanitized);
    }

    /**
     * Select a single row from a table.
     *
     * @param string $table The table name.
     * @param array $columns The columns to select. Defaults to ['*'].
     * @param array $where An associative array of conditions (column => value).
     * @return array|null Returns an associative array of the selected row, or null if not found.
     */
    public function select(string $table, array $columns = ['*'], array $where = []): ?array
    {
        $columns = array_map(function($col) {
            return $col === '*' ? $col : $this->quoteIdentifier($col);
        }, $columns);

        $sql = "SELECT " . implode(', ', $columns) . " FROM " . $this->quoteIdentifier($table);

        $params = [];
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $paramKey = 'where_' . count($params);
                $conditions[] = $this->quoteIdentifier($key) . " = :" . $paramKey;
                $params[$paramKey] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " LIMIT 1";

        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Select multiple rows from a table.
     *
     * @param string $table The table name.
     * @param array $columns The columns to select. Defaults to ['*'].
     * @param array $where An associative array of conditions (column => value).
     * @param string|null $orderBy Column to order by (e.g. 'created_at DESC').
     * @param int|null $limit Maximum number of rows to return.
     * @param int|null $offset Number of rows to skip.
     * @return array Returns an array of associative arrays for each selected row.
     */
    public function selectAll(
        string $table,
        array $columns = ['*'],
        array $where = [],
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $columns = array_map(function($col) {
            return $col === '*' ? $col : $this->quoteIdentifier($col);
        }, $columns);

        $sql = "SELECT " . implode(', ', $columns) . " FROM " . $this->quoteIdentifier($table);

        $params = [];
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $paramKey = 'where_' . count($params);
                $conditions[] = $this->quoteIdentifier($key) . " = :" . $paramKey;
                $params[$paramKey] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        if ($orderBy !== null) {
            $sql .= " ORDER BY " . $this->sanitizeOrderBy($orderBy);
        }

        if ($limit !== null) {
            $sql .= " LIMIT " . $limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . $offset;
            }
        }

        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count rows in a table.
     *
     * @param string $table The table name.
     * @param array $where An associative array of conditions (column => value).
     * @return int The number of matching rows.
     */
    public function count(string $table, array $where = []): int
    {
        $sql = "SELECT COUNT(*) FROM " . $this->quoteIdentifier($table);

        $params = [];
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $paramKey = 'where_' . count($params);
                $conditions[] = $this->quoteIdentifier($key) . " = :" . $paramKey;
                $params[$paramKey] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Insert a new row into a table.
     *
     * @param string $table The table name.
     * @param array $data An associative array of column => value pairs to insert.
     * @return int Returns the last inserted ID.
     */
    public function insert(string $table, array $data): int
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $key => $value) {
            $sanitizedKey = 'param_' . count($params);
            $columns[] = $this->quoteIdentifier($key);
            $placeholders[] = ':' . $sanitizedKey;
            $params[$sanitizedKey] = $value;
        }

        $sql = "INSERT INTO " . $this->quoteIdentifier($table)
            . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return (int) $this->getConnection()->lastInsertId();
    }

    /**
     * Update existing rows in a table.
     *
     * @param string $table The table name.
     * @param array $data An associative array of column => value pairs to update.
     * @param array $where An associative array of conditions (column => value).
     * @return int Returns the number of rows affected.
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = [];
        $params = [];

        foreach ($data as $key => $value) {
            $paramKey = 'set_' . count($params);
            $set[] = $this->quoteIdentifier($key) . " = :" . $paramKey;
            $params[$paramKey] = $value;
        }

        $conditions = [];
        foreach ($where as $key => $value) {
            $paramKey = 'where_' . count($params);
            $conditions[] = $this->quoteIdentifier($key) . " = :" . $paramKey;
            $params[$paramKey] = $value;
        }

        $sql = "UPDATE " . $this->quoteIdentifier($table)
            . " SET " . implode(', ', $set)
            . " WHERE " . implode(' AND ', $conditions);

        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Delete rows from a table.
     *
     * @param string $table The table name.
     * @param array $where An associative array of conditions (column => value).
     * @return int Returns the number of rows affected.
     */
    public function delete(string $table, array $where): int
    {
        $conditions = [];
        $params = [];

        foreach ($where as $key => $value) {
            $paramKey = 'where_' . count($params);
            $conditions[] = $this->quoteIdentifier($key) . " = :" . $paramKey;
            $params[$paramKey] = $value;
        }

        $sql = "DELETE FROM " . $this->quoteIdentifier($table)
            . " WHERE " . implode(' AND ', $conditions);

        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $sql The SQL query.
     * @param array $params Parameters to bind to the query.
     * @return \PDOStatement The PDOStatement object.
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Begin a database transaction.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Roll back the current transaction.
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Prepares an SQL statement for execution.
     *
     * @param string $sql The SQL statement.
     * @return \PDOStatement The prepared PDOStatement object.
     */
    private function prepare(string $sql): \PDOStatement
    {
        $this->queries[] = $sql;
        return $this->getConnection()->prepare($sql);
    }

    /**
     * Get the list of executed queries.
     *
     * @return array An array of SQL strings that have been executed.
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get the PDO connection. Establishes a new connection if one doesn't exist.
     *
     * @return \PDO The PDO connection instance.
     */
    public function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $this->connection = new \PDO(
                $this->createDsn(),
                $this->config['username'],
                $this->config['password'],
                [
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        }

        return $this->connection;
    }

    /**
     * Create a DSN string based on the configuration array.
     *
     * @return string The DSN string.
     */
    private function createDsn(): string
    {
        return sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );
    }
}
