# SquirrelForge Rule Engine

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: declarative automation rule definitions, authoritative context and decision references
Used By: TRIGGER-MANAGER.md, AUTOMATION-MANAGER.md
Last Updated: 2026-07-08

## Purpose

The Rule Engine evaluates declarative Automation-domain conditions against supplied authoritative context and returns structured rule results.

## Responsibilities

- Check automation-rule structure and required inputs.
- Evaluate Boolean, threshold, schedule-window, dependency, and composite automation conditions.
- Consume permission, risk, policy, security, health, and workflow-state references from authoritative owners when rules depend on them.
- Detect conflicts among Automation-domain rule definitions and return conflict findings.
- Produce pass, fail, conditional, deferred, or input-missing results with evidence references.

## Boundary

The Rule Engine does not perform governance-policy evaluation, runtime authorization, general risk assessment, platform validation, approval decisions, trigger activation, workflow execution, recovery, telemetry collection, or audit infrastructure ownership. It does not reinterpret authoritative decisions supplied by other layers.

## Rule

A rule result states whether declarative automation conditions are satisfied. It is not governance approval, Security authorization, or execution authority.