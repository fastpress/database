# Fastpress\Memory\Database

This class provides a simple database abstraction layer built on top of PDO, enabling you to interact with a MySQL database. It simplifies common CRUD (Create, Read, Update, Delete) operations and lets you run raw queries if needed.

## Features

- PHP 8.0+
- PDO extension enabled
- A MySQL database

## Installation

Install via Composer:

```bash
composer require fastpress/memory
```
## Usage Example
```php
<?php

use Fastpress\Memory\Database;

$config = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'your_database',
    'charset' => 'utf8mb4',
    'username' => 'your_username',
    'password' => 'your_password'
];

$db = new Database($config);

// Select a single row
$user = $db->select('users', ['id', 'name', 'email'], ['id' => 1]);
if ($user) {
    echo "Name: {$user['name']}, Email: {$user['email']}";
}

// Select multiple rows
$allUsers = $db->selectAll('users', ['id', 'name', 'email']);
foreach ($allUsers as $u) {
    echo "ID: {$u['id']}, Name: {$u['name']}\n";
}

// Insert a new row
$newUserId = $db->insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
echo "Inserted user ID: $newUserId";

// Update existing rows
$rowsUpdated = $db->update('users', [
    'email' => 'john.doe@example.com'
], [
    'id' => $newUserId
]);
echo "$rowsUpdated row(s) updated.";

// Delete rows
$rowsDeleted = $db->delete('users', [
    'id' => $newUserId
]);
echo "$rowsDeleted row(s) deleted.";

// Running a raw query
$stmt = $db->query("SELECT COUNT(*) as total FROM `users`");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total users: {$result['total']}";
```
## Methods
```php
select($table, $columns = ['*'], $where = [])
// Fetches a single row.

selectAll($table, $columns = ['*'], $where = [])
// Fetches all rows matching criteria.

insert($table, $data)
// Inserts a new row and returns the inserted ID.

update($table, $data, $where)
// Updates rows matching where conditions and returns the number of affected rows.

delete($table, $where)
// Deletes rows matching where conditions and returns the number of affected rows.

query($sql, $params = [])
// Executes a raw SQL query.

getQueries()
// Returns an array of executed queries.
```
