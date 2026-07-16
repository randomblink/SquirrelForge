# SquirrelForge Testing

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: 14_ENGINE/VALIDATION.md, 19_REASONING/RISK-ASSESSOR.md, 20_EXECUTION, 22_INTERFACES, 23_GOVERNANCE/QUALITY-GATES.md, 27_OBSERVABILITY
Used By: 14_ENGINE/VALIDATION.md, 23_GOVERNANCE/QUALITY-GATES.md, release and deployment owners
Last Updated: 2026-07-08

## Purpose

The Testing layer plans and performs defined categories of tests and produces test results, reports, and evidence references for downstream validation, governance quality gates, release review, and deployment decisions.

Testing owns test planning, test-category scope, test execution within each defined category, and test-result reporting. It does not own platform-wide validation, governance quality-gate decisions, release approval, deployment authority, execution recovery, authoritative workflow state, or general observability infrastructure.

## Component Roster

| Component | Responsibility |
|---|---|
| `TEST-PLANNER.md` | Maps requirements, risk inputs, acceptance criteria, environments, fixtures, and coverage needs into test plans. |
| `UNIT-TESTS.md` | Defines and performs isolated component-level tests and produces unit-test results. |
| `INTEGRATION-TESTS.md` | Defines and performs interaction tests across real interface and component boundaries. |
| `SYSTEM-TESTS.md` | Defines and performs end-to-end system scenario tests in representative environments. |
| `SMOKE-TESTS.md` | Defines and performs short critical-path test suites against candidate builds or deployments. |
| `REGRESSION-TESTS.md` | Defines and performs regression coverage for previously working, changed, and defect-prone behavior. |
| `TEST-REPORTING.md` | Aggregates test results and evidence references into testing-domain reports for downstream decision owners. |

## Layer Boundary

The Testing layer:

- owns test plans, test suites, test-category execution, test results, and testing-domain reports;
- consumes requirements, acceptance criteria, risk-assessment references, interface contracts, builds, environments, fixtures, and execution evidence as test inputs;
- provides test evidence to `14_ENGINE/VALIDATION.md` without replacing platform validation authority;
- provides test evidence and recommendations to `23_GOVERNANCE/QUALITY-GATES.md` without making governance gate decisions;
- emits test events and evidence references for `27_OBSERVABILITY` without owning general logging, metrics, tracing, dashboards, alerting, diagnostics, health, or audit-trail infrastructure;
- may exercise retry, recovery, rollback, authorization, persistence, and failure behavior as test scenarios without becoming the owner of those operational mechanisms;
- does not approve releases or deployments;
- does not own authoritative workflow or task state.

## Execution Model

```text
Requirements + Acceptance Criteria + Risk Inputs
                    ↓
              TEST-PLANNER
                    ↓
     Unit → Integration → System
                    ↓
          Smoke and Regression
                    ↓
             TEST-REPORTING
                    ↓
 Validation / Quality Gates / Release and Deployment Owners
```

The exact test ordering may vary by change type and risk. The diagram describes evidence flow, not mandatory authority transfer.

## Domain-Specific Testing Standards

Domain layers may define additional validation dimensions on top of these general categories. For WordPress work, `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md` defines the required WordPress-specific dimensions and maps each one onto the general categories above; it does not replace or duplicate them. WordPress work must satisfy both this layer's execution and reporting responsibilities and that standard's WordPress-specific dimensions.

## Rules

- Tests must be repeatable and isolated where appropriate.
- Test plans must map coverage to requirements, acceptance criteria, risks, or change impact where applicable.
- Test-category components own their test results, not downstream acceptance decisions.
- Failures must preserve reproducible evidence and references sufficient for owning components to diagnose and act.
- Testing reports may recommend a gate outcome but must not decide governance quality gates, release approval, or deployment authorization.
- Testing components must not create parallel observability, storage, execution-state, or recovery infrastructure.
