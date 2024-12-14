<?php

/**
 * Class Database
 *
 * This class provides a simple database abstraction layer on top of PDO.
 * It supports basic CRUD operations (select, insert, update, delete)
 * and allows the execution of raw queries. It uses prepared statements
 * to prevent SQL injection attacks. The class also quotes identifiers (table and column names)
 * to avoid reserved keyword issues. The connection is established using a DSN, username, and password
 * provided in the configuration array.
 */

namespace Fastpress\Memory;

class Database 
{
    private static ?\PDO $connection = null;
    private array $config;
    private array $queries = [];

    /**
     * Constructor
     *
     * @param array $config Configuration array with keys: host, port, database, charset, username, password
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Quotes an identifier (e.g., table or column name) to prevent issues with reserved keywords.
     *
     * @param string $identifier The identifier to quote.
     * @return string The quoted identifier.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Select a single row from a table.
     *
     * @param string $table The table name.
     * @param array $columns The columns to select. Defaults to ['*'].
     * @param array $where An associative array of conditions (column => value).
     * @return array|null Returns an associative array of the selected row, or null if no row was found.
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
     * @return array Returns an array of associative arrays for each selected row.
     */
    public function selectAll(string $table, array $columns = ['*'], array $where = []): array 
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

        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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

        $sql = "INSERT INTO " . $this->quoteIdentifier($table) . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        
        return (int)$this->getConnection()->lastInsertId();
    }

    /**
     * Update existing rows in a table.
     *
     * @param string $table The table name.
     * @param array $data An associative array of column => value pairs to update.
     * @param array $where An associative array of conditions (column => value) to determine which rows to update.
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
        
        $sql = "UPDATE " . $this->quoteIdentifier($table) . " SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $conditions);
        
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
        
        $sql = "DELETE FROM " . $this->quoteIdentifier($table) . " WHERE " . implode(' AND ', $conditions);
        
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
     * Get the PDO connection. Establishes a new connection if it doesn't exist yet.
     *
     * @return \PDO The PDO connection instance.
     */
    private function getConnection(): \PDO
    {
        if (self::$connection === null) {
            self::$connection = new \PDO(
                $this->createDsn(),
                $this->config['username'],
                $this->config['password'],
                [
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]
            );
        }

        return self::$connection;
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
