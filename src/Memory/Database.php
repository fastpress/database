<?php

namespace Fastpress\Memory;

class Database 
{
    private static ?\PDO $connection = null;
    private array $config;
    private array $queries = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

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

    // Raw query execution for complex queries
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function prepare(string $sql): \PDOStatement
    {
        $this->queries[] = $sql;
        return $this->getConnection()->prepare($sql);
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

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
