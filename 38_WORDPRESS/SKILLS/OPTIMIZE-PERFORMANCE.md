# Skill: Optimize WordPress Performance

Version: 1.0.0  
Status: Draft  
Owner: SquirrelForge Maintainers  
Domain: WordPress  

## 1. Purpose

To identify, prioritize, implement, and verify WordPress performance improvements through a repeatable evidence-based process.

This skill treats optimization as a controlled engineering exercise. It establishes a baseline, locates measured bottlenecks, applies the smallest safe change, verifies the result, and preserves functional, security, accessibility, and data-integrity requirements.

## 2. When to Use

Use this skill when:

- A WordPress page, request, job, plugin, theme, or administration screen is slow.
- Database queries, remote requests, cron jobs, or background tasks consume excessive resources.
- Assets, images, blocks, or templates delay rendering.
- A deployment causes a measurable performance regression.
- Capacity, throughput, latency, or cache behavior requires improvement.
- A project needs a performance review before release.

Do not use this skill to make speculative changes when no performance objective, baseline, or reproducible symptom exists.

## 3. Required Inputs

- Project path and WordPress project type.
- Affected URL, workflow, hook, command, or background task.
- Target environment and relevant infrastructure constraints.
- Reproduction steps and representative data volume.
- Current measurements, when available.
- Performance objective or acceptance threshold.
- Allowed change scope and compatibility requirements.
- Available profiling, monitoring, cache, and database tools.

If production data is involved, access must be authorized and sensitive information must be protected.

## 4. Required Knowledge

Consult the Knowledge Manager before analysis. Typical references include:

- `../KNOWLEDGE/SECURITY.md`
- `../DATABASE.md`
- `../CODING-STANDARDS.md`
- `../REST-API.md` when API traffic is involved
- `../STANDARDS/PHP-STANDARD.md`
- `../STANDARDS/CSS-STANDARD.md`
- `../STANDARDS/JAVASCRIPT-STANDARD.md` when available
- `../../02_WORKFLOWS/PERFORMANCE-OPTIMIZATION-WORKFLOW.md`

Security and correctness rules take priority over performance convenience.

## 5. Performance Optimization Workflow

### Phase 1: Define the Objective

1. Identify the affected user journey or system operation.
2. Define the performance symptom in measurable terms.
3. Select the primary metric and target.
4. Record environmental conditions, data size, cache state, and test method.
5. Define functional and non-functional behavior that must not change.

Common metrics include:

- Server response time.
- Largest Contentful Paint and other relevant web-vital measurements.
- Database query count and duration.
- Remote request count and duration.
- PHP execution time and peak memory.
- Asset count, transfer size, and render-blocking time.
- Cron duration, queue latency, and throughput.
- Cache hit rate.

### Phase 2: Establish a Baseline

1. Reproduce the issue consistently.
2. Warm or clear caches according to the test plan.
3. Run multiple comparable measurements.
4. Record median and outlier behavior.
5. Preserve the baseline report before modifying code.

Do not compare measurements produced under materially different conditions.

### Phase 3: Profile the Bottleneck

Inspect only the paths relevant to the measured symptom.

Review:

- Slow, repeated, unbounded, or N+1 database queries.
- Missing indexes and inefficient query shapes.
- Large autoloaded options.
- Repeated computation or repeated option/meta retrieval.
- Slow hooks and callbacks running outside their required context.
- Synchronous remote HTTP calls.
- Duplicate cron scheduling or long-running scheduled work.
- Unconditional asset loading.
- Oversized or unoptimized images, fonts, scripts, and styles.
- Expensive block rendering or template logic.
- Ineffective cache keys, invalidation, or expiration.
- Excessive object creation, serialization, or memory retention.

### Phase 4: Form and Rank Hypotheses

For each candidate bottleneck, record:

- Supporting evidence.
- Expected benefit.
- Change complexity.
- Correctness and security risk.
- Cache invalidation requirements.
- Compatibility impact.
- Rollback method.

Prioritize high-confidence, high-impact, low-risk changes.

### Phase 5: Implement Controlled Changes

1. Apply one logically isolated optimization at a time.
2. Preserve public behavior and documented interfaces.
3. Add cache invalidation before introducing caching.
4. Bound database, API, and batch operations.
5. Load assets and callbacks only where required.
6. Add or update regression tests.
7. Record the exact change and expected effect.

### Phase 6: Verify the Result

1. Repeat the baseline measurement procedure.
2. Compare the same metric under the same conditions.
3. Run functional, security, accessibility, and regression tests.
4. Test cold-cache and warm-cache behavior where relevant.
5. Verify failure behavior and cache invalidation.
6. Revert changes that do not produce a meaningful safe improvement.

### Phase 7: Report and Monitor

Publish:

- Original symptom and baseline.
- Root cause and supporting evidence.
- Changes made.
- Before-and-after measurements.
- Test results.
- Residual risks and unresolved bottlenecks.
- Monitoring and rollback instructions.

## 6. Optimization Strategies

Use only when supported by measurements:

- Reduce query volume or select only required fields.
- Add appropriate indexes through a reviewed migration.
- Cache expensive deterministic results with scoped keys and explicit invalidation.
- Use object caching, transients, or persistent storage according to data semantics.
- Batch or defer non-critical work.
- Prevent duplicate scheduled events.
- Paginate and bound large result sets.
- Condition hooks and asset enqueueing by screen, route, block, or feature.
- Remove duplicate dependencies and unused assets.
- Optimize image sizes, formats, loading behavior, and responsive markup.
- Minimize synchronous third-party requests and define timeouts.
- Precompute stable data when invalidation is reliable.

## 7. Guardrails

The skill must never:

- Weaken authorization, nonce, validation, sanitization, or escaping controls.
- Disable required logging, auditing, or error handling to improve a metric.
- Cache private data under shared or insufficiently scoped keys.
- Introduce stale-data behavior without an accepted freshness policy.
- call `flush_rewrite_rules()` on normal requests.
- Run unbounded queries or migrations in user-facing requests.
- Claim improvement without comparable measurements.
- Optimize synthetic benchmarks while degrading the actual user journey.
- Modify WordPress core.

## 8. Expected Outputs

- Performance baseline.
- Bottleneck and root-cause report.
- Ranked optimization plan.
- Implemented changes or a patch proposal.
- Before-and-after measurements.
- Regression and safety test results.
- Monitoring, invalidation, and rollback notes.

## 9. Quality Checklist

- [ ] A reproducible symptom and measurable target are defined.
- [ ] Baseline measurements are preserved.
- [ ] The bottleneck is supported by profiling evidence.
- [ ] Changes are isolated and proportionate to the evidence.
- [ ] Security, accessibility, and correctness remain intact.
- [ ] Cache ownership, scope, expiration, and invalidation are documented.
- [ ] Database operations are bounded and safe.
- [ ] Assets and hooks load only where needed.
- [ ] Before-and-after measurements use comparable conditions.
- [ ] Functional and regression tests pass.
- [ ] Rollback and monitoring instructions exist.

## 10. Failure and Recovery

If an optimization causes regression or instability:

1. Stop further rollout.
2. Preserve diagnostics and measurements.
3. Revert the isolated change or disable it through an approved feature control.
4. Clear or migrate affected caches safely.
5. Verify restoration against the original baseline and functional tests.
6. Record the failure and update the optimization hypothesis.

## 11. Related Skills

- Code Review
- Bug Fixing
- Testing
- Security Review
- Database Optimization
- Deployment

## 12. Rule

SquirrelForge must not approve a WordPress performance optimization unless it is supported by comparable measurements, preserves required behavior and controls, and includes verification and rollback evidence.
