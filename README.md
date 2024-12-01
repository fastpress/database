# Fastpress\Database\Database Class

The `Database` class provides a simple interface for interacting with a MySQL database using PDO. It supports basic CRUD operations and allows for raw query execution. Below is a detailed explanation of the public methods available in this class.

## Public Methods

### `__construct(array $config)`

- **Description**: Initializes the `Database` object with the given configuration.
- **Parameters**:
  - `array $config`: An associative array containing database connection details such as host, port, database name, username, password, and charset.

### `select(string $table, array $columns = ['*'], array $where = []): ?array`

- **Description**: Fetches a single record from the specified table.
- **Parameters**:
  - `string $table`: The name of the table to query.
  - `array $columns`: An array of column names to select. Defaults to all columns (`['*']`).
  - `array $where`: An associative array of conditions for the WHERE clause.
- **Returns**: An associative array representing the fetched record or `null` if no record is found.

### `selectAll(string $table, array $columns = ['*'], array $where = []): array`

- **Description**: Fetches all records from the specified table that match the given conditions.
- **Parameters**:
  - `string $table`: The name of the table to query.
  - `array $columns`: An array of column names to select. Defaults to all columns (`['*']`).
  - `array $where`: An associative array of conditions for the WHERE clause.
- **Returns**: An array of associative arrays representing the fetched records.

### `insert(string $table, array $data): int`

- **Description**: Inserts a new record into the specified table.
- **Parameters**:
  - `string $table`: The name of the table where data will be inserted.
  - `array $data`: An associative array of column-value pairs to insert.
- **Returns**: The ID of the inserted record as an integer.

### `update(string $table, array $data, array $where): int`

- **Description**: Updates existing records in the specified table based on given conditions.
- **Parameters**:
  - `string $table`: The name of the table to update.
  - `array $data`: An associative array of column-value pairs to update.
  - `array $where`: An associative array of conditions for the WHERE clause.
- **Returns**: The number of affected rows as an integer.

### `delete(string $table, array $where): int`

- **Description**: Deletes records from the specified table based on given conditions.
- **Parameters**:
  - `string $table`: The name of the table from which records will be deleted.
  - `array $where`: An associative array of conditions for the WHERE clause.
- **Returns**: The number of affected rows as an integer.

### `query(string $sql, array $params = []): \PDOStatement`

- **Description**: Executes a raw SQL query with optional parameters.
- **Parameters**:
  - `string $sql`: The raw SQL query to execute.
  - `array $params`: An associative array of parameters to bind to the query.
- **Returns**: A PDOStatement object representing the result set.

### `getQueries(): array`

- **Description**: Retrieves all executed SQL queries during the current session.
- **Returns**: An array of strings representing executed SQL queries.

## Usage Example

```php
use Fastpress\Database\Database;

// Configuration
$config = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'my_database',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Initialize Database object
$db = new Database($config);

// Insert example
$insertId = $db->insert('users', ['name' => 'John Doe', 'email' => 'john@example.com']);

// Select example
$user = $db->select('users', ['name', 'email'], ['id' => $insertId]);

// Update example
$affectedRows = $db->update('users', ['email' => 'john.doe@example.com'], ['id' => $insertId]);

// Delete example
$deletedRows = $db->delete('users', ['id' => $insertId]);

// Raw query example
$stmt = $db->query('SELECT * FROM users WHERE email LIKE :email', ['email' => '%example.com']);
$users = $stmt->fetchAll(\PDO::FETCH_ASSOC);