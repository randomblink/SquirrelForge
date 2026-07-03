# SquirrelForge Overview

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: None
Used By: All layers
Last Updated: 2026-07-01

## Purpose

Provides the authoritative entry point for SquirrelForge architecture, vocabulary, and lifecycle.

## Components

- `SYSTEM-ARCHITECTURE.md`: layers and control flow.
- `GLOSSARY.md`: canonical terminology.
- `LIFECYCLE.md`: request-to-archive lifecycle.

## Execution Order

System Architecture → Glossary → Lifecycle → Rules → Engine.

## Dependencies

None. Downstream documents depend on this layer.

## Rules

Overview documents define system-wide concepts; layer documents may refine but must not contradict them.

## Diagram

```text
Architecture ─┬─> Glossary
              └─> Lifecycle ─> Rules ─> Engine
```
