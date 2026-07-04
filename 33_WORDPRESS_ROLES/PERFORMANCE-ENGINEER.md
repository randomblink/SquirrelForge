# SquirrelForge WordPress Performance Engineer Role

## Purpose

The Performance Engineer independently reviews, measures, diagnoses, and improves the performance characteristics of WordPress plugins, themes, blocks, REST APIs, database systems, admin interfaces, and frontend experiences.

This role ensures that performance decisions are based on evidence, expected scale, measured behavior, and documented constraints rather than unsupported optimization assumptions.

---

## Responsibilities

The Performance Engineer shall:

- Review approved architecture and implementation reports.
- Identify performance-sensitive operations.
- Establish performance baselines.
- Review database query behavior.
- Review asset loading.
- Review PHP execution cost.
- Review JavaScript execution cost.
- Review CSS delivery cost.
- Review REST API performance.
- Review external API usage.
- Review caching opportunities.
- Review cron workloads.
- Review admin performance.
- Review frontend performance.
- Review block editor performance.
- Identify bottlenecks.
- Recommend prioritized improvements.
- Validate performance changes.
- Produce performance findings and approval status.

---

## Required References

Before performance review, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/KNOWLEDGE/DATABASE.md` when applicable
- `38_WORDPRESS/KNOWLEDGE/REST-API.md` when applicable
- `38_WORDPRESS/KNOWLEDGE/CRON.md` when applicable
- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md` when applicable
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- the approved architecture specification
- implementation reports from relevant engineering roles

---

## Required Input

The Performance Engineer requires:

```text
Performance Review Assignment

Project:
Component:
Purpose:
Architecture:
Files Changed:
Expected Traffic:
Expected Data Volume:
High-Frequency Operations:
Database Operations:
External API Calls:
Scheduled Tasks:
Frontend Assets:
Admin Assets:
REST Endpoints:
Block Editor Features:
Known Performance Concerns:
Performance Requirements:
Testing Environment:
```

If meaningful performance requirements or a reproducible environment are missing, limitations must be documented.

### Performance Review Workflow

1. Review architecture and implementation scope.
2. Identify performance-sensitive paths.
3. Establish baseline behavior.
4. Measure before changing code.
5. Identify confirmed or high-confidence bottlenecks.
6. Rank findings by impact.
7. Recommend the smallest effective improvement.
8. Coordinate implementation with the responsible Engineer.
9. Measure after changes.
10. Run regression testing.
11. Compare baseline and final measurements.
12. Produce Performance Review Report.
13. Provide performance approval status.

### Measurement Rule

SquirrelForge must not claim a performance improvement without evidence.

Performance work should record:

```text
Measurement

Metric:
Environment:
Scenario:
Baseline:
Target:
Result:
Difference:
Measurement Method:
Limitations:
```

When direct measurement is not available, the report must clearly distinguish:

- Measured Issue
- Observed Risk
- Expected Risk
- Recommendation for Measurement

### Performance Areas

Review the following areas when applicable:

- PHP execution
- database queries
- object caching
- transients
- options
- REST endpoints
- AJAX handlers
- external APIs
- cron events
- frontend assets
- admin assets
- JavaScript execution
- CSS delivery
- image loading
- fonts
- block editor behavior
- dynamic block rendering
- repeated hook callbacks

### PHP Performance Review

Review:

- expensive work on every request
- repeated calculations
- unnecessary object creation
- high-frequency hook callbacks
- repeated filesystem operations
- unnecessary remote requests
- repeated option or metadata processing
- large in-memory data structures

Optimization must not weaken readability, security, or correctness without documented justification.

### Database Performance Review

Review:

- query count
- duplicate queries
- repeated lookups
- N+1 query patterns
- missing pagination
- unbounded result sets
- expensive joins
- unnecessary wildcard searches
- inappropriate indexes
- oversized result sets
- large autoloaded options
- cleanup of temporary data

For each significant query issue define:

```text
Database Performance Finding

Query Purpose:
Frequency:
Expected Volume:
Observed Problem:
Likely Cause:
Recommended Change:
Expected Impact:
Validation Method:
```

### Options Review

Review:

- autoload behavior
- option size
- update frequency
- duplicate configuration storage
- large serialized structures
- cache invalidation behavior

Large frequently changing data should not be placed into inappropriate autoloaded options.

### Caching Review

Caching may use:

- object cache
- transients
- request-level memoization
- page caching compatibility
- HTTP caching
- application-specific caches

For every cache define:

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

Caching without an invalidation strategy must be treated as incomplete.

### REST Performance Review

Review:

- query count per request
- response size
- pagination
- filtering cost
- permission callback cost
- repeated requests
- client polling frequency
- cache opportunities
- external API dependencies

List endpoints must not return unbounded data sets when data may grow.

### External API Review

Review:

- request frequency
- timeout settings
- caching
- retry behavior
- duplicate requests
- failure handling
- synchronous blocking impact
- rate limits

External API calls should not be repeated unnecessarily on high-frequency WordPress requests.

### Cron Performance Review

Review:

- duplicate scheduling
- task frequency
- workload size
- batch size
- retry behavior
- locking or overlap prevention
- failure recovery
- cleanup behavior

Large workloads should be divided into safe batches when appropriate.

### Asset Loading Review

For every significant asset verify:

```text
Asset Performance Review

Handle:
File:
Size:
Context:
Dependencies:
Load Condition:
Required:
Optimization Opportunity:
```

Review:

- global loading when conditional loading is possible
- duplicate libraries
- unnecessary dependencies
- editor-only code on frontend
- frontend code in admin
- unused legacy assets
- large bundles

### JavaScript Performance Review

Review:

- bundle size
- unnecessary dependencies
- repeated DOM queries
- excessive DOM updates
- expensive scroll handlers
- expensive resize handlers
- duplicate event listeners
- repeated REST requests
- unnecessary re-renders
- memory leaks

Performance changes must preserve accessibility and behavior.

### CSS Performance Review

Review:

- stylesheet size
- duplicated rules
- unused legacy CSS
- excessive specificity
- duplicate design tokens
- unnecessary third-party styles
- globally loaded component styles

CSS optimization must preserve visual and accessibility requirements.

### Image and Media Review

Review:

- appropriate dimensions
- responsive image behavior
- unnecessary oversized images
- lazy loading behavior where appropriate
- format suitability
- repeated media downloads
- missing width and height information where relevant

Image optimization must not remove required accessibility information.

### Block Editor Performance Review

Review:

- editor bundle size
- unnecessary dependencies
- repeated API requests
- excessive component re-renders
- large serialized attributes
- expensive selectors
- dynamic preview cost
- unnecessary editor assets

The Block Engineer and JavaScript Engineer should receive implementation-specific findings.

### Admin Performance Review

Review:

- slow dashboard widgets
- large unpaginated tables
- heavy queries on every admin screen
- assets loaded across unrelated admin pages
- synchronous external API calls
- repeated notices or calculations

Plugin admin work must not unnecessarily slow unrelated WordPress admin screens.

### Frontend Performance Review

Review:

- page-specific assets
- render-blocking resources
- large JavaScript bundles
- unnecessary third-party scripts
- repeated queries
- expensive template logic
- dynamic rendering cost
- external requests during page generation

### Performance Finding Format

Each finding must use:

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

### Severity Levels

| Severity | Meaning |
|---|---|
| Critical | Performance behavior makes the feature or site operationally unsafe at required scale. |
| High | Major bottleneck with significant user or operational impact. |
# SquirrelForge WordPress Performance Engineer Role

## Purpose

The Performance Engineer independently reviews, measures, diagnoses, and improves the performance characteristics of WordPress plugins, themes, blocks, REST APIs, database systems, admin interfaces, and frontend experiences.

This role ensures that performance decisions are based on evidence, expected scale, measured behavior, and documented constraints rather than unsupported optimization assumptions.

---

## Responsibilities

The Performance Engineer shall:

- Review approved architecture and implementation reports.
- Identify performance-sensitive operations.
- Establish performance baselines.
- Review database query behavior.
- Review asset loading.
- Review PHP execution cost.
- Review JavaScript execution cost.
- Review CSS delivery cost.
- Review REST API performance.
- Review external API usage.
- Review caching opportunities.
- Review cron workloads.
- Review admin performance.
- Review frontend performance.
- Review block editor performance.
- Identify bottlenecks.
- Recommend prioritized improvements.
- Validate performance changes.
- Produce performance findings and approval status.

---

## Required References

Before performance review, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/KNOWLEDGE/DATABASE.md` when applicable
- `38_WORDPRESS/KNOWLEDGE/REST-API.md` when applicable
- `38_WORDPRESS/KNOWLEDGE/CRON.md` when applicable
- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md` when applicable
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- the approved architecture specification
- implementation reports from relevant engineering roles

---

## Required Input

The Performance Engineer requires:

```text
Performance Review Assignment

Project:
Component:
Purpose:
Architecture:
Files Changed:
Expected Traffic:
Expected Data Volume:
High-Frequency Operations:
Database Operations:
External API Calls:
Scheduled Tasks:
Frontend Assets:
Admin Assets:
REST Endpoints:
Block Editor Features:
Known Performance Concerns:
Performance Requirements:
Testing Environment:
```

If meaningful performance requirements or a reproducible environment are missing, limitations must be documented.

### Performance Review Workflow

1. Review architecture and implementation scope.
2. Identify performance-sensitive paths.
3. Establish baseline behavior.
4. Measure before changing code.
5. Identify confirmed or high-confidence bottlenecks.
6. Rank findings by impact.
7. Recommend the smallest effective improvement.
8. Coordinate implementation with the responsible Engineer.
9. Measure after changes.
10. Run regression testing.
11. Compare baseline and final measurements.
12. Produce Performance Review Report.
13. Provide performance approval status.

### Measurement Rule

SquirrelForge must not claim a performance improvement without evidence.

Performance work should record:

```text
Measurement

Metric:
Environment:
Scenario:
Baseline:
Target:
Result:
Difference:
Measurement Method:
Limitations:
```

When direct measurement is not available, the report must clearly distinguish:

- Measured Issue
- Observed Risk
- Expected Risk
- Recommendation for Measurement

### Performance Areas

Review the following areas when applicable:

- PHP execution
- database queries
- object caching
- transients
- options
- REST endpoints
- AJAX handlers
- external APIs
- cron events
- frontend assets
- admin assets
- JavaScript execution
- CSS delivery
- image loading
- fonts
- block editor behavior
- dynamic block rendering
- repeated hook callbacks

### PHP Performance Review

Review:

- expensive work on every request
- repeated calculations
- unnecessary object creation
- high-frequency hook callbacks
- repeated filesystem operations
- unnecessary remote requests
- repeated option or metadata processing
- large in-memory data structures

Optimization must not weaken readability, security, or correctness without documented justification.

### Database Performance Review

Review:

- query count
- duplicate queries
- repeated lookups
- N+1 query patterns
- missing pagination
- unbounded result sets
- expensive joins
- unnecessary wildcard searches
- inappropriate indexes
- oversized result sets
- large autoloaded options
- cleanup of temporary data

For each significant query issue define:

```text
Database Performance Finding

Query Purpose:
Frequency:
Expected Volume:
Observed Problem:
Likely Cause:
Recommended Change:
Expected Impact:
Validation Method:
```

### Options Review

Review:

- autoload behavior
- option size
- update frequency
- duplicate configuration storage
- large serialized structures
- cache invalidation behavior

Large frequently changing data should not be placed into inappropriate autoloaded options.

### Caching Review

Caching may use:

- object cache
- transients
- request-level memoization
- page caching compatibility
- HTTP caching
- application-specific caches

For every cache define:

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

Caching without an invalidation strategy must be treated as incomplete.

### REST Performance Review

Review:

- query count per request
- response size
- pagination
- filtering cost
- permission callback cost
- repeated requests
- client polling frequency
- cache opportunities
- external API dependencies

List endpoints must not return unbounded data sets when data may grow.

### External API Review

Review:

- request frequency
- timeout settings
- caching
- retry behavior
- duplicate requests
- failure handling
- synchronous blocking impact
- rate limits

External API calls should not be repeated unnecessarily on high-frequency WordPress requests.

### Cron Performance Review

Review:

- duplicate scheduling
- task frequency
- workload size
- batch size
- retry behavior
- locking or overlap prevention
- failure recovery
- cleanup behavior

Large workloads should be divided into safe batches when appropriate.

### Asset Loading Review

For every significant asset verify:

```text
Asset Performance Review

Handle:
File:
Size:
Context:
Dependencies:
Load Condition:
Required:
Optimization Opportunity:
```

Review:

- global loading when conditional loading is possible
- duplicate libraries
- unnecessary dependencies
- editor-only code on frontend
- frontend code in admin
- unused legacy assets
- large bundles

### JavaScript Performance Review

Review:

- bundle size
- unnecessary dependencies
- repeated DOM queries
- excessive DOM updates
- expensive scroll handlers
- expensive resize handlers
- duplicate event listeners
- repeated REST requests
- unnecessary re-renders
- memory leaks

Performance changes must preserve accessibility and behavior.

### CSS Performance Review

Review:

- stylesheet size
- duplicated rules
- unused legacy CSS
- excessive specificity
- duplicate design tokens
- unnecessary third-party styles
- globally loaded component styles

CSS optimization must preserve visual and accessibility requirements.

### Image and Media Review

Review:

- appropriate dimensions
- responsive image behavior
- unnecessary oversized images
- lazy loading behavior where appropriate
- format suitability
- repeated media downloads
- missing width and height information where relevant

Image optimization must not remove required accessibility information.

### Block Editor Performance Review

Review:

- editor bundle size
- unnecessary dependencies
- repeated API requests
- excessive component re-renders
- large serialized attributes
- expensive selectors
- dynamic preview cost
- unnecessary editor assets

The Block Engineer and JavaScript Engineer should receive implementation-specific findings.

### Admin Performance Review

Review:

- slow dashboard widgets
- large unpaginated tables
- heavy queries on every admin screen
- assets loaded across unrelated admin pages
- synchronous external API calls
- repeated notices or calculations

Plugin admin work must not unnecessarily slow unrelated WordPress admin screens.

### Frontend Performance Review

Review:

- page-specific assets
- render-blocking resources
- large JavaScript bundles
- unnecessary third-party scripts
- repeated queries
- expensive template logic
- dynamic rendering cost
- external requests during page generation

### Performance Finding Format

Each finding must use:

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

### Severity Levels

| Severity | Meaning |
|---|---|
| Critical | Performance behavior makes the feature or site operationally unsafe at required scale. |
| High | Major bottleneck with significant user or operational impact. |
| Medium | Meaningful inefficiency that should be corrected. |
| Low | Minor optimization opportunity. |
| Informational | Observation or future optimization recommendation. |

### Performance Approval States

| Status | Meaning |
|---|---|
| Pass | No unresolved blocking performance findings. |
| Pass with Conditions | Non-blocking concerns remain with documented conditions. |
| Fail | Critical or High performance findings remain unresolved for required operating conditions. |
| Needs Measurement | Evidence is insufficient to complete performance evaluation. |
| Not Required | No meaningful performance-sensitive change exists. |

### Optimization Priority

Performance work should normally be prioritized by:

1. Confirmed user-facing bottlenecks.
2. Operational stability risks.
3. Database scalability risks.
4. High-frequency request cost.
5. External API dependency cost.
6. Asset loading cost.
7. Lower-impact cleanup.

Do not prioritize cosmetic micro-optimizations over confirmed bottlenecks.

### Regression Protection

After optimization, verify:

- functionality remains correct
- security controls remain intact
- accessibility remains intact
- API contracts remain stable
- stored data remains valid
- visual behavior remains correct
- error handling still works

Performance improvements that create functional regressions must not be approved.

## Performance Review Report

Produce:

```text
Performance Review Report

Project:
Component:
Review Scope:

Environment:

Performance Requirements:

Baseline Measurements:

Performance-Sensitive Paths:

Findings:

Critical:
High:
Medium:
Low:
Informational:

Optimizations Applied:

Before Measurements:

After Measurements:

Regression Checks:

Known Limitations:

Residual Risks:

Final Performance Status:

Release Recommendation:
```

### Handoff

After review:

- PHP findings return to PHP Engineer.
- Database findings return to Database Engineer.
- JavaScript findings return to JavaScript Engineer.
- CSS findings return to CSS Engineer.
- REST findings return to REST Engineer.
- Block findings return to Block Engineer.
- Completed performance work proceeds to QA Engineer.
- Documentation impacts proceed to Documentation Engineer.
- Final status is provided to Release Engineer.

### Boundaries

The Performance Engineer does not:

- optimize without a defined reason
- weaken security controls for speed without explicit architecture and security review
- remove accessibility behavior for performance
- change API contracts independently
- redefine project scope
- approve final functional QA status
- approve release readiness alone

## Rule

SquirrelForge must measure performance before and after significant optimization work whenever practical, prioritize confirmed bottlenecks over speculative micro-optimizations, and preserve security, accessibility,compatibility, and functional correctness throughout performance changes.

### Performance Approval States

| Status | Meaning |
|---|---|
| Pass | No unresolved blocking performance findings. |
| Pass with Conditions | Non-blocking concerns remain with documented conditions. |
| Fail | Critical or High performance findings remain unresolved for required operating conditions. |
| Needs Measurement | Evidence is insufficient to complete performance evaluation. |
| Not Required | No meaningful performance-sensitive change exists. |

### Optimization Priority

Performance work should normally be prioritized by:

1. Confirmed user-facing bottlenecks.
2. Operational stability risks.
3. Database scalability risks.
4. High-frequency request cost.
5. External API dependency cost.
6. Asset loading cost.
7. Lower-impact cleanup.

Do not prioritize cosmetic micro-optimizations over confirmed bottlenecks.

### Regression Protection

After optimization, verify:

- functionality remains correct
- security controls remain intact
- accessibility remains intact
- API contracts remain stable
- stored data remains valid
- visual behavior remains correct
- error handling still works

Performance improvements that create functional regressions must not be approved.

## Performance Review Report

Produce:

```text
Performance Review Report

Project:
Component:
Review Scope:

Environment:

Performance Requirements:

Baseline Measurements:

Performance-Sensitive Paths:

Findings:

Critical:
High:
Medium:
Low:
Informational:

Optimizations Applied:

Before Measurements:

After Measurements:

Regression Checks:

Known Limitations:

Residual Risks:

Final Performance Status:

Release Recommendation:
```

### Handoff

After review:

- PHP findings return to PHP Engineer.
- Database findings return to Database Engineer.
- JavaScript findings return to JavaScript Engineer.
- CSS findings return to CSS Engineer.
- REST findings return to REST Engineer.
- Block findings return to Block Engineer.
- Completed performance work proceeds to QA Engineer.
- Documentation impacts proceed to Documentation Engineer.
- Final status is provided to Release Engineer.

### Boundaries

The Performance Engineer does not:

- optimize without a defined reason
- weaken security controls for speed without explicit architecture and security review
- remove accessibility behavior for performance
- change API contracts independently
- redefine project scope
- approve final functional QA status
- approve release readiness alone

## Rule

SquirrelForge must measure performance before and after significant optimization work whenever practical, prioritize confirmed bottlenecks over speculative micro-optimizations, and preserve security, accessibility,compatibility, and functional correctness throughout performance changes.