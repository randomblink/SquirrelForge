Status: Stable

---
# SquirrelForge WordPress Create REST Endpoint Skill

## Purpose

This Skill defines the controlled workflow for creating a WordPress REST API endpoint.

It coordinates requirements, knowledge selection, architecture, role routing, specialist implementation, security, performance validation, QA, documentation, and release review.

---

## Trigger Conditions

Use this Skill when the request is to:

- create a new custom REST endpoint
- add a method to an existing REST route
- expose WordPress data via a controlled API

Do not use this Skill when the task is only to:

- create a full plugin
- create a shortcode
- create a simple AJAX handler

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/REST-API.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`
- `33_WORDPRESS_ROLES/REST-ENGINEER.md`
- `33_WORDPRESS_ROLES/PHP-ENGINEER.md`
- `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`
- `33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`
- `33_WORDPRESS_ROLES/QA-ENGINEER.md`
- `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`

---

## Required Input

```text
REST Endpoint Creation Request

Purpose:
Consumers:
Authentication Context:
Required Endpoints:
Data Requirements:
Permission Requirements:
Performance Constraints:
Compatibility Requirements:
Known Constraints:
```

### Workflow

#### Stage 1 — REST Architecture

Use `REST Engineer` to produce a `REST Engineering Report` (as a plan). The plan must define the namespace, routes, methods, arguments, validation, sanitization, permission callbacks, and response contracts.

#### Stage 2 — Role Routing

Use `33_WORDPRESS_ROLES/ROLE-MANAGER.md` and `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md` to produce the `WordPress Role Routing Decision`.

#### Stage 3 — Implementation

Use `PHP Engineer` to implement the endpoint registration and callback logic according to the `REST Engineering Report`.

#### Stage 4 — Security Validation

Use `Security Engineer` to audit the endpoint, focusing on the `permission_callback`, argument validation, and sanitization. This is a **blocking gate**.

#### Stage 5 — Performance Validation

Use `Performance Engineer` to analyze the performance impact if the endpoint is high-traffic or performs heavy queries. This is a **blocking gate** when required.

#### Stage 6 — QA Validation

Use `QA Engineer` to execute a test plan verifying the endpoint's functionality, error handling, and security. This is a **blocking gate**.

#### Stage 7 — Documentation

Use `Documentation Engineer` to document the endpoint's route, methods, parameters, and example responses.

### Validation Commands

These commands support Stage 4 (Security Validation) through Stage 6 (QA Validation). Exact availability depends on the target project's setup and installed tooling, not on SquirrelForge itself. Run every command from the target plugin's own repository.

`<plugin-directory>` is the plugin's root directory. `<namespace>/<route>` is the registered REST route (for example `my-plugin/v1/submit`). `<site-url>` is the target WordPress site's base URL.

Never place real credentials, nonces, or application passwords in these commands. Use environment variables for authentication values instead — production credentials pasted directly into a command are written to shell history.

#### PHP Syntax Validation

Shell commands for Terminal

```sh
find <plugin-directory> -name "*.php" -print0 | xargs -0 -n1 php -l
```

#### Focused PHPUnit REST Tests, When Available

`<RestTestClassName>` is the PHPUnit test class covering the endpoint. `vendor/bin/phpunit` only exists when PHPUnit is installed as a dev dependency of the target plugin.

Shell commands for Terminal

```sh
if [ -x <plugin-directory>/vendor/bin/phpunit ]; then
  (cd <plugin-directory> && vendor/bin/phpunit tests/<RestTestClassName>.php)
else
  echo "PHPUnit not found in <plugin-directory>/vendor/bin -- skipping REST tests."
fi
```

#### List or Inspect Routes

When WP-CLI is available, `wp eval` against WordPress's own REST server lists registered routes without requiring an extra WP-CLI package:

Shell commands for Terminal

```sh
if command -v wp >/dev/null 2>&1; then
  wp eval 'echo wp_json_encode( array_keys( rest_get_server()->get_routes() ) );' --path=<wordpress-root>
else
  echo "WP-CLI not found -- inspect routes via the REST index instead (see below)."
fi
```

Without WP-CLI, the REST index itself lists namespaces and routes:

Shell commands for Terminal

```sh
curl -s https://<site-url>/wp-json/<namespace>
```

#### Authenticated Request Example

Set credentials as environment variables first; `<placeholder-username>` and `<placeholder-application-password>` are placeholders, never real values.

Shell commands for Terminal

```sh
export WP_REST_USER="<placeholder-username>"
export WP_REST_APP_PASSWORD="<placeholder-application-password>"
curl -i -u "${WP_REST_USER}:${WP_REST_APP_PASSWORD}" \
  https://<site-url>/wp-json/<namespace>/<route>
```

#### Unauthenticated Request, to Verify Authorization Rejection

Confirm the response is `401` or `403` for any endpoint whose `permission_callback` requires authentication.

Shell commands for Terminal

```sh
curl -i https://<site-url>/wp-json/<namespace>/<route>
```

#### Git Checks

These are read-only checks; they do not modify the working tree.

Shell commands for Terminal

```sh
git diff --check
git status --short
```

### REST Endpoint Final Report

Produce a final report summarizing the status of all stages.

---

## Completion Criteria

This Skill is complete only when:

- the namespace, route, methods, and argument schema are defined;
- a `permission_callback` is defined for every registered route;
- Stage 4 (Security Validation) has passed;
- Stage 5 (Performance Validation) has passed or was not required;
- Stage 6 (QA Validation) has passed;
- Stage 7 (Documentation) is complete;
- and the REST Endpoint Final Report is produced.

---

## Rule

1.  **Mandatory `permission_callback`**: The agent must always generate a `permission_callback` for every endpoint. For public, read-only endpoints, it can be `__return_true`, but it must be explicitly defined.
2.  **Define `args` for Validation**: The agent must generate an `args` array to define, validate, and sanitize all expected parameters.
3.  **Use `WP_REST_Response` and `WP_Error`**: Generated callbacks must always `return` an instance of `WP_REST_Response` or `WP_Error`, never `echo` and `die()`.
4.  **Use Versioned Namespaces**: All generated routes must be placed within a versioned namespace (e.g., `my-plugin/v1`).
