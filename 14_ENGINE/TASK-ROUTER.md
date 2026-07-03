# SquirrelForge Task Router

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: Execution Plan, Workflow Selector, Agent API
Used By: Coordination
Last Updated: 2026-07-01

Routes each ready task to the workflow and agent capable of satisfying its requirements. Routing must honor dependencies, permissions, ownership, capacity, and task priority, and must emit a traceable routing record.
