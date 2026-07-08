# SquirrelForge Optimization Governance

Version: 1.0.0
Status: Stable
Owner: Optimization Maintainers
Depends On: OPTIMIZATION-VALIDATOR.md, 19_REASONING/RISK-ASSESSOR.md, 23_GOVERNANCE/POLICY-ENGINE.md, applicable Security and compliance decision references
Used By: OPTIMIZATION-MANAGER.md and approved implementation workflows
Last Updated: 2026-07-08

## Purpose

Optimization Governance owns Optimization-domain decisions to approve, reject, defer, condition, restrict, retire, or require revision of optimization proposals.

## Responsibilities

- Review optimization proposals and Optimization Validator findings.
- Consume authoritative risk assessments, policy-evaluation results, Security decisions, compliance findings, readiness references, and rollback-plan references where applicable.
- Produce Optimization-domain decision records with rationale, conditions, restrictions, evidence references, and review requirements.
- Publish decision references for the Optimization Manager and downstream implementation owners.
- Review outcome evidence to decide whether Optimization-domain conditions remain satisfied or require refinement or retirement review.

## Boundary

Optimization Governance does not perform general risk assessment, governance-policy evaluation, Security decisions, compliance certification, platform validation, quality-gate decisions, release or deployment authorization, implementation, rollback, recovery, remediation, general monitoring, or audit-trail infrastructure.

Optimization-domain approval is distinct from external approvals required by platform Governance, Security, Quality Gates, release, deployment, Execution, and Resilience owners.

## Rule

Optimization Governance may authorize progression within the Optimization domain, but it cannot replace any required external authority.