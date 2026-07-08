# SquirrelForge Test Planner

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: acceptance criteria, 19_REASONING/RISK-ASSESSOR.md, interface contracts, change-impact references
Used By: 29_TESTING test-category components, 29_TESTING/TEST-REPORTING.md
Last Updated: 2026-07-08

## Purpose

The Test Planner converts requirements, acceptance criteria, risk-assessment references, interface contracts, and change-impact information into testing-domain plans.

## Responsibilities

- Define test scope and coverage objectives.
- Map requirements, acceptance criteria, and risk inputs to appropriate test categories.
- Identify required environments, fixtures, test data, prerequisites, and owners.
- Define test ordering and dependencies where sequencing is required.
- Define testing entry and exit criteria as test-plan criteria, not release or governance approval criteria.
- Require negative, boundary, permission-failure, recovery-scenario, and other risk-driven coverage where applicable.
- Produce test-plan records and coverage references for test-category components.

## Inputs

- Requirements and acceptance criteria.
- Risk-assessment references from `19_REASONING/RISK-ASSESSOR.md` where applicable.
- Interface and contract references.
- Change-impact and defect-history references.
- Environment and fixture availability references.

## Outputs

- Test-plan records.
- Test-category assignments.
- Coverage mappings.
- Environment and fixture requirements.
- Test prerequisites and ordering references.
- Testing entry and exit criteria.

## Boundary

The Test Planner plans testing. It does not:

- execute tests;
- perform platform-wide validation;
- decide governance quality gates;
- approve releases or deployments;
- perform general risk assessment;
- own environments, fixtures, or production configuration;
- own execution recovery, retry, or rollback mechanisms;
- own authoritative workflow or task state;
- own general observability or audit infrastructure.

## Rule

A test plan may recommend coverage and define testing completion criteria, but downstream validation, governance quality-gate, release, and deployment decisions remain with their authoritative owners.
