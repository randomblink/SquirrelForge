# SquirrelForge Agent Bootstrap

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `AGENT-PROFILE.md`, `COLLECTION-MANIFEST.md`, `CAPABILITY-ROUTER.md`
Used By: Agent hosts and Project Loader
Last Updated: 2026-07-04

## Purpose

The Agent Bootstrap defines the required startup sequence before SquirrelForge accepts, plans, modifies, tests, or completes project work.

Bootstrap exists to prevent the agent from acting with guessed identity, missing rules, unknown permissions, stale context, or unverified project state.

---

## Bootstrap Principle

> Load the operating context before changing the project context.

SquirrelForge must not perform project-changing execution until the applicable bootstrap checks pass.

---

## Bootstrap Sequence

1. **Identity**
   - Load `AGENT-PROFILE.md`.
   - Establish the agent role, operating posture, constraints, and success criteria.

2. **Collection Manifest**
   - Load `COLLECTION-MANIFEST.md`.
   - Identify the authoritative source layers needed for the request.
   - Do not duplicate source-layer responsibilities inside the Agent Layer.

3. **Mandatory Rules**
   - Load the applicable mandatory rules from `01_RULES`.
   - Always load general agent behavior rules.
   - Load domain-specific rules only when the request requires that domain.

4. **Runtime Policy**
   - Merge configuration in this order:
     1. defaults,
     2. project settings,
     3. model and tool configuration,
     4. permissions,
     5. current request constraints.

5. **Project Initialization**
   - Execute or consult `14_ENGINE/PROJECT-LOADER.md`.
   - Verify the project root, project type, current state, available interfaces, and relevant local instructions.
   - Run the Repository Identity Verification Procedure in `14_ENGINE/PROJECT-LOADER.md` before any write operation, and re-run it after any `cd` into a different project.

6. **Request Intake**
   - Capture the user goal, expected output, constraints, acceptance criteria, and missing information.
   - Determine whether the request is read-only, planning-only, project-changing, destructive, external, or deployment-related.

7. **Capability Selection**
   - Use `CAPABILITY-ROUTER.md` and `14_ENGINE/WORKFLOW-SELECTOR.md`.
   - Select one primary workflow, responsible agent, supporting skills, needed tools, and required validation.

8. **Domain Knowledge Loading**
   - Load only the domain knowledge required for the request.
   - For WordPress work, load the relevant references from `38_WORDPRESS`.
   - For non-WordPress work, do not force-load WordPress-specific rules or handbooks.

9. **Reasoning and Planning**
   - Evaluate rules, risks, tradeoffs, strategy, confidence, dependencies, task decomposition, and execution order.
   - Identify rollback needs before executing risky actions.

10. **Permission Review**
    - Confirm the planned actions fit the active execution boundary.
    - Escalate, pause, or refuse actions that exceed allowed permissions.

11. **Execution**
    - Route tasks through coordination and the Execution layer.
    - Use only allowed interfaces, tools, permissions, and project paths.

12. **Verification**
    - Run applicable validation, checklists, test levels, and governance quality gates.
    - Do not claim validation evidence that was not actually produced.

13. **Completion**
    - Report what changed, what was checked, what remains uncertain, and what should happen next.
    - Store appropriate memory and lifecycle records only when allowed.

---

## Ready Check

- [ ] Agent profile loaded
- [ ] Collection manifest loaded
- [ ] Mandatory general rules loaded
- [ ] Domain-specific rules loaded only when applicable
- [ ] Project root and settings validated
- [ ] Repository identity verified against the requested project, re-verified after any project switch
- [ ] Permissions and tools known
- [ ] Goal and acceptance criteria recorded
- [ ] Request risk classification identified
- [ ] Primary workflow and owner selected
- [ ] Relevant domain knowledge loaded
- [ ] Risks, dependencies, tests, and rollback needs identified
- [ ] State, logging, and checkpoints initialized where applicable

Execution must not begin until every applicable ready-check item passes.

---

## Domain Loading Rule

Domain documents are loaded by need, not by habit.

Examples:

- WordPress plugin request → load relevant `38_WORDPRESS` plugin, security, coding standards, testing, and performance references.
- General architecture request → load architecture, engine, reasoning, governance, and documentation references.
- Runtime implementation request → load source code, tests, execution rules, and relevant interface contracts.
- Documentation cleanup request → load affected READMEs, architecture documents, manifests, and cross-references.

This keeps bootstrap focused and prevents one domain from leaking into unrelated work.

---

## Failure States

Bootstrap may stop with one of these states:

| State | Meaning |
|---|---|
| `READY` | Required bootstrap checks passed. |
| `READY_WITH_LIMITATIONS` | Work may continue, but unavailable tools or missing context must be disclosed. |
| `BLOCKED` | Required context, permissions, or safety conditions are missing. |
| `RECOVERY_REQUIRED` | Interrupted or unsafe previous work must be resolved before continuing. |
| `BOOT_FAILED` | Bootstrap could not complete and no safe degraded path exists. |

---

## Rule

> SquirrelForge must complete the applicable Agent Bootstrap before project-changing execution begins.

If bootstrap is blocked, the agent must state the blocker instead of guessing or silently proceeding.
