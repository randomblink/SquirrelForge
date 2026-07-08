Status: Stable

---
# WordPress Custom Taxonomies

## Purpose

This document provides a guide to creating and managing custom taxonomies. Taxonomies are the WordPress equivalent of categories and tags, used to group and classify content. Creating custom taxonomies allows for structured classification of Custom Post Types (e.g., grouping "Books" by "Genre").

## Core Principle

Use taxonomies to create relationships between posts and to allow users to filter and browse content by classification.

---

## Core Function: `register_taxonomy()`

The primary function for creating a custom taxonomy is `register_taxonomy( string $taxonomy, array|string $object_type, array $args = [] )`.

- **`$taxonomy`**: The name of the taxonomy. Must be a unique string, max 32 characters, and cannot contain capital letters or spaces. Example: `genre`.
- **`$object_type`**: The post type(s) this taxonomy will apply to. Can be a string for a single post type (e.g., `book`) or an array for multiple (e.g., `['post', 'book']`).
- **`$args`**: An array of arguments that define the taxonomy's behavior.

### Registration Hook

**Rule:** `register_taxonomy()` **must** be called from a function hooked to the `init` action.

```php
add_action( 'init', 'my_plugin_register_genre_taxonomy' );
```

---

## Key Arguments for `$args`

- **`labels`**: (array) An array of strings for the admin UI. Crucial for user experience.
- **`hierarchical`**: (bool) The most important argument.
    - If `true`, the taxonomy behaves like categories (parent/child relationships, checkboxes in UI).
    - If `false`, it behaves like tags (flat structure, text input for adding terms).
- **`public`**: (bool) Whether the taxonomy is intended for public use.
- **`show_ui`**: (bool) Whether to show a UI for managing the taxonomy in the admin area.
- **`show_in_rest`**: (bool) Whether to include the taxonomy in the REST API. **Set to `true`** to make it available to the block editor.
- **`rewrite`**: (array|bool) Defines the URL structure for taxonomy term archives. Example: `rewrite => [ 'slug' => 'genres' ]`.

---

## Flushing Rewrite Rules

**Important Rule:** Just like with Custom Post Types, `flush_rewrite_rules()` should be called on plugin activation to register the new taxonomy slugs, and **never** on the `init` hook.

---

## Full Example: Registering a "Genre" Taxonomy for "Books"

This example creates a hierarchical "Genre" taxonomy for the "book" CPT.

```php
<?php
/**
 * Register the "Genre" custom taxonomy for the "Book" post type.
 */
function my_plugin_register_genre_taxonomy() {

    $labels = [
        'name'              => _x( 'Genres', 'taxonomy general name', 'my-plugin-textdomain' ),
        'singular_name'     => _x( 'Genre', 'taxonomy singular name', 'my-plugin-textdomain' ),
        'search_items'      => __( 'Search Genres', 'my-plugin-textdomain' ),
        'all_items'         => __( 'All Genres', 'my-plugin-textdomain' ),
        'parent_item'       => __( 'Parent Genre', 'my-plugin-textdomain' ),
        'parent_item_colon' => __( 'Parent Genre:', 'my-plugin-textdomain' ),
        'edit_item'         => __( 'Edit Genre', 'my-plugin-textdomain' ),
        'update_item'       => __( 'Update Genre', 'my-plugin-textdomain' ),
        'add_new_item'      => __( 'Add New Genre', 'my-plugin-textdomain' ),
        'new_item_name'     => __( 'New Genre Name', 'my-plugin-textdomain' ),
        'menu_name'         => __( 'Genres', 'my-plugin-textdomain' ),
    ];

    $args = [
        'labels'            => $labels,
        'hierarchical'      => true, // Like categories
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => true,
        'show_in_rest'      => true, // Important for Block Editor
        'rewrite'           => [ 'slug' => 'genre' ],
    ];

    register_taxonomy( 'genre', [ 'book' ], $args );
}
add_action( 'init', 'my_plugin_register_genre_taxonomy' );

/**
 * Flush rewrite rules on plugin activation.
 */
function my_plugin_taxonomy_rewrite_flush() {
    // First, re-register the post type and taxonomy.
    my_plugin_register_book_cpt(); // Assuming this function exists from CPT registration.
    my_plugin_register_genre_taxonomy();

    // Now, flush the rules.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'my_plugin_taxonomy_rewrite_flush' );
```

## Rule

WordPress taxonomy work must preserve data integrity, rewrite behavior, capability rules, and admin/frontend compatibility.
