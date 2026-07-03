# Skill: Create REST Endpoint

## Purpose

This document defines the end-to-end process SquirrelForge must follow to create a new, secure, and standards-compliant custom REST API endpoint in WordPress.

## Core Principle

Creating a REST endpoint is a security-critical task. The process must prioritize authorization and data validation to ensure the endpoint is robust and safe from misuse.

---

## Required Inputs

- A user request describing the endpoint's purpose, the data it will handle, the required HTTP methods, and the necessary permissions.

## Expected Outputs

- The complete PHP code for registering and handling the REST API endpoint, typically within a plugin.
- A final report detailing the endpoint's structure, security model, and testing plan.

---

## Workflow

This skill follows the master `PIPELINE.md`.

1.  **Intent Analysis**:
    -   Deconstruct the request: `Task: create_rest_endpoint, Route: /my-plugin/v1/submit, Method: POST, Permissions: administrator`.

2.  **Knowledge Selection**:
    -   The `Knowledge Manager` selects `REST-API.md`, `SECURITY.md`, and `CODING-STANDARDS.md`.

3.  **Requirements Builder**:
    -   Generate functional requirements (e.g., "Must accept a 'name' and 'email' parameter," "Must return a JSON object with a success status").
    -   Generate security requirements (e.g., "Must only be accessible to users with the 'manage_options' capability").

4.  **Architecture Planning**:
    -   Design the endpoint's architecture: define the namespace, route, methods, the `permission_callback` function, and the schema for all arguments (`args`) including validation and sanitization callbacks.

5.  **Implementation Planning**:
    -   Break the architecture down into a concrete plan (e.g., "1. Create a function to hook into `rest_api_init`. 2. Inside, call `register_rest_route`. 3. Implement the `permission_callback` function. 4. Implement the main endpoint `callback` function...").

6.  **Code Generation**:
    -   Execute the plan, generating the PHP code for the endpoint.

7.  **Security Validation**:
    -   The `Security Validator` scans the code, paying special attention to the presence and correctness of the `permission_callback` and the use of sanitized data from the `WP_REST_Request` object within the main callback.
    -   **Gate**: The pipeline halts if the `permission_callback` is missing or insufficient for a data-modifying endpoint.

8.  **Standards Validation**:
    -   The `Standards Validator` checks the code for compliance with all loaded standards.

9.  **Testing Plan**:
    -   The `Testing Planner` generates a `TESTING.md` file with a checklist for manual verification (e.g., "Test the endpoint with an authorized user," "Test with a logged-out user," "Test with missing parameters").

10. **Code Review**:
    -   The `Code Reviewer` performs a final logical pass, ensuring the endpoint returns correctly formatted `WP_REST_Response` or `WP_Error` objects.

11. **Documentation Update**:
    -   The `Documentation Generator` updates the `README.md` to include details about the new endpoint, its parameters, and an example response.

12. **Final Approval**:
    -   The `WordPress Manager` reviews all reports. If all gates pass, it approves the build and generates the final report.

---

## Agent Rules

1.  **Mandatory `permission_callback`**: The agent must always generate a `permission_callback` for every endpoint. For public, read-only endpoints, it can be `__return_true`, but it must be explicitly defined.
2.  **Define `args` for Validation**: The agent must generate an `args` array to define, validate, and sanitize all expected parameters.
3.  **Use `WP_REST_Response` and `WP_Error`**: Generated callbacks must always `return` an instance of `WP_REST_Response` or `WP_Error`, never `echo` and `die`.
4.  **Use Versioned Namespaces**: All generated routes must be placed within a versioned namespace (e.g., `my-plugin/v1`).