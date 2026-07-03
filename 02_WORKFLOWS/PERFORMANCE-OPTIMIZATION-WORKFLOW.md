# Performance Optimization Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

This workflow defines the standard process the `AGENT-PERFORMANCE` follows to analyze, optimize, and verify the performance of a WordPress project.

### Phase 1 — Analyze & Profile

1.  **Identify Target:** Determine the scope of the optimization (e.g., the entire site, a specific plugin, a slow page).
2.  **Establish Baseline:** If possible, measure the current performance (e.g., page load time, number of queries, memory usage) to have a baseline for comparison.
3.  **Static Code Analysis:** Scan the codebase for common performance anti-patterns.

**Deliverable:** A performance baseline and a list of potential areas of concern.

### Phase 2 — Identify Bottlenecks

1.  **Query Analysis:** Look for inefficient database queries, especially queries inside loops (N+1 problem).
2.  **Asset Analysis:** Check for large, unminified, or un-conditionally loaded CSS and JavaScript files.
3.  **Caching Analysis:** Identify expensive operations (e.g., complex calculations, remote API calls) that are not being cached using the Transients API or an object cache.

**Deliverable:** A prioritized list of specific performance bottlenecks.

### Phase 3 — Plan Optimizations

1.  **Design Solutions:** For each bottleneck, design the most effective and least disruptive fix.
2.  **Prioritize:** Rank the optimizations based on their expected impact and ease of implementation.

**Deliverable:** A step-by-step optimization plan.

### Phase 4 — Implement Optimizations

1.  **Apply Changes:** Implement the planned optimizations one by one.
2.  **Adhere to Standards:** Ensure all changes follow project coding standards and do not introduce new bugs.

**Deliverable:** The implemented code optimizations.

### Phase 5 — Verify & Measure

1.  **Functional Testing:** Perform regression testing to ensure the optimizations have not broken any functionality.
2.  **Measure Improvement:** Re-run the performance measurements from Phase 1 and compare them against the baseline to quantify the improvement.

**Deliverable:** A verified fix with a report on the performance gains.

### Phase 6 — Report

1.  **Summarize Findings:** Detail the bottlenecks that were identified.
2.  **Describe Solutions:** Explain the optimizations that were implemented.
3.  **Present Results:** Show the before-and-after performance metrics.

**Deliverable:** A comprehensive performance optimization report.