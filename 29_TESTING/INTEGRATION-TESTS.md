# SquirrelForge Integration Tests

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: 29_TESTING/TEST-PLANNER.md, 22_INTERFACES, approved test environments and fixtures
Used By: 29_TESTING/TEST-REPORTING.md, 14_ENGINE/VALIDATION.md, 23_GOVERNANCE/QUALITY-GATES.md
Last Updated: 2026-07-08

## Purpose

Integration Tests verify behavior across real component and interface boundaries and produce integration-test results and evidence references.

## Responsibilities

- Test contract and schema compatibility across integrated boundaries.
- Test expected request, response, status, and failure propagation behavior.
- Exercise permission-denial, partial-failure, timeout, retryable-status, and data-consistency scenarios where relevant.
- Verify integration behavior against approved interface contracts and test-plan coverage.
- Produce reproducible integration-test results, failure evidence, and coverage references.

## Boundary

Integration Tests may exercise authorization failures, retry behavior, persistence, and recovery scenarios, but they do not own those operational mechanisms. They do not:

- make authorization decisions;
- execute production retry or recovery policy;
- own integration routing or connector registries;
- perform platform-wide validation;
- decide governance quality gates;
- approve releases or deployments;
- own general observability or audit infrastructure.

## Rule

Integration-test conclusions are limited to tested interactions and contracts. Platform acceptance and governance gate decisions remain with downstream authoritative owners.
