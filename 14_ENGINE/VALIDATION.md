# SquirrelForge Engine Validation

Version: 1.1.0
Status: Stable
Owner: Engine Maintainers
Depends On: `03_CHECKLISTS`, `14_ENGINE/EXECUTION-PLANNER.md`, `14_ENGINE/STATE-MANAGER.md`, `19_REASONING`, `20_EXECUTION`, `23_GOVERNANCE`, `24_SECURITY`, `29_TESTING`, applicable domain layers
Used By: Engine, Workflow Selector, Task Router, Output Rules, Reporting
Last Updated: 2026-07-25

## Purpose

Engine Validation coordinates the validation requirements for an active request, workflow, task, execution result, and output.

It converts acceptance criteria, mandatory rules, risk, workflow requirements, execution evidence, and applicable quality gates into an ordered validation plan. It then evaluates referenced evidence and produces a standardized validation record that the State Manager, Output Rules, reporting, and downstream interfaces can consume.

Engine Validation does not perform every check itself. Test execution, policy decisions, security review, runtime execution, governance waivers, and domain-specific checks remain owned by their authoritative components.

---

## Validation Principle

> A result is acceptable only when every required validation item has passed or has an explicitly permitted disposition that is fully reported.

A changed file, generated artifact, successful command, attempted execution, or model assertion is not proof of completion by itself.

Validation must fail closed for required safety, security, authorization, policy, integrity, and governance gates. Missing optional evidence may permit a limited result only when the limitation is explicit and the applicable policy allows it.

---

## Inputs

Validation should receive:

- request, goal, and acceptance criteria,
- expected output and artifact references,
- project and domain context,
- selected primary and supporting workflows,
- execution plan and task identifiers,
- applicable mandatory rules and permission references,
- risk assessment and required quality gates,
- execution result and change-set references,
- test plans, test reports, review findings, and other evidence references,
- retry, repair, rollback, and recovery constraints,
- current State Manager record,
- and approved waiver references, when any exist.

Unknown, stale, unverifiable, or missing inputs must remain explicit. Validation must not invent evidence, infer a pass from absence of a failure, or treat a waiver request as an approved waiver.

---

## Responsibilities

Engine Validation must:

- derive applicable validation items and their owners,
- order validation stages and preserve dependencies,
- define pass, fail, unavailable, and waiver criteria before evaluating evidence,
- confirm evidence identity, scope, freshness, and provenance,
- aggregate authoritative results without replacing their owners,
- stop dependent validation after a required blocking failure,
- route repair to the earliest responsible lifecycle phase,
- bound retries and prevent unchanged retry loops,
- require re-validation of repaired and invalidated dependent items,
- decide engine-level acceptance or rejection,
- update validation state through the State Manager,
- and emit one standardized validation record.

---

## Validation Stages

Stages run in order unless the validation plan documents that independent items may run in parallel. A required blocking failure stops dependent stages; already-produced evidence is preserved.

| Order | Stage | Required Checks | Pass Criteria | Failure Condition |
|---|---|---|---|---|
| 1 | `INPUT` | Required identifiers, goal, acceptance criteria, expected output, project context, workflow, and validation plan inputs are present and internally consistent. | All required inputs are present, typed or structured as required, and trace to the active request. | A required input is missing, malformed, contradictory, stale, or belongs to another request. |
| 2 | `STRUCTURE` | Result shape, required artifacts, interface schema, file or document structure, and declared output contract are valid. | Every required structural rule and contract check passes. | A required artifact is absent, unreadable, malformed, or contract-incompatible. |
| 3 | `RULE_POLICY` | Mandatory rules, scope, permissions, security controls, privacy requirements, and applicable governance policy are satisfied. | Every required authoritative rule or policy owner reports pass or an approved policy-permitted disposition. | Any mandatory rule, authorization, security, privacy, or non-waivable governance gate fails or lacks evidence. |
| 4 | `ACCEPTANCE` | The result is mapped to each acceptance criterion and requested outcome. | Every required acceptance criterion has passing, scoped evidence. | Any required criterion fails, is uncovered, or is supported only by assertion. |
| 5 | `QUALITY` | Applicable code quality, documentation, accessibility, performance, compatibility, domain, and checklist requirements are evaluated. | All required quality items pass; optional unavailable items are classified and disclosed. | A required quality item fails or a required quality class has no acceptable evidence. |
| 6 | `RUNTIME` | Applicable syntax, static analysis, build, unit, integration, system, smoke, or runtime behavior checks complete in the intended environment. | Required commands or authoritative test reports pass for the identified version, environment, and scope. | A required check fails, cannot execute safely, or produces invalid, stale, or mismatched evidence. |
| 7 | `RECOVERY` | Required rollback, backup, checkpoint, retry, or recovery behavior is available and verified to the risk-appropriate level. | Every recovery requirement in the execution plan has acceptable evidence. | A required recovery mechanism is absent, failed, or falsely claimed. |
| 8 | `OUTPUT` | The completion report accurately represents changes, evidence, failures, unavailable checks, waivers, limitations, and next action. | Reported claims match the validation record and expose all material limitations. | The output overclaims completion, hides a failure, omits a material limitation, or exposes prohibited data. |

Only applicable stages and items should be required. `NOT_REQUIRED` must include a reason; it is not a shortcut around applicability analysis.

---

## Validation Item Contract

Each validation item must define:

| Field | Requirement |
|---|---|
| `item_id` | Stable identifier unique within the validation run. |
| `stage` | One stage from the ordered stage list. |
| `requirement_ref` | Acceptance criterion, rule, risk, workflow step, contract, checklist, or gate being validated. |
| `description` | Concise statement of what is being checked. |
| `required` | Whether this item gates acceptance. |
| `blocking` | Whether failure stops dependent validation immediately. |
| `owner_ref` | Authoritative component responsible for producing or deciding the evidence. |
| `method` | Check, test, review, comparison, or inspection method. |
| `expected` | Predeclared success condition. |
| `evidence_refs` | Immutable or stable references to evidence actually evaluated. |
| `status` | Current validation state. |
| `attempt` | One-based attempt number for this item. |
| `failure_code` | Typed failure code when the item did not pass. |
| `observed` | Concise actual result, without secrets or unnecessary sensitive data. |
| `invalidates` | Dependent item identifiers that must be re-run if this item changes or is repaired. |

An item may be marked `PASSED` only when its expected condition is met by authoritative, applicable evidence.

---

## Evidence Rules

Evidence is acceptable only when it is:

- attributable to an authoritative owner or identified tool,
- correlated to the active request, task, execution, artifact, version, or change set,
- scoped to the requirement it supports,
- fresh enough that intervening changes have not invalidated it,
- reproducible or inspectable where policy requires,
- complete enough to support the recorded conclusion,
- and stored or referenced according to security, privacy, retention, and observability rules.

Evidence must identify, when relevant:

- command or check performed,
- environment and configuration reference,
- artifact, commit, build, or version tested,
- start and completion timestamps,
- result or exit status,
- summary and failure details,
- and evidence artifact or report reference.

A prior pass becomes `STALE` when a relevant artifact, dependency, environment, rule, acceptance criterion, or validation method changes. Stale evidence cannot satisfy a required item until revalidated.

---

## Validation States

| State | Meaning |
|---|---|
| `NOT_REQUIRED` | Applicability analysis determined the item is not required; a reason is recorded. |
| `REQUIRED` | Validation is required but has not started. |
| `PENDING` | Validation is underway or waiting on evidence. |
| `PASSED` | Applicable authoritative evidence met the expected condition. |
| `FAILED` | Evidence did not meet the expected condition. |
| `UNAVAILABLE` | Validation could not be performed; reason and impact are recorded. |
| `WAIVED` | An authorized governance decision explicitly waived the item; waiver reference, scope, and expiry are recorded. |
| `STALE` | Earlier evidence was invalidated by a relevant change and must be produced again. |
| `CANCELLED` | The validation item was cancelled because the request or owning task was cancelled. |

`UNAVAILABLE`, `WAIVED`, and `NOT_REQUIRED` are not synonyms for `PASSED`.

---

## Retry and Repair Behavior

Validation retry is allowed only when the failure is plausibly transient or new evidence can be produced without changing the result under review.

Repair is required when the result, plan, implementation, configuration, permission, or environment must change to satisfy the item.

For every retry or repair:

1. Record the failed item, evidence, failure code, and responsible lifecycle phase.
2. Classify the next action as `RETRY`, `REPAIR`, `REROUTE`, `ROLLBACK`, `RECOVERY`, `CLARIFICATION`, or `REJECT`.
3. Confirm the action is within scope and permission boundaries.
4. Define the changed condition expected to make another attempt meaningful.
5. Enforce the validation plan's attempt and time limits.
6. Preserve prior attempts and evidence; never overwrite failure history.
7. Return repair to the earliest responsible lifecycle phase.
8. Re-run the repaired item and every dependent item listed as invalidated.
9. Recompute the overall validation decision.

The Engine must not:

- retry an unchanged deterministic failure,
- use retries to evade a policy or authorization denial,
- silently broaden scope during repair,
- allow the validating component to approve its own governance waiver,
- continue after the attempt limit,
- or replace rollback or recovery with repeated execution.

Default limits must come from the selected workflow, execution plan, risk policy, or runtime configuration. If no limit is defined, validation may request one controlled repair attempt for a non-security, non-policy, reversible defect; otherwise it must stop and report the missing policy.

Security, authorization, privacy, integrity, and non-waivable policy failures are not automatically retryable. They require correction by the authoritative owner and fresh validation evidence.

---

## Acceptance and Rejection Rules

Engine Validation produces one overall decision:

| Decision | Conditions |
|---|---|
| `ACCEPTED` | Every required item is `PASSED`; no blocking failure, stale evidence, unresolved recovery need, or material reporting defect remains. |
| `ACCEPTED_WITH_LIMITATIONS` | Every non-waivable required gate passed; any `UNAVAILABLE` or `WAIVED` item is explicitly permitted by policy, does not hide material safety or correctness risk, and is fully disclosed. |
| `REPAIR_REQUIRED` | One or more failures are repairable within approved scope, permissions, recovery boundaries, and remaining attempt limits. |
| `CLARIFICATION_REQUIRED` | A material ambiguity or missing user decision prevents a defensible validation conclusion. |
| `BLOCKED` | Required evidence, capability, permission, environment, dependency, or authoritative decision is unavailable and no safe in-scope route can proceed. |
| `RECOVERY_REQUIRED` | The result or environment is partial, inconsistent, unsafe, or requires rollback or restoration before further work. |
| `REJECTED` | A required non-waived item failed and no approved repair remains; a non-waivable gate failed; attempt limits were exhausted; or the result is outside the approved goal, scope, rules, or permissions. |

`ACCEPTED_WITH_LIMITATIONS` is prohibited when the unavailable or waived item is a non-waivable safety, security, authorization, privacy, integrity, or mandatory policy gate.

A task may move to `COMPLETED` only after `ACCEPTED` or policy-permitted `ACCEPTED_WITH_LIMITATIONS`. Other decisions must map to the corresponding State Manager lifecycle and task states without erasing completed evidence.

---

## Standard Validation Object

The validator must emit a versioned record with this logical shape:

```json
{
  "schema_version": "1.0.0",
  "validation_id": "val_...",
  "request_id": "req_...",
  "correlation_id": "cor_...",
  "trace_id": "trace_...",
  "goal_id": "goal_...",
  "workflow_id": "workflow_...",
  "workflow_instance_id": "workflow_run_...",
  "execution_id": "exec_...",
  "task_id": "task_...",
  "subject": {
    "type": "artifact|state_change|decision|response|execution_result",
    "refs": ["artifact_or_result_ref"],
    "version_ref": "commit_build_or_revision_ref"
  },
  "plan_ref": "validation_plan_ref",
  "risk_ref": "risk_assessment_ref",
  "started_at": "RFC-3339 timestamp",
  "completed_at": "RFC-3339 timestamp or null",
  "decision": "ACCEPTED|ACCEPTED_WITH_LIMITATIONS|REPAIR_REQUIRED|CLARIFICATION_REQUIRED|BLOCKED|RECOVERY_REQUIRED|REJECTED",
  "stages": [
    {
      "stage": "INPUT",
      "status": "PASSED",
      "required": true,
      "item_ids": ["val_item_1"]
    }
  ],
  "items": [
    {
      "item_id": "val_item_1",
      "stage": "INPUT",
      "requirement_ref": "acceptance_criterion_or_rule_ref",
      "description": "Required request inputs are present and consistent.",
      "required": true,
      "blocking": true,
      "owner_ref": "authoritative_component_ref",
      "method": "schema_and_consistency_check",
      "expected": "All required inputs are valid and correlated.",
      "evidence_refs": ["evidence_ref"],
      "status": "PASSED",
      "attempt": 1,
      "failure_code": null,
      "observed": "Required inputs passed validation.",
      "invalidates": []
    }
  ],
  "summary": {
    "total": 1,
    "passed": 1,
    "failed": 0,
    "unavailable": 0,
    "waived": 0,
    "stale": 0,
    "not_required": 0,
    "cancelled": 0
  },
  "attempts": [
    {
      "attempt": 1,
      "trigger": "initial",
      "changed_condition": null,
      "item_ids": ["val_item_1"],
      "outcome": "PASSED"
    }
  ],
  "limitations": [],
  "residual_risks": [],
  "waiver_refs": [],
  "repair": {
    "action": null,
    "responsible_phase": null,
    "remaining_attempts": 0,
    "invalidated_item_ids": []
  },
  "state_transition": {
    "lifecycle_state": "REVIEW",
    "task_state": "COMPLETED"
  },
  "report_refs": [],
  "next_action": "Prepare truthful output from the accepted record."
}
```

### Object Invariants

- `schema_version`, `validation_id`, `request_id`, `correlation_id`, `subject`, `decision`, `items`, `summary`, and `next_action` are required.
- Parent identifiers already established by the System Orchestrator must be propagated, not regenerated.
- The summary counts must equal the item states.
- `completed_at` is required for every terminal decision.
- `PASSED` items require at least one evidence reference unless the validation method itself produces the referenced validation record.
- `FAILED`, `UNAVAILABLE`, `WAIVED`, and `STALE` items require an observed reason; `WAIVED` also requires an authorized waiver reference.
- `ACCEPTED` permits no required item outside `PASSED`.
- `ACCEPTED_WITH_LIMITATIONS` requires at least one disclosed limitation or waiver and a policy basis for proceeding.
- `REPAIR_REQUIRED` requires a responsible phase, next action, and remaining attempt allowance.
- `REJECTED`, `BLOCKED`, and `RECOVERY_REQUIRED` require a reason and safe next action.
- Evidence and reports must be referenced rather than embedded when embedding would duplicate authoritative records or expose sensitive data.

---

## State and Reporting Integration

The standardized validation object is the authoritative engine-level validation summary for the validated subject. The underlying test reports, security decisions, reviews, and governance records remain authoritative for their own findings.

The State Manager consumes:

- overall decision,
- item and stage states,
- active blocker or recovery requirement,
- responsible lifecycle phase,
- invalidated items,
- limitations and residual risks,
- and next action.

Output Rules and Reporting consume:

- decision and subject references,
- changed or validated artifact references,
- validation performed and passed,
- failures and unavailable checks,
- approved waivers,
- limitations and residual risks,
- repair or recovery status,
- and next action.

Final output must distinguish validation performed, passed, failed, unavailable, waived, stale, cancelled, and not applicable. It must not expose secrets or claim acceptance beyond the scope of the evidence.

---

## Domain Rule

Domain-specific validation loads only when the active request touches that domain.

For WordPress work, applicable WordPress checks and references are selected from `38_WORDPRESS` by the routed workflow and domain manager.

For non-WordPress work, WordPress checks must not be loaded, required, or reported automatically.

---

## Rule

> Engine Validation coordinates evidence, applies explicit completion gates, bounds repair loops, and emits a traceable validation object. It must never convert missing, stale, attempted, waived, or unavailable evidence into a pass.
