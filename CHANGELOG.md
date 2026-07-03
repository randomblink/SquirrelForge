# Changelog

Version: 1.0.0
Status: Draft
Owner: Release Maintainers
Depends On: `23_GOVERNANCE/VERSIONING.md`
Used By: Contributors and releases
Last Updated: 2026-07-01

All notable changes to SquirrelForge are recorded here.

## Unreleased

### Added

- Layered system architecture, lifecycle, glossary, configuration, interfaces, governance, execution, and testing documentation.
- README navigation for every active framework layer.
- Standard document metadata.
- Eight pipeline role agents (`src/Agent/Roles/`: Architect, Planner, Developer, Reviewer, Security, Performance, Documentation, Release) implementing the roles specified in `16_AGENTS/`.
- `AgentOrchestrator` (`src/Agent/AgentOrchestrator.php`) coordinating the Architect -> Planner -> Developer -> Reviewer -> Security -> Performance -> Documentation -> Release handoff sequence.
- `tests/RolePipelineTest.php` covering the pipeline's happy path and its stop/hold conditions.

### Fixed

- `ARCHITECTURE.md`: closed an unclosed code fence that was suppressing all Markdown formatting past "Primary Flow", and corrected stale directory references (`31_OBSERVABILITY` -> `27_OBSERVABILITY`, `27_LEARNING` -> `30_LEARNING`).
- `11_OVERVIEW/PROJECT-INVENTORY.md`: refreshed to match the actual post-restructure directory tree and runtime source files; previous "Missing Pieces" list was stale (the listed items already existed).

### Changed

- `AgentServiceProvider` now registers and boots the eight role agents and the orchestrator instead of leaving agent registration to future modules.
