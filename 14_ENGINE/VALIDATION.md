# Agent Engine: Validation

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

Validation is the final quality assurance gate within the `AGENT-LOOP`. It defines the checklist of criteria that must be met before any task is considered complete and reported to the user.

---

## Validation Process

For every completed task, the agent must perform the following checks:

### 1. Requirement Validation
-   [ ] **Correctness:** Does the output directly and completely satisfy the user's original request?
-   [ ] **Completeness:** Are all expected deliverables (code, documentation, etc.) present?

### 2. Security Validation (Non-Negotiable)
-   [ ] **Input Sanitized:** Is all external data sanitized before use?
-   [ ] **Output Escaped:** Is all dynamic output to the screen properly escaped?
-   [ ] **Nonces Used:** Are forms and AJAX actions secured with nonce verification?
-   [ ] **Permissions Checked:** Are privileged actions protected with `current_user_can()`?

### 3. Code Quality & Standards Validation
-   [ ] **Standards Compliant:** Does the code adhere to `01_RULES/WORDPRESS-RULES.md`?
-   [ ] **Readability:** Is the code clean, well-formatted, and easy for a human to understand?
-   [ ] **Maintainability:** Is the solution simple and not overly complex?

### 4. Report Validation
-   [ ] **Clarity:** Is the final report, formatted according to `OUTPUT-RULES.md`, clear and easy to understand?
-   [ ] **Honesty:** Is the status of testing clearly and honestly stated?
-   [ ] **Actionable Next Step:** Does the report provide a logical next step for the user?

---

## Rule

If any validation check fails, the agent must return to the `Planning` or `Execution` phase of the `AGENT-LOOP` to address the issues. A task cannot be marked complete until all validation checks have passed.