Status: Stable

---
# WordPress Security Principles

## Purpose

This document provides a deep dive into the core security principles that SquirrelForge must enforce for all WordPress development. It serves as the primary reference for the `SECURITY-VALIDATOR.md` and guides all architectural and code-generation decisions.

## Core Principle

**Never trust any data.** All data, whether from users, third-party APIs, or even the database, must be considered untrusted until it has been validated, sanitized, and properly escaped for its specific context.

---

## The Security Trinity

Every interaction in WordPress can be broken down into three critical security checks:

1.  **Permissions (Can you do this?):** Verify the user has the authority to perform the requested action.
2.  **Validation & Sanitization (Is this data safe?):** Clean and validate all incoming data before using it.
3.  **Escaping (Can this be displayed safely?):** Secure all outgoing data before rendering it to the screen.

---

## 1. Permissions & Access Control

### Capability Checks

- **Function:** `current_user_can( string $capability )`
- **Rule:** Every administrative action, AJAX handler, REST API endpoint, and form submission that performs a privileged operation **must** be protected by a capability check.
- **Example:**
  ```php
  if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'You do not have sufficient permissions to access this page.' );
  }
  ```

### Nonces (Cross-Site Request Forgery - CSRF)

- **Purpose:** Nonces ("numbers used once") are security tokens used to verify that a request was initiated by the current user from a legitimate source, not by a malicious third-party site.
- **Rule:** Any action that changes data on the server (e.g., saving settings, deleting a post, updating a user) **must** be protected by a nonce.
- **Workflow:**
    1.  **Create Nonce:** Use `wp_nonce_field()` in forms or `wp_create_nonce()` for URLs/AJAX.
    2.  **Verify Nonce:** Use `wp_verify_nonce()` to check the submitted nonce. If it fails, the request must be rejected.
- **Example:**
  ```php
  // In the form
  wp_nonce_field( 'my_plugin_save_settings_action', 'my_plugin_nonce_field' );

  // In the processing logic
  if ( ! isset( $_POST['my_plugin_nonce_field'] ) || ! wp_verify_nonce( $_POST['my_plugin_nonce_field'], 'my_plugin_save_settings_action' ) ) {
      exit( 'Invalid nonce.' );
  }
  ```

---

## 2. Data Validation & Sanitization

- **Purpose:** To clean and secure all incoming data before it is used in logic or saved to the database.
- **Rule:** **Never** use `$_POST`, `$_GET`, or `$_REQUEST` data directly. Always sanitize it first.
- **Common Sanitization Functions:**
    - `sanitize_text_field()`: For plain text input. Strips tags and newlines.
    - `sanitize_textarea_field()`: For textareas. Preserves newlines but strips other tags.
    - `sanitize_email()`: For email addresses.
    - `sanitize_key()`: For keys and slugs (lowercase, alphanumeric, dashes).
    - `absint()` or `(int)`: For positive integers.
    - `esc_url_raw()`: For URLs that will be stored in the database.

---

## 3. Data Escaping

- **Purpose:** To secure data before it is rendered in HTML, attributes, or JavaScript to prevent Cross-Site Scripting (XSS) attacks.
- **Rule:** **Always** escape data at the point of output.
- **Common Escaping Functions:**
    - `esc_html()`: For escaping data to be displayed inside an HTML element (e.g., `<div><?php echo esc_html( $title ); ?></div>`).
    - `esc_attr()`: For escaping data to be used inside an HTML attribute (e.g., `<input type="text" value="<?php echo esc_attr( $value ); ?>">`).
    - `esc_url()`: For escaping URLs to be used in `href` or `src` attributes.
    - `esc_js()`: For escaping text to be used inside inline JavaScript.
    - `wp_kses_post()`: For escaping rich text content that is allowed to contain a safe subset of HTML (e.g., post content).

---

## Database Security

- **Rule:** All custom database queries **must** use `$wpdb->prepare()` to prevent SQL injection. This method handles the proper escaping of parameters.
- **Forbidden:** Never build a query by concatenating variables directly into the SQL string.
- **Correct Usage:**
  ```php
  $user_id = 123;
  $status = 'active';
  $results = $wpdb->get_results(
      $wpdb->prepare(
          "SELECT * FROM {$wpdb->prefix}my_table WHERE user_id = %d AND status = %s",
          $user_id,
          $status
      )
  );
  ```

---

## File System Security

- **File Permissions:** Generated files and directories should have the most restrictive permissions possible.
- **File Uploads:**
    - **Never** trust the file name or MIME type sent by the browser.
    - Use `wp_check_filetype()` to validate file types on the server.
    - Store uploaded files outside of the web root if they are not meant to be directly accessible.
    - **Never** allow the upload of executable files (`.php`, `.sh`, etc.).
- **Path Traversal:** Sanitize all file paths to prevent directory traversal attacks (e.g., `../../..`). Use functions like `wp_normalize_path()`.

---

## Critical Failure Conditions

SquirrelForge must reject any code that:
- Lacks a capability check for a privileged action.
- Lacks nonce verification for a data-modifying action.
- Outputs unescaped data.
- Uses unsanitized input in a query or logic.
- Performs a raw SQL query with user input.
- Allows unrestricted file uploads.
