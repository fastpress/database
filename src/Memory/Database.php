<?php

namespace Fastpress\Database;

class Database 
{
    private static ?\PDO $connection = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function select(string $table, array $columns = ['*'], array $where = []): ?array 
    {
        $sql = "SELECT " . implode(', ', $columns) . " FROM " . $table;
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->prepare($sql);
        $stmt->execute($where);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function selectAll(string $table, array $columns = ['*'], array $where = []): array 
    {
        $sql = "SELECT " . implode(', ', $columns) . " FROM " . $table;
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->prepare($sql);
        $stmt->execute($where);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insert(string $table, array $data): int 
    {
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_map(fn($item) => ":$item", array_keys($data)));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($values)";
        
        $stmt = $this->prepare($sql);
        $stmt->execute($data);
        
        return (int)$this->getConnection()->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int 
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "$key = :set_$key";
        }
        
        $conditions = [];
        foreach ($where as $key => $value) {
            $conditions[] = "$key = :where_$key";
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $conditions);
        
        $params = [];
        foreach ($data as $key => $value) {
            $params["set_$key"] = $value;
        }
        foreach ($where as $key => $value) {
            $params["where_$key"] = $value;
        }
        
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    public function delete(string $table, array $where): int 
    {
        $conditions = [];
        foreach ($where as $key => $value) {
            $conditions[] = "$key = :$key";
        }
        
        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $conditions);
        
        $stmt = $this->prepare($sql);
        $stmt->execute($where);
        
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
        return $this->getConnection()->prepare($sql);
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