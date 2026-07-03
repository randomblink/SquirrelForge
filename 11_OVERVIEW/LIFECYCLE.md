# SquirrelForge Lifecycle

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `SYSTEM-ARCHITECTURE.md`
Used By: Engine, Agents, Execution, Governance
Last Updated: 2026-07-01

```text
Request → Planning → Execution → Review → Release → Archive
```

1. **Request:** capture the goal, constraints, context, and expected output.
2. **Planning:** select workflows, resolve dependencies, assess risk, and create tasks.
3. **Execution:** dispatch authorized actions and record checkpoints and events.
4. **Review:** validate correctness, security, performance, documentation, and tests.
5. **Release:** pass quality gates, version the result, and produce release records.
6. **Archive:** retain decisions, outcomes, reports, and reusable knowledge according to policy.

Progression requires the prior phase's exit criteria. A failed gate returns work to the earliest responsible phase.
