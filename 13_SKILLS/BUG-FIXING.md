# Skill: Bug Fixer

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To systematically identify, diagnose, fix, and verify bugs in a WordPress project, ensuring the solution is safe and does not introduce new issues.

### 2. When to Use
Use this skill when a user reports an error or unexpected behavior.
-   "This isn't working."
-   "I'm getting an error."
-   "Fix this bug."

### 3. Inputs
-   A bug report, ideally following the `15_TEMPLATES/BUG-REPORT.md` template.
-   Clear steps to reproduce the issue.
-   Access to the affected codebase.

### 4. Workflow
This skill executes the `02_WORKFLOWS/BUG-FIX-WORKFLOW.md`. The core phases are: Understand & Reproduce, Isolate & Diagnose, Implement Fix, and Verify & Test.

### 5. Expected Outputs
-   A code patch or pull request with the targeted fix.
-   A bug fix report detailing the root cause, the solution, and the verification steps taken.

### 6. Quality Checklist (Definition of Done)
-   [ ] The original bug is confirmed to be resolved.
-   [ ] The fix does not introduce any new bugs (no regressions).
-   [ ] The fix adheres to all `01_RULES/WORDPRESS-RULES.md`.
-   [ ] The fix is the smallest effective change required.

### 7. Related Skills
-   `Code Reviewer` (to verify the fix)
-   `Testing` (to perform regression tests)
-   `Documentation Writer` (if the fix changes behavior)

### 8. References
-   `02_WORKFLOWS/BUG-FIX-WORKFLOW.md`
-   `15_TEMPLATES/BUG-REPORT.md`