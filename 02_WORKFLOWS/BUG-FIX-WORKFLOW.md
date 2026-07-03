# Bug-Fix Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

This workflow defines the standard process SquirrelForge follows to identify, fix, and verify bugs in a WordPress project.

### Phase 1 — Understand & Reproduce

The most critical phase. A bug that cannot be reproduced cannot be reliably fixed.

1.  **Understand the Report:**
    -   What is the expected behavior?
    -   What is the actual, incorrect behavior?
2.  **Identify Reproduction Steps:**
    -   Determine the exact, step-by-step actions required to trigger the bug.
    -   Gather context: WordPress version, PHP version, relevant plugins/theme, user roles, and specific pages or posts involved.
3.  **Confirm Reproduction:** Follow the steps to confirm the bug is reproducible in the development environment.

**Deliverable:** A clear set of steps to reliably trigger the bug.

### Phase 2 — Isolate & Diagnose

Pinpoint the root cause of the issue.

1.  **Trace the Code:** Analyze the files and functions related to the buggy feature.
2.  **Form a Hypothesis:** Based on the code and the bug's behavior, form a hypothesis about the root cause (e.g., "The `save_post` action is firing too early," or "The input is not being sanitized correctly").
3.  **Debug:** Use debugging techniques (like enabling `WP_DEBUG` and checking logs) to confirm the hypothesis and isolate the specific lines of code responsible.

**Deliverable:** A confirmed root cause of the bug.

### Phase 3 — Implement the Fix

Develop and apply a targeted solution.

1.  **Design the Fix:** Create the smallest possible code change that resolves the root cause.
2.  **Adhere to Standards:** The fix must comply with all principles in `01_RULES/WORDPRESS-RULES.md`, especially concerning security and performance.
3.  **Apply the Change:** Implement the code change.

**Deliverable:** The implemented code fix.

### Phase 4 — Verify & Test

Ensure the fix is effective and does not cause unintended side effects.

1.  **Confirm the Fix:** Follow the original reproduction steps to verify that the bug no longer occurs.
2.  **Perform Regression Testing:** Test related functionality to ensure the fix has not introduced new bugs.
3.  **Check for Errors:** Ensure no new PHP errors, warnings, or notices are generated.

**Deliverable:** A verified and tested fix.

### Phase 5 — Report

Summarize the entire process for the user.

1.  **Bug Summary:** Briefly describe the original bug.
2.  **Root Cause:** Explain what was causing the issue.
3.  **Solution:** Describe the fix that was implemented.
4.  **Files Affected:** List all files that were modified.
5.  **Verification:** State that the fix was verified and how.

**Deliverable:** A comprehensive bug-fix report.