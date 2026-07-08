# SquirrelForge Automation Governance

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: AUTOMATION-VALIDATOR.md, 19_REASONING/RISK-ASSESSOR.md, 23_GOVERNANCE/POLICY-ENGINE.md, 24_SECURITY, 27_OBSERVABILITY
Used By: APPROVAL-GATE.md, AUTOMATION-MANAGER.md and automation lifecycle reviews
Last Updated: 2026-07-08

## Purpose

Automation Governance owns Automation-domain standards, lifecycle restrictions, exception decisions, governance review decisions, and Automation-domain governance records.

## Responsibilities

- Define and maintain Automation-domain standards and control requirements.
- Define which automation classes require Automation Governance review.
- Consume authoritative risk assessments, policy-evaluation results, Security decisions, compliance findings, validation findings, and observability references.
- Approve, reject, defer, condition, restrict, suspend, or retire automation within Automation-domain authority.
- Decide Automation-domain exceptions where permitted by higher-level governance.
- Record decision rationale, conditions, restrictions, evidence references, review dates, and status.
- Review outcome evidence for continued Automation-domain suitability.

## Boundary

Automation Governance does not define general platform governance policy, perform governance-policy evaluation, perform general risk assessment, accept Security risk, make runtime authorization decisions, certify compliance, perform platform validation, approve releases or deployments, execute automation, perform rollback/recovery/remediation, collect telemetry, or own general audit/storage infrastructure.

Automation Governance decisions remain subject to higher-level Governance, Security, compliance, Quality Gate, release, deployment, Execution, and Resilience authorities where applicable.

## Rule

Automation Governance governs the Automation domain only; it cannot replace or override authoritative cross-layer decisions.