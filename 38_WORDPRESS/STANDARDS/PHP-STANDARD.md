# SquirrelForge WordPress PHP Standard

## Purpose

This document defines the PHP standards SquirrelForge must follow when generating or reviewing WordPress plugin and theme code.

---

## Core Rules

SquirrelForge PHP code must be:

- readable
- secure
- maintainable
- prefixed or namespaced
- WordPress-compatible
- documented where useful

---

## PHP Version

Generated code should declare the minimum supported PHP version in the project documentation.

Default recommendation:

```text
PHP 8.0+
File Rules

Each PHP file must:

start with <?php
prevent direct access when executable by request
contain one primary class when class-based
use clear comments for non-obvious logic

Example:

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
Naming

All PHP naming must follow:

38_WORDPRESS/STANDARDS/NAMING-STANDARD.md
Security Rules

PHP code must:

sanitize input
escape output
verify nonces
check capabilities
prepare SQL queries
avoid hardcoded secrets
avoid unsafe file operations
WordPress API Preference

Use WordPress APIs before custom code.

Prefer:

get_option()
update_option()
add_action()
add_filter()
wp_enqueue_script()
wp_enqueue_style()
register_setting()
register_rest_route()
$wpdb->prepare()
Class Rules

Classes should:

have one primary responsibility
use clear method names
avoid hidden side effects
avoid excessive static methods
avoid direct output unless responsible for rendering
Function Rules

Global functions must be prefixed.

Unprefixed global functions are forbidden.

Error Handling

Code should fail safely.

Public users should not see technical errors.

Admin users may see clear, actionable notices.

Comments

Use comments to explain why code exists, not to restate obvious code.

Forbidden Patterns

Do not approve PHP code that:

uses unsanitized request data
echoes unescaped dynamic output
uses raw SQL with user input
skips capability checks
skips nonce checks for state changes
stores secrets in code
modifies WordPress core
uses generic global function names
Review Checklist

Verify:

files block direct access
names are prefixed or namespaced
input is sanitized
output is escaped
capabilities are checked
nonces protect state changes
SQL is prepared
WordPress APIs are preferred
code is readable
Rule

SquirrelForge must reject WordPress PHP code that fails critical security, naming, or maintainability requirements.
