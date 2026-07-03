# SquirrelForge Memory API

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: Memory Architecture, Retention
Used By: Engine, Reasoning, Agents
Last Updated: 2026-07-01

`put(record, classification, provenance) → id`; `get(id) → record`; `search(query, scope) → matches`; `promote(id, evidence) → result`; `archive(id, reason) → result`. Access, retention, and provenance checks apply to every operation.
