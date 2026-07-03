# SquirrelForge Governance

Version: 1.0.0
Status: Stable
Owner: Governance Maintainers
Depends On: System Architecture
Used By: All maintained components
Last Updated: 2026-07-01

## Purpose
Controls versions, changes, quality gates, and deprecation across the framework.

## Components
Versioning, Change Management, Quality Gates, and Deprecation Policy.

## Execution Order
Propose → assess → approve → implement → test → release → deprecate/archive when necessary.

## Dependencies
Ownership metadata, tests, release evidence, and interface compatibility.

## Rules
No undocumented breaking change; no release without required gates and accountable owner.

## Diagram
```text
Proposal → Review → Change → Gates → Release → Deprecation
```
