# SquirrelForge Unit Tests

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: 29_TESTING/TEST-PLANNER.md, component contracts, test fixtures
Used By: 29_TESTING/TEST-REPORTING.md, 14_ENGINE/VALIDATION.md, 23_GOVERNANCE/QUALITY-GATES.md
Last Updated: 2026-07-08

## Purpose

Unit Tests verify individual components or units in isolation and produce deterministic unit-test results and evidence references.

## Responsibilities

- Test normal behavior, boundaries, invalid inputs, and error behavior at unit scope.
- Use controlled fixtures, doubles, or isolated dependencies where appropriate.
- Preserve deterministic and reproducible unit-test results.
- Produce failure evidence and coverage references for reporting and downstream validation.
- Keep routine unit suites fast enough for frequent change validation where practical.

## Boundary

Unit Tests own unit-test definitions, execution at unit scope, and unit-test results. They do not:

- perform platform-wide validation;
- decide governance quality gates;
- approve releases or deployments;
- own integration or system-test conclusions;
- own production execution, retry, recovery, or rollback;
- own general observability or audit infrastructure.

## Rule

Unit-test results are testing evidence. Downstream Validation and Quality Gate owners decide how that evidence affects platform acceptance and governance decisions.
