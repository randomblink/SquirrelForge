# SquirrelForge Experience Store

Version: 1.0.0
Status: Stable
Owner: Learning Maintainers
Depends On: FEEDBACK-COLLECTOR.md, EVALUATION-ENGINE.md, PATTERN-DETECTOR.md, LEARNING-GOVERNANCE.md, 37_STORAGE
Used By: EVALUATION-ENGINE.md, PATTERN-DETECTOR.md, LEARNING-MANAGER.md, ADAPTATION-MANAGER.md
Last Updated: 2026-07-08

## Purpose

The Experience Store owns authoritative Learning-domain experience records, identifiers, metadata, relationships, and retrieval references. Raw persistence infrastructure remains with `37_STORAGE`.

## Responsibilities

- Create stable Learning experience identifiers.
- Record source, workflow, event-category, confidence, evidence, evaluation, pattern, governance, and adaptation references.
- Maintain Learning-domain experience relationships and lifecycle metadata.
- Provide metadata and record retrieval for evaluation and pattern analysis.
- Preserve references to authoritative source records without duplicating their ownership.
- Coordinate persistence and retrieval operations through Storage owners.

## Boundary

The Experience Store does not:

- own raw file, blob, database, backup, archive, or storage infrastructure;
- analyze experiences, evaluate learning value, or detect patterns;
- change governance decisions or adaptation outcomes;
- own general audit-trail infrastructure;
- perform authorization decisions;
- execute storage recovery or retry policy independently.

## Integrity Rule

Learning-domain experience records preserve stable identity and lineage. Persistence guarantees, backup, restoration, and physical storage lifecycle are supplied by authoritative Storage components.