# Security Review Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

This workflow defines the standard process the `AGENT-SECURITY` follows to conduct a focused audit of a WordPress project for security vulnerabilities.

### Phase 1 — Scope & Context

1.  **Identify Target:** Determine the exact codebase to be reviewed (e.g., a specific plugin, theme, or feature branch).
2.  **Analyze Data Flow:** Trace all sources of external data (`$_GET`, `$_POST`, REST API endpoints, user-submitted content) and map where this data is used, stored, and displayed. This creates a "threat map" for the review.

**Deliverable:** A clear understanding of the attack surface.

### Phase 2 — Vulnerability Audit

Systematically review the codebase against the following checklist:

#### Access Control
-   [ ] Are all privileged actions (e.g., saving settings, deleting data) protected with a `current_user_can()` check?
-   [ ] Does the code follow the principle of least privilege?

#### Cross-Site Request Forgery (CSRF)
-   [ ] Are all admin forms and state-changing actions protected with nonce creation and `wp_verify_nonce()` checks?

#### Cross-Site Scripting (XSS)
-   [ ] Is all dynamic data properly escaped on output using functions like `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`?

#### Input Sanitization
-   [ ] Is all incoming data sanitized before being used in database queries, function calls, or file operations? (e.g., using `sanitize_text_field()`, `absint()`).

#### SQL Injection
-   [ ] Are all custom database queries using `$wpdb->prepare()`?

#### Secure Data Handling
-   [ ] Are secrets (API keys, passwords) stored securely and not hardcoded?
-   [ ] Are file uploads validated for type and size, and stored in a secure location?

**Deliverable:** A list of all identified vulnerabilities and their locations.

### Phase 3 — Reporting

1.  **Categorize Findings:** Classify each vulnerability by severity (Critical, High, Medium, Low).
2.  **Create Report:** For each finding, provide:
    -   A clear description of the vulnerability and its potential impact.
    -   The specific file path and line number.
    -   A code snippet demonstrating the vulnerability.
    -   A code snippet showing the recommended fix.
3.  **Provide Verdict:** Conclude with a final verdict: `Approved`, `Warning`, or `Failed`.

**Deliverable:** A comprehensive and actionable security report.