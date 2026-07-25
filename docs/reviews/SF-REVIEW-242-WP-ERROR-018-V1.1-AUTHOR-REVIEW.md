# SF-REVIEW-242 — WP-ERROR-018 Version 1.1 Author Correction Review

**Disposition:** Approved.

The author review confirms from WordPress 7.0.1 Core that a failed initial `wpdb::db_connect()` attempt directly checks `wp-content/db-error.php`, otherwise constructs the connection-error presentation, and calls `wpdb::bail()` with `db_connect_fail`; `wpdb::bail()` passes the presentation to `wp_die()` when errors are shown. The initial branch does not call `dead_db()`.

The revised entry distinguishes that initial path from later Core call sites that invoke `dead_db()`. It preserves the optional drop-in behavior, handled-versus-uncaught-fatal boundary, general observable ownership, and handoffs to cause-specific Database entries. No open findings.
