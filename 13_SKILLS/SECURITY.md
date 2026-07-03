# Skill: Security Auditor

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To conduct a focused and in-depth security audit of a WordPress project, identifying potential vulnerabilities and providing actionable recommendations for remediation.

### 2. When to Use
Use this skill for explicit security-related requests.
-   "Audit my plugin for security vulnerabilities."
-   "Is my theme secure?"
-   "Scan my project for security issues."

### 3. Inputs
-   The full path to the project codebase to be audited.
-   (Optional) Specific areas of concern (e.g., "Focus on the form submission handling").

### 4. Workflow
This skill executes the `02_WORKFLOWS/SECURITY-REVIEW-WORKFLOW.md`. It systematically scans the codebase for common WordPress vulnerabilities like XSS, CSRF, SQL Injection, and improper access control.

### 5. Expected Outputs
-   A structured **Security Report** detailing:
    -   A list of all identified vulnerabilities, categorized by severity (Critical, High, Medium, Low).
    -   Clear descriptions of each vulnerability and its potential impact.
    -   Specific file paths and line numbers for each finding.
    -   Code examples demonstrating how to fix each vulnerability.

### 6. Quality Checklist (Definition of Done)
-   [ ] The audit covers input sanitization, output escaping, nonce verification, and capability checks.
-   [ ] Each finding is clearly explained and actionable.
-   [ ] Remediation advice is provided for all critical and high-severity findings.

### 7. Related Skills
-   `Code Reviewer` (a less specialized version of this skill)
-   `Bug Fixing` (to implement the recommended fixes)

### 8. References
-   `02_WORKFLOWS/SECURITY-REVIEW-WORKFLOW.md`
-   `01_RULES/WORDPRESS-RULES.md`
-   OWASP Top Ten