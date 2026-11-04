# Cloudflare D1 PHP Client

A lightweight, framework-agnostic PHP client for interacting with Cloudflare D1 databases.

## Requirements

- PHP 8.0 or higher
- cURL extension
- JSON extension
- A Cloudflare account with D1 database access

## Installation

```bash
composer require mdestafadilah/cloudflare-d1-php
```

## Usage

### Basic Setup

```php
require 'vendor/autoload.php';

use Cloudflare\D1\D1;

$d1 = new D1('your-account-id', 'your-api-token', 'your-database-id');
```

That is all the setup there is. Each client owns its credentials, so several
databases can be used in the same process without them interfering.

All three credentials are required. Passing an empty string throws
`D1Exception` at construction time, rather than sending a request with an empty
account or database ID.

### Upgrading from 0.x

The static properties `D1::$accountId`, `D1::$apiToken` and `D1::$databaseId`
are gone. They were a process-wide default that let one client's token replace
another's, so they are now required constructor arguments:

```php
// before
D1::$accountId = 'id';
D1::$apiToken = 'token';
D1::$databaseId = 'db';
$d1 = new D1();

// after
$d1 = new D1('id', 'token', 'db');
```

If a framework needs a single configured client, bind it as a container
singleton instead of reintroducing global state.

### Timeouts

Requests are bounded by default: 10 seconds to connect, 30 seconds in total.
A stalled API therefore cannot block a PHP worker forever.

```php
$d1 = (new D1('id', 'token', 'db'))->setConnectTimeout(5)->setTimeout(15);
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
