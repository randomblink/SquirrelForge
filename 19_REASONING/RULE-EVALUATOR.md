# SquirrelForge Rule Evaluator

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Rule Evaluator ensures every decision and implementation complies with the project's governing rules, standards, and constraints before execution proceeds.

## Responsibilities

- Evaluate project rules.
- Verify workflow compliance.
- Detect rule conflicts.
- Apply rule precedence.
- Record rule evaluation results.
- Forward compliant decisions to the Risk Assessor.

## Rule Sources

| Source | Description |
|---|---|
| Project Rules | Global project requirements |
| Engine Rules | Core execution behavior |
| Workflow Rules | Workflow-specific requirements |
| Agent Rules | Agent responsibilities and limits |
| Security Rules | Security requirements |
| Documentation Rules | Documentation standards |

## Evaluation Process

1. Receive the selected strategy.
2. Identify applicable rules.
3. Evaluate compliance.
4. Detect conflicts or violations.
5. Apply rule precedence.
6. Record the evaluation.
7. Forward compliant strategies to the Risk Assessor.

## Rule Priority

1. Project Rules
2. Security Rules
3. Engine Rules
4. Workflow Rules
5. Agent Rules
6. Documentation Rules

## Evaluation Record

| Field | Description |
|---|---|
| Rule ID | Rule evaluated |
| Source | Origin of the rule |
| Result | Passed / Failed / Warning |
| Reason | Explanation |
| Required Action | None / Revise / Escalate |

## Rule

No implementation strategy may proceed if it violates a higher-priority rule. All rule violations must be documented before revision or escalation.