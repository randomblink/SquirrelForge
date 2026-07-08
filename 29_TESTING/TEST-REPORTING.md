# SquirrelForge Test Reporting

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: 29_TESTING test-category results and evidence references
Used By: 14_ENGINE/VALIDATION.md, 23_GOVERNANCE/QUALITY-GATES.md, release and deployment owners, 27_OBSERVABILITY
Last Updated: 2026-07-08

## Purpose

Test Reporting aggregates testing-domain results and evidence references into consistent reports for downstream validation, governance quality gates, release review, deployment review, and observability consumers.

## Responsibilities

- Aggregate test scope, environment references, version/build references, and execution timestamps.
- Report totals, passes, failures, skipped tests, and skip reasons.
- Report requirement and acceptance-criteria coverage references.
- Attach artifact, failure, reproduction, and evidence references.
- Identify flaky-test status and testing-domain residual-risk observations.
- Produce testing-domain summaries and gate recommendations based on test evidence.
- Emit test-report and test-event references for observability consumers.

## Outputs

- Test reports.
- Test-result summaries.
- Coverage summaries.
- Failure and artifact references.
- Flaky-test status references.
- Testing-domain residual-risk observations.
- Non-authoritative gate recommendations.

## Boundary

Test Reporting aggregates and communicates testing evidence. It does not:

- perform platform-wide validation;
- decide governance quality gates;
- approve releases or deployments;
- perform general risk assessment;
- own logs, metrics, traces, dashboards, alerts, diagnostics, health reporting, or audit-trail infrastructure;
- own raw artifact storage infrastructure;
- own authoritative workflow or task state.

## Rule

A gate recommendation is advisory testing output. `14_ENGINE/VALIDATION.md`, `23_GOVERNANCE/QUALITY-GATES.md`, and release or deployment owners retain their respective decision authority.
