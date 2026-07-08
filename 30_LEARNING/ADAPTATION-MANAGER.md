# SquirrelForge Adaptation Manager

Version: 1.0.0
Status: Stable
Owner: Learning Maintainers
Depends On: LEARNING-GOVERNANCE.md, EVALUATION-ENGINE.md, PATTERN-DETECTOR.md, 14_ENGINE/VALIDATION.md, 19_REASONING/RISK-ASSESSOR.md, 20_EXECUTION, 35_RESILIENCE
Used By: LEARNING-MANAGER.md, LEARNING-MONITOR.md
Last Updated: 2026-07-08

## Purpose

The Adaptation Manager owns Learning-domain adaptation plans and coordinates approved adaptations through authoritative implementation, validation, deployment, and recovery owners.

## Responsibilities

- Receive approved adaptation proposals and decision references.
- Confirm required approval, validation, risk, readiness, and rollback-plan references are present.
- Create adaptation plans, sequencing references, affected-component references, and success criteria.
- Submit authorized implementation work to Execution owners.
- Track adaptation-domain progress from authoritative execution status references.
- Request post-change validation from Validation owners.
- Request rollback or recovery handling from Resilience and owning operational components when required.
- Record adaptation outcome references and report status to Learning consumers.

## Boundary

The Adaptation Manager does not:

- approve adaptations;
- execute production changes directly;
- perform deployment or release authorization;
- perform platform-wide validation;
- perform general risk assessment;
- execute rollback, retry, recovery, or remediation mechanisms;
- own authoritative workflow or task state;
- own general monitoring, logging, or audit infrastructure.

## Rule

The Adaptation Manager coordinates an approved Learning-domain adaptation. Operational changes, validation decisions, release/deployment decisions, and recovery actions remain with their authoritative owners.