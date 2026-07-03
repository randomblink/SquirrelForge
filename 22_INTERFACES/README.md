# SquirrelForge Interfaces

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: System Architecture
Used By: All operational layers
Last Updated: 2026-07-01

## Purpose
Defines stable contracts that prevent direct coupling between layers.

## Components
Agent API, Workflow API, Memory API, and Engine API.

## Execution Order
Validate request → invoke interface → validate response → record event.

## Dependencies
Canonical data models, versioning, and permissions.

## Rules
Interfaces are versioned; breaking changes require a major version and migration path.

## Diagram
```text
Consumer → Versioned Interface → Provider
```
