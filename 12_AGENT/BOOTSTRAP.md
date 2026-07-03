# SquirrelForge Agent Bootstrap

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `AGENT-PROFILE.md`, `COLLECTION-MANIFEST.md`
Used By: Agent hosts and Project Loader
Last Updated: 2026-07-01

## Bootstrap Sequence

1. **Identity:** load `AGENT-PROFILE.md` and `01_RULES/SYSTEM-PROMPT.md`.
2. **Mandatory policy:** load `01_RULES/AGENT-BEHAVIOR.md`, `01_RULES/WORDPRESS-RULES.md`, and applicable governance policy.
3. **Runtime policy:** merge configuration in the order Defaults → Project Settings → Model and Tool Config → Permissions.
4. **Project initialization:** execute `14_ENGINE/PROJECT-LOADER.md`; verify the project root, context, state, and available interfaces.
5. **Request intake:** capture the goal, expected output, constraints, acceptance criteria, and missing information.
6. **Capability selection:** use `CAPABILITY-ROUTER.md` and `14_ENGINE/WORKFLOW-SELECTOR.md` to select one primary workflow, responsible agent, supporting skills, and required tests.
7. **Reasoning and planning:** evaluate rules, risks, tradeoffs, strategy, confidence, dependencies, task decomposition, and execution order.
8. **Execution:** route tasks through coordination and the Execution Engine using only allowed interfaces and permissions.
9. **Verification:** run validation, checklists, applicable test levels, and governance quality gates.
10. **Completion:** report results using Output Rules, store appropriate memory, and archive lifecycle records.

## Ready Check

- [ ] Identity and mandatory rules loaded
- [ ] Project root and settings validated
- [ ] Permissions and tools known
- [ ] Goal and acceptance criteria recorded
- [ ] Primary workflow and owner selected
- [ ] Risks, dependencies, tests, and rollback needs identified
- [ ] State, logging, and checkpoints initialized

Execution must not begin until every applicable ready-check item passes.
