# SquirrelForge Learning Governance

Version: 1.0.0
Status: Stable
Owner: Learning Maintainers
Depends On: EVALUATION-ENGINE.md, PATTERN-DETECTOR.md, 14_ENGINE/VALIDATION.md, 19_REASONING/RISK-ASSESSOR.md, 23_GOVERNANCE/POLICY-ENGINE.md, applicable Security decision references
Used By: ADAPTATION-MANAGER.md, LEARNING-MANAGER.md
Last Updated: 2026-07-08

## Purpose

Learning Governance owns Learning-domain decisions to approve, reject, defer, condition, restrict, or prohibit adaptation proposals.

## Responsibilities

- Review adaptation proposals and supporting Learning-domain evidence.
- Consume validation, risk-assessment, policy-evaluation, security, and readiness references from authoritative owners.
- Decide whether a proposal is approved, approved with conditions, deferred, requires more evidence, rejected, or prohibited within the Learning domain.
- Record decision rationale, conditions, restrictions, evidence references, and decision status.
- Publish decision references for the Adaptation Manager and Learning Manager.

## Boundary

Learning Governance does not:

- perform platform-wide validation;
- perform general risk assessment;
- evaluate general governance policy independently;
- override Security policy or Security decisions;
- authorize release or deployment;
- execute adaptations, rollback, recovery, or remediation;
- own compliance certification;
- own general audit or observability infrastructure.

## Rule

A Learning Governance approval is necessary for governed Learning-domain adaptation progression, but it does not replace any required Validation, Policy Engine, Security, Quality Gate, release, deployment, Execution, or Resilience decision.