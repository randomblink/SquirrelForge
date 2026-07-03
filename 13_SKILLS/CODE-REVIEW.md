# Skill: Code Reviewer

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To perform a comprehensive analysis of a given piece of WordPress code, evaluating it against established best practices for security, performance, maintainability, and standards compliance.

### 2. When to Use
Use this skill when asked to review or analyze code.
-   "Review this code for me."
-   "Is this code secure?"
-   "Can you check if this follows WordPress standards?"
-   "Find any problems in this function."

### 3. Inputs
-   `file_path` (string): The full path to the file containing the code for context.
-   `code_snippet` (string): The specific block of code to be reviewed.

### 4. Workflow
This skill executes the `02_WORKFLOWS/CODE-REVIEW-WORKFLOW.md`. It systematically checks the code against the security, performance, and maintainability checklists defined in the workflow.

### 5. Expected Outputs
-   A structured Markdown report containing:
    -   **Security Analysis:** A list of any identified vulnerabilities.
    -   **Performance Analysis:** A list of any performance anti-patterns.
    -   **Maintainability & Best Practices:** A list of recommendations for clarity and standards.
    -   **Final Verdict:** A clear recommendation ("Approved," "Approved with Revisions," or "Requires Changes").
    -   **Refactored Code:** A corrected version of the code if significant issues were found.

### 6. Quality Checklist (Definition of Done)
-   [ ] The review explicitly checks for security, performance, and maintainability.
-   [ ] The findings are clear, specific, and actionable.
-   [ ] The final verdict is unambiguous.
-   [ ] A corrected code example is provided for any critical issues.

### 7. Related Skills
-   `Bug Fixing` (often follows a code review that finds bugs)
-   `Security Auditor` (a more specialized version of this skill)

### 8. References
-   `02_WORKFLOWS/CODE-REVIEW-WORKFLOW.md`
-   `01_RULES/WORDPRESS-RULES.md`