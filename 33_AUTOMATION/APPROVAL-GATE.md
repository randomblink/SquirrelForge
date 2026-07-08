# SquirrelForge Approval Gate

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: authoritative human approval records, AUTOMATION-VALIDATOR.md, AUTOMATION-GOVERNANCE.md, 19_REASONING/RISK-ASSESSOR.md, 23_GOVERNANCE/POLICY-ENGINE.md, 24_SECURITY/AUTHORIZATION-MANAGER.md, 24_SECURITY/COMPLIANCE.md
Used By: WORKFLOW-AUTOMATOR.md, AUTOMATION-MANAGER.md
Last Updated: 2026-07-08

## Purpose

The Approval Gate verifies that all required approval and authoritative decision references exist, remain valid, match scope, and permit Automation-domain progression.

## Responsibilities

- Determine required approval-reference categories from the automation contract and authoritative policy-evaluation results.
- Verify presence, validity window, scope, conditions, and status of required human, governance, Security, compliance, risk, validation, and other decision references.
- Produce gate results: pass, conditional pass, pending, blocked, expired-reference, revoked-reference, or incomplete.
- Preserve approval-reference and gate-result evidence relationships.
- Block Automation-domain progression when required references are absent or invalid.

## Boundary

The Approval Gate does not authenticate identities, make runtime authorization decisions, perform risk assessment, evaluate governance policy, make compliance findings, make Security approvals, perform platform validation, create human approvals, approve its own exceptions, execute automation, or own general audit/storage infrastructure.

## Rule

The Approval Gate verifies authoritative approvals; it does not manufacture or replace them.