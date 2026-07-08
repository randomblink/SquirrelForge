Status: Stable

---
# WordPress REST API

## Purpose

This document provides a guide to the WordPress REST API. It is the standard, modern way to interact with WordPress data programmatically. SquirrelForge must use this API to create endpoints for headless applications, JavaScript-powered admin interfaces, and other external integrations.

## Core Principle

The REST API provides a standardized, JSON-based interface for Create, Read, Update, and Delete (CRUD) operations on WordPress content. All custom endpoints must prioritize security, especially permission checks.

---

## Key Concepts

- **Namespace:** A vendor-specific prefix for your routes to prevent conflicts with core endpoints or other plugins. Example: `my-plugin/v1`.
- **Route:** The URL used to access an endpoint. It's the part after the namespace. Example: `/books/(?P<id>[\d]+)`.
- **Endpoint:** The combination of a route and an HTTP method (`GET`, `POST`, `PUT`, `DELETE`) that performs a specific action.
- **Schema:** A formal definition of the data an endpoint accepts (request) and returns (response), including data types and descriptions.

---

## Core Function: `register_rest_route()`

The primary function for creating a custom endpoint is `register_rest_route( string $namespace, string $route, array $args )`.

- **`$namespace`**: The unique namespace for your plugin's endpoints.
- **`$route`**: The specific route pattern. Can include regular expressions for parameters.
- **`$args`**: An array defining one or more endpoints for this route.

### Registration Hook

**Rule:** `register_rest_route()` **must** be called from a function hooked to the `rest_api_init` action.

```php
add_action( 'rest_api_init', 'my_plugin_register_custom_routes' );
```

---

## Key Arguments for `$args`

This array can contain multiple endpoints, one for each HTTP method.

- **`methods`**: (string|array) The HTTP method(s) for this endpoint (e.g., `GET`, `WP_REST_Server::READABLE`).
- **`callback`**: (callable) The function that will be executed to handle the request. It receives a `WP_REST_Request` object as its only parameter.
- **`permission_callback`**: (callable) **CRITICAL FOR SECURITY.** A function that checks if the current user has permission to access the endpoint. It must return `true` or a `WP_Error`. If this is not defined, the endpoint will be considered public, which is a major security risk for any data-modifying endpoint.
- **`args`**: (array) An array defining the expected parameters for the request. This is used for validation and sanitization.

---

## The `WP_REST_Request` and `WP_REST_Response` Objects

### `WP_REST_Request`

The callback function receives this object. Use its methods to safely access request data.

- `$request->get_params()`: Gets all URL, body, and default parameters.
- `$request->get_param( 'my_param' )`: Gets a specific parameter.
- `$request->get_method()`: Gets the HTTP method of the request.

### `WP_REST_Response`

Your callback should return an instance of this class (or a `WP_Error`).

- `new WP_REST_Response( $data, $status_code )`: Creates a new response.
- `$response->set_status( 201 )`: Sets the HTTP status code.

---

## Full Example: A Custom `GET` Endpoint

This example creates a read-only endpoint to get information about a specific book.

```php
<?php
/**
 * Register a custom REST API route.
 */
function my_plugin_register_custom_routes() {
    register_rest_route(
        'my-plugin/v1', // Namespace
        '/book/(?P<id>[\d]+)', // Route with a numeric ID parameter
        [
            'methods'             => WP_REST_Server::READABLE, // This is equivalent to 'GET'
            'callback'            => 'my_plugin_get_book_data',
            'permission_callback' => '__return_true', // Publicly accessible for reading
            'args'                => [
                'id' => [
                    'validate_callback' => function( $param, $request, $key ) {
                        return is_numeric( $param );
                    },
                    'required' => true,
                ],
            ],
        ]
    );
}
add_action( 'rest_api_init', 'my_plugin_register_custom_routes' );

/**
 * The callback function for the custom endpoint.
 *
 * @param WP_REST_Request $request The full request object.
 * @return WP_REST_Response|WP_Error The response object or a WP_Error on failure.
 */
function my_plugin_get_book_data( WP_REST_Request $request ) {
    $book_id = (int) $request['id'];
    $post = get_post( $book_id );

    // Check if the post exists and is of the correct type.
    if ( empty( $post ) || 'book' !== $post->post_type ) {
        return new WP_Error(
            'rest_book_not_found',
            'No book was found with that ID.',
            [ 'status' => 404 ]
        );
    }

    // Prepare the data to return.
    $data = [
        'id'    => $post->ID,
        'title' => get_the_title( $post ),
        'content' => apply_filters( 'the_content', $post->post_content ),
        'author' => get_the_author_meta( 'display_name', $post->post_author ),
    ];

    // Create the response object.
    $response = new WP_REST_Response( $data, 200 );

    return $response;
}
```

### Security Note on `permission_callback`

For any endpoint that modifies data (`POST`, `PUT`, `DELETE`), the `permission_callback` is not optional. It **must** check for appropriate user capabilities.

**Example for a data-modifying endpoint:**
```php
'permission_callback' => function () {
    return current_user_can( 'edit_posts' );
},
```

## Rule

WordPress REST API work must define route, permission callback, validation, sanitization, response contract, and tests.
