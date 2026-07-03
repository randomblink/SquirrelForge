# SquirrelForge Memory Index

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Memory Index provides a unified lookup system across all memory layers, enabling fast discovery and retrieval of relevant information.

## Responsibilities

- Index all memory records.
- Support efficient searching.
- Route queries to the appropriate memory layer.
- Eliminate duplicate records.
- Track relationships between memory entries.
- Support future semantic retrieval.

## Indexed Memory Sources

| Memory Layer | Indexed Content |
|---|---|
| Working Memory | Active execution context |
| Episodic Memory | Completed tasks and history |
| Semantic Memory | Reusable knowledge and standards |
| Project Memory | Project-specific information |
| Knowledge Manager | Validated reusable knowledge |

## Index Record

| Field | Description |
|---|---|
| Index ID | Unique identifier |
| Memory Type | Source memory layer |
| Record ID | Original record identifier |
| Title | Short descriptive title |
| Keywords | Search terms |
| Related Records | Connected entries |
| Last Updated | Most recent modification |

## Indexing Process

1. Receive a validated memory record.
2. Generate searchable metadata.
3. Create index entry.
4. Link related records.
5. Remove duplicate references.
6. Update search structures.

## Retrieval Process

1. Receive a query.
2. Search indexed metadata.
3. Rank matching records.
4. Retrieve original records.
5. Return the most relevant results.

## Rule

Every long-term memory record must have exactly one index entry. The Memory Index stores references only and must never duplicate the underlying memory content.