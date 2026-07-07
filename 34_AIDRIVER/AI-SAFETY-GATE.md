# SquirrelForge AI Safety Gate

Version: 1.0.0
Status: Stable
Owner: AI Driver Maintainers
Depends On: `19_REASONING/RULE-EVALUATOR.md`, `19_REASONING/RISK-ASSESSOR.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `19_REASONING/AI-DRIVER.md`, `20_EXECUTION/ACTION-DISPATCHER.md`
Last Updated: 2026-07-07

## Purpose

The AI Safety Gate is the final pre-dispatch checkpoint for AI-driven actions. It re-confirms that `19_REASONING/RULE-EVALUATOR.md`'s compliance finding and `19_REASONING/RISK-ASSESSOR.md`'s risk assessment still hold for the concrete action about to be dispatched, before it reaches `20_EXECUTION/ACTION-DISPATCHER.md`.

The AI Safety Gate does not re-run rule compliance or risk analysis from scratch — that authority remains owned by `19_REASONING/RULE-EVALUATOR.md` and `19_REASONING/RISK-ASSESSOR.md`. It also does not make the authorization grant/deny decision (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md`). It checks that nothing changed between when those findings were produced and when the action is actually about to execute — new information, a stale decision, or a mismatched action — and blocks dispatch if so. This is the same pattern `20_EXECUTION/ACTION-DISPATCHER.md` uses toward `14_ENGINE/TASK-ROUTER.md`: read an already-made decision rather than re-deciding it.

---

## Responsibilities

- Confirm the concrete action about to be dispatched matches the action `19_REASONING/RULE-EVALUATOR.md` and `19_REASONING/RISK-ASSESSOR.md` evaluated.
- Detect staleness — elapsed time, changed platform state, or changed permissions since those findings were produced.
- Confirm `24_SECURITY/AUTHORIZATION-MANAGER.md` has not since revoked or altered the relevant authorization.
- Block dispatch when the action, rule finding, risk finding, or authorization no longer match.
- Record every gate evaluation.

---

## Inputs

The AI Safety Gate receives:

- The concrete action proposed for dispatch (from `19_REASONING/DECISION-ENGINE.md` via `34_AIDRIVER/TOOL-SELECTOR.md`)
- The rule compliance finding (from `19_REASONING/RULE-EVALUATOR.md`)
- The risk assessment (from `19_REASONING/RISK-ASSESSOR.md`)
- The current authorization status (from `24_SECURITY/AUTHORIZATION-MANAGER.md`)

---

## Outputs

The AI Safety Gate produces:

- A gate decision (Pass / Blocked)
- A staleness or mismatch report, when applicable
- A re-evaluation request, routed back to `19_REASONING/RULE-EVALUATOR.md` or `19_REASONING/RISK-ASSESSOR.md`, when the prior finding no longer applies
- AI safety gate audit records

---

## Gate Evaluation Workflow

1. Receive the concrete action proposed for dispatch.
2. Retrieve the associated rule compliance finding and risk assessment.
3. Confirm the action matches what was evaluated.
4. Confirm the findings have not gone stale.
5. Confirm current authorization from `24_SECURITY/AUTHORIZATION-MANAGER.md`.
6. Pass the action to `20_EXECUTION/ACTION-DISPATCHER.md`, or block it and request re-evaluation.
7. Record audit information.

---

## Gate Decisions

- Pass
- Blocked — Stale Finding
- Blocked — Action Mismatch
- Blocked — Authorization Revoked
- Blocked — Escalated to `19_REASONING/RULE-EVALUATOR.md` or `19_REASONING/RISK-ASSESSOR.md`

---

## Safety Rules

The AI Safety Gate must never:

- Independently re-decide rule compliance or risk level.
- Grant or deny authorization itself.
- Pass an action whose rule or risk finding is stale or mismatched.
- Suppress a blocked outcome.
- Execute the action directly.
- Alter audit evidence.

---

## Failure Handling

If gate evaluation fails:

- Default to Blocked.
- Preserve evaluation inputs.
- Record the failure.
- Notify `19_REASONING/AI-DRIVER.md`.
- Escalate persistent failures.
- Maintain audit continuity.

---

## Audit Requirements

Every gate evaluation records:

- Gate evaluation ID
- Timestamp
- Action ID
- Rule finding reference
- Risk finding reference
- Authorization status reference
- Gate decision
- Final outcome

---

## Success Criteria

The AI Safety Gate succeeds when:

- No action reaches `20_EXECUTION/ACTION-DISPATCHER.md` with a stale, mismatched, or revoked finding.
- Re-evaluation requests are routed to the correct owning component.
- Gate decisions remain fast and do not duplicate upstream analysis.
- Audit records remain complete.

---

## Permission Boundary

The AI Safety Gate may confirm that an already-produced rule finding, risk finding, and authorization status still apply to the concrete action about to be dispatched, and may block dispatch when they do not.

It must not perform rule compliance analysis, risk assessment, or authorization decisions itself — those remain owned by `19_REASONING/RULE-EVALUATOR.md`, `19_REASONING/RISK-ASSESSOR.md`, and `24_SECURITY/AUTHORIZATION-MANAGER.md` respectively.

---

## Domain Rule

Gate evaluation applies identically regardless of domain; domain-specific rule and risk content is carried in the findings it reads, not re-interpreted by the gate itself.

---

## Rule

No AI-driven action may reach `20_EXECUTION/ACTION-DISPATCHER.md` without a Pass decision from the AI Safety Gate confirming its rule finding, risk finding, and authorization are current.
