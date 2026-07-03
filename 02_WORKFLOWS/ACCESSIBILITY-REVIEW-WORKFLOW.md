# Accessibility Review Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose
This workflow defines the standard process the `AGENT-ACCESSIBILITY` follows to audit a WordPress project for compliance with WCAG (Web Content Accessibility Guidelines).

### Phase 1 — Scope & Automated Scan
1.  **Identify Target:** Determine the scope of the review (e.g., a specific page, a user flow, the entire theme).
2.  **Automated Scan:** Run an automated accessibility checker (like WAVE or Axe) to catch low-hanging-fruit issues (e.g., missing alt text, low contrast).

**Deliverable:** A list of initial findings from the automated scan.

### Phase 2 — Manual Verification (Keyboard Navigation)
1.  **Keyboard-Only Navigation:** Tab through all interactive elements on the page.
    -   [ ] Is every interactive element (links, buttons, form fields) reachable?
    -   [ ] Is the focus order logical and predictable?
    -   [ ] Is the focus indicator always visible?
    -   [ ] Can all interactions be completed using only the keyboard (e.g., opening menus, submitting forms)?

**Deliverable:** A report on keyboard navigation issues.

### Phase 3 — Manual Verification (Content & Semantics)
1.  **Semantic HTML:** Inspect the page structure.
    -   [ ] Is the heading structure (`<h1>` through `<h6>`) logical and hierarchical?
    -   [ ] Are HTML5 landmark elements (`<header>`, `<nav>`, `<main>`, `<footer>`) used correctly?
    -   [ ] Are lists used for list content?
2.  **Content:** Review page content.
    -   [ ] Do all meaningful images have descriptive `alt` text?
    -   [ ] Is link text descriptive (i.e., avoids "click here")?
    -   [ ] Is color used as the only means of conveying information?
3.  **Forms:** Check all forms.
    -   [ ] Does every form input have a corresponding `<label>`?

**Deliverable:** A report on content and semantic structure issues.

### Phase 4 — Reporting
1.  **Consolidate Findings:** Combine the results from the automated and manual tests.
2.  **Categorize Issues:** Classify each issue by its impact on users (Critical, Serious, Moderate).
3.  **Provide Recommendations:** For each issue, provide:
    -   A clear description of the problem.
    -   An explanation of why it's an accessibility barrier.
    -   A code snippet or clear instructions on how to fix it.

**Deliverable:** A comprehensive and actionable accessibility report.