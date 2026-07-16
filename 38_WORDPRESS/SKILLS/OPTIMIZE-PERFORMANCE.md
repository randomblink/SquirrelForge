Status: Stable

---
# SquirrelForge WordPress Optimize Performance Skill

## Purpose

This Skill defines the controlled workflow for diagnosing and improving WordPress performance.

Performance optimization must begin with measurement, identify confirmed bottlenecks, apply targeted changes through the responsible specialist role, and validate results through remeasurement and regression testing.

Optimization must not weaken security, accessibility, compatibility, data integrity, or functional correctness.

---

## Trigger Conditions

Use this Skill when the request is to:

- improve WordPress performance
- reduce page-generation time
- reduce database query cost
- reduce REST API latency
- optimize AJAX behavior
- optimize cron workloads
- reduce PHP execution cost
- reduce JavaScript execution cost
- reduce CSS or asset delivery cost
- improve Block Editor performance
- investigate a confirmed or suspected performance problem

Do not use this Skill for general refactoring unless performance is the primary goal.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/KNOWLEDGE/DATABASE.md` when database behavior is involved
- `38_WORDPRESS/KNOWLEDGE/REST-API.md` when REST behavior is involved
- `38_WORDPRESS/KNOWLEDGE/CRON.md` when scheduled work is involved
- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md` when editor behavior is involved
- `38_WORDPRESS/KNOWLEDGE/THEME-HANDBOOK.md` when the target is a theme
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- applicable standards in `38_WORDPRESS/STANDARDS/`
- `38_WORDPRESS/ROLES/ROLE-MANAGER.md`
- `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`
- `38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md`
- applicable implementation Engineer roles
- `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md` when security controls may be affected
- `38_WORDPRESS/ROLES/QA-ENGINEER.md`
- `38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md` when operational behavior changes

---

## Required Input

```text
Performance Optimization Request

Project:
Project Type:
Component:
Observed Problem:
Affected Users:
Affected Environment:
Performance Target:
Traffic Expectations:
Data Volume:
Known Bottlenecks:
Available Measurements:
Recent Changes:
Security Constraints:
Compatibility Requirements:
Known Risks:
```

If the performance problem cannot be reproduced or measured, the Skill must distinguish investigation from confirmed optimization work.

---

## Workflow

### Stage 1 — Performance Scope

Define:

```text
Performance Scope

Project:
Component:
Scenario:
Affected Environment:
Expected Load:
Expected Data Volume:
Observed Problem:
Target Metric:
Security Constraints:
Compatibility Constraints:
Out of Scope:
```

### Stage 2 — Role Routing

Use:

- `38_WORDPRESS/ROLES/ROLE-MANAGER.md`
- `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`

Standard route:

```text
Role Manager
↓
Performance Engineer
↓
Responsible Implementation Engineer
↓
Performance Engineer Revalidation
↓
Security Engineer when security controls are affected
↓
QA Engineer
↓
Documentation Engineer when operational behavior changes
↓
Release Engineer when part of a release
```

Possible implementation owners:

- PHP Engineer
- Database Engineer
- REST Engineer
- JavaScript Engineer
- CSS Engineer
- Block Engineer

Add Theme Architect or Plugin Architect when the optimization requires template, block, or structural boundary changes rather than a bounded implementation fix.

Produce:

```text
WordPress Role Routing Decision

Task:
Selected Skill: OPTIMIZE-PERFORMANCE
Project Type:
Complexity:
Required Roles:
Optional Roles:
Role Sequence:
Required Gates:
Conditional Gates:
Expected Reports:
Known Risks:
Routing Status:
```

### Stage 3 — Baseline Measurement

The Performance Engineer must establish the baseline before optimization whenever practical.

Record:

```text
Performance Baseline

Metric:
Environment:
Scenario:
Traffic Level:
Data Volume:
Baseline Result:
Measurement Method:
Number of Runs:
Variance:
Limitations:
```

Possible metrics include:

- response time
- server execution time
- query count
- slow query duration
- memory use
- REST latency
- response size
- request count
- JavaScript execution time
- bundle size
- stylesheet size
- cron duration
- batch duration

### Stage 4 — Bottleneck Identification

Identify the confirmed bottleneck.

Produce:

```text
Performance Bottleneck Record

Component:
Scenario:
Evidence:
Metric:
Baseline:
Likely Cause:
Confirmed Cause:
Responsible Role:
Expected Improvement:
Validation Method:
```

Do not optimize unrelated code because it appears inefficient.

### Stage 5 — Optimization Plan

Produce:

```text
Performance Optimization Plan

Problem:
Confirmed Bottleneck:
Responsible Engineer:
Proposed Change:
Files Affected:
Expected Impact:
Security Impact:
Compatibility Impact:
Accessibility Impact:
Regression Risk:
Measurement Plan:
Rollback Plan:
```

### Stage 6 — Targeted Implementation

The responsible Engineer applies the smallest effective optimization.

Examples include:

- query restructuring
- removing duplicate queries
- pagination
- batching
- caching
- cache invalidation
- reducing repeated calculations
- reducing unnecessary hooks
- conditional asset loading
- reducing repeated REST requests
- reducing polling
- reducing unnecessary JavaScript dependencies
- reducing unnecessary CSS delivery
- reducing Block Editor re-renders

Each implementation role must produce its normal implementation report.

### Stage 7 — Cache Validation

When caching is introduced, define:

```text
Cache Plan

Data:
Cache Type:
Cache Key:
Lifetime:
Invalidation Trigger:
Fallback:
Failure Behavior:
```

Caching without an invalidation strategy is incomplete.

### Stage 8 — Performance Revalidation

The Performance Engineer must remeasure using a comparable environment and scenario.

Record:

```text
Performance Result

Metric:
Environment:
Scenario:
Baseline:
Target:
Final Result:
Absolute Difference:
Percentage Difference:
Measurement Method:
Limitations:
```

If measurement conditions changed materially, the comparison must be marked as non-equivalent.

### Stage 9 — Security Validation

Use the Security Engineer when optimization affects:

- authorization checks
- permission callbacks
- validation
- sanitization
- escaping
- data visibility
- caching of private data
- secret handling
- external API behavior

Required output: `Security Review Report`

Performance improvements must not bypass security controls.

### Stage 10 — QA Validation

Use `38_WORDPRESS/ROLES/QA-ENGINEER.md`.

Test:

- original user flow
- optimized flow
- invalid input
- permissions
- persistence
- cache invalidation
- stale-data behavior
- REST contracts
- AJAX behavior
- cron behavior
- accessibility
- compatibility
- regression behavior

Required output: `QA Report`

### Stage 11 — Documentation

Use the Documentation Engineer when optimization changes:

- operational requirements
- cache behavior
- cron behavior
- deployment requirements
- infrastructure assumptions
- external service usage
- configuration
- troubleshooting procedures

Required output: `Documentation Report`

---

## Optimization Priority

Prioritize work in this order unless dependencies require otherwise:

1. Confirmed user-facing bottlenecks.
2. Operational stability risks.
3. Database scalability risks.
4. High-frequency request costs.
5. External API dependency costs.
6. Asset delivery costs.
7. Lower-impact cleanup.

Do not prioritize speculative micro-optimizations over measured bottlenecks.

---

## Performance Finding Format

```text
Performance Finding

ID:
Title:
Severity:
Component:
Scenario:
Metric:
Baseline:
Evidence:
Cause:
Recommended Fix:
Expected Impact:
Verification Method:
Status:
```

---

## Performance Optimization Final Report

Produce:

```text
Performance Optimization Final Report

Project:
Project Type:
Component:
Performance Problem:
Baseline:
Confirmed Bottleneck:
Role Routing Status:
Roles Used:
Optimization Applied:
Files Created:
Files Modified:
Before Measurement:
After Measurement:
Measured Difference:
Security Status:
QA Status:
Documentation Status:
Known Limitations:
Residual Risks:
Final Result:
Next Step:
```

---

## Completion Criteria

The Optimize Performance Skill is complete only when:

- performance scope is defined
- role routing is complete
- baseline measurement exists when practical
- bottleneck is identified
- optimization plan is defined
- responsible Engineer applies the change
- performance is revalidated
- security is revalidated when affected
- QA confirms functional correctness
- regression testing passes
- operational documentation is updated when required

---

## Rule

The Optimize Performance Skill must measure before and after significant optimization work whenever practical, target confirmed bottlenecks, use the responsible specialist Engineer for implementation, and preserve security, accessibility, compatibility, data integrity, and functional correctness.
