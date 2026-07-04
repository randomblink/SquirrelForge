# WordPress Database Interaction

## Purpose

This document defines the best practices for interacting with the WordPress database. It provides the rules SquirrelForge must follow to ensure all database operations are secure, efficient, and maintainable.

## Core Principle

**Always use WordPress APIs for database operations.** Avoid writing raw SQL whenever possible. When custom SQL is unavoidable, it **must** be secured using the provided tools.

---

## The `$wpdb` Object

`$wpdb` is the global WordPress database abstraction object. It provides a safe and standardized way to interact with the database.

- **Access:** It should be accessed via the global variable `$wpdb`.
  ```php
  global $wpdb;
  ```
- **Key Properties:**
    - `$wpdb->prefix`: The WordPress table prefix (e.g., `wp_`). Use this to make table names portable.
    - `$wpdb->posts`: The name of the posts table.
    - `$wpdb->postmeta`: The name of the postmeta table.
    - `$wpdb->users`: The name of the users table.

---

## Security: Preventing SQL Injection with `$wpdb->prepare()`

This is the most critical rule for database security.

- **Purpose:** `$wpdb->prepare()` is a method that creates a secure SQL query by escaping all input data. It prevents SQL injection attacks.
- **Rule:** All custom SQL queries that include variable data **must** be passed through `$wpdb->prepare()`. There are no exceptions.
- **Placeholders:**
    - `%s`: For string values.
    - `%d`: For integer values.
    - `%f`: For float values.

### Example

**INCORRECT (Vulnerable to SQL Injection):**
```php
// NEVER DO THIS.
$user_id = $_GET['id'];
$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}my_table WHERE user_id = " . $user_id );
```

**CORRECT (Secure):**
```php
global $wpdb;
$user_id = 123; // Assume this came from a sanitized source.
$status = 'active';

$query = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}my_table WHERE user_id = %d AND status = %s",
    $user_id,
    $status
);
$results = $wpdb->get_results( $query );
```

---

## Common `$wpdb` Methods

### Reading Data

- **`$wpdb->get_results( string $query, string $output_type = OBJECT )`**: Retrieves multiple rows from the database.
- **`$wpdb->get_row( string $query, string $output_type = OBJECT, int $row_offset = 0 )`**: Retrieves a single row.
- **`$wpdb->get_var( string $query, int $col_offset = 0, int $row_offset = 0 )`**: Retrieves a single value from a specific row and column.

### Writing Data

These methods handle their own sanitization and should be preferred over writing raw `INSERT` or `UPDATE` queries.

- **`$wpdb->insert( string $table, array $data, array $format = null )`**: Safely inserts a row into a table.
  ```php
  $wpdb->insert(
      $wpdb->prefix . 'my_table',
      [ 'name' => 'John Doe', 'email' => 'john@example.com' ],
      [ '%s', '%s' ] // Format for each value.
  );
  ```
- **`$wpdb->update( string $table, array $data, array $where, array $format = null, array $where_format = null )`**: Safely updates rows.
  ```php
  $wpdb->update(
      $wpdb->prefix . 'my_table',
      [ 'name' => 'Jane Doe' ], // Data to update
      [ 'id' => 1 ],           // Where clause
      [ '%s' ],                // Format for data
      [ '%d' ]                 // Format for where
  );
  ```
- **`$wpdb->delete( string $table, array $where, array $where_format = null )`**: Safely deletes rows.

---

## Custom Tables

- **When to Use:** Only when the existing WordPress data structures (posts, users, meta, options, taxonomies) are not a good fit. This is rare.
- **Creation:** Custom tables should be created on plugin activation using the `dbDelta()` function. This function can create and update tables without causing errors if the table already exists.
- **Hook:** Use `register_activation_hook()` to run the table creation logic.

### `dbDelta()` Example

```php
function my_plugin_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_custom_table';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        name text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'my_plugin_install' );
```

---

## Hierarchy of Data Access

1.  **Use `WP_Query` first:** For any query involving posts, pages, or CPTs.
2.  **Use Meta/Option/User functions next:** Use `get_post_meta()`, `get_option()`, `get_user_by()` before writing custom queries.
3.  **Use `$wpdb` as a last resort:** For interacting with custom tables or when a complex query cannot be achieved with the above APIs.