# SquirrelForge Rule Evaluator

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `01_RULES/README.md`, `01_RULES/AGENT-BEHAVIOR.md`, `23_GOVERNANCE/POLICY-ENGINE.md`, `23_GOVERNANCE/QUALITY-GATES.md`, `24_SECURITY`
Used By: Decision Engine
Last Updated: 2026-07-06

## Purpose

The Rule Evaluator evaluates a proposed decision or action against applicable rules from authoritative rule and policy sources, detects violations and conflicts, applies precedence that already exists, and produces a traceable Rule Evaluation Record.

The Rule Evaluator applies precedence; it does not invent it. Rule precedence and conflict-resolution authority are owned by `01_RULES/README.md` (its Rule Loading Order and Conflict Rule) and `23_GOVERNANCE/POLICY-ENGINE.md`. The Evaluator does not select a strategy (owned by `19_REASONING/DECISION-ENGINE.md`), perform risk assessment (owned by `19_REASONING/RISK-ASSESSOR.md`), or assume a mandatory handoff to any specific downstream component — it returns its result to whichever component requested the evaluation, typically the Decision Engine, which decides what happens next.

---

## Responsibilities

The Rule Evaluator must:

- identify applicable rules from `01_RULES`, `23_GOVERNANCE`, and other authoritative rule and policy sources for the proposed decision or action,
- evaluate the proposal against those rules,
- detect violations and conflicts between applicable rules,
- apply the precedence `01_RULES/README.md`'s Rule Loading Order and `23_GOVERNANCE` already define, rather than inventing a new ordering,
- produce a traceable Rule Evaluation Record,
- return a `Passed`, `Warning`, or `Failed` outcome, or an architecture-approved equivalent,
- and request escalation, per `01_RULES/README.md`'s Conflict Rule, when a conflict cannot be resolved within existing authority.

---

## Rule Sources

| Source | Description | Owner |
|---|---|---|
| Agent Behavior Rules | General agent operating rules. | `01_RULES/AGENT-BEHAVIOR.md` |
| Domain Rules | Domain-specific implementation baseline rules. | `01_RULES` domain rule files (for example `WORDPRESS-RULES.md`) |
| Workflow Rules | Workflow-specific requirements. | The active workflow definition, per `14_ENGINE/WORKFLOW-SELECTOR.md` |
| Security / Governance Constraints | Security and governance policy. | `23_GOVERNANCE/POLICY-ENGINE.md`, `24_SECURITY` |
| Quality Gates | Required release and review gates. | `23_GOVERNANCE/QUALITY-GATES.md` |

---

## Evaluation Process

1. Receive the proposed decision or action.
2. Identify applicable rules from the Rule Sources.
3. Evaluate compliance against each applicable rule.
4. Detect conflicts between applicable rules.
5. Apply the precedence `01_RULES/README.md`'s Rule Loading Order and `23_GOVERNANCE` already define.
6. If a conflict cannot be resolved within existing authority, request escalation per `01_RULES/README.md`'s Conflict Rule.
7. Record the evaluation in a Rule Evaluation Record.
8. Return the outcome to the requesting component.

---

## Rule Precedence

Precedence between rule sources is not defined here. The Rule Evaluator applies `01_RULES/README.md`'s Rule Loading Order (General Agent Rules → Project-Specific Rules → Applicable Domain Rules → Workflow Rules → Security / Governance Constraints) and any governance-approved policy those layers establish.

If those sources do not resolve a conflict, the Evaluator escalates per `01_RULES/README.md`'s Conflict Rule rather than inventing a resolution.

---

## Rule Evaluation Record

| Field | Description |
|---|---|
| Rule ID | Rule evaluated. |
| Source | Owning rule or policy layer. |
| Result | `Passed` / `Warning` / `Failed`. |
| Reason | Explanation. |
| Conflict Detected | Whether this rule conflicted with another applicable rule. |
| Required Action | None / Revise / Escalate. |

---

## Permission Boundary

The Rule Evaluator may identify applicable rules, evaluate compliance, detect conflicts, apply externally defined precedence, and record the outcome.

It must not select a strategy itself (owned by `19_REASONING/DECISION-ENGINE.md`), define rule precedence or resolve a policy conflict by inventing new authority (owned by `01_RULES/README.md` and `23_GOVERNANCE`), perform risk assessment (owned by `19_REASONING/RISK-ASSESSOR.md`), or assume a mandatory handoff to a specific downstream component.

---

## Domain Rule

A domain rule constrains evaluation only when that domain is active, per `01_RULES/README.md`'s Domain Rule Boundary.

---

## Rule

> No proposed decision or action may proceed if it violates a rule and the conflict is not resolved through existing precedence and escalation. The Rule Evaluator applies precedence; it does not invent it.
