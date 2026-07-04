# SquirrelForge WordPress Optimize Performance Skill

## Purpose

This Skill defines the controlled workflow for identifying, prioritizing, implementing, and verifying WordPress performance improvements.

This skill treats optimization as a controlled engineering exercise. It establishes a baseline, locates measured bottlenecks, applies the smallest safe change, verifies the result, and preserves functional, security, accessibility, and data-integrity requirements.

---

## Trigger Conditions

Use this Skill when the request is to:

- A WordPress page, request, job, plugin, theme, or administration screen is slow.
- Database queries, remote requests, cron jobs, or background tasks consume excessive resources.
- Assets, images, blocks, or templates delay rendering.
- A deployment causes a measurable performance regression.

Do not use this Skill to make speculative changes when no performance objective, baseline, or reproducible symptom exists.

---

## Pipeline Execution for Performance Optimization

This skill implements a variation of the master `38_WORDPRESS/PIPELINE.md` to ensure performance improvements are measured, safe, and verified.

| Stage | Responsible Role(s) | Key Actions for this Skill |
|---|---|---|
| 1. Intent Analysis | `Role Manager` | Deconstruct the performance request (e.g., "speed up admin dashboard") into a structured task. |
| 2. Architecture Planning | `Project Architect`, `Performance Engineer` | Define the performance objective, scope, and high-level strategy. The `Performance Engineer` establishes baselines. Output: `Performance Optimization Plan`. |
| 3. Implementation Planning | `Performance Engineer` | Design the detailed optimization steps, including profiling, hypothesis formation, and specific code changes. |
| 4. Role & Task Routing | `Role Manager` | Consult the `ROLE-ROUTING-MATRIX.md` to produce the `WordPress Role Routing Decision` for the optimization. |
| 5. Code Generation | `PHP Engineer`, `JS Engineer`, `CSS Engineer`, `Database Engineer`, etc. | The assigned implementation engineer applies the optimization changes. |
| 6. Security Validation | `Security Engineer` | Audit the changes to ensure no security controls were weakened by the optimization. **(GATE)** |
| 7. Performance Validation | `Performance Engineer` | Re-measure the optimized code against the baseline to verify the improvement and check for regressions. **(GATE)** |
| 8. QA & Testing | `QA Engineer` | Execute a regression test plan to verify that no existing functionality was broken. **(GATE)** |
| 9. Documentation | `Documentation Engineer` | Update any developer or user documentation affected by operational behavior changes or new performance considerations. |
| 10. Release Preparation | `Release Engineer` | If the optimization is part of a release, package the changes following all release readiness checks. **(FINAL GATE)** |

---

## Agent Rules

1.  **Measure First**: Optimization must always begin with establishing a baseline and profiling to identify actual bottlenecks, not speculative changes.
2.  **Smallest Safe Change**: Apply optimizations incrementally. Each change should be the smallest possible to achieve a measurable improvement.
3.  **Preserve Controls**: Performance improvements must never weaken security, accessibility, or functional correctness without explicit, documented, and approved justification.
4.  **Verify After**: All optimized code must pass re-validation by the `Performance Engineer` (re-measurement) and `QA Engineer` (regression testing).
5.  **Gate Enforcement**: A `Fail` status from any validation role (`Security`, `Performance`, `QA`) or a `Blocked` status from the `Release Engineer` must halt the process and return the work to the appropriate implementation role for remediation.

---

## Rule

SquirrelForge must not approve a WordPress performance optimization unless it is supported by comparable measurements, preserves required behavior and controls, and includes verification and rollback evidence.
