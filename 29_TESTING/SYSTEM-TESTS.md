# SquirrelForge System Tests

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: 29_TESTING/TEST-PLANNER.md, integrated candidate system, representative test environment
Used By: 29_TESTING/TEST-REPORTING.md, 14_ENGINE/VALIDATION.md, release review owners
Last Updated: 2026-07-08

## Purpose

System Tests verify end-to-end system scenarios across an integrated candidate system in a production-representative test environment.

## Responsibilities

- Execute end-to-end scenarios from request intake through expected output and evidence production.
- Exercise representative lifecycle, persistence, failure, recovery-scenario, archive, and restoration behavior where applicable.
- Verify externally observable system behavior against test-plan expectations and acceptance criteria.
- Record system-test results, environment references, failure evidence, and scenario coverage.

## Boundary

System Tests exercise system behavior but do not own the operational mechanisms under test. They do not:

- execute production recovery, rollback, or remediation authority;
- own archive or storage infrastructure;
- own authoritative workflow or task state;
- perform platform-wide validation;
- approve release or deployment;
- decide governance quality gates;
- own general observability or audit infrastructure.

## Rule

Passing system tests provide end-to-end testing evidence. They do not independently certify platform validity, release readiness, or deployment authorization.
