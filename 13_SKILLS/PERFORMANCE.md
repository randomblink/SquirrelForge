# Skill: Performance Optimizer

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To analyze a WordPress project for performance bottlenecks and to recommend or implement optimizations to improve speed and efficiency.

### 2. When to Use
Use this skill for requests related to site speed and optimization.
-   "My site is slow, can you help?"
-   "Optimize this plugin for performance."
-   "How can I make this theme faster?"

### 3. Inputs
-   The full path to the project codebase.
-   (Optional) The specific URL or feature that is performing poorly.

### 4. Workflow
This skill executes the `02_WORKFLOWS/PERFORMANCE-OPTIMIZATION-WORKFLOW.md`. It analyzes the code for common performance issues such as inefficient database queries, unoptimized asset loading, and lack of caching.

### 5. Expected Outputs
-   A **Performance Report** detailing identified bottlenecks.
-   A list of recommended or implemented optimizations, such as:
    -   Refactored database queries to avoid N+1 problems.
    -   Implementation of the Transients API for caching expensive operations.
    -   Recommendations for conditional asset loading.
    -   Suggestions for image optimization or server-level caching.

### 6. Quality Checklist (Definition of Done)
-   [ ] The analysis identifies specific, actionable performance issues.
-   [ ] The proposed optimizations are safe and do not alter core functionality.
-   [ ] The report clearly explains why each optimization will improve performance.

### 7. Related Skills
-   `Code Reviewer` (performance is a key part of a general review)
-   `Bug Fixing` (performance issues are often treated as bugs)

### 8. References
-   `02_WORKFLOWS/PERFORMANCE-OPTIMIZATION-WORKFLOW.md`
-   `01_RULES/WORDPRESS-RULES.md`