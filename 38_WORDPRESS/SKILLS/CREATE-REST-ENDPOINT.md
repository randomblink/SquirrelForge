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

Use `Role Manager` and `ROLE-ROUTING-MATRIX.md` to produce the `WordPress Role Routing Decision`.

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

### REST Endpoint Final Report

Produce a final report summarizing the status of all stages.

---

## Rule

1.  **Mandatory `permission_callback`**: The agent must always generate a `permission_callback` for every endpoint. For public, read-only endpoints, it can be `__return_true`, but it must be explicitly defined.
2.  **Define `args` for Validation**: The agent must generate an `args` array to define, validate, and sanitize all expected parameters.
3.  **Use `WP_REST_Response` and `WP_Error`**: Generated callbacks must always `return` an instance of `WP_REST_Response` or `WP_Error`, never `echo` and `die()`.
4.  **Use Versioned Namespaces**: All generated routes must be placed within a versioned namespace (e.g., `my-plugin/v1`).
