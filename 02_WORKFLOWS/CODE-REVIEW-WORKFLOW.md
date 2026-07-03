# Code-Review Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

This workflow defines the standard process SquirrelForge follows when conducting a code review. It ensures that all code is analyzed against a consistent set of quality, security, and performance standards.

### Phase 1 — Understand Context

1.  **Identify Objective:** What is the purpose of the code being reviewed? Is it a new feature, a bug fix, or a refactor?
2.  **Identify Scope:** Which files and functions are included in this review?

**Deliverable:** A clear understanding of the code's purpose.

### Phase 2 — Security Audit (Top Priority)

Review the code against the non-negotiable security rules.

-   [ ] **Input Sanitization:** Is all external data (`$_GET`, `$_POST`, user input) sanitized with functions like `sanitize_text_field()` before use?
-   [ ] **Output Escaping:** Is all data escaped with functions like `esc_html()` or `esc_attr()` before being rendered?
-   [ ] **Nonces:** Are admin forms and actions protected with `wp_verify_nonce()`?
-   [ ] **Capability Checks:** Are privileged actions guarded by `current_user_can()`?
-   [ ] **Database Queries:** Are all custom database queries using `$wpdb->prepare()`?

**Deliverable:** A list of any identified security vulnerabilities.

### Phase 3 — Performance & Best Practices Audit

Review the code for efficiency and adherence to WordPress standards.

-   [ ] **Query Efficiency:** Are there any database queries inside loops (N+1 problem)?
-   [ ] **Asset Loading:** Are scripts and styles enqueued correctly using the proper hooks (`wp_enqueue_scripts`, `admin_enqueue_scripts`)? Are they loaded conditionally?
-   [ ] **Caching:** Could any slow or repetitive operations benefit from the Transients API?
-   [ ] **The WordPress Way:** Does the code leverage core APIs (`WP_Query`, Settings API, etc.) where appropriate?

**Deliverable:** A list of performance and best-practice recommendations.

### Phase 4 — Maintainability & Readability Audit

Review the code for long-term health and clarity.

-   [ ] **Coding Standards:** Does the code adhere to WordPress coding standards (naming conventions, formatting)?
-   [ ] **Readability:** Is the code easy to understand? Are variable and function names clear and descriptive?
-   [ ] **Complexity:** Is the logic overly complex? Can it be simplified?
-   [ ] **Documentation:** Is the code well-documented with PHPDoc blocks and inline comments where necessary?

**Deliverable:** A list of recommendations for improving code clarity and maintainability.

### Phase 5 — Synthesize & Report

Compile the findings into a clear, actionable report.

1.  **Structure the Report:** Organize feedback into three categories:
    -   **Critical Issues:** (e.g., Security vulnerabilities)
    -   **Recommendations:** (e.g., Performance improvements, best-practice changes)
    -   **Minor Suggestions:** (e.g., Typos, formatting tweaks)
2.  **Provide a Verdict:** Conclude with a summary recommendation: "Approved," "Approved with Revisions," or "Requires Changes."
3.  **Offer a Solution:** If significant issues are found, provide a refactored code example that addresses them.

**Deliverable:** A comprehensive code review report.