# SquirrelForge Workflow Selector

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: Goal, Workflow Catalog, Rules
Used By: Task Router
Last Updated: 2026-07-01

Selects one primary workflow from request type, expected output, risk, and project configuration. It records the selection rationale and any supporting workflows. Ambiguous or unsupported requests return a clarification or controlled fallback; they do not silently select an unrelated workflow.
