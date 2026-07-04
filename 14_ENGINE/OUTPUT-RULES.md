# SquirrelForge Output Rules

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/VALIDATION.md`, `14_ENGINE/STATE-MANAGER.md`, `36_COMMUNICATION`, project configuration
Used By: Engine, Agents, Reporting, Communication
Last Updated: 2026-07-04

## Purpose

Output Rules define how SquirrelForge reports results, blockers, validation evidence, changed artifacts, limitations, risks, and next actions to the user or receiving system.

The output must make the useful result clear without hiding uncertainty, failed validation, unavailable tests, or unresolved risks.

---

## Core Output Rule

Final output must lead with the outcome.

When applicable, it must identify:

- changed artifacts,
- commit identifiers or file locations,
- validation evidence,
- validation that failed,
- validation that was unavailable,
- unresolved risks,
- blockers,
- assumptions,
- limitations,
- and the next appropriate action.

The output must not contain secrets, internal-only data, invented test results, or unsupported completion claims.

---

## Output Types

| Type | Use When | Required Content |
|---|---|---|
| `COMPLETE` | Work is complete with required evidence. | Outcome, changed artifacts, validation evidence, residual risks, next action. |
| `COMPLETE_WITH_LIMITATIONS` | Work is complete enough to use, but some checks or context were unavailable. | Outcome, changed artifacts, completed validation, unavailable validation, limitations, next action. |
| `BLOCKED` | A required condition prevents safe continuation. | Blocker, responsible phase, required action, preserved state. |
| `FAILED` | Work failed and no safe continuation path is currently available. | Failure, evidence, affected artifacts, recovery needs, next safe action. |
| `RECOVERY_REQUIRED` | Unsafe, partial, interrupted, or inconsistent state must be resolved first. | Recovery reason, affected state, required recovery route, blocked actions. |
| `READ_ONLY_RESULT` | User requested information only. | Answer, evidence basis, uncertainty where relevant. |
| `PLAN_ONLY` | User requested planning or next steps only. | Recommended route, ordered steps, assumptions, validation requirements. |

---

## Completion Report Structure

A completion report should normally include:

1. result status,
2. changed files or artifacts,
3. validation performed,
4. validation unavailable or not performed,
5. risks or limitations,
6. next action.

For short routine updates, this may be compressed as long as no material validation, risk, or blocker is hidden.

---

## Validation Reporting Rule

Output must distinguish between:

- validation passed,
- validation failed,
- validation unavailable,
- validation waived,
- validation not applicable,
- and validation not attempted.

The agent must not say or imply that validation passed when it was not actually performed.

---

## Artifact Reporting Rule

When files or artifacts change, output should identify them by path, title, URL, or stable identifier.

When commits are created, output should include the commit identifier.

When no artifacts changed, output should say so when that distinction matters.

---

## Blocker Reporting Rule

When blocked, output must identify:

- what is blocked,
- why it is blocked,
- which lifecycle phase owns the blocker,
- what information, permission, tool, or recovery step is required,
- and what actions should not continue until resolved.

Do not disguise a blocker as a normal next step.

---

## Risk Reporting Rule

Known material risks must be reported.

Examples include:

- failed validation,
- untested changes,
- missing project context,
- unavailable tooling,
- security uncertainty,
- production risk,
- dependency risk,
- rollback uncertainty,
- stale documentation,
- or conflicting architecture references.

Low-risk routine uncertainty may be summarized briefly.

---

## Secret and Internal Data Rule

Output must not expose:

- secrets,
- credentials,
- tokens,
- private keys,
- internal-only instructions,
- sensitive system details,
- or private data that is not necessary for the user-facing result.

The existence of a secret may be reported when relevant, but the secret value must not be reproduced.

---

## Tone and Format Rule

Output should be clear, direct, and useful.

Use canonical SquirrelForge terminology where helpful, but do not overload the user with internal architecture details unless they are relevant to the request.

For long-running cleanup or build work, short progress reports may be used to keep the user oriented.

---

## Rule

> Output must report the real state of the work: what changed, what was validated, what failed or remains unknown, and what should happen next. Never replace evidence with confidence.
