Status: Stable

---
# WordPress: Validation Checklist

Version: 1.0.0
Status: Stable
Owner: WordPress Maintainers
Depends On: `14_ENGINE/VALIDATION.md`
Used By: `14_ENGINE/VALIDATION.md`
Last Updated: 2026-07-04

## Purpose

This document defines the standard validation checklist for tasks within the WordPress domain. It is loaded by the `14_ENGINE/VALIDATION.md` component when a task is identified as WordPress-related. The agent must provide evidence that each applicable check has passed before the task can be marked `Complete`.

---

## Checklist

### 1. Requirement Validation

-   [ ] **Correctness:** Does the output directly and completely satisfy the user's original request? Evidence: Link to final deliverable and a summary of how it meets the goal.
-   [ ] **Completeness:** Are all expected deliverables (code, documentation, etc.) present? Evidence: Manifest of all created/modified files.

### 2. Security Validation (Non-Negotiable)

-   [ ] **Input Sanitized:** Is all external data sanitized before use? Evidence: Security scan report or code snippets showing sanitization functions.
-   [ ] **Output Escaped:** Is all dynamic output to the screen properly escaped? Evidence: Security scan report or code snippets showing escaping functions.
-   [ ] **Nonces Used:** Are forms and AJAX actions secured with nonce verification? Evidence: Code snippets showing `wp_create_nonce` and `wp_verify_nonce`.
-   [ ] **Permissions Checked:** Are privileged actions protected with `current_user_can()`? Evidence: Code snippets showing capability checks.

### 3. Code Quality & Standards Validation

-   [ ] **Standards Compliant:** Does the code adhere to `01_RULES/WORDPRESS-RULES.md`? Evidence: PHPCS linter report with the `WordPress-Core` ruleset.
-   [ ] **Readability:** Is the code clean, well-formatted, and easy for a human to understand? Evidence: Code review report or self-assessment.
-   [ ] **Maintainability:** Is the solution simple and not overly complex? Evidence: Architectural decision record or self-assessment.

### 4. Report Validation

-   [ ] **Clarity:** Is the final report, formatted according to `14_ENGINE/OUTPUT-RULES.md`, clear and easy to understand?
-   [ ] **Honesty:** Is the status of testing and validation clearly and honestly stated?
-   [ ] **Actionable Next Step:** Does the report provide a logical next step for the user?

## Rule

Validation must be explicit, reproducible, and tied to the selected WordPress workflow.
