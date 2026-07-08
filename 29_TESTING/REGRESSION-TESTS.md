# SquirrelForge Regression Tests

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: 29_TESTING/TEST-PLANNER.md, defect-history references, change-impact references, prior test baselines
Used By: 29_TESTING/TEST-REPORTING.md, 14_ENGINE/VALIDATION.md, release review owners
Last Updated: 2026-07-08

## Purpose

Regression Tests protect previously working, changed, and defect-prone behavior by rerunning targeted and baseline coverage after relevant changes.

## Responsibilities

- Maintain regression test coverage derived from defect-history and change-impact references.
- Re-execute relevant prior behavior checks after changes.
- Add a reproducing regression test for a fixed defect when technically feasible.
- Compare current test results with applicable prior baselines.
- Produce regression results, changed-behavior evidence, and failure references.

## Boundary

Regression Tests own regression test definitions, execution, and results. They do not:

- own defect lifecycle records;
- decide change approval;
- perform platform-wide validation;
- decide governance quality gates;
- approve releases or deployments;
- execute rollback or recovery;
- own general historical-storage, observability, or audit infrastructure.

## Rule

Regression evidence informs downstream validation and release review. A regression pass does not independently establish complete platform validity or release readiness.
