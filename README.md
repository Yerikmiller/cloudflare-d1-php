# Cloudflare D1 PHP Client

A lightweight, framework-agnostic PHP client for interacting with Cloudflare D1 databases.

## Requirements

- PHP 7.4 or higher
- cURL extension
- JSON extension
- A Cloudflare account with D1 database access

## Installation

```bash
composer require yerikmiller/cloudflare-d1-php
```

## Usage

### Basic Setup

```php
require 'vendor/autoload.php';

use Cloudflare\D1\D1;

// Initialize the D1 client
$d1 = new D1(
    'your-account-id',      // Cloudflare Account ID
    'your-api-token',      // Cloudflare API Token with D1 permissions
    'your-database-id'     // D1 Database ID
);
```

### Executing Queries

#### Fetching Data

```php
// Get all rows from a table
$results = $d1->get('SELECT * FROM users');

// Get a single row
$user = $d1->first('SELECT * FROM users WHERE id = ?', [1]);

// Get a single value
$email = $d1->value('SELECT email FROM users WHERE id = ?', [1]);
```

#### Modifying Data

```php
// Insert a new record
$affectedRows = $d1->execute(
    'INSERT INTO users (name, email) VALUES (?, ?)',
    ['John Doe', 'john@example.com']
);

// Update a record
$affectedRows = $d1->execute(
    'UPDATE users SET name = ? WHERE id = ?',
    ['John Updated', 1]
);

// Delete a record
$affectedRows = $d1->execute('DELETE FROM users WHERE id = ?', [1]);
```

#### Working with Results

```php
// Get all users
$users = $d1->get('SELECT * FROM users');

foreach ($users as $user) {
    echo "User: {$user['name']} ({$user['email']})\n";
}

// Get the last insert ID
$id = $d1->execute(
    'INSERT INTO users (name, email) VALUES (?, ?)',
    ['New User', 'new@example.com']
);
$lastInsertId = $d1->value('SELECT last_insert_rowid()');
```

## Error Handling

```php
try {
    $result = $d1->query('SELECT * FROM non_existent_table');
} catch (\Cloudflare\D1\D1Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Testing

```bash
composer test
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
