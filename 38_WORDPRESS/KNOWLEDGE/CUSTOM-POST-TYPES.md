Status: Stable

---
# WordPress Custom Post Types (CPTs)

## Purpose

This document provides a comprehensive guide to creating and managing Custom Post Types (CPTs). CPTs are used to create new types of content in WordPress, separate from default Posts and Pages. This is the standard way to model structured data like "Products," "Events," "Books," or "Portfolio Items."

## Core Principle

Custom Post Types allow developers to create custom content schemas with their own unique fields, templates, and administrative UIs. They are the primary tool for building content-driven applications with WordPress.

---

## Core Function: `register_post_type()`

The primary function for creating a CPT is `register_post_type( string $post_type, array $args )`.

- **`$post_type`**: The name of the post type. Must be a unique string, max 20 characters, and cannot contain capital letters or spaces. Example: `book`.
- **`$args`**: An array of arguments that define the behavior and labels of the post type.

### Registration Hook

**Rule:** `register_post_type()` **must** be called from a function hooked to the `init` action. Calling it earlier or later can cause issues.

```php
add_action( 'init', 'my_plugin_register_book_cpt' );
```

---

## Key Arguments for `$args`

- **`labels`**: (array) An array of strings that define the text used in the admin UI for this post type. This is crucial for a good user experience.
- **`public`**: (bool) A shorthand for several other arguments. If `true`, the post type is visible to authors and readers on the front end. Defaults to `false`.
- **`has_archive`**: (bool|string) If `true`, enables a post type archive at `/post-type-slug/`. If a string is provided, it will be used as the archive slug.
- **`supports`**: (array) An array of features the post type supports. Common values include:
    - `title`
    - `editor` (the main content area)
    - `thumbnail` (featured image)
    - `excerpt`
    - `custom-fields` (for post meta)
    - `revisions`
    - `author`
- **`rewrite`**: (array|bool) Defines the URL structure. Setting `rewrite => [ 'slug' => 'books' ]` will make single posts available at `/books/post-name/`.
- **`menu_icon`**: (string) The icon to be used in the admin menu. Can be a URL to an image or a Dashicon class name (e.g., `dashicons-book-alt`).
- **`show_in_rest`**: (bool) Whether to include this post type in the REST API. Set to `true` to make it available to the block editor and other API clients.

---

## Flushing Rewrite Rules

**Important Rule:** After registering or modifying a CPT with a custom `rewrite` slug, WordPress needs to update its internal rewrite rules to recognize the new URLs. This should **not** be done on every page load.

- **Correct Way:** Call `flush_rewrite_rules()` inside your plugin's activation hook.
- **Incorrect Way:** Calling `flush_rewrite_rules()` inside the `init` hook. This is a major performance drain.

---

## Full Example: Registering a "Book" CPT

This example demonstrates a well-defined CPT registration.

```php
<?php
/**
 * Register the "Book" Custom Post Type.
 */
function my_plugin_register_book_cpt() {

    $labels = [
        'name'                  => _x( 'Books', 'Post type general name', 'my-plugin-textdomain' ),
        'singular_name'         => _x( 'Book', 'Post type singular name', 'my-plugin-textdomain' ),
        'menu_name'             => _x( 'Books', 'Admin Menu text', 'my-plugin-textdomain' ),
        'name_admin_bar'        => _x( 'Book', 'Add New on Toolbar', 'my-plugin-textdomain' ),
        'add_new'               => __( 'Add New', 'my-plugin-textdomain' ),
        'add_new_item'          => __( 'Add New Book', 'my-plugin-textdomain' ),
        'new_item'              => __( 'New Book', 'my-plugin-textdomain' ),
        'edit_item'             => __( 'Edit Book', 'my-plugin-textdomain' ),
        'view_item'             => __( 'View Book', 'my-plugin-textdomain' ),
        'all_items'             => __( 'All Books', 'my-plugin-textdomain' ),
        'search_items'          => __( 'Search Books', 'my-plugin-textdomain' ),
        'parent_item_colon'     => __( 'Parent Books:', 'my-plugin-textdomain' ),
        'not_found'             => __( 'No books found.', 'my-plugin-textdomain' ),
        'not_found_in_trash'    => __( 'No books found in Trash.', 'my-plugin-textdomain' ),
        'featured_image'        => _x( 'Book Cover Image', 'Overrides the “Featured Image” phrase for this post type.', 'my-plugin-textdomain' ),
        'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type.', 'my-plugin-textdomain' ),
        'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type.', 'my-plugin-textdomain' ),
        'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type.', 'my-plugin-textdomain' ),
        'archives'              => _x( 'Book archives', 'The post type archive label used in nav menus.', 'my-plugin-textdomain' ),
        'insert_into_item'      => _x( 'Insert into book', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media).', 'my-plugin-textdomain' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this book', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post).', 'my-plugin-textdomain' ),
        'filter_items_list'     => _x( 'Filter books list', 'Screen reader text for the filter links heading on the post type listing screen.', 'my-plugin-textdomain' ),
        'items_list_navigation' => _x( 'Books list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'my-plugin-textdomain' ),
        'items_list'            => _x( 'Books list', 'Screen reader text for the items list heading on the post type listing screen.', 'my-plugin-textdomain' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => [ 'slug' => 'books' ],
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5, // Below Posts
        'supports'           => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions' ],
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-book-alt',
    ];

    register_post_type( 'book', $args );
}
add_action( 'init', 'my_plugin_register_book_cpt' );

/**
 * Flush rewrite rules on plugin activation.
 */
function my_plugin_rewrite_flush() {
    my_plugin_register_book_cpt();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'my_plugin_rewrite_flush' );
```

## Rule

Custom post type knowledge must guide registration, capabilities, REST exposure, rewrite behavior, and lifecycle decisions.
