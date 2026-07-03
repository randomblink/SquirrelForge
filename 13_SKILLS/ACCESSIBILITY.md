# Skill: Accessibility Reviewer

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To analyze a WordPress project for common accessibility issues, ensuring it is usable by people with disabilities and compliant with WCAG standards.

### 2. When to Use
Use this skill for requests related to web accessibility.
-   "Make my theme accessible."
-   "Check my site for WCAG compliance."
-   "Review this page for accessibility issues."

### 3. Inputs
-   The full path to the project codebase.
-   (Optional) A specific URL or component to review.

### 4. Workflow
This skill executes the `02_WORKFLOWS/ACCESSIBILITY-REVIEW-WORKFLOW.md`. It checks for issues such as missing alt text, poor color contrast, lack of keyboard navigation, and improper use of ARIA roles.

### 5. Expected Outputs
-   An **Accessibility Report** detailing:
    -   A list of all identified accessibility barriers, categorized by impact (Critical, Serious, Moderate).
    -   Clear explanations of why each issue is a barrier.
    -   Code examples and recommendations for how to fix each issue.

### 6. Quality Checklist (Definition of Done)
-   [ ] The review covers key WCAG principles (Perceivable, Operable, Understandable, Robust).
-   [ ] Each finding is explained with reference to its impact on users with disabilities.
-   [ ] The provided recommendations are clear and actionable.

### 7. Related Skills
-   `Theme Developer` (accessibility is a core part of theme development)
-   `Code Reviewer` (accessibility is a component of a general review)

### 8. References
-   `02_WORKFLOWS/ACCESSIBILITY-REVIEW-WORKFLOW.md`
-   WCAG 2.1 Guidelines
-   WordPress Accessibility Handbook