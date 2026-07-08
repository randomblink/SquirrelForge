Status: Stable

---
# Skill: Create REST Endpoint

## Purpose

This document defines the process for creating a new, secure, and standards-compliant custom REST API endpoint in WordPress by orchestrating the specialist roles according to the master `PIPELINE.md`.

## Core Principle

Creating a REST endpoint is a security-critical task. The process must prioritize authorization and data validation to ensure the endpoint is robust and safe from misuse.

---

## Pipeline Execution for Creating a REST Endpoint

This skill implements a focused subset of the master `38_WORDPRESS/PIPELINE.md`.

| Stage | Responsible Role(s) | Key Actions for this Skill |
|---|---|---|
| 1. Triage & Planning | `Role Manager`, `REST Engineer` | The `Role Manager` deconstructs the request. The `REST Engineer` plans the endpoint's namespace, route, methods, and schema. |
| 2. Code Generation | `PHP Engineer` | Write the PHP code for the `permission_callback` and main `callback` functions as defined by the `REST Engineer`. |
| 3. Security Validation | `Security Engineer` | Audit the endpoint, focusing on the `permission_callback`, argument validation, and sanitization. **(GATE)** |
| 4. Performance Validation | `Performance Engineer` | If the endpoint is high-traffic or performs heavy queries, analyze its performance impact. **(GATE)** |
| 5. QA & Testing | `QA Engineer` | Execute a test plan to verify the endpoint's functionality, error handling, and security. **(GATE)** |
| 6. Documentation | `Documentation Engineer` | Document the endpoint's route, methods, parameters, and example responses. |

---

## Agent Rules

1.  **Mandatory `permission_callback`**: The agent must always generate a `permission_callback` for every endpoint. For public, read-only endpoints, it can be `__return_true`, but it must be explicitly defined.
2.  **Define `args` for Validation**: The agent must generate an `args` array to define, validate, and sanitize all expected parameters.
3.  **Use `WP_REST_Response` and `WP_Error`**: Generated callbacks must always `return` an instance of `WP_REST_Response` or `WP_Error`, never `echo` and `die()`.
4.  **Use Versioned Namespaces**: All generated routes must be placed within a versioned namespace (e.g., `my-plugin/v1`).
## Rule

A WordPress REST endpoint must define its route, schema, permission callback, validation, sanitization, response contract, tests, and documentation before it is considered complete.
