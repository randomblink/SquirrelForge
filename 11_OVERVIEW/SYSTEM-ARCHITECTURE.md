# SquirrelForge System Architecture

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: None
Used By: All layers
Last Updated: 2026-07-01

## Architecture Flow

```text
User
  ↓
Engine
  ↓
Reasoning
  ↓
Planner
  ↓
Agents
  ↓
Coordination
  ↓
Memory
  ↓
Execution
  ↓
Validation
  ↓
Output
```

The Engine owns lifecycle control. Reasoning selects a strategy; planning decomposes it into tasks; agents perform specialized work; coordination manages ownership and handoffs; memory supplies and records context; execution dispatches actions; validation and testing enforce quality; output presents the result. Configuration supplies runtime policy, interfaces prevent direct coupling, and governance controls change.

## Dependency Rule

Layers may depend on documented upstream outputs and interface contracts. They must not rely on another layer's internal state.
