# SquirrelForge Smoke Tests

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: 29_TESTING/TEST-PLANNER.md, candidate build or deployment reference, approved test environment
Used By: 29_TESTING/TEST-REPORTING.md, 14_ENGINE/VALIDATION.md, release and deployment owners
Last Updated: 2026-07-08

## Purpose

Smoke Tests provide a short critical-path suite that determines whether a candidate build or deployment is testably operational enough for further evaluation.

## Responsibilities

- Test startup and basic configuration loading.
- Test critical interfaces and one or more representative workflows.
- Test essential persistence, validation handoff, and reporting paths where applicable.
- Produce smoke-test pass/fail results, failure evidence, and environment/build references.
- Stop or flag further testing according to the test plan when critical smoke checks fail.

## Boundary

A smoke-test result may block further testing under the test plan, but Smoke Tests do not:

- approve or reject a release;
- authorize or perform deployment;
- decide governance quality gates;
- replace platform-wide validation;
- own runtime configuration, persistence, or workflow execution;
- perform recovery or rollback;
- own general observability or audit infrastructure.

## Rule

Smoke-test results are rapid testing evidence for downstream owners. A passing smoke suite is not by itself release approval or deployment authorization.
