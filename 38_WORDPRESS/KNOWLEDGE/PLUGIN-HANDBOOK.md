Status: Stable

---
# SquirrelForge WordPress Plugin Handbook

## Purpose

This document defines the concepts, best practices, and architectural rules SquirrelForge must follow when designing and building WordPress plugins. It serves as a practical guide, translating high-level principles into actionable development standards.

## Core Principle

**Plugins add functionality.** A plugin's features should be self-contained and persist even if the site's theme is changed. Core business logic, data models, and critical features belong in plugins, not themes.

---

## Core Concepts

- **Plugin Anatomy**: A standard plugin consists of a main PHP file with a plugin header, a directory structure separating concerns (`includes`, `admin`, `public`), and often an `uninstall.php` file for cleanup.
- **Plugin Lifecycle**: The four key states are Activation, Runtime, Deactivation, and Uninstall. Each has a specific purpose and corresponding hook.
- **Bootstrap Pattern**: The main plugin file should be a lightweight "bootstrap" that defines constants, includes necessary files, and instantiates a main plugin controller class. All functional logic should be delegated to other classes.
- **Separation of Concerns**: Logic for the admin area, public-facing frontend, and shared core functionality should be organized into separate classes and directories.

---

## WordPress APIs

- **Lifecycle Hooks**: `register_activation_hook`, `register_deactivation_hook`.
- **Initialization Hooks**: `plugins_loaded` (for translations, dependency checks), `init` (for registering post types, taxonomies, etc.).
- **Admin Hooks**: `admin_menu` (for adding pages), `admin_init` (for registering settings), `admin_enqueue_scripts` (for assets).
- **Frontend Hooks**: `wp_enqueue_scripts` (for assets), `the_content` (filter for modifying content).
- **Data Storage APIs**: `get_option`, `update_option`, `get_post_meta`, `update_user_meta`, `set_transient`.
- **Asset APIs**: `wp_register_script`, `wp_enqueue_script`, `wp_register_style`, `wp_enqueue_style`.

---

## Best Practices

- **Prefix Everything**: All functions, classes, constants, and global variables must be prefixed with a unique identifier to prevent conflicts.
- **Use a Hook Loader**: Centralize all `add_action` and `add_filter` calls into a dedicated class or function to keep hook management organized.
- **Object-Oriented Programming (OOP)**: Organize code into classes with a single, clear responsibility. Instantiate objects rather than relying on static methods.
- **Dependency Management**: Check for minimum PHP and WordPress versions on startup and deactivate gracefully if requirements are not met.
- **Error Handling**: Use the `WP_Error` class for returning manageable errors. Avoid showing raw PHP errors to end-users.
- **Internationalization**: Wrap all user-facing strings in translation functions and load a text domain.

---

## Common Mistakes

- **Placing all logic in one file**: Creates unmaintainable "god files".
- **Calling `flush_rewrite_rules()` on `init`**: A major performance bottleneck. This should only be done on activation.
- **Not prefixing functions/classes**: Leads to fatal errors from name collisions with other plugins.
- **Hardcoding URLs or paths**: Use functions like `plugin_dir_url()` and `plugin_dir_path()` to ensure portability.
- **Mixing business logic with presentation**: A plugin should not generate large blocks of HTML. Use template files that can be overridden.
- **Not planning for uninstall**: Leaving orphaned options and database tables behind.

---

## Security Considerations

- **Reference `SECURITY.md`**: All security principles apply.
- **Sanitize All Input**: Never trust data from `$_POST`, `$_GET`, REST API requests, or options.
- **Escape All Output**: Prevent XSS by escaping data at the point of rendering.
- **Verify Nonces**: Protect all forms and AJAX actions that change data from CSRF attacks.
- **Check Capabilities**: Ensure the current user has permission to perform any privileged action.

---

## Performance Considerations

- **Conditional Asset Loading**: Only enqueue scripts and styles on the specific admin or frontend pages where they are needed.
- **Use Transients for Caching**: Cache the results of expensive operations or external API calls using the Transients API.
- **Efficient Database Queries**: Use `WP_Query` and built-in functions where possible. Avoid raw SQL unless necessary, and ensure custom queries are well-optimized.

---

## Accessibility Considerations

- All admin pages and settings forms must be accessible, following WordPress standards for labels, ARIA attributes, and keyboard navigation.
- Any HTML output on the frontend must be semantic and accessible.

---

## Testing Requirements

- **Activation/Deactivation/Uninstall**: Manually test that the plugin activates without errors, deactivates cleanly, and removes its data upon uninstallation.
- **Unit Tests**: Business logic within classes should be covered by PHPUnit tests.
- **Integration Tests**: Test that hooks, filters, and interactions with WordPress core functions behave as expected.

---

## Examples

### Basic Bootstrap Pattern (in `my-plugin.php`)
```php
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require_once MY_PLUGIN_PATH . 'includes/class-my-plugin.php';

function my_plugin_run() {
	$plugin = new My_Plugin();
	$plugin->run();
}
my_plugin_run();
```

### Correct Activation Hook
```php
function my_plugin_activate() {
    // Register CPTs/taxonomies here to make them available to flush_rewrite_rules().
    My_Plugin_Post_Types::register();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'my_plugin_activate' );
```

---

## Decision Rules

- **Data Storage**:
    - **Options API**: Use for a small number of site-wide settings.
    - **Custom Post Types**: Use when you need structured content that behaves like a post (e.g., has a title, content, archive page). This is the most common choice for content modeling (e.g., "Events", "Products").
    - **Metadata API**: Use for data directly related to a specific post, user, or term (e.g., an "Event Date" for an "Event" post).
    - **Custom Tables**: Use only as a last resort for large, non-standard, or highly relational datasets that do not fit the post model, or for logging/tracking data where performance is critical.

- **Functionality vs. Presentation**:
    - If the feature should persist when the theme changes, it belongs in a plugin.
    - If the feature is purely for visual presentation, it belongs in a theme.

---

## Agent Rules

1.  **Always start with a plan**: Before generating code, consult the `PLUGIN-ARCHITECT.md` to define the file structure, classes, and data model.
2.  **Enforce Structure**: Generate code that adheres to the standard plugin architecture (separate classes for admin, public, core, etc.).
3.  **Prioritize Security**: Every generated function that handles data must include the appropriate sanitization, escaping, nonce, and capability checks.
4.  **Document Everything**: Generate PHPDoc blocks for all classes, methods, and functions.
5.  **Use Lifecycle Hooks Correctly**: Place `flush_rewrite_rules` in the activation hook. Place data removal in `uninstall.php`.
6.  **Prefix All Names**: Generate a unique prefix for the plugin and apply it to all non-class functions, constants, and globals.
7.  **Conditionally Load Assets**: When generating asset enqueueing code, use conditional checks (e.g., `is_admin()`, `is_singular('my-cpt')`) to limit where assets are loaded.
