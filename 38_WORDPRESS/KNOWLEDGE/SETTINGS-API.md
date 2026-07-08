Status: Stable

---
# WordPress Settings API

## Purpose

This document provides a comprehensive guide to the WordPress Settings API. It is the standard, secure method for creating and managing settings pages for plugins and themes. SquirrelForge must use this API for all plugin and theme options.

## Core Principle

The Settings API automates the saving, sanitizing, and retrieving of options, while also handling security nonces. This abstracts away manual data handling and significantly improves security.

---

## Key Concepts

- **Option Group:** A name for a group of settings. Used to register all settings for a specific page.
- **Option Name:** The key used to store the setting value in the `wp_options` database table. A single option can store an array of multiple settings.
- **Section:** A logical grouping of settings fields on a page, often with its own heading.
- **Field:** An individual setting, which corresponds to a form input (e.g., text box, checkbox, dropdown).

---

## Core Functions

### 1. `register_setting( string $option_group, string $option_name, array $args = [] )`

This is the most important function. It registers a setting with WordPress.

- **`$option_group`**: A unique name for the group of settings. This is used in `settings_fields()`.
- **`$option_name`**: The name of the option as it is stored in the `wp_options` table.
- **`$args`**: An array of arguments. The most important is `sanitize_callback`, which specifies a function to sanitize the data before it is saved to the database. **This is a critical security step.**

### 2. `add_settings_section( string $id, string $title, callable $callback, string $page )`

Creates a new section on a settings page.

- **`$id`**: A unique slug for the section.
- **`$title`**: The title of the section, displayed as a heading.
- **`$callback`**: A function that can render introductory text for the section.
- **`$page`**: The slug of the admin page where this section should be displayed.

### 3. `add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = [] )`

Adds a new field to a settings section.

- **`$id`**: A unique slug for the field.
- **`$title`**: The label for the field.
- **`$callback`**: A function that renders the HTML for the field's input element (e.g., `<input type="text">`).
- **`$page`**: The slug of the admin page.
- **`$section`**: The ID of the section this field belongs to.
- **`$args`**: Optional arguments passed to the callback function, often used for passing a label or description.

---

## Standard Workflow

1.  **Create an Admin Page:** Use `add_options_page()` (or another `add_*_page` function) to create the menu item and page.
2.  **Register Settings:** Hook a function into the `admin_init` action. Inside this function:
    -   Call `register_setting()` for each option you need to save. **Always include a `sanitize_callback`**.
    -   Call `add_settings_section()` to define the sections for your page.
    -   Call `add_settings_field()` for each individual setting.
3.  **Render the Page:** In the callback function for your admin page:
    -   Create a `<form method="post" action="options.php">`.
    -   Call `settings_fields( $option_group )`. This renders the nonce, `action`, and `option_page` hidden fields. **This is essential for security.**
    -   Call `do_settings_sections( $page_slug )`. This renders all sections and fields that were added to the page.
    -   Call `submit_button()`.

---

## Full Example

This example creates a simple settings page with one text field.

```php
<?php
/**
 * Create the admin menu page.
 */
function my_plugin_add_settings_page() {
    add_options_page(
        'My Plugin Settings',
        'My Plugin',
        'manage_options',
        'my-plugin',
        'my_plugin_render_settings_page'
    );
}
add_action( 'admin_menu', 'my_plugin_add_settings_page' );

/**
 * Register the settings, section, and field.
 */
function my_plugin_register_settings() {
    // Register the setting.
    register_setting(
        'my_plugin_options_group', // Option group
        'my_plugin_settings',      // Option name
        [
            'sanitize_callback' => 'sanitize_text_field', // Sanitize as a simple text field.
        ]
    );

    // Add the section.
    add_settings_section(
        'my_plugin_main_section', // Section ID
        'Main Settings',          // Title
        null,                     // Callback (optional)
        'my-plugin'               // Page slug
    );

    // Add the field.
    add_settings_field(
        'my_plugin_api_key', // Field ID
        'API Key',           // Title
        'my_plugin_render_api_key_field', // Callback to render the input
        'my-plugin',         // Page slug
        'my_plugin_main_section' // Section ID
    );
}
add_action( 'admin_init', 'my_plugin_register_settings' );

/**
 * Render the API Key input field.
 */
function my_plugin_render_api_key_field() {
    $options = get_option( 'my_plugin_settings' );
    $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
    printf(
        '<input type="text" id="my_plugin_api_key" name="my_plugin_settings[api_key]" value="%s" />',
        esc_attr( $api_key )
    );
}

/**
 * Render the main settings page form.
 */
function my_plugin_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Output security fields for the registered setting group.
            settings_fields( 'my_plugin_options_group' );
            // Output the settings sections and their fields.
            do_settings_sections( 'my-plugin' );
            // Output the submit button.
            submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}
```

## Rule

Settings API work must define registration, sanitization, defaults, permissions, rendering, and persistence behavior.
